<?php
/**
 * Industry Hub (theme): with no usable Industry Hub in Core (Core 1.9.3 has
 * none; a half-done upload switches the module off), every hook the theme
 * module registers must run without calling a Core function that does not
 * exist, and the page sitemap must be left as it was. With the module on,
 * the index page leaves the page sitemap only while no hub is indexable.
 * Each case loads a modified COPY of the plugin in its own PHP process.
 * Run: php tests/industry-hub/theme-without-core.php
 */
if ('--child' === ($argv[1] ?? '')) {
    require __DIR__ . '/stub.php';
    if (!function_exists('is_page')) { function is_page($p = '') { return false; } }
    if (!function_exists('wp_robots_no_robots')) { function wp_robots_no_robots($r) { $r['noindex'] = true; $r['follow'] = true; return $r; } }
    // The fixed index page /learn/industries/ exists and is published.
    $GLOBALS['ih_index_page'] = new WP_Post(['ID' => 990001, 'post_type' => 'page', 'post_name' => 'industries', 'post_status' => 'publish', 'post_title' => '業種別AI活用']);
    if (!function_exists('get_page_by_path')) { function get_page_by_path($p, $o = null, $t = 'page') { return 'learn/industries' === trim((string) $p, '/') ? $GLOBALS['ih_index_page'] : null; } }
    $theme = realpath(__DIR__ . '/../../wp-theme/omochix-theme/inc/learn-industries.php');
    require $theme;
    ih_boot();
    $mode = (string) ($argv[2] ?? '');
    $out  = ['enabled' => omochix_industry_hub_enabled()];
    if ('hub-on' === $mode) {
        $GLOBALS['caps'] = ['*' => true];
        $re = ih_term('omx_industry', 'real-estate')->term_id;
        $out['no hub indexable: index page left out'] = omochix_industry_hub_sitemap_post_ignore(false, $GLOBALS['ih_index_page']);
        ih_fill_hub($re); ih_add_workflow('real-estate');
        omochix_core_gate_new_request(); omochix_core_gate_invalidate(); omochix_core_gate_new_request();
        $out['a hub indexable: index page kept'] = omochix_industry_hub_sitemap_post_ignore(false, $GLOBALS['ih_index_page']);
        $out['other pages untouched'] = omochix_industry_hub_sitemap_post_ignore(false, new WP_Post(['ID' => 5, 'post_type' => 'page']));
        echo json_encode($out);
        exit(0);
    }
    // Run every callback the theme module registered, with arguments of the shape WordPress passes.
    $args = [
        'slim_seo_sitemap_post_ignore' => [false, $GLOBALS['ih_index_page']], 'omochix_core_industry_public' => [false], 'pre_handle_404' => [false, new WP_Query()],
        'redirect_canonical' => ['http://example.test/'], 'do_redirect_guess_404_permalink' => [true], 'pre_get_posts' => [new WP_Query()], 'the_posts' => [[], new WP_Query()],
        'template_include' => ['/theme/page.php'], 'omochix_core_industry_archive_noindex' => [true], 'slim_seo_robots_index' => [true], 'wp_robots' => [[]],
        'slim_seo_canonical_url' => ['http://example.test/'], 'slim_seo_meta_title' => ['t'], 'slim_seo_meta_description' => ['d'], 'slim_seo_breadcrumbs_links' => [[]],
        'slim_seo_schema_graph' => [[]], 'omochix_shell_enabled_for_request' => [false],
    ];
    $ran = 0; $undefined = []; $other = [];
    foreach ($GLOBALS['hooks'] as $hook => $by_priority) {
        foreach ($by_priority as $list) {
            foreach ($list as $h) {
                try { $ref = is_string($h[0]) || $h[0] instanceof Closure ? new ReflectionFunction($h[0]) : null; } catch (ReflectionException $e) { $ref = null; }
                if (!$ref || $ref->getFileName() !== $theme) { continue; }
                $ran++;
                try { ob_start(); call_user_func_array($h[0], array_slice($args[$hook] ?? [], 0, $h[1])); } catch (Throwable $e) {
                    if (false !== stripos($e->getMessage(), 'undefined function')) { $undefined[] = "$hook: " . $e->getMessage(); } else { $other[] = "$hook: " . get_class($e); }
                } finally { ob_end_clean(); }
            }
        }
    }
    $out += ['ran' => $ran, 'undefined' => $undefined, 'other' => $other];
    $out['sitemap: ignore=false kept'] = omochix_industry_hub_sitemap_post_ignore(false, $GLOBALS['ih_index_page']);
    $out['sitemap: ignore=true kept'] = omochix_industry_hub_sitemap_post_ignore(true, $GLOBALS['ih_index_page']);
    echo json_encode($out);
    exit(0);
}

$pass = 0; $fail = 0;
function tw_check($name, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }
$src = realpath(__DIR__ . '/../../wp-plugin/omochix-core');
function tw_copy($src) { $dst = sys_get_temp_dir() . '/ih-theme-' . bin2hex(random_bytes(4)); $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST); mkdir($dst); foreach ($it as $p) { $t = $dst . '/' . substr((string) $p, strlen($src) + 1); $p->isDir() ? mkdir($t) : copy((string) $p, $t); } return $dst; }
function tw_rm($d) { if (!is_dir($d) || 0 !== strpos($d, sys_get_temp_dir() . '/ih-theme-')) { return; } $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $p) { $p->isDir() ? rmdir((string) $p) : unlink((string) $p); } rmdir($d); }
function tw_run($dir, $mode = '') { exec('IH_PLUGIN_DIR=' . escapeshellarg($dir) . ' ' . escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -d error_reporting=-1 ' . escapeshellarg(__FILE__) . ' --child ' . escapeshellarg($mode) . ' 2>&1', $l, $rc); $raw = implode("\n", $l); return ['rc' => $rc, 'raw' => $raw, 'out' => json_decode((string) end($l), true)]; }

$cases = [
    'Core without Industry Hub (as Core 1.9.3)' => static function ($d) { unlink("$d/includes/industry-hub.php"); },
    'Industry Hub module file missing' => static function ($d) { unlink("$d/includes/industry-visibility.php"); },
    'module file half written' => static function ($d) { $f = "$d/includes/industry-term-meta.php"; $c = (string) file_get_contents($f); file_put_contents($f, substr($c, 0, intdiv(strlen($c), 2))); },
    'module files from another release' => static function ($d) { $f = "$d/omochix-core.php"; file_put_contents($f, preg_replace("/'OMOCHIX_CORE_VERSION', '[^']+'/", "'OMOCHIX_CORE_VERSION', '9.9.9'", (string) file_get_contents($f))); },
    'module loaded halfway (earlier files defined their functions, then one failed)' => static function ($d) { $f = "$d/includes/industry-visibility.php"; file_put_contents($f, preg_replace('#^(// omochix-industry-hub-version: [^\n]+\n)#m', "$1throw new RuntimeException('half');\n", (string) file_get_contents($f), 1)); },
];
foreach ($cases as $label => $mutate) {
    $dir = tw_copy($src); $mutate($dir); $r = tw_run($dir); tw_rm($dir); $o = $r['out'];
    $clean = 0 === $r['rc'] && is_array($o) && !preg_match('/(Fatal|Warning|Deprecated|Notice)/i', substr($r['raw'], 0, -strlen((string) json_encode($o))));
    tw_check("$label: the theme sees no usable hub", $clean && false === $o['enabled'], substr($r['raw'], 0, 300));
    tw_check("$label: every theme hook runs without calling a missing Core function", $clean && $o['ran'] >= 15 && [] === $o['undefined'], json_encode($o['undefined'] ?? $r['raw'], JSON_UNESCAPED_UNICODE));
    tw_check("$label: the page sitemap is left as it was (index page included / excluded as before)", $clean && false === $o['sitemap: ignore=false kept'] && true === $o['sitemap: ignore=true kept']);
}
$dir = tw_copy($src); $r = tw_run($dir, 'hub-on'); tw_rm($dir); $o = $r['out'];
tw_check('hub on: the index page leaves the page sitemap while no hub is indexable', is_array($o) && true === $o['enabled'] && true === $o['no hub indexable: index page left out'], substr($r['raw'], 0, 300));
tw_check('hub on: it is listed once a hub is indexable', is_array($o) && false === $o['a hub indexable: index page kept']);
tw_check('hub on: other pages are never touched', is_array($o) && false === $o['other pages untouched']);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
