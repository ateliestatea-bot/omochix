<?php
/**
 * AI news index (WordPress posts page).
 *
 * Assign a page named "AIニュース" as the posts page under Settings > Reading.
 * The template intentionally relies only on WordPress core APIs.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$omochix_news_page_id = (int) get_option('page_for_posts');
$omochix_news_title   = $omochix_news_page_id ? get_the_title($omochix_news_page_id) : __('AIニュース', 'omochix');
$omochix_news_url     = $omochix_news_page_id ? get_permalink($omochix_news_page_id) : home_url('/');

// Read and validate public filter parameters.
$omochix_has_category_filter = isset($_GET['news_category']);
$omochix_filter_category = $omochix_has_category_filter ? absint(wp_unslash($_GET['news_category'])) : 0;
$omochix_filter_tag      = isset($_GET['news_tag']) ? sanitize_title(wp_unslash($_GET['news_tag'])) : '';
$omochix_filter_order    = isset($_GET['news_order']) ? sanitize_key(wp_unslash($_GET['news_order'])) : 'latest';
$omochix_filter_search   = isset($_GET['news_search']) ? sanitize_text_field(wp_unslash($_GET['news_search'])) : '';
$omochix_allowed_orders  = ['latest', 'oldest', 'popular'];

if (!in_array($omochix_filter_order, $omochix_allowed_orders, true)) {
    $omochix_filter_order = 'latest';
}

$omochix_news_categories = get_categories([
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);
$omochix_news_tags = get_tags([
    'hide_empty' => false,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);
if (is_wp_error($omochix_news_categories)) {
    $omochix_news_categories = [];
}
if (is_wp_error($omochix_news_tags)) {
    $omochix_news_tags = [];
}

$omochix_default_news_category = get_category_by_slug('ai-news');
$omochix_query_category        = $omochix_filter_category;
if (!$omochix_has_category_filter && $omochix_default_news_category) {
    $omochix_query_category = (int) $omochix_default_news_category->term_id;
}

$omochix_paged = max(1, get_query_var('paged'), get_query_var('page'));
$omochix_query_args = [
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 12,
    'paged'               => $omochix_paged,
    'ignore_sticky_posts' => true,
    's'                   => $omochix_filter_search,
];

if ($omochix_query_category) {
    $omochix_query_args['cat'] = $omochix_query_category;
}
if ($omochix_filter_tag) {
    $omochix_query_args['tag'] = $omochix_filter_tag;
}

switch ($omochix_filter_order) {
    case 'oldest':
        $omochix_query_args['orderby'] = 'date';
        $omochix_query_args['order']   = 'ASC';
        break;
    case 'popular':
        // Comment count is a stable core metric and needs no plugin or custom table.
        $omochix_query_args['orderby'] = ['comment_count' => 'DESC', 'date' => 'DESC'];
        break;
    default:
        $omochix_query_args['orderby'] = 'date';
        $omochix_query_args['order']   = 'DESC';
}

$omochix_news_query = new WP_Query($omochix_query_args);

// Sidebar data remains useful even when the current filter has no results.
$omochix_popular_query = new WP_Query([
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 5,
    'orderby'             => ['comment_count' => 'DESC', 'date' => 'DESC'],
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
]);
$omochix_sidebar_categories = get_categories(['hide_empty' => true, 'number' => 8, 'orderby' => 'count', 'order' => 'DESC']);
$omochix_sidebar_tags       = get_tags(['hide_empty' => true, 'number' => 10, 'orderby' => 'count', 'order' => 'DESC']);
if (is_wp_error($omochix_sidebar_categories)) {
    $omochix_sidebar_categories = [];
}
if (is_wp_error($omochix_sidebar_tags)) {
    $omochix_sidebar_tags = [];
}
?>

<main class="news-index" id="main-content">
    <header class="news-index__hero">
        <div class="news-index__container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <li aria-current="page"><?php echo esc_html($omochix_news_title); ?></li>
                </ol>
            </nav>
            <div class="news-index__hero-copy">
                <p class="news-index__eyebrow"><?php esc_html_e('LATEST AI UPDATES', 'omochix'); ?></p>
                <h1><?php echo esc_html($omochix_news_title); ?></h1>
                <p><?php esc_html_e('AI業界の最新情報、OpenAI・Google・Anthropic・Metaなど最新情報を毎日お届けします。', 'omochix'); ?></p>
            </div>
        </div>
    </header>

    <section class="news-filter" aria-labelledby="news-filter-title">
        <div class="news-index__container">
            <h2 class="sr-only" id="news-filter-title"><?php esc_html_e('ニュースを絞り込む', 'omochix'); ?></h2>
            <form class="news-filter__form" method="get" action="<?php echo esc_url($omochix_news_url); ?>">
                <div class="news-filter__field">
                    <label for="news-category"><?php esc_html_e('カテゴリー', 'omochix'); ?></label>
                    <select id="news-category" name="news_category">
                        <option value="0" <?php selected($omochix_query_category, 0); ?>><?php esc_html_e('すべて', 'omochix'); ?></option>
                        <?php foreach ($omochix_news_categories as $omochix_category) : ?>
                            <option value="<?php echo esc_attr($omochix_category->term_id); ?>" <?php selected($omochix_query_category, $omochix_category->term_id); ?>>
                                <?php echo esc_html($omochix_category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="news-filter__field">
                    <label for="news-tag"><?php esc_html_e('タグ', 'omochix'); ?></label>
                    <select id="news-tag" name="news_tag">
                        <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                        <?php foreach ($omochix_news_tags as $omochix_tag) : ?>
                            <option value="<?php echo esc_attr($omochix_tag->slug); ?>" <?php selected($omochix_filter_tag, $omochix_tag->slug); ?>>
                                <?php echo esc_html($omochix_tag->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="news-filter__field">
                    <label for="news-order"><?php esc_html_e('並び替え', 'omochix'); ?></label>
                    <select id="news-order" name="news_order">
                        <option value="latest" <?php selected($omochix_filter_order, 'latest'); ?>><?php esc_html_e('新しい順', 'omochix'); ?></option>
                        <option value="oldest" <?php selected($omochix_filter_order, 'oldest'); ?>><?php esc_html_e('古い順', 'omochix'); ?></option>
                        <option value="popular" <?php selected($omochix_filter_order, 'popular'); ?>><?php esc_html_e('人気順', 'omochix'); ?></option>
                    </select>
                </div>
                <div class="news-filter__field news-filter__field--search">
                    <label for="news-search"><?php esc_html_e('検索', 'omochix'); ?></label>
                    <input id="news-search" name="news_search" type="search" value="<?php echo esc_attr($omochix_filter_search); ?>" placeholder="<?php esc_attr_e('ニュースを検索...', 'omochix'); ?>">
                </div>
                <button class="news-filter__submit" type="submit"><?php esc_html_e('絞り込む', 'omochix'); ?></button>
            </form>
        </div>
    </section>

    <div class="news-index__container news-index__layout">
        <section class="news-results" aria-labelledby="news-results-title">
            <header class="news-results__header">
                <h2 id="news-results-title"><?php esc_html_e('ニュース一覧', 'omochix'); ?></h2>
                <p><?php echo esc_html(sprintf(_n('%s件の記事', '%s件の記事', $omochix_news_query->found_posts, 'omochix'), number_format_i18n($omochix_news_query->found_posts))); ?></p>
            </header>

            <?php if ($omochix_news_query->have_posts()) : ?>
                <div class="news-results__grid">
                    <?php while ($omochix_news_query->have_posts()) : $omochix_news_query->the_post(); ?>
                        <?php
                        $omochix_card_image      = get_the_post_thumbnail_url(get_the_ID(), 'large');
                        $omochix_card_categories = get_the_category();
                        $omochix_card_category   = !empty($omochix_card_categories) ? $omochix_card_categories[0]->name : __('AIニュース', 'omochix');
                        $omochix_card_excerpt    = wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 46, '…');
                        ?>
                        <article class="news-list-card">
                            <a class="news-list-card__link" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf(__('記事を読む：%s', 'omochix'), get_the_title())); ?>">
                                <div class="news-list-card__media">
                                    <?php if ($omochix_card_image) : ?>
                                        <img src="<?php echo esc_url($omochix_card_image); ?>" width="640" height="360" alt="" loading="lazy" decoding="async">
                                    <?php else : ?>
                                        <div class="news-list-card__placeholder" role="img" aria-label="<?php esc_attr_e('OmochiX AIニュースのアイキャッチ画像', 'omochix'); ?>"><span aria-hidden="true">O</span></div>
                                    <?php endif; ?>
                                </div>
                                <div class="news-list-card__body">
                                    <div class="news-list-card__meta">
                                        <span><?php echo esc_html($omochix_card_category); ?></span>
                                        <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('Y.m.d')); ?></time>
                                    </div>
                                    <h3><?php echo esc_html(get_the_title()); ?></h3>
                                    <p><?php echo esc_html($omochix_card_excerpt); ?></p>
                                    <span class="news-list-card__more"><?php esc_html_e('続きを読む', 'omochix'); ?><span aria-hidden="true">→</span></span>
                                </div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php
                $omochix_pagination_args = [];
                if ($omochix_has_category_filter) {
                    $omochix_pagination_args['news_category'] = $omochix_filter_category;
                }
                if ($omochix_filter_tag) {
                    $omochix_pagination_args['news_tag'] = $omochix_filter_tag;
                }
                if ('latest' !== $omochix_filter_order) {
                    $omochix_pagination_args['news_order'] = $omochix_filter_order;
                }
                if ($omochix_filter_search) {
                    $omochix_pagination_args['news_search'] = $omochix_filter_search;
                }
                $omochix_pagination = paginate_links([
                    'total'     => $omochix_news_query->max_num_pages,
                    'current'   => $omochix_paged,
                    'mid_size'  => 1,
                    'prev_text' => __('前へ', 'omochix'),
                    'next_text' => __('次へ', 'omochix'),
                    'add_args'  => $omochix_pagination_args,
                    'type'      => 'list',
                ]);
                ?>
                <?php if ($omochix_pagination) : ?>
                    <nav class="pagination" aria-label="<?php esc_attr_e('ニュース一覧のページ送り', 'omochix'); ?>">
                        <?php echo wp_kses_post($omochix_pagination); ?>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="news-results__empty" role="status">
                    <span aria-hidden="true">O</span>
                    <p><?php esc_html_e('記事はまだありません。', 'omochix'); ?></p>
                    <a href="<?php echo esc_url($omochix_news_url); ?>"><?php esc_html_e('絞り込みを解除', 'omochix'); ?></a>
                </div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </section>

        <aside class="news-sidebar" aria-label="<?php esc_attr_e('AIニュースの補助情報', 'omochix'); ?>">
            <section class="sidebar-panel" aria-labelledby="popular-posts-title">
                <h2 id="popular-posts-title"><?php esc_html_e('人気記事', 'omochix'); ?></h2>
                <?php if ($omochix_popular_query->have_posts()) : ?>
                    <ol class="sidebar-posts">
                        <?php while ($omochix_popular_query->have_posts()) : $omochix_popular_query->the_post(); ?>
                            <li><a href="<?php the_permalink(); ?>"><span><?php echo esc_html(get_the_title()); ?></span><time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('Y.m.d')); ?></time></a></li>
                        <?php endwhile; ?>
                    </ol>
                <?php else : ?>
                    <p class="sidebar-panel__empty"><?php esc_html_e('人気記事を準備中です。', 'omochix'); ?></p>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            </section>

            <section class="sidebar-panel" aria-labelledby="popular-categories-title">
                <h2 id="popular-categories-title"><?php esc_html_e('人気カテゴリー', 'omochix'); ?></h2>
                <?php if (!empty($omochix_sidebar_categories)) : ?>
                    <ul class="sidebar-links">
                        <?php foreach ($omochix_sidebar_categories as $omochix_sidebar_category) : ?>
                            <li><a href="<?php echo esc_url(get_category_link($omochix_sidebar_category)); ?>"><span><?php echo esc_html($omochix_sidebar_category->name); ?></span><small><?php echo esc_html(number_format_i18n($omochix_sidebar_category->count)); ?></small></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="sidebar-panel__empty"><?php esc_html_e('カテゴリーを準備中です。', 'omochix'); ?></p>
                <?php endif; ?>
            </section>

            <section class="sidebar-panel" aria-labelledby="popular-tags-title">
                <h2 id="popular-tags-title"><?php esc_html_e('人気タグ', 'omochix'); ?></h2>
                <?php if (!empty($omochix_sidebar_tags)) : ?>
                    <div class="sidebar-tags">
                        <?php foreach ($omochix_sidebar_tags as $omochix_sidebar_tag) : ?>
                            <a href="<?php echo esc_url(get_tag_link($omochix_sidebar_tag)); ?>"><?php echo esc_html($omochix_sidebar_tag->name); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="sidebar-panel__empty"><?php esc_html_e('タグを準備中です。', 'omochix'); ?></p>
                <?php endif; ?>
            </section>

            <section class="sidebar-newsletter" aria-labelledby="sidebar-newsletter-title">
                <p><?php esc_html_e('OMOCHIX NEWSLETTER', 'omochix'); ?></p>
                <h2 id="sidebar-newsletter-title"><?php esc_html_e('AIの最新情報を見逃さない。', 'omochix'); ?></h2>
                <a href="<?php echo esc_url(home_url('/#newsletter-title')); ?>"><?php esc_html_e('Newsletterを見る', 'omochix'); ?><span aria-hidden="true">→</span></a>
            </section>
        </aside>
    </div>
</main>

<?php get_footer(); ?>
