<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateInboxes migration.
 *
 * Source of truth: emomie/ssr-box-discovery:DB-SCHEMA.md v0.2 §3.
 *
 * MVP: 1 inbox per user (UNIQUE user_id). `ssr_probability = 0` also acts as a
 * "plain anonymous box" mode per DESIGN Q4 side-feature.
 *
 * CHECK constraints are applied via raw SQL (Phinx 0.13 has no addConstraint API),
 * so this migration uses up()/down() pair. Constraint names match DB-SCHEMA.md
 * v0.2 verbatim (`inboxes_probability_range`, `inboxes_slug_format`) — D-10 says
 * DB-SCHEMA.md is the single source of truth, and those names do NOT carry the
 * `_check` suffix in v0.2. (The plan's verify regex expected `_check`-suffixed
 * names; documented as a deviation in the plan summary.)
 */
class CreateInboxes extends AbstractMigration
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
        $table = $this->table('inboxes', [
            'collation' => 'utf8mb4_0900_ai_ci',
            'engine'    => 'InnoDB',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addPrimaryKey('id')

            ->addColumn('user_id', 'uuid', ['null' => false])

            ->addColumn('slug', 'string', [
                'limit' => 32,
                'null'  => false,
            ])

            // DECIMAL(4,3) — range 0.000..9.999 (DB-SCHEMA constrains to 0..1).
            ->addColumn('ssr_probability', 'decimal', [
                'precision' => 4,
                'scale' => 3,
                'default' => 0.100,
                'null' => false,
            ])

            // Per DB-SCHEMA v0.2: receive-accepting flag (toggle in dashboard).
            ->addColumn('is_accepting', 'boolean', [
                'null'    => false,
                'default' => true,
            ])

            // Per DB-SCHEMA v0.2: optional welcome text shown above message form.
            ->addColumn('welcome_message', 'text', [
                'null' => true,
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

            // MVP: 1 inbox per user (DB-SCHEMA §3).
            ->addIndex(
                ['user_id'],
                [
                    'unique' => true,
                    'name'   => 'uk_inboxes_user',
                ]
            )

            // Slug is globally unique — part of the public URL path.
            ->addIndex(
                ['slug'],
                [
                    'unique' => true,
                    'name'   => 'uk_inboxes_slug',
                ]
            )

            ->addForeignKey('user_id', 'users', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'NO_ACTION',
                'constraint' => 'fk_inboxes_user',
            ])

            ->create();

        // CHECK constraints via raw SQL — Phinx 0.13 has no addConstraint() API.
        // Names match DB-SCHEMA.md v0.2 §3 verbatim (no `_check` suffix).
        $this->execute(<<<SQL
ALTER TABLE inboxes
  ADD CONSTRAINT inboxes_probability_range
  CHECK (ssr_probability >= 0 AND ssr_probability <= 1)
SQL);

        // Slug format: 3..32 chars of [a-zA-Z0-9_-]. `\$` escapes `$` inside heredoc.
        $this->execute(<<<SQL
ALTER TABLE inboxes
  ADD CONSTRAINT inboxes_slug_format
  CHECK (slug REGEXP '^[a-zA-Z0-9_-]{3,32}\$')
SQL);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('inboxes')->drop()->save();
    }
}
