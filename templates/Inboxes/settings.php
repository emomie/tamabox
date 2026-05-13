<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 * @var array<int, \App\Model\Entity\Block> $blocks
 * @var string $activeTab
 * @var int $unreadCount
 *
 * NAV-06 — Settings tab (Phase 7 Calm Gacha rewrite).
 * Hi-fi: ~/projects/handoff_tamabox/screens/Settings.jsx
 *
 * Renders inbox settings form (Phase 6 element) + block list (Phase 6 element)
 * + footer TabBar. AppBar lives at the top of this template (per Phase 6
 * cross-screen pattern: AppBar is content-internal, the global header-bar
 * remains in layout/default.php).
 *
 * Controller: InboxesController::settings() — GET branch renders this template
 * (changed from 302 → render in plan 07-03); POST branch unchanged.
 */
$this->assign('title', '受信箱の設定');
?>
<div class="tb-dash-screen settings-page">
    <header class="tb-appbar">
        <div class="tb-appbar__left">
            <button type="button" class="tb-icon-btn" aria-label="戻る" onclick="history.back()">
                <?= $this->element('icon', ['name' => 'back', 'size' => 22]) ?>
            </button>
            <div>
                <div class="tb-appbar__title">受信箱の設定</div>
            </div>
        </div>
        <div class="tb-appbar__right"></div>
    </header>

    <div class="tb-dash-screen__body" style="padding-top: 8px; gap: 18px;">
        <?= $this->element('inbox_settings_form', ['inbox' => $inbox]) ?>

        <?= $this->element('block_list', ['blocks' => $blocks]) ?>
    </div>

    <?= $this->element('tb_tabbar', ['active' => 'settings', 'unreadCount' => $unreadCount]) ?>
</div>
