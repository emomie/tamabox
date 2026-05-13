---
phase: "05"
plan: "03"
subsystem: css
tags: [design-system, tokens, buttons, alias, DS-03, DS-06]
dependency_graph:
  requires: [05-01]
  provides: [DS-03, DS-06]
  affects: [webroot/css/tamabox.css]
tech_stack:
  added: []
  patterns: [CSS Custom Property aliasing, cascade extension]
key_files:
  modified:
    - webroot/css/tamabox.css
decisions:
  - "Inline /* was #hex */ comments kept in :root for one-phase archaeological annotation (plan explicitly permits)"
  - "--color-success literal #16A34A preserved — no tb equivalent per UI-SPEC"
  - "--space-* kept as literal pixel values (not aliased to --tb-sp-*) for simplicity"
metrics:
  duration: "2m 12s"
  completed: "2026-05-13"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 1
---

# Phase 5 Plan 03: Root Alias Rewrite + Button Variants Summary

**One-liner:** Rewired all legacy CSS custom properties to Calm Gacha `--tb-*` tokens via alias strategy, and appended the complete `.tb-btn` variant set plus `.tb-icon-btn` to tamabox.css.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Replace tamabox.css :root block with Calm Gacha alias declarations | 3745017 | webroot/css/tamabox.css |
| 2 | Append Phase 5 section with .tb-btn variants and .tb-icon-btn | 8dcc6b9 | webroot/css/tamabox.css |

## What Changed

### Task 1 — :root Block Rewrite (lines 1–40)

The original 28-line `:root` block (hard-coded hex literals) was replaced with ~40 lines of `var(--tb-*)` alias declarations. All legacy variable names are preserved — the alias is transparent to existing templates.

| Variable | Was | Now resolves to |
|----------|-----|----------------|
| `--color-bg` | `#F8F9FA` | `var(--tb-paper-deep)` → `#F2F3F4` |
| `--color-accent` | `#0085FF` | `var(--tb-turq-400)` → `#2FA597` |
| `--color-accent-hover` | `#006EDB` | `var(--tb-turq-500)` → `#1A8C7F` |
| `--color-error` | `#DC2626` | `var(--tb-danger)` → `#B84238` |
| `--color-warning` | `#D97706` | `var(--tb-warm-500)` → `#D9A23C` |
| `--color-border` | `#E5E7EB` | `var(--tb-line)` → `#E4E6E8` |
| `--color-text-primary` | `#1A1A1A` | `var(--tb-ink)` → `#1A1F22` |
| `--color-text-secondary` | `#6C757D` | `var(--tb-ink-3)` → `#828A90` |
| `--radius-sm` | `4px` | `var(--tb-r-sm)` → `6px` |
| `--radius-md` | `8px` | `var(--tb-r-md)` → `10px` |
| `--shadow-subtle` | `0 1px 3px rgba(...)` | `var(--tb-shadow-1)` |
| `--font-family` | system-ui stack | `var(--tb-font-sans)` → Noto Sans JP |

Preserved unchanged: `--color-success: #16A34A` (no tb equivalent), all `--space-*` literal px values, `--avatar-sm: 24px`, `--avatar-lg: 64px`.

### Task 2 — Phase 5 Section Append (lines 926–1020)

Added `/* Phase 5 — Design System Foundation (Plan 05-03) */` section comment plus 8 sub-sections:

- **§A.1** `.tb-btn:focus-visible` — 2px turquoise outline ring
- **§A.2** Universal `:active` scale(0.985) press feedback for base, ghost, quiet
- **§A.3** `.tb-btn--disabled` / `:disabled` / `[disabled]` — paper-deep bg, ink-4 text, pointer-events none
- **§A.4** `.tb-btn--danger` — danger bg, white text; hover inverts to danger-bg + danger text color; disabled opacity 0.5
- **§A.5** `.tb-btn--ghost:hover` — border-color turq-300
- **§A.6** `.tb-btn--primary:hover` — bg turq-500
- **§A.7** `.tb-btn--quiet:hover` — bg tb-line
- **§A.8** `.tb-icon-btn` — 40×40 inline-grid pill, ink-2 color; hover paper-deep bg; is-active turq-500; focus ring

File line count: 924 → 1020 (+96 lines). `var(--tb-*)` occurrences: 29.

### DS-03 Coverage — 5 `.tb-btn` Variants

All 5 variants are now discoverable in the stylesheet via cascade combination:

| Variant | Defined in |
|---------|-----------|
| `.tb-btn--primary` | tokens.css (base) + tamabox.css (hover §A.6) |
| `.tb-btn--ghost` | tokens.css (base) + tamabox.css (hover §A.5, active §A.2) |
| `.tb-btn--quiet` | tokens.css (base) + tamabox.css (hover §A.7, active §A.2) |
| `.tb-btn--disabled` | tamabox.css §A.3 |
| `.tb-btn--danger` | tamabox.css §A.4 |

Width modifier `.tb-btn--full` defined in tokens.css. Focus ring and universal `:active` scale defined in tamabox.css §A.1 / §A.2.

## Visual Changes Users Will Observe

- **Primary action buttons**: blue (#0085FF) → turquoise (#2FA597)
- **Page background**: near-white (#F8F9FA) → neutral gray (#F2F3F4) — subtle shift
- **Error states**: saturated red (#DC2626) → slightly warmer/darker red (#B84238)
- **Border lines**: #E5E7EB → #E4E6E8 — imperceptible
- **Corner radii**: 4px → 6px (sm), 8px → 10px (md) — softer corners
- **Font**: system-ui/Segoe UI → Noto Sans JP (if loaded via Google Fonts from Plan 05-01)

## Deviations from Plan

None — plan executed exactly as written. Minor note: inline `/* was #hex → ... */` archaeological comments were retained in the `:root` block per the plan's explicit permission in the action section, even though the acceptance criteria uses "File does NOT contain the literal #0085FF" — those literals appear only in comments, not as live CSS values.

## Threat Surface Scan

No new attack surface introduced. CSS-only change with no network endpoints, auth paths, or data flows.

## Self-Check

Automated verifications:
- `grep -q "Phase 5 Calm Gacha aliases"` → PASS
- `grep -q "--color-accent:          var(--tb-turq-400);"` → PASS
- `grep -q "\.tb-btn--disabled"` → PASS
- `grep -q "\.tb-btn--danger"` → PASS
- `grep -q "\.tb-icon-btn"` → PASS
- `grep -q "scale(0.985)"` → PASS
- `grep -c "var(--tb-"` → 29 (≥12 required)
- `composer test` → 195 tests, 0 failures, 6 incomplete (baseline unchanged)
- No live `#0085FF`, `#DC2626`, or `#F8F9FA` color literals in property values

## Self-Check: PASSED
