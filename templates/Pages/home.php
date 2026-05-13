<?php
/**
 * @var \App\View\AppView $this
 *
 * UI-01 — Home / landing (Phase 6 Calm Gacha rewrite of UI-SPEC v1 §4).
 * Hi-fi: ~/projects/handoff_tamabox/screens/Home.jsx
 */
$this->assign('title', 'ホーム');
?>
<div class="tb-home">
    <div class="tb-paper-grain" aria-hidden="true"></div>

    <section class="tb-home__hero">
        <div class="tb-home__symbol" aria-hidden="true">✦</div>
        <h1 class="tb-home__title">tamabox</h1>
        <p class="tb-home__lead">
            送信者が出るかもしれない、<br>匿名メッセージ箱。
        </p>
    </section>

    <div class="tb-home__divider" aria-hidden="true">
        <span class="tb-home__divider-line"></span>
        <span class="tb-home__divider-label">HOW</span>
        <span class="tb-home__divider-line"></span>
    </div>

    <ol class="tb-step-list">
        <li class="tb-step-list__item">
            <span class="tb-step-list__num">01</span>
            <div>
                <div class="tb-step-list__title">受信箱をつくる</div>
                <div class="tb-step-list__sub">Bluesky でログイン</div>
            </div>
        </li>
        <li class="tb-step-list__item">
            <span class="tb-step-list__num">02</span>
            <div>
                <div class="tb-step-list__title">URL をシェアする</div>
                <div class="tb-step-list__sub">誰でも匿名でメッセージを送れる</div>
            </div>
        </li>
        <li class="tb-step-list__item">
            <span class="tb-step-list__num">03</span>
            <div>
                <div class="tb-step-list__title">稀に、送信者が現れる</div>
                <div class="tb-step-list__sub">SSR 確率で身元が開示されます <span class="tb-step-list__mark">✦</span></div>
            </div>
        </li>
    </ol>

    <div class="tb-home__cta">
        <?= $this->Form->create(null, [
            'url' => ['controller' => 'Auth', 'action' => 'startBluesky'],
            'type' => 'post',
            'class' => 'tb-home__cta-form',
        ]) ?>
            <button type="submit" class="tb-btn tb-btn--primary tb-btn--full">
                <svg class="tb-home__bluesky-mark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M5.8 4.5c2.6 1.9 5.4 5.8 6.2 8 .8-2.2 3.6-6.1 6.2-8 1.9-1.4 4.8-2.4 4.8 1 0 .7-.4 5.6-.6 6.4-.7 2.8-3.5 3.5-6 3-4.4-.7-5.5 1.6-3.1 3.9 4.5 4.4 6.4 9.9 3.7 12.6-2.7 2.6-5.6 1.8-7-1.7-.5-1.2-.7-1.8-.9-3.9 0 0 0 0 0 0s-.4 2.7-.9 3.9c-1.4 3.5-4.3 4.3-7 1.7-2.7-2.7-.8-8.2 3.7-12.6 2.4-2.3 1.3-4.6-3.1-3.9-2.5.5-5.3-.2-6-3-.2-.8-.6-5.7-.6-6.4 0-3.4 2.9-2.4 4.8-1z"/>
                </svg>
                <span>Bluesky でログイン</span>
            </button>
        <?= $this->Form->end() ?>
    </div>
</div>
