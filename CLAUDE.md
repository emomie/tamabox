<!-- GSD:project-start source:PROJECT.md -->
## Project

**tamabox — SSR箱**

送信者のSNSハンドル・アイコン・プロフリンクが確率で露出する、匿名メッセージ箱の Web アプリ。X や Bluesky 上のクリエイター(二次創作者・YouTuber 等)が自分の「受信箱」をカスタマイズして運用する。SSR(露出)確率は受け手がカスタマイズ可能で、0% に設定すれば普通の匿名箱としても機能する。

**Core Value:** **「確率で名前がバレる」仕組みが、悪意送信者の自己抑止になる**(ASSUMPTIONS V1 = コア仮説)。これが偽ならプロダクト全体が成立しない。他の価値(好意送信者の祝福体験、クリエイターの心理負荷低減)はすべてこの一点が成立することに依存する。

### Constraints

- **Tech stack**: PHP / CakePHP 4.5, MySQL 8.0, UUID (CHAR(36)) PK — 使い慣れた構成 & Lolipop で動作実績あり
- **Hosting**: ロリポップ共有レンタルサーバー — `SUPER` 権限なし前提、ストアドプロシージャ最小限、トリガ登録不可、webroot 外からの config 読み込みが必須
- **PHP version**: 本番 Lolipop は 8.0+ 想定(composer.json は `^7.4` になっているため **整合修正必要**)
- **Security**: OAuth トークン暗号化必須(AES-GCM `*_enc` 列)、ES256 private key は `config/keys/`(gitignore 済)、DebugKit は `debug=true` 時のみ(本番 false 固定)
- **Legal / Ethics**: プロバイダ責任制限法 / 開示請求対応に耐えうる運用(Vi2 未検証だが前提)、送信前同意 UI で「確率名前バレ」を明示(E1)
- **Deployment**: ロリポップの Git deploy(main push トリガー)を使用予定
- **Encoding**: `utf8mb4_0900_ai_ci`(絵文字対応)
<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->
## Technology Stack

## Languages
- PHP `>=7.4` (production target PHP 8.0+ per `README.md` — ロリポップ shared hosting) — used across all application code under `src/`, `config/`, `tests/`, `webroot/`
- SQL (MySQL 8.0 dialect) — raw schema files in `config/schema/i18n.sql`, `config/schema/sessions.sql`
- HTML/CSS — under `templates/` and `webroot/css/` (Milligram + normalize + custom `home.css`)
- Apache `mod_rewrite` configuration — `.htaccess` and `webroot/.htaccess`
## Runtime
- PHP `>=7.4` (composer `require.php`), with these PHP extensions required (`config/requirements.php`):
- Apache with `mod_rewrite` (for pretty URLs — the top-level `.htaccess` rewrites all requests into `webroot/`)
- Production host: ロリポップ shared rental server (per `README.md`)
- Composer — `composer.json` declares PSR-4 autoload under `App\` → `src/`
- Lockfile: missing — no `composer.lock` committed at repo root (vendor not yet installed in this checkout; `vendor/.gitkeep` only)
## Frameworks
- CakePHP `^4.5.0` (`cakephp/cakephp`) — full-stack MVC framework. Entry point `webroot/index.php` → `App\Application` (`src/Application.php`)
- Middleware stack (`src/Application.php:75-105`): `ErrorHandlerMiddleware`, `AssetMiddleware`, `RoutingMiddleware`, `BodyParserMiddleware`, `CsrfProtectionMiddleware`
- ORM: CakePHP built-in ORM (`Cake\Database\Driver\Mysql` configured in `config/app.php:297`)
- Router uses `DashedRoute` class (`config/routes.php:50`)
- PHPUnit `^9.6` (`require-dev`) — config `phpunit.xml.dist`, bootstrap `tests/bootstrap.php`
- CakePHP TestSuite with `Cake\TestSuite\Fixture\PHPUnitExtension` (loaded via `phpunit.xml.dist:22-24`)
- Test suite directory: `tests/TestCase/` (only `ApplicationTest.php` + empty `Controller/`, `Model/`, `View/` subdirs so far)
- CakePHP Migrations `^3.7` (`cakephp/migrations`) — `bin/cake migrations migrate` is the schema workflow
- CakePHP Bake `^2.8` (`cakephp/bake`, dev) — scaffolding, loaded only in CLI via `Application::bootstrapCli()` (`src/Application.php:125-132`)
- DebugKit `^4.9` (`cakephp/debug_kit`, dev) — loaded only when `Configure::read('debug')` is true (`src/Application.php:62-64`)
- `josegonzalez/dotenv` `^4.0` (dev) — `.env` loader (block is commented out in `config/bootstrap.php:63-69`; `.env` loading is currently NOT active)
- `cakephp/plugin-installer` `^1.3` — composer plugin that installs CakePHP plugins into `plugins/` (currently empty, `plugins/.gitkeep` only)
## Key Dependencies
- `cakephp/cakephp` `^4.5.0` — application framework (controllers, ORM, routing, middleware, auth plumbing)
- `cakephp/migrations` `^3.7` — schema migrations (baseline: no migration files present yet under `config/`)
- `mobiledetect/mobiledetectlib` `^3.74` — mobile/tablet detection, wired as request detectors in `config/bootstrap.php:179-188` (`$request->is('mobile')`, `$request->is('tablet')`)
- `cakephp/plugin-installer` `^1.3` — CakePHP plugin wiring
- `cakephp/cakephp-codesniffer` `^4.5` (dev) — coding standard ruleset referenced by `phpcs.xml`
## Configuration
- Primary env var loader: CakePHP `env()` helper, reading PHP `getenv()` / `$_SERVER` / `$_ENV`
- `.env` file support exists (template at `config/.env.example`) but the dotenv loader in `config/bootstrap.php:63-69` is commented out — env vars must come from the web server / CLI shell unless that block is uncommented
- Local override file: `config/app_local.php` (gitignored), loaded by `config/bootstrap.php:90-92` if present
- Example local config: `config/app_local.example.php`
- `composer.json` — scripts: `composer check`, `composer cs-check` (`phpcs --colors -p`), `composer cs-fix` (`phpcbf`), `composer test` (`phpunit --colors=always`)
- `phpcs.xml` — references `CakePHP` ruleset, scans `src/` + `tests/`
- `phpstan.neon` — level 8, scans `src/`, excludes `src/Console/Installer.php`
- `phpunit.xml.dist` — bootstrap `tests/bootstrap.php`, whitelists `src/` and `plugins/*/src/`
- `.editorconfig` — LF line endings, 4-space indent for PHP, 2-space for YAML
- `.gitattributes` — enforces LF eol for text; explicit `eol=lf` pinned on `*.pem` files
## Platform Requirements
- PHP 7.4+ with `intl` (ICU ≥ 50.1) and `mbstring` extensions (`config/requirements.php`)
- Composer
- MySQL 8.0 (per `README.md` — tested locally; `config/app.php:297` wires the `Cake\Database\Driver\Mysql` driver)
- `openssl` CLI for generating the OAuth ES256 keypair
- Optional: built-in dev server via `bin/cake server` (per `README.md`)
- ロリポップ shared rental server (shared Apache + MySQL 8.0)
- Apache with `mod_rewrite` enabled (required by both root and webroot `.htaccess`)
- Deployment: ロリポップ Git deploy triggered by `main` branch push (README "デプロイ" section — not yet wired)
- Only `webroot/` is web-exposed on ロリポップ; `config/`, `src/`, `tmp/`, etc. live above the document root
- PHP 8.0+ expected in production per `README.md:50`
- GitHub Actions workflows directory is absent (no `.github/workflows/`); `.github/` only contains `ISSUE_TEMPLATE.md`, `PULL_REQUEST_TEMPLATE.md`, and `dependabot.yml`
- `.github/dependabot.yml` configures weekly composer + github-actions dependency updates (cap 10 open PRs each)
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions

## Overview
- `phpcs.xml` — PHP_CodeSniffer ruleset (CakePHP standard)
- `phpstan.neon` — PHPStan level 8 on `src/` (excludes `src/Console/Installer.php`)
- `.editorconfig` — whitespace/EOL rules
- `composer.json` scripts — `composer cs-check`, `composer cs-fix`, `composer test`, `composer check`
## Naming Patterns
- One class per file, PascalCase filename matching class name (PSR-4 via `App\` → `src/`).
- Test files: `{ClassName}Test.php` mirroring source path under `tests/TestCase/`.
- Examples: `src/Application.php`, `src/Controller/PagesController.php`, `tests/TestCase/ApplicationTest.php`.
- Production code: `App\...` rooted at `src/`. See `composer.json` autoload block.
- Tests: `App\Test\...` rooted at `tests/`. See `composer.json` autoload-dev.
- Sub-namespaces mirror directory layout (e.g. `App\Controller`, `App\View`, `App\Console`).
- PascalCase: `Application`, `PagesController`, `AppView`, `AjaxView`, `ErrorController`.
- Controllers end in `Controller` (`PagesController`, `ErrorController`).
- Views end in `View` (`AppView`, `AjaxView`).
- Base "App*" classes are the local parents per CakePHP convention (`AppController`, `AppView`).
- camelCase: `bootstrap()`, `middleware()`, `bootstrapCli()`, `createAppLocalConfig()`, `setSecuritySaltInFile()`.
- Hook methods use CakePHP-defined names: `initialize()`, `beforeFilter()`, `beforeRender()`, `afterFilter()`.
- camelCase locals and properties: `$middlewareQueue`, `$rootDir`, `$appLocalConfig`, `$subpage`.
- `$io` / `$dir` / `$file` short names are used in `src/Console/Installer.php` where Composer passes them.
- UPPER_SNAKE_CASE class constants: `Installer::WRITABLE_DIRS` in `src/Console/Installer.php`.
- PascalCase for namespaced code dirs: `src/Controller/`, `src/View/`, `src/Model/Entity/`, `src/Model/Table/`, `src/Model/Behavior/`, `src/View/Helper/`, `src/View/Cell/`, `src/Controller/Component/`.
- lowercase for non-namespaced infra: `config/`, `templates/`, `webroot/`, `logs/`, `tmp/`, `bin/`, `plugins/`, `resources/`.
## Code Style
- `CakePHP` coding standard via `cakephp/cakephp-codesniffer ^4.5` (`phpcs.xml` references `../../cakephp/cakephp-codesniffer`).
- Scope: `src/` and `tests/` only.
- Run: `composer cs-check` (or `phpcs --colors -p`).
- Autofix: `composer cs-fix` (or `phpcbf --colors -p`).
- Indent: 4 spaces, never tabs (except `Makefile`).
- End-of-line: LF (CRLF only for `.bat`).
- Final newline required; trailing whitespace trimmed.
- YAML: 2-space indent.
- Twig: no final newline.
- Every PHP source and test file begins with `<?php` on line 1, `declare(strict_types=1);` on line 2, then the CakePHP MIT header docblock. See `src/Application.php:1-16` as the canonical template.
- PHPStan level 8 on `src/` only (`phpstan.neon`).
- `checkMissingIterableValueType: false` — array generic types (`array<string, mixed>`) not required in every docblock.
- `treatPhpDocTypesAsCertain: false` — narrower PHPDoc types are treated as hints, not facts.
- `checkGenericClassInNonGenericObjectType: false` — generics mismatches with CakePHP non-generic classes are silenced.
- `src/Console/Installer.php` excluded (Composer script plumbing).
- Run: `vendor/bin/phpstan analyse` (PHPStan is suggested but not in `require-dev`; install locally if needed).
## Type Declarations
- `declare(strict_types=1);` is mandatory on every PHP file (all existing files in `src/` and `tests/` use it).
- Use PHP 7.4+ type hints on parameters and return types wherever possible.
- Examples from `src/`:
- Nullable return `?Response`, `void`, and union-via-docblock are normal. See `ErrorController::beforeFilter` — typed param, no return type because it can return `Response|null|void` (documented in docblock only).
- Typed properties may be used (PHP 7.4+), but CakePHP inherited properties (e.g. `AjaxView::$layout`) use legacy `public $layout = 'ajax';` style when overriding framework contracts. See `src/View/AjaxView.php:33`.
## Import Organization
## Docblocks
- Use fully-qualified class names with leading backslash inside `@param`/`@return`/`@throws`: `\Cake\Http\Response`, `\Cake\Event\EventInterface<\App\Controller\ErrorController>`.
- Generic event type parameters are used: `\Cake\Event\EventInterface<\App\Controller\ErrorController>` — see `src/Controller/ErrorController.php:41`.
- Keep the CakePHP MIT copyright header on new files (all current files carry it). If you replace with an application-specific header, keep the `@license` and `@since` tags for PHPCS.
## Error Handling
- Throw specific CakePHP HTTP exceptions for request-level errors — they auto-map to HTTP status codes via `ErrorHandlerMiddleware`.
- Throw framework-specific exceptions from `Cake\*\Exception\...` rather than generic `\Exception` for request-cycle errors.
- Use plain `\Exception` / `\InvalidArgumentException` only for low-level infrastructure (`src/Console/Installer.php:124`).
- `ErrorHandlerMiddleware` is the first middleware in the queue — see `src/Application.php:80`. All uncaught exceptions below it become rendered error responses via `ErrorController` (`src/Controller/ErrorController.php`).
- `ErrorController` sets the template path in `beforeRender()` to `Error/` — see `src/Controller/ErrorController.php:58`.
## Logging
## Comments
- Docblocks on every class, method, and non-private property (sniff-enforced).
- Inline `//` comments to explain *why* for non-obvious middleware ordering, security decisions, and config toggles — see the comments above each `->add(...)` call in `src/Application.php:77-102`.
- Multi-line `/* ... */` for longer explanations inside methods — see `src/Application.php:58-61`.
## Function / Method Design
- Prefer typed, named parameters. Variadics (`string ...$path`) when the count is unknown — see `PagesController::display()`.
- Static installer methods accept `$dir, $io, ...` positionally — acceptable for Composer script hooks, not a pattern to copy into business code.
- Explicit return type on every method. Use `: void` when nothing is returned.
- Nullable return types (`: ?Response`) preferred over union docblocks where the value set is "X or null".
- `public` for framework hook methods (`bootstrap`, `initialize`, `middleware`, `services`, `beforeFilter`, `beforeRender`, `afterFilter`, `display`).
- `protected` for internal-but-subclassable helpers (`bootstrapCli()` in `src/Application.php:125`).
- `private` for truly local helpers (none yet).
- `public static` only on Composer script entry points (`Installer::*`).
## Module Design
- One class per file; the class is the module's "export". No function-level globals or constants.
- `src/Controller/` — HTTP controllers; extend `App\Controller\AppController`.
- `src/Controller/Component/` — controller-level reusable behaviors.
- `src/Model/Table/` — ORM table gateways; extend `Cake\ORM\Table`.
- `src/Model/Entity/` — ORM row objects; extend `Cake\ORM\Entity`.
- `src/Model/Behavior/` — ORM-level mixins.
- `src/View/` — view classes (`AppView`, `AjaxView`).
- `src/View/Helper/` — view-level template helpers.
- `src/View/Cell/` — reusable view fragments.
- `src/Console/` — Composer scripts and any future `Cake\Command\Command` subclasses.
- Future: `src/Service/` for business logic (OAuth, SSR probability logic) — referenced in `README.md:93` but directory not created yet.
## CSRF / Security Defaults
- `CsrfProtectionMiddleware` with `httponly => true` (`src/Application.php:100-102`). New controllers inherit CSRF protection automatically.
- `FormProtectionComponent` is commented out in `AppController::initialize()` (`src/Controller/AppController.php:51`). Enable it per-controller when adding form submissions that need field-tampering protection.
- Directory-traversal guard pattern: `in_array('..', $path, true) || in_array('.', $path, true)` → `throw new ForbiddenException()` (`src/Controller/PagesController.php:51-53`). Replicate this guard in any controller that interpolates user path segments.
## Commit & CI Hooks
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
## Architecture

## Pattern Overview
- Front-Controller pattern — every request enters through `webroot/index.php`
- PSR-4 autoloading under the `App\` namespace rooted at `src/`
- Middleware pipeline (error handling -> assets -> routing -> body parsing -> CSRF)
- Convention-based routing: `/{controller}/{action}/*` fallbacks
- Separation of HTTP/CLI bootstrap via `PHP_SAPI` check in `src/Application.php`
- Server-side rendering only (no SPA); README tagline "SSR箱" refers to product name, not rendering choice
- Template/layer stubs exist (`src/Model/Table/`, `src/Model/Entity/`, `src/Controller/Component/`, `src/View/Cell/`, `src/View/Helper/`) but are empty (`.gitkeep` only)
## Layers
- Purpose: Accept all incoming HTTP requests, delegate to `App\Application`
- Location: `webroot/index.php` (real entry), `index.php` (thin root shim that requires the webroot file)
- Contains: Platform requirement check (`config/requirements.php`), Composer autoload, `Cake\Http\Server` instantiation
- Depends on: `vendor/autoload.php`, `App\Application`
- Used by: Apache `mod_rewrite` (see `.htaccess` files)
- Purpose: Load configuration, register error handlers, configure Cache/DB/Email/Log/Security, define plugins
- Location: `src/Application.php`, `config/bootstrap.php`, `config/bootstrap_cli.php`, `config/paths.php`
- Contains: `Application::bootstrap()`, `Application::middleware()`, `Application::services()` (empty), `Application::bootstrapCli()`
- Depends on: `Cake\Http\BaseApplication`, `config/app.php`, `config/app_local.php` (gitignored)
- Used by: `webroot/index.php`, `bin/cake.php`
- Purpose: Cross-cutting HTTP concerns configured in `src/Application.php` `middleware()`
- Location: `src/Application.php` lines 75-105
- Pipeline order (registered via `MiddlewareQueue`):
- Depends on: Cake `Configure` values (`Error`, `Asset.cacheTime`)
- Used by: `BaseApplication::handle()` during request dispatch
- Purpose: Map URL paths to controller actions
- Location: `config/routes.php`
- Contains: Single `/` scope with `Pages::display`, `/pages/*` alias, and `$builder->fallbacks()` (generates `/{controller}` and `/{controller}/{action}/*`)
- Default route class: `DashedRoute` (converts CamelCase to dashed-urls)
- Depends on: `Cake\Routing\RouteBuilder`
- Used by: `RoutingMiddleware`
- Purpose: Handle request, invoke domain logic, set view vars, render response
- Location: `src/Controller/`
- Contains:
- Depends on: `Cake\Controller\Controller`, View layer
- Used by: `RoutingMiddleware` resolves which controller/action to invoke
- Purpose: ORM Tables (query builders, associations, finders) and Entities (row objects)
- Location: `src/Model/Table/`, `src/Model/Entity/`, `src/Model/Behavior/` (all `.gitkeep` only)
- Pattern: CakePHP Table/Entity split — `*Table` classes hold queries/validation; `*Entity` classes hold row state
- Expected depends on: `Cake\ORM\Table`, `Cake\ORM\Entity`, `ConnectionManager` (MySQL per `config/app.php`)
- Configured via: `FactoryLocator::add('Table', (new TableLocator())->allowFallbackClass(false))` in `src/Application.php:52` — forbids implicit Table classes, forces explicit `*Table` classes per model
- Purpose: Render templates to HTML/AJAX/email output
- Location:
- Templates: `templates/` (see STRUCTURE.md)
- Purpose: Artisan-style commands (migrations, bake, custom shells)
- Location: `bin/cake`, `bin/cake.php`, `src/Console/Installer.php`
- Entry: `bin/cake.php` builds `CommandRunner` with `App\Application`
- CLI-only plugins (`Bake`, `Migrations`) loaded in `Application::bootstrapCli()`
- Planned location per `README.md:94`: `src/Service/` — OAuth flows, SSR probability logic
- Current state: directory does not exist; no service classes present
- When added, services should be wired via `Application::services(ContainerInterface $container)` (currently empty at `src/Application.php:114`)
## Data Flow
- HTTP state: CakePHP sessions (`tmp/sessions/` directory configured; `config/schema/sessions.sql` provides MySQL-backed session table schema if session handler is switched to DB)
- Persistent state: MySQL via `Cake\Database\Driver\Mysql` (see `config/app.php:5`), configured under `Datasources` key loaded by `ConnectionManager::setConfig` in `config/bootstrap.php:168`
## Key Abstractions
- Purpose: Composition root — defines middleware pipeline, plugin loading, DI container hooks
- Extends: `Cake\Http\BaseApplication`
- Pattern: Template Method — parent orchestrates boot; subclass overrides `bootstrap()`, `middleware()`, `services()`
- Purpose: Request handlers, one class per resource
- Pattern: Thin controller; base `AppController` loads `RequestHandler` + `Flash` components for all children
- Convention: Controllers named `{Resource}Controller` extending `AppController`
- Purpose: Presentation logic, helpers, cells
- Pattern: Two-step view — content template + layout wrapper
- Default layout fetched via `$this->fetch('content')` pattern in `templates/layout/default.php`
- Purpose: Cross-cutting HTTP concerns
- Pattern: Onion/pipeline; each middleware wraps the next via `$handler->handle($request)`
## Entry Points
- Location: `webroot/index.php`
- Triggers: Apache rewrite from any URL under document root
- Responsibilities: Load requirements + autoloader, instantiate `Cake\Http\Server` with `App\Application`, emit response
- Location: `index.php` (project root)
- Triggers: Direct requests to project root if webroot is not the DocumentRoot (via `.htaccess` rewrite)
- Responsibilities: `require 'webroot/index.php'` — single-line delegate
- Location: `bin/cake` (shell wrapper), `bin/cake.php` (PHP entry), `bin/cake.bat` (Windows)
- Triggers: Manual invocation (`bin/cake server`, `bin/cake migrations migrate`, `bin/cake bake ...`)
- Responsibilities: Build `CommandRunner` and execute command
- Location: `tests/bootstrap.php` (invoked by `phpunit.xml.dist`)
- Triggers: `composer test` / `phpunit`
- Responsibilities: Load autoloader + `config/bootstrap.php`, configure `debug_kit` SQLite connection, run migrations via `Migrations\TestSuite\Migrator` to build test DB schema
## Error Handling
- `(new ErrorTrap(Configure::read('Error')))->register();` — registered in `config/bootstrap.php:125`
- `(new ExceptionTrap(Configure::read('Error')))->register();` — `config/bootstrap.php:126`
- `ErrorHandlerMiddleware` is the outermost middleware, catches exceptions thrown by inner layers (`src/Application.php:80`)
- `App\Controller\ErrorController` (`src/Controller/ErrorController.php`) renders error templates at `templates/Error/error400.php` and `templates/Error/error500.php`
- Controllers throw CakePHP HTTP exceptions for flow control: `NotFoundException`, `ForbiddenException` (example: `src/Controller/PagesController.php:52,70`)
## Cross-Cutting Concerns
- Configured via `Log::setConfig(Configure::consume('Log'))` in `config/bootstrap.php:171`
- Backend: `Cake\Log\Engine\FileLog` (see `config/app.php:6`), files written to `logs/` (path defined in `config/paths.php:71`)
- CLI-specific log files: `cli-debug`, `cli-error` (overridden in `config/bootstrap_cli.php:31-34`)
- Not yet implemented (no Table classes created). Convention: per-Table `validationDefault(Validator $validator)` method.
- Not yet implemented. Planned: Bluesky AT Protocol OAuth 2.0 (per `README.md:30`); ES256 key pair stored in `config/keys/` (gitignored). No auth middleware/component installed yet. `cakephp/authentication` plugin is NOT in `composer.json`.
- `CsrfProtectionMiddleware` enabled with `httponly => true` in `src/Application.php:100`
- Applied to every route by default
- `AssetMiddleware` with `cacheTime` from `Configure::read('Asset.cacheTime')` (`src/Application.php:83-85`)
- `mobiledetect/mobiledetectlib` ^3.74 registered as request detectors in `config/bootstrap.php:179-188` — enables `$request->is('mobile')` / `$request->is('tablet')` checks
- `date_default_timezone_set(Configure::read('App.defaultTimezone'))` — default `UTC` (`config/app.php:53`)
- `mb_internal_encoding(Configure::read('App.encoding'))` — default `UTF-8`
- `TypeFactory::map('time', StringType::class)` in `config/bootstrap.php:214` — MySQL `TIME` columns returned as PHP strings (no native Cake time type)
<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->
## Project Skills

No project skills found. Add skills to any of: `.claude/skills/`, `.agents/skills/`, `.cursor/skills/`, `.github/skills/`, or `.codex/skills/` with a `SKILL.md` index file.
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->



<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
