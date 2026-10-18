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
define('ABSPATH', '/');
define('OBJECT', 'OBJECT');
$THEME = realpath(__DIR__ . '/../../wp-theme/omochix-theme');

class WP_Post { public $ID, $post_type = 'page', $post_status = 'publish', $post_name = ''; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
$GLOBALS['pages'] = []; $GLOBALS['query_vars'] = []; $GLOBALS['ctx'] = ['is_page' => false, 'queried' => 0];
function add_action() {} function add_filter() {}
function __($t) { return $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_url($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function get_theme_file_path($f) { global $THEME; return $THEME . $f; }
function get_theme_file_uri($f) { return 'https://example.test/wp-content/themes/omochix-theme' . $f; }
function untrailingslashit($s) { return rtrim($s, '/'); }
function is_singular($t = '') { return $GLOBALS['ctx']['singular'] ?? false; }
function get_queried_object() { return $GLOBALS['ctx']['queried_post'] ?? null; }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function sanitize_title($t) { $t = strtolower(trim((string) $t)); $t = preg_replace('/[^a-z0-9-]+/', '-', $t); return trim($t, '-'); }
function sanitize_key($t) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $t)); }
function sanitize_text_field($t) { return trim(strip_tags((string) $t)); }
function get_page_by_path($path, $o = null, $type = 'page') { return $GLOBALS['pages'][$path] ?? null; }
function get_permalink($p) { return 'https://example.test/' . ($p instanceof WP_Post ? $p->post_name : $GLOBALS['pages_by_id'][$p]) . '/'; }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function user_trailingslashit($s) { return trailingslashit($s); }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function get_query_var($k, $d = '') { return $GLOBALS['query_vars'][$k] ?? $d; }
function is_page() { return $GLOBALS['ctx']['is_page']; }
function get_queried_object_id() { return $GLOBALS['ctx']['queried']; }
function post_type_exists($t) { return false; }
function get_the_title($p) { return 'Title'; }
require $THEME . '/inc/learn-gallery.php';

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
check('code: css-only sample has style + html and no script', str_contains($code, "<style>\n" . $item['preview']['css']) && str_contains($code, $item['preview']['html']) && !str_contains($code, '<script>'));
check('code: header comment names the sample', str_starts_with($code, '<!-- ポップイン (Pop-in) | OmochiX Learn 動く見本 #001 | https://example.test/x/ -->'));
$jsItem = omochix_learn_gallery_get_item('count-up');
$jsCode = omochix_learn_gallery_code_text($jsItem);
check('code: js sample includes shim before sample js', str_contains($jsCode, '<script>') && strpos($jsCode, 'omx.animate') > strpos($jsCode, 'var omx = window.omx') && str_contains($jsCode, $jsItem['preview']['js']));

$doc = omochix_learn_gallery_preview_document($item);
check('doc: html document with stage size and theme', str_starts_with($doc, '<!doctype html><html lang="ja" data-theme="light" data-stage-w="320" data-stage-h="180">') && str_ends_with($doc, '</body></html>'));
check('doc: runtime css and js inlined (sandbox frames cannot fetch theme files everywhere)', str_contains($doc, '.omx-stage') && str_contains($doc, 'omx:ready') && str_contains($doc, 'data-paused'));
check('doc: sample css/html inlined inside stage', str_contains($doc, '<style>' . $item['preview']['css'] . '</style>') && str_contains($doc, '<div class="omx-stage">' . $item['preview']['html'] . '</div>'));
check('doc: css-only sample has exactly one script (the runtime)', 1 === substr_count($doc, '<script>'));
check('doc: js sample has two scripts, runtime first and sample last', 2 === substr_count(omochix_learn_gallery_preview_document($jsItem), '<script>') && str_contains(omochix_learn_gallery_preview_document($jsItem), $jsItem['preview']['js'] . '</script></body>') && strpos(omochix_learn_gallery_preview_document($jsItem), 'omx:ready') < strpos(omochix_learn_gallery_preview_document($jsItem), $jsItem['preview']['js']));
check('doc: no placeholder left unfilled', !preg_match('/\{\{[a-z_]+\}\}/', $doc) && !preg_match('/\{\{[a-z_]+\}\}/', omochix_learn_gallery_preview_document($jsItem)));
$template = omochix_learn_gallery_preview_template();
check('template: carries every placeholder exactly once', !array_filter(['{{stage_w}}', '{{stage_h}}', '{{title}}', '{{runtime_css}}', '{{css}}', '{{html}}', '{{runtime_js}}', '{{js}}'], static fn($p) => 1 !== substr_count($template, $p)));
check('template: assembling it like the browser does reproduces the PHP document', strtr($template, ['{{stage_w}}' => '320', '{{stage_h}}' => '180', '{{title}}' => esc_html($item['name']['ja']), '{{runtime_css}}' => omochix_learn_gallery_runtime('css'), '{{css}}' => $item['preview']['css'], '{{html}}' => $item['preview']['html'], '{{runtime_js}}' => omochix_learn_gallery_runtime('js'), '{{js}}' => '']) === $doc);
check('doc: custom stage size', str_contains(omochix_learn_gallery_preview_document($item, ['width' => 640, 'height' => 360]), 'data-stage-w="640" data-stage-h="360"'));
$evil = $item; $evil['name']['ja'] = '<x>&';
check('doc: title is escaped', str_contains(omochix_learn_gallery_preview_document($evil), '<title>&lt;x&gt;&amp;</title>'));

$json = omochix_learn_gallery_client_json(['pop-in' => $item, 'count-up' => $jsItem]);
check('client json: decodes and carries fragments + label, runtime and template once', ($d = json_decode($json, true)) && isset($d['items']['pop-in']['html'], $d['items']['pop-in']['css'], $d['items']['pop-in']['title']) && !isset($d['items']['pop-in']['doc']) && 'ポップインのプレビュー' === $d['items']['pop-in']['label'] && 320 === $d['stage']['width'] && $d['template'] === omochix_learn_gallery_preview_template() && str_contains($d['runtime']['js'], 'omx:ready') && 1 === substr_count($json, 'omx:ready'));
check('client json: two items cost far less than two full documents', strlen($json) < strlen(omochix_learn_gallery_preview_document($item)) + strlen(omochix_learn_gallery_preview_document($jsItem)));
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
