---
phase: 05-design-system-foundation
plan: "05"
subsystem: css/design-system
tags: [cleanup, verification, design-system, phase5-close]
dependency_graph:
  requires: [05-01, 05-02, 05-03, 05-04]
  provides: [phase-5-verified]
  affects: [webroot/css/, templates/layout/error.php]
tech_stack:
  added: []
  patterns: [legacy-css-cleanup, composer-test-gate]
key_files:
  created:
    - .planning/phases/05-design-system-foundation/05-05-SUMMARY.md
  modified: []
  deleted:
    - webroot/css/home.css
decisions:
  - "home.css deleted (0 references); cake.css + fonts.css retained — referenced in templates/layout/error.php line 26 (our tamabox error layout); defer full error layout migration to Phase 6"
  - "composer test: 195 tests, 546 assertions, 6 incomplete, 0 failures — green"
  - "phpstan level 8: 0 errors"
  - "phpcs CakePHP standard: 0 violations"
metrics:
  duration: ~10 min
  completed: 2026-05-13
  task_count: 3
  file_count: 1
requirements: [DS-01]
---

# Phase 5 Plan 05: Final Verification + Cleanup Summary

**One-liner:** Legacy CSS cleanup (home.css deleted), full test suite green (195/0), Phase 5 awaits human smoke verification on tamabox.emomie.com.

---

## Task 1: Legacy CSS File Investigation and Cleanup

### Grep Investigation

Command run:
```
grep -rn "cake\.css\|fonts\.css\|home\.css" templates/ src/ config/ webroot/
grep -rn "'cake'\|'fonts'\|'home'" templates/ src/ | grep -i "Html->css\|HtmlHelper"
```

**Results:**

| File | References Found | Location |
|------|-----------------|----------|
| `webroot/css/home.css` | **0** | None |
| `webroot/css/cake.css` | **1** | `templates/layout/error.php:26` — `$this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake'])` |
| `webroot/css/fonts.css` | **1** | `templates/layout/error.php:26` — same line as above |

### Disposition

**Case C (mixed):**

- `webroot/css/home.css` — **DELETED** (0 references). This was a CakePHP bake scaffold file (generic homepage styles including old `cakefont` @font-face). No template, controller, or config references it. Commit: `3ffd737`.

- `webroot/css/cake.css` — **RETAINED**. Referenced in `templates/layout/error.php` line 26. This is a tamabox-owned error layout template (not a vendor file). The file provides Milligram overrides (font-family, link color) used when CakePHP renders 404/500 pages. Note: these are old blue-accent values (`#2f85ae`) inconsistent with Calm Gacha; however, deletion without updating the error layout would yield unstyled error pages on production. Deferring error layout migration to Phase 6.

- `webroot/css/fonts.css` — **RETAINED**. Referenced in `templates/layout/error.php` line 26. Contains Raleway `@font-face` definitions used by `cake.css`. Deferring to Phase 6 alongside `cake.css`.

### Phase 6 Action Required

When Phase 6 migrates `templates/layout/error.php` to use the Calm Gacha design system (tokens.css + tamabox.css), the Html->css call should be updated from:
```php
$this->Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake'])
```
to:
```php
$this->Html->css(['normalize.min', 'milligram.min', 'tokens', 'colors_and_type', 'tamabox'])
```
At that point, both `webroot/css/fonts.css` and `webroot/css/cake.css` can be safely deleted.

### Post-Cleanup webroot/css/ Inventory

```
cake.css            ← retained (error.php reference)
colors_and_type.css ← Phase 5 added (DS-01)
fonts.css           ← retained (error.php reference)
milligram.min.css   ← pre-existing base
normalize.min.css   ← pre-existing reset
tamabox.css         ← Phase 5 :root rewrite + component additions
tokens.css          ← Phase 5 added (DS-01)
```
(`home.css` — deleted in this plan)

---

## Task 2: Automated Test Suite + Static Analysis

### composer test

```
PHPUnit 9.6.34
195 / 195 (100%)
Time: 00:32.637, Memory: 42.00 MB
OK, but incomplete, skipped, or risky tests!
Tests: 195, Assertions: 546, Incomplete: 6.
Exit code: 0
```

**Result: PASS** — 195 tests, 546 assertions, 0 failures, 6 incomplete (pre-existing). Phase 5 introduced zero PHP regressions. The 6 incomplete tests were present in the v1 baseline and are unaffected by CSS-only changes.

### composer phpstan

```
PHPStan 1.12 — level 8
No errors
Exit code: 0
```

**Result: PASS**

### composer phpcs

```
phpcs --colors -p
69 / 69 files (100%)
Time: 924ms; Memory: 26MB
No violations
Exit code: 0
```

**Result: PASS**

### Summary: All gates green. No regressions from Phase 5 CSS/template changes.

---

## Task 3: Smoke Verification Checklist (Post-Deploy)

**Status: AWAITING HUMAN VERIFICATION**

Phase 5 code is complete and all automated checks pass. The following visual/functional items require browser-level verification on the live site after deploying to tamabox.emomie.com.

---

### Smoke Verification Checklist — Phase 5 Design System (Post-Deploy)

**Site:** https://tamabox.emomie.com
**Estimated time:** 10–15 minutes
**DevTools needed:** Yes (Network tab + Elements/Computed panel)

#### A. CSS Asset Loading (Network tab)

- [ ] **A-1** Open DevTools Network tab → reload `https://tamabox.emomie.com/` → confirm these 5 CSS files each return **HTTP 200** in this order:
  1. `normalize.min.css`
  2. `milligram.min.css`
  3. `tokens.css`
  4. `colors_and_type.css`
  5. `tamabox.css`
  (No 404 for `tokens.css` or `colors_and_type.css` — these are Phase 5 additions.)

- [ ] **A-2** In the same Network tab, confirm at least one request to `fonts.gstatic.com` for **Noto Sans JP** returns HTTP 200. (Google Fonts CDN is loading the Japanese font.)

#### B. CSS Token Chain (DevTools Elements / Computed)

- [ ] **B-1** In DevTools Elements, select `<html>` or `<body>` → Computed panel → verify CSS custom property `--tb-turq-400` resolves to `#2FA597`.

- [ ] **B-2** Verify `--tb-font-sans` includes `Noto Sans JP` in its value string.

- [ ] **B-3** Verify `--color-accent` resolves through `var(--tb-turq-400)` → final computed value is `#2FA597` (the alias chain from tamabox.css `:root` to tokens.css is intact).

#### C. Visual Checks — Calm Gacha Color Transition

- [ ] **C-1** On the landing page (`/`), the primary CTA button ("Bluesky でログイン" or similar) is **turquoise** (`#2FA597`), NOT the old blue (`#0085FF` or `#2f85ae`).

- [ ] **C-2** The page background color is approximately `#F2F3F4` (slightly cooler gray, Calm Gacha `--tb-bg`), NOT the older `#F8F9FA`.

- [ ] **C-3** Body text appears to render in **Noto Sans JP** — CJK characters (Japanese hiragana/kanji) have cleaner, rounder glyph rendering compared to system fallback fonts.

- [ ] **C-4** Button corner radius looks **visibly softer** — primary buttons have `border-radius: 10px` (Calm Gacha `--tb-r-btn`) vs the older `4px` or `8px`.

#### D. No-Regression Page Walk (7 pages)

Walk through each live page and confirm layout is intact (no overlapping elements, missing borders, broken columns):

- [ ] **D-1** `/` — Landing page / Login page
- [ ] **D-2** `/m/<your-slug>` — Send form (anonymous message compose page)
- [ ] **D-3** `/dashboard` — Inbox / received messages list
- [ ] **D-4** `/dashboard/settings` — Inbox settings (SSR probability, etc.)
- [ ] **D-5** `/dashboard/blocks` — Block list
- [ ] **D-6** `/report/<any-message-id>` — Report form
- [ ] **D-7** `/account/delete` — Account deletion confirmation page

For each page: no visible layout breaks, all text readable, all buttons/links clickable, no console errors in DevTools.

#### E. Icon SVG Loading

- [ ] **E-1** curl or DevTools Network: `https://tamabox.emomie.com/img/icons/inbox.svg` returns HTTP 200 with SVG content containing `viewBox="0 0 24 24"`.

- [ ] **E-2** `https://tamabox.emomie.com/img/icons/bell.svg` returns HTTP 200.

#### F. Error Layout (Optional)

- [ ] **F-1** Visit `https://tamabox.emomie.com/nonexistent-page` → confirm the 404 error page loads without JS errors (even though it still uses the older `cake.css`/`fonts.css`, it should not crash).

---

### Failure Modes to Watch For

| Symptom | Likely Cause | Fix |
|---------|-------------|-----|
| `tokens.css` 404 | File not deployed | Check deploy, verify `webroot/css/tokens.css` exists on server |
| `colors_and_type.css` 404 | File not deployed | Same as above |
| Buttons still blue | tamabox.css `:root` rewrite didn't deploy | Verify `webroot/css/tamabox.css` updated on server |
| Font not loading | Google Fonts `<link>` tag missing | Check `templates/layout/default.php` for the `fonts.googleapis.com` preconnect + stylesheet link |
| Layout broken (overlapping) | `var(--tb-line)` unresolved | Check alias chain in `tamabox.css` `:root` — ensure `--tb-line` is defined in `tokens.css` |
| Console `@import` error | `colors_and_type.css` still has old `@import url('tokens.css')` | Verify the `@import` was stripped from production copy (Plan 05-01 stripped it) |

---

### Resume Signal

Reply **"approved"** (optionally with a screenshot or paste of curl output) if:
- All 5 CSS files load with HTTP 200
- Calm Gacha turquoise is visible on primary buttons
- No layout regressions on the 7 live pages

Reply with a description of any issue observed if not approved (e.g., "tokens.css 404", "buttons still blue", "dashboard layout broken"), so Phase 5 can be revised before closing.

---

## Deviations from Plan

### Auto-fixed Issues

None — plan executed exactly as written with one Case C outcome (mixed: one file deleted, two retained).

### Deviation: Case C (Mixed) CSS Cleanup

**Found during:** Task 1 grep investigation

**Expected:** Planning notes said "preliminary grep during planning showed no hits in templates/ or src/" — implying Case A (all 3 deletable) was the expected outcome.

**Actual:** `templates/layout/error.php` line 26 references both `cake.css` and `fonts.css` via `Html->css(['normalize.min', 'milligram.min', 'fonts', 'cake'])`. This is a tamabox-authored error layout template (not a vendor file), so it counts as an in-scope reference.

**Fix applied:** Rule per plan Case C — deleted only `home.css` (0 references), retained `cake.css` and `fonts.css` with a deferral note for Phase 6.

**Files modified:** `webroot/css/home.css` (deleted), `templates/layout/error.php` (NOT modified — left as-is for Phase 6)

**Commit:** `3ffd737`

---

## DS Requirements Status

| Req ID | Description | Status | Artifact |
|--------|-------------|--------|---------|
| DS-01 | tokens.css + colors_and_type.css loaded via Html->css chain | Satisfied (code-level; awaits visual confirm A-1) | `webroot/css/tokens.css`, `webroot/css/colors_and_type.css`, `templates/layout/default.php` — Plan 05-01 |
| DS-02 | Noto Sans JP + JetBrains Mono via Google Fonts CDN | Satisfied (code-level; awaits visual confirm A-2) | `templates/layout/default.php` — Plan 05-01 Task 3 |
| DS-03 | `.tb-btn` 5 variants + `.tb-icon-btn` in stylesheet | Satisfied (code-level; visually check C-1, C-4) | `webroot/css/tamabox.css` — Plan 05-03 |
| DS-04 | `.tb-card` / `.tb-card-soft` / `.tb-letter` in stylesheet | Satisfied (code-level) | `webroot/css/tamabox.css` — Plan 05-04 |
| DS-05 | `.tb-chip` / `.tb-input` / `.tb-tabbar` / `.tb-appbar` in stylesheet | Satisfied (code-level) | `webroot/css/tamabox.css` — Plan 05-04 |
| DS-06 | Existing pages visually switched from blue to turquoise | Code-level satisfied; **awaits human verify** (check C-1, C-3, D-1 through D-7) | `webroot/css/tamabox.css` `:root` rewrite — Plan 05-03 |

**Overall Phase 5 status: code-level complete, awaits human smoke verification post-deploy.**

---

## Known Stubs

None — no data stubs, hardcoded empty values, or placeholder text introduced in this plan.

---

## Threat Flags

None — no new network endpoints, auth paths, or schema changes introduced. Cleanup + verification only.

---

## Self-Check: PASSED

- `webroot/css/home.css` — MISSING (deleted as intended) ✓
- `webroot/css/tokens.css` — EXISTS ✓
- `webroot/css/colors_and_type.css` — EXISTS ✓
- `webroot/css/tamabox.css` — EXISTS ✓
- `/tmp/phase5_test.log` — EXISTS (195 tests, 0 failures) ✓
- Commit `3ffd737` (Task 1 cleanup) — EXISTS ✓
