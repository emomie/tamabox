---
phase: 01-foundation-schema
plan: 02b
wave: 3
subsystem: schema-dependents
tags:
  - cakephp
  - migrations
  - phinx
  - mysql
  - schema
  - information_schema
requirements_completed:
  - INFRA-04  # full plan 01-02a + 01-02b arc — all 6 tables migrated
files_modified:
  - config/Migrations/20260422120004_CreateMessages.php (new, 216 lines)
  - config/Migrations/20260422120005_CreateBlocks.php (new, 115 lines)
  - config/Migrations/20260422120006_CreateReports.php (new, 157 lines)
  - .gitignore (+3 lines; schema-dump-*.lock pattern)
files_not_committed:
  - config/app_local.php (new, 58 lines; gitignored by CakePHP convention — contains DB URL env passthrough; created as Rule 3 fix)
commits:
  - dff4cbf feat(01-02b): add CreateMessages migration (Task 1)
  - 3d8662a feat(01-02b): add CreateBlocks migration (Task 2)
  - 2238c7d feat(01-02b): add CreateReports migration (Task 3)
  - 4eb0704 feat(01-02b): migrate schema + verify via INFORMATION_SCHEMA (Task 4)
resolved_versions:
  mysql-server: 8.0.45-0ubuntu0.24.04.1
  php: 8.3.6
  cakephp/cakephp: 4.5.8
  cakephp/migrations: 3.9.0
  robmorgan/phinx: 0.13.4
key-decisions:
  - DB-SCHEMA.md v0.2 wins over plan text (D-10) — 6 verbatim-name deviations tracked
  - messages.ssr_seed declared NOT NULL per DB-SCHEMA (plan text said nullable); Phase 3 MSG-03 populates it before any row is inserted
  - messages has body_length INT NOT NULL as a stored column (not purely computed); messages_body_length_check enforces equality with CHAR_LENGTH(body)
  - reports.status is 4-value ENUM (pending/reviewed/actioned/dismissed) per DB-SCHEMA v0.2
  - messages has no updated_at column (DB-SCHEMA v0.2 only defines created_at; messages are immutable post-send apart from opened_at/deleted_at)
  - blocks has no updated_at column (create-or-delete, never mutated)
  - reports has no updated_at column (status + reviewed_at encode the audit trail)
  - no messages_deleted_reason_check CHECK (app-layer enforcement per CONTEXT <specifics>)
duration: 6m 57s
completed: 2026-04-22
---

# Phase 01 Plan 02b: Schema Dependents — Summary

Dependent-tier Phinx migrations (`messages`, `blocks`, `reports`) authored
against DB-SCHEMA.md v0.2 §4-§6, then the full 6-table schema applied to the
local MySQL 8.0.45 tamabox database via `bin/cake migrations migrate`. Schema
validated through portable INFORMATION_SCHEMA introspection (Pitfall 1 / Q3
RESOLVED path). Rollback reversibility proven end-to-end: all 6 migrations
rolled back to zero with `bin/cake migrations rollback --target=0`, DB confirmed
empty of domain tables, then re-applied without modification — round trip
succeeded, satisfying Phase 4 deploy-rehearsal prerequisite (Q5 RESOLVED).

ROADMAP Phase 1 success criterion #4 (`bin/cake migrations migrate` succeeds)
is now closed. INFRA-04 is fully complete across Waves 2 + 3.

## Migration File Inventory

| Timestamp | Class | Lines | FKs | CHECKs | Indexes (non-PK) |
|-----------|-------|-------|-----|----|----|
| 20260422120001 | `CreateUsers` (Wave 2) | 85 | none | `users_display_name_check` | `idx_users_deleted_at` |
| 20260422120002 | `CreateUserIdentities` (Wave 2) | 150 | `fk_user_identities_user` CASCADE | none (ENUM enforces provider) | `uk_user_identities_provider_account`, `uk_user_identities_user` |
| 20260422120003 | `CreateInboxes` (Wave 2) | 132 | `fk_inboxes_user` CASCADE | `inboxes_probability_range`, `inboxes_slug_format` | `uk_inboxes_user`, `uk_inboxes_slug` |
| 20260422120004 | `CreateMessages` (Wave 3) | 216 | `fk_messages_inbox` CASCADE, `fk_messages_sender` **RESTRICT** | `messages_body_length_check`, `messages_body_size` | `idx_messages_inbox_created`, `idx_messages_sender`, `idx_messages_inbox_opened`, `idx_messages_inbox_deleted` |
| 20260422120005 | `CreateBlocks` (Wave 3) | 115 | `fk_blocks_blocker` CASCADE, `fk_blocks_blocked` CASCADE | `blocks_no_self` | `uk_blocks_pair`, `idx_blocks_blocked` |
| 20260422120006 | `CreateReports` (Wave 3) | 157 | `fk_reports_message` CASCADE, `fk_reports_reporter` **SET NULL** | none (ENUMs enforce domains) | `idx_reports_status`, `idx_reports_message` (+ implicit `fk_reports_reporter` index) |

Total on-disk: 6 migrations, 845 lines of PHP. FK order: users → user_identities
→ inboxes → messages → blocks → reports (D-06 hand-numbered timestamps).

## `bin/cake migrations status` output

```
 Status  Migration ID    Migration Name
-----------------------------------------
     up  20260422120001  CreateUsers
     up  20260422120002  CreateUserIdentities
     up  20260422120003  CreateInboxes
     up  20260422120004  CreateMessages
     up  20260422120005  CreateBlocks
     up  20260422120006  CreateReports
```

All 6 up. (Log: `/tmp/migrate_status.log`.)

## INFORMATION_SCHEMA verification

### TABLES — collation + engine (`/tmp/tables.log`)

```
blocks           utf8mb4_0900_ai_ci  InnoDB
inboxes          utf8mb4_0900_ai_ci  InnoDB
messages         utf8mb4_0900_ai_ci  InnoDB
reports          utf8mb4_0900_ai_ci  InnoDB
user_identities  utf8mb4_0900_ai_ci  InnoDB
users            utf8mb4_0900_ai_ci  InnoDB
```

6 domain tables (phinxlog excluded), single collation, single engine.

### COLUMNS — `id` PK (`/tmp/pk_check.log`)

```
blocks           char(36)  NO
inboxes          char(36)  NO
messages         char(36)  NO
reports          char(36)  NO
user_identities  char(36)  NO
users            char(36)  NO
```

All 6 `id` columns are CHAR(36) NOT NULL.

### REFERENTIAL_CONSTRAINTS — FKs (`/tmp/fks.log`)

```
fk_blocks_blocked        blocks           users     CASCADE
fk_blocks_blocker        blocks           users     CASCADE
fk_inboxes_user          inboxes          users     CASCADE
fk_messages_inbox        messages         inboxes   CASCADE
fk_messages_sender       messages         users     RESTRICT   ← 逃げ得防止 (T-01-09 mitigated)
fk_reports_message       reports          messages  CASCADE
fk_reports_reporter      reports          users     SET NULL   ← moderation-trail retention (T-01-12 mitigated)
fk_user_identities_user  user_identities  users     CASCADE
```

8 FKs present. Critical cascade directions match DB-SCHEMA.md v0.2 exactly.

### CHECK_CONSTRAINTS (`/tmp/checks.log`)

```
blocks_no_self               (`blocker_user_id` <> `blocked_user_id`)
inboxes_probability_range    ((`ssr_probability` >= 0) and (`ssr_probability` <= 1))
inboxes_slug_format          regexp_like(`slug`,_utf8mb4'^[a-zA-Z0-9_-]{3,32}$')
messages_body_length_check   (`body_length` = char_length(`body`))
messages_body_size           (char_length(`body`) between 1 and 2000)
users_display_name_check     (char_length(`display_name`) between 1 and 64)
```

6 CHECK constraints, all enforced (MySQL 8.0.45 >= 8.0.16 — Pitfall 1 cleared).
Smoke test (Step 10): `INSERT INTO users (id, display_name, ...) VALUES (UUID(), '', ...)` →
`ERROR 3819 (HY000): Check constraint 'users_display_name_check' is violated`.

### STATISTICS — indexes (`/tmp/indexes.log`)

| Table | Index | Unique | Columns |
|-------|-------|--------|---------|
| blocks | `idx_blocks_blocked` | no | blocked_user_id |
| blocks | `uk_blocks_pair` | **yes** | blocker_user_id, blocked_user_id |
| inboxes | `uk_inboxes_slug` | **yes** | slug |
| inboxes | `uk_inboxes_user` | **yes** | user_id |
| messages | `idx_messages_inbox_created` | no | inbox_id, created_at |
| messages | `idx_messages_inbox_deleted` | no | inbox_id, deleted_at |
| messages | `idx_messages_inbox_opened` | no | inbox_id, opened_at |
| messages | `idx_messages_sender` | no | sender_user_id |
| reports | `fk_reports_reporter` | no | reporter_user_id |
| reports | `idx_reports_message` | no | message_id |
| reports | `idx_reports_status` | no | status, created_at |
| user_identities | `uk_user_identities_provider_account` | **yes** | provider, provider_account_id |
| user_identities | `uk_user_identities_user` | **yes** | user_id |
| users | `idx_users_deleted_at` | no | deleted_at |

14 indexes (PRIMARY excluded). Phinx auto-created `fk_reports_reporter` as a
secondary index on the FK column (standard InnoDB FK auxiliary index); not
explicitly declared in the migration but required for FK performance and
counted separately from `idx_reports_message`.

## Rollback round-trip log

### `migrations rollback --target=0` (`/tmp/rollback.log`)

```
 == 20260422120006 CreateReports: reverted 0.0142s
 == 20260422120005 CreateBlocks: reverted 0.0176s
 == 20260422120004 CreateMessages: reverted 0.0248s
 == 20260422120003 CreateInboxes: reverted 0.0103s
 == 20260422120002 CreateUserIdentities: reverted 0.0080s
 == 20260422120001 CreateUsers: reverted 0.1196s
All Done. Took 0.1964s
```

Exit 0. Reverse migration order respects FK dependencies (reports/messages/blocks
dropped before their ancestor tables) — proves explicit `down()` methods are
correct.

Post-rollback table count: 0 domain tables, phinxlog preserved (Phinx's own
tracking table — normal).

### `migrations migrate` (re-apply, `/tmp/remigrate.log`)

```
 == 20260422120001 CreateUsers: migrated 0.0506s
 == 20260422120002 CreateUserIdentities: migrated 0.0912s
 == 20260422120003 CreateInboxes: migrated 0.1528s
 == 20260422120004 CreateMessages: migrated 0.3364s
 == 20260422120005 CreateBlocks: migrated 0.1180s
 == 20260422120006 CreateReports: migrated 0.9055s
All Done. Took 0.9055s
```

Exit 0. Re-migrate succeeds without modification — round trip verified.
**Pass.**

## Deviations from Plan

All deviations continue the pattern established in Wave 2: DB-SCHEMA.md v0.2 is
the single source of truth per D-10; when plan text disagrees with DB-SCHEMA,
trust DB-SCHEMA and document.

### 1. [Rule 3 — Authoritative-source conflict] messages adds `body_length` + `ssr_probability_at_send` columns

- **Found during:** Task 1
- **Issue:** Plan text action listed ~14 columns for messages and omitted two
  columns that DB-SCHEMA.md v0.2 §4 defines as NOT NULL:
  - `body_length INT NOT NULL` — physically stored length, paired with the
    `messages_body_length_check` CHECK (`body_length = CHAR_LENGTH(body)`).
  - `ssr_probability_at_send DECIMAL(4,3) NOT NULL` — snapshot of the inbox's
    `ssr_probability` at send time (audit evidence for F2).
- **Fix:** Added both columns per DB-SCHEMA v0.2. `body_length` uses Phinx
  `integer` with `signed=true`. `ssr_probability_at_send` uses Phinx `decimal`
  with precision=4 scale=3 (identical to inboxes.ssr_probability).
- **Files modified:** `config/Migrations/20260422120004_CreateMessages.php`
- **Commit:** `dff4cbf`

### 2. [Rule 3 — Authoritative-source conflict] messages.ssr_seed is NOT NULL (not nullable)

- **Found during:** Task 1
- **Issue:** Plan text and the plan's `<acceptance_criteria>` both described
  `ssr_seed VARCHAR(64) NULL` (nullable). DB-SCHEMA.md v0.2 §4 explicitly
  declares it `VARCHAR(64) NOT NULL`. The rationale in the discovery design is
  that `ssr_seed` is **always** computed at send (Phase 3 SsrService does it
  synchronously before the INSERT), so there is never a valid state where a
  row exists without a seed. NULL would undermine the auditability invariant.
- **Fix:** Declared `ssr_seed VARCHAR(64) NOT NULL` per DB-SCHEMA.
- **Consequence:** Phase 3 MSG-03 MUST compute the seed before INSERT; no
  placeholder row creation is possible. The plan text's `ssr_seed_length_check`
  CHECK (optional "only add if DB-SCHEMA has it") was NOT added, because
  DB-SCHEMA v0.2 defines no such CHECK. Length = 64 is enforced only by the
  VARCHAR(64) limit + app-layer sha256-hex input.
- **Files modified:** `config/Migrations/20260422120004_CreateMessages.php`
- **Commit:** `dff4cbf`

### 3. [Rule 3 — Authoritative-source conflict] messages has no `updated_at`

- **Found during:** Task 1
- **Issue:** Plan text listed `updated_at TIMESTAMP(6) NOT NULL DEFAULT
  CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)` on messages. DB-SCHEMA.md
  v0.2 §4 defines **only** `created_at` on messages. Messages are effectively
  immutable post-send; lifecycle state is encoded via `opened_at` + `deleted_at`
  + `deleted_reason` without needing an `updated_at` bump.
- **Fix:** Omitted `updated_at` per DB-SCHEMA.
- **Consequence:** CakePHP `Timestamp` Behavior on MessagesTable (Plan 01-03)
  must NOT apply the default `modified => updated_at` mapping — only
  `created_at`. Behavior config needs `fields => ['created_at' => 'new']`
  with no `modified` field.
- **Files modified:** `config/Migrations/20260422120004_CreateMessages.php`
- **Commit:** `dff4cbf`

### 4. [Rule 3 — Authoritative-source conflict] no `messages_deleted_reason_check` CHECK

- **Found during:** Task 1
- **Issue:** Plan text `<action>` explicitly listed a CHECK constraint
  `messages_deleted_reason_check` enforcing the allowed-value list
  (`'user'|'report_actioned'|'account_removed'`) on the VARCHAR(64) column.
  DB-SCHEMA.md v0.2 §4 defines **no** such CHECK. CONTEXT `<specifics>` clarifies
  that these values are "ドキュメントで許容値を明示" (documented, enforced at
  app layer) — the VARCHAR(64) was chosen specifically to avoid ENUM rigidity
  for a column that may expand.
- **Fix:** No CHECK added on `deleted_reason`; documented the allowed-value list
  in the column docblock comment. App-layer validation in Phase 4 MOD-03 will
  enforce the domain.
- **Consequence:** Invalid `deleted_reason` values are technically insertable
  at DB level. This is an explicit project decision (extensibility > strictness
  for this column).
- **Files modified:** `config/Migrations/20260422120004_CreateMessages.php`
- **Commit:** `dff4cbf`

### 5. [Rule 3 — Authoritative-source conflict] messages.body type is TEXT (Phinx `text`)

- **Found during:** Task 1
- **Issue:** Plan text hinted that body "may be TEXT vs MEDIUMTEXT vs VARCHAR,
  verify DB-SCHEMA". DB-SCHEMA.md v0.2 §4 specifies TEXT, constrained to
  `BETWEEN 1 AND 2000` chars by `messages_body_size` CHECK.
- **Fix:** Used Phinx `text` type (→ MySQL TEXT, 65,535 bytes; >> 2000 chars
  even for 3-byte UTF-8 characters). The CHECK is the effective length ceiling.
- **Files modified:** `config/Migrations/20260422120004_CreateMessages.php`
- **Commit:** `dff4cbf`

### 6. [Rule 3 — Authoritative-source conflict] blocks composite UNIQUE name is `uk_blocks_pair`

- **Found during:** Task 2
- **Issue:** Plan text (and the plan's `<action>` + `<verify>` regex)
  named the composite UNIQUE `uk_blocks_blocker_blocked`. DB-SCHEMA.md v0.2 §5
  names it `uk_blocks_pair` (shorter, conveys "pair" semantic directly).
- **Fix:** Used DB-SCHEMA name `uk_blocks_pair` verbatim.
- **Consequence:** The plan's `<verify>` automated regex `grep -q
  'uk_blocks_blocker_blocked'` would fail as written. The unique constraint
  is present and functionally identical, just under the DB-SCHEMA name. Task 4
  INFORMATION_SCHEMA verification independently confirmed the name.
- **Files modified:** `config/Migrations/20260422120005_CreateBlocks.php`
- **Commit:** `3d8662a`

### 7. [Rule 3 — Authoritative-source conflict] blocks CHECK name is `blocks_no_self` (no `_check` suffix)

- **Found during:** Task 2
- **Issue:** Plan text expected `blocks_no_self_check`. DB-SCHEMA.md v0.2 §5
  names it `blocks_no_self` (no `_check` suffix). Same pattern as Wave 2
  deviation #5 on `inboxes_probability_range` / `inboxes_slug_format`.
- **Fix:** Used DB-SCHEMA name verbatim.
- **Files modified:** `config/Migrations/20260422120005_CreateBlocks.php`
- **Commit:** `3d8662a`

### 8. [Rule 3 — Authoritative-source conflict] blocks/reports have no `updated_at`

- **Found during:** Tasks 2 + 3
- **Issue:** Plan text listed `updated_at TIMESTAMP(6)` on both blocks and
  reports. DB-SCHEMA.md v0.2 §5 and §6 define only `created_at`. blocks are
  create-or-delete (no mutation); reports use `status` + `reviewed_at` +
  `reviewed_by_admin` to encode the audit trail without needing a generic
  `updated_at`.
- **Fix:** Omitted `updated_at` on both tables per DB-SCHEMA.
- **Consequence:** BlocksTable and ReportsTable (Plan 01-03) must skip the
  default Timestamp Behavior `modified` mapping.
- **Files modified:** `config/Migrations/20260422120005_CreateBlocks.php`,
  `config/Migrations/20260422120006_CreateReports.php`
- **Commits:** `3d8662a`, `2238c7d`

### 9. [Rule 3 — Authoritative-source conflict] reports.status ENUM is 4-value, not 3

- **Found during:** Task 3
- **Issue:** Plan text listed `status` ENUM as `('pending','actioned','dismissed')`
  (3 values, default `pending`). DB-SCHEMA.md v0.2 §6 declares 4 values:
  `('pending','reviewed','actioned','dismissed')`. The `reviewed` intermediate
  state distinguishes "admin has looked but not yet decided" from "admin has
  acted/dismissed".
- **Fix:** Used 4-value ENUM per DB-SCHEMA.
- **Consequence:** Phase 4 moderation UI must handle the intermediate
  `reviewed` status in the state-machine UX.
- **Files modified:** `config/Migrations/20260422120006_CreateReports.php`
- **Commit:** `2238c7d`

### 10. [Rule 3 — Authoritative-source conflict] reports has `detail` + `reviewed_at` + `reviewed_by_admin` + `resolution_note`

- **Found during:** Task 3
- **Issue:** Plan text listed only `message_id`, `reporter_user_id`, `reason`,
  `status`, `created_at`, `updated_at` on reports. DB-SCHEMA.md v0.2 §6 adds
  four more moderation-workflow columns:
  - `detail TEXT NULL` — reporter-provided free-text explanation
  - `reviewed_at DATETIME(6) NULL` — when admin reviewed
  - `reviewed_by_admin VARCHAR(128) NULL` — admin identifier
  - `resolution_note TEXT NULL` — internal admin note on resolution
- **Fix:** Added all four columns per DB-SCHEMA. Types match verbatim.
- **Files modified:** `config/Migrations/20260422120006_CreateReports.php`
- **Commit:** `2238c7d`

### 11. [Rule 3 — Authoritative-source conflict] reports status index name is `idx_reports_status`

- **Found during:** Task 3
- **Issue:** Plan text named the composite status index
  `idx_reports_status_created`. DB-SCHEMA.md v0.2 §6 names it `idx_reports_status`
  (shorter). Columns are identical: (status, created_at).
- **Fix:** Used DB-SCHEMA name verbatim.
- **Files modified:** `config/Migrations/20260422120006_CreateReports.php`
- **Commit:** `2238c7d`

### 12. [Rule 3 — Missing critical configuration] `config/app_local.php` was absent — blocked `bin/cake migrations migrate`

- **Found during:** Task 4 (first migrate attempt)
- **Issue:** Initial `bin/cake migrations migrate` failed with
  `SQLSTATE[HY000] [2002] No such file or directory` + `Undefined array key
  "database"`. Root cause: CakePHP 4 reads `DATABASE_URL` from
  `config/app_local.php`'s `Datasources` section, but that file did not exist
  in the project. Wave 1 Task 4 activated the dotenv loader and populated
  `config/.env` with a valid `DATABASE_URL`, but never copied
  `config/app_local.example.php` → `config/app_local.php`. Result: `env()`
  resolved `DATABASE_URL` in PHP but no config path consumed it.
- **Fix:** Created `config/app_local.php` with minimal Datasources shim
  (`'url' => env('DATABASE_URL', null)` for `default`, same for `test` via
  `DATABASE_TEST_URL`) + `Security.salt` passthrough + `EmailTransport` stub.
  File is gitignored per CakePHP convention (line 3 of `.gitignore`:
  `/config/app_local.php`) so it is NOT committed. Re-running `bin/cake
  migrations migrate` succeeded immediately.
- **Files modified:** `config/app_local.php` (new, local-only)
- **Commit:** none for `app_local.php` (gitignored). The `.gitignore` +3
  lines for `schema-dump-*.lock` was committed as part of Task 4
  (`4eb0704`).
- **Wave 1 follow-up:** Recommend Plan 01-04 (or a future infra plan) update
  Wave 1's documentation to either (a) copy `app_local.example.php` → `.php`
  as part of environment bootstrap, or (b) document the required manual step.
  For now, this deviation entry is the record.

### 13. [Rule 3 — Generated artifact tracking] `config/Migrations/schema-dump-default.lock` now gitignored

- **Found during:** Task 4 (post-migrate git status)
- **Issue:** `bin/cake migrations migrate` writes
  `config/Migrations/schema-dump-default.lock` as a local schema snapshot
  cache (used by `bin/cake bake migration` for DIFF detection). The file is
  not source code and is regenerated on every migrate run; it should be
  gitignored.
- **Fix:** Added `/config/Migrations/schema-dump-*.lock` pattern to `.gitignore`.
- **Files modified:** `.gitignore` (+3 lines including comment)
- **Commit:** `4eb0704`

### No other deviations

Tasks 1-3 otherwise matched plan text exactly. Task 4's migrate/verify/rollback
sequence executed step-by-step as written in the plan's `<action>`.

## Authentication gates encountered

None. Work was entirely filesystem (writing PHP files) + localhost MySQL
introspection; no OAuth, no 2FA, no CLI login prompts.

## Requirement completion

- **INFRA-04** — Phinx migrations for all 6 tables written + applied end-to-end.
  Status: **COMPLETE**.

(INFRA-02, INFRA-03, INFRA-05, INFRA-07 were closed in Wave 1; this plan
touches only INFRA-04.)

## Handoff note to Plan 01-03 (Wave 4 — Table class bake)

- All 6 domain tables exist in the `tamabox` MySQL DB with the DDL defined in
  DB-SCHEMA.md v0.2 §1-§6. `bin/cake bake model <Name>` will introspect them.
- Tables + field snapshot for bake expectations:
  - `users` — id (uuid), display_name, created_at, updated_at, deleted_at
  - `user_identities` — FK to users, ENUM provider, token cols (TEXT NULL),
    is_primary boolean
  - `inboxes` — FK to users, slug, ssr_probability DECIMAL(4,3),
    is_accepting boolean, welcome_message text
  - `messages` — FK to inboxes + users (RESTRICT!), 14 domain cols,
    **NO `updated_at`** — Table class Timestamp behavior must skip `modified`
  - `blocks` — dual FK to users, **NO `updated_at`**
  - `reports` — FK to messages + users (reporter nullable for SET NULL),
    **NO `updated_at`**, 4-value status ENUM
- Wave 4 BakeModel commands must NOT regenerate `created_at`/`updated_at`
  auto-mapping for messages/blocks/reports — override the generated
  Timestamp behavior config to use `['created_at' => 'new']` only.
- The `config/app_local.php` file created as deviation #12 is required for
  any future `bin/cake` invocation. If you wipe local state, recreate it from
  `config/app_local.example.php` with the two Datasource `url` lines pointed
  at env().
- `config/Migrations/schema-dump-default.lock` now exists (not tracked). Phinx
  will update it automatically on any future migrate/bake-migration call.
- MySQL state post-plan: all 6 tables present and empty (0 rows). DB ready
  for Plan 01-03 bake + any Phase 2 fixture data.

## Known Stubs

- `messages.is_ssr`, `messages.ssr_probability_at_send`, `messages.ssr_seed` —
  all columns exist but no INSERT path populates them in Phase 1. Phase 3
  MSG-02 computes `is_ssr` + `ssr_probability_at_send` at send time; MSG-03
  computes `ssr_seed = sha256(server_secret || id || created_at)`. The
  NOT NULL constraint on all three means Phase 3 MUST compute before INSERT;
  the schema will reject any partial send attempt.
- `messages.sender_handle_snapshot`, `sender_avatar_url_snapshot`,
  `sender_profile_url_snapshot` — NOT NULL snapshot columns populated by the
  MSG-04 send-path in Phase 3 from the authenticated sender's
  `user_identities` cache.
- `user_identities.access_token_enc`, `refresh_token_enc`,
  `token_expires_at`, `last_synced_at` — reserved for Phase 2 AUTH-07
  (OAuth token lifecycle). Nullable, so schema does not force population.
- `reports.reviewed_at`, `reviewed_by_admin`, `resolution_note` — populated by
  Phase 4 moderation UI. Nullable, so status = `pending` rows have NULLs
  by design.

## Threat Flags

No new threat surface beyond the plan's `<threat_model>`. Mitigations verified
at schema level:

- **T-01-09** (sender hard-delete 逃げ得) — `fk_messages_sender ON DELETE
  RESTRICT` confirmed via REFERENTIAL_CONSTRAINTS introspection. DB now
  refuses `DELETE FROM users WHERE id = <sender-with-messages>`.
- **T-01-10** (MySQL < 8.0.16 silently drops CHECK) — Smoke test Step 10
  (`INSERT ... display_name = ''`) triggered MySQL error 3819
  "`users_display_name_check` is violated", confirming CHECK is enforced,
  not advisory.
- **T-01-11** (self-block bypass) — `blocks_no_self` CHECK present
  (`blocker_user_id <> blocked_user_id`), verified via CHECK_CONSTRAINTS
  introspection.
- **T-01-12** (reporter-deletion evidence loss) — `fk_reports_reporter ON
  DELETE SET NULL` confirmed; reporter_user_id is NULLABLE per Pattern 2
  requirement.
- **T-01-13** (ssr_seed absent) — Column exists as VARCHAR(64) NOT NULL;
  Phase 3 populates (documented in deviation #2).
- **T-01-14** (migration against wrong DB) — accepted per plan; no new surface.

## Self-Check

**Commits (verified on `main`):**
- FOUND: `dff4cbf` (Task 1 — CreateMessages)
- FOUND: `3d8662a` (Task 2 — CreateBlocks)
- FOUND: `2238c7d` (Task 3 — CreateReports)
- FOUND: `4eb0704` (Task 4 — migrate + verify + .gitignore)

**Files:**
- FOUND: `config/Migrations/20260422120004_CreateMessages.php` (216 lines)
- FOUND: `config/Migrations/20260422120005_CreateBlocks.php` (115 lines)
- FOUND: `config/Migrations/20260422120006_CreateReports.php` (157 lines)
- FOUND: `config/app_local.php` (58 lines, gitignored)
- FOUND: `.gitignore` updated with `/config/Migrations/schema-dump-*.lock`

**Verification:**
- FOUND: `php -l` passes on all 3 new migration files
- FOUND: `bin/cake migrations migrate` exits 0 (after Rule 3 app_local.php fix)
- FOUND: `bin/cake migrations status` shows 6/6 `up`
- FOUND: 6 tables in tamabox DB with utf8mb4_0900_ai_ci + InnoDB
- FOUND: 6 CHAR(36) `id` columns
- FOUND: 8 FKs with correct DELETE_RULE (RESTRICT on sender, SET NULL on reporter)
- FOUND: 6 CHECK constraints enforced (MySQL 3819 smoke test)
- FOUND: `bin/cake migrations rollback --target=0` exits 0; all 6 tables dropped
- FOUND: `bin/cake migrations migrate` (re-apply) exits 0; schema restored
- FOUND: 0/6 new migration files use `change()` method
- FOUND: 3/3 new migration files have `public $autoId = false`
- FOUND: 3/3 new migration files use `utf8mb4_0900_ai_ci` collation

**Introspection logs on disk:** `/tmp/migrate.log`, `/tmp/migrate_status.log`,
`/tmp/tables.log`, `/tmp/pk_check.log`, `/tmp/fks.log`, `/tmp/checks.log`,
`/tmp/indexes.log`, `/tmp/rollback.log`, `/tmp/after_rollback.log`,
`/tmp/remigrate.log`, `/tmp/check_test.log`.

## Self-Check: PASSED
