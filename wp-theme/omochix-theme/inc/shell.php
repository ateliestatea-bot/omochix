<?php
/** Shared editorial shell (left sidebar nav on desktop). Enabled per template, page by page. */
if (!defined('ABSPATH')) { exit; }

/** Remember which template file WordPress finally chose (after the Learn template swap in inc/learn.php). */
add_filter('template_include', static function ($template) {
    $GLOBALS['omochix_shell_template'] = basename((string) $template);
    return $template;
}, 99);

/**
 * Pages that use one of the theme's own page templates. Generic pages that fall back to index.php (a placeholder)
 * and pages with a custom page template are deliberately left alone.
 */
function omochix_shell_page_enabled() {
    if (get_page_template_slug()) { return false; }
    $template = isset($GLOBALS['omochix_shell_template']) ? $GLOBALS['omochix_shell_template'] : '';
    return in_array($template, [
        'page-about.php', 'page-contact.php', 'page-company.php', 'page-privacy-policy.php', 'page-terms.php',
        'page-learn.php', 'page-learn-article.php',
    ], true);
}

/** Templates that currently use the shell. Extend this list as more templates are migrated. */
function omochix_shell_enabled() {
    if (is_front_page()) { return false; }
    return is_404() || is_search() || is_home() || is_category() || is_tag()
        || is_post_type_archive(['ai_tool', 'prompt']) || is_tax(['ai_tool_category', 'prompt_category', 'prompt_model'])
        || is_singular(['prompt', 'ai_tool', 'post'])
        || (is_page() && omochix_shell_page_enabled());
}

add_filter('body_class', static function ($classes) {
    if (omochix_shell_enabled()) { $classes[] = 'omx-shell'; }
    return $classes;
});
add_action('wp_enqueue_scripts', static function () {
    if (!omochix_shell_enabled()) { return; }
    wp_enqueue_style('omochix-shell', get_theme_file_uri('/assets/css/shell.css'), ['omochix-style'], (string) filemtime(get_theme_file_path('/assets/css/shell.css')));
});

/** Keep the old footer's "カテゴリー" link (only when that page is published) on the shared footer. */
add_filter('omochix_editorial_footer_links', static function ($links) {
    if (!omochix_shell_enabled()) { return $links; }
    $url = omochix_get_published_page_url('category');
    if (!$url) { return $links; }
    $item = ['label' => __('カテゴリー', 'omochix'), 'icon' => '', 'url' => $url];
    foreach ($links as $i => $link) {
        if (isset($link['label']) && 'About' === $link['label']) {
            array_splice($links, $i, 0, [$item]);
            return $links;
        }
    }
    $links[] = $item;
    return $links;
});
