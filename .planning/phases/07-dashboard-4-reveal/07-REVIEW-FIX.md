---
phase: 07-dashboard-4-reveal
fixed_at: 2026-05-13T00:00:00Z
review_path: .planning/phases/07-dashboard-4-reveal/07-REVIEW.md
iteration: 1
findings_in_scope: 9
fixed: 6
deferred: 3
status: partial
---

# Phase 7: Code Review Fix Report

**Fixed at:** 2026-05-13
**Source review:** `.planning/phases/07-dashboard-4-reveal/07-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope (Medium + Low + Info): 9
- Fixed: 6 (M-01, M-02, L-01, L-02, L-03, L-04)
- Deferred: 3 (IN-01, IN-02, IN-03)
- Test result after all fixes: **199 tests, 553 assertions, 0 failures, 6 incomplete (pre-existing)** — baseline maintained.

---

## Fixed Issues

### M-01: First-open reveal does NOT animate — initial-paint fade-in added

**Files modified:** `webroot/js/reveal-motion.js`
**Commit:** `b75a0cc`
**Applied fix:** Added a `fireInitialFadeFromHash()` branch that runs alongside `armAll()` inside the existing DOMContentLoaded / fall-through init path. It reads `window.location.hash`, locates the matching `<details>` element, verifies it's a `details.message-row` in `open` state, and triggers the same `.is-opening` fade the toggle handler would. Extracted the fade logic into a shared `playFade(body)` helper so the toggle handler and the new initial-paint handler share identical timing + reflow-hack logic. Also short-circuits when `prefers-reduced-motion: reduce` matches (defense-in-depth alongside M-02 CSS fix). Syntax verified with `node -c`; `composer test` still 199/0 (no JS unit tests exist — JS file is loaded by the layout for all dashboard screens).

---

### M-02: `prefers-reduced-motion` opt-out for `tb-fade-in` keyframe

**Files modified:** `webroot/css/tamabox.css` (§H.2)
**Commit:** `58742cd`
**Applied fix:** Added `@media (prefers-reduced-motion: reduce) { .message-row__body.is-opening { animation: none; } }` immediately after the existing rule at §H.2. Closes the WCAG 2.3.3 (Level AAA) gap flagged in REVIEW. Companion JS short-circuit in M-01 makes this defense-in-depth — the class is no longer added by `reveal-motion.js` when the media query matches, but if any future caller adds `.is-opening` directly the CSS rule still suppresses the animation.

---

### L-01: Settings back button inline `onclick=history.back()` removed

**Files modified:** `templates/Inboxes/settings.php`, `webroot/css/tamabox.css`
**Commit:** `204c29d`
**Applied fix:** Replaced the `<button type="button" onclick="history.back()">` with `<a href="/dashboard" class="tb-icon-btn" aria-label="戻る">` (REVIEW Option A). Predictable target (Settings is reached from the TabBar inbox tab the vast majority of the time, so /dashboard is where back would land anyway) and no empty-history edge case. Companion CSS tweak: added `text-decoration: none` to the `.tb-icon-btn` base rule so the class works on `<a>` as well as `<button>` — idiomatic for an icon button regardless of element type. Closes the project's only inline event handler introduced in Phase 7; pre-existing inline handlers (e.g. delete-form `onsubmit` confirm) remain out of Phase 7 scope.

---

### L-02: Hidden `<img src="/img/default-avatar.svg" style="display:none">` removed

**Files modified:** `templates/Users/dashboard.php`
**Commit:** `1445cf1`
**Applied fix:** Deleted the redundant hidden `<img>` line in the sender-card no-avatar branch (line 171 in pre-fix). The `<span>` initial-on-gradient fallback already covers the no-avatar case; the `<img>` was `display:none` but the browser still fetched the SVG on every HIT-with-no-avatar render. Confirmed via `grep -r 'default-avatar\|sender-card__avatar' tests/` that no test asserts on this element — safe to remove without adjusting tests.

---

### L-03: Dead `h()` wrappers on enum-literal data attributes removed

**Files modified:** `templates/Users/dashboard.php`
**Commit:** `cc2c2ef`
**Applied fix:** Three sites in `templates/Users/dashboard.php`:

- `data-state="<?= h($state) ?>"` → `data-state="<?= $state ?>"`
- `tb-dash-dot--<?= h($dotMod) ?>` → `tb-dash-dot--<?= $dotMod ?>`
- `tb-message-row__from--<?= h($fromMod) ?>` → `tb-message-row__from--<?= $fromMod ?>`

All three variables are assigned compile-time string literals (`unread|opened`, `unread|hit|miss`, `hit|anon`) on lines 100-114 — `h()` on them was dead defense and muddied the trust boundary for future readers. User-derived emissions in the same template (`$handle`, `$bodyPreview`, `$senderHandle`, `$senderAvatar`, `$senderProfileUrl`, `(string)$msg->id`, `(string)$msg->body` via `nl2br(h(...))`) remain h()-wrapped.

---

### L-04: Unread dot now persists across all 4 dashboard tabs

**Files modified:** `src/Controller/UsersController.php`, `src/Controller/InboxesController.php`, `templates/Users/discover.php`, `templates/Users/notifications.php`, `templates/Inboxes/settings.php`
**Commit:** `602a80a`
**Applied fix:** The TabBar element already accepts `$unreadCount` (CONTEXT.md D-07) but the 3 non-inbox callers hardcoded `0`, dropping the dot the moment a user navigated away from `/dashboard` — exactly when the signal is most useful. Refactor:

- Added `UsersController::computeUnreadCount(string $userId): int` helper (single COUNT on `(inbox_id, opened_at IS NULL, deleted_at IS NULL)`, returns 0 when user lacks an inbox).
- `UsersController::discover()` and `::notifications()` now resolve the authenticated `$userId` from the identity and pass the real count via `$this->set([...])`.
- `InboxesController::settings()` GET branch computes the count inline (it already loads `$inbox` and `$blocks`; adding one COUNT alongside is cheap and avoids cross-controller helper coupling).
- All three templates forward the new value into `$this->element('tb_tabbar', ['active' => ..., 'unreadCount' => $unreadCount])` and declare the new view variable in their docblocks (also resolves IN-02 docblock drift on `Inboxes/settings.php` and `Users/discover.php`).

D-19 scope respected: only controller method additions / param passthrough; no Model / Migration / OAuth / SSR / moderation surface touched. `composer test` still 199/0.

---

## Deferred Issues

### IN-01: Substring assertions in new test cases — consider structural assertions

**File:** `tests/TestCase/Controller/UsersControllerTest.php:283-313`
**Reason for deferral:** Test enhancement, not a correctness fix. REVIEW explicitly tags this as "Fix (optional, not blocking)" and notes the tests already guard the primary 302/200 + auth boundary. The L-04 commit (which already broadened the controllers) bumped assertion count from 548 → 553 without touching these tests; adding tabbar-presence asserts would be a separate, dedicated test hardening pass. Better revisited when the same test file is touched for Phase 8 / 9 work (which will likely add new dashboard actions and need similar smoke coverage). Same Phase 6 IN-03 pattern was deferred for the same reason.

---

### IN-02: Template docblock drift (`$activeTab` / `$unreadCount` not declared)

**Files (residual):** `templates/Users/dashboard.php:1-13` (the dashboard template's docblock pre-existed without these two variables and was not touched in this iteration)
**Reason for deferral:** Partially fixed as a side-effect of L-04 — the L-04 commit updated `templates/Inboxes/settings.php` and `templates/Users/discover.php` docblocks to declare `$unreadCount` (and `$activeTab` where missing). The dashboard.php docblock would benefit from the same treatment but the file already pre-dates Phase 7 and lists many other view variables that pre-existed in Phase 4/6 form. A dedicated docblock pass across all 4 dashboard templates is better scoped to a Phase 8 cleanup, alongside the IN-03 dead `$blocks` removal (these are the same kind of "controller→view contract" drift). PHPDoc-only, zero runtime impact.

---

### IN-03: `$blocks` unused in dashboard.php — dead controller query

**Files:** `src/Controller/UsersController.php:108-115` (the `Blocks` find), `templates/Users/dashboard.php:9` (the docblock `@var $blocks`)
**Reason for deferral:** REVIEW itself notes "Fix (deferred to Phase 8 per CONTEXT.md)". CONTEXT.md Phase 7 Claude's Discretion §1 explicitly says: "既存 dashboard の settings aside 撤去で生じる data-flow 簡素化 ($inbox / $blocks を controller から渡さなくする) を本 phase で実施するか保留するかは plan-phase で判断 (clean-up コスト次第)". Settings-aside-removal cleanup was already deferred at plan-phase time, and removing the now-unused `Blocks` query touches both the controller and the template and would also benefit from updating `UsersControllerTest::testDashboard` style assertions (which can pre-existently inspect `$blocks` via response markup). Keep scoped for Phase 8 as the CONTEXT decision intended.

---

## Test Results

After all fixes applied:

```
composer test: 199 tests, 553 assertions, 0 failures, 6 incomplete (pre-existing)
```

Same 199 baseline as Phase 7 implementation. The L-04 controller refactor added 5 new assertions (553 vs the post-implementation 548 — the existing discover/notifications tests now exercise the new `unreadCount` controller path, which the test client happens to render against). The 6 incomplete tests are pre-existing and unrelated to Phase 7 changes.

---

## Commit Trail

| # | Hash | Finding | Subject |
|---|---|---|---|
| 1 | `b75a0cc` | M-01 | `fix(07): M-01 fire reveal fade-in on initial paint from URL hash` |
| 2 | `58742cd` | M-02 | `fix(07): M-02 add prefers-reduced-motion opt-out for tb-fade-in keyframe` |
| 3 | `602a80a` | L-04 | `fix(07): L-04 propagate real unread count to all 4 dashboard tabs` |
| 4 | `204c29d` | L-01 | `fix(07): L-01 replace inline onclick=history.back() with <a href=/dashboard>` |
| 5 | `1445cf1` | L-02 | `fix(07): L-02 remove hidden default-avatar <img> that wasted an HTTP request` |
| 6 | `cc2c2ef` | L-03 | `fix(07): L-03 drop dead h() wrappers on enum-literal data attributes` |

---

_Fixed: 2026-05-13_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
