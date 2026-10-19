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
const OMOCHIX_LEARN_COMPOSER_REWRITE_VERSION = '2';
const OMOCHIX_LEARN_COMPOSER_MAX_SLOTS       = 3;
const OMOCHIX_LEARN_COMPOSER_HASH_KEY        = 'c';
const OMOCHIX_LEARN_COMPOSER_HASH_VERSION    = '1';
const OMOCHIX_LEARN_COMPOSER_NOTE_MAX        = 40;
const OMOCHIX_LEARN_COMPOSER_NOTE_RE         = '/^[\p{L}\p{N} 　・、。，．ー\-_()（）!?！？:：.,\/\'"&]{0,40}$/u';
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
 * Registered before the gallery's rule (init priority 10): WordPress keeps
 * "top" rules in registration order, so this one is matched first and
 * "compose" is never taken for a sample slug.
 */
function omochix_learn_composer_register_rewrite_rules() {
    add_rewrite_rule(
        '^' . preg_quote(OMOCHIX_LEARN_COMPOSER_PATH, '#') . '/?$',
        'index.php?pagename=' . OMOCHIX_LEARN_COMPOSER_PATH,
        'top'
    );
}
add_action('init', 'omochix_learn_composer_register_rewrite_rules', 9);

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
    get_template_part('template-parts/learn-gallery/playground', null, ['item' => $item, 'heading' => 'h3', 'share' => false, 'host' => true]);
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
    $seen  = [];
    foreach ($slots as $slot) {
        if (count($out['slots']) >= OMOCHIX_LEARN_COMPOSER_MAX_SLOTS) {
            break;
        }
        if (!is_array($slot)) {
            continue;
        }
        $slug = isset($slot['slug']) && is_string($slot['slug']) ? sanitize_title($slot['slug']) : '';
        $item = '' !== $slug ? omochix_learn_gallery_get_item($slug) : null;
        if (!$item || isset($seen[$slug])) {
            continue; // unknown or repeated samples are dropped
        }
        $seen[$slug] = true;
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

/** The per-slot omx shim used inside the combined document (plain JS, no WordPress): root is the slot's section. */
function omochix_learn_composer_slot_shim() {
    return 'function omxSlot(n,p){var l=[];var r=document.querySelector(\'.omx-slot[data-slot="\'+n+\'"]\')||document;return{root:r,reduced:!!window.omx.reduced,animate:function(f){window.omx.animate(f)},param:function(k,d){return Object.prototype.hasOwnProperty.call(p,k)?p[k]:d},onParams:function(f){l.push(f)}}}';
}

/**
 * The per-slot omx shim the brief ships with every JavaScript sample. Its
 * root is the sample's own wrapper (the nearest `.omx-motion-n` before the
 * script), so the same sample can be pasted twice on one page.
 */
function omochix_learn_composer_brief_shim() {
    return <<<'JS'
function omxSlot(n, p) {
  // この見本の要素：この script の直前にある .omx-motion-n（同じ見本を 2 回置いても別々に動く）
  var root = document.currentScript ? document.currentScript.previousElementSibling : null;
  while (root && !(root.classList && root.classList.contains('omx-motion-' + n))) root = root.previousElementSibling;
  if (!root) { var all = document.querySelectorAll('.omx-motion-' + n); root = all[all.length - 1] || document; }
  return {
    root: root,
    reduced: window.omx.reduced,
    animate: function (fn) { window.omx.animate(fn); },
    param: function (key, fallback) { return Object.prototype.hasOwnProperty.call(p, key) ? p[key] : fallback; },
    onParams: function () {}
  };
}
JS;
}

/**
 * Design tokens the sample CSS refers to (var(--primary) etc.), with the
 * values of the preview runtime's :root (assets/css/learn-preview-runtime.css),
 * so the brief's code renders outside the theme. Order and values are
 * mirrored in learn-composer-core.js (TOKENS); tests/learn-composer/run.php
 * checks them against the runtime stylesheet.
 *
 * @return array<string, string>
 */
function omochix_learn_composer_tokens() {
    return [
        '--bg'      => '#f7f7f5',
        '--surface' => '#ffffff',
        '--text'    => '#101010',
        '--muted'   => '#666663',
        '--line'    => 'rgba(16, 16, 16, 0.12)',
        '--primary' => '#6e4bff',
        '--blue'    => '#2563eb',
        '--amber'   => '#d97706',
    ];
}

/** ".omx-motion-n{--bg:…;…;--amber:…}" */
function omochix_learn_composer_token_block($n) {
    $pairs = [];
    foreach (omochix_learn_composer_tokens() as $name => $value) {
        $pairs[] = $name . ':' . $value;
    }
    return '.omx-motion-' . (int) $n . '{' . implode(';', $pairs) . '}';
}

/**
 * The sample's HTML with every `data-<key>` attribute of a JS parameter set
 * to the current value (e.g. count-up's data-target), so the markup an AI
 * reads agrees with the omxSlot() values.
 */
function omochix_learn_composer_brief_html(array $item, array $values) {
    $html = $item['preview']['html'];
    foreach ($item['params'] as $def) {
        if ('js' !== $def['target']) {
            continue;
        }
        $v    = $values[$def['key']] ?? $def['default'];
        $text = 'range' === $def['type'] ? omochix_learn_gallery_format_number($v) : (string) $v;
        $html = preg_replace('/\bdata-' . preg_quote($def['key'], '/') . '="[^"]*"/', 'data-' . $def['key'] . '="' . $text . '"', $html, 1);
    }
    return $html;
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
 * Implementation brief (Markdown for Claude Code / Codex). PHP is the
 * reference; learn-composer-core.js fills the same templates so the text
 * the visitor copies is byte-identical on both sides (parity test).
 * ---------------------------------------------------------------------- */

/** A note as it may appear in Markdown: plain already, but never a list/heading/quote marker at line start. */
function omochix_learn_composer_brief_note($note) {
    $note = omochix_learn_composer_sanitize_note($note);
    return preg_match('/^[#\-*>|+]/', $note) ? '　' . $note : $note;
}

/** Preset label for a note that is a preset key, else the note itself. */
function omochix_learn_composer_note_label($kind, $note) {
    $presets = omochix_learn_composer_presets();
    return $presets[$kind][$note] ?? $note;
}

/** Opening line per assistant; the rest of the brief is shared. */
function omochix_learn_composer_assistants() {
    return [
        'claude-code' => ['label' => 'Claude Code', 'line' => __('このファイルの指示に従って実装してください。分からない点は実装前に質問してください。', 'omochix')],
        'codex'       => ['label' => 'Codex', 'line' => __('以下の指示に従って実装してください。前提が足りない場合は推測せず、質問してください。', 'omochix')],
    ];
}

function omochix_learn_composer_brief_templates() {
    return [
        'header' => implode("\n", [
            '# ' . __('モーション実装指示（OmochiX Learn Motion Composer）', 'omochix'),
            '{{assistant_line}}',
            '',
            '## ' . __('概要', 'omochix'),
            '- ' . __('見本: {{count}}件（上から順に）', 'omochix'),
            '{{list}}',
            '- ' . __('再生: 同時に（各見本は自分の周期で繰り返す）', 'omochix'),
            '',
        ]),
        'list_item' => '  - #{{id}} {{name}}（{{en}}） {{url}}',
        'slot' => implode("\n", [
            '## {{n}}. {{name}}（{{en}}）',
            '- ' . __('見本: #{{id}}（{{category}}） {{url}}', 'omochix'),
            '- ' . __('適用対象: {{target}} / 意図: {{intent}}', 'omochix'),
            '- ' . __('どんな動きか: {{summary}}', 'omochix'),
            '- ' . __('動きの仕様:', 'omochix'),
            '{{spec}}',
            '{{params_text}}',
            '- ' . __('使いどころ: {{use}}', 'omochix'),
            '- ' . __('実装コード（{{tech}}。クラス名と変数は見本のまま。`.omx-motion-{{n}}` の中だけに効く）:', 'omochix'),
            '',
            '```html',
            '{{code}}',
            '```',
            '',
        ]),
        'footer' => implode("\n", [
            '## ' . __('全体の実装条件', 'omochix'),
            '- ' . __('HTML / CSS（必要なら素の JavaScript）で、外部ライブラリは使わない', 'omochix'),
            '- ' . __('見本の形・タイミング・イージングの数値はそのまま使い、文字・色・素材だけ今作っているものに合わせる', 'omochix'),
            '- ' . __('各見本は `.omx-motion-n` で包み、変数（--p-*）はその要素に置く。別の見本のクラス名や keyframes 名を変えない', 'omochix'),
            '- ' . __('prefers-reduced-motion: reduce の環境では動きを止めるか最小限にする（例: `@media (prefers-reduced-motion: reduce) { .omx-motion * { animation: none !important; transition: none !important; } }`）', 'omochix'),
            '- ' . __('性能: transform と opacity だけを動かす。will-change は実際に動かす要素だけ。同時に動く要素は 3 つまで。JavaScript は requestAnimationFrame を 1 本にまとめる', 'omochix'),
            '',
            '## ' . __('受け入れ条件', 'omochix'),
            '- ' . __('各見本が上の順番で、指定した長さ・値で再生される', 'omochix'),
            '- ' . __('reduced-motion を有効にすると動きが止まる', 'omochix'),
            '- ' . __('レイアウトシフト（CLS）が増えない', 'omochix'),
        ]),
        'note_empty' => __('（未指定）', 'omochix'),
    ];
}

/**
 * The copyable implementation code of one motion, scoped with a wrapper
 * class instead of :root: the tokens the sample CSS uses, the non-default
 * parameters, the sample CSS, the HTML (data-* attributes at the current
 * values) and, for JS samples, the runtime shim + per-slot shim + sample.
 */
function omochix_learn_composer_brief_code(array $item, array $values, $n, $url = '') {
    $values = omochix_learn_gallery_sanitize_params($item, $values);
    $block  = omochix_learn_gallery_params_blocks($item, $values);
    $parts  = [];
    $parts[] = sprintf('<!-- %s (%s) | OmochiX Learn 動く見本 #%s%s -->', $item['name']['ja'], $item['name']['en'], $item['id'], $url ? ' | ' . $url : '');
    $scoped = '' !== $block['css'] ? '.omx-motion-' . (int) $n . '{' . substr($block['css'], strlen(':root{')) . "\n" : '';
    $parts[] = "<style>\n" . '/* ' . __('見本が参照する色。自分のテーマの色に置き換えてよい', 'omochix') . ' */' . "\n" . omochix_learn_composer_token_block($n) . "\n" . $scoped . $item['preview']['css'] . "\n</style>";
    $parts[] = '<div class="omx-motion omx-motion-' . (int) $n . '">' . "\n" . omochix_learn_composer_brief_html($item, $values) . "\n" . '</div>';
    if ('' !== $item['preview']['js']) {
        $parts[] = "<script>\n" . omochix_learn_gallery_js_shim() . "\n" . omochix_learn_composer_brief_shim() . "\n"
            . '(function(omx){' . "\n" . $item['preview']['js'] . "\n" . '}(omxSlot(' . (int) $n . ',' . omochix_learn_composer_js_params($item, $values) . ')));'
            . "\n</script>";
    }
    return implode("\n\n", $parts);
}

/** Per-sample facts the brief needs beyond the client catalog JSON (printed on the composer page as #omx-composer-data). */
function omochix_learn_composer_item_data(array $item) {
    $category = omochix_learn_gallery_get_category($item['category']);
    return [
        'id'          => $item['id'],
        'name'        => $item['name'],
        'category'    => $category ? $category['name'] : $item['category'],
        'summary'     => $item['summary'],
        'description' => $item['description'],
        'spec'        => $item['prompt']['spec'],
        'use'         => $item['prompt']['use'],
        'tech'        => implode(' / ', array_map(static function ($tech) {
            return omochix_learn_gallery_techs()[$tech] ?? $tech;
        }, $item['tech'])),
        'url'         => omochix_learn_gallery_url($item),
    ];
}

function omochix_learn_composer_data_json() {
    $out = ['items' => [], 'shim' => omochix_learn_gallery_js_shim(), 'slotShim' => omochix_learn_composer_brief_shim()];
    foreach (omochix_learn_gallery_get_items() as $slug => $item) {
        $out['items'][$slug] = omochix_learn_composer_item_data($item);
    }
    return (string) wp_json_encode($out, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Fill {{placeholders}}; unknown ones are left as they are (same rule as the gallery). */
function omochix_learn_composer_fill($template, array $map) {
    return strtr($template, $map);
}

/**
 * The whole brief as Markdown for a (raw or sanitized) composition.
 *
 * @param mixed  $composition
 * @param string $assistant 'claude-code' | 'codex'
 */
function omochix_learn_composer_brief($composition, $assistant = 'claude-code') {
    $comp       = omochix_learn_composer_sanitize($composition);
    $t          = omochix_learn_composer_brief_templates();
    $assistants = omochix_learn_composer_assistants();
    $assistant  = isset($assistants[$assistant]) ? $assistant : 'claude-code';
    if (!$comp['slots']) {
        return '';
    }
    $list  = [];
    $slots = [];
    $n     = 0;
    foreach ($comp['slots'] as $slot) {
        $n++;
        $item   = omochix_learn_gallery_get_item($slot['slug']);
        $data   = omochix_learn_composer_item_data($item);
        $values = omochix_learn_gallery_sanitize_params($item, $slot['params']);
        $list[] = omochix_learn_composer_fill($t['list_item'], ['{{id}}' => $data['id'], '{{name}}' => $data['name']['ja'], '{{en}}' => $data['name']['en'], '{{url}}' => $data['url']]);
        $spec   = implode("\n", array_map(static fn($line) => '  - ' . $line, $data['spec']));
        $params = $item['params'] ? '- ' . rtrim(str_replace("\n- ", "\n  - ", omochix_learn_gallery_params_text($item, $values))) : '- ' . __('調整した値: なし', 'omochix');
        $slots[] = omochix_learn_composer_fill($t['slot'], [
            '{{n}}'           => (string) $n,
            '{{name}}'        => $data['name']['ja'],
            '{{en}}'          => $data['name']['en'],
            '{{id}}'          => $data['id'],
            '{{category}}'    => $data['category'],
            '{{url}}'         => $data['url'],
            '{{target}}'      => '' !== $slot['target'] ? omochix_learn_composer_brief_note(omochix_learn_composer_note_label('target', $slot['target'])) : $t['note_empty'],
            '{{intent}}'      => '' !== $slot['intent'] ? omochix_learn_composer_brief_note(omochix_learn_composer_note_label('intent', $slot['intent'])) : $t['note_empty'],
            '{{summary}}'     => $data['summary'],
            '{{spec}}'        => $spec,
            '{{params_text}}' => $params,
            '{{use}}'         => $data['use'],
            '{{tech}}'        => $data['tech'],
            '{{code}}'        => omochix_learn_composer_brief_code($item, $values, $n, $data['url']),
        ]);
    }
    $header = omochix_learn_composer_fill($t['header'], [
        '{{assistant_line}}' => $assistants[$assistant]['line'],
        '{{count}}'          => (string) count($comp['slots']),
        '{{list}}'           => implode("\n", $list),
    ]);
    return $header . "\n" . implode("\n", $slots) . "\n" . $t['footer'] . "\n";
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
            'notes'       => __('用途', 'omochix'),
            'notesClose'  => __('用途を閉じる', 'omochix'),
            'custom'      => __('自由入力…', 'omochix'),
            'noteEmpty'   => __('（未指定）', 'omochix'),
            'briefCopied' => __('指示書をコピーしました', 'omochix'),
            'jsonCopied'  => __('JSONをコピーしました', 'omochix'),
            'jsonInvalid' => __('読み込めるJSONではありません。', 'omochix'),
            'jsonLoaded'  => __('JSONから読み込みました', 'omochix'),
            'noParams'    => __('調整した値: なし', 'omochix'),
            'linkTooLong' => __('リンクが長すぎます。JSON でコピーしてください。', 'omochix'),
            'noteInvalid' => __('使えない文字があります。文字・数字・空白と . , / \' " & ( ) ! ? : - _ 、40文字まで。', 'omochix'),
            'pickerCount' => __('%d件の見本', 'omochix'),
            'moveUp'      => __('%sをひとつ前へ', 'omochix'),
            'moveDown'    => __('%sをひとつ後ろへ', 'omochix'),
            'removeOne'   => __('%sを外す', 'omochix'),
        ],
        'brief'       => omochix_learn_composer_brief_templates(),
        'assistants'  => omochix_learn_composer_assistants(),
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

/** Title as shown in the tab / meta title: "<page title> | Learn". */
function omochix_learn_composer_title() {
    return omochix_learn_composer_is_request() ? sprintf(__('%s | Learn', 'omochix'), get_the_title()) : '';
}
add_filter('document_title_parts', static function ($parts) {
    $title = omochix_learn_composer_title();
    if ($title) {
        $parts['title'] = $title;
    }
    return $parts;
});
add_filter('slim_seo_meta_title', static function ($title) {
    $own = omochix_learn_composer_title();
    return $own ? $own . ' | ' . get_bloginfo('name') : $title;
});
add_filter('slim_seo_meta_description', static function ($description) {
    if (!omochix_learn_composer_is_request() || has_excerpt()) {
        return $description;
    }
    return __('動く見本を3つまで選んで順番と値を決め、ひとつの流れとして確かめられます。組み合わせはリンクで共有でき、AIへの実装指示書としてコピーできます。', 'omochix');
});
add_filter('slim_seo_canonical_url', static function ($url) {
    return omochix_learn_composer_is_request() ? (omochix_learn_composer_url() ?: $url) : $url;
});
