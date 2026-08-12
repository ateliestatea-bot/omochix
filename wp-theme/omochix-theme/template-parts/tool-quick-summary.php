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
    <div><dt><?php esc_html_e('料金', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_pricing_label); ?></dd></div>
    <div><dt><?php esc_html_e('無料プラン', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_free_plan_label); ?></dd></div>
    <div><dt><?php esc_html_e('日本語対応', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_japanese_label); ?></dd></div>
    <div><dt><?php esc_html_e('API', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_boolean_label($omochix_api)); ?></dd></div>
    <div><dt><?php esc_html_e('商用利用', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_boolean_label($omochix_commercial)); ?></dd></div>
    <div><dt><?php esc_html_e('対応環境', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_platform_label); ?></dd></div>
    <div><dt><?php esc_html_e('提供状況', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_status_label); ?></dd></div>
</dl>
