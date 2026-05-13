<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 *
 * UI-03 — Send done page (Phase 6 Calm Gacha rewrite of UI-SPEC v1 §9).
 * Hi-fi: ~/projects/handoff_tamabox/screens/Done.jsx
 * D-19 (preserved): sender sees no SSR outcome.
 */
$this->assign('title', '送信しました');
$slug = (string)$inbox->slug;
?>
<div class="tb-screen tb-screen--done">
    <header class="tb-appbar tb-appbar--transparent">
        <div></div>
        <button type="button" class="tb-icon-btn" aria-label="閉じる" onclick="history.back()">
            <?= $this->element('icon', ['name' => 'close', 'size' => 22]) ?>
        </button>
    </header>

    <section class="tb-screen__body tb-done__center">
        <div class="tb-done__check" aria-hidden="true">
            <?= $this->element('icon', ['name' => 'check', 'size' => 42]) ?>
        </div>
        <h2 class="tb-done__heading">送信しました</h2>
        <p class="tb-done__body">
            受け手が開封したとき、抽選次第であなたのアカウントが開示されます。
        </p>
        <div class="tb-done__meta" aria-hidden="true">
            <span class="tb-done__meta-rule"></span>
            sent
            <span class="tb-done__meta-rule"></span>
        </div>
    </section>

    <div class="tb-screen__cta tb-done__cta">
        <?= $this->Html->link(
            'もう一件 送る',
            '/' . $slug,
            ['class' => 'tb-btn tb-btn--primary tb-btn--full', 'escape' => false]
        ) ?>
        <?= $this->Html->link(
            '他の受信箱を見る',
            '/',
            ['class' => 'tb-btn tb-btn--ghost tb-btn--full', 'escape' => false]
        ) ?>
    </div>
</div>
