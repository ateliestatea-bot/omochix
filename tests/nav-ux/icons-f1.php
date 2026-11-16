<?php
/**
 * F1: with a Site Icon configured, the public front end prints only the theme icon set, while wp-admin and wp-login.php keep
 * WordPress's own Site Icon tags. The Site Icon is simulated with the get_site_icon_url filter: no option is written.
 * Usage: php tests/nav-ux/icons-f1.php /path/to/wp-load.php
 */
require $argv[1];
$fail = 0;
$check = static function ($name, $ok, $detail = '') use (&$fail) { if (!$ok) { $fail++; } echo ($ok ? 'PASS ' : 'FAIL ') . $name . ('' !== $detail ? "  ($detail)" : '') . "\n"; };
$icon = 'https://example.test/site-icon.png';
add_filter('get_site_icon_url', static fn() => $icon);
$capture = static function () { ob_start(); wp_site_icon(); $wp = ob_get_clean(); ob_start(); omochix_icon_links(); $theme = ob_get_clean(); return [$wp, $theme]; };

[$wp, $theme] = $capture();
$check('front: WordPress Site Icon tags removed', false === strpos($wp, $icon), trim($wp) === '' ? 'none' : 'present');
$check('front: theme prints its 5 icon tags', 5 === substr_count($theme, '<link'));

require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
set_current_screen('dashboard');   // is_admin() === true from here
[$wp] = $capture();
$check('admin: WordPress Site Icon tags kept', substr_count($wp, $icon) >= 3, substr_count($wp, $icon) . ' tags');
$GLOBALS['current_screen'] = null;

$GLOBALS['wp_actions']['login_init'] = 1;   // as on wp-login.php, without running the login hooks
[$wp] = $capture();
$check('login: WordPress Site Icon tags kept', substr_count($wp, $icon) >= 3, substr_count($wp, $icon) . ' tags');
unset($GLOBALS['wp_actions']['login_init']);

add_filter('omochix_theme_icons_override_site_icon', '__return_false');
[$wp, $theme] = $capture();
$check('opt-out filter: Site Icon wins on the front end, theme prints nothing', substr_count($wp, $icon) >= 3 && '' === $theme);

echo $fail ? "FAIL: $fail\n" : "PASS: F1\n";
exit($fail ? 1 : 0);
