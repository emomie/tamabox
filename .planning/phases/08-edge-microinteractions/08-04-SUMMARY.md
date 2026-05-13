---
phase: 8
plan: 08-04
status: complete
commit: 9a093cb
requirements: [EDGE-03]
completed: 2026-05-13
---

# Plan 08-04 Summary — EDGE-03 Send overflow chip + live feedback

## What shipped

- **`templates/Messages/send.php`** markup additions:
  - New `.tb-send__textarea-block__label-row` wrapping the existing `<label>` + a new `<span class="tb-send__overflow-chip" data-send-overflow-chip aria-live="polite">長すぎます</span>` chip.
  - `data-send-textarea` attribute on the `<textarea>`.
  - `tb-send__counter` class on the `#body-counter` span.
  - `data-send-submit` attribute on the submit button.
  - Removed the inline 9-line `<script>` block at the bottom (logic moved to `send-counter.js`).
- **`webroot/js/send-counter.js`** (new, 48 lines) — IIFE that listens to `input` events on the textarea and toggles 4 things when `length > 2000`:
  1. `.tb-send__counter--over` on the counter wrap (color → warm-700)
  2. `.is-overflow` on the textarea (border warm-500 + bg `#FFFBEF`)
  3. `.is-visible` on the chip (display none → inline-flex)
  4. `disabled` attribute on the submit button
- **`templates/layout/default.php`** — extended the script defer list: `Html->script(['reveal-motion', 'send-counter'], ['defer' => true])`.
- **New test** `testSendFormIncludesOverflowChipMarkup` GETs `/alice` and asserts the 4 markup hooks are present.

## Files touched

- `templates/Messages/send.php` (markup additions + inline script removal)
- `webroot/js/send-counter.js` (new file)
- `templates/layout/default.php` (script defer list extension)
- `tests/TestCase/Controller/MessagesControllerTest.php` (+1 test method)

## Tests

```
Tests: 201, Assertions: 562, Incomplete: 6.
OK
```

Delta: 200 → 201 (+1 new test). JS syntax sanity: `node -c webroot/js/send-counter.js` → OK.

## Decisions confirmed

- D-06: existing `maxlength="2000"` HTML5 attribute + server-side guard preserved. JS is additive UX layer.
- D-07: counter color + textarea overflow class + chip + disabled CTA all wired per UI-SPEC.
- D-08: the "highlight overflow chars" hi-fi effect is approximated by warm-500 border + cream bg `#FFFBEF` (range-highlighting inside a native `<textarea>` is not possible without contentEditable; deferred to v3).
- D-09: progressive enhancement — JS-disabled users still get HTML5 maxlength + server guard.

## Risks closed

- `aria-live="polite"` on the chip: initial state `display: none` suppresses screen-reader announcement until the chip becomes visible. Verified by CSS rule order in §I.4.
- The `disabled` attribute on submit prevents form submission, including legitimate retries if the user reduces below 2000 chars. The JS re-enables it via `submit.disabled = over;` where `over` flips back to false on the next `input` event.
- Multiple `<textarea>` ambiguity: this template has exactly one — `data-send-textarea` selector returns first match. Confirmed unique.

## Unlocks

08-05 (SendFailed) — shares `tb-send__body` pattern, no JS interaction.
