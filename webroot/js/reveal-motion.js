/**
 * Phase 7 MOTION-02 — Reveal fade-in motion.
 * Attaches a toggle listener to each <details class="message-row"> so that when
 * the user opens a closed details element, the .message-row__body fades in via
 * the .is-opening class (CSS keyframe tb-fade-in @ tamabox.css §H.2).
 *
 * Also fires the fade-in on initial paint when the page loads with a target
 * details already opened (post-redirect path: server renders <details ... open>
 * and the URL hash points at #msg-{id}). Browsers do NOT fire a `toggle` event
 * for an already-open details on initial paint, so the toggle listener alone
 * misses the primary reveal moment. See REVIEW M-01.
 *
 * Honors prefers-reduced-motion: reduce — when set, both paths short-circuit
 * (the .is-opening class is never added so the CSS animation never runs).
 *
 * Idempotent: data-reveal-armed flag prevents double-binding.
 */
(function () {
    'use strict';

    function prefersReducedMotion() {
        return !!(window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function playFade(body) {
        if (!body) { return; }
        if (prefersReducedMotion()) { return; }
        body.classList.remove('is-opening');
        // Force reflow so re-adding the class restarts the animation.
        void body.offsetWidth;
        body.classList.add('is-opening');
        window.setTimeout(function () {
            body.classList.remove('is-opening');
        }, 500); /* 400ms keyframe + 100ms buffer */
    }

    function arm(details) {
        if (!details || details.dataset.revealArmed === '1') { return; }
        details.dataset.revealArmed = '1';
        details.addEventListener('toggle', function () {
            if (!details.open) { return; }
            var body = details.querySelector('.message-row__body');
            playFade(body);
        });
    }

    function armAll() {
        document.querySelectorAll('details.message-row').forEach(arm);
    }

    /**
     * REVIEW M-01 fix — fire the fade-in for the post-redirect initial paint.
     * The open-form POST → redirect lands at /dashboard#msg-{id} with the row
     * already in `open` state. No `toggle` event fires for an already-open
     * details on page load (HTML spec — toggle fires only on transitions), so
     * we trigger the same fade manually if the URL hash points at a known
     * message row.
     */
    function fireInitialFadeFromHash() {
        var hash = window.location.hash;
        if (!hash || hash.indexOf('#msg-') !== 0) { return; }
        var target = document.getElementById(hash.slice(1));
        if (!target) { return; }
        if (target.tagName !== 'DETAILS') { return; }
        if (!target.classList.contains('message-row')) { return; }
        if (!target.open) { return; }
        var body = target.querySelector('.message-row__body');
        playFade(body);
    }

    function init() {
        armAll();
        fireInitialFadeFromHash();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
