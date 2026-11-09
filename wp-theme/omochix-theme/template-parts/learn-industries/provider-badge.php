<?php
/**
 * Provider badge. Unclassified content shows no badge (it is never called
 * official); partner content is marked as advertising (PR) in text, not only
 * by colour.
 *
 * Args: post (WP_Post|int) or type (string).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_type = isset($args['type']) ? (string) $args['type'] : (isset($args['post']) && function_exists('omochix_core_get_provider_type') ? omochix_core_get_provider_type($args['post']) : 'unclassified');
$omochix_label = function_exists('omochix_core_get_provider_label') ? omochix_core_get_provider_label($omochix_type) : '';
if ('' === $omochix_label) {
    return;
}
?>
<span class="ih-badge ih-badge--<?php echo esc_attr($omochix_type); ?>"><?php echo esc_html('partner' === $omochix_type ? __('PR・パートナー提供', 'omochix') : $omochix_label); ?></span>
