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
    ['slots' => [['slug' => 'toast', 'target' => '(ヒーロー)!'], ['slug' => 'toast', 'params' => ['distance' => 80]], ['slug' => 'pop-in', 'intent' => "v2.0 hero, test/'x' & \"q\""], ['slug' => 'fade-up']]],
];
$hashes = ['', '1', '1;toast;toast;pop-in', '1;toast:t=%28%E3%83%92%E3%83%BC%E3%83%AD%E3%83%BC%29%21,i=v2.0%20hero%2C%20test%2F%27x%27', '2;pop-in', '1;pop-in', '1;pop-in:cycle=1.5,color=ff0000,badge_delay=0.8,t=hero,i=%E7%AC%AC%E4%B8%80%E5%8D%B0%E8%B1%A1;toast;gradient-shift:angle=45', '1;pop-in;toast;fade-up;stagger', '1;pop-in:Bad=1,cycle=' . str_repeat('9', 100), '1;count-up:target=512,easing=linear,size=72;particles:count=1000', '1;toast:t=' . rawurlencode('<img onerror=1>') . ',i=hello'];

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
// Combined preview: scoped blocks and whole documents for a few compositions.
$out['combined'] = [];
foreach ([$raws[1], $raws[2], $raws[3], $raws[5]] as $raw) {
    $comp  = omochix_learn_composer_sanitize($raw);
    $slots = [];
    $blocks = [];
    $n = 0;
    foreach ($comp['slots'] as $slot) {
        $n++;
        $item   = omochix_learn_gallery_get_item($slot['slug']);
        $values = omochix_learn_gallery_sanitize_params($item, $slot['params']);
        $slots[] = ['item' => $item, 'values' => $values];
        $blocks[] = ['scoped' => omochix_learn_composer_scoped_block($item, $values, $n), 'js' => omochix_learn_composer_js_params($item, $values)];
    }
    $out['combined'][] = ['slots' => fixture_shape($comp)['slots'], 'blocks' => $blocks, 'doc' => omochix_learn_composer_preview_document($slots)];
}
$out['catalog'] = ['stage' => $client['stage'], 'runtime' => $client['runtime'], 'items' => array_map(static fn($it) => ['params' => $it['params'], 'html' => $it['html'], 'css' => $it['css'], 'js' => $it['js']], $client['items'])];
// Brief: whole Markdown for a few compositions and both assistants.
$data = json_decode(omochix_learn_composer_data_json(), true);
$out['briefCtx'] = ['data' => $data['items'], 'shim' => $data['shim'], 'slotShim' => $data['slotShim'], 'templates' => omochix_learn_composer_brief_templates(), 'assistants' => omochix_learn_composer_assistants(), 'presets' => omochix_learn_composer_presets(), 'noParams' => '調整した値: なし'];
$out['brief'] = [];
foreach ([$raws[1], $raws[2], $raws[3], ['slots' => [['slug' => 'pop-in', 'target' => '-x', 'intent' => 'mood'], ['slug' => 'fade-up', 'params' => ['ease' => 'bounce'], 'target' => 'カスタム 対象', 'intent' => 'first']]], ['slots' => [['slug' => 'count-up', 'params' => ['target' => 500, 'duration' => 1.5]], ['slug' => 'particles', 'params' => ['count' => 80]]]]] as $raw) {
    foreach (['claude-code', 'codex'] as $assistant) {
        $out['brief'][] = ['in' => $raw, 'assistant' => $assistant, 'out' => omochix_learn_composer_brief($raw, $assistant)];
    }
}
// Brief code pieces: the token block and the data-* rewrite of JS params.
$out['tokenBlock'] = omochix_learn_composer_token_block(2);
$out['tokens'] = array_map(null, array_keys(omochix_learn_composer_tokens()), array_values(omochix_learn_composer_tokens()));
$out['tokenComment'] = omochix_learn_composer_token_comment();
$out['darkTokens'] = array_map(null, array_keys(omochix_learn_composer_dark_tokens()), array_values(omochix_learn_composer_dark_tokens()));
$out['briefJsParams'] = [];
foreach ([['count-up', ['target' => '500']], ['count-up', ['target' => 500, 'duration' => 1.5, 'easing' => 'linear']], ['count-up', ['size' => 72]], ['particles', ['count' => 80, 'speed' => 2]], ['pop-in', ['cycle' => 1.5]]] as [$slug, $params]) {
    $item = omochix_learn_gallery_get_item($slug);
    $out['briefJsParams'][] = ['slug' => $slug, 'params' => (object) $params, 'out' => omochix_learn_composer_brief_js_params($item, omochix_learn_gallery_sanitize_params($item, $params))];
}
$out['briefHtml'] = [];
foreach ([['count-up', ['target' => '500']], ['count-up', []], ['count-up', ['target' => 1000000, 'easing' => 'linear']], ['particles', ['count' => 80]], ['pop-in', ['cycle' => 1.5]]] as [$slug, $params]) {
    $item = omochix_learn_gallery_get_item($slug);
    $out['briefHtml'][] = ['slug' => $slug, 'params' => (object) $params, 'out' => omochix_learn_composer_brief_html($item, omochix_learn_gallery_sanitize_params($item, $params))];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
