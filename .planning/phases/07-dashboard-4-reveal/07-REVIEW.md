---
phase: 07-dashboard-4-reveal
reviewed: 2026-05-13T00:00:00Z
depth: standard
files_reviewed: 13
files_reviewed_list:
  - config/routes.php
  - src/Controller/InboxesController.php
  - src/Controller/UsersController.php
  - templates/Inboxes/settings.php
  - templates/Users/dashboard.php
  - templates/Users/discover.php
  - templates/Users/notifications.php
  - templates/element/tb_tabbar.php
  - templates/layout/default.php
  - tests/TestCase/Controller/InboxesControllerTest.php
  - tests/TestCase/Controller/UsersControllerTest.php
  - webroot/css/tamabox.css
  - webroot/js/reveal-motion.js
findings:
  critical: 0
  high: 0
  medium: 2
  low: 4
  info: 3
  total: 9
status: findings
---

# Phase 7: Code Review Report

**Reviewed:** 2026-05-13
**Depth:** standard
**Files Reviewed:** 13 (2 controllers + 1 routes + 5 templates + 1 element + 1 layout + 1 CSS + 1 JS + 2 tests)
**Status:** issues_found (no critical / no high; 2 medium UX/a11y findings, 4 low, 3 info)

---

## Summary

Phase 7 successfully adds the 4-tab dashboard structure with two new controller actions (`UsersController::discover` / `::notifications`), two GET-only routes, the `tb_tabbar` element, three new/rewritten templates, ~370 new CSS lines (§H.1–§H.8), and a small idempotent JS file for reveal motion. The backend deviation from Phase 6 is scoped exactly as CONTEXT.md D-19 allows: only `routes.php` + `UsersController` + `InboxesController` modified — no model / migration / OAuth / SSR / moderation touched. Test suite is documented green at 199/0.

Security posture is solid: every user-derived value is wrapped in `h()` at emission; the `block_list.php` `onerror=` pattern from Phase 6 H-01 is **not reintroduced** in the new Phase 7 markup; the new JS uses `classList` + DOM APIs only (no `innerHTML`, `outerHTML`, `eval`); the new routes are GET-only as spec'd; both new controller actions check `Authentication->getIdentity()` and redirect on unauth. CSS comment balance is intact (depth=0 at EOF) — the Phase 5 `*/` trap regression class is not repeated.

The findings cluster around UX/a11y polish, not correctness. The most consequential one (**M-01**) is that `reveal-motion.js` does not fade-in the first-open reveal (the only path the user perceives as "reveal"), because the server-side redirect renders the details element with the `open` attribute already present — and the browser does not fire a `toggle` event for already-open details on page load. **M-02** flags the absence of a `prefers-reduced-motion` media query around the 400ms keyframe (accessibility regression vs the Phase 5/6 a11y posture).

---

## Medium

### M-01: First-open reveal does NOT animate — `reveal-motion.js` only fires on user re-toggle, not on post-redirect initial paint

**Files:**
- `webroot/js/reveal-motion.js:14-25`
- `templates/Users/dashboard.php:116-120` (server-rendered `<details ... open>`)
- `src/Controller/UsersController.php` (open-form posts to `/dashboard/messages/{id}/open` which redirects to `/dashboard#msg-{id}` — already-opened state)

**Issue:** The verification narrative (§3 of `07-VERIFICATION.md`) claims:

> Click unread row → submit POST → server redirects to `/dashboard#msg-{id}` → page re-renders with the message in `open` state → reveal-motion.js fires the fade-in once on initial paint

That last clause is wrong. `reveal-motion.js` arms a `toggle` event listener:

```javascript
details.addEventListener('toggle', function () {
    if (!details.open) { return; }
    var body = details.querySelector('.message-row__body');
    ...
    body.classList.add('is-opening');
    ...
});
```

The HTML spec only fires `toggle` when `open` *transitions*. When the server renders `<details open>` on page load, `open` is the initial state — no transition occurs, no event fires. Browsers (Chrome, Firefox, Safari) all behave this way. As a result the user's actual "reveal moment" (first open after submit-POST-redirect) has **no fade-in**. Animation only fires on the secondary path of closing-then-reopening an already-opened row, which is not the moment MOTION-02 cares about.

`tb-message-row[data-state="opened"]` and the redirect target `#msg-{id}` are both already known at template-render time, so the fade can be triggered without a `toggle` event.

**Impact:** MOTION-02 acceptance criterion ("400ms fade-in on reveal") is not met for the primary path. The reveal moment that users experience as "opening the box" has no motion at all. Phase 6 didn't ship motion; Phase 7's promised contribution silently no-ops here.

**Fix:** Add a small DOMContentLoaded branch that fades the post-redirect row. The redirect already uses `#msg-{id}` so the target can be located reliably:

```javascript
// webroot/js/reveal-motion.js — after the existing arm() definition, before armAll()
function fireInitialFadeFromHash() {
    var hash = window.location.hash;
    if (!hash || hash.indexOf('#msg-') !== 0) { return; }
    var target = document.getElementById(hash.slice(1));
    if (!target || !target.classList.contains('message-row')) { return; }
    if (!target.open) { return; }
    var body = target.querySelector('.message-row__body');
    if (!body) { return; }
    // Reflow then add is-opening; matching the toggle handler logic.
    void body.offsetWidth;
    body.classList.add('is-opening');
    window.setTimeout(function () { body.classList.remove('is-opening'); }, 500);
}
```

Then call `fireInitialFadeFromHash()` alongside `armAll()` inside the existing DOMContentLoaded / fall-through branches.

Optional refinement: respect `prefers-reduced-motion` here too (see M-02).

---

### M-02: 400ms `tb-fade-in` keyframe has no `prefers-reduced-motion` opt-out

**File:** `webroot/css/tamabox.css:2052-2059` (§H.2)

**Issue:** The new keyframe + animation declarations have no companion `@media (prefers-reduced-motion: reduce)` rule. Users who have set OS-level "reduce motion" still get the 400ms fade. This is a small motion (`translateY(2px)` + opacity 0→1) so the vestibular risk is low, but the contract for the project is to honor the system setting wherever animations are introduced — and §H.2 is the first non-trivial keyframe in the codebase (the only other `@keyframes` is `spin` for a loader, which is a deliberate progress indicator and is conventionally exempted).

The other Phase 7 additions (CSS transitions on `:focus-visible` outlines, hover states) are within the spec-defined "essential UI feedback" exemption, but a content-reveal fade does not qualify.

**Impact:** WCAG 2.3.3 (Level AAA) compliance gap. Users with vestibular disorders or who actively dislike motion will see the unsuppressed animation. The fix is cheap and conventional.

**Fix:** Add a single rule at the end of §H.2:

```css
@media (prefers-reduced-motion: reduce) {
    .message-row__body.is-opening {
        animation: none;
    }
}
```

If wanting to keep the opacity-only part (which has no vestibular risk), replace with:

```css
@media (prefers-reduced-motion: reduce) {
    .message-row__body.is-opening {
        animation: tb-fade-in-flat 1ms linear;
    }
    @keyframes tb-fade-in-flat {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
}
```

Either is acceptable; the first is simpler. Defer the JS portion in M-01 fix similarly:

```javascript
var prefersReduce = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if (prefersReduce) { return; }
```

---

## Low

### L-01: `onclick="history.back()"` on the settings back button — inline event handler, breaks no-JS fallback

**File:** `templates/Inboxes/settings.php:23`

**Issue:** The Settings AppBar back button uses inline `onclick="history.back()"`. Three sub-issues:

1. **CSP-unfriendly.** Any future `Content-Security-Policy: script-src 'self'` (or strict-dynamic) without `'unsafe-inline'` will silently neuter this button. The project doesn't ship a CSP today, but the patterns being established in Phase 7 (defer-loaded external JS, no inline scripts elsewhere) point in that direction; this is the one inline handler that violates the trend.
2. **No-JS fallback is broken.** With JS disabled the button does nothing.
3. **Inconsistent with the project's pattern.** `dashboard.php:219` uses the same anti-pattern (`'onsubmit' => "return confirm(...)"` on the delete form) — that one is also pre-existing and out of scope for Phase 7, but the back button is **net new in Phase 7**.

**Impact:** Low — JS is required for the app to function (OAuth, reveal motion), and there's no CSP today. But this is the cleanest spot to break the pattern.

**Fix (option A — link to dashboard, which is where back lands 95% of the time):**

```php
<a href="/dashboard" class="tb-icon-btn" aria-label="戻る">
    <?= $this->element('icon', ['name' => 'back', 'size' => 22]) ?>
</a>
```

**Fix (option B — keep history.back semantics, move to a tiny event listener):**

```php
<button type="button" class="tb-icon-btn" data-back="1" aria-label="戻る">
    <?= $this->element('icon', ['name' => 'back', 'size' => 22]) ?>
</button>
```

Then in `reveal-motion.js` (or a new `nav-back.js`):
```javascript
document.querySelectorAll('[data-back="1"]').forEach(function (b) {
    b.addEventListener('click', function () { window.history.back(); });
});
```

Option A is simpler and more predictable (no surprise when the back stack is empty — clicking back at /dashboard/settings with an empty history just sits there with option B).

---

### L-02: Hidden fallback `<img src="/img/default-avatar.svg" style="display:none">` in sender card serves no purpose

**File:** `templates/Users/dashboard.php:169-172`

**Issue:** When the sender has no avatar URL, the template renders:

```php
<span class="tb-sender-card__avatar" aria-hidden="true"><?= h($senderInitial) ?></span>
<img class="sender-card__avatar" src="/img/default-avatar.svg" alt="..." width="44" height="44" style="display:none;">
```

The `<span>` provides the visible fallback (initial-on-gradient circle). The `<img>` is `display:none` so it never renders, but the browser still **issues an HTTP request for `/img/default-avatar.svg`** (modern browsers do not fully short-circuit hidden image loads — they fetch then skip layout). This wastes a request on every HIT-with-no-avatar message row and confuses any future reader.

The file does exist (`webroot/img/default-avatar.svg`, 286 bytes) so there's no 404, but the request is still wasted.

`git log -S "default-avatar.svg" -- templates/Users/dashboard.php` would clarify whether this came from a Phase 4 test assertion ("sender-card__avatar img is present"). If a test still asserts on the `<img>`'s presence, the fix is to drop the test alongside the markup; otherwise just remove the line.

**Impact:** One extra HTTP request per render of a HIT row with no sender avatar. Cosmetic; not a correctness issue.

**Fix:** Delete line 171 (the hidden `<img>`). If a test asserts on `sender-card__avatar` as an `<img>`, broaden it to allow span-or-img, and remove the dead element.

---

### L-03: `data-state="<?= h($state) ?>"` is technically safe but `$state` is already trusted — `h()` is dead code

**File:** `templates/Users/dashboard.php:118`

**Issue:** `$state` is set at line 100 to `$isUnread ? 'unread' : 'opened'` — two compile-time string literals. Wrapping in `h()` is a no-op. Same applies to line 122 (`$dotMod` is `'unread'|'hit'|'miss'`) and line 125/114 (`$fromMod` is `'hit'|'anon'`). These all read as defensive copy-paste from earlier user-derived attributes (where `h()` is essential) but the values here are enum-like literals.

**Impact:** Zero functional impact. Readability nit: a future reader scanning for "is this attribute user-influenceable?" will see `h()` and assume yes. Removing the wrappers makes the trust boundary clearer.

**Fix (optional):**

```php
data-state="<?= $state ?>"
<span class="tb-dash-dot tb-dash-dot--<?= $dotMod ?>" ...>
<span class="tb-message-row__from tb-message-row__from--<?= $fromMod ?>">
```

Not worth a re-commit on its own; bundle if other changes happen in this file.

---

### L-04: `unreadCount = 0` hardcoded on every non-inbox tab — drops the unread dot when user is on Discover/Notifications/Settings

**Files:**
- `templates/Users/discover.php:50` — `['active' => 'discover', 'unreadCount' => 0]`
- `templates/Users/notifications.php:32` — `['active' => 'notifications', 'unreadCount' => 0]`
- `templates/Inboxes/settings.php:39` — `['active' => 'settings', 'unreadCount' => 0]`
- `src/Controller/UsersController.php:187` (discover) / `:205` (notifications) — controllers do not compute unread count
- `src/Controller/InboxesController.php:64-65` (settings GET branch) — sets `unreadCount => 0` explicitly

**Issue:** The `tb_tabbar` element supports an unread-dot on the inbox tab driven by `$unreadCount`. On `/dashboard` (inbox tab) the controller computes it correctly. But on the other three tabs, the value is passed in as a hardcoded `0` — so the unread dot **disappears** the moment the user navigates away from inbox, even if they have unread messages. This contradicts the UX intent: "TabBar 受信 icon shows a dot whenever unread > 0" should hold regardless of which tab is currently active.

**Impact:** UX bug — the unread indicator on the TabBar (which is global navigation) only renders when the user is already viewing the inbox. The signal is most valuable when the user is on a different tab.

**Fix:** Compute `$unreadCount` in all three controller actions and pass through:

```php
// UsersController::discover() and ::notifications() — add before $this->set:
$inbox = $this->fetchTable('Inboxes')->find()
    ->where(['Inboxes.user_id' => $userId])->first();
$unreadCount = 0;
if ($inbox !== null) {
    $unreadCount = (int)$this->fetchTable('Messages')->find()
        ->where([
            'Messages.inbox_id' => $inbox->id,
            'Messages.opened_at IS' => null,
            'Messages.deleted_at IS' => null,
        ])->count();
}
$this->set([
    'activeTab' => 'discover', // or 'notifications'
    'unreadCount' => $unreadCount,
]);
```

Then in each template, pass `$unreadCount` through to the element:
```php
<?= $this->element('tb_tabbar', ['active' => 'discover', 'unreadCount' => $unreadCount]) ?>
```

Same for `InboxesController::settings` GET branch — line 64 already has the `$inbox` entity so adding the Messages count query is one COUNT.

Alternative if the DB cost is a concern (it shouldn't be — COUNT on an indexed (inbox_id, opened_at, deleted_at) is cheap): extract a `Component` or a helper method that the four actions share.

---

## Info

### IN-01: New tests assert on minimum substrings — same pattern Phase 6 M-02 cautioned about

**File:** `tests/TestCase/Controller/UsersControllerTest.php:283-313`

**Issue:** The four new test cases follow the same lightweight pattern as Phase 4's: assert response code + one Japanese heading substring (`発見はもうすぐ来ます`, `通知はまだありません`). This is the same flavor of weak-assertion that Phase 6's review flagged retrospectively as "load-bearing but fragile" (`06-REVIEW.md` IN-03). If a future phase copy-pastes the Empty state into a different screen, the assertion would still pass even if Discover lost its body content entirely.

**Impact:** None today — the tests do guard the 302/200 + auth boundary which is the primary contract. The substring assertion is a smoke check, not a contract.

**Fix (optional, not blocking):** Add one structural assertion per new screen:

```php
// Discover — assert TabBar present + active state
$this->assertResponseContains('class="tb-tabbar"');
$this->assertResponseContains('tb-tabbar__item is-active');
$this->assertResponseContains('aria-current="page"');
```

These also catch the case where someone accidentally drops the TabBar element call from a template.

---

### IN-02: `templates/Inboxes/settings.php` template-vardoc lists `$inbox` and `$blocks` only, but the controller also `set`s `$activeTab` and `$unreadCount`

**File:** `templates/Inboxes/settings.php:1-7`

**Issue:** The `@var` PHPDoc at the top of the template documents only `$inbox` and `$blocks`. The Phase 7 controller change adds `activeTab` and `unreadCount` to the `set()` payload (`InboxesController.php:64-65`) but the template docblock wasn't updated. Same minor gap in `templates/Users/discover.php:1-5` (documents `$activeTab` but not the absent `$unreadCount`, which it does receive as 0 from the hardcoded element call but does not consume directly).

**Impact:** None — PHPDoc accuracy only. Static analyzers (PHPStan / Psalm) running on templates would flag, but the project doesn't appear to run these on templates.

**Fix (optional):**

```php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 * @var array<int, \App\Model\Entity\Block> $blocks
 * @var string $activeTab
 * @var int $unreadCount
 */
```

---

### IN-03: `templates/Users/dashboard.php:9` typed `$blocks` is "unused in this template" — clean-up deferred per CONTEXT.md

**File:** `templates/Users/dashboard.php:9`

**Issue:** The dashboard template still receives `$blocks` from the controller (`UsersController.php:165`) but the comment correctly notes it is unused after the Phase 7 D-04 settings-aside removal. CONTEXT.md "Claude's Discretion" §1 made this clean-up explicitly optional ("コスト次第で plan 末尾 or Phase 8 持ち越し"). The data flow has one query (`$blocksTable->find()` at `UsersController.php:111-115`) running on every `/dashboard` page load whose result is now discarded — a small wasted query.

**Impact:** Slight latency on every dashboard page load. Not measurable in dev, but the indexed find + contain on `BlockedUsers`+`UserIdentities` is non-trivial. Cleanup is deferred per CONTEXT.

**Fix (deferred to Phase 8 per CONTEXT.md):** Remove the block list query + `$blocks` set from `UsersController::dashboard()` since it's moved to `InboxesController::settings()`. Drop the `@var` line + the misleading docblock entry in `dashboard.php:9`.

---

## CSS Structural Sign-off

- **Brace balance:** verified (manual scan + awk depth counter returns to 0 at EOF — line 2404).
- **Comment balance:** `/*` and `*/` counts pair up; no `*/` inside a `--var-name` (the Phase 5 regression class — `--color-*/--radius-*` early-close pattern — is not introduced anywhere in §H.1–§H.8).
- **Selector specificity:** §H sections follow the established `.tb-{component}__{element}` BEM-ish pattern; one inline-style hotspot at `dashboard.php:150-156` (the watermark `position:absolute` etc.) — covered by IN-04 below.
- **Token references:** all `--tb-*` and `--tb-r-*` and `--tb-shadow-*` resolve to existing declarations.
- **Animation:** one `@keyframes tb-fade-in` added; the only other keyframe in the file is `spin` from §A (the existing loader). No conflict. Reduced-motion media query is missing — covered by M-02.

---

## JavaScript Review (`reveal-motion.js`)

- **Idempotency:** `data-reveal-armed` flag prevents double-binding ✓
- **DOM safety:** uses `querySelectorAll` + `addEventListener` + `classList` — no `innerHTML` / `outerHTML` / `eval` / `document.write`. No XSS surface ✓
- **Strict mode:** `'use strict';` ✓
- **Listener cleanup:** No leak risk because details elements live as long as the page; if a phase ever destroys details elements via AJAX, the listeners would leak (no `removeEventListener` path). Not a current concern.
- **Browser support:** `void body.offsetWidth` is the standard reflow hack — works back to IE10. `dataset` is IE11+.
- **`setTimeout` of 500ms** for class removal: matches keyframe (400ms) + 100ms buffer, intentional.
- **Initial-paint fade gap:** the listener-only model misses the post-redirect first-open animation. Covered by M-01.

---

## Backend Scope Sign-off (CONTEXT.md D-19 / D-20)

Verified via `git diff --stat 5385ede..HEAD -- 'src/' 'config/'`:

```
config/routes.php                       |  13 ++
src/Controller/InboxesController.php    |  31 ++++-
src/Controller/UsersController.php      |  53 +++++++-
```

- **D-19 allows:** `UsersController::discover` + `::notifications` (added, 2 actions) + `routes.php` 2 entries (added) + GET render branch on `InboxesController::settings` (added — explicitly called out in plan 07-03 / VERIFICATION §6). All within scope.
- **D-20 forbids:** Model / Migration / OAuth / SSR / moderation. Verified: `src/Model/` unchanged, `config/Migrations/` unchanged, `src/Controller/Auth*` / `src/Controller/Oauth*` unchanged, no SSR-probability logic touched, no Reports/Blocks behavior changed.
- **AuthenticationMiddleware mirror:** both new actions check `$this->Authentication->getIdentity()` and redirect to `/` on null. Same pattern as `UsersController::dashboard()` lines 44-47. Verified consistent.

---

## CakePHP Helper Usage Sign-off

- `tb_tabbar.php`: anchors are bare `<a href=...>` (correct — these are navigation, not destructive actions).
- `dashboard.php`: every form uses `$this->Form->create()` / `->end()` (CSRF tokens preserved); destructive POST routes (`open`, `delete`, `block`) all go through Form helper.
- `settings.php` back button: bare `<button onclick="history.back()">` — covered by L-01.
- `discover.php` / `notifications.php`: no forms (stub screens).

---

## h() Escape Sign-off

Every user-derived emission point in the changed files is `h()`-wrapped:

- `tb_tabbar.php:34` `h($item['href'])` (defensive — the href values are compile-time constants, but `h()` is cheap)
- `tb_tabbar.php:42` `h($item['label'])` (same)
- `dashboard.php` — every `$handle` / `$senderHandle` / `$senderAvatar` / `$senderProfileUrl` / `$slug` / `$bodyPreview` / `$initialChar` / `(string)$msg->id` / `(string)$msg->body` (the last via `nl2br(h($body))` which is the correct order) is escaped.
- `discover.php:38` `h($tag)` (defensive — `$tag` is from a literal array)
- `notifications.php`: no dynamic emissions
- `settings.php`: no direct emissions (delegates to Phase 6 elements which were `h()`-audited in Phase 6 review)

No new instances of attribute-context JS strings, `innerHTML` injection, or pre-escaped slugs (Phase 6 IN-02 pattern is not reintroduced).

---

## Backend-Immutability Audit (Re-confirmation)

Confirmed via `git diff --stat 5385ede..HEAD -- src/ config/Migrations/ src/Model/`:

- `src/Model/` — no changes
- `config/Migrations/` — no changes
- `src/Controller/Oauth*` / `src/Controller/Auth*` — no changes
- `src/Controller/MessagesController.php` — no changes (POST `/open` and `/delete` routes still point at unchanged handlers)
- `src/Controller/ReportsController.php` — no changes
- `src/Controller/BlocksController.php` — no changes

The reachable surface for existing endpoints (`POST /dashboard/messages/{id}/open`, `POST /dashboard/messages/{id}/delete`, `POST /block/{userId}`, `POST /dashboard/settings`, `GET|POST /report/{id}`) is preserved — `dashboard.php` still renders the same forms with the same URLs.

---

_Reviewed: 2026-05-13_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
