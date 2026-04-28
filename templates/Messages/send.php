<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 * @var bool $isAuthenticated
 * @var bool $isOwnInbox
 * @var bool $isBlocked
 * @var string $restoredBody
 *
 * Send page — UI-SPEC §1 (Send Form contract).
 * D-13 unauthenticated POST flow + D-14/D-15 consent + D-16/D-17 body validation + D-38 self-inbox notice.
 * Phase 4 D-05/D-06: when $isBlocked, render error banner + disabled form (UX preserved, action prevented).
 */
// display_name lives on the related User (Inboxes belongsTo Users, loaded via contain).
$displayName = $inbox->user !== null ? (string)$inbox->user->display_name : '';
$this->assign('title', h($displayName) . ' の受信箱');
$slug = (string)$inbox->slug;
$probabilityPct = (int)round((float)$inbox->ssr_probability * 100);
$welcomeMessage = $inbox->welcome_message;
$isAccepting = (bool)$inbox->is_accepting;
// Phase 4 D-05/D-06: pre-compute blocked-form modifier flags for the disabled UX.
$blockedFlag = isset($isBlocked) && $isBlocked === true;
$blockedDisabledAttr = $blockedFlag ? 'disabled' : '';
$blockedFormClassMod = $blockedFlag ? ' is-disabled' : '';
?>
<div class="send-form-page">
    <header class="inbox-header">
        <h1><?= h($displayName) ?> の受信箱</h1>
    </header>

    <?php if ($blockedFlag): ?>
        <div class="error-banner" role="status">この受信箱には送信できません</div>
    <?php endif; ?>

    <?php if ($isOwnInbox): ?>
        <p class="inbox-self-notice">これはあなたの受信箱です。<a href="/dashboard">/dashboard で受信一覧</a></p>
    <?php endif; ?>

    <?php if ($welcomeMessage !== null && $welcomeMessage !== ''): ?>
        <section class="welcome-message">
            <h2><?= h($displayName) ?> から:</h2>
            <p><?= nl2br(h((string)$welcomeMessage)) ?></p>
        </section>
    <?php endif; ?>

    <?php if (!$isAccepting): ?>
        <p class="empty-state">現在この受信箱は受け付けていません</p>
        <p class="text-secondary">受け取り再開をお待ちください。</p>
    <?php else: ?>
        <?= $this->Form->create(null, [
            'url' => '/' . h($slug),
            'type' => 'post',
            'class' => 'send-form' . $blockedFormClassMod,
        ]) ?>
            <textarea
                name="body" <?= $blockedDisabledAttr ?>
                required
                maxlength="2000"
                rows="6"
                aria-describedby="body-counter body-help"
                class="send-form__body"
            ><?= h($restoredBody) ?></textarea>
            <p id="body-help" class="text-secondary">最大 2000 文字、改行可、絵文字対応</p>
            <p id="body-counter" class="char-counter" aria-live="polite">
                <span data-counter><?= mb_strlen($restoredBody) ?></span> / 2000
            </p>

            <label class="consent-label<?= $blockedFormClassMod ?>">
                <input type="checkbox" name="consent" value="1" required <?= $blockedDisabledAttr ?>>
                このメッセージは抽選で送信者の Bluesky アカウントが開示される可能性があります(現在の確率: <strong><?= $probabilityPct ?>%</strong>)
            </label>

            <?php if ($isAuthenticated): ?>
                <button type="submit" class="button primary-button" <?= $blockedDisabledAttr ?>>送信する</button>
            <?php else: ?>
                <button type="submit" class="button primary-button" <?= $blockedDisabledAttr ?>>Bluesky でログインして送信</button>
            <?php endif; ?>
        <?= $this->Form->end() ?>
    <?php endif; ?>
</div>
