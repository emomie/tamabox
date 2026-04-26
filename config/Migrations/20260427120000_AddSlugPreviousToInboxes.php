<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * AddSlugPreviousToInboxes — Phase 3 D-04 (single-generation slug rename redirect).
 *
 * Adds a nullable `slug_previous` column to `inboxes` to support 301-redirect from
 * the old slug after a Bluesky handle rename. CHECK constraint mirrors the existing
 * inboxes_slug_format regex (3..32 chars, [a-zA-Z0-9_-]) but allows NULL.
 *
 * Source of truth for the regex: Phase 1 CreateInboxes migration L117-122.
 * Constraint name uses snake_case without _check suffix (DB-SCHEMA.md v0.2 convention).
 */
class AddSlugPreviousToInboxes extends AbstractMigration
{
    /**
     * Add slug_previous column to inboxes table.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('inboxes')
            ->addColumn('slug_previous', 'string', [
                'limit' => 32,
                'null' => true,
                'default' => null,
                'after' => 'slug',
                'comment' => 'Previous slug retained for 1-generation 301 redirect after Bluesky handle rename (Phase 3 D-04).',
            ])
            ->addIndex(['slug_previous'], ['name' => 'idx_inboxes_slug_previous'])
            ->update();

        $this->execute(<<<SQL
ALTER TABLE inboxes
  ADD CONSTRAINT inboxes_slug_previous_format
  CHECK (slug_previous IS NULL OR slug_previous REGEXP '^[a-zA-Z0-9_-]{3,32}$')
SQL);
    }

    /**
     * Remove slug_previous column from inboxes table.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute('ALTER TABLE inboxes DROP CONSTRAINT inboxes_slug_previous_format');
        $this->table('inboxes')
            ->removeIndexByName('idx_inboxes_slug_previous')
            ->removeColumn('slug_previous')
            ->update();
    }
}
