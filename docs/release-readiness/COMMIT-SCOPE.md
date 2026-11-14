# コミット対象（確定版）

確定日：2026-11-14 / ブランチ：`feature/nav-ux-update-v1`（HEAD `a617fd3`、未コミット）
この一覧は `R2R3-FIX-REPORT.md` 第 5 節の候補一覧に代わるもの。**除外したファイルは削除していない**。コミットはまだ行っていない。

## 対象（合計 66 ファイル：変更 22・新規 44）

### テーマ：変更（21）
`wp-theme/omochix-theme/` 配下
```
archive-ai_tool.php
archive-prompt.php
assets/css/editorial-home.css
assets/css/shell.css
category.php
footer-editorial.php
footer.php
front-page.php
functions.php
header.php
inc/editorial-home.php
inc/labs.php
inc/seo.php
inc/shell.php
manifest.webmanifest
page-learn.php
search.php
single-ai_tool.php
single.php
style.css
template-parts/labs/index.php
```

### テーマ：新規（22）
`wp-theme/omochix-theme/` 配下
```
assets/css/footer-editorial.css
assets/css/search-suggest.css
assets/js/search-suggest.js
assets/icons/chat.svg
assets/icons/labs.svg
assets/loader/omochix-loader.css
assets/loader/omochix-loader.js
assets/loader/omochix-loader-page.js
inc/icons.php
inc/nav.php
inc/search-suggest.php
inc/omochix-loader.php
inc/omochix-loader-markup.php
assets/img/icons/favicon.ico
assets/img/icons/favicon.svg
assets/img/icons/favicon-16.png
assets/img/icons/favicon-32.png
assets/img/icons/apple-touch-icon-180.png
assets/img/icons/icon-192.png
assets/img/icons/icon-512.png
assets/img/icons/icon-maskable-192.png
assets/img/icons/icon-maskable-512.png
```
- ローダー 5 ファイル（`assets/loader/*`、`inc/omochix-loader*.php`）は、`functions.php` が読み込むので必須。ページ全体のオーバーレイは初期状態で OFF。
- ファビコンは、テーマ（`inc/icons.php`・`manifest.webmanifest`）が参照する 9 点だけ。

### CI・テスト（15：変更 1・新規 14）
```
.github/workflows/tests.yml          CI に tests/nav/run.php を追加
tests/nav/run.php                    CI（ナビ登録 16 項目）
tests/nav-ux/suggest-leak.php        D3（開発環境・LEAKTEST の検証データが必要）
tests/nav-ux/suggest-flush.php       D5
tests/nav-ux/suggest-r2r3.php        R2・R3
tests/nav-ux/article-tools.php       D6
tests/nav-ux/icons-f1.php            F1
tests/nav-ux/ime.cjs                 D1（Chromium・WebKit）
tests/nav-ux/ime-check.js            D1（ime.cjs と firefox-suggest.cjs が共有）
tests/nav-ux/firefox-suggest.cjs     D1・D2・候補（Firefox）
tests/nav-ux/suggest.cjs             検索候補の QA
tests/nav-ux/suggest-warming.cjs     R2（ブラウザ）
tests/nav-ux/qa.cjs                  ナビ / UX 総合 QA（16 項目）
tests/nav-ux/seo-snapshot.mjs        SEO の前後比較（チェックリスト A7）
tests/nav-ux/perf2.cjs               FCP / LCP / CLS（チェックリスト I1）
```
- `tests/nav-ux/` は開発環境・ステージング用で、CI では実行しない。
- Playwright の場所は `PW_CORE` 環境変数で指定する。
- `suggest.cjs`・`qa.cjs`・`seo-snapshot.mjs`・`perf2.cjs` は、結果を `docs/` の下に書き出す（`perf2.cjs` は `OUT_DIR` で変更可）。ステージングで実行したときに出た結果ファイルや画像は、コミットに含めない。

### 文書（8）
```
docs/release-readiness/COMMIT-SCOPE.md            この一覧
docs/release-readiness/STAGING-CHECKLIST.md       ステージングの実行チェックリスト
docs/release-readiness/POST-INDUSTRY-HUB-CHECKS.md Industry Hub 統合後の確認項目
docs/release-readiness/R2R3-FIX-REPORT.md
docs/release-readiness/P0-FIX-REPORT.md
docs/release-readiness/FINAL-CHECK-REPORT.md
docs/nav-ux-update/NAV-REGISTRY.md                ナビ登録（published / coming-soon / hidden）の運用説明
docs/ux-expansion/07-SEARCH-SUGGEST.md            検索候補の仕様
```
- 本番の URL、秘密情報、保護コンテンツの実データは含まれていない（外部 URL は GitHub の PR だけ）。

## 除外（削除しない）

| 対象 | 件数 | 理由 |
|---|---|---|
| `design/logo-assembly-loader/`（PNG 以外） | 49 | 指示により今回は除外。テーマには組み込み済みのコピーがある |
| `design/` の PNG | 17 | 指示により除外 |
| `docs/nav-ux-update/screens/`・`docs/ux-expansion/screens/` | 326（約 47MB） | QA 画像 |
| 元の作業ツリーの `releases/`（配布パッケージと ops） | — | 別作業の成果物。この作業ツリーの外にある |
| 参照されていないファビコン（`favicon-48.png`、`favicon-navy.svg`、`favicon-transparent*` の 5 点） | 7 | テーマから参照されていない |
| `tools/icons/build-favicons.cjs` | 1 | 除外する `design/logo-assembly-loader/brand-source/` に依存しており、単独では動かない。ファビコンを作り直すときは `design/` と一緒に扱う |
| `tests/nav-ux/perf.cjs`・`firefox.cjs`・`shots.cjs` | 3 | 前段階のもの（`perf2.cjs`・`firefox-suggest.cjs` に置き換え済み、`shots.cjs` は画像を作るだけ） |
| `docs/nav-ux-update/` の `REPORT.md`・`FINAL-AUDIT.md`・`*.json` 5 本 | 7 | 作業時点の記録。リリースには不要 |
| `docs/ux-expansion/` の `01`〜`06`・`REPORT.md`・`*.json` 6 本 | 13 | 設計段階の文書（株価影響の設計などを含む）と測定記録 |
| `docs/release-readiness/` の `RELEASE-READINESS-AUDIT.md`・`MINIMAL-DIFFS.md`・`STAGING-PLAN.md`・`NEWS-AUTOMATION-HANDOFF.md`・`*.json` 3 本 | 7 | `STAGING-CHECKLIST.md` と各報告に置き換え済み。ニュース自動化は別プロジェクト |

## ステージングに追加するときのコマンド（実行は承認の後）

変更ファイルを追加し、新規ファイルは一覧で指定する。

```bash
cd ~/omochix-nav-ux-wt
git add -u
git add -- \
  wp-theme/omochix-theme/assets/css/footer-editorial.css wp-theme/omochix-theme/assets/css/search-suggest.css \
  wp-theme/omochix-theme/assets/js/search-suggest.js wp-theme/omochix-theme/assets/icons/chat.svg wp-theme/omochix-theme/assets/icons/labs.svg \
  wp-theme/omochix-theme/assets/loader/omochix-loader.css wp-theme/omochix-theme/assets/loader/omochix-loader.js wp-theme/omochix-theme/assets/loader/omochix-loader-page.js \
  wp-theme/omochix-theme/inc/icons.php wp-theme/omochix-theme/inc/nav.php wp-theme/omochix-theme/inc/search-suggest.php \
  wp-theme/omochix-theme/inc/omochix-loader.php wp-theme/omochix-theme/inc/omochix-loader-markup.php \
  wp-theme/omochix-theme/assets/img/icons/favicon.ico wp-theme/omochix-theme/assets/img/icons/favicon.svg \
  wp-theme/omochix-theme/assets/img/icons/favicon-16.png wp-theme/omochix-theme/assets/img/icons/favicon-32.png \
  wp-theme/omochix-theme/assets/img/icons/apple-touch-icon-180.png wp-theme/omochix-theme/assets/img/icons/icon-192.png \
  wp-theme/omochix-theme/assets/img/icons/icon-512.png wp-theme/omochix-theme/assets/img/icons/icon-maskable-192.png \
  wp-theme/omochix-theme/assets/img/icons/icon-maskable-512.png \
  tests/nav/run.php tests/nav-ux/suggest-leak.php tests/nav-ux/suggest-flush.php tests/nav-ux/suggest-r2r3.php \
  tests/nav-ux/article-tools.php tests/nav-ux/icons-f1.php tests/nav-ux/ime.cjs tests/nav-ux/ime-check.js \
  tests/nav-ux/firefox-suggest.cjs tests/nav-ux/suggest.cjs tests/nav-ux/suggest-warming.cjs tests/nav-ux/qa.cjs \
  tests/nav-ux/seo-snapshot.mjs tests/nav-ux/perf2.cjs \
  docs/release-readiness/COMMIT-SCOPE.md docs/release-readiness/STAGING-CHECKLIST.md docs/release-readiness/POST-INDUSTRY-HUB-CHECKS.md \
  docs/release-readiness/R2R3-FIX-REPORT.md docs/release-readiness/P0-FIX-REPORT.md docs/release-readiness/FINAL-CHECK-REPORT.md \
  docs/nav-ux-update/NAV-REGISTRY.md docs/ux-expansion/07-SEARCH-SUGGEST.md
git diff --cached --stat | tail -1      # 66 files であること
git diff --cached --name-only | grep -E '/screens/|^design/|^releases/|\.bak$' && echo "除外対象が混入" || echo "OK"
```

注意（2026-11-15 追記）：統合後の main と重なるのは `page-learn.php`・`style.css` の 2 ファイルで、3-way の比較では競合 0。ファイルを丸ごとコピーすると SVG Motion の導線と Version 1.29.0 が消えるので、必ずマージで載せる（`POST-INDUSTRY-HUB-CHECKS.md` 0.6・4）。

注意：この一覧は、Industry Hub が統合された後の main に載せ直すときに使う。統合後の main で差分が変わった場合（競合の解消など）は、件数をもう一度確認する。
