<?php
/**
 * Related Labs tools, then related Learn pages, AI Tools and Prompts
 * (published content only; missing slugs are skipped).
 *
 * @package OmochiX
 *
 * @var array $args { tool: array }
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_tool    = $args['tool'];
$omochix_related = omochix_labs_related_tools($omochix_tool, 4);
$omochix_links   = omochix_labs_site_links($omochix_tool);
$omochix_groups  = [
    'learn'    => __('Learn で学ぶ', 'omochix'),
    'ai_tools' => __('関連するAIツール', 'omochix'),
    'prompts'  => __('使えるプロンプト', 'omochix'),
];
?>
<?php if ($omochix_related) : ?>
    <section class="labs-section" aria-labelledby="labs-related-title">
        <h2 id="labs-related-title"><?php esc_html_e('ほかのツール', 'omochix'); ?></h2>
        <ul class="labs-grid labs-grid--compact">
            <?php foreach ($omochix_related as $omochix_item) : ?>
                <?php get_template_part('template-parts/labs/card', null, ['tool' => $omochix_item, 'heading' => 'h3']); ?>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if (array_filter($omochix_links)) : ?>
    <section class="labs-section" aria-labelledby="labs-site-links-title">
        <h2 id="labs-site-links-title"><?php esc_html_e('あわせて読みたい', 'omochix'); ?></h2>
        <div class="labs-site-links">
            <?php foreach ($omochix_groups as $omochix_key => $omochix_label) : ?>
                <?php if (!$omochix_links[$omochix_key]) { continue; } ?>
                <div class="labs-site-links__group">
                    <h3><?php echo esc_html($omochix_label); ?></h3>
                    <ul>
                        <?php foreach ($omochix_links[$omochix_key] as $omochix_link) : ?>
                            <li><a href="<?php echo esc_url($omochix_link['url']); ?>" data-labs-link-type="<?php echo esc_attr($omochix_link['type']); ?>" data-labs-link-slug="<?php echo esc_attr($omochix_link['slug']); ?>"><?php echo esc_html($omochix_link['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
