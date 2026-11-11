<?php
/**
 * OmochiX Labs list ("/labs/") and tool pages ("/labs/{slug}/").
 *
 * Served by the template_include filter in inc/labs.php for the page at path
 * "labs". With the `omx_lab` query var set to a published tool the tool page
 * is rendered; an unknown tool is a 404 before this template runs.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * WordPress also picks this file by name for a page with the slug "labs"
 * (page-{slug}.php), even when inc/labs.php is not loaded — for example
 * after functions.php was rolled back while this file and the page remain.
 * Every function used here and in template-parts/labs/ comes from
 * inc/labs.php, so one check covers them all. Without it: a plain 404
 * instead of a fatal error. Rollback still sets the page to draft first.
 */
if (!function_exists('omochix_labs_current_tool')) {
    global $wp_query;
    if ($wp_query instanceof WP_Query) {
        $wp_query->set_404();
    }
    status_header(404);
    nocache_headers();
    $omochix_labs_404 = get_404_template();
    if ($omochix_labs_404 && realpath($omochix_labs_404) !== realpath(__FILE__)) {
        include $omochix_labs_404;
    }
    return;
}

get_header();

while (have_posts()) :
    the_post();
    $omochix_labs_tool = omochix_labs_current_tool();
    if ($omochix_labs_tool) {
        get_template_part('template-parts/labs/tool', null, ['tool' => $omochix_labs_tool]);
    } else {
        get_template_part('template-parts/labs/index');
    }
endwhile;

get_footer();
