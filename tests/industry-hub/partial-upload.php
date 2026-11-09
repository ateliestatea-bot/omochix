<?php
/**
 * Industry Hub: a half-done FTP upload of the Core plugin must never break the
 * site. Every scenario loads a modified COPY of the plugin in a separate PHP
 * process (through stub.php, IH_PLUGIN_DIR) and checks that the process ends
 * normally, the rest of the plugin works, and the Industry Hub is either fully
 * on or fully off (no hook left behind from a module file).
 * Run: php tests/industry-hub/partial-upload.php
 */
if ('--child' === ($argv[1] ?? '')) {
    // ---------------------------------------------------------------- child
    require __DIR__ . '/stub.php';
    $mode = (string) ($argv[2] ?? '');
    $dir  = rtrim((string) getenv('IH_PLUGIN_DIR'), '/') . '/';
    $out  = ['loaded' => function_exists('omochix_core_industry_hub_loaded') && omochix_core_industry_hub_loaded(), 'problems' => $GLOBALS['omochix_core_industry_hub_status']['problems'] ?? null];
    if ('prefix' === $mode) {
        // Load the module files directly in manifest order, up to the given one, on top of the plugin without the module.
        $files = json_decode((string) $argv[3], true);
        foreach ($files as $f) { require_once realpath(__DIR__ . '/../../wp-plugin/omochix-core') . '/' . $f; }
        echo json_encode(['ok' => true]);
        exit(0);
    }
    if ('hold' === $mode) {
        // The fail-closed hold, through the same filters WordPress runs for each path.
        if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event($t, $hook, $args = []) { $GLOBALS['scheduled'][] = [$hook, $args]; return true; } }
        if (!function_exists('check_and_publish_future_post')) { function check_and_publish_future_post($id) { $GLOBALS['cron_published'][] = (int) $id; } }
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        ih_boot();
        if (!ih_term('learn_format', 'workflow')) { $c = $GLOBALS['caps']; $GLOBALS['caps'] = ['manage_categories' => true]; wp_insert_term('導入する', 'learn_format', ['slug' => 'workflow']); $GLOBALS['caps'] = $c; }
        $GLOBALS['caps'] = ['*' => true];
        $wf_term = ih_term('learn_format', 'workflow');
        $mk = static function ($status, $workflow) use ($wf_term) { $id = ih_add_post(['post_type' => 'learn_practice', 'post_status' => $status, 'post_title' => 'x']); if ($workflow) { wp_set_object_terms($id, [(int) $wf_term->term_id], 'learn_format'); } return $id; };
        $hold = static fn($data, $postarr) => 12 === has_filter('wp_insert_post_data', 'omochix_core_workflow_hold_insert_data') ? omochix_core_workflow_hold_insert_data($data, $postarr)['post_status'] : 'not registered';
        $ins = static fn($id, $status, $extra = []) => $hold(['post_type' => 'learn_practice', 'post_status' => $status, 'post_title' => 'x'], ['ID' => $id] + $extra);
        $r = [];
        $r['draft workflow -> publish'] = $ins($mk('draft', true), 'publish');
        $r['draft workflow -> future'] = $ins($mk('draft', true), 'future');
        $r['new post, workflow by tax_input slug'] = $ins(0, 'publish', ['tax_input' => ['learn_format' => 'workflow']]);
        $r['new post, workflow by tax_input id'] = $ins(0, 'publish', ['tax_input' => ['learn_format' => [(string) $wf_term->term_id]]]);
        $r['live workflow update'] = $ins($mk('publish', true), 'publish');
        $r['draft non-workflow practice -> publish'] = $ins($mk('draft', false), 'publish');
        $r['normal post'] = $hold(['post_type' => 'post', 'post_status' => 'publish'], ['ID' => 0]);
        $rest = static function ($id, $formats = null) { $req = new class($formats) { private $f; function __construct($f) { $this->f = $f; } function get_param($k) { return 'learn-formats' === $k ? $this->f : null; } }; $p = (object) ['ID' => $id, 'post_status' => 'publish', 'post_type' => 'learn_practice']; $o = 12 === has_filter('rest_pre_insert_learn_practice', 'omochix_core_workflow_hold_rest') ? omochix_core_workflow_hold_rest($p, $req) : 'not registered'; return $o instanceof WP_Error ? 'error:' . $o->get_error_code() : (is_string($o) ? $o : 'allowed'); };
        $r['REST draft workflow -> publish'] = $rest($mk('draft', true));
        $r['REST adds the workflow format'] = $rest($mk('draft', false), [(int) $wf_term->term_id]);
        $r['REST non-workflow'] = $rest($mk('draft', false));
        $r['REST live workflow'] = $rest($mk('publish', true));
        $due = $mk('future', true); $GLOBALS['cron_published'] = []; $GLOBALS['scheduled'] = [];
        do_action('publish_future_post', $due);
        $r['cron: due scheduled workflow'] = in_array($due, $GLOBALS['cron_published'], true) ? 'published' : ('rescheduled' === (($GLOBALS['scheduled'][0][0] ?? '') === 'publish_future_post' ? 'rescheduled' : '') ? 'rescheduled' : 'not published, not rescheduled');
        $plain = $mk('future', false); $GLOBALS['cron_published'] = [];
        do_action('publish_future_post', $plain);
        $r['cron: next event (non-workflow) still publishes'] = in_array($plain, $GLOBALS['cron_published'], true) ? 'published' : 'NOT published';
        echo json_encode(['loaded' => $out['loaded'], 'hold' => $r], JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    $module = [];
    foreach ((array) json_decode((string) getenv('IH_MODULE_FILES'), true) as $f) { if (($r = realpath($dir . $f))) { $module[] = $r; } }
    $left = 0; $uncallable = 0;
    foreach ($GLOBALS['hooks'] as $hook => $by_priority) {
        foreach ($by_priority as $list) {
            foreach ($list as $h) {
                if (!is_callable($h[0])) { $uncallable++; }
                try { $ref = is_array($h[0]) ? new ReflectionMethod($h[0][0], $h[0][1]) : (is_string($h[0]) || $h[0] instanceof Closure ? new ReflectionFunction($h[0]) : null); } catch (ReflectionException $e) { $ref = null; }
                if ($ref && in_array($ref->getFileName(), $module, true)) { $left++; }
            }
        }
    }
    ih_boot(); // init: the existing plugin registers, the module only when loaded
    $out['module_hooks_left'] = $left;
    $out['uncallable_hooks']  = $uncallable;
    $out['industry_taxonomy'] = taxonomy_exists('omx_industry');
    $out['ai_tool']           = post_type_exists('ai_tool') && post_type_exists('prompt') && post_type_exists('learn_practice');
    $GLOBALS['caps'] = ['activate_plugins' => true];
    ob_start(); do_action('admin_notices'); $out['notice'] = ob_get_clean();
    $GLOBALS['caps'] = [];
    ob_start(); do_action('admin_notices'); $out['notice_visitor'] = ob_get_clean();
    // Exercise the rest of the request (REST and front-end filters) without the module.
    apply_filters('rest_post_query', [], null); apply_filters('the_content', 'x'); do_action('template_redirect');
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit(0);
}

// ------------------------------------------------------------------ parent
$pass = 0; $fail = 0;
function pu_check($name, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }

$src = realpath(__DIR__ . '/../../wp-plugin/omochix-core');
$hub = (string) file_get_contents($src . '/includes/industry-hub.php');
preg_match("/define\\( 'OMOCHIX_CORE_VERSION', '([^']+)' \\);/", (string) file_get_contents($src . '/omochix-core.php'), $m); $ver = (string) ($m[1] ?? '');
preg_match('/function omochix_core_industry_hub_files\(\) \{\s*return array\((.*?)\);/s', $hub, $m); preg_match_all("/'([^']+\\.php)'/", $m[1] ?? '', $mm); $files = $mm[1];
$mark = '// omochix-industry-hub-version: ' . $ver;

// ---------------------------------------------------------- static checks
pu_check('manifest: 11 module files; the plugin version is read', 11 === count($files) && '' !== $ver, count($files) . ' files, version ' . $ver);
$bad = [];
foreach ($files as $f) { $c = (string) file_get_contents("$src/$f"); if (0 !== strpos($c, "<?php\n$mark\n") || !str_ends_with(rtrim($c), "$mark (end)")) { $bad[] = $f; } }
pu_check('markers: every module file carries OMOCHIX_CORE_VERSION on line 2 and on its last line (a version bump must update them)', !$bad, implode(', ', $bad));
$marked = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $p) { if ('php' === $p->getExtension() && false !== strpos((string) file_get_contents((string) $p), 'omochix-industry-hub-version:') && 'includes/industry-hub.php' !== substr((string) $p, strlen($src) + 1)) { $marked[] = substr((string) $p, strlen($src) + 1); } }
sort($marked); $sorted = $files; sort($sorted);
pu_check('manifest: lists exactly the files that carry the marker (a new module file cannot be forgotten)', $sorted === $marked, json_encode(array_values(array_diff($marked, $files))));
$defined = [];
foreach ($files as $f) { preg_match_all('/^\s*function\s+([a-z0-9_]+)\s*\(/m', (string) file_get_contents("$src/$f"), $d); $defined = array_merge($defined, $d[1]); preg_match_all("/^const (OMOCHIX_CORE_[A-Z0-9_]+)/m", (string) file_get_contents("$src/$f"), $d); $defined = array_merge($defined, $d[1]); }
$uses = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $p) {
    $rel = substr((string) $p, strlen($src) + 1);
    if ('php' !== $p->getExtension() || in_array($rel, $files, true) || 'includes/industry-hub.php' === $rel) { continue; }
    $c = (string) file_get_contents((string) $p);
    foreach ($defined as $sym) { if (preg_match('/\b' . preg_quote($sym, '/') . '\b/', $c)) { $uses[] = "$rel: $sym"; } }
}
pu_check('dependencies: no other plugin file uses a module function or constant (switching the module off cannot break them)', count($defined) > 100 && !$uses, implode('; ', $uses));

// ------------------------------------------------------------- scenarios
function copy_plugin($src) { $dst = sys_get_temp_dir() . '/ih-partial-' . bin2hex(random_bytes(4)); $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST); mkdir($dst); foreach ($it as $p) { $t = $dst . '/' . substr((string) $p, strlen($src) + 1); $p->isDir() ? mkdir($t) : copy((string) $p, $t); } return $dst; }
function rm_tree($d) { if (!is_dir($d) || 0 !== strpos($d, sys_get_temp_dir() . '/ih-partial-')) { return; } $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $p) { $p->isDir() ? rmdir((string) $p) : unlink((string) $p); } rmdir($d); }
function run_child($dir, array $files, array $extra = []) {
    $env = 'IH_PLUGIN_DIR=' . escapeshellarg($dir) . ' IH_MODULE_FILES=' . escapeshellarg(json_encode($files));
    $cmd = $env . ' ' . escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -d error_reporting=-1 ' . escapeshellarg(__FILE__) . ' --child ' . implode(' ', array_map('escapeshellarg', $extra)) . ' 2>&1';
    exec($cmd, $lines, $rc);
    $raw = implode("\n", $lines);
    return ['rc' => $rc, 'raw' => $raw, 'out' => json_decode((string) end($lines), true)];
}
function scenario($label, callable $mutate, $expect_loaded, $expect_problem = null) {
    global $src, $files;
    $dir = copy_plugin($src);
    $mutate($dir);
    $r = run_child($dir, $files);
    rm_tree($dir);
    $o = $r['out'];
    $clean = 0 === $r['rc'] && is_array($o) && !preg_match('/(Fatal|Warning|Notice|Deprecated|Parse error)/i', substr($r['raw'], 0, -strlen((string) json_encode($o, JSON_UNESCAPED_UNICODE))));
    if ($expect_loaded) {
        pu_check("$label: loads, Industry Hub on", $clean && true === $o['loaded'] && $o['industry_taxonomy'] && $o['module_hooks_left'] > 50 && '' === $o['notice'], substr($r['raw'], 0, 300));
        return;
    }
    pu_check("$label: no fatal error or warning, the rest of the plugin works", $clean && true === ($o['ai_tool'] ?? false), substr($r['raw'], 0, 400));
    pu_check("$label: Industry Hub fully off (no taxonomy, no module hook, no uncallable hook left)", $clean && false === $o['loaded'] && false === $o['industry_taxonomy'] && 0 === $o['module_hooks_left'] && 0 === $o['uncallable_hooks'], json_encode($o ? array_diff_key($o, ['notice' => 1, 'notice_visitor' => 1]) : $r['raw'], JSON_UNESCAPED_UNICODE));
    if (null !== $expect_problem) {
        pu_check("$label: administrators are told which file ($expect_problem); visitors see nothing", $clean && in_array($expect_problem, (array) $o['problems'], true) && false !== strpos((string) $o['notice'], $expect_problem) && '' === $o['notice_visitor']);
    }
}

scenario('complete upload', static function () {}, true);
foreach ($files as $f) {
    scenario("missing $f", static function ($d) use ($f) { unlink("$d/$f"); }, false, $f);
}
scenario('missing includes/industry-hub.php (the loader itself)', static function ($d) { unlink("$d/includes/industry-hub.php"); }, false);
scenario('only the loader and omochix-core.php uploaded (every module file missing)', static function ($d) use ($files) { foreach ($files as $f) { unlink("$d/$f"); } }, false, $files[0]);
$visibility = 'includes/industry-visibility.php';
scenario('truncated while uploading (first half of a file)', static function ($d) use ($visibility) { $c = file_get_contents("$d/$visibility"); file_put_contents("$d/$visibility", substr($c, 0, intdiv(strlen($c), 2))); }, false, $visibility);
scenario('truncated at a function boundary (valid PHP, end marker missing)', static function ($d) use ($visibility) { $c = file_get_contents("$d/$visibility"); $cut = strpos($c, "\nfunction omochix_core_blocked_workflow_ids"); file_put_contents("$d/$visibility", substr($c, 0, $cut) . "\n"); }, false, $visibility);
scenario('a file left over from an older release (no marker)', static function ($d) { $f = 'includes/industry-term-meta.php'; file_put_contents("$d/$f", preg_replace('#^// omochix-industry-hub-version: [^\n]+\n#m', '', (string) file_get_contents("$d/$f"))); }, false, 'includes/industry-term-meta.php');
scenario('a file from another release (marker of an older version)', static function ($d) use ($ver) { $f = 'includes/provider.php'; file_put_contents("$d/$f", str_replace('omochix-industry-hub-version: ' . $ver, 'omochix-industry-hub-version: 1.9.9', (string) file_get_contents("$d/$f"))); }, false, 'includes/provider.php');
scenario('omochix-core.php from another release than the module files', static function ($d) use ($ver) { $f = 'omochix-core.php'; file_put_contents("$d/$f", str_replace("'OMOCHIX_CORE_VERSION', '" . $ver . "'", "'OMOCHIX_CORE_VERSION', '9.9.9'", (string) file_get_contents("$d/$f"))); }, false, $files[0]);
$rel = 'includes/industry-relations.php';
scenario('a complete-looking file that fails while loading (earlier files already hooked)', static function ($d) use ($rel) { $c = (string) file_get_contents("$d/$rel"); file_put_contents("$d/$rel", preg_replace('#^(// omochix-industry-hub-version: [^\n]+\n)#m', "$1throw new RuntimeException('half written');\n", $c, 1)); }, false, $rel);
scenario('the loader itself half written (includes/industry-hub.php)', static function ($d) { $f = 'includes/industry-hub.php'; $c = (string) file_get_contents("$d/$f"); file_put_contents("$d/$f", substr($c, 0, intdiv(strlen($c), 2))); }, false);
scenario('an earlier file hooks a function of a later file that has a syntax error (no uncallable hook left)', static function ($d) use ($files, $mark) {
    $first = "$d/{$files[0]}"; file_put_contents($first, str_replace("\n$mark (end)", "\nadd_action( 'init', 'omochix_core_blocked_workflow_ids' );\n$mark (end)", (string) file_get_contents($first)));
    $last = "$d/{$files[10]}"; $c = (string) file_get_contents($last); $i = strpos($c, "\nfunction "); file_put_contents($last, substr($c, 0, $i) . "\nfunction ( {\n" . substr($c, $i));
}, false, $files[10]);
scenario('a complete-looking file with a syntax error', static function ($d) use ($rel) { $c = (string) file_get_contents("$d/$rel"); $i = strpos($c, "\nfunction "); file_put_contents("$d/$rel", substr($c, 0, $i) . "\nfunction ( {\n" . substr($c, $i)); }, false, $rel);

// ------------------------------------------------- fail-closed workflow hold
$expect_off = ['draft workflow -> publish' => 'draft', 'draft workflow -> future' => 'draft', 'new post, workflow by tax_input slug' => 'draft', 'new post, workflow by tax_input id' => 'draft', 'live workflow update' => 'publish', 'draft non-workflow practice -> publish' => 'publish', 'normal post' => 'publish', 'REST draft workflow -> publish' => 'error:omochix_workflow_on_hold', 'REST adds the workflow format' => 'error:omochix_workflow_on_hold', 'REST non-workflow' => 'allowed', 'REST live workflow' => 'allowed', 'cron: due scheduled workflow' => 'rescheduled', 'cron: next event (non-workflow) still publishes' => 'published'];
foreach (['module off (a file missing)' => true, 'module loaded' => false] as $label => $off) {
    $dir = copy_plugin($src);
    if ($off) { unlink("$dir/{$files[10]}"); }
    $r = run_child($dir, $files, ['hold']);
    rm_tree($dir);
    $h = $r['out']['hold'] ?? null;
    if (!is_array($h)) { pu_check("hold ($label): runs", false, substr($r['raw'], 0, 300)); continue; }
    foreach ($expect_off as $case => $want) {
        if ($off) {
            pu_check("hold ($label): $case → $want", $want === $h[$case], (string) $h[$case]);
        } elseif (in_array($case, ['cron: due scheduled workflow', 'REST adds the workflow format', 'REST draft workflow -> publish', 'draft workflow -> publish', 'draft workflow -> future', 'new post, workflow by tax_input slug', 'new post, workflow by tax_input id'], true)) {
            // With the module loaded the hold is inactive: the module's own guard decides (these fixtures lack workflow fields, so it holds them for its own reasons, never with the hold's error code).
            pu_check("hold ($label): $case is not decided by the hold", 'error:omochix_workflow_on_hold' !== $h[$case] && 'rescheduled' !== $h[$case], (string) $h[$case]);
        }
    }
}

// ------------------------------------------------------------ load order
$order_ok = true; $detail = '';
for ($i = 1; $i <= count($files); $i++) {
    $dir = copy_plugin($src); unlink("$dir/includes/industry-hub.php");
    $r = run_child($dir, $files, ['prefix', json_encode(array_slice($files, 0, $i))]);
    rm_tree($dir);
    if (0 !== $r['rc'] || false === strpos($r['raw'], '{"ok":true}') || preg_match('/(Fatal|Warning|Notice|Deprecated)/i', $r['raw'])) { $order_ok = false; $detail = $files[$i - 1] . ': ' . substr($r['raw'], 0, 200); break; }
}
pu_check('load order: each module file loads with only the files before it (constants at load time come first)', $order_ok, $detail);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
