# OmochiX Learn v2 — 設計仕様（Phase 1）

更新日：2026年10月17日 / ブランチ：`feature/learn-interactive-v1` / 基点：`837681a`

Learnを「読む学習ページ＋プロンプト集」から、**見て・試して・AIで作れる**インタラクティブなプラットフォームへ進化させるための設計と実装範囲をまとめます。参考にしたのは動く見本をプロンプトと一緒に渡せる外部ギャラリーの考え方ですが、UI・コード・データはOmochiX独自に設計しています。

## 1. 情報アーキテクチャ

```
/learn/                         Learnトップ（既存ページ・URL不変、UIを刷新）
├─ /learn/motion/               Motion Gallery 一覧（新設・WordPress固定ページ「learn/motion」）
│   └─ /learn/motion/{slug}/    見本の詳細（新設・rewrite rule。実体は固定ページ + カタログJSON）
├─ /learn/{section}/...         既存のLearn記事（page-learn-article.php、変更なし）
└─ /prompts/                    既存のPrompt Library（URL・テンプレート不変）
```

- **既存URLはすべて維持**します。新設は `/learn/motion/` 配下のみです。
- `/learn/motion/` は通常の固定ページとして作成します（親＝Learn、slug＝`motion`）。公開されるまでLearnトップのカードやリンクは出ません（既存の `omochix_get_published_page_url()` と同じ振る舞い）。
- 見本の詳細URLはテーマ側の rewrite rule で `index.php?pagename=learn/motion&omx_motion={slug}` に解決します。見本はWordPress投稿ではなく、テーマ同梱の `catalog.json` が正です（`omochix-core` は変更しません）。
- 将来のPlayground（`/learn/playground/`）・OmochiX Video連携はデータ側に予約フィールドだけ持たせ、画面は作りません。

## 2. 画面構成

### 2.1 Learnトップ（`page-learn.php`）

1. **Hero**：eyebrow「LEARN」、見出し、リード、CTA2つ（「動く見本を見る」→ `/learn/motion/`、「プロンプトを探す」→ `/prompts/`）。CTAはリンク先が公開されている場合のみ表示。
2. **見て試す**：Motion Galleryの注目見本3件を動くプレビューつきで表示。`/learn/motion/` が未公開なら非表示。
3. **学ぶ**：カテゴリカード（既存6枚＋「動く見本」）。
4. **Learning Path**：既存の6ステップをそのまま維持。
5. **使う**：Prompt Libraryの最新3件（公開済みのみ）。0件なら非表示。
6. **作る（準備中）**：Playground / Video連携の予告。リンクなし・`aria-disabled`。
7. 固定ページ本文（既存のエディタ本文）。

### 2.2 Motion Gallery 一覧（`page-learn-gallery.php`）

- パンくず Home / Learn / 動く見本。
- 見出し・リード・件数（`aria-live`）。
- **フィルターバー**：カテゴリチップ（`?cat=`）、難易度（`?level=`）、技術（`?tech=`）、キーワード検索（`?q=`）、「すべて一時停止」トグル、リセット。GETパラメータでSSR絞り込み、JSがあれば即時絞り込み＋`history.replaceState`でURL同期。
- **カテゴリごとのセクション**：カード3列（PC）/2列（タブレット）/1列（スマホ）。
- **カード**：プレビュー（16:9）、番号、名前（日/英）、一行説明、難易度バッジ、再生/一時停止ボタン。カード全体が詳細へのリンク。JSがある場合は詳細をダイアログで開き、URLを `pushState` で詳細URLへ更新（戻るで閉じる）。
- JSなし：プレビューは静的なポスター（CSSのみ）＋詳細ページへのリンク。

### 2.3 見本の詳細（同テンプレート、`omx_motion` あり）

- パンくず Home / Learn / 動く見本 / 名前。
- ヘッダー：カテゴリ、番号、名前（日/英）、難易度、タグ。
- **大きなプレビュー**：再生/一時停止、拡大（`<dialog>` で画面幅いっぱい）。
- **AIへの頼み方**：一言の頼み方（ask）、動きの仕様（spec）、使いどころ（use）、「プロンプトをコピー」。
- **コード**：`<details>` 内に HTML / CSS / JS を表示、「コードをコピー」。見本で動いているものと同じコード。
- 関連：同じカテゴリの前後の見本、関連プロンプト（Prompt Library、公開済みのみ）、関連AIツール（公開済みのみ）、関連Learn記事（公開済みのみ）。
- 一覧へ戻るCTA。
- `?omx_partial=1` を付けると詳細の中身だけを返し、一覧のダイアログがそれを読み込みます（マークアップはPHP側の1か所だけ）。

## 3. コンポーネント設計

| コンポーネント | 役割 | 実装場所 |
|---|---|---|
| `motion-filter` | チップ／セレクト／検索／一時停止トグル。フォームとして JS なしでも動く | `template-parts/learn-gallery/filter-bar.php` |
| `motion-card` | 一覧カード。`data-cat` `data-level` `data-tech` `data-search` を持ち、JS は `hidden` を切り替えるだけ | `template-parts/learn-gallery/card.php` |
| `motion-preview` | ポスター＋遅延生成される sandbox iframe。再生/一時停止/拡大ボタン | `template-parts/learn-gallery/preview.php` + `learn-gallery.js` |
| `motion-detail` | 詳細本文（ヘッダー、プレビュー、プロンプト、コード、関連） | `template-parts/learn-gallery/detail.php` |
| `motion-dialog` | 一覧上の詳細ダイアログ。`fetch(url?omx_partial=1)` で中身を取得 | `page-learn-gallery.php` + JS |
| `motion-expand` | プレビュー拡大ダイアログ | 同上 |
| コピー | `[data-omx-copy][data-omx-copy-target]` をイベント委任で処理。成功/失敗は `aria-live` に通知 | `learn-gallery.js` |
| `learn-hub-v2` | Learnトップの各セクション | `page-learn.php` |

### プレビューの仕組み

- 見本は **固定サイズのステージ（既定 320×180）** に対して作り、iframe内のランタイムが `scale()` で枠に合わせます。カードでも拡大表示でも同じ見た目になります。
- iframe は `sandbox="allow-scripts"`（同一オリジン権限なし）で、`srcdoc` にランタイムCSS/JS＋見本のHTML/CSS/JSを埋め込みます。見本のコードは親ページのCSS・JSから完全に隔離されます。
- iframe の生成は `IntersectionObserver` で可視範囲に近づいたときだけ行い、画面外に出たら一時停止します。
- **再生/一時停止**：親→iframe に `postMessage({type:'omx:play'|'omx:pause'})`。iframe側は `document.getAnimations()` の一括 pause/play と、JS見本用のタイマー停止で対応します。
- **ライト/ダーク**：親の `html[data-theme]` を iframe に渡し、切替時は `MutationObserver` で全iframeに通知します。iframe内の配色はOmochiXのデザイントークンと同じ値を使います。
- **prefers-reduced-motion: reduce**：自動再生せず、代表フレーム（最初の状態）で停止したまま「再生」ボタンを出します。利用者の明示操作でのみ動かします。
- **キーボード/支援技術**：ボタンはすべて `<button>`、状態は `aria-pressed`、iframe は `title` を持ち `tabindex="-1"` でフォーカス順から外します。ダイアログはネイティブ `<dialog>`（Esc・背景クリックで閉じ、フォーカスを戻す）。

## 4. データモデル

`wp-theme/omochix-theme/assets/data/motion/catalog.json`（スキーマ：`catalog.schema.json`）

```json
{
  "version": 1,
  "updated": "2026-10-17",
  "stage": { "width": 320, "height": 180 },
  "categories": [
    { "slug": "entrance", "name": "登場・退場", "description": "…", "order": 1 }
  ],
  "items": [
    {
      "id": "001",
      "slug": "pop-in",
      "category": "entrance",
      "name": { "ja": "ポップイン", "en": "Pop-in" },
      "summary": "ポンと弾んで現れる",
      "description": "見本で起きていることの説明",
      "level": "beginner",
      "tech": ["css"],
      "tags": ["登場", "ばね"],
      "featured": true,
      "preview": { "html": "…", "css": "…", "js": "" },
      "prompt": { "ask": "…", "spec": ["…"], "use": "…" },
      "related": { "prompts": ["prompt-slug"], "tools": ["ai-tool-slug"], "learn": ["learn/development"] },
      "playground": { "params": [ { "key": "duration", "label": "長さ", "type": "range", "min": 200, "max": 2000, "step": 50, "default": 400, "unit": "ms", "css_var": "--d" } ] },
      "video": null
    }
  ]
}
```

- `id` は3桁の通し番号、`slug` はURLに使う一意の英小文字。`level` は Learn/Prompt と同じ `beginner|intermediate|advanced`。`tech` は `css|js` の配列。
- `preview` が見本の実体で、「コードをコピー」もここから組み立てます（見えているものと同じコード）。
- `related.*` は slug/パスだけを持ち、表示時にWordPress側で公開済みのものだけ解決します（存在しなければ出ません）。
- `playground` / `video` は将来用の予約フィールドです。Phase 1では読み込み時に形だけ検証し、画面には出しません。
- 読み込みはPHPが1回だけ行い（静的キャッシュ）、JSには `<script type="application/json">` で必要なフィールドだけ埋め込みます（`</script>` が混ざっても壊れないようエスケープ）。

## 5. 既存機能との連携と保護

- Prompt Library：見本→関連プロンプト（`related.prompts`）。逆方向（プロンプト詳細→関連する見本）はPhase 1Cで追加。
- AI Tools：見本→関連ツール（`related.tools`）。
- 共通シェル：`page-learn-gallery.php` を `inc/shell.php` の許可リストに追加するだけ。他テンプレートの挙動は変えません。
- `style.css` は変更せず、Learn v2専用の `assets/css/learn-v2.css` を Learn配下でだけ読み込みます。
- `omochix-core` は変更しません。`feature/learn-launch-content` は取り込みません。

## 6. 予定ファイル一覧

| 種別 | パス |
|---|---|
| 新規 | `docs/Learn-v2-Spec.md`（本書） |
| 新規 | `wp-theme/omochix-theme/assets/data/motion/catalog.json` / `catalog.schema.json` |
| 新規 | `wp-theme/omochix-theme/inc/learn-gallery.php` |
| 新規 | `wp-theme/omochix-theme/page-learn-gallery.php` |
| 新規 | `wp-theme/omochix-theme/template-parts/learn-gallery/{filter-bar,card,preview,detail}.php` |
| 新規 | `wp-theme/omochix-theme/assets/css/learn-v2.css` |
| 新規 | `wp-theme/omochix-theme/assets/css/learn-preview-runtime.css` / `assets/js/learn-preview-runtime.js`（iframe内ランタイム。PHPが srcdoc に埋め込む） |
| 新規 | `wp-theme/omochix-theme/assets/js/learn-gallery.js` |
| 新規 | `tests/learn-gallery/run.php`（単体テスト）/ `tests/learn-gallery/preview-server.php`（WordPressなしのブラウザ確認用サーバー） |
| 変更 | `wp-theme/omochix-theme/page-learn.php`（新UI） |
| 変更 | `wp-theme/omochix-theme/inc/shell.php`（許可リストに1行） |
| 変更 | `wp-theme/omochix-theme/functions.php`（require 1行） |

## 7. 実装順序

1. Phase 1B（本フェーズ）：カタログJSON＋スキーマ → `inc/learn-gallery.php` → テンプレート → CSS → JS → Learnトップ → テスト。
2. Phase 1C：Prompt詳細からの逆リンク、関連見本の表示、実機・ブラウザQA、`docs/Pre-Launch-Backlog.md` への項目追加、リリース（バージョン bump）。
3. Phase 2以降：Playground（パラメータ編集）、OmochiX Video連携、見本の追加（コンテンツ運用）。

## 8. テスト計画

- `php -l` 全PHPファイル、`catalog.json` のJSON妥当性とスキーマ検証（`tests/learn-gallery/run.php` 内のPHP検証器）。
- 単体（WordPressスタブ）：カタログ読込、必須項目/一意性/enum検証、絞り込み（cat/level/tech/q）、プロンプト文・コード文の組み立て、URL生成、未知slugの404、`omx_partial` 判定、JSON埋め込みのエスケープ。
- ブラウザ（分離したローカルWordPress）：320/390/768/1080/1081/1440px × ライト/ダーク、reduced-motion、JS無効、キーボードのみ操作（チップ→カード→ダイアログ→Esc）、コピーの内容一致、Learn/Prompts既存ページの回帰、`git diff --check`。
- 性能：一覧で同時に動くiframeが可視分だけであること、ページ外で停止すること。

## 8.1 Phase 1B 検証状況（2026年10月17日）

実施済み（`tests/learn-gallery/preview-server.php` によるWordPressなしのスタブ環境、Claude desktop 内蔵ブラウザ）：

- 単体テスト 83件、既存テスト（article-v2 197件 / editorial-home 26件）すべて通過。PHP 70ファイル lint、JS 7ファイル構文、JSON 妥当性、`git diff --check` 通過。
- 一覧：カード20件、可視範囲のみ iframe を遅延生成、画面外で一時停止、「すべて一時停止」トグル、カテゴリチップ／キーワード／難易度／技術の絞り込みとURL同期、SSR絞り込み（`?cat=ui&level=beginner`）とJS側の一致、空状態とリセット。
- 詳細：ダイアログ表示（`?omx_partial=1` フラグメント）、`pushState`／ダイアログ内の前後移動は `replaceState`、Esc・閉じるボタン・ブラウザの戻る／進むで一覧URLとダイアログ状態が同期、フォーカスはタイトル→閉じた後にトリガーへ復帰、Tab 40回でフォーカスがダイアログ内に留まる。
- プレビュー：`sandbox="allow-scripts"`、`src` なし（`srcdoc` のみ）、`contentDocument` は親からアクセス不可、カタログJSONに生の `<` を含まない、`omx:ready`／`omx:play`／`omx:pause`／`omx:theme` の往復、拡大表示、JSサンプル（カウントアップ）のループ動作。
- ライト／ダーク切替（iframe内にも反映）、reduced-motion（親：自動再生せず「再生」ボタンのみ、iframe内ランタイム：初期状態で `data-paused`、`omx:play` で再生）。
- 幅 375 / 768 / 1024 で横スクロールなし。375px のダイアログは全画面（開くアニメーションを無効化して修正済み）。
- コピー：Clipboard API が拒否される環境では `execCommand('copy')` にフォールバック（修正済み）。

未検証（WordPress実環境が必要）：固定ページ `learn/motion` 作成後の rewrite rule 反映と `/learn/motion/{slug}/` の解決、Slim SEO の title／description／canonical／robots フィルターの実出力、共通シェルのサイドバー（1081px以上）との同時表示、`get_template_part` の `$args` 受け渡し（WP 5.5+）、実際の Prompt／AI Tool／Learn 記事との関連リンク解決、キャッシュプラグインとの相性、実機 iOS Safari／Android Chrome、Lighthouse。

## 8.2 Phase 1C（2026年10月18日）

| 項目 | 実装 |
|---|---|
| Prompt詳細からの関連見本 | `omochix_learn_gallery_items_for_prompt()` が `related.prompts` を逆引きし、`single-prompt.php` に「このプロンプトと組み合わせる動く見本」セクション（カード＋プレビュー）を追加。該当する見本があり、かつ `/learn/motion/` が公開されているときだけ表示し、そのときだけ `learn-v2.css` / `learn-gallery.js` を読み込む。カタログ側に暫定リンク10件（`video-image-to-video-motion-direction` ×5、`video-text-to-video-shot-prompts` ×3、`development-web-performance-investigation` ×2）を設定。公開済みのPromptにしか効かないので、本番の slug と照合して確定する |
| 見出し階層 | 詳細パートの見出しをコンテキストで切替（単体ページ h1 > h2 > h3、ダイアログ内 h2 > h3 > h4）。見出し飛びなし |
| パンくず schema | `slim_seo_breadcrumbs_links` で一覧ページをリンクとして追加し、`single_post_title` で現在ページ名を見本名に差し替え。schema は「ホーム > Learn > 動く見本 > 見本名」の4階層 |
| カタログJSONの重複 | ランタイムCSS/JSとドキュメントテンプレートをページに1回だけ埋め込み、`learn-gallery.js` が各見本の断片（title/html/css/js）からテンプレートを埋めて `srcdoc` を組み立てる。PHP側は同じテンプレートで noscript 用の完全ドキュメントを生成（単一定義）。一覧HTML 231KB→127KB、カタログJSON 131KB→27KB |

試した上で採用しなかった案：ランタイムを外部ファイル参照（`<script src>`）にする方式。sandbox（不透明オリジン）の iframe からの読み込みが、Local の `.local` ドメイン（ループバック）環境で Chromium のローカルネットワークアクセス制限により失敗した。公開HTTPSでは通る可能性が高いが、環境依存を避けるためインライン方式を維持。

Phase 1C 検証（Local複製サイト）：一覧20件の再生、Prompt詳細の関連見本5件の再生とリンク、関連のないPromptではセクション・アセットとも出ないこと、詳細ページの見出し列 `h1 h2 h3 h3 h2 h2`、ダイアログ断片 `h2 h3 h4 h4 h3 h3`、BreadcrumbList 4階層、`debug.log` にエラーなし。

## 8.3 Phase 2B — Interactive Playground 試作（2026年10月18日、`feature/learn-playground-v1`）

見本の詳細（単体ページとダイアログの両方）に「調整する」パネルを追加し、パラメータを変えるとプレビュー・「コードをコピー」・「プロンプトをコピー」の内容がその場で更新されます。クライアントサイドだけで完結し、DB・URL構造・omochix-core は変更しません。

| 要素 | 実装 |
|---|---|
| データ | `playground.params[]` を正式化（`catalog.schema.json`）。型は `range` / `color` / `select` の3種（`text` は不採用）。`css_var`（`--p-*`）か `target: "js"` のどちらかに束縛し、既定値は従来の見た目と同じ。PHP の `omochix_learn_gallery_validate_params()` が key 一意・範囲整合・css_var 命名・プレビューCSSでの参照・select の CSS 値（`url(` 等は不可）を検証 |
| 値の正規化 | `omochix_learn_gallery_sanitize_params()`（PHP）と `sanitizeParams()`（JS）が同じ規則：range はクランプ＋step に丸め、color は `#rrggbb` のみ、select は許可値のみ、未知キーは破棄 |
| 生成ブロック | `omochix_learn_gallery_params_blocks()` / `paramBlocks()` が `:root{--p-a:…;--p-b:…}` と `window.omxParams={…};` を決定的な書式で生成。プレビュー文書テンプレートに `{{params_css}}`（`<style id="omx-params">`）と `{{params_js}}` を追加し、コピー用コードのテンプレート（`{{params_css}}` / `{{params_js}}`）とプロンプトのテンプレート（`{{params_text}}`）も PHP が定義して JSON で渡す。**プレビューと生成コードは同じブロックを同じテンプレートに差し込むため定義上一致** |
| iframe ランタイム | `omx:params` メッセージで `#omx-params` の内容を書き換え（親から来た値も名前・値の文法を再検証、`url(` 拒否）、`window.omxParams` を更新して `omx.onParams()` のリスナーを呼ぶ。見本側 API は `omx.param(key, fallback)` / `omx.onParams(fn)`。コピー用 shim も同じ API を提供 |
| UI | `template-parts/learn-gallery/playground.php`（ネイティブ range / color / select、`<output>` の現在値、既定に戻す、調整状態のリンクをコピー）。変更中は詳細記事に `is-modified` が付き、コピーボタンに「（調整済み）」を表示。状態は URL ハッシュ `#p=key:value,…` に `replaceState` で保持（クロール対象URLを増やさない）。`hashchange` にも追従し、拡大表示には現在値を引き継ぐ |
| 試作3件 | 001 ポップイン（1回の長さ・行き過ぎ・アイコン色・バッジの遅れ）、006 カウントアップ（目標値・長さ・進み方＝JS、数字の大きさ＝CSS）、017 流れるグラデーション（色×3・往復の長さ・角度） |
| テスト | `tests/learn-gallery/run.php` 126件（検証器の各規則、正規化、ブロック書式、PHP/JS テンプレート整合、コピー文の差分が `:root` ブロックだけであること、クライアントJSON） |

確認済み（スタブサーバー）：値変更がプレビューに反映（アイコン色・周期、カウントアップの目標値と文字サイズ）、ハッシュからの復元、既定に戻す、ダイアログ内のパネル、他の見本（17件）にはパネルが出ないこと。残課題：`learn-gallery.js` が 830 行を超えたため 2C で `learn-playground.js` への分割を検討、reduced-motion 時の挙動（値変更後も自動再生しない）の実機確認、スマホでの color 入力の使い勝手。

### 8.3.1 Phase 2C — 磨き込み・共有リンク・JS分割（2026年10月18日）

| 項目 | 内容 |
|---|---|
| JS分割 | `assets/js/learn-params.js`（純粋関数：正規化・ブロック生成・ハッシュ直列化・テンプレート充填。DOM に触れず Node からも読める）→ `learn-gallery.js`（プレビュー・フィルタ・ダイアログ・コピー、618行）→ `learn-playground.js`（「調整する」パネル）の順に `wp_enqueue_script` の依存で読み込む。gallery は `window.omochixMotionGallery`（`catalog` / `previewsOf(slug)` / `mount` / `sendParams` / `copyText` / `flash` / `expand`）を公開し、ダイアログに詳細を描画したあと `document` に `omx:motion:detail` イベント（`detail.container`）を送る。params ファイルが読めなくてもプレビューは既定値で描画される（CSS の `var(--p-x, 既定)` と `omx.param(key, 既定)` のフォールバック） |
| 一覧カード | 調整できる見本に「調整できる」バッジ（`.motion-card__adjustable`）。※ 2D で全見本が調整できるようになったため撤去 |
| 詳細レイアウト | 調整できる見本だけ `.motion-detail__studio` で囲み、1024px 以上ではプレビュー＋説明（`.motion-detail__stage`、sticky）とパネルを 3:2 の2カラムに並べる。ページでは `--shell-sticky-top`（shell）または `--header-height` を基準に、ダイアログ内は `top: 0`。調整できない見本の DOM は変更なし |
| 「（調整済み）」 | CSS の `::after` をやめ、コピーボタン内の `<span data-omx-modified hidden>` を JS で表示切替（`flash()` はボタン内の `[data-omx-label]` だけを書き換える）。range には `aria-valuetext`（例「1.5s」）、既定に戻すとパネルのライブリージョンに「既定の値に戻しました」 |
| 共有リンク | `[data-motion-detail]` の `data-url`（見本の正規URL）＋ `#p=…` を組み立てる（一覧URLや `?omx_partial` を含めない）。`navigator.share` がある端末は共有シート、キャンセル（AbortError）は無視、失敗時や非対応はクリップボードへコピーして「リンクをコピーしました」 |
| テスト | WordPress スタブを `tests/learn-gallery/stub.php` に共通化。`node tests/learn-gallery/params-parity.js` が `params-fixture.php`（PHP 側の正規化・ブロック・プロンプト行・コピー用コード/プロンプト全文）と `learn-params.js` の出力を 3見本 × 11ケース（範囲外・文字列・空・余計なキー・`url(` など）で全比較し、ハッシュの往復も確認（264件） |

確認済み（スタブサーバー、1280px と 375px）：ページとダイアログでパネルが束縛される（イベント経由）、2カラム／sticky、スマホでは縦積みで横スクロールなし、共有URLが正規URL＋ハッシュ、拡大表示に値を引き継ぐ、ダイアログを閉じると一覧URLに戻る、コンソールエラーなし。既知の制約：共有リンクのハッシュはサーバーに届かないため、OGP 画像や初期HTMLは既定の見た目のまま（JS 実行後に調整値が反映される）。

### 8.3.2 Phase 2D — 全20見本にパラメータを展開（2026年10月18日）

**2D-0 既定値の省略**：`omochix_learn_gallery_params_blocks()` と `learn-params.js` の `blocks()` は、既定値と同じパラメータを `:root{…}` / `window.omxParams` に書かない。見本の CSS は `var(--p-x, 既定)`、JS 見本は `omx.param(key, 既定)` で既定を持つので、触っていない見本は Phase 1 と同じ見た目（ダークテーマの `--primary` 等もそのまま）になり、コピーするコードには変えた値だけが入る。プロンプトの「調整した値」一覧は従来どおり全件を（既定）付きで出す。iframe ランタイムは `omx:params` のたびに `window.omxParams` を丸ごと置き換える（既定に戻した値は消える）。

**命名の統一**：`cycle`（1周期・片道の長さ）、`ease`（動き方：なめらか／やわらか／弾む など見本ごとの3択）、`distance`（移動量）、`stagger`（ずらし）、`color`（主色）、`size`（文字の大きさ）。倍率は単位なし（`press` 0.95 など）。色の既定はライトテーマの値（`#6e4bff` / `#2563eb` / `#d97706`）で、フォールバックはテーマ変数。

| # | 見本 | パラメータ |
|---|---|---|
| 002 | フェードアップ | cycle, distance, ease |
| 003 | スタガー | stagger, cycle, color, ease |
| 004 | マスクリビール | cycle, color, bar（バーの太さ） |
| 005 | タイプライター | cycle, blink（点滅）, color, size |
| 007 | マーカー線 | color, thick（太さ %）, cycle, ease ※ `color-mix()` で透明度を保つ |
| 008 | ワードスワップ | cycle, color, size, ease |
| 009 | トグルスイッチ | cycle, color（ON）, bounce |
| 010 | ボタンの押し込み | press（倍率）, ripple（倍率）, color, cycle |
| 011 | トースト通知 | cycle, distance, color（アイコン）, ease |
| 012 | スケルトン | shine（光の速さ）, cycle, glow（光の強さ） |
| 013 | スクロールでフェードイン | distance, stagger, cycle, ease |
| 014 | 追従ヘッダー | cycle, compact（縮んだ高さ）, shadow, color |
| 015 | 読了プログレスバー | color, thick, cycle |
| 016 | パララックス | cycle, depth（倍率）, sun, near |
| 018 | ふわふわブロブ | cycle（+1s/+2s で3つをずらす）, blur, c1, c2, c3 |
| 019 | ライトスイープ | cycle, glow, band（帯の幅 %）, angle, color（バッジ） |
| 020 | パーティクル | count / speed / size（JS、`omx.onParams` で粒を作り直す）, color（CSS の `color` を canvas が読む） |

合計 65 パラメータ（3件の試作と合わせて 78）。カタログは手書き整形を保つため文字列置換スクリプトで編集した（差分は各見本の `preview.css`（particles は `js` も）と `playground` の行だけ）。全見本が調整できるようになったので、2C で付けたカードの「調整できる」バッジは外し、ギャラリーのリード文（抜粋未設定時の既定文）に一言足した。

**テスト**：`run.php` に「全20見本が 3〜5 個のパラメータを持つ」「CSS の `var(--p-x, fallback)` のフォールバックが既定値と一致する（色はテーマ変数も可）」を追加（131件）。parity テストは 20見本 × 11ケース＝1760件。

## 9. 未決定事項・運用上の注意

- `/learn/motion/` 固定ページの作成・公開は運営者の操作です（本番DBは触りません）。
- 新しい rewrite rule はテーマ更新後に一度だけ `flush_rewrite_rules()` が走る仕組みにしていますが、反映されない場合は「設定 → パーマリンク」を保存し直してください。
- 見本の本数・カテゴリ名はPhase 1のサンプル（20件・5カテゴリ）です。コンテンツ方針に合わせて追加・改名できます（`catalog.json` のみ）。
- 詳細ページのOGP画像は未定（Default OGPに依存）。
- Playground・Videoの仕様は未定（予約フィールドのみ）。
