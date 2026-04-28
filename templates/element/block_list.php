<?php
/**
 * @var \App\View\AppView $this
 * @var array<int, \App\Model\Entity\Block> $blocks
 *
 * UI-SPEC §3 — Dashboard "ブロック中ユーザー" section partial.
 * Consumed by templates/Users/dashboard.php (Plan 04-01 Task 3).
 *
 * Each $block must contain BlockedUser->user_identity (controller eager-loads
 * via contain(['BlockedUsers' => ['UserIdentities']])).
 */
?>
<section class="block-list">
    <h2>ブロック中ユーザー</h2>
    <?php if (count($blocks) === 0): ?>
        <p class="text-secondary block-list__empty">ブロックしているユーザーはいません</p>
    <?php else: ?>
        <ul class="block-list__items">
            <?php foreach ($blocks as $block): ?>
                <?php
                $blocked = $block->blocked_user ?? null;
                $identity = ($blocked !== null && isset($blocked->user_identity)) ? $blocked->user_identity : null;
                $handle = $identity !== null ? (string)$identity->handle_cached : '';
                $avatarRaw = $identity !== null ? $identity->avatar_url_cached : null;
                $avatar = ($avatarRaw !== null && $avatarRaw !== '') ? (string)$avatarRaw : '/img/default-avatar.svg';
                ?>
                <li class="block-list__row">
                    <img class="block-list__avatar"
                         src="<?= h($avatar) ?>"
                         alt=""
                         width="24" height="24"
                         onerror="this.src='/img/default-avatar.svg'">
                    <span class="block-list__handle">@<?= h($handle) ?></span>
                    <?= $this->Form->create(null, [
                        'url' => '/dashboard/blocks/' . h((string)$block->id) . '/delete',
                        'type' => 'post',
                        'class' => 'inline block-list__unblock-form',
                    ]) ?>
                        <button type="submit" class="button button-clear">解除</button>
                    <?= $this->Form->end() ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
