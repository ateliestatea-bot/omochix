<?php
/**
 * Prints the PHP side of the pricing rules as JSON for tests/labs/data.js:
 * the allowed source hosts and pricing_view() / pricing_active() for the
 * shipped pricing.json and a set of mutated rows, on several dates.
 */
require __DIR__ . '/stub.php';

$raw   = omochix_labs_read_guarded_json(omochix_labs_pricing_file())['data'];
$cases = json_decode(file_get_contents('php://stdin'), true);
$out   = ['hosts' => OMOCHIX_LABS_PRICE_SOURCE_HOSTS, 'results' => []];
foreach ($cases as $case) {
    $data = $raw;
    foreach (($case['review'] ?? []) as $key => $value) {
        $data['review'][$key] = $value;
    }
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
        'states' => array_map(static fn($m) => $m['id'] . '@' . $m['input'] . '=' . $m['approval'], $view['models']),
        'file_approved' => omochix_labs_pricing_approved($data),
        'gate' => array_map(static function ($production) use ($view, $data) {
            $g = omochix_labs_pricing_gate($view, omochix_labs_pricing_approved($data), $production);
            return ['models' => array_column($g['models'], 'id'), 'withheld' => $g['withheld'], 'hidden' => (object) $g['hidden']];
        }, [true, false]),
    ];
}
echo json_encode($out);
