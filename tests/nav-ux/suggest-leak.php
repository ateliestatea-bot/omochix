<?php
/**
 * D3: the public suggest index must not depend on who (or which cookies) triggered its rebuild.
 * Dev-environment test (needs a WordPress with the LEAKTEST fixtures). Usage: php tests/nav-ux/suggest-leak.php /path/to/wp-load.php
 * Scenarios: anonymous rebuild / administrator rebuild / rebuild while holding a valid post-password cookie.
 */
require $argv[1];
$scan = static function () {
    omochix_suggest_flush();
    delete_transient(OMOCHIX_SUGGEST_TRANSIENT . '_lock');
    $items = omochix_suggest_index()['items'];
    $leaks = array_values(array_filter($items, static fn($e) => false !== strpos($e['t'], 'LEAKTEST')));
    return ['count' => count($items), 'leaks' => array_map(static fn($e) => $e['y'] . ': ' . $e['t'], $leaks)];
};
$out = [];
wp_set_current_user(0); unset($_COOKIE['wp-postpass_' . COOKIEHASH]);
$out['anonymous'] = $scan();
$admin = get_users(['role' => 'administrator', 'number' => 1]);
wp_set_current_user($admin ? $admin[0]->ID : 1);
$out['administrator'] = $scan();
require_once ABSPATH . WPINC . '/class-phpass.php';
$hasher = new PasswordHash(8, true);
$_COOKIE['wp-postpass_' . COOKIEHASH] = $hasher->HashPassword(wp_unslash('pw123'));   // what wp-login.php?action=postpass sets
$out['administrator+postpass-cookie'] = $scan();
wp_set_current_user(0);
$out['anonymous+postpass-cookie'] = $scan();
unset($_COOKIE['wp-postpass_' . COOKIEHASH]);
omochix_suggest_flush();
$fail = array_sum(array_map(static fn($s) => count($s['leaks']), $out));
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n", $fail ? "FAIL: $fail leaked entries\n" : "PASS: no restricted entry in the public index in any scenario\n";
exit($fail ? 1 : 0);
