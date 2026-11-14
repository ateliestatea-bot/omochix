<?php
/**
 * Release gate: what this tree would ship, checked against the release config AND
 * against independent expectations recorded by hand (tests/release/expected/<config>.json).
 * The config is never only compared with itself: counts, the exact set of changed paths
 * (as a SHA-256), the baseline files' SHA-256, the allowed Labs files, the switch files,
 * the ordering rules and the rollback come from the expectations file.
 *
 * - Core: the Version header, OMOCHIX_CORE_VERSION, readme.txt "Stable tag", a Changelog
 *   entry and the Industry Hub version markers agree, with the expected release version.
 * - Theme: style.css carries the expected release version, newer than the baseline.
 * - Scope: against the production baseline (tools/release/baseline/*.sha256), the files that
 *   differ are exactly the config's approved steps and match the expected counts and set;
 *   nothing is deleted; packages that the release must not touch are byte-identical to
 *   their baseline; no symlinks; forbidden areas (Labs, SVG Motion) only through the
 *   files the expectations name one by one.
 * - Order: switch files last, the expected ordering rules, the expected rollback.
 *
 * Run: php tests/release/release-gate.php
 * Env (all optional): OMOCHIX_RELEASE_CONFIG  a file name in tools/release/configs/ (default tools/release/release.json)
 *                     OMOCHIX_RELEASE_TREE    folder that holds wp-plugin/ and wp-theme/ (default: this repository)
 *                     OMOCHIX_RELEASE_REPO    folder that holds tools/release/ and tests/release/expected/ (tests only)
 */
$repo = getenv('OMOCHIX_RELEASE_REPO') ?: dirname(__DIR__, 2);
$tree = getenv('OMOCHIX_RELEASE_TREE') ?: $repo;
$name = getenv('OMOCHIX_RELEASE_CONFIG') ?: '';
$pass = 0; $fail = 0;
function gate($name, $ok, $detail = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }
function stop($msg) { echo "FAIL $msg\n\n0 passed, 1 failed\n"; exit(1); }
function read_json($path) { $d = is_file($path) ? json_decode((string) file_get_contents($path), true) : null; if (!is_array($d)) { stop('cannot read ' . basename($path)); } return $d; }
function manifest($path) {
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $parts = explode('  ', $line, 2);
        if (2 !== count($parts) || !preg_match('/^[0-9a-f]{64}$/', $parts[0]) || isset($out[$parts[1]])) { stop('malformed baseline line in ' . basename($path)); }
        $out[$parts[1]] = $parts[0];
    }
    return $out;
}
/** Files of a package folder (relative path => sha256); symlinks are reported, never followed. */
function package_files($dir, &$links) {
    $now = [];
    if (!is_dir($dir)) { return $now; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) {
        $rel = substr((string) $f, strlen($dir) + 1);
        if (is_link((string) $f)) { $links[] = $rel; continue; }
        if ($f->isDir() || '.DS_Store' === basename($rel)) { continue; }
        $now[$rel] = hash_file('sha256', (string) $f);
    }
    return $now;
}

if ('' !== $name && (false !== strpos($name, '/') || false !== strpos($name, '\\') || '.json' !== substr($name, -5) || '.' === $name[0])) { stop('OMOCHIX_RELEASE_CONFIG must be a file name in tools/release/configs/'); }
$cfgPath = '' !== $name ? "$repo/tools/release/configs/$name" : "$repo/tools/release/release.json";
$cfg = read_json($cfgPath);
$exp = read_json("$repo/tests/release/expected/" . ('' !== $name ? $name : 'release.json'));
echo "release: {$cfg['release']}\n";
gate('expectations: written for this release', ($exp['release'] ?? null) === $cfg['release'], ($exp['release'] ?? '-') . ' vs ' . $cfg['release']);

// Versions.
$core = "$tree/wp-plugin/omochix-core";
$main = (string) @file_get_contents("$core/omochix-core.php");
preg_match("/define\\( 'OMOCHIX_CORE_VERSION', '([^']+)' \\);/", $main, $m); $version = $m[1] ?? '';
preg_match('/^ \* Version: (\S+)/m', $main, $m); $header = $m[1] ?? '';
$readme = (string) @file_get_contents("$core/readme.txt");
preg_match('/^Stable tag: (\S+)/m', $readme, $m); $stable = $m[1] ?? '';
gate('core: a release version (x.y.z, no -dev)', (bool) preg_match('/^\d+\.\d+\.\d+$/', $version), $version);
gate('core: the Version header, OMOCHIX_CORE_VERSION and readme.txt Stable tag agree', $version === $header && $version === $stable, "$header / $version / $stable");
gate('core: the expected version', $version === ($exp['versions']['core'] ?? null), $version . ' vs ' . ($exp['versions']['core'] ?? '-'));
gate('core: readme.txt has a Changelog entry for this version', false !== strpos($readme, "== Changelog ==") && false !== strpos($readme, "= $version ="));
$marked = 0; $bad = [];
if (is_dir($core)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($core, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ('php' !== $f->getExtension()) { continue; }
        $c = (string) file_get_contents((string) $f);
        if (preg_match_all('#^// omochix-industry-hub-version: (\S+)( \(end\))?$#m', $c, $mm)) { $marked++; foreach ($mm[1] as $v) { if ($v !== $version) { $bad[] = basename((string) $f) . ":$v"; } } }
    }
}
gate("core: all {$exp['core_version_markers']} Industry Hub module files carry the release version marker", $exp['core_version_markers'] === $marked && !$bad, "$marked files; " . implode(', ', $bad));
preg_match('/^Version:\s*(\S+)/m', (string) @file_get_contents("$tree/wp-theme/omochix-theme/style.css"), $m); $theme = $m[1] ?? '';
gate('theme: a release version (x.y.z, no -dev)', (bool) preg_match('/^\d+\.\d+\.\d+$/', $theme), $theme);
gate('theme: the expected version', $theme === ($exp['versions']['theme'] ?? null), $theme . ' vs ' . ($exp['versions']['theme'] ?? '-'));
if (isset($cfg['packages']['theme'])) {
    gate('theme: newer than the production baseline (no downgrade)', version_compare($theme, $cfg['packages']['theme']['baseline_version'], '>'), $theme . ' vs ' . $cfg['packages']['theme']['baseline_version']);
}

// Scope against the production baseline.
$pk = array_keys($cfg['packages']); $pe = array_keys($exp['packages']); sort($pk); sort($pe);
gate('scope: the release ships exactly the expected packages', $pk === $pe, implode(',', $pk) . ' vs ' . implode(',', $pe));
$order = array_map(static fn($s) => $s[0] . ':' . $s[1], $cfg['order']);
$diff = []; $kind = [];
foreach ($cfg['packages'] as $key => $pkg) {
    $want = $exp['packages'][$key] ?? null;
    $bpath = "$repo/{$pkg['baseline']}";
    gate("{$pkg['name']}: baseline file is the expected production manifest", is_file($bpath) && $want && hash_file('sha256', $bpath) === $want['baseline_sha256'], $pkg['baseline']);
    if (!is_file($bpath) || !$want) { continue; }
    $base = manifest($bpath); $links = [];
    $now = package_files("$tree/{$pkg['dir']}", $links);
    gate("{$pkg['name']}: no symlinks", !$links, implode(', ', $links));
    $deleted = array_diff_key($base, $now);
    gate("{$pkg['name']}: nothing that production has is deleted", !$deleted, implode(', ', array_keys($deleted)));
    $add = 0; $rep = 0;
    foreach ($now as $rel => $h) {
        if (!isset($base[$rel])) { $add++; $diff[] = "$key:$rel"; $kind["$key:$rel"] = 'add'; }
        elseif ($base[$rel] !== $h) { $rep++; $diff[] = "$key:$rel"; $kind["$key:$rel"] = 'replace'; }
    }
    gate("{$pkg['name']}: {$want['add']} added and {$want['replace']} replaced files", $add === $want['add'] && $rep === $want['replace'], "$add added, $rep replaced");
    gate("{$pkg['name']}: {$want['files']} files in the package", count($now) === $want['files'], (string) count($now));
}
foreach ($exp['unchanged'] as $key => $u) {
    $bpath = "$repo/{$u['baseline']}";
    gate("$key: baseline file is the expected production manifest", is_file($bpath) && hash_file('sha256', $bpath) === $u['baseline_sha256'], $u['baseline']);
    gate("$key: not part of this release", !isset($cfg['packages'][$key]));
    if (!is_file($bpath)) { continue; }
    $base = manifest($bpath); $links = [];
    $now = package_files("$tree/{$u['dir']}", $links);
    $changed = array_keys(array_diff_assoc($now, $base)); $gone = array_keys(array_diff_key($base, $now));
    gate("$key: byte-identical to production (no change at all)", !$links && !$changed && !$gone, 'changed: ' . implode(', ', $changed) . ' | missing: ' . implode(', ', $gone) . ($links ? ' | links: ' . implode(', ', $links) : ''));
}
sort($diff); $sorted = $order; sort($sorted);
gate('scope: the files that differ from production are exactly the approved steps', $diff === $sorted, 'not approved: ' . implode(', ', array_diff($diff, $order)) . ' | planned but unchanged: ' . implode(', ', array_diff($order, $diff)));
gate("scope: {$exp['steps']} steps", $exp['steps'] === count($order) && count(array_unique($order)) === count($order), (string) count($order));
gate('scope: the changed files are exactly the expected set', hash('sha256', implode('', array_map(static fn($p) => "$p\n", $diff))) === $exp['changed_paths_sha256']);
$allowed = $exp['forbidden']['allowed'];
$hit = array_values(array_filter($diff, static fn($s) => (bool) preg_match($exp['forbidden']['pattern'], $s)));
gate('scope: no Labs or SVG Motion file except the ones the expectations name', !array_diff($hit, $allowed), implode(', ', array_diff($hit, $allowed)));
gate('scope: every named Labs exception is really part of this release', !array_diff($allowed, $hit), implode(', ', array_diff($allowed, $hit)));

// Order.
$sw = array_map(static fn($s) => $s[0] . ':' . $s[1], $cfg['switches']);
gate('order: the expected switch files', $sw === $exp['switches'], implode(', ', $sw));
foreach ($cfg['switches'] as [$k, $p]) {
    $pkgSteps = array_values(array_filter($cfg['order'], static fn($s) => $s[0] === $k));
    $pos = array_search([$k, $p], $pkgSteps, true);
    gate("order: switch file $k:$p comes after every new file of its package", false !== $pos && !array_filter(array_slice($pkgSteps, $pos + 1), static fn($s) => !preg_match('#^(style\.css|readme\.txt)$#', $s[1])));
}
foreach ($exp['order_rules'] as $r) {
    $pkgKey = strstr($r['path'], ':', true);
    $at = array_search($r['path'], $order, true);
    $after = $before = [];
    foreach ($order as $i => $s) {
        if (0 !== strpos($s, "$pkgKey:") || $s === $r['path'] || in_array($s, $r['except'], true)) { continue; }
        if (($kind[$s] ?? '') === $r['after_every'] && false !== $at && $i > $at) { $after[] = $s; }
        if (($kind[$s] ?? '') === $r['before_every'] && false !== $at && $i < $at) { $before[] = $s; }
    }
    gate("order: {$r['path']} after every {$r['after_every']} and before every {$r['before_every']} of its package", false !== $at && !$after && !$before, 'comes after: ' . implode(', ', $before) . ' | comes before: ' . implode(', ', $after));
}
$gk = array_keys($cfg['gates']);
gate('order: the expected gates', $gk === $exp['gates'], implode(',', $gk));
foreach ($cfg['gates'] as $g => $def) { gate("order: gate $g follows a step of the release", in_array($def['after'][0] . ':' . $def['after'][1], $order, true)); }
$rb = array_map(static fn($s) => $s[0] . ':' . $s[1], $cfg['rollback_order']);
$replaced = array_keys(array_filter($kind, static fn($k) => 'replace' === $k)); $rbs = $rb; sort($rbs); sort($replaced);
gate('rollback: restores exactly the replaced files', $rbs === $replaced && count($rb) === $exp['rollback']['count'], count($rb) . ' entries');
gate('rollback: the expected file first' . (isset($exp['rollback']['last']) ? ' and last' : ''), ($rb[0] ?? '') === $exp['rollback']['first'] && (!isset($exp['rollback']['last']) || end($rb) === $exp['rollback']['last']));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
