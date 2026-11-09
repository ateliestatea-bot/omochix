<?php
/**
 * Industry Hub Phase 2 (plugin): visibility (G1+G2..G5), indexability
 * (G1..G7), the gate cache and its invalidation, batched gate computation,
 * and leak protection once hubs are public (REST, post fields and filters,
 * post_class, site search, feeds / embeds, sitemap).
 * Run: php tests/industry-hub/visibility.php
 */
require __DIR__ . '/stub.php';

ih_fresh();
$re = ih_term('omx_industry', 'real-estate')->term_id;
$rs = ih_term('omx_industry', 'restaurant')->term_id;
$by = ih_term('omx_industry', 'beauty')->term_id;
$sub = wp_insert_term('賃貸管理', 'omx_industry', ['slug' => 'rental', 'parent' => $re])['term_id'];

// ------------------------------------------------------------- visibility
$v = omochix_core_industry_visibility($re);
check('visibility: an empty hub is neither visible nor indexable', false === $v['visible'] && false === $v['indexable']);
ih_fill_hub($re);
omochix_core_gate_new_request(); omochix_core_gate_invalidate();
check('visibility: content without an official workflow is not visible (G5 is part of visibility)', false === omochix_core_industry_visibility($re)['visible'] && in_array('G5_official_workflow', omochix_core_industry_visibility($re)['unmet'], true));
$wf = ih_add_workflow('real-estate');
omochix_core_gate_new_request(); omochix_core_gate_invalidate();
$v = omochix_core_industry_visibility($re);
check('visibility: G1..G7 met → visible and indexable', true === $v['visible'] && true === $v['indexable'] && [] === $v['unmet']);
update_term_meta($re, 'industry_sources', []);
omochix_core_gate_new_request(); omochix_core_gate_invalidate();
$v = omochix_core_industry_visibility($re);
check('visibility: missing sources (G7) → still visible, not indexable', true === $v['visible'] && false === $v['indexable']);
update_term_meta($re, 'industry_verified_at', '2020-01-01');
update_term_meta($re, 'industry_sources', [['label' => 's', 'url' => 'https://example.com/']]);
omochix_core_gate_new_request(); omochix_core_gate_invalidate();
check('visibility: stale (G6) → visible, not indexable', true === omochix_core_industry_visibility($re)['visible'] && false === omochix_core_industry_visibility($re)['indexable']);
update_term_meta($re, 'industry_verified_at', gmdate('Y-m-d'));
update_term_meta($re, 'industry_hub_ready', false);
omochix_core_gate_new_request(); omochix_core_gate_invalidate();
check('visibility: without the editor flag (G1) a complete hub is not visible', false === omochix_core_industry_visibility($re)['visible']);
update_term_meta($re, 'industry_hub_ready', true);
omochix_core_gate_new_request(); omochix_core_gate_invalidate();
check('visibility: sub-industries follow their parent', true === omochix_core_industry_term_is_visible($sub) && false === omochix_core_industry_term_is_visible($by) && false === omochix_core_industry_term_is_visible(999999));

// ------------------------------------------------------------- gate cache
$nr = 'omochix_core_gate_new_request';
$nr(); omochix_core_gate_invalidate();
$nr();
$GLOBALS['query_count'] = 0;
omochix_core_industry_visibility($re);
$first = $GLOBALS['query_count'];
omochix_core_industry_visibility($re);
check('cache: the second read in the same version does not query workflows again', $first >= 1 && $first === $GLOBALS['query_count']);
$opt = get_option('omochix_core_industry_gate_cache');
check('cache: stored in one option for the current version (no term meta written on read)', is_array($opt) && omochix_core_gate_version() === $opt['v'] && [] === $opt['gates'][$re]['unmet'] && !isset($GLOBALS['term_meta'][$re]['_industry_gate_cache']));
$nr();
$GLOBALS['query_count'] = 0;
omochix_core_industry_visibility($re);
check('cache: a new request reads the stored option (no recompute)', 0 === $GLOBALS['query_count']);
$GLOBALS['query_count'] = 0;
omochix_core_industry_gates([$re], time() + DAY_IN_SECONDS + 5);
check('cache: entries expire after a day (G6 depends on time)', $GLOBALS['query_count'] >= 1);
$bump = static function (callable $fn) use ($nr) { $nr(); $v = omochix_core_gate_version(); $fn(); return omochix_core_gate_version() - $v; };
check('invalidate: editing hub content bumps the version', 1 === $bump(static fn() => do_action('updated_term_meta', 1, $re, 'industry_lead', 'x')));
check('invalidate: unrelated term meta does not', 0 === $bump(static fn() => do_action('updated_term_meta', 1, $re, 'slim_seo', [])));
check('invalidate: a workflow status change', 1 === $bump(static fn() => do_action('transition_post_status', 'draft', 'publish', get_post($wf))));
check('invalidate: not for same-status saves or other post types', 0 === $bump(static function () use ($wf) { do_action('transition_post_status', 'publish', 'publish', get_post($wf)); do_action('transition_post_status', 'draft', 'publish', new WP_Post(['ID' => 5, 'post_type' => 'post'])); }));
check('invalidate: provider / format / industry terms on a practice item', 1 === $bump(static fn() => omochix_core_gate_on_object_terms($wf, [], [], 'omx_provider_type')));
check('invalidate: terms removed with wp_remove_object_terms (deleted_term_relationships)', 1 === $bump(static fn() => do_action('deleted_term_relationships', $wf, [1], 'learn_format')));
check('invalidate: workflow fields and required tools', 1 === $bump(static fn() => do_action('updated_post_meta', 1, $wf, 'workflow_problem', 'x')) && 1 === $bump(static fn() => do_action('updated_post_meta', 1, $wf, 'related_tool_ids', [])));
check('invalidate: not for other practice meta', 0 === $bump(static fn() => do_action('updated_post_meta', 1, $wf, 'practice_summary', 'x')));
check('invalidate: editing the industry term, editing or deleting a purpose', 1 === $bump(static fn() => do_action('edited_omx_industry', $re, $re, 'omx_industry')) && 1 === $bump(static fn() => do_action('delete_omx_purpose', 1, 1, 'omx_purpose')));
check('invalidate: a password added to a workflow (publish → publish)', 1 === $bump(static fn() => do_action('post_updated', $wf, new WP_Post(['ID' => $wf, 'post_type' => 'learn_practice', 'post_password' => 'pw']), get_post($wf))));
check('invalidate: deleting or trashing a tool or practice item', 1 === $bump(static fn() => do_action('before_delete_post', $wf)) && 0 === $bump(static fn() => do_action('trashed_post', ih_add_post(['post_type' => 'post']))));
$nr(); $v0 = omochix_core_gate_version();
omochix_core_gate_invalidate(); omochix_core_gate_invalidate(); omochix_core_gate_invalidate();
check('coalesce: many changes in one request bump once at once…', $v0 + 1 === omochix_core_gate_version());
do_action('shutdown');
check('…and once more at shutdown (a recompute in between is never kept)', $v0 + 2 === omochix_core_gate_version());
$nr(); $v0 = omochix_core_gate_version();
omochix_core_gate_invalidate();
omochix_core_industry_visibility($re); // recompute and store under the new version
$stored = get_option('omochix_core_industry_gate_cache')['v'];
omochix_core_gate_invalidate(); do_action('shutdown');
check('coalesce: the stored result of an in-between recompute is outdated after shutdown', $stored < omochix_core_gate_version());

// --------------------------------------------------------------- batching
ih_fill_hub($rs);
ih_add_workflow('restaurant');
ih_fill_hub($by);
omochix_core_gate_new_request(); omochix_core_gate_invalidate();
$GLOBALS['query_count'] = 0; $GLOBALS['primed'] = [];
$rows = omochix_core_get_industries_with_visibility(false);
check('batch: the gates of all six industries are computed with one workflow query', 1 === $GLOBALS['query_count'] && 6 === count($rows));
check('batch: candidate workflows and their tools are primed in bulk', count($GLOBALS['primed']) >= 2);
$vis = array_map(static fn($r) => $r['term']->slug, array_filter($rows, static fn($r) => $r['visible']));
check('batch: only hubs with content and an official workflow are visible', ['real-estate', 'restaurant'] === array_values($vis));
check('visible ids helper', [$re, $rs] === omochix_core_get_visible_industry_ids() || [$rs, $re] === omochix_core_get_visible_industry_ids());
$creator = ih_add_workflow('beauty', 'marketing', 'creator');
omochix_core_gate_new_request(); omochix_core_gate_invalidate();
check('batch: a workflow by a creator does not make beauty visible', false === omochix_core_industry_visibility($by)['visible']);

// --------------------------------------------------- leak protection (public)
$tool = ih_add_post(['post_type' => 'ai_tool', 'post_name' => 'gpt', 'post_status' => 'publish']);
ih_as([]);
check('inert while private: REST term query untouched', ['x' => 1] === omochix_core_industry_rest_term_query(['x' => 1]));
add_filter('omochix_core_industry_public', static fn() => true);
$args = omochix_core_industry_rest_term_query([]);
check('rest list: visitors get visible hubs and their sub-industries only', !in_array($by, $args['include'], true) && in_array($re, $args['include'], true) && in_array($sub, $args['include'], true));
$args = omochix_core_industry_rest_term_query(['include' => [$by]]);
check('rest list: asking for a hidden industry by id returns nothing', [0] === $args['include']);
$err = omochix_core_industry_rest_term_single('resp', get_term($by, 'omx_industry'), null);
check('rest single: a hidden industry is a 404', $err instanceof WP_Error && 404 === $err->data['status'] && 'resp' === omochix_core_industry_rest_term_single('resp', get_term($re, 'omx_industry'), null));
$resp = new class(['id' => 1, 'industries' => [$re, $by, $sub]]) { private $d; function __construct($d) { $this->d = $d; } function get_data() { return $this->d; } function set_data($d) { $this->d = $d; } };
check('rest post: the industries field keeps visible ones only', [$re, $sub] === omochix_core_industry_rest_post_fields($resp)->get_data()['industries']);
$q = omochix_core_industry_rest_post_query(['tax_query' => [['taxonomy' => 'omx_industry', 'field' => 'term_id', 'terms' => [$by]], ['taxonomy' => 'category', 'terms' => [1]]]]);
check('rest post query: ?industries=<hidden> matches nothing; other clauses kept', [0] === $q['tax_query'][0]['terms'] && [1] === $q['tax_query'][1]['terms']);
ih_as(['edit_posts']);
check('editors are not filtered', ['include' => [$by]] === omochix_core_industry_rest_term_query(['include' => [$by]]) && 'resp' === omochix_core_industry_rest_term_single('resp', get_term($by, 'omx_industry'), null));
ih_as([]);
check('post_class: industry classes are never printed', ['category', 'post_tag'] === apply_filters('post_class_taxonomies', ['category', 'omx_industry', 'post_tag']));

// every main query narrowed by an industry
class IH_Main_Query extends WP_Query { function is_main_query() { return true; } }
$q = new IH_Main_Query([]); $q->tax_query = (object) ['queries' => [['taxonomy' => 'omx_industry', 'field' => 'slug', 'terms' => ['beauty']], ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [2]]]];
omochix_core_industry_tax_query_guard($q);
check('tax guard: a hidden industry (slug) matches nothing, like a missing one; other clauses kept', ['term_id', [0]] === [$q->tax_query->queries[0]['field'], $q->tax_query->queries[0]['terms']] && [2] === $q->tax_query->queries[1]['terms']);
$q->tax_query = (object) ['queries' => [['taxonomy' => 'omx_industry', 'field' => 'slug', 'terms' => ['no-such']]]];
omochix_core_industry_tax_query_guard($q);
check('tax guard: a missing industry gives the same clause (indistinguishable)', [0] === $q->tax_query->queries[0]['terms']);
$q->tax_query = (object) ['queries' => [['taxonomy' => 'omx_industry', 'field' => 'term_id', 'terms' => [$re, $by, ['x']]]]];
omochix_core_industry_tax_query_guard($q);
check('tax guard: visible IDs are kept, hidden and non-scalar values dropped (no warnings)', [$re] === $q->tax_query->queries[0]['terms']);
$q->tax_query = (object) ['queries' => [['taxonomy' => 'omx_industry', 'field' => 'name', 'terms' => ['賃貸管理']]]];
omochix_core_industry_tax_query_guard($q);
check('tax guard: names resolve too (sub-industry of a visible hub stays)', [$sub] === $q->tax_query->queries[0]['terms']);
$secondary = new WP_Query([]); $secondary->tax_query = (object) ['queries' => [['taxonomy' => 'omx_industry', 'field' => 'slug', 'terms' => ['beauty']]]];
omochix_core_industry_tax_query_guard($secondary);
check('tax guard: secondary (server-side) queries are left alone', ['beauty'] === $secondary->tax_query->queries[0]['terms']);
ih_as(['edit_posts']);
$q->tax_query = (object) ['queries' => [['taxonomy' => 'omx_industry', 'field' => 'slug', 'terms' => ['beauty']]]];
omochix_core_industry_tax_query_guard($q);
check('tax guard: editors are not filtered', ['beauty'] === $q->tax_query->queries[0]['terms']);
ih_as([]);
check('rest term search: hidden hubs are excluded', in_array($by, omochix_core_industry_rest_term_search(['exclude' => [5]])['exclude'], true) && in_array(5, omochix_core_industry_rest_term_search(['exclude' => [5]])['exclude'], true) && !in_array($re, omochix_core_industry_rest_term_search([])['exclude'], true));
check('core sitemap: the same per-term rule', [0] !== omochix_core_industry_core_sitemap_term_args(['hide_empty' => true], 'omx_industry')['include'] && false === omochix_core_industry_core_sitemap_term_args(['hide_empty' => true], 'omx_industry')['hide_empty'] && ['x' => 1] === omochix_core_industry_core_sitemap_term_args(['x' => 1], 'category'));

// feeds and embeds
$GLOBALS['wp_query'] = new class { public $is404 = false, $is_feed = true, $posts = [1], $post_count = 1; function set_404() { $this->is404 = true; } };
$GLOBALS['ctx'] = ['is_feed' => true, 'is_tax' => 'omx_industry']; $GLOBALS['status_header'] = null;
omochix_core_industry_block_feeds();
check('feed: /learn/industries/{slug}/feed/ is a 404 with an empty body (is_feed off, no posts)', true === $GLOBALS['wp_query']->is404 && 404 === $GLOBALS['status_header'] && false === $GLOBALS['wp_query']->is_feed && [] === $GLOBALS['wp_query']->posts);
$GLOBALS['wp_query']->is404 = false; $GLOBALS['ctx'] = ['is_feed' => true]; $GLOBALS['query_vars'] = ['omx_industry' => 'real-estate'];
omochix_core_industry_block_feeds();
check('feed: an array query var does not warn', (static function () { $GLOBALS['query_vars'] = ['omx_industry' => ['a']]; omochix_core_industry_block_feeds(); return true; })());
$GLOBALS['query_vars'] = ['omx_industry' => 'real-estate'];
check('feed: /feed/?omx_industry= is a 404', true === $GLOBALS['wp_query']->is404);
$GLOBALS['wp_query']->is404 = false; $GLOBALS['ctx'] = ['is_embed' => true, 'is_tax' => 'omx_industry']; $GLOBALS['query_vars'] = [];
omochix_core_industry_block_feeds();
check('embed: an industry embed is a 404', true === $GLOBALS['wp_query']->is404);
$GLOBALS['wp_query']->is404 = false; $GLOBALS['ctx'] = ['is_tax' => 'omx_industry']; $GLOBALS['query_vars'] = ['embed' => 'true'];
omochix_core_industry_block_feeds();
check('embed: a term embed (is_embed() false, embed query var set) is a 404', true === $GLOBALS['wp_query']->is404);
$GLOBALS['query_vars'] = [];
$GLOBALS['wp_query']->is404 = false; $GLOBALS['ctx'] = ['is_feed' => true];
omochix_core_industry_block_feeds();
check('feed: other feeds are untouched', false === $GLOBALS['wp_query']->is404);
$GLOBALS['ctx'] = [];

// sitemap
$args = omochix_core_industry_sitemap_term_args(['taxonomy' => 'omx_industry', 'hide_empty' => true]);
check('sitemap: only indexable top-level hubs, hide_empty off, parent 0', in_array($re, $args['include'], true) && in_array($rs, $args['include'], true) && !in_array($by, $args['include'], true) && false === $args['hide_empty'] && 0 === $args['parent']);
check('sitemap: other taxonomies untouched', ['taxonomy' => 'category'] === omochix_core_industry_sitemap_term_args(['taxonomy' => 'category']));
update_term_meta($re, 'industry_sources', []); update_term_meta($rs, 'industry_sources', []);
omochix_core_gate_new_request(); omochix_core_gate_invalidate();
check('sitemap: no indexable hub → include [0] (matches nothing, never "all")', [0] === omochix_core_industry_sitemap_term_args(['taxonomy' => 'omx_industry'])['include']);

// blocked workflows (§7 re-check of live workflows, cached per gate version)
$nr(); omochix_core_gate_invalidate(); $nr();
$wfs = []; for ($i = 0; $i < 3; $i++) { $wfs[] = ih_add_workflow('real-estate'); }
delete_post_meta($wfs[1], 'workflow_problem');
$nr();
$b = omochix_core_blocked_workflow_ids();
check('blocked: a live workflow emptied after publishing is listed, complete ones are not', in_array($wfs[1], $b, true) && !in_array($wfs[0], $b, true) && !in_array($wfs[2], $b, true));
$opt = get_option('omochix_core_blocked_workflow_cache');
check('blocked: stored in one option for the current gate version', is_array($opt) && omochix_core_gate_version() === $opt['v'] && $b === $opt['ids']);
$nr();
$GLOBALS['query_count'] = 0;
check('blocked: a new request reads the stored list (no workflow query)', $b === omochix_core_blocked_workflow_ids() && 0 === $GLOBALS['query_count']);
update_post_meta($wfs[1], 'workflow_problem', '課題');
$nr(); omochix_core_gate_invalidate(); // what the updated_post_meta hook does (wired on a real site; see http-access-matrix)
check('blocked: after a gate version bump the list is recomputed (restored workflow listed again)', !in_array($wfs[1], omochix_core_blocked_workflow_ids(), true));
$nr();
$GLOBALS['query_count'] = 0;
omochix_core_blocked_workflow_ids(time() + DAY_IN_SECONDS + 5);
check('blocked: entries expire after a day (the word list is a filter)', $GLOBALS['query_count'] >= 1);
delete_post_meta($wfs[0], 'workflow_problem'); // the oldest workflow
for ($i = 0; $i < 450; $i++) { ih_add_workflow('restaurant'); }
$nr(); omochix_core_gate_invalidate();
$GLOBALS['query_count'] = 0;
$b = omochix_core_blocked_workflow_ids();
check('blocked: every published workflow is checked in batches of 200 (no cap lets the oldest through)', in_array($wfs[0], $b, true) && 3 === $GLOBALS['query_count'], 'queries=' . $GLOBALS['query_count']);
update_post_meta($wfs[0], 'workflow_problem', '課題');
$nr(); omochix_core_gate_invalidate(); $nr();

ih_done();
