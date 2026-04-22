<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateBlocks migration.
 *
 * Source of truth: emomie/ssr-box-discovery:DB-SCHEMA.md v0.2 §5.
 *
 * Receiver-side blocklist. Self-referential N:N on users via (blocker, blocked).
 *
 * - Both FKs CASCADE: if either user row is deleted, the block record is
 *   irrelevant. (Unlike messages where RESTRICT enforces 逃げ得防止,
 *   block records carry no moderation evidence — losing them is fine.)
 * - Composite UNIQUE `uk_blocks_pair` enforces at-most-one block row per
 *   (blocker, blocked) directional pair.
 * - `blocks_no_self` CHECK prevents a user from blocking themselves
 *   (defense against app bugs / T-01-11 self-target bypass).
 * - `idx_blocks_blocked` supports "is user X blocked by anyone" reverse
 *   lookups used when computing visibility for a sender.
 * - DB-SCHEMA v0.2 defines only created_at (no updated_at); blocks are
 *   created-or-deleted, never mutated in place.
 *
 * CHECK constraints are applied via raw SQL (Phinx 0.13 has no addConstraint
 * API), so this migration uses an explicit up()/down() pair.
 */
class CreateBlocks extends AbstractMigration
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
        $table = $this->table('blocks', [
            'collation' => 'utf8mb4_0900_ai_ci',
            'engine'    => 'InnoDB',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addPrimaryKey('id')

            ->addColumn('blocker_user_id', 'uuid', ['null' => false])
            ->addColumn('blocked_user_id', 'uuid', ['null' => false])

            // Optional blocker-provided reason for the block; informational only.
            ->addColumn('reason', 'string', [
                'limit' => 255,
                'null'  => true,
            ])

            ->addColumn('created_at', 'datetime', [
                'limit'   => 6,
                'null'    => false,
                'default' => \Phinx\Util\Literal::from('CURRENT_TIMESTAMP(6)'),
            ])

            // Composite UNIQUE: at most one block row per (blocker, blocked).
            // Name matches DB-SCHEMA v0.2 §5 verbatim (`uk_blocks_pair`).
            ->addIndex(
                ['blocker_user_id', 'blocked_user_id'],
                [
                    'unique' => true,
                    'name'   => 'uk_blocks_pair',
                ]
            )

            // Reverse-direction lookup: "is sender X blocked by anyone?".
            ->addIndex(
                ['blocked_user_id'],
                ['name' => 'idx_blocks_blocked']
            )

            // Both FKs CASCADE: block rows are purely relational — deleting
            // either party makes the relation moot, and blocks carry no
            // moderation evidence that needs preservation.
            ->addForeignKey('blocker_user_id', 'users', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'NO_ACTION',
                'constraint' => 'fk_blocks_blocker',
            ])
            ->addForeignKey('blocked_user_id', 'users', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'NO_ACTION',
                'constraint' => 'fk_blocks_blocked',
            ])

            ->create();

        // CHECK constraint via raw SQL — Phinx 0.13 has no addConstraint() API.
        // Name matches DB-SCHEMA.md v0.2 §5 verbatim (`blocks_no_self`,
        // no `_check` suffix — D-10 says DB-SCHEMA wins over A7 convention).
        $this->execute(<<<SQL
ALTER TABLE blocks
  ADD CONSTRAINT blocks_no_self
  CHECK (blocker_user_id <> blocked_user_id)
SQL);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('blocks')->drop()->save();
    }
}
