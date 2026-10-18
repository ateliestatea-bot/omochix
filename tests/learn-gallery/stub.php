<?php
/**
 * Minimal WordPress stub shared by the Learn v2 / Motion Gallery tests
 * (run.php, params-fixture.php). Loads inc/learn-gallery.php without
 * WordPress, a database or the network.
 */
define('ABSPATH', '/');
define('OBJECT', 'OBJECT');
$THEME = realpath(__DIR__ . '/../../wp-theme/omochix-theme');

class WP_Post { public $ID, $post_type = 'page', $post_status = 'publish', $post_name = ''; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
$GLOBALS['pages'] = []; $GLOBALS['query_vars'] = []; $GLOBALS['ctx'] = ['is_page' => false, 'queried' => 0];
function add_action() {} function add_filter() {}
function __($t) { return $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_url($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function get_theme_file_path($f) { global $THEME; return $THEME . $f; }
function get_theme_file_uri($f) { return 'https://example.test/wp-content/themes/omochix-theme' . $f; }
function untrailingslashit($s) { return rtrim($s, '/'); }
function is_singular($t = '') { return $GLOBALS['ctx']['singular'] ?? false; }
function get_queried_object() { return $GLOBALS['ctx']['queried_post'] ?? null; }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function sanitize_title($t) { $t = strtolower(trim((string) $t)); $t = preg_replace('/[^a-z0-9-]+/', '-', $t); return trim($t, '-'); }
function sanitize_key($t) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $t)); }
function sanitize_text_field($t) { return trim(strip_tags((string) $t)); }
function get_page_by_path($path, $o = null, $type = 'page') { return $GLOBALS['pages'][$path] ?? null; }
function get_permalink($p) { return 'https://example.test/' . ($p instanceof WP_Post ? $p->post_name : $GLOBALS['pages_by_id'][$p]) . '/'; }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function user_trailingslashit($s) { return trailingslashit($s); }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function get_query_var($k, $d = '') { return $GLOBALS['query_vars'][$k] ?? $d; }
function is_page() { return $GLOBALS['ctx']['is_page']; }
function get_queried_object_id() { return $GLOBALS['ctx']['queried']; }
function post_type_exists($t) { return false; }
function get_the_title($p) { return 'Title'; }
require $THEME . '/inc/learn-gallery.php';
