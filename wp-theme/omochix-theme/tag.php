<?php
/**
 * Tag archive.
 *
 * WordPress's template hierarchy falls through tag-{slug}.php, tag-{id}.php,
 * tag.php, archive.php, then index.php in that order. Before this file
 * existed, every tag archive (e.g. /tag/claude-code/, /tag/mcp/) silently
 * fell all the way through to index.php's placeholder hero instead of its
 * post list — the same gap category.php already fixed for category
 * archives.
 *
 * This intentionally reuses category.php's plain article-list markup
 * (breadcrumb, eyebrow, h1, description, count, news-list-card grid,
 * pagination, sidebar) so it inherits the exact same CSS — light/dark mode,
 * mobile/desktop layout, and hover states all come from styles that already
 * ship for category.php/home.php, with nothing new added here. Unlike
 * category.php, this never shows the AI-development hub sections ("read
 * first" picks / topic navigation) — that configuration is opt-in per
 * category slug only (see omochix_get_category_hub_config()) and has no
 * equivalent for tags, so tag archives are always the plain article list.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $wp_query;

$omochix_tag_term = get_queried_object();
$omochix_tag_name = $omochix_tag_term instanceof WP_Term
    ? $omochix_tag_term->name
    : single_tag_title('', false);
$omochix_tag_description = $omochix_tag_term instanceof WP_Term
    ? trim(wp_strip_all_tags(tag_description($omochix_tag_term->term_id)))
    : '';
$omochix_tag_lead = $omochix_tag_description
    ? $omochix_tag_description
    : sprintf(
        /* translators: %s: tag name. */
        __('%sに関する記事一覧', 'omochix'),
        $omochix_tag_name
    );

$omochix_paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));

$omochix_sidebar_categories = get_categories(['hide_empty' => true, 'number' => 8, 'orderby' => 'count', 'order' => 'DESC']);
$omochix_sidebar_tags       = get_tags(['hide_empty' => true, 'number' => 10, 'orderby' => 'count', 'order' => 'DESC']);
if (is_wp_error($omochix_sidebar_categories)) {
    $omochix_sidebar_categories = [];
}
if (is_wp_error($omochix_sidebar_tags)) {
    $omochix_sidebar_tags = [];
}

$omochix_popular_query = new WP_Query([
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 5,
    'orderby'             => ['comment_count' => 'DESC', 'date' => 'DESC'],
    'ignore_sticky_posts' => true,
    'no_found_rows'       => true,
]);
?>

<main class="news-index" id="main-content">
    <header class="news-index__hero">
        <div class="news-index__container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <li aria-current="page"><?php echo esc_html($omochix_tag_name); ?></li>
                </ol>
            </nav>
            <div class="news-index__hero-copy">
                <p class="news-index__eyebrow"><?php esc_html_e('TAG', 'omochix'); ?></p>
                <h1><?php echo esc_html($omochix_tag_name); ?></h1>
                <?php if ($omochix_tag_lead) : ?>
                    <p><?php echo esc_html($omochix_tag_lead); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="news-index__container news-index__layout">
        <section class="news-results" aria-labelledby="news-results-title">
            <header class="news-results__header">
                <h2 id="news-results-title"><?php echo esc_html(sprintf(__('%sの記事一覧', 'omochix'), $omochix_tag_name)); ?></h2>
                <p><?php echo esc_html(sprintf(_n('%s件の記事', '%s件の記事', $wp_query->found_posts, 'omochix'), number_format_i18n($wp_query->found_posts))); ?></p>
            </header>

            <?php if (have_posts()) : ?>
                <div class="news-results__grid">
                    <?php while (have_posts()) : the_post(); ?>
                        <?php
                        $omochix_card_image      = get_the_post_thumbnail_url(get_the_ID(), 'large');
                        $omochix_card_categories = get_the_category();
                        $omochix_card_category   = !empty($omochix_card_categories) ? $omochix_card_categories[0]->name : $omochix_tag_name;
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
                $omochix_pagination = paginate_links([
                    'total'     => $wp_query->max_num_pages,
                    'current'   => $omochix_paged,
                    'mid_size'  => 1,
                    'prev_text' => __('前へ', 'omochix'),
                    'next_text' => __('次へ', 'omochix'),
                    'type'      => 'list',
                ]);
                ?>
                <?php if ($omochix_pagination) : ?>
                    <nav class="pagination" aria-label="<?php esc_attr_e('記事一覧のページ送り', 'omochix'); ?>">
                        <?php echo wp_kses_post($omochix_pagination); ?>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="news-results__empty" role="status">
                    <span aria-hidden="true">O</span>
                    <p><?php esc_html_e('このタグの記事はまだありません。', 'omochix'); ?></p>
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('トップページに戻る', 'omochix'); ?></a>
                </div>
            <?php endif; ?>
        </section>

        <aside class="news-sidebar" aria-label="<?php esc_attr_e('タグの補助情報', 'omochix'); ?>">
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
                            <li><a href="<?php echo esc_url(omochix_get_category_url($omochix_sidebar_category)); ?>"><span><?php echo esc_html($omochix_sidebar_category->name); ?></span><small><?php echo esc_html(number_format_i18n($omochix_sidebar_category->count)); ?></small></a></li>
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
