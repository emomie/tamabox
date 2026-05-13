---
phase: 5
slug: design-system-foundation
overall: flagged
audited: 2026-05-13
baseline: 05-UI-SPEC.md (approved)
screenshots: not captured (no dev server)
---

# Phase 5 — UI Review: Design System Foundation

**Audited:** 2026-05-13
**Baseline:** `05-UI-SPEC.md` (Calm Gacha token + component contract)
**Screenshots:** Not captured — no dev server detected at localhost:3000 / :8080. Code-only audit.

---

## Pillar Scores

| Pillar | Score | Status | Key Finding |
|--------|-------|--------|-------------|
| 1. Copywriting | 4/4 | PASS | No user-facing strings added; forbidden patterns absent; icon.php security comments correct |
| 2. Visuals | 3/4 | FLAG | All 17 component classes present and dimensionally correct; `.button` backward-compat lacks pill radius and 52px height vs spec |
| 3. Color | 4/4 | PASS | Zero v1 blue leaks; all aliases resolve through `--tb-*` chain; `#fff` and flash-state literal backgrounds acceptable |
| 4. Typography | 4/4 | PASS | Override applied; 8 approved sizes implemented; WR-01 (17px→18px) confirmed fixed; 4 approved weights only; legacy 24px/32px in pre-Phase-5 sections |
| 5. Spacing | 3/4 | FLAG | `--tb-sp-*` tokens complete (WR-02 resolved); 3 approved exceptions present correctly; `gap: 5px` in `.tb-phone__statusicons` (phone frame — not deployed); `margin-top: 2px` in `.tb-appbar__sub` is cosmetic micro-offset |
| 6. Experience Design | 4/4 | PASS | All 5 button states implemented; focus-visible on `.tb-btn` and `.tb-icon-btn`; all 4 input states present; `aria-label` requirement documented in code comment + icon.php docblock |

**Overall: 22/24**

---

## Top 3 Priority Fixes

1. **`.button` backward-compat uses `min-height: 44px` and `border-radius: var(--radius-md)` instead of `height: 52px` and `border-radius: var(--tb-r-pill)`** — existing templates using `.button` will render shorter, square-cornered buttons that visually diverge from the `.tb-btn--primary` contract specified in UI-SPEC line 279. Fix: update `tamabox.css` lines 50-58 to add `height: 52px; border-radius: var(--tb-r-pill);` and remove `min-height: 44px`.

2. **`.button.button-clear` maps to `color: var(--color-text-secondary)` with `border: none`** — the spec (UI-SPEC line 280) mandates ghost visual: `color: var(--tb-turq-700); border: 1px solid var(--tb-turq-200)`. Current mapping uses tertiary ink color and drops the border entirely, producing a visually weaker ghost that does not match the Calm Gacha ghost variant. Fix: update `tamabox.css` lines 65-70 to set `color: var(--tb-turq-700); border: 1px solid var(--tb-turq-200);`.

3. **`gap: 5px` in `.tb-phone__statusicons`** — this is a non-4-multiple value (not in the three approved exceptions: 6/14/18px) and not documented as a locked exception. The phone frame is not deployed in any PHP template, so it has zero production impact today, but the spacing violation will generate false positives in future audits. Fix: change to `gap: 4px` or `gap: 8px`, or add a locked-decision annotation to `05-CONTEXT.md` if 5px is the visual intent.

---

## Detailed Findings

### Pillar 1: Copywriting (4/4) — PASS

Phase 5 adds zero user-facing string content (no screens shipped). Audit scope was limited to code comments and the icon helper file.

- No forbidden patterns found: grep for `ガチャ`, `大当たり`, `やった`, `SSR ゲット` across all `templates/**/*.php` returned zero results.
- No generic English CTA strings (`Submit`, `Click Here`, `OK`, `Cancel`, `Save`) found in templates.
- `templates/element/icon.php` — security model correctly documented: `$name` matched against closed PHP `match` expression, unknown values emit empty string. Comment at line 14 correctly instructs template authors NOT to pipe `$inner` through `h()`. Accessible-name responsibility delegated to parent `aria-label` per spec line 381.
- Copywriting contract strings (Phase 6 source of truth) are defined in UI-SPEC and are not yet deployed — correct for a foundation-only phase.

No deductions.

---

### Pillar 2: Visuals (3/4) — FLAG

**What passes:**

All 17 required component classes confirmed present across `tokens.css` and `tamabox.css`:
`.tb-btn--primary`, `.tb-btn--ghost`, `.tb-btn--quiet`, `.tb-btn--disabled`, `.tb-btn--danger`, `.tb-btn--full`, `.tb-card-soft`, `.tb-letter`, `.tb-chip--warm`, `.tb-chip--quiet`, `.tb-input`, `.tb-tabbar`, `.tb-tabbar__item`, `.tb-appbar`, `.tb-appbar--big`, `.tb-appbar--transparent`, `.tb-icon-btn`

Dimensions confirmed correct:
- `.tb-btn`: `height: 52px`, `padding: 0 22px`, `border-radius: var(--tb-r-pill)` — matches spec line 259
- `.tb-tabbar`: `height: 72px` — matches spec line 332
- `.tb-appbar`: `height: 56px`, `padding: 0 16px` — matches spec lines 350-352
- `.tb-icon-btn`: `width/height: 40px`, `border-radius: 999px` — matches spec line 377
- `.tb-letter`: `border-left: 3px solid var(--tb-warm-500)`, `border-radius: var(--tb-r-md)` — matches spec line 290
- `.tb-appbar__title` font-size `18px` confirmed (WR-01 fix applied, `/* was 17px */` comment present)
- SVG icons: all 13 files present at `webroot/img/icons/`; spot-checked `inbox.svg`, `send.svg`, `back.svg`, `star.svg` — path data matches handoff spec verbatim; wrapper attributes (`stroke-width="1.6"`, `stroke-linecap="round"`, `stroke-linejoin="round"`) correct.
- `icon.php` inline helper: all 13 named icons, correct `match` mapping, correct SVG wrapper, `aria-hidden="true"` default.
- `.tb-appbar--big`: `padding: 8px 20px 12px`, title `font-size: 22px` — matches spec lines 366-371.
- `.tb-appbar--transparent`: `background: transparent; border-bottom: none` — matches spec line 372.
- `.tb-unread-dot` positioning: `position: absolute; top: -2px; right: -4px` with `.tb-tabbar__icon { position: relative }` anchor — matches spec lines 345-347.

**Flag — `.button` backward-compat height and radius mismatch:**

`tamabox.css` lines 50-58:
```css
.button, button, input[type='submit'] {
    min-height: 44px;          /* should be height: 52px per spec line 279 */
    border-radius: var(--radius-md); /* should be var(--tb-r-pill) per spec line 279 */
}
```
UI-SPEC line 279 states these selectors must alias to `.tb-btn--primary` visual values, which specifies `height: 52px` and `border-radius: var(--tb-r-pill)`. The current rule produces a 44px minimum-height square-cornered button. This is a visual deviation on every existing page that uses the `.button` / `<button>` element.

**Flag — `.button.button-clear` does not match ghost spec:**

`tamabox.css` lines 65-70:
```css
.button.button-clear, button.button-clear {
    background: transparent;
    color: var(--color-text-secondary);  /* --tb-ink-3: #828A90 — not turq-700 */
    border: none;                        /* ghost needs 1px solid --tb-turq-200 */
}
```
UI-SPEC line 280 mandates ghost visual: `color: var(--tb-turq-700); border: 1px solid var(--tb-turq-200)`. The current mapping is visually closer to the `.tb-btn--quiet` variant (muted ink, no border) than the ghost.

---

### Pillar 3: Color (4/4) — PASS

**Zero v1 blue palette leaks confirmed:**

Grepped `tamabox.css` and `tokens.css` for: `#0085FF`, `#006EDB`, `#DC2626`, `#D97706`, `#F8F9FA`, `#6C757D`, `#1A1A1A`, `#E5E7EB` — zero matches in active rules. The comment text references old values (e.g., `/* was #0085FF */`) but these are documentation-only; no actual CSS value uses them.

**Token chain integrity verified:**

- `--color-accent` → `var(--tb-turq-400)` → `#2FA597` — correct
- `--color-bg` → `var(--tb-paper-deep)` → `#F2F3F4` — correct (page surface is the recessed neutral, not the card white)
- `--color-error` → `var(--tb-danger)` → `#B84238` — correct
- `--color-warning` → `var(--tb-warm-500)` → `#D9A23C` — correct
- `--color-border` → `var(--tb-line)` → `#E4E6E8` — correct

**Acceptable literal hex values:**

- `#16A34A` (`--color-success`) — documented in spec as "no tb equivalent — keep literal" (`tamabox.css` line 13). PASS.
- `#FEF2F2`, `#FFFBEB`, `#F0FDF4` — flash message tint backgrounds. Pre-Phase-5 legacy values; not replaced by token chain because no `--tb-*` flash-surface equivalents exist. Acceptable.
- `#fff` in `.tb-btn--primary` and `.tb-input:focus` — spec explicitly states `color: #fff` for primary button text (UI-SPEC line 268) and `background: #fff` for focused input. PASS.
- `#0a0a0a` in `.tb-phone__notch` — phone frame simulator element, not a deployed UI component. Acceptable.

**Honey accent scope verified:**

`.tb-letter` uses `border-left: 3px solid var(--tb-warm-500)` — matches spec restriction.
`.tb-chip--warm` uses `--tb-warm-100` background / `--tb-warm-700` text — matches spec.
No honey color found on non-approved elements.

**`colors_and_type.css` @import check:** No `@import` statement present — correctly stripped for production use. PASS.

---

### Pillar 4: Typography (4/4) — PASS

**Locked override applied** per `05-CONTEXT.md` — 8 sizes / 4 weights approved. This pillar is evaluated under the override.

**Approved sizes confirmed implemented:**

| Size | Token | Location | Confirmed |
|------|-------|----------|-----------|
| 22px | `--type-h1`, `--type-display` | `colors_and_type.css:34-35`, `tamabox.css:1136` | Yes |
| 18px | `--type-h2` | `colors_and_type.css:36`, `tamabox.css:1116` | Yes |
| 16px | `--type-body-lg` | `colors_and_type.css:39` | Yes |
| 15px | `--type-h3`, `.tb-btn`, `.tb-input` | `colors_and_type.css:37`, `tokens.css:153,240` | Yes |
| 14px | `--type-body` | `colors_and_type.css:38` | Yes |
| 12px | `--type-mono`, `.tb-label` | `colors_and_type.css:41`, `tokens.css:250` | Yes |
| 11px | `--type-meta`, `.tb-chip`, `.tb-appbar__sub` | `colors_and_type.css:40`, `tokens.css:192`, `tamabox.css:1122` | Yes |
| 10px | `--type-label`, `.tb-tabbar__item` | `colors_and_type.css:42`, `tokens.css:224` | Yes |

**WR-01 fix confirmed:** `.tb-appbar__title` is `font-size: 18px` (`tamabox.css` line 1116) with comment `/* was 17px — realigned to approved typography scale (WR-01) */`. No 17px value found anywhere.

**Approved weights:** 400, 500, 600, 700 — all four present, none beyond this set in Phase 5 authored CSS. PASS.

**Legacy off-scale values in pre-Phase-5 sections:**

`tamabox.css` contains `font-size: 24px` (7 occurrences) and `font-size: 32px` (1 occurrence, `.display-heading`). These are in Phase 2/3/4 authored sections (`.header-bar-title a`, `.dashboard-page h1`, `.display-heading`, etc.) and are **not** introduced by Phase 5. They are pre-existing and out of scope for this phase's type scale audit. Phase 6 screen replacements will migrate these to approved scale values. Noted here as informational only; no deduction.

**`--type-*` shorthand tokens** confirmed in `colors_and_type.css` with correct font/size/line-height shorthand and matching `--tracking-*` variables.

---

### Pillar 5: Spacing (3/4) — FLAG

**`--tb-sp-*` token scale complete (WR-02 confirmed):**

All 7 spacing tokens declared in `tokens.css` lines 58-65:
`--tb-sp-1: 4px`, `--tb-sp-2: 8px`, `--tb-sp-3: 12px`, `--tb-sp-4: 16px`, `--tb-sp-5: 20px`, `--tb-sp-6: 24px`, `--tb-sp-8: 32px`

**Legacy `--space-*` aliases complete:**

`tamabox.css` lines 19-26: `--space-1` through `--space-8` all alias to `--tb-sp-*`. `--space-5` added (IN-03 fix). `--space-12: 48px` literal preserved per spec. PASS.

**Approved spacing exceptions correctly applied:**

| Exception | Location | Selector | Status |
|-----------|----------|----------|--------|
| `6px` gap | `tokens.css:187` | `.tb-chip` | PASS |
| `14px` padding | `tokens.css:238` | `.tb-input` (`padding: 14px 14px`) | PASS |
| `18px` padding | `tamabox.css:1036,1046,1055` | `.tb-card`, `.tb-card-soft`, `.tb-letter` | PASS |

All three exceptions are component-internal only; no layout-level spacing uses non-4-multiple values.

**Flag — `gap: 5px` in `.tb-phone__statusicons` (tokens.css line 111):**

`5px` is not in the three approved spacing exceptions and is not a multiple of 4. The element is the phone-frame status bar icon row (a design-system mockup container), confirmed absent from all PHP templates — zero production impact. However, the value is undocumented and will surface as a spacing audit violation in future automated checks.

Recommended fix: change to `gap: 4px` or `gap: 8px`; or if 5px is the handoff-accurate value, append a note to the Locked Decision — Spacing Exceptions entry in `05-CONTEXT.md`.

**Note — `margin-top: 2px` values:**

Two occurrences: `.tb-appbar__sub` (`tamabox.css:1126`) and `.account-delete-form__consent input` (`tamabox.css:898`). The appbar sub value matches the spec exactly (UI-SPEC line 362). The consent input value is a pre-Phase-5 alignment tweak. Neither is a layout-level spacing value; both are sub-pixel nudges consistent with the handoff source. No deduction.

---

### Pillar 6: Experience Design (4/4) — PASS

**Button state coverage:**

| State | Class | CSS Rule | Status |
|-------|-------|----------|--------|
| Primary | `.tb-btn--primary` | `tokens.css:160` | PASS |
| Ghost | `.tb-btn--ghost` | `tokens.css:165` | PASS |
| Quiet | `.tb-btn--quiet` | `tokens.css:171` | PASS |
| Disabled | `.tb-btn--disabled`, `:disabled`, `[disabled]` | `tamabox.css:954-960` | PASS |
| Danger | `.tb-btn--danger` | `tamabox.css:964-981` | PASS |

**Hover/active/press feedback:**

`.tb-btn:active { transform: scale(0.985) }` — matches spec line 490.
`.tb-btn--primary:hover`, `.tb-btn--ghost:hover`, `.tb-btn--quiet:hover`, `.tb-btn--danger:hover` all implemented in tamabox.css Phase 5 section.

**Focus rings:**

`:focus-visible` on `.tb-btn`: `outline: 2px solid var(--tb-turq-400); outline-offset: 2px` — matches spec line 276.
`:focus-visible` on `.tb-icon-btn`: same pattern at `tamabox.css:1016`. PASS.

**Input state coverage:**

Default, focus (`:focus`), over-limit (`.is-over-limit` and `--over-limit` dual selector), disabled (`:disabled` / `[disabled]`), placeholder (`::placeholder: --tb-ink-4`) — all 4+1 states present. PASS.

**Accessibility — `.tb-icon-btn`:**

Runtime enforcement of `aria-label` is not possible in CSS. The requirement is documented in:
- UI-SPEC line 381: explicit requirement
- `tamabox.css` line 1020-1021: CSS comment reminder at the rule
- `icon.php` docblock lines 15-17: author instruction to parent `<button>` 

This is the correct approach for a CSS foundation phase. PHP templates (Phase 6+) are responsible for runtime compliance. No deduction.

**`shadcn_initialized: false` verified:** No `components.json` found. Registry safety audit skipped per protocol — no third-party registries declared in UI-SPEC.

---

## Registry Safety

No shadcn initialization detected (`components.json` absent). No external component registry used. All CSS is hand-authored from `handoff_tamabox` design package. Registry audit not applicable.

Registry audit: 0 third-party blocks checked — not applicable.

---

## Files Audited

| File | Lines | Role |
|------|-------|------|
| `/home/claude/projects/tamabox/.planning/phases/05-design-system-foundation/05-UI-SPEC.md` | 630 | Primary audit contract |
| `/home/claude/projects/tamabox/.planning/phases/05-design-system-foundation/05-CONTEXT.md` | 124 | Locked decisions (typography override, spacing exceptions) |
| `/home/claude/projects/tamabox/webroot/css/tokens.css` | 268 | Primary token layer |
| `/home/claude/projects/tamabox/webroot/css/colors_and_type.css` | 77 | Semantic alias layer |
| `/home/claude/projects/tamabox/webroot/css/tamabox.css` | 1144 | Legacy aliases + Phase 5 component CSS |
| `/home/claude/projects/tamabox/templates/layout/default.php` | 49 | Font + CSS load chain |
| `/home/claude/projects/tamabox/templates/element/icon.php` | 39 | Inline SVG helper |
| `/home/claude/projects/tamabox/webroot/img/icons/` (13 files) | — | Static SVG icon set |
