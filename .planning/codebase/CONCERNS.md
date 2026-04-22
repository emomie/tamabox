# Codebase Concerns

**Analysis Date:** 2026-04-22

> **Project stage context:** README declares "discovery フェーズ完了、build フェーズ開始". The checkout is a **fresh CakePHP 4.5 skeleton** — `src/Model/Table/`, `src/Model/Entity/`, `src/Model/Behavior/`, `src/Controller/Component/` are empty `.gitkeep` dirs. There is no Service layer (`src/Service/` referenced in README does not yet exist). Only the framework-provided `PagesController`, `ErrorController`, `AppController`, `AppView`, `AjaxView`, and the CakePHP "welcome" home template are present. Accordingly, most concerns below are **pre-build risks / things that will bite as soon as domain code lands**, not active bugs in shipped code.

---

## Tech Debt

**Stale scaffolding / "dead code" left from CakePHP skeleton:**
- Issue: The default CakePHP marketing home page is still wired at `/` (`config/routes.php:58` → `templates/Pages/home.php`), which renders the "Welcome to CakePHP Strawberry" screen with framework environment diagnostics and external links (`cakephp.org`, IRC, Slack, Bakery). In debug mode a real visitor would see framework self-diagnostics; in prod it throws `NotFoundException` with the literal string "Please replace templates/Pages/home.php with your own version" (`templates/Pages/home.php:53-56`).
- Files: `templates/Pages/home.php`, `config/routes.php`, `tests/TestCase/Controller/PagesControllerTest.php` (still asserts `CakePHP` string literal at line 43)
- Impact: Any accidental prod deploy before a real home view exists = 404 with a developer-facing message OR the CakePHP welcome page leaks internal env/DB/cache status. Test suite will break the moment the home template is replaced (`assertResponseContains('CakePHP')` is skeleton-specific).
- Fix approach: Replace `templates/Pages/home.php` and update `PagesControllerTest::testDisplay()` as part of the first build phase. Consider removing the generic `$builder->fallbacks()` (`config/routes.php:78`) once real routes exist, so unknown `/{controller}` paths don't hit the PagesController catchall.

**`fallbacks()` catchall route:**
- Issue: `config/routes.php:78` calls `$builder->fallbacks();` which exposes `/{controller}` and `/{controller}/{action}/*` for every class in `src/Controller/`. As the domain grows (especially auth/OAuth controllers), this auto-exposes every public method.
- Files: `config/routes.php`
- Impact: Future `BlueskyOauthController::internalHelper()` gets a free public URL unless the author remembers to mark non-public actions protected.
- Fix approach: Remove `fallbacks()` before landing any authenticated controller; connect routes explicitly.

**`AppController` has `FormProtection` disabled:**
- Issue: `src/Controller/AppController.php:51` leaves the `FormProtection` component commented out. Only `RequestHandler` + `Flash` are loaded. CSRF middleware is on in `Application::middleware()` (`src/Application.php:100`), but form field tampering protection is not.
- Files: `src/Controller/AppController.php`
- Impact: For a project whose core mechanic is identity-sensitive messaging (SSR reveal), relying on CSRF alone is weaker than CSRF + FormProtection. Hidden field tampering (e.g. `recipient_id`, probability override) won't be caught.
- Fix approach: Enable `FormProtection` in `AppController::initialize()` before the first form-driven controller lands.

**`RequestHandler` component is loaded globally but deprecated in CakePHP 4.4+:**
- Issue: `src/Controller/AppController.php:44` and `src/Controller/ErrorController.php:35` both `loadComponent('RequestHandler')`. CakePHP 4.4 deprecated this component in favor of content negotiation via view classes / middleware.
- Files: `src/Controller/AppController.php`, `src/Controller/ErrorController.php`
- Impact: Upgrade friction when moving to CakePHP 5.
- Fix approach: Switch to `ContentTypeNegotiation` / dedicated view classes; project already has `src/View/AjaxView.php` as a seed for that pattern.

**Empty `src/Service/` directory contract vs reality:**
- Issue: README (`README.md:93`) advertises `src/Service/` as the home for OAuth / SSR logic, but that directory does not exist yet.
- Files: `README.md:93`, `src/` (no Service subdir)
- Impact: First developer to add OAuth has no convention established — may dump logic into controllers.
- Fix approach: Create `src/Service/` with an initial skeleton and a CONVENTIONS entry before starting build.

**DebugKit plugin loaded based on `debug` flag only:**
- Issue: `src/Application.php:62-64` loads DebugKit whenever `Configure::read('debug')` is true. `config/app.php:424-428` also exposes `DebugKit.forceEnable` via env.
- Files: `src/Application.php`, `config/app.php`
- Impact: If `DEBUG=true` leaks into production (common on shared hosting like Lolipop where env files are easy to mis-deploy), DebugKit exposes DB queries, session state, cache, env vars to any visitor on a toolbar.
- Fix approach: Document Lolipop-side `.htaccess` / env hardening in README; consider guarding `addPlugin('DebugKit')` behind IP whitelist or `Configure::read('debug') && PHP_SAPI !== 'cli' && !isProduction()`.

---

## Known Bugs

None observed in the current committed code — there is almost no domain code to contain bugs. The only functional controllers are CakePHP defaults which are battle-tested.

**Test suite will red the moment home.php is replaced:**
- Symptoms: `PagesControllerTest::testDisplay` fails with "response did not contain 'CakePHP'".
- Files: `tests/TestCase/Controller/PagesControllerTest.php:43`
- Trigger: Any PR that replaces the placeholder home template.
- Workaround: Update/remove the assertion in the same PR.

---

## Security Considerations

**Session defaults to PHP ini with no hardening:**
- Risk: `config/app.php:421-423` sets `'Session' => ['defaults' => 'php']` and nothing else. No explicit `cookie`, `cookieSecure`, `cookieHttpOnly`, `cookieSameSite`, `timeout`, or `ini.session.use_strict_mode`. On shared hosting (Lolipop) that means session cookies inherit whatever the system php.ini declares — often not Secure, not SameSite=Lax/Strict.
- Files: `config/app.php:421-423`
- Current mitigation: CSRF middleware is enabled with `httponly => true` for the CSRF cookie (`src/Application.php:100-102`), but that's the CSRF token cookie, not the session cookie.
- Recommendations: Before any auth code lands, set:
  ```php
  'Session' => [
      'defaults' => 'php',
      'ini' => [
          'session.cookie_secure' => true,
          'session.cookie_httponly' => true,
          'session.cookie_samesite' => 'Lax',
          'session.use_strict_mode' => 1,
      ],
      'timeout' => 60,
  ],
  ```
  and consider switching to `'database'` sessions once the users table exists (SSR-box will benefit from server-side session invalidation on block/report).

**Session files written inside project tree, not gitignored properly under MPM scenarios:**
- Risk: `tmp/sessions/` is the default session save path on `defaults => 'php'` when `save_path` is unset AND CakePHP's `TMP` is used. `tmp/*` is gitignored, but on Lolipop deploy via `git deploy`, the `tmp/sessions` dir may not be auto-created with correct perms, and on shared hosting the session save path is commonly elsewhere (PHP default `/tmp` or hosting-specific).
- Files: `tmp/sessions/.gitkeep`, `.gitignore:6` (`/tmp/*`)
- Current mitigation: `Installer.php` (`src/Console/Installer.php:37-46`) creates the dir and chmods `tmp/` world-writable (0777) during `composer install`.
- Recommendations: Verify on Lolipop staging that session files are actually written to the application's `tmp/sessions/` (else migrate to DB sessions). 0777 on `tmp/` is "works everywhere" but flags security scanners — consider 0775 if the deploy user matches the PHP runtime user.

**`setFolderPermissions` chmods `tmp/` world-writable:**
- Risk: `src/Console/Installer.php:116-171` walks `tmp/` and `logs/` and OR-masks with `0007`, granting world read/write/execute. Comment at line 110 admits this is "not the most secure default".
- Files: `src/Console/Installer.php:138-170`
- Current mitigation: None — this runs on every `composer install`.
- Recommendations: On shared hosting where Apache runs as a group-shared user (Lolipop), 0770 or 0775 is usually enough. Customize the mask or accept the trade-off explicitly in a comment.

**`.env` loading in bootstrap is commented out:**
- Risk: `config/bootstrap.php:63-69` has the dotenv-loader commented out. Secrets will need to be set via real env vars (OK for Lolipop if done via `.htaccess SetEnv`) — but if a developer copies `.env.example` to `.env` expecting it to work, nothing loads it, so defaults silently apply (e.g. `SECURITY_SALT` fallback to `__SALT__` in `app_local.example.php:28`).
- Files: `config/bootstrap.php:63-69`, `config/app_local.example.php:28`, `config/.env.example`
- Current mitigation: `Installer.php::setSecuritySalt()` rewrites `__SALT__` with a random value during `composer install`, so fresh checkouts get a real salt.
- Recommendations: Either enable the dotenv block or delete `.env.example` to avoid confusion. Document which approach Lolipop uses.

**`config/keys/` is empty and not auto-generated:**
- Risk: README (`README.md:64-68`) instructs `openssl ecparam -genkey ...` for Bluesky OAuth ES256 signing keys, but there is no automation. A developer can forget; the app won't boot if a service later `file_get_contents(CONFIG . 'keys/private.key')` is attempted against an empty file.
- Files: `config/keys/.gitkeep`, `README.md:64-68`
- Current mitigation: `.gitignore:10-11` correctly excludes `*.key` and `*.pem`.
- Recommendations: Add key-existence check at bootstrap (fail-fast with a clear error) OR a `bin/cake` command to generate them. Verify private key perms on Lolipop (0600 needs the deploy user === PHP user).

**`.htaccess` has httpoxy mitigation commented out:**
- Risk: `.htaccess:3-5` leaves the `RequestHeader unset Proxy` block commented. CakePHP's default skeleton ships it commented because it requires `mod_headers`. Lolipop enables `mod_headers`, so this is leaving a known CVE (CVE-2016-5385 class) mitigation off by default.
- Files: `.htaccess:1-5`, `webroot/.htaccess`
- Current mitigation: None.
- Recommendations: Uncomment the block before prod deploy; verify Lolipop has `mod_headers`.

**No `X-Frame-Options` / `Content-Security-Policy` / `Strict-Transport-Security` headers:**
- Risk: Neither `.htaccess` nor any middleware sets security response headers. For a messaging app that embeds SNS handles, clickjacking protection and CSP are meaningful.
- Files: `.htaccess`, `webroot/.htaccess`, `src/Application.php:75-105`
- Current mitigation: None.
- Recommendations: Add `HttpsEnforcerMiddleware` (CakePHP built-in) and a `SecurityHeaderMiddleware` custom class before shipping auth.

**`trustProxy` hardcoded to false:**
- Risk: `config/bootstrap.php:149` sets `$trustProxy = false;`. Lolipop's shared hosting setup may terminate TLS at a load balancer; if so, `env('HTTPS')` is never truthy inside PHP, so `Router::fullBaseUrl()` generates `http://` absolute URLs. That leaks into OAuth redirect URIs, emails, etc., and Bluesky OAuth will reject non-https `redirect_uri`.
- Files: `config/bootstrap.php:149-160`
- Current mitigation: None.
- Recommendations: Once Lolipop's proxy behavior is known, flip `$trustProxy` or set `App.fullBaseUrl` explicitly via env.

**Directory traversal protection limited to `.`/`..` literal match:**
- Risk: `src/Controller/PagesController.php:51` only checks `in_array('..', $path, true) || in_array('.', $path, true)`. This is the upstream CakePHP default and does NOT catch encoded variants (`%2e%2e`, null bytes, etc.), though CakePHP's router normalizes most of these upstream.
- Files: `src/Controller/PagesController.php:51-53`
- Current mitigation: Test exists at `tests/TestCase/Controller/PagesControllerTest.php:82-87` for the literal case.
- Recommendations: If `PagesController` is kept long-term, replace with `realpath()` containment check. Safer: remove the generic Pages route once a real home/static-content controller exists.

**PHP 7.4 declared minimum:**
- Risk: `composer.json:8` says `"php": ">=7.4"`. PHP 7.4 is EOL since 2022-11. CakePHP 4.5 itself supports up through 8.3, but letting 7.4 in means a contributor's 7.4 local box passes tests that would fail on Lolipop 8.1+.
- Files: `composer.json:8`, `config/requirements.php:23` (checks `>= 7.2`)
- Current mitigation: README says "本番ロリポップは 8.0+ 想定".
- Recommendations: Bump to `"php": ">=8.1"`.

---

## Performance Bottlenecks

Nothing runtime-observable in the current code (no domain queries yet). Preemptive notes:

**Route cache disabled in debug, OK default in prod:**
- Problem: `config/bootstrap.php:98-103` resets `_cake_routes_` duration to `+2 seconds` when `debug=true`. Fine for dev; make sure debug stays `false` in prod so the configured `+1 years` applies.
- Files: `config/bootstrap.php:98-103`, `config/app.php:139-146`

**Cache engine is `FileEngine` rooted at `tmp/cache/`:**
- Problem: `config/app.php:97-147` all four cache profiles use `FileEngine`. On Lolipop (spinning disk, shared I/O), file cache can be slow under concurrency. `APCu` or `Redis` is unavailable on most shared plans — so this is likely the only option, but worth noting as a scaling ceiling.
- Files: `config/app.php:97-147`

---

## Fragile Areas

**`PagesController::display` is a public catch-all template renderer:**
- Files: `src/Controller/PagesController.php:46-72`
- Why fragile: Any file added under `templates/Pages/*.php` becomes a publicly-renderable URL at `/pages/<filename>`. A stray debug template (`templates/Pages/debug.php`) auto-ships to prod.
- Safe modification: Either keep only `home.php` and remove the wildcard `/pages/*` route in `config/routes.php:63`, or move ad-hoc HTML into proper controllers.
- Test coverage: `testDirectoryTraversalProtection` covers `..`, but does not prevent well-named-but-sensitive templates from being exposed.

**`index.php` → `webroot/index.php` double-hop:**
- Files: `index.php`, `webroot/index.php`
- Why fragile: The top-level `index.php:16` simply `require`s `webroot/index.php`. This exists to support shared hosts (Lolipop) that expose the project root, not `webroot/`. But `.htaccess:7-12` already rewrites to `webroot/` — so the top-level `index.php` is only a fallback for servers without mod_rewrite. If Lolipop DOES expose `webroot/` directly as the docroot, the top-level `index.php` is dead code; if NOT, the double-hop plus rewrite must both stay correct.
- Safe modification: Pick one model (docroot = `webroot/` vs docroot = project root) and document it. Lolipop supports both; the ambiguity is the risk.

---

## Scaling Limits

**Lolipop shared hosting:**
- Current capacity: Unknown — depends on plan. Typical shared plans cap concurrent PHP processes at ~10-20.
- Limit: At the first viral SSR event (probability-10% reveal gets shared), concurrent reads of the same box can saturate the plan.
- Scaling path: Migrate to VPS (Xserver VPS already available per global CLAUDE.md context, but out of scope here) or add page-level caching in front of box views.

**File cache under concurrent writes:**
- Current capacity: OK for low-traffic.
- Limit: `FileEngine` uses `flock()`; stampedes on hot keys serialize.
- Scaling path: No cheap fix on shared hosting. Cache at HTTP layer (CDN, Cloudflare) for SSR'd/public pages.

---

## Dependencies at Risk

**`cakephp/cakephp ^4.5.0`:**
- Risk: CakePHP 5 is current; 4.5 receives security fixes but no new features. Upgrade window is finite.
- Impact: Framework deprecations (notably `RequestHandler` component, see tech debt) will need addressing on 5.x migration.
- Migration plan: Plan CakePHP 5 upgrade after MVP; track CakePHP release notes.

**`mobiledetect/mobiledetectlib ^3.74`:**
- Risk: Used only for the `ServerRequest::addDetector('mobile'/'tablet', ...)` setup in `config/bootstrap.php:179-188`. Not referenced anywhere in `src/`. Dead weight unless UX logic will branch on mobile detection.
- Impact: Extra 100KB+ of dep, regex updates needed to keep detection accurate.
- Migration plan: Drop from `composer.json:12` if the build never uses it; otherwise move detection to CSS media queries.

**`composer.json` has `"minimum-stability": "dev"` with `"prefer-stable": true`:**
- Risk: `composer.json:49-50`. `prefer-stable` mitigates most risk but leaves the door open for dev-branch packages to land in `composer.lock`.
- Impact: Surprising upgrades.
- Migration plan: Flip to `"minimum-stability": "stable"` unless a specific dep requires dev.

**Dependabot enabled, `composer.lock` not in repo:**
- Risk: `.github/dependabot.yml` opens up to 10 weekly composer PRs, but without a committed `composer.lock` the PRs cannot update locked versions — they will update `composer.json` constraints only.
- Impact: Dependency drift between dev machines and prod.
- Migration plan: Commit `composer.lock`.

---

## Missing Critical Features

The entire product is unimplemented. Per README this is expected ("build フェーズ開始"), but concretely:

**No domain layer:**
- Problem: Empty `src/Model/Table`, `src/Model/Entity`, `src/Model/Behavior`, no migrations in `config/Migrations/` (the `migrations` dir itself doesn't exist — `README.md:71` says `bin/cake migrations migrate`, which will no-op).
- Blocks: Every feature (auth, box, message, SSR, block/report).

**No OAuth implementation:**
- Problem: README (`README.md:64-68`) plans Bluesky ES256 OAuth but no service, no controller, no routes, no callback handler at `webroot/oauth/` (the README says this exists — it does not).
- Blocks: Login, send-message auth, core SSR mechanic.

**No rate limiting middleware:**
- Problem: Anonymous message box + public write endpoint = spam magnet. No throttling anywhere.
- Blocks: Safe public launch.

**No CAPTCHA / antispam:**
- Problem: Combined with OAuth-required-sender design, CAPTCHA may be unnecessary — but the design says the OAuth is per-sender-account, which does not prevent disposable accounts.
- Blocks: Production resilience.

**No migrations directory:**
- Problem: `bin/cake migrations migrate` in README will report "no migrations to run". The `config/schema/` dir contains only `i18n.sql` and `sessions.sql` (framework helpers).
- Blocks: Any DB-backed feature.

---

## Test Coverage Gaps

**Fixture directory empty, zero domain tests:**
- What's not tested: Everything except framework scaffolding.
- Files: `tests/Fixture/.gitkeep`, `tests/TestCase/Controller/Component/.gitkeep`, `tests/TestCase/Model/Behavior/.gitkeep`, `tests/TestCase/View/Helper/.gitkeep` — all empty
- Risk: No safety net exists when domain code lands.
- Priority: **High** — establish factories + a CI step (`.github/` has no workflows under `workflows/`, only dependabot) before merging OAuth.

**No CI workflow at all:**
- What's not tested: Nothing on push/PR.
- Files: `.github/` contains only `ISSUE_TEMPLATE.md`, `PULL_REQUEST_TEMPLATE.md`, `dependabot.yml` — no `workflows/` dir.
- Risk: Broken pushes to `main` auto-deploy via Lolipop git deploy (per README:106).
- Priority: **High** — add `.github/workflows/ci.yml` running `composer test` + `composer cs-check` before main is treated as deployable.

**`PagesControllerTest::testDisplay` is skeleton-coupled:**
- What's not tested: Functional home page content.
- Files: `tests/TestCase/Controller/PagesControllerTest.php:38-45`
- Risk: See Tech Debt section.
- Priority: Medium — fix during first real home template.

**No tests for `ErrorController`:**
- What's not tested: 4xx/5xx rendering, missing template → 404 conversion.
- Files: `src/Controller/ErrorController.php`, `templates/Error/error400.php`, `templates/Error/error500.php`
- Risk: Low — it's framework-provided, and `PagesControllerTest::testMissingTemplate` exercises the production 404 path.
- Priority: Low.

**`tests/schema.sql` is empty except for a comment:**
- What's not tested: N/A (bootstrap uses Migrations runner at `tests/bootstrap.php:65`, but with zero migrations, the test DB is empty).
- Files: `tests/schema.sql`
- Risk: When migrations land, `SchemaLoader` vs `Migrator` choice matters for test-run speed.
- Priority: Low, decide later.

---

## Gitignore / Deploy Hygiene Notes

**`tmp/` is correctly ignored but shipped with `.gitkeep`:**
- `.gitignore:6` ignores `/tmp/*`, and `.gitkeep` files preserve the subdir structure (`tmp/cache/models/.gitkeep`, etc.). Correct pattern.
- `tmp/` contents are not in git. Good.

**`/logs/*` gitignored but `logs/` dir does not exist in checkout:**
- `.gitignore:5` is `/logs/*`, but `logs/` is absent. `Installer.php` creates it on `composer install`. On first deploy without running the installer, the `FileLog` writer (`config/app.php:356-380`) will fail silently.
- Recommendation: Add `logs/.gitkeep`.

**`vendor/` contains only a `.gitkeep`:**
- `.gitignore:7` ignores `/vendor/*`, and the checkout has not been `composer install`-ed. Normal.

**`config/keys/` has a `.gitkeep` and .gitignore excludes `*.key`/`*.pem`:**
- Correct — structure preserved, secrets excluded.

---

*Concerns audit: 2026-04-22*
