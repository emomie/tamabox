<?php
/**
 * @var \App\View\AppView $this
 * @var array<int, \App\Model\Entity\Block> $blocks
 *
 * UI-07 — Block list (Phase 6 Calm Gacha rewrite of Phase 4 D-04).
 * Hi-fi: ~/projects/handoff_tamabox/screens/Block.jsx
 *
 * Class names "block-list" (section) and "block-list__row" (list item) are PRESERVED
 * because tests/TestCase/Controller/UsersControllerTest.php asserts on them. New visual
 * styling is layered via .tb-block-list and .tb-block-row classes.
 *
 * AppBar is the responsibility of the parent template; this element renders the
 * section heading + note + list.
 */
$count = count($blocks);
?>
<section class="block-list tb-block-list">
    <div class="tb-section-label">ブロック中ユーザー <span class="tb-mono tb-block-list__count">(<?= $count ?>)</span></div>

    <?php if ($count === 0): ?>
        <div class="tb-card-soft tb-block-list__empty">
            <p class="tb-block-list__empty-title">ブロックしているユーザーはいません</p>
            <p class="tb-block-list__empty-body">
                SSR で開示された送信者の「ブロック」ボタンから登録できます。
            </p>
        </div>
    <?php else: ?>
        <div class="tb-card-soft tb-block-list__note">
            <p>ブロック中のユーザーは、あなたの箱に新規メッセージを送れません。</p>
            <p class="tb-block-list__note-sub">SSR で開示された送信者のメニューから追加できます。</p>
        </div>
        <ul class="block-list__items tb-block-list__items">
            <?php foreach ($blocks as $block): ?>
                <?php
                $blocked = $block->blocked_user ?? null;
                $identity = ($blocked !== null && isset($blocked->user_identity)) ? $blocked->user_identity : null;
                $handle = $identity !== null ? (string)$identity->handle_cached : '';
                $avatarRaw = $identity !== null ? $identity->avatar_url_cached : null;
                $avatar = ($avatarRaw !== null && $avatarRaw !== '') ? (string)$avatarRaw : null;
                $initial = $handle !== '' ? mb_strtoupper(mb_substr($handle, 0, 1)) : '?';
                ?>
                <li class="block-list__row tb-block-row">
                    <?php if ($avatar !== null): ?>
                        <img class="tb-block-row__avatar"
                             src="<?= h($avatar) ?>"
                             alt=""
                             width="40" height="40">
                    <?php else: ?>
                        <span class="tb-block-row__avatar tb-block-row__avatar--fallback"><?= h($initial) ?></span>
                    <?php endif; ?>
                    <div class="tb-block-row__body">
                        <div class="tb-block-row__name"><?= h($handle) ?></div>
                        <div class="tb-mono tb-block-row__handle">@<?= h($handle) ?></div>
                    </div>
                    <?= $this->Form->create(null, [
                        'url' => '/dashboard/blocks/' . h((string)$block->id) . '/delete',
                        'type' => 'post',
                        'class' => 'inline tb-block-row__form',
                    ]) ?>
                        <button type="submit" class="tb-pill-btn">解除</button>
                    <?= $this->Form->end() ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
