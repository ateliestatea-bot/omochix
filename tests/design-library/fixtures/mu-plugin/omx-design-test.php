<?php
/**
 * Plugin Name: OmochiX Design Library — local isolation test hooks
 * Description: For the THROWAWAY local QA site only. Never install on staging or production.
 *
 * Copy (or symlink) into wp-content/mu-plugins/ of the local site. Does
 * nothing unless WP_ENVIRONMENT_TYPE is "local" and the site runs on
 * 127.0.0.1. Then:
 *  - while the flag file "<site>/design-isolation-test.on" exists, the
 *    catalog gets the isolation probe item (tests/design-library/fixtures/
 *    isolation-probe/) and every item is treated as "verified-local", so the
 *    demos can be served to the isolation tests before they have passed;
 *  - while "<site>/design-isolation-unverified.on" exists, every item is
 *    treated as "unverified" (the demo gate must then return 404);
 *  - a request with "X-OMX-Test-Block-Headers: 1" makes the demo endpoint
 *    believe it cannot send headers (B9 fail-closed check);
 *  - a request with "X-OMX-Test-Hsts: 1" gets an HSTS header queued early, to
 *    check the demo endpoint keeps headers it does not own;
 *  - a request with "X-OMX-Test-Schema: 1" prints, in <head>, what Slim SEO
 *    would print from its schema graph: a minimal WebPage + BreadcrumbList
 *    passed through the real "slim_seo_schema_graph" filter (Slim SEO itself
 *    is not installed on the throwaway site — EMULATION, not Slim SEO).
 * The hooks it uses can only add the probe or make the endpoint fail; none
 * can widen the demo CSP (that string has no filter).
 */
if (!defined('ABSPATH')) {
    exit;
}
if ('local' !== wp_get_environment_type() || 0 !== strpos((string) home_url('/'), 'http://127.0.0.1:')) {
    return;
}

define('OMX_DESIGN_TEST_FIXTURES', dirname(__DIR__)); // __DIR__ is the real path even when this file is symlinked into mu-plugins
define('OMX_DESIGN_TEST_FLAG', dirname(ABSPATH) . '/design-isolation-test.on');
define('OMX_DESIGN_UNVERIFIED_FLAG', dirname(ABSPATH) . '/design-isolation-unverified.on');

add_filter('omochix_design_catalog_data', static function ($data) {
    if (!is_file(OMX_DESIGN_UNVERIFIED_FLAG) || !is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        return $data;
    }
    foreach ($data['items'] as $i => $item) {
        if (is_array($item) && isset($item['verification']) && is_array($item['verification'])) {
            $data['items'][$i]['verification']['state'] = 'unverified';
        }
    }
    return $data;
}, 20);

add_filter('omochix_design_catalog_data', static function ($data) {
    if (!is_file(OMX_DESIGN_TEST_FLAG) || !is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        return $data;
    }
    foreach ($data['items'] as $i => $item) {
        if (is_array($item) && isset($item['verification']) && is_array($item['verification'])) {
            $data['items'][$i]['verification']['state'] = 'verified-local';
            $data['items'][$i]['verification']['checked'] = $data['items'][$i]['verification']['checked'] ?? '2026-11-16';
            $data['items'][$i]['verification']['environments'] = $data['items'][$i]['verification']['environments'] ?: ['chromium'];
        }
    }
    $probe = json_decode((string) file_get_contents(OMX_DESIGN_TEST_FIXTURES . '/isolation-probe/item.json'), true);
    if (is_array($probe)) {
        $data['items'][] = $probe;
    }
    return $data;
});

// The object-decoded copy (used for the {} / [] check) gets the same edits, item for item.
add_filter('omochix_design_catalog_raw', static function ($raw, $data) {
    unset($data);
    if (!$raw instanceof stdClass || !isset($raw->items) || !is_array($raw->items)) {
        return $raw;
    }
    if (is_file(OMX_DESIGN_TEST_FLAG)) {
        foreach ($raw->items as $item) {
            if ($item instanceof stdClass && isset($item->verification) && $item->verification instanceof stdClass) {
                $item->verification->state = 'verified-local';
                $item->verification->checked = $item->verification->checked ?? '2026-11-16';
                $item->verification->environments = $item->verification->environments ?: ['chromium'];
            }
        }
        $probe = json_decode((string) file_get_contents(OMX_DESIGN_TEST_FIXTURES . '/isolation-probe/item.json'));
        if ($probe instanceof stdClass) {
            $raw->items[] = $probe;
        }
    }
    if (is_file(OMX_DESIGN_UNVERIFIED_FLAG)) {
        foreach ($raw->items as $item) {
            if ($item instanceof stdClass && isset($item->verification) && $item->verification instanceof stdClass) {
                $item->verification->state = 'unverified';
            }
        }
    }
    return $raw;
}, 10, 2);

add_filter('omochix_design_asset_file', static function ($file, $path) {
    $map = [
        'posters/isolation-probe.avif'    => OMX_DESIGN_TEST_FIXTURES . '/isolation-probe/poster.avif',
        'demos/isolation-probe/demo.php'  => OMX_DESIGN_TEST_FIXTURES . '/isolation-probe/demo.php',
    ];
    return $map[$path] ?? $file;
}, 10, 2);

add_filter('omochix_design_demo_headers_blocked', static function ($blocked) {
    return $blocked || '1' === ($_SERVER['HTTP_X_OMX_TEST_BLOCK_HEADERS'] ?? '');
});

add_action('send_headers', static function () {
    if ('1' === ($_SERVER['HTTP_X_OMX_TEST_HSTS'] ?? '')) {
        header('Strict-Transport-Security: max-age=60');
    }
});

add_action('wp_head', static function () {
    if ('1' !== ($_SERVER['HTTP_X_OMX_TEST_SCHEMA'] ?? '') || defined('SLIM_SEO_VER')) {
        return;
    }
    $url   = (string) wp_get_canonical_url();
    $graph = [
        ['@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url],
        ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumblist', 'itemListElement' => []],
    ];
    $graph = apply_filters('slim_seo_schema_graph', $graph);
    echo '<script type="application/ld+json" id="omx-test-schema-emulation">' . wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}, 99);
