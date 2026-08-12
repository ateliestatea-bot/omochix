<?php
/**
 * AI tool archive.
 *
 * Filters use WordPress query APIs and remain fully functional without JavaScript.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$omochix_archive_url = get_post_type_archive_link('ai_tool');
$omochix_archive_url = $omochix_archive_url ?: home_url('/ai-tools/');
$omochix_paged       = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));

// Validate every public query parameter before it reaches WP_Query.
$omochix_search   = isset($_GET['tool_search']) ? sanitize_text_field(wp_unslash($_GET['tool_search'])) : '';
$omochix_category = isset($_GET['tool_category']) ? sanitize_title(wp_unslash($_GET['tool_category'])) : '';
$omochix_pricing  = isset($_GET['pricing']) ? sanitize_key(wp_unslash($_GET['pricing'])) : '';
$omochix_japanese = isset($_GET['japanese']) ? sanitize_key(wp_unslash($_GET['japanese'])) : '';
$omochix_platform = isset($_GET['platform']) ? sanitize_title(wp_unslash($_GET['platform'])) : '';
$omochix_order    = isset($_GET['tool_order']) ? sanitize_key(wp_unslash($_GET['tool_order'])) : 'latest';

$omochix_pricing_options = [
    'free'     => __('無料', 'omochix'),
    'freemium' => __('無料プランあり', 'omochix'),
    'paid'     => __('有料', 'omochix'),
    'trial'    => __('無料体験あり', 'omochix'),
    'contact'  => __('要問い合わせ', 'omochix'),
];
$omochix_japanese_options = [
    'full'    => __('日本語対応', 'omochix'),
    'partial' => __('一部日本語対応', 'omochix'),
    'none'    => __('日本語非対応', 'omochix'),
    'unknown' => __('未確認', 'omochix'),
];
$omochix_order_options = [
    'latest' => __('新着順', 'omochix'),
    'rating' => __('評価が高い順', 'omochix'),
    'name'   => __('名前順', 'omochix'),
];

if (!array_key_exists($omochix_pricing, $omochix_pricing_options)) {
    $omochix_pricing = '';
}
if (!array_key_exists($omochix_japanese, $omochix_japanese_options)) {
    $omochix_japanese = '';
}
if (!array_key_exists($omochix_order, $omochix_order_options)) {
    $omochix_order = 'latest';
}

$omochix_categories = get_terms([
    'taxonomy'   => 'ai_tool_category',
    'hide_empty' => true,
    'orderby'    => 'name',
]);
$omochix_platforms = get_terms([
    'taxonomy'   => 'ai_tool_platform',
    'hide_empty' => true,
    'orderby'    => 'name',
]);
if (is_wp_error($omochix_categories)) {
    $omochix_categories = [];
}
if (is_wp_error($omochix_platforms)) {
    $omochix_platforms = [];
}

$omochix_tax_query = [];
if ($omochix_category && term_exists($omochix_category, 'ai_tool_category')) {
    $omochix_tax_query[] = [
        'taxonomy' => 'ai_tool_category',
        'field'    => 'slug',
        'terms'    => $omochix_category,
    ];
} else {
    $omochix_category = '';
}
if ($omochix_platform && term_exists($omochix_platform, 'ai_tool_platform')) {
    $omochix_tax_query[] = [
        'taxonomy' => 'ai_tool_platform',
        'field'    => 'slug',
        'terms'    => $omochix_platform,
    ];
} else {
    $omochix_platform = '';
}

$omochix_meta_query = [];
if ($omochix_pricing) {
    $omochix_pricing_compat = [
        'free'     => ['free', '無料'],
        'freemium' => ['freemium', '無料・有料', '無料プランあり'],
        'paid'     => ['paid', '有料'],
        'trial'    => ['trial', '無料体験', '無料体験あり'],
        'contact'  => ['contact', '要問い合わせ', '料金情報なし'],
    ];
    $omochix_meta_query[] = [
        'key'     => 'pricing_type',
        'value'   => $omochix_pricing_compat[$omochix_pricing],
        'compare' => 'IN',
    ];
}
if ($omochix_japanese) {
    $omochix_japanese_compat = [
        'full'    => ['full', '1', 'true', 'yes', 'on'],
        'partial' => ['partial'],
        'none'    => ['none', '0', 'false', 'no', 'off', ''],
        'unknown' => ['unknown'],
    ];
    $omochix_meta_query[] = [
        'key'     => 'japanese_support',
        'value'   => $omochix_japanese_compat[$omochix_japanese],
        'compare' => 'IN',
    ];
}

$omochix_query_args = [
    'post_type'      => 'ai_tool',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $omochix_paged,
    's'              => $omochix_search,
];
if ($omochix_tax_query) {
    $omochix_query_args['tax_query'] = $omochix_tax_query;
}
if ($omochix_meta_query) {
    $omochix_query_args['meta_query'] = $omochix_meta_query;
}

switch ($omochix_order) {
    case 'name':
        $omochix_query_args['orderby'] = 'title';
        $omochix_query_args['order']   = 'ASC';
        break;
    case 'rating':
        // Keep unrated tools in the result while ordering rated tools first.
        $omochix_rating_clause = [
            'relation'       => 'OR',
            'rating_value'   => [
                'key'     => 'rating_overall',
                'compare' => 'EXISTS',
                'type'    => 'DECIMAL',
            ],
            'rating_empty'   => [
                'key'     => 'rating_overall',
                'compare' => 'NOT EXISTS',
            ],
        ];
        if ($omochix_meta_query) {
            $omochix_query_args['meta_query'] = [
                'relation' => 'AND',
                ...$omochix_meta_query,
                $omochix_rating_clause,
            ];
        } else {
            $omochix_query_args['meta_query'] = $omochix_rating_clause;
        }
        $omochix_query_args['orderby'] = [
            'rating_value' => 'DESC',
            'date'         => 'DESC',
        ];
        break;
    default:
        $omochix_query_args['orderby'] = 'date';
        $omochix_query_args['order']   = 'DESC';
}

$omochix_tools_query = new WP_Query($omochix_query_args);

// Featured tools provide a stable core-only definition of popularity for MVP.
$omochix_popular_query = new WP_Query([
    'post_type'           => 'ai_tool',
    'post_status'         => 'publish',
    'posts_per_page'      => 5,
    'meta_key'            => 'is_featured',
    'meta_value'          => '1',
    'orderby'             => ['date' => 'DESC'],
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
]);
if (!$omochix_popular_query->have_posts()) {
    $omochix_popular_query = new WP_Query([
        'post_type'           => 'ai_tool',
        'post_status'         => 'publish',
        'posts_per_page'      => 5,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ]);
}

$omochix_sidebar_categories = get_terms([
    'taxonomy'   => 'ai_tool_category',
    'hide_empty' => true,
    'number'     => 8,
    'orderby'    => 'count',
    'order'      => 'DESC',
]);
if (is_wp_error($omochix_sidebar_categories)) {
    $omochix_sidebar_categories = [];
}
?>

<main class="tool-archive" id="main-content">
    <header class="tool-archive__hero">
        <div class="tool-archive__container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <li aria-current="page"><?php esc_html_e('AIツール', 'omochix'); ?></li>
                </ol>
            </nav>
            <div class="tool-archive__hero-grid">
                <div class="tool-archive__hero-copy">
                    <p class="tool-archive__eyebrow"><?php esc_html_e('FIND YOUR AI TOOL', 'omochix'); ?></p>
                    <h1><?php esc_html_e('AIツール', 'omochix'); ?></h1>
                    <p><?php esc_html_e('目的や使い方に合うAIツールを、料金・日本語対応・利用環境から比較して見つけられます。', 'omochix'); ?></p>
                </div>
                <form class="tool-archive__search" role="search" method="get" action="<?php echo esc_url($omochix_archive_url); ?>">
                    <label for="tool-hero-search"><?php esc_html_e('AIツールを検索', 'omochix'); ?></label>
                    <div>
                        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>
                        <input id="tool-hero-search" name="tool_search" type="search" value="<?php echo esc_attr($omochix_search); ?>" placeholder="<?php esc_attr_e('ツール名や用途を検索...', 'omochix'); ?>">
                        <button type="submit"><?php esc_html_e('検索', 'omochix'); ?></button>
                    </div>
                    <p aria-live="polite"><?php echo esc_html(sprintf(_n('%s件のAIツール', '%s件のAIツール', $omochix_tools_query->found_posts, 'omochix'), number_format_i18n($omochix_tools_query->found_posts))); ?></p>
                </form>
            </div>
        </div>
    </header>

    <section class="tool-filter" aria-labelledby="tool-filter-title">
        <div class="tool-archive__container">
            <h2 class="sr-only" id="tool-filter-title"><?php esc_html_e('AIツールを絞り込む', 'omochix'); ?></h2>
            <form class="tool-filter__form" method="get" action="<?php echo esc_url($omochix_archive_url); ?>">
                <?php if ($omochix_search) : ?>
                    <input type="hidden" name="tool_search" value="<?php echo esc_attr($omochix_search); ?>">
                <?php endif; ?>
                <div class="tool-filter__field">
                    <label for="tool-category"><?php esc_html_e('カテゴリー', 'omochix'); ?></label>
                    <select id="tool-category" name="tool_category">
                        <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                        <?php foreach ($omochix_categories as $omochix_term) : ?>
                            <option value="<?php echo esc_attr($omochix_term->slug); ?>" <?php selected($omochix_category, $omochix_term->slug); ?>><?php echo esc_html($omochix_term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tool-filter__field">
                    <label for="tool-pricing"><?php esc_html_e('料金', 'omochix'); ?></label>
                    <select id="tool-pricing" name="pricing">
                        <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                        <?php foreach ($omochix_pricing_options as $omochix_value => $omochix_label) : ?>
                            <option value="<?php echo esc_attr($omochix_value); ?>" <?php selected($omochix_pricing, $omochix_value); ?>><?php echo esc_html($omochix_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tool-filter__field">
                    <label for="tool-japanese"><?php esc_html_e('日本語対応', 'omochix'); ?></label>
                    <select id="tool-japanese" name="japanese">
                        <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                        <?php foreach ($omochix_japanese_options as $omochix_value => $omochix_label) : ?>
                            <option value="<?php echo esc_attr($omochix_value); ?>" <?php selected($omochix_japanese, $omochix_value); ?>><?php echo esc_html($omochix_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tool-filter__field">
                    <label for="tool-platform"><?php esc_html_e('対応OS', 'omochix'); ?></label>
                    <select id="tool-platform" name="platform">
                        <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                        <?php foreach ($omochix_platforms as $omochix_term) : ?>
                            <option value="<?php echo esc_attr($omochix_term->slug); ?>" <?php selected($omochix_platform, $omochix_term->slug); ?>><?php echo esc_html($omochix_term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tool-filter__field">
                    <label for="tool-order"><?php esc_html_e('並び順', 'omochix'); ?></label>
                    <select id="tool-order" name="tool_order">
                        <?php foreach ($omochix_order_options as $omochix_value => $omochix_label) : ?>
                            <option value="<?php echo esc_attr($omochix_value); ?>" <?php selected($omochix_order, $omochix_value); ?>><?php echo esc_html($omochix_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="tool-filter__submit" type="submit"><?php esc_html_e('絞り込む', 'omochix'); ?></button>
                <?php if ($omochix_search || $omochix_category || $omochix_pricing || $omochix_japanese || $omochix_platform || 'latest' !== $omochix_order) : ?>
                    <a class="tool-filter__reset" href="<?php echo esc_url($omochix_archive_url); ?>"><?php esc_html_e('条件をクリア', 'omochix'); ?></a>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <div class="tool-archive__container tool-archive__layout">
        <section class="tool-results" aria-labelledby="tool-results-title">
            <header class="tool-results__header">
                <h2 id="tool-results-title"><?php esc_html_e('ツール一覧', 'omochix'); ?></h2>
                <p><?php echo esc_html(sprintf(_n('%s件', '%s件', $omochix_tools_query->found_posts, 'omochix'), number_format_i18n($omochix_tools_query->found_posts))); ?></p>
            </header>

            <?php if ($omochix_tools_query->have_posts()) : ?>
                <div class="tool-results__grid">
                    <?php while ($omochix_tools_query->have_posts()) : $omochix_tools_query->the_post(); ?>
                        <?php
                        $omochix_tool_id       = get_the_ID();
                        $omochix_title         = get_the_title();
                        $omochix_logo_id       = absint(get_post_meta($omochix_tool_id, 'tool_logo', true));
                        $omochix_description   = get_post_meta($omochix_tool_id, 'short_description', true);
                        $omochix_description   = $omochix_description ?: get_the_excerpt();
                        $omochix_description   = wp_trim_words(wp_strip_all_tags($omochix_description), 42, '…');
                        $omochix_company       = get_post_meta($omochix_tool_id, 'company_name', true);
                        $omochix_rating        = (float) get_post_meta($omochix_tool_id, 'rating_overall', true);
                        $omochix_pricing_value = get_post_meta($omochix_tool_id, 'pricing_type', true) ?: 'contact';
                        $omochix_jp_value      = get_post_meta($omochix_tool_id, 'japanese_support', true) ?: 'unknown';
                        $omochix_tool_terms    = get_the_terms($omochix_tool_id, 'ai_tool_category');
                        $omochix_tool_terms    = is_wp_error($omochix_tool_terms) ? [] : (array) $omochix_tool_terms;
                        $omochix_pricing_label = function_exists('omochix_core_get_pricing_type_label')
                            ? omochix_core_get_pricing_type_label($omochix_pricing_value)
                            : ($omochix_pricing_options[$omochix_pricing_value] ?? __('要問い合わせ', 'omochix'));
                        $omochix_jp_label = function_exists('omochix_core_get_japanese_support_label')
                            ? omochix_core_get_japanese_support_label($omochix_jp_value)
                            : ($omochix_japanese_options[$omochix_jp_value] ?? __('未確認', 'omochix'));
                        ?>
                        <article class="tool-list-card">
                            <a class="tool-list-card__link" href="<?php the_permalink(); ?>">
                                <div class="tool-list-card__top">
                                    <div class="tool-list-card__logo">
                                        <?php if ($omochix_logo_id) : ?>
                                            <?php echo wp_kses_post(wp_get_attachment_image($omochix_logo_id, 'thumbnail', false, ['alt' => '', 'width' => 64, 'height' => 64, 'loading' => 'lazy', 'decoding' => 'async'])); ?>
                                        <?php else : ?>
                                            <span aria-hidden="true"><?php echo esc_html(function_exists('mb_substr') ? mb_substr($omochix_title, 0, 1) : substr($omochix_title, 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($omochix_rating > 0) : ?>
                                        <p class="tool-list-card__rating" aria-label="<?php echo esc_attr(sprintf(__('OmochiX編集部評価 5点満点中%s', 'omochix'), number_format_i18n($omochix_rating, 1))); ?>">
                                            <span aria-hidden="true">★</span><strong><?php echo esc_html(number_format_i18n($omochix_rating, 1)); ?></strong><small>/ 5</small>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="tool-list-card__identity">
                                    <h3><?php echo esc_html($omochix_title); ?></h3>
                                    <?php if ($omochix_company) : ?><p><?php echo esc_html($omochix_company); ?></p><?php endif; ?>
                                </div>
                                <?php if ($omochix_description) : ?><p class="tool-list-card__description"><?php echo esc_html($omochix_description); ?></p><?php endif; ?>
                                <ul class="tool-list-card__tags" aria-label="<?php esc_attr_e('ツール情報', 'omochix'); ?>">
                                    <li><?php echo esc_html($omochix_pricing_label); ?></li>
                                    <li><?php echo esc_html($omochix_jp_label); ?></li>
                                    <?php if ($omochix_tool_terms) : ?><li><?php echo esc_html($omochix_tool_terms[0]->name); ?></li><?php endif; ?>
                                </ul>
                                <span class="tool-list-card__more"><?php esc_html_e('続きを読む', 'omochix'); ?><span aria-hidden="true">→</span></span>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php
                $omochix_add_args = array_filter([
                    'tool_search'   => $omochix_search,
                    'tool_category' => $omochix_category,
                    'pricing'       => $omochix_pricing,
                    'japanese'      => $omochix_japanese,
                    'platform'      => $omochix_platform,
                    'tool_order'    => 'latest' !== $omochix_order ? $omochix_order : '',
                ]);
                $omochix_pagination = paginate_links([
                    'total'     => $omochix_tools_query->max_num_pages,
                    'current'   => $omochix_paged,
                    'mid_size'  => 1,
                    'prev_text' => __('前へ', 'omochix'),
                    'next_text' => __('次へ', 'omochix'),
                    'add_args'  => $omochix_add_args,
                    'type'      => 'list',
                ]);
                ?>
                <?php if ($omochix_pagination) : ?>
                    <nav class="pagination" aria-label="<?php esc_attr_e('AIツール一覧のページ送り', 'omochix'); ?>"><?php echo wp_kses_post($omochix_pagination); ?></nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="tool-results__empty" role="status">
                    <span aria-hidden="true">O</span>
                    <p><?php esc_html_e('条件に合うAIツールはまだありません。', 'omochix'); ?></p>
                    <a href="<?php echo esc_url($omochix_archive_url); ?>"><?php esc_html_e('絞り込みを解除', 'omochix'); ?></a>
                </div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </section>

        <aside class="tool-sidebar" aria-label="<?php esc_attr_e('AIツールの補助情報', 'omochix'); ?>">
            <section class="sidebar-panel" aria-labelledby="popular-tools-title">
                <h2 id="popular-tools-title"><?php esc_html_e('人気ツール', 'omochix'); ?></h2>
                <?php if ($omochix_popular_query->have_posts()) : ?>
                    <ol class="tool-sidebar__tools">
                        <?php while ($omochix_popular_query->have_posts()) : $omochix_popular_query->the_post(); ?>
                            <li><a href="<?php the_permalink(); ?>"><span><?php echo esc_html(get_the_title()); ?></span><small><?php esc_html_e('詳細を見る', 'omochix'); ?></small></a></li>
                        <?php endwhile; ?>
                    </ol>
                <?php else : ?>
                    <p class="sidebar-panel__empty"><?php esc_html_e('人気ツールを準備中です。', 'omochix'); ?></p>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            </section>

            <section class="sidebar-panel" aria-labelledby="tool-categories-title">
                <h2 id="tool-categories-title"><?php esc_html_e('人気カテゴリー', 'omochix'); ?></h2>
                <?php if ($omochix_sidebar_categories) : ?>
                    <ul class="sidebar-links">
                        <?php foreach ($omochix_sidebar_categories as $omochix_term) : ?>
                            <li><a href="<?php echo esc_url(get_term_link($omochix_term)); ?>"><span><?php echo esc_html($omochix_term->name); ?></span><small><?php echo esc_html(number_format_i18n($omochix_term->count)); ?></small></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="sidebar-panel__empty"><?php esc_html_e('カテゴリーを準備中です。', 'omochix'); ?></p>
                <?php endif; ?>
            </section>

            <section class="sidebar-newsletter" aria-labelledby="tool-newsletter-title">
                <p><?php esc_html_e('OMOCHIX NEWSLETTER', 'omochix'); ?></p>
                <h2 id="tool-newsletter-title"><?php esc_html_e('AIの最新情報を見逃さない。', 'omochix'); ?></h2>
                <a href="<?php echo esc_url(home_url('/#newsletter-title')); ?>"><?php esc_html_e('Newsletterを見る', 'omochix'); ?><span aria-hidden="true">→</span></a>
            </section>
        </aside>
    </div>
</main>

<?php get_footer(); ?>
