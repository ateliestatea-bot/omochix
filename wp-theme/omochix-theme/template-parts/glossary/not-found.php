<?php
/**
 * AI glossary: answer like any missing page (the glossary is closed, its
 * module is missing, or the term is not visible). Used by the glossary
 * templates, which WordPress may still pick by name.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wp_query;
if ($wp_query instanceof WP_Query) {
    $wp_query->set_404();
}
status_header(404);
nocache_headers();
if (!headers_sent()) {
    header('X-Robots-Tag: noindex, follow');
}
$omochix_404 = get_404_template();
if ($omochix_404) {
    include $omochix_404;
}
