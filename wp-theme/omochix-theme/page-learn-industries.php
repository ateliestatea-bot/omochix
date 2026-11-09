<?php
/**
 * Industry Hub index: /learn/industries/ (the fixed page, D-1).
 *
 * Served by the template_include filter in inc/learn-industries.php. No
 * "Template Name" header on purpose: a page template slug would turn the
 * shared shell off (inc/shell.php).
 *
 * Choose an industry → (optionally) a typical challenge → its workflows.
 * Hidden industries are never named here (same rule as REST).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_d       = omochix_industry_hub_decide();
$omochix_filters = (array) $omochix_d['filters'];
$omochix_bad     = 'bad_request' === $omochix_d['action'];
$omochix_index   = omochix_industry_hub_index_url();
$omochix_learn   = function_exists('omochix_get_published_page_url') ? omochix_get_published_page_url('learn') : '';
$omochix_data    = omochix_industry_hub_index_rows();
$omochix_paged   = max(1, (int) get_query_var('paged'));
$omochix_search  = !$omochix_bad && omochix_industry_hub_is_filtered($omochix_filters) ? omochix_industry_hub_index_search($omochix_filters, $omochix_paged) : null;

get_header();
while (have_posts()) :
    the_post();
    ?>
<main class="ih ih--index" id="main-content">
    <header class="ih-hero">
        <div class="ih-container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <?php if ($omochix_learn) : ?><li><a href="<?php echo esc_url($omochix_learn); ?>">Learn</a></li><?php endif; ?>
                    <li aria-current="page"><?php the_title(); ?></li>
                </ol>
            </nav>
            <p class="ih-eyebrow">INDUSTRY HUB</p>
            <h1 class="ih-title"><?php the_title(); ?></h1>
            <div class="ih-lead"><?php the_content(); ?></div>
        </div>
    </header>

    <?php if ($omochix_bad) : ?>
        <div class="ih-container"><?php get_template_part('template-parts/learn-industries/bad-request', null, ['reset' => $omochix_index, 'index' => '']); ?></div>
    <?php else : ?>
        <section class="ih-section" aria-labelledby="ih-industries-title">
            <div class="ih-container">
                <h2 class="ih-h2" id="ih-industries-title"><?php esc_html_e('業種を選ぶ', 'omochix'); ?></h2>
                <?php if ($omochix_data['rows']) : ?>
                    <ul class="ih-industries">
                        <?php foreach ($omochix_data['rows'] as $omochix_row) : ?>
                            <li class="ih-industry">
                                <h3 class="ih-industry__name"><a href="<?php echo esc_url($omochix_row['url']); ?>"><?php echo esc_html($omochix_row['term']->name); ?></a></h3>
                                <?php if ('' !== $omochix_row['lead']) : ?><p class="ih-industry__lead"><?php echo esc_html(wp_trim_words($omochix_row['lead'], 50, '…')); ?></p><?php endif; ?>
                                <?php if ($omochix_row['challenges']) : ?>
                                    <p class="ih-industry__label" id="ih-ind-<?php echo esc_attr($omochix_row['term']->slug); ?>-c"><?php esc_html_e('よくある課題から実践手順へ', 'omochix'); ?></p>
                                    <ul class="ih-industry__shortcuts" aria-labelledby="ih-ind-<?php echo esc_attr($omochix_row['term']->slug); ?>-c">
                                        <?php foreach ($omochix_row['challenges'] as $omochix_c) : ?>
                                            <li><a class="ih-chip ih-chip--small" href="<?php echo esc_url(omochix_industry_hub_challenge_url($omochix_row['term'], $omochix_c)); ?>"><?php echo esc_html((string) $omochix_c['title']); ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="ih-empty" role="status"><p><?php esc_html_e('業種別のページは準備中です。', 'omochix'); ?></p></div>
                <?php endif; ?>
                <?php if ($omochix_data['hidden'] > 0) : ?>
                    <p class="ih-note"><?php echo esc_html(sprintf(__('ほかに %d 業種を準備しています。', 'omochix'), (int) $omochix_data['hidden'])); ?></p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($omochix_data['rows']) : ?>
            <section class="ih-section" aria-labelledby="ih-search-title">
                <div class="ih-container">
                    <h2 class="ih-h2" id="ih-search-title"><?php esc_html_e('業種をまたいで探す', 'omochix'); ?></h2>
                    <?php get_template_part('template-parts/learn-industries/filter-bar', null, ['context' => 'index', 'term' => null, 'filters' => $omochix_filters, 'action' => $omochix_index, 'total' => $omochix_search ? (int) $omochix_search->found_posts : null]); ?>
                    <?php if ($omochix_search) : ?>
                        <?php if ($omochix_search->have_posts()) : ?>
                            <div class="ih-grid ih-grid--compact">
                                <?php foreach ($omochix_search->posts as $omochix_p) : ?>
                                    <?php
                                    if ('learn_practice' === $omochix_p->post_type && has_term('workflow', 'learn_format', $omochix_p)) {
                                        get_template_part('template-parts/learn-industries/workflow-card', null, ['post' => $omochix_p]);
                                    } else {
                                        get_template_part('template-parts/learn-industries/content-card', null, ['post' => $omochix_p]);
                                    }
                                    ?>
                                <?php endforeach; ?>
                            </div>
                            <?php
                            $omochix_links = paginate_links([
                                'base'      => trailingslashit($omochix_index) . '%_%',
                                'format'    => 'page/%#%/',
                                'current'   => $omochix_paged,
                                'total'     => (int) $omochix_search->max_num_pages,
                                'add_args'  => array_filter($omochix_filters, static fn($v) => '' !== (string) $v),
                                'mid_size'  => 1,
                                'prev_text' => __('前へ', 'omochix'),
                                'next_text' => __('次へ', 'omochix'),
                                'type'      => 'list',
                            ]);
                            if ($omochix_links) {
                                echo '<nav class="navigation pagination" aria-label="' . esc_attr__('検索結果のページ送り', 'omochix') . '">' . wp_kses_post($omochix_links) . '</nav>';
                            }
                            ?>
                        <?php else : ?>
                            <div class="ih-empty" role="status"><p><?php esc_html_e('条件に合うコンテンツがありません。', 'omochix'); ?></p><a class="ih-link" href="<?php echo esc_url($omochix_index); ?>"><?php esc_html_e('条件を外す', 'omochix'); ?></a></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="ih-section ih-section--legend" aria-labelledby="ih-legend-title">
            <div class="ih-container">
                <h2 class="ih-h3" id="ih-legend-title"><?php esc_html_e('提供元の表示について', 'omochix'); ?></h2>
                <ul class="ih-legend">
                    <li><?php get_template_part('template-parts/learn-industries/provider-badge', null, ['type' => 'official']); ?> <?php esc_html_e('OmochiX が作成・検証したコンテンツ', 'omochix'); ?></li>
                    <li><?php get_template_part('template-parts/learn-industries/provider-badge', null, ['type' => 'external-service']); ?> <?php esc_html_e('外部サービスの公式情報', 'omochix'); ?></li>
                    <li><?php get_template_part('template-parts/learn-industries/provider-badge', null, ['type' => 'creator']); ?> <?php esc_html_e('外部のクリエイターのコンテンツ', 'omochix'); ?></li>
                    <li><?php get_template_part('template-parts/learn-industries/provider-badge', null, ['type' => 'partner']); ?> <?php esc_html_e('提携・広告を含むコンテンツ', 'omochix'); ?></li>
                </ul>
                <p class="ih-note"><?php esc_html_e('提供元が確認できていないコンテンツには表示を付けません（公式とはみなしません）。', 'omochix'); ?></p>
            </div>
        </section>
    <?php endif; ?>
</main>
    <?php
endwhile;
get_footer();
