/**
 * send-counter.js — EDGE-03 (Phase 8) live overflow feedback for Send.
 *
 * Source: .planning/phases/08-edge-microinteractions/08-UI-SPEC.md §"Component 4"
 *
 * Progressive enhancement (D-09): server-side guard in MessagesController::processSend
 * always enforces the 2000-char limit; this script only adds visual feedback.
 *
 * Hooks:
 *   [data-send-textarea]            — the <textarea> being measured
 *   [data-send-overflow-chip]       — the "長すぎます" pill (display:none default)
 *   [data-send-submit]              — the submit button (gets `disabled` while over)
 *   #body-counter                   — the counter wrap (gets .tb-send__counter--over)
 *   [data-counter]                  — text node inside the counter wrap
 *
 * Idempotent: re-running is a no-op (we don't bind twice — single textarea per page).
 */
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
            // Defense in depth: server guard re-checks mb_strlen > 2000.
            submit.disabled = over;
        }
    }
    ta.addEventListener('input', update);
    update();
})();
