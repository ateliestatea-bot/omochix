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
