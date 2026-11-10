<?php
/**
 * Industry Hub compatibility tests: everything the Core plugin registered at
 * the base commit (fixtures/base-registrations.json, taken before any
 * Industry Hub code) is still registered with exactly the same arguments.
 * Industry Hub may only add new names.
 * Run: php tests/industry-hub/compat.php
 */
require __DIR__ . '/stub.php';

$base = json_decode((string) file_get_contents(__DIR__ . '/fixtures/base-registrations.json'), true);
$now  = json_decode((string) json_encode(ih_registration_snapshot(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);

check('fixture: base snapshot is readable and has the three existing post types', is_array($base) && ['ai_tool', 'learn_practice', 'prompt'] === array_keys($base['post_types']));

foreach ($base['post_types'] as $name => $args) {
    check("post type $name: arguments unchanged (slug, archive, rest, supports, …)", isset($now['post_types'][$name]) && $args === $now['post_types'][$name]);
}
foreach ($base['taxonomies'] as $name => $tax) {
    check("taxonomy $name: object types and arguments unchanged", isset($now['taxonomies'][$name]) && $tax === $now['taxonomies'][$name]);
}
foreach ($base['post_meta'] as $type => $keys) {
    $same = true;
    foreach ($keys as $key => $args) {
        if (!isset($now['post_meta'][$type][$key]) || $args !== $now['post_meta'][$type][$key]) { $same = false; echo "  changed: $type.$key\n"; }
    }
    check("post meta on $type: all " . count($keys) . ' existing keys registered unchanged', $same);
}
$missing_rules = array_filter($base['rewrite'], static fn($rule) => !in_array($rule, $now['rewrite'], true));
check('rewrite: every existing rule is still registered', [] === $missing_rules);
$lost = [];
foreach ($base['hooks'] as $hook => $callbacks) {
    foreach (array_diff($callbacks, $now['hooks'][$hook] ?? []) as $cb) { $lost[] = "$hook $cb"; }
}
foreach ($lost as $l) { echo "  lost hook: $l\n"; }
check('hooks: no existing callback was removed or re-prioritised', [] === $lost);

$new_types = array_diff(array_keys($now['post_types']), array_keys($base['post_types']));
$new_tax   = array_diff(array_keys($now['taxonomies']), array_keys($base['taxonomies']));
check('additions: new post types only use the omx_ prefix', !array_filter($new_types, static fn($n) => 0 !== strpos($n, 'omx_')));
check('additions: new taxonomies only use the omx_ prefix', !array_filter($new_tax, static fn($n) => 0 !== strpos($n, 'omx_')));
$new_rules = array_filter($now['rewrite'], static fn($rule) => !in_array($rule, $base['rewrite'], true));
check('additions: Phase 1 adds no rewrite rules (no new public URL)', [] === array_values($new_rules));

// The theme-owned Motion catalog is data the plugin must never rewrite.
$catalog = realpath(__DIR__ . '/../../wp-theme/omochix-theme/assets/data/motion/catalog.json');
$items   = json_decode((string) file_get_contents($catalog), true)['items'] ?? [];
check('motion: catalog.json still has the 53 samples', 53 === count($items));
check('motion: catalog.json is byte-identical to the base commit', '5d91ea9ad85d334dbdc608a72db45e7dd5ae945f' === sha1_file($catalog));

ih_done();
