<?php
/**
 * Web 2.0 module loaders (Core includes/web2-loader.php, theme
 * inc/web2-loader.php): unit, fatal-error and side-effect tests.
 *
 * Every case runs in its own PHP process against a COPY of the loader in a
 * temporary plugin (or theme) folder with fixture modules, so a fatal error
 * shows as the child's exit code instead of stopping this runner, and no
 * case sees another's functions or hooks. Both loaders run the same cases.
 * Needs no WordPress (a small recording stub stands in for it).
 *
 * Run: php tests/web2/hooks/run.php
 */

const OMX_SIDES = [
    'core'  => ['loader' => 'wp-plugin/omochix-core/includes/web2-loader.php', 'rel' => 'includes/web2-loader.php', 'mod' => 'includes/web2/', 'fn' => 'omochix_core_web2_', 'api' => 'OMOCHIX_CORE_WEB2_LOADER_API', 'cap' => 'activate_plugins', 'ctx' => 'core'],
    'theme' => ['loader' => 'wp-theme/omochix-theme/inc/web2-loader.php', 'rel' => 'inc/web2-loader.php', 'mod' => 'inc/web2/', 'fn' => 'omochix_web2_', 'api' => 'OMOCHIX_WEB2_LOADER_API', 'cap' => 'switch_themes', 'ctx' => 'theme'],
];

// ===================================================================== child
if ('--child' === ($argv[1] ?? '')) {
    $job = json_decode((string) file_get_contents($argv[2]), true);
    define('ABSPATH', '/');
    if (!empty($job['disabled_const'])) { define('OMOCHIX_WEB2_DISABLED', $job['disabled_const'] === 'true'); }
    $GLOBALS['calls'] = []; $GLOBALS['wp_filter'] = []; $GLOBALS['caps'] = (array) ($job['caps'] ?? []);
    $GLOBALS['php_errors'] = [];
    set_error_handler(static function ($no, $str, $file, $line) { $GLOBALS['php_errors'][] = basename($file) . ":$line $str"; return true; });
    class WP_Hook { public $callbacks = []; }
    function omx_rec($fn, $args) { $GLOBALS['calls'][] = [$fn, $args[0] ?? null]; }
    function add_filter($hook, $cb, $priority = 10, $args = 1) { omx_rec('add_filter', [$hook]); $GLOBALS['wp_filter'][$hook] ??= new WP_Hook(); $GLOBALS['wp_filter'][$hook]->callbacks[$priority][] = ['function' => $cb, 'accepted_args' => $args]; return true; }
    function add_action($hook, $cb, $priority = 10, $args = 1) { omx_rec('add_action', [$hook]); $GLOBALS['wp_filter'][$hook] ??= new WP_Hook(); $GLOBALS['wp_filter'][$hook]->callbacks[$priority][] = ['function' => $cb, 'accepted_args' => $args]; return true; }
    function remove_filter($hook, $cb, $priority = 10) { omx_rec('remove_filter', [$hook]); foreach ($GLOBALS['wp_filter'][$hook]->callbacks[$priority] ?? [] as $i => $h) { if ($h['function'] === $cb) { unset($GLOBALS['wp_filter'][$hook]->callbacks[$priority][$i]); } } return true; }
    function apply_filters($hook, $value, ...$args) { omx_rec('apply_filters', [$hook]); foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $list) { foreach ($list as $h) { $value = call_user_func_array($h['function'], array_slice(array_merge([$value], $args), 0, $h['accepted_args'])); } } return $value; }
    function do_action($hook, ...$args) { foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $list) { foreach ($list as $h) { call_user_func_array($h['function'], array_slice($args, 0, $h['accepted_args'])); } } }
    function current_user_can($cap) { omx_rec('current_user_can', [$cap]); return !empty($GLOBALS['caps'][$cap]); }
    function esc_html($s) { omx_rec('esc_html', [$s]); return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
    function __($s, $d = 'default') { return $s; }
    // Forbidden for the loader: recorded so a call is a failure.
    foreach (['register_rest_route', 'add_rewrite_rule', 'flush_rewrite_rules', 'add_menu_page', 'register_post_type', 'register_taxonomy', 'update_option', 'add_option', 'delete_option', 'set_transient', 'add_role', 'wp_insert_post', 'update_post_meta'] as $f) {
        eval("function $f(...\$a) { omx_rec('$f', \$a); return true; }"); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test stub only
    }
    foreach ((array) ($job['pre'] ?? []) as $code) { eval($code); } // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test fixture only
    $fn = $job['fn'];
    $funcs_before = get_defined_functions()['user'];
    $consts_before = array_keys(get_defined_constants(true)['user'] ?? []);
    ob_start();
    foreach ((array) $job['require'] as $file) { require $file; }
    $load_out = (string) ob_get_clean();
    $out = ['load_output' => $load_out, 'calls_at_load' => $GLOBALS['calls']];
    $out['new_functions'] = array_values(array_diff(get_defined_functions()['user'], $funcs_before));
    $out['new_constants'] = array_values(array_diff(array_keys(get_defined_constants(true)['user'] ?? []), $consts_before));
    $out['hooks_at_load'] = array_keys(array_filter($GLOBALS['wp_filter'], static function ($h) { return (bool) array_filter($h->callbacks); }));
    $before = json_encode(array_map(static function ($h) { return array_map(static function ($l) { return array_map(static function ($c) { return is_string($c['function']) ? $c['function'] : '{closure}'; }, array_values($l)); }, array_filter($h->callbacks)); }, $GLOBALS['wp_filter']));
    if (isset($job['modules'])) {
        $GLOBALS['calls'] = [];
        $level = ob_get_level();
        ob_start();
        $state = ($fn . 'load_modules')($job['modules'], $job['dir']);
        if (!empty($job['load_twice'])) { $state = ($fn . 'load_modules')($job['modules'], $job['dir']); }
        $out['run_output'] = (string) ob_get_clean();
        $out['ob_level_kept'] = ob_get_level() === $level;
        $out['calls_at_run'] = $GLOBALS['calls'];
    }
    $out['state'] = ($fn . 'state')();
    $out['status'] = [];
    foreach (array_keys($out['state']['modules']) as $id) { $out['status'][$id] = ($fn . 'module_status')($id); }
    $out['hooks'] = array_map(static function ($h) { return array_map(static function ($l) { return array_map(static function ($c) { return is_string($c['function']) ? $c['function'] : '{closure}'; }, array_values($l)); }, array_filter($h->callbacks)); }, $GLOBALS['wp_filter']);
    $out['hooks'] = array_filter($out['hooks']);
    $out['hooks_unchanged'] = json_encode(array_filter(json_decode($before, true))) === json_encode($out['hooks']);
    $out['globals'] = array_intersect_key($GLOBALS, array_flip(['omx_counter', 'omx_side_effect']));
    $GLOBALS['calls'] = [];
    ob_start(); do_action('admin_notices'); $out['notice'] = (string) ob_get_clean();
    $out['notice_calls'] = $GLOBALS['calls'];
    $out['php_errors'] = $GLOBALS['php_errors'];
    foreach ((array) ($job['post'] ?? []) as $k => $code) { $out['post'][$k] = eval($code); } // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test fixture only
    echo "\n@@JSON@@" . json_encode($out);
    exit(0);
}

// ==================================================================== runner
$REPO = realpath(__DIR__ . '/../../..');
$pass = 0; $fail = 0;
function check($name, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? " -- $detail" : '') . "\n"; } }

$TMP_ROOT = sys_get_temp_dir() . '/omx-web2-hooks-' . getmypid() . '-' . bin2hex(random_bytes(4));
mkdir($TMP_ROOT, 0700, true);
register_shutdown_function(static function () use ($TMP_ROOT) {
    if (!is_dir($TMP_ROOT)) { return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($TMP_ROOT, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $p = $f->getPathname(); (is_link($p) || $f->isFile()) ? unlink($p) : rmdir($p); }
    rmdir($TMP_ROOT);
});

/** A fresh fake plugin/theme folder with a copy of the loader. */
function omx_site($side, $loader_source = null) {
    global $TMP_ROOT, $REPO;
    static $n = 0;
    $dir = $TMP_ROOT . '/' . $side . '-' . (++$n);
    $s = OMX_SIDES[$side];
    mkdir($dir . '/' . dirname($s['rel']), 0700, true);
    mkdir($dir . '/' . rtrim($s['mod'], '/'), 0700, true);
    file_put_contents($dir . '/' . $s['rel'], $loader_source ?? file_get_contents($REPO . '/' . $s['loader']));
    return realpath($dir);
}

/** Module file text with the markers. */
function omx_module($id, $body, $api = 1) {
    return "<?php\n// omochix-web2-module: $id api=$api\n" . $body . "\n// omochix-web2-module: $id api=$api (end)\n";
}

/** Run a child; returns [exit code, decoded result or null, raw output]. */
function omx_child(array $job) {
    global $TMP_ROOT;
    $jf = tempnam($TMP_ROOT, 'job');
    file_put_contents($jf, json_encode($job));
    $cmd = escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d log_errors=0 ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($jf) . ' 2>&1';
    exec($cmd, $lines, $code);
    $raw = implode("\n", $lines);
    $pos = strrpos($raw, '@@JSON@@');
    $res = false === $pos ? null : json_decode(substr($raw, $pos + 8), true);
    return [$code, $res, $raw];
}

/** Load a site's loader, then load_modules($modules). */
function omx_run($side, $dir, $modules, array $extra = []) {
    $s = OMX_SIDES[$side];
    return omx_child(array_merge(['fn' => $s['fn'], 'require' => [$dir . '/' . $s['rel']], 'modules' => $modules, 'dir' => $dir], $extra));
}

$CLEAN = static function ($r) { return is_array($r) && [] === $r['php_errors'] && '' === $r['load_output'] && '' === ($r['run_output'] ?? '') && ($r['ob_level_kept'] ?? true); };

foreach (OMX_SIDES as $side => $s) {
    $P = "[$side] ";
    $fn = $s['fn'];
    $mod = $s['mod'];
    $good = static function ($id, $extra = '') use ($fn) {
        $f = 'omochix_t_' . str_replace('-', '_', $id);
        return omx_module($id, "if (!defined('ABSPATH')) { exit; }\n\$GLOBALS['omx_counter'] = (\$GLOBALS['omx_counter'] ?? 0) + 1;\nfunction {$f}_init() { return 'ok'; }\nadd_action('init', '{$f}_init');\n" . $extra);
    };

    // ---------------------------------------------------------- empty list
    $dir = omx_site($side);
    [$code, $r] = omx_child(['fn' => $fn, 'require' => [$dir . '/' . $s['rel']]]);
    check($P . 'empty list: loads with no error and no output', 0 === $code && $CLEAN($r), (string) $code);
    check($P . 'empty list: calls no WordPress function and adds no hook', [] === ($r['calls_at_load'] ?? null) && [] === ($r['hooks_at_load'] ?? null));
    check($P . 'empty list: the loader ran and loaded no module', true === ($r['state']['ran'] ?? null) && [] === ($r['state']['modules'] ?? null) && false === $r['state']['disabled']);
    check($P . 'empty list: defines only its own functions and one API constant', [] !== $r['new_functions'] && [] === array_filter($r['new_functions'], static function ($f) use ($fn) { return 0 !== strpos($f, $fn); }) && [$s['api']] === $r['new_constants']);
    check($P . 'empty list: no admin notice', '' === $r['notice']);
    check($P . 'the fixed list in the shipped loader is empty', 1 === preg_match('/function ' . $fn . 'modules\(\) \{\s*return array\(\);\s*\}/', (string) file_get_contents($REPO . '/' . $s['loader'])));
    if ('theme' === $side) {
        check($P . 'no theme loader function uses the Core prefix', [] === array_filter($r['new_functions'], static function ($f) { return 0 === strpos($f, 'omochix_core_'); }));
    }

    // ------------------------------------------- the real boot path (list)
    $src = (string) file_get_contents($REPO . '/' . $s['loader']);
    $src = preg_replace('/(function ' . $fn . 'modules\(\) \{\s*)return array\(\);/', "\$1return array( 'alpha' => array( 'file' => '{$mod}alpha.php', 'enabled' => true ), 'beta' => array( 'file' => '{$mod}beta.php', 'enabled' => false ) );", $src, 1, $cnt);
    $dir = omx_site($side, $src);
    file_put_contents("$dir/{$mod}alpha.php", $good('alpha'));
    file_put_contents("$dir/{$mod}beta.php", $good('beta'));
    [$code, $r] = omx_child(['fn' => $fn, 'require' => [$dir . '/' . $s['rel']], 'post' => ['boot_again' => "return {$fn}boot()['modules'];", 'counter' => "return \$GLOBALS['omx_counter'] ?? 0;"]]);
    check($P . 'boot: an enabled module on the list loads from the loader folder', 1 === $cnt && 0 === $code && $CLEAN($r) && 'loaded' === ($r['status']['alpha'] ?? null) && isset($r['hooks']['init']));
    check($P . 'boot: a listed module with enabled => false stays off (not read)', 'off' === ($r['status']['beta'] ?? null));
    check($P . 'boot: a second boot loads nothing again', 1 === ($r['post']['counter'] ?? null) && 'loaded' === ($r['post']['boot_again']['alpha']['state'] ?? null));
    check($P . 'boot: only the module\'s own hook is added (no notice hook when all is well)', ['init'] === array_keys($r['hooks']));
    check($P . 'boot: the filter is the only filter read, once per enabled module', [['apply_filters', 'omochix_web2_module_enabled']] === array_values(array_filter($r['calls_at_load'], static function ($c) { return 'apply_filters' === $c[0]; })));
    check($P . 'status of an unknown id is "unknown"', 'unknown' === omx_child(['fn' => $fn, 'require' => [$dir . '/' . $s['rel']], 'post' => ['x' => "return {$fn}module_status('nope');"]])[1]['post']['x']);

    // ------------------------------------------------- duplicate loading
    $dir = omx_site($side);
    $dir2 = omx_site($side);
    file_put_contents("$dir/{$mod}alpha.php", $good('alpha'));
    [$code, $r, $raw] = omx_child(['fn' => $fn, 'require' => [$dir . '/' . $s['rel'], $dir2 . '/' . $s['rel'], $dir . '/' . $s['rel']], 'modules' => ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => true]], 'dir' => $dir, 'load_twice' => true]);
    check($P . 'duplicate: a second copy of the loader from another path is not a fatal error', 0 === $code && $CLEAN($r), $raw);
    check($P . 'duplicate: the module file runs once even when asked twice', 1 === ($r['globals']['omx_counter'] ?? null) && 'loaded' === ($r['status']['alpha'] ?? null));
    check($P . 'duplicate: the same file under a second id is refused by its marker', 'bad-marker' === omx_run($side, $dir, ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => true], 'alpha2' => ['file' => "{$mod}alpha.php", 'enabled' => true]])[1]['status']['alpha2']);

    // ------------------------------------------------------- off / filter
    $dir = omx_site($side);
    file_put_contents("$dir/{$mod}alpha.php", $good('alpha'));
    foreach (['enabled missing' => ['file' => "{$mod}alpha.php"], 'enabled false' => ['file' => "{$mod}alpha.php", 'enabled' => false], 'enabled "1" (not true)' => ['file' => "{$mod}alpha.php", 'enabled' => '1'], 'enabled 1 (not true)' => ['file' => "{$mod}alpha.php", 'enabled' => 1], 'entry not an array' => "{$mod}alpha.php"] as $name => $entry) {
        [$code, $r] = omx_run($side, $dir, ['alpha' => $entry]);
        check($P . "default off: $name -> off, file not read", 0 === $code && 'off' === ($r['status']['alpha'] ?? null) && null === ($r['globals']['omx_counter'] ?? null) && [] === $r['hooks']);
    }
    foreach (['returns false' => 'return false;', 'returns 1' => 'return 1;', 'returns null' => 'return null;'] as $name => $ret) {
        [$code, $r] = omx_run($side, $dir, ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => true]], ['pre' => ["add_filter('omochix_web2_module_enabled', function (\$on, \$id, \$ctx) { $ret }, 10, 3);"]]);
        check($P . "filter $name -> off", 'off' === ($r['status']['alpha'] ?? null) && null === ($r['globals']['omx_counter'] ?? null));
    }
    [$code, $r] = omx_run($side, $dir, ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => false]], ['pre' => ["add_filter('omochix_web2_module_enabled', function () { return true; });"]]);
    check($P . 'filter returning true cannot switch on a module that is off', 'off' === ($r['status']['alpha'] ?? null) && null === ($r['globals']['omx_counter'] ?? null));
    [$code, $r] = omx_run($side, $dir, ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => true]], ['pre' => ["add_filter('omochix_web2_module_enabled', function (\$on, \$id, \$ctx) { \$GLOBALS['omx_side_effect'] = [\$id, \$ctx]; return \$on; }, 10, 3);"]]);
    check($P . 'filter gets the id and the side', ['alpha', $s['ctx']] === ($r['globals']['omx_side_effect'] ?? null) && 'loaded' === $r['status']['alpha']);

    // ----------------------------------------------------- disable switch
    [$code, $r] = omx_run($side, $dir, ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => true]], ['disabled_const' => 'true']);
    check($P . 'OMOCHIX_WEB2_DISABLED true: nothing loads, state disabled', 0 === $code && true === $r['state']['disabled'] && 'disabled' === $r['status']['alpha'] && null === ($r['globals']['omx_counter'] ?? null) && [] === $r['hooks'] && '' === $r['notice']);
    [$code, $r] = omx_run($side, $dir, ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => true]], ['disabled_const' => 'false']);
    check($P . 'OMOCHIX_WEB2_DISABLED false: loads normally', 'loaded' === $r['status']['alpha']);

    // ------------------------------------------------------- id and path
    $bad = [
        'bad-id' => ['Alpha' => "{$mod}alpha.php", 'al pha' => "{$mod}alpha.php", '../x' => "{$mod}alpha.php", '1a' => "{$mod}alpha.php", 'a<b>' => "{$mod}alpha.php", "alpha\n" => "{$mod}alpha.php"],
        'bad-path' => ['p1' => "{$mod}../" . OMX_SIDES[$side]['rel'], 'p2' => "{$mod}sub/alpha.php", 'p3' => '/etc/passwd', 'p4' => "{$mod}alpha.PHP", 'p5' => "{$mod}alpha.php\0.txt", 'p6' => "{$mod}.alpha.php", 'p7' => 'alpha.php', 'p8' => "../{$mod}alpha.php", 'p9' => "{$mod}alpha.inc", 'p10' => ('core' === $side ? '../../themes/omochix-theme/inc/web2/alpha.php' : '../../plugins/omochix-core/includes/web2/alpha.php'), 'p11' => " {$mod}alpha.php", 'p12' => "{$mod}alpha.php/", 'p13' => "{$mod}alpha.php\n"],
    ];
    foreach ($bad as $reason => $cases) {
        foreach ($cases as $id => $file) {
            [$code, $r] = omx_run($side, $dir, [$id => ['file' => $file, 'enabled' => true]]);
            check($P . "$reason: " . json_encode([$id => $file]) . ' refused', 0 === $code && $reason === ($r['status'][$id] ?? null) && null === ($r['globals']['omx_counter'] ?? null), json_encode($r['status'] ?? $code));
        }
    }
    [$code, $r] = omx_run($side, $dir, ['alpha' => ['file' => ['x'], 'enabled' => true], 'beta' => ['file' => null, 'enabled' => true]]);
    check($P . 'bad-path: a non-string file is refused', 'bad-path' === $r['status']['alpha'] && 'bad-path' === $r['status']['beta']);

    // ------------------------------------------------- links and missing
    $dir = omx_site($side);
    $outside = $TMP_ROOT . "/outside-$side.php";
    file_put_contents($outside, $good('alpha'));
    symlink($outside, "$dir/{$mod}alpha.php");
    symlink("$dir/{$mod}nowhere.php", "$dir/{$mod}dangling.php");
    $real = omx_site($side);
    file_put_contents("$real/{$mod}gamma.php", $good('gamma'));
    $linked = omx_site($side);
    rmdir("$linked/" . rtrim($mod, '/'));
    symlink("$real/" . rtrim($mod, '/'), "$linked/" . rtrim($mod, '/'));
    [$code, $r] = omx_run($side, $dir, ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => true], 'dangling' => ['file' => "{$mod}dangling.php", 'enabled' => true], 'gone' => ['file' => "{$mod}gone.php", 'enabled' => true]]);
    check($P . 'a module file that is a symbolic link is refused', 'bad-path' === ($r['status']['alpha'] ?? null) && null === ($r['globals']['omx_counter'] ?? null));
    check($P . 'a dangling symbolic link is refused', 'bad-path' === ($r['status']['dangling'] ?? null));
    check($P . 'a missing file is "missing" (and the site keeps running)', 0 === $code && 'missing' === ($r['status']['gone'] ?? null));
    [$code, $r] = omx_run($side, $linked, ['gamma' => ['file' => "{$mod}gamma.php", 'enabled' => true]]);
    check($P . 'a module folder that is a symbolic link is refused', 'bad-path' === ($r['status']['gamma'] ?? null) && null === ($r['globals']['omx_counter'] ?? null));
    mkdir("$dir/{$mod}adir.php");
    check($P . 'a directory named like a module is refused', 'bad-path' === omx_run($side, $dir, ['adir' => ['file' => "{$mod}adir.php", 'enabled' => true]])[1]['status']['adir']);
    file_put_contents("$dir/{$mod}big.php", omx_module('big', str_repeat("// padding\n", 50000)));
    check($P . 'a file over 512 KB is refused', 'too-large' === omx_run($side, $dir, ['big' => ['file' => "{$mod}big.php", 'enabled' => true]])[1]['status']['big']);

    // ------------------------------------------------- markers and api
    $dir = omx_site($side);
    $full = $good('alpha');
    $variants = [
        'no marker' => [preg_replace('#// omochix-web2-module: [^\n]*\n#', '', $full), 'bad-marker'],
        'marker not on line 2' => ["<?php\n\n" . substr($full, 6), 'bad-marker'],
        'marker for another id' => [str_replace('module: alpha ', 'module: beta ', $full), 'bad-marker'],
        'end marker missing (upload cut short)' => [substr($full, 0, (int) strrpos($full, '// omochix-web2-module')), 'bad-marker'],
        'cut in the middle' => [substr($full, 0, (int) (strlen($full) / 2)), 'bad-marker'],
        'end marker with another api' => [str_replace('api=1 (end)', 'api=2 (end)', $full), 'bad-marker'],
        'code after the end marker' => [$full . "\$GLOBALS['omx_counter'] = 99;\n", 'bad-marker'],
        'empty file' => ['', 'bad-marker'],
        'api=2 (newer module)' => [omx_module('alpha', "function omochix_t_alpha_init() {}", 2), 'api-mismatch'],
        'api=0 (older module)' => [omx_module('alpha', "function omochix_t_alpha_init() {}", 0), 'api-mismatch'],
        'CRLF line endings' => [str_replace("\n", "\r\n", $full), 'loaded'],
    ];
    foreach ($variants as $name => [$text, $want]) {
        file_put_contents("$dir/{$mod}alpha.php", $text);
        [$code, $r] = omx_run($side, $dir, ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => true]]);
        check($P . "marker: $name -> $want", 0 === $code && $want === ($r['status']['alpha'] ?? null) && ('loaded' === $want || null === ($r['globals']['omx_counter'] ?? null)), json_encode($r['status'] ?? $code));
    }

    // ---------------------------- syntax, namespace, output, declarations
    $dir = omx_site($side);
    $cases = [
        'syntax error' => ["function omochix_t_x( {\n", 'syntax'],
        'namespace' => ["namespace Omochix\\Glossary;\nfunction omochix_t_x() {}", 'namespace'],
        'HTML outside PHP at load' => ["?>\n<p>hello</p>\n<?php", 'output'],
        'function without the omochix_ prefix' => ["function glossary_init() {}", 'prefix'],
        'class without the omochix_ prefix' => ["class Glossary_Thing {}", 'prefix'],
        'one function declared twice in the file' => ["if (true) { function omochix_t_x() {} } else { function omochix_t_x() {} }", 'redeclare'],
        'function that already exists' => ["function omochix_t_exists() {}", 'redeclare'],
        'class that already exists' => ["class Omochix_T_Exists {}", 'redeclare'],
        'redeclares a loader function' => ["function {$fn}modules() { return []; }", 'redeclare'],
        'methods, closures, anonymous class and ::class are not declarations' => ["class Omochix_T_Ok { function a() {} static function b() {} }\n\$f = function () {}; \$g = static fn() => 1; \$o = new class { function c() {} };\n\$n = Omochix_T_Ok::class;\nfunction &omochix_t_ref() { static \$a = []; return \$a; }\ninterface Omochix_T_I {} trait Omochix_T_Tr { function d() {} }\nenum Omochix_T_E: string { case A = 'a'; function e() {} }", 'loaded'],
        'HTML inside a function is allowed' => ["function omochix_t_tpl() { ?>\n<p>x</p>\n<?php }", 'loaded'],
    ];
    foreach ($cases as $name => [$body, $want]) {
        $id = 'm' . substr(md5($name), 0, 8);
        file_put_contents("$dir/{$mod}$id.php", omx_module($id, $body));
        [$code, $r, $raw] = omx_run($side, $dir, [$id => ['file' => "{$mod}$id.php", 'enabled' => true]], ['pre' => ['function omochix_t_exists() {}', 'class Omochix_T_Exists {}']]);
        check($P . "precheck: $name -> $want (no fatal error)", 0 === $code && $want === ($r['status'][$id] ?? null) && $CLEAN($r), $want . ' vs ' . json_encode($r['status'] ?? $raw));
    }

    // ---------------------- failures while loading: hooks removed, others ok
    $dir = omx_site($side);
    file_put_contents("$dir/{$mod}first.php", $good('first'));
    file_put_contents("$dir/{$mod}throws.php", omx_module('throws', "function omochix_t_throws_a() {}\nadd_action('init', 'omochix_t_throws_a');\nadd_filter('the_content', 'omochix_t_throws_a', 5);\nadd_action('wp_head', function () {});\nthrow new RuntimeException('boom');"));
    file_put_contents("$dir/{$mod}errors.php", omx_module('errors', "function omochix_t_errors_a() {}\nadd_action('init', 'omochix_t_errors_a');\nomochix_t_undefined_function();"));
    file_put_contents("$dir/{$mod}prints.php", omx_module('prints', "function omochix_t_prints_a() {}\nadd_action('init', 'omochix_t_prints_a');\necho 'leak';"));
    file_put_contents("$dir/{$mod}buffer.php", omx_module('buffer', "function omochix_t_buffer_a() {}\nob_start(); echo 'x';"));
    file_put_contents("$dir/{$mod}last.php", $good('last'));
    $list = [];
    foreach (['first', 'throws', 'errors', 'prints', 'buffer', 'last'] as $id) { $list[$id] = ['file' => "{$mod}$id.php", 'enabled' => true]; }
    [$code, $r, $raw] = omx_run($side, $dir, $list, ['pre' => ["add_action('init', 'omochix_t_site_init'); function omochix_t_site_init() {}"]]);
    check($P . 'load failures: the request keeps running', 0 === $code && is_array($r), $raw);
    check($P . 'load failures: exception -> error, Error -> error, echo -> output, open buffer -> output', ['first' => 'loaded', 'throws' => 'error', 'errors' => 'error', 'prints' => 'output', 'buffer' => 'output', 'last' => 'loaded'] === ($r['status'] ?? null), json_encode($r['status'] ?? null));
    check($P . 'load failures: hooks of failed modules are removed, closures too', ['init' => [['omochix_t_site_init', 'omochix_t_first_init', 'omochix_t_last_init']], 'admin_notices' => [[$fn . 'notice']]] === array_map('array_values', $r['hooks'] ?? []), json_encode($r['hooks'] ?? null));
    check($P . 'load failures: nothing printed reaches the page, output buffers are back to their level', '' === $r['run_output'] && true === $r['ob_level_kept'] && [] === $r['php_errors']);
    check($P . 'load failures: the loader itself adds only the admin notice hook', ['add_action'] === array_values(array_unique(array_map(static function ($c) { return $c[0]; }, array_filter($r['calls_at_run'], static function ($c) { return in_array($c[0], ['add_action', 'add_filter'], true) && 'admin_notices' === $c[1]; })))));

    // ------------------------------------------------------------ notice
    [$code, $r] = omx_run($side, $dir, $list, ['caps' => [$s['cap'] => true]]);
    check($P . "notice: shown to users who can {$s['cap']}, ids and reasons only", false !== strpos($r['notice'], 'throws (error)') && false !== strpos($r['notice'], 'prints (output)') && false === strpos($r['notice'], 'first') && false === strpos($r['notice'], $TMP_ROOT) && false === strpos($r['notice'], '.php'));
    check($P . 'notice: text goes through esc_html', in_array('esc_html', array_column($r['notice_calls'], 0), true));
    [$code, $r] = omx_run($side, $dir, $list, ['caps' => ['edit_posts' => true, ('core' === $side ? 'switch_themes' : 'activate_plugins') => true]]);
    check($P . 'notice: not shown to other users', '' === $r['notice']);
    [$code, $r] = omx_run($side, $dir, ['first' => $list['first'], 'off' => ['file' => "{$mod}off.php"]], ['caps' => [$s['cap'] => true]]);
    check($P . 'notice: none (and no hook) when nothing failed', '' === $r['notice'] && !isset($r['hooks']['admin_notices']));

    // ------------------------------------- permission and public boundary
    $dir = omx_site($side);
    file_put_contents("$dir/{$mod}alpha.php", $good('alpha'));
    [$code, $r] = omx_run($side, $dir, ['alpha' => ['file' => "{$mod}alpha.php", 'enabled' => true]], ['caps' => [$s['cap'] => true]]);
    $forbidden = ['register_rest_route', 'add_rewrite_rule', 'flush_rewrite_rules', 'add_menu_page', 'register_post_type', 'register_taxonomy', 'update_option', 'add_option', 'delete_option', 'set_transient', 'add_role', 'wp_insert_post', 'update_post_meta'];
    $made = array_column(array_merge($r['calls_at_load'], $r['calls_at_run'], $r['notice_calls']), 0);
    check($P . 'boundary: the loader registers no route/REST/menu/type and writes no option, meta, role or post', [] === array_intersect($forbidden, $made));
    check($P . 'boundary: the loader asks no capability unless there is a notice to show', !in_array('current_user_can', $made, true));
    check($P . 'boundary: the loader reads no filter except omochix_web2_module_enabled', [] === array_filter($r['calls_at_run'], static function ($c) { return 'apply_filters' === $c[0] && 'omochix_web2_module_enabled' !== $c[1]; }));

    // ----------------------------------------------------- static checks
    $code_src = (string) file_get_contents($REPO . '/' . $s['loader']);
    $tokens = token_get_all($code_src);
    $ids = []; $vars = []; $includes = []; $depth = 0; $top_functions = [];
    foreach ($tokens as $i => $t) {
        if (!is_array($t)) { if ('{' === $t) { $depth++; } elseif ('}' === $t) { $depth--; } continue; }
        if (T_CURLY_OPEN === $t[0] || T_DOLLAR_OPEN_CURLY_BRACES === $t[0]) { $depth++; }
        if (T_STRING === $t[0]) { $ids[] = strtolower($t[1]); }
        if (T_VARIABLE === $t[0]) { $vars[] = $t[1]; }
        if (in_array($t[0], [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_EVAL], true)) { $includes[] = $t[1]; }
        if (T_FUNCTION === $t[0] && 0 === $depth) { $top_functions[] = $t[2]; }
    }
    $bad_ids = ['register_rest_route', 'add_rewrite_rule', 'add_rewrite_tag', 'flush_rewrite_rules', 'add_menu_page', 'add_submenu_page', 'add_options_page', 'register_post_type', 'register_taxonomy', 'register_post_meta', 'register_meta', 'register_term_meta', 'update_option', 'add_option', 'delete_option', 'set_transient', 'set_site_transient', 'update_post_meta', 'add_post_meta', 'update_term_meta', 'update_user_meta', 'add_role', 'remove_role', 'add_cap', 'remove_cap', 'wp_insert_post', 'wp_update_post', 'wp_delete_post', 'wp_remote_get', 'wp_remote_post', 'wp_remote_request', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'header', 'setcookie', 'file_put_contents', 'fopen', 'unlink', 'mkdir', 'rename', 'copy', 'glob', 'scandir', 'opendir', 'add_filter', 'wp_enqueue_script', 'wp_enqueue_style', 'status_header', 'wp_redirect', 'wp_safe_redirect'];
    check($P . 'static: no route, rewrite, REST, menu, option, meta, role, post, HTTP, shell, file-write or directory-listing call', [] === array_intersect($bad_ids, $ids), implode(',', array_intersect($bad_ids, $ids)));
    check($P . 'static: no request data, no $wpdb', [] === array_intersect(['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER', '$_FILES', '$_ENV', '$_SESSION', '$wpdb'], $vars));
    check($P . 'static: exactly one require (the vetted module file), no include or eval', ['require_once'] === $includes);
    check($P . 'static: the only hook it adds is admin_notices', 1 === substr_count($code_src, 'add_action(') && 1 === substr_count($code_src, "add_action( 'admin_notices', '{$fn}notice' );"));
    check($P . 'static: every function is declared inside the run-time guard (no compile-time redeclare)', [] === $top_functions && false !== strpos($code_src, "if ( ! defined( '{$s['api']}' ) ) {"));
    check($P . 'static: the API is 1', false !== strpos($code_src, "define( '{$s['api']}', 1 );"));
}

// ======================================================= Core / theme parity
$core = (string) file_get_contents($REPO . '/' . OMX_SIDES['core']['loader']);
$theme = (string) file_get_contents($REPO . '/' . OMX_SIDES['theme']['loader']);
$code_only = static function ($src) { $out = ''; foreach (token_get_all($src) as $t) { if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) { continue; } $out .= is_array($t) ? $t[1] : $t; } return array_values(array_filter(array_map('trim', explode("\n", $out)), 'strlen')); };
$c = $code_only(str_replace(['omochix_core_web2_', 'OMOCHIX_CORE_WEB2_LOADER_API'], ['omochix_web2_', 'OMOCHIX_WEB2_LOADER_API'], $core));
$t = $code_only($theme);
$diff = [];
foreach ($c as $i => $line) { if (($t[$i] ?? null) !== $line) { $diff[] = $line . ' => ' . ($t[$i] ?? '-'); } }
$allowed = 4; // module folder, filter context, notice capability, notice text
check('parity: the theme loader is the Core loader except folder, filter side, notice capability and text', count($c) === count($t) && count($diff) === $allowed, count($diff) . " lines differ:\n" . implode("\n", $diff));

// =================================================== limits of the isolation
// Not prevented by the loader (PHP ends the request): kept here so the
// documentation stays honest, and to show what the prechecks do prevent.
$dir = omx_site('core');
file_put_contents("$dir/includes/web2/dupmethod.php", omx_module('dupmethod', "class Omochix_T_Dup { function a() {} function a() {} }"));
[$code] = omx_run('core', $dir, ['dupmethod' => ['file' => 'includes/web2/dupmethod.php', 'enabled' => true]]);
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg("$dir/includes/web2/dupmethod.php") . ' 2>&1', $o, $lint);
check('limit: a compile error that parses (a method declared twice) is fatal even through the loader', 0 !== $code);
check('limit: ... and php -l (Lint PHP in CI) reports it', 0 !== $lint);
file_put_contents("$dir/includes/web2/exits.php", omx_module('exits', 'exit(3);'));
check('limit: exit() in a module file ends the request', 3 === omx_run('core', $dir, ['exits' => ['file' => 'includes/web2/exits.php', 'enabled' => true]])[0]);
file_put_contents("$dir/includes/web2/redecl.php", omx_module('redecl', 'function omochix_t_exists() {}'));
[$code_raw] = omx_child(['fn' => 'omochix_core_web2_', 'require' => ["$dir/includes/web2-loader.php", "$dir/includes/web2/redecl.php"], 'pre' => ['function omochix_t_exists() {}']]);
[$code_ldr, $r] = omx_run('core', $dir, ['redecl' => ['file' => 'includes/web2/redecl.php', 'enabled' => true]], ['pre' => ['function omochix_t_exists() {}']]);
check('isolation: a plain require of a module that redeclares a function is fatal (try/catch cannot help)', 0 !== $code_raw);
check('isolation: through the loader the same module is refused before it runs and the request goes on', 0 === $code_ldr && 'redeclare' === $r['status']['redecl']);
file_put_contents("$dir/includes/web2/synerr.php", omx_module('synerr', 'function omochix_t_s( {'));
[$code_ldr, $r] = omx_run('core', $dir, ['synerr' => ['file' => 'includes/web2/synerr.php', 'enabled' => true]]);
check('isolation: a syntax error is found by the tokenizer before the file runs', 0 === $code_ldr && 'syntax' === $r['status']['synerr']);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
