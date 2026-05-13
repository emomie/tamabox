/**
 * Phase 7 MOTION-02 — Reveal fade-in motion.
 * Attaches a toggle listener to each <details class="message-row"> so that when
 * the user opens a closed details element, the .message-row__body fades in via
 * the .is-opening class (CSS keyframe tb-fade-in @ tamabox.css §H.2).
 *
 * Idempotent: data-reveal-armed flag prevents double-binding.
 */
(function () {
    'use strict';
    function arm(details) {
        if (!details || details.dataset.revealArmed === '1') { return; }
        details.dataset.revealArmed = '1';
        details.addEventListener('toggle', function () {
            if (!details.open) { return; }
            var body = details.querySelector('.message-row__body');
            if (!body) { return; }
            body.classList.remove('is-opening');
            // Force reflow so re-adding the class restarts the animation.
            void body.offsetWidth;
            body.classList.add('is-opening');
            window.setTimeout(function () {
                body.classList.remove('is-opening');
            }, 500); /* 400ms keyframe + 100ms buffer */
        });
    }
    function armAll() {
        document.querySelectorAll('details.message-row').forEach(arm);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', armAll);
    } else {
        armAll();
    }
})();
