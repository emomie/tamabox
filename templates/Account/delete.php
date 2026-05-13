<?php
/**
 * @var \App\View\AppView $this
 *
 * UI-06 — Account delete page (Phase 6 Calm Gacha rewrite of Phase 4 D-23 / D-24 / D-27).
 * Hi-fi: ~/projects/handoff_tamabox/screens/ReportDelete.jsx (Delete component lines 90-163)
 */
$this->assign('title', '退会の手続き');
$consequences = [
    ['t' => '受信メッセージ',  's' => '受け取った全メッセージが削除されます'],
    ['t' => '送信履歴',        's' => '受け手側にはあなたの当時の handle・avatar が残ります (MOD-03)'],
    ['t' => '箱の URL',        's' => 'あなたの slug は再割り当てされません'],
];
?>
<div class="tb-screen tb-screen--delete">
    <header class="tb-appbar">
        <div class="tb-appbar__left">
            <button type="button" class="tb-icon-btn" aria-label="戻る" onclick="history.back()">
                <?= $this->element('icon', ['name' => 'back', 'size' => 22]) ?>
            </button>
            <span class="tb-appbar__title">退会</span>
        </div>
        <div></div>
    </header>

    <?= $this->Form->create(null, [
        'url' => '/account/delete',
        'type' => 'post',
        'class' => 'tb-delete__form',
    ]) ?>
        <section class="tb-screen__body tb-delete__body">
            <div class="tb-delete__heading-block">
                <h2 class="tb-delete__heading">tamabox から退会</h2>
                <p class="tb-delete__lead">
                    退会すると以下のデータの扱いが確定します。元に戻すことはできません。
                </p>
            </div>

            <div class="tb-card tb-delete__consequences">
                <?php foreach ($consequences as $i => $item): ?>
                    <div class="tb-delete__row<?= $i === 0 ? ' tb-delete__row--first' : '' ?>">
                        <span class="tb-delete__mark" aria-hidden="true">×</span>
                        <div>
                            <div class="tb-delete__row-title"><?= h($item['t']) ?></div>
                            <div class="tb-delete__row-sub"><?= h($item['s']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <label class="tb-card-soft tb-delete__consent">
                <input type="checkbox" name="confirm_delete" value="1" required class="tb-delete__consent-input">
                <span class="tb-delete__consent-check" aria-hidden="true">
                    <?= $this->element('icon', ['name' => 'check', 'size' => 14]) ?>
                </span>
                <span class="tb-delete__consent-body">
                    上記の内容を理解し、<b>取り消せない</b>ことに同意します。
                </span>
            </label>
        </section>

        <div class="tb-screen__cta tb-delete__cta">
            <button type="submit" class="tb-btn tb-btn--danger tb-btn--full">退会する</button>
            <a href="/dashboard" class="tb-btn tb-btn--quiet tb-btn--full">キャンセル</a>
        </div>
    <?= $this->Form->end() ?>
</div>
