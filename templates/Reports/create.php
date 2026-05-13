<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Message $message
 *
 * UI-05 — Report message form (Phase 6 Calm Gacha rewrite of Phase 4 D-09 / D-10 / D-11).
 * Hi-fi: ~/projects/handoff_tamabox/screens/ReportDelete.jsx (Report lines 3-87)
 *
 * Class "report-form" is preserved on the Form element because
 * ReportsControllerTest:75 asserts on it. Visual styling lives on .tb-report-form.
 */
$this->assign('title', 'メッセージを通報する');
$bodyExcerptRaw = (string)$message->body;
$bodyExcerpt = mb_substr($bodyExcerptRaw, 0, 200);
$bodyTruncated = mb_strlen($bodyExcerptRaw) > 200;
$reasons = [
    ['id' => 'harassment', 't' => 'ハラスメント・誹謗中傷', 'd' => '差別・脅迫・人格攻撃を含む'],
    ['id' => 'spam',       't' => 'スパム',                'd' => '宣伝・繰り返し送信'],
    ['id' => 'illegal',    't' => '違法・有害コンテンツ',   'd' => '犯罪・違法に関わる内容'],
    ['id' => 'other',      't' => 'その他',                'd' => '上記に当てはまらない問題(必要に応じ詳細記入)'],
];
?>
<div class="tb-screen tb-screen--report">
    <header class="tb-appbar">
        <div class="tb-appbar__left">
            <button type="button" class="tb-icon-btn" aria-label="閉じる" onclick="history.back()">
                <?= $this->element('icon', ['name' => 'close', 'size' => 20]) ?>
            </button>
            <span class="tb-appbar__title">メッセージを通報</span>
        </div>
        <div></div>
    </header>

    <?= $this->Form->create(null, [
        'url' => '/report/' . (string)$message->id,
        'type' => 'post',
        'class' => 'report-form tb-report-form',
    ]) ?>
        <section class="tb-screen__body tb-report__body">
            <div class="tb-card-soft tb-report__excerpt">
                <div class="tb-section-label">対象メッセージ</div>
                <p class="tb-report__excerpt-body"><?= h($bodyExcerpt) ?><?= $bodyTruncated ? '…' : '' ?></p>
            </div>

            <fieldset class="tb-report__reasons">
                <legend class="tb-section-label">
                    理由 <span class="tb-section-label__required">*</span>
                </legend>
                <div class="tb-report__tiles">
                    <?php foreach ($reasons as $r): ?>
                        <label class="tb-radio-tile">
                            <input type="radio" name="reason" value="<?= h($r['id']) ?>" required class="tb-radio-tile__input">
                            <span class="tb-radio-tile__mark" aria-hidden="true"><span></span></span>
                            <span class="tb-radio-tile__body">
                                <span class="tb-radio-tile__title"><?= h($r['t']) ?></span>
                                <span class="tb-radio-tile__sub"><?= h($r['d']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset class="tb-report__detail">
                <legend class="tb-section-label">
                    詳細 <span class="tb-section-label__optional">任意</span>
                </legend>
                <textarea name="detail" maxlength="1000" rows="4" class="tb-input" placeholder="補足があれば書いてください…"></textarea>
            </fieldset>
        </section>

        <div class="tb-screen__cta tb-report__cta">
            <a href="/dashboard" class="tb-btn tb-btn--quiet tb-btn--full">キャンセル</a>
            <button type="submit" class="tb-btn tb-btn--danger tb-btn--full">通報する</button>
        </div>
    <?= $this->Form->end() ?>
</div>
