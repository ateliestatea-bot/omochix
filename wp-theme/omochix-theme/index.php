<?php
/**
 * Main template.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="site-main" id="main-content">
    <!-- Hero section will replace this intentionally minimal placeholder. -->
    <section class="hero-placeholder" aria-labelledby="page-title">
        <div>
            <h1 id="page-title">OmochiX</h1>
            <p><?php esc_html_e('AIで世界の情報を、もっとシンプルに。', 'omochix'); ?></p>
        </div>
    </section>
</main>

<?php get_footer(); ?>
