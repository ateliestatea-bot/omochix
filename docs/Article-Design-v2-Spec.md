# Article Design v2 — 記事HTML仕様（自動記事生成システム向け）

更新日：2026年10月10日 / 対象：OmochiX Theme 1.15.0 以降 / 実装：`wp-theme/omochix-theme/assets/css/article-v2.css`、`inc/article-v2.php`

この文書は、記事を自動生成するシステムが守る **契約仕様** です。「必須」を満たしたHTMLだけが v2 として扱われ、満たさない旧形式の記事は従来どおり表示されます。§14 の Editorial Engine 接続契約は **暫定版** です（Editorial Engine 側の仕様が確定するまで変わり得ます）。

## 1. 基本方針

- v2 は **オプトイン** です。本文に `omx2-article` クラスを持つ要素がある投稿だけが対象です。旧形式の記事（`omx-article` + 記事内 `<style>` など）は一切変更されません。
- 見た目（色・余白・文字組み・ダークモード）は **テーマ側のCSSが決めます**。記事側は意味のあるHTMLとクラス名だけを出力します。
- 記事のタイトル（H1）、パンくず、メタ情報、アイキャッチ、目次、関連記事、共有、著者欄は **テーマが出力** します。本文には含めません。
- **テーマの表示時補正（§12）は、入力HTMLの安全性検証の代替ではありません。** 生成側は、この仕様に適合したHTMLを出力する責任を持ちます。安全性（不正なタグ・属性の除去）は WordPress の保存時サニタイズ（kses）の責務です。

## 2. 必須ルール（MUST）

| # | ルール | 違反時の扱い |
|---|---|---|
| 1 | 本文の最上位に `<div class="omx2-article" data-brand="…" data-design="2">…</div>` を1つ置く。**`data-design="2"` は必須**（この契約のバージョン識別子）。テーマは判定に `data-design` を使わず、クラス `omx2-article` だけで判定します（値が未知でも v2 として表示。将来の改訂に備えた前方互換） | クラスが無いと v2 になりません（旧形式として表示） |
| 2 | `data-brand` は `openai` `anthropic` `google` `meta` `nvidia` `microsoft` `mistral` `xai` `generic` のいずれか | 他の値は `generic` と同じ配色になります |
| 3 | 本文に `<h1>` を書かない | 表示時に `<h2>` へ変換されます（ページのH1は1つ）。変換に頼らないこと |
| 4 | 本文に `<style>` `<script>` `style="…"` `on…=` 属性を書かない（§3） | `<style>` は表示時に除去。`style=` は除去されません（デザインが崩れます） |
| 5 | セクション見出しは `<h2 id="…">`。`id` は半角英字で始まり、`[A-Za-z0-9_:.-]` のみ、記事内で一意 | 欠落・重複・不正な `id` は、表示時に `section-1`, `section-2`… が補われます。**生成側で必ず付けてください**（補完に頼らない） |
| 6 | 部品の中の見出しに `<h2>` を使わない（カード名などは `*__title` クラスの `<p>`、または `<h3>`） | 目次にノイズが入ります |
| 7 | **抜粋（excerpt）を必ず設定する**（80〜120文字、記事の要点を1〜2文のプレーンテキスト。HTML・ラベル・ブランド名の羅列は不可） | 空の場合は本文の最初の自然な段落から自動生成されますが、品質は保証されません |
| 8 | ニュース記事は末尾に出典ブロック `omx2-sources`（公式発表・一次情報へのリンク）と `最終確認日：YYYY年M月D日` を置く | 公開前チェックの必須項目（`docs/AI-News-Publishing-Template.md`） |
| 9 | 画像は `alt` 必須（装飾のみなら `alt=""`）。URLは `https://`（メディアライブラリの画像）。キャプションは `<figure class="omx2-figure"><img …><figcaption>…</figcaption></figure>` | `data:` URL は保存時に除去されます |
| 10 | 表は `<caption>`、列見出し `<th scope="col">`、行見出し `<th scope="row">` を持つ。表全体を `omx2-table-wrap` で包み、`role="region" aria-label="（表の名前）" tabindex="0"` を付ける（キーボードで横スクロールできるように） | — |
| 11 | クラス名は §7 の一覧にあるもののみ使う（`omx2-` 以外のクラスは使わない） | 未定義クラスは装飾なしになります |
| 12 | `list-style` を消す一覧（`omx2-tldr` の `ul`、`omx2-steps`、`omx2-flow`）には `role="list"` を付ける（Safari/VoiceOver がリストとして読むため） | — |
| 13 | コードの `<pre>` に `tabindex="0"` を付ける（長い行をキーボードでスクロールするため）。`<` `&` は必ずエスケープ | — |
| 14 | 本文中に **ハードな改行を入れない**（§6） | `<br />` が自動挿入されます |

## 3. 禁止事項（MUST NOT）

- 記事内のCSS（`<style>`、`style` 属性、外部CSSの読み込み）と、独自の配色変数（`--bg`、`--claude` など）の定義
- 本文内のヒーロー（`omx-hero`）、キッカー、独自の見出しブロック（タイトルはテーマが表示します）
- `omx-*` 系の旧クラス（旧形式の部品）。名前が衝突するため、v2 記事では使いません
- `<h1>`、`<script>`、`<iframe>`、`<form>`、インラインのイベント属性（`on…=`）、`javascript:` の URL
- ブランド色を文字色として直接指定すること（色はすべて `data-brand` で決まります）

## 4. 投稿ユーザーの権限と WordPress の保存時サニタイズ

保存時に WordPress が HTML を整えます（kses）。隔離環境で実測した結果は次のとおりです。

| 入力 | `unfiltered_html` あり（管理者・編集者） | なし（投稿者・寄稿者） |
|---|---|---|
| `omx2-*` クラス、`data-brand`、`data-design`、`id`、`scope`、`role`、`aria-*`、`tabindex` | 保持 | **保持**（完成例3本は無傷） |
| `aside` `figure` `figcaption` `cite` `caption` `blockquote` `table` など | 保持 | 保持 |
| `<style>` | 保持される（→ 表示時に除去） | **タグだけ除去され、中身のCSSが本文テキストとして表示される** |
| `style="…"` | 保持 | **保持される**（安全なプロパティのみ。デザインが崩れる） |
| `<script>` `<iframe>` `<form>` `on…=` `javascript:` | `<script>` 等は保持されることがある | 除去 |
| `data:` URL の画像 | — | `data:` が除去され画像が表示されない |

**契約**：生成側は、`<style>`・`<script>`・`style=` を **出力しない**。表示時の補正に頼らない。自動投稿用の WordPress ユーザーの権限に関わらず、同じHTMLで同じ見た目になること。

## 5. 記事種別別の構成

### 5.1 ニュース（`ai-news` カテゴリー）
1. `omx2-lead`（リード：何が起きたかを1〜2文）→ 2. `omx2-tldr`（3行まとめ。ちょうど3項目）→ 3. `<h2 id>` ごとの本文（必要に応じて `omx2-stats` `omx2-table-wrap` `omx2-cards` `omx2-callout`）→ 4. 注意事項・制約は `omx2-callout--warning` → 5. `omx2-sources`（出典 + 最終確認日）

### 5.2 解説・ガイド
1. `omx2-lead` → `omx2-tldr` → 2. `<h2 id>` ごとに `omx2-steps`（手順）、`omx2-flow`（全体像）、`omx2-code`（コマンド）→ 3. つまずき・補足は `omx2-callout--tip` / `--note` → 4. `omx2-cta` → `omx2-sources`

### 5.3 比較記事
1. `omx2-lead` → `omx2-tldr`（結論を先に）→ 2. `omx2-compare`（2〜3項目、推奨に `omx2-compare__item--best` + `omx2-badge`）→ 3. `omx2-table-wrap`（最良の値に `class="omx2-best"`）→ 4. `omx2-sources`

> 複数の企業が登場する記事は、主題となる企業の `data-brand` を選びます。決められない場合は `generic`。

## 6. wpautop（自動整形）の注意

WordPress は本文に `wpautop` を適用します。次のルールを守らないと、意図しない `<br />` や `<p>` が入ります。

- **テキストの途中に改行を入れない**。`<li>手順1⏎手順2</li>` は `手順1<br />手順2` になります。1つの文は1行で出力する。
- ブロック要素（`div` `ul` `ol` `table` `figure` `aside` `blockquote` `h2` `h3` `p`）の間の改行・空行は問題ありません（完成例のとおり）。
- 段落の中に空行を入れない。
- `<pre>` の中の改行は保持されます（コードはそのまま改行してよい）。

## 7. クラス一覧（全40）

下記が `article-v2.css` が定義するクラスのすべてです（`omx2-article` を含めて41）。BEM の `__要素` と `--修飾` は完全な名前で列挙します。**テスト（`tests/article-v2/run.php`）が、この一覧と CSS の一致を検査します。**

| クラス | 要素 | 用途 |
|---|---|---|
| `omx2-article` | `div` | ラッパー（`data-brand` `data-design="2"` を付ける） |
| `omx2-lead` | `p` | リード文 |
| `omx2-label` | `p` | 小さなラベル（「3行まとめ」「出典」） |
| `omx2-tldr` | `div` | 3行まとめ（中に `omx2-label` と `ul role="list"`） |
| `omx2-callout` | `div` | 注意・補足の枠（`omx2-callout--point` `--note` `--tip` `--warning` のいずれか1つを併記） |
| `omx2-callout--point` | `div` | ポイント（既定のアクセント） |
| `omx2-callout--note` | `div` | 補足 |
| `omx2-callout--tip` | `div` | ヒント |
| `omx2-callout--warning` | `div` | 注意 |
| `omx2-callout__title` | `p` | callout の見出し |
| `omx2-cards` | `div` | カードのグリッド（2〜4枚） |
| `omx2-card` | `div` | 情報カード |
| `omx2-card__meta` | `p` | カードの小ラベル（例：`USE CASE 01`） |
| `omx2-card__title` | `p` | カードの名前 |
| `omx2-stats` | `div` | 数値のグリッド（2〜4個） |
| `omx2-stat` | `div` | 数値1つ |
| `omx2-stat__label` | `p` | 数値の名前 |
| `omx2-stat__value` | `p` | 数値 |
| `omx2-stat__note` | `p` | 数値の補足 |
| `omx2-steps` | `ol role="list"` | 手順の一覧（番号はCSS） |
| `omx2-step` | `li` | 手順1つ |
| `omx2-step__title` | `p` | 手順の名前 |
| `omx2-flow` | `ol role="list"` | 矢印フロー（3〜5項目） |
| `omx2-flow__item` | `li` | フローの1項目 |
| `omx2-flow__title` | `span` | フロー項目の名前 |
| `omx2-compare` | `div` | 比較カードのグリッド（2〜3項目） |
| `omx2-compare__item` | `div` | 比較カード1つ |
| `omx2-compare__item--best` | `div` | 推奨の比較カード（`omx2-compare__item` と併記） |
| `omx2-compare__name` | `p` | 比較対象の名前 |
| `omx2-badge` | `span` | バッジ（例：`おすすめ`） |
| `omx2-table-wrap` | `div role="region" aria-label tabindex="0"` | 表の横スクロール枠 |
| `omx2-table` | `table` | 表（`caption` `th scope` 必須） |
| `omx2-best` | `td` | 表の最良の値 |
| `omx2-quote` | `blockquote` | 引用（`<p>` と `<cite>`） |
| `omx2-sources` | `aside` | 出典ブロック |
| `omx2-sources__checked` | `p` | `最終確認日：YYYY年M月D日` |
| `omx2-code` | `figure` | コードブロック（`figcaption` + `pre tabindex="0"` + `code`） |
| `omx2-cta` | `div` | 誘導枠（1記事に1つまで） |
| `omx2-cta__title` | `p` | CTA の見出し |
| `omx2-cta__button` | `a` | CTA のボタン（`href` はサイト内の URL） |
| `omx2-figure` | `figure` | 画像とキャプション |

通常の `<p>` `<ul>` `<ol>` `<h3>` `<h4>` `<strong>` `<a>` `<code>` `<hr>` も使えます（スタイルはテーマが付けます）。完成例は `tests/article-v2/fixtures/`（`news-anthropic.html` `guide-generic.html` `compare-openai-google.html`）です。

## 8. ブランド属性（`data-brand`）

| 値 | 対象 | 値 | 対象 |
|---|---|---|---|
| `openai` | OpenAI / ChatGPT | `microsoft` | Microsoft / Copilot |
| `anthropic` | Anthropic / Claude | `mistral` | Mistral AI |
| `google` | Google / Gemini | `xai` | xAI / Grok |
| `meta` | Meta / Llama | `generic` | 上記以外・複数社・サイト既定 |
| `nvidia` | NVIDIA | | |

**未知の値は `generic` 扱い**です（CSSが一致しないため）。ブランド色は **装飾用**（線・バー・点。3:1以上）、**文字用**（リンク・ラベル・数値。4.5:1以上）、**塗り用**（白文字ボタン。白に対して4.5:1以上）を区別し、ライト/ダーク別の値をテーマが持ちます。生成側が色を指定する必要はありません（`tests/article-v2/run.php` が自動検査）。

## 9. 見出し（H1/H2）と目次

- ページのH1はテーマが出します。本文の最初の見出しは `<h2>` から始めます。
- 目次は `id` つきの `<h2>` が2つ以上あると、テーマが自動で表示します。
- `id` の規則：英字で始まり、`[A-Za-z0-9_:.-]` のみ、記事内で一意（大文字小文字は別の値）。日本語・スペースを含めない。

## 10. 抜粋・出典・最終確認日・画像

- **抜粋**：WordPress の「抜粋」フィールド（REST では `excerpt`）に **本文とは独立して** 保存します。80〜120文字、プレーンテキスト。ヘッダーのリード文、検索結果、SNS・検索エンジン向けの説明の元です。Slim SEO は `post_excerpt` を使うため、空だと meta description が本文の先頭から作られます。
- **出典**：`omx2-sources` に一次情報（公式発表・公式ドキュメント）のURLを列挙。**URLは実在し公開されているものだけ**（§14 では AI が URL を書かない）。
- **最終確認日**：`最終確認日：YYYY年M月D日`（`docs/AI-News-Publishing-Template.md` の必須項目）。
- **画像**：`alt` 必須、`https://` のURL、`width` と `height` を付ける、キャプションは `figcaption`。

## 11. 旧形式との互換性

| 項目 | 内容 |
|---|---|
| 判定 | `div` `section` `article` のいずれかで、クラストークンが完全一致の `omx2-article` を持つ **実在する要素** がある場合だけ v2。コメント、`<pre>` `<code>` `<script>` `<style>` `<svg>` の中、属性値の中の文字列、エスケープされた文字列、`omx2-article-foo` のような別名は対象外 |
| 旧記事 | CSSの追加読み込みなし、HTMLの書き換えなし、抜粋の挙動も従来どおり（既存100件で、新旧テーマのHTMLがバイト単位で同一であることを確認済み） |
| 併用 | 1つの記事に旧形式と v2 を混在させない |
| 移行 | 既存記事の一括変換は行いません |

## 12. 表示側の動作（実装メモ）

- 保存済みの本文、投稿オブジェクト、グローバルの `$post` は **一切変更しません**（保存フックも使いません）。表示時の補正は、`get_the_content()` の元になる `$pages` と `the_content` に対して行います（冪等）。目次（`single.php`）は `get_the_content()` から作られるので、補正後の `id` と目次のリンクは一致します。
- 補正の内容：`<style>` ブロックの除去、`<h1>` → `<h2>`、`<h2>` の `id` の補完・重複解消・不正値の置換。それ以外のHTMLは1バイトも変えません（コメント・属性値・コード・SVG の中は対象外）。
- **失敗時は何もしません**：内部エラーや、本文が 1MB を超える場合は、元の本文をそのまま返します（空にしません）。判定に失敗した場合は旧形式として扱います。
- 抜粋の補完は、手動の抜粋が無い v2 記事だけ。HTML エンティティは **復号しません**（出力先ごとのエスケープに任せます）。インラインの `<code>` の語は残します。
- 処理は線形時間のスキャナで行い、閉じていないタグ・コメントでも処理時間が二乗で増えません。

### 12.1 処理の上限と不正HTMLの扱い

| 項目 | 値 / 動作 |
|---|---|
| 本文サイズ上限 | 1MB（`OMOCHIX_ARTICLE_V2_MAX_BYTES`）。超えると旧形式として扱い、補正しない |
| トークン数上限 | 20,000（`OMOCHIX_ARTICLE_V2_MAX_TOKENS`。タグとコメントの合計）。実在100記事の最大は 1,169（52KB）で、約17倍の余裕。細かいタグを1MB敷き詰めた入力のピークメモリは、修正前 約+76MB → 修正後 約+4.6MB |
| 上限・異常に達したとき | 判定は **false（旧形式）**、補正は **元の本文をそのまま**、抜粋の補完は **空文字**（WordPress標準の抜粋に戻る）。本文は消えず、PHP Fatal Error も起こさない |
| 閉じていない `<pre>` `<script>` `<style>` `<textarea>` `<template>` `<noscript>` | 以降の解釈がブラウザと食い違うため検査失敗。上記「異常に達したとき」の動作 |
| 閉じていない `<code>` | 同上（v2 と判定せず、補正しない）。余分な `</code>` は無害 |
| 属性の間の `/`（例 `<div / class="omx2-article">`） | HTML の仕様どおり読み飛ばして属性を認識する（`<br />` などの自己終了、`href=x/y` の値は従来どおり） |
| 対応する開始タグのない `</h1>` | 書き換えない（開始タグと対になった `<h1>…</h1>` だけを `<h2>` にする） |
| 複数ページ記事（`<!--nextpage-->`） | 補正は **ページごと** に行う。自動採番の `section-N` は各ページで 1 から振り直される（ページをまたいだ一意性は保証されない）。**生成側が一意な `id` を明示すれば影響を受けない**ため、複数ページ記事でも `id` は生成側で全体一意にする |

不正HTMLを補正しません。**修復して出力するのではなく、そのまま出すか、旧形式として扱う** のが方針です。

## 13. 検証

```bash
php tests/article-v2/run.php                                        # 判定・補正・異常系・性能・抜粋・契約・ブランドのコントラスト
OMX_LEGACY_JSON=/path/to/posts.json php tests/article-v2/run.php    # 既存記事が v2 と誤判定されないこと
```

## 14. Editorial Engine 接続契約（暫定）

> **暫定版**：Editorial Engine（Evidence Pack → Editor → Writer → Fact Check → HTML Renderer → WordPress 下書き）の仕様が確定するまで、この節は変更されます。目的は、AI が HTML を直接書かず、出典 URL を創作せず、未知の入力を安全に扱えるようにすることです。

### 14.1 責務
| 段階 | やること | やらないこと |
|---|---|---|
| Writer | **構造化 JSON だけ** を出力する（§14.2） | HTML、URL、CSS、ブランド色を書かない |
| Fact Check | 数値・固有名詞の主張に `source_id`（Evidence Pack の ID）が紐づくか検証 | — |
| **HTML Renderer** | JSON を検証し、**このレンダラーだけが** §2〜§7 に適合したHTMLを生成する | AI の自由文をHTMLとして通さない |
| WordPress 連携 | 下書き（`draft`）として作成。`title`・`content`・`excerpt`・`categories`・`status` を **別フィールド** で送る | 公開は人の承認後（公開前チェックリストに従う） |

### 14.2 JSON の形（暫定）
```json
{
  "schema": "omx2-article/0.1-provisional",
  "meta": { "title": "…", "excerpt": "80〜120文字のプレーンテキスト", "brand": "anthropic", "category": "ai-news", "checked_date": "2026-10-09" },
  "blocks": [
    { "type": "lead", "text": "…" },
    { "type": "tldr", "items": ["…", "…", "…"] },
    { "type": "section", "id": "what-is", "title": "…", "blocks": [ { "type": "paragraph", "spans": [ { "t": "文" }, { "b": "強調" }, { "code": "npm i" } ] } ] },
    { "type": "callout", "kind": "warning", "title": "注意", "text": "…" },
    { "type": "stats", "items": [ { "label": "INPUT", "value": "$0.10", "note": "100万トークンあたり", "evidence": ["S1"] } ] },
    { "type": "table", "caption": "…", "columns": ["項目", "A", "B"], "rows": [ ["Input", "$0.10", "$0.50"] ], "best": [[0, 1]] },
    { "type": "steps", "items": [ { "title": "…", "text": "…" } ] },
    { "type": "flow", "items": [ { "title": "…", "text": "…" } ] },
    { "type": "compare", "items": [ { "name": "…", "text": "…", "points": ["…"], "best": true, "badge": "おすすめ" } ] },
    { "type": "cards", "items": [ { "meta": "USE CASE 01", "title": "…", "text": "…" } ] },
    { "type": "quote", "text": "…", "source_id": "S1" },
    { "type": "code", "filename": "terminal", "text": "npm install …" },
    { "type": "figure", "media_id": 123, "alt": "…", "caption": "…" },
    { "type": "cta", "title": "…", "text": "…", "route": "ai-tools" },
    { "type": "sources", "items": ["S1", "S2"] }
  ]
}
```
- テキストはすべてプレーンテキスト（`<` `>` `&` はレンダラーがエスケープする）。リンクを書く手段はありません。

### 14.3 出典（`source_id`）— AI は URL を書かない
- Evidence Pack の各出典は `{source_id, url, title, publisher, published_at, retrieved_at}` を持つ。URL を持つのは Evidence Pack だけ。
- Writer は `source_id` だけを書く。Renderer が Evidence Pack から URL を解決し、`<a href>` を生成する。
- **未登録の `source_id` は拒否**する（記事を作らず、Fact Check に差し戻す）。URL は `https://` のみ、Evidence Pack に登録されたホストのみ。
- サイト内リンク（CTA 等）は URL ではなく **ルートキー**（許可リスト：`ai-tools` `ai-news` `prompts` `learn` など）で指定し、Renderer が `home_url` 等から生成する。
- `最終確認日` は Evidence Pack の最終取得日以後の日付で、Renderer が `omx2-sources__checked` に書く。

### 14.4 未知のブロック・属性の扱い
| 入力 | 扱い |
|---|---|
| 未知の `type` | **そのブロックを出力しない**（生HTMLにフォールバックしない）。警告ログを残し、記事を「要確認」の下書きに留める |
| 未知のフィールド | 無視する |
| 必須フィールドの欠落、型の誤り、項目数の超過（例：`tldr` が3項目でない） | 記事全体を拒否（WordPress に送らない） |
| 未知の `brand` | `generic` にする |
| `callout.kind` が許可値以外 | `note` にする |
| テキストに `<` `>` が含まれる | エスケープして出力し、警告する |
| `route` が許可リスト外、`source_id` が未登録 | 拒否 |

### 14.5 出力の検証ゲート
**HTML の構文検証は必須**：タグはすべて正しく閉じる（特に `<code>` `<pre>`）。属性間に余分な `/` を置かない。対応する開始タグのない閉じタグを出さない。トークン数（タグ+コメント）は 20,000 未満、本文は 1MB 未満。複数ページ記事（`<!--nextpage-->`）では `id` を全体で一意にする。検査に失敗した出力は保存しない。
Renderer の出力は、保存前に §2 の必須ルール（H1 なし、`style` なし、`id` の一意性、`alt`、`caption`、`th scope`、クラスは §7 のみ、`data-brand` の許可値、`data-design="2"`、改行の禁止、抜粋の長さ）を満たすことを検査してから、WordPress の下書きとして作成する（検査ツールは別途用意する予定）。
