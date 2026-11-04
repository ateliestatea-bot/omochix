<?php
/**
 * Minimal WordPress stub for the OmochiX Labs tests (run.php, fixture.php,
 * preview-server.php). Records hooks so filters can be called, and lets a
 * test set the current request with labs_request().
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
$THEME = realpath(__DIR__ . '/../../wp-theme/omochix-theme');

class WP_Post { public $ID, $post_type = 'page', $post_status = 'publish', $post_name = '', $post_title = '', $post_parent = 0, $post_password = '', $post_excerpt = '', $path = null; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
class WP_Query { public $is_404 = false; function set_404() { $this->is_404 = true; } }
class WP_Theme_Stub { function get($k) { return '1.28.0-test'; } }

$GLOBALS['hooks'] = []; $GLOBALS['pages'] = []; $GLOBALS['posts'] = []; $GLOBALS['options'] = [];
$GLOBALS['query_vars'] = []; $GLOBALS['ctx'] = ['is_page' => false, 'queried' => 0];
$GLOBALS['status'] = 200; $GLOBALS['enqueued'] = ['styles' => [], 'scripts' => [], 'inline' => []]; $GLOBALS['rewrite'] = []; $GLOBALS['flushed'] = 0;
$GLOBALS['wp_query'] = new WP_Query();

function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = [$p, $cb, $n]; }
function add_filter($h, $cb, $p = 10, $n = 1) { add_action($h, $cb, $p, $n); }
function apply_filters($h, $value, ...$args) {
    $cbs = $GLOBALS['hooks'][$h] ?? [];
    usort($cbs, static fn($a, $b) => $a[0] <=> $b[0]);
    foreach ($cbs as [$p, $cb, $n]) { $value = $cb($value, ...array_slice($args, 0, max(0, $n - 1))); }
    return $value;
}
function do_action($h, ...$args) {
    $cbs = $GLOBALS['hooks'][$h] ?? [];
    usort($cbs, static fn($a, $b) => $a[0] <=> $b[0]);
    foreach ($cbs as [$p, $cb]) { $cb(...$args); }
}
function __($t, $d = '') { return $t; }
function _e($t, $d = '') { echo $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_attr($t) { return esc_html($t); }
function esc_url($t) { $t = (string) $t; return preg_match('#^(https?://|/)#', $t) || '' === $t ? esc_html($t) : ''; }
function esc_html_e($t, $d = '') { echo esc_html($t); }
function esc_attr_e($t, $d = '') { echo esc_attr($t); }
function esc_html__($t, $d = '') { return esc_html($t); }
function wp_strip_all_tags($t) { return trim(strip_tags((string) $t)); }
function get_theme_file_path($f = '') { global $THEME; return $THEME . $f; }
function get_theme_file_uri($f = '') { return ($GLOBALS['theme_uri'] ?? 'https://example.test/wp-content/themes/omochix-theme') . $f; }
function wp_get_theme() { return new WP_Theme_Stub(); }
function home_url($p = '') { return ($GLOBALS['home'] ?? 'https://example.test') . $p; }
function untrailingslashit($s) { return rtrim($s, '/'); }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function user_trailingslashit($s) { return trailingslashit($s); }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function sanitize_title($t) { $t = strtolower(trim((string) $t)); $t = preg_replace('/[^a-z0-9-]+/', '-', $t); return trim($t, '-'); }
function sanitize_key($t) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $t)); }
function sanitize_text_field($t) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $t))); }
function wp_date($format, $ts = null) { return $GLOBALS['today_override'] ?? (new DateTime('@' . ($ts ?? time())))->setTimezone(new DateTimeZone('Asia/Tokyo'))->format($format); }
function get_page_by_path($path, $o = null, $type = 'page') { return $GLOBALS['pages'][$path] ?? null; }
function get_permalink($p) { $post = $p instanceof WP_Post ? $p : ($GLOBALS['posts_by_id'][$p] ?? null); if (!$post) { return ''; } $base = 'prompt' === $post->post_type ? 'prompts/' : ('ai_tool' === $post->post_type ? 'ai-tools/' : ''); return home_url('/') . $base . ($post->path ?? $post->post_name) . '/'; }
function get_the_title($p = 0) { $post = $p instanceof WP_Post ? $p : ($GLOBALS['posts_by_id'][$p] ?? null); return $post ? $post->post_title : ''; }
function get_query_var($k, $d = '') { return $GLOBALS['query_vars'][$k] ?? $d; }
function is_page() { return $GLOBALS['ctx']['is_page']; }
function is_404() { return $GLOBALS['wp_query']->is_404; }
function is_singular($t = '') { return false; }
function is_admin() { return false; }
function get_queried_object_id() { return $GLOBALS['ctx']['queried']; }
function get_queried_object() { return null; }
function post_type_exists($t) { return in_array($t, ['ai_tool', 'prompt'], true); }
function get_posts($args) {
    return array_values(array_filter($GLOBALS['posts'], static fn($p) => $p->post_type === $args['post_type'] && 'publish' === $p->post_status && in_array($p->post_name, $args['post_name__in'], true)));
}
function get_option($k, $d = false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k, $v) { $GLOBALS['options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['options'][$k]); return true; }
function add_rewrite_rule($regex, $query, $after) { $GLOBALS['rewrite'][] = [$regex, $query, $after]; }
function flush_rewrite_rules($hard = true) { $GLOBALS['flushed']++; }
function status_header($code) { $GLOBALS['status'] = $code; }
function nocache_headers() {}
function wp_style_is($h, $l = 'enqueued') { return isset($GLOBALS['enqueued']['styles'][$h]); }
function wp_enqueue_style($h, $src = '', $deps = [], $ver = false) { $GLOBALS['enqueued']['styles'][$h] = $src; }
function wp_enqueue_script($h, $src = '', $deps = [], $ver = false, $footer = false) { $GLOBALS['enqueued']['scripts'][$h] = ['src' => $src, 'deps' => $deps]; }
function wp_add_inline_script($h, $data, $pos = 'after') { $GLOBALS['enqueued']['inline'][$h][] = $data; }
function get_post_type_archive_link($t) { return 'ai_tool' === $t ? home_url('/ai-tools/') : ('prompt' === $t ? home_url('/prompts/') : ''); }
function omochix_get_published_page_url($slug) { return isset($GLOBALS['pages'][$slug]) ? home_url('/') . $slug . '/' : ''; }
function has_excerpt() { return false; }
function get_the_excerpt() { return ''; }
function the_title() { echo esc_html($GLOBALS['ctx']['title'] ?? 'OmochiX Labs'); }
function get_template_part($slug, $name = null, $args = []) { global $THEME; $file = $THEME . '/' . $slug . '.php'; if (!is_file($file)) { echo "<!-- missing $slug -->"; return false; } include $file; return true; }

/** Reset and set up a request. $slug null = list page; $published false = no labs page. */
function labs_request($slug = null, array $get = [], $published = true, $is_page = true) {
    $GLOBALS['pages'] = ['learn' => new WP_Post(['ID' => 5, 'post_name' => 'learn', 'post_title' => 'Learn'])];
    $GLOBALS['posts_by_id'] = [5 => $GLOBALS['pages']['learn']];
    if ($published) {
        $GLOBALS['pages']['labs'] = new WP_Post(['ID' => 77, 'post_name' => 'labs', 'post_title' => 'OmochiX Labs']);
        $GLOBALS['posts_by_id'][77] = $GLOBALS['pages']['labs'];
    }
    $GLOBALS['query_vars'] = null === $slug ? [] : ['omx_lab' => $slug];
    $GLOBALS['ctx'] = ['is_page' => $is_page, 'queried' => $is_page && $published ? 77 : 0, 'title' => 'OmochiX Labs'];
    $GLOBALS['wp_query'] = new WP_Query();
    $GLOBALS['status'] = 200;
    $_GET = $get;
    labs_reset_statics();
}

/** inc/labs.php caches the page id, catalog and views in statics; re-include it in a fresh scope via a flag. */
function labs_reset_statics() {
    $GLOBALS['labs_static_epoch'] = ($GLOBALS['labs_static_epoch'] ?? 0) + 1;
}

// inc/labs.php keeps its caches in function statics; wrap them so tests can reset them.
$labs_src = file_get_contents($THEME . '/inc/labs.php');
$labs_src = preg_replace('/static \$(page_id|catalog|view) = null;\n(\s+)if \(null !== \$\1\) \{/', 'static \$$1 = null; static \$epoch = 0;' . "\n" . '$2if (($GLOBALS[\'labs_static_epoch\'] ?? 0) !== $epoch) { \$$1 = null; \$epoch = $GLOBALS[\'labs_static_epoch\'] ?? 0; }' . "\n" . '$2if (null !== \$$1) {', $labs_src, -1, $count);
$labs_src = preg_replace('/static \$(catalog|view) = null;\n(\s+)if \(null === \$\1\) \{/', 'static \$$1 = null; static \$epoch = 0;' . "\n" . '$2if (($GLOBALS[\'labs_static_epoch\'] ?? 0) !== $epoch) { \$$1 = null; \$epoch = $GLOBALS[\'labs_static_epoch\'] ?? 0; }' . "\n" . '$2if (null === \$$1) {', $labs_src, -1, $count2);
$GLOBALS['labs_static_wraps'] = $count + $count2;
$tmp = sys_get_temp_dir() . '/omochix-labs-under-test-' . getmypid() . '.php';
file_put_contents($tmp, $labs_src);
require $tmp;
unlink($tmp);
