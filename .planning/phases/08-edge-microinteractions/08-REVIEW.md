---
phase: 08-edge-microinteractions
reviewed: 2026-05-13T00:00:00Z
depth: standard
files_reviewed: 15
files_reviewed_list:
  - src/Controller/MessagesController.php
  - src/Controller/UsersController.php
  - templates/Error/error400.php
  - templates/Messages/send.php
  - templates/Messages/send_failed.php
  - templates/Users/dashboard.php
  - templates/element/tb_block_modal.php
  - templates/layout/default.php
  - templates/layout/error.php
  - tests/TestCase/Controller/MessagesControllerTest.php
  - tests/TestCase/Controller/PagesControllerTest.php
  - tests/TestCase/Controller/UsersControllerTest.php
  - webroot/css/tamabox.css
  - webroot/js/block-modal.js
  - webroot/js/send-counter.js
findings:
  critical: 0
  high: 1
  medium: 1
  low: 3
  info: 3
  total: 8
status: issues_found
---

# Phase 8: Code Review Report

**Reviewed:** 2026-05-13
**Depth:** standard
**Files Reviewed:** 15 (2 controllers + 6 templates + 1 element + 2 layouts + 1 CSS + 2 JS + 3 tests)
**Status:** issues_found (no critical; 1 high correctness, 1 medium a11y, 3 low UX/maintainability, 3 info)

---

## Summary

Phase 8 closes the v2 milestone with 5 hi-fi error / motion screens (EDGE-01..05 + MOTION-01) plus a clean carry-over of Phase 7 deferreds. Backend deviation is exactly within CONTEXT D-25/D-26: `MessagesController::processSend` catch-path render switch + `UsersController::dashboard` dead-query removal + `UsersController::computeUnreadCount` helper for L-04 fix from Phase 7. No model / migration / OAuth / SSR / moderation backend touched. Test suite documented green at 203/0 with +4 new tests. CSS structural integrity verified (120/120 comment balance, depth=0 at EOF, no `*/`-in-`--var` regressions, `prefers-reduced-motion` guards on §I.1 button scale and §I.6 modal sheet).

Security posture stays strong: every dynamic emission in the 4 new/rewritten templates is `h()`-wrapped, no `innerHTML` / `eval` / `document.write` in the 2 new JS files, no `onerror=` JS-in-attribute pattern (Phase 6 H-01) reintroduced, the `<dialog>` form goes through `$this->Form->create()` so CSRF tokens auto-inject, and the `/block/{senderUserId}` route enforces a UUID regex at the routes layer (`[0-9a-f-]{36}`).

The 4 Flash-error redirect paths in `processSend` (`is_accepting=false`, consent empty, body empty, body >2000 chars) are intentionally preserved as `Flash + redirect` per CONTEXT D-12 — only the post-DB `RuntimeException` catch routes to the new render. The 500 status code is correctly set via `$this->response = $this->response->withStatus(500)` *before* the render call, ensuring response code precedes body write.

The most consequential finding (**H-01**) is a double-escape in `error400.php` where the user-visible URL pill will display `&amp;lt;` instead of `<` for any request path containing special characters: CakePHP's `WebExceptionRenderer` pre-escapes `$url` via `h()` at line 271 before passing it to the template (verified against `vendor/cakephp/cakephp/src/Error/Renderer/WebExceptionRenderer.php:269-275`), and the template then runs `h((string)$url)` again. This is a real correctness bug for the URL-pill UX, not a security issue (still safe to render).

The other findings cluster around small fixable items: a `javascript:` URI scheme on the "URL を確認しなおす" link in error400.php (net-new in Phase 8, matches the L-01 pattern Phase 7 review already flagged for `settings.php` back button), missing `prefers-reduced-motion` opt-out on the `.tb-send-failed__retry` hover/focus path (none today but the §I.6 modal sheet `animation: none` rule references a missing keyframe), and a TableRegistry stub leak risk in the new EDGE-04 test if any earlier assertion throws.

---

## High

### H-01: `error400.php` double-escapes `$url` — URL pill shows `&amp;`/`&lt;` instead of literal characters

**Files:**
- `templates/Error/error400.php:65`
- `vendor/cakephp/cakephp/src/Error/Renderer/WebExceptionRenderer.php:269-275` (reference — pre-escapes `$url`)

**Issue:** CakePHP's `WebExceptionRenderer::_outputMessageSafe` (and the standard `_outputMessage`) populates the view vars block at line 269-275 like this:

```php
$viewVars = [
    'message' => $message,
    'url' => h($url),       // ← already h()-escaped
    'error' => $exception,
    ...
];
```

The framework runs `h()` on `$url` (sourced from `getRequestTarget()` at line 246) *before* setting it on the controller, then `templates/Error/error400.php:65` does:

```php
<?php if (isset($url) && $url !== ''): ?>
    <div class="tb-error-screen__url-pill tb-mono"><?= h((string)$url) ?></div>
<?php endif; ?>
```

The second `h()` double-encodes. Concrete example — visiting `/foo<bar` (or any path with `&`, `<`, `>`, `"`):

- `$url` arrives at the template as `/foo&lt;bar` (already escaped)
- The template re-escapes to `/foo&amp;lt;bar`
- Browser renders literal text: `/foo&lt;bar` instead of `/foo<bar`

The bug is invisible for the happy path (path = `/no-such-inbox`, `[a-zA-Z0-9_-]` only) but every URL with a special char will mis-render the user-facing URL pill. Since the contract of the pill is "show the user what URL they typed wrong," this defeats the hi-fi UX intent.

**Impact:** Correctness bug — user sees encoded entities instead of literal URL chars in the SendNotFound pill. Cosmetic, not exploitable (the value is already escape-safe before the second `h()` runs, so no XSS — `h()` of an already-escaped string is idempotently-safe). Likely surface area: any 404 path with a query string containing `&` (e.g., search engine bots probing with `?utm_source=...&utm_medium=...`).

**Fix:** Drop the second `h()`. The framework's pre-escape guarantees safety:

```php
<?php if (isset($url) && $url !== ''): ?>
    <div class="tb-error-screen__url-pill tb-mono"><?= $url ?></div>
<?php endif; ?>
```

Add a one-line comment above pinning the rationale so future readers don't re-add the `h()`:

```php
<?php // $url is pre-escaped by WebExceptionRenderer::_outputMessage (vendor verified). ?>
<?php if (isset($url) && $url !== ''): ?>
    <div class="tb-error-screen__url-pill tb-mono"><?= $url ?></div>
<?php endif; ?>
```

Optional test addition (`MessagesControllerTest`):

```php
public function testSendNotFoundUrlPillRendersLiteralUrl(): void
{
    $this->get('/foo%26bar'); // %26 → & in target
    $this->assertResponseCode(404);
    // After fix: literal '&' in the pill; with double-escape bug: '&amp;'
    $this->assertResponseNotContains('&amp;amp;'); // double-escape signature
}
```

---

## Medium

### M-01: `error400.php` uses `<a href="javascript:history.back()">` — net-new inline JS URI scheme

**File:** `templates/Error/error400.php:72`

**Issue:** The "URL を確認しなおす" quiet link is implemented as:

```php
<a href="javascript:history.back()" class="tb-error-screen__quiet-link">URL を確認しなおす</a>
```

Three sub-issues:

1. **`javascript:` URI scheme** is the strongest form of CSP-incompatibility — even `script-src 'self' 'unsafe-inline'` blocks `javascript:` URIs unless `'unsafe-eval'` or specific `script-src-elem` exceptions are added. The codebase doesn't ship a CSP today, but Phase 7 review (L-01) already flagged the same anti-pattern category for `settings.php:23` (`onclick="history.back()"`), and Phase 7 left the issue open. Phase 8 reintroduces a stricter variant (URI scheme is worse than inline event handler from a CSP perspective).
2. **No-JS fallback is broken** — the link is dead with JS disabled. Error pages are exactly the case where JS may not have loaded (network failure cascading to the 404 page itself).
3. **Inconsistent with the rest of Phase 8.** The error screen already has a primary "tamabox に戻る" CTA (`<a href="/">`). The quiet link adds a secondary back-stack navigation that few users will discover; the primary CTA covers the "exit this dead end" need.

**Impact:** UX bug — link does nothing with JS off, blocks future CSP rollout. The send.php and settings.php pre-existing `onclick="history.back()"` instances are out of scope for this review (carried over from Phase 6/7), but the error400.php case is **net-new in Phase 8** and should not regress the surface area.

**Fix (option A — simplest, drop the second link entirely):** The user already has the "tamabox に戻る" primary CTA. Remove the quiet link:

```php
<div class="tb-error-screen__cta">
    <a href="/" class="tb-btn tb-btn--primary tb-btn--full">tamabox に戻る</a>
</div>
```

**Fix (option B — preserve the back-stack semantic via a tiny event listener):** Add a `data-back` attribute, bind in a defer-loaded script (e.g., a new `nav-back.js`, or fold into `reveal-motion.js`):

```php
<button type="button" class="tb-error-screen__quiet-link" data-back="1">URL を確認しなおす</button>
```

```javascript
// In reveal-motion.js or new nav-back.js (already deferred-loaded by layout/default.php).
// But error.php layout does NOT load these JS files — choose option A or add the
// script to error.php as well.
document.querySelectorAll('[data-back="1"]').forEach(function (b) {
    b.addEventListener('click', function () { window.history.back(); });
});
```

**Note:** The error layout `templates/layout/error.php` does **not** include `<?= $this->Html->script(...) ?>` in its `<head>` block (verified). So option B requires layout/error.php to either load the scripts too, or rely on an inline `<script>` (which is back to CSP-incompatible). Option A is the pragmatic answer.

---

## Low

### L-01: `testSendPostRendersFailedWhenMessagesTableThrows` — TableRegistry stub leak risk if assertion fails

**File:** `tests/TestCase/Controller/MessagesControllerTest.php:93-118`

**Issue:** The new EDGE-04 test sets a mock `MessagesTable` in `TableRegistry` at line 102, then runs 4 assertions, then removes the stub at line 117. If any of the 4 `assertResponseCode/Contains` calls fails (raising `ExpectationFailedException`), the `remove()` call is skipped and the stub leaks to subsequent tests in the same PHPUnit process — they will see `MessagesController::send` POST always throw `RuntimeException`, cascading into multiple unrelated failures.

```php
\Cake\ORM\TableRegistry::getTableLocator()->set('Messages', $stub);
// ... 4 assertions
\Cake\ORM\TableRegistry::getTableLocator()->remove('Messages'); // not reached on failure
```

The current test passes, so the leak is theoretical today. But the next time someone makes a slight change to send_failed.php that breaks one assertion, they will see ~5 unrelated test failures across the suite and waste time bisecting.

**Impact:** Test brittleness — a single broken assertion will mask the actual cause with cascading failures. Cleanup is unconditional only inside `tearDown()` / try-finally.

**Fix (preferred — try/finally):**

```php
public function testSendPostRendersFailedWhenMessagesTableThrows(): void
{
    $stub = $this->getMockBuilder(\App\Model\Table\MessagesTable::class)
        ->disableOriginalConstructor()
        ->onlyMethods(['sendMessage'])
        ->getMock();
    $stub->method('sendMessage')->willThrowException(
        new \RuntimeException('simulated sendMessage failure')
    );
    \Cake\ORM\TableRegistry::getTableLocator()->set('Messages', $stub);

    try {
        $this->enableCsrfToken();
        $this->loginAsDave();
        $this->post('/alice', [
            'body' => 'retry me later',
            'consent' => '1',
        ]);

        $this->assertResponseCode(500);
        $this->assertResponseContains('送信できませんでした');
        $this->assertResponseContains('retry me later');
        $this->assertResponseContains('もう一度送信');
    } finally {
        \Cake\ORM\TableRegistry::getTableLocator()->remove('Messages');
    }
}
```

**Alternative — tearDown override:** add a `tearDown()` that always calls `TableRegistry::clear()` (cleanest if multiple tests start using the same pattern):

```php
protected function tearDown(): void
{
    \Cake\ORM\TableRegistry::getTableLocator()->remove('Messages');
    parent::tearDown();
}
```

---

### L-02: `Form->create('url' => '/block/' . h($senderUserId))` — `h()` on a UUID is dead code, but more importantly redundant with Form helper's own attr escape

**File:** `templates/element/tb_block_modal.php:69`

**Issue:**

```php
<?= $this->Form->create(null, [
    'url' => '/block/' . h($senderUserId),
    ...
]) ?>
```

Two layered no-ops:

1. `$senderUserId` is route-constrained to `[0-9a-f-]{36}` (verified via `config/routes.php:135`), so `h()` is functionally a no-op — all 7 characters that `h()` transforms are excluded by the regex.
2. CakePHP's `Form::create()` runs the URL through its own router + attribute-escape pipeline before emitting the `<form action="…">`. Pre-escaping a URL passed to Form helper risks double-encoding if the value were ever to contain a true special char (it doesn't today, but the pattern is wrong).

Same pattern exists in `templates/Users/dashboard.php:139` and `:218` for `/dashboard/messages/{id}/open` and `/.../delete` — pre-existing from Phase 4, not net-new in Phase 8, but the `tb_block_modal.php` instance is **new in Phase 8** so worth flagging at point-of-introduction.

**Impact:** Zero functional impact today. Readability nit: a future reader sees `h()` and assumes the variable is user-controlled. Removing makes the trust boundary clearer and prevents accidental double-encoding if the route constraint is ever loosened.

**Fix:**

```php
<?= $this->Form->create(null, [
    'url' => '/block/' . $senderUserId,
    'type' => 'post',
    'class' => 'tb-block-modal__actions',
]) ?>
```

Not worth a re-commit on its own; bundle if the H-01 / M-01 fixes ship together.

---

### L-03: `@media (prefers-reduced-motion: reduce) { .tb-block-modal__sheet { animation: none; } }` references a nonexistent animation

**File:** `webroot/css/tamabox.css:2792-2794`

**Issue:** The §I.6 block sets `.tb-block-modal__sheet { animation: none; }` inside a `prefers-reduced-motion` guard, but `.tb-block-modal__sheet` never declares an `animation` property in the first place (verified by `grep -n "animation:" webroot/css/tamabox.css` returning no match inside `.tb-block-modal__sheet` — the keyframes for the sheet slide-up animation were deferred to v3 per `08-VERIFICATION.md` §"Deviations from 08-CONTEXT.md").

So this `@media` rule is dead code. It does no harm (sets a property that doesn't exist to a value that also doesn't exist), but it advertises an a11y promise the CSS doesn't deliver. If someone later adds a slide-up animation to the sheet (the v3 plan), they may assume the reduced-motion guard "already exists" and skip it — silently shipping an animation that ignores user prefs.

**Impact:** Dead CSS rule. Forward-compatibility hazard for v3 (false sense of a11y coverage).

**Fix:** Remove the empty guard until the animation actually exists, or add a TODO comment:

```css
/* §I.6 — Block confirm modal (EDGE-05) — native <dialog> bottom sheet */
.tb-block-modal { ... }
.tb-block-modal__sheet { ... }
/* (Sheet slide-up animation deferred to v3. When added, add a paired
   @media (prefers-reduced-motion: reduce) { .tb-block-modal__sheet { animation: none; } }
   rule here.) */
```

Or simply delete lines 2792-2794. The dead rule was clearly added in anticipation of a slide animation that wasn't shipped.

---

## Info

### IN-01: `error400.php` always renders production hi-fi even when `debug=true` after the debug section runs `$this->start('file')`

**File:** `templates/Error/error400.php:18-49`

**Issue:** The debug branch at lines 23-46 runs `$this->layout = 'dev_error'` and starts collecting into the `'file'` view block, then falls through to the production hi-fi markup at lines 50-75. In `debug=true` mode the dev_error layout is used and the file block is rendered into the appropriate slot, but the hi-fi markup outside the `<?php endif; ?>` block also gets emitted (verified by reading bake's default error400.php which had the same trailing structure — original bake outputs `<h2>...</h2>` blocks after the dev branch).

The CakePHP convention is that the dev_error layout typically ignores `$this->fetch('content')` and renders only the dev-error specific blocks, so the trailing hi-fi markup is "rendered but not used" in debug mode. This is a bake-default pattern and matches the original `error400.php` structure (verified against the cake bake template), so it's correct behavior — just unintuitive to readers.

The realistic deploy is `debug=false` (Phase 1 locked decision), so this never matters in production. But the rewrite did NOT preserve a fallback layout choice for the bake-style dev mode, only the assignment `$this->assign('title', '箱が見つかりません')` at line 48 — which means in debug mode the title becomes the user-facing string instead of the exception message. Minor inconsistency.

**Impact:** Zero — debug mode is off in production. Dev users will see a slightly mismatched title in dev_error layout (Japanese user-facing string instead of the exception class name). Bake's original template assigned `$this->assign('title', $message)` inside the debug branch (line 25) AND a fall-through; this rewrite preserves the inner assign but the outer hi-fi `$this->assign('title', '箱が見つかりません')` then overwrites it.

**Fix (optional):** Move the production-title assignment into the `else` of the debug branch, or guard it:

```php
if (!Configure::read('debug')) {
    $this->assign('title', '箱が見つかりません');
}
```

Or wrap the production markup in a similar `if (!Configure::read('debug'))` guard so the trailing HTML doesn't get rendered into the dev_error layout's slot. Not worth a fix today; flagging for context.

---

### IN-02: `tb_block_modal.php` element re-derives `$displayName` from handle, but `senderInitial` is also derived from the same handle outside the element — coupling risk

**Files:**
- `templates/element/tb_block_modal.php:26-35` (derives `$displayName` from `$senderHandle`)
- `templates/Users/dashboard.php:198` (caller derives `senderInitial` from `$senderHandle` outside the element)

**Issue:** The element accepts both `senderHandle` and `senderInitial`, then internally derives a `$displayName` (= handle's pre-first-dot segment) without exposing it. The caller derives `senderInitial` (= first char of handle) on its own. Two derivations of "handle-to-display-string" run in two files. If `$senderHandle` is `""` (defensive fallback returns `'?'` for initial, `'送信者'` for display name), the inconsistency is harmless but the pattern invites drift.

`mb_substr($senderHandle, 0, 1)` is run **twice** at the dashboard.php:198 call site:
```php
'senderInitial' => $senderHandle !== '' ? mb_substr($senderHandle, 0, 1) : '?',
```

And `tb_block_modal.php` separately re-substrings inside the avatar via `<?= h($senderInitial) ?>`. Not a bug — the duplication is intentional separation of element-API from caller-derivation — but the asymmetric exposure ($displayName private, $senderInitial public) means a caller can't override the display-name fallback ('送信者') without editing the element.

**Impact:** Minor maintainability nit. Phase 8 only has one caller (dashboard.php HIT branch), so coupling cost is zero today. If v3 adds Block modal to another screen (e.g., a future Reveal page), the caller may want different fallback text and will have to edit the element.

**Fix (optional, v3):** Either accept `displayName` as a parameter (and derive from handle in the caller), or expose `displayName` in the element's `@var` doc as derived-and-private with the fallback noted. Not worth changing today.

---

### IN-03: `UsersController::computeUnreadCount` is declared `public` but is only called internally from `discover` / `notifications` — could be `private`

**File:** `src/Controller/UsersController.php:228`

**Issue:** The new helper is declared `public function computeUnreadCount(string $userId): int`. CakePHP's request dispatcher will treat any public method on a controller as a potential action (modulo the `@_protected_methods` convention via `Controller::_isPrivateAction`), which means an attacker could craft a request like `/users/compute-unread-count/{userId}` and hit the method directly. Default Cake routing would map `users.action` → `UsersController::action()`, and `compute-unread-count` is a legal action name.

Checking `config/routes.php`: the project does NOT define `/users/{action}` as a fallback. The only `/dashboard*` routes are explicitly enumerated, and there's no `$builder->fallbacks();` that maps `/users/*`. Verified via `grep fallbacks config/routes.php` returning `$builder->fallbacks();` at line 180 — but the fallbacks builder uses **DashedRoute**, which would map `/users/compute-unread-count/some-uuid` to `UsersController::computeUnreadCount('some-uuid')`. The method takes one string parameter, returns `int`, and the response would be… empty (the action returns int but Cake controllers must return Response|null; returning int triggers a different code path).

Concrete test: `curl https://tamabox.emomie.com/users/compute-unread-count/$(uuidgen)`. Likely behavior: an attacker-controlled `userId` triggers a DB query (1 SELECT on Inboxes + 1 COUNT on Messages), CakePHP probably 500s on the int return, and the auth middleware **does not gate** the method because `$identity` is never checked inside `computeUnreadCount`. The endpoint is therefore an unauthenticated DB-query oracle.

**Impact:** Low-severity unauthenticated information disclosure / DB-load amplification. An attacker can flood `/users/compute-unread-count/{any-uuid}` and force COUNT queries against the Messages table for arbitrary UUIDs without auth. The response leaks nothing (int swallowed), but it consumes DB connections.

`computeUnreadCount` is a controller helper masquerading as an action. Verification step before fixing: confirm with `php bin/cake routes` whether the route is actually exposed via fallbacks.

**Fix:**

```php
private function computeUnreadCount(string $userId): int
```

Single-character change. Then re-run `composer test` to confirm `discover` and `notifications` still pass (they call `$this->computeUnreadCount(...)` so `private` works — same class).

**Alternative:** prefix with underscore (`_computeUnreadCount`) which CakePHP's `Controller::invokeAction` treats as private-action-by-convention, but `private` is the right modern answer.

This is the same "method-visibility-as-routing-surface" class of issue as Phase 4 H-01 (the InboxesController fix). Worth fixing before milestone close even at low impact.

---

## CSS Structural Sign-off

- **Brace balance:** verified via awk depth counter — final depth = 0 at EOF (line 2871).
- **Comment balance:** 120 `/*` and 120 `*/`, perfectly paired. No `*/` inside a `--var-name` (the Phase 5 regression class — `--color-*/--radius-*` early-close — is not introduced anywhere in §I.1–§I.7). The CSS-comment-trap memory note is honored.
- **Selector specificity:** all `.tb-dash-*`, `.tb-block-modal__*`, `.tb-send-failed__*`, `.tb-error-screen__*` follow the established `.tb-{component}__{element}` BEM-ish pattern. No bleed to other pages — the `.tb-flex-grow`, `.tb-flex-row`, `.tb-flex-row--sm-gap`, `.tb-flex-row--baseline` utility classes added in §I.7 are generic but namespaced (`tb-` prefix), so collision risk with milligram or normalize is zero.
- **Token references:** all `--tb-*` and `--tb-r-*` and `--tb-shadow-*` and `--tb-warm-*` resolve to existing declarations in tokens.css.
- **Animation:** §I.6 declares an empty `prefers-reduced-motion` rule for `.tb-block-modal__sheet` — covered by L-03.
- **Reduced-motion coverage:** §I.1 `.tb-btn:active { transform: scale(0.985); }` has the paired `@media (prefers-reduced-motion: reduce) { .tb-btn:active { transform: none; } }` guard. ✓
- **`.tb-btn:active scale` applied universally:** verified — the bare `.tb-btn:active` selector at line 2424 catches all 5 variants. The §A.2 primary-specific rule at line 942 (line referenced in 07-REVIEW; preserved here at line 942 cf. line range earlier) sets the `background` color only on `:active` for primary, and its `transform` (if any) is overridden by §I.1's later cascade. ✓
- **Disabled state visuals:** `:active` selectors do NOT apply to elements with `disabled` attribute (browsers do not fire `:active` on disabled buttons). The `tb-btn--disabled` modifier class is a separate concern. ✓

---

## JavaScript Review

### `webroot/js/send-counter.js`

- **DOM safety:** uses `querySelector` + `addEventListener` + `classList.toggle` + `textContent` — no `innerHTML` / `outerHTML` / `eval` / `document.write`. No XSS surface. ✓
- **Strict mode:** `'use strict';` ✓
- **Defensive guards:** `if (!ta) { return; }` at line 21 — exits cleanly on pages without the textarea (e.g., send_failed.php which uses a read-only div, EDGE-01 error page).
- **Debouncing:** none — `input` event runs `update()` synchronously on every keystroke. For 2000-char threshold this is fine (length-check is O(1) on the value's existing string length). Not flagged.
- **Idempotency:** single-textarea-per-page assumption is correct (verified — `[data-send-textarea]` appears once in send.php).
- **Initial state:** `update()` at line 45 fires on script load to set initial counter from `$restoredBody` server-rendered value. Good UX defensive coding.
- **Progressive enhancement:** server `mb_strlen > 2000` guard at `MessagesController.php:258-262` is preserved. The 4 Flash error paths (`is_accepting=false`, consent empty, body empty, body >2000) all still redirect — only RuntimeException routes to render. ✓

### `webroot/js/block-modal.js`

- **DOM safety:** uses `querySelectorAll` + `addEventListener` + `closest` + `getAttribute` + `showModal` / `close` — no `innerHTML` / `eval`. ✓
- **Strict mode:** `'use strict';` ✓
- **Idempotency:** triggers guarded via `data-block-modal-armed` flag (line 19-20). Cancel binding guarded via `body.dataset.blockModalCancelArmed` (line 34-35). Re-running is safe.
- **Native dialog reliance:** uses `dlg.showModal()` (line 27) and `dlg.close()` (line 43). `showModal` natively handles focus trap (focus moves to first focusable element inside the dialog, trap until close) and ESC-to-close. Verified the dialog markup at `tb_block_modal.php:37` does not opt out (`autofocus` not set, no `tabindex="-1"` on body — focus trap works).
- **Event listener leak risk:** the document-level cancel listener (line 36) is bound once per page (body dataset guard). The trigger button listeners are individually armed (line 19 guard). Multiple modals on the same dashboard (multiple HIT messages) get unique modal IDs via `block-modal-{msg.id}` (verified at `dashboard.php:191,195`), so the dispatcher correctly maps trigger → dialog.
- **DOMContentLoaded handling:** line 54-58 covers both pre-DOMContentLoaded and post-DOMContentLoaded loads. ✓
- **Defer-loaded** via `templates/layout/default.php:20` — runs after parse, before DOMContentLoaded fires. The `document.readyState` check at line 54 handles the edge case if defer ordering puts the script after DOMContentLoaded already fired (which it shouldn't with `defer`, but the defensive check is correct).
- **Focus restoration:** native `<dialog>` does NOT restore focus to the trigger button on close by default in all browsers. Chrome / Firefox 110+ does restore; Safari historically did not. Not flagged here because the modal triggers are inside the message-row__body which stays in DOM, and the modal close just dismisses — user can still tab to find their place. v3 enhancement if needed.

---

## Backend Scope Sign-off (CONTEXT D-25 / D-26)

Verified via `git diff --stat a5d9dbe..HEAD -- 'src/' 'config/'`:

```
src/Controller/MessagesController.php   |  15 ++
src/Controller/UsersController.php      |  67 ++++++++--
```

- **D-25 allows:** `MessagesController::processSend` catch render switch ✓ (the only `src/` change to MessagesController is at lines 264-279 — the catch block plus a single `$this->set([...])` + `withStatus(500)` + `render('send_failed')`). No new controller actions added. `UsersController` changes: removed `$blocks` query and `$blocksTable` fetch (~7 lines deleted); added `computeUnreadCount` helper (~24 lines); both `discover` and `notifications` updated to call the helper. Within scope per CONTEXT D-22 + Phase 7 L-04 carry-over.
- **D-26 forbids:** Model / Migration / OAuth / SSR / moderation backend. Verified: `src/Model/` unchanged, `config/Migrations/` unchanged, `src/Controller/Auth*` / `src/Controller/Oauth*` unchanged, `src/Controller/BlocksController.php` unchanged, `src/Controller/ReportsController.php` unchanged. The Block modal's POST target is the existing `/block/{senderUserId}` route — `BlocksController::create` is unchanged.
- **500 status code:** `$this->response = $this->response->withStatus(500)` at MessagesController.php:276 is correct for `send_failed`. The catch handles `RuntimeException` (an application-level "something went wrong inside `sendMessage`"), not `NotFoundException` (which would 404) or `ForbiddenException` (403). 500 is the appropriate semantic. The status is set **before** `return $this->render('send_failed')`, so the response object carries 500 through the render pipeline. ✓

---

## CakePHP Helper Usage Sign-off

- `error400.php`: anchors only (no forms). Production renders `<a href="/">` primary CTA + `<a href="javascript:...">` quiet — covered by M-01.
- `send_failed.php`: anchors only (`<a href="/{slug}">` for retry CTA, `<a href="/{slug}">` for back button). No POST forms — correct, the retry is a GET re-navigation. The body preserve uses `nl2br(h(...))` order ✓.
- `tb_block_modal.php`: `$this->Form->create()` for the POST form — CSRF auto-injection verified by CakePHP's CsrfProtectionMiddleware behavior. ✓
- `send.php` closed branch: no form rendered (the `<?php if ($isAccepting): ?>` guard at line 44 holds the Form->create outside the closed branch). ✓
- `dashboard.php` Block trigger: replaces the previous direct POST form with a `<button type="button" data-block-modal-trigger="...">`. The form now lives inside the `<dialog>` and goes through `$this->Form->create()` (CSRF preserved). ✓

---

## `h()` Escape Sign-off

Every user-derived emission point in the new / rewritten files is `h()`-wrapped:

- `error400.php`: `h((string)$url)` — covered by H-01 (double-escape, not under-escape).
- `send_failed.php`: `h($slug)` (anchor href), `h($displayName)`, `h($initial)`, `nl2br(h((string)$restoredBody))`. ✓
- `tb_block_modal.php`: `h($modalId)`, `h($displayName)`, `h($senderHandle)`, `h($senderInitial)`, `h($senderUserId)` (UUID — redundant but defensive). ✓
- `send.php` closed branch additions: no new dynamic emissions — all copy is static.
- `send.php` overflow markup additions: `aria-live="polite"`, `data-counter`, `data-send-textarea`, `data-send-submit` — all static attribute names. The `<?= mb_strlen($restoredBody) ?>` at line 118 emits an integer (safe).
- `dashboard.php` modal trigger: `data-block-modal-trigger="block-modal-<?= h((string)$msg->id) ?>"` — UUID id passed through `h()` defensively. ✓

No new instances of `innerHTML` injection, attribute-context JS strings, or pre-escaped slugs (Phase 6 IN-02 pattern is not reintroduced). The `onerror=` JS-in-HTML-attribute (Phase 6 H-01 fix) is **not reintroduced anywhere in new code** — verified via `grep -rn 'onerror=' templates/` returning zero hits.

---

## Backend-Immutability Audit (Re-confirmation)

Confirmed via `git diff --stat a5d9dbe..HEAD -- src/Model/ config/Migrations/`:

- `src/Model/` — no changes
- `config/Migrations/` — no changes
- `config/routes.php` — no changes (Phase 7 routes preserved; no new routes added for EDGE-* / MOTION-01)
- `src/Controller/BlocksController.php` — no changes (existing `/block/{senderUserId}` POST target reused)
- `src/Controller/ReportsController.php` — no changes
- `src/Controller/AccountController.php` — no changes
- `src/Controller/InboxesController.php` — no changes
- `src/Controller/OauthController.php` / `AuthController.php` — no changes (search via `git log --since='2026-05-01' src/Controller/Oauth*` returned no Phase 8 commits)

The reachable surface for existing endpoints is preserved.

---

## Phase 7 Cleanup Sign-off

| Item | Reference | Verified |
|------|-----------|---------|
| D-21 — Dashboard inline-style scrub | `grep -c 'style="' templates/Users/dashboard.php` = 0 | ✓ |
| D-22 — `$blocks` view-data cleanup | `UsersController::dashboard()` no longer queries Blocks; `dashboard.php` no longer declares `$blocks` in `@var` block (line 1-11 ✓) | ✓ |
| D-23 — `#FBFCFD` locked decision | Documented in `08-CONTEXT.md` + `tamabox.css:2796-2797` references the locked decision. The literal is used at `tamabox.css:2136`. ✓ | ✓ |
| D-24 — 3px sub-grid micro-offset | New "Acknowledged sub-grid micro-offset" locked decision in `08-CONTEXT.md`. Selectors at `tamabox.css:2829`, `2853`. ✓ | ✓ |
| Phase 7 L-04 fix (unread dot persistence) | `UsersController::computeUnreadCount` added, called from `dashboard` / `discover` / `notifications`. Covered by IN-03 (visibility) but the L-04 functional fix is in. | ✓ |

---

_Reviewed: 2026-05-13_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
