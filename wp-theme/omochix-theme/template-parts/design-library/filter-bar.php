<?php
/**
 * Design Library filters: keyword, medium (top page only), use case, motion
 * type, technology, industry. A plain GET form (works without JavaScript);
 * the list script filters in place and keeps the URL in sync. Only options
 * that some public work uses are offered.
 *
 * @var array $args {medium: string ('' on the top page), filters: array, total: int}
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omx_f      = $args['filters'];
$omx_opts   = omochix_design_filter_options($args['medium']);
$omx_action = '' === $args['medium'] ? omochix_design_url() : omochix_design_medium_url($args['medium']);
$omx_labels = ['medium' => __('種類', 'omochix')];
foreach (omochix_design_filter_axes() as $omx_key => $omx_axis) {
    $omx_labels[$omx_key] = $omx_axis['label'];
}
if ('' !== $args['medium'] || count($omx_opts['medium']) < 2) {
    unset($omx_labels['medium']);
}
?>
<form class="dl-filters" method="get" action="<?php echo esc_url($omx_action); ?>" role="search" data-dl-filters aria-label="<?php esc_attr_e('作品を絞り込む', 'omochix'); ?>">
    <div class="dl-filters__field dl-filters__field--q">
        <label for="dl-q"><?php esc_html_e('キーワード', 'omochix'); ?></label>
        <input id="dl-q" type="search" name="q" maxlength="60" value="<?php echo esc_attr($omx_f['q']); ?>" placeholder="<?php esc_attr_e('例：ガラス、グラフ、ロゴ', 'omochix'); ?>" autocomplete="off">
    </div>
    <?php foreach ($omx_labels as $omx_key => $omx_label) : ?>
        <?php if (!$omx_opts[$omx_key]) { continue; } ?>
        <div class="dl-filters__field">
            <label for="dl-<?php echo esc_attr($omx_key); ?>"><?php echo esc_html($omx_label); ?></label>
            <select id="dl-<?php echo esc_attr($omx_key); ?>" name="<?php echo esc_attr($omx_key); ?>">
                <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                <?php foreach ($omx_opts[$omx_key] as $omx_v => $omx_name) : ?>
                    <option value="<?php echo esc_attr((string) $omx_v); ?>"<?php selected($omx_f[$omx_key], (string) $omx_v); ?>><?php echo esc_html($omx_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>
    <div class="dl-filters__actions">
        <button type="submit" class="dl-btn dl-btn--primary" data-dl-submit><?php esc_html_e('絞り込む', 'omochix'); ?></button>
        <a class="dl-btn" href="<?php echo esc_url($omx_action); ?>" data-dl-reset><?php esc_html_e('リセット', 'omochix'); ?></a>
    </div>
    <p class="dl-filters__count" role="status" data-dl-count><?php echo esc_html(sprintf(__('%s件の作品', 'omochix'), number_format_i18n($args['total']))); ?></p>
</form>
