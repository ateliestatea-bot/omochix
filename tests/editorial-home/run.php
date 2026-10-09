<?php
/**
 * Regression tests for inc/editorial-home.php using a minimal WordPress stub.
 * Run: php tests/editorial-home/run.php   (no WordPress, DB or network needed)
 * The stub WP_Query honours the query args the selection code relies on; it is
 * not a substitute for a real WordPress run.
 */
define('ABSPATH', '/');
class WP_Post { public $ID, $post_type, $post_status = 'publish', $post_password = '', $post_date; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
class WP_Term { public $term_id = 5; }
$GLOBALS['posts'] = []; $GLOBALS['meta'] = [];
function __($t) { return $t; }
function get_theme_file_path($p = '') { return $p; }
function get_option($n, $d = false) { return $d; }
function absint($v) { return abs((int) $v); }
function get_category_by_slug($s) { return null; }
function post_type_exists($t) { return true; }
function get_post_meta($id, $k, $single = false) { return $GLOBALS['meta'][$id][$k] ?? ''; }
function wp_list_pluck($l, $f) { return array_map(static fn($o) => $o->$f, $l); }
function omochix_get_active_ai_tool_meta_query() { return ['relation' => 'OR', ['key' => 'tool_status', 'compare' => 'NOT EXISTS'], ['key' => 'tool_status', 'value' => 'discontinued', 'compare' => '!=']]; }
function add_filter() {} function add_action() {}
class WP_Query {
    public $posts = [];
    function __construct($a) {
        $o = [];
        foreach ($GLOBALS['posts'] as $p) {
            if ($p->post_type !== $a['post_type'] || $p->post_status !== ($a['post_status'] ?? 'publish')) { continue; }
            if (isset($a['has_password']) && $a['has_password'] === false && $p->post_password !== '') { continue; }
            if (in_array($p->ID, $a['post__not_in'] ?? [], true)) { continue; }
            if (isset($a['post__in']) && !in_array($p->ID, $a['post__in'], true)) { continue; }
            if (!$this->meta($p, $a['meta_query'] ?? [])) { continue; }
            $o[] = $p;
        }
        usort($o, static fn($x, $y) => [$y->post_date, $y->ID] <=> [$x->post_date, $x->ID]);
        $n = $a['posts_per_page'] ?? 10;
        if ($n > 0) { $o = array_slice($o, (($a['paged'] ?? 1) - 1) * $n, $n); }
        $this->posts = $o;
    }
    private function meta($p, $q) {
        $rel = $q['relation'] ?? 'AND'; $res = [];
        foreach ($q as $k => $c) {
            if ($k === 'relation') { continue; }
            if (isset($c['relation']) || !isset($c['key'])) { $res[] = $this->meta($p, $c); continue; }
            $has = isset($GLOBALS['meta'][$p->ID][$c['key']]); $v = $GLOBALS['meta'][$p->ID][$c['key']] ?? null;
            switch ($c['compare']) {
                case 'NOT EXISTS': $res[] = !$has; break;
                case '!=': $res[] = $has && $v != $c['value']; break;
                case 'IN': $res[] = $has && in_array((string) $v, $c['value'], true); break;
                default: $res[] = false;
            }
        }
        return $rel === 'OR' ? in_array(true, $res, true) : !in_array(false, $res, true);
    }
}
require __DIR__ . '/../../wp-theme/omochix-theme/inc/editorial-home.php';

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }
function reset_data() { $GLOBALS['posts'] = []; $GLOBALS['meta'] = []; }
function add($id, $type, $date, $meta = [], $extra = []) {
    $GLOBALS['posts'][] = new WP_Post(['ID' => $id, 'post_type' => $type, 'post_date' => $date] + $extra);
    $GLOBALS['meta'][$id] = $meta;
}
function ids($l) { return array_map(static fn($p) => $p->ID, $l); }
function tools() { return ids(omochix_editorial_tools(3)); }

// --- AI Tools ---
reset_data();
foreach ([1 => 10, 2 => 20, 3 => 30, 4 => 40, 5 => 5] as $id => $ord) { add($id, 'ai_tool', "2026-01-0$id 10:00:00", ['is_featured' => '1', 'display_order' => (string) $ord]); }
add(6, 'ai_tool', '2026-02-01 10:00:00', ['is_featured' => '1', 'display_order' => '1']); // newest, order 1
check('featured>=4: lowest display_order wins even when older (5,1,2)', tools() === [6, 5, 1]);
check('featured>=4: exactly 3 returned', count(tools()) === 3);

reset_data();
add(1, 'ai_tool', '2026-01-01 00:00:00', ['is_featured' => '1']);
add(2, 'ai_tool', '2026-01-02 00:00:00', ['is_featured' => '1', 'display_order' => '3']);
add(3, 'ai_tool', '2026-01-03 00:00:00', ['is_featured' => 'yes', 'display_order' => '']);
add(4, 'ai_tool', '2026-01-04 00:00:00', ['is_featured' => 'true', 'display_order' => 'abc']);
check('unset/empty/non-numeric display_order goes last, newest first', tools() === [2, 4, 3]);

reset_data();
foreach ([1, 2, 3, 4] as $i) { add($i, 'ai_tool', '2026-01-01 00:00:00', ['is_featured' => '1', 'display_order' => '7']); }
check('duplicate order + same date: deterministic by ID desc', tools() === [4, 3, 2]);
check('deterministic across repeated runs', tools() === tools());
reset_data();
add(1, 'ai_tool', '2026-01-01 00:00:00', ['is_featured' => '1', 'display_order' => '2']);
add(2, 'ai_tool', '2026-01-09 00:00:00', ['is_featured' => '1', 'display_order' => '2']);
check('duplicate order: newer date first', tools() === [2, 1]);

reset_data();
add(1, 'ai_tool', '2026-01-01 00:00:00', ['is_featured' => '1', 'display_order' => '1', 'tool_status' => 'discontinued']);
add(2, 'ai_tool', '2026-01-02 00:00:00', ['is_featured' => '1', 'display_order' => '2', 'tool_status' => 'active']);
add(3, 'ai_tool', '2026-01-03 00:00:00', ['is_featured' => '1', 'display_order' => '3'], ['post_status' => 'draft']);
add(4, 'ai_tool', '2026-01-04 00:00:00', ['is_featured' => '1', 'display_order' => '4'], ['post_password' => 'x']);
add(5, 'ai_tool', '2026-01-05 00:00:00', ['is_featured' => '1', 'display_order' => '5'], ['post_status' => 'private']);
check('discontinued, draft, private, password-protected excluded', tools() === [2]);

reset_data();
add(1, 'ai_tool', '2026-01-01 00:00:00', ['is_featured' => '1', 'display_order' => '1']);
add(2, 'ai_tool', '2026-01-02 00:00:00', []);
add(3, 'ai_tool', '2026-01-03 00:00:00', ['tool_status' => 'discontinued']);
add(4, 'ai_tool', '2026-01-04 00:00:00', [], ['post_status' => 'draft']);
add(5, 'ai_tool', '2026-01-05 00:00:00', ['is_featured' => '0']);
check('shortage: featured first, filled with newest active public, no duplicates, no excluded', tools() === [1, 5, 2]);
check('no duplicate IDs', count(array_unique(tools())) === count(tools()));
reset_data();
check('no tools: empty list', tools() === []);

// --- Prompts ---
check('body: plain text valid', omochix_editorial_has_prompt_body('hello'));
foreach (['empty' => '', 'spaces' => '   ', 'newlines' => "\n\r\n\t", 'nbsp' => "\u{00A0}", 'ideographic space' => "\u{3000}\u{3000}", 'zero-width' => "\u{200B}\u{FEFF}", 'mixed' => " \u{3000}\n\u{200B}\u{2028} ", 'non-string' => null] as $label => $body) {
    check("body: $label rejected", !omochix_editorial_has_prompt_body($body));
}
check('body: Japanese text with surrounding blanks valid', omochix_editorial_has_prompt_body("\u{3000}日本語\n"));
check('body: invalid UTF-8 falls back safely', omochix_editorial_has_prompt_body("\xff\xfe") === true);

reset_data();
add(10, 'prompt', '2026-03-01 00:00:00', ['prompt_body' => "valid old"]);
for ($i = 11; $i < 11 + 45; $i++) { add($i, 'prompt', '2026-04-01 00:00:' . sprintf('%02d', $i - 11), ['prompt_body' => "\u{3000} \n"]); }
$p = omochix_editorial_prompt();
check('prompt: 45 blank newer bodies cannot hide a valid one (paging)', $p && $p->ID === 10);
reset_data();
add(1, 'prompt', '2026-01-01 00:00:00', ['prompt_body' => 'older']);
add(2, 'prompt', '2026-01-02 00:00:00', ['prompt_body' => 'newer']);
add(3, 'prompt', '2026-01-03 00:00:00', ['prompt_body' => 'draft'], ['post_status' => 'draft']);
add(4, 'prompt', '2026-01-04 00:00:00', ['prompt_body' => 'pw'], ['post_password' => 'x']);
add(5, 'prompt', '2026-01-05 00:00:00', ['prompt_body' => ' ']);
add(6, 'prompt', '2026-01-06 00:00:00', []);
check('prompt: newest valid public, no password/draft/blank/missing', omochix_editorial_prompt()->ID === 2);
reset_data();
add(1, 'prompt', '2026-01-01 00:00:00', ['prompt_body' => " \n"]);
check('prompt: none valid -> null (empty state)', omochix_editorial_prompt() === null);
reset_data();
check('prompt: no posts -> null', omochix_editorial_prompt() === null);

// --- selection wiring ---
reset_data();
add(1, 'ai_tool', '2026-01-01 00:00:00', ['is_featured' => '1']);
add(2, 'prompt', '2026-01-01 00:00:00', ['prompt_body' => 'x']);
$sel = omochix_editorial_selection();
check('selection returns tools and prompt', ids($sel['tools']) === [1] && $sel['prompt']->ID === 2);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
