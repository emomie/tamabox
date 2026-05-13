/**
 * block-modal.js — EDGE-05 (Phase 8) Block confirm modal control.
 *
 * Source: .planning/phases/08-edge-microinteractions/08-UI-SPEC.md §"Component 6"
 *
 * Hooks:
 *   [data-block-modal-trigger="<dialog-id>"]  — button that opens the modal
 *   [data-block-modal-close]                  — button inside <dialog> that closes it
 *
 * Native <dialog> handles ESC-to-close and ::backdrop rendering. We only wire
 * the open/close click handlers. Idempotent — re-running is safe.
 */
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
                if (!id) { return; }
                var dlg = document.getElementById(id);
                if (dlg && typeof dlg.showModal === 'function') {
                    dlg.showModal();
                }
            });
        });
    }

    function bindCancels() {
        if (document.body && document.body.dataset.blockModalCancelArmed === '1') { return; }
        if (document.body) { document.body.dataset.blockModalCancelArmed = '1'; }
        document.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t || typeof t.closest !== 'function') { return; }
            var btn = t.closest('[data-block-modal-close]');
            if (!btn) { return; }
            ev.preventDefault();
            var dlg = btn.closest('dialog');
            if (dlg && typeof dlg.close === 'function') {
                dlg.close();
            }
        });
    }

    function init() {
        bindTriggers();
        bindCancels();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
