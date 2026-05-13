---
phase: "05-design-system-foundation"
plan: "04"
subsystem: "css"
tags: [design-system, css, components, cards, input, tabbar, appbar]
dependency_graph:
  requires: [05-03]
  provides: [DS-04, DS-05]
  affects: [webroot/css/tamabox.css]
tech_stack:
  added: []
  patterns: [cascade-augmentation, locked-spacing-exceptions, bem-modifier]
key_files:
  created: []
  modified:
    - webroot/css/tamabox.css
decisions:
  - "Both .tb-input.is-over-limit and .tb-input--over-limit selectors provided for Phase 6 naming flexibility"
  - "18px card padding applied to .tb-card (cascade augment), .tb-card-soft, .tb-letter as locked spacing exception"
  - ".tb-tabbar__icon helper added alongside .tb-unread-dot to enable positional anchoring"
metrics:
  duration: "5 minutes"
  completed: "2026-05-13"
  tasks_completed: 1
  files_modified: 1
---

# Phase 5 Plan 04: DS-04 + DS-05 Component CSS Summary

**One-liner:** Added cards (.tb-card padding+shadow, .tb-card-soft, .tb-letter), chip SVG sizing, input states (over-limit+disabled), unread dot, and full appbar family to tamabox.css.

## What Was Built

### Component Classes Added

**§B — Cards (DS-04)**
- `.tb-card` — augmented with `padding: 18px` and `box-shadow: var(--tb-shadow-1)` (tokens.css provides bg+border+radius base)
- `.tb-card-soft` — new: soft gray surface, `background: var(--tb-card-soft)`, `border`, `border-radius: var(--tb-r-lg)`, `padding: 18px`, no shadow
- `.tb-letter` — new: honey-bordered letter card with `border-left: 3px solid var(--tb-warm-500)`, `border-radius: var(--tb-r-md)`, `padding: 18px`, `box-shadow: var(--tb-shadow-2)`

**§C — Chip icon sizing (DS-05)**
- `.tb-chip svg` — sizing helper: `width: 12px; height: 12px; display: block`

**§D — Input states (DS-05)**
- `.tb-input.is-over-limit` + `.tb-input--over-limit` — dual-selector over-limit state: `border-color: var(--tb-warm-700)`
- `.tb-input-counter--over-limit` — companion char counter class: `color: var(--tb-warm-700)`
- `.tb-input:disabled` + `.tb-input[disabled]` — disabled state: `opacity: 0.5; cursor: not-allowed`

**§E — Tabbar augmentation (DS-05)**
- `.tb-tabbar__item .tb-unread-dot` — 6px turquoise dot, absolutely positioned top-right of icon wrapper
- `.tb-tabbar__item .tb-tabbar__icon` — position helper: `position: relative; display: inline-flex`

**§F — AppBar family (DS-05)**
- `.tb-appbar` — 56px height, flex row, `background: var(--tb-paper)`, `border-bottom: 1px solid var(--tb-line)`, `flex: 0 0 auto`
- `.tb-appbar__title` — 17px/700/`letter-spacing: 0.02em`
- `.tb-appbar__sub` — 11px/`--tb-ink-3`/uppercase/`letter-spacing: 0.08em`
- `.tb-appbar--big` — height auto, `padding: 8px 20px 12px`, no border-bottom
- `.tb-appbar--big .tb-appbar__title` — font-size 22px override
- `.tb-appbar--transparent` — `background: transparent; border-bottom: none`

### Locked Spacing Exceptions Applied

Per CONTEXT.md "Locked Decision — Spacing Exceptions":
- `18px` — `.tb-card` internal padding (cascade augment over tokens.css base) — approved exception
- `18px` — `.tb-card-soft` internal padding — approved exception
- `18px` — `.tb-letter` internal padding — approved exception
- `6px` (chip gap) and `14px` (input padding) inherited from tokens.css — no action needed in this plan

### Cascade Notes

`tokens.css` (loaded earlier) provides base rules for:
- `.tb-card` (bg/border/radius) — this plan adds padding+shadow on top
- `.tb-chip` + variants (complete base) — this plan adds only svg sizing helper
- `.tb-tabbar` + `.tb-tabbar__item` + `.is-active` (complete base) — this plan adds `.tb-unread-dot`
- `.tb-input` + `:focus` + `::placeholder` + `.tb-label` (complete base) — this plan adds disabled + over-limit states

`tamabox.css` is loaded last per layout/default.php cascade order — its rules augment without conflicting.

### tamabox.css Final Line Count

1142 lines (was 1020; +122 lines added by this plan).

## Commits

| Hash | Message |
|------|---------|
| `66202e5` | feat(05-04): add DS-04+DS-05 component CSS to tamabox.css |

## Deviations from Plan

None — plan executed exactly as written.

## Threat Flags

None. CSS-only changes, no new data flows, auth paths, or trust boundaries.

## Self-Check: PASSED

- `webroot/css/tamabox.css` exists and contains all required selectors
- Commit `66202e5` exists in git log
- `padding: 18px` appears 3 times (one per card variant)
- `composer test` exits 0 (195 tests, 0 failures, 6 pre-existing incomplete)
