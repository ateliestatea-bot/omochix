<?php
/**
 * Minimal WordPress stub for the Design Library unit tests (run.php,
 * validate-cli.php). Loads inc/design-library.php and inc/design-library-demo.php
 * without WordPress, a database or the network.
 */
define('ABSPATH', '/');
define('OBJECT', 'OBJECT');
$THEME = realpath(__DIR__ . '/../../wp-theme/omochix-theme');

class WP_Post { public $ID, $post_type = 'page', $post_status = 'publish', $post_name = '', $post_password = '', $post_title = ''; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
$GLOBALS['stub'] = ['filters' => [], 'pages' => [], 'qv' => [], 'env' => 'local', 'is_page' => false, 'queried' => 0, 'terms' => []];
function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['stub']['actions'][$h][] = $cb; }
function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['stub']['actions'][$h][] = $cb; }
function apply_filters($h, $v, ...$args) { foreach ($GLOBALS['stub']['filters'][$h] ?? [] as $cb) { $v = $cb($v, ...$args); } return $v; }
function __($t, $d = '') { return $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_html__($t, $d = '') { return esc_html($t); }
function esc_attr($t) { return esc_html($t); }
function esc_url($t) { return esc_html($t); }
function get_theme_file_path($f) { global $THEME; return $THEME . $f; }
function get_theme_file_uri($f) { return 'https://example.test/wp-content/themes/omochix-theme' . $f; }
function home_url($p = '/') { return 'https://example.test' . $p; }
function wp_parse_url($u) { return parse_url($u); }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function sanitize_text_field($t) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $t))); }
function sanitize_key($t) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $t)); }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function add_query_arg($args, $url) { $q = []; foreach ($args as $k => $v) { $q[] = $k . '=' . $v; } return $url . '?' . implode('&', $q); }
function get_page_by_path($p, $o = null, $t = 'page') { return $GLOBALS['stub']['pages'][$p] ?? null; }
function get_permalink($id) { if ($id instanceof WP_Post) { return 'https://example.test/' . $id->post_name . '/'; } foreach ($GLOBALS['stub']['pages'] as $path => $pg) { if ($pg->ID === $id) { return 'https://example.test/' . $path . '/'; } } return ''; }
function wp_get_environment_type() { return $GLOBALS['stub']['env']; }
function taxonomy_exists($t) { return 'omx_industry' === $t && [] !== $GLOBALS['stub']['terms']; }
function omochix_core_industry_is_public() { return true; }
function get_terms($a) { $out = []; foreach ($GLOBALS['stub']['terms'] as $slug => $name) { $out[] = (object) ['slug' => $slug, 'name' => $name]; } return $out; }
function get_query_var($k, $d = '') { return $GLOBALS['stub']['qv'][$k] ?? $d; }
function is_page() { return $GLOBALS['stub']['is_page']; }
function get_queried_object_id() { return $GLOBALS['stub']['queried']; }
function status_header($c) { http_response_code($c); }
function did_action($h) { return 0; }
function get_the_title($p = null) { return $p instanceof WP_Post ? (string) ($p->post_title ?? '') : ''; }
function error_log_stub() {}

require $THEME . '/inc/design-library.php';
require $THEME . '/inc/design-library-demo.php';

/** Publish the "design" page (C1) or remove it. */
function stub_page($status) {
    $GLOBALS['stub']['pages'] = $status ? ['design' => new WP_Post(['ID' => 77, 'post_name' => 'design', 'post_status' => $status])] : [];
    omochix_design_reset_cache();
}
