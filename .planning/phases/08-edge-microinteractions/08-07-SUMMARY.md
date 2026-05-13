---
phase: 8
plan: 08-07
status: complete
commit: f852546
requirements: [Phase-7-deferred-D-21, Phase-7-deferred-D-22, Phase-7-deferred-D-23, Phase-7-deferred-D-24]
completed: 2026-05-13
---

# Plan 08-07 Summary — Phase 7 deferred cleanup

## What shipped

### D-21 — Dashboard inline-style elimination

17 inline `style="..."` attributes in `templates/Users/dashboard.php` replaced by §I.7 helper classes (shipped in 08-01). Per UI-SPEC mapping:

| Original location | New class |
|---|---|
| Box card inner wrap | `.tb-flex-grow` |
| Counts row title block | `.tb-flex-row--baseline` |
| Message-row meta wrap | `.tb-flex-grow` |
| Hit-card corner ✦ glyph | `.tb-reveal-hit-card__corner-glyph` |
| Hit-card content stack | `.tb-reveal-hit-card__content` |
| Hit kicker / title / sub | `.tb-reveal-hit-card__kicker` / `__title` / `__sub` |
| Hit mono pct | `.tb-reveal-hit-card__sub-pct` |
| Sender-card middle wrap | `.tb-flex-grow` |
| Sender-card name row | `.tb-flex-row--sm-gap` |
| Sender-card SSR mini-label | `.tb-sender-card__name-ssr` |
| Miss-card content stack | `.tb-flex-grow` |
| Miss kicker / title / sub | `.tb-reveal-miss-card__kicker` / `__title` / `__sub` |
| Miss mono pct | `.tb-reveal-miss-card__sub-pct` |

Verification: `grep -c 'style="' templates/Users/dashboard.php` → **0** (down from 17).

### D-22 — `$blocks` view-variable cleanup

`UsersController::dashboard()` no longer queries or sets `$blocks`:
- Removed the BlocksTable query block (was 8 lines: `Blocks find/where/contain/order/toArray`)
- Removed `'blocks' => $blocks,` from both branches of `$this->set([...])` (happy path + page-out-of-range fallback)
- Replaced with a `// Phase 8 D-22 — block-list query removed...` doc comment
- Dropped `@var array<int, \App\Model\Entity\Block> $blocks` line from dashboard.php PHPDoc

The block list moved to `/dashboard/settings` (Phase 7 D-04) — query lives in `InboxesController::settings()` now. Saves 1 DB roundtrip per dashboard render.

### D-23 — `#FBFCFD` locked decision cross-reference

The exception was already documented in `08-CONTEXT.md` (Phase 8 追記 section, lines 90-95). Cross-referenced in §I.7 of `tamabox.css` via the comment "/* #FBFCFD — Phase 7 deferred color exception, see 08-CONTEXT.md Locked Decisions */" (shipped in 08-01).

### D-24 — Sub-grid 3px micro-offset locked decision

New "Locked Decision — Acknowledged sub-grid micro-offset" section added to `08-CONTEXT.md`. Scope: `.tb-dash-box__url`, `.tb-reveal-hit-card__title`, `.tb-reveal-miss-card__title`. Value: `margin-top: 3px` (1px off the 4-grid). Justification: hi-fi typographic optical baseline.

### Bonus — Literal hex values locked decision

Phase 8 §I introduced 3 single-use literal hex values (`#F0DCA8`, `#FFFBEF`, `#EFD5D2`). Added a "Locked Decision — Single-use literal hex values" section to `08-CONTEXT.md` documenting scope + justification.

## Files touched

- `templates/Users/dashboard.php` (17 inline styles removed, PHPDoc trimmed)
- `src/Controller/UsersController.php` (8 lines BlocksTable query block removed, `'blocks' =>` removed from 2 set calls)
- `.planning/phases/08-edge-microinteractions/08-CONTEXT.md` (2 new locked-decision sections added)

## Tests

```
Tests: 203, Assertions: 576, Incomplete: 6.
OK
```

Delta: 203 → 203 (no count change — pure refactor / cleanup). All existing dashboard test assertions still pass (data-state, ★ 抽選 hit / miss, profile URL, rel=noopener, action=/dashboard/messages/.../open, 開封する, etc.).

## Decisions confirmed

All 4 Phase 7 carry-over items closed:
- D-21 inline-style scrub ✓
- D-22 controller view-data trim ✓
- D-23 #FBFCFD cross-reference ✓
- D-24 3px sub-grid locked decision ✓

## Risks closed

- Render-output equivalence: the helper classes in §I.7 produce identical computed styles to the original inline declarations (visual-regression-free).
- Test suite: existing dashboard tests (UsersControllerTest) all assert on text substrings + class names, never inline styles, so the refactor is invisible to PHPUnit.
- `$blocks` removal: no existing test asserts on this view variable from `/dashboard` (the block-list tests target `/dashboard/settings` since Phase 7).

## Unlocks

08-08 (Final verification report) — Phase 8 code-complete state.
