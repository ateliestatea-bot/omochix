<?php
/**
 * Design Library: top ("/design/", every work grouped by medium), list
 * ("/design/{web|video}/") and detail ("/design/{medium}/{slug}/"). Served by
 * the template_include filter in inc/design-library.php only for views that
 * pass the publish conditions; everything else is already a 404.
 *
 * Works without JavaScript: the filters are a GET form, cards show their
 * poster, the detail page shows the sandboxed demo, the video with controls,
 * or the poster.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();
    $omx_cur     = omochix_design_current();
    $omx_mediums = omochix_design_mediums();
    ?>
    <main class="dl" id="main-content" data-dl-root>
        <?php if ('detail' === $omx_cur['view']) : ?>
            <?php get_template_part('template-parts/design-library/detail', null, ['item' => $omx_cur['item']]); ?>
        <?php else : ?>
            <?php
            $omx_is_list = 'list' === $omx_cur['view'];
            $omx_medium  = $omx_is_list ? $omx_cur['medium'] : '';
            $omx_filters = omochix_design_sanitize_filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
            if ($omx_is_list) {
                $omx_filters['medium'] = '';
            }
            $omx_matching = omochix_design_get_items($omx_medium, $omx_filters);
            $omx_sections = $omx_is_list ? [$omx_medium] : array_values(array_filter(omochix_design_public_mediums(), 'omochix_design_is_public'));
            ?>
            <header class="dl-hero">
                <div class="dl-container">
                    <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                        <ol>
                            <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                            <?php if ($omx_is_list) : ?>
                                <li><a href="<?php echo esc_url(omochix_design_url()); ?>"><?php the_title(); ?></a></li>
                                <li aria-current="page"><?php echo esc_html($omx_mediums[$omx_medium]['label']); ?></li>
                            <?php else : ?>
                                <li aria-current="page"><?php the_title(); ?></li>
                            <?php endif; ?>
                        </ol>
                    </nav>
                    <p class="dl-eyebrow">DESIGN LIBRARY</p>
                    <h1><?php echo esc_html($omx_is_list ? $omx_mediums[$omx_medium]['label'] : get_the_title()); ?></h1>
                    <p class="dl-lead"><?php esc_html_e('Web デザインと動画モーションの作品を、動くプレビューで見比べられます。作品ごとに使った技術、制作手順、AI に頼むときのプロンプトをまとめています。', 'omochix'); ?></p>
                    <nav class="dl-kinds" aria-label="<?php esc_attr_e('作品の種類', 'omochix'); ?>">
                        <?php foreach (omochix_design_public_mediums() as $omx_m) : ?>
                            <?php if (!omochix_design_is_public($omx_m)) { continue; } ?>
                            <a class="dl-kinds__link" href="<?php echo esc_url(omochix_design_medium_url($omx_m)); ?>"<?php echo ($omx_is_list && $omx_m === $omx_medium) ? ' aria-current="page"' : ''; ?>>
                                <?php echo esc_html($omx_mediums[$omx_m]['label']); ?>
                                <span class="dl-kinds__count"><?php echo esc_html((string) count(omochix_design_get_items($omx_m))); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </header>

            <div class="dl-container">
                <?php get_template_part('template-parts/design-library/filter-bar', null, ['medium' => $omx_medium, 'filters' => $omx_filters, 'total' => count($omx_matching)]); ?>

                <?php $omx_i = 0; foreach ($omx_sections as $omx_m) : ?>
                    <?php $omx_section_items = omochix_design_get_items($omx_m); ?>
                    <section class="dl-section" data-dl-section aria-labelledby="dl-section-<?php echo esc_attr($omx_m); ?>"<?php echo !array_intersect_key($omx_section_items, $omx_matching) ? ' hidden' : ''; ?>>
                        <div class="dl-section-head">
                            <h2 id="dl-section-<?php echo esc_attr($omx_m); ?>"><?php echo esc_html($omx_mediums[$omx_m]['label']); ?></h2>
                            <?php if (!$omx_is_list) : ?>
                                <a class="dl-more" href="<?php echo esc_url(omochix_design_medium_url($omx_m)); ?>"><?php echo esc_html(sprintf(__('%sをすべて見る', 'omochix'), $omx_mediums[$omx_m]['label'])); ?> <span aria-hidden="true">→</span></a>
                            <?php endif; ?>
                        </div>
                        <ul class="dl-grid" data-dl-grid>
                            <?php foreach ($omx_section_items as $omx_item) : ?>
                                <?php
                                get_template_part('template-parts/design-library/card', null, ['item' => $omx_item, 'hidden' => !isset($omx_matching[$omx_item['slug']]), 'eager' => $omx_i < 2]);
                                $omx_i++;
                                ?>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
                <p class="dl-empty" data-dl-empty<?php echo $omx_matching ? ' hidden' : ''; ?>><?php esc_html_e('条件に合う作品がありません。条件を減らしてみてください。', 'omochix'); ?></p>

                <aside class="dl-about" aria-labelledby="dl-about-h">
                    <h2 id="dl-about-h"><?php esc_html_e('このライブラリーについて', 'omochix'); ?></h2>
                    <p><?php esc_html_e('掲載している作品は、OmochiX が CSS・SVG・WebGL などで一から制作したものです。製品名や数値はすべて架空です。Web デザインのライブデモは、サイト本体から切り離した環境で動きます。検証の状態は作品ごとの詳細ページに書いています。', 'omochix'); ?></p>
                </aside>
            </div>
        <?php endif; ?>
    </main>
    <?php
endwhile;

get_footer();
