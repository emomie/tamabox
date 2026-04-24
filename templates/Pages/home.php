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
</div>
