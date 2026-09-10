<?php
/**
 * Theme setup and assets.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_theme_file_path('/inc/seo.php');
require_once get_theme_file_path('/inc/contact-form.php');

function omochix_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    register_nav_menus([
        'primary' => __('メインナビゲーション', 'omochix'),
    ]);
}
add_action('after_setup_theme', 'omochix_setup');

/**
 * Return a published page URL, or an empty string when it is not available.
 *
 * @param string $path Page path without surrounding slashes.
 * @return string
 */
function omochix_get_published_page_url($path) {
    $page = get_page_by_path($path, OBJECT, 'page');

    return $page instanceof WP_Post && 'publish' === $page->post_status
        ? get_permalink($page)
        : '';
}

function omochix_enqueue_assets() {
    $theme = wp_get_theme();

    wp_enqueue_style(
        'omochix-style',
        get_stylesheet_uri(),
        [],
        $theme->get('Version')
    );

    wp_enqueue_script(
        'omochix-header',
        get_template_directory_uri() . '/assets/js/header.js',
        [],
        $theme->get('Version'),
        true
    );

    if (is_singular('post')) {
        wp_enqueue_script(
            'omochix-article',
            get_template_directory_uri() . '/assets/js/article.js',
            [],
            $theme->get('Version'),
            true
        );
    }

    if (is_page('contact')) {
        wp_enqueue_script(
            'omochix-contact-form',
            get_template_directory_uri() . '/assets/js/contact-form.js',
            [],
            $theme->get('Version'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'omochix_enqueue_assets');

/**
 * Limit public site search to editorial content and AI tools.
 *
 * @param WP_Query $query Current query.
 * @return void
 */
function omochix_prepare_site_search($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }

    $type = isset($_GET['content_type']) ? sanitize_key(wp_unslash($_GET['content_type'])) : 'all';
    $allowed_types = [
        'all'   => ['post', 'ai_tool'],
        'news'  => ['post'],
        'tools' => ['ai_tool'],
    ];
    if (!isset($allowed_types[$type])) {
        $type = 'all';
    }

    $query->set('post_type', $allowed_types[$type]);
    $query->set('post_status', 'publish');
    $query->set('posts_per_page', 12);
}
add_action('pre_get_posts', 'omochix_prepare_site_search');

/**
 * Extend front-end search with the two MVP tool meta fields and tool terms.
 *
 * EXISTS subqueries avoid duplicate rows and apply only to the main public
 * search query. Standard title, excerpt and content matching is retained.
 *
 * @param string   $search Existing search SQL.
 * @param WP_Query $query  Current query.
 * @return string
 */
function omochix_extend_site_search_sql($search, $query) {
    global $wpdb;

    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return $search;
    }

    $keyword = sanitize_text_field((string) $query->get('s'));
    if ('' === $keyword) {
        return $search;
    }

    $terms = preg_split('/\s+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY);
    $terms = array_slice(array_unique(array_map('sanitize_text_field', (array) $terms)), 0, 6);
    if (!$terms) {
        return $search;
    }

    $groups = [];
    foreach ($terms as $term) {
        $like = '%' . $wpdb->esc_like($term) . '%';
        $groups[] = $wpdb->prepare(
            "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR ({$wpdb->posts}.post_type = 'ai_tool' AND (EXISTS (SELECT 1 FROM {$wpdb->postmeta} omx_search_pm WHERE omx_search_pm.post_id = {$wpdb->posts}.ID AND omx_search_pm.meta_key IN ('company_name', 'short_description') AND omx_search_pm.meta_value LIKE %s) OR EXISTS (SELECT 1 FROM {$wpdb->term_relationships} omx_search_tr INNER JOIN {$wpdb->term_taxonomy} omx_search_tt ON omx_search_tt.term_taxonomy_id = omx_search_tr.term_taxonomy_id INNER JOIN {$wpdb->terms} omx_search_t ON omx_search_t.term_id = omx_search_tt.term_id WHERE omx_search_tr.object_id = {$wpdb->posts}.ID AND omx_search_tt.taxonomy IN ('ai_tool_category', 'ai_tool_feature', 'ai_tool_tag', 'ai_tool_platform') AND omx_search_t.name LIKE %s))))",
            $like,
            $like,
            $like,
            $like,
            $like
        );
    }

    $search = ' AND (' . implode(' AND ', $groups) . ') ';
    if (!is_user_logged_in()) {
        $search .= $wpdb->prepare(" AND {$wpdb->posts}.post_password = %s ", '');
    }
    return $search;
}
add_filter('posts_search', 'omochix_extend_site_search_sql', 10, 2);

/**
 * Keep internal search results and not-found responses out of indexes.
 *
 * @param array<string, bool> $robots Robots directives.
 * @return array<string, bool>
 */
function omochix_search_robots($robots) {
    if (is_search() || is_404()) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }
    return $robots;
}
add_filter('wp_robots', 'omochix_search_robots');

/**
 * Return starter data used until the ai_tool post type has enough content.
 *
 * Keeping this outside the template also makes the data reusable by future
 * archives, blocks or import routines.
 *
 * @return array<int, array<string, mixed>>
 */
function omochix_get_starter_ai_tools() {
    return [
        [
            'name' => 'ChatGPT', 'company' => 'OpenAI', 'slug' => 'chatgpt',
            'description' => __('文章作成、調査、画像生成、コード作成まで幅広く対応し、日常から仕事まで使いやすい総合型AI。', 'omochix'),
            'rating' => 4.9, 'pricing' => __('無料・有料', 'omochix'), 'category' => __('AIチャット', 'omochix'), 'japanese' => true, 'free_plan' => true,
        ],
        [
            'name' => 'Claude', 'company' => 'Anthropic', 'slug' => 'claude',
            'description' => __('長文の読解や自然な文章作成、資料分析を得意とし、丁寧な対話で思考整理を支える生成AI。', 'omochix'),
            'rating' => 4.8, 'pricing' => __('無料・有料', 'omochix'), 'category' => __('AIチャット', 'omochix'), 'japanese' => true, 'free_plan' => true,
        ],
        [
            'name' => 'Midjourney', 'company' => 'Midjourney', 'slug' => 'midjourney',
            'description' => __('短い指示から高品質で独創的なビジュアルを生成でき、企画やデザイン制作に強い画像生成AI。', 'omochix'),
            'rating' => 4.7, 'pricing' => __('有料', 'omochix'), 'category' => __('画像生成', 'omochix'), 'japanese' => true, 'free_plan' => false,
        ],
        [
            'name' => 'Gemini', 'company' => 'Google', 'slug' => 'gemini',
            'description' => __('Googleサービスとの連携に強く、文章、画像、情報検索を横断して活用できるマルチモーダルAI。', 'omochix'),
            'rating' => 4.6, 'pricing' => __('無料・有料', 'omochix'), 'category' => __('AIチャット', 'omochix'), 'japanese' => true, 'free_plan' => true,
        ],
        [
            'name' => 'Perplexity', 'company' => 'Perplexity AI', 'slug' => 'perplexity',
            'description' => __('情報源を確認しながらウェブを調査でき、知りたい内容をすばやく整理することに適した検索AI。', 'omochix'),
            'rating' => 4.7, 'pricing' => __('無料・有料', 'omochix'), 'category' => __('AI検索', 'omochix'), 'japanese' => true, 'free_plan' => true,
        ],
        [
            'name' => 'Cursor', 'company' => 'Anysphere', 'slug' => 'cursor',
            'description' => __('コードベースを理解し、補完、修正、質問対応を一つのエディター内で行える開発者向けAIツール。', 'omochix'),
            'rating' => 4.8, 'pricing' => __('無料・有料', 'omochix'), 'category' => __('開発支援', 'omochix'), 'japanese' => true, 'free_plan' => true,
        ],
    ];
}

/**
 * Return the purpose-led category definitions used on the front page.
 *
 * Slugs and taxonomy names are intentionally explicit so each item can be
 * connected to a real term without changing the front-page template. An item
 * with `post_type` resolves to that post type's archive instead of a taxonomy
 * term. A card that cannot resolve to a real archive is skipped entirely by
 * the front page rather than falling back to a search URL.
 *
 * @return array<int, array<string, string>>
 */
function omochix_get_front_page_categories() {
    return [
        ['name' => __('AIニュース', 'omochix'), 'slug' => 'ai-news', 'taxonomy' => 'category', 'description' => __('AIの最新動向を知る', 'omochix'), 'icon' => 'news'],
        ['name' => __('AIツール', 'omochix'), 'post_type' => 'ai_tool', 'description' => __('目的に合うAIサービスを探す', 'omochix'), 'icon' => 'tools'],
        ['name' => __('チュートリアル', 'omochix'), 'slug' => 'tutorial', 'taxonomy' => 'category', 'description' => __('使い方を手順から学ぶ', 'omochix'), 'icon' => 'tutorial'],
        ['name' => __('プロンプト集', 'omochix'), 'slug' => 'prompts', 'taxonomy' => 'category', 'description' => __('すぐ使える指示文を見つける', 'omochix'), 'icon' => 'prompt'],
        ['name' => __('ビジネス', 'omochix'), 'slug' => 'business', 'taxonomy' => 'category', 'description' => __('仕事と業務改善に活かす', 'omochix'), 'icon' => 'business'],
        ['name' => __('開発・技術', 'omochix'), 'slug' => 'development', 'taxonomy' => 'category', 'description' => __('開発や技術情報を深める', 'omochix'), 'icon' => 'code'],
        ['name' => __('デザイン', 'omochix'), 'slug' => 'design', 'taxonomy' => 'category', 'description' => __('制作と表現の幅を広げる', 'omochix'), 'icon' => 'design'],
        ['name' => __('ライフスタイル', 'omochix'), 'slug' => 'lifestyle', 'taxonomy' => 'category', 'description' => __('暮らしを便利に整える', 'omochix'), 'icon' => 'life'],
    ];
}

/**
 * Resolve an optional page without producing a known 404 link.
 *
 * @param string $path        Page path without surrounding slashes.
 * @param string $search_term Safe search fallback.
 * @return string
 */
function omochix_get_page_or_search_url($path, $search_term) {
    $page = get_page_by_path($path, OBJECT, 'page');

    if ($page instanceof WP_Post && 'publish' === $page->post_status) {
        return get_permalink($page);
    }

    return add_query_arg('s', $search_term, home_url('/'));
}
