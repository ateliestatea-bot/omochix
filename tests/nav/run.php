<?php
/** Navigation registry tests (inc/nav.php) with minimal WordPress stubs. Run: php tests/nav/run.php */
define('ABSPATH', __DIR__ . '/');
$GLOBALS['opts'] = []; $GLOBALS['filters'] = []; $GLOBALS['pages'] = ['learn' => 'https://x.test/learn/', 'labs' => 'https://x.test/labs/', 'about' => 'https://x.test/about/', 'videos' => 'https://x.test/videos/'];
function __($s) { return $s; } function esc_html__($s) { return $s; } function esc_html_e($s) { echo $s; } function esc_html($s) { return htmlspecialchars((string) $s); } function esc_attr($s) { return htmlspecialchars((string) $s); } function esc_url($s) { return (string) $s; }
function get_option($k, $d = false) { return $GLOBALS['opts'][$k] ?? $d; }
function add_filter($h, $f, $p = 10) { $GLOBALS['filters'][$h][] = $f; } function remove_filter($h, $f) {}
function apply_filters($h, $v, ...$a) { foreach ($GLOBALS['filters'][$h] ?? [] as $f) { $v = $f($v, ...$a); } return $v; }
function get_post($id) { return null; } function get_permalink($p) { return ''; } function get_post_type_archive_link($t) { return ['ai_tool' => 'https://x.test/ai-tools/', 'prompt' => 'https://x.test/prompts/'][$t] ?? false; }
function get_category_by_slug($s) { return null; } function omochix_get_category_url($c) { return ''; }
function omochix_get_published_page_url($slug) { return $GLOBALS['pages'][$slug] ?? ''; }
function is_home() { return false; } function is_category(...$a) { return false; } function is_singular(...$a) { return false; } function has_category(...$a) { return false; } function is_post_type_archive(...$a) { return false; } function is_tax(...$a) { return false; } function is_page(...$a) { return false; } function get_page_uri() { return ''; }
function untrailingslashit($s) { return rtrim($s, '/'); } function wp_parse_url($u, $c = -1) { return parse_url($u, $c); } function wp_unslash($s) { return $s; }
require __DIR__ . '/../../wp-theme/omochix-theme/inc/nav.php';

$pass = 0; $fail = 0;
function t($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }
$ids = fn($items) => array_column($items, 'id');

$nav = omochix_nav_items('nav');
t('nav lists six primary items in order', array_slice($ids($nav), 0, 6) === ['news', 'tools', 'dev', 'learn', 'prompts', 'labs']);
t('Labs links to the published labs page', array_values(array_filter($nav, fn($i) => $i['id'] === 'labs'))[0]['url'] === 'https://x.test/labs/');
t('published-but-missing page (news, dev) degrades to coming-soon with no URL', array_values(array_filter($nav, fn($i) => $i['id'] === 'news'))[0]['status'] === 'coming-soon' && array_values(array_filter($nav, fn($i) => $i['id'] === 'dev'))[0]['url'] === '');
$videos = array_values(array_filter($nav, fn($i) => $i['id'] === 'videos'))[0];
t('coming-soon stays text-only even though the videos page exists', $videos['status'] === 'coming-soon' && $videos['url'] === '');
t('hidden (chat) is never rendered', !in_array('chat', $ids($nav), true) && !in_array('chat', $ids(omochix_nav_items('footer')), true));
t('home surface = primary shortcuts only (6 items, no future)', $ids(omochix_nav_items('home')) === ['news', 'tools', 'dev', 'learn', 'prompts', 'labs']);
t('soon surface = coming-soon items only', $ids(omochix_nav_items('soon')) === ['videos', 'community']);
t('footer has about + contact only when published', in_array('about', $ids(omochix_nav_items('footer')), true));

// publishing a future section via the option
$GLOBALS['opts']['omochix_nav_status'] = ['videos' => 'published'];
$v = array_values(array_filter(omochix_nav_items('nav'), fn($i) => $i['id'] === 'videos'))[0];
t('option flips videos to published -> link', $v['status'] === 'published' && $v['url'] === 'https://x.test/videos/');
t('published future item joins home shortcuts (7 items)', count(omochix_nav_items('home')) === 7);
$GLOBALS['opts']['omochix_nav_status'] = ['community' => 'published'];
$c = array_values(array_filter(omochix_nav_items('nav'), fn($i) => $i['id'] === 'community'))[0];
t('published without a page stays coming-soon (never a dead link)', $c['status'] === 'coming-soon' && $c['url'] === '');
$GLOBALS['opts']['omochix_nav_status'] = ['tools' => 'bogus', 'learn' => 'hidden'];
t('invalid status ignored; hidden removes item', in_array('tools', $ids(omochix_nav_items('nav')), true) && !in_array('learn', $ids(omochix_nav_items('nav')), true));
$GLOBALS['opts'] = [];

// extension: register a new section without touching templates
add_filter('omochix_nav_registry', function ($items) { $items['chat'] = ['status' => 'published', 'url' => 'https://x.test/chat/', 'surfaces' => ['nav', 'footer', 'home']] + $items['chat']; return $items; });
t('new section via filter appears on nav, footer and home', in_array('chat', $ids(omochix_nav_items('nav')), true) && in_array('chat', $ids(omochix_nav_items('footer')), true) && in_array('chat', $ids(omochix_nav_items('home')), true));
t('7 shortcuts with chat published: count is 7', count(omochix_nav_items('home')) === 7);

// next steps skips unpublished
$GLOBALS['filters']['omochix_nav_registry'] = [];
ob_start(); omochix_next_steps('learn'); $html = ob_get_clean();
t('next steps lists only published destinations (labs yes, prompts yes, dev no)', strpos($html, 'https://x.test/labs/') !== false && strpos($html, 'https://x.test/prompts/') !== false && strpos($html, 'dev') === false);
ob_start(); omochix_next_steps('unknown'); t('unknown context renders nothing', ob_get_clean() === '');
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
