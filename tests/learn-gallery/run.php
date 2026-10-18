<?php
/**
 * Learn v2 / Motion Gallery: unit and contract tests using a minimal WordPress stub.
 * Run: php tests/learn-gallery/run.php            (no WordPress, DB or network needed)
 *
 * Covers inc/learn-gallery.php: catalog loading and validation (mirrors
 * assets/data/motion/catalog.schema.json), filtering, neighbours, prompt /
 * code / preview-document composition, client JSON escaping, URL helpers and
 * filter sanitization. Browser and layout checks are done separately in an
 * isolated WordPress (see docs/Learn-v2-Spec.md §8).
 */
require __DIR__ . '/stub.php';

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }
function raw_catalog() { static $raw = null; return $raw ?? ($raw = json_decode(file_get_contents(omochix_learn_gallery_catalog_file()), true)); }
function errors_for(callable $mutate) { $data = raw_catalog(); $mutate($data); return omochix_learn_gallery_validate_catalog($data); }
function has_error(array $errors, $needle) { foreach ($errors as $e) { if (false !== strpos($e, $needle)) { return true; } } return false; }

// ----------------------------------------------------------------- loading
$loaded = omochix_learn_gallery_load_catalog_from_file(omochix_learn_gallery_catalog_file());
check('load: shipped catalog is valid', $loaded['ok'] && [] === $loaded['errors']);
$catalog = omochix_learn_gallery_get_catalog();
check('load: 20 items in 5 categories', 20 === count($catalog['items']) && 5 === count($catalog['categories']));
check('load: categories ordered by order', array_keys($catalog['categories']) === ['entrance', 'text', 'ui', 'scroll', 'ambient']);
check('load: category counts', array_sum(array_column($catalog['categories'], 'count')) === 20 && 4 === $catalog['categories']['ui']['count']);
check('load: items ordered by id', array_values(array_column($catalog['items'], 'id')) === array_map(static fn($i) => sprintf('%03d', $i), range(1, 20)));
check('load: featured defaults to boolean', is_bool($catalog['items']['fade-up']['featured']) && false === $catalog['items']['fade-up']['featured'] && true === $catalog['items']['pop-in']['featured']);
check('load: stage from file', ['width' => 320, 'height' => 180] === $catalog['stage']);
$missing = omochix_learn_gallery_load_catalog_from_file('/nonexistent/catalog.json');
check('load: missing file degrades to empty catalog', !$missing['ok'] && [] === $missing['catalog']['items'] && 0 === $missing['catalog']['version']);
$tmp = tempnam(sys_get_temp_dir(), 'omx'); file_put_contents($tmp, '{not json');
$broken = omochix_learn_gallery_load_catalog_from_file($tmp); unlink($tmp);
check('load: invalid JSON degrades to empty catalog', !$broken['ok'] && has_error($broken['errors'], 'invalid JSON') && [] === $broken['catalog']['items']);
check('schema file: shipped schema is valid JSON', is_array(json_decode(file_get_contents($THEME . '/assets/data/motion/catalog.schema.json'), true)));

// -------------------------------------------------------------- validation
check('validate: non-array root', has_error(omochix_learn_gallery_validate_catalog('x'), 'root'));
check('validate: wrong version', has_error(errors_for(static function (&$d) { $d['version'] = 2; }), 'version'));
check('validate: bad updated date', has_error(errors_for(static function (&$d) { $d['updated'] = '2026/10/17'; }), 'updated'));
check('validate: stage out of range', has_error(errors_for(static function (&$d) { $d['stage']['width'] = 10; }), 'stage'));
check('validate: duplicate item slug', has_error(errors_for(static function (&$d) { $d['items'][1]['slug'] = $d['items'][0]['slug']; }), 'duplicate slug'));
check('validate: duplicate item id', has_error(errors_for(static function (&$d) { $d['items'][1]['id'] = $d['items'][0]['id']; }), 'duplicate id'));
check('validate: duplicate category slug', has_error(errors_for(static function (&$d) { $d['categories'][1]['slug'] = $d['categories'][0]['slug']; }), 'duplicate slug'));
check('validate: unknown category', has_error(errors_for(static function (&$d) { $d['items'][0]['category'] = 'nope'; }), 'unknown category'));
check('validate: invalid level', has_error(errors_for(static function (&$d) { $d['items'][0]['level'] = 'expert'; }), 'invalid level'));
check('validate: invalid tech', has_error(errors_for(static function (&$d) { $d['items'][0]['tech'] = ['css', 'wasm']; }), 'tech must be'));
check('validate: empty tech', has_error(errors_for(static function (&$d) { $d['items'][0]['tech'] = []; }), 'tech must be'));
check('validate: uppercase slug rejected', has_error(errors_for(static function (&$d) { $d['items'][0]['slug'] = 'Pop-In'; }), 'invalid slug'));
check('validate: closing script tag in preview js', has_error(errors_for(static function (&$d) { $d['items'][0]['preview']['js'] = 'x;</script><script>alert(1)'; }), 'closing script/style'));
check('validate: closing style tag in preview css (case-insensitive)', has_error(errors_for(static function (&$d) { $d['items'][0]['preview']['css'] = 'a{}</STYLE >'; }), 'closing script/style'));
check('validate: missing preview html', has_error(errors_for(static function (&$d) { $d['items'][0]['preview']['html'] = ''; }), 'preview.html'));
check('validate: prompt spec required', has_error(errors_for(static function (&$d) { $d['items'][0]['prompt']['spec'] = []; }), 'prompt.ask'));
check('validate: too many tags', has_error(errors_for(static function (&$d) { $d['items'][0]['tags'] = array_fill(0, 13, 't'); }), 'tags'));
check('validate: related prompt slug', has_error(errors_for(static function (&$d) { $d['items'][0]['related']['prompts'] = ['Bad Slug']; }), 'related.prompts'));
check('validate: related learn path', has_error(errors_for(static function (&$d) { $d['items'][0]['related']['learn'] = ['prompts/x']; }), 'related.learn'));
check('validate: playground must be object or null', has_error(errors_for(static function (&$d) { $d['items'][0]['playground'] = 'yes'; }), 'playground'));
check('validate: summary too long', has_error(errors_for(static function (&$d) { $d['items'][0]['summary'] = str_repeat('あ', 81); }), 'summary'));
check('validate: a valid mutation stays valid', [] === errors_for(static function (&$d) { $d['items'][0]['tags'] = []; unset($d['items'][0]['featured'], $d['items'][0]['related']); }));

// ---------------------------------------------------------------- queries
check('items: no filter returns all', 20 === count(omochix_learn_gallery_get_items()));
check('items: cat filter', ['toggle-switch', 'button-press', 'toast', 'skeleton-loading'] === array_keys(omochix_learn_gallery_get_items(['cat' => 'ui'])));
check('items: level filter', ['particles'] === array_keys(omochix_learn_gallery_get_items(['level' => 'advanced'])));
check('items: tech filter', ['count-up', 'particles'] === array_keys(omochix_learn_gallery_get_items(['tech' => 'js'])));
check('items: keyword matches Japanese name', isset(omochix_learn_gallery_get_items(['q' => 'ポップ'])['pop-in']));
check('items: keyword matches #id', ['stagger'] === array_keys(omochix_learn_gallery_get_items(['q' => '#003'])));
check('items: keyword matches English name case-insensitively', isset(omochix_learn_gallery_get_items(['q' => 'TYPEWRITER'])['typewriter']));
check('items: keyword matches tag', isset(omochix_learn_gallery_get_items(['q' => 'Canvas'])['particles']));
check('items: multiple keywords are ANDed', ['pop-in'] === array_keys(omochix_learn_gallery_get_items(['q' => 'pop 登場'])) && [] === omochix_learn_gallery_get_items(['q' => 'pop canvas']));
check('items: combined filters', ['count-up'] === array_keys(omochix_learn_gallery_get_items(['cat' => 'text', 'tech' => 'js'])));
check('items: no match is empty', [] === omochix_learn_gallery_get_items(['q' => 'zzzz-not-there']));
check('item: lookup by slug', 'ポップイン' === omochix_learn_gallery_get_item('pop-in')['name']['ja'] && null === omochix_learn_gallery_get_item('nope'));
check('category: lookup', '登場・退場' === omochix_learn_gallery_get_category('entrance')['name'] && null === omochix_learn_gallery_get_category('x'));
$featured = omochix_learn_gallery_get_featured(3);
check('featured: capped and all featured', 3 === count($featured) && !array_filter($featured, static fn($i) => !$i['featured']));
check('featured: limit 0 is empty', [] === omochix_learn_gallery_get_featured(0));
$n = omochix_learn_gallery_get_neighbors(omochix_learn_gallery_get_item('pop-in'));
check('neighbors: first in category has no prev', null === $n['prev'] && 'fade-up' === $n['next']['slug']);
$n = omochix_learn_gallery_get_neighbors(omochix_learn_gallery_get_item('mask-reveal'));
check('neighbors: last in category has no next', 'stagger' === $n['prev']['slug'] && null === $n['next']);
check('search text: lower-cased and includes id/en/tags', str_contains(omochix_learn_gallery_search_text(omochix_learn_gallery_get_item('pop-in')), '#001 001 pop-in ポップイン pop-in'));
check('level label: fallback to beginner', '初級' === omochix_learn_gallery_level_label('nope') && '上級' === omochix_learn_gallery_level_label('advanced'));
check('items for prompt: reverse lookup by related.prompts, id order', ['parallax', 'gradient-shift', 'floating-blobs', 'light-sweep', 'particles'] === array_keys(omochix_learn_gallery_items_for_prompt('video-image-to-video-motion-direction')));
check('items for prompt: unknown or empty slug is empty', [] === omochix_learn_gallery_items_for_prompt('nope') && [] === omochix_learn_gallery_items_for_prompt(''));
check('catalog: every related prompt slug is a valid slug', !array_filter(omochix_learn_gallery_get_catalog()['items'], static fn($i) => array_filter($i['related']['prompts'], static fn($s) => !omochix_learn_gallery_is_slug($s))));

// ------------------------------------------------------------- breadcrumbs
$trail = [['url' => 'https://example.test/', 'text' => 'ホーム'], ['url' => 'https://example.test/learn/', 'text' => 'Learn']];
$crumbItem = omochix_learn_gallery_get_item('pop-in');
$crumbs = omochix_learn_gallery_breadcrumb_links($trail, $crumbItem, 'https://example.test/learn/motion/', '動く見本');
check('breadcrumbs: gallery list appended as a link before the sample', 3 === count($crumbs) && ['url' => 'https://example.test/learn/motion/', 'text' => '動く見本'] === $crumbs[2]);
check('breadcrumbs: not duplicated when the list is already the last link', 3 === count(omochix_learn_gallery_breadcrumb_links($crumbs, $crumbItem, 'https://example.test/learn/motion', '動く見本')));
check('breadcrumbs: untouched when the gallery has no url', $trail === omochix_learn_gallery_breadcrumb_links($trail, $crumbItem, '', '動く見本'));

// --------------------------------------------------- prompt page asset gate
$GLOBALS['ctx']['singular'] = true;
$GLOBALS['ctx']['queried_post'] = new WP_Post(['ID' => 500, 'post_type' => 'prompt', 'post_name' => 'video-image-to-video-motion-direction']);
check('prompt gate: no gallery page published -> no assets', !omochix_learn_gallery_is_prompt_with_samples());
$GLOBALS['ctx']['singular'] = false;

// ---------------------------------------------------------------- filters
$f = omochix_learn_gallery_sanitize_filters(['cat' => 'ui', 'level' => 'advanced', 'tech' => 'js', 'q' => '  pop  ']);
check('sanitize: valid values kept', ['cat' => 'ui', 'level' => 'advanced', 'tech' => 'js', 'q' => 'pop'] === $f);
$f = omochix_learn_gallery_sanitize_filters(['cat' => 'nope', 'level' => 'expert', 'tech' => 'wasm', 'q' => ['x']]);
check('sanitize: unknown values dropped', ['cat' => '', 'level' => '', 'tech' => '', 'q' => ''] === $f);
check('sanitize: q is capped at 60 chars and tags stripped', 60 === mb_strlen(omochix_learn_gallery_sanitize_filters(['q' => str_repeat('a', 80)])['q']) && 'x' === omochix_learn_gallery_sanitize_filters(['q' => '<b>x</b>'])['q']);
check('sanitize: category slug normalised', 'ui' === omochix_learn_gallery_sanitize_filters(['cat' => 'UI'])['cat']);
check('sanitize: empty source', ['cat' => '', 'level' => '', 'tech' => '', 'q' => ''] === omochix_learn_gallery_sanitize_filters([]));

// --------------------------------------------------------------- composers
$item = omochix_learn_gallery_get_item('pop-in');
$prompt = omochix_learn_gallery_prompt_text($item, 'https://example.test/learn/motion/pop-in/');
check('prompt: names, category and id', str_contains($prompt, '「ポップイン（Pop-in）」') && str_contains($prompt, '#001（登場・退場）'));
check('prompt: ask, every spec line, use and url', str_contains($prompt, $item['prompt']['ask']) && !array_filter($item['prompt']['spec'], static fn($s) => !str_contains($prompt, '- ' . $s)) && str_contains($prompt, $item['prompt']['use']) && str_contains($prompt, 'https://example.test/learn/motion/pop-in/'));
check('prompt: tech names and reduced-motion condition', str_contains($prompt, 'CSS') && str_contains($prompt, 'prefers-reduced-motion'));
check('prompt: no url line when url empty', !str_contains(omochix_learn_gallery_prompt_text($item), '見本（動くプレビュー'));
$code = omochix_learn_gallery_code_text($item, 'https://example.test/x/');
$plainItem = omochix_learn_gallery_get_item('fade-up');
$plainItem['params'] = []; // every shipped sample is adjustable since Phase 2D; this stands in for one that is not
$sVals = ['cycle' => 4.0, 'overshoot' => 'soft', 'color' => '#ff0000', 'badge_delay' => 0.35]; // pop-in with two values changed
$plainCode = omochix_learn_gallery_code_text($plainItem, 'https://example.test/x/');
check('code: sample without params has style + html and no script (unchanged shape)', str_contains($plainCode, "<style>\n" . $plainItem['preview']['css']) && str_contains($plainCode, $plainItem['preview']['html']) && !str_contains($plainCode, '<script>') && !str_contains($plainCode, ':root{'));
check('code: adjustable css-only sample at its defaults has no :root block (fallbacks carry the look)', str_contains($code, "<style>\n" . $item['preview']['css']) && !str_contains($code, ':root{') && !str_contains($code, '<script>'));
check('code: adjusted css-only sample carries the changed values in a :root block first and no script', str_contains(omochix_learn_gallery_code_text($item, 'https://example.test/x/', $sVals), "<style>\n:root{--p-cycle:4s;--p-color:#ff0000;--p-badge-delay:0.35s}\n" . $item['preview']['css']) && !str_contains(omochix_learn_gallery_code_text($item, 'https://example.test/x/', $sVals), '<script>'));
check('code: header comment names the sample', str_starts_with($code, '<!-- ポップイン (Pop-in) | OmochiX Learn 動く見本 #001 | https://example.test/x/ -->'));
$jsItem = omochix_learn_gallery_get_item('count-up');
$jsCode = omochix_learn_gallery_code_text($jsItem);
$jsVals = ['target' => 500.0, 'duration' => 2.2, 'easing' => 'out', 'size' => 56.0];
$jsCodeS = omochix_learn_gallery_code_text($jsItem, '', $jsVals);
check('code: adjusted js sample includes shim, then window.omxParams with the changed value, then sample js', str_contains($jsCodeS, '<script>') && strpos($jsCodeS, 'var omx = window.omx') < strpos($jsCodeS, 'window.omxParams={"target":500};') && strpos($jsCodeS, 'window.omxParams={"target":500};') < strpos($jsCodeS, $jsItem['preview']['js']));
check('code: js sample at its defaults has the shim and the sample js but no window.omxParams', str_contains($jsCode, 'var omx = window.omx') && str_contains($jsCode, $jsItem['preview']['js']) && !str_contains($jsCode, 'window.omxParams='));
check('code: shim provides param() and onParams()', str_contains(omochix_learn_gallery_js_shim(), 'param: function') && str_contains(omochix_learn_gallery_js_shim(), 'onParams: function'));

$doc = omochix_learn_gallery_preview_document($item);
check('doc: html document with stage size and theme', str_starts_with($doc, '<!doctype html><html lang="ja" data-theme="light" data-stage-w="320" data-stage-h="180">') && str_ends_with($doc, '</body></html>'));
check('doc: runtime css and js inlined (sandbox frames cannot fetch theme files everywhere)', str_contains($doc, '.omx-stage') && str_contains($doc, 'omx:ready') && str_contains($doc, 'data-paused'));
check('doc: sample css/html inlined inside stage', str_contains($doc, '<style>' . $item['preview']['css'] . '</style>') && str_contains($doc, '<div class="omx-stage">' . $item['preview']['html'] . '</div>'));
check('doc: css-only sample has exactly one script (the runtime)', 1 === substr_count($doc, '<script>'));
$jsDoc = omochix_learn_gallery_preview_document($jsItem, null, $jsVals);
check('doc: js sample at its defaults has runtime and sample scripts only', 2 === substr_count(omochix_learn_gallery_preview_document($jsItem), '<script>'));
check('doc: adjusted js sample has runtime, params and sample scripts in that order', 3 === substr_count($jsDoc, '<script>') && str_contains($jsDoc, $jsItem['preview']['js'] . '</script></body>') && strpos($jsDoc, 'omx:ready') < strpos($jsDoc, '<script>window.omxParams=') && strpos($jsDoc, '<script>window.omxParams=') < strpos($jsDoc, $jsItem['preview']['js']));
check('doc: params style is empty at the defaults and carries the changed values otherwise', str_contains($doc, '<style id="omx-params"></style>') && str_contains(omochix_learn_gallery_preview_document($item, null, $sVals), '<style id="omx-params">:root{--p-cycle:4s;--p-color:#ff0000;--p-badge-delay:0.35s}</style>') && str_contains(omochix_learn_gallery_preview_document($plainItem), '<style id="omx-params"></style>'));
check('doc: no placeholder left unfilled', !preg_match('/\{\{[a-z_]+\}\}/', $doc) && !preg_match('/\{\{[a-z_]+\}\}/', $jsDoc));
$template = omochix_learn_gallery_preview_template();
check('template: carries every placeholder exactly once', !array_filter(['{{stage_w}}', '{{stage_h}}', '{{title}}', '{{runtime_css}}', '{{params_css}}', '{{css}}', '{{html}}', '{{runtime_js}}', '{{params_js}}', '{{js}}'], static fn($p) => 1 !== substr_count($template, $p)));
$blocks = omochix_learn_gallery_params_blocks($item, omochix_learn_gallery_sanitize_params($item));
check('template: assembling it like the browser does reproduces the PHP document', strtr($template, ['{{stage_w}}' => '320', '{{stage_h}}' => '180', '{{title}}' => esc_html($item['name']['ja']), '{{runtime_css}}' => omochix_learn_gallery_runtime('css'), '{{params_css}}' => $blocks['css'], '{{css}}' => $item['preview']['css'], '{{html}}' => $item['preview']['html'], '{{runtime_js}}' => omochix_learn_gallery_runtime('js'), '{{params_js}}' => '', '{{js}}' => '']) === $doc);

// ------------------------------------------------------------- playground
check('params: normalized definitions for the three prototypes', ['cycle', 'overshoot', 'color', 'badge_delay'] === array_column($item['params'], 'key') && ['target', 'duration', 'easing', 'size'] === array_column($jsItem['params'], 'key') && 5 === count(omochix_learn_gallery_get_item('gradient-shift')['params']));
check('params: every range reaches max and default from min in whole steps (native <input type=range> snaps the same way)', [] === array_filter(array_merge(...array_map(static fn($i) => array_map(static fn($d) => [$i['slug'], $d], $i['params']), array_values(omochix_learn_gallery_get_items()))), static function ($pair) { $d = $pair[1]; if ('range' !== $d['type']) { return false; } $n = ($d['max'] - $d['min']) / $d['step']; $m = ($d['default'] - $d['min']) / $d['step']; return abs($n - round($n)) > 1e-6 || abs($m - round($m)) > 1e-6; }));
check('params: every shipped sample is adjustable with 3 to 5 controls', [] === array_filter(omochix_learn_gallery_get_items(), static fn($i) => count($i['params']) < 3 || count($i['params']) > 5) && 20 === count(array_filter(omochix_learn_gallery_get_items(), static fn($i) => [] !== $i['params'])));
// Defaults preserve the shipped look: every var(--p-x, fallback) in a sample's CSS
// must fall back to the default's own value (or to a theme variable for colours).
$fallbackIssues = [];
foreach (omochix_learn_gallery_get_items() as $fbSlug => $fbItem) {
    foreach ($fbItem['params'] as $def) {
        if ('js' === $def['target']) { continue; }
        $css = $fbItem['preview']['css'];
        $pos = 0;
        $seen = 0;
        while (false !== ($at = strpos($css, 'var(' . $def['css_var'], $pos))) {
            $pos = $at + 4;
            $depth = 1; $i = $at + 4; $start = null;
            for (; $i < strlen($css) && $depth > 0; $i++) {
                $ch = $css[$i];
                if ('(' === $ch) { $depth++; }
                elseif (')' === $ch) { $depth--; }
                elseif (',' === $ch && 1 === $depth && null === $start) { $start = $i + 1; }
            }
            $seen++;
            $fallback = null === $start ? '' : substr($css, $start, $i - 1 - $start);
            if ('color' === $def['type']) { $ok = $fallback === $def['default'] || str_starts_with($fallback, 'var(--'); }
            elseif ('select' === $def['type']) { $ok = $fallback === array_values(array_filter($def['options'], static fn($o) => $o['value'] === $def['default']))[0]['css']; }
            else { $ok = '' !== $fallback && (float) $fallback === (float) $def['default'] && ($def['unit'] === preg_replace('/^[0-9.-]+/', '', $fallback)); }
            if (!$ok) { $fallbackIssues[] = "$fbSlug {$def['css_var']}: '$fallback'"; }
        }
        if (0 === $seen) { $fallbackIssues[] = "$fbSlug {$def['css_var']}: never read"; }
    }
}
check('params: every var(--p-x, fallback) in sample CSS falls back to the default value' . ($fallbackIssues ? ' [' . implode('; ', $fallbackIssues) . ']' : ''), [] === $fallbackIssues);
check('params: every default equals the shipped look (sanitize of nothing = defaults)', ['cycle' => 2.4, 'overshoot' => 'soft', 'color' => '#6e4bff', 'badge_delay' => 0.25] === omochix_learn_gallery_sanitize_params($item));
$s = omochix_learn_gallery_sanitize_params($item, ['cycle' => '99', 'overshoot' => 'huge', 'color' => 'FF0000', 'badge_delay' => 0.33, 'unknown' => 'x']);
check('params: sanitize clamps, snaps to step, accepts bare hex, rejects unknown option and drops unknown keys', ['cycle' => 4.0, 'overshoot' => 'soft', 'color' => '#ff0000', 'badge_delay' => 0.35] === $s);
check('params: sanitize rejects garbage', ['cycle' => 2.4, 'overshoot' => 'soft', 'color' => '#6e4bff', 'badge_delay' => 0.0] === omochix_learn_gallery_sanitize_params($item, ['cycle' => 'abc', 'color' => 'red', 'badge_delay' => -5]));
check('params: blocks are compact, deterministic and omit values equal to the default', ':root{--p-cycle:4s;--p-color:#ff0000;--p-badge-delay:0.35s}' === omochix_learn_gallery_params_blocks($item, $s)['css'] && '' === omochix_learn_gallery_params_blocks($item, $s)['js'] && '' === omochix_learn_gallery_params_blocks($item, omochix_learn_gallery_sanitize_params($item))['css']);
check('params: js blocks only for js targets, css block only for changed css params', 'window.omxParams={"target":500,"duration":1,"easing":"linear"};' === omochix_learn_gallery_params_blocks($jsItem, omochix_learn_gallery_sanitize_params($jsItem, ['target' => 512, 'duration' => 0.5, 'easing' => 'linear']))['js'] && '' === omochix_learn_gallery_params_blocks($jsItem, omochix_learn_gallery_sanitize_params($jsItem))['css'] && ':root{--p-size:72px}' === omochix_learn_gallery_params_blocks($jsItem, omochix_learn_gallery_sanitize_params($jsItem, ['size' => 72]))['css']);
check('params: number formatting has no trailing zeros', '0.25' === omochix_learn_gallery_format_number(0.25) && '400' === omochix_learn_gallery_format_number(400.0) && '0' === omochix_learn_gallery_format_number(0.00001));
check('params: code with values differs from the default code only by the inserted :root block', preg_replace('/<style>\n/', "<style>\n:root{--p-cycle:4s;--p-color:#ff0000;--p-badge-delay:0.35s}\n", $code, 1) === omochix_learn_gallery_code_text($item, 'https://example.test/x/', $s));
check('params: preview document with values carries the same :root block as the code', str_contains(omochix_learn_gallery_preview_document($item, null, $s), '<style id="omx-params">:root{--p-cycle:4s;'));
$ptext = omochix_learn_gallery_prompt_text($item, '', $s);
check('params: prompt lists adjusted values with labels, units and （既定） markers', str_contains($ptext, "調整した値:\n- 登場から退場までの1周期は4s\n- 行き過ぎ（オーバーシュート）は「少し」（既定）\n- アイコンの色: #ff0000\n- バッジは0.35s遅らせて出す\n") && !str_contains(omochix_learn_gallery_prompt_text($plainItem), '調整した値'));
check('params: prompt with defaults marks every line as 既定', 4 === substr_count(omochix_learn_gallery_prompt_text($item), '（既定）'));
$pj = json_decode(omochix_learn_gallery_client_json(['pop-in' => $item, 'fade-up' => $plainItem]), true);
check('client json: params and copy templates only for adjustable samples', 4 === count($pj['items']['pop-in']['params']) && str_contains($pj['items']['pop-in']['codeTemplate'], '{{params_css}}') && str_contains($pj['items']['pop-in']['promptTemplate'], '{{params_text}}') && [] === $pj['items']['fade-up']['params'] && !isset($pj['items']['fade-up']['codeTemplate']));
check('template: preview template exposes the params slots', str_contains($pj['template'], '<style id="omx-params">{{params_css}}</style>') && str_contains($pj['template'], '{{params_js}}{{js}}'));
// Validator: every rule the schema states.
$pv = static function (array $params, $css = 'x{a:var(--p-a) var(--p-b)}') { return omochix_learn_gallery_validate_params($params, ['css' => $css]); };
$good = ['key' => 'a', 'label' => 'A', 'type' => 'range', 'min' => 0, 'max' => 10, 'step' => 1, 'default' => 5, 'unit' => 'px', 'css_var' => '--p-a'];
check('validate params: a valid range passes', [] === $pv([$good]));
check('validate params: bad key', has_error($pv([['key' => 'Bad'] + $good]), 'invalid key'));
check('validate params: duplicate key', has_error($pv([$good, $good]), 'duplicate key'));
check('validate params: min >= max', has_error($pv([['min' => 10] + $good]), 'min must be below max'));
check('validate params: default outside range', has_error($pv([['default' => 11] + $good]), 'within min..max'));
check('validate params: step must be positive', has_error($pv([['step' => 0] + $good]), 'step must be positive'));
check('validate params: css_var pattern', has_error($pv([['css_var' => '--x'] + $good]), 'css_var must match'));
check('validate params: css_var must be read by the preview css', has_error($pv([['css_var' => '--p-zzz'] + $good]), 'never reads var(--p-zzz)'));
check('validate params: js target must not declare css_var', has_error($pv([['target' => 'js'] + $good]), 'must not declare css_var'));
check('validate params: unsupported unit', has_error($pv([['unit' => 'pt'] + $good]), 'unsupported unit'));
check('validate params: color default must be hex', has_error($pv([['key' => 'c', 'label' => 'C', 'type' => 'color', 'default' => 'red', 'css_var' => '--p-b']]), '#rrggbb'));
check('validate params: select needs options with plain css and a default among them', has_error($pv([['key' => 'c', 'label' => 'C', 'type' => 'select', 'options' => [['value' => 'a', 'label' => 'A', 'css' => 'url(x)'], ['value' => 'b', 'label' => 'B', 'css' => 'ease']], 'default' => 'zz', 'css_var' => '--p-b']]), 'css must be a plain CSS value') && has_error($pv([['key' => 'c', 'label' => 'C', 'type' => 'select', 'options' => [['value' => 'a', 'label' => 'A', 'css' => 'ease'], ['value' => 'b', 'label' => 'B', 'css' => 'linear']], 'default' => 'zz', 'css_var' => '--p-b']]), 'default must be one of'));
check('validate params: text type is not accepted', has_error($pv([['key' => 't', 'label' => 'T', 'type' => 'text', 'default' => 'x']]), 'type must be one of'));
check('validate params: at most 8', has_error($pv(array_map(static fn($i) => ['key' => 'k' . $i, 'css_var' => '--p-a'] + $good, range(1, 9))), 'at most 8'));
check('validate params: prompt must contain {value}', has_error($pv([['prompt' => 'no placeholder'] + $good]), 'containing {value}'));
check('validate catalog: shipped prototypes pass the full validator', [] === omochix_learn_gallery_validate_catalog(json_decode(file_get_contents(omochix_learn_gallery_catalog_file()), true)));
check('doc: custom stage size', str_contains(omochix_learn_gallery_preview_document($item, ['width' => 640, 'height' => 360]), 'data-stage-w="640" data-stage-h="360"'));
$evil = $item; $evil['name']['ja'] = '<x>&';
check('doc: title is escaped', str_contains(omochix_learn_gallery_preview_document($evil), '<title>&lt;x&gt;&amp;</title>'));

$json = omochix_learn_gallery_client_json(['pop-in' => $item, 'count-up' => $jsItem]);
check('client json: decodes and carries fragments + label, runtime and template once', ($d = json_decode($json, true)) && isset($d['items']['pop-in']['html'], $d['items']['pop-in']['css'], $d['items']['pop-in']['title']) && !isset($d['items']['pop-in']['doc']) && 'ポップインのプレビュー' === $d['items']['pop-in']['label'] && 320 === $d['stage']['width'] && $d['template'] === omochix_learn_gallery_preview_template() && str_contains($d['runtime']['js'], 'omx:ready') && 1 === substr_count($json, 'omx:ready'));
check('client json: runtime appears once even with several items', 1 === substr_count(omochix_learn_gallery_client_json(['pop-in' => $item, 'count-up' => $jsItem, 'fade-up' => $plainItem]), 'omx:ready'));
check('client json: no raw angle brackets (safe inside <script>)', !str_contains($json, '<') && !str_contains($json, '>'));
check('client json: unicode kept readable', str_contains($json, 'ポップイン'));
check('registry: stores by slug', isset(omochix_learn_gallery_registry($item)['pop-in']) && 1 === count(omochix_learn_gallery_registry()));

// ------------------------------------------------------------------- urls
check('url: empty when page is not published', '' === omochix_learn_gallery_url() && '' === omochix_learn_gallery_url($item));
$GLOBALS['pages']['learn/motion'] = new WP_Post(['ID' => 42, 'post_name' => 'learn/motion']);
$GLOBALS['pages_by_id'][42] = 'learn/motion';
// page_id is memoised per request; re-require is not possible, so test through a fresh closure of the same logic.
$page = get_page_by_path('learn/motion', null, 'page');
check('url: page lookup requires publish status', 42 === $page->ID && 'publish' === $page->post_status);
$GLOBALS['pages']['learn/motion']->post_status = 'draft';
check('url: draft page yields no url (fresh lookup)', !(($p = get_page_by_path('learn/motion')) && 'publish' === $p->post_status));
$GLOBALS['pages']['learn/motion']->post_status = 'publish';

// Routing helpers that read the query.
$GLOBALS['query_vars'] = [OMOCHIX_LEARN_GALLERY_QUERY_VAR => 'Pop-In', OMOCHIX_LEARN_GALLERY_PARTIAL_VAR => '1'];
check('routing: requested slug is sanitized', 'pop-in' === omochix_learn_gallery_requested_slug());
check('routing: partial flag', omochix_learn_gallery_is_partial_request());
$GLOBALS['query_vars'] = [];
check('routing: no slug / no partial by default', '' === omochix_learn_gallery_requested_slug() && !omochix_learn_gallery_is_partial_request());
check('routing: not a gallery request outside pages', !omochix_learn_gallery_is_request());
check('routing: rewrite constants', 'learn/motion' === OMOCHIX_LEARN_GALLERY_PATH && 'omx_motion' === OMOCHIX_LEARN_GALLERY_QUERY_VAR);
check('url: item url builds from slug', str_ends_with(user_trailingslashit(trailingslashit('https://example.test/learn/motion/') . $item['slug']), '/learn/motion/pop-in/'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
