<?php
/**
 * Practice list ("/learn/practice/") and the practice term archives (routed
 * here by inc/learn-practice.php). Filters are plain GET parameters applied
 * to the main query, so the page works without JavaScript and /page/N/
 * paginates normally.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$omochix_filters  = omochix_learn_practice_current_filters();
$omochix_term     = is_tax() ? get_queried_object() : null;
$omochix_hub_url  = omochix_get_published_page_url('learn') ?: home_url('/learn/');
$omochix_list_url = omochix_learn_practice_url();
$omochix_title    = $omochix_term instanceof WP_Term ? $omochix_term->name : __('実践教材', 'omochix');
$omochix_total    = (int) $GLOBALS['wp_query']->found_posts;
?>
<main class="practice-list" id="main-content">
    <header class="practice-list__hero">
        <div class="practice-list__container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <li><a href="<?php echo esc_url($omochix_hub_url); ?>"><?php esc_html_e('Learn', 'omochix'); ?></a></li>
                    <?php if ($omochix_term instanceof WP_Term) : ?>
                        <li><a href="<?php echo esc_url($omochix_list_url); ?>"><?php esc_html_e('実践教材', 'omochix'); ?></a></li>
                        <li aria-current="page"><?php echo esc_html($omochix_term->name); ?></li>
                    <?php else : ?>
                        <li aria-current="page"><?php esc_html_e('実践教材', 'omochix'); ?></li>
                    <?php endif; ?>
                </ol>
            </nav>
            <p class="practice-list__eyebrow"><?php esc_html_e('LEARN PRACTICE', 'omochix'); ?></p>
            <h1><?php echo esc_html($omochix_title); ?></h1>
            <p class="practice-list__lead"><?php echo $omochix_term instanceof WP_Term && $omochix_term->description ? esc_html($omochix_term->description) : esc_html__('見本・プロンプト・コード・検証手順を一つにまとめた教材です。作りたいものを選び、AIへの実装指示書をそのまま使えます。', 'omochix'); ?></p>
        </div>
    </header>

    <section class="practice-list__body" aria-label="<?php esc_attr_e('教材一覧', 'omochix'); ?>">
        <div class="practice-list__container">
            <?php get_template_part('template-parts/learn-practice/filter-bar', null, ['filters' => $omochix_filters, 'total' => $omochix_total]); ?>

            <?php if (have_posts()) : ?>
                <div class="practice-grid">
                    <?php while (have_posts()) : the_post(); ?>
                        <?php get_template_part('template-parts/learn-practice/card', null, ['post' => get_post()]); ?>
                    <?php endwhile; ?>
                </div>
                <?php the_posts_pagination(['mid_size' => 1, 'prev_text' => __('前へ', 'omochix'), 'next_text' => __('次へ', 'omochix'), 'screen_reader_text' => __('教材一覧のページ送り', 'omochix')]); ?>
            <?php else : ?>
                <div class="practice-empty" role="status">
                    <p><?php echo omochix_learn_practice_count() ? esc_html__('条件に合う教材がありません。', 'omochix') : esc_html__('実践教材は準備中です。', 'omochix'); ?></p>
                    <?php if (omochix_learn_practice_is_filtered($omochix_filters) || $omochix_term instanceof WP_Term) : ?>
                        <a href="<?php echo esc_url($omochix_list_url); ?>" data-practice-reset><?php esc_html_e('絞り込みを解除する', 'omochix'); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
get_footer();
