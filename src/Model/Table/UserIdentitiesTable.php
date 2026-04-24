<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\User;
use App\Service\OAuth\TokenEncryptionService;
use Cake\Database\Exception\DatabaseException;
use Cake\I18n\FrozenTime;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Validation\Validator;
use RuntimeException;

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

    /**
     * UPSERT a Bluesky identity: create a user + identity on first login, update the
     * identity on subsequent logins.
     *
     * Caller passes PLAINTEXT access/refresh tokens; this method encrypts them via
     * TokenEncryptionService before the DB save (AUTH-07).
     *
     * Transaction semantics:
     *   - New user path: users INSERT + user_identities INSERT in one transaction.
     *     DB constraint violation (UNIQUE provider+did race) → RuntimeException, neither row saved.
     *   - Existing user path: user_identities UPDATE only; users row untouched.
     *   - Profile-fetch failure handled by CALLER (see OauthController::callback).
     *
     * @param array<string, mixed> $profile Plaintext profile with keys did (string, non-empty), handle (string, non-empty), avatar (string|null), profile_url (string).
     * @param array<string, mixed> $tokens Plaintext tokens with keys access_token (string), refresh_token (string), expires_in (int); encrypted here.
     * @return \App\Model\Entity\User The (new or existing) User entity.
     * @throws \RuntimeException On DB constraint violation, validation failure, or missing args.
     */
    public function upsertBlueskyIdentity(array $profile, array $tokens): User
    {
        $did = (string)($profile['did'] ?? '');
        $handle = (string)($profile['handle'] ?? '');
        if ($did === '' || $handle === '') {
            throw new RuntimeException('upsertBlueskyIdentity: did and handle are required.');
        }

        $tokenSvc = new TokenEncryptionService();
        $accessEnc = $tokenSvc->encrypt((string)$tokens['access_token']);
        $refreshEnc = $tokenSvc->encrypt((string)$tokens['refresh_token']);
        $expiresIn = (int)($tokens['expires_in'] ?? 0);
        $tokenExpiresAt = new FrozenTime('+' . $expiresIn . ' seconds');
        $now = FrozenTime::now();

        $connection = $this->getConnection();

        /** @var \App\Model\Table\UsersTable $usersTable */
        $usersTable = $this->getAssociation('Users')->getTarget();

        // Look up existing identity (D-09 — UNIQUE (provider, provider_account_id)).
        /** @var \App\Model\Entity\UserIdentity|null $existing */
        $existing = $this->find()
            ->where([
                $this->aliasField('provider') => 'bluesky',
                $this->aliasField('provider_account_id') => $did,
            ])
            ->first();

        try {
            return $connection->transactional(
                function () use (
                    $existing,
                    $usersTable,
                    $did,
                    $handle,
                    $profile,
                    $accessEnc,
                    $refreshEnc,
                    $tokenExpiresAt,
                    $now
                ): User {
                    if ($existing !== null) {
                        // Existing user — UPDATE identity only.
                        $patched = $this->patchEntity($existing, [
                            'handle_cached' => $handle,
                            'avatar_url_cached' => $profile['avatar'] ?? null,
                            'profile_url_cached' => (string)$profile['profile_url'],
                            'access_token_enc' => $accessEnc,
                            'refresh_token_enc' => $refreshEnc,
                            'token_expires_at' => $tokenExpiresAt,
                            'last_synced_at' => $now,
                        ], ['accessibleFields' => [
                            'handle_cached' => true,
                            'avatar_url_cached' => true,
                            'profile_url_cached' => true,
                            'access_token_enc' => true,
                            'refresh_token_enc' => true,
                            'token_expires_at' => true,
                            'last_synced_at' => true,
                        ]]);
                        $this->saveOrFail($patched);

                        /** @var \App\Model\Entity\User $user */
                        $user = $usersTable->find()
                            ->where(['Users.id' => $patched->user_id])
                            ->firstOrFail();

                        return $user;
                    }

                    // New user — INSERT users + user_identities.
                    $newUserId = Text::uuid();
                    /** @var \App\Model\Entity\User $newUser */
                    $newUser = $usersTable->newEntity([
                        'id' => $newUserId,
                        'display_name' => mb_substr($handle, 0, 64),
                    ], ['accessibleFields' => [
                        'id' => true,
                        'display_name' => true,
                    ]]);
                    $usersTable->saveOrFail($newUser);

                    $newIdentity = $this->newEntity([
                        'id' => Text::uuid(),
                        'user_id' => $newUser->id,
                        'provider' => 'bluesky',
                        'provider_account_id' => $did,
                        'handle_cached' => $handle,
                        'avatar_url_cached' => $profile['avatar'] ?? null,
                        'profile_url_cached' => (string)$profile['profile_url'],
                        'access_token_enc' => $accessEnc,
                        'refresh_token_enc' => $refreshEnc,
                        'token_expires_at' => $tokenExpiresAt,
                        'last_synced_at' => $now,
                        'is_primary' => true,
                    ], ['accessibleFields' => [
                        'id' => true,
                        'user_id' => true,
                        'provider' => true,
                        'provider_account_id' => true,
                        'handle_cached' => true,
                        'avatar_url_cached' => true,
                        'profile_url_cached' => true,
                        'access_token_enc' => true,
                        'refresh_token_enc' => true,
                        'token_expires_at' => true,
                        'last_synced_at' => true,
                        'is_primary' => true,
                    ]]);
                    $this->saveOrFail($newIdentity);

                    return $newUser;
                }
            );
        } catch (DatabaseException $e) {
            // T-02-04-10: UNIQUE violation (provider+did race) or other integrity error.
            throw new RuntimeException('Identity upsert failed: database constraint violation.', 0, $e);
        } catch (PersistenceFailedException $e) {
            throw new RuntimeException('Identity upsert failed: validation or save error.', 0, $e);
        }
    }
}
