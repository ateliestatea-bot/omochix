<?php
/**
 * AI glossary: /glossary/{slug}/ (one term).
 *
 * Only a visible term reaches this point, or an editor's preview (noindex,
 * nofollow, marked on the page). Everything else answers as a missing page.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}
$omochix_d = function_exists('omochix_glossary_decide') ? omochix_glossary_decide() : ['action' => 'none'];
if (!function_exists('omochix_glossary_enabled') || !omochix_glossary_enabled() || 'ok' !== $omochix_d['action'] || !$omochix_d['post'] instanceof WP_Post) {
    get_template_part('template-parts/glossary/not-found');
    return;
}
$omochix_v     = omochix_glossary_view($omochix_d['post']);
$omochix_index = omochix_glossary_index_url();
$omochix_cat   = $omochix_v['category'];
$omochix_catl  = $omochix_cat instanceof WP_Term ? get_term_link($omochix_cat) : '';
$omochix_kinds = ['official' => __('公式', 'omochix'), 'paper' => __('論文', 'omochix'), 'standard' => __('標準・公的文書', 'omochix'), 'docs' => __('技術文書', 'omochix'), 'media' => __('解説記事', 'omochix')];
get_header();
?>
<main class="glossary glossary--term" id="main-content">
    <article class="glossary-term" aria-labelledby="glossary-term-title">
        <header class="glossary-hero glossary-hero--term">
            <div class="glossary-container">
                <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                        <li><a href="<?php echo esc_url($omochix_index); ?>"><?php esc_html_e('AI用語集', 'omochix'); ?></a></li>
                        <?php if (is_string($omochix_catl) && '' !== $omochix_catl) : ?><li><a href="<?php echo esc_url($omochix_catl); ?>"><?php echo esc_html($omochix_cat->name); ?></a></li><?php endif; ?>
                        <li aria-current="page"><?php echo esc_html($omochix_v['title']); ?></li>
                    </ol>
                </nav>
                <?php if (!empty($omochix_d['preview'])) : ?>
                    <p class="glossary-preview" role="note"><?php esc_html_e('未公開プレビュー：この用語は公開の条件を満たしていないため、編集者にだけ表示されています。', 'omochix'); ?></p>
                <?php endif; ?>
                <h1 class="glossary-hero__title" id="glossary-term-title"><?php echo esc_html($omochix_v['title']); ?></h1>
                <p class="glossary-term__names">
                    <?php if ('' !== $omochix_v['reading']) : ?><span class="glossary-term__reading"><?php echo esc_html($omochix_v['reading']); ?></span><?php endif; ?>
                    <?php if ('' !== $omochix_v['en']) : ?><span class="glossary-term__en" lang="en"><?php echo esc_html($omochix_v['en']); ?></span><?php endif; ?>
                </p>
                <?php if ($omochix_v['aliases']) : ?>
                    <p class="glossary-term__aliases"><span><?php esc_html_e('別名', 'omochix'); ?>：</span><?php echo esc_html(implode('、', $omochix_v['aliases'])); ?></p>
                <?php endif; ?>
            </div>
        </header>
        <div class="glossary-container glossary-body">
            <section class="glossary-definition" aria-labelledby="glossary-definition-title">
                <h2 id="glossary-definition-title"><?php echo esc_html(sprintf(/* translators: %s: term */ __('%sとは', 'omochix'), $omochix_v['title'])); ?></h2>
                <p class="glossary-definition__text"><?php echo esc_html($omochix_v['summary']); ?></p>
                <?php if ('' !== $omochix_v['level'] || $omochix_cat instanceof WP_Term) : ?>
                    <p class="glossary-definition__tags">
                        <?php if ($omochix_cat instanceof WP_Term && is_string($omochix_catl)) : ?><a class="glossary-tag" href="<?php echo esc_url($omochix_catl); ?>"><?php echo esc_html($omochix_cat->name); ?></a><?php endif; ?>
                        <?php if ('' !== $omochix_v['level']) : ?><span class="glossary-tag glossary-tag--level"><?php echo esc_html($omochix_v['level']); ?></span><?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>
            <div class="glossary-content"><?php echo $omochix_v['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- omochix_glossary_content_html(): wp_kses with the glossary allow-list ?></div>
            <?php if ($omochix_v['sources']) : ?>
                <section class="glossary-sources" aria-labelledby="glossary-sources-title">
                    <h2 id="glossary-sources-title"><?php esc_html_e('出典', 'omochix'); ?></h2>
                    <ol>
                        <?php foreach ($omochix_v['sources'] as $omochix_s) : ?>
                            <li>
                                <a href="<?php echo esc_url($omochix_s['url'], ['https']); ?>" rel="noopener noreferrer external"><?php echo esc_html($omochix_s['title']); ?></a>
                                <span class="glossary-sources__meta"><?php echo esc_html($omochix_s['publisher']); ?><?php if (isset($omochix_kinds[$omochix_s['kind']])) : ?>（<?php echo esc_html($omochix_kinds[$omochix_s['kind']]); ?>）<?php endif; ?><?php if ('' !== $omochix_s['checked_on']) : ?> ・ <?php echo esc_html(sprintf(/* translators: %s: date */ __('%s確認', 'omochix'), $omochix_s['checked_on'])); ?><?php endif; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>
            <footer class="glossary-term__footer">
                <?php if ('' !== $omochix_v['verified_on']) : ?><p><?php echo esc_html(sprintf(/* translators: %s: date */ __('最終確認日：%s', 'omochix'), $omochix_v['verified_on'])); ?></p><?php endif; ?>
                <?php if ($omochix_v['ai_assisted']) : ?><p><?php esc_html_e('この解説は下書きにAIを利用し、編集部が出典と照合して確認しています。', 'omochix'); ?></p><?php endif; ?>
            </footer>
            <?php get_template_part('template-parts/glossary/related', null, ['related' => omochix_glossary_related($omochix_d['post'])]); ?>
            <p class="glossary-back"><a href="<?php echo esc_url($omochix_index); ?>"><?php esc_html_e('AI用語集の一覧へ戻る', 'omochix'); ?></a></p>
        </div>
    </article>
</main>
<?php
get_footer();
