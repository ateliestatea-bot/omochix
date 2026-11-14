<?php
/**
 * Lightweight search suggestions (no new search engine, no external service).
 *
 * Design
 *  - ONE cacheable, read-only JSON index of public titles (tools, categories, Learn pages, Labs tools, prompts, latest news,
 *    section shortcuts, popular themes). The browser fetches it once (lazily, on first focus of a search box) and filters
 *    locally, so typing causes NO request and nothing the visitor types ever reaches the server or the logs.
 *  - Server cost: one DB pass per 15 minutes (transient + stampede lock), invalidated when content changes.
 *  - Only published, non-password-protected content. No user data, no private meta, no auth required (read-only public data).
 *  - Response is size-capped; Cache-Control public for 5 minutes (+ ETag) so CDN / browser cache absorb bursts.
 *
 * Search results page: also shows matching Labs tools and Learn pages (they are not WP search post types), with all
 * output escaped. The search term itself is only echoed through esc_html / esc_attr.
 */
if (!defined('ABSPATH')) { exit; }

const OMOCHIX_SUGGEST_CAP = 420;           // hard cap on entries (payload stays well under ~50KB)
const OMOCHIX_SUGGEST_TRANSIENT = 'omochix_suggest_index_v2';   // v2: discards any index cached before the public-context rebuild (D3)

function omochix_suggest_popular_terms() {
    return apply_filters('omochix_suggest_popular_terms', ['ChatGPT', 'Claude', 'Gemini', 'Midjourney', 'Copilot', 'AIエージェント']);
}

function omochix_suggest_entry($title, $url, $type, $keywords = '') {
    $title = trim(wp_strip_all_tags((string) $title));
    if ('' === $title || !$url) { return null; }
    $e = ['t' => mb_substr($title, 0, 80), 'u' => esc_url_raw((string) $url), 'y' => $type];
    $kw = trim(wp_strip_all_tags((string) $keywords));
    if ('' !== $kw) { $e['k'] = mb_substr($kw, 0, 120); }
    return $e;
}

/** Build the index (DB work). Public data only. */
function omochix_suggest_build_index() {
    $out = [];
    $add = static function ($e) use (&$out) { if ($e && count($out) < OMOCHIX_SUGGEST_CAP) { $out[] = $e; } };

    // Sections (from the navigation registry: published only).
    foreach (omochix_nav_items('nav') as $it) {
        if ($it['url'] && in_array($it['group'], ['primary'], true)) { $add(omochix_suggest_entry($it['label'], $it['url'], 'section', '')); }
    }
    // Popular themes -> normal site search.
    foreach (omochix_suggest_popular_terms() as $term) {
        $add(omochix_suggest_entry($term, add_query_arg('s', $term, home_url('/')), 'theme'));
    }
    // AI tools (published, not password protected).
    if (post_type_exists('ai_tool')) {
        $tools = get_posts(['post_type' => 'ai_tool', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 200, 'no_found_rows' => true, 'orderby' => 'title', 'order' => 'ASC']);
        foreach ($tools as $p) {
            $add(omochix_suggest_entry($p->post_title, get_permalink($p), 'tool', get_post_meta($p->ID, 'company_name', true)));
        }
    }
    // Categories.
    foreach (['category' => 'category', 'ai_tool_category' => 'tool-category', 'prompt_category' => 'prompt-category'] as $tax => $type) {
        if (!taxonomy_exists($tax) || !is_taxonomy_viewable($tax)) { continue; }
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true, 'number' => 40]);
        if (is_wp_error($terms)) { continue; }
        foreach ($terms as $t) {
            if ('category' === $tax && in_array($t->slug, ['uncategorized'], true)) { continue; }
            $link = get_term_link($t);
            if (!is_wp_error($link)) { $add(omochix_suggest_entry($t->name, $link, $type)); }
        }
    }
    // Learn pages (children of the Learn page) and practice items.
    $learn = get_page_by_path('learn');
    if ($learn instanceof WP_Post && 'publish' === $learn->post_status) {
        $pages = get_pages(['child_of' => $learn->ID, 'post_status' => 'publish', 'number' => 80, 'sort_column' => 'post_title']);
        // The page's own password field, NOT post_password_required(): that depends on the visitor's cookie (D3).
        foreach ($pages as $p) { if ('' === (string) $p->post_password) { $add(omochix_suggest_entry($p->post_title, get_permalink($p), 'learn')); } }
    }
    if (post_type_exists('learn_practice')) {
        foreach (get_posts(['post_type' => 'learn_practice', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 40, 'no_found_rows' => true]) as $p) {
            $add(omochix_suggest_entry($p->post_title, get_permalink($p), 'learn'));
        }
    }
    // Labs tools (theme catalog).
    if (function_exists('omochix_labs_get_tools') && function_exists('omochix_labs_url')) {
        foreach (omochix_labs_get_tools() as $tool) {
            $add(omochix_suggest_entry(isset($tool['name']) ? $tool['name'] : (isset($tool['title']) ? $tool['title'] : ''), omochix_labs_url($tool), 'labs', isset($tool['category']) ? $tool['category'] : ''));
        }
    }
    // Prompts, then latest news (recent first), up to the cap.
    if (post_type_exists('prompt')) {
        foreach (get_posts(['post_type' => 'prompt', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 120, 'no_found_rows' => true]) as $p) { $add(omochix_suggest_entry($p->post_title, get_permalink($p), 'prompt')); }
    }
    foreach (get_posts(['post_type' => 'post', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 80, 'no_found_rows' => true, 'ignore_sticky_posts' => true]) as $p) { $add(omochix_suggest_entry($p->post_title, get_permalink($p), 'news')); }
    return $out;
}

function omochix_suggest_index() {
    $cached = get_transient(OMOCHIX_SUGGEST_TRANSIENT);
    if (is_array($cached)) { return $cached; }
    $lock = OMOCHIX_SUGGEST_TRANSIENT . '_lock';
    if (get_transient($lock)) { return ['items' => [], 'v' => 'warming']; }   // another request is building: answer empty, client retries later
    set_transient($lock, 1, 30);
    // Build as an anonymous visitor: whoever triggers the rebuild (an editor, a visitor holding a post-password cookie),
    // the shared public index must contain the same public data only (D3).
    $user = get_current_user_id();
    wp_set_current_user(0);
    try {
        $items = omochix_suggest_build_index();
    } finally {
        // Restore the caller even when the build throws (R3). On a throw nothing is cached and the lock is kept until it expires
        // (30s), so a failing build is retried at most twice a minute; until then the endpoint answers "warming" (no-store).
        wp_set_current_user($user);
    }
    $data = ['items' => $items, 'v' => substr(md5(wp_json_encode($items)), 0, 12)];
    set_transient(OMOCHIX_SUGGEST_TRANSIENT, $data, 15 * MINUTE_IN_SECONDS);
    delete_transient($lock);
    return $data;
}

function omochix_suggest_flush($id = 0) {
    // Autosaves and revisions do not change public content: no rebuild (D5).
    if (is_numeric($id) && $id && 'save_post' === current_action() && (wp_is_post_revision((int) $id) || wp_is_post_autosave((int) $id))) {
        return;
    }
    delete_transient(OMOCHIX_SUGGEST_TRANSIENT);
}
foreach (['save_post', 'deleted_post', 'trashed_post', 'edited_term', 'created_term', 'delete_term'] as $omochix_hook) { add_action($omochix_hook, 'omochix_suggest_flush'); }

add_action('rest_api_init', static function () {
    register_rest_route('omochix/v1', '/suggest-index', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',   // read-only, public, published titles only
        'callback'            => static function () {
            $data = omochix_suggest_index();
            if ('warming' === $data['v']) {
                // Temporary answer while another request rebuilds (R2): never let a shared cache keep it. The client treats a
                // non-2xx answer as "no suggestions yet" (normal search still works) and retries on the next focus.
                $res = new WP_REST_Response($data, 503);
                $res->header('Cache-Control', 'no-store');
                $res->header('Retry-After', '5');
                return $res;
            }
            $res = new WP_REST_Response($data);   // a real index, even an empty one, carries a content hash as "v"
            $res->header('Cache-Control', 'public, max-age=300, s-maxage=300');
            $res->header('ETag', '"' . $data['v'] . '"');
            return $res;
        },
    ]);
});

/**
 * Front end: nothing is loaded before the page is done. A tiny inline loader injects the (cached) script after window "load",
 * and the script itself fetches its stylesheet and the JSON index only on the first focus of a search box, so the suggest
 * feature adds no render-blocking CSS, no pre-FCP JS and no pre-FCP network use. Without JS the normal search form works.
 */
add_action('wp_footer', static function () {
    if (is_admin() || is_feed()) { return; }
    $js  = get_theme_file_uri('/assets/js/search-suggest.js') . '?ver=' . filemtime(get_theme_file_path('/assets/js/search-suggest.js'));
    $css = get_theme_file_uri('/assets/css/search-suggest.css') . '?ver=' . filemtime(get_theme_file_path('/assets/css/search-suggest.css'));
    $cfg = [
        'css'    => esc_url_raw($css),
        'index'  => esc_url_raw(rest_url('omochix/v1/suggest-index')),
        'search' => esc_url_raw(home_url('/')),
        'types'  => ['section' => __('メニュー', 'omochix'), 'theme' => __('人気の検索', 'omochix'), 'tool' => __('AIツール', 'omochix'), 'category' => __('カテゴリー', 'omochix'), 'tool-category' => __('ツールの用途', 'omochix'), 'prompt-category' => __('プロンプト', 'omochix'), 'learn' => 'Learn', 'labs' => 'Labs', 'prompt' => 'Prompts', 'news' => __('ニュース', 'omochix')],
        'i18n'   => ['all' => __('「%s」を全体から検索', 'omochix'), 'label' => __('検索候補', 'omochix'), 'none' => __('候補はありません。Enterで全体検索できます', 'omochix')],
    ];
    echo '<script>window.omochixSuggest=' . wp_json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';';
    echo "addEventListener('load',function(){var s=document.createElement('script');s.src=" . wp_json_encode(esc_url_raw($js), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ";s.async=true;document.head.appendChild(s)});</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-encoded with HEX_TAG/AMP, URLs escaped
}, 30);

/** Labs tools and Learn pages that match a search term (they are not WP search post types). Escaped by the caller. */
function omochix_search_related($term, $limit = 3) {
    $term = trim((string) $term);
    $out = ['labs' => [], 'learn' => []];
    if ('' === $term || mb_strlen($term) < 2 || mb_strlen($term) > 60) { return $out; }
    if (function_exists('omochix_labs_filter_tools') && function_exists('omochix_labs_url')) {
        foreach (array_slice(omochix_labs_filter_tools(['cat' => '', 'q' => $term]), 0, $limit) as $tool) {
            $out['labs'][] = ['title' => isset($tool['name']) ? $tool['name'] : (isset($tool['title']) ? $tool['title'] : ''), 'url' => omochix_labs_url($tool)];
        }
    }
    $learn = get_page_by_path('learn');
    if ($learn instanceof WP_Post && 'publish' === $learn->post_status) {
        $q = new WP_Query(['post_type' => 'page', 'post_status' => 'publish', 'has_password' => false, 'post_parent__in' => array_merge([$learn->ID], wp_list_pluck(get_pages(['child_of' => $learn->ID, 'post_status' => 'publish']), 'ID')), 's' => $term, 'posts_per_page' => $limit, 'no_found_rows' => true]);
        foreach ($q->posts as $p) { $out['learn'][] = ['title' => get_the_title($p), 'url' => get_permalink($p)]; }
    }
    return $out;
}

/**
 * Tools an article actually talks about: a tool counts only when its name appears as a whole word in the post's TITLE or one of its
 * TAGS (names shorter than 3 characters are ignored). No keyword stuffing: nothing is added when there is no explicit match.
 * Uses the cached suggest index, so it costs no extra query.
 *
 * @return array[] each ['title' => , 'url' => ]
 */
function omochix_article_mentioned_tools($post, $limit = 3) {
    $post = get_post($post);
    if (!$post instanceof WP_Post) { return []; }
    $hay = [$post->post_title];
    foreach ((array) get_the_tags($post->ID) as $tag) { if ($tag instanceof WP_Term) { $hay[] = $tag->name; } }
    $text = mb_strtolower(implode(' | ', $hay), 'UTF-8');
    // Longest names first; once a name matched, it is removed from the text so a shorter name it contains
    // ("Claude" inside "Claude Code") is only counted when it also appears on its own (D6).
    $tools = array_values(array_filter(omochix_suggest_index()['items'], static fn($e) => 'tool' === $e['y']));
    usort($tools, static fn($a, $b) => mb_strlen($b['t']) <=> mb_strlen($a['t']));
    $found = [];
    foreach ($tools as $e) {
        $name = mb_strtolower($e['t'], 'UTF-8');
        if (mb_strlen($name) < 3) { continue; }
        $ascii   = (bool) preg_match('/^[\x20-\x7e]+$/', $name);
        $pattern = $ascii ? '/(?<![a-z0-9])' . preg_quote($name, '/') . '(?![a-z0-9])/u' : '/' . preg_quote($name, '/') . '/u';
        if (preg_match($pattern, $text)) {
            $found[] = ['title' => $e['t'], 'url' => $e['u']];
            $text = (string) preg_replace($pattern, ' ', $text);
            if (count($found) >= $limit) { break; }
        }
    }
    return $found;
}

/** Article hand-off: mentioned tools (explicit matches only) + the AI development flow for development posts. */
function omochix_article_flow($post = null) {
    $post = get_post($post);
    if (!$post instanceof WP_Post || 'post' !== $post->post_type) { return; }
    $tools = omochix_article_mentioned_tools($post);
    $is_dev = has_category('ai-development', $post);
    if (!$tools && !$is_dev) { return; }
    $tools_url = function_exists('omochix_nav_item') ? (omochix_nav_item('tools')['url'] ?? '') : '';
    ?>
    <aside class="next-steps next-steps--article" aria-labelledby="article-flow-title">
        <h2 class="next-steps__title" id="article-flow-title"><?php esc_html_e('この記事から次へ', 'omochix'); ?></h2>
        <?php if ($tools) : ?>
            <p class="next-steps__lead"><?php esc_html_e('記事で触れているAIツールの詳細・料金・比較はこちら。', 'omochix'); ?></p>
            <ul class="next-steps__list">
                <?php foreach ($tools as $t) : ?><li><a href="<?php echo esc_url($t['url']); ?>"><strong><?php echo esc_html($t['title']); ?></strong><span><?php esc_html_e('AIツールの詳細を見る', 'omochix'); ?></span></a></li><?php endforeach; ?>
                <?php if ($tools_url) : ?><li><a href="<?php echo esc_url($tools_url); ?>"><strong><?php esc_html_e('AIツール一覧', 'omochix'); ?></strong><span><?php esc_html_e('目的別に比べて選ぶ', 'omochix'); ?></span></a></li><?php endif; ?>
            </ul>
        <?php endif; ?>
        <?php if ($is_dev) : ?>
            <p class="next-steps__lead"><?php esc_html_e('作る人向けの学び方と、すぐ試せる道具。', 'omochix'); ?></p>
            <ul class="next-steps__list">
                <?php foreach (['learn' => __('基礎から学び直す', 'omochix'), 'prompts' => __('プロンプトで試す', 'omochix'), 'labs' => __('Labsで道具を使ってみる', 'omochix')] as $id => $text) : $it = omochix_nav_item($id); if (!$it || !$it['url']) { continue; } ?>
                    <li><a href="<?php echo esc_url($it['url']); ?>"><strong><?php echo esc_html($it['label']); ?></strong><span><?php echo esc_html($text); ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>
    <?php
}
