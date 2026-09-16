<?php
/**
 * Learn section: ancestor-based template dispatch and shared configuration.
 *
 * "/learn" itself is a normal WordPress page and already resolves to
 * page-learn.php via the native page-{slug}.php template hierarchy — no
 * special handling is needed for it.
 *
 * Every *descendant* of that page (any depth: /learn/basics,
 * /learn/tools/github, ...) is intended to share one common "Learn Article"
 * template without requiring a page-{slug}.php file per article. Since
 * WordPress's template hierarchy only ever looks at a page's own slug, this
 * file adds a `template_include` filter that walks each page's ancestors to
 * detect Learn descendants and swaps in page-learn-article.php for them.
 *
 * The filter only overrides WordPress's own resolved template when that
 * template is the generic index.php fallback, so a more specific template a
 * future editor deliberately assigns is never overridden.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve the published "Learn" hub page ID.
 *
 * @return int
 */
function omochix_get_learn_page_id() {
    static $learn_page_id = null;

    if (null !== $learn_page_id) {
        return $learn_page_id;
    }

    $page = get_page_by_path('learn', OBJECT, 'page');

    $learn_page_id = ($page instanceof WP_Post && 'publish' === $page->post_status)
        ? (int) $page->ID
        : 0;

    return $learn_page_id;
}

/**
 * Whether a page is the Learn hub page itself.
 *
 * @param WP_Post|null $post Page to check.
 * @return bool
 */
function omochix_page_is_learn_root($post) {
    $learn_page_id = omochix_get_learn_page_id();

    return $learn_page_id && $post instanceof WP_Post && $learn_page_id === (int) $post->ID;
}

/**
 * Whether a page is a descendant (any depth) of the Learn hub page.
 *
 * @param WP_Post|null $post Page to check.
 * @return bool
 */
function omochix_page_is_learn_descendant($post) {
    $learn_page_id = omochix_get_learn_page_id();

    if (!$learn_page_id || !($post instanceof WP_Post) || 0 === (int) $post->post_parent) {
        return false;
    }

    return in_array($learn_page_id, get_post_ancestors($post), true);
}

/**
 * Return the page's nearest ancestor that is a direct child of the Learn hub
 * (its "section"), or the page itself when it already is that direct child.
 *
 * Used to label an article's category badge and to group "next" / related
 * articles by section.
 *
 * @param WP_Post $post Learn descendant page.
 * @return int
 */
function omochix_get_learn_section_id($post) {
    $ancestors = get_post_ancestors($post);

    if (count($ancestors) < 2) {
        return (int) $post->ID;
    }

    // Ancestors are ordered nearest-parent first; the entry just before the
    // topmost one (the Learn page itself) is the direct child of Learn.
    return (int) $ancestors[count($ancestors) - 2];
}

/**
 * Serve the common Learn Article template to every Learn descendant page
 * that has not been assigned a more specific template of its own.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function omochix_template_include_for_learn($template) {
    if (!is_page() || 'index.php' !== basename($template)) {
        return $template;
    }

    $post = get_queried_object();
    if (!omochix_page_is_learn_descendant($post)) {
        return $template;
    }

    $learn_article_template = get_theme_file_path('/page-learn-article.php');

    return file_exists($learn_article_template) ? $learn_article_template : $template;
}
add_filter('template_include', 'omochix_template_include_for_learn');

/**
 * Return the Learn hub's category card configuration.
 *
 * A card whose target page does not exist/publish yet is skipped rather than
 * linking to a broken URL, matching the pattern used by the front page's
 * "OmochiXでできること" destinations.
 *
 * @return array<int, array<string, string>>
 */
function omochix_get_learn_categories() {
    $cards = [
        ['name' => __('AI入門', 'omochix'), 'description' => __('AIの基本を、ゼロから理解する', 'omochix'), 'path' => 'learn/basics'],
        ['name' => __('プロンプト', 'omochix'), 'description' => __('すぐ使えるプロンプトを探す', 'omochix'), 'url' => get_post_type_archive_link('prompt')],
        ['name' => __('AI仕事術', 'omochix'), 'description' => __('日々の仕事にAIを取り入れる', 'omochix'), 'path' => 'learn/work'],
        ['name' => __('開発入門', 'omochix'), 'description' => __('AIを使った開発の基礎を学ぶ', 'omochix'), 'path' => 'learn/development'],
        ['name' => __('Tools', 'omochix'), 'description' => __('学習・開発に使う定番ツール', 'omochix'), 'path' => 'learn/tools'],
        ['name' => __('Tutorials', 'omochix'), 'description' => __('手順に沿って実際に手を動かす', 'omochix'), 'path' => 'learn/tutorials'],
    ];

    $resolved = [];
    foreach ($cards as $card) {
        $url = $card['url'] ?? (isset($card['path']) ? omochix_get_published_page_url($card['path']) : '');
        if (!$url) {
            continue;
        }
        $resolved[] = [
            'name'        => $card['name'],
            'description' => $card['description'],
            'url'         => $url,
        ];
    }

    return $resolved;
}

/**
 * Return the Learn hub's featured "Learning Path" steps.
 *
 * Purely informational for the MVP: no login, no progress tracking. A step
 * whose target page does not exist/publish yet still renders, but without a
 * link, so the path's shape is visible even before every page is written.
 *
 * @return array<int, array<string, string>>
 */
function omochix_get_learn_learning_path_steps() {
    $steps = [
        ['number' => '01', 'title' => __('基礎', 'omochix'), 'description' => __('AIの基本用語と考え方を知る', 'omochix'), 'path' => 'learn/basics'],
        ['number' => '02', 'title' => __('AIで作る', 'omochix'), 'description' => __('AIを使って手を動かしはじめる', 'omochix'), 'path' => 'learn/development'],
        ['number' => '03', 'title' => __('GitHub', 'omochix'), 'description' => __('コードをバージョン管理して保存する', 'omochix'), 'path' => 'learn/tools/github'],
        ['number' => '04', 'title' => __('Vercel', 'omochix'), 'description' => __('作ったサービスをデプロイする', 'omochix'), 'path' => 'learn/tools/vercel'],
        ['number' => '05', 'title' => __('Neon', 'omochix'), 'description' => __('サーバーレスDBでデータを保存する', 'omochix'), 'path' => 'learn/tools/neon'],
        ['number' => '06', 'title' => __('公開', 'omochix'), 'description' => __('Webサービスとして世界に公開する', 'omochix'), 'path' => 'learn/tutorials'],
    ];

    foreach ($steps as &$step) {
        $step['url'] = omochix_get_published_page_url($step['path']);
    }
    unset($step);

    return $steps;
}

/**
 * Register the Learn-only "difficulty / estimated time" side panel.
 *
 * Scoped to Learn descendant pages only (via add_meta_boxes_page + an
 * ancestry check) so editors of unrelated pages (About, Contact, ...) never
 * see it.
 *
 * @param WP_Post $post Page being edited.
 * @return void
 */
function omochix_learn_add_meta_boxes($post) {
    if (!omochix_page_is_learn_descendant($post)) {
        return;
    }

    add_meta_box(
        'omochix-learn-article',
        __('Learn記事設定', 'omochix'),
        'omochix_render_learn_meta_box',
        'page',
        'side',
        'default'
    );
}
add_action('add_meta_boxes_page', 'omochix_learn_add_meta_boxes');

/**
 * Render the difficulty / estimated time fields.
 *
 * @param WP_Post $post Page being edited.
 * @return void
 */
function omochix_render_learn_meta_box($post) {
    wp_nonce_field('omochix_save_learn_meta', 'omochix_learn_meta_nonce');

    $difficulty = get_post_meta($post->ID, 'learn_difficulty', true) ?: 'beginner';
    $minutes    = get_post_meta($post->ID, 'learn_estimated_minutes', true);
    $options    = [
        'beginner'     => __('初級', 'omochix'),
        'intermediate' => __('中級', 'omochix'),
        'advanced'     => __('上級', 'omochix'),
    ];
    ?>
    <p>
        <label for="omochix-learn-difficulty"><strong><?php esc_html_e('難易度', 'omochix'); ?></strong></label><br>
        <select id="omochix-learn-difficulty" name="omochix_learn_meta[difficulty]">
            <?php foreach ($options as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($difficulty, $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="omochix-learn-minutes"><strong><?php esc_html_e('推定読了時間（分）', 'omochix'); ?></strong></label><br>
        <input type="number" min="1" step="1" id="omochix-learn-minutes" name="omochix_learn_meta[estimated_minutes]" value="<?php echo esc_attr($minutes); ?>" class="small-text">
    </p>
    <?php
}

/**
 * Persist the Learn-only meta fields.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function omochix_save_learn_meta($post_id) {
    if ('page' !== get_post_type($post_id)) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    if (!isset($_POST['omochix_learn_meta_nonce'])) {
        return;
    }
    $nonce = sanitize_text_field(wp_unslash($_POST['omochix_learn_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'omochix_save_learn_meta')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (!omochix_page_is_learn_descendant(get_post($post_id))) {
        return;
    }

    $submitted  = isset($_POST['omochix_learn_meta']) && is_array($_POST['omochix_learn_meta']) ? wp_unslash($_POST['omochix_learn_meta']) : [];
    $difficulty = sanitize_key($submitted['difficulty'] ?? 'beginner');
    if (!in_array($difficulty, ['beginner', 'intermediate', 'advanced'], true)) {
        $difficulty = 'beginner';
    }
    update_post_meta($post_id, 'learn_difficulty', $difficulty);

    $minutes = isset($submitted['estimated_minutes']) ? absint($submitted['estimated_minutes']) : 0;
    if ($minutes > 0) {
        update_post_meta($post_id, 'learn_estimated_minutes', $minutes);
    } else {
        delete_post_meta($post_id, 'learn_estimated_minutes');
    }
}
add_action('save_post_page', 'omochix_save_learn_meta');

/**
 * Return the translated Learn difficulty label.
 *
 * @param string $value Stored difficulty value.
 * @return string
 */
function omochix_get_learn_difficulty_label($value) {
    $labels = [
        'beginner'     => __('初級', 'omochix'),
        'intermediate' => __('中級', 'omochix'),
        'advanced'     => __('上級', 'omochix'),
    ];

    return $labels[$value] ?? $labels['beginner'];
}
