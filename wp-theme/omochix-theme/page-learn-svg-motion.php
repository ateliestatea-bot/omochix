<?php
/**
 * SVG Motion Studio page ("/learn/svg-motion/").
 *
 * Served by the template_include filter in inc/learn-svg-motion.php. The preset
 * names and descriptions are in the HTML; assets/js/svg-motion/studio.js adds
 * the previews and controls. Without JavaScript the page says what it needs.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $omochix_data    = omochix_svg_motion_data();
    $omochix_hub_url = omochix_get_published_page_url('learn');
    $omochix_lead    = has_excerpt() ? trim(wp_strip_all_tags(get_the_excerpt())) : __('ロゴに付けられる動きを選んで、その場で試せます。', 'omochix');
    $omochix_labels  = ['entrance' => __('登場', 'omochix'), 'loop' => __('ループ', 'omochix')];
    ?>
    <main class="svg-motion" id="main-content" data-svg-motion>
        <header class="svg-motion__hero">
            <div class="svg-motion__container">
                <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                        <?php if ($omochix_hub_url) : ?><li><a href="<?php echo esc_url($omochix_hub_url); ?>"><?php esc_html_e('Learn', 'omochix'); ?></a></li><?php endif; ?>
                        <li aria-current="page"><?php the_title(); ?></li>
                    </ol>
                </nav>
                <p class="svg-motion__eyebrow"><?php esc_html_e('SVG MOTION STUDIO', 'omochix'); ?></p>
                <h1><?php the_title(); ?></h1>
                <p class="svg-motion__lead"><?php echo esc_html($omochix_lead); ?></p>
                <noscript><p class="svg-motion__notice"><?php esc_html_e('この機能には JavaScript が必要です。', 'omochix'); ?></p></noscript>
            </div>
        </header>

        <?php if (empty($omochix_data['presets'])) : ?>
            <div class="svg-motion__container"><p class="svg-motion__notice"><?php esc_html_e('プリセットを読み込めませんでした。', 'omochix'); ?></p></div>
        <?php else : ?>
        <div class="svg-motion__container svg-motion__layout">
            <section class="svg-motion__detail" aria-labelledby="svgm-title">
                <button type="button" class="svg-motion__stage" data-svgm-stage aria-label="<?php esc_attr_e('プレビュー。押すと再生と停止を切り替えます', 'omochix'); ?>"></button>
                <p class="svg-motion__notice" data-svgm-error role="alert" hidden></p>
                <div class="svg-motion__controls">
                    <button type="button" class="svg-motion__button svg-motion__button--primary" data-svgm-toggle aria-pressed="false" data-play="<?php esc_attr_e('再生', 'omochix'); ?>" data-pause="<?php esc_attr_e('停止', 'omochix'); ?>"><?php esc_html_e('再生', 'omochix'); ?></button>
                    <button type="button" class="svg-motion__button" data-svgm-reset><?php esc_html_e('リセット', 'omochix'); ?></button>
                </div>
                <p class="sr-only" data-svgm-status role="status" aria-live="polite"></p>
                <h2 class="svg-motion__title" id="svgm-title" data-svgm-title></h2>
                <p class="svg-motion__description" data-svgm-description></p>
                <p class="svg-motion__note" data-svgm-note hidden></p>

                <div class="svg-motion__adjust">
                    <div class="svg-motion__params-head">
                        <h3><?php esc_html_e('調整する', 'omochix'); ?></h3>
                        <button type="button" class="svg-motion__link" data-svgm-defaults><?php esc_html_e('既定に戻す', 'omochix'); ?></button>
                    </div>
                    <div class="svg-motion__params" data-svgm-params></div>
                    <details class="svg-motion__more">
                        <summary><?php esc_html_e('位置・大きさ・繰り返しなど', 'omochix'); ?></summary>
                        <div class="svg-motion__params" data-svgm-common></div>
                    </details>
                </div>

                <section class="svg-motion__export" aria-labelledby="svgm-export-title">
                    <h3 id="svgm-export-title"><?php esc_html_e('書き出す', 'omochix'); ?></h3>
                    <fieldset class="svg-motion__usages">
                        <legend><?php esc_html_e('使い方', 'omochix'); ?></legend>
                        <?php foreach (omochix_svg_motion_usages() as $omochix_key => $omochix_usage) : ?>
                            <label class="svg-motion__usage"><input type="radio" name="svgm-usage" value="<?php echo esc_attr($omochix_key); ?>"<?php checked('image' === $omochix_key); ?>><span><?php echo esc_html($omochix_usage['label']); ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="svg-motion__privacy" data-svgm-usage-note></p>
                    <label class="svg-motion__alt"><span><?php esc_html_e('ロゴの説明（代替テキスト）', 'omochix'); ?></span><input type="text" maxlength="120" autocomplete="off" data-svgm-alt placeholder="<?php esc_attr_e('例：OmochiX のロゴ', 'omochix'); ?>"></label>
                    <p class="svg-motion__note" data-svgm-origin-note hidden></p>
                    <pre class="svg-motion__code" tabindex="0" aria-label="<?php esc_attr_e('埋め込みコード', 'omochix'); ?>"><code data-svgm-code></code></pre>
                    <div class="svg-motion__export-actions">
                        <button type="button" class="svg-motion__button svg-motion__button--primary" data-svgm-copy><?php esc_html_e('コードをコピー', 'omochix'); ?></button>
                        <span data-svgm-downloads></span>
                    </div>
                    <div class="svg-motion__settings-io">
                        <button type="button" class="svg-motion__button" data-svgm-json-download><?php esc_html_e('設定を保存（JSON）', 'omochix'); ?></button>
                        <label class="svg-motion__button svg-motion__file">
                            <input class="sr-only" type="file" accept=".json,application/json" data-svgm-json-file>
                            <span><?php esc_html_e('設定を読み込む', 'omochix'); ?></span>
                        </label>
                    </div>
                </section>
            </section>

            <div class="svg-motion__main">
                <section class="svg-motion__import" aria-labelledby="svgm-import-title">
                    <h2 class="svg-motion__title" id="svgm-import-title"><?php esc_html_e('ロゴを入れる', 'omochix'); ?></h2>
                    <p class="svg-motion__current"><?php esc_html_e('表示中：', 'omochix'); ?><span data-svgm-logo-name></span></p>
                    <div class="svg-motion__drop" data-svgm-drop>
                        <label class="svg-motion__button svg-motion__file">
                            <input class="sr-only" type="file" accept=".svg,image/svg+xml" data-svgm-file>
                            <span><?php esc_html_e('SVG ファイルを選ぶ', 'omochix'); ?></span>
                        </label>
                        <span class="svg-motion__hint"><?php esc_html_e('ここにドロップもできます（1MB まで）', 'omochix'); ?></span>
                    </div>
                    <details class="svg-motion__paste">
                        <summary><?php esc_html_e('SVG コードを貼り付ける', 'omochix'); ?></summary>
                        <label class="sr-only" for="svgm-paste"><?php esc_html_e('SVG コード', 'omochix'); ?></label>
                        <textarea id="svgm-paste" rows="4" spellcheck="false" autocomplete="off" data-svgm-paste></textarea>
                        <button type="button" class="svg-motion__button" data-svgm-paste-button><?php esc_html_e('読み込む', 'omochix'); ?></button>
                    </details>
                    <ul class="svg-motion__warnings" data-svgm-warnings role="status" hidden></ul>
                    <p class="svg-motion__privacy"><?php esc_html_e('ロゴはこのブラウザの中だけで処理し、外部へは送信しません。読み込んだロゴはこのブラウザに保存されます。', 'omochix'); ?></p>
                    <p class="svg-motion__privacy" data-svgm-storage hidden><?php esc_html_e('このブラウザでは保存できません。ページを閉じると元に戻ります。', 'omochix'); ?></p>
                    <button type="button" class="svg-motion__link" data-svgm-remove hidden><?php esc_html_e('このロゴを削除する', 'omochix'); ?></button>
                    <fieldset class="svg-motion__samples">
                        <legend><?php esc_html_e('見本のロゴ', 'omochix'); ?></legend>
                        <?php foreach ($omochix_data['samples'] as $omochix_sample) : ?>
                            <label class="svg-motion__sample"><input type="radio" name="svgm-sample" value="<?php echo esc_attr($omochix_sample['id']); ?>"><span><?php echo esc_html($omochix_sample['name']); ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                </section>

            <section class="svg-motion__gallery" aria-labelledby="svgm-gallery-title">
                <div class="svg-motion__toolbar">
                    <h2 class="svg-motion__title" id="svgm-gallery-title"><?php esc_html_e('モーションを選ぶ', 'omochix'); ?></h2>
                    <button type="button" class="svg-motion__link" data-svgm-thumbs aria-pressed="false" data-stop="<?php esc_attr_e('一覧の動きを止める', 'omochix'); ?>" data-start="<?php esc_attr_e('一覧を動かす', 'omochix'); ?>"><?php esc_html_e('一覧の動きを止める', 'omochix'); ?></button>
                </div>
                <div class="svg-motion__chips" role="group" aria-label="<?php esc_attr_e('カテゴリ', 'omochix'); ?>">
                    <button type="button" class="svg-motion__chip" data-svgm-filter="all" aria-pressed="true"><?php esc_html_e('すべて', 'omochix'); ?></button>
                    <?php foreach ($omochix_labels as $omochix_key => $omochix_label) : ?>
                        <button type="button" class="svg-motion__chip" data-svgm-filter="<?php echo esc_attr($omochix_key); ?>" aria-pressed="false"><?php echo esc_html($omochix_label); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php /* translators: %d: number of presets shown. */ ?>
                <p class="svg-motion__count" data-svgm-count data-template="<?php esc_attr_e('%d 種類', 'omochix'); ?>"><?php echo esc_html(sprintf(__('%d 種類', 'omochix'), count($omochix_data['presets']))); ?></p>
                <fieldset class="svg-motion__grid">
                    <legend class="sr-only"><?php esc_html_e('モーション', 'omochix'); ?></legend>
                    <?php foreach ($omochix_data['presets'] as $omochix_preset) : ?>
                        <label class="svg-motion__card" data-svgm-card data-category="<?php echo esc_attr($omochix_preset['category']); ?>">
                            <input type="radio" name="svgm-preset" value="<?php echo esc_attr($omochix_preset['id']); ?>">
                            <span class="svg-motion__thumb"><img data-svgm-thumb="<?php echo esc_attr($omochix_preset['id']); ?>" alt="" width="96" height="96"></span>
                            <span class="svg-motion__card-name"><?php echo esc_html($omochix_preset['name']); ?></span>
                            <span class="svg-motion__badge"><?php echo esc_html($omochix_labels[$omochix_preset['category']] ?? ''); ?></span>
                            <span class="svg-motion__card-text"><?php echo esc_html($omochix_preset['description']); ?></span>
                            <span class="svg-motion__reason" data-svgm-reason hidden></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            </section>
            </div>
        </div>
        <script type="application/json" id="omx-svg-motion-data"><?php echo omochix_svg_motion_data_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_TAG in the helper. ?></script>
        <?php endif; ?>
    </main>
    <?php
endwhile;

get_footer();
