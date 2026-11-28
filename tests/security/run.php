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
class WP_Post { public $ID = 0, $post_type = 'post', $post_status = 'publish', $post_password = '', $post_title = '', $post_date = '2026-01-01 00:00:00', $post_parent = 0, $post_author = 7; function __construct($a = []) { foreach ($a as $k => $v) { $this->$k = $v; } } }
// WordPress's newest-menu query, as the fallback runs it: published, by date, newest first (records the arguments).
class WP_Query { public $posts = []; function __construct($a) { $GLOBALS['queries'][] = $a; $GLOBALS['last_query'] = $a; $l = array_values(array_filter($GLOBALS['posts'], static fn($p) => $p->post_type === ($a['post_type'] ?? '') && $p->post_status === ($a['post_status'] ?? ''))); if (isset($a['date_query'][0])) { $d = $a['date_query'][0]; $in = !empty($d['inclusive']); $shift = $GLOBALS['date_shift'] ?? ''; $l = array_values(array_filter($l, static fn($p) => '' === $shift && ($in ? ($p->post_date >= $d['after'] && $p->post_date <= $d['before']) : ($p->post_date > $d['after'] && $p->post_date < $d['before'])))); } usort($l, static fn($x, $y) => strcmp($y->post_date, $x->post_date) ?: $y->ID <=> $x->ID); $this->posts = array_slice($l, 0, (int) ($a['posts_per_page'] ?? 10)); } }
function get_current_user_id() { return (int) ($GLOBALS['me'] ?? 0); }
function current_user_can($cap, ...$args) { return !empty($GLOBALS['caps'][$cap]); }
// Post types WordPress knows on this request (the glossary type is missing while its module is off).
function post_type_exists($t) { return in_array($t, $GLOBALS['registered'] ?? ['post', 'page', 'attachment', 'revision', 'wp_block', 'wp_navigation'], true); }
function get_post_type_object($t) { return 'wp_navigation' === $t ? (object) ['cap' => (object) ['edit_posts' => 'edit_theme_options']] : null; }
class WP_REST_Response { private $d; function __construct($d) { $this->d = $d; } function get_data() { return $this->d; } function set_data($d) { $this->d = $d; } }
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
$junk = [0, -1, -$menu, 0.0, -1.0, $menu + 0.5, 1e30, INF, -INF, NAN, 9007199254740992.0, '0', '-1', '+' . $menu, '0' . $menu, ' ' . $menu, $menu . ' ', $menu . 'a', '1e3', '0x1f', $menu . "\n", '', 'abc', '9223372036854775807', '99999999999999999999', [$menu], ['ref' => $menu], true, false, null, (object) ['ID' => $menu], new WP_Post(['ID' => $menu, 'post_type' => 'wp_navigation'])];
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

// ------------------------------------------------------------ fallback (a navigation block without a reference)
$fb = [['blockName' => 'core/navigation-link', 'attrs' => ['label' => 'x']]];
$only = static function (array $keep) { $GLOBALS['posts'] = array_filter($GLOBALS['posts'], static fn($p) => 'wp_navigation' !== $p->post_type || in_array($p->ID, $keep, true)); };
$saved = $GLOBALS['posts'];
$GLOBALS['posts'][$menu]->post_date = '2026-05-01 00:00:00'; $GLOBALS['posts'][$pwm]->post_date = '2026-06-01 00:00:00';
check('fallback: the newest published menu has a password: the fallback is emptied (nothing renders)', [] === omochix_core_security_nav_fallback($fb));
$q = $GLOBALS['last_query'];
$GLOBALS['queries'] = []; omochix_core_security_nav_fallback($fb); $q = $GLOBALS['queries'][0];
check('fallback: first the very query WordPress runs (published menus, newest by date, one)', ['post_type' => 'wp_navigation', 'no_found_rows' => true, 'update_post_meta_cache' => false, 'update_post_term_cache' => false, 'order' => 'DESC', 'orderby' => 'date', 'post_status' => 'publish', 'posts_per_page' => 1] === $q);
$GLOBALS['posts'][$pwm]->post_date = '2026-04-01 00:00:00';
check('fallback: the newest menu is allowed: WordPress\'s fallback is kept as it is', $fb === omochix_core_security_nav_fallback($fb));
$GLOBALS['posts'][$pwm]->post_date = '2026-05-01 00:00:00';
check('fallback: a refused menu published at the same moment as the newest one empties it too (either could be taken)', [] === omochix_core_security_nav_fallback($fb));
$GLOBALS['posts'][$pwm]->post_date = '2026-04-01 00:00:00';
$tie = []; for ($t = 0; $t < 3; $t++) { $tie[] = $mk(['post_type' => 'wp_navigation', 'post_date' => '2026-05-01 00:00:00']); }
check('fallback: three or more allowed menus at the same moment keep the fallback', $fb === omochix_core_security_nav_fallback($fb));
$GLOBALS['posts'][$tie[0]]->post_password = 'x';
check('fallback: a refused one among three or more at the same moment empties it (not only the first two are checked)', [] === omochix_core_security_nav_fallback($fb));
$GLOBALS['posts'][$tie[0]]->post_password = '';
for ($t = 0; $t < 20; $t++) { $tie[] = $mk(['post_type' => 'wp_navigation', 'post_date' => '2026-05-01 00:00:00']); }
check('fallback: more than 20 menus at one moment fails closed', [] === omochix_core_security_nav_fallback($fb));
foreach ($tie as $t) { unset($GLOBALS['posts'][$t]); }
$GLOBALS['posts'][$pwm]->post_date = '2026-05-01 00:00:00';
$GLOBALS['posts'][$pwm]->post_date = '2026-04-01 00:00:00';
$only([]);
check('fallback: no menu at all: the page list WordPress chose is kept', $fb === omochix_core_security_nav_fallback($fb));
$GLOBALS['posts'] = $saved;
$GLOBALS['posts'][$menu]->post_date = '2026-07-01 00:00:00';
$GLOBALS['date_shift'] = 'dst';
check('fallback: if the newest menu is not found again by its own date (a local time that does not exist), it fails closed', [] === omochix_core_security_nav_fallback($fb));
unset($GLOBALS['date_shift']);
check('fallback: the same-moment check includes the boundary (inclusive)', $fb === omochix_core_security_nav_fallback($fb) && true === end($GLOBALS['queries'])['date_query'][0]['inclusive']);
$GLOBALS['posts'][$menu]->post_date = '2026-05-01 00:00:00';
check('fallback: an empty or junk fallback is passed through', [] === omochix_core_security_nav_fallback([]) && false === omochix_core_security_nav_fallback(false) && null === omochix_core_security_nav_fallback(null) && 'x' === omochix_core_security_nav_fallback('x'));
check('fallback hook: registered once, last', 9999 === has_filter('block_core_navigation_render_fallback', 'omochix_core_security_nav_fallback') && 1 === count(array_filter(array_merge(...array_values($GLOBALS['hooks']['block_core_navigation_render_fallback'])), static fn($x) => 'omochix_core_security_nav_fallback' === $x[0])));

// ------------------------------------------------------------ REST: /wp/v2/navigation (context=embed returns content.raw)
$resp = static fn() => new WP_REST_Response(['id' => 1, 'title' => ['rendered' => 'T'], 'content' => ['raw' => '<!-- wp:navigation-link {"label":"SECRET"} /-->', 'rendered' => '<a>SECRET-HTML</a>', 'protected' => false, 'block_version' => 1]]);
$GLOBALS['caps'] = [];
$r = omochix_core_security_nav_rest($resp(), get_post($pwm))->get_data();
check('REST: a password-protected menu loses content.raw and block_version for a user who may not edit it; rendered empty, protected true, title kept', !isset($r['content']['raw']) && !isset($r['content']['block_version']) && '' === $r['content']['rendered'] && true === $r['content']['protected'] && ['rendered' => 'T'] === $r['title'] && false === strpos(json_encode($r), 'SECRET'));
$GLOBALS['caps'] = ['edit_post' => true];
check('REST: a user who may edit the menu keeps the raw blocks', isset(omochix_core_security_nav_rest($resp(), get_post($pwm))->get_data()['content']['raw']));
$GLOBALS['caps'] = [];
check('REST: a menu without a password, other post types and junk are untouched', isset(omochix_core_security_nav_rest($resp(), get_post($menu))->get_data()['content']['raw']) && isset(omochix_core_security_nav_rest($resp(), get_post($postpw))->get_data()['content']['raw']) && 'x' === omochix_core_security_nav_rest('x', get_post($pwm)) && isset(omochix_core_security_nav_rest($resp(), null)->get_data()['content']['raw']));
$nocontent = new WP_REST_Response(['id' => 1, 'title' => ['rendered' => 'T']]);
check('REST: a response without content (_fields) is left as it is', ['id' => 1, 'title' => ['rendered' => 'T']] === omochix_core_security_nav_rest($nocontent, get_post($pwm))->get_data());
$GLOBALS['caps'] = [];
$qa = ['post_type' => 'wp_navigation', 's' => '"example.org/pw', 'post__in' => [1]];
check('REST search: a user who may not edit menus searches without password-protected menus (no guessing at their content)', false === omochix_core_security_nav_rest_query($qa)['has_password'] && $qa['s'] === omochix_core_security_nav_rest_query($qa)['s']);
$GLOBALS['caps'] = ['edit_theme_options' => true];
check('REST search: a user who may edit menus searches them all', !isset(omochix_core_security_nav_rest_query($qa)['has_password']));
$GLOBALS['caps'] = [];
check('REST search: no search, an empty search and junk are untouched', ['post_type' => 'wp_navigation'] === omochix_core_security_nav_rest_query(['post_type' => 'wp_navigation']) && ['s' => ''] === omochix_core_security_nav_rest_query(['s' => '']) && 'x' === omochix_core_security_nav_rest_query('x'));
check('REST search hook: rest_wp_navigation_query, last', 9999 === has_filter('rest_wp_navigation_query', 'omochix_core_security_nav_rest_query'));
check('REST hook: rest_prepare_wp_navigation, last, with both arguments', 9999 === has_filter('rest_prepare_wp_navigation', 'omochix_core_security_nav_rest') && [['omochix_core_security_nav_rest', 2]] === array_values(array_filter($GLOBALS['hooks']['rest_prepare_wp_navigation'][9999], static fn($x) => 'omochix_core_security_nav_rest' === $x[0])));

// ------------------------------------------------------------ comments screen (P3-1): rows of a post type nobody registered
$GLOBALS['caps'] = [];
$tdraft = $mk(['post_type' => 'omx_glossary', 'post_status' => 'draft']);
$tfut   = $mk(['post_type' => 'omx_glossary', 'post_status' => 'future']);
$tpriv  = $mk(['post_type' => 'omx_glossary', 'post_status' => 'private']);
$trev   = $mk(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $term]);
$prev   = $mk(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $apost]);
$orev   = $mk(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => 0]);
$grev   = $mk(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => 999998]);
$terms  = [$term, $termpw, $tdraft, $tfut, $tpriv, $trev];
check('comments: before any comments list was built, titles are left alone (the filter only acts on that screen)', 'T' === omochix_core_security_comments_title('T', $term) && false === omochix_core_security_comments_screen());
check('comments: a row of an unregistered post type, every status (published, password, draft, scheduled, private) and its revision, is masked for a user who cannot edit others\' posts', [] === array_filter($terms, static fn($i) => !omochix_core_security_orphan_row_masked($i)) && omochix_core_security_orphan_row_masked(get_post($term)) && omochix_core_security_orphan_row_masked((string) $term));
check('comments: posts, pages, menus, synced patterns, revisions of posts, revisions without a parent or with a missing parent, unknown IDs and junk are not masked', [] === array_filter([$apost, $postpw, $page, $menu, $wpblk, $prev, $orev, $grev, 999999, 0, -1, null, 'abc', [$term]], static fn($i) => omochix_core_security_orphan_row_masked($i)));
$GLOBALS['caps'] = [];
$GLOBALS['post'] = get_post($term);
check('comments: ID 0, "" and null never fall back to the global post (get_post( 0 ) would)', !omochix_core_security_orphan_row_masked(0) && !omochix_core_security_orphan_row_masked('') && !omochix_core_security_orphan_row_masked(null) && !omochix_core_security_orphan_row_masked('0') && !omochix_core_security_orphan_row_masked(' ' . $term) && !omochix_core_security_orphan_row_masked((float) $term));
unset($GLOBALS['post']);
// Role changed after writing (review of PR #97): an editor or administrator wrote private and draft terms
// (and their revisions), then was made an author or contributor. Only the current capability counts.
$GLOBALS['me'] = 7;
$own = [$mk(['post_type' => 'omx_glossary', 'post_status' => 'private', 'post_author' => 7]), $mk(['post_type' => 'omx_glossary', 'post_status' => 'draft', 'post_author' => 7])];
$own[] = $mk(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $own[0], 'post_author' => 7]);
$own[] = $mk(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $own[1], 'post_author' => 7]);
check('comments: rows the user wrote while an editor (private, draft and their revisions) are masked once the user cannot edit others\' posts (authorship grants nothing)', [] === array_filter($own, static fn($i) => !omochix_core_security_orphan_row_masked($i)));
$GLOBALS['caps'] = ['edit_others_posts' => true];
check('comments: the same rows are visible again while the user is an editor', [] === array_filter($own, static fn($i) => omochix_core_security_orphan_row_masked($i)));
$GLOBALS['caps'] = [];
$trev8 = $mk(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $term, 'post_author' => 7]);
check('comments: a revision the user saved of someone else\'s row is masked too', omochix_core_security_orphan_row_masked($trev8));
unset($GLOBALS['me']);
$GLOBALS['caps'] = ['edit_others_posts' => true];
check('comments: editors and administrators (edit_others_posts) still see every row', [] === array_filter($terms, static fn($i) => omochix_core_security_orphan_row_masked($i)));
$GLOBALS['caps'] = [];
$GLOBALS['registered'] = ['post', 'page', 'attachment', 'revision', 'wp_block', 'wp_navigation', 'omx_glossary'];
check('comments: once the post type is registered (module loaded), its rows are left to WordPress and the module', [] === array_filter($terms, static fn($i) => omochix_core_security_orphan_row_masked($i)));
unset($GLOBALS['registered']);
$base = ['post_id' => $term, 'status' => 'all', 'number' => 20];
$a1 = omochix_core_security_comments_args($base);
check('comments list: a masked row lists no comments (post__not_in), post_id is kept for later filters, the rest is untouched', [$term] === $a1['post__not_in'] && $term === $a1['post_id'] && 'all' === $a1['status'] && 20 === $a1['number'] && true === omochix_core_security_comments_screen());
$a2 = omochix_core_security_comments_args(['post_id' => (string) $tdraft, 'post__not_in' => [5]]);
check('comments list: a post_id given as a digit string is handled; an existing post__not_in is kept', [5, $tdraft] === $a2['post__not_in']);
check('comments list: posts, unknown IDs, the whole-site list (post_id 0 or missing) and junk are untouched', ['post_id' => $apost] === omochix_core_security_comments_args(['post_id' => $apost]) && ['post_id' => 999999] === omochix_core_security_comments_args(['post_id' => 999999]) && ['post_id' => 0] === omochix_core_security_comments_args(['post_id' => 0]) && ['status' => 'all'] === omochix_core_security_comments_args(['status' => 'all']) && ['post_id' => 'x'] === omochix_core_security_comments_args(['post_id' => 'x']) && 'x' === omochix_core_security_comments_args('x'));
$GLOBALS['caps'] = ['edit_others_posts' => true];
check('comments list: for an editor the masked row is listed as usual', !isset(omochix_core_security_comments_args(['post_id' => $tpriv])['post__not_in']));
$GLOBALS['caps'] = [];
$GLOBALS['me'] = 7;
check('comments title: a former editor sees no title of the rows they wrote (private, draft, revisions)', [] === array_filter($own, static fn($x) => '' !== omochix_core_security_comments_title('T', $x)));
unset($GLOBALS['me']);
check('comments title: on the screen, a masked row has no title ("(no title)", as for an ID that does not exist), also in the "In response to" column', [] === array_filter($terms, static fn($i) => '' !== omochix_core_security_comments_title('T', $i)) && '' === omochix_core_security_comments_title('T', (string) $term));
check('comments title: posts, unknown IDs, ID 0 and junk keep their title', 'T' === omochix_core_security_comments_title('T', $apost) && 'T' === omochix_core_security_comments_title('T', 999999) && 'T' === omochix_core_security_comments_title('T', 0) && 'T' === omochix_core_security_comments_title('T') && 'T' === omochix_core_security_comments_title('T', 'abc') && 'T' === omochix_core_security_comments_title('T', null));
$GLOBALS['caps'] = ['edit_others_posts' => true];
check('comments title: an editor sees the title', 'T' === omochix_core_security_comments_title('T', $term) && 'T' === omochix_core_security_comments_title('T', $own[0]));
$GLOBALS['caps'] = [];
$zero = (object) ['approved' => 0, 'moderated' => 0, 'spam' => 0, 'trash' => 0, 'post-trashed' => 0, 'total_comments' => 0, 'all' => 0];
check('comments count: a masked row counts zero, the shape WordPress gives an ID that does not exist', $zero == omochix_core_security_comments_count([], $term) && $zero == omochix_core_security_comments_count([], (string) $tdraft));
check('comments count: other posts, rows not asked for on the screen, the whole site (0) and junk let WordPress count', [] === omochix_core_security_comments_count([], $apost) && [] === omochix_core_security_comments_count([], $tfut) && [] === omochix_core_security_comments_count([], 0) && [] === omochix_core_security_comments_count([], 'x') && 'k' === omochix_core_security_comments_count('k', 999999));
check('comments hooks: list query at 10 (before the module\'s own check), title and count last, with both arguments', 10 === has_filter('comments_list_table_query_args', 'omochix_core_security_comments_args') && 9999 === has_filter('the_title', 'omochix_core_security_comments_title') && 9999 === has_filter('wp_count_comments', 'omochix_core_security_comments_count') && [['omochix_core_security_comments_title', 2]] === array_values(array_filter($GLOBALS['hooks']['the_title'][9999], static fn($x) => 'omochix_core_security_comments_title' === $x[0])) && [['omochix_core_security_comments_count', 2]] === array_values(array_filter($GLOBALS['hooks']['wp_count_comments'][9999], static fn($x) => 'omochix_core_security_comments_count' === $x[0])));

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
