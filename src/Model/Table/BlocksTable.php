<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Blocks Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 *
 * @method \App\Model\Entity\Block newEmptyEntity()
 * @method \App\Model\Entity\Block newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Block[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Block get($primaryKey, $options = [])
 * @method \App\Model\Entity\Block findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Block patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Block[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Block|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Block saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Block[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Block[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Block[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Block[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class BlocksTable extends Table
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

        $this->setTable('blocks');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        // blocks has no `updated_at` column per DB-SCHEMA.md v0.2 §5
        // (create-or-delete only; never mutated).
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created_at' => 'new',
                ],
            ],
        ]);

        $this->belongsTo('Users', [
            'foreignKey' => 'blocker_user_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Users', [
            'foreignKey' => 'blocked_user_id',
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
            ->uuid('blocker_user_id')
            ->notEmptyString('blocker_user_id');

        $validator
            ->uuid('blocked_user_id')
            ->notEmptyString('blocked_user_id');

        $validator
            ->scalar('reason')
            ->maxLength('reason', 255)
            ->allowEmptyString('reason');

        $validator
            ->dateTime('created_at')
            ->notEmptyDateTime('created_at');

        return $validator;
    }
}
