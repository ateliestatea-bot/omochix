<?php
/** Homepage footer: reuse verified social destinations and published pages only. */
if (!defined('ABSPATH')) { exit; }
$links = array_map(static function ($it) { return ['label' => $it['label'], 'url' => $it['url']]; }, array_filter(omochix_nav_items('footer'), static function ($it) { return !empty($it['url']); }));
/** Lets other templates add links (used by the shared shell for the legacy "カテゴリー" page link). */
$links = apply_filters('omochix_editorial_footer_links', $links);
$legal = [__('プライバシーポリシー', 'omochix') => get_privacy_policy_url() ?: omochix_get_published_page_url('privacy-policy'), __('利用規約', 'omochix') => omochix_get_published_page_url('terms'), __('運営者情報', 'omochix') => omochix_get_published_page_url('company')];
?>
<footer class="ed-footer">
    <div class="ed-footer-top">
        <a href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('OmochiX ホーム', 'omochix'); ?>"><img src="<?php echo esc_url(get_theme_file_uri('/assets/img/logo-lockup-horizontal-dark.svg')); ?>" width="132" height="34" alt="OmochiX"></a>
        <?php get_template_part('template-parts/social-links'); ?>
    </div>
    <nav class="ed-footer-nav" aria-label="<?php esc_attr_e('フッターナビゲーション', 'omochix'); ?>"><?php foreach ($links as $item) : if (!$item['url'] || is_wp_error($item['url'])) { continue; } ?><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a><?php endforeach; ?></nav>
    <?php $soon = omochix_nav_items('soon'); if ($soon) : ?><div class="ed-footer-future"><?php foreach ($soon as $it) : ?><span><?php echo esc_html($it['label']); ?> <small><?php esc_html_e('準備中', 'omochix'); ?></small></span><?php endforeach; ?></div><?php endif; ?>
    <div class="ed-footer-bottom"><p class="ed-footer-copy">© <?php echo esc_html(wp_date('Y')); ?> OmochiX</p><nav class="ed-footer-legal" aria-label="<?php esc_attr_e('法務・運営情報', 'omochix'); ?>"><?php foreach ($legal as $label => $url) : if (!$url) { continue; } ?><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?></nav></div>
</footer>
<?php wp_footer(); ?>
</body></html>
