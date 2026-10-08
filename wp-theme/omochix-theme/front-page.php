<?php
/** Search-led editorial homepage. Existing post types, meta and URLs stay intact. */
if (!defined('ABSPATH')) { exit; }
get_header();
$ed = omochix_editorial_selection();
$destinations = omochix_editorial_destinations();
$news_url = $destinations[0]['url'];
$tools_url = $destinations[1]['url'];
$learn_url = $destinations[3]['url'];
$prompts_url = $destinations[4]['url'];
$about_url = omochix_get_published_page_url('about');
?>
<main class="ed-main" id="main-content">
    <section class="ed-hero" aria-labelledby="ed-title">
        <div class="ed-hero-heading">
            <div><p class="ed-eyebrow">YOUR DAILY AI COMPANION</p><h1 id="ed-title"><?php esc_html_e('今日は、', 'omochix'); ?><br><?php esc_html_e('どんなAIに出会う？', 'omochix'); ?></h1></div>
            <?php if (file_exists(get_theme_file_path('/assets/img/omochi-hero.webp'))) : ?>
                <img class="ed-mascot" src="<?php echo esc_url(get_theme_file_uri('/assets/img/omochi-hero.webp')); ?>" width="200" height="240" alt="<?php esc_attr_e('紫のパーカーを着た、おもち', 'omochix'); ?>" fetchpriority="high" decoding="async">
            <?php endif; ?>
        </div>
        <form class="ed-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <label class="sr-only" for="ed-search-input"><?php esc_html_e('ニュース・ツール・プロンプトを検索', 'omochix'); ?></label>
            <svg class="ed-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10" cy="10" r="6.5"/><path d="m15 15 5 5"/></svg>
            <input type="search" name="s" id="ed-search-input" placeholder="<?php esc_attr_e('気になるAIを検索', 'omochix'); ?>" enterkeyhint="search" value="<?php echo esc_attr(get_search_query()); ?>">
            <button type="submit" aria-label="<?php esc_attr_e('検索する', 'omochix'); ?>"><?php echo omochix_editorial_icon('arrow'); ?></button>
        </form>
        <nav class="ed-categories" aria-label="<?php esc_attr_e('カテゴリーから探す', 'omochix'); ?>">
            <?php foreach ($destinations as $destination) : ?>
                <?php if ($destination['url'] && !is_wp_error($destination['url'])) : ?>
                    <a href="<?php echo esc_url($destination['url']); ?>"><?php echo omochix_editorial_icon($destination['icon']); ?><span><?php echo esc_html($destination['label']); ?></span></a>
                <?php else : ?>
                    <span class="ed-unavailable"><?php echo omochix_editorial_icon($destination['icon']); ?><span><?php echo esc_html($destination['label']); ?></span><small><?php esc_html_e('準備中', 'omochix'); ?></small></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </section>

    <?php if ($ed['feature']) : $feature = $ed['feature'][0]; ?>
        <section class="ed-feature" aria-labelledby="ed-feature-title">
            <a class="ed-media ed-feature-media" href="<?php echo esc_url(get_permalink($feature)); ?>" tabindex="-1" aria-hidden="true"><?php omochix_editorial_media($feature, 'full', true); ?></a>
            <p class="ed-meta"><?php echo esc_html(omochix_editorial_category($feature)); ?> <span aria-hidden="true">·</span> <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C, $feature)); ?>"><?php echo esc_html(get_the_date('', $feature)); ?></time></p>
            <h2 id="ed-feature-title"><a href="<?php echo esc_url(get_permalink($feature)); ?>"><?php echo esc_html(get_the_title($feature)); ?></a></h2>
            <p class="ed-excerpt"><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt($feature)), 70, '…')); ?></p>
        </section>
    <?php endif; ?>

    <section class="ed-section" aria-labelledby="ed-latest-title">
        <div class="ed-section-heading"><h2 id="ed-latest-title"><?php esc_html_e('最新のAIニュース', 'omochix'); ?></h2><?php if ($news_url) : ?><a href="<?php echo esc_url($news_url); ?>"><?php esc_html_e('すべて見る', 'omochix'); ?> <span aria-hidden="true">↗</span></a><?php endif; ?></div>
        <?php if ($ed['latest']) : ?>
            <div class="ed-stories">
                <?php foreach ($ed['latest'] as $story) : ?>
                    <article class="ed-story"><a href="<?php echo esc_url(get_permalink($story)); ?>">
                        <div class="ed-media"><?php omochix_editorial_media($story); ?></div>
                        <div><p class="ed-meta"><?php echo esc_html(omochix_editorial_category($story)); ?> <span aria-hidden="true">·</span> <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C, $story)); ?>"><?php echo esc_html(get_the_date('', $story)); ?></time></p>
                        <h3><?php echo esc_html(get_the_title($story)); ?></h3></div>
                    </a></article>
                <?php endforeach; ?>
            </div>
        <?php else : ?><p class="ed-empty"><?php esc_html_e('新しいニュースを準備しています。', 'omochix'); ?></p><?php endif; ?>
    </section>

    <section class="ed-section" aria-labelledby="ed-try-title">
        <div class="ed-section-heading"><h2 id="ed-try-title"><?php esc_html_e('使ってみる', 'omochix'); ?></h2></div>
        <div class="ed-practice">
            <article class="ed-practice-card" data-prompt-copy-scope>
                <p class="ed-kicker"><?php echo omochix_editorial_icon('prompt'); ?> Prompts</p>
                <?php if ($ed['prompt']) : $prompt = $ed['prompt']; ?>
                    <h3><a href="<?php echo esc_url(get_permalink($prompt)); ?>"><?php echo esc_html(get_the_title($prompt)); ?></a></h3>
                    <pre class="ed-prompt-text" id="ed-prompt-text" data-prompt-text><?php echo esc_html(get_post_meta($prompt->ID, 'prompt_body', true)); ?></pre>
                    <div class="ed-practice-actions"><a href="<?php echo esc_url(get_permalink($prompt)); ?>"><?php esc_html_e('使い方を見る', 'omochix'); ?> ↗</a><button type="button" class="ed-copy" data-copy-prompt data-copy-target="ed-prompt-text"><?php esc_html_e('コピー', 'omochix'); ?></button></div>
                    <p class="sr-only" role="status" aria-live="polite" data-copy-status></p>
                <?php else : ?><h3><?php esc_html_e('すぐに使える、プロンプト。', 'omochix'); ?></h3><p><?php esc_html_e('試せるプロンプトを準備しています。', 'omochix'); ?></p><?php endif; ?>
                <?php if ($prompts_url) : ?><a class="ed-text-link" href="<?php echo esc_url($prompts_url); ?>"><?php esc_html_e('プロンプトを探す', 'omochix'); ?> ↗</a><?php endif; ?>
            </article>
            <article class="ed-practice-card ed-learn">
                <p class="ed-kicker"><?php echo omochix_editorial_icon('learn'); ?> Learn</p>
                <h3><?php esc_html_e('AIの基本を、ひとつずつ。', 'omochix'); ?></h3>
                <p><?php esc_html_e('はじめての人にも、もう一度学びたい人にも。AIの基礎と使い方を、自分のペースで。', 'omochix'); ?></p>
                <?php if ($learn_url) : ?><a class="ed-text-link" href="<?php echo esc_url($learn_url); ?>"><?php esc_html_e('Learnを見る', 'omochix'); ?> ↗</a><?php else : ?><p class="ed-meta"><?php esc_html_e('準備中', 'omochix'); ?></p><?php endif; ?>
            </article>
        </div>
    </section>

    <section class="ed-section" aria-labelledby="ed-tools-title">
        <div class="ed-section-heading"><h2 id="ed-tools-title"><?php esc_html_e('AIツールを探す', 'omochix'); ?></h2><?php if ($tools_url) : ?><a href="<?php echo esc_url($tools_url); ?>"><?php esc_html_e('すべて見る', 'omochix'); ?> ↗</a><?php endif; ?></div>
        <?php
        $purpose_links = [];
        foreach (['writing' => __('文章', 'omochix'), 'research' => __('調査', 'omochix'), 'image-generation' => __('画像', 'omochix'), 'programming' => __('開発', 'omochix')] as $slug => $label) {
            $term = taxonomy_exists('ai_tool_category') ? get_term_by('slug', $slug, 'ai_tool_category') : null;
            if ($term instanceof WP_Term && $term->count > 0) {
                $url = get_term_link($term);
                if (!is_wp_error($url)) { $purpose_links[] = ['label' => $label, 'url' => $url]; }
            }
        }
        ?>
        <?php if ($purpose_links) : ?><nav class="ed-purposes" aria-label="<?php esc_attr_e('用途からAIツールを探す', 'omochix'); ?>"><?php foreach ($purpose_links as $item) : ?><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?> ↗</a><?php endforeach; ?></nav><?php endif; ?>
        <div class="ed-tools">
            <?php foreach ($ed['tools'] as $tool) :
                $logo = get_post_meta($tool->ID, 'tool_logo', true);
                $logo_url = is_numeric($logo) ? wp_get_attachment_image_url((int) $logo, 'thumbnail') : $logo;
                $logo_url = $logo_url ?: get_the_post_thumbnail_url($tool, 'thumbnail');
                $name = get_the_title($tool);
                $description = get_post_meta($tool->ID, 'short_description', true) ?: get_the_excerpt($tool);
                ?>
                <a class="ed-tool" href="<?php echo esc_url(get_permalink($tool)); ?>">
                    <?php if ($logo_url) : ?><img src="<?php echo esc_url($logo_url); ?>" width="44" height="44" alt="" loading="lazy" decoding="async"><?php else : ?><span class="ed-tool-initial" aria-hidden="true"><?php echo esc_html(function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1)); ?></span><?php endif; ?>
                    <span><strong><?php echo esc_html($name); ?></strong><small><?php echo esc_html(wp_trim_words(wp_strip_all_tags($description), 30, '…')); ?></small></span><?php echo omochix_editorial_icon('arrow'); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$ed['tools']) : ?><p class="ed-empty"><?php esc_html_e('AIツールの情報を準備しています。', 'omochix'); ?></p><?php endif; ?>
    </section>

    <section class="ed-about" aria-labelledby="ed-about-title"><h2 id="ed-about-title"><?php esc_html_e('知る。その先の、できるへ。', 'omochix'); ?></h2><p><?php esc_html_e('AIと出会い、学び、使いこなす。そんな一歩を、OmochiXと。', 'omochix'); ?></p><?php if ($about_url) : ?><a href="<?php echo esc_url($about_url); ?>"><?php esc_html_e('OmochiXについて', 'omochix'); ?> ↗</a><?php endif; ?></section>
</main>
<?php get_footer('editorial'); ?>
