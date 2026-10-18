<?php
/**
 * Motion sample detail: header, large preview, prompt, code, related, nav.
 *
 * Args:
 *   item    (array)  Catalog item. Required.
 *   context (string) "page" (full detail URL) or "dialog" (fragment loaded
 *                    into the list's dialog via ?omx_partial=1).
 *
 * One markup source for both: the dialog fetches exactly this fragment.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_item = isset($args['item']) && is_array($args['item']) ? $args['item'] : null;
if (!$omochix_item) {
    return;
}
$omochix_context  = isset($args['context']) && 'dialog' === $args['context'] ? 'dialog' : 'page';
$omochix_is_page  = 'page' === $omochix_context;
// Heading levels follow the context so the outline never skips a level:
// standalone page h1 > h2 > h3, inside the list's dialog h2 > h3 > h4.
$omochix_heading  = $omochix_is_page ? 'h1' : 'h2';
$omochix_h_panel  = $omochix_is_page ? 'h2' : 'h3';
$omochix_h_sub    = $omochix_is_page ? 'h3' : 'h4';
$omochix_url      = omochix_learn_gallery_url($omochix_item);
$omochix_list_url = omochix_learn_gallery_url();
$omochix_category = omochix_learn_gallery_get_category($omochix_item['category']);
$omochix_cat_url  = $omochix_category ? omochix_learn_gallery_url(null, ['cat' => $omochix_category['slug']]) : $omochix_list_url;
$omochix_prompt   = omochix_learn_gallery_prompt_text($omochix_item, $omochix_url);
$omochix_code     = omochix_learn_gallery_code_text($omochix_item, $omochix_url);
$omochix_nav      = omochix_learn_gallery_get_neighbors($omochix_item);
$omochix_prompts  = omochix_learn_gallery_related_prompts($omochix_item);
$omochix_tools    = omochix_learn_gallery_related_tools($omochix_item);
$omochix_learn    = omochix_learn_gallery_related_learn($omochix_item);
$omochix_uid      = 'motion-' . $omochix_item['slug'];
$omochix_hub_url  = function_exists('omochix_get_published_page_url') ? omochix_get_published_page_url('learn') : '';
?>
<article class="motion-detail__article" data-motion-detail data-slug="<?php echo esc_attr($omochix_item['slug']); ?>" data-url="<?php echo esc_url($omochix_url); ?>" aria-labelledby="<?php echo esc_attr($omochix_uid); ?>-title">
    <?php if ($omochix_is_page) : ?>
        <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
            <ol>
                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                <?php if ($omochix_hub_url) : ?><li><a href="<?php echo esc_url($omochix_hub_url); ?>"><?php esc_html_e('Learn', 'omochix'); ?></a></li><?php endif; ?>
                <li><a href="<?php echo esc_url($omochix_list_url); ?>"><?php esc_html_e('動く見本', 'omochix'); ?></a></li>
                <li aria-current="page"><?php echo esc_html($omochix_item['name']['ja']); ?></li>
            </ol>
        </nav>
    <?php endif; ?>

    <header class="motion-detail__header">
        <p class="motion-detail__eyebrow">
            <?php if ($omochix_category) : ?><a href="<?php echo esc_url($omochix_cat_url); ?>"><?php echo esc_html($omochix_category['name']); ?></a><?php endif; ?>
            <span>#<?php echo esc_html($omochix_item['id']); ?></span>
        </p>
        <<?php echo $omochix_heading; ?> class="motion-detail__title" id="<?php echo esc_attr($omochix_uid); ?>-title">
            <?php echo esc_html($omochix_item['name']['ja']); ?>
            <span class="motion-detail__en" lang="en"><?php echo esc_html($omochix_item['name']['en']); ?></span>
        </<?php echo $omochix_heading; ?>>
        <p class="motion-detail__summary"><?php echo esc_html($omochix_item['summary']); ?></p>
        <div class="motion-detail__meta">
            <span class="motion-badge motion-badge--<?php echo esc_attr($omochix_item['level']); ?>"><?php echo esc_html(omochix_learn_gallery_level_label($omochix_item['level'])); ?></span>
            <?php foreach ($omochix_item['tech'] as $omochix_tech) : ?>
                <span class="motion-chip"><?php echo esc_html(omochix_learn_gallery_techs()[$omochix_tech] ?? $omochix_tech); ?></span>
            <?php endforeach; ?>
            <?php foreach ($omochix_item['tags'] as $omochix_tag) : ?>
                <a class="motion-chip motion-chip--tag" href="<?php echo esc_url(omochix_learn_gallery_url(null, ['q' => $omochix_tag])); ?>">#<?php echo esc_html($omochix_tag); ?></a>
            <?php endforeach; ?>
        </div>
    </header>

    <div class="motion-detail__preview">
        <?php get_template_part('template-parts/learn-gallery/preview', null, ['item' => $omochix_item, 'size' => 'detail', 'static' => $omochix_is_page]); ?>
    </div>
    <p class="motion-detail__description"><?php echo esc_html($omochix_item['description']); ?></p>

    <?php get_template_part('template-parts/learn-gallery/playground', null, ['item' => $omochix_item, 'heading' => $omochix_h_panel]); ?>

    <div class="motion-detail__layout">
        <section class="motion-panel" aria-labelledby="<?php echo esc_attr($omochix_uid); ?>-prompt">
            <div class="motion-panel__header">
                <<?php echo $omochix_h_panel; ?> class="motion-panel__title" id="<?php echo esc_attr($omochix_uid); ?>-prompt"><?php esc_html_e('AIへの頼み方', 'omochix'); ?></<?php echo $omochix_h_panel; ?>>
                <button type="button" class="motion-copy" data-omx-copy data-omx-copy-target="<?php echo esc_attr($omochix_uid); ?>-prompt-text"><?php esc_html_e('プロンプトをコピー', 'omochix'); ?></button>
            </div>
            <blockquote class="motion-detail__ask"><?php echo esc_html($omochix_item['prompt']['ask']); ?></blockquote>
            <<?php echo $omochix_h_sub; ?> class="motion-panel__subtitle"><?php esc_html_e('動きの仕様', 'omochix'); ?></<?php echo $omochix_h_sub; ?>>
            <ul class="motion-detail__spec">
                <?php foreach ($omochix_item['prompt']['spec'] as $omochix_spec) : ?><li><?php echo esc_html($omochix_spec); ?></li><?php endforeach; ?>
            </ul>
            <<?php echo $omochix_h_sub; ?> class="motion-panel__subtitle"><?php esc_html_e('使いどころ', 'omochix'); ?></<?php echo $omochix_h_sub; ?>>
            <p class="motion-detail__use"><?php echo esc_html($omochix_item['prompt']['use']); ?></p>
            <details class="motion-code">
                <summary><?php esc_html_e('コピーされるプロンプト全文を見る', 'omochix'); ?></summary>
                <pre class="motion-code__pre" tabindex="0" id="<?php echo esc_attr($omochix_uid); ?>-prompt-text"><?php echo esc_html($omochix_prompt); ?></pre>
            </details>
            <p class="sr-only" data-omx-copy-status aria-live="polite"></p>
        </section>

        <section class="motion-panel" aria-labelledby="<?php echo esc_attr($omochix_uid); ?>-code">
            <div class="motion-panel__header">
                <<?php echo $omochix_h_panel; ?> class="motion-panel__title" id="<?php echo esc_attr($omochix_uid); ?>-code"><?php esc_html_e('コード', 'omochix'); ?></<?php echo $omochix_h_panel; ?>>
                <button type="button" class="motion-copy" data-omx-copy data-omx-copy-target="<?php echo esc_attr($omochix_uid); ?>-code-text"><?php esc_html_e('コードをコピー', 'omochix'); ?></button>
            </div>
            <p class="motion-panel__note"><?php esc_html_e('プレビューで動いているものと同じコードです。HTMLに貼り付ければそのまま動きます。', 'omochix'); ?></p>
            <details class="motion-code" open>
                <summary><?php esc_html_e('HTML / CSS / JavaScript', 'omochix'); ?></summary>
                <pre class="motion-code__pre" tabindex="0" id="<?php echo esc_attr($omochix_uid); ?>-code-text"><?php echo esc_html($omochix_code); ?></pre>
            </details>
            <p class="sr-only" data-omx-copy-status aria-live="polite"></p>
        </section>
    </div>

    <?php if ($omochix_prompts || $omochix_tools || $omochix_learn) : ?>
        <section class="motion-detail__related" aria-labelledby="<?php echo esc_attr($omochix_uid); ?>-related">
            <<?php echo $omochix_h_panel; ?> class="motion-panel__title" id="<?php echo esc_attr($omochix_uid); ?>-related"><?php esc_html_e('あわせて使う', 'omochix'); ?></<?php echo $omochix_h_panel; ?>>
            <div class="motion-related__grid">
                <?php foreach ($omochix_prompts as $omochix_prompt_post) : ?>
                    <a class="motion-related-card" href="<?php echo esc_url(get_permalink($omochix_prompt_post)); ?>">
                        <span class="motion-related-card__kind"><?php esc_html_e('プロンプト', 'omochix'); ?></span>
                        <strong><?php echo esc_html(get_the_title($omochix_prompt_post)); ?></strong>
                        <?php if (has_excerpt($omochix_prompt_post)) : ?><span><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt($omochix_prompt_post)), 28, '…')); ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php foreach ($omochix_tools as $omochix_tool) : ?>
                    <a class="motion-related-card" href="<?php echo esc_url(get_permalink($omochix_tool)); ?>">
                        <span class="motion-related-card__kind"><?php esc_html_e('AIツール', 'omochix'); ?></span>
                        <strong><?php echo esc_html(get_the_title($omochix_tool)); ?></strong>
                        <?php $omochix_tool_description = get_post_meta($omochix_tool->ID, 'short_description', true); ?>
                        <?php if ($omochix_tool_description) : ?><span><?php echo esc_html(wp_trim_words(wp_strip_all_tags($omochix_tool_description), 24, '…')); ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php foreach ($omochix_learn as $omochix_learn_page) : ?>
                    <a class="motion-related-card" href="<?php echo esc_url($omochix_learn_page['url']); ?>">
                        <span class="motion-related-card__kind"><?php esc_html_e('Learn', 'omochix'); ?></span>
                        <strong><?php echo esc_html($omochix_learn_page['title']); ?></strong>
                        <?php if ($omochix_learn_page['description']) : ?><span><?php echo esc_html($omochix_learn_page['description']); ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($omochix_nav['prev'] || $omochix_nav['next']) : ?>
        <nav class="motion-detail__nav" aria-label="<?php esc_attr_e('同じカテゴリの見本', 'omochix'); ?>">
            <?php if ($omochix_nav['prev']) : ?>
                <a class="motion-detail__nav-link motion-detail__nav-link--prev" href="<?php echo esc_url(omochix_learn_gallery_url($omochix_nav['prev'])); ?>" data-motion-open>
                    <span><?php esc_html_e('前の見本', 'omochix'); ?></span>
                    <strong>#<?php echo esc_html($omochix_nav['prev']['id']); ?> <?php echo esc_html($omochix_nav['prev']['name']['ja']); ?></strong>
                </a>
            <?php endif; ?>
            <?php if ($omochix_nav['next']) : ?>
                <a class="motion-detail__nav-link motion-detail__nav-link--next" href="<?php echo esc_url(omochix_learn_gallery_url($omochix_nav['next'])); ?>" data-motion-open>
                    <span><?php esc_html_e('次の見本', 'omochix'); ?></span>
                    <strong>#<?php echo esc_html($omochix_nav['next']['id']); ?> <?php echo esc_html($omochix_nav['next']['name']['ja']); ?></strong>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <?php if ($omochix_is_page) : ?>
        <footer class="motion-detail__cta">
            <a class="motion-button motion-button--ghost" href="<?php echo esc_url($omochix_list_url); ?>"><?php esc_html_e('すべての見本を見る', 'omochix'); ?></a>
        </footer>
    <?php endif; ?>
</article>
