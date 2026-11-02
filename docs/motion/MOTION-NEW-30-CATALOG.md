# Motion 追加 30 件 カタログ（M2 設計）

作成日：2026年11月3日
前提：既存 23 件（ID 001〜023）に ID 024〜053 を追加。すべて 320×180 ステージ、CSS トークン利用、外部リソースなし、`prefers-reduced-motion` 対応、調整パラメータ 3〜5 個、`--p-cycle` を基準にしたループ。

## 1. カテゴリの整合（提案）

依頼の 5 分類と既存 6 カテゴリの対応。

| 依頼の分類 | 対応 | 理由 |
|---|---|---|
| 1. ボタン・CTA | **新カテゴリ `cta`「ボタン・CTA」を追加**（order 3、以降を繰り下げ） | 既存 `ui` は「操作の手応え」（スイッチ・通知・スケルトン）で、CTA 6 件を足すと 10 件に偏る。CTA は LP の最重要部位で独立した探し方がある |
| 2. テキストアニメーション | 既存 `text`「テキスト・数字」に 6 件 | 名称・説明そのまま |
| 3. スクロールアニメーション | 既存 `scroll`「スクロール演出」に 6 件 | 同上 |
| 4. カード・UIパーツ | 既存 `ui`「UIパーツの動き」に 6 件 | 同上（カードは UI パーツに含める） |
| 5. 背景・ローディング | 既存 `ambient`「背景・雰囲気」に 2 件、**新カテゴリ `loading`「ローディング・待機」を追加**（order 7、camera を 8 に）に 4 件 | ローディングは「待たせる間の動き」で背景とも UI とも用途が違う。既存の `skeleton-loading`（ui）は URL・分類とも据え置き（移動はしない） |

カテゴリ order の変更は一覧の並び順にだけ影響し、URL・slug・テスト以外の挙動は変わらない。テスト（カテゴリ順の配列、カテゴリ数 6→8、件数）は同時に更新する。

結果：entrance 4 / text 10 / cta 6 / ui 10 / scroll 10 / ambient 6 / loading 4 / camera 3 ＝ 53 件。

## 2. 追加 30 件の確定一覧

列：ID / slug / 名前（ja / en）/ カテゴリ / 難易度 / 技術 / 既存との違い（独自性）/ 調整パラメータ（3〜5）。

### ボタン・CTA（`cta`、6 件）

| ID | slug | 名前 | 難易度 | 技術 | 独自性 | パラメータ |
|---|---|---|---|---|---|---|
| 024 | magnetic-button | マグネットボタン / Magnetic button | 上級 | css+js | ポインタに吸い寄せられて少し追従する。JS で `pointermove` を計算（自動デモはステージ内で擬似ポインタを周回）。既存 button-press は押下の変形のみ | 強さ(range)、戻りの速さ(range)、色(color)、周回の長さ(cycle) |
| 025 | shine-button | シャインボタン / Shine sweep button | 初級 | css | 斜めのハイライトがボタン表面を横切る（`::after` ＋ `translateX`）。light-sweep はカード全体の光、こちらはボタン専用で文字の上を通る | 周期(cycle)、光の幅(range)、角度(range)、色(color) |
| 026 | pulse-ring | パルスリング / Pulse ring CTA | 初級 | css | ボタンの外側に波紋リングが広がって消える（`box-shadow`/`::before` の scale）。注目喚起用。既存に同種なし | 周期(cycle)、リングの数(select 1〜3)、広がり(range)、色(color) |
| 027 | arrow-nudge | 矢印ナッジ / Arrow nudge link | 初級 | css | テキストリンクの矢印が hover/周期で前後に小さく動き、下線が伸びる。CTA の「次へ」誘導 | 周期(cycle)、移動量(range)、下線の太さ(range)、色(color) |
| 028 | morph-submit | 送信ボタンの状態変化 / Morphing submit | 中級 | css+js | 「送信」→ スピナー → チェック の 3 状態を幅・角丸の補間で変形（`transition` ＋ クラス切替を JS で周期制御）。toast は通知の出現で別物 | 各状態の長さ(range)、角丸(range)、色(color)、成功色(color) |
| 029 | fab-expand | 展開する FAB / Expanding FAB | 中級 | css | 右下の丸ボタンが開くと 3 つの小ボタンが扇状に出る（`transform` の stagger）。toggle-switch とは別の「開閉」 | 周期(cycle)、広がる距離(range)、時間差(range)、色(color) |

### テキスト（`text`、6 件）

| ID | slug | 名前 | 難易度 | 技術 | 独自性 | パラメータ |
|---|---|---|---|---|---|---|
| 030 | split-letters | 一文字ずつ登場 / Split letters | 中級 | css | 文字を `span` に分け、`--i` の遅延で 1 文字ずつ上がる。typewriter（カーソルと幅）とも stagger（リスト）とも実装が違う | 周期(cycle)、文字の間隔(range)、上がる距離(range)、イージング(select) |
| 031 | gradient-text | 流れる文字グラデーション / Gradient text | 初級 | css | `background-clip: text` で文字の中だけグラデーションが流れる。gradient-shift は背景全面 | 周期(cycle)、色1(color)、色2(color)、角度(range) |
| 032 | scramble-text | 文字スクランブル / Text scramble | 上級 | css+js | 乱数の文字が次第に正しい文字へ確定していく（JS、`omx.animate` の経過時間で位置ごとに確定）。kinetic-words は単語の入れ替え | 解決時間(range)、乱数の速さ(range)、文字セット(select 英数/記号/カナ)、色(color) |
| 033 | underline-draw | 下線が描かれる / Drawing underline | 初級 | css | 見出しの下に線が左から描かれ、端で少し太くなる（`background-size` の transition、hover と周期）。marker-highlight は塗り潰し | 周期(cycle)、太さ(range)、色(color)、角(select 直線/丸) |
| 034 | marquee-ticker | 流れるティッカー / Marquee ticker | 初級 | css | 無限に横へ流れる帯（複製 2 本で継ぎ目なし、`translateX(-50%)`）。ニュースや実績ロゴ用 | 速さ(range)、向き(select)、間隔(range)、端のぼかし(select) |
| 035 | number-flip | 数字のめくり / Number flip | 中級 | css | 各桁が縦にスライドして次の数字に変わる（オドメーター）。count-up は 1 つの数値を補間する JS | 周期(cycle)、桁数(select 3〜5)、めくりの速さ(range)、色(color) |

### スクロール（`scroll`、6 件）

既存の scroll 4 件と同じく、ステージ内に「自動でスクロールするページ」を作って演出を見せる（`--p-cycle` の `@keyframes` で内側をスクロール）。

| ID | slug | 名前 | 難易度 | 技術 | 独自性 | パラメータ |
|---|---|---|---|---|---|---|
| 036 | scroll-scale-in | スクロールで拡大登場 / Scale-in on scroll | 初級 | css | 画像ブロックが画面に入ると 0.9→1 に拡大しつつ現れる。scroll-fade-in は透明度と上方向の移動 | 周期(cycle)、開始の縮小率(range)、ぼかし(select)、時間差(range) |
| 037 | scroll-snap-cards | スナップするカード列 / Scroll-snap cards | 中級 | css | `scroll-snap-type` で横に並ぶカードが 1 枚ずつ止まる（自動スクロールで demo、コピー先では指で操作できる）。parallax とは別 | 周期(cycle)、カード幅(range)、間隔(range)、色(color) |
| 038 | scroll-driven-bar | スクロール連動バー / Scroll-driven animation | 上級 | css+js | `animation-timeline: scroll()` でスクロール量に直結した棒グラフの伸び。非対応ブラウザは JS で `scrollTop` から同じ値を書く（フォールバック同梱）。scroll-progress は読了位置の 1 本線 | 周期(cycle)、棒の数(select)、色(color)、最大の高さ(range) |
| 039 | reveal-lines | 行ごとに読み進む / Reveal lines on scroll | 中級 | css | 本文の行が、スクロールで通過した行だけ濃くなる（未読は薄い）。読ませる演出。既存に同種なし | 周期(cycle)、薄さ(range)、切替の速さ(range)、強調色(color) |
| 040 | back-to-top | トップへ戻るボタン / Back-to-top | 初級 | css | 一定量スクロールすると右下に丸ボタンがスライドして現れ、押すと上へ戻る（demo は自動）。sticky-header は上部固定 | 周期(cycle)、出るまでの位置(range)、大きさ(range)、色(color) |
| 041 | section-pin | セクションの固定と切替 / Pinned section | 上級 | css | 左の見出しが `position: sticky` で固定され、右の内容だけ流れる（よくある LP の「ピン留め」）。sticky-header とは固定する対象と目的が違う | 周期(cycle)、固定幅(range)、切替の速さ(range)、色(color) |

### カード・UIパーツ（`ui`、6 件）

| ID | slug | 名前 | 難易度 | 技術 | 独自性 | パラメータ |
|---|---|---|---|---|---|---|
| 042 | tilt-card | 傾くカード / 3D tilt card | 上級 | css+js | ポインタ位置で `rotateX/Y` が変わる 3D カード（demo は擬似ポインタで周回）。magnetic-button は位置の追従、こちらは傾き | 最大角度(range)、戻り時間(range)、光沢(select)、色(color) |
| 043 | flip-card | 裏返るカード / Flip card | 初級 | css | 表裏を `rotateY(180deg)` と `backface-visibility` で入れ替える | 周期(cycle)、回転時間(range)、向き(select 横/縦)、裏面色(color) |
| 044 | accordion | アコーディオン / Accordion | 中級 | css | `grid-template-rows: 0fr → 1fr` で高さが滑らかに開閉（height:auto 問題の解法）。FAB の扇状展開とは別 | 周期(cycle)、開く速さ(range)、矢印(select)、色(color) |
| 045 | tabs-indicator | 滑るタブ下線 / Sliding tab indicator | 初級 | css | タブの切替に合わせて下線が横に滑る（`transform` のみ）。underline-draw は 1 本の線が描かれる演出 | 周期(cycle)、滑る速さ(range)、太さ(range)、色(color) |
| 046 | drawer-menu | スライドメニュー / Slide-in drawer | 中級 | css | ハンバーガーから横のドロワーが出て背景が暗くなる。項目は stagger で登場 | 周期(cycle)、幅(range)、暗さ(range)、時間差(range) |
| 047 | check-draw | チェックが描かれる / Drawing checkmark | 初級 | css | SVG の `stroke-dasharray/offset` で丸とチェックが描かれる（送信完了・成功表示）。morph-submit の最終状態だけを独立させた汎用部品 | 周期(cycle)、描画時間(range)、太さ(range)、色(color) |

### 背景（`ambient`、2 件）

| ID | slug | 名前 | 難易度 | 技術 | 独自性 | パラメータ |
|---|---|---|---|---|---|---|
| 048 | dot-grid-wave | ドットの波 / Dot grid wave | 中級 | css | 格子状のドットが `--i`/`--j` の遅延で波打つ（CSS のみ、要素は PHP ではなく HTML に展開）。particles は Canvas の浮遊 | 周期(cycle)、振幅(range)、ドット間隔(range)、色(color) |
| 049 | ripple-rings | 広がる波紋 / Ripple rings | 初級 | css | 中心から同心円が等間隔に広がって薄れる。pulse-ring（ボタン周囲）と違い背景全面 | 周期(cycle)、リングの数(select)、線の太さ(range)、色(color) |

### ローディング（`loading`、4 件）

| ID | slug | 名前 | 難易度 | 技術 | 独自性 | パラメータ |
|---|---|---|---|---|---|---|
| 050 | spinner-ring | リングスピナー / Ring spinner | 初級 | css | `conic-gradient` と `mask` で欠けた円が回る。最小の読み込み表示 | 回転速度(range)、太さ(range)、欠けの大きさ(range)、色(color) |
| 051 | dots-bounce | 跳ねる 3 点 / Bouncing dots | 初級 | css | 3 つの点が時間差で跳ねる（チャットの「入力中」）。stagger（登場の時間差）とは用途が違う | 周期(cycle)、高さ(range)、点の大きさ(range)、色(color) |
| 052 | progress-stripes | 流れる縞のバー / Striped progress | 初級 | css | 不確定プログレスバーの縞が流れる（`repeating-linear-gradient` ＋ `background-position`） | 速さ(range)、縞の幅(range)、色(color)、角丸(range) |
| 053 | liquid-fill | 液体が満ちる / Liquid fill | 中級 | css | 円の中を波打つ液面が上がっていく（`border-radius` の回転で波を作る）。進捗％の演出 | 周期(cycle)、波の速さ(range)、満ちる割合(range)、色(color) |

難易度：初級 14・中級 11・上級 5。JS あり 5 件（024 / 028 / 032 / 038 / 042）。

## 3. 各教材に実装する内容（全件共通）

| 要件 | 実装 |
|---|---|
| 動作するライブプレビュー | `preview.html/css/js`。320×180 ステージ、トークンで配色、`--p-cycle` でループ |
| 完全なサンプルコード | 既存と同じく `omochix_learn_gallery_code_text()` が preview から自己完結 HTML を生成。JS 見本は shim を同梱 |
| コピー機能 | 既存のコピーボタン（変更不要） |
| 初心者向けの解説 | `summary`（80 字）＋ `description`（300 字、「何が起きているか」）＋ `prompt.use`（使いどころ） |
| 実装手順 | `prompt.spec`（1〜10 行、「動きの仕様」）を手順として書く。詳細ページの「動きの仕様」欄に出る |
| カスタマイズ可能な値 | `playground.params` 3〜5 個（`label`・`prompt` 文付き）。詳細の「調整する」パネルとコピーされる `:root{--p-…}` に反映 |
| スマホ対応 | ステージは `scale()` で収まる。hover 依存の見本（024 / 027 / 033 / 042 / 045）は周期アニメで自動デモし、`@media (hover:hover)` の時だけ hover を追加 |
| prefers-reduced-motion | ランタイム共通（初期フレームで停止）。JS 見本は `omx.reduced` で最終状態を描く。CSS 見本は追加指定不要 |
| スクリプト実行の安全性 | JS 5 件は `omx.root` 内だけを参照し、`document`・`window.parent`・`fetch`・`localStorage`・外部 URL を使わない。`</script>` を含まない。テスト（composer run.php の `omx.root` 規約）に 5 件を追加 |

## 4. 重複チェックの基準

- 同じ CSS プロパティ・同じ用途の組み合わせが既存にある案は採らない（例：「ボタンの波紋」は button-press のリップルと重なるため、外側リングの pulse-ring に変更。「ネオン文字」は gradient-text と色違いになるため不採用）。
- 1 件ごとに「既存との違い」を `description` にも 1 文入れる（例：number-flip「カウントアップが数を数えるのに対し、桁ごとにめくれる」）。

## 5. 関連（related）の方針

- `related.learn`：Web 系は `learn/development`、`learn/tutorials/ai-web-service-launch`。
- `related.prompts`：本番に存在する slug のみ（`development-web-performance-investigation`、`development-prototype-brief-for-ai-builders` など）。M2 実装時に本番の公開 slug を読み取りで照合する。
- `related.tools`：`cursor` / `claude-code` / `claude` 等、本番の AI ツール slug に合わせる。
- `featured`：新規では付けない（Learn トップの 3 枠は現行のまま）。

## 6. 50 件ちょうどにする場合（代替案）

30 件から 3 件を外す候補：041 section-pin（上級・実装が重い）、053 liquid-fill（ローディングの中で最も装飾的）、049 ripple-rings（pulse-ring と視覚が近い）。この場合 scroll 5 / ambient 1 / loading 3 になる。
