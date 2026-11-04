<?php
/**
 * OmochiX Labs preview server: renders /labs/ and /labs/{slug}/ with the
 * test stub, the theme's real CSS / JS and the same security headers as
 * production, so the tools can be checked in a browser without WordPress.
 *
 * Run from the repository root:
 *   php -S 127.0.0.1:8790 -t . tests/labs/preview-server.php
 * Then open http://127.0.0.1:8790/labs/ (add ?gtag=1 to record analytics
 * events in window.__labsEvents instead of sending them anywhere).
 *
 * Not a substitute for a real WordPress run: no Slim SEO, no shell sidebar
 * markup, fixture pages only.
 */
$ROOT = realpath(__DIR__ . '/../..');
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ('/' !== $path && is_file($ROOT . $path)) {
    return false; // static asset
}
if ('/' === $path) {
    header('Location: /labs/');
    exit;
}

require __DIR__ . '/stub.php';
$origin             = 'http://' . $_SERVER['HTTP_HOST'];
$GLOBALS['home']      = $origin;
$GLOBALS['theme_uri'] = $origin . '/wp-theme/omochix-theme';

if (!preg_match('#^/labs(?:/([a-z0-9-]+))?/?$#', $path, $m)) {
    http_response_code(404);
    echo '<!doctype html><title>404</title><p>Not found (preview)</p>';
    exit;
}
parse_str((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $query);
labs_request($m[1] ?? null, $query);
do_action('template_redirect');
if (is_404()) {
    http_response_code(404);
    echo '<!doctype html><html lang="ja"><title>404</title><p>404: ' . esc_html($path) . '</p></html>';
    exit;
}
// Same headers as production (template_redirect sent them through header()).

$GLOBALS['enqueued']['styles']['omochix-style'] = $GLOBALS['theme_uri'] . '/style.css';
$GLOBALS['enqueued']['styles']['omochix-shell'] = $GLOBALS['theme_uri'] . '/assets/css/shell.css';
do_action('wp_enqueue_scripts');

function get_header() {
    $theme = isset($_GET['theme']) && 'dark' === $_GET['theme'] ? ' data-theme="dark"' : '';
    echo '<!doctype html><html lang="ja"' . $theme . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . esc_html(omochix_labs_meta_title()) . '</title>';
    echo '<meta name="description" content="' . esc_attr(omochix_labs_meta_description()) . '">';
    echo '<link rel="canonical" href="' . esc_url(omochix_labs_canonical()) . '">';
    foreach ($GLOBALS['enqueued']['styles'] as $src) {
        echo '<link rel="stylesheet" href="' . esc_url($src) . '">';
    }
    if (!empty($_GET['gtag'])) {
        echo '<script>window.__labsEvents=[];window.gtag=function(){window.__labsEvents.push(Array.prototype.slice.call(arguments));};</script>';
    }
    echo '</head><body class="omx-shell omx-labs"><a class="skip-link" href="#main-content">本文へ移動</a>';
}
function get_footer() {
    echo '<footer style="padding:24px;text-align:center">preview footer</footer>';
    foreach ($GLOBALS['enqueued']['scripts'] as $handle => $script) {
        foreach ($GLOBALS['enqueued']['inline'][$handle] ?? [] as $inline) {
            echo '<script>' . $inline . '</script>';
        }
        echo '<script src="' . esc_url($script['src']) . '"></script>';
    }
    echo '</body></html>';
}
$GLOBALS['have_posts'] = 1;
function have_posts() { return $GLOBALS['have_posts']-- > 0; }
function the_post() {}

include get_theme_file_path('/page-labs.php');
