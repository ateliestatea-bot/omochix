<?php
/**
 * Design Library — isolated demo endpoint "/design/demo/{kind}/{slug}/".
 *
 * Rules (docs/design-library/Design-Library-D0-Architecture.md §3.3):
 *  - Every response (200, 404, 503) carries an ENFORCED Content-Security-Policy
 *    with "sandbox allow-scripts" (no allow-same-origin / top-navigation /
 *    forms / popups / modals), so the demo always runs in an opaque origin,
 *    also when the URL is opened directly. There is no report-only or off mode.
 *  - Fail-closed: when the headers cannot be sent, or the policy is not in
 *    headers_list() afterwards, no demo body is returned (503, empty).
 *  - The document is assembled here from demo.php (ABSPATH-guarded markup),
 *    demo.css and demo.js of the item; scripts are allowed by their SHA-256
 *    only. No request may leave the document (connect-src 'none').
 *  - Only the demo's own Web works (medium "web") have this endpoint.
 *  - The response does not depend on who is logged in (no admin bar, no
 *    nonce, no user data): the request exits before any template runs.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Sandbox tokens. Fixed here on purpose: no constant or filter may widen them (D0 §3.8 R6). */
const OMOCHIX_DESIGN_DEMO_SANDBOX = 'sandbox allow-scripts';

/** scheme://host[:port] of the site, for the demo runtime's postMessage checks. */
function omochix_design_site_origin() {
    $parts = wp_parse_url(home_url('/'));
    $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    return isset($parts['port']) ? $origin . ':' . $parts['port'] : $origin;
}

/**
 * The enforced policy. Hosts are explicit (no 'self': its meaning inside a
 * sandboxed document has differed between browsers).
 *
 * @param string[] $script_hashes base64 SHA-256 of each inline script.
 */
function omochix_design_demo_csp(array $script_hashes) {
    $assets = omochix_design_assets_uri() . '/';
    $scripts = $script_hashes ? implode(' ', array_map(static function ($h) { return "'sha256-" . $h . "'"; }, $script_hashes)) : "'none'";
    return implode('; ', [
        OMOCHIX_DESIGN_DEMO_SANDBOX,
        "default-src 'none'",
        'script-src ' . $scripts,
        "style-src 'unsafe-inline'",
        'img-src ' . $assets . ' data: blob:',
        'font-src data:',
        'media-src ' . $assets,
        "connect-src 'none'",
        "worker-src 'none'",
        "frame-src 'none'",
        "object-src 'none'",
        "manifest-src 'none'",
        "frame-ancestors 'self'",
        "base-uri 'none'",
        "form-action 'none'",
    ]);
}

/** @return array<string, string> */
function omochix_design_demo_headers(array $script_hashes, $status) {
    return [
        'Content-Security-Policy' => omochix_design_demo_csp($script_hashes),
        'Content-Type'            => 'text/html; charset=UTF-8',
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'SAMEORIGIN',
        'Referrer-Policy'         => 'no-referrer',
        'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=(), hid=(), display-capture=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cache-Control'           => 200 === $status ? 'public, max-age=300' : 'no-store',
        'X-Robots-Tag'            => 'noindex, nofollow',
    ];
}

/**
 * The demo's own sources: rendered markup of demo.php, demo.css, demo.js
 * (without the shared runtime). Used for the document below and, as text
 * only, for the "code" section of the detail page.
 *
 * @return array{html: string, css: string, js: string}
 */
function omochix_design_demo_sources(array $item) {
    $dir  = omochix_design_demo_dir($item);
    $file = $dir . '/demo.php';
    $html = '';
    if (is_file($file)) {
        $demo = [
            'assets' => omochix_design_assets_uri(),
            'poster' => omochix_design_asset_url($item['posterFallback'] ?? $item['poster']),
            'item'   => $item,
        ];
        ob_start();
        (static function ($omx_demo, $omx_file) {
            include $omx_file;
        })($demo, $file);
        $html = trim((string) ob_get_clean());
    }
    return [
        'html' => $html,
        'css'  => is_file($dir . '/demo.css') ? (string) file_get_contents($dir . '/demo.css') : '',
        'js'   => is_file($dir . '/demo.js') ? (string) file_get_contents($dir . '/demo.js') : '',
    ];
}

/**
 * Build the demo document. Returns null when the sources are missing or
 * could break out of their element.
 *
 * @return array{html: string, hashes: string[]}|null
 */
function omochix_design_demo_document(array $item) {
    $dir  = omochix_design_demo_dir($item);
    $file = $dir . '/demo.php';
    $runtime_file = omochix_design_assets_dir() . '/runtime/demo-runtime.js';
    if (!is_file($file) || !is_file($runtime_file)) {
        return null;
    }
    $src     = omochix_design_demo_sources($item);
    $markup  = $src['html'];
    $css     = $src['css'];
    $js      = $src['js'];
    $runtime = str_replace('"__OMX_SITE_ORIGIN__"', (string) wp_json_encode(omochix_design_site_origin(), JSON_UNESCAPED_SLASHES), (string) file_get_contents($runtime_file));
    foreach ([$css, $js, $runtime] as $part) {
        if (false !== stripos($part, '</style') || false !== stripos($part, '</script') || false !== stripos($part, '<!--')) {
            return null;
        }
    }
    $scripts = array_values(array_filter([$runtime, $js], 'strlen'));
    $hashes  = array_map(static function ($s) { return base64_encode(hash('sha256', $s, true)); }, $scripts);
    $html  = '<!doctype html><html lang="ja" data-theme="light"><head><meta charset="utf-8">';
    $html .= '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><link rel="icon" href="data:,">';
    $html .= '<title>' . esc_html($item['title']) . ' — ' . esc_html__('デザインの見本', 'omochix') . '</title>';
    $html .= '<style>' . $css . '</style></head><body>' . $markup;
    foreach ($scripts as $s) {
        $html .= '<script>' . $s . '</script>';
    }
    $html .= '</body></html>';
    return ['html' => $html, 'hashes' => $hashes];
}

/**
 * Headers WordPress or plugins may have queued that must not reach a demo
 * response (cookies, links, caching, competing policies). Anything else —
 * HSTS from a security plugin, for example — is kept.
 */
function omochix_design_demo_strip_headers() {
    foreach (['Set-Cookie', 'Link', 'Cache-Control', 'Expires', 'Pragma', 'Last-Modified', 'ETag', 'X-Pingback', 'Content-Security-Policy',
        'Content-Security-Policy-Report-Only', 'X-Frame-Options', 'Content-Type', 'Access-Control-Allow-Origin', 'Access-Control-Allow-Credentials',
        'Refresh', 'Location', 'X-Robots-Tag', 'Referrer-Policy', 'Permissions-Policy', 'X-Content-Type-Options', 'Cross-Origin-Opener-Policy'] as $name) {
        header_remove($name);
    }
}

/**
 * Send the headers and confirm the enforced policy is really queued.
 * Test hook "omochix_design_demo_headers_blocked" can only make this fail.
 */
function omochix_design_demo_send_headers(array $hashes, $status) {
    if (headers_sent() || apply_filters('omochix_design_demo_headers_blocked', false)) {
        return false;
    }
    omochix_design_demo_strip_headers();
    status_header($status);
    $headers = omochix_design_demo_headers($hashes, $status);
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value, true);
    }
    $expected = 'content-security-policy: ' . strtolower($headers['Content-Security-Policy']);
    foreach (headers_list() as $line) {
        if (strtolower($line) === $expected) {
            return true;
        }
    }
    return false;
}

/** Empty 503 with the enforced policy when possible (never the demo body). */
function omochix_design_demo_fail_closed() {
    if (!headers_sent()) {
        omochix_design_demo_strip_headers();
        status_header(503);
        foreach (omochix_design_demo_headers([], 503) as $name => $value) {
            header($name . ': ' . $value, true);
        }
        header('Retry-After: 300');
    }
    exit;
}

function omochix_design_demo_handle_request() {
    if (!omochix_design_is_demo_request()) {
        return;
    }
    $medium = omochix_design_requested_medium();
    $slug   = omochix_design_requested_slug();
    $item   = ('web' === $medium && '' !== $slug) ? omochix_design_get_item('web', $slug) : null;
    $doc  = ($item && omochix_design_demo_allowed($item)) ? omochix_design_demo_document($item) : null;
    if (null === $doc) {
        if (!omochix_design_demo_send_headers([], 404)) {
            omochix_design_demo_fail_closed();
        }
        exit;
    }
    if (!omochix_design_demo_send_headers($doc['hashes'], 200)) {
        omochix_design_demo_fail_closed();
    }
    if ('HEAD' !== ($_SERVER['REQUEST_METHOD'] ?? 'GET')) {
        echo $doc['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- reviewed theme demo sources, assembled above.
    }
    exit;
}
add_action('template_redirect', 'omochix_design_demo_handle_request', 0);
