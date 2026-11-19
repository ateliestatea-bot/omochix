<?php
/**
 * Design Library — rewrite self-heal checks against a THROWAWAY local
 * WordPress (never staging or production).
 *
 *   DL_BASE=http://127.0.0.1:8961 DL_SITE=/path/to/site \
 *   DL_ROLLBACK_THEME=/path/to/old/omochix-theme \
 *   DL_MYSQL="mysql --no-defaults -h127.0.0.1 -P3461 -uroot" \
 *     php tests/design-library/rewrite-heal-check.php
 *
 * - normal state: no heal, no rewrite writes, one option load per request
 * - rules missing: the first single request heals (200, one reload), later ones do not
 * - rules missing, 40 concurrent requests x 5 rounds: no 404 / no non-200
 * - first deploy (version option and rules missing) and an update from an
 *   older rule version, both under concurrent load
 * - rollback to a theme without the Design Library + permalinks regenerated
 *   there + this theme deployed again, under concurrent load
 * DL_ROLLBACK_THEME is optional (that part is skipped without it). DL_MYSQL is
 * optional: with it, the MySQL general log of the throwaway server is switched
 * on for the run to count statements, and switched off again.
 * Persistent object cache: not covered here (see the QA record).
 */
$base = rtrim((string) getenv('DL_BASE'), '/');
$site = rtrim((string) getenv('DL_SITE'), '/');
$old  = rtrim((string) getenv('DL_ROLLBACK_THEME'), '/');
$mysql = trim((string) getenv('DL_MYSQL'));
if (!preg_match('#^http://127\.0\.0\.1:\d+$#', $base) || !is_dir($site . '/wp')) { fwrite(STDERR, "DL_BASE=http://127.0.0.1:PORT and DL_SITE=<throwaway site dir> required\n"); exit(2); }
if ('' !== $mysql && !preg_match('#^mysql --no-defaults -h127\.0\.0\.1 -P\d+ -uroot$#', $mysql)) { fwrite(STDERR, "DL_MYSQL must target the local throwaway server\n"); exit(2); }
$pass = 0; $fail = 0;
function check($name, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }
function info($s) { echo "INFO $s\n"; }

$theme_link = $site . '/wp/wp-content/themes/omochix-theme';
$theme_real = readlink($theme_link);
register_shutdown_function(static function () use ($theme_link, $theme_real) {
    if (readlink($theme_link) !== $theme_real) { @unlink($theme_link); symlink($theme_real, $theme_link); echo "INFO theme link restored\n"; }
});

/** PHP with SHORTINIT: options only, no theme, no init hooks (so nothing heals while we look). */
function shortinit($code) { global $site; return trim((string) shell_exec('cd ' . escapeshellarg($site) . ' && php -r ' . escapeshellarg('define("SHORTINIT", true); require "wp/wp-load.php"; ' . $code) . ' 2>&1')); }
/** Full WordPress (theme loaded). */
function full_wp($code) { global $site; return trim((string) shell_exec('cd ' . escapeshellarg($site) . ' && php -r ' . escapeshellarg('require "wp/wp-load.php"; ' . $code) . ' 2>&1')); }
function design_rules() { return (int) shortinit('echo count(array_filter(array_keys((array) get_option("rewrite_rules")), function ($k) { return 0 === strpos($k, "^design/"); }));'); }
function drop_design_rules() { shortinit('$r = get_option("rewrite_rules"); foreach (array_keys((array) $r) as $k) { if (0 === strpos($k, "^design/")) unset($r[$k]); } update_option("rewrite_rules", $r);'); return design_rules(); }
function status($path) {
    global $base;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => false, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => false]);
    curl_exec($ch);
    return (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
}
/** $n requests at once; returns [path => [codes]]. */
function burst(array $paths, $n = 40) {
    global $base;
    $mh = curl_multi_init(); $hs = [];
    for ($i = 0; $i < $n; $i++) {
        $p = $paths[$i % count($paths)];
        $ch = curl_init($base . $p);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120]);
        curl_multi_add_handle($mh, $ch); $hs[] = [$p, $ch];
    }
    do { $st = curl_multi_exec($mh, $running); if ($running) { curl_multi_select($mh, 1.0); } } while ($running && CURLM_OK === $st);
    $out = [];
    foreach ($hs as [$p, $ch]) { $out[$p][] = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_multi_remove_handle($mh, $ch); }
    curl_multi_close($mh);
    return $out;
}
function non200(array $res) { $bad = []; foreach ($res as $p => $codes) { foreach ($codes as $c) { if (200 !== $c) { $bad[] = "$p $c"; } } } return $bad; }
// The rule version the theme expects (read from the code, so a version bump does not break this test).
preg_match("/const OMOCHIX_DESIGN_REWRITE_VERSION\\s*=\\s*'([^']+)'/", (string) file_get_contents(dirname(__DIR__, 2) . '/wp-theme/omochix-theme/inc/design-library.php'), $vm);
$VER = $vm[1] ?? '?';
$PATHS = ['/design/web/', '/design/video/logo-reveal-short/', '/about/', '/design/', '/design/demo/web/bento-saas/'];

// ---------------------------------------------------------------- statement counting (optional)
$glog = $site . '/logs/heal-check-general.log';
function mysql_q($q) { global $mysql; return trim((string) shell_exec($mysql . ' -N -e ' . escapeshellarg($q) . ' 2>&1')); }
function glog_offset() { global $glog; clearstatcache(); return is_file($glog) ? filesize($glog) : 0; }
/** Counts since $from: option loads (alloptions SELECT) and writes to the rewrite / version options. */
function glog_counts($from) {
    global $glog;
    clearstatcache();
    $t = is_file($glog) ? (string) file_get_contents($glog, false, null, $from) : '';
    return [
        'alloptions' => preg_match_all('/Query\s+SELECT option_name, option_value FROM `?\w+options`? WHERE autoload IN/i', $t),
        'writes'     => preg_match_all('/Query\s+(UPDATE|INSERT|DELETE|REPLACE)\b[^\n]*(rewrite_rules|omochix_design_rewrite_version)/i', $t),
    ];
}
$counting = '' !== $mysql;
if ($counting) {
    @unlink($glog);
    mysql_q("SET GLOBAL general_log_file='" . addslashes($glog) . "'; SET GLOBAL log_output='FILE'; SET GLOBAL general_log=ON");
    register_shutdown_function(static function () use ($glog) { mysql_q('SET GLOBAL general_log=OFF'); @unlink($glog); echo "INFO general log off\n"; });
    usleep(200000);
} else {
    info('DL_MYSQL not set: statement counts skipped');
}

// ---------------------------------------------------------------- 1. normal state
status('/');
check("setup: our 3 rules are stored and the version is $VER", 3 === design_rules() && $VER === shortinit('echo get_option("omochix_design_rewrite_version");'));
$o = glog_offset(); $codes = [];
foreach (array_merge($PATHS, $PATHS, $PATHS, $PATHS, $PATHS, $PATHS) as $p) { $codes[] = status($p); }
$c = glog_counts($o);
check('normal: 30 requests, all 200', [200] === array_values(array_unique($codes)), implode(',', array_unique($codes)));
if ($counting) {
    check('normal: no write to rewrite_rules / version', 0 === $c['writes'], (string) $c['writes']);
    check('normal: exactly one option load per request (no reload)', 30 === $c['alloptions'], (string) $c['alloptions']);
}

// ---------------------------------------------------------------- 2. single request heal
check('single: rules removed for the test', 0 === drop_design_rules());
$o = glog_offset();
$first = status('/design/web/');
$c1 = glog_counts($o);
check('single: the first request to /design/web/ is 200 and heals', 200 === $first && 3 === design_rules(), "$first, rules " . design_rules());
$o = glog_offset(); $next = [];
for ($i = 0; $i < 10; $i++) { $next[] = status($PATHS[$i % 5]); }
$c2 = glog_counts($o);
check('single: the next 10 requests are 200', [200] === array_values(array_unique($next)));
if ($counting) {
    check('single: the healing request wrote rewrite_rules once and loaded options twice (reload)', 1 === $c1['writes'] && 2 === $c1['alloptions'], json_encode($c1));
    check('single: the next 10 requests write nothing and load options once each', 0 === $c2['writes'] && 10 === $c2['alloptions'], json_encode($c2));
}

// ---------------------------------------------------------------- 3. concurrent heal, 40 x 5
$all_bad = []; $writes = [];
for ($round = 1; $round <= 5; $round++) {
    drop_design_rules();
    $o = glog_offset();
    $bad = non200(burst($PATHS, 40));
    $all_bad = array_merge($all_bad, $bad);
    $writes[] = glog_counts($o)['writes'];
    check("concurrent round $round: 40 requests with the rules missing, none fails, rules stored afterwards", [] === $bad && 3 === design_rules(), implode(', ', array_slice($bad, 0, 5)));
}
if ($counting) {
    info('concurrent: rewrite/version writes per round = ' . implode(', ', $writes) . ' (duplicate same-value writes from parallel heals; no error)');
    check('concurrent: writes stop once healed (≤ 40 per round, 0 afterwards)', max($writes) <= 40 && 0 === glog_counts(glog_offset())['writes']);
}
$o = glog_offset(); burst($PATHS, 40);
if ($counting) { check('concurrent: after healing, 40 more requests write nothing', 0 === glog_counts($o)['writes']); }

// ---------------------------------------------------------------- 4. first deploy and update from an older rule version
foreach ([
    'first deploy (no version option, rules missing)' => 'delete_option("omochix_design_rewrite_version"); $r = get_option("rewrite_rules"); foreach (array_keys((array) $r) as $k) { if (0 === strpos($k, "^design/")) unset($r[$k]); } update_option("rewrite_rules", $r);',
    'update from rule version 1 (D1 rules: no video segment)' => 'update_option("omochix_design_rewrite_version", "1"); $r = get_option("rewrite_rules"); $n = []; foreach ((array) $r as $k => $v) { if (0 === strpos($k, "^design/")) { $k = str_replace("web|video|app|components", "web|app|components", $k); } $n[$k] = $v; } update_option("rewrite_rules", $n);',
] as $label => $code) {
    shortinit($code);
    $bad = non200(burst($PATHS, 40));
    check("$label: 40 concurrent requests all 200, version $VER and our rules stored", [] === $bad && 3 === design_rules() && $VER === shortinit('echo get_option("omochix_design_rewrite_version");') && false !== strpos(shortinit('echo implode(" ", array_keys((array) get_option("rewrite_rules")));'), '^design/(web|video|app|components)/?$'), implode(', ', array_slice($bad, 0, 5)));
}

// ---------------------------------------------------------------- 5. rollback and deploy again
if ('' === $old || !is_file($old . '/style.css')) {
    info('DL_ROLLBACK_THEME not set: rollback / redeploy skipped');
} else {
    @unlink($theme_link); symlink($old, $theme_link);
    full_wp('flush_rewrite_rules(false);'); // "regenerate permalinks" on the rolled-back theme (Runbook)
    check('rollback: the old theme (no Design Library) is active and the stored rules no longer contain ours', 0 === design_rules() && !is_file($old . '/inc/design-library.php'));
    check('rollback: /design/web/ is a 404 under the old theme (expected)', 404 === status('/design/web/'));
    check("rollback: the version option still says $VER (what made D1 stay broken)", $VER === shortinit('echo get_option("omochix_design_rewrite_version");'));
    @unlink($theme_link); symlink($theme_real, $theme_link); // deploy this theme again
    $bad = non200(burst($PATHS, 40));
    check('redeploy: 40 concurrent requests right after the deploy all succeed, rules stored', [] === $bad && 3 === design_rules(), implode(', ', array_slice($bad, 0, 5)));
    $bad = [];
    for ($round = 1; $round <= 4; $round++) {
        @unlink($theme_link); symlink($old, $theme_link); full_wp('flush_rewrite_rules(false);');
        @unlink($theme_link); symlink($theme_real, $theme_link);
        $bad = array_merge($bad, non200(burst($PATHS, 40)));
    }
    check('rollback + redeploy repeated 4 more times: no failed request', [] === $bad, implode(', ', array_slice($bad, 0, 5)));
}

check("end: our rules stored, version $VER, theme link is this worktree", 3 === design_rules() && $VER === shortinit('echo get_option("omochix_design_rewrite_version");') && readlink($theme_link) === $theme_real);
check('end: the sitemap rule (^design-sitemap\\.xml$) is stored too and the sitemap answers', false !== strpos(shortinit('echo implode(" ", array_keys((array) get_option("rewrite_rules")));'), '^' . preg_quote('design-sitemap.xml', '#') . '$') && 200 === status('/design-sitemap.xml')); // the key is built with preg_quote, like the theme
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
