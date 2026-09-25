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
require_once get_theme_file_path('/inc/learn.php');

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

    if (is_singular('prompt')) {
        wp_enqueue_script(
            'omochix-prompt',
            get_template_directory_uri() . '/assets/js/prompt.js',
            [],
            $theme->get('Version'),
            true
        );
    }

    if (is_singular('ai_tool')) {
        wp_enqueue_script(
            'omochix-tool-detail',
            get_template_directory_uri() . '/assets/js/tool-detail.js',
            [],
            $theme->get('Version'),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'omochix_enqueue_assets');

/**
 * Limit public site search to editorial content, AI tools and prompts.
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
        'all'     => ['post', 'ai_tool', 'prompt'],
        'news'    => ['post'],
        'tools'   => ['ai_tool'],
        'prompts' => ['prompt'],
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
 * Match the category archive's main query page size to archive-ai_tool.php.
 *
 * The template renders its own 12-per-page WP_Query; aligning the main query
 * keeps /page/N/ from 404ing when posts_per_page differs from 12.
 *
 * @param WP_Query $query Query instance.
 * @return void
 */
function omochix_prepare_ai_tool_category_archive($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_tax('ai_tool_category')) {
        return;
    }

    $query->set('posts_per_page', 12);
}
add_action('pre_get_posts', 'omochix_prepare_ai_tool_category_archive');

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
        // Prompt rows additionally match prompt body/usage and prompt terms;
        // the clause is scoped to post_type = 'prompt', so post and ai_tool
        // matching is unchanged.
        $groups[] = '(' . $wpdb->prepare(
            "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR ({$wpdb->posts}.post_type = 'ai_tool' AND (EXISTS (SELECT 1 FROM {$wpdb->postmeta} omx_search_pm WHERE omx_search_pm.post_id = {$wpdb->posts}.ID AND omx_search_pm.meta_key IN ('company_name', 'short_description') AND omx_search_pm.meta_value LIKE %s) OR EXISTS (SELECT 1 FROM {$wpdb->term_relationships} omx_search_tr INNER JOIN {$wpdb->term_taxonomy} omx_search_tt ON omx_search_tt.term_taxonomy_id = omx_search_tr.term_taxonomy_id INNER JOIN {$wpdb->terms} omx_search_t ON omx_search_t.term_id = omx_search_tt.term_id WHERE omx_search_tr.object_id = {$wpdb->posts}.ID AND omx_search_tt.taxonomy IN ('ai_tool_category', 'ai_tool_feature', 'ai_tool_tag', 'ai_tool_platform') AND omx_search_t.name LIKE %s))))",
            $like,
            $like,
            $like,
            $like,
            $like
        ) . ' OR ' . omochix_get_prompt_search_sql($like) . ')';
    }

    $search = ' AND (' . implode(' AND ', $groups) . ') ';
    if (!is_user_logged_in()) {
        $search .= $wpdb->prepare(" AND {$wpdb->posts}.post_password = %s ", '');
    }
    return $search;
}
add_filter('posts_search', 'omochix_extend_site_search_sql', 10, 2);

/**
 * Return a prepared SQL condition matching prompt-only searchable fields.
 *
 * Only ever true for rows whose post_type is 'prompt', so OR-ing it into a
 * search group cannot widen results for any other post type.
 *
 * @param string $like Already esc_like()'d and %-wrapped LIKE pattern.
 * @return string
 */
function omochix_get_prompt_search_sql($like) {
    global $wpdb;

    return $wpdb->prepare(
        "({$wpdb->posts}.post_type = 'prompt' AND (EXISTS (SELECT 1 FROM {$wpdb->postmeta} omx_prompt_pm WHERE omx_prompt_pm.post_id = {$wpdb->posts}.ID AND omx_prompt_pm.meta_key IN ('prompt_body', 'prompt_usage') AND omx_prompt_pm.meta_value LIKE %s) OR EXISTS (SELECT 1 FROM {$wpdb->term_relationships} omx_prompt_tr INNER JOIN {$wpdb->term_taxonomy} omx_prompt_tt ON omx_prompt_tt.term_taxonomy_id = omx_prompt_tr.term_taxonomy_id INNER JOIN {$wpdb->terms} omx_prompt_t ON omx_prompt_t.term_id = omx_prompt_tt.term_id WHERE omx_prompt_tr.object_id = {$wpdb->posts}.ID AND omx_prompt_tt.taxonomy IN ('prompt_category', 'prompt_model') AND omx_prompt_t.name LIKE %s)))",
        $like,
        $like
    );
}

/**
 * Extend the Prompt Library archive search (/prompts/?prompt_search=) to
 * prompt body, usage and prompt term names.
 *
 * Applies only to queries that opt in with the `omochix_prompt_search` query
 * var (set by archive-prompt.php) and query the prompt post type alone, so no
 * other search is affected. Mirrors the site search's per-word AND matching.
 *
 * @param string   $search Existing search SQL.
 * @param WP_Query $query  Current query.
 * @return string
 */
function omochix_extend_prompt_search_sql($search, $query) {
    global $wpdb;

    if (is_admin() || !$query->get('omochix_prompt_search') || 'prompt' !== $query->get('post_type')) {
        return $search;
    }

    $keyword = sanitize_text_field((string) $query->get('s'));
    $terms   = preg_split('/\s+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY);
    $terms   = array_slice(array_unique((array) $terms), 0, 6);
    if (!$terms) {
        return $search;
    }

    $groups = [];
    foreach ($terms as $term) {
        $like = '%' . $wpdb->esc_like($term) . '%';
        $groups[] = '(' . $wpdb->prepare(
            "{$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s",
            $like,
            $like,
            $like
        ) . ' OR ' . omochix_get_prompt_search_sql($like) . ')';
    }

    $search = ' AND (' . implode(' AND ', $groups) . ') ';
    if (!is_user_logged_in()) {
        $search .= $wpdb->prepare(" AND {$wpdb->posts}.post_password = %s ", '');
    }
    return $search;
}
add_filter('posts_search', 'omochix_extend_prompt_search_sql', 10, 2);

/**
 * Align the Prompt Library main query page size with archive-prompt.php.
 *
 * The template renders its own 12-per-page WP_Query; matching the main query
 * keeps /prompts/page/N/ and term /page/N/ URLs from 404ing when the Reading
 * setting differs from 12. Scoped to the prompt archive and prompt term
 * archives only.
 *
 * @param WP_Query $query Query instance.
 * @return void
 */
function omochix_prepare_prompt_archive($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    if (!$query->is_post_type_archive('prompt') && !$query->is_tax(['prompt_category', 'prompt_model'])) {
        return;
    }

    $query->set('posts_per_page', 12);
}
add_action('pre_get_posts', 'omochix_prepare_prompt_archive');

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
        ['name' => __('AI開発', 'omochix'), 'slug' => 'ai-development', 'taxonomy' => 'category', 'description' => __('AI開発を実践的に学ぶ', 'omochix'), 'icon' => 'code'],
        ['name' => __('チュートリアル', 'omochix'), 'slug' => 'tutorial', 'taxonomy' => 'category', 'description' => __('使い方を手順から学ぶ', 'omochix'), 'icon' => 'tutorial'],
        // Points at the "prompt" CPT archive (/prompts), the new Prompt
        // Library. The pre-existing "prompts" `category` taxonomy term is
        // intentionally left untouched (see AGENTS.md handoff notes) so
        // older posts filed under it keep their URLs; it is simply no longer
        // this card's destination.
        ['name' => __('プロンプト集', 'omochix'), 'post_type' => 'prompt', 'description' => __('すぐ使えるプロンプトを見つける', 'omochix'), 'icon' => 'prompt'],
        ['name' => __('ビジネス', 'omochix'), 'slug' => 'business', 'taxonomy' => 'category', 'description' => __('仕事と業務改善に活かす', 'omochix'), 'icon' => 'business'],
        ['name' => __('開発・技術', 'omochix'), 'slug' => 'development', 'taxonomy' => 'category', 'description' => __('開発や技術情報を深める', 'omochix'), 'icon' => 'code'],
        ['name' => __('デザイン', 'omochix'), 'slug' => 'design', 'taxonomy' => 'category', 'description' => __('制作と表現の幅を広げる', 'omochix'), 'icon' => 'design'],
        ['name' => __('ライフスタイル', 'omochix'), 'slug' => 'lifestyle', 'taxonomy' => 'category', 'description' => __('暮らしを便利に整える', 'omochix'), 'icon' => 'life'],
    ];
}

/**
 * Return OmochiX's real, live official social profile URLs.
 *
 * Single source of truth for the footer social links and the Organization
 * schema's `sameAs` property, so the two never drift apart.
 *
 * @return array<int, array<string, string>>
 */
function omochix_get_social_links() {
    return [
        ['name' => 'X', 'label' => 'OmochiX on X', 'url' => 'https://x.com/omochix528', 'icon' => 'x'],
        ['name' => 'Instagram', 'label' => 'OmochiX on Instagram', 'url' => 'https://www.instagram.com/omochix528/', 'icon' => 'instagram'],
        ['name' => 'TikTok', 'label' => 'OmochiX on TikTok', 'url' => 'https://www.tiktok.com/@omochix528', 'icon' => 'tiktok'],
        ['name' => 'YouTube', 'label' => 'OmochiX on YouTube', 'url' => 'https://www.youtube.com/channel/UCiU0SZYcHnVnIf-LNMYvTgg', 'icon' => 'youtube'],
    ];
}

/**
 * Resolve a category's canonical front-end URL.
 *
 * The "AIニュース" category (slug `ai-news`) shares its content with the
 * WordPress posts page (`page_for_posts`): /category/ai-news/ permanently
 * redirects to that posts page via a Slim SEO redirect rule. Every internal
 * link should point at the posts page directly instead of bouncing through
 * that redirect, so this is the single place that decision is made.
 *
 * @param WP_Term $category Category term.
 * @return string
 */
function omochix_get_category_url($category) {
    if ($category instanceof WP_Term && 'ai-news' === $category->slug) {
        $posts_page_id = (int) get_option('page_for_posts');
        if ($posts_page_id) {
            $permalink = get_permalink($posts_page_id);
            if ($permalink) {
                return $permalink;
            }
        }
    }

    return get_category_link($category);
}

/**
 * Return hub-page configuration for the category archives that should show
 * curated hero copy, "read first" picks, and topic navigation instead of a
 * plain post list.
 *
 * Categories with no entry here (i.e. every category except the ones opted
 * in below) render category.php exactly as before this function existed —
 * name, description, article grid — so this is additive and cannot regress
 * any other category archive.
 *
 * @param string $slug Category slug.
 * @return array{tagline: string, read_first: array<int, array<string, string>>, topics: array<int, array<string, string>>}|null
 */
function omochix_get_category_hub_config($slug) {
    $hubs = [
        'ai-development' => [
            'tagline' => __('Claude Code・MCP・AIエージェント・自動開発を実践的に学ぶ', 'omochix'),
            'read_first' => [
                ['url' => 'https://omochix.com/claude-code-ai-development-guide/', 'title' => __('Claude Code完全ガイド', 'omochix'), 'description' => __('Claude Codeの基本から料金・使い方・実践的なAI開発までまとめて解説。', 'omochix')],
                ['url' => 'https://omochix.com/claude-code-mcp-guide-2026/', 'title' => __('Claude Code × MCP完全ガイド', 'omochix'), 'description' => __('MCPの仕組みと接続方法、外部ツールをClaude Codeから操作する方法を解説。', 'omochix')],
                ['url' => 'https://omochix.com/claude-code-ai-development-stack-2026/', 'title' => __('Claude CodeでAI自動開発環境を作る', 'omochix'), 'description' => __('仕様・デザイン・実装・QA・本番監視までをつないだAI開発環境の全体像を解説。', 'omochix')],
            ],
            'topics' => [
                ['name' => __('Claude Code', 'omochix'), 'description' => __('Claude Codeの基礎・使い方・実践', 'omochix'), 'taxonomy' => 'post_tag', 'slug' => 'claude-code'],
                ['name' => __('MCP', 'omochix'), 'description' => __('外部ツール連携・おすすめMCP', 'omochix'), 'taxonomy' => 'post_tag', 'slug' => 'mcp'],
                ['name' => __('AI自動開発', 'omochix'), 'description' => __('AIエージェントによる開発自動化', 'omochix'), 'taxonomy' => 'post_tag', 'slug' => '自動開発'],
                ['name' => __('QA / E2E', 'omochix'), 'description' => __('Playwright・自動テスト・品質管理', 'omochix'), 'taxonomy' => 'post_tag', 'slug' => 'e2eテスト'],
                ['name' => __('AIコーディング比較', 'omochix'), 'description' => __('Claude Code・Codexなどの比較', 'omochix'), 'taxonomy' => 'post_tag', 'slug' => 'ai比較'],
            ],
        ],
    ];

    return $hubs[$slug] ?? null;
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
