<?php
/**
 * Design Library — HTTP checks against a THROWAWAY local WordPress
 * (never staging or production). Needs the test mu-plugin
 * (fixtures/mu-plugin/omx-design-test.php) and the site folder, whose flag
 * file this script turns on and off, and whose local admin password file
 * (created with the throwaway site) it reads for the logged-in checks.
 *
 *   DL_BASE=http://127.0.0.1:8961 DL_SITE=/path/to/site php tests/design-library/http-check.php
 *
 * Covers: routing / 404 matrix, B1 (enforced demo headers, twice), B9
 * (fail-closed 503), B10 (static files), C1 (the page switch), the demo gate
 * (no demo before the isolation tests pass), SEO basics, and V1 L2-L4
 * (cookies, CSRF and REST checked on their own, with an admin session and
 * no nonce — what a demo could at most send if the sandbox failed).
 */
$base = rtrim((string) getenv('DL_BASE'), '/');
$site = rtrim((string) getenv('DL_SITE'), '/');
if (!preg_match('#^http://127\.0\.0\.1:\d+$#', $base) || !is_dir($site . '/wp')) { fwrite(STDERR, "DL_BASE=http://127.0.0.1:PORT and DL_SITE=<throwaway site dir> required\n"); exit(2); }
$flag = $site . '/design-isolation-test.on';
$pass = 0; $fail = 0;
function check($name, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }
function req($path, array $o = []) {
    global $base;
    $ch = curl_init($base . $path);
    $h = $o['headers'] ?? [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $h]);
    if (isset($o['jar'])) { curl_setopt($ch, CURLOPT_COOKIEFILE, $o['jar']); curl_setopt($ch, CURLOPT_COOKIEJAR, $o['jar']); }
    if (isset($o['method'])) { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $o['method']); if ('HEAD' === $o['method']) { curl_setopt($ch, CURLOPT_NOBODY, true); } }
    if (isset($o['post'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, $o['post']); }
    $raw = (string) curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = []; $setcookie = [];
    foreach (explode("\r\n", substr($raw, 0, $hs)) as $line) {
        if (!strpos($line, ':')) { continue; }
        [$k, $v] = explode(':', $line, 2); $k = strtolower(trim($k));
        if ('set-cookie' === $k) { $setcookie[] = trim($v); }
        $headers[$k][] = trim($v);
    }
    return ['code' => $code, 'h' => $headers, 'cookies' => $setcookie, 'body' => substr($raw, $hs)];
}
function hv($r, $k) { return implode(' | ', $r['h'][$k] ?? []); }
function wp_php($code) { global $site; $out = shell_exec('cd ' . escapeshellarg($site) . ' && php -r ' . escapeshellarg('require "wp/wp-load.php"; ' . $code) . ' 2>&1'); return trim((string) $out); }

$items  = ['liquid-glass', 'minimal-editorial', '3d-interactive', 'bento-saas'];
$videos = ['logo-reveal-short', 'data-chart-explainer'];
touch($flag);

// ------------------------------------------------------------ routing
$matrix = [
    ['/design/', 200], ['/design/web/', 200], ['/design/web/no-such-item/', 404], ['/design/web/Bad_Slug/', 404],
    ['/design/app/', 404], ['/design/components/', 404], ['/design/app/liquid-glass/', 404], ['/design/components/liquid-glass/', 404],
    ['/design/guides/', 404], ['/design/hosting/', 404], ['/design/demo/', 404], ['/design/demo/web/', 404],
    ['/design/demo/web/no-such-item/', 404], ['/design/demo/app/liquid-glass/', 404], ['/design/demo/components/liquid-glass/', 404],
    ['/design-sitemap.xml', 404], ['/design/video/', 200], ['/design/demo/video/', 404], ['/design/video/liquid-glass/', 404], ['/design/web/logo-reveal-short/', 404],
    // internal query vars from the query string are refused (review finding 1 and 3)
    ['/design/web/liquid-glass/?omx_design_demo=1', 404], ['/design/web/?omx_design_slug=liquid-glass', 404], ['/design/?omx_design_kind=web', 404],
    ['/design/video/logo-reveal-short/?omx_design_demo=1', 404],
];
foreach ($items as $s) { $matrix[] = ["/design/web/$s/", 200]; $matrix[] = ["/design/demo/web/$s/", 200]; }
foreach ($videos as $s) { $matrix[] = ["/design/video/$s/", 200]; $matrix[] = ["/design/demo/video/$s/", 404]; $matrix[] = ["/design/demo/web/$s/", 404]; }
$q = req('/?omx_design_kind=web&omx_design_slug=liquid-glass&omx_design_demo=1');
check('route: query-string demo vars without the /design/demo/ path are not a demo', !isset($q['h']['content-security-policy']) && false === strpos($q['body'], 'Aerolume'));
foreach ($matrix as [$p, $want]) {
    $r = req($p);
    check("route $p -> $want", $r['code'] === $want, (string) $r['code']);
}
$top = req('/design/');
check('top: Web and video sections, 6 works (+ the test probe), every loop preload="none"', false !== strpos($top['body'], 'id="dl-section-web"') && false !== strpos($top['body'], 'id="dl-section-video"') && 6 <= substr_count($top['body'], 'data-dl-card') && 6 === substr_count($top['body'], 'data-dl-video') && 6 === substr_count($top['body'], 'preload="none"'));
check('top: filters for medium, use case, motion type, technology (no style/motion selects any more)', false !== strpos($top['body'], 'name="medium"') && false !== strpos($top['body'], 'name="use_case"') && false !== strpos($top['body'], 'name="motion_type"') && false !== strpos($top['body'], 'name="technology"') && false === strpos($top['body'], 'name="style"'));
$tf = req('/design/?medium=video&use_case=sns-short');
check('top filtered on the server: only Logo Reveal Short visible, noindex, canonical is the top page', 1 === preg_match_all('#<li class="dl-card[^"]*" data-dl-card(?:(?!hidden>).)*?>\s*<article#s', $tf['body']) && false !== strpos($tf['body'], 'noindex') && false !== strpos($tf['body'], 'rel="canonical" href="' . $base . '/design/"'));
$vl = req('/design/video/');
check('video list: 2 works, a play button each, no live-demo link', 2 === substr_count($vl['body'], 'data-dl-video') && false === strpos($vl['body'], '/design/demo/'));
$list = req('/design/web/');
check('list: 4 cards (+ the test probe), each with poster, loop sources and a live-demo link', 4 <= substr_count($list['body'], 'data-dl-card') && 4 === substr_count($list['body'], 'data-dl-video') && 4 <= substr_count($list['body'], '/design/demo/web/'));
check('list: Report-Only CSP + X-Frame-Options + nosniff on the page', false !== strpos(hv($list, 'content-security-policy-report-only'), "frame-src 'self'") && 'SAMEORIGIN' === hv($list, 'x-frame-options') && 'nosniff' === hv($list, 'x-content-type-options'));
check('list: no loop is preloaded (preload="none")', 4 === substr_count($list['body'], 'preload="none"'));
$filtered = req('/design/web/?motion_type=pointer&q=%3Cscript%3E');
check('filtered list: noindex and the query is escaped', false !== strpos($filtered['body'], 'noindex') && false === strpos($filtered['body'], '<script>alert') && false === strpos($filtered['body'], 'value="<script>'));
$detail = req('/design/web/3d-interactive/');
check('detail: iframe sandbox="allow-scripts" only, lazy, titled, no-referrer (B2 markup)', 1 === preg_match('#<iframe[^>]*data-dl-demo[^>]*sandbox="allow-scripts"[^>]*loading="lazy"[^>]*referrerpolicy="no-referrer"[^>]*title="[^"]+"#', $detail['body']) && false === strpos($detail['body'], 'allow-same-origin'));
check('detail: unique title', false !== strpos($detail['body'], '<title>3D Interactive — Web デザインの見本'));
check('detail: canonical is the detail URL even without Slim SEO (review finding 2)', false !== strpos($detail['body'], 'rel="canonical" href="' . $base . '/design/web/3d-interactive/"') && false !== strpos($list['body'], 'rel="canonical" href="' . $base . '/design/web/"'));
check('detail (Web): steps, prompt with copy, code (HTML/CSS/JS), verification, credits, Motion Gallery links', false !== strpos($detail['body'], 'id="dl-d-steps"') && false !== strpos($detail['body'], 'data-dl-copy="dl-prompt-text"') && 3 === substr_count($detail['body'], 'class="dl-codebox"') && false !== strpos($detail['body'], 'data-dl-verification="verified-local"') && false !== strpos($detail['body'], '/learn/motion/orbit/'));
check('detail (Web): the code is escaped text (no live markup from the demo)', false === strpos($detail['body'], '<canvas class="kd-canvas"') && false !== strpos($detail['body'], '&lt;canvas class=&quot;kd-canvas&quot;'));
foreach ($videos as $s) {
    $vd = req("/design/video/$s/");
    $body = $vd['body'];
    check("detail video $s: <video controls preload=none> with webm + mp4, no iframe, no demo link, no code block", 1 === preg_match('#<video class="dl-stage__video" controls playsinline muted preload="none"#', $body) && false !== strpos($body, "videos/$s.webm") && false !== strpos($body, "videos/$s.mp4") && false === strpos($body, '<iframe') && false === strpos($body, '/design/demo/') && false === strpos($body, 'dl-codebox'));
    check("detail video $s: steps, prompt, verification and credits shown", false !== strpos($body, 'id="dl-d-steps"') && false !== strpos($body, 'id="dl-prompt-text"') && false !== strpos($body, 'data-dl-verification="verified-local"') && false !== strpos($body, '自作・オリジナル'));
    check("detail video $s: no create / generate / Motion Studio button", !preg_match('#(Motion Studio で作る|この作品を作る|生成する|data-dl-motion-studio|localhost:3000)#u', $body));
    $f = req("/wp-content/themes/omochix-theme/assets/design/videos/$s.mp4", ['method' => 'HEAD']);
    check("video file $s.mp4 is served as video/mp4", 200 === $f['code'] && 0 === strpos(hv($f, 'content-type'), 'video/mp4'));
}

// ------------------------------------------------------------ B1 headers
foreach ($items as $s) {
    $a = req("/design/demo/web/$s/"); $b = req("/design/demo/web/$s/");
    $csp = hv($a, 'content-security-policy');
    $ok = 0 === strpos($csp, 'sandbox allow-scripts;') && !preg_match('/allow-(same-origin|top-navigation|forms|popups|modals)/', $csp)
        && false !== strpos($csp, "frame-ancestors 'self'") && false !== strpos($csp, "connect-src 'none'") && false !== strpos($csp, "default-src 'none'")
        && !isset($a['h']['content-security-policy-report-only']) && 1 === count($a['h']['content-security-policy']);
    check("B1 $s: enforced CSP with sandbox allow-scripts, nothing wider, frame-ancestors self, connect none", $ok, $csp);
    check("B1 $s: second request has the same headers", hv($a, 'content-security-policy') === hv($b, 'content-security-policy') && hv($a, 'cache-control') === hv($b, 'cache-control'));
    check("B1 $s: nosniff, noindex, no-referrer, no Set-Cookie, no Link header", 'nosniff' === hv($a, 'x-content-type-options') && false !== strpos(hv($a, 'x-robots-tag'), 'noindex') && 'no-referrer' === hv($a, 'referrer-policy') && !$a['cookies'] && !isset($a['h']['link']));
    preg_match_all('#<script>(.*?)</script>#s', $a['body'], $m);
    $hashes = array_map(static function ($x) { return "'sha256-" . base64_encode(hash('sha256', $x, true)) . "'"; }, $m[1]);
    preg_match("/script-src ([^;]+)/", $csp, $sm);
    check("B1 $s: script-src lists exactly the hashes of the inline scripts", $sm && trim($sm[1]) === implode(' ', $hashes));
}
$hs = req('/design/demo/web/bento-saas/', ['headers' => ['X-OMX-Test-Hsts: 1']]);
check('demo keeps headers it does not own (HSTS) and still sends its own policy (review finding 5)', 'max-age=60' === hv($hs, 'strict-transport-security') && 0 === strpos(hv($hs, 'content-security-policy'), 'sandbox allow-scripts;'));
$nf = req('/design/demo/web/no-such-item/');
check('B1 404: unknown demo still gets the enforced sandbox CSP and an empty body', 0 === strpos(hv($nf, 'content-security-policy'), 'sandbox allow-scripts;') && '' === $nf['body'] && 'no-store' === hv($nf, 'cache-control'));
$head = req('/design/demo/web/liquid-glass/', ['method' => 'HEAD']);
check('B1 HEAD: same policy, no body', 200 === $head['code'] && 0 === strpos(hv($head, 'content-security-policy'), 'sandbox allow-scripts;'));

// ------------------------------------------------------------ B9 fail-closed
foreach (['/design/demo/web/liquid-glass/', '/design/demo/web/no-such-item/'] as $p) {
    $r = req($p, ['headers' => ['X-OMX-Test-Block-Headers: 1']]);
    check("B9 $p: headers cannot be sent -> 503 with an empty body", 503 === $r['code'] && '' === $r['body']);
}

// ------------------------------------------------------------ B10 static files
foreach ($items as $s) {
    $r = req("/wp-content/themes/omochix-theme/assets/design/demos/$s/demo.php");
    check("B10 $s demo.php opened directly prints nothing", '' === trim($r['body']), (string) $r['code']);
}
$js = req('/wp-content/themes/omochix-theme/assets/design/runtime/demo-runtime.js');
check('B10 runtime JS is served as script text, not HTML', false === stripos(hv($js, 'content-type'), 'html'));
foreach (['posters/isolation-probe.avif', 'demos/isolation-probe/demo.php', 'posters/draft-item.avif', 'demos/index.html'] as $p) {
    $r = req('/wp-content/themes/omochix-theme/assets/design/' . $p);
    if (301 === $r['code'] && isset($r['h']['location'])) { $r = req(parse_url($r['h']['location'][0], PHP_URL_PATH)); } // WordPress adds a slash first
    // A missing static path falls through to WordPress (404 or its own page); what matters is that the file is not there.
    $served = preg_match('#^(image|video)/#', hv($r, 'content-type')) || false !== strpos($r['body'], 'Isolation probe') || false !== strpos($r['body'], '__probe');
    check("B10 $p is not in the theme and not served", !file_exists(dirname(__DIR__, 2) . '/wp-theme/omochix-theme/assets/design/' . $p) && !$served, $r['code'] . ' ' . hv($r, 'content-type'));
}

// ------------------------------------------------------------ demo gate (every item treated as unverified)
unlink($flag);
$unverified = $site . '/design-isolation-unverified.on';
touch($unverified);
foreach ($items as $s) {
    check("gate $s: demo is a 404 while isolation is unverified", 404 === req("/design/demo/web/$s/")['code']);
}
$d0 = req('/design/web/liquid-glass/');
check('gate: detail shows the poster and a note instead of an iframe', false === strpos($d0['body'], '<iframe') && false !== strpos($d0['body'], 'dl-stage__poster'));
check('gate: list has no live-demo link', false === strpos(req('/design/web/')['body'], '/design/demo/web/'));
foreach (['/design/web/liquid-glass/', '/design/video/logo-reveal-short/'] as $p) {
    $u = req($p)['body'];
    check("verification label $p: an unverified work says 未検証 and never 検証済み", false !== strpos($u, 'data-dl-verification="unverified"') && false === strpos($u, '検証済み'));
}
unlink($unverified);
check('gate: with the shipped catalog (verified-local) the demos serve on this local site', 200 === req('/design/demo/web/liquid-glass/')['code']);
touch($flag);

// ------------------------------------------------------------ rewrite rules restored after a rollback
// Simulates: rollback to a theme without the Design Library, permalinks regenerated there, this theme
// deployed again (the version option still matches, the stored rules no longer contain ours).
wp_php('$r = get_option("rewrite_rules"); foreach (array_keys($r) as $k) { if (0 === strpos($k, "^design/")) { unset($r[$k]); } } update_option("rewrite_rules", $r);');
// SHORTINIT loads options without themes or init hooks, so it reads the stored rules without healing them.
$count_rules = static function () { global $site; return trim((string) shell_exec('cd ' . escapeshellarg($site) . ' && php -r ' . escapeshellarg('define("SHORTINIT", true); require "wp/wp-load.php"; echo count(array_filter(array_keys((array) get_option("rewrite_rules")), function ($k) { return 0 === strpos($k, "^design/"); }));'))); };
check('rewrite: our rules were removed for the test', '0' === $count_rules());
check('rewrite: the first request restores them (no 404 window)', 200 === req('/design/web/')['code'] && 200 === req('/design/video/logo-reveal-short/')['code']);
check('rewrite: the stored rules contain ours again', '3' === $count_rules());

// ------------------------------------------------------------ C1 page switch
$id = (int) wp_php('echo get_page_by_path("design")->ID;');
wp_php("wp_update_post(['ID' => $id, 'post_status' => 'draft']);");
foreach (['/design/', '/design/web/', '/design/web/liquid-glass/', '/design/demo/web/liquid-glass/'] as $p) {
    check("C1 page draft: $p -> 404", 404 === req($p)['code']);
}
check('C1 page draft: demo 404 still carries the sandbox CSP', 0 === strpos(hv(req('/design/demo/web/liquid-glass/'), 'content-security-policy'), 'sandbox allow-scripts;'));
wp_php("wp_update_post(['ID' => $id, 'post_status' => 'publish']);");
check('C1 page published again: list is back', 200 === req('/design/web/')['code']);

// ------------------------------------------------------------ V1 L2 cookies
$jar = tempnam(sys_get_temp_dir(), 'dljar');
$pw  = trim((string) @file_get_contents($site . '/.admin-pass'));
req('/wp-login.php', ['jar' => $jar]);
$login = req('/wp-login.php', ['jar' => $jar, 'post' => http_build_query(['log' => 'omxadmin', 'pwd' => $pw, 'wp-submit' => 'Log In', 'testcookie' => '1', 'redirect_to' => '/wp-admin/']), 'headers' => ['Cookie: wordpress_test_cookie=WP%20Cookie%20check']]);
unset($pw);
$auth = array_filter($login['cookies'], static function ($c) { return 0 === strpos($c, 'wordpress_'); });
check('L2: logged in (local test admin)', 302 === $login['code'] && count($auth) >= 2);
$session = array_filter($auth, static function ($c) { return 0 !== strpos($c, 'wordpress_test_cookie'); });
$names = array_map(static function ($c) { return strstr($c, '=', true) . (false !== stripos($c, 'httponly') ? ' (HttpOnly)' : ' (no HttpOnly)'); }, $session);
check('L2: every WordPress auth cookie is HttpOnly', $session && !array_filter($names, static function ($n) { return false !== strpos($n, 'no HttpOnly'); }), implode(', ', $names));
echo "INFO L2: Secure/SameSite not checked here (local http); record them on the HTTPS staging/production run.\n";
foreach ($items as $s) {
    $anon = req("/design/demo/web/$s/"); $adm = req("/design/demo/web/$s/", ['jar' => $jar]);
    check("L2 $s: demo body is identical logged in and out", hash('sha256', $anon['body']) === hash('sha256', $adm['body']) && '' !== $anon['body']);
    check("L2 $s: no nonce, REST settings, admin bar or user name in the demo", !preg_match('/_wpnonce|wpApiSettings|X-WP-Nonce|wp-admin-bar|omxadmin|wp-json/i', $adm['body']));
}
check('L2: the logged-in detail page has an admin bar, the demo inside it does not', false !== strpos(req('/design/web/liquid-glass/', ['jar' => $jar])['body'], 'wpadminbar'));

// ------------------------------------------------------------ V1 L3 CSRF (admin cookie, no nonce)
$victim = (int) wp_php('echo wp_insert_post(["post_title" => "L3 victim", "post_status" => "publish"]);');
$tries = [
    'trash a post (GET)'           => ['/wp-admin/post.php?action=trash&post=' . $victim, []],
    'delete a post (admin-ajax)'   => ['/wp-admin/admin-ajax.php', ['post' => 'action=delete-post&id=' . $victim]],
    'admin-post action'            => ['/wp-admin/admin-post.php', ['post' => 'action=omx_probe']],
    'switch theme (GET)'           => ['/wp-admin/themes.php?action=activate&stylesheet=twentytwentyfour', []],
    'deactivate Core (GET)'        => ['/wp-admin/plugins.php?action=deactivate&plugin=omochix-core%2Fomochix-core.php', []],
    'change an option (POST)'      => ['/wp-admin/options.php', ['post' => 'option_page=general&action=update&blogname=pwned']],
];
foreach ($tries as $label => [$p, $o]) {
    $r = req($p, ['jar' => $jar] + $o);
    $ok = in_array($r['code'], [400, 403], true) || '-1' === trim($r['body']) || '0' === trim($r['body']) || false !== stripos($r['body'], 'link you followed has expired') || false !== stripos($r['body'], 'リンクの有効期限');
    check("L3 $label without nonce is refused", $ok, $r['code'] . ' ' . substr(trim(strip_tags($r['body'])), 0, 80));
}
check('L3: state unchanged (post published, theme, Core active, blog name)', 'publish' === wp_php("echo get_post_status($victim);") && 'omochix-theme' === wp_php('echo get_stylesheet();') && '1' === wp_php('echo (int) is_plugin_active("omochix-core/omochix-core.php");') && 'OmochiX' === wp_php('echo get_option("blogname");'));
$out = req('/wp-login.php?action=logout', ['jar' => $jar]);
check('L3: logout link without nonce asks for confirmation and keeps the session', 200 === req('/wp-admin/', ['jar' => $jar])['code']);
wp_php("wp_delete_post($victim, true);");

// ------------------------------------------------------------ V1 L4 REST (admin cookie, no nonce)
$me = req('/wp-json/wp/v2/users/me', ['jar' => $jar]);
check('L4 users/me with cookie but no nonce -> 401 rest_not_logged_in', 401 === $me['code'] && false !== strpos($me['body'], 'rest_not_logged_in'));
$post = req('/wp-json/wp/v2/posts', ['jar' => $jar, 'method' => 'POST', 'post' => '{"title":"x"}', 'headers' => ['Content-Type: application/json']]);
check('L4 create post with cookie but no nonce -> 401/403', in_array($post['code'], [401, 403], true));
$index = json_decode(req('/wp-json/')['body'], true);
$routes = array_keys($index['routes'] ?? []);
check('L4 no Design Library REST namespace or route', !array_filter($routes, static function ($r) { return false !== stripos($r, 'design'); }) && !in_array('omochix/design', $index['namespaces'] ?? [], true));
$writes = 0; $bad = [];
foreach ($index['routes'] ?? [] as $route => $def) {
    if (0 !== strpos($route, '/omochix') || false !== strpos($route, '(?P')) { continue; }
    foreach ($def['endpoints'] ?? [] as $ep) {
        foreach (array_intersect($ep['methods'] ?? [], ['POST', 'PUT', 'PATCH', 'DELETE']) as $m) {
            $writes++;
            $r = req('/wp-json' . $route, ['jar' => $jar, 'method' => $m, 'post' => '{}', 'headers' => ['Content-Type: application/json']]);
            if (!in_array($r['code'], [400, 401, 403], true)) { $bad[] = "$m $route {$r['code']}"; }
        }
    }
}
check("L4 OmochiX Core write routes ($writes) refuse a cookie without nonce", !$bad, implode(', ', $bad));
@unlink($jar);
@unlink($flag);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
