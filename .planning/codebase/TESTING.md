# Testing Patterns

**Analysis Date:** 2026-04-22

## Overview

Only **two test files** exist today — both are CakePHP skeleton boilerplate that verify framework wiring (`Application` bootstrap/middleware, `PagesController` static display). No domain tests exist yet. This document captures the **established conventions those samples demonstrate**, so new tests are shaped consistently.

## Test Framework

**Runner:**
- PHPUnit `^9.6` (`composer.json:19`).
- Config: `phpunit.xml.dist` (root).
- Bootstrap: `tests/bootstrap.php`.

**Assertion library:**
- PHPUnit built-ins + CakePHP's `Cake\TestSuite\TestCase` base class + `Cake\TestSuite\IntegrationTestTrait`.
- CakePHP-specific response assertions: `assertResponseOk()`, `assertResponseError()`, `assertResponseFailure()`, `assertResponseCode()`, `assertResponseContains()`, `assertResponseNotContains()`. See `tests/TestCase/Controller/PagesControllerTest.php`.

**CakePHP fixture extension:**
- `Cake\TestSuite\Fixture\PHPUnitExtension` is registered in `phpunit.xml.dist:23`. Any class-level `protected $fixtures = [...]` on a test case will be picked up and applied per-test.

**Run commands:**
```bash
composer test              # Run the "app" suite (tests/TestCase/) via phpunit
vendor/bin/phpunit         # Same thing, direct invocation
vendor/bin/phpunit --filter testDisplay tests/TestCase/Controller/PagesControllerTest.php
composer cs-check          # PHPCS (style), complementary to tests
composer check             # Shortcut: test + cs-check
```
Scripts defined in `composer.json:41-48`.

**Coverage command:** Not defined as a script. Run manually: `vendor/bin/phpunit --coverage-html tmp/coverage` (requires Xdebug or PCOV). `phpunit.xml.dist:27-35` already whitelists `src/` and `plugins/*/src/` and excludes `src/Console/Installer.php`.

## Bootstrap & Database Setup

**`tests/bootstrap.php` (the single bootstrap entry point):**
- Loads Composer autoloader and the main `config/bootstrap.php` so tests run with the same `Configure` state as the app.
- Sets `App.fullBaseUrl` to `http://localhost` when not already defined — makes `IntegrationTestTrait` URL assertions stable in CLI.
- Registers a SQLite connection `test_debug_kit` aliased as `debug_kit` — prevents DebugKit from erroring out when tests boot the full app under `debug=true` (see `tests/bootstrap.php:39-48`).
- Fixes `session_id('cli')` before any stdout writes (PHP 7.2+ requirement for session integration tests).
- Runs `(new Migrator())->run()` at the tail to sync the test DB schema from `config/Migrations/` automatically. If you are not using migrations, swap for `SchemaLoader::loadSqlFiles('./tests/schema.sql', 'test')` — the stub `tests/schema.sql` exists but is empty.

**Test DB connection:** Must be named `test` in `config/app_local.php` → `Datasources.test`. The `Migrator` writes to whatever is configured there. No separate test-only config file.

## Test File Organization

**Location:**
- All tests under `tests/TestCase/`, mirroring `src/` layout.
- `tests/TestCase/ApplicationTest.php` ↔ `src/Application.php`.
- `tests/TestCase/Controller/PagesControllerTest.php` ↔ `src/Controller/PagesController.php`.
- Placeholder `.gitkeep` directories already exist for `tests/TestCase/Controller/Component/`, `tests/TestCase/Model/Behavior/`, `tests/TestCase/View/Helper/`. Add tests alongside these paths when sub-class code arrives.

**Naming:**
- File: `{ClassName}Test.php` (suffix `Test`).
- Class: `{ClassName}Test extends Cake\TestSuite\TestCase`.
- Methods: `test{BehaviorBeingTested}` — camelCase starting with `test`. Examples in `tests/TestCase/Controller/PagesControllerTest.php`:
  - `testDisplay`
  - `testMissingTemplate`
  - `testMissingTemplateInDebug`
  - `testDirectoryTraversalProtection`
  - `testCsrfAppliedError`
  - `testCsrfAppliedOk`

**Namespace:**
- `App\Test\TestCase\...` mirroring the folder layout (`composer.json:33-36` declares `App\Test\` → `tests/`).

**Directory pattern:**
```
tests/
├── Fixture/                      # Cake\TestSuite\Fixture\TestFixture subclasses
├── TestCase/
│   ├── ApplicationTest.php
│   ├── Controller/
│   │   ├── Component/
│   │   └── PagesControllerTest.php
│   ├── Model/
│   │   └── Behavior/
│   └── View/
│       └── Helper/
├── bootstrap.php
└── schema.sql                    # empty stub; migrations are the source of truth
```

## Test Structure

**Baseline class template (from `tests/TestCase/Controller/PagesControllerTest.php`):**
```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * PagesControllerTest class
 *
 * @uses \App\Controller\PagesController
 */
class PagesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * testDisplay method
     *
     * @return void
     */
    public function testDisplay()
    {
        Configure::write('debug', true);
        $this->get('/pages/home');
        $this->assertResponseOk();
        $this->assertResponseContains('CakePHP');
    }
}
```

**Patterns:**
- Every test method has its own docblock with a one-line summary and `@return void`.
- Class docblock includes `@uses \Fully\Qualified\ClassUnderTest` — tells IDE/static-analysis what this suite covers.
- `declare(strict_types=1);` on line 2, same as `src/`.
- **No `setUp()` / `tearDown()` overrides yet.** When they are needed, call `parent::setUp()` / `parent::tearDown()` first — CakePHP's `TestCase` does plumbing there.
- Arrange / Act / Assert is implicit and compact — these integration tests are 3-5 lines each.

**Base class choice:**
- HTTP / controller tests: `extends Cake\TestSuite\TestCase` + `use Cake\TestSuite\IntegrationTestTrait` (gives `$this->get()`, `$this->post()`, `enableCsrfToken()`, response assertions).
- Application-wiring tests: same base, `IntegrationTestTrait` still optional (`ApplicationTest.php:34` uses it even though the test body does not — keep it for consistency).
- Pure unit tests on services/utilities: `extends Cake\TestSuite\TestCase` without the trait.

## Integration Test Patterns

**HTTP request simulation — `IntegrationTestTrait`:**
```php
$this->get('/pages/home');
$this->post('/pages/home', ['hello' => 'world']);
$this->enableCsrfToken();                       // simulate a valid CSRF token cookie
$this->assertResponseOk();                      // 2xx
$this->assertResponseError();                   // 4xx
$this->assertResponseFailure();                 // 5xx
$this->assertResponseCode(403);
$this->assertResponseContains('Forbidden');
$this->assertResponseNotContains('CSRF');
```
See `tests/TestCase/Controller/PagesControllerTest.php:38-114`.

**Toggling debug mode per test — use `Configure::write('debug', ...)` inside the test:**
```php
public function testMissingTemplateInDebug()
{
    Configure::write('debug', true);
    $this->get('/pages/not_existing');
    $this->assertResponseFailure();
    $this->assertResponseContains('Missing Template');
}
```
See `tests/TestCase/Controller/PagesControllerTest.php:66-75`. CakePHP's TestCase restores Configure between tests, so this is safe.

**Custom assertions via `assertThat`:**
```php
$this->assertThat(403, $this->logicalNot(new StatusCode($this->_response)));
```
See `tests/TestCase/Controller/PagesControllerTest.php:112`. Use `Cake\TestSuite\Constraint\Response\StatusCode` plus `logicalNot()` when PHPUnit's built-in assertions don't express the intent.

## Mocking

**Framework:** PHPUnit's `getMockBuilder()` / `createMock()`. No separate mocking library (e.g. Mockery, Prophecy) is in `require-dev`.

**Pattern — override specific methods, keep constructor args:**
```php
$app = $this->getMockBuilder(Application::class)
    ->setConstructorArgs([dirname(dirname(__DIR__)) . '/config'])
    ->onlyMethods(['addPlugin'])
    ->getMock();

$app->method('addPlugin')
    ->will($this->throwException(new InvalidArgumentException('test exception.')));

$app->bootstrap();
```
See `tests/TestCase/ApplicationTest.php:77-85`.

**Conventions:**
- Use `->onlyMethods([...])` (PHPUnit 9) — not the deprecated `->setMethods()`.
- Reach for a mock only when the real dependency is expensive, non-deterministic, or needs to throw. For controller tests, use `IntegrationTestTrait` end-to-end instead of mocking the controller.

**What to mock:**
- External services you introduce later (Bluesky OAuth client, HTTP clients, clock, RNG for SSR probability).
- CakePHP hooks you cannot easily trigger in a full integration test (e.g. plugin registration failures — see the `addPlugin` example above).

**What NOT to mock:**
- CakePHP's ORM — use fixtures against the real test DB.
- `Configure` — mutate it directly, CakePHP resets between tests.
- Middleware / routing — run a real request via `$this->get()`.

## Fixtures and Factories

**Location:** `tests/Fixture/` (empty, `.gitkeep` only).

**Current state:** No fixtures defined yet. When you add ORM tables (`src/Model/Table/...`), create a matching `tests/Fixture/{Name}Fixture.php` extending `Cake\TestSuite\Fixture\TestFixture`.

**Expected pattern (standard CakePHP):**
```php
// tests/Fixture/UsersFixture.php
namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{
    public array $records = [
        [
            'id' => 'uuid-1',
            'handle' => 'alice.bsky.social',
            'created' => '2026-04-22 00:00:00',
        ],
    ];
}
```
Then in the test class:
```php
protected $fixtures = ['app.Users'];
```
The `PHPUnitExtension` registered in `phpunit.xml.dist:23` will load it.

**Factories (optional):** If fixture volume gets painful, consider `cakephp/test-suite-light` + `vierge-noire/cakephp-fixture-factories` later. Not a current dependency.

## Coverage

**Requirements:** No coverage floor enforced.

**Whitelist (from `phpunit.xml.dist:27-35`):**
- Includes: `src/` (all `.php`), `plugins/*/src/`.
- Excludes: `src/Console/Installer.php`.

**View coverage:**
```bash
vendor/bin/phpunit --coverage-html tmp/coverage       # HTML report
vendor/bin/phpunit --coverage-text                    # CLI summary
```
Requires Xdebug (`xdebug.mode=coverage`) or PCOV installed on the PHP running the tests.

## Test Types

**Unit tests:**
- Isolated class-level behavior. No `IntegrationTestTrait`. No DB. Mock collaborators.
- None exist yet — add them in `tests/TestCase/Service/`, `tests/TestCase/Model/Entity/`, etc. as those classes arrive.

**Integration tests:**
- Full request cycle via `IntegrationTestTrait`. Real router, middleware, and (when applicable) real DB via fixtures.
- Current `PagesControllerTest` and `ApplicationTest` are the reference.

**E2E / browser tests:** Not used. Codeception is supported by CakePHP but is not in `require-dev`.

## Common Patterns

**Async testing:** N/A — PHP request cycle is synchronous. If you introduce queue jobs (`cakephp/queue`), test the job class directly like a unit, then an integration test that dispatches into an in-memory queue backend.

**Exception testing — two shapes:**

*Shape A: whole-test expectation (use when the entire test body is expected to throw):*
```php
public function testBootstrapPluginWithoutHalt()
{
    $this->expectException(InvalidArgumentException::class);

    $app = $this->getMockBuilder(Application::class)
        ->setConstructorArgs([dirname(dirname(__DIR__)) . '/config'])
        ->onlyMethods(['addPlugin'])
        ->getMock();

    $app->method('addPlugin')
        ->will($this->throwException(new InvalidArgumentException('test exception.')));

    $app->bootstrap();
}
```
See `tests/TestCase/ApplicationTest.php:73-86`.

*Shape B: assert HTTP status (for exceptions handled by `ErrorHandlerMiddleware`):*
```php
$this->get('/pages/../Layout/ajax');
$this->assertResponseCode(403);
$this->assertResponseContains('Forbidden');
```
See `tests/TestCase/Controller/PagesControllerTest.php:82-87`.

**Middleware-queue inspection:**
```php
$app = new Application(dirname(dirname(__DIR__)) . '/config');
$middleware = $app->middleware(new MiddlewareQueue());

$this->assertInstanceOf(ErrorHandlerMiddleware::class, $middleware->current());
$middleware->seek(1);
$this->assertInstanceOf(AssetMiddleware::class, $middleware->current());
```
See `tests/TestCase/ApplicationTest.php:93-104`. Use when asserting middleware *order* matters.

**Config paths in tests:** `dirname(dirname(__DIR__)) . '/config'` is the idiom used by skeleton tests to locate `config/`. Tolerable for now; if test nesting deepens, introduce a `CONFIG` constant helper.

## Gaps / TODO for Test Suite

- No `Service/` tests (service layer not created yet).
- No model/ORM tests (no tables yet).
- No OAuth flow tests (Bluesky AT Protocol integration pending — see `README.md`).
- No SSR probability logic tests — this is a core mechanic and will need deterministic RNG injection for testability.
- No GitHub Actions workflow running `composer check` on PR — add one under `.github/workflows/` when the suite grows beyond the skeleton samples.

---

*Testing analysis: 2026-04-22*
