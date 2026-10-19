<?php
/**
 * Composer picker: a dialog listing every catalog sample with its preview
 * (registered for learn-gallery.js, so it animates once the dialog opens)
 * and an "add" button. The previews here are also the source the slots
 * clone their preview from.
 *
 * Args:
 *   items      (array) Catalog items keyed by slug.
 *   categories (array) Catalog categories keyed by id.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_items      = isset($args['items']) && is_array($args['items']) ? $args['items'] : [];
$omochix_categories = isset($args['categories']) && is_array($args['categories']) ? $args['categories'] : [];
?>
<dialog class="motion-composer__picker" data-composer-picker aria-labelledby="composer-picker-title">
    <div class="motion-composer__picker-frame">
        <header class="motion-composer__picker-head">
            <h2 id="composer-picker-title" class="motion-composer__title"><?php esc_html_e('見本を選ぶ', 'omochix'); ?></h2>
            <label class="motion-composer__picker-search">
                <span class="sr-only"><?php esc_html_e('名前・番号・タグで探す', 'omochix'); ?></span>
                <input type="search" data-composer-search placeholder="<?php esc_attr_e('名前・番号・タグで探す', 'omochix'); ?>" autocomplete="off">
            </label>
            <button type="button" class="motion-composer__picker-close icon-button" data-composer-picker-close aria-label="<?php esc_attr_e('閉じる', 'omochix'); ?>">×</button>
        </header>
        <p class="sr-only" data-composer-picker-status aria-live="polite"></p>
        <?php foreach ($omochix_categories as $omochix_cat_id => $omochix_cat) : ?>
            <section class="motion-composer__picker-group" data-composer-group="<?php echo esc_attr($omochix_cat_id); ?>">
                <h3 class="motion-composer__picker-group-title"><?php echo esc_html($omochix_cat['name']); ?></h3>
                <ul class="motion-composer__picker-list">
                    <?php foreach ($omochix_items as $omochix_item) : ?>
                        <?php if ($omochix_item['category'] !== $omochix_cat_id) { continue; } ?>
                        <li class="motion-composer__picker-item" data-composer-item data-slug="<?php echo esc_attr($omochix_item['slug']); ?>" data-search="<?php echo esc_attr(omochix_learn_gallery_search_text($omochix_item)); ?>">
                            <div class="motion-composer__picker-preview">
                                <?php get_template_part('template-parts/learn-gallery/preview', null, ['item' => $omochix_item, 'size' => 'hub']); ?>
                            </div>
                            <div class="motion-composer__picker-meta">
                                <span class="motion-card__id">#<?php echo esc_html($omochix_item['id']); ?></span>
                                <strong class="motion-composer__picker-name"><?php echo esc_html($omochix_item['name']['ja']); ?></strong>
                                <span class="motion-composer__picker-summary"><?php echo esc_html($omochix_item['summary']); ?></span>
                            </div>
                            <button type="button" class="motion-copy motion-copy--ghost motion-composer__add" data-composer-add aria-label="<?php echo esc_attr(sprintf(__('%sを追加', 'omochix'), $omochix_item['name']['ja'])); ?>"><?php esc_html_e('追加', 'omochix'); ?></button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
        <p class="motion-composer__picker-none" data-composer-picker-none hidden><?php esc_html_e('条件に合う見本がありません。', 'omochix'); ?></p>
    </div>
</dialog>
