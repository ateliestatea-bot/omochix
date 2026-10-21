<?php
/**
 * A block of practice cards for other pages (Learn articles, prompts, AI
 * tools, motion samples). Renders nothing without posts.
 *
 * Args:
 *   posts   (WP_Post[]) Practice posts. Required.
 *   title   (string)    Section heading.
 *   eyebrow (string)    Small label above the heading.
 *   more    (string)    URL of a "see all" link (optional).
 *   heading (string)    Heading tag (default h2).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_posts = isset($args['posts']) && is_array($args['posts']) ? array_filter($args['posts'], static fn($p) => $p instanceof WP_Post) : [];
if (!$omochix_posts) {
    return;
}
$omochix_title   = isset($args['title']) ? (string) $args['title'] : __('この内容を実践する', 'omochix');
$omochix_eyebrow = isset($args['eyebrow']) ? (string) $args['eyebrow'] : __('LEARN PRACTICE', 'omochix');
$omochix_more    = isset($args['more']) ? (string) $args['more'] : '';
$omochix_h       = isset($args['heading']) && in_array($args['heading'], ['h2', 'h3'], true) ? $args['heading'] : 'h2';
$omochix_uid     = 'practice-related-' . wp_unique_id();
?>
<section class="practice-related" aria-labelledby="<?php echo esc_attr($omochix_uid); ?>">
    <header class="practice-related__header">
        <p class="practice-related__eyebrow"><?php echo esc_html($omochix_eyebrow); ?></p>
        <<?php echo $omochix_h; ?> id="<?php echo esc_attr($omochix_uid); ?>"><?php echo esc_html($omochix_title); ?></<?php echo $omochix_h; ?>>
    </header>
    <div class="practice-grid practice-grid--related">
        <?php foreach ($omochix_posts as $omochix_post) : ?>
            <?php get_template_part('template-parts/learn-practice/card', null, ['post' => $omochix_post, 'size' => 'compact']); ?>
        <?php endforeach; ?>
    </div>
    <?php if ($omochix_more) : ?><p class="practice-related__more"><a href="<?php echo esc_url($omochix_more); ?>"><?php esc_html_e('すべての実践教材を見る', 'omochix'); ?><span aria-hidden="true">→</span></a></p><?php endif; ?>
</section>
