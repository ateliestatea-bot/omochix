<?php
/**
 * Design Library unit tests (no WordPress, DB or network):
 *   php tests/design-library/run.php
 *
 * Publish conditions C1-C5 (D0 §4.3) for Web and video works, the demo gate
 * per environment and verification state, the enforced demo CSP, the
 * assembled demo document (hashes, break-out refusal), the demo sources shown
 * as code, filters on the D1.5 axes, related links, and the Motion Studio
 * hand-over (never a link).
 */
require __DIR__ . '/stub.php';
ini_set('error_log', '/dev/null');

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }

$WEB   = ['liquid-glass', 'minimal-editorial', '3d-interactive', 'bento-saas'];
$VIDEO = ['logo-reveal-short', 'data-chart-explainer'];

// ------------------------------------------------------------ C1-C5
stub_page(null);
check('C1: no design page -> nothing is public', !omochix_design_is_public('') && !omochix_design_is_public('web') && !omochix_design_is_public('video') && !omochix_design_is_public('web', 'liquid-glass'));
check('C1: no design page -> no URL, no item, no list', '' === omochix_design_url() && null === omochix_design_get_item('video', 'logo-reveal-short') && [] === omochix_design_get_items(''));
stub_page('draft');
check('C1: draft design page -> nothing is public', !omochix_design_is_public('web') && !omochix_design_is_public('video', 'data-chart-explainer'));
stub_page('private');
check('C1: private design page -> nothing is public', !omochix_design_is_public('video'));
$GLOBALS['stub']['pages'] = ['design' => new WP_Post(['ID' => 77, 'post_status' => 'publish', 'post_password' => 'x'])]; omochix_design_reset_cache();
check('C1: password-protected design page -> nothing is public', !omochix_design_is_public('web'));
stub_page('publish');
check('C1+C2+C5: published page -> web, video and the top page are public', omochix_design_is_public('web') && omochix_design_is_public('video') && omochix_design_is_public(''));
check('C2: app and component are never public (D0 §16)', !omochix_design_is_public('app') && !omochix_design_is_public('component') && !omochix_design_is_public('game'));
check('C3/C4: every shipped work is public under its own medium', array_reduce($WEB, static function ($c, $s) { return $c && omochix_design_is_public('web', $s); }, true) && array_reduce($VIDEO, static function ($c, $s) { return $c && omochix_design_is_public('video', $s); }, true));
check('C2: a work is not public under another medium', !omochix_design_is_public('video', 'liquid-glass') && !omochix_design_is_public('web', 'logo-reveal-short'));
check('C3: unknown slug is not public', !omochix_design_is_public('web', 'no-such-item'));
check('list: 4 Web works kept from D1, in catalog order', array_keys(omochix_design_get_items('web')) === $WEB);
check('list: 2 video works', array_keys(omochix_design_get_items('video')) === $VIDEO);
check('top: all 6 works, Web first', array_keys(omochix_design_get_items('')) === array_merge($WEB, $VIDEO));

$tmp = tempnam(sys_get_temp_dir(), 'dlc');
file_put_contents($tmp, str_replace('"status": "published"', '"status": "draft"', file_get_contents(omochix_design_catalog_file()))); // text edit keeps {} as {}
$res = omochix_design_load_catalog_from_file($tmp);
check('C4: draft works load (valid) but are not published', 6 === count($res['items']) && 'draft' === $res['items']['logo-reveal-short']['status']);
$raw = file_get_contents(omochix_design_catalog_file());
file_put_contents($tmp, str_replace('"perf": {}', '"perf": []', $raw));
$res = omochix_design_load_catalog_from_file($tmp);
check('validation: "perf": [] (list instead of object) drops the work', count($res['items']) < 6 && false !== strpos(implode(' ', $res['errors']), 'audit.perf'));
unlink($tmp);

// -------------------------------------------------------- demo gate (env x state)
$item = omochix_design_get_catalog()['items']['liquid-glass'];
$set  = static function ($state) use ($item) { $i = $item; $i['verification']['state'] = $state; return $i; };
$GLOBALS['stub']['env'] = 'local';
check('gate local: unverified -> no demo', !omochix_design_demo_allowed($set('unverified')));
check('gate local: verified-local -> demo', omochix_design_demo_allowed($set('verified-local')));
foreach (['production', 'staging'] as $env) {
    $GLOBALS['stub']['env'] = $env;
    check("gate $env: verified-local -> no demo", !omochix_design_demo_allowed($set('verified-local')));
    check("gate $env: verified-production -> demo", omochix_design_demo_allowed($set('verified-production')));
}
$GLOBALS['stub']['env'] = 'local';
check('gate: a video work never has a live demo or demo URL', !omochix_design_demo_allowed(omochix_design_get_catalog()['items']['logo-reveal-short']) && '' === omochix_design_demo_url(omochix_design_get_catalog()['items']['logo-reveal-short']));
stub_page(null);
check('gate: verified but design page missing (C1) -> no demo', !omochix_design_demo_allowed($set('verified-production')));
stub_page('publish');
check('labels: the verification label is exactly the state (verified-local says production is unverified)', false !== strpos(omochix_design_verification_states()['verified-local'], '本番環境では未検証') && '未検証' === omochix_design_verification_states()['unverified']);

// ------------------------------------------------------------- demo CSP
$csp = omochix_design_demo_csp(['abc=']);
$dir = array_map('trim', explode(';', $csp));
check('csp: first directive is exactly "sandbox allow-scripts"', 'sandbox allow-scripts' === $dir[0]);
check('csp: no allow-same-origin / top-navigation / forms / popups / modals', !preg_match('/allow-(same-origin|top-navigation|forms|popups|modals|downloads|pointer-lock|presentation|storage-access)/', $csp));
foreach (["default-src 'none'", "connect-src 'none'", "frame-ancestors 'self'", "base-uri 'none'", "form-action 'none'", "object-src 'none'", "worker-src 'none'", "frame-src 'none'", "script-src 'sha256-abc='"] as $d) {
    check("csp: has $d", in_array($d, $dir, true));
}
check('csp: no unsafe-eval, no wildcard, no data: scripts', false === strpos($csp, 'unsafe-eval') && !preg_match('/script-src[^;]*(\*|data:|https?:)/', $csp));
check('csp: no hashes -> script-src none', in_array("script-src 'none'", array_map('trim', explode(';', omochix_design_demo_csp([]))), true));
$h = omochix_design_demo_headers([], 200);
check('headers: enforced CSP (not report-only), nosniff, noindex, no-referrer', isset($h['Content-Security-Policy']) && !isset($h['Content-Security-Policy-Report-Only']) && 'nosniff' === $h['X-Content-Type-Options'] && false !== strpos($h['X-Robots-Tag'], 'noindex') && 'no-referrer' === $h['Referrer-Policy']);
check('headers: 404/503 are not cached', 'no-store' === omochix_design_demo_headers([], 404)['Cache-Control']);
check('headers: the demo no longer clears every header (HSTS etc. survive)', false === strpos(file_get_contents($THEME . '/inc/design-library-demo.php'), 'header_remove();'));

// --------------------------------------------------------- demo document and code
foreach (omochix_design_get_items('web') as $slug => $it) {
    $doc = omochix_design_demo_document($it);
    preg_match_all('#<script>(.*?)</script>#s', (string) $doc['html'], $m);
    $calc = array_map(static function ($s) { return base64_encode(hash('sha256', $s, true)); }, $m[1]);
    check("document $slug: every inline script is covered by its hash, and nothing else", $doc && $calc === $doc['hashes'] && 2 === count($calc));
    $src = omochix_design_demo_sources($it);
    check("code $slug: shown sources are the demo's own (no runtime, no site origin)", '' !== $src['html'] && '' !== $src['css'] && '' !== $src['js'] && false === strpos($src['js'] . $src['html'], '__OMX_SITE_ORIGIN__') && false === strpos($src['js'], 'omx-design:ready') && false === strpos($src['html'], 'ABSPATH'));
}
$evil = sys_get_temp_dir() . '/omx-dl-evil-' . getmypid();
@mkdir($evil . '/demos/liquid-glass', 0777, true);
file_put_contents($evil . '/demos/liquid-glass/demo.php', "<?php defined('ABSPATH') || exit; ?><p>x</p>");
file_put_contents($evil . '/demos/liquid-glass/demo.js', 'var a = "</script><script>alert(1)//";');
$GLOBALS['stub']['filters']['omochix_design_asset_file'][] = static function ($file, $path) use ($evil) { return 0 === strpos($path, 'demos/') ? $evil . '/' . $path : $file; };
check('document: a source containing </script is refused (no break-out)', null === omochix_design_demo_document($item));
file_put_contents($evil . '/demos/liquid-glass/demo.js', 'var a = 1;');
file_put_contents($evil . '/demos/liquid-glass/demo.css', 'p{}</style><script>alert(1)</script>');
check('document: a stylesheet containing </style is refused', null === omochix_design_demo_document($item));
array_map('unlink', glob($evil . '/demos/liquid-glass/*')); rmdir($evil . '/demos/liquid-glass'); rmdir($evil . '/demos'); rmdir($evil);
$GLOBALS['stub']['filters'] = [];

// ------------------------------------------------------- filters, search
$GLOBALS['stub']['terms'] = ['web-production' => 'Web制作', 'ecommerce' => 'EC', 'construction' => '建設', 'beauty' => '美容', 'restaurant' => '飲食', 'real-estate' => '不動産']; omochix_design_reset_cache(); stub_page('publish');
$f = omochix_design_sanitize_filters(['q' => "  <b>ガラス</b>\n  3D ", 'medium' => 'app', 'use_case' => 'lp', 'motion_type' => '<script>', 'technology' => 'flash', 'industry' => 'nope']);
check('filters: tags stripped, spaces collapsed, unknown values and unpublishable medium dropped', 'ガラス 3D' === $f['q'] && '' === $f['medium'] && 'lp' === $f['use_case'] && '' === $f['motion_type'] && '' === $f['technology'] && '' === $f['industry']);
check('filters: arrays and long input are not accepted', '' === omochix_design_sanitize_filters(['use_case' => ['lp']])['use_case'] && 60 === mb_strlen(omochix_design_sanitize_filters(['q' => str_repeat('あ', 200)])['q']));
check('match: medium video', array_keys(omochix_design_get_items('', ['medium' => 'video'])) === $VIDEO);
check('match: use case LP (Web only)', array_keys(omochix_design_get_items('', ['use_case' => 'lp'])) === ['liquid-glass', '3d-interactive', 'bento-saas']);
check('match: motion type chart (Web and video)', array_keys(omochix_design_get_items('', ['motion_type' => 'chart'])) === ['bento-saas', 'data-chart-explainer']);
check('match: technology FFmpeg', array_keys(omochix_design_get_items('', ['technology' => 'ffmpeg'])) === $VIDEO);
check('match: technology WebGL', array_keys(omochix_design_get_items('web', ['technology' => 'webgl'])) === ['3d-interactive']);
check('match: industry (term must exist)', array_keys(omochix_design_get_items('', ['industry' => 'beauty'])) === ['minimal-editorial']);
check('match: keyword in Japanese labels', array_keys(omochix_design_get_items('', ['q' => 'ロゴ'])) === ['logo-reveal-short']);
check('match: keyword AND across fields', array_keys(omochix_design_get_items('', ['q' => 'グラフ 解説'])) === ['data-chart-explainer']);
$o = omochix_design_filter_options('');
check('options: only values used by public works, in vocabulary order', array_keys($o['medium']) === ['web', 'video'] && !isset($o['technology']['blender']) && !isset($o['technology']['remotion']) && array_keys($o['use_case'])[0] === 'lp');
check('options: the video list offers only video values', array_keys(omochix_design_filter_options('video')['use_case']) === ['brand-intro', 'sns-short', 'explainer', 'presentation']);
$GLOBALS['stub']['terms'] = []; omochix_design_reset_cache(); stub_page('publish');
check('options: no Industry Hub -> no industry filter, page still works', [] === omochix_design_filter_options('')['industry'] && 6 === count(omochix_design_get_items('')));

// ------------------------------------------------------------ related, Motion Studio
$GLOBALS['stub']['pages']['learn/development'] = new WP_Post(['ID' => 80, 'post_name' => 'learn/development', 'post_status' => 'publish', 'post_title' => 'AI開発']);
$GLOBALS['stub']['pages']['learn/hidden'] = new WP_Post(['ID' => 81, 'post_name' => 'learn/hidden', 'post_status' => 'draft', 'post_title' => '下書き']);
$it = omochix_design_get_catalog()['items']['bento-saas'];
$it['related']['learn'] = ['learn/development', 'learn/hidden', 'learn/missing'];
$it['related']['designs'] = ['liquid-glass', 'bento-saas', 'no-such-item'];
$rel = omochix_design_related($it);
check('related: only published Learn pages are linked', 1 === count($rel['learn']) && 'AI開発' === $rel['learn'][0]['title']);
check('related: other public works only (not itself, not unknown)', 1 === count($rel['designs']) && 'Liquid Glass' === $rel['designs'][0]['title']);
check('related: Motion Gallery links need the gallery (not loaded here) -> none', [] === $rel['motion']);
$it['motionStudio'] = ['templateId' => 'rag-intro', 'status' => 'verified'];
check('Motion Studio: no URL even for a verified mapping (no editor exists)', '' === omochix_design_motion_studio_url($it));

// ------------------------------------------------------------------ URLs
$lg = omochix_design_get_catalog()['items']['liquid-glass']; $lv = omochix_design_get_catalog()['items']['logo-reveal-short'];
check('urls: list, detail, demo for Web', 'https://example.test/design/web/' === omochix_design_medium_url('web') && 'https://example.test/design/web/liquid-glass/' === omochix_design_item_url($lg) && 'https://example.test/design/demo/web/liquid-glass/' === omochix_design_demo_url($lg));
check('urls: list and detail for video', 'https://example.test/design/video/' === omochix_design_medium_url('video') && 'https://example.test/design/video/logo-reveal-short/' === omochix_design_item_url($lv));
check('urls: component medium uses the "components" segment', 'https://example.test/design/components/' === omochix_design_medium_url('component'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
