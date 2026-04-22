<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Messages Model
 *
 * @property \App\Model\Table\InboxesTable&\Cake\ORM\Association\BelongsTo $Inboxes
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\ReportsTable&\Cake\ORM\Association\HasMany $Reports
 *
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
        $this->belongsTo('Users', [
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
            ->uuid('inbox_id')
            ->notEmptyString('inbox_id');

        $validator
            ->uuid('sender_user_id')
            ->notEmptyString('sender_user_id');

        $validator
            ->scalar('body')
            ->requirePresence('body', 'create')
            ->notEmptyString('body');

        $validator
            ->integer('body_length')
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
            ->notEmptyString('ssr_seed');

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
}
