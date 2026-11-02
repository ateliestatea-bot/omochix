<?php
/**
 * Learn / SVG Motion Studio: logo motion presets.
 *
 * Page: "/learn/svg-motion/" (a child page of Learn). Nothing is uploaded or
 * stored on the server: no options, posts, REST routes or media uploads. All
 * SVG handling happens in the browser, and display happens only in a sandboxed
 * frame (see assets/js/svg-motion/). Independent of the Motion Gallery.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

const OMOCHIX_SVG_MOTION_PATH = 'learn/svg-motion';

/**
 * Limits taken from the measurements in docs/motion/SVG-MOTION-S1B-RESULT.md:
 * 200 animated parts or shapes held 60fps at a 20x CPU slowdown. Above maxParts
 * a logo animates as one piece; above maxShapes the per-shape presets are off.
 */
const OMOCHIX_SVG_MOTION_BUDGET = ['maxParts' => 200, 'maxShapes' => 200];

/** Published page ID, or 0. */
function omochix_svg_motion_page_id() {
    static $page_id = null;
    if (null !== $page_id) {
        return $page_id;
    }
    $page    = get_page_by_path(OMOCHIX_SVG_MOTION_PATH, OBJECT, 'page');
    $page_id = $page instanceof WP_Post && 'publish' === $page->post_status ? (int) $page->ID : 0;
    return $page_id;
}

/** Whether the main query is the studio page. */
function omochix_svg_motion_is_request() {
    if (!is_page()) {
        return false;
    }
    $page_id = omochix_svg_motion_page_id();
    return $page_id && get_queried_object_id() === $page_id;
}

/**
 * Presets and sample logos, or an empty list when the file is unusable.
 *
 * @return array{presets: array<int, array<string, mixed>>, samples: array<int, array<string, string>>}
 */
function omochix_svg_motion_data() {
    static $data = null;
    if (null !== $data) {
        return $data;
    }
    $empty = ['presets' => [], 'samples' => []];
    $file  = get_theme_file_path('/assets/data/svg-motion/presets.json');
    $raw   = is_readable($file) ? file_get_contents($file) : false;
    $json  = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($json) || empty($json['presets']) || empty($json['samples'])) {
        $data = $empty;
        return $data;
    }
    $data = ['presets' => $json['presets'], 'samples' => $json['samples']];
    return $data;
}

/** JSON for studio.js, safe inside a script element. */
function omochix_svg_motion_data_json() {
    $data           = omochix_svg_motion_data();
    $data['budget'] = OMOCHIX_SVG_MOTION_BUDGET;
    return (string) wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

/** Serve page-learn-svg-motion.php; runs before the Learn article swap (10). */
function omochix_svg_motion_template_include($template) {
    if (!omochix_svg_motion_is_request()) {
        return $template;
    }
    $file = get_theme_file_path('/page-learn-svg-motion.php');
    return file_exists($file) ? $file : $template;
}
add_filter('template_include', 'omochix_svg_motion_template_include', 8);

/**
 * Keeps the studio out of the sibling list on Learn articles. page-learn-article.php
 * builds its related list and NEXT link from a WP_Query of the pages under the same
 * parent, so a new child of Learn would otherwise change existing articles. Only that
 * kind of query is affected: the main query and the sitemap still find the page.
 *
 * @param WP_Query $query Query about to run.
 */
function omochix_svg_motion_exclude_from_siblings($query) {
    if (is_admin() || $query->is_main_query() || 'page' !== $query->get('post_type')) {
        return;
    }
    $page_id = omochix_svg_motion_page_id();
    if (!$page_id || !function_exists('omochix_get_learn_page_id') || (int) $query->get('post_parent') !== omochix_get_learn_page_id()) {
        return;
    }
    $query->set('post__not_in', array_merge((array) $query->get('post__not_in'), [$page_id]));
}
add_action('pre_get_posts', 'omochix_svg_motion_exclude_from_siblings');

function omochix_svg_motion_enqueue_assets() {
    if (!omochix_svg_motion_is_request()) {
        return;
    }
    $version = wp_get_theme()->get('Version');
    $deps    = wp_style_is('omochix-shell', 'enqueued') ? ['omochix-style', 'omochix-shell'] : ['omochix-style'];
    wp_enqueue_style('omochix-svg-motion', get_theme_file_uri('/assets/css/learn-svg-motion.css'), $deps, (string) filemtime(get_theme_file_path('/assets/css/learn-svg-motion.css')));
    // In dependency order; only this page loads them.
    wp_enqueue_script('omochix-dompurify', get_theme_file_uri('/assets/js/svg-motion/lib/purify.min.js'), [], '3.4.17', true);
    wp_enqueue_script('omochix-svg-safety', get_theme_file_uri('/assets/js/svg-motion/safety.js'), ['omochix-dompurify'], $version, true);
    wp_enqueue_script('omochix-svg-compile', get_theme_file_uri('/assets/js/svg-motion/compile.js'), [], $version, true);
    wp_enqueue_script('omochix-svg-frame', get_theme_file_uri('/assets/js/svg-motion/frame.js'), ['omochix-svg-safety', 'omochix-svg-compile'], $version, true);
    wp_enqueue_script('omochix-svg-studio', get_theme_file_uri('/assets/js/svg-motion/studio.js'), ['omochix-svg-frame'], $version, true);
}
add_action('wp_enqueue_scripts', 'omochix_svg_motion_enqueue_assets', 20);
