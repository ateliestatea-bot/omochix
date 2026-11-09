<?php
/**
 * In-page navigation of a hub, in the same order as the sections (§6.1).
 * Fixed to the bottom of the screen on phones, a plain list on wide screens.
 *
 * Args: items (array of [id, label]).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_items = array_values(array_filter((array) ($args['items'] ?? []), static fn($i) => is_array($i) && !empty($i[0])));
if (!$omochix_items) {
    return;
}
?>
<nav class="ih-jump" aria-label="<?php esc_attr_e('このページの目次', 'omochix'); ?>">
    <ol class="ih-jump__list">
        <?php foreach ($omochix_items as $omochix_i => $omochix_item) : ?>
            <li><a href="#<?php echo esc_attr($omochix_item[0]); ?>"><span class="ih-jump__num" aria-hidden="true"><?php echo (int) $omochix_i + 1; ?></span><?php echo esc_html($omochix_item[1]); ?></a></li>
        <?php endforeach; ?>
    </ol>
</nav>
