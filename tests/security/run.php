<?php
/**
 * Web 2.0 security layer (wp-plugin/omochix-core/includes/web2/security.php), without WordPress.
 *
 * N-1: a navigation block renders only a published, password-free wp_navigation menu by reference;
 * every other reference renders nothing (no fallback menu), for every user, with or without modules.
 *
 * Run: php tests/security/run.php
 */
$REPO = realpath(__DIR__ . '/../..');
$FILE = $REPO . '/wp-plugin/omochix-core/includes/web2/security.php';
define('ABSPATH', '/tmp/');
$GLOBALS['hooks'] = []; $GLOBALS['posts'] = []; $pass = 0; $fail = 0;
function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['hooks'][$h][$p][] = [$cb, $a]; return true; }
function has_filter($h, $cb) { foreach ($GLOBALS['hooks'][$h] ?? [] as $p => $l) { foreach ($l as $x) { if ($x[0] === $cb) { return $p; } } } return false; }
// WordPress: get_post( 0 | '' | null ) falls back to the global post; get_post( numeric ) casts to int.
function get_post($p = null) { if ($p instanceof WP_Post) { return $p; } if (empty($p)) { return $GLOBALS['post'] ?? null; } return is_numeric($p) ? ($GLOBALS['posts'][(int) $p] ?? null) : null; }
class WP_Post { public $ID = 0, $post_type = 'post', $post_status = 'publish', $post_password = '', $post_title = ''; function __construct($a = []) { foreach ($a as $k => $v) { $this->$k = $v; } } }
class WP_Block { public $parsed_block; function __construct($pb = []) { $this->parsed_block = $pb; } }
function check($name, $ok, $detail = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }
$id = 100; $mk = static function (array $a) use (&$id) { $id++; $GLOBALS['posts'][$id] = new WP_Post(['ID' => $id] + $a); return $id; };

require $FILE;

$menu   = $mk(['post_type' => 'wp_navigation']);
$draftm = $mk(['post_type' => 'wp_navigation', 'post_status' => 'draft']);
$futm   = $mk(['post_type' => 'wp_navigation', 'post_status' => 'future']);
$pendm  = $mk(['post_type' => 'wp_navigation', 'post_status' => 'pending']);
$privm  = $mk(['post_type' => 'wp_navigation', 'post_status' => 'private']);
$trashm = $mk(['post_type' => 'wp_navigation', 'post_status' => 'trash']);
$pwm    = $mk(['post_type' => 'wp_navigation', 'post_password' => 'x']);
$term   = $mk(['post_type' => 'omx_glossary']);
$termpw = $mk(['post_type' => 'omx_glossary', 'post_password' => 'x']);
$apost  = $mk(['post_type' => 'post']);
$postpw = $mk(['post_type' => 'post', 'post_password' => 'x']);
$page   = $mk(['post_type' => 'page']);
$wpblk  = $mk(['post_type' => 'wp_block']);
$nb = static fn($attrs) => new WP_Block(['blockName' => 'core/navigation', 'attrs' => $attrs]);

// ------------------------------------------------------------ reference format
check('format: int, digit string and whole float (the block renderer REST route passes floats) give the ID', $menu === omochix_core_security_nav_ref_id($menu) && $menu === omochix_core_security_nav_ref_id((string) $menu) && $menu === omochix_core_security_nav_ref_id((float) $menu));
$junk = [0, -1, -$menu, 0.0, -1.0, $menu + 0.5, 1e30, INF, -INF, NAN, 9007199254740992.0, '0', '-1', '+' . $menu, '0' . $menu, ' ' . $menu, $menu . ' ', $menu . 'a', '1e3', '0x1f', '', 'abc', '9223372036854775807', '99999999999999999999', [$menu], ['ref' => $menu], true, false, null, (object) ['ID' => $menu], new WP_Post(['ID' => $menu, 'post_type' => 'wp_navigation'])];
$bad = array_keys(array_filter($junk, static fn($v) => 0 !== omochix_core_security_nav_ref_id($v)));
check('format: 0, negatives, fractions, huge or non-finite floats, signed, padded, leading-zero, hex or exponent strings, text, lists, booleans, null and objects give 0', [] === $bad, implode(',', $bad));
$GLOBALS['post'] = get_post($menu);
check('format: an empty reference does not fall back to the global post (get_post( 0 ) would)', !omochix_core_security_nav_ref_allowed(0) && !omochix_core_security_nav_ref_allowed('') && !omochix_core_security_nav_ref_allowed(null) && !omochix_core_security_nav_ref_allowed(false));
unset($GLOBALS['post']);

// ------------------------------------------------------------ reference target
check('target: a published navigation menu without a password is allowed (int, string, float)', omochix_core_security_nav_ref_allowed($menu) && omochix_core_security_nav_ref_allowed((string) $menu) && omochix_core_security_nav_ref_allowed((float) $menu));
check('target: draft, scheduled, pending, private, trashed and password-protected menus are refused', !omochix_core_security_nav_ref_allowed($draftm) && !omochix_core_security_nav_ref_allowed($futm) && !omochix_core_security_nav_ref_allowed($pendm) && !omochix_core_security_nav_ref_allowed($privm) && !omochix_core_security_nav_ref_allowed($trashm) && !omochix_core_security_nav_ref_allowed($pwm));
check('target: glossary terms (with or without a password), posts, password-protected posts, pages, synced patterns and unknown IDs are refused', !omochix_core_security_nav_ref_allowed($term) && !omochix_core_security_nav_ref_allowed($termpw) && !omochix_core_security_nav_ref_allowed($apost) && !omochix_core_security_nav_ref_allowed($postpw) && !omochix_core_security_nav_ref_allowed($page) && !omochix_core_security_nav_ref_allowed($wpblk) && !omochix_core_security_nav_ref_allowed(999999) && !omochix_core_security_nav_ref_allowed((string) $term) && !omochix_core_security_nav_ref_allowed((float) $postpw));
$GLOBALS['posts'][555] = new WP_Post(['ID' => 7, 'post_type' => 'wp_navigation']);
check('target: a post object that is not the one asked for is refused', !omochix_core_security_nav_ref_allowed(555));
unset($GLOBALS['posts'][555]);

// ------------------------------------------------------------ block
check('block: no reference is left to WordPress (inner blocks or its fallback)', omochix_core_security_nav_render_allowed([], $nb([])) && omochix_core_security_nav_render_allowed(['overlayMenu' => 'never'], $nb(['overlayMenu' => 'never'])) && omochix_core_security_nav_render_allowed([], null) && omochix_core_security_nav_render_allowed('x', 'y'));
check('block: a menu reference renders, in "ref" or the legacy "navigationMenuId"', omochix_core_security_nav_render_allowed(['ref' => $menu], $nb(['ref' => $menu])) && omochix_core_security_nav_render_allowed(['navigationMenuId' => $menu], $nb(['navigationMenuId' => $menu])) && omochix_core_security_nav_render_allowed(['ref' => (float) $menu], $nb(['ref' => $menu])));
check('block: any refused value in either key, prepared or stored, refuses the block', !omochix_core_security_nav_render_allowed(['ref' => $term], $nb(['ref' => $term])) && !omochix_core_security_nav_render_allowed(['ref' => $menu, 'navigationMenuId' => $postpw], $nb(['ref' => $menu, 'navigationMenuId' => $postpw])) && !omochix_core_security_nav_render_allowed(['ref' => $menu], $nb(['ref' => $term])) && !omochix_core_security_nav_render_allowed(['ref' => $term], $nb(['ref' => $menu])));
check('block: a stored reference WordPress dropped as the wrong type fails closed (no fallback menu)', !omochix_core_security_nav_render_allowed([], $nb(['ref' => 'abc'])) && !omochix_core_security_nav_render_allowed([], $nb(['ref' => [$menu]])) && !omochix_core_security_nav_render_allowed([], $nb(['ref' => true])) && !omochix_core_security_nav_render_allowed([], $nb(['ref' => null])) && !omochix_core_security_nav_render_allowed([], $nb(['navigationMenuId' => ''])));

// ------------------------------------------------------------ render callback
$calls = 0;
$args = omochix_core_security_nav_block_args(['render_callback' => static function ($a, $c, $b) use (&$calls) { $calls++; return "NAV:$c"; }, 'title' => 'Navigation'], 'core/navigation');
$cb = $args['render_callback'];
check('render: a refused block prints an empty string and WordPress is not called', '' === $cb(['ref' => $term], 'x', $nb(['ref' => $term])) && '' === $cb(['ref' => $postpw], 'x', $nb(['ref' => $postpw])) && '' === $cb(['ref' => $draftm], 'x', $nb(['ref' => $draftm])) && 0 === $calls);
check('render: a menu or no reference calls WordPress with the same arguments; other arguments are kept', 'NAV:x' === $cb(['ref' => $menu], 'x', $nb(['ref' => $menu])) && 'NAV:' === $cb([], '', $nb([])) && 2 === $calls && 'Navigation' === $args['title']);
$plain = static fn() => 'X';
check('render: other block types, a type without a callback and junk are untouched', ['render_callback' => $plain] === omochix_core_security_nav_block_args(['render_callback' => $plain], 'core/block') && ['title' => 'N'] === omochix_core_security_nav_block_args(['title' => 'N'], 'core/navigation') && ['render_callback' => 'no_such_fn'] === omochix_core_security_nav_block_args(['render_callback' => 'no_such_fn'], 'core/navigation') && 'x' === omochix_core_security_nav_block_args('x', 'core/navigation') && ['render_callback' => $plain] === omochix_core_security_nav_block_args(['render_callback' => $plain]));
check('hook: registered once, last, with both arguments', 9999 === has_filter('register_block_type_args', 'omochix_core_security_nav_block_args') && 1 === count(array_filter(array_merge(...array_values($GLOBALS['hooks']['register_block_type_args'])), static fn($x) => 'omochix_core_security_nav_block_args' === $x[0])) && [['omochix_core_security_nav_block_args', 2]] === array_values(array_filter($GLOBALS['hooks']['register_block_type_args'][9999], static fn($x) => 'omochix_core_security_nav_block_args' === $x[0])));

// ------------------------------------------------------------ file rules
$src = (string) file_get_contents($FILE);
$code = implode('', array_map(static fn($t) => is_array($t) ? (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : $t[1]) : $t, token_get_all($src)));
check('T-SAFE: no request data, no database handle, no globals, no output, no network', 0 === preg_match('/\$_(POST|GET|REQUEST|COOKIE|SERVER|FILES|ENV)|\$wpdb|\bglobal\b|\$GLOBALS|\becho\b|\bprint\b|printf|wp_remote_|curl_|file_get_contents|fopen/', $code));
check('independent: no glossary function, switch, allow list or Web 2.0 loader state is used', 0 === preg_match('/glossary|omochix_web2|omochix_core_web2|OMOCHIX_WEB2/', $code));
check('not a Web 2.0 module: no module marker (the loader must never list it)', false === strpos($src, 'omochix-web2-module:'));
preg_match_all('/\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $code, $m);
check('declares only omochix_core_security_* functions, no class, no namespace', [] !== $m[1] && [] === array_filter($m[1], static fn($f) => 0 !== strpos($f, 'omochix_core_security_')) && 0 === preg_match('/\bclass\s+\w|\bnamespace\b/', $code));
$ft = []; foreach (token_get_all($src) as $t) { if (is_array($t) && T_STRING === $t[0]) { $ft[] = $t[1]; } }
check('PHP 8.1: no syntax or function newer than 8.1 (enum, readonly, never, json_validate, mb_str_pad, str_increment, #[\Override])', 0 === preg_match('/\benum\s+\w|\breadonly\b|:\s*never\b|#\[\\\\?Override\]/', $code) && [] === array_intersect($ft, ['json_validate', 'mb_str_pad', 'str_increment', 'str_decrement', 'array_find', 'array_any', 'array_all', 'mb_trim']));
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('define("ABSPATH", "/tmp/"); function add_filter() { return true; } require ' . var_export($FILE, true) . ';') . ' 2>&1');
check('the file loads on its own without printing anything (no notice, no output)', '' === (string) $out, (string) $out);
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export($FILE, true) . '; echo "RAN";') . ' 2>&1');
check('outside WordPress (no ABSPATH) the file stops before declaring anything', '' === (string) $out, (string) $out);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
