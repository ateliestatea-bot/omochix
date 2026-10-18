<?php
/**
 * Learn hub page ("/learn").
 *
 * A plain WordPress page (slug "learn") resolves here automatically via the
 * native page-{slug}.php template hierarchy. Every descendant page instead
 * renders through page-learn-article.php (see inc/learn.php), except the
 * Motion Gallery page "learn/motion" (see inc/learn-gallery.php).
 *
 * Learn v2 layout: hero with two calls to action, "見て試す" (featured
 * motion samples), "学ぶ" (category cards), Learning Path, "使う" (latest
 * prompts) and a "作る" teaser for the Playground / OmochiX Video work that
 * is not built yet. Every section that depends on content that is not
 * published yet is simply left out, so the page never links to a 404.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $omochix_learn_categories = omochix_get_learn_categories();
    $omochix_learning_path    = omochix_get_learn_learning_path_steps();
    $omochix_gallery_url      = function_exists('omochix_learn_gallery_url') ? omochix_learn_gallery_url() : '';
    $omochix_prompts_url      = get_post_type_archive_link('prompt') ?: '';
    $omochix_featured         = $omochix_gallery_url && function_exists('omochix_learn_gallery_get_featured') ? omochix_learn_gallery_get_featured(3) : [];
    $omochix_latest_prompts   = post_type_exists('prompt') ? get_posts([
        'post_type'           => 'prompt',
        'post_status'         => 'publish',
        'has_password'        => false,
        'posts_per_page'      => 3,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'orderby'             => ['date' => 'DESC', 'ID' => 'DESC'],
    ]) : [];

    if ($omochix_gallery_url) {
        // "動く見本" goes first: it is the new entry point of Learn v2.
        array_unshift($omochix_learn_categories, [
            'name'        => __('動く見本', 'omochix'),
            'description' => __('動きの名前を、動く見本つきで探す', 'omochix'),
            'url'         => $omochix_gallery_url,
        ]);
    }
    ?>

    <main class="learn-hub learn-hub-v2" id="main-content" data-motion-root>
        <header class="learn-hub__hero">
            <div class="learn-hub__container">
                <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                        <li aria-current="page"><?php the_title(); ?></li>
                    </ol>
                </nav>
                <p class="learn-hub__eyebrow"><?php esc_html_e('LEARN', 'omochix'); ?></p>
                <h1><?php esc_html_e('見て、試して、AIで作る。', 'omochix'); ?></h1>
                <p class="learn-hub__lead">
                    <?php esc_html_e('ChatGPTの使い方からAI開発まで。動く見本を見て、プロンプトをコピーして、実際に作って公開するところまでを一本の流れで学べる、実践型のAI学習ガイドです。', 'omochix'); ?>
                </p>
                <?php if ($omochix_gallery_url || $omochix_prompts_url) : ?>
                    <div class="learn-hub__cta">
                        <?php if ($omochix_gallery_url) : ?>
                            <a class="motion-button" href="<?php echo esc_url($omochix_gallery_url); ?>"><?php esc_html_e('動く見本を見る', 'omochix'); ?><span aria-hidden="true">→</span></a>
                        <?php endif; ?>
                        <?php if ($omochix_prompts_url) : ?>
                            <a class="motion-button motion-button--ghost" href="<?php echo esc_url($omochix_prompts_url); ?>"><?php esc_html_e('プロンプトを探す', 'omochix'); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($omochix_featured) : ?>
            <section class="learn-hub-v2__section" aria-labelledby="learn-featured-title">
                <div class="learn-hub__container">
                    <header class="learn-hub-v2__section-header">
                        <div>
                            <p class="learn-hub__eyebrow"><?php esc_html_e('MOTION GALLERY', 'omochix'); ?></p>
                            <h2 id="learn-featured-title"><?php esc_html_e('見て試す', 'omochix'); ?></h2>
                            <p><?php esc_html_e('動きには名前があります。見本を見て、名前で頼めるようになると、AIへの指示が一気に伝わりやすくなります。', 'omochix'); ?></p>
                        </div>
                        <a class="learn-hub-v2__more" href="<?php echo esc_url($omochix_gallery_url); ?>"><?php esc_html_e('すべての見本', 'omochix'); ?> <span aria-hidden="true">→</span></a>
                    </header>
                    <div class="learn-hub-v2__featured">
                        <?php foreach ($omochix_featured as $omochix_sample) : ?>
                            <article class="motion-card">
                                <div class="motion-card__preview">
                                    <?php get_template_part('template-parts/learn-gallery/preview', null, ['item' => $omochix_sample, 'size' => 'hub']); ?>
                                </div>
                                <div class="motion-card__body">
                                    <div class="motion-card__meta">
                                        <span class="motion-card__id">#<?php echo esc_html($omochix_sample['id']); ?></span>
                                        <span class="motion-badge motion-badge--<?php echo esc_attr($omochix_sample['level']); ?>"><?php echo esc_html(omochix_learn_gallery_level_label($omochix_sample['level'])); ?></span>
                                    </div>
                                    <h3 class="motion-card__title">
                                        <a class="motion-card__link" href="<?php echo esc_url(omochix_learn_gallery_url($omochix_sample)); ?>">
                                            <?php echo esc_html($omochix_sample['name']['ja']); ?>
                                            <span class="motion-card__en" lang="en"><?php echo esc_html($omochix_sample['name']['en']); ?></span>
                                        </a>
                                    </h3>
                                    <p class="motion-card__summary"><?php echo esc_html($omochix_sample['summary']); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($omochix_learn_categories) : ?>
            <section class="learn-categories" aria-labelledby="learn-categories-title">
                <div class="learn-hub__container">
                    <header class="learn-hub__section-header">
                        <p class="learn-hub__eyebrow"><?php esc_html_e('LEARN', 'omochix'); ?></p>
                        <h2 id="learn-categories-title"><?php esc_html_e('学ぶ：カテゴリから探す', 'omochix'); ?></h2>
                    </header>
                    <div class="learn-categories__grid">
                        <?php foreach ($omochix_learn_categories as $omochix_category) : ?>
                            <a class="learn-category-card" href="<?php echo esc_url($omochix_category['url']); ?>">
                                <strong><?php echo esc_html($omochix_category['name']); ?></strong>
                                <span><?php echo esc_html($omochix_category['description']); ?></span>
                                <span class="learn-category-card__arrow" aria-hidden="true">→</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="learn-path" aria-labelledby="learn-path-title">
            <div class="learn-hub__container">
                <header class="learn-hub__section-header">
                    <p class="learn-hub__eyebrow"><?php esc_html_e('LEARNING PATH', 'omochix'); ?></p>
                    <h2 id="learn-path-title"><?php esc_html_e('AIで0からWebサービスを公開する', 'omochix'); ?></h2>
                </header>
                <ol class="learn-path__steps">
                    <?php foreach ($omochix_learning_path as $omochix_step) : ?>
                        <?php $omochix_step_tag = $omochix_step['url'] ? 'a' : 'div'; ?>
                        <li>
                            <<?php echo esc_html($omochix_step_tag); ?> class="learn-path__step<?php echo $omochix_step['url'] ? '' : ' is-coming-soon'; ?>"<?php if ($omochix_step['url']) : ?> href="<?php echo esc_url($omochix_step['url']); ?>"<?php else : ?> aria-disabled="true"<?php endif; ?>>
                                <span class="learn-path__number"><?php echo esc_html($omochix_step['number']); ?></span>
                                <span class="learn-path__body">
                                    <strong><?php echo esc_html($omochix_step['title']); ?></strong>
                                    <span><?php echo esc_html($omochix_step['description']); ?></span>
                                    <?php if (!$omochix_step['url']) : ?><small><?php esc_html_e('準備中', 'omochix'); ?></small><?php endif; ?>
                                </span>
                            </<?php echo esc_html($omochix_step_tag); ?>>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>

        <?php if ($omochix_latest_prompts && $omochix_prompts_url) : ?>
            <section class="learn-hub-v2__section" aria-labelledby="learn-prompts-title">
                <div class="learn-hub__container">
                    <header class="learn-hub-v2__section-header">
                        <div>
                            <p class="learn-hub__eyebrow"><?php esc_html_e('PROMPT LIBRARY', 'omochix'); ?></p>
                            <h2 id="learn-prompts-title"><?php esc_html_e('使う：新着プロンプト', 'omochix'); ?></h2>
                        </div>
                        <a class="learn-hub-v2__more" href="<?php echo esc_url($omochix_prompts_url); ?>"><?php esc_html_e('プロンプトライブラリ', 'omochix'); ?> <span aria-hidden="true">→</span></a>
                    </header>
                    <div class="learn-hub-v2__prompts">
                        <?php foreach ($omochix_latest_prompts as $omochix_prompt_post) : ?>
                            <?php
                            $omochix_prompt_difficulty = get_post_meta($omochix_prompt_post->ID, 'prompt_difficulty', true) ?: 'beginner';
                            $omochix_prompt_models     = get_the_terms($omochix_prompt_post->ID, 'prompt_model');
                            $omochix_prompt_model      = is_array($omochix_prompt_models) && $omochix_prompt_models ? $omochix_prompt_models[0] : null;
                            ?>
                            <article class="prompt-card">
                                <a class="prompt-card__link" href="<?php echo esc_url(get_permalink($omochix_prompt_post)); ?>">
                                    <div class="prompt-card__top">
                                        <span class="prompt-card__badge prompt-card__badge--<?php echo esc_attr($omochix_prompt_difficulty); ?>"><?php echo esc_html(omochix_get_learn_difficulty_label($omochix_prompt_difficulty)); ?></span>
                                        <?php if ($omochix_prompt_model) : ?><span class="prompt-card__model"><?php echo esc_html($omochix_prompt_model->name); ?></span><?php endif; ?>
                                    </div>
                                    <h3><?php echo esc_html(get_the_title($omochix_prompt_post)); ?></h3>
                                    <?php if (has_excerpt($omochix_prompt_post)) : ?>
                                        <p class="prompt-card__description"><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt($omochix_prompt_post)), 40, '…')); ?></p>
                                    <?php endif; ?>
                                    <span class="prompt-card__more"><?php esc_html_e('プロンプトを見る', 'omochix'); ?><span aria-hidden="true">→</span></span>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="learn-hub-v2__section" aria-labelledby="learn-build-title">
            <div class="learn-hub__container">
                <header class="learn-hub-v2__section-header">
                    <div>
                        <p class="learn-hub__eyebrow"><?php esc_html_e('COMING SOON', 'omochix'); ?></p>
                        <h2 id="learn-build-title"><?php esc_html_e('作る', 'omochix'); ?></h2>
                    </div>
                </header>
                <div class="learn-hub-v2__soon-grid">
                    <div class="learn-hub-v2__soon" aria-disabled="true">
                        <small><?php esc_html_e('準備中', 'omochix'); ?></small>
                        <strong><?php esc_html_e('Playground', 'omochix'); ?></strong>
                        <span><?php esc_html_e('見本の長さ・色・動き方をその場で変えて、調整したコードとプロンプトを持ち帰れるようにします。', 'omochix'); ?></span>
                    </div>
                    <div class="learn-hub-v2__soon" aria-disabled="true">
                        <small><?php esc_html_e('準備中', 'omochix'); ?></small>
                        <strong><?php esc_html_e('OmochiX Video', 'omochix'); ?></strong>
                        <span><?php esc_html_e('見本の使い方を短い動画で解説し、動画から見本・プロンプトへ行き来できるようにします。', 'omochix'); ?></span>
                    </div>
                </div>
            </div>
        </section>

        <?php
        $omochix_learn_content = trim((string) get_the_content());
        if ($omochix_learn_content) :
            ?>
            <section class="learn-hub__editor-content" aria-label="<?php esc_attr_e('Learnについて', 'omochix'); ?>">
                <div class="learn-hub__container static-page__editor-content">
                    <?php the_content(); ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <?php
endwhile;

get_footer();
