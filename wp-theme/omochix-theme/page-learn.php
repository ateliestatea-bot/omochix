<?php
/**
 * Learn hub page ("/learn").
 *
 * A plain WordPress page (slug "learn") resolves here automatically via the
 * native page-{slug}.php template hierarchy. Every descendant page instead
 * renders through page-learn-article.php; see inc/learn.php for how that is
 * detected.
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
    ?>

    <main class="learn-hub" id="main-content">
        <header class="learn-hub__hero">
            <div class="learn-hub__container">
                <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                        <li aria-current="page"><?php the_title(); ?></li>
                    </ol>
                </nav>
                <p class="learn-hub__eyebrow"><?php esc_html_e('LEARN', 'omochix'); ?></p>
                <h1><?php esc_html_e('AIを、使える知識に。', 'omochix'); ?></h1>
                <p class="learn-hub__lead">
                    <?php esc_html_e('ChatGPTの使い方からAI開発まで。「知る」だけで終わらず、使って・作って・公開できる実践型AI学習ガイド。', 'omochix'); ?>
                </p>
            </div>
        </header>

        <?php if ($omochix_learn_categories) : ?>
            <section class="learn-categories" aria-labelledby="learn-categories-title">
                <div class="learn-hub__container">
                    <header class="learn-hub__section-header">
                        <h2 id="learn-categories-title"><?php esc_html_e('カテゴリから探す', 'omochix'); ?></h2>
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
