<?php
/**
 * Front page template.
 *
 * The content values are kept together so they can later be replaced with
 * Customizer, block or custom-field values without restructuring the markup.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$omochix_hero = [
    'eyebrow'     => __('AIで迷ったら、まずOmochiX。', 'omochix'),
    'title_first' => __('AIで世界の情報を、', 'omochix'),
    'title_last'  => __('もっとシンプルに。', 'omochix'),
    'description' => __('最新のAIニュース、便利なAIツール、実践的な活用方法まで。あなたのAIライフを、OmochiXがナビゲートします。', 'omochix'),
    'image_alt'   => __('紫色のパーカーを着たフレンチブルドッグ「おもち」', 'omochix'),
];

$omochix_popular_searches = [
    'ChatGPT',
    'Claude',
    'Midjourney',
    'Gemini',
    'Copilot',
    __('AIエージェント', 'omochix'),
];

// Add assets/img/omochi-hero.webp to replace the accessible placeholder.
$omochix_hero_image_path = get_theme_file_path('/assets/img/omochi-hero.webp');
$omochix_has_hero_image  = file_exists($omochix_hero_image_path);
?>

<main class="site-main site-main--home" id="main-content">
    <section class="home-hero" aria-labelledby="home-hero-title">
        <div class="home-hero__inner">
            <div class="home-hero__content">
                <p class="home-hero__eyebrow">
                    <span aria-hidden="true"></span>
                    <?php echo esc_html($omochix_hero['eyebrow']); ?>
                </p>

                <h1 class="home-hero__title" id="home-hero-title">
                    <?php echo esc_html($omochix_hero['title_first']); ?><br>
                    <span><?php echo esc_html($omochix_hero['title_last']); ?></span>
                </h1>

                <p class="home-hero__description">
                    <?php echo esc_html($omochix_hero['description']); ?>
                </p>

                <form class="home-hero__search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <label class="sr-only" for="home-hero-search"><?php esc_html_e('サイト内を検索', 'omochix'); ?></label>
                    <svg class="home-hero__search-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="10.8" cy="10.8" r="6.8"/>
                        <path d="m16 16 4.2 4.2"/>
                    </svg>
                    <input
                        id="home-hero-search"
                        name="s"
                        type="search"
                        value="<?php echo esc_attr(get_search_query()); ?>"
                        placeholder="<?php esc_attr_e('AIについて何でも検索...', 'omochix'); ?>"
                        enterkeyhint="search"
                    >
                    <button type="submit"><?php esc_html_e('検索', 'omochix'); ?></button>
                </form>

                <nav class="home-hero__popular" aria-label="<?php esc_attr_e('人気の検索ワード', 'omochix'); ?>">
                    <span><?php esc_html_e('人気の検索', 'omochix'); ?></span>
                    <ul>
                        <?php foreach ($omochix_popular_searches as $omochix_search_term) : ?>
                            <li>
                                <a href="<?php echo esc_url(add_query_arg('s', $omochix_search_term, home_url('/'))); ?>">
                                    <?php echo esc_html($omochix_search_term); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>

            <div class="home-hero__visual" aria-label="<?php esc_attr_e('おもちのAIナビゲーション', 'omochix'); ?>">
                <div class="home-hero__image-frame">
                    <?php if ($omochix_has_hero_image) : ?>
                        <img
                            src="<?php echo esc_url(get_theme_file_uri('/assets/img/omochi-hero.webp')); ?>"
                            width="720"
                            height="840"
                            alt="<?php echo esc_attr($omochix_hero['image_alt']); ?>"
                            fetchpriority="high"
                            decoding="async"
                        >
                    <?php else : ?>
                        <div class="home-hero__image-placeholder" role="img" aria-label="<?php echo esc_attr($omochix_hero['image_alt']); ?>">
                            <span class="home-hero__placeholder-mark" aria-hidden="true">O</span>
                            <span><?php esc_html_e('おもち、準備中。', 'omochix'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="home-hero__info-card home-hero__info-card--news" aria-label="<?php esc_attr_e('トレンドニュース', 'omochix'); ?>">
                    <span class="home-hero__card-icon" aria-hidden="true">↗</span>
                    <div>
                        <small><?php esc_html_e('いま知りたい', 'omochix'); ?></small>
                        <strong><?php esc_html_e('トレンドニュース', 'omochix'); ?></strong>
                    </div>
                </aside>

                <aside class="home-hero__info-card home-hero__info-card--tools" aria-label="<?php esc_attr_e('注目AIツール', 'omochix'); ?>">
                    <span class="home-hero__card-icon" aria-hidden="true">＋</span>
                    <div>
                        <small><?php esc_html_e('仕事をスマートに', 'omochix'); ?></small>
                        <strong><?php esc_html_e('注目AIツール', 'omochix'); ?></strong>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <?php
    $omochix_home_posts_page_id = (int) get_option('page_for_posts');
    $omochix_home_posts_page    = $omochix_home_posts_page_id ? get_post($omochix_home_posts_page_id) : null;
    $omochix_home_destinations  = [
        [
            'label'       => __('AIニュース', 'omochix'),
            'description' => __('最新のAIニュースをわかりやすくお届け', 'omochix'),
            'url'         => $omochix_home_posts_page instanceof WP_Post && 'publish' === $omochix_home_posts_page->post_status ? get_permalink($omochix_home_posts_page) : '',
            'icon'        => 'news',
        ],
        [
            'label'       => __('AIツール', 'omochix'),
            'description' => __('便利なAIツールを厳選して紹介', 'omochix'),
            'url'         => get_post_type_archive_link('ai_tool'),
            'icon'        => 'tools',
        ],
        [
            'label'       => __('動画', 'omochix'),
            'description' => __('動画でAI活用を学ぶ', 'omochix'),
            'url'         => omochix_get_published_page_url('videos'),
            'icon'        => 'video',
        ],
        [
            'label'       => __('コミュニティ', 'omochix'),
            'description' => __('AI仲間と情報を共有する', 'omochix'),
            'url'         => omochix_get_published_page_url('community'),
            'icon'        => 'community',
        ],
        [
            'label'       => __('About', 'omochix'),
            'description' => __('OmochiXについてもっと知る', 'omochix'),
            'url'         => omochix_get_published_page_url('about'),
            'icon'        => 'about',
        ],
    ];
    ?>

    <section class="home-guide" aria-labelledby="home-guide-title">
        <div class="home-guide__inner">
            <header class="home-guide__header">
                <h2 id="home-guide-title"><?php esc_html_e('OmochiXでできること', 'omochix'); ?></h2>
            </header>
            <div class="home-guide__grid">
                <?php foreach ($omochix_home_destinations as $omochix_destination) : ?>
                    <?php $omochix_destination_tag = $omochix_destination['url'] ? 'a' : 'div'; ?>
                    <<?php echo esc_html($omochix_destination_tag); ?> class="home-guide__card<?php echo $omochix_destination['url'] ? '' : ' is-coming-soon'; ?>"<?php if ($omochix_destination['url']) : ?> href="<?php echo esc_url($omochix_destination['url']); ?>"<?php else : ?> aria-disabled="true"<?php endif; ?>>
                        <span class="home-guide__icon home-guide__icon--<?php echo esc_attr($omochix_destination['icon']); ?>" aria-hidden="true"></span>
                        <strong><?php echo esc_html($omochix_destination['label']); ?></strong>
                        <span><?php echo esc_html($omochix_destination['description']); ?></span>
                        <?php if (!$omochix_destination['url']) : ?><small><?php esc_html_e('準備中', 'omochix'); ?></small><?php endif; ?>
                    </<?php echo esc_html($omochix_destination_tag); ?>>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php
    /**
     * Latest news configuration.
     * Change post_type to "news" when a dedicated news post type is introduced.
     */
    $omochix_news_post_type = 'post';
    $omochix_news_limit     = 4;
    $omochix_sticky_ids     = array_values(array_filter(array_map('absint', (array) get_option('sticky_posts', []))));
    $omochix_news_posts     = [];

    // Sticky posts are deliberately queried first so editorial picks take priority.
    if (!empty($omochix_sticky_ids)) {
        $omochix_featured_news = new WP_Query([
            'post_type'           => $omochix_news_post_type,
            'post_status'         => 'publish',
            'posts_per_page'      => $omochix_news_limit,
            'post__in'            => $omochix_sticky_ids,
            'orderby'             => 'post__in',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ]);
        $omochix_news_posts = $omochix_featured_news->posts;
    }

    // Fill any remaining positions with the newest non-sticky posts.
    $omochix_remaining_news = $omochix_news_limit - count($omochix_news_posts);
    if ($omochix_remaining_news > 0) {
        $omochix_latest_news = new WP_Query([
            'post_type'           => $omochix_news_post_type,
            'post_status'         => 'publish',
            'posts_per_page'      => $omochix_remaining_news,
            'post__not_in'        => $omochix_sticky_ids,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ]);
        $omochix_news_posts = array_merge($omochix_news_posts, $omochix_latest_news->posts);
    }

    $omochix_posts_page_id = (int) get_option('page_for_posts');
    $omochix_news_url      = $omochix_posts_page_id ? get_permalink($omochix_posts_page_id) : home_url('/');
    ?>

    <section class="latest-news" aria-labelledby="latest-news-title">
        <div class="latest-news__inner">
            <header class="latest-news__header">
                <div class="latest-news__heading">
                    <span class="latest-news__accent" aria-hidden="true">N</span>
                    <div>
                        <h2 id="latest-news-title"><?php esc_html_e('最新のAIニュース', 'omochix'); ?></h2>
                        <p><?php esc_html_e('今、注目すべきAIの最新動向をいち早くお届け', 'omochix'); ?></p>
                    </div>
                </div>
                <a class="latest-news__all-link" href="<?php echo esc_url($omochix_news_url); ?>">
                    <?php esc_html_e('すべて見る', 'omochix'); ?>
                    <span aria-hidden="true">→</span>
                </a>
            </header>

            <?php if (!empty($omochix_news_posts)) : ?>
                <div class="latest-news__grid">
                    <?php
                    foreach ($omochix_news_posts as $post) :
                        setup_postdata($post);

                        $omochix_thumbnail_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
                        $omochix_categories    = get_the_category();
                        $omochix_category      = !empty($omochix_categories) ? $omochix_categories[0]->name : __('AIニュース', 'omochix');
                        $omochix_excerpt       = wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 46, '…');
                        $omochix_content_size  = strlen(wp_strip_all_tags(get_the_content()));
                        $omochix_reading_time  = max(1, (int) ceil($omochix_content_size / 1200));
                        $omochix_is_new        = (current_time('timestamp') - get_post_time('U')) <= (3 * DAY_IN_SECONDS);
                        ?>
                        <article class="news-card">
                            <a class="news-card__link" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf(__('記事を読む：%s', 'omochix'), get_the_title())); ?>">
                                <div class="news-card__media">
                                    <?php if ($omochix_thumbnail_url) : ?>
                                        <img
                                            src="<?php echo esc_url($omochix_thumbnail_url); ?>"
                                            alt=""
                                            width="640"
                                            height="400"
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    <?php else : ?>
                                        <div class="news-card__placeholder" role="img" aria-label="<?php esc_attr_e('OmochiXニュースのアイキャッチ画像', 'omochix'); ?>">
                                            <span aria-hidden="true">OmochiX</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($omochix_is_new) : ?>
                                        <span class="news-card__new"><?php esc_html_e('NEW', 'omochix'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="news-card__body">
                                    <div class="news-card__meta">
                                        <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('Y.m.d')); ?></time>
                                        <span aria-hidden="true">·</span>
                                        <span class="news-card__category"><?php echo esc_html($omochix_category); ?></span>
                                    </div>
                                    <h3><?php echo esc_html(get_the_title()); ?></h3>
                                    <p class="news-card__excerpt"><?php echo esc_html($omochix_excerpt); ?></p>
                                    <div class="news-card__footer">
                                        <span><?php echo esc_html(sprintf(__('約%d分で読めます', 'omochix'), $omochix_reading_time)); ?></span>
                                        <span class="news-card__read-more"><?php esc_html_e('記事を読む', 'omochix'); ?> <span aria-hidden="true">→</span></span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="latest-news__empty" role="status">
                    <span aria-hidden="true">O</span>
                    <p><?php esc_html_e('最新ニュースは準備中です。', 'omochix'); ?></p>
                </div>
            <?php endif; ?>

            <?php wp_reset_postdata(); ?>
        </div>
    </section>

    <?php
    /**
     * Featured AI tools.
     * Native post meta is used so no custom-field plugin is required.
     */
    $omochix_tool_limit = 6;
    $omochix_tools      = [];
    $omochix_tool_ids   = [];

    if (post_type_exists('ai_tool')) {
        $omochix_featured_tools_query = new WP_Query([
            'post_type'           => 'ai_tool',
            'post_status'         => 'publish',
            'posts_per_page'      => $omochix_tool_limit,
            'meta_query'          => [
                [
                    'key'     => 'is_featured',
                    'value'   => ['1', 'true', 'yes'],
                    'compare' => 'IN',
                ],
            ],
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ]);

        // Apply an optional editorial display order without excluding posts that omit it.
        usort($omochix_featured_tools_query->posts, static function ($first, $second) {
            $first_order  = get_post_meta($first->ID, 'display_order', true);
            $second_order = get_post_meta($second->ID, 'display_order', true);
            $first_order  = is_numeric($first_order) ? (int) $first_order : PHP_INT_MAX;
            $second_order = is_numeric($second_order) ? (int) $second_order : PHP_INT_MAX;
            return $first_order <=> $second_order;
        });

        $omochix_tool_posts = array_slice($omochix_featured_tools_query->posts, 0, $omochix_tool_limit);
        $omochix_tool_ids   = wp_list_pluck($omochix_tool_posts, 'ID');
        $omochix_tool_slots = $omochix_tool_limit - count($omochix_tool_posts);

        if ($omochix_tool_slots > 0) {
            $omochix_new_tools_query = new WP_Query([
                'post_type'           => 'ai_tool',
                'post_status'         => 'publish',
                'posts_per_page'      => $omochix_tool_slots,
                'post__not_in'        => $omochix_tool_ids,
                'orderby'             => 'date',
                'order'               => 'DESC',
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            ]);
            $omochix_tool_posts = array_merge($omochix_tool_posts, $omochix_new_tools_query->posts);
        }

        foreach ($omochix_tool_posts as $omochix_tool_post) {
            $omochix_tool_id          = $omochix_tool_post->ID;
            $omochix_company_value    = get_post_meta($omochix_tool_id, 'company_id', true);
            $omochix_logo_value       = get_post_meta($omochix_tool_id, 'tool_logo', true);
            $omochix_tool_terms       = get_the_terms($omochix_tool_id, 'ai_tool_category');
            $omochix_company_name     = is_numeric($omochix_company_value) ? get_the_title((int) $omochix_company_value) : $omochix_company_value;
            $omochix_logo_url         = is_numeric($omochix_logo_value) ? wp_get_attachment_image_url((int) $omochix_logo_value, 'thumbnail') : $omochix_logo_value;
            $omochix_short_description = get_post_meta($omochix_tool_id, 'short_description', true);

            if (!$omochix_logo_url) {
                $omochix_logo_url = get_the_post_thumbnail_url($omochix_tool_id, 'thumbnail');
            }
            if (!$omochix_short_description) {
                $omochix_short_description = get_the_excerpt($omochix_tool_id);
            }

            $omochix_tools[] = [
                'name'        => get_the_title($omochix_tool_id),
                'company'     => $omochix_company_name ?: __('運営会社情報なし', 'omochix'),
                'description' => $omochix_short_description ?: __('詳しいツール情報を準備中です。', 'omochix'),
                'rating'      => get_post_meta($omochix_tool_id, 'rating_overall', true),
                'pricing'     => get_post_meta($omochix_tool_id, 'pricing_type', true) ?: __('料金情報なし', 'omochix'),
                'category'    => (!is_wp_error($omochix_tool_terms) && !empty($omochix_tool_terms)) ? $omochix_tool_terms[0]->name : __('AIツール', 'omochix'),
                'japanese'    => in_array(strtolower((string) get_post_meta($omochix_tool_id, 'japanese_support', true)), ['1', 'true', 'yes'], true),
                'free_plan'   => in_array(strtolower((string) get_post_meta($omochix_tool_id, 'has_free_plan', true)), ['1', 'true', 'yes'], true),
                'logo'        => $omochix_logo_url,
                'url'         => get_permalink($omochix_tool_id),
            ];
        }
    }

    // Fill remaining positions with stable starter data, avoiding duplicate names.
    $omochix_existing_tool_names = array_map(static function ($tool) {
        return strtolower($tool['name']);
    }, $omochix_tools);

    foreach (omochix_get_starter_ai_tools() as $omochix_starter_tool) {
        if (count($omochix_tools) >= $omochix_tool_limit) {
            break;
        }
        if (in_array(strtolower($omochix_starter_tool['name']), $omochix_existing_tool_names, true)) {
            continue;
        }
        $omochix_starter_tool['logo'] = '';
        $omochix_starter_tool['url']  = home_url('/ai-tools/' . $omochix_starter_tool['slug'] . '/');
        $omochix_tools[]              = $omochix_starter_tool;
        $omochix_existing_tool_names[] = strtolower($omochix_starter_tool['name']);
    }

    $omochix_tools_archive_url = post_type_exists('ai_tool') ? get_post_type_archive_link('ai_tool') : '';
    $omochix_tools_archive_url = $omochix_tools_archive_url ?: home_url('/ai-tools/');
    ?>

    <section class="featured-tools" aria-labelledby="featured-tools-title">
        <div class="featured-tools__inner">
            <header class="featured-tools__header">
                <div>
                    <p class="featured-tools__eyebrow"><?php esc_html_e('OMOCHIX SELECT', 'omochix'); ?></p>
                    <h2 id="featured-tools-title"><?php esc_html_e('注目のAIツール', 'omochix'); ?></h2>
                    <p class="featured-tools__lead"><?php esc_html_e('目的に合った、今使うべきAIツールが見つかる', 'omochix'); ?></p>
                </div>
                <a class="featured-tools__all-link" href="<?php echo esc_url($omochix_tools_archive_url); ?>">
                    <?php esc_html_e('すべてのツールを見る', 'omochix'); ?>
                    <span aria-hidden="true">→</span>
                </a>
            </header>

            <?php if (!empty($omochix_tools)) : ?>
                <div class="featured-tools__grid">
                    <?php foreach ($omochix_tools as $omochix_tool) : ?>
                        <?php
                        $omochix_tool_name = $omochix_tool['name'] ?: __('名称未設定', 'omochix');
                        $omochix_initial   = function_exists('mb_substr') ? mb_substr($omochix_tool_name, 0, 1) : substr($omochix_tool_name, 0, 1);
                        $omochix_rating    = is_numeric($omochix_tool['rating']) ? number_format_i18n((float) $omochix_tool['rating'], 1) : '';
                        ?>
                        <article class="tool-card">
                            <a class="tool-card__link" href="<?php echo esc_url($omochix_tool['url']); ?>" aria-label="<?php echo esc_attr(sprintf(__('%sの詳細を見る', 'omochix'), $omochix_tool_name)); ?>">
                                <div class="tool-card__topline">
                                    <div class="tool-card__logo">
                                        <?php if (!empty($omochix_tool['logo'])) : ?>
                                            <img src="<?php echo esc_url($omochix_tool['logo']); ?>" width="56" height="56" alt="<?php echo esc_attr($omochix_tool_name . __('のロゴ', 'omochix')); ?>" loading="lazy" decoding="async">
                                        <?php else : ?>
                                            <span aria-hidden="true"><?php echo esc_html(strtoupper($omochix_initial)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tool-card__rating" aria-label="<?php echo $omochix_rating ? esc_attr(sprintf(__('OmochiX編集部評価、5点中%s', 'omochix'), $omochix_rating)) : esc_attr__('OmochiX編集部評価は準備中です', 'omochix'); ?>">
                                        <span aria-hidden="true">★</span>
                                        <strong><?php echo $omochix_rating ? esc_html($omochix_rating) : '—'; ?></strong>
                                    </div>
                                </div>

                                <div class="tool-card__identity">
                                    <h3><?php echo esc_html($omochix_tool_name); ?></h3>
                                    <p><?php echo esc_html($omochix_tool['company']); ?></p>
                                </div>
                                <p class="tool-card__description"><?php echo esc_html(wp_strip_all_tags($omochix_tool['description'])); ?></p>
                                <p class="tool-card__category"><?php echo esc_html($omochix_tool['category']); ?></p>

                                <ul class="tool-card__tags" aria-label="<?php esc_attr_e('ツールの対応状況', 'omochix'); ?>">
                                    <li><?php echo esc_html($omochix_tool['pricing']); ?></li>
                                    <li><?php echo esc_html($omochix_tool['japanese'] ? __('日本語対応', 'omochix') : __('日本語未対応', 'omochix')); ?></li>
                                    <li><?php echo esc_html($omochix_tool['free_plan'] ? __('無料プランあり', 'omochix') : __('無料プランなし', 'omochix')); ?></li>
                                </ul>

                                <div class="tool-card__footer">
                                    <span><?php esc_html_e('自分に合うか確認する', 'omochix'); ?></span>
                                    <span aria-hidden="true">→</span>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="featured-tools__empty" role="status">
                    <p><?php esc_html_e('AIツール情報は準備中です。', 'omochix'); ?></p>
                </div>
            <?php endif; ?>

            <?php wp_reset_postdata(); ?>
        </div>
    </section>

    <?php
    /**
     * Purpose-led categories.
     * Existing terms use their canonical archive; missing terms use a valid
     * search URL rather than linking to an archive that may not exist.
     */
    $omochix_categories = [];
    foreach (omochix_get_front_page_categories() as $omochix_category_item) {
        $omochix_category_term = null;
        $omochix_category_url  = '';
        $omochix_category_count = 0;

        if (taxonomy_exists($omochix_category_item['taxonomy'])) {
            $omochix_category_term = get_term_by('slug', $omochix_category_item['slug'], $omochix_category_item['taxonomy']);

            // A name match makes existing Japanese terms work even before slugs are standardized.
            if (!$omochix_category_term) {
                $omochix_category_term = get_term_by('name', $omochix_category_item['name'], $omochix_category_item['taxonomy']);
            }
        }

        if ($omochix_category_term && !is_wp_error($omochix_category_term)) {
            $omochix_term_link = get_term_link($omochix_category_term);
            if (!is_wp_error($omochix_term_link)) {
                $omochix_category_url   = $omochix_term_link;
                $omochix_category_count = max(0, (int) $omochix_category_term->count);
            }
        }

        if (!$omochix_category_url) {
            $omochix_category_url = add_query_arg('s', $omochix_category_item['name'], home_url('/'));
        }

        $omochix_category_item['url']   = $omochix_category_url;
        $omochix_category_item['count'] = $omochix_category_count;
        $omochix_categories[]           = $omochix_category_item;
    }

    $omochix_category_page_id = (int) get_option('page_for_posts');
    $omochix_all_categories_url = $omochix_category_page_id
        ? get_permalink($omochix_category_page_id)
        : add_query_arg('s', __('AI', 'omochix'), home_url('/'));
    ?>

    <section class="popular-categories" aria-labelledby="popular-categories-title">
        <div class="popular-categories__inner">
            <header class="popular-categories__header">
                <div>
                    <p class="popular-categories__eyebrow"><?php esc_html_e('FIND YOUR WAY', 'omochix'); ?></p>
                    <h2 id="popular-categories-title"><?php esc_html_e('カテゴリーから探す', 'omochix'); ?></h2>
                    <p class="popular-categories__lead"><?php esc_html_e('やりたいことや目的から、必要なAI情報を見つける', 'omochix'); ?></p>
                </div>
                <a class="popular-categories__all-link" href="<?php echo esc_url($omochix_all_categories_url); ?>">
                    <?php esc_html_e('すべてのカテゴリーを見る', 'omochix'); ?>
                    <span aria-hidden="true">→</span>
                </a>
            </header>

            <?php if (!empty($omochix_categories)) : ?>
                <!-- Reusable, visually consistent icon set. -->
                <svg class="popular-categories__icon-sprite" aria-hidden="true">
                    <symbol id="category-icon-news" viewBox="0 0 24 24"><path d="M5 4.5h11.5A2.5 2.5 0 0 1 19 7v12.5H6.5A1.5 1.5 0 0 1 5 18V4.5Z"/><path d="M8.5 8h7M8.5 11.5h7M8.5 15h4.5"/></symbol>
                    <symbol id="category-icon-tools" viewBox="0 0 24 24"><path d="m14.5 5 4.5 4.5-9.5 9.5H5v-4.5L14.5 5Z"/><path d="m12.5 7 4.5 4.5M5 19h14"/></symbol>
                    <symbol id="category-icon-tutorial" viewBox="0 0 24 24"><path d="M4 6.5 12 3l8 3.5-8 3.5-8-3.5Z"/><path d="M7 9v5.5c2.8 2 7.2 2 10 0V9M20 7v6"/></symbol>
                    <symbol id="category-icon-prompt" viewBox="0 0 24 24"><path d="M5 5h14v11H9l-4 3V5Z"/><path d="M8.5 9h7M8.5 12h4"/></symbol>
                    <symbol id="category-icon-business" viewBox="0 0 24 24"><rect x="4" y="7" width="16" height="12" rx="2"/><path d="M9 7V5h6v2M4 12h16M10 12v2h4v-2"/></symbol>
                    <symbol id="category-icon-code" viewBox="0 0 24 24"><path d="m8.5 7-5 5 5 5M15.5 7l5 5-5 5M13.5 4l-3 16"/></symbol>
                    <symbol id="category-icon-design" viewBox="0 0 24 24"><path d="M12 4a8 8 0 1 0 0 16h1.2a1.8 1.8 0 0 0 0-3.6H12a1.5 1.5 0 0 1 0-3h2.5A5.5 5.5 0 0 0 12 4Z"/><circle cx="8" cy="9" r=".8"/><circle cx="12" cy="7.5" r=".8"/><circle cx="16" cy="9.5" r=".8"/></symbol>
                    <symbol id="category-icon-life" viewBox="0 0 24 24"><path d="M12 20v-9M12 14c-4.5 0-7-2.5-7-7 4.5 0 7 2.5 7 7ZM12 11c0-4 2.3-6 6.5-6 0 4-2.3 6-6.5 6Z"/></symbol>
                </svg>

                <div class="popular-categories__grid">
                    <?php foreach ($omochix_categories as $omochix_category) : ?>
                        <article class="category-card">
                            <a class="category-card__link" href="<?php echo esc_url($omochix_category['url']); ?>" aria-label="<?php echo esc_attr(sprintf(__('%1$sを見る、%2$d件', 'omochix'), $omochix_category['name'], $omochix_category['count'])); ?>">
                                <span class="category-card__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24"><use href="#category-icon-<?php echo esc_attr($omochix_category['icon']); ?>"></use></svg>
                                </span>
                                <span class="category-card__content">
                                    <strong><?php echo esc_html($omochix_category['name']); ?></strong>
                                    <span><?php echo esc_html($omochix_category['description']); ?></span>
                                </span>
                                <span class="category-card__count">
                                    <span class="sr-only"><?php esc_html_e('掲載数', 'omochix'); ?></span>
                                    <?php echo esc_html(number_format_i18n($omochix_category['count'])); ?><?php esc_html_e('件', 'omochix'); ?>
                                </span>
                                <span class="category-card__arrow" aria-hidden="true">→</span>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="popular-categories__empty" role="status">
                    <p><?php esc_html_e('カテゴリー情報は準備中です。', 'omochix'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php
    /**
     * Recommended reading.
     * Editorial signals use native tags/meta and gracefully fall back to the
     * newest posts. Each query is capped and skips count queries for speed.
     */
    $omochix_recommended_posts = [];
    $omochix_recommended_ids   = [];
    $omochix_recommend_rules   = [
        [
            'label' => __('AI初心者向け', 'omochix'),
            'args'  => ['tag' => 'ai-beginner'],
        ],
        [
            'label' => __('人気記事', 'omochix'),
            'args'  => ['meta_key' => 'post_views_count', 'orderby' => 'meta_value_num'],
        ],
        [
            'label' => __('編集部おすすめ', 'omochix'),
            'args'  => [
                'meta_query' => [
                    [
                        'key'     => 'is_recommended',
                        'value'   => ['1', 'true', 'yes'],
                        'compare' => 'IN',
                    ],
                ],
            ],
        ],
    ];

    foreach ($omochix_recommend_rules as $omochix_recommend_rule) {
        $omochix_rule_query = new WP_Query(array_merge([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => 1,
            'post__not_in'        => $omochix_recommended_ids,
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ], $omochix_recommend_rule['args']));

        if (!empty($omochix_rule_query->posts)) {
            $omochix_selected_post       = $omochix_rule_query->posts[0];
            $omochix_recommended_posts[] = [
                'post'  => $omochix_selected_post,
                'label' => $omochix_recommend_rule['label'],
            ];
            $omochix_recommended_ids[]            = $omochix_selected_post->ID;
        }
    }

    $omochix_recommended_slots = 3 - count($omochix_recommended_posts);
    if ($omochix_recommended_slots > 0) {
        $omochix_recommended_fallback = new WP_Query([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => $omochix_recommended_slots,
            'post__not_in'        => $omochix_recommended_ids,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ]);

        foreach ($omochix_recommended_fallback->posts as $omochix_fallback_post) {
            $omochix_recommended_posts[] = [
                'post'  => $omochix_fallback_post,
                'label' => __('おすすめ記事', 'omochix'),
            ];
        }
    }
    ?>

    <section class="recommended-reading" aria-labelledby="recommended-reading-title">
        <div class="recommended-reading__inner">
            <header class="recommended-reading__header">
                <div>
                    <p class="recommended-reading__eyebrow"><?php esc_html_e('START HERE', 'omochix'); ?></p>
                    <h2 id="recommended-reading-title"><?php esc_html_e('おすすめ記事', 'omochix'); ?></h2>
                    <p><?php esc_html_e('AIを知り、選び、使いこなすための厳選ガイド', 'omochix'); ?></p>
                </div>
                <?php
                $omochix_reading_page_id = (int) get_option('page_for_posts');
                $omochix_reading_url     = $omochix_reading_page_id ? get_permalink($omochix_reading_page_id) : add_query_arg('s', __('AI', 'omochix'), home_url('/'));
                ?>
                <a class="recommended-reading__all-link" href="<?php echo esc_url($omochix_reading_url); ?>">
                    <?php esc_html_e('記事をもっと見る', 'omochix'); ?><span aria-hidden="true">→</span>
                </a>
            </header>

            <?php if (!empty($omochix_recommended_posts)) : ?>
                <div class="recommended-reading__grid">
                    <?php foreach ($omochix_recommended_posts as $omochix_recommended_item) : ?>
                        <?php
                        $post = $omochix_recommended_item['post'];
                        setup_postdata($post);
                        $omochix_recommend_image = get_the_post_thumbnail_url(get_the_ID(), 'large');
                        $omochix_recommend_terms = get_the_category();
                        $omochix_recommend_category = !empty($omochix_recommend_terms) ? $omochix_recommend_terms[0]->name : __('AIガイド', 'omochix');
                        $omochix_recommend_excerpt  = wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 52, '…');
                        ?>
                        <article class="reading-card">
                            <a class="reading-card__link" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf(__('記事を読む：%s', 'omochix'), get_the_title())); ?>">
                                <div class="reading-card__media">
                                    <?php if ($omochix_recommend_image) : ?>
                                        <img src="<?php echo esc_url($omochix_recommend_image); ?>" width="720" height="450" alt="" loading="lazy" decoding="async">
                                    <?php else : ?>
                                        <div class="reading-card__placeholder" role="img" aria-label="<?php esc_attr_e('OmochiXおすすめ記事のアイキャッチ画像', 'omochix'); ?>">
                                            <span aria-hidden="true">O</span>
                                        </div>
                                    <?php endif; ?>
                                    <span class="reading-card__pick"><?php echo esc_html($omochix_recommended_item['label']); ?></span>
                                </div>
                                <div class="reading-card__body">
                                    <div class="reading-card__meta">
                                        <span><?php echo esc_html($omochix_recommend_category); ?></span>
                                        <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('Y.m.d')); ?></time>
                                    </div>
                                    <h3><?php echo esc_html(get_the_title()); ?></h3>
                                    <p><?php echo esc_html($omochix_recommend_excerpt); ?></p>
                                    <span class="reading-card__more"><?php esc_html_e('続きを読む', 'omochix'); ?> <span aria-hidden="true">→</span></span>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="recommended-reading__empty" role="status">
                    <p><?php esc_html_e('おすすめ記事を準備中です。', 'omochix'); ?></p>
                </div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </div>
    </section>

    <section class="newsletter" aria-labelledby="newsletter-title">
        <div class="newsletter__inner">
            <div class="newsletter__copy">
                <p class="newsletter__eyebrow"><?php esc_html_e('OMOCHIX NEWSLETTER', 'omochix'); ?></p>
                <h2 id="newsletter-title"><?php esc_html_e('AIの最新情報を見逃さない。', 'omochix'); ?></h2>
                <p><?php esc_html_e('重要なニュースと実用的なAI活用法を、読みやすく整理してお届けします。', 'omochix'); ?></p>
            </div>
            <div class="newsletter__signup" role="group" aria-labelledby="newsletter-title">
                <label class="sr-only" for="newsletter-email"><?php esc_html_e('メールアドレス', 'omochix'); ?></label>
                <input id="newsletter-email" type="email" inputmode="email" autocomplete="email" placeholder="name@example.com" aria-describedby="newsletter-note">
                <button type="button"><?php esc_html_e('登録する', 'omochix'); ?></button>
            </div>
            <p class="newsletter__note" id="newsletter-note"><?php esc_html_e('登録機能は近日公開予定です。', 'omochix'); ?></p>
        </div>
    </section>
</main>

<?php get_footer(); ?>
