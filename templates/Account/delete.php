<?php
/**
 * @var \App\View\AppView $this
 *
 * UI-SPEC §7 — Account deletion page (Phase 4 D-23 / D-24 / D-27).
 */
$this->assign('title', '退会の手続き');
?>
<div class="account-delete-page">
    <header class="account-delete-page__header">
        <h1>退会の手続き</h1>
    </header>

    <section class="account-delete-page__notice">
        <p>退会するとあなたの受信箱は使えなくなります。</p>
        <p>あなたが過去に送信したメッセージは、受け手側の画面ではあなたの当時の handle・avatar が記録されたまま残ります(MOD-03)。</p>
        <p>退会後、あなたの slug は他の人に再割り当てされません。</p>
    </section>

    <?= $this->Form->create(null, [
        'url' => '/account/delete',
        'type' => 'post',
        'class' => 'account-delete-form',
    ]) ?>
        <label class="account-delete-form__consent">
            <input type="checkbox" name="confirm_delete" value="1" required>
            <span>上記の内容を理解した上で、退会します</span>
        </label>
        <div class="account-delete-form__actions">
            <button type="submit" class="button primary-button button-destructive-bg">退会する</button>
            <a href="/dashboard" class="button button-clear">ダッシュボードに戻る</a>
        </div>
    <?= $this->Form->end() ?>
</div>
