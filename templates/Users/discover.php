<?php
/**
 * @var \App\View\AppView $this
 * @var string $activeTab
 *
 * NAV-04 — 発見タブ Empty-state スタブ (Phase 7).
 * Hi-fi: ~/projects/handoff_tamabox/screens/Discover.jsx (Empty subset).
 *
 * No backend / no DB query. The full Discover feed (search / tag filter / featured 箱
 * / list) is deferred to v3 (DISC-01).
 */
$this->assign('title', '発見');
$tags = ['すべて', '創作', '音楽', '研究', '写真', 'ゲーム'];
?>
<div class="tb-dash-screen discover-page">
    <header class="tb-appbar tb-appbar--big">
        <div class="tb-appbar__left">
            <div>
                <div class="tb-appbar__title">発見</div>
                <div class="tb-appbar__sub">箱をみつける</div>
            </div>
        </div>
        <div class="tb-appbar__right"></div>
    </header>

    <div class="tb-dash-screen__body">
        <div class="tb-discover-search" aria-disabled="true" role="search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--tb-ink-3)" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7" />
                <path d="m20 20-3.5-3.5" />
            </svg>
            <span class="tb-discover-search__placeholder">@handle で箱をさがす</span>
        </div>

        <div class="tb-discover-tags" role="list" aria-label="カテゴリ">
            <?php foreach ($tags as $i => $tag): ?>
                <span class="tb-discover-tag<?= $i === 0 ? ' is-pseudo-active' : '' ?>" role="listitem" aria-disabled="true">
                    <?= h($tag) ?>
                </span>
            <?php endforeach; ?>
        </div>

        <div class="tb-empty-state">
            <div class="tb-empty-state__symbol" aria-hidden="true">✦</div>
            <div class="tb-empty-state__title">発見はもうすぐ来ます</div>
            <p class="tb-empty-state__body">他の人の箱を探して送信する機能は、近日公開予定です。今は自分の URL を直接共有してみてください。</p>
        </div>
    </div>

    <?= $this->element('tb_tabbar', ['active' => 'discover', 'unreadCount' => 0]) ?>
</div>
