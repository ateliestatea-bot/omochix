<?php
/** Editorial homepage presentation; no data migrations or public URL changes. */
if (!defined('ABSPATH')) { exit; }

function omochix_editorial_icon($name) {
    $allowed = ['news', 'tools', 'code', 'learn', 'prompt', 'video', 'community', 'about', 'arrow'];
    if (!in_array($name, $allowed, true)) { return ''; }
    // Only fixed, theme-owned SVG files may be inlined. No post/user input.
    return file_get_contents(get_theme_file_path('/assets/icons/' . $name . '.svg'));
}

function omochix_editorial_destinations() {
    $posts_id = (int) get_option('page_for_posts');
    $posts = $posts_id ? get_post($posts_id) : null;
    $dev = get_category_by_slug('ai-development');
    return [
        ['label' => __('AIニュース', 'omochix'), 'icon' => 'news', 'url' => $posts instanceof WP_Post && $posts->post_status === 'publish' ? get_permalink($posts) : ''],
        ['label' => __('AIツール', 'omochix'), 'icon' => 'tools', 'url' => get_post_type_archive_link('ai_tool') ?: ''],
        ['label' => __('AI開発', 'omochix'), 'icon' => 'code', 'url' => $dev instanceof WP_Term ? omochix_get_category_url($dev) : ''],
        ['label' => 'Learn', 'icon' => 'learn', 'url' => omochix_get_published_page_url('learn')],
        ['label' => 'Prompts', 'icon' => 'prompt', 'url' => get_post_type_archive_link('prompt') ?: ''],
    ];
}

/** Public, non-password-protected entries only, also when a user is logged in. */
function omochix_editorial_posts($args = []) {
    return (new WP_Query(array_merge([
        'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
        'posts_per_page' => 3, 'no_found_rows' => true, 'ignore_sticky_posts' => true,
        'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
    ], $args)))->posts;
}

function omochix_editorial_selection() {
    $sticky = array_values(array_filter(array_map('absint', (array) get_option('sticky_posts', []))));
    $feature = $sticky ? omochix_editorial_posts(['post__in' => $sticky, 'posts_per_page' => 1]) : [];
    $news = get_category_by_slug('ai-news');
    if (!$feature && $news instanceof WP_Term) {
        $feature = omochix_editorial_posts(['cat' => $news->term_id, 'posts_per_page' => 1]);
    }
    $exclude = wp_list_pluck($feature, 'ID');
    $latest = $news instanceof WP_Term ? omochix_editorial_posts(['cat' => $news->term_id, 'post__not_in' => $exclude]) : [];
    $tools = [];
    if (post_type_exists('ai_tool')) {
        $tools = omochix_editorial_posts([
            'post_type' => 'ai_tool',
            'meta_query' => ['relation' => 'AND', omochix_get_active_ai_tool_meta_query(), ['key' => 'is_featured', 'value' => ['1', 'true', 'yes'], 'compare' => 'IN']],
        ]);
        usort($tools, static function ($a, $b) {
            $a_order = get_post_meta($a->ID, 'display_order', true);
            $b_order = get_post_meta($b->ID, 'display_order', true);
            return (is_numeric($a_order) ? (int) $a_order : PHP_INT_MAX) <=> (is_numeric($b_order) ? (int) $b_order : PHP_INT_MAX);
        });
        if (count($tools) < 3) {
            $tools = array_merge($tools, omochix_editorial_posts([
                'post_type' => 'ai_tool', 'posts_per_page' => 3 - count($tools),
                'post__not_in' => wp_list_pluck($tools, 'ID'), 'meta_query' => omochix_get_active_ai_tool_meta_query(),
            ]));
        }
    }
    $prompts = post_type_exists('prompt') ? omochix_editorial_posts([
        'post_type' => 'prompt', 'posts_per_page' => 1,
        'meta_query' => [['key' => 'prompt_body', 'value' => '', 'compare' => '!=']],
    ]) : [];
    return ['feature' => $feature, 'latest' => $latest, 'tools' => $tools, 'prompt' => $prompts ? $prompts[0] : null];
}

function omochix_editorial_media($post, $size = 'large', $priority = false) {
    if (has_post_thumbnail($post)) {
        echo get_the_post_thumbnail($post, $size, [
            'alt' => '', 'loading' => $priority ? 'eager' : 'lazy', 'decoding' => 'async',
        ]);
    } else {
        echo '<span class="ed-media-fallback" aria-hidden="true">Omochi<span>X</span></span>';
    }
}

function omochix_editorial_category($post) {
    $terms = get_the_category($post->ID);
    return $terms ? $terms[0]->name : __('記事', 'omochix');
}

add_filter('body_class', static function ($classes) {
    if (is_front_page()) { $classes[] = 'omx-editorial'; }
    return $classes;
});
add_action('wp_enqueue_scripts', static function () {
    if (!is_front_page()) { return; }
    wp_enqueue_style('omochix-editorial', get_theme_file_uri('/assets/css/editorial-home.css'), ['omochix-style'], (string) filemtime(get_theme_file_path('/assets/css/editorial-home.css')));
    // Reuse exact copy behavior used by existing prompt details.
    wp_enqueue_script('omochix-prompt', get_theme_file_uri('/assets/js/prompt.js'), [], wp_get_theme()->get('Version'), true);
});
