# Runway — Image QA（Image Phase v1 Batch）

監査日：2026-10-03
production：READ ONLY（REST の GET のみ）

## production 現状

| 項目 | 値 |
|---|---|
| post_id | 675 |
| tool_logo_attachment_id | 0 |
| featured_image_attachment_id | 0 |
| current logo | なし（頭文字タイル） |
| current HERO | なし（OmochiX プレースホルダー） |
| Logo 判定 | MISSING → READY |
| HERO 判定 | MISSING → NEED_GENERATION |

## Logo

### Logo：READY

| 項目 | 結果 |
|---|---|
| source | https://runway.com/assets/Runway-Brand-Guidelines.zip |
| zip sha256 | `276a857249424a63c1de0d3867028e11037870ff629dfdcf7776bfafc0442a2c` |
| original | `Runway Brand Guidelines/Runway_White_Symbol.png`（816x816、PNG (RGBA, background opaque except 1px edge)） |
| ready file | `omochix-ai-tool-runway-logo.webp` |
| dimensions / aspect | 512x512、1:1 |
| format / bytes | WebP lossless（RGB）、5,112 bytes |
| sha256 | `a658020608ed51ce553a7f62aa5d33b55a8f97dd29824886f68fef51a7b12730` |
| metadata | なし |
| processing | 1px 半透明縁を公式背景色（#000000）でフラット化 → 背景のみの余白をトリミング（816→662、シンボル占有率 40.6%→50%） → 512×512 に Lanczos 縮小 → WebP lossless。シンボル形状・色は無変更（ピクセル差 0、トリミング領域は全画素 #000000） |
| logo integrity | 形状・色・比率は無変更。生成・描き直し・エフェクトなし |
| transparency / background | 不透明。公式 asset 自身の背景色を使用（プレート追加なし） |
| light / dark | 両モードのタイル（80px／58px）で視認性を確認済み（ローカル tile preview、2026-10-03） |
| blur / clipping | なし。シンボル周囲の余白は保持 |
| usage terms | 「Runway」と表記（Runway AI／RunwayML は不可）。デジタルでのロゴ高さは 24px 以上。黒いワードマークは明るい背景、白いワードマークは暗い背景に使う。狭い場所ではシンボル単体を使ってよい。ワードマークとシンボルの併置・縦積み・置換・歪み・回転は禁止。ダウンロード時に該当ガイドラインと利用条件への同意を求められる（ユーザーの取得指示に基づき取得） |
| alt | 「Runway ロゴ」 |

## HERO：READY（初期状態：NEED_GENERATION）

- 生成していない（この環境には安全に使える画像生成機能がない。外部サービス・API key は使っていない）
- spec／prompt／negative／QA チェックリスト：`prompts/runway-hero.md`
- 計画ファイル名：`omochix-ai-tool-runway-hero.webp`
- alt：「重なり合う映像レイヤーを光がつなぐ、Runwayによる映像制作のイメージ」
- 生成後、`prompts/runway-hero.md` §4 の全項目を実施し、本ファイルに結果を追記する

## Rollback

- logo：現状 0（未設定）。Updater では 0 を指定できないため、wp-admin で tool_logo を解除する
- hero：現状 0（未設定）。Updater では 0 を指定できないため、wp-admin でアイキャッチ画像を削除する
- いずれも Apply 前の Updater before-snapshot JSON を保存する。旧 attachment は削除しない

---

## HERO QA 結果（2026-10-03）：**PASS**

- 受領ファイル：会話添付 `8.webp`
  - sha256 `34bc380fc969b94be8fef697e6e7cf0f0973090858357cce6016822025fd5624`
- ready 配置：`ready/runway/omochix-ai-tool-runway-hero.webp`（無加工コピー、sha256 一致）
- 確認方法：100% の上下半分を目視、疑わしい領域を 200% で確認、340px contact sheet、数値解析（色相・外縁輝度・ブロックノイズ）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 167,692 bytes（目標 ≤250KB を満たす） |
| metadata | EXIF／XMP／ICC なし（webpinfo で VP8 チャンクのみ） |
| faces / people / hands | なし |
| fake UI | ウィンドウボタン・タブ・アイコン列なし |
| artifact / clipping | 目立つ破綻なし。主役はフレーム端で切れていない |
| mobile 340px | 主役のシルエットを判別できる（contact sheet で確認） |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 14.6、下 24.0、左 29.8、右 33.1（基準 ≥14 を満たす） |
| compression | block-edge 比 1.112（Cursor は 1.161）。目立つバンディングなし |
| color | 高彩度の色相：シアン〜青 24.2%、紫〜マゼンタ 74.6%、赤系 1.3%、緑 0.0%、黄 0.0% |
| central safe area | 高輝度画素の中央 60% 内の比率 78.6%、重心 (688, 314) |

### 内容確認（prompt §3 の negative と制作ルールとの照合）

- 対応：層をなすガラスパネル、パネルを縫うマゼンタ／紫の光、中央でパネルが粒子へほどけて再構成される、下部のノード付きライン。prompt と一致
- 文字・編集ソフト UI（タイムラインのラベル、トラック名、再生ボタン）：なし。R 形状：なし
- 主役（中央の再構成パネル x≈540–850）は中央 60% 内
- 注記（spec との差分）：パネルの中身が「抽象的な色面」ではなく、空・波・山の風景。Veo HERO とモチーフ（風景フレーム＋粒子）が似ている。品質ゲートはすべて満たすため PASS。差別化を強めたい場合は任意で再生成
