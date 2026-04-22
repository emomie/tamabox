---
phase: 01-foundation-schema
plan: 01
wave: 1
subsystem: infrastructure-hygiene
tags:
  - cakephp
  - composer
  - dotenv
  - httpoxy
  - infrastructure
requirements_closed:
  - INFRA-02
  - INFRA-03
  - INFRA-05
files_modified:
  - composer.json
  - composer.lock (new)
  - config/bootstrap.php
  - config/app.php
  - config/.env.example
  - .htaccess
commits:
  - 0afe20a feat(01-01): bump PHP to ^8.0 and restructure composer deps (Task 1)
  - fb41750 feat(01-01): regenerate composer.lock with CakePHP 4 compatible deps (Task 2)
  - c50e48a feat(01-01): activate dotenv loader in config/bootstrap.php (Task 3)
  - 995f3b2 feat(01-01): wire Security.serverSecret and activate DB URLs in .env.example (Task 4)
  - 79b891c feat(01-01): activate httpoxy mitigation in .htaccess (Task 5)
resolved_versions:
  cakephp/migrations: 3.9.0
  robmorgan/phinx: 0.13.4
  josegonzalez/dotenv: 4.0.0
  phpstan/phpstan: 1.12.33 (dev)
  cakedc/cakephp-phpstan: 2.1.0 (dev)
completed: 2026-04-22
---

# Phase 01 Plan 01: Infrastructure Hygiene — Summary

CakePHP 4.5 skeleton hardened for Lolipop PHP 8.0+ operation: `.env` loader active, httpoxy header stripped, `Security.serverSecret` wired via env, composer constraints bumped to `^8.0` with runtime `dotenv` dep and `phpstan`/`cakedc-phpstan` dev tooling. `composer.lock` committed for reproducible production installs. Closes INFRA-02 / INFRA-03 / INFRA-05.

## composer.json Diff (final state)

```json
"require": {
    "php": "^8.0",
    "cakephp/cakephp": "^4.5.0",
    "cakephp/migrations": "^3.7",
    "cakephp/plugin-installer": "^1.3",
    "josegonzalez/dotenv": "^4.0",
    "mobiledetect/mobiledetectlib": "^3.74"
},
"require-dev": {
    "cakedc/cakephp-phpstan": "^2.1",
    "cakephp/bake": "^2.8",
    "cakephp/cakephp-codesniffer": "^4.5",
    "cakephp/debug_kit": "^4.9",
    "phpstan/phpstan": "^1.10",
    "phpunit/phpunit": "^9.6"
},
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
```

## Acceptance Criteria per Task

### Task 1: composer.json edits

- [x] `php -r 'json_decode(...)'` validates (exits 0)
- [x] `require.php` equals `"^8.0"`
- [x] `require["josegonzalez/dotenv"]` present (`^4.0`)
- [x] `require-dev["josegonzalez/dotenv"]` absent
- [x] `require-dev["phpstan/phpstan"]` present (see deviation — final: `^1.10`)
- [x] `require-dev["cakedc/cakephp-phpstan"]` present (see deviation — final: `^2.1`)
- [x] scripts has phpcs, phpcs-fix, phpstan, test, check, cs-check, cs-fix

### Task 2: composer update + lock

- [x] `composer validate --no-check-publish` → "./composer.json is valid"
- [x] `composer.lock` exists at project root
- [x] `composer install --dry-run` → "Nothing to install, update or remove"
- [x] lock `packages`: josegonzalez/dotenv 4.0.0, cakephp/migrations 3.9.0, robmorgan/phinx 0.13.4
- [x] lock `packages-dev`: phpstan/phpstan 1.12.33, cakedc/cakephp-phpstan 2.1.0
- [x] `vendor/autoload.php` exists

### Task 3: .env loader in bootstrap.php

- [x] `php -l config/bootstrap.php` → "No syntax errors detected"
- [x] Exactly one active `if (!env('APP_NAME') && file_exists(CONFIG . '.env'))` line
- [x] Zero `// if (!env('APP_NAME')` comment lines remaining
- [x] `josegonzalez\Dotenv\Loader` reference present
- [x] `Configure::load('app'` appears after the dotenv loader (ordering preserved)

### Task 4: Security.serverSecret + .env.example

- [x] `php -l config/app.php` passes
- [x] `'serverSecret' => env('SERVER_SECRET')` present in Security array
- [x] `'salt' => env('SECURITY_SALT')` still present (not overwritten)
- [x] `__SERVER_SECRET__` placeholder in .env.example (1 occurrence)
- [x] `DATABASE_URL=mysql://tamabox:...` active + uncommented (1 occurrence)
- [x] `DATABASE_TEST_URL=mysql://tamabox:...` active + uncommented (1 occurrence)
- [x] `encoding=utf8mb4` in both URL lines (2 occurrences)
- [x] Zero `#export DATABASE_URL=` commented leftovers
- [x] Zero `my_app` user references remaining

### Task 5: httpoxy in .htaccess

- [x] `<IfModule mod_headers.c>` active at start of line
- [x] `    RequestHeader unset Proxy` active (4-space indent)
- [x] `</IfModule>` present
- [x] `#<IfModule mod_headers.c>` absent (commented version removed)
- [x] `<IfModule mod_rewrite.c>` still intact (Lolipop-critical webroot redirect)

### Phase-level verification

- [x] composer validate → valid
- [x] composer.lock + vendor/autoload.php exist
- [x] composer.json require.php = "^8.0"
- [x] `php -l` passes on bootstrap.php and app.php
- [x] httpoxy block active in .htaccess
- [x] dotenv loader active, no commented form remaining
- [x] serverSecret wired + SERVER_SECRET placeholder in .env.example

### End-to-end bootstrap smoke test

Ran `php -r 'require vendor/autoload.php; require config/bootstrap.php; ...'` with the real VPS-provisioned `config/.env` present (SERVER_SECRET, SECURITY_SALT, DATABASE_URL populated). Results:

- `Configure::read("debug")` → `true` (from `.env DEBUG=true`)
- `Security::getSalt()` → 64-char hex string (after Configure::consume → Security::setSalt)
- `Configure::read("Security.serverSecret")` → 64-char hex string (unconsumed, ready for Phase 2-3 services)
- `env("DATABASE_URL")` → `mysql://tamabox:<pass>@localhost/...` (resolved from `.env`)

This confirms the full wiring chain works: dotenv Loader → `putenv/toEnv/toServer` → `env()` helper → `Configure::load('app')` → `Security::setSalt`/`serverSecret`.

## Deviations from Plan

### 1. [Rule 3 — Blocking: Version Constraint Conflict] cakedc/cakephp-phpstan + phpstan/phpstan version adjustments

- **Found during:** Task 2 (`composer update --with-all-dependencies`)
- **Issue:** Plan/RESEARCH prescribed `cakedc/cakephp-phpstan: ^4.1` and `phpstan/phpstan: ^2.1`, but composer returned:
  > `cakedc/cakephp-phpstan[4.1.0, ..., 4.2.0] require cakephp/cakephp ^5.0 -> conflicts with root require (^4.5.0)`
- **Root cause:** Every 4.x release of `cakedc/cakephp-phpstan` on Packagist requires CakePHP `^5.0`. The CakePHP 4.x compatible line is `2.1.x` (requires `phpstan ^1.0`). Verified via `repo.packagist.org/p2/cakedc/cakephp-phpstan.json`:
  ```
  4.2.0 -> cakephp: ^5.0 phpstan: ^2.1.26 php: >=8.1
  4.0.0 -> cakephp: ^5.0 phpstan: ^2.0   php: >=8.1
  3.x.y -> cakephp: ^5.0 phpstan: ^1.x   php: >=8.1
  2.1.0 -> cakephp: ^4.0 phpstan: ^1.0   php: >=7.2   ← chosen
  ```
- **Fix:** In the same commit that produced `composer.lock`, downgraded composer.json constraints:
  - `cakedc/cakephp-phpstan: ^4.1` → `^2.1`
  - `phpstan/phpstan: ^2.1` → `^1.10`
- **Resolved versions:** `cakedc/cakephp-phpstan 2.1.0`, `phpstan/phpstan 1.12.33`
- **Impact:** No functional impact on Phase 1. PHPStan 1.12 supports PHP 8.3 and cakedc-phpstan 2.1 covers CakePHP 4.x Table/Component/Helper magic — exactly the coverage we need for level 8. When the project migrates to CakePHP 5 (deferred, not in the current roadmap), bump both back to the 4.x / 2.x lines.
- **Files modified:** composer.json (require-dev block)
- **Commit:** fb41750

### 2. No other deviations

Tasks 1 (composer.json other edits), 3 (bootstrap.php), 4 (app.php + .env.example), 5 (.htaccess) executed exactly as written in the plan.

## Authentication gates encountered

None. All work was filesystem edits + composer package resolution (no secrets / no logins required).

## Handoff note to Plan 02

- `config/.env` already exists on this VPS (600 perm, gitignored) with real SECURITY_SALT / SERVER_SECRET / DATABASE_URL / DATABASE_TEST_URL values. The VPS `tamabox` MySQL user and `tamabox` + `tamabox_test` databases are already provisioned.
- On other developer machines (future contributors), setup is:
  1. `cp config/.env.example config/.env`
  2. Fill in real values (generate SECURITY_SALT + SERVER_SECRET via `openssl rand -hex 32`; set DB credentials)
  3. `chmod 600 config/.env`
- Plan 02 (schema migrations) can proceed directly — `DATABASE_URL` already resolves through the loader. No need to uncomment anything in `app_local.php` or set env vars manually.
- `composer.lock` is committed so `composer install --no-dev` on Lolipop will produce identical deps.

## Known Stubs

None. This plan only modifies infrastructure config; no application code that could contain stubs.

## Threat Flags

No new threat surface beyond what's in the plan's `<threat_model>`. All mitigations applied:
- T-01-01 (httpoxy) → Task 5 `RequestHeader unset Proxy` active
- T-01-02 (.env leakage) → verified `.gitignore` line 4 excludes `/config/.env`; `.env.example` uses `__PLACEHOLDER__` pattern (no real secrets committed)
- T-01-03 (supply chain) → `composer.lock` pinning + Packagist HTTPS (accepted per plan)
- T-01-04 (SERVER_SECRET leakage) → no log/echo of `Security.serverSecret` in Phase 1 code; flag for Phase 3 review
- T-01-05 (PHP 8.4 deprecations) → accepted, Phase 4 concern
- T-01-06 (fail-fast on missing .env) → intentional, accepted

## Self-Check

**Commits:**
- FOUND: 0afe20a (Task 1)
- FOUND: fb41750 (Task 2)
- FOUND: c50e48a (Task 3)
- FOUND: 995f3b2 (Task 4)
- FOUND: 79b891c (Task 5)

**Files:**
- FOUND: composer.json (modified)
- FOUND: composer.lock (new, tracked)
- FOUND: config/bootstrap.php (modified)
- FOUND: config/app.php (modified)
- FOUND: config/.env.example (modified)
- FOUND: .htaccess (modified)
- FOUND: vendor/autoload.php (generated, gitignored)

**Verification:**
- FOUND: `composer validate` → valid
- FOUND: `composer install --dry-run` → "Nothing to install"
- FOUND: `php -l config/bootstrap.php` → no syntax errors
- FOUND: `php -l config/app.php` → no syntax errors
- FOUND: bootstrap smoke test → Security.salt + Security.serverSecret both resolve from .env

## Self-Check: PASSED
