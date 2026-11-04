<?php
/**
 * Prints the PHP side of the pricing rules as JSON for tests/labs/data.js:
 * the allowed source hosts and pricing_view() / pricing_active() for the
 * shipped pricing.json and a set of mutated rows, on several dates.
 */
require __DIR__ . '/stub.php';

$raw   = json_decode(file_get_contents(omochix_labs_data_file('pricing')), true);
$cases = json_decode(file_get_contents('php://stdin'), true);
$out   = ['hosts' => OMOCHIX_LABS_PRICE_SOURCE_HOSTS, 'results' => []];
foreach ($cases as $case) {
    $data = $raw;
    foreach ($case['mutations'] as [$index, $key, $value]) {
        if (null === $value) {
            unset($data['models'][$index][$key]);
        } else {
            $data['models'][$index][$key] = $value;
        }
    }
    $view = omochix_labs_pricing_view($data, $case['date']);
    $out['results'][] = [
        'view'   => array_map(static fn($m) => $m['id'] . '@' . $m['input'], $view['models']),
        'hidden' => (object) $view['hidden'],
        'active' => array_map(static fn($m) => $m['id'] . '@' . $m['input'], omochix_labs_pricing_active($view['models'], $case['date'], $view['max_age_days'])),
    ];
}
echo json_encode($out);
