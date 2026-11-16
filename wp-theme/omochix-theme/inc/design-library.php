<?php
/**
 * Design Library (D1.5: Web works and video works).
 *
 * "/design/" (all works, grouped by medium), "/design/{web|video}/" (one
 * medium) and "/design/{medium}/{slug}/" (a work), served from the theme's
 * JSON catalog (assets/data/design/catalog.json v2, schema next to it) on the
 * page at path "design" plus rewrite rules — no custom post type, no new
 * table (docs/design-library/Design-Library-D0-Architecture.md §1, §4;
 * D1.5 model: docs/design-library/D1.5-Works-Model.md). The isolated live
 * demo of a Web work ("/design/demo/web/{slug}/", enforced CSP sandbox,
 * fail-closed) lives in inc/design-library-demo.php.
 *
 * Every endpoint checks the same publish conditions (C1-C6, D0 §4.3) through
 * omochix_design_is_public(). App Design and UI Components stay in the data
 * model but cannot be published (D0 §16). Nothing here renders a button for
 * a production feature that does not exist (no generator, no live editor).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

const OMOCHIX_DESIGN_PATH             = 'design';
const OMOCHIX_DESIGN_KIND_VAR         = 'omx_design_kind';
const OMOCHIX_DESIGN_SLUG_VAR         = 'omx_design_slug';
const OMOCHIX_DESIGN_DEMO_VAR         = 'omx_design_demo';
const OMOCHIX_DESIGN_SITEMAP_VAR      = 'omx_design_sitemap';
const OMOCHIX_DESIGN_SITEMAP_PATH     = 'design-sitemap.xml';
const OMOCHIX_DESIGN_REWRITE_VERSION  = '3'; // 3: /design-sitemap.xml
const OMOCHIX_DESIGN_CATALOG_VERSION  = 2;
/** Cards that may play their loop at the same time (D0 §2.1). */
const OMOCHIX_DESIGN_MAX_PLAYING      = 2;

/* -------------------------------------------------------------------------
 * Vocabulary (unknown values are rejected by the catalog validation)
 * ---------------------------------------------------------------------- */

/** medium => [label, URL segment, preview type]. */
function omochix_design_mediums() {
    return [
        'web'       => ['label' => __('Web デザイン', 'omochix'), 'segment' => 'web', 'preview' => 'browser'],
        'video'     => ['label' => __('動画モーション', 'omochix'), 'segment' => 'video', 'preview' => 'video'],
        'app'       => ['label' => __('アプリデザイン', 'omochix'), 'segment' => 'app', 'preview' => 'device'],
        'component' => ['label' => __('UI コンポーネント', 'omochix'), 'segment' => 'components', 'preview' => 'stage'],
    ];
}

/** Mediums that may be published: Web and video (App / Components stay on hold, D0 §16). Not filterable on purpose. */
function omochix_design_public_mediums() {
    return ['web', 'video'];
}

function omochix_design_medium_from_segment($segment) {
    foreach (omochix_design_mediums() as $medium => $def) {
        if ($def['segment'] === $segment) {
            return $medium;
        }
    }
    return '';
}

function omochix_design_use_cases() {
    return [
        'lp' => __('LP', 'omochix'), 'corporate' => __('企業サイト', 'omochix'), 'product' => __('製品紹介', 'omochix'),
        'saas' => __('SaaS・業務ツール', 'omochix'), 'media' => __('メディア・読み物', 'omochix'), 'portfolio' => __('ポートフォリオ', 'omochix'),
        'brand-intro' => __('ブランド紹介', 'omochix'), 'sns-short' => __('SNS ショート動画', 'omochix'), 'explainer' => __('解説動画', 'omochix'),
        'presentation' => __('プレゼン・報告', 'omochix'),
    ];
}

function omochix_design_motion_types() {
    return [
        'scroll' => __('スクロール', 'omochix'), '3d' => __('3D', 'omochix'), 'typography' => __('タイポグラフィ', 'omochix'),
        'logo' => __('ロゴ', 'omochix'), 'chart' => __('グラフ・データ', 'omochix'), 'pointer' => __('ポインター', 'omochix'),
        'transition' => __('切り替え', 'omochix'), 'ambient' => __('背景・空気感', 'omochix'), 'ui' => __('UI', 'omochix'),
    ];
}

/** Technologies. canvas / remotion / blender are vocabulary for later works; nothing here runs them. */
function omochix_design_technologies() {
    return ['html' => 'HTML', 'css' => 'CSS', 'js' => 'JavaScript', 'svg' => 'SVG', 'webgl' => 'WebGL', 'canvas' => 'Canvas', 'ffmpeg' => 'FFmpeg', 'remotion' => 'Remotion', 'blender' => 'Blender'];
}

function omochix_design_styles() {
    return [
        'glass' => __('ガラス', 'omochix'), 'gradient' => __('グラデーション', 'omochix'), 'dark' => __('ダーク', 'omochix'),
        'light' => __('ライト', 'omochix'), 'minimal' => __('ミニマル', 'omochix'), 'editorial' => __('エディトリアル', 'omochix'),
        'typography' => __('タイポグラフィ', 'omochix'), 'monochrome' => __('モノトーン', 'omochix'), 'immersive' => __('イマーシブ', 'omochix'),
        '3d' => __('3D', 'omochix'), 'bento' => __('Bento グリッド', 'omochix'), 'dashboard' => __('ダッシュボード', 'omochix'),
    ];
}

function omochix_design_motions() {
    return [
        'pointer-follow' => __('ポインター追従', 'omochix'), 'float' => __('浮遊', 'omochix'), 'scroll-linked' => __('スクロール連動', 'omochix'),
        'parallax' => __('パララックス', 'omochix'), 'text-reveal' => __('テキスト出現', 'omochix'), 'line-draw' => __('線の描画', 'omochix'),
        'page-transition' => __('ページ送り', 'omochix'), 'drag-rotate' => __('ドラッグ回転', 'omochix'), 'camera-scroll' => __('カメラ移動', 'omochix'),
        'assemble' => __('組み立て演出', 'omochix'), 'stagger' => __('順次出現', 'omochix'), 'hover-lift' => __('ホバー拡大', 'omochix'),
        'count-up' => __('カウントアップ', 'omochix'), 'chart-draw' => __('グラフ描画', 'omochix'), 'logo-draw' => __('ロゴの描画', 'omochix'),
    ];
}

function omochix_design_levels() {
    return ['beginner' => __('初級', 'omochix'), 'intermediate' => __('中級', 'omochix'), 'advanced' => __('上級', 'omochix')];
}

/** Verification states. Only these labels are ever shown to visitors (no "verified" beyond what the state says). */
function omochix_design_verification_states() {
    return [
        'unverified'          => __('未検証', 'omochix'),
        'verified-local'      => __('ローカル環境で検証済み（本番環境では未検証）', 'omochix'),
        'verified-production' => __('本番環境で検証済み', 'omochix'),
    ];
}

function omochix_design_environments() {
    return ['chromium' => 'Chromium', 'webkit' => 'WebKit', 'firefox' => 'Firefox', 'safari-macos' => 'Safari（macOS）', 'safari-ios' => 'Safari（iOS）', 'chrome-android' => 'Chrome（Android）', 'ffprobe' => __('動画の仕様検査（ffprobe）', 'omochix')];
}

function omochix_design_material_types() {
    return ['font' => __('フォント', 'omochix'), 'image' => __('画像', 'omochix'), 'audio' => __('音声', 'omochix'), 'video' => __('動画', 'omochix'), 'library' => __('ライブラリ', 'omochix'), 'data' => __('データ', 'omochix'), 'code' => __('コード', 'omochix')];
}

/* -------------------------------------------------------------------------
 * Files
 * ---------------------------------------------------------------------- */

function omochix_design_catalog_file() {
    return (string) apply_filters('omochix_design_catalog_file', get_theme_file_path('/assets/data/design/catalog.json'));
}

/** Directory of the static design assets (posters, loops, videos, demo sources). */
function omochix_design_assets_dir() {
    return get_theme_file_path('/assets/design');
}

function omochix_design_assets_uri() {
    return get_theme_file_uri('/assets/design');
}

/**
 * Absolute file for a catalog asset path ("posters/x.avif", "demos/x/demo.php").
 * Filterable only so the local isolation tests can add a probe item whose
 * files stay outside the theme (they never ship).
 */
function omochix_design_asset_file($path) {
    return (string) apply_filters('omochix_design_asset_file', omochix_design_assets_dir() . '/' . $path, $path);
}

/** Directory holding one Web work's demo sources (demo.php, demo.css, demo.js). */
function omochix_design_demo_dir(array $item) {
    return dirname(omochix_design_asset_file($item['preview']['entry'] . 'demo.php'));
}

/* -------------------------------------------------------------------------
 * Catalog validation (mirrors assets/data/design/catalog.schema.json).
 * Patterns end with \z: "$" would also accept a trailing newline.
 * ---------------------------------------------------------------------- */

function omochix_design_is_slug($value) {
    return is_string($value) && 1 === preg_match('/^[a-z0-9](?:[a-z0-9-]{1,46})[a-z0-9]\z/', $value) && false === strpos($value, '--');
}

/** One-line plain text: no control characters, no angle brackets. */
function omochix_design_is_text($value, $max, $min = 1) {
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        return false;
    }
    $len = mb_strlen($value);
    return $len >= $min && $len <= $max && !preg_match('/[\x00-\x1F\x7F<>]/u', $value);
}

/** Multi-line plain text (prompts, steps): newlines and tabs allowed, no other control characters, no angle brackets. */
function omochix_design_is_long_text($value, $max) {
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        return false;
    }
    $len = mb_strlen($value);
    return $len >= 1 && $len <= $max && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F<>]/u', $value);
}

function omochix_design_is_date($value) {
    return is_string($value) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}\z/', $value);
}

function omochix_design_is_list($value) {
    return is_array($value) && array_values($value) === $value;
}

/** Non-empty list of unique strings taken from $allowed. */
function omochix_design_is_vocab_list($value, array $allowed, $max, $min = 1) {
    if (!omochix_design_is_list($value) || count($value) < $min || count($value) > $max) {
        return false;
    }
    foreach ($value as $v) {
        if (!is_string($v) || !in_array($v, $allowed, true)) {
            return false;
        }
    }
    return count(array_unique($value)) === count($value);
}

function omochix_design_is_slug_list($value, $max) {
    if (!omochix_design_is_list($value) || count($value) > $max) {
        return false;
    }
    foreach ($value as $v) {
        if (!omochix_design_is_slug($v)) {
            return false;
        }
    }
    return count(array_unique($value)) === count($value);
}

/** Keys of $value must be within $required + $optional and include $required. An object, not a list. */
function omochix_design_has_keys($value, array $required, array $optional = []) {
    if (!is_array($value) || ($value && array_values($value) === $value)) {
        return false;
    }
    foreach (array_keys($value) as $key) {
        if (!in_array($key, $required, true) && !in_array($key, $optional, true)) {
            return false;
        }
    }
    foreach ($required as $key) {
        if (!array_key_exists($key, $value)) {
            return false;
        }
    }
    return true;
}

/**
 * Asset path check: exact name bound to the item's slug, no traversal, and
 * the file exists under assets/design (C6, D0 §4.3).
 */
function omochix_design_asset_ok($path, $dir, $slug, array $exts) {
    if (!is_string($path) || !preg_match('#^' . preg_quote($dir, '#') . '/' . preg_quote($slug, '#') . '\.(' . implode('|', $exts) . ')\z#', $path)) {
        return false;
    }
    return is_file(omochix_design_asset_file($path));
}

/**
 * JSON objects and lists both decode to PHP arrays; this second look at the
 * object-decoded JSON tells "{}" from "[]" for the fields where it matters.
 *
 * @param mixed $raw_item Item decoded with objects (stdClass).
 * @return string[] Field names with the wrong JSON type.
 */
function omochix_design_shape_errors($raw_item) {
    if (!$raw_item instanceof stdClass) {
        return ['item'];
    }
    $lists   = ['use_cases', 'motion_types', 'technologies', 'styles', 'motions', 'industries', 'palette', 'fonts', 'materials', 'customization', 'steps'];
    $objects = ['preview', 'deliverables', 'credits', 'verification', 'related', 'audit', 'video', 'prompt', 'motionStudio'];
    $bad = [];
    foreach ($lists as $k) {
        if (property_exists($raw_item, $k) && !is_array($raw_item->$k)) {
            $bad[] = $k;
        }
    }
    foreach ($objects as $k) {
        if (property_exists($raw_item, $k) && !$raw_item->$k instanceof stdClass) {
            $bad[] = $k;
        }
    }
    if (isset($raw_item->audit) && $raw_item->audit instanceof stdClass && property_exists($raw_item->audit, 'perf') && !$raw_item->audit->perf instanceof stdClass) {
        $bad[] = 'audit.perf';
    }
    if (isset($raw_item->motionStudio) && $raw_item->motionStudio instanceof stdClass && property_exists($raw_item->motionStudio, 'settings') && !$raw_item->motionStudio->settings instanceof stdClass) {
        $bad[] = 'motionStudio.settings';
    }
    return $bad;
}

/**
 * Validate one item. Collects every problem (the caller drops the item).
 *
 * @param mixed  $item  Decoded item (associative arrays).
 * @param string $where Label for messages.
 * @return string[]
 */
function omochix_design_validate_item($item, $where) {
    $e = [];
    $required = ['id', 'slug', 'medium', 'status', 'title', 'summary', 'description', 'use_cases', 'motion_types', 'technologies', 'styles', 'motions',
        'industries', 'level', 'palette', 'fonts', 'poster', 'preview', 'materials', 'customization', 'credits', 'verification', 'related', 'audit'];
    $optional = ['posterFallback', 'loop', 'loopFallback', 'video', 'prompt', 'steps', 'deliverables', 'motionStudio'];
    if (!omochix_design_has_keys($item, $required, $optional)) {
        return ["$where: unknown or missing keys"];
    }
    if (!is_string($item['medium']) || !isset(omochix_design_mediums()[$item['medium']])) {
        return ["$where: unknown medium"];
    }
    $medium = $item['medium'];
    if (!is_string($item['id']) || !preg_match('/^dl-(web|video|app|component)-[0-9]{4}\z/', $item['id']) || 0 !== strpos($item['id'], 'dl-' . $medium . '-')) {
        $e[] = "$where: id must be dl-{medium}-NNNN and match the medium";
    }
    if (!omochix_design_is_slug($item['slug'])) {
        $e[] = "$where: invalid slug";
        return $e;
    }
    $slug = $item['slug'];
    if (!in_array($item['status'], ['draft', 'published'], true)) {
        $e[] = "$where: status must be draft or published";
    } elseif ('published' === $item['status'] && !in_array($medium, omochix_design_public_mediums(), true)) {
        $e[] = "$where: medium $medium cannot be published yet";
    }
    foreach (['title' => 60, 'summary' => 160, 'description' => 200] as $k => $max) {
        if (!omochix_design_is_text($item[$k], $max)) {
            $e[] = "$where: $k must be 1-$max plain characters";
        }
    }
    $vocab = [
        'use_cases'    => [omochix_design_use_cases(), 4],
        'motion_types' => [omochix_design_motion_types(), 5],
        'technologies' => [omochix_design_technologies(), 8],
        'styles'       => [omochix_design_styles(), 6],
        'motions'      => [omochix_design_motions(), 8],
    ];
    foreach ($vocab as $k => [$allowed, $max]) {
        if (!omochix_design_is_vocab_list($item[$k], array_keys($allowed), $max)) {
            $e[] = "$where: $k must be 1-$max known values";
        }
    }
    if (!omochix_design_is_slug_list($item['industries'], 6)) {
        $e[] = "$where: industries must be up to 6 slugs";
    }
    if (!is_string($item['level']) || !isset(omochix_design_levels()[$item['level']])) {
        $e[] = "$where: unknown level";
    }
    $palette_ok = omochix_design_is_list($item['palette']) && count($item['palette']) >= 1 && count($item['palette']) <= 8;
    foreach ($palette_ok ? $item['palette'] : [] as $color) {
        if (!omochix_design_has_keys($color, ['name', 'value']) || !omochix_design_is_text($color['name'], 24) || !is_string($color['value']) || !preg_match('/^#[0-9a-f]{6}\z/', $color['value'])) {
            $palette_ok = false;
        }
    }
    if (!$palette_ok) {
        $e[] = "$where: palette must be 1-8 {name, #rrggbb} entries";
    }
    $fonts_ok = omochix_design_is_list($item['fonts']) && count($item['fonts']) >= 1 && count($item['fonts']) <= 4;
    foreach ($fonts_ok ? $item['fonts'] : [] as $font) {
        if (!is_string($font) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]{0,39}\z/', $font)) {
            $fonts_ok = false;
        }
    }
    if (!$fonts_ok) {
        $e[] = "$where: fonts must be 1-4 font family names";
    }

    // Media: poster always, loop optional, the full video only (and always) for video works.
    if (!omochix_design_asset_ok($item['poster'], 'posters', $slug, ['avif'])) {
        $e[] = "$where: poster must be posters/$slug.avif and exist";
    }
    if (array_key_exists('posterFallback', $item) && !omochix_design_asset_ok($item['posterFallback'], 'posters', $slug, ['webp'])) {
        $e[] = "$where: posterFallback must be posters/$slug.webp and exist";
    }
    if (array_key_exists('loop', $item) && !omochix_design_asset_ok($item['loop'], 'loops', $slug, ['webm'])) {
        $e[] = "$where: loop must be loops/$slug.webm and exist";
    }
    if (array_key_exists('loopFallback', $item) && (!array_key_exists('loop', $item) || !omochix_design_asset_ok($item['loopFallback'], 'loops', $slug, ['mp4']))) {
        $e[] = "$where: loopFallback must be loops/$slug.mp4, exist, and come with loop";
    }
    if ('video' === $medium) {
        $v = $item['video'] ?? null;
        $ok = omochix_design_has_keys($v, ['mp4', 'width', 'height', 'fps', 'seconds'], ['webm'])
            && omochix_design_asset_ok($v['mp4'], 'videos', $slug, ['mp4'])
            && (!array_key_exists('webm', $v) || omochix_design_asset_ok($v['webm'], 'videos', $slug, ['webm']))
            && is_int($v['width']) && $v['width'] >= 160 && $v['width'] <= 3840 && is_int($v['height']) && $v['height'] >= 160 && $v['height'] <= 3840
            && is_int($v['fps']) && $v['fps'] >= 1 && $v['fps'] <= 60 && (is_int($v['seconds']) || is_float($v['seconds'])) && $v['seconds'] >= 1 && $v['seconds'] <= 600;
        if (!$ok) {
            $e[] = "$where: video must have videos/$slug.mp4 (and optional .webm) that exist, width, height, fps and seconds";
        }
    } elseif (array_key_exists('video', $item)) {
        $e[] = "$where: video is only for medium video";
    }
    $e = array_merge($e, omochix_design_validate_preview($item['preview'], $medium, $slug, $where, $item['video'] ?? null));

    // Production information.
    $mt = omochix_design_material_types();
    $ok = omochix_design_is_list($item['materials']) && count($item['materials']) <= 12;
    foreach ($ok ? $item['materials'] : [] as $m) {
        if (!omochix_design_has_keys($m, ['name', 'type', 'source', 'license']) || !omochix_design_is_text($m['name'], 60) || !is_string($m['type']) || !isset($mt[$m['type']])
            || !in_array($m['source'], ['original', 'system'], true) || !in_array($m['license'], ['original', 'system-font'], true)) {
            $ok = false;
        }
    }
    if (!$ok) {
        $e[] = "$where: materials must be up to 12 {name, type, source original|system, license original|system-font}";
    }
    $ok = omochix_design_is_list($item['customization']) && count($item['customization']) <= 12;
    foreach ($ok ? $item['customization'] : [] as $c) {
        if (!omochix_design_has_keys($c, ['label', 'description']) || !omochix_design_is_text($c['label'], 40) || !omochix_design_is_text($c['description'], 160)) {
            $ok = false;
        }
    }
    if (!$ok) {
        $e[] = "$where: customization must be up to 12 {label, description}";
    }
    if (array_key_exists('prompt', $item) && (!omochix_design_has_keys($item['prompt'], ['text']) || !omochix_design_is_long_text($item['prompt']['text'], 4000))) {
        $e[] = "$where: prompt must be {text} with 1-4000 plain characters";
    }
    if (array_key_exists('steps', $item)) {
        $ok = omochix_design_is_list($item['steps']) && count($item['steps']) >= 1 && count($item['steps']) <= 10;
        foreach ($ok ? $item['steps'] : [] as $s) {
            if (!omochix_design_has_keys($s, ['title', 'body']) || !omochix_design_is_text($s['title'], 60) || !omochix_design_is_long_text($s['body'], 600)) {
                $ok = false;
            }
        }
        if (!$ok) {
            $e[] = "$where: steps must be 1-10 {title, body}";
        }
    }
    if ('web' === $medium) {
        $d = $item['deliverables'] ?? null;
        if (!omochix_design_has_keys($d, ['html', 'showCode']) || !omochix_design_has_keys($d['html'], ['file', 'sha256'])
            || !is_array($item['preview']) || ($d['html']['file'] ?? null) !== ($item['preview']['entry'] ?? false)
            || !is_string($d['html']['sha256']) || !preg_match('/^[0-9a-f]{64}\z/', $d['html']['sha256']) || !is_bool($d['showCode'])) {
            $e[] = "$where: deliverables must be {html: {file = preview.entry, sha256}, showCode}";
        }
    } elseif (array_key_exists('deliverables', $item)) {
        $e[] = "$where: deliverables (demo code) are only for medium web";
    }
    $c = $item['credits'];
    if (!omochix_design_has_keys($c, ['author', 'source', 'license'], ['notes']) || !omochix_design_is_text($c['author'], 60) || 'original' !== $c['source'] || 'original' !== $c['license']
        || (array_key_exists('notes', $c) && !omochix_design_is_text($c['notes'], 200))) {
        $e[] = "$where: credits must be {author, source original, license original, notes?} (only original works are accepted)";
    }
    $v = $item['verification'];
    $ok = omochix_design_has_keys($v, ['state', 'checked', 'environments', 'limitations'])
        && is_string($v['state']) && isset(omochix_design_verification_states()[$v['state']])
        && (null === $v['checked'] || omochix_design_is_date($v['checked']))
        && omochix_design_is_vocab_list($v['environments'], array_keys(omochix_design_environments()), 8, 0)
        && omochix_design_is_list($v['limitations']) && count($v['limitations']) <= 8;
    foreach ($ok ? $v['limitations'] : [] as $l) {
        if (!omochix_design_is_text($l, 160)) {
            $ok = false;
        }
    }
    if ($ok && 'unverified' !== $v['state'] && (null === $v['checked'] || [] === $v['environments'])) {
        $ok = false; // a verified state needs the date and where it was checked
    }
    if (!$ok) {
        $e[] = "$where: verification must be {state, checked, environments, limitations}; a verified state needs a date and environments";
    }
    $r = $item['related'];
    $ok = omochix_design_has_keys($r, ['designs', 'motion', 'learn', 'prompts']) && omochix_design_is_slug_list($r['designs'], 12)
        && omochix_design_is_slug_list($r['motion'], 12) && omochix_design_is_slug_list($r['prompts'], 12) && omochix_design_is_list($r['learn']) && count($r['learn']) <= 8;
    foreach ($ok ? $r['learn'] : [] as $p) {
        if (!is_string($p) || !preg_match('#^learn(/[a-z0-9-]+){1,3}\z#', $p)) {
            $ok = false;
        }
    }
    if (!$ok) {
        $e[] = "$where: related must have designs, motion, prompts slug lists and learn page paths";
    }
    if (array_key_exists('motionStudio', $item)) {
        $ms = $item['motionStudio'];
        $ok = omochix_design_has_keys($ms, ['templateId', 'status'], ['settings']) && is_string($ms['templateId'])
            && preg_match('/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?\z/', $ms['templateId']) && in_array($ms['status'], ['unverified', 'verified'], true);
        if ($ok && array_key_exists('settings', $ms)) {
            $ok = is_array($ms['settings']) && count($ms['settings']) <= 16;
            foreach ($ok ? $ms['settings'] : [] as $k => $val) {
                if (!is_string($k) || !preg_match('/^[a-z][a-zA-Z0-9]{0,31}\z/', $k) || !(is_bool($val) || is_int($val) || is_float($val) || (is_string($val) && omochix_design_is_text($val, 120, 0)))) {
                    $ok = false;
                }
            }
        }
        if (!$ok) {
            $e[] = "$where: motionStudio must be {templateId, status unverified|verified, settings?: scalar values}";
        }
    }
    $a = $item['audit'];
    if (!omochix_design_has_keys($a, ['reviewed', 'perf']) || !omochix_design_is_date($a['reviewed']) || !omochix_design_perf_ok($a['perf'])) {
        $e[] = "$where: audit must have a reviewed date and perf numbers";
    }
    return $e;
}

function omochix_design_perf_ok($perf) {
    $keys = ['transferKb', 'jsKb', 'lcpMs', 'cls', 'longTasks', 'heapMb', 'fpsP5'];
    if (!omochix_design_has_keys($perf, [], $keys)) {
        return false;
    }
    foreach ($perf as $v) {
        if (!(is_int($v) || is_float($v)) || $v < 0 || $v > 100000) {
            return false;
        }
    }
    return true;
}

/** @return string[] */
function omochix_design_validate_preview($p, $medium, $slug, $where, $video = null) {
    $type = omochix_design_mediums()[$medium]['preview'];
    if (!is_array($p) || ($p['type'] ?? null) !== $type) {
        return ["$where: preview.type must be $type"];
    }
    if ('video' === $medium) {
        $orient = is_array($video) && isset($video['width'], $video['height']) && is_int($video['width']) && is_int($video['height'])
            ? ($video['width'] > $video['height'] ? 'landscape' : ($video['width'] < $video['height'] ? 'portrait' : 'square')) : null;
        return (omochix_design_has_keys($p, ['type', 'orientation']) && $p['orientation'] === $orient) ? [] : ["$where: preview must be {type video, orientation matching the video size}"];
    }
    $entry_ok = ($p['entry'] ?? null) === "demos/$slug/" && is_file(omochix_design_asset_file("demos/$slug/demo.php"));
    if ('web' === $medium) {
        $ok = omochix_design_has_keys($p, ['type', 'entry', 'viewports', 'scroll', 'webgl'])
            && omochix_design_is_vocab_list($p['viewports'], ['desktop', 'tablet', 'mobile'], 3)
            && is_bool($p['scroll']) && is_bool($p['webgl']);
    } elseif ('app' === $medium) {
        $ok = omochix_design_has_keys($p, ['type', 'entry', 'devices', 'screens', 'transitions', 'theme'])
            && omochix_design_is_vocab_list($p['devices'], ['iphone', 'android'], 2)
            && omochix_design_is_slug_list($p['screens'], 24) && omochix_design_is_list($p['transitions']) && in_array($p['theme'], ['light', 'dark'], true);
        foreach ($ok ? $p['transitions'] : [] as $t) {
            if (!omochix_design_has_keys($t, ['from', 'gesture', 'to']) || !in_array($t['from'], $p['screens'], true) || !in_array($t['to'], $p['screens'], true) || !in_array($t['gesture'], ['tap', 'swipe', 'long-press'], true)) {
                $ok = false;
            }
        }
    } else {
        $ok = omochix_design_has_keys($p, ['type', 'entry', 'states', 'stageSize', 'interactions'])
            && omochix_design_is_vocab_list($p['states'], ['default', 'hover', 'focus', 'active', 'disabled', 'loading'], 6)
            && omochix_design_has_keys($p['stageSize'], ['width', 'height']) && is_int($p['stageSize']['width']) && is_int($p['stageSize']['height'])
            && $p['stageSize']['width'] >= 120 && $p['stageSize']['width'] <= 1920 && $p['stageSize']['height'] >= 80 && $p['stageSize']['height'] <= 1200
            && omochix_design_is_vocab_list($p['interactions'], ['click', 'drag', 'toggle', 'scroll'], 4, 0);
    }
    $e = [];
    if (!$ok) {
        $e[] = "$where: preview has unknown, missing or invalid fields for $medium";
    }
    if (!$entry_ok) {
        $e[] = "$where: preview.entry must be demos/$slug/ with a demo.php";
    }
    return $e;
}

/**
 * Validate a decoded catalog. Root problems reject everything; an invalid
 * item is dropped on its own (D0 §1.4), as is a duplicate id or slug (the
 * first one wins, so a later copy cannot replace a reviewed item).
 *
 * @param mixed      $data Decoded JSON (associative arrays).
 * @param mixed|null $raw  The same JSON decoded with objects, for the {} / [] check (null = skip).
 * @return array{items: array<string, array<string, mixed>>, errors: string[]}
 */
function omochix_design_validate_catalog($data, $raw = null) {
    if (!omochix_design_has_keys($data, ['version', 'updated', 'items'])) {
        return ['items' => [], 'errors' => ['catalog: root must have exactly version, updated, items']];
    }
    if (OMOCHIX_DESIGN_CATALOG_VERSION !== $data['version']) {
        return ['items' => [], 'errors' => ['catalog: unsupported version']];
    }
    if (!omochix_design_is_date($data['updated'])) {
        return ['items' => [], 'errors' => ['catalog: updated must be YYYY-MM-DD']];
    }
    if (!omochix_design_is_list($data['items']) || count($data['items']) > 500) {
        return ['items' => [], 'errors' => ['catalog: items must be a list (max 500)']];
    }
    $raw_items = ($raw instanceof stdClass && isset($raw->items) && is_array($raw->items)) ? $raw->items : null;
    $items = []; $errors = []; $ids = [];
    foreach ($data['items'] as $index => $item) {
        $where = "items[$index]";
        $item_errors = omochix_design_validate_item($item, $where);
        if (null !== $raw_items) {
            $shape = omochix_design_shape_errors($raw_items[$index] ?? null);
            if ($shape) {
                $item_errors[] = "$where: wrong JSON type (object vs list) for " . implode(', ', $shape);
            }
        }
        if (!$item_errors && isset($ids[$item['id']])) {
            $item_errors[] = "$where: duplicate id {$item['id']}";
        }
        if (!$item_errors && isset($items[$item['slug']])) {
            $item_errors[] = "$where: duplicate slug {$item['slug']}";
        }
        if ($item_errors) {
            $errors = array_merge($errors, $item_errors);
            continue;
        }
        $ids[$item['id']]     = true;
        $items[$item['slug']] = $item;
    }
    return ['items' => $items, 'errors' => $errors];
}

/** @return array{version: int, updated: string, items: array<string, array<string, mixed>>, errors: string[]} */
function omochix_design_load_catalog_from_file($file) {
    $empty = ['version' => 0, 'updated' => '', 'items' => []];
    if (!is_readable($file)) {
        return $empty + ['errors' => ['catalog: file is missing or unreadable']];
    }
    $json = (string) file_get_contents($file);
    $data = json_decode($json, true);
    if (JSON_ERROR_NONE !== json_last_error()) {
        return $empty + ['errors' => ['catalog: invalid JSON (' . json_last_error_msg() . ')']];
    }
    $raw    = json_decode($json);
    $data   = apply_filters('omochix_design_catalog_data', $data);
    $raw    = apply_filters('omochix_design_catalog_raw', $raw, $data);
    $result = omochix_design_validate_catalog($data, $raw);
    if (!$result['items']) {
        return $empty + ['errors' => $result['errors']];
    }
    return ['version' => $data['version'], 'updated' => $data['updated'], 'items' => $result['items'], 'errors' => $result['errors']];
}

/** The validated catalog, once per request. Problems go to the PHP log (no fatal, no partial item). */
function omochix_design_get_catalog() {
    if (!isset($GLOBALS['omochix_design_catalog'])) {
        $GLOBALS['omochix_design_catalog'] = omochix_design_load_catalog_from_file(omochix_design_catalog_file());
        if ($GLOBALS['omochix_design_catalog']['errors']) {
            error_log('[omochix design-library] ' . implode('; ', $GLOBALS['omochix_design_catalog']['errors']));
        }
    }
    return $GLOBALS['omochix_design_catalog'];
}

/** Reset the per-request caches (tests only). */
function omochix_design_reset_cache() {
    $GLOBALS['omochix_design_page_id'] = null;
    unset($GLOBALS['omochix_design_terms'], $GLOBALS['omochix_design_catalog'], $GLOBALS['omochix_design_current']);
}

/* -------------------------------------------------------------------------
 * Publish conditions (C1-C6, D0 §4.3)
 * ---------------------------------------------------------------------- */

/** C1: the published page at "design", or 0. */
function omochix_design_page_id() {
    if (isset($GLOBALS['omochix_design_page_id'])) {
        return $GLOBALS['omochix_design_page_id'];
    }
    $page = get_page_by_path(OMOCHIX_DESIGN_PATH, OBJECT, 'page');
    $GLOBALS['omochix_design_page_id'] = ($page instanceof WP_Post && 'publish' === $page->post_status && '' === (string) $page->post_password) ? (int) $page->ID : 0;
    return $GLOBALS['omochix_design_page_id'];
}

/**
 * Publish decision used by every endpoint. With a slug: C1-C4 for that item
 * (C3 = it passed validation). Without: C1, C2 and C5 (at least one
 * published item of the medium). $medium '' = any public medium.
 */
function omochix_design_is_public($medium, $slug = null) {
    if (!omochix_design_page_id()) {
        return false;
    }
    $mediums = '' === $medium ? omochix_design_public_mediums() : [$medium];
    foreach ($mediums as $m) {
        if (!in_array($m, omochix_design_public_mediums(), true)) {
            continue;
        }
        if (null !== $slug) {
            $item = omochix_design_get_catalog()['items'][$slug] ?? null;
            return is_array($item) && $item['medium'] === $m && 'published' === $item['status'];
        }
        foreach (omochix_design_get_catalog()['items'] as $item) {
            if ($item['medium'] === $m && 'published' === $item['status']) {
                return true;
            }
        }
    }
    return false;
}

/** A public item, or null. */
function omochix_design_get_item($medium, $slug) {
    return omochix_design_is_public($medium, $slug) ? omochix_design_get_catalog()['items'][$slug] : null;
}

/** Public items of a medium ('' = every public medium) in catalog order. */
function omochix_design_get_items($medium = '', array $filters = []) {
    $out = [];
    foreach ('' === $medium ? omochix_design_public_mediums() : [$medium] as $m) {
        if (!omochix_design_is_public($m)) {
            continue;
        }
        foreach (omochix_design_get_catalog()['items'] as $slug => $item) {
            if ($item['medium'] === $m && 'published' === $item['status'] && omochix_design_item_matches($item, $filters)) {
                $out[$slug] = $item;
            }
        }
    }
    return $out;
}

/** Verification state of an item ('unverified' when absent). */
function omochix_design_verification_state(array $item) {
    return (string) ($item['verification']['state'] ?? 'unverified');
}

/**
 * Whether the live demo of a Web work may be served: the item is public and
 * its isolation tests passed for this environment. Outside a local or
 * development site only "verified-production" counts (the default
 * environment type is production, so an unset type fails closed).
 */
function omochix_design_demo_allowed(array $item) {
    if ('web' !== ($item['medium'] ?? '') || !omochix_design_is_public('web', $item['slug'])) {
        return false;
    }
    return omochix_design_verified_here($item);
}

/** Whether the item's verification state counts in this environment (production / staging / unset: verified-production only). */
function omochix_design_verified_here(array $item) {
    $state = omochix_design_verification_state($item);
    $env   = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
    if (in_array($env, ['local', 'development'], true)) {
        return in_array($state, ['verified-local', 'verified-production'], true);
    }
    return 'verified-production' === $state;
}

/**
 * Whether a work may be indexed (detail page, sitemap, JSON-LD): public and
 * verified for this environment — the same rule as the live demo, so a work
 * only checked locally stays noindex in production.
 */
function omochix_design_is_indexable(array $item) {
    return omochix_design_is_public($item['medium'] ?? '', $item['slug'] ?? '') && omochix_design_verified_here($item);
}

/** Indexable works of a medium ('' = all public mediums), catalog order. */
function omochix_design_indexable_items($medium = '') {
    return array_filter(omochix_design_get_items($medium), 'omochix_design_is_indexable');
}

/**
 * Link into Motion Studio's editor for an item. Always '' in D1.5: there is
 * no Motion Studio editor or template-loading endpoint reachable from the
 * site (the studio is a local Remotion project), so no "create" button is
 * rendered. Designed hand-over: docs/design-library/D1.5-Works-Model.md §5.
 */
function omochix_design_motion_studio_url(array $item) {
    unset($item);
    return '';
}

/* -------------------------------------------------------------------------
 * Filters (list pages, GET parameters, noindex when active)
 * ---------------------------------------------------------------------- */

/** Industry terms (slug => name) from OmochiX Core, or [] when Core / the Industry Hub is unavailable. */
function omochix_design_industry_terms() {
    if (isset($GLOBALS['omochix_design_terms'])) {
        return $GLOBALS['omochix_design_terms'];
    }
    $terms = [];
    if (function_exists('omochix_core_industry_is_public') && taxonomy_exists('omx_industry') && omochix_core_industry_is_public()) {
        $found = get_terms(['taxonomy' => 'omx_industry', 'hide_empty' => false]);
        if (is_array($found)) {
            foreach ($found as $term) {
                $terms[$term->slug] = $term->name;
            }
        }
    }
    $GLOBALS['omochix_design_terms'] = $terms;
    return $terms;
}

/** Industry slugs of an item that exist as terms. */
function omochix_design_item_industries(array $item) {
    $terms = omochix_design_industry_terms();
    $out = [];
    foreach ($item['industries'] as $slug) {
        if (isset($terms[$slug])) {
            $out[$slug] = $terms[$slug];
        }
    }
    return $out;
}

/** Filter keys => vocabulary (labels). */
function omochix_design_filter_axes() {
    return [
        'use_case'    => ['field' => 'use_cases', 'label' => __('用途', 'omochix'), 'vocab' => omochix_design_use_cases()],
        'motion_type' => ['field' => 'motion_types', 'label' => __('表現', 'omochix'), 'vocab' => omochix_design_motion_types()],
        'technology'  => ['field' => 'technologies', 'label' => __('技術', 'omochix'), 'vocab' => omochix_design_technologies()],
        'industry'    => ['field' => 'industries', 'label' => __('業種', 'omochix'), 'vocab' => omochix_design_industry_terms()],
    ];
}

/** Options actually used by public items of the medium ('' = all), so a filter never offers an empty result. */
function omochix_design_filter_options($medium = '') {
    $opts = ['medium' => [], 'use_case' => [], 'motion_type' => [], 'technology' => [], 'industry' => []];
    $mediums = omochix_design_mediums();
    foreach (omochix_design_get_items($medium) as $item) {
        $opts['medium'][$item['medium']] = $mediums[$item['medium']]['label'];
        foreach (omochix_design_filter_axes() as $key => $axis) {
            foreach ($item[$axis['field']] as $v) {
                if (isset($axis['vocab'][$v])) {
                    $opts[$key][$v] = $axis['vocab'][$v];
                }
            }
        }
    }
    foreach ($opts as $key => $values) {
        // Keep vocabulary order, not catalog order.
        $order = 'medium' === $key ? array_keys($mediums) : array_keys(omochix_design_filter_axes()[$key]['vocab']);
        uksort($values, static function ($a, $b) use ($order) { return array_search($a, $order, true) <=> array_search($b, $order, true); });
        $opts[$key] = $values;
    }
    return $opts;
}

/** @return array{q: string, medium: string, use_case: string, motion_type: string, technology: string, industry: string} */
function omochix_design_sanitize_filters(array $source) {
    $q = isset($source['q']) && is_string($source['q']) ? sanitize_text_field(wp_unslash($source['q'])) : '';
    $q = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $q)), 0, 60);
    $pick = static function ($key, array $allowed) use ($source) {
        $v = isset($source[$key]) && is_string($source[$key]) ? sanitize_key(wp_unslash($source[$key])) : '';
        return in_array($v, $allowed, true) ? $v : '';
    };
    $out = ['q' => $q, 'medium' => $pick('medium', omochix_design_public_mediums())];
    foreach (omochix_design_filter_axes() as $key => $axis) {
        $out[$key] = $pick($key, array_map('strval', array_keys($axis['vocab'])));
    }
    return $out;
}

/** Lowercased text searched by the keyword filter (same on the server and in JS). */
function omochix_design_search_text(array $item) {
    $parts = [$item['title'], $item['summary'], $item['description'], omochix_design_mediums()[$item['medium']]['label']];
    $maps = ['use_cases' => omochix_design_use_cases(), 'motion_types' => omochix_design_motion_types(), 'technologies' => omochix_design_technologies(), 'styles' => omochix_design_styles(), 'motions' => omochix_design_motions()];
    foreach ($maps as $field => $labels) {
        foreach ($item[$field] as $v) {
            $parts[] = $labels[$v];
            $parts[] = $v;
        }
    }
    foreach (omochix_design_item_industries($item) as $slug => $name) {
        $parts[] = $name;
        $parts[] = $slug;
    }
    return mb_strtolower(implode(' ', $parts));
}

function omochix_design_item_matches(array $item, array $filters) {
    if (!empty($filters['medium']) && $filters['medium'] !== $item['medium']) {
        return false;
    }
    foreach (omochix_design_filter_axes() as $key => $axis) {
        if (!empty($filters[$key]) && !in_array($filters[$key], $item[$axis['field']], true)) {
            return false;
        }
    }
    if (!empty($filters['q'])) {
        $hay = omochix_design_search_text($item);
        foreach (explode(' ', mb_strtolower($filters['q'])) as $word) {
            if ('' !== $word && false === mb_strpos($hay, $word)) {
                return false;
            }
        }
    }
    return true;
}

/* -------------------------------------------------------------------------
 * Related content (published WordPress content and public catalog items only)
 * ---------------------------------------------------------------------- */

/**
 * @return array{designs: array<int, array{url: string, title: string}>, motion: array<int, array{url: string, title: string}>, learn: array<int, array{url: string, title: string}>}
 */
function omochix_design_related(array $item) {
    $out = ['designs' => [], 'motion' => [], 'learn' => []];
    foreach ($item['related']['designs'] as $slug) {
        $other = omochix_design_get_catalog()['items'][$slug] ?? null;
        if ($other && $slug !== $item['slug'] && omochix_design_is_public($other['medium'], $slug)) {
            $out['designs'][] = ['url' => omochix_design_item_url($other), 'title' => $other['title']];
        }
    }
    if (function_exists('omochix_learn_gallery_get_item') && function_exists('omochix_learn_gallery_url')) {
        foreach ($item['related']['motion'] as $slug) {
            $m   = omochix_learn_gallery_get_item($slug);
            $url = $m ? omochix_learn_gallery_url($m) : '';
            if ($m && '' !== $url) {
                $out['motion'][] = ['url' => $url, 'title' => $m['name']['ja']];
            }
        }
    }
    foreach ($item['related']['learn'] as $path) {
        $page = get_page_by_path($path, OBJECT, 'page');
        if ($page instanceof WP_Post && 'publish' === $page->post_status && '' === (string) $page->post_password) {
            $out['learn'][] = ['url' => (string) get_permalink($page), 'title' => get_the_title($page)];
        }
    }
    return $out;
}

/* -------------------------------------------------------------------------
 * URLs (all demo URLs come from omochix_design_demo_url(): moving demos to
 * a separate origin later changes that one function, D0 §3.2)
 * ---------------------------------------------------------------------- */

function omochix_design_url(array $filters = []) {
    $id = omochix_design_page_id();
    if (!$id) {
        return '';
    }
    $url  = trailingslashit((string) get_permalink($id));
    $args = array_filter($filters, static function ($v) { return '' !== $v && null !== $v; });
    return $args ? add_query_arg(array_map('rawurlencode', $args), $url) : $url;
}

function omochix_design_medium_url($medium, array $filters = []) {
    $base    = omochix_design_url();
    $mediums = omochix_design_mediums();
    if ('' === $base || !isset($mediums[$medium])) {
        return '';
    }
    $url  = $base . $mediums[$medium]['segment'] . '/';
    $args = array_filter($filters, static function ($v) { return '' !== $v && null !== $v; });
    return $args ? add_query_arg(array_map('rawurlencode', $args), $url) : $url;
}

function omochix_design_item_url(array $item) {
    $list = omochix_design_medium_url($item['medium']);
    return '' === $list ? '' : $list . $item['slug'] . '/';
}

function omochix_design_demo_url(array $item) {
    $base = omochix_design_url();
    return ('' === $base || 'web' !== $item['medium']) ? '' : $base . 'demo/web/' . $item['slug'] . '/';
}

function omochix_design_asset_url($path) {
    return omochix_design_assets_uri() . '/' . $path;
}

/* -------------------------------------------------------------------------
 * Routing
 * ---------------------------------------------------------------------- */

add_filter('query_vars', static function ($vars) {
    $vars[] = OMOCHIX_DESIGN_KIND_VAR;
    $vars[] = OMOCHIX_DESIGN_SLUG_VAR;
    $vars[] = OMOCHIX_DESIGN_DEMO_VAR;
    $vars[] = OMOCHIX_DESIGN_SITEMAP_VAR;
    return $vars;
});

/**
 * The Design Library query vars are internal: they may only come from
 * the rewrite rules. If a request carries one in its query string (or form
 * body), it is a 404 — otherwise "?omx_design_demo=1" would turn a detail URL
 * into the demo document, and "?omx_design_slug=" a list URL into a detail.
 */
add_filter('request', static function ($query_vars) {
    foreach ([OMOCHIX_DESIGN_KIND_VAR, OMOCHIX_DESIGN_SLUG_VAR, OMOCHIX_DESIGN_DEMO_VAR, OMOCHIX_DESIGN_SITEMAP_VAR] as $var) {
        if (isset($_GET[$var]) || isset($_POST[$var])) { // phpcs:ignore WordPress.Security.NonceVerification -- only detects the key to refuse it.
            unset($query_vars[OMOCHIX_DESIGN_KIND_VAR], $query_vars[OMOCHIX_DESIGN_SLUG_VAR], $query_vars[OMOCHIX_DESIGN_DEMO_VAR], $query_vars[OMOCHIX_DESIGN_SITEMAP_VAR]);
            $query_vars['error'] = '404';
            return $query_vars;
        }
    }
    return $query_vars;
});

function omochix_design_register_rewrite_rules() {
    $p    = preg_quote(OMOCHIX_DESIGN_PATH, '#');
    $seg  = '(web|video|app|components)';
    $page = 'index.php?pagename=' . OMOCHIX_DESIGN_PATH;
    add_rewrite_rule("^$p/demo/$seg/([a-z0-9-]+)/?$", $page . '&' . OMOCHIX_DESIGN_DEMO_VAR . '=1&' . OMOCHIX_DESIGN_KIND_VAR . '=$matches[1]&' . OMOCHIX_DESIGN_SLUG_VAR . '=$matches[2]', 'top');
    add_rewrite_rule("^$p/$seg/([a-z0-9-]+)/?$", $page . '&' . OMOCHIX_DESIGN_KIND_VAR . '=$matches[1]&' . OMOCHIX_DESIGN_SLUG_VAR . '=$matches[2]', 'top');
    add_rewrite_rule("^$p/$seg/?$", $page . '&' . OMOCHIX_DESIGN_KIND_VAR . '=$matches[1]', 'top');
    add_rewrite_rule('^' . preg_quote(OMOCHIX_DESIGN_SITEMAP_PATH, '#') . '$', 'index.php?' . OMOCHIX_DESIGN_SITEMAP_VAR . '=1', 'top');
}
add_action('init', 'omochix_design_register_rewrite_rules');

/** Whether the stored rewrite rules contain the Design Library rules (plain permalinks have none at all). */
function omochix_design_rewrite_rules_present() {
    if ('' === (string) get_option('permalink_structure')) {
        return true; // no rewrite rules in use; nothing to restore
    }
    $rules = get_option('rewrite_rules');
    return is_array($rules) && isset($rules['^' . OMOCHIX_DESIGN_PATH . '/(web|video|app|components)/?$'], $rules['^' . preg_quote(OMOCHIX_DESIGN_SITEMAP_PATH, '#') . '$']);
}

/**
 * Flush once per rule version (a theme update does not fire after_switch_theme),
 * and again if the stored rules lost ours — e.g. a rollback to a theme without
 * the Design Library, permalinks regenerated there (Runbook), then this theme
 * deployed again: the version option still matches, the rules are gone.
 */
add_action('init', static function () {
    if (get_option('omochix_design_rewrite_version') === OMOCHIX_DESIGN_REWRITE_VERSION && omochix_design_rewrite_rules_present()) {
        return;
    }
    flush_rewrite_rules(false); // deferred by WordPress to wp_loaded (priority 10)
    update_option('omochix_design_rewrite_version', OMOCHIX_DESIGN_REWRITE_VERSION);
    // When several requests heal at once, all but one save an unchanged row; update_option() then keeps the
    // stale cached rules and this request would route with them (404 on /design/). Re-read from the database
    // right after the flush. Only on a request that healed; normal requests never get here.
    add_action('wp_loaded', 'omochix_design_reload_rewrite_rules', 11);
}, 20);

/** After a heal: drop the cached options so routing reads the stored (fresh) rewrite rules. */
function omochix_design_reload_rewrite_rules() {
    global $wp_rewrite;
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('rewrite_rules', 'options');
    $wp_rewrite->rules = '';
}

add_action('after_switch_theme', static function () {
    delete_option('omochix_design_rewrite_version');
});

/** Medium requested in the URL ('' on the top page; unknown segment = 'invalid'). */
function omochix_design_requested_medium() {
    $seg = get_query_var(OMOCHIX_DESIGN_KIND_VAR, '');
    if ('' === $seg || !is_string($seg)) {
        return '';
    }
    $medium = omochix_design_medium_from_segment($seg);
    return '' === $medium ? 'invalid' : $medium;
}

function omochix_design_requested_slug() {
    $slug = get_query_var(OMOCHIX_DESIGN_SLUG_VAR, '');
    return is_string($slug) && omochix_design_is_slug($slug) ? $slug : '';
}

/** Only the rewritten "/design/demo/..." request (pagename=design) is a demo request. */
function omochix_design_is_demo_request() {
    return '1' === (string) get_query_var(OMOCHIX_DESIGN_DEMO_VAR, '') && OMOCHIX_DESIGN_PATH === (string) get_query_var('pagename', '');
}

/** Whether the main query is the design page (top, list or detail). */
function omochix_design_is_request() {
    $id = omochix_design_page_id();
    return $id && is_page() && get_queried_object_id() === $id && !omochix_design_is_demo_request();
}

/** What the current request shows: ['view' => top|list|detail|none, 'medium' => ..., 'item' => ...]. */
function omochix_design_current() {
    if (isset($GLOBALS['omochix_design_current'])) {
        return $GLOBALS['omochix_design_current'];
    }
    $cur = ['view' => 'none', 'medium' => '', 'item' => null];
    if (omochix_design_is_request()) {
        $medium = omochix_design_requested_medium();
        $slug   = omochix_design_requested_slug();
        $raw    = get_query_var(OMOCHIX_DESIGN_SLUG_VAR, '');
        if ('' === $medium) {
            if (omochix_design_is_public('')) {
                $cur['view'] = 'top';
            }
        } elseif ('invalid' !== $medium && '' === (string) $raw) {
            if (omochix_design_is_public($medium)) {
                $cur = ['view' => 'list', 'medium' => $medium, 'item' => null];
            }
        } elseif ('invalid' !== $medium && '' !== $slug) {
            $item = omochix_design_get_item($medium, $slug);
            if ($item) {
                $cur = ['view' => 'detail', 'medium' => $medium, 'item' => $item];
            }
        }
    }
    if (did_action('template_redirect')) {
        $GLOBALS['omochix_design_current'] = $cur; // the main query is final from here on
    }
    return $cur;
}

/** Anything on the design page that is not public is a 404 (no empty noindex page, D0 §4.1). */
add_action('template_redirect', static function () {
    if (!omochix_design_is_request()) {
        return;
    }
    if ('none' === omochix_design_current()['view']) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return;
    }
    omochix_design_send_page_headers();
}, 5);

/**
 * A design URL that became a 404 stays a 404 (C1-C5): without this,
 * redirect_canonical "guesses" a similar URL (the design page, or any post
 * whose slug starts with "design") and answers 301 instead.
 */
add_filter('redirect_canonical', static function ($redirect) {
    if (is_404() && OMOCHIX_DESIGN_PATH === (string) get_query_var('pagename', '')) {
        return false;
    }
    return $redirect;
});

add_filter('template_include', static function ($template) {
    if (!omochix_design_is_request() || 'none' === omochix_design_current()['view']) {
        return $template;
    }
    $file = get_theme_file_path('/page-design.php');
    return file_exists($file) ? $file : $template;
}, 9);

/* -------------------------------------------------------------------------
 * Page security headers (top / list / detail; the demo endpoint sends its own)
 * Report-Only CSP like Labs (D0 §3.5), plus enforced framing protection.
 * ---------------------------------------------------------------------- */

function omochix_design_page_headers() {
    $ga  = 'https://www.googletagmanager.com https://*.googletagmanager.com https://*.google-analytics.com https://*.analytics.google.com https://*.g.doubleclick.net';
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob: https://s.w.org https://secure.gravatar.com " . $ga,
        "media-src 'self'",
        "font-src 'self' data:",
        "connect-src 'self' " . $ga,
        "worker-src 'self' blob:",
        "frame-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ]);
    return [
        'Content-Security-Policy-Report-Only' => $csp,
        'X-Content-Type-Options'             => 'nosniff',
        'X-Frame-Options'                    => 'SAMEORIGIN',
        'Referrer-Policy'                    => 'strict-origin-when-cross-origin',
    ];
}

function omochix_design_send_page_headers() {
    if (headers_sent()) {
        return;
    }
    $present = [];
    foreach (headers_list() as $line) {
        $present[strtolower(trim((string) strstr($line, ':', true)))] = true;
    }
    foreach (omochix_design_page_headers() as $name => $value) {
        $lower = strtolower($name);
        if (isset($present[$lower]) || ('content-security-policy-report-only' === $lower && isset($present['content-security-policy']))) {
            continue; // never replace a policy someone else already set
        }
        header($name . ': ' . $value, false);
    }
}

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

add_action('wp_enqueue_scripts', static function () {
    $cur = omochix_design_current();
    if ('none' === $cur['view']) {
        return;
    }
    $deps = wp_style_is('omochix-shell', 'enqueued') ? ['omochix-style', 'omochix-shell'] : ['omochix-style'];
    $css  = '/assets/css/design-library.css';
    wp_enqueue_style('omochix-design-library', get_theme_file_uri($css), $deps, (string) filemtime(get_theme_file_path($css)));
    $js = '/assets/js/design-library.js';
    wp_enqueue_script('omochix-design-library', get_theme_file_uri($js), [], (string) filemtime(get_theme_file_path($js)), true);
    wp_add_inline_script('omochix-design-library', 'window.omochixDesignLibrary = ' . wp_json_encode([
        'maxPlaying' => OMOCHIX_DESIGN_MAX_PLAYING,
        'strings'    => [
            'play'      => __('プレビューを再生', 'omochix'),
            'pause'     => __('プレビューを停止', 'omochix'),
            'count'     => __('%s件の作品', 'omochix'),
            'noResults' => __('条件に合う作品がありません', 'omochix'),
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';', 'before');
    if ('detail' === $cur['view']) {
        $pjs = '/assets/js/design-library-preview.js';
        wp_enqueue_script('omochix-design-library-preview', get_theme_file_uri($pjs), [], (string) filemtime(get_theme_file_path($pjs)), true);
    }
}, 20);

/* -------------------------------------------------------------------------
 * SEO (title, canonical, robots, breadcrumbs — with and without Slim SEO)
 * ---------------------------------------------------------------------- */

function omochix_design_is_filtered_list() {
    $cur = omochix_design_current();
    return in_array($cur['view'], ['top', 'list'], true) && '' !== implode('', omochix_design_sanitize_filters($_GET)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter state.
}

/** Canonical URL of the current view ('' when not a design view). Filtered lists point at the unfiltered list. */
function omochix_design_canonical() {
    $cur = omochix_design_current();
    if ('detail' === $cur['view']) {
        return omochix_design_item_url($cur['item']);
    }
    if ('list' === $cur['view']) {
        return omochix_design_medium_url($cur['medium']);
    }
    return 'top' === $cur['view'] ? omochix_design_url() : '';
}

/** Document title for the current view, '' to keep WordPress's own. */
function omochix_design_view_title() {
    $cur = omochix_design_current();
    $mediums = omochix_design_mediums();
    if ('detail' === $cur['view']) {
        return sprintf(__('%1$s — %2$sの見本 | デザインライブラリー', 'omochix'), $cur['item']['title'], $mediums[$cur['medium']]['label']);
    }
    if ('list' === $cur['view']) {
        return sprintf(__('%s | デザインライブラリー', 'omochix'), $mediums[$cur['medium']]['label']);
    }
    return '';
}

add_filter('document_title_parts', static function ($parts) {
    $t = omochix_design_view_title();
    if ($t) {
        $parts['title'] = $t;
    }
    return $parts;
});
add_filter('slim_seo_meta_title', static function ($title) {
    $t = omochix_design_view_title();
    return $t ? $t . ' | ' . get_bloginfo('name') : $title;
});
add_filter('slim_seo_meta_description', static function ($description) {
    $cur = omochix_design_current();
    if ('detail' === $cur['view']) {
        return $cur['item']['description'];
    }
    if (in_array($cur['view'], ['top', 'list'], true)) {
        return __('Web デザインと動画モーションの作品を、動くプレビューつきで見比べられます。用途・表現・技術で絞り込み、制作手順とプロンプトも確認できます。', 'omochix');
    }
    return $description;
});
add_filter('slim_seo_canonical_url', static function ($url) {
    $c = omochix_design_canonical();
    return '' !== $c ? $c : $url;
});
// Without Slim SEO, WordPress prints rel=canonical from get_canonical_url() (the page itself, "/design/").
add_filter('get_canonical_url', static function ($url, $post) {
    if ($post instanceof WP_Post && (int) $post->ID === omochix_design_page_id()) {
        $c = omochix_design_canonical();
        return '' !== $c ? $c : $url;
    }
    return $url;
}, 10, 2);
/**
 * Whether the current design view must not be indexed: a filtered list, a
 * work that is not indexable here (not verified for this environment), or a
 * list / top page without any indexable work.
 */
function omochix_design_view_noindex() {
    $cur = omochix_design_current();
    if ('none' === $cur['view']) {
        return false;
    }
    if (omochix_design_is_filtered_list()) {
        return true;
    }
    if ('detail' === $cur['view']) {
        return !omochix_design_is_indexable($cur['item']);
    }
    return [] === omochix_design_indexable_items('list' === $cur['view'] ? $cur['medium'] : '');
}

add_filter('slim_seo_robots_index', static function ($indexed) {
    return omochix_design_view_noindex() ? false : $indexed;
});
add_filter('wp_robots', static function ($robots) {
    if (omochix_design_view_noindex()) {
        $robots['noindex'] = true;
        if (empty($robots['nofollow'])) {
            $robots['follow'] = true; // never "nofollow, follow" on a site that is not public
        }
    }
    return $robots;
});
add_filter('slim_seo_breadcrumbs_links', static function ($links) {
    $cur = omochix_design_current();
    if (!is_array($links) || !in_array($cur['view'], ['list', 'detail'], true)) {
        return $links;
    }
    $links[] = ['url' => omochix_design_url(), 'text' => get_the_title(omochix_design_page_id())];
    if ('detail' === $cur['view']) {
        $links[] = ['url' => omochix_design_medium_url($cur['medium']), 'text' => omochix_design_mediums()[$cur['medium']]['label']];
    }
    return $links;
});
add_filter('single_post_title', static function ($title, $post) {
    $cur = omochix_design_current();
    if ('none' !== $cur['view'] && $post instanceof WP_Post && (int) $post->ID === omochix_design_page_id()) {
        if ('detail' === $cur['view']) {
            return $cur['item']['title'];
        }
        if ('list' === $cur['view']) {
            return omochix_design_mediums()[$cur['medium']]['label'];
        }
    }
    return $title;
}, 10, 2);

/* -------------------------------------------------------------------------
 * Sitemap ("/design-sitemap.xml"): list pages and works that are indexable
 * here. The page "/design/" itself is a normal page in Slim SEO's page
 * sitemap and is not repeated; it leaves that sitemap while nothing is
 * indexable. With nothing indexable the URL is a normal 404 and robots.txt
 * does not mention it. Demo URLs are never listed (they send noindex).
 * ---------------------------------------------------------------------- */

/** @return array<int, array{loc: string, lastmod: string}> */
function omochix_design_sitemap_entries() {
    if (!omochix_design_page_id()) {
        return [];
    }
    $out = [];
    foreach (omochix_design_public_mediums() as $medium) {
        $items = omochix_design_indexable_items($medium);
        if (!$items) {
            continue;
        }
        $dates = [];
        $rows  = [];
        foreach ($items as $item) {
            $date    = (string) ($item['verification']['checked'] ?? $item['audit']['reviewed']);
            $dates[] = $date;
            $rows[]  = ['loc' => omochix_design_item_url($item), 'lastmod' => $date];
        }
        rsort($dates);
        $out[] = ['loc' => omochix_design_medium_url($medium), 'lastmod' => $dates[0]];
        $out   = array_merge($out, $rows);
    }
    return $out;
}

/** Public sitemap URL, or '' while nothing is indexable. */
function omochix_design_sitemap_url() {
    return omochix_design_sitemap_entries() ? home_url('/' . OMOCHIX_DESIGN_SITEMAP_PATH) : '';
}

function omochix_design_sitemap_xml(array $entries) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $e) {
        $xml .= "\t<url><loc>" . esc_url($e['loc']) . '</loc><lastmod>' . esc_html($e['lastmod']) . "</lastmod></url>\n";
    }
    return $xml . "</urlset>\n";
}

add_action('template_redirect', static function () {
    if ('1' !== (string) get_query_var(OMOCHIX_DESIGN_SITEMAP_VAR, '')) {
        return;
    }
    $entries = omochix_design_sitemap_entries();
    if (!$entries) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return;
    }
    if (!headers_sent()) {
        status_header(200);
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');
    }
    if ('HEAD' !== ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
        echo omochix_design_sitemap_xml($entries); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in omochix_design_sitemap_xml().
    }
    exit;
}, 0);

/** Announce the sitemap in robots.txt (after Slim SEO), only while it exists and the site is public. */
add_filter('robots_txt', static function ($output, $public) {
    $url = $public ? omochix_design_sitemap_url() : '';
    if ('' === $url || false !== stripos((string) $output, $url)) {
        return $output;
    }
    return rtrim((string) $output, "\r\n") . "\nSitemap: " . $url . "\n";
}, 10000, 2);

/** The page "/design/" leaves Slim SEO's page sitemap while no work is indexable. */
add_filter('slim_seo_sitemap_post_ignore', static function ($ignore, $post_id = 0) {
    $id = $post_id instanceof WP_Post ? (int) $post_id->ID : (int) $post_id;
    if ($id && $id === omochix_design_page_id()) {
        return [] === omochix_design_indexable_items('');
    }
    return $ignore;
}, 10, 2);

/* -------------------------------------------------------------------------
 * JSON-LD: added to Slim SEO's graph (Slim SEO already prints WebPage and
 * BreadcrumbList from the breadcrumb links above), so nothing is duplicated.
 * Detail: CreativeWork (Web) or VideoObject (video) as the page's main
 * entity. Top / list: an ItemList of the indexable works. Nothing is added
 * to a noindex view.
 * ---------------------------------------------------------------------- */

/** Schema entity of one work. */
function omochix_design_schema_entity(array $item) {
    $url    = omochix_design_item_url($item);
    $date   = (string) ($item['verification']['checked'] ?? $item['audit']['reviewed']);
    $poster = omochix_design_asset_url($item['posterFallback'] ?? $item['poster']);
    $author = ['@type' => 'Organization', 'name' => $item['credits']['author']];
    $tags   = [];
    foreach (['use_cases' => omochix_design_use_cases(), 'motion_types' => omochix_design_motion_types(), 'technologies' => omochix_design_technologies()] as $field => $labels) {
        foreach ($item[$field] as $v) {
            $tags[] = $labels[$v];
        }
    }
    if ('video' === $item['medium'] && !empty($item['video'])) {
        $v = $item['video'];
        return [
            '@type'        => 'VideoObject',
            '@id'          => $url . '#work',
            'name'         => $item['title'],
            'description'  => $item['description'],
            'url'          => $url,
            'thumbnailUrl' => [$poster],
            'contentUrl'   => omochix_design_asset_url($v['mp4']),
            'uploadDate'   => $date,
            'duration'     => 'PT' . rtrim(rtrim(number_format((float) $v['seconds'], 2, '.', ''), '0'), '.') . 'S',
            'width'        => $v['width'],
            'height'       => $v['height'],
            'inLanguage'   => 'ja',
            'keywords'     => implode(', ', $tags),
            'author'       => $author,
        ];
    }
    return [
        '@type'               => 'CreativeWork',
        '@id'                 => $url . '#work',
        'name'                => $item['title'],
        'description'         => $item['description'],
        'url'                 => $url,
        'image'               => $poster,
        'dateModified'        => $date,
        'inLanguage'          => 'ja',
        'keywords'            => implode(', ', $tags),
        'isAccessibleForFree' => true,
        'author'              => $author,
    ];
}

/**
 * @param array<int, array<string, mixed>> $graph Slim SEO's graph.
 * @return array<int, array<string, mixed>>
 */
function omochix_design_schema_graph($graph) {
    $cur = omochix_design_current();
    if (!is_array($graph) || 'none' === $cur['view'] || omochix_design_view_noindex()) {
        return $graph;
    }
    if ('detail' === $cur['view']) {
        $main = omochix_design_schema_entity($cur['item']);
    } else {
        $page_url = 'list' === $cur['view'] ? omochix_design_medium_url($cur['medium']) : omochix_design_url();
        $main     = ['@type' => 'ItemList', '@id' => $page_url . '#works', 'itemListElement' => []];
        foreach (array_values(omochix_design_indexable_items('list' === $cur['view'] ? $cur['medium'] : '')) as $i => $item) {
            $main['itemListElement'][] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => omochix_design_item_url($item), 'name' => $item['title']];
        }
        $main['numberOfItems'] = count($main['itemListElement']);
    }
    foreach ($graph as &$entity) {
        if (isset($entity['@type']) && in_array($entity['@type'], ['WebPage', 'CollectionPage', 'ItemPage'], true)) {
            $entity['mainEntity'] = ['@id' => $main['@id']];
        }
    }
    unset($entity);
    $graph[] = $main;
    return $graph;
}
add_filter('slim_seo_schema_graph', 'omochix_design_schema_graph', 30);

/** The shared editorial shell (left navigation) on the design pages. */
add_filter('omochix_shell_enabled_for_request', static function ($enabled) {
    return $enabled || 'none' !== omochix_design_current()['view'];
});
