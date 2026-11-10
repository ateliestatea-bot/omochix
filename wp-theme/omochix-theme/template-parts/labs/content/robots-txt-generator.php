<?php
/**
 * robots.txt generator: workspace and guide. The AI crawler table is
 * rendered from assets/data/labs/crawlers.json (official sources, checked
 * dates) so it is readable without JavaScript too.
 *
 * @package OmochiX
 *
 * @var array $args { tool: array, part: 'workspace'|'guide' }
 */

if (!defined('ABSPATH')) {
    exit;
}

$omochix_crawlers = omochix_labs_get_crawlers();
?>
<?php if ('workspace' === $args['part']) : ?>
    <div class="labs-toolbar" role="group" aria-label="<?php esc_attr_e('操作', 'omochix'); ?>">
        <button type="button" class="labs-button labs-button--ghost" data-labs-sample><?php esc_html_e('入力例', 'omochix'); ?></button>
        <button type="button" class="labs-button labs-button--ghost" data-labs-reset><?php esc_html_e('リセット', 'omochix'); ?></button>
    </div>
    <div class="labs-panes labs-panes--form">
        <div class="labs-pane">
            <fieldset class="labs-fieldset">
                <legend><?php esc_html_e('すべてのクローラー（User-agent: *）', 'omochix'); ?></legend>
                <div class="labs-radios labs-radios--stack">
                    <label for="labs-robots-preset-allow"><input type="radio" name="labs-robots-preset" id="labs-robots-preset-allow" value="allow" checked> <?php esc_html_e('すべて許可する', 'omochix'); ?></label>
                    <label for="labs-robots-preset-wordpress"><input type="radio" name="labs-robots-preset" id="labs-robots-preset-wordpress" value="wordpress"> <?php esc_html_e('WordPress の標準（管理画面だけ拒否）', 'omochix'); ?></label>
                    <label for="labs-robots-preset-block"><input type="radio" name="labs-robots-preset" id="labs-robots-preset-block" value="block"> <?php esc_html_e('すべて拒否する（公開前のサイトなど）', 'omochix'); ?></label>
                </div>
                <h3 class="labs-subhead"><?php esc_html_e('追加のルール', 'omochix'); ?></h3>
                <ul class="labs-rules" data-labs-rules></ul>
                <button type="button" class="labs-button labs-button--small" data-labs-add-rule><?php esc_html_e('ルールを追加', 'omochix'); ?></button>
                <p class="labs-hint"><?php esc_html_e('パスは「/」か「*」で始めます。* は任意の文字列、末尾の $ は「ここで終わる」を表します（例：/*.pdf$）。最大200行。', 'omochix'); ?></p>
            </fieldset>

            <fieldset class="labs-fieldset">
                <legend><?php esc_html_e('AIクローラー', 'omochix'); ?></legend>
                <?php if ($omochix_crawlers['crawlers']) : ?>
                    <div class="labs-toolbar labs-toolbar--small" role="group" aria-label="<?php esc_attr_e('AIクローラーの一括設定', 'omochix'); ?>">
                        <button type="button" class="labs-button labs-button--small" data-labs-bulk="training"><?php esc_html_e('学習用を拒否', 'omochix'); ?></button>
                        <button type="button" class="labs-button labs-button--small" data-labs-bulk="all"><?php esc_html_e('すべて拒否', 'omochix'); ?></button>
                        <button type="button" class="labs-button labs-button--small labs-button--ghost" data-labs-bulk="none"><?php esc_html_e('指定をクリア', 'omochix'); ?></button>
                    </div>
                    <div class="labs-table-wrap" role="region" aria-label="<?php esc_attr_e('AIクローラーの一覧', 'omochix'); ?>" tabindex="0">
                        <table class="labs-table labs-table--crawlers">
                            <thead><tr><th scope="col"><?php esc_html_e('クローラー', 'omochix'); ?></th><th scope="col" class="labs-col-wide"><?php esc_html_e('用途', 'omochix'); ?></th><th scope="col"><?php esc_html_e('設定', 'omochix'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($omochix_crawlers['crawlers'] as $omochix_i => $omochix_c) : ?>
                                    <tr>
                                        <th scope="row"><code><?php echo esc_html($omochix_c['token']); ?></code><span class="labs-table__sub"><?php echo esc_html($omochix_c['operator']); ?><span class="labs-only-narrow"> ・ <?php echo esc_html($omochix_crawlers['purposes'][$omochix_c['purpose']]); ?></span></span></th>
                                        <td class="labs-col-wide"><?php echo esc_html($omochix_crawlers['purposes'][$omochix_c['purpose']]); ?></td>
                                        <td>
                                            <label class="labs-sr-only" for="labs-crawler-<?php echo esc_attr((string) $omochix_i); ?>"><?php echo esc_html(sprintf(__('%sの設定', 'omochix'), $omochix_c['token'])); ?></label>
                                            <select class="labs-select" id="labs-crawler-<?php echo esc_attr((string) $omochix_i); ?>" data-labs-crawler="<?php echo esc_attr($omochix_c['token']); ?>" data-purpose="<?php echo esc_attr($omochix_c['purpose']); ?>">
                                                <option value=""><?php esc_html_e('指定しない', 'omochix'); ?></option>
                                                <option value="allow"><?php esc_html_e('許可', 'omochix'); ?></option>
                                                <option value="disallow"><?php esc_html_e('拒否', 'omochix'); ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="labs-hint"><?php esc_html_e('「指定しない」にすると、そのクローラーは上の「すべてのクローラー」のルールに従います。各クローラーの説明と公式ページは下の解説にあります。', 'omochix'); ?></p>
                <?php else : ?>
                    <p class="labs-hint"><?php esc_html_e('確認済みのAIクローラー情報がないため、一覧を表示していません。', 'omochix'); ?></p>
                <?php endif; ?>
            </fieldset>

            <fieldset class="labs-fieldset">
                <legend><?php esc_html_e('サイトマップ', 'omochix'); ?></legend>
                <label class="labs-pane__label" for="labs-robots-sitemaps"><?php esc_html_e('サイトマップのURL（1行に1つ）', 'omochix'); ?></label>
                <textarea class="labs-textarea labs-code" id="labs-robots-sitemaps" rows="3" spellcheck="false" autocapitalize="off" autocomplete="off" placeholder="https://example.com/sitemap.xml"></textarea>
            </fieldset>
        </div>

        <div class="labs-pane labs-pane--sticky">
            <div class="labs-pane__head">
                <label class="labs-pane__label" for="labs-robots-output">robots.txt</label>
                <div class="labs-pane__actions">
                    <button type="button" class="labs-button labs-button--small" data-labs-copy><?php esc_html_e('コピー', 'omochix'); ?></button>
                    <button type="button" class="labs-button labs-button--small" data-labs-download><?php esc_html_e('保存', 'omochix'); ?></button>
                </div>
            </div>
            <textarea class="labs-textarea labs-code" id="labs-robots-output" rows="16" readonly spellcheck="false"></textarea>
            <div class="labs-warnings" data-labs-warnings hidden>
                <h3><?php esc_html_e('確認してください', 'omochix'); ?></h3>
                <ul></ul>
            </div>
        </div>
    </div>

    <section class="labs-tester" aria-labelledby="labs-tester-title">
        <h3 id="labs-tester-title"><?php esc_html_e('URLテスター', 'omochix'); ?></h3>
        <p class="labs-hint"><?php esc_html_e('robots.txt で、あるクローラーがそのURLを巡回できるかを判定します（RFC 9309 準拠）。', 'omochix'); ?></p>
        <label class="labs-check" for="labs-tester-use-generated"><input type="checkbox" id="labs-tester-use-generated" checked> <?php esc_html_e('上で作った robots.txt で判定する', 'omochix'); ?></label>
        <div class="labs-tester__custom" data-labs-tester-custom hidden>
            <label class="labs-pane__label" for="labs-tester-robots"><?php esc_html_e('判定に使う robots.txt', 'omochix'); ?></label>
            <textarea class="labs-textarea labs-code" id="labs-tester-robots" rows="8" spellcheck="false" autocapitalize="off" autocomplete="off"></textarea>
        </div>
        <div class="labs-tester__row">
            <label class="labs-field" for="labs-tester-agent"><?php esc_html_e('クローラー（User-agent のトークン）', 'omochix'); ?>
                <input class="labs-input" id="labs-tester-agent" list="labs-tester-agents" value="Googlebot" maxlength="60" autocapitalize="off" autocomplete="off" spellcheck="false">
            </label>
            <datalist id="labs-tester-agents">
                <option value="Googlebot"></option>
                <option value="Bingbot"></option>
                <?php foreach ($omochix_crawlers['crawlers'] as $omochix_c) : ?>
                    <option value="<?php echo esc_attr($omochix_c['token']); ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <label class="labs-field" for="labs-tester-path"><?php esc_html_e('URL またはパス', 'omochix'); ?>
                <input class="labs-input" id="labs-tester-path" value="/wp-admin/" maxlength="2048" autocapitalize="off" autocomplete="off" spellcheck="false">
            </label>
            <button type="button" class="labs-button labs-button--primary" data-labs-run data-action="test"><?php esc_html_e('判定する', 'omochix'); ?></button>
        </div>
        <p class="labs-verdict" data-labs-verdict hidden></p>
    </section>
<?php else : ?>
    <section>
        <h3><?php esc_html_e('使い方', 'omochix'); ?></h3>
        <ol>
            <li><?php esc_html_e('「すべてのクローラー」で基本の方針を選びます。WordPress のサイトなら「WordPress の標準」が出発点です。', 'omochix'); ?></li>
            <li><?php esc_html_e('検索結果に出す必要のないページ（社内用の一覧、検索結果ページなど）があれば「ルールを追加」で Disallow を足します。', 'omochix'); ?></li>
            <li><?php esc_html_e('AIクローラーごとに「許可」「拒否」を選びます。「学習用を拒否」を押すと、AIモデルの学習に使うクローラーだけをまとめて拒否できます。', 'omochix'); ?></li>
            <li><?php esc_html_e('サイトマップのURLを入れ、右の robots.txt を「コピー」または「保存」して、サイトのルート（https://あなたのドメイン/robots.txt）に置きます。', 'omochix'); ?></li>
            <li><?php esc_html_e('下の「URLテスター」で、狙いどおりに許可・拒否されるかを確かめます。', 'omochix'); ?></li>
        </ol>
    </section>
    <section>
        <h3><?php esc_html_e('robots.txt でできること・できないこと', 'omochix'); ?></h3>
        <p><?php esc_html_e('robots.txt は「このURLは巡回しないでください」とクローラーにお願いするためのファイルです。標準仕様は RFC 9309 で定められ、主要な検索エンジンや多くのAIクローラーが従います。一方で、アクセスを止める仕組みではありません。ルールを無視するクローラーもあり、拒否したURLでも他のページからリンクされていれば検索結果にURLだけが載ることがあります。', 'omochix'); ?></p>
        <ul>
            <li><?php esc_html_e('検索結果に出したくないページ：robots.txt ではなく、ページ側の noindex を使います（クロールを拒否すると noindex も読まれません）。', 'omochix'); ?></li>
            <li><?php esc_html_e('見られたくない情報：ログインやアクセス制限で守ります。robots.txt は誰でも読めるので、秘密のURLを書くと逆に知らせることになります。', 'omochix'); ?></li>
            <li><?php esc_html_e('サイト全体の拒否（Disallow: /）は、公開前のテスト環境など限られた場面だけにします。本番で残っていると、検索結果から徐々に消えていきます。', 'omochix'); ?></li>
        </ul>
    </section>
    <section>
        <h3><?php esc_html_e('判定のルール（RFC 9309）', 'omochix'); ?></h3>
        <ol>
            <li><?php esc_html_e('クローラーは、自分の名前（プロダクトトークン）と一致する User-agent のグループに従います。大文字・小文字は区別しません。一致するグループがなければ「*」のグループに従います。', 'omochix'); ?></li>
            <li><?php esc_html_e('グループの中では、URLのパスに一致するルールのうち、パターンが最も長いものが優先されます。同じ長さなら Allow が勝ちます。', 'omochix'); ?></li>
            <li><?php esc_html_e('* は任意の文字列、末尾の $ はURLの終わりを表します。どのルールにも一致しなければ許可です。/robots.txt 自体は常に許可されます。', 'omochix'); ?></li>
        </ol>
        <p><?php esc_html_e('crawl-delay など、検索エンジンごとの独自の書き方は判定に使いません。Google は 500KiB を超えた部分を読まないため、その場合は警告します。', 'omochix'); ?></p>
    </section>
    <?php if ($omochix_crawlers['crawlers']) : ?>
        <section>
            <h3><?php esc_html_e('主なAIクローラー', 'omochix'); ?></h3>
            <p><?php esc_html_e('同じ会社でも、学習用・検索用・ユーザー操作用でトークンが分かれています。検索や回答での表示は残し、学習だけを断る、といった使い分けができます。内容は各社の公式ページをもとにしており、確認日を併記しています。', 'omochix'); ?></p>
            <div class="labs-table-wrap" role="region" aria-label="<?php esc_attr_e('主なAIクローラーの説明', 'omochix'); ?>" tabindex="0">
                <table class="labs-table">
                    <thead><tr><th scope="col"><?php esc_html_e('トークン', 'omochix'); ?></th><th scope="col"><?php esc_html_e('運営', 'omochix'); ?></th><th scope="col"><?php esc_html_e('用途', 'omochix'); ?></th><th scope="col"><?php esc_html_e('説明', 'omochix'); ?></th><th scope="col"><?php esc_html_e('出典（確認日）', 'omochix'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($omochix_crawlers['crawlers'] as $omochix_c) : ?>
                            <tr>
                                <th scope="row"><code><?php echo esc_html($omochix_c['token']); ?></code></th>
                                <td><?php echo esc_html($omochix_c['operator']); ?></td>
                                <td><?php echo esc_html($omochix_crawlers['purposes'][$omochix_c['purpose']]); ?></td>
                                <td><?php echo esc_html($omochix_c['note']); ?><?php if ('' !== $omochix_c['robots_note']) : ?><br><strong><?php echo esc_html($omochix_c['robots_note']); ?></strong><?php endif; ?></td>
                                <td><a href="<?php echo esc_url($omochix_c['source_url']); ?>" rel="noopener noreferrer external"><?php esc_html_e('公式ページ', 'omochix'); ?></a><span class="labs-table__sub"><?php echo esc_html($omochix_c['verified_at']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="labs-hint"><?php esc_html_e('リンクのプレビューを作るクローラー（facebookexternalhit など）を拒否すると、SNSで共有したときにタイトルや画像が出なくなることがあります。この一覧には含めていません。', 'omochix'); ?></p>
        </section>
    <?php endif; ?>
    <section>
        <h3><?php esc_html_e('よくある間違い', 'omochix'); ?></h3>
        <ul>
            <li><?php esc_html_e('Disallow のパスを「/」で始めていない：「wp-admin/」ではなく「/wp-admin/」と書きます。このツールは「/」か「*」で始まらないパスを出力しません。', 'omochix'); ?></li>
            <li><?php esc_html_e('大文字・小文字の違い：パスは大文字と小文字を区別します。「/Private/」と「/private/」は別のパスです。', 'omochix'); ?></li>
            <li><?php esc_html_e('特定のクローラー用のグループを作ったら、「*」のルールは適用されない：たとえば GPTBot 用のグループがあると、GPTBot は「*」のグループを読みません。このツールで「許可」を選んだ場合は、「*」と同じルールを自動で書き写します。', 'omochix'); ?></li>
            <li><?php esc_html_e('サイトマップを相対パスで書く：Sitemap は「https://」から始まる完全なURLで書きます。', 'omochix'); ?></li>
            <li><?php esc_html_e('公開前の「すべて拒否」を本番に残したまま：サイトを公開したら、最初に robots.txt を確認してください。', 'omochix'); ?></li>
        </ul>
    </section>
    <section>
        <h3><?php esc_html_e('WordPress での確認と置き場所', 'omochix'); ?></h3>
        <p><?php esc_html_e('WordPress は、robots.txt のファイルがない場合に自動で内容を返します。SEOプラグインが内容を管理していることも多いので、まずブラウザで https://あなたのドメイン/robots.txt を開き、今の内容を確認してから差し替えてください。', 'omochix'); ?></p>
        <p><?php esc_html_e('ファイルを置く場合は、WordPress をインストールしたディレクトリ（wp-config.php と同じ場所）ではなく、ドメインのルートに置きます。サブディレクトリに WordPress を置いている場合も、robots.txt はドメインの直下でしか読まれません。', 'omochix'); ?></p>
    </section>
<?php endif; ?>
