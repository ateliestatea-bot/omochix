<?php
/**
 * Composer slot: an empty placeholder that learn-composer.js fills with a
 * sample (preview cloned from the picker, order controls, remove).
 *
 * Args:
 *   index (int) 0-based slot position.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_index = isset($args['index']) ? (int) $args['index'] : 0;
?>
<li class="motion-composer__slot is-empty" data-composer-slot="<?php echo esc_attr((string) $omochix_index); ?>" data-motion-detail data-omx-params-host>
    <div class="motion-composer__slot-empty">
        <span class="motion-composer__slot-number" aria-hidden="true"><?php echo esc_html((string) ($omochix_index + 1)); ?></span>
        <button type="button" class="motion-composer__pick" data-composer-pick aria-haspopup="dialog"><?php esc_html_e('見本を選ぶ', 'omochix'); ?></button>
    </div>
    <div class="motion-composer__slot-body" data-composer-slot-body hidden>
        <header class="motion-composer__slot-head">
            <span class="motion-composer__slot-number" data-composer-order aria-hidden="true"><?php echo esc_html((string) ($omochix_index + 1)); ?></span>
            <div class="motion-composer__slot-name">
                <strong data-composer-name></strong>
                <span class="motion-composer__slot-en" lang="en" data-composer-en></span>
                <span class="motion-composer__badge" data-composer-adjusted hidden></span>
            </div>
            <div class="motion-composer__slot-controls">
                <button type="button" class="motion-composer__move" data-composer-up aria-label="<?php esc_attr_e('ひとつ前へ', 'omochix'); ?>">↑</button>
                <button type="button" class="motion-composer__move" data-composer-down aria-label="<?php esc_attr_e('ひとつ後ろへ', 'omochix'); ?>">↓</button>
                <button type="button" class="motion-composer__remove" data-composer-remove><?php esc_html_e('外す', 'omochix'); ?></button>
            </div>
        </header>
        <div class="motion-composer__slot-preview" data-composer-preview></div>
        <p class="motion-composer__slot-summary" data-composer-summary></p>
        <div class="motion-composer__slot-foot">
            <button type="button" class="motion-copy motion-copy--ghost motion-composer__adjust" data-composer-adjust aria-expanded="false"><?php esc_html_e('調整する', 'omochix'); ?></button>
            <a class="motion-composer__slot-link" data-composer-link href="#"><?php esc_html_e('見本の詳細を見る', 'omochix'); ?></a>
        </div>
        <div class="motion-composer__panel" data-composer-panel hidden aria-busy="false"></div>
    </div>
</li>
