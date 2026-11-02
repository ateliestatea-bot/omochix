# Motion 20→50（実態 23→53）実装計画（M2〜M4）

作成日：2026年11月3日
基準：main `f4fe9f1`（Theme 1.26.0 / Core 1.9.2）。本番 Theme 1.26.0。
原則：Theme のみ変更（`catalog.json`・テスト・必要なら `learn-v2.css` の最小調整）。Core Plugin・AI Tools（P5b / P5c）・本番 WordPress・DB・FTP・公開記事には触れない。既存 23 件の `preview` と slug・ID は変更しない。

## 1. 変更対象と影響範囲

| ファイル | 変更 | 影響 |
|---|---|---|
| `wp-theme/omochix-theme/assets/data/motion/catalog.json` | `categories` に `cta`（order 3）と `loading`（order 7）を追加し order を繰り下げ、`items` に 024〜053 を追加、`updated` 更新 | 一覧の並び、Composer のセレクト、Learn トップ（featured は変更なし）、一覧の埋め込み JSON サイズ（約 76KB → 約 170KB 見込み。カード分の preview 断片は可視分だけ） |
| `tests/learn-gallery/run.php` | 件数 23→53、カテゴリ 6→8、順序配列、ui / camera / 新カテゴリの count、逆引き slug 配列 | テストのみ |
| `tests/learn-composer/run.php` | JS 見本 2→7 件の `omx.root` 規約チェック、テスト用 data json の件数 | テストのみ |
| `tests/learn-gallery/params-parity.js` | 固定件数なし（自動） | — |
| `wp-theme/omochix-theme/assets/css/learn-v2.css` | 原則変更なし。カテゴリ 8 個のチップが 375px で横スクロールに収まることを確認し、必要なら余白のみ調整 | 一覧 |
| `docs/Learn-v2-Spec.md` | §9 の「20 件・5 カテゴリ」を更新、M2 の節を追加 | 文書 |
| `docs/Beta-Deployment-Runbook.md` 8b | 件数と確認 URL を更新 | 文書 |

既存ファイルを変更する前に、各段階で `git diff --stat` と上の表の影響範囲を照合し、`catalog.json` の既存 23 件が `git diff` で一切変わっていないこと（追加行のみ）を確認してから進める。

## 2. 制作ルール（1 件ごとのチェックリスト）

1. `id` は 3 桁連番、`slug` は英小文字とハイフン、既存と重複しない。
2. `preview.html` ≤ 8,000 / `css` ≤ 12,000 / `js` ≤ 12,000 バイト、`</script>` `</style>` を含まない、外部 URL・`@import`・`url()` を使わない。
3. ステージ 320×180 の中に収まる（`.omx-stage` は中央寄せの grid）。色は `var(--primary)` 等のトークン。ダークでも見えることを確認。
4. ループは `--p-cycle` を 1 周期にする（既定 2〜6 秒）。hover 依存の見本は周期で自動デモし、hover は `@media (hover:hover)` で追加。
5. `playground.params` は 3〜5 個。CSS 向けは `css_var` を `var(--p-x, 既定値)` で読む。`range` は `(max−min)/step` が整数、`select` は 2〜8 択、`color` は `#rrggbb`。各パラメータに `prompt`（`{value}` 入り、120 字まで）を付ける。
6. JS 見本：`omx.root.querySelector` のみ、`omx.animate(fn(t))`、`omx.onParams`、`omx.reduced` の分岐。`document.`・`window.parent`・`fetch`・`setInterval` を使わない（ループはランタイムが管理）。
7. `prompt.ask`（依頼文 1 文）、`prompt.spec`（1〜10 行の仕様＝実装手順）、`prompt.use`（使いどころ）。`description` に「既存のどれと何が違うか」を 1 文。
8. `related` は本番に存在する slug だけ。`featured` は付けない。
9. 追加後に `tests/learn-gallery/run.php`・`params-parity.js`・`tests/learn-composer/*` が通る。

## 3. 段階と承認ポイント

| 段階 | 内容 | ブランチ | 成果 | 承認 |
|---|---|---|---|---|
| M1（本書） | 監査・設計・計画 | `feature/motion-50-m1`（docs のみ） | `docs/motion/MOTION-EXISTING-20-AUDIT.md`、`MOTION-NEW-30-CATALOG.md`、`MOTION-IMPLEMENTATION-PLAN.md` | 設計と件数（53 か 50）の承認 |
| M2-0 | カテゴリ追加と order 変更、テストの件数・順序を「追加分込み」に更新できる形へ | `feature/motion-50-m2-0` | `catalog.json`（categories のみ）、テスト更新 | commit |
| M2-1 | CTA 6 件（024〜029） | `feature/motion-50-m2-cta` | 6 件追加、テスト緑、Local で 6 件のプレビュー・コピー・調整を確認 | commit → PR |
| M2-2 | テキスト 6 件（030〜035） | `…-text` | 同上 | 同上 |
| M2-3 | スクロール 6 件（036〜041） | `…-scroll` | 同上 | 同上 |
| M2-4 | カード・UI 6 件（042〜047） | `…-ui` | 同上 | 同上 |
| M2-5 | 背景 2 件＋ローディング 4 件（048〜053） | `…-ambient-loading` | 同上 | 同上 |
| M3 | 53 件の品質検証（§5） | QA 環境（Local） | `MOTION-QA-RESULT.md` | 不具合修正の commit |
| M4 | リリース準備 Theme 1.27.0 | `release/theme-1.27.0` | ZIP / SHA / manifest / 変更一覧 / deploy.md、`MOTION-PRODUCTION-DEPLOY-PLAN.md` | PR / merge / 本番配置は個別承認 |

各段階で commit・push・PR・merge は承認を待つ。M2 の各バッチは 1 PR にまとめてもよい（依頼者の指定に従う）。

## 4. 制作の進め方（M2）

1. 1 件ずつ `catalog.json` に追加し、`php tests/learn-gallery/run.php` と `node tests/learn-gallery/params-parity.js` を回す（バリデータが構造・params・`var()` の読み取りを機械的に検査する）。
2. 6 件ごとに Local の `/learn/motion/`（一覧）と `/learn/motion/<slug>/`（詳細）で、プレビューの動作・一時停止・再開・拡大・調整パネル・コピー・ダークモードを確認し、スクリーンショットを `docs/learn-v2-qa/` の形式で残す。
3. コピーしたコードの独立動作：各見本のコピー結果を単独 HTML として保存し、ブラウザで開いて動くことを確認（既存 Phase 3E と同じ方法）。
4. Composer：新規見本を 3 スロットに入れた結合プレビューと AI 指示書を 1 組ずつ確認（JS 見本 5 件は必須）。
5. Local が止まっている場合は、実テーマの CSS/JS を読む静的ハーネス（Phase 5 と同じ方法）で代替し、その旨を記録する。

## 5. 品質検証の項目（M3、`MOTION-QA-RESULT.md` に PASS/FAIL で記録）

- 53 件の詳細ページ（200、PHP 警告なし、title・canonical、前後リンク）。
- 53 件のライブプレビュー（一覧カード・詳細・拡大の 3 箇所、ダークモード、`prefers-reduced-motion` で停止→再生ボタン）。
- コピーしたコードが単独で動く（53 件、JS 見本は shim 込み）。
- 検索・カテゴリ（8 チップ）・難易度・技術フィルターと件数表示、0 件表示、URL での絞り込み（`?cat=cta` 等）。
- 一時停止・再開（個別、すべて一時停止、タブ非表示）、「既定に戻す」。
- モーダル（ダイアログ・拡大）、Esc・背景クリック・戻る、フォーカス復帰、Tab 順。
- 375 / 768 / 1440px（横スクロールなし、チップの横スクロール、カード列数）。
- コンソールエラー 0、一覧の埋め込み JSON サイズと初回描画時間の記録。
- iframe サンドボックス：新規 JS 5 件が `document`・`parent`・ネットワークに触れないこと（静的検査＋実行時にコンソールのエラー・警告なし）。
- 既存 23 件の回帰（Phase 1.24.2 の本番監査と同じ項目）。
- テスト：learn-gallery / params-parity / learn-composer / core-parity / learn-practice / article-v2 / editorial-home。

## 6. リリース・本番移行（M4 の要点）

- Theme 1.27.0 として `catalog.json`・`style.css`（Version）・必要なら `learn-v2.css` を配置。Core は 1.9.2 のまま。
- 本番に先に FTP 反映されたファイルがないか（1.26.0 の manifest と照合）を確認してから差分を確定する。
- ロールバック：`catalog.json` を 1.26.0 の版に戻すだけで 23 件に戻る（DB 変更なし）。
- 公開後：`/learn/motion/` が 53 件・8 カテゴリ、`/learn/motion/compose/` のセレクトに新規が出る、Learn トップの featured が従来どおり 3 件。

## 7. リスクと対策

| リスク | 対策 |
|---|---|
| 一覧が重くなる（53 件） | iframe は可視分のみ。M3 で 375px の初回描画と JSON サイズを測り、閾値超なら「もっと見る」（12 件ずつ）を別 PR で検討 |
| 新カテゴリ追加でテストが壊れる | M2-0 で先にテストを「カテゴリ数・順序」ごと更新し、各バッチで件数だけ増やす |
| hover 依存の見本がスマホで動かない | 周期の自動デモを必須にし、hover は追加扱い |
| `scroll-driven` の非対応ブラウザ | JS フォールバックを同梱し、`@supports (animation-timeline: scroll())` で分岐 |
| JS 見本の安全性 | `omx.root` 規約テストに 5 件を追加、`document`/`parent`/`fetch` の静的検査を M3 のチェックに含める |
| 関連先の slug が本番に無い | M2 で本番の公開 slug を読み取りで照合（Phase 4C と同じ手順） |
