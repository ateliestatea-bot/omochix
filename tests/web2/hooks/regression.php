<?php
/**
 * Web 2.0 loaders: the Core plugin and the theme behave exactly as before
 * while the module list is empty, and keep working when the loader file is
 * missing, cut short by an upload, or throws.
 *
 * Core: tests/industry-hub/stub.php loads the whole plugin (omochix-core.php)
 * without WordPress and boots init; the registrations (post types,
 * taxonomies, meta, rewrite rules, every hook) and the defined functions are
 * compared between this tree and copies of it. Theme: functions.php is
 * loaded whole (at load time it only adds hooks) and its hook list, in
 * order, and its functions are compared the same way. "Before" is this tree
 * with the Web 2.0 block taken back out of omochix-core.php / functions.php
 * and the loader deleted; the test fails if that block is not exactly the
 * appended one. Each load runs in its own PHP process.
 *
 * Run: php tests/web2/hooks/regression.php
 */
$REPO = realpath(__DIR__ . '/../../..');

if ('--core' === ($argv[1] ?? '')) {
    // IH_PLUGIN_DIR (read by the stub) points at the copy to load.
    ob_start();
    require $REPO . '/tests/industry-hub/stub.php';
    $load_out = (string) ob_get_clean();
    $fns = array_values(array_filter(get_defined_functions()['user'], static function ($f) { return 0 !== strpos($f, 'omochix_core_web2_') && 0 !== strpos($f, 'ih_') && false === strpos($f, '{closure'); }));
    sort($fns);
    $snap = ih_registration_snapshot();
    $GLOBALS['caps'] = ['*' => true, 'activate_plugins' => true];
    ob_start(); do_action('admin_notices'); $notice = (string) ob_get_clean();
    echo "\n@@JSON@@" . json_encode(['out' => $load_out, 'errors' => $GLOBALS['fail'], 'snap' => $snap, 'functions' => $fns, 'ih' => omochix_core_industry_hub_loaded(), 'notice' => $notice, 'web2' => function_exists('omochix_core_web2_state') ? omochix_core_web2_state() : null]);
    exit(0);
}
if ('--theme' === ($argv[1] ?? '')) {
    define('ABSPATH', '/');
    $T = $argv[2];
    $GLOBALS['hooks'] = [];
    $GLOBALS['errors'] = [];
    set_error_handler(static function ($no, $str, $file, $line) { $GLOBALS['errors'][] = basename($file) . ":$line $str"; return true; });
    function get_theme_file_path($p = '') { return $GLOBALS['T'] . $p; }
    function add_filter($h, $cb, $p = 10, $a = 1) { $GLOBALS['hooks'][] = $h . '@' . $p . '/' . $a . ':' . (is_string($cb) ? $cb : (is_array($cb) ? (is_object($cb[0]) ? get_class($cb[0]) : $cb[0]) . '::' . $cb[1] : '{closure}')); return true; }
    function add_action($h, $cb, $p = 10, $a = 1) { return add_filter($h, $cb, $p, $a); }
    $GLOBALS['T'] = $T;
    $before = get_defined_functions()['user'];
    ob_start();
    require $T . '/functions.php';
    $out = (string) ob_get_clean();
    $fns = array_values(array_filter(array_diff(get_defined_functions()['user'], $before), static function ($f) { return 0 !== strpos($f, 'omochix_web2_'); }));
    sort($fns);
    echo "\n@@JSON@@" . json_encode(['out' => $out, 'errors' => $GLOBALS['errors'], 'hooks' => $GLOBALS['hooks'], 'functions' => $fns, 'web2' => function_exists('omochix_web2_state') ? omochix_web2_state() : null, 'globals' => array_values(array_filter(array_keys($GLOBALS), static function ($k) { return 0 === strpos($k, 'omochix_web2'); }))]);
    exit(0);
}

$pass = 0; $fail = 0;
function rg_check($name, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? " -- $detail" : '') . "\n"; } }
function rg_child(array $args, array $env = []) {
    $prefix = '';
    foreach ($env as $k => $v) { $prefix .= $k . '=' . escapeshellarg($v) . ' '; }
    exec($prefix . escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr ' . implode(' ', array_map('escapeshellarg', array_merge([__FILE__], $args))) . ' 2>&1', $lines, $code);
    $raw = implode("\n", $lines);
    $pos = strrpos($raw, '@@JSON@@');
    return [$code, false === $pos ? null : json_decode(substr($raw, $pos + 8), true), $raw];
}
$TMP = sys_get_temp_dir() . '/omx-web2-regress-' . getmypid() . '-' . bin2hex(random_bytes(4));
mkdir($TMP, 0700, true);
register_shutdown_function(static function () use ($TMP) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($TMP, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $p = $f->getPathname(); (is_link($p) || $f->isFile()) ? unlink($p) : rmdir($p); }
    rmdir($TMP);
});
/** Copy a folder (PHP and JSON files only: what loading needs). */
function rg_copy_tree($from, $to) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    mkdir($to, 0700, true);
    foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen($from));
        if ($f->isDir()) { if (!is_dir($to . $rel)) { mkdir($to . $rel, 0700, true); } continue; }
        if (in_array($f->getExtension(), ['php', 'json'], true)) { copy($f->getPathname(), $to . $rel); }
    }
}

$CORE_BLOCK = "\n// Web 2.0 modules (docs/web2/hooks/README.md). Loaded last and guarded: with\n// the loader missing or broken the plugin works as before. The loader loads\n// only modules on its fixed list (empty, default off) and checks each file\n// before loading it; OMOCHIX_WEB2_DISABLED in wp-config.php stops them all.\nif ( is_readable( OMOCHIX_CORE_DIR . 'includes/web2-loader.php' ) ) {\n\ttry {\n\t\trequire_once OMOCHIX_CORE_DIR . 'includes/web2-loader.php';\n\t} catch ( Throwable \$omochix_core_error ) {\n\t\tunset( \$omochix_core_error );\n\t}\n}\n";
$THEME_BLOCK = "\n// Web 2.0 modules (docs/web2/hooks/README.md). Loaded last and guarded, like\n// the Core plugin's loader: with the loader missing or broken the theme works\n// as before. Only modules on its fixed list (empty, default off) are loaded;\n// OMOCHIX_WEB2_DISABLED in wp-config.php stops them all.\nif (is_readable(get_theme_file_path('/inc/web2-loader.php'))) {\n    try {\n        require_once get_theme_file_path('/inc/web2-loader.php');\n    } catch (Throwable \$omochix_web2_error) {\n        unset(\$omochix_web2_error);\n    }\n}\n";

$variants = [
    'before (no Web 2.0 block, no loader)' => static function ($d, $main, $block, $loader) { file_put_contents("$d/$main", substr((string) file_get_contents("$d/$main"), 0, -strlen($block))); unlink("$d/$loader"); },
    'loader file missing'                  => static function ($d, $main, $block, $loader) { unlink("$d/$loader"); },
    'loader cut short by an upload'        => static function ($d, $main, $block, $loader) { $c = (string) file_get_contents("$d/$loader"); file_put_contents("$d/$loader", substr($c, 0, (int) (strlen($c) * 0.6))); },
    'loader that throws'                   => static function ($d, $main, $block, $loader) { file_put_contents("$d/$loader", "<?php\nthrow new RuntimeException('broken loader');\n"); },
    'loader empty (0 bytes)'               => static function ($d, $main, $block, $loader) { file_put_contents("$d/$loader", ''); },
    'Web 2.0 switched off (OMOCHIX_WEB2_DISABLED)' => static function ($d, $main, $block, $loader) { $f = "$d/$main"; $c = (string) file_get_contents($f); file_put_contents($f, preg_replace('/^(<\?php\n)/', "\$1define( 'OMOCHIX_WEB2_DISABLED', true );\n", $c, 1)); },
];

// ===================================================================== Core
$plugin = "$REPO/wp-plugin/omochix-core";
$main = (string) file_get_contents("$plugin/omochix-core.php");
rg_check('core: omochix-core.php ends with the Web 2.0 block, added once', str_ends_with($main, $CORE_BLOCK) && 1 === substr_count($main, 'web2-loader.php\' ) ) {'));
rg_check('core: the Web 2.0 block is the last require of the plugin bootstrap', strrpos($main, 'require_once') > strrpos($main, "includes/industry-hub.php'"));
[$code, $now, $raw] = rg_child(['--core'], ['IH_PLUGIN_DIR' => $plugin]);
rg_check('core (this tree): loads with no PHP error and no output', 0 === $code && is_array($now) && 0 === $now['errors'] && '' === $now['out'], $raw);
rg_check('core (this tree): the Web 2.0 loader ran with no module and no notice', is_array($now) && true === $now['web2']['ran'] && [] === $now['web2']['modules'] && '' === $now['notice']);
rg_check('core (this tree): Industry Hub module still loaded', is_array($now) && true === $now['ih']);
foreach ($variants as $name => $mutate) {
    $d = $TMP . '/core-' . md5($name);
    rg_copy_tree($plugin, $d);
    $mutate($d, 'omochix-core.php', $CORE_BLOCK, 'includes/web2-loader.php');
    [$code, $r, $raw] = rg_child(['--core'], ['IH_PLUGIN_DIR' => $d]);
    rg_check("core ($name): loads with no PHP error and no output", 0 === $code && is_array($r) && 0 === $r['errors'] && '' === $r['out'], $raw);
    rg_check("core ($name): same post types, taxonomies, meta, rewrite rules and hooks", is_array($r) && $r['snap'] === $now['snap'], is_array($r) ? json_encode(array_diff_key($r['snap'], array_filter(array_map(static function ($k) use ($r, $now) { return $r['snap'][$k] === $now['snap'][$k]; }, array_combine(array_keys($r['snap']), array_keys($r['snap'])))))) : '');
    rg_check("core ($name): same functions (apart from the loader's own)", is_array($r) && $r['functions'] === $now['functions'], is_array($r) ? implode(',', array_merge(array_diff($r['functions'], $now['functions']), array_diff($now['functions'], $r['functions']))) : '');
    rg_check("core ($name): Industry Hub module still loaded, no admin notice", is_array($r) && true === $r['ih'] && '' === $r['notice']);
}

// ==================================================================== theme
$theme = "$REPO/wp-theme/omochix-theme";
$fn = (string) file_get_contents("$theme/functions.php");
rg_check('theme: functions.php ends with the Web 2.0 block, added once', str_ends_with($fn, $THEME_BLOCK) && 1 === substr_count($fn, "inc/web2-loader.php'))) {"));
rg_check('theme: the Web 2.0 block comes after the Industry Hub require (loaded last)', strrpos($fn, 'web2-loader.php') > strrpos($fn, 'learn-industries.php'));
rg_check('theme: inc/labs.php is still required exactly once (tests/labs)', 1 === substr_count($fn, "require_once get_theme_file_path('/inc/labs.php');"));
$tcopy = $TMP . '/theme-now';
rg_copy_tree($theme, $tcopy);
[$code, $tnow, $raw] = rg_child(['--theme', $tcopy]);
rg_check('theme (this tree): functions.php loads with no PHP error and no output', 0 === $code && is_array($tnow) && [] === $tnow['errors'] && '' === $tnow['out'], $raw);
rg_check('theme (this tree): the Web 2.0 loader ran with no module', is_array($tnow) && true === $tnow['web2']['ran'] && [] === $tnow['web2']['modules']);
rg_check('theme (this tree): no global variable left behind by the block', is_array($tnow) && [] === $tnow['globals']);
foreach ($variants as $name => $mutate) {
    $d = $TMP . '/theme-' . md5($name);
    rg_copy_tree($theme, $d);
    $mutate($d, 'functions.php', $THEME_BLOCK, 'inc/web2-loader.php');
    [$code, $r, $raw] = rg_child(['--theme', $d]);
    rg_check("theme ($name): functions.php loads with no PHP error and no output", 0 === $code && is_array($r) && [] === $r['errors'] && '' === $r['out'], $raw);
    rg_check("theme ($name): the same hooks in the same order", is_array($r) && $r['hooks'] === $tnow['hooks'], is_array($r) ? implode(',', array_merge(array_diff($r['hooks'], $tnow['hooks']), array_diff($tnow['hooks'], $r['hooks']))) : '');
    rg_check("theme ($name): same functions (apart from the loader's own)", is_array($r) && $r['functions'] === $tnow['functions']);
}
rg_check('theme: at least the known feature hooks are present (sanity)', count($tnow['hooks']) > 100 && [] !== preg_grep('/^init@/', $tnow['hooks']));

// ================================================== no other file touched
// What the hooks stream may change; the rest of each file stays byte-for-byte.
rg_check('versions: Core header and constant are untouched by the loader block', 1 === preg_match("/^ \\* Version: (\\S+)/m", $main, $a) && false !== strpos($main, "define( 'OMOCHIX_CORE_VERSION', '{$a[1]}' );"));
rg_check('markers: the loaders carry no Industry Hub version marker (Release gate counts them)', false === strpos((string) file_get_contents("$plugin/includes/web2-loader.php"), 'omochix-industry-hub-version') && false === strpos((string) file_get_contents("$theme/inc/web2-loader.php"), 'omochix-industry-hub-version'));
rg_check('modules: no Web 2.0 module folder ships yet', !is_dir("$plugin/includes/web2") && !is_dir("$theme/inc/web2"));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
