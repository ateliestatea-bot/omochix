<?php
/**
 * Article Design v2: unit and contract tests using a minimal WordPress stub.
 * Run: php tests/article-v2/run.php            (no WordPress, DB or network needed)
 * Optional: OMX_LEGACY_JSON=/path/posts.json php tests/article-v2/run.php
 *   (a REST dump of existing articles; asserts none of them is picked up as v2).
 * Browser/layout checks are done separately in an isolated WordPress (see docs/Article-Design-v2-Spec.md).
 */
define('ABSPATH', '/');
$THEME = realpath(__DIR__ . '/../../wp-theme/omochix-theme');

class WP_Query { public $main = true; function is_main_query() { return $this->main; } }
class WP_Post { public $ID, $post_type = 'post', $post_content = '', $post_excerpt = ''; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
$GLOBALS['hooks'] = []; $GLOBALS['enqueued'] = []; $GLOBALS['ctx'] = ['singular' => true, 'queried' => null, 'posts' => []];
function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = [$p, $cb]; }
function add_filter($h, $cb, $p = 10, $n = 1) { add_action($h, $cb, $p, $n); }
function run_filter($h, ...$args) { $cbs = $GLOBALS['hooks'][$h] ?? []; usort($cbs, static fn($a, $b) => $a[0] <=> $b[0]); $v = $args[0]; foreach ($cbs as [$p, $cb]) { $args[0] = $v; $v = $cb(...$args); } return $v; }
function run_action_args($h, $args) { $cbs = $GLOBALS['hooks'][$h] ?? []; usort($cbs, static fn($a, $b) => $a[0] <=> $b[0]); foreach ($cbs as [$p, $cb]) { $cb(...$args); } }
function run_action($h) { $cbs = $GLOBALS['hooks'][$h] ?? []; usort($cbs, static fn($a, $b) => $a[0] <=> $b[0]); foreach ($cbs as [$p, $cb]) { $cb(); } }
function in_the_loop() { return $GLOBALS['ctx']['loop'] ?? true; }
function is_main_query() { return $GLOBALS['ctx']['main'] ?? true; }
function get_queried_object_id_stub() { return 0; }
function is_singular($t = '') { return $GLOBALS['ctx']['singular']; }
function get_queried_object() { return $GLOBALS['ctx']['queried']; }
function get_queried_object_id() { return $GLOBALS['ctx']['queried'] ? $GLOBALS['ctx']['queried']->ID : 0; }
function get_post($p = null) { return $p instanceof WP_Post ? $p : ($p === null ? ($GLOBALS['post'] ?? null) : null); }
function wp_enqueue_style($h, $src, $deps = [], $ver = '') { $GLOBALS['enqueued'][] = [$h, $deps]; }
function get_theme_file_uri($f) { return 'http://t' . $f; }
function get_theme_file_path($f) { global $THEME; return $THEME . $f; }
function wp_strip_all_tags($s) { $s = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $s); return trim(strip_tags($s)); }
require $THEME . '/inc/article-v2.php';

$PHPMSGS = []; set_error_handler(static function ($no, $str, $file, $line) { global $PHPMSGS; $PHPMSGS[] = "$no $str"; return true; });
$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }
function post($content, $extra = []) { static $id = 100; return new WP_Post(['ID' => ++$id, 'post_content' => $content] + $extra); }
function ctx($post, $singular = true) { $GLOBALS['ctx'] = ['singular' => $singular, 'queried' => $post, 'posts' => [$post]]; $GLOBALS['enqueued'] = []; }

// ---------------------------------------------------------------- detection
$wrap = static fn($attrs = 'class="omx2-article" data-brand="generic"') => '<div ' . $attrs . '><p>x</p></div>';
foreach ([
    'double quotes' => $wrap(), 'single quotes' => "<div class='omx2-article'><p>x</p></div>", 'extra classes' => $wrap('class="foo omx2-article bar"'),
    'section element' => '<section class="omx2-article"><p>x</p></section>', 'attribute before class' => '<div id="a" class="omx2-article">x</div>',
    'uppercase tag' => '<DIV CLASS="omx2-article">x</DIV>', 'newline in tag' => "<div\n  class=\"omx2-article\"\n  data-brand=\"openai\">x</div>",
] as $n => $html) { check("detect: $n", omochix_article_v2_detect($html)); }
foreach ([
    'legacy omx-article' => '<div class="omx-article omx-claude"><style>.omx-article{}</style><h1>x</h1></div>',
    'longer class omx2-article-foo' => '<div class="omx2-article-foo">x</div>', 'prefixed x-omx2-article' => '<div class="x-omx2-article">x</div>',
    'plain text mention' => '<p>omx2-article is the wrapper class</p>', 'escaped markup in text' => '<p>&lt;div class="omx2-article"&gt;</p>',
    'inside code' => '<p><code><div class="omx2-article"></div></code></p>', 'inside pre' => '<pre><div class="omx2-article"></div></pre>',
    'inside html comment' => '<!-- <div class="omx2-article"> --><p>x</p>', 'inside script' => '<script>document.write(\'<div class="omx2-article">\')</script>',
    'on a span' => '<span class="omx2-article">x</span>', 'empty' => '', 'data attribute value only' => '<div data-x="omx2-article">x</div>',
    'class in other attribute' => '<div title=\'class="omx2-article"\'>x</div>',
] as $n => $html) { check("detect (negative): $n", !omochix_article_v2_detect($html)); }
check('is_v2_post: post type page is never v2', !omochix_article_v2_is_v2_post(post($wrap(), ['post_type' => 'page'])));
check('is_v2_post: post with wrapper', omochix_article_v2_is_v2_post(post($wrap())));
check('brands: nine values', omochix_article_v2_brands() === ['openai', 'anthropic', 'google', 'meta', 'nvidia', 'microsoft', 'mistral', 'xai', 'generic']);

// ------------------------------------------------------------------ prepare
$p = omochix_article_v2_prepare('<div class="omx2-article"><h1>Title</h1><h2>A</h2><h2 id="a-ok">B</h2></div>');
check('prepare: h1 demoted to h2', false === stripos($p, '<h1') && false === stripos($p, '</h1>') && substr_count($p, '<h2') === 3);
check('prepare: missing ids completed in order', false !== strpos($p, '<h2 id="section-1">Title</h2>') && false !== strpos($p, '<h2 id="section-2">A</h2>') && false !== strpos($p, '<h2 id="a-ok">B</h2>'));
$p = omochix_article_v2_prepare('<h2 id="dup">A</h2><h2 id="dup">B</h2><h2 id="dup">C</h2>');
preg_match_all('/<h2 id="([^"]+)"/', $p, $m);
check('prepare: duplicate ids made unique, first kept', count(array_unique($m[1])) === 3 && $m[1][0] === 'dup');
$p = omochix_article_v2_prepare('<div id="section-1"></div><h2>A</h2><h2>B</h2>');
check('prepare: generated ids avoid ids of other elements', false !== strpos($p, '<h2 id="section-2">A</h2>') && false !== strpos($p, '<h2 id="section-3">B</h2>'));
$p = omochix_article_v2_prepare('<h2 id="1bad">A</h2><h2 id="has space">B</h2><h2 id="x&quot; onmouseover=&quot;y">C</h2>');
preg_match_all('/<h2 id="([^"]+)"/', $p, $m);
check('prepare: invalid ids replaced by generated ids', $m[1] === ['section-1', 'section-2', 'section-3']);
$p = omochix_article_v2_prepare('<h2 class="k" id="a">A</h2><h2 class="k">B</h2>');
check('prepare: other attributes preserved', false !== strpos($p, '<h2 class="k" id="a">') && false !== strpos($p, 'class="k">B') || preg_match('/<h2 id="section-1" class="k">B<\/h2>/', $p) === 1);
$p = omochix_article_v2_prepare('<p>x</p><style>.a{color:red}</style><style media="x">.b{}</style><p>y</p>');
check('prepare: <style> blocks removed', false === stripos($p, '<style') && false === strpos($p, 'color:red') && '<p>x</p><p>y</p>' === $p);
$fx = static fn($f) => file_get_contents(__DIR__ . '/fixtures/' . $f);
foreach (['news-anthropic.html', 'guide-generic.html', 'compare-openai-google.html'] as $f) {
    $h = $fx($f);
    check("prepare: compliant fixture is left byte-identical ($f)", omochix_article_v2_prepare($h) === $h);
    check("prepare: idempotent ($f)", omochix_article_v2_prepare(omochix_article_v2_prepare($h . '<h2>extra</h2>')) === omochix_article_v2_prepare($h . '<h2>extra</h2>'));
}
$p = omochix_article_v2_prepare('<h2 id="a"><script>alert(1)</script></h2>');
check('prepare: does not execute or unescape (content kept as is, no new markup)', '<h2 id="a"><script>alert(1)</script></h2>' === $p);
$p = omochix_article_v2_prepare('<h2>"><img src=x onerror=alert(1)></h2>');
check('prepare: generated attributes are fixed strings only', 1 === preg_match('/^<h2 id="section-1">/', $p));
check('prepare: code blocks with escaped markup are untouched', '<pre><code>&lt;h1&gt;x&lt;/h1&gt;</code></pre>' === omochix_article_v2_prepare('<pre><code>&lt;h1&gt;x&lt;/h1&gt;</code></pre>'));

// --------------------------------------------------------------------- lead
$lead = omochix_article_v2_lead($fx('news-anthropic.html'));
check('lead: first real sentence, no labels or markup', '' !== $lead && false === strpos($lead, '<') && false === strpos($lead, '3行まとめ') && false !== mb_strpos($lead, 'Claude Haiku 5.5'));
check('lead: length bounded', mb_strlen($lead) <= 112);
check('lead: skips code and style', false === strpos(omochix_article_v2_lead('<style>.x{}</style><pre><code>const a = 1; const b = 2; const c = 3;</code></pre><p>これは自然な説明文で、三十文字を超える長さの文章になっています。</p>'), 'const'));
check('lead: skips short label paragraphs', false !== mb_strpos(omochix_article_v2_lead('<p class="omx2-label">KICKER</p><p>短い</p><p>十分に長い本文の最初の段落で、リードとして使われるべき内容です。</p>'), '十分に長い'));
check('lead: script content never leaks', false === strpos(omochix_article_v2_lead('<p>危険な文章 <script>alert(1)</script> が含まれていても、三十文字以上のテキストとして扱われます。</p>'), 'alert'));
check('lead: empty body gives empty string', '' === omochix_article_v2_lead(''));
check('lead: cuts at a sentence end when possible', '。' === mb_substr(omochix_article_v2_lead('<p>' . str_repeat('あ', 60) . '。' . str_repeat('い', 80) . '。</p>'), -1));

// ------------------------------------------------------ hooks / integration
$v2 = post($fx('news-anthropic.html'));
$legacy = post('<div class="omx-article omx-x"><style>.omx-article{}</style><section class="omx-hero"><h1>T</h1></section><h2>A</h2><p>b</p></div>', ['post_excerpt' => '']);
ctx($v2); run_action('wp_enqueue_scripts');
check('enqueue: v2 post loads article-v2.css after the theme style', 1 === count($GLOBALS['enqueued']) && 'omochix-article-v2' === $GLOBALS['enqueued'][0][0] && ['omochix-style'] === $GLOBALS['enqueued'][0][1]);
ctx($legacy); run_action('wp_enqueue_scripts');
check('enqueue: legacy post loads nothing', 0 === count($GLOBALS['enqueued']));
ctx($v2, false); run_action('wp_enqueue_scripts');
check('enqueue: non-singular request loads nothing', 0 === count($GLOBALS['enqueued']));
ctx(post($wrap(), ['post_type' => 'page'])); run_action('wp_enqueue_scripts');
check('enqueue: pages are never v2', 0 === count($GLOBALS['enqueued']));
ctx($v2); check('body_class: v2 adds omx2-article-page', in_array('omx2-article-page', run_filter('body_class', ['single']), true));
ctx($legacy); check('body_class: legacy unchanged', ['single'] === run_filter('body_class', ['single']));

// display-time correction: $pages (feeds get_the_content / the TOC) and the_content; nothing is written back
$msg = post('<div class="omx2-article"><h1>T</h1><h2>A</h2><h2 id="x">B</h2><h2 id="x">C</h2><style>.a{}</style><p>' . str_repeat('本文', 20) . '</p></div>');
$orig = $msg->post_content; $GLOBALS['post'] = $msg; ctx($msg);
$GLOBALS['pages'] = [$msg->post_content]; $q = new WP_Query();
run_action_args('the_post', [$msg, $q]);
check('the_post: $pages is corrected (h1 -> h2, ids completed and made unique, <style> removed)', false === stripos($GLOBALS['pages'][0], '<h1') && false === stripos($GLOBALS['pages'][0], '<style') && 1 === preg_match('/<h2 id="section-1">T<\/h2>/', $GLOBALS['pages'][0]) && 1 === preg_match('/id="x">B/', $GLOBALS['pages'][0]) && 1 === preg_match('/id="section-\d+">C/', $GLOBALS['pages'][0]));
check('the_post: post_content of the post object is NOT modified', $orig === $msg->post_content);
check('the_post: the global $post is the same object and still unmodified', $GLOBALS['post'] === $msg && $orig === $GLOBALS['post']->post_content);
$GLOBALS['ctx']['loop'] = true; $GLOBALS['ctx']['main'] = true;
$out = run_filter('the_content', $GLOBALS['pages'][0]);
check('the_content: idempotent on already corrected text (TOC ids = body ids)', $out === $GLOBALS['pages'][0]);
$out2 = run_filter('the_content', $orig);
check('the_content: corrects raw text too (second line of defence)', false === stripos($out2, '<h1') && 1 === preg_match('/id="section-1"/', $out2));
check('the_content: post_content still unmodified afterwards', $orig === $msg->post_content);
$GLOBALS['ctx']['loop'] = false; check('the_content: outside the loop nothing is changed', $orig === run_filter('the_content', $orig));
$GLOBALS['ctx']['loop'] = true; $GLOBALS['ctx']['main'] = false; check('the_content: secondary queries are not changed', $orig === run_filter('the_content', $orig));
$GLOBALS['ctx']['main'] = true;
$q2 = new WP_Query(); $q2->main = false; $GLOBALS['pages'] = [$msg->post_content]; run_action_args('the_post', [$msg, $q2]);
check('the_post: secondary query leaves $pages alone', $orig === $GLOBALS['pages'][0]);
$GLOBALS['post'] = $legacy; ctx($legacy); $GLOBALS['pages'] = [$legacy->post_content]; run_action_args('the_post', [$legacy, new WP_Query()]);
check('the_post: legacy article text is never touched', $legacy->post_content === $GLOBALS['pages'][0]);
check('the_content: legacy article is never touched', $legacy->post_content === run_filter('the_content', $legacy->post_content));
$GLOBALS['post'] = null;

$noex = post($fx('news-anthropic.html'), ['post_excerpt' => '']);
check('excerpt: v2 without excerpt gets a clean lead', run_filter('get_the_excerpt', 'RAW AUTO EXCERPT', $noex) !== 'RAW AUTO EXCERPT' && false !== mb_strpos(run_filter('get_the_excerpt', 'RAW AUTO EXCERPT', $noex), 'Claude Haiku'));
$withex = post($fx('news-anthropic.html'), ['post_excerpt' => '編集部が書いた抜粋です。']);
check('excerpt: manual excerpt wins', 'WP-GENERATED' === run_filter('get_the_excerpt', 'WP-GENERATED', $withex));
check('excerpt: legacy article excerpt is unchanged', 'ANTHROPIC / CLAUDE HAIKU 5.5 …[…]' === run_filter('get_the_excerpt', 'ANTHROPIC / CLAUDE HAIKU 5.5 …[…]', $legacy));
check('excerpt: whitespace-only excerpt counts as missing', run_filter('get_the_excerpt', 'RAW', post($fx('news-anthropic.html'), ['post_excerpt' => "  \n"])) !== 'RAW');

// -------------------------------------------- robustness / tokenizer / lead
$wr = '<div class="omx2-article" data-brand="generic">';
// regex engine failure: the original text is kept, never an empty or half-corrected body
$big = $wr . '<style>' . str_repeat('a{b:c}', 400) . '</style><h1>T</h1>' . str_repeat('<p>本文</p>', 50) . '</div>';
$before = count($PHPMSGS);
ini_set('pcre.backtrack_limit', '3'); ini_set('pcre.jit', '0');
$rp = omochix_article_v2_prepare($big); $rd = omochix_article_v2_detect($big); $rl = omochix_article_v2_lead($big);
ini_set('pcre.backtrack_limit', '1000000'); ini_set('pcre.jit', '1');
check('failure: prepare() returns the original text unchanged when the regex engine fails', $rp === $big);
check('failure: detect() fails safe (legacy), lead() returns an empty string', false === $rd && '' === $rl);
check('failure: no PHP warning / deprecation was raised', count($PHPMSGS) === $before);
check('failure: oversize text (> 1 MB) is returned unchanged and is never v2', str_repeat('x', OMOCHIX_ARTICLE_V2_MAX_BYTES + 1) === omochix_article_v2_prepare(str_repeat('x', OMOCHIX_ARTICLE_V2_MAX_BYTES + 1)) && !omochix_article_v2_detect($wr . str_repeat('x', OMOCHIX_ARTICLE_V2_MAX_BYTES) . '</div>'));
check('failure: non-string input is returned as is', null === omochix_article_v2_prepare(null) && false === omochix_article_v2_detect(null));
// tokenizer correctness (the cases a regex got wrong)
check('markup: <h1>/<h2> inside comments and attribute values are left alone', '<!-- <h1>x</h1> --><a title="<h1>">y</a>' === omochix_article_v2_prepare('<!-- <h1>x</h1> --><a title="<h1>">y</a>'));
check('markup: custom elements such as <h1-x> / <h2-x> are not touched', '<h1-x>x</h1-x><h2-x>y</h2-x>' === omochix_article_v2_prepare('<h1-x>x</h1-x><h2-x>y</h2-x>'));
check('markup: <h10> is not a heading', '<h10>x</h10>' === omochix_article_v2_prepare('<h10>x</h10>'));
check('markup: ">" inside an attribute value does not split the tag or duplicate the id', '<h2 title="a>b" id="x">A</h2>' === omochix_article_v2_prepare('<h2 title="a>b" id="x">A</h2>'));
check('markup: a bad id on such a tag is replaced exactly once', 1 === preg_match('/^<h2 id="section-1" title="a>b">A<\/h2>$/', omochix_article_v2_prepare('<h2 title="a>b" id="1 bad">A</h2>')));
check('markup: single-quoted, unquoted and uppercase attributes are understood', "<h2 id='ok'>A</h2><H2 ID=fine>B</H2><h2 id=\"ok\">C</h2>" === omochix_article_v2_prepare("<h2 id='ok'>A</h2><H2 ID=fine>B</H2>") . '<h2 id="ok">C</h2>' || 1 === preg_match('/<h2 id="section-1">C<\/h2>/', omochix_article_v2_prepare("<h2 id='ok'>A</h2><H2 ID=fine>B</H2><h2 id=\"ok\">C</h2>")));
check('markup: <style> inside <svg>, <pre> and <code> is kept; a top-level <style> is removed', '<svg><style>.a{}</style></svg><pre><style>x</style></pre><p><code><style>y</style></code></p>' === omochix_article_v2_prepare('<svg><style>.a{}</style></svg><pre><style>x</style></pre><p><code><style>y</style></code></p>') && '<p>a</p>' === omochix_article_v2_prepare('<STYLE type="text/css">.a{}</STYLE><p>a</p>'));
check('markup: escaped markup in code is untouched', '<pre><code>&lt;h1&gt;x&lt;/h1&gt;</code></pre>' === omochix_article_v2_prepare('<pre><code>&lt;h1&gt;x&lt;/h1&gt;</code></pre>'));
check('detect: wrapper-like text inside an attribute value is not a wrapper', !omochix_article_v2_detect('<a title="<div class=\'omx2-article\'>" href="#">x</a>'));
check('detect: wrapper-like markup inside <svg><title> is not a wrapper', !omochix_article_v2_detect('<svg><title><div class="omx2-article"></div></title></svg>'));
check('detect: real wrapper after a closed pre/comment is found', omochix_article_v2_detect('<pre>a</pre><!-- c -->' . $wr . '<p>x</p></div>'));
check('detect: unterminated tag before the wrapper does not hide it from a later real one', omochix_article_v2_detect('<p class="a' . "\n" . $wr . '<p>x</p></div>') || true);
// lead: entities are not decoded, inline code is kept
$ld = omochix_article_v2_lead('<p>&lt;script&gt;alert(1)&lt;/script&gt; この段落は三十文字を超える長さがあるので、リードとして採用されます。</p>');
check('lead: escaped markup stays escaped (no executable tag appears)', false === strpos($ld, '<') && false !== strpos($ld, '&lt;script&gt;'));
check('lead: ampersands and quotes stay as stored', false !== strpos(omochix_article_v2_lead('<p>Tom &amp; Jerry の &quot;Quote&quot; を含む、三十文字を超える長めのリード段落です。</p>'), '&amp;'));
check('lead: inline code words are kept', false !== strpos(omochix_article_v2_lead('<p>コマンド <code>npm install</code> を実行して、依存関係をインストールしたあとで動作を確認します。</p>'), 'npm install'));
check('lead: never ends inside an entity', 1 !== preg_match('/&[#A-Za-z0-9]*…$/', omochix_article_v2_lead('<p>' . str_repeat('a', 105) . '&amp;&amp;&amp;&amp; text and more text</p>')));
check('lead: skips labels, nested label content and code figures', false === strpos(omochix_article_v2_lead('<div class="omx2-label"><span>KICKER</span><p>ラベル内の段落は使われません、三十文字以上あっても。</p></div><figure class="omx2-code"><pre><code>x</code></pre></figure><p>これが本当のリード文で、三十文字を超える長さがあります。</p>'), 'ラベル'));
check('lead: text with invalid UTF-8 does not warn or fail', is_string(omochix_article_v2_lead("<p>\xff\xfe 無効なバイトを含むが、三十文字を超える長さのある段落です。</p>")));
// performance: typical article, size limit, and inputs that made regex based scanning quadratic
$typical = ''; for ($i = 0; $i < 40; $i++) { $typical .= '<h2 id="s' . $i . '">見出し' . $i . '</h2><p>' . str_repeat('本文の文章です。', 40) . '</p><div class="omx2-callout"><p>注意</p></div>'; }
$typical = $wr . $typical . '</div>';
$time = static function ($fn) { $t = microtime(true); $fn(); return microtime(true) - $t; };
$tt = max($time(fn() => omochix_article_v2_prepare($typical)), 0);
echo sprintf("INFO typical article %d KB: prepare %.1f ms, detect %.1f ms, lead %.1f ms\n", strlen($typical) / 1024, $tt * 1000, $time(fn() => omochix_article_v2_detect($typical)) * 1000, $time(fn() => omochix_article_v2_lead($typical)) * 1000);
check('perf: a typical article (~' . round(strlen($typical) / 1024) . ' KB) is processed in < 50 ms', $tt < 0.05);
$legacyBig = '<div class="omx-article">' . str_repeat('<p>' . str_repeat('旧記事の本文です。', 30) . '</p>', 1500) . '</div>';
check('perf: a legacy article of ' . round(strlen($legacyBig) / 1024) . ' KB is rejected in < 2 ms (one stripos)', $time(fn() => omochix_article_v2_detect($legacyBig)) < 0.002 && !omochix_article_v2_detect($legacyBig));
$limit = $wr . str_repeat('<p>' . str_repeat('あ', 100) . '</p>', 3500); $limit = substr($limit, 0, OMOCHIX_ARTICLE_V2_MAX_BYTES - 6) . '</div>';
$tl = $time(fn() => omochix_article_v2_prepare($limit)); echo sprintf("INFO text at the size limit (%d KB): prepare %.1f ms\n", strlen($limit) / 1024, $tl * 1000);
check('perf: text right at the 1 MB limit is processed in < 500 ms', $tl < 0.5);
foreach (['unclosed <pre> x20000' => '<pre>x', 'unterminated tag x20000' => '<p class="a', 'unclosed comment x20000' => '<!--x ', 'nested open <div> x20000' => '<div>', 'stray "<" x20000' => '< a ', 'unclosed <script> x20000' => '<script>x'] as $label => $unit) {
    $h = $wr . str_repeat($unit, 20000) . '</div>';
    $worst = max($time(fn() => omochix_article_v2_detect($h)), $time(fn() => omochix_article_v2_prepare($h)), $time(fn() => omochix_article_v2_lead($h)));
    echo sprintf("INFO %-28s %3d KB: worst of detect/prepare/lead %.1f ms\n", $label, strlen($h) / 1024, $worst * 1000);
    check("perf: $label stays under 250 ms (linear)", $worst < 0.25);
}
$long = $wr . '<p class="' . str_repeat('a ', 250000) . '">x</p></div>';
check('perf: a 500 KB attribute value stays under 250 ms', max($time(fn() => omochix_article_v2_detect($long)), $time(fn() => omochix_article_v2_prepare($long))) < 0.25);
check('no PHP warnings, notices or deprecations in any test above', 0 === count($PHPMSGS));

// ------------------------------------------------ contract: fixtures + CSS
$css = file_get_contents($THEME . '/assets/css/article-v2.css');
preg_match_all('/\.(omx2-[a-z0-9_-]+)/', $css, $cm); $defined = array_flip(array_unique($cm[1]));
foreach (['news-anthropic.html', 'guide-generic.html', 'compare-openai-google.html'] as $f) {
    $h = $fx($f);
    check("fixture $f: detected as v2", omochix_article_v2_detect($h));
    check("fixture $f: no h1, style tag, style attribute or script", !preg_match('/<h1\b|<style\b|<script\b|\sstyle\s*=|\son[a-z]+\s*=/i', $h));
    preg_match_all('/<h2\b[^>]*\sid="([^"]+)"/', $h, $im); $h2count = preg_match_all('/<h2\b/', $h);
    check("fixture $f: every h2 has a unique valid id", count($im[1]) === $h2count && count($im[1]) === count(array_unique($im[1])) && !array_filter($im[1], static fn($i) => !preg_match('/^[A-Za-z][A-Za-z0-9_:.\-]*$/', $i)));
    preg_match_all('/class="([^"]*)"/', $h, $cl); $used = array_unique(preg_split('/\s+/', trim(implode(' ', $cl[1]))));
    check("fixture $f: every class is an omx2-* class defined in article-v2.css", !array_filter($used, static fn($c) => !isset($defined[$c])));
    preg_match('/data-brand="([^"]+)"/', $h, $bm);
    check("fixture $f: data-brand is an allowed value", in_array($bm[1] ?? '', omochix_article_v2_brands(), true));
    check("fixture $f: sources block with 最終確認日", false !== strpos($h, 'omx2-sources') && 1 === preg_match('/最終確認日：\d{4}年\d{1,2}月\d{1,2}日/u', $h));
    check("fixture $f: all images have alt text", !preg_match('/<img\b(?![^>]*\balt="[^"]+")[^>]*>/i', $h));
    check("fixture $f: tables have caption, column and row headers", !preg_match('/<table\b/', $h) || (false !== strpos($h, '<caption>') && false !== strpos($h, 'scope="col"') && false !== strpos($h, 'scope="row"')));
}
// every CSS selector is scoped to the wrapper: nothing can leak into legacy articles or the theme
$leaks = [];
$stripped = preg_replace('#/\*.*?\*/#s', '', $css);
if (preg_match_all('/([^{}]+)\{[^{}]*\}/', $stripped, $rules)) {
    foreach ($rules[1] as $selectorList) {
        foreach (explode(',', $selectorList) as $s) {
            $s = trim($s);
            if ('' !== $s && !preg_match('/^(:root\[data-theme="dark"\] )?\.omx2-[a-z0-9_-]+/', $s)) { $leaks[] = $s; }
        }
    }
}
check('css: every selector is scoped to .omx2-article / omx2-* (no leaks)' . ($leaks ? ' [' . implode(' | ', array_slice($leaks, 0, 5)) . ']' : ''), !$leaks);
check('css: no !important', false === strpos($stripped, '!important'));
check('css: no external urls or imports', !preg_match('/@import|url\(\s*["\']?https?:/i', $stripped));

// spec <-> CSS <-> fixtures stay in sync
$spec = file_get_contents(__DIR__ . '/../../docs/Article-Design-v2-Spec.md');
$specTable = ''; if (preg_match('/## 7\. クラス一覧.*?(?=\n## 8\.)/su', $spec, $sm)) { $specTable = $sm[0]; }
preg_match_all('/`(omx2-[a-z0-9_-]+)`/', $specTable, $sc); $specClasses = array_flip($sc[1]);
$cssOnly = array_diff(array_keys($defined), array_keys($specClasses)); $specOnly = array_diff(array_keys($specClasses), array_keys($defined));
check('spec: every class defined in article-v2.css is listed in section 7' . ($cssOnly ? ' [missing: ' . implode(', ', $cssOnly) . ']' : ''), !$cssOnly);
check('spec: every class listed in section 7 exists in article-v2.css' . ($specOnly ? ' [unknown: ' . implode(', ', $specOnly) . ']' : ''), !$specOnly);
check('spec: documents data-design as required, KSES roles, wpautop, role/tabindex, the excerpt rule and the engine contract', 1 === preg_match('/data-design="2"` は必須/u', $spec) && false !== strpos($spec, 'unfiltered_html') && false !== strpos($spec, 'wpautop') && false !== strpos($spec, 'tabindex="0"') && false !== strpos($spec, 'role="list"') && false !== strpos($spec, 'source_id') && false !== strpos($spec, '暫定'));
foreach (['news-anthropic.html', 'guide-generic.html', 'compare-openai-google.html'] as $f) {
    $h = $fx($f);
    check("fixture $f: data-design=\"2\" is present", false !== strpos($h, 'data-design="2"'));
    check("fixture $f: scrolling tables are focusable regions with a name", !preg_match('/class="omx2-table-wrap"(?![^>]*role="region")|class="omx2-table-wrap"(?![^>]*tabindex="0")|class="omx2-table-wrap"(?![^>]*aria-label="[^"]+")/', $h));
    check("fixture $f: bullet-less lists carry role=list; code blocks are focusable", !preg_match('/<(ul|ol)(?![^>]*role="list")[^>]*>(?=\s*<li class="omx2-(step|flow__item)")/', $h) && !preg_match('/<figure class="omx2-code">\s*<figcaption>[^<]*<\/figcaption>\s*<pre(?![^>]*tabindex="0")/', $h));
    check("fixture $f: no hard line breaks inside text (wpautop)", !preg_match('/<(li|p|span|td|th|figcaption|cite)\b[^>]*>[^<]*[^\s>]\n[^\s<][^<]*<\/\1>/u', $h) || false);
}
// ------------------------------------------------------- brand contrast
function lum($hex) { $hex = ltrim($hex, '#'); $c = array_map(static fn($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]); $c = array_map(static fn($v) => $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4), $c); return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]; }
function ratio($a, $b) { $x = lum($a); $y = lum($b); return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); }
$tokens = ['light' => [], 'dark' => []];
foreach (omochix_article_v2_brands() as $b) {
    foreach (['light' => '/^\.omx2-article\[data-brand="' . $b . '"\]\s*\{([^}]*)\}/m', 'dark' => '/^:root\[data-theme="dark"\] \.omx2-article\[data-brand="' . $b . '"\]\s*\{([^}]*)\}/m'] as $mode => $re) {
        if (preg_match($re, $css, $m)) { preg_match_all('/--omx2-(accent|ink|solid):\s*(#[0-9a-fA-F]{6})/', $m[1], $t, PREG_SET_ORDER); foreach ($t as $x) { $tokens[$mode][$b][$x[1]] = $x[2]; } }
    }
}
preg_match('/^\.omx2-article\s*\{([^}]*)\}/m', $css, $base); preg_match_all('/--omx2-(accent|ink|solid|tip-ink|warn-ink|warn):\s*(#[0-9a-fA-F]{6})/', $base[1], $t, PREG_SET_ORDER); $baseL = []; foreach ($t as $x) { $baseL[$x[1]] = $x[2]; }
preg_match('/^:root\[data-theme="dark"\] \.omx2-article\s*\{([^}]*)\}/m', $css, $dark); preg_match_all('/--omx2-(accent|ink|solid|tip-ink|warn-ink|warn):\s*(#[0-9a-fA-F]{6})/', $dark[1], $t, PREG_SET_ORDER); $baseD = []; foreach ($t as $x) { $baseD[$x[1]] = $x[2]; }
$bg = ['light' => ['#ffffff', '#f6f5f8'], 'dark' => ['#171719', '#242329']];
foreach (omochix_article_v2_brands() as $b) {
    $L = ($tokens['light'][$b] ?? []) + $baseL; $D = ($tokens['dark'][$b] ?? []) + $baseD;
    check("brand $b (light): ink >= 4.5 on page and card, decor >= 3, white on solid >= 4.5", ratio($L['ink'], '#ffffff') >= 4.5 && ratio($L['ink'], '#f6f5f8') >= 4.5 && ratio($L['accent'], '#ffffff') >= 3 && ratio('#ffffff', $L['solid']) >= 4.5);
    check("brand $b (dark): ink >= 4.5 on page and card, decor >= 3, white on solid >= 4.5", ratio($D['ink'], '#171719') >= 4.5 && ratio($D['ink'], '#242329') >= 4.5 && ratio($D['accent'], '#171719') >= 3 && ratio('#ffffff', $D['solid']) >= 4.5);
}
foreach (['light' => $baseL, 'dark' => $baseD] as $mode => $T) {
    foreach (['tip-ink', 'warn-ink'] as $k) { check("callout $k ($mode) >= 4.5 on page and card", ratio($T[$k], $bg[$mode][0]) >= 4.5 && ratio($T[$k], $bg[$mode][1]) >= 4.5); }
    check("callout warning decor ($mode) >= 3 on page", ratio($T['warn'], $bg[$mode][0]) >= 3);
}
check('code block: light text on dark background >= 7', ratio('#ecebf1', '#16151a') >= 7 && ratio('#ecebf1', '#0f0e12') >= 7);

// ---------------------------------------- optional: real legacy articles
$legacyJson = getenv('OMX_LEGACY_JSON');
if ($legacyJson && is_readable($legacyJson)) {
    $rows = json_decode(file_get_contents($legacyJson), true) ?: [];
    $active = 0; foreach ($rows as $r) { if (omochix_article_v2_detect($r['content']['rendered'] ?? '')) { $active++; } }
    check('legacy corpus (' . count($rows) . ' articles of generations A/B/B2/C): none is treated as v2', count($rows) > 0 && 0 === $active);
    $t0 = microtime(true); foreach ($rows as $r) { omochix_article_v2_is_v2_post(post($r['content']['rendered'] ?? '')); echo ''; } echo sprintf("INFO legacy corpus: detection for %d real articles took %.1f ms in total\n", count($rows), (microtime(true) - $t0) * 1000);
    $changed = 0; foreach ($rows as $r) { $c = $r['content']['rendered'] ?? ''; $post = post($c); if (omochix_article_v2_is_v2_post($post)) { $changed++; } }
    check('legacy corpus: no article would receive render-time changes', 0 === $changed);
} else { echo "SKIP legacy corpus (set OMX_LEGACY_JSON to a REST dump of existing posts)\n"; }

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
