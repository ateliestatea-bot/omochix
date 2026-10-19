<?php
/**
 * Prints, as JSON, what the PHP side produces for the adjustable samples
 * over a set of raw inputs: sanitized values, the :root / window.omxParams
 * blocks, the prompt lines, and the full copied code and prompt. The Node
 * side (params-parity.js) feeds the same inputs to assets/js/learn-params.js
 * and expects identical output.
 *
 * Run through: node tests/learn-gallery/params-parity.js
 */
require __DIR__ . '/stub.php';

$GLOBALS['pages']['learn/motion'] = new WP_Post(['ID' => 7, 'post_name' => 'learn/motion']);
$GLOBALS['pages_by_id'] = [7 => 'learn/motion'];

$client = json_decode(omochix_learn_gallery_client_json(omochix_learn_gallery_get_items()), true);
$out    = [];

foreach (omochix_learn_gallery_get_items() as $slug => $item) {
    if ([] === $item['params']) {
        continue;
    }
    $url = omochix_learn_gallery_url($item);

    // Raw input candidates per control; case k takes candidate k of every control.
    $candidates = [];
    foreach ($item['params'] as $def) {
        if ('range' === $def['type']) {
            $candidates[$def['key']] = [
                (string) ($def['min'] - 100), (string) ($def['max'] + 100), (string) (($def['min'] + $def['max']) / 2 + 0.123),
                'abc', '', (string) $def['default'], (string) ($def['min'] + $def['step'] * 1.4), '  1e1 ', '-0',
            ];
        } elseif ('color' === $def['type']) {
            $candidates[$def['key']] = ['ff0066', '#ABCDEF', 'red', '', '#12345', ' #a1b2c3 ', $def['default'], '#GGGGGG', '00ff00'];
        } else {
            $values                  = array_column($def['options'], 'value');
            $candidates[$def['key']] = [end($values), 'nope', '', $values[0], 'A', 'none', $def['default'], 'url(x)', $values[count($values) > 1 ? 1 : 0]];
        }
    }
    $cases = [[], ['unknown' => 'x']];
    for ($k = 0; $k < 9; $k++) {
        $raw = [];
        foreach ($candidates as $key => $list) {
            $raw[$key] = $list[$k];
        }
        $cases[] = $raw;
    }

    $templates = json_decode(omochix_learn_gallery_item_json($item, $url), true);
    $entry     = ['url' => $url, 'defs' => $client['items'][$slug]['params'], 'codeTemplate' => $templates['codeTemplate'], 'promptTemplate' => $templates['promptTemplate'], 'cases' => []];
    foreach ($cases as $raw) {
        $values = omochix_learn_gallery_sanitize_params($item, $raw);
        $blocks = omochix_learn_gallery_params_blocks($item, $values);
        $entry['cases'][] = [
            'raw'    => (object) $raw,
            'values' => (object) $values,
            'css'    => $blocks['css'],
            'js'     => $blocks['js'],
            'text'   => omochix_learn_gallery_params_text($item, $values),
            'code'   => omochix_learn_gallery_code_text($item, $url, $values),
            'prompt' => omochix_learn_gallery_prompt_text($item, $url, $values),
        ];
    }
    $out[$slug] = $entry;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
