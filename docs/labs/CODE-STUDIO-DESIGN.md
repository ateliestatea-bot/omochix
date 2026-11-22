# OmochiX Code Studio 設計書(Phase 0。Phase 1 の実装に合わせて 2026-11-20 に更新)

作成日:2026-11-20。対象ブランチ:`feature/labs-code-studio-p1`(`origin/main` `c9ae6e2` から)。

コンセプト:**コードを書く → 動きを見る → 意味を理解する → 変更を試す → 教材の課題を解く**。

## 1. 現状監査(2026-11-20、読み取りだけ)

| 項目 | 状態 |
| --- | --- |
| 本番 | Theme **1.31.0** / OmochiX Core **1.10.0**(公開の `style.css`・`readme.txt`。`style.css` の更新は 11-19 17:22 GMT) |
| `main` | `c9ae6e2`。`style.css` と Labs の 6 ファイルの SHA が本番と同一 → **`main` = 本番**。Labs・SVG Motion Studio・Industry Hub・Design Library がすべて入っている |
| 作業中の PR | #72(Design Library の sitemap、theme 1.31.1)、#55(SVG Motion、Draft)。どちらも Labs のファイルは変えない |
| 公開中の Labs | JSON 整形・テキスト差分・robots.txt 生成の 3 つ。料金計算は `draft`(404) |
| `/learn/industries/`・`/design/` | 本番では 404(固定ページが非公開) |

### 既存のプレビュー技術と、重複・競合

| 既存 | 方式 | Code Studio との関係 |
| --- | --- | --- |
| Learn Motion Gallery / Composer / Practice | サイトが用意したコードを `<iframe sandbox="allow-scripts" srcdoc>` で表示。パラメーターを変えて試す | 入力は「サイトのコード + 数値」。利用者が自由に書いたコードは扱わない。**役割が違う**(Gallery = 見本を調整、Code Studio = 自分で書く) |
| SVG Motion Studio | 利用者の SVG を DOMPurify で無害化 → sandbox のフレームで表示。専用の CSP | **無害化の部品(DOMPurify 3.4.17)を再利用する**。Studio 自体は変えない |
| Design Library のデモ | 強制 CSP(`sandbox allow-scripts`)付きの専用エンドポイント。サイトのデモだけ | 利用者のコードは扱わない。変えない |
| Labs(`inc/labs.php`) | catalog.json によるデータ駆動、ツールごとのモジュール、共通の `shared.js`、Report-Only の CSP、公開ゲート | **Code Studio は Labs の 5 つ目のツール `code-studio` として追加する**(ルーティング・SEO・ゲート・計測を再利用)。初期の状態は `draft` |

過去の構想との統合:
- **Interactive Playground** → エディタ + プレビュー
- **Code Explainer** → タグ・プロパティの解説パネル
- **レスポンシブ修正ラボ** → 画面幅の切り替え + 教材 3 つ目の課題

## 2. 画面・UX(Phase 1)

```
[教材を選ぶ ▼]  [リセット] [コピー] [保存(.html)]
┌ 課題 ─────────────────────────────┐
│ 説明、手順、達成条件(チェックリスト)、[答え合わせ] │
└───────────────────────────────────┘
┌ HTML ────────┐ ┌ プレビュー  [PC][タブレット][スマホ] ┐
│ <textarea>    │ │  sandbox の iframe                    │
├ CSS ─────────┤ │                                       │
│ <textarea>    │ │                                       │
└───────────────┘ └───────────────────────────────────────┘
[変更前と比べる]  → 最初のコードとの差分 + 最初の表示と今の表示を並べる
┌ このコードで使っているもの ───────────────┐
│ <h1> 見出し… / color 文字の色…            │
└───────────────────────────────────────────┘
```

- **書く**:HTML と CSS の 2 つの入力欄(`<textarea>`、等幅)。Tab キーはフォーカス移動のまま(キーボード利用者を閉じ込めない)。
- **見る**:入力から 300 ミリ秒後に自動で再描画。幅は PC(100%)・タブレット(768px)・スマホ(375px)。
- **理解する**:今のコードに出てくるタグとプロパティを一覧にし、初心者向けの説明を出す(辞書はデータ)。
- **試す・比べる**:教材の最初のコードとの差分(Labs の差分の部品を再利用)と、最初の表示・今の表示の 2 つのプレビュー。
- **解く**:課題ごとに達成条件(データ)を機械的に判定し、満たした条件にチェックを付ける。結果は `role="status"` で読み上げる。
- **持ち帰る**:リセット(確認つき)・コピー・`.html` として保存(ブラウザーの中で生成)。
- 保存はしない。再読み込みで教材の最初の状態に戻る(Phase 4 で進捗の保存を検討)。

## 3. 言語別の対応(言語アダプタ)

アダプタは「言語 id → { ファイルの種類、解析、実行方式、判定に使える条件 }」の対応表。教材のデータは `language` でアダプタを選ぶ。

| 言語 | 実行の方式 | 段階 | 備考 |
| --- | --- | --- | --- |
| `html-css` | 無害化 → `sandbox=""` の iframe(スクリプトなし) | **Phase 1** | 本書の対象 |
| `javascript` | `sandbox="allow-scripts"` の iframe + 強制 CSP(通信なし)+ Worker で時間制限 + `console` の中継 | Phase 2 | 別オリジンのエンドポイント(Design Library のデモと同じ形)を検討 |
| `typescript` / `react` | ブラウザーの中で変換(型の除去 / JSX)してから `javascript` と同じ枠で実行 | Phase 3 | 変換器の大きさと供給網を審査する |
| `python` | WebAssembly(Pyodide など)を Worker で実行。時間・メモリの上限 | Phase 3 | 大きいので遅延読み込み。自己ホスト |
| `c` / `cpp` / `java` | 初めは**解説だけ**(実行しない)。実行は、分離した実行サービスを別のオリジンに置く場合だけ | Phase 4 以降 | **WordPress のサーバーでは実行しない** |

アダプタの共通インターフェース(Phase 1 は `html-css` だけ実装):

```
{ id, files: [{ key, label, mime }], limits,
  prepare(files) -> { doc, warnings },      // 無害化と文書の組み立て
  analyze(files) -> { tags, properties },   // 解説のための抽出
  checks: ['element', 'text', 'attribute', 'css', 'css-media'] }
```

## 4. 教材連動のスキーマ(`assets/data/labs/code-studio.json`、`schema_version: 1`)

```
{ schema_version, updated,
  glossary: { tags: { h1: { label, text } }, properties: { color: { label, text } } },
  lessons: [{
    id,            // 教材 ID(将来の Learn 連動のキー。不変)。例 "cs-html-001"
    slug, title, level: "beginner", language: "html-css", summary,
    learn: { path: "learn/development/web-basics" | null, lesson_id: null },   // Learn の教材へのリンクと、将来の教材 ID
    files: { html, css },                     // 最初のコード
    steps: [string],                          // 手順(説明)
    exercises: [{ id, prompt, hint, checks: [
      { type: "element",   selector, min },                       // 要素がある
      { type: "text",      selector, contains | min_length },     // 文字が入っている
      { type: "attribute", selector, name },                      // 属性がある(空でない)。正規表現はデータから受け取らない
      { type: "css",       selector, property, value? | not? },   // 宣言がある
      { type: "css-media", feature: "max-width" | "min-width", selector, property }  // その条件のメディアクエリの中の宣言
    ] }]
  }] }
```

- **1 対多**:Learn の 1 つの教材が複数の `lessons[].id` を参照できる。**1 対 1**:`learn.lesson_id`。
- Phase 4 で、Learn 側(Core の投稿メタ)に `code_studio_lesson` を持たせ、`/labs/code-studio/?lesson=<slug>` で開く。Phase 1 はクエリ `lesson` を読むだけ(値は教材の slug と照合する。任意の文字列を画面に出さない)。
- データはサーバーで検証する(`omochix_labs_studio_view()`)。未知の `type`・不正な値の行は捨てる。

## 5. セキュリティ設計

原則:**利用者のコードは WordPress の DOM・Cookie・権限で一切動かさない**。サーバーにも送らない。

| 脅威 | 対策(Phase 1) |
| --- | --- |
| XSS(親ページでの実行) | 入力は `textarea.value` と `textContent` だけで扱う。親の DOM に `innerHTML` で入れない。解説・判定は `DOMParser` の不活性な文書で行う |
| プレビューでのスクリプト実行 | `<iframe sandbox="">`(`allow-scripts` なし・`allow-same-origin` なし)。加えて DOMPurify で `script`・イベント属性・`javascript:` を除去(二重の防御) |
| iframe からの脱出・親の操作 | sandbox により不透明なオリジン。`allow-top-navigation`・`allow-popups`・`allow-forms`・`allow-modals` なし |
| 外部への通信(画像・フォント・CSS・ビーコン) | 文書の先頭に `<meta http-equiv="Content-Security-Policy" content="default-src 'none'; style-src 'unsafe-inline'; img-src data:; form-action 'none'">`。CSS(入力欄と `style` 属性の両方)の `@import`・`@font-face`・`url()`(`data:image` 以外)・`image-set()` など・`\` のエスケープ・`http:` などのスキームは取り除く |
| フレーム内の遷移による通信(`<a href>`・`meta refresh`) | **`href` は一切許可しない**(リンクは表示だけ。押しても移動しない)。`srcdoc` の文書では `#top` も親ページの URL として解決され、枠の中にサイト自身を読み込むため(ブラウザー試験で検出)。`<base>` で直す案は、親ページの CSP(`base-uri 'self'`)に触れるので採らない。`meta`・`base`・`link`・`iframe`・`object`・`embed`・`form` などは禁止タグ |
| `</style>` による抜け出し | CSS の中の `<` を取り除く。CSS は `textContent` 相当で組み立てる |
| DoS(巨大な入力・重い CSS) | HTML・CSS は各 50KB まで。要素は 1,500 個まで。再描画は 300 ミリ秒の間引き。プレビューは `prefers-reduced-motion` のときアニメーションを止める規則を入れる |
| サーバーでの実行・保存 | しない。エンドポイントを追加しない。REST・Ajax・DB の書き込みなし |
| 計測への入力の混入 | 既存の許可リスト(`core/events.js`)だけ。コードの内容は送らない |
| AI の解説(将来) | 分離した API(別オリジン、サーバー側でキーを保持)。**API キーを WordPress の公開コードに入れない** |
| 供給網 | 新しい外部ライブラリを足さない(同梱済みの DOMPurify 3.4.17 を使う) |

親ページの CSP(Labs の Report-Only)は変えない。`srcdoc` の文書は親の方針を継承し、その上に `meta` の強制の方針が加わる。

検証する項目(Phase 1 のテスト):スクリプトが動かない、外部への要求が 0、親の `window` に触れない、`top` の遷移が起きない、上限を超えた入力を拒否、キーボード・ダークモード・`prefers-reduced-motion`・375px。

## 6. ロードマップ

| 段階 | 内容 |
| --- | --- |
| **1(本書)** | HTML/CSS のエディタ、隔離プレビュー、画面幅、前後の比較、タグとプロパティの解説、リセット・コピー・保存、教材 3 件、課題と達成条件、Learn へのリンクと教材 ID |
| 2 | JavaScript の安全な実行(`allow-scripts` + 強制 CSP + 時間制限 + `console` の中継)。行・関数の単位の解説 |
| 3 | 処理の流れの可視化。TypeScript・React の変換、Python(WebAssembly)。C++・Java は解説だけ |
| 4 | Learn の教材から演習を開く(教材 ID の連動)。学習の履歴と進捗(まず端末の中、次にアカウント) |
| 5 | AI によるコードの解説・改善の提案(分離した API)。GitHub 連携・制作物の公開 |

## 7. 本番公開の前に残ること

- `draft` のまま merge し、公開は catalog の `status` を `published` に変える別のリリースで行う(公開ゲートが、公開前の言及を検出する)。
- 実機:iPhone の Safari(入力欄・プレビューのスクロール)、VoiceOver。
- 教材の文章の校正と、Learn の教材側のリンクの用意。
- カテゴリ:今は `dev`。学習用のカテゴリを足すかは、公開のときに決める。
- 実装と試験の結果、残課題の最新版は `CODE-STUDIO-PHASE1-REPORT.md`。
