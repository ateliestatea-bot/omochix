# SVG Motion Studio CI-1 / S4-0 実装結果

検証日：2026年11月3日
ブランチ：`feature/svg-motion-s1b`。S3 は `23bfd57`。CI-1 と S4-0 は未コミット（コミットは別途承認）。
本番 WordPress、本番 DB、FTP、Core、AI Tools、既存 53 教材、Theme 1.27.0 の配布物は変更していない。push、PR、merge、deploy なし。S4-1 以降の編集 UI と WP-1 のショートコードは未着手。

表記：**[実測]** はこの作業で測った事実、**[未検証]** は確かめていないこと。

## A. CI-1 実装結果

| 項目 | 実装 | ファイル |
|---|---|---|
| エンジンごとのジョブ | `strategy.matrix.engine`、`fail-fast: false`。`SVGM_ENGINE` で 1 エンジンだけ実行 | `.github/workflows/svg-motion.yml`、`tests/svg-motion/run.js` |
| 並列実行 | 3 ジョブ並列。PR では新しい push が前の実行を取り消し、release と定期実行は取り消さない | 同上 |
| PR 用と完全版 | `SVGM_PROFILE=fast`（PR）/ `full`（`release/**` への push、毎週日曜、手動。既定は `full`） | `run.js`、`export-tests.js` |
| WebKit の撮影の時間切れ | `locator.screenshot()`（要素が止まるまで待つ）を `page.screenshot({clip})` に替え、撮影前に `bringToFront` と 2 フレーム待ち。10 秒の制限と 1 回の再試行、回数を結果に記録。reduced-motion の確認は 1 ページずつ開いて閉じる | `export-tests.js` |
| 静止時刻を指定した画像比較 | インライン系（inline、external、script）は `document.getAnimations()` で時刻を設定して撮る。実時間が必要な画像系（image、object、background）は専用ウィンドウで実時間 | `export-tests.js` |
| 失敗時の記録 | 全コンテキストで trace を記録し、失敗があったものだけ `results/trace-*.zip` に残す。失敗した比較の画像を `results/failures/<engine>/` に保存。`results/` を `upload-artifact` で 7 日保持（trace は失敗時のみ） | `run.js`、`export-tests.js`、workflow |
| Linux 依存の除去 | サーバーは固定の待ち時間ではなくポートが開くまで待つ。テストに macOS 固有のパスなし（確認済み）。アーティファクト名をエンジンごとに分けた。ハーネスのポートは `SVGM_PORT` で変更可（既定 8770、取れなければ番号を出して終了） | `run.js`、`server.js`、workflow |
| 実時間確認の撮り直し | 並列で「動かない」と出た画像系ページは単独で 1 回撮り直し、回数と最初の撮影までの時間を結果に記録（B-4） | `export-tests.js` |
| 安全性と回帰の維持 | 攻撃 69 種、爆弾 11 種、アップロード経路、書き出した攻撃ロゴ 66 種、陽性対照、53 教材の回帰は両プロファイルで全エンジン。`fast` で減るのは「代表ロゴ以外の画像系の実時間確認」だけ | `export-tests.js` |
| 対照の追加 | 時刻設定の方式が「動く動画を動くと見る」「静止ページを静止と見る」こと（陽性・陰性）。書き出しの検査を意図的に失敗させ、ファイルもコピーも提供されず理由が出ること | `export-tests.js` |

### 時間と安定性の内訳

→ B 章。

## B. CI 実行時間・安定性レポート [実測、手元の Mac]

（この章は C 以降を先に書き、最後の連続実行の結果で更新する。）

### B-1. 変更前（S3、`23bfd57`）

- 3 エンジン順番で 42 分 4 秒（1 エンジン約 14 分、export 約 10.5 分）。
- WebKit の export で撮影の時間切れが 5 回中 1 回。

### B-2. 変更後の 1 回目（fast）

| エンジン | 合計 | security | presets | upload | page | export | compose | regression |
|---|---|---|---|---|---|---|---|---|
| Chromium | 6 分 37 秒 | 18s | 50s | 49s | 66s | 205s | 4s | 4s |
| Firefox（修正前） | 13 分 23 秒、失敗 8 件 | 20s | 44s | 45s | 57s | 625s | 4s | 7s |
| Firefox（修正後、export のみ） | export 7 分 0 秒、合格 | | | | | 420s | | |
| WebKit | 9 分 33 秒 | 19s | 40s | 50s | 64s | 390s | 3s | 5s |

Firefox の失敗 8 件はすべて画像系の実時間確認で「動かない」。並列 6 で開いた背景タブの描画を Firefox が止めるため。実時間のページを作業者ごとの専用ウィンドウ（コンテキスト）で開くようにして解消した。

### B-3. 連続実行 1 回目（修正前）と見つかった不安定要因

| 実行 | Chromium | Firefox | WebKit |
|---|---|---|---|
| full 1 | 合格 9 分 47 秒（export 392s） | 合格 10 分 19 秒（export 441s） | **失敗 1 件** 9 分 46 秒（export 404s） |
| fast 2 | 合格 6 分 58 秒 | 合格 9 分 49 秒 | **失敗 6 件** 11 分 7 秒 |
| fast 3 | 合格 6 分 59 秒 | **失敗 13 件** 12 分 42 秒 | 合格 10 分 45 秒 |
| fast 4 | — | **失敗 8 件** 13 分 11 秒 | — |

失敗はすべて画像系の実時間確認（`<img>`、`<object>`、背景画像）の「動かない」で、Chromium では一度も出なかった。保存された失敗画像を調べると、7 枚の撮影がすべて同一で、しかも終了姿勢と同じだった。つまり「動いていない」のではなく、**最初の 1 枚を撮る前にアニメーションが終わっていた**。画像系はファイルの読み込みと同時に時計が始まり、6 つの窓で同時に撮影していると、読み込みから最初の撮影までに短いプリセット（pop、wipe、stagger-pop、rise-mask、focus、assemble）の全長を超えることがある。撮影の `shotRetries` は全実行で 0 で、WebKit の時間切れは再発していない。

対策（`export-tests.js`）：画像系の形では、最初の撮影の直前に同じファイルを新しいクエリ（`?omx=…`）で読み込み直し、そこから時計を始める。同じファイルを同じ形で読む点は変わらない。あわせて「動いた」の判定を「最初の 1 枚との差」だけでなく「隣り合う 2 枚の差」でも取るようにした。

このほか、手元の Mac で 2 回目の連続実行を始めた際、macOS の `sharingd` が 8770 番を使っていて全実行が起動に失敗した。ハーネスのポートを `SVGM_PORT` で変えられるようにし（既定は 8770、カナリアは 8771 固定）、取れないときはポート番号を出して終了する（`server.js`、`run.js`、`export-tests.js`）。共有ランナーでの衝突にも効く。

### B-4. 連続実行 2 回目（読み込み直しのみ）と残った不安定要因

| 実行 | Firefox | WebKit |
|---|---|---|
| fast 5 / 4 | **失敗 2 件** 15 分 19 秒 | **失敗 1 件** 9 分 42 秒 |
| full 2 | — | **失敗 6 件** 13 分 9 秒 |
| fast 6 / 5 | 合格 8 分 27 秒 | 合格 9 分 13 秒 |
| fast 7 | 合格 9 分 14 秒 | — |

減ったが残った。失敗画像は同じ（7 枚同一、終了姿勢）。読み込み直しから最初の撮影までに、6 つの窓が `bringToFront` を奪い合い、前面でない窓の `requestAnimationFrame` 待ちが止まるため、まだ 1 秒を超えることがあった。

追加対策（`export-tests.js`）：

1. 専用窓のページでは前面化と 2 フレーム待ちを省く。ただし **Firefox だけは残す**（Firefox は前面でない窓を約 1 秒に 1 回しか描かず、省くと export が 330〜520 秒から 800 秒に伸びた。前面化だけ残して待ちを省いても 817 秒。いずれも export のみの計測）。WebKit は省くと export が 370〜400 秒から 290 秒に縮んだ。
2. 読み込み直しから最初の撮影までの時間（gap）を各ページで記録し、最大値を結果に出す（`export.realTime.gapMax`）。
3. 並列で「動かない」と出た実時間ページは、キューが空いてから **1 ページずつ単独で撮り直す**。撮り直しの一覧（gap の前後と動き）を `export.realTime.retried` に残す。単独でも動かなければ失敗のまま。CSP 版も同じ。

安全性と回帰の確認はどれも減らしていない。撮り直しは「動くかどうか」の判定条件を変えず、機械の混み具合に左右される撮影時刻だけを変える。

### B-5. 連続実行 3 回目（最終）[実測]

手元の Mac は他の作業と並行で、load average 14〜32 の状態だった。

| 実行 | 結果 | 合計 | export | 撮り直し（単独で動いた） | gap 最大 |
|---|---|---|---|---|---|
| fast Firefox 8 | 合格 | 14 分 4 秒 | 535s | 8 | 1.3s |
| fast WebKit 6 | 合格 | 9 分 3 秒 | 359s | 2 | 1.9s |
| full WebKit 3 | 合格 | 11 分 32 秒 | 495s | 1 | 0.7s |
| fast Firefox 9 | **失敗 2 件** | 23 分 6 秒 | 1017s | 18（うち 1 件は単独でも動かず） | 6.2s |
| fast WebKit 7 | 合格 | 9 分 1 秒 | 338s | 0 | 0.2s |
| full Firefox 2 | 合格 | 15 分 45 秒 | 756s | 0 | 2.0s |
| fast Firefox 10 | 合格 | 9 分 46 秒 | 400s | 1 | 0.8s |
| fast Chromium 5 | 合格 | 6 分 43 秒 | 208s | 0 | 0.05s |
| fast Firefox 11 | 合格 | 9 分 29 秒 | 382s | 5 | 1.9s |
| fast Firefox 12（load average 40 超） | 合格 | 18 分 31 秒 | 877s | 1 | 0.6s |

Firefox 11・12 は運営者の指示で最後の検証とし、追加の再実行はしていない。修正後の Firefox は fast 5 回中 4 回合格（失敗 1 回は下記）、full 1 回合格。

Firefox 9 は機械全体が詰まった実行で、upload が通常 45 秒のところ 147 秒、撮影の時間切れ 2 回、単独の撮り直しでも 1 枚に 14.5 秒かかった。失敗 2 件は「実時間の対照（script 形）が動かない」と「CSP 版 `<img>` が単独でも動かない」で、どちらも撮影が止まっていた間にアニメーションが終わったもの。WebKit は修正後 5 回連続合格（export のみの確認を含む）、Chromium は全実行合格。

以前 WebKit で起きていた撮影の時間切れ（`locator.screenshot`）は、全実行で `shotRetries` 0（Firefox 9 の 2 回を除く）。

## C. S4-0 実装結果

| 項目 | 実装 | ファイル |
|---|---|---|
| Transform 合成 | 個別プロパティ `translate`、`rotate`、`scale` を `PROPERTIES` に追加。編集トラックは同じ要素に別プロパティとして当たる | `compile.js` |
| 同一プロパティの重複 | レイヤー内（`EDIT_CONFLICT prop:`）、同じ対象の 2 レイヤー（`EDIT_CONFLICT target:`）、プリセットとの競合（`compileEdit` で `EDIT_CONFLICT part:n:prop`）を拒否 | `compile.js` |
| root とパーツの座標系 | パーツは既存の `fill-box`。編集があるときは root に `transform-box:view-box; transform-origin: Xpx Ypx` を書く（`plan.origin`）。JS ランナーと sandbox 枠のランタイムも同じ原点を使う | `compile.js`、`export.js`、`frame.js` |
| 原点の計測 | `OmxSvgFrame.measure()`：ノンス CSP、`allow-scripts` のみの sandbox 枠で `[data-omx-root]` の `getBBox()` を取り、数値 4 つだけを `postMessage` で返す。3 秒で諦めて `null` | `frame.js` |
| **CSS 生成の不具合修正** | 同じ要素に複数トラックがあると、後の `animation` 規則が前を置き換えていた（S4 で初めて起きる）。要素ごとに 1 つの `animation` 規則にまとめた。既存プリセットは 1 要素 1 トラックなので出力は変わらない（単体テストで確認） | `compile.js` |
| schema 2 | `validateEdit`（丸めずに拒否）、`compileEdit`（統一プランへ）、`emptyEdit`。`parseSettings` は 1 と 2 を読む。書き出しは編集があれば 2、なければ 1 | `compile.js`、`export.js` |
| 保存 | IndexedDB `SCHEMA` 2。1 の記録は `edit: null` として読み、不明な番号は削除 | `store.js` |
| スタジオ | `settings.edit`。読み込み（保存、JSON）時にロゴのパーツで検査し、拒否したら破棄して状態文に表示。ロゴの差し替えで破棄して表示（確認ダイアログは S4-1）。`data-svgm-edit`、`data-svgm-origin` 属性。書き出し失敗時はダウンロードボタンを消しコピーを無効化 | `studio.js` |
| 文言 | `EDIT_INVALID`、`EDIT_LIMIT`、`EDIT_CONFLICT`、`editDiscarded` | `inc/learn-svg-motion.php` |
| CSS の上限 | 262,144 バイト（`EDIT_LIMITS.css`）。書き出しで超えると `EXPORT_FAILED css-size` | `compile.js`、`export.js` |

**CSS 上限を 128KB から 256KB にした理由 [実測]**：編集そのものが足す CSS は最大で約 46KB（120 トラック）。しかし `logo-stagger-pop` のようにパーツごとに動くプリセットは、パーツ予算 200 のロゴで単独で約 86KB になる。128KB だと「既存プリセット + 編集」が書き出せない場合があるため、編集 64KB + プリセット分の余裕で 256KB にした。

## D. schema 2 確定仕様

`docs/motion/SVG-MOTION-SCHEMA-2.md` を参照。

## E. 3 エンジンの画素比較結果 [実測、`tests/svg-motion/compose-tests.js`]

参照画像は「1 つの `transform` にすべてを合成した静止画」。製品の `compileEdit` → `planToCss` が書いた CSS を `<img>` で描き、画素差（RGB 差 60 超の画素数）を数えた。

| 確認 | Chromium | Firefox | WebKit |
|---|---|---|---|
| 1 パーツに 5 本同時（translate、rotate、scale、opacity、プリセット由来の `transform`）t=0.25 | 0 | 0 | 0 |
| 同 t=0.5 | 0 | 0 | 0 |
| 同 t=0.75 | 0 | 0 | 0 |
| root の回転 + パーツの縮小、原点固定（100,100） | 0 | 0 | 0 |
| 同、原点を故意にずらした対照（検出できること） | 3,757 | 3,758 | 3,728 |
| `logo-pop` + 編集（part 1 の移動と回転）t=300ms：インラインと `<img>` | 0 | 0 | 0 |
| 同 t=700ms | 0 | 0 | 0 |

書き出しの行列（`export-tests.js`）でも、編集つきの 3 件（pop、breathe、wipe + 編集）を含む 136 通りで、凍結画像とプレビューの差 0、終了状態の差 0、外部通信 0 を 3 エンジンで確認した。

## F. 性能検証 [実測、手元の Mac]

パーツ 200 のロゴに、40 レイヤー × 3 トラック（121 トラック、CSS 28.5KB）を載せた。

| 項目 | Chromium | Firefox | WebKit | 基準 |
|---|---|---|---|---|
| プレビュー 1 枚（CSS 生成 → Blob → `decode()`）の中央値 / 最大（15 回） | 2.3 / 5.2 ms | 2.0 / 3.0 ms | 4.0 / 5.0 ms | 中央値 100ms 以下（No-Go 条件） |
| 121 本を同時に再生中のページの描画回数（1 秒） | 62（画面の上限） | 122 | 62（画面の上限） | 記録のみ |
| 同時に動いているアニメーション数 | 121 | 121 | 121 | 120 以上 |

No-Go 条件（100ms 超）には遠い。GitHub の Linux ランナーでの値は [未検証]。

## G. 回帰テスト結果 [実測]

| 項目 | 結果 |
|---|---|
| 既存 Learn Motion 53 教材（スタブ、3 エンジン、`regression`） | 全件合格（B 章の各実行） |
| 既存 11 スイート | 全件合格（article-v2 197、editorial-home 26 と配色、learn-composer 46 と 78、learn-gallery 134、learn-practice 96、AI Tools の CSV、インライン、クイック編集 33） |
| SVG Motion 契約 / 単体 | 44 / 168 合格（S4-0 で 15 件追加：編集の検査、競合、上限、原点、CSS の 1 規則化、schema 2 の読み取り） |
| SVG 攻撃・外部通信（security、upload、書き出しの攻撃ロゴ） | 3 エンジン全件合格（B 章） |
| 正常 SVG の描画（fidelity、presets、書き出し 136 通り） | 3 エンジン全件合格 |
| 保存・復元（S2 のテスト、schema 2 の保存と再読み込み） | 3 エンジン合格 |
| プレビューと書き出しの一致 | 差 0（編集つき 3 件を含む） |
| Theme 1.27.0 の ZIP | SHA-256 `574e19a5…` 一致 |
| `catalog.json`（53 教材） | `f7140a9e…` 一致 |
| Core、既存 Motion データ | `94152a7` からの差分 0 |
| `git diff --check` | 問題なし |

## H. 未検証事項・リスク

| 項目 | 状態 |
|---|---|
| GitHub Actions での実行 | 未実行（push の承認が必要）。固定した `upload-artifact` のハッシュ（v4.6.2）、Node 24.14.1、PHP 8.3 の存在は初回実行で判明 |
| Linux ランナーの時間 | 手元の Mac の値のみ。ランナーが遅い場合に備え `timeout-minutes: 25` |
| Linux のフォント、クリップボード、WebKit の描画差 | 未検証（S4 レビュー E-2 のとおり） |
| 原点の計測が使えない環境 | `measure()` が `null` を返すと root は `fill-box` のまま。Firefox と Chromium で結果が違いうる。状態文には出さない（S4-1 で検討） |
| 編集 UI がない | S4-0 の編集データは JSON の読み込みでだけ入る。実際の編集操作の使い勝手は未検証 |
| ロゴ差し替え時の確認ダイアログ | 破棄と表示のみ。確認は S4-1 |
| 実機 Safari / iOS / Android | 未検証（S3 から継続） |
| `playwright install --with-deps` の `sudo` | ランナーで可のはずだが未確認 |
| Firefox の実時間確認 | 機械が詰まると単独の撮り直しでも間に合わないことがある（B-5 の Firefox 9）。ランナーでの頻度は未検証。対処の候補は Firefox だけ `SVGM_PARALLEL` を下げること |
| 手元の環境固有 | macOS の `sharingd` が 8770 番を使うことがあり、手元では `SVGM_PORT=8790` で実行した。ランナーには関係ない見込み |

## I. S4-1 開始 Go / No-Go

| 条件 | 結果 |
|---|---|
| テストを削除・無効化していない | はい。攻撃・アップロード・回帰・対照はすべて両プロファイルで残っている。減らしたのは fast の「代表ロゴ以外の画像系の実時間確認」だけ |
| 3 エンジンで合成結果が一致 | はい（E 章、差 0。対照は 3,700 画素超） |
| ジョブ 1 本が 30 分未満 | はい。最長は詰まった Firefox 9 の 23 分 6 秒、通常は 7〜16 分。workflow は `timeout-minutes: 25` |
| プレビュー再生成の中央値 100ms 以下 | はい（2〜5ms、F 章） |
| 手元の CI 相当で安定 | Chromium と WebKit は修正後すべて合格。Firefox は修正後 6 回中 5 回合格、機械が詰まった 1 回（load average 30 超）だけ失敗（B-5）。**未解決事項**として次フェーズに持ち越す：Firefox の実時間確認は機械の混み具合で撮り直しが増え、極端に詰まると単独でも間に合わない |

**判定：S4-1 開始は Go**。ただし次を条件とする。

1. GitHub Actions での初回実行（push の承認後）で、3 ジョブの時間と `export.realTime`（撮り直し数、gap 最大）を確認する。Linux ランナーでの値は [未検証]。
2. Firefox の実時間確認はランナーの混み具合に敏感で、単独の撮り直しで吸収している。初回実行で撮り直しが常に多い（10 件超）なら、Firefox だけ `SVGM_PARALLEL` を下げる（例：3）。
3. S4-1 の編集 UI は、ここで確定した `compileEdit` と schema 2 の上に載せ、`validateEdit` の規則（丸めず拒否、ID 再利用なし、ロゴ差し替えで破棄）を変えない。
