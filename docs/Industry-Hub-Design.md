# OmochiX Industry Hub — 設計書 v0.2

作成日：2026-11-05 / Claude Code
状態：v0.2 承認済み（2026-11-05）。Phase 1 実装中。v0.1 の監査結果は承認時の会話に記録（本リポジトリには置いていない）。

> 実装時の補足（Phase 1）
> - 業種ハブの公開URLは Phase 1 では作らない。`omx_industry` は既定で非公開（`omochix_core_industry_is_public()` = false）。Phase 2 のテーマが `omochix_core_industry_public` フィルタで有効化し、その時点で rewrite が一度 flush される。
> - 予約スラッグは、`/learn/industries/` 配下で実際に衝突する WordPress のエンドポイント（`page`, `feed`, `embed` 等）に限定した。`practice` や `motion` はこの配下では衝突しないため予約しない（§7.1 の記述をこれで置き換える）。
> - テスト stub は既存の `tests/learn-practice/stub.php` を読み込まず、独立した `tests/industry-hub/stub.php` とした（既存 stub の `current_user_can()` は常に true を返すため、権限テストができない）。§10 の記述をこれで置き換える。
> - Workflow のメタは既存の `omochix_core_get_practice_meta_schema()` に追加せず、別スキーマ `omochix_core_get_workflow_meta_schema()` として `learn_practice` に登録する（既存の保存・エクスポート・リビジョン処理の挙動を変えないため）。そのため、practice の JSON 入出力には workflow メタと新タクソノミーがまだ含まれない（P1-08 は未着手）。

---

## 0. 環境確認結果（2026-11-05 読み取り専用で確認）

| # | 項目 | 結果 |
|---|---|---|
| 1 | 既存作業ツリー | `feature/ai-tools-platform-taxonomy-cleanup` @ 53c156e。変更2件・未追跡13件（v0.1 §0.2 と同じ）。`git status --porcelain` のハッシュは調査開始時と終了時で同じ（`7c84973…`）。既存 stash 1件（`stash@{0}: On main: WIP before Learn MVP`）も未操作 |
| 2 | `main` と `origin/main` | ローカル `main` = 4144f56（PR #11 時点）で、**`origin/main` より120コミット遅れ**、ahead 0。ローカル `main` は今回使わない（更新もしない） |
| 3 | リモート main | `git ls-remote origin refs/heads/main` = **94152a7927a55d0ba29c6a79b21b28d442f9a203** で、ローカルの `origin/main` と一致。`git fetch --dry-run` の取得差分も無いため、fetch は不要（実行していない）。main の最新 CI「Tests」は同じ SHA で success（2026-11-02） |
| 4 | Labs 1.28.0 | `release/theme-1.28.0` @ 076a85e。PR #56 は **Draft/OPEN**（「not for merge yet」）。分岐元 94152a7 から 5 ahead。CI の Tests と Labs は success |
| 4 | SVG Motion Studio | `feature/svg-motion-s1b` @ b0d0092。PR #55 は **Draft/OPEN**。分岐元 94152a7 から 12 ahead。CI の Tests と SVG Motion は success。Labs には含まれていない |
| 5 | AGENTS.md の P0 制約 | 未完了の P0 はブランド素材（Site Icon、OGP×3）、初期ニュース・出典、Terms、本番 SEO（Search Console、Sitemap、canonical、robots、Rich Results）。Industry Hub はどれも解消しない＝P0 外の作業。本番・DB・外部サービスへの変更は禁止 |
| 6 | 新規 worktree | 作成可能。パス `/Users/omochi/Documents/omochix-industry-hub-wt` は未使用、ブランチ名 `feature/industry-hub-p1` はローカル・リモートとも未使用、空き容量 410GiB。既存 worktree 19件（メイン作業ツリーを含む）のうち 7件が prunable（**prune はしない**） |
| 7 | テスト・CI | `tests.yml`（PHP 8.1/8.3、`php -l` 全件、PHP 7本、Node 4本）。main のスナップショットで手元実行し **PHP 572 PASS / 0 FAIL、Node 4本 exit 0**。手元の PHP は 8.5.8（CI の 8.1/8.3 とは違う）、Node は 24.14.1（CI と同じ） |

---

## 1. 確定アーキテクチャ

| 決定 | 内容 |
|---|---|
| AD-1 | Workflow は **`learn_practice` を拡張**する（`learn_format` に `workflow` を追加し、`workflow_*` メタを足す）。URL は `/learn/practice/{slug}/` のまま |
| AD-2 | 業種は **`omx_industry`**（階層。親＝業種、子＝サブ業種） |
| AD-3 | 業務目的は **新規の `omx_purpose`**（§2.2 で `learn_usecase` と比べて判断。本番の語彙は未確認なので、Phase 1 着手前にステージングで読み取り確認する） |
| AD-4 | 提供元は **`omx_provider_type`**。**未設定は「未分類」**として扱い、公式と認定しない（§2.3） |
| AD-5 | 外部コンテンツの台帳は **非公開 CPT `omx_resource`** |
| AD-6 | Motion 53件は **`catalog.json` と schema を変更せずに**、slug 参照で関連付ける |
| AD-7 | SVG Motion Studio（PR #55）は**初期実装の対象外** |
| AD-8 | index 判定は件数ではなく**独自コンテンツの充実度ゲート**（§6） |
| AD-9 | 既存 URL・CPT/タクソノミーの引数・メタキー・Motion JSON は**一切変えない**（追加のみ） |
| AD-10 | 役割分担：データモデルは Core プラグイン（1.10.0）、表示はテーマ（Labs 後の版番号、§11） |
| AD-11 | 新規 CI は**別ワークフローファイル** `.github/workflows/industry-hub.yml`（Labs・SVG と同じ方式。`tests.yml` は触らない） |

### ファイル構成（すべて新規。既存ファイルの変更は §11 の最小限のみ）

```
wp-plugin/omochix-core/
  includes/industry-taxonomies.php    omx_industry / omx_purpose / omx_provider_type、seed、rewrite version
  includes/industry-term-meta.php     業種 term meta スキーマ・サニタイズ・ゲート判定
  includes/provider.php               提供元の判定（未分類あり）・ラベル・PR表記・rel
  includes/resource-post-type.php     omx_resource（非公開）
  includes/resource-meta-schema.php   ライセンス・出典メタ、公開ガード
  includes/workflow-meta-schema.php   learn_practice への workflow_* 追加、ガード拡張
  includes/industry-relations.php     業種→各コンテンツの取得（tax_query）
  admin/industry-term-fields.php      業種タームの編集UI
  admin/resource-meta-boxes.php, admin/save-resource-meta.php
  admin/workflow-meta-panel.php
wp-theme/omochix-theme/   （Phase 2〜）
  inc/learn-industries.php, page-learn-industries.php, taxonomy-omx_industry.php
  template-parts/learn-industries/*.php
  assets/css/learn-industries.css, assets/js/learn-industries.js（任意）
tests/industry-hub/{stub.php, run.php, resource.php, workflow.php, seo.php}
.github/workflows/industry-hub.yml
```

既存ファイルの変更が必要な箇所（この3か所に限定）：
`omochix-core.php`（require 追加・init 呼び出し・版番号）、`functions.php`（require 1行）、`inc/shell.php`（Phase 2 で、業種ページをシェル対象にする1行）。Phase 2 後半はこれに `page-learn.php`（導線）と `single-learn_practice.php`（workflow セクション）が加わる。

---

## 2. タクソノミー仕様

### 2.1 `omx_industry`（業種）

| 項目 | 値 |
|---|---|
| object types | `ai_tool`, `prompt`, `learn_practice`, `page`, `post`, `omx_resource` |
| hierarchical | true（2階層まで。3階層目は保存時に拒否） |
| public / publicly_queryable | true / true（親業種のアーカイブ＝業種ハブ） |
| rewrite | `['slug' => 'learn/industries', 'with_front' => false, 'hierarchical' => false]` |
| show_in_rest | true（`rest_base` = `industries`）。編集画面のパネル用 |
| capabilities | manage/edit/delete＝`manage_categories`、assign＝`edit_posts` |
| show_admin_column | true |
| seed（親6件） | `real-estate` 不動産, `restaurant` 飲食, `beauty` 美容, `ecommerce` EC, `construction` 建設, `web-production` Web制作 |
| サブ業種 | seed しない（編集者が作る） |

登録方法：既存 CPT の `register_post_type` 引数は変えず、`register_taxonomy($tax, [...])` の object types に既存 CPT 名を渡す。`init` の優先度は既存 CPT 登録（10）の後にする。

### 2.2 業務目的：`learn_usecase` を再利用するか、新設するか

| 観点 | 既存 `learn_usecase`（コードと文書で確認） | 求められる業務目的 |
|---|---|---|
| 意味 | 「用途」＝作る物の使い道（テストの例：`portfolio`）。編集ガイドでは「必要なときだけ」付ける任意項目 | 業務上の目的（集客・問い合わせ対応・書類作成…） |
| 対象読者 | 制作・開発の学習者 | 事業者・業務担当者 |
| object types | `learn_practice`, `page` | ツール、プロンプト、投稿、resource も必要 |
| 公開URL | `/learn/usecase/{slug}/`。**2件以上で index** | アーカイブ不要（絞り込み専用） |
| seed | 無し | 7件を想定 |

結論は**新設 `omx_purpose`**。再利用すると、①既存タクソノミーの object types が変わる、②index されている既存のアーカイブページに業務語彙が混ざり内容が変わる、③「作る物の用途」と「業務目的」が一つの絞り込みに混在する。これは AD-9（互換性維持）に反する。
未確認：本番の `learn_usecase` に業務目的と同じ意味の語彙が既に入っていないか。ステージングで読み取り確認し（§12 B-2）、重なる場合は `omx_purpose` 側の slug を変えて意味を分ける。データ移行はしない。

`omx_purpose` の仕様：非階層、`public => false`, `publicly_queryable => false`, `rewrite => false`, `show_ui => true`, `show_in_rest => true`、object types は `omx_industry` と同じ。seed は `marketing` 集客・販促, `customer-support` 接客・問い合わせ, `document` 書類・事務, `creative` 画像・動画・デザイン, `analysis` 分析・レポート, `knowledge` 社内ナレッジ・教育, `sales` 見積・提案・営業。

### 2.3 `omx_provider_type`（提供元）

| 項目 | 値 |
|---|---|
| 語彙（固定4件） | `official` OmochiX公式, `external-service` 外部サービス, `creator` クリエイター, `partner` パートナー（PR） |
| object types | `learn_practice`, `prompt`, `post`, `page`, `omx_resource`（`ai_tool` は対象外：ツールそのものなので） |
| public | false。rewrite なし |
| 語彙の固定 | `pre_insert_term` で4件以外の追加を拒否。term の削除と slug 変更も拒否 |
| 単一値 | 1投稿につき1つ。保存時に2つ以上なら拒否 |

**未分類の扱い（AD-4）**：

```
omx_get_provider_type($post) → 'official' | 'external-service' | 'creator' | 'partner' | 'unclassified'
```
- タームが無ければ `unclassified`。作者・post type からの推測はしない。
- 表示：`unclassified` には提供元バッジを出さない。「公式」とも書かない。
- 絞り込み：「提供元＝公式」で選んでも `unclassified` は含めない。
- 管理画面：一覧に「提供元」列を出し、未分類を目立たせる。
- `learn_format=workflow` と `omx_resource` は**公開ガードで提供元を必須**にする。既存コンテンツの公開状態は変えない。
- 既存コンテンツの分類は、編集者が承認しながら少しずつ付けていく。一括付与はしない。

---

## 3. コンテンツ関連付けモデル

| 関係 | 保存方法 | 方向 |
|---|---|---|
| コンテンツ ⇄ 業種（多対多） | `omx_industry` ターム | 双方向（tax_query） |
| コンテンツ ⇄ 業務目的（多対多） | `omx_purpose` ターム | 同上 |
| 業種 → 推奨ツール（キュレーション） | term meta `industry_tool_ids`（ai_tool ID、最大12） | 業種→ツール |
| 業種 → 先頭に出す Workflow・教材 | term meta `industry_featured_ids`（最大6） | 業種→投稿 |
| 業種 → Motion | term meta `industry_motion_slugs`（最大6。表示時に catalog.json に存在する slug だけ使う） | 業種→Motion |
| Workflow → ツール・プロンプト・教材・Learn・Motion | 既存の `related_tool_ids / related_prompt_ids / related_practice_ids / related_learn_ids / related_motion_slugs` | 既存のまま |
| Workflow → 外部コンテンツ | 新規 `workflow_resource_ids`（omx_resource ID、最大10） | Workflow→resource |
| 外部コンテンツ ⇄ 業種 | `omx_industry` ターム | 双方向 |

取得のルール：公開済み・パスワード無しに限る。`no_found_rows`、件数上限あり。終了ツールは `omochix_get_active_ai_tool_meta_query()` で除外。参照先が非公開・削除済みなら黙って外す。重複表示は ID で除く。
Motion の逆引き（Motion 詳細→業種）は Phase 1〜3 では作らない（Gallery のテンプレを変えないため）。

---

## 4. Workflow データモデル（`learn_practice` 拡張）

判定：`has_term('workflow', 'learn_format', $post)`。workflow 以外の practice は**挙動・必須項目・表示すべて変えない**。

| 指示書の項目 | 保存先 | 新規/既存 | workflow で必須か |
|---|---|---|---|
| 解決する業務課題 | `workflow_problem`（textarea、最大600字） | 新規 | 必須 |
| 対象業種 | `omx_industry` | 新規タクソノミー | 必須（1つ以上） |
| 想定利用者 | `workflow_audience` rows≤6 `{role, note}` | 新規 | 必須 |
| 必要なAIツール | `related_tool_ids` | 既存 | 必須（1つ以上） |
| 関連教材 | `related_practice_ids / related_learn_ids / related_motion_slugs / related_prompt_ids` | 既存 | 任意 |
| 外部コンテンツと出典 | `workflow_resource_ids` ＋ `practice_sources` | 新規＋既存 | 出典（`practice_sources`）は既存ルールで必須 |
| 前提条件 | `practice_prereq` | 既存 | 必須 |
| 費用 | `workflow_cost` rows≤10 `{item, amount, period(once/month/year/usage), note, checked_at}` | 新規 | 必須（無料なら0円の行を1つ） |
| 難易度・所要時間 | `practice_difficulty` / `practice_minutes` | 既存 | 既存ルール |
| 手順 | `practice_steps` | 既存 | 既存ルール |
| プロンプト・テンプレート | `practice_prompts` / `practice_code` | 既存 | 既存ルール |
| 完成イメージ・成果物 | `practice_outcome` / `practice_preview_*` | 既存 | 既存ルール |
| 品質確認・注意事項 | `practice_verify` / `practice_safety` / `practice_troubleshooting` | 既存 | 既存ルール |
| 効果測定方法 | `workflow_metrics` rows≤8 `{metric, how, frequency}` | 新規 | 必須 |
| 成果（実証か想定か） | `workflow_results` rows≤8 `{metric, value, evidence, source_url, source_resource_id, measured_at, note}` | 新規 | 任意 |
| 提供元 | `omx_provider_type` | 新規タクソノミー | 必須 |

`evidence` の語彙と表示：

| 値 | 表示ラベル | 公開に必要な条件 |
|---|---|---|
| `measured` | OmochiX検証済み（測定日） | `measured_at` 必須、`note` に測定条件 |
| `reported` | 提供元・事例の公表値 | `source_url` か `source_resource_id`、`measured_at`（確認日）が必須 |
| `expected` | 想定・目安（成果を保証しません） | 断定表現（必ず・確実・100%・絶対 等）は**公開不可** |

公開ガードの拡張：既存の `omochix_core_get_practice_missing_meta()` に workflow 用の条件を足す（既存条件は変えない）。秘密情報らしき文字列の検出を workflow の新メタにも適用する。既に公開済みの記事は降格せず警告だけ出す（既存方針と同じ）。
JSON 入出力：新メタとタームを追加する（既存 JSON はそのまま読み込める）。

---

## 5. 外部コンテンツの権限・ライセンス管理（`omx_resource`）

CPT の引数：`public=false, publicly_queryable=false, exclude_from_search=true, show_in_nav_menus=false, has_archive=false, rewrite=false, show_in_rest=false, show_ui=true, capability_type='post', map_meta_cap=true`。サポートは title と revisions だけ（本文フィールドを置かない＝転載の器を作らない）。

| メタ | 型 | 公開表示 | 内容・ルール |
|---|---|---|---|
| `resource_url` | url | ○ | https のみ。`wp_http_validate_url`、`javascript:` と `data:` は拒否 |
| `resource_kind` | enum | ○ | video / article / course / template / docs / tool |
| `resource_provider_name`, `resource_provider_url` | text / url | ○ | 提供者 |
| `resource_summary` | text≤160 | ○ | OmochiX が自分の言葉で書く紹介文。原文のコピーは禁止 |
| `resource_license_basis` | enum | ○（区分名） | `link_only` / `quote` / `official_embed` / `licensed` / `partner` / `open_license` |
| `resource_license_detail` | text | ○ | 例：CC BY 4.0、許諾の範囲 |
| `resource_attribution` | text | ○ | クレジット表記 |
| `resource_permission_ref` | text≤64 | ✕ | 社内の参照番号だけを書く（メール本文・個人情報は書かない）。REST・エクスポート・フロントには出さない。編集・閲覧は `edit_others_posts` |
| `resource_embed_provider` | enum | — | `official_embed` の場合のみ。初期は `youtube` だけ（WP の oEmbed 経由。任意の iframe は不可） |
| `resource_lang`, `resource_price` | enum | ○ | ja/en/other、free/freemium/paid |
| `resource_verified_at` | date | ○ | 最終確認日 |
| `resource_thumbnail` | image_id | ○ | `licensed`・`partner`・`open_license` の場合のみ。外部画像のホットリンクや無断保存は不可 |

公開ガード（下書きから公開への遷移）：提供元が必須（`omx_provider_type`）。`link_only` 以外は `license_detail` が必須。`licensed` と `partner` は `permission_ref` が必須。`verified_at` が必須。180日を超えるとフロントで「要再確認」と表示し、ハブのゲート（§6）でも数えない。
表示のルール：カードには自分の言葉の紹介文、提供者、区分、確認日、外部リンク（`rel="noopener"`）。partner は「PR」ラベルを付け `rel="sponsored noopener"`。サーバーから外部 URL を自動で巡回することはしない（SSRF 防止のため。リンク確認は人が行う）。

---

## 6. SEO 仕様

### 6.1 index 判定（独自コンテンツの充実度ゲート）

親業種ハブを index にするのは、**次をすべて満たしたとき**だけ：

| # | 条件 | 意図 |
|---|---|---|
| G1 | `industry_hub_ready = true`（`manage_categories` 権限を持つ編集者が立てる） | 人の判断を必須にする |
| G2 | `industry_lead` が120字以上 | 導入文が独自に書かれている |
| G3 | `industry_challenges` が3件以上で、各本文が80字以上 | 課題の独自解説 |
| G4 | `industry_usecases` が3件以上で、各本文が80字以上、2つ以上の業務目的にまたがる | 活用方法の独自解説 |
| G5 | その業種の workflow が**提供元 official で1件以上**公開済み（公開ガード通過済み） | OmochiX 独自の実践コンテンツ |
| G6 | `industry_verified_at` が180日以内 | 鮮度 |
| G7 | `industry_sources` が1件以上（課題・統計を書いた場合） | 根拠 |

外部コンテンツ・ツールカード・プロンプトの**件数は G1〜G7 に数えない**（集めただけのページを index しないため）。
ゲートを満たさない場合：noindex,follow、sitemap から除外、ハブ一覧では「準備中」（リンクしない）。判定ロジックは関数1つにまとめ、管理画面の業種編集画面に未達の項目を表示する。

### 6.2 URL ごとの方針

| URL | robots | canonical | sitemap | JSON-LD |
|---|---|---|---|---|
| `/learn/industries/` | index（ゲートを満たす業種が1つ以上ある場合。0件なら noindex） | 自己 | 条件付き | CollectionPage, BreadcrumbList |
| `/learn/industries/{親}/` | §6.1 のとおり | 自己 | 条件付き | CollectionPage, ItemList, BreadcrumbList |
| `/learn/industries/{サブ}/` | noindex,follow | 親 + `?sub=` ではなく親の素のURL | 除外 | — |
| `?purpose=` `?level=` `?provider=` `?type=` `?q=` `?sub=` | noindex,follow | 素の URL | — | — |
| `/learn/industries/{親}/page/N/` | ページ送りは作らない（各セクション件数に上限） | — | — | — |
| Workflow 単体 | 既存 practice の方針に従う | 自己 | 既存 | Article。`SoftwareApplication` は出さない（Core のツール詳細だけ） |
| `omx_purpose` / `omx_provider_type` / `omx_resource` | 公開URL無し | — | 明示的に除外（`slim_seo_sitemap_taxonomies` / `post_types`） | — |

title の雛形は「{業種}のAI活用｜ツール・教材・導入ワークフロー | OmochiX」。description は `industry_lead` の先頭から作る。実装は既存と同じ Slim SEO のフィルタ（`wp_robots`, `slim_seo_robots_index`, `slim_seo_canonical_url`, `slim_seo_meta_description`, `slim_seo_breadcrumbs_links`）。

---

## 7. 業種別 UI・URL 構造

### 7.1 URL（新規は `/learn/industries/` 配下だけ）

| URL | 実体 | 備考 |
|---|---|---|
| `/learn/industries/` | 固定ページ `learn/industries`（ステージングで作る）＋ `template_include`（優先度7） | Learn 子孫の共通テンプレート・兄弟 NEXT 送りからは除外する |
| `/learn/industries/{industry}/` | `omx_industry` のタームアーカイブ | DB にページを増やさない |
| `/learn/practice/{slug}/` | workflow（既存の URL 体系） | 変更なし |

衝突確認（Phase 1 のテスト項目）：`learn/motion`, `learn/motion/{slug}`, `learn/motion/compose`, `learn/practice`, `learn/{field}`（分野ハブ8件）, `learn/field|format|usecase|ai|tech/{term}`, `learn/svg-motion`（未マージ）, `labs`（未マージ）。業種 slug に `practice` `motion` `field` などの予約語が使われた場合は保存時に拒否する。
未確認：本番とステージングに `learn/industries` で始まるページが既にあるか（§12 B-2）。

### 7.2 画面

v0.1 §4 の構成を引き継ぎ、次を追加・確定する：
- 業種ハブのセクション順：課題 → AI活用方法 → 実践ワークフロー → 推奨ツール → 関連教材（Practice・Learn・Motion）→ プロンプト → 外部コンテンツ → 出典。中身が空のセクションは見出しごと出さない。
- 提供元バッジ：公式・外部サービス・クリエイター・PR の4種。未分類はバッジ無し。
- 成果バッジ：検証済み・公表値・想定の3種。色だけに頼らず文字でも区別する。
- 絞り込み：GET フォームで、JS が無くても動く。業務目的・難易度・提供元・種別・サブ業種・キーワード（最大60字）。
- 対応幅：375 / 768 / 1280、light / dark、`prefers-reduced-motion`。CSS は分離ファイルに置き、`style.css` は変更しない。

---

## 8. Phase 1〜3 の実装順序

### Phase 1（Core のみ。テーマへの影響ゼロ。Labs・SVG と衝突しない）

| 順 | ID | タスク |
|---|---|---|
| 1 | P1-01 | テスト用の stub と `industry-hub.yml` を用意する（先にテストが失敗する状態を作る） |
| 2 | P1-02 | `industry-taxonomies.php`：3タクソノミー、seed、rewrite version、予約語の拒否、2階層制限 |
| 3 | P1-03 | `provider.php`：未分類の判定、4語彙の固定、単一値 |
| 4 | P1-04 | `industry-term-meta.php`：スキーマ、サニタイズ（practice のサニタイザを関数ごと再利用）、ゲート判定関数 |
| 5 | P1-05 | `admin/industry-term-fields.php`：nonce、権限、未達項目の表示 |
| 6 | P1-06 | `resource-post-type.php` / `resource-meta-schema.php` / メタボックスと保存処理 / 公開ガード |
| 7 | P1-07 | `workflow-meta-schema.php`：`workflow` format の seed、新メタ、ガード拡張 |
| 8 | P1-08 | JSON 入出力の拡張（`permission_ref` は出力しない） |
| 9 | P1-09 | `industry-relations.php`：取得関数（表示はまだ無し） |
| 10 | P1-10 | `omochix-core.php` への組み込み、版番号 1.10.0、readme |

### Phase 2（テーマ。Labs の扱いが決まってから着手）

P2-01 ルーティングと template_include → P2-02 GET サニタイズ → P2-03 SEO（ゲート連動）→ P2-04 `page-learn-industries.php` → P2-05 `taxonomy-omx_industry.php` と template-parts → P2-06 CSS・a11y → P2-07 `shell.php` に1行 → P2-08 Learn ハブの導線（`page-learn.php`。Labs のマージ後）→ P2-09 ステージングで固定ページを作る手順を Runbook に追記。

### Phase 3（Workflow Library）

P3-01 `single-learn_practice.php` に workflow セクションを条件付きで追加 → P3-02 管理パネル → P3-03 resource ピッカー → P3-04 一覧の導線 → P3-05 6業種の JSON 雛形（成果は空。ガードで公開できない状態が正しい）→ P3-06 編集ガイド → P3-07 Prompts の CSV に任意列を追加。

実コンテンツ（業種の解説文・workflow 本文・外部コンテンツの許諾）は開発とは別トラックで、運営者の承認を得て進める。

---

## 9. 受け入れ基準

### Phase 1
- [ ] 既存 CI の全テスト（PHP 7本・Node 4本・`php -l`）が PHP 8.1 と 8.3 で green。追加した `industry-hub.yml` も green
- [ ] 既存の `register_post_type` / `register_taxonomy` の引数が変わっていないことをテストで確認（snapshot 比較）
- [ ] 既存メタキーの追加・削除・型変更なし。`catalog.json` と schema の差分なし
- [ ] seed が冪等で、rewrite flush はバージョンごとに1回だけ
- [ ] 提供元が未設定のものは `unclassified` を返し、`official` を返さない
- [ ] workflow 以外の practice は、必須判定と公開ガードの結果が拡張前と同じ（既存テストの96項目＋回帰ケース）
- [ ] `omx_resource` は公開URL・REST・検索・sitemap のどれにも出ない。`permission_ref` はエクスポートにも出ない
- [ ] 権限が無い場合と nonce が不正な場合は、term meta と resource meta が保存されない
- [ ] 本番・DB・外部サービスへの変更なし

### Phase 2
- [ ] 既存 URL の回帰表（v0.1 §7.3 と Motion 53件の詳細ページ）で、ステータス・canonical・robots が変わらない
- [ ] ゲート未達の業種は noindex で sitemap に出ず、達した業種だけ index になる
- [ ] 絞り込みした状態は noindex,follow で、canonical は素の URL
- [ ] axe で重大違反0件。キーボードだけで操作できる。JS 無効でも動く。375/768/1280 × light/dark で表示が崩れない
- [ ] 1ページあたりのクエリ数が上限内（基準値は Phase 2 の着手時にローカル計測して決める）

### Phase 3
- [ ] workflow ページで12項目すべてが表示される。非 workflow の practice の表示差分は0
- [ ] `expected` に断定表現があると公開できない。`measured` と `reported` は根拠が無いと公開できない
- [ ] partner の外部コンテンツに PR 表記と `rel="sponsored"` が付く

---

## 10. テスト計画

| 種別 | 内容 | 置き場所 |
|---|---|---|
| 単体（stub） | 登録・seed・flush・予約語・2階層制限・権限・語彙固定・未分類・term meta サニタイズ・ゲート判定（G1〜G7 の境界値） | `tests/industry-hub/run.php` |
| 単体（stub） | resource：非公開性、URL 検証、ライセンスごとの必須条件、エクスポートからの除外 | `tests/industry-hub/resource.php` |
| 単体（stub） | workflow：必須条件、証拠区分、断定表現、非 workflow の不変性 | `tests/industry-hub/workflow.php` |
| 単体（stub） | SEO：robots・canonical・sitemap 除外・rewrite 非衝突 | `tests/industry-hub/seo.php`（Phase 2） |
| セキュリティ | GET への XSS・配列注入・長すぎる q、`javascript:` URL、権限・nonce 欠落 | 上記に含める |
| 既存回帰 | `tests.yml` の全テストを変更せずに実行 | 既存 |
| 結合 | ローカル WP + SQLite（本番から分離）で URL 回帰表、スクリーンショット、axe | 手順を `docs/` に記載 |
| ステージング | 運営者の承認後、読み取り確認とテーマ/プラグインの反映確認 | Runbook |

`stub.php` は既存の `tests/learn-practice/stub.php` を**読み込んで拡張**する（コピーしない）。既存の stub に足りない関数があれば、新しい stub 側で `function_exists` で守って定義する。

---

## 11. 既存ブランチとの競合回避策

| 共有ファイル | Labs #56 | SVG #55 | Industry Hub | 回避策 |
|---|---|---|---|---|
| `wp-plugin/**` | 変更しない | 変更しない | Phase 1 の全部 | **衝突なし**。Phase 1 は先に進められる |
| `.github/workflows/tests.yml` | 変更しない | テストを1行追加 | **触らない** | 別ファイル `industry-hub.yml` |
| `functions.php` | require 追加（末尾） | require 追加（中ほど） | Phase 2 で require 1行 | 末尾に追加。衝突しても1行で解消できる。Labs がマージされた後に rebase |
| `inc/shell.php` | `omochix_shell_enabled()` の最終行 | `page_enabled` 配列 | Phase 2 で `is_tax` 配列と `page_enabled` に追加 | **Phase 2 の最後に1コミットだけ**で変更し、先にマージされた方に合わせて rebase。テーマ側の他ファイルからは shell の内部に触らない |
| `page-learn.php` | 導線を追加 | — | 導線を追加 | Labs のマージ後に着手（P2-08） |
| `style.css`（版番号） | 1.28.0 | — | 版番号を上げる | 版番号の更新はリリース用 PR に分け、Labs の後の番号にする |
| `single-learn_practice.php` | — | — | Phase 3 | 衝突なし |

運用ルール：
- worktree は `/Users/omochi/Documents/omochix-industry-hub-wt`、ブランチ `feature/industry-hub-p1`、ベースは `origin/main` @ 94152a7（ローカル `main` は使わない）。
- Phase ごとに 1 PR。Draft で出し、レビュー後にマージ。Labs と SVG のブランチには一切 commit しない。
- 既存の作業ツリー・ローカル `main`・stash・prunable な worktree には触らない。

---

## 12. 残っているブロッカーと未確認事項

| ID | 内容 | 種別 |
|---|---|---|
| B-1 | 新規 worktree の作成、設計書のリポジトリ保存、commit・push・PR の承認 | 承認待ち |
| B-2 | ステージングでの読み取り確認：`learn_usecase` の語彙、`learn/industries` 系のページの有無、Slim SEO の sitemap 設定 | 未確認（ステージングのアクセス手段も未確認） |
| B-3 | Industry Hub（P0 外）を P0 作業と並行して進めてよいかの運営判断 | 承認待ち |
| B-4 | パートナー提供の PR 表記の文言と収益関係の扱い | 法務判断 |
| B-5 | 外部コンテンツを `link_only` と `youtube` 埋め込みだけで始めてよいか | 承認待ち |
| B-6 | 業種 slug（特に `web-production`）とサブ業種の運用方針 | 承認待ち（暫定案あり） |
| B-7 | Labs #56 のマージ時期（Phase 2 の P2-07 と P2-08 の着手条件） | 未確定 |
| B-8 | ゲートの閾値（120字・80字・3件・180日）の妥当性 | 承認待ち（暫定案） |
| — | 手元の PHP は 8.5.8 で、CI の 8.1 / 8.3 による手元検証はしていない | 制約（CI で担保） |

---

## 13. Phase 1 実装状況（2026-11-05、`feature/industry-hub-p1`）

ベース：`94152a7`。プラグインのみの変更。テーマ・`tests.yml`・`functions.php`・`shell.php`・`page-learn.php` は未変更。

| 項目 | 実装 | 主なファイル |
|---|---|---|
| 読み込み | `omochix-core.php` に require 1行。登録は `init` 優先度 11 | `includes/industry-hub.php` |
| 業種・業務目的・提供元 | 3タクソノミー。業種は Phase 1 では非公開（URLなし）。提供元は固定4語彙・1件のみ・未設定は未分類 | `includes/industry-taxonomies.php`, `includes/provider.php` |
| 業種ハブの編集内容 | term meta 11キー、編集画面、内容ゲート G1〜G7 | `includes/industry-term-meta.php`, `admin/industry-term-fields.php` |
| Motion 参照 | slug で参照。`catalog.json` に存在する slug だけを使い、テーマの読み込み関数が無ければ何も返さない | 同上 |
| 外部コンテンツ台帳 | 非公開 CPT `omx_resource`、ライセンス記録、公開ガード、社内参照番号の保護 | `includes/resource-post-type.php`, `includes/resource-meta-schema.php`, `admin/resource-meta-boxes.php` |
| Workflow データ基盤 | `learn_practice` に `workflow_*` 6キー、Workflow ガード（classic・REST）、リビジョン検知、編集パネル。`workflow` 形式のタームは Phase 1 では作らない | `includes/workflow-meta-schema.php`, `admin/workflow-meta-panel.php` |
| 読み出し | 業種別の取得、キュレーション、台帳カード | `includes/industry-relations.php` |
| REST | 3タクソノミーのターム一覧は、編集権限が無いユーザーには 401 | `includes/industry-taxonomies.php` |

テスト（`tests/industry-hub/`、CI は `.github/workflows/industry-hub.yml`）：

```bash
php tests/industry-hub/compat.php      # 既存登録のスナップショット比較・catalog.json 不変
php tests/industry-hub/run.php         # タクソノミー・提供元・多対多・REST 読み取り制限
php tests/industry-hub/term-meta.php   # term meta・編集画面・Motion・内容ゲート
php tests/industry-hub/resource.php    # 台帳の非公開性・ライセンス・ガード・保存
php tests/industry-hub/workflow.php    # Workflow メタ・ガード・リビジョン・パネル
php tests/industry-hub/relations.php   # 読み出し側
php tests/industry-hub/wp-integration.php /path/to/throwaway/wordpress   # 実 WordPress（CI 対象外、使い捨て環境のみ）
```

Phase 1 で未着手・保留の項目：
- P1-08 practice JSON 入出力への workflow メタと新タクソノミーの追加（Phase 3 で `workflow` タームを作るときに合わせて行う）。
- 版番号の更新（Core 1.10.0）はリリース用 PR で行う。
- ステージングでの確認（B-2）はアクセス手段が未確認のため未実施。
