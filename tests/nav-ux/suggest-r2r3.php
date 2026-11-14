<?php
/**
 * R2 / R3: suggest-index responses and user-state restoration. Dev-environment test.
 * Usage: php tests/nav-ux/suggest-r2r3.php /path/to/wp-load.php
 * Touches only the suggest transients (index + lock), and leaves them flushed (the next request rebuilds the index).
 */
require $argv[1];
$T = OMOCHIX_SUGGEST_TRANSIENT;
$L = $T . '_lock';
$fail = 0;
$check = static function ($name, $ok, $detail = '') use (&$fail) {
    if (!$ok) { $fail++; }
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ('' !== $detail ? "  ($detail)" : '') . "\n";
};
$call = static function () {
    $res = rest_do_request(new WP_REST_Request('GET', '/omochix/v1/suggest-index'));
    return [$res->get_status(), $res->get_headers(), $res->get_data()];
};
do_action('rest_api_init');
$admin = get_users(['role' => 'administrator', 'number' => 1]);
$admin_id = $admin ? $admin[0]->ID : 1;

// R2-1: normal index -> 200, public cache, ETag = v.
delete_transient($T); delete_transient($L);
[$st, $h, $d] = $call();
$check('normal: 200 + public cache + ETag', 200 === $st && false !== strpos($h['Cache-Control'] ?? '', 'public') && ($h['ETag'] ?? '') === '"' . $d['v'] . '"' && count($d['items']) > 0, "status=$st items=" . count($d['items']));

// R2-2: rebuild in progress (lock held, no index) -> 503, no-store, Retry-After, no ETag, nothing cached.
delete_transient($T); set_transient($L, 1, 30);
[$st, $h, $d] = $call();
$check('warming: 503 + no-store + Retry-After, no ETag', 503 === $st && 'no-store' === ($h['Cache-Control'] ?? '') && isset($h['Retry-After']) && !isset($h['ETag']) && 'warming' === $d['v'] && [] === $d['items'], "status=$st cc=" . ($h['Cache-Control'] ?? '-'));
$check('warming: index not written', false === get_transient($T));
delete_transient($L);

// R2-3: a real but empty index stays a normal, cacheable 200 (distinguished from "warming" by its hash).
set_transient($T, ['items' => [], 'v' => substr(md5(wp_json_encode([])), 0, 12)], 60);
[$st, $h, $d] = $call();
$check('empty real index: 200 + public cache', 200 === $st && false !== strpos($h['Cache-Control'] ?? '', 'public') && 'warming' !== $d['v'] && [] === $d['items'], "status=$st v=" . $d['v']);
delete_transient($T);

// R3: the build throws (Exception and Error) while an administrator is the current user.
foreach (['RuntimeException' => static function () { throw new RuntimeException('r3 test'); }, 'Error' => static function () { throw new Error('r3 test'); }] as $kind => $thrower) {
    delete_transient($T); delete_transient($L);
    wp_set_current_user($admin_id);
    $hook = static function ($q) use ($thrower) { $thrower(); };
    add_action('pre_get_posts', $hook);
    $caught = null;
    try { omochix_suggest_index(); } catch (Throwable $e) { $caught = get_class($e); }
    remove_action('pre_get_posts', $hook);
    $check("R3 $kind: thrown to caller", $kind === $caught, (string) $caught);
    $check("R3 $kind: user restored", get_current_user_id() === $admin_id, 'user=' . get_current_user_id());
    $check("R3 $kind: no partial index cached", false === get_transient($T));
    $check("R3 $kind: lock kept (backoff)", (bool) get_transient($L));
    [$st, $h] = $call();
    $check("R3 $kind: while locked -> 503 no-store", 503 === $st && 'no-store' === ($h['Cache-Control'] ?? ''));
    // Recovery: once the 30s lock expires (simulated), the next request rebuilds normally, as the anonymous context, and restores the caller.
    delete_transient($L);
    $data = omochix_suggest_index();
    $check("R3 $kind: recovers after lock expiry", count($data['items']) > 0 && 'warming' !== $data['v'] && false === get_transient($L) && get_current_user_id() === $admin_id);
    $check("R3 $kind: recovered index has no restricted entry", 0 === count(array_filter($data['items'], static fn($e) => false !== strpos($e['t'], 'LEAKTEST'))));
}

// Normal path: caller restored after a successful build.
delete_transient($T); delete_transient($L);
wp_set_current_user($admin_id);
omochix_suggest_index();
$check('normal build: caller restored', get_current_user_id() === $admin_id);

delete_transient($T); delete_transient($L);
echo $fail ? "FAIL: $fail\n" : "PASS: all R2/R3 checks\n";
exit($fail ? 1 : 0);
