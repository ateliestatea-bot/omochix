<?php
// omochix-web2-module: glossary api=1
/**
 * AI glossary (Knowledge Hub), theme side: entry file.
 *
 * Loaded only by the theme's Web 2.0 loader (inc/web2-loader.php, hooks
 * stream) once it lists this module. The data model, the rules and the leak
 * guards are in OmochiX Core (includes/web2/glossary-*.php); this file
 * decides how glossary URLs answer and how they look:
 * - /glossary/ (all terms), /glossary/category/{slug}/, /glossary/{slug}/;
 * - hidden, draft, password-protected and failing terms answer exactly like
 *   a missing page (404, noindex, no cache), editors get a noindex preview;
 * - paging (/page/2/), feeds, embeds and trackbacks are 404, ?s= on a glossary
 *   list is 400;
 * - a query that asks for the glossary post type any other way
 *   (?post_type[]=omx_glossary, alone or mixed with other types, with a feed
 *   or a search) is 404: terms are only ever shown by the three views above;
 * - every such 404 is emptied (no feed, no posts), and nothing redirects a
 *   visitor to a hidden term (/?p=ID, an old slug);
 * - title, description, canonical (indexable pages only), robots,
 *   breadcrumbs and DefinedTerm / DefinedTermSet structured data.
 *
 * The glossary stays closed: omochix_glossary_public_switch() answers false
 * whatever runs before it. Opening is a one-line change in a reviewed
 * release; closing again is the same line back.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Index thresholds (gate G-C, Phase 0 D-4): the list needs 10 indexable terms, a category 3.
 * Core owns the numbers (its sitemaps use them); the theme follows, so robots, canonical,
 * structured data and sitemaps cannot disagree. The literals are for a Core without them.
 */
if (!defined('OMOCHIX_GLOSSARY_INDEX_MIN')) {
    define('OMOCHIX_GLOSSARY_INDEX_MIN', defined('OMOCHIX_CORE_GLOSSARY_INDEX_MIN') ? (int) OMOCHIX_CORE_GLOSSARY_INDEX_MIN : 10);
}
if (!defined('OMOCHIX_GLOSSARY_CATEGORY_INDEX_MIN')) {
    define('OMOCHIX_GLOSSARY_CATEGORY_INDEX_MIN', defined('OMOCHIX_CORE_GLOSSARY_CATEGORY_INDEX_MIN') ? (int) OMOCHIX_CORE_GLOSSARY_CATEGORY_INDEX_MIN : 3);
}

/**
 * The theme's answer to Core's gate G-A. Phase 1: closed.
 *
 * @param mixed $public Value so far.
 * @return bool
 */
function omochix_glossary_public_switch($public) {
    return false;
}
add_filter('omochix_core_glossary_public', 'omochix_glossary_public_switch', 10);

/** Whether the glossary can be shown at all (Core module loaded and open). */
function omochix_glossary_enabled() {
    return function_exists('omochix_core_glossary_public') && function_exists('omochix_core_glossary_visibility')
        && omochix_core_glossary_public() && post_type_exists('omx_glossary');
}

/** Whether the request path is under /glossary/ (also while the glossary is closed). */
function omochix_glossary_path_matches() {
    $uri  = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared
    $path = (string) wp_parse_url($uri, PHP_URL_PATH);
    $home = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
    $rel  = '/' . ltrim(substr(strtolower(rawurldecode($path)), strlen(rtrim($home, '/'))), '/');
    return 1 === preg_match('#^/glossary(?:/|$)#', $rel);
}

/** Whether this request is one of the glossary views (archive, category, single). */
function omochix_glossary_is_request() {
    return omochix_glossary_enabled() && (is_post_type_archive('omx_glossary') || is_singular('omx_glossary') || is_tax('omx_glossary_category'));
}

/**
 * Whether the main query asks for the glossary post type by name. WordPress
 * takes ?post_type[]=omx_glossary (an array) as a plain list of posts: not a
 * post type archive, so none of the three views, and it would print every
 * published term (also hidden ones) through the blog, feed or search template.
 */
function omochix_glossary_query_names_glossary() {
    $types = get_query_var('post_type');
    foreach (is_array($types) ? $types : [$types] as $type) {
        if (is_string($type) && 'omx_glossary' === strtolower(trim($type))) {
            return true;
        }
    }
    return false;
}

/**
 * Published, visible terms (gate G-B), sorted by reading. Optionally of one category.
 *
 * @param string $category Category slug, or '' for all.
 * @param bool   $reset    Forget the cached lists (tests).
 * @return WP_Post[]
 */
function omochix_glossary_visible_terms($category = '', $reset = false) {
    static $cache = [];
    if ($reset) {
        $cache = [];
        return [];
    }
    if (isset($cache[$category])) {
        return $cache[$category];
    }
    $args = ['post_type' => 'omx_glossary', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 500, 'no_found_rows' => true, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true];
    if ('' !== $category) {
        $args['tax_query'] = [['taxonomy' => 'omx_glossary_category', 'field' => 'slug', 'terms' => [$category]]];
    }
    $out = [];
    foreach ((array) get_posts($args) as $p) {
        if ($p instanceof WP_Post && omochix_core_glossary_visibility($p)['visible']) {
            $out[] = $p;
        }
    }
    usort($out, static function ($a, $b) {
        $ra = (string) get_post_meta($a->ID, 'glossary_reading', true);
        $rb = (string) get_post_meta($b->ID, 'glossary_reading', true);
        return [$ra, $a->post_title] <=> [$rb, $b->post_title];
    });
    return $cache[$category] = $out;
}

/** Number of indexable terms in a list. */
function omochix_glossary_indexable_count(array $posts) {
    return count(array_filter($posts, static fn($p) => omochix_core_glossary_visibility($p)['indexable']));
}

/**
 * Categories that have at least one visible term, in the order of their term_order/name.
 *
 * @return WP_Term[]
 */
function omochix_glossary_categories() {
    $terms = get_terms(['taxonomy' => 'omx_glossary_category', 'hide_empty' => false]);
    if (!is_array($terms)) {
        return [];
    }
    return array_values(array_filter($terms, static fn($t) => $t instanceof WP_Term && [] !== omochix_glossary_visible_terms($t->slug)));
}

/**
 * What this request should answer (cached per request).
 *
 * @param bool $reset Forget the cached decision (tests).
 * @return array{action: string, kind: string, post: ?WP_Post, term: ?WP_Term, index: bool, preview: bool}
 */
function omochix_glossary_decide($reset = false) {
    static $d = null;
    if ($reset) {
        $d = null;
        omochix_glossary_visible_terms('', true);
        if (function_exists('omochix_core_glossary_index_state')) {
            omochix_core_glossary_index_state(true);
        }
    }
    if (null !== $d) {
        return $d;
    }
    $d = ['action' => 'none', 'kind' => '', 'post' => null, 'term' => null, 'index' => false, 'preview' => false];
    if (!omochix_glossary_is_request()) {
        if (omochix_glossary_enabled() && omochix_glossary_query_names_glossary()) {
            $d['action'] = 'not_found'; // The post type asked for outside its own views.
        }
        return $d;
    }
    $d['action'] = 'ok';
    if (is_feed() || is_embed() || is_trackback() || (int) get_query_var('paged') > 1 || (int) get_query_var('page') > 1) {
        $d['action'] = 'not_found';
        return $d;
    }
    if (is_singular('omx_glossary')) {
        $d['kind'] = 'single';
        $post = get_queried_object();
        $post = $post instanceof WP_Post ? $post : null;
        $vis  = $post ? omochix_core_glossary_visibility($post) : ['visible' => false, 'indexable' => false];
        $d['post'] = $post;
        if ($vis['visible']) {
            $d['index'] = (bool) $vis['indexable'];
        } elseif ($post && current_user_can('edit_post', $post->ID)) {
            $d['preview'] = true;
        } else {
            $d['action'] = 'not_found';
        }
        return $d;
    }
    if (is_search()) {
        $d['action'] = 'bad_request'; // The list is filtered on the page; no WordPress search over it.
        return $d;
    }
    if (is_tax('omx_glossary_category')) {
        $d['kind'] = 'category';
        $term = get_queried_object();
        $d['term'] = $term instanceof WP_Term ? $term : null;
        $posts = $d['term'] ? omochix_glossary_visible_terms($d['term']->slug) : [];
        if (!$posts) {
            $d['action'] = 'not_found';
            return $d;
        }
        // Core's answer when it has one: its sitemaps list a category exactly when this is true.
        $d['index'] = function_exists('omochix_core_glossary_category_indexable')
            ? omochix_core_glossary_category_indexable($d['term'])
            : omochix_glossary_indexable_count($posts) >= OMOCHIX_GLOSSARY_CATEGORY_INDEX_MIN;
        return $d;
    }
    $d['kind']  = 'archive';
    $d['index'] = function_exists('omochix_core_glossary_list_indexable')
        ? omochix_core_glossary_list_indexable()
        : omochix_glossary_indexable_count(omochix_glossary_visible_terms()) >= OMOCHIX_GLOSSARY_INDEX_MIN;
    return $d;
}

/** Headers for every 404 / 400 / preview answer. */
function omochix_glossary_private_headers($robots = 'noindex, follow') {
    nocache_headers();
    if (!headers_sent()) {
        header('X-Robots-Tag: ' . $robots);
    }
}

/**
 * Make the main query a 404 with nothing in it. set_404() alone keeps is_feed
 * and the posts it found, and WordPress would then still print them as a feed
 * (titles, links and content of every published term, hidden ones included)
 * under the 404 status.
 *
 * @param mixed $wp_query Main query.
 * @return void
 */
function omochix_glossary_empty_404($wp_query) {
    if (!is_object($wp_query) || !method_exists($wp_query, 'set_404')) {
        return;
    }
    $wp_query->set_404();
    $wp_query->is_feed         = false;
    $wp_query->is_comment_feed = false;
    $wp_query->is_trackback    = false;
    $wp_query->is_embed        = false;
    $wp_query->posts           = [];
    $wp_query->post_count      = 0;
    $wp_query->found_posts     = 0;
    $wp_query->current_post    = -1;
    $wp_query->post            = null;
    $wp_query->queried_object    = null;
    $wp_query->queried_object_id = 0;
    $wp_query->comments          = [];
    $wp_query->comment_count     = 0;
    if ($wp_query->is_main_query()) {
        $GLOBALS['post'] = null; // WordPress may have copied the term there already.
    }
}

/** pre_handle_404: hidden terms become a 404 like any missing page. */
function omochix_glossary_pre_handle_404($preempt, $wp_query) {
    if ($preempt || !$wp_query instanceof WP_Query || !$wp_query->is_main_query()) {
        return $preempt;
    }
    $d = omochix_glossary_decide();
    if ('not_found' === $d['action']) {
        omochix_glossary_empty_404($wp_query);
        status_header(404);
        omochix_glossary_private_headers();
        return true;
    }
    return 'bad_request' === $d['action'] || $d['preview'] ? true : $preempt;
}
add_filter('pre_handle_404', 'omochix_glossary_pre_handle_404', 10, 2);

/** template_redirect 0: apply the decision before canonical redirects. */
function omochix_glossary_template_redirect() {
    $d = omochix_glossary_decide();
    omochix_glossary_forget_hidden_id();
    if ('not_found' === $d['action']) {
        global $wp_query;
        if ($wp_query instanceof WP_Query) {
            omochix_glossary_empty_404($wp_query); // Again: also when pre_handle_404 did not run.
        }
        status_header(404);
        omochix_glossary_private_headers();
    } elseif ('bad_request' === $d['action']) {
        status_header(400);
        omochix_glossary_private_headers();
    } elseif ($d['preview']) {
        omochix_glossary_private_headers('noindex, nofollow');
    } elseif (is_404() && omochix_glossary_path_matches()) {
        // While closed (or for a missing slug) /glossary/… is WordPress's own 404: same headers.
        omochix_glossary_private_headers();
    }
}
add_action('template_redirect', 'omochix_glossary_template_redirect', 0);

/** No guessing a nearby post for a missing /glossary/ URL. */
function omochix_glossary_no_guess($guess) {
    return omochix_glossary_path_matches() ? false : $guess;
}
add_filter('do_redirect_guess_404_permalink', 'omochix_glossary_no_guess');

/** Whether a post is a glossary term this visitor may not see (hidden and not theirs to edit). */
function omochix_glossary_is_hidden_term($post) {
    $post = $post ? get_post($post) : null;
    return $post instanceof WP_Post && 'omx_glossary' === $post->post_type && function_exists('omochix_core_glossary_visibility')
        && !omochix_core_glossary_visibility($post)['visible'] && !current_user_can('edit_post', $post->ID);
}

/**
 * /?p=ID of a hidden term must answer exactly like /?p=ID of a missing post.
 * On a 404 WordPress looks the ID up and redirects to that post's address
 * (its slug). Stopping the redirect would be a difference of its own (a
 * missing ID still gets its address tidied), so the ID is forgotten instead,
 * before WordPress looks: it then treats the request as it treats a missing ID.
 *
 * @return void
 */
function omochix_glossary_forget_hidden_id() {
    foreach (['p', 'page_id', 'attachment_id'] as $var) {
        $id = (int) get_query_var($var);
        if ($id > 0 && omochix_glossary_is_hidden_term($id)) {
            set_query_var($var, 0);
        }
    }
}

/**
 * No redirect from an old slug to a hidden term (it would show that the term
 * exists, and its current slug).
 *
 * @param mixed $id Post ID WordPress found for the old slug.
 * @return mixed
 */
function omochix_glossary_old_slug_redirect($id) {
    return $id && omochix_glossary_is_hidden_term((int) $id) ? 0 : $id;
}
add_filter('old_slug_redirect_post_id', 'omochix_glossary_old_slug_redirect');

/** The 400 page uses the theme's 404 template with a 400 status. */
function omochix_glossary_template_include($template) {
    $d = omochix_glossary_decide();
    if ('bad_request' === $d['action'] || 'not_found' === $d['action']) {
        $t = get_404_template();
        return $t ? $t : $template;
    }
    return $template;
}
add_filter('template_include', 'omochix_glossary_template_include', 9);

// ------------------------------------------------------------------ view data

/**
 * Safe HTML of a term's explanation: the glossary's own small allow-list,
 * https links only (stricter than wp_kses_post).
 *
 * @param string $html Stored content.
 * @return string
 */
function omochix_glossary_content_html($html) {
    $allowed = [];
    foreach (['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'code', 'pre', 'blockquote', 'br', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'sup', 'sub'] as $tag) {
        $allowed[$tag] = [];
    }
    $allowed['a'] = ['href' => true];
    // The block editor's comments and its own class names come off first (the same step the rules use).
    $html = function_exists('omochix_core_glossary_normalize_content') ? omochix_core_glossary_normalize_content((string) $html) : (string) $html;
    $out  = wp_kses($html, $allowed, ['https']);
    // External links never get to read the opener or the referrer.
    return (string) preg_replace('#<a href="#', '<a rel="noopener noreferrer external" href="', $out);
}

/** YYYY-MM-DD as Japanese date (YYYY年M月D日), or ''. */
function omochix_glossary_date($ymd) {
    if (!is_string($ymd) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $ymd, $m)) {
        return '';
    }
    return sprintf('%d年%d月%d日', (int) $m[1], (int) $m[2], (int) $m[3]);
}

/**
 * Everything a template shows for one term. Strings are raw: templates escape.
 *
 * @param WP_Post $post Term.
 * @return array<string, mixed>
 */
function omochix_glossary_view($post) {
    $meta = static fn($k, $d = '') => ('' === ($v = get_post_meta($post->ID, $k, true)) || null === $v) ? $d : $v;
    $cats = wp_get_object_terms($post->ID, 'omx_glossary_category');
    $sources = [];
    foreach ((array) $meta('glossary_sources', []) as $s) {
        if (is_array($s) && '' === omochix_core_glossary_url_problem($s['url'] ?? null)) {
            $sources[] = ['url' => (string) $s['url'], 'title' => (string) ($s['title'] ?? ''), 'publisher' => (string) ($s['publisher'] ?? ''), 'kind' => (string) ($s['kind'] ?? ''), 'checked_on' => omochix_glossary_date($s['checked_on'] ?? '')];
        }
    }
    $levels = ['beginner' => __('初級', 'omochix'), 'intermediate' => __('中級', 'omochix'), 'advanced' => __('上級', 'omochix')];
    $level  = (string) $meta('glossary_level');
    return [
        'id'          => (int) $post->ID,
        'url'         => (string) get_permalink($post),
        'title'       => (string) get_the_title($post),
        'reading'     => (string) $meta('glossary_reading'),
        'en'          => (string) $meta('glossary_en'),
        'aliases'     => array_values(array_filter(array_map('strval', (array) $meta('glossary_aliases', [])))),
        'summary'     => (string) $meta('glossary_summary'),
        'content'     => omochix_glossary_content_html((string) $post->post_content),
        'sources'     => $sources,
        'verified_on' => omochix_glossary_date((string) $meta('glossary_verified_on')),
        'ai_assisted' => (bool) $meta('glossary_ai_assisted', false),
        'level'       => $levels[$level] ?? '',
        'category'    => is_array($cats) && isset($cats[0]) && $cats[0] instanceof WP_Term ? $cats[0] : null,
    ];
}

/** Text the on-page filter matches (title, reading, English, aliases), lowercased. */
function omochix_glossary_search_text(array $view) {
    $text = implode(' ', array_merge([$view['title'], $view['reading'], $view['en']], $view['aliases']));
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

/**
 * Related links of a term, only what a visitor may see: visible glossary
 * terms, published AI tools that are not discontinued, published pages under
 * /learn/, published practice items, visible industries.
 *
 * @param WP_Post $post Term.
 * @return array{glossary: array, tools: array, learn: array, practice: array, industries: array}
 */
function omochix_glossary_related($post) {
    $ids = static fn($k) => array_values(array_filter(array_map('intval', (array) get_post_meta($post->ID, $k, true))));
    $ok  = static function ($id, $type) {
        $p = get_post($id);
        return $p instanceof WP_Post && $type === $p->post_type && 'publish' === $p->post_status && '' === (string) $p->post_password ? $p : null;
    };
    $out = ['glossary' => [], 'tools' => [], 'learn' => [], 'practice' => [], 'industries' => []];
    foreach ($ids('related_glossary_ids') as $id) {
        $p = $ok($id, 'omx_glossary');
        if ($p && $p->ID !== $post->ID && omochix_core_glossary_visibility($p)['visible']) {
            $out['glossary'][] = ['url' => (string) get_permalink($p), 'title' => (string) get_the_title($p), 'summary' => (string) get_post_meta($p->ID, 'glossary_summary', true)];
        }
    }
    foreach ($ids('related_tool_ids') as $id) {
        $p = $ok($id, 'ai_tool');
        if ($p && !(function_exists('omochix_is_ai_tool_discontinued') && omochix_is_ai_tool_discontinued($p->ID))) {
            $out['tools'][] = ['url' => (string) get_permalink($p), 'title' => (string) get_the_title($p)];
        }
    }
    foreach ($ids('related_learn_page_ids') as $id) {
        $p = $ok($id, 'page');
        if ($p && 0 === strpos((string) get_page_uri($p), 'learn/')) {
            $out['learn'][] = ['url' => (string) get_permalink($p), 'title' => (string) get_the_title($p)];
        }
    }
    foreach ($ids('related_practice_ids') as $id) {
        $p = $ok($id, 'learn_practice');
        if ($p) {
            $out['practice'][] = ['url' => (string) get_permalink($p), 'title' => (string) get_the_title($p)];
        }
    }
    foreach ($ids('related_industry_ids') as $id) {
        $t = get_term($id, 'omx_industry');
        if ($t instanceof WP_Term && function_exists('omochix_core_industry_term_is_visible') && omochix_core_industry_term_is_visible($t)) {
            $link = get_term_link($t);
            if (is_string($link)) {
                $out['industries'][] = ['url' => $link, 'title' => (string) $t->name];
            }
        }
    }
    return $out;
}

/** URL of the list (/glossary/). */
function omochix_glossary_index_url() {
    $u = get_post_type_archive_link('omx_glossary');
    return is_string($u) ? $u : '';
}

// ------------------------------------------------------------------------ SEO

/** Whether this glossary page must not be indexed. */
function omochix_glossary_is_noindex() {
    $d = omochix_glossary_decide();
    return 'ok' !== $d['action'] || $d['preview'] || !$d['index'];
}

function omochix_glossary_canonical_url() {
    $d = omochix_glossary_decide();
    if ('ok' !== $d['action'] || omochix_glossary_is_noindex()) {
        return '';
    }
    if ('single' === $d['kind'] && $d['post']) {
        return (string) get_permalink($d['post']);
    }
    if ('category' === $d['kind'] && $d['term']) {
        $l = get_term_link($d['term']);
        return is_string($l) ? $l : '';
    }
    return omochix_glossary_index_url();
}

function omochix_glossary_slim_seo_index($indexed) {
    return omochix_glossary_is_request() && omochix_glossary_is_noindex() ? false : $indexed;
}
add_filter('slim_seo_robots_index', 'omochix_glossary_slim_seo_index', 30);

function omochix_glossary_wp_robots($robots) {
    if (!omochix_glossary_is_request() || !omochix_glossary_is_noindex()) {
        return $robots;
    }
    $robots = is_array($robots) ? $robots : [];
    unset($robots['index'], $robots['max-image-preview']);
    $robots['noindex'] = true;
    if (omochix_glossary_decide()['preview']) {
        $robots['nofollow'] = true;
        unset($robots['follow']);
    } else {
        $robots['follow'] = true;
    }
    return $robots;
}
add_filter('wp_robots', 'omochix_glossary_wp_robots', 30);

function omochix_glossary_canonical($url) {
    return omochix_glossary_is_request() ? omochix_glossary_canonical_url() : $url;
}
add_filter('slim_seo_canonical_url', 'omochix_glossary_canonical', 30);

function omochix_glossary_meta_title($title) {
    if (!omochix_glossary_is_request()) {
        return $title;
    }
    $d = omochix_glossary_decide();
    if ('single' === $d['kind'] && $d['post']) {
        /* translators: %s: term */
        return sprintf(__('%sとは？意味をわかりやすく解説｜AI用語集', 'omochix'), get_the_title($d['post']));
    }
    if ('category' === $d['kind'] && $d['term']) {
        /* translators: %s: category */
        return sprintf(__('%sの用語一覧｜AI用語集', 'omochix'), $d['term']->name);
    }
    return __('AI用語集｜生成AI・LLMの用語をわかりやすく解説', 'omochix');
}
add_filter('slim_seo_meta_title', 'omochix_glossary_meta_title', 30);

function omochix_glossary_meta_description($description) {
    if (!omochix_glossary_is_request()) {
        return $description;
    }
    $d = omochix_glossary_decide();
    if ('single' === $d['kind'] && $d['post']) {
        $s = (string) get_post_meta($d['post']->ID, 'glossary_summary', true);
        return '' !== $s ? $s : $description;
    }
    if ('category' === $d['kind'] && $d['term']) {
        $s = (string) get_term_meta($d['term']->term_id, 'glossary_category_lead', true);
        return '' !== $s ? $s : $description;
    }
    return __('生成AI・大規模言語モデル・AIエージェントなど、AIとテクノロジーの用語を、一次情報にもとづいてわかりやすく解説するOmochiXのAI用語集です。', 'omochix');
}
add_filter('slim_seo_meta_description', 'omochix_glossary_meta_description', 30);

/** Breadcrumbs: Home › AI用語集 › category › term. */
function omochix_glossary_breadcrumbs($links) {
    if (!omochix_glossary_is_request()) {
        return $links;
    }
    $d   = omochix_glossary_decide();
    $out = [['url' => home_url('/'), 'text' => __('Home', 'omochix')]];
    $index = omochix_glossary_index_url();
    $out[] = ['url' => $index, 'text' => __('AI用語集', 'omochix')];
    if ('single' === $d['kind'] && $d['post']) {
        $v = omochix_glossary_view($d['post']);
        if ($v['category']) {
            $l = get_term_link($v['category']);
            $out[] = ['url' => is_string($l) ? $l : '', 'text' => $v['category']->name];
        }
        $out[] = ['url' => (string) get_permalink($d['post']), 'text' => $v['title']];
    } elseif ('category' === $d['kind'] && $d['term']) {
        $l = get_term_link($d['term']);
        $out[] = ['url' => is_string($l) ? $l : '', 'text' => $d['term']->name];
    }
    return $out;
}
add_filter('slim_seo_breadcrumbs_links', 'omochix_glossary_breadcrumbs', 30);

/**
 * Structured data: DefinedTerm on an indexable term page, DefinedTermSet on
 * an indexable list or category (indexable terms only). Nothing is added to
 * a noindex page, and article types are taken off term pages.
 *
 * @param mixed $graph Slim SEO graph.
 * @return mixed
 */
function omochix_glossary_schema_graph($graph) {
    if (!is_array($graph) || !omochix_glossary_is_request()) {
        return $graph;
    }
    $d     = omochix_glossary_decide();
    $index = omochix_glossary_index_url();
    $set   = $index . '#glossary';
    if ('single' === $d['kind']) {
        $graph = array_values(array_filter($graph, static fn($e) => !(is_array($e) && in_array($e['@type'] ?? '', ['Article', 'BlogPosting', 'NewsArticle', 'SoftwareApplication', 'FAQPage'], true))));
    }
    if (omochix_glossary_is_noindex()) {
        return $graph;
    }
    if ('single' === $d['kind'] && $d['post']) {
        $v    = omochix_glossary_view($d['post']);
        $id   = $v['url'] . '#term';
        $term = ['@type' => 'DefinedTerm', '@id' => $id, 'name' => $v['title'], 'description' => $v['summary'], 'url' => $v['url'], 'inDefinedTermSet' => ['@type' => 'DefinedTermSet', '@id' => $set, 'name' => __('OmochiX AI用語集', 'omochix'), 'url' => $index]];
        $alt  = array_values(array_unique(array_filter(array_merge([$v['en']], $v['aliases']))));
        if ($alt) {
            $term['alternateName'] = $alt;
        }
        $main = $term;
    } else {
        $posts = 'category' === $d['kind'] && $d['term'] ? omochix_glossary_visible_terms($d['term']->slug) : omochix_glossary_visible_terms();
        $terms = [];
        foreach ($posts as $p) {
            if (omochix_core_glossary_visibility($p)['indexable']) {
                $u = (string) get_permalink($p);
                $terms[] = ['@type' => 'DefinedTerm', '@id' => $u . '#term', 'name' => (string) get_the_title($p), 'url' => $u];
            }
        }
        $page = 'category' === $d['kind'] ? omochix_glossary_canonical_url() : $index;
        $main = ['@type' => 'DefinedTermSet', '@id' => 'category' === $d['kind'] ? $page . '#glossary-category' : $set, 'name' => 'category' === $d['kind'] ? $d['term']->name : __('OmochiX AI用語集', 'omochix'), 'url' => $page, 'hasDefinedTerm' => $terms];
    }
    foreach ($graph as &$e) {
        if (is_array($e) && in_array($e['@type'] ?? '', ['WebPage', 'CollectionPage'], true)) {
            $e['mainEntity'] = ['@id' => $main['@id']];
        }
    }
    unset($e);
    $graph[] = $main;
    return $graph;
}
add_filter('slim_seo_schema_graph', 'omochix_glossary_schema_graph', 35);

// --------------------------------------------------------------------- assets

function omochix_glossary_enqueue_assets() {
    if (!omochix_glossary_is_request() || 'ok' !== omochix_glossary_decide()['action']) {
        return;
    }
    $file = get_theme_file_path('/assets/css/glossary.css');
    if (is_readable($file)) {
        $deps = wp_style_is('omochix-shell', 'enqueued') ? ['omochix-style', 'omochix-shell'] : ['omochix-style'];
        wp_enqueue_style('omochix-glossary', get_theme_file_uri('/assets/css/glossary.css'), $deps, (string) filemtime($file));
    }
}
add_action('wp_enqueue_scripts', 'omochix_glossary_enqueue_assets', 24);

/** Use the shared shell (left navigation, editorial footer) on glossary pages. */
function omochix_glossary_shell($enabled) {
    return $enabled || omochix_glossary_is_request();
}
add_filter('omochix_shell_enabled_for_request', 'omochix_glossary_shell');
// omochix-web2-module: glossary api=1 (end)
