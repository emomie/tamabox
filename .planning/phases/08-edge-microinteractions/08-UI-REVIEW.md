---
phase: 8
slug: edge-microinteractions
score: 27/30
verdict: PASS
created: 2026-05-13
baseline: 08-UI-SPEC.md (approved) + handoff_tamabox/screens/SendErrors.jsx (4 variants) + RevealHit.jsx 172-300 + components.jsx (hi-fi)
screenshots: not captured (no dev server)
---

# Phase 8 — UI Review: エッジケース & マイクロインタラクション

**Audited:** 2026-05-13
**Baseline:** `08-UI-SPEC.md` + `~/projects/handoff_tamabox/screens/SendErrors.jsx` (416 lines, 4 variants) + `RevealHit.jsx` 172-300 (BlockConfirmModal) + `components.jsx` (TbButton / TbCard / TbChip / TbAppBar baseline)
**Screenshots:** Not captured — no dev server detected at localhost:3000 / :5173 / :8080. Code-only audit comparing PHP templates + JS + Phase 8 §I in `tamabox.css` (lines 2415-2871) against hi-fi JSX reference and Phase 5-7 locked decisions.

This is the **v2 milestone closer**. Findings flagged for milestone-close readiness are called out explicitly at the bottom.

---

## Pillar Scores

| # | Pillar | Score | Verdict | Key Finding |
|---|--------|-------|---------|-------------|
| 1 | Hi-fi fidelity | 5/5 | PASS | All 4 send-error surfaces + Block modal reproduce hi-fi DOM/layout/color tone; documented v3 deferrals only (下書き保存 + net_err debug strip on SendFailed; HIT text-highlight overlay on SendOverLimit) |
| 2 | Token discipline | 4/5 | PASS | All semantic colors via `--tb-*`; 3 documented single-use literals (`#F0DCA8`, `#FFFBEF`, `#EFD5D2`) match CONTEXT D-locked list; `rgba(20,28,32,0.42)` backdrop + `rgba(217,162,60,0.10)` corner-✦ are fresh literals carried over from spec §I.6/§I.7 but not in the documented exception table |
| 3 | Component reuse | 4/5 | FLAG | TbAppBar / TbCard / TbChip / icon element all consumed correctly; **bespoke `.tb-send-failed__retry`, `.tb-send__closed-cta`, `.tb-block-modal__confirm`, `.tb-block-modal__cancel` bypass `.tb-btn`** — they skip the MOTION-01 :active scale and the focus-visible inherit, by design but worth noting |
| 4 | Typography scale | 5/5 | PASS | All 10 §I font-sizes land on the locked scale (10/11/12/13/14/15/16/18) plus the 32px / 130px display-character precedents; 3 weights (300/600/700) — 300 is display-character only (Phase 6 precedent), 400 implicit, no new half-pixels introduced |
| 5 | Spacing scale | 4/5 | FLAG | Multiple-of-4 dominant; 8 off-grid values either inherit Phase 5-7 locked exceptions or land in the documented Phase 8 carry-over set (`14`/`18`/`6`/`10`/`2`/`3`); 2 fresh sub-grid values `gap: 9px` (block-modal list) and `padding: 22px` (modal sheet) are hi-fi-pinned but not in the locked table |
| 6 | Accessibility hooks | 4/5 | FLAG | `aria-label` on every icon-btn; `aria-hidden` on all decorative spans; `role="alert"` / `role="status"` / `role="document"` correct; `<dialog>` has `aria-labelledby`; reduced-motion guards on §I.1 + §I.6. **Two real gaps:** (a) `send_failed.php` banner title uses `<div>` not a heading — `role="alert"` already announces but the visual h2/h3 hierarchy is missing; (b) Block modal has no `aria-describedby` linking the 3-item consequence list |

**Overall: 26/30 — PASS**

---

## Top 3 Priority Recommendations (Advisory — none block v2 close)

1. **Block confirm modal lacks `aria-describedby` pointing at the consequence list** — `templates/element/tb_block_modal.php:37` declares `aria-labelledby="{modalId}-title"` on the `<dialog>` (good — points at "X さんをブロック"). But the screen-reader user gets the title and then the focus lands on the first form button without context about *what* blocking does. The 3-item `<ul class="tb-block-modal__list">` (lines 49-62) carries the actual consequence detail. Adding `id="{modalId}-desc"` to the `<ul>` and `aria-describedby="{modalId}-desc"` on the `<dialog>` would surface the consequences to AT automatically. 2-line fix; real WCAG 1.3.1 / 4.1.2 win. Phase 8 is the right home — same surface as the v2 closer.

2. **SendFailed banner title is a `<div>` not a heading** — `templates/Messages/send_failed.php:37` renders `<div class="tb-send-failed__title">送信できませんでした</div>`. The page has zero `<h1>`/`<h2>` elements; the document outline is `<header><span title>` + a roleless banner. The `role="alert"` on the banner div (line 34) compensates for the announce, but screen-reader users using heading navigation get no landmark. EDGE-01 (`error400.php:63`) and EDGE-02 (`send.php:85`) both render proper `<h2>`/`<h3>` for the same conceptual content. Lift the SendFailed title to `<h2 class="tb-send-failed__title">` for parity. Same applies if a future audit wants strict h1-per-page — none of the 5 Phase 8 surfaces has an h1 (all use h2/h3 for the primary heading), matching the existing v2 pattern.

3. **Bespoke buttons skip `.tb-btn` and forgo MOTION-01 :active scale** — `.tb-send-failed__retry` (line 2639), `.tb-send__closed-cta` (line 2559), `.tb-block-modal__confirm` (line 2770), `.tb-block-modal__cancel` (line 2781) are all `<button>`/`<a>` elements styled directly rather than as `.tb-btn` variants. Net effect: the MOTION-01 universal press-scale (§I.1) does not fire on the modal's confirm/cancel buttons or the SendFailed retry pill. The hi-fi reference uses `<TbButton variant="primary" full>` for SendFailed's "もう一度送信" (which is correctly mapped to `.tb-btn.tb-btn--primary.tb-btn--full` in our impl, line 62 of send_failed.php), but the inline retry pill in the banner and the modal action buttons are inline JSX `<button style={...}>` even in the hi-fi — so this matches hi-fi intent. **Suggestion (not a defect):** add a focus-visible outline rule to each bespoke button so keyboard-only users see focus — currently `.tb-block-modal__confirm:focus-visible` and friends inherit only the UA default ring. One block per bespoke; ~6 lines total.

---

## Detailed Findings

### Pillar 1: Hi-fi fidelity (5/5) — PASS

Each Phase 8 surface was read side-by-side with its hi-fi `.jsx` reference. Fidelity follows the Phase 5-7 D-22 standard: layout / spacing / typography / color tone match — not pixel-perfect.

**EDGE-01 SendNotFound** (`templates/Error/error400.php`, 76 lines) — matches `SendErrors.jsx` lines 9-64:
- TbAppBar with back icon + "メッセージを送る" title ✓
- 76×76 dashed-border circle with `?` glyph (32px / 300 weight / ink-3 color) — exact hi-fi geometry ✓
- 18px / 700 / 0.04em heading "この箱は見つかりません" ✓
- URL pill (`.tb-error-screen__url-pill`) with mono font, 8/14 padding, pill radius, card-soft background ✓
- 12px body text with `<br>` line break, 1.85 line-height, 280px max-width ✓
- Primary CTA "tamabox に戻る" + quiet link "URL を確認しなおす" ✓

**One spec-acknowledged deviation:** hi-fi line 47 uses `fontSize: 12.5` for body text and `12.5` for the quiet link; impl uses `12` per CONTEXT D-22 half-pixel rounding policy. The visual delta is sub-typography-cap and matches Phase 5-7 precedent.

**EDGE-02 SendInboxClosed** (`templates/Messages/send.php` else-branch, lines 79-97 + 146-150) — matches `SendErrors.jsx` lines 71-161:
- Warm honey status block with #F0DCA8 border (re-used Phase 7 §H.5 edge tone) ✓
- 10px / 700 / 0.2em uppercase "inbox · paused" kicker with 6×6 warm-500 dot ✓
- 16px / 700 / 0.04em heading "この箱はいま受信を停止しています" ✓
- 12px / 1.8 / 0.02em body copy with `<br>` line break ✓
- Disabled textarea stand-in (120px min-height, paper-deep bg, ink-4 text, center-aligned "受信停止中のため、入力できません") ✓
- Disabled CTA button "送信できません" with 52px height, paper-deep / ink-4 styling ✓

**Hi-fi extra not in impl** (no deduction — spec-acknowledged): hi-fi line 99 wraps the receiver card with `opacity: 0.7` and inserts a quiet "停止中" chip on the receiver card. The impl preserves the standard `.tb-send__receiver` card without opacity dimming because Phase 6 D-15 locked the receiver-card visual baseline and Phase 8 spec did not require this override.

**EDGE-03 SendOverflow** (`templates/Messages/send.php` markup + `webroot/js/send-counter.js`) — matches `SendErrors.jsx` lines 176-298:
- 10px / 600 / 0.1em uppercase "長すぎます" chip with warm-700 text + warm-100 bg + pill radius ✓
- `data-send-overflow-chip` + `aria-live="polite"` for AT announcement ✓
- Counter color toggles to warm-700 via `.tb-send__counter--over` ✓
- Textarea border-color warm-500 + bg #FFFBEF when `.is-overflow` ✓
- Submit button `disabled` while over ✓
- Idempotent JS with `'use strict'` and progressive enhancement ✓

**Hi-fi NOT implemented** (documented v3 — spec §"Out of scope vs hi-fi"): the linear-gradient text highlight overlay on the overflowing range (SendErrors.jsx lines 234-238) and the fake caret marker (lines 240-244). These require contenteditable / clone-overlay tricks that the spec explicitly punted. The implemented visual (border + bg tint + chip + counter color + disabled submit) carries 90% of the semantic signal.

**EDGE-04 SendFailed** (`templates/Messages/send_failed.php`, 67 lines) — matches `SendErrors.jsx` lines 305-414:
- TbAppBar with back icon + "メッセージを送る" title ✓
- Danger-tone banner with 24px circle "!" glyph + 13/700 title + 11/1.6 sub + retry pill ✓
- Banner has `role="alert"` for AT priority announce ✓
- Receiver card (re-uses `.tb-send__receiver` from Phase 6 — pure reuse, no copy-paste) ✓
- Preserved body (read-only `.tb-input` div with `nl2br(h(...))` and `aria-label="入力された本文"`) ✓
- Primary CTA "もう一度送信" with trailing send icon ✓

**Hi-fi v3 deferrals** (spec-acknowledged): "下書き保存" secondary button, `net_err · 09:41:08` debug strip with auto-retry counter, "下書きは端末に保存されています" sub-line under the body. All three require localStorage + error-code surfacing that v2 does not have.

**EDGE-05 Block confirm modal** (`templates/element/tb_block_modal.php`, 77 lines + `webroot/js/block-modal.js`) — matches `RevealHit.jsx` lines 172-300:
- Native `<dialog>` with `aria-labelledby` and a `tb-block-modal__sheet` inner role="document" ✓
- 36×4 handle pill (`.tb-block-modal__handle`) at top — matches hi-fi bottom-sheet affordance ✓
- 40×40 avatar with #E7C795→#B98449 honey gradient (re-used Phase 7 §H.7 RevealHit sender avatar gradient) ✓
- 14px/700 name + 11px/mono handle ✓
- 3-item consequence list with 5×5 ink-3 dots + 12px / 1.55 / ink-2 body ✓
- 11px / 1.7 / ink-3 hint with `<b>` for "設定 → ブロック中" ✓
- 50px danger confirm + 46px transparent cancel buttons (column layout) ✓
- Form helper preserves CSRF token; POST URL `/block/{senderUserId}` unchanged from v1 ✓

**MOTION-01 .tb-btn :active scale** (`tamabox.css:2424-2429`) — single 6-line declaration:
- `.tb-btn:active { transform: scale(0.985); }`
- `@media (prefers-reduced-motion: reduce) { .tb-btn:active { transform: none; } }`
- The base `.tb-btn` rule in tokens.css already sets `transition: transform 0.08s ease`, so the 80ms feel comes for free.
- Applies to all 5 .tb-btn variants (primary, ghost, quiet, danger, full). NOT applied to `.tb-icon-btn` per spec §I.1.

All 5 Phase 8 surfaces reproduce hi-fi within the Phase 5-7 D-22 tolerance. No deductions.

---

### Pillar 2: Token discipline (4/5) — PASS

**§I references 19 unique `--tb-*` properties:**

`--tb-card-soft`, `--tb-danger`, `--tb-danger-bg`, `--tb-ink`, `--tb-ink-2`, `--tb-ink-3`, `--tb-ink-4`, `--tb-line`, `--tb-line-strong`, `--tb-paper`, `--tb-paper-deep`, `--tb-r-lg`, `--tb-r-md`, `--tb-r-xl`, `--tb-shadow-3`, `--tb-turq-700`, `--tb-warm-100`, `--tb-warm-500`, `--tb-warm-700`.

**Raw color / gradient literals in §I:**

| Value | Location | Status |
|-------|----------|--------|
| `#F0DCA8` | `.tb-send__closed-status` border (line 2511) | CONTEXT 08 locked single-use literal (also reused from Phase 7 §H.5 hit card) |
| `#FFFBEF` | `.tb-input.is-overflow` background (line 2597) | CONTEXT 08 locked single-use literal |
| `#EFD5D2` | `.tb-send-failed__banner` border (line 2607) | CONTEXT 08 locked single-use literal |
| `#fff` | 5 occurrences (banner symbol, retry text, modal sheet bg, modal avatar text, modal confirm text) | Phase 5 allowed |
| `#E7C795`, `#B98449` | `.tb-block-modal__avatar` gradient (line 2712) | Phase 7 §H.7 documented (RevealHit sender avatar gradient — reused) |
| `rgba(20, 28, 32, 0.42)` | `.tb-block-modal::backdrop` (line 2680) | **NEW** — not in CONTEXT 08 locked list. Hi-fi-pinned (RevealHit.jsx backdrop value) |
| `rgba(217, 162, 60, 0.10)` | `.tb-reveal-hit-card__corner-glyph` color (line 2808) | **carried over from dashboard.php inline (Phase 7 audit Recommendation §2)** — now in §I.7 as a CSS class, but the literal is still undocumented in CONTEXT 08 locked list |

**Net assessment:** Phase 8 documents the 3 single-use hex literals (`#F0DCA8` / `#FFFBEF` / `#EFD5D2`) in CONTEXT D-locked section and comments them at point-of-use ✓. The 2 fresh rgba literals (`rgba(20,28,32,0.42)` backdrop + `rgba(217,162,60,0.10)` corner-✦) are hi-fi-pinned but were not added to the CONTEXT 08 locked-literal table — both are single-use, both are decorative-only (one is a modal backdrop wash, one is a 10%-alpha decorative glyph). Either (a) add a 2-line entry to CONTEXT 08 documenting them, or (b) accept them as inherited from the Phase 7 audit Recommendation §2 follow-up (the corner-✦ literal was specifically flagged in `07-UI-REVIEW.md` Pillar 2 and migrating it to §I.7 is the cleanup; documenting was the implicit task). Score 4/5 to flag the 2 undocumented literals.

The `--tb-r-xl: 20px` token (tokens.css:49) is consumed for the modal sheet radius — confirms no new token addition was needed. The `--tb-shadow-3` token similarly exists in tokens.css. Clean.

---

### Pillar 3: Component reuse (4/5) — FLAG

**Phase 5/6/7 components consumed in Phase 8:**

| Surface | Consumed components |
|---------|---------------------|
| `error400.php` | `.tb-appbar` / `.tb-appbar__left` / `.tb-appbar__title` / `.tb-icon-btn` (back) / `icon` element / `.tb-mono` / `.tb-btn.tb-btn--primary.tb-btn--full` |
| `send.php` (closed branch) | `.tb-appbar` family / `.tb-screen` / `.tb-screen__body` / `.tb-screen__cta` / `.tb-card.tb-send__receiver` / `.tb-chip.tb-chip--warm` (SSR%) / `.tb-mono` / `.tb-label` / `.tb-input` |
| `send_failed.php` | `.tb-appbar` family / `.tb-screen` / `.tb-card.tb-send__receiver` (Phase 6 element pattern reused) / `.tb-chip.tb-chip--warm` / `.tb-mono` / `.tb-label` / `.tb-input` / `.tb-btn.tb-btn--primary.tb-btn--full` / `icon` element (back, send) |
| `tb_block_modal.php` | `.tb-mono` / `Form->create()` (CSRF) / `--tb-r-xl` token / `--tb-shadow-3` token |
| `dashboard.php` (modified) | All Phase 7 §H components + new `.tb-flex-grow` / `.tb-flex-row--*` / `.tb-reveal-hit-card__*` / `.tb-reveal-miss-card__*` / `.tb-sender-card__name-ssr` helper classes (§I.7) |

**New Phase 8 helper classes added (§I.7):**

- `.tb-flex-grow`, `.tb-flex-row`, `.tb-flex-row--sm-gap`, `.tb-flex-row--baseline` — 4 layout utilities consumed by both `dashboard.php` (replacing inline styles) and `send_failed.php` / `tb_block_modal.php` (for the banner+heading flex containers). Each clears the 2+ call-site threshold.

- `.tb-reveal-hit-card__corner-glyph`, `.tb-reveal-hit-card__content`, `.tb-reveal-hit-card__kicker`, `.tb-reveal-hit-card__title`, `.tb-reveal-hit-card__sub`, `.tb-reveal-hit-card__sub-pct`, `.tb-reveal-miss-card__kicker`, `.tb-reveal-miss-card__title`, `.tb-reveal-miss-card__sub`, `.tb-reveal-miss-card__sub-pct`, `.tb-sender-card__name-ssr` — 11 dashboard-specific classes that absorb the Phase 7 inline styles. These are single-call-site but the Phase 7 audit Recommendation §3 explicitly authorized the extraction.

**Flag — 4 bespoke buttons bypass `.tb-btn`:**

1. `.tb-send-failed__retry` (line 2639) — the inline retry pill in the SendFailed banner. Hi-fi uses `<button style={...}>` inline (SendErrors.jsx:337), so the bespoke class matches hi-fi intent. Effect: no MOTION-01 :active scale, no `.tb-btn:focus-visible` outline.

2. `.tb-send__closed-cta` (line 2559) — the disabled "送信できません" CTA on EDGE-02. Hi-fi uses `<button disabled style={...}>` inline (SendErrors.jsx:152). Effect: same.

3. `.tb-block-modal__confirm` (line 2770) — the danger "ブロックする" button. Hi-fi RevealHit.jsx uses an inline `<button>` here. Effect: no MOTION-01.

4. `.tb-block-modal__cancel` (line 2781) — the "キャンセル" button. Same pattern.

This is hi-fi-faithful behavior — the hi-fi React source uses inline styled buttons here, not `<TbButton>`. But Phase 8 ships MOTION-01 as a *universal* `.tb-btn` effect, and these 4 bespoke buttons are visually "buttons" from the user's perspective. There's no contract violation (spec §I.1 explicitly says "promotes the existing primary-only effect to all five `.tb-btn` variants" — not "all interactive elements"), but the user-perceived MOTION-01 coverage is partial. Score 4/5 to flag this. The Phase 6 audit had a similar flag (`.tb-pill-btn` / `.tb-preset` not folding into `.tb-btn--ghost`); this is the same family.

---

### Pillar 4: Typography scale (5/5) — PASS

**Locked scale (Phase 5):** 8 sizes (10 / 11 / 12 / 14 / 15 / 16 / 18 / 22 px), 4 weights (400 / 500 / 600 / 700). Phase 6 added the 30px Home title exception. Phase 7 added 17px (display character on avatar) + 36px (display) + 130px (display ghost-✦) as display-character precedents.

**Sizes used in §I (`tamabox.css:2415-2871`):**

| Size | Approved? | Locations |
|------|-----------|-----------|
| 10px | yes | `.tb-error-screen__quiet-link` (12 — actually quiet-link is 12px) — recount: `.tb-send__closed-status__kicker`, `.tb-send__overflow-chip`, `.tb-reveal-hit-card__kicker`, `.tb-reveal-miss-card__kicker`, `.tb-sender-card__name-ssr` |
| 11px | yes | `.tb-send-failed__sub`, `.tb-send-failed__retry`, `.tb-block-modal__handle-text`, `.tb-block-modal__hint`, `.tb-reveal-miss-card__sub` |
| 12px | yes | `.tb-error-screen__url-pill`, `.tb-error-screen__body-text`, `.tb-error-screen__quiet-link`, `.tb-send__closed-status__body`, `.tb-block-modal__list-item`, `.tb-reveal-hit-card__sub` |
| 13px | yes (Phase 6 half-step accepted) | `.tb-send-failed__title`, `.tb-block-modal__cancel` |
| 14px | yes | `.tb-send__closed-input`, `.tb-send-failed__symbol`, `.tb-send-failed__body-preserved`, `.tb-block-modal__name`, `.tb-block-modal__confirm` |
| 15px | yes | `.tb-send__closed-cta`, `.tb-block-modal__avatar` |
| 16px | yes | `.tb-send__closed-status__title`, `.tb-reveal-hit-card__title` |
| 18px | yes | `.tb-error-screen__title` |
| 32px | display | `.tb-error-screen__symbol` glyph (`?`) — Phase 5-7 display-character precedent (32 < 36) |
| 130px | display | `.tb-reveal-hit-card__corner-glyph` — Phase 7 audit documented this in dashboard.php inline, now lifted to §I.7 as-is |

**Weights used in §I:** 300, 600, 700. (No 400/500 in §I directly — those come from inherited `.tb-btn` etc.)

- `font-weight: 300` on `.tb-error-screen__symbol` (line 2457) and `.tb-reveal-hit-card__corner-glyph` (line 2809). Both are decorative display-characters — matches the Phase 6 precedent for `.tb-home__symbol` (the ✦ glyph at 64px / 300). Phase 6 audit accepted this; Phase 7 audit reinforced it.

**Half-pixel hi-fi values — all rounded per D-22/D-23:**

- hi-fi `12.5px` body copy on SendErrors (lines 47, 121, 333) → impl `12px` ✓
- hi-fi `11.5px` sub-text (lines 206, 333) → impl `11px` ✓
- hi-fi `10.5px` chip (line 219) → impl `10px` ✓
- hi-fi `14.5px` modal title (RevealHit) → impl `14px` ✓
- hi-fi `13.5px` CTA copy (line 407) → impl `13px` (`.tb-block-modal__cancel`) ✓
- hi-fi `1.5px` warm underline → not implemented (v3 deferred per spec §"hi-fi NOT implemented")

No new half-pixel sites introduced. Every size lands on the locked scale or a documented display-character precedent. Score 5/5.

---

### Pillar 5: Spacing scale (4/5) — FLAG

**Approved scale (Phase 5):** multiples of 4 — `4 / 8 / 12 / 16 / 20 / 24 / 28 / 32 / 40 / ...`. Locked exceptions: `.tb-chip gap: 6px`, `.tb-input padding: 14px`, `.tb-card padding: 18px`. Phase 6/7 audit-supplemented hi-fi-pinned: `gap: 10`, `gap: 18`, `margin-top: 14`, `margin-top: 2`, `margin-top: 3` (3 selectors only — Phase 8 D-24 locked). Phase 8 CONTEXT 08 confirms the 3 `margin-top: 3px` selectors are: `.tb-dash-box__url` (§H.3), `.tb-reveal-hit-card__title` (§I.7), `.tb-reveal-miss-card__title` (§I.7).

**Multiples-of-4 used in §I (compliant):** `4`, `8`, `12`, `16`, `20`, `24`, `28`, `32`, `40`, `44`, `46`*, `48`, `50`*, `52`*, `56`, `76`, `120`, `160`, `240`, `280`. (* = 46/50/52 are hi-fi-pinned button heights that previously appeared in Phase 5-6 CTAs — Phase 5 `.tb-btn--full` is 52, etc.)

**Off-grid values in §I:**

| Value | Locations | Status |
|-------|-----------|--------|
| `gap: 6px` | `.tb-flex-row--sm-gap` (§I.7) | Phase 5 locked chip-gap exception carry-over |
| `gap: 8px` | `.tb-send__closed-status__kicker`, `.tb-block-modal__actions` | multiple of 4 — actually 8 IS compliant; on-scale |
| `gap: 9px` | `.tb-block-modal__list` (line 2739) | **NEW off-grid** — not in any locked exception. Hi-fi-pinned (RevealHit.jsx). 1px off the 8/10 family |
| `gap: 10px` | `.tb-error-screen__cta`, `.tb-flex-row`, `.tb-flex-row--baseline`, `.tb-block-modal__list-item` | Phase 6 advisory exception carry-over |
| `gap: 12px` | `.tb-block-modal__heading`, `.tb-send-failed__banner` | on-scale (12 = 4×3) |
| `padding: 2px 8px` | `.tb-send__overflow-chip` | 2 = sub-pixel-nudge family (Phase 6 documented) |
| `padding: 8px` | `.tb-error-screen__quiet-link` | on-scale |
| `padding: 8px 14px` | `.tb-error-screen__url-pill` | 14 = locked input-padding exception carry-over |
| `padding: 12px 14px` | `.tb-send-failed__banner` | 14 carry-over |
| `padding: 14px` | `.tb-block-modal__list`, `.tb-send-failed__body-preserved` | 14 carry-over |
| `padding: 18px 18px 20px` | `.tb-send__closed-status` | 18 = locked card-padding exception carry-over |
| `padding: 22px 22px 18px` | `.tb-block-modal__sheet` (line 2691) | **NEW off-grid** — 22px not in any locked exception. Hi-fi-pinned (RevealHit.jsx). 2px past the 20-scale |
| `margin-top: 1px` | `.tb-block-modal__handle-text` | sub-pixel-nudge family |
| `margin-top: 2px` | `.tb-send-failed__sub`, `.tb-reveal-*-card__sub` | sub-pixel-nudge family |
| `margin-top: 3px` | `.tb-reveal-hit-card__title`, `.tb-reveal-miss-card__title` | **Phase 8 D-24 locked exception** (3 selectors total, 2 here + 1 in §H.3) |
| `margin-top: 8px` | `.tb-block-modal__list-dot` | on-scale |
| `margin-top: 14px` | `.tb-error-screen__url-pill` | 14 carry-over |
| `margin-top: 18px` | `.tb-error-screen__body-text` | on-scale (18 = card-padding family) |
| `margin: -8px auto 14px` | `.tb-block-modal__handle` (negative top — pulls handle into sheet padding) | negative-margin technique; 8 on-scale, 14 carry-over |
| `margin: 12px 16px 0` | `.tb-send-failed__banner` | on-scale |
| `margin: 14px 2px 0` | `.tb-block-modal__hint` | 14 carry-over, 2 sub-pixel-nudge |
| `margin: 18px 0 0` | `.tb-error-screen__body-text` | 18 carry-over |

**Net assessment:** Phase 8 D-24 locks `margin-top: 3px` to exactly 3 selectors — verified by `grep margin-top:.*3px tamabox.css` → 3 matches (line 2091 `.tb-dash-box__url` §H.3, line 2829 `.tb-reveal-hit-card__title`, line 2853 `.tb-reveal-miss-card__title`). ✓ consistency verified.

**Two fresh sub-grid values that are NOT in any locked exception:**

1. `gap: 9px` on `.tb-block-modal__list` (line 2739) — between the 3 consequence list items. Hi-fi-pinned (RevealHit.jsx:266). 1px off the 8/10 family. Could absorb to `gap: 10` with no visible delta — recommend.

2. `padding: 22px 22px 18px` on `.tb-block-modal__sheet` (line 2691) — bottom-sheet inner padding. 22 = 4-grid + 2 (Phase 7 sub-pixel-nudge family precedent). Could absorb to `20 20 18` or `24 24 18` — visible delta tighter or looser, hi-fi nuance preserved by 22. Recommend documenting as Phase 8 sub-grid carry-over rather than rounding.

Score 4/5: spacing discipline is otherwise strong, the 22px sheet padding and 9px list gap are the only fresh deviations. Both are hi-fi-pinned and each appears in exactly 1 selector — the YAGNI threshold for documenting locked exceptions is not yet crossed.

---

### Pillar 6: Accessibility hooks (4/5) — FLAG

**`aria-label` on every icon-only button** ✓:
- `error400.php:53` — back link, `aria-label="戻る"`
- `send.php:36` — back button, `aria-label="戻る"` (inherited from Phase 6)
- `send_failed.php:26` — back link, `aria-label="戻る"`

**`aria-hidden="true"` on every decorative element** ✓:
- error400: `?` glyph (line 62)
- send_failed: `!` glyph (line 35), avatar initial (line 45)
- tb_block_modal: handle pill (line 39), avatar (line 42), 3 list-dots (lines 51/55/59)
- Phase 7 §I.7 inline-style cleanup preserves `aria-hidden` on `.tb-reveal-hit-card__corner-glyph` (verified in dashboard.php:149)

**ARIA roles correctly applied:**
- `send_failed.php:34` — `role="alert"` on the failure banner (urgent announce) ✓
- `send.php:80` — `role="status"` on the inbox-closed status block ✓
- `tb_block_modal.php:37` — `<dialog>` element provides modal-dialog semantics natively + `aria-labelledby` points at the title ✓
- `tb_block_modal.php:38` — `role="document"` on the inner sheet (proper for dialog containing scrollable content)

**Focus management on `<dialog>`:**
- `webroot/js/block-modal.js:27` — calls native `dlg.showModal()` which auto-focuses the first focusable element (the confirm `<button type="submit">`) ✓
- ESC closes the dialog (native `<dialog>` behavior) ✓
- Backdrop click does NOT close (intentional — `<dialog>::backdrop` is not interactive by default; matches hi-fi "deliberate confirmation" intent) ✓
- No explicit focus trap — but `<dialog>.showModal()` provides one natively ✓

**Reduced-motion guards:**
- §I.1 (line 2427) — `.tb-btn:active { transform: none; }` when reduced-motion ✓
- §I.6 (line 2792) — `.tb-block-modal__sheet { animation: none; }` (defensive — no animation defined, but future-proof) ✓
- Phase 7 §H reveal motion guard at line 2064 also still active ✓

**Two real a11y gaps (advisory — see Recommendations #1, #2):**

1. **Block modal lacks `aria-describedby`** — the `<dialog>` has `aria-labelledby="{modalId}-title"` (which announces "X さんをブロック"), but the 3 consequence list items below are not linked. AT users miss the impact summary. Fix: 2 lines.

2. **SendFailed banner uses `<div>` not `<h2>` for title** — `role="alert"` does fire the announce, but heading-based AT navigation has nothing to land on. EDGE-01 and EDGE-02 both have proper headings (h2 / h3); SendFailed is the outlier. Fix: 1 line.

**Latent issue noted from Phase 6 audit (not fixed in Phase 8, advisory carry-over):** Custom radio / checkbox tiles in `send.php` consent + `delete.php` consent + `report/create.php` tiles still hide the native input via `opacity: 0` — keyboard focus ring invisible. Not a Phase 8 surface, but the v2 milestone close may want a Phase 7 supplement entry to defer this to v3 explicitly.

Score 4/5: strong overall a11y posture (`role="alert"` correctly applied, reduced-motion guards on all new keyframes, `aria-hidden` everywhere needed, native `<dialog>` with `aria-labelledby` and CSRF-preserving form), but 2 small gaps that are real WCAG concerns and trivial to fix.

---

## Registry Safety

Not applicable. `components.json` does not exist in this project (PHP/CakePHP codebase, not shadcn). No third-party UI registries are in use. Phase 8 introduces no new icons (per spec §"Checker Sign-Off Dimension 6") — `back` and `send` icons are reused from the Phase 5 `templates/element/icon.php` central registry.

---

## v2 Milestone Close Readiness — Flags

This is the **v2 closer**. The following items affect milestone close:

### Green (no action required for v2 close)
- All 5 Phase 8 EDGE surfaces ship at PASS or FLAG (no FAIL)
- MOTION-01 active across all 5 `.tb-btn` variants
- Phase 7 deferred items (D-21..D-24) all resolved: 0 inline `style="..."` in dashboard.php verified ✓, `$blocks` removed from `dashboard()` `$this->set()` (spec-asserted, not re-verified here), `#FBFCFD` locked, `margin-top: 3px` locked to 3 selectors
- 3 single-use hex literals documented at point-of-use ✓
- No new `--tb-*` tokens introduced ✓
- No backend file touched outside the spec-allowed `MessagesController::processSend` catch ✓

### Yellow (advisory — recommend before v3, none block v2)
- **A11y gaps #1, #2 above** — the SendFailed heading + Block modal `aria-describedby`. Both are 1-2 line fixes; both are real WCAG wins. If v2 close is time-pressured, log as v2-supplement or first v3 issue.
- **2 undocumented rgba literals** — `rgba(20,28,32,0.42)` modal backdrop + `rgba(217,162,60,0.10)` corner-✦. Either add 2 lines to CONTEXT 08 locked-literal table, or accept as inherited from the Phase 7 audit recommendation. Cosmetic doc hygiene.
- **2 fresh sub-grid spacing values** — `gap: 9px` (modal list) + `padding: 22px 22px 18px` (modal sheet). Both hi-fi-pinned, both single-use. Document or accept.
- **4 bespoke buttons skip `.tb-btn`** — matches hi-fi intent (the React source uses inline buttons here too), but partial MOTION-01 coverage. Either accept (hi-fi parity > MOTION-01 universality) or expand `.tb-btn` to apply to these via additional classes.

### Red (blocks v2 close)
- **None.** Phase 8 is ship-ready.

---

## Files Audited

| File | Lines | Role |
|------|-------|------|
| `templates/Error/error400.php` | 76 | REWRITE — EDGE-01 SendNotFound |
| `templates/Messages/send.php` | 152 | MODIFY — EDGE-02 closed-branch + EDGE-03 chip/counter |
| `templates/Messages/send_failed.php` | 67 | NEW — EDGE-04 SendFailed |
| `templates/element/tb_block_modal.php` | 77 | NEW — EDGE-05 Block confirm modal element |
| `templates/Users/dashboard.php` | 244 | MODIFY — Phase 7 inline-style cleanup + Block modal trigger wiring |
| `templates/layout/default.php` | (verified line 20) | MODIFY — added `send-counter` + `block-modal` to defer script list |
| `webroot/js/send-counter.js` | 46 | NEW — EDGE-03 live overflow feedback |
| `webroot/js/block-modal.js` | 60 | NEW — EDGE-05 `<dialog>` open/close control |
| `webroot/css/tamabox.css` §I (lines 2415-2871) | 457 | Phase 8 §I CSS — MOTION-01 + 4 EDGE surfaces + cleanup helpers |
| `webroot/css/tokens.css` | (verified `--tb-r-xl` + `--tb-shadow-3`) | Phase 5 tokens (no Phase 8 additions) |
| `~/projects/handoff_tamabox/screens/SendErrors.jsx` | 416 | hi-fi EDGE-01..04 reference |
| `~/projects/handoff_tamabox/screens/RevealHit.jsx` (lines 172-300) | 128 | hi-fi EDGE-05 BlockConfirmModal reference |
| `~/projects/handoff_tamabox/components.jsx` | 146 | hi-fi TbButton / TbCard / TbChip / TbAppBar baseline |
| `.planning/phases/08-edge-microinteractions/08-UI-SPEC.md` | 1152 | Phase 8 design contract |
| `.planning/phases/08-edge-microinteractions/08-CONTEXT.md` | 222 | Phase 8 locked decisions (D-01..D-30 + 3 Phase 8 追記 locked decisions) |
| `.planning/phases/06-v1-calm-gacha/06-UI-REVIEW.md` | 290 | Phase 6 review (template precedent) |
| `.planning/phases/07-dashboard-4-reveal/07-UI-REVIEW.md` | 302 | Phase 7 review (template precedent) |

---

## Overall Summary

**26/30 — PASS.** Phase 8 successfully ships the v2 closer: 4 send-error surfaces (404 / closed / overflow / failed) + Block confirm bottom-sheet modal + universal MOTION-01 button press-feedback + Phase 7 deferred cleanup. Hi-fi fidelity holds across all 5 surfaces (5/5 — the 2 spec-acknowledged v3 deferrals on SendFailed and the linear-gradient text highlight on SendOverflow are documented in the spec). Token discipline is strong (4/5 — the 3 locked single-use hex literals are at point-of-use; the 2 fresh rgba literals are doc-hygiene gaps, not contract violations). Component reuse is good (4/5 — TbAppBar / TbCard / TbChip / icon / Form helper all consumed correctly; the 4 bespoke buttons that skip `.tb-btn` match hi-fi intent but forgo MOTION-01 coverage). Typography is exemplary (5/5 — every size lands on the locked scale, half-pixel rounding policy applied throughout, the 300-weight glyphs are display-character only). Spacing is strong (4/5 — 2 fresh sub-grid values `gap: 9px` + `padding: 22px` are hi-fi-pinned single-use; the locked `margin-top: 3px` exception verified at 3 selectors as CONTEXT D-24 specifies). Accessibility is solid (4/5 — `role="alert"` / `role="status"` / `<dialog>` + `aria-labelledby` / reduced-motion guards all correct; 2 small WCAG gaps on SendFailed heading semantics + Block modal `aria-describedby`).

**The Phase 7 deferred cleanup (D-21..D-24) is fully discharged in Phase 8:**
- `dashboard.php` has 0 inline `style="..."` attributes ✓
- `margin-top: 3px` locked to exactly 3 selectors per Phase 8 D-24 ✓
- `#FBFCFD` Phase 7 deferred color exception documented in CONTEXT 08 + commented in §I.7 ✓
- The 11 §I.7 reveal-card helper classes consume Phase 7 audit Recommendation §3

**v2 milestone close readiness:** Phase 8 ships. The 2 advisory a11y gaps (#1, #2 above) and the 2 doc-hygiene items (undocumented rgba literals, undocumented `gap: 9px` / `padding: 22px`) can be carried as v2-supplement or first v3 issue without blocking the milestone close. The 4 bespoke-button MOTION-01 partial coverage is intentional hi-fi parity and does not require action.

**This audit is advisory — Phase 8 ships, v2 closes.**
