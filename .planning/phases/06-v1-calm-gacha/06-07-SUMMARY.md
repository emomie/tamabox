# 06-07-SUMMARY — BlockList Calm Gacha 化 (UI-07)

**Status:** complete

## One-liner
Rewrote `templates/element/block_list.php` to the Calm Gacha layout per `Block.jsx`: section label with count → optional note `.tb-card-soft` → card-wrapped list of rows (avatar / handle / unblock pill) with separator borders. Empty state shows a friendly hint card.

## Files changed
- `templates/element/block_list.php` (rewrite — 45 → 65 lines)
- `webroot/css/tamabox.css` (+109 lines, §G.9)
- `tests/TestCase/Controller/UsersControllerTest.php` (assertions broadened to substring — see decisions)

## Decisions
- Preserved test-required legacy classes `block-list` (outer section) and `block-list__row` (each `<li>`) alongside new `.tb-block-list` / `.tb-block-row` classes.
- Empty state inverted from the v1 single-line text into a `.tb-card-soft` with title + body, matching the hi-fi `BlockListEmpty` (without the dashed-border circle decoration — kept simple).
- Avatar fallback uses an inline `onerror` handler that swaps the failed img for a fallback span with the initial — same UX as the v1 `onerror="this.src=..."` but visually richer.
- 解除 button styled as a borderless pill (`.tb-pill-btn`).
- **Tests updated**: `testDashboardRendersBlockListSection` asserted on `class="block-list"` (exact closing quote) and `class="block-list__row"`. The new template emits two classes on each (`block-list tb-block-list` / `block-list__row tb-block-row`), so the closing quote moves. Updated assertions to substring form `class="block-list ` and `class="block-list__row ` — still proves the legacy class is present, same semantic strength.

## Metrics
- `composer test`: 195/195 pass, 548 assertions
- LOC: 45 → 65 (template), CSS +109 lines

## Verification
- (a) Hi-fi side-by-side with `Block.jsx`: structure matches (section label + count → optional note → card list with rows / empty state).
- (b) composer test: green.
- (c) Controller behavior unchanged: `$blocks` shape consumed identically; POST `/dashboard/blocks/{id}/delete` unchanged.
- (d) Manual smoke: deferred to phase end.
