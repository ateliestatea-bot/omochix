<?php
/**
 * Learn v2: Motion Gallery ("/learn/motion/" and "/learn/motion/{slug}/").
 *
 * The gallery is a normal WordPress page (path "learn/motion") whose content
 * comes from the theme-shipped catalog in assets/data/motion/catalog.json
 * (schema: catalog.schema.json). Samples are not posts: no post type, no
 * database rows, and omochix-core is untouched. A sample's detail URL is a
 * rewrite rule that resolves to that same page with the `omx_motion` query
 * var set, so the page template renders either the list or one sample.
 *
 * Everything here is additive. Existing Learn pages, the Prompt Library and
 * the shared shell keep their templates and URLs; the only hooks into them
 * are the one-line allow-list entry in inc/shell.php and the require in
 * functions.php. See docs/Learn-v2-Spec.md.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

const OMOCHIX_LEARN_GALLERY_PATH            = 'learn/motion';
const OMOCHIX_LEARN_GALLERY_QUERY_VAR       = 'omx_motion';
const OMOCHIX_LEARN_GALLERY_PARTIAL_VAR     = 'omx_partial';
const OMOCHIX_LEARN_GALLERY_REWRITE_VERSION = '1';
const OMOCHIX_LEARN_GALLERY_CATALOG_VERSION = 1;

/* -------------------------------------------------------------------------
 * Vocabularies
 * ---------------------------------------------------------------------- */

/** Difficulty levels, same values as Learn articles and Prompt Library. */
function omochix_learn_gallery_levels() {
    return [
        'beginner'     => __('初級', 'omochix'),
        'intermediate' => __('中級', 'omochix'),
        'advanced'     => __('上級', 'omochix'),
    ];
}

/** Implementation technologies a sample may use. */
function omochix_learn_gallery_techs() {
    return [
        'css' => 'CSS',
        'js'  => 'JavaScript',
    ];
}

/**
 * @param string $level Stored level value.
 * @return string
 */
function omochix_learn_gallery_level_label($level) {
    $levels = omochix_learn_gallery_levels();
    return $levels[$level] ?? $levels['beginner'];
}

/* -------------------------------------------------------------------------
 * Catalog: load, validate, normalize
 * ---------------------------------------------------------------------- */

function omochix_learn_gallery_catalog_file() {
    return get_theme_file_path('/assets/data/motion/catalog.json');
}

/** Lower-case slug: letters, digits, single hyphens. */
function omochix_learn_gallery_is_slug($value) {
    return is_string($value) && '' !== $value && strlen($value) <= 60 && 1 === preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
}

/**
 * Validate a decoded catalog against the rules in catalog.schema.json.
 *
 * Only the rules that matter at render time are enforced (required keys,
 * types, enums, uniqueness, cross references, and that preview fields can be
 * inlined into a srcdoc document). Returns a list of human-readable errors;
 * an empty list means the catalog is usable.
 *
 * @param mixed $data Decoded JSON.
 * @return string[]
 */
function omochix_learn_gallery_validate_catalog($data) {
    $errors = [];
    if (!is_array($data)) {
        return ['catalog: root must be an object'];
    }
    if (($data['version'] ?? null) !== OMOCHIX_LEARN_GALLERY_CATALOG_VERSION) {
        $errors[] = 'catalog: unsupported version';
    }
    if (!isset($data['updated']) || !is_string($data['updated']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['updated'])) {
        $errors[] = 'catalog: updated must be YYYY-MM-DD';
    }
    $stage = $data['stage'] ?? null;
    if (!is_array($stage) || !is_int($stage['width'] ?? null) || !is_int($stage['height'] ?? null)
        || $stage['width'] < 120 || $stage['width'] > 1920 || $stage['height'] < 80 || $stage['height'] > 1080) {
        $errors[] = 'catalog: stage must have integer width (120-1920) and height (80-1080)';
    }

    $category_slugs = [];
    if (!isset($data['categories']) || !is_array($data['categories']) || !$data['categories']) {
        $errors[] = 'catalog: categories must be a non-empty array';
    } else {
        foreach ($data['categories'] as $index => $category) {
            $where = "categories[$index]";
            if (!is_array($category)) {
                $errors[] = "$where: must be an object";
                continue;
            }
            if (!omochix_learn_gallery_is_slug($category['slug'] ?? null)) {
                $errors[] = "$where: invalid slug";
            } elseif (isset($category_slugs[$category['slug']])) {
                $errors[] = "$where: duplicate slug {$category['slug']}";
            } else {
                $category_slugs[$category['slug']] = true;
            }
            if (!is_string($category['name'] ?? null) || '' === $category['name'] || mb_strlen($category['name']) > 40) {
                $errors[] = "$where: name must be 1-40 characters";
            }
            if (!is_string($category['description'] ?? null) || mb_strlen($category['description']) > 200) {
                $errors[] = "$where: description must be a string up to 200 characters";
            }
            if (!is_int($category['order'] ?? null) || $category['order'] < 1) {
                $errors[] = "$where: order must be a positive integer";
            }
        }
    }

    $levels = array_keys(omochix_learn_gallery_levels());
    $techs  = array_keys(omochix_learn_gallery_techs());
    $ids    = [];
    $slugs  = [];
    if (!isset($data['items']) || !is_array($data['items']) || !$data['items']) {
        $errors[] = 'catalog: items must be a non-empty array';
        return $errors;
    }
    foreach ($data['items'] as $index => $item) {
        $where = "items[$index]";
        if (!is_array($item)) {
            $errors[] = "$where: must be an object";
            continue;
        }
        if (!is_string($item['id'] ?? null) || !preg_match('/^\d{3}$/', $item['id'])) {
            $errors[] = "$where: id must be three digits";
        } elseif (isset($ids[$item['id']])) {
            $errors[] = "$where: duplicate id {$item['id']}";
        } else {
            $ids[$item['id']] = true;
        }
        if (!omochix_learn_gallery_is_slug($item['slug'] ?? null)) {
            $errors[] = "$where: invalid slug";
        } elseif (isset($slugs[$item['slug']])) {
            $errors[] = "$where: duplicate slug {$item['slug']}";
        } else {
            $slugs[$item['slug']] = true;
        }
        if (!isset($category_slugs[$item['category'] ?? ''])) {
            $errors[] = "$where: unknown category";
        }
        $name = $item['name'] ?? null;
        if (!is_array($name) || !is_string($name['ja'] ?? null) || '' === $name['ja'] || !is_string($name['en'] ?? null) || '' === $name['en']) {
            $errors[] = "$where: name.ja and name.en are required";
        }
        foreach (['summary' => 80, 'description' => 300] as $key => $max) {
            if (!is_string($item[$key] ?? null) || '' === $item[$key] || mb_strlen($item[$key]) > $max) {
                $errors[] = "$where: $key must be 1-$max characters";
            }
        }
        if (!in_array($item['level'] ?? null, $levels, true)) {
            $errors[] = "$where: invalid level";
        }
        $tech = $item['tech'] ?? null;
        if (!is_array($tech) || !$tech || array_diff($tech, $techs) || count($tech) !== count(array_unique($tech))) {
            $errors[] = "$where: tech must be a unique list of " . implode('|', $techs);
        }
        $tags = $item['tags'] ?? [];
        if (!is_array($tags) || count($tags) > 12) {
            $errors[] = "$where: tags must be an array of up to 12 strings";
        } else {
            foreach ($tags as $tag) {
                if (!is_string($tag) || '' === $tag || mb_strlen($tag) > 30) {
                    $errors[] = "$where: every tag must be 1-30 characters";
                    break;
                }
            }
        }
        if (isset($item['featured']) && !is_bool($item['featured'])) {
            $errors[] = "$where: featured must be boolean";
        }
        $preview = $item['preview'] ?? null;
        if (!is_array($preview) || !is_string($preview['html'] ?? null) || '' === $preview['html']) {
            $errors[] = "$where: preview.html is required";
        } else {
            foreach (['html' => 8000, 'css' => 12000, 'js' => 12000] as $key => $max) {
                $value = $preview[$key] ?? '';
                if (!is_string($value) || strlen($value) > $max) {
                    $errors[] = "$where: preview.$key must be a string up to $max bytes";
                } elseif (preg_match('#</\s*(script|style)#i', $value)) {
                    // The preview is inlined into a srcdoc document; a closing
                    // tag inside it would end the wrapper element early.
                    $errors[] = "$where: preview.$key must not contain a closing script/style tag";
                }
            }
        }
        $prompt = $item['prompt'] ?? null;
        if (!is_array($prompt) || !is_string($prompt['ask'] ?? null) || '' === $prompt['ask']
            || !is_array($prompt['spec'] ?? null) || !$prompt['spec'] || count($prompt['spec']) > 10
            || !is_string($prompt['use'] ?? null) || '' === $prompt['use']) {
            $errors[] = "$where: prompt.ask, prompt.spec (1-10) and prompt.use are required";
        } else {
            foreach ($prompt['spec'] as $line) {
                if (!is_string($line) || '' === $line || mb_strlen($line) > 300) {
                    $errors[] = "$where: every prompt.spec line must be 1-300 characters";
                    break;
                }
            }
        }
        $related = $item['related'] ?? [];
        if (!is_array($related)) {
            $errors[] = "$where: related must be an object";
        } else {
            foreach (['prompts', 'tools'] as $key) {
                foreach ((array) ($related[$key] ?? []) as $slug) {
                    if (!omochix_learn_gallery_is_slug($slug)) {
                        $errors[] = "$where: related.$key contains an invalid slug";
                        break;
                    }
                }
            }
            foreach ((array) ($related['learn'] ?? []) as $path) {
                if (!is_string($path) || !preg_match('#^learn(?:/[a-z0-9-]+)+$#', $path)) {
                    $errors[] = "$where: related.learn contains an invalid page path";
                    break;
                }
            }
        }
        if (array_key_exists('playground', $item) && null !== $item['playground']) {
            if (!is_array($item['playground'])) {
                $errors[] = "$where: playground must be an object or null";
            } else {
                foreach (omochix_learn_gallery_validate_params($item['playground']['params'] ?? [], is_array($item['preview'] ?? null) ? $item['preview'] : []) as $error) {
                    $errors[] = "$where: $error";
                }
            }
        }
        if (array_key_exists('video', $item) && null !== $item['video'] && !is_array($item['video'])) {
            $errors[] = "$where: video must be an object or null";
        }
    }

    return $errors;
}

/* -------------------------------------------------------------------------
 * Playground parameters (Phase 2): definitions, values, generated blocks
 *
 * A sample may declare editable parameters. Each parameter is typed
 * (range / color / select), has a default equal to the sample's shipped
 * look, and is bound either to a CSS custom property (--p-*) that the
 * sample's CSS reads with a fallback, or to `window.omxParams` for JS
 * samples. Values only ever enter CSS/JS after
 * omochix_learn_gallery_sanitize_params() (mirrored in learn-gallery.js and
 * the preview runtime), and are emitted through fixed formats (number+unit,
 * #rrggbb, catalog-declared CSS for selects), never as free text.
 * ---------------------------------------------------------------------- */

const OMOCHIX_LEARN_GALLERY_MAX_PARAMS       = 8;
const OMOCHIX_LEARN_GALLERY_PARAM_KEY_RE     = '/^[a-z][a-z0-9_]{0,23}$/';
const OMOCHIX_LEARN_GALLERY_PARAM_CSS_VAR_RE = '/^--p-[a-z0-9-]{1,30}$/';
const OMOCHIX_LEARN_GALLERY_PARAM_CSS_VAL_RE = '/^[a-z0-9 .,()%#-]{1,80}$/i';
const OMOCHIX_LEARN_GALLERY_PARAM_UNIT_RE    = '/^(|ms|s|px|deg|%|em|rem|vw|vh)$/';
const OMOCHIX_LEARN_GALLERY_COLOR_RE         = '/^#[0-9a-f]{6}$/i';

/** Parameter types the playground renders (free text is intentionally absent). */
function omochix_learn_gallery_param_types() {
    return ['range', 'color', 'select'];
}

/**
 * Validate a sample's playground.params list (mirrors catalog.schema.json).
 *
 * @param mixed                $params  Raw list from the catalog.
 * @param array<string, mixed> $preview Sample preview (to check css_var usage).
 * @return string[] errors
 */
function omochix_learn_gallery_validate_params($params, array $preview) {
    $errors = [];
    if (!is_array($params)) {
        return ['playground.params must be an array'];
    }
    if (count($params) > OMOCHIX_LEARN_GALLERY_MAX_PARAMS) {
        $errors[] = 'playground.params: at most ' . OMOCHIX_LEARN_GALLERY_MAX_PARAMS . ' parameters';
    }
    $keys = [];
    $vars = [];
    foreach ($params as $index => $p) {
        $w = "playground.params[$index]";
        if (!is_array($p)) {
            $errors[] = "$w: must be an object";
            continue;
        }
        $key = $p['key'] ?? null;
        if (!is_string($key) || !preg_match(OMOCHIX_LEARN_GALLERY_PARAM_KEY_RE, $key)) {
            $errors[] = "$w: invalid key";
        } elseif (isset($keys[$key])) {
            $errors[] = "$w: duplicate key $key";
        } else {
            $keys[$key] = true;
        }
        if (!is_string($p['label'] ?? null) || '' === $p['label'] || mb_strlen($p['label']) > 30) {
            $errors[] = "$w: label must be 1-30 characters";
        }
        $type = $p['type'] ?? null;
        if (!in_array($type, omochix_learn_gallery_param_types(), true)) {
            $errors[] = "$w: type must be one of " . implode('|', omochix_learn_gallery_param_types());
            continue;
        }
        $target = $p['target'] ?? 'css';
        if (!in_array($target, ['css', 'js'], true)) {
            $errors[] = "$w: target must be css or js";
        }
        if ('css' === $target) {
            $var = $p['css_var'] ?? null;
            if (!is_string($var) || !preg_match(OMOCHIX_LEARN_GALLERY_PARAM_CSS_VAR_RE, $var)) {
                $errors[] = "$w: css_var must match --p-[a-z0-9-]";
            } elseif (isset($vars[$var])) {
                $errors[] = "$w: duplicate css_var $var";
            } else {
                $vars[$var] = true;
                if (false === strpos((string) ($preview['css'] ?? ''), 'var(' . $var)) {
                    $errors[] = "$w: preview.css never reads var($var)";
                }
            }
        } elseif (array_key_exists('css_var', $p)) {
            $errors[] = "$w: js parameters must not declare css_var";
        }
        if (array_key_exists('unit', $p) && (!is_string($p['unit']) || !preg_match(OMOCHIX_LEARN_GALLERY_PARAM_UNIT_RE, $p['unit']))) {
            $errors[] = "$w: unsupported unit";
        }
        if (array_key_exists('prompt', $p) && (!is_string($p['prompt']) || mb_strlen($p['prompt']) > 120 || false === strpos($p['prompt'], '{value}'))) {
            $errors[] = "$w: prompt must be a string up to 120 characters containing {value}";
        }
        switch ($type) {
            case 'range':
                $numeric = true;
                foreach (['min', 'max', 'step', 'default'] as $k) {
                    if (!isset($p[$k]) || !is_numeric($p[$k]) || !is_finite((float) $p[$k])) {
                        $errors[] = "$w: range needs numeric $k";
                        $numeric  = false;
                    }
                }
                if (!$numeric) {
                    break;
                }
                if ((float) $p['min'] >= (float) $p['max']) {
                    $errors[] = "$w: min must be below max";
                }
                if ((float) $p['step'] <= 0) {
                    $errors[] = "$w: step must be positive";
                }
                if ((float) $p['default'] < (float) $p['min'] || (float) $p['default'] > (float) $p['max']) {
                    $errors[] = "$w: default must lie within min..max";
                }
                break;
            case 'color':
                if (!is_string($p['default'] ?? null) || !preg_match(OMOCHIX_LEARN_GALLERY_COLOR_RE, $p['default'])) {
                    $errors[] = "$w: color default must be #rrggbb";
                }
                if (array_key_exists('unit', $p)) {
                    $errors[] = "$w: color parameters take no unit";
                }
                break;
            case 'select':
                $options = $p['options'] ?? null;
                if (!is_array($options) || count($options) < 2 || count($options) > 8) {
                    $errors[] = "$w: select needs 2-8 options";
                    break;
                }
                $values = [];
                foreach ($options as $oi => $o) {
                    if (!is_array($o) || !is_string($o['value'] ?? null) || !preg_match('/^[a-z0-9_-]{1,24}$/', $o['value'])) {
                        $errors[] = "$w: options[$oi] needs a slug-like value";
                        continue;
                    }
                    if (isset($values[$o['value']])) {
                        $errors[] = "$w: duplicate option value {$o['value']}";
                    }
                    $values[$o['value']] = true;
                    if (!is_string($o['label'] ?? null) || '' === $o['label'] || mb_strlen($o['label']) > 30) {
                        $errors[] = "$w: options[$oi] label must be 1-30 characters";
                    }
                    if ('css' === $target && (!is_string($o['css'] ?? null) || !preg_match(OMOCHIX_LEARN_GALLERY_PARAM_CSS_VAL_RE, $o['css']) || preg_match('/url\s*\(/i', $o['css']))) {
                        // Plain values only: numbers, keywords, timing functions. No url(), quotes, slashes or braces.
                        $errors[] = "$w: options[$oi] css must be a plain CSS value";
                    }
                }
                if (!isset($values[$p['default'] ?? ''])) {
                    $errors[] = "$w: default must be one of the option values";
                }
                break;
        }
    }
    return $errors;
}

/**
 * Normalized parameter definitions of an item (empty when none).
 *
 * @param mixed $playground Raw playground block (already validated).
 * @return array<int, array<string, mixed>>
 */
function omochix_learn_gallery_normalize_params($playground) {
    $out = [];
    foreach ((array) ($playground['params'] ?? []) as $p) {
        $def = [
            'key'     => $p['key'],
            'label'   => $p['label'],
            'type'    => $p['type'],
            'target'  => $p['target'] ?? 'css',
            'default' => $p['default'],
            'unit'    => (string) ($p['unit'] ?? ''),
            'css_var' => (string) ($p['css_var'] ?? ''),
            'prompt'  => (string) ($p['prompt'] ?? ''),
        ];
        if ('range' === $p['type']) {
            $def['min']     = (float) $p['min'];
            $def['max']     = (float) $p['max'];
            $def['step']    = (float) $p['step'];
            $def['default'] = (float) $p['default'];
        } elseif ('color' === $p['type']) {
            $def['default'] = strtolower($p['default']);
        } else {
            $def['options'] = array_map(static function ($o) {
                return ['value' => $o['value'], 'label' => $o['label'], 'css' => (string) ($o['css'] ?? '')];
            }, $p['options']);
        }
        $out[] = $def;
    }
    return $out;
}

/**
 * Format a number the way the browser does for the generated CSS/JS, so PHP
 * and JS output are byte-identical: up to 4 decimals, trailing zeros removed.
 *
 * @param float $n Number.
 * @return string
 */
function omochix_learn_gallery_format_number($n) {
    $s = number_format((float) $n, 4, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return '' === $s || '-0' === $s ? '0' : $s;
}

/**
 * Coerce raw values (from the URL hash, a form, or defaults) into valid
 * parameter values for an item. Unknown keys are dropped; missing or invalid
 * values fall back to the default. Mirrored by sanitizeParams() in
 * learn-gallery.js.
 *
 * @param array<string, mixed> $item Catalog item.
 * @param array<string, mixed> $raw  Raw values keyed by parameter key.
 * @return array<string, float|string>
 */
function omochix_learn_gallery_sanitize_params(array $item, array $raw = []) {
    $values = [];
    foreach ($item['params'] as $def) {
        $v = $raw[$def['key']] ?? null;
        switch ($def['type']) {
            case 'range':
                if (!is_numeric($v) || !is_finite((float) $v)) {
                    $v = $def['default'];
                }
                $v = max($def['min'], min($def['max'], (float) $v));
                $v = $def['min'] + round(($v - $def['min']) / $def['step']) * $def['step'];
                $v = max($def['min'], min($def['max'], $v));
                $values[$def['key']] = (float) omochix_learn_gallery_format_number($v);
                break;
            case 'color':
                $v = is_string($v) ? strtolower(trim($v)) : '';
                if (preg_match('/^[0-9a-f]{6}$/', $v)) {
                    $v = '#' . $v;
                }
                $values[$def['key']] = preg_match(OMOCHIX_LEARN_GALLERY_COLOR_RE, $v) ? $v : $def['default'];
                break;
            default:
                $allowed = array_column($def['options'], 'value');
                $values[$def['key']] = is_string($v) && in_array($v, $allowed, true) ? $v : $def['default'];
        }
    }
    return $values;
}

/** True when a (sanitized) value equals the definition's default. */
function omochix_learn_gallery_param_is_default(array $def, $v) {
    return 'range' === $def['type'] ? (float) $v === (float) $def['default'] : $v === $def['default'];
}

/**
 * The CSS and JS blocks that carry parameter values into a preview document
 * and into the copied code. Deterministic formatting shared with blocks()
 * in learn-params.js:
 *   css: ":root{--p-a:400ms;--p-b:#6e4bff}"      (or "" when no CSS params)
 *   js:  "window.omxParams={\"target\":12800};"  (or "" when no JS params)
 * Values equal to the default are left out: the sample's own CSS carries
 * the default as a var() fallback (and JS samples as the omx.param()
 * fallback), so an untouched sample renders exactly as shipped, theme
 * variables included, and the copied code only lists what was changed.
 *
 * @param array<string, mixed>        $item   Catalog item.
 * @param array<string, float|string> $values Sanitized values.
 * @return array{css: string, js: string}
 */
function omochix_learn_gallery_params_blocks(array $item, array $values) {
    $css = [];
    $js  = [];
    foreach ($item['params'] as $def) {
        $v = $values[$def['key']] ?? $def['default'];
        if (omochix_learn_gallery_param_is_default($def, $v)) {
            continue;
        }
        if ('select' === $def['type']) {
            $cssValue = '';
            foreach ($def['options'] as $o) {
                if ($o['value'] === $v) {
                    $cssValue = $o['css'];
                }
            }
            $jsValue = $v;
        } elseif ('color' === $def['type']) {
            $cssValue = $v;
            $jsValue  = $v;
        } else {
            $cssValue = omochix_learn_gallery_format_number($v) . $def['unit'];
            $jsValue  = (float) omochix_learn_gallery_format_number($v);
        }
        if ('js' === $def['target']) {
            $js[$def['key']] = $jsValue;
        } elseif ('' !== $cssValue) {
            $css[] = $def['css_var'] . ':' . $cssValue;
        }
    }
    return [
        'css' => $css ? ':root{' . implode(';', $css) . '}' : '',
        'js'  => $js ? 'window.omxParams=' . wp_json_encode($js, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' : '',
    ];
}

/**
 * Display form of a value: "400ms", "#6e4bff", or an option label.
 *
 * @param array<string, mixed> $def Parameter definition.
 * @param float|string         $v   Sanitized value.
 * @return string
 */
function omochix_learn_gallery_param_display(array $def, $v) {
    if ('range' === $def['type']) {
        return omochix_learn_gallery_format_number($v) . $def['unit'];
    }
    if ('select' === $def['type']) {
        foreach ($def['options'] as $o) {
            if ($o['value'] === $v) {
                return $o['label'];
            }
        }
    }
    return (string) $v;
}

/**
 * Human-readable list of the current parameter values for the prompt text
 * (empty when the item has no parameters). Mirrored by paramsText() in
 * learn-gallery.js.
 *
 * @param array<string, mixed>        $item   Catalog item.
 * @param array<string, float|string> $values Sanitized values.
 * @return string
 */
function omochix_learn_gallery_params_text(array $item, array $values) {
    if (!$item['params']) {
        return '';
    }
    $lines = ['調整した値:'];
    foreach ($item['params'] as $def) {
        $v       = $values[$def['key']] ?? $def['default'];
        $display = omochix_learn_gallery_param_display($def, $v);
        $line    = '' !== $def['prompt'] ? str_replace('{value}', $display, $def['prompt']) : $def['label'] . ': ' . $display;
        $same    = omochix_learn_gallery_param_is_default($def, $v);
        $lines[] = '- ' . $line . ($same ? '（既定）' : '');
    }
    return implode("\n", $lines) . "\n";
}

/**
 * Fill defaults, order categories/items and build lookups.
 *
 * @param array<string, mixed> $data Validated catalog.
 * @return array<string, mixed>
 */
function omochix_learn_gallery_normalize_catalog(array $data) {
    $categories = [];
    foreach ($data['categories'] as $category) {
        $categories[$category['slug']] = [
            'slug'        => $category['slug'],
            'name'        => $category['name'],
            'description' => $category['description'],
            'order'       => (int) $category['order'],
            'count'       => 0,
        ];
    }
    uasort($categories, static function ($a, $b) {
        return [$a['order'], $a['slug']] <=> [$b['order'], $b['slug']];
    });

    $items = [];
    foreach ($data['items'] as $item) {
        $preview = $item['preview'];
        $related = $item['related'] ?? [];
        $items[$item['slug']] = [
            'id'          => $item['id'],
            'slug'        => $item['slug'],
            'category'    => $item['category'],
            'name'        => ['ja' => $item['name']['ja'], 'en' => $item['name']['en']],
            'summary'     => $item['summary'],
            'description' => $item['description'],
            'level'       => $item['level'],
            'tech'        => array_values($item['tech']),
            'tags'        => array_values($item['tags'] ?? []),
            'featured'    => !empty($item['featured']),
            'preview'     => [
                'html' => $preview['html'],
                'css'  => (string) ($preview['css'] ?? ''),
                'js'   => (string) ($preview['js'] ?? ''),
            ],
            'prompt'      => [
                'ask'  => $item['prompt']['ask'],
                'spec' => array_values($item['prompt']['spec']),
                'use'  => $item['prompt']['use'],
            ],
            'related'     => [
                'prompts' => array_values((array) ($related['prompts'] ?? [])),
                'tools'   => array_values((array) ($related['tools'] ?? [])),
                'learn'   => array_values((array) ($related['learn'] ?? [])),
            ],
            'params'      => omochix_learn_gallery_normalize_params($item['playground'] ?? null),
            'playground'  => $item['playground'] ?? null,
            'video'       => $item['video'] ?? null,
        ];
        $categories[$item['category']]['count']++;
    }
    uasort($items, static function ($a, $b) {
        return strcmp($a['id'], $b['id']);
    });

    return [
        'version'    => (int) $data['version'],
        'updated'    => $data['updated'],
        'stage'      => ['width' => (int) $data['stage']['width'], 'height' => (int) $data['stage']['height']],
        'categories' => $categories,
        'items'      => $items,
    ];
}

/** The structure returned when no catalog is available. */
function omochix_learn_gallery_empty_catalog() {
    return ['version' => 0, 'updated' => '', 'stage' => ['width' => 320, 'height' => 180], 'categories' => [], 'items' => []];
}

/**
 * Read and validate a catalog file.
 *
 * @param string $file Absolute path.
 * @return array{ok: bool, errors: string[], catalog: array<string, mixed>}
 */
function omochix_learn_gallery_load_catalog_from_file($file) {
    if (!is_readable($file)) {
        return ['ok' => false, 'errors' => ['catalog: file is missing or unreadable'], 'catalog' => omochix_learn_gallery_empty_catalog()];
    }
    $json = file_get_contents($file);
    $data = json_decode((string) $json, true);
    if (null === $data && JSON_ERROR_NONE !== json_last_error()) {
        return ['ok' => false, 'errors' => ['catalog: invalid JSON (' . json_last_error_msg() . ')'], 'catalog' => omochix_learn_gallery_empty_catalog()];
    }
    $errors = omochix_learn_gallery_validate_catalog($data);
    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'catalog' => omochix_learn_gallery_empty_catalog()];
    }
    return ['ok' => true, 'errors' => [], 'catalog' => omochix_learn_gallery_normalize_catalog($data)];
}

/**
 * The catalog, loaded once per request. An invalid catalog renders as an
 * empty gallery (no fatal error on the public site); the reasons are logged
 * when WP_DEBUG is on.
 *
 * @return array<string, mixed>
 */
function omochix_learn_gallery_get_catalog() {
    static $catalog = null;
    if (null !== $catalog) {
        return $catalog;
    }
    $result  = omochix_learn_gallery_load_catalog_from_file(omochix_learn_gallery_catalog_file());
    $catalog = $result['catalog'];
    if (!$result['ok'] && defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[omochix learn-gallery] ' . implode('; ', $result['errors']));
    }
    return $catalog;
}

/* -------------------------------------------------------------------------
 * Catalog queries
 * ---------------------------------------------------------------------- */

/** @return array<string, array<string, mixed>> keyed by slug, in display order */
function omochix_learn_gallery_get_categories() {
    return omochix_learn_gallery_get_catalog()['categories'];
}

/**
 * @param string $slug Category slug.
 * @return array<string, mixed>|null
 */
function omochix_learn_gallery_get_category($slug) {
    return omochix_learn_gallery_get_catalog()['categories'][$slug] ?? null;
}

/**
 * @param string $slug Item slug.
 * @return array<string, mixed>|null
 */
function omochix_learn_gallery_get_item($slug) {
    return omochix_learn_gallery_get_catalog()['items'][$slug] ?? null;
}

/** @return array{width: int, height: int} */
function omochix_learn_gallery_get_stage() {
    return omochix_learn_gallery_get_catalog()['stage'];
}

/**
 * Whether an item matches a filter set (cat / level / tech / q).
 *
 * The same rule is applied client-side by learn-gallery.js through the
 * card's data-search attribute, which omochix_learn_gallery_search_text()
 * builds from the same fields, so JS and PHP agree on what matches.
 *
 * @param array<string, mixed> $item    Catalog item.
 * @param array<string, string> $filters Sanitized filters.
 * @return bool
 */
function omochix_learn_gallery_item_matches(array $item, array $filters) {
    if (!empty($filters['cat']) && $item['category'] !== $filters['cat']) {
        return false;
    }
    if (!empty($filters['level']) && $item['level'] !== $filters['level']) {
        return false;
    }
    if (!empty($filters['tech']) && !in_array($filters['tech'], $item['tech'], true)) {
        return false;
    }
    if (isset($filters['q']) && '' !== $filters['q']) {
        $haystack = omochix_learn_gallery_search_text($item);
        foreach (preg_split('/\s+/u', mb_strtolower($filters['q']), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            if (false === mb_strpos($haystack, $term)) {
                return false;
            }
        }
    }
    return true;
}

/**
 * Lower-cased text a keyword search runs over.
 *
 * @param array<string, mixed> $item Catalog item.
 * @return string
 */
function omochix_learn_gallery_search_text(array $item) {
    $parts = array_merge(
        ['#' . $item['id'], $item['id'], $item['slug'], $item['name']['ja'], $item['name']['en'], $item['summary']],
        $item['tags']
    );
    return mb_strtolower(implode(' ', $parts));
}

/**
 * Items matching a filter set, in id order.
 *
 * @param array<string, string> $filters Sanitized filters.
 * @return array<string, array<string, mixed>>
 */
function omochix_learn_gallery_get_items(array $filters = []) {
    $matches = [];
    foreach (omochix_learn_gallery_get_catalog()['items'] as $slug => $item) {
        if (omochix_learn_gallery_item_matches($item, $filters)) {
            $matches[$slug] = $item;
        }
    }
    return $matches;
}

/**
 * Featured items (for the Learn hub), id order, capped.
 *
 * @param int $limit Maximum number of items.
 * @return array<string, array<string, mixed>>
 */
function omochix_learn_gallery_get_featured($limit = 3) {
    $featured = array_filter(omochix_learn_gallery_get_catalog()['items'], static function ($item) {
        return $item['featured'];
    });
    return array_slice($featured, 0, max(0, (int) $limit), true);
}

/**
 * Items that list a Prompt Library entry in related.prompts (reverse lookup
 * used by single-prompt.php), id order.
 *
 * @param string $prompt_slug Prompt post_name.
 * @return array<string, array<string, mixed>>
 */
function omochix_learn_gallery_items_for_prompt($prompt_slug) {
    $prompt_slug = (string) $prompt_slug;
    if ('' === $prompt_slug) {
        return [];
    }
    $matches = [];
    foreach (omochix_learn_gallery_get_catalog()['items'] as $slug => $item) {
        if (in_array($prompt_slug, $item['related']['prompts'], true)) {
            $matches[$slug] = $item;
        }
    }
    return $matches;
}

/**
 * Previous and next items within the same category (id order).
 *
 * @param array<string, mixed> $item Catalog item.
 * @return array{prev: array<string, mixed>|null, next: array<string, mixed>|null}
 */
function omochix_learn_gallery_get_neighbors(array $item) {
    $siblings = array_values(omochix_learn_gallery_get_items(['cat' => $item['category']]));
    $prev     = null;
    $next     = null;
    foreach ($siblings as $index => $sibling) {
        if ($sibling['slug'] === $item['slug']) {
            $prev = $siblings[$index - 1] ?? null;
            $next = $siblings[$index + 1] ?? null;
            break;
        }
    }
    return ['prev' => $prev, 'next' => $next];
}

/**
 * Sanitize list filters from a request array (usually $_GET).
 *
 * Unknown categories/levels/techs are dropped rather than passed through, so
 * the SSR list and the client-side filter never disagree about an invalid
 * value.
 *
 * @param array<string, mixed> $source Raw request values.
 * @return array{cat: string, level: string, tech: string, q: string}
 */
function omochix_learn_gallery_sanitize_filters(array $source) {
    $cat = isset($source['cat']) && is_string($source['cat']) ? sanitize_title(wp_unslash($source['cat'])) : '';
    if ($cat && !omochix_learn_gallery_get_category($cat)) {
        $cat = '';
    }
    $level = isset($source['level']) && is_string($source['level']) ? sanitize_key(wp_unslash($source['level'])) : '';
    if (!isset(omochix_learn_gallery_levels()[$level])) {
        $level = '';
    }
    $tech = isset($source['tech']) && is_string($source['tech']) ? sanitize_key(wp_unslash($source['tech'])) : '';
    if (!isset(omochix_learn_gallery_techs()[$tech])) {
        $tech = '';
    }
    $q = isset($source['q']) && is_string($source['q']) ? sanitize_text_field(wp_unslash($source['q'])) : '';
    $q = mb_substr($q, 0, 60);

    return ['cat' => $cat, 'level' => $level, 'tech' => $tech, 'q' => $q];
}

/* -------------------------------------------------------------------------
 * Text composers: prompt and code
 * ---------------------------------------------------------------------- */

/**
 * The text "プロンプトをコピー" puts on the clipboard.
 *
 * @param array<string, mixed> $item Catalog item.
 * @param string               $url  Public detail URL (may be empty).
 * @return string
 */
function omochix_learn_gallery_prompt_text(array $item, $url = '', ?array $values = null) {
    $values = null === $values ? omochix_learn_gallery_sanitize_params($item) : $values;
    return str_replace('{{params_text}}', omochix_learn_gallery_params_text($item, $values), omochix_learn_gallery_prompt_template($item, $url));
}

/**
 * Prompt text with a {{params_text}} placeholder (filled by PHP for the
 * server-rendered copy and by learn-gallery.js after the viewer adjusts
 * parameters). Everything else is fixed per item.
 *
 * @param array<string, mixed> $item Catalog item.
 * @param string               $url  Public detail URL (may be empty).
 * @return string
 */
function omochix_learn_gallery_prompt_template(array $item, $url = '') {
    $category = omochix_learn_gallery_get_category($item['category']);
    $lines    = [];
    $lines[]  = sprintf('「%s（%s）」の動きを実装してください。', $item['name']['ja'], $item['name']['en']);
    $lines[]  = sprintf('OmochiX Learn の動く見本 #%s（%s）です。', $item['id'], $category ? $category['name'] : $item['category']);
    $lines[]  = '';
    $lines[]  = '頼み方の例: ' . $item['prompt']['ask'];
    $lines[]  = 'どんな動きか: ' . $item['summary'];
    $lines[]  = '見本で起きること: ' . $item['description'];
    $lines[]  = '仕様:';
    foreach ($item['prompt']['spec'] as $spec) {
        $lines[] = '- ' . $spec;
    }
    $lines[] = '使いどころ: ' . $item['prompt']['use'];
    $lines[] = '{{params_text}}';
    $lines[] = '実装の条件:';
    $lines[] = '- HTML / CSS（必要なら素のJavaScript）で、外部ライブラリは使わない';
    $lines[] = '- 見本の形・タイミング・イージングの数値はそのまま使い、文字・色・素材だけ今作っているものに合わせる';
    $lines[] = '- prefers-reduced-motion: reduce の環境では動きを止めるか最小限にする';
    $lines[] = '- 使った技術: ' . implode(' / ', array_map(static function ($tech) {
        return omochix_learn_gallery_techs()[$tech] ?? $tech;
    }, $item['tech']));
    if ($url) {
        $lines[] = '';
        $lines[] = '見本（動くプレビューと参考コード）: ' . $url;
    }
    return implode("\n", $lines);
}

/**
 * Stand-in for the preview runtime's `omx` so a copied JavaScript sample
 * runs on its own: one requestAnimationFrame loop shared by every
 * animate() callback, stopped while the tab is hidden or the visitor
 * prefers reduced motion. `root` is the element a sample queries inside
 * (the document here; the composer's per-slot omxSlot() narrows it).
 *
 * @return string
 */
function omochix_learn_gallery_js_shim() {
    return <<<'JS'
var omx = window.omx = window.omx || (function () {
  // OmochiX の見本用の最小ランタイム。animate() の処理をすべて 1 本の
  // requestAnimationFrame にまとめ、タブが隠れている間と
  // prefers-reduced-motion: reduce のときは止める。
  var fns = [], frame = 0, started = 0, stoppedAt = 0, lost = 0;
  var media = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  function stopped() { return (media && media.matches) || document.hidden; }
  function tick(now) {
    frame = 0;
    if (stopped()) { stop(); return; }
    var t = (now - started - lost) / 1000;
    for (var i = 0; i < fns.length; i++) { try { fns[i](t); } catch (e) { /* 他の見本は止めない */ } }
    frame = window.requestAnimationFrame(tick);
  }
  function stop() {
    if (frame) { window.cancelAnimationFrame(frame); frame = 0; }
    if (started && !stoppedAt) stoppedAt = performance.now();
  }
  function sync() {
    api.reduced = !!(media && media.matches);
    if (stopped()) { stop(); return; }
    if (frame || !fns.length) return;
    var now = performance.now();
    if (!started) started = now; else if (stoppedAt) lost += now - stoppedAt;
    stoppedAt = 0;
    frame = window.requestAnimationFrame(tick);
  }
  var api = {
    root: document,
    reduced: !!(media && media.matches),
    animate: function (fn) { if (typeof fn === 'function') { fns.push(fn); sync(); } },
    param: function (key, fallback) { var p = window.omxParams || {}; return Object.prototype.hasOwnProperty.call(p, key) ? p[key] : fallback; },
    onParams: function () {}
  };
  document.addEventListener('visibilitychange', sync);
  if (media && media.addEventListener) media.addEventListener('change', sync);
  return api;
}());
JS;
}

/**
 * The text "コードをコピー" puts on the clipboard: one self-contained HTML
 * fragment that reproduces the preview with the given parameter values.
 *
 * @param array<string, mixed>             $item   Catalog item.
 * @param string                           $url    Public detail URL (may be empty).
 * @param array<string, float|string>|null $values Sanitized parameter values (defaults when null).
 * @return string
 */
function omochix_learn_gallery_code_text(array $item, $url = '', ?array $values = null) {
    $values = null === $values ? omochix_learn_gallery_sanitize_params($item) : $values;
    $blocks = omochix_learn_gallery_params_blocks($item, $values);
    return strtr(omochix_learn_gallery_code_template($item, $url), [
        '{{params_css}}' => '' !== $blocks['css'] ? $blocks['css'] . "\n" : '',
        '{{params_js}}'  => '' !== $blocks['js'] ? $blocks['js'] . "\n" : '',
    ]);
}

/**
 * Copied-code template with {{params_css}} / {{params_js}} placeholders
 * (each becomes either an empty string or a block followed by a newline).
 * Shared with learn-gallery.js so adjusted code is assembled identically.
 *
 * @param array<string, mixed> $item Catalog item.
 * @param string               $url  Public detail URL (may be empty).
 * @return string
 */
function omochix_learn_gallery_code_template(array $item, $url = '') {
    $parts   = [];
    $parts[] = sprintf('<!-- %s (%s) | OmochiX Learn 動く見本 #%s%s -->', $item['name']['ja'], $item['name']['en'], $item['id'], $url ? ' | ' . $url : '');
    if ('' !== $item['preview']['css'] || $item['params']) {
        $parts[] = "<style>\n{{params_css}}" . $item['preview']['css'] . "\n</style>";
    }
    $parts[] = $item['preview']['html'];
    if ('' !== $item['preview']['js']) {
        $parts[] = "<script>\n" . omochix_learn_gallery_js_shim() . "\n{{params_js}}" . $item['preview']['js'] . "\n</script>";
    }
    return implode("\n\n", $parts);
}

/* -------------------------------------------------------------------------
 * Preview document (sandboxed iframe srcdoc)
 * ---------------------------------------------------------------------- */

/**
 * Runtime CSS and JS every preview document carries, read once per request.
 *
 * Inlined rather than linked: a sandboxed srcdoc frame has an opaque origin,
 * and fetching theme files from it is refused in some setups (local-network
 * access checks, strict CSP), while inline script always runs. To avoid
 * repeating the runtime in each of the 20+ documents the list embeds, the
 * client JSON carries the runtime once plus the document template, and
 * learn-gallery.js assembles each document from them (see
 * omochix_learn_gallery_preview_template()).
 *
 * @param string $kind "css" or "js".
 * @return string
 */
function omochix_learn_gallery_runtime($kind) {
    static $cache = [];
    if (!isset($cache[$kind])) {
        $file         = 'css' === $kind ? '/assets/css/learn-preview-runtime.css' : '/assets/js/learn-preview-runtime.js';
        $path         = get_theme_file_path($file);
        $cache[$kind] = is_readable($path) ? (string) file_get_contents($path) : '';
    }
    return $cache[$kind];
}

/**
 * The single definition of a preview document's structure, with
 * placeholders. PHP fills it for the no-JS iframe on the detail page and
 * learn-gallery.js fills the same template in the browser, so the two can
 * never drift apart.
 *
 * Placeholders: {{stage_w}} {{stage_h}} {{title}} (already HTML-escaped)
 * {{runtime_css}} {{params_css}} (":root{…}" or empty) {{css}} {{html}}
 * {{runtime_js}} {{params_js}} ("<script>window.omxParams=…</script>" or
 * empty) {{js}} (a complete "<script>…</script>" element or an empty
 * string).
 *
 * @return string
 */
function omochix_learn_gallery_preview_template() {
    return '<!doctype html><html lang="ja" data-theme="light" data-stage-w="{{stage_w}}" data-stage-h="{{stage_h}}">'
        . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>{{title}}</title>'
        . '<style>{{runtime_css}}</style>'
        . '<style id="omx-params">{{params_css}}</style>'
        . '<style>{{css}}</style>'
        . '</head><body><div class="omx-stage">{{html}}</div>'
        . '<script>{{runtime_js}}</script>{{params_js}}{{js}}</body></html>';
}

/**
 * Build the complete HTML document a preview iframe runs.
 *
 * The document is only ever loaded through `srcdoc` on an iframe with
 * sandbox="allow-scripts" (no same-origin access, no top navigation), so a
 * sample's code cannot reach the parent page, cookies or storage. The theme
 * is set later by the parent through postMessage; "light" is the initial
 * value so the first paint matches the default theme.
 *
 * @param array<string, mixed> $item  Catalog item.
 * @param array<string, int>   $stage Stage size.
 * @return string
 */
function omochix_learn_gallery_preview_document(array $item, ?array $stage = null, ?array $params = null) {
    $stage  = $stage ?: omochix_learn_gallery_get_stage();
    $params = null === $params ? omochix_learn_gallery_sanitize_params($item) : $params;
    $blocks = omochix_learn_gallery_params_blocks($item, $params);
    $values = [
        '{{stage_w}}'     => (string) (int) $stage['width'],
        '{{stage_h}}'     => (string) (int) $stage['height'],
        '{{title}}'       => esc_html($item['name']['ja']),
        '{{runtime_css}}' => omochix_learn_gallery_runtime('css'),
        '{{params_css}}'  => $blocks['css'],
        '{{css}}'         => $item['preview']['css'],
        '{{html}}'        => $item['preview']['html'],
        '{{runtime_js}}'  => omochix_learn_gallery_runtime('js'),
        '{{params_js}}'   => '' !== $blocks['js'] ? '<script>' . $blocks['js'] . '</script>' : '',
        '{{js}}'          => '' !== $item['preview']['js'] ? '<script>' . $item['preview']['js'] . '</script>' : '',
    ];
    return strtr(omochix_learn_gallery_preview_template(), $values);
}

/**
 * Items whose preview documents the current page needs; template parts
 * register what they render and the footer prints them once as JSON.
 *
 * @param array<string, mixed>|null $item Item to register, or null to read.
 * @return array<string, array<string, mixed>>
 */
function omochix_learn_gallery_registry($item = null) {
    static $registry = [];
    if (is_array($item) && isset($item['slug'])) {
        $registry[$item['slug']] = $item;
    }
    return $registry;
}

/**
 * JSON for the client: only what learn-gallery.js needs (copy templates travel
 * with the panel, see omochix_learn_gallery_item_json()). The runtime and the
 * document template appear once; each item carries just its own fragment
 * (title, html, css, js) and the browser assembles the document. JSON_HEX_TAG
 * keeps a literal "</script>" inside a sample from ending the embedding
 * element.
 *
 * @param array<string, array<string, mixed>> $items Registered items.
 * @return string
 */
function omochix_learn_gallery_client_json(array $items) {
    $stage = omochix_learn_gallery_get_stage();
    $out   = [
        'stage'    => $stage,
        'template' => omochix_learn_gallery_preview_template(),
        'runtime'  => ['css' => omochix_learn_gallery_runtime('css'), 'js' => omochix_learn_gallery_runtime('js')],
        'items'    => [],
    ];
    foreach ($items as $slug => $item) {
        $out['items'][$slug] = [
            'id'     => $item['id'],
            'name'   => $item['name'],
            'label'  => sprintf(__('%sのプレビュー', 'omochix'), $item['name']['ja']),
            'title'  => esc_html($item['name']['ja']),
            'html'   => $item['preview']['html'],
            'css'    => $item['preview']['css'],
            'js'     => $item['preview']['js'],
            'params' => $item['params'],
        ];
    }
    return (string) wp_json_encode($out, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * The copy templates of one adjustable sample, as JSON for the playground
 * script. Printed next to the panel (template-parts/learn-gallery/
 * playground.php) on the detail page and inside the dialog fragment, so the
 * list page's catalog JSON does not carry twenty samples' worth of
 * templates that only a shown panel can use. Empty string for a sample
 * without params.
 *
 * @param array<string, mixed> $item Catalog item.
 * @param string               $url  Canonical detail URL (goes into the templates).
 * @return string
 */
function omochix_learn_gallery_item_json(array $item, $url = '') {
    if (!$item['params']) {
        return '';
    }
    return (string) wp_json_encode([
        'codeTemplate'   => omochix_learn_gallery_code_template($item, $url),
        'promptTemplate' => omochix_learn_gallery_prompt_template($item, $url),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Print the registered previews for learn-gallery.js (runs late in wp_footer). */
function omochix_learn_gallery_print_client_data() {
    $items = omochix_learn_gallery_registry();
    if (!$items) {
        return;
    }
    echo '<script type="application/json" id="omx-motion-catalog">' . omochix_learn_gallery_client_json($items) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON built by omochix_learn_gallery_client_json().
}
add_action('wp_footer', 'omochix_learn_gallery_print_client_data', 5);

/* -------------------------------------------------------------------------
 * Routing
 * ---------------------------------------------------------------------- */

/** Published gallery page ID, or 0. */
function omochix_learn_gallery_page_id() {
    static $page_id = null;
    if (null !== $page_id) {
        return $page_id;
    }
    $page    = get_page_by_path(OMOCHIX_LEARN_GALLERY_PATH, OBJECT, 'page');
    $page_id = ($page instanceof WP_Post && 'publish' === $page->post_status) ? (int) $page->ID : 0;
    return $page_id;
}

/**
 * Public URL of the gallery list, an item, or a filtered list. Empty when
 * the gallery page is not published.
 *
 * @param array<string, mixed>|null $item    Item for a detail URL.
 * @param array<string, string>     $filters Filters for a list URL.
 * @return string
 */
function omochix_learn_gallery_url($item = null, array $filters = []) {
    $page_id = omochix_learn_gallery_page_id();
    if (!$page_id) {
        return '';
    }
    $base = trailingslashit((string) get_permalink($page_id));
    if (is_array($item) && isset($item['slug'])) {
        return user_trailingslashit($base . $item['slug']);
    }
    $args = array_filter($filters, static function ($value) {
        return '' !== $value && null !== $value;
    });
    return $args ? add_query_arg(array_map('rawurlencode', $args), $base) : $base;
}

function omochix_learn_gallery_register_query_vars($vars) {
    $vars[] = OMOCHIX_LEARN_GALLERY_QUERY_VAR;
    $vars[] = OMOCHIX_LEARN_GALLERY_PARTIAL_VAR;
    return $vars;
}
add_filter('query_vars', 'omochix_learn_gallery_register_query_vars');

function omochix_learn_gallery_register_rewrite_rules() {
    add_rewrite_rule(
        '^' . preg_quote(OMOCHIX_LEARN_GALLERY_PATH, '#') . '/([a-z0-9-]+)/?$',
        'index.php?pagename=' . OMOCHIX_LEARN_GALLERY_PATH . '&' . OMOCHIX_LEARN_GALLERY_QUERY_VAR . '=$matches[1]',
        'top'
    );
}
add_action('init', 'omochix_learn_gallery_register_rewrite_rules');

/**
 * Flush rewrite rules once per rule version (deploying a theme update does
 * not fire after_switch_theme). Also flushes on theme activation.
 */
function omochix_learn_gallery_maybe_flush_rewrite_rules() {
    if (get_option('omochix_learn_gallery_rewrite_version') === OMOCHIX_LEARN_GALLERY_REWRITE_VERSION) {
        return;
    }
    flush_rewrite_rules(false);
    // Autoloaded (default) so the check above costs no extra query per request.
    update_option('omochix_learn_gallery_rewrite_version', OMOCHIX_LEARN_GALLERY_REWRITE_VERSION);
}
add_action('init', 'omochix_learn_gallery_maybe_flush_rewrite_rules', 20);
add_action('after_switch_theme', static function () {
    delete_option('omochix_learn_gallery_rewrite_version');
});

/** Whether the main query is the gallery page (list or detail). */
function omochix_learn_gallery_is_request() {
    if (!is_page()) {
        return false;
    }
    $page_id = omochix_learn_gallery_page_id();
    return $page_id && get_queried_object_id() === $page_id;
}

/** Requested item slug ('' on the list). */
function omochix_learn_gallery_requested_slug() {
    $slug = get_query_var(OMOCHIX_LEARN_GALLERY_QUERY_VAR, '');
    return is_string($slug) ? sanitize_title($slug) : '';
}

/** Whether only the detail fragment was requested (?omx_partial=1). */
function omochix_learn_gallery_is_partial_request() {
    return '1' === (string) get_query_var(OMOCHIX_LEARN_GALLERY_PARTIAL_VAR, '');
}

/** Whether the main query is the Learn hub page ("/learn"). */
function omochix_learn_gallery_is_hub_request() {
    if (!is_page() || !function_exists('omochix_get_learn_page_id')) {
        return false;
    }
    $hub_id = omochix_get_learn_page_id();
    return $hub_id && get_queried_object_id() === $hub_id;
}

/**
 * Unknown item -> 404; partial request -> print the fragment and stop.
 */
function omochix_learn_gallery_template_redirect() {
    if (!omochix_learn_gallery_is_request()) {
        return;
    }
    $slug = omochix_learn_gallery_requested_slug();
    if ('' === $slug) {
        return;
    }
    $item = omochix_learn_gallery_get_item($slug);
    if (!$item) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return;
    }
    if (omochix_learn_gallery_is_partial_request()) {
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        header('X-Robots-Tag: noindex');
        get_template_part('template-parts/learn-gallery/detail', null, ['item' => $item, 'context' => 'dialog']);
        exit;
    }
}
add_action('template_redirect', 'omochix_learn_gallery_template_redirect');

/**
 * Serve page-learn-gallery.php for the gallery page. Runs before the Learn
 * article swap in inc/learn.php (priority 10), which then leaves the
 * already-specific template alone.
 *
 * @param string $template Resolved template.
 * @return string
 */
function omochix_learn_gallery_template_include($template) {
    if (!omochix_learn_gallery_is_request()) {
        return $template;
    }
    $gallery = get_theme_file_path('/page-learn-gallery.php');
    return file_exists($gallery) ? $gallery : $template;
}
add_filter('template_include', 'omochix_learn_gallery_template_include', 9);

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

/**
 * Whether the current prompt detail page shows related motion samples (and
 * therefore needs the gallery assets).
 */
function omochix_learn_gallery_is_prompt_with_samples() {
    if (!is_singular('prompt') || !omochix_learn_gallery_page_id()) {
        return false;
    }
    $post = get_queried_object();
    return $post instanceof WP_Post && [] !== omochix_learn_gallery_items_for_prompt($post->post_name);
}

function omochix_learn_gallery_enqueue_assets() {
    $composer = function_exists('omochix_learn_composer_is_request') && omochix_learn_composer_is_request();
    if (!$composer && !omochix_learn_gallery_is_request() && !omochix_learn_gallery_is_hub_request() && !omochix_learn_gallery_is_prompt_with_samples()) {
        return;
    }
    $theme = wp_get_theme();
    // After shell.css when the shared shell is on this page, so the shell's token overrides win.
    $deps = wp_style_is('omochix-shell', 'enqueued') ? ['omochix-style', 'omochix-shell'] : ['omochix-style'];
    wp_enqueue_style('omochix-learn-v2', get_theme_file_uri('/assets/css/learn-v2.css'), $deps, (string) filemtime(get_theme_file_path('/assets/css/learn-v2.css')));
    // Three scripts, in dependency order: parameter helpers (pure, shared with
    // the tests), the gallery (previews, filters, dialog) and the playground panel.
    wp_enqueue_script('omochix-learn-params', get_theme_file_uri('/assets/js/learn-params.js'), [], $theme->get('Version'), true);
    wp_enqueue_script('omochix-learn-gallery', get_theme_file_uri('/assets/js/learn-gallery.js'), ['omochix-learn-params'], $theme->get('Version'), true);
    wp_enqueue_script('omochix-learn-playground', get_theme_file_uri('/assets/js/learn-playground.js'), ['omochix-learn-gallery'], $theme->get('Version'), true);
    wp_add_inline_script('omochix-learn-gallery', 'window.omochixLearnGallery = ' . wp_json_encode([
        'listUrl' => omochix_learn_gallery_url(),
        'partialParam' => OMOCHIX_LEARN_GALLERY_PARTIAL_VAR,
        'strings' => [
            'play'        => __('再生', 'omochix'),
            'pause'       => __('一時停止', 'omochix'),
            'pauseAll'    => __('すべて一時停止', 'omochix'),
            'playAll'     => __('すべて再生', 'omochix'),
            'copied'      => __('コピーしました', 'omochix'),
            'copyFailed'  => __('コピーできませんでした', 'omochix'),
            'count'       => __('%s件の見本', 'omochix'),
            'noResults'   => __('条件に合う見本がありません', 'omochix'),
            'loadFailed'  => __('読み込めませんでした。ページを開きます。', 'omochix'),
            'linkCopied'  => __('リンクをコピーしました', 'omochix'),
            'resetDone'   => __('既定の値に戻しました', 'omochix'),
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'omochix_learn_gallery_enqueue_assets', 20);

/* -------------------------------------------------------------------------
 * SEO (document title, Slim SEO description / canonical / robots)
 * ---------------------------------------------------------------------- */

/** @return array<string, mixed>|null The item being viewed on a detail URL. */
function omochix_learn_gallery_current_item() {
    if (!omochix_learn_gallery_is_request()) {
        return null;
    }
    $slug = omochix_learn_gallery_requested_slug();
    return '' !== $slug ? omochix_learn_gallery_get_item($slug) : null;
}

/** True on the list when any filter is active (such URLs are noindex). */
function omochix_learn_gallery_is_filtered_list() {
    if (!omochix_learn_gallery_is_request() || '' !== omochix_learn_gallery_requested_slug()) {
        return false;
    }
    $filters = omochix_learn_gallery_sanitize_filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter state.
    return '' !== implode('', $filters);
}

function omochix_learn_gallery_detail_title() {
    $item = omochix_learn_gallery_current_item();
    if (!$item) {
        return '';
    }
    return sprintf(__('%1$s（%2$s）の動く見本 | Learn', 'omochix'), $item['name']['ja'], $item['name']['en']);
}

add_filter('document_title_parts', static function ($parts) {
    $title = omochix_learn_gallery_detail_title();
    if ($title) {
        $parts['title'] = $title;
    }
    return $parts;
});
add_filter('slim_seo_meta_title', static function ($title) {
    $detail = omochix_learn_gallery_detail_title();
    return $detail ? $detail . ' | ' . get_bloginfo('name') : $title;
});
add_filter('slim_seo_meta_description', static function ($description) {
    $item = omochix_learn_gallery_current_item();
    if (!$item) {
        return $description;
    }
    return sprintf(__('%1$s：%2$s プロンプトとコードをコピーして、AIにそのまま頼めます。', 'omochix'), $item['summary'], $item['description']);
}, 10, 1);
add_filter('slim_seo_canonical_url', static function ($url) {
    $item = omochix_learn_gallery_current_item();
    if ($item) {
        return omochix_learn_gallery_url($item) ?: $url;
    }
    return omochix_learn_gallery_is_filtered_list() ? (omochix_learn_gallery_url() ?: $url) : $url;
});
add_filter('slim_seo_robots_index', static function ($indexed) {
    return omochix_learn_gallery_is_filtered_list() ? false : $indexed;
});

/**
 * Breadcrumb trail for a sample detail: the gallery list becomes a link and
 * the sample name becomes the current page (Slim SEO builds its visible
 * breadcrumb and the BreadcrumbList schema from these two values).
 *
 * @param array<int, array{url: string, text: string}> $links Trail so far (home … gallery page's ancestors).
 * @param array<string, mixed>                         $item  Sample being viewed.
 * @param string                                       $list_url Gallery list URL.
 * @param string                                       $list_title Gallery page title.
 * @return array<int, array{url: string, text: string}>
 */
function omochix_learn_gallery_breadcrumb_links(array $links, array $item, $list_url, $list_title) {
    unset($item);
    $last = $links ? end($links) : null;
    if (!$list_url || ($last && isset($last['url']) && untrailingslashit($last['url']) === untrailingslashit($list_url))) {
        return $links;
    }
    $links[] = ['url' => $list_url, 'text' => $list_title];
    return $links;
}
add_filter('slim_seo_breadcrumbs_links', static function ($links) {
    $item = omochix_learn_gallery_current_item();
    if (!$item || !is_array($links)) {
        return $links;
    }
    return omochix_learn_gallery_breadcrumb_links($links, $item, omochix_learn_gallery_url(), get_the_title(omochix_learn_gallery_page_id()));
});
add_filter('single_post_title', static function ($title, $post) {
    $item = omochix_learn_gallery_current_item();
    if ($item && $post instanceof WP_Post && (int) $post->ID === omochix_learn_gallery_page_id()) {
        return $item['name']['ja'];
    }
    return $title;
}, 10, 2);
add_filter('wp_robots', static function ($robots) {
    if (omochix_learn_gallery_is_filtered_list()) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }
    return $robots;
});

/* -------------------------------------------------------------------------
 * Related content (resolved against published WordPress content only)
 * ---------------------------------------------------------------------- */

/**
 * @param array<string, mixed> $item Catalog item.
 * @return WP_Post[]
 */
function omochix_learn_gallery_related_prompts(array $item) {
    if (!$item['related']['prompts'] || !post_type_exists('prompt')) {
        return [];
    }
    return get_posts([
        'post_type'      => 'prompt',
        'post_status'    => 'publish',
        'post_name__in'  => $item['related']['prompts'],
        'posts_per_page' => 6,
        'orderby'        => 'post_name__in',
        'no_found_rows'  => true,
    ]);
}

/**
 * @param array<string, mixed> $item Catalog item.
 * @return WP_Post[]
 */
function omochix_learn_gallery_related_tools(array $item) {
    if (!$item['related']['tools'] || !post_type_exists('ai_tool')) {
        return [];
    }
    $args = [
        'post_type'      => 'ai_tool',
        'post_status'    => 'publish',
        'post_name__in'  => $item['related']['tools'],
        'posts_per_page' => 6,
        'orderby'        => 'post_name__in',
        'no_found_rows'  => true,
    ];
    if (function_exists('omochix_get_active_ai_tool_meta_query')) {
        $args['meta_query'] = omochix_get_active_ai_tool_meta_query(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
    }
    return get_posts($args);
}

/**
 * @param array<string, mixed> $item Catalog item.
 * @return array<int, array{title: string, url: string, description: string}>
 */
function omochix_learn_gallery_related_learn(array $item) {
    $pages = [];
    foreach ($item['related']['learn'] as $path) {
        $page = get_page_by_path($path, OBJECT, 'page');
        if (!($page instanceof WP_Post) || 'publish' !== $page->post_status) {
            continue;
        }
        $pages[] = [
            'title'       => get_the_title($page),
            'url'         => (string) get_permalink($page),
            'description' => function_exists('omochix_get_learn_child_description') ? omochix_get_learn_child_description($page) : '',
        ];
    }
    return $pages;
}
