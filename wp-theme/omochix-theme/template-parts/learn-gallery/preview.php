<?php
/**
 * Motion preview: poster + lazily mounted sandboxed iframe + controls.
 *
 * Args:
 *   item     (array)  Catalog item. Required.
 *   size     (string) "card" | "detail" | "hub". Affects CSS only.
 *   static   (bool)   Also print a no-JS iframe (detail page only).
 *
 * Without JavaScript the poster is shown (and, with `static`, a plain
 * iframe that runs the sample). learn-gallery.js registers the item's
 * preview document via omochix_learn_gallery_registry() and mounts the
 * iframe when the preview scrolls near the viewport.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_item = isset($args['item']) && is_array($args['item']) ? $args['item'] : null;
if (!$omochix_item) {
    return;
}
$omochix_size   = isset($args['size']) ? sanitize_key($args['size']) : 'card';
$omochix_static = !empty($args['static']);
$omochix_stage  = omochix_learn_gallery_get_stage();
$omochix_label  = sprintf(__('%sのプレビュー', 'omochix'), $omochix_item['name']['ja']);
omochix_learn_gallery_registry($omochix_item);
?>
<div class="motion-preview motion-preview--<?php echo esc_attr($omochix_size); ?>" data-motion-preview data-slug="<?php echo esc_attr($omochix_item['slug']); ?>" style="--stage-w: <?php echo (int) $omochix_stage['width']; ?>; --stage-h: <?php echo (int) $omochix_stage['height']; ?>;">
    <div class="motion-preview__poster" aria-hidden="true">
        <span class="motion-preview__poster-id">#<?php echo esc_html($omochix_item['id']); ?></span>
        <span class="motion-preview__poster-name"><?php echo esc_html($omochix_item['name']['ja']); ?></span>
    </div>
    <div class="motion-preview__frame" data-motion-frame data-label="<?php echo esc_attr($omochix_label); ?>">
        <?php if ($omochix_static) : ?>
            <noscript>
                <iframe class="motion-preview__iframe" sandbox="allow-scripts" title="<?php echo esc_attr($omochix_label); ?>" tabindex="-1" srcdoc="<?php echo esc_attr(omochix_learn_gallery_preview_document($omochix_item, $omochix_stage)); ?>"></iframe>
            </noscript>
        <?php endif; ?>
    </div>
    <div class="motion-preview__controls" data-motion-controls hidden>
        <button type="button" class="motion-preview__btn" data-motion-toggle aria-label="<?php echo esc_attr(sprintf(__('%sを一時停止', 'omochix'), $omochix_label)); ?>">
            <svg class="motion-preview__icon motion-preview__icon--pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5h3v14H8zM13 5h3v14h-3z"/></svg>
            <svg class="motion-preview__icon motion-preview__icon--play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
        </button>
        <?php if ('hub' !== $omochix_size) : ?>
            <button type="button" class="motion-preview__btn" data-motion-expand-btn aria-label="<?php echo esc_attr(sprintf(__('%sを拡大して見る', 'omochix'), $omochix_label)); ?>">
                <svg class="motion-preview__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        <?php endif; ?>
    </div>
</div>
