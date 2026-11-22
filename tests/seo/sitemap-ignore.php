<?php
/**
 * SEO: Slim SEO sitemap exclusion of posts whose Slim SEO canonical names another URL
 * (omochix_sitemap_ignore_non_self_canonical() in inc/seo.php) and of retired posts pinned by ID and slug
 * (omochix_sitemap_ignore_retired_post()), run through the registered 'slim_seo_sitemap_post_ignore'
 * callbacks exactly as Slim SEO calls them: ($ignore, WP_Post). Also checks that the theme 1.32.1
 * release plan (tools/release/release.json and its expectations) matches the rule.
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
$cfg  = json_decode((string) file_get_contents($ROOT . '/tools/release/release.json'), true);
$exp  = json_decode((string) file_get_contents($ROOT . '/tests/release/expected/release.json'), true);
preg_match('/^Version:\s*(\S+)/m', (string) file_get_contents($THEME . '/style.css'), $ver);
$pair = static fn($l) => array_map(static fn($s) => $s[0] . ':' . $s[1], (array) $l);
check('REL theme version 1.32.1 = expectations', '1.32.1' === ($ver[1] ?? '') && '1.32.1' === ($exp['versions']['theme'] ?? ''));
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

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
