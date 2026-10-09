<?php
/** Shared editorial shell (left sidebar nav on desktop). Enabled per template, page by page. */
if (!defined('ABSPATH')) { exit; }

/** Pages that currently use the shell. Extend this list as more templates are migrated. */
function omochix_shell_enabled() {
    return !is_front_page() && (is_search() || is_home() || is_category() || is_tag() || is_page(['about', 'contact'])
        || is_post_type_archive(['ai_tool', 'prompt']) || is_tax(['ai_tool_category', 'prompt_category', 'prompt_model'])
        || is_singular(['prompt', 'ai_tool', 'post']));
}

add_filter('body_class', static function ($classes) {
    if (omochix_shell_enabled()) { $classes[] = 'omx-shell'; }
    return $classes;
});
add_action('wp_enqueue_scripts', static function () {
    if (!omochix_shell_enabled()) { return; }
    wp_enqueue_style('omochix-shell', get_theme_file_uri('/assets/css/shell.css'), ['omochix-style'], (string) filemtime(get_theme_file_path('/assets/css/shell.css')));
});
