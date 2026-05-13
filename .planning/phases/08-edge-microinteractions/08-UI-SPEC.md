---
phase: 8
slug: edge-microinteractions
status: draft
preset: none
created: 2026-05-13
---

# Phase 8 — UI Design Contract: エッジケース & マイクロインタラクション

> Visual and interaction contract for the v2 closer phase: 4 Send-flow error states (EDGE-01..04), a bottom-sheet Block confirm modal (EDGE-05), the global `.tb-btn:active` press-scale feedback (MOTION-01), and the Phase 7 deferred cleanup (inline-styles → CSS class refactor, controller view-data trim, locked-decision documentation).
> Sources of truth: `~/projects/handoff_tamabox/screens/SendErrors.jsx` (416 lines, 4 variants), `~/projects/handoff_tamabox/screens/RevealHit.jsx` lines 172-300 (BlockConfirmModal bottom sheet), `~/projects/handoff_tamabox/components.jsx` (TbButton / TbChip / TbAppBar baseline), Phase 5/6/7 CSS already in `tokens.css` + `tamabox.css` (§5 / §G / §H).
> Phase 8 creates **3 new templates** (`Error/error400.php` rewrite, `Messages/send_failed.php`, `element/tb_block_modal.php`), **2 new JS files** (`send-counter.js`, `block-modal.js`), **1 layout migration** (`layout/error.php` → Calm Gacha chain), **1 backend tweak** (`MessagesController::processSend` catch → render), and **1 §I CSS section** appended to `tamabox.css`.

---

## Scope

| # | Plan | Req | Target file(s) | Hi-fi reference |
|---|------|-----|----------------|-----------------|
| 1 | 08-01 | MOTION-01 + foundations | `webroot/css/tamabox.css` (§I) | SendErrors.jsx + RevealHit.jsx 172-300 |
| 2 | 08-02 | EDGE-01 | `templates/layout/error.php` + `templates/Error/error400.php` (rewrite) | SendErrors.jsx 9-64 (SendNotFound) |
| 3 | 08-03 | EDGE-02 | `templates/Messages/send.php` (else-branch rewrite) | SendErrors.jsx 71-161 (SendInboxClosed) |
| 4 | 08-04 | EDGE-03 | `templates/Messages/send.php` (chip + counter markup) + `webroot/js/send-counter.js` (new) | SendErrors.jsx 176-298 (SendOverLimit) |
| 5 | 08-05 | EDGE-04 | `src/Controller/MessagesController.php` (catch render) + `templates/Messages/send_failed.php` (new) + test | SendErrors.jsx 305-414 (SendFailed) |
| 6 | 08-06 | EDGE-05 | `templates/element/tb_block_modal.php` (new) + `webroot/js/block-modal.js` (new) + `templates/Users/dashboard.php` (trigger wiring) + test | RevealHit.jsx 172-300 (BlockConfirmModal) |
| 7 | 08-07 | Phase 7 cleanup | `templates/Users/dashboard.php` (inline-style scrub) + `src/Controller/UsersController.php` (`$inbox`/`$blocks` trim) | Phase 7 D-04 / D-21..D-24 |
| 8 | 08-08 | Verification | (no code) — `08-VERIFICATION.md` + v2 milestone close prep | — |

Execution order locked: foundational CSS lands first (08-01) so subsequent plans can reference helpers; then the two flagship error screens (08-02, 08-03); the counter + chip live state (08-04); the rendered failure path (08-05); the modal element (08-06); cleanup (08-07); verification last (08-08).

---

## Inherited Locked Decisions (from Phase 5-7)

- **Typography scale override** (Phase 5): 8 sizes (22 / 18 / 16 / 15 / 14 / 12 / 11 / 10 px) + 4 weights (400 / 500 / 600 / 700). Half-pixel hi-fi values (12.5, 11.5, 10.5, 14.5, 13.5) round to the nearest in-scale size per Phase 6 D-22/D-23.
- **Spacing exceptions**: `.tb-chip gap: 6px`, `.tb-input padding: 14px`, `.tb-card padding: 18px`.
- **Home `.tb-home__title` 30px** — does not propagate to Phase 8 selectors.
- **No emoji** in user-facing strings except `✦` (U+2726) and the locked `?` / `!` / `—` glyphs used for icon stand-ins.
- **Voice**: 静かな日本語 (no hype, no exclamation marks except the SendFailed `!` glyph which is the danger ring marker, not a sentence terminator).
- **Mono font** for numbers, handles, percentages via `.tb-mono` / `var(--tb-font-mono)`.
- **`prefers-reduced-motion: reduce`** opt-out for every new keyframe / transform (Phase 7 M-02 pattern).
- **#FBFCFD locked-color exception** (Phase 7 deferred → Phase 8 §I documentation): `.tb-message-row[data-state="unread"] .tb-message-row__head` row tint. See `08-CONTEXT.md` "Locked Decision — Phase 7 deferred color exception".

### Phase 8 candidate new exceptions

| Hi-fi value | Locked-scale resolution | Notes |
|-------------|-------------------------|-------|
| `12.5px` body copy on error screens | round to **12px** | SendErrors line 47 / 121 / 333 |
| `11.5px` handle / status sub | round to **12px** (or 11px when paired with bigger heading) | SendErrors 206 / 333 / RevealHit 242 |
| `10.5px` chip "長すぎます" | round to **10px** | SendErrors 219 |
| `14.5px` modal title | round to **14px** | RevealHit 241 |
| `13.5px` SendFailed CTA copy | round to **14px** | SendErrors 407 |
| `1.5px` warm underline on overflow text | KEEP as 1.5px border | thin border, no font-size impact |

No new color/background hex outside Phase 5-7 palette. The danger-tone `#EFD5D2` border on the SendFailed banner uses `var(--tb-danger-bg)` + a 1px adjacent border that we will introduce as `--tb-danger-line` token alias inline in §I (NOT a new token in `tokens.css` — scope-limited).

> *Update*: review of `tokens.css` shows `--tb-danger-bg` is already defined. The `#EFD5D2` border value is hi-fi-only and used in **one** place (the banner). To stay on the no-new-token policy, we ship the literal `#EFD5D2` in §I with a comment marking it as a derived edge tone of `--tb-danger-bg`. If a second consumer arises, promote to a token.

---

## Component 1 — Phase 8 §I CSS (MOTION-01 + foundations)

**File:** `webroot/css/tamabox.css` (append §I after §H.8)
**Hi-fi:** none directly — these are foundation helpers consumed by 08-02 / 03 / 04 / 05 / 06.

### §I.1 — `.tb-btn` press-scale animation (MOTION-01)

```css
/* §I.1 — MOTION-01: universal .tb-btn press feedback */
.tb-btn:active {
    transform: scale(0.985);
}
/* keep the 80ms feel — base .tb-btn already has transition: transform 0.08s ease */
@media (prefers-reduced-motion: reduce) {
    .tb-btn:active { transform: none; }
}
```

The base `.tb-btn` rule in `tokens.css` already sets `transition: transform 0.08s ease`. Adding the bare `:active { transform: scale(0.985); }` at §I.1 promotes the existing primary-only effect to all five variants (primary, ghost, quiet, disabled, danger). The pre-existing `.tb-btn--primary:active { transform: scale(0.985); background: var(--tb-turq-500); }` rule in `tokens.css` continues to fire (because the cascade allows both rules) — the `background` override remains primary-specific.

**Out of scope for §I.1:** `.tb-icon-btn` does NOT receive the press scale. The hi-fi icon buttons are tap-only and the 32px touch target makes a 1.5% scale visually negligible; revisit if hi-fi adds an icon-button micro-anim variant.

### §I.2 — `.tb-error-screen` layout helper (EDGE-01 / 02 / 04)

Common flex-column scaffolding for the three full-screen error templates.

```css
/* §I.2 — Error screen scaffold (EDGE-01, EDGE-02, EDGE-04) */
.tb-error-screen {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 56px);
    background: var(--tb-paper);
}
.tb-error-screen__body {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0 28px 40px;
    text-align: center;
}
.tb-error-screen__symbol {
    width: 76px;
    height: 76px;
    border-radius: 50%;
    background: var(--tb-card-soft);
    border: 1px dashed var(--tb-line-strong);
    display: grid;
    place-items: center;
    color: var(--tb-ink-3);
    font-size: 32px;
    font-weight: 300;
    line-height: 1;
    margin-bottom: 22px;
}
.tb-error-screen__title {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: var(--tb-ink);
    letter-spacing: 0.04em;
}
.tb-error-screen__url-pill {
    margin-top: 14px;
    padding: 8px 14px;
    background: var(--tb-card-soft);
    border: 1px solid var(--tb-line);
    border-radius: 999px;
    font-size: 12px;
    color: var(--tb-ink-2);
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tb-error-screen__body-text {
    margin: 18px 0 0;
    font-size: 12px;
    color: var(--tb-ink-2);
    line-height: 1.85;
    max-width: 280px;
}
.tb-error-screen__cta {
    margin-top: 28px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
    max-width: 280px;
}
.tb-error-screen__quiet-link {
    background: transparent;
    border: 0;
    color: var(--tb-turq-700);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.06em;
    cursor: pointer;
    padding: 8px;
    text-decoration: none;
}
```

### §I.3 — Closed-inbox status block (EDGE-02)

```css
/* §I.3 — Inbox-closed status block (EDGE-02) */
.tb-send__closed-status {
    background: var(--tb-warm-100);
    border: 1px solid #F0DCA8; /* edge tone of warm-200; same value used in §H.5 hit card */
    border-radius: var(--tb-r-lg);
    padding: 18px 18px 20px;
}
.tb-send__closed-status__kicker {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 10px;
    font-weight: 700;
    color: var(--tb-warm-700);
    letter-spacing: 0.2em;
    text-transform: uppercase;
    margin-bottom: 8px;
}
.tb-send__closed-status__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--tb-warm-500);
    flex: 0 0 auto;
}
.tb-send__closed-status__title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--tb-ink);
    letter-spacing: 0.04em;
}
.tb-send__closed-status__body {
    margin: 10px 0 0;
    font-size: 12px;
    color: var(--tb-ink-2);
    line-height: 1.8;
    letter-spacing: 0.02em;
}
.tb-send__closed-input {
    font-size: 14px;
    line-height: 1.8;
    min-height: 120px;
    color: var(--tb-ink-4);
    background: var(--tb-paper-deep);
    cursor: not-allowed;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}
.tb-send__closed-cta {
    width: 100%;
    height: 52px;
    border-radius: 999px;
    border: 0;
    background: var(--tb-paper-deep);
    color: var(--tb-ink-4);
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 0.04em;
    cursor: not-allowed;
}
```

### §I.4 — Overflow live state (EDGE-03)

```css
/* §I.4 — Send overflow live feedback (EDGE-03) */
.tb-send__counter--over {
    color: var(--tb-warm-700);
    font-weight: 600;
}
.tb-send__overflow-chip {
    display: none; /* JS toggles to inline-flex when over */
    align-items: center;
    font-size: 10px;
    font-weight: 600;
    color: var(--tb-warm-700);
    background: var(--tb-warm-100);
    padding: 2px 8px;
    border-radius: 999px;
    letter-spacing: 0.1em;
}
.tb-send__overflow-chip.is-visible { display: inline-flex; }
.tb-input.is-overflow {
    border-color: var(--tb-warm-500);
    background: #FFFBEF; /* derived from warm-100; single-use, kept inline per Phase 8 token policy */
}
.tb-send__overflow-helper {
    color: var(--tb-warm-700);
}
```

### §I.5 — SendFailed banner (EDGE-04)

```css
/* §I.5 — Send failed banner + retry CTA (EDGE-04) */
.tb-send-failed__banner {
    margin: 12px 16px 0;
    background: var(--tb-danger-bg);
    border: 1px solid #EFD5D2; /* edge tone of danger-bg; single-use literal */
    border-radius: var(--tb-r-md);
    padding: 12px 14px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.tb-send-failed__symbol {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--tb-danger);
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 14px;
    font-weight: 700;
    flex: 0 0 auto;
    line-height: 1;
}
.tb-send-failed__title {
    font-size: 13px;
    font-weight: 700;
    color: var(--tb-danger);
    letter-spacing: 0.04em;
}
.tb-send-failed__sub {
    font-size: 11px;
    color: var(--tb-ink-2);
    line-height: 1.6;
    margin-top: 2px;
}
.tb-send-failed__retry {
    height: 30px;
    padding: 0 12px;
    border-radius: 999px;
    background: var(--tb-danger);
    color: #fff;
    border: 0;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    cursor: pointer;
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
}
```

### §I.6 — Block confirm modal (EDGE-05)

```css
/* §I.6 — Block confirm modal (EDGE-05) — native <dialog> bottom sheet */
.tb-block-modal {
    border: 0;
    padding: 0;
    background: transparent;
    max-width: none;
    max-height: none;
    width: 100%;
    height: 100%;
    margin: 0;
    inset: 0;
    overflow: visible;
}
.tb-block-modal::backdrop {
    background: rgba(20, 28, 32, 0.42);
    backdrop-filter: blur(2px);
}
.tb-block-modal__sheet {
    position: absolute;
    left: 16px;
    right: 16px;
    bottom: 24px;
    background: #fff;
    border-radius: var(--tb-r-xl);
    box-shadow: var(--tb-shadow-3);
    padding: 22px 22px 18px;
    display: flex;
    flex-direction: column;
}
.tb-block-modal__handle {
    width: 36px;
    height: 4px;
    border-radius: 999px;
    background: var(--tb-line);
    margin: -8px auto 14px;
}
.tb-block-modal__heading {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}
.tb-block-modal__avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #E7C795, #B98449);
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: 15px;
    flex: 0 0 auto;
}
.tb-block-modal__name {
    font-size: 14px;
    font-weight: 700;
    color: var(--tb-ink);
}
.tb-block-modal__handle-text {
    font-size: 11px;
    color: var(--tb-ink-3);
    margin-top: 1px;
}
.tb-block-modal__list {
    margin: 0;
    padding: 14px;
    list-style: none;
    background: var(--tb-card-soft);
    border: 1px solid var(--tb-line);
    border-radius: var(--tb-r-md);
    display: flex;
    flex-direction: column;
    gap: 9px;
}
.tb-block-modal__list-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 12px;
    color: var(--tb-ink-2);
    line-height: 1.55;
}
.tb-block-modal__list-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--tb-ink-3);
    flex: 0 0 auto;
    margin-top: 8px;
}
.tb-block-modal__hint {
    margin: 14px 2px 0;
    font-size: 11px;
    color: var(--tb-ink-3);
    line-height: 1.7;
    letter-spacing: 0.02em;
}
.tb-block-modal__actions {
    margin-top: 18px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.tb-block-modal__confirm {
    height: 50px;
    border-radius: 999px;
    border: 0;
    background: var(--tb-danger);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.06em;
    cursor: pointer;
}
.tb-block-modal__cancel {
    height: 46px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    color: var(--tb-ink-2);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.04em;
    cursor: pointer;
}
@media (prefers-reduced-motion: reduce) {
    .tb-block-modal__sheet { animation: none; }
}
```

### §I.7 — Phase 7 inline-style replacement helpers (cleanup)

```css
/* §I.7 — Phase 7 deferred inline-style → CSS class refactor */
.tb-flex-grow { flex: 1; min-width: 0; }
.tb-flex-row { display: flex; align-items: center; gap: 10px; }
.tb-flex-row--sm-gap { display: flex; align-items: center; gap: 6px; }
.tb-flex-row--baseline { display: flex; align-items: baseline; gap: 10px; }
.tb-reveal-hit-card__corner-glyph {
    position: absolute;
    right: -14px;
    top: -22px;
    font-size: 130px;
    line-height: 1;
    color: rgba(217, 162, 60, 0.10);
    font-weight: 300;
    pointer-events: none;
}
.tb-reveal-hit-card__content {
    flex: 1;
    min-width: 0;
    position: relative;
    z-index: 1;
}
.tb-reveal-hit-card__kicker {
    font-size: 10px;
    font-weight: 700;
    color: var(--tb-warm-700);
    letter-spacing: 0.2em;
    text-transform: uppercase;
}
.tb-reveal-hit-card__title {
    font-size: 16px;
    font-weight: 700;
    color: var(--tb-ink);
    margin-top: 3px;
    letter-spacing: 0.04em;
}
.tb-reveal-hit-card__sub {
    font-size: 12px;
    color: var(--tb-ink-2);
    margin-top: 2px;
    letter-spacing: 0.04em;
}
.tb-reveal-hit-card__sub-pct {
    color: var(--tb-warm-700);
    font-weight: 700;
}
.tb-reveal-miss-card__kicker {
    font-size: 10px;
    font-weight: 600;
    color: var(--tb-ink-3);
    letter-spacing: 0.2em;
    text-transform: uppercase;
}
.tb-reveal-miss-card__title {
    font-size: 15px;
    font-weight: 600;
    color: var(--tb-ink);
    margin-top: 3px;
    letter-spacing: 0.04em;
}
.tb-reveal-miss-card__sub {
    font-size: 11px;
    color: var(--tb-ink-3);
    margin-top: 2px;
    letter-spacing: 0.04em;
}
.tb-reveal-miss-card__sub-pct {
    color: var(--tb-warm-700);
    font-weight: 600;
}
.tb-sender-card__name-ssr {
    font-size: 10px;
    color: var(--tb-warm-500);
    font-weight: 700;
    letter-spacing: 0.18em;
}
```

---

## Component 2 — `templates/Error/error400.php` (EDGE-01)

**File:** `templates/Error/error400.php` (rewrite — 43 lines → ~50 lines)
**Hi-fi:** `~/projects/handoff_tamabox/screens/SendErrors.jsx` lines 9-64 (SendNotFound)
**Layout chain:** `templates/layout/error.php` must first migrate to Calm Gacha CSS (tokens.css + tamabox.css) so the rewrite renders correctly. Plan 08-02 covers both files.

### Markup contract

```html
<div class="tb-error-screen">
    <header class="tb-appbar">
        <div class="tb-appbar__left">
            <a href="/" class="tb-icon-btn" aria-label="戻る">
                <?= icon back 22 ?>
            </a>
            <span class="tb-appbar__title">メッセージを送る</span>
        </div>
        <div></div>
    </header>

    <div class="tb-error-screen__body">
        <div class="tb-error-screen__symbol" aria-hidden="true">?</div>
        <h2 class="tb-error-screen__title">この箱は見つかりません</h2>
        <div class="tb-error-screen__url-pill tb-mono"><?= h($url) ?></div>
        <p class="tb-error-screen__body-text">
            URL の入力ミス、または箱が<br>削除された可能性があります。
        </p>
        <div class="tb-error-screen__cta">
            <a href="/" class="tb-btn tb-btn--primary tb-btn--full">tamabox に戻る</a>
            <a href="javascript:history.back()" class="tb-error-screen__quiet-link">URL を確認しなおす</a>
        </div>
    </div>
</div>
```

`$url` and `$message` are provided by CakePHP's ErrorController. The template keeps both as fall-backs; `$message` is shown only in `debug=true` via the parent dev_error layout (preserved from the existing template).

### Why not a custom ExceptionRenderer?

CONTEXT D-01 picked the **single-pattern simplification**: every CakePHP 404 (and every other 400-class error) renders the SendNotFound hi-fi. This matches the codebase's reality — the only realistic user-facing 404 source is `MessagesController::send` throwing `NotFoundException` for unknown slugs. Adding an ExceptionRenderer would carve out a narrow inbox-slug-only special case, which CONTEXT D-03 explicitly rejected.

### `layout/error.php` migration

The existing `templates/layout/error.php` loads `cake.css` + `fonts.css` (legacy Milligram theming). Migrate to:

```html
<?= $this->Html->css(['normalize.min', 'milligram.min', 'tokens', 'colors_and_type', 'tamabox']) ?>
```

Drop the `<?= $this->Html->link(__('Back'), 'javascript:history.back()') ?>` at the bottom (the body template provides its own CTAs). Keep the `<?= $this->Flash->render() ?>` block (defensive — no current 404 flow flashes anything, but the slot is harmless).

The legacy `cake.css` and `fonts.css` files become orphan after this migration. Plan 08-02 decides whether to delete them or leave as-is for caution (recommend: leave; deletion is a v3 polish PR).

### Verification

- Hi-fi side-by-side with `SendErrors.jsx` 9-64
- `composer test` green — existing `testSendGetUnknownSlugReturns404` continues to pass (assertion is on response code 404, not on content); new test `testSendNotFoundRendersHiFi` asserts response 404 + body contains "この箱は見つかりません"
- EDGE-01 satisfied

---

## Component 3 — `Messages/send.php` else-branch rewrite (EDGE-02)

**File:** `templates/Messages/send.php` (modify lines 79-83 — the `!$isAccepting` branch)
**Hi-fi:** `~/projects/handoff_tamabox/screens/SendErrors.jsx` lines 71-161 (SendInboxClosed)

### Current state

The `else` branch of `<?php if ($isAccepting): ?>` renders a 2-line `.tb-card-soft` with title + sub. Hi-fi requires a kicker + warm status block + disabled textarea stand-in + disabled CTA.

### New markup contract (when `!$isAccepting`)

```html
<div class="tb-send__closed-status" role="status">
    <div class="tb-send__closed-status__kicker">
        <span class="tb-send__closed-status__dot" aria-hidden="true"></span>
        inbox · paused
    </div>
    <h3 class="tb-send__closed-status__title">この箱はいま受信を停止しています</h3>
    <p class="tb-send__closed-status__body">
        受信者が一時的にメッセージを受け取らない設定にしています。<br>
        また開いた頃に、もう一度きてみてください。
    </p>
</div>

<div class="tb-send__textarea-block">
    <label class="tb-label" aria-hidden="true">メッセージ</label>
    <div class="tb-input tb-send__closed-input" aria-disabled="true">
        <span>受信停止中のため、入力できません</span>
    </div>
</div>
```

Plus the form-end branch (`<?php if ($isAccepting): ?>...<?php endif; ?>`) is updated so the disabled CTA still renders:

```html
<?php if (!$isAccepting): ?>
    <div class="tb-screen__cta tb-send__cta">
        <button type="button" disabled class="tb-send__closed-cta">送信できません</button>
    </div>
<?php endif; ?>
```

### Preserved behaviors

- Existing test `testSendGetIsAcceptingFalseHidesForm` asserts `'現在この受信箱は受け付けていません'` and the **absence** of `<textarea name="body"`. The new copy "この箱はいま受信を停止しています" is **different**; the test must be updated to assert the new copy. Old substring not weakening — both messages mean "inbox is closed", we are upgrading the assertion target. Plan 08-03 updates the test in the same commit.
- No `<textarea name="body">` is rendered (the disabled stand-in uses `<div>` — test absence assertion stays valid).
- Form helper (`<?php $this->Form->create(...) ?>`) is gated behind `<?php if ($isAccepting): ?>` so CSRF doesn't fire on the closed branch.

### Verification

- Hi-fi side-by-side with SendErrors.jsx 71-161
- `composer test` green — `testSendGetIsAcceptingFalseHidesForm` updated to new copy
- EDGE-02 satisfied

---

## Component 4 — Overflow live feedback (EDGE-03)

**Files:**
- `templates/Messages/send.php` (markup additions: chip, label-row, counter class)
- `webroot/js/send-counter.js` (new, ~50 lines)
- `templates/layout/default.php` (one `<script>` tag for send-counter.js)

**Hi-fi:** `~/projects/handoff_tamabox/screens/SendErrors.jsx` lines 176-298 (SendOverLimit)

### Markup additions in send.php

The textarea block (`.tb-send__textarea-block`) gains a label-row that contains the chip on the right:

```html
<div class="tb-send__textarea-block">
    <div class="tb-send__textarea-block__label-row">
        <label for="send-body" class="tb-label">メッセージ</label>
        <span class="tb-send__overflow-chip" data-send-overflow-chip aria-live="polite">長すぎます</span>
    </div>
    <textarea
        id="send-body"
        name="body" <?= $blockedDisabledAttr ?>
        required
        maxlength="2000"
        rows="7"
        aria-describedby="body-counter body-help"
        class="tb-input send-form__body"
        data-send-textarea
        placeholder="ここに書きます…"
    ><?= h($restoredBody) ?></textarea>
    <div class="tb-send__meta">
        <span id="body-help">改行・絵文字 OK · 最大 2000 文字</span>
        <span id="body-counter" class="tb-mono char-counter tb-send__counter" aria-live="polite">
            <span data-counter><?= mb_strlen($restoredBody) ?></span> / 2000
        </span>
    </div>
</div>
```

The existing `<script>` block at the bottom of `send.php` (the inline counter increment) is replaced by the dedicated `send-counter.js` file. The submit button gains a `data-send-submit` attribute hook.

### JS contract — `send-counter.js`

```js
(function () {
    'use strict';
    var ta = document.querySelector('[data-send-textarea]');
    if (!ta) { return; }
    var counter = document.querySelector('[data-counter]');
    var counterWrap = document.getElementById('body-counter');
    var chip = document.querySelector('[data-send-overflow-chip]');
    var submit = document.querySelector('[data-send-submit]');
    var MAX = 2000;

    function update() {
        var len = (ta.value || '').length;
        if (counter) { counter.textContent = len; }
        var over = len > MAX;
        if (counterWrap) {
            counterWrap.classList.toggle('tb-send__counter--over', over);
        }
        ta.classList.toggle('is-overflow', over);
        if (chip) {
            chip.classList.toggle('is-visible', over);
        }
        if (submit) {
            // Keep server-side guard (defense in depth). disabled prevents accidental submit.
            submit.disabled = over;
        }
    }
    ta.addEventListener('input', update);
    update();
})();
```

The script is **idempotent and progressive** — if JS is disabled or fails:
- The HTML5 `maxlength="2000"` attribute prevents most overflow at the browser level
- `MessagesController::processSend` server guard (`mb_strlen($body) > 2000`) catches paste-bypass attempts
- The visual chip / color / disabled state are upgrades, not gates

### Layout script tag

Add to `templates/layout/default.php` `<head>` next to `reveal-motion`:

```php
<?= $this->Html->script(['reveal-motion', 'send-counter'], ['defer' => true]) ?>
```

(or equivalent — combine into single call.)

### Verification

- Hi-fi side-by-side with SendErrors.jsx 176-298 (the chip + counter color match)
- `node -c webroot/js/send-counter.js` — syntax check (no execution needed)
- `composer test` green — no existing test asserts on the counter; new test `testSendFormIncludesOverflowChipMarkup` GETs `/alice` and asserts response contains `data-send-overflow-chip`
- EDGE-03 satisfied

---

## Component 5 — SendFailed template (EDGE-04)

**Files:**
- `src/Controller/MessagesController.php` (modify `processSend` catch block — render instead of redirect)
- `templates/Messages/send_failed.php` (new, ~80 lines)

**Hi-fi:** `~/projects/handoff_tamabox/screens/SendErrors.jsx` lines 305-414 (SendFailed)

### Controller change (allowed under D-25)

```php
} catch (RuntimeException $e) {
    // EDGE-04 (Phase 8 D-10) — render dedicated failure screen instead of
    // redirect-with-flash. Preserves body + receiver context for retry.
    $this->set([
        'inbox' => $inbox,
        'restoredBody' => $body,
    ]);
    $this->response = $this->response->withStatus(500);
    return $this->render('send_failed');
}
```

Note the `withStatus(500)` — search-bots and monitoring see the failure. The retry CTA links back to `/{slug}` (GET) which re-renders the empty form (session body restoration only fires after OAuth callback, not after a failed send).

### Template contract — `send_failed.php`

```html
<div class="tb-screen tb-screen--send tb-send-failed">
    <header class="tb-appbar">
        <div class="tb-appbar__left">
            <a href="/<?= h((string)$inbox->slug) ?>" class="tb-icon-btn" aria-label="戻る">
                <?= icon back 22 ?>
            </a>
            <span class="tb-appbar__title">メッセージを送る</span>
        </div>
        <div></div>
    </header>

    <div class="tb-send-failed__banner" role="alert">
        <span class="tb-send-failed__symbol" aria-hidden="true">!</span>
        <div class="tb-flex-grow">
            <div class="tb-send-failed__title">送信できませんでした</div>
            <div class="tb-send-failed__sub">通信が不安定なようです。本文はそのまま残してあります。</div>
        </div>
        <a href="/<?= h((string)$inbox->slug) ?>" class="tb-send-failed__retry">再送信</a>
    </div>

    <section class="tb-screen__body tb-send__body">
        <!-- Receiver card (reuse Send pattern) -->
        <div class="tb-card tb-send__receiver">…</div>
        <!-- Preserved body — read-only view of what was attempted -->
        <div class="tb-send__textarea-block">
            <label class="tb-label">メッセージ</label>
            <div class="tb-input tb-send-failed__body-preserved">
                <?= nl2br(h((string)$restoredBody)) ?>
            </div>
        </div>
    </section>

    <div class="tb-screen__cta tb-send__cta">
        <a href="/<?= h((string)$inbox->slug) ?>" class="tb-btn tb-btn--primary tb-btn--full">
            <span>もう一度送信</span>
            <?= icon send 16 ?>
        </a>
    </div>
</div>
```

### Out of scope vs hi-fi

The hi-fi SendFailed has a "下書き保存" sub-button and an `net_err · 09:41:08` debug strip. Both are deferred to v3 (no localStorage drafts, no error-code surfacing in v2). The plan 08-05 PHP omits both.

### Verification

- Hi-fi side-by-side with SendErrors.jsx 305-414
- `composer test` green — new test `testSendPostRendersFailedWhenMessagesTableThrows` mocks MessagesTable::sendMessage to throw RuntimeException, asserts response 500 + body contains "送信できませんでした"
- EDGE-04 satisfied

---

## Component 6 — Block confirm modal element (EDGE-05)

**Files:**
- `templates/element/tb_block_modal.php` (new, ~60 lines)
- `webroot/js/block-modal.js` (new, ~40 lines)
- `templates/Users/dashboard.php` (modify HIT branch — Block button becomes modal trigger + modal element inclusion)

**Hi-fi:** `~/projects/handoff_tamabox/screens/RevealHit.jsx` lines 172-300 (BlockConfirmModal). The standalone `screens/Block.jsx` is the BlockList screen, NOT the confirm modal — the confirm modal source of truth is RevealHit.jsx.

### Element API — `tb_block_modal.php`

```php
<?= $this->element('tb_block_modal', [
    'modalId'          => 'block-modal-' . h((string)$msg->id),
    'senderHandle'     => $senderHandle,            // e.g. 'morino.bsky.social'
    'senderUserId'     => $senderUserId,            // UUID for POST /block/{id}
    'senderInitial'    => mb_substr($senderHandle, 0, 1),
]) ?>
```

- `modalId` MUST be unique per dashboard render (multiple HIT cards may coexist) — convention: include `$msg->id`.
- All values are escaped inside the element body; the call site passes raw values.

### Modal markup

```html
<dialog class="tb-block-modal" id="<?= h($modalId) ?>">
    <div class="tb-block-modal__sheet" role="document">
        <div class="tb-block-modal__handle" aria-hidden="true"></div>

        <div class="tb-block-modal__heading">
            <span class="tb-block-modal__avatar" aria-hidden="true"><?= h($senderInitial) ?></span>
            <div class="tb-flex-grow">
                <div class="tb-block-modal__name"><?= h(mb_substr($senderHandle, 0, mb_strpos($senderHandle, '.') ?: mb_strlen($senderHandle))) ?> さんをブロック</div>
                <div class="tb-block-modal__handle-text tb-mono">@<?= h($senderHandle) ?></div>
            </div>
        </div>

        <ul class="tb-block-modal__list">
            <li class="tb-block-modal__list-item">
                <span class="tb-block-modal__list-dot" aria-hidden="true"></span>
                <span>このユーザーから新しいメッセージを受け取りません</span>
            </li>
            <li class="tb-block-modal__list-item">
                <span class="tb-block-modal__list-dot" aria-hidden="true"></span>
                <span>今回のメッセージは受信箱に残ります</span>
            </li>
            <li class="tb-block-modal__list-item">
                <span class="tb-block-modal__list-dot" aria-hidden="true"></span>
                <span>ブロックの事実は相手に通知されません</span>
            </li>
        </ul>

        <p class="tb-block-modal__hint">
            解除は <b>設定 → ブロック中</b> からいつでも行えます。
        </p>

        <?= $this->Form->create(null, [
            'url' => '/block/' . h($senderUserId),
            'type' => 'post',
            'class' => 'tb-block-modal__actions',
        ]) ?>
            <button type="submit" class="tb-block-modal__confirm">ブロックする</button>
            <button type="button" class="tb-block-modal__cancel" data-block-modal-close>キャンセル</button>
        <?= $this->Form->end() ?>
    </div>
</dialog>
```

CSRF token is auto-injected by `$this->Form->create()` (CakePHP middleware). Existing `BlocksController::create` POST handler is untouched (D-25 — no new backend action).

### JS contract — `block-modal.js`

```js
(function () {
    'use strict';
    function bindTriggers() {
        var triggers = document.querySelectorAll('[data-block-modal-trigger]');
        triggers.forEach(function (btn) {
            if (btn.dataset.blockModalArmed === '1') { return; }
            btn.dataset.blockModalArmed = '1';
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                var id = btn.getAttribute('data-block-modal-trigger');
                var dlg = document.getElementById(id);
                if (dlg && typeof dlg.showModal === 'function') {
                    dlg.showModal();
                }
            });
        });
    }
    function bindCancels() {
        document.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t) { return; }
            // closest() falls back gracefully if not supported
            var btn = t.closest && t.closest('[data-block-modal-close]');
            if (!btn) { return; }
            ev.preventDefault();
            var dlg = btn.closest('dialog');
            if (dlg && typeof dlg.close === 'function') {
                dlg.close();
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bindTriggers(); bindCancels(); });
    } else {
        bindTriggers();
        bindCancels();
    }
})();
```

Idempotent (the `armed` dataset key prevents double-binding on re-init). Browser support: native `<dialog>` is supported in all evergreen browsers. Phase 8 D-14 confirms IE11 is out of scope (the production user base is Bluesky users on modern devices).

### Dashboard wiring (in `Users/dashboard.php` HIT branch)

Current:
```php
<?= $this->Form->create(null, ['url' => '/block/' . h($senderUserId), ...]) ?>
    <button type="submit" class="tb-btn tb-btn--quiet">このユーザーをブロック</button>
<?= $this->Form->end() ?>
```

Replaces with:
```php
<button type="button"
        class="tb-btn tb-btn--quiet"
        data-block-modal-trigger="block-modal-<?= h((string)$msg->id) ?>">
    このユーザーをブロック
</button>
<?= $this->element('tb_block_modal', [
    'modalId'       => 'block-modal-' . h((string)$msg->id),
    'senderHandle'  => $senderHandle,
    'senderUserId'  => $senderUserId,
    'senderInitial' => $senderHandle !== '' ? mb_substr($senderHandle, 0, 1) : '?',
]) ?>
```

The form (with CSRF token) now lives inside the `<dialog>` and only submits on the confirm button. Existing `BlocksControllerTest` continues to pass — the form action / method / target URL are identical to the old inline form.

### Layout script tag

Add `block-modal` to the `defer` script list in `layout/default.php`.

### Verification

- Hi-fi side-by-side with RevealHit.jsx 172-300
- `composer test` green — existing `BlocksControllerTest::testCreateInsertsBlockRow` still passes (form submits to same URL). New test `testBlockModalElementRendersWithRequiredParts` mocks an element render and asserts `<dialog class="tb-block-modal"` + the three consequence strings appear
- EDGE-05 satisfied

---

## Component 7 — Phase 7 cleanup (D-21..D-24)

**Files:**
- `templates/Users/dashboard.php` (17 inline `style="..."` instances → CSS class refactor)
- `src/Controller/UsersController.php` (drop `$inbox` / `$blocks` from dashboard view-data? — see note below)

### Inline-style elimination map

| Line | Original inline style | Replacement |
|------|----------------------|-------------|
| 68 | `style="flex:1; min-width:0;"` (Box card) | `class="tb-flex-grow"` |
| 76 | `style="display:flex; align-items:baseline; gap:10px;"` | `class="tb-flex-row--baseline"` |
| 123 | `style="flex:1; min-width:0;"` (message row meta wrapper) | `class="tb-flex-grow"` |
| 150 | hit-card corner glyph absolute positioning | `class="tb-reveal-hit-card__corner-glyph"` |
| 152 | `style="flex:1; min-width:0; position:relative; z-index:1;"` (hit content) | `class="tb-reveal-hit-card__content"` |
| 153 | hit kicker font/color/letter-spacing | `class="tb-reveal-hit-card__kicker"` |
| 154 | hit title 16/700 + margin-top 3px | `class="tb-reveal-hit-card__title"` (the 3px is acknowledged §I.7) |
| 155 | hit sub 12px / ink-2 | `class="tb-reveal-hit-card__sub"` |
| 156 | mono pct color warm-700 | `<span class="tb-mono tb-reveal-hit-card__sub-pct">` |
| 172 | sender-card text block flex-grow | `class="tb-flex-grow"` |
| 173 | sender-card row 6px gap | `class="tb-flex-row--sm-gap"` |
| 175 | "SSR" small label | `<span class="tb-sender-card__name-ssr">SSR</span>` |
| 202 | miss-card content flex-grow | `class="tb-flex-grow"` |
| 203 | miss kicker | `class="tb-reveal-miss-card__kicker"` |
| 204 | miss title (margin-top 3px preserved) | `class="tb-reveal-miss-card__title"` |
| 205 | miss sub | `class="tb-reveal-miss-card__sub"` |
| 206 | miss mono pct | `class="tb-mono tb-reveal-miss-card__sub-pct"` |

After the refactor, `dashboard.php` should have **0** inline `style="..."` attributes (`grep -c 'style="' templates/Users/dashboard.php` → `0`).

### Controller view-data trim — `dashboard()` `$inbox` / `$blocks`

CONTEXT D-22 marks the dashboard's `$inbox` / `$blocks` view-data as **unused**. Reviewing `templates/Users/dashboard.php`:
- `$inbox` IS used (line 29 `$slug = (string)$inbox->slug;` and line 30 SSR %). KEEP.
- `$blocks` is NOT referenced in the template body. The PHPDoc at line 9 already says "unused in this template (moved to /dashboard/settings)".

So D-22 narrows to dropping `$blocks` from `dashboard()`'s `$this->set()`. Plan 08-07 makes this trim and verifies tests stay green. The "ブロック中ユーザー" assertions all live in `Inboxes/settings.php` now (moved in Phase 7).

### #FBFCFD locked decision confirmation

The locked decision is already documented in `08-CONTEXT.md` (Phase 8 追記 lines 90-95). Plan 08-07 verifies the §I CSS comment near `.tb-message-row[data-state="unread"]` cross-references the decision doc (no new CSS, just a comment line).

### `margin-top: 3px` minor off-grid

This appears in §H.3 `.tb-dash-box__url` and §I.7 `.tb-reveal-hit-card__title` / `.tb-reveal-miss-card__title`. The 3px breaks the 4-grid by 1px. Justification: matches hi-fi `Dashboard.jsx` and `RevealHit.jsx` typographic optical baseline. Document in `08-CONTEXT.md` as an "Acknowledged sub-grid micro-offset" under the locked-decisions section. Plan 08-07 makes this doc update.

### Verification

- `composer test` green — no test asserts on inline styles, so the refactor is invisible to PHPUnit
- `grep -c 'style="' templates/Users/dashboard.php` → 0 (post-refactor target)
- `$blocks` removed from dashboard `$this->set()`; `UsersControllerTest` tests still pass (none of them assert on `$blocks` from /dashboard)
- Phase 7 D-21..D-24 satisfied

---

## CSS map — §I additions to `tamabox.css`

```
§I.1  .tb-btn:active scale + reduced-motion guard           (MOTION-01)
§I.2  .tb-error-screen scaffold + symbol + body + CTA       (EDGE-01, 02, 04)
§I.3  .tb-send__closed-status block + disabled input + CTA  (EDGE-02)
§I.4  .tb-send__counter--over + chip + .is-overflow         (EDGE-03)
§I.5  .tb-send-failed banner + retry + symbol               (EDGE-04)
§I.6  .tb-block-modal dialog + sheet + heading + actions    (EDGE-05)
§I.7  Phase 7 cleanup helpers (flex-grow, reveal-card parts)
```

Total new CSS: ~280 lines. The §I section sits after Phase 7's §H.8 (.tb-dash-avatar).

---

## View variable additions

| Controller / view | New key | Source | Used by |
|--------------------|---------|--------|---------|
| MessagesController::processSend catch | `restoredBody` | user POST body | send_failed.php |
| (dashboard template) `$modalId` | local inline | concat `'block-modal-' . $msg->id` | element call site (not a `$this->set`) |

No DB schema changes. No new routes. No new controller actions.

---

## Cross-cutting patterns

### Form helper + CSRF preservation

All POST flows continue to use `$this->Form->create()`. The block modal form lives inside `<dialog>` but is still server-rendered with CSRF token injected at render time — this is the same pattern used in v1 for the inline block button.

### Backend immutability (D-25, D-26)

Phase 8 modifies ONLY:
- `src/Controller/MessagesController.php::processSend` catch (render switch, +6 lines, allowed under D-25)
- `src/Controller/UsersController.php::dashboard` (drop `$blocks` set — D-22 cleanup)

NO changes to:
- Any Model / Table
- Any Migration
- OAuth flow
- Moderation logic (Blocks / Reports)
- AnyController action body except `MessagesController::processSend` catch

### Layout integration

`templates/layout/default.php` gains 2 script tags (`send-counter.js` + `block-modal.js`) added defer-loaded into `<head>` next to `reveal-motion`. The existing `<script>` block at the bottom of `send.php` is **removed** (its logic moved to `send-counter.js`).

`templates/layout/error.php` is migrated to the Calm Gacha CSS chain (drop `cake.css` / `fonts.css`, add `tokens.css` / `colors_and_type.css` / `tamabox.css`).

### `prefers-reduced-motion` continuity

All new transforms / animations gain reduced-motion guards in §I:
- §I.1 `.tb-btn:active` transform disabled
- §I.6 `.tb-block-modal__sheet` animation set to none (the dialog still pops; only the slide animation is suppressed — and since we rely on `<dialog>`'s default which has no slide animation in §I.6, this is a defensive future-proof)

---

## Verification Checklist (per-screen + phase-wide)

### Per-screen

| Screen | Hi-fi side-by-side | composer test | Requirement |
|--------|--------------------|---------------|-------------|
| 404 page | SendErrors.jsx 9-64 | new test asserts 404 + "この箱は見つかりません" | EDGE-01 |
| Inbox closed | SendErrors.jsx 71-161 | `testSendGetIsAcceptingFalseHidesForm` updated to new copy | EDGE-02 |
| Overflow chip | SendErrors.jsx 176-298 | new test asserts `data-send-overflow-chip` markup | EDGE-03 |
| SendFailed | SendErrors.jsx 305-414 | new test mocks RuntimeException → render | EDGE-04 |
| Block modal | RevealHit.jsx 172-300 | new test asserts element output structure | EDGE-05 |
| `.tb-btn:active` scale | (CSS-only; no test) | unchanged | MOTION-01 |

### Phase-wide

- [ ] All 4 send error screens visually match SendErrors.jsx variants
- [ ] Block confirm modal matches RevealHit.jsx 172-300 bottom-sheet
- [ ] `.tb-btn:active scale(0.985) 80ms` works on all 5 variants
- [ ] `composer test` 199 baseline → 199+ tests, 0 failures
- [ ] No new `--tb-*` token added (1 documented locked color exception `#FBFCFD` carried from Phase 7)
- [ ] Two single-use literals (`#EFD5D2`, `#FFFBEF`, `#F0DCA8` reuse) commented at point-of-use
- [ ] Phase 7 deferred cleanup items (inline-styles, `$blocks`, 3px off-grid, #FBFCFD) all completed
- [ ] No backend file touched outside D-25 allowance (MessagesController catch)
- [ ] Manual smoke checklist runs against tamabox.emomie.com after deploy (out of band — `status: human_needed`)

---

## Checker Sign-Off (advisory)

- [ ] Dimension 1 Copywriting: PASS (no emoji except locked `✦`, `?`, `!`, `—`)
- [ ] Dimension 2 Visuals: PASS (4 error screens + modal match hi-fi)
- [ ] Dimension 3 Color: PASS (turquoise + honey + ink + danger; 3 single-use literal documented at point-of-use; 1 locked color exception inherited)
- [ ] Dimension 4 Typography: PASS (Phase 5 override inherited; half-pixel hi-fi values rounded)
- [ ] Dimension 5 Spacing: PASS (4-grid; 3 locked exceptions inherited; 1 documented 3px sub-grid acknowledged)
- [ ] Dimension 6 Registry Safety: PASS (no new icons; reuse existing `back`, `send`, `check`)

---

## Pre-Population Sources

| Source | Decisions Used |
|--------|---------------|
| `~/projects/handoff_tamabox/screens/SendErrors.jsx` | All 4 error variants — full markup template |
| `~/projects/handoff_tamabox/screens/RevealHit.jsx` 172-300 | BlockConfirmModal bottom sheet |
| `~/projects/handoff_tamabox/components.jsx` | TbButton / TbChip / TbAppBar baseline |
| `08-CONTEXT.md` | D-01..D-30 (all 30 decisions inform the spec) |
| Phase 7 §H CSS | reuse pattern + helper class naming |
| `tokens.css` `.tb-btn` | base transition already in place — only `:active` rule needed |
| `tamabox.css` §G/§H | section comment style + 4-grid spacing |
