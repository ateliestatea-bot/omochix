<?php
/**
 * Learn v2 / Motion Composer: pick up to three Motion Gallery samples, order
 * them, adjust them and get one implementation brief for an AI assistant.
 *
 * Page: "/learn/motion/compose/" (a child page of the gallery page). The
 * gallery's rewrite rule would otherwise read "compose" as a sample slug, so
 * this file registers its own rule ahead of it. Everything else is
 * client-side: the composition lives in the URL hash (#c=…) and in a
 * localStorage draft; no options, posts or tables are written.
 *
 * Builds on inc/learn-gallery.php (catalog, params, previews, copy templates)
 * without changing its behaviour.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

const OMOCHIX_LEARN_COMPOSER_PATH            = 'learn/motion/compose';
const OMOCHIX_LEARN_COMPOSER_REWRITE_VERSION = '1';
const OMOCHIX_LEARN_COMPOSER_MAX_SLOTS       = 3;
const OMOCHIX_LEARN_COMPOSER_HASH_KEY        = 'c';
const OMOCHIX_LEARN_COMPOSER_HASH_VERSION    = '1';
const OMOCHIX_LEARN_COMPOSER_NOTE_MAX        = 40;
const OMOCHIX_LEARN_COMPOSER_NOTE_RE         = '/^[\p{L}\p{N} 　・、。，．ー\-_()（）!?！？:：]{0,40}$/u';
const OMOCHIX_LEARN_COMPOSER_PANEL_VAR       = 'omx_panel';

/* -------------------------------------------------------------------------
 * Routing
 * ---------------------------------------------------------------------- */

/** Published composer page ID, or 0. */
function omochix_learn_composer_page_id() {
    static $page_id = null;
    if (null !== $page_id) {
        return $page_id;
    }
    $page    = get_page_by_path(OMOCHIX_LEARN_COMPOSER_PATH, OBJECT, 'page');
    $page_id = $page instanceof WP_Post && 'publish' === $page->post_status ? (int) $page->ID : 0;
    return $page_id;
}

/** Composer URL ('' until the page exists). */
function omochix_learn_composer_url() {
    $page_id = omochix_learn_composer_page_id();
    return $page_id ? get_permalink($page_id) : '';
}

/** Whether the main query is the composer page. */
function omochix_learn_composer_is_request() {
    if (!is_page()) {
        return false;
    }
    $page_id = omochix_learn_composer_page_id();
    return $page_id && get_queried_object_id() === $page_id;
}

/**
 * Registered after the gallery's rule (init priority 10) and also at the
 * top, so it is matched first and "compose" is never taken for a sample.
 */
function omochix_learn_composer_register_rewrite_rules() {
    add_rewrite_rule(
        '^' . preg_quote(OMOCHIX_LEARN_COMPOSER_PATH, '#') . '/?$',
        'index.php?pagename=' . OMOCHIX_LEARN_COMPOSER_PATH,
        'top'
    );
}
add_action('init', 'omochix_learn_composer_register_rewrite_rules', 11);

function omochix_learn_composer_maybe_flush_rewrite_rules() {
    if (get_option('omochix_learn_composer_rewrite_version') === OMOCHIX_LEARN_COMPOSER_REWRITE_VERSION) {
        return;
    }
    flush_rewrite_rules(false);
    update_option('omochix_learn_composer_rewrite_version', OMOCHIX_LEARN_COMPOSER_REWRITE_VERSION);
}
add_action('init', 'omochix_learn_composer_maybe_flush_rewrite_rules', 21);
add_action('after_switch_theme', static function () {
    delete_option('omochix_learn_composer_rewrite_version');
});

/** Serve page-learn-composer.php; runs before the gallery (9) and Learn (10) filters. */
function omochix_learn_composer_template_include($template) {
    if (!omochix_learn_composer_is_request()) {
        return $template;
    }
    $file = get_theme_file_path('/page-learn-composer.php');
    return file_exists($file) ? $file : $template;
}
add_filter('template_include', 'omochix_learn_composer_template_include', 8);

function omochix_learn_composer_register_query_vars($vars) {
    $vars[] = OMOCHIX_LEARN_COMPOSER_PANEL_VAR;
    return $vars;
}
add_filter('query_vars', 'omochix_learn_composer_register_query_vars');

/**
 * Panel-only fragment: "/learn/motion/{slug}/?omx_partial=1&omx_panel=1"
 * prints just the sample's adjustment panel (plus its copy templates) so a
 * composer slot can load it on demand. Runs before the gallery's own
 * template_redirect (priority 10), which would print the whole detail.
 */
function omochix_learn_composer_panel_redirect() {
    if (!omochix_learn_gallery_is_request() || !omochix_learn_gallery_is_partial_request() || '1' !== (string) get_query_var(OMOCHIX_LEARN_COMPOSER_PANEL_VAR, '')) {
        return;
    }
    $slug = omochix_learn_gallery_requested_slug();
    $item = '' !== $slug ? omochix_learn_gallery_get_item($slug) : null;
    if (!$item) {
        return; // the gallery turns it into a 404
    }
    header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
    header('X-Robots-Tag: noindex');
    get_template_part('template-parts/learn-gallery/playground', null, ['item' => $item, 'heading' => 'h3', 'share' => false]);
    exit;
}
add_action('template_redirect', 'omochix_learn_composer_panel_redirect', 9);

/* -------------------------------------------------------------------------
 * Composition model (mirrored in assets/js/learn-composer-core.js)
 * ---------------------------------------------------------------------- */

/** Allowed values of a slot's purpose note; both lists are also offered as presets in the UI. */
function omochix_learn_composer_presets() {
    return [
        'target' => [
            'hero'    => __('ヒーロー', 'omochix'),
            'heading' => __('見出し', 'omochix'),
            'cta'     => __('CTAボタン', 'omochix'),
            'card'    => __('カード', 'omochix'),
            'nav'     => __('ナビゲーション', 'omochix'),
            'feedback'=> __('通知・フィードバック', 'omochix'),
            'bg'      => __('背景', 'omochix'),
            'list'    => __('リスト', 'omochix'),
        ],
        'intent' => [
            'first'   => __('第一印象', 'omochix'),
            'guide'   => __('視線誘導', 'omochix'),
            'confirm' => __('操作の確認', 'omochix'),
            'wait'    => __('待ち時間の演出', 'omochix'),
            'emphasis'=> __('強調', 'omochix'),
            'mood'    => __('雰囲気づくり', 'omochix'),
        ],
    ];
}

/**
 * Coerce a raw composition into a valid one. Unknown samples and extra
 * slots are dropped, params go through the gallery's sanitizer and only
 * values that differ from the default are kept, notes are limited to a
 * short plain string.
 *
 * @param mixed $raw ['slots' => [['slug' => …, 'params' => […], 'target' => …, 'intent' => …], …]]
 * @return array{v: string, slots: array<int, array{slug: string, params: array<string, float|string>, target: string, intent: string}>}
 */
function omochix_learn_composer_sanitize($raw) {
    $out   = ['v' => OMOCHIX_LEARN_COMPOSER_HASH_VERSION, 'slots' => []];
    $slots = is_array($raw) && isset($raw['slots']) && is_array($raw['slots']) ? $raw['slots'] : [];
    foreach ($slots as $slot) {
        if (count($out['slots']) >= OMOCHIX_LEARN_COMPOSER_MAX_SLOTS) {
            break;
        }
        if (!is_array($slot)) {
            continue;
        }
        $slug = isset($slot['slug']) && is_string($slot['slug']) ? sanitize_title($slot['slug']) : '';
        $item = '' !== $slug ? omochix_learn_gallery_get_item($slug) : null;
        if (!$item) {
            continue;
        }
        $values = omochix_learn_gallery_sanitize_params($item, is_array($slot['params'] ?? null) ? $slot['params'] : []);
        $params = [];
        foreach ($item['params'] as $def) {
            if (!omochix_learn_gallery_param_is_default($def, $values[$def['key']])) {
                $params[$def['key']] = $values[$def['key']];
            }
        }
        $out['slots'][] = [
            'slug'   => $slug,
            'params' => $params,
            'target' => omochix_learn_composer_sanitize_note($slot['target'] ?? ''),
            'intent' => omochix_learn_composer_sanitize_note($slot['intent'] ?? ''),
        ];
    }
    return $out;
}

/** A short plain note (preset key or free text); '' when invalid. */
function omochix_learn_composer_sanitize_note($note) {
    if (!is_string($note)) {
        return '';
    }
    $note = trim(preg_replace('/\s+/u', ' ', $note));
    if (mb_strlen($note) > OMOCHIX_LEARN_COMPOSER_NOTE_MAX || !preg_match(OMOCHIX_LEARN_COMPOSER_NOTE_RE, $note)) {
        return '';
    }
    return $note;
}

/**
 * Hash payload for a composition: "1;pop-in:cycle=1.5,color=ff0000;toast;gradient-shift:angle=45".
 * Version first; one segment per slot: slug, then ":" and key=value pairs
 * for non-default params (colours without "#"). Notes travel as t= / i=
 * pairs after the params, percent-encoded. '' for an empty composition.
 */
function omochix_learn_composer_hash(array $composition) {
    $composition = omochix_learn_composer_sanitize($composition);
    if (!$composition['slots']) {
        return '';
    }
    $segments = [OMOCHIX_LEARN_COMPOSER_HASH_VERSION];
    foreach ($composition['slots'] as $slot) {
        $item  = omochix_learn_gallery_get_item($slot['slug']);
        $pairs = [];
        foreach ($item['params'] as $def) {
            if (!array_key_exists($def['key'], $slot['params'])) {
                continue;
            }
            $v = $slot['params'][$def['key']];
            $pairs[] = $def['key'] . '=' . ('color' === $def['type'] ? ltrim($v, '#') : ('range' === $def['type'] ? omochix_learn_gallery_format_number($v) : $v));
        }
        if ('' !== $slot['target']) {
            $pairs[] = 't=' . rawurlencode($slot['target']);
        }
        if ('' !== $slot['intent']) {
            $pairs[] = 'i=' . rawurlencode($slot['intent']);
        }
        $segments[] = $slot['slug'] . ($pairs ? ':' . implode(',', $pairs) : '');
    }
    return implode(';', $segments);
}

/** Parse a hash payload back into a raw composition (then pass it to the sanitizer). */
function omochix_learn_composer_parse_hash($payload) {
    $raw      = ['slots' => []];
    $segments = explode(';', (string) $payload);
    if (array_shift($segments) !== OMOCHIX_LEARN_COMPOSER_HASH_VERSION) {
        return $raw;
    }
    foreach (array_slice($segments, 0, OMOCHIX_LEARN_COMPOSER_MAX_SLOTS) as $segment) {
        $colon = strpos($segment, ':');
        $slug  = false === $colon ? $segment : substr($segment, 0, $colon);
        $slot  = ['slug' => $slug, 'params' => [], 'target' => '', 'intent' => ''];
        if (false !== $colon) {
            foreach (array_slice(explode(',', substr($segment, $colon + 1)), 0, 20) as $pair) {
                $eq = strpos($pair, '=');
                if (false === $eq || 0 === $eq) {
                    continue;
                }
                $key   = substr($pair, 0, $eq);
                $value = substr($pair, $eq + 1, 64);
                if ('t' === $key) {
                    $slot['target'] = rawurldecode($value);
                } elseif ('i' === $key) {
                    $slot['intent'] = rawurldecode($value);
                } elseif (preg_match(OMOCHIX_LEARN_GALLERY_PARAM_KEY_RE, $key)) {
                    $slot['params'][$key] = $value;
                }
            }
        }
        $raw['slots'][] = $slot;
    }
    return $raw;
}

/* -------------------------------------------------------------------------
 * Combined preview (mirrored in learn-composer-core.js: scopedBlock /
 * buildCombinedDoc). Several samples share one document, so their parameter
 * variables are scoped to a wrapper per slot instead of :root, and JS
 * samples get a per-slot `omx` whose param() reads that slot's values.
 * ---------------------------------------------------------------------- */

/** ".omx-slot[data-slot=\"n\"]{--p-a:v;…}" for the slot's non-default CSS params, '' when none. */
function omochix_learn_composer_scoped_block(array $item, array $values, $slot) {
    $block = omochix_learn_gallery_params_blocks($item, $values);
    if ('' === $block['css']) {
        return '';
    }
    return '.omx-slot[data-slot="' . (int) $slot . '"]{' . substr($block['css'], strlen(':root{')); // keep the inner "a:v;b:w}" as is
}

/** Non-default JS params of a slot as JSON ("{}" when none), for the per-slot omx shim. */
function omochix_learn_composer_js_params(array $item, array $values) {
    $block = omochix_learn_gallery_params_blocks($item, $values);
    if ('' === $block['js']) {
        return '{}';
    }
    return substr($block['js'], strlen('window.omxParams='), -1); // strip the assignment and the ";"
}

/** The per-slot omx shim used inside the combined document (plain JS, no WordPress). */
function omochix_learn_composer_slot_shim() {
    return 'function omxSlot(n,p){var l=[];return{animate:function(f){window.omx.animate(f)},param:function(k,d){return Object.prototype.hasOwnProperty.call(p,k)?p[k]:d},onParams:function(f){l.push(f)}}}';
}

/** Fits every .omx-stage to its section's width (the runtime only fits the first one). */
function omochix_learn_composer_fit_script() {
    return '(function(){var r=document.documentElement,w=parseInt(r.getAttribute("data-stage-w"),10)||320,h=parseInt(r.getAttribute("data-stage-h"),10)||180;function fit(){var s=document.querySelectorAll(".omx-slot");for(var i=0;i<s.length;i++){var k=s[i].clientWidth/w;s[i].style.height=Math.round(h*k)+"px";var g=s[i].querySelector(".omx-stage");if(g){g.style.setProperty("--omx-w",w+"px");g.style.setProperty("--omx-h",h+"px");g.style.setProperty("--omx-scale",String(k));}}}window.addEventListener("resize",fit);fit();}());';
}

/**
 * One sandboxed document showing the slots stacked in order.
 *
 * @param array<int, array{item: array<string, mixed>, values: array<string, float|string>}> $slots In order.
 * @return string
 */
function omochix_learn_composer_preview_document(array $slots) {
    $stage  = omochix_learn_gallery_get_stage();
    $scoped = [];
    $css    = [];
    $html   = [];
    $js     = [];
    $n      = 0;
    foreach ($slots as $slot) {
        $n++;
        $item   = $slot['item'];
        $values = omochix_learn_gallery_sanitize_params($item, $slot['values'] ?? []);
        $block  = omochix_learn_composer_scoped_block($item, $values, $n);
        if ('' !== $block) {
            $scoped[] = $block;
        }
        if ('' !== $item['preview']['css']) {
            $css[] = '<style>' . $item['preview']['css'] . '</style>';
        }
        $html[] = '<section class="omx-slot" data-slot="' . $n . '"><div class="omx-stage">' . $item['preview']['html'] . '</div></section>';
        if ('' !== $item['preview']['js']) {
            $js[] = '<script>(function(omx){' . $item['preview']['js'] . '}(omxSlot(' . $n . ',' . omochix_learn_composer_js_params($item, $values) . ')));</script>';
        }
    }
    return '<!doctype html><html lang="ja" data-theme="light" data-stage-w="' . (int) $stage['width'] . '" data-stage-h="' . (int) $stage['height'] . '">'
        . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . esc_html(__('組み合わせプレビュー', 'omochix')) . '</title>'
        . '<style>' . omochix_learn_gallery_runtime('css') . '</style>'
        . '<style>html,body{height:auto;overflow:hidden}.omx-slot{position:relative;width:100%;max-width:640px;margin:0 auto;overflow:hidden}.omx-slot+.omx-slot{border-top:1px solid var(--line)}.omx-slot .omx-stage{transform-origin:top left;left:0;top:0;transform:scale(var(--omx-scale,1))}</style>'
        . '<style id="omx-params">' . implode('', $scoped) . '</style>'
        . implode('', $css)
        . '</head><body>' . implode('', $html)
        . '<script>' . omochix_learn_gallery_runtime('js') . '</script>'
        . '<script>' . omochix_learn_composer_slot_shim() . '</script>'
        . implode('', $js)
        . '<script>' . omochix_learn_composer_fit_script() . '</script>'
        . '</body></html>';
}

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

/** Composer assets, after the gallery's (which inc/learn-gallery.php enqueues for this page too). */
function omochix_learn_composer_enqueue_assets() {
    if (!omochix_learn_composer_is_request()) {
        return;
    }
    $theme = wp_get_theme();
    wp_enqueue_style('omochix-learn-composer', get_theme_file_uri('/assets/css/learn-composer.css'), ['omochix-learn-v2'], (string) filemtime(get_theme_file_path('/assets/css/learn-composer.css')));
    wp_enqueue_script('omochix-learn-composer-core', get_theme_file_uri('/assets/js/learn-composer-core.js'), ['omochix-learn-params'], $theme->get('Version'), true);
    wp_enqueue_script('omochix-learn-composer', get_theme_file_uri('/assets/js/learn-composer.js'), ['omochix-learn-composer-core', 'omochix-learn-playground'], $theme->get('Version'), true);
    wp_add_inline_script('omochix-learn-composer', 'window.omochixLearnComposer = ' . wp_json_encode([
        'maxSlots'    => OMOCHIX_LEARN_COMPOSER_MAX_SLOTS,
        'hashKey'     => OMOCHIX_LEARN_COMPOSER_HASH_KEY,
        'hashVersion' => OMOCHIX_LEARN_COMPOSER_HASH_VERSION,
        'noteMax'     => OMOCHIX_LEARN_COMPOSER_NOTE_MAX,
        'presets'     => omochix_learn_composer_presets(),
        'strings'     => [
            'slotEmpty'   => __('見本を選ぶ', 'omochix'),
            'linkCopied'  => __('リンクをコピーしました', 'omochix'),
            'copyFailed'  => __('コピーできませんでした', 'omochix'),
            'full'        => __('3件まで選べます。どれかを外してから追加してください。', 'omochix'),
            'added'       => __('%sを追加しました', 'omochix'),
            'removed'     => __('%sを外しました', 'omochix'),
            'moved'       => __('%sを%d番目に移動しました', 'omochix'),
            'cleared'     => __('すべて外しました', 'omochix'),
            'restored'    => __('前回の編集状態を復元しました', 'omochix'),
            'adjust'      => __('調整する', 'omochix'),
            'adjustClose' => __('調整を閉じる', 'omochix'),
            'adjusted'    => __('%d項目を調整中', 'omochix'),
            'panelFailed' => __('調整パネルを読み込めませんでした。', 'omochix'),
            'retry'       => __('もう一度読み込む', 'omochix'),
        ],
        'panelParam'  => OMOCHIX_LEARN_COMPOSER_PANEL_VAR,
        'partialParam' => OMOCHIX_LEARN_GALLERY_PARTIAL_VAR,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'omochix_learn_composer_enqueue_assets', 21);

/* -------------------------------------------------------------------------
 * SEO: the composer is a tool page whose state lives in the hash; keep the
 * single page indexable-neutral (noindex, follow) so no state URL competes
 * with the gallery.
 * ---------------------------------------------------------------------- */

add_filter('wp_robots', static function ($robots) {
    if (omochix_learn_composer_is_request()) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }
    return $robots;
});
add_filter('slim_seo_robots_index', static function ($indexed) {
    return omochix_learn_composer_is_request() ? false : $indexed;
});
