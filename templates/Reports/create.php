<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Message $message  passed by ReportsController::create
 *
 * UI-SPEC §4 — Report form page (Phase 4 D-09 / D-10 / D-11).
 */
$this->assign('title', 'メッセージを通報する');
$bodyExcerptRaw = (string)$message->body;
$bodyExcerpt = mb_substr($bodyExcerptRaw, 0, 200);
$bodyTruncated = mb_strlen($bodyExcerptRaw) > 200;
?>
<div class="report-page">
    <header class="report-page__header">
        <h1>このメッセージを通報する</h1>
        <p class="text-secondary">通報内容は運営が確認します。重複通報はできません。</p>
    </header>

    <section class="report-page__msg-excerpt">
        <p class="text-secondary">対象メッセージ:</p>
        <blockquote><?= h($bodyExcerpt) ?><?= $bodyTruncated ? '…' : '' ?></blockquote>
    </section>

    <?= $this->Form->create(null, [
        'url' => '/report/' . h((string)$message->id),
        'type' => 'post',
        'class' => 'report-form',
    ]) ?>
        <fieldset class="report-form__reasons">
            <legend>通報の理由を 1 つ選んでください</legend>
            <label class="report-form__radio-row">
                <input type="radio" name="reason" value="harassment" required>
                <span>嫌がらせ・誹謗中傷</span>
            </label>
            <label class="report-form__radio-row">
                <input type="radio" name="reason" value="spam">
                <span>スパム・宣伝</span>
            </label>
            <label class="report-form__radio-row">
                <input type="radio" name="reason" value="illegal">
                <span>違法・有害コンテンツ</span>
            </label>
            <label class="report-form__radio-row">
                <input type="radio" name="reason" value="other">
                <span>その他(自由記述で説明)</span>
            </label>
        </fieldset>

        <fieldset class="report-form__detail">
            <legend>詳細(その他選択時は必須・最大 1000 文字)</legend>
            <textarea name="detail" maxlength="1000" rows="5"></textarea>
        </fieldset>

        <div class="report-form__actions">
            <button type="submit" class="button primary-button">通報を送信する</button>
            <a href="/dashboard" class="button button-clear">キャンセル</a>
        </div>
    <?= $this->Form->end() ?>
</div>
