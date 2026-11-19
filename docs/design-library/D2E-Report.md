# OmochiX Design Library — D2-E 実装報告（12 作品目 Interactive Storytelling）

- ブランチ：`feature/design-library-d1`
- 基準：D2-D の commit `da7822d`。今回の変更は**未コミット**（commit・push・PR・merge はしていない）
- 実施日：2026-11-18
- 環境：使い捨てのローカル WordPress（`http://127.0.0.1:8961`）。本番・DB・サーバー・キャッシュは操作していない
- 変更していないもの：CSP・sandbox・公開の判定・ランタイム・Core プラグイン、既存 11 作品のデモ、D0 の未追跡文書 2 件、既存の QA ファイル 129 件と `perf-results.json`、別の worktree
- 外部ライブラリ・外部通信・WebGL・依存パッケージは追加していない

## 1. 作品

**Interactive Storytelling**（`dl-web-0009`、`interactive-story`、`verified-local`）

- 架空の手漉き和紙の工房「紙季廊（SHIKIRO）」の一年を、4 章 13 場面の読み物にした。**工房名は仮採用で、商標は未確認。**
- 段落が画面の中ほど（上から 50〜60% の帯）に来ると、横（PC）・上（スマホ）に固定した挿絵がその場面に変わる。遠景の山・太陽・下の線（読んだ場面の数）も場面に合わせて動く。
- 各段落に「職人の目（わたし）」と「紙の目（ぼく）」の 2 つの語り。ラジオボタンで切り替え、切り替えても読んでいる段落の位置は保たれる（4px 以内を試験で確認）。
- 上部に章名と読書位置の棒（JavaScript）、目次のダイアログ、各章の終わりに「次の章へ」、結びに一年の流れ。
- 挿絵は 13 個の SVG `symbol` に 1 度だけ定義し、固定の挿絵と、静的な表示の挿絵の両方を `use` で描く（画像ファイル・WebGL・Canvas は使わない）。
- 紙すきの工程は一般的な流れを簡単にしたもので、工房・人物・地名は架空と明記した。
- 配色：和紙 `#f4efe4`・墨 `#22201c`・藍 `#1f3a5f`・柿（文字用）`#a8432f`。章ごとに本文の地と挿絵の地の色を変える。紙の世界の物語なので、ライトのみ（サイトのダーク表示でもデモは明るいまま）。

### 実装の順番（指示どおり）

1. JavaScript 無効でも各段落と完成した挿絵を読める構成（段落ごとに `svg > use`、職人の語り、目次はページ内リンク）。語りの切り替えは CSS の `:has` だけで動くので、JavaScript 無効でも使える。
2. 挿絵の固定とスクロール連動（IntersectionObserver。常に回る rAF・タイマーは無い）。
3. 語りの切り替え（位置の補正と読み上げ）、目次（`role="dialog"`・`aria-modal`、Tab をダイアログ内にとどめ、Esc・閉じる・外側で閉じて「目次」ボタンへ戻す、章へ移ったら見出しにフォーカス）、読書位置の棒（スクロール中だけ rAF 1 本）。
4. 一時停止・非表示：ランタイムの一時停止は CSS の transition を止めないため、一時停止・非表示の間は場面を切り替えず、最後の段落を覚えておいて再開時に一度で反映する。読書位置の棒もその間は描かない。
5. reduced-motion・JavaScript 無効・高さの低い横向き画面：挿絵の固定をやめ、段落ごとに挿絵を並べる。Save-Data：同じ構成で、切り替えを即時にする（transition なし）。

## 2. 検証

### 2.1 作品の試験

| 試験 | 結果 | 内容 |
| --- | --- | --- |
| new-works（Chromium・WebKit） | 32 / 32 | 3 幅で CSP 違反 0・外部通信 0・ページのエラー 0・横スクロールなし、opaque origin・Cookie 不可、小さい文字のコントラスト 4.5:1 以上（実測）、axe、reduced-motion、JavaScript 無効、埋め込みの一時停止・再開・再読み込み、非表示中のフレーム 0、フォーカスの輪 |
| d2e-story（Chromium・WebKit・Firefox、新規） | **初回 80 / 81（FAIL 1）→ 修正後 81 / 81** | 下の §2.2・§2.3 |
| axe（WCAG 2.2 A/AA、1440・834・390） | serious・critical 0 | — |
| isolation（Chromium・WebKit） | 106 / 106 | 12 作品目を含む |
| firefox | 32 / 32 | 12 作品目を含む |

### 2.2 d2e-story の確認内容

- 直接表示（1440・834・390）：場面 1・5・8・13 で、表示される挿絵は 1 つだけで段落と一致、段落の印・章の色・章名も一致。スクロール中も挿絵が固定。読書位置が最後に 98% 以上。横スクロール・CSP 違反・エラー・外部通信なし。
- 語りの切り替え（1440・390）：紙の語りに変わり、段落の位置のずれ 4px 以内、「紙の目で読んでいます」を読み上げ。
- 目次（1440）：現在の章（第 2 章）にフォーカス、Tab を 7 回押してもダイアログ内、Esc で閉じて「目次」ボタンへ戻る。第 3 章を選ぶと見出しにフォーカスが移り画面内。「次の章へ」（Enter）で次の章の見出しへ。見学のボタンは状態の表示だけ。
- 非表示：非表示中にスクロールしても場面は変わらず（3 のまま）、フレームは 0。戻ると場面 9 になる。
- 埋め込み（詳細ページ、1440・834・390）：**フレームの上でマウスホイールを回すと、フレーム内がスクロールして場面が進む**。一時停止中はフレームをスクロールしても場面が変わらず、再生で現在の段落（12）になる。フレーム内の CSP 違反 0。
- reduced-motion・JavaScript 無効・844×390 の横向き：固定の挿絵なし、本文中に挿絵 13 枚、すべての段落が読める、目次のリンク 4 件、横スクロールなし。reduced-motion ではアニメーション 0。JavaScript 無効でも語りの切り替えが動き、「目次」ボタンは出ない。
- Save-Data：場面は変わり、transition は 0 秒。

### 2.3 失敗の記録（初回の FAIL を残す）

| 試験 | 初回 | 原因 | 対応 | 再実行 |
| --- | --- | --- | --- | --- |
| d2e-story | FAIL 1 件：WebKit で、目次を Esc で閉じたときに「目次」ボタンへフォーカスが戻らなかった | **特定済み（作品の不具合）**：WebKit はクリックしたボタンにフォーカスを移さないため、開く前の `activeElement` が body だった | 戻り先を常に「目次」ボタンにした（目次を開けるのはこのボタンだけ） | 81 / 81 |
| スクリーンショットの確認（試験では未検出） | 目次を閉じると、Chromium・WebKit でページが 454px 上に戻り、読んでいた章からずれた | **特定済み（作品の不具合）**：`html` の `scroll-padding-top` のため、固定ヘッダーの「目次」ボタンへフォーカスを戻すとき、ブラウザーがボタンを見せようとしてスクロールした | `scroll-padding-top` をやめ、章などの移動先に `scroll-margin-top` を付けた。フォーカスは `preventScroll` で戻す。d2e-story に「目次を開いて閉じてもページが動かない」を追加 | 3 ブラウザーとも移動 0px、d2e-story 81 / 81 |

### 2.4 性能（Chromium、perf.js）

| 項目 | 値 | 計画の目安 |
| --- | --- | --- |
| 転送量（gzip） | 13.6 KB | 18 KB 以下 |
| JavaScript（gzip） | 3.7 KB | 4 KB 以下 |
| 長いタスク | 0 | 0 |
| ヒープ | 1 MB | — |
| fps（p5） | 108 | 60 以上 |
| LCP（スマホ、CPU 4 倍・150ms・1.6Mbps） | 2,260 ms | 2,500 ms 以下 |
| CLS | 0 | 0 |

ポスター AVIF 9KB、ループ WebM 228KB・MP4 327KB（上限 600KB）。Web 一覧の初回転送量は 517.8KB（ループ 2 本を含む、上限 1.5MB）。

## 3. 全体の試験（時間の上限付き）

`run-all.sh` の各試験に時間の上限を付けた（`DL_SUITE_LIMIT`、既定 1,500 秒）。上限を超えた試験は止めて **FAIL（timeout）** と記録する。

| 試験 | 1 回目（03:17〜03:38） | 2 回目（目次の修正後、03:40〜04:02） |
| --- | --- | --- |
| catalog | 127 / 127 | 127 / 127 |
| unit | 91 / 91 | 91 / 91 |
| http | 169 / 169 | 169 / 169 |
| isolation | 106 / 106 | 106 / 106 |
| firefox | 32 / 32 | 32 / 32 |
| ui | 222 / 222 | 222 / 222 |
| layout | 116 / 116 | 116 / 116 |
| d2d-video | 56 / 56 | 56 / 56 |
| d2d-new（3 作品） | 96 / 96 | 96 / 96 |
| d2d-web | 96 / 96 | 96 / 96 |
| d2e-story | 81 / 81 | 81 / 81 |
| preview | 102 / 102 | 102 / 102 |
| perf | 67 / 67 | 67 / 67 |
| heal | 25 / 25 | 25 / 25 |
| 回帰（86 URL） | 差分 0 | 差分 0 |

- どちらの回も、停止・時間切れは 0 件だった（所要は約 21 分）。
- **D2-D で起きた 4 件の停止（ui・d2d-video・preview・perf）の原因は、引き続き未特定。** 今回起きなかったことは、解決したことを意味しない。D2-D の時点で、Node の時間の上限（`alarm`）を超えても停止した Node が残っていた例が 1 件あった。今回、上限で Node が止まること（終了コード 142）は確認した。
- perf の Interactive Storytelling は 2 回目も転送 13.6KB・JS 3.7KB・fps p5 111・LCP 2,260ms・CLS 0。

## 4. 12 作品

| # | ID | 作品 | 種類 | 検証 |
| --- | --- | --- | --- | --- |
| 1 | dl-web-0001 | Liquid Glass | Web | verified-local |
| 2 | dl-web-0002 | Minimal Editorial | Web | verified-local |
| 3 | dl-web-0003 | 3D Interactive | Web（WebGL） | verified-local |
| 4 | dl-web-0004 | Bento SaaS | Web | verified-local |
| 5 | dl-web-0005 | Dark Luxury | Web | verified-local |
| 6 | dl-web-0006 | Aurora Gradient | Web（Canvas） | verified-local |
| 7 | dl-web-0007 | AI Workspace UI | Web | verified-local |
| 8 | dl-web-0008 | Micro Interaction Collection | Web | verified-local |
| 9 | dl-web-0009 | **Interactive Storytelling（今回）** | Web | verified-local |
| 10 | dl-video-0001 | Logo Reveal Short | 動画 9:16 | verified-local |
| 11 | dl-video-0002 | Data Chart Explainer | 動画 16:9 | verified-local |
| 12 | dl-video-0003 | Brand Typography Motion | 動画 1:1 | verified-local |

`verified-production` の作品は無い。

## 5. 変更ファイル（未コミット）

| ファイル | 内容 |
| --- | --- |
| `wp-theme/omochix-theme/assets/design/demos/interactive-story/{demo.php,demo.css,demo.js}`（新規） | 作品 |
| `wp-theme/omochix-theme/assets/design/posters/interactive-story.{avif,webp}`、`loops/interactive-story.{webm,mp4}`（新規） | ポスター・ループ |
| `wp-theme/omochix-theme/assets/data/design/catalog.json` | 1 件追加（`verified-local`、perf の値）。既存 11 作品は変更なし |
| `tools/design-library/capture-media.js` | 撮り方を 1 件追加 |
| `tests/design-library/browser/d2e-story.js`（新規） | 専用の試験 |
| `tests/design-library/browser/{lib,new-works,ui}.js`、`catalog-check.js`、`run.php` | 作品一覧・件数・JS 無効の確認に 1 件追加 |
| `tests/design-library/run-all.sh` | 各試験の時間の上限、`d2e-story` を追加 |
| `docs/design-library/D2E-Report.md`（新規） | この報告 |
| `docs/design-library/D2E-Storytelling-Plan.md`（新規、前回作成） | 実装計画 |

スクリーンショットは `docs/design-library/qa/d2e/`（10 枚、これまでと同じく commit の対象外）。

## 6. 未解決事項

- **【未解決】商標**：工房名「紙季廊（SHIKIRO）」は仮採用で、一般的な Web 検索だけ。商標の登録は確認していない。D2-D の YOMIBA・NOKURA・HAKARI も同じ。
- **【未解決】フォントのライセンス**：動画に描き出したシステムフォントの利用条件（D2-D から継続。今回の作品は Web で、閲覧者の端末のフォントで表示するだけなので対象外）。
- **【未解決】試験の停止の原因**：D2-D の 4 件の原因は未特定（§3）。
- **紙すきの工程の記述**：一般的な流れを簡単にしたもので、専門家の確認は受けていない。
- **ダーク表示**：この作品はライトのみ（意図した設計）。
- **実機**：iOS Safari・Android Chrome の実機での、固定の挿絵とスクロールの動き（アドレスバーの伸び縮みによる高さの変化を含む）は未確認。
- **本番**：LiteSpeed・キャッシュ・Slim SEO との連携・本番の Learn ページは未確認（D2-B から継続）。
