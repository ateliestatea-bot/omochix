<?php
/**
 * Slim SEO integration and structured-data ownership boundaries.
 *
 * Slim SEO owns title, meta description, canonical, Open Graph, X Card,
 * Organization, WebSite and SearchAction. OmochiX Core exclusively owns the
 * future SoftwareApplication entity for ai_tool posts.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Use the Core data contract for AI tool descriptions unless an editor entered
 * a manual Slim SEO description.
 *
 * @param string $description Current Slim SEO description.
 * @param int    $object_id   Queried object ID.
 * @return string
 */
function omochix_slim_seo_ai_tool_description($description, $object_id) {
    if ('ai_tool' !== get_post_type($object_id)) {
        return $description;
    }

    $slim_seo_data = get_post_meta($object_id, 'slim_seo', true);
    if (is_array($slim_seo_data) && !empty($slim_seo_data['description'])) {
        return $description;
    }

    $short_description = get_post_meta($object_id, 'short_description', true);

    return $short_description
        ? sanitize_text_field(wp_strip_all_tags($short_description))
        : $description;
}
add_filter('slim_seo_meta_description', 'omochix_slim_seo_ai_tool_description', 10, 2);

/**
 * Apply OmochiX's schema ownership rules to Slim SEO's graph.
 *
 * Posts in the AI news category use NewsArticle. AI tool application schemas
 * are reserved for OmochiX Core, which appends its entity after priority 20.
 *
 * @param array<int, array<string, mixed>> $graph Slim SEO schema graph.
 * @return array<int, array<string, mixed>>
 */
function omochix_slim_seo_normalize_schema_graph($graph) {
    $reserved_types = [
        'Article',
        'NewsArticle',
        'BlogPosting',
        'SoftwareApplication',
        'WebApplication',
        'MobileApplication',
    ];

    $post_id = get_queried_object_id();
    $is_news = is_singular('post') && $post_id && has_category('ai-news', $post_id);

    foreach ($graph as $index => $entity) {
        if (!is_array($entity)) {
            continue;
        }

        $types = isset($entity['@type']) ? (array) $entity['@type'] : [];

        if ($is_news && in_array('Article', $types, true)) {
            $graph[$index]['@type'] = 'NewsArticle';
        }
    }

    if (!is_singular('ai_tool')) {
        return $graph;
    }

    return array_values(array_filter($graph, static function ($entity) use ($reserved_types) {
        if (!is_array($entity)) {
            return true;
        }

        $types = isset($entity['@type']) ? (array) $entity['@type'] : [];

        return !array_intersect($reserved_types, $types);
    }));
}
add_filter('slim_seo_schema_graph', 'omochix_slim_seo_normalize_schema_graph', 20);

/**
 * Remove a breadcrumb level that duplicates the name of the level right after it.
 *
 * Slim SEO's auto-generated breadcrumb trail can include both the WordPress
 * "posts page" ancestor and the post's primary category ancestor. When both
 * happen to carry the same display name (e.g. a posts page and a category
 * that are both named 「AIニュース」), the visible breadcrumb only shows one
 * level but the structured data shows two, which no longer matches Google's
 * breadcrumb markup guidance. This is a no-op for any trail that has no such
 * consecutive duplicate, so other post types and categories are unaffected.
 *
 * Hooked on Slim SEO's per-entity `slim_seo_schema_breadcrumblist` filter
 * (not the aggregate `slim_seo_schema_graph`) so this only ever touches the
 * BreadcrumbList entity itself.
 *
 * @param array<string, mixed> $schema Slim SEO's BreadcrumbList schema entity.
 * @return array<string, mixed>
 */
function omochix_dedupe_breadcrumb_schema($schema) {
    if (!is_array($schema) || !isset($schema['itemListElement']) || !is_array($schema['itemListElement'])) {
        return $schema;
    }

    $items = $schema['itemListElement'];

    $deduped = [];
    foreach ($items as $i => $item) {
        $next = $items[$i + 1] ?? null;
        if (
            is_array($item) && is_array($next)
            && isset($item['name'], $next['name'])
            && $item['name'] === $next['name']
        ) {
            // Drop the earlier, less specific level (e.g. the posts page);
            // keep the one that follows (e.g. the actual category), which
            // is what the visible on-page breadcrumb links to.
            continue;
        }
        $deduped[] = $item;
    }

    if (count($deduped) === count($items)) {
        return $schema;
    }

    foreach ($deduped as $position => &$deduped_item) {
        if (is_array($deduped_item)) {
            $deduped_item['position'] = $position + 1;
        }
    }
    unset($deduped_item);

    $schema['itemListElement'] = array_values($deduped);

    return $schema;
}
add_filter('slim_seo_schema_breadcrumblist', 'omochix_dedupe_breadcrumb_schema');

/**
 * Determine whether the current archive or parameterized view must not be indexed.
 *
 * A term needs at least two public objects for the MVP index threshold. Editors
 * can revisit this threshold when taxonomy landing content is introduced.
 *
 * @return bool
 */
function omochix_is_noindex_archive_request() {
    if (is_author() || is_date() || is_attachment() || is_tax(['ai_tool_feature', 'ai_tool_tag'])) {
        return true;
    }

    $news_parameters = ['news_category', 'news_tag', 'news_order', 'news_search'];
    $tool_parameters = ['tool_search', 'tool_category', 'pricing', 'japanese', 'platform', 'tool_order'];
    $request_keys    = array_keys($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filters.

    if (is_home() && array_intersect($news_parameters, $request_keys)) {
        return true;
    }

    if (is_post_type_archive('ai_tool') && array_intersect($tool_parameters, $request_keys)) {
        return true;
    }

    if (is_category() || is_tax('ai_tool_category')) {
        $term = get_queried_object();
        return $term instanceof WP_Term && (int) $term->count < 2;
    }

    return false;
}

/**
 * Extend WordPress robots directives when Slim SEO is unavailable.
 *
 * @param array<string, string|bool> $robots Robots directives.
 * @return array<string, string|bool>
 */
function omochix_filter_archive_robots($robots) {
    if (omochix_is_noindex_archive_request()) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }

    return $robots;
}
add_filter('wp_robots', 'omochix_filter_archive_robots');

/**
 * Let Slim SEO remove canonical output for every OmochiX noindex request.
 *
 * @param bool $indexed Whether Slim SEO considers the request indexable.
 * @return bool
 */
function omochix_filter_slim_seo_indexability($indexed) {
    return omochix_is_noindex_archive_request() ? false : $indexed;
}
add_filter('slim_seo_robots_index', 'omochix_filter_slim_seo_indexability');

/**
 * Exclude non-indexable AI tool facets from the Slim SEO sitemap index.
 *
 * @param string[] $taxonomies Sitemap taxonomy names.
 * @return string[]
 */
function omochix_filter_slim_seo_sitemap_taxonomies($taxonomies) {
    return array_values(array_diff($taxonomies, ['ai_tool_feature', 'ai_tool_tag']));
}
add_filter('slim_seo_sitemap_taxonomies', 'omochix_filter_slim_seo_sitemap_taxonomies');

/**
 * Exclude thin term archives from taxonomy sitemap output.
 *
 * @param WP_Term[]|int[] $terms      Retrieved terms.
 * @param string[]        $taxonomies Requested taxonomies.
 * @return WP_Term[]|int[]
 */
function omochix_filter_thin_sitemap_terms($terms, $taxonomies) {
    if (!get_query_var('ss_sitemap') || !array_intersect(['category', 'ai_tool_category'], (array) $taxonomies)) {
        return $terms;
    }

    return array_values(array_filter($terms, static function ($term) {
        return !$term instanceof WP_Term || (int) $term->count >= 2;
    }));
}
add_filter('get_terms', 'omochix_filter_thin_sitemap_terms', 10, 2);

/**
 * Keep noindex search pages crawlable so robots meta can be observed.
 *
 * @return string
 */
function omochix_filter_slim_seo_robots_txt() {
    return '';
}
add_filter('slim_seo_robots_txt', 'omochix_filter_slim_seo_robots_txt');
