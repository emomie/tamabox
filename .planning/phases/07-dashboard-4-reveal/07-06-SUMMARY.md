# 07-06-SUMMARY — Discover tab Empty-state stub (NAV-04)

**Status:** complete

## One-liner
Replaced the 07-02 placeholder with the full 発見 tab Empty-state layout (TbAppBar + disabled search/tag UI + "発見はもうすぐ来ます" empty card + TabBar) and strengthened the auth test to assert body copy.

## Files changed
- `templates/Users/discover.php` (placeholder 2 → 52 lines)
- `tests/TestCase/Controller/UsersControllerTest.php` (testDiscoverAuthRenders200 +1 assertion)

## Decisions
- Search "input" is a `<div>` (not `<input>`) to make the disabled affordance unambiguous — no form, no submit.
- Inline magnifier SVG embedded directly in the template (not added to `icon.php`) because it's a one-call-site usage. If a second use appears later, lift it to `icon.php` per Phase 6 YAGNI rule.
- Tag chips use a single `.is-pseudo-active` first item; all chips carry `aria-disabled="true"` for AT clarity that none of them are interactive.
- The Empty-state card uses the §H.6 `.tb-empty-state` helpers (defined in 07-01) with the ✦ glyph symbol.
- Phase 7 ships ONLY the Empty骨格 per CONTEXT.md D-16 — the rich featured / list section from Discover.jsx is v3 (DISC-01).

## Metrics
- `composer test`: 199 tests / 0 failures, 552 assertions (was 551 — +1 for the body assertion)
- discover.php: 2 → 52 lines

## Verification
- (a) Hi-fi side-by-side with `~/projects/handoff_tamabox/screens/Discover.jsx` lines 30-65: header / search pill / tag chips match. Empty card is a Phase 7-specific substitution per D-16.
- (b) `composer test`: 199 / 0 — green.
- (c) Controller behavior unchanged. UsersController::discover() from 07-02 untouched.
- (d) NAV-04 satisfied (発見タブ Empty-state hi-fi 骨格).
