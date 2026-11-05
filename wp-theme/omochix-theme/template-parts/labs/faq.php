<?php
/**
 * Tool FAQ from the catalog. Shown as plain disclosure widgets; deliberately
 * no FAQPage schema (Google limits FAQ rich results to a few site types).
 *
 * @package OmochiX
 *
 * @var array $args { tool: array }
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_faq = $args['tool']['faq'];
if (!$omochix_faq) {
    return;
}
?>
<section class="labs-section labs-faq" aria-labelledby="labs-faq-title">
    <h2 id="labs-faq-title"><?php esc_html_e('よくある質問', 'omochix'); ?></h2>
    <?php foreach ($omochix_faq as $omochix_entry) : ?>
        <details class="labs-faq__item">
            <summary><?php echo esc_html($omochix_entry['q']); ?></summary>
            <p><?php echo esc_html($omochix_entry['a']); ?></p>
        </details>
    <?php endforeach; ?>
</section>
