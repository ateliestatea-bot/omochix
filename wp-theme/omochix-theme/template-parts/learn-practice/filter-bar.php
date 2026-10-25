<?php
/**
 * Practice list filter bar: field chips, format segments, level / AI / tech /
 * usecase selects, keyword, reset and result count. A plain GET form; JS only
 * auto-submits selects and hides the submit button.
 *
 * Args:
 *   filters (array) Sanitized filters. Required.
 *   total   (int)   Number of matching items.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_filters = isset($args['filters']) && is_array($args['filters']) ? $args['filters'] : omochix_learn_practice_sanitize_filters([]);
$omochix_total   = isset($args['total']) ? (int) $args['total'] : 0;
$omochix_list    = omochix_learn_practice_url();
$omochix_other   = array_diff_key($omochix_filters, ['field' => true]);
$omochix_fields  = omochix_learn_practice_fields(false);
$omochix_ai      = omochix_learn_practice_terms('learn_ai');
$omochix_tech    = omochix_learn_practice_terms('learn_tech');
$omochix_usecase = omochix_learn_practice_terms('learn_usecase');
?>
<form class="practice-filter" method="get" action="<?php echo esc_url($omochix_list); ?>" data-practice-filter aria-label="<?php esc_attr_e('教材の絞り込み', 'omochix'); ?>">
    <div class="practice-filter__chips" role="group" aria-label="<?php esc_attr_e('分野', 'omochix'); ?>">
        <a class="practice-filter__chip<?php echo '' === $omochix_filters['field'] ? ' is-active' : ''; ?>" href="<?php echo esc_url(omochix_learn_practice_url($omochix_other)); ?>"<?php echo '' === $omochix_filters['field'] ? ' aria-current="true"' : ''; ?>><?php esc_html_e('すべて', 'omochix'); ?></a>
        <?php foreach ($omochix_fields as $omochix_slug => $omochix_term) : ?>
            <?php $omochix_active = $omochix_filters['field'] === $omochix_slug; ?>
            <a class="practice-filter__chip<?php echo $omochix_active ? ' is-active' : ''; ?>" href="<?php echo esc_url(omochix_learn_practice_url(array_merge($omochix_other, ['field' => $omochix_slug]))); ?>"<?php echo $omochix_active ? ' aria-current="true"' : ''; ?>><?php echo esc_html($omochix_term->name); ?></a>
        <?php endforeach; ?>
    </div>
    <input type="hidden" name="field" value="<?php echo esc_attr($omochix_filters['field']); ?>">
    <div class="practice-filter__formats" role="group" aria-label="<?php esc_attr_e('形式', 'omochix'); ?>">
        <?php $omochix_other_f = array_diff_key($omochix_filters, ['format' => true]); ?>
        <a class="practice-filter__segment<?php echo '' === $omochix_filters['format'] ? ' is-active' : ''; ?>" href="<?php echo esc_url(omochix_learn_practice_url($omochix_other_f)); ?>"<?php echo '' === $omochix_filters['format'] ? ' aria-current="true"' : ''; ?>><?php esc_html_e('すべて', 'omochix'); ?></a>
        <?php foreach (omochix_learn_practice_formats() as $omochix_slug => $omochix_label) : ?>
            <?php $omochix_active = $omochix_filters['format'] === $omochix_slug; ?>
            <a class="practice-filter__segment<?php echo $omochix_active ? ' is-active' : ''; ?>" href="<?php echo esc_url(omochix_learn_practice_url(array_merge($omochix_other_f, ['format' => $omochix_slug]))); ?>"<?php echo $omochix_active ? ' aria-current="true"' : ''; ?>><?php echo esc_html($omochix_label); ?></a>
        <?php endforeach; ?>
    </div>
    <input type="hidden" name="format" value="<?php echo esc_attr($omochix_filters['format']); ?>">
    <div class="practice-filter__row">
        <label class="practice-filter__search">
            <span class="sr-only"><?php esc_html_e('キーワードで探す', 'omochix'); ?></span>
            <input type="search" name="q" value="<?php echo esc_attr($omochix_filters['q']); ?>" placeholder="<?php esc_attr_e('作りたいもの・技術で探す', 'omochix'); ?>" maxlength="<?php echo (int) OMOCHIX_LEARN_PRACTICE_Q_MAX; ?>" autocomplete="off">
        </label>
        <label class="practice-filter__select"><span><?php esc_html_e('難易度', 'omochix'); ?></span>
            <select name="level" data-practice-auto>
                <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                <?php foreach (omochix_learn_practice_levels() as $omochix_value => $omochix_label) : ?><option value="<?php echo esc_attr($omochix_value); ?>" <?php selected($omochix_filters['level'], $omochix_value); ?>><?php echo esc_html($omochix_label); ?></option><?php endforeach; ?>
            </select>
        </label>
        <?php if ($omochix_ai) : ?>
            <label class="practice-filter__select"><span><?php esc_html_e('対応AI', 'omochix'); ?></span>
                <select name="ai" data-practice-auto>
                    <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                    <?php foreach ($omochix_ai as $omochix_slug => $omochix_term) : ?><option value="<?php echo esc_attr($omochix_slug); ?>" <?php selected($omochix_filters['ai'], $omochix_slug); ?>><?php echo esc_html($omochix_term->name); ?></option><?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($omochix_tech) : ?>
            <label class="practice-filter__select"><span><?php esc_html_e('技術', 'omochix'); ?></span>
                <select name="tech" data-practice-auto>
                    <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                    <?php foreach ($omochix_tech as $omochix_slug => $omochix_term) : ?><option value="<?php echo esc_attr($omochix_slug); ?>" <?php selected($omochix_filters['tech'], $omochix_slug); ?>><?php echo esc_html($omochix_term->name); ?></option><?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if ($omochix_usecase) : ?>
            <label class="practice-filter__select"><span><?php esc_html_e('用途', 'omochix'); ?></span>
                <select name="usecase" data-practice-auto>
                    <option value=""><?php esc_html_e('すべて', 'omochix'); ?></option>
                    <?php foreach ($omochix_usecase as $omochix_slug => $omochix_term) : ?><option value="<?php echo esc_attr($omochix_slug); ?>" <?php selected($omochix_filters['usecase'], $omochix_slug); ?>><?php echo esc_html($omochix_term->name); ?></option><?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <button type="submit" class="practice-filter__submit" data-practice-nojs><?php esc_html_e('絞り込む', 'omochix'); ?></button>
        <a class="practice-filter__reset" href="<?php echo esc_url($omochix_list); ?>" data-practice-reset><?php esc_html_e('リセット', 'omochix'); ?></a>
    </div>
    <p class="practice-filter__count" aria-live="polite"><?php echo esc_html(sprintf(__('%s件の教材', 'omochix'), number_format_i18n($omochix_total))); ?></p>
</form>
