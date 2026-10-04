# Veo — Image QA（Image Phase v1 Batch）

監査日：2026-10-03
production：READ ONLY（REST の GET のみ）

## production 現状

| 項目 | 値 |
|---|---|
| post_id | 678 |
| tool_logo_attachment_id | 0 |
| featured_image_attachment_id | 0 |
| current logo | なし（頭文字タイル） |
| current HERO | なし（OmochiX プレースホルダー） |
| Logo 判定 | MISSING → BLOCKED |
| HERO 判定 | MISSING → NEED_GENERATION |

## Logo

### Logo：BLOCKED

- 理由：Google の Product icons は Brand Resource Center で「Ask first」（許可申請が必要）。Veo 専用の公開ブランドキットなし。許可取得まで画像を登録しない（頭文字タイルで運用）
- 公式情報源：https://about.google/brand-resource-center/guidance/ （products-and-services に Veo の掲載なし）
- 計画ファイル名（解除後）：`omochix-ai-tool-veo-logo.webp`、alt「Veo ロゴ」
- **ready ファイルは作成していない。** 非公式ロゴ・生成ロゴは使用しない。解除まで頭文字タイル（現行 fallback）で運用する

## HERO：READY（初期状態：NEED_GENERATION）

- 生成していない（この環境には安全に使える画像生成機能がない。外部サービス・API key は使っていない）
- spec／prompt／negative／QA チェックリスト：`prompts/veo-hero.md`
- 計画ファイル名：`omochix-ai-tool-veo-hero.webp`
- alt：「光の粒子が映像フレームへと変わっていく、Veoによる動画生成のイメージ」
- 生成後、`prompts/veo-hero.md` §4 の全項目を実施し、本ファイルに結果を追記する

## Rollback

- hero：現状 0（未設定）。Updater では 0 を指定できないため、wp-admin でアイキャッチ画像を削除する
- いずれも Apply 前の Updater before-snapshot JSON を保存する。旧 attachment は削除しない

---

## HERO QA 結果（2026-10-03）：**PASS**

- 受領ファイル：会話添付 `9.webp`
  - sha256 `71c3705c1f2ce2b2187d5df821ca9691b3c54c4f09647bb807636051c2d637d4`
- ready 配置：`ready/veo/omochix-ai-tool-veo-hero.webp`（無加工コピー、sha256 一致）
- 確認方法：100% の上下半分を目視、疑わしい領域を 200% で確認、340px contact sheet、数値解析（色相・外縁輝度・ブロックノイズ）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 140,332 bytes（目標 ≤250KB を満たす） |
| metadata | EXIF／XMP／ICC なし（webpinfo で VP8 チャンクのみ） |
| faces / people / hands | なし |
| fake UI | ウィンドウボタン・タブ・アイコン列なし |
| artifact / clipping | 目立つ破綻なし。主役はフレーム端で切れていない |
| mobile 340px | 主役のシルエットを判別できる（contact sheet で確認） |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 24.9、下 23.4、左 34.8、右 24.6（基準 ≥14 を満たす） |
| compression | block-edge 比 1.084（Cursor は 1.161）。目立つバンディングなし |
| color | 高彩度の色相：シアン〜青 17.0%、紫〜マゼンタ 61.8%、赤系 18.5%、緑 0.0%、黄 0.1% |
| central safe area | 高輝度画素の中央 60% 内の比率 86.0%、重心 (650, 335) |

### 内容確認（prompt §3 の negative と制作ルールとの照合）

- 対応：粒子が映像フレームへ結晶化し、山並み・波・街の光の 3〜4 枚のフレームが並ぶ。prompt と一致
- 文字・数字・疑似文字：なし（街の灯りのフレームも 200% で建物シルエットのボケのみ。特定のランドマークではない）
- ロゴ・Google 配色：なし（緑・黄の高彩度ピクセル 0%）。カチンコ・タイムコード・字幕：なし
- 主役（形成中の中央フレーム x≈540–830）は中央 60% 内
- 注記：フレーム内の風景は抽象度が低く写真的だが、汎用的な風景で、実在の場所・作品を特定できない
