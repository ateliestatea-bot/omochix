# ChatGPT — Image QA（Image Phase v1 Batch）

監査日：2026-10-03
production：READ ONLY（REST の GET のみ）

## production 現状

| 項目 | 値 |
|---|---|
| post_id | 53 |
| tool_logo_attachment_id | 54 |
| featured_image_attachment_id | 566 |
| current logo | {"url": "/uploads/2026/09/OAI_OpenAI-Blossom_Black.png", "dimensions": "716x716", "mime": "image/png", "bytes": 15987, "alt": "OAI OpenAI Blossom Black"} |
| current HERO | {"url": "/uploads/2026/09/chatgpt-hero.png", "dimensions": "1672x941", "mime": "image/png", "bytes": 1080958, "alt": "ChatGPTのAIアシスタント画面"} |
| Logo 判定 | REPLACE → BLOCKED |
| HERO 判定 | REPLACE → NEED_GENERATION |

## Logo

### Logo：BLOCKED

- 理由：公式ロゴのダウンロードボタン（openai.com/brand「ロゴをダウンロード」）は、使用条件への同意チェックボックス（download-logos-checkbox）にチェックしないと有効にならない。規約への同意はユーザー本人の判断が必要なため取得していない。ページ表示用の SVG を CDN から直接取得することも、同意手続きの回避になるため行わない。ユーザーが同意してロゴパックを取得し、sources/chatgpt/ に配置すれば作成できる
- 公式情報源：https://openai.com/brand/ （「ロゴをダウンロード」は同意チェックが必要。ロゴ使用許可の申請・質問：partnercomms@openai.com）
- 計画ファイル名（解除後）：`omochix-ai-tool-chatgpt-logo.webp`、alt「ChatGPT ロゴ」
- **ready ファイルは作成していない。** 非公式ロゴ・生成ロゴは使用しない。解除まで頭文字タイル（現行 fallback）で運用する

## HERO：READY（初期状態：NEED_GENERATION）

- 生成していない（この環境には安全に使える画像生成機能がない。外部サービス・API key は使っていない）
- spec／prompt／negative／QA チェックリスト：`prompts/chatgpt-hero.md`
- 計画ファイル名：`omochix-ai-tool-chatgpt-hero.webp`
- alt：「対話を中心に多様なタスクが広がる、ChatGPTのAIアシスタントのイメージ」
- 生成後、`prompts/chatgpt-hero.md` §4 の全項目を実施し、本ファイルに結果を追記する

## Rollback

- logo：Updater で tool_logo_attachment_id を旧 ID 54 に戻す
- hero：Updater で featured_image_attachment_id を旧 ID 566 に戻す
- いずれも Apply 前の Updater before-snapshot JSON を保存する。旧 attachment は削除しない

---

## HERO QA 結果（2026-10-03）：**PASS**

- 受領ファイル：会話添付 `2.webp`
  - sha256 `10616833105c5e37b4b80c727735eada09b3d311ed535144f5150bf7116282f2`
- ready 配置：`ready/chatgpt/omochix-ai-tool-chatgpt-hero.webp`（無加工コピー、sha256 一致）
- 確認方法：100% の上下半分を目視、疑わしい領域を 200% で確認、340px contact sheet、数値解析（色相・外縁輝度・ブロックノイズ）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 192,396 bytes（目標 ≤250KB を満たす） |
| metadata | EXIF／XMP／ICC なし（webpinfo で VP8 チャンクのみ） |
| faces / people / hands | なし |
| fake UI | ウィンドウボタン・タブ・アイコン列なし |
| artifact / clipping | 目立つ破綻なし。主役はフレーム端で切れていない |
| mobile 340px | 主役のシルエットを判別できる（contact sheet で確認） |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 23.8、下 66.8、左 22.0、右 20.4（基準 ≥14 を満たす） |
| compression | block-edge 比 1.098（Cursor は 1.161）。目立つバンディングなし |
| color | 高彩度の色相：シアン〜青 55.5%、紫〜マゼンタ 42.3%、赤系 2.2%、緑 0.0%、黄 0.0% |
| central safe area | 高輝度画素の中央 60% 内の比率 62.6%、重心 (690, 365) |

### 内容確認（prompt §3 の negative と制作ルールとの照合）

- 対応：中央の光の球、文字のない吹き出し、文書シート・風景フレーム・数字のない棒グラフ・ガラスブロック。prompt と一致
- 文書シートは角丸バーのみで、疑似文字なし（200% で確認）。棒グラフに数字なし
- OpenAI Blossom・ChatGPT ロゴ・六角の結び目形状：なし（球の中は S 字の渦）。球に顔・目：なし
- モデル名・実在のチャット UI：なし（旧 HERO 566 の不合格理由を解消）
- 主役（球と内側の吹き出し）は中央 60% 内
