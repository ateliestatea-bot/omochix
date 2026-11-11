# SVG Motion Studio MVP-2 実装結果（ショートコードと CI の時間対策）

検証日：2026年11月4日
ブランチ：`feature/svg-motion-s1b`（HEAD `aa0dcae` の上に未コミット）。Draft PR #55 は未マージ。
本番 WordPress、本番 DB、FTP、デプロイ、Core、AI Tools、既存 53 教材、Theme 1.27.0 の配布物は変更していない。S4 の高度な編集 UI には着手していない。

表記：**[実測]** はこの作業で確かめた事実、**[未検証]** は確かめていないこと。

## 1. ショートコード `[omochix_logo_motion]`

```
[omochix_logo_motion name="omochix-logo-motion-pop" alt="OmochiX のロゴ" size="320"]
```

| 要件 | 実装（`wp-theme/omochix-theme/inc/logo-motion.php`） |
|---|---|
| 許可する属性 | `name`、`alt`、`size` のみ（`shortcode_atts`。それ以外は無視） |
| ファイル名の検証 | `^[a-z0-9][a-z0-9-]{0,63}$`。ファイルに触れる前に判定する |
| 固定フォルダーへの制限 | `wp-content/uploads/omochix-logo-motion/`。`realpath()` したファイルの親がフォルダーの `realpath()` と一致すること |
| パストラバーサル、リンク | `..`、`/`、`%2e` などは名前の段階で拒否。ファイルがシンボリックリンクなら拒否（フォルダー内を指すものも）。フォルダー自体がリンクなら拒否 |
| 管理者が置いたファイルだけ | 投稿からはファイルを作れない。メディアライブラリは使わない（SVG のアップロードは許可しない） |
| SVG の安全性 | スタジオの検証器と同じ許可リスト（要素、属性、`data-omx-*`）、ハンドラー禁止、`href` は `#id` か画像の data URI のみ、`url()` は `#id` のみ、生成 CSS は `cssIsClean` と同じ文字、ロゴの CSS はスコープ付きの規則のみ。追加で：DOCTYPE・実体・処理命令・コメントの拒否、要素 5,000・深さ 32・パス 524,288 文字・1MB の上限、`id` は書き出しの接頭辞（`omx-xxxxxxxx-`）か重ね表示の 2 つの固定 id のみ |
| 整合性 | 2 つのファイルが同じ書き出し（同じスコープ class と viewBox）で、動く方に `@keyframes` があり、静止画に動きがないこと |
| 出力 | PHP が組み立てる `<picture>`（`<source media="(prefers-reduced-motion: reduce)">` に静止画、`<img>` に動く SVG、`loading="lazy"`、`?v=` は更新時刻と大きさから）。インラインにはしない |
| reduced-motion | `<source>` で静止画に切り替える。書き出した SVG 自身も `@media (prefers-reduced-motion: reduce)` で止まる |
| 権限 | `unfiltered_html` は変更しない。投稿に保存されるのはショートコードの文字列だけなので、kses の影響を受けない |
| 不正な入力 | 訪問者には何も出さない。投稿を編集できる人にだけ `<!-- omochix_logo_motion: 理由コード -->`（入力値やパスは出さない） |
| 外部通信 | 出力する URL はアップロードフォルダーのものだけ。SVG は画像として表示するので、中のスクリプトや外部参照は動かない |

`functions.php` に `require_once` を 1 行追加した。スタジオのページや JS とは独立していて、ショートコードを使うページでも追加の JS や CSS は読み込まない。

## 2. テスト結果 [実測、手元の Mac]

### 2-1. PHP 契約テスト（`tests/svg-motion/shortcode.php`、29 件、全件合格）

- 登録と出力の形、属性の無視、`alt` の除去とエスケープ、120 文字の上限、`size` の範囲（16〜1024、それ以外は 200）、縦横比。
- 名前 20 種の拒否、配列や `null` の拒否、64 文字の受け付け。
- ファイルがない、片方だけ、外を指すリンク、中を指すリンク、サブフォルダー、フォルダー自体がリンク、フォルダーがない。
- 中身 31 種の拒否（DOCTYPE、実体、処理命令、`script`、`foreignObject`、ハンドラー（大文字含む）、外部 `href`、`javascript:`、`data:text/html`、`url()` の外部参照、`style` 属性、未知の属性、不正な id、他の名前空間の属性、HTML の要素、`@import`、生成 CSS への外部参照、スコープのない CSS、`@font-face`、スコープ class なし、SVG 以外、名前空間違い、壊れた XML、空、1MB 超、深さ超過、コメント、DOM clobbering 用の id、他の書き出しの接頭辞、巨大なパス）。どれも理由つきで拒否され、何も表示されない。
- 組み合わせ違い、静止画 2 つ、動く SVG 2 つの拒否。
- 差し替えで `?v=` が変わること、改ざんしたファイルに置き換えると同じリクエスト内でもすぐ拒否されること、静止画を消すと何も出ないこと。

GitHub Actions では `tests.yml`（PHP 8.1 と 8.3）と、SVG Motion の core ジョブで実行する。

### 2-2. ブラウザー（`tests/svg-motion/shortcode-tests.js`、3 エンジン、全件合格）

| 確認 | Chromium | Firefox | WebKit |
|---|---|---|---|
| スタジオの書き出し 7 ロゴ × 19 プリセット（266 ファイル）を PHP の検査が受け付ける | 266 / 266 | 266 / 266 | 266 / 266 |
| 攻撃コーパスをスタジオが安全化して書き出した 66 組（132 ファイル）を受け付ける | 132 / 132 | 132 / 132 | 132 / 132 |
| 生の攻撃コーパス（69 種）と爆弾（11 種）を書き出しに埋め込んだ 80 件 | 77 件を拒否 | 同じ | 同じ |
| 受け付けた 3 件（`<title>`、`<desc>`、`<text>` の中のエスケープ済みの文字列。画像では無害で、スタジオの安全化済みの書き出しにも同じ文字列が残る）をショートコードで表示 | 読み込み、外部通信 0、エラー 0 | 同じ | 同じ |
| ショートコードの出力とスタジオの画像用コードの一致（media、2 つのファイル、alt） | 一致 | 一致 | 一致 |
| 訪問者として表示：動く SVG が動く | 動く | 動く | 動く |
| 動きを減らす：静止画に切り替わり、動かない | 合格 | 合格 | 合格 |
| 差し替え：新しい `?v=` で新しい画像が出る。削除：何も出ない | 合格 | 合格 | 合格 |

Firefox で一度、遅延読み込み（`loading="lazy"`）の画像が読み込まれる前に確認してしまい、2 件失敗した。テストを「画像の読み込み完了を待つ」に直し（判定条件は同じ）、3 エンジンとショートコードの段階、Firefox の core 全体を再実行して合格した。

### 2-3. WordPress 複製環境での権限別の確認

使い捨ての環境を手元に作った：WordPress 7.1.4 のコアファイル（Local の開発サイトから。`wp-content`、`wp-config.php`、データは含めない）、専用の MySQL 8.4（スクラッチ領域、専用ソケット）、このブランチのテーマ（リンク）。外部への HTTP は遮断（`WP_HTTP_BLOCK_EXTERNAL`）。ユーザーの操作は PHP の `wp_set_current_user` で行い、パスワードはどこにも入力していない。確認後に停止した。

4 権限が同じ投稿を保存した（ショートコード、不正なショートコード 3 つ、カスタム HTML の `<picture>`）。

| 権限 | `unfiltered_html` | 保存されたショートコード | 表示された `<picture>`（ショートコード） | カスタム HTML の `<picture>` |
|---|---|---|---|---|
| 管理者 | あり | 4 / 4 | 2（正しい 2 つ） | 残る |
| 編集者 | あり | 4 / 4 | 2 | 残る |
| 投稿者 | なし | 4 / 4 | 2 | **消える**（`<img>` だけ） |
| 寄稿者 | なし | 4 / 4 | 2 | **消える** |

- ショートコードの出力は 4 権限で**完全に同じ**。パストラバーサル（`../../../wp-config`）と改ざんしたファイルは何も出ない。余計な属性（`onload`、`src`）は無視された。
- 各権限の `unfiltered_html` は確認の前後で変わらない。
- `alt` に `"` を含めるには、属性全体を `'` で囲む（WordPress のショートコードの書式では `\"` は使えない）。手順書に記載した。
- 3 エンジン × 4 権限 × 2 設定（24 通り）で表示した。ショートコードの画像はすべて読み込まれ、動きを減らす設定では静止画に切り替わって止まった。ページのエラーは 0。投稿者と寄稿者のカスタム HTML は、動きを減らす設定でも動く SVG のままだった（ショートコードが必要な理由）。
- 各ページで外部への通信が 1 件あった：投稿者のアバター（Gravatar）。テーマの投稿テンプレートによるもので、ショートコードとは関係ない（テストでは遮断した）。

### 2-4. 回帰 [実測]

| 項目 | 結果 |
|---|---|
| SVG Motion 全スイート（core と export に分けて、Firefox の export は 2 分割） | 3 エンジン全件合格 |
| 53 教材の回帰（`regression`） | 3 エンジン合格 |
| 攻撃テスト（security、upload、書き出した攻撃ロゴ） | 3 エンジン合格 |
| 契約 / 単体 / ショートコード | 44 / 168 / 29 合格 |
| 既存 11 スイート | 全件合格（article-v2 197 / 197） |
| Theme 1.27.0 の ZIP / `catalog.json` | ハッシュ一致 |
| Core、AI Tools、既存 Motion データ | 差分 0 |

## 3. CI の時間対策

### 3-1. 調査 [実測]

前回の CI（MVP-1）で Firefox のジョブは 20 分 8 秒。そのうち export が 906 秒、ほかの段階の合計は 252 秒だった。export の中では、実時間で撮影する画像系の確認（並列 3）が大半を占める。手元で export に区切りごとの時間を記録するようにした（`export.timing`）。

| 区切り（手元、累積秒） | Chromium | Firefox（分割 1/2） | Firefox（分割 2/2） | WebKit |
|---|---|---|---|---|
| 形ごとの確認の終わり | 81 | 129 | 96 | 235 |
| CSP の終わり | 113 | 185 | 108 | 291 |
| reduced-motion の終わり | 185 | 267 | — | 378 |
| スタジオ UI の終わり（全体） | 212 | 306 | — | 414 |

### 3-2. 対策

| 対策 | 内容 |
|---|---|
| ジョブの分割 | 各エンジンを core（security、upload、fidelity、presets、page、compose、shortcode、53 教材）と export の 2 ジョブに分けた |
| Firefox の export の分割 | `SVGM_SHARD=k/n`。形ごとの確認と CSP の確認を n 個に分け、1 回だけ行う確認（プレビュー、reduced-motion、2 つのロゴ、攻撃ロゴ、スタジオ UI）は分割 1 だけで行う。合わせると 1 回分と同じ確認になる |
| 並列度 | Firefox は 3 のまま（安定性のため。CI-2）、Chromium と WebKit は 6 |
| 削除・無効化したテスト | なし。セキュリティテストはすべて core（Chromium、Firefox、WebKit）と export 分割 1 に残る |
| ジョブ数 | 7（chromium-core、chromium-export、firefox-core、firefox-export-1、firefox-export-2、webkit-core、webkit-export）。macOS は 2 ジョブ |

### 3-3. 見込み [未検証：GitHub Actions では未実行]

前回の CI の値から比例で見積もった。

| ジョブ | 見込み | 25 分に対する余裕 |
|---|---|---|
| firefox-core | 約 5〜6 分 | 約 19 分 |
| firefox-export-1 | 約 11〜12 分 | 約 13 分 |
| firefox-export-2 | 約 5〜6 分 | 約 19 分 |
| chromium、webkit の各ジョブ | 約 5〜11 分 | 14 分以上 |

Firefox の 2 つの export は、1 回だけの確認が分割 1 に集まるため偏っている（手元で 307 秒と 110 秒）。必要なら 1 回だけの確認の一部を分割 2 に移せる。

## 4. 変更ファイル

| ファイル | 内容 |
|---|---|
| `wp-theme/omochix-theme/inc/logo-motion.php`（新規） | ショートコード、検査、描画 |
| `wp-theme/omochix-theme/functions.php` | `require_once` 1 行 |
| `tests/svg-motion/shortcode.php`（新規） | PHP 契約テストと、ブラウザーのテストから使う補助（`--render`、`--check-dir`） |
| `tests/svg-motion/shortcode-tests.js`（新規） | 書き出し・攻撃コーパス・3 エンジンでの表示・差し替え |
| `tests/svg-motion/fixtures/logo-motion/`（新規、4 ファイル） | スタジオの書き出し 2 組（pop、wipe） |
| `tests/svg-motion/run.js` | `shortcode` の段階を追加 |
| `tests/svg-motion/export-tests.js` | 区切りごとの時間、`SVGM_SHARD` |
| `.github/workflows/svg-motion.yml` | 7 ジョブへの分割、`logo-motion.php` を対象に追加、ショートコードの PHP テスト |
| `.github/workflows/tests.yml` | `tests/svg-motion/shortcode.php` を追加 |
| `docs/motion/SVG-MOTION-EMBED-GUIDE.md` | WordPress の手順をショートコード中心に書き直した |
| `docs/motion/SVG-MOTION-MVP-2-DESIGN.md` | チェックリストの状態を更新 |
| `docs/motion/SVG-MOTION-MVP-2-RESULT.md`（新規） | この文書 |

## 5. 残る公開ブロッカー

| # | 項目 | 状態 |
|---|---|---|
| 1 | GitHub Actions で 7 ジョブの時間と結果を確認 | 未実行（commit と push は別途承認） |
| 2 | 実機（iOS Safari、Android Chrome、実機 Safari）：スタジオ、書き出し、ショートコードの表示、動きを減らす設定、読み上げ | **未検証** |
| 3 | 実際のデザインツールの SVG での互換性（設計書 4 章） | 未実施 |
| 4 | セキュリティの最終レビュー（第三者）とファジング（設計書 5 章）。ショートコードの検査器を範囲に含める | 未実施 |
| 5 | スクリーンリーダーの確認 | 未実施 |
| 6 | 実サーバー（nginx、アクセラレータ）で SVG が `image/svg+xml` で配信されること、キャッシュ | 未実施 |
| 7 | 新しいテーマの版と配布物、本番の固定ページ、PR #55 のマージ | 運営者の承認待ち |
| 8 | 本番での `omochix-logo-motion` フォルダーの作成とファイルの配置手順（誰が、どう置くか） | 運営者の判断待ち |

## 6. 本番 MVP 公開の判定

**No-Go（現時点）。** 実装と手元での検証は揃ったが、公開の前に次が必要。

1. GitHub Actions で 7 ジョブが合格すること（ブロッカー 1）。
2. 必須の実機確認で NG がないこと（ブロッカー 2）。未検証の項目を合格にはしない。
3. 第三者のセキュリティレビュー（ブロッカー 4）。ショートコードは利用者のファイルではなく管理者が置いたファイルだけを扱うが、検査器は新しいコードであるため。
4. 運営者による本番の作業手順（テーマの更新、フォルダーの作成）の承認。

これらが揃えば Go と判断できる。実 SVG の互換性（3）とスクリーンリーダー（5）は、公開後すぐの改善に回すかどうかを運営者が判断する。
