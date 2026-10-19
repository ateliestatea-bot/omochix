<?php
/**
 * Learn v2 / Motion Composer: unit tests for inc/learn-composer.php using the
 * gallery's WordPress stub. Run: php tests/learn-composer/run.php
 */
require __DIR__ . '/../learn-gallery/stub.php';
require $THEME . '/inc/learn-composer.php';

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "PASS $name\n"; } else { $fail++; echo "FAIL $name\n"; } }

$GLOBALS['pages']['learn/motion'] = new WP_Post(['ID' => 7, 'post_name' => 'learn/motion']);
$GLOBALS['pages']['learn/motion/compose'] = new WP_Post(['ID' => 8, 'post_name' => 'learn/motion/compose']);
$GLOBALS['pages_by_id'] = [7 => 'learn/motion', 8 => 'learn/motion/compose'];

// ---------------------------------------------------------------- sanitize
$empty = omochix_learn_composer_sanitize(null);
check('sanitize: nothing in -> version and no slots', '1' === $empty['v'] && [] === $empty['slots']);
$raw = ['slots' => [
    ['slug' => 'pop-in', 'params' => ['cycle' => '1.5', 'color' => 'ff0000', 'overshoot' => 'soft', 'badge_delay' => '99'], 'target' => 'hero', 'intent' => '第一印象'],
    ['slug' => 'Toast', 'params' => []],
    ['slug' => 'nope'],
    'garbage',
    ['slug' => 'gradient-shift', 'params' => ['angle' => 45], 'target' => str_repeat('あ', 41), 'intent' => '<script>'],
    ['slug' => 'fade-up'],
]];
$comp = omochix_learn_composer_sanitize($raw);
check('sanitize: keeps at most 3 valid slots in order, drops unknown slugs and garbage', ['pop-in', 'toast', 'gradient-shift'] === array_column($comp['slots'], 'slug'));
check('sanitize: params are sanitized and only non-default values survive', ['cycle' => 1.5, 'color' => '#ff0000', 'badge_delay' => 0.8] === $comp['slots'][0]['params'] && [] === $comp['slots'][1]['params'] && ['angle' => 45.0] === $comp['slots'][2]['params']);
check('sanitize: notes keep short plain text, drop over-long and markup', 'hero' === $comp['slots'][0]['target'] && '第一印象' === $comp['slots'][0]['intent'] && '' === $comp['slots'][2]['target'] && '' === $comp['slots'][2]['intent']);
check('sanitize: duplicate slug is allowed by the sanitizer (the UI prevents it)', 2 === count(omochix_learn_composer_sanitize(['slots' => [['slug' => 'toast'], ['slug' => 'toast']]])['slots']));

// --------------------------------------------------------------------- hash
$hash = omochix_learn_composer_hash($comp);
check('hash: version first, one segment per slot, non-default params only, colours without #, notes encoded', '1;pop-in:cycle=1.5,color=ff0000,badge_delay=0.8,t=hero,i=' . rawurlencode('第一印象') . ';toast;gradient-shift:angle=45' === $hash);
check('hash: empty composition -> empty string', '' === omochix_learn_composer_hash(['slots' => []]));
$back = omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash($hash));
check('hash: round trip reproduces the composition', $back === $comp);
check('hash: wrong version or junk -> empty', [] === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('2;pop-in'))['slots'] && [] === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash(''))['slots'] && [] === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;;;'))['slots']);
check('hash: more than 3 segments are cut', 3 === count(omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;pop-in;toast;fade-up;stagger'))['slots']));
check('hash: unknown or malformed keys are ignored', [] === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;pop-in:Bad=1,__x=2,=3'))['slots'][0]['params']);
$capped = omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;pop-in:cycle=' . str_repeat('9', 100)));
check('hash: an over-long numeric value is clamped to the range max', 4.0 === $capped['slots'][0]['params']['cycle']);
check('hash: injection in notes is dropped', '' === omochix_learn_composer_sanitize(omochix_learn_composer_parse_hash('1;pop-in:t=' . rawurlencode('<img onerror=1>')))['slots'][0]['target']);

// ----------------------------------------------------------------- combined
$popIn   = omochix_learn_gallery_get_item('pop-in');
$countUp = omochix_learn_gallery_get_item('count-up');
check('combined: scoped block carries only non-default css params under the slot selector', '.omx-slot[data-slot="2"]{--p-cycle:1.5s;--p-color:#ff0000}' === omochix_learn_composer_scoped_block($popIn, omochix_learn_gallery_sanitize_params($popIn, ['cycle' => 1.5, 'color' => '#ff0000']), 2) && '' === omochix_learn_composer_scoped_block($popIn, omochix_learn_gallery_sanitize_params($popIn), 1));
check('combined: js params are a JSON object of non-default js values', '{"target":500}' === omochix_learn_composer_js_params($countUp, omochix_learn_gallery_sanitize_params($countUp, ['target' => 500])) && '{}' === omochix_learn_composer_js_params($countUp, omochix_learn_gallery_sanitize_params($countUp)));
$doc = omochix_learn_composer_preview_document([['item' => $popIn, 'values' => ['cycle' => 1.5]], ['item' => $countUp, 'values' => ['target' => 500, 'size' => 72]]]);
check('combined: document stacks the slots in order with scoped params', strpos($doc, 'data-slot="1"') < strpos($doc, 'data-slot="2"') && str_contains($doc, '<style id="omx-params">.omx-slot[data-slot="1"]{--p-cycle:1.5s}.omx-slot[data-slot="2"]{--p-size:72px}</style>') && str_contains($doc, $popIn['preview']['html']) && str_contains($doc, $countUp['preview']['html']));
check('combined: js samples are wrapped with a per-slot omx and their params', str_contains($doc, 'omxSlot(2,{"target":500})') && str_contains($doc, '(function(omx){' . $countUp['preview']['js'] . '}(omxSlot(2,') && str_contains($doc, 'function omxSlot(n,p)') && 1 === substr_count($doc, 'omx:ready'));
check('combined: no :root block; exactly four scripts (runtime, shim, one js sample, fit) close cleanly', !str_contains($doc, '<style id="omx-params">:root') && 4 === substr_count($doc, '</script>') && 4 === substr_count($doc, '<script>'));
check('combined: fit script present, runtime once, stage size from the catalog', str_contains($doc, 'data-stage-w="320"') && 1 === substr_count($doc, '.omx-stage{') && str_contains($doc, 'querySelectorAll(".omx-slot")'));

// ------------------------------------------------------------------ presets
$presets = omochix_learn_composer_presets();
check('presets: target and intent lists exist with plain keys', count($presets['target']) >= 6 && count($presets['intent']) >= 5 && !array_filter(array_merge(array_keys($presets['target']), array_keys($presets['intent'])), static fn($k) => !preg_match('/^[a-z]+$/', $k)));

// ------------------------------------------------------------------- routing
check('routing: composer url resolves through the published page', 'https://example.test/learn/motion/compose/' === omochix_learn_composer_url());
check('routing: constants', 'learn/motion/compose' === OMOCHIX_LEARN_COMPOSER_PATH && 3 === OMOCHIX_LEARN_COMPOSER_MAX_SLOTS && 'c' === OMOCHIX_LEARN_COMPOSER_HASH_KEY);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
