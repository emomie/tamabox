# Coding Conventions

**Analysis Date:** 2026-04-22

## Overview

Project is a CakePHP 4.5 skeleton (fresh scaffold, discovery-phase). All source under `src/` is framework boilerplate. Conventions are **enforced by tooling**, not by accumulated custom patterns — follow CakePHP's `CakePHP` ruleset (a PSR-12 superset) plus PHPStan level 8.

**Tooling sources of truth:**
- `phpcs.xml` — PHP_CodeSniffer ruleset (CakePHP standard)
- `phpstan.neon` — PHPStan level 8 on `src/` (excludes `src/Console/Installer.php`)
- `.editorconfig` — whitespace/EOL rules
- `composer.json` scripts — `composer cs-check`, `composer cs-fix`, `composer test`, `composer check`

## Naming Patterns

**Files:**
- One class per file, PascalCase filename matching class name (PSR-4 via `App\` → `src/`).
- Test files: `{ClassName}Test.php` mirroring source path under `tests/TestCase/`.
- Examples: `src/Application.php`, `src/Controller/PagesController.php`, `tests/TestCase/ApplicationTest.php`.

**Namespaces:**
- Production code: `App\...` rooted at `src/`. See `composer.json` autoload block.
- Tests: `App\Test\...` rooted at `tests/`. See `composer.json` autoload-dev.
- Sub-namespaces mirror directory layout (e.g. `App\Controller`, `App\View`, `App\Console`).

**Classes:**
- PascalCase: `Application`, `PagesController`, `AppView`, `AjaxView`, `ErrorController`.
- Controllers end in `Controller` (`PagesController`, `ErrorController`).
- Views end in `View` (`AppView`, `AjaxView`).
- Base "App*" classes are the local parents per CakePHP convention (`AppController`, `AppView`).

**Functions / Methods:**
- camelCase: `bootstrap()`, `middleware()`, `bootstrapCli()`, `createAppLocalConfig()`, `setSecuritySaltInFile()`.
- Hook methods use CakePHP-defined names: `initialize()`, `beforeFilter()`, `beforeRender()`, `afterFilter()`.

**Variables:**
- camelCase locals and properties: `$middlewareQueue`, `$rootDir`, `$appLocalConfig`, `$subpage`.
- `$io` / `$dir` / `$file` short names are used in `src/Console/Installer.php` where Composer passes them.

**Constants:**
- UPPER_SNAKE_CASE class constants: `Installer::WRITABLE_DIRS` in `src/Console/Installer.php`.

**Directories:**
- PascalCase for namespaced code dirs: `src/Controller/`, `src/View/`, `src/Model/Entity/`, `src/Model/Table/`, `src/Model/Behavior/`, `src/View/Helper/`, `src/View/Cell/`, `src/Controller/Component/`.
- lowercase for non-namespaced infra: `config/`, `templates/`, `webroot/`, `logs/`, `tmp/`, `bin/`, `plugins/`, `resources/`.

## Code Style

**Ruleset:**
- `CakePHP` coding standard via `cakephp/cakephp-codesniffer ^4.5` (`phpcs.xml` references `../../cakephp/cakephp-codesniffer`).
- Scope: `src/` and `tests/` only.
- Run: `composer cs-check` (or `phpcs --colors -p`).
- Autofix: `composer cs-fix` (or `phpcbf --colors -p`).

**Formatting (`.editorconfig`):**
- Indent: 4 spaces, never tabs (except `Makefile`).
- End-of-line: LF (CRLF only for `.bat`).
- Final newline required; trailing whitespace trimmed.
- YAML: 2-space indent.
- Twig: no final newline.

**File header:**
- Every PHP source and test file begins with `<?php` on line 1, `declare(strict_types=1);` on line 2, then the CakePHP MIT header docblock. See `src/Application.php:1-16` as the canonical template.

**Static analysis:**
- PHPStan level 8 on `src/` only (`phpstan.neon`).
- `checkMissingIterableValueType: false` — array generic types (`array<string, mixed>`) not required in every docblock.
- `treatPhpDocTypesAsCertain: false` — narrower PHPDoc types are treated as hints, not facts.
- `checkGenericClassInNonGenericObjectType: false` — generics mismatches with CakePHP non-generic classes are silenced.
- `src/Console/Installer.php` excluded (Composer script plumbing).
- Run: `vendor/bin/phpstan analyse` (PHPStan is suggested but not in `require-dev`; install locally if needed).

## Type Declarations

**Strict types:**
- `declare(strict_types=1);` is mandatory on every PHP file (all existing files in `src/` and `tests/` use it).

**Signatures:**
- Use PHP 7.4+ type hints on parameters and return types wherever possible.
- Examples from `src/`:
  - `public function bootstrap(): void` — `src/Application.php:44`
  - `public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue` — `src/Application.php:75`
  - `public function services(ContainerInterface $container): void` — `src/Application.php:114`
  - `public function display(string ...$path): ?Response` — `src/Controller/PagesController.php:46` (variadic + nullable return)
- Nullable return `?Response`, `void`, and union-via-docblock are normal. See `ErrorController::beforeFilter` — typed param, no return type because it can return `Response|null|void` (documented in docblock only).

**Property types:**
- Typed properties may be used (PHP 7.4+), but CakePHP inherited properties (e.g. `AjaxView::$layout`) use legacy `public $layout = 'ajax';` style when overriding framework contracts. See `src/View/AjaxView.php:33`.

## Import Organization

**Grouping:**
1. `namespace App\...;` line.
2. Blank line.
3. `use` statements (alphabetized, one group).
4. Blank line before class docblock.

**Example (`src/Application.php:17-29`):**
```php
namespace App;

use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
```

**Alphabetical sort** is enforced by the CakePHP sniff set. No aliasing observed. Global classes (`Exception`, `InvalidArgumentException`) are `use`-imported rather than referenced with a leading backslash — see `src/Console/Installer.php:26` and `tests/TestCase/ApplicationTest.php:27`.

**No path aliases:** PSR-4 autoload via Composer is the only resolution mechanism. `App\` → `src/`, `App\Test\` → `tests/`.

## Docblocks

**Required for every class, method, and property.** The `CakePHP` sniff enforces this.

**Class docblock — brief summary and optional `@link`:**
```php
/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication
```
See `src/Application.php:31-37`.

**Method docblock — params, return, throws:**
```php
/**
 * Displays a view
 *
 * @param string ...$path Path segments.
 * @return \Cake\Http\Response|null
 * @throws \Cake\Http\Exception\ForbiddenException When a directory traversal attempt.
 * @throws \Cake\View\Exception\MissingTemplateException When the view file could not
 *   be found and in debug mode.
 * @throws \Cake\Http\Exception\NotFoundException When the view file could not
 *   be found and not in debug mode.
 */
public function display(string ...$path): ?Response
```
See `src/Controller/PagesController.php:34-46`.

**Types in docblocks:**
- Use fully-qualified class names with leading backslash inside `@param`/`@return`/`@throws`: `\Cake\Http\Response`, `\Cake\Event\EventInterface<\App\Controller\ErrorController>`.
- Generic event type parameters are used: `\Cake\Event\EventInterface<\App\Controller\ErrorController>` — see `src/Controller/ErrorController.php:41`.

**File-level docblock:**
- Keep the CakePHP MIT copyright header on new files (all current files carry it). If you replace with an application-specific header, keep the `@license` and `@since` tags for PHPCS.

## Error Handling

**Exception style:**
- Throw specific CakePHP HTTP exceptions for request-level errors — they auto-map to HTTP status codes via `ErrorHandlerMiddleware`.
  - `ForbiddenException` → 403 (e.g. directory traversal, `src/Controller/PagesController.php:52`)
  - `NotFoundException` → 404 (e.g. missing template outside debug, `src/Controller/PagesController.php:70`)
- Throw framework-specific exceptions from `Cake\*\Exception\...` rather than generic `\Exception` for request-cycle errors.
- Use plain `\Exception` / `\InvalidArgumentException` only for low-level infrastructure (`src/Console/Installer.php:124`).

**Try/catch pattern — rethrow or wrap:**
```php
try {
    return $this->render(implode('/', $path));
} catch (MissingTemplateException $exception) {
    if (Configure::read('debug')) {
        throw $exception;
    }
    throw new NotFoundException();
}
```
See `src/Controller/PagesController.php:64-71`. Pattern: catch framework exception, inspect config, either rethrow or throw a friendlier one.

**Centralized handling:**
- `ErrorHandlerMiddleware` is the first middleware in the queue — see `src/Application.php:80`. All uncaught exceptions below it become rendered error responses via `ErrorController` (`src/Controller/ErrorController.php`).
- `ErrorController` sets the template path in `beforeRender()` to `Error/` — see `src/Controller/ErrorController.php:58`.

**No custom exceptions yet.** When business logic exceptions are needed, place them in `src/Exception/` (create the directory) with PSR-4 namespace `App\Exception\...`.

## Logging

**Framework:** CakePHP's `Cake\Log\Log` facade (loaded by `config/bootstrap.php`). Not yet used in any `src/` file.

**When to log:** Not established yet. For new business logic, prefer CakePHP's `Log::error()` / `Log::warning()` / `Log::info()` rather than `error_log()` or `echo`.

**Console output:** The installer uses Composer's `$io->write()` for CLI messages — see `src/Console/Installer.php:85`. That is specific to Composer scripts; do not use `$io` in regular application code.

## Comments

**When to comment:**
- Docblocks on every class, method, and non-private property (sniff-enforced).
- Inline `//` comments to explain *why* for non-obvious middleware ordering, security decisions, and config toggles — see the comments above each `->add(...)` call in `src/Application.php:77-102`.
- Multi-line `/* ... */` for longer explanations inside methods — see `src/Application.php:58-61`.

**Language:** English for code comments and docblocks (framework-facing). Japanese is acceptable in `README.md` and design docs but keep source-level docblocks English so CakePHP sniffs and IDE tooling render cleanly.

## Function / Method Design

**Size:** Methods are short (mostly < 30 lines). Longest observed is `Installer::setFolderPermissions()` at ~50 lines (`src/Console/Installer.php:116-171`) and it uses nested closures to keep responsibilities isolated.

**Parameters:**
- Prefer typed, named parameters. Variadics (`string ...$path`) when the count is unknown — see `PagesController::display()`.
- Static installer methods accept `$dir, $io, ...` positionally — acceptable for Composer script hooks, not a pattern to copy into business code.

**Returns:**
- Explicit return type on every method. Use `: void` when nothing is returned.
- Nullable return types (`: ?Response`) preferred over union docblocks where the value set is "X or null".

**Visibility:**
- `public` for framework hook methods (`bootstrap`, `initialize`, `middleware`, `services`, `beforeFilter`, `beforeRender`, `afterFilter`, `display`).
- `protected` for internal-but-subclassable helpers (`bootstrapCli()` in `src/Application.php:125`).
- `private` for truly local helpers (none yet).
- `public static` only on Composer script entry points (`Installer::*`).

## Module Design

**Exports:**
- One class per file; the class is the module's "export". No function-level globals or constants.

**Barrel files:** Not used. CakePHP's autoloader walks PSR-4 — do not introduce `index.php`-style re-export files.

**Directory roles (CakePHP convention):**
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

**Applied globally in `src/Application.php`:**
- `CsrfProtectionMiddleware` with `httponly => true` (`src/Application.php:100-102`). New controllers inherit CSRF protection automatically.
- `FormProtectionComponent` is commented out in `AppController::initialize()` (`src/Controller/AppController.php:51`). Enable it per-controller when adding form submissions that need field-tampering protection.
- Directory-traversal guard pattern: `in_array('..', $path, true) || in_array('.', $path, true)` → `throw new ForbiddenException()` (`src/Controller/PagesController.php:51-53`). Replicate this guard in any controller that interpolates user path segments.

## Commit & CI Hooks

**`composer check`:** Runs `test` then `cs-check` (`composer.json:41-44`). Treat this as the pre-commit gate.

**GitHub Actions:** Not configured yet. `.github/` contains only templates and `dependabot.yml` (weekly Composer + Actions updates). Add `.github/workflows/ci.yml` when CI is needed — it should run `composer install && composer check`.

---

*Convention analysis: 2026-04-22*
