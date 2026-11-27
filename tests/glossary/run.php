<?php
/**
 * AI glossary (Knowledge Hub) Phase 1, Core side: unit tests without WordPress.
 *
 * Loads the whole Core plugin through tests/industry-hub/stub.php (read-only
 * use of that stub), then the glossary module entry file the way the Web 2.0
 * loader would. Half-done uploads run in their own PHP process.
 *
 * Run: php tests/glossary/run.php
 */
$REPO = realpath(__DIR__ . '/../..');

/** Hook => sorted "priority:callback" list; tolerates hooks whose callbacks were all removed. */
function gl_hook_names() {
    $out = [];
    foreach ($GLOBALS['hooks'] ?? [] as $hook => $by_priority) {
        foreach ((array) $by_priority as $priority => $list) {
            foreach ((array) $list as $h) { $out[$hook][] = $priority . ':' . (is_string($h[0]) ? $h[0] : (is_array($h[0]) ? 'array' : '{closure}')); }
        }
        if (isset($out[$hook])) { sort($out[$hook]); }
    }
    ksort($out);
    return $out;
}

// ------------------------------------------------- child: partial upload
if ('--child' === ($argv[1] ?? '')) {
    putenv('IH_PLUGIN_DIR=' . $argv[2]);
    ob_start();
    require $REPO . '/tests/industry-hub/stub.php';
    ob_end_clean();
    $before = gl_hook_names();
    ob_start();
    try { require $argv[2] . '/includes/web2/glossary-loader.php'; $ex = ''; } catch (Throwable $e) { $ex = get_class($e); }
    $out = (string) ob_get_clean();
    $added = [];
    foreach (gl_hook_names() as $hook => $list) { foreach (array_diff($list, $before[$hook] ?? []) as $cb) { $added[] = "$hook $cb"; } }
    echo "\n@@JSON@@" . json_encode(['loaded' => function_exists('omochix_core_glossary_loaded') && omochix_core_glossary_loaded(), 'problems' => function_exists('omochix_core_glossary_load') ? omochix_core_glossary_load()['problems'] : null, 'added' => $added, 'out' => $out, 'exception' => $ex, 'errors' => $GLOBALS['fail']]);
    exit(0);
}

require $REPO . '/tests/industry-hub/stub.php';
$PLUGIN = $REPO . '/wp-plugin/omochix-core';
$core_hooks = gl_hook_names();
require $PLUGIN . '/includes/web2/glossary-loader.php';
check('module: loaded from this tree', omochix_core_glossary_loaded() && [] === omochix_core_glossary_load()['problems']);
// The rules take "now" from a clock the tests fix: the fixtures below carry dates around 2026-11-21,
// and with the real clock they would start to fail once those dates are past their review time.
omochix_core_glossary_clock(strtotime('2026-11-24 03:00:00 UTC')); // 12:00 in Japan
$TODAY = '2026-11-23';

/** A term that passes every rule (synthetic test data, not glossary content). */
function gl_valid() {
    return [
        'schema' => 'omochix-glossary/1', 'slug' => 'test-term', 'title' => 'テスト用語', 'reading' => 'てすとようご', 'en' => 'Test Term',
        'aliases' => ['テスト語'], 'category' => 'basics', 'level' => 'beginner',
        'summary' => 'これは検証ルールの単体テストだけに使う架空の用語で、公開する内容ではありません。説明の長さを満たすための文です。',
        'content_html' => '<p>' . str_repeat('この段落はテスト用の文章で、意味のある説明ではありません。', 16) . '</p><h2>見出し</h2><ul><li>項目</li></ul><p><a href="https://docs.example.org/spec">仕様</a></p>',
        'sources' => [['url' => 'https://docs.example.org/spec', 'title' => '仕様書', 'publisher' => 'Example Docs', 'kind' => 'docs', 'checked_on' => '2026-11-20']],
        'verified_on' => '2026-11-21', 'review_days' => 180, 'ai_assisted' => true,
        'related' => ['glossary' => ['other-term'], 'tools' => ['chatgpt'], 'learn_pages' => ['learn/basics'], 'practice' => [], 'industries' => []],
    ];
}
function gl_check($t) { return omochix_core_glossary_validate_term($t, ['today' => '2026-11-23', 'categories' => ['basics', 'llm']]); }
function gl_has($res, $needle) { foreach ($res['errors'] as $e) { if (false !== strpos($e, $needle)) { return true; } } return false; }

// ===================================================== T-VAL: one term
$ok = gl_check(gl_valid());
check('T-VAL valid term: no error, no warning', [] === $ok['errors'] && [] === $ok['warnings']);
$cases = [
    'schema missing' => [static function (&$t) { unset($t['schema']); }, 'schema'],
    'unknown key' => [static function (&$t) { $t['status'] = 'publish'; }, 'status：未知'],
    'slug uppercase' => [static function (&$t) { $t['slug'] = 'Test-Term'; }, 'slug'],
    'slug with underscore' => [static function (&$t) { $t['slug'] = 'test_term'; }, 'slug'],
    'slug trailing hyphen' => [static function (&$t) { $t['slug'] = 'test-'; }, 'slug'],
    'slug reserved (feed)' => [static function (&$t) { $t['slug'] = 'feed'; }, '予約語'],
    'slug reserved (category)' => [static function (&$t) { $t['slug'] = 'category'; }, '予約語'],
    'slug Japanese' => [static function (&$t) { $t['slug'] = 'ようご'; }, 'slug'],
    'slug too long' => [static function (&$t) { $t['slug'] = str_repeat('a', 61); }, 'slug'],
    'title missing' => [static function (&$t) { unset($t['title']); }, 'title：必須'],
    'title too long' => [static function (&$t) { $t['title'] = str_repeat('あ', 41); }, 'title'],
    'title with tag' => [static function (&$t) { $t['title'] = '<b>x</b>'; }, 'title'],
    'title with newline' => [static function (&$t) { $t['title'] = "a\nb"; }, 'title'],
    'title not a string' => [static function (&$t) { $t['title'] = ['x']; }, 'title：文字列'],
    'reading katakana' => [static function (&$t) { $t['reading'] = 'テスト'; }, 'reading'],
    'reading latin' => [static function (&$t) { $t['reading'] = 'test'; }, 'reading'],
    'summary too short' => [static function (&$t) { $t['summary'] = '短い。'; }, 'summary'],
    'summary too long' => [static function (&$t) { $t['summary'] = str_repeat('あ', 121); }, 'summary'],
    'summary with entity' => [static function (&$t) { $t['summary'] = str_repeat('あ', 40) . '&amp;'; }, '文字参照'],
    'en non-ASCII' => [static function (&$t) { $t['en'] = 'テスト'; }, 'en'],
    'alias equals title' => [static function (&$t) { $t['aliases'] = ['テスト用語']; }, 'aliases'],
    'alias duplicated' => [static function (&$t) { $t['aliases'] = ['RAG', 'rag']; }, 'aliases'],
    'too many aliases' => [static function (&$t) { $t['aliases'] = array_map(static fn($i) => "a$i", range(1, 11)); }, 'aliases'],
    'aliases not a list' => [static function (&$t) { $t['aliases'] = ['a' => 'b']; }, 'aliases'],
    'category missing' => [static function (&$t) { unset($t['category']); }, 'category'],
    'category unknown' => [static function (&$t) { $t['category'] = 'agents'; }, 'categories.json'],
    'level unknown' => [static function (&$t) { $t['level'] = 'expert'; }, 'level'],
    'content missing' => [static function (&$t) { unset($t['content_html']); }, 'content_html：必須'],
    'content <script>' => [static function (&$t) { $t['content_html'] .= '<script>alert(1)</script>'; }, '<script>'],
    'content <SCRIPT > uppercase' => [static function (&$t) { $t['content_html'] .= '< SCRIPT>x</script>'; }, '<script>'],
    'content <iframe>' => [static function (&$t) { $t['content_html'] .= '<iframe src="https://a.example.org"></iframe>'; }, '<iframe>'],
    'content <img onerror>' => [static function (&$t) { $t['content_html'] .= '<img src=x onerror=alert(1)>'; }, '<img>'],
    'content onclick' => [static function (&$t) { $t['content_html'] .= '<p onclick="x()">a</p>'; }, 'on〜'],
    'content style attribute' => [static function (&$t) { $t['content_html'] .= '<p style="color:red">a</p>'; }, 'style'],
    'content javascript: link' => [static function (&$t) { $t['content_html'] .= '<a href="javascript:alert(1)">a</a>'; }, 'javascript'],
    'content entity-encoded javascript: link' => [static function (&$t) { $t['content_html'] .= '<a href="&#106;avascript:alert(1)">a</a>'; }, 'https'],
    'content http link' => [static function (&$t) { $t['content_html'] .= '<a href="http://docs.example.org/">a</a>'; }, 'https'],
    'content link with target' => [static function (&$t) { $t['content_html'] .= '<a href="https://docs.example.org/" target="_blank">a</a>'; }, 'href="…" だけ'],
    'content <div>' => [static function (&$t) { $t['content_html'] .= '<div>a</div>'; }, '<div>'],
    'content comment' => [static function (&$t) { $t['content_html'] .= '<!-- x -->'; }, 'コメント'],
    'content <svg>' => [static function (&$t) { $t['content_html'] .= '<svg><script>1</script></svg>'; }, '<svg>'],
    'content too long' => [static function (&$t) { $t['content_html'] = '<p>' . str_repeat('あ', 6001) . '</p>'; }, '長すぎ'],
    'no sources' => [static function (&$t) { $t['sources'] = []; }, 'sources：1〜8'],
    'nine sources' => [static function (&$t) { $t['sources'] = array_map(static fn($i) => ['url' => "https://docs.example.org/$i", 'title' => 't', 'publisher' => 'p', 'kind' => 'docs', 'checked_on' => '2026-11-20'], range(1, 9)); }, 'sources：1〜8'],
    'source http' => [static function (&$t) { $t['sources'][0]['url'] = 'http://docs.example.org/spec'; }, 'https'],
    'source IPv4' => [static function (&$t) { $t['sources'][0]['url'] = 'https://192.168.0.1/x'; }, 'IP'],
    'source IPv6' => [static function (&$t) { $t['sources'][0]['url'] = 'https://[::1]/x'; }, 'IP'],
    'source decimal IP' => [static function (&$t) { $t['sources'][0]['url'] = 'https://2130706433/x'; }, 'IP'],
    'source localhost' => [static function (&$t) { $t['sources'][0]['url'] = 'https://localhost/x'; }, '公開されていない'],
    'source .local' => [static function (&$t) { $t['sources'][0]['url'] = 'https://printer.local/x'; }, '公開されていない'],
    'source single label' => [static function (&$t) { $t['sources'][0]['url'] = 'https://intranet/x'; }, '公開されていない'],
    'source user:pass' => [static function (&$t) { $t['sources'][0]['url'] = 'https://u:p@docs.example.org/x'; }, 'パスワード'],
    'source port 8080' => [static function (&$t) { $t['sources'][0]['url'] = 'https://docs.example.org:8080/x'; }, 'ポート'],
    'source token query' => [static function (&$t) { $t['sources'][0]['url'] = 'https://docs.example.org/x?token=abc'; }, 'token'],
    'source API key query (case)' => [static function (&$t) { $t['sources'][0]['url'] = 'https://docs.example.org/x?API_KEY=abc'; }, 'API_KEY'],
    'source with space' => [static function (&$t) { $t['sources'][0]['url'] = 'https://docs.example.org/a b'; }, '空白'],
    'source with quote' => [static function (&$t) { $t['sources'][0]['url'] = 'https://docs.example.org/"onmouseover="x'; }, '引用符'],
    'source duplicated URL' => [static function (&$t) { $t['sources'][] = $t['sources'][0]; }, '同じ URL'],
    'only a media source' => [static function (&$t) { $t['sources'][0]['kind'] = 'media'; }, '一次情報'],
    'source kind unknown' => [static function (&$t) { $t['sources'][0]['kind'] = 'blog'; }, 'kind'],
    'source title missing' => [static function (&$t) { unset($t['sources'][0]['title']); }, 'title'],
    'source unknown key' => [static function (&$t) { $t['sources'][0]['note'] = 'x'; }, 'note：未知'],
    'checked_on in the future' => [static function (&$t) { $t['sources'][0]['checked_on'] = '2026-11-24'; }, '未来'],
    'checked_on not a date' => [static function (&$t) { $t['sources'][0]['checked_on'] = '2026-02-30'; }, '実在する日付'],
    'verified_on missing' => [static function (&$t) { unset($t['verified_on']); }, 'verified_on'],
    'verified_on in the future' => [static function (&$t) { $t['verified_on'] = '2027-01-01'; }, '未来'],
    'verified_on before a source check' => [static function (&$t) { $t['verified_on'] = '2026-11-19'; }, '出典の確認日'],
    'review_days 29' => [static function (&$t) { $t['review_days'] = 29; }, 'review_days'],
    'review_days 366' => [static function (&$t) { $t['review_days'] = 366; }, 'review_days'],
    'review_days string' => [static function (&$t) { $t['review_days'] = '180'; }, 'review_days'],
    'ai_assisted missing' => [static function (&$t) { unset($t['ai_assisted']); }, 'ai_assisted'],
    'ai_assisted "yes"' => [static function (&$t) { $t['ai_assisted'] = 'yes'; }, 'ai_assisted'],
    'related unknown key' => [static function (&$t) { $t['related']['news'] = []; }, 'related.news'],
    'related too many' => [static function (&$t) { $t['related']['glossary'] = array_map(static fn($i) => "t$i", range(1, 9)); }, 'related.glossary'],
    'related to itself' => [static function (&$t) { $t['related']['glossary'] = ['test-term']; }, '自分自身'],
    'related bad slug' => [static function (&$t) { $t['related']['tools'] = ['Chat GPT']; }, 'related.tools'],
    'related duplicate' => [static function (&$t) { $t['related']['tools'] = ['a', 'a']; }, '重複'],
    'related learn path outside learn/' => [static function (&$t) { $t['related']['learn_pages'] = ['about']; }, 'learn_pages'],
    'related learn path traversal' => [static function (&$t) { $t['related']['learn_pages'] = ['learn/../wp-admin']; }, 'learn_pages'],
    'OpenAI-style key in content' => [static function (&$t) { $t['content_html'] .= '<p>sk-' . str_repeat('A1', 15) . '</p>'; }, '秘密情報'],
    'AWS key in summary' => [static function (&$t) { $t['summary'] = str_repeat('あ', 30) . 'AKIA' . 'ABCDEFGHIJKLMNOP'; }, '秘密情報'],
    'private key in a source title' => [static function (&$t) { $t['sources'][0]['title'] = '-----BEGIN RSA ' . 'PRIVATE KEY-----'; }, '秘密情報'],
    'invalid UTF-8' => [static function (&$t) { $t['title'] = "\xC3\x28"; }, 'UTF-8'],
    'not an object' => [static function (&$t) { $t = ['a', 'b']; }, 'オブジェクト'],
];
foreach ($cases as $name => [$mutate, $needle]) {
    $t = gl_valid();
    $mutate($t);
    $res = gl_check($t);
    check("T-VAL rejects: $name", gl_has($res, $needle));
}
$t = gl_valid(); $t['content_html'] = '<p>' . str_repeat('あ', 100) . '</p>';
$res = gl_check($t);
check('T-VAL a short explanation is a warning, not an error', [] === $res['errors'] && 1 === count($res['warnings']));
$t = gl_valid(); unset($t['en'], $t['aliases'], $t['level'], $t['related'], $t['review_days']);
check('T-VAL optional fields may be left out', [] === gl_check($t)['errors']);
$t = gl_valid(); $t['sources'][] = ['url' => 'https://news.example.org/a', 'title' => '記事', 'publisher' => 'News', 'kind' => 'media', 'checked_on' => '2026-11-20'];
check('T-VAL a media source next to a primary one is fine', [] === gl_check($t)['errors']);
check('T-VAL the secret scanner names the kind, never the value', ['OpenAI 形式の API キー'] === omochix_core_glossary_secret_findings('x sk-' . 'abcdefghijklmnopqrstuvwxyz0123 y'));

// =============================================== T-COL: the whole set
$cats = ['schema' => 'omochix-glossary-categories/1', 'categories' => array_map(static fn($s) => ['slug' => $s, 'name' => "カテゴリ $s", 'lead' => str_repeat('説明', 12)], ['basics', 'machine-learning', 'llm', 'prompting', 'multimodal', 'agents', 'development', 'data-evaluation', 'safety-law', 'business'])];
check('T-COL categories.json with 10 categories passes', [] === omochix_core_glossary_validate_categories($cats)['errors']);
$c9 = $cats; array_pop($c9['categories']);
check('T-COL 9 categories are refused', [] !== omochix_core_glossary_validate_categories($c9)['errors']);
$cd = $cats; $cd['categories'][1]['slug'] = 'basics';
check('T-COL duplicate category slug is refused', [] !== omochix_core_glossary_validate_categories($cd)['errors']);
$cl = $cats; $cl['categories'][0]['lead'] = '短い';
check('T-COL category lead too short is refused', [] !== omochix_core_glossary_validate_categories($cl)['errors']);
check('T-COL wrong categories schema is refused', [] !== omochix_core_glossary_validate_categories(['categories' => $cats['categories']])['errors']);
$a = gl_valid(); $a['related']['glossary'] = ['test-term-2'];
$b = gl_valid(); $b['slug'] = 'test-term-2'; $b['title'] = 'テスト用語2'; $b['related']['glossary'] = ['test-term'];
$res = omochix_core_glossary_validate_collection(['terms/test-term.json' => $a, 'terms/test-term-2.json' => $b], $cats, ['today' => $TODAY]);
check('T-COL two linked terms pass (draft mode: counts are warnings)', [] === $res['errors'] && 2 === $res['counts']['basics'] && [] !== $res['warnings']);
$res = omochix_core_glossary_validate_collection(['terms/test-term.json' => $a, 'terms/test-term-2.json' => $b], $cats, ['today' => $TODAY, 'release' => true]);
check('T-COL release mode: fewer than 50 terms and thin categories are errors', [] !== preg_grep('/目標 50/', $res['errors']) && [] !== preg_grep('/カテゴリ「llm」/', $res['errors']));
$res = omochix_core_glossary_validate_collection(['terms/test-term.json' => $a, 'terms/other.json' => $a], $cats, ['today' => $TODAY]);
check('T-COL duplicate slug and file name mismatch are errors', [] !== preg_grep('/重複/', $res['errors']) && [] !== preg_grep('/ファイル名/', $res['errors']));
$res = omochix_core_glossary_validate_collection(['terms/test-term.json' => gl_valid()], $cats, ['today' => $TODAY]);
check('T-COL related glossary slug must exist', [] !== preg_grep('/other-term/', $res['errors']));

// ================================================== T-DM-1: closed by default
ih_boot();
$base_flushes = $GLOBALS['flushed']; // Core's own first-boot flushes (practice, Industry Hub)
$glossary_rules = static fn() => array_values(array_filter($GLOBALS['registered']['rewrite'] ?? [], static fn($r) => false !== strpos(json_encode($r), 'glossary')));
$pt = $GLOBALS['registered']['post_types']['omx_glossary'] ?? null;
$tx = $GLOBALS['registered']['taxonomies']['omx_glossary_category']['args'] ?? null;
check('T-DM-1 post type registered', is_array($pt));
check('T-DM-1 closed: not public, not queryable, no rewrite, no archive, no query var, not searchable', false === $pt['public'] && false === $pt['publicly_queryable'] && false === $pt['rewrite'] && false === $pt['has_archive'] && false === $pt['query_var'] && true === $pt['exclude_from_search'] && false === $pt['show_in_nav_menus']);
check('T-DM-1 closed taxonomy: not public, no rewrite, no query var', false === $tx['public'] && false === $tx['publicly_queryable'] && false === $tx['rewrite'] && false === $tx['query_var']);
check('T-DM-1 capability type and map_meta_cap', ['omx_glossary', 'omx_glossaries'] === $pt['capability_type'] && true === $pt['map_meta_cap']);
check('T-DM-1 category terms are managed by administrators only', 'manage_options' === $tx['capabilities']['manage_terms'] && 'manage_options' === $tx['capabilities']['delete_terms'] && 'edit_omx_glossaries' === $tx['capabilities']['assign_terms']);
remove_action('init', 'omochix_core_glossary_init', 12);
ih_boot();
$without = $GLOBALS['flushed'];
add_action('init', 'omochix_core_glossary_init', 12);
ih_boot();
check('T-DM-1 closed: no glossary rewrite rule, nothing written, no extra flush', [] === $glossary_rules() && !isset($GLOBALS['options']['omochix_core_glossary_rewrite_version']) && $without === $GLOBALS['flushed'] && $base_flushes === $GLOBALS['flushed']);
check('T-DM-1 menu position 11 does not collide with Core menus (6-9)', 11 === $pt['menu_position']);
foreach (['1 (truthy, not true)' => 1, '"1"' => '1', 'array' => [true]] as $name => $v) {
    add_filter('omochix_core_glossary_public', $f = static fn() => $v);
    check("T-DM-1 gate stays closed when the filter returns $name", false === omochix_core_glossary_public());
    remove_filter('omochix_core_glossary_public', $f);
}
add_filter('omochix_core_glossary_public', $open = static fn() => true);
ih_boot();
$pt = $GLOBALS['registered']['post_types']['omx_glossary'];
check('T-DM-1 open (filter returns true): public with /glossary/ rules, still no feeds or paging', true === $pt['public'] && 'glossary' === $pt['has_archive'] && 'glossary' === $pt['rewrite']['slug'] && false === $pt['rewrite']['feeds'] && false === $pt['rewrite']['pages'] && true === $pt['exclude_from_search']);
check('T-DM-1 open: one extra flush, the version written, the category rule added before single terms', $base_flushes + 1 === $GLOBALS['flushed'] && '1:open' === ($GLOBALS['options']['omochix_core_glossary_rewrite_version'] ?? '') && 1 === count($glossary_rules()) && 'top' === $glossary_rules()[0][2]);
$opts = $GLOBALS['options']; $GLOBALS['flushed'] = 0;
omochix_core_glossary_maybe_flush_rewrite_rules();
check('T-DM-1 open: no second flush', 0 === $GLOBALS['flushed']);
remove_filter('omochix_core_glossary_public', $open);
$GLOBALS['options'] = $opts;
omochix_core_glossary_maybe_flush_rewrite_rules();
check('T-DM-1 closing again flushes once and records it', 1 === $GLOBALS['flushed'] && '1:closed' === $GLOBALS['options']['omochix_core_glossary_rewrite_version']);
ih_boot();

// ======================================================= T-CAP-1
$roles = [
    'administrator' => ['manage_options' => true, 'edit_others_posts' => true, 'edit_posts' => true],
    'editor' => ['edit_others_posts' => true, 'edit_posts' => true],
    'author' => ['edit_posts' => true, 'publish_posts' => true],
    'contributor' => ['edit_posts' => true],
    'subscriber' => ['read' => true],
    'visitor' => [],
];
foreach ($roles as $role => $caps) {
    $got = omochix_core_glossary_user_has_cap($caps);
    $has = array_values(array_intersect(omochix_core_glossary_primitive_caps(), array_keys(array_filter($got))));
    $want = in_array($role, ['administrator', 'editor'], true) ? omochix_core_glossary_primitive_caps() : [];
    check("T-CAP-1 $role: " . ($want ? 'all glossary capabilities' : 'none'), $has === $want);
}
check('T-CAP-1 a stale grant from a role is removed for non-editors', !isset(omochix_core_glossary_user_has_cap(['edit_posts' => true, 'publish_omx_glossaries' => true])['publish_omx_glossaries']));
check('T-CAP-1 nothing is written to options or roles', !isset($GLOBALS['options']['wp_user_roles']));

// ======================================================= T-META
$meta = $GLOBALS['registered']['post_meta']['omx_glossary'] ?? [];
check('T-META every field registered single, typed, sanitized, with an auth callback', array_keys(omochix_core_glossary_meta_fields()) === array_keys($meta) && [] === array_filter($meta, static fn($m) => true !== $m['single'] || !is_callable($m['sanitize_callback']) || !is_callable($m['auth_callback'])));
$GLOBALS['caps'] = [];
check('T-META auth callback refuses users who cannot edit the post', false === ($meta['glossary_summary']['auth_callback'])(true, 'glossary_summary', 5));
$GLOBALS['caps'] = ['edit_post' => true];
check('T-META auth callback allows editors of the post', true === ($meta['glossary_summary']['auth_callback'])(false, 'glossary_summary', 5));
$GLOBALS['caps'] = [];
$s = omochix_core_glossary_sanitize_meta([['url' => 'http://a.example.org/'], ['url' => 'https://10.0.0.1/'], ['url' => 'https://docs.example.org/a?token=x'], ['url' => 'https://docs.example.org/ok', 'title' => '<b>T</b>', 'publisher' => 'P', 'kind' => 'evil', 'checked_on' => 'x'], 'junk'], 'glossary_sources');
check('T-META sources: refused URLs are dropped, text stripped, bad kind/date emptied', 1 === count($s) && 'https://docs.example.org/ok' === $s[0]['url'] && 'T' === $s[0]['title'] && '' === $s[0]['kind'] && '' === $s[0]['checked_on']);
check('T-META aliases: tags stripped, duplicates and empties dropped, capped at 10', ['a', 'b'] === omochix_core_glossary_sanitize_meta(['<i>a</i>', 'a', '', 'b'], 'glossary_aliases') && 10 === count(omochix_core_glossary_sanitize_meta(array_map('strval', range(1, 20)), 'glossary_aliases')));
check('T-META ids: positive integers only (no -1 turned into 1), unique, capped', [3, 5] === omochix_core_glossary_sanitize_meta(['3', 5, 5, 'x', -1, '-1', 0, '0', 1.5, '07a'], 'related_glossary_ids') && 4 === count(omochix_core_glossary_sanitize_meta(range(1, 9), 'related_industry_ids')));
check('T-META review_days outside 30-365 falls back to 180', 180 === omochix_core_glossary_sanitize_meta(9999, 'glossary_review_days') && 90 === omochix_core_glossary_sanitize_meta('90', 'glossary_review_days'));
check('T-META summary: newlines and tags removed', 'a b' === omochix_core_glossary_sanitize_meta("<p>a\nb</p>", 'glossary_summary'));

// ============================================ helpers: a stored term
function gl_store($status = 'draft', array $over = []) {
    $v = gl_valid();
    $id = ih_add_post(array_merge(['post_type' => 'omx_glossary', 'post_status' => $status, 'post_name' => $v['slug'], 'post_title' => $v['title'], 'post_content' => $v['content_html']], $over['post'] ?? []));
    $m = ['glossary_reading' => $v['reading'], 'glossary_en' => $v['en'], 'glossary_aliases' => $v['aliases'], 'glossary_summary' => $v['summary'], 'glossary_sources' => $v['sources'], 'glossary_verified_on' => $v['verified_on'], 'glossary_review_days' => 180, 'glossary_ai_assisted' => true, 'glossary_level' => 'beginner'];
    foreach (array_merge($m, $over['meta'] ?? []) as $k => $val) { update_post_meta($id, $k, $val); }
    $cat = ih_term('omx_glossary_category', 'basics') ?: (wp_insert_term('AIの基礎概念', 'omx_glossary_category', ['slug' => 'basics']) && true ? ih_term('omx_glossary_category', 'basics') : null);
    wp_set_object_terms($id, $over['cats'] ?? ['basics'], 'omx_glossary_category');
    return $id;
}
wp_insert_term('AIの基礎概念', 'omx_glossary_category', ['slug' => 'basics']);
wp_insert_term('生成AI', 'omx_glossary_category', ['slug' => 'llm']);

// ======================================================= T-GD-1 guard
$id = gl_store('draft');
$data = ['post_type' => 'omx_glossary', 'post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']];
check('T-GD-1 a complete draft may be published', 'publish' === omochix_core_glossary_guard_insert_data($data, ['ID' => $id])['post_status']);
$bad = gl_store('draft', ['meta' => ['glossary_sources' => []]]);
check('T-GD-1 no source: publish becomes draft', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $bad])['post_status']);
check('T-GD-1 the reasons are kept for the editor', [] !== preg_grep('/sources/', (array) get_transient('omochix_core_glossary_hold_' . $bad)));
check('T-GD-1 scheduling (future) is held the same way', 'draft' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_status' => 'future']), ['ID' => $bad])['post_status']);
check('T-GD-1 a new post with no meta cannot be published', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => 0])['post_status']);
$two = gl_store('draft', ['cats' => ['basics', 'llm']]);
check('T-GD-1 two categories: held', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $two])['post_status']);
check('T-GD-1 categories submitted with the save are checked (tax_input)', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $id, 'tax_input' => ['omx_glossary_category' => ['basics', 'llm']]])['post_status']);
check('T-GD-1 script in the submitted content: held', 'draft' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_content' => $data['post_content'] . '<script>x</script>']), ['ID' => $id])['post_status']);
check('T-GD-1 a key in the submitted title: held', 'draft' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_title' => 'AKIA' . 'ABCDEFGHIJKLMNOP']), ['ID' => $id])['post_status']);
$future = gl_store('draft', ['meta' => ['glossary_verified_on' => '2099-01-01']]);
check('T-GD-1 a future check date: held', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $future])['post_status']);
$live = gl_store('publish', ['meta' => ['glossary_sources' => []]]);
check('T-GD-1 a live term is never demoted by the guard', 'publish' === omochix_core_glossary_guard_insert_data($data, ['ID' => $live])['post_status']);
check('T-GD-1 other post types are untouched', 'publish' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_type' => 'post']), ['ID' => $bad])['post_status']);
check('T-GD-1 draft saves are untouched', 'draft' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_status' => 'draft']), ['ID' => $bad])['post_status']);
class GL_Req { public $p; public $r; function __construct($p = [], $r = '/wp/v2/glossary') { $this->p = $p; $this->r = $r; } function get_param($k) { return $this->p[$k] ?? null; } function get_route() { return $this->r; } }
$prep = (object) ['ID' => $bad, 'post_status' => 'publish', 'post_type' => 'omx_glossary'];
$r = omochix_core_glossary_guard_rest($prep, new GL_Req());
check('T-GD-1 REST: publishing an incomplete term is refused (400 with reasons)', $r instanceof WP_Error && 400 === $r->data['status'] && [] !== preg_grep('/sources/', $r->data['problems']));
$r = omochix_core_glossary_guard_rest($prep, new GL_Req(['meta' => ['glossary_sources' => gl_valid()['sources']]]));
check('T-GD-1 REST: the meta sent with the request is checked (now complete)', !($r instanceof WP_Error));
$r = omochix_core_glossary_guard_rest((object) ['ID' => $id, 'post_status' => 'publish'], new GL_Req(['meta' => ['glossary_sources' => [['url' => 'http://insecure.example.org/']]]]));
check('T-GD-1 REST: meta sent with the request is sanitized first (a refused URL leaves no source)', $r instanceof WP_Error);
$r = omochix_core_glossary_guard_rest((object) ['ID' => $id, 'post_status' => 'publish'], new GL_Req(['glossary-categories' => [ih_term('omx_glossary_category', 'basics')->term_id, ih_term('omx_glossary_category', 'llm')->term_id]]));
check('T-GD-1 REST: two categories sent with the request are refused', $r instanceof WP_Error);
check('T-GD-1 REST: drafts pass', !(omochix_core_glossary_guard_rest((object) ['ID' => $bad, 'post_status' => 'draft'], new GL_Req()) instanceof WP_Error));
$GLOBALS['posts'][$bad]->post_status = 'future';
do_action('publish_future_post', $bad);
check('T-GD-1 cron: a due incomplete term is set back to draft', 'draft' === get_post_field('post_status', $bad));
check('T-GD-1 cron: WordPress\'s own scheduled publishing is put back', false !== has_action('publish_future_post', 'check_and_publish_future_post'));

// ========================================== T-VIS: gate G-B
$pub = gl_store('publish');
$v = omochix_core_glossary_visibility($pub, $TODAY);
check('T-VIS closed glossary: even a complete published term is not visible', false === $v['visible'] && false === $v['indexable']);
add_filter('omochix_core_glossary_public', $open);
$v = omochix_core_glossary_visibility($pub, $TODAY);
check('T-VIS open: a complete published term is visible and indexable', true === $v['visible'] && true === $v['indexable'] && false === $v['review'] && '2027-05-20' === $v['due']);
check('T-VIS review date = verified_on + 180 days; on that day no review yet', false === omochix_core_glossary_visibility($pub, '2027-05-20')['review']);
$d = omochix_core_glossary_visibility($pub, '2027-05-21');
check('T-VIS the day after: review due, still indexable', true === $d['review'] && true === $d['indexable']);
check('T-VIS 30 days after the review date: still indexable', true === omochix_core_glossary_visibility($pub, '2027-06-19')['indexable']);
$d = omochix_core_glossary_visibility($pub, '2027-06-20');
check('T-VIS 31 days after: visible but noindex', true === $d['visible'] && false === $d['indexable']);
check('T-VIS draft: not visible', false === omochix_core_glossary_visibility(gl_store('draft'), $TODAY)['visible']);
check('T-VIS password: not visible', false === omochix_core_glossary_visibility(gl_store('publish', ['post' => ['post_password' => 'x']]), $TODAY)['visible']);
check('T-VIS published but failing the rules (live, never demoted): hidden', false === omochix_core_glossary_visibility($live, $TODAY)['visible']);
check('T-VIS other post types: nothing', false === omochix_core_glossary_visibility(ih_add_post(['post_type' => 'post', 'post_status' => 'publish']), $TODAY)['visible']);

// ===================================================== T-SEC (unit)
$GLOBALS['caps'] = [];
foreach (['/wp/v2/glossary', '/wp/v2/glossary/12', '/WP/V2/Glossary', '//wp/v2//glossary', '/wp/v2/glossary-categories', '/wp/v2/glossary-categories/3', '/wp/v2/types/omx_glossary', '/wp/v2/taxonomies/omx_glossary_category', '/wp/v2/%67lossary'] as $route) {
    $r = omochix_core_glossary_rest_guard(null, null, new GL_Req([], $route));
    check("T-SEC-2 REST $route: 401 for visitors (also while open)", $r instanceof WP_Error && 401 === $r->data['status']);
}
foreach (['/wp/v2/posts', '/wp/v2/glossaryx', '/wp/v2/types', '/omochix/v1/glossary'] as $route) {
    check("T-SEC-2 REST $route: untouched", null === omochix_core_glossary_rest_guard(null, null, new GL_Req([], $route)));
}
$GLOBALS['caps'] = ['edit_others_omx_glossaries' => true];
check('T-SEC-2 REST: editors pass', null === omochix_core_glossary_rest_guard(null, null, new GL_Req([], '/wp/v2/glossary')));
$GLOBALS['caps'] = [];
class GL_Res { public $d; function __construct($d) { $this->d = $d; } function get_data() { return $this->d; } function set_data($d) { $this->d = $d; } }
$res = omochix_core_glossary_rest_lists(new GL_Res(['post' => 1, 'omx_glossary' => 2]), null, new GL_Req([], '/wp/v2/types'));
check('T-SEC-2 /wp/v2/types lists no glossary for visitors', ['post' => 1] === $res->d);
$res = omochix_core_glossary_rest_lists(new GL_Res(['category' => 1, 'omx_glossary_category' => 2]), null, new GL_Req([], '/wp/v2/taxonomies/'));
check('T-SEC-2 /wp/v2/taxonomies lists no glossary category for visitors', ['category' => 1] === $res->d);
check('T-SEC-2 /wp/v2/search never searches the glossary for visitors', ['post', 'page'] === omochix_core_glossary_rest_search_args(['post_type' => ['post', 'omx_glossary', 'page']])['post_type'] && ['__omx_none__'] === omochix_core_glossary_rest_search_args(['post_type' => 'omx_glossary'])['post_type']);
$other = ih_add_post(['post_type' => 'post', 'post_status' => 'publish']);
check('T-SEC-7 oEmbed: a term is never embedded, other posts are untouched', 0 === omochix_core_glossary_oembed_post_id($pub) && $other === omochix_core_glossary_oembed_post_id($other));
remove_filter('omochix_core_glossary_public', $open);
check('T-SEC-3 closed: no glossary in sitemap post types (core shape)', ['post' => 1] === omochix_core_glossary_sitemap_post_types(['post' => 1, 'omx_glossary' => 2]));
check('T-SEC-3 closed: no glossary in sitemap post types (Slim SEO shape)', ['post', 'page'] === omochix_core_glossary_sitemap_post_types(['post', 'omx_glossary', 'page']));
check('T-SEC-3 closed: no glossary category in sitemap taxonomies (both shapes)', ['category'] === omochix_core_glossary_sitemap_taxonomies(['category', 'omx_glossary_category']) && ['category' => 1] === omochix_core_glossary_sitemap_taxonomies(['category' => 1, 'omx_glossary_category' => 2]));
check('T-SEC-3 closed: every term is ignored by the Slim SEO sitemap', true === omochix_core_glossary_sitemap_post_ignore(false, $pub));
add_filter('omochix_core_glossary_public', $open);
check('T-SEC-3 open: the post type stays listed', ['post', 'omx_glossary'] === omochix_core_glossary_sitemap_post_types(['post', 'omx_glossary']));
check('T-SEC-5 open: an indexable term is kept, a failing one ignored', false === omochix_core_glossary_sitemap_post_ignore(false, $pub) && true === omochix_core_glossary_sitemap_post_ignore(false, $live));
check('T-SEC-3 other posts are untouched by the sitemap filter', false === omochix_core_glossary_sitemap_post_ignore(false, ih_add_post(['post_type' => 'post'])));
remove_filter('omochix_core_glossary_public', $open);

// ================================== fixes after the security review of PR #79
// ---------------------------------------------- P1-2: REST meta (custom-fields)
$pt = $GLOBALS['registered']['post_types']['omx_glossary'];
// Not $meta: at file level that name is the stub's meta store ($GLOBALS['meta']).
check('P1-2 the post type supports custom-fields (WordPress saves registered meta over REST only with it)', in_array('custom-fields', $pt['supports'], true));
check('P1-2 the other supports are unchanged', ['title', 'editor', 'revisions', 'author', 'custom-fields'] === $pt['supports']);
check('P1-2 the generic Custom Fields box is taken off the glossary screen', false !== has_action('add_meta_boxes_omx_glossary', 'omochix_core_glossary_remove_custom_fields_box'));
$reg_meta = $GLOBALS['registered']['post_meta']['omx_glossary'];
$rest_ok = true;
foreach (omochix_core_glossary_meta_fields() as $key => $type) {
    $r = $reg_meta[$key]['show_in_rest'] ?? false;
    $is_list = in_array($type, ['array', 'sources', 'ids'], true);
    // WordPress refuses to register an array meta for REST without an items schema.
    $rest_ok = $rest_ok && ($is_list ? (is_array($r) && 'array' === ($r['schema']['type'] ?? '') && isset($r['schema']['items'])) : true === $r);
    $rest_ok = $rest_ok && true === $reg_meta[$key]['single'] && true === ($reg_meta[$key]['revisions_enabled'] ?? false);
}
check('P1-2 every field is in REST: scalars as is, lists with an items schema; revisions need the revisions support', $rest_ok && in_array('revisions', $pt['supports'], true));
/**
 * What WP_REST_Meta_Fields does with the meta of a request, as far as this module decides it:
 * no meta at all without custom-fields support, then per key the capability (auth callback),
 * the sanitize callback and the write. A model: the real controller is checked on the isolated WordPress.
 */
function gl_rest_meta($post_id, array $values) {
    $pt = $GLOBALS['registered']['post_types']['omx_glossary'];
    if (!in_array('custom-fields', $pt['supports'], true)) { return 'unsupported'; }
    $reg = $GLOBALS['registered']['post_meta']['omx_glossary'];
    foreach ($values as $k => $v) {
        if (!isset($reg[$k]) || empty($reg[$k]['show_in_rest'])) { return 'unknown:' . $k; }
        if (!($reg[$k]['auth_callback'])(false, $k, $post_id)) { return 'forbidden:' . $k; }
    }
    foreach ($values as $k => $v) { update_post_meta($post_id, $k, ($reg[$k]['sanitize_callback'])($v)); }
    return 'saved';
}
$rm = gl_store('draft', ['meta' => ['glossary_sources' => [], 'glossary_summary' => '']]);
$GLOBALS['caps'] = [];
check('P1-2 REST meta write: refused for a user who cannot edit the term, nothing stored', 'forbidden:glossary_summary' === gl_rest_meta($rm, ['glossary_summary' => gl_valid()['summary']]) && '' === get_post_meta($rm, 'glossary_summary', true));
$GLOBALS['caps'] = ['edit_post' => true];
check('P1-2 REST meta write: an editor saves, and the value reads back', 'saved' === gl_rest_meta($rm, ['glossary_summary' => gl_valid()['summary'], 'glossary_sources' => gl_valid()['sources'], 'glossary_review_days' => 90, 'glossary_ai_assisted' => true]) && gl_valid()['summary'] === get_post_meta($rm, 'glossary_summary', true) && gl_valid()['sources'] === get_post_meta($rm, 'glossary_sources', true) && 90 === get_post_meta($rm, 'glossary_review_days', true));
gl_rest_meta($rm, ['glossary_summary' => "<script>x</script>a\nb", 'glossary_sources' => [['url' => 'https://docs.example.org/x?access-token=abc', 'title' => 't', 'publisher' => 'p', 'kind' => 'docs', 'checked_on' => '2026-11-20']], 'related_glossary_ids' => ['7', -1, 'x']]);
check('P1-2 REST meta write is sanitized: tags and newlines out, a URL with a credential dropped, IDs positive only', 'xa b' === get_post_meta($rm, 'glossary_summary', true) && [] === get_post_meta($rm, 'glossary_sources', true) && [7] === get_post_meta($rm, 'related_glossary_ids', true));
check('P1-2 REST meta write: a key the module did not register is not written', 'unknown:_edit_lock' === gl_rest_meta($rm, ['_edit_lock' => 'x']));
$GLOBALS['caps'] = [];

// ---------------------------------------------- P1-3: block editor content
$para = str_repeat('この段落はテスト用の文章で、意味のある説明ではありません。', 16);
$blocks = "<!-- wp:paragraph -->\n<p>$para</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">見出し</h2>\n<!-- /wp:heading -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">小見出し</h3>\n<!-- /wp:heading -->\n\n"
    . "<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>項目 <strong>太字</strong> と <a href=\"https://docs.example.org/spec\">リンク</a></li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->\n\n"
    . "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\"><!-- wp:list-item -->\n<li>一</li>\n<!-- /wp:list-item --></ol>\n<!-- /wp:list -->\n\n"
    . "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>引用</p>\n<!-- /wp:paragraph --></blockquote>\n<!-- /wp:quote -->\n\n"
    . "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>x = 1 &lt; 2</code></pre>\n<!-- /wp:code -->\n\n<!-- wp:preformatted -->\n<pre class=\"wp-block-preformatted\">整形済み</pre>\n<!-- /wp:preformatted -->\n\n"
    . "<!-- wp:table {\"hasFixedLayout\":true} -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\"><thead><tr><th>列</th></tr></thead><tbody><tr><td>値</td></tr></tbody></table></figure>\n<!-- /wp:table -->";
$t = gl_valid(); $t['content_html'] = $blocks;
$res = gl_check($t);
check('P1-3 a term written in the block editor (paragraph, headings, lists, quote, code, table) passes', [] === $res['errors'], implode(' / ', $res['errors']));
$norm = omochix_core_glossary_normalize_content($blocks);
check('P1-3 normalized: no comment, no class, no figure; the tags themselves are kept', false === strpos($norm, '<!--') && false === strpos($norm, 'class=') && false === stripos($norm, 'figure') && false !== strpos($norm, '<h3>小見出し</h3>') && false !== strpos($norm, '<table><thead>') && false !== strpos($norm, '<a href="https://docs.example.org/spec">'));
$plain = gl_valid()['content_html'];
check('P1-3 hand-written HTML is not changed by the normalization', $plain === omochix_core_glossary_normalize_content($plain));
check('P1-3 the text length is counted without the block comments', omochix_core_glossary_len(omochix_core_glossary_text($blocks)) === omochix_core_glossary_len(omochix_core_glossary_text($norm)));
$wrap = static fn($inner) => "<!-- wp:paragraph -->\n<p>" . str_repeat('この段落はテスト用の文章で、意味のある説明ではありません。', 16) . "</p>\n<!-- /wp:paragraph -->\n\n" . $inner;
$bad_blocks = [
    'custom HTML block' => ["<!-- wp:html -->\n<p>a</p>\n<!-- /wp:html -->", 'ブロック'],
    'image block' => ['<!-- wp:image {"id":5} --><figure class="wp-block-image"><img src="https://a.example.org/x.png" alt=""/></figure><!-- /wp:image -->', '<img>'],
    'embed block' => ['<!-- wp:embed {"url":"https://www.youtube.com/watch?v=x"} --><figure class="wp-block-embed">https://www.youtube.com/watch?v=x</figure><!-- /wp:embed -->', 'ブロック'],
    'shortcode block' => ['<!-- wp:shortcode -->[gallery]<!-- /wp:shortcode -->', 'ブロック'],
    'a dynamic block written as void' => ['<!-- wp:latest-posts /-->', 'ブロック'],
    'an allowed block written as void' => ['<!-- wp:paragraph /-->', 'ブロック'],
    'a third-party block' => ['<!-- wp:acme/widget --><p>a</p><!-- /wp:acme/widget -->', 'ブロック'],
    'paragraph with a style setting' => ['<!-- wp:paragraph {"style":{"color":{"text":"#ff0000"}}} --><p>a</p><!-- /wp:paragraph -->', 'ブロック'],
    'paragraph with className' => ['<!-- wp:paragraph {"className":"x"} --><p>a</p><!-- /wp:paragraph -->', 'ブロック'],
    'paragraph bound to post meta (block bindings)' => ['<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"glossary_summary"}}}}} --><p>a</p><!-- /wp:paragraph -->', 'ブロック'],
    'heading with an anchor' => ['<!-- wp:heading {"anchor":"x"} --><h2>a</h2><!-- /wp:heading -->', 'ブロック'],
    'heading level 1' => ['<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">a</h1><!-- /wp:heading -->', 'ブロック'],
    'heading level as a string' => ['<!-- wp:heading {"level":"3"} --><h3>a</h3><!-- /wp:heading -->', 'ブロック'],
    'list setting that is not a boolean' => ['<!-- wp:list {"ordered":"<script>"} --><ol><li>a</li></ol><!-- /wp:list -->', 'ブロック'],
    'settings that are not an object' => ['<!-- wp:paragraph [1] --><p>a</p><!-- /wp:paragraph -->', 'ブロック'],
    'settings on a closing comment' => ['<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph {"level":2} -->', 'ブロック'],
    'a comment that is not a block' => ['<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --><!-- note -->', 'コメント'],
    'a block comment with no space (not what the editor writes)' => ['<!--wp:paragraph--><p>a</p><!--/wp:paragraph-->', 'ブロック'],
    'script inside a paragraph block' => ['<!-- wp:paragraph --><p>a<script>alert(1)</script></p><!-- /wp:paragraph -->', '<script>'],
    'a tag split by a block comment' => ['<scr<!-- wp:paragraph -->ipt>alert(1)</script>', '<script>'],
    'iframe inside a block' => ['<!-- wp:paragraph --><p><iframe src="https://a.example.org"></iframe></p><!-- /wp:paragraph -->', '<iframe>'],
    'event handler next to an allowed class' => ['<!-- wp:heading --><h2 class="wp-block-heading" onclick="x()">a</h2><!-- /wp:heading -->', 'on〜'],
    'javascript: link inside a block' => ['<!-- wp:paragraph --><p><a href="javascript:alert(1)">a</a></p><!-- /wp:paragraph -->', 'javascript'],
    'a class the editor does not write' => ['<!-- wp:heading --><h2 class="wp-block-heading evil">a</h2><!-- /wp:heading -->', 'class'],
    'a colour class' => ['<!-- wp:paragraph --><p class="has-text-color has-red-color">a</p><!-- /wp:paragraph -->', 'class'],
    'an allowed class on another tag' => ['<!-- wp:paragraph --><p class="wp-block-heading">a</p><!-- /wp:paragraph -->', 'class'],
    'class in single quotes' => ["<!-- wp:heading --><h2 class='wp-block-heading'>a</h2><!-- /wp:heading -->", 'class'],
    'class without quotes' => ['<!-- wp:heading --><h2 class=wp-block-heading>a</h2><!-- /wp:heading -->', 'class'],
    'an empty class' => ['<!-- wp:heading --><h2 class="">a</h2><!-- /wp:heading -->', 'class'],
    'class and id' => ['<!-- wp:heading --><h2 class="wp-block-heading" id="x">a</h2><!-- /wp:heading -->', 'class'],
    'style attribute in a block' => ['<!-- wp:paragraph --><p style="color:red">a</p><!-- /wp:paragraph -->', 'style'],
    'a figure that is not the table wrapper' => ['<figure><table><tbody><tr><td>a</td></tr></tbody></table></figure>', '<figure>'],
    'an image figure' => ['<figure class="wp-block-image"><table><tbody><tr><td>a</td></tr></tbody></table></figure>', 'class'],
    'a table caption' => ['<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>a</td></tr></tbody></table><figcaption class="wp-element-caption">c</figcaption></figure><!-- /wp:table -->', '<figcaption>'],
    'link opening in a new tab' => ['<!-- wp:paragraph --><p><a href="https://docs.example.org/" target="_blank" rel="noreferrer noopener">a</a></p><!-- /wp:paragraph -->', 'href="…" だけ'],
];
foreach ($bad_blocks as $name => [$inner, $needle]) {
    $t = gl_valid(); $t['content_html'] = $wrap($inner);
    $res = gl_check($t);
    check("P1-3 rejects: $name", gl_has($res, $needle), implode(' / ', $res['errors']));
}
$bd = gl_store('draft', ['post' => ['post_content' => $blocks]]);
check('P1-3 guard: a complete term with block editor content may be published', 'publish' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_content' => wp_slash($blocks)]), ['ID' => $bd])['post_status']);
check('P1-3 guard: block editor content with a custom HTML block is held', 'draft' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_content' => wp_slash($blocks . "\n<!-- wp:html --><p>x</p><!-- /wp:html -->")]), ['ID' => $bd])['post_status']);

// ---------------------------------------------- P1-4: REST publish in one request
/**
 * The order of WP_REST_Posts_Controller::update_item / create_item, as far as the guard is in it:
 * rest_pre_insert (the REST guard) -> wp_insert_post_data (the insert guard, before any meta of the
 * request is stored) -> meta and categories stored -> rest_request_after_callbacks.
 * Returns the stored status, or the WP_Error of the REST guard.
 */
function gl_rest_save($id, array $params, array $post = [], $status = 'publish') {
    $v = gl_valid();
    $fields = array_merge(['post_name' => $v['slug'], 'post_title' => $v['title'], 'post_content' => $v['content_html']], $post);
    $prepared = (object) array_merge(['post_status' => $status, 'post_type' => 'omx_glossary'], $fields, $id ? ['ID' => $id] : []);
    $r = apply_filters('rest_pre_insert_omx_glossary', $prepared, new GL_Req($params));
    if ($r instanceof WP_Error) { apply_filters('rest_request_after_callbacks', $r, null, null); return $r; }
    $out = apply_filters('wp_insert_post_data', array_merge(['post_type' => 'omx_glossary', 'post_status' => $status], array_map('wp_slash', $fields)), ['ID' => $id]);
    if (!$id) { $id = ih_add_post(array_merge(['post_type' => 'omx_glossary'], $fields)); }
    $GLOBALS['posts'][$id]->post_status = $out['post_status'];
    foreach ($params['meta'] ?? [] as $k => $val) { update_post_meta($id, $k, omochix_core_glossary_sanitize_meta($val, $k)); }
    if (isset($params['glossary-categories'])) { wp_set_object_terms($id, array_map(static function ($tid) { foreach ($GLOBALS['terms']['omx_glossary_category'] as $t) { if ($t->term_id === (int) $tid) { return $t->slug; } } return 'x'; }, $params['glossary-categories']), 'omx_glossary_category'); }
    apply_filters('rest_request_after_callbacks', null, null, null);
    $GLOBALS['gl_last_id'] = $id;
    return $out['post_status'];
}
$full_meta = ['glossary_reading' => gl_valid()['reading'], 'glossary_summary' => gl_valid()['summary'], 'glossary_sources' => gl_valid()['sources'], 'glossary_verified_on' => gl_valid()['verified_on'], 'glossary_ai_assisted' => true];
$basics_id = ih_term('omx_glossary_category', 'basics')->term_id;
$llm_id = ih_term('omx_glossary_category', 'llm')->term_id;
$empty = static fn() => gl_store('draft', ['meta' => ['glossary_reading' => '', 'glossary_summary' => '', 'glossary_sources' => [], 'glossary_verified_on' => ''], 'cats' => []]);
$e1 = $empty();
check('P1-4 reproduction: the insert guard alone judges by the stored (old) meta and holds the term', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $e1])['post_status']);
check('P1-4 REST: meta, category and publish in one request -> published (was silently put back to draft)', 'publish' === gl_rest_save($e1, ['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
check('P1-4 REST: what was published is complete as stored (gate G-B agrees)', [] === omochix_core_glossary_publish_problems(get_post($e1)));
check('P1-4 REST: creating a new term complete and published in one request works', 'publish' === gl_rest_save(0, ['meta' => $full_meta, 'glossary-categories' => [$basics_id]]) && [] === omochix_core_glossary_publish_problems(get_post($GLOBALS['gl_last_id'])));
$e2 = $empty();
check('P1-4 REST: scheduling (future) in one request is kept', 'future' === gl_rest_save($e2, ['meta' => $full_meta, 'glossary-categories' => [$basics_id]], [], 'future'));
$e3 = $empty();
$r = gl_rest_save($e3, ['meta' => array_merge($full_meta, ['glossary_sources' => []]), 'glossary-categories' => [$basics_id]]);
check('P1-4 REST: an incomplete request is refused with 400 and reasons, the term stays a draft', $r instanceof WP_Error && 400 === $r->data['status'] && [] !== preg_grep('/sources/', $r->data['problems']) && 'draft' === get_post_field('post_status', $e3));
check('P1-4 after a refused request nothing is handed over: a later save is judged by the stored values', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $e3])['post_status']);
$r = gl_rest_save($e3, ['meta' => $full_meta]);
check('P1-4 REST: complete meta but no category (none stored, none sent) is refused', $r instanceof WP_Error && [] !== preg_grep('/category/', $r->data['problems']));
$r = gl_rest_save($e3, ['meta' => $full_meta, 'glossary-categories' => [$basics_id, $llm_id]]);
check('P1-4 REST: two categories in the request are refused', $r instanceof WP_Error);
$r = gl_rest_save($e3, ['meta' => array_merge($full_meta, ['glossary_verified_on' => '2099-01-01']), 'glossary-categories' => [$basics_id]]);
check('P1-4 REST: a future check date in the request is refused', $r instanceof WP_Error && [] !== preg_grep('/未来/', $r->data['problems']));
$r = gl_rest_save($e3, ['meta' => $full_meta, 'glossary-categories' => [$basics_id]], ['post_content' => gl_valid()['content_html'] . '<script>x</script>']);
check('P1-4 REST: forbidden HTML in the request is refused', $r instanceof WP_Error && [] !== preg_grep('/script/', $r->data['problems']));
$r = gl_rest_save(0, ['meta' => $full_meta, 'glossary-categories' => [$basics_id]], ['post_name' => '']);
check('P1-4 REST: a new term without a slug is refused (no silent draft)', $r instanceof WP_Error && [] !== preg_grep('/slug/', $r->data['problems']));
// The hand-over itself.
$e4 = $empty(); $e5 = $empty();
$ok = omochix_core_glossary_guard_rest((object) ['ID' => $e4, 'post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']], new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
check('P1-4 hand-over: values accepted for one term are not used for another', !($ok instanceof WP_Error) && 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $e5])['post_status']);
check('P1-4 hand-over: the insert guard still runs every rule on the post as it will be stored (script added after the REST check)', 'draft' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_content' => $data['post_content'] . '<script>x</script>']), ['ID' => $e4])['post_status']);
check('P1-4 hand-over: used once (the save above took it; the next one sees the stored values)', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $e4])['post_status']);
omochix_core_glossary_guard_rest((object) ['ID' => $e4, 'post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']], new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
apply_filters('rest_request_after_callbacks', null, null, null);
check('P1-4 hand-over: forgotten when the REST call ends (nothing reaches the next call of a batch)', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $e4])['post_status']);
omochix_core_glossary_guard_rest((object) ['ID' => $e4, 'post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']], new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
check('P1-4 hand-over: categories sent with the classic form still win (two categories: held)', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $e4, 'tax_input' => ['omx_glossary_category' => ['basics', 'llm']]])['post_status']);
omochix_core_glossary_pending(null);
$r = omochix_core_glossary_guard_rest((object) ['ID' => $id, 'post_status' => 'publish'], new GL_Req(['meta' => ['glossary_sources' => []]]));
check('P1-4 hand-over: the values of a refused request are dropped at once (a later save of the complete stored term in the same PHP request publishes)', $r instanceof WP_Error && 'publish' === omochix_core_glossary_guard_insert_data($data, ['ID' => $id])['post_status']);
omochix_core_glossary_pending(null);
check('P1-4 hand-over: a draft REST save hands nothing over', !(omochix_core_glossary_guard_rest((object) ['ID' => $e4, 'post_status' => 'draft'], new GL_Req(['meta' => $full_meta])) instanceof WP_Error) && null === omochix_core_glossary_pending($e4));
// What WordPress does with the meta of a request, after the post is saved: it converts by the REST
// schema ("a,b" becomes a list, "1" becomes true) and refuses what does not fit (an unknown property).
// So the REST guard must refuse, before the post is saved, anything that is not already the stored type.
$sh = static fn($meta) => omochix_core_glossary_guard_rest((object) ['ID' => $e4, 'post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']], new GL_Req(['meta' => array_merge($full_meta, $meta), 'glossary-categories' => [$basics_id]]));
$bad_shapes = [
    'a source with an extra property (WordPress refuses the whole meta after publishing)' => ['glossary_sources' => [array_merge(gl_valid()['sources'][0], ['note' => 'x'])]],
    'aliases as a comma string (WordPress stores it as a list the guard never saw)' => ['glossary_aliases' => 'テスト用語,別名'],
    'aliases with a number' => ['glossary_aliases' => ['a', 5]],
    'aliases as an object' => ['glossary_aliases' => ['x' => 'a']],
    'sources as an object' => ['glossary_sources' => ['a' => gl_valid()['sources'][0]]],
    'a source that is a string' => ['glossary_sources' => ['https://docs.example.org/spec']],
    'a source property that is not text' => ['glossary_sources' => [array_merge(gl_valid()['sources'][0], ['title' => ['x']])]],
    'summary as a list' => ['glossary_summary' => [gl_valid()['summary']]],
    'review days as text' => ['glossary_review_days' => '180'],
    'AI flag as text' => ['glossary_ai_assisted' => 'false'],
    'AI flag as a number' => ['glossary_ai_assisted' => 1],
    'IDs as text' => ['related_tool_ids' => ['7']],
    'IDs as a comma string' => ['related_tool_ids' => '7,8'],
];
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
foreach ($bad_shapes as $name => $m) {
    $r = $sh($m);
    check("P1-4 REST refuses before saving: $name", $r instanceof WP_Error && 400 === $r->data['status'] && [] !== preg_grep('/値の型/', $r->data['problems']) && null === omochix_core_glossary_pending($e4) && false === omochix_core_glossary_rest_approved($e4));
}
$r = $sh(['glossary_aliases' => ['別名'], 'glossary_review_days' => 90, 'related_tool_ids' => [7, 8], 'glossary_en' => 'Test']);
check('P1-4 REST accepts values of exactly the stored type', !($r instanceof WP_Error));
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
$r = $sh(['glossary_summary' => null]);
check('P1-4 REST: null (WordPress deletes that meta) is judged as empty, so publishing is refused', $r instanceof WP_Error && [] !== preg_grep('/summary/', $r->data['problems']));
$whole = gl_store('draft');
$r = omochix_core_glossary_guard_rest((object) ['ID' => $whole, 'post_status' => 'publish'], new GL_Req(['meta' => ['glossary_summary' => null]]));
check('P1-4 REST: null over a complete stored term is judged as the deletion it is (not by the stored value)', $r instanceof WP_Error && [] !== preg_grep('/summary/', $r->data['problems']) && !(omochix_core_glossary_guard_rest((object) ['ID' => $whole, 'post_status' => 'publish'], new GL_Req()) instanceof WP_Error));
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
$draft_only = omochix_core_glossary_guard_rest((object) ['ID' => $e4, 'post_status' => 'draft'], new GL_Req(['meta' => ['glossary_aliases' => 'a,b']]));
check('P1-4 a draft save is not judged at all (WordPress and the sanitizer handle its meta)', !($draft_only instanceof WP_Error));
// Reading back after everything is stored.
$rb = $empty();
// WordPress hands every hook of one call the same request object; the guard's notes belong to that call.
$approve = static function ($pid, $creating = false) use ($full_meta, $basics_id) { $GLOBALS['gl_rq'] = new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]); return omochix_core_glossary_guard_rest((object) array_merge(['post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']], $creating ? [] : ['ID' => $pid]), $GLOBALS['gl_rq']); };
$approve($rb);
$GLOBALS['posts'][$rb]->post_status = 'publish'; // The post is saved; its meta was not (WordPress refused or lost it).
do_action('rest_after_insert_omx_glossary', get_post($rb), $GLOBALS['gl_rq'], false);
check('P1-4 read-back: a term this request made live that fails as stored goes back to draft, with the reasons kept', 'draft' === get_post_field('post_status', $rb) && [] !== preg_grep('/sources/', (array) get_transient('omochix_core_glossary_hold_' . $rb)));
$rb2 = $empty();
$approve($rb2);
$GLOBALS['posts'][$rb2]->post_status = 'publish';
foreach ($full_meta as $k => $val) { update_post_meta($rb2, $k, $val); }
wp_set_object_terms($rb2, ['basics'], 'omx_glossary_category');
do_action('rest_after_insert_omx_glossary', get_post($rb2), $GLOBALS['gl_rq'], false);
check('P1-4 read-back: a term that is complete as stored stays published', 'publish' === get_post_field('post_status', $rb2));
apply_filters('rest_request_after_callbacks', null, null, null);
do_action('rest_after_insert_omx_glossary', get_post($live), new GL_Req(), false);
check('P1-4 read-back: a term that was live before (not made live by this request) is never demoted', 'publish' === get_post_field('post_status', $live));
$approve(0, true);
$new = ih_add_post(['post_type' => 'omx_glossary', 'post_status' => 'publish', 'post_name' => 'new-term', 'post_title' => '新しい用語', 'post_content' => gl_valid()['content_html']]);
do_action('rest_after_insert_omx_glossary', get_post($new), $GLOBALS['gl_rq'], true);
check('P1-4 read-back: also for a term created by the request', 'draft' === get_post_field('post_status', $new));
$approve($rb);
apply_filters('rest_request_after_callbacks', null, null, null);
$GLOBALS['posts'][$rb]->post_status = 'publish';
do_action('rest_after_insert_omx_glossary', get_post($rb), new GL_Req(), false);
check('P1-4 read-back: only inside the REST call that was approved (nothing is remembered after it)', 'publish' === get_post_field('post_status', $rb));
$GLOBALS['posts'][$rb]->post_status = 'draft';
// A new term from the block editor: no slug in the request, none stored yet.
$ns = $empty(); $GLOBALS['posts'][$ns]->post_name = '';
$r = omochix_core_glossary_guard_rest((object) ['ID' => $ns, 'post_status' => 'publish', 'post_title' => 'RAG', 'post_content' => gl_valid()['content_html']], new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
check('P1-4 REST: no slug sent or stored: judged with the slug WordPress will make from the title', !($r instanceof WP_Error));
$r = omochix_core_glossary_guard_rest((object) ['ID' => $ns, 'post_status' => 'publish', 'post_title' => 'A', 'post_content' => gl_valid()['content_html']], new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
check('P1-4 REST: a title that gives no usable slug is refused with the reason', $r instanceof WP_Error && [] !== preg_grep('/slug/', $r->data['problems']));
// The object WordPress answers with, and the calls that end before the read-back.
$rb3 = $empty(); $approve($rb3); $GLOBALS['posts'][$rb3]->post_status = 'publish';
$obj = clone get_post($rb3); // WordPress hands the action its own copy of the row.
do_action('rest_after_insert_omx_glossary', $obj, $GLOBALS['gl_rq'], false);
check('P1-4 read-back: the post object WordPress builds its answer from says draft too', 'draft' === $obj->post_status && 'draft' === get_post_field('post_status', $rb3));
$rb4 = $empty(); $approve($rb4); $GLOBALS['posts'][$rb4]->post_status = 'publish'; // Saved; then WordPress stopped at an error (no rest_after_insert).
apply_filters('rest_request_after_callbacks', new WP_Error('rest_meta_database_error', 'x'), null, null);
check('P1-4 read-back also when the call ended with an error after the post was saved', 'draft' === get_post_field('post_status', $rb4) && [] === omochix_core_glossary_rest_approved('list'));
$approve(0, true);
$new2 = ih_add_post(['post_type' => 'omx_glossary', 'post_status' => 'publish', 'post_name' => 'new-term-2', 'post_title' => '新しい用語2', 'post_content' => gl_valid()['content_html']]);
do_action('rest_insert_omx_glossary', get_post($new2), new GL_Req(), true);
apply_filters('rest_request_after_callbacks', new WP_Error('rest_cannot_update', 'x'), null, null);
check('P1-4 the same for a term the call created (its ID is known once the row is saved)', 'draft' === get_post_field('post_status', $new2));
$approve($rb4); // Approved, but the post was never saved as live (WordPress failed before): nothing to do.
apply_filters('rest_request_after_callbacks', null, null, null);
check('P1-4 a call that ends without making the term live changes nothing', 'draft' === get_post_field('post_status', $rb4) && 'publish' === get_post_field('post_status', $live) && 'publish' === get_post_field('post_status', $rb2));
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
check('P1-4 regression: classic save of a complete stored draft still publishes, an incomplete one is still held', 'publish' === omochix_core_glossary_guard_insert_data($data, ['ID' => $id])['post_status'] && 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $e5])['post_status']);
$GLOBALS['posts'][$e5]->post_status = 'future';
do_action('publish_future_post', $e5);
check('P1-4 regression: cron still sets a due incomplete term back to draft', 'draft' === get_post_field('post_status', $e5) && false !== has_action('publish_future_post', 'check_and_publish_future_post'));

// ---------------------------------------------- P2-1: today is the site's date
$ts = static fn($s) => (new DateTimeImmutable($s, new DateTimeZone('UTC')))->getTimestamp();
check('P2-1 without WordPress: Japan time. 23:59:59 JST is still that day', '2026-11-23' === omochix_core_glossary_today($ts('2026-11-23 14:59:59')));
check('P2-1 00:00:00 JST is the new day (UTC is still the day before)', '2026-11-24' === omochix_core_glossary_today($ts('2026-11-23 15:00:00')) && '2026-11-23' === gmdate('Y-m-d', $ts('2026-11-23 15:00:00')));
check('P2-1 08:59:59 JST: the new day (UTC still the day before)', '2026-11-24' === omochix_core_glossary_today($ts('2026-11-23 23:59:59')));
check('P2-1 09:00:00 JST: both agree', '2026-11-24' === omochix_core_glossary_today($ts('2026-11-24 00:00:00')));
check('P2-1 year end: 2027-01-01 00:00 JST', '2027-01-01' === omochix_core_glossary_today($ts('2026-12-31 15:00:00')) && '2026-12-31' === omochix_core_glossary_today($ts('2026-12-31 14:59:59')));
$early = $ts('2026-11-23 16:30:00'); // 01:30 JST on the 24th
$t = gl_valid(); $t['verified_on'] = '2026-11-24'; $t['sources'][0]['checked_on'] = '2026-11-24';
check('P2-1 reproduction: with the UTC date a term checked today (01:30 JST) is "in the future"', gl_has(omochix_core_glossary_validate_term($t, ['today' => gmdate('Y-m-d', $early), 'categories' => ['basics']]), '未来'));
check('P2-1 with the site date the same term passes', [] === omochix_core_glossary_validate_term($t, ['today' => omochix_core_glossary_today($early), 'categories' => ['basics']])['errors']);
$t['verified_on'] = '2026-11-25'; $t['sources'][0]['checked_on'] = '2026-11-25';
check('P2-1 tomorrow (JST) is still refused', gl_has(omochix_core_glossary_validate_term($t, ['today' => omochix_core_glossary_today($early), 'categories' => ['basics']]), '未来'));
check('P2-1 an explicit today still wins (tests, CLI)', gl_has(omochix_core_glossary_validate_term(gl_valid(), ['today' => '2026-11-20', 'categories' => ['basics']]), '未来') && false === omochix_core_glossary_visibility($pub, '2020-01-01')['review']);
check('clock: fixed for this run, so today is 2026-11-24 whatever the real date is', '2026-11-24' === omochix_core_glossary_today() && strtotime('2026-11-24 03:00:00 UTC') === omochix_core_glossary_clock());
$pinned = omochix_core_glossary_clock();
omochix_core_glossary_clock(false);
$jp = static fn() => (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d');
$before = $jp(); $now_today = omochix_core_glossary_today(); $after = $jp(); // Read on both sides: the run may cross midnight.
check('P2-1 the default today is a date and is the Japan date now', 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/D', $now_today) && in_array($now_today, [$before, $after], true) && abs(omochix_core_glossary_clock() - time()) <= 2);
omochix_core_glossary_clock($pinned);
// Years from now the same run must give the same answers: nothing may read the real clock.
omochix_core_glossary_clock(strtotime('2031-03-01 03:00:00 UTC'));
$later = [omochix_core_glossary_today(), omochix_core_glossary_visibility($pub)['review'], [] === omochix_core_glossary_publish_problems(get_post($id))];
omochix_core_glossary_clock($pinned);
check('clock: the rules follow it (in 2031 the same term is past its review; today it is not), and no rule reads the real time', ['2031-03-01', true, true] === $later && false === omochix_core_glossary_visibility($pub)['review'] && 1 === preg_match_all('/\btime\(\)/', $core_src_all = implode('', array_map(static fn($f) => (string) file_get_contents("$PLUGIN/$f"), omochix_core_glossary_files()))) && 0 === preg_match('/\b(?:date|gmdate|wp_date)\(\s*\'[^\']*\'\s*\)|\bcurrent_time\(|\bstrtotime\(\s*\'(?:now|today|tomorrow)/', $core_src_all));
// With WordPress: the timezone setting (wp_timezone) decides.
function wp_timezone() { return new DateTimeZone($GLOBALS['gl_tz'] ?? 'Asia/Tokyo'); }
$GLOBALS['gl_tz'] = 'Asia/Tokyo';
check('P2-1 WordPress set to Asia/Tokyo: 00:00 JST is the new day', '2026-11-24' === omochix_core_glossary_today($ts('2026-11-23 15:00:00')) && '2026-11-23' === omochix_core_glossary_today($ts('2026-11-23 14:59:59')));
$GLOBALS['gl_tz'] = '+09:00';
check('P2-1 WordPress set to a manual offset (UTC+9): the same', '2026-11-24' === omochix_core_glossary_today($ts('2026-11-23 15:00:00')));
$GLOBALS['gl_tz'] = 'UTC';
check('P2-1 WordPress set to UTC: the setting is followed', '2026-11-23' === omochix_core_glossary_today($ts('2026-11-23 15:00:00')));
$GLOBALS['gl_tz'] = 'America/New_York';
check('P2-1 WordPress set to New York: the setting is followed', '2026-11-23' === omochix_core_glossary_today($ts('2026-11-24 04:59:59')) && '2026-11-24' === omochix_core_glossary_today($ts('2026-11-24 05:00:00')));
$GLOBALS['gl_tz'] = 'Asia/Tokyo';
$core_src = '';
foreach (omochix_core_glossary_files() as $f) { $core_src .= file_get_contents("$PLUGIN/$f"); }
check('P2-1 no file takes "today" from UTC any more (gmdate without a time)', 0 === preg_match('/gmdate\(\s*\'Y-m-d\'\s*\)/', $core_src));
check('P2-1 the review date arithmetic is unchanged (verified_on + 180 days, + 30 days grace)', '2027-05-20' === omochix_core_glossary_review_due(['verified_on' => '2026-11-21', 'review_days' => 180]));

// ---------------------------------------------- P2-2: credentials in a source URL
$k = 'sk-' . str_repeat('A1', 15);
$jwt = 'eyJ' . str_repeat('a', 12) . '.eyJ' . str_repeat('b', 12) . '.' . str_repeat('c', 12);
$url_bad = [
    'hyphenated key access-token' => 'https://docs.example.org/x?access-token=abc',
    'camel case accessToken' => 'https://docs.example.org/x?accessToken=abc',
    'X-Api-Key' => 'https://docs.example.org/x?X-Api-Key=abc',
    'client_secret' => 'https://docs.example.org/x?id=1&client_secret=abc',
    'refresh-token' => 'https://docs.example.org/x?refresh-token=abc',
    'nested a[token]' => 'https://docs.example.org/x?a[token]=abc',
    'nested auth[access_token]' => 'https://docs.example.org/x?auth[access_token]=abc',
    'nested the other way token[0]' => 'https://docs.example.org/x?token[0]=abc',
    'nested password[value]' => 'https://docs.example.org/x?password[value]=abc',
    'encoded brackets a%5Btoken%5D' => 'https://docs.example.org/x?a%5Btoken%5D=abc',
    'encoded key access%5Ftoken' => 'https://docs.example.org/x?access%5Ftoken=abc',
    'double-encoded key access%255Ftoken' => 'https://docs.example.org/x?access%255Ftoken=abc',
    'encoded separator %26token%3D' => 'https://docs.example.org/x?a=1%26token%3Dabc',
    'double-encoded separator %2526token%253D' => 'https://docs.example.org/x?a=1%2526token%253Dabc',
    'fragment #access_token=' => 'https://docs.example.org/cb#access_token=abc&token_type=bearer',
    'fragment route #/x?token=' => 'https://docs.example.org/app#/x?token=abc',
    'path parameter ;jsessionid=' => 'https://docs.example.org/x;jsessionid=ABC123',
    'signed URL X-Amz-Signature' => 'https://docs.example.org/f.pdf?X-Amz-Algorithm=AWS4&X-Amz-Signature=abcdef',
    'signed URL X-Goog-Signature' => 'https://docs.example.org/f.pdf?X-Goog-Signature=abcdef',
    'password' => 'https://docs.example.org/x?user=a&password=b',
    'API key value in the query under a harmless name' => 'https://docs.example.org/x?q=' . $k,
    'API key value in the path' => 'https://docs.example.org/' . $k . '/x',
    'API key value in the fragment' => 'https://docs.example.org/x#' . $k,
    'AWS key in the path' => 'https://docs.example.org/AKIA' . 'ABCDEFGHIJKLMNOP/x',
    'JWT in the fragment' => 'https://docs.example.org/x#' . $jwt,
    'JWT in the query' => 'https://docs.example.org/x?v=' . $jwt,
    'URL-encoded API key value' => 'https://docs.example.org/x?q=sk%2D' . str_repeat('A1', 15),
    'GitHub token in the path' => 'https://docs.example.org/ghp_' . str_repeat('a1', 16),
];
foreach ($url_bad as $name => $u) {
    $why = omochix_core_glossary_url_problem($u);
    check("P2-2 refused: $name", '' !== $why && (false !== strpos($why, '認証情報') || false !== strpos($why, '秘密情報')), $why);
}
$url_ok = [
    'plain' => 'https://docs.example.org/spec',
    'arXiv' => 'https://arxiv.org/abs/1706.03762',
    'language and paging' => 'https://docs.example.org/guide?hl=ja&page=2',
    'campaign parameters' => 'https://www.example.org/news/2026/model?utm_source=x&utm_medium=y',
    'id and version' => 'https://docs.example.org/item?id=123&v=2',
    'anchor' => 'https://docs.example.org/guide#section-3',
    'anchor named api-key' => 'https://docs.example.org/reference#api-key',
    'anchor named access-token' => 'https://docs.example.org/reference#access-token',
    'anchor named authentication' => 'https://docs.example.org/reference#authentication',
    'path about tokens and keys' => 'https://docs.example.org/docs/api-keys/tokens/',
    'path about secrets' => 'https://docs.example.org/guides/secrets-management',
    'search word token as a value' => 'https://docs.example.org/search?q=token',
    'value password' => 'https://docs.example.org/search?topic=password-reset',
    'tokenizer page' => 'https://docs.example.org/tokenizer?lang=ja',
    'encoded Japanese path' => 'https://ja.wikipedia.org/wiki/%E4%BA%BA%E5%B7%A5%E7%9F%A5%E8%83%BD',
    'encoded percent sign' => 'https://docs.example.org/a%2520b?x=1',
    'DOI' => 'https://doi.org/10.48550/arXiv.1706.03762',
    'path with task-force (sk- inside a word)' => 'https://www.example.org/reports/task-force-on-artificial-intelligence-governance-2026',
    'port 443' => 'https://docs.example.org:443/x?tab=readme',
    'keyword parameter' => 'https://docs.example.org/x?keyword=llm&category=ai&sort=asc&offset=20',
    'monkey and author (key/auth inside a word)' => 'https://docs.example.org/x?monkey=1&author=2&design=3&passage=4',
];
foreach ($url_ok as $name => $u) {
    check("P2-2 accepted: $name", '' === omochix_core_glossary_url_problem($u), omochix_core_glossary_url_problem($u));
}
check('P2-2 the earlier refusals still hold (token, API_KEY, code, sig)', '' !== omochix_core_glossary_url_problem('https://docs.example.org/x?token=abc') && '' !== omochix_core_glossary_url_problem('https://docs.example.org/x?API_KEY=abc') && '' !== omochix_core_glossary_url_problem('https://docs.example.org/x?code=abc') && '' !== omochix_core_glossary_url_problem('https://docs.example.org/x?sig=abc'));
$all_msgs = '';
foreach ($url_bad as $u) { $all_msgs .= omochix_core_glossary_url_problem($u) . "\n"; }
check('P2-2 no message repeats a value: no key, no JWT, no "abc"', false === strpos($all_msgs, $k) && false === strpos($all_msgs, $jwt) && false === strpos($all_msgs, 'A1A1') && 0 === preg_match('/=\s*abc|abcdef|ABC123/', $all_msgs));
$t = gl_valid(); $t['sources'][0]['url'] = 'https://docs.example.org/x?q=' . $k;
$res = gl_check($t);
check('P2-2 a term whose source URL carries a key: refused, and the errors never contain the key', gl_has($res, 'sources[0].url') && false === strpos(implode("\n", $res['errors']), $k));
$t = gl_valid(); $t['content_html'] .= '<p><a href="https://docs.example.org/x?access-token=topsecretvalue">a</a></p>';
$res = gl_check($t);
check('P2-2 a link in the text with a credential: refused, the link is not repeated in the message', gl_has($res, 'リンク（docs.example.org）：') && false === strpos(implode("\n", $res['errors']), 'topsecretvalue'));
$t = gl_valid(); $t['content_html'] .= '<p><a href="http://insecure.example.org/page">a</a></p>';
$res = gl_check($t);
check('P2-2 an ordinary bad link is named by its host only (the editor can find it; the address is never repeated)', gl_has($res, 'リンク（insecure.example.org）：') && false === strpos(implode("\n", $res['errors']), '/page'));
$t = gl_valid(); $t['content_html'] .= '<p><a href="https://user:S3cr3tV4lu3XyZ@docs.example.org/">a</a><a href="https://docs.example.org:8443/?x=S3cr3tV4lu3XyZ">b</a></p>';
$res = gl_check($t);
check('P2-2 a password in a link (user:pass@) or any value in its query is never echoed', gl_has($res, 'パスワード') && gl_has($res, 'ポート') && false === strpos(implode("\n", $res['errors']), 'S3cr3t'));
mt_srand(7);
$b64 = static function ($n) { $c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'; $o = ''; for ($i = 0; $i < $n; $i++) { $o .= $c[mt_rand(0, 63)]; } return $o; };
$missed = 0;
for ($i = 0; $i < 3000; $i++) { foreach (['sk-ant-api03-' . $b64(93) . 'AA', 'sk-proj-' . $b64(156), 'sk-svcacct-' . $b64(156)] as $key) { if (!omochix_core_glossary_secret_findings("x $key y")) { $missed++; } } }
check('P2-2 realistic keys with - and _ inside (sk-ant-api03-…, sk-proj-…, sk-svcacct-…): 9,000 random ones, none missed', 0 === $missed, (string) $missed);
check('P2-2 a key written as hyphenated short groups first is still seen', [] !== omochix_core_glossary_secret_findings('sk-' . 'proj-AbCdEfGh-IjKlMnOpQr_StUvWx-' . 'Yz0123456789AbCdEfGhIjKl'));
$t0 = microtime(true); $slow = omochix_core_glossary_secret_findings(str_repeat('eyJaaaaaaaaaa-', 14000) . '.'); $slow2 = omochix_core_glossary_secret_findings(str_repeat('sk-abc-', 28000)); $dt = microtime(true) - $t0;
check('P2-2 the scan stays fast on content built to slow it down (near the size limit)', $dt < 1.0 && [] === $slow && [] === $slow2, (string) $dt);
// Making the pattern engine fail on purpose depends on the PHP build (JIT), so this is checked in the source.
$vs = (string) file_get_contents("$PLUGIN/includes/web2/glossary-validate.php");
$sf = substr($vs, strpos($vs, 'function omochix_core_glossary_secret_findings('), 2600);
check('P2-2 a scan that could not run is a finding, not "nothing found"', 1 === preg_match('/\$hit = preg_match\( \$re, \(string\) \$text \);\s+if \( \$hit \) \{\s+\$found\[\] = \$name;\s+\} elseif \( false === \$hit \) \{\s+\$found\[\] = \'検査できなかった文字列\';/', $sf));
$t = gl_valid(); $t['content_html'] = "<p>\xff" . str_repeat('あ', 9000) . '</p>';
check('P1-3 content that is not valid UTF-8 is refused (its length cannot be counted)', gl_has(gl_check($t), 'UTF-8'));
$t = gl_valid(); $t['aliases'] = [$k, $k];
$res = gl_check($t);
check('P2-2 a message that would quote a key (duplicate alias) is replaced', false === strpos(implode("\n", $res['errors']), $k) && gl_has($res, '内容は表示しません'));
$t = gl_valid(); $t['related']['tools'] = ['AKIA' . 'ABCDEFGHIJKLMNOP'];
$res = gl_check($t);
check('P2-2 the same for a key written where a slug belongs', false === strpos(implode("\n", $res['errors']), 'AKIA' . 'ABCDEFGHIJKLMNOP') && gl_has($res, 'related.tools'));
check('P2-2 a message without a credential is not touched', 'slug：予約語です' === omochix_core_glossary_redact('slug：予約語です'));
$s = omochix_core_glossary_sanitize_meta([['url' => 'https://docs.example.org/cb#access_token=abc', 'title' => 't', 'publisher' => 'p', 'kind' => 'docs', 'checked_on' => '2026-11-20'], ['url' => 'https://docs.example.org/reference#access-token', 'title' => 't', 'publisher' => 'p', 'kind' => 'docs', 'checked_on' => '2026-11-20']], 'glossary_sources');
check('P2-2 meta: a source URL with a credential is never stored, an anchor is', 1 === count($s) && 'https://docs.example.org/reference#access-token' === $s[0]['url']);
// After the independent review: what a message may show, false alarms on real sources, and misses.
$echo = [
    'https://docs.example.org/?apikey%3DSuperSecretValue123', 'https://docs.example.org/?access_token:SuperSecretValue123', 'https://docs.example.org/?secretSuperSecretValue123=1',
    'https://docs.example.org/x;sessionid-SuperSecretValue123', 'https://docs.example.org/?password%3DSuperSecretValue123', 'https://docs.example.org/?a[SuperSecretValue123][token]=1',
];
$msgs = implode("\n", array_map('omochix_core_glossary_url_problem', $echo));
check('P2-2 a "name" that holds the value is never shown: the message names the listed word instead', false === stripos($msgs, 'SuperSecret') && false === strpos($msgs, 'Value123') && false !== strpos(omochix_core_glossary_url_problem($echo[0]), '（apikey）') && false !== strpos(omochix_core_glossary_url_problem($echo[1]), '（accesstoken）') && false !== strpos(omochix_core_glossary_url_problem($echo[4]), '（password）') && false !== strpos(omochix_core_glossary_url_problem($echo[5]), '（token）'));
check('P2-2 a name that is exactly a listed word is shown as written; a longer one by its listed part', false !== strpos(omochix_core_glossary_url_problem('https://docs.example.org/x?API_KEY=abc'), '（API_KEY）') && false !== strpos(omochix_core_glossary_url_problem('https://docs.example.org/x?token=abc'), '（token）') && false !== strpos(omochix_core_glossary_url_problem('https://docs.example.org/x?access-token=abc'), '（token）') && false !== strpos(omochix_core_glossary_url_problem('https://docs.example.org/x?X-Api-Key=abc'), '（apikey）'));
foreach ([
    'a slug that starts with sk- (SK hynix newsroom)' => 'https://news.skhynix.com/sk-hynix-begins-volume-production-of-hbm3e/',
    'a slug that starts with sk- (SK telecom)' => 'https://www.sktelecom.com/en/press/sk-telecom-unveils-new-ai-data-center-strategy-2026',
    'a query word that starts with sk-' => 'https://docs.example.org/search?q=sk-learn-pipeline-tutorial-for-beginners',
    'secretary' => 'https://www.example.go.jp/list?secretary=cabinet',
    'credentials=include (a fetch option in documentation)' => 'https://docs.example.org/api?credentials=include',
    'sid (a section or shop ID)' => 'https://docs.example.org/x?sid=12',
    'design, monkey, passage, authors' => 'https://docs.example.org/x?design=1&monkey=2&passage=3&authors=4',
] as $name => $u) {
    check("P2-2 accepted (was a false alarm): $name", '' === omochix_core_glossary_url_problem($u), omochix_core_glossary_url_problem($u));
}
$t = gl_valid(); $t['content_html'] .= '<p><a href="https://news.skhynix.com/sk-hynix-begins-volume-production-of-hbm3e/">SK hynix</a></p>';
check('P2-2 the same slug in a link of the text is no "API key" either', [] === gl_check($t)['errors']);
foreach ([
    'reset_token' => 'https://docs.example.org/x?reset_token=abc', 'csrf-token' => 'https://docs.example.org/x?csrf-token=abc', 'x_token' => 'https://docs.example.org/x?x_token=abc', 'user.token' => 'https://docs.example.org/x?user.token=abc',
    'otp' => 'https://docs.example.org/x?otp=123456', 'hmac' => 'https://docs.example.org/x?hmac=abc', 'pw' => 'https://docs.example.org/x?pw=abc', 'api_key_v2' => 'https://docs.example.org/x?api_key_v2=abc',
    'a key after an underscore (_sk-…)' => 'https://docs.example.org/x?q=_sk-' . str_repeat('A1', 15), 'a project key (sk-proj-…)' => 'https://docs.example.org/x?q=sk-proj-' . str_repeat('aB3', 12),
    'a GitLab token' => 'https://docs.example.org/x/glpat-' . str_repeat('aB3', 8), 'a Hugging Face token' => 'https://docs.example.org/x#hf_' . str_repeat('aB3', 11),
    // Made-up values, put together here so that no scanner takes this file for a real webhook.
    'a Slack webhook' => 'https://hooks.slack' . '.com/services/' . 'T00000000/B00000000/' . str_repeat('X', 24), 'a Discord webhook' => 'https://discord' . '.com/api/webhooks/' . str_repeat('1', 18) . '/' . str_repeat('ab', 8),
] as $name => $u) {
    check("P2-2 refused (was missed): $name", '' !== omochix_core_glossary_url_problem($u));
}
check('P2-2 known limits, not changed: a bare key=, code= or session= is refused (stricter than needed); a secret as a path segment or inside a JSON value is not seen', '' !== omochix_core_glossary_url_problem('https://docs.example.org/x?code=123') && '' !== omochix_core_glossary_url_problem('https://docs.example.org/x?session=2024-spring') && '' === omochix_core_glossary_url_problem('https://docs.example.org/token/0123456789abcdef') && '' === omochix_core_glossary_url_problem('https://docs.example.org/x?download_key=abc'));
// Content the rules cannot read is refused, never passed.
$huge = '<!-- wp:paragraph {' . str_repeat('} ', 200000) . '--><script>alert(1)</script><p>x</p>';
check('P1-3 content too large to read is refused by its size (a pattern that gives up must not mean "nothing found")', [] !== preg_grep('/大きすぎます/', omochix_core_glossary_html_problems($huge)) && $huge === omochix_core_glossary_normalize_content($huge));
$limit = ini_get('pcre.backtrack_limit'); ini_set('pcre.backtrack_limit', '100');
$small = '<!-- wp:paragraph {' . str_repeat('} ', 2000) . '--><script>alert(1)</script><p>x</p>';
$np = omochix_core_glossary_normalize_content($small); $hp = omochix_core_glossary_html_problems($small);
ini_set('pcre.backtrack_limit', $limit);
check('P1-3 when the pattern engine gives up, nothing is taken off and the content is refused', $small === $np && [] !== $hp, implode(' / ', $hp));
$vsrc = (string) file_get_contents("$PLUGIN/includes/web2/glossary-validate.php");
$fn = substr($vsrc, strpos($vsrc, 'function omochix_core_glossary_html_problems('), strpos($vsrc, 'function omochix_core_glossary_text(') - strpos($vsrc, 'function omochix_core_glossary_html_problems('));
check('P1-3 every pattern that reads the whole content reports when it could not run (6 checks, 1 message)', 6 === preg_match_all('/if \( \$ran\( preg_match(?:_all)?\( /', $fn) && 6 === preg_match_all('/preg_match(?:_all)?\( [^\n]*\$html[,) ]/', $fn) && 1 === substr_count($fn, "\$out[] = '本文を検査できませんでした"));
$t = gl_valid(); $t['content_html'] = '<p>' . str_repeat('あ', 500) . '</p>' . str_repeat('<p></p>', 30000);
check('P1-3 a term is refused for raw content over the size limit even when its text is short', gl_has(gl_check($t), '大きすぎます'));
$big_ok = "<!-- wp:paragraph -->\n<p>" . str_repeat('この段落はテスト用の文章で、意味のある説明ではありません。', 60) . "</p>\n<!-- /wp:paragraph -->";
$t0 = microtime(true); $near = str_repeat($big_ok, 10); $pr = omochix_core_glossary_html_problems($near); $dt = microtime(true) - $t0;
check('P1-3 ordinary content near the size limit is read quickly and passes the HTML rules', [] === $pr && $dt < 2.0 && strlen($near) > 50000 && strlen($near) < omochix_core_glossary_rules()['content_bytes_max']);
$full = "<!-- wp:paragraph -->\n<p>" . str_repeat('あ', 6000) . "</p>\n<!-- /wp:paragraph -->" . str_repeat("\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">見出し</h2>\n<!-- /wp:heading -->", 100);
$over_limit = '<p>' . str_repeat('あ', 500) . '</p>' . str_repeat('<p></p>', 8400); // 60,307 bytes
check('size limit: 60,000 bytes of raw content; one step over is refused unread', strlen($over_limit) > 60000 && strlen($over_limit) < 61000 && [] !== preg_grep('/大きすぎます/', omochix_core_glossary_html_problems($over_limit)) && [] === preg_grep('/大きすぎます/', omochix_core_glossary_html_problems(substr($over_limit, 0, 59990))));
// The answer for a term is kept for the request; it must never be an old answer.
$mm = gl_store('publish');
add_filter('omochix_core_glossary_public', $open);
$v1 = omochix_core_glossary_visibility($mm);
update_post_meta($mm, 'glossary_sources', []);
$v2 = omochix_core_glossary_visibility($mm);
update_post_meta($mm, 'glossary_sources', gl_valid()['sources']);
$GLOBALS['posts'][$mm]->post_status = 'draft'; $v3 = omochix_core_glossary_visibility($mm);
$GLOBALS['posts'][$mm]->post_status = 'publish'; $GLOBALS['posts'][$mm]->post_password = 'x'; $v4 = omochix_core_glossary_visibility($mm);
$GLOBALS['posts'][$mm]->post_password = ''; $v5 = omochix_core_glossary_visibility($mm);
$v6 = omochix_core_glossary_visibility($mm, '2027-06-20');
$GLOBALS['posts'][$mm]->post_content .= '<script>x</script>'; $v7 = omochix_core_glossary_visibility($mm);
remove_filter('omochix_core_glossary_public', $open);
$v8 = omochix_core_glossary_visibility($mm);
check('cache: the kept answer follows every change (meta, status, password, date, content, the gate)', [true, false, false, false, true, true, false, false] === [$v1['visible'], $v2['visible'], $v3['visible'], $v4['visible'], $v5['visible'], $v6['visible'], $v7['visible'], $v8['visible']] && true === $v5['indexable'] && false === $v6['indexable']);
$GLOBALS['posts'][$mm]->post_content = gl_valid()['content_html'];
// Content that takes long to check: the first answer costs a full check, the next 300 must not.
$GLOBALS['posts'][$mm]->post_content = '<p>' . str_repeat('あ', 500) . '</p>' . str_repeat('<p><a href="https://docs.example.org/a">x</a></p>', 1000);
add_filter('omochix_core_glossary_public', $open);
$first = omochix_core_glossary_visibility($mm);
// 15 checks that cannot be answered from the cache (15 different dates), against 300 that can.
$t0 = microtime(true); for ($i = 1; $i <= 15; $i++) { omochix_core_glossary_visibility($mm, sprintf('2026-12-%02d', $i)); } $dt_fresh15 = microtime(true) - $t0;
$t0 = microtime(true); for ($i = 0; $i < 300; $i++) { $again = omochix_core_glossary_visibility($mm); } $dt_cached = microtime(true) - $t0;
remove_filter('omochix_core_glossary_public', $open);
check('cache: 300 more questions about an unchanged term cost less than 150 full checks (they would cost 300)', true === $first['visible'] && $first === $again && $dt_cached < 10 * $dt_fresh15, sprintf('15 fresh %.4f, 300 cached %.4f', $dt_fresh15, $dt_cached));
$GLOBALS['posts'][$mm]->post_content = gl_valid()['content_html'];
check('size limit: a term with the longest allowed text (6,000 characters) and 100 block headings fits', strlen($full) < omochix_core_glossary_rules()['content_bytes_max'] && [] === omochix_core_glossary_html_problems($full), (string) strlen($full));
// Ordinary content, as the block editor saves it, must not be refused by the size limit (or by anything else).
function gls_sent($i) { $s = ['大規模言語モデルは、大量のテキストから次に来る語を予測するように学習したモデルです。', 'プロンプトの書き方によって、同じモデルでも回答の質や形式が大きく変わります。', '出力を業務で使う前に、出典と日付を確認し、誤りがないかを人が確かめる必要があります。', 'API の料金は入力と出力のトークン数で決まることが多く、長い文書ほど費用がかかります。', 'Retrieval-Augmented Generation（RAG）は、検索した文書を根拠として回答を作る方法です。']; return $s[$i % 5]; }
function gls_text($chars, $seed = 0) { $t = ''; for ($i = $seed; mb_strlen($t) < $chars; $i++) { $t .= gls_sent($i); } return mb_substr($t, 0, $chars); }
function gls_p($inner) { return "<!-- wp:paragraph -->\n<p>$inner</p>\n<!-- /wp:paragraph -->\n\n"; }
function gls_h($text, $lv = 2) { return '<!-- wp:heading' . (2 === $lv ? '' : ' {"level":' . $lv . '}') . " -->\n<h$lv class=\"wp-block-heading\">$text</h$lv>\n<!-- /wp:heading -->\n\n"; }
function gls_links($chars, $links, $seed = 0) { $u = ['https://platform.openai.com/docs/guides/text-generation', 'https://docs.anthropic.com/en/docs/build-with-claude/prompt-engineering/overview', 'https://ai.google.dev/gemini-api/docs/models', 'https://arxiv.org/abs/2005.11401', 'https://www.nist.gov/itl/ai-risk-management-framework', 'https://huggingface.co/docs/transformers/main/en/index']; $per = intdiv($chars, $links); $o = ''; for ($n = 0; $n < $links; $n++) { $o .= gls_text($per, $seed + $n) . '（<a href="' . $u[$n % 6] . '?ref=' . $n . '">資料' . ($n + 1) . '</a>）'; } return gls_p($o); }
function gls_list($items, $each, $ordered = false, $seed = 0) { $tag = $ordered ? 'ol' : 'ul'; $o = '<!-- wp:list' . ($ordered ? ' {"ordered":true}' : '') . " -->\n<$tag class=\"wp-block-list\">"; for ($i = 0; $i < $items; $i++) { $o .= "<!-- wp:list-item -->\n<li>" . gls_text($each, $seed + $i) . "</li>\n<!-- /wp:list-item -->"; } return $o . "</$tag>\n<!-- /wp:list -->\n\n"; }
function gls_table($rows, $cols, $each) { $o = "<!-- wp:table {\"hasFixedLayout\":true} -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\"><thead><tr>"; for ($c = 0; $c < $cols; $c++) { $o .= '<th>項目' . ($c + 1) . '</th>'; } $o .= '</tr></thead><tbody>'; for ($r = 0; $r < $rows; $r++) { $o .= '<tr>'; for ($c = 0; $c < $cols; $c++) { $o .= '<td>' . gls_text($each, $r + $c) . '</td>'; } $o .= '</tr>'; } return $o . "</tbody></table></figure>\n<!-- /wp:table -->\n\n"; }
$gls_code = "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>curl https://api.example.com/v1/messages \\\n  -H &quot;content-type: application/json&quot;</code></pre>\n<!-- /wp:code -->\n\n";
$gls_quote = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">" . gls_p(gls_sent(2)) . "</blockquote>\n<!-- /wp:quote -->\n\n";
$gls = ['long article: 6,000 characters under 24 headings' => '', 'many links: 2,000 characters with 80 links' => gls_h('参考') . gls_links(1980, 80), 'lists: 5 lists of 40 items' => '', 'tables: 3 tables of 20 rows x 4 columns' => '', 'mixed: 60 links, 3 lists, 2 tables, code and a quote' => gls_h('概要') . gls_links(1500, 30) . gls_h('仕組み') . gls_p(gls_text(560, 1)) . gls_list(20, 30) . gls_h('比較') . gls_table(15, 4, 15) . gls_h('使い方') . gls_list(15, 30, true, 3) . $gls_code . $gls_quote . gls_h('注意点') . gls_links(800, 30, 2) . gls_table(10, 3, 15) . gls_h('まとめ') . gls_list(8, 25, false, 4), 'dense list: 540 items of 10 characters' => gls_list(540, 10)];
for ($s = 0; $s < 12; $s++) { $gls['long article: 6,000 characters under 24 headings'] .= gls_h('説明' . ($s + 1)) . gls_h('補足', 3) . gls_p(gls_text(490, $s)); }
for ($l = 0; $l < 5; $l++) { $gls['lists: 5 lists of 40 items'] .= gls_h('一覧' . ($l + 1)) . gls_list(40, 28, 1 === $l % 2, $l); }
for ($t = 0; $t < 3; $t++) { $gls['tables: 3 tables of 20 rows x 4 columns'] .= gls_h('比較表' . ($t + 1)) . gls_table(20, 4, 20); }
foreach ($gls as $name => $html) {
    $tt = gl_valid(); $tt['content_html'] = $html; $res = gl_check($tt); $chars = omochix_core_glossary_len(omochix_core_glossary_text($html));
    check("size limit: ordinary content is accepted whole ($name)", [] === $res['errors'] && $chars <= 6000 && strlen($html) < omochix_core_glossary_rules()['content_bytes_max'], sprintf('%d bytes, %d characters; %s', strlen($html), $chars, implode(' / ', $res['errors'])));
}
check('size limit: the realistic cases above stay under half the limit, except the dense list', max(array_map('strlen', array_diff_key($gls, ['dense list: 540 items of 10 characters' => 1]))) < 30000 && strlen($gls['dense list: 540 items of 10 characters']) < 50000);
check('P3 (not changed here): a public name that resolves to an internal address is still accepted; no DNS lookup is made', '' === omochix_core_glossary_url_problem('https://127.0.0.1.nip.io/x') && 0 === preg_match('/gethostby|dns_get_record|checkdnsrr|wp_remote|curl_/', $core_src));

// ================================== F1-F4: hardening after the final review of PR #83
// ---------------------------------------------- F1: a scheduled term is not "live"
$sch = gl_store('future');
check('F1 a scheduled complete term may be published now', 'publish' === omochix_core_glossary_guard_insert_data($data, ['ID' => $sch])['post_status']);
update_post_meta($sch, 'glossary_sources', []); // Emptied while it waits (a draft-like save is always allowed).
check('F1 reproduction: scheduled, then made incomplete, then "publish now": held (it was never public)', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $sch])['post_status']);
check('F1 the reasons are kept', [] !== preg_grep('/sources/', (array) get_transient('omochix_core_glossary_hold_' . $sch)));
check('F1 the same when it is saved as scheduled again', 'draft' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_status' => 'future']), ['ID' => $sch])['post_status']);
$r = omochix_core_glossary_guard_rest((object) ['ID' => $sch, 'post_status' => 'publish'], new GL_Req());
check('F1 REST: publishing a scheduled incomplete term is refused', $r instanceof WP_Error && [] !== preg_grep('/sources/', $r->data['problems']));
$r = omochix_core_glossary_guard_rest((object) ['ID' => $sch, 'post_status' => 'publish'], new GL_Req(['meta' => ['glossary_sources' => gl_valid()['sources']]]));
check('F1 REST: with the missing meta in the request it is allowed, and noted for the read-back', !($r instanceof WP_Error) && in_array($sch, omochix_core_glossary_rest_approved('list'), true));
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
foreach (['category removed' => static function ($p) { wp_set_object_terms($p, [], 'omx_glossary_category'); }, 'summary emptied' => static function ($p) { update_post_meta($p, 'glossary_summary', ''); }, 'check date in the future' => static function ($p) { update_post_meta($p, 'glossary_verified_on', '2099-01-01'); }, 'script in the content' => static function ($p) { $GLOBALS['posts'][$p]->post_content .= '<script>x</script>'; }] as $name => $break) {
    $p = gl_store('future'); $break($p);
    $d2 = array_merge($data, ['post_content' => wp_slash($GLOBALS['posts'][$p]->post_content)]);
    check("F1 scheduled term, $name: publishing now is held", 'draft' === omochix_core_glossary_guard_insert_data($d2, ['ID' => $p])['post_status']);
}
check('F1 a published term is still never demoted (only "publish" counts as live)', 'publish' === omochix_core_glossary_guard_insert_data($data, ['ID' => $live])['post_status'] && 'future' === omochix_core_glossary_guard_insert_data(array_merge($data, ['post_status' => 'future']), ['ID' => $live])['post_status'] && true === omochix_core_glossary_is_live($live) && false === omochix_core_glossary_is_live($sch) && false === omochix_core_glossary_is_live(0));
$GLOBALS['posts'][$sch]->post_status = 'future';
do_action('publish_future_post', $sch);
check('F1 cron is unchanged: the due incomplete term goes back to draft', 'draft' === get_post_field('post_status', $sch));

// A REST save that sends no status (review of this change, R1): a scheduled term stays scheduled, or is
// published at once when its date has passed. WordPress decides that while saving, so the guard must
// judge by the stored status when none is sent.
$s1 = gl_store('future');
$r = omochix_core_glossary_guard_rest((object) ['ID' => $s1, 'post_date' => '2020-01-01 00:00:00'], $q1 = new GL_Req(['meta' => ['glossary_sources' => [], 'glossary_summary' => '']]));
check('F1 REST without a status on a scheduled term, meta emptied in the same request: refused (it would be published on the old meta)', $r instanceof WP_Error && 400 === $r->data['status'] && [] !== preg_grep('/sources/', $r->data['problems']) && null === omochix_core_glossary_pending($s1) && [] === omochix_core_glossary_rest_approved('list'));
$r = omochix_core_glossary_guard_rest((object) ['ID' => $s1], new GL_Req(['glossary-categories' => []]));
check('F1 the same when the request only takes the category away', $r instanceof WP_Error && [] !== preg_grep('/category/', $r->data['problems']));
$r = omochix_core_glossary_guard_rest((object) ['ID' => $s1, 'post_title' => '新しい見出し'], $q2 = new GL_Req(['meta' => ['glossary_en' => 'Edited']]));
check('F1 a complete scheduled term can still be edited without a status: judged, handed over and noted for the read-back', !($r instanceof WP_Error) && [$s1] === omochix_core_glossary_rest_approved('list', false, omochix_core_glossary_rest_owner($q2)));
check('F1 ...and the insert guard, which sees "future" or "publish" from WordPress, agrees', 'publish' === omochix_core_glossary_guard_insert_data($data, ['ID' => $s1])['post_status']);
update_post_meta($s1, 'glossary_summary', ''); $GLOBALS['posts'][$s1]->post_status = 'publish'; // What a spoiled save would leave.
do_action('rest_after_insert_omx_glossary', get_post($s1), $q2, false);
check('F1 ...and the read-back demotes it when what was stored fails', 'draft' === get_post_field('post_status', $s1));
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
$dr = gl_store('draft', ['meta' => ['glossary_sources' => []]]);
check('F1 a draft, a pending or a private term saved without a status is not judged (it does not become public)', !(omochix_core_glossary_guard_rest((object) ['ID' => $dr], new GL_Req(['meta' => ['glossary_summary' => '']])) instanceof WP_Error) && [] === omochix_core_glossary_rest_approved('list'));
check('F1 a published term saved without a status is not judged either (live: never demoted)', !(omochix_core_glossary_guard_rest((object) ['ID' => $live], new GL_Req(['meta' => ['glossary_summary' => '']])) instanceof WP_Error) && [] === omochix_core_glossary_rest_approved('list'));
check('F1 a new post without a status (WordPress makes it a draft) is not judged', !(omochix_core_glossary_guard_rest((object) ['post_title' => 'x'], new GL_Req()) instanceof WP_Error));

// ---------------------------------------------- F2: no address for a hidden term
$hid_pub = gl_store('publish', ['meta' => ['glossary_sources' => []]]); $hid_draft = gl_store('draft'); $vis = gl_store('publish');
$link = static fn($p) => omochix_core_glossary_post_link('https://example.test/glossary/test-term/', get_post($p));
$GLOBALS['caps'] = [];
check('F2 closed: no term has an address for a visitor (WordPress would answer /?post_type=omx_glossary&p=ID from wp-trackback.php)', '' === $link($vis) && '' === $link($hid_pub) && '' === $link($hid_draft));
add_filter('omochix_core_glossary_public', $open);
check('F2 open: a hidden published term and a draft have no address for a visitor', '' === $link($hid_pub) && '' === $link($hid_draft));
check('F2 open: a visible term keeps its address', 'https://example.test/glossary/test-term/' === $link($vis));
$GLOBALS['caps'] = ['edit_post' => true];
check('F2 an editor keeps every address (admin links, previews)', 'https://example.test/glossary/test-term/' === $link($hid_pub) && 'https://example.test/glossary/test-term/' === $link($hid_draft));
$GLOBALS['caps'] = [];
$other = ih_add_post(['post_type' => 'post', 'post_status' => 'draft', 'post_name' => 'x']);
check('F2 other post types are untouched, also drafts; a missing post is untouched', 'u' === omochix_core_glossary_post_link('u', get_post($other)) && 'u' === omochix_core_glossary_post_link('u', null) && 'u' === omochix_core_glossary_post_link('u', (object) ['post_type' => 'ai_tool']));
$rv_h = ih_add_post(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $hid_pub, 'post_name' => $hid_pub . '-revision-v1']);
$rv_v = ih_add_post(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $vis, 'post_name' => $vis . '-revision-v1']);
$rv_o = ih_add_post(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $other, 'post_name' => $other . '-revision-v1']);
add_filter('omochix_core_glossary_public', $open);
check('F2 a revision of a hidden term has no address either; a revision of a visible term or of another post keeps WordPress\'s', '' === omochix_core_glossary_post_link('u', get_post($rv_h)) && 'u' === omochix_core_glossary_post_link('u', get_post($rv_v)) && 'u' === omochix_core_glossary_post_link('u', get_post($rv_o)) && true === omochix_core_glossary_is_hidden_from_user($rv_h) && false === omochix_core_glossary_is_hidden_from_user($rv_o) && false === omochix_core_glossary_is_hidden_from_user(0));
remove_filter('omochix_core_glossary_public', $open);
check('F2 the filter is on post_type_link (terms) and post_link (their revisions), last', 9999 === has_filter('post_type_link', 'omochix_core_glossary_post_link') && 9999 === has_filter('post_link', 'omochix_core_glossary_post_link'));
remove_filter('omochix_core_glossary_public', $open);

// ---------------------------------------------- F2: the other doors (review of this change, R2 R5 R6 R7)
if (!class_exists('IXR_Error')) { class IXR_Error { public $code, $message; function __construct($c, $m) { $this->code = $c; $this->message = $m; } } }
if (!function_exists('url_to_postid')) { function url_to_postid($url) { if (!is_string($url)) { return 0; } foreach ($GLOBALS['posts'] as $p) { if ('omx_glossary' === $p->post_type && 'publish' === $p->post_status && false !== strpos((string) $url, '/glossary/' . $p->post_name . '-' . $p->ID . '/')) { return $p->ID; } } return 0; } }
if (!function_exists('count_user_posts')) { function count_user_posts($uid, $types = 'post', $public = false) { $n = 0; foreach ($GLOBALS['posts'] as $p) { if (in_array($p->post_type, (array) $types, true) && 'publish' === $p->post_status && (int) ($p->post_author ?? 0) === (int) $uid) { $n++; } } return $n; } }
/** Core's own method, as far as it matters: an empty list for any post that exists. */
$GLOBALS['wp_xmlrpc_server'] = new class { function pingback_extensions_getPingbacks($url) { return url_to_postid($url) ? [] : new IXR_Error(32, 'The specified target URL does not exist.'); } };
$GLOBALS['caps'] = [];
add_filter('omochix_core_glossary_public', $open);
$m = omochix_core_glossary_xmlrpc_methods(['pingback.ping' => 'this:pingback_ping', 'pingback.extensions.getPingbacks' => 'this:pingback_extensions_getPingbacks', 'wp.getPost' => 'this:wp_getPost']);
check('F2 XML-RPC: only getPingbacks is wrapped; every other method is left as it is', 'omochix_core_glossary_xmlrpc_get_pingbacks' === $m['pingback.extensions.getPingbacks'] && 'this:pingback_ping' === $m['pingback.ping'] && 'this:wp_getPost' === $m['wp.getPost'] && 3 === count($m) && ['a' => 'b'] === omochix_core_glossary_xmlrpc_methods(['a' => 'b']));
$u = static fn($p) => 'https://example.test/glossary/' . get_post($p)->post_name . '-' . $p . '/';
$missing = omochix_core_glossary_xmlrpc_get_pingbacks('https://example.test/glossary/no-such/');
$hiddenr = omochix_core_glossary_xmlrpc_get_pingbacks($u($hid_pub));
check('F2 XML-RPC getPingbacks: the address of a hidden published term answers like an address nothing lives at (reproduction: an empty list)', $missing instanceof IXR_Error && $hiddenr instanceof IXR_Error && [$missing->code, $missing->message] === [$hiddenr->code, $hiddenr->message]);
check('F2 XML-RPC getPingbacks: a visible term still answers core\'s list; a non-string is "no such address"', [] === omochix_core_glossary_xmlrpc_get_pingbacks($u($vis)) && omochix_core_glossary_xmlrpc_get_pingbacks(['x']) instanceof IXR_Error);
$GLOBALS['caps'] = ['edit_post' => true];
check('F2 XML-RPC getPingbacks: an editor gets core\'s answer for a hidden term', [] === omochix_core_glossary_xmlrpc_get_pingbacks($u($hid_pub)));
$GLOBALS['caps'] = [];
check('F2 a term never takes trackbacks, pingbacks or comments, whatever is stored; other posts keep their setting', false === omochix_core_glossary_no_pings(true, $hid_pub) && false === omochix_core_glossary_no_pings(true, $vis) && true === omochix_core_glossary_no_pings(true, $other) && false === omochix_core_glossary_no_pings(false, $other) && true === omochix_core_glossary_no_pings(true, 0) && 99 === has_filter('pings_open', 'omochix_core_glossary_no_pings') && 99 === has_filter('comments_open', 'omochix_core_glossary_no_pings'));
check('F2 REST term search: no glossary category for a visitor (alone: nothing is found; with others: only the others)', [0] === omochix_core_glossary_rest_term_search_args(['taxonomy' => ['omx_glossary_category']])['include'] && ['category', 'post_tag'] === omochix_core_glossary_rest_term_search_args(['taxonomy' => ['category', 'omx_glossary_category', 'post_tag']])['taxonomy'] && ['taxonomy' => ['category']] === omochix_core_glossary_rest_term_search_args(['taxonomy' => ['category']]));
check('F2 REST users: asking only about terms lists nobody (review N1: ?has_published_posts=omx_glossary listed authors of hidden terms)', ['has_published_posts' => ['omx_glossary'], 'include' => [0]] === omochix_core_glossary_rest_user_args(['has_published_posts' => ['omx_glossary']]) && [0] === omochix_core_glossary_rest_user_args(['has_published_posts' => ['omx_glossary', 'omx_glossary'], 'include' => [5]])['include']);
check('F2 REST users: a term does not make its author public; the list of types is never emptied', ['post', 'page'] === omochix_core_glossary_rest_user_args(['has_published_posts' => ['post', 'omx_glossary', 'page']])['has_published_posts'] && !isset(omochix_core_glossary_rest_user_args(['has_published_posts' => ['post', 'omx_glossary']])['include']) && ['has_published_posts' => true] === omochix_core_glossary_rest_user_args(['has_published_posts' => true]) && ['x' => 1] === omochix_core_glossary_rest_user_args(['x' => 1]));
$GLOBALS['posts'][$hid_pub]->post_author = 77; $GLOBALS['posts'][$other]->post_author = 78; $GLOBALS['posts'][$other]->post_status = 'publish';
check('F2 REST single user: terms are not counted for a visitor (0 for a terms-only author), other posts still are', 0 === omochix_core_glossary_user_post_count(1, 77, ['post', 'omx_glossary'], true) && 1 === omochix_core_glossary_user_post_count(2, 78, ['post', 'omx_glossary'], true) && 0 === omochix_core_glossary_user_post_count(5, 77, 'omx_glossary') && 5 === omochix_core_glossary_user_post_count(5, 77, 'post') && 5 === omochix_core_glossary_user_post_count(5, 77, ['post', 'page']));
$GLOBALS['caps'] = ['edit_others_omx_glossaries' => true];
check('F2 an editor sees categories in search, and the user and count answers untouched', ['taxonomy' => ['omx_glossary_category']] === omochix_core_glossary_rest_term_search_args(['taxonomy' => ['omx_glossary_category']]) && ['has_published_posts' => ['omx_glossary']] === omochix_core_glossary_rest_user_args(['has_published_posts' => ['omx_glossary']]) && 9 === omochix_core_glossary_user_post_count(9, 77, ['post', 'omx_glossary']));
$GLOBALS['caps'] = []; $GLOBALS['posts'][$other]->post_status = 'draft';
remove_filter('omochix_core_glossary_public', $open);
check('F2 closed: every term is hidden, so getPingbacks answers "no such address" for any of them', omochix_core_glossary_xmlrpc_get_pingbacks($u($vis)) instanceof IXR_Error);

// ---------------------------------------------- N2: link search, "Attach to" search, read_post (review N2)
// Reproduction (real WordPress 7.1.4, glossary open): with the internal-linking nonce (post editor, contributors and up)
// wp-link-ajax returned the ID and title of published hidden terms; with the find-posts nonce (media list, authors and up)
// find_posts returned the titles of draft, scheduled, pending and private terms; the media list and the media modal
// printed the title of a published hidden term as a file's parent ("Uploaded to"), because read_post allowed it.
if (!function_exists('get_post_types')) { function get_post_types($args = [], $out = 'names') { $n = ['post' => 'post', 'page' => 'page', 'attachment' => 'attachment', 'omx_glossary' => 'omx_glossary']; return $n; } }
$gl_q = static function (array $v) { return new class($v) { public $v; function __construct($v) { $this->v = $v; } function get($k) { return $this->v[$k] ?? ''; } function set($k, $x) { $this->v[$k] = $x; } }; };
$gl_find = static function ($q) { $GLOBALS['action_stack'][] = 'wp_ajax_find_posts'; omochix_core_glossary_find_posts_query($q); array_pop($GLOBALS['action_stack']); return $q->v['post_type']; };
$GLOBALS['caps'] = [];
add_filter('omochix_core_glossary_public', $open);
$lq = ['post_type' => ['post', 'page', 'omx_glossary'], 'suppress_filters' => true, 'post_status' => 'publish', 'posts_per_page' => 20, 'offset' => 0];
$la = omochix_core_glossary_link_query_args($lq);
check('N2 link search: published hidden terms are left out of the query for a non-editor (so paging stays right), visible terms are not', in_array($hid_pub, $la['post__not_in'], true) && !in_array($vis, $la['post__not_in'], true) && ['post', 'page', 'omx_glossary'] === $la['post_type'] && 20 === $la['posts_per_page']);
check('N2 link search: an existing post__not_in is kept', in_array($other, omochix_core_glossary_link_query_args(['post__not_in' => [$other]] + $lq)['post__not_in'], true) && 'omx_glossary' !== get_post($other)->post_type && in_array($hid_pub, omochix_core_glossary_link_query_args(['post__not_in' => [$other]] + $lq)['post__not_in'], true));
check('N2 link search: any status but "publish" (a filter widened it) drops the glossary from the search', ['post', 'page'] === omochix_core_glossary_link_query_args(['post_status' => ['publish', 'draft']] + $lq)['post_type'] && ['post', 'page'] === omochix_core_glossary_link_query_args(['post_status' => 'any'] + $lq)['post_type'] && ['post', 'page'] === omochix_core_glossary_link_query_args(array_diff_key($lq, ['post_status' => 1]))['post_type']);
check('N2 link search: "any" type becomes the public types without the glossary; glossary alone becomes nothing', !in_array('omx_glossary', omochix_core_glossary_link_query_args(['post_type' => 'any', 'post_status' => 'draft'] + $lq)['post_type'], true) && ['__omx_none__'] === omochix_core_glossary_link_query_args(['post_type' => 'omx_glossary', 'post_status' => 'draft'] + $lq)['post_type']);
check('N2 link search: a query without the glossary, a non-array and an editor\'s query are untouched', ['post_type' => ['post'], 'post_status' => 'publish'] === omochix_core_glossary_link_query_args(['post_type' => ['post'], 'post_status' => 'publish']) && 'x' === omochix_core_glossary_link_query_args('x'));
$big = []; for ($i = 0; $i <= OMOCHIX_CORE_GLOSSARY_LINK_QUERY_MAX; $i++) { $big[] = ih_add_post(['post_type' => 'omx_glossary', 'post_status' => 'publish', 'post_name' => 'big-' . $i]); }
check('N2 link search: more published terms than can be listed: the glossary is left out of the search (fail closed)', null === omochix_core_glossary_hidden_published_ids() && ['post', 'page'] === omochix_core_glossary_link_query_args($lq)['post_type']);
foreach ($big as $i) { unset($GLOBALS['posts'][$i]); }
check('N2 link search: below the limit the list is back', is_array(omochix_core_glossary_hidden_published_ids()));
$rows = [['ID' => $hid_pub, 'title' => 'h'], ['ID' => $vis, 'title' => 'v'], ['ID' => $other, 'title' => 'o'], ['ID' => 0, 'title' => 'z'], ['ID' => 999999, 'title' => 'gone'], 'junk'];
check('N2 link results: a hidden term is not returned even if the query found it; visible terms and other posts are (a row for a post that cannot be read back is dropped too)', [$vis, $other, 0] === array_column(omochix_core_glossary_link_query_results($rows), 'ID') && 'x' === omochix_core_glossary_link_query_results('x'));
check('N2 find_posts: inside the handler the glossary is left out for a non-editor (review: draft and scheduled titles were listed)', ['post', 'page'] === $gl_find($gl_q(['post_type' => ['post', 'page', 'omx_glossary'], 'post_status' => 'any'])) && ['__omx_none__'] === $gl_find($gl_q(['post_type' => 'omx_glossary'])) && !in_array('omx_glossary', $gl_find($gl_q(['post_type' => 'any'])), true) && ['post'] === $gl_find($gl_q(['post_type' => ['post']])));
$q = $gl_q(['post_type' => ['post', 'omx_glossary']]); omochix_core_glossary_find_posts_query($q);
check('N2 find_posts: queries outside that handler are untouched (front end, other admin screens)', ['post', 'omx_glossary'] === $q->v['post_type'] && null === omochix_core_glossary_find_posts_query('x'));
check('N2 read_post: a hidden term needs the glossary\'s editor capability (media "Uploaded to" printed its title), for a revision too', ['read', 'edit_others_omx_glossaries'] === omochix_core_glossary_map_read_post(['read'], 'read_post', 7, [$hid_pub]) && ['read', 'edit_others_omx_glossaries'] === omochix_core_glossary_map_read_post(['read'], 'read_post', 7, [$rv_h]) && in_array('edit_others_omx_glossaries', omochix_core_glossary_map_read_post(['edit_others_omx_glossaries'], 'read_post', 7, [$hid_draft]), true));
check('N2 read_post: a post object is judged like its ID (review F5: (int) of an object is 1, another post)', ['read', 'edit_others_omx_glossaries'] === omochix_core_glossary_map_read_post(['read'], 'read_post', 7, [get_post($hid_pub)]) && ['read'] === omochix_core_glossary_map_read_post(['read'], 'read_post', 7, [get_post($vis)]) && ['read'] === omochix_core_glossary_map_read_post(['read'], 'read_post', 7, [(object) []]));
check('N2 read_post: a visible term, other posts, other capabilities and junk are untouched', ['read'] === omochix_core_glossary_map_read_post(['read'], 'read_post', 7, [$vis]) && ['read'] === omochix_core_glossary_map_read_post(['read'], 'read_post', 7, [$other]) && ['edit_x'] === omochix_core_glossary_map_read_post(['edit_x'], 'edit_post', 7, [$hid_pub]) && ['read'] === omochix_core_glossary_map_read_post(['read'], 'read_post', 7, []) && 'x' === omochix_core_glossary_map_read_post('x', 'read_post', 7, [$hid_pub]));
check('N2 the capability asked for is one only editors get at run time', omochix_core_glossary_user_has_cap(['edit_others_posts' => true])['edit_others_omx_glossaries'] && !isset(omochix_core_glossary_user_has_cap(['edit_posts' => true, 'edit_others_omx_glossaries' => true])['edit_others_omx_glossaries']));
$GLOBALS['caps'] = ['edit_others_omx_glossaries' => true];
check('N2 an editor keeps every term in the link search, its results and the "Attach to" search', $lq === omochix_core_glossary_link_query_args($lq) && $rows === omochix_core_glossary_link_query_results($rows) && ['post', 'omx_glossary'] === $gl_find($gl_q(['post_type' => ['post', 'omx_glossary']])));
$GLOBALS['caps'] = [];
remove_filter('omochix_core_glossary_public', $open);
check('N2 closed: no term in the link search for a non-editor, and no term is readable', ['post', 'page'] === omochix_core_glossary_link_query_args($lq)['post_type'] && ['read', 'edit_others_omx_glossaries'] === omochix_core_glossary_map_read_post(['read'], 'read_post', 7, [$vis]));
check('N2 hooks: link query args and results last, pre_get_posts last, map_meta_cap with its 4 arguments', 9999 === has_filter('wp_link_query_args', 'omochix_core_glossary_link_query_args') && 9999 === has_filter('wp_link_query', 'omochix_core_glossary_link_query_results') && 9999 === has_action('pre_get_posts', 'omochix_core_glossary_find_posts_query') && 10 === has_filter('map_meta_cap', 'omochix_core_glossary_map_read_post') && [['omochix_core_glossary_map_read_post', 4]] === array_values(array_filter($GLOBALS['hooks']['map_meta_cap'][10] ?? [], static fn($h) => 'omochix_core_glossary_map_read_post' === $h[0])));

// ---------------------------------------------- N2 case B: Query Loop block (review of the N2 fix)
// Reproduction (real WordPress 7.1.4, glossary open): a Query Loop set to the glossary in an author's published post listed
// hidden terms (rule problems, a password, a future check date) with title, content and summary to visitors, in REST and feeds.
add_filter('omochix_core_glossary_public', $open);
$GLOBALS['caps'] = [];
$priv_t = gl_store('private');
$qv = ['post_type' => 'omx_glossary', 'order' => 'DESC', 'orderby' => 'date', 'post__not_in' => [], 'tax_query' => [], 'offset' => 0, 'posts_per_page' => 10];
$qa = omochix_core_glossary_query_loop_vars($qv);
check('N2-B Query Loop: hidden published terms are left out of the query itself (pages and counts agree), visible ones stay', in_array($hid_pub, $qa['post__not_in'], true) && !in_array($vis, $qa['post__not_in'], true) && 'omx_glossary' === $qa['post_type'] && 10 === $qa['posts_per_page'] && true === $qa['omochix_glossary_loop']);
check('N2-B Query Loop: only published posts are asked for (review F1: left at the default, WP_Query adds drafts, pending and scheduled posts in wp-admin, private ones for readers)', 'publish' === $qa['post_status'] && 'publish' === omochix_core_glossary_query_loop_vars(['post_status' => 'publish'] + $qv)['post_status'] && 'publish' === omochix_core_glossary_query_loop_vars(['post_status' => []] + $qv)['post_status']);
$qi = omochix_core_glossary_query_loop_vars(['post__in' => [$hid_pub, $vis, $other]] + $qv);
check('N2-B Query Loop: hidden terms are taken out of post__in too (review F2: WP_Query ignores post__not_in once post__in is set, e.g. sticky "only")', [$vis, $other] === $qi['post__in'] && [0] === omochix_core_glossary_query_loop_vars(['post__in' => [$hid_pub]] + $qv)['post__in'] && !isset($qa['post__in']));
$gl_lq = static function (array $v) { return new class($v) { public $v; function __construct($v) { $this->v = $v; } function get($k) { return $this->v[$k] ?? ''; } }; };
$lp = [get_post($hid_pub), get_post($vis), get_post($other), get_post($hid_draft)];
check('N2-B Query Loop results: a hidden term found anyway is dropped (second line); other posts and visible terms stay; other queries are untouched', [$vis, $other] === array_map(static fn($p) => $p->ID, omochix_core_glossary_loop_posts($lp, $gl_lq(['omochix_glossary_loop' => true]))) && $lp === omochix_core_glossary_loop_posts($lp, $gl_lq([])) && $lp === omochix_core_glossary_loop_posts($lp, $gl_lq(['omochix_glossary_loop' => 1])) && $lp === omochix_core_glossary_loop_posts($lp) && 'x' === omochix_core_glossary_loop_posts('x', $gl_lq(['omochix_glossary_loop' => true])) && [$vis] === array_map(static fn($p) => $p->ID, omochix_core_glossary_loop_posts([$vis, $hid_pub], $gl_lq(['omochix_glossary_loop' => true]))));
check('N2-B Query Loop: mixed types keep every other type, and an earlier exclusion is kept', ['post', 'omx_glossary'] === omochix_core_glossary_query_loop_vars(['post_type' => ['post', 'omx_glossary']] + $qv)['post_type'] && in_array($other, omochix_core_glossary_query_loop_vars(['post__not_in' => [$other]] + $qv)['post__not_in'], true));
check('N2-B Query Loop: "any", no type, other types and non-arrays are untouched ("any" never covers the glossary, it is excluded from search)', ['post_type' => 'any'] === omochix_core_glossary_query_loop_vars(['post_type' => 'any']) && ['x' => 1] === omochix_core_glossary_query_loop_vars(['x' => 1]) && ['post_type' => ''] === omochix_core_glossary_query_loop_vars(['post_type' => '']) && ['post_type' => ['page']] === omochix_core_glossary_query_loop_vars(['post_type' => ['page']]) && 'x' === omochix_core_glossary_query_loop_vars('x'));
check('N2-B Query Loop: a status other than the default or "publish" drops the glossary (fail closed); "publish" alone keeps the list', ['post'] === omochix_core_glossary_query_loop_vars(['post_type' => ['post', 'omx_glossary'], 'post_status' => ['publish', 'draft']] + $qv)['post_type'] && ['__omx_none__'] === omochix_core_glossary_query_loop_vars(['post_status' => 'any'] + $qv)['post_type'] && 'omx_glossary' === omochix_core_glossary_query_loop_vars(['post_status' => 'publish'] + $qv)['post_type'] && 'omx_glossary' === omochix_core_glossary_query_loop_vars(['post_status' => []] + $qv)['post_type']);
$GLOBALS['caps'] = ['edit_others_omx_glossaries' => true];
check('N2-B Query Loop: editors get the same list (a page built for one viewer may be cached for the next)', $qa === omochix_core_glossary_query_loop_vars($qv));
$GLOBALS['caps'] = [];
$big = []; for ($i = 0; $i <= OMOCHIX_CORE_GLOSSARY_LINK_QUERY_MAX; $i++) { $big[] = ih_add_post(['post_type' => 'omx_glossary', 'post_status' => 'publish', 'post_name' => 'bigq-' . $i]); }
check('N2-B Query Loop: more published terms than can be listed: no glossary (fail closed)', ['__omx_none__'] === omochix_core_glossary_query_loop_vars($qv)['post_type']);
foreach ($big as $i) { unset($GLOBALS['posts'][$i]); }
remove_filter('omochix_core_glossary_public', $open);
check('N2-B Query Loop closed: no glossary at all', ['__omx_none__'] === omochix_core_glossary_query_loop_vars($qv)['post_type'] && ['post'] === omochix_core_glossary_query_loop_vars(['post_type' => ['post', 'omx_glossary']] + $qv)['post_type']);
add_filter('omochix_core_glossary_public', $open);

// ---------------------------------------------- N2 case A: the comments screen and admin titles
// Reproduction: /wp-admin/edit-comments.php?p=<ID> (contributors and up) printed the title of a term in any status, glossary closed too.
$die = static function ($id) { try { omochix_core_glossary_comments_screen_args(['post_id' => $id, 'number' => 20]); return false; } catch (RuntimeException $e) { return false !== strpos($e->getMessage(), 'wp_die'); } };
check('N2-A comments screen: refused for a hidden published term, a draft and a private term (before anything is printed)', $die($hid_pub) && $die($hid_draft) && $die($priv_t));
check('N2-A comments screen: a visible term, another post, an unknown ID and the screen without a post are unchanged', ['post_id' => $vis] === omochix_core_glossary_comments_screen_args(['post_id' => $vis]) && ['post_id' => $other] === omochix_core_glossary_comments_screen_args(['post_id' => $other]) && ['post_id' => 999999] === omochix_core_glossary_comments_screen_args(['post_id' => 999999]) && ['post_id' => 0] === omochix_core_glossary_comments_screen_args(['post_id' => 0]) && [] === omochix_core_glossary_comments_screen_args([]) && 'x' === omochix_core_glossary_comments_screen_args('x'));
$GLOBALS['ctx']['is_admin'] = true;
check('N2-A admin titles: none for a hidden term or its revision; visible terms, other posts and junk IDs keep theirs', '' === omochix_core_glossary_admin_title('T', $hid_pub) && '' === omochix_core_glossary_admin_title('T', $hid_draft) && '' === omochix_core_glossary_admin_title('T', $rv_h) && 'T' === omochix_core_glossary_admin_title('T', $vis) && 'T' === omochix_core_glossary_admin_title('T', $other) && 'T' === omochix_core_glossary_admin_title('T', 0) && 'T' === omochix_core_glossary_admin_title('T', 'abc') && 'T' === omochix_core_glossary_admin_title('T'));
$GLOBALS['caps'] = ['edit_post' => true, 'edit_others_omx_glossaries' => true];
check('N2-A editors keep the comments screen and every title', ['post_id' => $hid_draft] === omochix_core_glossary_comments_screen_args(['post_id' => $hid_draft]) && 'T' === omochix_core_glossary_admin_title('T', $hid_draft));
$GLOBALS['caps'] = [];
$GLOBALS['ctx']['is_admin'] = false;
check('N2-A front end, feeds, REST and e-mails: titles are not touched outside wp-admin', 'T' === omochix_core_glossary_admin_title('T', $hid_pub));
remove_filter('omochix_core_glossary_public', $open);
$GLOBALS['ctx']['is_admin'] = true;
check('N2-A closed: every term is hidden from non-editors on the comments screen and in admin titles', $die($vis) && '' === omochix_core_glossary_admin_title('T', $vis));
$GLOBALS['ctx']['is_admin'] = false;
unset($GLOBALS['posts'][$priv_t]);
$hook = static fn($h, $cb) => array_values(array_filter(array_merge(...array_values($GLOBALS['hooks'][$h] ?? [[]])), static fn($x) => $cb === $x[0]));
// ---------------------------------------------- N-1: navigation block "ref" (audit 3 of the N2 fix)
// Reproduction (real WordPress 7.1.4, open, closed and with the module not loaded): <!-- wp:navigation {"ref":<term ID>} /-->
// in an author's published post printed a hidden term's title (aria-label) and blocks to visitors; the block renderer REST
// route did the same for a contributor. WordPress checks only that the post is published, not its type or a password.
add_filter('omochix_core_glossary_public', $open);
if (!class_exists('WP_Block')) { class WP_Block { public $parsed_block; function __construct($pb = []) { $this->parsed_block = $pb; } } }
$navm = ih_add_post(['post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Menu']);
$navd = ih_add_post(['post_type' => 'wp_navigation', 'post_status' => 'draft', 'post_title' => 'Draft menu']);
$hid_pass = gl_store('publish'); $GLOBALS['posts'][$hid_pass]->post_password = 'pw';
$priv_n = gl_store('private');
$nb = static fn($attrs) => new WP_Block(['blockName' => 'core/navigation', 'attrs' => $attrs]);
check('N-1 ref: a navigation menu (int, digit string, or a whole float as the block renderer REST route passes it) is accepted, published or not (WordPress still shows only published menus)', omochix_core_glossary_nav_ref_ok($navm) && omochix_core_glossary_nav_ref_ok((string) $navm) && omochix_core_glossary_nav_ref_ok((float) $navm) && omochix_core_glossary_nav_ref_ok($navd));
check('N-1 ref: a term in any state, another post type and an unknown ID are refused', !omochix_core_glossary_nav_ref_ok($hid_pub) && !omochix_core_glossary_nav_ref_ok($vis) && !omochix_core_glossary_nav_ref_ok($hid_draft) && !omochix_core_glossary_nav_ref_ok($hid_pass) && !omochix_core_glossary_nav_ref_ok($priv_n) && !omochix_core_glossary_nav_ref_ok($other) && !omochix_core_glossary_nav_ref_ok(999999) && !omochix_core_glossary_nav_ref_ok((string) $hid_pub) && !omochix_core_glossary_nav_ref_ok((float) $hid_pub));
$junk = [0, -1, -$navm, 0.0, -1.0, (float) -$navm, $navm + 0.5, 1e30, INF, NAN, 9007199254740992.0, '0', '-1', '0' . $navm, ' ' . $navm, $navm . ' ', $navm . 'a', '+' . $navm, '1e3', 'abc', '', [$navm], ['ref' => $navm], true, false, null, (object) ['ID' => $navm], '99999999999999999999', PHP_INT_MAX, get_post($navm)];
$GLOBALS['posts'][0] = get_post($navm); // WordPress: get_post(0) falls back to the global post, which may be a menu.
$bad_junk = array_keys(array_filter($junk, static fn($v) => omochix_core_glossary_nav_ref_ok($v)));
unset($GLOBALS['posts'][0]);
check('N-1 ref: 0, negatives, fractions, huge or non-finite floats, padded or signed strings, text, lists, booleans, null, objects and overflowing numbers are refused (a menu\'s own ID in those forms too)', [] === $bad_junk, implode(',', $bad_junk));
check('N-1 guard: a block without a reference is left to WordPress (inner blocks or its fallback)', omochix_core_glossary_nav_ref_guard([], $nb([])) && omochix_core_glossary_nav_ref_guard(['overlayMenu' => 'never'], $nb(['overlayMenu' => 'never'])) && omochix_core_glossary_nav_ref_guard([], null) && omochix_core_glossary_nav_ref_guard('x', 'y'));
check('N-1 guard: a menu reference renders; a term reference does not, in "ref" or the legacy "navigationMenuId"', omochix_core_glossary_nav_ref_guard(['ref' => $navm], $nb(['ref' => $navm])) && !omochix_core_glossary_nav_ref_guard(['ref' => $hid_pub], $nb(['ref' => $hid_pub])) && !omochix_core_glossary_nav_ref_guard(['navigationMenuId' => $hid_pub], $nb(['navigationMenuId' => $hid_pub])) && !omochix_core_glossary_nav_ref_guard(['ref' => $navm, 'navigationMenuId' => $hid_pub], $nb(['ref' => $navm, 'navigationMenuId' => $hid_pub])));
check('N-1 guard: a stored reference WordPress dropped as the wrong type ("abc", a list, true, null) fails closed instead of showing the fallback menu; stored and prepared must both be menus', !omochix_core_glossary_nav_ref_guard([], $nb(['ref' => 'abc'])) && !omochix_core_glossary_nav_ref_guard([], $nb(['ref' => [$hid_pub]])) && !omochix_core_glossary_nav_ref_guard([], $nb(['ref' => true])) && !omochix_core_glossary_nav_ref_guard([], $nb(['ref' => null])) && !omochix_core_glossary_nav_ref_guard(['ref' => $navm], $nb(['ref' => $hid_pub])) && !omochix_core_glossary_nav_ref_guard(['ref' => $hid_pub], $nb(['ref' => $navm])));
$inner_calls = 0;
$wrapped = omochix_core_glossary_nav_block_args(['render_callback' => static function ($a, $c, $b) use (&$inner_calls) { $inner_calls++; return 'NAV'; }, 'title' => 'Navigation'], 'core/navigation');
$cb = $wrapped['render_callback'];
check('N-1 render: the wrapped callback prints nothing for a term and calls WordPress for a menu or no reference', '' === $cb(['ref' => $hid_pub], '', $nb(['ref' => $hid_pub])) && '' === $cb(['ref' => $vis], '', $nb(['ref' => $vis])) && '' === $cb(['ref' => $hid_pass], '', $nb(['ref' => $hid_pass])) && 0 === $inner_calls && 'NAV' === $cb(['ref' => $navm], '', $nb(['ref' => $navm])) && 'NAV' === $cb([], '', $nb([])) && 2 === $inner_calls && 'Navigation' === $wrapped['title']);
$plain = static fn() => 'X';
check('N-1 render: other blocks, a block type without a callback and junk arguments are untouched', ['render_callback' => $plain] === omochix_core_glossary_nav_block_args(['render_callback' => $plain], 'core/block') && ['title' => 'N'] === omochix_core_glossary_nav_block_args(['title' => 'N'], 'core/navigation') && ['render_callback' => 'no_such_fn'] === omochix_core_glossary_nav_block_args(['render_callback' => 'no_such_fn'], 'core/navigation') && 'x' === omochix_core_glossary_nav_block_args('x', 'core/navigation'));
$GLOBALS['caps'] = ['edit_others_omx_glossaries' => true, 'edit_others_posts' => true];
check('N-1 the same answer for editors (a page built for one viewer may be cached for the next)', '' === $cb(['ref' => $hid_pub], '', $nb(['ref' => $hid_pub])) && '' === $cb(['ref' => $vis], '', $nb(['ref' => $vis])) && 'NAV' === $cb(['ref' => $navm], '', $nb(['ref' => $navm])));
$GLOBALS['caps'] = [];
remove_filter('omochix_core_glossary_public', $open);
check('N-1 closed: the same rule (it does not depend on the switch or on what is visible)', '' === $cb(['ref' => $vis], '', $nb(['ref' => $vis])) && 'NAV' === $cb(['ref' => $navm], '', $nb(['ref' => $navm])));
$hook_n1 = array_values(array_filter(array_merge(...array_values($GLOBALS['hooks']['register_block_type_args'] ?? [[]])), static fn($x) => 'omochix_core_glossary_nav_block_args' === $x[0]));
check('N-1 hook: the block type arguments are wrapped last, with both arguments', 9999 === has_filter('register_block_type_args', 'omochix_core_glossary_nav_block_args') && [['omochix_core_glossary_nav_block_args', 2]] === $hook_n1, wp_json_encode($hook_n1));
$vis_src = (string) file_get_contents($PLUGIN . '/includes/web2/glossary-visibility.php');
$n1_a = strpos($vis_src, 'function omochix_core_glossary_nav_ref_ok'); $n1_b = strpos($vis_src, "add_filter( 'register_block_type_args'");
check('N-1 T-SAFE: the navigation guard reads no request data and no database handle', false !== $n1_a && $n1_b > $n1_a && 0 === preg_match('/\$_(POST|GET|REQUEST|COOKIE|SERVER|FILES)|\$wpdb|\bglobal\b/', substr($vis_src, $n1_a, $n1_b - $n1_a)));
unset($GLOBALS['posts'][$navm], $GLOBALS['posts'][$navd], $GLOBALS['posts'][$hid_pass], $GLOBALS['posts'][$priv_n]);

check('N2-A/B hooks: Query Loop vars, comments screen and admin titles run last, with the arguments they use', 9999 === has_filter('query_loop_block_query_vars', 'omochix_core_glossary_query_loop_vars') && 9999 === has_filter('comments_list_table_query_args', 'omochix_core_glossary_comments_screen_args') && 9999 === has_filter('the_title', 'omochix_core_glossary_admin_title') && 9999 === has_filter('the_posts', 'omochix_core_glossary_loop_posts') && [['omochix_core_glossary_loop_posts', 2]] === $hook('the_posts', 'omochix_core_glossary_loop_posts') && [['omochix_core_glossary_admin_title', 2]] === $hook('the_title', 'omochix_core_glossary_admin_title'));

// ---------------------------------------------- F3: the REST autosave route never publishes
// The autosave controller saves the post from what rest_pre_insert returns (it does not stop on an error),
// never stores meta or categories, and never fires rest_after_insert. So the status is taken off there.
foreach (['/wp/v2/glossary/12/autosaves', '/wp/v2/glossary/12/autosaves/', '/WP/V2/Glossary/12/Autosaves', '//wp/v2//glossary/12//autosaves', '/wp/v2/glossary/12/%61utosaves'] as $route) {
    foreach (['publish', 'future'] as $want) {
        $e = $empty();
        $prep = (object) ['ID' => $e, 'post_status' => $want, 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']];
        $r = omochix_core_glossary_guard_rest($prep, new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]], $route));
        check("F3 autosave route $route, status=$want, complete request: the status is taken off the save, nothing is handed over or noted", $r === $prep && !isset($r->post_status) && 'テスト用語' === $r->post_title && null === omochix_core_glossary_pending($e) && [] === omochix_core_glossary_rest_approved('list'));
    }
}
$e = $empty();
check('F3 reproduction of the bypass: with the hand-over an autosave published on meta it never stores; now the insert guard sees only the stored (empty) meta', 'draft' === omochix_core_glossary_guard_insert_data($data, ['ID' => $e])['post_status']);
$prep = (object) ['ID' => $e, 'post_title' => 'x', 'post_content' => 'y'];
check('F3 an ordinary autosave (no status) and a draft autosave pass untouched', $prep === omochix_core_glossary_guard_rest($prep, new GL_Req([], '/wp/v2/glossary/12/autosaves')) && 'x' === $prep->post_title && !isset(omochix_core_glossary_guard_rest((object) ['ID' => $e, 'post_status' => 'draft'], new GL_Req([], '/wp/v2/glossary/12/autosaves'))->post_status));
$r = omochix_core_glossary_guard_rest((object) ['ID' => $e, 'post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']], new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]], '/wp/v2/glossary/12'));
check('F3 the normal route still publishes a complete request (status kept, noted for the read-back)', !($r instanceof WP_Error) && 'publish' === $r->post_status && [$e] === omochix_core_glossary_rest_approved('list'));
foreach (['/wp/v2/glossary/12/revisions', '/wp/v2/glossary/autosaves-guide', '/wp/v2/glossary/12/myautosaves'] as $route) {
    omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
    $r = omochix_core_glossary_guard_rest((object) ['ID' => $e, 'post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']], new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]], $route));
    check("F3 a route that only looks similar ($route) is judged normally", !($r instanceof WP_Error) && 'publish' === $r->post_status);
}
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);

// ---------------------------------------------- F4: the guard's notes belong to one REST call
$outer = new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]);
$a = $empty(); $b = $empty();
$mk = static fn($pid) => (object) ['ID' => $pid, 'post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']];
omochix_core_glossary_guard_rest($mk($a), $outer);
$inner = new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]);
omochix_core_glossary_guard_rest($mk($b), $inner); // A REST call made from inside the first one (rest_do_request in a save hook).
$GLOBALS['posts'][$b]->post_status = 'publish';
apply_filters('rest_request_after_callbacks', null, null, $inner);
check('F4 a nested call ending: its own term is read back (incomplete as stored: draft) and its notes are dropped', 'draft' === get_post_field('post_status', $b) && !in_array($b, omochix_core_glossary_rest_approved('list'), true) && null === omochix_core_glossary_pending($b));
check('F4 ...and the outer call keeps its own (reproduction: they were all dropped, and the outer read-back was skipped)', in_array($a, omochix_core_glossary_rest_approved('list'), true) && is_array(omochix_core_glossary_pending($a)));
$GLOBALS['posts'][$a]->post_status = 'publish'; // The outer call saves the post; its meta was changed by the hook, or never stored.
do_action('rest_after_insert_omx_glossary', get_post($a), $outer, false);
check('F4 the outer read-back still runs: a term that fails as stored goes back to draft', 'draft' === get_post_field('post_status', $a));
apply_filters('rest_request_after_callbacks', null, null, $outer);
check('F4 when the outer call ends nothing is left', [] === omochix_core_glossary_rest_approved('list'));
// The same term in two calls: the later one owns the note.
$c = $empty(); $r1 = new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]); $r2 = new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]);
omochix_core_glossary_guard_rest($mk($c), $r1); omochix_core_glossary_guard_rest($mk($c), $r2);
apply_filters('rest_request_after_callbacks', null, null, $r1);
check('F4 a call ending does not drop a note a later call made for the same term', in_array($c, omochix_core_glossary_rest_approved('list'), true));
apply_filters('rest_request_after_callbacks', null, null, $r2);
// A nested call for the SAME term (review, R4): each call has its own notes for it.
$same = $empty(); $o2 = new GL_Req(['meta' => array_merge($full_meta, ['glossary_en' => 'Outer']), 'glossary-categories' => [$basics_id]]); $i2 = new GL_Req(['meta' => array_merge($full_meta, ['glossary_en' => 'Inner']), 'glossary-categories' => [$basics_id]]);
omochix_core_glossary_guard_rest($mk($same), $o2);
omochix_core_glossary_guard_rest($mk($same), $i2); // Inner call, same term.
$first_taken = omochix_core_glossary_pending($same); $second_taken = omochix_core_glossary_pending($same);
check('F4 the save that runs now takes the newest hand-over (the inner call\'s), the outer one\'s stays for the outer save', 'Inner' === ($first_taken['glossary_en'] ?? '') && 'Outer' === ($second_taken['glossary_en'] ?? '') && null === omochix_core_glossary_pending($same));
$GLOBALS['posts'][$same]->post_status = 'publish';
foreach ($full_meta as $k => $val) { update_post_meta($same, $k, $val); } wp_set_object_terms($same, ['basics'], 'omx_glossary_category');
do_action('rest_after_insert_omx_glossary', get_post($same), $i2, false); // Inner read-back: complete at this moment.
apply_filters('rest_request_after_callbacks', null, null, $i2);
check('F4 the inner call ending leaves the outer call\'s note for the same term', [$same] === omochix_core_glossary_rest_approved('list', false, omochix_core_glossary_rest_owner($o2)));
update_post_meta($same, 'glossary_summary', ''); // Then the outer call stores something that fails.
do_action('rest_after_insert_omx_glossary', get_post($same), $o2, false);
check('F4 ...so the outer read-back still demotes it (reproduction: the inner call had used up the note, the term stayed published)', 'draft' === get_post_field('post_status', $same));
$same2 = $empty(); $o3 = new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]);
omochix_core_glossary_guard_rest($mk($same2), $o3);
$r = omochix_core_glossary_guard_rest($mk($same2), new GL_Req(['meta' => array_merge($full_meta, ['glossary_sources' => []])])); // Inner call refused.
check('F4 an inner call that is refused drops only its own notes', $r instanceof WP_Error && [$same2] === omochix_core_glossary_rest_approved('list') && is_array(omochix_core_glossary_pending($same2)));
omochix_core_glossary_guard_rest($mk($same2), $au = new GL_Req(['meta' => $full_meta], '/wp/v2/glossary/9/autosaves'));
check('F4 an inner autosave for the same term drops nothing of the outer call', [$same2] === omochix_core_glossary_rest_approved('list'));
do_action('rest_after_insert_omx_glossary', get_post($same2), new GL_Req(), false);
check('F4 a read-back asked by a call that noted nothing does nothing', [$same2] === omochix_core_glossary_rest_approved('list'));
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
// The same request judged twice (dispatched again, or another filter runs the controller again): the later answer counts.
$tw = $empty(); $rq2 = new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]);
omochix_core_glossary_guard_rest($mk($tw), $rq2);
$rq2->p['meta']['glossary_aliases'] = 'a,b'; // Now of the wrong type.
$r = omochix_core_glossary_guard_rest($mk($tw), $rq2);
check('F4 a call that was allowed and is then refused for the type of a value keeps no approval and no hand-over', $r instanceof WP_Error && [] === omochix_core_glossary_rest_approved('list') && null === omochix_core_glossary_pending($tw));
omochix_core_glossary_guard_rest($mk($tw), $rq3 = new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
$rq3->p['meta']['glossary_sources'] = [];
$r = omochix_core_glossary_guard_rest($mk($tw), $rq3);
check('F4 ...and the same when it is then refused for its content', $r instanceof WP_Error && [] === omochix_core_glossary_rest_approved('list') && null === omochix_core_glossary_pending($tw));
// A create that is cut short inside wp_insert_post (review, R3): no REST hook has run yet, the note still says "a new post".
omochix_core_glossary_guard_rest((object) ['post_status' => 'publish', 'post_name' => 'cut-term', 'post_title' => '途中で止まる用語', 'post_content' => gl_valid()['content_html']], new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
check('F4 create: the note is for "a new post" until the row exists', [0] === omochix_core_glossary_rest_approved('list'));
$cut = ih_add_post(['post_type' => 'omx_glossary', 'post_status' => 'publish', 'post_name' => 'cut-term', 'post_title' => '途中で止まる用語', 'post_content' => gl_valid()['content_html']]);
do_action('transition_post_status', 'publish', 'new', get_post($cut)); // WordPress fires this inside wp_insert_post, before save_post.
check('F4 create: as soon as the row exists the note carries its ID', [$cut] === omochix_core_glossary_rest_approved('list'));
do_action('shutdown'); // save_post exits: nothing else runs.
check('F4 create cut short inside wp_insert_post: read back at shutdown, draft (reproduction: it stayed published with no meta)', 'draft' === get_post_field('post_status', $cut) && [] === omochix_core_glossary_rest_approved('list'));
$oth = ih_add_post(['post_type' => 'post', 'post_status' => 'publish', 'post_name' => 'p']);
omochix_core_glossary_rest_approved(0, true, 7);
do_action('transition_post_status', 'publish', 'new', get_post($oth)); do_action('transition_post_status', 'draft', 'new', get_post($cut)); do_action('transition_post_status', 'publish', 'draft', get_post($cut));
check('F4 create: another post type, a new draft, or an existing post never takes the note', [0] === omochix_core_glossary_rest_approved('list'));
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
// A call that never ends properly (wp_die, exit, a fatal error after the post row is saved).
$dz = $empty();
omochix_core_glossary_guard_rest($mk($dz), new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
$GLOBALS['posts'][$dz]->post_status = 'publish';
check('F4 the read-back is also registered for the end of the PHP request', false !== has_action('shutdown', 'omochix_core_glossary_guard_shutdown'));
do_action('shutdown');
check('F4 a call cut short after saving (no REST hook runs any more): read back at shutdown, draft, nothing left', 'draft' === get_post_field('post_status', $dz) && [] === omochix_core_glossary_rest_approved('list') && null === omochix_core_glossary_pending($dz));
$GLOBALS['posts'][$live]->post_status = 'publish';
do_action('shutdown');
check('F4 shutdown with nothing noted touches nothing (a live failing term stays published)', 'publish' === get_post_field('post_status', $live));
$gone = $empty();
omochix_core_glossary_guard_rest($mk($gone), new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]));
$GLOBALS['posts'][$gone]->post_status = 'publish';
$real_posts = $GLOBALS['posts']; $kept = $GLOBALS['posts'][$gone]; unset($GLOBALS['posts'][$gone]);
check('F4 read-back of a post that cannot be read: reported as not demoted, no error', false === omochix_core_glossary_rest_recheck($gone));
$GLOBALS['posts'] = $real_posts;
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);
// Something puts the status back (another plugin's hook): the read-back must not claim a demotion that did not hold.
$stuck = $empty();
$rq = new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]]);
omochix_core_glossary_guard_rest($mk($stuck), $rq);
$GLOBALS['posts'][$stuck]->post_status = 'publish';
add_action('transition_post_status', $undo = static function ($new, $old, $post) use ($stuck) { if ((int) $post->ID === $stuck && 'draft' === $new) { $post->post_status = 'publish'; } }, 10, 3);
$answer = clone get_post($stuck);
do_action('rest_after_insert_omx_glossary', $answer, $rq, false);
remove_action('transition_post_status', $undo, 10);
check('F4 read-back: when the demotion does not hold, the answer is not changed to "draft" (it says what is stored)', 'publish' === get_post_field('post_status', $stuck) && 'publish' === $answer->post_status);
$GLOBALS['posts'][$stuck]->post_status = 'draft';
omochix_core_glossary_pending(null); omochix_core_glossary_rest_approved(null);

// =================================================== T-SAFE: static
$src = '';
foreach (omochix_core_glossary_files() as $f) { $src .= file_get_contents("$PLUGIN/$f"); }
$src .= file_get_contents("$PLUGIN/includes/web2/glossary-loader.php");
$ids = []; $vars = [];
foreach (token_get_all($src) as $t) { if (is_array($t) && T_STRING === $t[0]) { $ids[] = strtolower($t[1]); } if (is_array($t) && T_VARIABLE === $t[0]) { $vars[] = $t[1]; } if (is_array($t) && in_array($t[0], [T_EVAL, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE], true)) { $ids[] = 'T_' . strtoupper($t[1]); } }
$forbidden = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'file_put_contents', 'fopen', 'unlink', 'rename', 'header', 'setcookie', 'add_role', 'remove_role', 'add_cap', 'remove_cap', 'wp_remote_get', 'wp_remote_post', 'wp_remote_request', 'unserialize', 'maybe_unserialize', 'extract', 'create_function', 'register_rest_route', 'add_menu_page', 'delete_option', 'add_option', 'T_EVAL', 'T_INCLUDE', 'T_INCLUDE_ONCE', 'T_REQUIRE'];
check('T-SAFE no shell, file writes, raw HTTP, roles, unserialize, eval, include or new REST routes', [] === array_intersect($forbidden, $ids), implode(',', array_intersect($forbidden, $ids)));
check('T-SAFE no $wpdb, no POST/REQUEST/COOKIE/SERVER/FILES; $_GET only for the notice post ID (absint)', [] === array_intersect(['$wpdb', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER', '$_FILES', '$_ENV'], $vars) && 1 === substr_count($src, '$_GET[') - substr_count($src, "isset( \$_GET['post'] )") && false !== strpos($src, "absint( \$_GET['post'] )"));
check('T-SAFE the only option written is the rewrite version', 1 === preg_match_all('/update_option\(/', $src) && false !== strpos($src, "update_option( 'omochix_core_glossary_rewrite_version'"));
check('T-SAFE every echo goes through esc_html', [] === preg_grep('/echo (?!\'<div class="notice notice-error|\'<li>|\'<\/ul><\/div>|\'<\/p><ul>)/', explode("\n", $src)) && false === strpos($src, 'echo $'));

// ============================================ T-LD-1 partial uploads
$TMP = sys_get_temp_dir() . '/omx-glossary-' . getmypid() . '-' . bin2hex(random_bytes(3));
mkdir($TMP, 0700, true);
$copy = static function ($name, $mutate) use ($TMP, $PLUGIN) {
    $d = "$TMP/$name";
    mkdir($d, 0700, true);
    exec('cp -R ' . escapeshellarg($PLUGIN) . '/. ' . escapeshellarg($d));
    $mutate($d);
    return $d;
};
$child = static function ($dir) {
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($dir) . ' 2>&1', $o, $code);
    $raw = implode("\n", $o); $p = strrpos($raw, '@@JSON@@');
    return [$code, false === $p ? null : json_decode(substr($raw, $p + 8), true), $raw];
};
[$code, $r] = $child($copy('full', static function ($d) {}));
check('T-LD-1 complete copy: loaded, hooks added, no output', 0 === $code && true === $r['loaded'] && [] !== $r['added'] && '' === $r['out'] && 0 === $r['errors']);
$scenarios = [
    'a file missing' => static function ($d) { unlink("$d/includes/web2/glossary-guard.php"); },
    'a file cut short (no end marker)' => static function ($d) { $f = "$d/includes/web2/glossary-visibility.php"; $c = file_get_contents($f); file_put_contents($f, substr($c, 0, (int) (strlen($c) * 0.7))); },
    'a file from another version' => static function ($d) { $f = "$d/includes/web2/glossary-meta.php"; file_put_contents($f, str_replace('omochix-glossary-version: 1', 'omochix-glossary-version: 2', file_get_contents($f))); },
    'a file replaced by a symbolic link' => static function ($d) { $f = "$d/includes/web2/glossary-caps.php"; rename($f, "$f.real"); symlink("$f.real", $f); },
    'a file that throws while loading' => static function ($d) { $f = "$d/includes/web2/glossary-guard.php"; file_put_contents($f, str_replace("add_action( 'admin_notices', 'omochix_core_glossary_hold_notice' );", "add_action( 'admin_notices', 'omochix_core_glossary_hold_notice' );\nthrow new RuntimeException( 'boom' );", file_get_contents($f))); },
    'a file that prints while loading' => static function ($d) { $f = "$d/includes/web2/glossary-caps.php"; file_put_contents($f, str_replace("add_filter( 'user_has_cap'", "echo 'leak';\nadd_filter( 'user_has_cap'", file_get_contents($f))); },
];
foreach ($scenarios as $name => $mutate) {
    [$code, $r, $raw] = $child($copy(md5($name), $mutate));
    $left = array_values(array_filter($r['added'] ?? ['?'], static fn($h) => 0 !== strpos($h, 'admin_notices ')));
    check("T-LD-1 $name: not loaded, no hook left but the notice, no output, no fatal error", 0 === $code && false === $r['loaded'] && [] === $left && '' === $r['out'] && '' === $r['exception'], $raw);
}
exec('rm -rf ' . escapeshellarg($TMP));

// ===================================== regression: Core alone unchanged
check('regression: Core does not load the glossary by itself (only the Web 2.0 loader may)', false === strpos((string) file_get_contents("$PLUGIN/omochix-core.php"), 'glossary'));
$added = [];
foreach (gl_hook_names() as $hook => $list) { foreach (array_diff($list, $core_hooks[$hook] ?? []) as $cb) { $added[] = $cb; } }
$removed = [];
foreach ($core_hooks as $hook => $list) { foreach (array_diff($list, gl_hook_names()[$hook] ?? []) as $cb) { $removed[] = "$hook $cb"; } }
check('regression: no existing Core callback was removed or replaced', [] === $removed, implode(', ', $removed));
check('regression: every callback the glossary added is its own (omochix_core_glossary_* or a test closure)', [] === array_filter($added, static fn($cb) => false === strpos($cb, ':omochix_core_glossary_') && false === strpos($cb, '{closure}')), implode(', ', $added));

ih_done();
