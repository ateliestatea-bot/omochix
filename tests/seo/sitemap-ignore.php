<?php
/**
 * SEO: Slim SEO sitemap exclusion of posts whose Slim SEO canonical names another URL
 * (omochix_sitemap_ignore_non_self_canonical() in inc/seo.php) and of retired posts pinned by ID and slug
 * (omochix_sitemap_ignore_retired_post()), run through the registered 'slim_seo_sitemap_post_ignore'
 * callbacks exactly as Slim SEO calls them: ($ignore, WP_Post). Also checks that the theme 1.32.1
 * release plan (archived: tools/release/configs/theme-1.32.1.json and tests/release/expected/theme-1.32.1.json) matches the rule.
 * Run: php tests/seo/sitemap-ignore.php
 */
define('ABSPATH', __DIR__ . '/');
$THEME = realpath(__DIR__ . '/../../wp-theme/omochix-theme');

class WP_Post { public $ID = 0, $post_type = 'post', $post_status = 'publish', $post_name = ''; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
class WP_Term {}
$GLOBALS['filters'] = [];
function add_filter($hook, $cb, $prio = 10, $args = 1) { $GLOBALS['filters'][$hook][] = $cb; }
function add_action($hook, $cb, $prio = 10, $args = 1) {}
function apply_sitemap_ignore($ignore, $post) { foreach ($GLOBALS['filters']['slim_seo_sitemap_post_ignore'] ?? [] as $cb) { $ignore = $cb($ignore, $post); } return $ignore; }
function home_url($path = '') { return 'https://example.test' . ('' === $path ? '' : '/' . ltrim($path, '/')); }
function untrailingslashit($s) { return rtrim($s, '/\\'); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function get_permalink($post) { $p = $post instanceof WP_Post ? $post : get_post($post); return $p ? home_url($p->post_name . '/') : false; }
function get_post($id) { return $GLOBALS['posts'][(int) $id] ?? null; }
function get_post_meta($id, $key, $single = false) { return $GLOBALS['meta'][(int) $id][$key] ?? ''; }

set_error_handler(static function ($no, $str, $file, $line) { throw new ErrorException($str, 0, $no, $file, $line); });
require $THEME . '/inc/seo.php';

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }
function post($id, $slug, $canonical = null) {
    $GLOBALS['posts'][$id] = new WP_Post(['ID' => $id, 'post_name' => $slug]);
    if (null !== $canonical) { $GLOBALS['meta'][$id]['slim_seo'] = $canonical; }
    return $GLOBALS['posts'][$id];
}

check('hooked on slim_seo_sitemap_post_ignore', in_array('omochix_sitemap_ignore_non_self_canonical', $GLOBALS['filters']['slim_seo_sitemap_post_ignore'] ?? [], true));

// T-U1: no manual canonical -> listed.
check('T-U1 no slim_seo meta: listed', false === apply_sitemap_ignore(false, post(1, 'normal-post')));
check('T-U1 slim_seo meta without canonical: listed', false === apply_sitemap_ignore(false, post(2, 'desc-only', ['description' => 'x'])));
check('T-U1 empty canonical: listed', false === apply_sitemap_ignore(false, post(3, 'empty-canonical', ['canonical' => '  '])));

// T-U2: canonical is the post's own permalink, written differently -> listed.
check('T-U2 identical: listed', false === apply_sitemap_ignore(false, post(10, 'self', ['canonical' => 'https://example.test/self/'])));
check('T-U2 no trailing slash: listed', false === apply_sitemap_ignore(false, post(11, 'self2', ['canonical' => 'https://example.test/self2'])));
check('T-U2 upper-case scheme/host, http, fragment: listed', false === apply_sitemap_ignore(false, post(12, 'self3', ['canonical' => 'HTTP://EXAMPLE.TEST/self3/#top'])));
check('T-U2 relative own path: listed', false === apply_sitemap_ignore(false, post(13, 'self4', ['canonical' => '/self4/'])));

// T-U3: another internal URL (the RTX case) -> left out.
check('T-U3 other internal post: left out', true === apply_sitemap_ignore(false, post(20, 'nvidia-rtx-spark-launch-announcement', ['canonical' => 'https://example.test/microsoft-nvidia-rtx-spark-local-ai-windows-2026/'])));
check('T-U3 relative other path: left out', true === apply_sitemap_ignore(false, post(21, 'old', ['canonical' => '/new/'])));
check('T-U3 same path with a query: left out', true === apply_sitemap_ignore(false, post(22, 'q', ['canonical' => 'https://example.test/q/?ref=1'])));
check('T-U3 path case differs: left out (paths are case-sensitive)', true === apply_sitemap_ignore(false, post(23, 'case', ['canonical' => 'https://example.test/CASE/'])));

// T-U4: external domain -> left out.
check('T-U4 external domain: left out', true === apply_sitemap_ignore(false, post(30, 'ext', ['canonical' => 'https://other.example/ext/'])));
check('T-U4 protocol-relative external: left out', true === apply_sitemap_ignore(false, post(31, 'ext2', ['canonical' => '//other.example/ext2/'])));
check('T-U4 same host other port: left out', true === apply_sitemap_ignore(false, post(32, 'port', ['canonical' => 'https://example.test:8443/port/'])));

// T-U5: another filter already left the post out -> stays out.
check('T-U5 ignore=true kept (no canonical)', true === apply_sitemap_ignore(true, post(40, 'kept')));
check('T-U5 ignore=true kept (self canonical)', true === apply_sitemap_ignore(true, post(41, 'kept2', ['canonical' => 'https://example.test/kept2/'])));

// T-U6: odd arguments and odd meta -> unchanged, no warning (the error handler turns warnings into failures).
$odd = static function ($ignore, $p) { try { return apply_sitemap_ignore($ignore, $p); } catch (Throwable $e) { return 'ERR ' . $e->getMessage(); } };
check('T-U6 post as int id resolves', true === $odd(false, (post(50, 'by-id', ['canonical' => 'https://example.test/else/']))->ID));
check('T-U6 unknown int id: unchanged', false === $odd(false, 999));
check('T-U6 null: unchanged', false === $odd(false, null));
check('T-U6 string garbage: unchanged', false === $odd(false, 'abc'));
check('T-U6 meta not an array: unchanged', false === $odd(false, post(51, 'str-meta', 'canonical=https://x/')));
check('T-U6 canonical not a string: unchanged', false === $odd(false, post(52, 'arr-canon', ['canonical' => ['https://x/']])));
check('T-U6 canonical not a URL: unchanged', false === $odd(false, post(53, 'bad-canon', ['canonical' => 'not a url'])));
check('T-U6 canonical "/" (home) on a post: left out', true === $odd(false, post(54, 'to-home', ['canonical' => '/'])));

// ------------------------------------------------------------------- retired posts (301 outside WordPress' data)
$RTX = 'nvidia-rtx-spark-launch-announcement';
$typed = static function ($id, $slug, $type = 'post') { return $GLOBALS['posts'][$id] = new WP_Post(['ID' => $id, 'post_name' => $slug, 'post_type' => $type]); };
check('T-R0 hooked on slim_seo_sitemap_post_ignore', in_array('omochix_sitemap_ignore_retired_post', $GLOBALS['filters']['slim_seo_sitemap_post_ignore'] ?? [], true));
check('T-R0 the list is exactly post 1184 with its slug', [1184 => $RTX] === omochix_sitemap_retired_posts());
$src = (string) file_get_contents($THEME . '/inc/seo.php');
check('T-R0 the list cannot be changed at run time (no filter, no option, no request input)',
    false === strpos($src, "'omochix_sitemap_retired_posts'") && false === strpos($src, '"omochix_sitemap_retired_posts"')
    && 1 === preg_match('/function omochix_sitemap_retired_posts\(\) \{\s*return \[[^\]]*\];\s*\}/', $src)
    && !preg_match('/function omochix_sitemap_retired_posts\(\)[\s\S]{0,400}?(get_option|\$_(GET|POST|REQUEST|COOKIE|SERVER)|apply_filters)/', $src));
// T-R1/R2: canonical empty, noindex off (no slim_seo meta), ID 1184 and the slug -> left out, as a post object or an id.
check('T-R1 post 1184 with the slug, no canonical: left out', true === apply_sitemap_ignore(false, $typed(1184, $RTX)));
check('T-R2 the same by int id: left out', true === apply_sitemap_ignore(false, 1184));
check('T-R2 the same by numeric string id: left out', true === apply_sitemap_ignore(false, '1184'));
// T-R3..R5: only some of the three conditions -> listed.
check('T-R3 ID 1184 with another slug: listed', false === apply_sitemap_ignore(false, $typed(1184, $RTX . '-2')));
check('T-R4 the slug on another ID: listed', false === apply_sitemap_ignore(false, $typed(1185, $RTX)));
check('T-R5 ID 1184 and the slug as a page: listed', false === apply_sitemap_ignore(false, $typed(1184, $RTX, 'page')));
check('T-R5 ID 1184 and the slug as an ai_tool: listed', false === apply_sitemap_ignore(false, $typed(1184, $RTX, 'ai_tool')));
check('T-R5 slug compared exactly (case, trailing space)', false === apply_sitemap_ignore(false, $typed(1184, strtoupper($RTX))) && false === apply_sitemap_ignore(false, $typed(1184, $RTX . ' ')));
// T-R6: another filter's decision is kept.
check('T-R6 ignore=true kept for an unrelated post', true === apply_sitemap_ignore(true, $typed(60, 'unrelated')));
// T-R7: the pure rule with odd lists never matches by accident and never warns.
$p = new WP_Post(['ID' => 1184, 'post_name' => $RTX]);
check('T-R7 empty list', false === omochix_sitemap_is_retired_post($p, []));
check('T-R7 empty / non-string slug in the list', false === omochix_sitemap_is_retired_post($p, [1184 => '']) && false === omochix_sitemap_is_retired_post($p, [1184 => 0]) && false === omochix_sitemap_is_retired_post($p, [1184 => null]));
check('T-R7 post_name not a string', false === omochix_sitemap_is_retired_post(new WP_Post(['ID' => 1184, 'post_name' => null]), [1184 => $RTX]));
check('T-R7 ID 0 / negative', false === omochix_sitemap_is_retired_post(new WP_Post(['ID' => 0, 'post_name' => 'x']), [0 => 'x']) && false === omochix_sitemap_is_retired_post(new WP_Post(['ID' => -1, 'post_name' => 'x']), [-1 => 'x']));
check('T-R8 another list entry works through the pure rule', true === omochix_sitemap_is_retired_post(new WP_Post(['ID' => 7, 'post_name' => 'gone']), [7 => 'gone']));
// T-R9: odd arguments -> unchanged.
check('T-R9 null / 0 / garbage / unknown id: unchanged', false === $odd(false, null) && false === $odd(false, 0) && false === $odd(false, 'abc') && false === $odd(false, 4242));
// The canonical rule is unchanged for post 1184's neighbours (T-U3 above still left out by canonical).
check('T-R10 canonical rule still applies on its own', true === apply_sitemap_ignore(false, post(70, 'old-dup', ['canonical' => '/new/'])));

// ------------------------------------------------------------------- the 1.32.1 release plan matches this rule
$ROOT = realpath(__DIR__ . '/../..');
// The 1.32.1 plan as archived with that release (the current release.json belongs to the next release; its theme version is
// checked by tests/release/release-gate.php).
$cfg  = json_decode((string) file_get_contents($ROOT . '/tools/release/configs/theme-1.32.1.json'), true);
$exp  = json_decode((string) file_get_contents($ROOT . '/tests/release/expected/theme-1.32.1.json'), true);
$pair = static fn($l) => array_map(static fn($s) => $s[0] . ':' . $s[1], (array) $l);
check('REL archived 1.32.1 expectations: theme 1.32.1 on its release commit 1aa10bb', '1.32.1' === ($exp['versions']['theme'] ?? '') && '1aa10bbc47cec1ca72d29dd21f0001e08102024e' === ($exp['commit'] ?? ''));
check('REL theme only, on the production 1.32.0 baseline', ['theme'] === array_keys($cfg['packages'] ?? []) && '1.32.0' === ($cfg['packages']['theme']['baseline_version'] ?? '')
    && 'tools/release/baseline/omochix-theme-1.32.0.sha256' === ($cfg['packages']['theme']['baseline'] ?? ''));
check('REL order: learn-composer, seo, style.css last', ['theme:inc/learn-composer.php', 'theme:inc/seo.php', 'theme:style.css'] === $pair($cfg['order'] ?? []));
check('REL every step file exists in the theme', !array_filter((array) ($cfg['order'] ?? []), static fn($s) => !is_file($THEME . '/' . $s[1])));
check('REL switch style.css; rollback restores the 3 files in reverse', ['theme:style.css'] === $pair($cfg['switches'] ?? []) && ['theme:style.css', 'theme:inc/seo.php', 'theme:inc/learn-composer.php'] === $pair($cfg['rollback_order'] ?? []));
check('REL expectations: 0 added, 3 replaced, 3 steps, same release name', 0 === ($exp['packages']['theme']['add'] ?? null) && 3 === ($exp['packages']['theme']['replace'] ?? null) && 3 === ($exp['steps'] ?? null) && ($exp['release'] ?? '') === ($cfg['release'] ?? '-'));
$gb = (string) ($cfg['gates']['B']['check'] ?? '');
check('REL gate B follows style.css', ['theme', 'style.css'] === ($cfg['gates']['B']['after'] ?? null));
check('REL gate B names the retired post by the ID and slug the code uses', false !== strpos($gb, 'post ' . array_key_first(omochix_sitemap_retired_posts())) && false !== strpos($gb, '/' . array_values(omochix_sitemap_retired_posts())[0] . '/'));
check('REL gate B: 301 to the newer article kept, 594 -> 592, compose 200 noindex', false !== strpos($gb, '301') && false !== strpos($gb, '/microsoft-nvidia-rtx-spark-local-ai-windows-2026/')
    && false !== strpos($gb, '594') && false !== strpos($gb, '592') && false !== strpos($gb, '/learn/motion/compose/') && false !== strpos($gb, 'noindex, follow') && false !== strpos($gb, 'style.css?ver=1.32.1'));
check('REL gate B no longer expects the old article to answer 200', false === strpos($gb, 'both pages still answer 200'));
check('REL rollback note: 594 again, 301 stays', 1 === count(array_filter((array) ($cfg['after_rollback'] ?? []), static fn($n) => false !== strpos($n, '594') && false !== strpos($n, '301'))));

// ------------------------------------------------------------------- gate B, read as claims (not substrings)
// Every "X answers NNN" in gate B must name its subject as a path ("/a/b/", optionally followed by a parenthesis) or
// "an unknown URL"; a pronoun ("that article", "it", "both pages") is refused, so no status can be claimed for an
// unnamed page. The claims per path, the sitemap removals and the counts are then checked against the code.
$rtxPath     = '/' . array_values(omochix_sitemap_retired_posts())[0] . '/';
$newerPath   = '/microsoft-nvidia-rtx-spark-local-ai-windows-2026/';
preg_match("/const OMOCHIX_LEARN_COMPOSER_PATH\s*=\s*'([^']+)'/", (string) file_get_contents($THEME . '/inc/learn-composer.php'), $cp);
$composePath = '/' . ($cp[1] ?? '?') . '/';
function gate_b_claims($text) {
    preg_match_all('/(\S+)(?:\s+\([^()]*\))?\s+(?:still\s+)?answers?\s+(\d{3})\b/', $text, $m, PREG_SET_ORDER);
    $claims = []; $unnamed = [];
    foreach ($m as $c) {
        $subject = $c[1];
        if (preg_match('#^/[A-Za-z0-9/._-]*$#', $subject)) { $claims[$subject][] = (int) $c[2]; }
        elseif ('URL' === $subject && preg_match('/\ban unknown URL\s+answers?\s+' . $c[2] . '/', $text)) { $claims['(unknown)'][] = (int) $c[2]; }
        else { $unnamed[] = $c[0]; }
    }
    return [$claims, $unnamed];
}
function gate_b_removals($text) {
    preg_match_all('#(/[A-Za-z0-9/_-]+/) from (/sitemap-[a-z_-]+\.xml)#', $text, $m, PREG_SET_ORDER);
    $out = array_map(static fn($r) => $r[1] . ' ' . $r[2], $m); sort($out); return $out;
}
[$claims, $unnamed] = gate_b_claims($gb);
check('GB every status claim names its page (no "that article answers 200")', [] === $unnamed);
check('GB the old RTX article must answer 301, and nothing else', [301] === ($claims[$rtxPath] ?? null));
check('GB the 301 goes to the newer article', 1 === preg_match('#' . preg_quote($rtxPath, '#') . '(?:\s+\([^()]*\))?\s+answers\s+301\s+to\s+' . preg_quote($newerPath, '#') . '#', $gb));
check('GB the newer article must answer 200, and nothing else', [200] === ($claims[$newerPath] ?? null));
check('GB the old RTX article is never expected to answer 200', !in_array(200, $claims[$rtxPath] ?? [], true));
check('GB compose must answer 200 with noindex, follow', [200] === ($claims[$composePath] ?? null)
    && 1 === preg_match('#' . preg_quote($composePath, '#') . '\s+answers\s+200\s+with\s+noindex, follow#', $gb));
check('GB unknown URL 404; Code Studio and /design/ 404', [404] === ($claims['(unknown)'] ?? null) && [404] === ($claims['/labs/code-studio/'] ?? null) && [404] === ($claims['/design/'] ?? null));
$removals = gate_b_removals($gb);
check('GB exactly RTX (post sitemap) and compose (page sitemap) leave the sitemap',
    [$rtxPath . ' /sitemap-post-type-post.xml', $composePath . ' /sitemap-post-type-page.xml'] === $removals || [$composePath . ' /sitemap-post-type-page.xml', $rtxPath . ' /sitemap-post-type-post.xml'] === $removals);
check('GB "exactly 2 URLs leave", 594 -> 592, and 594 - 592 equals the removals',
    1 === preg_match('/exactly (\d+) URLs leave/', $gb, $ex) && 1 === preg_match('/(\d+) sitemap URLs become (\d+)/', $gb, $cnt)
    && '594' === $cnt[1] && '592' === $cnt[2] && (int) $ex[1] === count($removals) && (int) $cnt[1] - (int) $cnt[2] === count($removals) && 2 === count($removals));
check('GB the two removals are exactly what the code leaves out (retired list + composer path)', 1 === count(omochix_sitemap_retired_posts()) && 'learn/motion/compose' === ($cp[1] ?? ''));
check('GB 101 URLs unchanged apart from the intended sitemap difference', 1 === preg_match('/Existing 101 URLs unchanged from the 1\.32\.0 state apart from that intended sitemap difference/', $gb));
preg_match('/^\s*\*\s*Version:\s*(\S+)/m', (string) file_get_contents($ROOT . '/wp-plugin/omochix-core/omochix-core.php'), $coreVer);
check('GB theme 1.32.1 and Core stays 1.10.0 (gate, expectations, plugin header, untouched baseline)',
    1 === preg_match('/style\.css\?ver=1\.32\.1\b/', $gb) && 1 === preg_match('/Core stays 1\.10\.0\b/', $gb)
    && '1.10.0' === ($exp['versions']['core'] ?? '') && '1.10.0' === ($coreVer[1] ?? '')
    && 'tools/release/baseline/omochix-core-1.10.0.sha256' === ($exp['unchanged']['core']['baseline'] ?? '') && !isset($cfg['packages']['core']));
// The parser itself: the wording found in review is refused, and a 200 claimed for the old article is caught.
[, $u1] = gate_b_claims('/old/ answers 301 to /new/; that article still answers 200');
[$c2]   = gate_b_claims('/old/ answers 200; /new/ answers 200');
check('GB parser refuses a pronoun subject and records a named 200', 1 === count($u1) && [200] === ($c2['/old/'] ?? null));

// ------------------------------------------------------------------- the canonical rule from #76 is unchanged
$fnHash = static function ($name) use ($src) { return preg_match('/^function ' . $name . '\(.*?^\}\n/ms', $src, $m) ? hash('sha256', $m[0]) : ''; };
check('CANON omochix_sitemap_comparable_url() byte-identical to #76 (af91dcc)', '8742a8cac0729e831cdf0b370051d4e97b1786d2992f8f7cd4ba1660a0ff790b' === $fnHash('omochix_sitemap_comparable_url'));
check('CANON omochix_sitemap_ignore_non_self_canonical() byte-identical to #76 (af91dcc)', '1faa6eec30189422751e81f78b8339ba5ec5e4c7fe7d0efcf328cb7ea998548a' === $fnHash('omochix_sitemap_ignore_non_self_canonical'));
check('CANON still hooked once at 10, 2', 1 === substr_count($src, "add_filter('slim_seo_sitemap_post_ignore', 'omochix_sitemap_ignore_non_self_canonical', 10, 2);"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
