<?php
/**
 * Industry Hub Phase 1: the read side — content by industry (many-to-many,
 * sub-industries, purpose and provider filters), curated lists, Motion
 * references, ledger cards, and what is never returned (drafts, password
 * protected, unknown filters, unclassified as official).
 * Run: php tests/industry-hub/relations.php
 */
require __DIR__ . '/stub.php';

ih_fresh();
$re  = ih_term('omx_industry', 'real-estate');
$sub = wp_insert_term('賃貸管理', 'omx_industry', ['slug' => 'rental', 'parent' => $re->term_id])['term_id'];
$add = static function ($type, $industries, $extra = []) { $id = ih_add_post(array_merge(['post_type' => $type, 'post_status' => 'publish', 'post_name' => $type . '-' . count($GLOBALS['posts'])], $extra)); wp_set_object_terms($id, $industries, 'omx_industry'); return $id; };

$tool_a = $add('ai_tool', ['real-estate', 'restaurant']);
$tool_b = $add('ai_tool', ['rental']);
$tool_c = $add('ai_tool', ['beauty']);
$draft  = $add('ai_tool', ['real-estate'], ['post_status' => 'draft']);
$locked = $add('prompt', ['real-estate'], ['post_password' => 'pw']);
$prompt = $add('prompt', ['real-estate']);
wp_set_object_terms($prompt, ['marketing'], 'omx_purpose');
$prompt2 = $add('prompt', ['real-estate']);
wp_set_object_terms($prompt2, ['document'], 'omx_purpose');
wp_set_object_terms($prompt2, ['creator'], 'omx_provider_type');
$ids = static fn($posts) => wp_list_pluck($posts, 'ID');

check('by industry: tools tagged with the industry or one of its sub-industries', [$tool_a, $tool_b] === $ids(omochix_core_get_industry_posts('real-estate', 'ai_tool')));
check('by industry: the same tool appears under each of its industries (many-to-many)', [$tool_a] === $ids(omochix_core_get_industry_posts('restaurant', 'ai_tool')) && [$tool_c] === $ids(omochix_core_get_industry_posts('beauty', 'ai_tool')));
check('by industry: a sub-industry lists only its own content', [$tool_b] === $ids(omochix_core_get_industry_posts($sub, 'ai_tool')));
check('by industry: drafts and password-protected posts are never returned', !in_array($draft, $ids(omochix_core_get_industry_posts('real-estate', 'ai_tool')), true) && !in_array($locked, $ids(omochix_core_get_industry_posts('real-estate', 'prompt')), true));
check('by industry: accepts an ID, a slug or a term; unknown industries give nothing', $ids(omochix_core_get_industry_posts($re->term_id, 'prompt')) === $ids(omochix_core_get_industry_posts($re, 'prompt')) && [] === omochix_core_get_industry_posts('no-such', 'prompt') && [] === omochix_core_get_industry_posts(ih_term('omx_purpose', 'marketing'), 'prompt'));
check('search: industry + business purpose', [$prompt] === $ids(omochix_core_get_industry_posts('real-estate', 'prompt', ['purpose' => 'marketing'])) && [$prompt2] === $ids(omochix_core_get_industry_posts('real-estate', 'prompt', ['purpose' => 'document'])));
check('search: an unknown purpose narrows to nothing (not "ignore the filter")', [] === omochix_core_get_industry_posts('real-estate', 'prompt', ['purpose' => '<script>']));
check('search: array-shaped filter values (?purpose[]=x) narrow to nothing, without warnings', [] === omochix_core_get_industry_posts('real-estate', 'prompt', ['purpose' => ['marketing']]) && [] === omochix_core_get_industry_posts('real-estate', 'prompt', ['provider' => ['creator']]));
$GLOBALS['post_terms'][$prompt2]['omx_provider_type'][] = ih_term('omx_provider_type', 'official')->term_id; // two providers, written around the API
check('search: a post carrying two providers matches neither filter', [] === omochix_core_get_industry_posts('real-estate', 'prompt', ['provider' => 'official']) && [] === omochix_core_get_industry_posts('real-estate', 'prompt', ['provider' => 'creator']));
$GLOBALS['post_terms'][$prompt2]['omx_provider_type'] = [ih_term('omx_provider_type', 'creator')->term_id];
check('search: provider filter', [$prompt2] === $ids(omochix_core_get_industry_posts('real-estate', 'prompt', ['provider' => 'creator'])));
check('search: "official" never matches unclassified content', [] === omochix_core_get_industry_posts('real-estate', 'prompt', ['provider' => 'official']));
check('search: "unclassified" or unknown providers are not filters', [] === omochix_core_get_industry_posts('real-estate', 'prompt', ['provider' => 'unclassified']) && [] === omochix_core_get_industry_posts('real-estate', 'ai_tool', ['provider' => 'official']));
check('search: only listable post types (never the private ledger through this getter)', [] === omochix_core_get_industry_posts('real-estate', 'omx_resource') && [] === omochix_core_get_industry_posts('real-estate', 'attachment'));
omochix_core_get_industry_posts('real-estate', 'prompt', ['limit' => 500, 'exclude' => [$prompt]]);
check('query: published only, no passwords, no_found_rows, fetches headroom (24) for a 12-item cap, exclusions applied', 'publish' === $GLOBALS['last_query']['post_status'] && false === $GLOBALS['last_query']['has_password'] && true === $GLOBALS['last_query']['no_found_rows'] && 24 === $GLOBALS['last_query']['posts_per_page'] && [$prompt] === $GLOBALS['last_query']['post__not_in']);
function omochix_get_active_ai_tool_meta_query() { return ['relation' => 'OR', ['key' => 'tool_status', 'compare' => 'NOT EXISTS']]; }
$many = array_map(static fn($i) => $add('prompt', ['construction']), range(1, 15));
check('query: the result never exceeds the requested limit', 12 === count(omochix_core_get_industry_posts('construction', 'prompt', ['limit' => 99])) && 3 === count(omochix_core_get_industry_posts('construction', 'prompt', ['limit' => 3])));
omochix_core_get_industry_posts('real-estate', 'ai_tool');
check('query: discontinued tools are excluded with the theme\'s active-tool rule when available', isset($GLOBALS['last_query']['meta_query']['relation']));
omochix_core_get_industry_posts('real-estate', 'prompt');
check('query: the active-tool rule is not applied to other types', !isset($GLOBALS['last_query']['meta_query']));

// curated lists keep the editor's order and drop what is not published
update_term_meta($re->term_id, 'industry_tool_ids', [$tool_c, $draft, $tool_a]);
check('curated: editor order, published only', [$tool_c, $tool_a] === $ids(omochix_core_get_industry_curated('real-estate', 'industry_tool_ids')) && 'post__in' === $GLOBALS['last_query']['orderby'] && isset($GLOBALS['last_query']['meta_query']));
check('curated: only the two curated keys are readable this way', [] === omochix_core_get_industry_curated('real-estate', 'industry_lead'));
$GLOBALS['term_meta'][$re->term_id]['industry_tool_ids'] = [$prompt, $tool_a];
check('curated: a tampered list is re-checked by post type on read', [$tool_a] === $ids(omochix_core_get_industry_curated('real-estate', 'industry_tool_ids')));

// industries of a post
$both = $add('learn_practice', ['rental', 'real-estate', 'construction']);
check('post industries: a sub-industry counts as its parent, no duplicates', ['real-estate', 'construction'] === wp_list_pluck(omochix_core_get_post_industries($both), 'slug') && [] === omochix_core_get_post_industries($add('post', [])));

// Motion
update_term_meta($re->term_id, 'industry_motion_slugs', ['pop-in', 'ghost-slug']);
check('motion: without the theme catalog nothing is returned', [] === omochix_core_get_industry_motion_slugs('real-estate'));
if (!function_exists('omochix_learn_gallery_get_item')) { function omochix_learn_gallery_get_item($slug) { static $c = null; $c = $c ?? json_decode((string) file_get_contents(__DIR__ . '/../../wp-theme/omochix-theme/assets/data/motion/catalog.json'), true); foreach ($c['items'] as $i) { if ($i['slug'] === $slug) { return $i; } } return null; } }
check('motion: with the catalog, existing slugs only', ['pop-in'] === omochix_core_get_industry_motion_slugs('real-estate'));

// ledger cards
$res_ok = $add('omx_resource', ['real-estate']);
foreach (['resource_url' => 'https://example.com/a', 'resource_kind' => 'article', 'resource_provider_name' => 'A社', 'resource_summary' => '紹介', 'resource_license_basis' => 'link_only', 'resource_verified_at' => '2026-11-01', 'resource_permission_ref' => 'LIC-1'] as $k => $v) { update_post_meta($res_ok, $k, $v); }
wp_set_object_terms($res_ok, ['external-service'], 'omx_provider_type');
$res_half = $add('omx_resource', ['real-estate']);
update_post_meta($res_half, 'resource_url', 'https://example.com/c'); // published but incomplete record
$res_draft = $add('omx_resource', ['real-estate'], ['post_status' => 'draft']);
update_post_meta($res_draft, 'resource_url', 'https://example.com/b');
$cards = omochix_core_get_industry_resource_cards('real-estate');
check('ledger cards by industry: published, complete entries only, no permission reference', 1 === count($cards) && $res_ok === $cards[0]['id'] && false === strpos(json_encode($cards), 'LIC-1'));
$wf = $add('learn_practice', ['real-estate']);
update_post_meta($wf, 'workflow_resource_ids', [$res_draft, $res_ok, $tool_a]);
check('workflow cards: stored order, unpublished and non-ledger IDs dropped', [$res_ok] === array_column(omochix_core_get_workflow_resource_cards($wf), 'id'));

ih_done();
