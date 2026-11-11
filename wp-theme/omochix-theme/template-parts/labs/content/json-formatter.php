<?php
/**
 * JSON formatter: workspace and guide.
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
        <button type="button" class="labs-button labs-button--primary" data-labs-run data-action="format"><?php esc_html_e('整形する', 'omochix'); ?></button>
        <button type="button" class="labs-button" data-labs-run data-action="minify"><?php esc_html_e('圧縮する', 'omochix'); ?></button>
        <button type="button" class="labs-button labs-button--ghost" data-labs-sample><?php esc_html_e('入力例', 'omochix'); ?></button>
        <button type="button" class="labs-button labs-button--ghost" data-labs-reset><?php esc_html_e('リセット', 'omochix'); ?></button>
    </div>
    <div class="labs-options">
        <label class="labs-field labs-field--inline" for="labs-json-indent"><?php esc_html_e('インデント', 'omochix'); ?>
            <select class="labs-select" id="labs-json-indent">
                <option value="2"><?php esc_html_e('スペース2', 'omochix'); ?></option>
                <option value="4"><?php esc_html_e('スペース4', 'omochix'); ?></option>
                <option value="tab"><?php esc_html_e('タブ', 'omochix'); ?></option>
            </select>
        </label>
        <label class="labs-check" for="labs-json-sort"><input type="checkbox" id="labs-json-sort"> <?php esc_html_e('キーを辞書順に並べ替える', 'omochix'); ?></label>
    </div>
    <div class="labs-panes">
        <div class="labs-pane">
            <label class="labs-pane__label" for="labs-json-input"><?php esc_html_e('入力（JSON）', 'omochix'); ?></label>
            <textarea class="labs-textarea labs-code" id="labs-json-input" rows="14" spellcheck="false" autocapitalize="off" autocomplete="off" aria-describedby="labs-json-hint"></textarea>
            <p class="labs-hint" id="labs-json-hint"><?php esc_html_e('最大5MBまで。Ctrl（Mac は ⌘）+ Enter でも整形できます。', 'omochix'); ?></p>
        </div>
        <div class="labs-pane">
            <div class="labs-pane__head">
                <label class="labs-pane__label" for="labs-json-output"><?php esc_html_e('結果', 'omochix'); ?></label>
                <div class="labs-pane__actions">
                    <button type="button" class="labs-button labs-button--small" data-labs-copy disabled><?php esc_html_e('コピー', 'omochix'); ?></button>
                    <button type="button" class="labs-button labs-button--small" data-labs-download disabled><?php esc_html_e('保存', 'omochix'); ?></button>
                </div>
            </div>
            <textarea class="labs-textarea labs-code" id="labs-json-output" rows="14" readonly spellcheck="false"></textarea>
            <dl class="labs-stats" data-labs-stats hidden></dl>
        </div>
    </div>
    <div class="labs-warnings" data-labs-warnings hidden>
        <h3><?php esc_html_e('注意', 'omochix'); ?></h3>
        <ul></ul>
    </div>
<?php else : ?>
    <section>
        <h3><?php esc_html_e('使い方', 'omochix'); ?></h3>
        <ol>
            <li><?php esc_html_e('左の入力欄にJSONを貼り付けます。形を確かめたいだけなら「入力例」を押すと見本が入ります。', 'omochix'); ?></li>
            <li><?php esc_html_e('「整形する」を押すと、読みやすい形（インデント付き）に直した結果が右に出ます。1行にまとめたいときは「圧縮する」を押します。', 'omochix'); ?></li>
            <li><?php esc_html_e('エラーがあると、何行目の何文字目がおかしいかを、該当行と「^」の目印付きで表示します。直したらもう一度「整形する」を押してください。', 'omochix'); ?></li>
            <li><?php esc_html_e('結果は「コピー」でクリップボードへ、「保存」で .json ファイルとして手元に保存できます（ファイルはブラウザの中で作られます）。', 'omochix'); ?></li>
        </ol>
    </section>
    <section>
        <h3><?php esc_html_e('JSONとは', 'omochix'); ?></h3>
        <p><?php esc_html_e('JSON（JavaScript Object Notation）は、データを「キーと値の組」と「配列」で表すテキスト形式です。Web API のやり取り、設定ファイル、ChatGPT や Claude などの AI API へのリクエストや出力の指定など、開発のあらゆる場面で使われます。決まりが少ない一方で、カンマ1つ・引用符1つの違いでも読み込めなくなるため、目で探すより機械で確かめるほうが確実です。', 'omochix'); ?></p>
    </section>
    <section>
        <h3><?php esc_html_e('よくあるエラーと直し方', 'omochix'); ?></h3>
        <dl class="labs-glossary">
            <dt><?php esc_html_e('最後の要素の後のカンマ', 'omochix'); ?></dt>
            <dd><?php esc_html_e('{"a": 1, } のように、閉じる } や ] の直前にカンマがあるとJSONではエラーです。JavaScript のコードからコピーしたときに起きやすい間違いです。', 'omochix'); ?></dd>
            <dt><?php esc_html_e('キーが引用符で囲まれていない', 'omochix'); ?></dt>
            <dd><?php esc_html_e('{name: "omochi"} は JavaScript のオブジェクトとしては書けますが、JSONではキーも "name" のように二重引用符で囲む必要があります。', 'omochix'); ?></dd>
            <dt><?php esc_html_e('単一引用符・コメント', 'omochix'); ?></dt>
            <dd><?php esc_html_e('\'text\' のような単一引用符や、// で始まるコメントはJSONの仕様にありません。設定ファイル（JSONC や JSON5）から持ってきた場合は取り除いてください。', 'omochix'); ?></dd>
            <dt><?php esc_html_e('文字列の中の改行', 'omochix'); ?></dt>
            <dd><?php esc_html_e('文字列の中に改行やタブをそのまま入れることはできません。\n（改行）や \t（タブ）のように書きます。', 'omochix'); ?></dd>
            <dt><?php esc_html_e('余分なデータ', 'omochix'); ?></dt>
            <dd><?php esc_html_e('JSONの値は全体で1つです。ログのように {…}{…} と並んでいる場合は、[ ] で囲んでカンマで区切るか、1件ずつ整形してください。', 'omochix'); ?></dd>
        </dl>
    </section>
    <section>
        <h3><?php esc_html_e('このツールの特長と注意点', 'omochix'); ?></h3>
        <ul>
            <li><?php esc_html_e('数値と文字列は書かれたとおりに残します。9007199254740991 を超える整数（データベースのIDなど）も丸めずに整形でき、その場合は注意を表示します。', 'omochix'); ?></li>
            <li><?php esc_html_e('同じオブジェクトの中で同じキーが2回出てきた場合は注意を表示します。多くの処理系では後に書いた値だけが使われます。', 'omochix'); ?></li>
            <li><?php esc_html_e('「キーを辞書順に並べ替える」をオンにすると、オブジェクトのキーを文字コード順に並べます。2つのJSONを比べる前にそろえておくと、テキスト差分比較で違いを見つけやすくなります。', 'omochix'); ?></li>
            <li><?php esc_html_e('入力は5MBまでです。256KBを超える入力は Web Worker（別スレッド）で処理し、10秒を超えた場合は中断します。', 'omochix'); ?></li>
        </ul>
    </section>
<?php endif; ?>
