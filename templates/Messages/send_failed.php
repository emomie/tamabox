<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 * @var string $restoredBody
 *
 * EDGE-04 — Phase 8 D-10: rendered when MessagesController::processSend
 * catches RuntimeException from MessagesTable::sendMessage. The body the
 * user attempted to send is preserved (read-only view) so they can retry
 * via the CTA → /{slug} (GET) and re-enter.
 *
 * Hi-fi: ~/projects/handoff_tamabox/screens/SendErrors.jsx :: SendFailed (lines 305-414)
 *
 * Out of scope (v3): "下書き保存" localStorage button, net_err code strip,
 * auto-retry counter. The retry path here is a manual GET re-navigation.
 */
$displayName = $inbox->user !== null ? (string)$inbox->user->display_name : '';
$slug = (string)$inbox->slug;
$probabilityPct = (int)round((float)$inbox->ssr_probability * 100);
$initial = $displayName !== '' ? mb_substr($displayName, 0, 1) : '?';
$this->assign('title', $displayName . ' の受信箱');
?>
<div class="tb-screen tb-screen--send tb-send-failed">
    <header class="tb-appbar">
        <div class="tb-appbar__left">
            <a href="/<?= h($slug) ?>" class="tb-icon-btn" aria-label="戻る">
                <?= $this->element('icon', ['name' => 'back', 'size' => 22]) ?>
            </a>
            <span class="tb-appbar__title">メッセージを送る</span>
        </div>
        <div></div>
    </header>

    <div class="tb-send-failed__banner" role="alert">
        <span class="tb-send-failed__symbol" aria-hidden="true">!</span>
        <div class="tb-flex-grow">
            <h2 class="tb-send-failed__title">送信できませんでした</h2>
            <div class="tb-send-failed__sub">通信が不安定なようです。本文はそのまま残してあります。</div>
        </div>
        <a href="/<?= h($slug) ?>" class="tb-send-failed__retry">再送信</a>
    </div>

    <section class="tb-screen__body tb-send__body">
        <div class="tb-card tb-send__receiver">
            <span class="tb-send__avatar" aria-hidden="true"><?= h($initial) ?></span>
            <div class="tb-send__receiver-info">
                <div class="tb-send__receiver-name"><?= h($displayName) ?></div>
                <div class="tb-mono tb-send__receiver-slug">@<?= h($slug) ?></div>
            </div>
            <span class="tb-chip tb-chip--warm tb-mono">SSR&nbsp;<?= $probabilityPct ?>%</span>
        </div>

        <div class="tb-send__textarea-block">
            <label class="tb-label">メッセージ</label>
            <div class="tb-input tb-send-failed__body-preserved" aria-label="入力された本文">
                <?= nl2br(h((string)$restoredBody)) ?>
            </div>
        </div>
    </section>

    <div class="tb-screen__cta tb-send__cta">
        <a href="/<?= h($slug) ?>" class="tb-btn tb-btn--primary tb-btn--full">
            <span>もう一度送信</span>
            <?= $this->element('icon', ['name' => 'send', 'size' => 16]) ?>
        </a>
    </div>
</div>
