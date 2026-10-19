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
| 詳細レイアウト | 調整できる見本だけ `.motion-detail__studio` で囲み、1024px 以上ではプレビュー＋説明（`.motion-detail__stage`）とパネルを 3:2 の2カラムに並べる。※ 当初 stage を sticky にしたが、2E の実機確認で両カラムの高さがほぼ同じで sticky が効かない（効く余地がない）と分かり撤去。調整できない見本の DOM は変更なし |
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

### 8.3.3 Phase 2E — Local 複製環境での実機確認（2026年10月18日）

環境：Local の `omochix-learn-v2`（複製サイト、WP_DEBUG 有効）。テーマ symlink だけを `~/Documents/omochix-playground-v1/wp-theme/omochix-theme`（`feature/learn-playground-v1`、テーマ 1.20.0 ベース）に向け替え、プラグイン側は変更なし。symlink 切り替え直後は PHP opcache が旧パス解決を保持して `inc/learn-gallery.php` だけが古いまま配信されたため、サイトを停止→起動して解消（`opcache.revalidate_path` 既定値の挙動。本番の symlink デプロイでも同じ注意が要る）。

| 観点 | 結果 |
|---|---|
| 到達・PHP | `/learn/motion/`、各詳細、`?omx_partial=1`（`X-Robots-Tag: noindex`）、`/learn/`、`?cat=ui`、`/prompts/` がすべて 200、PHP notice / deprecated なし。スクリプトは params → gallery → playground の順で ver=1.20.0 |
| 一覧 | 20件、バッジなし。リード文は複製側の固定ページに抜粋が設定されているため抜粋（旧既定文）が出る＝仕様どおり |
| ダイアログ | カードから開いてパネルが束縛される。値変更でプレビュー srcdoc / コード / プロンプト / ハッシュ / 「（調整済み）」が更新、拡大表示に値を引き継ぐ、Esc で閉じると一覧URLに戻りカードにフォーカスが戻る |
| 単体ページ | 1280px で 3:2 の2カラム（571px / 381px）、375px では縦積みで横スクロールなし。`#p=…` 付きURLの直接オープンで入力・srcdoc・コードが復元 |
| 2D-0 | ダークテーマで既定のままの pop-in が `#omx-params` 空のままテーマ色（`#9b84ff`）で描画される。変更した値だけが `:root{…}` に入る |
| 20見本の総当たり | 各見本の全パラメータを端の値（range は max、color は `#ff0000`、select は最後の選択肢）にして、入力・srcdoc のブロック・コード・プロンプト（「（既定）」なし）・調整済み表示が一致。count-up の `target` は max 999999 が step 100 の格子に乗らず、ネイティブ range が 999900 に丸めて値がずれたため max を 1000000 に修正し、「(max−min)/step と (default−min)/step が整数」の検査を `run.php` に追加 |
| キーボード | Tab 順：1回の長さ → 行き過ぎ → アイコンの色 → バッジの遅れ → 共有 → プロンプトをコピー。range の → キーで 0.1 ずつ増え、`aria-valuetext`・表示値・ハッシュが追従。既定に戻すは既定時 disabled |
| 共有リンク | 正規URL＋ハッシュがクリップボード API に渡る（Local は http のため非セキュアコンテキスト→ `execCommand` 経路。Web Share は実機スマホ未確認） |
| コンソール | テーマ由来のエラーなし（唯一の 404 は確認作業で `?s=1` を付けて検索ページを開いた際のもの） |
| sticky | 2C で stage を sticky にしていたが、プレビュー列とパネル列の高さがほぼ同じで sticky が効く余地がなく撤去（表示に変化なし） |

スクリーンショット：`docs/learn-v2-qa/phase2-gallery-list-1280-light.jpg`、`phase2-dialog-gradient-shift-1280-light.jpg`、`phase2-detail-pop-in-1280-dark.jpg`、`phase2-detail-toast-375-light.jpg`。

**PR #25 レビュー後の修正**：(1) ダイアログ内で次／前の見本へ移るとき、履歴の push / replace をパネル束縛より前に行い、前の見本の `#p=` を引き継がないようにした。(2) パネルごとの `hashchange` リスナーは、パネルが文書から外れたら自分で解除する。(3) `#p=` 以外のアンカー（例 `#motion-pop-in-code`）は既定値のままでは消さない（値を変えると `#p=` に置き換わる）。(4) マーカー線は `color-mix()` 非対応ブラウザ向けに従来の `rgba()` 版 `background` を先に置き、既定の見た目を保つ（その環境では色の調整だけ効かない）。

未確認：prefers-reduced-motion での挙動（Local では OS 設定の切り替えが必要。ランタイムは値変更で再生状態を変えないので設計上は変わらない）、実機スマホでの Web Share シートと `input type="color"` の使い勝手、Lighthouse。確認作業上の注意：パネルの描画は `requestAnimationFrame` でまとめているため、バックグラウンドのタブでは入力イベントだけでは更新されない（ユーザー操作では起きない）。

### 8.3.5 一覧ページの埋め込み JSON の軽量化（2026年10月18日、`feature/learn-gallery-slim-json`）

本番 1.21.0 の計測で、一覧 `/learn/motion/` の HTML 259.5KB のうち埋め込みカタログ JSON が 103.1KB（40%）、その半分がパネルのある場面でしか使わないコピー用テンプレート（`codeTemplate` 22.2KB、`promptTemplate` 29.3KB）だった。

| 変更 | 内容 |
|---|---|
| PHP | `omochix_learn_gallery_client_json()` からテンプレートを外す（`params` は残す）。新規 `omochix_learn_gallery_item_json($item, $url)` が1見本分の `{codeTemplate, promptTemplate}` を `JSON_HEX_TAG` 付きで返す（params なしなら空文字） |
| テンプレート | `playground.php` がパネルの直後に `<script type="application/json" id="motion-<slug>-pg-templates" data-omx-item-templates>` を出す。ページでもダイアログのフラグメントでも同じ経路 |
| JS | `learn-playground.js` の `bind()` は `[data-motion-detail]` 内のその script を `JSON.parse` して使う。無い／壊れている場合はプレビュー・ハッシュ・既定に戻す・共有は動かし、コード／プロンプトの書き換えと「（調整済み）」は行わない |
| テスト | `run.php` 134件（client JSON にテンプレートが無いこと、`item_json` のプレースホルダーと `</script>` のエスケープ（`JSON_HEX_TAG` で `\u003C/script\u003E` になることを JS 見本で確認）、params なしで空）。parity fixture は `item_json` からテンプレートを取る（1760件） |

効果（スタブサーバー、JSON は本番と同一構造）：埋め込み JSON 103.1KB → 47.5KB（−54%）、gzip 後 20.0KB → 11.3KB。詳細ページは当該1件のテンプレート（約 2.4KB）が JSON から script タグへ移るだけで総量は同じ。ダイアログのフラグメントは 14.2KB → 16.1KB（gzip 3.7KB → 3.8KB）。

### 8.4 Phase 3B — Motion Composer 基盤（2026年10月19日、`feature/motion-composer-v1`）

「見る → 調整する → 組み合わせる → AIで制作する」の 3 段目。動く見本を 3 つまで選び、順番を決め、組み合わせをリンクとして共有する。Phase 3A の設計（§8.3 の後段、2026年10月19日の監査報告）に従い、既存の Gallery / Playground には手を入れず、公開 API の利用と 4 行のフック（`learn-gallery.js` の `registerWithin` / `unregisterWithin` 公開、`learn-gallery.php` の enqueue 判定、`functions.php` の require、`shell.php` の allow-list）だけで接続した。

| 要素 | 実装 |
|---|---|
| ページ | `/learn/motion/compose/`（`learn/motion` の子ページ、`page-learn-composer.php`）。ギャラリーの `^learn/motion/([a-z0-9-]+)` が `compose` を見本スラッグとして拾わないよう、`inc/learn-composer.php` が `init` 優先度 11 で専用ルールを先頭に登録し、`omochix_learn_composer_rewrite_version` で一度だけ flush。`template_include` 優先度 8。`noindex, follow`（状態 URL が無限に増えるため） |
| モデル | `{v:"1", slots:[{slug, params（既定と違う値だけ）, target, intent}]}`、最大 3。PHP `omochix_learn_composer_sanitize()` と JS `learn-composer-core.js`（純粋関数、Node でも読める）が双子。`target` / `intent` は 40 文字以内の平文（文字・数字・空白・句読点のみ）で、指示書にだけ使い、CSS / JS / srcdoc には流さない |
| 保存 | URL ハッシュ `#c=1;pop-in:cycle=1.5,color=ff0000;toast;gradient-shift:angle=45,t=hero,i=…`（`replaceState`）。`localStorage` の `omx-composer-draft` に最後の状態を 1 件（URL が優先、無ければ下書きを復元して告知）。DB・オプションは書かない |
| UI | 3 スロット（空のときは次の 1 つだけ「見本を選ぶ」を出す）、ピッカー `<dialog>`（20 件をカテゴリ別に、検索、選択済みは追加不可）、スロットは `preview.php` の複製を `registerWithin` で登録して再生、↑↓ で並べ替え、外す、すべて外す、リンクをコピー（Web Share 対応）。操作はライブリージョンで告知し、フォーカスを次の操作に移す |
| テスト | `tests/learn-composer/run.php` 16件（正規化・ハッシュ往復・上限・注入・プリセット・ルーティング）、`tests/learn-composer/core-parity.js` 30件（PHP ⇄ JS）。既存の 134 / 1760 件は無変更で通過。スタブサーバーに `/learn/motion/compose/` を追加 |

未実装（3C 以降）：スロットごとの調整パネル、結合プレビュー、指示書。本番公開には固定ページ `learn/motion/compose` の作成（運営者）と、デプロイ後の自動 flush（初回アクセス時）が必要。

### 8.4.1 Phase 3C — スロットごとの調整パネルと結合プレビュー（2026年10月19日）

| 要素 | 実装 |
|---|---|
| パネル取得 | `/learn/motion/<slug>/?omx_partial=1&omx_panel=1` が `playground.php` だけを返す（`inc/learn-composer.php` の `template_redirect` 優先度 9、`X-Robots-Tag: noindex`、`share => false`）。スロットの「調整する」で初回だけ取得し、slug 単位でキャッシュ。失敗時は再試行ボタン |
| ホストモード | `learn-playground.js` は `[data-omx-params-host]` の中では `#p=` を読み書きせず、`data-omx-values` から初期値を取り、変更を `omx:params:change`（`{slug, values, modified}`、bubbles）で報告する。ギャラリーの詳細・ダイアログには属性が無いので挙動は不変 |
| スロット | 変更を受けて `composition.slots[i].params` を更新 → `#c=`・下書き・結合プレビューを更新。個別プレビューはパネルが `sendParams` で即時反映。見出し横に「n項目を調整中」バッジ。並べ替えても開いたパネルと値はついてくる |
| 結合プレビュー | 1 つの `srcdoc` に `<section class="omx-slot" data-slot="n">` を縦に積む。パラメータは `.omx-slot[data-slot="n"]{--p-*}` にスコープ（`:root` を使わない）。JS 見本は `(function(omx){…}(omxSlot(n,{…})))` で包み、`param()` がそのスロットの値を返す。各ステージは末尾の fit スクリプトで幅基準に拡縮。値・順番が変わると 300ms 後に文書を作り直す（`preview.docBuilder` を `learn-gallery.js` の `mount` が使う）。再生・一時停止・テーマ・reduced-motion は既存経路 |
| PHP ⇄ JS | `omochix_learn_composer_scoped_block / js_params / preview_document` と `learn-composer-core.js` の `scopedBlock / jsParams / buildCombinedDoc` が同一文字列を返す（parity 48件）。`run.php` 22件 |

確認済み（スタブ）：パネル取得と束縛（URL に `#p=` が付かない）、値変更 → `#c=1;pop-in:cycle=1.5;count-up`・バッジ・個別プレビュー・結合 srcdoc（スコープ付きブロック、`omxSlot(2,{})`）、並べ替え後もパネルと値が維持、既定に戻す、ギャラリーの詳細（`#p=` 復元）とダイアログの回帰なし、375px。同時再生のみ（順番再生は 3E の任意項目）。

### 8.4.2 Phase 3D — AI 実装指示書（2026年10月19日）

| 要素 | 実装 |
|---|---|
| 用途入力 | スロットの「用途」トグルで「適用対象」「意図」をプリセット select（PHP の `omochix_learn_composer_presets()`：対象 8・意図 6）＋「自由入力…」（40 文字、平文のみ）で入力。値は `slots[].target / intent` → `#c=` の `t=` / `i=` と下書きへ。CSS / JS / srcdoc には流さない |
| 指示書 | PHP が正本：`brief_templates()`（header / list_item / slot / footer）、`brief_code()`（`code_template` と同じ構成で `:root` の代わりに `.omx-motion-n{--p-*}`、HTML を `<div class="omx-motion omx-motion-n">` で包み、JS 見本は基本シム＋`omxSlot` ＋ IIFE）、`brief($composition, $assistant)`。JS は `learn-composer-core.js` の `buildBrief()` が同じテンプレートを埋める（parity 8 件、全文一致）。見本の仕様・用途・URL は composer ページだけに印字する `#omx-composer-data`（20 件、約 17KB）から取る |
| 表現 | Markdown。概要（件数・見本一覧・再生）、見本ごとの章（見本・適用対象／意図・どんな動きか・仕様・調整した値（（既定）付き）・使いどころ・実装コード）、全体の実装条件（ライブラリ不可、数値はそのまま、接頭辞で包む、reduced-motion の具体例、性能）、受け入れ条件。Claude Code / Codex は冒頭 1 行だけ違う。注記が行頭で `#` `-` `*` `>` `\|` `+` になるときは全角空白を前置して Markdown の記法にしない |
| UI | ページ下部「AIへの実装指示書」：相手の切替（ラジオ）、本文 `<pre>`、コピー、`.md` 保存（Blob）、組み合わせを JSON でコピー、JSON を貼り付けて読み込む（`<dialog>`、`core.sanitize` を通し、無効なら文言を表示）。状態変更の 300ms 後に再生成 |
| テスト | `run.php` 30 件（章の順・用途の表示・（既定）・スコープ付きコード・footer・相手の差分・データ JSON）、parity 56 件 |

### 8.4.3 Phase 3E — 仕上げ（2026年10月19日）

- **読み上げ**：`learn-gallery.js` の `flash()` が、`.motion-panel` / `[data-motion-detail]` に無い場合は最も近い祖先の `[data-omx-copy-status]` を使うようになり、指示書の「コピー」「JSON でコピー」も告知される。既存ページの挙動は不変。
- **用途入力**：各スロットの select / 入力に `aria-describedby` で「AIへの指示書にだけ使われます（プレビューやコードには入りません）」を付けた。
- **SEO**：composer ページは `noindex, follow`、canonical は自分自身、`<title>` は「<ページ名> | Learn」、description は抜粋があればそれ、無ければ既定文。Learn ハブの一覧には `learn/motion` の子なので混ざらない。
- **導線**：ギャラリー一覧のリード文の下に「見本を3つまで組み合わせて、AIへの指示書にする →」（composer ページが公開されているときだけ）。
- **共有リンクの長さ**：`#c=` が 1,900 文字を超えると共有ボタンを無効化し、JSON コピーを案内（通常の組み合わせは 100〜400 文字。全パラメータを変えて 6 つの用途欄すべてに日本語 40 文字を入れた最悪ケースで約 2,300 文字になり、このときだけ無効化される）。
- **公開に必要な運営者作業**：固定ページ `learn/motion/compose`（親：動く見本、slug `compose`）の作成 → 初回アクセスでリライトルールの flush → アクセラレータの「キャッシュ削除」。手順は `docs/Beta-Deployment-Runbook.md` 8c。
- **順番再生**は見送り（同時再生のみ）。

## 9. 未決定事項・運用上の注意

- `/learn/motion/` 固定ページの作成・公開は運営者の操作です（本番DBは触りません）。
- 新しい rewrite rule はテーマ更新後に一度だけ `flush_rewrite_rules()` が走る仕組みにしていますが、反映されない場合は「設定 → パーマリンク」を保存し直してください。
- 見本の本数・カテゴリ名はPhase 1のサンプル（20件・5カテゴリ）です。コンテンツ方針に合わせて追加・改名できます（`catalog.json` のみ）。
- 詳細ページのOGP画像は未定（Default OGPに依存）。
- Playground・Videoの仕様は未定（予約フィールドのみ）。
