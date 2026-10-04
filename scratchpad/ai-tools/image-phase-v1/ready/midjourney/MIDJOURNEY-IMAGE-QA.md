# Midjourney — Image QA（Image Phase v1 Batch）

監査日：2026-10-03
production：READ ONLY（REST の GET のみ）

## production 現状

| 項目 | 値 |
|---|---|
| post_id | 59 |
| tool_logo_attachment_id | 0 |
| featured_image_attachment_id | 0 |
| current logo | なし（頭文字タイル） |
| current HERO | なし（OmochiX プレースホルダー） |
| Logo 判定 | MISSING → BLOCKED |
| HERO 判定 | MISSING → NEED_GENERATION |

## Logo

### Logo：BLOCKED

- 理由：公開ロゴキットなし。www.midjourney.com/press・/brand は 404（2026-10-03）。Trademark Policy は改変禁止・推奨示唆禁止。公式高解像度 asset が確認できないため使用しない（press@midjourney.com に依頼が必要）
- 公式情報源：https://docs.midjourney.com/hc/en-us/articles/32084281102349-Midjourney-Trademark-Policy （問い合わせ：press@midjourney.com）
- 計画ファイル名（解除後）：`omochix-ai-tool-midjourney-logo.webp`、alt「Midjourney ロゴ」
- **ready ファイルは作成していない。** 非公式ロゴ・生成ロゴは使用しない。解除まで頭文字タイル（現行 fallback）で運用する

## HERO：READY（初期状態：NEED_GENERATION）

- 生成していない（この環境には安全に使える画像生成機能がない。外部サービス・API key は使っていない）
- spec／prompt／negative／QA チェックリスト：`prompts/midjourney-hero.md`
- 計画ファイル名：`omochix-ai-tool-midjourney-hero.webp`
- alt：「霧の中に多様な画風のキャンバスが浮かぶ、Midjourneyによる画像生成のイメージ」
- 生成後、`prompts/midjourney-hero.md` §4 の全項目を実施し、本ファイルに結果を追記する

## Rollback

- hero：現状 0（未設定）。Updater では 0 を指定できないため、wp-admin でアイキャッチ画像を削除する
- いずれも Apply 前の Updater before-snapshot JSON を保存する。旧 attachment は削除しない

---

## HERO QA 結果（2026-10-03）：**PASS**

- 受領ファイル：会話添付 `6.webp`
  - sha256 `bc6d5df8c0dcd51df0d17f4cf969dd93012169ad2af4e5e438a72318e7cce687`
- ready 配置：`ready/midjourney/omochix-ai-tool-midjourney-hero.webp`（無加工コピー、sha256 一致）
- 確認方法：100% の上下半分を目視、疑わしい領域を 200% で確認、340px contact sheet、数値解析（色相・外縁輝度・ブロックノイズ）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 204,416 bytes（目標 ≤250KB を満たす） |
| metadata | EXIF／XMP／ICC なし（webpinfo で VP8 チャンクのみ） |
| faces / people / hands | なし |
| fake UI | ウィンドウボタン・タブ・アイコン列なし |
| artifact / clipping | 目立つ破綻なし。主役はフレーム端で切れていない |
| mobile 340px | 主役のシルエットを判別できる（contact sheet で確認） |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 58.2、下 69.5、左 43.0、右 23.9（基準 ≥14 を満たす） |
| compression | block-edge 比 1.105（Cursor は 1.161）。目立つバンディングなし |
| color | 高彩度の色相：シアン〜青 7.0%、紫〜マゼンタ 59.9%、赤系 31.4%、緑 0.0%、黄 0.0% |
| central safe area | 高輝度画素の中央 60% 内の比率 62.5%、重心 (616, 331) |

### 内容確認（prompt §3 の negative と制作ルールとの照合）

- 対応：ギャラリー空間に浮かぶキャンバス、中央のキャンバスが顔料の飛沫から描き出される。prompt と一致
- 帆船・船・帆：なし。署名・ラベル・額のキャプション：なし（各キャンバス隅を 200% で確認）。名画の模写：なし（抽象的なマーブル／厚塗り）
- 主役（中央キャンバス x≈505–850）は中央 60% 内
- 注記（spec との差分）：画風が油彩・水彩・木版調などに分かれず、全体にマーブル／アルコールインク調で似ている。コンセプトは伝わるため PASS
