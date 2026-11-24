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
$approve = static function ($pid, $creating = false) use ($full_meta, $basics_id) { return omochix_core_glossary_guard_rest((object) array_merge(['post_status' => 'publish', 'post_name' => 'test-term', 'post_title' => 'テスト用語', 'post_content' => gl_valid()['content_html']], $creating ? [] : ['ID' => $pid]), new GL_Req(['meta' => $full_meta, 'glossary-categories' => [$basics_id]])); };
$approve($rb);
$GLOBALS['posts'][$rb]->post_status = 'publish'; // The post is saved; its meta was not (WordPress refused or lost it).
do_action('rest_after_insert_omx_glossary', get_post($rb), new GL_Req(), false);
check('P1-4 read-back: a term this request made live that fails as stored goes back to draft, with the reasons kept', 'draft' === get_post_field('post_status', $rb) && [] !== preg_grep('/sources/', (array) get_transient('omochix_core_glossary_hold_' . $rb)));
$rb2 = $empty();
$approve($rb2);
$GLOBALS['posts'][$rb2]->post_status = 'publish';
foreach ($full_meta as $k => $val) { update_post_meta($rb2, $k, $val); }
wp_set_object_terms($rb2, ['basics'], 'omx_glossary_category');
do_action('rest_after_insert_omx_glossary', get_post($rb2), new GL_Req(), false);
check('P1-4 read-back: a term that is complete as stored stays published', 'publish' === get_post_field('post_status', $rb2));
apply_filters('rest_request_after_callbacks', null, null, null);
do_action('rest_after_insert_omx_glossary', get_post($live), new GL_Req(), false);
check('P1-4 read-back: a term that was live before (not made live by this request) is never demoted', 'publish' === get_post_field('post_status', $live));
$approve(0, true);
$new = ih_add_post(['post_type' => 'omx_glossary', 'post_status' => 'publish', 'post_name' => 'new-term', 'post_title' => '新しい用語', 'post_content' => gl_valid()['content_html']]);
do_action('rest_after_insert_omx_glossary', get_post($new), new GL_Req(), true);
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
do_action('rest_after_insert_omx_glossary', $obj, new GL_Req(), false);
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
check('P2-1 the default today is a date and is the Japan date now', 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/D', omochix_core_glossary_today()) && (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d') === omochix_core_glossary_today());
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
$t0 = microtime(true); $pr = omochix_core_glossary_html_problems(str_repeat($big_ok, 30)); $dt = microtime(true) - $t0;
check('P1-3 ordinary content near the size limit is read quickly and passes the HTML rules', [] === $pr && $dt < 2.0);
check('P3 (not changed here): a public name that resolves to an internal address is still accepted; no DNS lookup is made', '' === omochix_core_glossary_url_problem('https://127.0.0.1.nip.io/x') && 0 === preg_match('/gethostby|dns_get_record|checkdnsrr|wp_remote|curl_/', $core_src));

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
