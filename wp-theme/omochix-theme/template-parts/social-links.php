<?php
/**
 * Official OmochiX social links.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_social_links = omochix_get_social_links();
?>

<ul class="social-links">
    <?php foreach ($omochix_social_links as $omochix_social_link) : ?>
        <li>
            <a href="<?php echo esc_url($omochix_social_link['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($omochix_social_link['label']); ?>">
                <?php
                $icon = $omochix_social_link['icon'];
                if (in_array($icon, ['x', 'instagram', 'tiktok', 'youtube'], true)) {
                    // Fixed theme-owned assets; no user-provided SVG input.
                    echo file_get_contents(get_theme_file_path('/assets/icons/social/' . $icon . '.svg'));
                }
                ?>
                <span><?php echo esc_html($omochix_social_link['name']); ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
