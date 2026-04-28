<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\Model\Table;

use Cake\Database\Exception\DatabaseException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Validation\Validator;

/**
 * Inboxes Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\MessagesTable&\Cake\ORM\Association\HasMany $Messages
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
            ->minLength('slug', 3)
            ->requirePresence('slug', 'create')
            ->notEmptyString('slug')
            ->add('slug', 'unique', ['rule' => 'validateUnique', 'provider' => 'table'])
            ->add('slug', 'format', [
                'rule' => ['custom', '/^[a-zA-Z0-9_-]{3,32}$/'],
                'message' => 'スラッグは英数字とハイフン・アンダースコアのみ、3〜32文字で指定してください。',
            ]);

        $validator
            ->scalar('slug_previous')
            ->allowEmptyString('slug_previous')
            ->add('slug_previous', 'format', [
                'rule' => ['custom', '/^[a-zA-Z0-9_-]{3,32}$/'],
                'message' => 'previous slug の形式が不正です。',
            ]);

        $validator
            ->decimal('ssr_probability')
            ->notEmptyString('ssr_probability')
            ->greaterThanOrEqual('ssr_probability', 0.0, '確率は 0〜1 の範囲で指定してください。')
            ->lessThanOrEqual('ssr_probability', 1.0, '確率は 0〜1 の範囲で指定してください。');

        $validator
            ->boolean('is_accepting')
            ->notEmptyString('is_accepting');

        $validator
            ->scalar('welcome_message')
            ->maxLength('welcome_message', 1000, 'welcome message は 1000 文字以内で入力してください。')
            ->allowEmptyString('welcome_message');

        $validator
            ->dateTime('created_at')
            ->notEmptyDateTime('created_at');

        $validator
            ->dateTime('updated_at')
            ->notEmptyDateTime('updated_at');

        return $validator;
    }

    /**
     * Find an inbox by current slug, falling back to slug_previous (D-04 single-generation
     * grace period). Caller decides whether to 301-redirect when fallback hits.
     *
     * @param string $slug The slug from the URL.
     * @return array{inbox: \App\Model\Entity\Inbox, redirect: bool} redirect=true means slug_previous matched.
     * @throws \Cake\Http\Exception\NotFoundException If neither slug nor slug_previous matches.
     */
    public function findBySlugOrPrevious(string $slug): array
    {
        /** @var \App\Model\Entity\Inbox|null $inbox */
        $inbox = $this->find()
            ->contain(['Users'])
            ->where([
                $this->aliasField('slug') => $slug,
                'Users.deleted_at IS' => null, // Phase 4 REV-01 / D-25 — retired user → 404
            ])
            ->first();
        if ($inbox !== null) {
            return ['inbox' => $inbox, 'redirect' => false];
        }

        /** @var \App\Model\Entity\Inbox|null $prev */
        $prev = $this->find()
            ->contain(['Users'])
            ->where([
                $this->aliasField('slug_previous') => $slug,
                'Users.deleted_at IS' => null, // Phase 4 REV-01 / D-25
            ])
            ->first();
        if ($prev !== null) {
            return ['inbox' => $prev, 'redirect' => true];
        }

        throw new NotFoundException(__('受信箱が見つかりませんでした。'));
    }

    /**
     * Create or update the inbox for a user, assigning a unique slug derived from the user's
     * Bluesky handle. Phase 3 D-03 (rename trigger) + D-02 (collision suffix retry) + D-06
     * (collision flash signal).
     *
     * On new user (no existing inbox row): INSERT with derived slug; if UNIQUE collision,
     * suffix `-2`, `-3`... up to N=100, then `<base>-<did_hash8>` fallback.
     *
     * On existing user with handle change (existing inbox.slug != newDerivedBase): UPDATE
     * inbox.slug to a new unique value, copy old slug → inbox.slug_previous.
     *
     * @param string $userId Owner user UUID.
     * @param string $derivedBaseSlug SlugDeriver::deriveFromHandle output for the user's handle.
     * @param string $did User's DID (for fallback hash if N=100 exhausted).
     * @return array{slug: string, slug_changed: bool, suffix_applied: bool}
     *   slug: final slug stored.
     *   slug_changed: true if existing inbox got a new slug (rename).
     *   suffix_applied: true if base collided and a -N suffix was used.
     * @throws \RuntimeException If retry exhausted AND fallback also collides (vanishingly rare).
     */
    public function assignSlugForUser(string $userId, string $derivedBaseSlug, string $did): array
    {
        /** @var \App\Model\Entity\Inbox|null $existing */
        $existing = $this->find()
            ->where([$this->aliasField('user_id') => $userId])
            ->first();

        $candidate = $derivedBaseSlug;

        if ($existing === null) {
            // New inbox path.
            for ($n = 0; $n <= 100; $n++) {
                $trySlug = $n === 0 ? $candidate : ($candidate . '-' . ($n + 1));
                try {
                    $entity = $this->newEntity([
                        'id' => Text::uuid(),
                        'user_id' => $userId,
                        'slug' => $trySlug,
                        // schema defaults (DB) — set explicitly for clarity:
                        'ssr_probability' => '0.100',
                        'is_accepting' => true,
                    ], ['accessibleFields' => [
                        'id' => true,
                        'user_id' => true,
                        'slug' => true,
                        'ssr_probability' => true,
                        'is_accepting' => true,
                    ]]);
                    $this->saveOrFail($entity);

                    return ['slug' => $trySlug, 'slug_changed' => false, 'suffix_applied' => $n > 0];
                } catch (PersistenceFailedException $e) {
                    // Retry on slug uniqueness collision (validation-layer or DB-layer).
                    $errors = $e->getEntity()->getErrors();
                    $cause = $e->getPrevious();
                    if (isset($errors['slug']['unique']) || $cause instanceof DatabaseException) {
                        continue;
                    }
                    throw $e;
                } catch (DatabaseException $e) {
                    continue;
                }
            }
            // Fallback to did_hash8 if N=100 exhausted (D-02).
            $fallback = $candidate . '-' . substr(hash('sha256', $did), 0, 8);
            $entity = $this->newEntity([
                'id' => Text::uuid(),
                'user_id' => $userId,
                'slug' => $fallback,
                'ssr_probability' => '0.100',
                'is_accepting' => true,
            ], ['accessibleFields' => [
                'id' => true,
                'user_id' => true,
                'slug' => true,
                'ssr_probability' => true,
                'is_accepting' => true,
            ]]);
            $this->saveOrFail($entity);

            return ['slug' => $fallback, 'slug_changed' => false, 'suffix_applied' => true];
        }

        // Existing inbox path. Only rename if base differs.
        // Strip any existing -N suffix from existing slug for comparison.
        $currentBase = preg_replace('/-\d+$/', '', (string)$existing->slug) ?? (string)$existing->slug;
        if ($currentBase === $derivedBaseSlug) {
            // Handle didn't actually change the base — keep current slug.
            return ['slug' => (string)$existing->slug, 'slug_changed' => false, 'suffix_applied' => false];
        }

        // Rename. Save current slug → slug_previous (D-04), then retry-update.
        $oldSlug = (string)$existing->slug;
        for ($n = 0; $n <= 100; $n++) {
            $trySlug = $n === 0 ? $candidate : ($candidate . '-' . ($n + 1));
            try {
                $patched = $this->patchEntity($existing, [
                    'slug' => $trySlug,
                    'slug_previous' => $oldSlug,
                ], ['accessibleFields' => [
                    'slug' => true,
                    'slug_previous' => true,
                ]]);
                $this->saveOrFail($patched);

                return ['slug' => $trySlug, 'slug_changed' => true, 'suffix_applied' => $n > 0];
            } catch (PersistenceFailedException $e) {
                // Retry on slug uniqueness collision (validation-layer or DB-layer).
                $errors = $e->getEntity()->getErrors();
                $cause = $e->getPrevious();
                if (isset($errors['slug']['unique']) || $cause instanceof DatabaseException) {
                    continue;
                }
                throw $e;
            } catch (DatabaseException $e) {
                continue;
            }
        }
        $fallback = $candidate . '-' . substr(hash('sha256', $did), 0, 8);
        $patched = $this->patchEntity($existing, [
            'slug' => $fallback,
            'slug_previous' => $oldSlug,
        ], ['accessibleFields' => [
            'slug' => true,
            'slug_previous' => true,
        ]]);
        $this->saveOrFail($patched);

        return ['slug' => $fallback, 'slug_changed' => true, 'suffix_applied' => true];
    }
}
