<?php
/**
 * Shared layout for public-facing static pages.
 *
 * Expected arguments:
 * - type
 * - description
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_static_page_type        = isset($args['type']) ? sanitize_key($args['type']) : 'default';
$omochix_static_page_description = isset($args['description']) ? (string) $args['description'] : '';

get_header();

while (have_posts()) :
    the_post();

    $omochix_page_content = trim((string) get_the_content());
    $omochix_posts_page   = get_post((int) get_option('page_for_posts'));
    $omochix_news_url     = $omochix_posts_page instanceof WP_Post && 'publish' === $omochix_posts_page->post_status
        ? get_permalink($omochix_posts_page)
        : '';
    $omochix_tools_url    = get_post_type_archive_link('ai_tool');
    ?>
    <main class="static-page static-page--<?php echo esc_attr($omochix_static_page_type); ?>" id="main-content">
        <header class="static-page__hero">
            <div class="static-page__container">
                <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                        <li aria-current="page"><?php the_title(); ?></li>
                    </ol>
                </nav>

                <div class="static-page__hero-layout">
                    <div class="static-page__hero-copy">
                        <h1><?php the_title(); ?></h1>
                        <?php if ($omochix_static_page_description) : ?>
                            <p><?php echo esc_html($omochix_static_page_description); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ('about' === $omochix_static_page_type) : ?>
                        <div class="static-page__mascot" aria-hidden="true">
                            <?php if (file_exists(get_theme_file_path('/assets/img/omochi-hero.webp'))) : ?>
                                <img src="<?php echo esc_url(get_theme_file_uri('/assets/img/omochi-hero.webp')); ?>" width="360" height="420" alt="" loading="eager" decoding="async">
                            <?php else : ?>
                                <span>O</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="static-page__container static-page__body">
            <?php if ('about' === $omochix_static_page_type) : ?>
                <div class="static-page__prose">
                    <section aria-labelledby="about-omochix-title">
                        <h2 id="about-omochix-title"><?php esc_html_e('OmochiXとは', 'omochix'); ?></h2>
                        <p><?php esc_html_e('OmochiXは、AI初心者でも必要な情報へ迷わずたどり着けることを目指すAI情報メディアです。ニュース、ツール、実践的な活用方法を分かりやすく整理して届けます。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="about-mission-title">
                        <h2 id="about-mission-title"><?php esc_html_e('Mission', 'omochix'); ?></h2>
                        <p class="static-page__lead"><?php esc_html_e('AIをもっと身近に。', 'omochix'); ?></p>
                        <p><?php esc_html_e('最新情報だけではなく、本当に役立つAI活用法を届けることで、次に何を選べばよいか判断しやすい場所をつくります。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="about-editorial-title">
                        <h2 id="about-editorial-title"><?php esc_html_e('編集方針', 'omochix'); ?></h2>
                        <ul>
                            <li><?php esc_html_e('公式発表などの一次情報を優先します。', 'omochix'); ?></li>
                            <li><?php esc_html_e('公開前に事実関係と出典を確認します。', 'omochix'); ?></li>
                            <li><?php esc_html_e('公開日と更新日を分かりやすく表示します。', 'omochix'); ?></li>
                            <li><?php esc_html_e('AIを利用した場合も、編集者が内容を確認します。', 'omochix'); ?></li>
                            <li><?php esc_html_e('広告と編集コンテンツを明確に分離します。', 'omochix'); ?></li>
                        </ul>
                    </section>

                    <section aria-labelledby="about-trust-title">
                        <h2 id="about-trust-title"><?php esc_html_e('情報の信頼性', 'omochix'); ?></h2>
                        <p><?php esc_html_e('AI分野は変化が速いため、参照した情報源と確認時点を重視します。変更が確認された内容は、履歴を尊重しながら継続的に更新します。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="about-ai-title">
                        <h2 id="about-ai-title"><?php esc_html_e('AIとの付き合い方', 'omochix'); ?></h2>
                        <p><?php esc_html_e('AIは判断を助ける道具であり、すべての答えを代わりに決めるものではありません。利便性と限界の両方を伝え、安心して選べる情報を提供します。', 'omochix'); ?></p>
                    </section>

                    <p class="static-page__updated">
                        <?php esc_html_e('最終更新日：', 'omochix'); ?>
                        <time datetime="<?php echo esc_attr(get_the_modified_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_modified_date('Y年n月j日')); ?></time>
                    </p>

                    <?php if ($omochix_page_content) : ?>
                        <section aria-labelledby="about-additional-title">
                            <h2 id="about-additional-title"><?php esc_html_e('OmochiXについて', 'omochix'); ?></h2>
                            <div class="static-page__editor-content">
                                <?php the_content(); ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            <?php elseif ('company' === $omochix_static_page_type) : ?>
                <div class="static-page__prose">
                    <dl class="static-page__facts">
                        <div>
                            <dt><?php esc_html_e('サイト名', 'omochix'); ?></dt>
                            <dd><?php echo esc_html(get_bloginfo('name')); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('運営', 'omochix'); ?></dt>
                            <dd><?php echo esc_html(get_option('omochix_company_name', __('OmochiX編集部', 'omochix'))); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('所在地', 'omochix'); ?></dt>
                            <dd><?php esc_html_e('運営者確認待ち', 'omochix'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('お問い合わせ', 'omochix'); ?></dt>
                            <dd><?php esc_html_e('要設定', 'omochix'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('設立', 'omochix'); ?></dt>
                            <dd><?php esc_html_e('運営者確認待ち', 'omochix'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('更新日', 'omochix'); ?></dt>
                            <dd><time datetime="<?php echo esc_attr(get_the_modified_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_modified_date()); ?></time></dd>
                        </div>
                    </dl>

                    <?php if ($omochix_page_content) : ?>
                        <div class="static-page__editor-content">
                            <?php the_content(); ?>
                        </div>
                    <?php else : ?>
                        <div class="static-page__notice" role="status">
                            <h2><?php esc_html_e('運営情報を準備中です。', 'omochix'); ?></h2>
                            <p><?php esc_html_e('会社名、所在地、連絡先などの確定情報を管理画面から設定した後に公開してください。', 'omochix'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="static-page__prose">
                    <?php if ($omochix_page_content) : ?>
                        <div class="static-page__editor-content">
                            <?php the_content(); ?>
                        </div>
                    <?php elseif ('contact' !== $omochix_static_page_type) : ?>
                        <div class="static-page__notice" role="status">
                            <h2><?php esc_html_e('ページ内容を準備中です。', 'omochix'); ?></h2>
                            <p><?php esc_html_e('確認済みの内容をWordPress管理画面から入力した後に公開してください。', 'omochix'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ('contact' === $omochix_static_page_type) : ?>
                        <?php get_template_part('template-parts/contact-form'); ?>

                        <section aria-labelledby="contact-response-title">
                            <h2 id="contact-response-title"><?php esc_html_e('返信について', 'omochix'); ?></h2>
                            <p><?php esc_html_e('返信目安と受付条件は、お問い合わせ窓口の運用内容が確定した後にご案内します。', 'omochix'); ?></p>
                        </section>
                        <section aria-labelledby="contact-notes-title">
                            <h2 id="contact-notes-title"><?php esc_html_e('お問い合わせ前の注意事項', 'omochix'); ?></h2>
                            <p><?php esc_html_e('個人情報、パスワード、APIキーなどの機密情報は送信しないでください。', 'omochix'); ?></p>
                        </section>
                        <section aria-labelledby="contact-hours-title">
                            <h2 id="contact-hours-title"><?php esc_html_e('対応時間', 'omochix'); ?></h2>
                            <p><?php esc_html_e('運営者確認待ち', 'omochix'); ?></p>
                        </section>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($omochix_news_url || $omochix_tools_url) : ?>
            <section class="static-page__cta" aria-labelledby="static-page-cta-title">
                <div class="static-page__container static-page__cta-inner">
                    <div>
                        <h2 id="static-page-cta-title"><?php esc_html_e('OmochiXの情報を探す', 'omochix'); ?></h2>
                        <p><?php esc_html_e('最新のAIニュースや、目的に合うAIツールを分かりやすく紹介しています。', 'omochix'); ?></p>
                    </div>
                    <div class="static-page__actions">
                        <?php if ($omochix_news_url) : ?>
                            <a class="static-page__button static-page__button--primary" href="<?php echo esc_url($omochix_news_url); ?>"><?php esc_html_e('AIニュースを見る', 'omochix'); ?></a>
                        <?php endif; ?>
                        <?php if ($omochix_tools_url) : ?>
                            <a class="static-page__button static-page__button--secondary" href="<?php echo esc_url($omochix_tools_url); ?>"><?php esc_html_e('AIツールを見る', 'omochix'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <?php
endwhile;

get_footer();
