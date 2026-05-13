<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 * @var \App\Model\Entity\Inbox $inbox
 * @var \Cake\Datasource\ResultSetInterface|array $messages
 * @var bool $pageOutOfRange
 * @var array{slug: string, base: string}|null $collisionFlash
 * @var array<int, \App\Model\Entity\Block> $blocks  // unused in this template (moved to /dashboard/settings)
 * @var array<string, bool> $reportedSet
 * @var int $unreadCount
 * @var string $activeTab
 *
 * NAV-03 — Dashboard 受信タブ (Phase 7 Calm Gacha rewrite).
 * Hi-fi: ~/projects/handoff_tamabox/screens/Dashboard.jsx
 *
 * Settings aside + block_list element previously rendered here now live in
 * /dashboard/settings (templates/Inboxes/settings.php) per Phase 7 D-02 / D-04.
 *
 * Preserved Phase 4 assertion substrings: data-state, ★ 抽選 hit / miss, profile URL,
 * rel="noopener", action="/dashboard/messages/{id}/open", 開封する, ようこそ.
 */
$this->assign('title', 'ダッシュボード');

$handle = '';
if (isset($user->user_identity) && $user->user_identity !== null) {
    $handle = (string)$user->user_identity->handle_cached;
}
$slug = (string)$inbox->slug;
$ssrPct = (int)round((float)$inbox->ssr_probability * 100);
if (is_countable($messages)) {
    $totalCount = count($messages);
} else {
    $totalCount = 0;
    foreach ($messages as $_dummy) {
        $totalCount++;
    }
}
$initialChar = $handle !== '' ? mb_substr($handle, 0, 1) : '';
?>
<div class="tb-dash-screen dashboard-page">
    <header class="tb-appbar tb-appbar--big">
        <div class="tb-appbar__left">
            <div>
                <div class="tb-appbar__title">受信箱</div>
            </div>
        </div>
        <div class="tb-appbar__right">
            <a href="/dashboard/notifications" class="tb-icon-btn" aria-label="通知">
                <?= $this->element('icon', ['name' => 'bell', 'size' => 22]) ?>
            </a>
            <?php if ($initialChar !== ''): ?>
                <span class="tb-dash-avatar" aria-hidden="true"><?= h($initialChar) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div class="tb-dash-screen__body">
        <p class="visually-hidden">ようこそ、<?= h($handle) ?> さん</p>

        <?php if ($collisionFlash !== null): ?>
            <div class="tb-card-soft tb-dash-collision">
                あなたの slug: <strong><?= h($collisionFlash['slug']) ?></strong> になりました(<?= h($collisionFlash['base']) ?> は他のユーザーに使われていたため)
            </div>
        <?php endif; ?>

        <div class="tb-dash-box">
            <div style="flex:1; min-width:0;">
                <div class="tb-dash-box__label">あなたの箱</div>
                <div class="tb-mono tb-dash-box__url">tamabox.emomie.com<?= h('/' . $slug) ?></div>
            </div>
            <span class="tb-chip tb-chip--warm">SSR <?= $ssrPct ?>%</span>
        </div>

        <div class="tb-dash-counts">
            <div style="display:flex; align-items:baseline; gap:10px;">
                <span class="tb-dash-counts__title">受信</span>
                <span class="tb-mono tb-dash-counts__num"><?= $totalCount ?> 件</span>
            </div>
            <?php if ($unreadCount > 0): ?>
                <span class="tb-dash-counts__pill">未開封 <?= $unreadCount ?></span>
            <?php endif; ?>
        </div>

        <?php if ($pageOutOfRange === true): ?>
            <div class="tb-card-soft">
                <p>そのページはありません。</p>
                <p><?= $this->Html->link('最初のページに戻る', '/dashboard') ?></p>
            </div>
        <?php elseif ($totalCount === 0): ?>
            <section class="tb-card-soft receive-list-empty">
                <h2>まだ受信したメッセージはありません</h2>
                <p>あなたの inbox URL を Bluesky でシェアしてみましょう: <code>https://tamabox.emomie.com<?= h('/' . $slug) ?></code></p>
            </section>
        <?php else: ?>
            <section class="tb-receive-list receive-list">
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $isUnread = $msg->opened_at === null;
                    $state = $isUnread ? 'unread' : 'opened';
                    $bodyPreview = mb_substr((string)$msg->body, 0, 80);
                    if (mb_strlen((string)$msg->body) > 80) {
                        $bodyPreview .= '…';
                    }
                    $createdIso = $msg->created_at !== null ? $msg->created_at->format(DATE_ATOM) : '';
                    $createdShort = $msg->created_at !== null ? $msg->created_at->format('n/d') : '';
                    $isHit = (bool)$msg->is_ssr;
                    $senderHandle = (string)$msg->sender_handle_snapshot;
                    $senderAvatar = $msg->sender_avatar_url_snapshot !== null ? (string)$msg->sender_avatar_url_snapshot : '';
                    $senderProfileUrl = $msg->sender_profile_url_snapshot !== null ? (string)$msg->sender_profile_url_snapshot : '';
                    $senderUserId = (string)$msg->sender_user_id;
                    $dotMod = $isUnread ? 'unread' : ($isHit ? 'hit' : 'miss');
                    $fromText = (!$isUnread && $isHit && $senderHandle !== '') ? '@' . $senderHandle : '匿名';
                    $fromMod = (!$isUnread && $isHit) ? 'hit' : 'anon';
                    ?>
                    <details class="message-row tb-message-row"
                             data-msg-id="<?= h((string)$msg->id) ?>"
                             data-state="<?= h($state) ?>"
                             id="msg-<?= h((string)$msg->id) ?>"
                             <?= $isUnread ? '' : 'open' ?>>
                        <summary class="message-row__head tb-message-row__head">
                            <span class="tb-dash-dot tb-dash-dot--<?= h($dotMod) ?>" aria-hidden="true"></span>
                            <div style="flex:1; min-width:0;">
                                <div class="tb-message-row__meta">
                                    <span class="tb-message-row__from tb-message-row__from--<?= h($fromMod) ?>"><?= h($fromText) ?></span>
                                    <?php if (!$isUnread && $isHit): ?>
                                        <span class="tb-message-row__ssr">SSR</span>
                                    <?php endif; ?>
                                    <span class="visually-hidden"><?= $isUnread ? '未開封' : '開封済' ?></span>
                                </div>
                                <div class="tb-message-row__preview"><?= h($bodyPreview) ?></div>
                            </div>
                            <time class="tb-mono tb-message-row__time" datetime="<?= h($createdIso) ?>"><?= h($createdShort) ?></time>
                        </summary>
                        <div class="message-row__body">
                            <p><?= nl2br(h((string)$msg->body)) ?></p>

                            <?php if ($isUnread): ?>
                                <?= $this->Form->create(null, [
                                    'url' => '/dashboard/messages/' . h((string)$msg->id) . '/open',
                                    'type' => 'post',
                                    'class' => 'open-form',
                                ]) ?>
                                    <button type="submit" class="tb-btn tb-btn--primary tb-btn--full">開封する</button>
                                <?= $this->Form->end() ?>
                            <?php else: ?>
                                <?php if ($isHit): ?>
                                    <div class="ssr-reveal" data-outcome="hit">
                                        <div class="tb-reveal-hit-card">
                                            <span aria-hidden="true" style="position:absolute; right:-14px; top:-22px; font-size:130px; line-height:1; color:rgba(217,162,60,0.10); font-weight:300; pointer-events:none;">✦</span>
                                            <div class="tb-reveal-hit-card__symbol" aria-hidden="true">✦</div>
                                            <div style="flex:1; min-width:0; position:relative; z-index:1;">
                                                <div style="font-size:10px; font-weight:700; color:var(--tb-warm-700); letter-spacing:0.2em; text-transform:uppercase;">抽選結果 · SSR</div>
                                                <div style="font-size:16px; font-weight:700; color:var(--tb-ink); margin-top:3px; letter-spacing:0.04em;">送信者が開示されました</div>
                                                <div style="font-size:12px; color:var(--tb-ink-2); margin-top:2px; letter-spacing:0.04em;">
                                                    <span class="tb-mono" style="color:var(--tb-warm-700); font-weight:700;"><?= $ssrPct ?>%</span> を引き当てました
                                                </div>
                                            </div>
                                            <span class="visually-hidden">★ 抽選 hit — 送信者が開示されました</span>
                                        </div>

                                        <div class="tb-sender-card sender-card">
                                            <?php $senderInitial = $senderHandle !== '' ? mb_substr($senderHandle, 0, 1) : '?'; ?>
                                            <?php if ($senderAvatar !== ''): ?>
                                                <img class="tb-sender-card__avatar sender-card__avatar"
                                                     src="<?= h($senderAvatar) ?>"
                                                     alt="<?= h($senderHandle) ?>"
                                                     width="44" height="44">
                                            <?php else: ?>
                                                <span class="tb-sender-card__avatar" aria-hidden="true"><?= h($senderInitial) ?></span>
                                                <img class="sender-card__avatar" src="/img/default-avatar.svg" alt="<?= h($senderHandle) ?>" width="44" height="44" style="display:none;">
                                            <?php endif; ?>
                                            <div style="flex:1; min-width:0;">
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <span class="tb-sender-card__name"><?= h($senderHandle) ?></span>
                                                    <span style="font-size:10px; color:var(--tb-warm-500); font-weight:700; letter-spacing:0.18em;">SSR</span>
                                                </div>
                                                <a class="tb-mono tb-sender-card__handle sender-card__handle"
                                                   href="https://bsky.app/profile/<?= h($senderHandle) ?>"
                                                   rel="noopener"
                                                   target="_blank">@<?= h($senderHandle) ?></a>
                                            </div>
                                            <?php if ($senderProfileUrl !== ''): ?>
                                                <a class="tb-sender-card__profile-link"
                                                   href="<?= h($senderProfileUrl) ?>"
                                                   target="_blank"
                                                   rel="noopener">プロフィール</a>
                                            <?php endif; ?>
                                        </div>

                                        <?= $this->Form->create(null, [
                                            'url' => '/block/' . h($senderUserId),
                                            'type' => 'post',
                                            'class' => 'inline tb-sender-card-block-form',
                                        ]) ?>
                                            <button type="submit" class="tb-btn tb-btn--quiet">このユーザーをブロック</button>
                                        <?= $this->Form->end() ?>
                                    </div>
                                <?php else: ?>
                                    <div class="ssr-reveal" data-outcome="miss">
                                        <div class="tb-reveal-miss-card">
                                            <div class="tb-reveal-miss-card__dash" aria-hidden="true">—</div>
                                            <div style="flex:1; min-width:0;">
                                                <div style="font-size:10px; font-weight:600; color:var(--tb-ink-3); letter-spacing:0.2em; text-transform:uppercase;">抽選結果</div>
                                                <div style="font-size:15px; font-weight:600; color:var(--tb-ink); margin-top:3px; letter-spacing:0.04em;">送信者は匿名のまま</div>
                                                <div style="font-size:11px; color:var(--tb-ink-3); margin-top:2px; letter-spacing:0.04em;">
                                                    <span class="tb-mono" style="color:var(--tb-warm-700); font-weight:600;"><?= 100 - $ssrPct ?>%</span> を引きました
                                                </div>
                                            </div>
                                            <span class="visually-hidden ssr-reveal__miss">★ 抽選 miss(送信者は匿名のまま)</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="message-row__footer">
                                    <?= $this->Form->create(null, [
                                        'url' => '/dashboard/messages/' . h((string)$msg->id) . '/delete',
                                        'type' => 'post',
                                        'class' => 'inline',
                                        'onsubmit' => "return confirm('このメッセージを削除しますか?(削除後は元に戻せません)');",
                                    ]) ?>
                                        <button type="submit" class="tb-btn tb-btn--quiet">削除</button>
                                    <?= $this->Form->end() ?>
                                    <?php if (isset($reportedSet[(string)$msg->id])): ?>
                                        <span class="report-badge" aria-label="このメッセージは通報済みです">通報済</span>
                                    <?php else: ?>
                                        <a href="/report/<?= h((string)$msg->id) ?>" class="tb-btn tb-btn--quiet">通報する</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </section>

            <nav class="pagination tb-pagination" aria-label="受信一覧ページ送り">
                <?= $this->Paginator->numbers() ?>
            </nav>
        <?php endif; ?>
    </div>

    <?= $this->element('tb_tabbar', ['active' => 'inbox', 'unreadCount' => $unreadCount]) ?>
</div>
