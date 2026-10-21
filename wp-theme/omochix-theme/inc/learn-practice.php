<?php
/**
 * Learn v2 / Phase 4: practice items ("/learn/practice/…").
 *
 * The data model (post type learn_practice, five taxonomies, structured
 * meta) lives in the OmochiX Core plugin; this file is the presentation
 * side: filters for the list, SEO rules, the AI implementation brief, the
 * field-hub glue for the existing Learn pages and small helpers the
 * templates share. Everything degrades to "nothing rendered" when the
 * plugin is not active.
 *
 * URL state is plain GET like the Motion Gallery: ?field= &format= &level=
 * &ai= &tech= &q=. Filtered views are noindex,follow with a canonical to the
 * bare list.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

const OMOCHIX_LEARN_PRACTICE_TYPE      = 'learn_practice';
const OMOCHIX_LEARN_PRACTICE_PER_PAGE  = 12;
const OMOCHIX_LEARN_PRACTICE_Q_MAX     = 60;
const OMOCHIX_LEARN_PRACTICE_TAXONOMIES = ['field' => 'learn_field', 'format' => 'learn_format', 'ai' => 'learn_ai', 'tech' => 'learn_tech', 'usecase' => 'learn_usecase'];

/** Whether the Core plugin registered the practice post type. */
function omochix_learn_practice_enabled() {
    return post_type_exists(OMOCHIX_LEARN_PRACTICE_TYPE);
}

/** Number of published practice items (0 without the plugin). */
function omochix_learn_practice_count() {
    return function_exists('omochix_core_count_published_practice') ? omochix_core_count_published_practice() : 0;
}

/** Archive URL ('' when the post type is absent). */
function omochix_learn_practice_url(array $filters = []) {
    if (!omochix_learn_practice_enabled()) {
        return '';
    }
    $url     = get_post_type_archive_link(OMOCHIX_LEARN_PRACTICE_TYPE);
    $filters = array_filter(omochix_learn_practice_sanitize_filters($filters), static fn($v) => '' !== $v);
    return $filters ? add_query_arg(array_map('rawurlencode', $filters), $url) : $url;
}

/* -------------------------------------------------------------------------
 * Vocabularies and labels
 * ---------------------------------------------------------------------- */

function omochix_learn_practice_levels() {
    return ['beginner' => __('初級', 'omochix'), 'intermediate' => __('中級', 'omochix'), 'advanced' => __('上級', 'omochix')];
}

function omochix_learn_practice_level_label($level) {
    return omochix_learn_practice_levels()[$level] ?? omochix_learn_practice_levels()['beginner'];
}

/** Format slug => label, in display order (falls back to the plugin's seed list). */
function omochix_learn_practice_formats() {
    $labels = function_exists('omochix_core_get_practice_format_labels') ? omochix_core_get_practice_format_labels() : ['project' => '作る', 'lesson' => '学ぶ', 'demo' => '試す', 'reference' => '調べる', 'prompt' => '使う'];
    return $labels;
}

function omochix_learn_practice_format_label($slug) {
    return omochix_learn_practice_formats()[$slug] ?? $slug;
}

/**
 * Terms of a practice taxonomy as [slug => WP_Term], published-count aware.
 *
 * @param string $taxonomy Taxonomy name.
 * @param bool   $with_empty Include terms without posts.
 * @return array<string, WP_Term>
 */
function omochix_learn_practice_terms($taxonomy, $with_empty = false) {
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => !$with_empty, 'orderby' => 'name']);
    if (is_wp_error($terms)) {
        return [];
    }
    $out = [];
    foreach ($terms as $term) {
        $out[$term->slug] = $term;
    }
    return $out;
}

/** Fields in the fixed seed order first, then any extra terms alphabetically. */
function omochix_learn_practice_fields($with_empty = true) {
    $order = ['web', 'apps', 'motion', 'video', 'automation', 'ai', 'projects', 'devtools'];
    $terms = omochix_learn_practice_terms('learn_field', $with_empty);
    $sorted = [];
    foreach ($order as $slug) {
        if (isset($terms[$slug])) {
            $sorted[$slug] = $terms[$slug];
            unset($terms[$slug]);
        }
    }
    return $sorted + $terms;
}

/**
 * The published Learn page acting as a field's hub ("learn/{field slug}").
 * "motion" is special: its hub is the Motion Gallery page.
 *
 * @param string $field_slug Field term slug.
 * @return string URL or ''.
 */
function omochix_learn_practice_field_hub_url($field_slug) {
    $field_slug = sanitize_title($field_slug);
    if ('motion' === $field_slug && function_exists('omochix_learn_gallery_url')) {
        return omochix_learn_gallery_url();
    }
    return $field_slug ? omochix_get_published_page_url('learn/' . $field_slug) : '';
}

/* -------------------------------------------------------------------------
 * Filters (list)
 * ---------------------------------------------------------------------- */

/**
 * Sanitize list filters against the taxonomy vocabularies. Unknown values
 * become '' so a mistyped URL shows the full list instead of an empty one.
 *
 * @param array<string, mixed> $source Usually $_GET.
 * @return array{field: string, format: string, level: string, ai: string, tech: string, q: string}
 */
function omochix_learn_practice_sanitize_filters(array $source) {
    $filters = ['field' => '', 'format' => '', 'level' => '', 'ai' => '', 'tech' => '', 'q' => ''];
    foreach (['field', 'format', 'ai', 'tech'] as $key) {
        $value = isset($source[$key]) ? sanitize_title((string) wp_unslash($source[$key])) : '';
        if ('' !== $value && taxonomy_exists(OMOCHIX_LEARN_PRACTICE_TAXONOMIES[$key]) && term_exists($value, OMOCHIX_LEARN_PRACTICE_TAXONOMIES[$key])) {
            $filters[$key] = $value;
        }
    }
    $level = isset($source['level']) ? sanitize_key((string) wp_unslash($source['level'])) : '';
    if (isset(omochix_learn_practice_levels()[$level])) {
        $filters['level'] = $level;
    }
    $q = isset($source['q']) ? sanitize_text_field((string) wp_unslash($source['q'])) : '';
    $q = preg_replace('/\s+/u', ' ', trim($q));
    $filters['q'] = mb_substr((string) $q, 0, OMOCHIX_LEARN_PRACTICE_Q_MAX);
    return $filters;
}

/** Current request's filters (archive and practice term archives). */
function omochix_learn_practice_current_filters() {
    $filters = omochix_learn_practice_sanitize_filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public filters.
    if (is_tax(array_values(OMOCHIX_LEARN_PRACTICE_TAXONOMIES))) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $key = array_search($term->taxonomy, OMOCHIX_LEARN_PRACTICE_TAXONOMIES, true);
            if ($key && isset($filters[$key])) {
                $filters[$key] = $term->slug;
            }
        }
    }
    return $filters;
}

/** Whether any filter is set. */
function omochix_learn_practice_is_filtered(array $filters) {
    return '' !== implode('', $filters);
}

/**
 * Apply the filters to the main archive query (SSR). Pagination and the
 * per-page size stay in the main query so /page/N/ never 404s.
 *
 * @param WP_Query $query Main query.
 * @return void
 */
function omochix_learn_practice_pre_get_posts($query) {
    if (is_admin() || !$query->is_main_query() || !omochix_learn_practice_enabled()) {
        return;
    }
    if (!$query->is_post_type_archive(OMOCHIX_LEARN_PRACTICE_TYPE) && !$query->is_tax(array_values(OMOCHIX_LEARN_PRACTICE_TAXONOMIES))) {
        return;
    }
    $filters = omochix_learn_practice_current_filters();
    $query->set('post_type', OMOCHIX_LEARN_PRACTICE_TYPE);
    $query->set('posts_per_page', OMOCHIX_LEARN_PRACTICE_PER_PAGE);
    $query->set('orderby', ['menu_order' => 'ASC', 'date' => 'DESC']);

    $tax_query = (array) $query->get('tax_query');
    foreach (['field', 'format', 'ai', 'tech'] as $key) {
        if ('' !== $filters[$key]) {
            $tax_query[] = ['taxonomy' => OMOCHIX_LEARN_PRACTICE_TAXONOMIES[$key], 'field' => 'slug', 'terms' => $filters[$key]];
        }
    }
    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }
    if ($tax_query) {
        $query->set('tax_query', $tax_query);
    }
    if ('' !== $filters['level']) {
        $query->set('meta_query', [['key' => 'practice_difficulty', 'value' => $filters['level']]]);
    }
    if ('' !== $filters['q']) {
        $query->set('s', $filters['q']);
    }
}
add_action('pre_get_posts', 'omochix_learn_practice_pre_get_posts');

/** Practice term archives render through the list template. */
function omochix_learn_practice_template_include($template) {
    if (is_tax(array_values(OMOCHIX_LEARN_PRACTICE_TAXONOMIES))) {
        $list = get_theme_file_path('/archive-learn_practice.php');
        if (file_exists($list)) {
            return $list;
        }
    }
    return $template;
}
add_filter('template_include', 'omochix_learn_practice_template_include', 9);

/* -------------------------------------------------------------------------
 * Request helpers
 * ---------------------------------------------------------------------- */

function omochix_learn_practice_is_list() {
    return omochix_learn_practice_enabled() && (is_post_type_archive(OMOCHIX_LEARN_PRACTICE_TYPE) || is_tax(array_values(OMOCHIX_LEARN_PRACTICE_TAXONOMIES)));
}

function omochix_learn_practice_is_single() {
    return omochix_learn_practice_enabled() && is_singular(OMOCHIX_LEARN_PRACTICE_TYPE);
}

/** Pages whose markup includes practice blocks (hub, field hubs, articles, prompts, tools, gallery). */
function omochix_learn_practice_needs_assets() {
    if (!omochix_learn_practice_enabled()) {
        return false;
    }
    if (omochix_learn_practice_is_list() || omochix_learn_practice_is_single()) {
        return true;
    }
    if (is_page()) {
        $page = get_queried_object();
        return $page instanceof WP_Post && (omochix_page_is_learn_root($page) || omochix_page_is_learn_descendant($page));
    }
    return is_singular(['prompt', 'ai_tool']);
}

function omochix_learn_practice_enqueue_assets() {
    if (!omochix_learn_practice_needs_assets()) {
        return;
    }
    $theme = wp_get_theme();
    wp_enqueue_style('omochix-learn-practice', get_theme_file_uri('/assets/css/learn-practice.css'), ['omochix-style'], (string) filemtime(get_theme_file_path('/assets/css/learn-practice.css')));
    if (omochix_learn_practice_is_single() || omochix_learn_practice_is_list()) {
        wp_enqueue_script('omochix-learn-practice', get_theme_file_uri('/assets/js/learn-practice.js'), [], $theme->get('Version'), true);
        wp_add_inline_script('omochix-learn-practice', 'window.omochixLearnPractice=' . wp_json_encode([
            'strings' => ['copied' => __('コピーしました', 'omochix'), 'copyFailed' => __('コピーできませんでした', 'omochix'), 'outline' => __('目次', 'omochix')],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';', 'before');
    }
}
add_action('wp_enqueue_scripts', 'omochix_learn_practice_enqueue_assets', 22);

/* -------------------------------------------------------------------------
 * Item data accessors (safe for templates)
 * ---------------------------------------------------------------------- */

/**
 * Everything a template needs from a practice post, normalized.
 *
 * @param int|WP_Post $post Post.
 * @return array<string, mixed>
 */
function omochix_learn_practice_item($post) {
    $post = get_post($post);
    $id   = $post ? (int) $post->ID : 0;
    $meta = static function ($key, $default) use ($id) {
        $value = $id ? get_post_meta($id, $key, true) : '';
        return ('' === $value || null === $value || false === $value) ? $default : $value;
    };
    $rows = static function ($key) use ($meta) {
        $value = $meta($key, []);
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    };
    $lines = static function ($key) use ($meta) {
        $value = $meta($key, []);
        return is_array($value) ? array_values(array_filter(array_map('strval', $value), 'strlen')) : [];
    };
    $terms = static function ($taxonomy) use ($id) {
        if (!$id || !taxonomy_exists($taxonomy)) {
            return [];
        }
        $terms = get_the_terms($id, $taxonomy);
        return is_array($terms) ? $terms : [];
    };
    $verified = (string) $meta('practice_verified_at', '');
    return [
        'id'           => $id,
        'post'         => $post,
        'title'        => $post ? get_the_title($post) : '',
        'url'          => $post ? get_permalink($post) : '',
        'summary'      => (string) $meta('practice_summary', ''),
        'outcome'      => (string) $meta('practice_outcome', ''),
        'level'        => (string) $meta('practice_difficulty', 'beginner'),
        'minutes'      => (int) $meta('practice_minutes', 0),
        'preview'      => ['type' => (string) $meta('practice_preview_type', 'image'), 'src' => (string) $meta('practice_preview_src', ''), 'poster' => (int) $meta('practice_preview_poster', 0)],
        'prompts'      => $rows('practice_prompts'),
        'code'         => $rows('practice_code'),
        'prereq'       => $lines('practice_prereq'),
        'steps'        => $rows('practice_steps'),
        'verify'       => $rows('practice_verify'),
        'safety'       => $lines('practice_safety'),
        'troubleshooting' => $rows('practice_troubleshooting'),
        'publish'      => $lines('practice_publish'),
        'env'          => $lines('practice_env'),
        'sources'      => $rows('practice_sources'),
        'changelog'    => $rows('practice_changelog'),
        'motion'       => $lines('related_motion_slugs'),
        'verified_at'  => $verified,
        'stale'        => function_exists('omochix_core_practice_is_stale') ? omochix_core_practice_is_stale($verified) : '' === $verified,
        'fields'       => $terms('learn_field'),
        'formats'      => $terms('learn_format'),
        'usecases'     => $terms('learn_usecase'),
        'ai'           => $terms('learn_ai'),
        'tech'         => $terms('learn_tech'),
    ];
}

/** The item's primary format slug ('' when none). */
function omochix_learn_practice_primary_format(array $item) {
    foreach (array_keys(omochix_learn_practice_formats()) as $slug) {
        foreach ($item['formats'] as $term) {
            if ($term->slug === $slug) {
                return $slug;
            }
        }
    }
    return $item['formats'] ? $item['formats'][0]->slug : '';
}

/** Published AI tool for a learn_ai term (same slug), or null. */
function omochix_learn_practice_tool_for_ai_term($term) {
    static $cache = [];
    if (!post_type_exists('ai_tool')) {
        return null;
    }
    if (!array_key_exists($term->slug, $cache)) {
        $posts = get_posts(['post_type' => 'ai_tool', 'post_status' => 'publish', 'name' => $term->slug, 'posts_per_page' => 1, 'no_found_rows' => true]);
        $cache[$term->slug] = $posts ? $posts[0] : null;
    }
    return $cache[$term->slug];
}

/**
 * Motion samples referenced by the item that exist in the catalog, plus the
 * Composer URL for the first three (when both pages are published).
 *
 * @param array<string, mixed> $item From omochix_learn_practice_item().
 * @return array{items: array<int, array<string, mixed>>, composer: string}
 */
function omochix_learn_practice_motion(array $item) {
    $samples = [];
    if (function_exists('omochix_learn_gallery_get_item') && function_exists('omochix_learn_gallery_url') && omochix_learn_gallery_url()) {
        foreach ($item['motion'] as $slug) {
            $sample = omochix_learn_gallery_get_item($slug);
            if ($sample) {
                $samples[] = $sample;
            }
        }
    }
    $composer = '';
    if ($samples && function_exists('omochix_learn_composer_url') && omochix_learn_composer_url()) {
        $slugs    = array_slice(array_column($samples, 'slug'), 0, 3);
        $composer = omochix_learn_composer_url() . '#c=1;' . implode(';', $slugs);
    }
    return ['items' => $samples, 'composer' => $composer];
}

/** Related posts of each kind (published only, via the plugin readers). */
function omochix_learn_practice_related(array $item) {
    if (!$item['id'] || !function_exists('omochix_core_get_practice_related')) {
        return ['practice' => [], 'prompts' => [], 'tools' => [], 'learn' => []];
    }
    return [
        'practice' => omochix_core_get_practice_related($item['id'], 'related_practice_ids', OMOCHIX_LEARN_PRACTICE_TYPE),
        'prompts'  => post_type_exists('prompt') ? omochix_core_get_practice_related($item['id'], 'related_prompt_ids', 'prompt') : [],
        'tools'    => post_type_exists('ai_tool') ? omochix_core_get_practice_related($item['id'], 'related_tool_ids', 'ai_tool') : [],
        'learn'    => omochix_core_get_practice_related($item['id'], 'related_learn_ids', 'page'),
    ];
}

/** Practice items for a Learn page: those listing it, else those in the field matching the page slug. */
function omochix_learn_practice_for_page($page, $limit = 6) {
    if (!omochix_learn_practice_enabled() || !($page instanceof WP_Post)) {
        return [];
    }
    $posts = function_exists('omochix_core_get_practice_for_page') ? omochix_core_get_practice_for_page($page->ID, $limit) : [];
    if (!$posts && function_exists('omochix_core_get_practice_by_term') && (int) $page->post_parent === omochix_get_learn_page_id() && term_exists($page->post_name, 'learn_field')) {
        $posts = omochix_core_get_practice_by_term('learn_field', $page->post_name, ['posts_per_page' => $limit]);
    }
    return $posts;
}

/** Items marked sticky-ish for the hub: newest published, capped. */
function omochix_learn_practice_featured($limit = 6) {
    if (!omochix_learn_practice_enabled()) {
        return [];
    }
    return get_posts(['post_type' => OMOCHIX_LEARN_PRACTICE_TYPE, 'post_status' => 'publish', 'posts_per_page' => $limit, 'orderby' => ['menu_order' => 'ASC', 'date' => 'DESC'], 'no_found_rows' => true]);
}

/* -------------------------------------------------------------------------
 * AI implementation brief (Markdown, PHP only)
 * ---------------------------------------------------------------------- */

/** Assistant opening lines (shared with the Composer when it is loaded). */
function omochix_learn_practice_assistants() {
    if (function_exists('omochix_learn_composer_assistants')) {
        return omochix_learn_composer_assistants();
    }
    return [
        'claude-code' => ['label' => 'Claude Code', 'line' => __('このファイルの指示に従って実装してください。分からない点は実装前に質問してください。', 'omochix')],
        'codex'       => ['label' => 'Codex', 'line' => __('以下の指示に従って実装してください。前提が足りない場合は推測せず、質問してください。', 'omochix')],
    ];
}

/** A Markdown list from lines ('' when empty). */
function omochix_learn_practice_md_list(array $lines, $indent = '') {
    return implode("\n", array_map(static fn($line) => $indent . '- ' . preg_replace('/\s+/u', ' ', trim((string) $line)), $lines));
}

/** Fenced block; a fence longer than any run of backticks inside the text. */
function omochix_learn_practice_md_fence($text, $lang = '') {
    $text  = rtrim(str_replace(["\r\n", "\r"], "\n", (string) $text), "\n");
    $fence = '```';
    while (false !== strpos($text, $fence)) {
        $fence .= '`';
    }
    return $fence . $lang . "\n" . $text . "\n" . $fence;
}

/**
 * The whole brief for one practice item.
 *
 * @param int|WP_Post $post      Practice post.
 * @param string      $assistant 'claude-code' | 'codex'.
 * @return string Markdown ('' for a missing post).
 */
function omochix_learn_practice_brief($post, $assistant = 'claude-code') {
    $item = omochix_learn_practice_item($post);
    if (!$item['id']) {
        return '';
    }
    $assistants = omochix_learn_practice_assistants();
    $assistant  = isset($assistants[$assistant]) ? $assistant : 'claude-code';
    $out        = [];
    $out[]      = '# ' . sprintf(__('実装指示: %s', 'omochix'), $item['title']);
    $out[]      = $assistants[$assistant]['line'];
    $out[]      = '';
    $out[]      = '## ' . __('概要', 'omochix');
    $out[]      = '- ' . __('教材', 'omochix') . ': ' . $item['url'];
    $out[]      = '- ' . __('難易度', 'omochix') . ': ' . omochix_learn_practice_level_label($item['level']) . ($item['minutes'] ? ' / ' . sprintf(__('所要 約%d分', 'omochix'), $item['minutes']) : '');
    if ($item['ai']) {
        $out[] = '- ' . __('対応AI', 'omochix') . ': ' . implode(' / ', array_map(static fn($t) => $t->name, $item['ai']));
    }
    if ($item['tech']) {
        $out[] = '- ' . __('使用技術', 'omochix') . ': ' . implode(' / ', array_map(static fn($t) => $t->name, $item['tech']));
    }
    if ($item['verified_at']) {
        $out[] = '- ' . __('最終検証日', 'omochix') . ': ' . $item['verified_at'];
    }
    $out[] = '';
    $out[] = '## ' . __('作るもの', 'omochix');
    $out[] = $item['outcome'] ?: $item['summary'];
    if ($item['prereq']) {
        $out[] = '';
        $out[] = '## ' . __('前提条件', 'omochix');
        $out[] = omochix_learn_practice_md_list($item['prereq']);
    }
    if ($item['env']) {
        $out[] = '';
        $out[] = '## ' . __('動作確認環境', 'omochix');
        $out[] = omochix_learn_practice_md_list($item['env']);
    }
    if ($item['steps']) {
        $out[] = '';
        $out[] = '## ' . __('実装手順', 'omochix');
        foreach ($item['steps'] as $i => $step) {
            $out[] = '';
            $out[] = '### ' . sprintf(__('STEP %d: %s', 'omochix'), $i + 1, $step['title']);
            if ('' !== $step['body']) {
                $out[] = trim(wp_strip_all_tags(preg_replace('#</(p|li|h[1-6])>#i', "\n", (string) $step['body'])));
            }
            $prompt = isset($step['prompt']) && is_int($step['prompt']) && isset($item['prompts'][$step['prompt']]) ? $item['prompts'][$step['prompt']] : null;
            if ($prompt) {
                $out[] = '';
                $out[] = __('AI に渡すプロンプト', 'omochix') . ($prompt['title'] ? '（' . $prompt['title'] . '）' : '') . ':';
                $out[] = omochix_learn_practice_md_fence($prompt['body'], 'text');
            }
            $code = isset($step['code']) && is_int($step['code']) && isset($item['code'][$step['code']]) ? $item['code'][$step['code']] : null;
            if ($code) {
                $out[] = '';
                $out[] = __('コード例', 'omochix') . ($code['file'] ? '（' . $code['file'] . '）' : '') . ':';
                $out[] = omochix_learn_practice_md_fence($code['body'], (string) $code['lang']);
            }
            if ('' !== $step['check']) {
                $out[] = '';
                $out[] = '- ' . __('確認', 'omochix') . ': ' . $step['check'];
            }
        }
    }
    if ($item['verify']) {
        $out[] = '';
        $out[] = '## ' . __('検証', 'omochix');
        foreach ($item['verify'] as $row) {
            $out[] = '- ' . $row['title'] . ('' !== $row['how'] ? ' — ' . __('やり方', 'omochix') . ': ' . preg_replace('/\s+/u', ' ', $row['how']) : '') . ('' !== $row['expect'] ? ' / ' . __('期待結果', 'omochix') . ': ' . preg_replace('/\s+/u', ' ', $row['expect']) : '');
        }
    }
    if ($item['safety']) {
        $out[] = '';
        $out[] = '## ' . __('安全性・秘密情報', 'omochix');
        $out[] = omochix_learn_practice_md_list($item['safety']);
        $out[] = '- ' . __('「＜ここに貼る：…＞」の形のプレースホルダーは、環境変数など公開されない場所に実際の値を置き、コードやリポジトリに書かない', 'omochix');
    }
    if ($item['publish']) {
        $out[] = '';
        $out[] = '## ' . __('公開手順', 'omochix');
        $out[] = omochix_learn_practice_md_list($item['publish']);
    }
    if ($item['troubleshooting']) {
        $out[] = '';
        $out[] = '## ' . __('よくあるエラー', 'omochix');
        foreach ($item['troubleshooting'] as $row) {
            $out[] = '- ' . $row['symptom'] . ('' !== $row['cause'] ? ' — ' . __('原因', 'omochix') . ': ' . preg_replace('/\s+/u', ' ', $row['cause']) : '') . ('' !== $row['fix'] ? ' / ' . __('対処', 'omochix') . ': ' . preg_replace('/\s+/u', ' ', $row['fix']) : '');
        }
    }
    if ($item['sources']) {
        $out[] = '';
        $out[] = '## ' . __('参照した公式仕様', 'omochix');
        foreach ($item['sources'] as $row) {
            $out[] = '- ' . $row['label'] . ('' !== $row['url'] ? ' ' . $row['url'] : '');
        }
    }
    $out[] = '';
    $out[] = '## ' . __('受け入れ条件', 'omochix');
    $out[] = '- ' . __('上の検証項目がすべて期待結果どおりになる', 'omochix');
    $out[] = '- ' . __('秘密情報がコード・設定ファイル・公開ページに含まれていない', 'omochix');
    $out[] = '- ' . __('スマートフォン幅でも表示が崩れず、キーボードだけで操作できる', 'omochix');
    return implode("\n", $out) . "\n";
}

/* -------------------------------------------------------------------------
 * SEO (Slim SEO hooks, same policy as the Motion Gallery)
 * ---------------------------------------------------------------------- */

/** Filtered lists and every practice term archive except usecases with 2+ posts are noindex. */
function omochix_learn_practice_is_noindex_request() {
    if (!omochix_learn_practice_enabled()) {
        return false;
    }
    if (is_tax(['learn_field', 'learn_format', 'learn_ai', 'learn_tech'])) {
        return true;
    }
    if (is_tax('learn_usecase')) {
        $term = get_queried_object();
        if ($term instanceof WP_Term && (int) $term->count < 2) {
            return true;
        }
    }
    if (omochix_learn_practice_is_list()) {
        return omochix_learn_practice_is_filtered(omochix_learn_practice_sanitize_filters($_GET)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    return false;
}

add_filter('wp_robots', static function ($robots) {
    if (omochix_learn_practice_is_noindex_request()) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }
    return $robots;
});
add_filter('slim_seo_robots_index', static function ($indexed) {
    return omochix_learn_practice_is_noindex_request() ? false : $indexed;
});
add_filter('slim_seo_canonical_url', static function ($url) {
    if (omochix_learn_practice_is_list() && omochix_learn_practice_is_noindex_request()) {
        return omochix_learn_practice_url();
    }
    return $url;
});
add_filter('slim_seo_meta_description', static function ($description) {
    if (omochix_learn_practice_is_single()) {
        $summary = (string) get_post_meta(get_queried_object_id(), 'practice_summary', true);
        return '' !== $summary && !has_excerpt() ? $summary : $description;
    }
    if (omochix_learn_practice_is_list() && !omochix_learn_practice_is_noindex_request()) {
        return __('作る・学ぶ・試す・調べる・使う。見本とプロンプトとコードを一つにした実践教材を、分野と用途から探せます。', 'omochix');
    }
    return $description;
});
add_filter('slim_seo_breadcrumbs_links', static function ($links) {
    if (!omochix_learn_practice_is_single() || !is_array($links)) {
        return $links;
    }
    $item = omochix_learn_practice_item(get_queried_object());
    $hub  = $item['fields'] ? omochix_learn_practice_field_hub_url($item['fields'][0]->slug) : '';
    if ($hub) {
        $last = array_pop($links);
        $links[] = ['url' => $hub, 'text' => $item['fields'][0]->name];
        $links[] = $last;
    }
    return $links;
});
add_filter('slim_seo_sitemap_taxonomies', static function ($taxonomies) {
    return is_array($taxonomies) ? array_values(array_diff($taxonomies, ['learn_field', 'learn_format', 'learn_ai', 'learn_tech'])) : $taxonomies;
});
