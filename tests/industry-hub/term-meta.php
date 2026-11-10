<?php
/**
 * Industry Hub Phase 1: industry term meta, admin fields (nonce, capability,
 * sanitize, escape), Motion slug references and the hub content gate.
 * Run: php tests/industry-hub/term-meta.php
 */
require __DIR__ . '/stub.php';

ih_fresh();
$re  = ih_term('omx_industry', 'real-estate')->term_id;
$reg = $GLOBALS['registered']['term_meta']['omx_industry'] ?? [];

// ------------------------------------------------------------ registration
check('register: 12 industry term meta keys, single, not in REST, with sanitize and auth callbacks', 12 === count($reg) && !array_filter($reg, static fn($a) => true !== $a['single'] || false !== $a['show_in_rest'] || 'omochix_core_sanitize_registered_industry_meta' !== $a['sanitize_callback'] || 'omochix_core_auth_registered_industry_meta' !== $a['auth_callback']));
check('register: term meta only on omx_industry (no other taxonomy gets meta)', ['omx_industry'] === array_keys($GLOBALS['registered']['term_meta']));
ih_as([]);
check('auth: a user who cannot edit the term cannot write meta', false === omochix_core_auth_registered_industry_meta(true, 'industry_lead', $re));
ih_as(['edit_term']);
check('auth: a user with edit_term can', true === omochix_core_auth_registered_industry_meta(false, 'industry_lead', $re));

// ---------------------------------------------------------------- sanitize
$s = 'omochix_core_sanitize_industry_meta_value';
check('sanitize: unknown keys are rejected (null)', null === $s('x', 'industry_secret'));
check('sanitize: hub_ready accepts only true-ish values', true === $s('1', 'industry_hub_ready') && true === $s('on', 'industry_hub_ready') && false === $s('0', 'industry_hub_ready') && false === $s('yes please', 'industry_hub_ready') && false === $s(['1'], 'industry_hub_ready'));
check('sanitize: icon is an allow-list (fallback to none)', 'building' === $s('building', 'industry_icon') && '' === $s('../../wp-config', 'industry_icon') && '' === $s(['x'], 'industry_icon'));
check('sanitize: lead strips markup', false === strpos($s('<b>a</b><script>alert(1)</script>', 'industry_lead'), '<'));
check('sanitize: lead cap', 600 === mb_strlen($s(str_repeat('あ', 900), 'industry_lead')));
check('sanitize: order is a non-negative integer', 0 === $s(-3, 'industry_order') && 7 === $s('7', 'industry_order') && 0 === $s('x', 'industry_order'));
check('sanitize: verified date must be a real Y-m-d', '2026-11-01' === $s('2026-11-01', 'industry_verified_at') && '' === $s('2026-02-30', 'industry_verified_at') && '' === $s(['2026-11-01'], 'industry_verified_at'));
$tool  = ih_add_post(['post_type' => 'ai_tool', 'post_name' => 'claude', 'post_title' => 'Claude']);
$tool2 = ih_add_post(['post_type' => 'ai_tool', 'post_name' => 'gemini', 'post_title' => 'Gemini']);
$prm   = ih_add_post(['post_type' => 'prompt', 'post_name' => 'p']);
$prac  = ih_add_post(['post_type' => 'learn_practice', 'post_name' => 'wf']);
check('sanitize: tool IDs keep only ai_tool posts, dedupe, accept text input', [$tool, $tool2] === $s("$tool, $prm, x, $tool2, $tool, 0, -5", 'industry_tool_ids'));
check('sanitize: featured IDs accept practice and pages, not tools', [$prac] === $s([$prac, $tool, '99999'], 'industry_featured_ids'));
check('sanitize: tool IDs capped at 12', 12 === count($s(array_map(static fn($i) => ih_add_post(['post_type' => 'ai_tool', 'post_name' => "t$i"]), range(1, 15)), 'industry_tool_ids')));
check('sanitize: motion slugs are normalised, invalid ones dropped, max 6', ['pop-in', 'toast'] === $s('pop-in, Toast, ***, pop-in, あ', 'industry_motion_slugs') && 6 === count($s(['a', 'b', 'c', 'd', 'e', 'f', 'g'], 'industry_motion_slugs')));
$rows = $s('[{"title":"<b>集客</b>","body":"本文","purpose":"marketing","evil":"x"},{"title":"t","body":"b","purpose":"nope"},"junk",{}]', 'industry_usecases');
check('sanitize: use-case rows drop unknown keys, strip tags and keep only existing purposes', [['title' => '集客', 'body' => '本文', 'purpose' => 'marketing'], ['title' => 't', 'body' => 'b', 'purpose' => '']] === $rows);
check('sanitize: source URLs must be http(s)', [['label' => 'a', 'url' => ''], ['label' => 'b', 'url' => 'https://example.com/x']] === $s([['label' => 'a', 'url' => 'javascript:alert(1)'], ['label' => 'b', 'url' => 'https://example.com/x']], 'industry_sources'));

// --------------------------------------------------------------- save form
$post_form = static function ($fields, $nonce = null, $term_id = 0) {
    $_POST = ['omochix_core_industry_meta' => $fields];
    if (false !== $nonce) { $_POST['omochix_core_industry_nonce'] = $nonce ?? 'nonce-omochix_core_save_industry_' . $term_id; }
};
ih_as(['edit_term']);
$post_form(['industry_lead' => 'リード'], false, $re);
omochix_core_save_industry_fields($re);
check('save: without our nonce field nothing is written (quick edit, REST, code)', '' === get_term_meta($re, 'industry_lead', true));
$post_form(['industry_lead' => 'リード'], 'nonce-omochix_core_save_industry_999', $re);
omochix_core_save_industry_fields($re);
check('save: a nonce for another term is refused', '' === get_term_meta($re, 'industry_lead', true));
ih_as([]);
$post_form(['industry_lead' => 'リード'], null, $re);
omochix_core_save_industry_fields($re);
check('save: a valid nonce without edit_term is refused', '' === get_term_meta($re, 'industry_lead', true));
ih_as(['edit_term']);
$post_form(['industry_lead' => '<em>不動産</em>の導入文', 'industry_hub_ready' => '1', 'industry_tool_ids' => "$tool, $prm", 'industry_challenges' => '[{"title":"T","body":"B"}]'], null, $re);
omochix_core_save_industry_fields($re);
check('save: valid request writes sanitized values', '不動産の導入文' === get_term_meta($re, 'industry_lead', true) && true === get_term_meta($re, 'industry_hub_ready', true) && [$tool] === get_term_meta($re, 'industry_tool_ids', true) && [['title' => 'T', 'body' => 'B', 'purpose' => '']] === get_term_meta($re, 'industry_challenges', true));
$post_form(['industry_challenges' => '[{"title":"broken"', 'industry_lead' => '新しい導入文'], null, $re);
omochix_core_save_industry_fields($re);
check('save: malformed JSON keeps the stored rows, other fields still save, a notice is queued', [['title' => 'T', 'body' => 'B', 'purpose' => '']] === get_term_meta($re, 'industry_challenges', true) && '新しい導入文' === get_term_meta($re, 'industry_lead', true) && is_array(get_transient('omochix_core_industry_notice_1')));
check('save: the unchecked hub_ready box (not posted) clears the flag', '' === get_term_meta($re, 'industry_hub_ready', true));
$post_form(['industry_lead' => 'api_key = "' . str_repeat('A', 30) . '"'], null, $re);
omochix_core_save_industry_fields($re);
check('save: a secret-looking string never reaches the database', '新しい導入文' === get_term_meta($re, 'industry_lead', true));
$post_form(['industry_lead' => ''], null, $re);
omochix_core_save_industry_fields($re);
check('save: an emptied field is deleted, not stored as ""', !array_key_exists('industry_lead', $GLOBALS['term_meta'][$re]));
$sub = wp_insert_term('賃貸管理', 'omx_industry', ['slug' => 'rental', 'parent' => $re])['term_id'];
$post_form(['industry_lead' => 'サブ'], null, $sub);
omochix_core_save_industry_fields($sub);
check('save: sub-industries carry no hub meta', [] === get_term_meta($sub));
check('notice: queued problems are shown once and escaped', (static function () { set_transient('omochix_core_industry_notice_1', ['<img src=x onerror=alert(1)>']); ob_start(); omochix_core_industry_notice(); $o = ob_get_clean(); return false === strpos($o, '<img') && false !== strpos($o, '&lt;img') && false === get_transient('omochix_core_industry_notice_1'); })());
$_POST = [];

// --------------------------------------------------------------- edit form
update_term_meta($re, 'industry_lead', '</textarea><script>alert(1)</script>');
update_term_meta($re, 'industry_sources', [['label' => '"><svg onload=alert(1)>', 'url' => 'https://example.com']]);
ob_start(); omochix_core_industry_edit_fields(get_term($re, 'omx_industry')); $form = ob_get_clean();
check('form: our nonce for this term is printed', false !== strpos($form, 'name="omochix_core_industry_nonce" value="nonce-omochix_core_save_industry_' . $re . '"'));
check('form: stored values are escaped (no textarea break-out, no markup injection)', false === strpos($form, '<script>') && false === strpos($form, '<svg') && false === strpos($form, '</textarea><script'));
check('form: every schema field is rendered with a label', 12 === substr_count($form, 'name="omochix_core_industry_meta['));
check('form: the content gate is listed for editors', false !== strpos($form, '検索エンジン登録の条件') && false !== strpos($form, '導入文'));
ob_start(); omochix_core_industry_edit_fields(get_term($sub, 'omx_industry')); $subform = ob_get_clean();
check('form: a sub-industry shows a note instead of hub fields', false === strpos($subform, 'omochix_core_industry_meta[') && false !== strpos($subform, 'サブ業種'));
delete_term_meta($re, 'industry_lead'); delete_term_meta($re, 'industry_sources');

// --------------------------------------------------------- Motion references
update_term_meta($re, 'industry_motion_slugs', ['pop-in', 'no-such-motion', 'toast']);
check('motion: without the theme catalog reader nothing is returned (fail closed)', [] === omochix_core_filter_known_motion_slugs(['pop-in']));
$catalog = json_decode((string) file_get_contents(__DIR__ . '/../../wp-theme/omochix-theme/assets/data/motion/catalog.json'), true);
if (!function_exists('omochix_learn_gallery_get_item')) { function omochix_learn_gallery_get_item($slug) { foreach ($GLOBALS['ih_catalog']['items'] as $i) { if ($i['slug'] === $slug) { return $i; } } return null; } } // conditional: declared only from here on
$GLOBALS['ih_catalog'] = $catalog;
$known = omochix_core_filter_known_motion_slugs(get_term_meta($re, 'industry_motion_slugs', true));
check('motion: only slugs present in catalog.json are used; unknown ones dropped', in_array('pop-in', $known, true) && !in_array('no-such-motion', $known, true));
$all = array_column($catalog['items'], 'slug');
check('motion: every one of the 53 catalog slugs survives the industry slug sanitizer unchanged', 53 === count($all) && $all === array_merge(...array_map(static fn($c) => omochix_core_sanitize_industry_meta_value($c, 'industry_motion_slugs'), array_chunk($all, 6))));
ob_start(); omochix_core_industry_edit_fields(get_term($re, 'omx_industry')); $form = ob_get_clean();
check('motion: the editor is warned about slugs missing from catalog.json', false !== strpos($form, 'no-such-motion') && false !== strpos($form, 'catalog.json に無いslug'));
delete_term_meta($re, 'industry_motion_slugs');

// --------------------------------------------------------------- content gate
$now  = strtotime('2026-11-05 00:00:00 UTC');
$gate = omochix_core_industry_hub_gate($re, $now);
check('gate: an empty hub is not ready and lists all seven conditions', false === $gate['ready'] && ['G1_editor_ready', 'G2_lead', 'G3_challenges', 'G4_usecases', 'G5_official_workflow', 'G6_fresh', 'G7_sources'] === $gate['unmet']);
$body = str_repeat('業務の流れを具体的に説明する本文。', 6); // > 80 characters
update_term_meta($re, 'industry_hub_ready', true);
update_term_meta($re, 'industry_lead', str_repeat('不動産業のAI活用を独自に解説する導入文です。', 6));
update_term_meta($re, 'industry_challenges', array_fill(0, 3, ['title' => '課題', 'body' => $body]));
update_term_meta($re, 'industry_usecases', [['title' => 'a', 'body' => $body, 'purpose' => 'marketing'], ['title' => 'b', 'body' => $body, 'purpose' => 'document'], ['title' => 'c', 'body' => $body, 'purpose' => 'marketing']]);
update_term_meta($re, 'industry_verified_at', '2026-10-01');
update_term_meta($re, 'industry_sources', [['label' => '国交省', 'url' => 'https://www.mlit.go.jp/']]);
update_term_meta($re, 'industry_tool_ids', [$tool, $tool2]);
check('gate: written content alone is not enough without an official workflow', ['G5_official_workflow'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
// The workflow format term is seeded by the workflow module; seed it here so this file stands alone.
ih_as(['manage_categories']); // only editors may create the workflow format
if (!ih_term('learn_format', 'workflow')) { wp_insert_term('導入する', 'learn_format', ['slug' => 'workflow']); }
ih_as(['edit_others_posts']); // only editors may mark content official
$fill_wf = static function ($id) use ($tool) { foreach (['workflow_problem' => '課題', 'workflow_audience' => [['role' => '担当者', 'note' => '']], 'workflow_cost' => [['item' => 'x', 'amount' => '0円', 'period' => 'once', 'note' => '', 'checked_at' => '2026-11-01']], 'workflow_metrics' => [['metric' => 'm', 'how' => 'h', 'frequency' => '']], 'related_tool_ids' => [$tool]] as $k => $v) { update_post_meta($id, $k, $v); } };
$wf = ih_add_post(['post_type' => 'learn_practice', 'post_name' => 'wf-creator', 'post_status' => 'publish']);
wp_set_object_terms($wf, ['real-estate'], 'omx_industry'); wp_set_object_terms($wf, ['workflow'], 'learn_format'); wp_set_object_terms($wf, ['creator'], 'omx_provider_type');
check('gate: a workflow by a creator does not count as OmochiX content', ['G5_official_workflow'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
$wf2 = ih_add_post(['post_type' => 'learn_practice', 'post_name' => 'wf-official', 'post_status' => 'draft']);
wp_set_object_terms($wf2, ['real-estate'], 'omx_industry'); wp_set_object_terms($wf2, ['workflow'], 'learn_format'); wp_set_object_terms($wf2, ['official'], 'omx_provider_type');
check('gate: an unpublished official workflow does not count', ['G5_official_workflow'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
$GLOBALS['posts'][$wf2]->post_status = 'publish';
check('gate: a published official workflow that does not meet the workflow rules does not count', ['G5_official_workflow'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
$fill_wf($wf2);
check('gate: all seven met → ready', true === omochix_core_industry_hub_gate($re, $now)['ready']);
$GLOBALS['posts'][$wf2]->post_password = 'secret';
check('gate: a password-protected workflow does not count', ['G5_official_workflow'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
$GLOBALS['posts'][$wf2]->post_password = '';
$wf3 = ih_add_post(['post_type' => 'learn_practice', 'post_name' => 'wf-sub', 'post_status' => 'publish']);
wp_set_object_terms($wf2, ['restaurant'], 'omx_industry');
wp_set_object_terms($wf3, ['rental'], 'omx_industry'); $fill_wf($wf3); wp_set_object_terms($wf3, ['workflow'], 'learn_format'); wp_set_object_terms($wf3, ['official'], 'omx_provider_type');
check('gate: an official workflow tagged with a sub-industry counts for the parent (include_children)', true === omochix_core_industry_hub_gate($re, $now)['ready'] && true === $GLOBALS['last_query']['tax_query'][0]['include_children']);
check('gate: freshness boundary (180 days ok, 181 days stale)', true === omochix_core_industry_hub_gate($re, strtotime('2026-10-01 00:00:00 UTC') + 180 * DAY_IN_SECONDS)['ready'] && in_array('G6_fresh', omochix_core_industry_hub_gate($re, strtotime('2026-10-01 00:00:00 UTC') + 181 * DAY_IN_SECONDS)['unmet'], true));
update_term_meta($re, 'industry_lead', str_repeat('あ', 119));
check('gate: lead boundary (119 characters is not enough)', ['G2_lead'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
update_term_meta($re, 'industry_lead', str_repeat('あ', 120));
update_term_meta($re, 'industry_usecases', [['title' => 'a', 'body' => $body, 'purpose' => 'marketing'], ['title' => 'b', 'body' => $body, 'purpose' => 'marketing'], ['title' => 'c', 'body' => $body, 'purpose' => 'marketing']]);
check('gate: use cases must span at least two business purposes', ['G4_usecases'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
update_term_meta($re, 'industry_usecases', [['title' => 'a', 'body' => 'short', 'purpose' => 'marketing'], ['title' => 'b', 'body' => $body, 'purpose' => 'document'], ['title' => 'c', 'body' => $body, 'purpose' => 'sales']]);
check('gate: short use-case bodies do not count (written content, not placeholders)', ['G4_usecases'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
update_term_meta($re, 'industry_usecases', [['title' => 'a', 'body' => $body, 'purpose' => 'marketing'], ['title' => 'b', 'body' => $body, 'purpose' => 'document'], ['title' => 'c', 'body' => $body, 'purpose' => 'sales']]);
update_term_meta($re, 'industry_tool_ids', []);
check('gate: curated tool cards do not count either way (removing them keeps the hub ready)', true === omochix_core_industry_hub_gate($re, $now)['ready']);
$GLOBALS['post_terms'][$wf3]['omx_provider_type'][] = ih_term('omx_provider_type', 'creator')->term_id; // two providers, written around the API
check('gate: an official workflow that also carries a second provider is ambiguous and does not count', ['G5_official_workflow'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
$GLOBALS['post_terms'][$wf3]['omx_provider_type'] = [ih_term('omx_provider_type', 'official')->term_id];
update_term_meta($re, 'industry_hub_ready', false);
check('gate: without the editor flag the hub is never ready', ['G1_editor_ready'] === omochix_core_industry_hub_gate($re, $now)['unmet']);
check('gate: a sub-industry is never a hub', ['not_top_level'] === omochix_core_industry_hub_gate($sub, $now)['unmet'] && ['missing_term'] === omochix_core_industry_hub_gate(424242, $now)['unmet']);
$GLOBALS['term_meta'][$re]['industry_lead'] = ['not', 'a', 'string']; $GLOBALS['omochix_core_industry_meta_memo'] = []; // written around the API
check('read: a tampered stored value is re-sanitized on read', '' === omochix_core_get_industry_meta($re)['industry_lead']);

ih_done();
