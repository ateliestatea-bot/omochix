<?php
/**
 * Industry Hub Phase 2 (theme): query validation (§4.2) as a table, the
 * raw query-string parser, and URL building.
 * Run: php tests/industry-hub/theme-validate.php
 */
require __DIR__ . '/stub.php';
if (!function_exists('get_page_by_path')) { function get_page_by_path($p, $o = null, $t = 'page') { return null; } }
if (!function_exists('is_page')) { function is_page($p = '') { return false; } }
if (!function_exists('wp_robots_no_robots')) { function wp_robots_no_robots($r) { $r['noindex'] = true; $r['follow'] = true; return $r; } }
require __DIR__ . '/../../wp-theme/omochix-theme/inc/learn-industries.php';

ih_fresh();
$re  = ih_term('omx_industry', 'real-estate');
$rs  = ih_term('omx_industry', 'restaurant');
$sub = get_term(wp_insert_term('賃貸管理', 'omx_industry', ['slug' => 'rental', 'parent' => $re->term_id])['term_id'], 'omx_industry');
wp_insert_term('居酒屋', 'omx_industry', ['slug' => 'izakaya', 'parent' => $rs->term_id]);
$v = static fn($qs, $ctx = 'hub', $t = null) => omochix_industry_hub_validate($qs, $ctx, $t ?? $re);

// parser
$p = omochix_industry_hub_parse_query_string('purpose=a&purpose%5B%5D=b&q=%E4%B8%8D+%E5%8B%95&x=1&x=2&flag');
check('parser: decodes keys and values, + is a space, key[] is an array, repeats are kept, bare keys are ""', ['a', 'b'] === $p['purpose']['values'] && true === $p['purpose']['array'] && ['不 動'] === $p['q']['values'] && ['1', '2'] === $p['x']['values'] && [''] === $p['flag']['values']);

// table: [query, ok?, expected filter or error]
$rows = [
    ['', true, []],
    ['purpose=marketing', true, ['purpose' => 'marketing']],
    ['purpose=', true, ['purpose' => '']],
    ['purpose=nope', false, ['purpose' => 'unknown']],
    ['purpose=MARKETING', true, ['purpose' => 'marketing']],
    ['purpose=%E3%81%82', false, ['purpose' => 'unknown']],
    ['purpose[]=marketing', false, ['purpose' => 'array']],
    ['purpose=marketing&purpose=document', false, ['purpose' => 'duplicate']],
    ['type=workflow', true, ['type' => 'workflow']],
    ['type=tool', true, ['type' => 'tool']],
    ['type=video', false, ['type' => 'unknown']],
    ['level=advanced', true, ['level' => 'advanced']],
    ['level=expert', false, ['level' => 'unknown']],
    ['provider=official', true, ['provider' => 'official']],
    ['provider=unclassified', false, ['provider' => 'unknown']],
    ['provider=partner', true, ['provider' => 'partner']],
    ['sub=rental', true, ['sub' => 'rental']],
    ['sub=izakaya', false, ['sub' => 'unknown']],
    ['sub=real-estate', false, ['sub' => 'unknown']],
    ['q=' . rawurlencode(str_repeat('あ', 60)), true, ['q' => str_repeat('あ', 60)]],
    ['q=' . rawurlencode(str_repeat('あ', 61)), false, ['q' => 'too_long']],
    ['q=%20%20%20', true, ['q' => '']],
    ['q=%3Cscript%3Ealert(1)%3C%2Fscript%3E', true, ['q' => 'alert(1)']],
    ['utm_source=x&gclid=y&fbclid=z', true, []],
    ['purpose=marketing&utm_source=news', true, ['purpose' => 'marketing']],
];
foreach ($rows as [$qs, $ok, $expect]) {
    $r = $v($qs);
    $good = $ok === $r['ok'];
    foreach ($expect as $k => $val) { $good = $good && ($ok ? $val === $r['filters'][$k] : $val === ($r['errors'][$k] ?? null)); }
    check("validate hub: \"$qs\" → " . ($ok ? '200' : '400'), $good);
}
check('validate: unrelated keys are passed through (for the 302), but not paged / omx_industry / arrays', ['utm_source' => 'x', 'gclid' => 'y'] === $v('utm_source=x&gclid=y&paged=2&omx_industry=a&arr[]=1')['passthrough']);
check('validate: the hub context has no "industry" key (ignored, not 400)', true === $v('industry=foo')['ok']);

// index context
check('validate index: industry must be a visible top-level industry', false === omochix_industry_hub_validate('industry=real-estate', 'index')['ok'] && false === omochix_industry_hub_validate('industry=rental', 'index')['ok']);
ih_fill_hub($re->term_id); ih_add_workflow('real-estate'); omochix_core_gate_new_request(); omochix_core_gate_invalidate();
check('validate index: a visible industry is accepted', true === omochix_industry_hub_validate('industry=real-estate&purpose=marketing', 'index')['ok']);
check('validate index: hub-only keys are ignored on the index (sub, level)', true === omochix_industry_hub_validate('sub=rental&level=nope', 'index')['ok']);

// helpers
check('filtered: empty filters are not "filtered"', false === omochix_industry_hub_is_filtered(['q' => '', 'purpose' => '']) && true === omochix_industry_hub_is_filtered(['q' => 'x']));
check('url: filters are appended, empty ones dropped, extra (passthrough) kept', 'https://example.test/learn/industries/real-estate/?purpose=marketing&utm_source=x' === omochix_industry_hub_url($re, ['purpose' => 'marketing', 'q' => ''], ['utm_source' => 'x']));
check('url: no index page → empty string (index not published)', '' === omochix_industry_hub_url(null, ['q' => 'x']));
check('challenge chip: purpose → filtered #workflows, none → unfiltered #workflows', 'https://example.test/learn/industries/real-estate/?purpose=document#workflows' === omochix_industry_hub_challenge_url($re, ['purpose' => 'document']) && 'https://example.test/learn/industries/real-estate/#workflows' === omochix_industry_hub_challenge_url($re, ['purpose' => '']));
check('types and levels vocabularies', ['workflow', 'practice', 'tool', 'prompt', 'learn', 'news'] === array_keys(omochix_industry_hub_types()) && ['beginner', 'intermediate', 'advanced'] === array_keys(omochix_industry_hub_levels()));
check('public switch: on only with the Phase 2 plugin', true === omochix_industry_hub_public_switch(false));
check('case redirect: ASCII case only, never for percent-encoded slugs (no loop)', true === omochix_industry_hub_needs_case_redirect('Real-Estate', 'real-estate') && false === omochix_industry_hub_needs_case_redirect('real-estate', 'real-estate') && false === omochix_industry_hub_needs_case_redirect('%E7%BE%8E', '%e7%be%8e') && false === omochix_industry_hub_needs_case_redirect('%e7%be%8e', '%e7%be%8e') && false === omochix_industry_hub_needs_case_redirect('', 'x'));

ih_done();
