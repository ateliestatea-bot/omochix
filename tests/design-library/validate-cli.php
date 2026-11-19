<?php
/**
 * Validate a catalog file with the theme's PHP validator and print JSON
 * {"items": [accepted slugs], "errors": [...]}. Used by catalog-check.js to
 * prove the PHP validator and the JSON Schema reject the same bad catalogs.
 *
 *   php tests/design-library/validate-cli.php path/to/catalog.json
 */
require __DIR__ . '/stub.php';
ini_set('error_log', '/dev/null');
$json   = (string) file_get_contents($argv[1] ?? '');
$result = omochix_design_validate_catalog(json_decode($json, true), json_decode($json));
echo json_encode(['items' => array_keys($result['items']), 'errors' => $result['errors']], JSON_UNESCAPED_SLASHES), "\n";
