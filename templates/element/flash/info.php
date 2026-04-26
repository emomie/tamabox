<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 *
 * Info variant flash element — UI-SPEC §11.
 * Used by Flash->set($msg, ['element' => 'info']) — accent color is muted (border-left
 * --color-text-secondary, NOT --color-accent) to keep accent reserved for primary CTAs.
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<div class="message info" role="status" onclick="this.classList.add('hidden')">
    <?= $message ?>
</div>
