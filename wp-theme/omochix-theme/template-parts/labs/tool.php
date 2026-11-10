<?php
/**
 * OmochiX Labs tool page: header, the tool itself (template-parts/labs/
 * content/{slug}.php, part "workspace"), the guide (same file, part
 * "guide"), FAQ and related links. Everything except the interactive tool
 * is plain HTML, readable without JavaScript.
 *
 * @package OmochiX
 *
 * @var array $args { tool: array }
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_tool     = $args['tool'];
$omochix_category = omochix_labs_get_category($omochix_tool['category']);
$omochix_list_url = omochix_labs_url();
$omochix_content  = 'template-parts/labs/content/' . $omochix_tool['slug'];
?>
<main class="labs labs-tool" id="main-content">
    <header class="labs-hero labs-hero--tool">
        <div class="labs-container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <li><a href="<?php echo esc_url($omochix_list_url); ?>"><?php echo esc_html(get_the_title(omochix_labs_page_id())); ?></a></li>
                    <li aria-current="page"><?php echo esc_html($omochix_tool['name']); ?></li>
                </ol>
            </nav>
            <p class="labs-eyebrow"><?php echo esc_html($omochix_category ? 'OMOCHIX LABS / ' . $omochix_category['name'] : 'OMOCHIX LABS'); ?></p>
            <h1><?php echo esc_html($omochix_tool['name']); ?></h1>
            <p class="labs-lead"><?php echo esc_html($omochix_tool['lead']); ?></p>
            <p class="labs-privacy"><span class="labs-icon labs-icon--lock" aria-hidden="true"></span><?php esc_html_e('入力した内容はブラウザの外に送信されません。', 'omochix'); ?></p>
        </div>
    </header>

    <div class="labs-container">
        <noscript>
            <p class="labs-noscript"><?php esc_html_e('このツールは JavaScript を使ってブラウザの中で動きます。JavaScript を有効にしてお使いください（使い方と解説は下に掲載しています）。', 'omochix'); ?></p>
        </noscript>

        <section class="labs-workspace" aria-labelledby="labs-workspace-title" data-labs-tool="<?php echo esc_attr($omochix_tool['slug']); ?>" data-labs-pending aria-busy="false">
            <h2 class="labs-workspace__title" id="labs-workspace-title"><?php echo esc_html($omochix_tool['name']); ?></h2>
            <?php get_template_part($omochix_content, null, ['tool' => $omochix_tool, 'part' => 'workspace']); ?>
            <div class="labs-status" role="status" aria-live="polite" data-labs-status></div>
            <div class="labs-error" role="alert" data-labs-error hidden></div>
        </section>

        <article class="labs-guide" aria-labelledby="labs-guide-title">
            <h2 id="labs-guide-title"><?php echo esc_html(sprintf(__('%sの使い方と解説', 'omochix'), $omochix_tool['name'])); ?></h2>
            <?php get_template_part($omochix_content, null, ['tool' => $omochix_tool, 'part' => 'guide']); ?>
            <p class="labs-updated"><?php echo esc_html(sprintf(__('最終更新：%s', 'omochix'), wp_date(__('Y年n月j日', 'omochix'), strtotime($omochix_tool['updated'] . ' 12:00:00')))); ?></p>
        </article>

        <?php get_template_part('template-parts/labs/faq', null, ['tool' => $omochix_tool]); ?>
        <?php get_template_part('template-parts/labs/related', null, ['tool' => $omochix_tool]); ?>
    </div>
</main>
