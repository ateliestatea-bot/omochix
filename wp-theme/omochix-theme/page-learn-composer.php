<?php
/**
 * Motion Composer page ("/learn/motion/compose/").
 *
 * Served by the template_include filter in inc/learn-composer.php. Renders
 * three empty slots, the sample picker (every catalog sample, which also
 * registers their previews for learn-gallery.js) and the markup template a
 * filled slot is built from. learn-composer.js does the rest; without
 * JavaScript the page explains what it is and links to the gallery.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $omochix_list_url   = omochix_learn_gallery_url();
    $omochix_hub_url    = omochix_get_published_page_url('learn');
    $omochix_items      = omochix_learn_gallery_get_items();
    $omochix_categories = omochix_learn_gallery_get_categories();
    $omochix_lead       = has_excerpt() ? trim(wp_strip_all_tags(get_the_excerpt())) : __('動く見本を3つまで選んで順番を決め、ひとつの流れとして確かめられます。組み合わせはリンクとして共有できます。', 'omochix');
    ?>
    <main class="motion-composer" id="main-content" data-motion-root data-composer>
        <header class="motion-composer__hero">
            <div class="motion-composer__container">
                <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                        <?php if ($omochix_hub_url) : ?><li><a href="<?php echo esc_url($omochix_hub_url); ?>"><?php esc_html_e('Learn', 'omochix'); ?></a></li><?php endif; ?>
                        <li><a href="<?php echo esc_url($omochix_list_url); ?>"><?php esc_html_e('動く見本', 'omochix'); ?></a></li>
                        <li aria-current="page"><?php the_title(); ?></li>
                    </ol>
                </nav>
                <p class="motion-composer__eyebrow"><?php esc_html_e('MOTION COMPOSER', 'omochix'); ?></p>
                <h1><?php the_title(); ?></h1>
                <p class="motion-composer__lead"><?php echo esc_html($omochix_lead); ?></p>
                <noscript><p class="motion-composer__noscript"><?php esc_html_e('この機能には JavaScript が必要です。', 'omochix'); ?> <a href="<?php echo esc_url($omochix_list_url); ?>"><?php esc_html_e('動く見本の一覧へ', 'omochix'); ?></a></p></noscript>
            </div>
        </header>

        <div class="motion-composer__container">
            <section class="motion-composer__slots" aria-labelledby="composer-slots-title">
                <div class="motion-composer__toolbar">
                    <h2 id="composer-slots-title" class="motion-composer__title"><?php esc_html_e('選んだ見本', 'omochix'); ?> <span class="motion-composer__count" data-composer-count aria-live="polite">0 / <?php echo (int) OMOCHIX_LEARN_COMPOSER_MAX_SLOTS; ?></span></h2>
                    <div class="motion-composer__actions">
                        <button type="button" class="motion-copy motion-copy--ghost" data-composer-clear hidden><?php esc_html_e('すべて外す', 'omochix'); ?></button>
                        <button type="button" class="motion-copy" data-composer-share hidden><?php esc_html_e('この組み合わせのリンクをコピー', 'omochix'); ?></button>
                    </div>
                </div>
                <p class="sr-only" data-composer-status aria-live="polite"></p>
                <ol class="motion-composer__list" data-composer-slots>
                    <?php for ($omochix_i = 0; $omochix_i < OMOCHIX_LEARN_COMPOSER_MAX_SLOTS; $omochix_i++) : ?>
                        <?php get_template_part('template-parts/learn-composer/slot', null, ['index' => $omochix_i]); ?>
                    <?php endfor; ?>
                </ol>
            </section>

            <?php $omochix_stage = omochix_learn_gallery_get_stage(); ?>
            <section class="motion-composer__combined" data-composer-combined hidden aria-labelledby="composer-combined-title">
                <div class="motion-composer__toolbar">
                    <h2 id="composer-combined-title" class="motion-composer__title"><?php esc_html_e('組み合わせて見る', 'omochix'); ?></h2>
                    <p class="motion-composer__hint"><?php esc_html_e('選んだ順に上から並べて、同時に再生します。', 'omochix'); ?></p>
                </div>
                <div class="motion-preview motion-preview--combined" data-motion-preview data-slug="composer" style="--stage-w: <?php echo (int) $omochix_stage['width']; ?>; --stage-h: <?php echo (int) $omochix_stage['height']; ?>;">
                    <div class="motion-preview__poster" aria-hidden="true">
                        <span class="motion-preview__poster-name"><?php esc_html_e('組み合わせプレビュー', 'omochix'); ?></span>
                    </div>
                    <div class="motion-preview__frame" data-motion-frame data-label="<?php esc_attr_e('組み合わせプレビュー', 'omochix'); ?>"></div>
                    <div class="motion-preview__controls" data-motion-controls hidden>
                        <button type="button" class="motion-preview__btn" data-motion-toggle aria-label="<?php esc_attr_e('組み合わせプレビューを一時停止', 'omochix'); ?>">
                            <svg class="motion-preview__icon motion-preview__icon--pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5h3v14H8zM13 5h3v14h-3z"/></svg>
                            <svg class="motion-preview__icon motion-preview__icon--play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        </button>
                    </div>
                </div>
            </section>

            <?php get_template_part('template-parts/learn-composer/picker', null, ['items' => $omochix_items, 'categories' => $omochix_categories]); ?>
        </div>
    </main>
    <?php
endwhile;

get_footer();
