<?php
/**
 * One tool card (list and related tools). The heading link covers the card.
 *
 * @package OmochiX
 *
 * @var array $args { tool: array, hidden?: bool, heading?: 'h2'|'h3', from?: string }
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_tool     = $args['tool'];
$omochix_category = omochix_labs_get_category($omochix_tool['category']);
$omochix_heading  = 'h3' === ($args['heading'] ?? 'h2') ? 'h3' : 'h2';
?>
<li class="labs-card" data-labs-card data-category="<?php echo esc_attr($omochix_tool['category']); ?>" data-search="<?php echo esc_attr(omochix_labs_search_text($omochix_tool)); ?>"<?php echo !empty($args['hidden']) ? ' hidden' : ''; ?>>
    <span class="labs-card__icon labs-icon labs-icon--<?php echo esc_attr($omochix_tool['icon']); ?>" aria-hidden="true"></span>
    <<?php echo $omochix_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed h2/h3. ?> class="labs-card__title">
        <a href="<?php echo esc_url(omochix_labs_url($omochix_tool)); ?>" data-labs-link-type="labs" data-labs-link-slug="<?php echo esc_attr($omochix_tool['slug']); ?>"><?php echo esc_html($omochix_tool['name']); ?></a>
    </<?php echo $omochix_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed h2/h3. ?>>
    <p class="labs-card__lead"><?php echo esc_html($omochix_tool['lead']); ?></p>
    <p class="labs-card__meta">
        <?php if ($omochix_category) : ?><span class="labs-tag"><?php echo esc_html($omochix_category['name']); ?></span><?php endif; ?>
        <span class="labs-tag labs-tag--local"><?php esc_html_e('ブラウザ内で処理', 'omochix'); ?></span>
    </p>
</li>
