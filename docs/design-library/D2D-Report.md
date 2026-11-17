# OmochiX Design Library — D2-D 実装報告（新規 3 作品）

- ブランチ：`feature/design-library-d1`
- 基準：D2-C の commit `c489219`。最終監査の後、D2-D の変更だけをローカルに commit した（push・PR・merge はしていない）
- 実施日：2026-11-17
- 環境：使い捨てのローカル WordPress（`http://127.0.0.1:8961`）。本番・DB・キャッシュは操作していない
- 新しい依存パッケージ・外部フォント・外部ライブラリは追加していない。CSP・sandbox・公開の判定・ランタイムは変更していない。
- 変更していないもの：D0 の未追跡文書 2 件（SHA-1 一致）、既存の QA 画像と `perf-results.json`（ハッシュ一致）、他の worktree、Motion Studio の別リポジトリ（読み取りだけ）

## 1. Phase 1 事前確認

| 項目 | 結果 |
| --- | --- |
| branch・HEAD | `feature/design-library-d1`、HEAD `c489219`（D2-C） |
| git status（開始時） | 変更 36（D2-C 開始前からある QA 画像の再生成分）、未追跡 11（D0 文書 2・QA 画像・D2D-Design.md） |
| カタログ構造 | v2。作品ごとに id・slug・medium・用途・表現・技術・スタイル・モーション・業種・配色・フォント・ポスター・ループ・プレビュー・素材・カスタマイズ・プロンプト・手順・成果物（Web は sha256）・クレジット・検証・関連・監査。説明文は 200 文字まで |
| フォントのライセンス | **未検証（運営者の確認が必要）**。macOS の使用許諾 §1.E は「Apple ソフトウェアの実行中に内容を表示・印刷するために」フォントを使えるとし、埋め込みはフォントごとの制限に従う、としている。動画に描き出して配布する使い方を明示的に許可・禁止する文言は確認できなかった。法的な判断はしていない。対策として、Brand Typography Motion のワードマーク「YOMIBA」はフォントを使わず線で自作した。和文のコピーは既存の 2 本の動画と同じくシステムフォント（ヒラギノ）で描き出している。Web 作品は閲覧者の端末のフォントで表示するだけなので、この問題は無い |
| Core の業種ターム | `real-estate`・`restaurant`・`beauty`・`ecommerce`・`construction`・`web-production` の 6 件（`omochix_core_get_industry_initial_terms()`、ローカル DB も同じ）。家計・経費アプリに合う用語は無いので、Micro Interaction は空にした |
| Motion Studio の既存テンプレート | 読み取りだけで確認。`src/videos/registry.ts` に 82 件の動画（解説動画とブランドフィルム）。サイズは 1080×1920 の縦型だけで、1:1 の文字だけの動画に当たるテンプレートは無い。→ `motionStudio` は書かず、制作ボタンも出さない（既存のメタデータ方式のまま） |
| 架空ブランド名 | 一般的な Web 検索で、同名の製品は見つからなかった：YOMIBA（書店兼カフェ）、HAKARI（家計簿アプリ）。AI アプリは当初「TOMOSHI」としたが、綴りの近い AI アシスタントアプリ「TOMOSIA AI Assistant」があったため、「NOKURA」に変えた。**商標の登録（J-PlatPat など）は確認していない（未検証）** |

## 2. 制作した 3 作品

### 2.1 Brand Typography Motion（`dl-video-0003`、`brand-typography`）

- 架空の書店兼カフェ「YOMIBA」の 1:1・10 秒・30fps・音声なしの動画。地の色は紙 `#f2ecdf`・明るい緑 `#3cc47c`・黄 `#ffd23f`、文字は墨 `#12352b`（紙 11.4:1・緑 6.0:1・黄 9.3:1）。
- 4 場面：① 紙の地に明朝の「よむ。」をマスクで 1 文字ずつ ② 緑の面を左から拭き、和文の縦組みと欧文の太字 3 行を交差させる ③ 黄の面を上から下ろし、細・太・明朝の 3 行を字間を詰めながら出す ④ 紙の円が広がり、線で自作したワードマーク「YOMIBA」を描いて 1 秒以上止める（ループの継ぎ目）。
- 既存の方式のまま制作：`tools/design-library/video-sources/brand-typography/index.html` を `render-video.js` で 1 フレームずつ書き出した（`SPECS` に 1 件追加）。動画 MP4 271KB・WebM 277KB、一覧のループ 960×960（WebM 159KB・MP4 151KB）、ポスター AVIF 11KB・WebP 18KB。
- 動画内の文字はすべて、詳細ページの「カスタマイズ」の先頭に全文で載せた（説明文は 200 文字の上限があるため）。
- Motion Studio：該当するテンプレートが無いので `motionStudio` は書いていない（制作ボタンは出ない。既存の方式のまま）。
- 1:1 の動画のための表示の修正（テーマ）：
  - 一覧カードの情報行が「1:1 横型」になっていたのを「1:1 正方形」に直した（`omochix_design_card_facts()`）。
  - 詳細ページに正方形の枠（`dl-stage--square`、幅は 80vh・760px まで）を追加した（`detail.php`・`design-library.css`）。

### 2.2 AI Workspace UI（`dl-web-0007`、`ai-workspace`）

- 架空の AI リサーチアプリ「NOKURA」の作業画面。架空の「みなと市」の図書館の資料を題材にした。
- 3 カラム（スレッド・会話・出典とメモ）、コマンドパレット（⌘K / Ctrl+K、矢印・Enter・Esc、フォーカスは閉じたら元へ戻る）、出典番号から参照パネルへの移動、回答の段落ごとの表示と「止める」、スケルトン、読み込みに失敗した出典と再読み込み、新しい調査の空の状態。
- ライトとダーク：上部のボタンとコマンドで切り替え、サイトのテーマにも従う（親ページからの `omx-design:theme`）。単独で開いたときは OS の設定に従う。
- 画面幅：1200px 以上は 3 カラム（出典パネルを閉じられる）、768〜1199px はスレッド一覧を引き出しに、767px 以下は下のタブで 1 画面ずつ。
- **入力内容はどこにも送らない**：AI・外部 API には接続していない。返答は用意した文章で、入力した文字は `textContent` でだけ表示する（`<img onerror>` を入れても要素にならないことを試験で確認）。localStorage・Cookie・クリップボード・フォーム送信は使っていない。
- タイマーは一時停止中・非表示中は保留し、再開すると続きから表示する。reduced-motion・Save-Data では回答を一度に表示する。JavaScript が無いときはすべてを縦に並べて読める（入力欄・パレットなど JS が要る操作は出さない）。
- 性能（Chromium、perf.js）：転送 12.6KB・JS 5.6KB・長いタスク 0・ヒープ 1MB・fps p5 109・LCP 2,204ms・CLS 0。設計の目安（転送 12KB・JS 5KB）をわずかに超えた。
- 設計からの変更：アクセント色を `#4d7c0f` → `#3f6a0a` にした（薄い緑の上の出典番号が 4.4:1 で基準に届かなかったため。変更後は 5.6:1）。アプリ名を TOMOSHI → NOKURA にした（§1）。

### 2.3 Micro Interaction Collection（`dl-web-0008`、`micro-interactions`）

- 架空の家計・経費アプリ「HAKARI」の 1 画面に、3 つの場面をまとめた。Learn Motion Gallery のように動きを 1 つずつ並べるのではなく、アプリの中で状態の変化を伝える動きとして作った。
  1. **設定を変える**：`role="switch"` のチェックボックス（押している間つまみが伸び、離すと行き過ぎて戻る）、理由付きの無効なスイッチ、2 択のセグメント（矢印キーで移動、つまみが追う）、割合のスライダー。変えるたびに「保存しました（項目：値）」を短く表示（`role="status"`）。
  2. **入力を確かめる**：金額の確認（範囲外は `aria-invalid`、`aria-describedby` でつながったメッセージ、横に 1 回だけ 4px 揺らす。正しければ書式を整えてチェックを描く）、メモの文字数（上限の手前で警告）、記録ボタンの「読み込み中（`aria-busy`）→ 成功（チェックを描く）→ 元に戻る」と、失敗の例（「もう一度試す」）。ボタンの 8 つの状態（通常・ホバー・フォーカス・押下・無効・読み込み中・成功・失敗）の見本も並べた。
  3. **状態を切り替える**：精算済みのチェックで未精算の合計が数字の動きで変わる。削除すると次の行にフォーカスを移し、5 秒間「元に戻す」を出す（戻すと同じ位置に戻り、その行にフォーカスが返る）。
- 状態は色だけでなく、形・記号（! と ✓）・文字でも伝える。押せる部分はすべて 44px 以上。
- 入力内容は送信も保存もしない。新しい行のメモも `textContent` でだけ表示する（`<b>` を入れても要素にならないことを試験で確認）。
- 一時停止中・非表示中は、保存の印・送信の結果・取り消しの猶予（5 秒）を進めない。reduced-motion では揺れ・移動・数字の動きをやめ、状態の表示（色・記号・文字）は残す。JavaScript が無いときも、設定のスイッチ・セグメント・スライダー・精算のチェックは HTML の標準の動作で使える（削除・失敗の例・結果の行は出さない）。
- ライトとダークの両方に対応（サイトのテーマに従う）。
- 性能（Chromium、perf.js）：転送 10.6KB・JS 4.6KB・長いタスク 0・ヒープ 0.9MB・fps p5 100・LCP 2,180ms・CLS 0。設計の目安（転送 10KB・JS 3KB）を少し超えた（3 場面の状態と取り消しの処理を入れたため）。

## 3. 変更ファイル

| ファイル | 内容 |
| --- | --- |
| `wp-theme/omochix-theme/assets/data/design/catalog.json` | 3 作品を追加（D2-D の 3 件は `verified-local`）。Logo Reveal Short の関連作品に Brand Typography Motion を追加 |
| `wp-theme/omochix-theme/assets/design/demos/ai-workspace/`（新規 3 ファイル） | AI Workspace UI のデモ |
| `wp-theme/omochix-theme/assets/design/demos/micro-interactions/`（新規 3 ファイル） | Micro Interaction Collection のデモ |
| `wp-theme/omochix-theme/assets/design/{videos,loops,posters}/brand-typography.*`（新規 6 ファイル） | 動画・ループ・ポスター |
| `wp-theme/omochix-theme/assets/design/{loops,posters}/{ai-workspace,micro-interactions}.*`（新規 8 ファイル） | ループ・ポスター |
| `wp-theme/omochix-theme/inc/design-library.php` | 1:1 の動画を「正方形」と表示（1 行） |
| `wp-theme/omochix-theme/template-parts/design-library/detail.php` | 1:1 の動画の枠（`dl-stage--square`） |
| `wp-theme/omochix-theme/assets/css/design-library.css` | 正方形の枠の大きさ（1 行） |
| `tools/design-library/video-sources/brand-typography/index.html`（新規） | 動画の元になる HTML（テーマには含まれない） |
| `tools/design-library/render-video.js` | `SPECS` に brand-typography |
| `tools/design-library/capture-media.js` | 2 作品のポスター・ループの撮り方 |
| `tests/design-library/browser/d2d-video.js`（新規） | 動画作品の試験（Chromium・WebKit・Firefox） |
| `tests/design-library/browser/d2d-web.js`（新規） | 2 つの Web 作品の操作の試験（Chromium・WebKit・Firefox） |
| `tests/design-library/browser/new-works.js` | 2 作品の項目（コントラスト・表示・フォーカス） |
| `tests/design-library/browser/lib.js` | `DEMOS` に 2 作品、`published()`（カタログから作品数を数える）、`qaOut()`（`DL_QA_OUT` で QA の出力先を変える） |
| `tests/design-library/browser/{ui,firefox,list-layout,preview,perf}.js`、`catalog-check.js`、`run.php`、`http-check.php` | 作品数・件数をカタログから数えるように変更、新しい作品の期待値、QA の出力先 |
| `tests/design-library/run-all.sh` | `d2d-video`・`d2d-new`・`d2d-web` を追加 |
| `docs/design-library/D2D-Report.md`・`D2D-Storytelling-Design.md`（新規） | この報告と 4 作品目の設計 |

QA の画像（`docs/design-library/qa/d2d/`）は、D2-B・D2-C と同じく作業ツリーにだけ置く。

**既存の QA 画像を変えないための工夫**：既存の試験（ui・preview・perf）は、実行のたびに `qa/screenshots`・`qa/d2a/after`・`perf-results.json` を上書きしていた。今回は `DL_QA_OUT`（作業用のフォルダー）を指定すると、そちらに書き出すようにした。D2-D の試験はすべてこの指定で実行し、既存の QA ファイル 129 件が開始時とハッシュで一致することを確認した（削除・復元はしていない）。指定しない場合の動作は従来どおり。

## 4. 検証（Phase 3）

作品ごとに「作品の試験 → 既存の全試験 → 86 URL の回帰」を通してから次の作品へ進んだ。各作品は `unverified` で追加し、作品の試験に合格してから `verified-local` にした（`verified-production` はどの作品にも無い）。

### 4.1 作品ごとの確認

| 確認 | Brand Typography | AI Workspace | Micro Interaction |
| --- | --- | --- | --- |
| カタログ（スキーマ・PHP の検証・sha256） | 合格 | 合格 | 合格 |
| PHP・JS の構文 | `php -l`・`node --check` 合格 | 同左 | 同左 |
| CSP 違反・外部通信 | 0・0（一覧・詳細） | 0・0（直接・埋め込み） | 0・0（直接・埋め込み） |
| sandbox の隔離（opaque origin・Cookie 不可・親へ届かない） | 動画のため iframe なし | isolation 合格 | isolation 合格 |
| Chromium・WebKit・Firefox | 3 つとも | 3 つとも | 3 つとも |
| PC・タブレット・スマホ（1440・834・390） | 横スクロールなし・1:1 の枠 | 3 カラム／引き出し／タブ | 2 列／1 列 |
| キーボード | 再生ボタン・動画の操作 | パレット・タブ・引き出し・出典 | スイッチ・セグメント・削除と取り消し |
| axe（WCAG 2.2 A/AA、serious・critical） | 0（ライト・ダーク × 1440・390） | 0（ライト 1440・390、ダーク 1440・834・390） | 0（ライト・ダーク × 1440・834・390） |
| reduced-motion | 自動再生しない | 回答を一度に表示・アニメーション 0 | 揺れ・移動 0、状態は表示 |
| Save-Data | 自動再生しない | 回答を一度に表示 | 常に動くものは無い |
| JS 無効 | ラベル・ポスター・`<video controls>` | 全文を縦に表示 | 標準の部品で操作できる |
| 再生・停止・再開 | カードのループと詳細の動画で時刻が保たれる | 一時停止・非表示中は回答が止まり、再開で続く | 取り消しの猶予が非表示中は進まない |

### 4.2 全体（最終）

| 試験 | 結果 |
| --- | --- |
| catalog | 121 / 121 |
| unit | 89 / 89 |
| http | 169 / 169 |
| isolation（Chromium・WebKit） | 100 / 100 |
| firefox | 31 / 31 |
| ui（Chromium・WebKit） | 216 / 216（※） |
| layout | 116 / 116 |
| d2d-video（新規） | 56 / 56（※） |
| d2d-new（new-works の 2 作品） | 64 / 64 |
| d2d-web（新規） | 96 / 96 |
| preview | 96 / 96（※） |
| perf | 60 / 60（※） |
| heal | 25 / 25 |
| 回帰（86 URL） | 差分 0 |

※ 下の 4.3 のとおり、この 4 件は**最後の通しの実行（初回）では失敗**し、**個別の再実行で合格**した。表の数値は再実行の結果。

### 4.3 試験の停止（ハング）4 件 — 原因は未特定

| 試験 | 初回（最後の通しの実行） | 再実行（個別、20 分の上限付き） |
| --- | --- | --- |
| ui | Chromium の一覧の検査（スマホ幅で遅延読み込みのポスターを確かめる手順）で 63 分間止まったため、手動で停止 → FAIL | 216 / 216 合格 |
| d2d-video | WebKit で動画の詳細ページの読み込みが 30 秒で時間切れ → FAIL | 56 / 56 合格 |
| preview | 48 項目合格の後、クリック操作が 30 秒で時間切れ → FAIL | 96 / 96 合格 |
| perf | Bento SaaS の計測の後（Dark Luxury の計測中）に 38 分間止まったため、手動で停止 → FAIL | 60 / 60 合格 |

- **原因は特定していない。** 確かめたこと：止まっている間もローカルの PHP サーバーは 20ms で応答し、ワーカーに詰まりは無かった。止まった Node の CPU 使用率はほぼ 0 で、ヘッドレスのブラウザーは起動していた。止まった箇所はいずれも D2-D で変えていない手順・作品だった。それより前の段階（動画のみ、AI Workspace まで）の通しの実行でも perf が 1 回 70 分止まり、単独で再実行すると数分で終わった。
- 対応：perf.js の応答本文の読み取りを最大 10 秒で打ち切るようにした（止まったまま待ち続けないため。ただし、これで原因が解消したかは確認できていない）。
- 次に通しで実行するときは、試験ごとに時間の上限を付けて、再び止まるか見る必要がある。
- 別件：Aurora Gradient の CLS が 1 回だけ 0.082（上限 0.05）になり、再実行では 0 だった（D2-D では変えていない作品）。

## 5. 未検証・残る課題

- **【未解決】フォントのライセンス**：動画に描き出したシステムフォント（ヒラギノ）の利用条件は、運営者の確認待ち（既存の 2 本の動画も同じ）。
- **【未解決】商標**：YOMIBA・NOKURA・HAKARI は一般的な Web 検索だけで、商標の登録（J-PlatPat など）は確認していない。公開前に確認が必要。
- **実機**：iOS Safari・Android Chrome の実機は未確認（タップのしやすさ、スマホのキーボードが出たときの AI Workspace の入力欄の位置など）。
- **Save-Data・端末のメモリ**：試験上の偽装だけ。
- **本番**：LiteSpeed・キャッシュを通した隔離、永続オブジェクトキャッシュ、実際の Slim SEO との連携、本番の Learn ページは未確認（D2-B から同じ）。
- **性能の目安**：AI Workspace（12.6KB / 5.6KB）と Micro Interaction（10.6KB / 4.6KB）は、設計の目安をわずかに超えた。サイト全体の予算（300KB / 40KB）の範囲内。
- **【未解決】試験の停止**：4 件の試験が初回の通しの実行で止まった・時間切れになった（§4.3）。原因は特定していない。
- **一覧の初回の転送量**：作品が増えたため、Web 一覧の初回転送量は 455.7KB（D2-C）→ 508.2KB（ループ 2 本を含む）。上限 1.5MB の範囲内。

## 6. Phase 4：Interactive Storytelling

設計は `docs/design-library/D2D-Storytelling-Design.md`。実装はしていない。

- 架空の紙すき工房の一年を、段落と挿絵が同期するスクロリーテリングで見せ、途中で「職人の目／紙の目」に語り手を切り替えられる読み物。
- 既存作品との違いは、挿絵の同期・語り手の切り替え・目次と読書位置。HTML・CSS・JS・SVG だけで、WebGL・Canvas・画像は使わない。スキーマの変更は要らない。
