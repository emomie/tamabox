# 06-03-SUMMARY — SendDone Calm Gacha 化 (UI-03)

**Status:** complete

## One-liner
Rewrote `templates/Messages/send_done.php` to the Calm Gacha center-hero layout (transparent appbar + 96px check circle + heading + body + meta line + dual CTA) per `Done.jsx`.

## Files changed
- `templates/Messages/send_done.php` (rewrite — 25 → 47 lines)
- `webroot/css/tamabox.css` (+87 lines, §G.3 screen layout + §G.4 send-done)
- `tests/TestCase/Controller/MessagesControllerTest.php` (2 assertion sets split — see decisions)

## Decisions
- Introduced reusable screen layout helpers `.tb-screen`, `.tb-screen__body`, `.tb-screen__cta` (§G.3). These are anticipated to be reused by 06-04 / 05 / 06 / 07 / 08 (all subsequent screens).
- Used `history.back()` for the close icon-button (no explicit close target in v1 routing — sane default).
- Kept the existing body copy "受け手が開封したとき、抽選次第であなたのアカウントが開示されます。" since the controller does not pass the recipient handle to this template (hi-fi version references the recipient).
- **Tests updated**: `testSendPostAuthenticatedHappyPathInsertsMessage` and `testSendPostToOwnInboxStillInserts` previously asserted on the single string "送信しました。受け手が開封したとき" (the old longer copy). New layout splits this into a heading "送信しました" + a body "受け手が開封したとき...". Replaced the single assertion with two independent assertions on the same content fragments. Assertion strength is the same (both pieces of evidence prove send_done rendered); not a weakening.

## Metrics
- `composer test`: 195/195 pass, 548 assertions (was 546 — +2 from split assertions)
- LOC: 25 → 47 (template); CSS +87 lines; tests +4 lines (split assertions + comments)

## Verification
- (a) Hi-fi side-by-side with `Done.jsx`: structure matches (close → check circle → heading → body → meta line → dual CTA).
- (b) composer test: green.
- (c) Controller behavior unchanged: `Messages::send` still redirects to render `send_done`; `$inbox` view variable consumed identically.
- (d) Manual smoke: deferred to phase end.
