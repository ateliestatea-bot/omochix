# Image Phase v1 Batch 01 — Production Final Audit

- IMAGE_PHASE_V1_BATCH_01_STATUS: **PASS**
- audit date: 2026-10-04
- 対象：AI Tool Content Updater で `upload/ai-tools-image-update-batch-01.json` を Apply した後の production
- 方法：READ ONLY
  - public REST（`/wp-json/wp/v2/ai-tools`、`/wp-json/wp/v2/media`）
  - 公開ページ HTML の GET
  - ブラウザでの表示確認
  - 監査による production への書き込みはなし
- 比較基準：Apply 前に取得した REST の state（rollback snapshot `upload/ai-tools-image-rollback-snapshot-batch-01.json` と同じ取得値）
- target tools: 8
- expected image fields: 10

## Final attachment IDs

| Tool | Logo | HERO |
|---|---|---|
| Veo | — | 1087 |
| Kling AI | — | 1083 |
| Runway | 1088 | 1086 |
| Midjourney | — | 1084 |
| Claude Code | 1089 | 1080 |
| NotebookLM | — | 1085 |
| ChatGPT | （変更なし：54） | 1079 |
| Gemini | （変更なし：634） | 1096 |

Gemini の production filename：`omochix-ai-tool-gemini-hero-v3.webp`（運営者承認により正式値。内容は QA PASS 済みの v3 で、sha256 `d48755fa…` が一致）

## Checks

| Check | Result | 確認内容 |
|---|---|---|
| IMAGE_ID_CHECK | PASS | 8 ツール 10 フィールドが期待値と一致（REST） |
| IMAGE_HTTP_CHECK | PASS | 10 ファイルの原寸と thumbnail／medium／large が HTTP 200、image/webp |
| ALT_CHECK | PASS | 10 件の alt が UPLOAD-PLAN と一致。寸法（HERO 1280×720、Logo 512×512）も不変 |
| DETAIL_PAGE_CHECK | PASS | 8 ページすべて HTTP 200。HERO（1024×576 派生）が読み込まれる。Runway／Claude Code の Logo（150×150 派生）が読み込まれる |
| ARCHIVE_CARD_CHECK | PASS | `/ai-tools/page/3/` で Runway／Claude Code のカード Logo が表示される |
| RESPONSIVE_CHECK | PASS | desktop 1024px と mobile 375px で横スクロールなし。mobile の HERO 枠は 337×189（16:9） |
| LIGHT_DARK_CHECK | PASS | 詳細ページ（Runway desktop、Claude Code mobile）とアーカイブで、両モードとも表示に問題なし |
| SEO_IMAGE_CHECK | PASS | 8 ページすべてで og:image、twitter:image、JSON-LD の SoftwareApplication.image が新 HERO。og:image:alt は計画 alt。JSON-LD のパースエラーなし |
| BROKEN_IMAGE_CHECK | PASS | broken image 0（lazy 画像を含む） |
| OLD_ATTACHMENT_CHECK | PASS | 566、636、54、634 が存在し、変更されていない |
| NON_TARGET_REGRESSION_CHECK | PASS | 対象外 44 ツールの image ID は不変。Cursor／Perplexity／Sora は従来どおり表示 |
| NON_IMAGE_FIELD_CHECK | PASS | 52 ツールすべてで title、content、meta、modified に変更なし |
| ERROR_CHECK | PASS | 詳細 11 ページ、トップ、アーカイブ 1〜5、検索のすべてが HTTP 200。PHP エラー表示 0、JS console エラー 0 |

## 記録事項

- ChatGPT old HERO 566 retained
- Gemini old HERO 636 retained
- ChatGPT logo 54 unchanged
- Gemini logo 634 unchanged
- Cursor 1076／1077 unchanged
- Perplexity 630／632 unchanged
- Sora unchanged（画像未設定のまま）
- other 44 AI Tool image IDs unchanged
- 6 tools received WordPress `has-post-thumbnail` automatically; this is expected
  - 対象：Veo、Kling AI、Runway、Midjourney、Claude Code、NotebookLM
  - 理由：featured image を新規に設定したため、WordPress が `class_list` に自動で付与した
- no rollback required

## Pre-existing issues（not caused by this Apply）

- Perplexity の og:image:alt：ファイル名由来（UUID 形式）
- Sora の og:image:alt：サイト既定画像の alt（「ChatGPT Image …」）
- Gemini logo（634）と Perplexity logo（630）の alt：ファイル名由来
