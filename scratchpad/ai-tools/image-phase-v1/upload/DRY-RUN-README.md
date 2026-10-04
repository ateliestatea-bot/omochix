# Image Batch 01 — Updater dry-run 手順（手動実行用）

- 使用ファイル：`ai-tools-image-update-batch-01.json`
  - sha256 `feb1e23d935e52729f17bbc74e2f648f31f616861940ae604fc0ae555aeda67a`
- 含まれるキー：`slug`、`tool_logo_attachment_id`、`featured_image_attachment_id` のみ
  - title、本文、SEO、meta は含まない
- rollback：`ai-tools-image-rollback-snapshot-batch-01.json`

## 手順

1. wp-admin → AI Tool Content Updater で上記 JSON をアップロードし、**dry-run**（確認）だけを実行する
2. 結果を以下の期待値と照合する
3. **Apply はしない**（再承認待ち）

## 期待される dry-run 結果

- 対象：8 件
- 更新予定：8 件
- 変更なし：0 件
- エラー：0 件

| slug | field | before | after |
|---|---|---|---|
| veo | featured_image_attachment_id | （未設定）0 | 1087 |
| kling-ai | featured_image_attachment_id | （未設定）0 | 1083 |
| runway | tool_logo_attachment_id | （未設定）0 | 1088 |
| runway | featured_image_attachment_id | （未設定）0 | 1086 |
| midjourney | featured_image_attachment_id | （未設定）0 | 1084 |
| claude-code | tool_logo_attachment_id | （未設定）0 | 1089 |
| claude-code | featured_image_attachment_id | （未設定）0 | 1080 |
| notebooklm | featured_image_attachment_id | （未設定）0 | 1085 |
| chatgpt | featured_image_attachment_id | 566 | 1079 |
| gemini | featured_image_attachment_id | 636 | 1096 |

- 差分に上記 10 行以外（post_content、slim_seo.*、その他の meta）が出たら **Apply しない**
- ChatGPT／Gemini の Logo（54／634）は JSON に含めないので変化しない

## Apply 時（承認後）の注意

- Apply 直前に Updater が自動取得する before snapshot JSON をダウンロードして保存する
- rollback：
  - ChatGPT／Gemini は `rollback_updater_json`（566／636 に戻す）を Updater で実行する
  - それ以外は wp-admin でアイキャッチ画像を削除し、tool_logo を解除する（Updater では 0 を指定できない）
- 旧 attachment 566／636 は削除しない
