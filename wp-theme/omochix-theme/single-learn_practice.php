<?php
/**
 * Practice item ("/learn/practice/{slug}/"): preview, outcome, prerequisites,
 * steps with copyable prompts and code, verification, safety, publishing,
 * related content, sources, changelog, and the AI implementation brief.
 *
 * @package OmochiX
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $omochix_item     = omochix_learn_practice_item(get_post());
    $omochix_motion   = omochix_learn_practice_motion($omochix_item);
    $omochix_related  = omochix_learn_practice_related($omochix_item);
    $omochix_hub_url  = omochix_get_published_page_url('learn') ?: home_url('/learn/');
    $omochix_list_url = omochix_learn_practice_url();
    $omochix_field    = $omochix_item['fields'] ? $omochix_item['fields'][0] : null;
    $omochix_field_url = $omochix_field ? omochix_learn_practice_field_hub_url($omochix_field->slug) : '';
    $omochix_format   = omochix_learn_practice_primary_format($omochix_item);
    $omochix_brief    = omochix_learn_practice_brief(get_post(), 'claude-code');
    $omochix_brief_codex = omochix_learn_practice_brief(get_post(), 'codex');
    $omochix_all_prompts = implode("\n\n---\n\n", array_map(static fn($p) => ('' !== $p['title'] ? '# ' . $p['title'] . "\n" : '') . $p['body'], $omochix_item['prompts']));
    $omochix_preview  = $omochix_item['preview'];
    $omochix_poster   = $omochix_preview['poster'] ? wp_get_attachment_image($omochix_preview['poster'], 'large', false, ['alt' => '']) : get_the_post_thumbnail(get_post(), 'large', ['alt' => '']);
    $omochix_sections = [];
    $omochix_sections['overview'] = __('作るもの', 'omochix');
    if ($omochix_item['prereq']) { $omochix_sections['prereq'] = __('前提条件', 'omochix'); }
    if ($omochix_item['steps']) { $omochix_sections['steps'] = __('実装手順', 'omochix'); }
    if ($omochix_item['verify']) { $omochix_sections['verify'] = __('検証', 'omochix'); }
    if ($omochix_item['safety']) { $omochix_sections['safety'] = __('安全性', 'omochix'); }
    if ($omochix_item['publish']) { $omochix_sections['publish'] = __('公開手順', 'omochix'); }
    $omochix_sections['brief'] = __('AI実装指示書', 'omochix');
    if ($omochix_motion['items'] || array_filter($omochix_related)) { $omochix_sections['related'] = __('関連', 'omochix'); }
    if ($omochix_item['sources']) { $omochix_sections['sources'] = __('参照仕様', 'omochix'); }
    if ($omochix_item['changelog']) { $omochix_sections['changelog'] = __('更新履歴', 'omochix'); }
    ?>
    <main class="practice" id="main-content" data-practice-id="<?php echo (int) $omochix_item['id']; ?>">
        <header class="practice__header">
            <div class="practice__container">
                <nav class="breadcrumb" aria-label="<?php esc_attr_e('パンくずリスト', 'omochix'); ?>">
                    <ol>
                        <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'omochix'); ?></a></li>
                        <li><a href="<?php echo esc_url($omochix_hub_url); ?>"><?php esc_html_e('Learn', 'omochix'); ?></a></li>
                        <?php if ($omochix_field_url) : ?><li><a href="<?php echo esc_url($omochix_field_url); ?>"><?php echo esc_html($omochix_field->name); ?></a></li><?php else : ?><li><a href="<?php echo esc_url($omochix_list_url); ?>"><?php esc_html_e('実践教材', 'omochix'); ?></a></li><?php endif; ?>
                        <li aria-current="page"><?php the_title(); ?></li>
                    </ol>
                </nav>
                <div class="practice__badges">
                    <?php if ($omochix_field) : ?><a class="practice-badge practice-badge--field" href="<?php echo esc_url($omochix_field_url ?: omochix_learn_practice_url(['field' => $omochix_field->slug])); ?>"><?php echo esc_html($omochix_field->name); ?></a><?php endif; ?>
                    <?php if ($omochix_format) : ?><span class="practice-badge practice-badge--format"><?php echo esc_html(omochix_learn_practice_format_label($omochix_format)); ?></span><?php endif; ?>
                </div>
                <h1><?php the_title(); ?></h1>
                <?php if ($omochix_item['summary']) : ?><p class="practice__summary"><?php echo esc_html($omochix_item['summary']); ?></p><?php endif; ?>
                <div class="practice__meta">
                    <span class="practice-badge practice-badge--<?php echo esc_attr($omochix_item['level']); ?>"><?php echo esc_html(omochix_learn_practice_level_label($omochix_item['level'])); ?></span>
                    <?php if ($omochix_item['minutes']) : ?><span><?php echo esc_html(sprintf(__('約%d分', 'omochix'), $omochix_item['minutes'])); ?></span><?php endif; ?>
                    <?php if ($omochix_item['verified_at']) : ?>
                        <span class="practice__verified<?php echo $omochix_item['stale'] ? ' is-stale' : ''; ?>"><?php esc_html_e('最終検証', 'omochix'); ?> <time datetime="<?php echo esc_attr($omochix_item['verified_at']); ?>"><?php echo esc_html(str_replace('-', '.', $omochix_item['verified_at'])); ?></time><?php if ($omochix_item['stale']) : ?> <em><?php esc_html_e('要再検証', 'omochix'); ?></em><?php endif; ?></span>
                    <?php endif; ?>
                    <?php foreach ($omochix_item['ai'] as $omochix_term) : ?>
                        <?php $omochix_tool = omochix_learn_practice_tool_for_ai_term($omochix_term); ?>
                        <?php if ($omochix_tool) : ?><a class="practice__ai" href="<?php echo esc_url(get_permalink($omochix_tool)); ?>"><?php echo esc_html($omochix_term->name); ?></a><?php else : ?><span class="practice__ai"><?php echo esc_html($omochix_term->name); ?></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </header>

        <div class="practice__container practice__layout">
            <div class="practice__main">
                <?php if ('srcdoc' === $omochix_preview['type'] && '' !== $omochix_preview['src']) : ?>
                    <?php $omochix_doc = '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0;min-height:100%;font-family:system-ui,-apple-system,"Hiragino Sans","Noto Sans JP",sans-serif;background:#f7f7f5;color:#101010}</style></head><body>' . $omochix_preview['src'] . '</body></html>'; ?>
                    <figure class="practice__preview practice__preview--srcdoc">
                        <iframe class="practice__frame" sandbox="allow-scripts" srcdoc="<?php echo esc_attr($omochix_doc); ?>" title="<?php echo esc_attr(sprintf(__('%s のプレビュー', 'omochix'), get_the_title())); ?>" loading="lazy"></iframe>
                        <figcaption><?php esc_html_e('プレビュー（サンドボックス内で動作。送信などは模擬です）', 'omochix'); ?></figcaption>
                    </figure>
                <?php elseif ('youtube' === $omochix_preview['type'] && preg_match('/^[A-Za-z0-9_-]{11}$/', $omochix_preview['src'])) : ?>
                    <figure class="practice__preview practice__preview--youtube" data-practice-youtube="<?php echo esc_attr($omochix_preview['src']); ?>">
                        <a class="practice__youtube" href="<?php echo esc_url('https://www.youtube.com/watch?v=' . $omochix_preview['src']); ?>" rel="noopener" target="_blank">
                            <img src="<?php echo esc_url('https://i.ytimg.com/vi/' . $omochix_preview['src'] . '/hqdefault.jpg'); ?>" alt="" loading="lazy" width="480" height="360">
                            <span><?php esc_html_e('動画を再生', 'omochix'); ?></span>
                        </a>
                    </figure>
                <?php elseif ($omochix_poster) : ?>
                    <figure class="practice__preview practice__preview--image"><?php echo $omochix_poster; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image output. ?></figure>
                <?php endif; ?>

                <section class="practice__section" id="overview" aria-labelledby="practice-overview-title">
                    <h2 id="practice-overview-title"><?php esc_html_e('この教材で作るもの', 'omochix'); ?></h2>
                    <p><?php echo esc_html($omochix_item['outcome'] ?: $omochix_item['summary']); ?></p>
                    <?php if ($omochix_item['env']) : ?>
                        <p class="practice__env"><strong><?php esc_html_e('動作確認環境', 'omochix'); ?>:</strong> <?php echo esc_html(implode(' / ', $omochix_item['env'])); ?></p>
                    <?php endif; ?>
                </section>

                <?php if ($omochix_item['prereq']) : ?>
                    <section class="practice__section" id="prereq" aria-labelledby="practice-prereq-title">
                        <h2 id="practice-prereq-title"><?php esc_html_e('前提条件', 'omochix'); ?></h2>
                        <ul class="practice__list"><?php foreach ($omochix_item['prereq'] as $omochix_line) : ?><li><?php echo esc_html($omochix_line); ?></li><?php endforeach; ?></ul>
                    </section>
                <?php endif; ?>

                <?php if ($omochix_item['steps']) : ?>
                    <section class="practice__section" id="steps" aria-labelledby="practice-steps-title">
                        <h2 id="practice-steps-title"><?php esc_html_e('実装手順', 'omochix'); ?></h2>
                        <ol class="practice-steps">
                            <?php foreach ($omochix_item['steps'] as $omochix_i => $omochix_step) : ?>
                                <?php
                                $omochix_prompt = isset($omochix_step['prompt']) && is_int($omochix_step['prompt']) && isset($omochix_item['prompts'][$omochix_step['prompt']]) ? $omochix_item['prompts'][$omochix_step['prompt']] : null;
                                $omochix_code   = isset($omochix_step['code']) && is_int($omochix_step['code']) && isset($omochix_item['code'][$omochix_step['code']]) ? $omochix_item['code'][$omochix_step['code']] : null;
                                $omochix_sid    = 'practice-step-' . ($omochix_i + 1);
                                ?>
                                <li class="practice-step" id="<?php echo esc_attr($omochix_sid); ?>">
                                    <h3 class="practice-step__title"><span class="practice-step__number" aria-hidden="true"><?php echo (int) ($omochix_i + 1); ?></span><?php echo esc_html($omochix_step['title']); ?></h3>
                                    <?php if ('' !== $omochix_step['body']) : ?><div class="practice-step__body article-content"><?php echo wp_kses_post($omochix_step['body']); ?></div><?php endif; ?>
                                    <?php if ($omochix_prompt) : ?>
                                        <details class="practice-block practice-block--prompt" data-practice-copy-scope>
                                            <summary><?php esc_html_e('AI に渡すプロンプト', 'omochix'); ?><?php if ('' !== $omochix_prompt['title']) : ?><span><?php echo esc_html($omochix_prompt['title']); ?></span><?php endif; ?><?php if ('' !== $omochix_prompt['for']) : ?><em><?php echo esc_html($omochix_prompt['for']); ?></em><?php endif; ?></summary>
                                            <div class="practice-block__actions"><button type="button" class="practice-btn practice-btn--ghost" data-practice-copy="<?php echo esc_attr($omochix_sid); ?>-prompt"><?php esc_html_e('プロンプトをコピー', 'omochix'); ?></button><span class="sr-only" data-practice-copy-status aria-live="polite"></span></div>
                                            <pre class="practice-block__text" id="<?php echo esc_attr($omochix_sid); ?>-prompt" tabindex="0"><?php echo esc_html($omochix_prompt['body']); ?></pre>
                                        </details>
                                    <?php endif; ?>
                                    <?php if ($omochix_code) : ?>
                                        <details class="practice-block practice-block--code" data-practice-copy-scope>
                                            <summary><?php esc_html_e('コード例', 'omochix'); ?><?php if ('' !== $omochix_code['file']) : ?><span><?php echo esc_html($omochix_code['file']); ?></span><?php elseif ('' !== $omochix_code['label']) : ?><span><?php echo esc_html($omochix_code['label']); ?></span><?php endif; ?></summary>
                                            <div class="practice-block__actions"><button type="button" class="practice-btn practice-btn--ghost" data-practice-copy="<?php echo esc_attr($omochix_sid); ?>-code"><?php esc_html_e('コードをコピー', 'omochix'); ?></button><span class="sr-only" data-practice-copy-status aria-live="polite"></span></div>
                                            <pre class="practice-block__text" id="<?php echo esc_attr($omochix_sid); ?>-code" tabindex="0"><code class="language-<?php echo esc_attr($omochix_code['lang'] ?: 'text'); ?>"><?php echo esc_html($omochix_code['body']); ?></code></pre>
                                        </details>
                                    <?php endif; ?>
                                    <?php if ('' !== $omochix_step['check']) : ?><p class="practice-step__check"><strong><?php esc_html_e('確認', 'omochix'); ?>:</strong> <?php echo esc_html($omochix_step['check']); ?></p><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endif; ?>

                <?php if ($omochix_item['verify']) : ?>
                    <section class="practice__section" id="verify" aria-labelledby="practice-verify-title">
                        <h2 id="practice-verify-title"><?php esc_html_e('テスト・検証', 'omochix'); ?></h2>
                        <p class="practice__hint"><?php esc_html_e('チェックはこのブラウザにだけ保存されます。', 'omochix'); ?></p>
                        <table class="practice-verify">
                            <caption class="sr-only"><?php esc_html_e('検証項目', 'omochix'); ?></caption>
                            <thead><tr><th scope="col"><?php esc_html_e('済', 'omochix'); ?></th><th scope="col"><?php esc_html_e('項目', 'omochix'); ?></th><th scope="col"><?php esc_html_e('やり方', 'omochix'); ?></th><th scope="col"><?php esc_html_e('期待結果', 'omochix'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($omochix_item['verify'] as $omochix_i => $omochix_row) : ?>
                                    <tr>
                                        <td><input type="checkbox" id="practice-verify-<?php echo (int) $omochix_i; ?>" data-practice-check="<?php echo (int) $omochix_i; ?>" aria-label="<?php echo esc_attr(sprintf(__('「%s」を確認済みにする', 'omochix'), $omochix_row['title'])); ?>"></td>
                                        <th scope="row"><label for="practice-verify-<?php echo (int) $omochix_i; ?>"><?php echo esc_html($omochix_row['title']); ?></label></th>
                                        <td><?php echo nl2br(esc_html($omochix_row['how'])); ?></td>
                                        <td><?php echo nl2br(esc_html($omochix_row['expect'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </section>
                <?php endif; ?>

                <?php if ($omochix_item['safety']) : ?>
                    <section class="practice__section practice__section--safety" id="safety" aria-labelledby="practice-safety-title">
                        <h2 id="practice-safety-title"><?php esc_html_e('安全性・秘密情報', 'omochix'); ?></h2>
                        <ul class="practice__list"><?php foreach ($omochix_item['safety'] as $omochix_line) : ?><li><?php echo esc_html($omochix_line); ?></li><?php endforeach; ?></ul>
                        <p class="practice__hint"><?php esc_html_e('「＜ここに貼る：…＞」の形で書かれた値は、環境変数など公開されない場所に置きます。コード・リポジトリ・このページには入れません。', 'omochix'); ?></p>
                    </section>
                <?php endif; ?>

                <?php if ($omochix_item['publish']) : ?>
                    <section class="practice__section" id="publish" aria-labelledby="practice-publish-title">
                        <h2 id="practice-publish-title"><?php esc_html_e('公開手順', 'omochix'); ?></h2>
                        <ol class="practice__list"><?php foreach ($omochix_item['publish'] as $omochix_line) : ?><li><?php echo esc_html($omochix_line); ?></li><?php endforeach; ?></ol>
                    </section>
                <?php endif; ?>

                <section class="practice__section practice__brief" id="brief" aria-labelledby="practice-brief-title" data-practice-brief>
                    <h2 id="practice-brief-title"><?php esc_html_e('AIへの実装指示書', 'omochix'); ?></h2>
                    <p class="practice__hint"><?php esc_html_e('この教材の手順・プロンプト・検証・安全性を 1 枚にまとめた Markdown です。コピーして Claude Code や Codex に渡します。', 'omochix'); ?></p>
                    <div class="practice-brief__options" role="radiogroup" aria-label="<?php esc_attr_e('渡す相手', 'omochix'); ?>">
                        <?php foreach (omochix_learn_practice_assistants() as $omochix_key => $omochix_assistant) : ?>
                            <label><input type="radio" name="practice-assistant" value="<?php echo esc_attr($omochix_key); ?>" data-practice-assistant <?php checked('claude-code', $omochix_key); ?>> <?php echo esc_html($omochix_assistant['label']); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="practice-block__actions">
                        <button type="button" class="practice-btn" data-practice-copy="practice-brief-text"><?php esc_html_e('指示書をコピー', 'omochix'); ?></button>
                        <button type="button" class="practice-btn practice-btn--ghost" data-practice-download="practice-brief-text" data-practice-filename="<?php echo esc_attr(sanitize_title(get_post_field('post_name')) . '-brief.md'); ?>"><?php esc_html_e('.md で保存', 'omochix'); ?></button>
                        <?php if ($omochix_all_prompts) : ?><button type="button" class="practice-btn practice-btn--ghost" data-practice-copy="practice-all-prompts"><?php esc_html_e('プロンプトをまとめてコピー', 'omochix'); ?></button><?php endif; ?>
                        <span class="sr-only" data-practice-copy-status aria-live="polite"></span>
                    </div>
                    <pre class="practice-brief__text" id="practice-brief-text" tabindex="0" data-practice-brief-text data-brief-claude-code="<?php echo esc_attr($omochix_brief); ?>" data-brief-codex="<?php echo esc_attr($omochix_brief_codex); ?>"><?php echo esc_html($omochix_brief); ?></pre>
                    <?php if ($omochix_all_prompts) : ?><pre id="practice-all-prompts" hidden><?php echo esc_html($omochix_all_prompts); ?></pre><?php endif; ?>
                </section>

                <?php if ($omochix_motion['items'] || array_filter($omochix_related)) : ?>
                    <section class="practice__section" id="related" aria-labelledby="practice-related-title">
                        <h2 id="practice-related-title"><?php esc_html_e('あわせて使う', 'omochix'); ?></h2>
                        <?php if ($omochix_motion['items']) : ?>
                            <h3><?php esc_html_e('動く見本', 'omochix'); ?></h3>
                            <ul class="practice-links">
                                <?php foreach ($omochix_motion['items'] as $omochix_sample) : ?>
                                    <li><a href="<?php echo esc_url(omochix_learn_gallery_url($omochix_sample)); ?>"><span class="practice-links__kind"><?php echo esc_html('#' . $omochix_sample['id']); ?></span><?php echo esc_html($omochix_sample['name']['ja']); ?><span class="practice-links__en" lang="en"><?php echo esc_html($omochix_sample['name']['en']); ?></span></a></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ($omochix_motion['composer']) : ?><p><a class="practice-btn" href="<?php echo esc_url($omochix_motion['composer']); ?>"><?php esc_html_e('この組み合わせを Composer で開く', 'omochix'); ?><span aria-hidden="true">→</span></a></p><?php endif; ?>
                        <?php endif; ?>
                        <?php if ($omochix_related['practice']) : ?>
                            <?php get_template_part('template-parts/learn-practice/related', null, ['posts' => $omochix_related['practice'], 'title' => __('関連する教材', 'omochix'), 'eyebrow' => '', 'heading' => 'h3']); ?>
                        <?php endif; ?>
                        <?php foreach (['prompts' => __('使うプロンプト', 'omochix'), 'tools' => __('AIツール', 'omochix'), 'learn' => __('先に読む Learn', 'omochix')] as $omochix_kind => $omochix_label) : ?>
                            <?php if ($omochix_related[$omochix_kind]) : ?>
                                <h3><?php echo esc_html($omochix_label); ?></h3>
                                <ul class="practice-links">
                                    <?php foreach ($omochix_related[$omochix_kind] as $omochix_post) : ?>
                                        <li><a href="<?php echo esc_url(get_permalink($omochix_post)); ?>"><?php echo esc_html(get_the_title($omochix_post)); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <?php if ($omochix_item['sources']) : ?>
                    <section class="practice__section" id="sources" aria-labelledby="practice-sources-title">
                        <h2 id="practice-sources-title"><?php esc_html_e('参照した公式仕様', 'omochix'); ?></h2>
                        <ul class="practice__list">
                            <?php foreach ($omochix_item['sources'] as $omochix_row) : ?>
                                <li><?php if ('' !== $omochix_row['url']) : ?><a href="<?php echo esc_url($omochix_row['url']); ?>" rel="noopener" target="_blank"><?php echo esc_html($omochix_row['label'] ?: $omochix_row['url']); ?></a><?php else : ?><?php echo esc_html($omochix_row['label']); ?><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if ($omochix_item['changelog']) : ?>
                    <section class="practice__section" id="changelog" aria-labelledby="practice-changelog-title">
                        <h2 id="practice-changelog-title"><?php esc_html_e('更新履歴', 'omochix'); ?></h2>
                        <dl class="practice-changelog">
                            <?php foreach ($omochix_item['changelog'] as $omochix_row) : ?>
                                <div><dt><?php echo esc_html(str_replace('-', '.', $omochix_row['date'])); ?></dt><dd><?php echo esc_html($omochix_row['note']); ?></dd></div>
                            <?php endforeach; ?>
                        </dl>
                    </section>
                <?php endif; ?>

                <?php if (trim((string) get_the_content())) : ?>
                    <section class="practice__section article-content" aria-label="<?php esc_attr_e('補足', 'omochix'); ?>"><?php the_content(); ?></section>
                <?php endif; ?>
            </div>

            <aside class="practice__rail" aria-label="<?php esc_attr_e('ページ内の案内', 'omochix'); ?>">
                <nav class="practice-outline" aria-labelledby="practice-outline-title" data-practice-outline>
                    <p class="practice-outline__title" id="practice-outline-title"><?php esc_html_e('目次', 'omochix'); ?></p>
                    <ol>
                        <?php foreach ($omochix_sections as $omochix_id => $omochix_label) : ?><li><a href="#<?php echo esc_attr($omochix_id); ?>"><?php echo esc_html($omochix_label); ?></a></li><?php endforeach; ?>
                    </ol>
                </nav>
                <div class="practice-rail__actions">
                    <a class="practice-btn" href="#brief"><?php esc_html_e('AI実装指示書へ', 'omochix'); ?></a>
                    <?php if ($omochix_all_prompts) : ?><button type="button" class="practice-btn practice-btn--ghost" data-practice-copy="practice-all-prompts"><?php esc_html_e('プロンプトをまとめてコピー', 'omochix'); ?></button><?php endif; ?>
                    <span class="sr-only" data-practice-copy-status aria-live="polite"></span>
                </div>
                <?php if ($omochix_item['tech'] || $omochix_item['usecases']) : ?>
                    <dl class="practice-rail__facts">
                        <?php if ($omochix_item['tech']) : ?><div><dt><?php esc_html_e('使用技術', 'omochix'); ?></dt><dd><?php echo esc_html(implode(' / ', array_map(static fn($t) => $t->name, $omochix_item['tech']))); ?></dd></div><?php endif; ?>
                        <?php if ($omochix_item['usecases']) : ?><div><dt><?php esc_html_e('用途', 'omochix'); ?></dt><dd><?php foreach ($omochix_item['usecases'] as $omochix_term) : ?><a href="<?php echo esc_url(get_term_link($omochix_term)); ?>"><?php echo esc_html($omochix_term->name); ?></a> <?php endforeach; ?></dd></div><?php endif; ?>
                    </dl>
                <?php endif; ?>
            </aside>
        </div>

        <div class="practice__sticky" data-practice-sticky>
            <a class="practice-btn" href="#brief"><?php esc_html_e('指示書', 'omochix'); ?></a>
            <?php if ($omochix_all_prompts) : ?><button type="button" class="practice-btn practice-btn--ghost" data-practice-copy="practice-all-prompts"><?php esc_html_e('プロンプト', 'omochix'); ?></button><?php endif; ?>
            <a class="practice-btn practice-btn--ghost" href="#verify"><?php esc_html_e('検証', 'omochix'); ?></a>
            <span class="sr-only" data-practice-copy-status aria-live="polite"></span>
        </div>
    </main>
    <?php
endwhile;

get_footer();
