<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateMessages migration.
 *
 * Source of truth: emomie/ssr-box-discovery:DB-SCHEMA.md v0.2 §4.
 *
 * The widest table in the schema. Key traits:
 *
 * - `fk_messages_inbox` ON DELETE **CASCADE** — inbox deletion wipes messages.
 * - `fk_messages_sender` ON DELETE **RESTRICT** — 逃げ得防止 (sender cannot
 *   hard-delete themselves while authored messages exist). Pair with
 *   `users.deleted_at` soft-delete for retirement UX.
 * - Sender-snapshot columns (`sender_handle_snapshot` etc.) preserve identity
 *   at the moment of send, so SNS-side rename / block / deletion does not
 *   retroactively rewrite history (MSG-04).
 * - `is_ssr` + `ssr_probability_at_send` + `ssr_seed` are **computed at send
 *   time** in Phase 3 (MSG-02 / MSG-03). Phase 1 only defines the columns.
 *   `ssr_seed = sha256(server_secret || message_id || created_at)` — 64-char hex.
 * - `deleted_reason` is `VARCHAR(64) NULL` per CONTEXT `<specifics>` (NOT an
 *   ENUM, for future-value extensibility without ALTER). Allowed values
 *   `'user'` / `'report_actioned'` / `'account_removed'` are enforced at the
 *   app layer; DB-SCHEMA.md v0.2 defines no CHECK on this column.
 * - `body_length INT NOT NULL` is a physically stored column so the
 *   `messages_body_length_check` CHECK can compare it to `CHAR_LENGTH(body)`
 *   without MySQL requiring deterministic expressions.
 * - Four composite/non-unique indexes support the four dominant query patterns
 *   (inbox listing, sender lookup, open-state scan, soft-delete filter).
 *
 * CHECK constraints are applied via raw SQL (Phinx 0.13 has no addConstraint
 * API), so this migration is NOT auto-reversible → explicit up()/down() pair.
 */
class CreateMessages extends AbstractMigration
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
        $table = $this->table('messages', [
            'collation' => 'utf8mb4_0900_ai_ci',
            'engine'    => 'InnoDB',
        ]);

        $table
            ->addColumn('id', 'uuid', ['null' => false])
            ->addPrimaryKey('id')

            ->addColumn('inbox_id', 'uuid', ['null' => false])
            ->addColumn('sender_user_id', 'uuid', ['null' => false])

            // body: MySQL TEXT (<= 65,535 bytes; DB-SCHEMA v0.2 constrains
            // CHAR_LENGTH to 1..2000 via messages_body_size CHECK).
            ->addColumn('body', 'text', [
                'null' => false,
            ])

            // Physically stored length, kept in sync with body by app layer.
            // messages_body_length_check CHECK enforces equality with CHAR_LENGTH(body).
            ->addColumn('body_length', 'integer', [
                'null' => false,
                'signed' => true,
            ])

            // SSR audit columns — computed at send time in Phase 3 (MSG-02 / MSG-03).
            // Phase 1 only defines the columns; no data populated yet.
            ->addColumn('is_ssr', 'boolean', [
                'null' => false,
            ])

            // Snapshot of the inbox's ssr_probability at the moment of send;
            // DECIMAL(4,3) matches inboxes.ssr_probability type so auditors
            // can reconstruct the original probability without join risk.
            ->addColumn('ssr_probability_at_send', 'decimal', [
                'precision' => 4,
                'scale'     => 3,
                'null'      => false,
            ])

            // ssr_seed = sha256(server_secret || id || created_at) hex — 64 chars.
            // Phase 3 SsrService::computeSeed populates this at send time; value
            // becomes effectively immutable (enforced at app layer since
            // Phinx 0.13 + shared hosting have no trigger support).
            ->addColumn('ssr_seed', 'string', [
                'limit' => 64,
                'null'  => false,
            ])

            // Sender provider (SNS origin). ENUM per DB-SCHEMA v0.2; aligns
            // with user_identities.provider ENUM for cross-table consistency.
            ->addColumn('sender_provider', 'enum', [
                'values' => ['bluesky', 'x'],
                'null'   => false,
            ])

            // Sender identity snapshot — preserved even if sender soft-deletes
            // or SNS-side handle changes (逃げ得防止 + MSG-04).
            ->addColumn('sender_handle_snapshot', 'string', [
                'limit' => 255,
                'null'  => false,
            ])

            ->addColumn('sender_avatar_url_snapshot', 'string', [
                'limit' => 2048,
                'null'  => true,
            ])

            ->addColumn('sender_profile_url_snapshot', 'string', [
                'limit' => 2048,
                'null'  => true,
            ])

            // Open / delete lifecycle (MSG-06 / MSG-08).
            ->addColumn('opened_at', 'datetime', [
                'limit' => 6,
                'null'  => true,
            ])

            ->addColumn('deleted_at', 'datetime', [
                'limit' => 6,
                'null'  => true,
            ])

            // VARCHAR(64) NOT ENUM per CONTEXT <specifics> — allowed values
            // 'user' / 'report_actioned' / 'account_removed' enforced at app
            // layer. Documented in this comment; no DB CHECK per DB-SCHEMA v0.2.
            ->addColumn('deleted_reason', 'string', [
                'limit' => 64,
                'null'  => true,
            ])

            // DB-SCHEMA v0.2 §4 defines only created_at on messages (no
            // updated_at). Messages are effectively immutable post-send;
            // mutations happen only through opened_at / deleted_at columns.
            ->addColumn('created_at', 'datetime', [
                'limit'   => 6,
                'null'    => false,
                'default' => \Phinx\Util\Literal::from('CURRENT_TIMESTAMP(6)'),
            ])

            // Composite indexes per DB-SCHEMA v0.2 §4 — four query patterns:
            // 1. Inbox message list (chronological)
            ->addIndex(
                ['inbox_id', 'created_at'],
                ['name' => 'idx_messages_inbox_created']
            )
            // 2. Sender lookup (FK side — required anyway, named for clarity)
            ->addIndex(
                ['sender_user_id'],
                ['name' => 'idx_messages_sender']
            )
            // 3. Open-state scan per inbox (unopened messages)
            ->addIndex(
                ['inbox_id', 'opened_at'],
                ['name' => 'idx_messages_inbox_opened']
            )
            // 4. Soft-delete filter per inbox (D-12 replacement for partial index)
            ->addIndex(
                ['inbox_id', 'deleted_at'],
                ['name' => 'idx_messages_inbox_deleted']
            )

            // FK to inboxes: CASCADE — inbox deletion wipes messages.
            ->addForeignKey('inbox_id', 'inboxes', 'id', [
                'delete'     => 'CASCADE',
                'update'     => 'NO_ACTION',
                'constraint' => 'fk_messages_inbox',
            ])

            // FK to users: RESTRICT — 逃げ得防止. Sender row deletion blocked
            // at DB level while authored messages exist; retirement flows
            // must use users.deleted_at soft-delete instead.
            ->addForeignKey('sender_user_id', 'users', 'id', [
                'delete'     => 'RESTRICT',
                'update'     => 'NO_ACTION',
                'constraint' => 'fk_messages_sender',
            ])

            ->create();

        // CHECK constraints via raw SQL — Phinx 0.13 has no addConstraint() API.
        // Names match DB-SCHEMA.md v0.2 §4 verbatim.
        $this->execute(<<<SQL
ALTER TABLE messages
  ADD CONSTRAINT messages_body_length_check
  CHECK (body_length = CHAR_LENGTH(body))
SQL);

        $this->execute(<<<SQL
ALTER TABLE messages
  ADD CONSTRAINT messages_body_size
  CHECK (CHAR_LENGTH(body) BETWEEN 1 AND 2000)
SQL);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        // DROP TABLE implicitly drops CHECK constraints on MySQL 8.0.
        // Messages drop before users (rollback reverses FK order) so the
        // fk_messages_sender RESTRICT does not block users.down().
        $this->table('messages')->drop()->save();
    }
}
