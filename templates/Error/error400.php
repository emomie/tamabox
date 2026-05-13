<?php
/**
 * @var \App\View\AppView $this
 * @var \Cake\Database\StatementInterface $error
 * @var string $message
 * @var string $url
 *
 * EDGE-01 — 404 (and any 400-class) error template.
 * Hi-fi: ~/projects/handoff_tamabox/screens/SendErrors.jsx :: SendNotFound (lines 9-64)
 *
 * Phase 8 D-01: every CakePHP 400-class error renders the SendNotFound hi-fi
 * (single-pattern simplification). The realistic user-facing source is
 * MessagesController::send throwing NotFoundException for unknown slugs.
 *
 * Dev-mode (Configure debug=true) still uses the dev_error layout for
 * stack-trace inspection — preserved from the bake default.
 */
use Cake\Core\Configure;
use Cake\Error\Debugger;

$this->layout = 'error';

if (Configure::read('debug')) :
    $this->layout = 'dev_error';

    $this->assign('title', $message);
    $this->assign('templateName', 'error400.php');

    $this->start('file');
?>
<?php if (!empty($error->queryString)) : ?>
    <p class="notice">
        <strong>SQL Query: </strong>
        <?= h($error->queryString) ?>
    </p>
<?php endif; ?>
<?php if (!empty($error->params)) : ?>
    <strong>SQL Query Params: </strong>
    <?php Debugger::dump($error->params) ?>
<?php endif; ?>

<?php
    echo $this->element('auto_table_warning');

    $this->end();
endif;

$this->assign('title', '箱が見つかりません');
?>
<div class="tb-error-screen">
    <header class="tb-appbar">
        <div class="tb-appbar__left">
            <a href="/" class="tb-icon-btn" aria-label="戻る">
                <?= $this->element('icon', ['name' => 'back', 'size' => 22]) ?>
            </a>
            <span class="tb-appbar__title">メッセージを送る</span>
        </div>
        <div></div>
    </header>

    <div class="tb-error-screen__body">
        <div class="tb-error-screen__symbol" aria-hidden="true">?</div>
        <h2 class="tb-error-screen__title">この箱は見つかりません</h2>
        <?php // $url is pre-escaped by WebExceptionRenderer::_outputMessage (vendor verified). ?>
        <?php if (isset($url) && $url !== ''): ?>
            <div class="tb-error-screen__url-pill tb-mono"><?= $url ?></div>
        <?php endif; ?>
        <p class="tb-error-screen__body-text">
            URL の入力ミス、または箱が<br>削除された可能性があります。
        </p>
        <div class="tb-error-screen__cta">
            <a href="/" class="tb-btn tb-btn--primary tb-btn--full">tamabox に戻る</a>
            <a href="javascript:history.back()" class="tb-error-screen__quiet-link">URL を確認しなおす</a>
        </div>
    </div>
</div>
