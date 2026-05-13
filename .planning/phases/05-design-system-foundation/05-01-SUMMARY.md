---
phase: 05-design-system-foundation
plan: "01"
subsystem: frontend/css
tags: [design-system, tokens, typography, google-fonts, css-chain]
dependency_graph:
  requires: []
  provides: [tokens.css, colors_and_type.css, google-fonts-link, css-chain-order]
  affects: [templates/layout/default.php, webroot/css/]
tech_stack:
  added: []
  patterns: [css-custom-properties, semantic-alias-layer, cascading-css-chain]
key_files:
  created:
    - webroot/css/tokens.css
    - webroot/css/colors_and_type.css
  modified:
    - templates/layout/default.php
decisions:
  - "@import stripped from colors_and_type.css: tokens.css loaded separately by Html->css() chain to avoid redundant HTTP request and potential relative-path 404"
  - "Google Fonts minimal weight set: Noto Sans JP 400/700 + JetBrains Mono 500 only — weight 600 synthesized by browser from 400/700 axis per UI-SPEC"
metrics:
  duration: "~5 minutes"
  completed: "2026-05-13"
  tasks_completed: 3
  tasks_total: 3
  files_created: 2
  files_modified: 1
---

# Phase 05 Plan 01: Design System Foundation — Token Layer + Font Loading

Primitive token variables (--tb-*) and semantic alias layer (--fg-*, --bg-*, --accent, --type-*) deployed to webroot/css/, with Google Fonts preconnect tags and a 5-file CSS load chain in the layout.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Copy tokens.css verbatim | febcbfc | webroot/css/tokens.css (258 lines, 6959 bytes) |
| 2 | Copy colors_and_type.css with @import stripped | febcbfc | webroot/css/colors_and_type.css (76 lines) |
| 3 | Update layout/default.php with Google Fonts + CSS chain | 18d0cd6 | templates/layout/default.php (+4 lines) |

Note: Tasks 1 and 2 were committed together in febcbfc after discovering gsd-tools requires files to be git-tracked (new files must be staged manually before commit). A prior commit 3688586 captured only STATE.md update.

## Files Added

**webroot/css/tokens.css** — 258 lines, 6959 bytes
- Byte-identical to handoff_tamabox/tokens.css (diff returns 0 differences)
- Contains: full --tb-* primitive token set (surfaces, ink, turquoise palette, warm/honey palette, signals, font stacks, radius, shadow) plus utility classes (.tb, .tb-mono, .tb-btn with all variants, .tb-card, .tb-chip, .tb-input, .tb-tabbar, .tb-paper-grain, etc.)

**webroot/css/colors_and_type.css** — 76 lines
- Source (77 lines) minus 1 line: `@import url('tokens.css');` removed
- Contains: semantic foreground/background aliases, --accent layer, type roles (--type-display through --type-label), tracking tokens, elevation aliases, radius aliases, semantic helper selectors (h1/.h1 through .label-en)

## Layout File Modification

`templates/layout/default.php` lines 15-19 (was lines 15-16 before edit):

Inserted after `<?= $this->Html->meta('icon') ?>`:
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500&family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
```

CSS chain updated from 3-file to 5-file:
```php
// Before
<?= $this->Html->css(['normalize.min', 'milligram.min', 'tamabox']) ?>
// After
<?= $this->Html->css(['normalize.min', 'milligram.min', 'tokens', 'colors_and_type', 'tamabox']) ?>
```

No other lines in the file were touched.

## Verification Results

- `diff -q tokens.css` — exit 0 (byte-identical)
- `grep @import colors_and_type.css` — not found (stripped)
- `grep --accent...tb-turq-400` — found
- `grep --type-mono...tb-font-mono` — found
- `grep .label-en` — found
- `php -l templates/layout/default.php` — No syntax errors detected
- `composer test` — 195 tests, 546 assertions, 0 failures (6 pre-existing incomplete)

## Deviations from Plan

**1. [Rule 1 - Discrepancy] Line count differs from plan documentation**
- Plan stated tokens.css = 259 lines; actual source = 258 lines (no trailing newline after last line)
- Plan stated colors_and_type.css source = 78 lines; actual = 77 lines
- Result after @import strip: 76 lines (not 77 as plan expected)
- Fix: None needed — files are byte-identical to source. Plan documentation had off-by-one inaccuracies. Diff verification confirms correctness.

**2. [Rule 3 - Blocking] gsd-tools commit did not stage new untracked files**
- Found during: Task 1/2
- Issue: gsd-tools.cjs commit command did not add untracked new files to index; commit 3688586 only updated STATE.md
- Fix: Manually ran `git add` before `git commit` for new files; Tasks 1 and 2 committed together in febcbfc

## Known Stubs

None. This plan delivers static asset files only — no UI data flow, no stub patterns.

## Threat Flags

None. Only additions are:
- Static CSS files served from /css/ (no new server-side logic)
- Google Fonts cross-origin link tags (accepted per threat model T-05-01; fallback font stack in --tb-font-sans covers T-05-02 mitigate disposition)

## Self-Check: PASSED

- webroot/css/tokens.css: FOUND
- webroot/css/colors_and_type.css: FOUND
- templates/layout/default.php: modified and verified
- Commit febcbfc: FOUND (git log confirmed)
- Commit 18d0cd6: FOUND (git log confirmed)
- composer test: 0 failures
