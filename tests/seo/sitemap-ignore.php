<?php
/**
 * SEO: Slim SEO sitemap exclusion of posts whose Slim SEO canonical names another URL
 * (omochix_sitemap_ignore_non_self_canonical() in inc/seo.php), run through the registered
 * 'slim_seo_sitemap_post_ignore' callback exactly as Slim SEO calls it: ($ignore, WP_Post).
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

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
