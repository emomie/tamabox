---
phase: 03-inbox-message-ssr-reveal
plan: 03a
subsystem: dashboard-reveal
tags:
  - dashboard
  - settings
  - open
  - reveal
  - ssr
  - paginator
  - controller
  - integration-test
dependency_graph:
  requires:
    - 03-01-slug-foundation
    - 03-02-send-flow
  provides:
    - MessagesTable::markOpened (idempotent ownership-checked UPDATE)
    - MessagesController::open (replaces 501 placeholder with real implementation)
    - InboxesController::settings (GET redirect / POST patch with validation)
    - UsersController::dashboard (paginated 20/page + collision flash + settings vars)
    - templates/Users/dashboard.php (full UI-SPEC §2/§3/§5/§10/§11)
    - templates/element/inbox_settings_form.php (UI-SPEC §3 settings form with JS)
    - templates/Inboxes/settings.php (standalone settings page wrapper)
  affects:
    - INBOX-02
    - INBOX-03
    - MSG-06
    - MSG-07
tech_stack:
  added: []
  patterns:
    - NotFoundException catch for Paginator out-of-range (Controller::paginate() re-throws as NotFoundException)
    - consume-once session flash (read + delete in same request)
    - patchEntity with accessibleFields for settings POST
    - nl2br(h()) XSS-safe body rendering in templates
key_files:
  created:
    - src/Controller/InboxesController.php
    - templates/Inboxes/settings.php
    - templates/element/inbox_settings_form.php
    - tests/TestCase/Controller/InboxesControllerTest.php
    - tests/TestCase/Controller/UsersControllerTest.php
  modified:
    - src/Controller/MessagesController.php (open() body replaced from 501 placeholder)
    - src/Controller/UsersController.php (dashboard expanded with paginator + flash + inbox)
    - src/Model/Table/MessagesTable.php (markOpened added)
    - templates/Users/dashboard.php (full rewrite per UI-SPEC)
    - tests/TestCase/Controller/MessagesControllerTest.php (501 placeholder test replaced with 5 open tests)
decisions:
  - "Rule 1 deviation: Controller::paginate() internally catches PageOutOfBoundsException and re-throws as NotFoundException. Plan sticky note said catch PageOutOfBoundsException directly, but parent class transforms it. UsersController catches NotFoundException for out-of-range page fallback."
  - "TestDashboardCollisionFlashShownOnceThenCleared: IntegrationTestTrait._session persists across requests within the same test; must manually clear Flash from _session before second GET to simulate consume-once behavior correctly."
  - "paginate property declared as public $paginate (untyped) to match parent Controller::$paginate declaration; typed array causes PHP Fatal error."
  - "InboxesController settings POST: saveOrFail() dead catch removed (phpstan level 8); validator errors surfaced via getErrors() before save."
metrics:
  duration: ~30 minutes
  completed: "2026-04-26"
  tasks_completed: 3
  files_changed: 10
---

# Phase 03 Plan 03a: Dashboard Reveal Summary

**One-liner:** Full receive dashboard with paginated `<details>` message rows, SSR hit/miss reveal cards, settings form, and ownership-checked idempotent `markOpened` — INBOX-02/03 + MSG-06/07 closed.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | MessagesTable::markOpened + MessagesController::open body | bd56563 | MessagesTable.php, MessagesController.php, MessagesControllerTest.php |
| 2 | InboxesController::settings + integration tests | 4cd4fc0 | InboxesController.php, InboxesControllerTest.php |
| 3 | UsersController::dashboard + templates + UsersControllerTest | cabf293 | UsersController.php, dashboard.php, inbox_settings_form.php, settings.php, UsersControllerTest.php |

## Test Results

- **Before plan:** 133 tests / 0 failures (Wave 2 baseline)
- **After plan:** 160 tests / 433 assertions / 0 failures / 6 incomplete (pre-existing)
- **New tests:** +27 (MessagesControllerTest +4 net, InboxesControllerTest +13, UsersControllerTest +10)
- **phpstan:** level 8 [OK]
- **phpcs:** clean (0 errors, 0 warnings)

## Requirements Closed

- INBOX-02: Receive list with unread/opened visual distinction (paginated 20/page)
- INBOX-03: Settings UI for ssr_probability / welcome_message / is_accepting
- MSG-06: Open action — opened_at set on first open, idempotent on re-open
- MSG-07: SSR reveal at open time (hit: sender card; miss: miss text)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Controller::paginate() re-throws PageOutOfBoundsException as NotFoundException**
- **Found during:** Task 3 — testDashboardOutOfRangePageShowsCopy returned 404 instead of 200
- **Issue:** Plan sticky note instructed catching `Cake\Datasource\Paging\Exception\PageOutOfBoundsException` directly. However, `vendor/cakephp/cakephp/src/Controller/Controller.php` line ~1005 internally catches `PageOutOfBoundsException` and re-throws it as `Cake\Http\Exception\NotFoundException`. Our catch block never fired.
- **Fix:** Changed `catch (PageOutOfBoundsException $e)` to `catch (NotFoundException $e)` in UsersController::dashboard. Documented in class docblock.
- **Files modified:** src/Controller/UsersController.php
- **Commit:** cabf293

**2. [Rule 1 - Bug] public array $paginate causes PHP Fatal error**
- **Found during:** Task 3 — PHPUnit fatal error at test startup
- **Issue:** Parent `Cake\Controller\Controller::$paginate` is declared `public $paginate = []` (untyped). Subclass redeclaring `public array $paginate` causes PHP fatal "Type must not be defined".
- **Fix:** Changed to untyped `public $paginate = [...]` with `@var array<string, mixed>` phpdoc.
- **Files modified:** src/Controller/UsersController.php
- **Commit:** cabf293

**3. [Rule 1 - Bug] testDashboardCollisionFlashShownOnceThenCleared session persistence**
- **Found during:** Task 3 — second GET in collision flash test still showed the flash
- **Issue:** IntegrationTestTrait stores session data in `$this->_session` and re-writes it before every request. The Flash.slug_collision_suffix set via `$this->session()` was being re-injected on the second GET even after the controller deleted it from the real session.
- **Fix:** Added `$this->session(['Flash' => []])` before the second GET to clear the collision flash from `_session`. Added explanatory comment.
- **Files modified:** tests/TestCase/Controller/UsersControllerTest.php
- **Commit:** cabf293

**4. [Rule 1 - Bug] testDashboardBodyScriptEscaped body_length CHECK constraint**
- **Found during:** Task 3 — DB check constraint violation when patching body without body_length
- **Issue:** `messages` table has `CHECK (body_length = LENGTH(body))`. Patching only `body` left `body_length` at old value.
- **Fix:** Added `body_length` to patchEntity call with `mb_strlen($xssBody)`.
- **Files modified:** tests/TestCase/Controller/UsersControllerTest.php
- **Commit:** cabf293

**5. [Rule 2 - phpstan] Dead catch in InboxesController removed**
- **Found during:** Task 2 — phpstan level 8 flagged `catch (PersistenceFailedException)` as dead
- **Issue:** `saveOrFail` after `getErrors()` pre-check was wrapped in try/catch that phpstan correctly identified as never-throwing in that path.
- **Fix:** Removed try/catch; let `saveOrFail` propagate naturally (validation already checked above).
- **Files modified:** src/Controller/InboxesController.php
- **Commit:** 4cd4fc0

## Known Stubs

- `/report/{id}` and `/block/{user_id}` POST forms in dashboard SSR hit card are 501 stubs (Phase 4 implementation). UI renders buttons correctly; backend returns 501.

## Threat Flags

No new security surface beyond what the plan's threat model documented. All T-03-03a-* mitigations implemented:
- T-03-03a-01: ownership check in markOpened ✓
- T-03-03a-02: settings POST loads inbox by user_id (no IDOR) ✓
- T-03-03a-03: pct 0..100 range validation + error flash ✓
- T-03-03a-04/05: nl2br(h()) escaping for body and handle ✓
- T-03-03a-06: rel="noopener" on external profile link ✓
- T-03-03a-08: maxLength(1000) validator for welcome_message ✓
- T-03-03a-09: opened_at IS NULL guard in markOpened ✓
- T-03-03a-11: NotFoundException catch for out-of-range page ✓

## Self-Check

## Self-Check: PASSED

All files verified present. All commits verified in git history.

| Item | Status |
|------|--------|
| src/Controller/InboxesController.php | FOUND |
| src/Controller/UsersController.php | FOUND |
| src/Controller/MessagesController.php | FOUND |
| src/Model/Table/MessagesTable.php | FOUND |
| templates/Users/dashboard.php | FOUND |
| templates/element/inbox_settings_form.php | FOUND |
| templates/Inboxes/settings.php | FOUND |
| tests/TestCase/Controller/UsersControllerTest.php | FOUND |
| tests/TestCase/Controller/InboxesControllerTest.php | FOUND |
| commit bd56563 (Task 1) | FOUND |
| commit 4cd4fc0 (Task 2) | FOUND |
| commit cabf293 (Task 3) | FOUND |

