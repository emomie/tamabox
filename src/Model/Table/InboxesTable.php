<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Inboxes Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\MessagesTable&\Cake\ORM\Association\HasMany $Messages
 *
 * @method \App\Model\Entity\Inbox newEmptyEntity()
 * @method \App\Model\Entity\Inbox newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Inbox[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Inbox get($primaryKey, $options = [])
 * @method \App\Model\Entity\Inbox findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Inbox patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Inbox[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Inbox|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Inbox saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Inbox[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Inbox[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Inbox[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Inbox[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class InboxesTable extends Table
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

        $this->setTable('inboxes');
        $this->setDisplayField('slug');
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
        $this->hasMany('Messages', [
            'foreignKey' => 'inbox_id',
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
            ->scalar('slug')
            ->maxLength('slug', 32)
            ->requirePresence('slug', 'create')
            ->notEmptyString('slug')
            ->add('slug', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->decimal('ssr_probability')
            ->notEmptyString('ssr_probability');

        $validator
            ->boolean('is_accepting')
            ->notEmptyString('is_accepting');

        $validator
            ->scalar('welcome_message')
            ->allowEmptyString('welcome_message');

        $validator
            ->dateTime('created_at')
            ->notEmptyDateTime('created_at');

        $validator
            ->dateTime('updated_at')
            ->notEmptyDateTime('updated_at');

        return $validator;
    }
}
