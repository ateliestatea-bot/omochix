# AI Tools Batch 01 — Production Read-Only Audit

監査日：2026-10-01（JST）
対象：veo / kling-ai / runway / midjourney / claude-code / cursor / perplexity / gemini / chatgpt / notebooklm（10件）
比較用：heygen（品質基準、件数外）／ sora（現状記録のみ）
基準JSON：`scratchpad/ai-tools/batch-01/ai-tools-content-batch-01.json`（SHA-256 `a332f2fc…a525b`）

## 0. 監査方法と制約

- productionへのアクセスは **GET / HEAD のみ**。スクリプト経由145リクエスト（GET・HEADのみ、全て200）＋ブラウザでの表示確認。POST/PUT/PATCH/DELETE、wp-adminアクセス、キャッシュ操作、テーマ切替ボタンのクリックは行っていない（ダークモードはブラウザ側の配色エミュレーションで確認）。
- 本番URLはこのファイルに記載していない（パスのみ）。
- 反映確認は公開REST（`/wp-json/wp/v2/ai-tools?slug=…`）のmeta値と、公開HTMLの両方で実施。
- 10件とも `modified = 2026-10-01T00:48` で、Content UpdaterのApplyが反映済み。

## 1. 総合判定

| 観点 | 結果 |
|---|---|
| HTTP | 10/10が200 |
| コンテンツ反映 | 10/10 PASS（JSONの全フィールドがREST値と一致、不一致0） |
| SEO | 10/10 PASS |
| UI（Desktop / Mobile / Dark） | PASS（崩れ・横スクロールなし） |
| JS / PHPエラー・壊れたアセット | 0 / 0 / 0 |
| 関連（Prompt ⇄ AI Tool） | PASS（リンク先すべて200、逆リンクも確認） |
| 画像 | ロゴ・HEROとも CUSTOM 3 / FALLBACK 7 / BROKEN 0（別Phaseで整備） |
| Critical | 0 |
| Non-critical | 8（§9） |

## 2. Public page audit（10件）

全ページ共通：HTTP 200、H1は1つでツール名、canonicalは1つで自己参照、meta descriptionは1つ、robotsは `max-image-preview:large, max-snippet:-1, max-video-preview:-1`（noindexなし、X-Robots-Tagなし）、JSON-LDのパースエラー0。

| slug | ID | SEO title | H1 | 目次 | タブ |
|---|---|---|---|---|---|
| veo | 678 | Veoとは？料金・機能・使い方・日本語対応を解説｜OmochiX | Veo | 10項目 | 概要/できること/特徴/評価/関連コンテンツ/関連ツール |
| kling-ai | 677 | Kling AIとは？…｜OmochiX | Kling AI | 10 | 同上 |
| runway | 675 | Runwayとは？…｜OmochiX | Runway | 10 | 同上 |
| midjourney | 59 | Midjourneyとは？…｜OmochiX | Midjourney | 10 | 同上 |
| claude-code | 665 | Claude Codeとは？…｜OmochiX | Claude Code | 10 | 同上 |
| cursor | 65 | Cursorとは？…｜OmochiX | Cursor | 10 | 同上 |
| perplexity | 63 | Perplexityとは？…｜OmochiX | Perplexity | 10 | 同上 |
| gemini | 61 | Geminiとは？…｜OmochiX | Gemini | 10 | 同上＋アップデート |
| chatgpt | 53 | ChatGPTとは？…｜OmochiX | ChatGPT | 10 | 同上＋アップデート |
| notebooklm | 659 | NotebookLM（Gemini Notebook）とは？…｜OmochiX | NotebookLM | 10 | 同上 |

セクション表示（全10件で表示）：クイックサマリー、概要（post_content）、できること（Feature Cards 7〜8枚）、選ぶ前に知っておきたいこと（8パネル）、おすすめの使い方、詳細情報、OmochiX編集部評価、OMOCHIX VIEW、関連コンテンツ（PRODUCT HUB）、関連AIツール。目次のアンカー切れは0。

非表示セクションの切り分け（いずれも **仕様上正常**）：

| セクション | 非表示のツール | 理由 |
|---|---|---|
| 最新アップデート / 更新履歴 | gemini・chatgpt以外の8件 | product timelineデータが未登録。データがある2件は表示されている |
| 関連記事 | veo / kling-ai / midjourney | ツール名に一致する記事がない（NO_DATA） |
| 詳細情報の「連携サービス」 | kling-ai | `integrations` をJSONに含めていない（確認できる情報なし） |
| 詳細情報の「対応モデル」 | notebooklm | `supported_models` をJSONに含めていない |
| 詳細情報の「API / SDK」「セキュリティ」 | midjourney | 公式APIなしのため `api_sdk_info` を含めていない。`security_info` も同様 |

## 3. Content reflection

JSONに含めた全フィールドをRESTのmeta値と突合（post_contentはレンダリング後テキストで比較、SEOはmetaと実際の `<title>` / meta descriptionの両方）。

| slug | 突合フィールド数 | 不一致 | post_content | 構造化データの画面表示 |
|---|---|---|---|---|
| veo | 25 | 0 | 3,393字 | key_features 7/7、配列・テキストすべて表示 |
| kling-ai | 24 | 0 | 3,104字 | 7/7、すべて表示 |
| runway | 25 | 0 | 2,978字 | 8/8、すべて表示 |
| midjourney | 23 | 0 | 3,299字（※1） | 8/8、すべて表示 |
| claude-code | 25 | 0 | 3,316字 | 8/8、すべて表示 |
| cursor | 25 | 0 | 3,510字 | 8/8、すべて表示 |
| perplexity | 23 | 0 | 3,014字 | 8/8、すべて表示 |
| gemini | 24 | 0 | 3,240字 | 8/8、すべて表示 |
| chatgpt | 25 | 0 | 3,270字 | 8/8、すべて表示 |
| notebooklm | 24 | 0 | 3,386字 | 7/7、すべて表示 |

- `has_free_plan` / `api_available` / `commercial_use` / `japanese_support` はJSONどおり（midjourney・veoとも `partial`）。
- `info_checked_date`：9件が `2026-10-01`。**kling-aiのみ `2026-09-30`**。これはJSONの値どおりで反映不良ではない（料金を9/30の取得値のまま再取得していないため、意図的に9/30としている）。
- JSONに含めなかったフィールド（perplexityの `supported_models`・`integrations`、geminiの `integrations` など）は本番の従来値がそのまま残っている。未反映ではない。
- ※1 midjourneyは保存値は一致するが、表示時に1か所だけ文字が変わる（§9-1）。

## 4. Image audit / Inventory

判定根拠：RESTの `tool_logo` メタと `featured_media`、および公開HTMLの実際の出力。FALLBACKロゴは頭文字1文字のタイル、FALLBACK HEROは「OmochiX」プレースホルダー。

| Tool | Slug | Tool Logo | Hero | Logo Attachment ID | Hero Attachment ID | Needs Logo | Needs Hero |
|---|---|---|---|---|---|---|---|
| Veo | veo | FALLBACK（V） | FALLBACK | 0 | 0 | YES | YES |
| Kling AI | kling-ai | FALLBACK（K） | FALLBACK | 0 | 0 | YES | YES |
| Runway | runway | FALLBACK（R） | FALLBACK | 0 | 0 | YES | YES |
| Midjourney | midjourney | FALLBACK（M） | FALLBACK | 0 | 0 | YES | YES |
| Claude Code | claude-code | FALLBACK（C） | FALLBACK | 0 | 0 | YES | YES |
| Cursor | cursor | FALLBACK（C） | FALLBACK | 0 | 0 | YES | YES |
| Perplexity | perplexity | CUSTOM | CUSTOM | 630 | 632 | NO | NO |
| Gemini | gemini | CUSTOM | CUSTOM | 634 | 636 | NO | YES（差し替え推奨） |
| ChatGPT | chatgpt | CUSTOM | CUSTOM | 54 | 566 | YES（差し替え推奨） | YES（差し替え推奨） |
| NotebookLM | notebooklm | FALLBACK（N） | FALLBACK | 0 | 0 | YES | YES |
| （比較）HeyGen | heygen | CUSTOM | CUSTOM | 952 | 953 | — | — |

- MISSING 0：画像領域は全ページに存在する。BROKEN 0：CUSTOM画像はサムネイル・原寸とも200で読み込める。
- HEROなしの7件は `og:image` もサイト既定画像になっている（フォールバックとして正常）。

### HERO品質（CUSTOMの3件）

| Tool | 原寸 | 表示 | 判定 | 理由 |
|---|---|---|---|---|
| Perplexity | 1672×941 | 16:9で正常 | **PASS** | 文字なし、ツールの内容と一致、HeyGenと同等の質感 |
| Gemini | 1672×941 | 16:9で正常 | **REPLACE_RECOMMENDED** | 画像内の文字が多い（見出し・機能ラベル5つ・キャッチコピー・英文）。Google系プロダクトのマークも描き込まれている |
| ChatGPT | 1672×941 | 16:9で正常 | **REPLACE_RECOMMENDED** | 製品UIを模した画像で文字が多く、モデル表示が「GPT-5.6」のまま（本文はGPT-6系）。2026-09-20作成で内容が古い |
| （比較）HeyGen | 1672×940 | 16:9で正常 | PASS | 文字なし |

### ロゴ品質（CUSTOMの3件）

| Tool | 原寸 | 判定 | 理由 |
|---|---|---|---|
| Perplexity | 2000×2000 JPEG | **PASS** | 黒地に白マーク。切れ・余白の問題なし（ファイル名とaltが `IMG_8506` / `IMG` のまま） |
| Gemini | 800×800 WebP | **PASS** | 現行のマーク。白背景で余白も適正 |
| ChatGPT | 716×716 PNG（透過） | **REPLACE_RECOMMENDED** | 黒一色の透過PNGのため、ダークモードでは暗いタイルの上でほぼ見えない（`audit-screenshots/chatgpt_desktop-dark_hero-logo-low-contrast.jpg`） |

## 5. UI audit（代表4ページ）

確認した組み合わせ：

| ページ | Desktop 1440 light | Desktop 1440 dark | Mobile 390 light | Mobile 390 dark |
|---|---|---|---|---|
| Claude Code | ✔ | — | ✔ | ✔ |
| Midjourney | ✔ | — | ✔ | ✔ |
| Runway | — | ✔ | ✔ | — |
| NotebookLM | ✔ | — | — | ✔ |

Desktop darkはRunwayとChatGPT（ロゴ確認用）で確認。4ページ×4条件の全16通りは実施しておらず、各ページ2〜3条件。

| 項目 | Desktop | Mobile |
|---|---|---|
| 横スクロール | なし（はみ出し要素0） | なし（同0） |
| HERO | 2カラム、メディア枠523×294（16:9） | 1カラム、354×199（16:9） |
| Quick Summary | 表示 | 2列グリッドで表示 |
| 目次 | サイドバーに表示、10項目 | 折りたたみ式で表示、10項目 |
| sticky TOC | サイドバーがstickyで追従、現在位置のハイライトが更新される | （仕様上なし） |
| タブ | sticky（ヘッダー直下に固定）、6〜7項目が収まる | 非sticky、2列で折り返して表示 |
| H2/H3装飾 | H2上のアクセントライン表示 | 同左 |
| Feature Cards | 3列 | 1列 |
| Pros/Cons等パネル | 2列、8パネル | 1列 |
| How-to step | 番号付きカード（01〜）で表示 | 2列で表示 |
| Use Case | 3列 | 2列 |
| 詳細情報 | 3列 | 1列 |
| OmochiX VIEW | 表示 | 表示 |
| FAQ | Q/Aとも表示 | 表示 |
| 関連コンテンツ / 関連ツール | 表示 | 1列で表示 |
| ダークモード | 背景・カード・文字色とも切り替わり、可読 | 同左 |

スクリーンショット：`audit-screenshots/`（22枚＋CUSTOM HERO 3枚）。

## 6. JS / HTTP error

- コンソール：確認した全ページ・全条件でログ0件（error / uncaught exceptionなし）。
- ネットワーク：テーマCSS・JS、ロゴ、画像を含め失敗リクエストなし。
- 同一オリジンのアセット（CSS / JS / 画像 / アイコン）：10ページ分をHEADで確認、200以外は0件。
- PHPエラー：HTML内に Fatal / Warning / Notice / Deprecated の出力なし。
- 外部analytics由来のエラー：該当なし。

## 7. SEO audit

| 項目 | 結果 |
|---|---|
| HTTP 200 | 10/10 |
| indexable（noindexなし） | 10/10 |
| canonical self | 10/10（各1つ） |
| SEO title | 10/10、JSONの `seo_title` と一致 |
| meta description | 10/10、JSONの `meta_description` と一致（各1つ） |
| 構造化データ | 10/10でWebSite / BreadcrumbList / WebPage / Organization / SoftwareApplicationを出力、パースエラー0。HEROがある3件はImageObjectも出力 |
| SoftwareApplication | name・description（新しいshort_description）・applicationCategory・operatingSystem・Reviewを出力。壊れていない |
| サイトマップ | `/sitemap-post-type-ai_tool.xml` に10件とも掲載 |
| アーカイブ | `/ai-tools/`・`/prompts/` とも200 |

## 8. Relation audit

| Tool | 関連Prompt | 関連ツール | 関連記事 | その他 |
|---|---|---|---|---|
| ChatGPT | 6件＋「すべて見る」200 | 3件200 | 3件200 | Learn 3件・News 2件も200 |
| Claude Code | 4件200 | 3件200 | 3件200 | — |
| Gemini | 6件＋「すべて見る」200 | 3件200 | 3件200 | — |
| Midjourney | 4件200 | 3件200 | NO_DATA | — |
| Runway | 3件200 | 3件200 | 2件200 | — |
| Veo / Kling AI | 各1件200 | 3件200 | NO_DATA | — |
| Cursor / Perplexity / NotebookLM | 5 / 5 / 6件200 | 3件200 | 3 / 3 / 1件200 | — |

- 5ツール（ChatGPT / Claude Code / Gemini / Midjourney / Runway）で、Prompt側のページからツールページへの逆リンクも確認。
- `related_learn_ids` 等の手動関連メタは全件空（NO_DATA）。表示は自動関連によるもの。

## 9. Non-critical（本文・データ側の調整事項）

1. **Midjourney：`--v` が `–v` と表示される。** 本文の「プロンプト末尾の「--v」パラメータ」が、WordPressの自動整形でダッシュ1文字に変換されている。`<code>--v</code>` で囲めば回避できる（Claude Codeの `claude --version` は `<code>` 内のため正常）。
2. **Veo：クイックサマリーが「料金：無料プランあり」「無料プラン：なし」と矛盾して見える。** `pricing_type=freemium` が残っているため。Content Updaterでは直せない項目。
3. **NotebookLM：「料金：無料」と表示される。** `pricing_type=free` のため。本文は有料上位枠を説明しており、freemiumが実態に近い。
4. **「対応環境」（taxonomy）と「対応デバイス」（今回更新）が食い違う。** Veo（Web ／ Web・iOS・Android）、Runway（iOS・Web ／ Androidあり）、Midjourney（未確認 ／ Web・Discord）、Perplexity（Windowsなし）、Gemini（macOSなし）、Claude Code（Linux・macOS・Windowsのみ）。taxonomyはUpdater対象外。
5. **Claude Code：How-toのステップカードが縦長になる。** ステップ文が長く、Desktopで175×482px、Mobileで167×457pxのカードになる。崩れではないが、HeyGen形式（短い見出し＋一文）のMidjourney（175×256px）と比べて読みにくい。同じ書き方のVeo / Kling AI / Cursorは未計測。
6. **ChatGPT：ロゴがダークモードでほぼ見えない**（§4）。
7. **Soraが「提供中」の関連ツールとして表示される。** Veo / Kling AI / Runwayの関連AIツール欄に出ている。
8. **Perplexityの対応モデル欄は9/24時点の一覧のまま。** JSONに含めなかったため（再確認できなかった項目）。

## 10. Sora（変更なし・現状記録）

| 項目 | 現状 |
|---|---|
| HTTP / ID / 更新日 | 200 / 679 / 2026-09-27（Batch 01では未変更） |
| 提供状況 | **「提供中」表示のまま**（`tool_status=active`） |
| 説明 | 現在形のまま（「…動画へ展開できるAI。…体験も備える。」） |
| 本文 | 空。概要・できること・特徴・詳細情報・VIEW・目次は非表示、タブは「評価／関連コンテンツ／関連ツール」のみ |
| ロゴ / HERO | FALLBACK（S）/ FALLBACK（attachment IDはともに0） |
| SEO | title「Sora – OmochiX」、indexable、canonical self、SoftwareApplicationスキーマ出力中（評価4.5付き） |
| 関連 | Prompt 1件（200）、関連ツール3件（200） |

提供終了対応は別Phase（`SORA-READONLY-AUDIT.md` 参照）。

## 11. Production changes

なし。
