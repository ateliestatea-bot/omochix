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
omochix_core_gate_invalidate();
check('visibility: content without an official workflow is not visible (G5 is part of visibility)', false === omochix_core_industry_visibility($re)['visible'] && in_array('G5_official_workflow', omochix_core_industry_visibility($re)['unmet'], true));
$wf = ih_add_workflow('real-estate');
omochix_core_gate_invalidate();
$v = omochix_core_industry_visibility($re);
check('visibility: G1..G7 met → visible and indexable', true === $v['visible'] && true === $v['indexable'] && [] === $v['unmet']);
update_term_meta($re, 'industry_sources', []);
omochix_core_gate_invalidate();
$v = omochix_core_industry_visibility($re);
check('visibility: missing sources (G7) → still visible, not indexable', true === $v['visible'] && false === $v['indexable']);
update_term_meta($re, 'industry_verified_at', '2020-01-01');
update_term_meta($re, 'industry_sources', [['label' => 's', 'url' => 'https://example.com/']]);
omochix_core_gate_invalidate();
check('visibility: stale (G6) → visible, not indexable', true === omochix_core_industry_visibility($re)['visible'] && false === omochix_core_industry_visibility($re)['indexable']);
update_term_meta($re, 'industry_verified_at', gmdate('Y-m-d'));
update_term_meta($re, 'industry_hub_ready', false);
omochix_core_gate_invalidate();
check('visibility: without the editor flag (G1) a complete hub is not visible', false === omochix_core_industry_visibility($re)['visible']);
update_term_meta($re, 'industry_hub_ready', true);
omochix_core_gate_invalidate();
check('visibility: sub-industries follow their parent', true === omochix_core_industry_term_is_visible($sub) && false === omochix_core_industry_term_is_visible($by) && false === omochix_core_industry_term_is_visible(999999));

// ------------------------------------------------------------- gate cache
omochix_core_gate_invalidate();
$GLOBALS['query_count'] = 0;
omochix_core_industry_visibility($re);
$first = $GLOBALS['query_count'];
omochix_core_industry_visibility($re);
check('cache: the second read in the same version does not query workflows again', $first >= 1 && $first === $GLOBALS['query_count']);
$cache = get_term_meta($re, '_industry_gate_cache', true);
check('cache: stored on the term with version, time and unmet codes', is_array($cache) && omochix_core_gate_version() === $cache['v'] && [] === $cache['unmet']);
$GLOBALS['query_count'] = 0;
omochix_core_industry_gates([$re], time() + DAY_IN_SECONDS + 5);
check('cache: entries expire after a day (G6 depends on time)', $GLOBALS['query_count'] >= 1);
$v0 = omochix_core_gate_version();
do_action('updated_term_meta', 1, $re, 'industry_lead', 'x');
check('invalidate: editing hub content bumps the version', $v0 + 1 === omochix_core_gate_version());
$v0 = omochix_core_gate_version();
do_action('updated_term_meta', 1, $re, '_industry_gate_cache', []);
do_action('updated_term_meta', 1, $re, 'slim_seo', []);
check('invalidate: writing the cache itself or unrelated meta does not (no loop)', $v0 === omochix_core_gate_version());
do_action('transition_post_status', 'draft', 'publish', get_post($wf));
check('invalidate: a workflow status change', $v0 + 1 === omochix_core_gate_version());
$v0 = omochix_core_gate_version();
do_action('transition_post_status', 'publish', 'publish', get_post($wf));
do_action('transition_post_status', 'draft', 'publish', new WP_Post(['ID' => 5, 'post_type' => 'post']));
check('invalidate: not for same-status saves or other post types', $v0 === omochix_core_gate_version());
omochix_core_gate_on_object_terms($wf, [], [], 'omx_provider_type'); // direct: a fake set_object_terms would trigger the provider rules
check('invalidate: provider / format / industry terms on a practice item', $v0 + 1 === omochix_core_gate_version());
$v0 = omochix_core_gate_version();
do_action('updated_post_meta', 1, $wf, 'workflow_problem', 'x');
do_action('updated_post_meta', 1, $wf, 'related_tool_ids', []);
check('invalidate: workflow fields and required tools', $v0 + 2 === omochix_core_gate_version());
$v0 = omochix_core_gate_version();
do_action('updated_post_meta', 1, $wf, 'practice_summary', 'x');
check('invalidate: not for other practice meta', $v0 === omochix_core_gate_version());
do_action('edited_omx_industry', $re, $re, 'omx_industry');
check('invalidate: editing the term', $v0 + 1 === omochix_core_gate_version());

// --------------------------------------------------------------- batching
ih_fill_hub($rs);
ih_add_workflow('restaurant');
ih_fill_hub($by);
omochix_core_gate_invalidate();
$GLOBALS['query_count'] = 0; $GLOBALS['primed'] = [];
$rows = omochix_core_get_industries_with_visibility(false);
check('batch: the gates of all six industries are computed with one workflow query', 1 === $GLOBALS['query_count'] && 6 === count($rows));
check('batch: candidate workflows and their tools are primed in bulk', count($GLOBALS['primed']) >= 2);
$vis = array_map(static fn($r) => $r['term']->slug, array_filter($rows, static fn($r) => $r['visible']));
check('batch: only hubs with content and an official workflow are visible', ['real-estate', 'restaurant'] === array_values($vis));
check('visible ids helper', [$re, $rs] === omochix_core_get_visible_industry_ids() || [$rs, $re] === omochix_core_get_visible_industry_ids());
$creator = ih_add_workflow('beauty', 'marketing', 'creator');
omochix_core_gate_invalidate();
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

// site search narrowed by a hidden industry
$main = new class { public $vars = []; public $tax_query = 'x'; function is_main_query() { return true; } function is_search() { return true; } function get($k) { return $this->vars[$k] ?? ''; } function set($k, $v) { $this->vars[$k] = $v; } };
$main->vars = ['omx_industry' => 'beauty', 'tax_query' => [['taxonomy' => 'omx_industry', 'terms' => ['beauty']], ['taxonomy' => 'category', 'terms' => [2]]]];
omochix_core_industry_main_query_guard($main);
check('search: ?omx_industry=<hidden> is dropped for visitors (other conditions kept)', '' === $main->vars['omx_industry'] && 1 === count($main->vars['tax_query']));
$main->vars = ['omx_industry' => 'real-estate'];
omochix_core_industry_main_query_guard($main);
check('search: a visible industry filter is kept', 'real-estate' === $main->vars['omx_industry']);

// feeds and embeds
$GLOBALS['wp_query'] = new class { public $is404 = false; function set_404() { $this->is404 = true; } };
$GLOBALS['ctx'] = ['is_feed' => true, 'is_tax' => 'omx_industry']; $GLOBALS['status_header'] = null;
omochix_core_industry_block_feeds();
check('feed: /learn/industries/{slug}/feed/ is a 404', true === $GLOBALS['wp_query']->is404 && 404 === $GLOBALS['status_header']);
$GLOBALS['wp_query']->is404 = false; $GLOBALS['ctx'] = ['is_feed' => true]; $GLOBALS['query_vars'] = ['omx_industry' => 'real-estate'];
omochix_core_industry_block_feeds();
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
omochix_core_gate_invalidate();
check('sitemap: no indexable hub → include [0] (matches nothing, never "all")', [0] === omochix_core_industry_sitemap_term_args(['taxonomy' => 'omx_industry'])['include']);

ih_done();
