<?php
/**
 * @var \App\View\AppView $this
 * @var string $modalId
 * @var string $senderHandle
 * @var string $senderUserId
 * @var string $senderInitial
 *
 * EDGE-05 — Phase 8 D-13..D-17: bottom-sheet confirm modal for blocking a sender.
 * Hi-fi: ~/projects/handoff_tamabox/screens/RevealHit.jsx :: BlockConfirmModal (lines 172-300)
 *
 * Element API:
 *   <?= $this->element('tb_block_modal', [
 *       'modalId'       => 'block-modal-' . (string)$msg->id,
 *       'senderHandle'  => $senderHandle,         // e.g. 'morino.bsky.social'
 *       'senderUserId'  => $senderUserId,         // UUID for POST /block/{id}
 *       'senderInitial' => mb_substr($senderHandle, 0, 1),  // single-char fallback
 *   ]) ?>
 *
 * Notes:
 * - $modalId MUST be unique per dashboard render (multiple HIT cards may coexist).
 * - $this->Form->create() injects CSRF token automatically (CakePHP middleware).
 * - Native HTML5 <dialog>: showModal/close via webroot/js/block-modal.js. ESC closes by default.
 */

// Derive display name from handle: first segment before the first dot.
$displayDot = mb_strpos($senderHandle, '.');
if ($displayDot === false || $displayDot === 0) {
    $displayName = $senderHandle;
} else {
    $displayName = mb_substr($senderHandle, 0, $displayDot);
}
if ($displayName === '') {
    $displayName = '送信者';
}
?>
<dialog class="tb-block-modal" id="<?= h($modalId) ?>" aria-labelledby="<?= h($modalId) ?>-title" aria-describedby="<?= h($modalId) ?>-impacts">
    <div class="tb-block-modal__sheet" role="document">
        <div class="tb-block-modal__handle" aria-hidden="true"></div>

        <div class="tb-block-modal__heading">
            <span class="tb-block-modal__avatar" aria-hidden="true"><?= h($senderInitial) ?></span>
            <div class="tb-flex-grow">
                <div class="tb-block-modal__name" id="<?= h($modalId) ?>-title"><?= h($displayName) ?> さんをブロック</div>
                <div class="tb-block-modal__handle-text tb-mono">@<?= h($senderHandle) ?></div>
            </div>
        </div>

        <ul class="tb-block-modal__list" id="<?= h($modalId) ?>-impacts">
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

        <?php // $senderUserId is route-constrained to [0-9a-f-]{36}; Form helper escapes its own URL attr. ?>
        <?= $this->Form->create(null, [
            'url' => '/block/' . $senderUserId,
            'type' => 'post',
            'class' => 'tb-block-modal__actions',
        ]) ?>
            <button type="submit" class="tb-block-modal__confirm">ブロックする</button>
            <button type="button" class="tb-block-modal__cancel" data-block-modal-close>キャンセル</button>
        <?= $this->Form->end() ?>
    </div>
</dialog>
