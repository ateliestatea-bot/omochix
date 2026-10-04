# NotebookLM — Image QA（Image Phase v1 Batch）

監査日：2026-10-03
production：READ ONLY（REST の GET のみ）

## production 現状

| 項目 | 値 |
|---|---|
| post_id | 659 |
| tool_logo_attachment_id | 0 |
| featured_image_attachment_id | 0 |
| current logo | なし（頭文字タイル） |
| current HERO | なし（OmochiX プレースホルダー） |
| Logo 判定 | MISSING → BLOCKED |
| HERO 判定 | MISSING → NEED_GENERATION |

## Logo

### Logo：BLOCKED

- 理由：Google の Product icons は「Ask first」（許可申請が必要）。NotebookLM 専用の公開ブランドキットなし。ブランド名移行（Gemini Notebook）の動向もあるため、許可とブランド確定まで登録しない
- 公式情報源：https://about.google/brand-resource-center/guidance/ （products-and-services に NotebookLM の掲載なし）
- 計画ファイル名（解除後）：`omochix-ai-tool-notebooklm-logo.webp`、alt「NotebookLM ロゴ」
- **ready ファイルは作成していない。** 非公式ロゴ・生成ロゴは使用しない。解除まで頭文字タイル（現行 fallback）で運用する

## HERO：READY（初期状態：NEED_GENERATION）

- 生成していない（この環境には安全に使える画像生成機能がない。外部サービス・API key は使っていない）
- spec／prompt／negative／QA チェックリスト：`prompts/notebooklm-hero.md`
- 計画ファイル名：`omochix-ai-tool-notebooklm-hero.webp`
- alt：「複数の資料がつながり1冊のノートと音声にまとまる、NotebookLMによるリサーチ支援のイメージ」
- 生成後、`prompts/notebooklm-hero.md` §4 の全項目を実施し、本ファイルに結果を追記する

## Rollback

- hero：現状 0（未設定）。Updater では 0 を指定できないため、wp-admin でアイキャッチ画像を削除する
- いずれも Apply 前の Updater before-snapshot JSON を保存する。旧 attachment は削除しない

---

## HERO QA 結果（2026-10-03）：**PASS**

- 受領ファイル：会話添付 `7.webp`
  - sha256 `35940f8ce895c33cc77d9da3f92e7c5dbe4933499508f4e44f0bac0b86b6bea5`
- ready 配置：`ready/notebooklm/omochix-ai-tool-notebooklm-hero.webp`（無加工コピー、sha256 一致）
- 確認方法：100% の上下半分を目視、疑わしい領域を 200% で確認、340px contact sheet、数値解析（色相・外縁輝度・ブロックノイズ）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 187,160 bytes（目標 ≤250KB を満たす） |
| metadata | EXIF／XMP／ICC なし（webpinfo で VP8 チャンクのみ） |
| faces / people / hands | なし |
| fake UI | ウィンドウボタン・タブ・アイコン列なし |
| artifact / clipping | 目立つ破綻なし。主役はフレーム端で切れていない |
| mobile 340px | 主役のシルエットを判別できる（contact sheet で確認） |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 20.4、下 37.1、左 35.9、右 16.3（基準 ≥14 を満たす） |
| compression | block-edge 比 1.086（Cursor は 1.161）。目立つバンディングなし |
| color | 高彩度の色相：シアン〜青 58.9%、紫〜マゼンタ 39.6%、赤系 1.3%、緑 0.0%、黄 0.0% |
| central safe area | 高輝度画素の中央 60% 内の比率 70.9%、重心 (703, 382) |

### 内容確認（prompt §3 の negative と制作ルールとの照合）

- 対応：周囲の資料シート（画像枠＋バー）から光の糸が中央の開いた本に流れ込み、右上にミント色の音声波形。prompt と一致
- 読める文書文字：なし（本のページ・小パネルを 200% で確認。バーと画像枠のみ）
- Google 配色：なし（緑・黄 0%。ミントはシアン系の色相）。NotebookLM ロゴ：なし
- 主役（本 x≈340–1000、y≈360–570）は中央 60% 内（下端はほぼ境界）
