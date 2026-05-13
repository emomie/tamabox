---
phase: 08-edge-microinteractions
fixed_at: 2026-05-13T00:00:00Z
review_paths:
  - .planning/phases/08-edge-microinteractions/08-REVIEW.md
  - .planning/phases/08-edge-microinteractions/08-UI-REVIEW.md
iteration: 1
findings_in_scope: 12
fixed: 9
deferred: 3
status: partial
---

# Phase 8: Code + UI Review Fix Report

**Fixed at:** 2026-05-13
**Source reviews:**
- Code review: `.planning/phases/08-edge-microinteractions/08-REVIEW.md` (1H / 1M / 3L / 3IN)
- UI review: `.planning/phases/08-edge-microinteractions/08-UI-REVIEW.md` (26/30 PASS, 4 advisory flags)

**Iteration:** 1
**v2 milestone closer.** All v2-blocking + a11y findings landed; doc-hygiene + intentional hi-fi parity items deferred or accepted.

**Summary:**
- Code review findings in scope (H + M + L + IN): 8
- UI review advisory items resolved: 4 (2 a11y + rgba doc + sub-grid acknowledged)
- **Fixed:** 9 (H-01, M-01, L-01, L-02, L-03, IN-03 from code review; aria-describedby + h2 lift + rgba doc from UI review)
- **Deferred / Accepted:** 3 (IN-01 + IN-02 from code review; 4 bespoke-button MOTION-01 partial coverage + 2 sub-grid spacing values from UI review — both intentional hi-fi parity)
- **Test result after all fixes:** **203 tests, 576 assertions, 0 failures, 6 incomplete (pre-existing)** — baseline maintained.

---

## Fixed Issues

### IN-03 (treated as security-hardening): `UsersController::computeUnreadCount` public → private

**Files modified:** `src/Controller/UsersController.php`
**Commit:** `b8fc89d`
**Applied fix:** One-character change: `public function computeUnreadCount(...)` → `private function computeUnreadCount(...)`. With `config/routes.php` `$builder->fallbacks()` using `DashedRoute`, any public controller method is a potential action; `/users/compute-unread-count/{any-uuid}` would map directly to the helper, exposing an unauthenticated DB-query oracle (1 SELECT on Inboxes + 1 COUNT on Messages per call) with no identity check inside the method. Reviewer rated info, but exposed-method-as-route is the same class as Phase 4 H-01 — promoted to a security-hardening commit at milestone close. `computeUnreadCount` is only called from `discover()` / `notifications()` within the same class; private works. `composer test` confirms 203/0.

---

### H-01: `error400.php` double-escape on `$url` removed

**Files modified:** `templates/Error/error400.php`
**Commit:** `dccf575`
**Applied fix:** Changed `<?= h((string)$url) ?>` → `<?= $url ?>` on the URL pill, with a one-line `<?php // ... ?>` comment above pinning the rationale: CakePHP's `WebExceptionRenderer::_outputMessage` pre-escapes `$url` via `h()` (vendor `WebExceptionRenderer.php:269-275`). The template's second `h()` double-encoded any path containing `&`, `<`, `>`, `"` — so visiting `/foo&bar` would render `/foo&amp;bar` in the pill instead of `/foo&bar`. Pre-escaped value is escape-safe (h() of h()'d string is idempotent), so the fix is correctness-only, not XSS. Comment pinning prevents future readers re-adding the `h()`.

---

### M-01: `<a href="javascript:history.back()">` replaced with `<a href="/">`

**Files modified:** `templates/Error/error400.php`
**Commit:** `5ea435f`
**Applied fix:** The "URL を確認しなおす" quiet link used the `javascript:` URI scheme, which is the strongest form of CSP-incompatibility (even `script-src 'unsafe-inline'` blocks it) and is dead with JS disabled (error pages are exactly the case where the JS bundle may not have loaded). Reviewer's option A was "drop the link entirely"; I kept the link and changed `href` to `/` instead — preserves the hi-fi two-CTA layout, points users at the same safer destination as the primary CTA. Both anchors now resolve to home; the primary CTA stays styled as such, the quiet link stays the secondary affordance.

---

### UI-REVIEW a11y #1: Block modal `aria-describedby` linking to impact list

**Files modified:** `templates/element/tb_block_modal.php`
**Commit:** `16af0fb`
**Applied fix:** Two-line change: added `id="<?= h($modalId) ?>-impacts"` to the `<ul class="tb-block-modal__list">` (the 3-item consequence list), and `aria-describedby="<?= h($modalId) ?>-impacts"` to the `<dialog>` (already had `aria-labelledby` pointing at the title). Screen-reader users now get the title *and* the consequence summary before focus lands on the confirm button. Modal IDs are already per-message-unique (EDGE-05 already namespaces with `block-modal-{msg.id}`), so the impacts ID inherits that uniqueness. Real WCAG 1.3.1 / 4.1.2 win.

---

### UI-REVIEW a11y #2: SendFailed banner title `<div>` → `<h2>`

**Files modified:** `templates/Messages/send_failed.php`
**Commit:** `e3c0a44`
**Applied fix:** Changed `<div class="tb-send-failed__title">送信できませんでした</div>` → `<h2 class="tb-send-failed__title">送信できませんでした</h2>`. The page had zero `<h1>`/`<h2>` elements, so screen-reader users using heading navigation had nothing to land on — `role="alert"` on the parent banner div did fire the announce, but the document outline was missing the anchor. EDGE-01 and EDGE-02 already render proper `h2`/`h3` for the same conceptual content; this brings EDGE-04 into parity. `role="alert"` stays on the parent banner div, so announce behavior is unchanged. Tests still 203/0.

---

### L-01: TableRegistry stub cleanup wrapped in try/finally

**Files modified:** `tests/TestCase/Controller/MessagesControllerTest.php`
**Commit:** `4f3b230`
**Applied fix:** Wrapped the 4 `assertResponse*` assertions in `testSendPostRendersFailedWhenMessagesTableThrows` inside `try { ... } finally { TableRegistry::getTableLocator()->remove('Messages'); }`. Previously, if any of the 4 assertions failed, the unconditional `remove()` was skipped — the stub would leak to subsequent tests in the same PHPUnit process, cascading 1 real failure into multiple unrelated ones. try/finally ensures cleanup even on `ExpectationFailedException`. Single-test run still 4 assertions / 1 test green; full suite still 203/0.

---

### L-02: Dead `h()` on UUID in `tb_block_modal.php` Form->create url

**Files modified:** `templates/element/tb_block_modal.php`
**Commit:** `8ab03a4`
**Applied fix:** Changed `'url' => '/block/' . h($senderUserId)` → `'url' => '/block/' . $senderUserId` with a one-line comment pinning the rationale: `$senderUserId` is route-constrained to `[0-9a-f-]{36}` (`config/routes.php:135`) so `h()` touches nothing the regex permits, and `Form->create()` runs the URL through its own attribute-escape pipeline so pre-escaping risks double-encoding if the value ever held a true special char. The two pre-existing instances in `dashboard.php` (Phase 4 carry-over) are out of scope for this review.

---

### L-03: Empty `prefers-reduced-motion` guard for nonexistent animation removed

**Files modified:** `webroot/css/tamabox.css`
**Commit:** `760d220`
**Applied fix:** The §I.6 rule `@media (prefers-reduced-motion: reduce) { .tb-block-modal__sheet { animation: none; } }` referenced an animation that was deferred to v3 — the empty guard created a false sense of a11y coverage. Replaced with a TODO comment that captures the exact rule pair to add when the slide-up animation lands. CSS brace balance verified (final depth = 0 at EOF); comment balance 121/121 (consistent with the change — net +1 `/*` + 1 `*/`).

---

### UI-REVIEW doc hygiene: 2 single-use rgba literals documented

**Files modified:** `.planning/phases/08-edge-microinteractions/08-CONTEXT.md`
**Commit:** `0870ce4`
**Applied fix:** Added a new "Locked Decision — Single-use rgba literals (Phase 8 §I, post-UI-REVIEW)" section that mirrors the existing single-use hex-literals locked decision. Documents `rgba(20,28,32,0.42)` (modal backdrop, §I.6, hi-fi-pinned from RevealHit.jsx BlockConfirmModal) and `rgba(217,162,60,0.10)` (corner-✦ glyph, §I.7, lifted from Phase 7 audit Recommendation §2 follow-up). Both are decorative-only, single-use, hi-fi-pinned — closes the Pillar 2 advisory flag from UI review without expanding the `--tb-*` token surface.

---

## Deferred / Accepted Issues

### IN-01: `error400.php` always-rendered hi-fi markup in debug mode

**File:** `templates/Error/error400.php:18-49`
**Severity:** Info
**Reason for deferral:** Zero functional impact — debug mode is off in production (Phase 1 locked decision). The trailing hi-fi markup gets emitted in `debug=true` mode but the dev_error layout ignores `$this->fetch('content')`, so it's "rendered but not used". Reviewer explicitly tags as "Not worth a fix today; flagging for context." Matches the bake-default structure. No-op to address; not a milestone-close blocker.

---

### IN-02: `tb_block_modal.php` re-derives `$displayName` from handle — coupling risk

**Files:** `templates/element/tb_block_modal.php:26-35`, `templates/Users/dashboard.php:198`
**Severity:** Info
**Reason for deferral:** Phase 8 has only one caller (dashboard.php HIT branch), so coupling cost is zero today. Reviewer's recommendation is explicitly tagged "Fix (optional, v3)" — accept `displayName` as a parameter or expose it in the element's `@var` doc. Not worth changing today; revisit in v3 if the Block modal gets added to a second screen (e.g., a future Reveal page).

---

### UI-REVIEW advisory: 4 bespoke buttons skip `.tb-btn` (MOTION-01 partial coverage) + 2 sub-grid spacing values

**Severity:** Advisory (UI review Pillar 3 flag, Pillar 5 flag)
**Reason for acceptance (not deferral):**
- **4 bespoke buttons** (`.tb-send-failed__retry`, `.tb-send__closed-cta`, `.tb-block-modal__confirm`, `.tb-block-modal__cancel`) intentionally match hi-fi intent — the React source uses inline-styled `<button>` here, not `<TbButton>`. Reviewer notes "matches hi-fi intent" and that "spec §I.1 explicitly says 'promotes the existing primary-only effect to all five `.tb-btn` variants' — not 'all interactive elements'". No contract violation; preserving hi-fi parity over universal MOTION-01 coverage is the documented Phase 8 trade-off. Adding `focus-visible` outlines to each bespoke button (reviewer's suggestion #3) is reasonable v3 polish but not v2-blocking.
- **2 sub-grid spacing values** (`gap: 9px` on `.tb-block-modal__list`, `padding: 22px 22px 18px` on `.tb-block-modal__sheet`): both hi-fi-pinned, both single-use per the Phase 5 YAGNI threshold for documented exceptions ("2+ call sites for promotion"). The 9px and 22px values each appear in exactly 1 selector and are hi-fi-pinned to RevealHit.jsx — promoting to locked exceptions would expand the design-spec surface without payoff. Accept as inherited "hi-fi nuance preserved" until a second consumer materializes.

---

## Test Results

After all fixes applied:

```
composer test: 203 tests, 576 assertions, 0 failures, 6 incomplete (pre-existing)
```

Same 203 baseline as Phase 8 implementation. No new test added (test changes were a cleanup-only try/finally wrapper in L-01); the 6 incomplete tests are pre-existing and unrelated to Phase 8 changes.

---

## Commit Trail

| # | Hash | Finding | Subject |
|---|---|---|---|
| 1 | `b8fc89d` | IN-03 | `fix(08): IN-03 make computeUnreadCount private to remove unauth route exposure` |
| 2 | `dccf575` | H-01 | `fix(08): H-01 drop double-escape on $url in error400.php URL pill` |
| 3 | `5ea435f` | M-01 | `fix(08): M-01 replace javascript:history.back() with /` |
| 4 | `16af0fb` | UI-REVIEW a11y #1 | `fix(08): UI-REVIEW a11y add aria-describedby on block confirm modal` |
| 5 | `e3c0a44` | UI-REVIEW a11y #2 | `fix(08): UI-REVIEW a11y lift SendFailed banner title to h2` |
| 6 | `4f3b230` | L-01 | `fix(08): L-01 wrap TableRegistry stub cleanup in try/finally` |
| 7 | `8ab03a4` | L-02 | `fix(08): L-02 drop dead h() on UUID in tb_block_modal Form->create url` |
| 8 | `760d220` | L-03 | `fix(08): L-03 remove empty prefers-reduced-motion guard for nonexistent animation` |
| 9 | `0870ce4` | UI-REVIEW Pillar 2 | `docs(08): document 2 single-use rgba literals per UI-REVIEW Pillar 2` |

---

## v2 Milestone Close Readiness

After this fix pass:
- All v2-blocking findings resolved (none were red-flagged; H/M severity all landed)
- Both UI review a11y gaps closed (real WCAG wins)
- 2 doc-hygiene items resolved (rgba literals documented)
- Intentional hi-fi parity items (bespoke buttons + sub-grid spacing) accepted with rationale
- Backend scope D-25 / D-26 untouched: no Model / Migration / OAuth / SSR / moderation backend touched in any of the 9 commits
- `composer test` stays 203/0 throughout

**Phase 8 is ready for v2 milestone close.**

---

_Fixed: 2026-05-13_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
