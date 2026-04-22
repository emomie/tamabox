---
phase: 01-foundation-schema
plan: 02b
type: execute
wave: 3
depends_on:
  - 01-02a
files_modified:
  - config/Migrations/20260422120004_CreateMessages.php
  - config/Migrations/20260422120005_CreateBlocks.php
  - config/Migrations/20260422120006_CreateReports.php
autonomous: true
requirements:
  - INFRA-04
tags:
  - cakephp
  - migrations
  - phinx
  - mysql
  - schema

must_haves:
  truths:
    - "config/Migrations/20260422120004_CreateMessages.php syntactically valid (FK RESTRICT to users sender, FK CASCADE to inboxes, snapshot columns present, ssr_seed/is_ssr stubbed)"
    - "config/Migrations/20260422120005_CreateBlocks.php syntactically valid (dual FK CASCADE, composite UNIQUE, blocks_no_self CHECK)"
    - "config/Migrations/20260422120006_CreateReports.php syntactically valid (FK CASCADE to messages, FK SET_NULL to users reporter — reporter_user_id nullable)"
    - "bin/cake migrations migrate exits 0 on the clean tamabox DB prepared by Plan 02a"
    - "All 6 tables exist with utf8mb4_0900_ai_ci collation and InnoDB engine"
    - "All 6 tables have CHAR(36) primary key named id (INFORMATION_SCHEMA introspection)"
    - "All CHECK constraints from DB-SCHEMA.md v0.2 are present (display_name length, ssr_probability range, slug regex, body_length, blocks_no_self, deleted_reason allowed-values)"
    - "FK cascade directions match DB-SCHEMA.md v0.2: CASCADE for ownership deletion, RESTRICT for fk_messages_sender (逃げ得防止), SET_NULL for fk_reports_reporter"
    - "bin/cake migrations status shows all 6 migrations as 'up'"
    - "bin/cake migrations rollback --target=0 fully reverses all 6 migrations (down() methods work), and re-migrate is idempotent"
  artifacts:
    - path: "config/Migrations/20260422120004_CreateMessages.php"
      provides: "messages table with inbox FK CASCADE, sender_user_id FK RESTRICT (逃げ得), is_ssr+ssr_seed columns (seed value computed in Phase 3), sender snapshot (handle/avatar/profile_url), deleted_reason VARCHAR(64), composite index (inbox_id, deleted_at)"
      contains: "class CreateMessages extends AbstractMigration"
      min_lines: 90
    - path: "config/Migrations/20260422120005_CreateBlocks.php"
      provides: "blocks table with both FKs CASCADE, composite UNIQUE (blocker_user_id, blocked_user_id), blocks_no_self CHECK (blocker <> blocked)"
      contains: "class CreateBlocks extends AbstractMigration"
      min_lines: 55
    - path: "config/Migrations/20260422120006_CreateReports.php"
      provides: "reports table with message FK CASCADE, reporter FK SET_NULL (nullable), reason ENUM (4 values), status ENUM"
      contains: "class CreateReports extends AbstractMigration"
      min_lines: 60
  key_links:
    - from: "messages.inbox_id"
      to: "inboxes.id"
      via: "FK ON DELETE CASCADE"
      pattern: "fk_messages_inbox"
    - from: "messages.sender_user_id"
      to: "users.id"
      via: "FK ON DELETE RESTRICT (逃げ得防止 — sender snapshot preserved)"
      pattern: "fk_messages_sender"
    - from: "blocks.blocker_user_id + blocks.blocked_user_id"
      to: "users.id (both)"
      via: "FK ON DELETE CASCADE; blocks_no_self CHECK ensures blocker != blocked"
      pattern: "fk_blocks_(blocker|blocked)"
    - from: "reports.message_id"
      to: "messages.id"
      via: "FK ON DELETE CASCADE"
      pattern: "fk_reports_message"
    - from: "reports.reporter_user_id"
      to: "users.id"
      via: "FK ON DELETE SET NULL (reporter account removal preserves report)"
      pattern: "fk_reports_reporter"
---

<objective>
Plan 02a が書いた 3 migration (users / user_identities / inboxes) に続き、依存側の 3 テーブル (messages / blocks / reports) の Phinx migration を書き、`bin/cake migrations migrate` を実行して MySQL 8.0 上にスキーマを投入、INFORMATION_SCHEMA 経由で DDL 一致を検証する。

Purpose:
- INFRA-04 の後半 ── 依存テーブル 3 つの migration + 全 6 テーブルの migrate 実行 + 検証で ROADMAP Phase 1 success criterion #4 を閉じる。
- CHECK 制約は Phinx 0.13 にネイティブ API がないため raw SQL (`$this->execute`) で書く。`up()`/`down()` 明示ペア。
- 検証は INFORMATION_SCHEMA 経由 (RESEARCH Open Questions Q3 RESOLVED) — portable SQL で charset/collation/PK/FK/CHECK constraints を一気に点検。
- 復旧手順: migration 失敗時は `bin/cake migrations rollback --target=<prev>` を per-step で実行 (Q5 RESOLVED)。

Output:
- `config/Migrations/` 配下に 3 ファイル追加 (messages / blocks / reports)
- `bin/cake migrations migrate` がローカル MySQL 8.0 で成功
- `bin/cake migrations status` で全 6 件 up
- INFORMATION_SCHEMA 検証ログ (tables, columns, FKs, CHECK constraints)

**Non-goals (Plan 02b 外):**
- `ssr_seed` の計算式 (`sha256(server_secret + message_id + created_at)`) ← Phase 3 (MSG-03)
- 通報レビュー UI ← Phase 4
- bake Table classes ← Plan 01-03
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/01-foundation-schema/01-CONTEXT.md
@.planning/phases/01-foundation-schema/01-RESEARCH.md
@.planning/phases/01-foundation-schema/01-PATTERNS.md
@.planning/phases/01-foundation-schema/01-01-SUMMARY.md
@.planning/phases/01-foundation-schema/01-02a-SUMMARY.md
@.planning/codebase/STACK.md
@.planning/codebase/ARCHITECTURE.md

<prereqs>
## Prerequisites

1. Plan 01-02a must be complete:
   - `config/.env` populated (SERVER_SECRET, SECURITY_SALT)
   - MySQL 8.0.16+ running
   - tamabox + test_tamabox databases exist with utf8mb4_0900_ai_ci
   - config/Migrations/ contains 3 files: 20260422120001_CreateUsers.php, 20260422120002_CreateUserIdentities.php, 20260422120003_CreateInboxes.php

2. DB-SCHEMA.md v0.2 fetched at /tmp/DB-SCHEMA.md (re-fetch if missing):
   ```
   gh api repos/emomie/ssr-box-discovery/contents/DB-SCHEMA.md --jq '.content' | base64 -d > /tmp/DB-SCHEMA.md
   ```

3. `bin/cake migrations migrate` is NOT yet run (Plan 02a deliberately stopped at file-creation-only).
</prereqs>

<decisions_locked>
## Decisions Locked (from CONTEXT.md + RESEARCH.md resolutions — identical set to 02a)

- **A2 ENUM-vs-VARCHAR:** `messages.sender_provider`, `reports.reason`, `reports.status` stay as MySQL ENUM per DB-SCHEMA.md verbatim. Only `messages.deleted_reason` is VARCHAR(64) (per CONTEXT.md `<specifics>`).
- **A3 timestamp-vs-datetime for updated_at:** `updated_at` uses Phinx `timestamp` type with `limit:6, update:'CURRENT_TIMESTAMP(6)'` (2038 ceiling accepted). `created_at`, `deleted_at`, `opened_at` use Phinx `datetime` with `limit:6`.
- **A7 CHECK naming:** `<table>_<field>_check`.
- **D-06 file naming:** hand-numbered timestamps. FK order: users → user_identities → inboxes → messages → blocks → reports.
- **D-10 DB-SCHEMA.md verbatim.**
- **D-12 no partial index:** composite `(inbox_id, deleted_at)` for messages.
- **Raw SQL pattern:** every CHECK via `$this->execute(...)` at end of `up()`. No `change()`.
- **Recovery (Q5 RESOLVED):** on migration failure, `bin/cake migrations rollback --target=<prev_timestamp>` per-step, then re-run `bin/cake migrations migrate`. Manual `DROP TABLE <failed>` only if rollback is blocked (e.g. partial DDL state from crashed migration).
</decisions_locked>

<canonical_template>
## Canonical Migration Skeleton (see Plan 02a for the full template — same shape here)

Shared header, `$autoId = false`, `up()`/`down()`, raw SQL for CHECK. FK/ENUM/UNIQUE patterns per RESEARCH.md §Architecture Patterns.
</canonical_template>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Write CreateMessages migration (20260422120004)</name>
  <read_first>
    - /tmp/DB-SCHEMA.md section on `messages` — read verbatim (this is the most complex table)
    - .planning/phases/01-foundation-schema/01-RESEARCH.md: FK cascade table (sender_user_id RESTRICT), Pattern 4 inconsistency note (deleted_reason = VARCHAR(64) per CONTEXT.md `<specifics>`)
    - .planning/phases/01-foundation-schema/01-CONTEXT.md `<specifics>` — ssr_seed column is stub (no computation in Phase 1), deleted_reason VARCHAR(64) rationale
    - .planning/phases/01-foundation-schema/01-PATTERNS.md — CreateMessages row
  </read_first>
  <files>config/Migrations/20260422120004_CreateMessages.php</files>
  <action>
  Write the fourth migration (the widest table).

  Columns (verify each vs DB-SCHEMA.md v0.2 §4):
  - `id` uuid NOT NULL PRIMARY KEY
  - `inbox_id` uuid NOT NULL  (FK → inboxes.id CASCADE)
  - `sender_user_id` uuid NOT NULL  (FK → users.id **RESTRICT** — 逃げ得防止; DO NOT make nullable)
  - `body` TEXT NOT NULL  (Phinx: `text` — verify DB-SCHEMA.md specifies TEXT vs MEDIUMTEXT vs VARCHAR)
  - `sender_provider` ENUM('bluesky','x') NOT NULL  (Pattern 4)
  - `sender_handle_snapshot` VARCHAR(255) NOT NULL  (MSG-04 — snapshot preserved even after sender deletion)
  - `sender_avatar_url_snapshot` VARCHAR(2048) NULL
  - `sender_profile_url_snapshot` VARCHAR(2048) NULL
  - `is_ssr` BOOLEAN NOT NULL  (MSG-02 — computed in Phase 3 at send time)
  - `ssr_seed` VARCHAR(64) NULL  (MSG-03 — value = `sha256(server_secret + message_id + created_at)`, computed in Phase 3; Phase 1 only defines the column)
  - `opened_at` DATETIME(6) NULL  (MSG-06)
  - `deleted_at` DATETIME(6) NULL  (MSG-08)
  - `deleted_reason` VARCHAR(64) NULL  (per CONTEXT.md `<specifics>`: NOT ENUM; allowed values ‘user’ / ‘report_actioned’ / ‘account_removed’ enforced at app layer, documented in DDL comment)
  - `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
  - `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)

  Indexes:
  - `idx_messages_inbox_deleted` composite on (inbox_id, deleted_at) — D-12 supports dashboard lists with soft-delete filter
  - `idx_messages_sender` on (sender_user_id)
  - `idx_messages_opened` on (opened_at) — optional; only include if DB-SCHEMA.md v0.2 specifies it

  FKs:
  - `fk_messages_inbox` on `inbox_id` → `inboxes.id`, `'delete' => 'CASCADE'`
  - `fk_messages_sender` on `sender_user_id` → `users.id`, **`'delete' => 'RESTRICT'`** (critical — 逃げ得防止 per DB-SCHEMA.md v0.2 FK table)

  CHECK constraints (per DB-SCHEMA.md v0.2):
  - `messages_body_length_check` — `CHAR_LENGTH(body) BETWEEN 1 AND 2000` (verify exact max against DB-SCHEMA.md)
  - `messages_deleted_reason_check` — `deleted_reason IS NULL OR deleted_reason IN ('user','report_actioned','account_removed')` (enforces the VARCHAR(64) allowed-value list at DB level, replacing ENUM ALTER fragility — matches CONTEXT.md `<specifics>`)
  - `messages_ssr_seed_length_check` — `ssr_seed IS NULL OR CHAR_LENGTH(ssr_seed) = 64` (sha256 hex = 64 chars; only add if DB-SCHEMA.md v0.2 has it)

  Add a docblock comment on `ssr_seed` column (inline // or via PHP docblock) noting: "Populated in Phase 3 by SsrService::computeSeed; sha256 hex of server_secret || message_id || created_at."

  down(): drop table (FK to users with RESTRICT means we must drop messages BEFORE users — because rollback runs in reverse migration order, users.down() runs AFTER messages.down(), which is the correct order).
  </action>
  <verify>
    <automated>test -f config/Migrations/20260422120004_CreateMessages.php && php -l config/Migrations/20260422120004_CreateMessages.php | grep -q 'No syntax errors' && grep -q 'class CreateMessages extends AbstractMigration' config/Migrations/20260422120004_CreateMessages.php && grep -qE "addForeignKey.*'inbox_id'.*'inboxes'" config/Migrations/20260422120004_CreateMessages.php && grep -q 'fk_messages_inbox' config/Migrations/20260422120004_CreateMessages.php && grep -qE "addForeignKey.*'sender_user_id'.*'users'" config/Migrations/20260422120004_CreateMessages.php && grep -q 'fk_messages_sender' config/Migrations/20260422120004_CreateMessages.php && grep -qE "'delete'\s*=>\s*'RESTRICT'" config/Migrations/20260422120004_CreateMessages.php && grep -qE "'sender_provider',\s*'enum'" config/Migrations/20260422120004_CreateMessages.php && grep -q 'sender_handle_snapshot' config/Migrations/20260422120004_CreateMessages.php && grep -q 'is_ssr' config/Migrations/20260422120004_CreateMessages.php && grep -q 'ssr_seed' config/Migrations/20260422120004_CreateMessages.php && grep -qE "'deleted_reason',\s*'string'" config/Migrations/20260422120004_CreateMessages.php && grep -q 'idx_messages_inbox_deleted' config/Migrations/20260422120004_CreateMessages.php && grep -qE 'messages_body_length_check|messages_body_check' config/Migrations/20260422120004_CreateMessages.php && ! grep -q 'public function change' config/Migrations/20260422120004_CreateMessages.php</automated>
  </verify>
  <acceptance_criteria>
    - File exists, `php -l` clean
    - Class `CreateMessages`, `$autoId = false`
    - FK `fk_messages_inbox` to inboxes CASCADE
    - FK `fk_messages_sender` to users **RESTRICT** (critical — DB-level 逃げ得防止)
    - `sender_provider` column uses Phinx enum
    - `sender_handle_snapshot` column NOT NULL
    - `is_ssr` boolean column present
    - `ssr_seed` column present (nullable, VARCHAR-length = 64)
    - `deleted_reason` is VARCHAR-like (`'string'` type), NOT enum
    - Composite index `idx_messages_inbox_deleted` on (inbox_id, deleted_at)
    - CHECK on body length present
    - No `change()` method
  </acceptance_criteria>
  <done>CreateMessages migration written with full sender-snapshot columns, FK RESTRICT to users (not CASCADE), SSR columns stubbed, body-length CHECK in place.</done>
</task>

<task type="auto">
  <name>Task 2: Write CreateBlocks migration (20260422120005)</name>
  <read_first>
    - /tmp/DB-SCHEMA.md section on `blocks` — verbatim
    - .planning/phases/01-foundation-schema/01-PATTERNS.md — CreateBlocks row
    - .planning/phases/01-foundation-schema/01-RESEARCH.md: Patterns 2, 3
  </read_first>
  <files>config/Migrations/20260422120005_CreateBlocks.php</files>
  <action>
  Write the fifth migration.

  Columns (DB-SCHEMA.md v0.2 §5):
  - `id` uuid NOT NULL PRIMARY KEY
  - `blocker_user_id` uuid NOT NULL  (FK → users.id CASCADE)
  - `blocked_user_id` uuid NOT NULL  (FK → users.id CASCADE)
  - `reason` VARCHAR(255) NULL  (verify against DB-SCHEMA.md — may be absent or ENUM)
  - `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
  - `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)

  Indexes:
  - Composite UNIQUE `uk_blocks_blocker_blocked` on (blocker_user_id, blocked_user_id)
  - Standard index `idx_blocks_blocked` on (blocked_user_id) — for "is user X blocked by anyone who owns inbox Y" lookups

  FKs:
  - `fk_blocks_blocker` on `blocker_user_id` → `users.id`, `'delete' => 'CASCADE'`
  - `fk_blocks_blocked` on `blocked_user_id` → `users.id`, `'delete' => 'CASCADE'`

  CHECK:
  - `blocks_no_self_check` — `blocker_user_id <> blocked_user_id`

  Raw SQL:

      $this->execute(<<<SQL
      ALTER TABLE blocks
        ADD CONSTRAINT blocks_no_self_check
        CHECK (blocker_user_id <> blocked_user_id)
      SQL);

  down(): drop table.
  </action>
  <verify>
    <automated>test -f config/Migrations/20260422120005_CreateBlocks.php && php -l config/Migrations/20260422120005_CreateBlocks.php | grep -q 'No syntax errors' && grep -q 'class CreateBlocks extends AbstractMigration' config/Migrations/20260422120005_CreateBlocks.php && grep -q 'blocker_user_id' config/Migrations/20260422120005_CreateBlocks.php && grep -q 'blocked_user_id' config/Migrations/20260422120005_CreateBlocks.php && grep -q 'fk_blocks_blocker' config/Migrations/20260422120005_CreateBlocks.php && grep -q 'fk_blocks_blocked' config/Migrations/20260422120005_CreateBlocks.php && grep -qc "'delete'\s*=>\s*'CASCADE'" config/Migrations/20260422120005_CreateBlocks.php && grep -q 'uk_blocks_blocker_blocked' config/Migrations/20260422120005_CreateBlocks.php && grep -q 'blocks_no_self_check' config/Migrations/20260422120005_CreateBlocks.php && grep -qE "blocker_user_id\s*<>\s*blocked_user_id" config/Migrations/20260422120005_CreateBlocks.php && ! grep -q 'public function change' config/Migrations/20260422120005_CreateBlocks.php</automated>
  </verify>
  <acceptance_criteria>
    - File exists, `php -l` clean
    - Class `CreateBlocks`, `$autoId = false`
    - Both FKs CASCADE to users
    - Composite UNIQUE on (blocker_user_id, blocked_user_id)
    - CHECK `blocks_no_self_check` with `blocker_user_id <> blocked_user_id`
    - No `change()` method
  </acceptance_criteria>
  <done>CreateBlocks migration written with dual FK CASCADE, composite UNIQUE, self-block CHECK.</done>
</task>

<task type="auto">
  <name>Task 3: Write CreateReports migration (20260422120006)</name>
  <read_first>
    - /tmp/DB-SCHEMA.md section on `reports` — verbatim
    - .planning/phases/01-foundation-schema/01-RESEARCH.md: FK cascade table (reporter_user_id → SET_NULL, column MUST be nullable), Pattern 4
    - .planning/phases/01-foundation-schema/01-PATTERNS.md — CreateReports row
  </read_first>
  <files>config/Migrations/20260422120006_CreateReports.php</files>
  <action>
  Write the sixth and final migration.

  Columns (DB-SCHEMA.md v0.2 §6):
  - `id` uuid NOT NULL PRIMARY KEY
  - `message_id` uuid NOT NULL  (FK → messages.id CASCADE)
  - `reporter_user_id` uuid NULL  (**MUST be nullable** because FK uses SET NULL per RESEARCH Pattern 2 constraint + MOD-03 rationale)
  - `reason` ENUM('harassment','spam','illegal','other') NOT NULL  (Pattern 4 — the 4 categories per MOD-01)
  - `status` ENUM('pending','actioned','dismissed') NOT NULL DEFAULT 'pending'  (verify exact values + default against DB-SCHEMA.md v0.2; typical values)
  - `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
  - `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)

  Indexes:
  - `idx_reports_message` on (message_id)
  - `idx_reports_status_created` composite on (status, created_at) — for the pending-review queue in Phase 4

  FKs:
  - `fk_reports_message` on `message_id` → `messages.id`, `'delete' => 'CASCADE'`
  - `fk_reports_reporter` on `reporter_user_id` → `users.id`, **`'delete' => 'SET_NULL'`** — CRITICAL: the column MUST be `'null' => true` or Phinx will error (RESEARCH Pattern 2 constraint)

  CHECK: none beyond what ENUM naturally enforces — MOD-02 explicitly states no DDL-level content filtering.

  down(): drop table.
  </action>
  <verify>
    <automated>test -f config/Migrations/20260422120006_CreateReports.php && php -l config/Migrations/20260422120006_CreateReports.php | grep -q 'No syntax errors' && grep -q 'class CreateReports extends AbstractMigration' config/Migrations/20260422120006_CreateReports.php && grep -qE "'message_id',\s*'uuid'" config/Migrations/20260422120006_CreateReports.php && grep -qE "'reporter_user_id',\s*'uuid'" config/Migrations/20260422120006_CreateReports.php && grep -A 3 "'reporter_user_id'" config/Migrations/20260422120006_CreateReports.php | grep -q "'null' => true" && grep -qE "'reason',\s*'enum'" config/Migrations/20260422120006_CreateReports.php && grep -qE "'harassment'.*'spam'.*'illegal'.*'other'" config/Migrations/20260422120006_CreateReports.php && grep -qE "'status',\s*'enum'" config/Migrations/20260422120006_CreateReports.php && grep -q 'fk_reports_message' config/Migrations/20260422120006_CreateReports.php && grep -q 'fk_reports_reporter' config/Migrations/20260422120006_CreateReports.php && grep -qE "'delete'\s*=>\s*'SET_NULL'" config/Migrations/20260422120006_CreateReports.php && grep -qE "'delete'\s*=>\s*'CASCADE'" config/Migrations/20260422120006_CreateReports.php && ! grep -q 'public function change' config/Migrations/20260422120006_CreateReports.php</automated>
  </verify>
  <acceptance_criteria>
    - File exists, `php -l` clean
    - Class `CreateReports`, `$autoId = false`
    - `reporter_user_id` column is nullable (`'null' => true`)
    - `reason` ENUM contains values `harassment`, `spam`, `illegal`, `other` (order may vary)
    - `status` ENUM with `'pending'` default
    - FK `fk_reports_message` CASCADE
    - FK `fk_reports_reporter` SET_NULL
    - Indexes on (message_id) and (status, created_at)
    - No `change()` method
  </acceptance_criteria>
  <done>CreateReports migration written. 6/6 migrations on disk, FK-order timestamps correct, ready for migrate.</done>
</task>

<task type="auto">
  <name>Task 4: Run bin/cake migrations migrate and verify schema via INFORMATION_SCHEMA introspection</name>
  <read_first>
    - All 6 migration files (3 from Plan 02a + 3 from Tasks 1-3 of this plan) — read skim to confirm present
    - .planning/phases/01-foundation-schema/01-RESEARCH.md: Pitfall 5 (FK order), Pitfall 1 (MySQL 8.0.16+ CHECK enforcement), Open Questions Q3 & Q5 (RESOLVED — INFORMATION_SCHEMA introspection + per-step rollback recovery), CI-style local check recipe
  </read_first>
  <files>No new files (side effect: 7 new tables created in MySQL: users, user_identities, inboxes, messages, blocks, reports, phinxlog)</files>
  <action>
  Apply all 6 migrations to the tamabox DB and verify schema matches DB-SCHEMA.md v0.2 via INFORMATION_SCHEMA (per RESEARCH Q3 RESOLVED — portable, SQL-standard introspection that covers charset/collation/PK/FK/CHECK without relying on vendor-specific `SHOW CREATE TABLE`).

  Step 1: Ensure tamabox DB is empty (from Plan 02a Task 1 bootstrap). Confirm:
      mysql tamabox -Nse "SHOW TABLES;" | wc -l
     must be 0.

  Step 2: Run migrate:
      bin/cake migrations migrate 2>&1 | tee /tmp/migrate.log
      echo "exit=$?"

     Must exit 0. Log must show 6 `== 20260422120001 CreateUsers: migrated` / `CreateUserIdentities: migrated` / … / `CreateReports: migrated` lines.

     Recovery note (per Q5 RESOLVED): if migration N fails partway through, run
         bin/cake migrations rollback --target=<timestamp-of-N-minus-1>
     to undo completed migrations back to the last good one, then fix the failing migration file and re-run `migrations migrate`. Only resort to manual `DROP TABLE <failed>` if rollback itself fails (e.g. partial CREATE TABLE in InnoDB). Each migration has explicit `down()` so rollback is deterministic.

  Step 3: Verify status:
      bin/cake migrations status 2>&1 | tee /tmp/migrate_status.log

     All 6 migrations must show status `up`.

  Step 4: INFORMATION_SCHEMA — tables + collation:
      mysql tamabox -Nse "
        SELECT TABLE_NAME, TABLE_COLLATION, ENGINE
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = 'tamabox' AND TABLE_NAME != 'phinxlog'
        ORDER BY TABLE_NAME
      " | tee /tmp/tables.log

     Expected output (6 rows — all collation utf8mb4_0900_ai_ci, all engine InnoDB):
         blocks	utf8mb4_0900_ai_ci	InnoDB
         inboxes	utf8mb4_0900_ai_ci	InnoDB
         messages	utf8mb4_0900_ai_ci	InnoDB
         reports	utf8mb4_0900_ai_ci	InnoDB
         user_identities	utf8mb4_0900_ai_ci	InnoDB
         users	utf8mb4_0900_ai_ci	InnoDB

  Step 5: INFORMATION_SCHEMA — CHAR(36) PK for all 6:
      mysql tamabox -Nse "
        SELECT TABLE_NAME, COLUMN_TYPE, IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'tamabox'
          AND COLUMN_NAME = 'id'
          AND TABLE_NAME IN ('users','user_identities','inboxes','messages','blocks','reports')
        ORDER BY TABLE_NAME
      " | tee /tmp/pk_check.log

     All rows must show `char(36)` and `NO` (not null).

  Step 6: INFORMATION_SCHEMA — FK constraints with DELETE_RULE:
      mysql tamabox -Nse "
        SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = 'tamabox'
        ORDER BY TABLE_NAME, CONSTRAINT_NAME
      " | tee /tmp/fks.log

     Expected 8 rows (order may vary):
         fk_blocks_blocked      blocks             users     CASCADE
         fk_blocks_blocker      blocks             users     CASCADE
         fk_inboxes_user        inboxes            users     CASCADE
         fk_messages_inbox      messages           inboxes   CASCADE
         fk_messages_sender     messages           users     RESTRICT
         fk_reports_message     reports            messages  CASCADE
         fk_reports_reporter    reports            users     SET NULL
         fk_user_identities_user user_identities   users     CASCADE

  Step 7: INFORMATION_SCHEMA — CHECK constraints (MySQL 8.0.16+; RESEARCH Pitfall 1):
      mysql tamabox -Nse "
        SELECT CONSTRAINT_NAME, CHECK_CLAUSE
        FROM information_schema.CHECK_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = 'tamabox'
        ORDER BY CONSTRAINT_NAME
      " | tee /tmp/checks.log

     Must include at minimum: `users_display_name_check`, `inboxes_ssr_probability_range_check`, `inboxes_slug_format_check`, `messages_body_length_check`, `messages_deleted_reason_check`, `blocks_no_self_check`. (Plus any additional per-table checks DB-SCHEMA.md specified.) The exact count is asserted in acceptance ≥ 6.

  Step 8: INFORMATION_SCHEMA — composite UNIQUE + indexes:
      mysql tamabox -Nse "
        SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = 'tamabox'
          AND INDEX_NAME != 'PRIMARY'
        GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
        ORDER BY TABLE_NAME, INDEX_NAME
      " | tee /tmp/indexes.log

     Must include: `uk_user_identities_provider_account` (unique, cols: provider,provider_account_id), `uk_user_identities_user` (unique, cols: user_id), `uk_inboxes_slug` (unique, cols: slug), `uk_blocks_blocker_blocked` (unique, cols: blocker_user_id,blocked_user_id), `idx_messages_inbox_deleted` (non-unique, cols: inbox_id,deleted_at), `idx_reports_status_created` (non-unique, cols: status,created_at).

  Step 9: Rollback sanity test — prove down() methods work per Q5 RESOLVED:
      bin/cake migrations rollback --target=0 2>&1 | tee /tmp/rollback.log
      echo "exit=$?"
      mysql tamabox -Nse "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='tamabox' AND TABLE_NAME IN ('users','user_identities','inboxes','messages','blocks','reports')" | tee /tmp/after_rollback.log

     Must exit 0. After rollback, the count must be 0 (all 6 domain tables dropped). Then re-apply:
      bin/cake migrations migrate 2>&1 | tee /tmp/remigrate.log

     Must succeed. This proves down() is correct and re-migrate is idempotent — required for Phase 4 deploy rehearsal.

  Step 10: (recommended) Test one CHECK violation to confirm enforcement is active:
      mysql tamabox -e "INSERT INTO users (id, display_name, created_at, updated_at) VALUES (UUID(), '', NOW(6), NOW(6));" 2>&1 | tee /tmp/check_test.log

     Must error with "CONSTRAINT ... failed" (CHECK violation on empty display_name). Acceptable output fragments: `Check constraint`, `failed`, `3819`, or `users_display_name_check`.

  Leave the DB in the "migrated" state (after Step 9's re-migrate) for Plan 01-03 to bake Table classes against.
  </action>
  <verify>
    <automated>bin/cake migrations status 2>&1 | grep -c 'up ' | awk '{ if ($1 >= 6) print "OK"; else print "FAIL("$1")" }' | grep -q '^OK$' && mysql tamabox -Nse "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'tamabox' AND TABLE_NAME IN ('users','user_identities','inboxes','messages','blocks','reports');" | grep -q '^6$' && mysql tamabox -Nse "SELECT COUNT(DISTINCT TABLE_COLLATION) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'tamabox' AND TABLE_NAME IN ('users','user_identities','inboxes','messages','blocks','reports');" | grep -q '^1$' && mysql tamabox -Nse "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='tamabox' AND COLUMN_NAME='id' AND COLUMN_TYPE='char(36)' AND TABLE_NAME IN ('users','user_identities','inboxes','messages','blocks','reports');" | grep -q '^6$' && mysql tamabox -Nse "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='tamabox';" | awk '{ if ($1 >= 8) print "OK"; else print "FAIL("$1")" }' | grep -q '^OK$' && mysql tamabox -Nse "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_NAME='fk_messages_sender';" | grep -q '^RESTRICT$' && mysql tamabox -Nse "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_NAME='fk_reports_reporter';" | grep -qE '^(SET NULL|SET_NULL)$' && mysql tamabox -Nse "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='tamabox';" | awk '{ if ($1 >= 6) print "OK"; else print "FAIL("$1")" }' | grep -q '^OK$'</automated>
  </verify>
  <acceptance_criteria>
    - `bin/cake migrations migrate` exits 0
    - `bin/cake migrations status` shows ≥ 6 rows in `up` state
    - All 6 domain tables exist in tamabox DB (INFORMATION_SCHEMA.TABLES)
    - All 6 tables use collation `utf8mb4_0900_ai_ci` (exactly 1 distinct collation)
    - All 6 `id` PK columns are `char(36)` (INFORMATION_SCHEMA.COLUMNS)
    - At least 8 FK constraints present in REFERENTIAL_CONSTRAINTS (8 expected FKs)
    - `fk_messages_sender` DELETE_RULE = `RESTRICT`
    - `fk_reports_reporter` DELETE_RULE = `SET NULL`
    - At least 6 CHECK constraints in CHECK_CONSTRAINTS
    - Rollback + re-migrate round trip completes without error
  </acceptance_criteria>
  <done>Schema end-to-end applied and reversible. Phase 1 ROADMAP success criterion #4 (migrate succeeds) satisfied. INFORMATION_SCHEMA introspection confirms DDL matches DB-SCHEMA.md v0.2. DB ready for Plan 01-03 Table-class bake.</done>
</task>

</tasks>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| Application SQL queries → MySQL | Untrusted user input (Phase 2+) will eventually flow into parameterized queries against these tables; constraint enforcement at DB level is the last line of defense. |
| Migration script → production DB | Phase 4 will run these migrations on Lolipop production; a flawed migration can corrupt prod. |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-01-09 | Elevation of Privilege | sender hard-deletes themselves to remove evidence (逃げ得) | mitigate | `fk_messages_sender ON DELETE RESTRICT` prevents sender row deletion while messages exist. Snapshot columns (sender_handle_snapshot etc.) preserve identity independently. |
| T-01-10 | Information Disclosure | CHECK constraints silently ignored on MySQL < 8.0.16 → invalid data persisted | mitigate | Plan 02a Task 1 validates MySQL version ≥ 8.0.16; this plan's Task 4 Step 10 tests a CHECK violation to confirm enforcement. |
| T-01-11 | Tampering | Attacker blocks themselves to bypass self-targeting logic | mitigate | `blocks_no_self_check` CHECK enforces blocker_user_id <> blocked_user_id at DB layer. |
| T-01-12 | Denial of Service | Reporter account deletion cascades report removal (losing moderation evidence) | mitigate | `fk_reports_reporter ON DELETE SET NULL` — reporter anonymized but report row (and message_id) preserved for moderation review. |
| T-01-13 | Repudiation | `ssr_seed` absent → receiver cannot audit SSR-was-computed-at-send claim | mitigate (Phase 3) | Column is defined in Phase 1 (VARCHAR(64), nullable). Phase 3 SSR service populates it at message-send and it becomes immutable (enforced at app layer since Phinx 0.13 lacks trigger support on shared hosting). |
| T-01-14 | Tampering | Migration applied to wrong DB (e.g. prod instead of dev) | accept | MVP has no automatic safeguard; developer responsibility. Phase 4 deploy plan will add an environment check. |
</threat_model>

<verification>
Final phase-level checks (after all 4 tasks in Plan 02b + prior 4 tasks in Plan 02a):

1. `ls config/Migrations/2026042212000{1,2,3,4,5,6}_Create*.php` → all 6 files exist
2. `for f in config/Migrations/2026042212000*_Create*.php; do php -l "$f" || exit 1; done` → all pass
3. `bin/cake migrations status` → 6 rows, all `up`
4. `mysql tamabox -Nse "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='tamabox' AND TABLE_NAME != 'phinxlog'"` → 6
5. `mysql tamabox -Nse "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='tamabox'"` → ≥ 6
6. `mysql tamabox -Nse "SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='tamabox'"` → 8
7. INFORMATION_SCHEMA introspection logs (`/tmp/tables.log`, `/tmp/fks.log`, `/tmp/checks.log`, `/tmp/indexes.log`) attached to SUMMARY
</verification>

<success_criteria>
- [ ] 3 additional migration files exist (messages, blocks, reports) with FK-order timestamps 20260422120004..006
- [ ] Every migration uses `public $autoId = false` + `public function up()` + `public function down()` (no `change()`)
- [ ] `bin/cake migrations migrate` succeeds on the clean tamabox DB (INFRA-04 completed across 02a + 02b)
- [ ] `bin/cake migrations status` reports all 6 as `up`
- [ ] `bin/cake migrations rollback --target=0` + re-migrate succeeds (down() reversibility verified — Q5 RESOLVED pathway)
- [ ] FK cascade directions match DB-SCHEMA.md v0.2: CASCADE for ownership, RESTRICT on fk_messages_sender, SET NULL on fk_reports_reporter (verified via INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS)
- [ ] CHECK constraints are enforced (verified via INFORMATION_SCHEMA.CHECK_CONSTRAINTS + empty-display_name INSERT rejected)
- [ ] All 6 tables use utf8mb4_0900_ai_ci + InnoDB (verified via INFORMATION_SCHEMA.TABLES)
- [ ] All 6 `id` PK columns are CHAR(36) (verified via INFORMATION_SCHEMA.COLUMNS)
</success_criteria>

<output>
After completion, create `.planning/phases/01-foundation-schema/01-02b-SUMMARY.md` containing:
- Final migration filename list (6 files total, 3 from 02a + 3 from 02b) with timestamps
- `bin/cake migrations status` output
- INFORMATION_SCHEMA introspection output (tables/collation, columns/PK, REFERENTIAL_CONSTRAINTS, CHECK_CONSTRAINTS, STATISTICS for composite UNIQUE indexes) — attach contents of /tmp/tables.log, /tmp/fks.log, /tmp/checks.log, /tmp/indexes.log
- Any deviations from DB-SCHEMA.md v0.2 discovered during implementation (e.g. column length differences, missing predicates) — flag for Phase 2-4 to re-read
- Recovery procedure verification: rollback-to-zero + re-migrate round trip log (/tmp/rollback.log + /tmp/remigrate.log)
- Handoff note to Plan 01-03: "Tables are migrated in tamabox DB. Ready for `bin/cake bake model <Name>` — bake introspects the schema to generate Table classes."
</output>
