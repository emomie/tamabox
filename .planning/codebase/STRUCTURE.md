# Codebase Structure

**Analysis Date:** 2026-04-22

## Directory Layout

```
tamabox/
├── .editorconfig            # Editor settings (indent, charset)
├── .github/                 # GitHub templates + dependabot config
│   ├── ISSUE_TEMPLATE.md
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── dependabot.yml
├── .gitattributes
├── .gitignore
├── .htaccess                # Root Apache rewrite -> webroot/
├── .planning/               # GSD planning artifacts (this directory's parent)
│   └── codebase/            # Codebase map docs (ARCHITECTURE, STRUCTURE, etc.)
├── README.md                # Project overview (Japanese, tamabox/SSR箱 product notes)
├── bin/                     # CLI entry binaries
│   ├── cake                 # Unix shell wrapper -> cake.php
│   ├── cake.bat             # Windows wrapper
│   ├── cake.php             # PHP entry building CommandRunner
│   └── bash_completion.sh
├── composer.json            # PHP dependencies + autoload (PSR-4 App\ -> src/)
├── config/                  # Application configuration
│   ├── .env.example         # Env var template (copy to .env, gitignored)
│   ├── app.php              # Public config (debug, App, Security, DB, cache, log)
│   ├── app_local.example.php# Local override template (copy to app_local.php)
│   ├── bootstrap.php        # Runtime bootstrap (web + CLI shared)
│   ├── bootstrap_cli.php    # CLI-only bootstrap extensions
│   ├── keys/                # OAuth ES256 key pair (gitignored, only .gitkeep present)
│   ├── paths.php            # Path constants (ROOT, APP, CONFIG, WWW_ROOT, TMP, LOGS)
│   ├── requirements.php     # PHP version / extension checks
│   ├── routes.php           # URL -> controller/action mappings
│   └── schema/              # Ancillary SQL schemas
│       ├── i18n.sql         # i18n translations table (optional)
│       └── sessions.sql     # Sessions table (optional, for DB session handler)
├── index.php                # Root shim: require 'webroot/index.php'
├── phpcs.xml                # PHP_CodeSniffer config (CakePHP ruleset)
├── phpstan.neon             # PHPStan config
├── phpunit.xml.dist         # PHPUnit config
├── plugins/                 # CakePHP plugins (empty; .gitkeep only)
├── resources/               # Non-class resources, locales (empty; .gitkeep only)
├── src/                     # Application PHP classes (PSR-4 root under App\)
│   ├── Application.php      # App composition root (middleware, plugins, DI)
│   ├── Console/
│   │   └── Installer.php    # Composer post-install hooks (salt gen, dir perms)
│   ├── Controller/
│   │   ├── AppController.php      # Base controller (loads RequestHandler, Flash)
│   │   ├── ErrorController.php    # Error response renderer
│   │   ├── PagesController.php    # Static page dispatcher (display(...$path))
│   │   └── Component/             # Custom components (empty; .gitkeep only)
│   ├── Model/
│   │   ├── Behavior/        # ORM behaviors (empty; .gitkeep only)
│   │   ├── Entity/          # Row entity classes (empty; .gitkeep only)
│   │   └── Table/           # ORM Table classes (empty; .gitkeep only)
│   └── View/
│       ├── AjaxView.php     # XHR response view (ajax layout)
│       ├── AppView.php      # Base view
│       ├── Cell/            # ViewCells (empty; .gitkeep only)
│       └── Helper/          # View helpers (empty; .gitkeep only)
├── templates/               # View templates (no .php extension folder = layer; lowercase = non-controller-bound)
│   ├── Error/               # Error response templates
│   │   ├── error400.php
│   │   └── error500.php
│   ├── Pages/               # Templates served by PagesController::display
│   │   └── home.php
│   ├── cell/                # ViewCell templates (empty; .gitkeep only)
│   ├── element/             # Reusable snippets (<<< $this->element('...') >>>)
│   │   └── flash/           # Flash message partials
│   │       ├── default.php
│   │       ├── error.php
│   │       ├── info.php
│   │       ├── success.php
│   │       └── warning.php
│   ├── email/               # Mailer content templates
│   │   ├── html/default.php
│   │   └── text/default.php
│   └── layout/              # Wrapper layouts (default, ajax, error, email)
│       ├── ajax.php
│       ├── default.php
│       ├── email/
│       │   ├── html/default.php
│       │   └── text/default.php
│       └── error.php
├── tests/                   # PHPUnit tests
│   ├── Fixture/             # Test fixtures (empty; .gitkeep only)
│   ├── TestCase/
│   │   ├── ApplicationTest.php
│   │   ├── Controller/
│   │   │   ├── Component/   # (.gitkeep only)
│   │   │   └── PagesControllerTest.php
│   │   ├── Model/Behavior/  # (.gitkeep only)
│   │   └── View/Helper/     # (.gitkeep only)
│   ├── bootstrap.php        # Test bootstrap (loads config/bootstrap.php + migrator)
│   └── schema.sql           # Fallback SQL schema for tests (not currently used)
├── tmp/                     # Runtime scratch (writable; not served)
│   ├── cache/
│   ├── sessions/            # File-based PHP sessions
│   └── tests/
├── vendor/                  # Composer dependencies (empty; .gitkeep only — needs composer install)
└── webroot/                 # Public-facing DocumentRoot (only this is served by lollipop shared host)
    ├── .htaccess            # Rewrite non-files to index.php
    ├── css/                 # Framework default stylesheets (cake.css, milligram, normalize, fonts, home)
    ├── favicon.ico
    ├── font/                # Webfonts (cakedingbats, raleway)
    ├── img/                 # Default CakePHP images (logos)
    ├── index.php            # Front Controller entry
    └── js/                  # (empty; .gitkeep only)
```

## Directory Purposes

**`.planning/`:**
- Purpose: GSD workflow artifacts (planning, codebase maps, phase docs)
- Contains: `codebase/` with ARCHITECTURE.md, STRUCTURE.md, and other maps
- Generated: Yes (written by GSD mapper agents)
- Committed: Typically yes (project-specific)

**`bin/`:**
- Purpose: CLI executables
- Contains: `cake` wrapper scripts that delegate to `cake.php` -> `App\Application` via `CommandRunner`
- Key files: `bin/cake.php` (entry point for all console commands)

**`config/`:**
- Purpose: Application configuration, bootstrap, routes, schemas, crypto keys
- Contains: PHP config arrays (`app.php`, `app_local.php`), bootstrap sequences, route definitions, env templates, SQL schema extras, OAuth key pair
- Key files: `config/bootstrap.php`, `config/routes.php`, `config/app.php`, `config/app_local.php` (gitignored), `config/.env` (gitignored)

**`plugins/`:**
- Purpose: Locally-developed CakePHP plugins (vendor-style modules inside the repo)
- Contains: Currently empty (`.gitkeep` only)
- Note: Third-party plugins installed via Composer live in `vendor/`

**`resources/`:**
- Purpose: Non-class resources — expected subdir `resources/locales/` for i18n translation `.po` files
- Contains: Currently empty (`.gitkeep` only)
- Path constant: `RESOURCES` (defined in `config/paths.php:81`)

**`src/`:**
- Purpose: Application PHP classes, autoloaded under `App\` namespace (PSR-4, per `composer.json:29`)
- Contains: Controllers, Models (Tables/Entities/Behaviors), Views, Console hooks
- Key files: `src/Application.php` (composition root)

**`templates/`:**
- Purpose: View templates (`.php` files with HTML + short PHP echo tags)
- Contains: Per-controller subdirectories named in PascalCase matching controller; plus non-controller layers `cell/`, `element/`, `email/`, `layout/`
- Convention: Templates resolved by CakePHP view-render hierarchy

**`tests/`:**
- Purpose: PHPUnit tests mirroring `src/` structure
- Contains: `TestCase/` tree, `Fixture/`, `bootstrap.php`, `schema.sql`
- Autoloaded: `App\Test\` namespace -> `tests/` (`composer.json:34`)

**`tmp/`:**
- Purpose: Writable runtime directory for cache, sessions, test artifacts
- Committed: Only `.gitkeep` stubs; real contents gitignored
- Path constant: `TMP` (`config/paths.php:66`)

**`vendor/`:**
- Purpose: Composer-installed dependencies (CakePHP core, plugins, dev tools)
- Committed: NO — currently only `.gitkeep`; must run `composer install`
- Path: `CAKE_CORE_INCLUDE_PATH` = `vendor/cakephp/cakephp` (`config/paths.php:88`)

**`webroot/`:**
- Purpose: Public-facing DocumentRoot. On lollipop shared hosting this is the only directory exposed to the internet
- Contains: Front controller `index.php`, `.htaccess`, static assets (`css/`, `js/`, `img/`, `font/`), favicon
- Critical: Do NOT put secrets or PHP logic outside of `index.php` here

## Key File Locations

**Entry Points:**
- `webroot/index.php`: HTTP front controller
- `index.php`: Root shim delegating to `webroot/index.php`
- `bin/cake.php`: CLI front controller
- `tests/bootstrap.php`: PHPUnit bootstrap

**Configuration:**
- `config/app.php`: Public config (not gitignored; safe values and `env()` references)
- `config/app_local.php`: Local secrets/overrides (gitignored; created from `.example`)
- `config/.env`: Environment variable file (gitignored; created from `.env.example`; `.env` loading in `bootstrap.php:63` is currently commented out — uncomment if using)
- `config/bootstrap.php`: Runtime init — configure Cache/DB/Email/Log/Security
- `config/bootstrap_cli.php`: CLI-only overrides
- `config/routes.php`: URL routing
- `config/paths.php`: PHP path constants (ROOT, APP, CONFIG, WWW_ROOT, TMP, LOGS, RESOURCES, CAKE)
- `config/requirements.php`: Pre-bootstrap PHP requirement check
- `config/keys/`: OAuth ES256 key pair location (gitignored)
- `phpunit.xml.dist`: PHPUnit configuration
- `phpcs.xml`: PHP_CodeSniffer (CakePHP coding standard)
- `phpstan.neon`: PHPStan static analysis config
- `.editorconfig`: Editor defaults

**Core Logic:**
- `src/Application.php`: Application class — middleware pipeline, plugin loading
- `src/Controller/AppController.php`: Base controller — components loaded for every request
- `src/View/AppView.php`: Base view — helpers loaded for every template
- `src/Controller/ErrorController.php`: Error response rendering

**Testing:**
- `tests/bootstrap.php`: Test bootstrap with `Migrations\TestSuite\Migrator` to build schema
- `tests/TestCase/ApplicationTest.php`: Application class sanity test
- `tests/TestCase/Controller/PagesControllerTest.php`: PagesController test

**Build/Deploy:**
- `composer.json`: Dependencies and scripts (`composer test`, `composer cs-check`, `composer cs-fix`)
- No CI/CD pipeline files detected besides `.github/dependabot.yml`
- Deploy: Lollipop Git deploy triggered by `main` push (per `README.md:106`)

## Naming Conventions

**Files:**
- Classes: PascalCase, one class per file (`PagesController.php`, `AppController.php`, `AjaxView.php`) — PSR-4
- Templates: lowercase action name with `.php` extension (`home.php`, `error400.php`)
- Config: lowercase with underscores (`app.php`, `bootstrap_cli.php`, `app_local.php`)
- SQL extras: lowercase plural table name (`sessions.sql`, `i18n.sql`)

**Directories:**
- Class namespaces mirror directories: `App\Controller\PagesController` -> `src/Controller/PagesController.php`
- Template layer directories under `templates/`:
  - PascalCase (`Error/`, `Pages/`) for controller-bound templates — matches Controller class short name
  - lowercase (`cell/`, `element/`, `email/`, `layout/`) for non-controller-bound template kinds
- Plugins: PascalCase plugin name at `plugins/{PluginName}/`

**Classes:**
- Controllers: `{Resource}Controller` extending `AppController`
- Tables: `{Plural}Table` extending `Cake\ORM\Table` (not yet present)
- Entities: `{Singular}` extending `Cake\ORM\Entity` (not yet present)
- Views: `{Name}View` extending `AppView`
- Helpers: `{Name}Helper`
- Components: `{Name}Component`
- Behaviors: `{Name}Behavior`

**URLs (DashedRoute):**
- CamelCase controllers become dashed URLs: `UserProfilesController::view` -> `/user-profiles/view`
- Configured via `$routes->setRouteClass(DashedRoute::class)` in `config/routes.php:50`

## Where to Add New Code

**New HTTP resource (controller + views):**
- Controller: `src/Controller/{Resource}Controller.php` extending `AppController`
- Templates: `templates/{Resource}/{action}.php`
- Tests: `tests/TestCase/Controller/{Resource}ControllerTest.php`
- Optional route customization: `config/routes.php` (fallback route covers `/resource/action/*` automatically)

**New database model:**
- Table class: `src/Model/Table/{Plural}Table.php` extending `Cake\ORM\Table`
- Entity class: `src/Model/Entity/{Singular}.php` extending `Cake\ORM\Entity`
- Migration: `bin/cake bake migration Create{Plural}` — creates file in `config/Migrations/` (directory will be auto-created)
- Table tests: `tests/TestCase/Model/Table/{Plural}TableTest.php`
- Fixture: `tests/Fixture/{Plural}Fixture.php`

**New domain service (e.g., OAuth, SSR probability):**
- Create `src/Service/` directory (does not yet exist; README.md:94 plans this layer)
- Class: `src/Service/{Name}Service.php` in namespace `App\Service`
- Wire into DI container via `Application::services(ContainerInterface $container)` in `src/Application.php:114`
- Tests: `tests/TestCase/Service/{Name}ServiceTest.php`

**New middleware:**
- Class: `src/Middleware/{Name}Middleware.php` (directory does not yet exist) in namespace `App\Middleware`
- Register in `src/Application.php::middleware()` at appropriate pipeline position

**New view helper:**
- Class: `src/View/Helper/{Name}Helper.php` in namespace `App\View\Helper`
- Load in `src/View/AppView.php::initialize()` via `$this->loadHelper('{Name}')`

**New view cell:**
- Class: `src/View/Cell/{Name}Cell.php` in namespace `App\View\Cell`
- Template: `templates/cell/{Name}/{action}.php`

**New controller component:**
- Class: `src/Controller/Component/{Name}Component.php` in namespace `App\Controller\Component`
- Load in `src/Controller/AppController.php::initialize()` via `$this->loadComponent('{Name}')`

**New console command:**
- Class: `src/Command/{Name}Command.php` (directory does not yet exist) in namespace `App\Command`
- Auto-discovered by `CommandRunner`; invoked as `bin/cake {name}`

**New CakePHP plugin (locally developed):**
- Directory: `plugins/{PluginName}/` with its own `src/`, `templates/`, `config/`
- Register in `src/Application.php::bootstrap()` via `$this->addPlugin('{PluginName}')`

**New email template:**
- HTML: `templates/email/html/{template}.php`
- Text: `templates/email/text/{template}.php`
- Layout: `templates/layout/email/{html,text}/default.php` (already exists)

**Static/landing pages:**
- Template: `templates/Pages/{name}.php` — accessible at `/pages/{name}` via `PagesController::display`

**Static assets:**
- CSS: `webroot/css/{name}.css` — reference via `$this->Html->css('name')`
- JS: `webroot/js/{name}.js` — reference via `$this->Html->script('name')`
- Images: `webroot/img/{name}.{ext}` — reference via `$this->Html->image('name.ext')`
- Fonts: `webroot/font/{file}` — reference from CSS

## Special Directories

**`config/keys/`:**
- Purpose: OAuth ES256 private/public key pair (per `README.md:64-68`)
- Generated: Yes (`openssl ecparam ...`)
- Committed: NO — gitignored (only `.gitkeep` tracked)
- Permissions: `chmod 600 private.key`, `chmod 644 public.key`

**`tmp/`:**
- Purpose: Writable runtime scratch (cache, sessions, test artifacts)
- Subdirs required by `Installer::WRITABLE_DIRS` (`src/Console/Installer.php:37-46`): `tmp`, `tmp/cache`, `tmp/cache/models`, `tmp/cache/persistent`, `tmp/cache/views`, `tmp/sessions`, `tmp/tests`
- Generated: Yes (via `composer install` post-install hook `App\Console\Installer::postInstall`)
- Committed: Only `.gitkeep` sentinels; real contents gitignored

**`logs/`:**
- Purpose: FileLog output destination (path `LOGS` in `config/paths.php:71`)
- Generated: Yes at runtime
- Committed: NO — directory not even present in repo yet, will be auto-created

**`vendor/`:**
- Purpose: Composer-installed dependencies
- Generated: Yes (`composer install`)
- Committed: NO — gitignored except `.gitkeep`

**`.planning/`:**
- Purpose: GSD workflow state (codebase maps, phase plans)
- Generated: Yes (by GSD agents)
- Committed: Yes (traceability / onboarding)

**`resources/locales/`:**
- Purpose: i18n translation `.po` / `.pot` files (not yet present; configured in `config/app.php:66`)
- Generated: Via `bin/cake i18n extract` when needed

---

*Structure analysis: 2026-04-22*
