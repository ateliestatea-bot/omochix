<?php
/**
 * Industry Hub — REST permissions by user, on a THROWAWAY WordPress (in-process rest_do_request()).
 *
 *   IH_WP_ROOT=/path/to/throwaway/wp php tests/industry-hub/rest-roles.php
 *
 * Users: logged out, a user with no role ("disabled"), a deleted user id, subscriber, contributor,
 * author, editor, administrator. It creates temporary users and terms and removes them at the end.
 * Never point it at staging or production.
 */
$root = rtrim((string) getenv('IH_WP_ROOT'), '/');
if (!is_file($root . '/wp-load.php') || !is_file($root . '/wp-content/database/it.sqlite')) {
    fwrite(STDERR, "IH_WP_ROOT must be the throwaway SQLite WordPress\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$pass = 0; $fail = 0;
function check($name, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }
function req($method, $route, $params = []) {
    $r = new WP_REST_Request($method, $route);
    foreach ($params as $k => $v) { $r->set_param($k, $v); }
    $res = rest_do_request($r);
    return [$res->get_status(), $res->get_data()];
}

$made_users = []; $made_terms = [];
$users = ['logged out' => 0];
foreach (['none' => '', 'subscriber' => 'subscriber', 'contributor' => 'contributor', 'author' => 'author', 'editor' => 'editor', 'administrator' => 'administrator'] as $label => $role) {
    $id = wp_insert_user(['user_login' => 'ihrr_' . $label . '_' . wp_rand(1000, 9999), 'user_pass' => wp_generate_password(24), 'user_email' => 'ihrr_' . $label . wp_rand(1000, 9999) . '@example.invalid', 'role' => $role ?: 'subscriber']);
    if ('' === $role) { (new WP_User($id))->set_role(''); }
    $made_users[] = $id;
    $users['no role (disabled)' === $label ? $label : ('none' === $label ? 'no role (disabled)' : $label)] = $id;
}
$ghost = (int) $GLOBALS['wpdb']->get_var("SELECT MAX(ID) FROM {$GLOBALS['wpdb']->users}") + 1000;
$users['deleted user id'] = $ghost;

$can_manage = ['editor', 'administrator'];
$industry = get_terms(['taxonomy' => 'omx_industry', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids'])[0] ?? 0;
check('setup: an industry term exists', (bool) $industry);

foreach ($users as $label => $uid) {
    wp_set_current_user($uid);
    $manager = in_array($label, $can_manage, true);
    // Create terms.
    [$st, $d] = req('POST', '/wp/v2/industries', ['name' => 'ihrr industry ' . $label]);
    if (201 === $st && !empty($d['id'])) { $made_terms[] = [(int) $d['id'], 'omx_industry']; }
    check("$label: create industry " . ($manager ? 'allowed' : 'refused'), $manager ? 201 === $st : in_array($st, [401, 403], true), "status $st");
    [$st, $d] = req('POST', '/wp/v2/business-purposes', ['name' => 'ihrr purpose ' . $label]);
    if (201 === $st && !empty($d['id'])) { $made_terms[] = [(int) $d['id'], 'omx_purpose']; }
    check("$label: create business purpose " . ($manager ? 'allowed' : 'refused'), $manager ? 201 === $st : in_array($st, [401, 403], true), "status $st");
    [$st] = req('POST', '/wp/v2/provider-types', ['name' => 'ihrr provider ' . $label]);
    check("$label: create provider type refused (fixed vocabulary)", in_array($st, [401, 403], true), "status $st");
    // Edit and delete an existing industry.
    [$st] = req('POST', "/wp/v2/industries/$industry", ['description' => 'ihrr edited']);
    check("$label: edit industry " . ($manager ? 'allowed' : 'refused'), $manager ? 200 === $st : in_array($st, [401, 403], true), "status $st");
    if ($manager) { wp_update_term($industry, 'omx_industry', ['description' => '']); }
    [$st] = req('DELETE', "/wp/v2/industries/999999999", ['force' => true]);
    check("$label: delete industry " . ($manager ? 'reaches the term lookup' : 'refused'), $manager ? 404 === $st : in_array($st, [401, 403, 404], true), "status $st");
    // Edit context (exposes private fields).
    [$st] = req('GET', '/wp/v2/industries', ['context' => 'edit']);
    check("$label: industries in edit context " . ($manager ? 'allowed' : 'refused'), $manager ? 200 === $st : in_array($st, [401, 403], true), "status $st");
    // Private post type and meta stay out of REST.
    [$st] = req('GET', '/wp/v2/omx_resource');
    check("$label: resource post type not in REST", 404 === $st, "status $st");
    // Term meta (gate, contacts) never in the REST term payload.
    [$st, $d] = req('GET', '/wp/v2/industries', ['per_page' => 100, 'hide_empty' => false]);
    $leak = false;
    foreach ((array) $d as $t) { if (is_array($t) && !empty($t['meta'])) { foreach ((array) $t['meta'] as $k => $v) { if (0 === strpos($k, 'industry_') || 0 === strpos($k, 'omochix')) { $leak = true; } } } }
    check("$label: no industry term meta in REST", !$leak);
}
wp_set_current_user(0);
// Anonymous: user objects of the temporary users are not listed (they have no published posts).
[$st, $d] = req('GET', '/wp/v2/users', ['per_page' => 100]);
$listed = array_filter((array) $d, static fn($u) => is_array($u) && 0 === strpos((string) ($u['slug'] ?? ''), 'ihrr'));
check('logged out: temporary users are not exposed by /wp/v2/users', [] === $listed);

// Clean up.
wp_set_current_user(1);
foreach ($made_terms as [$tid, $tax]) { wp_delete_term($tid, $tax); }
foreach ($made_users as $id) { wp_delete_user($id); }
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
