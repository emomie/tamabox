---
phase: 01-foundation-schema
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - composer.json
  - composer.lock
  - config/bootstrap.php
  - config/app.php
  - config/.env.example
  - .htaccess
autonomous: true
requirements:
  - INFRA-02
  - INFRA-03
  - INFRA-05
tags:
  - cakephp
  - composer
  - infrastructure
  - dotenv
  - httpoxy

must_haves:
  truths:
    - "composer install succeeds on PHP 8.0+ with no version-constraint errors"
    - "composer install --no-dev succeeds (dotenv resolves in require, not require-dev)"
    - "config/.env is loaded on bootstrap so env('DATABASE_URL') / env('SERVER_SECRET') resolve"
    - "Apache strips the Proxy request header before PHP sees HTTP_PROXY (httpoxy blocked)"
    - "Configure::read('Security.serverSecret') returns the value from .env SERVER_SECRET"
    - "composer phpcs / composer phpstan / composer test scripts are invocable (phpstan require-dev installed)"
  artifacts:
    - path: "composer.json"
      provides: "PHP ^8.0 constraint, dotenv promoted to require, scripts (phpcs/phpcs-fix/phpstan/test/check), phpstan+cakedc deps"
      contains: '"php": "^8.0"'
    - path: "composer.lock"
      provides: "Resolved dependency graph pinned for reproducible production install"
      min_lines: 100
    - path: "config/bootstrap.php"
      provides: "Active dotenv loader block (no leading // on lines 63-69)"
    - path: "config/app.php"
      provides: "Security.serverSecret key wired to env('SERVER_SECRET')"
      contains: "serverSecret"
    - path: "config/.env.example"
      provides: "SERVER_SECRET placeholder + uncommented DATABASE_URL/DATABASE_TEST_URL with utf8mb4 encoding"
      contains: "SERVER_SECRET"
    - path: ".htaccess"
      provides: "Active httpoxy block (no leading # on IfModule mod_headers lines)"
      contains: "RequestHeader unset Proxy"
  key_links:
    - from: "config/bootstrap.php (dotenv loader)"
      to: "config/app.php (env() calls)"
      via: "ENV / SERVER / putenv populated BEFORE Configure::load('app')"
      pattern: "josegonzalez.Dotenv.Loader"
    - from: "config/app.php"
      to: "Configure::read('Security.serverSecret')"
      via: "Security array: salt + serverSecret via env('SERVER_SECRET')"
      pattern: "serverSecret.*env..SERVER_SECRET"
    - from: ".htaccess (IfModule mod_headers.c)"
      to: "Apache request pipeline"
      via: "RequestHeader unset Proxy directive applied before PHP SAPI"
      pattern: "RequestHeader unset Proxy"
---

<objective>
Phase 1 のインフラ衛生化: CakePHP 4.5 skeleton を Lolipop PHP 8.0+ 本番運用に向けて整え、`.env` ローダ有効化 / httpoxy 緩和 / server_secret 配線 / 開発ツール scripts 整備をまとめて適用する。

Purpose:
- 後続プラン (schema migrations / Table classes) が `bin/cake migrations migrate` を実行する前に `DATABASE_URL` が `.env` から解決される状態にする (RESEARCH.md "Anti-Pattern: Baking migration then running migrations migrate with DATABASE_URL unset")。
- 本番 `composer install --no-dev` で dotenv Loader が存在する状態にする (RESEARCH.md A5 / §Standard Stack)。
- INFRA-02 (.env) / INFRA-03 (PHP ^8.0) / INFRA-05 (httpoxy) の 3 要件をクローズする。

Output:
- 編集済み composer.json / config/bootstrap.php / config/app.php / config/.env.example / .htaccess
- 新規 composer.lock (git commit 済)
- composer phpcs / composer phpstan / composer test が起動可能
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md
@.planning/REQUIREMENTS.md
@.planning/phases/01-foundation-schema/01-CONTEXT.md
@.planning/phases/01-foundation-schema/01-RESEARCH.md
@.planning/phases/01-foundation-schema/01-PATTERNS.md
@.planning/codebase/STACK.md
@.planning/codebase/CONVENTIONS.md
@composer.json
@config/bootstrap.php
@config/app.php
@config/.env.example
@.htaccess
@.gitignore

<interfaces>
<!-- Key existing content the executor MUST use verbatim -->

Existing commented-out dotenv loader (config/bootstrap.php lines 63-69):

    // if (!env('APP_NAME') && file_exists(CONFIG . '.env')) {
    //     $dotenv = new \josegonzalez\Dotenv\Loader([CONFIG . '.env']);
    //     $dotenv->parse()
    //         ->putenv()
    //         ->toEnv()
    //         ->toServer();
    // }

Existing commented-out httpoxy block (.htaccess lines 1-5):

    # Uncomment the following to prevent the httpoxy vulnerability
    # See: https://httpoxy.org/
    #<IfModule mod_headers.c>
    #    RequestHeader unset Proxy
    #</IfModule>

Existing Security block (config/app.php around line 77-79):

    'Security' => [
        'salt' => env('SECURITY_SALT'),
    ],

Existing require block (composer.json lines 7-13):

    "require": {
        "php": ">=7.4",
        "cakephp/cakephp": "^4.5.0",
        "cakephp/migrations": "^3.7",
        "cakephp/plugin-installer": "^1.3",
        "mobiledetect/mobiledetectlib": "^3.74"
    },

Existing require-dev (composer.json lines 14-20):

    "require-dev": {
        "cakephp/bake": "^2.8",
        "cakephp/cakephp-codesniffer": "^4.5",
        "cakephp/debug_kit": "^4.9",
        "josegonzalez/dotenv": "^4.0",
        "phpunit/phpunit": "^9.6"
    },

Existing scripts block (composer.json lines 38-48):

    "scripts": {
        "post-install-cmd": "App\\Console\\Installer::postInstall",
        "post-create-project-cmd": "App\\Console\\Installer::postInstall",
        "check": [
            "@test",
            "@cs-check"
        ],
        "cs-check": "phpcs --colors -p",
        "cs-fix": "phpcbf --colors -p",
        "test": "phpunit --colors=always"
    }

Existing `.gitignore` line 4: `/config/.env` (already ignored; `.env.example` is tracked).
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Edit composer.json — bump PHP to ^8.0, promote dotenv to require, add dev tooling, extend scripts</name>
  <read_first>
    - composer.json (entire file; note sort-packages: true, 4-space indent, existing block order)
    - .planning/phases/01-foundation-schema/01-RESEARCH.md sections: Standard Stack, Installation (Phase 1 composer commands), Assumption A5
    - .planning/phases/01-foundation-schema/01-PATTERNS.md section: composer.json
  </read_first>
  <files>composer.json</files>
  <action>
  Apply FOUR edits to composer.json. Preserve 4-space indentation, alphabetical package ordering within each require/require-dev block, and existing trailing-comma/brace style. Do NOT touch unrelated blocks (autoload, autoload-dev, config, minimum-stability, prefer-stable, keywords, homepage, type).

  Edit 1 — in "require" block (implements D-01 + A5 dotenv promotion):
  Change `"php": ">=7.4"` to `"php": "^8.0"`. Add `"josegonzalez/dotenv": "^4.0"` alphabetically between `cakephp/plugin-installer` and `mobiledetect/mobiledetectlib`. Final "require" block must read:

      "require": {
          "php": "^8.0",
          "cakephp/cakephp": "^4.5.0",
          "cakephp/migrations": "^3.7",
          "cakephp/plugin-installer": "^1.3",
          "josegonzalez/dotenv": "^4.0",
          "mobiledetect/mobiledetectlib": "^3.74"
      },

  Edit 2 — in "require-dev" block (implements A5 removal + D-13 phpstan install):
  Remove the `"josegonzalez/dotenv": "^4.0"` line (moved to require). Add `"cakedc/cakephp-phpstan": "^4.1"` alphabetically before `cakephp/bake`. Add `"phpstan/phpstan": "^2.1"` alphabetically before `phpunit/phpunit`. Final "require-dev" block:

      "require-dev": {
          "cakedc/cakephp-phpstan": "^4.1",
          "cakephp/bake": "^2.8",
          "cakephp/cakephp-codesniffer": "^4.5",
          "cakephp/debug_kit": "^4.9",
          "phpstan/phpstan": "^2.1",
          "phpunit/phpunit": "^9.6"
      },

  Edit 3 — replace the "scripts" block entirely with the expanded version (implements D-13). Keep `cs-check` / `cs-fix` as aliases so old muscle memory still works:

      "scripts": {
          "post-install-cmd": "App\\Console\\Installer::postInstall",
          "post-create-project-cmd": "App\\Console\\Installer::postInstall",
          "check": [
              "@phpcs",
              "@phpstan",
              "@test"
          ],
          "cs-check": "@phpcs",
          "cs-fix": "@phpcs-fix",
          "phpcs": "phpcs --colors -p",
          "phpcs-fix": "phpcbf --colors -p",
          "phpstan": "phpstan analyse --no-progress",
          "test": "phpunit --colors=always"
      }

  Edit 4 — no change anywhere else. Do NOT modify `minimum-stability`, `prefer-stable`, autoload, or any other top-level key.

  After editing, validate JSON syntax via: `php -r 'json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR); echo "OK\n";'` — must print OK.
  </action>
  <verify>
    <automated>php -r 'json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR); echo "OK\n";' | grep -q '^OK$' && grep -q '"php": "\^8.0"' composer.json && php -r '$j=json_decode(file_get_contents("composer.json"),true); exit(isset($j["require"]["josegonzalez/dotenv"])?0:1);' && php -r '$j=json_decode(file_get_contents("composer.json"),true); exit(isset($j["require-dev"]["josegonzalez/dotenv"])?1:0);' && php -r '$j=json_decode(file_get_contents("composer.json"),true); exit(isset($j["require-dev"]["phpstan/phpstan"]) && isset($j["require-dev"]["cakedc/cakephp-phpstan"])?0:1);' && php -r '$j=json_decode(file_get_contents("composer.json"),true); $s=$j["scripts"]; exit(isset($s["phpcs"],$s["phpstan"],$s["test"],$s["check"])?0:1);'</automated>
  </verify>
  <acceptance_criteria>
    - `php -r 'json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR);'` exits 0
    - composer.json `require.php` equals `"^8.0"`
    - composer.json `require["josegonzalez/dotenv"]` is present (runtime)
    - composer.json `require-dev["josegonzalez/dotenv"]` is ABSENT (not duplicated)
    - composer.json `require-dev["phpstan/phpstan"]` equals `"^2.1"`
    - composer.json `require-dev["cakedc/cakephp-phpstan"]` equals `"^4.1"`
    - composer.json `scripts` has keys: `phpcs`, `phpcs-fix`, `phpstan`, `test`, `check`, `cs-check`, `cs-fix`
  </acceptance_criteria>
  <done>composer.json validates as JSON, requires PHP ^8.0, dotenv is in require (runtime), phpstan+cakedc-phpstan are in require-dev, scripts block exposes phpcs/phpcs-fix/phpstan/test/check plus cs-check/cs-fix aliases.</done>
</task>

<task type="auto">
  <name>Task 2: Run composer update to regenerate composer.lock with new constraints (D-04)</name>
  <read_first>
    - composer.json (post-Task-1 state)
    - .planning/phases/01-foundation-schema/01-RESEARCH.md section: Common Pitfalls, Pitfall 7 (composer update without --lock flag)
    - .planning/phases/01-foundation-schema/01-RESEARCH.md section: Installation
  </read_first>
  <files>composer.lock, vendor/ (generated side effect)</files>
  <action>
  Regenerate composer.lock reflecting the Task 1 edits. Run from project root (/home/claude/projects/tamabox).

  Execute in order; each command must exit 0:

  1. `composer validate --no-check-publish` — sanity check composer.json before install.

  2. `composer update --with-all-dependencies 2>&1 | tail -80` — full resolve against new constraints. `--with-all-dependencies` is required because changing the PHP constraint can affect transitive packages. This creates composer.lock (currently absent) and populates vendor/.

     Expected resolved versions (post-run): cakephp/migrations 3.9.x, robmorgan/phinx 0.13.x, josegonzalez/dotenv 4.0.x, phpstan/phpstan 2.1.x, cakedc/cakephp-phpstan 4.1.x.

  3. `composer install --dry-run 2>&1 | tail -20` — must report "Nothing to install, update or remove" (proves lock is consistent with json).

  Failure recovery:
  - If composer update errors with "Your requirements could not be resolved": DO NOT hand-edit composer.lock. Read the error, cross-reference RESEARCH §Standard Stack, adjust composer.json (typical fix: bump phpstan/phpstan to ^2.1.26 because cakedc/cakephp-phpstan 4.1 requires that minimum), re-run Task 1 verify, then re-run this task.

  Do NOT commit vendor/ (already gitignored). composer.lock IS committed (part of files_modified).
  </action>
  <verify>
    <automated>composer validate --no-check-publish 2>&1 | grep -qE '(is valid|./composer.json is valid)' && test -f composer.lock && composer install --dry-run 2>&1 | grep -qE '(Nothing to install|Nothing to modify)' && php -r '$l=json_decode(file_get_contents("composer.lock"),true); $names=array_column($l["packages"],"name"); $devnames=array_column($l["packages-dev"]??[],"name"); $ok=in_array("josegonzalez/dotenv",$names)&&in_array("phpstan/phpstan",$devnames)&&in_array("cakephp/migrations",$names)&&in_array("robmorgan/phinx",$names); echo $ok?"LOCK_OK\n":"LOCK_MISS\n";' | grep -q '^LOCK_OK$' && test -f vendor/autoload.php</automated>
  </verify>
  <acceptance_criteria>
    - `composer validate --no-check-publish` exits 0 with "is valid"
    - `composer.lock` file exists at project root
    - `composer install --dry-run` reports "Nothing to install" (or "Nothing to modify")
    - composer.lock `packages` array contains `josegonzalez/dotenv`, `cakephp/migrations`, `robmorgan/phinx`
    - composer.lock `packages-dev` array contains `phpstan/phpstan`, `cakedc/cakephp-phpstan`
    - `vendor/autoload.php` exists (proves install ran)
  </acceptance_criteria>
  <done>composer.lock committed to tree, vendor/ populated, dotenv in runtime packages, phpstan in dev packages, lock and json are in sync.</done>
</task>

<task type="auto">
  <name>Task 3: Uncomment .env loader in config/bootstrap.php (D-02 / INFRA-02)</name>
  <read_first>
    - config/bootstrap.php (entire file; locate the 7-line commented block by content match on "josegonzalez" — line numbers may drift slightly)
    - .planning/phases/01-foundation-schema/01-PATTERNS.md section: config/bootstrap.php
    - .planning/phases/01-foundation-schema/01-RESEARCH.md sections: Code Examples (Uncommenting .env loader), Pitfall 2 (variables_order)
  </read_first>
  <files>config/bootstrap.php</files>
  <action>
  Locate the 7-line commented block in config/bootstrap.php that starts with `// if (!env('APP_NAME') && file_exists(CONFIG . '.env'))` and ends with `// }`. Remove the leading `// ` (slash-slash-space, 3 characters) from each of those 7 lines. Preserve all indentation within the lines.

  BEFORE:

      // if (!env('APP_NAME') && file_exists(CONFIG . '.env')) {
      //     $dotenv = new \josegonzalez\Dotenv\Loader([CONFIG . '.env']);
      //     $dotenv->parse()
      //         ->putenv()
      //         ->toEnv()
      //         ->toServer();
      // }

  AFTER:

      if (!env('APP_NAME') && file_exists(CONFIG . '.env')) {
          $dotenv = new \josegonzalez\Dotenv\Loader([CONFIG . '.env']);
          $dotenv->parse()
              ->putenv()
              ->toEnv()
              ->toServer();
      }

  Constraints:
  - Do NOT modify any other lines in bootstrap.php. In particular the `Configure::config(...)` / `Configure::load('app', ...)` calls below this block MUST stay in their current order (they consume env vars set by the loader).
  - Do NOT add try/catch around the block (Phase 1 accepts fail-fast on missing .env).
  - Do NOT touch the surrounding comment lines if any.

  Verify with `php -l config/bootstrap.php` — must print "No syntax errors detected".
  </action>
  <verify>
    <automated>php -l config/bootstrap.php | grep -q 'No syntax errors' && grep -Pq "^if \(\!env\('APP_NAME'\) && file_exists\(CONFIG \. '\.env'\)\) \{" config/bootstrap.php && grep -q 'josegonzalez\\Dotenv\\Loader' config/bootstrap.php && ! grep -Pq "^// if \(\!env\('APP_NAME'\)" config/bootstrap.php && awk '/josegonzalez\\Dotenv\\Loader/{d=NR} /Configure::load..app/{c=NR} END{ if (d && c && c>d) print "OK"; else print "BAD" }' config/bootstrap.php | grep -q '^OK$'</automated>
  </verify>
  <acceptance_criteria>
    - `php -l config/bootstrap.php` reports "No syntax errors detected"
    - Exactly one line matches `^if (!env('APP_NAME') && file_exists(CONFIG . '.env')) {` (no leading //)
    - Zero lines match `^// if (!env('APP_NAME')` (old commented form removed)
    - At least one line contains `josegonzalez\Dotenv\Loader`
    - The `Configure::load('app'` call appears on a later line than the dotenv loader (ordering preserved)
  </acceptance_criteria>
  <done>Dotenv loader block is active, PHP syntax valid, Configure::load still runs after the loader, env('DATABASE_URL') inside config/app.php will resolve from config/.env on boot.</done>
</task>

<task type="auto">
  <name>Task 4: Wire Security.serverSecret in config/app.php and extend config/.env.example (D-05)</name>
  <read_first>
    - config/app.php (locate the Security array — around line 77-79, match by content pattern `'Security' => [` followed by `'salt' => env('SECURITY_SALT')`)
    - config/.env.example (current content; note the `__SALT__` placeholder convention and the commented DATABASE_URL block around lines 32-34)
    - .planning/phases/01-foundation-schema/01-RESEARCH.md sections: Code Examples (config/.env.example additions), Code Examples (config/app.php SERVER_SECRET wiring)
    - .planning/phases/01-foundation-schema/01-PATTERNS.md section: config/.env.example
  </read_first>
  <files>config/app.php, config/.env.example</files>
  <action>
  Two file edits.

  EDIT A — config/app.php (add serverSecret key to the Security array):

  Locate the existing Security block. Current content:

      'Security' => [
          'salt' => env('SECURITY_SALT'),
      ],

  Add one line `'serverSecret' => env('SERVER_SECRET'),` immediately after the `'salt'` line, matching the existing 4-space interior indent:

      'Security' => [
          'salt' => env('SECURITY_SALT'),
          'serverSecret' => env('SERVER_SECRET'),
      ],

  Do NOT add any other keys. Do NOT touch any other block in config/app.php.

  EDIT B — config/.env.example (append SERVER_SECRET, uncomment DATABASE_URL / DATABASE_TEST_URL, change encoding=utf8 → utf8mb4, change user my_app → tamabox):

  B1. Immediately after the existing `export SECURITY_SALT="__SALT__"` line, insert a blank line then add three lines:

      # SSR server secret — 32-byte hex string. Generate via: openssl rand -hex 32
      # Read by Configure::read('Security.serverSecret'); consumed by Phase 3 SSR seed generation.
      export SERVER_SECRET="__SERVER_SECRET__"

  Placeholder `__SERVER_SECRET__` matches the `__SALT__` convention (Open Question 4 resolution per RESEARCH.md).

  B2. Replace the 3-line DATABASE section (current: 1 comment line + 2 commented export lines with `encoding=utf8` and user `my_app`) with the uncommented, utf8mb4, tamabox-user version:

  BEFORE:

      # Uncomment these to define database configuration via environment variables.
      #export DATABASE_URL="mysql://my_app:secret@localhost/${APP_NAME}?encoding=utf8&timezone=UTC&cacheMetadata=true&quoteIdentifiers=false&persistent=false"
      #export DATABASE_TEST_URL="mysql://my_app:secret@localhost/test_${APP_NAME}?encoding=utf8&timezone=UTC&cacheMetadata=true&quoteIdentifiers=false&persistent=false"

  AFTER:

      # Database connection — update credentials per local/prod environment.
      export DATABASE_URL="mysql://tamabox:secret@localhost/${APP_NAME}?encoding=utf8mb4&timezone=UTC&cacheMetadata=true&quoteIdentifiers=false&persistent=false"
      export DATABASE_TEST_URL="mysql://tamabox:secret@localhost/test_${APP_NAME}?encoding=utf8mb4&timezone=UTC&cacheMetadata=true&quoteIdentifiers=false&persistent=false"

  Preserve all other content in .env.example unchanged (APP_NAME, EMAIL_TRANSPORT_DEFAULT_URL, CACHE_*_URL, LOG_*_URL, DEBUG_KIT_*, etc.).

  Verify: `php -l config/app.php` prints "No syntax errors detected".
  </action>
  <verify>
    <automated>php -l config/app.php | grep -q 'No syntax errors' && grep -Fq "'serverSecret' => env('SERVER_SECRET')" config/app.php && grep -Fq "'salt' => env('SECURITY_SALT')" config/app.php && grep -q 'SERVER_SECRET="__SERVER_SECRET__"' config/.env.example && grep -cE '^export DATABASE_URL="mysql://tamabox:' config/.env.example | grep -q '^1$' && grep -cE '^export DATABASE_TEST_URL="mysql://tamabox:' config/.env.example | grep -q '^1$' && grep -cE 'encoding=utf8mb4' config/.env.example | grep -qE '^[2-9]$' && grep -cE '^#export DATABASE_URL=' config/.env.example | grep -q '^0$' && grep -cE 'my_app' config/.env.example | grep -q '^0$'</automated>
  </verify>
  <acceptance_criteria>
    - `php -l config/app.php` reports "No syntax errors detected"
    - `grep "'serverSecret' => env('SERVER_SECRET')" config/app.php` returns the line
    - `grep "'salt' => env('SECURITY_SALT')" config/app.php` STILL returns its line (not overwritten)
    - `grep '__SERVER_SECRET__' config/.env.example` returns exactly 1 line
    - `grep -c '^export DATABASE_URL="mysql://tamabox:' config/.env.example` equals 1 (uncommented, tamabox user)
    - `grep -c '^#export DATABASE_URL=' config/.env.example` equals 0 (no commented leftover)
    - `grep -c 'encoding=utf8mb4' config/.env.example` ≥ 2 (both URL lines)
    - `grep -c 'my_app' config/.env.example` equals 0 (all replaced with tamabox)
  </acceptance_criteria>
  <done>Security.serverSecret wired via env(); .env.example has SERVER_SECRET placeholder, active tamabox-user DATABASE_URL / DATABASE_TEST_URL with utf8mb4.</done>
</task>

<task type="auto">
  <name>Task 5: Uncomment httpoxy block in .htaccess (D-03 / INFRA-05)</name>
  <read_first>
    - .htaccess (root file, NOT webroot/.htaccess — read the entire ~15-20 line file)
    - .planning/phases/01-foundation-schema/01-RESEARCH.md section: Code Examples (httpoxy mitigation)
    - .planning/phases/01-foundation-schema/01-PATTERNS.md section: .htaccess
  </read_first>
  <files>.htaccess</files>
  <action>
  Edit the ROOT `.htaccess` (NOT `webroot/.htaccess`). Replace the 5-line commented block at the top with an active version.

  BEFORE (lines 1-5):

      # Uncomment the following to prevent the httpoxy vulnerability
      # See: https://httpoxy.org/
      #<IfModule mod_headers.c>
      #    RequestHeader unset Proxy
      #</IfModule>

  AFTER:

      # Prevent the httpoxy vulnerability (CVE-2016-5385).
      # See: https://httpoxy.org/
      <IfModule mod_headers.c>
          RequestHeader unset Proxy
      </IfModule>

  Constraints:
  - Do NOT touch lines 7+ (`<IfModule mod_rewrite.c>` block that redirects root to webroot/). That block is Lolipop-critical.
  - Keep both comment lines as documentation (rewrite the first to "Prevent the httpoxy vulnerability (CVE-2016-5385)." for clarity per RESEARCH §Code Examples).
  - Preserve 4-space indent inside the IfModule block.

  No Apache syntax checker is available in the execution environment. Rely on grep-based acceptance below; full runtime validation happens when Wave 2 boots `bin/cake` (bin/cake does not use Apache so httpoxy regression, if any, would only surface in Phase 2+ web boot — note this limitation).
  </action>
  <verify>
    <automated>grep -qE '^<IfModule mod_headers\.c>' .htaccess && grep -qE '^    RequestHeader unset Proxy$' .htaccess && grep -qE '^</IfModule>' .htaccess && ! grep -qE '^#<IfModule mod_headers\.c>' .htaccess && ! grep -qE '^#    RequestHeader unset Proxy' .htaccess && grep -qE '^<IfModule mod_rewrite\.c>' .htaccess</automated>
  </verify>
  <acceptance_criteria>
    - `grep '^<IfModule mod_headers.c>' .htaccess` returns the active line
    - `grep '^    RequestHeader unset Proxy$' .htaccess` returns the active line
    - `grep '^</IfModule>' .htaccess` returns at least one close tag
    - `grep '^#<IfModule mod_headers.c>' .htaccess` returns nothing (commented version removed)
    - `grep '^<IfModule mod_rewrite.c>' .htaccess` still returns the line (mod_rewrite block intact)
  </acceptance_criteria>
  <done>httpoxy block is active in root .htaccess, mod_rewrite block untouched. Apache (when running in Phase 2+) will strip the Proxy header from incoming requests.</done>
</task>

</tasks>

<threat_model>
## Trust Boundaries

| Boundary | Description |
|----------|-------------|
| Apache → PHP SAPI | Attacker-controlled `Proxy` request header could set `HTTP_PROXY` env, redirecting outbound HTTP calls via SSRF. httpoxy CVE-2016-5385. |
| Filesystem → bootstrap | `config/.env` is read at bootstrap; if an attacker writes to `config/` they can inject secrets or overwrite existing ones. |
| Composer registry → vendor/ | Newly added deps (phpstan, cakedc, josegonzalez/dotenv) arrive from Packagist; supply-chain compromise is possible. |

## STRIDE Threat Register

| Threat ID | Category | Component | Disposition | Mitigation Plan |
|-----------|----------|-----------|-------------|-----------------|
| T-01-01 | Tampering | Incoming HTTP `Proxy` header | mitigate | `RequestHeader unset Proxy` in .htaccess (Task 5) — Apache strips header before PHP sees it, closing httpoxy (CVE-2016-5385). |
| T-01-02 | Information Disclosure | config/.env committed by accident | mitigate | Existing `.gitignore` line 4 already excludes `/config/.env`; `.env.example` uses `__PLACEHOLDER__` pattern so accidental commit of real secrets is visually obvious. (Verification only — no change needed; part of Task 4 acceptance.) |
| T-01-03 | Tampering | Supply chain (newly added phpstan, cakedc, josegonzalez/dotenv) | accept | composer.lock pinning (Task 2) fixes transitive versions. Packagist HTTPS + integrity hashes in lock file bound trust. Acceptable for MVP. |
| T-01-04 | Elevation of Privilege | `SERVER_SECRET` leaking to logs | mitigate | `Configure::read('Security.serverSecret')` only; no echo/log call in Phase 1 ever dereferences it. Phase 3 SSR seed code will follow the same rule; flag as Phase 3 review item. |
| T-01-05 | Information Disclosure | PHP deprecations leaking paths on PHP 8.4 | accept | RESEARCH §Pitfall 8 — Phase 4 will pin Lolipop PHP to 8.1/8.2. Not a Phase 1 blocker (dev only). |
| T-01-06 | Denial of Service | dotenv load throws on missing .env in production | accept | Fail-fast is intentional (better than silent-default-secret). Phase 4 deploy doc will instruct setting `.env` before activating the site. |
</threat_model>

<verification>
Final phase-level checks (run from /home/claude/projects/tamabox after all 5 tasks):

1. `composer validate --no-check-publish` → "is valid"
2. `test -f composer.lock && test -f vendor/autoload.php` → exit 0
3. `php -r '$j=json_decode(file_get_contents("composer.json"),true); exit($j["require"]["php"]==="^8.0"?0:1);'` → exit 0
4. `php -l config/bootstrap.php && php -l config/app.php` → "No syntax errors detected" on both
5. `grep -q '^<IfModule mod_headers.c>' .htaccess && grep -q 'RequestHeader unset Proxy' .htaccess` → exit 0
6. `grep -q 'josegonzalez.Dotenv.Loader' config/bootstrap.php && ! grep -q '^// if (!env..APP_NAME' config/bootstrap.php` → exit 0
7. `grep -q "'serverSecret' => env" config/app.php && grep -q 'SERVER_SECRET="__SERVER_SECRET__"' config/.env.example` → exit 0

No DB or web boot required in this plan — Wave 2 performs the first real `bin/cake` invocation.
</verification>

<success_criteria>
This plan is done when all of the following are true:

- [ ] composer.json requires PHP ^8.0 (INFRA-03)
- [ ] composer.lock exists and is in sync with composer.json
- [ ] `composer install --no-dev` conceptually succeeds (dotenv in require, not require-dev) — verified via lock inspection
- [ ] config/bootstrap.php has the dotenv loader block active (no leading //) (INFRA-02)
- [ ] config/.env.example contains SERVER_SECRET placeholder and active DATABASE_URL/DATABASE_TEST_URL with utf8mb4 + tamabox user
- [ ] config/app.php Security array includes `serverSecret => env('SERVER_SECRET')`
- [ ] .htaccess has active httpoxy IfModule block (INFRA-05)
- [ ] composer scripts phpcs/phpcs-fix/phpstan/test/check (plus cs-check/cs-fix aliases) are all present
- [ ] `php -l` passes on bootstrap.php and app.php
</success_criteria>

<output>
After completion, create `.planning/phases/01-foundation-schema/01-01-SUMMARY.md` documenting:
- Exact final composer.json diff (require/require-dev/scripts sections)
- Resolved versions from composer.lock for: cakephp/migrations, robmorgan/phinx, josegonzalez/dotenv, phpstan/phpstan, cakedc/cakephp-phpstan
- Confirmation that .env loader is active and Configure::read('Security.serverSecret') wiring is in place
- Any deviations from plan (e.g. if phpstan version was bumped to ^2.1.26 for cakedc compatibility)
- Handoff note to Plan 02: "DATABASE_URL will resolve via .env once developer copies .env.example → .env and fills credentials; Plan 02 Task 1 starts with that setup step."
</output>
