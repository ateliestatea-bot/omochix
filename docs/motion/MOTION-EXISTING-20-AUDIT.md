# Motion 既存教材 監査（M1）

作成日：2026年11月3日
基準：main `f4fe9f1`（Theme 1.26.0 / Core 1.9.2）。本番は Theme 1.26.0。
対象：`wp-theme/omochix-theme/assets/data/motion/catalog.json` と Learn v2（動く見本）の実装。読み取りのみで作成した。

## 0. 前提の訂正：既存は 20 件ではなく 23 件

Phase 1（2026年10月17日）の 20 件に、Phase 4C（2026年10月22日、Theme 1.25.0）で「カメラワーク」3 件（021 dolly-in / 022 pan / 023 orbit）が追加され、本番の `/learn/motion/` は **23 件・6 カテゴリ**で公開中。本計画の「30 件追加」を実施すると合計 53 件になる。50 件ちょうどにするなら追加は 27 件（各カテゴリ 6 件の案から 3 件減らす）になる。本書は 30 件追加（53 件）を前提に書き、判断は依頼者に委ねる。

## 1. 既存教材の一覧

ID / slug / カテゴリ / 難易度 / 技術 / 調整パラメータ数 / プレビューのサイズ（HTML・CSS・JS の文字数）。表示形式はすべて共通（一覧カード＝ポスター＋遅延 iframe、詳細＝大きいプレビュー＋プレイグラウンド＋プロンプト＋コード＋関連）。

| ID | slug | カテゴリ | 名前 | 難易度 | 技術 | params | HTML/CSS/JS |
|---|---|---|---|---|---|---|---|
| 001 | pop-in | entrance 登場・退場 | ポップイン | 初級 | css | 4 | 84 / 771 / 0 |
| 002 | fade-up | entrance | フェードアップ | 初級 | css | 3 | 116 / 594 / 0 |
| 003 | stagger | entrance | スタガー | 初級 | css | 4 | 136 / 497 / 0 |
| 004 | mask-reveal | entrance | マスクリビール | 中級 | css | 3 | 88 / 708 / 0 |
| 005 | typewriter | text テキスト・数字 | タイプライター | 初級 | css | 4 | 32 / 412 / 0 |
| 006 | count-up | text | カウントアップ | 中級 | css+js | 4 | 98 / 286 / 669 |
| 007 | marker-highlight | text | マーカー線 | 初級 | css | 4 | 45 / 550 / 0 |
| 008 | kinetic-words | text | ワードスワップ | 中級 | css | 4 | 143 / 537 / 0 |
| 009 | toggle-switch | ui UIパーツの動き | トグルスイッチ | 初級 | css | 3 | 84 / 623 / 0 |
| 010 | button-press | ui | ボタンの押し込み | 初級 | css | 4 | 92 / 683 / 0 |
| 011 | toast | ui | トースト通知 | 初級 | css | 4 | 59 / 653 / 0 |
| 012 | skeleton-loading | ui | スケルトン | 中級 | css | 3 | 272 / 1249 / 0 |
| 013 | scroll-fade-in | scroll スクロール演出 | スクロールでフェードイン | 中級 | css | 4 | 302 / 1034 / 0 |
| 014 | sticky-header | scroll | 追従ヘッダー | 中級 | css | 4 | 184 / 1118 / 0 |
| 015 | scroll-progress | scroll | 読了プログレスバー | 初級 | css | 3 | 128 / 823 / 0 |
| 016 | parallax | scroll | パララックス | 中級 | css | 4 | 110 / 945 / 0 |
| 017 | gradient-shift | ambient 背景・雰囲気 | 流れるグラデーション | 初級 | css | 5 | 42 / 496 / 0 |
| 018 | floating-blobs | ambient | ふわふわブロブ | 初級 | css | 5 | 144 / 963 / 0 |
| 019 | light-sweep | ambient | ライトスイープ | 中級 | css | 5 | 89 / 859 / 0 |
| 020 | particles | ambient | パーティクル | 上級 | css+js | 4 | 72 / 79 / 905 |
| 021 | dolly-in | camera カメラワーク | ドリーイン | 初級 | css | 4 | 93 / 998 / 0 |
| 022 | pan | camera | パン | 初級 | css | 5 | 247 / 580 / 0 |
| 023 | orbit | camera | オービット | 中級 | css | 4 | 86 / 746 / 0 |

集計：初級 11・中級 11・上級 1。CSS のみ 21、JS あり 2（count-up、particles）。featured（Learn トップに出る）は pop-in / typewriter / scroll-fade-in / gradient-shift の 4 件。`video` は全件 null（予約フィールド）。

## 2. 各教材のデータ構造（HTML・CSS・JS）

- 1 件＝`catalog.json` の `items[]` 1 要素。`preview.html`（必須、8,000 バイトまで）、`preview.css`（12,000 まで）、`preview.js`（12,000 まで、省略可）。`</script>` `</style>` を含むと読み込み時に拒否される。
- 見本は **320×180 の固定ステージ**に対して作り、iframe 内のランタイムが `scale()` で枠に合わせる。色は `var(--primary)`・`var(--surface)`・`var(--text)` などランタイムのトークン（ライト／ダークの両方の値を持つ）を使う。
- 調整パラメータ（`playground.params`、3〜5 個が運用上の基準、最大 8）：`range` / `select` / `color`。CSS 向けは `css_var`（`--p-…`）を持ち、**CSS が `var(--p-x, 既定値)` の形で必ず読む**ことを検証される。JS 向けは `omx.param('key', 既定値)` で読む。
- JS 見本の規約：`omx.root.querySelector(...)` でステージ内だけを参照し（`document.querySelector` 禁止、テストで検査）、`omx.animate(function (t) {…})` で描画、`omx.onParams()` で調整値の変化を受け取り、`omx.reduced` が真なら最終状態を即描く。
- ループの規約：多くの CSS 見本は `--p-cycle` を 1 周期として `infinite` で繰り返し、プレビューと Composer の結合プレビューで同じ周期設計を使う。
- 本文側の情報：`name.ja/en`、`summary`（80 字）、`description`（300 字）、`tags`（12 個まで）、`prompt.ask / spec（1〜10 行）/ use`、`related.prompts / tools / learn`（slug・パス。表示時に公開済みのものだけ解決）。

## 3. ライブプレビューの実装方式

- 一覧カード・詳細・拡大表示・Composer のすべてが同じ `omochix_learn_gallery_preview_template()`（PHP）を使い、`learn-gallery.js` も同じテンプレートを埋めて iframe の `srcdoc` を組み立てる（PHP と JS が乖離しない構造）。
- iframe は `IntersectionObserver` で可視範囲に近づいたときだけ生成（`mount()`）、離れると一時停止。ページの `visibilitychange` でも一時停止・再開。
- ランタイム（`assets/js/learn-preview-runtime.js`、`assets/css/learn-preview-runtime.css`）を全ドキュメントにインライン。`postMessage` で `omx:play` / `omx:pause` / `omx:theme` / `omx:params` を受け取り、`omx:ready` を親へ返す。
- ダークモード：親の `html[data-theme]` を `MutationObserver` で監視して全 iframe へ転送。
- `prefers-reduced-motion: reduce`：ランタイムが最初のフレームで停止し、親が「再生」ボタンを出す。利用者の操作でのみ動く。CSS 見本は `:root[data-paused] *{animation-play-state:paused}` で静止、JS 見本は `omx.animate` のループが止まる。
- 詳細ページは JS 無効でも `<noscript>` の静的 iframe で動く。

## 4. コードコピー・検索・絞り込み

- コピー：`[data-omx-copy][data-omx-copy-target]` のイベント委任。`navigator.clipboard` → 失敗時は `execCommand('copy')` にフォールバック。結果は `aria-live` に通知。
- コピーされるコード：`omochix_learn_gallery_code_text()` が「プレビューで動いているものと同じ」自己完結 HTML 断片（`<style>`＋トークン＋`:root{--p-…}` 調整値＋HTML＋JS 見本なら最小ランタイム shim `omochix_learn_gallery_js_shim()`）を生成。単体で動作することを Phase 3E で確認済み。プロンプトも `prompt.spec` と調整値から合成してコピーできる。
- 検索・絞り込み（`filter-bar.php`）：`cat`（チップ）、`q`（名前・説明・タグ・英名）、`level`、`tech`。GET フォームで JS なしでも動き、JS はカードの `hidden` を切り替えるだけ（DOM を作り直さない）。件数表示、「絞り込みを解除する」、0 件表示。Phase 1.24.1/1.24.2 で `[hidden]` の CSS 競合を修正済み。
- ページ送りなし（全件を 1 ページに描画。53 件でもカードはポスターのみで iframe は可視分だけなので実害は小さいが、M3 で 1440px / 375px の描画時間を確認する）。

## 5. 一時停止・再開・リセット

- プレビューごとの再生／一時停止ボタン（`aria-pressed`、ラベルに見本名）と、一覧の「すべて一時停止」（`sessionStorage` に記憶）。
- **「リセット」に相当する機能は無い**：CSS 見本は `infinite` ループなので自然に先頭へ戻り、JS 見本は `omx.animate` の経過時間 `t` で描くため一時停止→再開で続きから動く。プレイグラウンドには「既定に戻す」がある（調整値のリセット）。M2 の要件「一時停止・再開・リセット」は、新規見本でも同じ設計（ループ＋調整値の既定化）で満たすのが整合的。ループしない見本（ワンショット）を作る場合は `t` のモジュロで周期化する。

## 6. モーダル・拡大表示・キーボード操作

- 詳細ダイアログ：ネイティブ `<dialog>`。カードのリンク（`data-motion-open`）で `fetch(url?omx_partial=1)` して中身を差し替え、`history.pushState` で URL を更新（戻るで閉じる）。Esc と背景クリックで閉じ、開いた元のリンクへフォーカスを戻す。開いたら見出しへフォーカス。ダイアログ外のプレビューは一時停止。
- 拡大表示：同様の `<dialog>` に大きい iframe を生成。閉じると元のボタンへフォーカス。
- キーボード：ボタンはすべて `<button>`、iframe は `tabindex="-1"`、`aria-label` に見本名。
- 詳細ページ内の「前の見本／次の見本」リンク（ID 順）。

## 7. iframe サンドボックスとセキュリティ

- `sandbox="allow-scripts"` のみ（同一オリジン権限なし、`allow-top-navigation` なし、フォーム送信なし）。`srcdoc` 読み込みなので origin は `null`。見本の JS から親のクッキー・ストレージ・DOM へは到達できない。
- 親→子の `postMessage` は `'*'` 宛だが、子は `event.data.type` が `omx:*` のものしか処理しない。子→親は `omx:ready` のみで、親は `event.source` が登録済み iframe のものか照合する。
- パラメータは親が sanitize した上で、子も同じ文法（`--p-[a-z0-9-]{1,30}`、値は `[a-z0-9 .,()%#-]{1,80}`、`url(` 禁止）で再検査してから `#omx-params` に書く。JS 値は数値か `[#a-z0-9_-]{1,24}` のみ。
- カタログ読み込み時の検証（`omochix_learn_gallery_validate_catalog()`）：slug・ID の一意性、サイズ上限、`</script>`・`</style>` の禁止、params の文法、`var(--p-…)` の読み取り確認。スキーマ `catalog.schema.json` も同じ制約を持つ。
- 残る注意点：見本の HTML は `srcdoc` に入るため、`"` を含む属性値は `esc_attr` でエスケープされる（詳細の noscript）。外部リソース（画像・フォント・CDN）は読み込まない方針（全件インラインの CSS/HTML/Canvas のみ）。新規 30 件でも外部 URL を使わない。

## 8. スマホ・タブレット・PC 表示

- `learn-v2.css` の分岐：767px 以下（1 列、チップ横スクロール）、640px 以上（2 列）、768px 以上、1024px 以上（3 列）、1081〜1375px の調整。1.24.x の本番監査と Phase 5 の QA で 375 / 768 / 1440 を確認済み（横スクロールなし）。
- プレビューはステージ比 16:9 を保ち、`scale()` で収める。カードの iframe はポスター表示後に遅延生成。
- `(hover: none)` 端末でカードの hover 変形を無効化、`prefers-reduced-motion` で transition を無効化。

## 9. WordPress への登録・公開手順

- 見本は **WordPress の投稿ではなく、テーマ内の `catalog.json`** にある。登録＝JSON に項目を追加してテーマを配布（Runbook 8b・`docs/Beta-Deployment-Runbook.md`）。固定ページ `learn/motion` と `learn/motion/compose` は作成済み（本番）。
- 公開手順：`catalog.json` 更新 → テスト（`tests/learn-gallery/run.php`、`params-parity.js`、`tests/learn-composer/*`）→ Theme の version bump → ZIP / SHA / manifest → FTP で `assets/data/motion/catalog.json`（と `style.css`）を配置 → キャッシュ削除 → `/learn/motion/` の件数確認。DB 変更なし。
- 関連（`related.prompts / tools / learn`）は本番に存在する slug だけ効く。新規 30 件の関連先は本番の公開 slug（プロンプト 108 件、AI ツール、Learn ページ）に合わせる。

## 10. テストと依存関係（30 件追加で触る箇所）

| 依存 | 現状 | 追加時の変更 |
|---|---|---|
| `tests/learn-gallery/run.php` | 23 件・6 カテゴリ・カテゴリ順・ID 連番・ui 4 / camera 3・params 3〜5・逆引きの slug 順 | 件数・カテゴリ数・順序・逆引きリストを更新 |
| `tests/learn-gallery/params-parity.js` | 23 samples の PHP/JS 一致 | 自動で新規分も検査（固定件数なし） |
| `tests/learn-composer/run.php` | JS 見本 2 件の `omx.root` 規約、トークン 8 個 | JS 見本が増えれば件数を更新 |
| `omochix_learn_gallery_normalize_catalog()` | カテゴリ `count` 集計、ID 順 | 変更不要 |
| Learn トップ featured | `featured: true` の先頭 3 件 | 新規で featured を増やすなら 4→3 件表示のまま、順序は ID |
| Composer | 3 スロット、全見本から選べる | 変更不要（53 件のセレクト UI は M3 で確認） |
| 一覧の埋め込み JSON | 可視分の preview 断片を 1 回出力 | 53 件で約 2 倍。M3 で転送量を記録（現状 catalog 76KB） |

## 11. 既存 23 件と重複しないための境界

新規 30 件は「同じ見た目の色違い」ではなく、**実装手法（CSS プロパティ・API）か用途**が既存と異なるものに限る。重複しやすい既存：ボタン押し込み（button-press）、トースト、スケルトン、スクロールでフェードイン、パララックス、流れるグラデーション、ふわふわブロブ、ライトスイープ、パーティクル、タイプライター、カウントアップ。`MOTION-NEW-30-CATALOG.md` に各新規見本の「既存との違い」を明記した。

## 12. 発見事項（M2 前に判断がいるもの）

1. 件数：23 件が実態。30 件追加なら 53 件。50 件に合わせるなら 27 件。
2. カテゴリ：依頼の 5 分類（ボタン・CTA／テキスト／スクロール／カード・UI／背景・ローディング）は既存 6 カテゴリと一部ずれる。提案は `MOTION-NEW-30-CATALOG.md` §1。
3. 「リセット」：既存に機能はなく、ループ設計で代替している（§5）。新規も同じ設計とする。
4. ページ送りなし：53 件を 1 ページに出す。M3 で描画時間と埋め込み JSON のサイズを測り、必要なら「もっと見る」を検討（M2 の範囲外）。
