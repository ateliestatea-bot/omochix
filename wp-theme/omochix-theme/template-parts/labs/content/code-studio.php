<?php
/**
 * Code Studio (HTML/CSS, Phase 1): workspace and guide.
 *
 * The learner's code is never printed by PHP and never sent to the server:
 * the textareas start empty and assets/js/labs/ui/code-studio.js fills them
 * from the lesson data. The preview is an <iframe sandbox=""> built by that
 * script (docs/labs/CODE-STUDIO-DESIGN.md §5).
 *
 * @package OmochiX
 *
 * @var array $args { tool: array, part: 'workspace'|'guide' }
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_studio = omochix_labs_get_studio();
?>
<?php if ('workspace' === $args['part']) : ?>
    <div class="labs-studio" data-studio>
        <?php if (!$omochix_studio['lessons']) : ?>
            <p class="labs-notice labs-notice--warning"><?php esc_html_e('教材を読み込めませんでした。時間をおいて、もう一度お試しください。', 'omochix'); ?></p>
        <?php endif; ?>
        <div class="labs-options labs-studio__bar">
            <label class="labs-field labs-field--inline" for="labs-studio-lesson"><?php esc_html_e('教材', 'omochix'); ?>
                <select class="labs-select" id="labs-studio-lesson">
                    <?php foreach ($omochix_studio['lessons'] as $omochix_lesson) : ?>
                        <option value="<?php echo esc_attr($omochix_lesson['slug']); ?>"><?php echo esc_html($omochix_lesson['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="labs-toolbar" role="group" aria-label="<?php esc_attr_e('操作', 'omochix'); ?>">
                <button type="button" class="labs-button labs-button--ghost" data-labs-reset><?php esc_html_e('最初に戻す', 'omochix'); ?></button>
                <button type="button" class="labs-button labs-button--ghost" data-labs-copy><?php esc_html_e('コピー', 'omochix'); ?></button>
                <button type="button" class="labs-button labs-button--ghost" data-labs-download><?php esc_html_e('ファイルに保存', 'omochix'); ?></button>
            </div>
        </div>

        <section class="labs-studio__lesson" aria-labelledby="labs-studio-lesson-title">
            <h3 id="labs-studio-lesson-title" data-studio-title></h3>
            <p class="labs-studio__summary" data-studio-summary></p>
            <ol class="labs-studio__steps" data-studio-steps></ol>
            <h4><?php esc_html_e('課題', 'omochix'); ?></h4>
            <ul class="labs-studio__tasks" data-studio-tasks></ul>
            <div class="labs-studio__check">
                <button type="button" class="labs-button labs-button--primary" data-labs-run><?php esc_html_e('答え合わせ', 'omochix'); ?></button>
                <p class="labs-studio__progress" data-studio-progress></p>
            </div>
            <p class="labs-studio__learn" data-studio-learn hidden><a href="#" data-studio-learn-link><?php esc_html_e('Learn の教材で、くわしい説明を読む →', 'omochix'); ?></a></p>
        </section>

        <div class="labs-panes labs-studio__panes">
            <div class="labs-pane">
                <label class="labs-pane__label" for="labs-studio-html"><?php esc_html_e('HTML（ページの中身）', 'omochix'); ?></label>
                <textarea class="labs-textarea labs-code" id="labs-studio-html" rows="10" spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off" aria-describedby="labs-studio-hint"></textarea>
                <label class="labs-pane__label" for="labs-studio-css"><?php esc_html_e('CSS（見た目）', 'omochix'); ?></label>
                <textarea class="labs-textarea labs-code" id="labs-studio-css" rows="10" spellcheck="false" autocapitalize="off" autocomplete="off" autocorrect="off" aria-describedby="labs-studio-hint"></textarea>
                <p class="labs-hint" id="labs-studio-hint"><?php esc_html_e('書き換えると、プレビューが自動で変わります。HTML・CSS はそれぞれ50KBまで。JavaScript は動きません。', 'omochix'); ?></p>
            </div>
            <div class="labs-pane">
                <div class="labs-pane__head">
                    <h3 class="labs-pane__label" id="labs-studio-preview-title"><?php esc_html_e('プレビュー', 'omochix'); ?></h3>
                    <fieldset class="labs-radios labs-studio__widths">
                        <legend class="labs-sr-only"><?php esc_html_e('プレビューの画面幅', 'omochix'); ?></legend>
                        <label class="labs-check" for="labs-studio-width-pc"><input type="radio" name="labs-studio-width" id="labs-studio-width-pc" value="pc" checked> <?php esc_html_e('PC', 'omochix'); ?></label>
                        <label class="labs-check" for="labs-studio-width-tablet"><input type="radio" name="labs-studio-width" id="labs-studio-width-tablet" value="tablet"> <?php esc_html_e('タブレット', 'omochix'); ?></label>
                        <label class="labs-check" for="labs-studio-width-phone"><input type="radio" name="labs-studio-width" id="labs-studio-width-phone" value="phone"> <?php esc_html_e('スマホ', 'omochix'); ?></label>
                    </fieldset>
                </div>
                <div class="labs-studio__stage" data-studio-stage data-width="pc"></div>
                <p class="labs-hint" data-studio-size></p>
                <p class="labs-notice labs-notice--warning" data-studio-notes hidden></p>
            </div>
        </div>

        <details class="labs-studio__compare" data-studio-compare>
            <summary><?php esc_html_e('変更前と比べる', 'omochix'); ?></summary>
            <div class="labs-studio__pair">
                <figure>
                    <figcaption><?php esc_html_e('最初の表示', 'omochix'); ?></figcaption>
                    <div class="labs-studio__stage" data-studio-before></div>
                </figure>
                <figure>
                    <figcaption><?php esc_html_e('今の表示', 'omochix'); ?></figcaption>
                    <div class="labs-studio__stage" data-studio-after></div>
                </figure>
            </div>
            <h4><?php esc_html_e('コードの変更点', 'omochix'); ?></h4>
            <div class="labs-studio__diff" data-studio-diff></div>
        </details>

        <section class="labs-studio__explain" aria-labelledby="labs-studio-explain-title">
            <h3 id="labs-studio-explain-title"><?php esc_html_e('このコードで使っているタグとプロパティ', 'omochix'); ?></h3>
            <dl class="labs-glossary" data-studio-glossary></dl>
        </section>
    </div>
<?php else : ?>
    <section>
        <h3><?php esc_html_e('使い方', 'omochix'); ?></h3>
        <ol>
            <li><?php esc_html_e('上の「教材」から、取り組みたい内容を選びます。はじめての方は「はじめてのHTML」から順に進めるのがおすすめです。', 'omochix'); ?></li>
            <li><?php esc_html_e('手順を読みながら、HTML と CSS の入力欄を書き換えます。書いた内容は、右のプレビューにすぐ反映されます。', 'omochix'); ?></li>
            <li><?php esc_html_e('「答え合わせ」を押すと、課題の達成条件を満たしているかを確かめて、達成できた課題に印を付けます。', 'omochix'); ?></li>
            <li><?php esc_html_e('「PC」「タブレット」「スマホ」を切り替えると、画面の幅による見え方の違いを確かめられます。', 'omochix'); ?></li>
            <li><?php esc_html_e('「変更前と比べる」を開くと、最初の表示と今の表示を並べて見られます。書き換えた行も一覧で確認できます。', 'omochix'); ?></li>
            <li><?php esc_html_e('うまくいかなくなったら「最初に戻す」で教材の最初の状態に戻せます。できあがったコードは「コピー」や「ファイルに保存」で持ち帰れます。', 'omochix'); ?></li>
        </ol>
    </section>
    <section>
        <h3><?php esc_html_e('HTML と CSS の役割', 'omochix'); ?></h3>
        <p><?php esc_html_e('Web ページは、HTML と CSS という2種類の言葉でできています。HTML は「これは見出し」「これは段落」「これは箇条書き」というように、文章の意味と構造を伝える言葉です。CSS は、その HTML に対して「見出しは紫色に」「カードには余白と角の丸みを」というように、見た目を指定する言葉です。', 'omochix'); ?></p>
        <p><?php esc_html_e('HTML は <h1> や <p> のような「タグ」で文章を囲んで書きます。CSS は「どこに（セレクタ）」「何を（プロパティ）」「どうする（値）」の3つを組にして書きます。たとえば h1 { color: purple; } は、「h1 の」「文字の色を」「紫にする」という意味です。', 'omochix'); ?></p>
        <p><?php esc_html_e('入力欄の下の「このコードで使っているタグとプロパティ」には、いま書いてあるコードに出てくるタグとプロパティの説明が並びます。知らない言葉が出てきたら、ここで意味を確かめながら進めてください。', 'omochix'); ?></p>
    </section>
    <section>
        <h3><?php esc_html_e('うまく表示されないときに確かめること', 'omochix'); ?></h3>
        <dl class="labs-glossary">
            <dt><?php esc_html_e('タグを閉じ忘れていないか', 'omochix'); ?></dt>
            <dd><?php esc_html_e('<p> で始めたら </p> で閉じます。閉じ忘れると、後ろの文章までその要素の中に入ってしまいます。', 'omochix'); ?></dd>
            <dt><?php esc_html_e('CSS の記号が抜けていないか', 'omochix'); ?></dt>
            <dd><?php esc_html_e('プロパティと値のあいだはコロン（:）、1行の終わりはセミコロン（;）、まとまりは波かっこ（{ }）で囲みます。どれかが抜けると、その行から後ろが効かなくなることがあります。', 'omochix'); ?></dd>
            <dt><?php esc_html_e('セレクタが HTML と合っているか', 'omochix'); ?></dt>
            <dd><?php esc_html_e('.card は class="card" の要素を指します。HTML 側の class の名前と、CSS 側のピリオドの後ろの名前が同じかを確かめてください。', 'omochix'); ?></dd>
            <dt><?php esc_html_e('スマホの幅ではみ出していないか', 'omochix'); ?></dt>
            <dd><?php esc_html_e('width を px で固定すると、狭い画面に入りきらないことがあります。max-width を使うか、メディアクエリで狭い画面のときの指定を書き足します。', 'omochix'); ?></dd>
        </dl>
    </section>
    <section>
        <h3><?php esc_html_e('安全のための制限', 'omochix'); ?></h3>
        <p><?php esc_html_e('プレビューは、このページとは切り離された枠の中に表示します。その枠では JavaScript が動かず、外部の画像・フォント・リンク先を読み込むこともできません。script タグや onclick などの属性、外部の URL は、表示の前に自動で取り除きます。リンク（a タグ）は見た目だけを確かめられ、押しても移動しません。', 'omochix'); ?></p>
        <p><?php esc_html_e('入力したコードは、お使いのブラウザの中だけで処理します。サーバーには送信も保存もしません。ページを再読み込みすると教材の最初の状態に戻るので、残しておきたいコードは「コピー」または「ファイルに保存」を使ってください。', 'omochix'); ?></p>
    </section>
<?php endif; ?>
