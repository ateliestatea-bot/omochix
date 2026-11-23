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
