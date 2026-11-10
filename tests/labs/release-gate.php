<?php
/**
 * OmochiX Labs release gate (PHP side): what a visitor can reach.
 *
 *   php tests/labs/release-gate.php                  the shipped theme
 *   php tests/labs/release-gate.php --published-copy the theme with every tool published
 *
 * Run through `node tests/labs/data.js --release`, which also checks the price
 * review row by row when the cost calculator is published. See release-state.php.
 */
require __DIR__ . '/stub.php';
require __DIR__ . '/release-state.php';

$theme = $GLOBALS['THEME'];
if (in_array('--published-copy', $argv, true)) {
    $theme = labs_published_theme($theme);
}
$catalog = json_decode(file_get_contents($theme . '/assets/data/labs/catalog.json'), true);
$costStatus = 'missing';
foreach ($catalog['tools'] ?? [] as $tool) {
    if (LABS_COST_SLUG === ($tool['slug'] ?? null)) {
        $costStatus = $tool['status'] ?? 'missing';
    }
}
$problems = labs_release_state_problems($theme);
foreach ($problems as $problem) {
    echo "FAIL release: $problem\n";
}
echo ($problems ? 'release gate (PHP): FAIL' : 'release gate (PHP): PASS') . " — cost calculator is $costStatus\n";
exit($problems ? 1 : 0);
