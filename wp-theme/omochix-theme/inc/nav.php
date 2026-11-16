<?php
/**
 * Single source of truth for OmochiX's public destinations (navigation registry).
 *
 * Every surface that lists destinations (PC sidebar, mobile menu, home shortcuts, both footers, "next steps")
 * reads this registry, so a new section is added in ONE place.
 *
 * Status of an entry
 *   published    live. Rendered as a link on every surface it opts into, but only when its URL resolves to something
 *                that really is published (a missing / draft page is shown as "coming-soon" instead of a dead link).
 *   coming-soon  announced, not live. Rendered as plain text with "準備中", never as a link, never on home shortcuts.
 *   hidden       not rendered anywhere.
 * A page existing in WordPress does NOT make a coming-soon entry public: flip the status deliberately
 * (filter `omochix_nav_status`, or the `omochix_nav_status` option: ['videos' => 'published']).
 *
 * Add a future section without touching templates:
 *   add_filter('omochix_nav_registry', function ($items) {
 *       $items['chat'] = ['label' => 'AIチャット', 'icon' => 'chat', 'status' => 'published', 'url' => home_url('/chat/')] + $items['chat'];
 *       return $items;
 *   });
 *
 * Admin menus: when a menu is assigned to the "primary" location in Appearance > Menus it still wins in the header
 * (unchanged behaviour). The registry then feeds every other surface.
 */
if (!defined('ABSPATH')) { exit; }

/** @return string[] valid statuses */
function omochix_nav_statuses() { return ['published', 'coming-soon', 'hidden']; }

/**
 * Registry definition. Order = display order. `surfaces`:
 *   nav    PC sidebar + mobile menu panel      home   home-page shortcut grid (keep <= 6 published items)
 *   footer footer link list                     soon   "準備中" row in footers (coming-soon items only)
 * `group`: primary | future | utility (used for dividers and next-steps).
 */
function omochix_nav_registry() {
    $items = [
        'news'      => ['label' => __('AIニュース', 'omochix'), 'icon' => 'news',      'group' => 'primary', 'status' => 'published',   'surfaces' => ['nav', 'home', 'footer'], 'resolver' => 'omochix_nav_url_news'],
        'tools'     => ['label' => __('AIツール', 'omochix'),   'icon' => 'tools',     'group' => 'primary', 'status' => 'published',   'surfaces' => ['nav', 'home', 'footer'], 'resolver' => 'omochix_nav_url_tools'],
        'dev'       => ['label' => __('AI開発', 'omochix'),     'icon' => 'code',      'group' => 'primary', 'status' => 'published',   'surfaces' => ['nav', 'home', 'footer'], 'resolver' => 'omochix_nav_url_dev'],
        'learn'     => ['label' => 'Learn',                     'icon' => 'learn',     'group' => 'primary', 'status' => 'published',   'surfaces' => ['nav', 'home', 'footer'], 'resolver' => 'omochix_nav_url_learn'],
        'prompts'   => ['label' => 'Prompts',                   'icon' => 'prompt',    'group' => 'primary', 'status' => 'published',   'surfaces' => ['nav', 'home', 'footer'], 'resolver' => 'omochix_nav_url_prompts'],
        'labs'      => ['label' => 'Labs',                      'icon' => 'labs',      'group' => 'primary', 'status' => 'published',   'surfaces' => ['nav', 'home', 'footer'], 'resolver' => 'omochix_nav_url_labs'],
        'videos'    => ['label' => __('動画', 'omochix'),       'icon' => 'video',     'group' => 'future',  'status' => 'coming-soon', 'surfaces' => ['nav', 'footer', 'home', 'soon'], 'resolver' => 'omochix_nav_url_videos'],
        'community' => ['label' => __('コミュニティ', 'omochix'), 'icon' => 'community', 'group' => 'future',  'status' => 'coming-soon', 'surfaces' => ['nav', 'footer', 'home', 'soon'], 'resolver' => 'omochix_nav_url_community'],
        'chat'      => ['label' => __('AIチャット', 'omochix'),  'icon' => 'chat',      'group' => 'future',  'status' => 'hidden',      'surfaces' => ['nav', 'footer', 'home', 'soon'], 'resolver' => 'omochix_nav_url_chat'],
        'about'     => ['label' => 'About',                     'icon' => 'about',     'group' => 'utility', 'status' => 'published',   'surfaces' => ['nav', 'footer'],          'resolver' => 'omochix_nav_url_about'],
        'contact'   => ['label' => __('お問い合わせ', 'omochix'), 'icon' => '',          'group' => 'utility', 'status' => 'published',   'surfaces' => ['footer'],                 'resolver' => 'omochix_nav_url_contact'],
    ];
    $items = apply_filters('omochix_nav_registry', $items);
    return is_array($items) ? $items : [];
}

/* ---- URL resolvers: return '' when the destination is not published ------------------------------------------ */
function omochix_nav_url_news() {
    $id = (int) get_option('page_for_posts'); $p = $id ? get_post($id) : null;
    return $p instanceof WP_Post && 'publish' === $p->post_status ? (string) get_permalink($p) : '';
}
function omochix_nav_url_tools()   { return (string) (get_post_type_archive_link('ai_tool') ?: ''); }
function omochix_nav_url_dev() {
    $c = get_category_by_slug('ai-development');
    return $c instanceof WP_Term ? (string) omochix_get_category_url($c) : '';
}
function omochix_nav_url_learn()     { return (string) omochix_get_published_page_url('learn'); }
function omochix_nav_url_prompts()   { return (string) (get_post_type_archive_link('prompt') ?: ''); }
function omochix_nav_url_labs()      { return (string) omochix_get_published_page_url(defined('OMOCHIX_LABS_PATH') ? OMOCHIX_LABS_PATH : 'labs'); }
function omochix_nav_url_videos()    { return (string) omochix_get_published_page_url('videos'); }
function omochix_nav_url_community() { return (string) omochix_get_published_page_url('community'); }
function omochix_nav_url_chat()      { return (string) omochix_get_published_page_url('chat'); }
function omochix_nav_url_about()     { return (string) omochix_get_published_page_url('about'); }
function omochix_nav_url_contact()   { return (string) omochix_get_published_page_url('contact'); }

/** Is the current request inside this section? (drives aria-current) */
function omochix_nav_is_current($id) {
    switch ($id) {
        case 'news':    return is_home() || is_category('ai-news') || (is_singular('post') && !has_category('ai-development'));
        case 'tools':   return is_post_type_archive('ai_tool') || is_singular('ai_tool') || is_tax('ai_tool_category');
        case 'dev':     return is_category('ai-development') || (is_singular('post') && has_category('ai-development'));
        case 'learn':   return is_page('learn') || is_singular('learn_practice') || is_post_type_archive('learn_practice') || (is_page() && 0 === strpos((string) get_page_uri(), 'learn/'));
        case 'prompts': return is_post_type_archive('prompt') || is_singular('prompt') || is_tax(['prompt_category', 'prompt_model']);
        case 'labs':    return function_exists('omochix_labs_is_request') && omochix_labs_is_request();
        case 'about':   return is_page('about');
    }
    return false;
}

/** Effective status after overrides (option, then filter). Unknown values fall back to the declared status. */
function omochix_nav_effective_status($id, array $item) {
    $status = isset($item['status']) ? $item['status'] : 'hidden';
    $opt = get_option('omochix_nav_status');
    if (is_array($opt) && isset($opt[$id]) && in_array($opt[$id], omochix_nav_statuses(), true)) { $status = $opt[$id]; }
    $status = apply_filters('omochix_nav_status', $status, $id, $item);
    return in_array($status, omochix_nav_statuses(), true) ? $status : 'hidden';
}

/** One-line role of each destination (shown under the home shortcuts on wide screens, and usable by other surfaces). */
function omochix_nav_descriptions() {
    return apply_filters('omochix_nav_descriptions', [
        'news' => __('今日のAIの動き', 'omochix'), 'tools' => __('比べて選ぶ', 'omochix'), 'dev' => __('作る人のために', 'omochix'),
        'learn' => __('基礎から学ぶ', 'omochix'), 'prompts' => __('コピーして使う', 'omochix'), 'labs' => __('ブラウザで試す', 'omochix'),
        'videos' => __('動画で学ぶ', 'omochix'), 'community' => __('仲間と話す', 'omochix'), 'chat' => __('AIに相談する', 'omochix'),
    ]);
}

/** Resolve one registry entry: status after overrides, URL (only when published), current-section flag. Null = hidden. */
function omochix_nav_resolve($id, array $item) {
    if (empty($item['label'])) { return null; }
    $status = omochix_nav_effective_status($id, $item);
    if ('hidden' === $status) { return null; }
    $url = '';
    if ('published' === $status) {
        if (!empty($item['url'])) { $url = (string) $item['url']; }
        elseif (!empty($item['resolver']) && is_callable($item['resolver'])) { $url = (string) call_user_func($item['resolver']); }
        if (!$url) { $status = 'coming-soon'; }   // never link to something that is not published
    }
    $cur = '';
    if ($url && omochix_nav_is_current($id)) {
        $cur = 'true';
        $req = (string) (isset($_SERVER['REQUEST_URI']) ? strtok(wp_unslash($_SERVER['REQUEST_URI']), '?') : '');
        if (untrailingslashit((string) wp_parse_url($url, PHP_URL_PATH)) === untrailingslashit($req)) { $cur = 'page'; }
    }
    $descs = omochix_nav_descriptions();
    return ['id' => $id, 'desc' => isset($item['desc']) ? (string) $item['desc'] : (isset($descs[$id]) ? $descs[$id] : ''), 'label' => (string) $item['label'], 'icon' => isset($item['icon']) ? (string) $item['icon'] : '', 'group' => isset($item['group']) ? (string) $item['group'] : 'primary', 'status' => $status, 'url' => $url, 'current' => $cur];
}

/**
 * Resolved items for a surface.
 *
 * @param string $surface nav | home | footer | soon
 * @param array  $args    group => string|string[] to keep only some groups
 * @return array[] each: id, label, icon, group, status (published|coming-soon), url ('' unless published), current (''|page|true)
 */
function omochix_nav_items($surface, array $args = []) {
    $out = [];
    foreach (omochix_nav_registry() as $id => $item) {
        if (!is_array($item)) { continue; }
        if (!in_array($surface, isset($item['surfaces']) ? (array) $item['surfaces'] : [], true)) { continue; }
        if (!empty($args['group']) && !in_array(isset($item['group']) ? $item['group'] : '', (array) $args['group'], true)) { continue; }
        $r = omochix_nav_resolve($id, $item);
        if (!$r) { continue; }
        // Home shortcuts: live destinations only. The six primary ones stay visible as "準備中" if their page is missing (unchanged behaviour).
        if ('home' === $surface && 'coming-soon' === $r['status'] && 'primary' !== $r['group']) { continue; }
        if ('soon' === $surface && 'coming-soon' !== $r['status']) { continue; }
        $out[] = $r;
    }
    return $out;
}

/** One resolved item by id (any surface), or null when unknown / hidden. */
function omochix_nav_item($id) {
    $reg = omochix_nav_registry();
    return isset($reg[$id]) && is_array($reg[$id]) ? omochix_nav_resolve($id, $reg[$id]) : null;
}

/** <li> markup for the header lists (PC sidebar + mobile panel share one <ul>). */
function omochix_nav_render_list_items(array $items) {
    $prev = '';
    foreach ($items as $it) {
        $start = ($prev && $prev !== $it['group']) ? ' data-group-start' : '';
        $prev = $it['group'];
        if ($it['url']) {
            $aria = '' === $it['current'] ? '' : ' aria-current="' . esc_attr($it['current']) . '"';
            printf('<li data-nav="%s"%s><a href="%s"%s>%s</a></li>', esc_attr($it['id']), $start, esc_url($it['url']), $aria, esc_html($it['label']));
        } else {
            printf('<li class="primary-nav__item--unavailable" data-nav="%s"%s><span aria-disabled="true">%s<small>%s</small></span></li>', esc_attr($it['id']), $start, esc_html($it['label']), esc_html__('準備中', 'omochix'));
        }
    }
}

/**
 * "次にできること": a short, role-aware hand-off at the bottom of hub pages. Links come from the registry, so an
 * unpublished destination is skipped automatically. Copy is fixed text (no user input).
 */
function omochix_next_steps($context) {
    $flows = [
        'learn'   => ['lead' => __('基礎がつかめたら、実際に手を動かしてみましょう。', 'omochix'),
                      'steps' => [['prompts', __('コピーしてすぐ使えるプロンプトで試す', 'omochix')], ['labs', __('ブラウザで動くLabsで形にする', 'omochix')], ['dev', __('AI開発の記事で一歩先へ', 'omochix')]]],
    'dev'     => ['lead' => __('読んだら、手を動かして確かめてみましょう。', 'omochix'),
                      'steps' => [['learn', __('基礎から学び直す', 'omochix')], ['prompts', __('プロンプトで試す', 'omochix')], ['labs', __('Labsで道具を使ってみる', 'omochix')]]],
    'prompts' => [ 'lead' => __('プロンプトを使いこなすために。', 'omochix'),
                      'steps' => [['learn', __('基本の考え方を学ぶ', 'omochix')], ['tools', __('使うAIツールを選ぶ', 'omochix')], ['labs', __('Labsで試す', 'omochix')]]],
    'labs'    => ['lead' => __('道具を使ったあとは、理解を深めましょう。', 'omochix'),
                      'steps' => [['learn', __('仕組みを基礎から学ぶ', 'omochix')], ['prompts', __('プロンプトを探す', 'omochix')], ['tools', __('AIツールを比べる', 'omochix')]]],
    'tools'   => ['lead' => __('ツールを選んだら、使い方を学びましょう。', 'omochix'),
                      'steps' => [['learn', __('使い方の基本を学ぶ', 'omochix')], ['prompts', __('プロンプトで試す', 'omochix')], ['news', __('最新のAIニュースを読む', 'omochix')]]],
    ];
    $flows = apply_filters('omochix_next_steps_flows', $flows);
    if (empty($flows[$context])) { return; }
    $rows = [];
    foreach ($flows[$context]['steps'] as $step) {
        $it = omochix_nav_item($step[0]);
        if ($it && $it['url'] && 'published' === $it['status']) { $rows[] = [$it, $step[1]]; }
    }
    if (!$rows) { return; }
    ?>
    <aside class="next-steps" aria-labelledby="next-steps-title-<?php echo esc_attr($context); ?>">
        <h2 class="next-steps__title" id="next-steps-title-<?php echo esc_attr($context); ?>"><?php esc_html_e('次にできること', 'omochix'); ?></h2>
        <p class="next-steps__lead"><?php echo esc_html($flows[$context]['lead']); ?></p>
        <ul class="next-steps__list">
            <?php foreach ($rows as $row) : ?>
                <li><a href="<?php echo esc_url($row[0]['url']); ?>"><strong><?php echo esc_html($row[0]['label']); ?></strong><span><?php echo esc_html($row[1]); ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    </aside>
    <?php
}
