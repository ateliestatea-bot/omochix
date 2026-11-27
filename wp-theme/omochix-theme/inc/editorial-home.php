<?php
/** Editorial homepage presentation; no data migrations or public URL changes. */
if (!defined('ABSPATH')) { exit; }

function omochix_editorial_icon($name) {
    $allowed = ['news', 'tools', 'code', 'learn', 'prompt', 'labs', 'chat', 'video', 'community', 'about', 'arrow'];
    if (!in_array($name, $allowed, true)) { return ''; }
    // Only fixed, theme-owned SVG files may be inlined. No post/user input.
    return file_get_contents(get_theme_file_path('/assets/icons/' . $name . '.svg'));
}

/** Home shortcut destinations (registry surface "home"). Same shape as before; `id` and `status` are new. */
function omochix_editorial_destinations() {
    $out = [];
    foreach (omochix_nav_items('home') as $it) {
        $out[] = ['id' => $it['id'], 'desc' => $it['desc'], 'label' => $it['label'], 'icon' => $it['icon'], 'url' => $it['url'], 'status' => $it['status']];
    }
    return $out;
}

/** URL of a destination by registry id ('' when not published). */
function omochix_editorial_destination_url($id) {
    foreach (omochix_editorial_destinations() as $d) { if ($d['id'] === $id) { return $d['url']; } }
    $it = omochix_nav_item($id);
    return $it ? $it['url'] : '';
}

/** Public, non-password-protected entries only, also when a user is logged in. */
function omochix_editorial_posts($args = []) {
    return (new WP_Query(array_merge([
        'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
        'posts_per_page' => 3, 'no_found_rows' => true, 'ignore_sticky_posts' => true,
        'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
    ], $args)))->posts;
}

/** display_order ascending; missing/non-numeric last; ties: newest date, then highest ID. */
function omochix_editorial_sort_tools(array $tools) {
    $keyed = [];
    foreach ($tools as $tool) {
        $order = get_post_meta($tool->ID, 'display_order', true);
        $keyed[] = [is_numeric($order) ? (float) $order : INF, (string) $tool->post_date, (int) $tool->ID, $tool];
    }
    usort($keyed, static function ($a, $b) {
        return [$a[0], $b[1], $b[2]] <=> [$b[0], $a[1], $a[2]];
    });
    return array_column($keyed, 3);
}

/** Featured tools are fetched in full, sorted, then cut, so display_order is never lost to a query LIMIT. */
function omochix_editorial_tools($limit = 3) {
    $tools = omochix_editorial_sort_tools(omochix_editorial_posts([
        'post_type' => 'ai_tool', 'posts_per_page' => -1,
        'meta_query' => ['relation' => 'AND', omochix_get_active_ai_tool_meta_query(), ['key' => 'is_featured', 'value' => ['1', 'true', 'yes'], 'compare' => 'IN']],
    ]));
    $tools = array_slice($tools, 0, $limit);
    if (count($tools) < $limit) {
        $fill = omochix_editorial_posts([
            'post_type' => 'ai_tool', 'posts_per_page' => $limit - count($tools),
            'post__not_in' => wp_list_pluck($tools, 'ID'), 'meta_query' => omochix_get_active_ai_tool_meta_query(),
        ]);
        $tools = array_merge($tools, $fill);
    }
    return $tools;
}

/** True when the body has at least one visible character (not only Unicode spaces, controls or zero-width marks). */
function omochix_editorial_has_prompt_body($body) {
    if (!is_string($body)) { return false; }
    $visible = @preg_match('/[^\p{Z}\p{C}]/u', $body);
    return $visible === false ? trim($body) !== '' : $visible === 1;
}

/** Newest public prompt with a usable body; pages through results so blank bodies cannot hide valid ones. */
function omochix_editorial_prompt() {
    $batch = 20;
    for ($paged = 1;; $paged++) {
        $posts = omochix_editorial_posts([
            'post_type' => 'prompt', 'posts_per_page' => $batch, 'paged' => $paged,
            'meta_query' => [['key' => 'prompt_body', 'value' => '', 'compare' => '!=']],
        ]);
        foreach ($posts as $post) {
            if (omochix_editorial_has_prompt_body(get_post_meta($post->ID, 'prompt_body', true))) { return $post; }
        }
        if (count($posts) < $batch) { return null; }
    }
}

function omochix_editorial_selection() {
    $sticky = array_values(array_filter(array_map('absint', (array) get_option('sticky_posts', []))));
    $feature = $sticky ? omochix_editorial_posts(['post__in' => $sticky, 'posts_per_page' => 1]) : [];
    $news = get_category_by_slug('ai-news');
    if (!$feature && $news instanceof WP_Term) {
        $feature = omochix_editorial_posts(['cat' => $news->term_id, 'posts_per_page' => 1]);
    }
    $exclude = wp_list_pluck($feature, 'ID');
    $latest = $news instanceof WP_Term ? omochix_editorial_posts(['cat' => $news->term_id, 'post__not_in' => $exclude]) : [];
    $tools = post_type_exists('ai_tool') ? omochix_editorial_tools(3) : [];
    $prompt = post_type_exists('prompt') ? omochix_editorial_prompt() : null;
    return ['feature' => $feature, 'latest' => $latest, 'tools' => $tools, 'prompt' => $prompt];
}

function omochix_editorial_media($post, $size = 'large', $priority = false, $fallback = 'news') {
    if (has_post_thumbnail($post)) {
        echo get_the_post_thumbnail($post, $size, [
            'alt' => '', 'loading' => $priority ? 'eager' : 'lazy', 'decoding' => 'async',
        ]);
    } else {
        omochix_editorial_visual($fallback, '', $priority);
    }
}

/** Decorative section art: post thumbnails always take priority in article cards. */
function omochix_editorial_visual($name, $class = '', $priority = false) {
    if (!in_array($name, ['feature', 'news', 'prompts', 'learn', 'tools'], true)) { return; }
    printf('<img class="%s" src="%s" width="1672" height="940" alt="" loading="%s" decoding="async">', esc_attr($class), esc_url(get_theme_file_uri('/assets/img/editorial/' . $name . '-v1.webp')), $priority ? 'eager' : 'lazy');
}

/**
 * Motion frames for the AI Tools, Prompts, Learn and About sections (HTML and CSS, assets/css/editorial-motion.css).
 * The markup with plain CSS is the finished frame: without JavaScript, with reduced motion, in print and after
 * the one play on first view (assets/js/editorial-motion.js) the same finished frame shows. Decorative only:
 * fixed, theme-owned text, no post or user data, hidden from assistive technology. Other names fall back to
 * the still image.
 */
function omochix_editorial_motion($name, $class = '') {
    if (!in_array($name, ['tools', 'prompts', 'learn', 'about'], true)) { omochix_editorial_visual($name, $class); return; }
    $line = '<span class="omx-mo-line"></span>';
    $logo = '<span class="omx-mo-logo"><img src="' . esc_url(get_theme_file_uri('/assets/img/editorial/omochix-logo-wh.png')) . '" width="406" height="90" alt="" loading="lazy" decoding="async"></span>';
    if ('tools' === $name) {
        $icons = [
            '文章' => '<path d="M5 19h4L19.5 8.5a2.1 2.1 0 0 0-3-3L6 16l-1 3Z"/><path d="m14.5 7.5 2 2"/>',
            '画像' => '<rect x="4" y="5" width="16" height="14" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="m5 17 4.5-4 3 2.5L16 12l3 3"/>',
            '開発' => '<path d="m9 8-4 4 4 4"/><path d="m15 8 4 4-4 4"/><path d="m13 6-2 12"/>',
        ];
        $cards = '';
        $i = 0;
        foreach ($icons as $label => $paths) {
            $cards .= '<span class="omx-mo-card omx-mo-glass" style="--i:' . $i++ . '"><span class="omx-mo-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg></span><span class="omx-mo-label">' . esc_html($label) . '</span>' . $line . $line . '</span>';
        }
        $body = '<span class="omx-mo-search omx-mo-glass"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6"/><path d="m15 15 4.5 4.5"/></svg><span class="omx-mo-query">' . esc_html__('気になるAIを検索', 'omochix') . '</span><span class="omx-mo-caret"></span></span><span class="omx-mo-cards">' . $cards . '</span>';
    } elseif ('prompts' === $name) {
        $stars = str_repeat('<span class="omx-mo-star"></span>', 4) . '<span class="omx-mo-star is-dim"></span>';
        $bars = '';
        foreach ([0.42, 0.66, 0.54, 0.88] as $i => $h) { $bars .= '<span style="--i:' . $i . ';--h:' . $h . '"></span>'; }
        $body = '<span class="omx-mo-sheet omx-mo-sheet--review omx-mo-glass"><span class="omx-mo-head">' . esc_html__('顧客レビュー', 'omochix') . '</span><span class="omx-mo-stars">' . $stars . '</span>' . $line . $line . '<span class="omx-mo-line is-short"></span></span>'
            . '<span class="omx-mo-sheet omx-mo-sheet--insight omx-mo-glass"><span class="omx-mo-head">' . esc_html__('購買インサイト', 'omochix') . '</span><span class="omx-mo-bars">' . $bars . '</span><span class="omx-mo-chip">' . esc_html__('リピート', 'omochix') . ' ↑</span></span>';
    } elseif ('about' === $name) {
        $body = '<span class="omx-mo-trail" style="--k:-1;--o:.55"></span><span class="omx-mo-trail" style="--k:0;--o:1"></span><span class="omx-mo-trail" style="--k:1;--o:.7"></span>';
    } else {
        $steps = '';
        foreach (['知る', '試す', '作る'] as $i => $label) { $steps .= '<span class="omx-mo-step" style="--i:' . $i . '"><span>' . esc_html($label) . '</span></span>'; }
        $body = '<span class="omx-mo-stairs">' . $steps . '</span><span class="omx-mo-ui omx-mo-glass"><span class="omx-mo-ui-bar"><i></i><i></i><i></i></span><span class="omx-mo-ui-body"><span class="omx-mo-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 12.5 4 4 8-9"/></svg></span>' . $line . '<span class="omx-mo-line is-short"></span><span class="omx-mo-btn">' . esc_html__('完成', 'omochix') . '</span></span></span>';
    }
    printf('<div class="omx-mo omx-mo--%1$s %2$s" data-omx-motion aria-hidden="true">%3$s<span class="omx-mo-stage">%4$s</span></div>', esc_attr($name), esc_attr($class), $logo, $body);
}

function omochix_editorial_category($post) {
    $terms = get_the_category($post->ID);
    return $terms ? $terms[0]->name : __('記事', 'omochix');
}

add_filter('body_class', static function ($classes) {
    if (is_front_page()) { $classes[] = 'omx-editorial'; }
    return $classes;
});
add_action('wp_enqueue_scripts', static function () {
    if (!is_front_page()) { return; }
    wp_enqueue_style('omochix-editorial', get_theme_file_uri('/assets/css/editorial-home.css'), ['omochix-style'], (string) filemtime(get_theme_file_path('/assets/css/editorial-home.css')));
    wp_enqueue_style('omochix-editorial-motion', get_theme_file_uri('/assets/css/editorial-motion.css'), ['omochix-editorial'], (string) filemtime(get_theme_file_path('/assets/css/editorial-motion.css')));
    wp_enqueue_script('omochix-editorial-motion', get_theme_file_uri('/assets/js/editorial-motion.js'), [], (string) filemtime(get_theme_file_path('/assets/js/editorial-motion.js')), true);
    // Reuse exact copy behavior used by existing prompt details.
    wp_enqueue_script('omochix-prompt', get_theme_file_uri('/assets/js/prompt.js'), [], wp_get_theme()->get('Version'), true);
});
