# Technology Stack

**Analysis Date:** 2026-04-22

## Languages

**Primary:**
- PHP `>=7.4` (production target PHP 8.0+ per `README.md` — ロリポップ shared hosting) — used across all application code under `src/`, `config/`, `tests/`, `webroot/`

**Secondary:**
- SQL (MySQL 8.0 dialect) — raw schema files in `config/schema/i18n.sql`, `config/schema/sessions.sql`
- HTML/CSS — under `templates/` and `webroot/css/` (Milligram + normalize + custom `home.css`)
- Apache `mod_rewrite` configuration — `.htaccess` and `webroot/.htaccess`

## Runtime

**Environment:**
- PHP `>=7.4` (composer `require.php`), with these PHP extensions required (`config/requirements.php`):
  - `intl` (with ICU `>= 50.1`)
  - `mbstring`
- Apache with `mod_rewrite` (for pretty URLs — the top-level `.htaccess` rewrites all requests into `webroot/`)
- Production host: ロリポップ shared rental server (per `README.md`)

**Package Manager:**
- Composer — `composer.json` declares PSR-4 autoload under `App\` → `src/`
- Lockfile: missing — no `composer.lock` committed at repo root (vendor not yet installed in this checkout; `vendor/.gitkeep` only)

## Frameworks

**Core:**
- CakePHP `^4.5.0` (`cakephp/cakephp`) — full-stack MVC framework. Entry point `webroot/index.php` → `App\Application` (`src/Application.php`)
- Middleware stack (`src/Application.php:75-105`): `ErrorHandlerMiddleware`, `AssetMiddleware`, `RoutingMiddleware`, `BodyParserMiddleware`, `CsrfProtectionMiddleware`
- ORM: CakePHP built-in ORM (`Cake\Database\Driver\Mysql` configured in `config/app.php:297`)
- Router uses `DashedRoute` class (`config/routes.php:50`)

**Testing:**
- PHPUnit `^9.6` (`require-dev`) — config `phpunit.xml.dist`, bootstrap `tests/bootstrap.php`
- CakePHP TestSuite with `Cake\TestSuite\Fixture\PHPUnitExtension` (loaded via `phpunit.xml.dist:22-24`)
- Test suite directory: `tests/TestCase/` (only `ApplicationTest.php` + empty `Controller/`, `Model/`, `View/` subdirs so far)

**Build/Dev:**
- CakePHP Migrations `^3.7` (`cakephp/migrations`) — `bin/cake migrations migrate` is the schema workflow
- CakePHP Bake `^2.8` (`cakephp/bake`, dev) — scaffolding, loaded only in CLI via `Application::bootstrapCli()` (`src/Application.php:125-132`)
- DebugKit `^4.9` (`cakephp/debug_kit`, dev) — loaded only when `Configure::read('debug')` is true (`src/Application.php:62-64`)
- `josegonzalez/dotenv` `^4.0` (dev) — `.env` loader (block is commented out in `config/bootstrap.php:63-69`; `.env` loading is currently NOT active)
- `cakephp/plugin-installer` `^1.3` — composer plugin that installs CakePHP plugins into `plugins/` (currently empty, `plugins/.gitkeep` only)

## Key Dependencies

**Critical:**
- `cakephp/cakephp` `^4.5.0` — application framework (controllers, ORM, routing, middleware, auth plumbing)
- `cakephp/migrations` `^3.7` — schema migrations (baseline: no migration files present yet under `config/`)
- `mobiledetect/mobiledetectlib` `^3.74` — mobile/tablet detection, wired as request detectors in `config/bootstrap.php:179-188` (`$request->is('mobile')`, `$request->is('tablet')`)

**Infrastructure:**
- `cakephp/plugin-installer` `^1.3` — CakePHP plugin wiring
- `cakephp/cakephp-codesniffer` `^4.5` (dev) — coding standard ruleset referenced by `phpcs.xml`

## Configuration

**Environment:**
- Primary env var loader: CakePHP `env()` helper, reading PHP `getenv()` / `$_SERVER` / `$_ENV`
- `.env` file support exists (template at `config/.env.example`) but the dotenv loader in `config/bootstrap.php:63-69` is commented out — env vars must come from the web server / CLI shell unless that block is uncommented
- Local override file: `config/app_local.php` (gitignored), loaded by `config/bootstrap.php:90-92` if present
- Example local config: `config/app_local.example.php`

**Environment variables consumed** (from `config/app.php` + `config/.env.example`):
  - `DEBUG` (bool)
  - `APP_NAME`, `APP_ENCODING` (default `UTF-8`), `APP_DEFAULT_LOCALE` (default `en_US`), `APP_DEFAULT_TIMEZONE` (default `UTC`)
  - `SECURITY_SALT` (required — used as encryption key, `config/app.php:78`)
  - `DATABASE_URL`, `DATABASE_TEST_URL` (DSN form, MySQL driver)
  - `CACHE_DEFAULT_URL`, `CACHE_CAKECORE_URL`, `CACHE_CAKEMODEL_URL`, `CACHE_CAKEROUTES_URL`
  - `EMAIL_TRANSPORT_DEFAULT_URL`
  - `LOG_DEBUG_URL`, `LOG_ERROR_URL`, `LOG_QUERIES_URL`
  - `DEBUG_KIT_FORCE_ENABLE`, `DEBUG_KIT_SAFE_TLD`, `DEBUG_KIT_IGNORE_AUTHORIZATION`

**OAuth key material** (per `README.md` setup + `.gitignore`):
  - `config/keys/private.key`, `config/keys/public.key` — ES256 EC keypair (prime256v1) generated with `openssl ecparam`/`openssl ec`
  - Directory `config/keys/` is committed (holds `.gitkeep`); `*.key` / `*.pem` files ignored by `.gitignore:10-11`

**Build:**
- `composer.json` — scripts: `composer check`, `composer cs-check` (`phpcs --colors -p`), `composer cs-fix` (`phpcbf`), `composer test` (`phpunit --colors=always`)
- `phpcs.xml` — references `CakePHP` ruleset, scans `src/` + `tests/`
- `phpstan.neon` — level 8, scans `src/`, excludes `src/Console/Installer.php`
- `phpunit.xml.dist` — bootstrap `tests/bootstrap.php`, whitelists `src/` and `plugins/*/src/`
- `.editorconfig` — LF line endings, 4-space indent for PHP, 2-space for YAML
- `.gitattributes` — enforces LF eol for text; explicit `eol=lf` pinned on `*.pem` files

## Platform Requirements

**Development:**
- PHP 7.4+ with `intl` (ICU ≥ 50.1) and `mbstring` extensions (`config/requirements.php`)
- Composer
- MySQL 8.0 (per `README.md` — tested locally; `config/app.php:297` wires the `Cake\Database\Driver\Mysql` driver)
- `openssl` CLI for generating the OAuth ES256 keypair
- Optional: built-in dev server via `bin/cake server` (per `README.md`)

**Production:**
- ロリポップ shared rental server (shared Apache + MySQL 8.0)
- Apache with `mod_rewrite` enabled (required by both root and webroot `.htaccess`)
- Deployment: ロリポップ Git deploy triggered by `main` branch push (README "デプロイ" section — not yet wired)
- Only `webroot/` is web-exposed on ロリポップ; `config/`, `src/`, `tmp/`, etc. live above the document root
- PHP 8.0+ expected in production per `README.md:50`

**CI:**
- GitHub Actions workflows directory is absent (no `.github/workflows/`); `.github/` only contains `ISSUE_TEMPLATE.md`, `PULL_REQUEST_TEMPLATE.md`, and `dependabot.yml`
- `.github/dependabot.yml` configures weekly composer + github-actions dependency updates (cap 10 open PRs each)

---

*Stack analysis: 2026-04-22*
