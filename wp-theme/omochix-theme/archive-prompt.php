<?php
/**
 * Prompt Library archive ("/prompts").
 *
 * Mirrors archive-ai_tool.php's query/filter shape for consistency; remains
 * fully functional without JavaScript. Also rendered for prompt_category and
 * prompt_model term archives (taxonomy-prompt_*.php), where the queried term
 * becomes the fixed category/model filter.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$omochix_archive_url = get_post_type_archive_link('prompt') ?: home_url('/prompts/');
$omochix_paged        = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));

$omochix_search     = isset($_GET['prompt_search']) ? sanitize_text_field(wp_unslash($_GET['prompt_search'])) : '';
$omochix_category   = isset($_GET['prompt_category']) ? sanitize_title(wp_unslash($_GET['prompt_category'])) : '';
$omochix_model       = isset($_GET['prompt_model']) ? sanitize_title(wp_unslash($_GET['prompt_model'])) : '';
$omochix_difficulty = isset($_GET['prompt_difficulty']) ? sanitize_key(wp_unslash($_GET['prompt_difficulty'])) : '';
$omochix_order       = isset($_GET['prompt_order']) ? sanitize_key(wp_unslash($_GET['prompt_order'])) : 'latest';
$omochix_related_tool = isset($_GET['related_tool']) ? sanitize_title(wp_unslash($_GET['related_tool'])) : '';

// On /prompts/category/{slug}/ and /prompts/model/{slug}/ the URL itself is the filter.
$omochix_context_term = is_tax(['prompt_category', 'prompt_model']) ? get_queried_object() : null;
$omochix_context_term = $omochix_context_term instanceof WP_Term ? $omochix_context_term : null;
$omochix_context_url  = $omochix_context_term ? get_term_link($omochix_context_term) : '';
$omochix_context_url  = is_wp_error($omochix_context_url) ? '' : $omochix_context_url;
if ($omochix_context_term && 'prompt_category' === $omochix_context_term->taxonomy) {
    $omochix_category = $omochix_context_term->slug;
} elseif ($omochix_context_term) {
    $omochix_model = $omochix_context_term->slug;
}
$omochix_fixed_taxonomy = $omochix_context_term ? $omochix_context_term->taxonomy : '';

$omochix_difficulty_options = [
    'beginner'     => __('初級', 'omochix'),
    'intermediate' => __('中級', 'omochix'),
    'advanced'     => __('上級', 'omochix'),
];
$omochix_order_options = [
    'latest' => __('新着順', 'omochix'),
    'name'   => __('名前順', 'omochix'),
];

if (!array_key_exists($omochix_difficulty, $omochix_difficulty_options)) {
    $omochix_difficulty = '';
}
if (!array_key_exists($omochix_order, $omochix_order_options)) {
    $omochix_order = 'latest';
}

$omochix_categories = get_terms(['taxonomy' => 'prompt_category', 'hide_empty' => true, 'orderby' => 'name']);
$omochix_models     = get_terms(['taxonomy' => 'prompt_model', 'hide_empty' => true, 'orderby' => 'name']);
$omochix_categories = is_array($omochix_categories) ? $omochix_categories : [];
$omochix_models     = is_array($omochix_models) ? $omochix_models : [];
// Keep an empty context term selectable so the filter form preserves it.
if ($omochix_context_term && 0 === (int) $omochix_context_term->count) {
    if ('prompt_category' === $omochix_fixed_taxonomy) {
        array_unshift($omochix_categories, $omochix_context_term);
    } else {
        array_unshift($omochix_models, $omochix_context_term);
    }
}

$omochix_tax_query = [];
if ($omochix_category && term_exists($omochix_category, 'prompt_category')) {
    $omochix_tax_query[] = ['taxonomy' => 'prompt_category', 'field' => 'slug', 'terms' => $omochix_category];
} else {
    $omochix_category = '';
}
if ($omochix_model && term_exists($omochix_model, 'prompt_model')) {
    $omochix_tax_query[] = ['taxonomy' => 'prompt_model', 'field' => 'slug', 'terms' => $omochix_model];
} else {
    $omochix_model = '';
}

$omochix_meta_query = [];
if ($omochix_difficulty) {
    $omochix_meta_query[] = ['key' => 'prompt_difficulty', 'value' => $omochix_difficulty];
}

// AI Tool -> Prompts "view all" link (single-ai_tool.php). Resolved against
// published tools only, so an unpublished or unknown slug degrades to a
// real, empty result set rather than silently ignoring the filter or (worse)
// showing the unfiltered archive.
$omochix_related_tool_post = null;
if ($omochix_related_tool) {
    $omochix_related_tool_matches = get_posts([
        'post_type'      => 'ai_tool',
        'name'           => $omochix_related_tool,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ]);
    $omochix_related_tool_post = $omochix_related_tool_matches ? $omochix_related_tool_matches[0] : null;
}

$omochix_query_args = [
    'post_type'      => 'prompt',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $omochix_paged,
    's'              => $omochix_search,
];
if ('' !== $omochix_search) {
    // Opts this query into omochix_extend_prompt_search_sql() (functions.php),
    // which also matches prompt body/usage and prompt term names.
    $omochix_query_args['omochix_prompt_search'] = true;
}
if ($omochix_tax_query) {
    $omochix_query_args['tax_query'] = $omochix_tax_query;
}
if ($omochix_meta_query) {
    $omochix_query_args['meta_query'] = $omochix_meta_query;
}
if ($omochix_related_tool) {
    // related_tool_ids is a manual meta relation, not a taxonomy, so it is
    // resolved to an exact, verified ID list first (see
    // omochix_core_get_tool_related_prompt_ids()) and applied via post__in.
    // An empty post__in means "no constraint" to WP_Query, so a genuinely
    // empty match set must use an impossible ID instead of [] — otherwise
    // this would silently show the entire unfiltered archive.
    $omochix_related_tool_ids = $omochix_related_tool_post && function_exists('omochix_core_get_tool_related_prompt_ids')
        ? omochix_core_get_tool_related_prompt_ids($omochix_related_tool_post->ID)
        : [];
    $omochix_query_args['post__in'] = $omochix_related_tool_ids ?: [0];
}
if ('name' === $omochix_order) {
    $omochix_query_args['orderby'] = 'title';
    $omochix_query_args['order']   = 'ASC';
} else {
    $omochix_query_args['orderby'] = 'date';
    $omochix_query_args['order']   = 'DESC';
}

$omochix_prompts_query = new WP_Query($omochix_query_args);

$omochix_popular_categories = get_terms(['taxonomy' => 'prompt_category', 'hide_empty' => true, 'number' => 8, 'orderby' => 'count', 'order' => 'DESC']);
$omochix_popular_categories = is_array($omochix_popular_categories) ? $omochix_popular_categories : [];
?>

<main class="prompt-archive" id="main-content">
    <header class="prompt-archive__hero">
        <div class="prompt-archive__container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <?php if ($omochix_context_term) : ?>
                        <li><a href="<?php echo esc_url($omochix_archive_url); ?>"><?php esc_html_e('プロンプトライブラリ', 'omochix'); ?></a></li>
                        <li aria-current="page"><?php echo esc_html($omochix_context_term->name); ?></li>
                    <?php else : ?>
                        <li aria-current="page"><?php esc_html_e('プロンプトライブラリ', 'omochix'); ?></li>
                    <?php endif; ?>
                </ol>
            </nav>
            <div class="prompt-archive__hero-grid">
                <div class="prompt-archive__hero-copy">
                    <?php if ($omochix_context_term) : ?>
                        <p class="prompt-archive__eyebrow"><?php echo 'prompt_category' === $omochix_fixed_taxonomy ? esc_html__('PROMPT CATEGORY', 'omochix') : esc_html__('PROMPTS BY AI', 'omochix'); ?></p>
                        <h1><?php echo esc_html(sprintf(__('%sのプロンプト', 'omochix'), $omochix_context_term->name)); ?></h1>
                        <?php if ($omochix_context_term->description) : ?>
                            <p><?php echo esc_html(wp_strip_all_tags($omochix_context_term->description)); ?></p>
                        <?php elseif ('prompt_category' === $omochix_fixed_taxonomy) : ?>
                            <p><?php echo esc_html(sprintf(__('「%s」のプロンプトを、対応AIや難易度から探せます。', 'omochix'), $omochix_context_term->name)); ?></p>
                        <?php else : ?>
                            <p><?php echo esc_html(sprintf(__('対応AI「%s」のプロンプトを、目的や難易度から探せます。', 'omochix'), $omochix_context_term->name)); ?></p>
                        <?php endif; ?>
                        <?php if ('prompt_category' === $omochix_fixed_taxonomy && function_exists('omochix_get_mapped_ai_tool_category')) :
                            $omochix_mapped_ai_tool_category = omochix_get_mapped_ai_tool_category($omochix_context_term->slug);
                            $omochix_mapped_ai_tool_category_url = $omochix_mapped_ai_tool_category ? get_term_link($omochix_mapped_ai_tool_category) : '';
                            if ($omochix_mapped_ai_tool_category && !is_wp_error($omochix_mapped_ai_tool_category_url)) : ?>
                                <a class="prompt-archive__cross-link" href="<?php echo esc_url($omochix_mapped_ai_tool_category_url); ?>"><?php esc_html_e('関連するAIツールを見る', 'omochix'); ?><span aria-hidden="true">→</span></a>
                            <?php endif;
                        endif; ?>
                    <?php else : ?>
                        <p class="prompt-archive__eyebrow"><?php esc_html_e('PROMPT LIBRARY', 'omochix'); ?></p>
                        <h1><?php esc_html_e('プロンプトライブラリ', 'omochix'); ?></h1>
                        <p><?php esc_html_e('目的やAIツールに合わせて、すぐ使えるプロンプトを見つけられます。', 'omochix'); ?></p>
                    <?php endif; ?>
                </div>
                <form class="prompt-archive__search" role="search" method="get" action="<?php echo esc_url($omochix_archive_url); ?>">
                    <?php if ($omochix_context_term) : ?>
                        <input type="hidden" name="<?php echo esc_attr($omochix_fixed_taxonomy); ?>" value="<?php echo esc_attr($omochix_context_term->slug); ?>">
                    <?php endif; ?>
                    <?php if ($omochix_related_tool) : ?>
                        <input type="hidden" name="related_tool" value="<?php echo esc_attr($omochix_related_tool); ?>">
                    <?php endif; ?>
                    <label for="prompt-hero-search"><?php esc_html_e('プロンプトを検索', 'omochix'); ?></label>
                    <div>
                        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>
                        <input id="prompt-hero-search" name="prompt_search" type="search" value="<?php echo esc_attr($omochix_search); ?>" placeholder="<?php esc_attr_e('用途やキーワードを検索...', 'omochix'); ?>">
                        <button type="submit"><?php esc_html_e('検索', 'omochix'); ?></button>
                    </div>
                    <p aria-live="polite"><?php echo esc_html(sprintf(_n('%s件のプロンプト', '%s件のプロンプト', $omochix_prompts_query->found_posts, 'omochix'), number_format_i18n($omochix_prompts_query->found_posts))); ?></p>
                </form>
            </div>
        </div>
    </header>

    <section class="prompt-filter" aria-labelledby="prompt-filter-title">
        <div class="prompt-archive__container">
            <h2 class="sr-only" id="prompt-filter-title"><?php esc_html_e('プロンプトを絞り込む', 'omochix'); ?></h2>
            <form class="prompt-filter__form" method="get" action="<?php echo esc_url($omochix_archive_url); ?>">
                <?php if ($omochix_search) : ?><input type="hidden" name="prompt_search" value="<?php echo esc_attr($omochix_search); ?>"><?php endif; ?>
                <?php if ($omochix_related_tool) : ?><input type="hidden" name="related_tool" value="<?php echo esc_attr($omochix_related_tool); ?>"><?php endif; ?>
                <?php if ($omochix_related_tool) : ?>
                    <p class="prompt-filter__active-tool" role="status">
                        <?php if ($omochix_related_tool_post) : ?>
                            <?php echo esc_html(sprintf(__('「%s」で使えるプロンプトのみ表示中', 'omochix'), get_the_title($omochix_related_tool_post))); ?>
                        <?php else : ?>
                            <?php esc_html_e('指定されたAIツールが見つかりませんでした。', 'omochix'); ?>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(remove_query_arg('related_tool')); ?>"><?php esc_html_e('解除', 'omochix'); ?></a>
                    </p>
                <?php endif; ?>
                <div class="prompt-filter__field">
                    <label for="prompt-category"><?php esc_html_e('カテゴリー', 'omochix'); ?></label>
                    <select id="prompt-category" name="prompt_category">
                        <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                        <?php foreach ($omochix_categories as $omochix_term) : ?>
                            <option value="<?php echo esc_attr($omochix_term->slug); ?>" <?php selected($omochix_category, $omochix_term->slug); ?>><?php echo esc_html($omochix_term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="prompt-filter__field">
                    <label for="prompt-model"><?php esc_html_e('対応AI', 'omochix'); ?></label>
                    <select id="prompt-model" name="prompt_model">
                        <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                        <?php foreach ($omochix_models as $omochix_term) : ?>
                            <option value="<?php echo esc_attr($omochix_term->slug); ?>" <?php selected($omochix_model, $omochix_term->slug); ?>><?php echo esc_html($omochix_term->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="prompt-filter__field">
                    <label for="prompt-difficulty"><?php esc_html_e('難易度', 'omochix'); ?></label>
                    <select id="prompt-difficulty" name="prompt_difficulty">
                        <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                        <?php foreach ($omochix_difficulty_options as $omochix_value => $omochix_label) : ?>
                            <option value="<?php echo esc_attr($omochix_value); ?>" <?php selected($omochix_difficulty, $omochix_value); ?>><?php echo esc_html($omochix_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="prompt-filter__field">
                    <label for="prompt-order"><?php esc_html_e('並び順', 'omochix'); ?></label>
                    <select id="prompt-order" name="prompt_order">
                        <?php foreach ($omochix_order_options as $omochix_value => $omochix_label) : ?>
                            <option value="<?php echo esc_attr($omochix_value); ?>" <?php selected($omochix_order, $omochix_value); ?>><?php echo esc_html($omochix_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="prompt-filter__submit" type="submit"><?php esc_html_e('絞り込む', 'omochix'); ?></button>
                <?php
                // The term fixed by a term archive URL is not a clearable filter there.
                $omochix_has_filters = $omochix_search || $omochix_difficulty || 'latest' !== $omochix_order || $omochix_related_tool
                    || ($omochix_category && 'prompt_category' !== $omochix_fixed_taxonomy)
                    || ($omochix_model && 'prompt_model' !== $omochix_fixed_taxonomy);
                ?>
                <?php if ($omochix_has_filters) : ?>
                    <a class="prompt-filter__reset" href="<?php echo esc_url($omochix_context_url ?: $omochix_archive_url); ?>"><?php esc_html_e('条件をクリア', 'omochix'); ?></a>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <div class="prompt-archive__container prompt-archive__layout">
        <section class="prompt-results" aria-labelledby="prompt-results-title">
            <header class="prompt-results__header">
                <h2 id="prompt-results-title"><?php esc_html_e('プロンプト一覧', 'omochix'); ?></h2>
                <p><?php echo esc_html(sprintf(_n('%s件', '%s件', $omochix_prompts_query->found_posts, 'omochix'), number_format_i18n($omochix_prompts_query->found_posts))); ?></p>
            </header>

            <?php if ($omochix_prompts_query->have_posts()) : ?>
                <div class="prompt-results__grid">
                    <?php while ($omochix_prompts_query->have_posts()) : $omochix_prompts_query->the_post(); ?>
                        <?php
                        $omochix_prompt_id         = get_the_ID();
                        $omochix_prompt_difficulty = get_post_meta($omochix_prompt_id, 'prompt_difficulty', true) ?: 'beginner';
                        $omochix_prompt_categories = get_the_terms($omochix_prompt_id, 'prompt_category');
                        $omochix_prompt_models     = get_the_terms($omochix_prompt_id, 'prompt_model');
                        $omochix_prompt_categories = is_array($omochix_prompt_categories) ? $omochix_prompt_categories : [];
                        $omochix_prompt_models     = is_array($omochix_prompt_models) ? $omochix_prompt_models : [];
                        $omochix_prompt_excerpt    = wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 36, '…');
                        ?>
                        <article class="prompt-card">
                            <a class="prompt-card__link" href="<?php the_permalink(); ?>">
                                <div class="prompt-card__top">
                                    <span class="prompt-card__badge prompt-card__badge--<?php echo esc_attr($omochix_prompt_difficulty); ?>"><?php echo esc_html(function_exists('omochix_core_get_prompt_difficulty_label') ? omochix_core_get_prompt_difficulty_label($omochix_prompt_difficulty) : $omochix_difficulty_options[$omochix_prompt_difficulty] ?? ''); ?></span>
                                    <?php if ($omochix_prompt_models) : ?><span class="prompt-card__model"><?php echo esc_html($omochix_prompt_models[0]->name); ?></span><?php endif; ?>
                                </div>
                                <h3><?php the_title(); ?></h3>
                                <?php if ($omochix_prompt_excerpt) : ?><p class="prompt-card__description"><?php echo esc_html($omochix_prompt_excerpt); ?></p><?php endif; ?>
                                <?php if ($omochix_prompt_categories) : ?>
                                    <ul class="prompt-card__tags" aria-label="<?php esc_attr_e('カテゴリー', 'omochix'); ?>">
                                        <?php foreach (array_slice($omochix_prompt_categories, 0, 2) as $omochix_term) : ?><li><?php echo esc_html($omochix_term->name); ?></li><?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <span class="prompt-card__more"><?php esc_html_e('プロンプトを見る', 'omochix'); ?><span aria-hidden="true">→</span></span>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php
                $omochix_add_args = array_filter([
                    'prompt_search'     => $omochix_search,
                    'prompt_category'   => 'prompt_category' === $omochix_fixed_taxonomy ? '' : $omochix_category,
                    'prompt_model'      => 'prompt_model' === $omochix_fixed_taxonomy ? '' : $omochix_model,
                    'prompt_difficulty' => $omochix_difficulty,
                    'prompt_order'      => 'latest' !== $omochix_order ? $omochix_order : '',
                    'related_tool'      => $omochix_related_tool,
                ]);
                $omochix_pagination = paginate_links([
                    'total'     => $omochix_prompts_query->max_num_pages,
                    'current'   => $omochix_paged,
                    'mid_size'  => 1,
                    'prev_text' => __('前へ', 'omochix'),
                    'next_text' => __('次へ', 'omochix'),
                    'add_args'  => $omochix_add_args,
                    'type'      => 'list',
                ]);
                ?>
                <?php if ($omochix_pagination) : ?>
                    <nav class="pagination" aria-label="<?php esc_attr_e('プロンプト一覧のページ送り', 'omochix'); ?>"><?php echo wp_kses_post($omochix_pagination); ?></nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="prompt-results__empty" role="status">
                    <span aria-hidden="true">O</span>
                    <p><?php esc_html_e('条件に合うプロンプトはまだありません。', 'omochix'); ?></p>
                    <a href="<?php echo esc_url($omochix_archive_url); ?>"><?php esc_html_e('絞り込みを解除', 'omochix'); ?></a>
                </div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </section>

        <aside class="prompt-sidebar" aria-label="<?php esc_attr_e('プロンプトの補助情報', 'omochix'); ?>">
            <section class="sidebar-panel" aria-labelledby="prompt-categories-title">
                <h2 id="prompt-categories-title"><?php esc_html_e('人気カテゴリー', 'omochix'); ?></h2>
                <?php if ($omochix_popular_categories) : ?>
                    <ul class="sidebar-links">
                        <?php foreach ($omochix_popular_categories as $omochix_term) : ?>
                            <li><a href="<?php echo esc_url(get_term_link($omochix_term)); ?>"<?php if ($omochix_context_term && $omochix_context_term->term_id === $omochix_term->term_id) : ?> aria-current="page"<?php endif; ?>><span><?php echo esc_html($omochix_term->name); ?></span><small><?php echo esc_html(number_format_i18n($omochix_term->count)); ?></small></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="sidebar-panel__empty"><?php esc_html_e('カテゴリーを準備中です。', 'omochix'); ?></p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</main>

<?php get_footer(); ?>
