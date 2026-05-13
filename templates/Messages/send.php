<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 * @var bool $isAuthenticated
 * @var bool $isOwnInbox
 * @var bool $isBlocked
 * @var string $restoredBody
 *
 * UI-02 — Send form (Phase 6 Calm Gacha rewrite of UI-SPEC v1 §1).
 * Hi-fi: ~/projects/handoff_tamabox/screens/Send.jsx
 *
 * Preserved behaviors:
 * - D-13 unauthenticated POST flow (button label differs by $isAuthenticated)
 * - D-14/D-15 consent checkbox required
 * - D-16/D-17 body validation (maxlength 2000, required)
 * - D-38 self-inbox notice
 * - Phase 4 D-05/D-06 blocked-user disabled form + error banner
 * - is_accepting=false → form hidden, copy shown
 */
$displayName = $inbox->user !== null ? (string)$inbox->user->display_name : '';
// L-01: layout escapes title with h() on render; pre-escaping here would double-encode.
$this->assign('title', $displayName . ' の受信箱');
$slug = (string)$inbox->slug;
$probabilityPct = (int)round((float)$inbox->ssr_probability * 100);
$welcomeMessage = $inbox->welcome_message;
$isAccepting = (bool)$inbox->is_accepting;
$blockedFlag = isset($isBlocked) && $isBlocked === true;
$blockedDisabledAttr = $blockedFlag ? 'disabled' : '';
$blockedFormClassMod = $blockedFlag ? ' is-disabled' : '';
$initial = $displayName !== '' ? mb_substr($displayName, 0, 1) : '?';
?>
<div class="tb-screen tb-screen--send">
    <header class="tb-appbar">
        <div class="tb-appbar__left">
            <button type="button" class="tb-icon-btn" aria-label="戻る" onclick="history.back()">
                <?= $this->element('icon', ['name' => 'back', 'size' => 22]) ?>
            </button>
            <span class="tb-appbar__title">メッセージを送る</span>
        </div>
        <div></div>
    </header>

    <?php if ($isAccepting): ?>
        <?= $this->Form->create(null, [
            'url' => '/' . $slug,
            'type' => 'post',
            'class' => 'send-form tb-send-form' . $blockedFormClassMod,
        ]) ?>
    <?php endif; ?>

    <section class="tb-screen__body tb-send__body">
        <div class="tb-card tb-send__receiver">
            <span class="tb-send__avatar" aria-hidden="true"><?= h($initial) ?></span>
            <div class="tb-send__receiver-info">
                <div class="tb-send__receiver-name"><?= h($displayName) ?></div>
                <div class="tb-mono tb-send__receiver-slug">@<?= h($slug) ?></div>
            </div>
            <span class="tb-chip tb-chip--warm tb-mono">SSR&nbsp;<?= $probabilityPct ?>%</span>
        </div>

        <?php if ($isOwnInbox): ?>
            <div class="tb-card-soft tb-send__notice">
                これはあなたの受信箱です。<a href="/dashboard">ダッシュボードで受信一覧を見る</a>
            </div>
        <?php endif; ?>

        <?php if ($blockedFlag): ?>
            <div class="error-banner tb-send__error" role="status">この受信箱には送信できません</div>
        <?php endif; ?>

        <?php if ($welcomeMessage !== null && $welcomeMessage !== ''): ?>
            <section class="tb-letter tb-send__welcome" aria-label="受信者からの一言">
                <div class="tb-send__welcome-label">受信者から</div>
                <p class="tb-send__welcome-body"><?= nl2br(h((string)$welcomeMessage)) ?></p>
            </section>
        <?php endif; ?>

        <?php if (!$isAccepting): ?>
            <div class="tb-card-soft tb-send__closed">
                <p class="tb-send__closed-title">現在この受信箱は受け付けていません</p>
                <p class="tb-send__closed-sub">受け取り再開をお待ちください。</p>
            </div>
        <?php else: ?>
            <div class="tb-send__textarea-block">
                <label for="send-body" class="tb-label">メッセージ</label>
                <textarea
                    id="send-body"
                    name="body" <?= $blockedDisabledAttr ?>
                    required
                    maxlength="2000"
                    rows="7"
                    aria-describedby="body-counter body-help"
                    class="tb-input send-form__body"
                    placeholder="ここに書きます…"
                ><?= h($restoredBody) ?></textarea>
                <div class="tb-send__meta">
                    <span id="body-help">改行・絵文字 OK · 最大 2000 文字</span>
                    <span id="body-counter" class="tb-mono char-counter" aria-live="polite">
                        <span data-counter><?= mb_strlen($restoredBody) ?></span> / 2000
                    </span>
                </div>
            </div>

            <label class="tb-card-soft tb-send__consent consent-label<?= $blockedFormClassMod ?>">
                <input type="checkbox" name="consent" value="1" required <?= $blockedDisabledAttr ?> class="tb-send__consent-input">
                <span class="tb-send__consent-check" aria-hidden="true">
                    <?= $this->element('icon', ['name' => 'check', 'size' => 14]) ?>
                </span>
                <span class="tb-send__consent-body">
                    <b class="tb-send__consent-pct"><?= $probabilityPct ?>%</b> の確率で、私の Bluesky アカウントが受信者に開示される事に同意します。
                </span>
            </label>
        <?php endif; ?>
    </section>

    <?php if ($isAccepting): ?>
        <div class="tb-screen__cta tb-send__cta">
            <button type="submit"
                    class="tb-btn tb-btn--primary tb-btn--full"
                    <?= $blockedDisabledAttr ?>>
                <span><?= $isAuthenticated ? '送信する' : 'Bluesky でログインして送信' ?></span>
                <?= $this->element('icon', ['name' => 'send', 'size' => 16]) ?>
            </button>
        </div>
        <?= $this->Form->end() ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var ta = document.getElementById('send-body');
    var counter = document.querySelector('[data-counter]');
    if (!ta || !counter) { return; }
    function update() { counter.textContent = (ta.value || '').length; }
    ta.addEventListener('input', update);
    update();
})();
</script>
