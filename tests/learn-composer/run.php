<?php
/**
 * Learn v2 / Motion Composer: unit tests for inc/learn-composer.php using the
 * gallery's WordPress stub. Run: php tests/learn-composer/run.php
 */
require __DIR__ . '/../learn-gallery/stub.php';
require $THEME . '/inc/learn-composer.php';

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }

$GLOBALS['pages']['learn/motion'] = new WP_Post(['ID' => 7, 'post_name' => 'learn/motion']);
$GLOBALS['pages']['learn/motion/compose'] = new WP_Post(['ID' => 8, 'post_name' => 'learn/motion/compose']);
$GLOBALS['pages_by_id'] = [7 => 'learn/motion', 8 => 'learn/motion/compose'];

// ---------------------------------------------------------------- sanitize
$empty = omochix_learn_composer_sanitize(null);
check('sanitize: nothing in -> version and no slots', '1' === $empty['v'] && [] === $empty['slots']);
$raw = ['slots' => [
    ['slug' => 'pop-in', 'params' => ['cycle' => '1.5', 'color' => 'ff0000', 'overshoot' => 'soft', 'badge_delay' => '99'], 'target' => 'hero', 'intent' => '第一印象'],
    ['slug' => 'Toast', 'params' => []],
    ['slug' => 'nope'],
    'garbage',
    ['slug' => 'gradient-shift', 'params' => ['angle' => 45], 'target' => str_repeat('あ', 41), 'intent' => '<script>'],
    ['slug' => 'fade-up'],
]];
$comp = omochix_learn_composer_sanitize($raw);
check('sanitize: keeps at most 3 valid slots in order, drops unknown slugs and garbage', ['pop-in', 'toast', 'gradient-shift'] === array_column($comp['slots'], 'slug'));
check('sanitize: params are sanitized and only non-default values survive', ['cycle' => 1.5, 'color' => '#ff0000', 'badge_delay' => 0.8] === $comp['slots'][0]['params'] && [] === $comp['slots'][1]['params'] && ['angle' => 45.0] === $comp['slots'][2]['params']);
check('sanitize: notes keep short plain text, drop over-long and markup', 'hero' === $comp['slots'][0]['target'] && '第一印象' === $comp['slots'][0]['intent'] && '' === $comp['slots'][2]['target'] && '' === $comp['slots'][2]['intent']);
check('sanitize: a repeated slug is dropped (the UI is keyed by slug)', ['toast', 'pop-in'] === array_column(omochix_learn_composer_sanitize(['slots' => [['slug' => 'toast'], ['slug' => 'toast'], ['slug' => 'pop-in']]])['slots'], 'slug'));
check('sanitize: notes accept common ASCII punctuation but not markup or markdown markers', "v2.0 hero, test/'x' & \"q\" (A)!" === omochix_learn_composer_sanitize_note("v2.0 hero, test/'x' & \"q\" (A)!") && '' === omochix_learn_composer_sanitize_note('<b>x</b>') && '' === omochix_learn_composer_sanitize_note('a `code` b') && '' === omochix_learn_composer_sanitize_note('[link](x)'));
check('hash: notes are rawurlencoded (parentheses and ! included) and round-trip', str_contains(omochix_learn_composer_hash(['slots' => [['slug' => 'toast', 'target' => '(ヒーロー)!']]]), 't=%28%E3%83%92%E3%83%BC%E3%83%AD%E3%83%BC%29%21') && '(ヒーロー)!' === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash(omochix_learn_composer_hash(['slots' => [['slug' => 'toast', 'target' => '(ヒーロー)!']]])))['slots'][0]['target']);

// --------------------------------------------------------------------- hash
$hash = omochix_learn_composer_hash($comp);
check('hash: version first, one segment per slot, non-default params only, colours without #, notes encoded', '1;pop-in:cycle=1.5,color=ff0000,badge_delay=0.8,t=hero,i=' . rawurlencode('第一印象') . ';toast;gradient-shift:angle=45' === $hash);
check('hash: empty composition -> empty string', '' === omochix_learn_composer_hash(['slots' => []]));
$back = omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash($hash));
check('hash: round trip reproduces the composition', $back === $comp);
check('hash: wrong version or junk -> empty', [] === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('2;pop-in'))['slots'] && [] === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash(''))['slots'] && [] === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;;;'))['slots']);
check('hash: more than 3 segments are cut', 3 === count(omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;pop-in;toast;fade-up;stagger'))['slots']));
check('hash: unknown or malformed keys are ignored', [] === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;pop-in:Bad=1,__x=2,=3'))['slots'][0]['params']);
$capped = omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;pop-in:cycle=' . str_repeat('9', 100)));
check('hash: an over-long numeric value is clamped to the range max', 4.0 === $capped['slots'][0]['params']['cycle']);
check('hash: injection in notes is dropped', '' === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;pop-in:t=' . rawurlencode('<img onerror=1>')))['slots'][0]['target']);

// ----------------------------------------------------------------- combined
$popIn   = omochix_learn_gallery_get_item('pop-in');
$countUp = omochix_learn_gallery_get_item('count-up');
check('combined: scoped block carries only non-default css params under the slot selector', '.omx-slot[data-slot="2"]{--p-cycle:1.5s;--p-color:#ff0000}' === omochix_learn_composer_scoped_block($popIn, omochix_learn_gallery_sanitize_params($popIn, ['cycle' => 1.5, 'color' => '#ff0000']), 2) && '' === omochix_learn_composer_scoped_block($popIn, omochix_learn_gallery_sanitize_params($popIn), 1));
check('combined: js params are a JSON object of non-default js values', '{"target":500}' === omochix_learn_composer_js_params($countUp, omochix_learn_gallery_sanitize_params($countUp, ['target' => 500])) && '{}' === omochix_learn_composer_js_params($countUp, omochix_learn_gallery_sanitize_params($countUp)));
$doc = omochix_learn_composer_preview_document([['item' => $popIn, 'values' => ['cycle' => 1.5]], ['item' => $countUp, 'values' => ['target' => 500, 'size' => 72]]]);
check('combined: document stacks the slots in order with scoped params', strpos($doc, 'data-slot="1"') < strpos($doc, 'data-slot="2"') && str_contains($doc, '<style id="omx-params">.omx-slot[data-slot="1"]{--p-cycle:1.5s}.omx-slot[data-slot="2"]{--p-size:72px}</style>') && str_contains($doc, $popIn['preview']['html']) && str_contains($doc, $countUp['preview']['html']));
check('combined: js samples are wrapped with a per-slot omx and their params', str_contains($doc, 'omxSlot(2,{"target":500})') && str_contains($doc, '(function(omx){' . $countUp['preview']['js'] . '}(omxSlot(2,') && str_contains($doc, 'function omxSlot(n,p)') && 1 === substr_count($doc, 'omx:ready'));
check('combined: no :root block; exactly four scripts (runtime, shim, one js sample, fit) close cleanly', !str_contains($doc, '<style id="omx-params">:root') && 4 === substr_count($doc, '</script>') && 4 === substr_count($doc, '<script>'));
check('combined: fit script present, runtime once, stage size from the catalog', str_contains($doc, 'data-stage-w="320"') && 1 === substr_count($doc, '.omx-stage{') && str_contains($doc, 'querySelectorAll(".omx-slot")'));

// -------------------------------------------------------------------- brief
$briefComp = ['slots' => [['slug' => 'pop-in', 'params' => ['cycle' => 1.5], 'target' => 'hero', 'intent' => '-注意'], ['slug' => 'count-up', 'params' => ['target' => 500]], ['slug' => 'particles']]];
$brief = omochix_learn_composer_brief($briefComp, 'claude-code');
check('brief: one heading per slot in order, overview list with all three', 3 === preg_match_all('/^## \d\. /m', $brief) && strpos($brief, '## 1. ポップイン') < strpos($brief, '## 2. カウントアップ') && strpos($brief, '## 2. カウントアップ') < strpos($brief, '## 3. パーティクル') && str_contains($brief, '- 見本: 3件') && str_contains($brief, '  - #020 パーティクル（Particles） https://example.test/learn/motion/particles/'));
check('brief: preset note shows its label, a free note starting with a marker is neutralised, empty shows （未指定）', str_contains($brief, '- 適用対象: ヒーロー / 意図: 　-注意') && str_contains($brief, '- 適用対象: （未指定） / 意図: （未指定）'));
check('brief: adjusted values with （既定） markers and the spec lines', str_contains($brief, "- 調整した値:\n  - 登場から退場までの1周期は1.5s\n  - 行き過ぎ（オーバーシュート）は「少し」（既定）") && str_contains($brief, "- 動きの仕様:\n  - 拡大率 0→1"));
check('brief: code is scoped with a wrapper class, no :root, js samples wrapped with their own omxSlot', str_contains($brief, "\n.omx-motion-1{--p-cycle:1.5s}\n") && str_contains($brief, '<div class="omx-motion omx-motion-1">') && !str_contains($brief, ':root{') && str_contains($brief, 'omxSlot(2,{})') && str_contains($brief, 'omxSlot(3,{})') && 2 === substr_count($brief, 'function omxSlot(n, p)'));
// Brief omxSlot params: a key the HTML carries as data-<key> (count-up's target) is left to the attribute; the others stay.
check('brief js params: data-<key>-carried keys are dropped, others kept, combined document keeps all', '{}' === omochix_learn_composer_brief_js_params($countUp, omochix_learn_gallery_sanitize_params($countUp, ['target' => 500])) && '{"duration":1.5,"easing":"linear"}' === omochix_learn_composer_brief_js_params($countUp, omochix_learn_gallery_sanitize_params($countUp, ['target' => 500, 'duration' => 1.5, 'easing' => 'linear'])) && '{"count":80}' === omochix_learn_composer_brief_js_params(omochix_learn_gallery_get_item('particles'), omochix_learn_gallery_sanitize_params(omochix_learn_gallery_get_item('particles'), ['count' => 80])) && '{"target":500}' === omochix_learn_composer_js_params($countUp, omochix_learn_gallery_sanitize_params($countUp, ['target' => 500])) && omochix_learn_composer_html_carries($countUp, 'target') && !omochix_learn_composer_html_carries($countUp, 'duration'));
check('brief js params: a brief with an adjusted target carries it in data-target only', str_contains(omochix_learn_composer_brief_code($countUp, ['target' => 500, 'duration' => 1.5], 2), 'data-target="500"') && str_contains(omochix_learn_composer_brief_code($countUp, ['target' => 500, 'duration' => 1.5], 2), 'omxSlot(2,{"duration":1.5})'));
// Standalone code: the tokens the sample CSS refers to are defined on every wrapper, with the preview runtime's :root values.
$runtimeCss = file_get_contents($THEME . '/assets/css/learn-preview-runtime.css');
preg_match('/:root \{([^}]*)\}/', $runtimeCss, $m);
$runtimeTokens = [];
foreach (array_filter(array_map('trim', explode(';', $m[1]))) as $decl) {
    [$name, $value] = array_map('trim', explode(':', $decl, 2));
    if (str_starts_with($name, '--')) { $runtimeTokens[$name] = $value; }
}
check('brief tokens: the hard-coded token list equals the runtime stylesheet :root (name, value and order)', $runtimeTokens === omochix_learn_composer_tokens() && 8 === count($runtimeTokens));
$darkTokens = [];
preg_match('/:root\[data-theme="dark"\] \{([^}]*)\}/', $runtimeCss, $m);
foreach (array_filter(array_map('trim', explode(';', $m[1]))) as $decl) {
    [$name, $value] = array_map('trim', explode(':', $decl, 2));
    if (str_starts_with($name, '--')) { $darkTokens[$name] = $value; }
}
check('brief tokens: the dark values quoted in the comment equal the runtime stylesheet :root[data-theme="dark"]', $darkTokens === omochix_learn_composer_dark_tokens() && str_contains(omochix_learn_composer_token_comment(), '--text:#f5f5f1') && str_starts_with(omochix_learn_composer_token_comment(), '/* ') && str_ends_with(omochix_learn_composer_token_comment(), ' */') && !str_contains(substr(omochix_learn_composer_token_comment(), 2, -2), '*/'));
check('brief: footer tells the assistant to set the eight tokens to the page theme', str_contains($brief, '- 色: `.omx-motion-n` の 8 つのトークン') && str_contains($brief, 'ダークモード'));
check('brief tokens: every slot style starts with the token block for its wrapper, before the params block and the sample css', 3 === substr_count($brief, "<style>\n/* ") && str_contains($brief, "*/\n" . omochix_learn_composer_token_block(1) . "\n.omx-motion-1{--p-cycle:1.5s}\n.pop{") && str_contains($brief, "*/\n" . omochix_learn_composer_token_block(2) . "\n.cu{") && str_contains(omochix_learn_composer_token_block(3), '.omx-motion-3{--bg:#f7f7f5;') && str_contains(omochix_learn_composer_token_block(3), ';--amber:#d97706}'));
check('brief tokens: a css-only sample without params still gets a style block (tokens)', str_contains(omochix_learn_composer_brief_code(omochix_learn_gallery_get_item('gradient-shift'), [], 1), "<style>\n/* "));
// Standalone code: JS samples query inside omx.root, and the HTML data-* of JS params carry the adjusted values.
check('brief html: count-up data-target is rewritten to the adjusted value (default otherwise), other html untouched', str_contains($brief, '<span class="cu__n" data-target="500">0</span>') && str_contains(omochix_learn_composer_brief_html($countUp, omochix_learn_gallery_sanitize_params($countUp)), 'data-target="12800"') && $popIn['preview']['html'] === omochix_learn_composer_brief_html($popIn, omochix_learn_gallery_sanitize_params($popIn, ['cycle' => 1.5])));
$jsItems = array_filter(omochix_learn_gallery_get_items(), static fn($it) => '' !== $it['preview']['js']);
check('catalog: every js sample queries its elements inside omx.root, never the whole document', 2 === count($jsItems) && !array_filter($jsItems, static fn($it) => str_contains($it['preview']['js'], 'document.querySelector') || !str_contains($it['preview']['js'], 'omx.root.querySelector')));
check('catalog: count-up shows the target right away under reduced motion and reads data-target as its fallback', str_contains($countUp['preview']['js'], 'if(omx.reduced)show(target())') && str_contains($countUp['preview']['js'], "getAttribute('data-target')"));
$shim = omochix_learn_gallery_js_shim();
check('shim: one shared rAF loop that stops for reduced motion and hidden tabs, exposes root and reduced', 2 === substr_count($shim, 'requestAnimationFrame(tick)') && str_contains($shim, "matchMedia('(prefers-reduced-motion: reduce)')") && str_contains($shim, 'document.hidden') && str_contains($shim, 'root: document') && str_contains($shim, 'visibilitychange') && str_contains($shim, 'var omx = window.omx = window.omx ||'));
check('shim: t is clamped at zero (a rAF timestamp may precede the request)', str_contains($shim, 'Math.max(0, now - started - lost)'));
check('shim: the brief slot shim roots at the enclosing or preceding .omx-motion-n, else the first unclaimed one, and claims it', str_contains(omochix_learn_composer_brief_shim(), 'document.currentScript') && str_contains(omochix_learn_composer_brief_shim(), "me.closest('.' + cls)") && str_contains(omochix_learn_composer_brief_shim(), '!all[i].omxClaimed') && str_contains(omochix_learn_composer_brief_shim(), 'root.omxClaimed = true') && str_contains(omochix_learn_composer_brief_shim(), "'omx-motion-' + n") && str_contains(omochix_learn_composer_brief_shim(), 'root: root') && str_contains(omochix_learn_composer_slot_shim(), '.omx-slot[data-slot="\'+n+\'"]') && str_contains(omochix_learn_composer_slot_shim(), 'root:r') && str_contains($doc, 'root:r,reduced:!!window.omx.reduced'));
check('brief: footer conditions and acceptance present once', 1 === substr_count($brief, '## 全体の実装条件') && 1 === substr_count($brief, '## 受け入れ条件') && str_contains($brief, 'prefers-reduced-motion'));
$codex = omochix_learn_composer_brief($briefComp, 'codex');
check('brief: assistants differ by the opening line only', $codex !== $brief && 1 === count(array_diff(explode("\n", $codex), explode("\n", $brief))));
check('brief: empty composition -> empty string; unknown assistant falls back', '' === omochix_learn_composer_brief(['slots' => []]) && $brief === omochix_learn_composer_brief($briefComp, 'nope'));
$dataJson = json_decode(omochix_learn_composer_data_json(), true);
check('data json: 20 items with spec/url/tech, plus both shims', 20 === count($dataJson['items']) && is_array($dataJson['items']['pop-in']['spec']) && str_contains($dataJson['items']['pop-in']['url'], '/learn/motion/pop-in/') && str_contains($dataJson['shim'], 'var omx = window.omx') && str_contains($dataJson['slotShim'], 'function omxSlot'));
check('data json: slotShim is the brief shim (currentScript-rooted), not the combined-document one', str_contains($dataJson['slotShim'], 'document.currentScript') && !str_contains($dataJson['slotShim'], 'data-slot'));

// ------------------------------------------------------------------ presets
$presets = omochix_learn_composer_presets();
check('presets: target and intent lists exist with plain keys', count($presets['target']) >= 6 && count($presets['intent']) >= 5 && !array_filter(array_merge(array_keys($presets['target']), array_keys($presets['intent'])), static fn($k) => !preg_match('/^[a-z]+$/', $k)));

// ------------------------------------------------------------------- routing
check('routing: composer url resolves through the published page', 'https://example.test/learn/motion/compose/' === omochix_learn_composer_url());
check('routing: constants', 'learn/motion/compose' === OMOCHIX_LEARN_COMPOSER_PATH && 3 === OMOCHIX_LEARN_COMPOSER_MAX_SLOTS && 'c' === OMOCHIX_LEARN_COMPOSER_HASH_KEY);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
