<?php
/**
 * @var \App\View\AppView $this
 * @var \Authentication\IdentityInterface $identity
 *
 * UI-08 — AvatarHandleChip element (Phase 6 Calm Gacha rewrite of UI-SPEC v1 §3).
 * Hi-fi: ~/projects/handoff_tamabox/components.jsx (TbChip lines 76-82).
 *
 * Pulls handle/avatar from the underlying User entity's user_identity association
 * (Authentication plugin getOriginalData() returns the entity ORM resolved from the
 * session 'id' value). Controller-side wiring is identical to v1 — only markup changed.
 */
$originalData = $identity->getOriginalData();
$handle = '';
$avatar = null;
if ($originalData !== null && isset($originalData->user_identity) && $originalData->user_identity !== null) {
    $handle = (string)$originalData->user_identity->handle_cached;
    if (isset($originalData->user_identity->avatar_url_cached) && $originalData->user_identity->avatar_url_cached !== null) {
        $avatar = (string)$originalData->user_identity->avatar_url_cached;
    }
}
?>
<span class="tb-chip tb-chip--user" role="group" aria-label="ログイン中のユーザー">
    <?php if ($avatar !== null && $avatar !== '') : ?>
        <img
            src="<?= h($avatar) ?>"
            alt=""
            class="tb-chip__avatar"
            width="20"
            height="20">
    <?php else : ?>
        <span class="tb-chip__avatar tb-chip__avatar--fallback" aria-hidden="true">
            <?= h(mb_substr($handle, 0, 1)) ?>
        </span>
    <?php endif; ?>
    <span class="tb-mono tb-chip__handle">@<?= h($handle) ?></span>
</span>
