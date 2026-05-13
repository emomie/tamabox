<?php
/**
 * Inline SVG icon helper.
 *
 * Usage: <?= $this->element('icon', ['name' => 'inbox', 'size' => 24]) ?>
 *
 * @var string $name  One of: inbox, send, user, bell, compass, back, close, more, check, chevron, letter, star, heart
 * @var int    $size  Width/height in pixels (default 24)
 *
 * SVG path data verbatim from handoff_tamabox/components.jsx (Phase 5).
 *
 * Security: $name is matched against a closed set via PHP match — unknown values
 * produce empty output, never echoed. Inner SVG strings are hardcoded literals,
 * never user-derived. Do NOT pipe $inner through h() as it would escape < and break SVG.
 *
 * Accessibility: aria-hidden="true" is the default for decorative icon usage.
 * Templates using icons semantically (e.g. standalone icon buttons) must supply
 * aria-label on the parent <button> element per UI-SPEC line 381.
 */
$size = isset($size) ? (int)$size : 24;
$inner = match ($name ?? '') {
    'inbox'   => '<path d="M3 12l3-7h12l3 7" /><path d="M3 12v6a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1v-6" /><path d="M3 12h5l1 2h6l1-2h5" />',
    'send'    => '<path d="M21 3 11 13" /><path d="M21 3 14 21l-3-8-8-3z" />',
    'user'    => '<circle cx="12" cy="8" r="4" /><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" />',
    'bell'    => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z" /><path d="M10 20a2 2 0 0 0 4 0" />',
    'compass' => '<circle cx="12" cy="12" r="9" /><path d="m15 9-2 5-5 2 2-5z" />',
    'back'    => '<path d="m15 6-6 6 6 6" />',
    'close'   => '<path d="M6 6l12 12M6 18 18 6" />',
    'more'    => '<circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none"/>',
    'check'   => '<path d="M5 12.5 10 17l9-10" />',
    'chevron' => '<path d="m9 6 6 6-6 6" />',
    'letter'  => '<rect x="3" y="6" width="18" height="13" rx="2" /><path d="m4 8 8 6 8-6" />',
    'star'    => '<path d="M12 3.5 14.5 9l6 .7-4.5 4 1.3 6L12 16.8 6.7 19.7l1.3-6L3.5 9.7 9.5 9z" />',
    'heart'   => '<path d="M12 20s-7-4.5-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.5-9 9-9 9z" />',
    default   => '',
};
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="<?= $size ?>" height="<?= $size ?>" aria-hidden="true"><?= $inner ?></svg>
