<?php
/**
 * Motion Gallery page ("/learn/motion/") and sample detail ("/learn/motion/{slug}/").
 *
 * Served by the template_include filter in inc/learn-gallery.php for the
 * page at path "learn/motion". With the `omx_motion` query var set (and a
 * matching catalog item) the sample detail is rendered; otherwise the list.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $omochix_item_slug = omochix_learn_gallery_requested_slug();
    $omochix_item      = '' !== $omochix_item_slug ? omochix_learn_gallery_get_item($omochix_item_slug) : null;
    $omochix_list_url  = omochix_learn_gallery_url();
    $omochix_hub_url   = omochix_get_published_page_url('learn');
    ?>

    <?php if ($omochix_item) : ?>
        <main class="motion-detail" id="main-content" data-motion-root>
            <div class="motion-detail__container">
                <?php get_template_part('template-parts/learn-gallery/detail', null, ['item' => $omochix_item, 'context' => 'page']); ?>
            </div>
        </main>
    <?php else : ?>
        <?php
        $omochix_filters    = omochix_learn_gallery_sanitize_filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filters.
        $omochix_categories = omochix_learn_gallery_get_categories();
        $omochix_items      = omochix_learn_gallery_get_items();
        $omochix_matching   = omochix_learn_gallery_get_items($omochix_filters);
        $omochix_lead       = has_excerpt() ? trim(wp_strip_all_tags(get_the_excerpt())) : __('動きの「名前」を、動く見本つきで並べました。気に入ったものはプロンプトとコードをコピーして、そのままAIに頼めます。', 'omochix');
        $omochix_content    = trim((string) get_the_content());
        ?>
        <main class="motion-gallery" id="main-content" data-motion-root data-motion-gallery>
            <header class="motion-gallery__hero">
                <div class="motion-gallery__container">
                    <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                        <ol>
                            <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                            <?php if ($omochix_hub_url) : ?><li><a href="<?php echo esc_url($omochix_hub_url); ?>"><?php esc_html_e('Learn', 'omochix'); ?></a></li><?php endif; ?>
                            <li aria-current="page"><?php the_title(); ?></li>
                        </ol>
                    </nav>
                    <p class="motion-gallery__eyebrow"><?php esc_html_e('MOTION GALLERY', 'omochix'); ?></p>
                    <h1><?php the_title(); ?></h1>
                    <p class="motion-gallery__lead"><?php echo esc_html($omochix_lead); ?></p>
                </div>
            </header>

            <div class="motion-gallery__container">
                <?php get_template_part('template-parts/learn-gallery/filter-bar', null, ['filters' => $omochix_filters, 'total' => count($omochix_matching)]); ?>
            </div>

            <?php if (!$omochix_items) : ?>
                <div class="motion-gallery__container">
                    <div class="static-page__notice" role="status">
                        <h2><?php esc_html_e('見本を準備中です。', 'omochix'); ?></h2>
                        <p><?php esc_html_e('カタログが読み込めませんでした。テーマの assets/data/motion/catalog.json を確認してください。', 'omochix'); ?></p>
                    </div>
                </div>
            <?php else : ?>
                <div class="motion-gallery__container motion-gallery__sections">
                    <?php foreach ($omochix_categories as $omochix_category) : ?>
                        <?php
                        $omochix_section_items = omochix_learn_gallery_get_items(['cat' => $omochix_category['slug']]);
                        if (!$omochix_section_items) {
                            continue;
                        }
                        $omochix_section_visible = (bool) array_filter($omochix_section_items, static function ($candidate) use ($omochix_filters) {
                            return omochix_learn_gallery_item_matches($candidate, $omochix_filters);
                        });
                        $omochix_first = reset($omochix_section_items);
                        $omochix_last  = end($omochix_section_items);
                        ?>
                        <section class="motion-section" data-motion-section data-cat="<?php echo esc_attr($omochix_category['slug']); ?>" aria-labelledby="motion-section-<?php echo esc_attr($omochix_category['slug']); ?>"<?php echo $omochix_section_visible ? '' : ' hidden'; ?>>
                            <header class="motion-section__header">
                                <div>
                                    <h2 id="motion-section-<?php echo esc_attr($omochix_category['slug']); ?>"><?php echo esc_html($omochix_category['name']); ?></h2>
                                    <?php if ($omochix_category['description']) : ?><p><?php echo esc_html($omochix_category['description']); ?></p><?php endif; ?>
                                </div>
                                <span class="motion-section__range">#<?php echo esc_html($omochix_first['id']); ?>–<?php echo esc_html($omochix_last['id']); ?></span>
                            </header>
                            <div class="motion-grid">
                                <?php foreach ($omochix_section_items as $omochix_section_item) : ?>
                                    <?php get_template_part('template-parts/learn-gallery/card', null, ['item' => $omochix_section_item, 'visible' => omochix_learn_gallery_item_matches($omochix_section_item, $omochix_filters)]); ?>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                    <div class="motion-gallery__empty" data-motion-empty role="status"<?php echo $omochix_matching ? ' hidden' : ''; ?>>
                        <p><?php esc_html_e('条件に合う見本がありません。', 'omochix'); ?></p>
                        <a href="<?php echo esc_url($omochix_list_url); ?>" data-motion-reset><?php esc_html_e('絞り込みを解除する', 'omochix'); ?></a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($omochix_content) : ?>
                <section class="motion-gallery__editor-content" aria-label="<?php esc_attr_e('動く見本について', 'omochix'); ?>">
                    <div class="motion-gallery__container static-page__editor-content">
                        <?php the_content(); ?>
                    </div>
                </section>
            <?php endif; ?>

            <dialog class="motion-dialog" data-motion-dialog aria-label="<?php esc_attr_e('見本の詳細', 'omochix'); ?>">
                <div class="motion-dialog__frame">
                    <button type="button" class="motion-dialog__close icon-button" data-motion-dialog-close aria-label="<?php esc_attr_e('閉じる', 'omochix'); ?>">
                        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 5 14 14M19 5 5 19"/></svg>
                    </button>
                    <div class="motion-dialog__body" data-motion-dialog-body></div>
                </div>
            </dialog>
        </main>
    <?php endif; ?>

    <dialog class="motion-expand" data-motion-expand aria-label="<?php esc_attr_e('プレビューを拡大', 'omochix'); ?>">
        <button type="button" class="motion-expand__close icon-button" data-motion-expand-close aria-label="<?php esc_attr_e('閉じる', 'omochix'); ?>">
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 5 14 14M19 5 5 19"/></svg>
        </button>
        <div class="motion-expand__stage" data-motion-expand-stage></div>
        <p class="motion-expand__caption" data-motion-expand-caption></p>
    </dialog>
    <?php
endwhile;

get_footer();
