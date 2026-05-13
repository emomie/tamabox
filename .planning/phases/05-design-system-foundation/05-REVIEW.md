---
phase: 05-design-system-foundation
reviewed: 2026-05-13T00:00:00Z
depth: standard
files_reviewed: 19
files_reviewed_list:
  - templates/element/icon.php
  - templates/layout/default.php
  - webroot/css/colors_and_type.css
  - webroot/css/tokens.css
  - webroot/css/tamabox.css
  - webroot/img/icons/back.svg
  - webroot/img/icons/bell.svg
  - webroot/img/icons/check.svg
  - webroot/img/icons/chevron.svg
  - webroot/img/icons/close.svg
  - webroot/img/icons/compass.svg
  - webroot/img/icons/heart.svg
  - webroot/img/icons/inbox.svg
  - webroot/img/icons/letter.svg
  - webroot/img/icons/more.svg
  - webroot/img/icons/send.svg
  - webroot/img/icons/star.svg
  - webroot/img/icons/user.svg
  - webroot/img/icons/user.svg
findings:
  critical: 0
  warning: 2
  info: 3
  total: 5
status: findings
---

# Phase 5: Code Review Report

**Reviewed:** 2026-05-13
**Depth:** standard
**Files Reviewed:** 19
**Status:** issues_found

---

## Summary

Phase 5 delivers the Calm Gacha design system: token files, semantic alias layer, component CSS (buttons, cards, chips, inputs, tabbar, appbar), 13 SVG icons, and an inline icon helper. The implementation is solid overall. All SVGs are clean and contain no unsafe constructs. The PHP `icon.php` element is correctly guarded against XSS. Token cascade and load order are correct.

Two warnings are raised: a typography scale violation (`17px` in `.tb-appbar__title` is outside the locked approved set), and a missing `--tb-sp-*` definition that creates a permanent undeclared-variable trap for Phase 6+ template authors. Three info items cover a comment inaccuracy, a redundant inline hover style on `.button-destructive-bg`, and a hardcoded hex value not covered by the token system.

No BLOCKERS. The two warnings should be resolved before Phase 6 templates begin consuming the new components.

---

## Warnings

### WR-01: `.tb-appbar__title` uses 17px — outside the locked Typography Scale

**File:** `webroot/css/tamabox.css:1115`
**Issue:** The locked Typography Scale (05-CONTEXT.md "Locked Decision — Typography Scale Override") explicitly enumerates 8 approved sizes: 22 / 18 / 16 / 15 / 14 / 12 / 11 / 10 px. `17px` appears nowhere in this set. The value originates in `05-UI-SPEC.md` line 358 (`font-size: 17px`) inside the AppBar component block, which was written before the locked set was finalized. The locked decision takes precedence and prohibits ad-hoc additions ("新しい size/weight を追加する場合のみ、再度判断 (ad-hoc 追加禁止)").

This will cause Dimension 4 to fail in UI-checker if evaluated strictly, and creates a precedent for unlocked sizes entering Phase 6-8 via copy-paste from the component block.

**Fix (choose one):**

Option A — align to the nearest approved size (18px):
```css
/* tamabox.css line 1115 */
.tb-appbar__title {
    font-size: 18px;  /* was 17px — realigned to approved scale */
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--tb-ink);
}
```

Option B — add 17px to the locked set via a CONTEXT.md entry if the visual distinction from 18px is intentional for the AppBar title role. This requires a new locked decision entry in `05-CONTEXT.md` per the override policy.

---

### WR-02: `--tb-sp-*` spacing tokens are never defined, creating a silent failure trap

**File:** `webroot/css/tamabox.css:18` (comment), `webroot/css/tokens.css` (absent)
**Issue:** The comment in `tamabox.css` line 18 states `--space-* aliased to --tb-sp-*`. The UI-SPEC (lines 70-89) documents `--tb-sp-1` through `--tb-sp-8` as first-class tokens. However, `tokens.css` (copied verbatim from `handoff_tamabox`) does not define any `--tb-sp-*` variables, and `tamabox.css` only defines `--space-*` as raw pixel literals (not `var(--tb-sp-N)`).

As a result, any Phase 6/7/8 template that follows the UI-SPEC and writes `var(--tb-sp-4)` will silently get an unresolved CSS variable (renders as 0 / empty), producing layout bugs with no error output.

Current state is safe because no template yet uses `var(--tb-sp-*)`, but Phase 6 will introduce this failure the moment the first new template follows the spec.

**Fix:** Add the `--tb-sp-*` scale to `tamabox.css` `:root`, and either update the `--space-*` aliases to point at them, or add them as parallel definitions:
```css
/* tamabox.css :root — add inside the existing :root block */

/* Spacing scale — Calm Gacha 4px grid (UI-SPEC lines 70-89) */
--tb-sp-1: 4px;
--tb-sp-2: 8px;
--tb-sp-3: 12px;
--tb-sp-4: 16px;
--tb-sp-5: 20px;
--tb-sp-6: 24px;
--tb-sp-8: 32px;

/* --space-* now alias to --tb-sp-* (removes the comment inaccuracy) */
--space-1:  var(--tb-sp-1);
--space-2:  var(--tb-sp-2);
--space-3:  var(--tb-sp-3);
--space-4:  var(--tb-sp-4);
--space-6:  var(--tb-sp-6);
--space-8:  var(--tb-sp-8);
--space-12: 48px; /* no tb-sp-12 equivalent */
```

---

## Info

### IN-01: Comment inaccuracy — `--space-*` described as aliased to `--tb-sp-*` but uses raw literals

**File:** `webroot/css/tamabox.css:18`
**Issue:** The comment reads `/* --space-* aliased to --tb-sp-* (preserve existing values; --tb-sp-* are identical 4/8/12/16/24/32) */` but the actual declarations (`--space-1: 4px;` etc.) are raw literals, not `var(--tb-sp-N)`. There is no `--tb-sp-*` anywhere. The comment implies an indirection that does not exist.
**Fix:** Resolved by WR-02 fix. If WR-02 is deferred, at minimum rewrite the comment to accurately describe the implementation: `/* --space-* literal values — equivalent to planned --tb-sp-* scale */`.

---

### IN-02: `.button-destructive-bg:hover` hardcodes `#B91C1C` outside the token system

**File:** `webroot/css/tamabox.css:911-912`
**Issue:** The hover state for `.button-destructive-bg` uses `background-color: #B91C1C; border-color: #B91C1C`. This hex value is a darker shade of the danger red but is not defined in `tokens.css` — `--tb-danger` is `#B84238` (the approved danger token) and there is no darker danger variant in the palette. `#B91C1C` is a legacy value from Phase 4 that was not updated during the Phase 5 alias pass.

This is Phase 4 code that survived the Phase 5 `:root` rewrite unchanged, so no regression was introduced, but the value is now inconsistent with the Calm Gacha palette and will look slightly different from danger-colored elements that correctly use `--tb-danger`.

**Fix:**
```css
/* tamabox.css lines 911-912 */
.button-destructive-bg:hover {
    background-color: var(--tb-turq-600); /* or define --tb-danger-dark if a darker danger is needed */
    border-color: var(--tb-turq-600);
}
```
More accurately, if a darker danger hover state is needed, define `--tb-danger-dark` in `tokens.css` and reference it here. Alternatively, accept `--tb-danger-bg` + `color: --tb-danger` (invert pattern) consistent with `.tb-btn--danger:hover`.

---

### IN-03: `--tb-sp-5` (20px) is documented in UI-SPEC but absent from `--space-*` aliases

**File:** `webroot/css/tamabox.css:18-25`
**Issue:** `--tb-sp-5: 20px` is listed in UI-SPEC Spacing Scale (line 75: "20px — AppBar horizontal padding"). The `--space-*` alias block in `tamabox.css` defines `space-1/2/3/4/6/8/12` but there is no `--space-5`. Phase 6+ templates implementing the AppBar layout per spec would have no `--space-5` equivalent to reach for.

The AppBar currently uses the literal `20px` in `tamabox.css:1131` (`.tb-appbar--big` padding), which works correctly. The gap is purely in the token alias coverage.

**Fix:** Add `--space-5: 20px;` to the `:root` block alongside the other `--space-*` aliases (addressed as part of WR-02 fix):
```css
--space-5: 20px; /* tb-sp-5 — AppBar horizontal padding */
```

---

## SVG Security Sign-off

All 13 SVG files in `webroot/img/icons/` were inspected. No `<script>`, `<foreignObject>`, event handler attributes (`onload`, `onerror`, etc.), XML entity declarations (`<!ENTITY`, `DOCTYPE SYSTEM`), or external URI references were found. Path data is pure geometry. The files are safe for both `<img src>` usage and inline inclusion.

The `more.svg` file correctly uses `fill="currentColor" stroke="none"` on its three dot-circles as specified in `05-UI-SPEC.md` (IconMore definition). This is intentional and correct.

---

## PHP Element Sign-off

`templates/element/icon.php` is correctly implemented:

- `$name` is consumed only through a closed `match()` expression — unknown values produce empty string, never echoed raw input
- `$size` is cast to `(int)` before use in the SVG attribute — prevents attribute injection
- `$inner` contains hardcoded SVG literal strings, never user-derived — the `h()` bypass documented in the file header is correct and safe
- `aria-hidden="true"` is set by default, consistent with the decorative icon pattern documented in UI-SPEC line 381
- The absence of `declare(strict_types=1)` is consistent with the established template file convention (verified in `avatar_handle_chip.php`, `block_list.php`, `inbox_settings_form.php`)

---

## Load Order Sign-off

`templates/layout/default.php` implements the correct CSS load order per UI-SPEC line 44:
`normalize.min → milligram.min → tokens → colors_and_type → tamabox`

Google Fonts `<link>` placement is correct (before `Html->css()` call). The `preconnect` for `fonts.gstatic.com` correctly carries the `crossorigin` attribute required for cross-origin font fetching. The `fonts.googleapis.com` preconnect does not carry `crossorigin` — this is correct because the initial DNS/connection is same-credential, only the font binary fetch from `gstatic.com` needs the cross-origin flag.

---

_Reviewed: 2026-05-13_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
