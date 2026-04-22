---
phase: 01-foundation-schema
verified: 2026-04-22T23:45:00Z
status: passed
score: 5/5 must-haves verified
overrides_applied: 0
requirements_shipped:
  - INFRA-02
  - INFRA-03
  - INFRA-04
  - INFRA-05
  - INFRA-07
verdict: phase_1_complete_phase_2_unblocked
---

# Phase 1: Foundation & Schema — Verification Report

**Phase Goal:** ロリポップ共有鯖で安全に CakePHP 4.5 を本番運用するための土台と、v1 で必要な全テーブル(users / user_identities / inboxes / messages / reports / blocks)を `bin/cake migrations migrate` で適用可能な状態にする。
**Verified:** 2026-04-22T23:45:00Z
**Status:** PASSED
**Re-verification:** No — initial verification after Wave 4 completion

---

## Goal Achievement — Observable Truths (ROADMAP Phase 1 Success Criteria)

| # | Truth (from ROADMAP) | Status | Evidence |
|---|----------------------|--------|----------|
| 1 | `composer.json` PHP 要件が `^8.0` に整合し、PHP 8.0+ 環境で `composer install` が通る | PASS | `composer.json` L8: `"php": "^8.0"`. `php --version` → 8.3.6. `composer validate` → `./composer.json is valid`. `composer install --dry-run` → `Nothing to install, update or remove`. `composer.lock` tracked in git. |
| 2 | `.env` ローダが有効化され、`config/.env` から秘匿値が CakePHP `env()` 経由で読める(コメントアウトが解除されている) | PASS | `config/bootstrap.php` L63-69: `if (!env('APP_NAME')...) { $dotenv = new \josegonzalez\Dotenv\Loader(...) }` — uncommented. Runtime test: `Security::getSalt()` → 64-char hex (`85a891bd5b13...`, NOT placeholder). `Configure::read('Security.serverSecret')` → 64-char hex (`86ba5791c351...`, NOT placeholder). `env('DATABASE_URL')` → `mysql://tamabox:...`. `config/.env` exists (perm 600). `git check-ignore` confirms `.gitignore:4:/config/.env` excludes it from VCS. `.env.example` lists SECURITY_SALT / SERVER_SECRET / DATABASE_URL / DATABASE_TEST_URL. |
| 3 | `.htaccess` の httpoxy ブロック(`RequestHeader unset Proxy`)が有効化されている | PASS | `.htaccess` L3-5: `<IfModule mod_headers.c>` → `    RequestHeader unset Proxy` → `</IfModule>` — no leading `#`. Lolipop mod_rewrite block at L7-12 intact. |
| 4 | `bin/cake migrations migrate` を実行すると v1 スキーマが MySQL 8.0 上に UUID CHAR(36) PK + `utf8mb4_0900_ai_ci` 付きで作成される | PASS | 6 migration files in FK-dependency order (120001-120006). `bin/cake migrations status` → all 6 `up`. MySQL 8.0.45 (≥ 8.0.16 for CHECK enforcement). 6 domain tables + phinxlog, all InnoDB; domain tables all `utf8mb4_0900_ai_ci`. All 6 `id` cols are `char(36) NOT NULL`. 8 FKs with DB-SCHEMA.md v0.2 cascade rules. 6 CHECK constraints enforced (smoke test: empty display_name INSERT → MySQL error 3819). |
| 5 | 上記スキーマに対応する `*Table` クラスがすべて明示的に `src/Model/Table/` 配下に存在し、`allowFallbackClass(false)` 下でも `TableLocator` から解決できる | PASS | 6 Table files + 6 Entity files present. `src/Application.php:54` still sets `allowFallbackClass(false)`. `vendor/bin/phpunit --filter LocatorSmokeTest` → `OK (1 test, 6 assertions)`. All 6 aliases resolve to concrete `App\Model\Table\<Name>Table` classes. |

**Score: 5/5 success criteria verified.**

---

## Required Artifacts

### composer.json (Criterion 1)

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `composer.json` | `require.php = ^8.0` | PASS | Line 8 exact match |
| `composer.lock` | tracked in git, pinned | PASS | `git ls-files` tracks; 228k lines; `composer install --dry-run` says nothing to install |
| `vendor/autoload.php` | present | PASS | generated, gitignored (normal) |

### .env loader (Criterion 2)

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `config/bootstrap.php` | Dotenv Loader block uncommented | PASS | L63-69 active, no leading `//` |
| `config/.env.example` | SECURITY_SALT / SERVER_SECRET / DATABASE_URL / DATABASE_TEST_URL keys | PASS | L21, L25, L37, L38 all present with `__PLACEHOLDER__`/active DB URLs |
| `config/.env` | exists, 600 perm, gitignored | PASS | `-rw-------`, `.gitignore:4:/config/.env` matches |
| `config/app.php` | `'serverSecret' => env('SERVER_SECRET')` in Security array | PASS | wires env() chain to Configure |

### .htaccess (Criterion 3)

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `/.htaccess` | `RequestHeader unset Proxy` uncommented inside `<IfModule mod_headers.c>` | PASS | L3-5, no `#` prefix |

### Migrations (Criterion 4)

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `config/Migrations/20260422120001_CreateUsers.php` | exists, valid Phinx | PASS | 85 lines, `$autoId = false`, up()/down(), utf8mb4_0900_ai_ci, CHAR(36) PK, CHECK |
| `config/Migrations/20260422120002_CreateUserIdentities.php` | exists, FK CASCADE | PASS | 150 lines; fk_user_identities_user CASCADE; provider ENUM bluesky/x; uk_user_identities_provider_account + uk_user_identities_user |
| `config/Migrations/20260422120003_CreateInboxes.php` | exists, 2 CHECKs | PASS | 132 lines; ssr_probability DECIMAL(4,3) DEFAULT 0.100; fk_inboxes_user CASCADE; CHECK probability_range + slug_format |
| `config/Migrations/20260422120004_CreateMessages.php` | exists, RESTRICT on sender | PASS | 216 lines; fk_messages_inbox CASCADE + fk_messages_sender RESTRICT; body_length + ssr_probability_at_send + ssr_seed NOT NULL; no `updated_at` column per DB-SCHEMA v0.2 |
| `config/Migrations/20260422120005_CreateBlocks.php` | exists, no-self CHECK | PASS | 115 lines; dual FK CASCADE; uk_blocks_pair; blocks_no_self CHECK (`blocker_user_id <> blocked_user_id`) |
| `config/Migrations/20260422120006_CreateReports.php` | exists, SET NULL on reporter | PASS | 157 lines; fk_reports_message CASCADE + fk_reports_reporter SET NULL; 4-value status ENUM |

### Table + Entity Classes (Criterion 5)

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `src/Model/Table/UsersTable.php` | explicit class, Timestamp w/ updated_at | PASS | 109 lines; 6 assocs; `'created_at' => 'new'`, `'updated_at' => 'always'` |
| `src/Model/Table/UserIdentitiesTable.php` | explicit class | PASS | 128 lines; belongsTo Users |
| `src/Model/Table/InboxesTable.php` | explicit class | PASS | 104 lines; belongsTo Users; hasMany Messages |
| `src/Model/Table/MessagesTable.php` | no `modified` mapping (no updated_at col) | PASS | 151 lines; Timestamp only maps `created_at => new`; comment L45 references DB-SCHEMA §4 |
| `src/Model/Table/BlocksTable.php` | BlockerUsers + BlockedUsers aliases, no `modified` | PASS | 93 lines; alias rename (bake bug fix); L44 comment |
| `src/Model/Table/ReportsTable.php` | ReporterUsers alias w/ joinType=LEFT, no `modified` | PASS | 113 lines; LEFT join matches nullable reporter_user_id per SET NULL FK |
| `src/Model/Entity/*.php` (x6) | all present, `@property string` on UUIDs | PASS | User 39L / UserIdentity 55L / Inbox 45L / Message 63L / Block 37L / Report 49L |
| `src/Application.php:54` | `allowFallbackClass(false)` maintained | PASS | grep matched; unchanged from pre-Phase-1 state |
| `tests/TestCase/Model/LocatorSmokeTest.php` | passes under phpunit | PASS | 1 test / 6 assertions / 0.015s; loops 6 aliases, asserts `instanceof` concrete class |

---

## Key Link Verification (Wiring)

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `.env` secrets | `Configure`/`Security` | Dotenv Loader → `putenv/toEnv/toServer` → `env()` | WIRED | Runtime test emits 64-char hex for both salt + serverSecret from real `.env` |
| `DATABASE_URL` env | CakePHP `Datasources.default` | `config/app_local.php` (gitignored, per Rule-3 fix in 01-02b #12) + `env()` resolver | WIRED | `bin/cake migrations status` connects to DB and returns 6 rows `up` |
| Migration files | live MySQL schema | `bin/cake migrations migrate` → Phinx → PDO | WIRED | INFORMATION_SCHEMA shows all 6 tables + 8 FKs + 6 CHECK constraints |
| CHECK constraints | live enforcement | MySQL 8.0.45 ≥ 8.0.16 | WIRED | Smoke test: `INSERT ... display_name=''` → ERROR 3819 (live rejection) |
| TableLocator alias | concrete Table subclass | `TableRegistry::getTableLocator()->get(alias)` | WIRED | LocatorSmokeTest passes with `allowFallbackClass(false)` policy |

---

## Data-Flow Trace (Level 4)

Phase 1 is schema + infra only — no data-rendering artifacts. N/A per verifier guidance ("skip for documentation-only or config-only phases"). Data flow in/out of the schema is verified by the CHECK-constraint smoke test (insert → reject) and the `bin/cake migrations migrate` round-trip (DDL applied → introspection sees it).

---

## Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|----------|---------|--------|--------|
| composer lockfile consistent | `composer install --dry-run --no-interaction` | "Nothing to install, update or remove"; exit 0 | PASS |
| composer.json valid | `composer validate --no-check-publish` | "./composer.json is valid" | PASS |
| composer.lock tracked | `git ls-files --error-unmatch composer.lock` | `composer.lock`; exit 0 | PASS |
| bootstrap + dotenv chain resolves secrets to non-placeholder values | `php -r` loader → `Security::getSalt()` + `Configure::read('Security.serverSecret')` | both 64-char hex; placeholder check `SECURITY_SALT_IS_PLACEHOLDER=no` / `SERVER_SECRET_IS_PLACEHOLDER=no` | PASS |
| migrations status shows all 6 up | `bin/cake migrations status` | 6/6 `up` | PASS |
| MySQL CHECK constraint is enforced (not silently dropped) | `INSERT INTO users (id, display_name, ...) VALUES (UUID(), '', NOW(6), NOW(6))` | `ERROR 3819 (HY000): Check constraint 'users_display_name_check' is violated.` | PASS |
| LocatorSmokeTest passes | `vendor/bin/phpunit --filter LocatorSmokeTest` | `OK (1 test, 6 assertions)`; exit 0 | PASS |
| Full test suite green | `vendor/bin/phpunit` | `Tests: 17, Assertions: 29, Incomplete: 6`; 0 errors, 0 failures; exit 0 | PASS |
| PHPStan level 8 clean | `composer phpstan` | `[OK] No errors`; exit 0 | PASS |
| phpcs clean | `composer phpcs` | `35 / 35 (100%)`; exit 0 | PASS |
| composer.json PHP requirement | `grep '"php"' composer.json` | `"php": "^8.0"` | PASS |
| httpoxy active | `.htaccess` L3-5 inspection | `RequestHeader unset Proxy` active (no `#`), inside `<IfModule mod_headers.c>` | PASS |

All 12 spot-checks PASS.

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| INFRA-02 | 01-01 | `.env` loader active, secrets via env() | SHIPPED | bootstrap.php L63-69 active; runtime env() resolves SECURITY_SALT + SERVER_SECRET + DATABASE_URL |
| INFRA-03 | 01-01 | composer.json PHP `^8.0` | SHIPPED | composer.json L8; `composer install` OK on PHP 8.3.6 |
| INFRA-04 | 01-02a + 01-02b | 6-table schema via `bin/cake migrations migrate` | SHIPPED | 6/6 migrations up; INFORMATION_SCHEMA introspection confirms DDL; CHECK constraints enforced live |
| INFRA-05 | 01-01 | `.htaccess` httpoxy mitigation active | SHIPPED | .htaccess L3-5 uncommented |
| INFRA-07 | 01-03 | Explicit Table classes + `allowFallbackClass(false)` resolution | SHIPPED | 6 Table classes in src/Model/Table/; LocatorSmokeTest passes |

**No orphaned requirements.** All 5 REQ-IDs mapped to Phase 1 in REQUIREMENTS.md §Traceability table are green in code, with matching plan sources in the summary frontmatter.

---

## Anti-Patterns Scanned

### TODO / FIXME / placeholder comments in Phase 1 source files

Scanned: `composer.json`, `composer.lock`, `config/bootstrap.php`, `config/app.php`, `config/.env.example`, `.htaccess`, all 6 migration files, all 6 Table classes, all 6 Entity classes, all 6 Fixtures, all 6 TableTest stubs, LocatorSmokeTest.

| Category | Count | Severity | Notes |
|----------|-------|----------|-------|
| `TODO` / `FIXME` / `XXX` / `HACK` markers | 0 | — | none found in Phase 1 files |
| bake `markTestIncomplete()` stubs | 6 | Info | Expected post-bake; 6 TableTest files, one per table; documented in 01-03-SUMMARY.md "Known Stubs"; Phase 2+ will fill. These are not blockers — they live in test bodies with explicit marker, not in production source. |
| `ssr_seed` / `is_ssr` / `snapshot_*` columns NOT NULL but unpopulated | — | Info | By design — Phase 1 scope is "schema creates column", Phase 3 MSG-02/03/04 populates. Schema enforces — any Phase 3 code that tries to INSERT without these values will be rejected by NOT NULL. Documented in 01-02b-SUMMARY.md "Known Stubs". |
| `access_token_enc` / `refresh_token_enc` TEXT NULL | — | Info | Reserved for Phase 2 AUTH-07. Nullable so no false-failure in Phase 1 tests. |

### Runtime code smells

- `config/app_local.php` is created locally (Rule-3 fix, deviation #12 in 01-02b) but gitignored per CakePHP convention (`.gitignore` line 3: `/config/app_local.php`). This is a **latent landmine for fresh clones** — a new developer cloning the repo and running `bin/cake` will fail with "No such file or directory" until they copy `config/app_local.example.php` → `config/app_local.php`. The 01-02b SUMMARY explicitly flags this and recommends a follow-up in Plan 01-04 or a future infra plan to either (a) auto-copy in bootstrap or (b) document the manual step. **This does NOT block Phase 2 start on this VPS** (app_local.php exists here). It is a known documentation gap for onboarding, not a functional regression. Flagged here for tracking; no action required for Phase 1 closure.

### Scope of modified files is sound

No Phase 1 commit touches unrelated code. SUMMARY-declared files match what git has. No accidental changes to middleware, controllers, or unrelated config.

---

## Git Reconciliation

- `git log 3aa3443^..HEAD` → 25 commits since roadmap creation; of those, 20 are Phase 1 execution commits (5 context/research/plan + 5 docs/state + 16 wave commits = does not reconcile with context's "16 Phase 1 commits" but matches per-wave breakdown 6+4+5+5=20 when you include SUMMARY commits).
  - Verifier context text said "16 Phase 1 commits"; its own per-wave breakdown (6/4/5/5) sums to 20. The per-wave breakdown matches reality. The "16" appears to be a context-doc typo (likely excluded the 4 SUMMARY commits). Not a gap.
  - Wave 1 (`0afe20a`..`4237369`): 5 feat + 1 SUMMARY = **6** commits
  - Wave 2 (`025f8cb`..`abdb3b2`): 3 feat + 1 SUMMARY = **4** commits
  - Wave 3 (`dff4cbf`..`0490886`): 4 feat + 1 SUMMARY = **5** commits
  - Wave 4 (`276c5fb`..`09233b5`): 3 feat + 1 fix + 1 SUMMARY = **5** commits
- `git status` → `nothing to commit, working tree clean`. No stray files, no un-gitignored generated artifacts.
- 4 SUMMARY.md files present under `.planning/phases/01-foundation-schema/`.

---

## Gaps Found

**None.**

All 5 ROADMAP success criteria are observable in live state. All 5 phase-mapped REQ-IDs (INFRA-02, 03, 04, 05, 07) are SHIPPED with corroborating evidence. No FAIL, no PARTIAL. Full regression guard (LocatorSmokeTest) is committed and active for Phase 2+ CI.

---

## Notable Good Signals

1. **Rollback round-trip proven** (01-02b Task 4): `bin/cake migrations rollback --target=0` → `bin/cake migrations migrate` succeeds without modification. Reverse order (reports/blocks/messages/inboxes/user_identities/users) respects FK dependencies, meaning explicit `down()` methods are correct. This de-risks Phase 4 production deploys.
2. **CHECK enforcement live-tested** (MySQL error 3819) — not merely declared in migration DDL but observably rejected at the DB engine. Confirms MySQL 8.0.45 ≥ 8.0.16 gate (Pitfall 1) is cleared.
3. **DB-SCHEMA.md v0.2 is source of truth** — 13 plan-vs-DB-SCHEMA divergences all resolved in favor of DB-SCHEMA per D-10. Every deviation is documented in the 01-02a/01-02b SUMMARYs with commit reference. No silent "plan-text-wins" decisions.
4. **PHPStan level 8 clean on Entity/Table surface** without manual `@property` fixups — cakephp/bake 2.8 correctly emits `@property string $id` for UUID (RESEARCH Pitfall 6 proved moot for bake 2.8).
5. **Timestamp Behavior is column-aware** — messages/blocks/reports tables (no `updated_at` column) do NOT map `modified`, avoiding the Phase 2/3 INSERT-time runtime error that a blind Timestamp default would have introduced.
6. **LocatorSmokeTest as ongoing regression guard** — exactly the observable artifact ROADMAP criterion #5 asks for. It will catch any future bake-wipe or namespace-rename accident before it reaches production.
7. **Association aliases disambiguated** — BlockerUsers/BlockedUsers, SenderUsers, ReporterUsers (with LEFT join) — avoids the silent-overwrite bug where `belongsTo('Users', ...)` called twice leaves only the second association wired.
8. **`fk_messages_sender ON DELETE RESTRICT`** mitigates T-01-09 (sender hard-delete 逃げ得). Verified via REFERENTIAL_CONSTRAINTS introspection.
9. **`fk_reports_reporter ON DELETE SET NULL`** preserves moderation audit trail after reporter deletion. Verified via REFERENTIAL_CONSTRAINTS introspection + `ReporterUsers` association uses `joinType => LEFT`.

---

## Tracking Items (Non-Blocking)

These are not Phase 1 gaps but are flagged for downstream phase planners:

1. **`config/app_local.php` onboarding doc gap** — Fresh clones will fail `bin/cake migrations status` with "No such file or directory" until `app_local.example.php` is copied. 01-02b SUMMARY recommends a Plan 01-04-style follow-up or bootstrap auto-copy. Not blocking for Phase 2 on this VPS (file exists locally, gitignored). Consider a lightweight README/bootstrap-doc update.
2. **6 `markTestIncomplete()` stubs in TableTest files** — Normal post-bake; filled when Phase 2/3 controllers use the ORM with concrete validation assertions.
3. **`UUID` generation hook** — Phase 2 must add `Text::uuid()` in `initialize()` or a `beforeSave` hook for UsersTable + UserIdentitiesTable (CHAR(36) NOT NULL, no DB-side DEFAULT). Called out in 01-03-SUMMARY "Handoff to Phase 2" §1.
4. **AES-GCM encryption for token columns** — Phase 2 AUTH-07 must add EncryptedFieldBehavior or equivalent on `user_identities.access_token_enc` / `refresh_token_enc`. Currently TEXT NULL with no population path.
5. **Entity `$_accessible` hardening** — T-01-17 accepted for Phase 3 scope. When Phase 3 controllers accept user input, set `'id' => false`, `'user_id' => false`, etc.

---

## Overall Verdict

## VERIFICATION PASSED

**Phase 1: Foundation & Schema is complete. All 5 ROADMAP success criteria are verified in live state, all 5 phase-mapped requirements (INFRA-02, 03, 04, 05, 07) are SHIPPED, and Phase 2 (Bluesky OAuth & Identity) is unblocked.**

---

*Verified: 2026-04-22T23:45:00Z*
*Verifier: Claude (gsd-verifier)*
