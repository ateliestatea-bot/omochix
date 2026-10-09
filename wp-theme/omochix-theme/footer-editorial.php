<?php
/** Homepage footer: reuse verified social destinations and published pages only. */
if (!defined('ABSPATH')) { exit; }
$links = omochix_editorial_destinations();
foreach (['about' => 'About', 'contact' => __('お問い合わせ', 'omochix')] as $slug => $label) {
    $links[] = ['label' => $label, 'url' => omochix_get_published_page_url($slug)];
}
$legal = [__('プライバシーポリシー', 'omochix') => get_privacy_policy_url() ?: omochix_get_published_page_url('privacy-policy'), __('利用規約', 'omochix') => omochix_get_published_page_url('terms'), __('運営者情報', 'omochix') => omochix_get_published_page_url('company')];
?>
<footer class="ed-footer">
    <div class="ed-footer-top">
        <a href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('OmochiX ホーム', 'omochix'); ?>"><img src="<?php echo esc_url(get_theme_file_uri('/assets/img/logo-lockup-horizontal-dark.svg')); ?>" width="132" height="34" alt="OmochiX"></a>
        <?php get_template_part('template-parts/social-links'); ?>
    </div>
    <nav class="ed-footer-nav" aria-label="<?php esc_attr_e('フッターナビゲーション', 'omochix'); ?>"><?php foreach ($links as $item) : if (!$item['url'] || is_wp_error($item['url'])) { continue; } ?><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a><?php endforeach; ?></nav>
    <div class="ed-footer-future"><?php foreach (['videos' => __('動画', 'omochix'), 'community' => __('コミュニティ', 'omochix')] as $slug => $label) : $url = omochix_get_published_page_url($slug); ?><?php if ($url) : ?><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a><?php else : ?><span><?php echo esc_html($label); ?> <small><?php esc_html_e('準備中', 'omochix'); ?></small></span><?php endif; ?><?php endforeach; ?></div>
    <div class="ed-footer-bottom"><span>© <?php echo esc_html(wp_date('Y')); ?> OmochiX</span><div><?php foreach ($legal as $label => $url) : if (!$url) { continue; } ?><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?></div></div>
</footer>
<?php wp_footer(); ?>
</body></html>
