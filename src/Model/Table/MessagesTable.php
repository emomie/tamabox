<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Service\Message\SsrJudge;
use Cake\I18n\FrozenTime;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use Cake\Validation\Validator;
use RuntimeException;

/**
 * Messages Model
 *
 * @property \App\Model\Table\InboxesTable&\Cake\ORM\Association\BelongsTo $Inboxes
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $SenderUsers
 * @property \App\Model\Table\ReportsTable&\Cake\ORM\Association\HasMany $Reports
 * @method \App\Model\Entity\Message newEmptyEntity()
 * @method \App\Model\Entity\Message newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Message[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Message get($primaryKey, $options = [])
 * @method \App\Model\Entity\Message findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Message patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Message[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Message|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Message saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Message[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Message[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Message[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Message[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class MessagesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('messages');
        $this->setDisplayField('ssr_seed');
        $this->setPrimaryKey('id');

        // messages has no `updated_at` column per DB-SCHEMA.md v0.2 §4
        // (immutable post-send; lifecycle encoded via opened_at/deleted_at).
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                ],
            ],
        ]);

        $this->belongsTo('Inboxes', [
            'foreignKey' => 'inbox_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('SenderUsers', [
            'className' => 'Users',
            'foreignKey' => 'sender_user_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('Reports', [
            'foreignKey' => 'message_id',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('inbox_id')
            ->notEmptyString('inbox_id');

        $validator
            ->scalar('sender_user_id')
            ->notEmptyString('sender_user_id');

        $validator
            ->scalar('body')
            ->requirePresence('body', 'create')
            ->notEmptyString('body', '本文を入力してください。')
            ->add('body', 'mbLength', [
                'rule' => function ($value): bool {
                    return is_string($value) && mb_strlen($value) <= 2000;
                },
                'message' => '本文は 2000 文字以内で入力してください。',
            ]);

        $validator
            ->integer('body_length')
            ->greaterThanOrEqual('body_length', 1)
            ->lessThanOrEqual('body_length', 2000)
            ->requirePresence('body_length', 'create')
            ->notEmptyString('body_length');

        $validator
            ->boolean('is_ssr')
            ->requirePresence('is_ssr', 'create')
            ->notEmptyString('is_ssr');

        $validator
            ->decimal('ssr_probability_at_send')
            ->requirePresence('ssr_probability_at_send', 'create')
            ->notEmptyString('ssr_probability_at_send');

        $validator
            ->scalar('ssr_seed')
            ->maxLength('ssr_seed', 64)
            ->requirePresence('ssr_seed', 'create')
            ->notEmptyString('ssr_seed')
            ->add('ssr_seed', 'hex64', [
                'rule' => ['custom', '/^[0-9a-f]{64}$/'],
                'message' => 'ssr_seed must be a 64-char lowercase hex string.',
            ]);

        $validator
            ->scalar('sender_provider')
            ->requirePresence('sender_provider', 'create')
            ->notEmptyString('sender_provider');

        $validator
            ->scalar('sender_handle_snapshot')
            ->maxLength('sender_handle_snapshot', 255)
            ->requirePresence('sender_handle_snapshot', 'create')
            ->notEmptyString('sender_handle_snapshot');

        $validator
            ->scalar('sender_avatar_url_snapshot')
            ->maxLength('sender_avatar_url_snapshot', 2048)
            ->allowEmptyString('sender_avatar_url_snapshot');

        $validator
            ->scalar('sender_profile_url_snapshot')
            ->maxLength('sender_profile_url_snapshot', 2048)
            ->allowEmptyFile('sender_profile_url_snapshot');

        $validator
            ->dateTime('opened_at')
            ->allowEmptyDateTime('opened_at');

        $validator
            ->dateTime('deleted_at')
            ->allowEmptyDateTime('deleted_at');

        $validator
            ->scalar('deleted_reason')
            ->maxLength('deleted_reason', 64)
            ->allowEmptyString('deleted_reason');

        $validator
            ->dateTime('created_at')
            ->notEmptyDateTime('created_at');

        return $validator;
    }

    /**
     * Insert a message with SSR judgement + sender snapshot bake (D-09 + D-29 + MSG-04).
     *
     * The judgement is computed from (Configure.serverSecret, message_id, created_at_micro)
     * via SsrJudge; the snapshot is copied from $senderUser->user_identities cached fields.
     *
     * @param \App\Model\Entity\Inbox $inbox The receiver's inbox (already loaded).
     * @param string $senderUserId UUID of the authenticated sender.
     * @param string $body Message text (validated upstream + here for defense in depth).
     * @return \App\Model\Entity\Message The persisted message entity (with ssr_seed + is_ssr).
     * @throws \RuntimeException If sender's identity lacks cached handle (data inconsistency)
     *   OR if Configure.serverSecret missing (SsrJudge rejects).
     * @throws \Cake\ORM\Exception\PersistenceFailedException On validation/save failure.
     */
    public function sendMessage(
        \App\Model\Entity\Inbox $inbox,
        string $senderUserId,
        string $body
    ): \App\Model\Entity\Message {
        if ($body === '') {
            throw new RuntimeException('sendMessage: body is empty.');
        }
        if (mb_strlen($body) > 2000) {
            throw new RuntimeException('sendMessage: body exceeds 2000 chars.');
        }
        if (!(bool)$inbox->is_accepting) {
            throw new RuntimeException('sendMessage: inbox is not accepting.');
        }

        /** @var \App\Model\Table\UsersTable $usersTable */
        $usersTable = TableRegistry::getTableLocator()->get('Users');
        /** @var \App\Model\Entity\User|null $senderUser */
        $senderUser = $usersTable->find()
            ->where(['Users.id' => $senderUserId])
            ->contain(['UserIdentities'])
            ->first();
        if ($senderUser === null) {
            throw new RuntimeException('sendMessage: sender user not found.');
        }

        /** @var \App\Model\Entity\UserIdentity|null $identity */
        $identity = null;
        // UsersTable::hasOne('UserIdentities') — ORM stores result as 'user_identity' (singular).
        $rawIdentity = $senderUser->get('user_identity');
        if ($rawIdentity instanceof \App\Model\Entity\UserIdentity) {
            $identity = $rawIdentity;
        }
        if ($identity === null) {
            throw new RuntimeException('sendMessage: sender has no user_identity.');
        }

        // === Compute deterministic SSR seed BEFORE INSERT (MSG-02 + MSG-03 + D-12 contract) ===
        $messageId = Text::uuid();
        $createdAt = FrozenTime::now();
        $createdAtMicro = $createdAt->format('Y-m-d H:i:s.u');
        $probability = (string)$inbox->ssr_probability; // DECIMAL(4,3) → '0.100' etc.

        $judge = new SsrJudge();
        $verdict = $judge->judge($messageId, $createdAtMicro, $probability);

        // === Sender snapshot (D-29 / D-32 / D-34 — frozen at SEND time) ===
        $entity = $this->newEntity([
            'id' => $messageId,
            'inbox_id' => (string)$inbox->id,
            'sender_user_id' => $senderUserId,
            'body' => $body,
            'body_length' => mb_strlen($body),
            'is_ssr' => $verdict['is_ssr'],
            'ssr_probability_at_send' => $verdict['ssr_probability_at_send'],
            'ssr_seed' => $verdict['ssr_seed'],
            'sender_provider' => 'bluesky',
            'sender_handle_snapshot' => (string)$identity->handle_cached,
            'sender_avatar_url_snapshot' => $identity->avatar_url_cached !== null
                ? (string)$identity->avatar_url_cached
                : null,
            'sender_profile_url_snapshot' => $identity->profile_url_cached !== null
                ? (string)$identity->profile_url_cached
                : null,
            'created_at' => $createdAt,
        ], ['accessibleFields' => [
            'id' => true,
            'inbox_id' => true,
            'sender_user_id' => true,
            'body' => true,
            'body_length' => true,
            'is_ssr' => true,
            'ssr_probability_at_send' => true,
            'ssr_seed' => true,
            'sender_provider' => true,
            'sender_handle_snapshot' => true,
            'sender_avatar_url_snapshot' => true,
            'sender_profile_url_snapshot' => true,
            'created_at' => true,
        ]]);

        /** @var \App\Model\Entity\Message $saved */
        $saved = $this->saveOrFail($entity);

        return $saved;
    }
}
