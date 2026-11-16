<?php
/**
 * OmochiX Logo Assembly Loader - WordPress integration sample (v1.1.0)
 *
 * NOT wired into the theme. To try it locally, copy to wp-theme/omochix-theme/inc/ and see
 * docs/IMPLEMENTATION.md. Nothing here touches the database or production settings.
 *
 * Public API
 *   omochix_loader( $args )         echo the loader markup (server-rendered, animates with CSS only)
 *   omochix_get_loader( $args )     return it as a string
 *   omochix_loader_enqueue()        enqueue CSS (+ tiny JS) - called automatically when needed
 *
 * Args: variant (full|chat|micro), theme (light|dark|auto), label, done_label, size (px int or CSS length),
 *       wordmark (bool, full only), show_label (bool), announce (bool), class (string)
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/omochix-loader-markup.php';

define('OMOCHIX_LOADER_VERSION', '1.1.0');

/** Asset directory (relative to the theme root). */
function omochix_loader_asset_uri($file) {
    return get_theme_file_uri('/assets/loader/' . $file);
}

function omochix_loader_enqueue() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    wp_enqueue_style('omochix-loader', omochix_loader_asset_uri('omochix-loader.css'), [], OMOCHIX_LOADER_VERSION);
    // JS is only needed to (a) finish on the completed logo and (b) pause off-screen. Pure-CSS looping works without it.
    wp_enqueue_script(
        'omochix-loader',
        omochix_loader_asset_uri('omochix-loader.js'),
        [],
        OMOCHIX_LOADER_VERSION,
        ['in_footer' => true, 'strategy' => 'defer']
    );
}

function omochix_get_loader($args = []) {
    $a = wp_parse_args($args, [
        'variant'    => 'full',
        'theme'      => 'auto',
        'label'      => '読み込み中',
        'done_label' => '読み込みが完了しました',
        'size'       => null,
        'wordmark'   => null,
        'show_label' => false,
        'announce'   => null,
        'class'      => '',
    ]);

    $variant = in_array($a['variant'], ['full', 'chat', 'micro'], true) ? $a['variant'] : 'full';
    $theme   = in_array($a['theme'], ['light', 'dark', 'auto'], true) ? $a['theme'] : 'auto';
    $wordmark = null === $a['wordmark'] ? 'full' === $variant : (bool) $a['wordmark'];
    $announce = null === $a['announce'] ? 'micro' !== $variant : (bool) $a['announce'];
    $uid = wp_unique_id('omx'); // unique per instance: gradient ids must not collide

    omochix_loader_enqueue();

    $style = '';
    if (null !== $a['size']) {
        $size = is_numeric($a['size']) ? absint($a['size']) . 'px' : preg_replace('/[^0-9a-z.%()+*\/ -]/i', '', (string) $a['size']);
        $style = ' style="--omx-size:' . esc_attr($size) . '"';
    }

    $cls = 'omx omx--' . $variant . ('' !== $a['class'] ? ' ' . $a['class'] : '');
    $aria = $announce ? ' role="status" aria-live="polite"' : ' aria-hidden="true"';
    $art = ($wordmark && 'full' === $variant) ? 'lockup' : 'mark';

    $html  = '<div class="' . esc_attr($cls) . '" data-omx-loader data-theme="' . esc_attr($theme) . '" data-state="loading"';
    $html .= ' data-done-label="' . esc_attr($a['done_label']) . '" aria-busy="true"' . $aria . $style . '>';
    $html .= '<span class="omx-sr">' . esc_html($a['label']) . '</span>';
    // Static, trusted SVG generated from the brand files (no user input).
    $html .= '<span class="omx-stage">' . omochix_loader_svg_markup($art, $uid) . '</span>';
    if ($a['show_label']) {
        $html .= '<span class="omx-label" aria-hidden="true">' . esc_html($a['label']) . '</span>';
    }
    $html .= '</div>';

    return $html;
}

function omochix_loader($args = []) {
    echo omochix_get_loader($args); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above, SVG is static.
}

/* ------------------------------------------------------------------------------------------------
 * Optional: full-page loading overlay. OFF by default.
 * Enable with:  add_filter('omochix_loader_page_overlay', '__return_true');
 * - Overlay is only shown when JS is available (html.omx-js), so it can never block a no-JS visit.
 * - Shown on the first page view of a browser session only; invisible for the first 250ms so fast loads never flash it.
 * - It ends on the real DOMContentLoaded + fonts-ready state (not window load), then fades. A 10s failsafe timer removes it so it can never get stuck.
 * ---------------------------------------------------------------------------------------------- */
function omochix_loader_page_overlay_enabled() {
    return (bool) apply_filters('omochix_loader_page_overlay', false) && !is_admin() && !wp_doing_ajax();
}

add_action('wp_head', function () {
    if (!omochix_loader_page_overlay_enabled()) {
        return;
    }
    // Marks "JS available" as early as possible (tiny, no dependencies).
    // First visit per browser session only (sessionStorage), so repeat page views never pay for the overlay.
    // Timers are JS (not CSS) so theme-level reduced-motion resets cannot break them: show after 250ms, hard failsafe at 10s.
    echo "<script>try{if(!sessionStorage.getItem('omxSeen')){var h=document.documentElement;h.classList.add('omx-js');setTimeout(function(){h.classList.add('omx-show')},250);setTimeout(function(){h.classList.remove('omx-js','omx-show')},10000);}}catch(e){}</script>
";
}, 1);

add_action('wp_body_open', function () {
    if (!omochix_loader_page_overlay_enabled()) {
        return;
    }
    echo '<div class="omx-overlay" data-omx-overlay>';
    omochix_loader(['variant' => 'full', 'wordmark' => true, 'size' => 240, 'label' => 'ページを読み込んでいます']);
    echo '</div>';
});

add_action('wp_enqueue_scripts', function () {
    if (!omochix_loader_page_overlay_enabled()) {
        return;
    }
    omochix_loader_enqueue();
    wp_enqueue_script(
        'omochix-loader-page',
        omochix_loader_asset_uri('omochix-loader-page.js'),
        ['omochix-loader'],
        OMOCHIX_LOADER_VERSION,
        ['in_footer' => true, 'strategy' => 'defer']
    );
});
