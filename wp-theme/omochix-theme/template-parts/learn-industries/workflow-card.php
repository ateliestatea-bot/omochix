<?php
/**
 * Workflow card: the problem it solves, level, time, cost and the evidence
 * class of its results (verified / published / expected), each in text.
 *
 * Args: post (WP_Post).
 *
 * Optional arg: level (heading level of the card title, 2–6; default 3).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_h = isset($args['level']) && in_array((int) $args['level'], [2, 3, 4, 5, 6], true) ? 'h' . (int) $args['level'] : 'h3';
$omochix_post = isset($args['post']) ? get_post($args['post']) : null;
if (!$omochix_post instanceof WP_Post) {
    return;
}
$omochix_problem = (string) get_post_meta($omochix_post->ID, 'workflow_problem', true);
$omochix_level   = (string) get_post_meta($omochix_post->ID, 'practice_difficulty', true);
$omochix_minutes = (int) get_post_meta($omochix_post->ID, 'practice_minutes', true);
$omochix_cost    = (array) get_post_meta($omochix_post->ID, 'workflow_cost', true);
$omochix_results = (array) get_post_meta($omochix_post->ID, 'workflow_results', true);
$omochix_levels  = function_exists('omochix_industry_hub_levels') ? omochix_industry_hub_levels() : [];
$omochix_ev      = function_exists('omochix_core_get_workflow_evidence_labels') ? omochix_core_get_workflow_evidence_labels() : [];
$omochix_found   = array_values(array_unique(array_filter(array_map(static fn($r) => is_array($r) ? (string) ($r['evidence'] ?? '') : '', $omochix_results))));
$omochix_amount  = '';
foreach ($omochix_cost as $omochix_row) {
    if (is_array($omochix_row) && '' !== (string) ($omochix_row['amount'] ?? '')) {
        $omochix_amount = (string) $omochix_row['amount'];
        break;
    }
}
?>
<article class="ih-card ih-card--workflow">
    <div class="ih-card__meta">
        <span class="ih-card__type"><?php esc_html_e('ワークフロー', 'omochix'); ?></span>
        <?php get_template_part('template-parts/learn-industries/provider-badge', null, ['post' => $omochix_post]); ?>
    </div>
    <<?php echo $omochix_h; ?> class="ih-card__title"><a href="<?php echo esc_url(get_permalink($omochix_post)); ?>"><?php echo esc_html(get_the_title($omochix_post)); ?></a></<?php echo $omochix_h; ?>>
    <?php if ('' !== $omochix_problem) : ?>
        <p class="ih-card__text"><span class="ih-card__label"><?php esc_html_e('解決する課題', 'omochix'); ?></span><?php echo esc_html(wp_trim_words($omochix_problem, 60, '…')); ?></p>
    <?php endif; ?>
    <dl class="ih-facts">
        <?php if (isset($omochix_levels[$omochix_level])) : ?><div><dt><?php esc_html_e('難易度', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_levels[$omochix_level]); ?></dd></div><?php endif; ?>
        <?php if ($omochix_minutes > 0) : ?><div><dt><?php esc_html_e('目安', 'omochix'); ?></dt><dd><?php echo esc_html(sprintf(__('%d分', 'omochix'), $omochix_minutes)); ?></dd></div><?php endif; ?>
        <?php if ('' !== $omochix_amount) : ?><div><dt><?php esc_html_e('費用', 'omochix'); ?></dt><dd><?php echo esc_html($omochix_amount); ?></dd></div><?php endif; ?>
    </dl>
    <?php if ($omochix_found) : ?>
        <ul class="ih-evidence" aria-label="<?php esc_attr_e('成果の根拠', 'omochix'); ?>">
            <?php foreach ($omochix_found as $omochix_key) : if (!isset($omochix_ev[$omochix_key])) { continue; } ?>
                <li class="ih-evidence__item ih-evidence__item--<?php echo esc_attr($omochix_key); ?>"><?php echo esc_html($omochix_ev[$omochix_key]); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>
