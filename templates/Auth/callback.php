<?php
/**
 * @var \App\View\AppView $this
 *
 * Interstitial spinner page (UI-SPEC §3 Spinner).
 *
 * Currently unused because OauthController::callback redirects immediately on both
 * success and error paths. Kept for UI-SPEC §3 reference and for future async flows
 * where the callback may need to render HTML (e.g., a redirect holding page).
 */
$this->assign('title', 'Bluesky と通信中');
?>
<div class="callback-page">
    <h2>Bluesky と通信中…</h2>
    <div role="status" aria-live="polite" class="spinner-wrapper">
        <div class="spinner" aria-hidden="true"></div>
        <span class="visually-hidden">Bluesky と通信中…</span>
    </div>
</div>
