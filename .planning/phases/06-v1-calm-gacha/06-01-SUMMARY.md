# 06-01-SUMMARY — AvatarHandleChip Calm Gacha 化 (UI-08)

**Status:** complete

## One-liner
Rewrote `templates/element/avatar_handle_chip.php` to emit `.tb-chip` markup (per Phase 5 TbChip contract) instead of legacy `.avatar-handle-chip` markup. The element is invoked on every authenticated page from `templates/layout/default.php`, so this single change updates the global header chip everywhere.

## Files changed
- `templates/element/avatar_handle_chip.php` (rewrite — same signature)
- `webroot/css/tamabox.css` (+27 lines, §G.1 chip-avatar variant)

## Decisions
- Avatar size in chip context: 20px (down from 24px). Hi-fi TbChip is more compact than the standalone .avatar-handle-chip.
- Handle text now uses `.tb-mono` per Phase 5 copywriting rule (numbers and handles in JetBrains Mono).
- Fallback initial character preserved as `<span>` (no img) — accessibility `aria-hidden="true"`.
- Outer chip gets a secondary class `.tb-chip--user` reserved for future styling differentiation (none added now).

## Metrics
- `composer test`: 195 tests / 0 failures (6 incomplete pre-existing)
- LOC before: 36 → after: 32

## Verification
- (a) Hi-fi side-by-side with `~/projects/handoff_tamabox/components.jsx` lines 76-82: DOM matches (outer chip → avatar → handle text).
- (b) composer test: green.
- (c) Controller behavior unchanged: `templates/layout/default.php` line 31 still passes `['identity' => $identity]`.
- (d) Manual smoke: deferred to phase end.
