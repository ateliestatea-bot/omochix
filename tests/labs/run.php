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
check('rewrite: one top rule for /labs/{slug}/', [['^labs/([a-z0-9-]+)/?$', 'index.php?pagename=labs&omx_lab=$matches[1]', 'top']] === $GLOBALS['rewrite']);
check('rewrite: flushed once per version', 1 === $GLOBALS['flushed'] && '1' === get_option('omochix_labs_rewrite_version'));
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
foreach (["default-src 'self'", "worker-src 'self'", "object-src 'none'", "frame-ancestors 'self'", "base-uri 'self'", "form-action 'self'", "connect-src 'self' https://www.googletagmanager.com"] as $directive) {
    check("headers: CSP has $directive", false !== strpos($csp, $directive));
}
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
$pricing_raw = json_decode(file_get_contents(omochix_labs_data_file('pricing')), true);
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
labs_request('token-cost-calculator');
$html = render(static function () { get_template_part('template-parts/labs/tool', null, ['tool' => omochix_labs_get_tool('token-cost-calculator')]); });
check('render cost: estimate disclaimer and model checkboxes', str_contains($html, '目安の計算です。') && str_contains($html, '実際の請求額を保証するものではありません') && 11 === preg_match_all('/data-labs-model(?:>| checked>)/', $html));
check('render cost: price table lists check dates and official links', 11 === substr_count($html, '2026-11-04</span>') + substr_count($html, '2026-11-04 / ') && str_contains($html, 'href="https://platform.claude.com/docs/en/about-claude/pricing"'));
$GLOBALS['today_override'] = '2027-03-01';
labs_reset_statics();
$html = render(static function () { get_template_part('template-parts/labs/tool', null, ['tool' => omochix_labs_get_tool('token-cost-calculator')]); });
check('render cost: stale prices are not shown at all', 0 === preg_match_all('/data-labs-model(?:>| checked>)/', $html) && !str_contains($html, 'platform.claude.com') && str_contains($html, '確認済みの価格がありません'));
unset($GLOBALS['today_override']);
labs_reset_statics();
$learn = file_get_contents(get_theme_file_path('/page-learn.php'));
check('learn hub: Labs link only when the labs page is published', str_contains($learn, "function_exists('omochix_labs_url') ? omochix_labs_url() : ''") && str_contains($learn, '<?php if ($omochix_labs_url) : ?>'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
