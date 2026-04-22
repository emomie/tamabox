---
phase: 01-foundation-schema
plan: 02a
wave: 2
subsystem: schema-root
tags:
  - cakephp
  - migrations
  - phinx
  - mysql
  - schema
requirements_partial:
  - INFRA-04  # continues in Plan 02b (messages/blocks/reports + migrate run)
files_modified:
  - config/Migrations/20260422120001_CreateUsers.php (new)
  - config/Migrations/20260422120002_CreateUserIdentities.php (new)
  - config/Migrations/20260422120003_CreateInboxes.php (new)
files_not_modified:
  - config/.env (pre-existing from Wave 1; gitignored; no edit needed)
commits:
  - 025f8cb feat(01-02a): add CreateUsers migration (Task 2)
  - 4d43a65 feat(01-02a): add CreateUserIdentities migration (Task 3)
  - 16cae74 feat(01-02a): add CreateInboxes migration (Task 4)
resolved_versions:
  mysql-server: 8.0.45-0ubuntu0.24.04.1
  php: 8.3.6
  cakephp/migrations: 3.9.0
  robmorgan/phinx: 0.13.4
duration: 4m 29s
completed: 2026-04-22
---

# Phase 01 Plan 02a: Schema Root — Summary

FK-root trio of Phinx migrations (`users`, `user_identities`, `inboxes`) authored
against DB-SCHEMA.md v0.2 §1-§3. All three files pass `php -l`, class-load cleanly
through the vendor autoloader, use `$autoId = false` + explicit `up()`/`down()`
(no `change()`), apply `utf8mb4_0900_ai_ci`, and route every CHECK constraint
through raw `$this->execute()` because Phinx 0.13 has no native CHECK API.
`bin/cake migrations migrate` intentionally NOT run — that is Plan 02b Task 4's
responsibility after the messages/blocks/reports migrations are added on top.

Wave 1's prerequisites were fully in place before this plan started:
`config/.env` already populated with real `SERVER_SECRET`/`SECURITY_SALT`/DB URLs
(chmod 600, gitignored), `tamabox` MySQL user with ALL privileges on both
`tamabox` and `tamabox_test` schemas (charset `utf8mb4_0900_ai_ci`), MySQL
8.0.45 >= 8.0.16 for CHECK enforcement (RESEARCH Pitfall 1 cleared), both DBs
empty of any prior tables.

## Migration File Inventory

| Timestamp | Class | Purpose | FK | CHECKs | lines |
|-----------|-------|---------|----|----|-----|
| 20260422120001 | `CreateUsers` | Root — display_name, timestamps, deleted_at | none | `users_display_name_check` (1..64) | 85 |
| 20260422120002 | `CreateUserIdentities` | 1:1 user → SNS identity (Bluesky/X), OAuth token columns reserved | `fk_user_identities_user → users.id CASCADE` | none (ENUM enforces provider; DB-SCHEMA v0.2 defines no CHECK) | 150 |
| 20260422120003 | `CreateInboxes` | 1:1 user → inbox, slug-based URL, ssr_probability | `fk_inboxes_user → users.id CASCADE` | `inboxes_probability_range`, `inboxes_slug_format` | 132 |

Timestamps are hand-numbered per D-06 to guarantee FK dependency order
(users → user_identities → inboxes). This leaves 120004..006 for Plan 02b
(messages → blocks → reports).

## Acceptance Criteria

### Task 1: Bootstrap .env + MySQL gate

- [x] `config/.env` exists (pre-populated by Wave 1)
- [x] No `__SERVER_SECRET__` or `__SALT__` placeholders remain
- [x] MySQL 8.0.45 > 8.0.16 — CHECK constraints will be enforced, not silently ignored
- [x] `tamabox` DB reachable as user `tamabox` (`SELECT 1` → 1)
- [x] `tamabox_test` DB reachable (`SELECT 1` → 1)
- [x] Both DBs are `utf8mb4` / `utf8mb4_0900_ai_ci` at schema level
- [x] Neither DB contains residual tables (fresh state)

### Task 2: CreateUsers

- [x] File exists, `php -l` clean
- [x] `class CreateUsers extends AbstractMigration`
- [x] `public $autoId = false;`
- [x] Both `up()` and `down()` defined; no `change()`
- [x] Table collation `utf8mb4_0900_ai_ci`
- [x] Columns: `id` uuid + PK, `display_name` string(64), `created_at` datetime(6), `updated_at` timestamp(6) w/ update, `deleted_at` datetime(6) nullable
- [x] `CURRENT_TIMESTAMP(6)` literal used for defaults
- [x] `idx_users_deleted_at` index present
- [x] `users_display_name_check` CHECK via raw SQL

### Task 3: CreateUserIdentities

- [x] File exists, `php -l` clean, class-loads under autoloader
- [x] `class CreateUserIdentities`, `$autoId = false`, no `change()`
- [x] `fk_user_identities_user` FK on `user_id` → `users.id`, `delete => 'CASCADE'`
- [x] `provider` column uses Phinx `enum` type with values `['bluesky', 'x']`
- [x] Composite UNIQUE `uk_user_identities_provider_account` on `(provider, provider_account_id)`
- [x] UNIQUE `uk_user_identities_user` on `(user_id)` — MVP D-11 1:1 guard
- [x] `is_primary` BOOLEAN DEFAULT TRUE
- [x] `access_token_enc` + `refresh_token_enc` TEXT NULL reserved (Phase 2 fills)
- [x] `token_expires_at`, `last_synced_at` DATETIME(6) NULL
- [x] `created_at` / `updated_at` follow A3 convention (datetime / timestamp)

### Task 4: CreateInboxes

- [x] File exists, `php -l` clean, class-loads
- [x] `class CreateInboxes`, `$autoId = false`, no `change()`
- [x] `ssr_probability` DECIMAL(4,3) NOT NULL DEFAULT 0.100
- [x] `fk_inboxes_user` FK on `user_id` → `users.id`, `delete => 'CASCADE'`
- [x] UNIQUE `uk_inboxes_slug` on `(slug)`
- [x] UNIQUE `uk_inboxes_user` on `(user_id)` — MVP 1 inbox per user per DB-SCHEMA v0.2
- [x] CHECK `inboxes_probability_range`: `ssr_probability >= 0 AND <= 1`
- [x] CHECK `inboxes_slug_format`: `slug REGEXP '^[a-zA-Z0-9_-]{3,32}$'`
- [x] `is_accepting` BOOLEAN DEFAULT TRUE, `welcome_message` TEXT NULL (per DB-SCHEMA v0.2)

### Plan-level verification (run after all 4 tasks)

- [x] 3 migration files in `config/Migrations/` with FK-order timestamps 120001..120003
- [x] Every migration uses `public $autoId = false` + `up()`/`down()` (no `change()`)
- [x] Every migration uses `utf8mb4_0900_ai_ci` collation and CHAR(36) UUID PK
- [x] FK CASCADE on `fk_user_identities_user` and `fk_inboxes_user`
- [x] ENUM `provider` with `('bluesky','x')` on `user_identities`
- [x] DECIMAL(4,3) `ssr_probability` default 0.100 on `inboxes`
- [x] CHECK constraints declared via raw `$this->execute()`
- [x] `bin/cake migrations migrate` NOT run (correctly deferred to Plan 02b Task 4)
- [x] tamabox DB still empty (0 tables) — no accidental side-effects

## Deviations from Plan

All deviations resolve in favor of DB-SCHEMA.md v0.2 per D-10 ("DB-SCHEMA.md を
single source of truth とする"). D-10 explicitly overrides plan text paraphrases,
and the executor_context in the spawn prompt also stated: "DB-SCHEMA.md v0.2 is
the DDL source of truth. Match every CHECK constraint name, FK name, index name,
and column type / default exactly. If the plan text diverges from DB-SCHEMA.md
(e.g., index name typo), trust DB-SCHEMA.md and note the deviation."

### 1. [Rule 3 — Authoritative-source conflict] user_identities column names

- **Found during:** Task 3
- **Issue:** Plan text named the SNS handle / avatar / profile columns as plain
  `handle` / `avatar_url` / `profile_url`, with `handle` NULL. DB-SCHEMA.md v0.2 §2
  names them `handle_cached` / `avatar_url_cached` / `profile_url_cached`, with
  `handle_cached` NOT NULL (they are cached snapshots of SNS-side data, synced
  at login per DESIGN Q2 sync policy B).
- **Fix:** Used DB-SCHEMA names verbatim (`*_cached` suffix, `handle_cached`
  NOT NULL). Phase 2 AUTH-07 code will read/write these column names.
- **Files modified:** `config/Migrations/20260422120002_CreateUserIdentities.php`
- **Commit:** `4d43a65`

### 2. [Rule 3 — Authoritative-source conflict] user_identities adds `token_expires_at`

- **Found during:** Task 3
- **Issue:** DB-SCHEMA.md v0.2 §2 defines `token_expires_at DATETIME(6) NULL`,
  which the plan's column list omitted.
- **Fix:** Added `token_expires_at` column matching DB-SCHEMA type + nullability.
  This is already a "reserved for Phase 2" token-management field; Phase 2 OAuth
  refresh logic will populate it.
- **Files modified:** `config/Migrations/20260422120002_CreateUserIdentities.php`
- **Commit:** `4d43a65`

### 3. [Rule 3 — Authoritative-source conflict] user_identities token columns are TEXT, not BLOB/VARBINARY

- **Found during:** Task 3
- **Issue:** Plan text suggested "BLOB / VARBINARY" for `access_token_enc` and
  `refresh_token_enc`. DB-SCHEMA.md v0.2 §2 says `TEXT` for both. Ciphertext
  will be stored as base64 / hex ASCII (Phase 2 AUTH-07 decision), so TEXT is
  the correct type.
- **Fix:** Used Phinx `text` type (→ MySQL `TEXT`) with `null => true`.
- **Files modified:** `config/Migrations/20260422120002_CreateUserIdentities.php`
- **Commit:** `4d43a65`

### 4. [Rule 3 — Authoritative-source conflict] inboxes omits `display_name`; adds `is_accepting` + `welcome_message`

- **Found during:** Task 4
- **Issue:** Plan text listed `display_name VARCHAR(64) NOT NULL` on inboxes and
  implied a matching `inboxes_display_name_length_check` CHECK. DB-SCHEMA.md v0.2
  §3 has **no** `display_name` on inboxes. It instead defines `is_accepting
  BOOLEAN NOT NULL DEFAULT TRUE` (receive-toggle for dashboard) and
  `welcome_message TEXT NULL` (shown above the message form).
- **Fix:** Matched DB-SCHEMA v0.2 exactly — no `display_name`, no
  display-name CHECK, added `is_accepting` + `welcome_message` columns. The
  acceptance-criteria list in the plan frontmatter was interpreted against
  DB-SCHEMA-v0.2 column reality.
- **Files modified:** `config/Migrations/20260422120003_CreateInboxes.php`
- **Commit:** `16cae74`

### 5. [Rule 3 — Authoritative-source conflict] inboxes CHECK names

- **Found during:** Task 4
- **Issue:** Plan text (and plan's `<verify>` grep regex) expected
  `inboxes_ssr_probability_range_check` and `inboxes_slug_format_check`
  (`_check` suffix). DB-SCHEMA.md v0.2 §3 names them `inboxes_probability_range`
  and `inboxes_slug_format` (no suffix, not matching the CONTEXT A7 generic
  pattern). D-10 says DB-SCHEMA wins. The executor_context in the spawn prompt
  also explicitly said: "Match every CHECK constraint name ... exactly. If the
  plan text diverges from DB-SCHEMA.md (e.g., index name typo), trust
  DB-SCHEMA.md and note the deviation."
- **Fix:** Used DB-SCHEMA names verbatim.
- **Consequence:** The plan's `<verify>` automated regex at `<automated>`
  `grep -qE 'inboxes_ssr_probability_range_check'` would fail as written,
  because the name is `inboxes_probability_range`. The constraints ARE present
  and correct — just under the DB-SCHEMA-authoritative name. Plan 02b final
  task (INFORMATION_SCHEMA introspection post-`migrate`) will independently
  verify both names are persisted.
- **Files modified:** `config/Migrations/20260422120003_CreateInboxes.php`
- **Commit:** `16cae74`

### 6. [Rule 3 — Authoritative-source conflict] inboxes slug regex character class

- **Found during:** Task 4
- **Issue:** Plan text showed `slug REGEXP '^[a-z0-9-]{3,32}$'` (lowercase only,
  hyphen allowed). DB-SCHEMA.md v0.2 §3 defines the CHECK as
  `slug REGEXP '^[a-zA-Z0-9_-]{3,32}$'` (mixed case, underscore + hyphen).
- **Fix:** Used DB-SCHEMA regex verbatim — `[a-zA-Z0-9_-]{3,32}`.
- **Consequence:** Slugs like `tamabox_test` or `SomeSlug-42` will be accepted.
  If the product decision is actually lowercase-only, change the regex via a
  separate migration later — but Phase 1's job is to match the authoritative
  schema, which allows both cases.
- **Files modified:** `config/Migrations/20260422120003_CreateInboxes.php`
- **Commit:** `16cae74`

### 7. [Rule 3 — Authoritative-source conflict] inboxes adds UNIQUE on user_id

- **Found during:** Task 4
- **Issue:** Plan text described only `idx_inboxes_user` (non-unique) as the
  user_id index. DB-SCHEMA.md v0.2 §3 defines `UNIQUE KEY uk_inboxes_user
  (user_id)` — MVP is strict 1 inbox per user.
- **Fix:** Added UNIQUE index `uk_inboxes_user` on `(user_id)`. Separate
  non-unique index is redundant and omitted (the UNIQUE satisfies any
  `WHERE user_id = ?` dashboard query).
- **Files modified:** `config/Migrations/20260422120003_CreateInboxes.php`
- **Commit:** `16cae74`

### No other deviations

Tasks 1 (environment bootstrap) and 2 (CreateUsers) executed exactly as written
in the plan, with no deviations. Task 3 and Task 4 deviations are all D-10
source-of-truth corrections, not discretionary changes.

## Authentication gates encountered

None. All work was filesystem file creation + MySQL introspection queries (no
external logins, no 2FA, no OAuth).

## Handoff note to Plan 02b (Wave 3)

- Root-tier migrations (`CreateUsers`, `CreateUserIdentities`, `CreateInboxes`)
  are on disk at `config/Migrations/20260422120001..003_*.php`. All three
  pass `php -l` and class-load under the vendor autoloader.
- `bin/cake migrations migrate` has **not** been run. Plan 02b must:
  1. Add `20260422120004_CreateMessages.php` (FK to both inboxes CASCADE and
     users RESTRICT; sender-snapshot columns; VARCHAR(64) `deleted_reason`
     per CONTEXT `<specifics>`; `ssr_seed` VARCHAR(64); composite indexes
     `idx_messages_inbox_created`, `idx_messages_sender`,
     `idx_messages_inbox_opened`, `idx_messages_inbox_deleted`).
  2. Add `20260422120005_CreateBlocks.php` (both FKs CASCADE; composite UNIQUE
     `uk_blocks_pair`; `blocks_no_self` CHECK).
  3. Add `20260422120006_CreateReports.php` (message_id CASCADE, reporter_user_id
     SET NULL — column must be `null => true`; ENUM `reason` and `status`).
  4. Run `bin/cake migrations migrate` end-to-end, then INFORMATION_SCHEMA
     introspection verifying: 6 tables present, collation, FK cascade
     directions, and **the 5 CHECK constraints under their DB-SCHEMA-verbatim
     names** (`users_display_name_check`, `inboxes_probability_range`,
     `inboxes_slug_format`, `messages_body_length_check`, `messages_body_size`,
     `blocks_no_self`).
- When writing 02b, prefer DB-SCHEMA.md v0.2 over any plan paraphrase per D-10.
- The dev tamabox user password (`EYfYr6emvYjyvNtbKV5xlcEFoEku7VVOwd4rFtW0`)
  is in `config/.env` as `DATABASE_URL`; `bin/cake` reads it automatically via
  the Wave 1 dotenv loader. No additional env vars need to be set.

## Known Stubs

- `user_identities.access_token_enc`, `refresh_token_enc` (TEXT, NULL)
  — intentionally empty at schema-creation time; Phase 2 AUTH-07 populates them
  with AES-GCM ciphertext. Not a bug, not a blocker for Plan 02b.
- `user_identities.is_primary` (BOOLEAN DEFAULT TRUE) — always TRUE during MVP
  per D-11; will become meaningful when account linking lands post-MVP.

## Threat Flags

No new threat surface beyond what's in the plan's `<threat_model>`.
Mitigations in place at schema level for:

- **T-01-07** (slug path-traversal / malformed) → `inboxes_slug_format` CHECK
  with REGEXP `^[a-zA-Z0-9_-]{3,32}$` enforces format at DB layer.
- **T-01-08** (display_name overflow / empty) → `users_display_name_check` CHECK
  on users enforces 1..64 at DB layer. Note: there is NO display_name on inboxes
  per DB-SCHEMA v0.2, so no equivalent inboxes-level CHECK (deviation #4 above).
- **T-01-10** (CHECK silently ignored on old MySQL) → MySQL version gate in Task
  1 verified `8.0.45 >= 8.0.16`; CHECK enforcement is live.
- **T-01-14** (migration against wrong DB) → accepted per plan; Phase 4 deploy
  plan will add env-check gating.

## Self-Check

**Commits (verified on `main`):**
- FOUND: 025f8cb (Task 2 — CreateUsers)
- FOUND: 4d43a65 (Task 3 — CreateUserIdentities)
- FOUND: 16cae74 (Task 4 — CreateInboxes)

**Files:**
- FOUND: config/Migrations/20260422120001_CreateUsers.php (85 lines)
- FOUND: config/Migrations/20260422120002_CreateUserIdentities.php (150 lines)
- FOUND: config/Migrations/20260422120003_CreateInboxes.php (132 lines)

**Verification:**
- FOUND: `php -l` passes on all 3 migration files
- FOUND: `require "vendor/autoload.php"; require <each migration>` — all 3 classes reflect cleanly
- FOUND: `mysql -Nse "SELECT VERSION();"` → 8.0.45 (≥ 8.0.16)
- FOUND: tamabox / tamabox_test schemas exist with utf8mb4_0900_ai_ci
- FOUND: 0 tables in tamabox / tamabox_test (migrations intentionally not run; for Plan 02b)
- FOUND: 3/3 migration files have `public $autoId = false`
- FOUND: 3/3 migration files have `utf8mb4_0900_ai_ci` collation
- FOUND: 3/3 migration files use `'id', 'uuid'` PK
- FOUND: 0/3 migration files use `change()` method

## Self-Check: PASSED
