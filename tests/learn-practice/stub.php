<?php
/**
 * WordPress stub for the Learn Practice tests: loads the Core plugin's
 * practice modules and the theme's inc/learn-practice.php without
 * WordPress. Registration calls are recorded so the tests can assert the
 * post type / taxonomy / meta contracts; post meta and terms live in arrays.
 */
// The gallery stub's helpers are repeated here (it is not required: two of
// them, post_type_exists() and get_the_title(), must behave differently).
define('ABSPATH', '/');
define('OBJECT', 'OBJECT');
$THEME = realpath(__DIR__ . '/../../wp-theme/omochix-theme');
class WP_Post { public $ID, $post_type = 'page', $post_status = 'publish', $post_name = '', $post_title = '', $post_parent = 0, $post_excerpt = '', $post_content = '', $menu_order = 0; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
$GLOBALS['pages'] = []; $GLOBALS['pages_by_id'] = []; $GLOBALS['query_vars'] = []; $GLOBALS['ctx'] = ['is_page' => false, 'queried' => 0];
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
function omochix_get_published_page_url($path) { $p = get_page_by_path($path); return $p instanceof WP_Post && 'publish' === $p->post_status ? get_permalink($p) : ''; }
require $THEME . '/inc/learn-gallery.php';

$PLUGIN = realpath(__DIR__ . '/../../wp-plugin/omochix-core');
define('OMOCHIX_CORE_DIR', $PLUGIN . '/');
define('OMOCHIX_CORE_URL', 'https://example.test/wp-content/plugins/omochix-core/');
define('OMOCHIX_CORE_VERSION', 'test');
define('DAY_IN_SECONDS', 86400);
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['registered'] = ['post_types' => [], 'taxonomies' => [], 'meta' => [], 'rewrite' => []];
$GLOBALS['meta'] = [];       // post_id => [key => value]
$GLOBALS['terms'] = [];      // taxonomy => [slug => ['name' => …]]
$GLOBALS['post_terms'] = []; // post_id => [taxonomy => [slug…]]
$GLOBALS['posts'] = [];      // post_id => WP_Post
$GLOBALS['options'] = [];

class WP_Term { public $term_id, $name, $slug, $taxonomy, $count = 0, $description = ''; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
class WP_Error {}
class WP_Query { public $posts = []; public $found_posts = 0; function __construct($args = []) { $GLOBALS['last_query'] = $args; $this->posts = $GLOBALS['query_result'] ?? []; } }

function register_post_type($name, $args) { $GLOBALS['registered']['post_types'][$name] = $args; }
function register_taxonomy($name, $types, $args) { $GLOBALS['registered']['taxonomies'][$name] = ['types' => (array) $types, 'args' => $args]; $GLOBALS['terms'][$name] = $GLOBALS['terms'][$name] ?? []; }
function register_post_meta($type, $key, $args) { $GLOBALS['registered']['meta'][$type][$key] = $args; }
function add_rewrite_rule($re, $q, $after = 'bottom') { $GLOBALS['registered']['rewrite'][] = [$re, $q, $after]; }
function post_type_exists($t) { return isset($GLOBALS['registered']['post_types'][$t]) || in_array($t, ['prompt', 'ai_tool', 'page'], true); }
function taxonomy_exists($t) { return isset($GLOBALS['registered']['taxonomies'][$t]); }
function term_exists($slug, $tax) { return isset($GLOBALS['terms'][$tax][$slug]) ? 1 : 0; }
function wp_insert_term($name, $tax, $args) { $GLOBALS['terms'][$tax][$args['slug']] = ['name' => $name]; return ['term_id' => count($GLOBALS['terms'][$tax])]; }
function get_terms($args) { $out = []; foreach ($GLOBALS['terms'][$args['taxonomy']] ?? [] as $slug => $t) { $out[] = new WP_Term(['term_id' => crc32($slug), 'name' => $t['name'], 'slug' => $slug, 'taxonomy' => $args['taxonomy'], 'count' => $t['count'] ?? 1]); } return $out; }
function get_the_terms($id, $tax) { $out = []; foreach ($GLOBALS['post_terms'][$id][$tax] ?? [] as $slug) { $t = $GLOBALS['terms'][$tax][$slug] ?? ['name' => $slug]; $out[] = new WP_Term(['term_id' => crc32($slug), 'name' => $t['name'], 'slug' => $slug, 'taxonomy' => $tax]); } return $out; }
function get_term_link($t) { return 'https://example.test/learn/' . str_replace('learn_', '', $t->taxonomy) . '/' . $t->slug . '/'; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta($id, $key, $v) { $GLOBALS['meta'][$id][$key] = is_string($v) ? stripslashes($v) : $v; return true; }
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); return true; }
function get_post($p = null) { if ($p instanceof WP_Post) { return $p; } return $GLOBALS['posts'][(int) $p] ?? null; }
function get_post_type($id) { $p = get_post($id); return $p ? $p->post_type : false; }
function get_posts($args) {
    // Name lookups (importer): posts by post_type + post_name; otherwise the canned result.
    if (isset($args['name'])) { foreach ($GLOBALS['posts'] as $p) { if ($p->post_type === ($args['post_type'] ?? $p->post_type) && $p->post_name === $args['name']) { return ($args['fields'] ?? '') === 'ids' ? [$p->ID] : [$p]; } } return []; }
    if (isset($args['post_type']) && 'learn_practice' === $args['post_type'] && ($args['fields'] ?? '') === 'ids' && !isset($GLOBALS['query_result'])) { return array_keys(array_filter($GLOBALS['posts'], static fn($p) => 'learn_practice' === $p->post_type)); }
    return $GLOBALS['query_result'] ?? [];
}
function get_post_field($field, $id) { $p = get_post($id); return $p ? ($p->$field ?? '') : ''; }
function wp_insert_post($a, $wp_error = false) { $id = max(array_keys($GLOBALS['posts'] ?: [0])) + 1; $GLOBALS['posts'][$id] = new WP_Post(['ID' => $id, 'post_type' => $a['post_type'], 'post_name' => $a['post_name'], 'post_title' => $a['post_title'] ?? '', 'post_status' => $a['post_status'] ?? 'draft', 'post_excerpt' => $a['post_excerpt'] ?? '', 'post_content' => $a['post_content'] ?? '', 'menu_order' => $a['menu_order'] ?? 0]); $GLOBALS['inserted'][] = $id; return $id; }
function wp_update_post($a, $wp_error = false) { $p = $GLOBALS['posts'][$a['ID']] ?? null; if (!$p) { return 0; } foreach ($a as $k => $v) { if ('ID' !== $k) { $p->$k = is_string($v) ? stripslashes($v) : $v; } } return $a['ID']; }
function wp_set_object_terms($id, $slugs, $tax, $append = false) { $GLOBALS['post_terms'][$id][$tax] = array_values((array) $slugs); return $slugs; }
function wp_slash($v) { return is_array($v) ? array_map('wp_slash', $v) : (is_string($v) ? addslashes($v) : $v); }
function current_time($t) { return '2026-10-21 00:00:00'; }
function get_userdata($id) { return null; }
function wp_count_posts($t) { return (object) ['publish' => $GLOBALS['practice_count'] ?? 0]; }
function get_option($k, $d = false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k, $v) { $GLOBALS['options'][$k] = $v; return true; }
function flush_rewrite_rules($hard = true) { $GLOBALS['flushed'] = ($GLOBALS['flushed'] ?? 0) + 1; }
function apply_filters($hook, $value) { return $value; }
function current_user_can() { return true; }
function wp_attachment_is_image($id) { return $id > 0; }
function wp_check_invalid_utf8($s) { return $s; }
function wp_kses_post($s) { return preg_replace('#<(script|style|iframe)[^>]*>.*?</\1>#is', '', (string) $s); }
function sanitize_textarea_field($t) { return trim(strip_tags((string) $t)); }
function esc_url_raw($u, $p = []) { return preg_match('#^https?://#i', (string) $u) ? $u : ''; }
function absint($v) { return abs((int) $v); }
function wp_strip_all_tags($t) { return trim(strip_tags((string) $t)); }
function get_post_type_archive_link($t) { return 'https://example.test/learn/practice/'; }
function is_admin() { return false; }
function is_tax($t = '') { return $GLOBALS['ctx']['is_tax'] ?? false; }
function is_post_type_archive($t = '') { return $GLOBALS['ctx']['is_archive'] ?? false; }
function has_excerpt($p = null) { return false; }
function get_the_title($p) { $p = get_post($p); return $p ? $p->post_title : 'Title'; }
function wp_get_theme() { return new class { function get($k) { return 'test'; } }; }
function wp_enqueue_style() {} function wp_enqueue_script() {} function wp_add_inline_script() {}
function get_post_ancestors($p) { return $GLOBALS['ancestors'][$p->ID] ?? []; }
function wp_unique_id($prefix = '') { static $n = 0; return $prefix . (++$n); }

require $PLUGIN . '/includes/prompt-meta-schema.php';
require $PLUGIN . '/includes/relations.php';
require $PLUGIN . '/includes/practice-post-type.php';
require $PLUGIN . '/includes/practice-taxonomies.php';
require $PLUGIN . '/includes/practice-meta-schema.php';
require $PLUGIN . '/includes/practice-relations.php';
require $PLUGIN . '/includes/practice-importer.php';
require $THEME . '/inc/learn.php';
require $THEME . '/inc/learn-composer.php';
require $THEME . '/inc/learn-practice.php';
