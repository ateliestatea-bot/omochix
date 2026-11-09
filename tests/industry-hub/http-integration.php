<?php
/**
 * Industry Hub Phase 2 — HTTP checks against a THROWAWAY WordPress with
 * Slim SEO. Not run in CI; never point it at staging or production.
 *
 *   IH_BASE=http://127.0.0.1:8777 php tests/industry-hub/http-integration.php
 *
 * Expects the fixture created by the seed script described in
 * docs/Industry-Hub-Phase2-Design.md §11 (real-estate visible + indexable,
 * restaurant visible but not indexable, beauty hidden, rental a
 * sub-industry of real-estate, the /learn/industries/ page published).
 */
$base = rtrim((string) getenv('IH_BASE'), '/');
if ('' === $base || !preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?$#', $base)) {
    fwrite(STDERR, "IH_BASE must be a local http://127.0.0.1:PORT throwaway site\n");
    exit(2);
}
$pass = 0; $fail = 0;
function check($name, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }
function req($path, $method = 'GET') {
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_NOBODY => 'HEAD' === $method]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = [];
    foreach (explode("\r\n", substr($raw, 0, $hsize)) as $line) { if (strpos($line, ':')) { [$k, $v] = explode(':', $line, 2); $headers[strtolower(trim($k))] = trim($v); } }
    $body = substr($raw, $hsize);
    preg_match("/<meta name=['\"]robots['\"] content=['\"]([^'\"]*)/", $body, $r);
    preg_match("/<link rel=['\"]canonical['\"] href=['\"]([^'\"]*)/", $body, $c);
    preg_match('/<!-- ih-queries: (\d+) -->/', $body, $q);
    return ['code' => $code, 'h' => $headers, 'body' => $body, 'robots' => $r[1] ?? '', 'canonical' => isset($c[1]) ? str_replace($base, '', $c[1]) : '', 'queries' => isset($q[1]) ? (int) $q[1] : -1];
}
function nostore($r) { return false !== stripos($r['h']['cache-control'] ?? '', 'no-store'); }
function noindex($r) { return false !== strpos($r['robots'], 'noindex') && '' === $r['canonical']; }

// ------------------------------------------------------------ status table
$rows = [
    // path, status, note
    ['/learn/industries/', 200, 'index page'],
    ['/learn/industries/real-estate/', 200, 'visible + indexable hub'],
    ['/learn/industries/restaurant/', 200, 'visible, not indexable'],
    ['/learn/industries/beauty/', 404, 'hidden hub (G1 only)'],
    ['/learn/industries/beauty', 404, 'hidden hub without trailing slash: no 301 first'],
    ['/?omx_industry=beauty', 404, 'hidden hub through the query var: no 301 first'],
    ['/learn/industries/no-such/', 404, 'unknown industry'],
    ['/learn/industries/real-estate/page/99/', 404, 'page out of range'],
    ['/learn/industries/real-estate/?purpose=nope', 400, 'unknown purpose'],
    ['/learn/industries/real-estate/?purpose%5B%5D=marketing', 400, 'array value'],
    ['/learn/industries/real-estate/?purpose=marketing&purpose=document', 400, 'duplicate key'],
    ['/learn/industries/real-estate/?q=' . rawurlencode(str_repeat('あ', 61)), 400, 'q too long'],
    ['/learn/industries/real-estate/?sub=izakaya', 400, 'sub of another industry'],
    ['/learn/industries/real-estate/page/99/?purpose=nope', 400, '400 wins over the paged 404'],
    ['/learn/industries/real-estate/?purpose=', 200, 'empty value = not set'],
    ['/learn/industries/real-estate/?utm_source=x&gclid=y', 200, 'tracking keys ignored'],
    ['/learn/industries/real-estate/?purpose=sales', 200, 'valid filter, zero results'],
    ['/learn/industries/real-estate/?purpose=sales&paged=2', 404, 'zero results, page 2'],
    ['/learn/industries/rental/?purpose=nope', 400, 'sub-industry: query validated before the redirect'],
    ['/learn/industries/rental/?sub=rental', 400, 'sub-industry twice'],
    ['/learn/industries/real-estate/feed/', 404, 'industry feed'],
    ['/feed/?omx_industry=real-estate', 404, 'feed narrowed by industry'],
    ['/learn/industries/real-estate/embed/', 404, 'industry embed'],
    ['/learn/industries/?industry=beauty', 400, 'index: hidden industry'],
    ['/learn/industries/?q=' . rawurlencode('紹介'), 200, 'index: search'],
    ['/learn/industries/page/2/', 404, 'index: page 2 without a search'],
];
foreach ($rows as [$path, $status, $note]) {
    $r = req($path);
    check("status $status: $path ($note)", $status === $r['code'], 'got ' . $r['code']);
    if (200 !== $status) {
        check("  no-store on the $status: $path", nostore($r), $r['h']['cache-control'] ?? '');
    }
}
$r = req('/learn/industries/beauty/', 'HEAD');
check('HEAD: a hidden hub is 404 for HEAD too', 404 === $r['code'], (string) $r['code']);
$r = req('/learn/industries/real-estate/?purpose=nope', 'HEAD');
check('HEAD: a bad request is 400 for HEAD too', 400 === $r['code'], (string) $r['code']);

// ---------------------------------------------------------- redirects (§3.3)
$r = req('/learn/industries/rental/');
check('S-1: sub-industry → 302 to the parent with ?sub=', 302 === $r['code'] && preg_match('#/learn/industries/real-estate/\?sub=rental$#', $r['h']['location'] ?? ''), ($r['code'] . ' ' . ($r['h']['location'] ?? '')));
check('S-1: 302 is no-store with X-Robots-Tag noindex', nostore($r) && false !== stripos($r['h']['x-robots-tag'] ?? '', 'noindex'));
$r = req('/learn/industries/rental/?purpose=marketing&utm_source=news');
check('S-2: valid filters and tracking keys carry over', 302 === $r['code'] && false !== strpos($r['h']['location'] ?? '', 'purpose=marketing') && false !== strpos($r['h']['location'] ?? '', 'sub=rental') && false !== strpos($r['h']['location'] ?? '', 'utm_source=news'), $r['h']['location'] ?? '');
$r = req('/learn/industries/rental/page/2/');
check('S-4: paged sub-industry → page 1 of the parent', 302 === $r['code'] && false === strpos($r['h']['location'] ?? '', 'page/2'), $r['h']['location'] ?? '');
$r = req('/learn/industries/Real-Estate/');
check('slug case: /Real-Estate/ → 301 to the lower-case URL', 301 === $r['code'] && preg_match('#/learn/industries/real-estate/$#', $r['h']['location'] ?? ''), $r['code'] . ' ' . ($r['h']['location'] ?? ''));
$r = req('/learn/industries/real-estate/?sub=rental');
check('S-6: ?sub= view is 200, noindex, no canonical', 200 === $r['code'] && noindex($r), $r['robots'] . ' / ' . $r['canonical']);

// ---------------------------------------------------------------- SEO (§8)
$r = req('/learn/industries/real-estate/');
check('index: indexable hub has index robots and a self canonical', 200 === $r['code'] && false === strpos($r['robots'], 'noindex') && '/learn/industries/real-estate/' === $r['canonical'], $r['robots'] . ' / ' . $r['canonical']);
check('title: 「{業種}のAI活用｜…」', (bool) preg_match('#<title>[^<]*real-estate|<title>[^<]*不動産のAI活用#u', $r['body']));
check('description: from the lead', (bool) preg_match('#<meta name="description" content="real-estateのAI活用#u', $r['body']));
check('JSON-LD: one ItemList linked from the page entity, no second CollectionPage', 1 === substr_count($r['body'], '"ItemList"') && 1 >= substr_count($r['body'], '"CollectionPage"') && false !== strpos($r['body'], '#industry-hub-list'));
$r = req('/learn/industries/restaurant/');
check('not indexable (G7 missing): noindex, no canonical', 200 === $r['code'] && noindex($r), $r['robots'] . ' / ' . $r['canonical']);
foreach (['/learn/industries/real-estate/?purpose=marketing', '/learn/industries/real-estate/page/2/', '/learn/industries/real-estate/?purpose=sales', '/learn/industries/?q=x', '/learn/industries/real-estate/?purpose=nope'] as $path) {
    $r = req($path);
    check("noindex + no canonical: $path", noindex($r), $r['code'] . ' ' . $r['robots'] . ' / ' . $r['canonical']);
}
$r = req('/learn/industries/');
check('index page: indexable while one hub is indexable', 200 === $r['code'] && false === strpos($r['robots'], 'noindex'), $r['robots']);

// ---------------------------------------------------------------- sitemap
$tax = req('/sitemap-taxonomy-omx_industry.xml');
check('sitemap: the industry sitemap lists the indexable hub only', 200 === $tax['code'] && false !== strpos($tax['body'], '/learn/industries/real-estate/') && false === strpos($tax['body'], '/restaurant/') && false === strpos($tax['body'], '/beauty/') && false === strpos($tax['body'], '/rental/'), (string) $tax['code']);
$idx = req('/sitemap.xml');
check('sitemap index: no purpose / provider / resource sitemaps', false === strpos($idx['body'], 'omx_purpose') && false === strpos($idx['body'], 'omx_provider_type') && false === strpos($idx['body'], 'omx_resource'));

// --------------------------------------------------------- leaks (visitor)
$list = json_decode(req('/wp-json/wp/v2/industries?per_page=100')['body'], true);
$slugs = is_array($list) ? array_column($list, 'slug') : [];
check('REST list: visible hubs and their sub-industries only', in_array('real-estate', $slugs, true) && in_array('rental', $slugs, true) && in_array('restaurant', $slugs, true) && !in_array('beauty', $slugs, true), implode(',', $slugs));
$beauty_id = null;
foreach ((array) json_decode(req('/wp-json/wp/v2/posts?per_page=100&_fields=id,slug')['body'], true) as $p) { if (($p['slug'] ?? '') === 'ih-news-beauty') { $beauty_post = $p['id']; } }
$single = req('/wp-json/wp/v2/posts/' . ($beauty_post ?? 0));
check('REST post: a post of a hidden industry shows no industry IDs', 200 === $single['code'] && [] === (json_decode($single['body'], true)['industries'] ?? null), substr($single['body'], 0, 80));
check('REST: purposes and providers stay 401 for visitors', 401 === req('/wp-json/wp/v2/business-purposes')['code'] && 401 === req('/wp-json/wp/v2/provider-types')['code']);
check('REST: Industries (case variant) answers the same', req('/wp-json/wp/v2/Industries')['code'] === req('/wp-json/wp/v2/industries')['code']);
$search = req('/?s=' . rawurlencode('美容ニュース'));
check('search without an industry still finds the post (baseline for the probes below)', 200 === $search['code'] && false !== strpos($search['body'], 'ih-news-beauty'));
$hub = req('/learn/industries/real-estate/');
check('post_class: no omx_industry-* classes are printed', false === strpos($hub['body'], 'omx_industry-'));

// ------------------------------------------- review round: leaks and probes
$f = req('/learn/industries/beauty/feed/');
check('feed of a hidden industry: 404 with no items in the body', 404 === $f['code'] && false === strpos($f['body'], '<item>') && false === strpos($f['body'], '美容ニュース'));
$f = req('/feed/?omx_industry=beauty');
check('/feed/?omx_industry=<hidden>: 404 with no items', 404 === $f['code'] && false === strpos($f['body'], '<item>'));
$ts = json_decode(req('/wp-json/wp/v2/search?type=term&subtype=omx_industry&per_page=100&search=')['body'], true);
$titles = is_array($ts) ? array_column($ts, 'title') : [];
check('REST term search: hidden industries are not found', !in_array('美容', $titles, true) && in_array('不動産', $titles, true), implode(',', $titles));
$hiddenCat = req('/?s=' . rawurlencode('美容ニュース') . '&omx_industry=beauty');
$missingCat = req('/?s=' . rawurlencode('美容ニュース') . '&omx_industry=no-such-industry');
check('search + hidden industry is answered like a missing one (no hit either way)', false === strpos($hiddenCat['body'], 'ih-news-beauty') && false === strpos($missingCat['body'], 'ih-news-beauty') && $hiddenCat['code'] === $missingCat['code']);
$tq = req('/?s=' . rawurlencode('美容ニュース') . '&taxonomy=omx_industry&term=beauty');
check('?taxonomy=omx_industry&term=<hidden> does not narrow to the hidden industry either', false === strpos($tq['body'], 'ih-news-beauty'));
$cat = req('/category/uncategorized/?omx_industry=beauty');
$cat2 = req('/category/uncategorized/?omx_industry=no-such-industry');
$cat0 = req('/category/uncategorized/');
// The slug also appears in site-wide widgets, so compare against the unfiltered archive.
check('category archive narrowed by a hidden vs missing industry: same status, same (narrowed) listing', $cat['code'] === $cat2['code'] && substr_count($cat['body'], 'ih-news-beauty') === substr_count($cat2['body'], 'ih-news-beauty') && substr_count($cat['body'], 'ih-news-beauty') < substr_count($cat0['body'], 'ih-news-beauty'), $cat['code'] . '/' . $cat2['code']);
$h = req('/learn/industries/beauty/'); $m = req('/learn/industries/no-such/');
check('404 headers: hidden and missing industries answer the same (status, X-Robots-Tag, Cache-Control)', $h['code'] === $m['code'] && ($h['h']['x-robots-tag'] ?? '') === ($m['h']['x-robots-tag'] ?? '') && ($h['h']['cache-control'] ?? '') === ($m['h']['cache-control'] ?? ''), ($h['h']['x-robots-tag'] ?? '-') . ' vs ' . ($m['h']['x-robots-tag'] ?? '-'));
check('?s= on a visible hub is a 400 (the hub searches with ?q=)', 400 === req('/learn/industries/real-estate/?s=x')['code']);
check('?s= on a hidden hub is a 404 (like a missing one)', 404 === req('/learn/industries/beauty/?s=x')['code'] && 404 === req('/learn/industries/no-such/?s=x')['code']);
check('array query var ?omx_industry[]= answers without a server error', 500 > req('/?omx_industry%5B%5D=a')['code'] && 500 > req('/?s=x&omx_industry%5B%5D=a')['code']);
check('the index page does not name hidden industries', false === strpos(req('/learn/industries/')['body'], '美容'));

// ------------------------------------------------------- existing URLs
foreach (['/', '/learn/', '/learn/web/', '/learn/practice/', '/learn/motion/', '/ai-tools/', '/prompts/', '/learn/practice/ih-lesson/', '/ai-tools/ih-chatgpt/'] as $path) {
    $r = req($path);
    check("existing URL still 200: $path", 200 === $r['code'], (string) $r['code']);
}

// ------------------------------------------------------------ performance
$r = req('/learn/industries/real-estate/');
echo "INFO queries: hub " . $r['queries'] . ", index " . req('/learn/industries/')['queries'] . ", hub ?purpose=marketing " . req('/learn/industries/real-estate/?purpose=marketing')['queries'] . "\n";

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
