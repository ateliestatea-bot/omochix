<?php
/**
 * Category archive.
 *
 * WordPress's template hierarchy falls through category-{slug}.php,
 * category.php, archive.php, then index.php in that order. Before this file
 * existed, every category archive (not just /category/ai-development/)
 * silently rendered index.php's placeholder hero instead of its post list.
 *
 * The markup, classes, and query shape below intentionally mirror home.php's
 * "AI News" post list template (card grid, pagination, sidebar) so this page
 * reuses every existing style rule — light/dark mode, mobile/desktop layout,
 * and hover states all come from CSS that already ships for home.php, with
 * nothing new added here.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $wp_query;

$omochix_category_term = get_queried_object();
$omochix_category_name = $omochix_category_term instanceof WP_Term
    ? $omochix_category_term->name
    : single_cat_title('', false);
$omochix_category_description = $omochix_category_term instanceof WP_Term
    ? trim(wp_strip_all_tags(category_description($omochix_category_term->term_id)))
    : '';
$omochix_category_ancestors = $omochix_category_term instanceof WP_Term
    ? omochix_get_category_ancestors($omochix_category_term)
    : [];

// Opt-in hub configuration (curated tagline, "read first" picks, topic nav)
// for select categories. Categories with no entry keep the plain name +
// description + article grid this template already had.
$omochix_hub = $omochix_category_term instanceof WP_Term
    ? omochix_get_category_hub_config($omochix_category_term->slug)
    : null;

// Hub categories show a curated tagline plus the live article count in the
// same hero paragraph the plain category description would otherwise use;
// every other category keeps showing category_description() unchanged.
$omochix_hero_lead = $omochix_hub
    ? sprintf(
        /* translators: 1: hub tagline, 2: number of published articles. */
        __('%1$s（現在%2$s件の記事を掲載中）', 'omochix'),
        $omochix_hub['tagline'],
        number_format_i18n($wp_query->found_posts)
    )
    : $omochix_category_description;

$omochix_paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));

// Resolve the hub's "read first" picks from their real, existing posts by
// URL, so the cards always reflect the actual post (thumbnail, permalink);
// the display title and description are overridden with the curated hub
// config copy rather than the post's own title/excerpt — article excerpts
// can surface incidental in-article hero label text (e.g. an English
// heading fragment) that reads poorly as hub navigation copy. A pick that
// no longer resolves to a real post is skipped, never a broken link.
$omochix_hub_read_first_query = null;
$omochix_hub_titles           = [];
$omochix_hub_descriptions     = [];
if ($omochix_hub && !empty($omochix_hub['read_first'])) {
    $omochix_hub_post_ids = [];
    foreach ($omochix_hub['read_first'] as $omochix_pick) {
        $omochix_pick_id = url_to_postid($omochix_pick['url']);
        if ($omochix_pick_id) {
            $omochix_hub_post_ids[]                     = $omochix_pick_id;
            $omochix_hub_titles[$omochix_pick_id]       = $omochix_pick['title'];
            $omochix_hub_descriptions[$omochix_pick_id] = $omochix_pick['description'] ?? '';
        }
    }
    if ($omochix_hub_post_ids) {
        $omochix_hub_read_first_query = new WP_Query([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'post__in'            => $omochix_hub_post_ids,
            'orderby'             => 'post__in',
            'posts_per_page'      => count($omochix_hub_post_ids),
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ]);
    }
}

// Resolve topic navigation links from real, existing tags/categories only.
// A topic with no matching term is skipped entirely — never an anchor to
// nowhere, never a search fallback.
$omochix_hub_topics = [];
if ($omochix_hub && !empty($omochix_hub['topics'])) {
    foreach ($omochix_hub['topics'] as $omochix_topic) {
        $omochix_topic_term = get_term_by('slug', $omochix_topic['slug'], $omochix_topic['taxonomy']);
        if (!($omochix_topic_term instanceof WP_Term)) {
            continue;
        }
        $omochix_topic_url = 'category' === $omochix_topic['taxonomy']
            ? omochix_get_category_url($omochix_topic_term)
            : get_tag_link($omochix_topic_term);
        if ($omochix_topic_url && !is_wp_error($omochix_topic_url)) {
            $omochix_hub_topics[] = [
                'name'        => $omochix_topic['name'],
                'description' => $omochix_topic['description'] ?? '',
                'url'         => $omochix_topic_url,
            ];
        }
    }
}

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
                    <?php foreach ($omochix_category_ancestors as $omochix_ancestor_category) : ?>
                        <li><a href="<?php echo esc_url(omochix_get_category_url($omochix_ancestor_category)); ?>"><?php echo esc_html($omochix_ancestor_category->name); ?></a></li>
                    <?php endforeach; ?>
                    <li aria-current="page"><?php echo esc_html($omochix_category_name); ?></li>
                </ol>
            </nav>
            <div class="news-index__hero-copy">
                <p class="news-index__eyebrow"><?php esc_html_e('CATEGORY', 'omochix'); ?></p>
                <h1><?php echo esc_html($omochix_category_name); ?></h1>
                <?php if ($omochix_hero_lead) : ?>
                    <p><?php echo esc_html($omochix_hero_lead); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ($omochix_hub_read_first_query && $omochix_hub_read_first_query->have_posts()) : ?>
        <section class="news-index__container" aria-labelledby="hub-read-first-title">
            <header class="news-results__header">
                <h2 id="hub-read-first-title"><?php esc_html_e('まず読む3本', 'omochix'); ?></h2>
            </header>
            <div class="hub-picks__grid">
                <?php $omochix_pick_number = 0; ?>
                <?php while ($omochix_hub_read_first_query->have_posts()) : $omochix_hub_read_first_query->the_post(); ?>
                    <?php
                    $omochix_pick_number++;
                    $omochix_pick_image   = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
                    $omochix_pick_title   = $omochix_hub_titles[get_the_ID()] ?? get_the_title();
                    $omochix_pick_excerpt = $omochix_hub_descriptions[get_the_ID()] ?? '';
                    if (!$omochix_pick_excerpt) {
                        $omochix_pick_excerpt = wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 22, '…');
                    }
                    ?>
                    <article class="hub-pick-card">
                        <a class="hub-pick-card__link" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf(__('記事を読む：%s', 'omochix'), $omochix_pick_title)); ?>">
                            <div class="hub-pick-card__top">
                                <span class="hub-pick-card__number"><?php echo esc_html(sprintf('%02d', $omochix_pick_number)); ?></span>
                                <?php if ($omochix_pick_image) : ?>
                                    <span class="hub-pick-card__media"><img src="<?php echo esc_url($omochix_pick_image); ?>" width="40" height="40" alt="" loading="lazy" decoding="async"></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="hub-pick-card__title"><?php echo esc_html($omochix_pick_title); ?></h3>
                            <p class="hub-pick-card__excerpt"><?php echo esc_html($omochix_pick_excerpt); ?></p>
                            <span class="hub-pick-card__cta"><?php esc_html_e('読む', 'omochix'); ?><span aria-hidden="true">→</span></span>
                        </a>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($omochix_hub_topics) : ?>
        <section class="news-index__container" aria-labelledby="hub-topics-title">
            <header class="news-results__header">
                <h2 id="hub-topics-title"><?php esc_html_e('トピックから探す', 'omochix'); ?></h2>
            </header>
            <div class="hub-topics__grid">
                <?php foreach ($omochix_hub_topics as $omochix_topic_link) : ?>
                    <a class="hub-topic-card" href="<?php echo esc_url($omochix_topic_link['url']); ?>">
                        <span class="hub-topic-card__text">
                            <span class="hub-topic-card__name"><?php echo esc_html($omochix_topic_link['name']); ?></span>
                            <?php if ($omochix_topic_link['description']) : ?>
                                <span class="hub-topic-card__description"><?php echo esc_html($omochix_topic_link['description']); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="hub-topic-card__arrow" aria-hidden="true">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="news-index__container news-index__layout">
        <section class="news-results" aria-labelledby="news-results-title">
            <header class="news-results__header">
                <h2 id="news-results-title"><?php echo esc_html(sprintf(__('%sの記事一覧', 'omochix'), $omochix_category_name)); ?></h2>
                <p><?php echo esc_html(sprintf(_n('%s件の記事', '%s件の記事', $wp_query->found_posts, 'omochix'), number_format_i18n($wp_query->found_posts))); ?></p>
            </header>

            <?php if (have_posts()) : ?>
                <div class="news-results__grid">
                    <?php while (have_posts()) : the_post(); ?>
                        <?php
                        $omochix_card_image      = get_the_post_thumbnail_url(get_the_ID(), 'large');
                        $omochix_card_categories = get_the_category();
                        $omochix_card_category   = !empty($omochix_card_categories) ? $omochix_card_categories[0]->name : $omochix_category_name;
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
                    <p><?php esc_html_e('このカテゴリーの記事はまだありません。', 'omochix'); ?></p>
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('トップページに戻る', 'omochix'); ?></a>
                </div>
            <?php endif; ?>
        </section>

        <aside class="news-sidebar" aria-label="<?php esc_attr_e('カテゴリーの補助情報', 'omochix'); ?>">
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
