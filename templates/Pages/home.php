<?php
/**
 * @var \App\View\AppView $this
 *
 * Phase 2 home / login-CTA page (UI-SPEC §4).
 */
$this->assign('title', 'ホーム');
?>
<div class="home-page">
    <h1 class="display-heading">tamabox</h1>
    <p class="text-secondary home-lead">
        Bluesky アカウントでログインして、あなたの受信箱をはじめましょう。
    </p>

    <?= $this->Form->create(null, [
        'url' => ['controller' => 'Auth', 'action' => 'startBluesky'],
        'type' => 'post',
        'class' => 'login-form',
    ]) ?>
        <button type="submit" class="button primary-button">Bluesky でログイン</button>
    <?= $this->Form->end() ?>

    <section class="home-explainer text-secondary">
        <p>tamabox は確率で送信者の名前がバレる、匿名メッセージ箱です。</p>
        <p>
            ログイン後、あなた専用の受信箱 URL を Bluesky でシェアして、
            メッセージを集めることができます。
            送信者は同意のうえで送信し、受信箱の確率設定に応じて送信者の Bluesky アカウントが開示されることがあります。
        </p>
    </section>
</div>
