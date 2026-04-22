<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateUserIdentities migration.
 *
 * Source of truth: emomie/ssr-box-discovery:DB-SCHEMA.md v0.2 §2.
 *
 * Provider-scoped SNS identity binding (Bluesky / X). MVP enforces strict 1:1 between
 * users and user_identities via UNIQUE (user_id); future account-linking will DROP
 * that index and keep `is_primary = TRUE` as the per-user singleton guard (D-11).
 *
 * OAuth tokens are stored as ciphertext in `access_token_enc` / `refresh_token_enc`
 * (TEXT) — Phase 2 adds the AES-GCM encryption layer (AUTH-07); Phase 1 only
 * reserves the columns.
 *
 * CHECK constraints are applied via raw SQL (Phinx 0.13 has no addConstraint API),
 * so this migration uses up()/down() pair. DB-SCHEMA.md v0.2 §2 defines no CHECK
 * predicates on user_identities, so this up() has no raw-SQL tail.
 */
class CreateUserIdentities extends AbstractMigration
{
    /**
     * Disable Phinx auto-BIGINT id column; UUID CHAR(36) PK defined explicitly.
     *
     * @var bool
     */
    public $autoId = false;

    /**
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('user_identities', [
            'collation' => 'utf8mb4_0900_ai_ci',
            'engine'    => 'InnoDB',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addPrimaryKey('id')

            ->addColumn('user_id', 'uuid', ['null' => false])

            ->addColumn('provider', 'enum', [
                'values' => ['bluesky', 'x'],
                'null'   => false,
            ])

            ->addColumn('provider_account_id', 'string', [
                'limit' => 255,
                'null'  => false,
            ])

            ->addColumn('handle_cached', 'string', [
                'limit' => 255,
                'null'  => false,
            ])

            ->addColumn('avatar_url_cached', 'string', [
                'limit' => 2048,
                'null'  => true,
            ])

            ->addColumn('profile_url_cached', 'string', [
                'limit' => 2048,
                'null'  => true,
            ])

            // OAuth access token ciphertext (AES-GCM); Phase 2 AUTH-07 populates.
            ->addColumn('access_token_enc', 'text', [
                'null' => true,
            ])

            // OAuth refresh token ciphertext (AES-GCM); Phase 2 AUTH-07 populates.
            ->addColumn('refresh_token_enc', 'text', [
                'null' => true,
            ])

            ->addColumn('token_expires_at', 'datetime', [
                'limit' => 6,
                'null'  => true,
            ])

            ->addColumn('last_synced_at', 'datetime', [
                'limit' => 6,
                'null'  => true,
            ])

            // MVP: 1 user → 1 identity (D-11). Phinx `boolean` maps to TINYINT(1).
            ->addColumn('is_primary', 'boolean', [
                'null'    => false,
                'default' => true,
            ])

            ->addColumn('created_at', 'datetime', [
                'limit'   => 6,
                'null'    => false,
                'default' => \Phinx\Util\Literal::from('CURRENT_TIMESTAMP(6)'),
            ])

            ->addColumn('updated_at', 'timestamp', [
                'limit'   => 6,
                'null'    => false,
                'default' => \Phinx\Util\Literal::from('CURRENT_TIMESTAMP(6)'),
                'update'  => 'CURRENT_TIMESTAMP(6)',
            ])

            // Composite UNIQUE on (provider, provider_account_id) — Pattern 3.
            ->addIndex(
                ['provider', 'provider_account_id'],
                [
                    'unique' => true,
                    'name'   => 'uk_user_identities_provider_account',
                ]
            )

            // MVP strict 1:1 with users (D-11). When account-linking lands this
            // index is DROPped and is_primary becomes the per-user singleton guard.
            ->addIndex(
                ['user_id'],
                [
                    'unique' => true,
                    'name'   => 'uk_user_identities_user',
                ]
            )

            ->addForeignKey('user_id', 'users', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'NO_ACTION',
                'constraint' => 'fk_user_identities_user',
            ])

            ->create();

        // DB-SCHEMA.md v0.2 §2 defines no additional CHECK predicates on
        // user_identities; ENUM('bluesky','x') already enforces provider values.
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('user_identities')->drop()->save();
    }
}
