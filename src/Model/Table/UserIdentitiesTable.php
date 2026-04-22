<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * UserIdentities Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @method \App\Model\Entity\UserIdentity newEmptyEntity()
 * @method \App\Model\Entity\UserIdentity newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\UserIdentity[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\UserIdentity get($primaryKey, $options = [])
 * @method \App\Model\Entity\UserIdentity findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\UserIdentity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\UserIdentity[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\UserIdentity|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UserIdentity saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UserIdentity[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\UserIdentity[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\UserIdentity[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\UserIdentity[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class UserIdentitiesTable extends Table
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

        $this->setTable('user_identities');
        $this->setDisplayField('provider');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                    'updated_at' => 'always',
                ],
            ],
        ]);

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
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
            ->uuid('user_id')
            ->notEmptyString('user_id')
            ->add('user_id', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('provider')
            ->requirePresence('provider', 'create')
            ->notEmptyString('provider');

        $validator
            ->scalar('provider_account_id')
            ->maxLength('provider_account_id', 255)
            ->requirePresence('provider_account_id', 'create')
            ->notEmptyString('provider_account_id');

        $validator
            ->scalar('handle_cached')
            ->maxLength('handle_cached', 255)
            ->requirePresence('handle_cached', 'create')
            ->notEmptyString('handle_cached');

        $validator
            ->scalar('avatar_url_cached')
            ->maxLength('avatar_url_cached', 2048)
            ->allowEmptyString('avatar_url_cached');

        $validator
            ->scalar('profile_url_cached')
            ->maxLength('profile_url_cached', 2048)
            ->allowEmptyFile('profile_url_cached');

        $validator
            ->scalar('access_token_enc')
            ->allowEmptyString('access_token_enc');

        $validator
            ->scalar('refresh_token_enc')
            ->allowEmptyString('refresh_token_enc');

        $validator
            ->dateTime('token_expires_at')
            ->allowEmptyDateTime('token_expires_at');

        $validator
            ->dateTime('last_synced_at')
            ->allowEmptyDateTime('last_synced_at');

        $validator
            ->boolean('is_primary')
            ->notEmptyString('is_primary');

        $validator
            ->dateTime('created_at')
            ->notEmptyDateTime('created_at');

        $validator
            ->dateTime('updated_at')
            ->notEmptyDateTime('updated_at');

        return $validator;
    }
}
