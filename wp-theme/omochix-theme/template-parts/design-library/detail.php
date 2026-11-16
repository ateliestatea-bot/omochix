<?php
/**
 * Design Library work detail (D1.5). Each block is printed only when the
 * work has that data: preview (sandboxed live demo / video / poster), about,
 * technologies and materials, production steps, prompt, code, what can be
 * customized, verification, credits, related content, Motion Studio mapping.
 * No button leads to a feature that does not exist (no generator, no live
 * editor); copy buttons only copy text already on the page.
 *
 * @var array $args {item: array}
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

$omx_item    = $args['item'];
$omx_medium  = $omx_item['medium'];
$omx_mediums = omochix_design_mediums();
$omx_techs   = omochix_design_technologies();
$omx_uc      = omochix_design_use_cases();
$omx_mt      = omochix_design_motion_types();
$omx_styles  = omochix_design_styles();
$omx_motions = omochix_design_motions();
$omx_levels  = omochix_design_levels();
$omx_env     = omochix_design_environments();
$omx_mtypes  = omochix_design_material_types();
$omx_ind     = omochix_design_item_industries($omx_item);
$omx_demo    = omochix_design_demo_allowed($omx_item) ? omochix_design_demo_url($omx_item) : '';
$omx_poster  = omochix_design_asset_url($omx_item['posterFallback'] ?? $omx_item['poster']);
$omx_state   = omochix_design_verification_state($omx_item);
$omx_ver     = $omx_item['verification'];
$omx_related = omochix_design_related($omx_item);
$omx_code    = ('web' === $omx_medium && !empty($omx_item['deliverables']['showCode'])) ? omochix_design_demo_sources($omx_item) : null;
$omx_label   = static function (array $map, array $values) { return implode('・', array_map(static function ($v) use ($map) { return $map[$v]; }, $values)); };
$omx_video   = $omx_item['video'] ?? null;
?>
<article class="dl-detail" data-dl-medium="<?php echo esc_attr($omx_medium); ?>">
    <div class="dl-container">
        <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
            <ol>
                <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                <li><a href="<?php echo esc_url(omochix_design_url()); ?>"><?php echo esc_html(get_the_title(omochix_design_page_id())); ?></a></li>
                <li><a href="<?php echo esc_url(omochix_design_medium_url($omx_medium)); ?>"><?php echo esc_html($omx_mediums[$omx_medium]['label']); ?></a></li>
                <li aria-current="page"><?php echo esc_html($omx_item['title']); ?></li>
            </ol>
        </nav>
        <header class="dl-detail__head">
            <p class="dl-eyebrow"><?php echo esc_html($omx_mediums[$omx_medium]['label']); ?></p>
            <h1><?php echo esc_html($omx_item['title']); ?></h1>
            <p class="dl-lead"><?php echo esc_html($omx_item['summary']); ?></p>
        </header>

        <?php if ('video' === $omx_medium && is_array($omx_video)) : ?>
            <?php $omx_portrait = $omx_video['height'] > $omx_video['width']; ?>
            <figure class="dl-stage dl-stage--video<?php echo $omx_portrait ? ' dl-stage--portrait' : ''; ?>" data-dl-stage data-state="video">
                <div class="dl-stage__view" style="aspect-ratio: <?php echo esc_attr($omx_video['width'] . ' / ' . $omx_video['height']); ?>">
                    <video class="dl-stage__video" controls playsinline muted preload="none" width="<?php echo esc_attr((string) $omx_video['width']); ?>" height="<?php echo esc_attr((string) $omx_video['height']); ?>"
                        poster="<?php echo esc_url($omx_poster); ?>" aria-label="<?php echo esc_attr(sprintf(__('%sの動画（音声なし）', 'omochix'), $omx_item['title'])); ?>">
                        <?php if (!empty($omx_video['webm'])) : ?><source src="<?php echo esc_url(omochix_design_asset_url($omx_video['webm'])); ?>" type="video/webm"><?php endif; ?>
                        <source src="<?php echo esc_url(omochix_design_asset_url($omx_video['mp4'])); ?>" type="video/mp4">
                    </video>
                </div>
                <figcaption class="dl-stage__caption">
                    <span><?php echo esc_html(sprintf(__('%1$d×%2$d・%3$s 秒・%4$d fps・音声なし。再生ボタンを押すまで読み込みません。', 'omochix'), $omx_video['width'], $omx_video['height'], rtrim(rtrim(number_format((float) $omx_video['seconds'], 1), '0'), '.'), $omx_video['fps'])); ?></span>
                </figcaption>
            </figure>
        <?php else : ?>
            <figure class="dl-stage" data-dl-stage data-state="<?php echo '' !== $omx_demo ? 'loading' : 'poster'; ?>">
                <div class="dl-stage__bar" aria-hidden="true"><span></span><span></span><span></span><p><?php echo esc_html($omx_item['title']); ?></p></div>
                <div class="dl-stage__view">
                    <?php if ('' !== $omx_demo) : ?>
                        <iframe class="dl-stage__frame" data-dl-demo src="<?php echo esc_url($omx_demo); ?>" sandbox="allow-scripts" loading="lazy" referrerpolicy="no-referrer"
                            title="<?php echo esc_attr(sprintf(__('%sのライブデモ', 'omochix'), $omx_item['title'])); ?>"></iframe>
                    <?php else : ?>
                        <img class="dl-stage__poster" src="<?php echo esc_url($omx_poster); ?>" width="1280" height="800" alt="<?php echo esc_attr(sprintf(__('%sの静止画', 'omochix'), $omx_item['title'])); ?>">
                    <?php endif; ?>
                </div>
                <figcaption class="dl-stage__caption">
                    <span data-dl-status><?php echo '' !== $omx_demo ? esc_html__('ライブデモはサイト本体から切り離した環境で動いています。', 'omochix') : esc_html__('ライブデモは安全性の確認が終わるまで公開していません。静止画を表示しています。', 'omochix'); ?></span>
                    <?php if ('' !== $omx_demo) : ?>
                        <a class="dl-btn" href="<?php echo esc_url($omx_demo); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('全画面で開く', 'omochix'); ?><span class="sr-only"><?php esc_html_e('（新しいタブ）', 'omochix'); ?></span></a>
                    <?php endif; ?>
                </figcaption>
            </figure>
        <?php endif; ?>

        <nav class="dl-toc" aria-label="<?php esc_attr_e('このページの内容', 'omochix'); ?>">
            <a href="#dl-d-about"><?php esc_html_e('概要と技術', 'omochix'); ?></a>
            <?php if (!empty($omx_item['steps'])) : ?><a href="#dl-d-steps"><?php esc_html_e('制作手順', 'omochix'); ?></a><?php endif; ?>
            <?php if (!empty($omx_item['prompt'])) : ?><a href="#dl-d-prompt"><?php esc_html_e('プロンプト', 'omochix'); ?></a><?php endif; ?>
            <?php if ($omx_code) : ?><a href="#dl-d-code"><?php esc_html_e('コード', 'omochix'); ?></a><?php endif; ?>
            <a href="#dl-d-verify"><?php esc_html_e('検証情報', 'omochix'); ?></a>
        </nav>

        <div class="dl-detail__grid">
            <div class="dl-detail__main">
                <section class="dl-block" aria-labelledby="dl-d-about">
                    <h2 id="dl-d-about"><?php esc_html_e('この作品について', 'omochix'); ?></h2>
                    <p><?php echo esc_html($omx_item['description']); ?></p>
                    <?php if ($omx_item['materials']) : ?>
                        <h3><?php esc_html_e('使っている素材', 'omochix'); ?></h3>
                        <ul class="dl-list">
                            <?php foreach ($omx_item['materials'] as $omx_m) : ?>
                                <li><?php echo esc_html($omx_m['name']); ?> <span class="dl-muted">（<?php echo esc_html($omx_mtypes[$omx_m['type']] . '・' . ('original' === $omx_m['source'] ? __('自作', 'omochix') : __('端末に入っているもの', 'omochix'))); ?>）</span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($omx_item['customization']) : ?>
                        <h3><?php esc_html_e('変えられるところ', 'omochix'); ?></h3>
                        <dl class="dl-pairs">
                            <?php foreach ($omx_item['customization'] as $omx_c) : ?>
                                <dt><?php echo esc_html($omx_c['label']); ?></dt><dd><?php echo esc_html($omx_c['description']); ?></dd>
                            <?php endforeach; ?>
                        </dl>
                        <p class="dl-muted"><?php esc_html_e('このページで値を変える機能はまだありません。プロンプトやコードの該当箇所を書き換えて使ってください。', 'omochix'); ?></p>
                    <?php endif; ?>
                </section>

                <?php if (!empty($omx_item['steps'])) : ?>
                    <section class="dl-block" aria-labelledby="dl-d-steps">
                        <h2 id="dl-d-steps"><?php esc_html_e('制作手順', 'omochix'); ?></h2>
                        <ol class="dl-steps">
                            <?php foreach ($omx_item['steps'] as $omx_s) : ?>
                                <li><h3><?php echo esc_html($omx_s['title']); ?></h3><p><?php echo esc_html($omx_s['body']); ?></p></li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endif; ?>

                <?php if (!empty($omx_item['prompt'])) : ?>
                    <section class="dl-block" aria-labelledby="dl-d-prompt">
                        <div class="dl-block__head">
                            <h2 id="dl-d-prompt"><?php esc_html_e('AI に頼むときのプロンプト', 'omochix'); ?></h2>
                            <button type="button" class="dl-btn" data-dl-copy="dl-prompt-text" hidden><?php esc_html_e('コピー', 'omochix'); ?></button>
                        </div>
                        <pre class="dl-code dl-code--prose" id="dl-prompt-text"><?php echo esc_html($omx_item['prompt']['text']); ?></pre>
                    </section>
                <?php endif; ?>

                <?php if ($omx_code) : ?>
                    <section class="dl-block" aria-labelledby="dl-d-code">
                        <h2 id="dl-d-code"><?php esc_html_e('コード', 'omochix'); ?></h2>
                        <p class="dl-muted"><?php esc_html_e('ライブデモと同じソースです（OmochiX の自作。サイト側の共通ランタイムは含みません）。', 'omochix'); ?></p>
                        <?php foreach (['html' => 'HTML', 'css' => 'CSS', 'js' => 'JavaScript'] as $omx_lang => $omx_name) : ?>
                            <?php if ('' === trim($omx_code[$omx_lang])) { continue; } ?>
                            <details class="dl-codebox">
                                <summary><?php echo esc_html($omx_name); ?> <span class="dl-muted"><?php echo esc_html(sprintf(__('%s 行', 'omochix'), number_format_i18n(substr_count($omx_code[$omx_lang], "\n") + 1))); ?></span></summary>
                                <div class="dl-block__head"><button type="button" class="dl-btn" data-dl-copy="dl-code-<?php echo esc_attr($omx_lang); ?>" hidden><?php esc_html_e('コピー', 'omochix'); ?></button></div>
                                <pre class="dl-code" id="dl-code-<?php echo esc_attr($omx_lang); ?>"><code><?php echo esc_html($omx_code[$omx_lang]); ?></code></pre>
                            </details>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <section class="dl-block" aria-labelledby="dl-d-verify">
                    <h2 id="dl-d-verify"><?php esc_html_e('検証情報', 'omochix'); ?></h2>
                    <p class="dl-verify dl-verify--<?php echo esc_attr($omx_state); ?>" data-dl-verification="<?php echo esc_attr($omx_state); ?>"><?php echo esc_html(omochix_design_verification_states()[$omx_state]); ?></p>
                    <dl class="dl-pairs">
                        <?php if (null !== $omx_ver['checked']) : ?><dt><?php esc_html_e('確認日', 'omochix'); ?></dt><dd><?php echo esc_html($omx_ver['checked']); ?></dd><?php endif; ?>
                        <?php if ($omx_ver['environments']) : ?><dt><?php esc_html_e('確認した環境', 'omochix'); ?></dt><dd><?php echo esc_html($omx_label($omx_env, $omx_ver['environments'])); ?></dd><?php endif; ?>
                    </dl>
                    <?php if ($omx_ver['limitations']) : ?>
                        <h3><?php esc_html_e('分かっている制約', 'omochix'); ?></h3>
                        <ul class="dl-list"><?php foreach ($omx_ver['limitations'] as $omx_l) : ?><li><?php echo esc_html($omx_l); ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="dl-detail__side">
                <dl class="dl-facts">
                    <dt><?php esc_html_e('用途', 'omochix'); ?></dt><dd><?php echo esc_html($omx_label($omx_uc, $omx_item['use_cases'])); ?></dd>
                    <dt><?php esc_html_e('表現', 'omochix'); ?></dt><dd><?php echo esc_html($omx_label($omx_mt, $omx_item['motion_types'])); ?></dd>
                    <dt><?php esc_html_e('技術', 'omochix'); ?></dt><dd><?php echo esc_html($omx_label($omx_techs, $omx_item['technologies'])); ?></dd>
                    <dt><?php esc_html_e('スタイル', 'omochix'); ?></dt><dd><?php echo esc_html($omx_label($omx_styles, $omx_item['styles'])); ?></dd>
                    <dt><?php esc_html_e('動き', 'omochix'); ?></dt><dd><?php echo esc_html($omx_label($omx_motions, $omx_item['motions'])); ?></dd>
                    <?php if ($omx_ind) : ?><dt><?php esc_html_e('向いている業種', 'omochix'); ?></dt><dd><?php echo esc_html(implode('・', $omx_ind)); ?></dd><?php endif; ?>
                    <dt><?php esc_html_e('難易度', 'omochix'); ?></dt><dd><?php echo esc_html($omx_levels[$omx_item['level']]); ?></dd>
                    <dt><?php esc_html_e('配色', 'omochix'); ?></dt>
                    <dd class="dl-swatches">
                        <?php foreach ($omx_item['palette'] as $omx_c) : ?>
                            <span class="dl-swatch"><i style="background:<?php echo esc_attr($omx_c['value']); ?>"></i><?php echo esc_html($omx_c['name'] . ' ' . $omx_c['value']); ?></span>
                        <?php endforeach; ?>
                    </dd>
                    <dt><?php esc_html_e('作者・出典', 'omochix'); ?></dt>
                    <dd><?php echo esc_html($omx_item['credits']['author'] . '（' . __('自作・オリジナル', 'omochix') . '）'); ?><?php if (!empty($omx_item['credits']['notes'])) : ?><br><span class="dl-muted"><?php echo esc_html($omx_item['credits']['notes']); ?></span><?php endif; ?></dd>
                    <?php if (!empty($omx_item['motionStudio'])) : ?>
                        <dt><?php esc_html_e('Motion Studio', 'omochix'); ?></dt>
                        <dd data-dl-motion-studio><?php echo esc_html(sprintf(__('対応テンプレート：%1$s（%2$s）', 'omochix'), $omx_item['motionStudio']['templateId'], 'verified' === $omx_item['motionStudio']['status'] ? __('対応を確認済み', 'omochix') : __('対応は未確認', 'omochix'))); ?><br><span class="dl-muted"><?php esc_html_e('このサイトから Motion Studio を開く機能はありません。', 'omochix'); ?></span></dd>
                    <?php endif; ?>
                </dl>

                <?php if ($omx_related['motion'] || $omx_related['learn'] || $omx_related['designs']) : ?>
                    <section class="dl-related" aria-labelledby="dl-d-related">
                        <h2 id="dl-d-related"><?php esc_html_e('関連', 'omochix'); ?></h2>
                        <?php foreach (['motion' => __('使っている動きの見本（Motion Gallery）', 'omochix'), 'learn' => __('Learn の記事', 'omochix'), 'designs' => __('ほかの作品', 'omochix')] as $omx_key => $omx_head) : ?>
                            <?php if (!$omx_related[$omx_key]) { continue; } ?>
                            <h3><?php echo esc_html($omx_head); ?></h3>
                            <ul class="dl-list"><?php foreach ($omx_related[$omx_key] as $omx_r) : ?><li><a href="<?php echo esc_url($omx_r['url']); ?>"><?php echo esc_html($omx_r['title']); ?></a></li><?php endforeach; ?></ul>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
        <p class="dl-back"><a href="<?php echo esc_url(omochix_design_medium_url($omx_medium)); ?>"><span aria-hidden="true">←</span> <?php echo esc_html(sprintf(__('%sの一覧に戻る', 'omochix'), $omx_mediums[$omx_medium]['label'])); ?></a></p>
    </div>
</article>
