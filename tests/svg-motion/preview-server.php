<?php
/**
 * Renders page-learn-svg-motion.php with a minimal WordPress stub, so the real
 * template, CSS and JS can be tested in a browser without WordPress or a database.
 * Run from the repository root: php -S 127.0.0.1:8788 -t . tests/svg-motion/preview-server.php
 */
$ROOT  = realpath(__DIR__ . '/../..');
$THEME = $ROOT . '/wp-theme/omochix-theme';
$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($ROOT . rawurldecode($path))) { return false; }
// WordPress's REST API answers JSONP for /wp-json/... and for ANY other URL that carries ?rest_route= when the file does
// not exist (WordPress handles every missing file). Modelled here so csp-tests.js can check, without WordPress, that the
// studio's script-src stops all of these routes (V-2). Existing files are served as they are (above), as on a real server.
if (isset($_GET['_jsonp']) && (0 === strpos($path, '/wp-json') || isset($_GET['rest_route']))) {
    $cb = (string) $_GET['_jsonp'];
    if (!preg_match('/^[\w.]+$/D', $cb)) { http_response_code(400); header('Content-Type: application/json'); echo '{"code":"rest_callback_invalid"}'; exit; }
    header('Content-Type: application/javascript; charset=UTF-8'); header('X-Content-Type-Options: nosniff');
    echo '/**/' . $cb . '({"name":"preview stub","url":"http://127.0.0.1:8788"})'; exit;
}

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
// Absolute, as WordPress returns it (the studio CSP names each script by its full URL).
function get_theme_file_uri($f) { return 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8788') . '/wp-theme/omochix-theme' . $f; }
function remove_action() {}
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
function checked($a, $b = true) { echo $a === $b ? ' checked' : ''; }
function get_the_excerpt() { return ''; }
function omochix_get_published_page_url($p) { return '/learn/'; }
function get_header() {
    $css = '/wp-theme/omochix-theme/';
    echo '<!doctype html><html lang="ja" data-theme="' . (isset($_GET['dark']) ? 'dark' : 'light') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>SVG Motion Studio</title>';
    echo '<link rel="stylesheet" href="' . $css . 'style.css"><link rel="stylesheet" href="' . $css . 'assets/css/shell.css"><link rel="stylesheet" href="' . $css . 'assets/css/learn-svg-motion.css"></head><body class="omx-shell page">';
}
function get_footer() {
    $js = '/wp-theme/omochix-theme/assets/js/svg-motion/';
    foreach (['lib/purify.min.js', 'safety.js', 'compile.js', 'frame.js', 'analyze.js', 'import.js', 'preview-image.js', 'store.js', 'export.js', 'studio.js'] as $f) { echo '<script src="' . $js . $f . '"></script>'; }
    echo '</body></html>';
}
require $THEME . '/inc/learn-svg-motion.php';
// The studio page's headers as the theme sends them (omochix_svg_motion_page_headers(): X-Frame-Options and the CSP):
// SVGM_CSP (or ?csp=) off | report | enforce; off when unset. Test only: ?csp=enforce-self sends the policy with
// script-src 'self' (the policy V-2 replaced), as the positive control for the JSONP tests.
if (preg_match('#^/learn/svg-motion/?$#', $path)) {
    $csp = isset($_GET['csp']) ? (string) $_GET['csp'] : (string) getenv('SVGM_CSP');
    if ('enforce-self' === $csp) {
        header('X-Frame-Options: SAMEORIGIN');
        header('Content-Security-Policy: ' . preg_replace('/script-src [^;]*/', "script-src 'self'", omochix_svg_motion_csp_policy()));
    } else {
        define('OMOCHIX_SVG_MOTION_CSP', in_array($csp, ['report', 'enforce'], true) ? $csp : 'off');
        omochix_svg_motion_page_headers();
    }
    include $THEME . '/page-learn-svg-motion.php';
    exit;
}
http_response_code(404);
echo 'not found (preview stub)';
