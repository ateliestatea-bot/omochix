<?php
/**
 * Print the Core plugin's registrations (post types, taxonomies, post meta,
 * rewrite rules, hook inventory) as JSON. fixtures/base-registrations.json
 * was produced with this script at the Industry Hub base commit 94152a7,
 * before any Industry Hub code existed:
 *   php tests/industry-hub/snapshot.php > tests/industry-hub/fixtures/base-registrations.json
 */
require __DIR__ . '/stub.php';
echo json_encode(ih_registration_snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
