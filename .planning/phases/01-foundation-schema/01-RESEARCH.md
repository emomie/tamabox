# Phase 1: Foundation & Schema — Research

**Researched:** 2026-04-22
**Domain:** CakePHP 4.5 infrastructure hygiene + Phinx-based migrations (MySQL 8.0)
**Confidence:** HIGH (all critical claims verified against upstream source code or official docs)

## Summary

Phase 1 is narrowly scoped: (1) harden the freshly-baked CakePHP 4.5 skeleton for Lolipop shared hosting, and (2) translate the already-finalized DB-SCHEMA.md v0.2 DDL into `cakephp/migrations 3.x` (Phinx 0.13) migration files + matching explicit Table classes. No domain logic, no OAuth, no SSR computation.

The research confirms that the 15 locked decisions in CONTEXT.md are implementable with the existing tooling, but surfaces four technical pitfalls the planner must address explicitly:

1. **Phinx 0.13 has NO native CHECK constraint API** — every `CHECK (...)` in DB-SCHEMA.md (`display_name` length, `ssr_probability` range, `slug` REGEXP, `body_length`, `blocks_no_self`) MUST be added via raw `$this->execute("ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)")` at the end of each migration's `up()`. This is also why each migration will need explicit `up()`/`down()` pair rather than the auto-reversible `change()` method (raw SQL is not auto-reversible).
2. **`DATETIME(6)` microsecond precision IS supported** via the undocumented-in-docs but present-in-source `['limit' => 6]` option on `datetime` / `timestamp` column types. `ON UPDATE CURRENT_TIMESTAMP(6)` for `updated_at` must be passed as the `update` column option on a `timestamp` type (MySQL adapter only; does not work on `datetime`).
3. **`josegonzalez/dotenv` is currently in `require-dev`** (composer.json:18) — enabling the `.env` loader means this dep is used at runtime in production. It must be moved to `require` before production deploy (or D-02 is only a dev-environment convenience and production uses Apache `SetEnv` / `.htaccess`). This is not spelled out in D-02; planner should flag.
4. **`bake model` auto-generates Entity + Table + fixture + test class**, but with `TableLocator::allowFallbackClass(false)` active, the HTTP entry point (`webroot/index.php`) will fatal if *any* of the 6 tables is queried without its Table class present — so the bake step must complete all 6 before the app goes live again.

**Primary recommendation:** Order Phase 1 tasks as (A) composer/config hygiene first → (B) 6 migrations in FK order → (C) `migrations migrate` end-to-end verification → (D) bake 6 model classes last. CHECK constraints go inside each migration's `up()` via `$this->execute()` immediately after `->create()`. `updated_at ON UPDATE CURRENT_TIMESTAMP(6)` requires using Phinx type `timestamp` (not `datetime`) with `limit:6, update:'CURRENT_TIMESTAMP(6)'`.

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| PHP version constraint declaration | Build (composer) | — | composer.json gates dependency resolution; production PHP is chosen at Lolipop control panel, separately |
| `.env` secret injection | Application Bootstrap | — | `config/bootstrap.php` runs before `Configure::load('app')`; must populate `$_ENV` before `env()` calls in app.php |
| httpoxy mitigation | Apache (.htaccess) | — | Must strip the `Proxy` request header BEFORE PHP sees `HTTP_PROXY`; pure web-server concern, cannot be done in PHP |
| Schema DDL | Database / Storage (MySQL 8.0) | Migration tooling (Phinx) | DDL is the contract; migrations are the mechanism to apply and version-track |
| Schema-to-ORM binding | Application (src/Model/Table) | — | Table classes live in PSR-4 autoload path; fallback-class disabled, so presence is load-bearing |
| Dev workflow scripts | Build (composer) | — | `composer phpcs/phpstan/test` is dev-time only, not wired into runtime |

## Standard Stack

### Core (already required by composer.json)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `cakephp/cakephp` | `^4.5.0` | Framework (keep current) | Locked in STACK.md, production compatible with PHP 8.0–8.4 |
| `cakephp/migrations` | `^3.7` (install resolves to `3.9.0`) | Phinx wrapper + bake integration | Only migrations plugin compatible with CakePHP 4.x; `4.x` requires CakePHP 5 |
| `robmorgan/phinx` | `^0.13.2` (transitive) | Migration engine | Pinned by cakephp/migrations 3.9.0; delivers the adapter layer |
| `cakephp/plugin-installer` | `^1.3` | Composer plugin installer | Already in; keep |
| `mobiledetect/mobiledetectlib` | `^3.74` | Mobile/tablet request detectors | Wired in bootstrap; CONCERNS flagged it as dead weight but leave alone in Phase 1 |

### Must promote from require-dev → require (D-02 dependency)
| Library | Version | Current Location | Why Move |
|---------|---------|-------------------|----------|
| `josegonzalez/dotenv` | `^4.0` (latest, 2023-05-29) [VERIFIED: packagist.org] | `require-dev` (composer.json:18) | Once `config/bootstrap.php` uncomments the loader, this is a runtime dependency on production. `composer install --no-dev` on Lolipop would otherwise fatal. |

### Recommended to add in this phase (D-13)
| Library | Version | Location | Purpose |
|---------|---------|----------|---------|
| `phpstan/phpstan` | `^2.1` [VERIFIED: packagist.org 2026-04-17] | `require-dev` | `phpstan.neon` already exists (level 8) but PHPStan is NOT yet installed. `composer phpstan` script can't run without it. [CITED: CONVENTIONS.md line 122] |
| `cakedc/cakephp-phpstan` | `^4.1` (requires phpstan `^2.1.26`) | `require-dev` | CakePHP 4-specific type resolution for Table/Helper/Behavior factories; without this, PHPStan level 8 will flood false-positives on CakePHP magic. |

### Not needed in this phase (already present)
- PHPUnit `^9.6` — present. Compatible with PHP 8.0–8.4. [VERIFIED: phpunit.de/supported-versions]
- cakephp/bake `^2.8` — present in require-dev, sufficient for Phase 1.
- cakephp/cakephp-codesniffer `^4.5` — present, wired via phpcs.xml.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `josegonzalez/dotenv` loader in bootstrap | Lolipop `.htaccess SetEnv` / control panel env | [CITED: CONCERNS.md:95-99] — Lolipop-native approach avoids shipping `.env` to prod at all. Decision D-02 chose the dotenv loader path, so this alternative is OUT but worth flagging to the planner: **dev .env works, prod should also configure SetEnv as defense-in-depth so that accidentally-missing .env doesn't fall through to empty secrets**. |
| Phinx `change()` auto-reversible method | Explicit `up()` / `down()` pair | Because CHECK constraints go through raw `execute()`, Phinx cannot auto-generate the reverse. **Must use `up()`/`down()` in all 6 migrations**. |
| `timestamp` PHP type for created_at | `datetime` type | `timestamp` in MySQL is limited to 2038-01-19 (32-bit int seconds); `datetime` has no year limit. For `created_at` we want 2038-proof → use `datetime`. But for `updated_at ON UPDATE CURRENT_TIMESTAMP(6)` auto-update, Phinx's `update` column option is only wired on `timestamp` type. Solution: **use `datetime` for `created_at`, `timestamp` for `updated_at`, accept the 2038 ceiling on updated_at** (SaaS product rewrite cycle is ~5 years, acceptable). Alternative: do the `ON UPDATE` via raw SQL too — cleaner consistency, slightly more code. |

**Installation (Phase 1 composer commands):**
```bash
# Bump PHP constraint (D-01) and move dotenv to runtime (D-02 implication)
# Edit composer.json manually, then:
composer update --lock

# Add dev tooling (D-13)
composer require --dev phpstan/phpstan:^2.1
composer require --dev cakedc/cakephp-phpstan:^4.1

# Commit composer.lock (D-04)
git add composer.json composer.lock
```

**Version verification performed:**
- `cakephp/migrations` `3.9.0` released 2023-09-22 [VERIFIED: `gh api repos/cakephp/migrations/releases/tags/3.9.0`]. Requires `php >=7.4.0`, `robmorgan/phinx ^0.13.2`, `cakephp/orm ^4.3.0`. `^3.7` in composer.json resolves to `3.9.0`.
- `josegonzalez/dotenv` `4.0.0` released 2023-05-29 [VERIFIED: packagist.org]. `php >=5.5.0` (compatible with PHP 8.x).
- `phpstan/phpstan` `2.1.50` latest as of 2026-04-17 [VERIFIED: web search]. `^2.1` constraint safe.
- `cakedc/cakephp-phpstan` `^4.1` supports CakePHP 4.x with phpstan `^2.1.26` [CITED: packagist.org/packages/cakedc/cakephp-phpstan].

## Architecture Patterns

### Phase 1 Data Flow (conceptual)

```
[Developer]
  │
  │ 1. edit composer.json (PHP ^8.0), edit bootstrap.php (uncomment .env), edit .htaccess (uncomment httpoxy)
  ▼
[composer update]
  │
  │ 2. writes composer.lock (D-04); resolves cakephp/migrations to 3.9.0
  ▼
[config/.env] ← seed with SERVER_SECRET (openssl rand -hex 32), SECURITY_SALT, DATABASE_URL
  │
  │ 3. loaded at bootstrap.php:63 (now uncommented) before Configure::load('app')
  ▼
[Configure::read('Datasources.default.url')]
  │
  │ 4. bin/cake CLI uses same DATABASE_URL
  ▼
[bin/cake bake migration CreateUsers]  ───(repeat × 6 in FK order)───▶  config/Migrations/YYYYMMDDHHMMSS_CreateUsers.php
  │
  │ 5. hand-edit each file: set $autoId=false, override id → 'uuid', add CHECK via ->execute()
  ▼
[bin/cake migrations migrate]
  │
  │ 6. runs in dependency order; creates 6 tables in MySQL 8.0
  ▼
[bin/cake bake model <Table>]  ───(× 6)───▶  src/Model/Table/UsersTable.php + src/Model/Entity/User.php + tests + fixtures
  │
  │ 7. TableLocator::allowFallbackClass(false) → every ORM query needs its class present
  ▼
[Application ready for Phase 2]
```

### Recommended Directory Changes
```
config/
├── .env                  # NEW (git-ignored) — seeded from .env.example
├── .env.example          # MODIFIED — add SERVER_SECRET, DATABASE_URL sample (already present but commented)
├── bootstrap.php         # MODIFIED — uncomment lines 63-69
└── Migrations/           # NEW DIR (currently absent)
    ├── 20260422120001_CreateUsers.php
    ├── 20260422120002_CreateUserIdentities.php
    ├── 20260422120003_CreateInboxes.php
    ├── 20260422120004_CreateMessages.php
    ├── 20260422120005_CreateBlocks.php
    └── 20260422120006_CreateReports.php

src/Model/
├── Table/                # NEW — 6 files
│   ├── UsersTable.php
│   ├── UserIdentitiesTable.php
│   ├── InboxesTable.php
│   ├── MessagesTable.php
│   ├── BlocksTable.php
│   └── ReportsTable.php
└── Entity/               # NEW — 6 files
    ├── User.php
    ├── UserIdentity.php
    ├── Inbox.php
    ├── Message.php
    ├── Block.php
    └── Report.php

.htaccess                 # MODIFIED — uncomment httpoxy block (lines 3-5)
composer.json             # MODIFIED — php: "^8.0", add scripts, move dotenv to require
composer.lock             # NEW — commit to repo
```

### Pattern 1: Phinx migration with UUID PK + CHECK constraint (CANONICAL PHASE 1 TEMPLATE)

**What:** Every Phase 1 migration follows this skeleton because the DB-SCHEMA.md DDL hits every "non-standard" Phinx path simultaneously (UUID PK, DATETIME(6), CHECK).

**When to use:** All 6 migrations in this phase.

**Example (`config/Migrations/20260422120001_CreateUsers.php`):**
```php
<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateUsers extends AbstractMigration
{
    /**
     * Disable auto-generated BIGINT id column so we can define CHAR(36) UUID PK.
     * This is a class-level toggle documented in Migrations\AbstractMigration:21-28.
     */
    public $autoId = false;

    /**
     * CHECK constraints are not supported by Phinx 0.13 natively, so the
     * migration is not auto-reversible — use up()/down() instead of change().
     */
    public function up(): void
    {
        $table = $this->table('users', [
            'collation' => 'utf8mb4_0900_ai_ci',
            'engine'    => 'InnoDB',
        ]);

        $table
            // UUID primary key (Phinx `uuid` type → MySQL CHAR(36))
            ->addColumn('id', 'uuid', ['null' => false])
            ->addPrimaryKey('id')

            ->addColumn('display_name', 'string', [
                'limit' => 64,
                'null'  => false,
            ])

            // DATETIME(6) — Phinx MySQL adapter honors `limit` as fractional precision.
            // Verified in robmorgan/phinx 0.13.4 src/Phinx/Db/Adapter/MysqlAdapter.php:961-964.
            ->addColumn('created_at', 'datetime', [
                'limit'   => 6,
                'null'    => false,
                'default' => \Phinx\Util\Literal::from('CURRENT_TIMESTAMP(6)'),
            ])

            // For `updated_at` we use `timestamp` because Phinx only wires the
            // `update` option on timestamp columns (gives ON UPDATE CURRENT_TIMESTAMP).
            // If the 2038 ceiling is a concern, use ->execute() raw SQL instead.
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
        // Naming convention: <table>_<field>_check to match DB-SCHEMA.md v0.2.
        $this->execute(<<<SQL
ALTER TABLE users
  ADD CONSTRAINT users_display_name_check
  CHECK (CHAR_LENGTH(display_name) BETWEEN 1 AND 64)
SQL);
    }

    public function down(): void
    {
        // DROP TABLE implicitly drops the CHECK constraint on MySQL 8.0.
        $this->table('users')->drop()->save();
    }
}
```

[VERIFIED: code lifted from Phinx 0.13.4 docs (`docs/en/migrations.rst`) + source (`src/Phinx/Db/Adapter/MysqlAdapter.php`), adapted to cakephp/migrations 3.9.0 `AbstractMigration` wrapper found at `src/AbstractMigration.php:13-49`.]

### Pattern 2: Foreign key with ON DELETE option

**When to use:** `user_identities`, `inboxes`, `messages`, `blocks`, `reports` — every dependent table.

```php
// inside up() after the column/index definitions:

$table->addForeignKey(
    'user_id',                       // column in THIS table
    'users',                          // referenced table
    'id',                             // referenced column
    [
        'delete'     => 'CASCADE',    // valid: 'CASCADE' | 'RESTRICT' | 'SET_NULL' | 'NO_ACTION'
        'update'     => 'NO_ACTION',
        'constraint' => 'fk_user_identities_user',  // match DB-SCHEMA.md names
    ]
);
```

[CITED: `/tmp/phinx_migrations.rst:1499-1516` from robmorgan/phinx 0.13.4 tag.]

**FK cascade direction per DB-SCHEMA.md v0.2:**

| Table | FK | Direction |
|-------|-----|-----------|
| user_identities.user_id → users.id | `CASCADE` | User delete wipes identity rows |
| inboxes.user_id → users.id | `CASCADE` | User delete wipes inbox |
| messages.inbox_id → inboxes.id | `CASCADE` | Inbox delete wipes messages |
| messages.sender_user_id → users.id | **`RESTRICT`** | Prevents sender-delete-while-messages-exist (逃げ得防止; snapshot columns preserve handle) |
| blocks.blocker_user_id → users.id | `CASCADE` | User delete wipes their block list |
| blocks.blocked_user_id → users.id | `CASCADE` | Blocked user delete wipes their entries |
| reports.message_id → messages.id | `CASCADE` | Message delete wipes reports |
| reports.reporter_user_id → users.id | **`SET_NULL`** | Reporter account removed, report retained for moderation |

For `SET_NULL` the column MUST be defined with `null => true` [CITED: phinx docs line 1515].

### Pattern 3: Composite UNIQUE key

```php
$table->addIndex(
    ['provider', 'provider_account_id'],
    [
        'unique' => true,
        'name'   => 'uk_user_identities_provider_account',
    ]
);
```

### Pattern 4: ENUM column (messages.sender_provider, reports.reason, reports.status, user_identities.provider)

```php
$table->addColumn('sender_provider', 'enum', [
    'values' => ['bluesky', 'x'],
    'null'   => false,
]);
```
[CITED: phinx docs line 1039-1043 + line 796-802.]

**Inconsistency to resolve:** DB-SCHEMA.md uses MySQL native `ENUM` for `user_identities.provider`, `messages.sender_provider`, `reports.reason`, `reports.status` — but CONTEXT.md `<specifics>` lines 106-108 specifies `messages.deleted_reason` as `VARCHAR(64)` (not ENUM) because *"MySQL ENUM は拡張時に ALTER 必要なので避ける"*. This reasoning applies equally to `reports.status`, `reports.reason`, and the `provider` enums — but the locked decisions in CONTEXT.md D-10 say "DB-SCHEMA.md を single source of truth とする". Interpretation: the ENUM columns stay as ENUM per DB-SCHEMA.md; only `deleted_reason` was explicitly re-decided as VARCHAR(64). Document the reasoning in migration comments. **Planner: confirm this interpretation with user during planning, or defer to DB-SCHEMA.md verbatim.**

### Pattern 5: Addressing DECIMAL(4,3) for ssr_probability

```php
$table->addColumn('ssr_probability', 'decimal', [
    'precision' => 4,
    'scale'     => 3,
    'default'   => 0.100,
    'null'      => false,
]);
// Then CHECK via raw SQL:
$this->execute(
    "ALTER TABLE inboxes ADD CONSTRAINT inboxes_probability_range "
  . "CHECK (ssr_probability >= 0 AND ssr_probability <= 1)"
);
```

### Anti-Patterns to Avoid

- **Using `change()` with raw `execute()`:** Phinx cannot auto-reverse raw SQL. If CHECK is in `change()`, rollback silently leaves the constraint. [CITED: phinx docs "Change Method" section]. **Always pair `up()` with `down()` when any migration line uses `execute()`.**

- **Relying on `timestamp` for all date columns:** MySQL `TIMESTAMP` has a 2038 range limit (32-bit seconds). `DATETIME` does not. The DB-SCHEMA.md only uses `DATETIME(6)` everywhere; so our migrations should too, EXCEPT where we need `ON UPDATE CURRENT_TIMESTAMP(6)` auto-update behavior. See alternative discussion in Standard Stack. Recommend consistency via raw `execute()` for the ON UPDATE clause if preferred — the planner should decide per-task.

- **Forgetting `TableLocator::allowFallbackClass(false)` implications:** Running `bin/cake migrations migrate` does NOT create Table classes. If the web app boots and queries an un-baked Table (via Phase 2+ code), the response is a fatal `MissingTableClassException`. **Plan 6 bake-model tasks immediately after migrations pass**, BEFORE any controller that uses the ORM is added. [VERIFIED: `src/Application.php:52-56` sets `allowFallbackClass(false)` explicitly.]

- **Baking migration then running `migrations migrate` with `DATABASE_URL` unset:** Until `.env` loading is enabled, `getenv('DATABASE_URL')` returns false → `env('DATABASE_URL', null)` → migrations silently fall back to `config/app_local.php` hardcoded credentials or error. **Order matters: bootstrap.php `.env` uncomment (D-02) must land BEFORE the first `migrations migrate` attempt.**

- **Using `['id' => false, 'primary_key' => ['id']]` via table options (classic Phinx pattern):** works but cakephp/migrations 3.x introduced `$autoId = false` + `->addPrimaryKey('id')` as the idiomatic way [VERIFIED: `Migrations\Table::addPrimaryKey()` in `src/Table.php:41-47`]. Use the newer form for consistency with bake output.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| `.env` → `$_ENV` parsing | Custom `file_get_contents` + `explode('=')` | `josegonzalez/dotenv` (already in composer.json) | Handles quoting, `${VAR}` nesting, multi-line, comments, export syntax. The exact stanza is already written in `config/bootstrap.php:63-69`; just uncomment. |
| UUID generation for new rows | `uniqid()` / custom | `Cake\Utility\Text::uuid()` in a Table `initialize()` behavior hook (Phase 2+) | [CITED: DB-SCHEMA.md CakePHP連携メモ]. Phase 1 does NOT need to implement this — the Table classes bake can be stubs, UUID behavior is added in Phase 2 when user-create logic lands. |
| Migration schema introspection for test DB | Re-reading .sql file | `Migrations\TestSuite\Migrator` (wired in `tests/bootstrap.php:65` already) | [VERIFIED: `tests/bootstrap.php` per STACK.md, ARCHITECTURE.md line 157]. Nothing to add in Phase 1. |
| `created_at` / `updated_at` auto-fill | Custom beforeSave hooks | CakePHP `Timestamp` Behavior via `$this->addBehavior('Timestamp', ['events' => ['Model.beforeSave' => ['created_at' => 'new', 'updated_at' => 'always']]])` | D-09 already commits to this. Phase 1 wires it into the baked Table classes (optional — bake may or may not add it; inspect and keep). |
| CHECK constraint validation at ORM level | Duplicating constraints in Table `validationDefault()` | Let MySQL enforce DB-side; add PHP-level validation in Phase 2-3 when controllers handle user input | Phase 1 is schema-only; validators live in Table classes that are baked but not filled. |
| Secret-value rotation mechanism | Version-keyed secret store | Skip entirely — D-05 locks in "single string, no rotation" for MVP | Phase 1 is explicit: no `ssr_secret_version` column. |

**Key insight:** Every "tempting to hand-roll" item in this phase already has a chosen CakePHP idiom. The value of research here is keeping the planner from reinventing them.

## Runtime State Inventory

> Phase 1 is greenfield: **zero deployed state on Lolipop yet** (STATE.md confirms "Phase 0/4 — 0%", no production deploy has happened). The repo is a fresh `composer create-project` checkout. However, the developer's local machine may have stale state from earlier manual experiments:

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | None — no production database exists yet. Developer local DB may have a `tamabox` schema from earlier `bin/cake migrations migrate` attempts; **verify clean state before Phase 1 run** (`DROP DATABASE tamabox; CREATE DATABASE tamabox;` if needed). | Ad-hoc cleanup step on developer box. |
| Live service config | None — no Lolipop deploy yet, no external services registered. | None. |
| OS-registered state | None — no cron, systemd, Task Scheduler entries reference tamabox. | None. |
| Secrets/env vars | `config/.env` does not yet exist on developer box; `.env.example` has template but no `SERVER_SECRET` entry yet (only SECURITY_SALT, APP_NAME, DATABASE_URL). **Phase 1 adds `SERVER_SECRET` to `.env.example` template (D-05) and instructs developer to `cp + openssl rand -hex 32`.** | Documentation + template update. |
| Build artifacts / installed packages | `vendor/` contains only `.gitkeep` [CITED: STATE.md + CONCERNS.md line 207]. No `composer.lock`. No cached autoload. No prior PHP extension installations to reconcile. | None — fresh install path. |

**Nothing found in category** (state explicitly): No production runtime state. Phase 1 runs entirely in "virgin soil".

## Common Pitfalls

### Pitfall 1: CHECK constraint invisible on older MySQL versions
**What goes wrong:** A CHECK constraint defined on MySQL 5.7 is parsed but silently ignored.
**Why it happens:** MySQL only started enforcing CHECK constraints in 8.0.16 [CITED: dev.mysql.com/blog-archive/mysql-8-0-16-introducing-check-constraint].
**How to avoid:** Verify target DB is MySQL 8.0.16+ before deploy. Add an assertion in the first migration's `up()`:
```php
$version = $this->fetchRow('SELECT VERSION() AS v')['v'];
if (version_compare($version, '8.0.16', '<')) {
    throw new RuntimeException("MySQL >= 8.0.16 required; found $version");
}
```
**Warning signs:** Invalid data slipping through in dev, integration tests failing only in CI.

### Pitfall 2: `.env` values not reaching `getenv()` due to PHP ini `variables_order`
**What goes wrong:** Dotenv calls `putenv()` + `$_ENV` + `$_SERVER` fill, but if `variables_order` in php.ini excludes E, some code paths don't see the value.
**Why it happens:** Lolipop's shared PHP config may differ from developer's box.
**How to avoid:** The existing snippet in `config/bootstrap.php:63-69` does `->putenv()->toEnv()->toServer()` — all three targets, so `env()` helper (which checks all three) will find the value. [VERIFIED: this snippet is already present and correct, just commented out].
**Warning signs:** `env('DATABASE_URL')` returns null in one SAPI (web) and works in another (CLI).

### Pitfall 3: Bake overwrites manually-edited Table class on re-bake
**What goes wrong:** You bake `UsersTable`, add a custom `initialize()` hook, then re-bake for a different reason → your customization is wiped.
**Why it happens:** `bin/cake bake model` does not merge — it regenerates.
**How to avoid:** Bake FIRST, commit, then edit. Or use `bin/cake bake model --no-overwrite`. In Phase 1, bake is a one-shot: run bake for all 6 AFTER migrations succeed, accept defaults, commit, move on.
**Warning signs:** Git diff shows unexpected wholesale rewrites of Table files.

### Pitfall 4: `DATETIME(6) ON UPDATE CURRENT_TIMESTAMP(6)` fails via Phinx `datetime` type
**What goes wrong:** Phinx MySQL adapter accepts the `update` option only on `timestamp` columns, not `datetime` [VERIFIED: inspecting `MysqlAdapter::getSqlType()` in `src/Phinx/Db/Adapter/MysqlAdapter.php:961-964` — `datetime` returns `['name'=>'datetime','limit'=>$limit]` but the auto-update clause is only built in the timestamp code path].
**Why it happens:** Phinx originated pre-MySQL 5.6.5, when `ON UPDATE` was timestamp-only. MySQL 5.6.5+ allows it on datetime too, but Phinx didn't backport.
**How to avoid:** Two options:
  1. Use `timestamp` for `updated_at` only (accept 2038 risk on updated_at, not created_at).
  2. Use `datetime` for both, but append raw SQL:
     ```php
     $this->execute(
         "ALTER TABLE users MODIFY updated_at DATETIME(6) NOT NULL "
       . "DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)"
     );
     ```
**Recommendation for planner:** Pick **option 1 (timestamp for updated_at)** — smaller diff, reversible, and MVP horizon is well before 2038. Document the 2038 note in migration comment.
**Warning signs:** `bin/cake migrations migrate` succeeds but `SHOW CREATE TABLE users` shows no `ON UPDATE` clause on the updated_at column.

### Pitfall 5: FK order inversion in migrations = failure on first run
**What goes wrong:** A migration for `user_identities` runs before `users` exists → FK creation fails.
**Why it happens:** Phinx runs migrations in filename timestamp order (oldest first). If you bake all 6 in quick succession, timestamps may equal to the second → ordering becomes alphabetical within the same second, which is not FK-safe.
**How to avoid:** When baking, add a 1-second sleep between `bin/cake bake migration` calls, or manually set filenames with increasing timestamps (D-06 locks in FK-dependency order: users → user_identities → inboxes → messages → blocks → reports).
**Warning signs:** `migrations migrate` errors with "ERROR 1215 (HY000): Cannot add foreign key constraint".

### Pitfall 6: `bake model` output uses `BIGINT` auto-increment assumptions
**What goes wrong:** Bake-generated Entity class has `protected $_accessible = ['id' => false]` (good) but the PHPDoc `@property int $id` is wrong for UUID.
**Why it happens:** Bake reads schema post-migration, sees `CHAR(36)` but its template may still write `int` types in docblocks based on the primary-key-is-id assumption.
**How to avoid:** Verify bake output against `Text::uuid()` expectations; manually fix `@property string $id` in the 6 Entity files. This is cosmetic (PHPStan level 8 will catch wrong types) but should be on the planner's checklist for post-bake cleanup.
**Warning signs:** PHPStan reports type mismatches on `$entity->id` usages in Phase 2.

### Pitfall 7: Running `composer update` without the `--lock` flag changes more than intended
**What goes wrong:** `composer update` with no args re-resolves ALL dependencies, which may pull in newer transitive versions than needed for Phase 1.
**Why it happens:** Fresh project with no lock file; D-01 requires bumping PHP only.
**How to avoid:** After editing `composer.json` PHP constraint to `^8.0`, run `composer update --lock` to regenerate lock file without bumping package versions beyond what composer.json allows. If bake/phpstan are also added, do `composer require --dev phpstan/phpstan:^2.1 cakedc/cakephp-phpstan:^4.1` as the single update call.
**Warning signs:** `composer.lock` diff shows hundreds of version bumps instead of just PHP constraint propagation.

### Pitfall 8: Lolipop assigns PHP 8.4 but composer.json says ^8.0 — deprecations at runtime
**What goes wrong:** `^8.0` satisfies 8.0, 8.1, 8.2, 8.3, 8.4. Lolipop's newer `lit-xx` / `std-xx` / `spd-xx` server tiers are already on PHP 8.3/8.4 [VERIFIED: lolipop.jp/manual/hp/cgi/]. CakePHP 4.5 has deprecations on 8.4 (e.g. `implicit nullable` removals).
**Why it happens:** PHP 8.4 tightens type system rules; CakePHP 4.5 was released mid-2023 (pre-8.4).
**How to avoid:** Phase 1 itself only needs composer-level compatibility, but for operational safety the planner should add a note: "deploy-time, pin Lolipop PHP panel to 8.1 or 8.2 until CakePHP 5 upgrade lands" — this is a **Phase 4 concern but surfaces now as a research flag**.
**Warning signs:** Deprecation notices flooding logs after Lolipop deploy.

## Code Examples

### Uncommenting `.env` loader (D-02)

Edit `config/bootstrap.php`, replace lines 63-69 with:
```php
if (!env('APP_NAME') && file_exists(CONFIG . '.env')) {
    $dotenv = new \josegonzalez\Dotenv\Loader([CONFIG . '.env']);
    $dotenv->parse()
        ->putenv()
        ->toEnv()
        ->toServer();
}
```
[Source: `config/bootstrap.php:63-69` — already present as comment, just remove leading `//`.]

### httpoxy mitigation (D-03)

Edit `.htaccess`, replace lines 1-5 with:
```apache
# Prevent the httpoxy vulnerability (CVE-2016-5385 class)
# https://httpoxy.org/
<IfModule mod_headers.c>
    RequestHeader unset Proxy
</IfModule>
```
[Source: `.htaccess:1-5` — already present as comment, just remove leading `#`. Lolipop has `mod_headers` available per CONCERNS.md line 110.]

### composer.json scripts section (D-13)

Replace existing `scripts` block with:
```json
"scripts": {
    "post-install-cmd": "App\\Console\\Installer::postInstall",
    "post-create-project-cmd": "App\\Console\\Installer::postInstall",
    "check": [
        "@phpcs",
        "@phpstan",
        "@test"
    ],
    "phpcs": "phpcs --colors -p",
    "phpcs-fix": "phpcbf --colors -p",
    "phpstan": "phpstan analyse --no-progress",
    "test": "phpunit --colors=always"
}
```
Notes:
- Renamed `cs-check` → `phpcs`, `cs-fix` → `phpcs-fix` per CONTEXT.md discretion (matches CakePHP community convention: `phpcs` is the standard Composer-script name). Planner can keep the old aliases for backward-compat by adding `"cs-check": "@phpcs"` mapping.
- `@phpstan` requires phpstan/phpstan to be installed (see Stack table); otherwise `composer check` errors.

### `config/.env.example` additions (D-05, Claude's Discretion on keys)

Append to existing `.env.example` (DO NOT overwrite — file already has SECURITY_SALT and APP_NAME templates):
```bash
# SSR server secret — 32-byte random string, used by Phase 3 SSR seed generation.
# Generate via: openssl rand -hex 32
# Phase 1 only sets the column + reads from here; no computation uses it yet.
export SERVER_SECRET="__SERVER_SECRET__"

# Database connection (uncomment and fill in for local dev)
# export DATABASE_URL="mysql://tamabox:devpass@localhost/tamabox?encoding=utf8mb4&timezone=UTC&cacheMetadata=true"
# export DATABASE_TEST_URL="mysql://tamabox:devpass@localhost/tamabox_test?encoding=utf8mb4&timezone=UTC&cacheMetadata=true"
```

### `config/app.php` SERVER_SECRET wiring (D-05)

Add under `Security` section in `config/app.php:77-79`:
```php
'Security' => [
    'salt'         => env('SECURITY_SALT'),
    'serverSecret' => env('SERVER_SECRET'),
],
```
This makes `Configure::read('Security.serverSecret')` return the value — Phase 2-3 services read from this key.

### CI-style local check recipe (composer test)

For Phase 1 acceptance:
```bash
# Must all pass before declaring Phase 1 done
composer phpcs       # CakePHP sniff clean on src/ + tests/
composer phpstan     # level 8, excluding src/Console/Installer.php
composer test        # PHPUnit suite (will include the skeleton test + any new tests)
bin/cake migrations migrate   # Applies all 6, idempotent on re-run
bin/cake migrations status    # All 6 marked as "up"
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Phinx `change()` auto-reversible method | `up()` + `down()` explicit pair | Remains current for raw-SQL migrations (Phinx 0.13) | Required by CHECK-constraint pattern in Phase 1 |
| `['id' => false, 'primary_key' => ['id']]` table options | `public $autoId = false` + `$table->addPrimaryKey('id')` | cakephp/migrations 3.x introduced `addPrimaryKey()` helper | Use the newer form |
| Phinx `0.12.x` | Phinx `0.13.x` | cakephp/migrations 3.7+ requires Phinx `^0.13.2`; Phinx `0.13` released 2022 | Already pinned — just install |
| `cs-check` / `cs-fix` composer-script names | `phpcs` / `phpcs-fix` / `phpstan` / `test` | Community convention shift over CakePHP 4 lifecycle | Cosmetic; keep aliases if desired |
| Implicit Table classes via `TableLocator` fallback | Explicit `*Table.php` classes required | CakePHP 4.x pattern, explicitly enforced in `Application.php:52-56` | Phase 1 must bake ALL 6 Table classes before any controller uses ORM |
| `cakephp/migrations 4.x` + `Migrations\BaseMigration` | Not applicable | `BaseMigration` is CakePHP 5 / migrations 4.x only; CakePHP 4.5 stays on migrations 3.x | **Ignore any 4.x doc examples found on cakephp.org — they use `BaseMigration`, we use `AbstractMigration`** |

**Deprecated/outdated:**
- `RequestHandler` component (already in `AppController`) — deprecated in CakePHP 4.4 [CITED: CONCERNS.md:29-33]. Not in Phase 1 scope to fix (keep for now).
- PHP 7.4 — EOL since 2022-11 [CITED: CONCERNS.md:131-135]. D-01 addresses by bumping to ^8.0.

## Project Constraints (from CLAUDE.md)

The root `CLAUDE.md` is auto-assembled by GSD — no hand-written project rules beyond what's in PROJECT.md + codebase maps. Key directives:
- Follow CakePHP coding standard via `phpcs.xml` [CITED: CONVENTIONS.md line 51]
- PHPStan level 8 on `src/` [CITED: phpstan.neon]
- `declare(strict_types=1);` mandatory on every PHP file [CITED: CONVENTIONS.md line 77]
- Docblocks required on every class/method/property (sniff-enforced)
- PSR-4: `App\` → `src/`; tests: `App\Test\` → `tests/`
- Write to `src/Service/` for business logic (directory does not yet exist — will be created in Phase 2)
- **GSD workflow:** Use `/gsd-execute-phase` for planned phase work; do not make direct repo edits outside a GSD workflow [CITED: CLAUDE.md:286-291]

Translation for Phase 1 tasks:
- Every new PHP file (migrations, Tables, Entities) gets `<?php\ndeclare(strict_types=1);\n` header.
- Docblocks on every migration class's `up()` and `down()` methods.
- Bake-generated files already include docblocks; keep them.
- PHPStan level 8 applies to `src/` only — `config/Migrations/` is not under `src/`, so migrations don't need level-8 compliance (but they should still pass phpcs).

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Lolipop's shared hosting git-deploy path preserves `config/.env` (which is gitignored) — must be scp'd or managed via control panel separately | Code Examples (.env section) | **Deploy-time discovery**: if wrong, production boots without env vars and silently falls back to defaults. However, this is a Phase 4 concern; Phase 1 doesn't deploy. |
| A2 | The 4 ENUM columns (`provider`, `sender_provider`, `reason`, `status`) stay as MySQL native ENUM per DB-SCHEMA.md, despite CONTEXT `<specifics>` arguing against ENUM for `deleted_reason` | Pattern 4 | Medium — if the user prefers VARCHAR(64) uniformly, the 4 migrations change; same schema result, different Phinx type. Planner should ask at planning time. |
| A3 | Option 1 (`timestamp` for updated_at, accept 2038 ceiling) is preferred over Option 2 (raw-SQL for datetime ON UPDATE) | Pitfall 4 | Low — both produce same behavior until 2038; cosmetic. Planner decides. |
| A4 | `updated_at ON UPDATE CURRENT_TIMESTAMP(6)` is actually needed / desired. DB-SCHEMA.md DDL includes it for users, user_identities, inboxes. CakePHP's Timestamp behavior (D-09) also updates `updated_at` at app level. Dual-updating is harmless (DB wins last) but the DB default is a belt-and-suspenders safety net. | Standard Stack "Alternatives Considered" | Low — documenting this is sufficient; no schema change either way. |
| A5 | Moving `josegonzalez/dotenv` from `require-dev` to `require` (implicit in D-02) is the intended interpretation | Stack table | High — if wrong, `composer install --no-dev` on production will fatal when bootstrap.php tries to `new \josegonzalez\Dotenv\Loader`. Planner MUST address this (either move it, OR decide D-02 is dev-only and prod uses Apache SetEnv). |
| A6 | `phpstan/phpstan` + `cakedc/cakephp-phpstan` should be added to require-dev as part of D-13, because `phpstan.neon` already declares level 8 but the tool isn't installed | Stack + Code Examples | Medium — if wrong and the user wants to defer phpstan install, the `composer phpstan` script fails. Simple fix: document `phpstan` script exists but requires manual install. |
| A7 | CHECK constraints matching DB-SCHEMA.md verbatim (naming, predicates) is what the user wants, not a paraphrase | Pattern 1 template | Low — DB-SCHEMA.md is single source of truth per D-10. |
| A8 | 6-second-apart migration timestamps (or hand-numbered sequential 20260422120001..006) is acceptable | Pitfall 5 | Low — cosmetic ordering. |

**If user confirms A2 (ENUM stays) and A5 (dotenv moves to require) during planning, all other assumptions are low-risk.**

## Open Questions (RESOLVED)

1. **Is `config/.env` the ONLY secret source, or a dev-convenience with Lolipop-side env also set?**
   **RESOLVED:** `.env` is the only secret source for Phase 1 (D-02 + CONTEXT specifics). Lolipop SetEnv is deferred to Phase 4 production hardening.
   - What we know: D-02 says uncomment the dotenv loader; CONCERNS.md:95-99 flags both approaches.
   - What's unclear: Whether production Lolipop will also need `.htaccess SetEnv` entries as defense-in-depth.
   - Recommendation: Document "dev: .env file; prod: both .env AND SetEnv as fail-safe" but **defer the SetEnv part to Phase 4**. Phase 1 only wires the dotenv loader.

2. **For the 4 ENUM columns, match DB-SCHEMA.md literally or apply the `deleted_reason=VARCHAR` reasoning uniformly?**
   **RESOLVED:** Keep ENUM for `user_identities.provider`, `messages.sender_provider`, `reports.reason`, `reports.status` per DB-SCHEMA.md v0.2 (D-10). VARCHAR(64) only for `messages.deleted_reason` per CONTEXT `<specifics>`.
   - What we know: DB-SCHEMA.md uses ENUM; CONTEXT `<specifics>` prefers VARCHAR for `deleted_reason`.
   - What's unclear: Whether "avoid ENUM" was a universal principle or a one-off.
   - Recommendation: Ask user at plan time; default to DB-SCHEMA.md verbatim (ENUM) if no preference expressed.

3. **Does the planner want a dedicated migration verification step (e.g., `bin/cake migrations status` output attached to the phase-complete artifact), or is "it ran without error" sufficient?**
   **RESOLVED:** Use INFORMATION_SCHEMA introspection in Plan 02b final task (covers charset/collation/PK/FK/constraints via standard SQL; portable output).
   - What we know: ROADMAP Phase 1 success criteria not yet exposed in this research scope.
   - What's unclear: Observable artifact.
   - Recommendation: At minimum, include a verification node that runs `SHOW CREATE TABLE users;` etc. and diffs against DB-SCHEMA.md v0.2. This catches CHECK-constraint-silently-ignored cases.

4. **Does `SERVER_SECRET` need to be in `.env.example` (committed) as `__SERVER_SECRET__` placeholder, or just documented in README?**
   **RESOLVED:** Use `__SERVER_SECRET__` (matches CakePHP skeleton `__SALT__` convention, documented in Plan 01 Task 4 action).
   - What we know: `.env.example` IS committed (gitignore only excludes `.env`, not `.env.example`).
   - What's unclear: Whether placeholder pattern matches project-wide convention.
   - Recommendation: Place `__SERVER_SECRET__` in `.env.example` matching the existing `__SALT__` placeholder pattern seen in `app_local.example.php:28` — consistency wins.

5. **Should there be a downgrade path if a migration fails partway through?**
   **RESOLVED:** Each migration has explicit `up()` + `down()` pair (P1 canonical template). Recovery = `bin/cake migrations rollback` per-step; documented in Plan 02b final task notes.
   - What we know: Each migration has `down()`; Phinx transaction behavior on DDL is MySQL-engine-dependent (InnoDB doesn't do DDL transactions at all).
   - What's unclear: Recovery procedure.
   - Recommendation: Document in phase README: "on migration failure, manually `DROP TABLE <failed> CASCADE;` and re-run `bin/cake migrations migrate`". No automation needed for MVP.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | All tooling | Developer: TBD; Production (Lolipop): 8.0+ | — | Documented blocker on developer's local box: version `php -v` should report >=8.0 |
| Composer | Dependency install (D-04) | Yes (assumed — project is a `composer create-project` skeleton) | 2.x assumed | None |
| MySQL 8.0.16+ | Schema apply + CHECK enforcement (D-10) | Lolipop confirmed MySQL 8.0 per STACK.md; local dev box: TBD | 8.0.16+ for CHECK | **No fallback — older MySQL silently ignores CHECK**. Developer must verify locally with `SELECT VERSION()`. |
| `bin/cake` CLI | All migration / bake tasks | Yes — present at `bin/cake` | CakePHP 4.5 | None |
| `openssl` CLI | `openssl rand -hex 32` for SERVER_SECRET | Standard on macOS / Linux / Lolipop | — | Alternative: `php -r 'echo bin2hex(random_bytes(32));'` works identically |
| Apache `mod_headers` | httpoxy mitigation (D-03) | Lolipop has it per CONCERNS.md:110 | — | If disabled: `<IfModule>` block no-ops silently; vulnerability reopens. Check Lolipop control panel pre-deploy (Phase 4). |
| git | `composer.lock` commit | Yes (project is under git) | — | None |

**Missing dependencies with no fallback:** None for Phase 1 execution. The MySQL 8.0.16+ requirement is the only hard blocker, and that's a dev-environment gate.

**Missing dependencies with fallback:** None critical. `openssl` vs PHP `random_bytes` is interchangeable.

**Already installed / requires no new install:** All CakePHP 4.5 plumbing is already in composer.json; `composer install` (D-04) is the only install step needed.

## Sources

### Primary (HIGH confidence)
- **`/cakephp/phinx`** (Context7 — via `npx ctx7 docs`) — Phinx 0.x migration API, rollback, change method, column types
- **robmorgan/phinx 0.13.4 tag** (GitHub raw via `gh api`):
  - `docs/en/migrations.rst` (1937 lines) — authoritative source for `addForeignKey`, `addIndex`, `addColumn`, column types, `$autoId`
  - `src/Phinx/Db/Adapter/MysqlAdapter.php:961-964` — PROOF that `datetime/timestamp/time` honor `limit` as fractional precision on MySQL
  - `src/Phinx/Db/Adapter/MysqlAdapter.php:1098-1099` — PROOF that Phinx `uuid` type = MySQL `CHAR(36)`
  - Exhaustive grep for `CHECK` / `addConstraint` / `CheckConstraint` in MysqlAdapter.php — ZERO results, confirming no native API
- **cakephp/migrations 3.9.0 tag** (GitHub raw via `gh api`):
  - `composer.json` — confirms PHP >=7.4, phinx ^0.13.2, ORM ^4.3.0
  - `src/AbstractMigration.php:21-49` — confirms `$autoId` toggle + `->table()` override
  - `src/Table.php:41-47` — confirms `addPrimaryKey($columns)` method signature
  - `templates/bake/config/skeleton.twig:17-38` — confirms baked migration boilerplate uses `use Migrations\AbstractMigration`
  - `templates/Phinx/create.php.template` — confirms `$baseClassName` default is `Migrations\AbstractMigration`
- **GitHub API** (`gh api repos/cakephp/migrations/releases/tags/3.9.0`) — release date 2023-09-22
- **`emomie/ssr-box-discovery:DB-SCHEMA.md`** (fetched via `gh api`) — authoritative schema source; all DDL, CHECK predicates, FK cascade directions
- **Project files read:** `composer.json`, `config/bootstrap.php`, `config/app.php`, `config/app_local.example.php`, `config/requirements.php`, `.htaccess`, `src/Application.php`, plus all `.planning/codebase/*.md` and `.planning/phases/01-foundation-schema/01-CONTEXT.md`

### Secondary (MEDIUM confidence)
- **packagist.org/packages/josegonzalez/dotenv** (WebFetch) — confirmed `4.0.0` latest, 2023-05-29, `php >=5.5.0`
- **packagist.org/packages/cakephp/migrations** (WebFetch) — confirmed `3.9.0` is latest 3.x
- **book.cakephp.org/migrations/4/en/writing-migrations.html** (WebFetch) — modern migrations API (migrations 4.x, for CakePHP 5) — used for cross-reference to confirm divergence from 3.x API
- **dev.mysql.com/blog-archive/mysql-8-0-16-introducing-check-constraint** (web search) — MySQL 8.0.16 as CHECK-enforcement threshold
- **lolipop.jp/manual/hp/cgi/** (WebFetch) — confirmed Lolipop PHP 7.4 / 8.0 / 8.1 / 8.2 / 8.3 / 8.4 per server tier (2026 state)
- **phpunit.de/supported-versions** (web search) — PHPUnit 9.x supports PHP 7.3+ / 8.x

### Tertiary (LOW confidence — flagged for planner)
- Exact Lolipop git-deploy behavior around `config/.env` (is it preserved across deploys? does deploy hook scp it in?) — **Phase 4 concern, not Phase 1; flag only**.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all versions verified against upstream source at specific tag SHAs
- Architecture (migrations patterns): HIGH — verified against Phinx 0.13.4 source code for non-obvious claims (DATETIME precision, uuid→CHAR(36), no CHECK support)
- Pitfalls: HIGH — Pitfalls 4, 6 derived directly from source inspection; Pitfalls 1, 2, 5, 7, 8 derived from cross-referenced documentation and codebase files
- CHECK constraint handling: HIGH — confirmed by exhaustive grep of MysqlAdapter.php; zero API exists
- User ENUM interpretation (A2): LOW — single source of truth ambiguity, flagged as Open Question 2

**Research date:** 2026-04-22
**Valid until:** 2026-05-22 (30 days — CakePHP 4.5 is stable / frozen; only josegonzalez/dotenv or phpstan could release new minors, and those are both isolated concerns)

---

*Phase: 01-foundation-schema*
*Researched: 2026-04-22*
