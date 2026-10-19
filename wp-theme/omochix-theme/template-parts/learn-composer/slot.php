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
            <button type="button" class="motion-copy motion-copy--ghost motion-composer__notes-toggle" data-composer-notes-toggle aria-expanded="false"><?php esc_html_e('用途', 'omochix'); ?></button>
            <a class="motion-composer__slot-link" data-composer-link href="#"><?php esc_html_e('見本の詳細を見る', 'omochix'); ?></a>
        </div>
        <div class="motion-composer__panel" data-composer-panel hidden aria-busy="false"></div>
        <?php $omochix_presets = omochix_learn_composer_presets(); ?>
        <div class="motion-composer__notes" data-composer-notes hidden>
            <p class="motion-composer__notes-hint" id="composer-notes-hint-<?php echo esc_attr((string) $omochix_index); ?>"><?php esc_html_e('AIへの指示書にだけ使われます（プレビューやコードには入りません）。', 'omochix'); ?></p>
            <?php foreach (['target' => __('適用対象', 'omochix'), 'intent' => __('意図', 'omochix')] as $omochix_kind => $omochix_kind_label) : ?>
                <div class="motion-composer__note" data-composer-note="<?php echo esc_attr($omochix_kind); ?>">
                    <label class="motion-composer__note-label" for="composer-<?php echo esc_attr($omochix_kind); ?>-<?php echo esc_attr((string) $omochix_index); ?>"><?php echo esc_html($omochix_kind_label); ?></label>
                    <select id="composer-<?php echo esc_attr($omochix_kind); ?>-<?php echo esc_attr((string) $omochix_index); ?>" data-composer-note-select aria-describedby="composer-notes-hint-<?php echo esc_attr((string) $omochix_index); ?>">
                        <option value=""><?php esc_html_e('（未指定）', 'omochix'); ?></option>
                        <?php foreach ($omochix_presets[$omochix_kind] as $omochix_key => $omochix_label) : ?>
                            <option value="<?php echo esc_attr($omochix_key); ?>"><?php echo esc_html($omochix_label); ?></option>
                        <?php endforeach; ?>
                        <option value="__custom"><?php esc_html_e('自由入力…', 'omochix'); ?></option>
                    </select>
                    <input type="text" class="motion-composer__note-input" data-composer-note-input maxlength="<?php echo (int) OMOCHIX_LEARN_COMPOSER_NOTE_MAX; ?>" hidden aria-describedby="composer-notes-hint-<?php echo esc_attr((string) $omochix_index); ?>" aria-label="<?php echo esc_attr($omochix_kind_label); ?>（<?php esc_attr_e('自由入力', 'omochix'); ?>）" placeholder="<?php esc_attr_e('40文字まで', 'omochix'); ?>">
                    <span class="motion-composer__note-warn" data-composer-note-warn role="status" hidden><?php esc_html_e('使えない文字があります。文字・数字・空白と . , / \' " & ( ) ! ? : - _ 、40文字まで。', 'omochix'); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</li>
