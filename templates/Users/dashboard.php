<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 *
 * UI-SPEC §4 — welcome + Phase 3 placeholder.
 */
$this->assign('title', 'ダッシュボード');

$handle = '';
if (isset($user->user_identity) && $user->user_identity !== null) {
    $handle = (string)$user->user_identity->handle_cached;
}
?>
<div class="dashboard-page">
    <h1>ようこそ、<?= h($handle) ?> さん</h1>
    <p class="text-secondary">受信箱はまだ作成されていません。受信箱の作成は次のステップで行います。</p>
</div>
