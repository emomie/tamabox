---
phase: 03-inbox-message-ssr-reveal
plan: 03b
subsystem: ui-assets
tags:
  - stub
  - block
  - css
  - asset
  - svg
  - landing
  - phase-4-handoff
dependency_graph:
  requires:
    - 03-02-send-flow (routes.php POST /block/{senderUserId} already wired)
    - 03-03a-dashboard-reveal (dashboard.php references default-avatar.svg + CSS classes)
  provides:
    - BlocksController 501 stub (D-35 Phase 4 hand-off contract)
    - webroot/img/default-avatar.svg (D-31 avatar fallback asset)
    - templates/element/flash/info.php (UI-SPEC §11 info flash element)
    - tamabox.css Phase 3 extension (~420 lines total, UI-SPEC §1-§13 coverage)
    - templates/Pages/home.php Phase 3 explainer copy
  affects:
    - Phase 4 (BlocksController body + test must be replaced together when INBOX-04/05 ships)
tech_stack:
  added: []
  patterns:
    - "501 stub + locked test = Phase 4 hand-off contract (mirror of Plan 02-04 OauthController callback pattern)"
    - "CSS design-token extension: only :root additions, no modification of Phase 2 rules"
    - "SVG static asset: shapes-only, no script/foreignObject (UI-SPEC §7 security spec)"
key_files:
  created:
    - src/Controller/BlocksController.php
    - tests/TestCase/Controller/BlocksControllerTest.php
    - webroot/img/default-avatar.svg
  modified:
    - templates/element/flash/info.php
    - templates/Pages/home.php
    - tests/TestCase/Controller/PagesControllerTest.php
    - webroot/css/tamabox.css
decisions:
  - "BlocksController::initialize() calls allowUnauthenticated(['create']) so AuthenticationMiddleware cannot 302-redirect before the 501 action runs — safe because no DB writes occur in the stub"
  - "flash/info.php existed with wrong class (missing 'info'); overwritten to add .message.info per UI-SPEC §11"
  - "home.php explainer inserted after Form->end() before closing </div> to preserve all Phase 2 assertion targets"
  - "tamabox.css Phase 3 block appended via shell heredoc (not Edit tool) to avoid token limits on 641-line file"
metrics:
  duration: "~25 minutes"
  completed_date: "2026-04-26"
  tasks_completed: 3
  files_modified: 7
---

# Phase 3 Plan 03b: Stubs, Styles & Asset Summary

**One-liner**: BlocksController 501 stub (D-35 Phase 4 contract) + default-avatar.svg (UI-SPEC §7) + tamabox.css ~200-line Phase 3 extension (UI-SPEC §1-§13) + flash/info element + home.php explainer.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | BlocksController 501 stub + integration test | `0707208` | src/Controller/BlocksController.php, tests/TestCase/Controller/BlocksControllerTest.php |
| 2 | default-avatar.svg + flash/info element + home.php copy | `84d3192` | webroot/img/default-avatar.svg, templates/element/flash/info.php, templates/Pages/home.php, tests/TestCase/Controller/PagesControllerTest.php |
| 3 | tamabox.css Phase 3 extension | `4f7a298` | webroot/css/tamabox.css |

## Test Counts

- **BlocksControllerTest**: 2 tests, 3 assertions (NEW)
- **PagesControllerTest**: 7 tests, 18 assertions (+1 Phase 3 explainer test added)
- **Full suite**: 163 tests, 439 assertions, 6 skipped (pre-existing), 0 failures

## CSS Metrics

| Metric | Value |
|--------|-------|
| Phase 2 baseline | 218 lines |
| Phase 3 addition | ~421 lines |
| Total | 641 lines |
| Brace balance | 98 open / 98 close (balanced) |
| :root additions | --avatar-sm: 24px, --avatar-lg: 64px |
| New class targets | .send-form-page, .send-form__body, .consent-label, .char-counter, .send-done-page, .send-done__lead, .send-done__actions, .dashboard-page (extended), .dashboard-header, .dashboard-settings, .receive-list, .receive-list-empty, .message-row (unread/opened states), .message-row__head, .message-row__icon, .message-row__time, .message-row__preview, .message-row__body, .open-form, .ssr-reveal, .ssr-reveal__banner, .ssr-reveal__miss, .sender-card, .sender-card__avatar, .sender-card__handle, .button-destructive, .inline, .settings-form, .probability-control, .probability-suffix, .pagination, .message.info |
| Responsive breakpoints | 768px (desktop 2-col dashboard grid: 3fr/2fr) |

## Phase 4 Hand-off Note (D-35)

`BlocksController::create()` returns HTTP 501 with body `'Not Implemented'`. `BlocksControllerTest::testCreateReturns501Stub` locks this contract.

When Phase 4 implements INBOX-04/INBOX-05, the implementer MUST:
1. Replace `BlocksController::create()` body with real INSERT-into-blocks logic
2. Remove `'create'` from `allowUnauthenticated(['create'])` in `initialize()`
3. Update `BlocksControllerTest::testCreateReturns501Stub` to assert 302+Flash instead of 501

If `testCreateReturns501Stub` still passes after Phase 4 deploy, the real implementation never happened. This is the same pattern used for `OauthController::callback` in Plan 02-03 (stub) → Plan 02-04 (replacement).

## PagesControllerTest Regression Confirmation

Phase 2 baseline assertions all still pass after home.php Phase 3 edit:
- `assertResponseContains('Bluesky でログイン')` — PASS (CTA preserved)
- `assertResponseContains('Bluesky アカウントでログインして、あなたの受信箱をはじめましょう')` — PASS (home-lead preserved)
- Phase 3 addition: `assertResponseContains('確率で送信者の名前がバレる')` — PASS

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] flash/info.php already existed with wrong class**
- **Found during:** Task 2
- **Issue:** `templates/element/flash/info.php` existed (from Wave 2 or earlier) with `class="message"` only, missing the `info` modifier class required by UI-SPEC §11
- **Fix:** Overwrote file with correct `class="message info"` markup, added `role="status"` for accessibility
- **Files modified:** templates/element/flash/info.php
- **Commit:** 84d3192

**2. [Rule 2 - CS] BlocksController docblock @inheritDoc warning**
- **Found during:** Task 1 CS check
- **Issue:** `@inheritDoc` with additional text in same docblock caused phpcs WARNING (not ERROR)
- **Fix:** Removed `@inheritDoc` tag, replaced with explicit `@return void` annotation; 0 errors, 0 warnings
- **Files modified:** src/Controller/BlocksController.php
- **Commit:** 0707208

## Sticky Note for Phase 3 Verifier

Plans 03-03a + 03-03b together complete the visual + functional contract for Phase 3:
- 03-03a provides: dashboard.php DOM structure, open action, settings controller
- 03-03b provides: CSS visual layer, avatar fallback SVG, 501 block stub, flash/info element

Live Bluesky OAuth session smoke is `human_needed` — deferred to Phase 4 deploy (same precedent as Phase 2 verify-phase: 7/7 code-level truths VERIFIED, 3 inherent-human-only items). The verifier can confirm all Phase 3 success criteria at code level without a live Bluesky AS session.

## Known Stubs

| Stub | File | Reason |
|------|------|--------|
| `BlocksController::create()` returns 501 | src/Controller/BlocksController.php | D-35 Phase 4 hand-off contract; INBOX-04/05 implementation deferred to Phase 4 |

## Self-Check: PASSED

Files exist:
- FOUND: src/Controller/BlocksController.php
- FOUND: tests/TestCase/Controller/BlocksControllerTest.php
- FOUND: webroot/img/default-avatar.svg
- FOUND: templates/element/flash/info.php
- FOUND: templates/Pages/home.php (modified)
- FOUND: webroot/css/tamabox.css (modified)

Commits exist:
- FOUND: 0707208 (BlocksController + test)
- FOUND: 84d3192 (SVG + flash/info + home.php + PagesControllerTest)
- FOUND: 4f7a298 (tamabox.css Phase 3 extension)

Test results verified: 163 tests / 439 assertions / 0 failures / 6 skipped (pre-existing)
phpstan level 8: [OK] No errors
phpcs: 0 errors, 0 warnings
