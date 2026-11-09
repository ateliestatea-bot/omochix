<?php
/**
 * Standalone WordPress stub for the Industry Hub tests.
 *
 * Loads the whole OmochiX Core plugin (omochix-core.php) without WordPress.
 * Registrations are recorded, hooks are real (add_filter / apply_filters /
 * do_action run the callbacks), and posts, terms, post meta and term meta live
 * in arrays. Capabilities and nonces are controlled per test through
 * $GLOBALS['caps'] and the "nonce-{action}" convention.
 *
 * Kept separate from tests/learn-practice/stub.php on purpose: that stub's
 * current_user_can() always returns true, so permission tests are impossible.
 */
define('ABSPATH', '/');
define('OBJECT', 'OBJECT');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('KB_IN_BYTES', 1024);
define('MB_IN_BYTES', 1048576);
$GLOBALS['pass'] = 0; $GLOBALS['fail'] = 0;
// Any PHP warning, notice or deprecation is a test failure (they would show
// in production logs and often hide a real bug).
error_reporting(E_ALL);
set_error_handler(static function ($no, $str, $file, $line) {
    $GLOBALS['fail']++;
    echo 'FAIL php error: ' . $str . ' at ' . basename($file) . ':' . $line . "\n";
    return true;
});


class WP_Post { public $ID = 0, $post_type = 'post', $post_status = 'publish', $post_date_gmt = '0000-00-00 00:00:00', $post_name = '', $post_title = '', $post_parent = 0, $post_excerpt = '', $post_content = '', $post_password = '', $menu_order = 0, $post_author = 1; function __construct($a = []) { foreach ($a as $k => $v) { $this->$k = $v; } } }
class WP_Term { public $term_id = 0, $term_taxonomy_id = 0, $name = '', $slug = '', $taxonomy = '', $parent = 0, $count = 0, $description = ''; function __construct($a = []) { foreach ($a as $k => $v) { $this->$k = $v; } } }
class WP_Error { public $code, $message, $data; function __construct($code = '', $message = '', $data = null) { $this->code = $code; $this->message = $message; $this->data = $data; } function get_error_message() { return $this->message; } function get_error_code() { return $this->code; } }
class WP_Query { public $posts = []; public $found_posts = 0; public $query_vars = []; public $tax_query = null; public $max_num_pages = 0; function is_main_query() { return false; } function __construct($args = []) { $this->query_vars = $args; $GLOBALS['last_query'] = $args; $this->posts = ih_query_posts($args); $this->found_posts = count($this->posts); } }

function ih_reset() {
    $GLOBALS['hooks'] = [];
    $GLOBALS['registered'] = ['post_types' => [], 'taxonomies' => [], 'post_meta' => [], 'term_meta' => [], 'rewrite' => []];
    $GLOBALS['posts'] = []; $GLOBALS['meta'] = []; $GLOBALS['terms'] = []; $GLOBALS['post_terms'] = []; $GLOBALS['term_meta'] = [];
    $GLOBALS['options'] = []; $GLOBALS['transients'] = []; $GLOBALS['flushed'] = 0; $GLOBALS['caps'] = []; $GLOBALS['ctx'] = [];
    $GLOBALS['next_term_id'] = 100; $_POST = []; $_GET = [];
}
ih_reset();

// ------------------------------------------------------------------- hooks
/** Mirror of WordPress's $wp_filter (WP_Hook->callbacks), read by omochix_core_industry_hub_unhook(). */
if (!class_exists('WP_Hook')) { class WP_Hook { public $callbacks = []; } }
function add_filter($hook, $cb, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook][$priority][] = [$cb, $args]; $GLOBALS['wp_filter'][$hook] ??= new WP_Hook(); $GLOBALS['wp_filter'][$hook]->callbacks[$priority][] = ['function' => $cb, 'accepted_args' => $args]; return true; }
function add_action($hook, $cb, $priority = 10, $args = 1) { return add_filter($hook, $cb, $priority, $args); }
function remove_filter($hook, $cb, $priority = 10) { foreach ($GLOBALS['hooks'][$hook][$priority] ?? [] as $i => $h) { if ($h[0] === $cb) { unset($GLOBALS['hooks'][$hook][$priority][$i]); } } foreach (isset($GLOBALS['wp_filter'][$hook]) ? $GLOBALS['wp_filter'][$hook]->callbacks[$priority] ?? [] : [] as $i => $h) { if ($h['function'] === $cb) { unset($GLOBALS['wp_filter'][$hook]->callbacks[$priority][$i]); } } return true; }
function has_filter($hook, $cb = false) { if (false === $cb) { return !empty($GLOBALS['hooks'][$hook]); } foreach ($GLOBALS['hooks'][$hook] ?? [] as $p => $list) { foreach ($list as $h) { if ($h[0] === $cb) { return $p; } } } return false; }
function has_action($hook, $cb = false) { return has_filter($hook, $cb); }
function apply_filters($hook, $value, ...$args) { $list = $GLOBALS['hooks'][$hook] ?? []; ksort($list); foreach ($list as $hs) { foreach ($hs as $h) { $value = call_user_func_array($h[0], array_slice(array_merge([$value], $args), 0, $h[1])); } } return $value; }
function do_action($hook, ...$args) { $GLOBALS['action_stack'][] = $hook; $list = $GLOBALS['hooks'][$hook] ?? []; ksort($list); foreach ($list as $p => $hs) { foreach ($hs as $h) { if (!in_array($h, $GLOBALS['hooks'][$hook][$p] ?? [], true)) { continue; } /* removed meanwhile (WP_Hook does the same) */ call_user_func_array($h[0], array_slice($args, 0, $h[1])); } } array_pop($GLOBALS['action_stack']); }
function remove_action($hook, $cb, $priority = 10) { return remove_filter($hook, $cb, $priority); }
function doing_action($hook = null) { $st = $GLOBALS['action_stack'] ?? []; return null === $hook ? (bool) $st : in_array($hook, $st, true); }
function did_action($hook) { return 0; }
function is_user_logged_in() { return !empty($GLOBALS['ctx']['logged_in']) || !empty($GLOBALS['caps']); }
function register_activation_hook($f, $cb) {} function register_deactivation_hook($f, $cb) {}

// ---------------------------------------------------------------- strings
function __($t, $d = '') { return $t; } function _e($t, $d = '') { echo $t; } function _x($t, $c, $d = '') { return $t; } function _n($s, $p, $n, $d = '') { return 1 === (int) $n ? $s : $p; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_url($t) { $t = (string) $t; return preg_match('#^(https?:)?//#i', $t) || '' === $t || '/' === ($t[0] ?? '') ? htmlspecialchars($t, ENT_QUOTES, 'UTF-8') : ''; }
function esc_html__($t, $d = '') { return esc_html($t); } function esc_html_e($t, $d = '') { echo esc_html($t); } function esc_attr__($t, $d = '') { return esc_attr($t); } function esc_attr_e($t, $d = '') { echo esc_attr($t); }
function esc_url_raw($u, $protocols = null) { $u = trim((string) $u); if ('' === $u || preg_match('/[\s<>"]/', $u)) { return ''; } $scheme = strtolower((string) parse_url($u, PHP_URL_SCHEME)); $allowed = $protocols ?: ['http', 'https']; return in_array($scheme, $allowed, true) ? $u : ''; }
function wp_unslash($v) { return is_array($v) ? array_map('wp_unslash', $v) : (is_string($v) ? stripslashes($v) : $v); }
function wp_slash($v) { return is_array($v) ? array_map('wp_slash', $v) : (is_string($v) ? addslashes($v) : $v); }
function sanitize_title($t) { $t = strtolower(trim(strip_tags((string) $t))); $t = preg_replace('/[^a-z0-9_-]+/', '-', $t); return trim($t, '-'); }
function sanitize_key($t) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $t)); }
function sanitize_text_field($t) { $t = strip_tags((string) $t); $t = preg_replace('/[\r\n\t ]+/', ' ', $t); return trim($t); }
function sanitize_textarea_field($t) { return trim(strip_tags((string) $t)); }
function wp_strip_all_tags($t) { return trim(strip_tags((string) $t)); }
function wp_kses_post($s) { return preg_replace('#<(script|style|iframe)[^>]*>.*?</\1>#is', '', (string) $s); }
function wp_check_invalid_utf8($s) { return mb_check_encoding((string) $s, 'UTF-8') ? (string) $s : ''; }
function wp_parse_url($u, $c = -1) { return parse_url((string) $u, $c); }
function absint($v) { return abs((int) $v); }
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function wp_list_pluck($list, $field) { return array_map(static fn($o) => is_object($o) ? $o->$field : $o[$field], (array) $list); }
function checked($a, $b = true, $echo = true) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ($echo) { echo $r; } return $r; }
function selected($a, $b = true, $echo = true) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ($echo) { echo $r; } return $r; }
function disabled($a, $b = true, $echo = true) { $r = (string) $a === (string) $b ? ' disabled="disabled"' : ''; if ($echo) { echo $r; } return $r; }
function wp_nonce_field($action, $name, $referer = true, $echo = true) { $f = '<input type="hidden" name="' . esc_attr($name) . '" value="nonce-' . esc_attr($action) . '">'; if ($echo) { echo $f; } return $f; }
function wp_verify_nonce($n, $a) { return 'nonce-' . $a === $n ? 1 : false; }
function current_time($t) { return '2026-11-05 00:00:00'; }
function wp_date($f, $ts = null) { return gmdate($f, $ts ?? time()); }
function plugin_dir_path($f) { return rtrim(dirname($f), '/') . '/'; }
function plugin_dir_url($f) { return 'https://example.test/wp-content/plugins/omochix-core/'; }
function admin_url($p = '') { return 'https://example.test/wp-admin/' . ltrim($p, '/'); }
function add_query_arg($args, $url) { [$base, $frag] = array_pad(explode('#', (string) $url, 2), 2, null); $sep = false === strpos($base, '?') ? '?' : '&'; $pairs = []; foreach ((array) $args as $k => $v) { $pairs[] = $k . '=' . $v; } return $base . ($pairs ? $sep . implode('&', $pairs) : '') . (null !== $frag ? '#' . $frag : ''); }
function home_url($p = '') { return 'https://example.test' . $p; }
function get_current_user_id() { return 1; }
function get_userdata($id) { return null; }
function is_admin() { return $GLOBALS['ctx']['is_admin'] ?? false; }
function is_tax($t = '') { $c = $GLOBALS['ctx']['is_tax'] ?? ''; return '' !== $c && ('' === $t || in_array($c, (array) $t, true)); }
function is_singular($t = '') { return $GLOBALS['ctx']['is_singular'] ?? false; }
function get_queried_object() { return $GLOBALS['ctx']['queried'] ?? null; }
function get_queried_object_id() { $q = get_queried_object(); return $q instanceof WP_Post ? $q->ID : ($q instanceof WP_Term ? $q->term_id : 0); }
function wp_attachment_is_image($id) { return isset($GLOBALS['posts'][$id]) && 'attachment' === $GLOBALS['posts'][$id]->post_type; }
function wp_is_post_autosave($id) { return false; } function wp_is_post_revision($id) { return false; }
function wp_save_post_revision($id) { return 0; }
function wp_enqueue_style() {} function wp_enqueue_script() {} function wp_localize_script() {} function wp_add_inline_script() {} function wp_register_script() {} function wp_register_style() {}
function add_meta_box(...$a) { $GLOBALS['meta_boxes'][] = $a; } function remove_meta_box(...$a) {}
function get_current_screen() { return $GLOBALS['ctx']['screen'] ?? null; }
function wp_die($m = '') { throw new RuntimeException('wp_die: ' . (is_string($m) ? $m : '')); }
function status_header($c) { $GLOBALS['status_header'] = $c; }
function nocache_headers() {}

// ------------------------------------------------------------ capabilities
function current_user_can($cap, ...$args) { return !empty($GLOBALS['caps'][$cap]) || !empty($GLOBALS['caps']['*']); }
function ih_as_admin() { $GLOBALS['caps'] = ['*' => true]; }
function ih_as($caps) { $GLOBALS['caps'] = array_fill_keys((array) $caps, true); }

// ---------------------------------------------------------------- options
function get_option($k, $d = false) { return $GLOBALS['options'][$k] ?? $d; }
function update_option($k, $v, $autoload = null) { $GLOBALS['options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['options'][$k]); return true; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['transients'][$k] = $v; return true; }
function get_transient($k) { return $GLOBALS['transients'][$k] ?? false; }
function delete_transient($k) { unset($GLOBALS['transients'][$k]); return true; }
function flush_rewrite_rules($hard = true) { $GLOBALS['flushed']++; }
function add_rewrite_rule($re, $q, $after = 'bottom') { $GLOBALS['registered']['rewrite'][] = [$re, $q, $after]; }

// ---------------------------------------------------------- registration
function register_post_type($name, $args) { $GLOBALS['registered']['post_types'][$name] = $args; return (object) $args; }
function register_taxonomy($name, $types, $args) { $GLOBALS['registered']['taxonomies'][$name] = ['types' => array_values((array) $types), 'args' => $args]; $GLOBALS['terms'][$name] = $GLOBALS['terms'][$name] ?? []; return true; }
function register_taxonomy_for_object_type($tax, $type) { $GLOBALS['registered']['taxonomies'][$tax]['types'][] = $type; return true; }
function register_post_meta($type, $key, $args) { $GLOBALS['registered']['post_meta'][$type][$key] = $args; return true; }
function register_meta($type, $key, $args) { $GLOBALS['registered']['post_meta'][$args['object_subtype'] ?? '*'][$key] = $args; return true; }
function register_term_meta($tax, $key, $args) { $GLOBALS['registered']['term_meta'][$tax][$key] = $args; return true; }
function post_type_exists($t) { return isset($GLOBALS['registered']['post_types'][$t]) || in_array($t, ['post', 'page', 'attachment'], true); }
function taxonomy_exists($t) { return isset($GLOBALS['registered']['taxonomies'][$t]); }
function get_taxonomy($t) { if (!taxonomy_exists($t)) { return false; } $a = $GLOBALS['registered']['taxonomies'][$t]['args']; $o = (object) $a; $o->name = $t; $o->cap = (object) array_merge(['manage_terms' => 'manage_categories', 'edit_terms' => 'manage_categories', 'delete_terms' => 'manage_categories', 'assign_terms' => 'edit_posts'], $a['capabilities'] ?? []); /* core defaults */ return $o; }
function get_object_taxonomies($type) { $out = []; foreach ($GLOBALS['registered']['taxonomies'] as $n => $t) { if (in_array(is_object($type) ? $type->post_type : $type, $t['types'], true)) { $out[] = $n; } } return $out; }
function is_taxonomy_hierarchical($t) { return !empty($GLOBALS['registered']['taxonomies'][$t]['args']['hierarchical']); }

// ------------------------------------------------------------------ terms
function ih_term($tax, $slug) { foreach ($GLOBALS['terms'][$tax] ?? [] as $t) { if ($t->slug === $slug) { return $t; } } return null; }
function term_exists($term, $tax = '', $parent = null) { foreach ($GLOBALS['terms'][$tax] ?? [] as $t) { if ($t->slug === $term || $t->name === $term || (is_int($term) && $t->term_id === $term)) { return ['term_id' => $t->term_id, 'term_taxonomy_id' => $t->term_id]; } } return null; }
function wp_insert_term($name, $tax, $args = []) {
    $name = apply_filters('pre_insert_term', $name, $tax, $args);
    if ($name instanceof WP_Error) { return $name; }
    $slug = sanitize_title($args['slug'] ?? $name);
    if (ih_term($tax, $slug)) { return new WP_Error('term_exists', 'exists'); }
    $id = ++$GLOBALS['next_term_id'];
    $GLOBALS['terms'][$tax][$id] = new WP_Term(['term_id' => $id, 'term_taxonomy_id' => $id, 'name' => $name, 'slug' => $slug, 'taxonomy' => $tax, 'parent' => (int) ($args['parent'] ?? 0)]);
    return ['term_id' => $id, 'term_taxonomy_id' => $id];
}
function get_term($id, $tax = '') { if ($id instanceof WP_Term) { return $id; } foreach ($tax ? [$tax] : array_keys($GLOBALS['terms']) as $t) { if (isset($GLOBALS['terms'][$t][(int) $id])) { return $GLOBALS['terms'][$t][(int) $id]; } } return null; }
function get_term_by($field, $value, $tax) { foreach ($GLOBALS['terms'][$tax] ?? [] as $t) { if (('slug' === $field && $t->slug === $value) || ('id' === $field && $t->term_id === (int) $value) || ('name' === $field && $t->name === $value)) { return $t; } } return false; }
function get_terms($args = []) {
    $out = [];
    foreach ($GLOBALS['terms'][$args['taxonomy']] ?? [] as $t) {
        if (isset($args['parent']) && (int) $t->parent !== (int) $args['parent']) { continue; }
        if (!empty($args['include']) && !in_array($t->term_id, array_map('intval', (array) $args['include']), true)) { continue; }
        if (!empty($args['slug']) && !in_array($t->slug, (array) $args['slug'], true)) { continue; }
        $out[] = $t;
    }
    if (!empty($args['number'])) { $out = array_slice($out, 0, (int) $args['number']); }
    if (($args['fields'] ?? '') === 'ids') { return wp_list_pluck($out, 'term_id'); }
    return $out;
}
function get_term_children($id, $tax) { $out = []; foreach ($GLOBALS['terms'][$tax] ?? [] as $t) { if ((int) $t->parent === (int) $id) { $out[] = $t->term_id; $out = array_merge($out, get_term_children($t->term_id, $tax)); } } return $out; }
function get_the_terms($post_id, $tax) { $p = $post_id instanceof WP_Post ? $post_id->ID : (int) $post_id; $ids = $GLOBALS['post_terms'][$p][$tax] ?? []; if (!$ids) { return false; } return array_values(array_filter(array_map(static fn($i) => $GLOBALS['terms'][$tax][$i] ?? null, $ids))); }
function wp_get_object_terms($ids, $tax, $args = []) { $t = get_the_terms((int) (is_array($ids) ? $ids[0] : $ids), $tax); $t = $t ?: []; return ($args['fields'] ?? '') === 'slugs' ? wp_list_pluck($t, 'slug') : (($args['fields'] ?? '') === 'ids' ? wp_list_pluck($t, 'term_id') : $t); }
function has_term($term, $tax, $post = null) { $p = $post instanceof WP_Post ? $post->ID : (int) $post; foreach (get_the_terms($p, $tax) ?: [] as $t) { if ($t->slug === $term || $t->term_id === $term || $t->name === $term) { return true; } } return false; }
function wp_set_object_terms($post_id, $terms, $tax, $append = false) {
    $old = $GLOBALS['post_terms'][$post_id][$tax] ?? [];
    $ids = [];
    foreach ((array) $terms as $t) {
        if (is_int($t) || ctype_digit((string) $t)) { if (isset($GLOBALS['terms'][$tax][(int) $t])) { $ids[] = (int) $t; } continue; }
        $found = ih_term($tax, sanitize_title($t)) ?: get_term_by('name', $t, $tax);
        if ($found) { $ids[] = $found->term_id; }
    }
    $ids = array_values(array_unique($append ? array_merge($old, $ids) : $ids));
    $GLOBALS['post_terms'][$post_id][$tax] = $ids;
    do_action('set_object_terms', $post_id, $terms, $ids, $tax, $append, $old);
    return $ids;
}
function wp_set_post_terms($post_id, $terms, $tax, $append = false) { return wp_set_object_terms($post_id, $terms, $tax, $append); }
function get_term_meta($id, $key = '', $single = false) { return '' === $key ? ($GLOBALS['term_meta'][$id] ?? []) : ($GLOBALS['term_meta'][$id][$key] ?? ''); }
function update_term_meta($id, $key, $v) { $GLOBALS['term_meta'][$id][$key] = $v; do_action('updated_term_meta', 0, $id, $key, $v); return true; }
function delete_term_meta($id, $key) { unset($GLOBALS['term_meta'][$id][$key]); do_action('deleted_term_meta', [0], $id, $key, ''); return true; }
function get_term_link($t, $tax = '') { return 'https://example.test/learn/industries/' . (is_object($t) ? $t->slug : $t) . '/'; }

// ------------------------------------------------------------------ posts
function ih_add_post($a) { $id = $a['ID'] ?? (max(array_keys($GLOBALS['posts'] ?: [0])) + 1); $a['ID'] = $id; $GLOBALS['posts'][$id] = new WP_Post($a); return $id; }
function get_post($p = null) { if ($p instanceof WP_Post) { return $p; } return $GLOBALS['posts'][(int) $p] ?? null; }
/** Scheduled publishing as WordPress does it (wp-includes/post.php): status written directly, no wp_insert_post_data. */
function wp_update_post($a) { $id = (int) ($a['ID'] ?? 0); $p = $GLOBALS['posts'][$id] ?? null; if (!$p) { return 0; } $old = $p->post_status; foreach ($a as $k => $v) { if ('ID' !== $k) { $p->$k = $v; } } if ($old !== $p->post_status) { do_action('transition_post_status', $p->post_status, $old, $p); } return $id; }
function wp_schedule_single_event($t, $hook, $args = []) { $GLOBALS['scheduled'][] = [$t, $hook, $args]; return true; }
function check_and_publish_future_post($post) { $p = get_post($post); if (!$p || 'future' !== $p->post_status) { return; } if (strtotime($p->post_date_gmt . ' GMT') > time()) { wp_schedule_single_event(strtotime($p->post_date_gmt . ' GMT'), 'publish_future_post', [$p->ID]); return; } $p->post_status = 'publish'; $GLOBALS['cron_published'][] = (int) $p->ID; do_action('transition_post_status', 'publish', 'future', $p); }
add_action('publish_future_post', 'check_and_publish_future_post', 10, 1); // default-filters.php
function get_post_type($p = null) { $p = get_post($p); return $p ? $p->post_type : false; }
function get_post_status($p = null) { $p = get_post($p); return $p ? $p->post_status : false; }
function get_post_field($f, $p) { $p = get_post($p); return $p ? ($p->$f ?? '') : ''; }
function get_the_title($p) { $p = get_post($p); return $p ? $p->post_title : ''; }
function get_permalink($p) { $p = get_post($p); return $p ? 'https://example.test/' . $p->post_type . '/' . $p->post_name . '/' : false; }
function get_post_meta($id, $key = '', $single = false) { return '' === $key ? ($GLOBALS['meta'][$id] ?? []) : ($GLOBALS['meta'][$id][$key] ?? ''); }
function update_post_meta($id, $key, $v) { $GLOBALS['meta'][$id][$key] = $v; return true; }
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$id][$key]); return true; }
function ih_query_posts($args) {
    $GLOBALS['last_query'] = $args;
    $GLOBALS['query_count'] = ($GLOBALS['query_count'] ?? 0) + 1;
    $types = isset($args['post_type']) ? (array) $args['post_type'] : ['post'];
    $status = isset($args['post_status']) ? (array) $args['post_status'] : ['publish'];
    $out = [];
    foreach ($GLOBALS['posts'] as $p) {
        if (!in_array('any', $types, true) && !in_array($p->post_type, $types, true)) { continue; }
        if (!in_array('any', $status, true) && !in_array($p->post_status, $status, true)) { continue; }
        if (!empty($args['post__in']) && !in_array($p->ID, array_map('intval', $args['post__in']), true)) { continue; }
        if (!empty($args['post__not_in']) && in_array($p->ID, array_map('intval', $args['post__not_in']), true)) { continue; }
        if (isset($args['has_password']) && false === $args['has_password'] && '' !== $p->post_password) { continue; }
        $ok = true;
        foreach ($args['tax_query'] ?? [] as $k => $c) {
            if ('relation' === $k) { continue; }
            $have = get_the_terms($p->ID, $c['taxonomy']) ?: [];
            $vals = ($c['field'] ?? 'term_id') === 'slug' ? wp_list_pluck($have, 'slug') : wp_list_pluck($have, 'term_id');
            if (($c['include_children'] ?? true)) { foreach ($have as $h) { if ($h->parent && ($par = get_term($h->parent, $c['taxonomy']))) { $vals[] = ($c['field'] ?? 'term_id') === 'slug' ? $par->slug : $par->term_id; } } }
            $hit = (bool) array_intersect(array_map('strval', (array) $c['terms']), array_map('strval', $vals));
            if (($c['operator'] ?? 'IN') === 'NOT IN' ? $hit : !$hit) { $ok = false; }
        }
        if ($ok) { $out[] = $p; }
    }
    if (!empty($args['post__in']) && ($args['orderby'] ?? '') === 'post__in') { $order = array_flip(array_map('intval', $args['post__in'])); usort($out, static fn($a, $b) => $order[$a->ID] <=> $order[$b->ID]); }
    $n = (int) ($args['posts_per_page'] ?? 10);
    if ($n > 0) { $out = array_slice($out, max(0, (int) ($args['paged'] ?? 1) - 1) * $n, $n); }
    return ($args['fields'] ?? '') === 'ids' ? wp_list_pluck($out, 'ID') : $out;
}
function _prime_post_caches($ids, $terms = true, $meta = true) { $GLOBALS['primed'][] = array_values((array) $ids); }
function is_feed($t = '') { return !empty($GLOBALS['ctx']['is_feed']); }
function is_embed() { return !empty($GLOBALS['ctx']['is_embed']); }
function is_search() { return !empty($GLOBALS['ctx']['is_search']); }
function is_404() { return !empty($GLOBALS['ctx']['is_404']); }
function get_query_var($k, $d = '') { return $GLOBALS['query_vars'][$k] ?? $d; }
function add_option($k, $v = '', $d = '', $a = null) { if (!isset($GLOBALS['options'][$k])) { $GLOBALS['options'][$k] = $v; } return true; }
function get_posts($args = []) { $args['posts_per_page'] = $args['posts_per_page'] ?? ($args['numberposts'] ?? 5); return ih_query_posts($args); }
function wp_count_posts($t) { return (object) ['publish' => 0]; }

// ---------------------------------------------------------------- loading
$PLUGIN = getenv('IH_PLUGIN_DIR') ?: realpath(__DIR__ . '/../../wp-plugin/omochix-core'); // IH_PLUGIN_DIR: a copy (partial-upload.php)
require $PLUGIN . '/omochix-core.php';

/** Run the plugin's init the way WordPress would (after resetting stores). */
function ih_boot() {
    $hooks = $GLOBALS['hooks'];
    ih_reset();
    $GLOBALS['hooks'] = $hooks;
    do_action('init');
}
$GLOBALS['ih_load_hooks'] = $GLOBALS['hooks'];
/** Reset everything except the hooks added while the plugin files loaded, then boot. */
function ih_fresh() { ih_reset(); $GLOBALS['hooks'] = $GLOBALS['ih_load_hooks']; do_action('init'); }

function check($name, $cond) { if ($cond) { $GLOBALS['pass']++; echo "PASS $name\n"; } else { $GLOBALS['fail']++; echo "FAIL $name\n"; } }
function ih_done() { echo "\n{$GLOBALS['pass']} passed, {$GLOBALS['fail']} failed\n"; exit($GLOBALS['fail'] ? 1 : 0); }

/** Hook name => sorted list of "priority:callback" for named callbacks (closures are counted). */
function ih_hook_names() {
    $out = [];
    foreach ($GLOBALS['hooks'] as $hook => $by_priority) {
        foreach ($by_priority as $priority => $list) {
            foreach ($list as $h) { $out[$hook][] = $priority . ':' . (is_string($h[0]) ? $h[0] : '{closure}'); }
        }
        sort($out[$hook]);
    }
    ksort($out);
    return $out;
}

/** Registrations after a fresh boot, closures normalised, keys sorted. */
function ih_registration_snapshot() {
    ih_fresh();
    $norm = static function ($v) use (&$norm) { if ($v instanceof Closure) { return '{closure}'; } if (is_array($v)) { return array_map($norm, $v); } return $v; };
    $r = $GLOBALS['registered'];
    ksort($r['post_types']); ksort($r['taxonomies']); ksort($r['post_meta']);
    foreach ($r['post_meta'] as &$m) { ksort($m); }
    unset($m);
    return ['post_types' => $norm($r['post_types']), 'taxonomies' => $norm($r['taxonomies']), 'post_meta' => $norm($r['post_meta']), 'rewrite' => $r['rewrite'], 'hooks' => ih_hook_names()];
}


// ------------------------------------------------------ content fixtures
/** Fill a top-level industry with content that meets G1..G7 (G5 needs a workflow too). */
function ih_fill_hub($term_id, $ready = true) {
    $body = str_repeat('業務の流れを具体的に説明する本文。', 6);
    update_term_meta($term_id, 'industry_hub_ready', $ready);
    update_term_meta($term_id, 'industry_lead', str_repeat('この業種のAI活用を独自に解説する導入文です。', 6));
    update_term_meta($term_id, 'industry_benefits', [['title' => '問い合わせ対応が速くなる', 'body' => $body]]);
    update_term_meta($term_id, 'industry_challenges', [['title' => '問い合わせ', 'body' => $body, 'purpose' => 'customer-support'], ['title' => '広告文', 'body' => $body, 'purpose' => 'marketing'], ['title' => '書類', 'body' => $body, 'purpose' => 'document']]);
    update_term_meta($term_id, 'industry_usecases', [['title' => 'a', 'body' => $body, 'purpose' => 'marketing'], ['title' => 'b', 'body' => $body, 'purpose' => 'document'], ['title' => 'c', 'body' => $body, 'purpose' => 'customer-support']]);
    update_term_meta($term_id, 'industry_verified_at', gmdate('Y-m-d', time() - 10 * DAY_IN_SECONDS));
    update_term_meta($term_id, 'industry_sources', [['label' => '統計', 'url' => 'https://www.e-stat.go.jp/']]);
}
/** A published, complete, official workflow in an industry (slug), with a tool. */
function ih_add_workflow($industry_slug, $purpose = 'marketing', $provider = 'official') {
    if (!ih_term('learn_format', 'workflow')) { $c = $GLOBALS['caps']; $GLOBALS['caps'] = ['manage_categories' => true]; wp_insert_term('導入する', 'learn_format', ['slug' => 'workflow']); $GLOBALS['caps'] = $c; }
    $tool = ih_add_post(['post_type' => 'ai_tool', 'post_name' => 'tool-' . count($GLOBALS['posts']), 'post_status' => 'publish']);
    $id = ih_add_post(['post_type' => 'learn_practice', 'post_name' => 'wf-' . count($GLOBALS['posts']), 'post_title' => 'WF ' . $industry_slug, 'post_status' => 'publish']);
    foreach (['workflow_problem' => '課題', 'workflow_audience' => [['role' => '担当者', 'note' => '']], 'workflow_cost' => [['item' => 'x', 'amount' => '0円', 'period' => 'once', 'note' => '', 'checked_at' => '2026-11-01']], 'workflow_metrics' => [['metric' => 'm', 'how' => 'h', 'frequency' => '']], 'related_tool_ids' => [$tool]] as $k => $v) { update_post_meta($id, $k, $v); }
    $c = $GLOBALS['caps']; $GLOBALS['caps'] = ['*' => true];
    wp_set_object_terms($id, [$industry_slug], 'omx_industry');
    wp_set_object_terms($id, ['workflow'], 'learn_format');
    wp_set_object_terms($id, [$purpose], 'omx_purpose');
    wp_set_object_terms($id, [$provider], 'omx_provider_type');
    $GLOBALS['caps'] = $c;
    return $id;
}
