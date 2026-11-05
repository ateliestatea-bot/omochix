<?php
/**
 * Industry Hub Phase 1: taxonomies (omx_industry, omx_purpose,
 * omx_provider_type), provider rules and multi-industry association.
 * Run: php tests/industry-hub/run.php
 */
require __DIR__ . '/stub.php';

ih_fresh();
$tax = $GLOBALS['registered']['taxonomies'];

// ------------------------------------------------------------ registration
check('register: the three taxonomies exist', isset($tax['omx_industry'], $tax['omx_purpose'], $tax['omx_provider_type']));
check('register: industry and purpose attach to tools, prompts, practice, pages, posts and the resource ledger', ['ai_tool', 'prompt', 'learn_practice', 'page', 'post', 'omx_resource'] === $tax['omx_industry']['types'] && $tax['omx_industry']['types'] === $tax['omx_purpose']['types']);
check('register: provider type is not attached to AI tools', !in_array('ai_tool', $tax['omx_provider_type']['types'], true) && in_array('learn_practice', $tax['omx_provider_type']['types'], true));
check('register: industry is hierarchical, purpose flat, provider a checkbox list', true === $tax['omx_industry']['args']['hierarchical'] && false === $tax['omx_purpose']['args']['hierarchical'] && true === $tax['omx_provider_type']['args']['hierarchical']);
check('phase 1: industry hubs have no public URL by default (not public, not queryable, no rewrite, no query var)', false === $tax['omx_industry']['args']['public'] && false === $tax['omx_industry']['args']['publicly_queryable'] && false === $tax['omx_industry']['args']['rewrite'] && false === $tax['omx_industry']['args']['query_var']);
check('phase 1: purpose and provider never get a public URL', false === $tax['omx_purpose']['args']['public'] && false === $tax['omx_purpose']['args']['rewrite'] && false === $tax['omx_provider_type']['args']['public'] && false === $tax['omx_provider_type']['args']['rewrite']);
check('register: REST bases for the editor panels', 'industries' === $tax['omx_industry']['args']['rest_base'] && 'business-purposes' === $tax['omx_purpose']['args']['rest_base'] && 'provider-types' === $tax['omx_provider_type']['args']['rest_base']);
check('caps: industry/purpose terms are managed with manage_categories, assigned with edit_posts', 'manage_categories' === $tax['omx_industry']['args']['capabilities']['edit_terms'] && 'edit_posts' === $tax['omx_industry']['args']['capabilities']['assign_terms'] && 'manage_categories' === $tax['omx_purpose']['args']['capabilities']['delete_terms']);
check('caps: nobody can edit or delete provider terms (fixed vocabulary)', 'do_not_allow' === $tax['omx_provider_type']['args']['capabilities']['edit_terms'] && 'do_not_allow' === $tax['omx_provider_type']['args']['capabilities']['delete_terms']);
check('provider box: radio meta box, term IDs sanitized like the category box', 'omochix_core_provider_meta_box' === $tax['omx_provider_type']['args']['meta_box_cb'] && 'taxonomy_meta_box_sanitize_cb_checkboxes' === $tax['omx_provider_type']['args']['meta_box_sanitize_cb']);
check('register: runs on init 11, after the existing post types (init 10)', 11 === has_action('init', 'omochix_core_industry_hub_init') && 10 === has_action('init', 'omochix_core_init'));
check('register: get_object_taxonomies sees omx_industry on ai_tool and learn_practice', in_array('omx_industry', get_object_taxonomies('ai_tool'), true) && in_array('omx_industry', get_object_taxonomies('learn_practice'), true));

// --------------------------------------------------------------------- seed
$slugs = static fn($t) => array_values(wp_list_pluck($GLOBALS['terms'][$t], 'slug'));
check('seed: six industries with stable English slugs', ['real-estate', 'restaurant', 'beauty', 'ecommerce', 'construction', 'web-production'] === $slugs('omx_industry'));
check('seed: web-production is not the learn_field "web" slug (different axis)', null === ih_term('omx_industry', 'web'));
check('seed: seven purposes, four providers', 7 === count($GLOBALS['terms']['omx_purpose']) && ['official', 'external-service', 'creator', 'partner'] === $slugs('omx_provider_type'));
check('seed: names are the visible Japanese labels', '不動産' === ih_term('omx_industry', 'real-estate')->name && 'パートナー（PR）' === ih_term('omx_provider_type', 'partner')->name);
// The boot flushes twice: once for the practice module, once for Industry Hub.
check('flush: once per version (practice + industry = 2 on a fresh site), recorded with the private suffix', 2 === $GLOBALS['flushed'] && '1-private' === $GLOBALS['options']['omochix_core_industry_rewrite_version']);
$count = count($GLOBALS['terms']['omx_industry']);
omochix_core_industry_maybe_flush_rewrite_rules();
omochix_core_insert_industry_initial_terms();
check('seed: idempotent, no second flush', $count === count($GLOBALS['terms']['omx_industry']) && 2 === $GLOBALS['flushed']);
$GLOBALS['terms']['omx_industry'][ih_term('omx_industry', 'beauty')->term_id]->name = '美容・サロン';
omochix_core_insert_industry_initial_terms();
check('seed: an editor renaming a term is not overwritten', '美容・サロン' === ih_term('omx_industry', 'beauty')->name);

// ------------------------------------------------------------ public switch
add_filter('omochix_core_industry_public', '__return_true');
function __return_true() { return true; }
omochix_core_register_industry_taxonomies();
$ind = $GLOBALS['registered']['taxonomies']['omx_industry']['args'];
check('public switch: hubs live under /learn/industries/{slug}/ with flat URLs', true === $ind['public'] && ['slug' => 'learn/industries', 'with_front' => false, 'hierarchical' => false] === $ind['rewrite'] && true === $ind['query_var']);
omochix_core_industry_maybe_flush_rewrite_rules();
check('public switch: flushes once more and records the public version', 3 === $GLOBALS['flushed'] && '1-public' === $GLOBALS['options']['omochix_core_industry_rewrite_version']);
$GLOBALS['ctx']['is_tax'] = 'omx_industry';
$robots = apply_filters('wp_robots', []);
check('public switch: industry archives stay noindex,follow until the theme lifts it (Phase 2 gate)', true === ($robots['noindex'] ?? false) && true === ($robots['follow'] ?? false) && false === apply_filters('slim_seo_robots_index', true));
$GLOBALS['ctx']['is_tax'] = 'learn_field';
check('robots: other taxonomies are not touched by Industry Hub', !isset(apply_filters('wp_robots', [])['noindex']) && true === apply_filters('slim_seo_robots_index', true));
$GLOBALS['ctx']['is_tax'] = '';
check('sitemap: purpose, provider and (while noindex) industry are removed; others kept', ['learn_usecase', 'prompt_category'] === apply_filters('slim_seo_sitemap_taxonomies', ['learn_usecase', 'omx_purpose', 'prompt_category', 'omx_provider_type', 'omx_industry']));
remove_filter('omochix_core_industry_public', '__return_true');
ih_fresh();

// ---------------------------------------------------------- industry rules
$parent = ih_term('omx_industry', 'real-estate')->term_id;
$res    = wp_insert_term('売買仲介', 'omx_industry', ['slug' => 'brokerage', 'parent' => $parent]);
check('depth: a sub-industry under an industry is allowed', is_array($res) && $parent === ih_term('omx_industry', 'brokerage')->parent);
$sub    = $res['term_id'];
$third  = wp_insert_term('三階層目', 'omx_industry', ['slug' => 'too-deep', 'parent' => $sub]);
check('depth: a third level is refused', $third instanceof WP_Error && 'omochix_industry_depth' === $third->get_error_code() && null === ih_term('omx_industry', 'too-deep'));
foreach (['page', 'feed', 'embed'] as $reserved) {
    $r = wp_insert_term('x', 'omx_industry', ['slug' => $reserved]);
    check("reserved: industry slug \"$reserved\" is refused (a WordPress endpoint under the hub URL)", $r instanceof WP_Error && 'omochix_industry_reserved_slug' === $r->get_error_code());
}
check('reserved: a reserved word derived from the name is refused too', wp_insert_term('Feed', 'omx_industry') instanceof WP_Error);
check('reserved: ordinary slugs pass', is_array(wp_insert_term('介護', 'omx_industry', ['slug' => 'care'])));
$beauty = ih_term('omx_industry', 'beauty')->term_id;
check('update parent: moving an industry under a sub-industry keeps the stored parent', 0 === omochix_core_industry_update_term_parent($sub, $beauty, 'omx_industry'));
check('update parent: an industry that has sub-industries cannot become a sub-industry', 0 === omochix_core_industry_update_term_parent($beauty, $parent, 'omx_industry'));
check('update parent: a term cannot be its own parent', 0 === omochix_core_industry_update_term_parent($beauty, $beauty, 'omx_industry'));
check('update parent: a leaf may move under another industry', $beauty === omochix_core_industry_update_term_parent($beauty, $sub, 'omx_industry'));
check('update parent: other taxonomies are untouched', 5 === omochix_core_industry_update_term_parent(5, 1, 'learn_field'));
check('update data: a rename to a reserved slug keeps the stored slug', 'beauty' === omochix_core_industry_update_term_data(['slug' => 'feed', 'name' => 'x'], $beauty, 'omx_industry')['slug'] && 'feed' === omochix_core_industry_update_term_data(['slug' => 'feed'], 1, 'learn_field')['slug']);

// ------------------------------------------------------------ provider rules
$r = wp_insert_term('自社', 'omx_provider_type', ['slug' => 'own']);
check('provider: a fifth provider term is refused', $r instanceof WP_Error && 'omochix_provider_fixed' === $r->get_error_code());
check('provider: a child provider term is refused', wp_insert_term('x', 'omx_provider_type', ['slug' => 'official-2', 'parent' => ih_term('omx_provider_type', 'official')->term_id]) instanceof WP_Error);
check('provider: purpose terms can be added by editors (not a fixed vocabulary)', is_array(wp_insert_term('採用', 'omx_purpose', ['slug' => 'recruiting'])));

$pid = ih_add_post(['post_type' => 'learn_practice', 'post_name' => 'p1']);
check('provider: no term means unclassified, never official', 'unclassified' === omochix_core_get_provider_type($pid));
$old = ih_add_post(['post_type' => 'post', 'post_name' => 'old-news', 'post_author' => 1]);
check('provider: existing content written by an admin is still unclassified (nothing is inferred)', 'unclassified' === omochix_core_get_provider_type(get_post($old)));
wp_set_object_terms($pid, ['creator'], 'omx_provider_type');
check('provider: one term resolves to that provider', 'creator' === omochix_core_get_provider_type($pid));
wp_set_object_terms($pid, ['official', 'partner'], 'omx_provider_type');
check('provider: two terms are ambiguous → all removed (unclassified) and a notice is queued', 'unclassified' === omochix_core_get_provider_type($pid) && [] === $GLOBALS['post_terms'][$pid]['omx_provider_type'] && 1 === get_transient('omochix_core_provider_notice_' . $pid));
check('provider: resolving slugs is strict (unknown or duplicate lists are unclassified)', 'unclassified' === omochix_core_provider_type_from_slugs(['own']) && 'unclassified' === omochix_core_provider_type_from_slugs(['official', 'creator']) && 'official' === omochix_core_provider_type_from_slugs(['official']));
check('provider: labels, PR flag and link rel', '' === omochix_core_get_provider_label('unclassified') && 'クリエイター' === omochix_core_get_provider_label('creator') && omochix_core_provider_is_pr('partner') && !omochix_core_provider_is_pr('official') && 'sponsored noopener noreferrer' === omochix_core_provider_link_rel('partner') && 'noopener noreferrer' === omochix_core_provider_link_rel('creator'));

ih_as(['assign_terms']);
wp_set_object_terms($pid, ['partner'], 'omx_provider_type');
ob_start(); omochix_core_provider_meta_box(get_post($pid)); $box = ob_get_clean();
$partner_id = ih_term('omx_provider_type', 'partner')->term_id;
check('provider box: radio inputs post term IDs under tax_input, current value checked, 未分類 = 0', 5 === substr_count($box, 'type="radio"') && false !== strpos($box, 'name="tax_input[omx_provider_type][]" value="' . $partner_id . '"  checked="checked"') && false !== strpos($box, 'value="0"') && false !== strpos($box, '未分類'));
ih_as([]);
ob_start(); omochix_core_provider_meta_box(get_post($pid)); $box = ob_get_clean();
check('provider box: users who cannot assign terms see disabled inputs', 5 === substr_count($box, 'disabled="disabled"'));
$GLOBALS['terms']['omx_provider_type'][$partner_id]->name = '<script>alert(1)</script>';
ob_start(); omochix_core_provider_meta_box(get_post($pid)); $box = ob_get_clean();
check('provider box: labels come from the fixed vocabulary, never from a (tampered) term name', false === strpos($box, '<script>') && false !== strpos($box, 'パートナー（PR）'));

$_GET = ['post' => (string) $pid];
set_transient('omochix_core_provider_notice_' . $pid, 1);
ob_start(); omochix_core_provider_notice(); $notice = ob_get_clean();
check('provider notice: shown once after an ambiguous save', false !== strpos($notice, '未分類') && false === get_transient('omochix_core_provider_notice_' . $pid));
$_GET = [];

// ------------------------------------------------ multi-industry association
$tool = ih_add_post(['post_type' => 'ai_tool', 'post_name' => 'chatgpt']);
wp_set_object_terms($tool, ['real-estate', 'restaurant', 'web-production'], 'omx_industry');
wp_set_object_terms($tool, ['marketing', 'customer-support'], 'omx_purpose');
$industries = wp_list_pluck(get_the_terms($tool, 'omx_industry'), 'slug');
check('many-to-many: one tool carries three industries and two purposes', ['real-estate', 'restaurant', 'web-production'] === $industries && 2 === count(get_the_terms($tool, 'omx_purpose')));
$prompt = ih_add_post(['post_type' => 'prompt', 'post_name' => 'listing-copy']);
wp_set_object_terms($prompt, ['real-estate'], 'omx_industry');
$q = static fn($types, $slug) => wp_list_pluck(ih_query_posts(['post_type' => $types, 'posts_per_page' => -1, 'tax_query' => [['taxonomy' => 'omx_industry', 'field' => 'slug', 'terms' => [$slug]]]]), 'ID');
check('many-to-many: a real-estate query finds the tool and the prompt; restaurant only the tool', [$tool, $prompt] === $q(['ai_tool', 'prompt'], 'real-estate') && [$tool] === $q(['ai_tool', 'prompt'], 'restaurant'));
check('many-to-many: an industry with nothing tagged returns nothing', [] === $q(['ai_tool', 'prompt'], 'beauty'));
check('association: tagging does not touch post meta (no migration)', !isset($GLOBALS['meta'][$tool]) && !isset($GLOBALS['meta'][$prompt]));

// ------------------------------------------------------------- REST reads
$req = static fn($route) => new class($route) { private $r; function __construct($r) { $this->r = $r; } function get_route() { return $this->r; } };
ih_as([]);
foreach (['/wp/v2/industries', '/wp/v2/industries/101', '/wp/v2/business-purposes', '/wp/v2/provider-types'] as $route) {
    $r = omochix_core_industry_rest_read_guard(null, [], $req($route));
    check("rest: visitors cannot list $route in Phase 1", $r instanceof WP_Error && 'rest_forbidden' === $r->get_error_code());
}
check('rest: other routes are untouched (learn-fields, posts)', null === omochix_core_industry_rest_read_guard(null, [], $req('/wp/v2/learn-fields')) && null === omochix_core_industry_rest_read_guard(null, [], $req('/wp/v2/industries-x')));
ih_as(['edit_posts']);
check('rest: editors (block editor panels) can read them', null === omochix_core_industry_rest_read_guard(null, [], $req('/wp/v2/provider-types')));
ih_as([]);
add_filter('omochix_core_industry_public', '__return_true');
check('rest: once hubs are public, industries are readable, purposes and providers still not', null === omochix_core_industry_rest_read_guard(null, [], $req('/wp/v2/industries')) && omochix_core_industry_rest_read_guard(null, [], $req('/wp/v2/business-purposes')) instanceof WP_Error);
remove_filter('omochix_core_industry_public', '__return_true');
$earlier = new WP_Error('x', 'y');
check('rest: an earlier error is passed through', $earlier === omochix_core_industry_rest_read_guard($earlier, [], $req('/wp/v2/industries')));

ih_done();
