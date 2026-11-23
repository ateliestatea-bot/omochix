<?php
/**
 * AI glossary: related terms and links (only what a visitor may see).
 *
 * @package OmochiX
 *
 * @var array $args ['related' => omochix_glossary_related()]
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_r = (array) ($args['related'] ?? []);
$omochix_sections = [
    'glossary'   => __('関連する用語', 'omochix'),
    'learn'      => __('Learnで学ぶ', 'omochix'),
    'tools'      => __('関連するAIツール', 'omochix'),
    'practice'   => __('実践ガイド', 'omochix'),
    'industries' => __('業種別のAI活用', 'omochix'),
];
if ([] === array_filter(array_intersect_key($omochix_r, $omochix_sections))) {
    return;
}
?>
<section class="glossary-related" aria-labelledby="glossary-related-title">
    <h2 id="glossary-related-title"><?php esc_html_e('あわせて読みたい', 'omochix'); ?></h2>
    <?php foreach ($omochix_sections as $omochix_k => $omochix_label) :
        $omochix_items = (array) ($omochix_r[$omochix_k] ?? []);
        if (!$omochix_items) { continue; } ?>
        <div class="glossary-related__group">
            <h3><?php echo esc_html($omochix_label); ?></h3>
            <ul class="<?php echo 'glossary' === $omochix_k ? 'glossary-cards glossary-cards--compact' : 'glossary-links'; ?>">
                <?php foreach ($omochix_items as $omochix_i) : ?>
                    <li<?php echo 'glossary' === $omochix_k ? ' class="glossary-card"' : ''; ?>>
                        <a<?php echo 'glossary' === $omochix_k ? ' class="glossary-card__link"' : ''; ?> href="<?php echo esc_url((string) $omochix_i['url']); ?>">
                            <span class="glossary-card__title"><?php echo esc_html((string) $omochix_i['title']); ?></span>
                            <?php if (!empty($omochix_i['summary'])) : ?><span class="glossary-card__summary"><?php echo esc_html((string) $omochix_i['summary']); ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
</section>
