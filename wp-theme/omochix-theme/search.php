<?php
/**
 * Unified site search results.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

global $wp_query;

$omochix_keyword = get_search_query();
$omochix_type    = isset($_GET['content_type']) ? sanitize_key(wp_unslash($_GET['content_type'])) : 'all';
if (!in_array($omochix_type, ['all', 'news', 'tools'], true)) {
    $omochix_type = 'all';
}
$omochix_found = (int) $wp_query->found_posts;
$omochix_paged = max(1, (int) get_query_var('paged'));
$omochix_news_url = get_option('page_for_posts')
    ? get_permalink((int) get_option('page_for_posts'))
    : home_url('/');
$omochix_tools_url = get_post_type_archive_link('ai_tool') ?: home_url('/ai-tools/');
$omochix_popular_searches = ['ChatGPT', 'Claude', 'Midjourney', 'Gemini', 'Copilot', 'AIエージェント'];

$omochix_type_links = [
    'all'   => __('すべて', 'omochix'),
    'news'  => __('AIニュース', 'omochix'),
    'tools' => __('AIツール', 'omochix'),
];
?>

<main class="search-page" id="main-content">
    <header class="search-page__hero">
        <div class="search-page__container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <li aria-current="page"><?php esc_html_e('検索結果', 'omochix'); ?></li>
                </ol>
            </nav>
            <div class="search-page__hero-copy">
                <p class="search-page__eyebrow"><?php esc_html_e('SEARCH OMOCHIX', 'omochix'); ?></p>
                <h1><?php echo esc_html(sprintf(__('「%s」の検索結果', 'omochix'), $omochix_keyword)); ?></h1>
                <p aria-live="polite"><?php echo esc_html(sprintf(_n('%s件の結果が見つかりました', '%s件の結果が見つかりました', $omochix_found, 'omochix'), number_format_i18n($omochix_found))); ?></p>
            </div>

            <form class="search-page__form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                <label for="search-page-input"><?php esc_html_e('OmochiXを検索', 'omochix'); ?></label>
                <div>
                    <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>
                    <input id="search-page-input" name="s" type="search" value="<?php echo esc_attr($omochix_keyword); ?>" placeholder="<?php esc_attr_e('記事、ツール、トピックを検索', 'omochix'); ?>" required enterkeyhint="search">
                    <?php if ('all' !== $omochix_type) : ?><input type="hidden" name="content_type" value="<?php echo esc_attr($omochix_type); ?>"><?php endif; ?>
                    <button type="submit"><?php esc_html_e('検索', 'omochix'); ?></button>
                </div>
            </form>
        </div>
    </header>

    <section class="search-type-filter" aria-labelledby="search-type-title">
        <div class="search-page__container">
            <h2 class="sr-only" id="search-type-title"><?php esc_html_e('検索対象を選ぶ', 'omochix'); ?></h2>
            <nav aria-label="<?php esc_attr_e('投稿タイプフィルター', 'omochix'); ?>">
                <ul>
                    <?php foreach ($omochix_type_links as $omochix_value => $omochix_label) :
                        $omochix_url = add_query_arg(
                            array_filter(['s' => $omochix_keyword, 'content_type' => 'all' !== $omochix_value ? $omochix_value : '']),
                            home_url('/')
                        );
                        ?>
                        <li><a href="<?php echo esc_url($omochix_url); ?>" <?php if ($omochix_type === $omochix_value) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html($omochix_label); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </section>

    <section class="search-results" aria-labelledby="search-results-title">
        <div class="search-page__container">
            <header class="search-results__header">
                <h2 id="search-results-title"><?php esc_html_e('検索結果一覧', 'omochix'); ?></h2>
                <p><?php echo esc_html(sprintf(_n('%s件', '%s件', $omochix_found, 'omochix'), number_format_i18n($omochix_found))); ?></p>
            </header>

            <?php if (have_posts()) : ?>
                <div class="search-results__grid">
                    <?php while (have_posts()) : the_post(); ?>
                        <?php if ('ai_tool' === get_post_type()) :
                            $omochix_tool_id = get_the_ID();
                            $omochix_logo_id = absint(get_post_meta($omochix_tool_id, 'tool_logo', true));
                            $omochix_company = get_post_meta($omochix_tool_id, 'company_name', true);
                            $omochix_description = get_post_meta($omochix_tool_id, 'short_description', true) ?: get_the_excerpt();
                            $omochix_pricing = get_post_meta($omochix_tool_id, 'pricing_type', true) ?: 'contact';
                            $omochix_japanese = get_post_meta($omochix_tool_id, 'japanese_support', true) ?: 'unknown';
                            $omochix_rating = (float) get_post_meta($omochix_tool_id, 'rating_overall', true);
                            $omochix_pricing_label = function_exists('omochix_core_get_pricing_type_label') ? omochix_core_get_pricing_type_label($omochix_pricing) : __('料金未確認', 'omochix');
                            $omochix_japanese_label = function_exists('omochix_core_get_japanese_support_label') ? omochix_core_get_japanese_support_label($omochix_japanese) : __('日本語対応未確認', 'omochix');
                            ?>
                            <article class="search-card search-card--tool">
                                <a class="search-card__link" href="<?php the_permalink(); ?>">
                                    <div class="search-tool-card__top">
                                        <div class="search-tool-card__logo">
                                            <?php if ($omochix_logo_id) : ?>
                                                <?php echo wp_kses_post(wp_get_attachment_image($omochix_logo_id, 'thumbnail', false, ['alt' => '', 'width' => 64, 'height' => 64, 'loading' => 'lazy'])); ?>
                                            <?php else : ?>
                                                <span aria-hidden="true"><?php echo esc_html(function_exists('mb_substr') ? mb_substr(get_the_title(), 0, 1) : substr(get_the_title(), 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="search-card__type"><?php esc_html_e('AIツール', 'omochix'); ?></span>
                                    </div>
                                    <div class="search-tool-card__identity">
                                        <p><?php echo esc_html($omochix_company ?: __('運営会社情報なし', 'omochix')); ?></p>
                                        <h3><?php the_title(); ?></h3>
                                    </div>
                                    <?php if ($omochix_description) : ?><p class="search-card__excerpt"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($omochix_description), 42, '…')); ?></p><?php endif; ?>
                                    <ul class="search-tool-card__facts" aria-label="<?php esc_attr_e('ツール情報', 'omochix'); ?>">
                                        <li><?php echo esc_html($omochix_pricing_label); ?></li>
                                        <li><?php echo esc_html($omochix_japanese_label); ?></li>
                                        <li><?php echo $omochix_rating > 0 ? esc_html(sprintf(__('編集部評価 %s', 'omochix'), number_format_i18n($omochix_rating, 1))) : esc_html__('未評価', 'omochix'); ?></li>
                                    </ul>
                                    <span class="search-card__more"><?php esc_html_e('ツールを見る', 'omochix'); ?><span aria-hidden="true">→</span></span>
                                </a>
                            </article>
                        <?php else :
                            $omochix_image = get_the_post_thumbnail_url(get_the_ID(), 'large');
                            $omochix_categories = get_the_category();
                            ?>
                            <article class="search-card search-card--post">
                                <a class="search-card__link" href="<?php the_permalink(); ?>">
                                    <div class="search-post-card__media">
                                        <?php if ($omochix_image) : ?><img src="<?php echo esc_url($omochix_image); ?>" width="640" height="360" alt="" loading="lazy" decoding="async"><?php else : ?><div aria-hidden="true">O</div><?php endif; ?>
                                        <span class="search-card__type"><?php esc_html_e('AIニュース', 'omochix'); ?></span>
                                    </div>
                                    <div class="search-post-card__body">
                                        <div class="search-post-card__meta"><span><?php echo esc_html($omochix_categories ? $omochix_categories[0]->name : __('AIニュース', 'omochix')); ?></span><time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('Y.m.d')); ?></time></div>
                                        <h3><?php the_title(); ?></h3>
                                        <p class="search-card__excerpt"><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 42, '…')); ?></p>
                                        <span class="search-card__more"><?php esc_html_e('記事を読む', 'omochix'); ?><span aria-hidden="true">→</span></span>
                                    </div>
                                </a>
                            </article>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </div>

                <?php
                $omochix_pagination = paginate_links([
                    'total'     => $wp_query->max_num_pages,
                    'current'   => $omochix_paged,
                    'mid_size'  => 1,
                    'prev_text' => __('前へ', 'omochix'),
                    'next_text' => __('次へ', 'omochix'),
                    'add_args'  => 'all' !== $omochix_type ? ['content_type' => $omochix_type] : [],
                    'type'      => 'list',
                ]);
                ?>
                <?php if ($omochix_pagination) : ?><nav class="pagination" aria-label="<?php esc_attr_e('検索結果のページ送り', 'omochix'); ?>"><?php echo wp_kses_post($omochix_pagination); ?></nav><?php endif; ?>
            <?php else : ?>
                <div class="search-empty" role="status">
                    <span aria-hidden="true">O</span>
                    <h2><?php esc_html_e('該当する情報が見つかりませんでした。', 'omochix'); ?></h2>
                    <p><?php esc_html_e('キーワードを短くするか、別の表記でもう一度お試しください。', 'omochix'); ?></p>
                    <div class="search-empty__links"><a href="<?php echo esc_url($omochix_news_url); ?>"><?php esc_html_e('AIニュース一覧', 'omochix'); ?></a><a href="<?php echo esc_url($omochix_tools_url); ?>"><?php esc_html_e('AIツール一覧', 'omochix'); ?></a></div>
                    <div class="search-empty__popular"><p><?php esc_html_e('人気の検索ワード', 'omochix'); ?></p><ul><?php foreach ($omochix_popular_searches as $omochix_term) : ?><li><a href="<?php echo esc_url(add_query_arg('s', $omochix_term, home_url('/'))); ?>"><?php echo esc_html($omochix_term); ?></a></li><?php endforeach; ?></ul></div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php get_footer(); ?>
