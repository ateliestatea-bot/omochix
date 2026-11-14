# R2・R3 修正と回帰 QA レポート

作成日：2026-11-14 / ブランチ：`feature/nav-ux-update-v1`（`~/omochix-nav-ux-wt`、HEAD `a617fd3`、未コミット）
前提資料：`FINAL-CHECK-REPORT.md`・`STAGING-CHECKLIST.md`・`P0-FIX-REPORT.md`

## 1. 再開時点の差異

- ブランチ・HEAD は前回の監査時と同じ。追跡ファイルの差分（22 ファイル、+229 / −124）と未追跡の数（19）も同じ。前回の監査後にこの作業ツリーで変わったファイルはない。stash（`WIP before Learn MVP`）も残っている。
- リモート：`main` は `a617fd3`、RC は `2f800b2` で変わっていない。#61・#63・#64 は Open のまま。
- 元の作業ツリー `~/Documents/omochix/releases/` に、新しく `industry-hub-2f800b2-ops/` が増えていた（11-13 作成。別作業の本番反映手順書と読み取り専用チェックのスクリプト）。中身は読み取りだけで、変更していない。配布パッケージ `industry-hub-2f800b2/` の zip の SHA-256 は `release-plan.json` の値と一致し、変わっていない。

## 2. 修正内容と変更ファイル

### R2：再構築中の一時的な空応答

`wp-theme/omochix-theme/inc/search-suggest.php`（REST コールバック）

| 応答 | ステータス | ヘッダー | 本文 |
|---|---|---|---|
| 正常な索引 | 200 | `Cache-Control: public, max-age=300, s-maxage=300`、`ETag: "<v>"` | `{"items":[…],"v":"<内容のハッシュ>"}` |
| 正常に空の索引 | 200 | 同上 | `{"items":[],"v":"<空配列のハッシュ>"}` |
| 再構築中（一時的） | **503** | **`Cache-Control: no-store`、`Retry-After: 5`**、ETag なし | `{"items":[],"v":"warming"}` |

- 「正常に空の索引」と「再構築中」は、ステータスと `v` で区別する。
- 正常な索引のキャッシュ指定は変えていない。

`wp-theme/omochix-theme/assets/js/search-suggest.js`（取得部分のみ）

- 2xx 以外の応答では `Retry-After` の秒数（ない場合は 5 秒）まで再取得しない。修正前は、入力するたびに再取得していた（503 の間に 6 回）。
- 通信エラーのときも 5 秒待つ。
- その間は候補を出さず、「全体から検索」の行と Enter による通常の検索はそのまま使える。
- 本文の `warming` を判定していた分岐は、503 に置き換えたので削除した。

### R3：索引を作る間のユーザー状態の復元

`wp-theme/omochix-theme/inc/search-suggest.php`（`omochix_suggest_index()`）

- 索引の作成を `try { … } finally { wp_set_current_user($user); }` で囲んだ。
- 作成が例外（`Exception`）やエラー（`Error`）で中断しても、元のユーザーに戻してから呼び出し元へ例外を伝える。
- 失敗した場合：
  - 不完全な索引は保存しない（保存は作成が成功した後だけ）。
  - ロックは有効期限（30 秒）まで残す。失敗し続ける場合でも作り直しは 1 分に 2 回まで。
  - その間の応答は 503 の no-store。
  - 期限が切れると、次のリクエストで作り直されて回復する。
- WordPress の認証や権限の処理は変えていない（`wp_set_current_user` を呼ぶ位置を変えていない）。
- 保証できないこと：
  - PHP の致命的エラー（メモリ不足、実行時間の上限）や、プロセスが強制終了された場合は `finally` が実行されない。この場合リクエスト自体が終わるので、匿名に切り替えた状態がほかへ持ち越されることはない。索引も保存されず、ロックは 30 秒で切れる。
  - 一方、ロックを持ったリクエストが致命的エラーで終わった場合、30 秒間は 503 が続く。

### 追加したテスト（開発環境用）

- `tests/nav-ux/suggest-r2r3.php`：R2 と R3 の正常系・異常系、19 項目。
- `tests/nav-ux/suggest-warming.cjs`：503 のときのブラウザの動き（Chromium・WebKit）。
- `tests/nav-ux/icons-f1.php`：F1。サイトアイコンは `get_site_icon_url` フィルターで再現し、DB の設定は書き換えない。

差分の規模（このフェーズ）：`search-suggest.php` +16 / −3、`search-suggest.js` +8 / −4。

## 3. 回帰 QA の結果（開発環境 127.0.0.1:8940）

| 対象 | テスト | 結果 |
|---|---|---|
| PHP の構文 | 追跡・未追跡のすべての `.php` に `php -l` | エラー 0 |
| CI と同じテスト | ai-tools ×3、learn-practice 96、learn-gallery 134、learn-composer 46、nav 16、article-v2 197、editorial-home 26、svg-motion 34、labs 285、industry-hub 83、Node 4 本（core-parity 78、params-parity 4664、menu-contrast、quick-edit 33） | すべて PASS |
| D1 IME | `ime.cjs`（Chromium・WebKit）、`firefox-suggest.cjs`（Firefox 159） | PASS（合成イベント。**実機の IME は未検証**） |
| D2 | 301 の表（`/page/2/`・`/page/999/`・`/?paged=3`・`/?page=2`・`/learn/page/2/`・`/about/page/7/`・`/labs/page/3/`・UTM 付き） | すべて 301 が 1 回、到達先は 200 |
| D2 | 正規のページ送り `/news/page/2/` は 200、範囲外カテゴリーは 404 | 変化なし |
| D2 | canonical（`/`・`/learn/`・`/about/`・`/labs/`） | 自分自身（開発環境では WordPress 本体が出力） |
| D2 | Industry Hub（`/learn/industries/page/2/`） | 開発環境にページがないため 404。除外の動作は**未検証** |
| D3 | `suggest-leak.php`（匿名・管理者・管理者 + パスワード Cookie・匿名 + パスワード Cookie） | 保護・非公開・下書き・予約は 0 件 |
| D3 | HTTP で匿名と Cookie 付きを比較 | 本文が一致（前回の監査で確認） |
| D5 | `suggest-flush.php` | PASS |
| D6 | `article-tools.php` | 5 ケースすべて PASS |
| F1 | `icons-f1.php` | フロントはテーマのタグ 5 本のみ。管理画面とログイン画面は WordPress の Site Icon（4 本）。無効化フィルターも動く |
| 検索候補 | `suggest.cjs`（Chromium・WebKit、出力先は作業用の一時ディレクトリ） | 候補、Esc、ダイアログ、XSS 0、モバイル、初回表示前の通信 0 |
| ナビ / UX | `qa.cjs`（出力先は一時ディレクトリ） | Chromium・WebKit とも 16/16 |
| R2（サーバー） | `suggest-r2r3.php` と HTTP の `curl` | 503 + no-store + Retry-After、ETag なし、索引の書き込みなし。正常は 200 の public。空の索引も 200 の public |
| R2（ブラウザ） | `suggest-warming.cjs` | 503 の間の取得は 2 回以内、Enter で通常の検索、Retry-After の後に再取得して候補が出る、JS エラー 0 |
| R3 | `suggest-r2r3.php`（`RuntimeException`・`Error` を発生させる） | 呼び出し元に例外が伝わる、管理者に戻る、索引は保存されない、ロックが残る、その間は 503、ロック切れ後に回復、回復後の索引に保護コンテンツなし |

- テストが触ったのは索引の transient（索引とロック）だけで、終わったら消している。次のリクエストで作り直される。
- 検証用データ（LEAKTEST・`d6-*`）は削除していない。site_icon の設定は 0 のまま。
- 性能：今回の変更は検索欄にフォーカスした後の取得処理だけで、初回表示の経路は変わらない。FCP / LCP / CLS は計測し直していない（前回の `perf-p0fix.json` を基準とする）。
- 8940 と 8941 は同じ DB を使うので、独立した比較環境としては扱っていない。今回のテストはすべて 8940 で行った。

## 4. 未検証の項目と残るリスク

未検証（PASS にしていない）：

- 日本語 IME（実機）、iPhone / Android、Safe Area、Learn の固定バー、スクリーンリーダー。
- Slim SEO が入った状態での D2・canonical・サイトマップ。
- Industry Hub 統合後のページ送りの除外。
- LiteSpeed・ホストキャッシュ・CDN が 503 と no-store を守るか（`STAGING-CHECKLIST.md` の B4）。
- 正常な索引が s-maxage を守るか（B1〜B3）。
- 参考：別作業の手順書（`industry-hub-2f800b2-ops/RUNBOOK.md`）には「本番はページキャッシュ OFF」とある。本番の応答には `x-cache` が付く。どちらも検証済みの事実としては扱わない。

残るリスク：

- 致命的エラーで終わったとき、最大 30 秒 503 が続く。その間も通常の検索は使える。
- 503 を監視が障害として数える可能性がある。対象は REST の 1 本だけで、出るのは再構築中だけ。
- 「本番に検索索引の機能がなかったので D3 の漏えいは本番で起こり得なかった」ことは確認済み。ただし、これは**サイト全体に漏えいがないことの保証ではない**。ほかの経路（WordPress 本体の検索、REST、フィード、Slim SEO のサイトマップ、RC の Industry Hub）の保護は、それぞれの仕様と検証に依存する。今回の監査はこの索引に限る。

## 5. コミット対象の候補（ファイル単位）

> **2026-11-14 追記**：確定版は `COMMIT-SCOPE.md`（66 ファイル）。この節は確定前の候補。

### 対象にする：テーマ（変更 21 ファイル）

- `archive-ai_tool.php`
- `archive-prompt.php`
- `assets/css/editorial-home.css`
- `assets/css/shell.css`
- `category.php`
- `footer-editorial.php`
- `footer.php`
- `front-page.php`
- `functions.php`
- `header.php`
- `inc/editorial-home.php`
- `inc/labs.php`
- `inc/seo.php`
- `inc/shell.php`
- `manifest.webmanifest`
- `page-learn.php`
- `search.php`
- `single-ai_tool.php`
- `single.php`
- `style.css`
- `template-parts/labs/index.php`

### 対象にする：テーマ（新規）

- `assets/css/footer-editorial.css`
- `assets/css/search-suggest.css`
- `assets/js/search-suggest.js`
- `assets/icons/chat.svg`
- `assets/icons/labs.svg`
- `assets/img/icons/`：ファビコン一式 16 ファイル（PNG 11・SVG 3・ICO 2）。製品のアセットで、QA 画像ではない。
- `assets/loader/omochix-loader.css`
- `assets/loader/omochix-loader.js`
- `assets/loader/omochix-loader-page.js`
- `inc/icons.php`
- `inc/nav.php`
- `inc/search-suggest.php`
- `inc/omochix-loader.php`
- `inc/omochix-loader-markup.php`

### 対象にする：CI・テスト・ツール

- `.github/workflows/tests.yml`（`tests/nav/run.php` を追加）
- `tests/nav/run.php`
- `tests/nav-ux/` の 16 ファイル（開発環境用。CI では実行しない）
- `tools/icons/build-favicons.cjs`

### 対象にする：文書

- `docs/nav-ux-update/`：`REPORT.md`、`NAV-REGISTRY.md`、`FINAL-AUDIT.md`、`*.json` 5 本
- `docs/ux-expansion/`：`01`〜`07`、`REPORT.md`、`*.json` 6 本
- `docs/release-readiness/`：全ファイル（この報告を含む）

### 要判断

- `design/logo-assembly-loader/`：PNG を除く 49 ファイル（ローダーの原本、React 版、プレビュー、ローダー用テスト）。テーマに組み込んだのはコピーなので、同じコミットに入れるか別にするかは所有者が決める。

### 対象外（削除しない）

- `docs/nav-ux-update/screens/`・`docs/ux-expansion/screens/`：326 ファイル、約 47MB
- `design/` の PNG：17 ファイル
- 元の作業ツリーの `releases/`（配布パッケージと ops）
- 他の作業ツリーにあるすべてのもの

対象外を除いてステージングに追加する例（実行はまだしない）：

```bash
git add -- . ':!docs/nav-ux-update/screens' ':!docs/ux-expansion/screens' ':!design/**/*.png'
```

## 6. Industry Hub 完了後の統合と QA の手順

Industry Hub の現在の状態（ローカル資料と `git ls-remote` で確認できた範囲）：

- RC `2f800b2`（Core 1.10.0 / テーマ 1.29.0）の配布パッケージがある。
- 本番反映手順書の最終版がある（11-13）。第 0 部（読み取り）以外は運営者の本番承認が必要。
- main への統合はまだ（#61・#63・#64 は Open）。
- 本番に反映されたかどうかは、今回はローカル資料からは判断できない。前回（11-12）の公開 GET での値（1.28.1）を、現在の値としては扱わない。

手順：

1. RC を本番に反映し、関門 A・B と反映後の監視を終える。その後に #64・#61・#63 を main に統合する。
2. 統合後の main で、本ブランチとの競合を確認する（前回の事前確認では 0）。
3. 所有者がコミット範囲を確定し（第 5 節）、本ブランチを新しい main に載せる。
4. テーマ Version を決める（候補は 1.30.0。今回は変更しない）。本番の 1.29.0 を基準にマニフェストを作り、差分が承認済みのファイルだけか確認する。
5. 第 3 節の自動テストをすべて再実行する。Industry Hub が入った状態で、D2 の除外（`/learn/industries/page/N/`）、索引にゲート未通過の業種が出ないこと、Hub のテスト（industry-hub）を追加で確認する。
6. ステージング（`STAGING-CHECKLIST.md`。本番と同じキャッシュ条件を再現できない項目は未検証のまま）、承認、本番反映、キャッシュ削除（別承認）、本番の読み取り確認の順に進める。

## 7. 本番 Go / No-Go

**No-Go**。コードの既知の課題（D1〜D6、F1、R2、R3）はローカルで解消し、自動テストはすべて通っている。残っているのは次のとおり：

- Industry Hub RC の本番反映と main への統合
- テーマ Version の確定
- コミット範囲の確定
- Slim SEO・LiteSpeed・ホストキャッシュを含むステージングでの検証
- 実機での確認
