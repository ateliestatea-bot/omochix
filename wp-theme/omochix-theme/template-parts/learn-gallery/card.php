<?php
/**
 * Motion Gallery list card.
 *
 * Args:
 *   item    (array) Catalog item. Required.
 *   visible (bool)  Whether the card matches the current filters (default true).
 *
 * The data-* attributes carry everything the client-side filter needs, so
 * learn-gallery.js only toggles `hidden` and never rebuilds markup.
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
$omochix_visible = !array_key_exists('visible', $args) || !empty($args['visible']);
$omochix_url     = omochix_learn_gallery_url($omochix_item);
?>
<article class="motion-card" data-motion-card data-slug="<?php echo esc_attr($omochix_item['slug']); ?>" data-cat="<?php echo esc_attr($omochix_item['category']); ?>" data-level="<?php echo esc_attr($omochix_item['level']); ?>" data-tech="<?php echo esc_attr(implode(' ', $omochix_item['tech'])); ?>" data-search="<?php echo esc_attr(omochix_learn_gallery_search_text($omochix_item)); ?>"<?php echo $omochix_visible ? '' : ' hidden'; ?>>
    <div class="motion-card__preview">
        <?php get_template_part('template-parts/learn-gallery/preview', null, ['item' => $omochix_item, 'size' => 'card']); ?>
    </div>
    <div class="motion-card__body">
        <div class="motion-card__meta">
            <span class="motion-card__id">#<?php echo esc_html($omochix_item['id']); ?></span>
            <span class="motion-badge motion-badge--<?php echo esc_attr($omochix_item['level']); ?>"><?php echo esc_html(omochix_learn_gallery_level_label($omochix_item['level'])); ?></span>
            <?php foreach ($omochix_item['tech'] as $omochix_tech) : ?>
                <span class="motion-card__tech"><?php echo esc_html(omochix_learn_gallery_techs()[$omochix_tech] ?? $omochix_tech); ?></span>
            <?php endforeach; ?>
            <?php if (!empty($omochix_item['params'])) : ?>
                <span class="motion-card__adjustable"><?php esc_html_e('調整できる', 'omochix'); ?></span>
            <?php endif; ?>
        </div>
        <h3 class="motion-card__title">
            <a class="motion-card__link" href="<?php echo esc_url($omochix_url); ?>" data-motion-open>
                <?php echo esc_html($omochix_item['name']['ja']); ?>
                <span class="motion-card__en" lang="en"><?php echo esc_html($omochix_item['name']['en']); ?></span>
            </a>
        </h3>
        <p class="motion-card__summary"><?php echo esc_html($omochix_item['summary']); ?></p>
    </div>
</article>
