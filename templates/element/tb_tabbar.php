<?php
/**
 * @var \App\View\AppView $this
 * @var string|null $active        One of 'inbox' | 'discover' | 'notifications' | 'settings'.
 * @var int|null    $unreadCount   Non-negative integer; renders unread dot on inbox tab when > 0.
 *
 * NAV-01 / NAV-02 — Tab bar element for the 4 dashboard tabs.
 * Hi-fi: ~/projects/handoff_tamabox/components.jsx lines 116-125 (TbTabBar React).
 *
 * Base CSS lives in webroot/css/tokens.css (.tb-tabbar) and tamabox.css
 * (.tb-unread-dot + §H.1 Phase 7 tweaks).
 *
 * Element parameters are deterministic — no DB / no controller dependency beyond
 * the two view variables. Whitelist-validated to defang typos.
 */
$active = isset($active) && is_string($active) ? $active : '';
$validTabs = ['inbox', 'discover', 'notifications', 'settings'];
if (!in_array($active, $validTabs, true)) {
    $active = '';
}
$unreadCount = isset($unreadCount) ? max(0, (int)$unreadCount) : 0;

$items = [
    ['id' => 'inbox',         'href' => '/dashboard',                'iconName' => 'inbox',   'label' => '受信'],
    ['id' => 'discover',      'href' => '/dashboard/discover',       'iconName' => 'compass', 'label' => '発見'],
    ['id' => 'notifications', 'href' => '/dashboard/notifications',  'iconName' => 'bell',    'label' => '通知'],
    ['id' => 'settings',      'href' => '/dashboard/settings',       'iconName' => 'user',    'label' => '設定'],
];
?>
<nav class="tb-tabbar" role="tablist" aria-label="ダッシュボードタブ" style="--cols: 4;">
    <?php foreach ($items as $item): ?>
        <?php $isActive = $item['id'] === $active; ?>
        <a class="tb-tabbar__item<?= $isActive ? ' is-active' : '' ?>"
           href="<?= h($item['href']) ?>"
           role="tab"<?php if ($isActive): ?> aria-current="page"<?php endif; ?>>
            <span class="tb-tabbar__icon">
                <?= $this->element('icon', ['name' => $item['iconName'], 'size' => 22]) ?>
                <?php if ($item['id'] === 'inbox' && $unreadCount > 0): ?>
                    <span class="tb-unread-dot" aria-label="未読 <?= $unreadCount ?> 件"></span>
                <?php endif; ?>
            </span>
            <span><?= h($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
