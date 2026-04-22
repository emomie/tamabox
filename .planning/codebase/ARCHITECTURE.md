# Architecture

**Analysis Date:** 2026-04-22

## Pattern Overview

**Overall:** CakePHP 4.5 MVC (Model-View-Controller) skeleton — vendored convention-over-configuration PHP framework. The repo is currently a freshly-generated `cakephp/app` skeleton with no domain code added yet; architectural decisions follow CakePHP defaults.

**Key Characteristics:**
- Front-Controller pattern — every request enters through `webroot/index.php`
- PSR-4 autoloading under the `App\` namespace rooted at `src/`
- Middleware pipeline (error handling -> assets -> routing -> body parsing -> CSRF)
- Convention-based routing: `/{controller}/{action}/*` fallbacks
- Separation of HTTP/CLI bootstrap via `PHP_SAPI` check in `src/Application.php`
- Server-side rendering only (no SPA); README tagline "SSR箱" refers to product name, not rendering choice
- Template/layer stubs exist (`src/Model/Table/`, `src/Model/Entity/`, `src/Controller/Component/`, `src/View/Cell/`, `src/View/Helper/`) but are empty (`.gitkeep` only)

## Layers

**HTTP Entry Layer (Front Controller):**
- Purpose: Accept all incoming HTTP requests, delegate to `App\Application`
- Location: `webroot/index.php` (real entry), `index.php` (thin root shim that requires the webroot file)
- Contains: Platform requirement check (`config/requirements.php`), Composer autoload, `Cake\Http\Server` instantiation
- Depends on: `vendor/autoload.php`, `App\Application`
- Used by: Apache `mod_rewrite` (see `.htaccess` files)

**Application Bootstrap Layer:**
- Purpose: Load configuration, register error handlers, configure Cache/DB/Email/Log/Security, define plugins
- Location: `src/Application.php`, `config/bootstrap.php`, `config/bootstrap_cli.php`, `config/paths.php`
- Contains: `Application::bootstrap()`, `Application::middleware()`, `Application::services()` (empty), `Application::bootstrapCli()`
- Depends on: `Cake\Http\BaseApplication`, `config/app.php`, `config/app_local.php` (gitignored)
- Used by: `webroot/index.php`, `bin/cake.php`

**Middleware Layer:**
- Purpose: Cross-cutting HTTP concerns configured in `src/Application.php` `middleware()`
- Location: `src/Application.php` lines 75-105
- Pipeline order (registered via `MiddlewareQueue`):
  1. `ErrorHandlerMiddleware` — catch exceptions in lower layers
  2. `AssetMiddleware` — serve plugin/theme assets
  3. `RoutingMiddleware` — match URL to controller/action
  4. `BodyParserMiddleware` — parse JSON/XML/form bodies
  5. `CsrfProtectionMiddleware` — CSRF tokens (httponly cookie)
- Depends on: Cake `Configure` values (`Error`, `Asset.cacheTime`)
- Used by: `BaseApplication::handle()` during request dispatch

**Routing Layer:**
- Purpose: Map URL paths to controller actions
- Location: `config/routes.php`
- Contains: Single `/` scope with `Pages::display`, `/pages/*` alias, and `$builder->fallbacks()` (generates `/{controller}` and `/{controller}/{action}/*`)
- Default route class: `DashedRoute` (converts CamelCase to dashed-urls)
- Depends on: `Cake\Routing\RouteBuilder`
- Used by: `RoutingMiddleware`

**Controller Layer:**
- Purpose: Handle request, invoke domain logic, set view vars, render response
- Location: `src/Controller/`
- Contains:
  - `src/Controller/AppController.php` — base class, loads `RequestHandler` + `Flash` components
  - `src/Controller/PagesController.php` — static content dispatcher (`display(...$path)`)
  - `src/Controller/ErrorController.php` — used by `ExceptionRenderer` for error responses
- Depends on: `Cake\Controller\Controller`, View layer
- Used by: `RoutingMiddleware` resolves which controller/action to invoke

**Model Layer (scaffolded, empty):**
- Purpose: ORM Tables (query builders, associations, finders) and Entities (row objects)
- Location: `src/Model/Table/`, `src/Model/Entity/`, `src/Model/Behavior/` (all `.gitkeep` only)
- Pattern: CakePHP Table/Entity split — `*Table` classes hold queries/validation; `*Entity` classes hold row state
- Expected depends on: `Cake\ORM\Table`, `Cake\ORM\Entity`, `ConnectionManager` (MySQL per `config/app.php`)
- Configured via: `FactoryLocator::add('Table', (new TableLocator())->allowFallbackClass(false))` in `src/Application.php:52` — forbids implicit Table classes, forces explicit `*Table` classes per model

**View Layer:**
- Purpose: Render templates to HTML/AJAX/email output
- Location:
  - `src/View/AppView.php` — default view class (empty `initialize()`, hook for loading helpers)
  - `src/View/AjaxView.php` — XHR response variant using `layout = 'ajax'`
  - `src/View/Helper/` — `.gitkeep` only
  - `src/View/Cell/` — `.gitkeep` only (ViewCells: reusable view fragments)
- Templates: `templates/` (see STRUCTURE.md)

**Console/CLI Layer:**
- Purpose: Artisan-style commands (migrations, bake, custom shells)
- Location: `bin/cake`, `bin/cake.php`, `src/Console/Installer.php`
- Entry: `bin/cake.php` builds `CommandRunner` with `App\Application`
- CLI-only plugins (`Bake`, `Migrations`) loaded in `Application::bootstrapCli()`

**Service Layer (planned, NOT YET CREATED):**
- Planned location per `README.md:94`: `src/Service/` — OAuth flows, SSR probability logic
- Current state: directory does not exist; no service classes present
- When added, services should be wired via `Application::services(ContainerInterface $container)` (currently empty at `src/Application.php:114`)

## Data Flow

**HTTP Request Flow:**

1. Request hits Apache; root `.htaccess` rewrites to `webroot/` (`.htaccess:11`)
2. `webroot/.htaccess` rewrites non-file requests to `webroot/index.php`
3. `webroot/index.php` loads `config/requirements.php`, `vendor/autoload.php`, instantiates `Cake\Http\Server` with `new App\Application(__DIR__/../config)`
4. `Server::run()` triggers `Application::bootstrap()` -> loads `config/bootstrap.php` (plus `config/bootstrap_cli.php` if CLI)
5. `Application::middleware()` returns the middleware queue; each middleware processes the PSR-7 request
6. `RoutingMiddleware` matches URL against `config/routes.php` -> resolves controller/action
7. Controller action runs, sets view vars via `$this->set()`
8. View renders template at `templates/{Controller}/{action}.php` into layout `templates/layout/default.php`
9. Response emitted via `$server->emit()`

**CLI Flow:**

1. `bin/cake <command>` executes `bin/cake.php`
2. `bin/cake.php` builds `CommandRunner(new Application(...), 'cake')` and runs `$argv`
3. `Application::bootstrap()` detects `PHP_SAPI === 'cli'` and calls `bootstrapCli()` instead of `FactoryLocator` setup
4. `bootstrapCli()` loads `Bake` (optional) and `Migrations` plugins

**State Management:**
- HTTP state: CakePHP sessions (`tmp/sessions/` directory configured; `config/schema/sessions.sql` provides MySQL-backed session table schema if session handler is switched to DB)
- Persistent state: MySQL via `Cake\Database\Driver\Mysql` (see `config/app.php:5`), configured under `Datasources` key loaded by `ConnectionManager::setConfig` in `config/bootstrap.php:168`

## Key Abstractions

**`App\Application` (src/Application.php):**
- Purpose: Composition root — defines middleware pipeline, plugin loading, DI container hooks
- Extends: `Cake\Http\BaseApplication`
- Pattern: Template Method — parent orchestrates boot; subclass overrides `bootstrap()`, `middleware()`, `services()`

**Controllers (src/Controller/):**
- Purpose: Request handlers, one class per resource
- Pattern: Thin controller; base `AppController` loads `RequestHandler` + `Flash` components for all children
- Convention: Controllers named `{Resource}Controller` extending `AppController`

**Views (src/View/):**
- Purpose: Presentation logic, helpers, cells
- Pattern: Two-step view — content template + layout wrapper
- Default layout fetched via `$this->fetch('content')` pattern in `templates/layout/default.php`

**Middleware (Cake PSR-15):**
- Purpose: Cross-cutting HTTP concerns
- Pattern: Onion/pipeline; each middleware wraps the next via `$handler->handle($request)`

## Entry Points

**HTTP Web:**
- Location: `webroot/index.php`
- Triggers: Apache rewrite from any URL under document root
- Responsibilities: Load requirements + autoloader, instantiate `Cake\Http\Server` with `App\Application`, emit response

**HTTP shim:**
- Location: `index.php` (project root)
- Triggers: Direct requests to project root if webroot is not the DocumentRoot (via `.htaccess` rewrite)
- Responsibilities: `require 'webroot/index.php'` — single-line delegate

**CLI:**
- Location: `bin/cake` (shell wrapper), `bin/cake.php` (PHP entry), `bin/cake.bat` (Windows)
- Triggers: Manual invocation (`bin/cake server`, `bin/cake migrations migrate`, `bin/cake bake ...`)
- Responsibilities: Build `CommandRunner` and execute command

**Test runner:**
- Location: `tests/bootstrap.php` (invoked by `phpunit.xml.dist`)
- Triggers: `composer test` / `phpunit`
- Responsibilities: Load autoloader + `config/bootstrap.php`, configure `debug_kit` SQLite connection, run migrations via `Migrations\TestSuite\Migrator` to build test DB schema

## Error Handling

**Strategy:** CakePHP ErrorTrap + ExceptionTrap + `ErrorHandlerMiddleware`

**Patterns:**
- `(new ErrorTrap(Configure::read('Error')))->register();` — registered in `config/bootstrap.php:125`
- `(new ExceptionTrap(Configure::read('Error')))->register();` — `config/bootstrap.php:126`
- `ErrorHandlerMiddleware` is the outermost middleware, catches exceptions thrown by inner layers (`src/Application.php:80`)
- `App\Controller\ErrorController` (`src/Controller/ErrorController.php`) renders error templates at `templates/Error/error400.php` and `templates/Error/error500.php`
- Controllers throw CakePHP HTTP exceptions for flow control: `NotFoundException`, `ForbiddenException` (example: `src/Controller/PagesController.php:52,70`)

## Cross-Cutting Concerns

**Logging:**
- Configured via `Log::setConfig(Configure::consume('Log'))` in `config/bootstrap.php:171`
- Backend: `Cake\Log\Engine\FileLog` (see `config/app.php:6`), files written to `logs/` (path defined in `config/paths.php:71`)
- CLI-specific log files: `cli-debug`, `cli-error` (overridden in `config/bootstrap_cli.php:31-34`)

**Validation:**
- Not yet implemented (no Table classes created). Convention: per-Table `validationDefault(Validator $validator)` method.

**Authentication:**
- Not yet implemented. Planned: Bluesky AT Protocol OAuth 2.0 (per `README.md:30`); ES256 key pair stored in `config/keys/` (gitignored). No auth middleware/component installed yet. `cakephp/authentication` plugin is NOT in `composer.json`.

**CSRF:**
- `CsrfProtectionMiddleware` enabled with `httponly => true` in `src/Application.php:100`
- Applied to every route by default

**Asset cache:**
- `AssetMiddleware` with `cacheTime` from `Configure::read('Asset.cacheTime')` (`src/Application.php:83-85`)

**Mobile detection:**
- `mobiledetect/mobiledetectlib` ^3.74 registered as request detectors in `config/bootstrap.php:179-188` — enables `$request->is('mobile')` / `$request->is('tablet')` checks

**Timezone & encoding:**
- `date_default_timezone_set(Configure::read('App.defaultTimezone'))` — default `UTC` (`config/app.php:53`)
- `mb_internal_encoding(Configure::read('App.encoding'))` — default `UTF-8`

**Type mapping:**
- `TypeFactory::map('time', StringType::class)` in `config/bootstrap.php:214` — MySQL `TIME` columns returned as PHP strings (no native Cake time type)

---

*Architecture analysis: 2026-04-22*
