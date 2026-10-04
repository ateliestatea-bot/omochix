# Kling AI — Image QA（Image Phase v1 Batch）

監査日：2026-10-03
production：READ ONLY（REST の GET のみ）

## production 現状

| 項目 | 値 |
|---|---|
| post_id | 677 |
| tool_logo_attachment_id | 0 |
| featured_image_attachment_id | 0 |
| current logo | なし（頭文字タイル） |
| current HERO | なし（OmochiX プレースホルダー） |
| Logo 判定 | MISSING → BLOCKED |
| HERO 判定 | MISSING → NEED_GENERATION |

## Logo

### Logo：BLOCKED

- 理由：kling.ai に press／brand／media kit ページなし（2026-10-03 再確認：Resources・About Us メニューは製品・ドキュメント・ブログのみ。/brand・/press はトップへ転送）。公式 CDN の apple-touch-icon（180×180）は高解像度要件を満たさず、ブランド配布物でもない。非公式ロゴは使わない
- 公式情報源：https://kling.ai/ （問い合わせ：support@kling.ai）
- 計画ファイル名（解除後）：`omochix-ai-tool-kling-ai-logo.webp`、alt「Kling AI ロゴ」
- **ready ファイルは作成していない。** 非公式ロゴ・生成ロゴは使用しない。解除まで頭文字タイル（現行 fallback）で運用する

## HERO：READY（初期状態：NEED_GENERATION）

- 生成していない（この環境には安全に使える画像生成機能がない。外部サービス・API key は使っていない）
- spec／prompt／negative／QA チェックリスト：`prompts/kling-ai-hero.md`
- 計画ファイル名：`omochix-ai-tool-kling-ai-hero.webp`
- alt：「静止したかたちから滑らかな動きが生まれる、Kling AIによる動画生成のイメージ」
- 生成後、`prompts/kling-ai-hero.md` §4 の全項目を実施し、本ファイルに結果を追記する

## Rollback

- hero：現状 0（未設定）。Updater では 0 を指定できないため、wp-admin でアイキャッチ画像を削除する
- いずれも Apply 前の Updater before-snapshot JSON を保存する。旧 attachment は削除しない

---

## HERO QA 結果（2026-10-03）：**PASS**

- 受領ファイル：会話添付 `5.webp`
  - sha256 `ea1a3f5a312a7f5c13b31f5fef7f8978778621f70f2162ea504f710db9d87928`
- ready 配置：`ready/kling-ai/omochix-ai-tool-kling-ai-hero.webp`（無加工コピー、sha256 一致）
- 確認方法：100% の上下半分を目視、疑わしい領域を 200% で確認、340px contact sheet、数値解析（色相・外縁輝度・ブロックノイズ）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 150,234 bytes（目標 ≤250KB を満たす） |
| metadata | EXIF／XMP／ICC なし（webpinfo で VP8 チャンクのみ） |
| faces / people / hands | なし |
| fake UI | ウィンドウボタン・タブ・アイコン列なし |
| artifact / clipping | 目立つ破綻なし。主役はフレーム端で切れていない |
| mobile 340px | 主役のシルエットを判別できる（contact sheet で確認） |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 18.5、下 41.8、左 37.2、右 17.9（基準 ≥14 を満たす） |
| compression | block-edge 比 1.091（Cursor は 1.161）。目立つバンディングなし |
| color | 高彩度の色相：シアン〜青 91.0%、紫〜マゼンタ 9.0%、赤系 0.0%、緑 0.0%、黄 0.0% |
| central safe area | 高輝度画素の中央 60% 内の比率 79.6%、重心 (569, 382) |

### 内容確認（prompt §3 の negative と制作ルールとの照合）

- 対応：ガラスカード内の折り鶴が右縁から解き放たれ、残像を重ねて右上へ飛ぶ。prompt と一致
- 文字・ロゴ・人物・動物の写実表現：なし。再生ボタン・プログレスバーなどのプレーヤー UI：なし
- 主役（カード右縁の解放点 x≈520、y≈400 と先頭の鶴）は中央 60% 内
- 注記：最後尾の残像（右上 y≈55–100）は X の 2:1 表示で一部切れるが、主役ではない
