<?php
/**
 * Filter form (plain GET, works without JavaScript). Every control offers
 * only valid values, so normal use never produces a 400.
 *
 * Args: context ('hub'|'index'), term (WP_Term|null), filters (array), action (URL), total (int|null).
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_ctx     = isset($args['context']) && 'index' === $args['context'] ? 'index' : 'hub';
$omochix_term    = $args['term'] ?? null;
$omochix_filters = (array) ($args['filters'] ?? []);
$omochix_action  = (string) ($args['action'] ?? '');
$omochix_total   = $args['total'] ?? null;
$omochix_open    = function_exists('omochix_industry_hub_is_filtered') && omochix_industry_hub_is_filtered($omochix_filters);
$omochix_id      = 'ih-filter-' . $omochix_ctx;
$omochix_purpose = get_terms(['taxonomy' => 'omx_purpose', 'hide_empty' => false]);
$omochix_select  = static function ($name, $label, array $options, $current) use ($omochix_id) {
    $id = $omochix_id . '-' . $name;
    echo '<div class="ih-filter__field"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label><select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"><option value="">' . esc_html__('すべて', 'omochix') . '</option>';
    foreach ($options as $value => $text) {
        echo '<option value="' . esc_attr((string) $value) . '"' . selected((string) $value, (string) $current, false) . '>' . esc_html((string) $text) . '</option>';
    }
    echo '</select></div>';
};
$omochix_purposes = [];
foreach (is_array($omochix_purpose) ? $omochix_purpose : [] as $omochix_t) {
    $omochix_purposes[$omochix_t->slug] = $omochix_t->name;
}
$omochix_types = array_map(static fn($t) => $t['label'], omochix_industry_hub_types());
?>
<details class="ih-filter"<?php echo $omochix_open ? ' open' : ''; ?>>
    <summary class="ih-filter__summary"><?php esc_html_e('条件で絞り込む', 'omochix'); ?></summary>
    <form class="ih-filter__form" method="get" action="<?php echo esc_url($omochix_action); ?>" role="search" aria-label="<?php esc_attr_e('関連コンテンツの絞り込み', 'omochix'); ?>">
        <div class="ih-filter__field ih-filter__field--q">
            <label for="<?php echo esc_attr($omochix_id); ?>-q"><?php esc_html_e('キーワード', 'omochix'); ?></label>
            <input type="search" id="<?php echo esc_attr($omochix_id); ?>-q" name="q" value="<?php echo esc_attr((string) ($omochix_filters['q'] ?? '')); ?>" maxlength="<?php echo (int) OMOCHIX_INDUSTRY_HUB_Q_MAX; ?>" autocomplete="off">
        </div>
        <?php
        $omochix_select('purpose', __('業務目的', 'omochix'), $omochix_purposes, $omochix_filters['purpose'] ?? '');
        $omochix_select('type', __('種類', 'omochix'), $omochix_types, $omochix_filters['type'] ?? '');
        if ('hub' === $omochix_ctx) {
            $omochix_select('level', __('難易度（教材）', 'omochix'), omochix_industry_hub_levels(), $omochix_filters['level'] ?? '');
            $omochix_providers = function_exists('omochix_core_get_provider_vocabulary') ? omochix_core_get_provider_vocabulary() : [];
            $omochix_select('provider', __('提供元', 'omochix'), $omochix_providers, $omochix_filters['provider'] ?? '');
            $omochix_subs = [];
            if ($omochix_term instanceof WP_Term) {
                foreach ((array) get_terms(['taxonomy' => 'omx_industry', 'parent' => (int) $omochix_term->term_id, 'hide_empty' => false]) as $omochix_s) {
                    if ($omochix_s instanceof WP_Term) {
                        $omochix_subs[$omochix_s->slug] = $omochix_s->name;
                    }
                }
            }
            if ($omochix_subs) {
                $omochix_select('sub', __('サブ業種', 'omochix'), $omochix_subs, $omochix_filters['sub'] ?? '');
            }
        } else {
            $omochix_inds = [];
            foreach (omochix_core_get_industries_with_visibility(true) as $omochix_row) {
                $omochix_inds[$omochix_row['term']->slug] = $omochix_row['term']->name;
            }
            $omochix_select('industry', __('業種', 'omochix'), $omochix_inds, $omochix_filters['industry'] ?? '');
        }
        ?>
        <div class="ih-filter__actions">
            <button type="submit" class="ih-button"><?php esc_html_e('この条件で表示', 'omochix'); ?></button>
            <?php if ($omochix_open) : ?><a class="ih-link" href="<?php echo esc_url($omochix_action); ?>"><?php esc_html_e('条件をすべて外す', 'omochix'); ?></a><?php endif; ?>
        </div>
    </form>
</details>
<?php if (null !== $omochix_total) : ?>
    <p class="ih-count" role="status" aria-live="polite"><?php echo esc_html(sprintf(__('%d件', 'omochix'), (int) $omochix_total)); ?></p>
<?php endif; ?>
