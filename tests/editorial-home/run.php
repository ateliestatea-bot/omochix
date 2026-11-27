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

// --- motion frames (AI Tools, Prompts, Learn): the markup is the finished frame ---
if (!function_exists('esc_url')) { function esc_url($u) { return $u; } }
if (!function_exists('esc_attr')) { function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES); } }
if (!function_exists('esc_html')) { function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES); } }
if (!function_exists('esc_html__')) { function esc_html__($t) { return esc_html($t); } }
if (!function_exists('get_theme_file_uri')) { function get_theme_file_uri($p = '') { return 'https://example.test/theme' . $p; } }
function motion_html($name, $class = 'x-class') { ob_start(); omochix_editorial_motion($name, $class); return ob_get_clean(); }
function in_order($html, array $needles) { $at = -1; foreach ($needles as $n) { $p = strpos($html, $n, $at + 1); if (false === $p) { return false; } $at = $p; } return true; }
$theme = __DIR__ . '/../../wp-theme/omochix-theme';
foreach (['tools', 'prompts', 'learn', 'about'] as $n) {
    $h = motion_html($n);
    check("motion $n: decorative frame with data-omx-motion, aria-hidden and the given class", 1 === preg_match('/^<div class="omx-mo omx-mo--' . $n . ' x-class" data-omx-motion aria-hidden="true">/', $h));
    check("motion $n: small logo, top left (406x90, empty alt)", false !== strpos($h, '<span class="omx-mo-logo"><img src="https://example.test/theme/assets/img/editorial/omochix-logo-wh.png" width="406" height="90" alt=""'));
    check("motion $n: rendered finished (no armed/playing state in markup)", false === strpos($h, 'is-armed') && false === strpos($h, 'is-playing'));
    check("motion $n: no prompt copy hooks, links or form controls inside", !preg_match('/data-prompt-text|data-copy|<a |<button|<input|tabindex/', $h));
}
$h = motion_html('tools');
check('motion tools: search field, then 文章・画像・開発 cards', in_order($h, ['omx-mo-search', '気になるAIを検索', 'omx-mo-cards', '>文章<', '>画像<', '>開発<']) && 3 === substr_count($h, 'omx-mo-card '));
$h = motion_html('prompts');
check('motion prompts: customer review sheet, then purchase insight sheet', in_order($h, ['omx-mo-sheet--review', '顧客レビュー', 'omx-mo-sheet--insight', '購買インサイト']));
$h = motion_html('learn');
check('motion learn: 知る・試す・作る steps, then the finished UI', in_order($h, ['omx-mo-stairs', '>知る<', '>試す<', '>作る<', 'omx-mo-ui ']));
$h = motion_html('about');
check('motion about: three trails, then the logo (no text, links or controls)', 3 === substr_count($h, 'omx-mo-trail"') && in_order($h, ['omx-mo-logo', 'omx-mo-stage', 'omx-mo-trail']));
ob_start(); omochix_editorial_motion('feature', 'y'); $h = ob_get_clean();
check('motion: other names fall back to the still image', 0 === strpos($h, '<img class="y" src="https://example.test/theme/assets/img/editorial/feature-v1.webp"'));
$logo = @getimagesize($theme . '/assets/img/editorial/omochix-logo-wh.png');
check('motion: supplied logo is a 406x90 PNG', $logo && 406 === $logo[0] && 90 === $logo[1] && IMAGETYPE_PNG === $logo[2]);
$front = file_get_contents($theme . '/front-page.php');
check('front page: the AI Tools, Prompts and Learn frames use their motion', 1 === substr_count($front, "omochix_editorial_motion('prompts', 'ed-practice-visual')") && 1 === substr_count($front, "omochix_editorial_motion('learn', 'ed-practice-visual')") && 1 === substr_count($front, "omochix_editorial_motion('tools', 'ed-tools-visual')"));
check('front page: the about frame uses its own motion; learn-v1.webp stays for the shared still image', 1 === substr_count($front, "omochix_editorial_motion('about', 'ed-about-visual')") && false === strpos($front, "omochix_editorial_visual('learn', 'ed-about-visual')") && is_file($theme . '/assets/img/editorial/learn-v1.webp'));
check('front page: prompt full-text copy is kept', false !== strpos($front, 'data-prompt-copy-scope') && false !== strpos($front, 'data-prompt-text') && false !== strpos($front, 'data-copy-prompt data-copy-target="ed-prompt-text"'));
$css = file_get_contents($theme . '/assets/css/editorial-motion.css');
preg_match_all('/@keyframes\s+([\w-]+)\s*\{(.*?)\}\s*\}/s', $css, $kf, PREG_SET_ORDER);
$bad = [];
foreach ($kf as $k) { if (preg_match('/\b(to|100%)\s*[{,]/', $k[2]) && 'omx-mo-caret' !== $k[1]) { $bad[] = $k[1]; } }
check('motion css: keyframes end on the plain rules (no "to"/100% step; caret ends hidden like its rule)', count($kf) >= 10 && !$bad && 1 === preg_match('/@keyframes omx-mo-caret \{[^@]*?20%, 60%, 100% \{ opacity: 0; \} \}/', $css) && 1 === preg_match('/\.omx-mo--tools \.omx-mo-caret \{[^}]*opacity: 0;/', $css));
$plain = preg_replace(['/@keyframes[^{]*\{.*?\}\s*\}/s', '/@media \((?:prefers-reduced-motion: reduce|print)\)? ?\{\s*\.omx-mo,\s*\.omx-mo::before,\s*\.omx-mo \* \{ animation: none !important; \}\s*\}/', '/@media print \{\s*\.omx-mo,\s*\.omx-mo::before,\s*\.omx-mo \* \{ animation: none !important; \}\s*\}/'], '', $css);
preg_match_all('/([^{}]+)\{[^}]*\banimation(?:-name)?\s*:/', $plain, $an);
$unguarded = array_filter($an[1], static fn($sel) => false === strpos($sel, ':is(.is-armed, .is-playing)'));
check('motion css: animations run only while armed or playing', count($an[1]) > 10 && !$unguarded);
check('motion css: reduced motion and print show the finished frame', 1 === preg_match('/@media \(prefers-reduced-motion: reduce\) \{\s*\.omx-mo,\s*\.omx-mo::before,\s*\.omx-mo \* \{ animation: none !important; \}/', $css) && 1 === preg_match('/@media print \{\s*\.omx-mo,/', $css));
check('motion css: armed frames wait on their first frame', false !== strpos($css, '.omx-mo.is-armed * { animation-play-state: paused !important; }'));
check('motion css: dark mode only swaps colours (custom properties)', 1 === preg_match('/:root\[data-theme="dark"\] \.omx-mo \{([^}]*)\}/', $css, $dm) && !preg_match('/^\s*(?!--)[a-z-]+\s*:/m', $dm[1]));
$js = file_get_contents($theme . '/assets/js/editorial-motion.js');
check('motion js: plays once per frame on first view, reduced motion skips it', false !== strpos($js, 'observer.unobserve(entry.target)') && false !== strpos($js, "matchMedia('(prefers-reduced-motion: reduce)')") && false !== strpos($js, 'if (reduce && reduce.matches) return;'));
check('motion js: no replay hooks (theme switch, storage, buttons)', !preg_match('/data-theme|MutationObserver|localStorage|sessionStorage|addEventListener\(\'click\'|createElement/', $js));
$inc = file_get_contents($theme . '/inc/editorial-home.php');
check('motion assets: enqueued on the front page only, script in footer', false !== strpos($inc, "wp_enqueue_style('omochix-editorial-motion'") && false !== strpos($inc, "'/assets/js/editorial-motion.js'), [], (string) filemtime(get_theme_file_path('/assets/js/editorial-motion.js')), true);"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
