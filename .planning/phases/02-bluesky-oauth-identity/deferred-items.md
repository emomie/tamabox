# Phase 02 — Deferred Items

Out-of-scope discoveries made during plan execution. Not fixed here; to be
addressed in a dedicated infra/hygiene plan.

## D-DEF-01: `templates/Pages/home.php` calls deprecated `$connection->connect()`

- **Discovered:** Plan 02-02 Task 1, during `composer test`.
- **File:** `templates/Pages/home.php` line 30 (the CakePHP 4.5 skeleton default landing page).
- **Symptom:** PHPUnit prints a lengthy deprecation stack trace at suite start:
  `If you cannot use automatic connection management, use $connection->getDriver()->connect() instead.`
- **Scope:** Pre-existing from `composer create-project` skeleton. Not introduced by Plan 02-02.
  Not in any current plan's `<files>`. Does not fail any test — suite still exits 0.
- **Fix sketch:** Either replace the deprecated call with `$connection->getDriver()->connect()`,
  or (preferred) delete the built-in "welcome" block from `home.php` once we ship a real landing
  template in Phase 3 / Phase 4.
- **Owner:** Phase 4 production launch plan (landing page work) or a dedicated cleanup plan.
