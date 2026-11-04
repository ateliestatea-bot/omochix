# OmochiX Labs — 1.28.0 公開前の検証計画(Safari・Firefox・iOS・WordPress ステージング)

作成日: 2026-11-04
対象: `feature/labs-c1-mvp`(C1 = `bf39195` と C1.1 の未コミット分)
前提: この端末では Chromium(Playwright)だけで自動検証できる。

| 環境 | この端末で検証できない理由 |
| --- | --- |
| Firefox | インストールされていない |
| Safari | `safaridriver` を使うには、システム設定(リモートオートメーションの許可)の変更が必要 |
| Playwright の WebKit 2336 | macOS 26.6 で起動しない |

以下は、別の端末か、承認を得たうえで実施する手順。

## 0. 共通の準備

1. ステージングの WordPress に、1.27.0 を公開した後の main と Labs ブランチを合わせたテーマを入れる。本番には入れない。
2. `wp-config.php` で `WP_ENVIRONMENT_TYPE` を `staging` にする。
   - これにより、承認前の価格も表示される。`production` のままだと価格の一覧は表示されない(§4 の確認項目)。
3. 管理画面で固定ページ `labs` を作る(タイトル「OmochiX Labs」、親なし、公開)。
4. 「設定 → パーマリンク」を開いて保存する。Labs の rewrite はバージョン 2 なので自動で 1 回 flush されるが、念のため。
5. LiteSpeed Cache をすべて削除する。
6. 記録用の表(環境 × 項目 × 結果 × スクリーンショット)を用意する。

## 1. ブラウザごとの確認項目

対象:Safari(macOS 最新)、Firefox(最新・ESR)、iOS Safari(実機)。可能なら Android Chrome も。

| # | 項目 | 手順 | 合格条件 |
| --- | --- | --- | --- |
| B-1 | 表示 | `/labs/` と 4 ツールを開く。幅 375・768・1440(iOS は縦と横) | 横スクロールがない。ボタンやリンクの高さが 44px 以上。文字の重なりがない |
| B-2 | JSON | 「入力例」→ 整形 → 圧縮 → コピー → 保存 → `[1,2,]` でエラー表示 → リセット | 結果・コピー・`.json` の保存ができる。エラーは「1行目 5文字目」と「^」付きの抜粋で出る |
| B-3 | 大きな JSON(Worker) | 2MB 程度の JSON を貼って整形 | 画面が固まらず、結果が出る |
| B-4 | 差分 | 入力例で比較 → 左右に並べる → diff 形式でコピー → 2 万行同士を比較 | `+` / `−` の印と色の強調が出る。2 万行が 10 秒以内に終わる |
| B-5 | robots.txt | 入力例 → GPTBot をテスト → ルールの追加・削除 → 保存 | 出力と判定が Chromium と同じ |
| B-6 | 料金計算 | 入力例 → 為替 150 → 日数を -3 → 文章から推定 → 入力トークンに使う | 円が表示される。不正な数値の欄が赤枠になりエラーが出る。推定値の表示が出る |
| B-7 | コピー | 各ツールのコピーボタン | iOS を含め、クリップボードに入る(失敗しても案内が出る) |
| B-8 | キーボード | Tab だけで全操作。Ctrl / ⌘ + Enter で実行 | フォーカスリングが見える。操作できない部品がない |
| B-9 | 読み上げ | macOS と iOS の VoiceOver で 4 ツールを操作 | ラベル、結果・エラーの読み上げ、差分の「追加」「削除」が読まれる |
| B-10 | ダークモード | OS をダークモードにする(サイトのテーマ切り替えがある場合はそれも) | コントラストが保たれる。差分の色を区別できる |
| B-11 | 動きを減らす設定 | OS の「視差効果を減らす」をオン | アニメーションしない |
| B-12 | WebP や日本語入力 | iOS で日本語入力しながら検索・入力 | 変換中に誤って実行されない |

自動テストを別の端末で動かす場合は、`tests/labs/browser/run.js` を、Playwright のブラウザ(firefox・webkit)が導入済みの環境で実行する。

```
LABS_BROWSERS=firefox,webkit node tests/labs/browser/run.js
```

firefox を対象にするには、`run.js` の `LABS_BROWSERS` の対象に firefox を追加する。追加は、そうした環境ができた時点で行う。

## 2. WordPress ステージングの確認項目

| # | 項目 | 手順 | 合格条件 |
| --- | --- | --- | --- |
| W-1 | ルーティング | `/labs/`、4 ツール、`/labs/unknown/`、`/labs/JSON-Formatter/`、`/labs/json-formatter`(末尾スラッシュなし) | 200、200、404、404、末尾スラッシュ付きへ 301 |
| W-2 | 既存 URL の回帰 | `/`、`/ai-tools/`、`/ai-tools/chatgpt/`、`/prompts/`、`/learn/`、`/learn/motion/`、`/learn/motion/pop-in/`、`/learn/motion/compose/`、`/learn/practice/`、記事 3 件、`/sitemap.xml`、`/robots.txt` | 1.27.0 と同じステータスと表示 |
| W-3 | メタ情報(Slim SEO) | 各ページの `<head>` をソース表示で確認 | `<title>`・description・canonical・og:url / og:title / og:type(website)が 1 つずつで、ページ固有。`?cat=ai` は `noindex, follow` で、canonical は `/labs/` |
| W-4 | 構造化データ | Google のリッチリザルトテストと Schema Markup Validator | 一覧は CollectionPage + ItemList(4 件)+ BreadcrumbList が 1 つずつ。ツールは WebPage + WebApplication + BreadcrumbList。エラーなし。FAQPage なし |
| W-5 | サイトマップ | `curl -I https://staging…/labs-sitemap.xml`。本文は `xmllint --noout` で確認。`If-Modified-Since` を付けて再取得 | 200、`application/xml`、`X-Robots-Tag: noindex`。4 URL だけで、一覧ページは含まない。2 回目は 304。Slim SEO の `/sitemap.xml` と固定ページのサイトマップは変化なし(`/labs/` は固定ページのサイトマップに 1 回だけ載る) |
| W-6 | robots.txt | `/robots.txt` を開く。Slim SEO で「robots.txt を編集」をオンにした状態でも確認 | Slim SEO の `Sitemap: …/sitemap.xml` が残り、`Sitemap: …/labs-sitemap.xml` が 1 行だけ増える |
| W-7 | 固定ページを下書きに戻す | `labs` を下書きにする | `/labs/`、4 ツール、`/labs-sitemap.xml` が 404 になる。robots.txt の Labs 行とフッターのリンクが消える |
| W-8 | ヘッダー | `curl -I` で `/labs/` と `/` を比べる | Labs のページだけに `Content-Security-Policy-Report-Only`・`X-Content-Type-Options`・`Referrer-Policy` が付く。サーバー(LiteSpeed や `.htaccess`)が同じ名前のヘッダーを付けていても重複しない(重複したらサーバー側の値を残し、Labs 側を外す) |
| W-9 | CSP Report-Only | DevTools の Console で `[Report Only]` を確認(5 ページ × PC とスマホ) | Labs 由来の違反がない。プラグイン由来の違反は記録し、C2 で許可リストに反映 |
| W-10 | GA4 | GA4 の DebugView(Site Kit のタグ)で操作する | `labs_tool_view` / `run` / `success` / `error` / `copy` / `related_click` が届く。パラメータは固定の語彙だけ。`page_location` に検索語が入らない(`?cat=` だけ) |
| W-11 | 価格ゲート | `WP_ENVIRONMENT_TYPE` を `production` にして料金計算を開く(確認後に `staging` に戻す) | 承認前は価格の一覧が出ず、「料金データの確認が完了するまで…」と表示される。トークン数の推定は使える |
| W-12 | キャッシュ | LiteSpeed Cache を有効にして 2 回目のアクセス | ヘッダーとサイトマップがキャッシュ後も同じ。料金はブラウザ側の日付でも再判定される |
| W-13 | シェルと導線 | PC とスマホでサイドバー・フッター・Learn のリンクを確認 | Labs が共通シェルで表示され、フッターと Learn からリンクされている |
| W-14 | 性能 | Lighthouse(モバイル)で 5 ページ | SEO と Accessibility が 95 以上。Performance が既存の Learn のページより悪くない |
| W-15 | PHP 8.1 / 8.3 | ステージングの PHP のバージョンで、エラーログを確認 | Notice・Warning なし |

## 3. 公開当日(本番)の確認

1. 1.27.0 が本番で安定していることを確認する。
2. 価格表の承認を確認する(`node tests/labs/data.js --release` が通ること)。
3. テーマ 1.28.0 をアップロード → 固定ページ `labs` を公開 → パーマリンクを保存 → LiteSpeed Cache をすべて削除。
4. W-1〜W-8 と W-10 を本番で簡易に確認する。
5. Search Console に `https://omochix.com/labs-sitemap.xml` を送信する(公開後)。
6. 問題があれば Runbook の Rollback に従う(テーマを戻し、固定ページを下書きにし、パーマリンクを保存し、キャッシュを削除)。

## 4. 記録

結果は `docs/labs/LABS-1.28.0-QA.md` に、環境・日時・合否・スクリーンショットとともに残す。
