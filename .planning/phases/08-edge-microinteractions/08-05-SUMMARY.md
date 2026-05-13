---
phase: 8
plan: 08-05
status: complete
commit: cb40093
requirements: [EDGE-04]
completed: 2026-05-13
---

# Plan 08-05 Summary — EDGE-04 SendFailed render path

## What shipped

- **`src/Controller/MessagesController.php::processSend`** — the `catch (RuntimeException $e)` block (the only Phase 8 backend touch, allowed under D-25) now sets `$inbox` + `$restoredBody`, calls `$this->response->withStatus(500)`, and `return $this->render('send_failed')`. Previous behavior: Flash + redirect to `/{slug}` GET (lost body).
- **`templates/Messages/send_failed.php`** (new, ~65 lines):
  - TbAppBar with back arrow to `/{slug}`
  - `.tb-send-failed__banner` danger block: 24px circle with `!` glyph + title "送信できませんでした" + sub "通信が不安定なようです。本文はそのまま残してあります。" + retry pill link to `/{slug}`
  - Receiver card (`.tb-card.tb-send__receiver` — same markup as send.php receiver section)
  - Preserved body shown read-only in `.tb-input.tb-send-failed__body-preserved` (`nl2br(h($restoredBody))`)
  - Full-width primary "もう一度送信" anchor CTA → `/{slug}`
- **New test** `testSendPostRendersFailedWhenMessagesTableThrows`:
  - Uses `TableRegistry::getTableLocator()->set('Messages', $mockStub)` to swap MessagesTable with a stub whose `sendMessage` always throws RuntimeException.
  - Logs in as Dave, POSTs `/alice` with valid body + consent.
  - Asserts response 500 + body contains "送信できませんでした" + the verbatim posted body "retry me later" + "もう一度送信" CTA.
  - Cleans up the TableRegistry stub after.

## Files touched

- `src/Controller/MessagesController.php` (catch block, +9 lines / -3 lines)
- `templates/Messages/send_failed.php` (new, 65 lines)
- `tests/TestCase/Controller/MessagesControllerTest.php` (+33 lines new test)

## Tests

```
Tests: 202, Assertions: 566, Incomplete: 6.
OK
```

Delta: 201 → 202 (+1 new test).

## Decisions confirmed

- D-10: render-pattern (not Flash + redirect) on RuntimeException; preserves body + receiver for retry.
- D-11: independent `send_failed.php` template (not folded into send.php) per UI-SPEC discretion area note (retry CTA URL differs and the banner-first layout is cleaner standalone).
- D-12: other Flash errors (consent / empty / length>2000) stay on Flash + redirect. They are UX-natural to bounce to the form (user-side correctable input issues).
- D-25: only the `processSend` catch block is touched in the controller; no new actions, no Model touch.

## Risks closed

- `withStatus(500)` interaction with CakePHP error middleware: tested green — middleware does not intercept a successfully rendered response, only uncaught exceptions.
- Body preservation: `$restoredBody` is the raw POST body, escaped at render via `h()` + `nl2br`. No double-escape.
- The retry GET re-renders the empty form (session-restore only triggers on `?restored=1` after OAuth callback). User sees an empty box and re-types — acceptable for the v2 ship, documented as v3 localStorage candidate.

## Unlocks

08-06 (Block confirm modal) — uses the same `$this->Form->create()` CSRF pattern as 08-05's receiver card.
