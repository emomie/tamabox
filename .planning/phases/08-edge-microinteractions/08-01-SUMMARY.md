---
phase: 8
plan: 08-01
status: complete
commit: 1e20dfc
requirements: [MOTION-01]
completed: 2026-05-13
---

# Plan 08-01 Summary — §I CSS Foundations + MOTION-01

## What shipped

Appended a new §I section (458 lines) to `webroot/css/tamabox.css` after Phase 7's §H.8 (.tb-dash-avatar):

- **§I.1** `.tb-btn:active { transform: scale(0.985); }` — universal press feedback (MOTION-01). The base `.tb-btn` rule in `tokens.css` already provides `transition: transform 0.08s ease`, so the press animation is 80ms by inheritance. `prefers-reduced-motion: reduce` opt-out included.
- **§I.2** `.tb-error-screen*` layout helpers — error-screen scaffold consumed by EDGE-01 / 02 / 04 templates (76px dashed-circle symbol, 280-char body text, 280-max-width CTA stack).
- **§I.3** `.tb-send__closed-status*` — EDGE-02 warm status block (kicker dot + uppercase label + title + body) + `.tb-send__closed-input` disabled-textarea stand-in + `.tb-send__closed-cta` disabled paper-deep button.
- **§I.4** `.tb-send__counter--over` + `.tb-send__overflow-chip` + `.tb-input.is-overflow` — EDGE-03 live overflow state (chip toggles `display: none ↔ inline-flex` via `.is-visible`; textarea border warm-500 + cream bg `#FFFBEF`).
- **§I.5** `.tb-send-failed__banner` + symbol + retry pill + preserved-body block — EDGE-04. Single-use literal `#EFD5D2` for the banner border (edge tone of `--tb-danger-bg`, documented in §I.5 comment).
- **§I.6** `.tb-block-modal*` — EDGE-05. Native `<dialog>` styled as bottom sheet (`position: absolute; left/right: 16px; bottom: 24px;`), `::backdrop` blurred scrim, drag handle, gradient avatar, soft-card list, danger/cancel stack.
- **§I.7** Phase 7 cleanup helpers — `.tb-flex-grow` / `.tb-flex-row` / `.tb-reveal-hit-card__corner-glyph` / `.tb-reveal-hit-card__content` + 4 reveal-card text helpers + `.tb-sender-card__name-ssr`. Section comment cross-references `08-CONTEXT.md` for the `#FBFCFD` locked color exception.

## Files touched

- `webroot/css/tamabox.css` — +458 lines (2413 → 2871 lines)

## Tests

```
Tests: 199, Assertions: 553, Incomplete: 6.
OK
```

No new tests this plan (CSS-only changes; the rule selectors get exercised by template tests in 08-02..08-07).

## Decisions confirmed

- D-18: CSS-only MOTION-01; no JS.
- D-19: reduced-motion opt-out applied (Phase 7 M-02 pattern).
- D-20: `.tb-icon-btn` does NOT receive press scale (touch-target rationale — UI-SPEC §I.1 out-of-scope note).
- 3 single-use literal hex values documented at point of use (`#F0DCA8` reused from §H.5; `#FFFBEF`, `#EFD5D2` are new but flagged as edge tones of existing tokens).

## Risks closed

- Cascade conflict with `tokens.css:164` `.tb-btn--primary:active`: confirmed safe. Both rules produce `transform: scale(0.985)`; the primary-only `background: var(--tb-turq-500)` override remains scoped.
- Token policy: no new `--tb-*` token added. The 3 literal hex stay literal until a second consumer arises.

## Unlocks

08-02 (EDGE-01 SendNotFound) — uses §I.2 scaffold
08-03 (EDGE-02 SendInboxClosed) — uses §I.3 status block
08-04 (EDGE-03 overflow) — uses §I.4 live state
08-05 (EDGE-04 SendFailed) — uses §I.5 banner
08-06 (EDGE-05 Block modal) — uses §I.6 dialog
08-07 (Phase 7 cleanup) — uses §I.7 helpers
