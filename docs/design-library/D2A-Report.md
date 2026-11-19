# OmochiX Design Library — D2-A 実装報告（詳細ページの体験と SEO）

- 作成日：2026-11-17
- ブランチ：`feature/design-library-d1`（D1＋D1.5 の commit `fdd8b30` の上に、未 commit の変更として作業）
- 状態：ローカル実装と試験まで。commit・push・PR・本番反映は未実施（指示どおり）。D0 の未追跡 2 文書は変更していない。
- 前提の設計：D0 v1.3、D1 実装報告書、`D1.5-Works-Model.md`。

---

## 1. 実装した機能

### 1.1 レスポンシブプレビュー（Web 作品）

- 詳細ページの枠の上部に、表示サイズの切り替え（PC 1280 px／タブレット 834 px／スマホ 390 px）を置いた。
- **デモの iframe 自体の幅と高さ（CSS px）を変える**ので、デモ側のメディアクエリがそのまま効く（デモ内の `innerWidth` が 1280／834／390 になることを、3 ブラウザで確認）。外枠だけを縮める実装ではない。
- 収まらないときは `transform: scale()` で縮小する。縦横比を保ち、拡大はしない。通常表示では端末全体が画面の 82% の高さに収まるようにした。ページの横スクロールは出ない（ページ幅 1440／834／390 で確認）。
- 切り替えではデモを再読み込みしない（デモ内に置いた目印の値が残ることで確認）。アニメーションも始めからやり直さない。
- キーボード：`role="radiogroup"` のロービングフォーカス（← → ↑ ↓、Home、End、端で折り返す）。選択の変化を読み上げる。
- JavaScript が無効なとき：切り替えは出さず、これまでどおり幅いっぱいの iframe を表示する。

### 1.2 全画面プレビュー

- 「全画面」ボタンで、プレビューの枠（`<figure>`）を Fullscreen API で全画面にする。枠の中の iframe はそのままなので、**sandbox・CSP・URL は変わらない**（デモ自身が全画面を要求することもできない。`document.fullscreenEnabled` が false）。
- Fullscreen API がない、または拒否されたとき（iPhone の Safari など）は、ページ内でプレビューを画面いっぱいに重ねる表示に切り替える。この表示は `role="dialog"`・`aria-modal` を持ち、背景のスクロールを止め、Tab のフォーカスを中に閉じ込める。
- Escape で閉じる。フォーカスがデモの中にあって親ページにキーが届かない場合のために、デモは Escape だけを親に知らせる（`omx-design:key`、値は `Escape` のみ。親は重ね表示中にしか受け付けない）。
- 閉じると、フォーカスはボタンに、スクロール位置は開く前の位置に戻る。
- デモだけを別タブで開くリンク（「新しいタブで開く」）は残した（JavaScript が無効なときの全画面の代わり）。
- 動画作品は、ブラウザ標準の動画プレイヤー（全画面ボタン付き）をそのまま使う。

### 1.3 モーションの操作

- 「一時停止／再生」「最初から再生」。
- 一時停止は、親からデモへのメッセージ `{type: "omx-design:control", action: "pause" | "play"}` で行う（デモ側は許可した 2 つの値以外を無視する）。デモの共通ランタイムは、一時停止を「タブが非表示」と同じに扱い、各デモの描画ループを止め、CSS アニメーションを `animation-play-state` で止める。3D のフレームループが止まり、再開することを 3 ブラウザで確認した。
- 「最初から再生」は、同じ URL を iframe に読み直させる（sandbox・CSP は同じ）。
- プレビューが画面外に出たら自動で一時停止し、戻ったら再開する。
- Save-Data（データセーバー）が有効なら、一時停止の状態で始まり、その旨を表示する。`prefers-reduced-motion` のときは、デモ自身が控えめな動きに切り替わる（D1 のまま）。操作ボタンはどちらでも使える。
- 1 ページに置くデモは 1 つだけ（全画面でも同じ iframe を使い、2 つ目を起動しない）。

### 1.4 Learn との関連付け

Motion Gallery の 53 件と、テーマのデータが参照している Learn の固定ページを調べた。作品で**実際に使っている技法**と、内容が関係するページだけを登録した。Learn のデータは複製していない。

| 作品 | Motion Gallery の見本 | Learn のページ |
|---|---|---|
| Liquid Glass | ふわふわブロブ、流れるグラデーション、ライトスイープ | `learn/development`、`learn/tools/vercel` |
| Minimal Editorial | フェードアップ、下線が描かれる、チェックが描かれる（線の描画） | 同上 |
| 3D Interactive | ドリーイン、オービット、スナップするカード列 | 同上 |
| Bento SaaS | スタガー、カウントアップ、スクロールでフェードイン | 同上 |
| Logo Reveal Short | 一文字ずつ登場、チェックが描かれる、ポップイン | `learn/svg-motion` |
| Data Chart Explainer | スタガー、フェードアップ | なし |

- 表示するのは、リンク先が公開されている場合だけ（固定ページは公開中で、パスワードなし。Gallery は、そのページが公開され、見本が実在するとき）。下書きのページへのリンクは出さないことを試験で確認した。
- **本番に `learn/development`・`learn/tools/vercel` が公開されているかは確認していない**（本番には接続していない）。存在しなければ表示されないだけ。

### 1.5 SEO

| 項目 | 内容 |
|---|---|
| title | 作品ごとに固有（「{作品名} — {種類}の見本 \| デザインライブラリー」）。6 件すべて異なることを確認 |
| meta description | 作品の `description`（Slim SEO のフィルター経由。D1 から） |
| canonical | 作品自身の URL。Slim SEO がない場合も WordPress 本体の canonical に同じ値を返す（D1.5） |
| パンくず | 画面上：Home / デザインライブラリー / 種類 / 作品名。構造化データの BreadcrumbList は、Slim SEO がパンくずのリンクから作る（そのためのフィルターは D1 から） |
| JSON-LD | Slim SEO のグラフ（`slim_seo_schema_graph`）に追加する方式（Labs・Industry Hub と同じ）。詳細ページ：Web 作品は `CreativeWork`、動画作品は `VideoObject`（`contentUrl`、`thumbnailUrl`、`duration`、`uploadDate`、幅・高さ）を、Slim SEO の `WebPage` の `mainEntity` にする。一覧・トップ：インデックス対象の作品の `ItemList`。**独自に `<script type="application/ld+json">` は出さないので、重複しない** |
| インデックスの可否 | デモの公開と同じ規則：ローカル・開発環境では `verified-local` 以上、それ以外（本番・ステージング・未設定）では `verified-production` の作品だけを対象にする。対象外の作品の詳細ページ、対象の作品がない一覧・トップ、絞り込んだ一覧は `noindex, follow`。JSON-LD にも出さない |
| サイトマップ | `/design-sitemap.xml`：対象の作品がある種類の一覧と、対象の作品の詳細（lastmod は確認日）。デモの URL・絞り込みの URL・トップは含めない（トップは固定ページなので Slim SEO のページ用サイトマップに入る。対象の作品が 0 件のときは、そこからも外す）。対象が 0 件、またはページ `design` が未公開なら 404。サイトが公開設定のときだけ robots.txt に載せる。Labs と同じ方式 |

**本番への影響（重要）**：今のカタログは全作品が `verified-local` のため、本番に入れた場合は、**すべての作品ページが noindex になり、サイトマップは 404、JSON-LD の作品要素も出ない**。インデックスさせるには、本番での隔離確認（D5）のあと `verified-production` に変える必要がある。

### 1.6 その他

- rewrite の版を `3` に上げた（サイトマップのルールを追加したため）。D1.5 の自己修復は、サイトマップのルールの有無も確認する。
- 内部用の query var（`omx_design_sitemap` を含む）をクエリ文字列から受け付けない処理を、サイトマップにも広げた。

---

## 2. 変更ファイル

| 区分 | ファイル |
|---|---|
| テーマ（変更） | `inc/design-library.php`（インデックス判定、サイトマップ、robots.txt、Slim SEO のサイトマップ除外、JSON-LD、rewrite 版 3）、`template-parts/design-library/detail.php`（表示サイズ・操作・全画面のマークアップ）、`assets/js/design-library-preview.js`（表示サイズ・操作・全画面・画面外での停止）、`assets/css/design-library.css`、`assets/design/runtime/demo-runtime.js`（一時停止の受信、Escape の通知）、`assets/data/design/catalog.json`（関連する Gallery と Learn） |
| 試験（変更） | `tests/design-library/run.php`、`http-check.php`、`browser/isolation.js`、`browser/firefox.js`、`rewrite-heal-check.php`（版を定数から読む・サイトマップのルール）、`run-all.sh`、`stub.php`、`fixtures/mu-plugin/omx-design-test.php`（Slim SEO のグラフを通す模擬出力。試験用ヘッダーがあるときだけ） |
| 試験（新規） | `tests/design-library/browser/preview.js`（D2-A のブラウザ試験 72 項目） |
| 道具（新規） | `tools/design-library/screens.js`（変更前後のスクリーンショット） |
| 記録 | 本書、`qa/summary-d2a.txt`、`qa/d2a/before/`・`qa/d2a/after/`（スクリーンショット）、`qa/perf-results.json`、`qa/screenshots/`（UI 試験が更新） |

---

## 3. 試験結果（2026-11-17）

| 試験 | D1＋D1.5 時点 | D2-A 後 |
|---|---|---|
| catalog | 94 | 94 / 94 |
| unit | 70 | 81 / 81 |
| http | 131 | 169 / 169 |
| isolation（Chromium・WebKit） | 72 | 76 / 76 |
| firefox | 22 | 25 / 25 |
| ui（Chromium・WebKit） | 192 | 192 / 192 |
| preview（D2-A、Chromium・WebKit、新規） | — | 72 / 72 |
| perf | 32 | 32 / 32 |
| heal（rewrite 自己修復） | 24 | 25 / 25 |
| **合計** | **637** | **766 / 766** |
| 既存 86 URL（変更前のテーマの基準と比較） | 差分 0 | **差分 0** |
| 既存の試験（Learn・Labs・Industry Hub・SVG Motion・AI Tools ほか） | 合格 | 合格（`release-gate` の 1 件は、承認外の新規ファイルを検出する想定どおりの失敗） |

D1＋D1.5 の既存 637 項目のうち、試験側を変えたのは 2 点だけで、どちらも期待値を D2-A の仕様に合わせたもの：

- `http-check` のルーティング表で、`/design-sitemap.xml` を 404 から 200 に変えた（D2-A でサイトマップを追加したため）。
- `rewrite-heal-check` で、rewrite の版の期待値を固定値の「2」から、コードの定数を読む形に変えた（版 3 になったため）。

途中で見つけて直した不具合：

- 重ね表示の全画面で、フォーカスがデモの中にあると Escape で閉じられなかった（キーが親ページに届かない）。また WebKit では、Tab でフォーカスが重ね表示の外に出た。→ デモから Escape だけを通知し、Tab の移動を親ページが行うように直した。
- D1 の `.dl-stage__bar span` の指定が新しい要素に当たり、表示サイズの数字が読めなくなっていた。→ 指定の範囲を絞った。

### 3.1 ブラウザ別の動作

| | Chromium | WebKit | Firefox |
|---|---|---|---|
| 表示サイズの切り替え（デモの幅、再読み込みなし、横スクロールなし） | ○（PC・タブレット・スマホ幅） | ○（同左） | ○（PC 幅） |
| キーボード（矢印・Home・End） | ○ | ○ | 未試験（BiDi 試験の範囲外） |
| 一時停止・再生・最初から | ○ | ○ | 一時停止・再生 ○ |
| 画面外での停止、Save-Data で一時停止から開始 | ○ | ○ | 未試験 |
| 全画面（API） | ○ | ○ | ○ |
| 全画面（API なしの重ね表示）、Escape、フォーカス、スクロールの復元 | ○ | ○ | 未試験 |
| JS 無効、reduced-motion | ○ | ○ | 未試験 |
| axe（serious・critical 0、ライト PC・ダーク スマホ） | ○ | ○ | — |

### 3.2 セキュリティ試験

- B1〜B10（D1）はすべて合格のまま。
- 全画面（API・重ね表示）のどちらでも、iframe の `sandbox="allow-scripts"`・URL は変わらず、`allow`・`allowfullscreen` 属性は付かない。デモの origin は `"null"`、Cookie は `SecurityError`、デモ自身は全画面を要求できない。
- 新しいメッセージ：`omx-design:control` は許可した 2 つの値だけが効き、不正な値や別の iframe からのものは無視される。`omx-design:key` は重ね表示中にしか効かず、どのメッセージでも全画面は開かない。
- 内部 query var（サイトマップを含む）は、クエリ文字列から渡すと 404。
- 本番環境では、`verified-local` の作品のデモは引き続き配信しない（D1 の規則のまま。単体試験で確認）。

### 3.3 SEO 試験（使い捨てサイトを一時的に公開設定にして実施。終了後に戻した）

- 6 作品とも、固有の title、自分自身を指す canonical、インデックス可、画面上のパンくず。
- JSON-LD（Slim SEO のグラフを通す試験用の模擬出力で確認）：Web 作品は WebPage → CreativeWork、動画は VideoObject、トップは 6 件の ItemList。絞り込んだ一覧には追加しない。
- サイトマップ：200・XML・`X-Robots-Tag: noindex`・8 URL（一覧 2、作品 6）。デモ・トップ・絞り込みの URL は含まない。robots.txt に載る。
- 全作品を未検証にすると：詳細・一覧・トップが noindex、サイトマップは 404、robots.txt にも載らず、JSON-LD に作品が出ない。サイトが非公開設定のときも robots.txt に載らない。ページ `design` を下書きにするとサイトマップは 404。

### 3.4 パフォーマンス

| 指標 | D1.5 | D2-A |
|---|---|---|
| デモ単体の JS（gzip） | 1.7〜6.8 KB | 2.2〜7.3 KB（共通ランタイムに一時停止の処理を追加） |
| 詳細ページの LCP（モバイル相当、CDP の制限付き） | 1.87〜1.91 s | 2.03〜2.10 s（基準 ≤ 2.5 s）。詳細ページの JS の増加と、枠の高さを JS で決める分 |
| CLS | 0 | 0 |
| 一時停止中のデモ | — | フレームループ 0 回（3D） |

LCP は基準内だが約 0.15 s 悪化した。D2-B 以降で、詳細ページの JS の読み込みを遅らせる余地がある。

---

## 4. 既存機能への影響

- 既存 86 URL の応答は、変更前のテーマと比べて差分 0。
- Learn・Labs・Industry Hub・SVG Motion Studio・AI Tools の既存試験はすべて合格。
- rewrite の版が 3 になったため、テーマを入れた最初のリクエストで rewrite ルールの flush が 1 回走る（D1.5 の自己修復と同じ仕組み）。
- robots.txt：サイトが公開設定で、インデックス対象の作品があるときだけ `Sitemap:` の行が 1 行増える。

---

## 5. 残る課題（未解決・未確認）

1. **本番ではインデックスされない**：全作品が `verified-local` のため（§1.5）。D5 の本番隔離確認と、`verified-production` への移行が必要。
2. **Slim SEO 本体での確認をしていない**：この端末に Slim SEO がないため、JSON-LD・パンくず・meta description は、同じフィルターを通す試験用の模擬出力でしか確認していない。Slim SEO が出す `WebPage` の型（WebPage・CollectionPage など）や順序が違う場合は、ステージングで確かめる必要がある。
3. 実機（iPhone の Safari での重ね表示、Android の Chrome）での全画面・表示サイズの確認。
4. Firefox は、キーボード操作・重ね表示・Save-Data・JS 無効の試験をしていない（Chromium・WebKit では実施）。
5. 本番の Learn ページ（`learn/development`・`learn/tools/vercel`）が公開されているかは未確認。
6. 詳細ページの LCP が約 0.15 s 悪化した（基準内）。
7. D2 の残り：作品の追加、サイトマップの Slim SEO サイトマップインデックスへの登録の要否、配布物のハッシュ付きファイル名（D0 V2）。
