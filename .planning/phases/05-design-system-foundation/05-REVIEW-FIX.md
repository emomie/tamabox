---
phase: 05-design-system-foundation
fixed_at: 2026-05-13T00:00:00Z
review_path: .planning/phases/05-design-system-foundation/05-REVIEW.md
iteration: 1
findings_in_scope: 2
fixed: 2
skipped: 0
status: all_fixed
---

# Phase 5: Code Review Fix Report

**Fixed at:** 2026-05-13
**Source review:** `.planning/phases/05-design-system-foundation/05-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope (Critical + Warning): 2
- Fixed: 2
- Skipped: 0
- Info findings also fixed: 2 (IN-02 and IN-03 — trivially adjacent)

---

## Fixed Issues

### WR-01: `.tb-appbar__title` font-size 17px → 18px

**Files modified:** `webroot/css/tamabox.css`
**Commit:** `696ec94`
**Applied fix:** Changed `font-size: 17px` to `font-size: 18px` in `.tb-appbar__title` (tamabox.css line 1115). The value 17px was outside the locked Typography Scale (approved: 22/18/16/15/14/12/11/10px per 05-CONTEXT.md). 18px is the nearest approved size and matches the H2 role for an AppBar title. Added inline comment referencing WR-01.

---

### WR-02: `--tb-sp-*` spacing tokens defined; `--space-*` aliases updated

**Files modified:** `webroot/css/tokens.css`, `webroot/css/tamabox.css`
**Commit:** `e90719d`
**Applied fix:**
- Added `--tb-sp-1` through `--tb-sp-8` (4/8/12/16/20/24/32px) to the `:root` block in `tokens.css` as a new "Spacing scale" section, co-located with other primitive tokens.
- Updated all `--space-*` aliases in `tamabox.css` `:root` to use `var(--tb-sp-N)` instead of raw pixel literals, making the comment accurate.
- Added `--space-5: var(--tb-sp-5)` (20px) which was previously absent (also resolves IN-03).
- `--space-12: 48px` kept as a literal since there is no `--tb-sp-12` equivalent in the 4px-grid scale.

Phase 6+ templates writing `var(--tb-sp-4)` will now resolve correctly to 16px.

---

## Info Findings Fixed (optional, trivially adjacent)

### IN-02: `.button-destructive-bg:hover` hardcoded `#B91C1C` → `var(--tb-danger)`

**Files modified:** `webroot/css/tamabox.css`
**Commit:** `d01784e`
**Applied fix:** Replaced `background-color: #B91C1C` and `border-color: #B91C1C` with `var(--tb-danger)` in `.button-destructive-bg:hover`. The hardcoded value was a Phase 4 legacy hex not covered by the Calm Gacha token system; `--tb-danger` is `#B84238` (the approved danger token). The visual difference is negligible and the hover state is now consistent with all other danger-colored elements.

### IN-03: `--space-5` (20px) added

Resolved as part of the WR-02 fix. See WR-02 above.

### IN-01: Comment inaccuracy

Resolved as part of the WR-02 fix — the `--space-*` aliases now genuinely point to `--tb-sp-*` primitives, making the comment accurate.

---

## Test Results

```
composer test: 195 tests, 546 assertions, 0 failures, 6 incomplete (pre-existing)
```

All tests pass. The 6 incomplete tests are pre-existing and unrelated to CSS changes.

---

_Fixed: 2026-05-13_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
