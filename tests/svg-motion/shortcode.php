<?php
/**
 * MVP-2: [omochix_logo_motion] contract tests (inc/logo-motion.php) with a WordPress stub and a throwaway uploads folder.
 * Run: php tests/svg-motion/shortcode.php
 * With --render '<json atts>' --base <dir> it prints one rendering (used by shortcode-tests.js).
 */
require __DIR__ . '/../learn-gallery/stub.php';

function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); }
function add_shortcode($tag, $fn) { $GLOBALS['shortcodes'][$tag] = $fn; }
function shortcode_atts($pairs, $atts, $tag = '') { $out = []; foreach ($pairs as $k => $v) { $out[$k] = array_key_exists($k, $atts) ? $atts[$k] : $v; } return $out; }
function current_user_can($cap) { return !empty($GLOBALS['caps'][$cap]); }
function is_feed() { return false; }
function wp_upload_dir($time = null, $create = true) { return ['basedir' => $GLOBALS['uploads'], 'baseurl' => $GLOBALS['uploads_url'] ?? 'https://example.test/wp-content/uploads', 'error' => false]; }
require $THEME . '/inc/logo-motion.php';

$FIX = __DIR__ . '/fixtures/logo-motion';

// Helpers for tests/svg-motion/shortcode-tests.js:
//   --render '<json atts>' <uploads dir> <uploads url>   one rendering, as a visitor sees it
//   --check-dir <dir>                                    {file: problems} for every .svg in the folder
if (($argv[1] ?? '') === '--render') {
    $GLOBALS['uploads'] = $argv[3] ?? '';
    $GLOBALS['uploads_url'] = $argv[4] ?? '';
    $GLOBALS['caps'] = [];
    echo omochix_logo_motion_render(json_decode($argv[2], true) ?: []);
    exit(0);
}
if (($argv[1] ?? '') === '--check-dir') {
    $out = [];
    foreach (glob(rtrim($argv[2], '/') . '/*.svg') as $file) { $out[basename($file)] = omochix_logo_motion_check_svg((string) file_get_contents($file))['problems']; }
    echo json_encode($out);
    exit(0);
}

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }

/** A fresh uploads folder with the folder of logo motions in it. */
function uploads() {
    $u = sys_get_temp_dir() . '/omx-sc-' . bin2hex(random_bytes(6));
    mkdir($u . '/' . OMOCHIX_LOGO_MOTION_DIR, 0777, true);
    $GLOBALS['uploads'] = $u;
    return $u . '/' . OMOCHIX_LOGO_MOTION_DIR;
}
function put($dir, $name, $animated, $still) { file_put_contents("$dir/$name.svg", $animated); file_put_contents("$dir/$name-static.svg", $still); }
function render($atts) { return omochix_logo_motion_render($atts); }
function rm_tree($d) { if (!file_exists($d) && !is_link($d)) return; if (is_link($d) || is_file($d)) { unlink($d); return; } foreach (scandir($d) as $f) { if ('.' !== $f && '..' !== $f) rm_tree("$d/$f"); } rmdir($d); }

$pop      = file_get_contents("$FIX/omochix-logo-motion-pop.svg");
$popStill = file_get_contents("$FIX/omochix-logo-motion-pop-static.svg");
$wipe     = file_get_contents("$FIX/omochix-logo-motion-wipe.svg");
$wipeStill = file_get_contents("$FIX/omochix-logo-motion-wipe-static.svg");

// ------------------------------------------------------------------ registration and output
omochix_logo_motion_register();
check('registered as [omochix_logo_motion]', ($GLOBALS['shortcodes']['omochix_logo_motion'] ?? '') === 'omochix_logo_motion_render');

$dir = uploads(); put($dir, 'acme', $pop, $popStill); $GLOBALS['caps'] = [];
$html = render(['name' => 'acme', 'alt' => 'Acme のロゴ', 'size' => '320']);
check('a placed pair renders a <picture> with the still file for reduced motion', 1 === preg_match('#^<picture class="omochix-logo-motion"><source media="\(prefers-reduced-motion: reduce\)" srcset="https://example\.test/wp-content/uploads/omochix-logo-motion/acme-static\.svg\?v=[0-9a-f]{12}"><img src="https://example\.test/wp-content/uploads/omochix-logo-motion/acme\.svg\?v=[0-9a-f]{12}" alt="Acme のロゴ" width="320" height="320" loading="lazy" decoding="async" style="max-width:100%;height:auto"></picture>$#u', $html));
check('the output is an image only: no inline SVG, script, handler, object or iframe', !preg_match('/<svg|<script|\son\w+=|<object|<iframe|<embed/i', $html));

$html = render(['name' => 'acme', 'alt' => 'x', 'size' => '200', 'class' => 'evil', 'style' => 'background:url(https://evil.example)', 'onload' => 'alert(1)', 'src' => 'https://evil.example/x.svg', 'srcset' => 'https://evil.example/y.svg']);
check('attributes other than name, alt and size are ignored', false === strpos($html, 'evil') && false === strpos($html, 'alert') && 1 === substr_count($html, 'style='));

$html = render(['name' => 'acme', 'alt' => '"><script>alert(1)</script><img src=x onerror=alert(2)>']);
check('alt is stripped and escaped', false === strpos($html, '<script') && false === strpos($html, 'onerror') && false !== strpos($html, 'alt="&quot;&gt;alert(1)"') && 1 === substr_count($html, '<img '));
$html = render(['name' => 'acme', 'alt' => str_repeat('あ', 200)]);
check('alt is cut at 120 characters', 1 === preg_match('/alt="(あ{120})"/u', $html));
$html = render(['name' => 'acme']);
check('no alt gives alt="" (decorative)', false !== strpos($html, 'alt=""'));

$sizes = ['5' => 16, '16' => 16, '320' => 320, '1024' => 1024, '5000' => 1024, '' => 200, 'abc' => 200, '320px' => 200, '-5' => 200, '1e3' => 200, '99999' => 200];
$ok = true; foreach ($sizes as $in => $want) { if (!preg_match('/width="' . $want . '" height="' . $want . '"/', render(['name' => 'acme', 'size' => (string) $in]))) { $ok = false; echo "  size $in\n"; } }
check('size is an integer kept between 16 and 1024, anything else gives 200', $ok);

$wide = str_replace('viewBox="0 0 120 120"', 'viewBox="0 0 240 120"', [$pop, $popStill]);
put($dir, 'wide', $wide[0], $wide[1]);
check('height follows the logo\'s own proportions', false !== strpos(render(['name' => 'wide', 'size' => '300']), 'width="300" height="150"'));

// ------------------------------------------------------------------ names and paths
$bad = ['', '../acme', 'acme/../acme', 'a/b', 'ACME', 'Acme', '-acme', 'acme.svg', 'acme%2e', "ac\0me", ' acme', 'acme ', str_repeat('a', 65), '..', '.', 'acme-static/..', '/etc/passwd', 'C:\\x', 'acme?x', 'acme#x'];
$ok = true; foreach ($bad as $name) { if ('name' !== (omochix_logo_motion_resolve($name)['error'] ?? '')) { $ok = false; echo '  accepted name ' . json_encode($name) . "\n"; } }
check('names other than [a-z0-9][a-z0-9-]{0,63} are refused before any file is touched', $ok);
check('a name that is not a string is refused', 'name' === omochix_logo_motion_resolve(['acme'])['error'] && 'name' === omochix_logo_motion_resolve(null)['error']);
// L-2: "$" without the D modifier also matches before a final "\n"; WordPress runs stripcslashes() on shortcode
// attributes, so name="acme\n" arrives with a real newline. Every full-match pattern must anchor at the very end.
$edge = ["acme\n", "acme\r\n", "acme\r", "\nacme", "acme\n\n", "acme\t", "acme\x0b", "acme\x0c", "acme\u{85}", "acme\u{2028}", "acme\u{200b}",
    "ａｃｍｅ", "acmé", "acme\u{301}", "acme/", "acme\\", "acme\\n", "acme%0a", "acme%0d%0a", "acme/..", "./acme", "acme\0"];
$ok = true; foreach ($edge as $name) { if ('name' !== (omochix_logo_motion_resolve($name)['error'] ?? '')) { $ok = false; echo '  accepted name ' . json_encode($name) . "\n"; } }
check('L-2: newline, CRLF, other line breaks, Unicode look-alikes and path separators are refused as names (' . count($edge) . ' cases)', $ok);
// A real pair whose file names end in a newline must not be shown, and must not make the page load "acme.svg" instead.
put($dir, "nl\n", $pop, $popStill); put($dir, 'nl', $wipe, $wipeStill);
$GLOBALS['caps'] = []; $visitor = render(['name' => "nl\n"]); $GLOBALS['caps'] = ['edit_posts' => true]; $editor = render(['name' => "nl\n"]); $GLOBALS['caps'] = [];
check('L-2: name="nl\n" with files "nl\n.svg" in the folder is refused (nothing for visitors, the name code for editors)', '' === $visitor && '<!-- omochix_logo_motion: name -->' === $editor);
unlink("$dir/nl\n.svg"); unlink("$dir/nl\n-static.svg");
$ok = true; foreach (["320\n", "320\r\n", "\n320", "3\n20"] as $in) { if (!preg_match('/width="200" height="200"/', render(['name' => 'acme', 'size' => $in]))) { $ok = false; echo '  size ' . json_encode($in) . "\n"; } }
check('L-2: a size with a line break falls back to 200', $ok);
check('a 64-character name is accepted when its files exist',(function () use ($dir, $pop, $popStill) { $n = str_repeat('a', 64); put($dir, $n, $pop, $popStill); return !isset(omochix_logo_motion_resolve($n)['error']); })());

check('missing pair: nothing shown to visitors', '' === render(['name' => 'nothing-here']));
$GLOBALS['caps'] = ['edit_posts' => true];
check('missing pair: editors see a code in a comment, not the input', '<!-- omochix_logo_motion: missing -->' === render(['name' => 'nothing-here']));
check('a refused name: the comment does not repeat the input', '<!-- omochix_logo_motion: name -->' === render(['name' => '<b>x</b>']));
$GLOBALS['caps'] = [];

file_put_contents("$dir/half.svg", $pop);
check('only the animated file: refused (missing)', 'missing' === omochix_logo_motion_resolve('half')['error']);

$outside = sys_get_temp_dir() . '/omx-sc-outside-' . bin2hex(random_bytes(4));
mkdir($outside); put($outside, 'acme', $pop, $popStill);
symlink("$outside/acme.svg", "$dir/linked.svg"); symlink("$outside/acme-static.svg", "$dir/linked-static.svg");
check('a symbolic link to a file outside the folder is refused', 'link' === omochix_logo_motion_resolve('linked')['error']);
symlink("$dir/acme.svg", "$dir/inside.svg"); copy("$dir/acme-static.svg", "$dir/inside-static.svg");
check('a symbolic link inside the folder is refused too', 'link' === omochix_logo_motion_resolve('inside')['error']);
link("$outside/acme.svg", "$dir/hard.svg"); link("$outside/acme-static.svg", "$dir/hard-static.svg");
check('a hard link to a file outside the folder is refused (MVP-3 S4)', 'link' === omochix_logo_motion_resolve('hard')['error']);
mkdir("$dir/sub"); put("$dir/sub", 'deep', $pop, $popStill);
check('files in a sub-folder cannot be reached', 'name' === omochix_logo_motion_resolve('sub/deep')['error']);

$u2 = sys_get_temp_dir() . '/omx-sc-' . bin2hex(random_bytes(6)); mkdir($u2); symlink($outside, $u2 . '/' . OMOCHIX_LOGO_MOTION_DIR); $GLOBALS['uploads'] = $u2;
check('the folder itself as a symbolic link is refused', 'folder' === omochix_logo_motion_resolve('acme')['error']);
$GLOBALS['uploads'] = sys_get_temp_dir() . '/omx-sc-none-' . bin2hex(random_bytes(4));
check('no folder: refused', 'folder' === omochix_logo_motion_resolve('acme')['error']);
$GLOBALS['uploads'] = dirname($dir);

// ------------------------------------------------------------------ content checks
$cases = [
    'doctype'           => [str_replace('<svg ', "<!DOCTYPE svg [<!ENTITY x \"y\">]><svg ", $pop), 'doctype'],
    'entity'            => [str_replace('<svg ', "<!ENTITY a SYSTEM \"file:///etc/passwd\"><svg ", $pop), 'doctype'],
    'processing'        => [str_replace('<title>', '<?php echo 1; ?><title>', $pop), 'pi'],
    'script'            => [str_replace('<title>', '<script>alert(1)</script><title>', $pop), 'element:script'],
    'foreignObject'     => [str_replace('<title>', '<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject><title>', $pop), 'element:foreignObject'],
    'handler'           => [str_replace('<title>', '<g onload="alert(1)"/><title>', $pop), 'handler:onload'],
    'handlerUpper'      => [str_replace('<title>', '<g ONCLICK="alert(1)"/><title>', $pop), 'handler:onclick'],
    'externalHref'      => [str_replace('<title>', '<image href="https://evil.example/x.png"/><title>', $pop), 'href:image'],
    'javascriptHref'    => [str_replace('<title>', '<use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="javascript:alert(1)"/><title>', $pop), 'href:use'],
    'dataHtml'          => [str_replace('<title>', '<image href="data:text/html;base64,PHNjcmlwdD4="/><title>', $pop), 'href:image'],
    'urlFill'           => [str_replace('fill="#fff"', 'fill="url(https://evil.example/#a)"', $pop), 'value:fill'],
    'styleAttr'         => [str_replace('fill="#fff"', 'style="background:url(https://evil.example)"', $pop), 'value:style'],
    'unknownAttr'       => [str_replace('fill="#fff"', 'formaction="x"', $pop), 'attribute:formaction'],
    'badId'             => [str_replace('id="omx-e40839bd-omx-a"', 'id="a&quot;b"', $pop), 'id'],
    'namespacedAttr'    => [str_replace('<title>', '<g xmlns:ev="http://www.w3.org/2001/xml-events" ev:event="click"/><title>', $pop), 'attribute-namespace'],
    'htmlElement'       => [str_replace('<title>', '<div xmlns="http://www.w3.org/1999/xhtml"/><title>', $pop), 'element:div'],
    'import'            => [preg_replace('/<style>/', '<style>@import url(https://evil.example/x.css);', $pop, 1), 'style'],
    'generatedUrl'      => [str_replace('transform-origin:center}', 'transform-origin:center;background:url(https://evil.example)}', $pop), 'style'],
    'logoStyleUnscoped' => [str_replace('<title>', '<style>path{fill:red}</style><title>', $pop), 'style:scope'],
    'logoStyleAt'       => [str_replace('<title>', '<style>@font-face{src:url(x)}</style><title>', $pop), 'style:scope'],
    'noScope'           => [str_replace('class="omx-e40839bd"', 'class="logo"', $pop), 'class'],
    'notSvg'            => ['<html xmlns="http://www.w3.org/1999/xhtml"><body/></html>', 'parse'],
    'svgWrongNs'        => ['<svg class="omx-e40839bd" viewBox="0 0 1 1"/>', 'parse'],
    'malformed'         => [substr($pop, 0, 200), 'parse'],
    'empty'             => ['', 'size'],
    'huge'              => [str_replace('<title>', '<desc>' . str_repeat('a', OMOCHIX_LOGO_MOTION_MAX) . '</desc><title>', $pop), 'size'],
    'deep'              => [str_replace('<title>', str_repeat('<g>', 40) . str_repeat('</g>', 40) . '<title>', $pop), 'budget'],
    'comment'           => [str_replace('<title>', '<!--<img src=x onerror=alert(1)>--><title>', $pop), 'comment'],
    'clobberId'         => [str_replace('<title>', '<rect id="getElementById" width="9" height="9"/><title>', $pop), 'id'],
    'foreignPrefixId'   => [str_replace('id="omx-e40839bd-omx-a"', 'id="omx-00000000-omx-a"', $pop), 'id'],
    'hugePath'          => [str_replace('<title>', '<path d="M0 0' . str_repeat(' L1 1', 110000) . '"/><title>', $pop), 'path'],
    // MVP-3 audit (S2, S3): encodings, nodes outside the root, generated CSS outside the studio's grammar.
    'utf16le'           => ["\xFF\xFE" . mb_convert_encoding($pop, 'UTF-16LE', 'UTF-8'), 'encoding'],
    'utf16be'           => ["\xFE\xFF" . mb_convert_encoding($pop, 'UTF-16BE', 'UTF-8'), 'encoding'],
    'utf16Doctype'      => ["\xFF\xFE" . mb_convert_encoding('<!DOCTYPE svg SYSTEM "https://evil.example/x.dtd">' . $pop, 'UTF-16LE', 'UTF-8'), 'encoding'],
    'utf16Stylesheet'   => ["\xFF\xFE" . mb_convert_encoding('<?xml-stylesheet href="https://evil.example/x.css"?>' . $pop, 'UTF-16LE', 'UTF-8'), 'encoding'],
    'utf16NoBom'        => [mb_convert_encoding($pop, 'UTF-16LE', 'UTF-8'), 'encoding'],
    'utf8Bom'           => ["\xEF\xBB\xBF" . $pop, 'encoding'],
    'latin1Declared'    => ['<?xml version="1.0" encoding="ISO-8859-1"?>' . $pop, 'encoding'],
    'invalidUtf8'       => [str_replace('<title>', "<title>\xC3\x28", $pop), 'encoding'],
    'nul'               => [str_replace('<title>', "<title>\0", $pop), 'encoding'],
    'utf8DeclaredOk'    => ['<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet href="x.css"?>' . $pop, 'pi'],
    'stylesheetFirst'   => ['<?xml-stylesheet type="text/css" href="https://evil.example/x.css"?>' . $pop, 'pi'],
    'commentFirst'      => ['<!-- note -->' . $pop, 'comment'],
    'cdataStyle'        => [preg_replace('/<style>(.*?)<\/style>/s', '<style><![CDATA[$1]]></style>', $pop, 1), 'cdata'],
    'markupText'        => [str_replace('<title>Prism', '<title>&lt;img src=x onerror=alert(1)&gt;', $pop), 'text'],
    'imageSet'          => [str_replace('transform-origin:center}', 'transform-origin:center;background-image:image-set("x.png" 1x)}', $pop), 'style'],
    'cssVar'            => [str_replace('transform-origin:center}', 'transform-origin:center;fill:var(--a)}', $pop), 'style'],
    'cssNamespace'      => [preg_replace('/<style>/', '<style>@namespace x "y";', $pop, 1), 'style'],
    'cssCharset'        => [preg_replace('/<style>/', '<style>@charset "utf-8";', $pop, 1), 'style'],
    'cssOtherMedia'     => [str_replace('@media (prefers-reduced-motion:reduce)', '@media print', $pop), 'style'],
    'cssUnknownProp'    => [str_replace('transform-origin:center}', 'transform-origin:center;content:"x"}', $pop), 'style'],
    'cssBadValue'       => [str_replace('opacity:0;', 'opacity:attr(x);', $pop), 'style'],
    'cssForeignScope'   => [str_replace('svg.omx-e40839bd{', 'svg.omx-00000000{', $pop), 'style'],
    'cssOtherSelector'  => [str_replace('svg.omx-e40839bd{', 'svg.omx-e40839bd,body{', $pop), 'style'],
    'cssForeignKeyframe'=> [str_replace('@keyframes omx-e40839bd-k0{', '@keyframes evil{', $pop), 'style'],
    'cssUnbalanced'     => [preg_replace('/<\/style>/', '{</style>', $pop, 1), 'style'],
    'cssEscape'         => [str_replace('opacity:0;', 'opa\\63ity:0;', $pop), 'style'],
    // L-2: a character reference keeps a real line break in the attribute value after XML parsing.
    'scopeNewline'      => [str_replace('class="omx-e40839bd"', 'class="omx-e40839bd&#10;"', $pop), 'class'],
    'idNewline'         => [str_replace('id="omx-e40839bd-omx-a"', 'id="omx-e40839bd-omx-a&#10;"', $pop), 'id'],
    'hrefNewline'       => [str_replace('<title>', '<linearGradient id="omx-e40839bd-omx-z" href="#omx-e40839bd-omx-a&#10;"/><title>', $pop), 'href:linearGradient'],
];
$ok = true;
foreach ($cases as $k => [$text, $want]) {
    $problems = omochix_logo_motion_check_svg($text)['problems'];
    if (!in_array($want, $problems, true)) { $ok = false; echo "  $k: " . json_encode($problems) . "\n"; }
    put($dir, 'case-' . strtolower($k), $text, $popStill);
    if ('' !== render(['name' => 'case-' . strtolower($k)])) { $ok = false; echo "  $k rendered\n"; }
}
check('each hostile or broken file is refused with its reason, and nothing is shown (' . count($cases) . ' cases)', $ok);
check('the studio\'s own exports pass the check (pop, wipe, both files)', !omochix_logo_motion_check_svg($pop)['problems'] && !omochix_logo_motion_check_svg($popStill)['problems'] && !omochix_logo_motion_check_svg($wipe)['problems'] && !omochix_logo_motion_check_svg($wipeStill)['problems']);

// ------------------------------------------------------------------ the pair
put($dir, 'mixed', $pop, $wipeStill);
check('files from two different exports are refused (pair)', 'pair' === omochix_logo_motion_resolve('mixed')['error']);
put($dir, 'still-twice', $popStill, $popStill);
check('a still file in place of the animated one is refused (pair)', 'pair' === omochix_logo_motion_resolve('still-twice')['error']);
put($dir, 'moving-twice', $pop, $pop);
check('an animated file in place of the still one is refused (pair)', 'pair' === omochix_logo_motion_resolve('moving-twice')['error']);

// ------------------------------------------------------------------ replacing and removing files
put($dir, 'swap', $pop, $popStill);
$before = render(['name' => 'swap']); preg_match('/\?v=([0-9a-f]{12})/', $before, $v1);
put($dir, 'swap', $wipe, $wipeStill); touch("$dir/swap.svg", time() + 5); touch("$dir/swap-static.svg", time() + 5);
$after = render(['name' => 'swap']); preg_match('/\?v=([0-9a-f]{12})/', $after, $v2);
check('replacing the pair with another export is shown, under a new ?v= (no stale cache)', '' !== $after && !empty($v1[1]) && !empty($v2[1]) && $v1[1] !== $v2[1]);
// Same size and the old modification time (touch -r): the version still changes, because it is the contents' hash.
$stamp = filemtime("$dir/swap.svg"); $same = str_replace('fill="#fff"', 'fill="#eee"', $wipe); file_put_contents("$dir/swap.svg", $same); touch("$dir/swap.svg", $stamp);
preg_match('/\?v=([0-9a-f]{12})/', render(['name' => 'swap']), $v3);
check('a same-size swap that keeps the old modification time still gets a new ?v= (contents hash)', strlen($same) === strlen($wipe) && !empty($v3[1]) && $v3[1] !== $v2[1]);
file_put_contents("$dir/swap.svg", str_replace('<title>', '<script>alert(1)</script><title>', $wipe)); touch("$dir/swap.svg", time() + 10);
check('replacing one file with a tampered one: refused at once, even in the same request', '' === render(['name' => 'swap']));
unlink("$dir/swap-static.svg");
check('removing the still file: nothing shown', '' === render(['name' => 'swap']) && 'missing' === omochix_logo_motion_resolve('swap')['error']);

rm_tree(dirname($dir)); rm_tree($outside); rm_tree($u2);
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
