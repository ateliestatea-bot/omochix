<?php
/**
 * AI token / API cost calculator: workspace and guide. Prices come from
 * assets/data/labs/pricing.json through omochix_labs_get_pricing(); only
 * rows that apply today (verified within 90 days, inside their date window)
 * are listed, and the browser re-checks them with its own date.
 *
 * @package OmochiX
 *
 * @var array $args { tool: array, part: 'workspace'|'guide' }
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_pricing = omochix_labs_get_pricing();
$omochix_models  = omochix_labs_pricing_active($omochix_pricing['models'], omochix_labs_today(), $omochix_pricing['max_age_days']);
$omochix_default = ['claude-sonnet-5-5', 'gpt-6.1-sol', 'gemini-3.8-flash'];
?>
<?php if ('workspace' === $args['part']) : ?>
    <p class="labs-notice labs-notice--warning"><strong><?php esc_html_e('目安の計算です。', 'omochix'); ?></strong><?php esc_html_e('トークン数は文字の種類から推定した値で、金額は公開されている標準ティアの料金から計算した概算です。キャッシュ書き込みの料金は計算に含みません。実際の請求額を保証するものではありません。', 'omochix'); ?></p>
    <div class="labs-toolbar" role="group" aria-label="<?php esc_attr_e('操作', 'omochix'); ?>">
        <button type="button" class="labs-button labs-button--ghost" data-labs-sample><?php esc_html_e('入力例', 'omochix'); ?></button>
        <button type="button" class="labs-button labs-button--ghost" data-labs-reset><?php esc_html_e('リセット', 'omochix'); ?></button>
    </div>
    <div class="labs-panes">
        <div class="labs-pane">
            <label class="labs-pane__label" for="labs-cost-text"><?php esc_html_e('トークン数を推定する文章（任意）', 'omochix'); ?></label>
            <textarea class="labs-textarea" id="labs-cost-text" rows="8" aria-describedby="labs-cost-text-hint"></textarea>
            <p class="labs-hint" id="labs-cost-text-hint"><?php esc_html_e('プロンプトや想定される回答を貼ると、トークン数の目安を表示します（1MBまで）。', 'omochix'); ?></p>
            <dl class="labs-stats" data-labs-estimate>
                <div><dt><?php esc_html_e('文字数', 'omochix'); ?></dt><dd data-labs-chars>0</dd></div>
                <div><dt><?php esc_html_e('推定トークン数', 'omochix'); ?></dt><dd data-labs-tokens>0</dd></div>
                <div><dt><?php esc_html_e('推定の幅', 'omochix'); ?></dt><dd data-labs-range>—</dd></div>
            </dl>
            <div class="labs-toolbar labs-toolbar--small">
                <button type="button" class="labs-button labs-button--small" data-labs-use="input"><?php esc_html_e('入力トークンに使う', 'omochix'); ?></button>
                <button type="button" class="labs-button labs-button--small" data-labs-use="output"><?php esc_html_e('出力トークンに使う', 'omochix'); ?></button>
            </div>
        </div>
        <div class="labs-pane">
            <fieldset class="labs-fieldset">
                <legend><?php esc_html_e('使い方の想定（1回あたり）', 'omochix'); ?></legend>
                <div class="labs-form-grid">
                    <label class="labs-field" for="labs-cost-input"><?php esc_html_e('入力トークン', 'omochix'); ?><input class="labs-input" type="number" id="labs-cost-input" min="0" max="1000000000" step="1" inputmode="numeric" value="2000"></label>
                    <label class="labs-field" for="labs-cost-output"><?php esc_html_e('出力トークン', 'omochix'); ?><input class="labs-input" type="number" id="labs-cost-output" min="0" max="1000000000" step="1" inputmode="numeric" value="800"></label>
                    <label class="labs-field" for="labs-cost-cached"><?php esc_html_e('キャッシュから読む入力トークン', 'omochix'); ?><input class="labs-input" type="number" id="labs-cost-cached" min="0" max="1000000000" step="1" inputmode="numeric" value="0"></label>
                    <label class="labs-field" for="labs-cost-requests"><?php esc_html_e('1日の回数', 'omochix'); ?><input class="labs-input" type="number" id="labs-cost-requests" min="0" max="10000000" step="1" inputmode="numeric" value="100"></label>
                    <label class="labs-field" for="labs-cost-days"><?php esc_html_e('日数', 'omochix'); ?><input class="labs-input" type="number" id="labs-cost-days" min="0" max="3660" step="1" inputmode="numeric" value="30"></label>
                    <label class="labs-field" for="labs-cost-rate"><?php esc_html_e('為替（1ドル＝何円、任意）', 'omochix'); ?><input class="labs-input" type="number" id="labs-cost-rate" min="0" max="10000" step="0.01" inputmode="decimal" placeholder="<?php esc_attr_e('例：150', 'omochix'); ?>"></label>
                </div>
            </fieldset>
        </div>
    </div>

    <fieldset class="labs-fieldset" data-labs-models-fieldset>
        <legend><?php esc_html_e('比べるモデル', 'omochix'); ?></legend>
        <div class="labs-models" data-labs-models>
            <?php if ($omochix_models) : ?>
                <?php foreach ($omochix_pricing['providers'] as $omochix_provider => $omochix_provider_name) : ?>
                    <?php $omochix_rows = array_filter($omochix_models, static fn($m) => $m['provider'] === $omochix_provider); ?>
                    <?php if (!$omochix_rows) { continue; } ?>
                    <div class="labs-models__group">
                        <p class="labs-models__provider"><?php echo esc_html($omochix_provider_name); ?></p>
                        <?php foreach ($omochix_rows as $omochix_model) : ?>
                            <label class="labs-check"><input type="checkbox" value="<?php echo esc_attr($omochix_model['id']); ?>" data-labs-model<?php echo in_array($omochix_model['id'], $omochix_default, true) ? ' checked' : ''; ?>> <?php echo esc_html($omochix_model['label']); ?></label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p class="labs-hint" data-labs-no-models<?php echo $omochix_models ? ' hidden' : ''; ?>><?php echo esc_html(!empty($omochix_pricing['withheld']) ? __('料金データの確認が完了するまで、料金の一覧は表示していません。トークン数の推定はご利用いただけます。', 'omochix') : __('現在、確認済みの価格がありません。価格の確認後に表示します。', 'omochix')); ?></p>
    </fieldset>

    <div class="labs-result" data-labs-result>
        <div class="labs-pane__head">
            <h3 class="labs-result__title" id="labs-cost-result-title"><?php esc_html_e('料金の目安（USD）', 'omochix'); ?></h3>
            <div class="labs-pane__actions">
                <button type="button" class="labs-button labs-button--small" data-labs-copy><?php esc_html_e('結果をコピー', 'omochix'); ?></button>
            </div>
        </div>
        <div class="labs-table-wrap" role="region" aria-labelledby="labs-cost-result-title" tabindex="0">
            <table class="labs-table labs-table--cost">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('モデル', 'omochix'); ?></th>
                        <th scope="col"><?php esc_html_e('1回', 'omochix'); ?></th>
                        <th scope="col"><?php esc_html_e('1日', 'omochix'); ?></th>
                        <th scope="col" data-labs-period-head><?php esc_html_e('期間合計', 'omochix'); ?></th>
                        <th scope="col"><?php esc_html_e('単価（100万トークン）', 'omochix'); ?></th>
                        <th scope="col"><?php esc_html_e('出典（確認日）', 'omochix'); ?></th>
                    </tr>
                </thead>
                <tbody data-labs-cost-rows></tbody>
            </table>
        </div>
        <p class="labs-hint" data-labs-cost-notes></p>
    </div>
<?php else : ?>
    <section>
        <h3><?php esc_html_e('使い方', 'omochix'); ?></h3>
        <ol>
            <li><?php esc_html_e('送る予定のプロンプトや、返ってくる回答の例を左の欄に貼ると、トークン数の目安が出ます。「入力トークンに使う」「出力トークンに使う」で右の欄に反映できます。', 'omochix'); ?></li>
            <li><?php esc_html_e('1回あたりの入力・出力トークン数、1日の回数、日数を入れます。プロンプトキャッシュを使う場合は、キャッシュから読む分を「キャッシュから読む入力トークン」に分けて入れます。', 'omochix'); ?></li>
            <li><?php esc_html_e('比べたいモデルにチェックを入れると、1回・1日・期間合計の料金の目安を一覧で表示します。為替レートを入れると円の目安も並べます。', 'omochix'); ?></li>
        </ol>
    </section>
    <section>
        <h3><?php esc_html_e('トークンと料金の関係', 'omochix'); ?></h3>
        <p><?php esc_html_e('AIのAPIは、送った文章（入力）と返ってきた文章（出力）を「トークン」という単位で数え、100万トークンあたりの単価で課金するのが一般的です。出力の単価は入力の数倍に設定されていることが多く、長い回答を求める使い方ほど出力側の金額が大きくなります。', 'omochix'); ?></p>
        <p><?php esc_html_e('計算式は「入力トークン × 入力単価 ＋ キャッシュ読み込みトークン × キャッシュ単価 ＋ 出力トークン × 出力単価」を100万で割ったものです。一部のモデルは、1回の入力が一定のトークン数を超えると単価が上がります（長いコンテキストの料金）。このツールはその境目も計算に入れます。', 'omochix'); ?></p>
    </section>
    <section>
        <h3><?php esc_html_e('トークン数の推定方法', 'omochix'); ?></h3>
        <p><?php esc_html_e('正確なトークン数はモデルごとのトークナイザーで決まり、同じ文章でもモデルによって変わります。このツールは文字の種類ごとに目安の係数を掛けて推定しています。', 'omochix'); ?></p>
        <ul>
            <li><?php esc_html_e('日本語（漢字・ひらがな・カタカナ）：1文字あたり約1トークン（0.7〜1.4）', 'omochix'); ?></li>
            <li><?php esc_html_e('英語：1単語あたり約1.3トークン（1.0〜1.7）', 'omochix'); ?></li>
            <li><?php esc_html_e('数字：約3桁で1トークン、記号：1文字あたり約1トークン', 'omochix'); ?></li>
        </ul>
        <p><?php esc_html_e('「推定の幅」は、この係数の上下限で計算した範囲です。予算を立てるときは幅の上限側で見積もると安全です。正確な値は、各社のトークン数カウント機能や、APIの応答に含まれる使用量で確認してください。', 'omochix'); ?></p>
    </section>
    <section>
        <h3><?php esc_html_e('計算に含まれないもの', 'omochix'); ?></h3>
        <ul>
            <li><?php esc_html_e('消費税、割引、無料枠、バッチ処理や優先処理などの別料金', 'omochix'); ?></li>
            <li><?php esc_html_e('プロンプトキャッシュへの書き込み料金、画像・音声・動画の入力、Web検索などツール利用の追加料金', 'omochix'); ?></li>
            <li><?php esc_html_e('推論（thinking）で使われるトークン。多くのモデルで出力として課金されるため、出力トークンに含めて入力してください。', 'omochix'); ?></li>
        </ul>
    </section>
    <section>
        <h3><?php esc_html_e('料金を抑えるコツ', 'omochix'); ?></h3>
        <ul>
            <li><?php esc_html_e('用途に合ったモデルを選ぶ：分類や要約など単純な作業は、小型で安いモデルでも十分なことが多く、単価は数十倍違うこともあります。', 'omochix'); ?></li>
            <li><?php esc_html_e('出力を短くする：出力の単価は入力より高いのが一般的です。「3文以内で」「JSONだけを返して」のように、必要な長さを指定します。', 'omochix'); ?></li>
            <li><?php esc_html_e('プロンプトキャッシュを使う：毎回同じ長い説明（システムプロンプトや資料）を送る場合、キャッシュから読む分は通常の入力より大幅に安くなります。', 'omochix'); ?></li>
            <li><?php esc_html_e('会話の履歴を送りすぎない：チャットでは過去のやり取りも毎回入力として数えられます。要約して短くすると入力トークンが減ります。', 'omochix'); ?></li>
            <li><?php esc_html_e('急がない処理はバッチ処理を検討する：多くのサービスに、結果を待てる代わりに割安になる料金体系があります（このツールの計算には含めていません）。', 'omochix'); ?></li>
        </ul>
    </section>
    <section>
        <h3><?php esc_html_e('計算例', 'omochix'); ?></h3>
        <p><?php esc_html_e('1回あたり入力2,000トークン・出力800トークンを1日100回、30日使う場合、入力単価が100万トークンあたり2ドル、出力単価が10ドルのモデルなら、1回あたり (2,000×2 ＋ 800×10) ÷ 1,000,000 ＝ 0.012ドル、1日で1.2ドル、30日で36ドルが目安です。', 'omochix'); ?></p>
    </section>
    <section>
        <h3><?php esc_html_e('掲載するモデルと価格の条件', 'omochix'); ?></h3>
        <ul>
            <li><?php esc_html_e('対象：Anthropic・OpenAI・Google の公式API価格ページに掲載され、API で指定するモデルIDが公式に確認できるモデルのうち、各社の現行の主力・中位・小型モデル。', 'omochix'); ?></li>
            <li><?php esc_html_e('料金の種類：標準ティア（Standard）の一般向け価格だけを掲載します。Batch・Flex・Priority などの別ティア、地域別の価格、契約による割引は含みません。', 'omochix'); ?></li>
            <li><?php esc_html_e('単価：テキストの入力・出力・キャッシュ読み込みの100万トークンあたりの価格（USD）。キャッシュ書き込み、画像・音声などの入力、ツール利用の料金は計算に含みません。', 'omochix'); ?></li>
            <li><?php esc_html_e('価格改定：公式に予告されている改定は、適用日から新しい価格に切り替えます。', 'omochix'); ?></li>
            <li><?php esc_html_e('鮮度：公式ページで確認した日付を併記し、確認日から90日を過ぎた価格や、確認できていない価格は表示しません。', 'omochix'); ?></li>
        </ul>
    </section>
    <section>
        <h3><?php esc_html_e('掲載している価格', 'omochix'); ?></h3>
        <?php if ($omochix_models) : ?>
            <p><?php echo esc_html(sprintf(__('各社の公式価格ページで確認した標準料金（USD、100万トークンあたり）です。確認日から%s日を過ぎた価格や、確認できていない価格は表示しません。', 'omochix'), (string) $omochix_pricing['max_age_days'])); ?></p>
            <div class="labs-table-wrap" role="region" aria-label="<?php esc_attr_e('掲載している価格', 'omochix'); ?>" tabindex="0">
                <table class="labs-table">
                    <thead><tr><th scope="col"><?php esc_html_e('モデル', 'omochix'); ?></th><th scope="col"><?php esc_html_e('入力', 'omochix'); ?></th><th scope="col"><?php esc_html_e('出力', 'omochix'); ?></th><th scope="col"><?php esc_html_e('キャッシュ読み込み', 'omochix'); ?></th><th scope="col"><?php esc_html_e('出典（確認日）', 'omochix'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($omochix_models as $omochix_model) : ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($omochix_model['label']); ?><span class="labs-table__sub"><?php echo esc_html($omochix_pricing['providers'][$omochix_model['provider']] ?? ''); ?></span></th>
                                <td>$<?php echo esc_html((string) $omochix_model['input']); ?></td>
                                <td>$<?php echo esc_html((string) $omochix_model['output']); ?></td>
                                <td><?php echo null === $omochix_model['cached_input'] ? '—' : '$' . esc_html((string) $omochix_model['cached_input']); ?></td>
                                <td><a href="<?php echo esc_url($omochix_model['source_url']); ?>" rel="noopener noreferrer external"><?php esc_html_e('公式ページ', 'omochix'); ?></a><span class="labs-table__sub"><?php echo esc_html($omochix_model['verified_at']); ?><?php if ($omochix_model['valid_until']) : ?> / <?php echo esc_html(sprintf(__('%sまでの価格', 'omochix'), $omochix_model['valid_until'])); ?><?php endif; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="labs-hint"><?php esc_html_e('長いコンテキストの料金があるモデルは、境目を超えたときの単価で自動的に計算します。価格は予告なく変わることがあるため、契約や予算の判断の前に必ず公式ページを確認してください。', 'omochix'); ?></p>
        <?php else : ?>
            <p><?php echo esc_html(!empty($omochix_pricing['withheld']) ? __('料金データの確認が完了するまで、価格の一覧は掲載していません。', 'omochix') : __('現在、確認済みの価格はありません。', 'omochix')); ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>
