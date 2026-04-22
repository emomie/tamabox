# External Integrations

**Analysis Date:** 2026-04-22

> **Stage note:** Per `README.md`, the project just finished the discovery phase and is at the very start of the build phase. The integration surface described below is the **designed / planned** surface. Only the skeleton wiring exists in code today — no auth controller, no OAuth client class, no SSR engine, no external HTTP client code is present in `src/` yet. Implementation lives entirely in the CakePHP 4.5 app-skeleton boilerplate.

## APIs & External Services

**Authentication / Identity:**
- **Bluesky (AT Protocol) OAuth 2.0** — primary auth provider for both receivers and senders
  - Role: receiver login (mandatory) + sender identity (required, no full anonymity) — per `README.md:14-16, 30`
  - Flow spec: [AUTH-FLOW.md v0.1](https://github.com/emomie/ssr-box-discovery/blob/main/AUTH-FLOW.md) in the sibling `emomie/ssr-box-discovery` design repo
  - SDK/client: none installed yet — no Bluesky / AT Protocol library in `composer.json`; the OAuth client will need to be implemented (or a library added) during build
  - Token signing keys: ES256 EC keypair at `config/keys/private.key` / `config/keys/public.key` (generated via `openssl ecparam -genkey -name prime256v1` per `README.md:64-68`; `chmod 600` on private, `644` on public)
  - Public OAuth endpoints planned at `webroot/oauth/` per `README.md:97` — **directory does not yet exist** (`webroot/` currently holds only `css/`, `js/`, `img/`, `font/`, `index.php`, `favicon.ico`, `.htaccess`)
  - Auth credentials / env vars: not yet defined — `config/.env.example` has no Bluesky-related variables
- **X (formerly Twitter) OAuth** — secondary auth provider, scheduled for a follow-up phase per `README.md:30` ("後続で X 追加")
  - No SDK, env var, or code present yet

## Data Storage

**Databases:**
- **MySQL 8.0** — the only datastore
  - Driver: `Cake\Database\Driver\Mysql` configured in `config/app.php:297, 340`
  - Connection config: `Datasources.default` in `config/app.php:284-333`; credentials read from `DATABASE_URL` DSN env var (`config/app_local.example.php:60`) or from `host` / `username` / `password` / `database` keys in `config/app_local.php`
  - Recommended encoding per `config/app.php:302`: `utf8mb4` (currently commented — needs enabling on real `app_local.php`)
  - Timezone: UTC (`config/app.php:299`)
  - Identifier types: UUID `CHAR(36)` per `README.md:32`
  - Schema source of truth: [DB-SCHEMA.md v0.2](https://github.com/emomie/ssr-box-discovery/blob/main/DB-SCHEMA.md) in the design repo
  - Migration tool: `cakephp/migrations` `^3.7`; no migration files yet in `config/Migrations/` (directory not even created)
- **Test database**
  - Default DSN: `sqlite://127.0.0.1/tmp/tests.sqlite` (fallback in `config/app_local.example.php:73`) — overridable via `DATABASE_TEST_URL`
  - `tests.sqlite` is gitignored (`.gitignore:30`)
- **Raw SQL schema fragments** (non-migration) in `config/schema/`:
  - `config/schema/sessions.sql` — CakePHP database session table (only needed if sessions are switched from the default PHP handler)
  - `config/schema/i18n.sql` — CakePHP i18n translation table (used if translation-in-DB is ever enabled)

**File Storage:**
- Local filesystem only
  - Cache: `tmp/cache/` via `Cake\Cache\Engine\FileEngine` (`config/app.php:97-147`)
  - Logs: `logs/` via `Cake\Log\Engine\FileLog` (`config/app.php:355-380`)
  - Sessions: PHP default handler writing to the OS session dir (`config/app.php:421-423`: `'Session' => ['defaults' => 'php']`)
  - `logs/*` and `tmp/*` are gitignored (`.gitignore:5-6`)
- No S3 / object storage / CDN wiring

**Caching:**
- File-backed only today (`FileEngine` on all four cache configs: `default`, `_cake_core_`, `_cake_model_`, `_cake_routes_`)
- Every cache entry has an optional env-var DSN override (`CACHE_DEFAULT_URL`, `CACHE_CAKECORE_URL`, `CACHE_CAKEMODEL_URL`, `CACHE_CAKEROUTES_URL`) so Redis/Memcached can be swapped in without code changes — no such URL is set in `config/.env.example`
- No Redis / Memcached library in `composer.json`

## Authentication & Identity

**Auth Provider:**
- External OAuth (Bluesky primary, X secondary) — design-only today; see "APIs & External Services" above
- No CakePHP Authentication/Authorization plugins installed (`cakephp/authentication`, `cakephp/authorization` absent from `composer.json`) — they may need to be added during build, or a hand-rolled OAuth flow may live under `src/Service/` (per `README.md:93` which mentions `src/Service/` as the planned location for OAuth + SSR logic; the directory does not exist yet — current `src/` has only `Console/`, `Controller/`, `Model/`, `View/`, `Application.php`)
- Session handling: default PHP sessions (`config/app.php:421-423`); `httponly` cookies enforced by the CSRF middleware config (`src/Application.php:100-102`)

## Monitoring & Observability

**Error Tracking:**
- No external service (no Sentry / Rollbar / Bugsnag SDK in `composer.json`)
- In-process: `Cake\Error\ErrorTrap` + `Cake\Error\ExceptionTrap` registered in `config/bootstrap.php:125-126`; errors routed through CakePHP's `ErrorHandlerMiddleware` in `src/Application.php:80`

**Logs:**
- File-based via `Cake\Log\Engine\FileLog` writing to `logs/` (`config/app.php:355-380`)
- Three channels: `debug` (notice/info/debug), `error` (warning+), `queries` (scoped to `queriesLog`, only active when a datasource sets `'log' => true`)
- Each channel honors a `LOG_*_URL` env var override for remote log shipping; none configured in `config/.env.example`
- Production log rotation / aggregation strategy: not yet defined

## CI/CD & Deployment

**Hosting:**
- ロリポップ (Lolipop) shared rental hosting — Apache + MySQL 8.0 (per `README.md:29, 105-106`)
- Document root pinned to `webroot/`; the root `.htaccess` rewrites every request (including `.well-known/*`) into `webroot/` so the app can be uploaded whole while only `webroot/` is web-exposed

**CI Pipeline:**
- No GitHub Actions workflows defined (`.github/workflows/` does not exist)
- `.github/dependabot.yml` enables weekly Dependabot scans for `composer` and `github-actions` ecosystems (10 open-PR cap each)
- PR/issue conventions: `.github/PULL_REQUEST_TEMPLATE.md`, `.github/ISSUE_TEMPLATE.md` (CakePHP skeleton defaults)

**Deployment:**
- Planned: ロリポップ Git deploy — pushes to `main` trigger production reflection (`README.md:105-106`); not yet wired

## Environment Configuration

**Required env vars (must be set before the app boots):**
- `SECURITY_SALT` — security/encryption salt (`config/app.php:78`); no default, will bomb Security::setSalt if missing
- Database connection — either a `DATABASE_URL` DSN OR explicit `host` / `username` / `password` / `database` in `config/app_local.php`
- `APP_DEFAULT_TIMEZONE` — set via `date_default_timezone_set()` in `config/bootstrap.php:109` (defaults to `UTC`)

**Recommended / planned env vars (not yet defined anywhere):**
- Bluesky OAuth client id, client secret (or DPoP key identifier), redirect URI
- X OAuth credentials (follow-up phase)
- SSR probability configuration (default 10 % per `README.md:15`, currently a design-doc value)

**Secrets location:**
- `config/.env` — template at `config/.env.example`; gitignored (`.gitignore:4`); dotenv loading currently commented out at `config/bootstrap.php:63-69` (needs uncommenting to take effect in dev)
- `config/app_local.php` — gitignored (`.gitignore:3`); auto-loaded by `config/bootstrap.php:90-92` when present
- `config/keys/*.key`, `config/keys/*.pem` — gitignored (`.gitignore:10-11`); OAuth signing material only, generated via `openssl` per `README.md:64-68`

## Webhooks & Callbacks

**Incoming:**
- Bluesky OAuth redirect/callback — will land under `webroot/oauth/` per `README.md:97` (directory not yet created; no route in `config/routes.php`)
- `.well-known/*` paths are preserved by the root `.htaccess:9` (pass-through rule) — relevant to AT-Protocol OAuth (`/.well-known/oauth-protected-resource`, client metadata, etc.)
- No other webhooks identified in scope

**Outgoing:**
- OAuth token exchange + PAR/DPoP calls to Bluesky / X IdPs (planned, not implemented)
- No email-out integrations — `EmailTransport.default` uses `Cake\Mailer\Transport\MailTransport` (PHP `mail()`) against `localhost:25` (`config/app.php:229-248`); no SMTP provider, no SendGrid/Resend/etc. SDK in `composer.json`

## Email

- Transport: `Cake\Mailer\Transport\MailTransport` (PHP `mail()`) to `localhost:25`, defined in `config/app.php:229-248`
- Default profile: `Email.default` with `from => 'you@localhost'` placeholder (`config/app.php:259-269`) — must be customized before any email is sent from production
- Override: `EMAIL_TRANSPORT_DEFAULT_URL` DSN env var (`config/app.php:246`)
- No transactional email provider wired

---

*Integration audit: 2026-04-22*
