<?php
/**
 * OmochiX Labs: free in-browser tools ("/labs/" and "/labs/{slug}/").
 *
 * Same shape as the Motion Gallery (inc/learn-gallery.php): the list is a
 * normal WordPress page (path "labs"), a tool's detail URL is a rewrite rule
 * that resolves to that page with the `omx_lab` query var set, and the tools
 * themselves come from the theme-shipped catalog in assets/data/labs/. No
 * post type, no database rows, no server endpoint: every tool runs in the
 * visitor's browser and nothing they type is sent anywhere.
 *
 * Everything here is additive. The only hooks into existing code are the
 * require in functions.php, one condition in omochix_shell_enabled()
 * (inc/shell.php) and the Labs card on the Learn hub. See docs/labs/.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

const OMOCHIX_LABS_PATH             = 'labs';
const OMOCHIX_LABS_QUERY_VAR        = 'omx_lab';
const OMOCHIX_LABS_REWRITE_VERSION  = '2'; // 2: /labs-sitemap.xml
const OMOCHIX_LABS_SITEMAP_PATH     = 'labs-sitemap.xml';
const OMOCHIX_LABS_SITEMAP_VAR      = 'omx_labs_sitemap';
const OMOCHIX_LABS_CATALOG_VERSION  = 1;
const OMOCHIX_LABS_PRICING_VERSION  = 1;
const OMOCHIX_LABS_CRAWLERS_VERSION = 1;

/**
 * Hosts a price may be sourced from (the providers' own pricing pages). A
 * price whose source_url is anywhere else is never shown. Mirrored in
 * assets/js/labs/core/cost.js (tests/labs/data.js checks both agree).
 */
const OMOCHIX_LABS_PRICE_SOURCE_HOSTS = [
    'platform.claude.com', 'docs.anthropic.com', 'www.anthropic.com', 'anthropic.com', 'claude.com', 'www.claude.com',
    'developers.openai.com', 'platform.openai.com', 'openai.com',
    'ai.google.dev', 'aistudio.google.com', 'cloud.google.com',
];

/** Input limits in bytes (UTF-8), shared with the browser through omochixLabs.limits. */
function omochix_labs_limits() {
    return [
        'json-formatter'        => ['input' => 5 * 1024 * 1024, 'worker' => 256 * 1024],
        'text-diff'             => ['input' => 1024 * 1024, 'lines' => 20000, 'worker' => 0],
        'robots-txt-generator'  => ['rules' => 200, 'path' => 2048, 'robots' => 512 * 1024],
        'token-cost-calculator' => ['input' => 1024 * 1024, 'worker' => 256 * 1024],
        'timeout_ms'            => 10000,
    ];
}

/* -------------------------------------------------------------------------
 * Data files: load and validate
 * ---------------------------------------------------------------------- */

function omochix_labs_data_file($name) {
    return get_theme_file_path('/assets/data/labs/' . $name . '.json');
}

/** Lower-case slug: letters, digits, single hyphens. */
function omochix_labs_is_slug($value) {
    return is_string($value) && '' !== $value && strlen($value) <= 60 && 1 === preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
}

/** Learn page path such as "learn/tutorials/add-ai-api". */
function omochix_labs_is_learn_path($value) {
    return is_string($value) && strlen($value) <= 120 && 1 === preg_match('#^learn(?:/[a-z0-9]+(?:-[a-z0-9]+)*)+$#', $value);
}

function omochix_labs_is_date($value) {
    if (!is_string($value) || 1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

function omochix_labs_strlen($text) {
    return function_exists('mb_strlen') ? mb_strlen((string) $text, 'UTF-8') : strlen((string) $text);
}

/**
 * @param string $file Absolute path.
 * @return array{ok: bool, data: mixed, errors: string[]}
 */
function omochix_labs_read_json($file) {
    if (!is_string($file) || !is_readable($file)) {
        return ['ok' => false, 'data' => null, 'errors' => ['file not readable']];
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (JSON_ERROR_NONE !== json_last_error()) {
        return ['ok' => false, 'data' => null, 'errors' => ['invalid JSON: ' . json_last_error_msg()]];
    }
    return ['ok' => true, 'data' => $data, 'errors' => []];
}

/**
 * Validate a decoded catalog. Returns human-readable errors; an empty list
 * means the catalog is usable.
 *
 * @param mixed $data Decoded catalog.json.
 * @return string[]
 */
function omochix_labs_validate_catalog($data) {
    if (!is_array($data)) {
        return ['root must be an object'];
    }
    $errors = [];
    if (($data['version'] ?? null) !== OMOCHIX_LABS_CATALOG_VERSION) {
        $errors[] = 'version must be ' . OMOCHIX_LABS_CATALOG_VERSION;
    }
    if (!omochix_labs_is_date($data['updated'] ?? null)) {
        $errors[] = 'updated must be YYYY-MM-DD';
    }
    $categories = [];
    foreach ((array) ($data['categories'] ?? []) as $i => $cat) {
        $slug = is_array($cat) ? ($cat['slug'] ?? null) : null;
        if (!omochix_labs_is_slug($slug)) {
            $errors[] = "categories[$i]: invalid slug";
            continue;
        }
        if (isset($categories[$slug])) {
            $errors[] = "categories[$i]: duplicate slug $slug";
        }
        if (!is_string($cat['name'] ?? null) || '' === $cat['name']) {
            $errors[] = "categories[$i]: name required";
        }
        $categories[$slug] = true;
    }
    if (!$categories) {
        $errors[] = 'categories must not be empty';
    }
    $tools = (array) ($data['tools'] ?? []);
    if (!$tools) {
        $errors[] = 'tools must not be empty';
    }
    $slugs = [];
    foreach ($tools as $tool) {
        if (is_array($tool) && omochix_labs_is_slug($tool['slug'] ?? null)) {
            $slugs[$tool['slug']] = true;
        }
    }
    $seen = [];
    foreach ($tools as $i => $tool) {
        $p = "tools[$i]";
        if (!is_array($tool)) {
            $errors[] = "$p: must be an object";
            continue;
        }
        $slug = $tool['slug'] ?? null;
        if (!omochix_labs_is_slug($slug)) {
            $errors[] = "$p: invalid slug";
            continue;
        }
        $p = "tools[$slug]";
        if (isset($seen[$slug])) {
            $errors[] = "$p: duplicate slug";
        }
        $seen[$slug] = true;
        if (!in_array($tool['status'] ?? null, ['published', 'draft'], true)) {
            $errors[] = "$p: status must be published or draft";
        }
        if (!isset($categories[$tool['category'] ?? ''])) {
            $errors[] = "$p: unknown category";
        }
        foreach (['name' => 40, 'title' => 70, 'lead' => 80, 'description' => 160] as $key => $max) {
            $value = $tool[$key] ?? null;
            if (!is_string($value) || '' === trim($value) || omochix_labs_strlen($value) > $max) {
                $errors[] = "$p: $key must be 1-$max characters";
            }
        }
        if (!in_array($tool['application_category'] ?? null, ['DeveloperApplication', 'UtilitiesApplication'], true)) {
            $errors[] = "$p: invalid application_category";
        }
        if (!omochix_labs_is_date($tool['updated'] ?? null)) {
            $errors[] = "$p: updated must be YYYY-MM-DD";
        }
        foreach (['keywords' => 20, 'features' => 8] as $key => $max) {
            $list = $tool[$key] ?? null;
            if (!is_array($list) || !$list || count($list) > $max || array_filter($list, static fn($v) => !is_string($v) || '' === $v)) {
                $errors[] = "$p: $key must be 1-$max non-empty strings";
            }
        }
        foreach ((array) ($tool['related'] ?? []) as $rel) {
            if (!is_string($rel) || !isset($slugs[$rel]) || $rel === $slug) {
                $errors[] = "$p: related must list other tool slugs";
                break;
            }
        }
        $links = $tool['links'] ?? [];
        if (!is_array($links)) {
            $errors[] = "$p: links must be an object";
        } else {
            foreach ((array) ($links['learn'] ?? []) as $path) {
                if (!omochix_labs_is_learn_path($path)) {
                    $errors[] = "$p: links.learn must be learn/… paths";
                    break;
                }
            }
            foreach (['ai_tools', 'prompts'] as $key) {
                foreach ((array) ($links[$key] ?? []) as $s) {
                    if (!omochix_labs_is_slug($s)) {
                        $errors[] = "$p: links.$key must be slugs";
                        break;
                    }
                }
            }
        }
        $faq = $tool['faq'] ?? [];
        if (!is_array($faq) || count($faq) > 10) {
            $errors[] = "$p: faq must be a list of at most 10";
        } else {
            foreach ($faq as $entry) {
                if (!is_array($entry) || !is_string($entry['q'] ?? null) || !is_string($entry['a'] ?? null) || '' === $entry['q'] || '' === $entry['a']) {
                    $errors[] = "$p: faq entries need q and a";
                    break;
                }
            }
        }
    }
    return $errors;
}

/**
 * @param array<string, mixed> $data Valid catalog.
 * @return array{version: int, updated: string, categories: array<string, array<string, mixed>>, tools: array<string, array<string, mixed>>}
 */
function omochix_labs_normalize_catalog(array $data) {
    $categories = [];
    foreach ($data['categories'] as $cat) {
        $categories[$cat['slug']] = [
            'slug'        => $cat['slug'],
            'name'        => (string) $cat['name'],
            'description' => (string) ($cat['description'] ?? ''),
            'order'       => (int) ($cat['order'] ?? 0),
            'count'       => 0,
        ];
    }
    uasort($categories, static fn($a, $b) => $a['order'] <=> $b['order']);
    $tools = [];
    foreach ($data['tools'] as $tool) {
        $links = is_array($tool['links'] ?? null) ? $tool['links'] : [];
        $tools[$tool['slug']] = [
            'slug'                 => $tool['slug'],
            'status'               => $tool['status'],
            'order'                => (int) ($tool['order'] ?? 0),
            'category'             => $tool['category'],
            'icon'                 => is_string($tool['icon'] ?? null) ? $tool['icon'] : 'tool',
            'name'                 => $tool['name'],
            'title'                => $tool['title'],
            'lead'                 => $tool['lead'],
            'description'          => $tool['description'],
            'keywords'             => array_values($tool['keywords']),
            'features'             => array_values($tool['features']),
            'application_category' => $tool['application_category'],
            'related'              => array_values((array) ($tool['related'] ?? [])),
            'links'                => [
                'learn'    => array_values((array) ($links['learn'] ?? [])),
                'ai_tools' => array_values((array) ($links['ai_tools'] ?? [])),
                'prompts'  => array_values((array) ($links['prompts'] ?? [])),
            ],
            'faq'                  => array_values((array) ($tool['faq'] ?? [])),
            'updated'              => $tool['updated'],
        ];
    }
    uasort($tools, static fn($a, $b) => $a['order'] <=> $b['order']);
    foreach ($tools as $tool) {
        if ('published' === $tool['status']) {
            $categories[$tool['category']]['count']++;
        }
    }
    return ['version' => (int) $data['version'], 'updated' => (string) $data['updated'], 'categories' => $categories, 'tools' => $tools];
}

/**
 * @param string $file Absolute path.
 * @return array{ok: bool, errors: string[], catalog: array<string, mixed>}
 */
function omochix_labs_load_catalog_from_file($file) {
    $empty = ['version' => 0, 'updated' => '', 'categories' => [], 'tools' => []];
    $read  = omochix_labs_read_json($file);
    if (!$read['ok']) {
        return ['ok' => false, 'errors' => $read['errors'], 'catalog' => $empty];
    }
    $errors = omochix_labs_validate_catalog($read['data']);
    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'catalog' => $empty];
    }
    return ['ok' => true, 'errors' => [], 'catalog' => omochix_labs_normalize_catalog($read['data'])];
}

function omochix_labs_get_catalog() {
    static $catalog = null;
    if (null === $catalog) {
        $loaded  = omochix_labs_load_catalog_from_file(omochix_labs_data_file('catalog'));
        $catalog = $loaded['catalog'];
        if (!$loaded['ok'] && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[omochix-labs] catalog.json rejected: ' . implode('; ', $loaded['errors'])); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }
    return $catalog;
}

/** @return array<string, array<string, mixed>> Published tools, catalog order. */
function omochix_labs_get_tools() {
    return array_filter(omochix_labs_get_catalog()['tools'], static fn($t) => 'published' === $t['status']);
}

/** @return array<string, mixed>|null Published tool or null (drafts are treated as unknown). */
function omochix_labs_get_tool($slug) {
    $tools = omochix_labs_get_tools();
    return is_string($slug) && isset($tools[$slug]) ? $tools[$slug] : null;
}

/** @return array<string, array<string, mixed>> Categories that have at least one published tool. */
function omochix_labs_get_categories() {
    return array_filter(omochix_labs_get_catalog()['categories'], static fn($c) => $c['count'] > 0);
}

function omochix_labs_get_category($slug) {
    $categories = omochix_labs_get_categories();
    return is_string($slug) && isset($categories[$slug]) ? $categories[$slug] : null;
}

/**
 * Curated related tools first, then other tools of the same category, then
 * the rest; never the tool itself.
 *
 * @param array<string, mixed> $tool Tool.
 * @return array<int, array<string, mixed>>
 */
function omochix_labs_related_tools(array $tool, $limit = 4) {
    $tools  = omochix_labs_get_tools();
    $result = [];
    foreach ($tool['related'] as $slug) {
        if (isset($tools[$slug])) {
            $result[$slug] = $tools[$slug];
        }
    }
    foreach ([true, false] as $same_category) {
        foreach ($tools as $slug => $other) {
            if ($slug !== $tool['slug'] && !isset($result[$slug]) && ($other['category'] === $tool['category']) === $same_category) {
                $result[$slug] = $other;
            }
        }
    }
    return array_slice(array_values($result), 0, max(0, (int) $limit));
}

/** Lower-cased text the index search matches against (the browser normalizes it further). */
function omochix_labs_search_text(array $tool) {
    $category = omochix_labs_get_category($tool['category']);
    $parts    = array_merge([$tool['slug'], $tool['name'], $tool['lead'], $category ? $category['name'] : ''], $tool['keywords']);
    $text     = implode(' ', $parts);
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

/**
 * Server-side list filter (the same result the browser shows, so the list
 * works without JavaScript). Keywords are ANDed.
 *
 * @param array{cat?: string, q?: string} $filters Sanitized filters.
 * @return array<string, array<string, mixed>>
 */
function omochix_labs_filter_tools(array $filters) {
    $cat   = (string) ($filters['cat'] ?? '');
    $q     = (string) ($filters['q'] ?? '');
    $q     = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $terms = array_filter(preg_split('/\s+/u', trim($q)) ?: [], 'strlen');
    return array_filter(omochix_labs_get_tools(), static function ($tool) use ($cat, $terms) {
        if ('' !== $cat && $tool['category'] !== $cat) {
            return false;
        }
        $text = omochix_labs_search_text($tool);
        foreach ($terms as $term) {
            if (false === strpos($text, $term)) {
                return false;
            }
        }
        return true;
    });
}

/**
 * @param array<string, mixed> $query Raw query ($_GET).
 * @return array{cat: string, q: string}
 */
function omochix_labs_sanitize_filters(array $query) {
    $cat = isset($query['cat']) && is_string($query['cat']) ? sanitize_key(wp_unslash($query['cat'])) : '';
    $q   = isset($query['q']) && is_string($query['q']) ? sanitize_text_field(wp_unslash($query['q'])) : '';
    $q   = function_exists('mb_substr') ? mb_substr($q, 0, 60, 'UTF-8') : substr($q, 0, 60);
    return ['cat' => omochix_labs_get_category($cat) ? $cat : '', 'q' => $q];
}

/* -------------------------------------------------------------------------
 * Pricing (token-cost-calculator) and crawler list (robots-txt-generator)
 * ---------------------------------------------------------------------- */

function omochix_labs_today() {
    return function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
}

function omochix_labs_days_between($from, $to) {
    return (int) floor((strtotime($to . ' 00:00:00 UTC') - strtotime($from . ' 00:00:00 UTC')) / 86400);
}

function omochix_labs_is_price($value) {
    return (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0 && $value < 100000;
}

/** Whether a URL is https on one of the allowed hosts. */
function omochix_labs_is_allowed_source($url, array $hosts) {
    if (!is_string($url) || 0 !== strpos($url, 'https://')) {
        return false;
    }
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) && in_array(strtolower($host), $hosts, true);
}

/**
 * Why a price row must not be shown ('' when it may be). The same rules as
 * hiddenReason() in assets/js/labs/core/cost.js.
 *
 * @param mixed  $model   Price row.
 * @param string $today   YYYY-MM-DD.
 * @param int    $max_age Maximum age of verified_at in days.
 */
function omochix_labs_price_hidden_reason($model, $today, $max_age) {
    if (!is_array($model)) {
        return 'invalid';
    }
    if ('verified' !== ($model['status'] ?? null)) {
        return 'unverified';
    }
    if (!omochix_labs_is_allowed_source($model['source_url'] ?? null, OMOCHIX_LABS_PRICE_SOURCE_HOSTS)) {
        return 'source';
    }
    $verified = $model['verified_at'] ?? null;
    // One day of slack: a visitor west of Japan can still be on the previous date.
    if (!omochix_labs_is_date($verified) || omochix_labs_days_between($today, $verified) > 1) {
        return 'unverified';
    }
    if (omochix_labs_days_between($verified, $today) > $max_age) {
        return 'stale';
    }
    if (!omochix_labs_is_slug(str_replace('.', '-', (string) ($model['id'] ?? ''))) || !is_string($model['label'] ?? null) || '' === $model['label'] || !is_string($model['provider'] ?? null)) {
        return 'invalid';
    }
    if (!omochix_labs_is_price($model['input'] ?? null) || !omochix_labs_is_price($model['output'] ?? null)) {
        return 'invalid';
    }
    if (null !== ($model['cached_input'] ?? null) && !omochix_labs_is_price($model['cached_input'])) {
        return 'invalid';
    }
    $long = $model['long_context'] ?? null;
    if (null !== $long && (!is_array($long) || !is_int($long['threshold_tokens'] ?? null) || $long['threshold_tokens'] < 1 || !omochix_labs_is_price($long['input'] ?? null) || !omochix_labs_is_price($long['output'] ?? null) || (null !== ($long['cached_input'] ?? null) && !omochix_labs_is_price($long['cached_input'])))) {
        return 'invalid';
    }
    foreach (['valid_from', 'valid_until'] as $key) {
        if (isset($model[$key]) && !omochix_labs_is_date($model[$key])) {
            return 'invalid';
        }
    }
    if (isset($model['valid_from'], $model['valid_until']) && $model['valid_from'] > $model['valid_until']) {
        return 'invalid';
    }
    if (isset($model['valid_until']) && $today > $model['valid_until']) {
        return 'expired';
    }
    if (isset($model['valid_from']) && $today < $model['valid_from']) {
        return 'upcoming';
    }
    return '';
}

/**
 * Price rows the page may use: valid data, verified from an allowed source
 * within max_age_days, not expired. Rows scheduled to start later are kept
 * (with valid_from) so a cached page can switch on the right day; which rows
 * apply on a given day is decided by omochix_labs_pricing_active() here and
 * by active() in cost.js in the browser, with the browser's own date.
 *
 * @param mixed  $data  Decoded pricing.json.
 * @param string $today YYYY-MM-DD.
 * @return array{ok: bool, currency: string, max_age_days: int, providers: array<string, string>, models: array<int, array<string, mixed>>, hidden: array<string, int>}
 */
function omochix_labs_pricing_view($data, $today) {
    $view = ['ok' => false, 'currency' => 'USD', 'max_age_days' => 90, 'providers' => [], 'models' => [], 'hidden' => []];
    if (!is_array($data) || ($data['schema_version'] ?? null) !== OMOCHIX_LABS_PRICING_VERSION || 'USD' !== ($data['currency'] ?? null) || 'per_1m_tokens' !== ($data['unit'] ?? null)) {
        return $view;
    }
    $max_age = (int) ($data['max_age_days'] ?? 0);
    if ($max_age < 1 || $max_age > 90) {
        $max_age = 90; // never longer than the published policy
    }
    $view['ok']           = true;
    $view['max_age_days'] = $max_age;
    foreach ((array) ($data['providers'] ?? []) as $provider) {
        if (is_array($provider) && omochix_labs_is_slug($provider['slug'] ?? null) && is_string($provider['name'] ?? null)) {
            $view['providers'][$provider['slug']] = $provider['name'];
        }
    }
    foreach ((array) ($data['models'] ?? []) as $model) {
        $reason = omochix_labs_price_hidden_reason($model, $today, $max_age);
        if (in_array($reason, ['', 'upcoming'], true) && !isset($view['providers'][$model['provider']])) {
            $reason = 'invalid';
        }
        if ('expired' === $reason) {
            continue; // a scheduled price that has ended is expected, not a data problem
        }
        if (!in_array($reason, ['', 'upcoming'], true)) {
            $view['hidden'][$reason] = ($view['hidden'][$reason] ?? 0) + 1;
            continue;
        }
        $long             = $model['long_context'] ?? null;
        $view['models'][] = [
            'id'           => $model['id'],
            'provider'     => $model['provider'],
            'label'        => $model['label'],
            'input'        => (float) $model['input'],
            'output'       => (float) $model['output'],
            'cached_input' => null === ($model['cached_input'] ?? null) ? null : (float) $model['cached_input'],
            'long_context' => is_array($long) ? ['threshold_tokens' => (int) $long['threshold_tokens'], 'input' => (float) $long['input'], 'output' => (float) $long['output'], 'cached_input' => null === ($long['cached_input'] ?? null) ? null : (float) $long['cached_input']] : null,
            'valid_from'   => $model['valid_from'] ?? null,
            'valid_until'  => $model['valid_until'] ?? null,
            'source_url'   => $model['source_url'],
            'verified_at'  => $model['verified_at'],
        ];
    }
    return $view;
}

/**
 * Rows that apply on $today: inside their date window and verified within
 * max_age_days. When two rows for one model both apply, neither is certain
 * and both are dropped. Mirrors active() in assets/js/labs/core/cost.js.
 *
 * @param array<int, array<string, mixed>> $models  pricing_view()['models'].
 * @return array<int, array<string, mixed>>
 */
function omochix_labs_pricing_active(array $models, $today, $max_age) {
    $rows = array_values(array_filter($models, static function ($m) use ($today, $max_age) {
        if (!empty($m['valid_from']) && $today < $m['valid_from']) {
            return false;
        }
        if (!empty($m['valid_until']) && $today > $m['valid_until']) {
            return false;
        }
        return omochix_labs_days_between($today, $m['verified_at']) <= 1 && omochix_labs_days_between($m['verified_at'], $today) <= $max_age;
    }));
    $counts = array_count_values(array_column($rows, 'id'));
    return array_values(array_filter($rows, static fn($m) => 1 === $counts[$m['id']]));
}

/**
 * Whether a person approved pricing.json (review.status "approved" with a
 * reviewer and a date) — the same rule as `tests/labs/data.js --release`.
 */
function omochix_labs_pricing_approved($data) {
    $review = is_array($data) && is_array($data['review'] ?? null) ? $data['review'] : [];
    return 'approved' === ($review['status'] ?? null)
        && is_string($review['reviewer'] ?? null) && '' !== trim($review['reviewer'])
        && omochix_labs_is_date($review['reviewed_at'] ?? null);
}

/** Production unless WP_ENVIRONMENT_TYPE says otherwise (WordPress's own default). */
function omochix_labs_is_production() {
    return !function_exists('wp_get_environment_type') || 'production' === wp_get_environment_type();
}

/**
 * Prices for the page. Release gate: on a production site no price is shown
 * until pricing.json is approved (local / development / staging show them so
 * they can be reviewed); `withheld` tells the templates why the list is empty.
 */
function omochix_labs_get_pricing() {
    static $view = null;
    if (null === $view) {
        $read = omochix_labs_read_json(omochix_labs_data_file('pricing'));
        $data = $read['ok'] ? $read['data'] : null;
        $view = omochix_labs_pricing_view($data, omochix_labs_today());
        $view['approved'] = omochix_labs_pricing_approved($data);
        $view['withheld'] = !$view['approved'] && omochix_labs_is_production();
        if ($view['withheld']) {
            $view['models'] = [];
        }
    }
    return $view;
}

/**
 * Crawler rows with an https source checked within max_age_days.
 *
 * @param mixed  $data  Decoded crawlers.json.
 * @param string $today YYYY-MM-DD.
 * @return array{purposes: array<string, string>, crawlers: array<int, array<string, string>>}
 */
function omochix_labs_crawlers_view($data, $today) {
    $view = ['purposes' => [], 'crawlers' => []];
    if (!is_array($data) || ($data['schema_version'] ?? null) !== OMOCHIX_LABS_CRAWLERS_VERSION) {
        return $view;
    }
    $max_age = max(1, min(365, (int) ($data['max_age_days'] ?? 180)));
    foreach ((array) ($data['purposes'] ?? []) as $key => $label) {
        if (in_array($key, ['training', 'search', 'user'], true) && is_string($label)) {
            $view['purposes'][$key] = $label;
        }
    }
    $seen = [];
    foreach ((array) ($data['crawlers'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $token    = $row['token'] ?? null;
        $verified = $row['verified_at'] ?? null;
        if (!is_string($token) || 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_-]{1,40}$/', $token) || isset($seen[strtolower($token)])) {
            continue;
        }
        if (!isset($view['purposes'][$row['purpose'] ?? '']) || !is_string($row['operator'] ?? null) || !is_string($row['note'] ?? null)) {
            continue;
        }
        if (!is_string($row['source_url'] ?? null) || 0 !== strpos($row['source_url'], 'https://') || !omochix_labs_is_date($verified) || omochix_labs_days_between($today, $verified) > 1 || omochix_labs_days_between($verified, $today) > $max_age) {
            continue;
        }
        $seen[strtolower($token)] = true;
        $view['crawlers'][]       = [
            'token'       => $token,
            'operator'    => $row['operator'],
            'purpose'     => $row['purpose'],
            'note'        => $row['note'],
            'robots_note' => is_string($row['robots_note'] ?? null) ? $row['robots_note'] : '',
            'source_url'  => $row['source_url'],
            'verified_at' => $verified,
        ];
    }
    return $view;
}

function omochix_labs_get_crawlers() {
    static $view = null;
    if (null === $view) {
        $read = omochix_labs_read_json(omochix_labs_data_file('crawlers'));
        $view = omochix_labs_crawlers_view($read['ok'] ? $read['data'] : null, omochix_labs_today());
    }
    return $view;
}

/* -------------------------------------------------------------------------
 * Routing
 * ---------------------------------------------------------------------- */

/** ID of the published "labs" page, or 0 (then Labs is off). */
function omochix_labs_page_id() {
    static $page_id = null;
    if (null !== $page_id) {
        return $page_id;
    }
    $page    = get_page_by_path(OMOCHIX_LABS_PATH, OBJECT, 'page');
    $page_id = ($page instanceof WP_Post && 'publish' === $page->post_status && 0 === (int) $page->post_parent) ? (int) $page->ID : 0;
    return $page_id;
}

/**
 * Public URL of the list, a tool, or a filtered list. Empty when the labs
 * page is not published.
 *
 * @param array<string, mixed>|null $tool    Tool, or null for the list.
 * @param array<string, string>     $filters List filters.
 */
function omochix_labs_url($tool = null, array $filters = []) {
    $page_id = omochix_labs_page_id();
    if (!$page_id) {
        return '';
    }
    $base = trailingslashit((string) get_permalink($page_id));
    if (is_array($tool) && isset($tool['slug'])) {
        return user_trailingslashit($base . $tool['slug']);
    }
    $args = array_filter($filters, static fn($v) => '' !== $v && null !== $v);
    return $args ? add_query_arg(array_map('rawurlencode', $args), $base) : $base;
}

function omochix_labs_register_query_vars($vars) {
    $vars[] = OMOCHIX_LABS_QUERY_VAR;
    $vars[] = OMOCHIX_LABS_SITEMAP_VAR;
    return $vars;
}
add_filter('query_vars', 'omochix_labs_register_query_vars');

function omochix_labs_register_rewrite_rules() {
    add_rewrite_rule(
        '^' . preg_quote(OMOCHIX_LABS_PATH, '#') . '/([a-z0-9-]+)/?$',
        'index.php?pagename=' . OMOCHIX_LABS_PATH . '&' . OMOCHIX_LABS_QUERY_VAR . '=$matches[1]',
        'top'
    );
    add_rewrite_rule('^' . preg_quote(OMOCHIX_LABS_SITEMAP_PATH, '#') . '$', 'index.php?' . OMOCHIX_LABS_SITEMAP_VAR . '=1', 'top');
}
add_action('init', 'omochix_labs_register_rewrite_rules');

/** Flush once per rule version (a theme update does not fire after_switch_theme). */
function omochix_labs_maybe_flush_rewrite_rules() {
    if (get_option('omochix_labs_rewrite_version') === OMOCHIX_LABS_REWRITE_VERSION) {
        return;
    }
    flush_rewrite_rules(false);
    update_option('omochix_labs_rewrite_version', OMOCHIX_LABS_REWRITE_VERSION);
}
add_action('init', 'omochix_labs_maybe_flush_rewrite_rules', 22);
add_action('after_switch_theme', static function () {
    delete_option('omochix_labs_rewrite_version');
});

/** Whether the main query is the labs page (list or tool). */
function omochix_labs_is_request() {
    if (!is_page()) {
        return false;
    }
    $page_id = omochix_labs_page_id();
    return $page_id && get_queried_object_id() === $page_id;
}

/** Requested tool slug ('' on the list). Upper-case or odd slugs never match a tool. */
function omochix_labs_requested_slug() {
    $slug = get_query_var(OMOCHIX_LABS_QUERY_VAR, '');
    return is_string($slug) ? $slug : '';
}

/** @return array<string, mixed>|null The tool being viewed. */
function omochix_labs_current_tool() {
    if (!omochix_labs_is_request()) {
        return null;
    }
    $slug = omochix_labs_requested_slug();
    return '' !== $slug ? omochix_labs_get_tool($slug) : null;
}

/** True on the list when a filter is active (such URLs are noindex and canonical to the list). */
function omochix_labs_is_filtered_list() {
    if (!omochix_labs_is_request() || '' !== omochix_labs_requested_slug()) {
        return false;
    }
    $filters = omochix_labs_sanitize_filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter state.
    return '' !== implode('', $filters);
}

/** Unknown or draft tool -> real 404; Labs pages get the security headers. */
function omochix_labs_template_redirect() {
    if (!omochix_labs_is_request()) {
        return;
    }
    $slug = omochix_labs_requested_slug();
    if ('' !== $slug && !omochix_labs_get_tool($slug)) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        return;
    }
    omochix_labs_send_security_headers();
}
add_action('template_redirect', 'omochix_labs_template_redirect');

function omochix_labs_template_include($template) {
    if (!omochix_labs_is_request() || is_404()) {
        return $template;
    }
    $labs = get_theme_file_path('/page-labs.php');
    return file_exists($labs) ? $labs : $template;
}
add_filter('template_include', 'omochix_labs_template_include', 9);

/* -------------------------------------------------------------------------
 * XML sitemap of the tool pages ("/labs-sitemap.xml")
 *
 * Slim SEO has no hook for URLs that are not posts, so the tool pages get a
 * sitemap of their own, announced in robots.txt. The list page "/labs/" is a
 * normal page and already in Slim SEO's page sitemap, so it is not repeated
 * here. Slim SEO's sitemaps are not touched.
 * ---------------------------------------------------------------------- */

/** Public sitemap URL, or '' while the labs page is not published. */
function omochix_labs_sitemap_url() {
    return omochix_labs_page_id() ? home_url('/' . OMOCHIX_LABS_SITEMAP_PATH) : '';
}

/** @return array<int, array{loc: string, lastmod: string}> Canonical URLs of the published tools. */
function omochix_labs_sitemap_entries() {
    $entries = [];
    foreach (omochix_labs_get_tools() as $tool) {
        $loc = omochix_labs_url($tool);
        if ('' !== $loc) {
            $entries[] = ['loc' => $loc, 'lastmod' => $tool['updated']];
        }
    }
    return $entries;
}

function omochix_labs_xml_escape($text) {
    return htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<int, array{loc: string, lastmod: string}> $entries */
function omochix_labs_sitemap_xml(array $entries) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($entries as $entry) {
        $xml .= "\t<url>\n\t\t<loc>" . omochix_labs_xml_escape($entry['loc']) . "</loc>\n";
        if (omochix_labs_is_date($entry['lastmod'])) {
            $xml .= "\t\t<lastmod>" . $entry['lastmod'] . "</lastmod>\n";
        }
        $xml .= "\t</url>\n";
    }
    return $xml . "</urlset>\n";
}

/**
 * Response headers. Last-Modified follows catalog.json (it only changes with
 * a theme release, when the page cache is purged anyway); one hour of
 * caching keeps LiteSpeed and browsers in step with that.
 *
 * @param int $modified Unix time of the last catalog change.
 * @return array<string, string>
 */
function omochix_labs_sitemap_headers($modified) {
    return [
        'Content-Type'           => 'application/xml; charset=UTF-8',
        'X-Robots-Tag'           => 'noindex, follow',
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control'          => 'public, max-age=3600',
        'Last-Modified'          => gmdate('D, d M Y H:i:s', (int) $modified) . ' GMT',
    ];
}

/** Whether an If-Modified-Since request header lets us answer 304. */
function omochix_labs_sitemap_not_modified($if_modified_since, $modified) {
    if (!is_string($if_modified_since) || '' === $if_modified_since) {
        return false;
    }
    $since = strtotime($if_modified_since);
    return false !== $since && $since >= (int) $modified;
}

function omochix_labs_sitemap_modified() {
    $file = omochix_labs_data_file('catalog');
    return is_file($file) ? (int) filemtime($file) : time();
}

/** A sitemap request while the labs page is not published is a normal 404. */
add_filter('request', static function ($query_vars) {
    if (!empty($query_vars[OMOCHIX_LABS_SITEMAP_VAR]) && !omochix_labs_sitemap_url()) {
        return ['error' => '404'];
    }
    return $query_vars;
});

/**
 * Print the sitemap. Returns the HTTP status sent, so the caller decides
 * whether to stop WordPress (the parse_request hook) or not (tests).
 */
function omochix_labs_send_sitemap($if_modified_since, $method = 'GET') {
    $modified = omochix_labs_sitemap_modified();
    $status   = omochix_labs_sitemap_not_modified($if_modified_since, $modified) ? 304 : 200;
    status_header($status);
    if (!headers_sent()) {
        foreach (omochix_labs_sitemap_headers($modified) as $name => $value) {
            header($name . ': ' . $value);
        }
    }
    if (200 === $status && 'HEAD' !== strtoupper((string) $method)) {
        echo omochix_labs_sitemap_xml(omochix_labs_sitemap_entries()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in omochix_labs_sitemap_xml().
    }
    return $status;
}

/** Answer before the main query and canonical redirects run. */
add_action('parse_request', static function ($wp) {
    if (!is_object($wp) || empty($wp->query_vars[OMOCHIX_LABS_SITEMAP_VAR]) || !omochix_labs_sitemap_url()) {
        return;
    }
    omochix_labs_send_sitemap(
        isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? wp_unslash($_SERVER['HTTP_IF_MODIFIED_SINCE']) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only parsed as a date.
        isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared to "HEAD" only.
    );
    exit;
}, 1);

/**
 * Add the Labs sitemap to robots.txt, after Slim SEO (priority 9999, which may
 * replace the whole file with the admin-edited text). Existing lines are kept;
 * nothing is added when the site discourages search engines, when the labs
 * page is unpublished, or when the line is already there.
 */
function omochix_labs_robots_txt($output, $public, $sitemap_url) {
    $output = (string) $output;
    if (!$public || '' === $sitemap_url || false !== stripos($output, $sitemap_url)) {
        return $output;
    }
    return rtrim($output, "\r\n") . "\nSitemap: " . $sitemap_url . "\n";
}
add_filter('robots_txt', static function ($output, $public) {
    return omochix_labs_robots_txt($output, (bool) $public, omochix_labs_sitemap_url());
}, 10000, 2);

/* -------------------------------------------------------------------------
 * Security headers (Labs pages only)
 * ---------------------------------------------------------------------- */

/**
 * Report-Only CSP plus two hardening headers. Report-Only first: it only
 * logs violations in the browser console, so a missed WordPress or plugin
 * source cannot break the page (docs/labs/LABS-C1-PLAN.md §8). There is no
 * report endpoint by design.
 *
 * @return array<string, string>
 */
function omochix_labs_security_headers() {
    $ga = 'https://www.googletagmanager.com https://*.googletagmanager.com https://*.google-analytics.com https://*.analytics.google.com https://*.g.doubleclick.net';
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob: https://s.w.org https://secure.gravatar.com " . $ga,
        "font-src 'self' data:",
        "connect-src 'self' " . $ga,
        "worker-src 'self'",
        "frame-src 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
    ]);
    return [
        'Content-Security-Policy-Report-Only' => $csp,
        'X-Content-Type-Options'             => 'nosniff',
        'Referrer-Policy'                    => 'strict-origin-when-cross-origin',
    ];
}

/**
 * Headers to add given those already queued: never replaces one that
 * WordPress, a plugin or the server config already set, so an existing
 * (possibly stricter) policy is not weakened.
 *
 * @param string[] $sent headers_list() output.
 * @return array<string, string>
 */
function omochix_labs_headers_to_send(array $sent) {
    $present = [];
    foreach ($sent as $line) {
        $name = strtolower(trim((string) strstr((string) $line, ':', true)));
        if ('' !== $name) {
            $present[$name] = true;
        }
    }
    $add = [];
    foreach (omochix_labs_security_headers() as $name => $value) {
        $lower = strtolower($name);
        if (isset($present[$lower])) {
            continue;
        }
        // An enforced CSP already present wins over adding a Report-Only one.
        if ('content-security-policy-report-only' === $lower && isset($present['content-security-policy'])) {
            continue;
        }
        $add[$name] = $value;
    }
    return $add;
}

function omochix_labs_send_security_headers() {
    if (headers_sent()) {
        return;
    }
    foreach (omochix_labs_headers_to_send(headers_list()) as $name => $value) {
        header($name . ': ' . $value, false);
    }
}

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

/** Core modules each tool needs (pure UMD files under assets/js/labs/core/). */
function omochix_labs_tool_modules() {
    return [
        'json-formatter'        => ['json'],
        'text-diff'             => ['diff'],
        'robots-txt-generator'  => ['robots'],
        'token-cost-calculator' => ['tokens', 'cost'],
    ];
}

function omochix_labs_asset_version($relative) {
    $file = get_theme_file_path($relative);
    return file_exists($file) ? (string) filemtime($file) : wp_get_theme()->get('Version');
}

function omochix_labs_enqueue_assets() {
    if (!omochix_labs_is_request() || is_404()) {
        return;
    }
    $tool = omochix_labs_current_tool();
    $deps = wp_style_is('omochix-shell', 'enqueued') ? ['omochix-style', 'omochix-shell'] : ['omochix-style'];
    wp_enqueue_style('omochix-labs', get_theme_file_uri('/assets/css/labs.css'), $deps, omochix_labs_asset_version('/assets/css/labs.css'));

    $script = static function ($handle, $relative, array $script_deps) {
        wp_enqueue_script($handle, get_theme_file_uri($relative), $script_deps, omochix_labs_asset_version($relative), true);
    };
    $script('omochix-labs-text', '/assets/js/labs/core/text.js', []);
    $script('omochix-labs-events', '/assets/js/labs/core/events.js', []);
    $script('omochix-labs-shared', '/assets/js/labs/shared.js', ['omochix-labs-text', 'omochix-labs-events']);

    $config = [
        'tool'     => $tool ? $tool['slug'] : null,
        'category' => $tool ? $tool['category'] : null,
        'limits'   => omochix_labs_limits(),
        'strings'  => [
            'copied'     => __('コピーしました', 'omochix'),
            'copyFailed' => __('コピーできませんでした。テキストを選択してコピーしてください。', 'omochix'),
            'reset'      => __('入力を消去しました', 'omochix'),
            'sample'     => __('入力例を入れました', 'omochix'),
            'working'    => __('処理しています…', 'omochix'),
            'tooLarge'   => __('入力が大きすぎます（上限 %s）。分割してお試しください。', 'omochix'),
            'timeout'    => __('処理が10秒を超えたため中断しました。入力を小さくしてお試しください。', 'omochix'),
            'internal'   => __('処理中に問題が起きました。ページを再読み込みしてお試しください。', 'omochix'),
            'count'      => __('%s件のツール', 'omochix'),
            'noResults'  => __('条件に合うツールがありません。キーワードを変えるか、カテゴリを「すべて」に戻してください。', 'omochix'),
        ],
    ];

    if (!$tool) {
        $script('omochix-labs-index', '/assets/js/labs/ui/index.js', ['omochix-labs-shared']);
        wp_add_inline_script('omochix-labs-shared', 'window.omochixLabs = ' . omochix_labs_json_for_script($config) . ';', 'before');
        return;
    }

    // The Worker imports the same files: text, the tool's modules, then ops.
    $modules = array_merge(omochix_labs_tool_modules()[$tool['slug']] ?? [], ['ops']);
    $deps    = ['omochix-labs-shared'];
    $workers = [get_theme_file_uri('/assets/js/labs/core/text.js') . '?ver=' . omochix_labs_asset_version('/assets/js/labs/core/text.js')];
    foreach ($modules as $module) {
        $relative = '/assets/js/labs/core/' . $module . '.js';
        $script('omochix-labs-' . $module, $relative, 'ops' === $module ? $deps : ['omochix-labs-text']);
        $deps[]    = 'omochix-labs-' . $module;
        $workers[] = get_theme_file_uri($relative) . '?ver=' . omochix_labs_asset_version($relative);
    }
    $script('omochix-labs-tool', '/assets/js/labs/ui/' . $tool['slug'] . '.js', $deps);

    $config['worker'] = [
        'url'     => get_theme_file_uri('/assets/js/labs/worker.js') . '?ver=' . omochix_labs_asset_version('/assets/js/labs/worker.js'),
        'scripts' => $workers,
    ];
    if ('token-cost-calculator' === $tool['slug']) {
        $config['pricing'] = omochix_labs_get_pricing();
    }
    if ('robots-txt-generator' === $tool['slug']) {
        $config['crawlers'] = omochix_labs_get_crawlers();
    }
    wp_add_inline_script('omochix-labs-shared', 'window.omochixLabs = ' . omochix_labs_json_for_script($config) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'omochix_labs_enqueue_assets', 20);

/** JSON safe to inline in a <script> element (no "</script>", no HTML-significant characters). */
function omochix_labs_json_for_script($data) {
    return (string) wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

add_filter('body_class', static function ($classes) {
    if (omochix_labs_is_request() && !is_404()) {
        $classes[] = 'omx-labs';
    }
    return $classes;
});

/* -------------------------------------------------------------------------
 * SEO: title, description, canonical, robots, Open Graph, breadcrumbs
 * ---------------------------------------------------------------------- */

function omochix_labs_index_title() {
    return __('OmochiX Labs｜AI・開発・Web運用の無料オンラインツール', 'omochix');
}

function omochix_labs_index_description() {
    $names = array_column(omochix_labs_get_tools(), 'name');
    return sprintf(
        /* translators: %s: tool names joined with "・" */
        __('%sなど、AI・開発・Web運用に役立つ無料ツール集。すべてブラウザの中で動き、入力した内容は外部に送信されません。', 'omochix'),
        implode('・', $names)
    );
}

/** Full <title>/og:title for a Labs request ('' when not on Labs). */
function omochix_labs_meta_title() {
    if (!omochix_labs_is_request() || is_404()) {
        return '';
    }
    $tool = omochix_labs_current_tool();
    return $tool ? $tool['title'] . ' | OmochiX Labs' : omochix_labs_index_title();
}

function omochix_labs_meta_description() {
    if (!omochix_labs_is_request() || is_404()) {
        return '';
    }
    $tool = omochix_labs_current_tool();
    return $tool ? $tool['description'] : omochix_labs_index_description();
}

/** Canonical URL: the tool URL, or the bare list URL (filters dropped). */
function omochix_labs_canonical() {
    if (!omochix_labs_is_request() || is_404()) {
        return '';
    }
    $tool = omochix_labs_current_tool();
    return omochix_labs_url($tool);
}

function omochix_labs_og_image() {
    $tool     = omochix_labs_current_tool();
    $relative = '/assets/img/labs/og/' . ($tool ? $tool['slug'] : 'index') . '.png';
    return file_exists(get_theme_file_path($relative)) ? get_theme_file_uri($relative) : '';
}

add_filter('pre_get_document_title', static function ($title) {
    $labs = omochix_labs_meta_title();
    return '' !== $labs ? $labs : $title;
}, 20);
add_filter('slim_seo_meta_title', static function ($title) {
    $labs = omochix_labs_meta_title();
    return '' !== $labs ? $labs : $title;
}, 20);
add_filter('slim_seo_meta_description', static function ($description) {
    $labs = omochix_labs_meta_description();
    return '' !== $labs ? $labs : $description;
}, 20, 1);
add_filter('slim_seo_canonical_url', static function ($url) {
    $labs = omochix_labs_canonical();
    return '' !== $labs ? $labs : $url;
}, 20);
add_filter('slim_seo_robots_index', static function ($indexed) {
    return omochix_labs_is_filtered_list() ? false : $indexed;
}, 20);
add_filter('wp_robots', static function ($robots) {
    if (omochix_labs_is_filtered_list()) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }
    return $robots;
});

/** Open Graph / X card values Slim SEO prints (it reads them through these filters). */
foreach (['title', 'type', 'description', 'url', 'image', 'image_width', 'image_height', 'image_alt'] as $omochix_labs_og_key) {
    add_filter('slim_seo_open_graph_' . $omochix_labs_og_key, static function ($value) use ($omochix_labs_og_key) {
        return omochix_labs_open_graph_value($omochix_labs_og_key, $value);
    }, 20);
}
unset($omochix_labs_og_key);
add_filter('slim_seo_twitter_card_image', static function ($value) {
    return omochix_labs_open_graph_value('image', $value);
}, 20);

/**
 * Labs value for a Slim SEO Open Graph field ($value unchanged off Labs).
 * The image size and alt are only replaced together with the Labs image.
 */
function omochix_labs_open_graph_value($key, $value) {
    if (!omochix_labs_is_request() || is_404()) {
        return $value;
    }
    $image = omochix_labs_og_image();
    switch ($key) {
        case 'title':
            return omochix_labs_meta_title();
        case 'type':
            return 'website';
        case 'description':
            return omochix_labs_meta_description();
        case 'url':
            return omochix_labs_canonical() ?: $value;
        case 'image':
            return $image ?: $value;
        case 'image_width':
            return $image ? 1200 : $value;
        case 'image_height':
            return $image ? 630 : $value;
        case 'image_alt':
            $tool = omochix_labs_current_tool();
            return $image ? ($tool ? $tool['name'] . ' | OmochiX Labs' : 'OmochiX Labs') : $value;
    }
    return $value;
}

/**
 * Breadcrumb trail Slim SEO renders and turns into its BreadcrumbList (the
 * only BreadcrumbList on the page): on a tool, the list becomes a link and the
 * tool name the current page.
 *
 * @param array<int, array{url: string, text: string}> $links Trail so far.
 */
function omochix_labs_breadcrumb_links(array $links, $tool, $list_url, $list_title) {
    if (!$tool || !$list_url) {
        return $links;
    }
    $last = $links ? end($links) : null;
    if (!$last || !isset($last['url']) || untrailingslashit($last['url']) !== untrailingslashit($list_url)) {
        $links[] = ['url' => $list_url, 'text' => $list_title];
    }
    return $links;
}
add_filter('slim_seo_breadcrumbs_links', static function ($links) {
    $tool = omochix_labs_current_tool();
    if (!$tool || !is_array($links)) {
        return $links;
    }
    return omochix_labs_breadcrumb_links($links, $tool, omochix_labs_url(), get_the_title(omochix_labs_page_id()));
});
add_filter('single_post_title', static function ($title, $post) {
    $tool = omochix_labs_current_tool();
    if ($tool && $post instanceof WP_Post && (int) $post->ID === omochix_labs_page_id()) {
        return $tool['name'];
    }
    return $title;
}, 10, 2);

/* -------------------------------------------------------------------------
 * Structured data (added to Slim SEO's graph, never a second graph)
 * ---------------------------------------------------------------------- */

/**
 * Rework Slim SEO's graph for a Labs URL: the list's WebPage becomes a
 * CollectionPage whose mainEntity is an ItemList of the tools; on a tool the
 * WebPage / BreadcrumbList / ReadAction ids and urls move to the tool URL and
 * a WebApplication is added. Other entities (WebSite, Organization, …) are
 * left untouched, and nothing is duplicated.
 *
 * @param array<int, mixed>          $graph Slim SEO graph.
 * @param array<string, mixed>       $ctx   ['list_url', 'tools' => [...], 'tool' => ?array, 'tool_url', 'name', 'description'].
 * @return array<int, mixed>
 */
function omochix_labs_schema_graph(array $graph, array $ctx) {
    $list_url = (string) $ctx['list_url'];
    $tool     = $ctx['tool'] ?? null;
    $page_url = $tool ? (string) $ctx['tool_url'] : $list_url;
    $old_ids  = [];
    $has_page = false;

    // Labs owns the application entities on its own URLs.
    $graph = array_values(array_filter($graph, static function ($entity) {
        $types = is_array($entity) && isset($entity['@type']) ? (array) $entity['@type'] : [];
        return !array_intersect(['WebApplication', 'SoftwareApplication', 'ItemList', 'CollectionPage'], $types);
    }));

    foreach ($graph as $i => $entity) {
        if (!is_array($entity) || !isset($entity['@type'])) {
            continue;
        }
        $type = $entity['@type'];
        if ('WebPage' === $type) {
            $has_page = true;
            $old_ids[(string) ($entity['@id'] ?? '')] = $page_url . '#webpage';
            $entity['@id']         = $page_url . '#webpage';
            $entity['url']         = $page_url;
            $entity['name']        = (string) $ctx['name'];
            $entity['description'] = (string) $ctx['description'];
            if ($tool) {
                $entity['mainEntity'] = ['@id' => $page_url . '#webapplication'];
                $entity['dateModified'] = $tool['updated'];
            } else {
                $entity['@type']      = 'CollectionPage';
                $entity['mainEntity'] = ['@id' => $list_url . '#itemlist'];
            }
            $graph[$i] = $entity;
        } elseif ($tool && in_array($type, ['BreadcrumbList', 'ReadAction'], true)) {
            $suffix = 'BreadcrumbList' === $type ? '#breadcrumblist' : '#readaction';
            $old_ids[(string) ($entity['@id'] ?? '')] = $page_url . $suffix;
            $entity['@id'] = $page_url . $suffix;
            if ('ReadAction' === $type) {
                $entity['target'] = $page_url;
            }
            $graph[$i] = $entity;
        }
    }
    if (!$has_page) {
        return $graph;
    }
    // Repoint references (WebPage.breadcrumb, potentialAction) at the moved ids.
    foreach ($graph as $i => $entity) {
        if (!is_array($entity)) {
            continue;
        }
        foreach ($entity as $key => $value) {
            if (is_array($value) && isset($value['@id']) && isset($old_ids[$value['@id']]) && '' !== $value['@id']) {
                $graph[$i][$key]['@id'] = $old_ids[$value['@id']];
            }
        }
    }

    if ($tool) {
        $graph[] = [
            '@type'               => 'WebApplication',
            '@id'                 => $page_url . '#webapplication',
            'name'                => $tool['name'],
            'url'                 => $page_url,
            'description'         => $tool['description'],
            'applicationCategory' => $tool['application_category'],
            'operatingSystem'     => 'Any',
            'browserRequirements' => 'Requires JavaScript. Works in current versions of Chrome, Safari, Firefox and Edge.',
            'inLanguage'          => 'ja',
            'isAccessibleForFree' => true,
            'featureList'         => $tool['features'],
            'dateModified'        => $tool['updated'],
            'offers'              => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'JPY'],
            'publisher'           => ['@id' => home_url('/') . '#organization'],
            'isPartOf'            => ['@id' => $list_url . '#webpage'],
        ];
        return $graph;
    }

    $items = [];
    foreach (array_values($ctx['tools']) as $n => $item) {
        $items[] = ['@type' => 'ListItem', 'position' => $n + 1, 'name' => $item['name'], 'url' => $item['url']];
    }
    $graph[] = [
        '@type'           => 'ItemList',
        '@id'             => $list_url . '#itemlist',
        'name'            => 'OmochiX Labs',
        'numberOfItems'   => count($items),
        'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
        'itemListElement' => $items,
    ];
    return $graph;
}

add_filter('slim_seo_schema_graph', static function ($graph) {
    if (!is_array($graph) || !omochix_labs_is_request() || is_404()) {
        return $graph;
    }
    $list_url = omochix_labs_url();
    if (!$list_url) {
        return $graph;
    }
    $tool  = omochix_labs_current_tool();
    $tools = [];
    foreach (omochix_labs_get_tools() as $item) {
        $tools[] = ['name' => $item['name'], 'url' => omochix_labs_url($item)];
    }
    return omochix_labs_schema_graph($graph, [
        'list_url'    => $list_url,
        'tool'        => $tool,
        'tool_url'    => $tool ? omochix_labs_url($tool) : '',
        'tools'       => $tools,
        'name'        => omochix_labs_meta_title(),
        'description' => omochix_labs_meta_description(),
    ]);
}, 30);

/* -------------------------------------------------------------------------
 * Links into the rest of the site (resolved against published content only)
 * ---------------------------------------------------------------------- */

/**
 * @param array<string, mixed> $tool Tool.
 * @return array<string, array<int, array{url: string, title: string, type: string, slug: string}>>
 */
function omochix_labs_site_links(array $tool) {
    $out = ['learn' => [], 'ai_tools' => [], 'prompts' => []];
    foreach ($tool['links']['learn'] as $path) {
        $page = get_page_by_path($path, OBJECT, 'page');
        if ($page instanceof WP_Post && 'publish' === $page->post_status && '' === (string) ($page->post_password ?? '')) {
            $out['learn'][] = ['url' => (string) get_permalink($page), 'title' => get_the_title($page), 'type' => 'learn', 'slug' => basename($path)];
        }
    }
    foreach (['ai_tools' => 'ai_tool', 'prompts' => 'prompt'] as $key => $post_type) {
        if (!$tool['links'][$key] || !post_type_exists($post_type)) {
            continue;
        }
        $posts = get_posts([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'has_password'   => false,
            'post_name__in'  => $tool['links'][$key],
            'posts_per_page' => 6,
            'orderby'        => 'post_name__in',
            'no_found_rows'  => true,
        ]);
        foreach ($posts as $post) {
            $out[$key][] = ['url' => (string) get_permalink($post), 'title' => get_the_title($post), 'type' => 'ai_tools' === $key ? 'ai_tool' : 'prompt', 'slug' => $post->post_name];
        }
    }
    return $out;
}

/** Footer link (only while the labs page is published), placed before "About". */
add_filter('omochix_editorial_footer_links', static function ($links) {
    $url = omochix_labs_url();
    if (!$url || !is_array($links)) {
        return $links;
    }
    $item = ['label' => 'Labs', 'icon' => '', 'url' => $url];
    foreach ($links as $i => $link) {
        if (isset($link['label']) && 'About' === $link['label']) {
            array_splice($links, $i, 0, [$item]);
            return $links;
        }
    }
    $links[] = $item;
    return $links;
});
