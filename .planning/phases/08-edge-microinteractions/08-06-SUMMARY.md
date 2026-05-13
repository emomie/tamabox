---
phase: 8
plan: 08-06
status: complete
commit: 6925920
requirements: [EDGE-05]
completed: 2026-05-13
---

# Plan 08-06 Summary — EDGE-05 Block confirm modal

## What shipped

- **`templates/element/tb_block_modal.php`** (new, ~75 lines) — native `<dialog class="tb-block-modal">` styled per §I.6 as a bottom sheet. Contains drag handle, gradient avatar circle (CSS gradient `#E7C795 → #B98449`), heading "{display-name} さんをブロック" (display-name = handle pre-first-dot segment, defaulting to "送信者"), 3-item soft-card consequence list with mini-dots, hint paragraph mentioning "設定 → ブロック中", and the POST form to `/block/{senderUserId}` (CSRF auto-injected by `$this->Form->create()`) with danger confirm + quiet cancel.
- **`webroot/js/block-modal.js`** (new, ~50 lines) — IIFE:
  - Binds `[data-block-modal-trigger="<id>"]` click handlers → `document.getElementById(id).showModal()`
  - Delegates document-level click for `[data-block-modal-close]` → `closest('dialog').close()`
  - Idempotent (per-button `data-block-modal-armed` + body-level `data-block-modal-cancel-armed`)
  - Native `<dialog>` ESC handling preserved
- **`templates/Users/dashboard.php`** HIT branch — replaced the inline `Form->create()` block form with a `<button type="button" data-block-modal-trigger="block-modal-{msg->id}">` + `<?= $this->element('tb_block_modal', ...) ?>`. Modal ID is per-message UUID so multiple HIT cards don't collide.
- **`templates/layout/default.php`** — extended script defer list to include `block-modal` alongside `reveal-motion` + `send-counter`.
- **New test** `testDashboardHitMessageRendersBlockModal` — login as alice, GET `/dashboard`, assert:
  - `<dialog class="tb-block-modal"` markup present
  - `data-block-modal-trigger="block-modal-` attribute present on trigger button
  - All 3 consequence strings render
  - Hint copy "解除は" appears
  - Form `action="/block/` preserved (existing BlocksController POST flow untouched)
  - Button labels "ブロックする" + "キャンセル" present

## Files touched

- `templates/element/tb_block_modal.php` (new)
- `webroot/js/block-modal.js` (new)
- `templates/layout/default.php` (script defer list)
- `templates/Users/dashboard.php` (HIT branch replacement)
- `tests/TestCase/Controller/UsersControllerTest.php` (+1 test method)

## Tests

```
Tests: 203, Assertions: 576, Incomplete: 6.
OK
```

Delta: 202 → 203 (+1 new test). JS syntax: `node -c webroot/js/block-modal.js` → OK.

## Decisions confirmed

- D-13: bottom-sheet modal (not inline POST) on Block intent.
- D-14: native `<dialog>` + minimal JS — IE11 ignored (modern browsers only).
- D-15: 3-item consequence list copy locked: "新しいメッセージを受け取りません" / "今回のメッセージは受信箱に残ります" / "ブロックの事実は相手に通知されません".
- D-16: element extracted to `tb_block_modal.php` (consumed once today but the pattern is reusable; CONTEXT D-16 specifies extraction).
- D-17: `data-block-modal-trigger` attribute + ID lookup pattern.
- D-25: no new backend action — POST target `/block/{id}` is the existing `BlocksController::create` route.

## Risks closed

- Multiple HIT messages → multiple dialogs. ID collision avoided by suffixing with `$msg->id` UUID. Verified by browsing the dashboard.php render path.
- CSRF token presence: `$this->Form->create()` auto-injects; verified in browser by inspecting the `<input type="hidden" name="_csrfToken">`.
- Existing `BlocksControllerTest::testCreateInsertsBlockRow` continues to pass — the form action / method / target URL are identical to the old inline form.
- The hi-fi shows a blurred Reveal-HIT under the scrim (DOM trickery); v2 ships the simpler native `::backdrop` blur instead — visually parallel but not pixel-identical. Documented as v3 polish candidate.

## Unlocks

08-07 (Phase 7 cleanup) — the dashboard.php HIT-branch refactor required by 08-06 leaves the file ready for the inline-style scrub.
