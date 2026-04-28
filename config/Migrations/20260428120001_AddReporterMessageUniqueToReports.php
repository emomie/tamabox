<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * AddReporterMessageUniqueToReports — Phase 4 D-12 (duplicate-report prevention).
 *
 * Adds composite UNIQUE index uk_reports_reporter_message on (reporter_user_id, message_id).
 * Enforces "1 report per (reporter, message)" with race-safety via DatabaseException catch
 * in ReportsController::create.
 *
 * MySQL 8.0 NULL-in-UNIQUE behavior: multiple NULLs ARE allowed when the column is nullable.
 * `reports.reporter_user_id` is nullable (FK SET NULL on reporter user delete), so retired
 * reporters' reports do NOT block other reports on the same message. RESEARCH Pitfall 3
 * confirms this is intentional, NOT a bug.
 *
 * Naming: `uk_*` prefix matches existing uk_blocks_pair / uk_user_identities_*
 * (Phase 1 DB-SCHEMA v0.2 convention).
 */
class AddReporterMessageUniqueToReports extends AbstractMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('reports')
            ->addIndex(
                ['reporter_user_id', 'message_id'],
                [
                    'unique' => true,
                    'name' => 'uk_reports_reporter_message',
                ]
            )
            ->update();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('reports')
            ->removeIndexByName('uk_reports_reporter_message')
            ->update();
    }
}
