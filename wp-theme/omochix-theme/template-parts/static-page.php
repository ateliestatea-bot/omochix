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

                    <section aria-labelledby="about-disclaimer-title">
                        <h2 id="about-disclaimer-title"><?php esc_html_e('免責事項', 'omochix'); ?></h2>
                        <p><?php esc_html_e('本サイトが掲載するAIニュース・AIツールの情報は、正確性・最新性を保証するものではありません。', 'omochix'); ?></p>
                        <p><?php esc_html_e('AIツール等の利用可否・適否についてのご判断は、利用者ご自身の責任で行ってください。', 'omochix'); ?></p>
                        <p><?php esc_html_e('本サイトからリンクする外部サイトの内容について、OmochiXは責任を負いません。', 'omochix'); ?></p>
                        <p><?php esc_html_e('掲載内容は、必要に応じて予告なく訂正・更新することがあります。', 'omochix'); ?></p>
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
                <?php $omochix_company_contact_url = omochix_get_published_page_url('contact'); ?>
                <div class="static-page__prose">
                    <dl class="static-page__facts">
                        <div>
                            <dt><?php esc_html_e('運営', 'omochix'); ?></dt>
                            <dd><?php esc_html_e('OmochiX編集部', 'omochix'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('運営形態', 'omochix'); ?></dt>
                            <dd><?php esc_html_e('個人運営', 'omochix'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('内容', 'omochix'); ?></dt>
                            <dd><?php esc_html_e('AIに関するニュース・ツール・活用情報等の情報提供', 'omochix'); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('お問い合わせ', 'omochix'); ?></dt>
                            <dd>
                                <?php if ($omochix_company_contact_url) : ?>
                                    <a href="<?php echo esc_url($omochix_company_contact_url); ?>"><?php esc_html_e('Contactページ', 'omochix'); ?></a>
                                <?php else : ?>
                                    <?php esc_html_e('Contactページ', 'omochix'); ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e('対応', 'omochix'); ?></dt>
                            <dd><?php esc_html_e('お問い合わせには順次対応いたします。内容により返信までお時間をいただく場合があります。', 'omochix'); ?></dd>
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
                    <?php endif; ?>
                </div>
            <?php elseif ('privacy' === $omochix_static_page_type) : ?>
                <?php $omochix_privacy_contact_url = omochix_get_published_page_url('contact'); ?>
                <div class="static-page__prose">
                    <p><?php esc_html_e('OmochiX編集部（以下「当メディア」）は、本サイトをご利用いただく皆さまの個人情報を適切に取り扱うため、以下のとおりプライバシーポリシーを定めます。', 'omochix'); ?></p>

                    <section aria-labelledby="privacy-collection-title">
                        <h2 id="privacy-collection-title"><?php esc_html_e('1. 個人情報の取得', 'omochix'); ?></h2>
                        <p><?php esc_html_e('当メディアは、お問い合わせフォームを通じて、お名前・メールアドレス・お問い合わせ内容をご提供いただく場合があります。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="privacy-purpose-title">
                        <h2 id="privacy-purpose-title"><?php esc_html_e('2. 利用目的', 'omochix'); ?></h2>
                        <p><?php esc_html_e('取得した個人情報は、お問い合わせ内容の確認、対応、必要な連絡・返信のためにのみ利用します。あらかじめお知らせした目的の範囲を超えて利用することはありません。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="privacy-management-title">
                        <h2 id="privacy-management-title"><?php esc_html_e('3. 個人情報の管理', 'omochix'); ?></h2>
                        <p><?php esc_html_e('取得した個人情報は、利用目的の達成に必要な期間のみ保持し、不要となった場合は適切に削除します。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="privacy-third-party-title">
                        <h2 id="privacy-third-party-title"><?php esc_html_e('4. 第三者提供', 'omochix'); ?></h2>
                        <p><?php esc_html_e('取得した個人情報は、法令に基づく場合等を除き、ご本人の同意なく第三者に提供しません。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="privacy-rights-title">
                        <h2 id="privacy-rights-title"><?php esc_html_e('5. 開示・訂正・削除・利用停止等', 'omochix'); ?></h2>
                        <p><?php esc_html_e('ご自身の個人情報の開示・訂正・削除・利用停止等をご希望の場合は、下記のお問い合わせ窓口までご連絡ください。内容を確認のうえ、法令に従い遅滞なく対応します。本人確認のため、追加の情報をお伺いする場合があります。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="privacy-contact-title">
                        <h2 id="privacy-contact-title"><?php esc_html_e('6. お問い合わせ窓口', 'omochix'); ?></h2>
                        <p>
                            <?php if ($omochix_privacy_contact_url) : ?>
                                <?php
                                echo wp_kses(
                                    sprintf(
                                        /* translators: %s: contact page link markup. */
                                        __('個人情報に関するお問い合わせは、%sよりご連絡ください。', 'omochix'),
                                        '<a href="' . esc_url($omochix_privacy_contact_url) . '">' . esc_html__('お問い合わせフォーム', 'omochix') . '</a>'
                                    ),
                                    ['a' => ['href' => []]]
                                );
                                ?>
                            <?php else : ?>
                                <?php esc_html_e('個人情報に関するお問い合わせは、お問い合わせフォームよりご連絡ください。', 'omochix'); ?>
                            <?php endif; ?>
                        </p>
                        <p><?php esc_html_e('運営者に関する法令上の開示事項については、お問い合わせフォームよりご請求ください。法令に従い、本人確認のうえ遅滞なく回答します。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="privacy-cookies-title">
                        <h2 id="privacy-cookies-title"><?php esc_html_e('7. Cookie・localStorage・アクセス解析等', 'omochix'); ?></h2>
                        <p><?php esc_html_e('本サイトは、ダークモード表示設定を保存するために、お使いのブラウザのlocalStorageを利用しています。この情報が外部へ送信されることはありません。', 'omochix'); ?></p>
                        <p><?php esc_html_e('本サイトは現在、アクセス解析ツール・広告配信ツール・アフィリエイトタグを使用していません。将来これらを導入する場合は、本ポリシーを改定してお知らせします。', 'omochix'); ?></p>
                    </section>

                    <section aria-labelledby="privacy-changes-title">
                        <h2 id="privacy-changes-title"><?php esc_html_e('8. プライバシーポリシーの変更', 'omochix'); ?></h2>
                        <p><?php esc_html_e('本ポリシーの内容は、法令の変更やサイト運営状況の変化に応じて、予告なく改定することがあります。改定後の内容は、本ページに掲載した時点から効力を持ちます。', 'omochix'); ?></p>
                    </section>

                    <p class="static-page__updated">
                        <?php esc_html_e('制定・最終改定日：', 'omochix'); ?>
                        <time datetime="<?php echo esc_attr(get_the_modified_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_modified_date('Y年n月j日')); ?></time>
                    </p>

                    <?php if ($omochix_page_content) : ?>
                        <div class="static-page__editor-content">
                            <?php the_content(); ?>
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
                            <p><?php esc_html_e('お問い合わせには順次対応いたします。内容により返信までお時間をいただく場合があります。', 'omochix'); ?></p>
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
