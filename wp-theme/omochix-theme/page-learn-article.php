<?php
/**
 * Common Learn article template.
 *
 * Loaded for every descendant page of the Learn hub ("/learn/...", any
 * depth) via the template_include filter in inc/learn.php — there is no
 * page-{slug}.php file per article. Editors write POINT / TRY / WARNING /
 * CODE callouts directly in the block editor using the following classes on
 * a Custom HTML / Group block, which style.css already defines:
 *
 *   <div class="learn-callout learn-callout--point">...</div>
 *   <div class="learn-callout learn-callout--try">...</div>
 *   <div class="learn-callout learn-callout--warning">...</div>
 *   <div class="learn-callout learn-callout--code"><pre><code>...</code></pre></div>
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $omochix_learn_post_id  = get_the_ID();
    $omochix_learn_post     = get_post($omochix_learn_post_id);
    $omochix_learn_hub_url  = omochix_get_published_page_url('learn') ?: home_url('/learn/');
    $omochix_section_id     = omochix_get_learn_section_id($omochix_learn_post);
    $omochix_show_category  = $omochix_section_id !== $omochix_learn_post_id;
    $omochix_section_title  = $omochix_show_category ? get_the_title($omochix_section_id) : '';
    $omochix_section_url    = $omochix_show_category ? get_permalink($omochix_section_id) : '';

    $omochix_difficulty = get_post_meta($omochix_learn_post_id, 'learn_difficulty', true) ?: 'beginner';
    $omochix_minutes    = (int) get_post_meta($omochix_learn_post_id, 'learn_estimated_minutes', true);

    // Breadcrumb: Home > Learn > [section, if not this page itself] > Current.
    $omochix_breadcrumb_ancestors = array_reverse(get_post_ancestors($omochix_learn_post));

    // Siblings under the same parent (menu_order) power both "NEXT" and
    // "related articles" — this keeps top-level sections (siblings = the
    // other sections) and deep articles (siblings = other guides in the
    // same section) working the same way, with no extra meta field.
    $omochix_siblings_query = new WP_Query([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'post_parent'    => (int) $omochix_learn_post->post_parent,
        'post__not_in'   => [$omochix_learn_post_id],
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
    ]);
    $omochix_siblings = $omochix_siblings_query->posts;

    $omochix_next_post = null;
    foreach ($omochix_siblings_query->posts as $omochix_sibling) {
        if ((int) $omochix_sibling->menu_order > (int) $omochix_learn_post->menu_order) {
            $omochix_next_post = $omochix_sibling;
            break;
        }
    }
    $omochix_related_posts = array_slice(array_filter($omochix_siblings, static function ($sibling) use ($omochix_next_post) {
        return !$omochix_next_post || $sibling->ID !== $omochix_next_post->ID;
    }), 0, 3);

    // A section page (e.g. "/learn/tools") auto-lists its own direct child
    // pages ("/learn/tools/github", ...) so a new article only needs to be
    // created under it to appear here — no separate config to maintain.
    $omochix_learn_children = omochix_get_learn_child_pages($omochix_learn_post_id);
    $omochix_has_content    = '' !== trim((string) get_the_content());
    ?>

    <main class="learn-article" id="main-content">
        <header class="learn-article__header">
            <div class="learn-article__container">
                <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                        <li><a href="<?php echo esc_url($omochix_learn_hub_url); ?>"><?php esc_html_e('Learn', 'omochix'); ?></a></li>
                        <?php foreach ($omochix_breadcrumb_ancestors as $omochix_ancestor_id) : ?>
                            <?php if ((int) $omochix_ancestor_id === omochix_get_learn_page_id()) {
                                continue;
                            } ?>
                            <li><a href="<?php echo esc_url(get_permalink($omochix_ancestor_id)); ?>"><?php echo esc_html(get_the_title($omochix_ancestor_id)); ?></a></li>
                        <?php endforeach; ?>
                        <li aria-current="page"><?php the_title(); ?></li>
                    </ol>
                </nav>

                <?php if ($omochix_show_category) : ?>
                    <a class="learn-article__category" href="<?php echo esc_url($omochix_section_url); ?>"><?php echo esc_html($omochix_section_title); ?></a>
                <?php endif; ?>
                <h1><?php the_title(); ?></h1>

                <div class="learn-article__meta">
                    <span class="learn-article__badge learn-article__badge--<?php echo esc_attr($omochix_difficulty); ?>"><?php echo esc_html(omochix_get_learn_difficulty_label($omochix_difficulty)); ?></span>
                    <?php if ($omochix_minutes > 0) : ?>
                        <span><?php echo esc_html(sprintf(__('約%d分', 'omochix'), $omochix_minutes)); ?></span>
                    <?php endif; ?>
                    <span>
                        <?php esc_html_e('更新', 'omochix'); ?>
                        <time datetime="<?php echo esc_attr(get_the_modified_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_modified_date('Y.m.d')); ?></time>
                    </span>
                </div>
            </div>
        </header>

        <?php if ($omochix_has_content || !$omochix_learn_children) : ?>
            <div class="learn-article__container learn-article__body">
                <div class="learn-article__content article-content">
                    <?php if ($omochix_has_content) : ?>
                        <?php the_content(); ?>
                    <?php else : ?>
                        <div class="static-page__notice" role="status">
                            <h2><?php esc_html_e('ページ内容を準備中です。', 'omochix'); ?></h2>
                            <p><?php esc_html_e('確認済みの内容をWordPress管理画面から入力した後に公開してください。', 'omochix'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($omochix_learn_children) : ?>
            <section class="learn-categories" aria-labelledby="learn-children-title">
                <div class="learn-article__container">
                    <header class="learn-hub__section-header">
                        <h2 id="learn-children-title"><?php esc_html_e('このカテゴリで学ぶ', 'omochix'); ?></h2>
                    </header>
                    <div class="learn-categories__grid">
                        <?php foreach ($omochix_learn_children as $omochix_child) : ?>
                            <a class="learn-category-card" href="<?php echo esc_url(get_permalink($omochix_child)); ?>">
                                <strong><?php echo esc_html(get_the_title($omochix_child)); ?></strong>
                                <span><?php echo esc_html(omochix_get_learn_child_description($omochix_child)); ?></span>
                                <span class="learn-category-card__arrow" aria-hidden="true">→</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($omochix_next_post instanceof WP_Post) : ?>
            <section class="learn-article__next" aria-labelledby="learn-next-title">
                <div class="learn-article__container">
                    <a href="<?php echo esc_url(get_permalink($omochix_next_post)); ?>">
                        <span class="learn-article__next-label" id="learn-next-title"><?php esc_html_e('NEXT', 'omochix'); ?></span>
                        <strong><?php echo esc_html(get_the_title($omochix_next_post)); ?></strong>
                        <span aria-hidden="true">→</span>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($omochix_related_posts) : ?>
            <section class="learn-article__related" aria-labelledby="learn-related-title">
                <div class="learn-article__container">
                    <header class="learn-hub__section-header">
                        <h2 id="learn-related-title"><?php esc_html_e('関連記事', 'omochix'); ?></h2>
                    </header>
                    <div class="learn-related__grid">
                        <?php foreach ($omochix_related_posts as $omochix_related) : ?>
                            <article class="learn-related-card">
                                <a href="<?php echo esc_url(get_permalink($omochix_related)); ?>">
                                    <h3><?php echo esc_html(get_the_title($omochix_related)); ?></h3>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <?php
endwhile;

get_footer();
