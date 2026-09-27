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
 * Enrich Slim SEO's Organization and WebSite entities with brand identity
 * fields Slim SEO does not populate on its own.
 *
 * Slim SEO's Organization node only ever contains `@type`, `@id`, `url`, and
 * `name` (plus `logo`/`image` when a Site Icon or Custom Logo is configured
 * in Settings > General / Customizer — a WordPress admin setting, not a code
 * concern). `alternateName`, `description`, and `sameAs` are never populated
 * by Slim SEO itself, so search engines and AI answer engines have no
 * machine-readable definition of what OmochiX is beyond its bare name. This
 * fills in only those three fields, using the same real, live social URLs
 * the site footer already links to (see omochix_get_social_links()) — never
 * placeholder or unverified URLs.
 *
 * @param array<int, array<string, mixed>> $graph Slim SEO schema graph.
 * @return array<int, array<string, mixed>>
 */
function omochix_enrich_brand_entity_schema($graph) {
    $description   = __('OmochiX（オモチックス）は、AIニュース・AI開発・AIツール・AI活用を扱う日本のAI専門メディア＆プラットフォームです。', 'omochix');
    $alternate_names = ['オモチックス', 'OmochiX AI'];
    $same_as       = wp_list_pluck(omochix_get_social_links(), 'url');

    foreach ($graph as $index => $entity) {
        if (!is_array($entity) || !isset($entity['@type'])) {
            continue;
        }

        if ('Organization' === $entity['@type']) {
            $graph[$index] += [
                'alternateName' => $alternate_names,
                'description'   => $description,
                'sameAs'        => $same_as,
            ];
        }

        if ('WebSite' === $entity['@type']) {
            $graph[$index] += [
                'alternateName' => $alternate_names,
            ];
        }
    }

    return $graph;
}
add_filter('slim_seo_schema_graph', 'omochix_enrich_brand_entity_schema', 20);

/**
 * Remove a breadcrumb level that duplicates the name of the level right after it,
 * and point the AI News category level at the URL it now permanently redirects to.
 *
 * Slim SEO's auto-generated breadcrumb trail can include both the WordPress
 * "posts page" ancestor and the post's primary category ancestor. When both
 * happen to carry the same display name (e.g. a posts page and a category
 * that are both named 「AIニュース」), the visible breadcrumb only shows one
 * level but the structured data shows two, which no longer matches Google's
 * breadcrumb markup guidance. This is a no-op for any trail that has no such
 * consecutive duplicate, so other post types and categories are unaffected.
 *
 * Separately, /category/ai-news/ now permanently redirects (301, via a Slim
 * SEO redirect rule) to the WordPress posts page at /ai-news/, since both
 * expose the same "AIニュース" content by design. The surviving breadcrumb
 * item for that category should reference the final URL directly instead of
 * one that immediately redirects; see omochix_get_category_url() for the
 * single place that decision is made for on-page links.
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

    $original_items = $schema['itemListElement'];
    $items          = $original_items;

    $ai_news_category = get_category_by_slug('ai-news');
    if ($ai_news_category instanceof WP_Term) {
        $redirected_url = get_category_link($ai_news_category);
        $canonical_url  = omochix_get_category_url($ai_news_category);

        if ($redirected_url && $canonical_url && $redirected_url !== $canonical_url) {
            foreach ($items as &$item) {
                if (is_array($item) && isset($item['item']) && $item['item'] === $redirected_url) {
                    $item['item'] = $canonical_url;
                }
            }
            unset($item);
        }
    }

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

    if ($deduped === $original_items) {
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
    if (omochix_is_noindex_prompt_request()) {
        return true;
    }

    if (is_author() || is_date() || is_attachment() || is_tax(['ai_tool_feature', 'ai_tool_tag'])) {
        return true;
    }

    $news_parameters = ['news_category', 'news_tag', 'news_order', 'news_search'];
    $tool_parameters = ['tool_search', 'tool_category', 'pricing', 'japanese', 'platform', 'tool_order'];
    $request_keys    = array_keys($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filters.

    if (is_home() && array_intersect($news_parameters, $request_keys)) {
        return true;
    }

    if ((is_post_type_archive('ai_tool') || is_tax('ai_tool_category')) && array_intersect($tool_parameters, $request_keys)) {
        return true;
    }

    if (is_category() || is_tag() || is_tax('ai_tool_category')) {
        $term = get_queried_object();
        return $term instanceof WP_Term && (int) $term->count < 2;
    }

    return false;
}

/**
 * Determine whether a Prompt Library request must not be indexed.
 *
 * Applies the existing archive policy to prompts:
 * - Parameterized filter/search/sort views of /prompts/ and prompt term
 *   archives are noindex (same as the AI tool and news filters), including
 *   their /page/N/ variants.
 * - prompt_category is the primary, curated grouping (like ai_tool_category):
 *   indexable once it has at least two public prompts.
 * - prompt_model is a secondary facet (like ai_tool_feature / ai_tool_tag)
 *   whose starter terms also mix models with media types ("Image", "Video"),
 *   so its archives stay navigable but noindex and out of the sitemap.
 *
 * @return bool
 */
function omochix_is_noindex_prompt_request() {
    if (is_tax('prompt_model')) {
        return true;
    }

    $prompt_parameters = ['prompt_search', 'prompt_category', 'prompt_model', 'prompt_difficulty', 'prompt_order', 'prompt_sort', 'related_tool'];
    $request_keys      = array_keys($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public filters.

    if ((is_post_type_archive('prompt') || is_tax('prompt_category')) && array_intersect($prompt_parameters, $request_keys)) {
        return true;
    }

    if (is_tax('prompt_category')) {
        $term = get_queried_object();
        return $term instanceof WP_Term && (int) $term->count < 2;
    }

    return false;
}

/**
 * Give prompts without an excerpt or description body a meta description
 * from their usage (or prompt body) text.
 *
 * Slim SEO's automatic description is built from the excerpt or content,
 * which prompts often leave empty because the prompt itself lives in meta.
 * A manual Slim SEO description, an excerpt, or post content always wins, so
 * this only fills the gap that would otherwise output no description at all.
 *
 * @param string $description Current Slim SEO description (may be a template).
 * @param int    $object_id   Queried object ID.
 * @return string
 */
function omochix_slim_seo_prompt_description($description, $object_id) {
    if ('prompt' !== get_post_type($object_id)) {
        return $description;
    }

    $slim_seo_data = get_post_meta($object_id, 'slim_seo', true);
    if (is_array($slim_seo_data) && !empty($slim_seo_data['description'])) {
        return $description;
    }

    $post = get_post($object_id);
    if (!$post || '' !== trim($post->post_excerpt) || '' !== trim(wp_strip_all_tags($post->post_content))) {
        return $description;
    }

    foreach (['prompt_usage', 'prompt_body'] as $meta_key) {
        $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) get_post_meta($object_id, $meta_key, true))));
        // Slim SEO renders {{ }} as template variables; keep prompt text literal.
        $text = str_replace(['{{', '}}'], ['{ {', '} }'], $text);
        if ('' !== $text) {
            return function_exists('mb_strimwidth') ? mb_strimwidth($text, 0, 240, '…', 'UTF-8') : $text;
        }
    }

    return $description;
}
add_filter('slim_seo_meta_description', 'omochix_slim_seo_prompt_description', 10, 2);

/**
 * Fill in the meta description Slim SEO otherwise leaves empty on the AI
 * Tool archive, the Prompt Library archive, and Prompt Library category
 * archives.
 *
 * Slim SEO's own CPT-archive description defaults to an empty string, and
 * its taxonomy-term description falls back to the term's own (here, never
 * populated) "description" field — so neither ever renders anything unless
 * an editor configures one by hand. This only ever fills that gap: a real
 * per-term Slim SEO description, or a manually authored WordPress term
 * description, always wins here, exactly like the manual per-post override
 * already respected by omochix_slim_seo_ai_tool_description() and
 * omochix_slim_seo_prompt_description() above. Paginated and
 * parameterized/filtered views of these same archives (search, category
 * chip, related_tool, prompt_model, etc.) still get a description from this
 * same filter, since a description is harmless on a noindex page and this
 * function never touches robots/canonical -- those stay governed solely by
 * omochix_is_noindex_archive_request() and omochix_is_noindex_prompt_request().
 *
 * @param string $description Current Slim SEO description (template or manual value).
 * @param int    $object_id   Queried object ID (unused: context is read from conditionals).
 * @return string
 */
function omochix_slim_seo_archive_description($description, $object_id) {
    unset($object_id);

    if (is_post_type_archive('ai_tool') || is_post_type_archive('prompt')) {
        if ('' !== trim((string) $description)) {
            return $description;
        }

        return is_post_type_archive('ai_tool')
            ? __('ChatGPT、Claude、Geminiをはじめ、生成AI・画像生成・動画生成・開発・業務効率化などのAIツールを紹介。特徴や料金、日本語対応、用途を比較し、自分に合ったAIツールを探せます。', 'omochix')
            : __('ChatGPT、Claude、Geminiなどで使える実践的なAIプロンプトを紹介。仕事、マーケティング、開発、文章作成、画像・動画生成など、目的別にすぐ使えるプロンプトを探せます。', 'omochix');
    }

    if (is_tax('prompt_category')) {
        $term = get_queried_object();
        if (!($term instanceof WP_Term)) {
            return $description;
        }

        $slim_seo_term_meta  = get_term_meta($term->term_id, 'slim_seo', true);
        $has_manual_slim_seo = is_array($slim_seo_term_meta) && !empty($slim_seo_term_meta['description']);
        $has_term_description = '' !== trim(wp_strip_all_tags((string) $term->description));
        if ($has_manual_slim_seo || $has_term_description) {
            return $description;
        }

        return omochix_get_prompt_category_description($term);
    }

    return $description;
}
add_filter('slim_seo_meta_description', 'omochix_slim_seo_archive_description', 10, 2);

/**
 * Return a natural, search-intent-matched meta description for a Prompt
 * Library category.
 *
 * The 8 launch categories each get a hand-written description. Any future
 * category not in this map (added via wp-admin without a matching code
 * change) falls back to a templated sentence built from the term's own
 * name, so a new category never ships with an empty description.
 *
 * @param WP_Term $term prompt_category term.
 * @return string
 */
function omochix_get_prompt_category_description($term) {
    $descriptions = [
        'sales'        => '営業活動で使えるAIプロンプトを紹介。商談準備、提案、メール作成、顧客対応など、ChatGPTやClaudeで使える実践的なプロンプトを探せます。',
        'marketing'    => 'マーケティングで使えるAIプロンプトを紹介。SNS、広告、企画、分析、コンテンツ制作など、実務ですぐ使えるプロンプトを探せます。',
        'development'  => '開発・プログラミングで使えるAIプロンプトを紹介。コード生成、レビュー、設計、デバッグなど、AIを開発業務に活用するプロンプトを探せます。',
        'writing'      => '文章作成で使えるAIプロンプトを紹介。記事、メール、構成、リライトなど、ChatGPTやClaudeを文章制作に活用するプロンプトを探せます。',
        'productivity' => '仕事効率化に使えるAIプロンプトを紹介。タスク整理、意思決定、計画、情報整理など、日々の業務を効率化するプロンプトを探せます。',
        'documents'    => '資料・ドキュメント作成で使えるAIプロンプトを紹介。要約、FAQ、ガイドライン、報告書など、業務文書の作成を効率化できます。',
        'image'        => 'AI画像生成で使えるプロンプトを紹介。商品画像、アイキャッチ、ビジュアル制作など、画像生成AIで使える実践的な指示文を探せます。',
        'video'        => 'AI動画生成で使えるプロンプトを紹介。動画構成、台本、image-to-video、モーション指示など、AI動画制作に使えるプロンプトを探せます。',
    ];

    if (isset($descriptions[$term->slug])) {
        return $descriptions[$term->slug];
    }

    return sprintf(
        /* translators: %s: Prompt Library category name. */
        __('%sで使えるAIプロンプトを紹介。ChatGPTやClaudeなどで活用できる実践的なプロンプトを、目的や難易度から探せます。', 'omochix'),
        $term->name
    );
}

/**
 * Exclude the noindex prompt_model facet from the Slim SEO sitemap index.
 *
 * @param string[] $taxonomies Sitemap taxonomy names.
 * @return string[]
 */
function omochix_filter_prompt_sitemap_taxonomies($taxonomies) {
    return array_values(array_diff((array) $taxonomies, ['prompt_model']));
}
add_filter('slim_seo_sitemap_taxonomies', 'omochix_filter_prompt_sitemap_taxonomies');

/**
 * Exclude thin prompt_category archives (fewer than two public prompts) from
 * taxonomy sitemap output, matching the category / ai_tool_category policy.
 *
 * @param WP_Term[]|int[] $terms      Retrieved terms.
 * @param string[]        $taxonomies Requested taxonomies.
 * @return WP_Term[]|int[]
 */
function omochix_filter_thin_prompt_sitemap_terms($terms, $taxonomies) {
    if (!get_query_var('ss_sitemap') || !in_array('prompt_category', (array) $taxonomies, true)) {
        return $terms;
    }

    return array_values(array_filter($terms, static function ($term) {
        return !($term instanceof WP_Term) || 'prompt_category' !== $term->taxonomy || (int) $term->count >= 2;
    }));
}
add_filter('get_terms', 'omochix_filter_thin_prompt_sitemap_terms', 10, 2);

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
 * Exclude thin term archives, and the AI News category, from taxonomy sitemap output.
 *
 * The "ai-news" category is excluded unconditionally (regardless of its post
 * count) because its archive URL, /category/ai-news/, now permanently
 * redirects to the WordPress posts page at /ai-news/ (see the Slim SEO
 * redirect rule and omochix_get_category_url()). Submitting a URL in the
 * sitemap that only ever 301s elsewhere provides no value. No other category
 * is affected by this exclusion.
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
        if (!$term instanceof WP_Term) {
            return true;
        }

        if ('category' === $term->taxonomy && 'ai-news' === $term->slug) {
            return false;
        }

        return (int) $term->count >= 2;
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
