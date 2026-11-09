<?php
/**
 * External content card (from the private ledger): OmochiX's own summary,
 * the provider, the license basis and an outbound link. PR content is
 * labelled and linked with rel="sponsored".
 *
 * Args: card (array from omochix_core_get_resource_card()).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_card = isset($args['card']) && is_array($args['card']) ? $args['card'] : null;
if (!$omochix_card || '' === (string) ($omochix_card['url'] ?? '')) {
    return;
}
?>
<article class="ih-card ih-card--resource">
    <div class="ih-card__meta">
        <span class="ih-card__type"><?php esc_html_e('外部コンテンツ', 'omochix'); ?></span>
        <?php get_template_part('template-parts/learn-industries/provider-badge', null, ['type' => (string) $omochix_card['provider_type']]); ?>
        <?php if (!empty($omochix_card['is_pr']) && 'partner' !== $omochix_card['provider_type']) : ?><span class="ih-badge ih-badge--partner"><?php esc_html_e('PR', 'omochix'); ?></span><?php endif; ?>
    </div>
    <h3 class="ih-card__title">
        <a href="<?php echo esc_url($omochix_card['url']); ?>" rel="<?php echo esc_attr((string) $omochix_card['rel']); ?> external" target="_blank"><?php echo esc_html((string) $omochix_card['title']); ?><span class="screen-reader-text"><?php esc_html_e('（外部サイト・新しいタブで開きます）', 'omochix'); ?></span><span class="ih-card__external" aria-hidden="true">↗</span></a>
    </h3>
    <?php if ('' !== (string) $omochix_card['summary']) : ?><p class="ih-card__text"><?php echo esc_html((string) $omochix_card['summary']); ?></p><?php endif; ?>
    <p class="ih-card__note">
        <?php if ('' !== (string) $omochix_card['provider_name']) : ?><?php echo esc_html(sprintf(__('提供：%s', 'omochix'), (string) $omochix_card['provider_name'])); ?><?php endif; ?>
        <?php if ('' !== (string) $omochix_card['license_label']) : ?> ／ <?php echo esc_html((string) $omochix_card['license_label']); ?><?php endif; ?>
        <?php if ('' !== (string) $omochix_card['verified_at']) : ?> ／ <?php echo esc_html(sprintf(__('確認日 %s', 'omochix'), (string) $omochix_card['verified_at'])); ?><?php endif; ?>
        <?php if (!empty($omochix_card['stale'])) : ?> ／ <strong><?php esc_html_e('要再確認', 'omochix'); ?></strong><?php endif; ?>
    </p>
</article>
