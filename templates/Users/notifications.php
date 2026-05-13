<?php
/**
 * @var \App\View\AppView $this
 * @var string $activeTab
 *
 * NAV-05 — 通知タブ Empty-state スタブ (Phase 7).
 * Hi-fi: ~/projects/handoff_tamabox/screens/Notifications.jsx (Empty subset).
 *
 * No backend / no DB query. The full notification feed (新着 / これまで sections,
 * 種別アイコン rows) is deferred to v3 (NOTIF-01).
 */
$this->assign('title', '通知');
?>
<div class="tb-dash-screen notifications-page">
    <header class="tb-appbar tb-appbar--big">
        <div class="tb-appbar__left">
            <div>
                <div class="tb-appbar__title">通知</div>
            </div>
        </div>
        <div class="tb-appbar__right"></div>
    </header>

    <div class="tb-notif-empty">
        <div class="tb-notif-empty__circle" aria-hidden="true">
            <?= $this->element('icon', ['name' => 'bell', 'size' => 36]) ?>
        </div>
        <div class="tb-notif-empty__title">通知はまだありません</div>
        <p class="tb-notif-empty__body">メッセージへの返信や開封のお知らせがここに届きます。</p>
    </div>

    <?= $this->element('tb_tabbar', ['active' => 'notifications', 'unreadCount' => 0]) ?>
</div>
