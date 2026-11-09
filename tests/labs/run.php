<?php
/**
 * OmochiX Labs: PHP unit and contract tests with a minimal WordPress stub.
 * Run: php tests/labs/run.php            (no WordPress, DB or network needed)
 *
 * Covers inc/labs.php (catalog, routing and 404s, rewrite rule, SEO filters,
 * Open Graph, JSON-LD on Slim SEO's graph, security headers, pricing and
 * crawler rules, links, footer) and renders every Labs template.
 */
require __DIR__ . '/stub.php';
require __DIR__ . '/release-state.php';

// The shipped catalog keeps the cost calculator in draft until its prices are
// reviewed. The tests below exercise every tool, so they run on a view of the
// theme with every tool published; the release-state checks at the end use the
// shipped theme as it is.
$REAL_THEME = $GLOBALS['THEME'];
$GLOBALS['THEME'] = labs_published_theme($REAL_THEME);

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }
function has_error(array $errors, $needle) { foreach ($errors as $e) { if (false !== strpos($e, $needle)) { return true; } } return false; }
function raw_catalog() { return json_decode(file_get_contents(omochix_labs_data_file('catalog')), true); }
function catalog_errors(callable $mutate) { $d = raw_catalog(); $mutate($d); return omochix_labs_validate_catalog($d); }
function render(callable $fn) { ob_start(); $fn(); return (string) ob_get_clean(); }
function filter_value($hook, $value) { return apply_filters($hook, $value, 0); }

check('stub: inc/labs.php statics are resettable (4 caches)', 4 === $GLOBALS['labs_static_wraps']);

// --------------------------------------------------------------- catalog
labs_request();
$loaded = omochix_labs_load_catalog_from_file(omochix_labs_data_file('catalog'));
check('catalog: shipped catalog is valid', $loaded['ok'] && [] === $loaded['errors']);
check('catalog: the four C1 tools, in order', ['json-formatter', 'text-diff', 'robots-txt-generator', 'token-cost-calculator'] === array_keys(omochix_labs_get_tools()));
check('catalog: three categories with counts', ['ai' => 1, 'dev' => 2, 'web' => 1] === array_column(omochix_labs_get_categories(), 'count', 'slug'));
check('catalog: every tool has 3+ FAQ entries and 3+ features', !array_filter(omochix_labs_get_tools(), static fn($t) => count($t['faq']) < 3 || count($t['features']) < 3));
check('catalog: missing file degrades to empty', !omochix_labs_load_catalog_from_file('/nonexistent.json')['ok'] && [] === omochix_labs_load_catalog_from_file('/nonexistent.json')['catalog']['tools']);
$tmp = tempnam(sys_get_temp_dir(), 'omx'); file_put_contents($tmp, '{bad');
check('catalog: invalid JSON degrades to empty', has_error(omochix_labs_load_catalog_from_file($tmp)['errors'], 'invalid JSON')); unlink($tmp);
check('validate: non-array root', has_error(omochix_labs_validate_catalog('x'), 'root'));
check('validate: wrong version', has_error(catalog_errors(static function (&$d) { $d['version'] = 2; }), 'version'));
check('validate: duplicate slug', has_error(catalog_errors(static function (&$d) { $d['tools'][1]['slug'] = $d['tools'][0]['slug']; }), 'duplicate slug'));
check('validate: upper-case slug', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['slug'] = 'JSON'; }), 'invalid slug'));
check('validate: unknown category', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['category'] = 'nope'; }), 'unknown category'));
check('validate: status', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['status'] = 'live'; }), 'status'));
check('validate: title length (70)', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['title'] = str_repeat('あ', 71); }), 'title'));
check('validate: description length (160)', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['description'] = str_repeat('あ', 161); }), 'description'));
check('validate: related must be other tools', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['related'] = ['json-formatter']; }), 'related') && has_error(catalog_errors(static function (&$d) { $d['tools'][0]['related'] = ['nope']; }), 'related'));
check('validate: learn links are learn/ paths', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['links']['learn'] = ['https://evil.test/']; }), 'links.learn'));
check('validate: ai_tools / prompts are slugs', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['links']['prompts'] = ['../x']; }), 'links.prompts'));
check('validate: faq entries need q and a', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['faq'][] = ['q' => 'x']; }), 'faq'));
check('validate: application category', has_error(catalog_errors(static function (&$d) { $d['tools'][0]['application_category'] = 'Game'; }), 'application_category'));
check('validate: a valid mutation stays valid', [] === catalog_errors(static function (&$d) { $d['tools'][0]['related'] = []; $d['tools'][0]['faq'] = []; }));

$draft = raw_catalog(); $draft['tools'][1]['status'] = 'draft';
$norm = omochix_labs_normalize_catalog($draft);
check('catalog: drafts stay out of category counts', 1 === $norm['categories']['dev']['count']);

// --------------------------------------------------------- list filtering
labs_request();
check('filter: keyword (case-insensitive)', ['json-formatter'] === array_keys(omochix_labs_filter_tools(['q' => 'JSON 整形'])));
check('filter: keyword ANDed', [] === omochix_labs_filter_tools(['q' => 'json robots']));
check('filter: category', ['json-formatter', 'text-diff'] === array_keys(omochix_labs_filter_tools(['cat' => 'dev'])));
check('filter: keyword matches English keyword', ['token-cost-calculator'] === array_keys(omochix_labs_filter_tools(['q' => 'anthropic'])) && ['robots-txt-generator', 'token-cost-calculator'] === array_keys(omochix_labs_filter_tools(['q' => 'claude'])));
check('filter: sanitize drops unknown category and trims query', ['cat' => '', 'q' => 'x'] === omochix_labs_sanitize_filters(['cat' => 'nope', 'q' => " <b>x</b> "]));
check('filter: query capped at 60 characters', 60 === mb_strlen(omochix_labs_sanitize_filters(['q' => str_repeat('あ', 100)])['q']));
check('filter: non-string query ignored', ['cat' => '', 'q' => ''] === omochix_labs_sanitize_filters(['cat' => ['ai'], 'q' => ['x']]));
$rel = array_column(omochix_labs_related_tools(omochix_labs_get_tool('json-formatter')), 'slug');
check('related: curated first, never self, at most 4', ['text-diff', 'token-cost-calculator', 'robots-txt-generator'] === $rel);

// ---------------------------------------------------------------- routing
do_action('init');
check('rewrite: top rules for /labs/{slug}/ and /labs-sitemap.xml', [['^labs/([a-z0-9-]+)/?$', 'index.php?pagename=labs&omx_lab=$matches[1]', 'top'], ['^labs\\-sitemap\\.xml$', 'index.php?omx_labs_sitemap=1', 'top']] === $GLOBALS['rewrite']);
check('rewrite: flushed once per version (2 adds the sitemap)', 1 === $GLOBALS['flushed'] && '2' === get_option('omochix_labs_rewrite_version'));
$sitemap_rule = '#' . $GLOBALS['rewrite'][1][0] . '#';
check('rewrite: sitemap rule matches only labs-sitemap.xml', preg_match($sitemap_rule, 'labs-sitemap.xml') && !array_filter(['sitemap.xml', 'sitemap-post-type-page.xml', 'labs-sitemap.xml/', 'xlabs-sitemap.xml', 'labs/labs-sitemap.xml', 'labs-sitemapxxml'], static fn($u) => preg_match($sitemap_rule, $u)));
check('rewrite: Slim SEO sitemap rules (anchored by WordPress) do not catch labs-sitemap.xml', !preg_match('#^sitemap\.xml$#', 'labs-sitemap.xml') && !preg_match('#^sitemap-(post-type-[^/]+?)\.xml$#', 'labs-sitemap.xml'));
check('query var for the sitemap registered', in_array('omx_labs_sitemap', apply_filters('query_vars', []), true));
do_action('init');
check('rewrite: not flushed again', 1 === $GLOBALS['flushed']);
check('query var registered', in_array('omx_lab', apply_filters('query_vars', ['p']), true));
$rule = '#' . $GLOBALS['rewrite'][0][0] . '#';
$must_not_match = ['labs', 'labs/', 'labs/a/b/', 'ai-tools/chatgpt/', 'learn/motion/pop-in/', 'learn/motion/compose/', 'prompts/category/x/', 'labsx/json-formatter/', 'my-labs/json-formatter/', 'labs/JSON/', 'labs/feed/x/'];
check('rewrite: does not capture existing or nested URLs', !array_filter($must_not_match, static fn($u) => preg_match($rule, $u)));
check('rewrite: captures tool URLs with or without slash', preg_match($rule, 'labs/json-formatter/') && preg_match($rule, 'labs/text-diff'));

labs_request(null, [], false);
check('routing: Labs is off while the page is unpublished', '' === omochix_labs_url() && !omochix_labs_is_request());
labs_request(null, [], true, false);
check('routing: not a request on other pages', !omochix_labs_is_request());
labs_request();
check('routing: list URL', 'https://example.test/labs/' === omochix_labs_url() && omochix_labs_is_request() && null === omochix_labs_current_tool());
check('routing: tool URL', 'https://example.test/labs/text-diff/' === omochix_labs_url(omochix_labs_get_tool('text-diff')));
check('routing: filtered list URL', 'https://example.test/labs/?cat=ai' === omochix_labs_url(null, ['cat' => 'ai', 'q' => '']));
foreach (['unknown-tool', 'JSON-FORMATTER', 'json-formatter/../x', '0'] as $bad) {
    labs_request($bad);
    do_action('template_redirect');
    check("404: unknown slug '$bad'", is_404() && 404 === $GLOBALS['status']);
}
$GLOBALS['labs_draft_catalog'] = true;
labs_request('json-formatter');
do_action('template_redirect');
check('404: a published tool is served', !is_404() && 200 === $GLOBALS['status'] && 'json-formatter' === omochix_labs_current_tool()['slug']);
check('template: page-labs.php for the request', str_ends_with(apply_filters('template_include', '/index.php'), '/page-labs.php'));
labs_request('unknown-tool');
do_action('template_redirect');
check('template: 404 keeps the 404 template', '/404.php' === apply_filters('template_include', '/404.php'));
labs_request(null, [], true, false);
check('template: other pages untouched', '/page.php' === apply_filters('template_include', '/page.php'));

// --------------------------------------------------------------------- SEO
$titles = []; $descriptions = [];
labs_request();
$titles[] = filter_value('slim_seo_meta_title', 'X'); $descriptions[] = filter_value('slim_seo_meta_description', 'X');
check('seo: list title', 'OmochiX Labs｜AI・開発・Web運用の無料オンラインツール' === $titles[0]);
check('seo: list canonical has no query', 'https://example.test/labs/' === filter_value('slim_seo_canonical_url', 'X'));
check('seo: list indexable', true === filter_value('slim_seo_robots_index', true));
foreach (array_keys(omochix_labs_get_tools()) as $slug) {
    labs_request($slug);
    $titles[] = filter_value('slim_seo_meta_title', 'X');
    $descriptions[] = filter_value('slim_seo_meta_description', 'X');
    check("seo: $slug canonical", "https://example.test/labs/$slug/" === filter_value('slim_seo_canonical_url', 'X'));
    check("seo: $slug document title matches Slim SEO title", filter_value('pre_get_document_title', '') === end($titles));
    check("seo: $slug og:type is website", 'website' === filter_value('slim_seo_open_graph_type', 'article'));
    check("seo: $slug og:url / og:title", "https://example.test/labs/$slug/" === filter_value('slim_seo_open_graph_url', 'X') && end($titles) === filter_value('slim_seo_open_graph_title', 'X'));
}
check('seo: five unique titles', 5 === count(array_unique($titles)) && !in_array('X', $titles, true));
check('seo: five unique descriptions of 60-160 characters', 5 === count(array_unique($descriptions)) && !array_filter($descriptions, static fn($d) => mb_strlen($d) < 60 || mb_strlen($d) > 160));
check('seo: tool titles end with the brand', 4 === count(array_filter($titles, static fn($t) => str_ends_with($t, ' | OmochiX Labs'))));
labs_request(null, ['cat' => 'ai']);
check('seo: filtered list is noindex, follow and canonical to the list', false === filter_value('slim_seo_robots_index', true) && 'https://example.test/labs/' === filter_value('slim_seo_canonical_url', 'X') && ['noindex' => true, 'follow' => true] === filter_value('wp_robots', []));
labs_request(null, ['cat' => 'bogus']);
check('seo: an unknown category is not a filter', true === filter_value('slim_seo_robots_index', true));
labs_request('unknown-tool');
do_action('template_redirect');
check('seo: a 404 keeps the default SEO values', 'X' === filter_value('slim_seo_meta_title', 'X') && 'X' === filter_value('slim_seo_canonical_url', 'X') && 'X' === filter_value('slim_seo_open_graph_title', 'X'));
labs_request(null, [], true, false);
check('seo: other pages keep their values', 'X' === filter_value('slim_seo_meta_title', 'X') && 'X' === filter_value('slim_seo_meta_description', 'X') && 'X' === filter_value('slim_seo_canonical_url', 'X') && true === filter_value('slim_seo_robots_index', true) && 'X' === filter_value('slim_seo_open_graph_image', 'X') && [] === filter_value('wp_robots', []));
labs_request('text-diff');
$crumbs = apply_filters('slim_seo_breadcrumbs_links', [['url' => 'https://example.test/', 'text' => 'ホーム']]);
check('seo: tool breadcrumb adds the list once', [['url' => 'https://example.test/', 'text' => 'ホーム'], ['url' => 'https://example.test/labs/', 'text' => 'OmochiX Labs']] === $crumbs);
check('seo: breadcrumb not duplicated when the list is already last', 2 === count(apply_filters('slim_seo_breadcrumbs_links', $crumbs)));
check('seo: page title becomes the tool name', 'テキスト差分比較' === apply_filters('single_post_title', 'OmochiX Labs', $GLOBALS['pages']['labs']));
$og_image = filter_value('slim_seo_open_graph_image', 'DEFAULT');
check('seo: og:image is the tool image when shipped, else Slim SEO default', $og_image === (file_exists(get_theme_file_path('/assets/img/labs/og/text-diff.png')) ? 'https://example.test/wp-content/themes/omochix-theme/assets/img/labs/og/text-diff.png' : 'DEFAULT'));

// -------------------------------------------------------- structured data
function slim_graph($url) {
    return [
        ['@type' => 'WebSite', '@id' => 'https://example.test/#website', 'url' => 'https://example.test/'],
        ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumblist', 'itemListElement' => []],
        ['@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => 'old', 'breadcrumb' => ['@id' => $url . '#breadcrumblist'], 'potentialAction' => ['@id' => $url . '#readaction'], 'isPartOf' => ['@id' => 'https://example.test/#website']],
        ['@type' => 'ReadAction', '@id' => $url . '#readaction', 'target' => $url],
        ['@type' => 'Organization', '@id' => 'https://example.test/#organization'],
        ['@type' => 'SoftwareApplication', '@id' => $url . '#software'],
    ];
}
function types($graph) { return array_map(static fn($e) => $e['@type'], $graph); }
labs_request();
$g = apply_filters('slim_seo_schema_graph', slim_graph('https://example.test/labs/'));
$byType = array_column($g, null, '@type');
check('schema list: CollectionPage + ItemList + one BreadcrumbList, no WebPage, no app types', ['WebSite', 'BreadcrumbList', 'CollectionPage', 'ReadAction', 'Organization', 'ItemList'] === types($g));
check('schema list: ItemList has the four tools in order', 4 === $byType['ItemList']['numberOfItems'] && 'https://example.test/labs/json-formatter/' === $byType['ItemList']['itemListElement'][0]['url'] && 4 === $byType['ItemList']['itemListElement'][3]['position']);
check('schema list: CollectionPage points at the ItemList', 'https://example.test/labs/#itemlist' === $byType['CollectionPage']['mainEntity']['@id'] && 'https://example.test/labs/#webpage' === $byType['CollectionPage']['@id']);
labs_request('json-formatter');
// Slim SEO builds its ids from the canonical URL (already the tool URL), but also handle the list URL.
foreach (['https://example.test/labs/json-formatter/', 'https://example.test/labs/'] as $source_url) {
    $g = apply_filters('slim_seo_schema_graph', slim_graph($source_url));
    $byType = array_column($g, null, '@type');
    $u = 'https://example.test/labs/json-formatter/';
    check("schema tool ($source_url): WebPage + WebApplication, one BreadcrumbList, no SoftwareApplication", ['WebSite', 'BreadcrumbList', 'WebPage', 'ReadAction', 'Organization', 'WebApplication'] === types($g));
    check("schema tool ($source_url): ids and references on the tool URL", $u . '#webpage' === $byType['WebPage']['@id'] && $u . '#breadcrumblist' === $byType['BreadcrumbList']['@id'] && $u . '#breadcrumblist' === $byType['WebPage']['breadcrumb']['@id'] && $u . '#readaction' === $byType['WebPage']['potentialAction']['@id'] && $u === $byType['ReadAction']['target']);
}
$app = $byType['WebApplication'];
check('schema tool: free WebApplication with category, OS and features', '0' === $app['offers']['price'] && 'JPY' === $app['offers']['priceCurrency'] && true === $app['isAccessibleForFree'] && 'DeveloperApplication' === $app['applicationCategory'] && 'Any' === $app['operatingSystem'] && count($app['featureList']) >= 3 && 'https://example.test/#organization' === $app['publisher']['@id']);
check('schema tool: no FAQPage anywhere', !in_array('FAQPage', types($g), true));
labs_request(null, [], true, false);
check('schema: other pages untouched', slim_graph('https://example.test/x/') === apply_filters('slim_seo_schema_graph', slim_graph('https://example.test/x/')));
labs_request('unknown-tool');
do_action('template_redirect');
check('schema: 404 untouched', slim_graph('https://example.test/labs/unknown-tool/') === apply_filters('slim_seo_schema_graph', slim_graph('https://example.test/labs/unknown-tool/')));

// ----------------------------------------------------------------- headers
$h = omochix_labs_security_headers();
check('headers: Report-Only CSP, nosniff, referrer policy', ['Content-Security-Policy-Report-Only', 'X-Content-Type-Options', 'Referrer-Policy'] === array_keys($h));
$csp = $h['Content-Security-Policy-Report-Only'];
foreach (["default-src 'self'", "worker-src 'self' blob:", "object-src 'none'", "base-uri 'self'", "form-action 'self'", "connect-src 'self' https://www.googletagmanager.com"] as $directive) {
    check("headers: CSP has $directive", false !== strpos($csp, $directive));
}
// The whole policy is pinned: any new source or directive has to change this list on purpose.
$ga = 'https://www.googletagmanager.com https://*.googletagmanager.com https://*.google-analytics.com https://*.analytics.google.com https://*.g.doubleclick.net';
$expected_csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob: https://s.w.org https://secure.gravatar.com $ga",
    "font-src 'self' data:",
    "connect-src 'self' $ga",
    "worker-src 'self' blob:",
    "frame-src 'self'",
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
];
check('headers: CSP is exactly the reviewed allowlist (nothing widened)', explode('; ', $csp) === $expected_csp, explode('; ', $csp));
// Browsers ignore frame-ancestors in Report-Only (Safari warns); it returns with the enforced policy in C2.
check('headers: Report-Only CSP has no frame-ancestors', false === strpos($csp, 'frame-ancestors'));
// No report endpoint by design (docs/labs/LABS-CSP-OBSERVATION.md): adding one is a reviewed change.
check('headers: Report-Only CSP has no report-uri / report-to', !preg_match('/\breport-(uri|to)\b/', $csp));
check('headers: CSP never allows eval', false === strpos($csp, 'unsafe-eval'));
check('headers: all added when none are present', 3 === count(omochix_labs_headers_to_send(['Content-Type: text/html'])));
check('headers: an existing header is never replaced (case-insensitive)', ['Content-Security-Policy-Report-Only', 'Referrer-Policy'] === array_keys(omochix_labs_headers_to_send(['x-content-type-options: nosniff'])));
check('headers: an enforced CSP already present suppresses the Report-Only one', !isset(omochix_labs_headers_to_send(["Content-Security-Policy: default-src 'none'"])['Content-Security-Policy-Report-Only']));
check('headers: a stricter existing referrer policy is kept', !isset(omochix_labs_headers_to_send(['Referrer-Policy: no-referrer'])['Referrer-Policy']));

// ------------------------------------------------------------------ assets
foreach (['json-formatter' => ['json'], 'text-diff' => ['diff'], 'robots-txt-generator' => ['robots'], 'token-cost-calculator' => ['tokens', 'cost']] as $slug => $mods) {
    labs_request($slug);
    $GLOBALS['enqueued'] = ['styles' => [], 'scripts' => [], 'inline' => []];
    do_action('wp_enqueue_scripts');
    $scripts = array_keys($GLOBALS['enqueued']['scripts']);
    $expect = array_merge(['omochix-labs-text', 'omochix-labs-events', 'omochix-labs-shared'], array_map(static fn($m) => 'omochix-labs-' . $m, $mods), ['omochix-labs-ops', 'omochix-labs-tool']);
    check("assets: $slug loads only its modules", $expect === $scripts && isset($GLOBALS['enqueued']['styles']['omochix-labs']));
    $inline = implode('', $GLOBALS['enqueued']['inline']['omochix-labs-shared'] ?? []);
    $cfg = json_decode(substr($inline, strlen('window.omochixLabs = '), -1), true);
    check("assets: $slug config", $slug === $cfg['tool'] && str_contains($cfg['worker']['url'], '/assets/js/labs/worker.js') && count($cfg['worker']['scripts']) === count($mods) + 2);
    check("assets: $slug script files exist", !array_filter($scripts, static fn($s) => !is_file(get_theme_file_path(substr($GLOBALS['enqueued']['scripts'][$s]['src'], strlen('https://example.test/wp-content/themes/omochix-theme'))))));
    if ('token-cost-calculator' === $slug) {
        check('assets: pricing only on the calculator', isset($cfg['pricing']['models']) && !isset($cfg['crawlers']));
    }
    if ('robots-txt-generator' === $slug) {
        check('assets: crawlers only on the robots tool', isset($cfg['crawlers']['crawlers']) && !isset($cfg['pricing']));
    }
}
labs_request(null, [], true, false);
$GLOBALS['enqueued'] = ['styles' => [], 'scripts' => [], 'inline' => []];
do_action('wp_enqueue_scripts');
check('assets: nothing on other pages', [] === $GLOBALS['enqueued']['scripts'] && [] === $GLOBALS['enqueued']['styles']);
check('assets: inline JSON cannot close the script element', !str_contains(omochix_labs_json_for_script(['x' => '</script><script>alert(1)</script>', 'y' => "a'b\"c&"]), '</') && !str_contains(omochix_labs_json_for_script(['y' => "'\"&<>"]), "'"));

// ----------------------------------------------------------------- pricing
$pricing_raw = omochix_labs_read_guarded_json(omochix_labs_pricing_file())['data'];
$view = omochix_labs_pricing_view($pricing_raw, '2026-11-04');
check('pricing: shipped file is valid with no hidden rows', $view['ok'] && [] === $view['hidden'] && 90 === $view['max_age_days']);
check('pricing: input and output prices are separate numbers', !array_filter($view['models'], static fn($m) => !is_float($m['input']) || !is_float($m['output'])));
check('pricing: every row has an official https source and a check date', !array_filter($view['models'], static fn($m) => !omochix_labs_is_allowed_source($m['source_url'], OMOCHIX_LABS_PRICE_SOURCE_HOSTS) || !omochix_labs_is_date($m['verified_at'])));
$labels = static fn($rows) => array_map(static fn($m) => $m['id'] . '=' . $m['input'], $rows);
$nov = omochix_labs_pricing_active($view['models'], '2026-11-04', 90);
check('pricing: 11 models apply on 2026-11-04 (Argon at $2)', 11 === count($nov) && in_array('gemini-4-argon=2', $labels($nov), true) && !in_array('gemini-4-argon=4', $labels($nov), true));
$dec = omochix_labs_pricing_active(omochix_labs_pricing_view($pricing_raw, '2026-12-02')['models'], '2026-12-02', 90);
check('pricing: on 2026-12-02 Argon switches to $4', in_array('gemini-4-argon=4', $labels($dec), true) && !in_array('gemini-4-argon=2', $labels($dec), true));
check('pricing: nothing older than 90 days is shown (2027-02-03)', [] === omochix_labs_pricing_active(omochix_labs_pricing_view($pricing_raw, '2027-02-03')['models'], '2027-02-03', 90));
check('pricing: still shown on day 90 (2027-02-02)', [] !== omochix_labs_pricing_active(omochix_labs_pricing_view($pricing_raw, '2027-02-02')['models'], '2027-02-02', 90));
$bad = $pricing_raw;
$bad['models'][0]['status'] = 'needs_review';
$bad['models'][1]['source_url'] = 'https://example.com/pricing';
$bad['models'][2]['verified_at'] = '2026-13-01';
$bad['models'][3]['input'] = '10';
$bad['models'][4]['provider'] = 'unknown';
$bad['max_age_days'] = 365;
$bv = omochix_labs_pricing_view($bad, '2026-11-04');
check('pricing: unverified / foreign source / bad date / string price / unknown provider are hidden', ['unverified' => 2, 'source' => 1, 'invalid' => 2] === $bv['hidden'] && 8 === count($bv['models']));
check('pricing: max_age_days is capped at 90', 90 === $bv['max_age_days']);
$dupe = $pricing_raw; $dupe['models'][] = $dupe['models'][0];
check('pricing: two rows for one model on the same day are both hidden', !in_array('claude-opus-5-5', array_column(omochix_labs_pricing_active(omochix_labs_pricing_view($dupe, '2026-11-04')['models'], '2026-11-04', 90), 'id'), true));
check('pricing: wrong schema / currency / unit is rejected', !omochix_labs_pricing_view(['schema_version' => 1, 'currency' => 'JPY', 'unit' => 'per_1m_tokens'], '2026-11-04')['ok'] && !omochix_labs_pricing_view(null, '2026-11-04')['ok']);
check('pricing: lookalike hosts are not official', !omochix_labs_is_allowed_source('https://platform.claude.com.evil.test/', OMOCHIX_LABS_PRICE_SOURCE_HOSTS) && !omochix_labs_is_allowed_source('http://platform.claude.com/', OMOCHIX_LABS_PRICE_SOURCE_HOSTS) && omochix_labs_is_allowed_source('https://PLATFORM.claude.com/docs', OMOCHIX_LABS_PRICE_SOURCE_HOSTS));
check('pricing: review is still pending (release gate)', 'pending' === $pricing_raw['review']['status']);
check('pricing: rows start pending (Anthropic, Google) or held with a reason (OpenAI); none approved', !array_filter($pricing_raw['models'], static fn($m) => ('openai' === $m['provider'] ? 'held' : 'pending') !== $m['approval']['status'] || ('held' === $m['approval']['status'] && '' === trim((string) ($m['approval']['reason'] ?? '')))) && !array_filter($view['models'], static fn($m) => 'approved' === $m['approval']));

// ---------------------------------------------------- pricing file is not public
$pfile = omochix_labs_pricing_file();
check('pricing file: lives under inc/, not assets/', str_contains($pfile, '/inc/labs-data/pricing.json.php') && !file_exists(get_theme_file_path('/assets/data/labs/pricing.json')));
check('pricing file: starts with the exit guard and prints nothing when executed (a browser request gets an empty body)', 0 === strpos((string) file_get_contents($pfile), '<?php exit;') && '' === (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($pfile)));
$tmpUnguarded = tempnam(sys_get_temp_dir(), 'omx'); file_put_contents($tmpUnguarded, '{"schema_version":2}');
check('pricing file: a file without the guard line is refused', !omochix_labs_read_guarded_json($tmpUnguarded)['ok']); unlink($tmpUnguarded);
check('pricing file: the data folder denies direct HTTP access (.htaccess, in case PHP is not executed)', str_contains((string) @file_get_contents(dirname($pfile) . '/.htaccess'), 'Require all denied') && str_contains((string) @file_get_contents(dirname($pfile) . '/.htaccess'), 'Deny from all'));
// Fail closed: broken or unexpected data shows no price anywhere.
foreach (['broken JSON' => "<?php exit; ?>\n{\"schema_version\": 2, ", 'no guard line' => json_encode($pricing_raw), 'schema 1' => "<?php exit; ?>\n" . json_encode(array_replace($pricing_raw, ['schema_version' => 1])), 'models not a list' => "<?php exit; ?>\n" . json_encode(array_replace($pricing_raw, ['models' => 'x']))] as $label => $body) {
    $tmpBad = tempnam(sys_get_temp_dir(), 'omx'); file_put_contents($tmpBad, $body);
    $readBad = omochix_labs_read_guarded_json($tmpBad); unlink($tmpBad);
    $viewBad = omochix_labs_pricing_view($readBad['ok'] ? $readBad['data'] : null, '2026-11-06');
    $ok = [] === omochix_labs_pricing_gate($viewBad, omochix_labs_pricing_approved($readBad['data'] ?? null), true)['models'] && [] === omochix_labs_pricing_gate($viewBad, true, false)['models'];
    check("fail closed: $label shows no price on production or staging", $ok);
}
$weird = approve_rows(array_replace($pricing_raw, ['review' => ['status' => 'APPROVED', 'reviewer' => 'R', 'reviewed_at' => '2026-11-06']]), ['claude-opus-5-5']);
check('fail closed: an unexpected review value is not an approval', !omochix_labs_pricing_approved($weird) && [] === omochix_labs_pricing_gate(omochix_labs_pricing_view($weird, '2026-11-06'), omochix_labs_pricing_approved($weird), true)['models']);
$weirdRow = $pricing_raw; $weirdRow['models'][0]['approval'] = ['status' => 'Approved', 'reviewer' => 'R', 'reviewed_at' => '2026-11-06', 'acknowledged' => $pricing_raw['models'][0]['caveats']];
check('fail closed: an unexpected row status hides the row', 1 === (omochix_labs_pricing_view($weirdRow, '2026-11-06')['hidden']['invalid'] ?? 0));
$publicHits = [];
$ids = array_unique(array_column($pricing_raw['models'], 'id'));
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(get_theme_file_path('/assets'), FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS)) as $f) {
    $txt = (string) file_get_contents((string) $f);
    foreach ($ids as $id) { if (str_contains($txt, $id)) { $publicHits[] = basename((string) $f) . ':' . $id; } }
}
check('pricing file: no public asset (CSS, JS, JSON, images) contains a priced model id', [] === $publicHits);

// --------------------------------------------------------- row gate (PHP side)
function approve_rows(array $data, array $ids, $date = '2026-11-06') {
    foreach ($data['models'] as $i => $m) {
        if (in_array($m['id'], $ids, true)) {
            $data['models'][$i]['approval'] = ['status' => 'approved', 'reviewer' => 'Reviewer', 'reviewed_at' => $date, 'acknowledged' => $m['caveats']];
        }
    }
    return $data;
}
$fileOk = $pricing_raw; $fileOk['review'] = ['status' => 'approved', 'reviewer' => 'Reviewer', 'reviewed_at' => '2026-11-06'];
$anth = approve_rows($fileOk, ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5']);
$g = omochix_labs_pricing_gate(omochix_labs_pricing_view($anth, '2026-11-06'), true, true);
check('row gate: production shows only the approved rows of an approved file', ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'] === array_column($g['models'], 'id') && false === $g['withheld'] && 5 === ($g['hidden']['row_held'] ?? 0) && 5 === ($g['hidden']['row_pending'] ?? 0)); // Google: 3 current + 2 scheduled rows
$g = omochix_labs_pricing_gate(omochix_labs_pricing_view(approve_rows($pricing_raw, ['claude-opus-5-5']), '2026-11-06'), false, true);
check('row gate: an approved row is still hidden while the file is not approved', [] === $g['models'] && true === $g['withheld']);
$g = omochix_labs_pricing_gate(omochix_labs_pricing_view($fileOk, '2026-11-06'), true, true);
check('row gate: an approved file with no approved row shows nothing', [] === $g['models'] && true === $g['withheld']);
$g = omochix_labs_pricing_gate(omochix_labs_pricing_view($anth, '2026-11-06'), true, false);
check('row gate: staging lists every valid row with its state', 11 === count(omochix_labs_pricing_active($g['models'], '2026-11-06', 90)) && ['approved' => 3, 'held' => 5, 'pending' => 3] === array_count_values(array_column(omochix_labs_pricing_active($g['models'], '2026-11-06', 90), 'approval')));
$partial = $anth; $partial['models'][0]['approval']['acknowledged'] = ['cache_write_excluded'];
check('row gate: an approval that misses a caveat stays pending', 'pending' === omochix_labs_row_approval_state($partial['models'][0]));
$early = approve_rows($fileOk, ['claude-opus-5-5'], '2026-11-03');
check('row gate: an approval dated before the price check stays pending (re-checked prices need a new review)', 'pending' === omochix_labs_row_approval_state($early['models'][0]));
$heldNoReason = $pricing_raw; unset($heldNoReason['models'][3]['approval']['reason']);
check('row gate: a held row without a reason is invalid', 1 === (omochix_labs_pricing_view($heldNoReason, '2026-11-06')['hidden']['invalid'] ?? 0));
$unknownCaveat = $pricing_raw; $unknownCaveat['models'][0]['caveats'][] = 'made_up_caveat';
check('row gate: a caveat code without a label is invalid', 1 === (omochix_labs_pricing_view($unknownCaveat, '2026-11-06')['hidden']['invalid'] ?? 0));
$staleApproved = approve_rows($fileOk, ['claude-opus-5-5']);
check('row gate: an approved row still disappears after 90 days', [] === omochix_labs_pricing_active(omochix_labs_pricing_gate(omochix_labs_pricing_view($staleApproved, '2027-02-04'), true, true)['models'], '2027-02-04', 90));

// ---------------------------------------------------------------- crawlers
$crawlers_raw = json_decode(file_get_contents(omochix_labs_data_file('crawlers')), true);
$cv = omochix_labs_crawlers_view($crawlers_raw, '2026-11-04');
check('crawlers: 13 rows with official https sources', 13 === count($cv['crawlers']) && !array_filter($cv['crawlers'], static fn($c) => !str_starts_with($c['source_url'], 'https://')));
check('crawlers: training / search / user purposes', ['training', 'search', 'user'] === array_keys($cv['purposes']));
check('crawlers: rows older than 180 days are dropped', [] === omochix_labs_crawlers_view($crawlers_raw, '2027-05-04')['crawlers']);
$cbad = $crawlers_raw; $cbad['crawlers'][0]['token'] = "Evil\nDisallow: /"; $cbad['crawlers'][1]['source_url'] = 'javascript:alert(1)'; $cbad['crawlers'][] = $cbad['crawlers'][2];
check('crawlers: bad token, bad source and duplicates are dropped', 11 === count(omochix_labs_crawlers_view($cbad, '2026-11-04')['crawlers']));

// ------------------------------------------------------------------- links
labs_request('token-cost-calculator');
$GLOBALS['posts'] = [
    new WP_Post(['ID' => 1, 'post_type' => 'ai_tool', 'post_name' => 'claude', 'post_title' => 'Claude']),
    new WP_Post(['ID' => 2, 'post_type' => 'ai_tool', 'post_name' => 'chatgpt', 'post_title' => 'ChatGPT', 'post_status' => 'draft']),
];
$GLOBALS['pages']['learn/basics/prompt'] = new WP_Post(['ID' => 9, 'post_name' => 'prompt', 'post_title' => 'プロンプトの基本', 'path' => 'learn/basics/prompt']);
$GLOBALS['pages']['learn/tutorials/add-ai-api'] = new WP_Post(['ID' => 10, 'post_name' => 'add-ai-api', 'post_title' => 'AI API', 'post_status' => 'private']);
$links = omochix_labs_site_links(omochix_labs_get_tool('token-cost-calculator'));
check('links: only published content, with type and slug for analytics', [['url' => 'https://example.test/learn/basics/prompt/', 'title' => 'プロンプトの基本', 'type' => 'learn', 'slug' => 'prompt']] === $links['learn'] && ['claude'] === array_column($links['ai_tools'], 'slug') && [] === $links['prompts']);
$footer = apply_filters('omochix_editorial_footer_links', [['label' => 'AIツール', 'url' => 'x'], ['label' => 'About', 'url' => 'y']]);
check('footer: Labs inserted before About', ['AIツール', 'Labs', 'About'] === array_column($footer, 'label'));
labs_request(null, [], false);
check('footer: no link while the page is unpublished', 1 === count(apply_filters('omochix_editorial_footer_links', [['label' => 'About', 'url' => 'y']])));
$shell = file_get_contents(get_theme_file_path('/inc/shell.php'));
check('shell: Labs pages use the shared shell', str_contains($shell, "(function_exists('omochix_labs_is_request') && omochix_labs_is_request())"));
check('functions.php: requires inc/labs.php once', 1 === substr_count(file_get_contents(get_theme_file_path('/functions.php')), "require_once get_theme_file_path('/inc/labs.php');"));

// ---------------------------------------------------------------- sitemap
labs_request();
$entries = omochix_labs_sitemap_entries();
check('sitemap: the four published tool URLs, not the list page (Slim SEO lists it)', array_map(static fn($t) => 'https://example.test/labs/' . $t . '/', array_keys(omochix_labs_get_tools())) === array_column($entries, 'loc') && !in_array('https://example.test/labs/', array_column($entries, 'loc'), true));
$xml = omochix_labs_sitemap_xml($entries);
$doc = new DOMDocument();
check('sitemap: well-formed XML', $doc->loadXML($xml, LIBXML_NONET));
$sx = simplexml_load_string($xml);
check('sitemap: urlset in the sitemaps.org 0.9 namespace with 4 urls', 'urlset' === $sx->getName() && 'http://www.sitemaps.org/schemas/sitemap/0.9' === $doc->documentElement->namespaceURI && 4 === count($sx->url));
check('sitemap: absolute https-or-http loc and W3C dates only', !array_filter(iterator_to_array($sx->url, false), static fn($u) => !preg_match('#^https?://[^/]+/labs/[a-z0-9-]+/$#', (string) $u->loc) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $u->lastmod)));
$esc = omochix_labs_sitemap_xml([['loc' => 'https://example.test/labs/x/?a=1&b="2"<3>\'', 'lastmod' => 'not-a-date']]);
check('sitemap: XML escaping round-trips and bad dates are dropped', str_contains($esc, '&amp;b=&quot;2&quot;&lt;3&gt;&apos;') && 'https://example.test/labs/x/?a=1&b="2"<3>\'' === (string) simplexml_load_string($esc)->url->loc && !str_contains($esc, 'lastmod'));
check('sitemap: empty catalog is still a valid urlset', 0 === count(simplexml_load_string(omochix_labs_sitemap_xml([]))->url));
$hdr = omochix_labs_sitemap_headers(1793750400);
check('sitemap: XML content type, noindex, nosniff, one-hour cache, Last-Modified', 'application/xml; charset=UTF-8' === $hdr['Content-Type'] && 'noindex, follow' === $hdr['X-Robots-Tag'] && 'nosniff' === $hdr['X-Content-Type-Options'] && 'public, max-age=3600' === $hdr['Cache-Control'] && 'Wed, 04 Nov 2026 00:00:00 GMT' === $hdr['Last-Modified']);
check('sitemap: conditional request', omochix_labs_sitemap_not_modified('Wed, 04 Nov 2026 00:00:00 GMT', 1793750400) && !omochix_labs_sitemap_not_modified('Tue, 03 Nov 2026 00:00:00 GMT', 1793750400) && !omochix_labs_sitemap_not_modified('', 1793750400) && !omochix_labs_sitemap_not_modified('garbage', 1793750400));
$body = render(static function () { $GLOBALS['sent_status'] = omochix_labs_send_sitemap(''); });
check('sitemap: GET sends 200 and the XML', 200 === $GLOBALS['sent_status'] && 200 === $GLOBALS['status'] && $body === omochix_labs_sitemap_xml($entries));
$body = render(static function () { $GLOBALS['sent_status'] = omochix_labs_send_sitemap(gmdate('D, d M Y H:i:s', time() + 60) . ' GMT'); });
check('sitemap: 304 without a body when unchanged', 304 === $GLOBALS['sent_status'] && '' === $body);
$body = render(static function () { omochix_labs_send_sitemap('', 'HEAD'); });
check('sitemap: HEAD has no body', '' === $body);
check('sitemap: request untouched while published', ['omx_labs_sitemap' => '1'] === apply_filters('request', ['omx_labs_sitemap' => '1']));
check('sitemap: other requests untouched', ['pagename' => 'about'] === apply_filters('request', ['pagename' => 'about']));
check('sitemap: URL', 'https://example.test/labs-sitemap.xml' === omochix_labs_sitemap_url());
$GLOBALS['pages']['labs/text-diff'] = new WP_Post(['ID' => 501, 'post_name' => 'text-diff', 'post_title' => 'A real page', 'post_parent' => 77]);
labs_reset_statics();
check('sitemap: a tool path that is also a real page (listed by Slim SEO) is left out', !in_array('https://example.test/labs/text-diff/', array_column(omochix_labs_sitemap_entries(), 'loc'), true) && 3 === count(omochix_labs_sitemap_entries()));
unset($GLOBALS['pages']['labs/text-diff']);
labs_reset_statics();
check('sitemap: every entry is a virtual tool URL one level under /labs/', 4 === count(array_filter(array_column(omochix_labs_sitemap_entries(), 'loc'), static fn($u) => 1 === preg_match('#^https://example\.test/labs/[a-z0-9-]+/$#', $u))));
labs_request(null, [], false);
check('sitemap: 404 while the labs page is unpublished', ['error' => '404'] === apply_filters('request', ['omx_labs_sitemap' => '1']) && '' === omochix_labs_sitemap_url());
// A draft tool never appears: run against a copy of the theme data with one tool in draft.
$tmpTheme = sys_get_temp_dir() . '/omx-labs-theme-' . getmypid();
@mkdir($tmpTheme . '/assets/data/labs', 0777, true);
$draftCatalog = raw_catalog(); $draftCatalog['tools'][1]['status'] = 'draft';
file_put_contents($tmpTheme . '/assets/data/labs/catalog.json', json_encode($draftCatalog));
$realTheme = $GLOBALS['THEME']; $GLOBALS['THEME'] = $tmpTheme;
labs_request();
check('sitemap: draft tools are left out', ['json-formatter', 'robots-txt-generator', 'token-cost-calculator'] === array_map(static fn($e) => basename($e['loc']), omochix_labs_sitemap_entries()));
labs_request('text-diff');
do_action('template_redirect');
check('sitemap: and a draft tool URL is a 404', is_404());
$GLOBALS['THEME'] = $realTheme; unlink($tmpTheme . '/assets/data/labs/catalog.json'); @rmdir($tmpTheme . '/assets/data/labs'); @rmdir($tmpTheme . '/assets/data'); @rmdir($tmpTheme . '/assets'); @rmdir($tmpTheme);
labs_request();

// ------------------------------------------------------------- robots.txt
$wpDefault = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: https://example.test/sitemap.xml\n";
check('robots: Labs sitemap appended, existing lines kept', $wpDefault . 'Sitemap: https://example.test/labs-sitemap.xml' . "\n" === apply_filters('robots_txt', $wpDefault, true));
check('robots: not added twice', apply_filters('robots_txt', $wpDefault . "Sitemap: https://example.test/labs-sitemap.xml\n", true) === $wpDefault . "Sitemap: https://example.test/labs-sitemap.xml\n");
check('robots: not added when search engines are discouraged', $wpDefault === apply_filters('robots_txt', $wpDefault, false));
check('robots: runs after Slim SEO (priority 9999)', 10000 === $GLOBALS['hooks']['robots_txt'][0][0]);
check('robots: works on admin-edited robots.txt without a trailing newline', "User-agent: *\nDisallow:\nSitemap: https://example.test/labs-sitemap.xml\n" === apply_filters('robots_txt', "User-agent: *\nDisallow:", true));
labs_request(null, [], false);
check('robots: unchanged while the labs page is unpublished', $wpDefault === apply_filters('robots_txt', $wpDefault, true));

// ----------------------------------------------------------- pricing gate
$approved = $pricing_raw; $approved['review'] = ['status' => 'approved', 'reviewer' => 'Reviewer', 'reviewed_at' => '2026-11-10'];
check('pricing gate: pending is not approved', !omochix_labs_pricing_approved($pricing_raw));
check('pricing gate: approved needs status, reviewer and date', omochix_labs_pricing_approved($approved) && !omochix_labs_pricing_approved(array_replace_recursive($approved, ['review' => ['reviewer' => ' ']])) && !omochix_labs_pricing_approved(array_replace_recursive($approved, ['review' => ['reviewed_at' => 'soon']])));
// Review mode needs a named non-production environment AND an administrator; everything else is public mode.
foreach (['production', 'staging', 'development', 'local', 'bogus-env'] as $env) {
    foreach (['anon', 'subscriber', 'admin'] as $user) {
        $GLOBALS['env'] = $env; $GLOBALS['user'] = $user;
        labs_request('token-cost-calculator');
        $pv = omochix_labs_get_pricing();
        $review = 'admin' === $user && in_array($env, ['staging', 'development', 'local'], true);
        check("pricing gate: pending file, $env / $user -> " . ($review ? 'review (all rows, marked)' : 'nothing shown'), $review ? (false === $pv['public_mode'] && count($pv['models']) > 0) : (true === $pv['public_mode'] && [] === $pv['models'] && true === $pv['withheld']));
    }
}
unset($GLOBALS['user']);
check('review mode: unknown, empty or non-string environments and non-true capability results are never review', !omochix_labs_pricing_review_allowed('bogus', true) && !omochix_labs_pricing_review_allowed('', true) && !omochix_labs_pricing_review_allowed(null, true) && !omochix_labs_pricing_review_allowed('Staging', true) && !omochix_labs_pricing_review_allowed('staging', 1) && !omochix_labs_pricing_review_allowed('staging', 'yes') && !omochix_labs_pricing_review_allowed('production', true) && omochix_labs_pricing_review_allowed('staging', true));
$nc = omochix_labs_review_nocache_headers();
check('review mode: responses are no-store / private / noindex', str_contains($nc['Cache-Control'], 'no-store') && str_contains($nc['Cache-Control'], 'private') && 'no-cache' === $nc['Pragma'] && str_contains($nc['X-Robots-Tag'], 'noindex'));
$GLOBALS['env'] = 'local'; $GLOBALS['user'] = 'admin';
$GLOBALS['hooks']['litespeed_control_set_nocache'] = [[10, static function () { $GLOBALS['ls_nocache'] = true; }, 1]];
labs_request('token-cost-calculator');
do_action('template_redirect');
check('review mode: the review page disables page caching (DONOTCACHEPAGE, LiteSpeed no-cache)', defined('DONOTCACHEPAGE') && true === ($GLOBALS['ls_nocache'] ?? false));
unset($GLOBALS['hooks']['litespeed_control_set_nocache'], $GLOBALS['ls_nocache']);
$GLOBALS['user'] = 'anon';
labs_request('token-cost-calculator');
$pubHtml = render(static function () { get_template_part('template-parts/labs/tool', null, ['tool' => omochix_labs_get_tool('token-cost-calculator')]); });
check('public mode: an anonymous visitor on a local site gets no unapproved price, id or review banner', 0 === preg_match_all('/data-labs-model(?:>| checked>)/', $pubHtml) && !str_contains($pubHtml, 'gpt-6-astra') && !str_contains($pubHtml, 'claude-opus-5-5') && !str_contains($pubHtml, 'レビュー表示') && str_contains($pubHtml, '料金データの確認が完了するまで'));
$GLOBALS['env'] = 'production'; $GLOBALS['user'] = 'admin';
labs_request('token-cost-calculator');
check('public mode: production never reviews, even for an administrator', true === omochix_labs_get_pricing()['public_mode'] && [] === omochix_labs_get_pricing()['models']);
unset($GLOBALS['user']);
$GLOBALS['env'] = 'production';
labs_request('token-cost-calculator');
$GLOBALS['enqueued'] = ['styles' => [], 'scripts' => [], 'inline' => []];
do_action('wp_enqueue_scripts');
$inline = implode('', $GLOBALS['enqueued']['inline']['omochix-labs-shared']);
check('pricing gate: no price reaches the production page config', str_contains($inline, '"models":[]') && !str_contains($inline, 'platform.claude.com'));
$html = render(static function () { get_template_part('template-parts/labs/tool', null, ['tool' => omochix_labs_get_tool('token-cost-calculator')]); });
check('pricing gate: production page explains the withheld list and shows no prices', str_contains($html, '料金データの確認が完了するまで') && 0 === preg_match_all('/data-labs-model(?:>| checked>)/', $html) && !str_contains($html, 'platform.claude.com/docs'));
unset($GLOBALS['env']);
labs_request();

// --------------------------------------- leak check: production page + config
// A throw-away theme root: the real templates and assets, a pricing file with
// the global review and the three Anthropic rows approved.
$leakTheme = sys_get_temp_dir() . '/omx-labs-leak-' . getmypid();
@mkdir($leakTheme . '/inc/labs-data', 0777, true);
foreach (['assets', 'template-parts'] as $dir) { @symlink($GLOBALS['THEME'] . '/' . $dir, $leakTheme . '/' . $dir); }
file_put_contents($leakTheme . '/inc/labs-data/pricing.json.php', "<?php exit; ?>\n" . json_encode($anth, JSON_UNESCAPED_UNICODE));
$realTheme2 = $GLOBALS['THEME']; $GLOBALS['THEME'] = $leakTheme;
$unapproved = array_values(array_filter($pricing_raw['models'], static fn($m) => 'anthropic' !== $m['provider']));
$needles = array_unique(array_merge(array_column($unapproved, 'id'), array_column($unapproved, 'label'), ['developers.openai.com', 'aistudio.google.com']));
foreach (['production' => true, 'staging' => false] as $env => $isProd) {
    $GLOBALS['env'] = $env;
    $GLOBALS['user'] = 'admin'; // the strongest case: even an administrator on production gets approved rows only
    labs_request('token-cost-calculator');
    $GLOBALS['enqueued'] = ['styles' => [], 'scripts' => [], 'inline' => []];
    do_action('wp_enqueue_scripts');
    $cfg = implode('', $GLOBALS['enqueued']['inline']['omochix-labs-shared'] ?? []);
    $page = render(static function () { get_template_part('template-parts/labs/tool', null, ['tool' => omochix_labs_get_tool('token-cost-calculator')]); });
    $leaks = array_values(array_filter($needles, static fn($n) => str_contains($cfg, $n) || str_contains($page, $n) || str_contains($cfg, str_replace('.', '\\u002e', $n))));
    if ($isProd) {
        check('leak: production page and inline config carry no unapproved model, label or source', [] === $leaks);
        check('leak: production shows the three approved Anthropic rows', 3 === preg_match_all('/data-labs-model(?:>| checked>)/', $page) && str_contains($cfg, 'claude-opus-5-5') && !str_contains($page, '承認待ち') && !str_contains($page, '保留'));
        check('leak: production config lists hidden rows only as counts', str_contains($cfg, '"row_held":5') && str_contains($cfg, '"row_pending":5'));
    } else {
        check('leak: staging, administrator (review mode) lists every row with its state', [] !== $leaks && 11 === preg_match_all('/data-labs-model(?:>| checked>)/', $page) && str_contains($page, '承認待ち') && str_contains($page, '保留'));
    }
}
$GLOBALS['env'] = 'staging'; $GLOBALS['user'] = 'subscriber';
labs_request('token-cost-calculator');
$GLOBALS['enqueued'] = ['styles' => [], 'scripts' => [], 'inline' => []];
do_action('wp_enqueue_scripts');
$subCfg = implode('', $GLOBALS['enqueued']['inline']['omochix-labs-shared'] ?? []);
$subPage = render(static function () { get_template_part('template-parts/labs/tool', null, ['tool' => omochix_labs_get_tool('token-cost-calculator')]); });
check('leak: staging, ordinary logged-in user gets approved rows only (no unapproved id, label or source)', [] === array_values(array_filter($needles, static fn($n) => str_contains($subCfg, $n) || str_contains($subPage, $n))) && 3 === preg_match_all('/data-labs-model(?:>| checked>)/', $subPage));
unset($GLOBALS['env'], $GLOBALS['user']);
$GLOBALS['THEME'] = $realTheme2;
unlink($leakTheme . '/inc/labs-data/pricing.json.php'); @rmdir($leakTheme . '/inc/labs-data'); @rmdir($leakTheme . '/inc');
foreach (['assets', 'template-parts'] as $dir) { @unlink($leakTheme . '/' . $dir); }
@rmdir($leakTheme);
labs_request();

// --------------------------------------------------------------- templates
$GLOBALS['posts'] = [];
labs_request();
$html = render(static function () { get_template_part('template-parts/labs/index'); });
check('render list: h1, search, categories, 4 cards, count', str_contains($html, '<h1>OmochiX Labs</h1>') && str_contains($html, 'role="search"') && 4 === substr_count($html, 'data-labs-card ') && 4 === substr_count($html, 'data-labs-cat=') && str_contains($html, '4件のツール'));
check('render list: search form is JavaScript-only and category links carry no search words', str_contains($html, 'data-labs-search hidden>') && !str_contains($html, '?q=') && !str_contains($html, '&amp;q='));
check('render list: card headings are h2 and link to the tool', str_contains($html, '<h2 class="labs-card__title">') && str_contains($html, 'href="https://example.test/labs/json-formatter/"'));
labs_request(null, ['cat' => 'dev', 'q' => '<script>alert(1)</script>']);
$html = render(static function () { get_template_part('template-parts/labs/index'); });
check('render list: server-side filter hides non-matching cards and shows the empty state', 4 === substr_count($html, 'data-labs-card ') && 4 === substr_count($html, '" hidden>') && str_contains($html, 'data-labs-empty>') && str_contains($html, '0件のツール'));
check('render list: query is escaped', !str_contains($html, '<script>alert') && str_contains($html, 'alert(1)'));
foreach (array_keys(omochix_labs_get_tools()) as $slug) {
    labs_request($slug);
    $tool = omochix_labs_get_tool($slug);
    $err = '';
    set_error_handler(static function ($no, $msg) use (&$err) { $err .= $msg . ';'; return true; });
    $html = render(static function () use ($tool) { get_template_part('template-parts/labs/tool', null, ['tool' => $tool]); });
    restore_error_handler();
    check("render $slug: no PHP notices", '' === $err);
    check("render $slug: one h1, workspace, status and alert regions", 1 === substr_count($html, '<h1') && str_contains($html, 'data-labs-tool="' . $slug . '"') && str_contains($html, 'role="status"') && str_contains($html, 'role="alert"'));
    check("render $slug: breadcrumb Home > Labs > tool", str_contains($html, '<li aria-current="page">' . esc_html($tool['name']) . '</li>'));
    check("render $slug: FAQ rendered as details", count($tool['faq']) === substr_count($html, '<details class="labs-faq__item">'));
    check("render $slug: no missing template parts", !str_contains($html, '<!-- missing'));
    check("render $slug: no inline scripts or event handlers", !preg_match('/<script|\son[a-z]+=/i', $html));
    check("render $slug: every form control has a label", (static function ($html) {
        preg_match_all('/<(?:input|select|textarea)\b[^>]*\bid="([^"]+)"/', $html, $m);
        foreach ($m[1] as $id) { if (!str_contains($html, 'for="' . $id . '"')) { return false; } }
        return count($m[1]) > 0;
    })($html));
    $guide = render(static function () use ($tool) { get_template_part('template-parts/labs/content/' . $tool['slug'], null, ['tool' => $tool, 'part' => 'guide']); });
    check("render $slug: guide has at least 1,200 characters of explanation", mb_strlen(trim(preg_replace('/\s+/u', ' ', strip_tags($guide)))) >= 1200);
    check("render $slug: external links are noopener noreferrer", !preg_match('/<a [^>]*href="https:\/\/(?!example\.test)[^"]*"(?![^>]*rel="noopener noreferrer)/', $html));
}
$GLOBALS['user'] = 'admin'; // review mode on a local site
labs_request('token-cost-calculator');
$html = render(static function () { get_template_part('template-parts/labs/tool', null, ['tool' => omochix_labs_get_tool('token-cost-calculator')]); });
check('render cost: review mode shows the administrator banner', str_contains($html, 'レビュー表示（管理者のみ・この環境のみ）'));
check('render cost: estimate disclaimer, standard tier and cache-write notes, model checkboxes', str_contains($html, '目安の計算です。') && str_contains($html, '実際の請求額を保証するものではありません') && str_contains($html, '標準ティア') && str_contains($html, 'キャッシュ書き込みの料金は計算に含みません') && str_contains($html, '掲載するモデルと価格の条件') && 11 === preg_match_all('/data-labs-model(?:>| checked>)/', $html));
check('render cost: price table lists check dates and official links', 11 === substr_count($html, '2026-11-04</span>') + substr_count($html, '2026-11-04 / ') && str_contains($html, 'href="https://platform.claude.com/docs/en/about-claude/pricing"'));
$GLOBALS['today_override'] = '2027-03-01';
labs_reset_statics();
$html = render(static function () { get_template_part('template-parts/labs/tool', null, ['tool' => omochix_labs_get_tool('token-cost-calculator')]); });
check('render cost: stale prices are not shown at all', 0 === preg_match_all('/data-labs-model(?:>| checked>)/', $html) && !str_contains($html, 'platform.claude.com') && str_contains($html, '確認済みの価格がありません'));
unset($GLOBALS['user']);
unset($GLOBALS['today_override']);
labs_reset_statics();
$learn = file_get_contents(get_theme_file_path('/page-learn.php'));
check('learn hub: Labs link only when the labs page is published', str_contains($learn, "function_exists('omochix_labs_url') ? omochix_labs_url() : ''") && str_contains($learn, '<?php if ($omochix_labs_url) : ?>'));

// ----------------------------------------------------------- release state
$GLOBALS['THEME'] = $REAL_THEME;
labs_reset_statics();
$shippedCatalog = json_decode(file_get_contents($REAL_THEME . '/assets/data/labs/catalog.json'), true);
check('release state: the shipped catalog keeps the cost calculator in draft (prices not reviewed)', 'draft' === array_column($shippedCatalog['tools'], 'status', 'slug')[LABS_COST_SLUG]);
check('release state: the other three tools are published', ['json-formatter' => 'published', 'text-diff' => 'published', 'robots-txt-generator' => 'published'] === array_diff_key(array_column($shippedCatalog['tools'], 'status', 'slug'), [LABS_COST_SLUG => 1]));
$shipped = labs_release_state_problems($REAL_THEME);
check('release state: shipped theme passes the gate (calculator unreachable, nothing leaks, no unapproved price)', [] === $shipped);
foreach ($shipped as $problem) { echo "  - $problem\n"; }
$published = labs_release_state_problems(labs_published_theme($REAL_THEME));
check('release state: publishing the calculator with unreviewed prices fails the gate', in_array('cost calculator is published but the price review is not approved', $published, true) && in_array('cost calculator is published but rows are still pending', $published, true));
// A draft calculator that still leaks fails too: put its name back into the Learn hub copy.
$leakView = sys_get_temp_dir() . '/omx-labs-leak-' . getmypid();
@mkdir($leakView);
foreach (scandir($REAL_THEME) as $entry) { if ('.' !== $entry && '..' !== $entry && 'page-learn.php' !== $entry) { symlink($REAL_THEME . '/' . $entry, $leakView . '/' . $entry); } }
file_put_contents($leakView . '/page-learn.php', str_replace('JSON整形や差分比較など', 'JSON整形やAPI料金計算など', file_get_contents($REAL_THEME . '/page-learn.php')));
check('release state: a draft calculator mentioned in public copy fails the gate', in_array('page-learn.php mentions "API料金"', labs_release_state_problems($leakView), true));
exec('rm -rf ' . escapeshellarg($leakView));
labs_reset_statics();

// ------------------------------------- rollback: page-labs.php without inc/labs.php
$fallback = static function ($template = null) {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/rollback-fallback.php') . (null === $template ? '' : ' ' . escapeshellarg($template));
    return json_decode((string) shell_exec($cmd), true);
};
$fb = $fallback();
check('rollback: page-labs.php without inc/labs.php answers 404 with the theme\'s 404 template, no-cache, no fatal', is_array($fb) && null === $fb['error'] && ['status:404', 'nocache', '404-template:is_404'] === $fb['log'] && [] === $fb['labs_functions']);
$noGuard = sys_get_temp_dir() . '/omx-labs-noguard-' . getmypid() . '.php';
file_put_contents($noGuard, preg_replace('/\nif \(!function_exists\(\'omochix_labs_current_tool\'\)\) \{.*?\n\}\n/s', "\n", file_get_contents($REAL_THEME . '/page-labs.php'), 1, $stripped));
$fbOld = $fallback($noGuard);
unlink($noGuard);
check('rollback: control — the same template without the guard is the fatal error the guard prevents', 1 === $stripped && is_array($fbOld) && str_contains((string) $fbOld['error'], 'Call to undefined function omochix_labs_current_tool()'));

// ------------------------------------------- release state: script copy
/** A view of $base where only the files in $replace (theme-relative path => contents) differ. */
function labs_variant_theme($base, array $replace) {
    $dir = sys_get_temp_dir() . '/omx-labs-variant-' . getmypid() . '-' . md5(serialize($replace) . $base);
    $link = static function ($from, $to, array $replace, $prefix) use (&$link) {
        @mkdir($to, 0777, true);
        $here = [];
        foreach (array_keys($replace) as $path) {
            if (0 === strpos($path, $prefix)) { $here[explode('/', substr($path, strlen($prefix)))[0]] = true; }
        }
        foreach (scandir($from) as $entry) {
            if ('.' === $entry || '..' === $entry) { continue; }
            if (!isset($here[$entry])) { symlink($from . '/' . $entry, $to . '/' . $entry); }
            elseif (isset($replace[$prefix . $entry])) { file_put_contents($to . '/' . $entry, $replace[$prefix . $entry]); }
            else { $link($from . '/' . $entry, $to . '/' . $entry, $replace, $prefix . $entry . '/'); }
        }
    };
    $link($base, $dir, $replace, '');
    return $dir;
}
$lits = labs_js_string_literals("var a = 'x'; // 'not a string'\n/* \"nor this\" */ var r = /it's \\/ [\"]/g, b = \"y\\n\\u3042\";\nreturn `t\${1}`;");
check('script copy: literals are found with lines; comments and regex literals are skipped; escapes decoded', [[1, 'x'], [2, "y\nあ"], [3, 't${1}']] === $lits);
check('script copy: display copy vs code (keys, slugs, URLs)', labs_is_display_copy('入力例を入れました') && labs_is_display_copy('Copy the result.') && !labs_is_display_copy('token-cost-calculator') && !labs_is_display_copy('labs-diff__code') && !labs_is_display_copy('/labs/x/'));
$diffJs = 'assets/js/labs/ui/text-diff.js';
$fixed  = file_get_contents($REAL_THEME . '/' . $diffJs);
$cases  = [
    'the old sample (B-1)'      => [str_replace('robots.txt生成のツールを公開しています。', 'robots.txt生成・API料金計算を公開しています。', $fixed), 'presents draft tool "token-cost-calculator" as published'],
    'a mention without a claim' => [str_replace('var LABEL = {', "var NOTE = '料金計算は準備中です';\n    var LABEL = {", $fixed), 'names draft tool "token-cost-calculator"'],
    'a link to the draft tool'  => [str_replace('var LABEL = {', "var MORE = '/labs/token-cost-calculator/';\n    var LABEL = {", $fixed), 'links to draft tool "token-cost-calculator"'],
    'a comment (internal note)' => [str_replace('var LABEL = {', "// TODO: API料金計算を公開したら入力例に足す\n    var LABEL = {", $fixed), null],
    'the slug as an identifier' => [str_replace('var LABEL = {', "var SKIP = 'token-cost-calculator';\n    var LABEL = {", $fixed), null],
];
check('script copy: every seeded case changes the file', count(array_unique(array_column($cases, 0))) === count($cases) && !in_array($fixed, array_column($cases, 0), true));
check('script copy: the shipped scripts name no draft tool', [] === labs_release_state_script_problems($shippedCatalog['tools']));
check('script copy: public pages load the Worker and the three tools\' scripts, never the calculator\'s', (static function () {
    $f = labs_public_script_files();
    return in_array('/assets/js/labs/ui/text-diff.js', $f, true) && in_array('/assets/js/labs/worker.js', $f, true) && in_array('/assets/js/labs/core/text.js', $f, true)
        && [] === array_intersect(['/assets/js/labs/ui/token-cost-calculator.js', '/assets/js/labs/core/cost.js', '/assets/js/labs/core/tokens.js'], $f);
})());
labs_reset_statics();
foreach ($cases as $label => [$src, $want]) {
    $view = labs_variant_theme($REAL_THEME, [$diffJs => $src]);
    $got  = labs_release_state_problems($view);
    $hit  = array_values(array_filter($got, static fn($p) => str_starts_with($p, '/' . $diffJs . ':')));
    if (null === $want) {
        check("script copy: $label passes (no false positive)", [] === $got);
    } else {
        check("script copy: $label fails the gate", 1 === count($hit) && str_contains($hit[0], $want));
    }
    foreach ($got as $problem) { echo "    $problem\n"; }
    exec('rm -rf ' . escapeshellarg($view));
    labs_reset_statics();
}
// Once the calculator is published, naming it is correct: the check follows the catalog, not a word list.
$pubView = labs_variant_theme(labs_published_theme($REAL_THEME), [$diffJs => $cases['the old sample (B-1)'][0]]);
check('script copy: naming a published tool is allowed (only the price review then fails)', [] === array_filter(labs_release_state_problems($pubView), static fn($p) => str_starts_with($p, '/assets/')));
exec('rm -rf ' . escapeshellarg($pubView));
labs_reset_statics();

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
