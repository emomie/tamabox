<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 *
 * Send-done page — fixed copy per UI-SPEC §9.
 * D-19: NO SSR result is shown to the sender.
 */
$this->assign('title', '送信しました');
?>
<div class="send-done-page">
    <p class="send-done__lead">送信しました。受け手が開封したとき、抽選次第であなたのアカウントが開示されます。</p>
    <div class="send-done__actions">
        <?= $this->Html->link(
            '同じ受信箱に再送する',
            '/' . h((string)$inbox->slug),
            ['class' => 'button primary-button']
        ) ?>
        <?= $this->Html->link(
            '他の受信箱を見る',
            '/',
            ['class' => 'button button-clear']
        ) ?>
    </div>
</div>
