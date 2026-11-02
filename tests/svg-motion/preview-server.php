<?php
/**
 * Renders page-learn-svg-motion.php with a minimal WordPress stub, so the real
 * template, CSS and JS can be tested in a browser without WordPress or a database.
 * Run from the repository root: php -S 127.0.0.1:8788 -t . tests/svg-motion/preview-server.php
 */
$ROOT  = realpath(__DIR__ . '/../..');
$THEME = $ROOT . '/wp-theme/omochix-theme';
$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($ROOT . $path)) { return false; }

define('ABSPATH', '/');
define('OBJECT', 'OBJECT');
class WP_Post { public $ID = 77, $post_status = 'publish', $post_excerpt = '', $post_title = 'SVG モーションスタジオ'; }
$GLOBALS['post'] = new WP_Post();
function add_filter() {} function add_action() {}
function __($t, $d = '') { return $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_attr($t) { return esc_html($t); }
function esc_url($t) { return esc_html($t); }
function esc_html_e($t, $d = '') { echo esc_html($t); }
function esc_attr_e($t, $d = '') { echo esc_attr($t); }
function get_theme_file_path($f) { global $THEME; return $THEME . $f; }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function wp_strip_all_tags($t) { return trim(strip_tags((string) $t)); }
function home_url($p = '/') { return $p; }
function get_page_by_path($p, $o = null, $t = 'page') { return $GLOBALS['post']; }
function is_page() { return true; }
function get_queried_object_id() { return 77; }
function have_posts() { static $n = 0; return $n++ < 1; }
function the_post() {}
function the_title() { echo esc_html($GLOBALS['post']->post_title); }
function has_excerpt() { return false; }
function get_the_excerpt() { return ''; }
function omochix_get_published_page_url($p) { return '/learn/'; }
function get_header() {
    $css = '/wp-theme/omochix-theme/';
    echo '<!doctype html><html lang="ja" data-theme="' . (isset($_GET['dark']) ? 'dark' : 'light') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>SVG Motion Studio</title>';
    echo '<link rel="stylesheet" href="' . $css . 'style.css"><link rel="stylesheet" href="' . $css . 'assets/css/shell.css"><link rel="stylesheet" href="' . $css . 'assets/css/learn-svg-motion.css"></head><body class="omx-shell page">';
}
function get_footer() {
    $js = '/wp-theme/omochix-theme/assets/js/svg-motion/';
    foreach (['lib/purify.min.js', 'safety.js', 'compile.js', 'frame.js', 'studio.js'] as $f) { echo '<script src="' . $js . $f . '"></script>'; }
    echo '</body></html>';
}
require $THEME . '/inc/learn-svg-motion.php';
if (preg_match('#^/learn/svg-motion/?$#', $path)) { include $THEME . '/page-learn-svg-motion.php'; exit; }
http_response_code(404);
echo 'not found (preview stub)';
