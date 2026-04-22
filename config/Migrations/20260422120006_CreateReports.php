<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateReports migration.
 *
 * Source of truth: emomie/ssr-box-discovery:DB-SCHEMA.md v0.2 §6.
 *
 * Post-hoc moderation reports filed by receivers against messages.
 *
 * - `fk_reports_message` ON DELETE **CASCADE** — message hard-delete
 *   wipes reports (reports are tied to the message they reference).
 * - `fk_reports_reporter` ON DELETE **SET NULL** — reporter account
 *   removal anonymizes the report but preserves the moderation trail
 *   (T-01-12 mitigation). `reporter_user_id` MUST be nullable for this
 *   FK action to be accepted by Phinx / MySQL (RESEARCH Pattern 2).
 * - `reason` ENUM has 4 MVP categories per DESIGN Q5 + MOD-01:
 *   harassment / spam / illegal / other. Re-classification is an
 *   operational activity post-MVP.
 * - `status` ENUM has 4 values per DB-SCHEMA v0.2 (not 3 as some plan
 *   paraphrases showed): `pending` (default) → `reviewed` →
 *   {`actioned` | `dismissed`}. Plan 01-03 / Phase 4 moderation UI
 *   drives status transitions.
 * - Additional moderation-workflow columns per DB-SCHEMA v0.2:
 *   `detail` (reporter-provided free text), `reviewed_at`,
 *   `reviewed_by_admin` (operator handle), `resolution_note` (admin
 *   free text). No `updated_at`: the status + reviewed_at combination
 *   encodes the audit trail.
 * - `idx_reports_status` composite on (status, created_at) supports the
 *   pending-review queue dashboard (Phase 4); `idx_reports_message`
 *   supports "show me all reports on this message" drill-down.
 *
 * DB-SCHEMA v0.2 §6 defines no CHECK constraints — the ENUMs already
 * enforce value domains, and MOD-02 explicitly rejects DDL-level content
 * filtering. So this migration has no raw-SQL tail.
 *
 * Phinx 0.13 has no addConstraint API, so this migration still uses the
 * explicit up()/down() pair (consistency with the rest of the Phase 1 set;
 * also reversibility is a hard requirement for Phase 4 deploy rehearsal).
 */
class CreateReports extends AbstractMigration
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
        $table = $this->table('reports', [
            'collation' => 'utf8mb4_0900_ai_ci',
            'engine'    => 'InnoDB',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addPrimaryKey('id')

            ->addColumn('message_id', 'uuid', ['null' => false])

            // CRITICAL: must be nullable for FK ON DELETE SET NULL
            // (RESEARCH Pattern 2 / Phinx docs constraint).
            ->addColumn('reporter_user_id', 'uuid', [
                'null' => true,
            ])

            ->addColumn('reason', 'enum', [
                'values' => ['harassment', 'spam', 'illegal', 'other'],
                'null'   => false,
            ])

            // Optional reporter-provided free-text explanation.
            ->addColumn('detail', 'text', [
                'null' => true,
            ])

            // 4 statuses per DB-SCHEMA v0.2 §6 (pending is default).
            ->addColumn('status', 'enum', [
                'values'  => ['pending', 'reviewed', 'actioned', 'dismissed'],
                'null'    => false,
                'default' => 'pending',
            ])

            ->addColumn('reviewed_at', 'datetime', [
                'limit' => 6,
                'null'  => true,
            ])

            // Moderator identifier (free-form — may be an email, a handle,
            // or an internal ID). 128 chars matches DB-SCHEMA v0.2 §6.
            ->addColumn('reviewed_by_admin', 'string', [
                'limit' => 128,
                'null'  => true,
            ])

            // Admin resolution note (internal, may contain sensitive detail).
            ->addColumn('resolution_note', 'text', [
                'null' => true,
            ])

            ->addColumn('created_at', 'datetime', [
                'limit'   => 6,
                'null'    => false,
                'default' => \Phinx\Util\Literal::from('CURRENT_TIMESTAMP(6)'),
            ])

            // idx_reports_status: supports the pending-review queue. Name
            // matches DB-SCHEMA v0.2 §6 verbatim — NOT the plan-text
            // `idx_reports_status_created`.
            ->addIndex(
                ['status', 'created_at'],
                ['name' => 'idx_reports_status']
            )

            // idx_reports_message: supports "all reports on this message" drill-down.
            ->addIndex(
                ['message_id'],
                ['name' => 'idx_reports_message']
            )

            // FK to messages: CASCADE — message hard-delete wipes reports.
            ->addForeignKey('message_id', 'messages', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'NO_ACTION',
                'constraint' => 'fk_reports_message',
            ])

            // FK to users (reporter): SET_NULL — reporter account removal
            // anonymizes but does NOT destroy the moderation trail (T-01-12).
            ->addForeignKey('reporter_user_id', 'users', 'id', [
                'delete'     => 'SET_NULL',
                'update'     => 'NO_ACTION',
                'constraint' => 'fk_reports_reporter',
            ])

            ->create();

        // DB-SCHEMA v0.2 §6 defines no CHECK predicates on reports; ENUMs
        // are the entire value-domain enforcement layer (MOD-02 explicitly
        // rejects DDL-level content filtering).
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('reports')->drop()->save();
    }
}
