<?php
/**
 * Compact card for AI tools, prompts, Learn pages and news. The whole card
 * is one link (the title's ::after covers it): one tab stop per card.
 *
 * Args: post (WP_Post).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_post = isset($args['post']) ? get_post($args['post']) : null;
if (!$omochix_post instanceof WP_Post) {
    return;
}
$omochix_types = ['ai_tool' => __('AIツール', 'omochix'), 'prompt' => __('プロンプト', 'omochix'), 'page' => __('Learn記事', 'omochix'), 'post' => __('ニュース', 'omochix'), 'learn_practice' => __('実践教材', 'omochix')];
$omochix_excerpt = wp_trim_words(wp_strip_all_tags(get_the_excerpt($omochix_post)), 40, '…');
?>
<article class="ih-card ih-card--<?php echo esc_attr($omochix_post->post_type); ?>">
    <div class="ih-card__meta">
        <span class="ih-card__type"><?php echo esc_html($omochix_types[$omochix_post->post_type] ?? ''); ?></span>
        <?php get_template_part('template-parts/learn-industries/provider-badge', null, ['post' => $omochix_post]); ?>
    </div>
    <h3 class="ih-card__title"><a href="<?php echo esc_url(get_permalink($omochix_post)); ?>"><?php echo esc_html(get_the_title($omochix_post)); ?></a></h3>
    <?php if ('' !== $omochix_excerpt) : ?>
        <p class="ih-card__text"><?php echo esc_html($omochix_excerpt); ?></p>
    <?php endif; ?>
</article>
