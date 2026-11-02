<?php
/**
 * SVG Motion Studio page ("/learn/svg-motion/").
 *
 * Served by the template_include filter in inc/learn-svg-motion.php. The preset
 * names and descriptions are in the HTML; assets/js/svg-motion/studio.js adds
 * the previews and controls. Without JavaScript the page says what it needs.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $omochix_data    = omochix_svg_motion_data();
    $omochix_hub_url = omochix_get_published_page_url('learn');
    $omochix_lead    = has_excerpt() ? trim(wp_strip_all_tags(get_the_excerpt())) : __('ロゴに付けられる動きを選んで、その場で試せます。', 'omochix');
    $omochix_labels  = ['entrance' => __('登場', 'omochix'), 'loop' => __('ループ', 'omochix')];
    ?>
    <main class="svg-motion" id="main-content" data-svg-motion>
        <header class="svg-motion__hero">
            <div class="svg-motion__container">
                <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                        <?php if ($omochix_hub_url) : ?><li><a href="<?php echo esc_url($omochix_hub_url); ?>"><?php esc_html_e('Learn', 'omochix'); ?></a></li><?php endif; ?>
                        <li aria-current="page"><?php the_title(); ?></li>
                    </ol>
                </nav>
                <p class="svg-motion__eyebrow"><?php esc_html_e('SVG MOTION STUDIO', 'omochix'); ?></p>
                <h1><?php the_title(); ?></h1>
                <p class="svg-motion__lead"><?php echo esc_html($omochix_lead); ?></p>
                <noscript><p class="svg-motion__notice"><?php esc_html_e('この機能には JavaScript が必要です。', 'omochix'); ?></p></noscript>
            </div>
        </header>

        <?php if (empty($omochix_data['presets'])) : ?>
            <div class="svg-motion__container"><p class="svg-motion__notice"><?php esc_html_e('プリセットを読み込めませんでした。', 'omochix'); ?></p></div>
        <?php else : ?>
        <div class="svg-motion__container svg-motion__layout">
            <section class="svg-motion__detail" aria-labelledby="svgm-title">
                <div class="svg-motion__stage" data-svgm-stage data-label="<?php esc_attr_e('選択中のモーションのプレビュー', 'omochix'); ?>"></div>
                <p class="svg-motion__notice" data-svgm-error data-message="<?php esc_attr_e('このロゴはプレビューできませんでした。', 'omochix'); ?>" data-too-complex="<?php esc_attr_e('このロゴは図形が多いため、このモーションは使えません。', 'omochix'); ?>" role="alert" hidden></p>
                <div class="svg-motion__controls">
                    <button type="button" class="svg-motion__button svg-motion__button--primary" data-svgm-toggle aria-pressed="false" data-play="<?php esc_attr_e('再生', 'omochix'); ?>" data-pause="<?php esc_attr_e('停止', 'omochix'); ?>"><?php esc_html_e('再生', 'omochix'); ?></button>
                    <button type="button" class="svg-motion__button" data-svgm-reset><?php esc_html_e('リセット', 'omochix'); ?></button>
                </div>
                <p class="sr-only" data-svgm-status aria-live="polite"></p>
                <h2 class="svg-motion__title" id="svgm-title" data-svgm-title></h2>
                <p class="svg-motion__description" data-svgm-description></p>

                <fieldset class="svg-motion__samples">
                    <legend><?php esc_html_e('見本のロゴ', 'omochix'); ?></legend>
                    <?php foreach ($omochix_data['samples'] as $omochix_sample) : ?>
                        <label class="svg-motion__sample"><input type="radio" name="svgm-sample" value="<?php echo esc_attr($omochix_sample['id']); ?>"><span><?php echo esc_html($omochix_sample['name']); ?></span></label>
                    <?php endforeach; ?>
                </fieldset>

                <div class="svg-motion__params-head">
                    <h3><?php esc_html_e('調整する', 'omochix'); ?></h3>
                    <button type="button" class="svg-motion__link" data-svgm-defaults><?php esc_html_e('既定に戻す', 'omochix'); ?></button>
                </div>
                <div class="svg-motion__params" data-svgm-params></div>
            </section>

            <section class="svg-motion__gallery" aria-labelledby="svgm-gallery-title">
                <div class="svg-motion__toolbar">
                    <h2 class="svg-motion__title" id="svgm-gallery-title"><?php esc_html_e('モーションを選ぶ', 'omochix'); ?></h2>
                    <button type="button" class="svg-motion__link" data-svgm-thumbs aria-pressed="false" data-stop="<?php esc_attr_e('一覧の動きを止める', 'omochix'); ?>" data-start="<?php esc_attr_e('一覧を動かす', 'omochix'); ?>"><?php esc_html_e('一覧の動きを止める', 'omochix'); ?></button>
                </div>
                <div class="svg-motion__chips" role="group" aria-label="<?php esc_attr_e('カテゴリ', 'omochix'); ?>">
                    <button type="button" class="svg-motion__chip" data-svgm-filter="all" aria-pressed="true"><?php esc_html_e('すべて', 'omochix'); ?></button>
                    <?php foreach ($omochix_labels as $omochix_key => $omochix_label) : ?>
                        <button type="button" class="svg-motion__chip" data-svgm-filter="<?php echo esc_attr($omochix_key); ?>" aria-pressed="false"><?php echo esc_html($omochix_label); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php /* translators: %d: number of presets shown. */ ?>
                <p class="svg-motion__count" data-svgm-count data-template="<?php esc_attr_e('%d 種類', 'omochix'); ?>"><?php echo esc_html(sprintf(__('%d 種類', 'omochix'), count($omochix_data['presets']))); ?></p>
                <fieldset class="svg-motion__grid">
                    <legend class="sr-only"><?php esc_html_e('モーション', 'omochix'); ?></legend>
                    <?php foreach ($omochix_data['presets'] as $omochix_preset) : ?>
                        <label class="svg-motion__card" data-svgm-card data-category="<?php echo esc_attr($omochix_preset['category']); ?>">
                            <input type="radio" name="svgm-preset" value="<?php echo esc_attr($omochix_preset['id']); ?>">
                            <span class="svg-motion__thumb"><img data-svgm-thumb="<?php echo esc_attr($omochix_preset['id']); ?>" alt="" width="96" height="96"></span>
                            <span class="svg-motion__card-name"><?php echo esc_html($omochix_preset['name']); ?></span>
                            <span class="svg-motion__badge"><?php echo esc_html($omochix_labels[$omochix_preset['category']] ?? ''); ?></span>
                            <span class="svg-motion__card-text"><?php echo esc_html($omochix_preset['description']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            </section>
        </div>
        <script type="application/json" id="omx-svg-motion-data"><?php echo omochix_svg_motion_data_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG in the helper. ?></script>
        <?php endif; ?>
    </main>
    <?php
endwhile;

get_footer();
