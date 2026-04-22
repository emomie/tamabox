---
phase: 01-foundation-schema
plan: 02a
type: execute
wave: 2
depends_on:
  - 01-01
files_modified:
  - config/Migrations/20260422120001_CreateUsers.php
  - config/Migrations/20260422120002_CreateUserIdentities.php
  - config/Migrations/20260422120003_CreateInboxes.php
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
    - "config/.env exists with real SERVER_SECRET and SECURITY_SALT (placeholders replaced)"
    - "MySQL 8.0.16+ verified; tamabox + test_tamabox databases exist with utf8mb4_0900_ai_ci"
    - "config/Migrations/20260422120001_CreateUsers.php syntactically valid"
    - "config/Migrations/20260422120002_CreateUserIdentities.php syntactically valid, FK CASCADE to users"
    - "config/Migrations/20260422120003_CreateInboxes.php syntactically valid, FK CASCADE to users, DECIMAL(4,3) probability with slug REGEXP CHECK"
    - "All 3 migrations use public \\$autoId = false + up()/down() (no change())"
    - "CHECK constraints written via raw \\$this->execute() (Phinx 0.13 has no addConstraint API)"
  artifacts:
    - path: "config/Migrations/20260422120001_CreateUsers.php"
      provides: "users table migration (no FKs; root of FK graph)"
      contains: "class CreateUsers extends AbstractMigration"
      min_lines: 50
    - path: "config/Migrations/20260422120002_CreateUserIdentities.php"
      provides: "user_identities table with FK to users CASCADE, provider ENUM, composite UNIQUE (provider, provider_account_id), is_primary BOOLEAN"
      contains: "class CreateUserIdentities extends AbstractMigration"
      min_lines: 60
    - path: "config/Migrations/20260422120003_CreateInboxes.php"
      provides: "inboxes table with FK to users CASCADE, slug UNIQUE, ssr_probability DECIMAL(4,3) default 0.100, slug regex CHECK"
      contains: "class CreateInboxes extends AbstractMigration"
      min_lines: 60
  key_links:
    - from: "user_identities.user_id"
      to: "users.id"
      via: "FK ON DELETE CASCADE"
      pattern: "fk_user_identities_user"
    - from: "inboxes.user_id"
      to: "users.id"
      via: "FK ON DELETE CASCADE"
      pattern: "fk_inboxes_user"
---

<objective>
DB-SCHEMA.md v0.2 の 6 テーブルのうち、依存の根である **users / user_identities / inboxes** の 3 つを Phinx 0.13 + cakephp/migrations 3.9 で実装する。Phase 2b (01-02b) が残りの messages / blocks / reports を積み増し、最終 `bin/cake migrations migrate` まで実行する。

Purpose:
- INFRA-04 の前半 ── ルート (users) とそれに直接ぶら下がる 2 テーブル (user_identities / inboxes) のスキーマ定義。
- Plan 02 を 8 タスクから分割 (checker BLOCKER 2 対応) し、各サブプランを 3-5 タスクに収めて context window を健全化。
- CHECK 制約は Phinx 0.13 にネイティブ API がないため全て raw SQL (`$this->execute`) で書く → `up()`/`down()` 明示ペア (RESEARCH §Anti-Patterns)。

Output:
- `config/Migrations/` 配下に 3 ファイル (users / user_identities / inboxes)、FK 依存順の timestamp でハード番号付け (D-06, Pitfall 5)
- 開発者ローカルに `config/.env` が配置され、MySQL 8.0.16+ が走り、tamabox + test_tamabox DB が空で存在する
- **`bin/cake migrations migrate` は Plan 02b の最終タスクで実行する** (このプランでは migration ファイル作成と環境準備のみ)

**Non-goals (Plan 02a 外):**
- messages / blocks / reports migration ファイル ← Plan 02b Task 1-3
- `bin/cake migrations migrate` 実行 ← Plan 02b Task 4
- UUID 自動生成ロジック ← Phase 2 で Table `initialize()` に入れる
- `ssr_seed` の計算式 ← Phase 3 (MSG-03)
- OAuth トークン暗号化実装 ← Phase 2 (AUTH-07)
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
@.planning/codebase/STACK.md
@.planning/codebase/ARCHITECTURE.md

<prereqs>
## Prerequisites (not authored in this plan, but required before `bin/cake migrations migrate` in Plan 02b)

1. Plan 01-01 must be complete (composer.lock, vendor/, bootstrap.php dotenv active, .env.example has DATABASE_URL).

2. Developer must have a local MySQL 8.0.16+ database and a `config/.env` file (copied from `.env.example` and filled with real credentials + `openssl rand -hex 32` for SERVER_SECRET). If `.env` is missing, Task 1 below produces it.

3. DB-SCHEMA.md v0.2 is the authoritative DDL reference. Fetch it via:
   ```
   gh api repos/emomie/ssr-box-discovery/contents/DB-SCHEMA.md --jq '.content' | base64 -d > /tmp/DB-SCHEMA.md
   ```
   Read /tmp/DB-SCHEMA.md before writing each migration's columns/checks. RESEARCH.md summarizes the cascade table and column list but is NOT a substitute — always cross-check column names, types, nullability, defaults, and CHECK predicates against DB-SCHEMA.md verbatim.
</prereqs>

<decisions_locked>
## Decisions Locked (from CONTEXT.md + RESEARCH.md resolutions)

- **A2 ENUM-vs-VARCHAR (resolved per additional_guidance):** `user_identities.provider`, `messages.sender_provider`, `reports.reason`, `reports.status` stay as MySQL ENUM per DB-SCHEMA.md verbatim. Only `messages.deleted_reason` is VARCHAR(64) (per CONTEXT.md `<specifics>`).
- **A3 timestamp-vs-datetime for updated_at (resolved per additional_guidance):** `updated_at` uses Phinx `timestamp` type with `limit:6, update:'CURRENT_TIMESTAMP(6)'` (2038 ceiling accepted). `created_at`, `deleted_at`, `opened_at`, `last_synced_at`, etc. use Phinx `datetime` type with `limit:6` (no 2038 issue).
- **A7 CHECK naming:** `<table>_<field>_check` (e.g. `users_display_name_check`) per DB-SCHEMA.md v0.2 convention.
- **D-06 file naming:** hand-numbered timestamps 20260422120001 … 20260422120006 in FK-dependency order. FK order: users → user_identities → inboxes → messages → blocks → reports (RESEARCH Pitfall 5).
- **D-10 DB-SCHEMA.md verbatim:** DDL sourced from `emomie/ssr-box-discovery:DB-SCHEMA.md` v0.2. When this plan's column hints disagree with DB-SCHEMA.md, DB-SCHEMA.md wins.
- **D-11 `is_primary` on user_identities:** `BOOLEAN DEFAULT TRUE` alongside the `(user_id)` UNIQUE (DB enforces 1:1 for MVP; future account-linking will replace the index).
- **D-12 no partial index:** `deleted_at` filtering is query-side; indexes are composite `(inbox_id, deleted_at)` for messages.
- **Raw SQL pattern:** every CHECK constraint goes via `$this->execute("ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)")` at end of `up()`. `change()` is forbidden — use `up()` + `down()`.
- **Anti-bake:** prefer hand-writing migrations from the RESEARCH Pattern 1 template rather than `bin/cake bake migration` (bake's auto-id BIGINT skeleton needs wholesale replacement anyway — direct hand-write is faster and matches hand-numbered timestamps).
</decisions_locked>

<canonical_template>
## Canonical Migration Skeleton (applies to all 6 migrations across 02a + 02b)

All 6 migration files follow this shape. See RESEARCH.md §Architecture Patterns Pattern 1 for the full verified version. Per-migration differences are listed in the Tasks below.

    <?php
    declare(strict_types=1);

    use Migrations\AbstractMigration;

    /**
     * Create<Pascal> migration.
     *
     * Source of truth: emomie/ssr-box-discovery:DB-SCHEMA.md v0.2.
     * CHECK constraints are applied via raw SQL (Phinx 0.13 has no addConstraint API),
     * so this migration is NOT auto-reversible → up()/down() pair, never change().
     */
    class Create<Pascal> extends AbstractMigration
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
            $table = $this->table('<plural_snake>', [
                'collation' => 'utf8mb4_0900_ai_ci',
                'engine'    => 'InnoDB',
            ]);

            $table
                ->addColumn('id', 'uuid', ['null' => false])
                ->addPrimaryKey('id')
                // ... domain columns per DB-SCHEMA.md ...
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
                // ... indexes ...
                // ... FKs (if any) via ->addForeignKey(...) ...
                ->create();

            // CHECK constraints — raw SQL because Phinx 0.13 has no native API.
            $this->execute(<<<SQL
    ALTER TABLE <plural_snake>
      ADD CONSTRAINT <plural_snake>_<field>_check
      CHECK (<predicate>)
    SQL);
        }

        /**
         * @return void
         */
        public function down(): void
        {
            $this->table('<plural_snake>')->drop()->save();
        }
    }

**FK template (Pattern 2 from RESEARCH.md):**

    $table->addForeignKey(
        '<col_in_this_table>',
        '<ref_table>',
        'id',
        [
            'delete'     => 'CASCADE',       // or 'RESTRICT' or 'SET_NULL'
            'update'     => 'NO_ACTION',
            'constraint' => 'fk_<this>_<ref>',
        ]
    );

**ENUM template (Pattern 4):**

    $table->addColumn('provider', 'enum', [
        'values' => ['bluesky', 'x'],
        'null'   => false,
    ]);

**Composite UNIQUE (Pattern 3):**

    $table->addIndex(
        ['provider', 'provider_account_id'],
        ['unique' => true, 'name' => 'uk_user_identities_provider_account']
    );
</canonical_template>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Bootstrap local .env and verify DB connection + MySQL version + clean state</name>
  <read_first>
    - config/.env.example (post-Plan-01 state — must contain __SERVER_SECRET__ placeholder and uncommented DATABASE_URL)
    - config/bootstrap.php (dotenv loader must be active — verify, do NOT re-edit)
    - .planning/phases/01-foundation-schema/01-RESEARCH.md sections: Common Pitfalls (Pitfall 1 MySQL 8.0.16, Pitfall 2 variables_order), Runtime State Inventory (DROP DATABASE cleanup note), Environment Availability
    - .planning/phases/01-foundation-schema/01-CONTEXT.md section: Decisions (D-05 server_secret generation)
  </read_first>
  <files>config/.env (NEW — gitignored, not committed)</files>
  <action>
  Bootstrap the developer's local environment so `bin/cake migrations migrate` (Plan 02b Task 4) can connect. This task produces a gitignored file (config/.env) — it IS part of the task but is NOT in files_modified (gitignored).

  Step 1: Copy template.
      cp config/.env.example config/.env

  Step 2: Generate a real SERVER_SECRET and replace the placeholder in the new config/.env (NOT in .env.example — that stays as template).
      SECRET=$(openssl rand -hex 32)
      # macOS sed needs '' after -i; linux sed (Lolipop / this VPS / most CI) uses -i with no arg.
      sed -i "s/__SERVER_SECRET__/${SECRET}/" config/.env
      # Also replace SECURITY_SALT __SALT__ placeholder with another random value (Cake uses it as encryption key).
      SALT=$(openssl rand -hex 32)
      sed -i "s/__SALT__/${SALT}/" config/.env

  Step 3: Confirm developer has the DB credentials the .env expects. Default `mysql://tamabox:secret@localhost/${APP_NAME}?...` assumes a local user named `tamabox` with password `secret` — if the developer's DB differs, they manually edit config/.env to match. This task does NOT create the MySQL user or the database; it only copies the template and swaps placeholders.

  Step 4: Verify dotenv resolution works end-to-end:
      bin/cake config_key Datasources.default 2>&1 | grep -q 'tamabox' \
        || { echo "config/.env not resolving — inspect"; bin/cake --version; exit 1; }

     (Note: `bin/cake config_key` is a builtin that prints a Configure key. If the builtin is not available in this CakePHP version, substitute `bin/cake i18n --help` or just check the app boots via `bin/cake --version`.)

  Step 5: Verify or create the target DB. Use `mysql` CLI (or `mysqladmin`) — assume the developer knows their root or admin credentials for local MySQL:
      mysql -e "CREATE DATABASE IF NOT EXISTS tamabox CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
      mysql -e "CREATE DATABASE IF NOT EXISTS test_tamabox CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
      # Ensure the 'tamabox' user the DSN points at exists and has privs — skip if already setup.

     NOTE (Warning W5 mitigation — MySQL CLI auth): the plain `mysql` invocations above assume a pre-configured `~/.my.cnf` (with `[client] user=... password=...`) OR an equivalent `--defaults-extra-file=/path/to/cnf`. If neither is set up on the developer's box, export `MYSQL_PWD=...` for the shell session before running, OR substitute a PHP-based check:
          php -r '$v = trim(shell_exec("mysql -Nse \"SELECT VERSION()\" 2>/dev/null")); if ($v === "") { fwrite(STDERR, "mysql CLI auth not configured\n"); exit(1); } echo "MySQL $v\n"; exit(version_compare($v,"8.0.16",">=")?0:1);'
     The PHP `version_compare` fallback is the portable option if `mysql` CLI auth is flaky on the dev box.

  Step 6: Check MySQL version ≥ 8.0.16 (RESEARCH Pitfall 1 — older MySQL silently ignores CHECK):
      mysql -e "SELECT VERSION();" 2>&1 | tee /tmp/mysql_version.txt
      VERSION=$(mysql -Nse "SELECT VERSION();" | grep -oE '^[0-9]+\.[0-9]+\.[0-9]+')
      php -r "exit(version_compare('$VERSION','8.0.16','>=')?0:1);" \
        || { echo "MySQL version $VERSION < 8.0.16 — CHECK constraints will be silently ignored. Upgrade before running migrations."; exit 1; }

  Step 7: Ensure target DB is clean (fresh Phase 1 run may retry). If the DB exists with partial tables from prior attempts:
      mysql tamabox -e "SHOW TABLES;" | tee /tmp/existing_tables.txt
      # If output contains any of: users, user_identities, inboxes, messages, blocks, reports, phinxlog:
      #   mysql -e "DROP DATABASE tamabox; CREATE DATABASE tamabox CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
      # otherwise proceed.

  Do NOT commit config/.env — already gitignored via /.gitignore line 4.
  </action>
  <verify>
    <automated>test -f config/.env && ! grep -q '__SERVER_SECRET__' config/.env && ! grep -q '__SALT__' config/.env && mysql -Nse "SELECT VERSION();" | grep -qE '^[89]\.[0-9]+\.[0-9]+' && mysql -Nse "SELECT VERSION();" | awk -F. '{if($1<8||($1==8 && $2==0 && $3<16)) exit 1; exit 0}' && mysql tamabox -Nse "SELECT 1" | grep -q '^1$' && mysql test_tamabox -Nse "SELECT 1" | grep -q '^1$'</automated>
  </verify>
  <acceptance_criteria>
    - `config/.env` exists
    - `config/.env` does NOT contain `__SERVER_SECRET__` or `__SALT__` (placeholders replaced)
    - `mysql -Nse "SELECT VERSION();"` returns a version ≥ 8.0.16
    - `mysql tamabox -e "SELECT 1"` exits 0 (database exists, user can connect)
    - `mysql test_tamabox -e "SELECT 1"` exits 0 (test DB exists)
    - `mysql tamabox -Nse "SHOW TABLES;"` returns empty or only `phinxlog` (no stale tamabox tables)
  </acceptance_criteria>
  <done>Local dev environment configured: .env populated with real SERVER_SECRET and SECURITY_SALT, tamabox + test_tamabox databases exist with utf8mb4_0900_ai_ci, MySQL is 8.0.16+, no stale tables present.</done>
</task>

<task type="auto">
  <name>Task 2: Write CreateUsers migration (20260422120001)</name>
  <read_first>
    - /tmp/DB-SCHEMA.md (fetch via `gh api repos/emomie/ssr-box-discovery/contents/DB-SCHEMA.md --jq '.content' | base64 -d > /tmp/DB-SCHEMA.md` if not yet fetched) — read the `## 1. users` section verbatim
    - .planning/phases/01-foundation-schema/01-RESEARCH.md section: Architecture Patterns, Pattern 1 (full users.php template)
    - .planning/phases/01-foundation-schema/01-PATTERNS.md section: Migrations → "Per-migration pattern-slot assignments" row for CreateUsers
  </read_first>
  <files>config/Migrations/20260422120001_CreateUsers.php</files>
  <action>
  Create config/Migrations/ directory if absent, then write the first migration using the Canonical Migration Skeleton (see context above).

  Columns (from DB-SCHEMA.md v0.2 §1 — verify verbatim before writing):
  - `id` uuid NOT NULL PRIMARY KEY
  - `display_name` VARCHAR(64) NOT NULL  (Phinx: `string`, `limit => 64`)
  - `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)  (Phinx: `datetime`, `limit => 6`, default Literal)
  - `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)  (Phinx: `timestamp`, `limit => 6`, `update => 'CURRENT_TIMESTAMP(6)'`)
  - `deleted_at` DATETIME(6) NULL  (Phinx: `datetime`, `limit => 6`, `null => true`)

  Indexes:
  - `idx_users_deleted_at` on `(deleted_at)` (supports `WHERE deleted_at IS NULL` queries per D-12)

  FKs: NONE (root of the FK graph).

  CHECK constraints (raw SQL at end of up()):
  1. `users_display_name_check` — `CHAR_LENGTH(display_name) BETWEEN 1 AND 64`
  2. (If DB-SCHEMA.md also specifies `deleted_at IS NULL OR deleted_at > created_at`): `users_deleted_after_created_check` — `deleted_at IS NULL OR deleted_at > created_at`

  Only add CHECK constraints that DB-SCHEMA.md v0.2 literally specifies for the `users` table — do not invent predicates.

  File header: standard CakePHP MIT docblock is NOT required for migrations (they live outside src/ PHPStan scope per PATTERNS.md). Use the minimum header:

      <?php
      declare(strict_types=1);

      use Migrations\AbstractMigration;

  down() implementation: `$this->table('users')->drop()->save();`

  File path: `config/Migrations/20260422120001_CreateUsers.php` (hand-numbered per D-06).

  After writing, verify syntactically: `php -l config/Migrations/20260422120001_CreateUsers.php` must print "No syntax errors detected". Do NOT run `bin/cake migrations migrate` yet — that happens in Plan 02b Task 4.
  </action>
  <verify>
    <automated>test -f config/Migrations/20260422120001_CreateUsers.php && php -l config/Migrations/20260422120001_CreateUsers.php | grep -q 'No syntax errors' && grep -q 'class CreateUsers extends AbstractMigration' config/Migrations/20260422120001_CreateUsers.php && grep -q "public \$autoId = false" config/Migrations/20260422120001_CreateUsers.php && grep -qE "public function up\(\)" config/Migrations/20260422120001_CreateUsers.php && grep -qE "public function down\(\)" config/Migrations/20260422120001_CreateUsers.php && grep -q "utf8mb4_0900_ai_ci" config/Migrations/20260422120001_CreateUsers.php && grep -q "'id', 'uuid'" config/Migrations/20260422120001_CreateUsers.php && grep -q "'display_name'" config/Migrations/20260422120001_CreateUsers.php && grep -q "'created_at', 'datetime'" config/Migrations/20260422120001_CreateUsers.php && grep -q "'updated_at', 'timestamp'" config/Migrations/20260422120001_CreateUsers.php && grep -q 'CURRENT_TIMESTAMP(6)' config/Migrations/20260422120001_CreateUsers.php && grep -qE 'users_display_name_check' config/Migrations/20260422120001_CreateUsers.php && ! grep -q 'public function change' config/Migrations/20260422120001_CreateUsers.php</automated>
  </verify>
  <acceptance_criteria>
    - File `config/Migrations/20260422120001_CreateUsers.php` exists
    - `php -l` reports no syntax errors
    - Class is named `CreateUsers` and extends `Migrations\AbstractMigration`
    - `public $autoId = false;` is present
    - Both `public function up()` and `public function down()` are defined; `public function change()` is NOT defined
    - Table collation is `utf8mb4_0900_ai_ci`
    - Columns defined: `id` (uuid), `display_name`, `created_at` (datetime), `updated_at` (timestamp with update), `deleted_at`
    - `CURRENT_TIMESTAMP(6)` literal appears at least twice (for created_at and updated_at defaults)
    - At least one CHECK constraint with `_check` suffix is defined via raw SQL (`$this->execute`)
    - Index on `deleted_at` exists (grep for `idx_users_deleted_at`)
  </acceptance_criteria>
  <done>CreateUsers migration file written with UUID PK, DATETIME(6) timestamps, deleted_at index, display_name CHECK constraint. Syntactically valid PHP, not yet executed.</done>
</task>

<task type="auto">
  <name>Task 3: Write CreateUserIdentities migration (20260422120002)</name>
  <read_first>
    - /tmp/DB-SCHEMA.md section on `user_identities` — read verbatim
    - .planning/phases/01-foundation-schema/01-RESEARCH.md sections: Architecture Patterns (Patterns 2, 3, 4), FK cascade table, Pattern 4 Inconsistency note (A2 resolution)
    - .planning/phases/01-foundation-schema/01-PATTERNS.md — "Per-migration pattern-slot assignments" row for CreateUserIdentities
    - .planning/phases/01-foundation-schema/01-CONTEXT.md — D-11 is_primary decision
  </read_first>
  <files>config/Migrations/20260422120002_CreateUserIdentities.php</files>
  <action>
  Write the second migration using the Canonical Migration Skeleton + Pattern 2 (FK) + Pattern 3 (composite UNIQUE) + Pattern 4 (ENUM).

  Columns (from DB-SCHEMA.md v0.2 §2 — verify verbatim):
  - `id` uuid NOT NULL PRIMARY KEY
  - `user_id` uuid NOT NULL  (FK target: users.id)
  - `provider` ENUM('bluesky','x') NOT NULL  (Phinx enum per Pattern 4, per A2 resolution)
  - `provider_account_id` VARCHAR(255) NOT NULL  (exact limit per DB-SCHEMA.md — may be different; verify)
  - `handle` VARCHAR(255) NULL
  - `avatar_url` VARCHAR(2048) NULL  (or TEXT if DB-SCHEMA.md says so)
  - `profile_url` VARCHAR(2048) NULL
  - `access_token_enc` BLOB / VARBINARY NULL  (AES-GCM ciphertext, Phase 2 populates)
  - `refresh_token_enc` BLOB / VARBINARY NULL
  - `is_primary` BOOLEAN NOT NULL DEFAULT TRUE  (D-11)
  - `last_synced_at` DATETIME(6) NULL
  - `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
  - `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)

  Indexes:
  - Composite UNIQUE `uk_user_identities_provider_account` on (provider, provider_account_id)  — Pattern 3
  - UNIQUE `uk_user_identities_user` on (user_id)  — D-11 (MVP 1:1 constraint)
  - Standard (non-unique) on `user_id` is redundant if UNIQUE already exists → skip

  FK:
  - `fk_user_identities_user` on `user_id` → `users.id`, `ON DELETE CASCADE`, `ON UPDATE NO_ACTION`

  CHECK constraints (only what DB-SCHEMA.md v0.2 literally specifies):
  - `user_identities_provider_account_id_check` — e.g. `CHAR_LENGTH(provider_account_id) >= 1` (only add if DB-SCHEMA.md specifies a length check; if not, omit)

  Column-type caveats:
  - Phinx's `blob` type → MySQL BLOB. If DB-SCHEMA.md specifies VARBINARY with a specific size, use `->addColumn('access_token_enc', 'varbinary', ['limit' => <size>, 'null' => true])`. Verify against DB-SCHEMA.md.
  - For boolean `is_primary`: use `->addColumn('is_primary', 'boolean', ['null' => false, 'default' => true])`. Phinx maps this to MySQL `TINYINT(1)`.

  down(): `$this->table('user_identities')->drop()->save();`
  </action>
  <verify>
    <automated>test -f config/Migrations/20260422120002_CreateUserIdentities.php && php -l config/Migrations/20260422120002_CreateUserIdentities.php | grep -q 'No syntax errors' && grep -q 'class CreateUserIdentities extends AbstractMigration' config/Migrations/20260422120002_CreateUserIdentities.php && grep -q "public \$autoId = false" config/Migrations/20260422120002_CreateUserIdentities.php && grep -qE "addForeignKey.*'user_id'.*'users'" config/Migrations/20260422120002_CreateUserIdentities.php && grep -q "'delete'\s*=>\s*'CASCADE'" config/Migrations/20260422120002_CreateUserIdentities.php && grep -q 'fk_user_identities_user' config/Migrations/20260422120002_CreateUserIdentities.php && grep -qE "'provider',\s*'enum'" config/Migrations/20260422120002_CreateUserIdentities.php && grep -qE "'bluesky'.*'x'|'x'.*'bluesky'" config/Migrations/20260422120002_CreateUserIdentities.php && grep -q 'uk_user_identities_provider_account' config/Migrations/20260422120002_CreateUserIdentities.php && grep -q 'is_primary' config/Migrations/20260422120002_CreateUserIdentities.php && ! grep -q 'public function change' config/Migrations/20260422120002_CreateUserIdentities.php</automated>
  </verify>
  <acceptance_criteria>
    - File exists, `php -l` clean
    - Class `CreateUserIdentities extends AbstractMigration`, `$autoId = false`
    - FK `fk_user_identities_user` on `user_id` → `users` with `'delete' => 'CASCADE'`
    - `provider` column uses Phinx enum type with values `['bluesky', 'x']`
    - Composite UNIQUE index `uk_user_identities_provider_account` on (provider, provider_account_id)
    - UNIQUE index on (user_id) — D-11 per-user singleton
    - `is_primary` boolean column with default true
    - `access_token_enc` and `refresh_token_enc` columns present (nullable; Phase 2 populates)
    - `created_at` / `updated_at` follow A3 convention (datetime / timestamp)
    - No `change()` method
  </acceptance_criteria>
  <done>CreateUserIdentities migration written with FK CASCADE to users, provider ENUM, 1:1 user UNIQUE (D-11), token_enc columns stubbed, syntax clean.</done>
</task>

<task type="auto">
  <name>Task 4: Write CreateInboxes migration (20260422120003)</name>
  <read_first>
    - /tmp/DB-SCHEMA.md section on `inboxes` — read verbatim
    - .planning/phases/01-foundation-schema/01-RESEARCH.md sections: Architecture Patterns Pattern 5 (DECIMAL(4,3) for ssr_probability), Pattern 2 (FK)
    - .planning/phases/01-foundation-schema/01-PATTERNS.md — CreateInboxes row
  </read_first>
  <files>config/Migrations/20260422120003_CreateInboxes.php</files>
  <action>
  Write the third migration.

  Columns (verify each against DB-SCHEMA.md v0.2 §3):
  - `id` uuid NOT NULL PRIMARY KEY
  - `user_id` uuid NOT NULL  (FK to users.id)
  - `slug` VARCHAR(32) NOT NULL  (UNIQUE)
  - `display_name` VARCHAR(64) NOT NULL
  - `ssr_probability` DECIMAL(4,3) NOT NULL DEFAULT 0.100  (Phinx: `decimal`, `precision => 4`, `scale => 3`, `default => 0.100`, per Pattern 5)
  - `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
  - `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)

  Indexes:
  - UNIQUE `uk_inboxes_slug` on (slug)
  - Standard `idx_inboxes_user` on (user_id) — for `WHERE user_id = ?` dashboard queries

  FK: `fk_inboxes_user` on `user_id` → `users.id`, `ON DELETE CASCADE`.

  CHECK constraints (per DB-SCHEMA.md v0.2; typical predicates):
  - `inboxes_ssr_probability_range_check` — `ssr_probability >= 0 AND ssr_probability <= 1`
  - `inboxes_slug_format_check` — `slug REGEXP '^[a-z0-9-]{3,32}$'`  (MySQL 8.0 `REGEXP` operator; verify DB-SCHEMA.md v0.2 slug regex exactly — adjust char class/length range if it differs)
  - `inboxes_display_name_length_check` — `CHAR_LENGTH(display_name) BETWEEN 1 AND 64`

  Raw SQL example for REGEXP check:

      $this->execute(<<<SQL
      ALTER TABLE inboxes
        ADD CONSTRAINT inboxes_slug_format_check
        CHECK (slug REGEXP '^[a-z0-9-]{3,32}\$')
      SQL);

  (Note the `\$` to escape `$` inside PHP heredoc.)

  down(): drop table.
  </action>
  <verify>
    <automated>test -f config/Migrations/20260422120003_CreateInboxes.php && php -l config/Migrations/20260422120003_CreateInboxes.php | grep -q 'No syntax errors' && grep -q 'class CreateInboxes extends AbstractMigration' config/Migrations/20260422120003_CreateInboxes.php && grep -qE "'ssr_probability',\s*'decimal'" config/Migrations/20260422120003_CreateInboxes.php && grep -q "'precision' => 4" config/Migrations/20260422120003_CreateInboxes.php && grep -q "'scale' => 3" config/Migrations/20260422120003_CreateInboxes.php && grep -qE "'default'\s*=>\s*0\.1" config/Migrations/20260422120003_CreateInboxes.php && grep -qE "addForeignKey.*'user_id'.*'users'" config/Migrations/20260422120003_CreateInboxes.php && grep -q 'fk_inboxes_user' config/Migrations/20260422120003_CreateInboxes.php && grep -q 'uk_inboxes_slug' config/Migrations/20260422120003_CreateInboxes.php && grep -qE 'inboxes_ssr_probability_range_check' config/Migrations/20260422120003_CreateInboxes.php && grep -qE 'inboxes_slug_format_check' config/Migrations/20260422120003_CreateInboxes.php && ! grep -q 'public function change' config/Migrations/20260422120003_CreateInboxes.php</automated>
  </verify>
  <acceptance_criteria>
    - File exists, `php -l` clean
    - Class `CreateInboxes`, `$autoId = false`
    - `ssr_probability` column is `decimal(4,3)` with default `0.100`
    - FK `fk_inboxes_user` to users CASCADE
    - UNIQUE on `slug`
    - CHECK `inboxes_ssr_probability_range_check` present
    - CHECK `inboxes_slug_format_check` (REGEXP) present
    - CHECK on display_name length present (if DB-SCHEMA.md specifies it)
    - No `change()` method
  </acceptance_criteria>
  <done>CreateInboxes migration written with DECIMAL probability + slug UNIQUE + slug-regex + probability-range CHECKs.</done>
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
| T-01-07 | Tampering | user-supplied slug bypassing format rules (e.g. containing path-traversal fragments) | mitigate | `inboxes_slug_format_check` CHECK with MySQL REGEXP `^[a-z0-9-]{3,32}$` enforces format at DB layer; Phase 3 controller-side validation layers on top. |
| T-01-08 | Tampering | display_name overflow / empty-string | mitigate | `users_display_name_check` CHECK + `display_name_length_check` on inboxes enforce 1..64 at DB layer. |
| T-01-10 | Information Disclosure | CHECK constraints silently ignored on MySQL < 8.0.16 → invalid data persisted | mitigate | Task 1 validates MySQL version ≥ 8.0.16; Plan 02b final task tests CHECK enforcement end-to-end. |
| T-01-14 | Tampering | Migration applied to wrong DB (e.g. prod instead of dev) | accept | MVP has no automatic safeguard; developer responsibility. Phase 4 deploy plan will add an environment check. |
</threat_model>

<verification>
Sub-phase verification (after all 4 tasks in Plan 02a):

1. `ls config/Migrations/2026042212000{1,2,3}_Create*.php` → all 3 files exist
2. `for f in config/Migrations/2026042212000{1,2,3}_Create*.php; do php -l "$f" || exit 1; done` → all pass
3. `config/.env` exists with no placeholders
4. `mysql -Nse "SELECT VERSION();"` reports ≥ 8.0.16
5. `mysql tamabox -Nse "SHOW TABLES"` returns empty or phinxlog only (no stale tables; `bin/cake migrations migrate` still NOT run — that's Plan 02b's job)
</verification>

<success_criteria>
- [ ] config/.env populated with real secrets (no placeholders)
- [ ] MySQL 8.0.16+ verified on dev box
- [ ] tamabox + test_tamabox databases exist with utf8mb4_0900_ai_ci
- [ ] 3 migration files exist in config/Migrations/ with FK-order timestamps 20260422120001..003
- [ ] Every migration uses `public $autoId = false` + `public function up()` + `public function down()` (no `change()`)
- [ ] Every migration uses utf8mb4_0900_ai_ci collation and CHAR(36) UUID PK
- [ ] FK CASCADE on fk_user_identities_user and fk_inboxes_user
- [ ] ENUM `provider` ('bluesky','x') on user_identities
- [ ] DECIMAL(4,3) `ssr_probability` default 0.100 on inboxes
- [ ] CHECK constraints declared via `$this->execute()` raw SQL
</success_criteria>

<output>
After completion, create `.planning/phases/01-foundation-schema/01-02a-SUMMARY.md` containing:
- Final migration filename list (3 files) with timestamps
- Confirmation that config/.env is populated and MySQL 8.0.16+ verified
- Any deviations from DB-SCHEMA.md v0.2 discovered while writing the 3 files
- Handoff note to Plan 02b: "Root-tier migrations (users, user_identities, inboxes) ready on disk; `bin/cake migrations migrate` NOT yet run. Plan 02b adds messages/blocks/reports files then runs migrate + INFORMATION_SCHEMA verification."
</output>
