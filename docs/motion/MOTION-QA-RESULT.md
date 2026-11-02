# Motion 50（実態 23→53）M3 品質検証結果

検証日：2026年11月3日
対象：main `5a4fdb6`（PR #52 マージ後、Theme 1.26.0 のコード＋53 件カタログ、未リリース）
環境：Local「omochix-learn-v2」（WordPress 7.1.3 / PHP 8.2.29 / MySQL 8.4.0、Core Plugin は Local 側の 1.6.0 のまま）。Local アプリは別サイト（omochix-p5a-qa）を起動中だったため、learn-v2 のサービス（mysqld / php-fpm / nginx :10003）のみ手動起動し、テーマ symlink を一時的にこの作業ツリーへ向けた。検証後に停止・原状復帰（`omochix-composer-v1` のテーマ）。Local の php.ini は `auto_prepend_file` が未マウントの DMG を指していたため、パスを `/Applications/Local.app` に差し替えた複製を scratch から渡した（Local の設定ファイルは無変更）。
ブラウザ：Claude の内蔵ブラウザ（Chromium）、1440 / 768 / 375px。
本番 WordPress・DB・FTP・公開記事には触れていない。

## 1. 結果一覧

| # | 項目 | 結果 | 根拠 |
|---|---|---|---|
| 1 | 53 件の詳細ページ（200、PHP 警告なし、title・canonical） | PASS | 53/53 が 200、本文に Warning/Notice/Fatal なし、title に日本語名、canonical が `/learn/motion/<slug>/`、最大 0.058s |
| 2 | 詳細の前後リンク | PASS | 001 は「次」のみ、024 は 025 へ、カテゴリ順で連結（詳細 §3） |
| 3 | 一覧：53 件・8 チップ・件数表示 | PASS | カード 53、チップ「すべて53 / 登場・退場4 / テキスト・数字10 / ボタン・CTA6 / UIパーツの動き10 / スクロール演出10 / 背景・雰囲気6 / ローディング・待機4 / カメラワーク3」、「53件の見本」 |
| 4 | 一覧：ライブプレビュー（可視分のみ iframe、画面外は一時停止） | PASS | 初回は可視 4 枚だけ iframe（`sandbox="allow-scripts"`・`srcdoc`）。全スクロール後に 50 枚マウント、再生中は可視 9〜10 枚のみ。残りは可視になった時点でマウント（dot-grid-wave で確認） |
| 5 | フィルター：カテゴリ / 難易度 / 技術 / 検索 / 0 件 / リセット / URL 同期 | PASS | `?cat=cta` 6 件（セクションは「ボタン・CTA」のみ）、`?cat=loading` 4 件、`?cat=loading&level=beginner` 3 件（spinner-ring / dots-bounce / progress-stripes）、`?tech=js` 7 件（count-up / scramble-text / magnetic-button / morph-submit / tilt-card / scroll-driven-bar / particles）、`?q=波` 5 件、`zzzz` 0 件、リセットで 53 件・URL パラメータなし |
| 6 | SSR 絞り込み（JS なし相当） | PASS | `/learn/motion/?cat=cta` と `?cat=loading&level=beginner` が 200（curl）。JS 側の結果と一致 |
| 7 | 「すべて一時停止」トグル | PASS | 再生中 4 → 0（`aria-pressed=true`、ラベル「すべて再生」）→ 4 |
| 8 | 詳細ダイアログ（一覧上） | PASS | tilt-card：`dialog.motion-dialog` が開き、プレビュー再生、コード・調整パネル（range 4＋color）、URL が `/learn/motion/tilt-card/` に。Esc で閉じ、URL は一覧へ戻り、フォーカスはカードのリンクへ復帰 |
| 9 | 拡大ダイアログ | PASS | liquid-fill：`dialog.motion-expand`、iframe 1132×636 で再生、閉じるボタンあり、Esc で閉じる |
| 10 | ダークモード | PASS | ヘッダーのトグルで `data-theme=dark`（body 背景 rgb(23,23,25)）、新規カードのプレビューも暗色トークンで描画（M2 でも 30 件を dark 文書で確認） |
| 11 | 375 / 768 / 1440px | PASS（375 は修正後） | 横スクロールなし（scrollWidth = innerWidth）。カード列 1 / 2 / 3。375 では 9 チップが 5 行に折り返しフィルター部が 628px になっていた → §2 の修正で 1 行の横スクロール（448px）。768 は 2 行折り返し（許容） |
| 12 | コンソールエラー | PASS | 一覧・詳細・Composer・Learn トップで 0 |
| 13 | 一覧の埋め込み JSON と初回描画 | PASS | `#omx-motion-catalog` 128,036 バイト（HTML 全体 319KB、1.26.0 時は約 76KB）。DOMContentLoaded / load 144ms（1440）、121ms（375）。「もっと見る」分割は不要と判断 |
| 14 | iframe サンドボックスと JS 見本の安全性 | PASS | 全 iframe が `sandbox="allow-scripts"`。JS 7 件（既存 2＋新規 5）を静的検査：`document.`・`window.parent`・`fetch`・`XMLHttpRequest`・`localStorage`・`setInterval`・外部 URL・`</script>` なし。composer テストの `omx.root` 規約（7 件）PASS |
| 15 | 調整パネル（詳細） | PASS | magnetic-button：range 3（strength / radius / cycle）＋color。値を変えると URL ハッシュ `#p=strength:0.6,…`、「既定に戻す」で復帰。コピー用ボタンのラベルが「（調整済み）」に |
| 16 | コピー機能 | 未検証（環境要因） | Local の http では `navigator.clipboard` が使えず execCommand 経路が失敗（Phase 4B/5 と同じ既知事象）。https は自己署名証明書で内蔵ブラウザが開けない。生成されるコード／プロンプト本文は `tests/learn-gallery/run.php`（code / prompt / params の各テスト）で検証済み |
| 17 | Composer | PASS | `#c=1;tilt-card;check-draw:thick=6;scroll-driven-bar` で 3 スロット再生、結合プレビュー再生、バッジ「1項目を調整中」、指示書（16,358 字）に `omxSlot(`・`--p-thick:6px`・`omx.root`・3 見本の名前。`#omx-composer-data` は 53 件・35,463 バイト。ピッカーに 53 件（hub プレビュー）が並ぶ |
| 18 | Learn トップ | PASS | featured は従来どおり pop-in / typewriter / scroll-fade-in の 3 件、「動く見本を見る」CTA あり、横スクロールなし |
| 19 | 既存 23 件の回帰 | PASS | 23 件の詳細が 200・警告なし（#1）。pop-in 詳細：range 2、関連 3、次＝fade-up。一覧の既存カード（013 scroll-fade-in 等）は再生。既存 23 件の JSON は PR #52 で無変更（diff は追加行のみ） |
| 20 | prefers-reduced-motion | 間接確認 | 内蔵ブラウザでメディア特性をエミュレートできない。ランタイム（1.26.0 から無変更）が初期フレームで停止して「再生」を出す挙動は Phase 1 で確認済み。新規 JS 5 件の `omx.reduced` 分岐は M2 の Node シミュレーションで確認（最終状態を描く） |
| 21 | テスト | PASS | learn-gallery 134 / params-parity 4664（53 samples）/ learn-composer 46 / core-parity 78 / learn-practice 96 / article-v2 197 / editorial-home 26、`git diff --check` OK |

PASS 19、未検証 1（コピー：環境要因）、間接確認 1（reduced-motion）。FAIL 0（#11 は修正後 PASS）。

## 2. QA 中の修正（未コミット、承認待ち）

| ファイル | 変更 | 理由 |
|---|---|---|
| `wp-theme/omochix-theme/assets/css/learn-v2.css` | `@media (max-width: 767px)` に `.motion-filter__chips { flex-wrap: nowrap; overflow-x: auto; … scrollbar-width: none }`、`.motion-filter__chip { flex: 0 0 auto; scroll-snap-align: start }` を追加（+4 行） | 8 カテゴリで 375px のチップが 5 行に折り返し、一覧の最初のカードまで 628px 空いていた。実装計画 §1 の「375px で横スクロールに収まることを確認し、必要なら調整」に該当。キーボードで末尾のチップにフォーカスすると自動で見える位置までスクロールすることを確認 |

既存 23 件・`catalog.json`・PHP・JS は変更なし。

## 3. 観察・注意

- 詳細ページ・ダイアログのプレビューは IntersectionObserver で可視時にマウントする。ブラウザペインが非表示だと rAF が止まり「マウント前」に見えるが、表示すると再生する（Phase 5 と同じ環境要因）。
- 一覧の HTML は 319KB（gzip 前）。53 件分のカード断片と 128KB の JSON を含むが、iframe は可視分のみなので初回描画は 150ms 未満。モバイル回線では転送量が増えるため、将来 80 件を超えるなら「もっと見る」を検討。
- 新規で使った関連 slug：prompt `development-prototype-brief-for-ai-builders`、tool `claude-code`（M1 方針どおり）。Local には該当投稿があり（/ai-tools/cursor/・/ai-tools/claude-code/・/learn/development/ へリンク）、本番では公開済みの slug にだけリンクが出る仕様。
- Local の Core Plugin は 1.6.0（別 worktree の symlink）。Motion はテーマ完結のため影響なし。
- 本番配置時の確認項目は `docs/Beta-Deployment-Runbook.md` 8b 手順 6。
