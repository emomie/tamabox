<?php
/**
 * @var \App\View\AppView $this
 */
$identity = $this->getRequest()->getAttribute('identity');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= h($this->fetch('title')) ?> — tamabox
    </title>
    <?= $this->Html->meta('icon') ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500&family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
    <?= $this->Html->css(['normalize.min', 'milligram.min', 'tokens', 'colors_and_type', 'tamabox']) ?>
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <header class="header-bar">
        <div class="header-bar-title">
            <a href="<?= $this->Url->build('/') ?>">tamabox</a>
        </div>
        <?php if ($identity) : ?>
            <div class="header-bar-right">
                <?= $this->element('avatar_handle_chip', ['identity' => $identity]) ?>
                <form method="post" action="<?= $this->Url->build('/oauth/logout') ?>" class="logout-form">
                    <?= $this->Form->hidden('_csrfToken', [
                        'value' => $this->getRequest()->getAttribute('csrfToken'),
                    ]) ?>
                    <button type="submit" class="button button-clear logout-btn">ログアウト</button>
                </form>
            </div>
        <?php endif; ?>
    </header>
    <main class="main">
        <div class="container">
            <?= $this->Flash->render() ?>
            <?= $this->fetch('content') ?>
        </div>
    </main>
</body>
</html>
