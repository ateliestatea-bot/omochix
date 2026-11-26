# OmochiX Code Studio:Phase 1 最終検証・リリース準備(2026-11-20)

- 対象:worktree `~/Documents/omochix-code-studio-wt`、ブランチ `feature/labs-code-studio-p1`(`origin/main` `c9ae6e2` から、未コミット)。
- 本番・`main`・PR #72・ほかの worktree は変えていない。push・PR・merge もしていない。Code Studio は catalog で `draft` のまま。
- 実装と設計は `CODE-STUDIO-PHASE1-REPORT.md` と `CODE-STUDIO-DESIGN.md`。本書はその後のレビュー・QA・リリース準備。

## 1. セキュリティ・コードレビュー

| # | 観点 | 結果 |
| --- | --- | --- |
| 1 | DOMPurify と CSS 無害化の迂回 | **CSS 無害化に迂回 2 件を確認して修正**(下の A・B)。HTML 側の迂回は見つからず |
| 2 | sandbox と CSP | `sandbox=""`(`allow-*` なし)、`srcdoc`、先頭の強制 CSP。無害化を迂回しても各層が単独で止める(3 ブラウザー) |
| 3 | CSS の難読化・エスケープ・外部 URL | `\` は全削除、コメント分割は CSS として無効。再結合の迂回は A で修正 |
| 4 | HTML の危険な属性・要素 | 許可リスト方式。`src` に SVG の data URL が通っていた点を修正(C)。`id`/`name` の DOM 上書きは DOMPurify が除去 |
| 5 | iframe 内のリンク・フォーム・遷移 | `href`・`form`・`formaction`・`target` は除去。押しても遷移・送信なし |
| 6 | 過大入力・負荷 | 50KB / 1,500 要素で拒否。正規表現は 50KB の最悪入力で 100 ミリ秒以内。重い CSS(巨大なぼかし等)は自分のタブが重くなるだけ(既知の制限) |
| 7 | draft の公開ゲート | 出荷状態で 404・一覧・検索・サイトマップ・ほかのツールのページに出ないことを、単体試験・ブラウザー試験・実 WordPress で確認 |
| 8 | 既存 Labs ツールへの影響 | 公開 3 件は 200 のまま、Code Studio のスクリプトや DOMPurify を読み込まない。token-cost-calculator は 404 のまま |

### 見つけて直したもの

- **A(中)CSS 無害化の再結合**:削除した結果、残りの文字がつながって `@import` や `url(` が復活した。例:`@im@im@im@import;port;port;port "//example/a.css";`、`uexpression(rl(//example/x)`。プレビューは CSP が止めるので外部通信は起きないが、**保存した `.html` には CSP が無い**ため、外部の読み込みが残り得た。→ 変化しなくなるまで繰り返し、12 回で収束しなければ CSS 全体を捨てる。
- **B(中)`@import` の取りこぼし**:URL に `{` や `}` を含む `@import "//example/a{.css";` が残った。影響と対処は A と同じ(次の `;` まで必ず取り除く)。
- **C(低)`<img src>` に SVG の data URL が通る**:DOMPurify は `img` の `data:` を独自に許可するため、設計の「png・gif・jpeg・webp だけ」と食い違っていた(画像としての SVG はスクリプトも外部読み込みも動かないので実害はない)。→ フックで `src` を判定。`cite` 属性は許可リストから外した。
- **D(機能の不具合)通常の属性が消えていた**:DOMPurify は URL 用の正規表現を全属性に当てるため、`type`・`colspan`・`rowspan`・`width`・`height`・`start`・`min`・`max`・`lang`・`tabindex` などが除去されていた(`<input type="checkbox">` がテキスト入力になる)。→ URL でない属性を `ADD_URI_SAFE_ATTR` に指定。
- **E(整理)** 教材データの `pattern` から正規表現を作る分岐がブラウザー側に残っていた(サーバーは渡さないので到達しない)。→ 削除。

追加した試験:Node 5 件(155)、ブラウザー 3 件 × 3(222)。

### 直していないもの(報告だけ)

- **公開ゲートの範囲**:`labs_release_state_problems()` の「到達できない」確認は token-cost-calculator 専用。Code Studio の draft は `tests/labs/run.php` の 2 件(404、一覧・検索・サイトマップ・一覧の HTML)と、スクリプト文言の確認、ブラウザー試験で見ている。構造化データ・関連リンク・PHP の文言まで含む汎用化は別作業として提案する(現状の漏れは無い:テーマ内の「Code Studio」は自身のファイルと catalog・`inc/labs.php`・`labs.css` だけ)。
- **単語照合の変更の影響**:英数字 1 語の名前("Code")は単語として照合する。`CodeStudio` のように続けて書いた文言は 1 語照合では検出しないが、完全な名前("Code Studio(HTML/CSS)")と slug の照合は従来どおり。
- **静的ファイルの直接 URL**:draft でも `catalog.json`・`code-studio.json`・JS は URL を直接打てば取得できる(本番の token-cost-calculator も同じ:`catalog.json` と JS が 200)。ページ・検索・サイトマップ・リンクからは到達しない。隠す必要があれば別途サーバー設定が要る。
- **reduced-motion**:学習者が `!important` と詳細度の高いセレクタで書いたアニメーションは、プレビュー内の停止ルールより優先される(本人のコードだけに影響)。
- プレビューの文書を直接差し替えた場合(無害化と CSP の両方を外した試験)に、枠の中だけ meta refresh が動くブラウザーがある。通常の経路では `meta` は除去される。

## 2. WordPress QA

QA サイト:Local の `omochix-labs-qa`(`http://omochix-labs-qa.local`、この Mac の中だけ)。

- 開始時:Theme 1.28.0 / Core 1.9.3。バックアップ:`~/Documents/omochix-code-studio-backups/labs-qa-before-cs-20261120/`(`db.sql`・`theme-1.28.0.tgz`・`core-1.9.3.tgz`)。
- 更新後:Theme 1.31.0 + Code Studio(この worktree)/ Core 1.10.0。QA だけの変更:固定ページ 3 件を追加(`learn/svg-motion`・`learn/industries`・`design`)、**QA の catalog のコピーだけ** `code-studio` を `published` に変更。
- draft のまま(更新直後):`/labs/code-studio/` 404、一覧に出ない、サイトマップは公開 3 件だけ、ほかのツールのページに記載なし。

| 確認 | 結果 |
| --- | --- |
| Code Studio のブラウザー試験(表示・編集・リアルタイムプレビュー・幅切り替え・比較・解説・教材 3 件・答え合わせ・保存・リセット・`?lesson=`・隔離・CSP・上限・キーボード・ダークモード・reduced-motion・375px・axe) | **201 / 201**(Chromium・WebKit・Firefox。公開ゲートの 21 件は QA では対象外) |
| コピー | 実行と完了の通知まで確認。QA は http のためクリップボードの中身は読み戻せない(スタブ環境では中身まで確認済み) |
| 既存ページ(`/`・`/labs/`・公開 3 ツール・`/learn/`・SVG Motion・業種別ハブ・Design Library)1440px / 375px、Chromium・WebKit | すべて 200、スクリプトエラー 0、横はみ出し 0 |
| `/labs/token-cost-calculator/` | 404 のまま |

- 既存の Labs ブラウザー試験・SVG Motion・Industry Hub・Design Library の専用スイートは、スタブ環境で実行(Labs 323 / 106、ほかは前回合格。今回の修正は Code Studio の 2 ファイルと試験だけ)。QA では上の表示確認まで。
- 未確認:ログイン中の管理者での表示(スタブ環境では 404 を確認)、Design Library・SVG Motion の操作を伴う試験の QA 上での実行。
- QA は今も Code Studio が公開状態。draft に戻すには、QA の `wp-content/themes/omochix-theme/assets/data/labs/catalog.json` の `code-studio` の `status` を `draft` にする。

## 3. iPhone・VoiceOver

**未検証**(実機に触れられない。承認済みの検証環境も無い)。Playwright の WebKit 375px は合格だが、実機の代わりにはならない。

実機で確認する項目(QA サイトを iPhone から開くには、承認済みの経路が必要):

| # | 項目 | 見るところ |
| --- | --- | --- |
| 1 | 375px の表示 | 横スクロールが出ない。教材の選択欄・3 つのボタンが 44px 以上で押せる |
| 2 | 日本語入力 | HTML 欄で変換中に表示が乱れない。確定後 0.3 秒ほどでプレビューが変わる。変換候補が欄を隠さない |
| 3 | エディタ内スクロール | 長いコードで欄の中だけが縦に動く。端まで行くとページが動く(閉じ込められない) |
| 4 | プレビューの操作 | 枠の中をスクロールできる。リンクを押しても移動しない。「タブレット」で枠が横スクロールになる |
| 5 | フォーカス移動 | キーボードを閉じた後に位置が飛ばない。「答え合わせ」後に結果が見える位置にある |
| 6 | 保存・コピー | 「ファイルに保存」でダウンロードできる。「コピー」後にメモへ貼り付けられる |
| 7 | 画面の回転・文字サイズ拡大 | 崩れない |
| 8 | VoiceOver | 入力欄 2 つの名前、幅の切り替え(ラジオ)、課題の「済 / 未」、答え合わせの結果の読み上げ、プレビュー枠の名前(「プレビュー」) |
| 9 | ダークモード | 文字が読める。プレビューは白地のまま |

## 4. PR #72 と統合の準備

- `origin/main` は `c9ae6e2` のまま(本番と同じ)。
- PR #72(`fix/design-sitemap-closed-404`、head `cf6ac92`):OPEN、MERGEABLE、未マージ。CI は「CI change guard」だけ FAILURE(ほかは SUCCESS)で BLOCKED。
- **ファイルの重なり:なし**。PR #72 は `inc/design-library.php`・`style.css`・リリース設定・CI。Code Studio は `inc/labs.php`・`assets/**/labs/**`・`template-parts/labs/`・`tests/labs/`。
- **重なるのはリリースの定義**:`style.css` の版番号、`tools/release/release.json`、`tests/release/expected/release.json`、`tools/ci/required-checks.json`、`.github/workflows/release.yml`。

### 1.31.1 を先に出す場合の手順(案)

1. PR #72 を merge し、1.31.1 を本番へ(別承認・運用者の作業)。
2. 本番を読み取りだけで再確認(1.31.1、`main` と一致)。
3. この worktree で `git fetch` → 未コミットの変更を退避(バックアップは `~/Documents/omochix-code-studio-backups/`)→ `origin/main` へ rebase。ファイルの競合は出ない見込み。
4. 新しい版のリリース設定を作る(下)。
5. 全試験 + リリースゲート 30/30 → PR(別承認)。

### リリースゲート 30/30 に要る変更(版番号は未確定。ここでは X と書く)

今の 6 件の不合格は、すべて「現在のリリース設定(1.31.0)にない 7 ファイル」が原因。PR #72 が 1.31.1 でしたのと同じ形の変更が要る。

| ファイル | 内容 |
| --- | --- |
| `tools/release/baseline/omochix-theme-1.31.1.sha256` | その時点の本番(1.31.1)の全ファイルの SHA-256 |
| `tools/release/configs/theme-1.31.1.json`、`tests/release/expected/theme-1.31.1.json` | 今の `release.json` と期待値を保存 |
| `tools/release/release.json` | 新しいリリース。Core 1.10.0 は変更なし。手順 8:追加 4 → 置換 4 |
| `tests/release/expected/release.json` | 手で記録する期待値(追加 4・置換 4・手順 8・パッケージのファイル数 +4・変更パスの SHA-256・ロールバック 4 件) |
| `tests/release/test_release_gate.py`、`test_release_tools.py`、`tools/ci/required-checks.json`、`.github/workflows/release.yml`、`tools/release/README.md` | 1 つ前のリリースを CI の対象として登録(「CI change guard」の承認が要る) |
| `wp-theme/omochix-theme/style.css` | 版番号 |

- 追加(4):`assets/data/labs/code-studio.json`、`assets/js/labs/core/codestudio.js`、`assets/js/labs/ui/code-studio.js`、`template-parts/labs/content/code-studio.php`
- 置換(4)の順:`inc/labs.php` → `assets/data/labs/catalog.json` → `assets/css/labs.css` → `style.css`(切り替え)。追加を全部置いてから置換する(`inc/labs.php` が新しいデータを読むため)。途中のどの状態でも、catalog が draft なのでページには出ない。
- ロールバックの基準:置換した 4 ファイルを 1.31.1 に戻す(`style.css` を最初、`inc/labs.php` を最後)。追加した 4 ファイルは残っても読まれない(catalog に無ければ到達しない)。DB の変更・パーマリンクの再保存は不要(新しいルールなし)。
- 現在の設定の「Labs・SVG Motion のファイルを含めない」規則は、新しい設定では上の Labs の 7 ファイルを名指しで許可する。
- 公開ゲート:`code-studio` は `draft` で出す。`published` への変更は catalog 1 ファイルだけの別リリース。

### 推奨する版番号(確定は運用者)

- **Theme 1.32.0**(1.31.1 の次。新機能を閉じたまま出すので、1.29.0・1.31.0 と同じ扱いのマイナー版)。Core は 1.10.0 のまま。
- PR #72 を出さずに Code Studio を先にする場合も 1.32.0 とし、PR #72 側を取り込み直す必要がある(推奨しない)。

## 5. 判定

- 実装とセキュリティ:リリース候補にできる状態(見つかった問題は修正・再検証済み)。
- **リリース候補としては NO-GO(条件付き)**。残り:(1) 版番号の確定とリリース設定(ゲート 24/30 → 30/30)、(2) PR #72 との順番、(3) iPhone・VoiceOver の実機確認、(4) 教材の文章の校正。
