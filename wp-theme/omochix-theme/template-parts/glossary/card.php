<?php
/**
 * AI glossary: one term in a list.
 *
 * @package OmochiX
 *
 * @var array $args ['view' => omochix_glossary_view()]
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_v = (array) ($args['view'] ?? []);
if (empty($omochix_v['url'])) {
    return;
}
?>
<li class="glossary-card" data-glossary-text="<?php echo esc_attr(omochix_glossary_search_text($omochix_v)); ?>">
    <a class="glossary-card__link" href="<?php echo esc_url($omochix_v['url']); ?>">
        <span class="glossary-card__title"><?php echo esc_html($omochix_v['title']); ?></span>
        <?php if ('' !== $omochix_v['reading']) : ?><span class="glossary-card__reading"><?php echo esc_html($omochix_v['reading']); ?></span><?php endif; ?>
        <?php if ('' !== $omochix_v['en']) : ?><span class="glossary-card__en" lang="en"><?php echo esc_html($omochix_v['en']); ?></span><?php endif; ?>
        <span class="glossary-card__summary"><?php echo esc_html($omochix_v['summary']); ?></span>
    </a>
</li>
