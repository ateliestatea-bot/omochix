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

## 9. 未決定事項・運用上の注意

- `/learn/motion/` 固定ページの作成・公開は運営者の操作です（本番DBは触りません）。
- 新しい rewrite rule はテーマ更新後に一度だけ `flush_rewrite_rules()` が走る仕組みにしていますが、反映されない場合は「設定 → パーマリンク」を保存し直してください。
- 見本の本数・カテゴリ名はPhase 1のサンプル（20件・5カテゴリ）です。コンテンツ方針に合わせて追加・改名できます（`catalog.json` のみ）。
- 詳細ページのOGP画像は未定（Default OGPに依存）。
- Playground・Videoの仕様は未定（予約フィールドのみ）。
