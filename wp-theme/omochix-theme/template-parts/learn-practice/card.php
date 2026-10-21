<?php
/**
 * Practice list card.
 *
 * Args:
 *   post (WP_Post|int) Practice post. Required.
 *   size (string)      "card" (default) or "compact" (related blocks).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_item = isset($args['post']) ? omochix_learn_practice_item($args['post']) : null;
if (!$omochix_item || !$omochix_item['id']) {
    return;
}
$omochix_size    = isset($args['size']) && 'compact' === $args['size'] ? 'compact' : 'card';
$omochix_format  = omochix_learn_practice_primary_format($omochix_item);
$omochix_field   = $omochix_item['fields'] ? $omochix_item['fields'][0] : null;
$omochix_poster  = $omochix_item['preview']['poster'] ? wp_get_attachment_image($omochix_item['preview']['poster'], 'medium_large', false, ['loading' => 'lazy', 'alt' => '']) : get_the_post_thumbnail($omochix_item['post'], 'medium_large', ['loading' => 'lazy', 'alt' => '']);
$omochix_ai      = array_slice($omochix_item['ai'], 0, 3);
?>
<article class="practice-card practice-card--<?php echo esc_attr($omochix_size); ?>" data-practice-card>
    <a class="practice-card__media" href="<?php echo esc_url($omochix_item['url']); ?>" tabindex="-1" aria-hidden="true">
        <?php if ($omochix_poster) : ?>
            <?php echo $omochix_poster; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image output. ?>
        <?php else : ?>
            <span class="practice-card__placeholder practice-card__placeholder--<?php echo esc_attr($omochix_format ?: 'lesson'); ?>"><?php echo esc_html($omochix_format ? omochix_learn_practice_format_label($omochix_format) : ''); ?></span>
        <?php endif; ?>
    </a>
    <div class="practice-card__body">
        <div class="practice-card__meta">
            <?php if ($omochix_field) : ?><span class="practice-badge practice-badge--field"><?php echo esc_html($omochix_field->name); ?></span><?php endif; ?>
            <?php if ($omochix_format) : ?><span class="practice-badge practice-badge--format"><?php echo esc_html(omochix_learn_practice_format_label($omochix_format)); ?></span><?php endif; ?>
            <span class="practice-badge practice-badge--<?php echo esc_attr($omochix_item['level']); ?>"><?php echo esc_html(omochix_learn_practice_level_label($omochix_item['level'])); ?></span>
        </div>
        <h3 class="practice-card__title"><a href="<?php echo esc_url($omochix_item['url']); ?>"><?php echo esc_html($omochix_item['title']); ?></a></h3>
        <?php if ($omochix_item['summary']) : ?><p class="practice-card__summary"><?php echo esc_html($omochix_item['summary']); ?></p><?php endif; ?>
        <p class="practice-card__facts">
            <?php if ($omochix_item['minutes']) : ?><span><?php echo esc_html(sprintf(__('約%d分', 'omochix'), $omochix_item['minutes'])); ?></span><?php endif; ?>
            <?php foreach ($omochix_ai as $omochix_term) : ?><span class="practice-card__ai"><?php echo esc_html($omochix_term->name); ?></span><?php endforeach; ?>
            <?php if ($omochix_item['verified_at']) : ?>
                <span class="practice-card__verified<?php echo $omochix_item['stale'] ? ' is-stale' : ''; ?>"><?php echo $omochix_item['stale'] ? esc_html__('要再検証', 'omochix') : esc_html(sprintf(__('検証 %s', 'omochix'), str_replace('-', '.', $omochix_item['verified_at']))); ?></span>
            <?php endif; ?>
        </p>
    </div>
</article>
