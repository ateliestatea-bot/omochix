<?php
/**
 * Single post template.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="article-page" id="main-content">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <?php
            $omochix_post_id       = get_the_ID();
            $omochix_post_url      = get_permalink();
            $omochix_post_title    = get_the_title();
            $omochix_post_excerpt  = get_the_excerpt();
            $omochix_post_image    = get_the_post_thumbnail_url($omochix_post_id, 'full');
            $omochix_post_category = get_the_category();
            $omochix_primary_category = !empty($omochix_post_category) ? $omochix_post_category[0] : null;
            $omochix_post_tags     = get_the_tags();
            $omochix_content_size  = strlen(wp_strip_all_tags(get_the_content()));
            $omochix_reading_time  = max(1, (int) ceil($omochix_content_size / 1200));
            $omochix_author_id     = (int) get_the_author_meta('ID');
            $omochix_author_bio    = get_the_author_meta('description', $omochix_author_id);
            $omochix_news_page_id  = (int) get_option('page_for_posts');
            $omochix_news_url      = $omochix_news_page_id ? get_permalink($omochix_news_page_id) : home_url('/');
            $omochix_news_title    = $omochix_news_page_id ? get_the_title($omochix_news_page_id) : __('AIニュース', 'omochix');

            // Build a TOC only from editor-defined h2 anchors; article HTML is not rewritten.
            $omochix_toc_items       = [];
            $omochix_raw_content = get_the_content();
            if (preg_match_all('/<h2[^>]*\sid=["\']([^"\']+)["\'][^>]*>(.*?)<\/h2>/is', $omochix_raw_content, $omochix_heading_matches, PREG_SET_ORDER)) {
                foreach ($omochix_heading_matches as $omochix_heading_match) {
                    $omochix_heading_text = trim(wp_strip_all_tags($omochix_heading_match[2]));
                    if ($omochix_heading_text) {
                        $omochix_toc_items[] = [
                            'id'    => $omochix_heading_match[1],
                            'title' => $omochix_heading_text,
                        ];
                    }
                }
            }
            $omochix_has_toc = count($omochix_toc_items) >= 2;

            $omochix_x_share_url = add_query_arg([
                'url'  => $omochix_post_url,
                'text' => $omochix_post_title,
            ], 'https://twitter.com/intent/tweet');
            $omochix_facebook_share_url = add_query_arg('u', $omochix_post_url, 'https://www.facebook.com/sharer/sharer.php');
            ?>

            <article class="article">
                <header class="article-header">
                    <div class="article-container article-container--wide">
                        <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                            <ol>
                                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                                <?php if ($omochix_primary_category) : ?>
                                    <li><a href="<?php echo esc_url(get_category_link($omochix_primary_category)); ?>"><?php echo esc_html($omochix_primary_category->name); ?></a></li>
                                <?php else : ?>
                                    <li><a href="<?php echo esc_url($omochix_news_url); ?>"><?php echo esc_html($omochix_news_title); ?></a></li>
                                <?php endif; ?>
                                <li aria-current="page"><?php echo esc_html($omochix_post_title); ?></li>
                            </ol>
                        </nav>

                        <div class="article-header__copy">
                            <?php if ($omochix_primary_category) : ?>
                                <a class="article-header__category" href="<?php echo esc_url(get_category_link($omochix_primary_category)); ?>"><?php echo esc_html($omochix_primary_category->name); ?></a>
                            <?php endif; ?>
                            <h1><?php the_title(); ?></h1>
                            <?php if ($omochix_post_excerpt) : ?>
                                <p class="article-header__lead"><?php echo esc_html(wp_strip_all_tags($omochix_post_excerpt)); ?></p>
                            <?php endif; ?>
                            <div class="article-header__meta">
                                <span><?php echo esc_html(get_the_author()); ?></span>
                                <span>
                                    <?php esc_html_e('公開', 'omochix'); ?>
                                    <time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('Y.m.d')); ?></time>
                                </span>
                                <span>
                                    <?php esc_html_e('更新', 'omochix'); ?>
                                    <time datetime="<?php echo esc_attr(get_the_modified_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_modified_date('Y.m.d')); ?></time>
                                </span>
                                <span><?php echo esc_html(sprintf(__('約%d分で読めます', 'omochix'), $omochix_reading_time)); ?></span>
                            </div>
                        </div>

                        <figure class="article-header__image">
                            <div class="article-header__media">
                                <?php if ($omochix_post_image) : ?>
                                    <img src="<?php echo esc_url($omochix_post_image); ?>" width="1280" height="720" alt="<?php echo esc_attr(get_post_meta(get_post_thumbnail_id(), '_wp_attachment_image_alt', true)); ?>" fetchpriority="high" decoding="async">
                                <?php else : ?>
                                    <div class="article-header__placeholder" role="img" aria-label="<?php esc_attr_e('OmochiX AIニュースのアイキャッチ画像', 'omochix'); ?>"><span aria-hidden="true">OmochiX</span></div>
                                <?php endif; ?>
                            </div>
                            <?php $omochix_image_caption = $omochix_post_image ? wp_get_attachment_caption(get_post_thumbnail_id()) : ''; ?>
                            <?php if ($omochix_image_caption) : ?>
                                <figcaption><?php echo wp_kses_post($omochix_image_caption); ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    </div>
                </header>

                <div class="article-container article-layout">
                    <div class="article-main">
                        <?php if ($omochix_has_toc) : ?>
                            <nav class="article-toc article-toc--inline" aria-labelledby="article-toc-inline-title">
                                <h2 id="article-toc-inline-title"><?php esc_html_e('この記事の内容', 'omochix'); ?></h2>
                                <ol>
                                    <?php foreach ($omochix_toc_items as $omochix_toc_item) : ?>
                                        <li><a href="#<?php echo esc_attr($omochix_toc_item['id']); ?>"><?php echo esc_html($omochix_toc_item['title']); ?></a></li>
                                    <?php endforeach; ?>
                                </ol>
                            </nav>
                        <?php endif; ?>

                        <div class="article-content">
                            <?php the_content(); ?>
                        </div>

                        <footer class="article-footer">
                            <section class="article-tags" aria-labelledby="article-tags-title">
                                <h2 id="article-tags-title"><?php esc_html_e('タグ', 'omochix'); ?></h2>
                                <?php if ($omochix_post_tags) : ?>
                                    <ul>
                                        <?php foreach ($omochix_post_tags as $omochix_post_tag) : ?>
                                            <li><a href="<?php echo esc_url(get_tag_link($omochix_post_tag)); ?>"><?php echo esc_html($omochix_post_tag->name); ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <p><?php esc_html_e('この記事にはタグがありません。', 'omochix'); ?></p>
                                <?php endif; ?>
                            </section>

                            <section class="article-share" aria-labelledby="article-share-title">
                                <h2 id="article-share-title"><?php esc_html_e('この記事を共有', 'omochix'); ?></h2>
                                <div class="article-share__links">
                                    <a href="<?php echo esc_url($omochix_x_share_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Xでこの記事を共有（新しいタブで開く）', 'omochix'); ?>">X</a>
                                    <a href="<?php echo esc_url($omochix_facebook_share_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Facebookでこの記事を共有（新しいタブで開く）', 'omochix'); ?>">Facebook</a>
                                    <button type="button" data-copy-url="<?php echo esc_url($omochix_post_url); ?>" aria-describedby="copy-url-status"><?php esc_html_e('URLをコピー', 'omochix'); ?></button>
                                </div>
                                <p class="sr-only" id="copy-url-status" data-copy-status aria-live="polite"></p>
                            </section>

                            <section class="article-author" aria-labelledby="article-author-title">
                                <div class="article-author__avatar"><?php echo wp_kses_post(get_avatar($omochix_author_id, 64, '', esc_attr(get_the_author()), ['loading' => 'lazy'])); ?></div>
                                <div>
                                    <p><?php esc_html_e('この記事を書いた人', 'omochix'); ?></p>
                                    <h2 id="article-author-title"><?php echo esc_html(get_the_author()); ?></h2>
                                    <p><?php echo esc_html($omochix_author_bio ?: __('OmochiX編集部が、AIの最新情報を分かりやすくお届けします。', 'omochix')); ?></p>
                                    <a href="<?php echo esc_url(omochix_get_page_or_search_url('editorial-policy', __('編集方針', 'omochix'))); ?>"><?php esc_html_e('編集・更新ポリシーを見る', 'omochix'); ?></a>
                                </div>
                            </section>

                            <?php if (get_previous_post() || get_next_post()) : ?>
                                <nav class="article-navigation" aria-label="<?php esc_attr_e('前後の記事', 'omochix'); ?>">
                                    <div><?php previous_post_link('%link', '<small>' . esc_html__('前の記事', 'omochix') . '</small><span>%title</span>'); ?></div>
                                    <div><?php next_post_link('%link', '<small>' . esc_html__('次の記事', 'omochix') . '</small><span>%title</span>'); ?></div>
                                </nav>
                            <?php endif; ?>
                        </footer>
                    </div>

                    <aside class="article-sidebar" aria-label="<?php esc_attr_e('記事の補助情報', 'omochix'); ?>">
                        <?php if ($omochix_has_toc) : ?>
                            <nav class="article-toc article-toc--sidebar" aria-labelledby="article-toc-sidebar-title">
                                <h2 id="article-toc-sidebar-title"><?php esc_html_e('この記事の内容', 'omochix'); ?></h2>
                                <ol>
                                    <?php foreach ($omochix_toc_items as $omochix_toc_item) : ?>
                                        <li><a href="#<?php echo esc_attr($omochix_toc_item['id']); ?>"><?php echo esc_html($omochix_toc_item['title']); ?></a></li>
                                    <?php endforeach; ?>
                                </ol>
                            </nav>
                        <?php endif; ?>

                        <?php
                        $omochix_article_popular = new WP_Query([
                            'post_type'           => 'post',
                            'post_status'         => 'publish',
                            'posts_per_page'      => 4,
                            'post__not_in'        => [$omochix_post_id],
                            'orderby'             => ['comment_count' => 'DESC', 'date' => 'DESC'],
                            'ignore_sticky_posts' => true,
                            'no_found_rows'       => true,
                        ]);
                        ?>
                        <section class="article-sidebar__panel" aria-labelledby="article-popular-title">
                            <h2 id="article-popular-title"><?php esc_html_e('人気記事', 'omochix'); ?></h2>
                            <?php if ($omochix_article_popular->have_posts()) : ?>
                                <ol class="article-sidebar__posts">
                                    <?php while ($omochix_article_popular->have_posts()) : $omochix_article_popular->the_post(); ?>
                                        <li><a href="<?php the_permalink(); ?>"><?php echo esc_html(get_the_title()); ?></a></li>
                                    <?php endwhile; ?>
                                </ol>
                            <?php else : ?>
                                <p><?php esc_html_e('人気記事を準備中です。', 'omochix'); ?></p>
                            <?php endif; ?>
                            <?php wp_reset_postdata(); ?>
                        </section>

                        <section class="article-sidebar__panel" aria-labelledby="article-categories-title">
                            <h2 id="article-categories-title"><?php esc_html_e('人気カテゴリー', 'omochix'); ?></h2>
                            <?php $omochix_popular_categories = get_categories(['hide_empty' => true, 'number' => 6, 'orderby' => 'count', 'order' => 'DESC']); ?>
                            <?php if (!is_wp_error($omochix_popular_categories) && $omochix_popular_categories) : ?>
                                <ul class="article-sidebar__categories">
                                    <?php foreach ($omochix_popular_categories as $omochix_popular_category) : ?>
                                        <li><a href="<?php echo esc_url(get_category_link($omochix_popular_category)); ?>"><span><?php echo esc_html($omochix_popular_category->name); ?></span><small><?php echo esc_html(number_format_i18n($omochix_popular_category->count)); ?></small></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p><?php esc_html_e('カテゴリーを準備中です。', 'omochix'); ?></p>
                            <?php endif; ?>
                        </section>

                        <section class="article-sidebar__newsletter" aria-labelledby="article-newsletter-title">
                            <p><?php esc_html_e('OMOCHIX NEWSLETTER', 'omochix'); ?></p>
                            <h2 id="article-newsletter-title"><?php esc_html_e('AIの最新情報を見逃さない。', 'omochix'); ?></h2>
                            <a href="<?php echo esc_url(home_url('/#newsletter-title')); ?>"><?php esc_html_e('Newsletterを見る', 'omochix'); ?><span aria-hidden="true">→</span></a>
                        </section>
                    </aside>
                </div>
            </article>

                <?php
                $omochix_related_posts = [];
                $omochix_related_ids   = [$omochix_post_id];
                $omochix_tax_query     = ['relation' => 'OR'];
                if ($omochix_post_category) {
                    $omochix_tax_query[] = ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => wp_list_pluck($omochix_post_category, 'term_id')];
                }
                if ($omochix_post_tags) {
                    $omochix_tax_query[] = ['taxonomy' => 'post_tag', 'field' => 'term_id', 'terms' => wp_list_pluck($omochix_post_tags, 'term_id')];
                }
                if (count($omochix_tax_query) > 1) {
                    $omochix_related_query = new WP_Query([
                        'post_type'           => 'post',
                        'post_status'         => 'publish',
                        'posts_per_page'      => 3,
                        'post__not_in'        => $omochix_related_ids,
                        'tax_query'           => $omochix_tax_query,
                        'orderby'             => 'date',
                        'order'               => 'DESC',
                        'ignore_sticky_posts' => true,
                        'no_found_rows'       => true,
                    ]);
                    $omochix_related_posts = $omochix_related_query->posts;
                    $omochix_related_ids   = array_merge($omochix_related_ids, wp_list_pluck($omochix_related_posts, 'ID'));
                }
                $omochix_related_slots = 3 - count($omochix_related_posts);
                if ($omochix_related_slots > 0) {
                    $omochix_related_fallback = new WP_Query([
                        'post_type'           => 'post',
                        'post_status'         => 'publish',
                        'posts_per_page'      => $omochix_related_slots,
                        'post__not_in'        => $omochix_related_ids,
                        'orderby'             => 'date',
                        'order'               => 'DESC',
                        'ignore_sticky_posts' => true,
                        'no_found_rows'       => true,
                    ]);
                    $omochix_related_posts = array_merge($omochix_related_posts, $omochix_related_fallback->posts);
                }
                ?>

                <section class="related-articles" aria-labelledby="related-articles-title">
                    <div class="article-container article-container--wide">
                        <header class="related-articles__header">
                            <h2 id="related-articles-title"><?php esc_html_e('関連記事', 'omochix'); ?></h2>
                            <p><?php esc_html_e('あわせて読みたいAIニュース', 'omochix'); ?></p>
                        </header>
                        <?php if ($omochix_related_posts) : ?>
                            <div class="related-articles__grid">
                                <?php foreach ($omochix_related_posts as $post) : setup_postdata($post); ?>
                                    <?php
                                    $omochix_related_image = get_the_post_thumbnail_url(get_the_ID(), 'large');
                                    $omochix_related_categories = get_the_category();
                                    $omochix_related_category = $omochix_related_categories ? $omochix_related_categories[0]->name : __('AIニュース', 'omochix');
                                    ?>
                                    <article class="related-card">
                                        <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr(sprintf(__('記事を読む：%s', 'omochix'), get_the_title())); ?>">
                                            <div class="related-card__media">
                                                <?php if ($omochix_related_image) : ?>
                                                    <img src="<?php echo esc_url($omochix_related_image); ?>" width="640" height="360" alt="" loading="lazy" decoding="async">
                                                <?php else : ?>
                                                    <div role="img" aria-label="<?php esc_attr_e('OmochiX AIニュースのアイキャッチ画像', 'omochix'); ?>"><span aria-hidden="true">O</span></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="related-card__meta"><span><?php echo esc_html($omochix_related_category); ?></span><time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('Y.m.d')); ?></time></div>
                                            <h3><?php echo esc_html(get_the_title()); ?></h3>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="related-articles__empty" role="status"><p><?php esc_html_e('関連記事を準備中です。', 'omochix'); ?></p></div>
                        <?php endif; ?>
                        <?php wp_reset_postdata(); ?>
                    </div>
                </section>

                <section class="article-cta" aria-labelledby="article-cta-title">
                    <div class="article-container article-container--wide article-cta__inner">
                        <div><p><?php esc_html_e('NEXT STEP', 'omochix'); ?></p><h2 id="article-cta-title"><?php esc_html_e('次に知りたいAI情報へ。', 'omochix'); ?></h2></div>
                        <div class="article-cta__links">
                            <a href="<?php echo esc_url(omochix_get_page_or_search_url('ai-tools', __('AIツール', 'omochix'))); ?>"><?php esc_html_e('自分に合うAIツールを探す', 'omochix'); ?></a>
                            <a href="<?php echo esc_url($omochix_news_url); ?>"><?php esc_html_e('最新のAIニュースをもっと見る', 'omochix'); ?></a>
                        </div>
                    </div>
                </section>
                <section class="article-social-follow" aria-labelledby="article-social-follow-title">
                    <h2 id="article-social-follow-title"><?php esc_html_e('OmochiXをフォロー', 'omochix'); ?></h2>
                    <p><?php esc_html_e('最新のAIニュースや活用情報をSNSでも配信しています。', 'omochix'); ?></p>
                    <?php get_template_part('template-parts/social-links'); ?>
                </section>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
