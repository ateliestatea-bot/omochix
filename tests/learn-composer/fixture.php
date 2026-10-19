<?php
/**
 * Prints what the PHP composer model produces for a set of raw compositions
 * and hash payloads, as JSON, for tests/learn-composer/core-parity.js.
 */
require __DIR__ . '/../learn-gallery/stub.php';
require $THEME . '/inc/learn-composer.php';

$GLOBALS['pages']['learn/motion'] = new WP_Post(['ID' => 7, 'post_name' => 'learn/motion']);
$GLOBALS['pages_by_id'] = [7 => 'learn/motion'];

$client = json_decode(omochix_learn_gallery_client_json(omochix_learn_gallery_get_items()), true);
$items  = [];
foreach ($client['items'] as $slug => $it) {
    $items[$slug] = ['params' => $it['params']];
}

$raws = [
    ['slots' => []],
    ['slots' => [['slug' => 'pop-in']]],
    ['slots' => [['slug' => 'pop-in', 'params' => ['cycle' => '1.5', 'color' => 'FF0000', 'overshoot' => 'big', 'badge_delay' => '0.33']], ['slug' => 'toast', 'params' => ['distance' => 999]], ['slug' => 'gradient-shift', 'params' => ['c1' => '00aaff', 'angle' => '45.5']]]],
    ['slots' => [['slug' => 'count-up', 'params' => ['target' => '512', 'easing' => 'linear'], 'target' => 'hero', 'intent' => '第一印象 テスト'], ['slug' => 'particles', 'params' => ['count' => '1000', 'size' => '0.1']]]],
    ['slots' => [['slug' => 'nope'], ['slug' => 'fade-up', 'params' => ['ease' => 'bounce'], 'target' => str_repeat('a', 41)], ['slug' => 'stagger'], ['slug' => 'typewriter'], ['slug' => 'toast']]],
    ['slots' => [['slug' => 'marker-highlight', 'params' => ['thick' => '70', 'cycle' => 'abc'], 'intent' => '<b>x</b>']]],
];
$hashes = ['', '1', '2;pop-in', '1;pop-in', '1;pop-in:cycle=1.5,color=ff0000,badge_delay=0.8,t=hero,i=%E7%AC%AC%E4%B8%80%E5%8D%B0%E8%B1%A1;toast;gradient-shift:angle=45', '1;pop-in;toast;fade-up;stagger', '1;pop-in:Bad=1,cycle=' . str_repeat('9', 100), '1;count-up:target=512,easing=linear,size=72;particles:count=1000', '1;toast:t=' . rawurlencode('<img onerror=1>') . ',i=hello'];

/** JSON-shape the composition like the JS does (params is always an object, never a list). */
function fixture_shape(array $comp) {
    foreach ($comp['slots'] as &$slot) {
        $slot['params'] = (object) $slot['params'];
    }
    return $comp;
}

$out = ['items' => $items, 'raw' => [], 'hash' => []];
foreach ($raws as $raw) {
    $comp = omochix_learn_composer_sanitize($raw);
    $out['raw'][] = ['in' => $raw, 'sanitized' => fixture_shape($comp), 'hash' => omochix_learn_composer_hash($comp)];
}
foreach ($hashes as $h) {
    $comp = omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash($h));
    $out['hash'][] = ['in' => $h, 'sanitized' => fixture_shape($comp), 'rehash' => omochix_learn_composer_hash($comp)];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
