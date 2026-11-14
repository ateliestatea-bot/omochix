# Industry Hub 統合後の確認項目（UX / SEO 変更を載せる前後）

作成日：2026-11-14（2026-11-15 に 0〜0.6・4 を更新）/ 対象：`feature/nav-ux-update-v1`（`COMMIT-SCOPE.md` の 66 ファイル）
Industry Hub の完了は推測しない。**下の「0. 統合の基準を決めるための入力」がすべてそろってから**、統合の基準（どの HEAD に載せるか）を決める。テーマ Version（候補 1.30.0）はまだ確定しない。

## 0. 統合の基準を決めるための入力（受け取るもの）

| # | 入力 | 用途 | 受領 |
|---|---|---|---|
| 0-1 | Industry Hub の最新の完了報告（本番反映の日時、関門 A・B と反映後の監視の結果、ロールバックの有無） | 本番の状態を確定する | 受領（2026-11-15、引き継ぎ報告：配置 29/29、関門 A・B 各 130/130、本番 31 URL 31/31、過去 122 URL 122/122。反映後の監視は記録がある分だけ完了扱い。このセッションでは本番を再確認していない） |
| 0-2 | 本番のテーマと Core の Version（反映後に確認したもの） | マニフェストの基準（現時点の想定は 1.29.0 / 1.10.0 だが、未確定） | 受領：WordPress 7.1.4 / Core 1.10.0 / テーマ 1.29.0 |
| 0-3 | main に統合された後の **main の確定 HEAD**（#64・#61・#63 の統合後） | 本ブランチを載せる基準 | **未確定**：`origin/main` は `a617fd3` のまま。#61・#63・#64 はすべて Open（draft）で未マージ（2026-11-15 確認） |
| 0-4 | 本番の HEAD とファイルが main と一致するか（RC の `release-plan.json` のマニフェストとの照合結果） | main と本番がずれていないことの確認 | 照合済み（下の 0.5）：本番の配布物と一致するのは RC `2f800b2`。main は一致しない |
| 0-5 | 本番で Industry Hub が公開されているか（`omochix_core_industry_public`）と、公開されている業種の数 | 下の A の確認条件 | 一部受領：業種別ページは非公開。一覧ページ（`/learn/industries/`）の公開状態と公開業種の数は未受領 |
| 0-6 | 統合後に別の変更（修正リリースなど）があるか | 基準の HEAD を決め直す必要の有無 | なし（`origin` に新しい修正ブランチはない。2026-11-15 時点） |

0-1〜0-4 がそろうまでは、本ブランチを main に載せない（rebase / merge をしない）。

## 0.5 照合結果（2026-11-15、読み取りのみ）

`git fetch` の後に、リリース記録 `releases/industry-hub-2f800b2/` の SHA-256 マニフェスト（テーマ 172・Core 59）と各ブランチのファイルを 1 件ずつ照合した。配布 zip とマニフェストも一致（172/172・59/59）、`ops/SHA256SUMS` も OK。

| 比べたもの | テーマ（172） | Core（59） | 判定 |
|---|---|---|---|
| RC `origin/rc/industry-hub-on-theme-1.28.1` = `2f800b2` | 172 一致 | 59 一致 | **本番の配布物と同じ** |
| `origin/main` = `a617fd3` | 169 一致・3 不一致 | 46 一致・13 不一致 | 一致しない（1.28.1 の Learn カード・SVG Motion の導線・Version、Core の Version 表記と REST の拒否コード） |
| #61 の head `c9fab17` | 170・2 不一致 | 58・1 不一致 | 一致しない（1.28.1 と `2f800b2` の修正がない） |
| #64 の head `65f1089` | 171・1 不一致 | 46・13 不一致 | 一致しない |

- RC は main の子孫（main → RC が 13 コミット、逆向きは 0）。#61・#63・#64 の head はすべて RC に含まれている。
- **#61・#63・#64 を個別にマージしただけでは、main は本番と一致しない**（`0153840`・`2f800b2` が入らない）。main を本番に合わせるには RC `2f800b2` そのものを main に入れ、マージ後にテーマ 172・Core 59 が全件一致することを確かめる。
- RC が main に加えるのは 37 ファイルで、テーマは `assets/css/learn-v2.css`・`page-learn.php`・`style.css` の 3 つだけ。Industry Hub の本体（`inc/learn-industries.php`、テンプレート、`functions.php`）は `a617fd3` の時点で main に入っており、RC との差はない。本ブランチの開発環境での QA は、この Industry Hub のコードを含んだ状態で行っている。

## 0.6 統合後の main と 66 ファイルの組み合わせ（読み取りのみ）

| # | 確認 | 結果 |
|---|---|---|
| P1 | 3-way の比較（基準 `a617fd3`、相手 `2f800b2`、作業ツリーには書かず `git merge-file` を scratchpad で） | 重なるのは `page-learn.php`・`style.css` の 2 ファイルだけ。**競合 0**。マージした `page-learn.php` は `php -l` で問題なし。`.github/workflows/tests.yml` は RC 側で変更なし（RC は別の `release.yml` を追加） |
| P2 | `functions.php` の `require` | RC 側は変更なし。マージ後も `nav`・`icons`・`search-suggest`・`omochix-loader`・`learn-industries` がすべて読み込まれる |
| P3 | `inc/seo.php` | RC 側は変更なし。D2（`template_redirect` の優先度 1）と S2 はそのまま残る。Industry Hub は優先度 0 で先に判定し、`slim_seo_robots_index` は 30 で本ブランチ（10）より後に動くので、Hub の noindex が勝つ |
| P4 | 件数 | 66 のまま（RC は 66 ファイルのうち 2 つを変えるが、追加・削除はない） |

既存の機能を失う可能性：

1. **ファイルを丸ごと上書きすると失われる**：本ブランチの `page-learn.php` と `style.css` は `a617fd3` が基準。作業ツリーのファイルを統合後の main にコピーすると、SVG Motion Studio の導線（`page-learn.php`）とテーマの Version 1.29.0（`style.css`）が消える。必ず 3-way のマージで載せる。
2. `assets/css/learn-v2.css`（RC で 4 行追加）は 66 ファイルに含まれないので影響しない。本ブランチの CSS に `learn-hub-v2__soon` を変えるルールはない。
3. Core の変更（Version 表記、語彙の拒否を REST で 403・400 にする修正）は本ブランチのコードと関係しない。66 ファイルに Core は含まれない。
4. 確認が残る組み合わせ：索引は Learn の子ページとして `/learn/industries/` を載せる。業種別ページが非公開の今、一覧ページが 404 または noindex になる設定だと、検索候補と検索結果の Learn の関連欄にその URL が出る可能性がある。A3 で一覧ページの実際のステータスと合わせて確かめる（コードは変更していない）。

## 4. 統合の方法（承認後に行う手順。今回は実行していない）

1. **main を本番に合わせる**（別の承認）：RC `2f800b2` を main に入れる PR を作り、マージする（#64 → #61 → RC の順でもよいが、最後に RC が入ることが条件）。#63 は base が #61 のブランチなので、RC に含まれたことを記録して閉じる。マージ後に、0.5 と同じ照合でテーマ 172・Core 59 が全件一致することを確かめる。これを 0-3 の確定 HEAD とする。
2. **本ブランチをコミット**（別の承認）：`COMMIT-SCOPE.md` の 66 ファイルを `a617fd3` の上に 1 つのコミットにする。
3. **確定した main を本ブランチにマージ**（rebase はしない）。競合は 0 の見込み。マージ後に `git diff --stat <確定 HEAD>` が 66 ファイルであること、`page-learn.php` に SVG Motion の導線と `omochix_next_steps('learn')` の両方があることを確かめる。
4. **Version**：テーマ `style.css` を 1.29.0 → **1.30.0（候補）**。新機能（ナビ登録、検索候補、ローダー、ファビコン）を含むためマイナー版。Core は変更しないので 1.10.0 のまま。リリース用に `tools/release/` の設定を 1.29.0（`releases/industry-hub-2f800b2/omochix-theme-1.29.0.manifest.sha256`）を基準として作る。差分は 66 ファイルのうちテーマの 43 ファイルと `style.css` の Version だけになるはず。
5. 統合後の QA（下の 2 の A〜F に加えて）：
   - CI の全スイート（RC が追加した `tests/release`・`release.yml` を含む）。
   - 本番 31 URL のうち、本ブランチが変える画面（ヘッダー・フッター・検索・Learn・ホーム・記事・AI ツール）を統合後の環境でもう一度確認する。31/31 PASS は 1.29.0 の結果で、統合後の回帰確認にはならない。
   - `seo-snapshot.mjs` で 1.29.0（確定 HEAD）と統合後を比較する（C4）。
   - `STAGING-CHECKLIST.md` B+（キャッシュの残存時間）・B++（503 と回復）は、ステージングで LiteSpeed の条件がそろうまで未検証のまま。

## 1. 載せる前の確認（読み取りのみ）

| # | 確認 | 期待 | 結果 |
|---|---|---|---|
| P1 | 0-3 の HEAD と本ブランチの 3-way 比較（作業ツリーには書かず、`git merge-tree` などで） | 競合 0。競合があればファイルと箇所を記録して止める | |
| P2 | `functions.php` の `require` の順序 | RC の `inc/learn-industries.php` と、本ブランチの `nav`・`icons`・`search-suggest`・`omochix-loader` がすべて読み込まれる | |
| P3 | `inc/seo.php` | RC 側の変更と、本ブランチの D2（`omochix_singular_no_pagination`）・S2（`post_tag` のサイトマップ除外）が両方残る | |
| P4 | `COMMIT-SCOPE.md` の件数 | 66 件のまま（変われば理由を記録） | |

## 2. 確認項目（開発環境 → ステージング）

凡例：**自動**＝テストで判定できる、**手動**＝人の目か実機が必要。手動の項目は自動テストの結果で PASS にしない。

### A. 可視性制御と索引の組み合わせ

| # | 手順 | 期待 | 種類 | 結果 |
|---|---|---|---|---|
| A1 | Industry Hub を公開した状態で、ゲート（G1〜G7）を通らない業種を 1 つ用意し、索引を作り直す | 索引に業種名も業種の URL も入らない（索引は業種タクソノミーを含まない設計。それを実データで確認） | 自動 | |
| A2 | 同じ状態で `/?s=<業種名>`、および検索結果の Labs / Learn の関連欄 | 非公開の業種が出ない（RC のサイト内検索の保護が効いたまま） | 自動 | |
| A3 | `/learn/industries/`（一覧ページ）が索引に Learn として 1 件入る | 一覧ページが公開されているときだけ入る | 自動 | |
| A4 | `suggest-leak.php`・`suggest-r2r3.php` を統合後の環境で実行 | PASS | 自動 | |
| A5 | 匿名・管理者・パスワード Cookie 付きで索引の本文が一致 | 一致 | 自動 | |
| A6 | 業種を公開 → 非公開に変えたとき、Hub の保護（RC 側のゲートキャッシュ）と索引の transient の両方が更新される | Hub は 404、索引は変化なし（もともと業種を含まない） | 自動 | |

### B. Industry Hub のページ送りと D2 の除外

| # | URL | 期待 | 種類 | 結果 |
|---|---|---|---|---|
| B1 | `/learn/industries/page/2/`（一覧のページ送り） | D2 の 301 にならず、RC の仕様どおり（200、または範囲外なら RC の判定） | 自動 | |
| B2 | `/learn/industries/<公開業種>/page/2/` | タクソノミーのページなので D2 の対象外。RC の仕様どおり | 自動 | |
| B3 | `/learn/industries/<非公開業種>/`・`/page/2/` | 404（RC の `pre_handle_404`）。D2 のリダイレクトが割り込まない | 自動 | |
| B4 | `/learn/page/2/`・`/page/2/`・`/about/page/7/` | 301 が 1 回で正規の URL へ（D2 は変わらない） | 自動 | |
| B5 | 上の URL の HEAD リクエスト | GET と同じステータス（RC は `template_redirect` の優先度 0、D2 は 1） | 自動 | |
| B6 | リダイレクトの追跡回数 | すべて 1 以下。ループなし | 自動 | |

### C. Slim SEO（ステージング。本番と同じ版・設定）

| # | 手順 | 期待 | 種類 | 結果 |
|---|---|---|---|---|
| C1 | 主要 URL と公開業種の Hub の canonical | 自分自身。ページ送りは Slim SEO と RC の仕様どおり | 自動 | |
| C2 | 非公開の業種・索引しない業種（G6・G7 未達）の robots | noindex（RC の `slim_seo_robots_index`）。本ブランチの noindex 判定（`omochix_filter_slim_seo_indexability`）とぶつからない | 自動 | |
| C3 | サイトマップ（index と、`post_tag`・`category`・`ai_tool_category`・業種の各サブ sitemap） | noindex の URL が載らない、記事 1 件以下のタグが載らない、空ページ・404 がない | 自動 | |
| C4 | `seo-snapshot.mjs` で統合前（本番相当）と統合後を比較 | 差分は「次にできること」の h2 と、意図した変更だけ | 自動 | |
| C5 | Slim SEO のリダイレクト設定の全ルール | 変更前と同じ | 手動 | |

### D. 主要 URL・検索・ファビコン・固定バー

| # | 手順 | 期待 | 種類 | 結果 |
|---|---|---|---|---|
| D1 | `STAGING-CHECKLIST.md` H の全主要 URL に、Industry Hub の一覧・公開業種の Hub・非公開業種の Hub（404）を加える | ステータス・title・canonical・robots・ナビの現在地・コンソールエラー 0 | 自動 | |
| D2 | `qa.cjs`（16 項目）、`suggest.cjs`、`suggest-warming.cjs`、`ime.cjs` | PASS | 自動 | |
| D3 | 検索：結果あり・0 件・業種名・ツール名・日本語 | 通常の検索と候補の両方が動く | 自動 | |
| D4 | ファビコン（`STAGING-CHECKLIST.md` G1〜G4） | テーマのタグ 5 本、管理画面は WordPress のサイトアイコン | 自動 + 手動 | |
| D5 | Learn 実践ページの下部固定バーが、フッターや Hub への導線と重ならない | 重ならない | 手動（実機） | |

### E. 実機・支援技術（自動テストでは PASS にしない）

| # | 手順 | 期待 | 結果 |
|---|---|---|---|
| E1 | 日本語 IME（macOS・Windows・iOS・Android）：`STAGING-CHECKLIST.md` D1〜D4 | 変換中のキーを奪わない | |
| E2 | Safe Area（iPhone 縦横）：E1〜E5 に Industry Hub のページを加える | 食い込みなし | |
| E3 | スクリーンリーダー（VoiceOver・NVDA・TalkBack）：F1〜F4 に Hub の一覧と業種ページを加える | 読み上げ順が見た目と一致 | |

### F. FCP・LCP・CLS

| # | 手順 | 期待 | 結果 |
|---|---|---|---|
| F1 | `perf2.cjs`：統合前（本番相当の main の HEAD）と統合後を、低速 4G・モバイル・各 5 回の中央値で比較。ページは ホーム・Learn・Industry Hub 一覧・業種 Hub・記事・検索 | FCP・LCP の悪化 5% 以下、CLS 0 | |
| F2 | 検索欄に初めてフォーカスしたときの通信 | CSS 1 本と JSON 1 本だけ | |
| F3 | キャッシュ HIT / MISS それぞれの索引の応答時間と JSON のサイズ | 記録する | |

## 3. 確認の後

- すべて PASS か、未検証の理由と本番後の確認方法を書けたら、Version（候補 1.30.0）を決める。
- 本番の 0-2 の版を基準にマニフェストを作り、承認を依頼する。
- 本番への反映、キャッシュの削除、本番での読み取り確認は、それぞれ別に承認を受けてから行う。

## 5. ローカル統合と QA の結果（2026-11-15）

ブランチ `feature/nav-ux-update-v1`。基準の main は `3ef4a14`（本番 1.29.0 / Core 1.10.0 とマニフェストが全件一致）。push・PR はしていない。

| commit | 内容 |
|---|---|
| `4e145ac` | `COMMIT-SCOPE.md` の 66 ファイル（明示指定で stage） |
| `536ad24` | `3ef4a14` を通常の merge で取り込み（競合 0） |
| `e57b776` | テーマ Version 1.30.0（候補）と、1.29.0 を基準にしたテーマだけのリリース設定（66 ファイルとは別の 4 ファイル：`style.css` の Version、`tools/release/baseline/omochix-theme-1.29.0.sha256`、`tools/release/configs/theme-1.30.0.json`、`tools/release/README.md`） |

リリース設定：`OMOCHIX_RELEASE_CONFIG=theme-1.30.0.json` で `plan.py --check` は 43 ステップ（追加 22・置換 21）で PASS。ビルドは 2 回とも同じ zip になり、`verify.py` も PASS（194 ファイル）。順序は「新規ファイル → `functions.php`（新しい inc を読み込む）→ それを呼ぶテンプレートと inc → `style.css`」。テンプレートが `functions.php` より先に置き換わると、未定義の関数で止まるため。既存の関数と名前がぶつかる新規の関数はない。

### QA（開発環境 8940 = 本ブランチ、比較用 8941 = `a617fd3`。2 つは同じ DB を共有）

| 項目 | 結果 |
|---|---|
| PHP の構文（全 PHP ファイル） | 0 エラー |
| Tests 相当（ai-tools 3、learn-practice 96、learn-gallery 134、learn-composer 46、nav 16、article-v2 197、editorial-home 26、svg-motion shortcode 34、Node 4 本） | すべて PASS |
| Labs（PHP 285、core.js 121、data.js 41、JS 構文） | PASS。最初の実行は OS の一時フォルダに 11-11 の古いキャッシュが残っていて失敗。`TMPDIR` を分けて再実行すると 285/285。古いキャッシュは削除していない |
| Industry Hub（10 本、合計 562 項目） | すべて PASS |
| Release（`release-gate.php`、unittest 74 件、既定の `release.json` での `plan.py --check`） | **FAIL**（下の「判断が必要」） |
| D1〜D6・F1・R2・R3（`suggest-leak`・`suggest-flush`・`suggest-r2r3`・`article-tools`・`icons-f1`・`ime.cjs`・`suggest-warming.cjs`・`firefox-suggest.cjs`） | すべて PASS（IME は合成イベント。実機は未検証） |
| `qa.cjs`・`suggest.cjs` | Chromium・WebKit とも 16/16、候補の操作もすべて期待どおり |
| D2・Industry Hub の URL | `/page/2/`・`/?paged=3`・`/learn/page/2/`・`/about/page/7/`・`/labs/page/3/` は 301 が 1 回。`/learn/industries/`・`/page/2/`・業種・業種の `/page/2/`・大文字の業種は 404（GET と HEAD が同じ、no-store・`X-Robots-Tag: noindex`）。`?s=` は 400。D2 は割り込まない |
| 検索候補の索引 | 200、`public, max-age=300`、ETag。42 件。URL 41 件をすべて取得して 200（404・401・403 なし）。業種の項目なし |
| Learn の導線 | SVG Motion のページを公開扱いにして（メモリ上だけ、DB は書き換えない）描画すると、SVG Motion のカードと `omochix_next_steps('learn')` の両方が出る。公開していないときはカードなし（仕様どおり） |
| SEO の前後比較（10 ページ） | title・description・canonical・robots・JSON-LD・OG・リンクはすべて同じ。差分は 4 ページの h2「次にできること」だけ |

開発環境の業種は 6 件すべて非公開で、一覧ページ `/learn/industries/` はない（本番の「業種別ページは非公開」に近い状態）。**一覧ページを公開した場合はローカルで確認していない**。コード上、公開業種が 0 の一覧ページは 200・noindex になり、そのとき検索候補に「Learn」として載る（404 や権限拒否ではない）。本番の一覧ページの状態は未受領。

`/ai-news/` は 8940・8941 とも 404（共有 DB に該当カテゴリーがない）。本ブランチによる差ではない。

### 判断が必要

- **リリースの CI**：`tools/release/release.json`・`tests/release/release-gate.php`・unittest の 2 件は Industry Hub のリリース専用（29 ステップ、Core 14、Labs のファイルを含まないこと）。1.30.0 は Labs の 3 ファイル（`inc/labs.php`・`template-parts/labs/index.php`・`assets/icons/labs.svg`）を含むので、このままでは PR の Release ワークフローが失敗する。現在のリリースを 1.30.0 に切り替えるか（Industry Hub の設定は `configs/` に記録として残す）、ゲートの条件をどう変えるかは安全確認の内容に関わるので、今回は変更していない。

### 未検証（PASS にしない）

ステージング（Slim SEO の実出力・canonical・サイトマップ、LiteSpeed・CDN のキャッシュと 503 の扱い、`STAGING-CHECKLIST.md` の B+・B++）、実機（日本語 IME、Safe Area、Learn の固定バー、スクリーンリーダー）、FCP・LCP・CLS、本番の一覧ページの公開状態、本番 31 URL の 1.30.0 での回帰。
