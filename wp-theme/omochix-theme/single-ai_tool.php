<?php
/**
 * Single AI tool decision page.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();

        $omochix_tool_id       = get_the_ID();
        $omochix_tool_name     = get_the_title();
        $omochix_archive_url   = get_post_type_archive_link('ai_tool') ?: home_url('/ai-tools/');
        $omochix_logo_id       = absint(get_post_meta($omochix_tool_id, 'tool_logo', true));
        $omochix_official_url  = get_post_meta($omochix_tool_id, 'official_url', true);
        $omochix_company       = get_post_meta($omochix_tool_id, 'company_name', true);
        $omochix_short         = get_post_meta($omochix_tool_id, 'short_description', true);
        $omochix_short         = $omochix_short ?: get_the_excerpt();
        $omochix_rating        = (float) get_post_meta($omochix_tool_id, 'rating_overall', true);
        $omochix_pricing       = get_post_meta($omochix_tool_id, 'pricing_type', true) ?: 'contact';
        $omochix_japanese      = get_post_meta($omochix_tool_id, 'japanese_support', true) ?: 'unknown';
        $omochix_status        = get_post_meta($omochix_tool_id, 'tool_status', true) ?: 'active';
        $omochix_free_plan     = get_post_meta($omochix_tool_id, 'has_free_plan', true);
        $omochix_has_free_meta = metadata_exists('post', $omochix_tool_id, 'has_free_plan');
        $omochix_api           = get_post_meta($omochix_tool_id, 'api_available', true) ?: 'unknown';
        $omochix_commercial    = get_post_meta($omochix_tool_id, 'commercial_use', true) ?: 'unknown';
        $omochix_feature_image = get_the_post_thumbnail_url($omochix_tool_id, 'large');
        $omochix_info_checked  = get_post_meta($omochix_tool_id, 'info_checked_date', true);

        $omochix_supported_devices = get_post_meta($omochix_tool_id, 'supported_devices', true);
        $omochix_supported_devices = is_array($omochix_supported_devices) ? array_filter($omochix_supported_devices) : [];
        $omochix_supported_models  = get_post_meta($omochix_tool_id, 'supported_models', true);
        $omochix_supported_models  = is_array($omochix_supported_models) ? array_filter($omochix_supported_models) : [];

        // v2 detail fields. Every one is optional; the section that reads it
        // below only renders when it is non-empty.
        $omochix_notes           = get_post_meta($omochix_tool_id, 'notes', true);
        $omochix_pricing_details = get_post_meta($omochix_tool_id, 'pricing_details', true);
        $omochix_integrations    = get_post_meta($omochix_tool_id, 'integrations', true);
        $omochix_integrations    = is_array($omochix_integrations) ? array_filter($omochix_integrations) : [];
        $omochix_api_sdk_info    = get_post_meta($omochix_tool_id, 'api_sdk_info', true);
        $omochix_security_info   = get_post_meta($omochix_tool_id, 'security_info', true);
        $omochix_view            = get_post_meta($omochix_tool_id, 'omochix_view', true);
        $omochix_has_overview    = '' !== trim(wp_strip_all_tags(get_the_content()));

        $omochix_product_updates = function_exists('omochix_core_get_product_updates')
            ? omochix_core_get_product_updates($omochix_tool_id, 6)
            : [];
        $omochix_changelog = function_exists('omochix_core_get_changelog_entries')
            ? omochix_core_get_changelog_entries($omochix_tool_id)
            : [];
        $omochix_update_status_labels = function_exists('omochix_core_get_update_status_labels')
            ? omochix_core_get_update_status_labels()
            : [];

        $omochix_related_learn = function_exists('omochix_core_get_related_content')
            ? omochix_core_get_related_content($omochix_tool_id, 'related_learn_ids', 'page')
            : [];
        $omochix_related_news = function_exists('omochix_core_get_related_content')
            ? omochix_core_get_related_content($omochix_tool_id, 'related_news_ids', 'post', ['orderby' => 'date', 'order' => 'DESC'])
            : [];
        $omochix_related_lab = function_exists('omochix_core_get_related_content')
            ? omochix_core_get_related_content($omochix_tool_id, 'related_lab_ids', 'post', ['orderby' => 'date', 'order' => 'DESC'])
            : [];
        $omochix_related_compare = function_exists('omochix_core_get_related_content')
            ? omochix_core_get_related_content($omochix_tool_id, 'related_compare_ids', 'post', ['orderby' => 'date', 'order' => 'DESC'])
            : [];
        // Reverse of each prompt's related_tool_ids; empty until prompts are linked.
        $omochix_related_prompts = function_exists('omochix_core_get_tool_related_prompts')
            ? omochix_core_get_tool_related_prompts($omochix_tool_id, 6)
            : [];

        $omochix_categories = get_the_terms($omochix_tool_id, 'ai_tool_category');
        $omochix_features   = get_the_terms($omochix_tool_id, 'ai_tool_feature');
        $omochix_tags       = get_the_terms($omochix_tool_id, 'ai_tool_tag');
        $omochix_platforms  = get_the_terms($omochix_tool_id, 'ai_tool_platform');
        $omochix_categories = is_array($omochix_categories) ? $omochix_categories : [];
        $omochix_features   = is_array($omochix_features) ? $omochix_features : [];
        $omochix_tags       = is_array($omochix_tags) ? $omochix_tags : [];
        $omochix_platforms  = is_array($omochix_platforms) ? $omochix_platforms : [];

        $omochix_pricing_labels = [
            'free' => __('無料', 'omochix'), 'freemium' => __('無料プランあり', 'omochix'),
            'paid' => __('有料', 'omochix'), 'trial' => __('無料体験あり', 'omochix'),
            'contact' => __('要問い合わせ', 'omochix'),
        ];
        $omochix_japanese_labels = [
            'full' => __('日本語対応', 'omochix'), 'partial' => __('一部日本語対応', 'omochix'),
            'none' => __('日本語非対応', 'omochix'), 'unknown' => __('未確認', 'omochix'),
        ];
        $omochix_status_labels = [
            'active' => __('提供中', 'omochix'), 'beta' => __('ベータ版', 'omochix'),
            'waitlist' => __('招待・待機中', 'omochix'), 'discontinued' => __('提供終了', 'omochix'),
        ];
        $omochix_pricing_label = function_exists('omochix_core_get_pricing_type_label')
            ? omochix_core_get_pricing_type_label($omochix_pricing)
            : ($omochix_pricing_labels[$omochix_pricing] ?? __('未確認', 'omochix'));
        $omochix_japanese_label = function_exists('omochix_core_get_japanese_support_label')
            ? omochix_core_get_japanese_support_label($omochix_japanese)
            : ($omochix_japanese_labels[$omochix_japanese] ?? __('未確認', 'omochix'));
        $omochix_status_label = $omochix_status_labels[$omochix_status] ?? __('未確認', 'omochix');

        $omochix_support_labels = [
            'yes' => __('対応', 'omochix'), 'partial' => __('一部対応', 'omochix'),
            'no' => __('非対応', 'omochix'), 'unknown' => __('未確認', 'omochix'),
        ];
        $omochix_get_support_label = function_exists('omochix_core_get_support_level_label')
            ? 'omochix_core_get_support_level_label'
            : static function ($value) use ($omochix_support_labels) {
                return $omochix_support_labels[$value] ?? __('未確認', 'omochix');
            };
        $omochix_api_label        = call_user_func($omochix_get_support_label, $omochix_api);
        $omochix_commercial_label = call_user_func($omochix_get_support_label, $omochix_commercial);
        $omochix_free_plan_label = !$omochix_has_free_meta
            ? __('未確認', 'omochix')
            : (in_array($omochix_free_plan, [true, 1, '1', 'true', 'yes', 'on'], true) ? __('あり', 'omochix') : __('なし', 'omochix'));
        $omochix_platform_label = $omochix_platforms
            ? implode('、', wp_list_pluck($omochix_platforms, 'name'))
            : __('未確認', 'omochix');

        $omochix_structured_sections = [
            'key_features'          => ['title' => __('主な特徴', 'omochix'), 'class' => 'features'],
            'strengths'             => ['title' => __('得意なこと', 'omochix'), 'class' => 'features'],
            'pros'                  => ['title' => __('メリット', 'omochix'), 'class' => 'pros'],
            'weaknesses'            => ['title' => __('苦手なこと・制限', 'omochix'), 'class' => 'cons'],
            'cons'                  => ['title' => __('デメリット', 'omochix'), 'class' => 'cons'],
            'recommended_use_cases' => ['title' => __('おすすめ用途', 'omochix'), 'class' => 'recommended'],
            'recommended_for'       => ['title' => __('向いている人', 'omochix'), 'class' => 'recommended'],
            'not_recommended_for'   => ['title' => __('向いていない人', 'omochix'), 'class' => 'cons'],
        ];
        foreach ($omochix_structured_sections as $omochix_key => &$omochix_section) {
            $omochix_items = get_post_meta($omochix_tool_id, $omochix_key, true);
            $omochix_section['items'] = is_array($omochix_items) ? array_filter(array_map('sanitize_text_field', $omochix_items)) : [];
        }
        unset($omochix_section);

        // Collect related tools in editorial priority order without duplicates.
        $omochix_related_tool_ids = [];
        $omochix_relation_sources = [
            'ai_tool_category' => wp_list_pluck($omochix_categories, 'term_id'),
            'ai_tool_feature'  => wp_list_pluck($omochix_features, 'term_id'),
            'ai_tool_tag'      => wp_list_pluck($omochix_tags, 'term_id'),
        ];
        foreach ($omochix_relation_sources as $omochix_taxonomy => $omochix_term_ids) {
            if (!$omochix_term_ids || count($omochix_related_tool_ids) >= 3) {
                continue;
            }
            $omochix_relation_query = new WP_Query([
                'post_type'      => 'ai_tool',
                'post_status'    => 'publish',
                'posts_per_page' => 3 - count($omochix_related_tool_ids),
                'post__not_in'   => array_merge([$omochix_tool_id], $omochix_related_tool_ids),
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'tax_query'      => [[
                    'taxonomy' => $omochix_taxonomy,
                    'field'    => 'term_id',
                    'terms'    => array_map('absint', $omochix_term_ids),
                ]],
            ]);
            $omochix_related_tool_ids = array_values(array_unique(array_merge($omochix_related_tool_ids, $omochix_relation_query->posts)));
        }
        if (count($omochix_related_tool_ids) < 3) {
            $omochix_latest_tools = new WP_Query([
                'post_type'      => 'ai_tool',
                'post_status'    => 'publish',
                'posts_per_page' => 3 - count($omochix_related_tool_ids),
                'post__not_in'   => array_merge([$omochix_tool_id], $omochix_related_tool_ids),
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            $omochix_related_tool_ids = array_values(array_unique(array_merge($omochix_related_tool_ids, $omochix_latest_tools->posts)));
        }
        $omochix_related_tools = $omochix_related_tool_ids ? new WP_Query([
            'post_type'      => 'ai_tool',
            'post_status'    => 'publish',
            'posts_per_page' => 3,
            'post__in'       => $omochix_related_tool_ids,
            'orderby'        => 'post__in',
            'no_found_rows'  => true,
        ]) : null;

        // Related posts: tool-name search first, then matching category and tag slugs.
        // Product Hub's manually curated "最新ニュース" (related_news_ids) already
        // covers the tool's most relevant news; excluding those IDs here keeps
        // this auto section from repeating the same articles Product Hub just
        // showed, per the v2.1 "no duplicate news lists" requirement.
        $omochix_hub_news_ids = wp_list_pluck($omochix_related_news, 'ID');
        $omochix_related_post_ids = [];
        if ($omochix_tool_name) {
            $omochix_name_posts = new WP_Query([
                'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 3,
                's' => $omochix_tool_name, 'fields' => 'ids', 'no_found_rows' => true,
                'post__not_in' => $omochix_hub_news_ids,
            ]);
            $omochix_related_post_ids = $omochix_name_posts->posts;
        }
        $omochix_post_tax_sources = [
            'category' => wp_list_pluck($omochix_categories, 'slug'),
            'post_tag' => wp_list_pluck($omochix_tags, 'slug'),
        ];
        foreach ($omochix_post_tax_sources as $omochix_taxonomy => $omochix_slugs) {
            if (!$omochix_slugs || count($omochix_related_post_ids) >= 3) {
                continue;
            }
            $omochix_post_relation_query = new WP_Query([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 3 - count($omochix_related_post_ids),
                'post__not_in'   => array_merge($omochix_related_post_ids, $omochix_hub_news_ids),
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'tax_query'      => [[
                    'taxonomy' => $omochix_taxonomy,
                    'field'    => 'slug',
                    'terms'    => array_map('sanitize_title', $omochix_slugs),
                ]],
            ]);
            $omochix_related_post_ids = array_values(array_unique(array_merge($omochix_related_post_ids, $omochix_post_relation_query->posts)));
        }
        $omochix_related_posts = $omochix_related_post_ids ? new WP_Query([
            'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 3,
            'post__in' => $omochix_related_post_ids, 'orderby' => 'post__in', 'no_found_rows' => true,
        ]) : null;
        ?>

        <main class="tool-detail" id="main-content">
            <article class="tool-detail__article">
                <header class="tool-detail__hero">
                    <div class="tool-detail__container">
                        <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                            <ol>
                                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                                <li><a href="<?php echo esc_url($omochix_archive_url); ?>"><?php esc_html_e('AIツール', 'omochix'); ?></a></li>
                                <?php if ($omochix_categories) : ?>
                                    <li><a href="<?php echo esc_url(get_term_link($omochix_categories[0])); ?>"><?php echo esc_html($omochix_categories[0]->name); ?></a></li>
                                <?php endif; ?>
                                <li aria-current="page"><?php echo esc_html($omochix_tool_name); ?></li>
                            </ol>
                        </nav>

                        <div class="tool-detail__hero-grid">
                            <div class="tool-detail__hero-copy">
                                <div class="tool-detail__identity">
                                    <div class="tool-detail__logo">
                                        <?php if ($omochix_logo_id) : ?>
                                            <?php echo wp_kses_post(wp_get_attachment_image($omochix_logo_id, 'thumbnail', false, ['alt' => '', 'width' => 96, 'height' => 96])); ?>
                                        <?php else : ?>
                                            <span aria-hidden="true"><?php echo esc_html(function_exists('mb_substr') ? mb_substr($omochix_tool_name, 0, 1) : substr($omochix_tool_name, 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p><?php echo esc_html($omochix_company ?: __('運営会社情報なし', 'omochix')); ?></p>
                                        <h1><?php the_title(); ?></h1>
                                    </div>
                                </div>
                                <?php if ($omochix_short) : ?><p class="tool-detail__lead"><?php echo esc_html(wp_strip_all_tags($omochix_short)); ?></p><?php endif; ?>
                                <dl class="tool-detail__hero-facts">
                                    <div><dt><?php esc_html_e('編集部評価', 'omochix'); ?></dt><dd><?php echo $omochix_rating > 0 ? esc_html(number_format_i18n($omochix_rating, 1) . ' / 5.0') : esc_html__('未評価', 'omochix'); ?></dd></div>
                                    <div><dt><?php esc_html_e('料金', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_pricing_label); ?></dd></div>
                                    <div><dt><?php esc_html_e('日本語', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_japanese_label); ?></dd></div>
                                    <div><dt><?php esc_html_e('提供状況', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_status_label); ?></dd></div>
                                </dl>
                                <?php if ($omochix_categories || $omochix_platforms) : ?>
                                    <div class="tool-detail__taxonomy">
                                        <?php foreach (array_slice($omochix_categories, 0, 2) as $omochix_term) : ?><span><?php echo esc_html($omochix_term->name); ?></span><?php endforeach; ?>
                                        <?php foreach (array_slice($omochix_platforms, 0, 3) as $omochix_term) : ?><span><?php echo esc_html($omochix_term->name); ?></span><?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <p class="tool-detail__updated">
                                    <?php esc_html_e('最終更新', 'omochix'); ?> <time datetime="<?php echo esc_attr(get_the_modified_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_modified_date('Y.m.d')); ?></time> · <?php echo esc_html(sprintf(__('編集：%s', 'omochix'), get_the_author())); ?>
                                    <?php if ($omochix_info_checked) : ?>
                                        · <?php esc_html_e('情報確認日', 'omochix'); ?> <time datetime="<?php echo esc_attr($omochix_info_checked); ?>"><?php echo esc_html(mysql2date('Y.m.d', $omochix_info_checked)); ?></time>
                                    <?php endif; ?>
                                </p>
                                <?php if ($omochix_official_url) : ?>
                                    <a class="tool-detail__official" href="<?php echo esc_url($omochix_official_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('公式サイトで確認する', 'omochix'); ?><span aria-hidden="true">↗</span><span class="sr-only"><?php esc_html_e('（外部サイトを新しいタブで開きます）', 'omochix'); ?></span></a>
                                <?php endif; ?>
                            </div>

                            <figure class="tool-detail__visual">
                                <div class="tool-detail__media">
                                    <?php if ($omochix_feature_image) : ?>
                                        <img src="<?php echo esc_url($omochix_feature_image); ?>" width="1200" height="675" alt="<?php echo esc_attr(sprintf(__('%sのイメージ', 'omochix'), $omochix_tool_name)); ?>" decoding="async" fetchpriority="high">
                                    <?php else : ?>
                                        <div class="tool-detail__placeholder" role="img" aria-label="<?php echo esc_attr(sprintf(__('%sのプレースホルダー画像', 'omochix'), $omochix_tool_name)); ?>"><span aria-hidden="true">OmochiX</span></div>
                                    <?php endif; ?>
                                </div>
                                <figcaption><?php echo esc_html(sprintf(__('%sの概要情報', 'omochix'), $omochix_tool_name)); ?></figcaption>
                            </figure>
                        </div>
                    </div>
                </header>

                <nav class="tool-detail__tabs" aria-label="<?php esc_attr_e('AIツール詳細のセクション', 'omochix'); ?>">
                    <div class="tool-detail__container">
                        <?php if ($omochix_has_overview) : ?><a href="#tool-overview" data-tool-nav-link><?php esc_html_e('概要', 'omochix'); ?></a><?php endif; ?>
                        <?php if (array_filter(wp_list_pluck($omochix_structured_sections, 'items'))) : ?><a href="#tool-features" data-tool-nav-link><?php esc_html_e('特徴', 'omochix'); ?></a><?php endif; ?>
                        <a href="#tool-rating" data-tool-nav-link><?php esc_html_e('評価', 'omochix'); ?></a>
                        <?php if ($omochix_product_updates) : ?><a href="#tool-updates" data-tool-nav-link><?php esc_html_e('アップデート', 'omochix'); ?></a><?php endif; ?>
                        <?php if ($omochix_related_learn || $omochix_related_news || $omochix_related_lab || $omochix_related_compare || $omochix_related_prompts) : ?><a href="#tool-hub" data-tool-nav-link><?php esc_html_e('関連コンテンツ', 'omochix'); ?></a><?php endif; ?>
                        <?php if ($omochix_related_tools && $omochix_related_tools->have_posts()) : ?><a href="#related-tools-title" data-tool-nav-link><?php esc_html_e('関連ツール', 'omochix'); ?></a><?php endif; ?>
                    </div>
                </nav>

                <div class="tool-detail__container tool-detail__layout">
                    <div class="tool-detail__main">
                        <section class="tool-summary tool-summary--mobile" aria-labelledby="tool-summary-mobile-title">
                            <h2 id="tool-summary-mobile-title"><?php esc_html_e('クイックサマリー', 'omochix'); ?></h2>
                            <?php include __DIR__ . '/template-parts/tool-quick-summary.php'; ?>
                        </section>

                        <?php if ($omochix_has_overview) : ?>
                            <section class="tool-content article-content" id="tool-overview" aria-label="<?php esc_attr_e('AIツールの詳細', 'omochix'); ?>">
                                <?php the_content(); ?>
                            </section>
                        <?php endif; ?>

                        <?php if (array_filter(wp_list_pluck($omochix_structured_sections, 'items'))) : ?>
                            <section class="tool-structured" id="tool-features" aria-labelledby="tool-structured-title">
                                <header><p><?php esc_html_e('AT A GLANCE', 'omochix'); ?></p><h2 id="tool-structured-title"><?php esc_html_e('選ぶ前に知っておきたいこと', 'omochix'); ?></h2></header>
                                <div class="tool-structured__grid">
                                    <?php foreach ($omochix_structured_sections as $omochix_section) : ?>
                                        <?php if ($omochix_section['items']) : ?>
                                            <section class="tool-structured__panel tool-structured__panel--<?php echo esc_attr($omochix_section['class']); ?>">
                                                <h3><?php echo esc_html($omochix_section['title']); ?></h3>
                                                <ul><?php foreach ($omochix_section['items'] as $omochix_item) : ?><li><?php echo esc_html($omochix_item); ?></li><?php endforeach; ?></ul>
                                            </section>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php
                        $omochix_detail_items = [
                            'pricing_details' => ['title' => __('料金詳細', 'omochix'), 'text' => $omochix_pricing_details],
                            'api_sdk_info'     => ['title' => __('API / SDK / 開発者向け情報', 'omochix'), 'text' => $omochix_api_sdk_info],
                            'security_info'    => ['title' => __('セキュリティ / データ利用', 'omochix'), 'text' => $omochix_security_info],
                            'notes'            => ['title' => __('注意点', 'omochix'), 'text' => $omochix_notes],
                        ];
                        $omochix_has_detail_text = array_filter(wp_list_pluck($omochix_detail_items, 'text'));
                        $omochix_has_detail_section = $omochix_has_detail_text || $omochix_integrations || $omochix_supported_devices || $omochix_supported_models;
                        ?>
                        <?php if ($omochix_has_detail_section) : ?>
                            <section class="tool-detail-info" id="tool-details" aria-labelledby="tool-detail-info-title">
                                <header><p><?php esc_html_e('MORE DETAILS', 'omochix'); ?></p><h2 id="tool-detail-info-title"><?php esc_html_e('詳細情報', 'omochix'); ?></h2></header>
                                <div class="tool-detail-info__grid">
                                    <?php if ($omochix_supported_models) : ?>
                                        <div class="tool-detail-info__block"><h3><?php esc_html_e('対応モデル', 'omochix'); ?></h3><ul><?php foreach ($omochix_supported_models as $omochix_model) : ?><li><?php echo esc_html($omochix_model); ?></li><?php endforeach; ?></ul></div>
                                    <?php endif; ?>
                                    <?php if ($omochix_supported_devices) : ?>
                                        <div class="tool-detail-info__block"><h3><?php esc_html_e('対応デバイス', 'omochix'); ?></h3><ul><?php foreach ($omochix_supported_devices as $omochix_device) : ?><li><?php echo esc_html($omochix_device); ?></li><?php endforeach; ?></ul></div>
                                    <?php endif; ?>
                                    <?php if ($omochix_integrations) : ?>
                                        <div class="tool-detail-info__block"><h3><?php esc_html_e('連携サービス', 'omochix'); ?></h3><ul><?php foreach ($omochix_integrations as $omochix_integration) : ?><li><?php echo esc_html($omochix_integration); ?></li><?php endforeach; ?></ul></div>
                                    <?php endif; ?>
                                    <?php foreach ($omochix_detail_items as $omochix_detail) : ?>
                                        <?php if ($omochix_detail['text']) : ?>
                                            <div class="tool-detail-info__block"><h3><?php echo esc_html($omochix_detail['title']); ?></h3><p><?php echo esc_html($omochix_detail['text']); ?></p></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <section class="tool-editor-rating" id="tool-rating" aria-labelledby="tool-rating-title">
                            <div><p><?php esc_html_e('OMOCHIX EDITORIAL RATING', 'omochix'); ?></p><h2 id="tool-rating-title"><?php esc_html_e('OmochiX編集部評価', 'omochix'); ?></h2></div>
                            <?php if ($omochix_rating > 0) : ?>
                                <p class="tool-editor-rating__score" aria-label="<?php echo esc_attr(sprintf(__('OmochiX編集部評価、5点満点中%s', 'omochix'), number_format_i18n($omochix_rating, 1))); ?>"><strong><?php echo esc_html(number_format_i18n($omochix_rating, 1)); ?></strong><span>/ 5.0</span></p>
                            <?php else : ?>
                                <p class="tool-editor-rating__empty"><?php esc_html_e('未評価', 'omochix'); ?></p>
                            <?php endif; ?>
                        </section>

                        <?php if ($omochix_product_updates) : ?>
                            <section class="tool-updates" id="tool-updates" aria-labelledby="tool-updates-title">
                                <header><p><?php esc_html_e('LATEST UPDATES', 'omochix'); ?></p><h2 id="tool-updates-title"><?php echo esc_html(sprintf(__('%sの最新アップデート', 'omochix'), $omochix_tool_name)); ?></h2></header>
                                <ul class="tool-updates__list">
                                    <?php foreach ($omochix_product_updates as $omochix_update) : ?>
                                        <li class="tool-updates__item">
                                            <div class="tool-updates__meta">
                                                <?php if ($omochix_update['date']) : ?><time datetime="<?php echo esc_attr($omochix_update['date']); ?>"><?php echo esc_html(mysql2date('Y.m.d', $omochix_update['date'])); ?></time><?php endif; ?>
                                                <?php if (!empty($omochix_update_status_labels[$omochix_update['status']])) : ?><span class="tool-updates__status tool-updates__status--<?php echo esc_attr($omochix_update['status']); ?>"><?php echo esc_html($omochix_update_status_labels[$omochix_update['status']]); ?></span><?php endif; ?>
                                            </div>
                                            <h3><?php echo esc_html($omochix_update['title']); ?></h3>
                                            <?php if ($omochix_update['description']) : ?><p><?php echo esc_html($omochix_update['description']); ?></p><?php endif; ?>
                                            <?php if ($omochix_update['news_url']) : ?><a class="tool-updates__link" href="<?php echo esc_url($omochix_update['news_url']); ?>"><?php esc_html_e('詳細Newsを見る', 'omochix'); ?><span aria-hidden="true">→</span></a><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <?php if ($omochix_changelog) : ?>
                            <?php $omochix_changelog_visible = array_slice($omochix_changelog, 0, 5); ?>
                            <?php $omochix_changelog_rest = array_slice($omochix_changelog, 5); ?>
                            <section class="tool-changelog" id="tool-changelog" aria-labelledby="tool-changelog-title">
                                <header><p><?php esc_html_e('CHANGELOG', 'omochix'); ?></p><h2 id="tool-changelog-title"><?php esc_html_e('更新履歴', 'omochix'); ?></h2></header>
                                <ol class="tool-changelog__list">
                                    <?php foreach ($omochix_changelog_visible as $omochix_entry) : ?>
                                        <li>
                                            <?php if ($omochix_entry['date']) : ?><time datetime="<?php echo esc_attr($omochix_entry['date']); ?>"><?php echo esc_html(mysql2date('Y.m.d', $omochix_entry['date'])); ?></time><?php endif; ?>
                                            <span><?php echo esc_html($omochix_entry['title']); ?></span>
                                            <?php if ($omochix_entry['news_url']) : ?><a href="<?php echo esc_url($omochix_entry['news_url']); ?>"><?php esc_html_e('詳細ニュース', 'omochix'); ?><span aria-hidden="true">→</span></a><?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                                <?php if ($omochix_changelog_rest) : ?>
                                    <details class="tool-changelog__more">
                                        <summary><?php echo esc_html(sprintf(__('さらに%d件を表示', 'omochix'), count($omochix_changelog_rest))); ?></summary>
                                        <ol class="tool-changelog__list">
                                            <?php foreach ($omochix_changelog_rest as $omochix_entry) : ?>
                                                <li>
                                                    <?php if ($omochix_entry['date']) : ?><time datetime="<?php echo esc_attr($omochix_entry['date']); ?>"><?php echo esc_html(mysql2date('Y.m.d', $omochix_entry['date'])); ?></time><?php endif; ?>
                                                    <span><?php echo esc_html($omochix_entry['title']); ?></span>
                                                    <?php if ($omochix_entry['news_url']) : ?><a href="<?php echo esc_url($omochix_entry['news_url']); ?>"><?php esc_html_e('詳細ニュース', 'omochix'); ?><span aria-hidden="true">→</span></a><?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </details>
                                <?php endif; ?>
                            </section>
                        <?php endif; ?>

                        <?php if ($omochix_view) : ?>
                            <section class="tool-view" id="tool-view" aria-labelledby="tool-view-title">
                                <p class="tool-view__label"><?php esc_html_e('OMOCHIX VIEW', 'omochix'); ?></p>
                                <h2 id="tool-view-title" class="sr-only"><?php esc_html_e('OmochiX編集部の見解', 'omochix'); ?></h2>
                                <div class="tool-view__body"><?php echo wp_kses_post(wpautop($omochix_view)); ?></div>
                                <p class="tool-view__disclaimer"><?php esc_html_e('※ 公式情報ではなく、OmochiX編集部独自の見解です。', 'omochix'); ?></p>
                            </section>
                        <?php endif; ?>
                    </div>

                    <aside class="tool-detail__sidebar" aria-label="<?php esc_attr_e('ツール選びの補助情報', 'omochix'); ?>">
                        <?php if ($omochix_categories) : ?>
                            <section class="sidebar-panel" aria-labelledby="same-category-title">
                                <h2 id="same-category-title"><?php esc_html_e('同じカテゴリー', 'omochix'); ?></h2>
                                <ul class="sidebar-links"><?php foreach ($omochix_categories as $omochix_term) : ?><li><a href="<?php echo esc_url(get_term_link($omochix_term)); ?>"><span><?php echo esc_html($omochix_term->name); ?></span><small><?php echo esc_html(number_format_i18n($omochix_term->count)); ?></small></a></li><?php endforeach; ?></ul>
                            </section>
                        <?php endif; ?>
                        <section class="sidebar-newsletter" aria-labelledby="tool-detail-newsletter-title">
                            <p><?php esc_html_e('OMOCHIX NEWSLETTER', 'omochix'); ?></p>
                            <h2 id="tool-detail-newsletter-title"><?php esc_html_e('AIの最新情報を見逃さない。', 'omochix'); ?></h2>
                            <a href="<?php echo esc_url(home_url('/#newsletter-title')); ?>"><?php esc_html_e('Newsletterを見る', 'omochix'); ?><span aria-hidden="true">→</span></a>
                        </section>
                    </aside>
                </div>

                <?php if ($omochix_related_learn || $omochix_related_news || $omochix_related_lab || $omochix_related_compare || $omochix_related_prompts) : ?>
                    <section class="tool-hub" id="tool-hub" aria-labelledby="tool-hub-title">
                        <div class="tool-detail__container">
                            <header class="tool-detail-section-header"><p><?php esc_html_e('PRODUCT HUB', 'omochix'); ?></p><h2 id="tool-hub-title"><?php echo esc_html(sprintf(__('%sをもっと知る', 'omochix'), $omochix_tool_name)); ?></h2></header>

                            <?php if ($omochix_related_learn) : ?>
                                <div class="tool-hub__group">
                                    <h3><?php esc_html_e('このツールを理解する', 'omochix'); ?></h3>
                                    <div class="tool-hub__grid">
                                        <?php foreach ($omochix_related_learn as $omochix_hub_post) : ?>
                                            <a class="tool-hub-card" href="<?php echo esc_url(get_permalink($omochix_hub_post)); ?>">
                                                <strong><?php echo esc_html(get_the_title($omochix_hub_post)); ?></strong>
                                                <span><?php echo esc_html(wp_trim_words(wp_strip_all_tags($omochix_hub_post->post_excerpt ?: $omochix_hub_post->post_content), 24, '…')); ?></span>
                                                <span class="tool-hub-card__arrow" aria-hidden="true">→</span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($omochix_related_news) : ?>
                                <div class="tool-hub__group">
                                    <h3><?php esc_html_e('最新ニュース', 'omochix'); ?></h3>
                                    <div class="tool-hub__grid">
                                        <?php foreach ($omochix_related_news as $omochix_hub_post) : ?>
                                            <a class="tool-hub-card" href="<?php echo esc_url(get_permalink($omochix_hub_post)); ?>">
                                                <strong><?php echo esc_html(get_the_title($omochix_hub_post)); ?></strong>
                                                <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C, $omochix_hub_post)); ?>"><?php echo esc_html(get_the_date('Y.m.d', $omochix_hub_post)); ?></time>
                                                <span class="tool-hub-card__arrow" aria-hidden="true">→</span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($omochix_related_lab) : ?>
                                <div class="tool-hub__group">
                                    <h3><?php esc_html_e('実践・使い方', 'omochix'); ?></h3>
                                    <div class="tool-hub__grid">
                                        <?php foreach ($omochix_related_lab as $omochix_hub_post) : ?>
                                            <a class="tool-hub-card" href="<?php echo esc_url(get_permalink($omochix_hub_post)); ?>">
                                                <strong><?php echo esc_html(get_the_title($omochix_hub_post)); ?></strong>
                                                <span class="tool-hub-card__arrow" aria-hidden="true">→</span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($omochix_related_compare) : ?>
                                <div class="tool-hub__group">
                                    <h3><?php esc_html_e('比較する', 'omochix'); ?></h3>
                                    <div class="tool-hub__grid">
                                        <?php foreach ($omochix_related_compare as $omochix_hub_post) : ?>
                                            <a class="tool-hub-card" href="<?php echo esc_url(get_permalink($omochix_hub_post)); ?>">
                                                <strong><?php echo esc_html(get_the_title($omochix_hub_post)); ?></strong>
                                                <span class="tool-hub-card__arrow" aria-hidden="true">→</span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($omochix_related_prompts) : ?>
                                <div class="tool-hub__group">
                                    <h3><?php esc_html_e('このツールで使えるプロンプト', 'omochix'); ?></h3>
                                    <div class="tool-hub__grid">
                                        <?php foreach ($omochix_related_prompts as $omochix_hub_post) : ?>
                                            <a class="tool-hub-card" href="<?php echo esc_url(get_permalink($omochix_hub_post)); ?>">
                                                <strong><?php echo esc_html(get_the_title($omochix_hub_post)); ?></strong>
                                                <?php if ($omochix_hub_post->post_excerpt) : ?><span><?php echo esc_html(wp_trim_words(wp_strip_all_tags($omochix_hub_post->post_excerpt), 24, '…')); ?></span><?php endif; ?>
                                                <span class="tool-hub-card__arrow" aria-hidden="true">→</span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($omochix_related_tools && $omochix_related_tools->have_posts()) : ?>
                    <section class="tool-detail-related" aria-labelledby="related-tools-title">
                        <div class="tool-detail__container">
                            <header class="tool-detail-section-header"><p><?php esc_html_e('MORE TOOLS', 'omochix'); ?></p><h2 id="related-tools-title"><?php esc_html_e('関連AIツール', 'omochix'); ?></h2></header>
                            <div class="tool-results__grid">
                                <?php while ($omochix_related_tools->have_posts()) : $omochix_related_tools->the_post();
                                    $omochix_related_id = get_the_ID();
                                    $omochix_related_logo = absint(get_post_meta($omochix_related_id, 'tool_logo', true));
                                    $omochix_related_description = get_post_meta($omochix_related_id, 'short_description', true) ?: get_the_excerpt();
                                    $omochix_related_rating = (float) get_post_meta($omochix_related_id, 'rating_overall', true);
                                    ?>
                                    <article class="tool-list-card"><a class="tool-list-card__link" href="<?php the_permalink(); ?>">
                                        <div class="tool-list-card__top"><div class="tool-list-card__logo"><?php if ($omochix_related_logo) { echo wp_kses_post(wp_get_attachment_image($omochix_related_logo, 'thumbnail', false, ['alt' => '', 'width' => 64, 'height' => 64, 'loading' => 'lazy'])); } else { ?><span aria-hidden="true"><?php echo esc_html(function_exists('mb_substr') ? mb_substr(get_the_title(), 0, 1) : substr(get_the_title(), 0, 1)); ?></span><?php } ?></div><?php if ($omochix_related_rating > 0) : ?><p class="tool-list-card__rating" aria-label="<?php echo esc_attr(sprintf(__('OmochiX編集部評価、5点満点中%s', 'omochix'), number_format_i18n($omochix_related_rating, 1))); ?>"><span aria-hidden="true">★</span><strong><?php echo esc_html(number_format_i18n($omochix_related_rating, 1)); ?></strong><small>/ 5</small></p><?php endif; ?></div>
                                        <div class="tool-list-card__identity"><h3><?php the_title(); ?></h3><p><?php echo esc_html(get_post_meta($omochix_related_id, 'company_name', true) ?: __('運営会社情報なし', 'omochix')); ?></p></div>
                                        <?php if ($omochix_related_description) : ?><p class="tool-list-card__description"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($omochix_related_description), 36, '…')); ?></p><?php endif; ?>
                                        <span class="tool-list-card__more"><?php esc_html_e('詳細を見る', 'omochix'); ?><span aria-hidden="true">→</span></span>
                                    </a></article>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>

                <?php if ($omochix_related_posts && $omochix_related_posts->have_posts()) : ?>
                    <section class="related-articles tool-related-articles" aria-labelledby="tool-related-posts-title">
                        <div class="article-container">
                            <header class="related-articles__header"><h2 id="tool-related-posts-title"><?php esc_html_e('関連記事', 'omochix'); ?></h2><p><?php echo esc_html(sprintf(__('%sをもっと理解するための記事', 'omochix'), $omochix_tool_name)); ?></p></header>
                            <div class="related-articles__grid">
                                <?php while ($omochix_related_posts->have_posts()) : $omochix_related_posts->the_post();
                                    $omochix_article_image = get_the_post_thumbnail_url(get_the_ID(), 'large');
                                    $omochix_article_categories = get_the_category(); ?>
                                    <article class="related-card"><a href="<?php the_permalink(); ?>">
                                        <div class="related-card__media"><?php if ($omochix_article_image) : ?><img src="<?php echo esc_url($omochix_article_image); ?>" width="640" height="360" alt="" loading="lazy" decoding="async"><?php else : ?><div aria-hidden="true">O</div><?php endif; ?></div>
                                        <div class="related-card__meta"><span><?php echo esc_html($omochix_article_categories ? $omochix_article_categories[0]->name : __('AIニュース', 'omochix')); ?></span><time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('Y.m.d')); ?></time></div>
                                        <h3><?php the_title(); ?></h3>
                                    </a></article>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>

                <footer class="tool-detail-cta">
                    <div class="tool-detail__container tool-detail-cta__inner">
                        <div><p><?php esc_html_e('NEXT STEP', 'omochix'); ?></p><h2><?php esc_html_e('自分に合うAIツールを選ぶ。', 'omochix'); ?></h2></div>
                        <div class="tool-detail-cta__links">
                            <?php if ($omochix_official_url) : ?><a class="tool-detail-cta__primary" href="<?php echo esc_url($omochix_official_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('公式サイトで確認する', 'omochix'); ?><span aria-hidden="true">↗</span><span class="sr-only"><?php esc_html_e('（外部サイトを新しいタブで開きます）', 'omochix'); ?></span></a><?php endif; ?>
                            <a href="<?php echo esc_url($omochix_archive_url); ?>"><?php esc_html_e('他のAIツールを探す', 'omochix'); ?></a>
                            <a href="<?php echo esc_url(get_option('page_for_posts') ? get_permalink((int) get_option('page_for_posts')) : home_url('/')); ?>"><?php esc_html_e('最新のAIニュースを見る', 'omochix'); ?></a>
                        </div>
                    </div>
                </footer>
            </article>
        </main>
        <?php
    endwhile;
endif;

get_footer();
