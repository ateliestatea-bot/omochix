<?php
/**
 * Text diff: workspace and guide.
 *
 * @package OmochiX
 *
 * @var array $args { tool: array, part: 'workspace'|'guide' }
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<?php if ('workspace' === $args['part']) : ?>
    <div class="labs-toolbar" role="group" aria-label="<?php esc_attr_e('操作', 'omochix'); ?>">
        <button type="button" class="labs-button labs-button--primary" data-labs-run data-action="compare"><?php esc_html_e('比較する', 'omochix'); ?></button>
        <button type="button" class="labs-button labs-button--ghost" data-labs-sample><?php esc_html_e('入力例', 'omochix'); ?></button>
        <button type="button" class="labs-button labs-button--ghost" data-labs-swap><?php esc_html_e('左右を入れ替え', 'omochix'); ?></button>
        <button type="button" class="labs-button labs-button--ghost" data-labs-reset><?php esc_html_e('リセット', 'omochix'); ?></button>
    </div>
    <div class="labs-options">
        <label class="labs-check" for="labs-diff-ws"><input type="checkbox" id="labs-diff-ws"> <?php esc_html_e('空白の違いを無視', 'omochix'); ?></label>
        <label class="labs-check" for="labs-diff-case"><input type="checkbox" id="labs-diff-case"> <?php esc_html_e('大文字・小文字を無視', 'omochix'); ?></label>
        <fieldset class="labs-radios">
            <legend><?php esc_html_e('表示', 'omochix'); ?></legend>
            <label for="labs-diff-view-unified"><input type="radio" name="labs-diff-view" id="labs-diff-view-unified" value="unified" checked> <?php esc_html_e('まとめて表示', 'omochix'); ?></label>
            <label for="labs-diff-view-split"><input type="radio" name="labs-diff-view" id="labs-diff-view-split" value="split"> <?php esc_html_e('左右に並べる', 'omochix'); ?></label>
        </fieldset>
    </div>
    <div class="labs-panes">
        <div class="labs-pane">
            <label class="labs-pane__label" for="labs-diff-old"><?php esc_html_e('変更前', 'omochix'); ?></label>
            <textarea class="labs-textarea labs-code" id="labs-diff-old" rows="12" spellcheck="false" autocapitalize="off" autocomplete="off" aria-describedby="labs-diff-hint"></textarea>
        </div>
        <div class="labs-pane">
            <label class="labs-pane__label" for="labs-diff-new"><?php esc_html_e('変更後', 'omochix'); ?></label>
            <textarea class="labs-textarea labs-code" id="labs-diff-new" rows="12" spellcheck="false" autocapitalize="off" autocomplete="off" aria-describedby="labs-diff-hint"></textarea>
        </div>
    </div>
    <p class="labs-hint" id="labs-diff-hint"><?php esc_html_e('それぞれ1MB・2万行まで。Ctrl（Mac は ⌘）+ Enter でも比較できます。', 'omochix'); ?></p>
    <div class="labs-result" data-labs-result hidden>
        <div class="labs-pane__head">
            <h3 class="labs-result__title" id="labs-diff-result-title" tabindex="-1"><?php esc_html_e('比較結果', 'omochix'); ?></h3>
            <div class="labs-pane__actions">
                <button type="button" class="labs-button labs-button--small" data-labs-copy><?php esc_html_e('diff形式でコピー', 'omochix'); ?></button>
            </div>
        </div>
        <p class="labs-diff-summary" data-labs-summary></p>
        <div class="labs-diff" role="region" aria-labelledby="labs-diff-result-title" tabindex="0" data-labs-output></div>
    </div>
<?php else : ?>
    <section>
        <h3><?php esc_html_e('使い方', 'omochix'); ?></h3>
        <ol>
            <li><?php esc_html_e('「変更前」と「変更後」にテキストを貼り付けます。文章・設定ファイル・コード・CSV など、テキストなら何でも比べられます。', 'omochix'); ?></li>
            <li><?php esc_html_e('「比較する」を押すと、追加された行は「+」、削除された行は「−」付きで色分けして表示します。変更された行の中では、変わった文字や単語だけをさらに強調します。', 'omochix'); ?></li>
            <li><?php esc_html_e('画面が狭いときは「まとめて表示」、変更前後を見比べたいときは「左右に並べる」が見やすくなります。', 'omochix'); ?></li>
            <li><?php esc_html_e('「diff形式でコピー」を押すと、git diff などと同じ unified diff 形式でコピーできます。レビュー依頼やAIへの相談にそのまま貼れます。', 'omochix'); ?></li>
        </ol>
    </section>
    <section>
        <h3><?php esc_html_e('差分の見方', 'omochix'); ?></h3>
        <p><?php esc_html_e('差分（diff）は「変更前を変更後にするには、どの行を消してどの行を足せばよいか」を最小の手数で示したものです。このツールは、Git などでも使われている Myers のアルゴリズムで行ごとの違いを求めます。そのうえで、1行の中の小さな修正も見落とさないよう、変更された行どうしを日本語なら1文字ずつ、英数字なら単語ごとに比べ直します。', 'omochix'); ?></p>
        <p><?php esc_html_e('色だけでなく行頭の「+」「−」でも区別できるので、色の見分けにくい環境や印刷でも読めます。', 'omochix'); ?></p>
    </section>
    <section>
        <h3><?php esc_html_e('オプションの使いどころ', 'omochix'); ?></h3>
        <dl class="labs-glossary">
            <dt><?php esc_html_e('空白の違いを無視', 'omochix'); ?></dt>
            <dd><?php esc_html_e('インデントの深さや、行末・単語間のスペースの数だけが違う行を同じものとして扱います。整形し直したコードや、コピーで空白が崩れた文章を比べるときに便利です。', 'omochix'); ?></dd>
            <dt><?php esc_html_e('大文字・小文字を無視', 'omochix'); ?></dt>
            <dd><?php esc_html_e('「Labs」と「labs」のような違いを無視します。SQL のキーワードや URL を比べるときに役立ちます。', 'omochix'); ?></dd>
            <dt><?php esc_html_e('改行コード', 'omochix'); ?></dt>
            <dd><?php esc_html_e('Windows（CRLF）と Mac・Linux（LF）の改行コードの違いは、常に同じものとして扱います（貼り付けた時点でブラウザが改行をそろえるため、改行コードだけの違いは差分になりません）。', 'omochix'); ?></dd>
        </dl>
    </section>
    <section>
        <h3><?php esc_html_e('注意点', 'omochix'); ?></h3>
        <ul>
            <li><?php esc_html_e('比較は Web Worker（別スレッド）で行い、10秒を超えたら中断します。違いが非常に多い場合は、正確な最小差分ではなく「まとめて削除・追加」の簡易表示に切り替え、その旨を表示します。', 'omochix'); ?></li>
            <li><?php esc_html_e('画面に表示するのは最初の5,000行までです。全体は「diff形式でコピー」で取り出せます。', 'omochix'); ?></li>
            <li><?php esc_html_e('JSONを比べるときは、先に JSON整形・検証ツールで同じインデント・キー順にそろえると、意味のある違いだけが残ります。', 'omochix'); ?></li>
        </ul>
    </section>
    <section>
        <h3><?php esc_html_e('こんな場面で使えます', 'omochix'); ?></h3>
        <ul>
            <li><?php esc_html_e('契約書や規約の改訂版：どの条文の、どの言葉が変わったかを1文字単位で確かめる。', 'omochix'); ?></li>
            <li><?php esc_html_e('AIに書き直してもらった文章：元の文章と並べて、意図しない言い換えや抜け落ちがないかを確認する。', 'omochix'); ?></li>
            <li><?php esc_html_e('設定ファイルや環境変数：本番と検証環境の .env や設定ファイルを比べ、値の違いを洗い出す。', 'omochix'); ?></li>
            <li><?php esc_html_e('コードレビューの前後：手元の修正を貼り付けて、コミット前に変更点を見直す。', 'omochix'); ?></li>
            <li><?php esc_html_e('翻訳や校正の確認：校正前後の原稿を比べ、表記ゆれの修正漏れを見つける。', 'omochix'); ?></li>
        </ul>
    </section>
    <section>
        <h3><?php esc_html_e('unified diff の読み方', 'omochix'); ?></h3>
        <p><?php esc_html_e('「diff形式でコピー」で得られるテキストは、Git や多くのレビューツールで使われる unified diff 形式です。先頭の「---」が変更前、「+++」が変更後の名前を表します。', 'omochix'); ?></p>
        <p><?php esc_html_e('「@@ -10,4 +10,5 @@」のような行は、変更前の10行目から4行分が、変更後の10行目から5行分に対応するという意味です。続く行の先頭が半角スペースなら変更なし、「-」なら削除、「+」なら追加です。変更の前後3行ずつを文脈として含めています。', 'omochix'); ?></p>
    </section>
<?php endif; ?>
