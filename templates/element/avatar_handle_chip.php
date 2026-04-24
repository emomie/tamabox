<?php
/**
 * @var \App\View\AppView $this
 * @var \Authentication\IdentityInterface $identity
 *
 * AvatarHandleChip element (UI-SPEC §3). Pulls handle/avatar from the underlying
 * User entity's user_identity association — Authentication plugin's getOriginalData()
 * returns the entity that the ORM identifier resolved from the session 'id' value.
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
<div class="avatar-handle-chip" role="group" aria-label="ログイン中のユーザー">
    <?php if ($avatar !== null && $avatar !== '') : ?>
        <img
            src="<?= h($avatar) ?>"
            alt="<?= h($handle) ?> のアイコン"
            class="avatar-handle-chip__avatar"
            width="24"
            height="24">
    <?php else : ?>
        <span
            class="avatar-handle-chip__avatar avatar-handle-chip__avatar--fallback"
            aria-label="<?= h($handle) ?> のアイコン">
            <?= h(mb_substr($handle, 0, 1)) ?>
        </span>
    <?php endif; ?>
    <span class="avatar-handle-chip__handle">@<?= h($handle) ?></span>
</div>
