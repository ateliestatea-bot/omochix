<?php
/**
 * Industry Hub (Phase 2): routing, validation, SEO and data for
 * /learn/industries/ and /learn/industries/{industry}/.
 * docs/Industry-Hub-Phase2-Design.md is the specification (§ numbers below).
 *
 * The data model and visibility rules live in the OmochiX Core plugin; this
 * file only turns hubs public when that plugin provides them, so a theme
 * update on a site with an older plugin changes nothing.
 *
 * Decisions (§4.1) are made once per request by omochix_industry_hub_decide()
 * and applied on pre_handle_404 (before core's paged 404) and on
 * template_redirect priority 0 (before redirect_canonical and before HEAD
 * requests stop), never after output has started.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

const OMOCHIX_INDUSTRY_HUB_PAGE_PATH = 'learn/industries';
const OMOCHIX_INDUSTRY_HUB_PER_PAGE  = 12;
const OMOCHIX_INDUSTRY_HUB_Q_MAX     = 60;
const OMOCHIX_INDUSTRY_HUB_SECTION   = 6;

/** Whether the Core plugin provides the Phase 2 data layer. */
function omochix_industry_hub_enabled() {
    return function_exists('omochix_core_industry_visibility') && function_exists('omochix_core_get_industry_meta') && taxonomy_exists('omx_industry');
}

/**
 * D-5: turn hubs public — only with a plugin that also ships the Phase 2
 * visibility and leak protection (an older plugin keeps hubs private).
 */
function omochix_industry_hub_public_switch($public) {
    return function_exists('omochix_core_industry_visibility') ? true : $public;
}
add_filter('omochix_core_industry_public', 'omochix_industry_hub_public_switch');

/* -------------------------------------------------------------------------
 * Request detection
 * ---------------------------------------------------------------------- */

/** The published hub index page (D-1), or null. */
function omochix_industry_hub_page() {
    static $page = false;
    if (false === $page) {
        $found = get_page_by_path(OMOCHIX_INDUSTRY_HUB_PAGE_PATH, OBJECT, 'page');
        $page  = $found instanceof WP_Post && 'publish' === $found->post_status ? $found : null;
    }
    return $page;
}

/** Index page URL ('' when the page does not exist). */
function omochix_industry_hub_index_url() {
    $page = omochix_industry_hub_page();
    return $page ? (string) get_permalink($page) : '';
}

/**
 * Whether the main request is the index page. Decided from the queried page
 * itself (no lookup query), so other requests do not pay for it.
 */
function omochix_industry_hub_is_index_request() {
    if (!is_page()) {
        return false;
    }
    $page = get_queried_object();
    return $page instanceof WP_Post && 'industries' === $page->post_name && 'publish' === $page->post_status && OMOCHIX_INDUSTRY_HUB_PAGE_PATH === get_page_uri($page);
}

/**
 * A hub request is an industry term archive that is not a search: a search
 * narrowed by ?omx_industry= is a search page (the plugin's tax guard makes a
 * hidden industry match nothing there, exactly like a missing one).
 */
function omochix_industry_hub_is_hub_request() {
    if (!omochix_industry_hub_enabled() || !is_tax('omx_industry') || is_search()) {
        return false;
    }
    $term = get_queried_object();
    return $term instanceof WP_Term && 'omx_industry' === $term->taxonomy;
}

function omochix_industry_hub_is_request() {
    return omochix_industry_hub_is_hub_request() || omochix_industry_hub_is_index_request();
}

/** Whether the request path is under /learn/industries/ (also for 404s). */
function omochix_industry_hub_path_matches() {
    $path = (string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '', PHP_URL_PATH);
    $home = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
    $rel  = '/' . ltrim(substr($path, strlen(rtrim($home, '/'))), '/');
    return 1 === preg_match('#^/learn/industries(/|$)#i', $rel);
}

/** The industry slug segment exactly as requested (raw, not decoded). */
function omochix_industry_hub_requested_slug() {
    $path = (string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '', PHP_URL_PATH);
    return preg_match('#/learn/industries/([^/]+)#i', $path, $m) ? $m[1] : '';
}

/**
 * Whether the requested segment is the term's slug in another ASCII case
 * (/Real-Estate/). Percent-encoded (non-ASCII) segments are never
 * normalised: clients may re-encode them, which would loop.
 */
function omochix_industry_hub_needs_case_redirect($requested, $slug) {
    return '' !== $requested && false === strpos($requested, '%') && $requested !== $slug && strtolower($requested) === $slug;
}

/* -------------------------------------------------------------------------
 * Validation (§4.2)
 * ---------------------------------------------------------------------- */

/** Content types of the "other related content" list. */
function omochix_industry_hub_types() {
    return [
        'workflow' => ['label' => __('ワークフロー', 'omochix'), 'post_type' => 'learn_practice'],
        'practice' => ['label' => __('実践教材', 'omochix'), 'post_type' => 'learn_practice'],
        'tool'     => ['label' => __('AIツール', 'omochix'), 'post_type' => 'ai_tool'],
        'prompt'   => ['label' => __('プロンプト', 'omochix'), 'post_type' => 'prompt'],
        'learn'    => ['label' => __('Learn記事', 'omochix'), 'post_type' => 'page'],
        'news'     => ['label' => __('ニュース', 'omochix'), 'post_type' => 'post'],
    ];
}

function omochix_industry_hub_levels() {
    return ['beginner' => __('初級', 'omochix'), 'intermediate' => __('中級', 'omochix'), 'advanced' => __('上級', 'omochix')];
}

/** Query keys the hub reads, per context. Everything else is ignored. */
function omochix_industry_hub_filter_keys($context) {
    return 'index' === $context ? ['q', 'purpose', 'type', 'industry'] : ['q', 'purpose', 'type', 'level', 'provider', 'sub'];
}

/**
 * Split a raw query string into key => list of raw values, normalising
 * "key[]" / "key[0]" to "key" and remembering that it was an array.
 *
 * @param string $qs Raw query string.
 * @return array<string, array{values: string[], array: bool}>
 */
function omochix_industry_hub_parse_query_string($qs) {
    $out = [];
    foreach (explode('&', (string) $qs) as $pair) {
        if ('' === $pair) {
            continue;
        }
        $parts = explode('=', $pair, 2);
        $key   = urldecode($parts[0]);
        $value = isset($parts[1]) ? urldecode(str_replace('+', ' ', $parts[1])) : '';
        $array = false;
        if (preg_match('/^([^\[]+)\[.*\]$/', $key, $m)) {
            $key   = $m[1];
            $array = true;
        }
        $out[$key]['values'][] = $value;
        $out[$key]['array']    = !empty($out[$key]['array']) || $array;
    }
    return $out;
}

/**
 * Validate the hub's own query keys (§4.2).
 *
 * @param string       $qs      Raw query string.
 * @param string       $context 'hub' or 'index'.
 * @param WP_Term|null $term    Current industry (hub context).
 * @return array{ok: bool, filters: array<string, string>, errors: array<string, string>, passthrough: array<string, string>}
 */
function omochix_industry_hub_validate($qs, $context, $term = null) {
    $keys    = omochix_industry_hub_filter_keys($context);
    $parsed  = omochix_industry_hub_parse_query_string($qs);
    $filters = array_fill_keys($keys, '');
    $errors  = [];
    $pass    = [];
    foreach ($parsed as $key => $info) {
        if (!in_array($key, $keys, true)) {
            if (1 === count($info['values']) && !$info['array'] && preg_match('/^[A-Za-z0-9_.-]{1,40}$/', (string) $key) && !in_array($key, ['paged', 'page', 'omx_industry'], true)) {
                $pass[$key] = $info['values'][0]; // tracking keys survive the 302
            }
            continue;
        }
        if ($info['array']) {
            $errors[$key] = 'array';
            continue;
        }
        if (count($info['values']) > 1) {
            $errors[$key] = 'duplicate';
            continue;
        }
        $raw = trim((string) $info['values'][0]);
        if ('q' === $key) {
            $q = preg_replace('/\s+/u', ' ', trim(sanitize_text_field($raw)));
            if (mb_strlen((string) $q) > OMOCHIX_INDUSTRY_HUB_Q_MAX) {
                $errors[$key] = 'too_long';
            } else {
                $filters['q'] = (string) $q;
            }
            continue;
        }
        if ('' === $raw) {
            continue; // an unselected form control
        }
        $value = sanitize_title($raw);
        $valid = false;
        switch ($key) {
            case 'purpose':
                $valid = '' !== $value && (bool) term_exists($value, 'omx_purpose');
                break;
            case 'type':
                $valid = isset(omochix_industry_hub_types()[$value]);
                break;
            case 'level':
                $valid = isset(omochix_industry_hub_levels()[$value]);
                break;
            case 'provider':
                $valid = function_exists('omochix_core_get_provider_vocabulary') && isset(omochix_core_get_provider_vocabulary()[$value]);
                break;
            case 'sub':
                $sub   = '' !== $value ? get_term_by('slug', $value, 'omx_industry') : false;
                $valid = $sub instanceof WP_Term && $term instanceof WP_Term && (int) $sub->parent === (int) $term->term_id;
                break;
            case 'industry':
                $ind   = '' !== $value ? get_term_by('slug', $value, 'omx_industry') : false;
                $valid = $ind instanceof WP_Term && 0 === (int) $ind->parent && omochix_core_industry_visibility($ind)['visible'];
                break;
        }
        if ($valid) {
            $filters[$key] = $value;
        } else {
            $errors[$key] = 'unknown';
        }
    }
    return ['ok' => !$errors, 'filters' => $filters, 'errors' => $errors, 'passthrough' => $pass];
}

/** Raw query string of the current request. */
function omochix_industry_hub_query_string() {
    return isset($_SERVER['QUERY_STRING']) ? (string) wp_unslash($_SERVER['QUERY_STRING']) : '';
}

/** Whether any filter is set. */
function omochix_industry_hub_is_filtered(array $filters) {
    return '' !== implode('', $filters);
}

/** Hub (or index) URL with filters. */
function omochix_industry_hub_url($term = null, array $filters = [], array $extra = []) {
    $base = $term instanceof WP_Term ? get_term_link($term, 'omx_industry') : omochix_industry_hub_index_url();
    if (is_wp_error($base) || !$base) {
        return '';
    }
    $args = array_filter($filters, static fn($v) => '' !== (string) $v) + $extra;
    return $args ? add_query_arg(array_map('rawurlencode', $args), $base) : (string) $base;
}

/* -------------------------------------------------------------------------
 * Decision (§3, §4)
 * ---------------------------------------------------------------------- */

/** Whether the current user may preview hidden hubs. */
function omochix_industry_hub_can_preview($term) {
    return $term instanceof WP_Term && current_user_can('edit_term', (int) $term->term_id);
}

/**
 * Decide the response for the current main request.
 *
 * @param bool $reset Forget the cached decision (tests).
 * @return array{action: string, status: int, location: string, term: WP_Term|null, filters: array<string, string>, preview: bool, indexable: bool, context: string, errors: array<string, string>}
 */
function omochix_industry_hub_decide($reset = false) {
    static $decision = null;
    if ($reset) {
        $decision = null;
    }
    if (null !== $decision) {
        return $decision;
    }
    $base = ['action' => 'pass', 'status' => 200, 'location' => '', 'term' => null, 'filters' => [], 'preview' => false, 'indexable' => false, 'context' => '', 'errors' => []];
    if (!omochix_industry_hub_enabled()) {
        return $decision = $base;
    }
    $qs = omochix_industry_hub_query_string();

    // WordPress' own search (?s=) under /learn/industries/ would replace the
    // hub; the hub searches with ?q=. Hidden or missing industries stay 404.
    if (isset($_GET['s']) && omochix_industry_hub_path_matches()) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $seg  = strtolower(omochix_industry_hub_requested_slug());
        $term = '' !== $seg && 'page' !== $seg ? get_term_by('slug', sanitize_title(rawurldecode($seg)), 'omx_industry') : null;
        if ('' !== $seg && 'page' !== $seg && (!$term instanceof WP_Term || !omochix_core_industry_term_is_visible($term))) {
            return $decision = array_merge($base, ['action' => 'not_found', 'status' => 404, 'context' => 'hub']);
        }
        return $decision = array_merge($base, ['action' => 'bad_request', 'status' => 400, 'context' => $term instanceof WP_Term ? 'hub' : 'index', 'term' => $term instanceof WP_Term ? $term : null, 'errors' => ['s' => 'unsupported']]);
    }

    if (omochix_industry_hub_is_index_request()) {
        $v = omochix_industry_hub_validate($qs, 'index');
        $d = array_merge($base, ['context' => 'index', 'filters' => $v['filters'], 'errors' => $v['errors']]);
        if (!$v['ok']) {
            return $decision = array_merge($d, ['action' => 'bad_request', 'status' => 400]);
        }
        $d['indexable'] = (bool) array_filter(omochix_core_get_industries_with_visibility(true), static fn($r) => $r['indexable']);
        $paged          = max(1, (int) get_query_var('paged'));
        if ($paged > 1) {
            $max = omochix_industry_hub_is_filtered($v['filters']) ? (int) omochix_industry_hub_index_search($v['filters'], $paged)->max_num_pages : 0;
            if ($paged > $max) {
                return $decision = array_merge($d, ['action' => 'not_found', 'status' => 404]);
            }
        }
        return $decision = $d;
    }

    if (!omochix_industry_hub_is_hub_request()) {
        return $decision = $base;
    }
    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return $decision = $base;
    }
    $top     = (int) $term->parent ? get_term((int) $term->parent, 'omx_industry') : $term;
    $vis     = $top instanceof WP_Term ? omochix_core_industry_visibility($top) : ['visible' => false, 'indexable' => false];
    $preview = !$vis['visible'] && omochix_industry_hub_can_preview($top);
    $d       = array_merge($base, ['context' => 'hub', 'term' => $term]);

    // 1. Hidden hubs do not exist for visitors (no redirect may reveal them).
    if (!$vis['visible'] && !$preview) {
        return $decision = array_merge($d, ['action' => 'not_found', 'status' => 404]);
    }
    // 2. Non-canonical slug spelling (e.g. upper case) → permanent canonical URL.
    $requested = omochix_industry_hub_requested_slug();
    if (omochix_industry_hub_needs_case_redirect($requested, $term->slug)) {
        $link = get_term_link($term, 'omx_industry');
        // The query string is kept verbatim; the target validates it again.
        return $decision = array_merge($d, ['action' => 'redirect', 'status' => 301, 'location' => is_wp_error($link) ? '' : $link . ('' !== $qs ? '?' . $qs : '')]);
    }
    // 3. Sub-industry → 302 to the parent with ?sub= (§3.3), after validating the query.
    if ((int) $term->parent) {
        $v = omochix_industry_hub_validate($qs, 'hub', $top);
        if (!$v['ok'] || '' !== $v['filters']['sub']) {
            return $decision = array_merge($d, ['action' => 'bad_request', 'status' => 400, 'term' => $top, 'errors' => $v['errors'] ?: ['sub' => 'duplicate'], 'preview' => $preview]);
        }
        if ('redirect' !== omochix_industry_hub_sub_mode($term)) {
            return $decision = $d; // future standalone sub-industry pages
        }
        $filters = array_merge($v['filters'], ['sub' => $term->slug]);
        return $decision = array_merge($d, ['action' => 'redirect', 'status' => 302, 'location' => omochix_industry_hub_url($top, $filters, $v['passthrough'])]);
    }
    // 4. Query validation → 400.
    $v = omochix_industry_hub_validate($qs, 'hub', $term);
    if (!$v['ok']) {
        return $decision = array_merge($d, ['action' => 'bad_request', 'status' => 400, 'filters' => $v['filters'], 'errors' => $v['errors'], 'preview' => $preview]);
    }
    return $decision = array_merge($d, ['filters' => $v['filters'], 'preview' => $preview, 'indexable' => !$preview && $vis['indexable']]);
}

/** How a sub-industry URL is served (§3.4). Phase 2 always redirects. */
function omochix_industry_hub_sub_mode($term) {
    return (string) apply_filters('omochix_industry_hub_sub_mode', 'redirect', $term);
}

/** Response headers shared by every non-200 / preview answer. */
function omochix_industry_hub_send_private_headers($robots = 'noindex, follow') {
    nocache_headers();
    if (!headers_sent()) {
        header('X-Robots-Tag: ' . $robots);
    }
}

/**
 * pre_handle_404: hidden hubs become 404 and bad requests are not turned into
 * 404s by core's paged check.
 *
 * @param bool     $preempt  Current value.
 * @param WP_Query $wp_query Main query.
 * @return bool
 */
function omochix_industry_hub_pre_handle_404($preempt, $wp_query) {
    if ($preempt || !$wp_query instanceof WP_Query || !$wp_query->is_main_query()) {
        return $preempt;
    }
    $d = omochix_industry_hub_decide();
    if ('not_found' === $d['action']) {
        $wp_query->set_404();
        status_header(404);
        omochix_industry_hub_send_private_headers();
        return true;
    }
    if (in_array($d['action'], ['bad_request', 'redirect'], true) || $d['preview']) {
        return true;
    }
    return $preempt;
}
add_filter('pre_handle_404', 'omochix_industry_hub_pre_handle_404', 10, 2);

/** template_redirect 0: apply the decision before canonical redirects and HEAD exits. */
function omochix_industry_hub_template_redirect() {
    $d = omochix_industry_hub_decide();
    switch ($d['action']) {
        case 'redirect':
            omochix_industry_hub_send_private_headers();
            if ($d['location']) {
                wp_safe_redirect($d['location'], $d['status'], 'OmochiX Industry Hub');
                exit;
            }
            break;
        case 'bad_request':
            status_header(400);
            omochix_industry_hub_send_private_headers();
            break;
        case 'not_found':
            global $wp_query;
            if ($wp_query instanceof WP_Query && !$wp_query->is_404()) {
                $wp_query->set_404();
            }
            status_header(404);
            omochix_industry_hub_send_private_headers();
            break;
        default:
            if ($d['preview']) {
                omochix_industry_hub_send_private_headers('noindex, nofollow');
            } elseif (is_404() && omochix_industry_hub_path_matches()) {
                // Core's own 404 (missing industry) answers exactly like a hidden one.
                omochix_industry_hub_send_private_headers();
            }
    }
}
add_action('template_redirect', 'omochix_industry_hub_template_redirect', 0);

/** No canonical / "guess" redirects for hub answers that are not a plain 200. */
function omochix_industry_hub_redirect_canonical($redirect) {
    $d = omochix_industry_hub_decide();
    return in_array($d['action'], ['not_found', 'bad_request', 'redirect'], true) ? false : $redirect;
}
add_filter('redirect_canonical', 'omochix_industry_hub_redirect_canonical', 1);

function omochix_industry_hub_no_guess($guess) {
    return omochix_industry_hub_path_matches() ? false : $guess;
}
add_filter('do_redirect_guess_404_permalink', 'omochix_industry_hub_no_guess');

/* -------------------------------------------------------------------------
 * Main query (§2, §6.1 section 6)
 * ---------------------------------------------------------------------- */

/**
 * Published workflows under these industries that no longer meet their rules
 * (§7). They are excluded in SQL, so the counts and the pagination match what
 * is listed.
 *
 * @param int[] $industry_ids Top-level industry term IDs (children included).
 * @return int[]
 */
function omochix_industry_hub_blocked_workflow_ids(array $industry_ids) {
    // Site-wide list, cached by Core for the current gate version (one option
    // read per request). Excluding a blocked workflow of another industry is a no-op.
    return $industry_ids && function_exists('omochix_core_blocked_workflow_ids') ? omochix_core_blocked_workflow_ids() : [];
}

/** Meta clauses shared by the hub list and the index search: level, and no discontinued AI tools. */
function omochix_industry_hub_meta_query($post_types, $level) {
    $meta = [];
    if ('' !== (string) $level) {
        $meta[] = ['key' => 'practice_difficulty', 'value' => (string) $level];
    }
    if (in_array('ai_tool', (array) $post_types, true) && function_exists('omochix_get_active_ai_tool_meta_query')) {
        $meta[] = omochix_get_active_ai_tool_meta_query(); // non-tools have no tool_status, so they pass
    }
    if (count($meta) > 1) {
        $meta['relation'] = 'AND';
    }
    return $meta;
}

/**
 * Hub main query = "other related content": all listable types of the
 * industry, narrowed by the validated filters, 12 per page.
 *
 * @param WP_Query $query Query.
 * @return void
 */
function omochix_industry_hub_pre_get_posts($query) {
    if (is_admin() || !$query->is_main_query() || !omochix_industry_hub_enabled() || !$query->is_tax('omx_industry') || $query->is_feed() || $query->is_embed()) {
        return;
    }
    $slug = $query->get('omx_industry');
    $term = is_scalar($slug) && '' !== (string) $slug ? get_term_by('slug', sanitize_title((string) $slug), 'omx_industry') : false;
    if (!$term instanceof WP_Term) {
        return;
    }
    $v       = omochix_industry_hub_validate(omochix_industry_hub_query_string(), 'hub', (int) $term->parent ? get_term((int) $term->parent, 'omx_industry') : $term);
    $filters = $v['filters'];
    $types   = omochix_industry_hub_types();
    $query->set('posts_per_page', OMOCHIX_INDUSTRY_HUB_PER_PAGE);
    $query->set('post_type', '' !== $filters['type'] ? $types[$filters['type']]['post_type'] : array_values(array_unique(array_column($types, 'post_type'))));
    $query->set('post_status', 'publish');
    $query->set('has_password', false);
    $query->set('orderby', ['menu_order' => 'ASC', 'date' => 'DESC']);
    $query->set('ignore_sticky_posts', true);
    if (!$v['ok']) {
        $query->set('post__in', [0]); // 400: render the message, list nothing
        return;
    }
    $tax = array_filter((array) $query->get('tax_query'), 'is_array');
    if ('' !== $filters['sub']) {
        $tax[] = ['taxonomy' => 'omx_industry', 'field' => 'slug', 'terms' => [$filters['sub']], 'include_children' => false];
    }
    if ('' !== $filters['purpose']) {
        $tax[] = ['taxonomy' => 'omx_purpose', 'field' => 'slug', 'terms' => [$filters['purpose']]];
    }
    if ('' !== $filters['provider']) {
        $tax[] = ['taxonomy' => 'omx_provider_type', 'field' => 'slug', 'terms' => [$filters['provider']]];
    }
    if ('workflow' === $filters['type']) {
        $tax[] = ['taxonomy' => 'learn_format', 'field' => 'slug', 'terms' => ['workflow']];
    } elseif ('practice' === $filters['type']) {
        $tax[] = ['taxonomy' => 'learn_format', 'field' => 'slug', 'terms' => ['workflow'], 'operator' => 'NOT IN'];
    }
    if (count($tax) > 1) {
        $tax['relation'] = 'AND';
    }
    if ($tax) {
        $query->set('tax_query', $tax);
    }
    if ('' !== $filters['level']) {
        $query->set('post_type', 'learn_practice');
    }
    if ('' !== $filters['q']) {
        $query->set('s', $filters['q']);
    }
    $meta = omochix_industry_hub_meta_query($query->get('post_type'), $filters['level']);
    if ($meta) {
        $query->set('meta_query', $meta);
    }
    if (in_array('learn_practice', (array) $query->get('post_type'), true)) {
        $top     = (int) $term->parent ? (int) $term->parent : (int) $term->term_id;
        $blocked = omochix_industry_hub_blocked_workflow_ids([$top]);
        if ($blocked) {
            $query->set('post__not_in', $blocked);
        }
    }
}
add_action('pre_get_posts', 'omochix_industry_hub_pre_get_posts', 20);

/** Live workflows that no longer meet their rules are not listed (§7). */
function omochix_industry_hub_the_posts($posts, $query) {
    if (!$query instanceof WP_Query || !$query->is_main_query() || !$query->is_tax('omx_industry') || !function_exists('omochix_core_workflow_stored_blockers')) {
        return $posts;
    }
    if (function_exists('omochix_core_prime_workflow_tools')) {
        omochix_core_prime_workflow_tools((array) $posts);
    }
    return array_values(array_filter((array) $posts, static fn($p) => !$p instanceof WP_Post || 'learn_practice' !== $p->post_type || !omochix_core_workflow_stored_blockers($p->ID)));
}
add_filter('the_posts', 'omochix_industry_hub_the_posts', 10, 2);

/** Cross-industry search on the index page (§2). */
function omochix_industry_hub_index_search(array $filters, $paged = 1) {
    static $cache = [];
    $key = md5(wp_json_encode([$filters, (int) $paged]));
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $visible = function_exists('omochix_core_get_visible_industry_ids') ? omochix_core_get_visible_industry_ids() : [];
    $ind     = '' !== ($filters['industry'] ?? '') ? get_term_by('slug', $filters['industry'], 'omx_industry') : null;
    $ids     = $ind instanceof WP_Term ? [(int) $ind->term_id] : $visible;
    $types   = omochix_industry_hub_types();
    $tax     = ['relation' => 'AND', ['taxonomy' => 'omx_industry', 'field' => 'term_id', 'terms' => $ids ?: [0], 'include_children' => true]];
    if ('' !== ($filters['purpose'] ?? '')) {
        $tax[] = ['taxonomy' => 'omx_purpose', 'field' => 'slug', 'terms' => [$filters['purpose']]];
    }
    if ('workflow' === ($filters['type'] ?? '')) {
        $tax[] = ['taxonomy' => 'learn_format', 'field' => 'slug', 'terms' => ['workflow']];
    } elseif ('practice' === ($filters['type'] ?? '')) {
        $tax[] = ['taxonomy' => 'learn_format', 'field' => 'slug', 'terms' => ['workflow'], 'operator' => 'NOT IN'];
    }
    $post_type = '' !== ($filters['type'] ?? '') ? $types[$filters['type']]['post_type'] : array_values(array_unique(array_column($types, 'post_type')));
    $args = [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'has_password'   => false,
        'posts_per_page' => OMOCHIX_INDUSTRY_HUB_PER_PAGE,
        'paged'          => max(1, (int) $paged),
        'tax_query'      => $tax,
        'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
    ];
    if ('' !== ($filters['q'] ?? '')) {
        $args['s'] = $filters['q'];
    }
    $meta = omochix_industry_hub_meta_query($post_type, '');
    if ($meta) {
        $args['meta_query'] = $meta;
    }
    if (in_array('learn_practice', (array) $post_type, true)) {
        $blocked = omochix_industry_hub_blocked_workflow_ids($ids);
        if ($blocked) {
            $args['post__not_in'] = $blocked;
        }
    }
    return $cache[$key] = new WP_Query($args);
}

/* -------------------------------------------------------------------------
 * Templates
 * ---------------------------------------------------------------------- */

/** Serve the hub templates (priority 9: before the shell records the template at 99). */
function omochix_industry_hub_template_include($template) {
    if (is_404() || !omochix_industry_hub_enabled()) {
        return $template;
    }
    $file = '';
    if (omochix_industry_hub_is_hub_request()) {
        $file = get_theme_file_path('/taxonomy-omx_industry.php');
    } elseif (omochix_industry_hub_is_index_request()) {
        $file = get_theme_file_path('/page-learn-industries.php');
    }
    return $file && file_exists($file) ? $file : $template;
}
add_filter('template_include', 'omochix_industry_hub_template_include', 9);

/* -------------------------------------------------------------------------
 * SEO (§8)
 * ---------------------------------------------------------------------- */

/** Whether the current hub / index answer must not be indexed. */
function omochix_industry_hub_is_noindex() {
    if (!omochix_industry_hub_is_request() && !omochix_industry_hub_path_matches()) {
        return false;
    }
    $d = omochix_industry_hub_decide();
    if ('pass' !== $d['action'] || $d['preview'] || !$d['indexable']) {
        return true;
    }
    return omochix_industry_hub_is_filtered($d['filters']) || max(1, (int) get_query_var('paged')) > 1;
}

/** Phase 1 hook: the plugin asks the theme whether the archive is noindex. */
add_filter('omochix_core_industry_archive_noindex', static fn($noindex) => omochix_industry_hub_is_request() ? omochix_industry_hub_is_noindex() : $noindex);

/**
 * Slim SEO decides indexability (and drops the canonical when not indexed).
 * For hub requests our answer wins, also over Slim SEO's "no posts" rule:
 * an indexable hub whose list section is empty stays indexed.
 */
function omochix_industry_hub_slim_seo_index($indexed) {
    return omochix_industry_hub_is_request() ? !omochix_industry_hub_is_noindex() : $indexed;
}
add_filter('slim_seo_robots_index', 'omochix_industry_hub_slim_seo_index', 30);

/** Robots without Slim SEO (or with blog_public off), and the preview's nofollow. */
function omochix_industry_hub_wp_robots($robots) {
    if (!omochix_industry_hub_is_request()) {
        return $robots;
    }
    $d = omochix_industry_hub_decide();
    if (omochix_industry_hub_is_noindex()) {
        $robots = wp_robots_no_robots($robots);
        remove_action('wp_head', 'rel_canonical');
    }
    if ($d['preview']) {
        unset($robots['follow']);
        $robots['nofollow'] = true;
    }
    return $robots;
}
add_filter('wp_robots', 'omochix_industry_hub_wp_robots', 30);

function omochix_industry_hub_canonical($url) {
    return omochix_industry_hub_is_request() && omochix_industry_hub_is_noindex() ? '' : $url;
}
add_filter('slim_seo_canonical_url', 'omochix_industry_hub_canonical', 30);

function omochix_industry_hub_meta_title($title) {
    if (omochix_industry_hub_is_hub_request() && get_queried_object() instanceof WP_Term) {
        /* translators: %s: industry name */
        return sprintf(__('%sのAI活用｜ツール・教材・導入ワークフロー', 'omochix'), get_queried_object()->name);
    }
    return $title;
}
add_filter('slim_seo_meta_title', 'omochix_industry_hub_meta_title', 30);

function omochix_industry_hub_meta_description($description) {
    if (omochix_industry_hub_is_hub_request() && get_queried_object() instanceof WP_Term) {
        $lead = (string) omochix_core_get_industry_meta(get_queried_object_id())['industry_lead'];
        if ('' !== $lead) {
            return mb_substr(preg_replace('/\s+/u', ' ', $lead), 0, 120);
        }
    }
    return $description;
}
add_filter('slim_seo_meta_description', 'omochix_industry_hub_meta_description', 30);

/** Breadcrumbs: Home › Learn › 業種別AI活用 › {industry}. */
function omochix_industry_hub_breadcrumbs($links) {
    if (!is_array($links) || !omochix_industry_hub_is_hub_request()) {
        return $links;
    }
    $index = omochix_industry_hub_index_url();
    if (!$index) {
        return $links;
    }
    $last = array_pop($links);
    $out  = [];
    foreach ($links as $link) {
        $out[] = $link;
    }
    $learn = function_exists('omochix_get_published_page_url') ? omochix_get_published_page_url('learn') : '';
    if ($learn && !in_array($learn, array_column($out, 'url'), true)) {
        $out[] = ['url' => $learn, 'text' => 'Learn'];
    }
    $out[] = ['url' => $index, 'text' => __('業種別AI活用', 'omochix')];
    $out[] = $last;
    return $out;
}
add_filter('slim_seo_breadcrumbs_links', 'omochix_industry_hub_breadcrumbs', 30);

/** Items the indexable page lists in its ItemList (hubs on the index, workflows on a hub). */
function omochix_industry_hub_item_list() {
    $items = [];
    if (omochix_industry_hub_is_index_request()) {
        foreach (omochix_core_get_industries_with_visibility(true) as $row) {
            $link = get_term_link($row['term'], 'omx_industry');
            if (!is_wp_error($link)) {
                $items[] = ['url' => $link, 'name' => $row['term']->name];
            }
        }
    } elseif (omochix_industry_hub_is_hub_request() && get_queried_object() instanceof WP_Term) {
        foreach (omochix_industry_hub_sections(get_queried_object(), omochix_industry_hub_decide()['filters'])['workflows'] as $post) {
            $items[] = ['url' => get_permalink($post), 'name' => get_the_title($post)];
        }
    }
    return $items;
}

/**
 * JSON-LD: Slim SEO already outputs CollectionPage and BreadcrumbList for
 * term archives; add one ItemList and point the page at it (no duplicates).
 */
function omochix_industry_hub_schema_graph($graph) {
    if (!is_array($graph) || !omochix_industry_hub_is_request() || omochix_industry_hub_is_noindex()) {
        return $graph;
    }
    $items = omochix_industry_hub_item_list();
    if (!$items) {
        return $graph;
    }
    $page_url = omochix_industry_hub_is_hub_request() ? get_term_link(get_queried_object(), 'omx_industry') : omochix_industry_hub_index_url();
    if (is_wp_error($page_url) || !$page_url) {
        return $graph;
    }
    $id = $page_url . '#industry-hub-list';
    $list     = ['@type' => 'ItemList', '@id' => $id, 'itemListElement' => []];
    foreach (array_values($items) as $i => $item) {
        $list['itemListElement'][] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $item['url'], 'name' => $item['name']];
    }
    foreach ($graph as &$entity) {
        if (isset($entity['@type']) && in_array($entity['@type'], ['WebPage', 'CollectionPage'], true)) {
            $entity['mainEntity'] = ['@id' => $id];
        }
    }
    unset($entity);
    $graph[] = $list;
    return $graph;
}
add_filter('slim_seo_schema_graph', 'omochix_industry_hub_schema_graph', 30);

/** The index page leaves the page sitemap while no hub is indexable. */
function omochix_industry_hub_sitemap_post_ignore($ignore, $post) {
    $page = omochix_industry_hub_page();
    if ($page && $post instanceof WP_Post && (int) $post->ID === (int) $page->ID) {
        return !array_filter(omochix_core_get_industries_with_visibility(true), static fn($r) => $r['indexable']);
    }
    return $ignore;
}
add_filter('slim_seo_sitemap_post_ignore', 'omochix_industry_hub_sitemap_post_ignore', 10, 2);

/* -------------------------------------------------------------------------
 * Data (§6.1, §7)
 * ---------------------------------------------------------------------- */

/**
 * Everything a hub page shows, in the §6.1 order, with the posts' meta and
 * terms loaded in one go.
 *
 * @param WP_Term               $term    Top-level industry.
 * @param array<string, string> $filters Validated filters.
 * @return array<string, mixed>
 */
function omochix_industry_hub_sections($term, array $filters = []) {
    static $cache = [];
    $key = (int) $term->term_id . ':' . md5(wp_json_encode($filters));
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $meta    = omochix_core_get_industry_meta($term->term_id);
    $purpose = (string) ($filters['purpose'] ?? '');

    // Section 3: selected purpose first, the rest after.
    $usecases = (array) $meta['industry_usecases'];
    if ('' !== $purpose) {
        usort($usecases, static fn($a, $b) => (int) (($b['purpose'] ?? '') === $purpose) <=> (int) (($a['purpose'] ?? '') === $purpose));
    }

    // Section 4: workflows (official first), narrowed by the purpose.
    $practice  = omochix_core_get_industry_posts($term, 'learn_practice', ['limit' => 12, 'purpose' => $purpose]);
    $blocked   = omochix_industry_hub_blocked_workflow_ids([(int) $term->term_id]);
    $featured  = array_values(array_filter(
        omochix_core_get_industry_curated($term, 'industry_featured_ids'),
        // Curated items follow the same rules as the rest: no blocked workflow, and only the chosen challenge's purpose.
        static fn($p) => 'learn_practice' === $p->post_type && !in_array((int) $p->ID, $blocked, true) && ('' === $purpose || has_term($purpose, 'omx_purpose', $p))
    ));
    $workflows = [];
    $lessons   = [];
    foreach (array_merge($featured, $practice) as $post) {
        if (isset($workflows[$post->ID]) || isset($lessons[$post->ID])) {
            continue;
        }
        if (has_term('workflow', 'learn_format', $post)) {
            $workflows[$post->ID] = $post;
        } else {
            $lessons[$post->ID] = $post;
        }
    }
    uasort($workflows, static fn($a, $b) => (int) ('official' === omochix_core_get_provider_type($b)) <=> (int) ('official' === omochix_core_get_provider_type($a)));

    // Section 5: tools (curated first), prompts, Motion.
    $tools = [];
    foreach (array_merge(omochix_core_get_industry_curated($term, 'industry_tool_ids'), omochix_core_get_industry_posts($term, 'ai_tool', ['limit' => OMOCHIX_INDUSTRY_HUB_SECTION])) as $post) {
        if (function_exists('omochix_is_ai_tool_discontinued') && omochix_is_ai_tool_discontinued($post->ID)) {
            continue; // discontinued tools are never recommended, curated or not
        }
        $tools[$post->ID] = $post;
    }
    $prompts = omochix_core_get_industry_posts($term, 'prompt', ['limit' => OMOCHIX_INDUSTRY_HUB_SECTION, 'purpose' => $purpose]);
    $motion  = [];
    if (function_exists('omochix_learn_gallery_get_item')) {
        foreach (omochix_core_get_industry_motion_slugs($term) as $slug) {
            $item = omochix_learn_gallery_get_item($slug);
            if ($item) {
                $motion[] = $item;
            }
        }
    }

    $out = [
        'meta'      => $meta,
        'overview'  => ['lead' => (string) $meta['industry_lead'], 'benefits' => (array) $meta['industry_benefits'], 'verified_at' => (string) $meta['industry_verified_at']],
        'challenges'=> (array) $meta['industry_challenges'],
        'usecases'  => $usecases,
        'workflows' => array_slice(array_values($workflows), 0, OMOCHIX_INDUSTRY_HUB_SECTION),
        'lessons'   => array_slice(array_values($lessons), 0, OMOCHIX_INDUSTRY_HUB_SECTION),
        'tools'     => array_slice(array_values($tools), 0, OMOCHIX_INDUSTRY_HUB_SECTION),
        'prompts'   => $prompts,
        'motion'    => array_slice($motion, 0, OMOCHIX_INDUSTRY_HUB_SECTION),
        'resources' => omochix_core_get_industry_resource_cards($term, OMOCHIX_INDUSTRY_HUB_SECTION),
        'sources'   => (array) $meta['industry_sources'],
    ];
    // One pass to load meta and terms for every post the page will render.
    $all = array_merge($out['workflows'], $out['lessons'], $out['tools'], $out['prompts']);
    if ($all && function_exists('update_post_caches')) {
        update_post_caches($all, 'any', true, true);
    }
    return $cache[$key] = $out;
}

/**
 * The hub index: visible industries only (hidden ones are never named, the
 * same rule as REST), plus how many are still being prepared.
 *
 * @return array{rows: array<int, array<string, mixed>>, hidden: int}
 */
function omochix_industry_hub_index_rows() {
    $rows   = [];
    $hidden = 0;
    foreach (omochix_core_get_industries_with_visibility(false) as $row) {
        if (!$row['visible']) {
            ++$hidden;
            continue;
        }
        $meta   = omochix_core_get_industry_meta($row['term']->term_id);
        $rows[] = [
            'term'       => $row['term'],
            'url'        => omochix_industry_hub_url($row['term']),
            'lead'       => (string) $meta['industry_lead'],
            'icon'       => (string) $meta['industry_icon'],
            'challenges' => array_slice(array_values(array_filter((array) $meta['industry_challenges'], static fn($c) => '' !== (string) ($c['title'] ?? ''))), 0, 3),
        ];
    }
    return ['rows' => $rows, 'hidden' => $hidden];
}

/** URL of a challenge chip: its purpose filter + #ih-workflows, or #ih-workflows unfiltered (§12 #10). */
function omochix_industry_hub_challenge_url($term, array $challenge) {
    $purpose = (string) ($challenge['purpose'] ?? '');
    $url     = omochix_industry_hub_url($term, '' !== $purpose ? ['purpose' => $purpose] : []);
    return $url ? $url . '#ih-workflows' : '';
}

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

/** Styles of the hub pages (practice cards are reused, so their CSS too). */
function omochix_industry_hub_enqueue_assets() {
    if (!omochix_industry_hub_is_request() || is_404()) {
        return;
    }
    $deps = wp_style_is('omochix-shell', 'enqueued') ? ['omochix-style', 'omochix-shell'] : ['omochix-style'];
    if (!wp_style_is('omochix-learn-practice', 'enqueued') && file_exists(get_theme_file_path('/assets/css/learn-practice.css'))) {
        wp_enqueue_style('omochix-learn-practice', get_theme_file_uri('/assets/css/learn-practice.css'), $deps, (string) filemtime(get_theme_file_path('/assets/css/learn-practice.css')));
    }
    wp_enqueue_style('omochix-learn-industries', get_theme_file_uri('/assets/css/learn-industries.css'), $deps, (string) filemtime(get_theme_file_path('/assets/css/learn-industries.css')));
}
add_action('wp_enqueue_scripts', 'omochix_industry_hub_enqueue_assets', 23);

/** Use the shared shell (left navigation, editorial footer) on hub pages. */
function omochix_industry_hub_shell($enabled) {
    return $enabled || omochix_industry_hub_is_request();
}
add_filter('omochix_shell_enabled_for_request', 'omochix_industry_hub_shell');
