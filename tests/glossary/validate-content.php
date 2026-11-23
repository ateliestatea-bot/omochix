<?php
/**
 * Check the glossary content of the seo stream (T-DATA).
 *
 *   php tests/glossary/validate-content.php [content/glossary] [--release] [--today=YYYY-MM-DD] [--json]
 *
 * Reads <dir>/categories.json and every <dir>/terms/*.json with the same
 * rules as the importer and the publish guard (Core
 * includes/web2/glossary-validate.php). Nothing is written and no network is
 * used: whether each source really says what the term says is the human
 * content QA (docs/web2/kh/README.md).
 *
 * --release: 50 terms and at least 3 per category are required (otherwise
 * warnings). Exit code 0 when there is no error, 1 otherwise, 2 on bad input.
 */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__, 2) . '/wp-plugin/omochix-core/includes/web2/glossary-validate.php';

$args = array_slice($argv, 1);
$dir = null; $release = false; $json = false; $today = gmdate('Y-m-d');
foreach ($args as $a) {
    if ('--release' === $a) { $release = true; }
    elseif ('--json' === $a) { $json = true; }
    elseif (0 === strpos($a, '--today=')) { $today = substr($a, 8); }
    elseif ('' !== $a && '-' !== $a[0] && null === $dir) { $dir = $a; }
    else { fwrite(STDERR, "unknown option: $a\n"); exit(2); }
}
if ('' !== omochix_core_glossary_date_problem($today, '9999-12-31')) { fwrite(STDERR, "--today must be YYYY-MM-DD\n"); exit(2); }
$dir = rtrim($dir ?? (dirname(__DIR__, 2) . '/content/glossary'), '/');
$real = realpath($dir);
if (false === $real || !is_dir($real)) { fwrite(STDERR, "no such folder: $dir\n"); exit(2); }

$read = static function ($path) {
    if (is_link($path) || !is_file($path)) { return [null, 'not a regular file']; }
    if (filesize($path) > 262144) { return [null, 'larger than 256 KB']; }
    $raw = (string) file_get_contents($path);
    if (0 === strncmp($raw, "\xEF\xBB\xBF", 3)) { return [null, 'starts with a byte order mark']; }
    try { return [json_decode($raw, true, 32, JSON_THROW_ON_ERROR), '']; }
    catch (JsonException $e) { return [null, 'invalid JSON: ' . $e->getMessage()]; }
};
$errors = []; $terms = [];
[$cats, $why] = $read("$real/categories.json");
if ('' !== $why) { $errors[] = "categories.json: $why"; $cats = null; }
$files = is_dir("$real/terms") ? glob("$real/terms/*") : [];
sort($files);
foreach ($files as $f) {
    $name = 'terms/' . basename($f);
    if (!preg_match('/^[a-z0-9-]+\.json$/D', basename($f))) { $errors[] = "$name: file name must be <slug>.json"; continue; }
    [$t, $why] = $read($f);
    if ('' !== $why) { $errors[] = "$name: $why"; continue; }
    $terms[$name] = $t;
}
$res = omochix_core_glossary_validate_collection($terms, $cats, ['today' => $today, 'release' => $release]);
$errors = array_merge($errors, $res['errors']);
if ($json) {
    echo json_encode(['terms' => count($terms), 'counts' => $res['counts'], 'errors' => $errors, 'warnings' => $res['warnings']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
} else {
    foreach ($errors as $e) { echo "ERROR $e\n"; }
    foreach ($res['warnings'] as $w) { echo "WARN  $w\n"; }
    printf("\n%d terms, %d errors, %d warnings (%s mode, today %s)\n", count($terms), count($errors), count($res['warnings']), $release ? 'release' : 'draft', $today);
    foreach ($res['counts'] as $c => $n) { printf("  %-18s %d\n", $c, $n); }
}
exit($errors ? 1 : 0);
