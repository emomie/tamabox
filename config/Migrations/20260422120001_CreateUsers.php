<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateUsers migration.
 *
 * Source of truth: emomie/ssr-box-discovery:DB-SCHEMA.md v0.2 §1.
 * Root of the FK graph — no foreign keys.
 *
 * CHECK constraints are applied via raw SQL (Phinx 0.13 has no addConstraint API),
 * so this migration is NOT auto-reversible → up()/down() pair, never change().
 *
 * Note on updated_at type: Phinx MySQL adapter only wires `ON UPDATE CURRENT_TIMESTAMP`
 * onto `timestamp` columns, not `datetime`. We accept the 2038 ceiling on updated_at
 * (MVP horizon well before 2038 per RESEARCH §Pitfall 4, Decision A3).
 */
class CreateUsers extends AbstractMigration
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
        $table = $this->table('users', [
            'collation' => 'utf8mb4_0900_ai_ci',
            'engine'    => 'InnoDB',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addPrimaryKey('id')

            ->addColumn('display_name', 'string', [
                'limit' => 64,
                'null'  => false,
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

            ->addColumn('deleted_at', 'datetime', [
                'limit' => 6,
                'null'  => true,
            ])

            ->addIndex(['deleted_at'], ['name' => 'idx_users_deleted_at'])
            ->create();

        // CHECK constraints via raw SQL — Phinx 0.13 has no addConstraint() API.
        // Naming convention: <table>_<field>_check per DB-SCHEMA.md v0.2.
        $this->execute(<<<SQL
ALTER TABLE users
  ADD CONSTRAINT users_display_name_check
  CHECK (CHAR_LENGTH(display_name) BETWEEN 1 AND 64)
SQL);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        // DROP TABLE implicitly drops the CHECK constraint on MySQL 8.0.
        $this->table('users')->drop()->save();
    }
}
