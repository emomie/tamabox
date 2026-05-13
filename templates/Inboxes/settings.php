<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 * @var array<int, \App\Model\Entity\Block> $blocks
 *
 * Settings page (Phase 7 NAV-06 intermediate state — full hi-fi layout lands in 07-05).
 */
$this->assign('title', '受信箱設定');
?>
<div class="settings-page">
    <h1>受信箱設定</h1>
    <?= $this->element('inbox_settings_form', ['inbox' => $inbox]) ?>
    <?= $this->element('block_list', ['blocks' => $blocks]) ?>
</div>
