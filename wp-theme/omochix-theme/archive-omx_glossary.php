<?php
/**
 * AI glossary: /glossary/ (every visible term, grouped by category).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
// WordPress can pick this file by name even when the glossary is closed or its module is missing.
if (!function_exists('omochix_glossary_enabled') || !omochix_glossary_enabled() || 'ok' !== omochix_glossary_decide()['action']) {
    get_template_part('template-parts/glossary/not-found');
    return;
}
$omochix_cats   = omochix_glossary_categories();
$omochix_groups = [];
foreach ($omochix_cats as $omochix_c) {
    $omochix_groups[] = ['term' => $omochix_c, 'posts' => omochix_glossary_visible_terms($omochix_c->slug)];
}
$omochix_count = count(omochix_glossary_visible_terms());
get_header();
?>
<main class="glossary glossary--index" id="main-content">
    <header class="glossary-hero">
        <div class="glossary-container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <li aria-current="page"><?php esc_html_e('AI用語集', 'omochix'); ?></li>
                </ol>
            </nav>
            <h1 class="glossary-hero__title"><?php esc_html_e('AI用語集', 'omochix'); ?></h1>
            <p class="glossary-hero__lead"><?php esc_html_e('生成AI・大規模言語モデル・AIエージェントなど、AIとテクノロジーの用語を、一次情報にもとづいてわかりやすく解説します。', 'omochix'); ?></p>
            <p class="glossary-hero__count"><?php echo esc_html(sprintf(/* translators: %d: number of terms */ __('%d語を掲載', 'omochix'), $omochix_count)); ?></p>
        </div>
    </header>
    <div class="glossary-container glossary-body">
        <?php get_template_part('template-parts/glossary/filter', null, ['categories' => $omochix_cats, 'current' => null, 'index_url' => omochix_glossary_index_url()]); ?>
        <?php get_template_part('template-parts/glossary/list', null, ['groups' => $omochix_groups, 'heading' => true]); ?>
    </div>
</main>
<?php
get_footer();
