<?php
/**
 * page-labs.php without inc/labs.php (functions.php rolled back while the
 * template and a "labs" page remain): it must answer a 404 with the theme's
 * 404 template, send no-cache headers and never call a Labs function.
 *
 * Runs in its own process (no Labs functions may exist). Used by run.php;
 * prints one JSON line. Optional argument: the template file to load.
 */
define('ABSPATH', '/');
$THEME = realpath(__DIR__ . '/../../wp-theme/omochix-theme');

class WP_Query { public $is_404 = false; function set_404() { $this->is_404 = true; } }
$GLOBALS['wp_query'] = new WP_Query();
$GLOBALS['log']      = [];

function status_header($code) { $GLOBALS['log'][] = "status:$code"; }
function nocache_headers() { $GLOBALS['log'][] = 'nocache'; }
function get_404_template() { return $GLOBALS['tpl404']; }
function get_header() { $GLOBALS['log'][] = 'get_header'; }
function get_footer() { $GLOBALS['log'][] = 'get_footer'; }
$GLOBALS['posts_left'] = 1; // one post, as for the published "labs" page
function have_posts() { $GLOBALS['log'][] = 'have_posts'; return $GLOBALS['posts_left']-- > 0; }
function the_post() {}

// Stand-in for the theme's 404.php (the real one needs all of WordPress).
$GLOBALS['tpl404'] = sys_get_temp_dir() . '/omx-labs-404-' . getmypid() . '.php';
file_put_contents($GLOBALS['tpl404'], '<?php $GLOBALS["log"][] = "404-template:" . (is_object($GLOBALS["wp_query"]) && $GLOBALS["wp_query"]->is_404 ? "is_404" : "not_404");');

$error = null;
try {
    ob_start();
    include $argv[1] ?? $THEME . '/page-labs.php'; // optional: another copy of the template (run.php checks a control)
    $out = ob_get_clean();
} catch (Throwable $e) {
    $error = get_class($e) . ': ' . $e->getMessage();
    ob_end_clean();
}
unlink($GLOBALS['tpl404']);
$labs = array_values(array_filter(get_defined_functions()['user'], static fn($f) => 0 === strpos($f, 'omochix_labs_')));
echo json_encode(['error' => $error, 'log' => $GLOBALS['log'], 'labs_functions' => $labs]) . "\n";
