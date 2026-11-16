<?php
/**
 * Design Library card: poster (AVIF, WebP fallback) with an optional short
 * loop the list script starts only when allowed (D0 §2.1). Without
 * JavaScript the card is the poster plus links. Same card for Web and video
 * works; portrait videos are letterboxed in the 16:10 box.
 *
 * @var array $args {item: array, hidden: bool, eager: bool}
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omx_item     = $args['item'];
$omx_url      = omochix_design_item_url($omx_item);
$omx_mediums  = omochix_design_mediums();
$omx_uc       = omochix_design_use_cases();
$omx_mt       = omochix_design_motion_types();
$omx_ind      = omochix_design_item_industries($omx_item);
$omx_demo     = omochix_design_demo_allowed($omx_item) ? omochix_design_demo_url($omx_item) : '';
$omx_title_id = 'dl-card-' . $omx_item['slug'];
$omx_portrait = 'video' === $omx_item['medium'] && 'portrait' === ($omx_item['preview']['orientation'] ?? '');
?>
<li class="dl-card<?php echo $omx_portrait ? ' dl-card--portrait' : ''; ?>" data-dl-card
    data-medium="<?php echo esc_attr($omx_item['medium']); ?>"
    data-use-case="<?php echo esc_attr(implode(' ', $omx_item['use_cases'])); ?>"
    data-motion-type="<?php echo esc_attr(implode(' ', $omx_item['motion_types'])); ?>"
    data-technology="<?php echo esc_attr(implode(' ', $omx_item['technologies'])); ?>"
    data-industry="<?php echo esc_attr(implode(' ', array_keys($omx_ind))); ?>"
    data-search="<?php echo esc_attr(omochix_design_search_text($omx_item)); ?>"
    <?php echo !empty($args['hidden']) ? 'hidden' : ''; ?>>
    <article aria-labelledby="<?php echo esc_attr($omx_title_id); ?>">
        <div class="dl-card__media" data-dl-media>
            <picture>
                <source type="image/avif" srcset="<?php echo esc_url(omochix_design_asset_url($omx_item['poster'])); ?>">
                <img src="<?php echo esc_url(omochix_design_asset_url($omx_item['posterFallback'] ?? $omx_item['poster'])); ?>" width="1280" height="800" alt=""
                    <?php echo !empty($args['eager']) ? 'fetchpriority="high"' : 'loading="lazy"'; ?> decoding="async">
            </picture>
            <?php if (!empty($omx_item['loop'])) : ?>
                <video class="dl-card__video" data-dl-video muted playsinline loop preload="none" aria-hidden="true" tabindex="-1" disablepictureinpicture
                    data-webm="<?php echo esc_url(omochix_design_asset_url($omx_item['loop'])); ?>"
                    <?php if (!empty($omx_item['loopFallback'])) : ?>data-mp4="<?php echo esc_url(omochix_design_asset_url($omx_item['loopFallback'])); ?>"<?php endif; ?>></video>
                <button type="button" class="dl-card__play" data-dl-play aria-pressed="false" hidden>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path class="dl-i-play" d="M8 5v14l11-7z"/><path class="dl-i-pause" d="M7 5h4v14H7zM13 5h4v14h-4z"/></svg>
                    <span class="sr-only"><?php echo esc_html(sprintf(__('%sのプレビューを再生', 'omochix'), $omx_item['title'])); ?></span>
                </button>
            <?php endif; ?>
            <span class="dl-card__medium"><?php echo esc_html($omx_mediums[$omx_item['medium']]['label']); ?></span>
        </div>
        <div class="dl-card__body">
            <h3 class="dl-card__title" id="<?php echo esc_attr($omx_title_id); ?>"><a href="<?php echo esc_url($omx_url); ?>"><?php echo esc_html($omx_item['title']); ?></a></h3>
            <p class="dl-card__summary"><?php echo esc_html($omx_item['summary']); ?></p>
            <ul class="dl-tags" aria-label="<?php esc_attr_e('用途と表現', 'omochix'); ?>">
                <?php foreach ($omx_item['use_cases'] as $omx_v) : ?><li class="dl-tag"><?php echo esc_html($omx_uc[$omx_v]); ?></li><?php endforeach; ?>
                <?php foreach ($omx_item['motion_types'] as $omx_v) : ?><li class="dl-tag dl-tag--motion"><?php echo esc_html($omx_mt[$omx_v]); ?></li><?php endforeach; ?>
            </ul>
            <div class="dl-card__actions">
                <a class="dl-btn dl-btn--primary" href="<?php echo esc_url($omx_url); ?>" aria-describedby="<?php echo esc_attr($omx_title_id); ?>"><?php esc_html_e('詳細を見る', 'omochix'); ?></a>
                <?php if ('' !== $omx_demo) : ?>
                    <a class="dl-btn" href="<?php echo esc_url($omx_demo); ?>" target="_blank" rel="noopener noreferrer" aria-describedby="<?php echo esc_attr($omx_title_id); ?>"><?php esc_html_e('デモを開く', 'omochix'); ?><span class="sr-only"><?php esc_html_e('（新しいタブ）', 'omochix'); ?></span></a>
                <?php endif; ?>
            </div>
        </div>
    </article>
</li>
