<?php
/**
 * Industry Hub Phase 1: the workflow data layer on learn_practice — meta,
 * format detection, the workflow publish guard (classic and REST), revision
 * detection, the edit panel, and non-interference with other practice items.
 * Run: php tests/industry-hub/workflow.php
 */
require __DIR__ . '/stub.php';

ih_fresh();
$reg = $GLOBALS['registered']['post_meta']['learn_practice'];
$wf_keys = ['workflow_problem', 'workflow_audience', 'workflow_cost', 'workflow_metrics', 'workflow_results', 'workflow_resource_ids'];

// ------------------------------------------------------------ registration
check('meta: six workflow keys on learn_practice with practice auth, own sanitizer, revisions', 6 === count(array_intersect_key($reg, array_flip($wf_keys))) && !array_filter(array_intersect_key($reg, array_flip($wf_keys)), static fn($a) => 'omochix_core_auth_registered_practice_meta' !== $a['auth_callback'] || 'omochix_core_sanitize_registered_workflow_meta' !== $a['sanitize_callback'] || true !== $a['revisions_enabled']));
check('meta: rows are typed objects in REST (no extra properties), resource IDs integers', 'object' === $reg['workflow_results']['show_in_rest']['schema']['items']['type'] && false === $reg['workflow_results']['show_in_rest']['schema']['items']['additionalProperties'] && ['integer', 'null'] === $reg['workflow_results']['show_in_rest']['schema']['items']['properties']['source_resource_id']['type'] && 'integer' === $reg['workflow_resource_ids']['show_in_rest']['schema']['items']['type']);
check('practice: the practice schema itself is unchanged (24 keys, same required list)', 24 === count(omochix_core_get_practice_meta_schema()) && ['practice_summary', 'practice_minutes', 'practice_outcome', 'practice_prompts', 'practice_steps', 'practice_verify', 'practice_safety', 'practice_verified_at', 'practice_sources'] === omochix_core_get_practice_required_meta() && !array_intersect($wf_keys, array_keys(omochix_core_get_practice_meta_schema())));
check('practice: the five format labels the theme filter uses are unchanged (no 導入する chip appears)', ['project' => '作る', 'lesson' => '学ぶ', 'demo' => '試す', 'reference' => '調べる', 'prompt' => '使う'] === omochix_core_get_practice_format_labels());
check('phase 1: the workflow format term is not created on boot (no new /learn/format/workflow/ archive)', null === ih_term('learn_format', 'workflow') && 5 === count($GLOBALS['terms']['learn_format']));
omochix_core_insert_workflow_format_term(); omochix_core_insert_workflow_format_term();
check('seed helper: creates 導入する once (for Phase 3)', '導入する' === ih_term('learn_format', 'workflow')->name && 6 === count($GLOBALS['terms']['learn_format']));

// ---------------------------------------------------------------- sanitize
$s   = 'omochix_core_sanitize_workflow_meta_value';
$res = ih_add_post(['post_type' => 'omx_resource', 'post_status' => 'publish']);
$tool = ih_add_post(['post_type' => 'ai_tool', 'post_name' => 'chatgpt']);
check('sanitize: unknown key → null; problem capped at 600 without markup', null === $s('x', 'workflow_secret') && 600 === mb_strlen($s(str_repeat('あ', 700), 'workflow_problem')) && false === strpos($s('<b>x</b>', 'workflow_problem'), '<'));
$cost = $s('[{"item":"Plus","amount":"3,000円","period":"weekly","checked_at":"2026-11-01","x":1},{"item":"API","amount":"従量","period":"usage","checked_at":"2026-13-01"}]', 'workflow_cost');
check('sanitize: cost period is an allow-list; dates validated; extra keys dropped', '' === $cost[0]['period'] && 'usage' === $cost[1]['period'] && '' === $cost[1]['checked_at'] && !isset($cost[0]['x']));
$r = $s([['metric' => 'm', 'value' => 'v', 'evidence' => 'guess', 'source_resource_id' => (string) $tool], ['metric' => 'm', 'evidence' => 'reported', 'source_resource_id' => (string) $res, 'source_url' => 'javascript:x']], 'workflow_results');
check('sanitize: evidence allow-list; source_resource_id must be a ledger entry; URLs http(s)', '' === $r[0]['evidence'] && null === $r[0]['source_resource_id'] && 'reported' === $r[1]['evidence'] && $res === $r[1]['source_resource_id'] && '' === $r[1]['source_url']);
check('sanitize: resource IDs keep only ledger entries', [$res] === $s("$tool, $res, $res", 'workflow_resource_ids'));

// ------------------------------------------------------------ detection
$p = ih_add_post(['post_type' => 'learn_practice', 'post_status' => 'draft', 'post_name' => 'wf']);
check('detect: a practice item without the workflow format is not a workflow', false === omochix_core_is_workflow($p));
wp_set_object_terms($p, ['workflow'], 'learn_format');
check('detect: stored format', true === omochix_core_is_workflow($p));
$_POST = ['tax_input' => ['learn_format' => '作る']];
check('detect: the classic request overrides the stored terms (names, comma separated)', false === omochix_core_is_workflow($p));
$_POST = ['tax_input' => ['learn_format' => '作る, 導入する']];
check('detect: classic names', true === omochix_core_is_workflow($p));
$_POST = [];
$wid = ih_term('learn_format', 'workflow')->term_id;
check('detect: REST term IDs under the rest_base', true === omochix_core_is_workflow(0, ['learn-formats' => [$wid]]) && false === omochix_core_is_workflow($p, ['learn-formats' => []]));

// -------------------------------------------------------------- blockers
$b = 'omochix_core_workflow_publish_blockers';
$good = [
    'workflow_problem'  => '物件写真の補正と紹介文の作成に毎回時間がかかる。',
    'workflow_audience' => [['role' => '賃貸仲介の担当者', 'note' => '']],
    'workflow_cost'     => [['item' => 'ChatGPT Plus', 'amount' => '3,000円', 'period' => 'month', 'note' => '参考', 'checked_at' => '2026-11-01']],
    'workflow_metrics'  => [['metric' => '1物件あたりの作業時間', 'how' => '導入前後2週間の記録を比べる', 'frequency' => '週1回']],
    'workflow_results'  => [],
    'workflow_resource_ids' => [],
];
check('blockers: a complete workflow passes', [] === $b($good, ['real-estate'], 'official', [$tool]));
$all = implode(' ', $b(array_fill_keys(array_keys($good), ''), [], 'unclassified', []));
foreach (['解決する業務課題', '対象業種', '想定利用者', '必要なAIツール', '費用', '効果測定方法', '提供元'] as $label) {
    check("blockers: missing $label is reported", false !== strpos($all, $label));
}
check('blockers: unclassified provider blocks (never treated as official)', [] !== $b($good, ['real-estate'], 'unclassified', [$tool]));
$c = $good; $c['workflow_cost'][0]['checked_at'] = '';
check('blockers: a cost without the date it was checked is refused', false !== strpos(implode(' ', $b($c, ['real-estate'], 'official', [$tool])), '確認した日付'));
$c = $good; $c['workflow_results'] = [['metric' => '作業時間', 'value' => '50%短縮', 'evidence' => '', 'source_url' => '', 'source_resource_id' => null, 'measured_at' => '', 'note' => '']];
check('results: a result without an evidence class is refused', false !== strpos(implode(' ', $b($c, ['real-estate'], 'official', [$tool])), '根拠の区分'));
$c['workflow_results'][0]['evidence'] = 'measured';
check('results: measured needs a date and the measuring conditions', false !== strpos(implode(' ', $b($c, ['real-estate'], 'official', [$tool])), '測定日'));
$c['workflow_results'][0]['measured_at'] = '2026-10-20'; $c['workflow_results'][0]['note'] = '自社3店舗、2週間、同一担当者';
check('results: measured with date and conditions passes', [] === $b($c, ['real-estate'], 'official', [$tool]));
$c['workflow_results'][0]['evidence'] = 'reported'; $c['workflow_results'][0]['measured_at'] = '';
check('results: reported needs a source and a check date', false !== strpos(implode(' ', $b($c, ['real-estate'], 'official', [$tool])), '出典と確認日'));
$c['workflow_results'][0]['source_resource_id'] = $res; $c['workflow_results'][0]['measured_at'] = '2026-11-01';
check('results: reported with a ledger source and date passes', [] === $b($c, ['real-estate'], 'official', [$tool]));
$c['workflow_results'][0] = ['metric' => '作業時間', 'value' => '必ず半分になります', 'evidence' => 'expected', 'source_url' => '', 'source_resource_id' => null, 'measured_at' => '', 'note' => ''];
check('results: an expected result may not promise ("必ず")', false !== strpos(implode(' ', $b($c, ['real-estate'], 'official', [$tool])), '断定表現'));
foreach (['確実に', '100%', '１００％', '誰でも'] as $w) { $c['workflow_results'][0]['value'] = "{$w}効果が出る"; check("results: expected with \"$w\" is refused", [] !== $b($c, ['real-estate'], 'official', [$tool])); }
$c['workflow_results'][0]['value'] = '作業時間が短くなる見込み（目安）';
check('results: a hedged expected result passes', [] === $b($c, ['real-estate'], 'official', [$tool]));
$c = $good; $c['workflow_problem'] = 'key: sk-' . str_repeat('x', 30);
check('blockers: a secret-looking string blocks', false !== strpos(implode(' ', $b($c, ['real-estate'], 'official', [$tool])), '秘密情報'));

// ------------------------------------------------------------- classic guard
foreach ($good as $k => $v) { if ($v) { update_post_meta($p, $k, $v); } }
$guard = static fn($id, $status = 'publish') => omochix_core_workflow_guard_insert_data(['post_type' => 'learn_practice', 'post_status' => $status], ['ID' => $id]);
check('guard: a workflow without industry, tools and provider is held as draft', 'draft' === $guard($p)['post_status'] && 'blocked' === get_transient('omochix_core_workflow_guard_' . $p)['type']);
wp_set_object_terms($p, ['real-estate', 'restaurant'], 'omx_industry');
wp_set_object_terms($p, ['official'], 'omx_provider_type');
update_post_meta($p, 'related_tool_ids', [$tool]);
delete_transient('omochix_core_workflow_guard_' . $p);
check('guard: complete (two industries, one tool, official) → publish allowed, no notice', 'publish' === $guard($p)['post_status'] && false === get_transient('omochix_core_workflow_guard_' . $p));
$_POST = ['omochix_core_workflow_meta' => ['workflow_problem' => ''], 'omochix_core_workflow_nonce' => 'x'];
check('guard: values submitted in the same request count (emptied problem → held)', 'draft' === $guard($p)['post_status']);
$_POST = ['tax_input' => ['omx_provider_type' => ['0']]];
check('guard: choosing 未分類 in the same request is held', 'draft' === $guard($p)['post_status']);
$_POST = ['omochix_core_practice_relation_fields' => ['related_tool_ids'], 'omochix_core_practice_meta' => ['related_tool_ids' => []]];
check('guard: removing every tool in the same request is held', 'draft' === $guard($p)['post_status']);
$_POST = [];
$plain = ih_add_post(['post_type' => 'learn_practice', 'post_status' => 'draft']);
wp_set_object_terms($plain, ['project'], 'learn_format');
check('guard: a non-workflow practice item with no workflow fields is not touched', 'publish' === $guard($plain)['post_status'] && false === get_transient('omochix_core_workflow_guard_' . $plain));
check('guard: drafts, other post types and earlier demotions pass through', 'draft' === $guard($p, 'draft')['post_status'] && 'publish' === omochix_core_workflow_guard_insert_data(['post_type' => 'post', 'post_status' => 'publish'], ['ID' => $p])['post_status']);
check('guard: runs after the practice guard on the same filter', 10 === has_filter('wp_insert_post_data', 'omochix_core_practice_guard_insert_data') && 11 === has_filter('wp_insert_post_data', 'omochix_core_workflow_guard_insert_data'));
$live = ih_add_post(['post_type' => 'learn_practice', 'post_status' => 'publish']);
wp_set_object_terms($live, ['workflow'], 'learn_format');
check('guard: a live workflow is never demoted (warning)', 'publish' === $guard($live)['post_status'] && 'warn' === get_transient('omochix_core_workflow_guard_' . $live)['type']);

// ------------------------------------------------------------------ REST
$prepared = (object) ['ID' => $p, 'post_status' => 'publish'];
check('rest: a complete workflow passes', $prepared === omochix_core_workflow_guard_rest($prepared, ['meta' => []]));
$err = omochix_core_workflow_guard_rest($prepared, ['meta' => ['workflow_results' => [['metric' => 'x', 'value' => '絶対に売上が上がる', 'evidence' => 'expected']]]]);
check('rest: submitted meta is checked and refused with a 400 WP_Error', $err instanceof WP_Error && 'omochix_workflow_blocked' === $err->get_error_code() && 400 === $err->data['status'] && false !== strpos($err->get_error_message(), '断定表現'));
$pid_ind = ih_term('omx_industry', 'beauty')->term_id;
check('rest: submitted taxonomy IDs are used (empty industries → refused)', omochix_core_workflow_guard_rest($prepared, ['industries' => []]) instanceof WP_Error && $prepared === omochix_core_workflow_guard_rest($prepared, ['industries' => [$pid_ind]]));
$earlier = new WP_Error('omochix_practice_blocked', 'practice');
check('rest: an earlier practice-guard error is passed through unchanged', $earlier === omochix_core_workflow_guard_rest($earlier, []));
check('rest: a live item is not re-checked', (object) ['ID' => $live, 'post_status' => 'publish'] == omochix_core_workflow_guard_rest((object) ['ID' => $live, 'post_status' => 'publish'], ['industries' => []]));
check('rest: registered after the practice REST guard', 10 === has_filter('rest_pre_insert_learn_practice', 'omochix_core_practice_guard_rest') && 11 === has_filter('rest_pre_insert_learn_practice', 'omochix_core_workflow_guard_rest'));

// ----------------------------------------------------------------- revisions
$rev = ih_add_post(['post_type' => 'revision', 'post_parent' => $p]);
foreach (omochix_core_get_workflow_meta_schema() as $k => $f) { $v = get_post_meta($p, $k, true); if ('' !== $v) { update_post_meta($rev, $k, $v); } }
check('revisions: identical workflow meta → no change', false === omochix_core_workflow_revision_has_changed(false, get_post($rev), get_post($p)));
update_post_meta($p, 'workflow_problem', '変更後の課題');
check('revisions: a workflow-only edit counts as a change (gets a revision)', true === omochix_core_workflow_revision_has_changed(false, get_post($rev), get_post($p)));
check('revisions: never turns "changed" into "unchanged"; other post types untouched', true === omochix_core_workflow_revision_has_changed(true, get_post($rev), get_post($p)) && false === omochix_core_workflow_revision_has_changed(false, get_post($rev), new WP_Post(['ID' => 999, 'post_type' => 'post'])));

// ------------------------------------------------------------------ panel
$q = ih_add_post(['post_type' => 'learn_practice', 'post_status' => 'draft']);
$_POST = ['omochix_core_workflow_meta' => ['workflow_problem' => '課題A', 'workflow_cost' => '[{"item":"x","amount":"0円","period":"once"}]']];
ih_as(['edit_post']);
omochix_core_save_workflow_meta($q);
check('save: no nonce → nothing written', [] === get_post_meta($q));
$_POST['omochix_core_workflow_nonce'] = 'nonce-omochix_core_save_workflow';
ih_as([]);
omochix_core_save_workflow_meta($q);
check('save: no edit_post → nothing written', [] === get_post_meta($q));
ih_as(['edit_post']);
omochix_core_save_workflow_meta($q);
check('save: valid request writes sanitized values', '課題A' === get_post_meta($q, 'workflow_problem', true) && 'once' === get_post_meta($q, 'workflow_cost', true)[0]['period']);
$_POST['omochix_core_workflow_meta'] = ['workflow_cost' => '[{"item":', 'workflow_problem' => 'token = "' . str_repeat('Z', 30) . '"'];
omochix_core_save_workflow_meta($q);
check('save: malformed JSON and a secret keep the stored values; a notice lists both', 'once' === get_post_meta($q, 'workflow_cost', true)[0]['period'] && '課題A' === get_post_meta($q, 'workflow_problem', true) && ['workflow_problem', 'workflow_cost'] === get_transient('omochix_core_workflow_save_' . $q));
check('save: runs after the practice save handler', 20 === has_action('save_post_learn_practice', 'omochix_core_save_workflow_meta'));
$_POST = [];
update_post_meta($q, 'workflow_problem', '</textarea><script>alert(1)</script>');
ob_start(); omochix_core_render_workflow_meta_box(get_post($q)); $box = ob_get_clean();
check('panel: nonce printed, six fields, values escaped', false !== strpos($box, 'value="nonce-omochix_core_save_workflow"') && 6 === substr_count($box, 'name="omochix_core_workflow_meta[') && false === strpos($box, '<script>'));
$_GET = ['post' => (string) $q];
ob_start(); omochix_core_workflow_save_notice(); $n = ob_get_clean();
check('notice: shown once', false !== strpos($n, 'workflow_cost') && false === get_transient('omochix_core_workflow_save_' . $q));
$_GET = [];

ih_done();
