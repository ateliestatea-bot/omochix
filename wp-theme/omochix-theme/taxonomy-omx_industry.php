<?php
/**
 * Industry hub: /learn/industries/{industry}/ (docs/Industry-Hub-Phase2-Design.md §6).
 *
 * Section order (§6.1): 1 overview + benefits, 2 choose a challenge,
 * 3 AI use cases, 4 hands-on workflows, 5 tools / prompts / Motion,
 * 6 other related content (filters + pagination, the main query).
 * Hidden hubs never reach this template for visitors (they are 404).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_d       = omochix_industry_hub_decide();
$omochix_term    = $omochix_d['term'] instanceof WP_Term ? $omochix_d['term'] : get_queried_object();
$omochix_filters = (array) $omochix_d['filters'];
$omochix_bad     = 'bad_request' === $omochix_d['action'];
$omochix_hub_url = omochix_industry_hub_url($omochix_term);
$omochix_index   = omochix_industry_hub_index_url();
$omochix_learn   = function_exists('omochix_get_published_page_url') ? omochix_get_published_page_url('learn') : '';
$omochix_s       = $omochix_bad ? null : omochix_industry_hub_sections($omochix_term, $omochix_filters);
$omochix_purpose = (string) ($omochix_filters['purpose'] ?? '');
$omochix_purpose_name = '';
if ('' !== $omochix_purpose) {
    $omochix_pt = get_term_by('slug', $omochix_purpose, 'omx_purpose');
    $omochix_purpose_name = $omochix_pt instanceof WP_Term ? $omochix_pt->name : '';
}

get_header();
?>
<main class="ih ih--hub" id="main-content">
    <header class="ih-hero">
        <div class="ih-container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <?php if ($omochix_learn) : ?><li><a href="<?php echo esc_url($omochix_learn); ?>">Learn</a></li><?php endif; ?>
                    <?php if ($omochix_index) : ?><li><a href="<?php echo esc_url($omochix_index); ?>"><?php esc_html_e('業種別AI活用', 'omochix'); ?></a></li><?php endif; ?>
                    <li aria-current="page"><?php echo esc_html($omochix_term->name); ?></li>
                </ol>
            </nav>
            <?php if (!empty($omochix_d['preview'])) : ?>
                <p class="ih-preview" role="note"><?php esc_html_e('未公開プレビュー：この業種ハブは公開条件（G1〜G5）を満たしていないため、編集者にだけ表示されています。', 'omochix'); ?></p>
            <?php endif; ?>
            <p class="ih-eyebrow">INDUSTRY</p>
            <h1 class="ih-title"><?php echo esc_html(sprintf(__('%sのAI活用', 'omochix'), $omochix_term->name)); ?></h1>
        </div>
    </header>

    <?php if ($omochix_bad) : ?>
        <div class="ih-container"><?php get_template_part('template-parts/learn-industries/bad-request', null, ['reset' => $omochix_hub_url, 'index' => $omochix_index]); ?></div>
    <?php else : ?>
        <?php
        $omochix_jump = [['ih-overview', __('概要', 'omochix')], ['ih-challenges', __('課題', 'omochix')], ['ih-usecases', __('事例', 'omochix')], ['ih-workflows', __('手順', 'omochix')]];
        if ($omochix_s['tools'] || $omochix_s['prompts'] || $omochix_s['motion']) {
            $omochix_jump[] = ['ih-tools', __('ツール', 'omochix')];
        }
        $omochix_jump[] = ['ih-more', __('その他', 'omochix')];
        get_template_part('template-parts/learn-industries/jump-nav', null, ['items' => $omochix_jump]);
        ?>

        <!-- 1. Overview and benefits -->
        <section class="ih-section ih-section--overview" id="ih-overview" aria-labelledby="ih-overview-title">
            <div class="ih-container">
                <h2 class="screen-reader-text" id="ih-overview-title"><?php esc_html_e('業種の概要とAI活用のメリット', 'omochix'); ?></h2>
                <p class="ih-lead"><?php echo esc_html($omochix_s['overview']['lead']); ?></p>
                <?php if ($omochix_s['overview']['benefits']) : ?>
                    <?php $omochix_b = array_values($omochix_s['overview']['benefits']); ?>
                    <?php // Phones: every benefit folded (the challenges must fit the first screen). Wider: two shown, the rest folded. ?>
                    <details class="ih-more-benefits ih-only-narrow">
                        <summary><?php echo esc_html(sprintf(__('AI活用のメリット（%d件）', 'omochix'), count($omochix_b))); ?></summary>
                        <ul class="ih-benefits">
                            <?php foreach ($omochix_b as $omochix_row) : ?>
                                <li><strong><?php echo esc_html((string) $omochix_row['title']); ?></strong><span><?php echo esc_html((string) $omochix_row['body']); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    <div class="ih-only-wide">
                        <ul class="ih-benefits">
                            <?php foreach (array_slice($omochix_b, 0, 2) as $omochix_row) : ?>
                                <li><strong><?php echo esc_html((string) $omochix_row['title']); ?></strong><span><?php echo esc_html((string) $omochix_row['body']); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($omochix_b) > 2) : ?>
                            <details class="ih-more-benefits">
                                <summary><?php echo esc_html(sprintf(__('ほかのメリット（%d件）', 'omochix'), count($omochix_b) - 2)); ?></summary>
                                <ul class="ih-benefits">
                                    <?php foreach (array_slice($omochix_b, 2) as $omochix_row) : ?>
                                        <li><strong><?php echo esc_html((string) $omochix_row['title']); ?></strong><span><?php echo esc_html((string) $omochix_row['body']); ?></span></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ('' !== $omochix_s['overview']['verified_at']) : ?>
                    <p class="ih-verified"><?php echo esc_html(sprintf(__('最終確認日：%s', 'omochix'), $omochix_s['overview']['verified_at'])); ?></p>
                <?php endif; ?>
            </div>
        </section>

        <!-- 2. Choose a challenge -->
        <section class="ih-section ih-section--challenges" id="ih-challenges" aria-labelledby="ih-challenges-title">
            <div class="ih-container">
                <h2 class="ih-h2" id="ih-challenges-title"><?php esc_html_e('解決したい業務課題を選ぶ', 'omochix'); ?></h2>
                <ul class="ih-chips">
                    <li><a class="ih-chip" href="<?php echo esc_url($omochix_hub_url . '#ih-workflows'); ?>"<?php echo '' === $omochix_purpose ? ' aria-current="true"' : ''; ?>><?php esc_html_e('すべての課題', 'omochix'); ?></a></li>
                    <?php foreach ($omochix_s['challenges'] as $omochix_c) : if ('' === (string) ($omochix_c['title'] ?? '')) { continue; } ?>
                        <?php $omochix_cp = (string) ($omochix_c['purpose'] ?? ''); ?>
                        <li><a class="ih-chip" href="<?php echo esc_url(omochix_industry_hub_challenge_url($omochix_term, $omochix_c)); ?>"<?php echo '' !== $omochix_cp && $omochix_cp === $omochix_purpose ? ' aria-current="true"' : ''; ?>><?php echo esc_html((string) $omochix_c['title']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <div class="ih-grid ih-grid--challenges">
                    <?php foreach ($omochix_s['challenges'] as $omochix_c) : if ('' === (string) ($omochix_c['title'] ?? '')) { continue; } ?>
                        <article class="ih-challenge"><h3><?php echo esc_html((string) $omochix_c['title']); ?></h3><p><?php echo esc_html((string) $omochix_c['body']); ?></p></article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 3. AI use cases -->
        <section class="ih-section" id="ih-usecases" aria-labelledby="ih-usecases-title">
            <div class="ih-container">
                <h2 class="ih-h2" id="ih-usecases-title"><?php echo '' !== $omochix_purpose_name ? esc_html(sprintf(__('AI活用事例（「%s」を先頭に表示）', 'omochix'), $omochix_purpose_name)) : esc_html__('AI活用事例', 'omochix'); ?></h2>
                <div class="ih-grid">
                    <?php foreach ($omochix_s['usecases'] as $omochix_u) : if ('' === (string) ($omochix_u['title'] ?? '')) { continue; } ?>
                        <?php $omochix_up = (string) ($omochix_u['purpose'] ?? ''); ?>
                        <article class="ih-usecase<?php echo '' !== $omochix_purpose && $omochix_up === $omochix_purpose ? ' is-match' : ''; ?>">
                            <h3><?php echo esc_html((string) $omochix_u['title']); ?></h3>
                            <p><?php echo esc_html((string) $omochix_u['body']); ?></p>
                            <?php if ('' !== $omochix_purpose && $omochix_up === $omochix_purpose) : ?><p class="ih-usecase__match"><?php esc_html_e('選んだ課題に関係する事例', 'omochix'); ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 4. Hands-on workflows -->
        <section class="ih-section ih-section--workflows" id="ih-workflows" aria-labelledby="ih-workflows-title">
            <div class="ih-container">
                <h2 class="ih-h2" id="ih-workflows-title"><?php esc_html_e('実践ワークフロー', 'omochix'); ?></h2>
                <?php if ($omochix_s['workflows']) : ?>
                    <div class="ih-grid">
                        <?php foreach ($omochix_s['workflows'] as $omochix_post) : ?>
                            <?php get_template_part('template-parts/learn-industries/workflow-card', null, ['post' => $omochix_post]); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="ih-empty" role="status">
                        <p><?php echo '' !== $omochix_purpose ? esc_html__('この課題の公式ワークフローは準備中です。', 'omochix') : esc_html__('ワークフローは準備中です。', 'omochix'); ?></p>
                        <?php if ('' !== $omochix_purpose) : ?><a class="ih-link" href="<?php echo esc_url($omochix_hub_url . '#ih-workflows'); ?>"><?php esc_html_e('すべてのワークフローを見る', 'omochix'); ?></a><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 5. Tools, prompts, Motion -->
        <?php if ($omochix_s['tools'] || $omochix_s['prompts'] || $omochix_s['motion']) : ?>
            <section class="ih-section" id="ih-tools" aria-labelledby="ih-tools-title">
                <div class="ih-container">
                    <h2 class="ih-h2" id="ih-tools-title"><?php esc_html_e('推奨AIツール・プロンプト・Motion教材', 'omochix'); ?></h2>
                    <?php if ($omochix_s['tools']) : ?>
                        <h3 class="ih-h3"><?php esc_html_e('AIツール', 'omochix'); ?></h3>
                        <div class="ih-grid ih-grid--compact">
                            <?php foreach ($omochix_s['tools'] as $omochix_post) : get_template_part('template-parts/learn-industries/content-card', null, ['post' => $omochix_post]); endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($omochix_s['prompts']) : ?>
                        <h3 class="ih-h3"><?php esc_html_e('プロンプト', 'omochix'); ?></h3>
                        <div class="ih-grid ih-grid--compact">
                            <?php foreach ($omochix_s['prompts'] as $omochix_post) : get_template_part('template-parts/learn-industries/content-card', null, ['post' => $omochix_post]); endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($omochix_s['motion']) : ?>
                        <h3 class="ih-h3"><?php esc_html_e('Motion教材', 'omochix'); ?></h3>
                        <div class="ih-grid ih-grid--compact">
                            <?php foreach ($omochix_s['motion'] as $omochix_m) : ?>
                                <article class="ih-card ih-card--motion">
                                    <div class="ih-card__meta"><span class="ih-card__type"><?php esc_html_e('Motion', 'omochix'); ?></span><span class="ih-badge ih-badge--official"><?php esc_html_e('OmochiX公式', 'omochix'); ?></span></div>
                                    <h3 class="ih-card__title"><a href="<?php echo esc_url(function_exists('omochix_learn_gallery_url') ? omochix_learn_gallery_url($omochix_m) : ''); ?>"><?php echo esc_html((string) ($omochix_m['name']['ja'] ?? $omochix_m['slug'])); ?></a></h3>
                                    <?php if (!empty($omochix_m['summary'])) : ?><p class="ih-card__text"><?php echo esc_html((string) $omochix_m['summary']); ?></p><?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- 6. Other related content -->
        <section class="ih-section ih-section--more" id="ih-more" aria-labelledby="ih-more-title">
            <div class="ih-container">
                <h2 class="ih-h2" id="ih-more-title"><?php esc_html_e('その他の関連コンテンツ', 'omochix'); ?></h2>
                <?php if ($omochix_s['lessons']) : ?>
                    <h3 class="ih-h3"><?php esc_html_e('関連する実践教材', 'omochix'); ?></h3>
                    <div class="ih-grid">
                        <?php foreach ($omochix_s['lessons'] as $omochix_post) : get_template_part('template-parts/learn-practice/card', null, ['post' => $omochix_post, 'size' => 'compact']); endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($omochix_s['resources']) : ?>
                    <h3 class="ih-h3"><?php esc_html_e('外部のコンテンツ', 'omochix'); ?></h3>
                    <p class="ih-note"><?php esc_html_e('外部サイトのコンテンツです。内容は各提供元の責任で公開されています。', 'omochix'); ?></p>
                    <div class="ih-grid ih-grid--compact">
                        <?php foreach ($omochix_s['resources'] as $omochix_card) : get_template_part('template-parts/learn-industries/resource-card', null, ['card' => $omochix_card]); endforeach; ?>
                    </div>
                <?php endif; ?>

                <h3 class="ih-h3" id="ih-list"><?php esc_html_e('すべての関連コンテンツ', 'omochix'); ?></h3>
                <?php get_template_part('template-parts/learn-industries/filter-bar', null, ['context' => 'hub', 'term' => $omochix_term, 'filters' => $omochix_filters, 'action' => $omochix_hub_url, 'total' => (int) $GLOBALS['wp_query']->found_posts]); ?>
                <?php if (have_posts()) : ?>
                    <div class="ih-grid ih-grid--compact">
                        <?php while (have_posts()) : the_post(); ?>
                            <?php
                            $omochix_p = get_post();
                            if ('learn_practice' === $omochix_p->post_type && has_term('workflow', 'learn_format', $omochix_p)) {
                                get_template_part('template-parts/learn-industries/workflow-card', null, ['post' => $omochix_p]);
                            } else {
                                get_template_part('template-parts/learn-industries/content-card', null, ['post' => $omochix_p]);
                            }
                            ?>
                        <?php endwhile; ?>
                    </div>
                    <?php the_posts_pagination(['mid_size' => 1, 'prev_text' => __('前へ', 'omochix'), 'next_text' => __('次へ', 'omochix'), 'screen_reader_text' => __('関連コンテンツのページ送り', 'omochix')]); ?>
                <?php else : ?>
                    <div class="ih-empty" role="status">
                        <p><?php esc_html_e('条件に合うコンテンツがありません。', 'omochix'); ?></p>
                        <?php if (omochix_industry_hub_is_filtered($omochix_filters)) : ?><a class="ih-link" href="<?php echo esc_url($omochix_hub_url . '#ih-list'); ?>"><?php esc_html_e('条件を外す', 'omochix'); ?></a><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php $omochix_sources = array_filter((array) $omochix_s['sources'], static fn($r) => '' !== (string) ($r['url'] ?? '')); ?>
                <?php if ($omochix_sources) : ?>
                    <h3 class="ih-h3"><?php esc_html_e('出典', 'omochix'); ?></h3>
                    <ul class="ih-sources">
                        <?php foreach ($omochix_sources as $omochix_src) : ?>
                            <li><a href="<?php echo esc_url((string) $omochix_src['url']); ?>" rel="noopener noreferrer external" target="_blank"><?php echo esc_html('' !== (string) $omochix_src['label'] ? (string) $omochix_src['label'] : (string) $omochix_src['url']); ?><span class="screen-reader-text"><?php esc_html_e('（外部サイト・新しいタブで開きます）', 'omochix'); ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
get_footer();
