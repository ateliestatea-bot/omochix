<?php
/**
 * Site header.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f7f5">
    <link rel="manifest" href="<?php echo esc_url(get_theme_file_uri('/manifest.webmanifest')); ?>">
    <?php if (!has_site_icon()) : ?>
        <link rel="icon" href="<?php echo esc_url(get_theme_file_uri('/assets/img/favicon.png')); ?>" sizes="512x512" type="image/png">
        <link rel="apple-touch-icon" href="<?php echo esc_url(get_theme_file_uri('/assets/img/apple-touch-icon.png')); ?>" sizes="180x180">
    <?php endif; ?>
    <script>
        /* Apply the saved theme before paint to avoid a flash. */
        (function () {
            try {
                var savedTheme = localStorage.getItem('omochix-theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.dataset.theme = savedTheme || (prefersDark ? 'dark' : 'light');
            } catch (error) {}
        }());
    </script>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#main-content"><?php esc_html_e('本文へ移動', 'omochix'); ?></a>

<header class="site-header" data-site-header>
    <div class="site-header__inner">
        <a class="site-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('OmochiX ホーム', 'omochix'); ?>">
            <img class="site-logo__image" src="<?php echo esc_url(get_theme_file_uri('/assets/img/logo-lockup-horizontal.svg')); ?>" width="142" height="36" alt="OmochiX">
        </a>

        <button class="icon-button site-header__menu-button" type="button" aria-expanded="false" aria-controls="primary-navigation" data-menu-toggle>
            <span class="sr-only"><?php esc_html_e('メニューを開く', 'omochix'); ?></span>
            <span class="menu-icon" aria-hidden="true"><i></i><i></i></span>
        </button>

        <nav class="primary-nav" id="primary-navigation" aria-label="<?php esc_attr_e('メインナビゲーション', 'omochix'); ?>" data-primary-nav>
            <?php if (has_nav_menu('primary')) : ?>
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'primary-nav__list',
                    'fallback_cb'    => false,
                    'depth'          => 1,
                ]);
                ?>
            <?php else : ?>
                <ul class="primary-nav__list">
                    <?php
                    $omochix_posts_page_id = (int) get_option('page_for_posts');
                    $omochix_posts_page    = $omochix_posts_page_id ? get_post($omochix_posts_page_id) : null;
                    $omochix_default_menu = [
                        __('AIニュース', 'omochix') => $omochix_posts_page instanceof WP_Post && 'publish' === $omochix_posts_page->post_status ? get_permalink($omochix_posts_page) : '',
                        __('AIツール', 'omochix')   => get_post_type_archive_link('ai_tool'),
                        __('動画', 'omochix')      => omochix_get_published_page_url('videos'),
                        __('コミュニティ', 'omochix') => omochix_get_published_page_url('community'),
                        __('About', 'omochix')     => omochix_get_published_page_url('about'),
                    ];
                    foreach ($omochix_default_menu as $omochix_label => $omochix_url) :
                        ?>
                        <?php if ($omochix_url) : ?>
                            <li><a href="<?php echo esc_url($omochix_url); ?>"><?php echo esc_html($omochix_label); ?></a></li>
                        <?php else : ?>
                            <li class="primary-nav__item--unavailable"><span aria-disabled="true"><?php echo esc_html($omochix_label); ?><small><?php esc_html_e('準備中', 'omochix'); ?></small></span></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="primary-nav__mobile-footer">
                <ul class="primary-nav__mobile-links">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                    <?php $omochix_contact_url = omochix_get_published_page_url('contact'); ?>
                    <?php if ($omochix_contact_url) : ?>
                        <li><a href="<?php echo esc_url($omochix_contact_url); ?>"><?php esc_html_e('お問い合わせ', 'omochix'); ?></a></li>
                    <?php else : ?>
                        <li><span aria-disabled="true"><?php esc_html_e('お問い合わせ', 'omochix'); ?><small><?php esc_html_e('準備中', 'omochix'); ?></small></span></li>
                    <?php endif; ?>
                </ul>
                <a class="primary-nav__login" href="<?php echo esc_url(wp_login_url()); ?>"><?php esc_html_e('ログイン', 'omochix'); ?></a>
            </div>
        </nav>

        <div class="site-header__actions">
            <button class="icon-button" type="button" data-theme-toggle aria-label="<?php esc_attr_e('ダークモードに切り替える', 'omochix'); ?>">
                <svg class="icon icon--sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"/></svg>
                <svg class="icon icon--moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 15.2A8.5 8.5 0 0 1 8.8 4a8.5 8.5 0 1 0 11.2 11.2Z"/></svg>
            </button>
            <button class="icon-button" type="button" data-search-open aria-haspopup="dialog" aria-controls="site-search" aria-label="<?php esc_attr_e('サイト内を検索', 'omochix'); ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.2 4.2"/></svg>
            </button>
            <a class="login-button" href="<?php echo esc_url(wp_login_url()); ?>"><?php esc_html_e('ログイン', 'omochix'); ?></a>
        </div>
    </div>
</header>

<dialog class="search-dialog" id="site-search" data-search-dialog aria-labelledby="search-title">
    <div class="search-dialog__inner">
        <div class="search-dialog__heading">
            <h2 id="search-title"><?php esc_html_e('OmochiXを検索', 'omochix'); ?></h2>
            <button class="icon-button" type="button" data-search-close aria-label="<?php esc_attr_e('検索を閉じる', 'omochix'); ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 5 14 14M19 5 5 19"/></svg>
            </button>
        </div>
        <form class="search-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="sr-only" for="site-search-input"><?php esc_html_e('検索キーワード', 'omochix'); ?></label>
            <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.2 4.2"/></svg>
            <input id="site-search-input" name="s" type="search" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="<?php esc_attr_e('記事、ツール、トピックを検索', 'omochix'); ?>" autocomplete="off">
            <button type="submit"><?php esc_html_e('検索', 'omochix'); ?></button>
        </form>
        <p class="search-dialog__hint"><kbd>Esc</kbd> <?php esc_html_e('で閉じる', 'omochix'); ?></p>
    </div>
</dialog>
