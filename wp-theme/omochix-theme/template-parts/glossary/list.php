<?php
/**
 * AI glossary: the list of terms, grouped by category.
 *
 * @package OmochiX
 *
 * @var array $args ['groups' => [[ 'term' => ?WP_Term, 'posts' => WP_Post[] ], ...], 'heading' => bool]
 */

if (!defined('ABSPATH')) {
    exit;
}
foreach ((array) ($args['groups'] ?? []) as $omochix_g) :
    $omochix_posts = (array) ($omochix_g['posts'] ?? []);
    if (!$omochix_posts) { continue; }
    $omochix_t  = $omochix_g['term'] ?? null;
    $omochix_hid = $omochix_t instanceof WP_Term ? 'glossary-cat-' . sanitize_html_class($omochix_t->slug) : 'glossary-all';
    ?>
    <section class="glossary-group" aria-labelledby="<?php echo esc_attr($omochix_hid); ?>">
        <?php if (!empty($args['heading']) && $omochix_t instanceof WP_Term) : ?>
            <h2 class="glossary-group__title" id="<?php echo esc_attr($omochix_hid); ?>"><?php echo esc_html($omochix_t->name); ?></h2>
        <?php else : ?>
            <h2 class="screen-reader-text" id="<?php echo esc_attr($omochix_hid); ?>"><?php esc_html_e('用語の一覧', 'omochix'); ?></h2>
        <?php endif; ?>
        <ul class="glossary-cards">
            <?php foreach ($omochix_posts as $omochix_p) {
                get_template_part('template-parts/glossary/card', null, ['view' => omochix_glossary_view($omochix_p)]);
            } ?>
        </ul>
    </section>
<?php endforeach;
