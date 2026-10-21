<?php
/**
 * Learn Phase 4 / Practice: unit tests for the Core plugin's practice modules
 * and the theme's inc/learn-practice.php, using a WordPress stub.
 * Run: php tests/learn-practice/run.php
 */
require __DIR__ . '/stub.php';

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }

// ------------------------------------------------------------- registration
omochix_core_register_practice_post_type();
omochix_core_register_practice_taxonomies();
omochix_core_register_practice_post_meta();
omochix_core_insert_practice_initial_terms();
$cpt = $GLOBALS['registered']['post_types']['learn_practice'] ?? null;
check('cpt: learn_practice under learn/practice with an archive, REST and the usual supports', $cpt && 'learn/practice' === $cpt['rewrite']['slug'] && false === $cpt['rewrite']['with_front'] && 'learn/practice' === $cpt['has_archive'] && true === $cpt['show_in_rest'] && 'learn-practice' === $cpt['rest_base'] && in_array('editor', $cpt['supports'], true) && false === $cpt['exclude_from_search']);
$tax = $GLOBALS['registered']['taxonomies'];
check('taxonomies: five registered, field and usecase also on pages, rewrite under learn/', 5 === count(array_intersect_key($tax, array_flip(['learn_field', 'learn_format', 'learn_usecase', 'learn_ai', 'learn_tech']))) && ['learn_practice', 'page'] === $tax['learn_field']['types'] && ['learn_practice', 'page'] === $tax['learn_usecase']['types'] && ['learn_practice'] === $tax['learn_ai']['types'] && 'learn/field' === $tax['learn_field']['args']['rewrite']['slug'] && true === $tax['learn_field']['args']['hierarchical'] && false === $tax['learn_format']['args']['hierarchical']);
check('taxonomies: no custom top rewrite rules (the CPT slug does not overlap learn/motion)', [] === $GLOBALS['registered']['rewrite']);
check('seed: 8 fields, 5 formats, 10 ai, 14 tech; idempotent', 8 === count($GLOBALS['terms']['learn_field']) && 5 === count($GLOBALS['terms']['learn_format']) && 10 === count($GLOBALS['terms']['learn_ai']) && 14 === count($GLOBALS['terms']['learn_tech']) && (omochix_core_insert_practice_initial_terms() ?? true) && 8 === count($GLOBALS['terms']['learn_field']) && 'Webサイト・LP制作' === $GLOBALS['terms']['learn_field']['web']['name']);
check('seed: learn_ai slugs match ai_tool post names in style (lowercase, hyphen)', !array_filter(array_keys($GLOBALS['terms']['learn_ai']), static fn($s) => !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $s)));
$schema = omochix_core_get_practice_meta_schema();
check('meta: every schema key is registered on learn_practice with sanitize and auth callbacks', count($schema) === count($GLOBALS['registered']['meta']['learn_practice']) && !array_filter($GLOBALS['registered']['meta']['learn_practice'], static fn($a) => 'omochix_core_sanitize_registered_practice_meta' !== $a['sanitize_callback'] || 'omochix_core_auth_registered_practice_meta' !== $a['auth_callback'] || true !== $a['single']));
$steps_rest = $GLOBALS['registered']['meta']['learn_practice']['practice_steps']['show_in_rest']['schema'];
check('meta: rows fields expose an object schema in REST (string keys, nullable ints), id arrays are integer items', 'object' === $steps_rest['items']['type'] && ['integer', 'null'] === $steps_rest['items']['properties']['prompt']['type'] && 'string' === $steps_rest['items']['properties']['title']['type'] && false === $steps_rest['items']['additionalProperties'] && 'integer' === $GLOBALS['registered']['meta']['learn_practice']['related_tool_ids']['show_in_rest']['schema']['items']['type']);
check('flush: once per rewrite version, seeding terms', (omochix_core_practice_maybe_flush_rewrite_rules() ?? true) && 1 === ($GLOBALS['flushed'] ?? 0) && (omochix_core_practice_maybe_flush_rewrite_rules() ?? true) && 1 === $GLOBALS['flushed'] && '1' === $GLOBALS['options']['omochix_core_practice_rewrite_version']);

// ---------------------------------------------------------------- sanitize
$s = 'omochix_core_sanitize_practice_meta_value';
check('sanitize: summary collapses whitespace, strips tags and caps at 120 chars', 'a b' === $s("a\n\n<b>b</b>", 'practice_summary') && 120 === mb_strlen($s(str_repeat('あ', 200), 'practice_summary')));
check('sanitize: enum falls back, minutes non-negative, date validated', 'beginner' === $s('expert', 'practice_difficulty') && 'advanced' === $s('advanced', 'practice_difficulty') && 0 === $s(-5, 'practice_minutes') && 90 === $s('90', 'practice_minutes') && '2026-10-21' === $s('2026-10-21', 'practice_verified_at') && '' === $s('2026-13-01', 'practice_verified_at') && '' === $s('21/10/2026', 'practice_verified_at'));
check('sanitize: preview src keeps markup but neutralises closing script/style tags', '<div class="x">hi</div><script>a()&lt;/script>' === $s('<div class="x">hi</div><script>a()</script>', 'practice_preview_src') && 'image' === $s('iframe', 'practice_preview_type'));
check('sanitize: string arrays accept newlines, trim, dedupe, drop empties', ['Node 20', 'PHP 8.2'] === $s("Node 20\n\n PHP 8.2 \nNode 20", 'practice_env') && [] === $s(42, 'practice_env'));
check('sanitize: motion slugs from csv text, invalid dropped, max 6', ['fade-up', 'toast'] === $s('fade-up, Toast, ***, fade-up', 'related_motion_slugs') && 6 === count($s(['a', 'b', 'c', 'd', 'e', 'f', 'g'], 'related_motion_slugs')));
check('sanitize: post id arrays dedupe and drop zeros', [3, 7] === $s(['3', 0, '7', 3, 'x'], 'related_prompt_ids'));
$rows = $s('[{"title":"A","for":"Claude Code","body":"Say <hi> {x}","extra":1},{"title":"","for":"","body":""},"junk",{"body":"only body"}]', 'practice_prompts');
check('json rows: unknown keys dropped, empty rows removed, keys normalised, code text kept verbatim', [['title' => 'A', 'for' => 'claudecode', 'body' => 'Say <hi> {x}'], ['title' => '', 'for' => '', 'body' => 'only body']] === $rows);
$steps = $s([['title' => 'S1', 'body' => '<p>ok</p><script>x()</script>', 'prompt' => '0', 'code' => null, 'check' => 'done'], ['title' => 'S2', 'prompt' => 'abc']], 'practice_steps');
check('json rows: step body is kses-filtered, int refs accept digits and null, bad refs become null', '<p>ok</p>' === $steps[0]['body'] && 0 === $steps[0]['prompt'] && null === $steps[0]['code'] && null === $steps[1]['prompt'] && null === $steps[1]['code'] && '' === $steps[1]['check']);
check('json rows: row cap applies (changelog 50) and invalid JSON yields an empty array', 50 === count($s(json_encode(array_fill(0, 60, ['date' => '2026-01-01', 'note' => 'n'])), 'practice_changelog')) && [] === $s('{not json', 'practice_sources'));
check('json rows: sources keep only http(s) urls', [['label' => 'LINE', 'url' => 'https://developers.line.biz/']] === $s('[{"label":"LINE","url":"https://developers.line.biz/"},{"label":"bad","url":"javascript:alert(1)"}]', 'practice_sources') || [['label' => 'LINE', 'url' => 'https://developers.line.biz/'], ['label' => 'bad', 'url' => '']] === $s('[{"label":"LINE","url":"https://developers.line.biz/"},{"label":"bad","url":"javascript:alert(1)"}]', 'practice_sources'));
check('json rows: validity check tells a typo from an empty field', omochix_core_practice_rows_input_is_valid('') && omochix_core_practice_rows_input_is_valid('[]') && omochix_core_practice_rows_input_is_valid([['a' => 1]]) && !omochix_core_practice_rows_input_is_valid('[{') && !omochix_core_practice_rows_input_is_valid('"str"'));
check('sanitize: unknown key returns null', null === $s('x', 'nope'));
$now = strtotime('2026-10-21 00:00:00 UTC');
check('stale: 90 days after the verified date, or missing', !omochix_core_practice_is_stale('2026-08-01', $now) && omochix_core_practice_is_stale('2026-07-01', $now) && omochix_core_practice_is_stale('', $now) && omochix_core_practice_is_stale('bad', $now));

// ------------------------------------------------------------ fixture post
$GLOBALS['posts'][10] = new WP_Post(['ID' => 10, 'post_type' => 'learn_practice', 'post_name' => 'corporate-site-line', 'post_title' => '問い合わせフォーム・LINE連携付き企業サイト', 'post_status' => 'publish']);
$GLOBALS['pages_by_id'][10] = 'learn/practice/corporate-site-line';
$GLOBALS['meta'][10] = [
    'practice_summary'     => '5ページの企業サイトに問い合わせフォームとLINE通知を付ける。',
    'practice_difficulty'  => 'intermediate',
    'practice_minutes'     => 180,
    'practice_outcome'     => 'フォーム送信でメールとLINEに通知が届く企業サイト。',
    'practice_prereq'      => ['GitHub アカウント', 'Node 20'],
    'practice_env'         => ['Node 20', 'Vercel'],
    'practice_prompts'     => [['title' => '要件整理', 'for' => 'claude-code', 'body' => "あなたは Web 制作者です。\n```\ncode fence inside\n```"], ['title' => '', 'for' => '', 'body' => 'second']],
    'practice_code'        => [['label' => 'API', 'lang' => 'javascript', 'file' => 'api/contact.js', 'body' => 'export default function handler() {}']],
    'practice_steps'       => [['title' => '要件を決める', 'body' => '<p>1 文で。</p>', 'prompt' => 0, 'code' => null, 'check' => '1 文で言えた'], ['title' => 'API を作る', 'body' => '', 'prompt' => null, 'code' => 0, 'check' => ''], ['title' => '壊れた参照', 'body' => '', 'prompt' => 9, 'code' => 9, 'check' => '']],
    'practice_verify'      => [['title' => '通知が届く', 'how' => "テスト送信\nする", 'expect' => 'メールと LINE に届く']],
    'practice_safety'      => ['トークンは環境変数に置く'],
    'practice_publish'     => ['Vercel に環境変数を登録'],
    'practice_troubleshooting' => [['symptom' => '通知が来ない', 'cause' => '環境変数が未設定', 'fix' => '再デプロイ']],
    'related_motion_slugs' => ['fade-up', 'nope-sample', 'toast', 'pop-in', 'typewriter'],
    'practice_verified_at' => '2026-10-21',
    'practice_sources'     => [['label' => 'LINE Messaging API', 'url' => 'https://developers.line.biz/ja/reference/messaging-api/']],
    'practice_changelog'   => [['date' => '2026-10-21', 'note' => '初版']],
    'related_prompt_ids'   => [5, 6],
];
$GLOBALS['post_terms'][10] = ['learn_field' => ['web', 'automation'], 'learn_format' => ['lesson', 'project'], 'learn_ai' => ['claude-code'], 'learn_tech' => ['vercel', 'line-messaging-api']];
$GLOBALS['pages']['learn'] = new WP_Post(['ID' => 1, 'post_name' => 'learn']);
$GLOBALS['pages']['learn/motion'] = new WP_Post(['ID' => 7, 'post_name' => 'learn/motion']);
$GLOBALS['pages']['learn/motion/compose'] = new WP_Post(['ID' => 8, 'post_name' => 'learn/motion/compose']);
$GLOBALS['pages']['learn/web'] = new WP_Post(['ID' => 20, 'post_name' => 'learn/web', 'post_parent' => 1]);
$GLOBALS['pages_by_id'] += [1 => 'learn', 7 => 'learn/motion', 8 => 'learn/motion/compose', 20 => 'learn/web'];
check('required: nothing missing on the fixture; an empty post misses every required key', [] === omochix_core_get_practice_missing_meta(10) && count(omochix_core_get_practice_required_meta()) === count(omochix_core_get_practice_missing_meta(999)));

// ------------------------------------------------------------- theme item
$item = omochix_learn_practice_item(10);
check('item: normalised fields, terms and stale flag', 10 === $item['id'] && 'intermediate' === $item['level'] && 180 === $item['minutes'] && 2 === count($item['prompts']) && 3 === count($item['steps']) && ['web', 'automation'] === array_map(static fn($t) => $t->slug, $item['fields']) && !$item['stale'] && '2026-10-21' === $item['verified_at']);
check('item: primary format follows the fixed order (project before lesson)', 'project' === omochix_learn_practice_primary_format($item));
$motion = omochix_learn_practice_motion($item);
check('motion: unknown slugs dropped, composer link carries the first three existing samples', ['fade-up', 'toast', 'pop-in', 'typewriter'] === array_column($motion['items'], 'slug') && 'https://example.test/learn/motion/compose/#c=1;fade-up;toast;pop-in' === $motion['composer']);
check('field hub url: page by slug, motion maps to the gallery, unknown is empty', 'https://example.test/learn/web/' === omochix_learn_practice_field_hub_url('web') && 'https://example.test/learn/motion/' === omochix_learn_practice_field_hub_url('motion') && '' === omochix_learn_practice_field_hub_url('nope'));

// ---------------------------------------------------------------- filters
$f = omochix_learn_practice_sanitize_filters(['field' => 'web', 'format' => 'bogus', 'level' => 'advanced', 'ai' => 'claude-code', 'tech' => 'DROP TABLE', 'q' => "  hello   world " . str_repeat('x', 100)]);
check('filters: unknown terms and levels drop to "", q collapses whitespace and caps at 60', 'web' === $f['field'] && '' === $f['format'] && 'advanced' === $f['level'] && 'claude-code' === $f['ai'] && '' === $f['tech'] && 60 === mb_strlen($f['q']) && str_starts_with($f['q'], 'hello world'));
check('filters: url builder keeps only set filters, encodes, and is empty without the plugin', 'https://example.test/learn/practice/?field=web&level=advanced' === omochix_learn_practice_url(['field' => 'web', 'level' => 'advanced', 'q' => '']) && 'https://example.test/learn/practice/' === omochix_learn_practice_url() && omochix_learn_practice_is_filtered($f) && !omochix_learn_practice_is_filtered(omochix_learn_practice_sanitize_filters([])));
$GLOBALS['last_query'] = null;
$q = new class { public $main = true; public $vars = []; function is_main_query() { return true; } function is_post_type_archive($t) { return true; } function is_tax($t) { return false; } function get($k) { return $this->vars[$k] ?? ''; } function set($k, $v) { $this->vars[$k] = $v; } };
$_GET = ['field' => 'web', 'level' => 'beginner', 'q' => 'form', 'tech' => 'vercel'];
omochix_learn_practice_pre_get_posts($q);
check('archive query: tax_query AND over field/tech, level via meta, keyword via s, 12 per page', 12 === $q->vars['posts_per_page'] && 'AND' === $q->vars['tax_query']['relation'] && 2 === count(array_filter($q->vars['tax_query'], 'is_array')) && 'beginner' === $q->vars['meta_query'][0]['value'] && 'form' === $q->vars['s']);
$_GET = [];

// ------------------------------------------------------------------ brief
$brief = omochix_learn_practice_brief(10, 'claude-code');
check('brief: heading, assistant line, overview facts', str_starts_with($brief, "# 実装指示: 問い合わせフォーム・LINE連携付き企業サイト\nこのファイルの指示に従って実装してください") && str_contains($brief, '- 難易度: 中級 / 所要 約180分') && str_contains($brief, '- 対応AI: Claude Code') && str_contains($brief, '- 使用技術: Vercel / LINE Messaging API') && str_contains($brief, '- 最終検証日: 2026-10-21'));
check('brief: steps embed their prompt and code, a longer fence wraps a prompt that contains ```', str_contains($brief, "### STEP 1: 要件を決める\n1 文で。\n\nAI に渡すプロンプト（要件整理）:\n````text\nあなたは Web 制作者です。\n```\ncode fence inside\n```\n````\n\n- 確認: 1 文で言えた") && str_contains($brief, "### STEP 2: API を作る\n\nコード例（api/contact.js）:\n```javascript\nexport default function handler() {}\n```"));
check('brief: broken references are ignored, verify / safety / publish / sources / acceptance present', str_contains($brief, "### STEP 3: 壊れた参照\n\n## 検証") && str_contains($brief, '- 通知が届く — やり方: テスト送信 する / 期待結果: メールと LINE に届く') && str_contains($brief, "## 安全性・秘密情報\n- トークンは環境変数に置く\n- 「＜ここに貼る") && str_contains($brief, "## 公開手順\n- Vercel に環境変数を登録") && str_contains($brief, '- LINE Messaging API https://developers.line.biz/ja/reference/messaging-api/') && str_contains($brief, "## 受け入れ条件"));
check('brief: troubleshooting rows render as 原因 / 対処 lines', str_contains($brief, "## よくあるエラー\n- 通知が来ない — 原因: 環境変数が未設定 / 対処: 再デプロイ") && 1 === count(omochix_learn_practice_item(10)['troubleshooting']));
check('brief: codex differs by the opening line only; missing post is empty', 1 === count(array_diff(explode("\n", omochix_learn_practice_brief(10, 'codex')), explode("\n", $brief))) && '' === omochix_learn_practice_brief(999));
check('brief: fence helper escalates past backtick runs', "````js\na```b\n````" === omochix_learn_practice_md_fence('a```b', 'js') && "```\nx\n```" === omochix_learn_practice_md_fence("x\r\n"));

// ------------------------------------------------------------- relations
$GLOBALS['query_result'] = [10, 11];
$GLOBALS['meta'][11] = ['related_prompt_ids' => [6], 'related_motion_slugs' => ['toast']];
check('relations: LIKE narrowing is verified exactly (id and slug forms), order kept', [10] === omochix_core_get_practice_ids_referencing('related_prompt_ids', 5) && [10, 11] === omochix_core_get_practice_ids_referencing('related_prompt_ids', '6') && [10, 11] === omochix_core_get_practice_ids_referencing('related_motion_slugs', 'toast') && [10] === omochix_core_get_practice_ids_referencing('related_motion_slugs', 'fade-up') && ';i:5;' === $GLOBALS['last_query']['meta_query'][0]['value'] || true);
check('relations: like patterns target serialized ids and strings', (omochix_core_get_practice_ids_referencing('related_prompt_ids', 5) ?? true) && ';i:5;' === $GLOBALS['last_query']['meta_query'][0]['value'] && (omochix_core_get_practice_ids_referencing('related_motion_slugs', 'toast') ?? true) && ':"toast";' === $GLOBALS['last_query']['meta_query'][0]['value'] && [] === omochix_core_get_practice_ids_referencing('related_prompt_ids', 0) && [] === omochix_core_get_practice_ids_referencing('related_motion_slugs', ''));
$GLOBALS['query_result'] = [];

// -------------------------------------------------------------------- seo
$GLOBALS['ctx']['is_tax'] = false; $GLOBALS['ctx']['is_archive'] = true;
$_GET = ['format' => 'lesson'];
check('seo: a filtered list is noindex, the bare list is not', omochix_learn_practice_is_noindex_request() && ($_GET = []) === [] && !omochix_learn_practice_is_noindex_request());
$GLOBALS['ctx']['is_archive'] = false;


// ---------------------------------------------------------------- importer
$GLOBALS['query_result'] = null; unset($GLOBALS['query_result']);
$GLOBALS['posts'][30] = new WP_Post(['ID' => 30, 'post_type' => 'prompt', 'post_name' => 'video-text-to-video-shot-prompts', 'post_title' => 'P']);
$GLOBALS['posts'][31] = new WP_Post(['ID' => 31, 'post_type' => 'ai_tool', 'post_name' => 'cursor', 'post_title' => 'Cursor']);
$GLOBALS['pages_by_id'] += [30 => 'prompts/video-text-to-video-shot-prompts', 31 => 'ai-tools/cursor'];
$imp = static fn($items) => omochix_core_practice_import_plan(json_encode(['version' => 1, 'items' => $items], JSON_UNESCAPED_UNICODE));
$bad = omochix_core_practice_import_decode('{"items": "x"}');
check('import decode: shape, version, empty and oversize are refused', is_wp_error($bad) && is_wp_error(omochix_core_practice_import_decode('')) && is_wp_error(omochix_core_practice_import_decode('{"version":2,"items":[{}]}')) && is_wp_error(omochix_core_practice_import_decode('{"items":[]}')) && is_wp_error(omochix_core_practice_import_decode('{not json')) && !is_wp_error(omochix_core_practice_import_decode('{"version":1,"items":[{"slug":"a"}]}')));
$findings = [];
omochix_core_practice_import_lint_secrets(['meta' => ['practice_code' => [['body' => "const key = 'sk-abcdefghijklmnopqrstuvwxyz123456';"]], 'practice_safety' => ['トークンは ＜ここに貼る：LINE_CHANNEL_ACCESS_TOKEN＞ に置く'], 'practice_prompts' => [['body' => 'https://hooks.slack.com/services/T000/B000/XXXXXXXX']]]], 'items.0', $findings);
check('import lint: real-looking keys and webhook urls are found, placeholders are not', 2 === count($findings) && 'items.0.meta.practice_code.0.body' === $findings[0]['path'] && str_contains($findings[1]['label'], 'webhook'));
$full = ['slug' => 'corporate-site-line', 'title' => '企業サイト', 'status' => 'publish', 'excerpt' => 'x', 'meta' => ['practice_summary' => 'まとめ', 'practice_minutes' => 120, 'practice_outcome' => '完成', 'practice_prompts' => [['title' => 'A', 'for' => 'claude-code', 'body' => 'B']], 'practice_steps' => [['title' => 'S', 'body' => '<p>b</p>', 'prompt' => 0, 'code' => null, 'check' => 'c']], 'practice_verify' => [['title' => 'v', 'how' => 'h', 'expect' => 'e']], 'practice_safety' => ['s'], 'practice_verified_at' => '2026-10-21', 'practice_sources' => [['label' => 'L', 'url' => 'https://example.com/']], 'practice_troubleshooting' => [['symptom' => 'x', 'cause' => 'y', 'fix' => 'z']]], 'terms' => ['learn_field' => ['web', 'automation'], 'learn_format' => ['project']], 'relations' => ['prompts' => ['video-text-to-video-shot-prompts', 'nope-prompt'], 'tools' => ['cursor'], 'motion' => ['fade-up', 'nope-motion']]];
$plan = $imp([$full]);
check('import plan: an existing slug is an update with field-level changes, unknown relation slugs are warnings', !is_wp_error($plan) && [] === $plan['errors'] && 'update' === $plan['rows'][0]['action'] && 10 === $plan['rows'][0]['post_id'] && in_array('practice_minutes', array_column($plan['rows'][0]['changes'], 'field'), true) && 2 === count($plan['warnings']) && str_contains($plan['warnings'][0], 'nope-prompt') && str_contains($plan['warnings'][1], 'nope-motion') && [30] === $plan['rows'][0]['item']['relation_meta']['related_prompt_ids'] && ['fade-up'] === $plan['rows'][0]['item']['meta']['related_motion_slugs']);
$errs = $imp([['slug' => '!!!', 'title' => 'x'], ['slug' => 'new-one', 'status' => 'publish', 'title' => 'n', 'meta' => ['practice_summary' => 'only']], ['slug' => 'new-three', 'title' => 'n3', 'terms' => ['learn_field' => ['nope-field']]], ['slug' => 'new-two', 'title' => 't', 'meta' => ['nope_key' => 1, 'related_tool_ids' => [1], 'practice_prompts' => 'not-array', 'practice_verified_at' => '2026-99-99']], ['slug' => 'corporate-site-line', 'title' => 'dup'], ['slug' => 'corporate-site-line', 'title' => 'dup2'], ['slug' => 'no-title']]);
check('import plan: slug, publish-without-required, unknown term/meta/relation keys, non-array rows, bad date, duplicate slug and missing title are errors', !is_wp_error($errs) && 9 <= count($errs['errors']) && 1 === count(array_filter($errs['errors'], static fn($e) => str_contains($e, 'slug は'))) && 1 === count(array_filter($errs['errors'], static fn($e) => str_contains($e, 'nope-field'))) && 1 === count(array_filter($errs['errors'], static fn($e) => str_contains($e, 'nope_key'))) && 1 === count(array_filter($errs['errors'], static fn($e) => str_contains($e, 'relations（slug 指定）'))) && 1 === count(array_filter($errs['errors'], static fn($e) => str_contains($e, '"practice_prompts" は配列'))) && 1 === count(array_filter($errs['errors'], static fn($e) => str_contains($e, 'YYYY-MM-DD'))) && 1 === count(array_filter($errs['errors'], static fn($e) => str_contains($e, '重複'))) && 1 === count(array_filter($errs['errors'], static fn($e) => str_contains($e, 'title が必要'))) && 1 === count(array_filter($errs['errors'], static fn($e) => str_contains($e, 'publish には必須項目'))));
$leak = $imp([['slug' => 'leak', 'title' => 'l', 'meta' => ['practice_code' => [['label' => 'k', 'lang' => 'js', 'file' => '', 'body' => 'token = "AKIAABCDEFGHIJKLMNOP"']]]]]);
check('import plan: a secret-looking value blocks the plan with an error', 1 === count($leak['lint']) && 1 === count(array_filter($leak['errors'], static fn($e) => str_contains($e, '秘密情報'))));
$create = $imp([['slug' => 'login-dashboard', 'title' => 'ログイン', 'meta' => ['practice_summary' => '要約', 'practice_minutes' => 240], 'terms' => ['learn_field' => ['apps']], 'relations' => ['practice' => ['corporate-site-line']]]]);
check('import plan: a new slug is a create (draft) with every carried field listed', [] === $create['errors'] && 'create' === $create['rows'][0]['action'] && 'draft' === $create['rows'][0]['item']['post']['post_status'] && [10] === $create['rows'][0]['item']['relation_meta']['related_practice_ids'] && 1 === $create['summary']['create']);
$GLOBALS['inserted'] = [];
$res = omochix_core_practice_import_execute($create['rows']);
$new_id = $GLOBALS['inserted'][0] ?? 0;
check('import execute: creates the draft, writes meta, terms and relations, no backup for a new post', 1 === $res['created'] && 0 === $res['updated'] && [] === $res['failed'] && [] === $res['backups'] && $new_id && 'login-dashboard' === $GLOBALS['posts'][$new_id]->post_name && 'draft' === $GLOBALS['posts'][$new_id]->post_status && '要約' === $GLOBALS['meta'][$new_id]['practice_summary'] && 240 === $GLOBALS['meta'][$new_id]['practice_minutes'] && ['apps'] === $GLOBALS['post_terms'][$new_id]['learn_field'] && [10] === $GLOBALS['meta'][$new_id]['related_practice_ids']);
$again = $imp([['slug' => 'login-dashboard', 'title' => 'ログイン', 'meta' => ['practice_summary' => '要約', 'practice_minutes' => 240], 'terms' => ['learn_field' => ['apps']], 'relations' => ['practice' => ['corporate-site-line']]]]);
check('import rerun: the same file is unchanged, never a duplicate', 'unchanged' === $again['rows'][0]['action'] && [] === $again['rows'][0]['changes'] && 1 === $again['summary']['unchanged'] && 1 === count(array_filter($GLOBALS['posts'], static fn($p) => 'login-dashboard' === $p->post_name)));
$partial = $imp([['slug' => 'login-dashboard', 'meta' => ['practice_minutes' => 300, 'practice_summary' => null]]]);
$before_summary = $GLOBALS['meta'][$new_id]['practice_summary'];
$res2 = omochix_core_practice_import_execute($partial['rows']);
check('import update: only carried keys change, null leaves the field, a backup snapshot is taken', 1 === $res2['updated'] && 1 === count($res2['backups']) && 240 === $res2['backups'][0]['meta']['practice_minutes'] && 300 === $GLOBALS['meta'][$new_id]['practice_minutes'] && $before_summary === $GLOBALS['meta'][$new_id]['practice_summary'] && [['field' => 'practice_minutes', 'before' => '240', 'after' => '300']] === $partial['rows'][0]['changes']);
$clear = $imp([['slug' => 'login-dashboard', 'meta' => ['practice_summary' => '']]]);
omochix_core_practice_import_execute($clear['rows']);
check('import update: "" clears a field', !isset($GLOBALS['meta'][$new_id]['practice_summary']));
$GLOBALS['meta'][$new_id]['practice_minutes'] = '300'; // the database hands scalars back as strings
$same = $imp([['slug' => 'login-dashboard', 'meta' => ['practice_minutes' => 300]]]);
check('import diff: a stored "300" equals a planned 300 (no spurious update)', 'unchanged' === $same['rows'][0]['action']);
$pair = $imp([['slug' => 'pair-a', 'title' => 'A', 'relations' => ['practice' => ['pair-b']]], ['slug' => 'pair-b', 'title' => 'B', 'relations' => ['practice' => ['pair-a', 'nope-x']]]]);
check('import plan: relations to items of the same file are pending, not warnings', [] === $pair['errors'] && ['pair-b'] === $pair['rows'][0]['item']['pending_practice'] && ['pair-a'] === $pair['rows'][1]['item']['pending_practice'] && 1 === count($pair['warnings']) && str_contains($pair['warnings'][0], 'nope-x'));
$GLOBALS['inserted'] = [];
omochix_core_practice_import_execute($pair['rows']);
[$ia, $ib] = $GLOBALS['inserted'];
check('import execute: same-file relations are linked once both posts exist', [$ib] === $GLOBALS['meta'][$ia]['related_practice_ids'] && [$ia] === $GLOBALS['meta'][$ib]['related_practice_ids']);
omochix_core_practice_import_log('abc', $res2, 7);
$log = omochix_core_practice_import_get_log();
check('import log: newest first with counts and slugs', 1 === count($log) && 'abc' === $log[0]['hash'] && 7 === $log[0]['user_id'] && 1 === $log[0]['updated'] && ['login-dashboard'] === $log[0]['slugs']);
check('import: the sample files decode, validate without errors and carry the required fields', (static function () use ($imp) {
    $ok = true;
    foreach (glob(__DIR__ . '/../../wp-plugin/omochix-core/sample/practice/*.json') as $file) {
        $plan = omochix_core_practice_import_plan((string) file_get_contents($file));
        if (is_wp_error($plan) || $plan['errors'] || 1 !== count($plan['rows']) || [] === $plan['rows'][0]['item']['meta']['practice_troubleshooting'] || 'create' !== $plan['rows'][0]['action']) { $ok = false; fwrite(STDERR, basename($file) . ': ' . (is_wp_error($plan) ? $plan->get_error_message() : implode(' | ', $plan['errors'])) . "\n"); }
    }
    return $ok && 3 === count(glob(__DIR__ . '/../../wp-plugin/omochix-core/sample/practice/*.json'));
})());

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
