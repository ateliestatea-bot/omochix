<?php
/**
 * OmochiX Labs list: search, categories and tool cards. The list is fully
 * rendered here (and filtered by ?cat= / ?q=) so it works without
 * JavaScript; assets/js/labs/ui/index.js filters in place.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_filters    = omochix_labs_sanitize_filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filters.
$omochix_tools      = omochix_labs_get_tools();
$omochix_matching   = omochix_labs_filter_tools($omochix_filters);
$omochix_categories = omochix_labs_get_categories();
$omochix_list_url   = omochix_labs_url();
$omochix_lead       = has_excerpt() ? trim(wp_strip_all_tags(get_the_excerpt())) : __('AI・開発・Web運用で「ちょっと確かめたい」を、その場で片づける無料ツール集です。どのツールもブラウザの中だけで動き、入力した内容は外部に送信されません。', 'omochix');
$omochix_sections   = [
    ['label' => 'AIツール', 'url' => get_post_type_archive_link('ai_tool') ?: '', 'type' => 'section', 'slug' => 'ai-tools', 'text' => __('目的別にAIサービスを比べる', 'omochix')],
    ['label' => 'Learn', 'url' => omochix_get_published_page_url('learn'), 'type' => 'section', 'slug' => 'learn', 'text' => __('AIの使い方から開発まで学ぶ', 'omochix')],
    ['label' => 'Prompts', 'url' => get_post_type_archive_link('prompt') ?: '', 'type' => 'section', 'slug' => 'prompts', 'text' => __('そのまま使えるプロンプト集', 'omochix')],
];
?>
<main class="labs labs-index" id="main-content" data-labs-index>
    <header class="labs-hero">
        <div class="labs-container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <li aria-current="page"><?php the_title(); ?></li>
                </ol>
            </nav>
            <p class="labs-eyebrow">OMOCHIX LABS</p>
            <h1><?php the_title(); ?></h1>
            <p class="labs-lead"><?php echo esc_html($omochix_lead); ?></p>
            <ul class="labs-promises">
                <li><?php esc_html_e('無料・登録不要', 'omochix'); ?></li>
                <li><?php esc_html_e('ブラウザ内で処理（入力は送信されません）', 'omochix'); ?></li>
                <li><?php esc_html_e('使い方と解説つき', 'omochix'); ?></li>
            </ul>
        </div>
    </header>

    <div class="labs-container">
        <?php // Shown by ui/index.js: searching never puts the typed words in a URL (GA4 records page URLs). ?>
        <form class="labs-search" role="search" method="get" action="<?php echo esc_url($omochix_list_url); ?>" data-labs-search hidden>
            <label class="labs-search__label" for="labs-search-q"><?php esc_html_e('ツールを検索', 'omochix'); ?></label>
            <div class="labs-search__row">
                <input class="labs-input" type="search" id="labs-search-q" name="q" value="<?php echo esc_attr($omochix_filters['q']); ?>" maxlength="60" autocomplete="off" placeholder="<?php esc_attr_e('例：json、差分、robots', 'omochix'); ?>">
                <?php if ('' !== $omochix_filters['cat']) : ?>
                    <input type="hidden" name="cat" value="<?php echo esc_attr($omochix_filters['cat']); ?>" data-labs-cat-input>
                <?php endif; ?>
                <button class="labs-button" type="submit"><?php esc_html_e('検索', 'omochix'); ?></button>
            </div>
        </form>

        <nav class="labs-categories" aria-label="<?php esc_attr_e('カテゴリ', 'omochix'); ?>">
            <ul>
                <li><a class="labs-chip" href="<?php echo esc_url($omochix_list_url); ?>" data-labs-cat=""<?php echo '' === $omochix_filters['cat'] ? ' aria-current="true"' : ''; ?>><?php esc_html_e('すべて', 'omochix'); ?> <span class="labs-chip__count"><?php echo esc_html((string) count($omochix_tools)); ?></span></a></li>
                <?php foreach ($omochix_categories as $omochix_cat) : ?>
                    <li><a class="labs-chip" href="<?php echo esc_url(omochix_labs_url(null, ['cat' => $omochix_cat['slug']])); ?>" data-labs-cat="<?php echo esc_attr($omochix_cat['slug']); ?>"<?php echo $omochix_cat['slug'] === $omochix_filters['cat'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html($omochix_cat['name']); ?> <span class="labs-chip__count"><?php echo esc_html((string) $omochix_cat['count']); ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <p class="labs-count" role="status" aria-live="polite" data-labs-count><?php echo esc_html(sprintf(__('%s件のツール', 'omochix'), count($omochix_matching))); ?></p>

        <?php if (!$omochix_tools) : ?>
            <div class="static-page__notice" role="status">
                <h2><?php esc_html_e('ツールを準備中です。', 'omochix'); ?></h2>
                <p><?php esc_html_e('カタログが読み込めませんでした。テーマの assets/data/labs/catalog.json を確認してください。', 'omochix'); ?></p>
            </div>
        <?php else : ?>
            <ul class="labs-grid" data-labs-grid>
                <?php foreach ($omochix_tools as $omochix_tool) : ?>
                    <?php get_template_part('template-parts/labs/card', null, ['tool' => $omochix_tool, 'hidden' => !isset($omochix_matching[$omochix_tool['slug']]), 'heading' => 'h2']); ?>
                <?php endforeach; ?>
            </ul>
            <p class="labs-empty" data-labs-empty<?php echo $omochix_matching ? ' hidden' : ''; ?>><?php esc_html_e('条件に合うツールがありません。キーワードを変えるか、カテゴリを「すべて」に戻してください。', 'omochix'); ?></p>
        <?php endif; ?>

        <section class="labs-section" aria-labelledby="labs-about-title">
            <h2 id="labs-about-title"><?php esc_html_e('OmochiX Labs について', 'omochix'); ?></h2>
            <div class="labs-about">
                <div>
                    <h3><?php esc_html_e('入力は外に出ません', 'omochix'); ?></h3>
                    <p><?php esc_html_e('整形・比較・計算はすべてお使いのブラウザの中で行います。サーバーに入力を送る仕組み自体がないため、社内の設定ファイルや下書きも安心して試せます。', 'omochix'); ?></p>
                </div>
                <div>
                    <h3><?php esc_html_e('結果の読み方まで', 'omochix'); ?></h3>
                    <p><?php esc_html_e('各ツールのページに、使い方・よくある間違い・FAQをまとめています。エラーの意味や設定の注意点もその場で確認できます。', 'omochix'); ?></p>
                </div>
                <div>
                    <h3><?php esc_html_e('AI活用とつながる', 'omochix'); ?></h3>
                    <p><?php esc_html_e('AIクローラーの設定など、AIを使う・AIに読まれる場面で役立つツールを中心に増やしていきます。', 'omochix'); ?></p>
                </div>
            </div>
        </section>

        <?php $omochix_sections = array_filter($omochix_sections, static fn($s) => '' !== $s['url']); ?>
        <?php if ($omochix_sections) : ?>
            <section class="labs-section" aria-labelledby="labs-more-title">
                <h2 id="labs-more-title"><?php esc_html_e('OmochiX のほかのコンテンツ', 'omochix'); ?></h2>
                <ul class="labs-links">
                    <?php foreach ($omochix_sections as $omochix_section) : ?>
                        <li><a href="<?php echo esc_url($omochix_section['url']); ?>" data-labs-link-type="<?php echo esc_attr($omochix_section['type']); ?>" data-labs-link-slug="<?php echo esc_attr($omochix_section['slug']); ?>"><strong><?php echo esc_html($omochix_section['label']); ?></strong><span><?php echo esc_html($omochix_section['text']); ?></span></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</main>
