<?php
/**
 * Not found template.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$omochix_posts_page_id = (int) get_option('page_for_posts');
$omochix_posts_page    = $omochix_posts_page_id ? get_post($omochix_posts_page_id) : null;
$omochix_news_url      = $omochix_posts_page instanceof WP_Post && 'publish' === $omochix_posts_page->post_status
    ? get_permalink($omochix_posts_page)
    : add_query_arg(['s' => __('AIニュース', 'omochix'), 'content_type' => 'news'], home_url('/'));
$omochix_tools_url     = get_post_type_archive_link('ai_tool') ?: add_query_arg(['s' => __('AIツール', 'omochix'), 'content_type' => 'tools'], home_url('/'));
?>

<main class="search-page" id="main-content">
    <header class="search-page__hero">
        <div class="search-page__container not-found-hero__layout">
            <div class="search-page__hero-copy">
                <p class="search-page__eyebrow"><?php esc_html_e('404 · PAGE NOT FOUND', 'omochix'); ?></p>
                <h1><span aria-hidden="true">404</span><?php esc_html_e('ページが見つかりません。', 'omochix'); ?></h1>
                <p><?php esc_html_e('URLが変更されたか、ページがまだ公開されていない可能性があります。', 'omochix'); ?></p>
            </div>
            <div class="not-found-hero__mascot" aria-hidden="true">
                <?php if (file_exists(get_theme_file_path('/assets/img/omochi-hero.webp'))) : ?>
                    <img src="<?php echo esc_url(get_theme_file_uri('/assets/img/omochi-hero.webp')); ?>" width="300" height="350" alt="" decoding="async">
                <?php else : ?>
                    <span>?</span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="search-results" aria-labelledby="not-found-guide-title">
        <div class="search-page__container">
            <div class="search-empty">
                <span aria-hidden="true">O</span>
                <h2 id="not-found-guide-title"><?php esc_html_e('お探しの情報を検索できます。', 'omochix'); ?></h2>
                <form class="search-page__form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <label for="not-found-search"><?php esc_html_e('サイト内検索', 'omochix'); ?></label>
                    <div>
                        <svg aria-hidden="true" viewBox="0 0 24 24" width="20" height="20"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>
                        <input id="not-found-search" name="s" type="search" placeholder="<?php esc_attr_e('記事、ツール、トピックを検索', 'omochix'); ?>" required enterkeyhint="search">
                        <button type="submit"><?php esc_html_e('検索', 'omochix'); ?></button>
                    </div>
                </form>
                <nav class="search-empty__links" aria-label="<?php esc_attr_e('主要ページ', 'omochix'); ?>">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a>
                    <a href="<?php echo esc_url($omochix_news_url); ?>"><?php esc_html_e('AIニュース', 'omochix'); ?></a>
                    <a href="<?php echo esc_url($omochix_tools_url); ?>"><?php esc_html_e('AIツール', 'omochix'); ?></a>
                </nav>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
