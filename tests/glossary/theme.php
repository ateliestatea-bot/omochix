<?php
/**
 * AI glossary, theme side: decisions (404 / 400 / preview / ok), SEO,
 * structured data, templates (escaping, leaks) and related links, without
 * WordPress. Uses tests/industry-hub/stub.php read-only, the Core glossary
 * module and the theme module inc/web2/glossary.php.
 *
 * wp_kses is replaced by a strict stand-in here (allowed tags kept, every
 * attribute but an https href dropped): the real kses is checked on the
 * isolated WordPress (docs/web2/kh/wp-test-plan.md).
 *
 * Run: php tests/glossary/theme.php
 */
$REPO  = realpath(__DIR__ . '/../..');
$THEME = $REPO . '/wp-theme/omochix-theme';

if ('--no-core' === ($argv[1] ?? '')) {
    // T-TH-1: the theme module without the Core glossary (older Core, or its module off).
    require $REPO . '/tests/industry-hub/stub.php';
    function is_post_type_archive($t = '') { return false; }
    ob_start();
    require $THEME . '/inc/web2/glossary.php';
    $out = (string) ob_get_clean();
    $GLOBALS['ctx'] = ['is_singular' => true];
    echo json_encode(['enabled' => omochix_glossary_enabled(), 'decide' => omochix_glossary_decide(true)['action'], 'out' => $out, 'errors' => $GLOBALS['fail']]);
    exit(0);
}

require $REPO . '/tests/industry-hub/stub.php';
// ---------------------------------------------- stand-ins the stub lacks
if (!function_exists('is_post_type_archive')) { function is_post_type_archive($t = '') { return !empty($GLOBALS['ctx']['is_archive']); } }
if (!function_exists('get_post_type_archive_link')) { function get_post_type_archive_link($t) { return 'omx_glossary' === $t ? 'https://example.test/glossary/' : false; } }
if (!function_exists('get_page_uri')) { function get_page_uri($p) { $p = get_post($p); return $p ? (string) ($GLOBALS['page_uri'][$p->ID] ?? $p->post_name) : ''; } }
if (!function_exists('esc_html_e')) { function esc_html_e($t, $d = 'default') { echo esc_html($t); } }
if (!function_exists('esc_attr_e')) { function esc_attr_e($t, $d = 'default') { echo esc_attr($t); } }
if (!function_exists('sanitize_html_class')) { function sanitize_html_class($c) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c); } }
if (!function_exists('get_header')) { function get_header() {} }
if (!function_exists('get_footer')) { function get_footer() {} }
function get_404_template() { return ''; }
if (!function_exists('set_query_var')) { function set_query_var($k, $v) { $GLOBALS['query_vars'][$k] = $v; } }
if (!function_exists('is_trackback')) { function is_trackback() { return !empty($GLOBALS['ctx']['is_trackback']); } }
if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }
if (!function_exists('get_page_by_path')) { function get_page_by_path($path, $output = OBJECT, $type = 'page') { foreach ($GLOBALS['posts'] as $p) { if ($type === $p->post_type && $path === $p->post_name) { return $p; } } return null; } }
if (!function_exists('wp_style_is')) { function wp_style_is($h, $l = 'enqueued') { return false; } }
if (!function_exists('get_theme_file_path')) { function get_theme_file_path($p = '') { return $GLOBALS['THEME'] . $p; } }
if (!function_exists('get_theme_file_uri')) { function get_theme_file_uri($p = '') { return 'https://example.test/wp-content/themes/omochix-theme' . $p; } }
if (!function_exists('get_template_part')) { function get_template_part($slug, $name = null, $args = []) { include $GLOBALS['THEME'] . '/' . $slug . '.php'; } }
if (!function_exists('wp_kses')) {
    function wp_kses($html, $allowed, $protocols = []) {
        $html = preg_replace('#<(script|style|iframe|svg|object)\b[^>]*>.*?</\1\s*>#is', '', (string) $html);
        return preg_replace_callback('#<(/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*)>#', static function ($m) use ($allowed) {
            $tag = strtolower($m[2]);
            if (!isset($allowed[$tag])) { return ''; }
            if ('/' === $m[1]) { return "</$tag>"; }
            if ('a' === $tag && preg_match('/\bhref\s*=\s*"(https:\/\/[^"]*)"/i', $m[3], $h)) { return '<a href="' . htmlspecialchars($h[1], ENT_QUOTES) . '">'; }
            return "<$tag>";
        }, $html);
    }
}
$GLOBALS['THEME'] = $THEME;

require $REPO . '/wp-plugin/omochix-core/includes/web2/glossary-loader.php';
$theme_hooks_before = array_keys($GLOBALS['hooks']);
require $THEME . '/inc/web2/glossary.php';
check('setup: Core glossary and theme module loaded', omochix_core_glossary_loaded() && function_exists('omochix_glossary_decide'));

// ================================================ A: the theme keeps it closed
add_filter('omochix_core_glossary_public', $early = static fn() => true, 5);
check('A the theme answers false over an earlier true (Phase 1 stays closed)', false === omochix_core_glossary_public());
remove_filter('omochix_core_glossary_public', $early, 5);
ih_boot();
check('A closed: post type registered closed', false === $GLOBALS['registered']['post_types']['omx_glossary']['public']);

// ---------------------------------------------------------- fixtures
wp_insert_term('AIの基礎概念', 'omx_glossary_category', ['slug' => 'basics']);
wp_insert_term('生成AI・大規模言語モデル', 'omx_glossary_category', ['slug' => 'llm']);
wp_insert_term('画像・音声・動画生成', 'omx_glossary_category', ['slug' => 'multimodal']);
update_term_meta(ih_term('omx_glossary_category', 'basics')->term_id, 'glossary_category_lead', 'AIの基本の言葉を集めたカテゴリの説明文です。テスト用。');
function gl_term($slug, $title, $status = 'publish', array $over = []) {
    $content = '<p>' . str_repeat('これはテスト用の説明文で、内容に意味はありません。', 17) . '</p>';
    $id = ih_add_post(array_merge(['post_type' => 'omx_glossary', 'post_status' => $status, 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content], $over['post'] ?? []));
    $meta = ['glossary_reading' => 'てすと', 'glossary_en' => 'Test', 'glossary_aliases' => [], 'glossary_summary' => 'これはテンプレートのテストだけに使う架空の用語で、公開する内容ではありません。説明文の長さを満たす文です。',
        'glossary_sources' => [['url' => 'https://docs.example.org/spec', 'title' => '仕様書', 'publisher' => 'Example Docs', 'kind' => 'docs', 'checked_on' => '2026-11-20']],
        'glossary_verified_on' => '2026-11-21', 'glossary_review_days' => 180, 'glossary_ai_assisted' => true, 'glossary_level' => 'beginner'];
    foreach (array_merge($meta, $over['meta'] ?? []) as $k => $v) { update_post_meta($id, $k, $v); }
    wp_set_object_terms($id, $over['cats'] ?? ['basics'], 'omx_glossary_category');
    return $id;
}
$ids = [];
foreach (range(1, 10) as $i) { $ids[] = gl_term("term-$i", "用語$i", 'publish', ['meta' => ['glossary_reading' => 'あ' . str_repeat('い', $i)]]); }
$llm1 = gl_term('llm-one', 'LLM用語', 'publish', ['cats' => ['llm']]);
$draft = gl_term('secret-draft', '下書きの用語', 'draft');
$pw = gl_term('secret-password', 'パスワード付きの用語', 'publish', ['post' => ['post_password' => 'x']]);
$failing = gl_term('secret-failing', '出典なしの用語', 'publish', ['meta' => ['glossary_sources' => []]]);
$stale = gl_term('stale-term', '期限切れの用語', 'publish', ['meta' => ['glossary_verified_on' => '2026-03-01', 'glossary_sources' => [['url' => 'https://docs.example.org/spec', 'title' => '仕様書', 'publisher' => 'Example Docs', 'kind' => 'docs', 'checked_on' => '2026-03-01']]]]);
$GLOBALS['caps'] = [];

function gl_req(array $ctx, array $vars = []) { $GLOBALS['ctx'] = $ctx; $GLOBALS['query_vars'] = $vars; unset($GLOBALS['status_header']); return omochix_glossary_decide(true); }
function gl_render($file) { ob_start(); include $GLOBALS['THEME'] . '/' . $file; return (string) ob_get_clean(); }

// ================================================ B: closed = like missing
$d = gl_req(['is_archive' => true]);
check('B closed: not a glossary request, nothing decided', 'none' === $d['action'] && false === omochix_glossary_is_request());
$out = gl_render('archive-omx_glossary.php');
check('B closed: the archive template answers 404 and shows no term', 404 === ($GLOBALS['status_header'] ?? 0) && false === strpos($out, '用語1'));
gl_req(['is_singular' => true, 'queried' => get_post($ids[0])]);
$out = gl_render('single-omx_glossary.php');
check('B closed: the term template answers 404 and shows nothing', 404 === ($GLOBALS['status_header'] ?? 0) && '' === trim($out));
gl_req(['is_tax' => 'omx_glossary_category', 'queried' => ih_term('omx_glossary_category', 'basics')]);
check('B closed: the category template answers 404', '' === trim(gl_render('taxonomy-omx_glossary_category.php')) && 404 === ($GLOBALS['status_header'] ?? 0));

// ================================================ C: open (test only)
add_filter('omochix_core_glossary_public', $open = static fn() => true, 20);
$d = gl_req(['is_archive' => true]);
check('C open archive: ok and indexable with 10+ indexable terms', 'ok' === $d['action'] && 'archive' === $d['kind'] && true === $d['index']);
$visible = array_map(static fn($p) => $p->post_name, omochix_glossary_visible_terms());
check('C visible terms exclude draft, password, failing; include the stale one (noindex but shown)', !array_intersect(['secret-draft', 'secret-password', 'secret-failing'], $visible) && in_array('stale-term', $visible, true) && 12 === count($visible));
check('C sorted by reading', array_search('term-1', $visible, true) < array_search('term-2', $visible, true));
$out = gl_render('archive-omx_glossary.php');
check('C archive page lists visible terms and none of the hidden ones', false !== strpos($out, '用語1') && false === strpos($out, '下書きの用語') && false === strpos($out, 'パスワード付き') && false === strpos($out, '出典なし') && false === strpos($out, 'secret-'));
check('C archive page: filter input (hidden until JS), live status, category links, "all" is current', 1 === preg_match('/<input[^>]+id="glossary-filter"[^>]+hidden>/', $out) && false !== strpos($out, 'aria-live="polite"') && false !== strpos($out, '<label class="glossary-search__label" for="glossary-filter">') && 1 === preg_match('#<a href="https://example.test/glossary/" aria-current="page">#', $out));
check('C archive page: one h1, category headings as h2, main landmark', 1 === substr_count($out, '<h1') && false !== strpos($out, '<h2 class="glossary-group__title"') && false !== strpos($out, '<main class="glossary glossary--index" id="main-content">'));
check('C the filter script writes no PHP value and no URL', 1 === preg_match('#<script>(.*?)</script>#s', $out, $m) && false === strpos($m[1], 'http') && false === strpos($m[1], 'innerHTML'));
check('C each card carries its lowercase search text', false !== strpos($out, 'data-glossary-text="用語1 あい test"'));
foreach (['paged 2' => [['is_archive' => true], ['paged' => 2]], 'feed' => [['is_archive' => true, 'is_feed' => true], []], 'embed' => [['is_singular' => true, 'is_embed' => true, 'queried' => get_post($ids[0])], []]] as $name => [$ctx, $vars]) {
    check("C $name: 404", 'not_found' === gl_req($ctx, $vars)['action']);
}
check('C ?s= on the list: 400', 'bad_request' === gl_req(['is_archive' => true, 'is_search' => true])['action']);
$d = gl_req(['is_tax' => 'omx_glossary_category', 'queried' => ih_term('omx_glossary_category', 'basics')]);
check('C category with 3+ indexable terms: ok and indexable', 'ok' === $d['action'] && 'category' === $d['kind'] && true === $d['index']);
$out = gl_render('taxonomy-omx_glossary_category.php');
check('C category page: its terms, the lead, current category marked, no other category\'s term', false !== strpos($out, '用語1') && false === strpos($out, 'LLM用語') && false !== strpos($out, 'テスト用。') && 1 === preg_match('#<a href="https://example.test/learn/industries/basics/" aria-current="page">#', $out));
$d = gl_req(['is_tax' => 'omx_glossary_category', 'queried' => ih_term('omx_glossary_category', 'llm')]);
check('C category with 1 term: ok but noindex', 'ok' === $d['action'] && false === $d['index'] && true === omochix_glossary_is_noindex());
check('C category with no visible term: 404', 'not_found' === gl_req(['is_tax' => 'omx_glossary_category', 'queried' => ih_term('omx_glossary_category', 'multimodal')])['action']);

// single
$d = gl_req(['is_singular' => true, 'queried' => get_post($ids[0])]);
check('C visible term: ok, indexable, no preview', 'ok' === $d['action'] && $d['index'] && !$d['preview']);
foreach (['draft' => $draft, 'password' => $pw, 'failing the rules (live, not demoted)' => $failing] as $name => $id) {
    $d = gl_req(['is_singular' => true, 'queried' => get_post($id)]);
    check("C $name term, visitor: 404", 'not_found' === $d['action']);
    $out = gl_render('single-omx_glossary.php');
    check("C $name term, visitor: the template shows nothing of it", '' === trim($out) && 404 === ($GLOBALS['status_header'] ?? 0));
}
$GLOBALS['caps'] = ['edit_post' => true];
$d = gl_req(['is_singular' => true, 'queried' => get_post($draft)]);
check('C draft term, editor: preview, noindex', 'ok' === $d['action'] && $d['preview'] && omochix_glossary_is_noindex());
$r = omochix_glossary_wp_robots(['index' => true, 'follow' => true, 'max-image-preview' => 'large']);
check('C preview robots: noindex, nofollow', !empty($r['noindex']) && !empty($r['nofollow']) && !isset($r['index']) && !isset($r['follow']));
$out = gl_render('single-omx_glossary.php');
check('C preview page is marked as a preview', false !== strpos($out, '未公開プレビュー'));
$GLOBALS['caps'] = [];
$d = gl_req(['is_singular' => true, 'queried' => get_post($stale)]);
check('C stale term (past review + 30 days): shown, noindex, no canonical', 'ok' === $d['action'] && !$d['index'] && '' === omochix_glossary_canonical_url());

// ================================================ D: SEO
gl_req(['is_singular' => true, 'queried' => get_post($ids[0])]);
check('D term title', '用語1とは？意味をわかりやすく解説｜AI用語集' === omochix_glossary_meta_title('x'));
check('D term description = summary', get_post_meta($ids[0], 'glossary_summary', true) === omochix_glossary_meta_description('x'));
check('D term canonical = its URL', get_permalink($ids[0]) === omochix_glossary_canonical('https://example.test/other/'));
check('D indexable term: Slim SEO index untouched, robots untouched', true === omochix_glossary_slim_seo_index(true) && ['index' => true] === omochix_glossary_wp_robots(['index' => true]));
$bc = omochix_glossary_breadcrumbs([]);
check('D breadcrumbs: Home › AI用語集 › category › term', ['Home', 'AI用語集', 'AIの基礎概念', '用語1'] === array_column($bc, 'text'));
gl_req(['is_tax' => 'omx_glossary_category', 'queried' => ih_term('omx_glossary_category', 'llm')]);
check('D noindex page: Slim SEO index false, robots noindex follow, canonical empty', false === omochix_glossary_slim_seo_index(true) && !empty(omochix_glossary_wp_robots([])['noindex']) && !empty(omochix_glossary_wp_robots([])['follow']) && '' === omochix_glossary_canonical('https://example.test/x/'));
gl_req(['is_archive' => true]);
check('D archive title and description', 'AI用語集｜生成AI・LLMの用語をわかりやすく解説' === omochix_glossary_meta_title('x') && '' !== omochix_glossary_meta_description(''));
gl_req([]); // Another page (the stub's is_singular() cannot tell post types apart, so no glossary context at all).
check('D other pages: not a glossary request; title, description, canonical, robots untouched', !omochix_glossary_is_request() && 'x' === omochix_glossary_meta_title('x') && 'd' === omochix_glossary_meta_description('d') && 'https://example.test/x/' === omochix_glossary_canonical('https://example.test/x/') && ['index' => true] === omochix_glossary_wp_robots(['index' => true]) && true === omochix_glossary_slim_seo_index(true));

// ================================================ E: structured data
$base = [['@type' => 'WebPage', '@id' => 'https://example.test/omx_glossary/term-1/#webpage'], ['@type' => 'Article', '@id' => 'a'], ['@type' => 'SoftwareApplication', '@id' => 's'], ['@type' => 'Organization', '@id' => 'o']];
update_post_meta($ids[0], 'glossary_aliases', ['用語ワン']);
gl_req(['is_singular' => true, 'queried' => get_post($ids[0])]);
$g = omochix_glossary_schema_graph($base);
$types = array_column($g, '@type');
$term = array_values(array_filter($g, static fn($e) => 'DefinedTerm' === ($e['@type'] ?? '')))[0] ?? [];
check('E term: DefinedTerm added, Article and SoftwareApplication removed, Organization kept', in_array('DefinedTerm', $types, true) && !in_array('Article', $types, true) && !in_array('SoftwareApplication', $types, true) && in_array('Organization', $types, true));
check('E term: name, description, url, set, alternate names', '用語1' === $term['name'] && '' !== $term['description'] && 'https://example.test/glossary/#glossary' === $term['inDefinedTermSet']['@id'] && ['Test', '用語ワン'] === $term['alternateName']);
check('E term: WebPage mainEntity points at the term', $term['@id'] === $g[0]['mainEntity']['@id']);
gl_req(['is_singular' => true, 'queried' => get_post($stale)]);
check('E noindex term: no DefinedTerm (article types still removed)', !in_array('DefinedTerm', array_column(omochix_glossary_schema_graph($base), '@type'), true) && !in_array('Article', array_column(omochix_glossary_schema_graph($base), '@type'), true));
gl_req(['is_archive' => true]);
$g = omochix_glossary_schema_graph([['@type' => 'CollectionPage', '@id' => 'c']]);
$set = array_values(array_filter($g, static fn($e) => 'DefinedTermSet' === ($e['@type'] ?? '')))[0] ?? [];
check('E archive: DefinedTermSet with indexable terms only (11: stale left out, hidden never)', 11 === count($set['hasDefinedTerm'] ?? []) && [] === array_filter($set['hasDefinedTerm'], static fn($t) => false !== strpos($t['url'], 'secret-') || false !== strpos($t['url'], 'stale-')) && 'https://example.test/glossary/#glossary' === $g[0]['mainEntity']['@id']);
gl_req(['is_tax' => 'omx_glossary_category', 'queried' => ih_term('omx_glossary_category', 'llm')]);
check('E noindex category: graph untouched', [['@type' => 'CollectionPage', '@id' => 'c']] === omochix_glossary_schema_graph([['@type' => 'CollectionPage', '@id' => 'c']]));
$GLOBALS['ctx'] = []; omochix_glossary_decide(true);
check('E other pages: graph untouched', $base === omochix_glossary_schema_graph($base));

// ================================================ F: XSS in a preview
$GLOBALS['caps'] = ['edit_post' => true];
$x = gl_term('xss', '"><script>alert(1)</script>', 'draft', ['meta' => [
    'glossary_reading' => '<img src=x onerror=alert(2)>', 'glossary_en' => '"><svg onload=alert(3)>', 'glossary_aliases' => ['<b onmouseover=alert(4)>a</b>'],
    'glossary_summary' => '</p><script>alert(5)</script>',
    'glossary_sources' => [['url' => 'javascript:alert(6)', 'title' => 'bad', 'publisher' => 'x', 'kind' => 'docs', 'checked_on' => '2026-11-20'], ['url' => 'https://docs.example.org/"onmouseover="alert(7)', 'title' => 'q', 'publisher' => 'x', 'kind' => 'docs', 'checked_on' => '2026-11-20'], ['url' => 'https://docs.example.org/ok', 'title' => '<script>alert(8)</script>', 'publisher' => '<i>p</i>', 'kind' => 'docs', 'checked_on' => '2026-11-20']],
], 'post' => ['post_content' => '<p onclick="alert(9)">x</p><script>alert(10)</script><iframe src="https://evil.example.org"></iframe><a href="javascript:alert(11)">j</a><a href="http://insecure.example.org/">h</a><a href="https://docs.example.org/" target="_blank" onclick="x">ok</a>']]);
gl_req(['is_singular' => true, 'queried' => get_post($x)]);
$out = gl_render('single-omx_glossary.php');
// Real markup only: a raw "<" starting a tag, or an on* attribute inside a raw tag. Escaped text (&lt;…) is harmless.
check('F preview with hostile values: no executable markup survives', 0 === preg_match('/<(?:script|svg|img|iframe|object)\b|<[a-z][^>]*\son[a-z]+\s*=|href="(?:javascript|http):/i', $out), substr($out, 0, 400));
check('F the hostile reading/English/alias are present only as escaped text', false !== strpos($out, '&lt;img src=x onerror=alert(2)&gt;') && false !== strpos($out, '&quot;&gt;&lt;svg onload=alert(3)&gt;'));
check('F hostile title and summary are shown escaped', false !== strpos($out, '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;') && false !== strpos($out, '&lt;/p&gt;&lt;script&gt;alert(5)'));
check('F sources: refused URLs are not linked, the valid one is, with rel noopener noreferrer external', false === strpos($out, 'javascript:alert(6)') && false === strpos($out, 'alert(7)') && 1 === preg_match('#<a href="https://docs.example.org/ok" rel="noopener noreferrer external">&lt;script&gt;#', $out));
check('F content: allowed tags only, links https with rel, no target/onclick', false !== strpos($out, '<a rel="noopener noreferrer external" href="https://docs.example.org/">ok</a>') && false === strpos($out, 'target='));
$GLOBALS['caps'] = [];

// ================================================ G: related links
$tool = ih_add_post(['post_type' => 'ai_tool', 'post_status' => 'publish', 'post_name' => 'tool-ok', 'post_title' => '公開ツール']);
$tool_end = ih_add_post(['post_type' => 'ai_tool', 'post_status' => 'publish', 'post_name' => 'tool-end', 'post_title' => '終了ツール']);
update_post_meta($tool_end, 'tool_status', 'discontinued');
if (!function_exists('omochix_is_ai_tool_discontinued')) { function omochix_is_ai_tool_discontinued($id) { return 'discontinued' === get_post_meta($id, 'tool_status', true); } }
$tool_draft = ih_add_post(['post_type' => 'ai_tool', 'post_status' => 'draft', 'post_name' => 'tool-draft', 'post_title' => '下書きツール']);
$learn = ih_add_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'prompt', 'post_title' => 'プロンプト入門']); $GLOBALS['page_uri'][$learn] = 'learn/basics/prompt';
$about = ih_add_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'about', 'post_title' => '会社概要']); $GLOBALS['page_uri'][$about] = 'about';
$prac = ih_add_post(['post_type' => 'learn_practice', 'post_status' => 'private', 'post_name' => 'p', 'post_title' => '非公開の実践']);
update_post_meta($ids[1], 'related_glossary_ids', [$ids[2], $draft, $pw, $failing, $ids[1]]);
update_post_meta($ids[1], 'related_tool_ids', [$tool, $tool_end, $tool_draft, $ids[3]]);
update_post_meta($ids[1], 'related_learn_page_ids', [$learn, $about]);
update_post_meta($ids[1], 'related_practice_ids', [$prac]);
update_post_meta($ids[1], 'related_industry_ids', [999]);
$rel = omochix_glossary_related(get_post($ids[1]));
check('G related terms: only visible ones, never itself', ['用語3'] === array_column($rel['glossary'], 'title'));
check('G related tools: published, not discontinued, right type', ['公開ツール'] === array_column($rel['tools'], 'title'));
check('G related Learn pages: only under /learn/', ['プロンプト入門'] === array_column($rel['learn'], 'title'));
check('G private practice and unknown industries are left out', [] === $rel['practice'] && [] === $rel['industries']);
gl_req(['is_singular' => true, 'queried' => get_post($ids[1])]);
$out = gl_render('single-omx_glossary.php');
check('G term page shows the allowed related links only', false !== strpos($out, '公開ツール') && false !== strpos($out, 'プロンプト入門') && false === strpos($out, '終了ツール') && false === strpos($out, '下書きツール') && false === strpos($out, '会社概要') && false === strpos($out, '下書きの用語'));
check('G term page: sources, last check date, AI note, back link', false !== strpos($out, '出典') && false !== strpos($out, '最終確認日：2026年11月21日') && false !== strpos($out, '下書きにAIを利用') && false !== strpos($out, 'AI用語集の一覧へ戻る'));
remove_filter('omochix_core_glossary_public', $open, 20);

// ================================================ J: fixes after the security review of PR #79
add_filter('omochix_core_glossary_public', $open, 20);
// ---------------------------------------------- P1-1: the post type asked for outside its views
// WordPress: ?post_type[]=omx_glossary is an array, so is_post_type_archive() is false and the
// blog / feed / search template would list every published term. (The stub's flags are set by hand.)
foreach ([
    'post_type[]=omx_glossary (blog list)' => [[], ['post_type' => ['omx_glossary']]],
    'mixed with posts' => [[], ['post_type' => ['post', 'omx_glossary']]],
    'as a feed' => [['is_feed' => true], ['post_type' => ['omx_glossary']]],
    'as a search (?s=x&post_type[]=…)' => [['is_search' => true], ['post_type' => ['omx_glossary'], 's' => 'x']],
    'with paging' => [[], ['post_type' => ['omx_glossary'], 'paged' => 3]],
    'written in another case' => [[], ['post_type' => ['OMX_Glossary']]],
    'a keyed array' => [[], ['post_type' => ['a' => 'omx_glossary']]],
] as $name => [$ctx, $vars]) {
    $d = gl_req($ctx, $vars);
    check("P1-1 $name: 404, no view, no term chosen", 'not_found' === $d['action'] && '' === $d['kind'] && null === $d['post'] && false === omochix_glossary_is_request());
}
/**
 * The main query as WordPress leaves it: set_404() sets is_404 and nothing else that matters here.
 * It keeps is_feed and the posts it found (WP_Query::set_404 restores is_feed on purpose), and
 * template-loader.php then prints a feed of those posts under the 404 status.
 */
class GL_Main_Query extends WP_Query {
    public $is404 = false, $is_feed = false, $is_comment_feed = false, $is_trackback = false, $is_embed = false, $post_count = 0, $current_post = -1, $post = null, $queried_object = null, $queried_object_id = 0, $comments = ['c'], $comment_count = 1;
    function __construct($feed = false, array $posts = []) { $this->is_feed = $feed; $this->posts = $posts; $this->post_count = count($posts); $this->found_posts = count($posts); $this->post = $posts[0] ?? null; }
    function is_main_query() { return true; }
    function set_404() { $this->is404 = true; }
    function is_404() { return $this->is404; }
    /** What template-loader.php would print: the feed of the posts, when is_feed is still set. */
    function would_print() { return $this->is_feed ? array_map(static fn($p) => $p->post_title, $this->posts) : []; }
}
$found = [get_post($ids[0]), get_post($failing), get_post($pw)]; // What the query finds: every published term, hidden ones too.
gl_req(['is_feed' => true], ['post_type' => ['omx_glossary']]);
$q = new GL_Main_Query(true, $found);
check('P1-1 reproduction: a 404 status alone still leaves a feed of every published term', ['用語1', '出典なしの用語', 'パスワード付きの用語'] === (static function () use ($found) { $x = new GL_Main_Query(true, $found); $x->set_404(); return $x->would_print(); })());
$GLOBALS['post'] = get_post($failing);
check('P1-1 pre_handle_404: 404, and the query is emptied (no feed, no post left to print)', true === omochix_glossary_pre_handle_404(false, $q) && null === $GLOBALS['post'] && [] === $q->comments && 0 === $q->comment_count && true === $q->is404 && 404 === ($GLOBALS['status_header'] ?? 0) && false === $q->is_feed && [] === $q->posts && 0 === $q->post_count && null === $q->post && [] === $q->would_print());
foreach ([
    'feed of the archive (/feed/?post_type=omx_glossary)' => [['is_archive' => true, 'is_feed' => true], ['post_type' => 'omx_glossary']],
    'feed of a hidden term (?feed=rss2&post_type=omx_glossary&p=ID)' => [['is_singular' => true, 'is_feed' => true, 'queried' => get_post($failing)], ['post_type' => 'omx_glossary', 'p' => $failing]],
    'feed of a category' => [['is_tax' => 'omx_glossary_category', 'is_feed' => true, 'queried' => ih_term('omx_glossary_category', 'basics')], []],
    'feed with the array form' => [['is_feed' => true], ['post_type' => ['post', 'omx_glossary']]],
] as $name => [$ctx, $vars]) {
    gl_req($ctx, $vars);
    $q = new GL_Main_Query(true, $found);
    $a = omochix_glossary_pre_handle_404(false, $q);
    $GLOBALS['wp_query'] = new GL_Main_Query(true, $found); // And when pre_handle_404 did not run (another plugin answered first).
    omochix_glossary_template_redirect();
    check("P1-1 $name: 404 and nothing is printed, by either hook", true === $a && [] === $q->would_print() && [] === $q->posts && true === $GLOBALS['wp_query']->is404 && [] === $GLOBALS['wp_query']->would_print() && [] === $GLOBALS['wp_query']->posts && 404 === ($GLOBALS['status_header'] ?? 0));
}
gl_req([], ['post_type' => ['omx_glossary']]);
$GLOBALS['wp_query'] = new GL_Main_Query(false, $found);
omochix_glossary_template_redirect();
check('P1-1 template_redirect answers 404, empties the query, and the 404 template is used', 404 === ($GLOBALS['status_header'] ?? 0) && true === $GLOBALS['wp_query']->is404 && [] === $GLOBALS['wp_query']->posts && 'x.php' === omochix_glossary_template_include('x.php'));
gl_req(['is_singular' => true, 'queried' => get_post($ids[0])]);
$q = new GL_Main_Query(false, [get_post($ids[0])]);
$GLOBALS['wp_query'] = $q;
omochix_glossary_template_redirect();
check('P1-1 a visible term keeps its query (nothing is emptied on a page that is shown)', false === omochix_glossary_pre_handle_404(false, $q) && false === $q->is404 && 1 === count($q->posts));
unset($GLOBALS['wp_query']);
foreach ([
    'no post type' => [[], []],
    'posts' => [[], ['post_type' => 'post']],
    'AI tools' => [[], ['post_type' => 'ai_tool']],
    'posts and AI tools (array)' => [[], ['post_type' => ['post', 'ai_tool']]],
    'a search over everything' => [['is_search' => true], ['s' => 'x', 'post_type' => 'any']],
    'a name that only contains the word' => [[], ['post_type' => ['omx_glossary_old', 'my_omx_glossary']]],
    'an Industry Hub term' => [['is_tax' => 'omx_industry'], []],
    'not strings' => [[], ['post_type' => [['omx_glossary'], 5, null]]],
] as $name => [$ctx, $vars]) {
    $d = gl_req($ctx, $vars);
    $q = new GL_Main_Query();
    check("P1-1 untouched: $name", 'none' === $d['action'] && false === omochix_glossary_pre_handle_404(false, $q) && false === $q->is404 && 'x.php' === omochix_glossary_template_include('x.php') && ['index' => true] === omochix_glossary_wp_robots(['index' => true]));
}
check('P1-1 the three real views still answer (archive, category, single)', 'ok' === gl_req(['is_archive' => true], ['post_type' => 'omx_glossary'])['action'] && 'ok' === gl_req(['is_tax' => 'omx_glossary_category', 'queried' => ih_term('omx_glossary_category', 'basics')])['action'] && 'ok' === gl_req(['is_singular' => true, 'queried' => get_post($ids[0])], ['post_type' => ['omx_glossary'], 'p' => $ids[0]])['action']);
check('P1-1 ?post_type[]=omx_glossary&p=ID of a hidden term is the single view: 404 for a visitor', 'not_found' === gl_req(['is_singular' => true, 'queried' => get_post($failing)], ['post_type' => ['omx_glossary'], 'p' => $failing])['action']);
check('P1-1 a trackback URL of a term is 404 (visible or hidden: the same answer)', 'not_found' === gl_req(['is_singular' => true, 'is_trackback' => true, 'queried' => get_post($ids[0])])['action'] && 'not_found' === gl_req(['is_singular' => true, 'is_trackback' => true, 'queried' => get_post($failing)])['action']);
check('P1-1 ?s= with the post type as a string is still 400, a feed of the archive still 404', 'bad_request' === gl_req(['is_archive' => true, 'is_search' => true], ['post_type' => 'omx_glossary'])['action'] && 'not_found' === gl_req(['is_archive' => true, 'is_feed' => true], ['post_type' => 'omx_glossary'])['action']);
remove_filter('omochix_core_glossary_public', $open, 20);
check('P1-1 closed: nothing is decided (WordPress itself drops a post type that is not publicly queryable)', 'none' === gl_req([], ['post_type' => ['omx_glossary']])['action']);
add_filter('omochix_core_glossary_public', $open, 20);
// Redirects that would tell a hidden term from a missing one.
// WordPress (canonical.php): on a 404 with ?p=ID it looks the post up and redirects to its address.
// A missing ID still gets its address tidied (/index.php?p=N -> /?p=N), so the redirect is not
// stopped: the ID is forgotten before WordPress looks, and both cases take the same path.
$forget = static function (array $vars, array $caps = []) { $GLOBALS['caps'] = $caps; gl_req(['is_404' => true], $vars); omochix_glossary_template_redirect(); $GLOBALS['caps'] = []; return $GLOBALS['query_vars']; };
check('P1-1 /?p=ID of a draft, a failing and a password term: the ID is forgotten (as if it did not exist)', 0 === $forget(['p' => $draft])['p'] && 0 === $forget(['p' => $failing])['p'] && 0 === $forget(['p' => $pw])['p']);
check('P1-1 the same through page_id and attachment_id', 0 === $forget(['page_id' => $failing])['page_id'] && 0 === $forget(['attachment_id' => $draft])['attachment_id']);
check('P1-1 /?p=ID of a visible term, of another post type, of a missing post: untouched', $ids[0] === $forget(['p' => $ids[0]])['p'] && $tool_draft === $forget(['p' => $tool_draft])['p'] && 999999 === $forget(['p' => 999999])['p'] && [] === $forget([]));
check('P1-1 an editor keeps the ID (the redirect to a draft still works for them)', $draft === $forget(['p' => $draft], ['edit_post' => true])['p']);
check('P1-1 no filter stops or rewrites canonical redirects (stopping one would itself be the difference)', false === has_filter('redirect_canonical') && !function_exists('omochix_glossary_redirect_canonical'));
check('P1-1 an old slug does not redirect to a hidden term', 0 === omochix_glossary_old_slug_redirect($failing) && 0 === omochix_glossary_old_slug_redirect($draft) && 0 === omochix_glossary_old_slug_redirect($pw) && false !== has_filter('old_slug_redirect_post_id', 'omochix_glossary_old_slug_redirect'));
check('P1-1 an old slug still redirects to a visible term, to other post types, and "none" stays none', $ids[0] === omochix_glossary_old_slug_redirect($ids[0]) && $tool === omochix_glossary_old_slug_redirect($tool) && $tool_draft === omochix_glossary_old_slug_redirect($tool_draft) && 0 === omochix_glossary_old_slug_redirect(0));
$GLOBALS['caps'] = ['edit_post' => true];
check('P1-1 an editor keeps the old-slug redirect', $draft === omochix_glossary_old_slug_redirect($draft));
$GLOBALS['caps'] = [];
remove_filter('omochix_core_glossary_public', $open, 20);
check('P1-1 closed: every term is hidden, so its ID is forgotten too', 0 === $forget(['p' => $ids[0]])['p']);
add_filter('omochix_core_glossary_public', $open, 20);

// ---------------------------------------------- P1-3: block editor content on the page
$blocks = "<!-- wp:paragraph -->\n<p>" . str_repeat('これはテスト用の説明文で、内容に意味はありません。', 17) . "</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">ブロックの見出し</h3>\n<!-- /wp:heading -->\n\n<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li><a href=\"https://docs.example.org/spec\">仕様</a></li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n<!-- wp:table -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\"><tbody><tr><td>表の値</td></tr></tbody></table></figure>\n<!-- /wp:table -->";
$blk = gl_term('block-term', 'ブロックの用語', 'publish', ['post' => ['post_content' => $blocks], 'meta' => ['glossary_reading' => 'ぶろっく']]);
$d = gl_req(['is_singular' => true, 'queried' => get_post($blk)]);
check('P1-3 a term written in the block editor is visible and indexable', 'ok' === $d['action'] && true === $d['index']);
$out = gl_render('single-omx_glossary.php');
check('P1-3 its page shows the content with no block comment, no editor class, no figure', false !== strpos($out, '<h3>ブロックの見出し</h3>') && false !== strpos($out, '<td>表の値</td>') && false !== strpos($out, '<a rel="noopener noreferrer external" href="https://docs.example.org/spec">仕様</a>') && false === strpos($out, 'wp:') && false === strpos($out, 'wp-block') && false === strpos($out, '<figure'));
$html = omochix_glossary_content_html('<!-- wp:html --><script>alert(1)</script><!-- /wp:html --><!-- wp:paragraph {"style":{"color":{"text":"red"}}} --><p class="has-text-color" style="color:red" onclick="x()">a</p><!-- /wp:paragraph --><!-- wp:heading --><h2 class="wp-block-heading">b</h2><!-- /wp:heading -->');
check('P1-3 the page never trusts the normalization: script, style, class and handlers are gone whatever the blocks say', 0 === preg_match('/<script|style=|class=|onclick|alert\(1\)/i', $html) && false !== strpos($html, '<p>a</p>') && false !== strpos($html, '<h2>b</h2>'));
wp_update_post(['ID' => $blk, 'post_status' => 'draft']);

// ---------------------------------------------- P1-5: sitemaps list exactly what is indexed
check('P1-5 one set of thresholds: the theme follows Core (10 and 3)', 10 === OMOCHIX_CORE_GLOSSARY_INDEX_MIN && 3 === OMOCHIX_CORE_GLOSSARY_CATEGORY_INDEX_MIN && OMOCHIX_GLOSSARY_INDEX_MIN === OMOCHIX_CORE_GLOSSARY_INDEX_MIN && OMOCHIX_GLOSSARY_CATEGORY_INDEX_MIN === OMOCHIX_CORE_GLOSSARY_CATEGORY_INDEX_MIN);
$basics = ih_term('omx_glossary_category', 'basics'); $llm = ih_term('omx_glossary_category', 'llm'); $multi = ih_term('omx_glossary_category', 'multimodal');
gl_req(['is_archive' => true]);
$st = omochix_core_glossary_index_state();
$want = array_merge($ids, [$llm1]); sort($want); $got = $st['ids']; sort($got);
check('P1-5 indexable terms: the 10 + 1 complete ones; never the draft, password, failing or stale term', $want === $got && true === $st['list'] && [$basics->term_id] === $st['category_ids']);
$a = omochix_core_glossary_sitemap_query_args(['post_type' => 'omx_glossary'], 'omx_glossary')['post__in']; sort($a);
$b = omochix_core_glossary_slim_sitemap_post_args(['post_type' => 'omx_glossary', 'paged' => 1], ['post_type' => 'omx_glossary', 'paged' => 1])['post__in']; sort($b);
check('P1-5 term URLs: WordPress core and Slim SEO sitemaps ask for exactly those terms', $want === $a && $want === $b);
check('P1-5 other post types: both sitemap queries untouched', ['post_type' => 'ai_tool'] === omochix_core_glossary_sitemap_query_args(['post_type' => 'ai_tool'], 'ai_tool') && ['post_type' => 'page'] === omochix_core_glossary_slim_sitemap_post_args(['post_type' => 'page'], ['post_type' => 'page']) && [] === omochix_core_glossary_slim_sitemap_post_args([], []));
check('P1-5 category URLs: only the category with 3+ indexable terms (core and Slim SEO)', [$basics->term_id] === omochix_core_glossary_sitemap_term_args(['taxonomy' => 'omx_glossary_category', 'hide_empty' => true], 'omx_glossary_category')['include'] && [$basics->term_id] === omochix_core_glossary_sitemap_term_args(['hide_empty' => true, 'taxonomy' => 'omx_glossary_category', 'offset' => 0], ['taxonomy' => 'omx_glossary_category', 'offset' => 0])['include']);
check('P1-5 other taxonomies, and Slim SEO\'s count for its index (no taxonomy named): untouched', ['taxonomy' => 'omx_industry'] === omochix_core_glossary_sitemap_term_args(['taxonomy' => 'omx_industry'], 'omx_industry') && ['hide_empty' => true] === omochix_core_glossary_sitemap_term_args(['hide_empty' => true], []) && ['taxonomy' => 'category'] === omochix_core_glossary_sitemap_term_args(['taxonomy' => 'category'], ['taxonomy' => 'category']));
/** Whether robots, canonical and the sitemap agree for every glossary list page. */
$agree = static function () use ($basics, $llm, $multi) {
    $bad = [];
    omochix_glossary_decide(true); // A new request: the per-request lists are counted again.
    $include = omochix_core_glossary_sitemap_term_args(['taxonomy' => 'omx_glossary_category'], 'omx_glossary_category')['include'];
    foreach ([$basics, $llm, $multi] as $t) {
        $d = gl_req(['is_tax' => 'omx_glossary_category', 'queried' => $t]);
        $indexed = 'ok' === $d['action'] && !omochix_glossary_is_noindex();
        $in_map = in_array($t->term_id, $include, true);
        $canon = '' !== omochix_glossary_canonical_url();
        $ld = in_array('DefinedTermSet', array_column(omochix_glossary_schema_graph([['@type' => 'CollectionPage', '@id' => 'c']]), '@type'), true);
        if ($indexed !== $in_map || $indexed !== $canon || $indexed !== $ld) { $bad[] = $t->slug . ':' . json_encode([$indexed, $in_map, $canon, $ld]); }
    }
    $d = gl_req(['is_archive' => true]);
    $indexed = 'ok' === $d['action'] && !omochix_glossary_is_noindex();
    $GLOBALS['hooks']['post_type_archive_link'] = [];
    do_action('slim_seo_sitemap_before_output');
    $in_map = false !== apply_filters('post_type_archive_link', 'https://example.test/glossary/', 'omx_glossary');
    $canon = '' !== omochix_glossary_canonical_url();
    $ld = in_array('DefinedTermSet', array_column(omochix_glossary_schema_graph([['@type' => 'CollectionPage', '@id' => 'c']]), '@type'), true);
    if ($indexed !== $in_map || $indexed !== $canon || $indexed !== $ld || $indexed !== omochix_core_glossary_list_indexable()) { $bad[] = 'list:' . json_encode([$indexed, $in_map, $canon, $ld]); }
    $other = apply_filters('post_type_archive_link', 'https://example.test/ai-tools/', 'ai_tool');
    $GLOBALS['hooks']['post_type_archive_link'] = [];
    return [$bad, $indexed, $other];
};
[$bad, $list_indexed, $other] = $agree();
check('P1-5 11 indexable terms: the list is indexed, has a canonical, structured data, and its URL stays in the Slim SEO sitemap', [] === $bad && true === $list_indexed && 'https://example.test/ai-tools/' === $other, implode(' ', $bad));
$d = gl_req(['is_tax' => 'omx_glossary_category', 'queried' => $llm]);
check('P1-5 the category with 1 term: shown, noindex, and not in the sitemap', 'ok' === $d['action'] && omochix_glossary_is_noindex() && !in_array($llm->term_id, omochix_core_glossary_index_state()['category_ids'], true));
check('P1-5 the category with no visible term: 404 and not in the sitemap', 'not_found' === gl_req(['is_tax' => 'omx_glossary_category', 'queried' => $multi])['action'] && !in_array($multi->term_id, omochix_core_glossary_index_state()['category_ids'], true));
// Category boundary: 2 -> not listed, 3 -> listed.
$m1 = gl_term('multi-1', 'マルチ1', 'publish', ['cats' => ['multimodal']]); $m2 = gl_term('multi-2', 'マルチ2', 'publish', ['cats' => ['multimodal']]);
[$bad] = $agree();
check('P1-5 a category with 2 indexable terms: noindex and not in the sitemap (all four agree)', [] === $bad && !in_array($multi->term_id, omochix_core_glossary_index_state()['category_ids'], true), implode(' ', $bad));
$m3 = gl_term('multi-3', 'マルチ3', 'publish', ['cats' => ['multimodal']]);
[$bad] = $agree();
check('P1-5 with a 3rd indexable term: indexed and in the sitemap (all four agree)', [] === $bad && in_array($multi->term_id, omochix_core_glossary_index_state()['category_ids'], true), implode(' ', $bad));
$m4 = gl_term('multi-stale', 'マルチ期限切れ', 'publish', ['cats' => ['llm'], 'meta' => ['glossary_verified_on' => '2026-03-01', 'glossary_sources' => [['url' => 'https://docs.example.org/spec', 'title' => '仕様書', 'publisher' => 'Example Docs', 'kind' => 'docs', 'checked_on' => '2026-03-01']]]]);
$m5 = gl_term('multi-stale-2', 'マルチ期限切れ2', 'publish', ['cats' => ['llm'], 'meta' => ['glossary_verified_on' => '2026-03-01', 'glossary_sources' => [['url' => 'https://docs.example.org/spec', 'title' => '仕様書', 'publisher' => 'Example Docs', 'kind' => 'docs', 'checked_on' => '2026-03-01']]]]);
[$bad] = $agree();
check('P1-5 3 visible terms of which only 1 is indexable (2 past review): noindex and not in the sitemap', [] === $bad && 3 === count(omochix_glossary_visible_terms('llm')) && !in_array($llm->term_id, omochix_core_glossary_index_state()['category_ids'], true), implode(' ', $bad));
// List boundary: 10 -> indexed, 9 -> noindex and the archive URL leaves the sitemap; the 9 terms stay.
foreach ([$m1, $m2, $m3, $llm1, $ids[9]] as $pid) { wp_update_post(['ID' => $pid, 'post_status' => 'draft']); }
[$bad, $list_indexed] = $agree();
check('P1-5 9 indexable terms: the list is noindex, no canonical, no structured data, and Slim SEO gets no archive URL', [] === $bad && false === $list_indexed && 9 === count(omochix_core_glossary_index_state()['ids']), implode(' ', $bad));
check('P1-5 9 indexable terms: their own URLs stay in both sitemaps', 9 === count(omochix_core_glossary_sitemap_query_args([], 'omx_glossary')['post__in']) && false === omochix_core_glossary_sitemap_post_ignore(false, $ids[0]));
wp_update_post(['ID' => $ids[9], 'post_status' => 'publish']);
[$bad, $list_indexed] = $agree();
check('P1-5 back to 10: indexed again (all four agree)', [] === $bad && true === $list_indexed, implode(' ', $bad));
check('P1-5 single terms: Slim SEO ignores a stale, a failing and a draft term; keeps an indexable one', true === omochix_core_glossary_sitemap_post_ignore(false, $stale) && true === omochix_core_glossary_sitemap_post_ignore(false, $failing) && true === omochix_core_glossary_sitemap_post_ignore(false, $draft) && false === omochix_core_glossary_sitemap_post_ignore(false, $ids[1]));
foreach ($ids as $pid) { wp_update_post(['ID' => $pid, 'post_status' => 'draft']); }
omochix_glossary_decide(true);
check('P1-5 no indexable term at all: both sitemaps ask for nothing (no empty list of everything)', [0] === omochix_core_glossary_sitemap_query_args([], 'omx_glossary')['post__in'] && [0] === omochix_core_glossary_slim_sitemap_post_args(['post_type' => 'omx_glossary'], [])['post__in'] && [0] === omochix_core_glossary_sitemap_term_args([], 'omx_glossary_category')['include'] && false === omochix_core_glossary_list_indexable());
foreach ($ids as $pid) { wp_update_post(['ID' => $pid, 'post_status' => 'publish']); }
remove_filter('omochix_core_glossary_public', $open, 20);
omochix_glossary_decide(true);
check('P1-5 closed: nothing is indexable, the sitemaps ask for nothing, post type and taxonomy are left out', [] === omochix_core_glossary_index_state()['ids'] && [0] === omochix_core_glossary_sitemap_query_args([], 'omx_glossary')['post__in'] && [0] === omochix_core_glossary_sitemap_term_args([], 'omx_glossary_category')['include'] && ['post'] === omochix_core_glossary_sitemap_post_types(['post', 'omx_glossary']) && ['category'] === omochix_core_glossary_sitemap_taxonomies(['category', 'omx_glossary_category']));
$GLOBALS['hooks']['post_type_archive_link'] = [];
do_action('slim_seo_sitemap_before_output');
check('P1-5 closed: Slim SEO gets no archive URL either', false === apply_filters('post_type_archive_link', 'https://example.test/glossary/', 'omx_glossary'));
$GLOBALS['hooks']['post_type_archive_link'] = [];

// ================================================ H: theme without Core
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --no-core 2>&1', $o, $code);
$r = json_decode((string) end($o), true);
check('H theme module without the Core glossary: no fatal error, disabled, nothing decided, no output', 0 === $code && is_array($r) && false === $r['enabled'] && 'none' === $r['decide'] && '' === $r['out'] && 0 === $r['errors'], implode("\n", $o));

// ================================================ I: static checks
$files = array_merge([$THEME . '/inc/web2/glossary.php', $THEME . '/archive-omx_glossary.php', $THEME . '/taxonomy-omx_glossary_category.php', $THEME . '/single-omx_glossary.php'], glob($THEME . '/template-parts/glossary/*.php'));
$bad = [];
foreach ($files as $f) {
    foreach (token_get_all((string) file_get_contents($f)) as $i => $t) {
        if (is_array($t) && T_VARIABLE === $t[0] && in_array($t[1], ['$_POST', '$_REQUEST', '$_COOKIE', '$_FILES', '$wpdb'], true)) { $bad[] = basename($f) . ' ' . $t[1]; }
        if (is_array($t) && T_STRING === $t[0] && in_array(strtolower($t[1]), ['update_option', 'add_option', 'update_post_meta', 'wp_insert_post', 'wp_update_post', 'register_rest_route', 'add_rewrite_rule', 'flush_rewrite_rules', 'exec', 'eval', 'unserialize'], true)) { $bad[] = basename($f) . ' ' . $t[1]; }
    }
    foreach (explode("\n", (string) file_get_contents($f)) as $n => $line) {
        if (preg_match('/<\?php echo (?!esc_html|esc_attr|esc_url|\$omochix_current|\$omochix_v\[\'content\'\]|\'glossary\' ===|\(\$omochix_current|\'\s)/', $line)) { $bad[] = basename($f) . ':' . ($n + 1) . ' unescaped echo'; }
    }
}
check('I templates and module: no write, no request data but the path check, every echo escaped (content: kses)', [] === $bad, implode('; ', $bad));
preg_match_all('/\\$_SERVER\\[\'([A-Z_]+)\'\\]/', (string) file_get_contents($THEME . '/inc/web2/glossary.php'), $sv);
check('I the module reads only REQUEST_URI from $_SERVER, to compare the path', ['REQUEST_URI'] === array_values(array_unique($sv[1])));
check('I theme files are not loaded by functions.php directly (only the Web 2.0 loader may)', false === strpos((string) file_get_contents($THEME . '/functions.php'), 'glossary'));

ih_done();
