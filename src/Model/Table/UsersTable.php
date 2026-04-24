<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Users Model
 *
 * @property \App\Model\Table\InboxesTable&\Cake\ORM\Association\HasOne $Inboxes
 * @property \App\Model\Table\UserIdentitiesTable&\Cake\ORM\Association\HasOne $UserIdentities
 * @property \App\Model\Table\MessagesTable&\Cake\ORM\Association\HasMany $SentMessages
 * @property \App\Model\Table\BlocksTable&\Cake\ORM\Association\HasMany $BlockerBlocks
 * @property \App\Model\Table\BlocksTable&\Cake\ORM\Association\HasMany $BlockedByBlocks
 * @property \App\Model\Table\ReportsTable&\Cake\ORM\Association\HasMany $ReportsMade
 * @method \App\Model\Entity\User newEmptyEntity()
 * @method \App\Model\Entity\User newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\User[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\User get($primaryKey, $options = [])
 * @method \App\Model\Entity\User findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\User patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\User[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\User|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\User saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class UsersTable extends Table
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

        $this->setTable('users');
        $this->setDisplayField('display_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);

        $this->hasOne('Inboxes', [
            'foreignKey' => 'user_id',
        ]);
        $this->hasOne('UserIdentities', [
            'foreignKey' => 'user_id',
        ]);
        $this->hasMany('SentMessages', [
            'className' => 'Messages',
            'foreignKey' => 'sender_user_id',
        ]);
        $this->hasMany('BlockerBlocks', [
            'className' => 'Blocks',
            'foreignKey' => 'blocker_user_id',
        ]);
        $this->hasMany('BlockedByBlocks', [
            'className' => 'Blocks',
            'foreignKey' => 'blocked_user_id',
        ]);
        $this->hasMany('ReportsMade', [
            'className' => 'Reports',
            'foreignKey' => 'reporter_user_id',
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
            ->scalar('display_name')
            ->maxLength('display_name', 64)
            ->requirePresence('display_name', 'create')
            ->notEmptyString('display_name');

        $validator
            ->dateTime('created_at')
            ->notEmptyDateTime('created_at');

        $validator
            ->dateTime('updated_at')
            ->notEmptyDateTime('updated_at');

        $validator
            ->dateTime('deleted_at')
            ->allowEmptyDateTime('deleted_at');

        return $validator;
    }

    /**
     * Custom finder: fetch user by SNS DID via user_identities join.
     *
     * Usage:
     *   $user = $this->Users->find('byDid', ['did' => 'did:plc:...'])->first();
     *
     * Returns a User entity with UserIdentities contained, or null if no match.
     *
     * @param \Cake\ORM\Query $query Base query from find('byDid', ...).
     * @param array<string, mixed> $options Must contain 'did' => non-empty string.
     * @return \Cake\ORM\Query
     */
    public function findByDid(Query $query, array $options): Query
    {
        if (!isset($options['did']) || !is_string($options['did']) || $options['did'] === '') {
            return $query->where(['1 = 0']); // empty result on bad input
        }
        $did = $options['did'];

        return $query
            ->contain(['UserIdentities'])
            ->matching('UserIdentities', function (Query $q) use ($did): Query {
                return $q->where([
                    'UserIdentities.provider' => 'bluesky',
                    'UserIdentities.provider_account_id' => $did,
                ]);
            });
    }
}
