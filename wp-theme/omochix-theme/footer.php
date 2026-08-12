<?php
/**
 * Site footer.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_footer_posts_page_id = (int) get_option('page_for_posts');
$omochix_footer_posts_page    = $omochix_footer_posts_page_id ? get_post($omochix_footer_posts_page_id) : null;
// Public navigation must contain published destinations only, preventing links to drafts or missing pages.
$omochix_footer_links = [
    __('メニュー', 'omochix') => [
        __('AIツール', 'omochix')  => get_post_type_archive_link('ai_tool'),
        __('AIニュース', 'omochix') => $omochix_footer_posts_page instanceof WP_Post && 'publish' === $omochix_footer_posts_page->post_status ? get_permalink($omochix_footer_posts_page) : '',
        __('カテゴリー', 'omochix')  => omochix_get_published_page_url('category'),
        __('About', 'omochix')     => omochix_get_published_page_url('about'),
    ],
    __('サポート', 'omochix') => [
        __('お問い合わせ', 'omochix')       => omochix_get_published_page_url('contact'),
        __('プライバシーポリシー', 'omochix') => get_privacy_policy_url(),
        __('利用規約', 'omochix')         => omochix_get_published_page_url('terms'),
        __('運営者情報', 'omochix')        => omochix_get_published_page_url('company'),
    ],
];
$omochix_footer_links = array_filter(array_map('array_filter', $omochix_footer_links));
?>

<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__brand">
            <a class="site-footer__logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('OmochiX ホーム', 'omochix'); ?>">
                <img src="<?php echo esc_url(get_theme_file_uri('/assets/img/logo-lockup-horizontal.svg')); ?>" width="142" height="36" alt="OmochiX">
            </a>
            <p><?php echo esc_html(get_bloginfo('description') ?: __('AIで迷ったら、まずOmochiX。', 'omochix')); ?></p>
        </div>

        <nav class="site-footer__navigation" aria-label="<?php esc_attr_e('フッターナビゲーション', 'omochix'); ?>">
            <?php foreach ($omochix_footer_links as $omochix_group_title => $omochix_links) : ?>
                <div class="site-footer__group">
                    <h2><?php echo esc_html($omochix_group_title); ?></h2>
                    <ul>
                        <?php foreach ($omochix_links as $omochix_link_label => $omochix_link_url) : ?>
                            <li><a href="<?php echo esc_url($omochix_link_url); ?>"><?php echo esc_html($omochix_link_label); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>

        <?php // SNS links remain hidden until official account URLs are confirmed. ?>

        <div class="site-footer__bottom">
            <p>&copy; <?php echo esc_html(wp_date('Y')); ?> OmochiX</p>
            <p><?php esc_html_e('AIの情報を、もっとシンプルに。', 'omochix'); ?></p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
