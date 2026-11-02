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
function get_page_by_path($p, $o = null, $t = 'page') { return $GLOBALS['page']; }
function is_page() { return $GLOBALS['ctx']['is_page']; }
function get_queried_object_id() { return $GLOBALS['ctx']['id']; }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
require $THEME . '/inc/learn-svg-motion.php';

$pass = 0; $fail = 0;
function check($n, $c) { global $pass, $fail; if ($c) { $pass++; echo "PASS $n\n"; } else { $fail++; echo "FAIL $n\n"; } }

$d = omochix_svg_motion_data();
check('data: 19 presets and 3 samples', 19 === count($d['presets']) && 3 === count($d['samples']));
$ids = array_column($d['presets'], 'id');
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
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
