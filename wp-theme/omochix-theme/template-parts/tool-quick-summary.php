<?php
/**
 * Shared AI tool quick summary list.
 *
 * Expects values prepared by single-ai_tool.php.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<dl class="tool-summary__list">
    <div><dt><span class="omx-icon omx-icon--coin" aria-hidden="true"></span><?php esc_html_e('料金', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_pricing_label); ?></dd></div>
    <div><dt><span class="omx-icon omx-icon--coin" aria-hidden="true"></span><?php esc_html_e('無料プラン', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_free_plan_label); ?></dd></div>
    <div><dt><span class="omx-icon omx-icon--globe" aria-hidden="true"></span><?php esc_html_e('日本語対応', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_japanese_label); ?></dd></div>
    <div><dt><span class="omx-icon omx-icon--bolt" aria-hidden="true"></span><?php esc_html_e('API', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_api_label); ?></dd></div>
    <div><dt><span class="omx-icon omx-icon--shield" aria-hidden="true"></span><?php esc_html_e('商用利用', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_commercial_label); ?></dd></div>
    <div><dt><span class="omx-icon omx-icon--monitor" aria-hidden="true"></span><?php esc_html_e('対応環境', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_environment_label); ?></dd></div>
    <?php if (!empty($omochix_supported_models)) : ?>
        <div><dt><span class="omx-icon omx-icon--flask" aria-hidden="true"></span><?php esc_html_e('対応モデル', 'omochix'); ?></dt><dd><?php echo esc_html(implode('、', $omochix_supported_models)); ?></dd></div>
    <?php endif; ?>
    <div><dt><span class="omx-icon omx-icon--dot" aria-hidden="true"></span><?php esc_html_e('提供状況', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_status_label); ?></dd></div>
    <?php if (!empty($omochix_info_checked)) : ?>
        <div><dt><span class="omx-icon omx-icon--info" aria-hidden="true"></span><?php esc_html_e('情報確認日', 'omochix'); ?></dt><dd><time datetime="<?php echo esc_attr($omochix_info_checked); ?>"><?php echo esc_html(mysql2date('Y.m.d', $omochix_info_checked)); ?></time></dd></div>
    <?php endif; ?>
</dl>
