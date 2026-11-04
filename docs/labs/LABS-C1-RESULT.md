# OmochiX Labs — C1 MVP 実装結果

作成日: 2026-11-04
ブランチ: `feature/labs-c1-mvp`(worktree `~/Documents/omochix-labs-c1`、起点 `origin/main` 94152a7 = theme 1.27.0)
状態: ローカルで実装し、テストまで完了。**未コミット**(コミットは承認待ち)。push・PR・merge・deploy・本番の管理画面・DB・Secrets には触れていない。
計画: [LABS-C1-PLAN.md](LABS-C1-PLAN.md)

## 1. 実装ファイル

### 既存ファイルの変更(3 ファイル、+7 / −1 行)

| ファイル | 変更 |
| --- | --- |
| `wp-theme/omochix-theme/functions.php` | require ブロックの末尾に `inc/labs.php` を 1 行追加 |
| `wp-theme/omochix-theme/inc/shell.php` | `omochix_shell_enabled()` に Labs の条件を 1 つ追加(svg-motion が変更している配列の行には触れていない) |
| `wp-theme/omochix-theme/page-learn.php` | Hero に Labs へのリンクを追加(固定ページ `labs` が公開中のときだけ表示) |

`tests.yml`、`footer-editorial.php`、`style.css`(Version)、`inc/seo.php` は変更していない。フッターのリンクは既存の `omochix_editorial_footer_links` フィルタで追加している。

### 新規ファイル

| 区分 | ファイル |
| --- | --- |
| ルーティング・SEO・ヘッダー | `inc/labs.php` |
| テンプレート | `page-labs.php`、`template-parts/labs/{index,card,tool,faq,related}.php`、`template-parts/labs/content/{json-formatter,text-diff,robots-txt-generator,token-cost-calculator}.php` |
| データ | `assets/data/labs/catalog.json`、`pricing.json`(13 行 = 11 モデル + 価格改定の予約 2 行)、`crawlers.json`(13 トークン) |
| JS(純粋関数) | `assets/js/labs/core/{text,events,json,diff,robots,tokens,cost,ops}.js` |
| JS(画面) | `assets/js/labs/shared.js`、`worker.js`、`ui/{index,json-formatter,text-diff,robots-txt-generator,token-cost-calculator}.js` |
| CSS | `assets/css/labs.css` |
| テスト | `tests/labs/{stub,run,fixture,preview-server}.php`、`tests/labs/{core,data}.js`、`tests/labs/browser/{run.js,package.json,.gitignore}` |
| CI | `.github/workflows/labs.yml` |
| 文書 | `docs/labs/LABS-C1-PLAN.md`、本書 |

## 2. 各ツールの動作(Chromium で確認)

| ツール | 確認した動作 |
| --- | --- |
| 一覧 `/labs/` | キーワード検索(全角と半角、カタカナとひらがなを同一視、複数語は AND)、カテゴリ絞り込み(`aria-current`、URL に反映)、該当なしの表示、JS なしでも `?q=` と `?cat=` で動く。カードからツールへ遷移できる |
| JSON 整形・検証 | 整形(スペース 2 / 4、タブ)、圧縮、キーの並べ替え、コピー、保存。エラーは行・列と「^」付きの抜粋で表示。9007199254740991 を超える整数を丸めずに残して警告。重複キーと BOM も警告。1.7MB の入力を Worker で処理。上限 5MB。Ctrl / ⌘ + Enter で実行できる |
| テキスト差分比較 | 行単位(Myers 法)と行内の文字・単語単位(`<ins>` / `<del>`)。まとめて表示と左右に並べる表示を切り替えられる。空白・大文字小文字の無視、左右入れ替え、unified diff 形式でコピー。2 万行同士を Worker で約 0.4 秒。10 秒を超えたら Worker を止めて案内し、次の実行で新しい Worker を起動する |
| robots.txt 生成 | 基本方針 3 種、ルールの追加と削除(キーボードで操作可)、AI クローラー 13 種の許可・拒否・一括設定(学習用だけ拒否など)、サイトマップ。サイト全体を拒否する設定・不正なパス・不正なサイトマップは警告し、出力しない。RFC 9309 準拠の URL テスター(最長一致、同じ長さなら Allow、`*` と `$`) |
| AI トークン・API 料金計算 | 文章からトークン数を推定し、幅も表示。1 回・1 日・期間合計の料金を USD で、為替を入れれば円でも表示。長いコンテキストの単価への切り替え、キャッシュ単価がないモデルの扱い、不正な数値の欄をマーク。各行に出典リンクと確認日。90 日を過ぎた価格はブラウザ側の日付でも非表示になる |

## 3. テスト件数と結果

| テスト | 件数 | 結果 |
| --- | --- | --- |
| `php tests/labs/run.php` | 186 | 全件 PASS |
| `node tests/labs/core.js` | 117 | 全件 PASS(JSON のランダムな 300 件の往復変換、差分のランダムな 400 件が最小手数であることも含む) |
| `node tests/labs/data.js` | 24 | 全件 PASS(PHP と JS の価格ルールを 11 ケースで照合) |
| `node tests/labs/data.js --release` | 25 | **1 件 FAIL(想定どおり)**:価格表のレビューが pending |
| `tests/labs/browser/run.js`(Chromium) | 94 | 全件 PASS |
| 既存テスト(PHP 7 本、Node 4 本) | — | 全件 PASS(Motion Gallery の「53 items in 8 categories」とパラメータの照合 4,664 件を含む) |
| PHP の構文チェック(リポジトリ全体と新規ファイル) | — | エラーなし |

ブラウザテストの内訳:

- 375 / 768 / 1440px × 5 ページで、横スクロールなし、axe(WCAG 2.2 A/AA)の違反 0 件、スクリプトのエラー 0 件
- ダークテーマでも axe の違反 0 件
- 各ツールで、入力例・実行・コピー・リセット・エラー・キーボード操作・上限・Worker を確認
- 外部への通信 0 件、CSP(Report-Only)の違反 0 件
- GA4 イベントは 6 種類のみで、入力に混ぜた目印の文字列は一度も含まれなかった
- 未知の slug と大文字の slug は 404

ブラウザテストの実行方法(ダウンロードなしで、手元にある Playwright 1.64.1、axe-core 4.13.0、Chromium 1243 を使用):

```
LABS_BROWSERS=chromium \
LABS_CHROMIUM_PATH="$HOME/Library/Caches/ms-playwright/chromium-1243/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing" \
LABS_PLAYWRIGHT=$HOME/Documents/omochix/.claude/worktrees/sweet-morse-35a7ed/tests/svg-motion/node_modules/playwright \
LABS_AXE=$HOME/Documents/omochix-ai-development/node_modules/axe-core/axe.min.js \
node tests/labs/browser/run.js
```

## 4. SEO・構造化データ

- **タイトルと説明**:一覧と 4 ツールで 5 種類すべて固有。説明は 60〜160 文字。ツールのタイトルは「… | OmochiX Labs」で終わる。
- **canonical**:ツールは `/labs/{slug}/`、一覧は `/labs/`(クエリなし)。`?cat=` と `?q=` 付きの URL は `noindex, follow` で、canonical は一覧に向ける。
- **Slim SEO との重複防止**:Slim SEO 4.9.11 のソース(ローカルのバックアップ)でフィルタ名を確認した。テーマは独自の JSON-LD を出さず、`slim_seo_schema_graph` の中身を書き換える。
  - 一覧:WebPage を CollectionPage に変え、ItemList(4 件)を追加する。
  - ツール:WebPage に加えて WebApplication(無料、`applicationCategory`、`featureList` 付き)を追加し、`@id` と参照をツールの URL にそろえる。
  - BreadcrumbList は Slim SEO が出す 1 つだけ(`slim_seo_breadcrumbs_links` で「Labs」の階層を追加)。FAQPage は付けていない。
  - Labs の URL に SoftwareApplication があれば取り除く。
- **Open Graph**:title、description、url、image(とその幅・高さ・alt)を上書きする。OG 画像は未作成のため、現状は Slim SEO の既定の画像が使われる(§7)。
- **404**:未知・下書き・大文字の slug は、`set_404()` と `status_header(404)` で本当の 404 を返す。このときは SEO の値も変えない。
- **rewrite**:`^labs/([a-z0-9-]+)/?$` の 1 本だけで、バージョン付きの flush を 1 回だけ行う。既存 URL(`ai-tools/*`、`learn/motion/*`、`prompts/*` など)と 2 階層以上の URL は捕まえないことをテストで確認した。
- **解説の量**:ツールごとに 1,247〜3,342 文字の使い方と解説、FAQ 4 件、関連ツール、Learn・AI Tools・Prompts へのリンク(公開中のものだけ)。

## 5. セキュリティの確認

- **入力の上限**:JSON 5MB、差分は片側 1MB または 2 万行、トークン計算 1MB、robots.txt のテスター 512KB、ルール 200 行、パス 2,048 文字。ブラウザテストで上限が効くことを確認した。
- **入力を HTML として実行しない**:入力は `textContent` と `value` だけで表示する。`innerHTML`、`eval`、`document.write`、ストレージ、`fetch` などのネットワーク API が Labs の JS 全 15 ファイルにないことを、ソース検査で確認した。`<img onerror>` を入力しても実行されなかった。
- **重い処理の分離**:JSON の解析(256KB 超)、差分(常に)、トークン推定(256KB 超)は Worker で動かす。Worker は同じオリジンの `labs/core/*.js` だけを読み込み、10 秒を超えたら止める。
- **外部通信**:ツールの処理中は 0 件。外に出るのは Site Kit の gtag だけ。
- **CSP**:Labs のページだけに `Content-Security-Policy-Report-Only` を付ける(`unsafe-eval` なし、`object-src 'none'`、`frame-ancestors 'self'`、`worker-src 'self'`)。あわせて `X-Content-Type-Options` と `Referrer-Policy` も付ける。
- **既存のヘッダーを弱めない**:同じ名前のヘッダーがすでにあれば上書きしない。強制の CSP がすでにある場合は Report-Only を付けない(テストで確認)。
- **GA4**:6 イベント、決まった語彙の値だけを送る(許可リスト方式)。入力本文・URL・数値は送らない。
- **インラインの設定 JSON**:`</script>` や引用符をエスケープして埋め込む(テストで確認)。

## 6. SVG Motion との競合確認

- SVG Motion のブランチ(`feature/svg-motion-s1b`)は作業中に `d5801f5`(MVP-2)がコミットされた。**その最新の状態に対して**、`origin/main` を基点に `git merge-file` で 3 方向マージを試した。
- 結果は `functions.php`、`inc/shell.php`、`page-learn.php` のいずれも**競合 0 件**。マージ後の `functions.php` には `learn-svg-motion.php`、`logo-motion.php`、`labs.php` の 3 行がすべて残り、PHP の構文チェックも通った。
- 新規ファイルのパスは重ならない。`tests.yml` は Labs 側で変更していない。
- SVG Motion の worktree と本体の checkout(未コミット 15 件)には書き込んでいない。

## 7. 1.28.0 公開前の残課題

| # | 課題 | 担当・方法 |
| --- | --- | --- |
| 1 | **価格表のレビュー**(`pricing.json` の `review.status` を `approved` にし、`reviewer` と `reviewed_at` を記入)。Gemini 4 Argon と 3.8 Flash は 12/1 と 1/1 の価格改定を予約行で表現している | 利用者。`node tests/labs/data.js --release` が通ること |
| 2 | **Safari・WebKit・Firefox・iOS は未確認**。手元の WebKit 2336 はこの macOS で起動できず、新しいブラウザはダウンロードしていない | Playwright のブラウザを導入(要承認)するか、実機の Safari で手動確認 |
| 3 | **本物の WordPress での確認**(ステージングかローカル):Slim SEO の実際の出力(本番の Slim SEO のバージョン)、シェルのサイドバー、LiteSpeed Cache とヘッダー、rewrite の flush、フッターと Learn のリンク | 1.27.0 の公開後に実施 |
| 4 | 1.27.0 を先に公開する → Labs のブランチを main に追随 → `style.css` を 1.28.0 に上げる | リリース手順(Beta-Deployment-Runbook) |
| 5 | 公開時に固定ページ `labs`(タイトル「OmochiX Labs」、親なし)を作成。ヘッダーのメニューへの追加 | 利用者が管理画面で行う |
| 6 | OG 画像(1200×630)を `assets/img/labs/og/{index,slug}.png` に用意する(なければ Slim SEO の既定の画像が使われる) | 制作 |
| 7 | `/labs/{slug}/` はサイトマップに載らない(Slim SEO に URL を追加するフックがない)。一覧ページと内部リンクから発見させる | 公開後に Search Console で確認 |
| 8 | CSP Report-Only を 2 週間観察する(DevTools の `[Report Only]`) → C2 で強制に切り替え | 公開後 |
| 9 | Lighthouse、VoiceOver での手動確認 | ステージング |
| 10 | GA4 のカスタムディメンション登録(任意) | 利用者 |
| 11 | コミット、push、PR。`labs.yml` は push 後に初めて CI で動く | 承認後 |

## 8. 最終監査(コミット前)で直したこと

| # | 問題 | 対応 |
| --- | --- | --- |
| 1 | **一覧の検索語が URL(`?q=`)に入っていた**。Site Kit の GA4(拡張計測で履歴の変化をページビューとして送る設定の場合)が `page_location` として記録し得る | 検索欄は JavaScript でだけ表示し、URL には反映しない。URL に入るのはカテゴリ(固定の語彙)だけ。テストとブラウザテストで確認 |
| 2 | Worker が起動しないときに処理中のまま止まる余地があり、初期化に失敗した Worker を終了していなかった | 起動は 5 秒で打ち切ってメインスレッドで処理する。失敗した Worker は `terminate` する |
| 3 | robots.txt テスターがパーセントエンコードの正規化と BOM を扱っていなかった(RFC 9309 §2.2.2) | 非ASCII はエンコードし、エンコード済みの非予約 ASCII は戻して比較する。最長一致もこの形のオクテット長で判定。BOM を除去 |
| 4 | 17 桁を超える小数も JSON.parse では丸められる | 書かれたまま残し、警告を出す |
| 5 | `og:type` が `article` になっていた | Labs のページでは `website` |

極端な入力の確認:すべて異なる 2 万行同士は簡易表示への切り替えで 35ms、交互に並ぶ 2 万行は 0.5 秒、1MB の 1 行は 11ms。絵文字はサロゲートペアの途中で分割しない。

ブラウザ:Firefox はインストールされていない。Safari は `safaridriver` の有効化にシステム設定の変更が必要。WebKit 2336 は macOS 26.6 で起動しない。このため、Chromium 以外は未確認のまま。

## 9. サイトマップへの追加案(未実装)

Slim SEO 4.9.11 には、仮想 URL をサイトマップに追加するフックがない(`slim_seo_sitemap_post_type_query_args` などは投稿のクエリを変えるだけ)。既存のサイトマップを壊さない案:

1. **推奨:Labs 専用のサイトマップ `/labs-sitemap.xml`**
   - `inc/labs.php` に rewrite 1 本(`^labs-sitemap\.xml$`)と出力処理を追加する。中身はカタログから作る `<urlset>`(一覧と 4 ツール、`lastmod` = `catalog.updated`)。
   - robots.txt に `Sitemap: https://omochix.com/labs-sitemap.xml` を 1 行足す。Slim SEO の `slim_seo_robots_txt` フィルタを使う。
   - Search Console に送信する。
   - Slim SEO の `/sitemap.xml` と各サイトマップには一切触れない。戻すときはテーマを戻すだけ。
2. 代替:ツールを固定ページ(`labs` の子ページ)として作る。Slim SEO の固定ページ用サイトマップに自動で載るが、DB の変更と管理画面での運用が増えるため C1 の方針(DB なし)に合わない。
3. 不採用:Slim SEO の XML 出力を出力バッファで書き換える。プラグインの更新で壊れやすい。

## 10. 料金表の承認に必要な情報

- 2026-11-04 に公式ページを再確認し、13 行すべてが一致した。予約行の日付も一致(Gemini 4 Argon は 2026-11-30 まで $2/$10/$0.10、12/1 から $4/$20/$0.20。Gemini 3.8 Flash は 2026-12-31 まで $0.75/$3.75/$0.075、2027-01-01 から $1.50/$7.50/$0.15)。
  - Anthropic: https://platform.claude.com/docs/en/about-claude/pricing(モデル ID は https://platform.claude.com/docs/en/about-claude/models/overview の `claude-opus-5-5` / `claude-sonnet-5-5` / `claude-haiku-5-5`)
  - OpenAI: https://developers.openai.com/api/docs/pricing(Standard。Short context は 272K 以下、Long context は 272K 超。gpt-5.4-mini には長いコンテキストの区分がない)
  - Google: https://aistudio.google.com/docs/pricing(ai.google.dev から 301 で転送。Paid の Standard)
- 承認者に判断してほしい点:
  1. 掲載するモデルの選定(11 モデル)
  2. Gemini 4 Argon と 3.8 Flash の価格に、ページ上でモダリティ(テキストのみか)の表記がないこと
  3. キャッシュ書き込みの料金は計算に含めない方針
  4. 標準ティアだけを掲載すること(Batch・Flex などは除く)
- 承認したら、`pricing.json` の `review` に `"status": "approved"`、`reviewer`、`reviewed_at` を記入する。その後 `node tests/labs/data.js --release` が通ることを確認する。**今回は承認フラグを変更していない**(リリースゲートは想定どおり失敗)。

## 11. C1.1(SEO・公開準備。未コミット)

- **サイトマップ**:`/labs-sitemap.xml` を追加した。
  - 中身:公開中のツールの canonical URL 4 件と `lastmod`。一覧 `/labs/` は Slim SEO の固定ページ用サイトマップに載るので入れない。下書きと未知の slug は含めない。
  - 応答:`application/xml; charset=UTF-8`、`X-Robots-Tag: noindex, follow`、`Cache-Control: public, max-age=3600`。`Last-Modified` は `catalog.json` の更新時刻で、変更がなければ 304 を返す。HEAD には本文を返さない。
  - 固定ページ `labs` が非公開のときは 404。rewrite のバージョンを 2 にした(1 回だけ flush される)。
- **robots.txt**:`robots_txt` フィルタを優先度 10000(Slim SEO の 9999 の後)で使い、`Sitemap: …/labs-sitemap.xml` を 1 行だけ末尾に追加する。既存の行は残す。検索エンジンを拒否する設定のとき、固定ページが非公開のとき、同じ行がすでにあるときは追加しない。Slim SEO のサイトマップと rewrite には触れていない。
- **価格表**:
  - `pricing.json` に `scope`(対象モデル、ティア、単価の範囲、価格改定の扱い)を追加した。
  - 画面に「標準ティアのみ」「キャッシュ書き込みは計算に含めない」「推定値」と、掲載モデルの条件を表示する。
  - **本番のゲート**:`wp_get_environment_type()` が `production` で、`review` が未承認(status・reviewer・reviewed_at がそろっていない)なら、価格を 1 件も出さない(ページ・設定 JSON・解説の表のどれにも出ない)。staging・development・local では、レビューのために表示する。`review.status` は `pending` のまま。
- **テスト**:PHP 220 件、Node 117 件、パリティ 25 件、Chromium 98 件、既存 11 スイート、すべて PASS。`--release` は未承認のため 1 件 FAIL(想定どおり)。`xmllint` で整形式を確認。
- **検証計画**:[LABS-C1.1-VERIFICATION.md](LABS-C1.1-VERIFICATION.md)
