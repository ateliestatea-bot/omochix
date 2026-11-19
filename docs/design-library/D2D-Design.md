# OmochiX Design Library — D2-D 設計（新規 4 作品）

- ブランチ：`feature/design-library-d1`、基準 commit `c489219`（D2-C）
- 作成日：2026-11-17
- 範囲：調査と設計だけ。コード・カタログ・スキーマ・試験は変更していない。この文書も未コミット。
- 変更していないもの：D0 の未追跡文書 2 件（SHA-1 一致）、QA 画像、別の worktree、Motion Studio。

## 0. 開始時の確認結果

| 項目 | 内容 |
| --- | --- |
| HEAD | `c489219`（D2-C）。その前が `9884956`（D2-B）、`a6ac4e9`（D2-A）、`fdd8b30`（D1） |
| git status | 変更 36 件（D2-C 開始前からある QA 画像の再生成分）、未追跡 10 件（D0 文書 2・QA 画像）。いずれも触っていない |
| 公開している種類 | `omochix_design_public_mediums()` は `web`・`video` だけ。`app`（端末プレビュー）と `component`（ステージプレビュー）はスキーマと URL の規則にはあるが、公開していない |
| 作品 ID | `dl-(web\|video\|app\|component)-NNNN`。次の番号は web `0007`〜、video `0003`〜 |
| 語彙（enum） | 用途 10、表現 9、技術 9、スタイル 12、モーション 15。業種は自由な slug で、OmochiX Core の `omx_industry` の用語と一致したものだけが絞り込みに出る |
| 隔離 | `/design/demo/web/{slug}/`。CSP は `sandbox allow-scripts`、スクリプトはハッシュ指定、`connect-src 'none'`、`frame-ancestors 'self'`。デモは 1 枚の文書に埋め込む（外部ファイル・外部フォントなし） |
| ランタイム | `window.omxDemo`（`isReduced`・`isHidden`・`isPaused`・`onMotion`・`onVisibility`・`onTheme`・`report`）。`html.js`・`html.reduced`・`omx-paused` のクラス |
| 動画の制作 | `tools/design-library/render-video.js` と `video-sources/`（HTML/SVG を書き出し、FFmpeg で mp4・webm を作る）。ポスターとループは `capture-media.js` |
| Motion Studio | 連携は設計だけ（D1.5 §5）。カタログの `motionStudio {templateId, status, settings}` は値だけを持ち、`omochix_design_motion_studio_url()` は常に空文字を返す。サイトから Motion Studio へのリンクはない |
| Learn | `related.learn` はページのパス。公開済みのページだけを表示する（`learn/development`・`learn/tools/vercel` はローカルに無いので非表示。D2-A から既知）。`related.motion` は Learn Gallery の 53 件の slug |

### 既存 8 作品

| # | 作品 | 種類 | 用途 | 表現 | 技術の特徴 | 地の色 |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Liquid Glass | Web | LP・製品 | ポインター・スクロール・アンビエント | CSS・SVG | 暗い（紺に紫・水色） |
| 2 | Minimal Editorial | Web | メディア・ブランド紹介 | 文字・スクロール・画面遷移 | CSS・SVG | 明るい（生成り・朱） |
| 3 | 3D Interactive | Web | 製品・LP | 3D・ポインター・スクロール | WebGL | 暗い |
| 4 | Bento SaaS | Web | SaaS・LP | UI・グラフ・スクロール | CSS・SVG | 明るい（藍） |
| 5 | Dark Luxury | Web | ブランド紹介・企業・作品集 | スクロール・文字・UI | SVG・明朝 | 暗い（金） |
| 6 | Aurora Gradient | Web | SaaS・LP・製品 | アンビエント・ポインター・スクロール | Canvas 2D | 暗い紺に極光 |
| 7 | Logo Reveal Short | 動画 9:16 | SNS ショート・ブランド紹介 | ロゴ・文字 | SVG＋FFmpeg | 暗い（紫） |
| 8 | Data Chart Explainer | 動画 16:9 | 解説・プレゼン | グラフ・文字 | SVG＋FFmpeg | 明るい |

既存作品の偏り：
- 暗い地が 5 件（1・3・5・6・7）。
- 用途は LP・製品・SaaS の「売るページ」が 4 件。「使う画面」（アプリの UI）は 0 件。
- 1:1 の動画、ライトとダークの両方に対応する作品、フォームなどの操作の状態（成功・失敗・読み込み中）を見せる作品は無い。

## 1. 4 作品の設計

共通の前提（4 作品すべて）：
- オリジナルの架空ブランド。実在の製品・企業の名前・ロゴ・画面構成をまねない。
- 外部への通信・外部ライブラリ・Web フォントは使わない。フォントは OS のもの（`Hiragino Sans`・`Hiragino Mincho ProN`・`Yu Gothic`・`system-ui`）。
- CSP・sandbox・公開の判定・ランタイムは変えない。新しい作品は、ランタイムの `onMotion`・`onVisibility`・`onTheme` を使う。
- `unverified` で作り始め、ローカルの検証に合格してから `verified-local` にする。`verified-production` にはしない。
- reduced-motion と Save-Data では、連続する動きを止めて完成した状態を見せる。JS が無いときは CSS だけで完成した状態を見せる（`html.js` があるときだけ動きを付ける）。
- 一時停止（`omx-paused`）・タブの非表示・画面外では、rAF・タイマーを止め、元の場所から再開する。

### 1.1 AI Workspace UI（`dl-web-0007`、slug 案 `ai-workspace`）

| 観点 | 設計 |
| --- | --- |
| コンセプト | 架空の AI リサーチ用ワークスペース「TOMOSHI」。質問・回答・出典・メモを 1 画面で扱う**アプリの画面**を見せる。既存作品にある「製品を紹介するページ」ではなく、「製品そのものの UI」 |
| 業種・用途 | 用途：`saas`・`product`。業種：`web-production`（Core にある用語だけを使う） |
| 構成 | 3 カラムの作業画面。① 左：スレッド一覧・フォルダー ② 中央：会話と文書（回答・出典番号・表） ③ 右：出典・メモのパネル。上部にコマンドパレット（⌘K / Ctrl+K）。空の状態、読み込み中（スケルトン）、エラー、出典のプレビューを見せる |
| 配色 | ライトとダークの両方。どちらも OS の設定とランタイムの `onTheme` に従う（既存作品で両方に対応するものは無い）。ライト：紙 `#f6f5f2`・墨 `#17181c`・オリーブ `#4d7c0f`。ダーク：`#121316`・`#e8e6e1`・`#a3e635`。紫は使わない |
| 文字 | `system-ui`・`Hiragino Sans`。本文 15px・行間 1.7。数値は等幅数字。見出しは小さめにして情報の密度を上げる |
| アニメーション | 回答を少しずつ表示する（文単位、あらかじめ用意した文章。生成はしない）。出典の番号を押すと右パネルの出典がハイライトされる。パネルの開閉、コマンドパレットの出入り、スケルトンから本文への切り替え。どれも 120〜240ms の短い動き |
| PC・スマホ | 1200px 以上：3 カラム。768〜1199px：右パネルは引き出し。767px 以下：1 カラムで、下に「スレッド・会話・出典」の切り替え（タブ）。入力欄は画面下に固定し、キーボードが出ても隠れないようにする（`dvh`） |
| 技術 | HTML・CSS・JS（`html`・`css`・`js`、必要なら `svg`）。Canvas・WebGL は使わない |
| 性能 | 転送量の目安 12KB 以下、JS 5KB 以下。文字の表示は rAF 1 本で、1 フレームに 1 文まで。長いタスク 0。CLS 0（回答欄の高さを先に確保する） |
| セキュリティ | 入力欄は**どこにも送らない**。入力された文字は `textContent` だけで表示し、決まった返答を出す。`<form>` の送信は使わない（sandbox に `allow-forms` が無いため。ボタンと Enter キーで処理する）。クリップボードのコピーは sandbox では使えないので、「コピー」ボタンは置かないか、デモであることを表示する。`alert()` などのダイアログは使わない（`allow-modals` が無い）。架空の会話にも個人情報らしいものを入れない |
| アクセシビリティ | 回答欄は `aria-live="polite"`（文単位で読み上げ、1 文字ずつは読まない）。コマンドパレットは `role="dialog"`、開いている間はフォーカスを中に閉じ込め、Esc で閉じて元の場所へ戻す。タブは矢印キーで移動。reduced-motion では回答を一度に表示する |
| JS 無効 | 回答・出典・メモがすべて表示された完成状態。パネルの切り替えはせず、縦に並べる |
| Learn・Motion | Motion：`skeleton-loading`・`typewriter`・`tabs-indicator`・`drawer-menu`・`toast`。Learn：`learn/development`。関連作品：Bento SaaS（売るページと使う画面の組み合わせ） |
| 既存との違い | Bento SaaS はマーケティング用のダッシュボード風 LP で、こちらは操作する画面。状態の変化（空・読み込み中・エラー）、テーマの切り替え、キーボード操作が見どころ |
| カタログの値（案） | 表現 `ui`・`transition`・`typography`。スタイル `dashboard`・`minimal`・`light`・`dark`。モーション `stagger`・`text-reveal`・`page-transition`・`hover-lift`。難易度 `intermediate` |

### 1.2 Cinematic 3D Landing Page（`dl-web-0008`、slug 案 `cinematic-3d`）

| 観点 | 設計 |
| --- | --- |
| コンセプト | 架空の山あいの宿「KIRIMA」の夜明けを、スクロールで進む短い映画のように見せる。手続き的に作った地形・霧・朝日の中をカメラが進み、4 つの章（夜明け前・霧・朝日・部屋）で文章が入れ替わる |
| 業種・用途 | 用途：`lp`・`brand-intro`。業種は宿泊・観光が合うが、Core に用語があるかは未確認。無ければ空にする（業種の用語は Core 側の作業で、ここでは足さない） |
| 構成 | 全画面のヒーロー（上下に黒帯、シネマスコープ風）→ 章ごとに固定（pin）した文章 → 最後に静かな予約の案内（ボタンは飾りで、どこにも送らない）。章の番号と進み具合を画面の端に出す |
| 配色 | 夜明けの色：スレート `#1c2433`・霧 `#e9eef5`・桃 `#f2c6a0`・日の色 `#ff8a5b`。3D Interactive（黒地に紫と水色）とはっきり分ける。暗い地から明るい地へ変わっていく |
| 文字 | 見出しは `Hiragino Mincho ProN`・`Yu Mincho`。字間を広く取る。本文は `Hiragino Sans`。Dark Luxury（明朝と金）と区別するため、文字は細く、色は霧の白 |
| アニメーション | スクロールに合わせてカメラが前へ進む・高度が下がる。霧の濃さ・日の位置・色温度が変わる。章が変わるとき文章がマスクで現れる。ポインターでは、わずかに視線が動く（PC だけ） |
| PC・スマホ | PC：WebGL の全画面。スマホ：描画の解像度を下げ（DPR は最大 1.5）、霧を 1 層にする。縦長の画面ではカメラの画角を広げ、地平線の位置を画面の 60% にする |
| 技術 | `html`・`css`・`js`・`webgl`。WebGL は 3D Interactive と同じく自前の小さなコードで書く（three.js などは入れない）。地形は高さのグリッドとノイズで作る。テクスチャ画像は使わない |
| 性能 | 転送量の目安 40KB 以下（3D Interactive は 38.7KB）、JS 8KB 以下。30fps と 60fps を切り替え、遅い端末では霧の層と解像度を自動で下げる（Aurora と同じ考え方）。画面外・一時停止・タブの非表示で描画を止める。WebGL の初期化は最初の描画の後（LCP は静止画のポスター） |
| セキュリティ | 3D Interactive と同じ制約。シェーダーはインラインの文字列、外部の読み込みなし。WebGL のコンテキストが失われたら静止画に切り替え、再取得できたら再開する |
| アクセシビリティ | 文章は普通の HTML（canvas の外）で、順に読める。章ごとの見出し。キャンバスは `aria-hidden`。reduced-motion ではカメラを動かさず、章ごとの静止画（CSS の背景）と文章を見せる |
| JS 無効・WebGL 無し | 4 章の静止画（CSS のグラデーションと SVG の山の輪郭）と文章を縦に並べる |
| Learn・Motion | Motion：`dolly-in`・`section-pin`・`mask-reveal`・`parallax`・`scroll-progress`。Learn：`learn/development`。関連作品：3D Interactive・Dark Luxury |
| 既存との違い | 3D Interactive は「製品を回して組み立てる」操作型で、こちらは「スクロールで物語を見せる」演出型。地の色も黒から夜明けへ変える。**4 作品の中で既存作品と最も重なり、性能とセキュリティ確認の負担も最も大きい** |
| カタログの値（案） | 表現 `3d`・`scroll`・`typography`。スタイル `3d`・`immersive`・`editorial`。モーション `camera-scroll`・`parallax`・`text-reveal`・`scroll-linked`。難易度 `advanced` |

### 1.3 Micro Interaction Collection（`dl-web-0009`、slug 案 `micro-interactions`）

| 観点 | 設計 |
| --- | --- |
| コンセプト | 架空の家計アプリ「HAKARI」の設定画面と送金フォームを舞台に、操作の手応え（押す・切り替える・送る・失敗する・成功する）を集めたもの。単独の動きを並べた見本帳ではなく、**画面の中で意味を持つ小さな動き** |
| 業種・用途 | 用途：`product`・`saas`。業種：未定（Core の用語を確認して決める） |
| 構成 | 1 ページに 4 つの場面。① 設定（トグル・セグメント・スライダー） ② フォーム（入力の検証・エラーの揺れ・パスワードの表示切り替え。値は使い捨てで保存しない） ③ 送信（ボタンが読み込み中から完了へ変わる。失敗の例も） ④ 通知（トースト・バッジ・元に戻す）。それぞれに「状態」の切り替えボタンを付け、通常・ホバー・フォーカス・押下・無効・読み込み中を見比べられるようにする |
| 配色 | 粘土色の明るい地 `#f3efe8`・墨 `#1d1b26`・珊瑚 `#ff6b4a`。状態の色は成功 `#1a7f4b`・エラー `#c62828`。状態は色だけでなく形・アイコン・文字でも区別する |
| 文字 | `system-ui`・`Hiragino Sans`。ラベル 14px、数値は等幅数字 |
| アニメーション | 80〜200ms の短い反応。ばね風の減衰（CSS の `linear()` で近似し、対応していないブラウザーでは ease）、チェックマークの描画、数字の繰り上がり、エラーの横揺れ（1 回だけ・振れ幅 4px） |
| PC・スマホ | PC：2×2 の場面。スマホ：1 列。押せる部分はすべて 44px 以上。ホバーの無い端末では、押したときの反応を主にする |
| 技術 | `html`・`css`・`js`・`svg`。動きの多くは CSS で、JS は状態の切り替えだけ |
| 性能 | 転送量の目安 10KB 以下、JS 3KB 以下。常に動いているものは無い（操作したときだけ動く）。`transform`・`opacity` だけを動かす |
| セキュリティ | 入力の値はどこにも送らず、保存もしない（localStorage は sandbox では使えない前提で、使わない）。`<form>` の送信は使わない。「パスワード」欄は見た目だけで、`autocomplete="off"`。実在の銀行・カードの番号らしい値は表示しない（`0000` などの明らかなダミー） |
| アクセシビリティ | 本物の `button`・`input`・`role="switch"` を使う。エラーは `aria-invalid` と `aria-describedby`。トーストは `role="status"`。読み込み中は `aria-busy`。reduced-motion では揺れと移動をやめて、色・アイコン・文字だけで状態を伝える |
| JS 無効 | 各場面の完成した状態（エラーの例・成功の例も並べる）。トグルなどは HTML の標準の動作だけ |
| Learn・Motion | Motion：`toggle-switch`・`button-press`・`morph-submit`・`check-draw`・`toast`・`number-flip`（Learn Gallery と最も重なるので、各動きから Gallery の解説へ案内する）。Learn：`learn/development` |
| 既存との違い | Learn Gallery は動きを 1 つずつ説明する。こちらは「同じ画面の中で、状態の変化を一貫した動きで伝える」設計の見本。既存の Web 作品はすべてページ全体の演出で、操作の部品を主役にした作品は無い |
| カタログの値（案） | 表現 `ui`・`pointer`・`transition`。スタイル `minimal`・`light`。モーション `hover-lift`・`line-draw`・`count-up`・`stagger`。難易度 `beginner`〜`intermediate` |
| 種類（medium） | 本来は `component`（ステージのプレビュー：状態・操作の一覧）が合う。ただし `component` は公開していないので、D2-D では `web` として作る（§2 の案 A） |

### 1.4 Brand Typography Motion（`dl-video-0003`、slug 案 `brand-typography`）

| 観点 | 設計 |
| --- | --- |
| コンセプト | 架空の書店兼カフェ「YOMIBA」の、文字だけで作るブランド動画（10 秒・1:1）。ロゴは出さず、和文と欧文の言葉の組み方・大きさ・リズムでブランドの声を伝える |
| 業種・用途 | 用途：`sns-short`・`brand-intro`。業種：`restaurant`（Minimal Editorial と同じ用語。Core で確認する） |
| 構成 | 4 つの場面：① 一語が画面いっぱいに出る ② 和文の縦組みと欧文の横組みが交差する ③ 色の面が切り替わり、短いコピーを 3 行で ④ 店の名前と一行の説明で終わる（最後の 1 秒は止める。ループの継ぎ目を目立たせない） |
| 配色 | 深い緑 `#12352b`・紙 `#efe9dc`・信号の黄 `#ffd23f`。場面ごとに地と文字の色を入れ替える。Logo Reveal（暗い地に紫のグラデーション）とは逆の、平らな色面 |
| 文字 | 和文 `Hiragino Mincho ProN`（縦組み）と `Hiragino Sans`（太さの差）、欧文は `system-ui`。動画は画像なので、閲覧者側にフォントは要らない。**ただし、書き出しに使う OS フォントを動画に含めてよいかは、Logo Reveal と同じく利用条件を確認する** |
| アニメーション | 文字単位のずらし、マスクで現れる、字間の伸縮、縦組みと横組みの入れ替え、色面のワイプ。12 の基本拍子で動かし、BGM は付けない（音声素材の権利を避ける。字幕は不要） |
| PC・スマホ | 1080×1080・30fps。一覧では 1:1 を contain で表示（D2-C の仕組みで切れない）。スマホでも文字が読める大きさ（最小で画面幅の 5%） |
| 技術 | `html`・`css`・`svg`・`ffmpeg`（既存の `render-video.js` を使う。新しい道具は入れない）。Remotion は使わない（Motion Studio の別リポジトリに触れないため） |
| 性能 | mp4 は 1.5MB 以下、webm も用意。一覧のループは 6 秒以内の切り出しで、既存の 2 本と同じ程度の大きさ。ポスターは AVIF と WebP |
| セキュリティ | 動画ファイルだけで、デモの iframe・スクリプトは無い。メタデータ（作成ソフト・場所）は FFmpeg で消す（既存と同じ） |
| アクセシビリティ | 詳細ページに動画の文字をすべてテキストで載せる（読み上げで内容が分かるように）。点滅は 1 秒に 3 回未満。一覧では reduced-motion・Save-Data のとき自動再生しない（既存の仕組み） |
| JS 無効 | 詳細ページの `<video controls>` とポスター（既存の仕組み） |
| Learn・Motion | Motion：`split-letters`・`kinetic-words`・`mask-reveal`・`reveal-lines`。Learn：`learn/svg-motion`。Motion Studio：テンプレートの候補（§3） |
| 既存との違い | 初めての 1:1 動画。ロゴを使わず文字だけで、明るい色面。Logo Reveal とは「暗い・縦長・ロゴ」と「明るい・正方形・文字」で反対の位置にある |
| カタログの値（案） | 表現 `typography`・`transition`。スタイル `typography`・`editorial`・`light`。モーション `text-reveal`・`stagger`・`page-transition`。プレビュー `{"type":"video","orientation":"square"}`。難易度 `intermediate` |

## 2. カテゴリー・スキーマの変更案（実装しない）

**D2-D の 4 作品は、今のスキーマと語彙のままで登録できる。** 次の変更は必須ではなく、入れるかどうかの判断材料として出す。

| 案 | 内容 | 影響範囲 | 判断 |
| --- | --- | --- | --- |
| A. `component` を公開する | Micro Interaction を `component`（ステージのプレビュー）で出す | `omochix_design_public_mediums()`、一覧・詳細のテンプレート（ステージのプレビューは未実装）、`/design/components/` の URL・サイトマップ・canonical、フィルターの種類、試験の作品数（catalog・ui・layout・firefox・http・回帰の URL が増える）、SEO の確認 | **今回は見送る**。作品が 1 件だけで種類を増やすのは早い。部品の作品が 3 件以上になってから |
| B. モーションの語彙を足す | `kinetic-type`（文字の組み替え）、`micro-feedback`（操作への反応）、`stream-text`（少しずつ表示） | `catalog.schema.json` の enum、PHP の検証と表示名、`catalog-check.js` の語彙の数。モーションは絞り込みの条件ではないので、一覧の HTML と回帰の結果は変わらない見込み（要確認） | 入れるなら `kinetic-type` と `micro-feedback` の 2 つ。無くても既存の語彙で表せる |
| C. 用途に `app-ui` を足す | AI Workspace・Micro Interaction を「アプリの画面」として絞り込めるようにする | スキーマの enum、PHP の検証と表示名、**フィルターの選択肢が増えるので一覧の HTML が変わる**（回帰の比較で差分が出る。基準の取り直しが要る） | 2 件そろってから検討。今は `saas`・`product` で表す |
| D. 業種の用語 | 宿泊・観光、小売など | 用語は OmochiX Core（プラグイン）の `omx_industry` 側。Design Library のスキーマは変わらない | Core の作業になるため、ここでは足さない。合う用語が無ければ空にする |
| E. `motionStudio` を書く | Brand Typography に `templateId` を設定する | スキーマは対応済み。表示は変わらない（URL は常に空） | Motion Studio に実在するテンプレートを確認できるまで書かない |

## 3. Learn・Motion Studio との関連付け

- **Learn Gallery（`related.motion`）**：4 作品とも、既存の 53 件から 4〜6 件を選ぶ（§1 の各表）。新しい Gallery の項目は作らない。
- **Learn のページ（`related.learn`）**：今あるパスは `learn/development`・`learn/tools/vercel`・`learn/svg-motion`。公開済みのページだけが表示されるので、作品の側では足すだけでよい。新しい Learn のページは D2-D の範囲外。
- **Motion Studio**：Brand Typography Motion が最初の候補。
  - 設定の候補：見出し（120 文字まで）、色 3 つ、秒数（6・10・15）、向き（1:1・9:16）。
  - D1.5 §5.3 の 4 条件（入口がある・値を渡して書き出せる・安全性・運用者の承認）がそろうまで、`motionStudio` は書かず、制作ボタンも出さない。
  - Motion Studio の別リポジトリは、今回は読んでも変えてもいない。

## 4. 設計の比較表

| 観点 | AI Workspace UI | Cinematic 3D Landing | Micro Interaction Collection | Brand Typography Motion |
| --- | --- | --- | --- | --- |
| 種類・ID | Web `dl-web-0007` | Web `dl-web-0008` | Web `dl-web-0009`（本来は component） | 動画 1:1 `dl-video-0003` |
| 主な用途 | SaaS・製品（使う画面） | LP・ブランド紹介 | 製品・SaaS（部品） | SNS・ブランド紹介 |
| 地の色 | ライト＋ダーク（両方） | 夜明け（暗い→明るい） | 明るい（粘土色） | 明るい色面（緑・紙・黄） |
| 描画 | HTML/CSS | WebGL（自前） | HTML/CSS/SVG | 動画（SVG→FFmpeg） |
| 常に動くもの | なし（操作したとき） | あり（スクロールと描画） | なし（操作したとき） | 動画（停止できる） |
| 転送量の目安 | 12KB 以下 | 40KB 以下 | 10KB 以下 | 動画 1.5MB 以下 |
| 既存との重なり | 小（Bento SaaS と用途が近い） | **大**（3D Interactive） | 中（Learn Gallery） | 小（Logo Reveal と反対の位置） |
| オリジナリティ | 高 | 中 | 中〜高 | 高 |
| 制作の負担 | 中 | 大 | 中 | 小〜中 |
| 検証の負担 | 中（キーボード・読み上げ・テーマ） | 大（GPU・性能・WebGL の喪失・3 ブラウザー） | 中（状態・読み上げ） | 小（ffprobe・再生・ポスター） |
| スキーマ変更 | 不要 | 不要 | 不要（component は将来） | 不要 |
| OmochiX との相性 | 高（AI メディア） | 中 | 中 | 高（SNS 運用・Motion Studio） |

## 5. 制作の優先順位

1. **Brand Typography Motion**：既存の動画の仕組みでそのまま作れ、危険が小さい。1:1 の動画と明るい色面という、欠けている部分を埋める。Motion Studio のテンプレートの候補にもなる。
2. **AI Workspace UI**：OmochiX（AI ツールのメディア）に最も合い、「使う画面」という新しい分類を開く。新しい技術は要らないが、キーボード・読み上げ・テーマ切り替えの検証に時間がかかる。
3. **Micro Interaction Collection**：Learn Gallery と重ならないよう「画面の文脈の中の動き」に絞る。`component` を公開するかの判断材料にもなる。
4. **Cinematic 3D Landing Page**：3D Interactive と最も重なり、WebGL の性能とセキュリティの確認が最も重い。2 つ目の WebGL 作品を作る価値があるかは、1〜3 の後に判断する。代わりの案：WebGL を使わず、CSS とスクロール連動の SVG で「映画のような」演出に絞る（重なりと負担が減る）。

作る順番の提案：D2-D1 で 1 と 2、D2-D2 で 3 と 4（4 は着手前に再判断）。

## 6. 12 作品全体のバランス

| 軸 | 今の 8 作品 | 12 作品にした後 | 評価 |
| --- | --- | --- | --- |
| 種類 | Web 6・動画 2 | Web 9・動画 3 | 動画の比率は変わらない（25%）。動画を 4 本目まで増やすか、Web の 1 件を動画にする余地がある |
| 地の色 | 暗い 5・明るい 3 | 暗い 5・明るい 6・両方 1（夜明けは中間） | 暗い地に偏っていたのが直る |
| 用途 | LP・製品・SaaS が中心 | アプリの画面・部品が加わる | 「売るページ」に偏っていたのが和らぐ |
| 表現 | スクロール 6 件、`ui` 2 件 | `ui` 4 件、`transition` 4 件 | スクロール演出への偏りが和らぐ。`logo`・`chart` は 1 件ずつのまま |
| 技術 | WebGL 1・Canvas 1 | WebGL 2・Canvas 1 | 4 を作る場合 WebGL が 2 件。重い作品の比率（3/12）は許容範囲 |
| 動画の向き | 9:16・16:9 | 9:16・16:9・1:1 | 3 つの向きがそろう |
| 難易度 | 初級 2・中級 3・上級 3 | 初級 3・中級 6・上級 3〜4 | 中級が厚くなり、初めての人も使いやすい |
| 業種 | 未設定の作品が多い | 同じ | 業種で探す使い方には弱い。Core の用語の整備と合わせて別に検討 |
| 暗い地＋紫 | 3 件（1・3・7） | 同じ。新しい 4 件は紫を使わない | サイトのブランド色（紫）との重なりは増えない |

## 7. 未確認・決めていないこと

- Core の `omx_industry` に宿泊・観光・小売などの用語があるか（業種の値が決まらない）。
- 動画の書き出しに使う OS フォント（ヒラギノ）を動画に含めてよいかの利用条件。Logo Reveal・Data Chart Explainer も同じ前提なので、まとめて確認したい。
- Motion Studio 側に、Brand Typography に合うテンプレートがあるか（別リポジトリは見ていない）。
- 3D 作品を 2 つ目まで作るか（§5 の 4）。
- 架空のブランド名（TOMOSHI・KIRIMA・HAKARI・YOMIBA）が実在の商標と重ならないかは、制作前に確認する。
- 作品が増えるたびに、試験の中の作品数（Web 6・動画 2）を直す必要がある。D2-D の実装では、数を固定せずカタログから数えるように試験を直すと、以後の手間が減る（これも実装の時に判断する）。
