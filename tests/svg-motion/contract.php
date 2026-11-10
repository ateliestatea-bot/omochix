<?php
/**
 * SVG Motion Studio contract checks that need neither WordPress nor a browser.
 * Run: php tests/svg-motion/contract.php
 */
define('ABSPATH', '/');
define('OBJECT', 'OBJECT');
$ROOT  = realpath(__DIR__ . '/../..');
$THEME = $ROOT . '/wp-theme/omochix-theme';
class WP_Post { public $ID = 5, $post_status = 'publish'; }
$GLOBALS['hooks'] = []; $GLOBALS['page'] = null; $GLOBALS['ctx'] = ['is_page' => false, 'id' => 0];
function add_filter($h, $c, $p = 10) { $GLOBALS['hooks'][] = [$h, $p]; } function add_action($h, $c, $p = 10) { $GLOBALS['hooks'][] = [$h, $p]; }
function get_theme_file_path($f) { global $THEME; return $THEME . $f; }
function get_theme_file_uri($f) { return 'https://example.test/wp-content/themes/omochix-theme' . $f; }
function get_page_by_path($p, $o = null, $t = 'page') { return $GLOBALS['page']; }
function is_page() { return $GLOBALS['ctx']['is_page']; }
function get_queried_object_id() { return $GLOBALS['ctx']['id']; }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function __($t, $d = '') { return $t; }
require $THEME . '/inc/learn-svg-motion.php';

$pass = 0; $fail = 0;
function check($n, $c) { global $pass, $fail; if ($c) { $pass++; echo "PASS $n\n"; } else { $fail++; echo "FAIL $n\n"; } }

$d = omochix_svg_motion_data();
check('data: 19 presets and 3 samples', 19 === count($d['presets']) && 3 === count($d['samples']));
$ids = array_column($d['presets'], 'id');
check('messages: every error code the scripts can raise has Japanese text', !array_diff(['EMPTY_INPUT','NOT_SVG_FILE','READ_FAILED','NOT_UTF8','TOO_MANY_FILES','TOO_LARGE','PARSE_ERROR','NOT_SVG','DOCTYPE_REJECTED','ELEMENT_LIMIT','DEPTH_LIMIT','PATH_LIMIT','USE_EXPANSION_LIMIT','SANITIZE_FAILED','VERIFY_FAILED','EMPTY_RESULT'], array_keys(omochix_svg_motion_messages()['errors'])));
check('messages: export and settings-file codes have Japanese text', !array_diff(['JSON_TOO_LARGE','JSON_INVALID','JSON_FORMAT','JSON_SCHEMA','EXPORT_FAILED','EDIT_INVALID','EDIT_LIMIT','EDIT_CONFLICT'], array_keys(omochix_svg_motion_messages()['errors'])) && '' !== omochix_svg_motion_messages()['editDiscarded'] && '' !== omochix_svg_motion_messages()['copied'] && '' !== omochix_svg_motion_messages()['copyFailed'] && '' !== omochix_svg_motion_messages()['imported']);
check('export: four usages, image first', ['image','inline','external','script'] === array_keys(omochix_svg_motion_usages()));
$ex = file_get_contents($THEME . '/assets/js/svg-motion/export.js');
check('export: no network, storage or eval in the exporter', !preg_match('/fetch\(|XMLHttpRequest|sendBeacon|localStorage|eval\(|new Function/', $ex));
check('data: nine common adjustments', ['delay','easing','loop','stagger','opacity','scale','x','y','rotate'] === array_column($d['common'], 'key'));
check('data: unique ids', count($ids) === count(array_unique($ids)));
check('scope: S1-B and S1-C presets', ['P01','P02','P03','P04','P05','P06','P07','P08','P09','P10','P11','P12','P13','P14','P15','P16','P17','P18','P19'] === (function ($r) { sort($r); return $r; })(array_column($d['presets'], 'ref')));
check('scope: P20 is not part of the product', !in_array('P20', array_column($d['presets'], 'ref'), true) && false === stripos(file_get_contents($THEME . '/assets/js/svg-motion/compile.js'), 'morph'));
foreach ($d['presets'] as $p) {
    check("preset {$p['id']}: name, description, category and 2+ params", '' !== $p['name'] && '' !== $p['description'] && in_array($p['category'], ['entrance', 'loop'], true) && count($p['params']) >= 2);
}
foreach ($d['samples'] as $s) {
    check("sample {$s['id']}: bundled SVG has nothing active or external", !preg_match('/<script|on[a-z]+=|https?:\/\/(?!www\.w3\.org)|<image|foreignObject/i', $s['svg']));
}
check('json: script-safe', !preg_match('#</script#i', omochix_svg_motion_data_json()) && false !== strpos(omochix_svg_motion_data_json(), '"budget"'));
check('routing: not a request without the page', !omochix_svg_motion_is_request());
check('hooks: template filter before the Learn swap', in_array(['template_include', 8], $GLOBALS['hooks'], true));
function is_admin() { return $GLOBALS['ctx']['admin'] ?? false; }
$GLOBALS['page'] = new WP_Post();
function omochix_get_learn_page_id() { return 9; }
class FakeQuery { public $v; public $main; function __construct($v, $main = false) { $this->v = $v; $this->main = $main; } function get($k) { return $this->v[$k] ?? ''; } function set($k, $x) { $this->v[$k] = $x; } function is_main_query() { return $this->main; } }
$q = new FakeQuery(['post_type' => 'page', 'post_parent' => 9, 'post__not_in' => [1]]); omochix_svg_motion_exclude_from_siblings($q);
check('siblings: excluded from the Learn sibling query, keeping existing exclusions', [1, 5] === $q->get('post__not_in'));
$q = new FakeQuery(['post_type' => 'page', 'post_parent' => 9], true); omochix_svg_motion_exclude_from_siblings($q);
check('siblings: the main query still finds the page', '' === $q->get('post__not_in'));
$q = new FakeQuery(['post_type' => 'page', 'post_parent' => 4]); omochix_svg_motion_exclude_from_siblings($q);
check('siblings: other parents are untouched', '' === $q->get('post__not_in'));
$q = new FakeQuery(['post_type' => 'page']); omochix_svg_motion_exclude_from_siblings($q);
check('siblings: a query without a parent (sitemap) is untouched', '' === $q->get('post__not_in'));
$GLOBALS['ctx']['admin'] = true; $q = new FakeQuery(['post_type' => 'page', 'post_parent' => 9]); omochix_svg_motion_exclude_from_siblings($q);
check('siblings: wp-admin is untouched', '' === $q->get('post__not_in')); $GLOBALS['ctx']['admin'] = false;
$src = file_get_contents($THEME . '/inc/learn-svg-motion.php');
check('server: no upload, REST, AJAX, option or post writes', !preg_match('/upload_mimes|register_rest_route|wp_ajax|update_option|wp_insert_post|wpdb|add_rewrite_rule/', preg_replace('#/\*.*?\*/#s', '', $src)));
$cat = hash_file('sha256', $THEME . '/assets/data/motion/catalog.json');
check('existing: catalog.json unchanged (Theme 1.27.0)', 'f7140a9e5df5f1be' === substr($cat, 0, 16));
check('existing: 53 samples still', 53 === count(json_decode(file_get_contents($THEME . '/assets/data/motion/catalog.json'), true)['items']));
$pur = file_get_contents($THEME . '/assets/js/svg-motion/lib/purify.min.js');
check('dependency: DOMPurify pinned by hash', 'cd37898f9782adf54820c413191b5691803f0f137c799d6edcfd38523b4c2c31' === hash('sha256', $pur));
check('dependency: no GSAP', !preg_match('/gsap/i', implode('', array_map('file_get_contents', glob($THEME . '/assets/js/svg-motion/*.js')))));
// CSP design (2026-11-09): the measuring frame and the studio page's policy.
$frameHtml = file_get_contents($THEME . '/assets/svg-motion/measure-frame.html'); $frameJs = file_get_contents($THEME . '/assets/js/svg-motion/measure-frame.js');
check('csp: the frame page refers to its script by the script\'s own hash (?ver=)', 1 === preg_match('#<script src="\.\./js/svg-motion/measure-frame\.js\?ver=([0-9a-f]{12})"></script>#', $frameHtml, $mv) && $mv[1] === substr(hash('sha256', $frameJs), 0, 12));
check('csp: the frame page has no inline script and its own strict policy', 1 === preg_match_all('#<script#i', $frameHtml) && false !== strpos($frameHtml, "default-src 'none'; script-src 'self';") && !preg_match("#unsafe-(inline|eval)[^\"]*script|script-src[^;\"]*unsafe#", $frameHtml));
$data = json_decode(omochix_svg_motion_data_json(), true);
check('csp: the page data gives the frame URL with the version of both files', $data['measureFrame'] === 'https://example.test/wp-content/themes/omochix-theme/assets/svg-motion/measure-frame.html?ver=' . substr(hash('sha256', $frameHtml . "\n" . $frameJs), 0, 12));
$pol = omochix_svg_motion_csp_policy(); preg_match('/script-src ([^;]*)/', $pol, $sm);
check('csp: no unsafe-inline or unsafe-eval for scripts, no wildcard', !preg_match('/unsafe-inline|unsafe-eval|\*/', $sm[1]) && false === strpos($pol, 'unsafe-eval') && !preg_match('/(?:^|\s)\*(?:\s|;|$)/', $pol));
check('csp: frames, objects, workers, base and ancestors are restricted', false !== strpos($pol, "frame-src 'self'") && false !== strpos($pol, "object-src 'none'") && false !== strpos($pol, "worker-src 'none'") && false !== strpos($pol, "base-uri 'self'") && false !== strpos($pol, "frame-ancestors 'self'"));
function omochix_theme_boot_script() { return "\n        /* test */\n    "; }
check('csp: the theme\'s inline script is allowed by its exact hash', false !== strpos(omochix_svg_motion_csp_policy(), "'sha256-" . base64_encode(hash('sha256', omochix_theme_boot_script(), true)) . "'"));
check('csp: default mode is report-only; unknown values fall back to it', 'report' === omochix_svg_motion_csp_mode());
check('csp: hooks for the header and for removing speculation rules', in_array(['template_redirect', 10], $GLOBALS['hooks'], true) && in_array(['wp_speculation_rules_configuration', 10], $GLOBALS['hooks'], true));
$fr = file_get_contents($THEME . '/assets/js/svg-motion/frame.js');
check('csp: measure() no longer uses srcdoc', !preg_match('/\.srcdoc\s*=/', $fr));
// V-2 (2026-11-10): script-src names the exact files, never 'self' or a folder (both would allow WordPress's REST JSONP).
function omochix_theme_header_script_url() { return 'https://example.test/wp-content/themes/omochix-theme/assets/js/header.js'; }
preg_match('/script-src ([^;]*)/', omochix_svg_motion_csp_policy(), $sm2); $sources = preg_split('/\s+/', trim($sm2[1]));
$urls = array_values(array_filter($sources, static fn($x) => 0 !== strpos($x, "'")));
check('V-2 script-src has no \'self\', no unsafe-*, no wildcard', !in_array("'self'", $sources, true) && !preg_grep('/unsafe-|\*/', $sources));
check('V-2 every script source is an exact absolute file URL (no folder prefix, no query) or the boot-script hash', !array_diff($sources, array_merge($urls, preg_grep("/^'sha256-[A-Za-z0-9+\/=]{44}'$/", $sources))) && array_reduce($urls, static fn($ok, $u) => $ok && preg_match('~^https://example\.test/wp-content/themes/omochix-theme/assets/js/[^?#;]*\.js$~D', $u), true));
check('V-2 the CSP names 11 scripts: the header script and the 10 studio scripts', 11 === count($urls) && 10 === count(OMOCHIX_SVG_MOTION_SCRIPTS) && in_array(omochix_theme_header_script_url(), $urls, true));
// T-2: what the page enqueues is exactly what the CSP names (one list feeds both).
$GLOBALS['enq'] = [];
function wp_get_theme() { return new class { function get($k) { return '9.9.9'; } }; }
function wp_style_is($h, $l) { return false; } function wp_enqueue_style(...$a) {} function wp_enqueue_script($h, $src, $deps = [], $ver = false, $foot = false) { $GLOBALS['enq'][$h] = [$src, $deps, $ver]; }
$GLOBALS['ctx'] = ['is_page' => true, 'id' => 5]; omochix_svg_motion_enqueue_assets();
$enqueued = array_column($GLOBALS['enq'], 0);
check('T-2 the studio enqueues exactly the scripts the CSP names (plus the header script from functions.php)', array_values(array_diff($urls, [omochix_theme_header_script_url()])) === $enqueued);
check('T-2 enqueue keeps the dependency order and versions', ['omochix-dompurify', 'omochix-svg-safety', 'omochix-svg-compile', 'omochix-svg-frame', 'omochix-svg-analyze', 'omochix-svg-import', 'omochix-svg-preview', 'omochix-svg-store', 'omochix-svg-export', 'omochix-svg-studio'] === array_keys($GLOBALS['enq']) && '3.4.17' === $GLOBALS['enq']['omochix-dompurify'][2] && '9.9.9' === $GLOBALS['enq']['omochix-svg-studio'][2] && ['omochix-svg-import', 'omochix-svg-preview', 'omochix-svg-store', 'omochix-svg-export'] === $GLOBALS['enq']['omochix-svg-studio'][1]);
check('T-2 every named script exists in the theme', array_reduce(OMOCHIX_SVG_MOTION_SCRIPTS, static fn($ok, $x) => $ok && is_file($THEME . $x[0]), true) && is_file($THEME . '/assets/js/header.js'));
// V-1: emoji detection removed on the studio page only; L-3: X-Frame-Options; L-2: the CSP header is added, not replaced.
$GLOBALS['removed'] = []; function remove_action($h, $f, $p = 10) { $GLOBALS['removed'][] = [$h, $f, $p]; return true; }
$GLOBALS['ctx'] = ['is_page' => false, 'id' => 0]; omochix_svg_motion_page_headers(); $notStudio = $GLOBALS['removed'];
$GLOBALS['ctx'] = ['is_page' => true, 'id' => 5]; omochix_svg_motion_page_headers(); $studio = $GLOBALS['removed'];
check('V-1 nothing is removed on other pages', [] === $notStudio);
check('V-1 on the studio page: emoji script (wp_head, 7) and both emoji style hooks are removed', [['wp_head', 'print_emoji_detection_script', 7], ['wp_enqueue_scripts', 'wp_enqueue_emoji_styles', 10], ['wp_print_styles', 'print_emoji_styles', 10]] === $studio);
$src2 = file_get_contents($THEME . '/inc/learn-svg-motion.php');
check('V-1 the removal runs on template_redirect (before the template prints)', in_array(['template_redirect', 10], $GLOBALS['hooks'], true) && false !== strpos($src2, "add_action('template_redirect', 'omochix_svg_motion_page_headers');"));
check('L-3 the studio page sends X-Frame-Options: SAMEORIGIN', false !== strpos($src2, "header('X-Frame-Options: SAMEORIGIN');"));
check('L-2 the CSP header is added, not replaced', 1 === preg_match("/omochix_svg_motion_csp_policy\(\), false\);/", $src2));
// T-8: the theme has exactly one inline script, printed from omochix_theme_boot_script() (so its hash is the one allowed).
$head = file_get_contents($THEME . '/header.php'); $fn = file_get_contents($THEME . '/functions.php');
check('T-8 header.php has exactly one inline script, the boot script', 1 === preg_match_all('/<script\b/i', $head) && 1 === substr_count($head, '<script><?php echo omochix_theme_boot_script();'));
check('T-8 functions.php loads the header script from omochix_theme_header_script_url()', false !== strpos($fn, "'omochix-header',\n        omochix_theme_header_script_url(),") && false !== strpos($fn, "return get_template_directory_uri() . '/assets/js/header.js';"));
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
