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
        if (array_key_exists('playground', $item) && null !== $item['playground'] && !is_array($item['playground'])) {
            $errors[] = "$where: playground must be an object or null";
        }
        if (array_key_exists('video', $item) && null !== $item['video'] && !is_array($item['video'])) {
            $errors[] = "$where: video must be an object or null";
        }
    }

    return $errors;
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
function omochix_learn_gallery_prompt_text(array $item, $url = '') {
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
    $lines[] = '';
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
 * Minimal stand-in for the preview runtime's `omx.animate()` so a copied
 * JavaScript sample runs on its own.
 *
 * @return string
 */
function omochix_learn_gallery_js_shim() {
    return "var omx = window.omx || { animate: function (fn) { var start = performance.now(); (function frame(now) { fn((now - start) / 1000); requestAnimationFrame(frame); }(start)); } };";
}

/**
 * The text "コードをコピー" puts on the clipboard: one self-contained HTML
 * fragment that reproduces the preview.
 *
 * @param array<string, mixed> $item Catalog item.
 * @param string               $url  Public detail URL (may be empty).
 * @return string
 */
function omochix_learn_gallery_code_text(array $item, $url = '') {
    $parts   = [];
    $parts[] = sprintf('<!-- %s (%s) | OmochiX Learn 動く見本 #%s%s -->', $item['name']['ja'], $item['name']['en'], $item['id'], $url ? ' | ' . $url : '');
    if ('' !== $item['preview']['css']) {
        $parts[] = "<style>\n" . $item['preview']['css'] . "\n</style>";
    }
    $parts[] = $item['preview']['html'];
    if ('' !== $item['preview']['js']) {
        $parts[] = "<script>\n" . omochix_learn_gallery_js_shim() . "\n" . $item['preview']['js'] . "\n</script>";
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
 * {{runtime_css}} {{css}} {{html}} {{runtime_js}} {{js}} (a complete
 * "<script>…</script>" element or an empty string).
 *
 * @return string
 */
function omochix_learn_gallery_preview_template() {
    return '<!doctype html><html lang="ja" data-theme="light" data-stage-w="{{stage_w}}" data-stage-h="{{stage_h}}">'
        . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>{{title}}</title>'
        . '<style>{{runtime_css}}</style>'
        . '<style>{{css}}</style>'
        . '</head><body><div class="omx-stage">{{html}}</div>'
        . '<script>{{runtime_js}}</script>{{js}}</body></html>';
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
function omochix_learn_gallery_preview_document(array $item, ?array $stage = null) {
    $stage  = $stage ?: omochix_learn_gallery_get_stage();
    $values = [
        '{{stage_w}}'     => (string) (int) $stage['width'],
        '{{stage_h}}'     => (string) (int) $stage['height'],
        '{{title}}'       => esc_html($item['name']['ja']),
        '{{runtime_css}}' => omochix_learn_gallery_runtime('css'),
        '{{css}}'         => $item['preview']['css'],
        '{{html}}'        => $item['preview']['html'],
        '{{runtime_js}}'  => omochix_learn_gallery_runtime('js'),
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
 * JSON for the client: only what learn-gallery.js needs. The runtime and the
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
            'id'    => $item['id'],
            'name'  => $item['name'],
            'label' => sprintf(__('%sのプレビュー', 'omochix'), $item['name']['ja']),
            'title' => esc_html($item['name']['ja']),
            'html'  => $item['preview']['html'],
            'css'   => $item['preview']['css'],
            'js'    => $item['preview']['js'],
        ];
    }
    return (string) wp_json_encode($out, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    if (!omochix_learn_gallery_is_request() && !omochix_learn_gallery_is_hub_request() && !omochix_learn_gallery_is_prompt_with_samples()) {
        return;
    }
    $theme = wp_get_theme();
    // After shell.css when the shared shell is on this page, so the shell's token overrides win.
    $deps = wp_style_is('omochix-shell', 'enqueued') ? ['omochix-style', 'omochix-shell'] : ['omochix-style'];
    wp_enqueue_style('omochix-learn-v2', get_theme_file_uri('/assets/css/learn-v2.css'), $deps, (string) filemtime(get_theme_file_path('/assets/css/learn-v2.css')));
    wp_enqueue_script('omochix-learn-gallery', get_theme_file_uri('/assets/js/learn-gallery.js'), [], $theme->get('Version'), true);
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
