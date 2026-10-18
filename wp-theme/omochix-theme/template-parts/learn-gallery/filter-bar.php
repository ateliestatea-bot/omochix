<?php
/**
 * Motion Gallery filter bar: category chips, keyword, level, tech,
 * pause-all toggle and result count.
 *
 * Args:
 *   filters (array) Sanitized filters (cat / level / tech / q). Required.
 *   total   (int)   Number of items matching the filters.
 *
 * Works as a plain GET form without JavaScript; learn-gallery.js takes over
 * submission, filters in place and mirrors the state into the URL.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_filters    = isset($args['filters']) && is_array($args['filters']) ? $args['filters'] : ['cat' => '', 'level' => '', 'tech' => '', 'q' => ''];
$omochix_total      = isset($args['total']) ? (int) $args['total'] : 0;
$omochix_list_url   = omochix_learn_gallery_url();
$omochix_categories = omochix_learn_gallery_get_categories();
$omochix_all_count  = count(omochix_learn_gallery_get_items());
$omochix_other      = array_diff_key($omochix_filters, ['cat' => true]); // chips keep the other filters.
?>
<form class="motion-filter" method="get" action="<?php echo esc_url($omochix_list_url); ?>" data-motion-filter aria-label="<?php esc_attr_e('見本の絞り込み', 'omochix'); ?>">
    <div class="motion-filter__chips" role="group" aria-label="<?php esc_attr_e('カテゴリ', 'omochix'); ?>">
        <a class="motion-filter__chip<?php echo '' === $omochix_filters['cat'] ? ' is-active' : ''; ?>" href="<?php echo esc_url(omochix_learn_gallery_url(null, $omochix_other)); ?>" data-motion-cat=""<?php echo '' === $omochix_filters['cat'] ? ' aria-current="true"' : ''; ?>>
            <?php esc_html_e('すべて', 'omochix'); ?><span><?php echo esc_html(number_format_i18n($omochix_all_count)); ?></span>
        </a>
        <?php foreach ($omochix_categories as $omochix_category) : ?>
            <?php $omochix_active = $omochix_filters['cat'] === $omochix_category['slug']; ?>
            <a class="motion-filter__chip<?php echo $omochix_active ? ' is-active' : ''; ?>" href="<?php echo esc_url(omochix_learn_gallery_url(null, array_merge($omochix_other, ['cat' => $omochix_category['slug']]))); ?>" data-motion-cat="<?php echo esc_attr($omochix_category['slug']); ?>"<?php echo $omochix_active ? ' aria-current="true"' : ''; ?>>
                <?php echo esc_html($omochix_category['name']); ?><span><?php echo esc_html(number_format_i18n($omochix_category['count'])); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="motion-filter__row">
        <input type="hidden" name="cat" value="<?php echo esc_attr($omochix_filters['cat']); ?>" data-motion-cat-input>
        <label class="motion-filter__search">
            <span class="sr-only"><?php esc_html_e('キーワードで探す', 'omochix'); ?></span>
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
            <input type="search" name="q" value="<?php echo esc_attr($omochix_filters['q']); ?>" placeholder="<?php esc_attr_e('名前・番号・タグで探す', 'omochix'); ?>" maxlength="60" autocomplete="off" data-motion-q>
        </label>
        <label class="motion-filter__select">
            <span><?php esc_html_e('難易度', 'omochix'); ?></span>
            <select name="level" data-motion-level>
                <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                <?php foreach (omochix_learn_gallery_levels() as $omochix_value => $omochix_label) : ?>
                    <option value="<?php echo esc_attr($omochix_value); ?>" <?php selected($omochix_filters['level'], $omochix_value); ?>><?php echo esc_html($omochix_label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="motion-filter__select">
            <span><?php esc_html_e('技術', 'omochix'); ?></span>
            <select name="tech" data-motion-tech>
                <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                <?php foreach (omochix_learn_gallery_techs() as $omochix_value => $omochix_label) : ?>
                    <option value="<?php echo esc_attr($omochix_value); ?>" <?php selected($omochix_filters['tech'], $omochix_value); ?>><?php echo esc_html($omochix_label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="motion-filter__submit" data-motion-nojs><?php esc_html_e('絞り込む', 'omochix'); ?></button>
        <a class="motion-filter__reset" href="<?php echo esc_url($omochix_list_url); ?>" data-motion-reset><?php esc_html_e('リセット', 'omochix'); ?></a>
        <button type="button" class="motion-filter__pause" data-motion-pause-all aria-pressed="false" hidden>
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M8 5h3v14H8zM13 5h3v14h-3z"/></svg>
            <span><?php esc_html_e('すべて一時停止', 'omochix'); ?></span>
        </button>
    </div>
    <p class="motion-filter__count" data-motion-count aria-live="polite"><?php echo esc_html(sprintf(__('%s件の見本', 'omochix'), number_format_i18n($omochix_total))); ?></p>
</form>
