# OmochiX Code Studio:Phase 0〜1 報告書(2026-11-20)

- 作業場所:worktree `~/Documents/omochix-code-studio-wt`、ブランチ `feature/labs-code-studio-p1`(`origin/main` `c9ae6e2` から)。
- **コミット・push・PR・merge・本番への反映は行っていない**(すべて未コミットの作業ツリー)。本番の WordPress・DB・Core・Secrets は読み取りだけ。
- 設計の詳細は `CODE-STUDIO-DESIGN.md`。

## 1. 現状監査

| 項目 | 結果(2026-11-20、読み取りだけ) |
| --- | --- |
| 本番 | Theme **1.31.0** / Core **1.10.0**(1.28.0 ではない) |
| `main` | `c9ae6e2`。`style.css` と Labs の 6 ファイルの SHA が本番と一致 → `main` = 本番 |
| 作業中の PR | #72(Design Library の sitemap、theme 1.31.1)、#55(SVG Motion、Draft)。どちらも Labs のファイルは変えない |
| 重複・競合 | Learn の Gallery / Composer / Practice と Design Library のデモは「サイトが用意したコード」を表示するもので、利用者が書いたコードは扱わない。SVG Motion Studio は利用者の SVG だけ。**役割の重複なし**。Code Studio は Labs の 5 つ目のツールとして追加し、Labs の仕組み(catalog・ルーティング・SEO・公開ゲート・計測)と、同梱済みの DOMPurify 3.4.17 を再利用した |

## 2. 画面・UX(実装したもの)

教材の選択 → 説明と手順 → 課題のチェックリストと「答え合わせ」 → HTML / CSS の入力欄とプレビュー(PC・タブレット 768px・スマホ 375px)→「変更前と比べる」(最初の表示と今の表示、コードの差分)→ 使っているタグとプロパティの解説 → 最初に戻す・コピー・`.html` として保存。

- 入力から 300 ミリ秒後に自動で再描画。Tab キーはフォーカス移動のまま(閉じ込めない)。Ctrl/⌘+Enter で答え合わせ。
- 結果は `role="status"` で読み上げ。達成は色だけでなく「済 / 未」の文字でも示す。
- `/labs/code-studio/?lesson=<slug>` で教材を指定して開ける(教材データにある slug だけ)。

## 3. 言語別の対応

| 言語 | 方式 | 段階 |
| --- | --- | --- |
| HTML / CSS | 無害化 → `sandbox=""` の iframe(スクリプトなし) | **Phase 1(実装済み)** |
| JavaScript | `allow-scripts` の iframe + 強制 CSP + 時間制限 + `console` の中継。別オリジンを検討 | Phase 2 |
| TypeScript / React | ブラウザーの中で変換して JavaScript の枠で実行 | Phase 3 |
| Python | WebAssembly を Worker で実行(自己ホスト、遅延読み込み) | Phase 3 |
| C / C++ / Java | 解説だけ。実行は、分離した別オリジンのサービスを置く場合だけ。**WordPress のサーバーでは実行しない** | Phase 4 以降 |

教材データの `language` でアダプタを選ぶ。サーバーの検証は、今は `html-css` 以外の教材を表示しない。

## 4. 教材連動のスキーマ

`assets/data/labs/code-studio.json`(`schema_version: 1`)。教材は `id`(不変の教材 ID)・`slug`・`language`・`learn: { path, lesson_id }`・`files`(最初のコード)・`steps`・`exercises[{ id, prompt, hint, checks[] }]`。達成条件は `element`・`text`・`attribute`・`css`・`css-media` の 5 種類。

- Learn との 1 対多は `lessons[].id` の参照、1 対 1 は `learn.lesson_id`(今は `null`。Phase 4 で使う)。
- サーバー(`omochix_labs_studio_view()`)が検証し、未知の種類・不正なセレクタ・上限超えの教材は丸ごと表示しない。正規表現はデータから受け取らない。
- 教材 3 件:はじめての HTML(課題 2)、はじめての CSS(課題 3)、レスポンシブ修正ラボ(課題 2)。解説の辞書はタグ 24・プロパティ 27。

## 5. セキュリティ設計と検証結果

| 要件 | 実装 | 検証 |
| --- | --- | --- |
| WordPress の DOM 権限で実行しない | 入力は `value` / `textContent` だけ。判定と解説は `DOMParser` の不活性な文書 | ソース走査(`innerHTML`・`eval`・通信・保存の API が 0)。親ページのフラグが立たない |
| sandbox の iframe で隔離 | `<iframe sandbox="">`(`allow-*` なし)+ `srcdoc` | 無害化を迂回して悪意ある文書を直接入れても、スクリプト不実行・親の遷移なし・ポップアップなし・フォーム送信なし(3 ブラウザー) |
| 任意の JavaScript と外部通信の禁止 | DOMPurify の許可リスト + CSS の無害化 + 文書先頭の強制 CSP `default-src 'none'; style-src 'unsafe-inline'; img-src data:; form-action 'none'` | HTML 38 種・CSS 14 種の攻撃入力で、別オリジンへの要求 **0**、編集中のサーバーへの要求 **0**。DOMPurify を迂回しても CSP がすべての読み込みを止める |
| 危険な要素・URL の制限 | `script`・`style`・`iframe`・`object`・`embed`・`link`・`meta`・`base`・`form`・`svg`・`math`・メディア類を禁止。イベント属性・`srcset`・`href` を禁止。URL は小さな `data:image` だけ | 同上 |
| サーバーに保存しない・実行しない | エンドポイントの追加なし。REST・Ajax・DB の書き込みなし | Cookie・localStorage・sessionStorage が空、POST が 0 |
| サイズ・時間・メモリの制限 | HTML・CSS 各 50KB、要素 1,500 個、300 ミリ秒の間引き | 上限超えを拒否して直前の表示を保つ。1,400 段の入れ子 + 50KB の重い CSS でも 5 秒以内に応答。32 回の打鍵が数回の描画にまとまる |
| 計測に入力を混ぜない | 既存の許可リストだけ | 6 種類のイベントと語彙の値だけ。コードの内容は入らない |

検証の途中で見つけて直した点:

1. プレビューの中の `<a href="#…">` を押すと、枠の中にサイト自身のページが読み込まれた(`srcdoc` では `#` も親の URL として解決されるため)。→ **`href` を一切許可しない**ことにした(リンクは表示だけ)。
2. `style="…"` 属性が CSS の無害化を通っていなかった(CSP が止めるが、保存した `.html` には残る)。→ 同じ無害化を通す。
3. CSS の `image-set("https://…")` などで、関数名だけ消えて URL の文字列が残っていた(無害だが不要)。→ 引数ごと取り除く。スキーム(`http:` など)も取り除く。
4. 375px で教材の選択欄がページを横にはみ出させた。Safari で選択欄の高さが 44px に届かなかった。→ CSS を修正。
5. 何も除去していないのに「取り除きました」と表示された(DOMPurify が自分の `body` を数えるため)。→ 修正。

## 6. Phase 1 の実装と試験結果

変更したファイル(15。うちテーマ 7、試験 7、文書 2 のうち本書を含む):

- 追加:`assets/js/labs/core/codestudio.js`、`assets/js/labs/ui/code-studio.js`、`assets/data/labs/code-studio.json`、`template-parts/labs/content/code-studio.php`、`tests/labs/browser/code-studio.js`、`docs/labs/CODE-STUDIO-DESIGN.md`、本書。
- 変更:`inc/labs.php`(上限・モジュール・DOMPurify の読み込み・教材データの検証)、`assets/data/labs/catalog.json`(`code-studio` を **`draft`** で追加)、`assets/css/labs.css`(`.labs-studio*` を追記)、`tests/labs/` の 6 ファイル。
- 既存のツール・Industry Hub・Design Library・SVG Motion のファイルは変えていない。新しい外部ライブラリは足していない。

| 試験 | 結果 |
| --- | --- |
| Labs PHP(`tests/labs/run.php`) | 329 / 329 |
| Labs Node(`core.js` / `data.js`) | 150 / 150、41 / 41 |
| Code Studio ブラウザー(Chromium・WebKit・Firefox。隔離・通信・上限・機能・キーボード・ダークモード・reduced-motion・375px・axe) | **213 / 213** |
| 既存の Labs ブラウザー(`run.js` Chromium・WebKit / `firefox.js`) | 323 / 323、106 / 106 |
| ほかの試験(AI ツール、Learn の 3 種、ナビ、記事、ホーム、SVG Motion の PHP 2 種、Industry Hub 10 種、Design Library の unit と catalog) | すべて合格 |
| PHP / JS の構文、ワークフローの方針 | 合格 |
| リリースゲート(`tests/release/release-gate.php`) | **24 / 30(想定どおり不合格)**。§8 の 1 |

出荷状態(catalog のまま)では `/labs/code-studio/` は 404(管理者でも)、一覧・検索・サイトマップ・ほかのツールのページに出ない・読み込まれないことを確認した。

試験側の変更で、判定の内容に関わるもの:公開ゲートの「draft のツール名がスクリプトに出ていないか」の照合で、英数字 1 語の名前(今回の "Code")を、識別子の一部(`labs-code` というクラス名)ではなく単語として照合するようにした。完全な名前・タイトル・slug の照合は変えていない。

未検証(PASS としない):iPhone の実機(入力欄とプレビューのスクロール、日本語入力)、VoiceOver、実際の WordPress 上での表示(今回は試験用のプレビューサーバー。QA サイトは 1.28.0 のままなので使っていない)、DOMPurify 自体の単体試験(Node では動かせないためブラウザー試験で確認)。

## 7. Phase 2 以降のロードマップ

| 段階 | 内容 |
| --- | --- |
| 2 | JavaScript の安全な実行(`allow-scripts` + 強制 CSP + 時間制限 + `console` の中継)。行・関数の単位の解説。ページ内リンクの対応 |
| 3 | 処理の流れの可視化。TypeScript・React の変換、Python(WebAssembly)。C++・Java は解説だけ |
| 4 | Learn の教材から演習を開く(教材 ID の連動)。学習の履歴(まず端末の中、次にアカウント) |
| 5 | AI による解説・改善の提案(**分離した API。API キーは WordPress の公開コードに入れない**)。GitHub 連携・制作物の公開 |

## 8. 本番公開の前に残ること

1. **リリースの準備**:`wp-theme/` を変える PR は CI のリリースゲートが必ず動き、今は「承認済みの範囲にないファイル 7 つ」で不合格になる。新しい版(例:Theme 1.32.0)のリリース設定・版番号・ロールバックの基準を、本番 1.31.0(PR #72 が先に出るなら 1.31.1)を基準に用意する。別承認。
2. `draft` のまま出し、公開は catalog の `status` を `published` に変える別のリリースで行う。
3. 実機:iPhone の Safari、VoiceOver。実際の WordPress(QA サイトを 1.31.0 にそろえてから)での確認。
4. 教材の文章の校正。Learn の教材側からのリンクの用意(`learn/development/web-basics` が公開されていればリンクが出る)。
5. Labs の CSP を強制に切り替えるときは、`srcdoc` の枠が親の方針を継承する点を再確認する。
6. カテゴリ(今は「開発」)と、OG 画像の用意。
7. PR #72 と同時に進める場合、`assets/data/labs/`・`inc/labs.php` は重ならないが、版番号とリリース設定は重なる。順番を決める。
