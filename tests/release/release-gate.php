<?php
/**
 * Release gate (Industry Hub Phase 2): what this commit would ship.
 * Reads the working tree only (no git history needed, so it runs in CI).
 *
 * - Core: the Version header, OMOCHIX_CORE_VERSION, readme.txt "Stable tag",
 *   a Changelog entry and the Industry Hub version markers all say the same
 *   release version (no "-dev").
 * - Theme: style.css carries a release version (no "-dev").
 * - Scope: against the production baseline (tools/release/baseline/*.sha256,
 *   Core 1.9.3 / theme 1.28.0 = Labs), the files that differ are exactly the approved
 *   steps in tools/release/release.json: nothing deleted, nothing else changed
 *   (so no Labs or SVG Motion file can ride along), switch files last.
 *
 * Run: php tests/release/release-gate.php
 */
$root = dirname(__DIR__, 2);
$pass = 0; $fail = 0;
function gate($name, $ok, $detail = '') { global $pass, $fail; if ($ok) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name" . ('' !== $detail ? "  [$detail]" : '') . "\n"; } }

$cfg  = json_decode((string) file_get_contents("$root/tools/release/release.json"), true);
$core = "$root/wp-plugin/omochix-core";
$main = (string) file_get_contents("$core/omochix-core.php");
preg_match("/define\\( 'OMOCHIX_CORE_VERSION', '([^']+)' \\);/", $main, $m); $version = $m[1] ?? '';
preg_match('/^ \* Version: (\S+)/m', $main, $m); $header = $m[1] ?? '';
$readme = (string) file_get_contents("$core/readme.txt");
preg_match('/^Stable tag: (\S+)/m', $readme, $m); $stable = $m[1] ?? '';
gate('core: a release version (x.y.z, no -dev)', (bool) preg_match('/^\d+\.\d+\.\d+$/', $version), $version);
gate('core: the Version header, OMOCHIX_CORE_VERSION and readme.txt Stable tag agree', $version === $header && $version === $stable, "$header / $version / $stable");
gate('core: readme.txt has a Changelog entry for this version', false !== strpos($readme, "== Changelog ==") && false !== strpos($readme, "= $version ="));
$marked = 0; $bad = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($core, FilesystemIterator::SKIP_DOTS)) as $f) {
    if ('php' !== $f->getExtension()) { continue; }
    $c = (string) file_get_contents((string) $f);
    if (preg_match_all('#^// omochix-industry-hub-version: (\S+)( \(end\))?$#m', $c, $mm)) { $marked++; foreach ($mm[1] as $v) { if ($v !== $version) { $bad[] = basename((string) $f) . ":$v"; } } }
}
gate('core: all 11 Industry Hub module files carry the release version marker', 11 === $marked && !$bad, "$marked files; " . implode(', ', $bad));

preg_match('/^Version:\s*(\S+)/m', (string) file_get_contents("$root/wp-theme/omochix-theme/style.css"), $m); $theme = $m[1] ?? '';
gate('theme: a release version (x.y.z, no -dev)', (bool) preg_match('/^\d+\.\d+\.\d+$/', $theme), $theme);
gate('theme: newer than the production baseline (no downgrade)', version_compare($theme, $cfg['packages']['theme']['baseline_version'], '>'), $theme . ' vs ' . $cfg['packages']['theme']['baseline_version']);

// Scope against the production baseline.
$order = array_map(static fn($s) => $s[0] . ':' . $s[1], $cfg['order']);
$diff = [];
foreach ($cfg['packages'] as $key => $pkg) {
    $base = [];
    foreach (file("$root/{$pkg['baseline']}", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) { [$h, $p] = explode('  ', $line, 2); $base[$p] = $h; }
    $dir = "$root/{$pkg['dir']}"; $now = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        $rel = substr((string) $f, strlen($dir) + 1);
        if ('.DS_Store' === basename($rel)) { continue; }
        $now[$rel] = hash_file('sha256', (string) $f);
    }
    $deleted = array_diff_key($base, $now);
    gate("{$pkg['name']}: nothing that production has is deleted", !$deleted, implode(', ', array_keys($deleted)));
    foreach ($now as $rel => $h) { if (($base[$rel] ?? null) !== $h) { $diff[] = "$key:$rel"; } }
}
sort($diff); $sorted = $order; sort($sorted);
gate('scope: the files that differ from production are exactly the approved 29 steps', $diff === $sorted, 'not approved: ' . implode(', ', array_diff($diff, $order)) . ' | planned but unchanged: ' . implode(', ', array_diff($order, $diff)));
gate('scope: 29 steps (Core 14, theme 15)', 29 === count($order) && 14 === count(array_filter($order, static fn($s) => 0 === strpos($s, 'core:'))));
$labs = array_filter($diff, static fn($s) => (bool) preg_match('#labs|svg-motion|logo-motion#i', $s));
gate('scope: no Labs or SVG Motion file', !$labs, implode(', ', $labs));
foreach ($cfg['switches'] as [$k, $p]) {
    $pkgSteps = array_values(array_filter($cfg['order'], static fn($s) => $s[0] === $k));
    $pos = array_search([$k, $p], $pkgSteps, true);
    gate("order: switch file $k:$p comes after every new file of its package", false !== $pos && !array_filter(array_slice($pkgSteps, $pos + 1), static fn($s) => !preg_match('#^(style\.css|readme\.txt)$#', $s[1])));
}
$rb = array_map(static fn($s) => $s[0] . ':' . $s[1], $cfg['rollback_order']);
gate('rollback: restores exactly the replaced files, switch files first', 'theme:functions.php' === $rb[0] && in_array('core:omochix-core.php', $rb, true) && 6 === count($rb));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
