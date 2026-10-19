<?php
/**
 * Playground panel: editable parameters of a motion sample.
 *
 * Args:
 *   item    (array)  Catalog item. Required; renders nothing without params.
 *   heading (string) Heading tag for the panel title ("h2" on the page, "h3" in the dialog).
 *   share   (bool)   Print the "copy link" button (default true; the composer brings its own).
 *
 * The panel is server-rendered with the sample's defaults, followed by a
 * JSON script with the sample's copy templates. learn-playground.js
 * binds the inputs: changes are sanitized, sent to the preview iframe(s) of
 * this sample as CSS custom properties / window.omxParams, mirrored into
 * the copy targets (prompt and code) and into the URL hash (#p=…). Without
 * JavaScript the inputs are inert but the defaults still describe the
 * sample.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_item = isset($args['item']) && is_array($args['item']) ? $args['item'] : null;
if (!$omochix_item || empty($omochix_item['params'])) {
    return;
}
$omochix_heading = isset($args['heading']) && in_array($args['heading'], ['h2', 'h3', 'h4'], true) ? $args['heading'] : 'h2';
$omochix_uid     = 'motion-' . $omochix_item['slug'] . '-pg';
$omochix_values  = omochix_learn_gallery_sanitize_params($omochix_item);
$omochix_share   = !array_key_exists('share', $args) || !empty($args['share']);
?>
<section class="motion-playground" data-motion-playground data-slug="<?php echo esc_attr($omochix_item['slug']); ?>" aria-labelledby="<?php echo esc_attr($omochix_uid); ?>-title">
    <div class="motion-panel__header">
        <<?php echo $omochix_heading; ?> class="motion-panel__title" id="<?php echo esc_attr($omochix_uid); ?>-title"><?php esc_html_e('調整する', 'omochix'); ?></<?php echo $omochix_heading; ?>>
        <button type="button" class="motion-playground__reset" data-omx-reset disabled><?php esc_html_e('既定に戻す', 'omochix'); ?></button>
    </div>
    <p class="motion-playground__hint"><?php esc_html_e('値を変えるとプレビューに反映され、「プロンプトをコピー」「コードをコピー」も調整後の内容になります。', 'omochix'); ?></p>
    <div class="motion-playground__grid">
        <?php foreach ($omochix_item['params'] as $omochix_def) : ?>
            <?php
            $omochix_field_id = $omochix_uid . '-' . $omochix_def['key'];
            $omochix_value    = $omochix_values[$omochix_def['key']];
            ?>
            <div class="motion-playground__field motion-playground__field--<?php echo esc_attr($omochix_def['type']); ?>">
                <label class="motion-playground__label" for="<?php echo esc_attr($omochix_field_id); ?>">
                    <span><?php echo esc_html($omochix_def['label']); ?></span>
                    <output class="motion-playground__value" data-omx-output="<?php echo esc_attr($omochix_def['key']); ?>" for="<?php echo esc_attr($omochix_field_id); ?>"><?php echo esc_html(omochix_learn_gallery_param_display($omochix_def, $omochix_value)); ?></output>
                </label>
                <?php if ('range' === $omochix_def['type']) : ?>
                    <input type="range" id="<?php echo esc_attr($omochix_field_id); ?>" data-omx-param="<?php echo esc_attr($omochix_def['key']); ?>" min="<?php echo esc_attr(omochix_learn_gallery_format_number($omochix_def['min'])); ?>" max="<?php echo esc_attr(omochix_learn_gallery_format_number($omochix_def['max'])); ?>" step="<?php echo esc_attr(omochix_learn_gallery_format_number($omochix_def['step'])); ?>" value="<?php echo esc_attr(omochix_learn_gallery_format_number($omochix_value)); ?>" data-omx-default="<?php echo esc_attr(omochix_learn_gallery_format_number($omochix_def['default'])); ?>">
                <?php elseif ('color' === $omochix_def['type']) : ?>
                    <input type="color" id="<?php echo esc_attr($omochix_field_id); ?>" data-omx-param="<?php echo esc_attr($omochix_def['key']); ?>" value="<?php echo esc_attr($omochix_value); ?>" data-omx-default="<?php echo esc_attr($omochix_def['default']); ?>">
                <?php else : ?>
                    <select id="<?php echo esc_attr($omochix_field_id); ?>" data-omx-param="<?php echo esc_attr($omochix_def['key']); ?>" data-omx-default="<?php echo esc_attr($omochix_def['default']); ?>">
                        <?php foreach ($omochix_def['options'] as $omochix_option) : ?>
                            <option value="<?php echo esc_attr($omochix_option['value']); ?>" <?php selected($omochix_value, $omochix_option['value']); ?>><?php echo esc_html($omochix_option['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="motion-playground__actions">
        <?php if ($omochix_share) : ?>
            <button type="button" class="motion-copy motion-copy--ghost" data-omx-share hidden><?php esc_html_e('調整した状態のリンクをコピー', 'omochix'); ?></button>
        <?php endif; ?>
        <p class="sr-only" data-omx-copy-status aria-live="polite"></p>
    </div>
</section>
<script type="application/json" id="<?php echo esc_attr($omochix_uid); ?>-templates" data-omx-item-templates><?php echo omochix_learn_gallery_item_json($omochix_item, omochix_learn_gallery_url($omochix_item)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON built with JSON_HEX_TAG by omochix_learn_gallery_item_json(). ?></script>
