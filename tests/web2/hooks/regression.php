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
 * Module folders (includes/web2/, inc/web2/) may hold modules before they are
 * listed: the list decides what loads, never the folder. The end of this file
 * checks that nothing in a folder runs, that its files already follow the
 * loader's rules, that listed modules breaking a rule are refused, and that
 * enabling the shipped modules opens nothing public.
 *
 * Run: php tests/web2/hooks/regression.php
 */
$REPO = realpath(__DIR__ . '/../../..');

/**
 * What the loader sees in a module folder ($dir/$sub): every PHP file read
 * with the loader's own tokenizer check ($declarations), and every entry
 * file (marker on line 2) with the loader's own file check ($check). Nothing
 * in the folder is run.
 */
function rg_module_report($dir, $sub, $check, $declarations) {
    $out = ['entries' => [], 'files' => []];
    $root = "$dir/$sub";
    if (!is_dir($root) || !function_exists($check) || !function_exists($declarations)) {
        return $out;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ('php' !== strtolower($f->getExtension())) {
            continue;
        }
        $rel  = $sub . str_replace(DIRECTORY_SEPARATOR, '/', substr($f->getPathname(), strlen($root)));
        $code = (string) file_get_contents($f->getPathname());
        $d    = $declarations($code);
        $out['files'][$rel] = is_array($d) ? $d : ['error' => $d];
        if (preg_match('#^<\?php\r?\n// omochix-web2-module: (\S+) api=#', $code, $m)) {
            $out['entries'][$rel] = ['id' => $m[1], 'check' => $check($m[1], $rel, $dir)];
        }
    }
    ksort($out['files']);
    ksort($out['entries']);
    return $out;
}

/** For each loaded module, what its public gate function (if it has one) returns. */
function rg_gates(?array $state, $format, $arg) {
    $gates = [];
    foreach ((array) ($state['modules'] ?? []) as $id => $m) {
        if ('loaded' !== $m['state']) {
            continue;
        }
        $fn = sprintf($format, str_replace('-', '_', (string) $id));
        $gates[$id] = function_exists($fn) ? var_export(null === $arg ? $fn() : $fn($arg), true) : 'none';
    }
    return $gates;
}

if ('--core' === ($argv[1] ?? '')) {
    // IH_PLUGIN_DIR (read by the stub) points at the copy to load. RG_ERRLOG: where error_log() writes.
    if (getenv('RG_ERRLOG')) { ini_set('error_log', getenv('RG_ERRLOG')); }
    if (!function_exists('__return_empty_string')) { function __return_empty_string() { return ''; } }
    ob_start();
    require $REPO . '/tests/industry-hub/stub.php';
    $load_out = (string) ob_get_clean();
    // What a navigation block would print after registration (no reference: the guard lets WordPress render it).
    $nav_args = apply_filters('register_block_type_args', ['render_callback' => static function () { return 'NAV'; }], 'core/navigation');
    $nav_probe = is_callable($nav_args['render_callback'] ?? null) ? (string) call_user_func($nav_args['render_callback'], [], '', null) : 'not callable';
    // REST: what /wp/v2/navigation would return to a non-editor for a menu, and whether every navigation filter can run.
    $GLOBALS['caps'] = [];
    $rest_probe = 'no response class';
    if (class_exists('WP_Post')) {
        $rest_res = new class(['content' => ['raw' => 'MENU-BLOCKS', 'rendered' => 'MENU-HTML']]) { private $d; function __construct($d) { $this->d = $d; } function get_data() { return $this->d; } function set_data($d) { $this->d = $d; } };
        try {
            $out = apply_filters('rest_prepare_wp_navigation', $rest_res, new WP_Post(['ID' => 77, 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_password' => 'pw']), null);
            $rest_probe = false !== strpos(json_encode($out->get_data()), 'MENU-BLOCKS') ? 'raw' : 'stripped';
            apply_filters('block_core_navigation_render_fallback', []);
        } catch (Throwable $e) { $rest_probe = 'fatal ' . get_class($e); }
    }
    $GLOBALS['caps'] = ['*' => true, 'activate_plugins' => true];
    $fns = array_values(array_filter(get_defined_functions()['user'], static function ($f) { return 0 !== strpos($f, 'omochix_core_web2_') && 0 !== strpos($f, 'ih_') && false === strpos($f, '{closure'); }));
    sort($fns);
    // A filter the plugin took off again leaves an empty list in the stub; drop those so the snapshot can sort.
    foreach (['hooks', 'ih_load_hooks'] as $g) { foreach ((array) ($GLOBALS[$g] ?? []) as $h => $by) { foreach ($by as $p => $l) { if (!$l) { unset($GLOBALS[$g][$h][$p]); } } if (!$GLOBALS[$g][$h]) { unset($GLOBALS[$g][$h]); } } }
    $snap = ih_registration_snapshot();
    $GLOBALS['caps'] = ['*' => true, 'activate_plugins' => true];
    ob_start(); do_action('admin_notices'); $notice = (string) ob_get_clean();
    $GLOBALS['caps'] = ['edit_posts' => true, 'edit_others_posts' => true];
    ob_start(); do_action('admin_notices'); $notice_editor = (string) ob_get_clean();
    $GLOBALS['caps'] = ['*' => true, 'activate_plugins' => true];
    $web2 = function_exists('omochix_core_web2_state') ? omochix_core_web2_state() : null;
    echo "\n@@JSON@@" . json_encode(['out' => $load_out, 'errors' => $GLOBALS['fail'], 'snap' => $snap, 'functions' => $fns, 'ih' => omochix_core_industry_hub_loaded(), 'notice' => $notice, 'notice_editor' => $notice_editor, 'web2' => $web2,
        'nav' => $nav_probe, 'rest' => $rest_probe, 'closed' => function_exists('has_filter') && false !== has_filter('register_block_type_args', 'omochix_core_nav_render_closed'), 'problem' => function_exists('omochix_core_sec_layer_problem') ? omochix_core_sec_layer_problem() : null, 'log' => getenv('RG_ERRLOG') && is_file(getenv('RG_ERRLOG')) ? (string) file_get_contents(getenv('RG_ERRLOG')) : '',
        'list' => function_exists('omochix_core_web2_modules') ? omochix_core_web2_modules() : null,
        'report' => rg_module_report(getenv('IH_PLUGIN_DIR'), 'includes/web2', 'omochix_core_web2_check', 'omochix_core_web2_declarations'),
        'gates' => rg_gates($web2, 'omochix_core_%s_public', null)]);
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
    $web2 = function_exists('omochix_web2_state') ? omochix_web2_state() : null;
    echo "\n@@JSON@@" . json_encode(['out' => $out, 'errors' => $GLOBALS['errors'], 'hooks' => $GLOBALS['hooks'], 'functions' => $fns, 'web2' => $web2, 'globals' => array_values(array_filter(array_keys($GLOBALS), static function ($k) { return 0 === strpos($k, 'omochix_web2'); })),
        'list' => function_exists('omochix_web2_modules') ? omochix_web2_modules() : null,
        'report' => rg_module_report($T, 'inc/web2', 'omochix_web2_check', 'omochix_web2_declarations'),
        'gates' => rg_gates($web2, 'omochix_%s_public_switch', true)]);
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

// ------------------------------------------------- Core: security layer (sec stream, review N-1)
// includes/web2/security.php is not a module: omochix-core.php loads it before the Web 2.0 block, so OMOCHIX_WEB2_DISABLED
// and an empty module list do not stop it. Fail closed: missing, cut short, throwing or incomplete -> every navigation
// block renders nothing, error_log() says why, administrators see a notice; the rest of the plugin is unchanged.
$sec_at = strpos($main, "\n// Web 2.0 security layer (docs/web2/sec/README.md).");
$sec_block = false === $sec_at ? '' : substr($main, $sec_at, strlen($main) - strlen($CORE_BLOCK) - $sec_at);
rg_check('core: the security block sits right before the Web 2.0 block, outside it, and runs once', '' !== $sec_block && str_ends_with($main, $sec_block . $CORE_BLOCK) && 1 === substr_count($main, "\nomochix_core_sec_layer_load();\n") && str_ends_with($sec_block, "\nomochix_core_sec_layer_load();\n") && false === strpos($sec_block, 'OMOCHIX_WEB2_DISABLED )'));
rg_check('core: the security block reads no request data and no database handle', '' !== $sec_block && 0 === preg_match('/\$_(POST|GET|REQUEST|COOKIE|SERVER|FILES)|\$wpdb/', $sec_block));
$sec_file = 'includes/web2/security.php';
rg_check('core (this tree): the security layer ships with the plugin (merge the sec pull request first)', is_file("$plugin/$sec_file"));
rg_check('core (this tree): the security layer is in place: no fail-closed filter, no notice, navigation renders, REST strips a password menu', is_array($now) && false === $now['closed'] && '' === $now['problem'] && 'NAV' === $now['nav'] && 'stripped' === $now['rest'] && '' === $now['notice']);
$sec_run = static function ($name, $mutate) use ($plugin, $TMP, $sec_file) {
    $d = $TMP . '/core-sec-' . md5($name);
    rg_copy_tree($plugin, $d);
    $mutate($d, "$d/$sec_file");
    $log = "$d.log"; @unlink($log);
    return rg_child(['--core'], ['IH_PLUGIN_DIR' => $d, 'RG_ERRLOG' => $log]);
};
$disable = static function ($d) { $f = "$d/omochix-core.php"; file_put_contents($f, preg_replace('/^(<\?php\n)/', "\$1define( 'OMOCHIX_WEB2_DISABLED', true );\n", (string) file_get_contents($f), 1)); };
[$code, $r, $raw] = $sec_run('Web 2.0 switched off', static function ($d, $f) use ($disable) { $disable($d); });
rg_check('core (Web 2.0 switched off, OMOCHIX_WEB2_DISABLED): the security layer is still in place, no module loaded', 0 === $code && is_array($r) && 0 === $r['errors'] && '' === $r['out'] && false === $r['closed'] && 'NAV' === $r['nav'] && '' === $r['notice'] && '' === $r['log'] && [] === $r['web2']['modules'] && true === $r['ih'], $raw);
foreach ([
    'security file missing' => ['missing', static function ($d, $f) { @unlink($f); }],
    'security file that throws' => ['failed to load (RuntimeException)', static function ($d, $f) { file_put_contents($f, "<?php\nthrow new RuntimeException('broken security layer');\n"); }],
    'security file with a syntax error' => ['failed to load (ParseError)', static function ($d, $f) { file_put_contents($f, "<?php\nfunction omochix_core_security_nav_block_args( \$a {\n"); }],
    'security file cut short by an upload' => ['', static function ($d, $f) { $c = (string) file_get_contents($f); file_put_contents($f, substr($c, 0, (int) (strlen($c) * 0.6))); }],
    'security file empty (0 bytes)' => ['incomplete', static function ($d, $f) { file_put_contents($f, ''); }],
    'security file without its guard' => ['incomplete', static function ($d, $f) { file_put_contents($f, "<?php\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\nfunction omochix_core_security_other() {\n\treturn true;\n}\n"); }],
    'security guard filter taken off' => ['incomplete', static function ($d, $f) { $c = (string) file_get_contents($f); file_put_contents($f, str_replace("add_filter( 'register_block_type_args', 'omochix_core_security_nav_block_args', 9999, 2 );", '', $c)); }],
    'security fallback filter taken off' => ['incomplete', static function ($d, $f) { $c = (string) file_get_contents($f); file_put_contents($f, str_replace("add_filter( 'block_core_navigation_render_fallback', 'omochix_core_security_nav_fallback', 9999 );", '', $c)); }],
    'security fallback guard renamed' => ['incomplete', static function ($d, $f) { $c = (string) file_get_contents($f); file_put_contents($f, str_replace('omochix_core_security_nav_fallback', 'omochix_core_security_nav_fallback_x', $c)); }],
    'security REST filter taken off' => ['incomplete', static function ($d, $f) { $c = (string) file_get_contents($f); file_put_contents($f, str_replace("add_filter( 'rest_prepare_wp_navigation', 'omochix_core_security_nav_rest', 9999, 2 );", '', $c)); }],
    'security fallback function missing, its filter still registered' => ['incomplete', static function ($d, $f) { $c = (string) file_get_contents($f); file_put_contents($f, str_replace('function omochix_core_security_nav_fallback(', 'function omochix_core_security_nav_fallback_gone(', $c)); }],
    'security REST guard renamed' => ['incomplete', static function ($d, $f) { $c = (string) file_get_contents($f); file_put_contents($f, str_replace('omochix_core_security_nav_rest', 'omochix_core_security_nav_rest_x', $c)); }],
    'security file missing, Web 2.0 switched off' => ['missing', static function ($d, $f) use ($disable) { @unlink($f); $disable($d); }],
] as $name => [$why, $mutate]) {
    [$code, $r, $raw] = $sec_run($name, $mutate);
    rg_check("core ($name): loads with no PHP error and no output; Industry Hub and the Web 2.0 loader unaffected", 0 === $code && is_array($r) && 0 === $r['errors'] && '' === $r['out'] && true === $r['ih'] && [] === $r['web2']['modules'], $raw);
    rg_check("core ($name): fails closed: navigation renders nothing, REST returns no menu blocks to non-editors, every navigation filter still runs", is_array($r) && true === $r['closed'] && '' === $r['nav'] && 'stripped' === $r['rest'], is_array($r) ? $r['rest'] : '');
    rg_check("core ($name): the reason is logged once and shown to administrators (no path)", is_array($r) && '' !== $r['problem'] && ('' === $why || $why === $r['problem'] || 0 === strpos($r['problem'], 'failed to load')) && 1 === substr_count($r['log'], 'OmochiX Core: the security layer') && false !== strpos($r['notice'], 'セキュリティ層') && '' === $r['notice_editor'] && false === strpos($r['notice'], $TMP), is_array($r) ? $r['problem'] . ' | ' . $r['log'] : '');
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

// ========================================================= module files
// Module files may ship before they are listed (each module stream adds its
// own folder files). The fixed list decides what loads, never the folder:
// while the list is empty nothing in it runs, every file in it already
// follows the loader's rules, and a listed module still has to pass them.
/** Replace the fixed list in a copy of a loader (exactly one, else the test stops). */
function rg_set_list($file, $fn, array $list) {
    $n = 0;
    $c = preg_replace('/(function ' . preg_quote($fn, '/') . '\(\) \{\s*return )array\(\);/', '${1}' . str_replace(['\\', '$'], ['\\\\', '\\$'], var_export($list, true)) . ';', (string) file_get_contents($file), -1, $n);
    if (1 !== $n) {
        fwrite(STDERR, "cannot find the list in $file\n");
        exit(2);
    }
    file_put_contents($file, $c);
}
/** A module file: marker, body, end marker (each part can be broken on purpose). */
function rg_module($id, $body, $api = 1, $end = true) {
    return "<?php\n// omochix-web2-module: $id api=$api\n$body\n" . ($end ? "// omochix-web2-module: $id api=$api (end)\n" : '');
}
$sides = [
    'core'  => ['dir' => $plugin, 'sub' => 'includes/web2', 'loader' => 'includes/web2-loader.php', 'list_fn' => 'omochix_core_web2_modules', 'p' => 'omochix_core_', 'now' => $now, 'notice' => static function ($r) { return '' !== $r['notice']; },
                'child' => static function ($d) { return rg_child(['--core'], ['IH_PLUGIN_DIR' => $d]); }],
    'theme' => ['dir' => $theme, 'sub' => 'inc/web2', 'loader' => 'inc/web2-loader.php', 'list_fn' => 'omochix_web2_modules', 'p' => 'omochix_', 'now' => $tnow, 'notice' => static function ($r) { return [] !== preg_grep('/^admin_notices@10\/1:omochix_web2_notice$/', $r['hooks']); },
                'child' => static function ($d) { return rg_child(['--theme', $d]); }],
];
foreach ($sides as $side => $s) {
    $n = $s['now'];
    $src = (string) file_get_contents("{$s['dir']}/{$s['loader']}");
    rg_check("$side: the fixed module list is empty in the source (one list, returns array())", 1 === preg_match_all('/function ' . $s['list_fn'] . '\(\) \{\s*return array\(\);\s*\}/', $src) && 1 === substr_count($src, 'function ' . $s['list_fn'] . '('));
    rg_check("$side (this tree): the module list is empty at run time and nothing was tried", is_array($n) && [] === $n['list'] && [] === $n['web2']['modules']);

    $rep = is_array($n) ? $n['report'] : ['files' => [], 'entries' => []];
    $declared = [];
    // The security layer (sec stream) sits in the Core module folder but is not a module: omochix-core.php
    // requires it on every request (checked above), so its functions are expected to exist.
    $always = 'core' === $side ? ['includes/web2/security.php'] : [];
    foreach ($rep['files'] as $rel => $info) { if (!in_array($rel, $always, true)) { $declared = array_merge($declared, $info['functions'] ?? []); } }
    foreach ($always as $rel) {
        if (isset($rep['files'][$rel])) {
            rg_check("$side: the always-loaded $rel is no module entry, follows the loader rules and ran", !isset($rep['entries'][$rel]) && !isset($rep['files'][$rel]['error']) && [] !== ($rep['files'][$rel]['functions'] ?? []) && [] === array_diff($rep['files'][$rel]['functions'], $n['functions']));
        }
    }
    $bad_files = array_keys(array_filter($rep['files'], static function ($i) { return isset($i['error']); }));
    $bad_entries = array_filter($rep['entries'], static function ($e) { return '' !== $e['check']; });
    $ids = array_column($rep['entries'], 'id');
    $count = count($rep['files']) . ' files, ' . count($rep['entries']) . ' entries';
    rg_check("$side: no function of the module folder exists after loading ($count): nothing in it ran", [] === array_intersect($declared, $n['functions']), implode(',', array_intersect($declared, $n['functions'])));
    rg_check("$side: every module folder file follows the loader rules (syntax, no namespace or output, omochix_ prefix)", [] === $bad_files, json_encode(array_intersect_key($rep['files'], array_flip($bad_files))));
    rg_check("$side: every module entry passes the loader check (marker, API version, prefix, no redeclare)", [] === $bad_entries, json_encode($bad_entries));
    rg_check("$side: module ids are unique", count($ids) === count(array_unique($ids)));

    // An unlisted module with side effects in the folder: still nothing runs.
    $d = "$TMP/$side-unlisted";
    rg_copy_tree($s['dir'], $d);
    @mkdir("$d/{$s['sub']}", 0700, true);
    $flag = "$TMP/$side-unlisted-ran";
    file_put_contents("$d/{$s['sub']}/zz-canary.php", rg_module('zz-canary', "file_put_contents(" . var_export($flag, true) . ", 'ran');\nfunction {$s['p']}zz_canary() { return 1; }\nadd_action('init', '{$s['p']}zz_canary');"));
    [$code, $r, $raw] = $s['child']($d);
    rg_check("$side (unlisted module in the folder): loads with no PHP error and no output", 0 === $code && is_array($r) && in_array($r['errors'], [0, []], true) && '' === $r['out'], $raw);
    rg_check("$side (unlisted module in the folder): it did not run (no side effect, no function, no hook)", is_array($r) && !file_exists($flag) && !in_array("{$s['p']}zz_canary", $r['functions'], true) && [] === $r['web2']['modules']
        && ('core' === $side ? $r['snap'] === $n['snap'] : $r['hooks'] === $n['hooks']));

    // Listed modules that break a rule are refused; one that is listed but off is not loaded.
    $d = "$TMP/$side-listed";
    rg_copy_tree($s['dir'], $d);
    @mkdir("$d/{$s['sub']}", 0700, true);
    $p = $s['p'];
    $existing = current(array_filter($n['functions'], static function ($f) use ($p) { return 0 === strpos($f, $p) && 0 !== strpos($f, $p . 'web2_'); }));
    $fixtures = [
        'zz-ok'         => [rg_module('zz-ok', "function {$p}zz_ok() { return 1; }"), 'loaded'],
        'zz-off'        => [rg_module('zz-off', "function {$p}zz_off_ran() { return 1; }"), 'off', false],
        'zz-bad-marker' => [rg_module('zz-bad-marker', "function {$p}zz_bad_marker_ran() { return 1; }", 1, false), 'bad-marker'],
        'zz-api'        => [rg_module('zz-api', "function {$p}zz_api_ran() { return 1; }", 999), 'api-mismatch'],
        'zz-prefix'     => [rg_module('zz-prefix', "function zz_prefix_ran() { return 1; }"), 'prefix'],
        'zz-redeclare'  => [rg_module('zz-redeclare', "function $existing() { return 1; }"), 'redeclare'],
        'zz-namespace'  => [rg_module('zz-namespace', "namespace Zz;\nfunction {$p}zz_namespace_ran() { return 1; }"), 'namespace'],
        'zz-html'       => [rg_module('zz-html', "function {$p}zz_html_ran() { return 1; }\n?>\n<p>leak</p>\n<?php"), 'output'],
        // Refused only while it runs (the loader cannot tell from the tokens):
        // its output is thrown away and its hooks are taken out again.
        'zz-echo'       => [rg_module('zz-echo', "function {$p}zz_echo_loaded() { return 1; }\nadd_action('init', '{$p}zz_echo_loaded');\necho 'leak';"), 'output'],
    ];
    $list = [];
    foreach ($fixtures as $id => $f) {
        file_put_contents("$d/{$s['sub']}/$id.php", $f[0]);
        $list[$id] = ['file' => "{$s['sub']}/$id.php", 'enabled' => $f[2] ?? true];
    }
    $list['zz-path'] = ['file' => "{$s['sub']}/../" . basename($s['loader']), 'enabled' => true];
    $fixtures['zz-path'] = ['', 'bad-path'];
    rg_set_list("$d/{$s['loader']}", $s['list_fn'], $list);
    [$code, $r, $raw] = $s['child']($d);
    $states = is_array($r) ? array_map(static function ($m) { return $m['state']; }, $r['web2']['modules']) : [];
    $want = array_map(static function ($f) { return $f[1]; }, $fixtures);
    ksort($states); ksort($want);
    rg_check("$side (listed modules that break the rules): loads with no PHP error and no output", 0 === $code && is_array($r) && in_array($r['errors'], [0, []], true) && '' === $r['out'], $raw);
    rg_check("$side (listed modules that break the rules): each is refused for its reason; only the valid one loads", $states === $want, json_encode(array_diff_assoc($states, $want)));
    $ran = array_values(array_filter(is_array($r) ? $r['functions'] : [], static function ($f) { return (bool) preg_match('/zz_[a-z_]+_ran$/', $f); }));
    rg_check("$side (listed modules that break the rules): no module refused before running, or switched off, ran", is_array($r) && [] === $ran && in_array("{$p}zz_ok", $r['functions'], true), implode(',', $ran));
    if ('core' === $side) {
        // The theme harness has no remove_filter (tests/web2/hooks/run.php covers the theme's unhooking).
        $echo_hooks = is_array($r) ? array_filter($r['snap']['hooks'], static function ($l) use ($p) { return [] !== preg_grep('/zz_echo_loaded$/', $l); }) : [1];
        rg_check('core (listed modules that break the rules): the module that printed while loading has no hook left and printed nothing', is_array($r) && [] === $echo_hooks && '' === $r['out']);
    }
    rg_check("$side (listed modules that break the rules): administrators are told", is_array($r) && $s['notice']($r));
    if ('core' === $side) {
        rg_check('core (listed modules that break the rules): Industry Hub module still loaded', is_array($r) && true === $r['ih']);
    }

    // The modules this tree ships, listed and enabled: they load, and nothing public opens.
    $entries = $rep['entries'];
    if ([] === $entries) {
        rg_check("$side: no module entry ships in this tree (nothing to enable)", [] === $rep['files']);
        continue;
    }
    $d = "$TMP/$side-enabled";
    rg_copy_tree($s['dir'], $d);
    $list = [];
    foreach ($entries as $rel => $e) { $list[$e['id']] = ['file' => $rel, 'enabled' => true]; }
    rg_set_list("$d/{$s['loader']}", $s['list_fn'], $list);
    [$code, $r, $raw] = $s['child']($d);
    $states = is_array($r) ? array_map(static function ($m) { return $m['state']; }, $r['web2']['modules']) : [];
    rg_check("$side (shipped modules enabled): loads with no PHP error and no output", 0 === $code && is_array($r) && in_array($r['errors'], [0, []], true) && '' === $r['out'], $raw);
    rg_check("$side (shipped modules enabled): every module loads (" . implode(',', array_keys($list)) . ')', count($states) === count($list) && [] === array_diff($states, ['loaded']), json_encode($states));
    rg_check("$side (shipped modules enabled): each public gate a module has stays closed", is_array($r) && [] === array_diff($r['gates'], ['false', 'none']), is_array($r) ? json_encode($r['gates']) : '');
    if ('core' === $side) {
        $new_pt = is_array($r) ? array_diff_key($r['snap']['post_types'], $n['snap']['post_types']) : [];
        $new_tx = is_array($r) ? array_diff_key($r['snap']['taxonomies'], $n['snap']['taxonomies']) : [];
        $open = [];
        foreach ($new_pt as $k => $a) { if (!empty($a['public']) || !empty($a['publicly_queryable']) || !empty($a['rewrite']) || !empty($a['has_archive'])) { $open[] = "post type $k"; } }
        foreach ($new_tx as $k => $t) { $a = $t['args']; if (!empty($a['public']) || !empty($a['publicly_queryable']) || !empty($a['rewrite'])) { $open[] = "taxonomy $k"; } }
        rg_check('core (shipped modules enabled): no rewrite rule added, every new post type and taxonomy is closed', is_array($r) && $r['snap']['rewrite'] === $n['snap']['rewrite'] && [] === $open, implode(',', $open));
        rg_check('core (shipped modules enabled): existing registrations unchanged, Industry Hub still loaded, no notice', is_array($r) && array_intersect_key($r['snap']['post_types'], $n['snap']['post_types']) === $n['snap']['post_types'] && array_intersect_key($r['snap']['taxonomies'], $n['snap']['taxonomies']) === $n['snap']['taxonomies'] && true === $r['ih'] && '' === $r['notice']);
    } else {
        rg_check('theme (shipped modules enabled): the existing hooks are all still there, in order', is_array($r) && array_values(array_intersect($r['hooks'], $n['hooks'])) === $n['hooks']);
    }
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
