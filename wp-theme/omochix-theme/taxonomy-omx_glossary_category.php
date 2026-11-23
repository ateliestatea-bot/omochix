<?php
/**
 * AI glossary: /glossary/category/{slug}/ (the visible terms of one category).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
if (!function_exists('omochix_glossary_enabled') || !omochix_glossary_enabled() || 'ok' !== omochix_glossary_decide()['action'] || !omochix_glossary_decide()['term'] instanceof WP_Term) {
    get_template_part('template-parts/glossary/not-found');
    return;
}
$omochix_term  = omochix_glossary_decide()['term'];
$omochix_posts = omochix_glossary_visible_terms($omochix_term->slug);
$omochix_lead  = (string) get_term_meta($omochix_term->term_id, 'glossary_category_lead', true);
$omochix_index = omochix_glossary_index_url();
get_header();
?>
<main class="glossary glossary--category" id="main-content">
    <header class="glossary-hero">
        <div class="glossary-container">
            <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                <ol>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <li><a href="<?php echo esc_url($omochix_index); ?>"><?php esc_html_e('AI用語集', 'omochix'); ?></a></li>
                    <li aria-current="page"><?php echo esc_html($omochix_term->name); ?></li>
                </ol>
            </nav>
            <h1 class="glossary-hero__title"><?php echo esc_html(sprintf(/* translators: %s: category */ __('%sの用語', 'omochix'), $omochix_term->name)); ?></h1>
            <?php if ('' !== $omochix_lead) : ?><p class="glossary-hero__lead"><?php echo esc_html($omochix_lead); ?></p><?php endif; ?>
            <p class="glossary-hero__count"><?php echo esc_html(sprintf(/* translators: %d: number of terms */ __('%d語', 'omochix'), count($omochix_posts))); ?></p>
        </div>
    </header>
    <div class="glossary-container glossary-body">
        <?php get_template_part('template-parts/glossary/filter', null, ['categories' => omochix_glossary_categories(), 'current' => $omochix_term, 'index_url' => $omochix_index]); ?>
        <?php get_template_part('template-parts/glossary/list', null, ['groups' => [['term' => $omochix_term, 'posts' => $omochix_posts]], 'heading' => false]); ?>
    </div>
</main>
<?php
get_footer();
