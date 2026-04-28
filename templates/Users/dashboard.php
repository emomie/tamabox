<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var \App\Model\Entity\Inbox $inbox
 * @var \Cake\Datasource\ResultSetInterface|array $messages
 * @var bool $pageOutOfRange
 * @var array{slug: string, base: string}|null $collisionFlash
 * @var array<int, \App\Model\Entity\Block> $blocks
 * @var array<string, bool> $reportedSet
 *
 * UI-SPEC §2 receive list + §3 settings + §5 empty + §10 paginator + §11 flash + D-06 collision.
 * Phase 4 04-01: + ブロック中ユーザー section (§3) + message-row footer (§6) with 削除 + 通報済 badge.
 */
$this->assign('title', 'ダッシュボード');

$handle = '';
if (isset($user->user_identity) && $user->user_identity !== null) {
    $handle = (string)$user->user_identity->handle_cached;
}
$slug = (string)$inbox->slug;
?>
<div class="dashboard-page">
    <header class="dashboard-header">
        <h1>ようこそ、<?= h($handle) ?> さん</h1>
        <p class="text-secondary">あなたの受信箱: <code><?= h('/' . $slug) ?></code></p>
    </header>

    <?php if ($collisionFlash !== null): ?>
        <div class="message info">
            あなたの slug: <strong><?= h($collisionFlash['slug']) ?></strong> になりました(<?= h($collisionFlash['base']) ?> は他のユーザーに使われていたため)
        </div>
    <?php endif; ?>

    <?php if ($pageOutOfRange === true): ?>
        <p>そのページはありません。</p>
        <p><?= $this->Html->link('最初のページに戻る', '/dashboard') ?></p>
    <?php elseif (count($messages) === 0): ?>
        <section class="receive-list-empty">
            <h2>まだ受信したメッセージはありません</h2>
            <p>あなたの inbox URL を Bluesky でシェアしてみましょう: <code>https://tamabox.emomie.com<?= h('/' . $slug) ?></code></p>
        </section>
    <?php else: ?>
        <section class="receive-list">
            <h2>受信メッセージ</h2>
            <?php foreach ($messages as $msg): ?>
                <?php
                $isUnread = $msg->opened_at === null;
                $state = $isUnread ? 'unread' : 'opened';
                $icon = $isUnread ? '●' : '✓';
                $bodyPreview = mb_substr((string)$msg->body, 0, 80);
                if (mb_strlen((string)$msg->body) > 80) {
                    $bodyPreview .= '…';
                }
                $createdIso = $msg->created_at !== null ? $msg->created_at->format(DATE_ATOM) : '';
                $createdDisplay = $msg->created_at !== null ? $msg->created_at->format('Y/m/d H:i') : '';
                $isHit = (bool)$msg->is_ssr;
                $senderHandle = (string)$msg->sender_handle_snapshot;
                $senderAvatar = $msg->sender_avatar_url_snapshot !== null ? (string)$msg->sender_avatar_url_snapshot : '';
                $senderProfileUrl = $msg->sender_profile_url_snapshot !== null ? (string)$msg->sender_profile_url_snapshot : '';
                $senderUserId = (string)$msg->sender_user_id;
                ?>
                <details class="message-row" data-msg-id="<?= h((string)$msg->id) ?>" data-state="<?= h($state) ?>" id="msg-<?= h((string)$msg->id) ?>" <?= $isUnread ? '' : 'open' ?>>
                    <summary class="message-row__head">
                        <span class="message-row__icon" aria-hidden="true"><?= $icon ?></span>
                        <span class="visually-hidden"><?= $isUnread ? '未開封' : '開封済' ?></span>
                        <time class="message-row__time" datetime="<?= h($createdIso) ?>"><?= h($createdDisplay) ?></time>
                        <span class="message-row__preview"><?= h($bodyPreview) ?></span>
                    </summary>
                    <div class="message-row__body">
                        <p><?= nl2br(h((string)$msg->body)) ?></p>

                        <?php if ($isUnread): ?>
                            <?= $this->Form->create(null, [
                                'url' => '/dashboard/messages/' . h((string)$msg->id) . '/open',
                                'type' => 'post',
                                'class' => 'open-form',
                            ]) ?>
                                <button type="submit" class="button primary-button">開封する</button>
                            <?= $this->Form->end() ?>
                        <?php else: ?>
                            <?php if ($isHit): ?>
                                <div class="ssr-reveal" data-outcome="hit">
                                    <div class="ssr-reveal__banner">★ 抽選 hit — 送信者が開示されました</div>
                                    <div class="sender-card">
                                        <?php if ($senderAvatar !== ''): ?>
                                            <img class="sender-card__avatar"
                                                 src="<?= h($senderAvatar) ?>"
                                                 alt="<?= h($senderHandle) ?>"
                                                 width="64" height="64"
                                                 onerror="this.src='/img/default-avatar.svg'">
                                        <?php else: ?>
                                            <img class="sender-card__avatar"
                                                 src="/img/default-avatar.svg"
                                                 alt="<?= h($senderHandle) ?>"
                                                 width="64" height="64">
                                        <?php endif; ?>
                                        <a class="sender-card__handle" href="https://bsky.app/profile/<?= h($senderHandle) ?>">@<?= h($senderHandle) ?></a>
                                        <?php if ($senderProfileUrl !== ''): ?>
                                            <a class="button button-clear"
                                               href="<?= h($senderProfileUrl) ?>"
                                               target="_blank"
                                               rel="noopener">Bluesky プロフィールを見る</a>
                                        <?php endif; ?>
                                        <?= $this->Form->create(null, [
                                            'url' => '/report/' . h((string)$msg->id),
                                            'type' => 'post',
                                            'class' => 'inline',
                                        ]) ?>
                                            <button type="submit" class="button button-clear button-destructive">通報する</button>
                                        <?= $this->Form->end() ?>
                                        <?= $this->Form->create(null, [
                                            'url' => '/block/' . h($senderUserId),
                                            'type' => 'post',
                                            'class' => 'inline',
                                        ]) ?>
                                            <button type="submit" class="button button-clear button-destructive">このユーザーをブロック</button>
                                        <?= $this->Form->end() ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="ssr-reveal__miss text-secondary">★ 抽選 miss(送信者は匿名のまま)</p>
                            <?php endif; ?>
                            <div class="message-row__footer">
                                <?= $this->Form->create(null, [
                                    'url' => '/dashboard/messages/' . h((string)$msg->id) . '/delete',
                                    'type' => 'post',
                                    'class' => 'inline',
                                    'onsubmit' => "return confirm('このメッセージを削除しますか?(削除後は元に戻せません)');",
                                ]) ?>
                                    <button type="submit" class="button button-clear button-destructive">削除</button>
                                <?= $this->Form->end() ?>
                                <?php if (isset($reportedSet[(string)$msg->id])): ?>
                                    <span class="report-badge" aria-label="このメッセージは通報済みです">通報済</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>

        <nav class="pagination" aria-label="受信一覧ページ送り">
            <?= $this->Paginator->numbers() ?>
        </nav>
    <?php endif; ?>

    <aside class="dashboard-settings">
        <h2>受信箱設定</h2>
        <?= $this->element('inbox_settings_form', ['inbox' => $inbox]) ?>
    </aside>

    <?= $this->element('block_list', ['blocks' => $blocks]) ?>
</div>
