---
phase: 8
plan: 08-03
status: complete
commit: ec5824e
requirements: [EDGE-02]
completed: 2026-05-13
---

# Plan 08-03 Summary — EDGE-02 SendInboxClosed

## What shipped

- **`templates/Messages/send.php`** — replaced the v1 `!$isAccepting` branch (plain `.tb-card-soft` with title + sub) with the hi-fi SendInboxClosed layout:
  - `.tb-send__closed-status` warm block with kicker dot + uppercase "inbox · paused" + title "この箱はいま受信を停止しています" + body explanatory text.
  - `.tb-send__textarea-block` with a disabled `<div class="tb-input tb-send__closed-input">` stand-in ("受信停止中のため、入力できません").
  - Disabled paper-deep `<button type="button" disabled class="tb-send__closed-cta">送信できません</button>` at the bottom (new `<?php else: ?>` branch in the CTA wrapper).
- **`testSendGetIsAcceptingFalseHidesForm`** updated:
  - Assertion swapped from `'現在この受信箱は受け付けていません'` (v1) to `'この箱はいま受信を停止しています'` (hi-fi).
  - New assertion `'inbox · paused'` (kicker sanity check).
  - Existing `assertResponseNotContains('<textarea name="body"')` preserved (the disabled stand-in is a `<div>`).

## Files touched

- `templates/Messages/send.php` (closed branch markup; CTA closed branch)
- `tests/TestCase/Controller/MessagesControllerTest.php` (1 assertion update + 1 new assertion + comment)

## Tests

```
Tests: 200, Assertions: 557, Incomplete: 6.
OK
```

Delta: 200 → 200 (no count change; existing test strengthened by +1 assertion).

## Decisions confirmed

- D-04: closed-branch markup hi-fi'd; controller untouched.
- D-05: `$inbox->user->display_name` etc. reused via existing `$displayName` / `$slug` upstream variables — no new view data.
- CSRF safety: `$this->Form->create()` only fires inside `<?php if ($isAccepting): ?>` so the closed view emits a plain `<button type="button">` (no token concerns).

## Risks closed

- Flash error string `現在この受信箱は受け付けていません` still appears in `MessagesController.php::processSend` line 241 (the POST-time guard for `is_accepting=false`). Test `testSendPostToClosedInboxRedirectsWithError` asserts on the regex `/受け付けていません/` against the Flash message — unaffected by template change.
- The receiver card at the top of the send screen still renders for the closed branch (matches hi-fi SendErrors lines 82-100 receiver-card-with-opacity treatment). The hi-fi additionally lowers receiver-card opacity to 0.7; v2 ships the full-opacity receiver card (deferred — visual cue is the warm status block below it).

## Unlocks

08-04 (overflow chip) — uses §I.4 CSS already in place + the same `.tb-send__textarea-block` parent that 08-03 didn't touch (closed branch is separate).
