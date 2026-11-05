<?php
/**
 * Industry Hub Phase 1 — integration checks against a REAL WordPress.
 *
 * Not run in CI. Run it only on a throwaway local WordPress (SQLite or a
 * scratch database) that has this plugin and theme activated — never on
 * production or staging: it creates users, posts and terms.
 *
 *   php tests/industry-hub/wp-integration.php /path/to/throwaway/wordpress
 *
 * It exercises core's own code paths that the stub cannot: wp_insert_post /
 * edit_post tax_input handling, register_*_meta sanitize callbacks through
 * update_*_meta, the REST API (routes, permissions, the publish guards), the
 * main query for the private ledger, and the rewrite rules.
 */
$root = rtrim($argv[1] ?? '', '/');
if ('' === $root || !is_file($root . '/wp-load.php')) {
    fwrite(STDERR, "usage: php wp-integration.php /path/to/throwaway/wordpress\n");
    exit(2);
}
$_SERVER['HTTP_HOST'] = parse_url((string) (getenv('IH_HOME') ?: 'http://it.localhost'), PHP_URL_HOST); $_SERVER['REQUEST_URI'] = '/';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (str_contains((string) home_url(), 'omochix.com')) { fwrite(STDERR, "refusing to run against a production-looking home URL\n"); exit(2); }

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }
function ih_user($role) { $login = 'ih-' . $role . '-' . wp_generate_password(6, false); $id = wp_insert_user(['user_login' => $login, 'user_pass' => wp_generate_password(24), 'user_email' => $login . '@example.test', 'role' => $role]); return (int) $id; }
function ih_rest($method, $route, $params = []) { $r = new WP_REST_Request($method, $route); foreach ($params as $k => $v) { $r->set_param($k, $v); } return rest_do_request($r); }

check('setup: plugin and theme active', is_plugin_active('omochix-core/omochix-core.php') && 'omochix-theme' === get_option('stylesheet'));
$admin  = ih_user('administrator');
$author = ih_user('author');

// ---------------------------------------------------------------- surface
wp_set_current_user(0);
check('surface: omx_resource is not a public or queryable post type', !in_array('omx_resource', get_post_types(['public' => true]), true) && !in_array('omx_resource', get_post_types(['publicly_queryable' => true]), true));
check('surface: no Industry Hub taxonomy is public in Phase 1', !array_intersect(['omx_industry', 'omx_purpose', 'omx_provider_type'], get_taxonomies(['public' => true])));
$rules = (array) get_option('rewrite_rules');
check('surface: no rewrite rule mentions the new types (omx_motion is the existing gallery var)', !array_filter(array_merge(array_keys($rules), array_values($rules)), static fn($r) => (bool) preg_match('/omx_(industry|purpose|provider_type|resource)|learn\/industries/', $r)));
$routes = array_keys(rest_get_server()->get_routes());
check('surface: the ledger has no REST route', !array_filter($routes, static fn($r) => false !== strpos($r, 'omx_resource') || false !== strpos($r, 'resource')));
foreach (['/wp/v2/industries', '/wp/v2/business-purposes', '/wp/v2/provider-types'] as $route) {
    check("rest: anonymous GET $route is refused", 401 === ih_rest('GET', $route)->get_status());
}
wp_set_current_user($author);
check('rest: an author (block editor) can list provider types', 200 === ih_rest('GET', '/wp/v2/provider-types')->get_status() && 4 === count(ih_rest('GET', '/wp/v2/provider-types')->get_data()));
check('rest: nobody can create a provider term through REST', !in_array(ih_rest('POST', '/wp/v2/provider-types', ['name' => 'own', 'slug' => 'own'])->get_status(), [200, 201], true));
wp_set_current_user($admin);
check('rest: not even an administrator can create a fifth provider', !in_array(ih_rest('POST', '/wp/v2/provider-types', ['name' => 'own', 'slug' => 'own'])->get_status(), [200, 201], true) && !term_exists('own', 'omx_provider_type'));
check('rest: an administrator cannot delete a provider term', !in_array(ih_rest('DELETE', '/wp/v2/provider-types/' . get_term_by('slug', 'official', 'omx_provider_type')->term_id, ['force' => true])->get_status(), [200, 201], true) && term_exists('official', 'omx_provider_type'));
$child_slug = 'ih-child-' . strtolower(wp_generate_password(6, false));
$deep = wp_insert_term('子 ' . $child_slug, 'omx_industry', ['slug' => $child_slug, 'parent' => get_term_by('slug', 'real-estate', 'omx_industry')->term_id]);
$third = is_array($deep) ? wp_insert_term('孫', 'omx_industry', ['slug' => $child_slug . '-x', 'parent' => $deep['term_id']]) : null;
check('terms: core wp_insert_term refuses a third industry level', $third instanceof WP_Error);
check('terms: core wp_insert_term refuses a reserved industry slug', wp_insert_term('feed', 'omx_industry', ['slug' => 'feed']) instanceof WP_Error);

// ------------------------------------------------------- ledger privacy
$res = wp_insert_post(['post_type' => 'omx_resource', 'post_title' => 'IH 外部動画', 'post_status' => 'publish'], true);
update_post_meta($res, 'resource_permission_ref', 'LIC-IT-1');
wp_set_current_user(0);
$q = new WP_Query(['p' => $res]);
check('ledger: ?p=ID on the front end finds nothing for visitors', 0 === $q->post_count);
$q = new WP_Query(['s' => 'IH 外部動画', 'post_type' => 'any']);
check('ledger: site search with post_type=any never returns entries', !in_array($res, wp_list_pluck($q->posts, 'ID'), true));
check('ledger: the reference is protected meta in core', is_protected_meta('resource_permission_ref', 'post'));
check('ledger: entries are removed from core sitemaps', !array_key_exists('omx_resource', apply_filters('wp_sitemaps_post_types', get_post_types(['public' => true], 'objects'))));
wp_set_current_user($author);
update_post_meta($res, 'resource_url', 'javascript:alert(1)');
check('ledger: core update_post_meta runs the sanitizer (unsafe URL stored as "")', '' === get_post_meta($res, 'resource_url', true));
$draft = wp_insert_post(['post_type' => 'omx_resource', 'post_title' => 'IH 未記入', 'post_status' => 'publish'], true);
check('ledger: an empty entry inserted as publish is stored as draft (guard)', 'draft' === get_post_status($draft));

// ----------------------------------------------- provider, classic edit_post
wp_set_current_user($admin);
$pid = wp_insert_post(['post_type' => 'post', 'post_title' => 'IH provider', 'post_status' => 'draft'], true);
check('provider: a new post is unclassified (not official)', 'unclassified' === omochix_core_get_provider_type($pid));
$official = get_term_by('slug', 'official', 'omx_provider_type')->term_id;
$partner  = get_term_by('slug', 'partner', 'omx_provider_type')->term_id;
edit_post(['post_ID' => $pid, 'post_type' => 'post', 'post_title' => 'IH provider', 'tax_input' => ['omx_provider_type' => ['0', (string) $official]]]);
check('provider: the classic radio box value (0 + term ID) saves through core edit_post()', 'official' === omochix_core_get_provider_type($pid));
edit_post(['post_ID' => $pid, 'post_type' => 'post', 'post_title' => 'IH provider', 'tax_input' => ['omx_provider_type' => ['0']]]);
check('provider: choosing 未分類 (only 0) clears the term', 'unclassified' === omochix_core_get_provider_type($pid));
edit_post(['post_ID' => $pid, 'post_type' => 'post', 'post_title' => 'IH provider', 'tax_input' => ['omx_provider_type' => [(string) $official, (string) $partner]]]);
check('provider: two providers through edit_post() fall back to unclassified', 'unclassified' === omochix_core_get_provider_type($pid) && [] === wp_get_object_terms($pid, 'omx_provider_type', ['fields' => 'ids']));
$r = ih_rest('POST', '/wp/v2/posts/' . $pid, ['provider-types' => [$official, $partner]]);
check('provider: two providers through REST also fall back to unclassified', 200 === $r->get_status() && 'unclassified' === omochix_core_get_provider_type($pid));

wp_set_current_user($author);
$own = wp_insert_post(['post_type' => 'post', 'post_title' => 'IH author post', 'post_status' => 'draft', 'post_author' => $author], true);
$r = ih_rest('POST', '/wp/v2/posts/' . $own, ['provider-types' => [$official]]);
check('provider: an author cannot mark a post official through REST (stays unclassified)', 'unclassified' === omochix_core_get_provider_type($own));
$creator = get_term_by('slug', 'creator', 'omx_provider_type')->term_id;
ih_rest('POST', '/wp/v2/posts/' . $own, ['provider-types' => [$creator]]);
check('provider: an author can choose creator through REST', 'creator' === omochix_core_get_provider_type($own));
$r = ih_rest('POST', '/wp/v2/business-purposes', ['name' => 'IH勝手', 'slug' => 'ih-adhoc']);
check('purpose: an author cannot create a business purpose through REST', !in_array($r->get_status(), [200, 201], true) && !term_exists('ih-adhoc', 'omx_purpose'));
wp_set_current_user($admin);
$_REQUEST['_wpnonce'] = wp_create_nonce('bulk-posts');
$bulk_res = wp_insert_post(['post_type' => 'omx_resource', 'post_title' => 'IH bulk', 'post_status' => 'draft'], true);
foreach (['resource_url' => 'https://example.com/b', 'resource_kind' => 'article', 'resource_provider_name' => 'B', 'resource_summary' => 's', 'resource_license_basis' => 'link_only', 'resource_verified_at' => '2026-11-01'] as $k => $v) { update_post_meta($bulk_res, $k, $v); }
wp_set_object_terms($bulk_res, ['external-service'], 'omx_provider_type');
$done = bulk_edit_posts(['post_type' => 'omx_resource', 'post' => [$bulk_res], '_status' => 'publish', 'tax_input' => ['omx_provider_type' => []]]);
check('bulk edit: a complete ledger entry can be bulk-published (stored provider merged by core)', 'publish' === get_post_status($bulk_res));

// --------------------------------------------------------- term meta (core)
$re = get_term_by('slug', 'real-estate', 'omx_industry')->term_id;
update_term_meta($re, 'industry_lead', '<script>alert(1)</script>導入');
check('term meta: core update_term_meta runs the sanitizer', false === strpos((string) get_term_meta($re, 'industry_lead', true), '<'));
$tool = wp_insert_post(['post_type' => 'ai_tool', 'post_title' => 'IH tool', 'post_status' => 'publish'], true);
update_term_meta($re, 'industry_tool_ids', [$tool, $pid, 'x']);
check('term meta: curated tool IDs keep only ai_tool posts', [$tool] === array_map('intval', (array) get_term_meta($re, 'industry_tool_ids', true)));
wp_set_current_user($author);
check('term meta: an author cannot write industry meta through the meta API', !current_user_can('edit_term', $re));

// ------------------------------------------------- multi-industry association
wp_set_object_terms($tool, ['real-estate', 'restaurant', $child_slug], 'omx_industry');
wp_set_current_user(0);
check('relations: a tool under three industries is found from each (core tax_query)', in_array($tool, wp_list_pluck(omochix_core_get_industry_posts('real-estate', 'ai_tool'), 'ID'), true) && in_array($tool, wp_list_pluck(omochix_core_get_industry_posts('restaurant', 'ai_tool'), 'ID'), true) && in_array($tool, wp_list_pluck(omochix_core_get_industry_posts($child_slug, 'ai_tool'), 'ID'), true));

// --------------------------------------------- workflow guard through REST
// A real REST request defines REST_REQUEST (so the classic guards stand
// aside and the REST guards answer); rest_do_request() in CLI does not.
if (!defined('REST_REQUEST')) { define('REST_REQUEST', true); }
wp_set_current_user($admin);
omochix_core_insert_workflow_format_term(); // Phase 1 does not seed it; the test does
$wf_term = get_term_by('slug', 'workflow', 'learn_format')->term_id;
$practice_meta = ['practice_summary' => 'IH', 'practice_minutes' => 30, 'practice_outcome' => '成果物', 'practice_prompts' => [['title' => 'p', 'for' => '', 'body' => 'b']], 'practice_steps' => [['title' => 's', 'body' => 'b', 'prompt' => null, 'code' => null, 'check' => '']], 'practice_verify' => [['title' => 'v', 'how' => 'h', 'expect' => 'e']], 'practice_safety' => ['注意'], 'practice_verified_at' => '2026-11-01', 'practice_sources' => [['label' => 'src', 'url' => 'https://example.com/']]];
$r = ih_rest('POST', '/wp/v2/learn-practice', ['title' => 'IH plain', 'status' => 'publish', 'meta' => $practice_meta]);
check('workflow: a complete non-workflow practice item publishes as before (201)', 201 === $r->get_status());
$r = ih_rest('POST', '/wp/v2/learn-practice', ['title' => 'IH wf', 'status' => 'publish', 'learn-formats' => [$wf_term], 'meta' => $practice_meta]);
check('workflow: a workflow without its fields is refused by the workflow guard (400)', 400 === $r->get_status() && 'omochix_workflow_blocked' === ($r->get_data()['code'] ?? ''));
$wf_meta = $practice_meta + ['workflow_problem' => '課題', 'workflow_audience' => [['role' => '担当者', 'note' => '']], 'workflow_cost' => [['item' => 'x', 'amount' => '0円', 'period' => 'once', 'note' => '', 'checked_at' => '2026-11-01']], 'workflow_metrics' => [['metric' => 'm', 'how' => 'h', 'frequency' => '']], 'related_tool_ids' => [$tool]];
$r = ih_rest('POST', '/wp/v2/learn-practice', ['title' => 'IH wf2', 'status' => 'publish', 'learn-formats' => [$wf_term], 'industries' => [$re], 'meta' => $wf_meta]);
check('workflow: complete but unclassified provider is still refused', 400 === $r->get_status());
$r = ih_rest('POST', '/wp/v2/learn-practice', ['title' => 'IH wf3', 'status' => 'publish', 'learn-formats' => [$wf_term], 'industries' => [$re], 'provider-types' => [$official], 'meta' => $wf_meta]);
check('workflow: complete with industry, tool and official provider publishes (201)', 201 === $r->get_status() && 'publish' === ($r->get_data()['status'] ?? ''));
if (201 !== $r->get_status()) { echo '  -> ', wp_json_encode($r->get_data(), JSON_UNESCAPED_UNICODE), ' tool=', $tool, ' type=', get_post_type($tool), "\n"; }
$id = (int) ($r->get_data()['id'] ?? 0);
$r = ih_rest('POST', '/wp/v2/learn-practice/' . $id, ['meta' => ['workflow_results' => [['metric' => 'x', 'value' => '必ず倍になる', 'evidence' => 'expected']]]]);
check('workflow: editing a live workflow is never blocked or demoted', 200 === $r->get_status() && 'publish' === get_post_status($id));
check('workflow: workflow meta is registered with REST schema on learn_practice', isset($r->get_data()['meta']['workflow_problem']));

// -------------------------------------------------------------- cleanup
foreach ([$res, $draft, $pid, $tool, $id, $own, $bulk_res] as $p) { if ($p && !is_wp_error($p)) { wp_delete_post((int) $p, true); } }
if (is_array($deep)) { wp_delete_term($deep['term_id'], 'omx_industry'); }
foreach (get_users(['search' => 'ih-*', 'search_columns' => ['user_login']]) as $u) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($u->ID); }
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
