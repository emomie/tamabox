<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 *
 * Settings page (currently routed only via /dashboard inline; this template exists
 * for direct render compatibility per CakePHP convention).
 */
$this->assign('title', '受信箱設定');
?>
<div class="settings-page">
    <h1>受信箱設定</h1>
    <?= $this->element('inbox_settings_form', ['inbox' => $inbox]) ?>
</div>
