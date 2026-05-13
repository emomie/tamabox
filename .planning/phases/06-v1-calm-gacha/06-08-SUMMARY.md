# 06-08-SUMMARY — Send Calm Gacha 化 (UI-02)

**Status:** complete

## One-liner
Rewrote `templates/Messages/send.php` to the Calm Gacha layout per `Send.jsx`: back-icon AppBar → receiver card (gradient avatar + display name + slug + warm SSR chip) → optional honey `.tb-letter` welcome → textarea with mono char counter → custom consent tile → sticky primary CTA with send icon. All Phase 4 behaviors (blocked-form is-disabled, self-inbox notice, accepting=false copy) preserved.

## Files changed
- `templates/Messages/send.php` (rewrite — 80 → 116 lines)
- `webroot/css/tamabox.css` (+144 lines, §G.10)

## Decisions
- Welcome message is rendered inside `.tb-letter.tb-send__welcome` — `.tb-letter` is the Phase 5 component that satisfies UI-02 acceptance "welcome shows in a TbLetter card". Overrides on `.tb-send__welcome` retint the background/border to honey per hi-fi.
- The 56px gradient receiver avatar is a styled `<span>` with first-character fallback (the controller does not pass a recipient avatar URL, so the gradient + initial is the production-stable substitute).
- The send-form spans `.tb-screen__body` AND `.tb-screen__cta` (the sticky bottom region). Used `display: contents` on `.tb-send-form` so the form doesn't break the screen flex layout. Form submit button lives inside `.tb-screen__cta` but is inside the form's HTML scope.
- `is-disabled` modifier on the form (Phase 4 D-05/D-06) now applies to both body and CTA via `.tb-send-form.is-disabled .tb-screen__body, .tb-screen__cta`. Native `disabled` attributes on textarea/checkbox/button also prevent action.
- Character counter span `[data-counter]` preserved. JS updates the count on input.
- The legacy `error-banner` class kept on the blocked-user banner so existing CSS aliases continue to apply (defense in depth — `.tb-send__error` provides Calm Gacha styling).
- Send-icon trailing the CTA label uses the `icon` element.

## Metrics
- `composer test`: 195/195 pass, 548 assertions
- LOC: 80 → 116 (template), CSS +144 lines

## Verification
- (a) Hi-fi side-by-side with `Send.jsx`: structure matches (back appbar → receiver card with warm chip → welcome letter → textarea with mono counter → consent tile → sticky primary CTA with send icon).
- (b) composer test: green — all MessagesController happy/edge paths pass.
- (c) Controller behavior unchanged: POST `/{slug}` with `body` + `consent` resolves; `$isBlocked`/`$isOwnInbox`/`$isAuthenticated` all consumed identically; CSRF intact via Form->create.
- (d) Manual smoke: deferred to phase end.
