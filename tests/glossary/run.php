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
