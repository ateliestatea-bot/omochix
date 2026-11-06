<?php
/**
 * Learn / SVG Motion Studio: logo motion presets.
 *
 * Page: "/learn/svg-motion/" (a child page of Learn). Nothing is uploaded or
 * stored on the server: no options, posts, REST routes or media uploads. All
 * SVG handling happens in the browser, and a logo reaches the screen only as an
 * image (see assets/js/svg-motion/). Independent of the Motion Gallery.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

const OMOCHIX_SVG_MOTION_PATH = 'learn/svg-motion';

/**
 * Limits taken from the measurements in docs/motion/SVG-MOTION-S1B-RESULT.md:
 * 200 animated parts or shapes held 60fps at a 20x CPU slowdown. Above maxParts
 * a logo animates as one piece; above maxShapes the per-shape presets are off.
 */
const OMOCHIX_SVG_MOTION_BUDGET = ['maxParts' => 200, 'maxShapes' => 200];

/** Published page ID, or 0. */
function omochix_svg_motion_page_id() {
    static $page_id = null;
    if (null !== $page_id) {
        return $page_id;
    }
    $page    = get_page_by_path(OMOCHIX_SVG_MOTION_PATH, OBJECT, 'page');
    $page_id = $page instanceof WP_Post && 'publish' === $page->post_status ? (int) $page->ID : 0;
    return $page_id;
}

/** Whether the main query is the studio page. */
function omochix_svg_motion_is_request() {
    if (!is_page()) {
        return false;
    }
    $page_id = omochix_svg_motion_page_id();
    return $page_id && get_queried_object_id() === $page_id;
}

/**
 * Presets and sample logos, or an empty list when the file is unusable.
 *
 * @return array{presets: array<int, array<string, mixed>>, samples: array<int, array<string, string>>}
 */
function omochix_svg_motion_data() {
    static $data = null;
    if (null !== $data) {
        return $data;
    }
    $empty = ['presets' => [], 'samples' => []];
    $file  = get_theme_file_path('/assets/data/svg-motion/presets.json');
    $raw   = is_readable($file) ? file_get_contents($file) : false;
    $json  = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($json) || empty($json['presets']) || empty($json['samples'])) {
        $data = $empty;
        return $data;
    }
    $data = ['common' => $json['common'] ?? [], 'presets' => $json['presets'], 'samples' => $json['samples']];
    return $data;
}

/** Ways to use an exported logo, in the order the page offers them. */
function omochix_svg_motion_usages() {
    return [
        'image'    => ['label' => __('画像として使う（おすすめ）', 'omochix'), 'note' => __('2 つの SVG ファイルを置いて、このコードを貼ります。スクリプト不要で、多くのサイトでそのまま動きます。動きを減らす設定の人には静止画を出します。', 'omochix')],
        'inline'   => ['label' => __('HTML に直接書く', 'omochix'), 'note' => __('ファイルを置かずに使えます。CSP でインラインのスタイルを禁止しているサイトでは動かず、静止したロゴになります。WordPress の投稿本文には向きません。', 'omochix')],
        'external' => ['label' => __('HTML と CSS ファイル', 'omochix'), 'note' => __('CSS ファイルを置いて読み込みます。CSP のあるサイト向けです。', 'omochix')],
        'script'   => ['label' => __('JavaScript で操作する', 'omochix'), 'note' => __('画面に入ったときに動き出し、再生・停止・やり直しを操作できます（window.omxLogoMotion）。', 'omochix')],
    ];
}

/** What studio.js shows the viewer. Reasons say what happened and, where possible, what to do. */
function omochix_svg_motion_messages() {
    return [
        'errors'      => [
            'DEFAULT'             => __('この SVG は読み込めませんでした。', 'omochix'),
            'EMPTY_INPUT'         => __('SVG コードを貼り付けてください。', 'omochix'),
            'NOT_SVG_FILE'        => __('SVG ファイルを選んでください（拡張子 .svg）。', 'omochix'),
            'READ_FAILED'         => __('ファイルを読み取れませんでした。もう一度お試しください。', 'omochix'),
            'NOT_UTF8'            => __('文字コードが UTF-8 ではありません。UTF-8 で書き出してください。', 'omochix'),
            'TOO_MANY_FILES'      => __('ファイルは 1 つずつ入れてください。', 'omochix'),
            'NOT_STRING'          => __('SVG として読み取れませんでした。', 'omochix'),
            'TOO_LARGE'           => __('ファイルが大きすぎます（上限 1MB）。不要な要素を減らしてください。', 'omochix'),
            'PARSE_ERROR'         => __('SVG として読み取れませんでした。ファイルが壊れていないか確認してください。', 'omochix'),
            'NOT_SVG'             => __('SVG ではないようです。先頭が svg 要素のデータを入れてください。', 'omochix'),
            'DOCTYPE_REJECTED'    => __('安全のため、DOCTYPE を含む SVG は読み込めません。書き出しの設定で外してください。', 'omochix'),
            'ELEMENT_LIMIT'       => __('要素が多すぎます（上限 5,000）。図形をまとめてください。', 'omochix'),
            'DEPTH_LIMIT'         => __('入れ子が深すぎます（上限 32 段）。グループをまとめてください。', 'omochix'),
            'PATH_LIMIT'          => __('パスが複雑すぎます。パスを簡略化してください。', 'omochix'),
            'USE_EXPANSION_LIMIT' => __('参照（use）が多すぎます。参照を減らしてください。', 'omochix'),
            'SANITIZE_FAILED'     => __('安全に処理できませんでした。別の形式で書き出してお試しください。', 'omochix'),
            'VERIFY_FAILED'       => __('安全に処理できませんでした。別の形式で書き出してお試しください。', 'omochix'),
            'JSON_TOO_LARGE'      => __('設定ファイルが大きすぎます（上限 2MB）。', 'omochix'),
            'JSON_INVALID'        => __('設定ファイルを読み取れませんでした。', 'omochix'),
            'JSON_FORMAT'         => __('このスタジオで保存した設定ファイルではありません。', 'omochix'),
            'JSON_SCHEMA'         => __('この設定ファイルの形式には対応していません。', 'omochix'),
            'EXPORT_FAILED'       => __('書き出しに失敗しました。別のモーションでお試しください。', 'omochix'),
            'EDIT_INVALID'        => __('編集データを読み取れませんでした。値や形式が正しくありません。', 'omochix'),
            'EDIT_LIMIT'          => __('編集データが上限を超えています（レイヤー 40、トラック 120、キーフレーム 600）。', 'omochix'),
            'EDIT_CONFLICT'       => __('同じパーツの同じ性質に、動きが 2 つ付いています。', 'omochix'),
            'EMPTY_RESULT'        => __('表示できる図形がありません。スクリプトや外部参照だけで作られている可能性があります。', 'omochix'),
        ],
        'warnings'    => [
            'text'      => __('文字は閲覧環境のフォントで表示されます。アウトライン化をおすすめします。', 'omochix'),
            'script'    => __('スクリプトを取り除きました。', 'omochix'),
            'external'  => __('外部の画像や埋め込み要素を取り除きました。見た目が変わる場合があります。', 'omochix'),
            'motion'    => __('元のアニメーションは引き継がれません。', 'omochix'),
            'style'     => __('一部のスタイルを取り除きました。見た目が変わる場合があります。', 'omochix'),
            'imageOnly' => __('図形がないため、線や図形を使うモーションは選べません。', 'omochix'),
        ],
        'unavailable' => [
            'noShapes'      => __('図形がないロゴには使えません。', 'omochix'),
            'tooManyShapes' => __('図形が 200 を超えるロゴには使えません。', 'omochix'),
        ],
        'onePart'     => __('このロゴはパーツが 1 つなので、全体を 1 つとして動かします。', 'omochix'),
        'loaded'      => __('ロゴを読み込みました。', 'omochix'),
        'removed'     => __('保存していたロゴを削除しました。', 'omochix'),
        'discarded'   => __('保存データを読み込めなかったため、削除しました。', 'omochix'),
        'usages'      => array_map(static fn($u) => $u['note'], omochix_svg_motion_usages()),
        'copied'      => __('コピーしました。', 'omochix'),
        'copyFailed'  => __('コピーできませんでした。コードを選択してコピーしてください。', 'omochix'),
        'imported'    => __('設定を読み込みました。', 'omochix'),
        'editDiscarded' => __('自由編集の内容は、このロゴには使えないため取り消しました。', 'omochix'),
        'originFallback' => __('ロゴの中心を測れなかったため、全体の回転と拡大は画像（viewBox）の中心を基準にしています。見た目の中心と少しずれることがあります。', 'omochix'),
        'measuring'     => __('ロゴの中心を測っています。終わるとコードとファイルを用意します。', 'omochix'),
        'download'    => [
            'animated' => __('SVG をダウンロード', 'omochix'),
            'still'    => __('静止画 SVG', 'omochix'),
            'css'      => __('CSS をダウンロード', 'omochix'),
            'js'       => __('JS をダウンロード', 'omochix'),
        ],
        'pastedName'  => __('貼り付けたロゴ', 'omochix'),
        'savedName'   => __('保存したロゴ', 'omochix'),
    ];
}

/** JSON for studio.js, safe inside a script element. */
function omochix_svg_motion_data_json() {
    $data             = omochix_svg_motion_data();
    $data['budget']   = OMOCHIX_SVG_MOTION_BUDGET;
    $data['messages'] = omochix_svg_motion_messages();
    return (string) wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

/** Serve page-learn-svg-motion.php; runs before the Learn article swap (10). */
function omochix_svg_motion_template_include($template) {
    if (!omochix_svg_motion_is_request()) {
        return $template;
    }
    $file = get_theme_file_path('/page-learn-svg-motion.php');
    return file_exists($file) ? $file : $template;
}
add_filter('template_include', 'omochix_svg_motion_template_include', 8);

/**
 * Keeps the studio out of the sibling list on Learn articles. page-learn-article.php
 * builds its related list and NEXT link from a WP_Query of the pages under the same
 * parent, so a new child of Learn would otherwise change existing articles. Only that
 * kind of query is affected: the main query and the sitemap still find the page.
 *
 * @param WP_Query $query Query about to run.
 */
function omochix_svg_motion_exclude_from_siblings($query) {
    if (is_admin() || $query->is_main_query() || 'page' !== $query->get('post_type')) {
        return;
    }
    $page_id = omochix_svg_motion_page_id();
    if (!$page_id || !function_exists('omochix_get_learn_page_id') || (int) $query->get('post_parent') !== omochix_get_learn_page_id()) {
        return;
    }
    $query->set('post__not_in', array_merge((array) $query->get('post__not_in'), [$page_id]));
}
add_action('pre_get_posts', 'omochix_svg_motion_exclude_from_siblings');

function omochix_svg_motion_enqueue_assets() {
    if (!omochix_svg_motion_is_request()) {
        return;
    }
    $version = wp_get_theme()->get('Version');
    $deps    = wp_style_is('omochix-shell', 'enqueued') ? ['omochix-style', 'omochix-shell'] : ['omochix-style'];
    wp_enqueue_style('omochix-svg-motion', get_theme_file_uri('/assets/css/learn-svg-motion.css'), $deps, (string) filemtime(get_theme_file_path('/assets/css/learn-svg-motion.css')));
    // In dependency order; only this page loads them.
    wp_enqueue_script('omochix-dompurify', get_theme_file_uri('/assets/js/svg-motion/lib/purify.min.js'), [], '3.4.17', true);
    wp_enqueue_script('omochix-svg-safety', get_theme_file_uri('/assets/js/svg-motion/safety.js'), ['omochix-dompurify'], $version, true);
    wp_enqueue_script('omochix-svg-compile', get_theme_file_uri('/assets/js/svg-motion/compile.js'), [], $version, true);
    wp_enqueue_script('omochix-svg-frame', get_theme_file_uri('/assets/js/svg-motion/frame.js'), ['omochix-svg-safety', 'omochix-svg-compile'], $version, true);
    wp_enqueue_script('omochix-svg-analyze', get_theme_file_uri('/assets/js/svg-motion/analyze.js'), [], $version, true);
    wp_enqueue_script('omochix-svg-import', get_theme_file_uri('/assets/js/svg-motion/import.js'), ['omochix-svg-frame', 'omochix-svg-analyze'], $version, true);
    wp_enqueue_script('omochix-svg-preview', get_theme_file_uri('/assets/js/svg-motion/preview-image.js'), ['omochix-svg-frame'], $version, true);
    wp_enqueue_script('omochix-svg-store', get_theme_file_uri('/assets/js/svg-motion/store.js'), [], $version, true);
    wp_enqueue_script('omochix-svg-export', get_theme_file_uri('/assets/js/svg-motion/export.js'), ['omochix-svg-frame'], $version, true);
    wp_enqueue_script('omochix-svg-studio', get_theme_file_uri('/assets/js/svg-motion/studio.js'), ['omochix-svg-import', 'omochix-svg-preview', 'omochix-svg-store', 'omochix-svg-export'], $version, true);
}
add_action('wp_enqueue_scripts', 'omochix_svg_motion_enqueue_assets', 20);
