<?php
/**
 * Learn v2 preview server: renders page-learn.php and page-learn-gallery.php
 * with a minimal WordPress stub, so the templates, CSS, JS and sandboxed
 * previews can be checked in a browser without WordPress or a database.
 *
 * Run from the repository root:
 *   php -S 127.0.0.1:8787 -t . tests/learn-gallery/preview-server.php
 * Then open http://127.0.0.1:8787/learn/ , /learn/motion/ , /learn/motion/pop-in/
 *
 * Not a substitute for a real WordPress run (no shell sidebar markup, no
 * Slim SEO, fake prompts). Pages, prompts and meta below are local fixtures.
 */
$ROOT  = realpath(__DIR__ . '/../..');
$THEME = $ROOT . '/wp-theme/omochix-theme';
$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($ROOT . $path)) { return false; } // static assets

define('ABSPATH', '/');
define('OBJECT', 'OBJECT');
define('WP_DEBUG', true);
class WP_Post { public $ID, $post_type = 'page', $post_status = 'publish', $post_name = '', $post_title = '', $post_excerpt = '', $post_content = '', $post_parent = 0; function __construct($a) { foreach ($a as $k => $v) { $this->$k = $v; } } }
class WP_Term { public $name; function __construct($n) { $this->name = $n; } }
$GLOBALS['hooks'] = [];
function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['hooks'][$h][] = [$p, $cb]; }
function add_filter($h, $cb, $p = 10, $n = 1) { add_action($h, $cb, $p, $n); }
function do_action_stub($h) { $cbs = $GLOBALS['hooks'][$h] ?? []; usort($cbs, static fn($a, $b) => $a[0] <=> $b[0]); foreach ($cbs as [$p, $cb]) { $cb(); } }
function __($t, $d = '') { return $t; }
function _e($t, $d = '') { echo $t; }
function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function esc_attr($t) { return esc_html($t); }
function esc_url($t) { return esc_html($t); }
function esc_html_e($t, $d = '') { echo esc_html($t); }
function esc_attr_e($t, $d = '') { echo esc_attr($t); }
function esc_html__($t, $d = '') { return esc_html($t); }
function get_theme_file_path($f) { global $THEME; return $THEME . $f; }
function get_theme_file_uri($f) { return '/wp-theme/omochix-theme' . $f; }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function wp_unslash($v) { return $v; }
function sanitize_title($t) { $t = strtolower(trim((string) $t)); $t = preg_replace('/[^a-z0-9-]+/', '-', $t); return trim($t, '-'); }
function sanitize_key($t) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $t)); }
function sanitize_text_field($t) { return trim(strip_tags((string) $t)); }
function wp_strip_all_tags($t) { return trim(strip_tags((string) $t)); }
function wp_trim_words($t, $n = 55, $m = '…') { return mb_strlen($t) > 60 ? mb_substr($t, 0, 60) . $m : $t; }
function number_format_i18n($n) { return number_format((float) $n); }
function selected($a, $b) { echo $a === $b ? 'selected' : ''; }
function checked($a, $b = true) { echo $a === $b ? 'checked' : ''; }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function user_trailingslashit($s) { return trailingslashit($s); }
// Like WordPress, values are expected to be urlencoded by the caller already.
function add_query_arg($args, $url) { $pairs = []; foreach ($args as $k => $v) { $pairs[] = $k . '=' . $v; } return $url . '?' . implode('&', $pairs); }
function home_url($p = '/') { return 'http://' . $_SERVER['HTTP_HOST'] . $p; }
function get_bloginfo($k) { return $k === 'charset' ? 'UTF-8' : 'OmochiX'; }
function status_header($c) { http_response_code($c); }
function nocache_headers() {}
function post_type_exists($t) { return $t === 'prompt'; }
function get_post_type_archive_link($t) { return $t === 'prompt' ? '/prompts/' : ''; }
function get_option($k, $d = false) { return $d; }
function update_option() {} function delete_option() {} function flush_rewrite_rules() {} function add_rewrite_rule() {}
function wp_style_is() { return true; }
function wp_enqueue_style() {} function wp_enqueue_script() {} function wp_add_inline_script() {}
function wp_get_theme() { return new class { function get($k) { return 'preview'; } }; }
function get_page_by_path($path, $o = null, $t = 'page') { return $GLOBALS['pages'][$path] ?? null; }
function get_permalink($p) { if ($p instanceof WP_Post) { return home_url('/' . $p->post_name . '/'); } foreach ($GLOBALS['pages'] as $pg) { if ($pg->ID === $p) { return home_url('/' . $pg->post_name . '/'); } } return ''; }
function get_the_title($p = null) { return $p instanceof WP_Post ? $p->post_title : ($GLOBALS['post']->post_title ?? ''); }
function the_title() { echo esc_html(get_the_title()); }
function has_excerpt($p = null) { $p = $p ?: $GLOBALS['post']; return '' !== $p->post_excerpt; }
function get_the_excerpt($p = null) { $p = $p ?: $GLOBALS['post']; return $p->post_excerpt; }
function get_the_content() { return $GLOBALS['post']->post_content; }
function the_content() { echo $GLOBALS['post']->post_content; }
function get_post_meta($id, $k, $s = false) { return $GLOBALS['meta'][$id][$k] ?? ''; }
function get_the_terms($id, $tax) { return [new WP_Term('ChatGPT')]; }
function get_posts($args) { if (($args['post_type'] ?? '') === 'prompt' && empty($args['post_name__in'])) { return $GLOBALS['prompts']; } return []; }
function get_pages($a) { return []; }
function get_post_ancestors($p) { return []; }
function get_query_var($k, $d = '') { return $GLOBALS['qv'][$k] ?? $d; }
function is_page() { return true; }
function get_queried_object_id() { return $GLOBALS['post']->ID; }
function get_queried_object() { return $GLOBALS['post']; }
function get_template_part($slug, $name = null, $args = []) { global $THEME; include $THEME . '/' . $slug . '.php'; }
function have_posts() { static $n = 0; return $n++ < 1; }
function the_post() {}
function get_header() { global $post;
    echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html($post->post_title) . ' | preview</title>';
    if (isset($_GET['omx_reduce'])) {
        // QA switch: pretend the viewer prefers reduced motion (parent page only; the sandboxed previews use the real OS setting).
        echo '<script>window.matchMedia=function(q){return {matches:/prefers-reduced-motion:\s*reduce/.test(q),media:q,onchange:null,addEventListener:function(){},removeEventListener:function(){},addListener:function(){},removeListener:function(){}};};</script>';
    }
    echo '<script>(function(){try{var s=localStorage.getItem("omochix-theme");var d=window.matchMedia("(prefers-color-scheme: dark)").matches;document.documentElement.dataset.theme=s||(d?"dark":"light");}catch(e){}}());</script>';
    echo '<link rel="stylesheet" href="/wp-theme/omochix-theme/style.css"><link rel="stylesheet" href="/wp-theme/omochix-theme/assets/css/shell.css"><link rel="stylesheet" href="/wp-theme/omochix-theme/assets/css/learn-v2.css"><link rel="stylesheet" href="/wp-theme/omochix-theme/assets/css/learn-composer.css"></head>';
    echo '<body class="omx-shell page"><a class="skip-link" href="#main-content">本文へ移動</a><header class="site-header" data-site-header><div class="site-header__inner"><a class="site-logo" href="/learn/"><img class="site-logo__image" src="/wp-theme/omochix-theme/assets/img/logo-lockup-horizontal.svg" width="142" height="36" alt="OmochiX"></a>';
    echo '<nav class="primary-nav" id="primary-navigation" data-primary-nav><ul class="primary-nav__list"><li><a href="/learn/">Learn</a></li><li><a href="/learn/motion/">動く見本</a></li><li><a href="/prompts/">Prompts</a></li></ul></nav>';
    echo '<div class="site-header__actions"><button class="icon-button" type="button" data-theme-toggle aria-label="ダークモードに切り替える"><svg class="icon icon--sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"/></svg><svg class="icon icon--moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4a8.5 8.5 0 1 0 11.2 11.2Z"/></svg></button></div></div></header>';
}
function get_footer() { echo '<footer class="site-footer" style="padding:40px 0;text-align:center;color:var(--color-muted);font-size:12px">preview footer</footer>';
    do_action_stub('wp_footer');
    echo '<script src="/wp-theme/omochix-theme/assets/js/header.js"></script><script>window.omochixLearnGallery=' . json_encode(['listUrl' => omochix_learn_gallery_url(), 'partialParam' => 'omx_partial', 'strings' => ['play' => '再生', 'pause' => '一時停止', 'pauseAll' => 'すべて一時停止', 'playAll' => 'すべて再生', 'copied' => 'コピーしました', 'copyFailed' => 'コピーできませんでした', 'count' => '%s件の見本', 'noResults' => '条件に合う見本がありません', 'loadFailed' => '読み込めませんでした', 'linkCopied' => 'リンクをコピーしました', 'resetDone' => '既定の値に戻しました']], JSON_UNESCAPED_UNICODE) . ';</script><script src="/wp-theme/omochix-theme/assets/js/learn-params.js"></script><script src="/wp-theme/omochix-theme/assets/js/learn-gallery.js"></script><script src="/wp-theme/omochix-theme/assets/js/learn-playground.js"></script><script>window.omochixLearnComposer=' . json_encode(['maxSlots' => OMOCHIX_LEARN_COMPOSER_MAX_SLOTS, 'hashKey' => OMOCHIX_LEARN_COMPOSER_HASH_KEY, 'hashVersion' => OMOCHIX_LEARN_COMPOSER_HASH_VERSION, 'noteMax' => OMOCHIX_LEARN_COMPOSER_NOTE_MAX, 'presets' => omochix_learn_composer_presets(), 'strings' => ['slotEmpty' => '見本を選ぶ', 'linkCopied' => 'リンクをコピーしました', 'copyFailed' => 'コピーできませんでした', 'full' => '3件まで選べます。', 'added' => '%sを追加しました', 'removed' => '%sを外しました', 'moved' => '%sを%d番目に移動しました', 'cleared' => 'すべて外しました', 'restored' => '前回の編集状態を復元しました', 'adjust' => '調整する', 'adjustClose' => '調整を閉じる', 'adjusted' => '%d項目を調整中', 'panelFailed' => '調整パネルを読み込めませんでした。', 'retry' => 'もう一度読み込む', 'notes' => '用途', 'notesClose' => '用途を閉じる', 'custom' => '自由入力…', 'noteEmpty' => '（未指定）', 'briefCopied' => '指示書をコピーしました', 'jsonCopied' => 'JSONをコピーしました', 'jsonInvalid' => '読み込めるJSONではありません。', 'jsonLoaded' => 'JSONから読み込みました', 'noParams' => '調整した値: なし'], 'panelParam' => 'omx_panel', 'partialParam' => 'omx_partial', 'brief' => omochix_learn_composer_brief_templates(), 'assistants' => omochix_learn_composer_assistants()], JSON_UNESCAPED_UNICODE) . ';</script><script src="/wp-theme/omochix-theme/assets/js/learn-composer-core.js"></script><script src="/wp-theme/omochix-theme/assets/js/learn-composer.js"></script></body></html>';
}

// Local fixtures (not real content).
$GLOBALS['pages'] = [
    'learn'             => new WP_Post(['ID' => 10, 'post_name' => 'learn', 'post_title' => 'Learn']),
    'learn/motion'      => new WP_Post(['ID' => 42, 'post_name' => 'learn/motion', 'post_title' => '動く見本', 'post_parent' => 10]),
    'learn/motion/compose' => new WP_Post(['ID' => 43, 'post_name' => 'learn/motion/compose', 'post_title' => '動きを組み合わせる', 'post_parent' => 42]),
    'learn/basics'      => new WP_Post(['ID' => 11, 'post_name' => 'learn/basics', 'post_title' => 'AI入門']),
    'learn/development' => new WP_Post(['ID' => 12, 'post_name' => 'learn/development', 'post_title' => '開発入門', 'post_excerpt' => 'AIを使った開発の基礎']),
];
$GLOBALS['prompts'] = [
    new WP_Post(['ID' => 101, 'post_type' => 'prompt', 'post_name' => 'prompts/sample-a', 'post_title' => '（検証用）商談前ヒアリング設計プロンプト', 'post_excerpt' => '相手企業の情報と商談目的を入力するだけで、仮説・質問リストまで作成します。']),
    new WP_Post(['ID' => 102, 'post_type' => 'prompt', 'post_name' => 'prompts/sample-b', 'post_title' => '（検証用）LPのキャッチコピー10案', 'post_excerpt' => 'ターゲットと提供価値から、トーン別に10案のキャッチコピーを出します。']),
    new WP_Post(['ID' => 103, 'post_type' => 'prompt', 'post_name' => 'prompts/sample-c', 'post_title' => '（検証用）議事録を3行に要約', 'post_excerpt' => '長い議事録から決定事項・宿題・期限だけを抜き出します。']),
];
$GLOBALS['meta'] = [101 => ['prompt_difficulty' => 'beginner'], 102 => ['prompt_difficulty' => 'intermediate'], 103 => ['prompt_difficulty' => 'advanced']];
$GLOBALS['wp_query'] = new class { public function set_404() { http_response_code(404); } };

require $THEME . '/inc/learn.php';
require $THEME . '/inc/learn-gallery.php';
require $THEME . '/inc/learn-composer.php';
function omochix_get_published_page_url($path) { $p = get_page_by_path($path); return $p ? get_permalink($p) : ''; }

// Routing: list, detail (with ?omx_partial=1 fragment), hub.
$GLOBALS['qv'] = [];
if (preg_match('#^/learn/motion/compose/?$#', $path)) {
    $GLOBALS['post'] = $GLOBALS['pages']['learn/motion/compose'];
    include $THEME . '/page-learn-composer.php';
} elseif (preg_match('#^/learn/motion/([a-z0-9-]+)/?$#', $path, $m)) {
    $GLOBALS['post'] = $GLOBALS['pages']['learn/motion'];
    $GLOBALS['qv']['omx_motion'] = $m[1];
    if (isset($_GET['omx_partial'])) { $GLOBALS['qv']['omx_partial'] = '1'; }
    if (isset($_GET['omx_panel'])) { $GLOBALS['qv']['omx_panel'] = '1'; }
    do_action_stub('template_redirect');
    if (http_response_code() === 404) { echo '<!doctype html><meta charset="utf-8"><h1>404 (preview stub)</h1>'; exit; }
    include $THEME . '/page-learn-gallery.php';
} elseif (preg_match('#^/learn/motion/?$#', $path)) {
    $GLOBALS['post'] = $GLOBALS['pages']['learn/motion'];
    include $THEME . '/page-learn-gallery.php';
} elseif (preg_match('#^/learn/?$#', $path) || $path === '/') {
    $GLOBALS['post'] = $GLOBALS['pages']['learn'];
    include $THEME . '/page-learn.php';
} else {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><p>not found (preview stub)</p>';
}
