# OmochiX Labs — Search Console・サイトマップ登録手順

対象: Labs 1.28.0 の公開後(固定ページ `labs` を公開した後)
関連: `docs/Beta-Deployment-Runbook.md` §17・§18、`docs/Beta-Release-Checklist.md`「Sitemap送信」

## 1. 公開直後(当日)

1. **robots.txt を確認する**
   - ブラウザで `https://omochix.com/robots.txt` を開く。
   - 次の 2 行がそれぞれ 1 回だけあること。
     ```
     Sitemap: https://omochix.com/sitemap.xml
     Sitemap: https://omochix.com/labs-sitemap.xml
     ```
2. **補助サイトマップを確認する**
   - `curl -sI https://omochix.com/labs-sitemap.xml` を実行する。
     - `HTTP/2 200`、`content-type: application/xml; charset=UTF-8`、`x-robots-tag: noindex, follow` であること。
   - `curl -s https://omochix.com/labs-sitemap.xml | xmllint --noout -` を実行する。
     - エラーが出ないこと。
   - `<loc>` は `/labs/{slug}/` の 4 件だけで、`/labs/` 自体は含まないこと。
3. **重複がないことを確認する**
   - `curl -s https://omochix.com/sitemap-post-type-page.xml | grep -c '/labs/'` の結果が `1`(`/labs/` だけ)であること。
   - `labs-sitemap.xml` の 4 件が、Slim SEO のどのサイトマップにも出てこないこと。
4. **Search Console に登録する**(プロパティ `https://omochix.com/`)
   1. 左のメニュー「サイトマップ」→「新しいサイトマップの追加」に `labs-sitemap.xml` と入力し、「送信」を押す。
   2. 既存の `sitemap.xml` が登録済みで、ステータスが「成功しました」であることを確認する(未登録なら同じ手順で送信する)。
   3. `labs-sitemap.xml` のステータスが「成功しました」、検出された URL が 4 件になるまで待つ(数時間から数日)。
5. **URL 検査**(任意。早めにインデックスさせたい場合)
   - 「URL 検査」に `/labs/` と 4 ツールの URL を 1 件ずつ入力し、「インデックス登録をリクエスト」を押す。

## 2. 1 週間後

- 「ページ」レポートで `/labs/` 以下の URL が「インデックス登録済み」になっているか確認する。
- 「クロール済み - インデックス未登録」や「重複しています」の理由が出ていないか確認する。
- 「拡張」→「パンくずリスト」でエラーがないことを確認する。
- 「サイトマップ」で、`labs-sitemap.xml` の最終読み込み日時が更新されていることを確認する。

## 3. 4 週間後

- 「検索パフォーマンス」でページの URL を `/labs/` で絞り込み、表示回数・クリック数・主なクエリを記録する。
- インデックスされていない URL があれば、URL 検査で理由を確認する。

## 4. 補助サイトマップをやめる場合

1. Search Console の「サイトマップ」で `labs-sitemap.xml` を削除する。
2. テーマ側の出力を止めると、`/labs-sitemap.xml` が 404 になり、robots.txt の行も消える。
3. Runbook §17 の表と §18 から `labs-sitemap.xml` を削除する。
