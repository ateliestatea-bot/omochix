<?php
/**
 * Official OmochiX social links.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_social_links = [
    [
        'name'  => 'X',
        'label' => 'OmochiX on X',
        'url'   => 'https://x.com/omochix528',
        'icon'  => 'x',
    ],
    [
        'name'  => 'Instagram',
        'label' => 'OmochiX on Instagram',
        'url'   => 'https://www.instagram.com/omochix528/',
        'icon'  => 'instagram',
    ],
    [
        'name'  => 'TikTok',
        'label' => 'OmochiX on TikTok',
        'url'   => 'https://www.tiktok.com/@omochix528',
        'icon'  => 'tiktok',
    ],
    [
        'name'  => 'YouTube',
        'label' => 'OmochiX on YouTube',
        'url'   => 'https://www.youtube.com/channel/UCiU0SZYcHnVnIf-LNMYvTgg',
        'icon'  => 'youtube',
    ],
];
?>

<ul class="social-links">
    <?php foreach ($omochix_social_links as $omochix_social_link) : ?>
        <li>
            <a href="<?php echo esc_url($omochix_social_link['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($omochix_social_link['label']); ?>">
                <?php if ('x' === $omochix_social_link['icon']) : ?>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5.2 4.5h3.7l3.8 5.1 4.4-5.1h1.7l-5.3 6.1 5.9 7.9h-3.7l-4.1-5.5-4.8 5.5H5.1l5.7-6.5-5.6-7.5Zm2.5 1.4 7.8 10.5h1.3L9 5.9H7.7Z"/></svg>
                <?php elseif ('instagram' === $omochix_social_link['icon']) : ?>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.6" cy="6.5" r="1"/></svg>
                <?php elseif ('tiktok' === $omochix_social_link['icon']) : ?>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14.2 3.5h3.1c.2 1.7 1.1 2.8 2.7 3.4v3a8.2 8.2 0 0 1-2.7-.8v5.8a5.6 5.6 0 1 1-4.8-5.5v3.1a2.5 2.5 0 1 0 1.7 2.4V3.5Z"/></svg>
                <?php else : ?>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.2 6.2a2.8 2.8 0 0 0-2-2C16.4 3.7 12 3.7 12 3.7s-4.4 0-6.2.5a2.8 2.8 0 0 0-2 2C3.3 8 3.3 12 3.3 12s0 4 .5 5.8a2.8 2.8 0 0 0 2 2c1.8.5 6.2.5 6.2.5s4.4 0 6.2-.5a2.8 2.8 0 0 0 2-2c.5-1.8.5-5.8.5-5.8s0-4-.5-5.8Z"/><path d="m10.3 15.2 4.8-3.2-4.8-3.2v6.4Z" fill="var(--color-bg)"/></svg>
                <?php endif; ?>
                <span><?php echo esc_html($omochix_social_link['name']); ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
