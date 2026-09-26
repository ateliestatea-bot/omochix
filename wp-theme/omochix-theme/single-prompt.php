<?php
/**
 * Prompt detail page ("/prompts/{slug}").
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();

        $omochix_prompt_id     = get_the_ID();
        $omochix_archive_url   = get_post_type_archive_link('prompt') ?: home_url('/prompts/');
        $omochix_prompt_body   = get_post_meta($omochix_prompt_id, 'prompt_body', true);
        $omochix_prompt_usage  = get_post_meta($omochix_prompt_id, 'prompt_usage', true);
        $omochix_prompt_example = get_post_meta($omochix_prompt_id, 'prompt_example', true);
        $omochix_difficulty    = get_post_meta($omochix_prompt_id, 'prompt_difficulty', true) ?: 'beginner';
        $omochix_difficulty_label = function_exists('omochix_core_get_prompt_difficulty_label')
            ? omochix_core_get_prompt_difficulty_label($omochix_difficulty)
            : $omochix_difficulty;

        $omochix_categories = get_the_terms($omochix_prompt_id, 'prompt_category');
        $omochix_models     = get_the_terms($omochix_prompt_id, 'prompt_model');
        $omochix_categories = is_array($omochix_categories) ? $omochix_categories : [];
        $omochix_models     = is_array($omochix_models) ? $omochix_models : [];
        $omochix_related_tools = function_exists('omochix_core_get_prompt_related_tools')
            ? omochix_core_get_prompt_related_tools($omochix_prompt_id, 6)
            : [];

        // Related prompts: same prompt_category first (the stronger, curated
        // signal), and only fall back to prompt_model if that alone doesn't
        // reach the cap. Always post_status => publish — even when an admin
        // is previewing this draft, the related list must reflect what a
        // public visitor would actually be able to click through to, not
        // other drafts that happen to share a taxonomy term.
        $omochix_related_cap = 6;
        $omochix_related_ids = [];
        foreach (['prompt_category' => $omochix_categories, 'prompt_model' => $omochix_models] as $omochix_taxonomy => $omochix_terms) {
            if (!$omochix_terms || count($omochix_related_ids) >= $omochix_related_cap) {
                continue;
            }
            $omochix_relation_query = new WP_Query([
                'post_type'      => 'prompt',
                'post_status'    => 'publish',
                'posts_per_page' => $omochix_related_cap - count($omochix_related_ids),
                'post__not_in'   => array_merge([$omochix_prompt_id], $omochix_related_ids),
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'tax_query'      => [[
                    'taxonomy' => $omochix_taxonomy,
                    'field'    => 'term_id',
                    'terms'    => wp_list_pluck($omochix_terms, 'term_id'),
                ]],
            ]);
            $omochix_related_ids = array_values(array_unique(array_merge($omochix_related_ids, $omochix_relation_query->posts)));
        }
        $omochix_related_prompts = $omochix_related_ids ? new WP_Query([
            'post_type' => 'prompt', 'post_status' => 'publish', 'posts_per_page' => $omochix_related_cap,
            'post__in' => $omochix_related_ids, 'orderby' => 'post__in', 'no_found_rows' => true,
        ]) : null;
        ?>

        <main class="prompt-detail" id="main-content">
            <article class="prompt-detail__article">
                <header class="prompt-detail__hero">
                    <div class="prompt-detail__container">
                        <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                            <ol>
                                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                                <li><a href="<?php echo esc_url($omochix_archive_url); ?>"><?php esc_html_e('プロンプトライブラリ', 'omochix'); ?></a></li>
                                <?php if ($omochix_categories) : ?>
                                    <li><a href="<?php echo esc_url(get_term_link($omochix_categories[0])); ?>"><?php echo esc_html($omochix_categories[0]->name); ?></a></li>
                                <?php endif; ?>
                                <li aria-current="page"><?php the_title(); ?></li>
                            </ol>
                        </nav>

                        <div class="prompt-detail__badges">
                            <span class="prompt-card__badge prompt-card__badge--<?php echo esc_attr($omochix_difficulty); ?>"><?php echo esc_html($omochix_difficulty_label); ?></span>
                            <?php foreach ($omochix_models as $omochix_term) : ?><span class="prompt-detail__model"><?php echo esc_html($omochix_term->name); ?></span><?php endforeach; ?>
                        </div>
                        <h1><?php the_title(); ?></h1>
                        <?php if (get_the_excerpt()) : ?><p class="prompt-detail__lead"><?php echo esc_html(wp_strip_all_tags(get_the_excerpt())); ?></p><?php endif; ?>
                        <?php if ($omochix_categories) : ?>
                            <div class="prompt-detail__taxonomy">
                                <?php foreach ($omochix_categories as $omochix_term) : ?><a href="<?php echo esc_url(get_term_link($omochix_term)); ?>"><?php echo esc_html($omochix_term->name); ?></a><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="prompt-detail__container prompt-detail__layout">
                    <div class="prompt-detail__main">
                        <?php if ($omochix_prompt_body) : ?>
                            <section class="prompt-body" aria-labelledby="prompt-body-title" data-prompt-copy-scope>
                                <div class="prompt-body__header">
                                    <h2 id="prompt-body-title"><?php esc_html_e('プロンプト本文', 'omochix'); ?></h2>
                                    <button type="button" class="prompt-body__copy" data-copy-prompt aria-describedby="prompt-copy-status"><?php esc_html_e('プロンプトをコピー', 'omochix'); ?></button>
                                </div>
                                <pre class="prompt-body__text" data-prompt-text><?php echo esc_html($omochix_prompt_body); ?></pre>
                                <p class="sr-only" id="prompt-copy-status" data-copy-status aria-live="polite"></p>
                            </section>
                        <?php endif; ?>

                        <?php if (trim((string) get_the_content())) : ?>
                            <section class="prompt-content article-content" aria-label="<?php esc_attr_e('プロンプトの説明', 'omochix'); ?>">
                                <?php the_content(); ?>
                            </section>
                        <?php endif; ?>

                        <?php if ($omochix_prompt_usage) : ?>
                            <section class="prompt-detail__panel" aria-labelledby="prompt-usage-title">
                                <h2 id="prompt-usage-title"><?php esc_html_e('使い方', 'omochix'); ?></h2>
                                <p><?php echo nl2br(esc_html($omochix_prompt_usage)); ?></p>
                            </section>
                        <?php endif; ?>

                        <?php if ($omochix_prompt_example) : ?>
                            <section class="prompt-detail__panel" aria-labelledby="prompt-example-title">
                                <h2 id="prompt-example-title"><?php esc_html_e('出力例', 'omochix'); ?></h2>
                                <p><?php echo nl2br(esc_html($omochix_prompt_example)); ?></p>
                            </section>
                        <?php endif; ?>
                    </div>

                    <aside class="prompt-detail__sidebar" aria-label="<?php esc_attr_e('プロンプトの補助情報', 'omochix'); ?>">
                        <?php if ($omochix_categories) : ?>
                            <section class="sidebar-panel" aria-labelledby="prompt-same-category-title">
                                <h2 id="prompt-same-category-title"><?php esc_html_e('同じカテゴリー', 'omochix'); ?></h2>
                                <?php
                                // A term's count only ever reflects published posts, so while
                                // every prompt in this category is still a draft it reads 0 —
                                // true but not useful to show, so the badge is hidden until
                                // this category has at least one published prompt.
                                ?>
                                <ul class="sidebar-links"><?php foreach ($omochix_categories as $omochix_term) : ?><li><a href="<?php echo esc_url(get_term_link($omochix_term)); ?>"><span><?php echo esc_html($omochix_term->name); ?></span><?php if ($omochix_term->count > 0) : ?><small><?php echo esc_html(number_format_i18n($omochix_term->count)); ?></small><?php endif; ?></a></li><?php endforeach; ?></ul>
                            </section>
                        <?php endif; ?>
                    </aside>
                </div>

                <?php if ($omochix_related_tools) : ?>
                    <section class="prompt-detail-related-tools" aria-labelledby="related-tools-title">
                        <div class="prompt-detail__container">
                            <header class="tool-detail-section-header"><p><?php esc_html_e('USE IT WITH', 'omochix'); ?></p><h2 id="related-tools-title"><?php esc_html_e('このプロンプトを使えるAIツール', 'omochix'); ?></h2></header>
                            <div class="tool-hub__grid">
                                <?php foreach ($omochix_related_tools as $omochix_tool) :
                                    $omochix_tool_description = get_post_meta($omochix_tool->ID, 'short_description', true);
                                    $omochix_tool_categories  = get_the_terms($omochix_tool->ID, 'ai_tool_category');
                                    $omochix_tool_category    = is_array($omochix_tool_categories) && $omochix_tool_categories ? $omochix_tool_categories[0] : null;
                                    ?>
                                    <a class="tool-hub-card" href="<?php echo esc_url(get_permalink($omochix_tool)); ?>">
                                        <strong><?php echo esc_html(get_the_title($omochix_tool)); ?></strong>
                                        <?php if ($omochix_tool_category) : ?><span><?php echo esc_html($omochix_tool_category->name); ?></span><?php endif; ?>
                                        <?php if ($omochix_tool_description) : ?><span><?php echo esc_html(wp_trim_words(wp_strip_all_tags($omochix_tool_description), 24, '…')); ?></span><?php endif; ?>
                                        <span class="tool-hub-card__arrow" aria-hidden="true">→</span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($omochix_related_prompts && $omochix_related_prompts->have_posts()) : ?>
                    <section class="prompt-detail-related" aria-labelledby="related-prompts-title">
                        <div class="prompt-detail__container">
                            <header class="tool-detail-section-header"><p><?php esc_html_e('MORE PROMPTS', 'omochix'); ?></p><h2 id="related-prompts-title"><?php esc_html_e('関連プロンプト', 'omochix'); ?></h2></header>
                            <div class="prompt-results__grid">
                                <?php while ($omochix_related_prompts->have_posts()) : $omochix_related_prompts->the_post();
                                    $omochix_related_id = get_the_ID();
                                    $omochix_related_difficulty = get_post_meta($omochix_related_id, 'prompt_difficulty', true) ?: 'beginner';
                                    ?>
                                    <article class="prompt-card"><a class="prompt-card__link" href="<?php the_permalink(); ?>">
                                        <div class="prompt-card__top"><span class="prompt-card__badge prompt-card__badge--<?php echo esc_attr($omochix_related_difficulty); ?>"><?php echo esc_html(function_exists('omochix_core_get_prompt_difficulty_label') ? omochix_core_get_prompt_difficulty_label($omochix_related_difficulty) : ''); ?></span></div>
                                        <h3><?php the_title(); ?></h3>
                                        <span class="prompt-card__more"><?php esc_html_e('プロンプトを見る', 'omochix'); ?><span aria-hidden="true">→</span></span>
                                    </a></article>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>

                <footer class="prompt-detail-cta">
                    <div class="prompt-detail__container prompt-detail-cta__inner">
                        <div><p><?php esc_html_e('NEXT STEP', 'omochix'); ?></p><h2><?php esc_html_e('他のプロンプトも探す。', 'omochix'); ?></h2></div>
                        <div class="prompt-detail-cta__links">
                            <a href="<?php echo esc_url($omochix_archive_url); ?>"><?php esc_html_e('プロンプトライブラリを見る', 'omochix'); ?></a>
                        </div>
                    </div>
                </footer>
            </article>
        </main>
        <?php
    endwhile;
endif;

get_footer();
