# Claude Code — Image QA（Image Phase v1 Batch）

監査日：2026-10-03
production：READ ONLY（REST の GET のみ）

## production 現状

| 項目 | 値 |
|---|---|
| post_id | 665 |
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
| source | https://www.anthropic.com/press-kit → https://www-cdn.anthropic.com/ae59ca4ca194dac9c9dc3bc78c5829468cb0e8af.zip |
| zip sha256 | `c68ac92df86c825f95177e24016fcc9a8863a3fd4ca344fe6f0700b2c1e07151` |
| original | `Anthropic media resources/Anthropic logos/Claude logos/4 Claude icon/PNG/ClaudeIcon-Square.png`（1280x1280、PNG (RGBA, ほぼ不透明：alpha 254)） |
| ready file | `omochix-ai-tool-claude-code-logo.webp` |
| dimensions / aspect | 512x512、1:1 |
| format / bytes | WebP lossless（RGB）、61,340 bytes |
| sha256 | `49d3ef498443d021b64519adba7b65152fe3c01e2e367d04dc2de2a2963a275a` |
| metadata | なし |
| processing | 公式背景色（#D97757）でフラット化 → 512×512 に Lanczos 縮小 → WebP lossless。形状・色は無変更（通常の縮小とのピクセル差 0） |
| logo integrity | 形状・色・比率は無変更。生成・描き直し・エフェクトなし |
| transparency / background | 不透明。公式 asset 自身の背景色を使用（プレート追加なし） |
| light / dark | 両モードのタイル（80px／58px）で視認性を確認済み（ローカル tile preview、2026-10-03） |
| blur / clipping | なし。シンボル周囲の余白は保持 |
| usage terms | press kit 内に利用ガイドライン文書はない（エグゼクティブ略歴 PDF のみ）。キットの Claude Code logo は横長ロックアップ（3638×500）のため正方形タイルでは判読できず、不採用。正方形の公式 Claude icon を使う。既存の claude ツール（attachment 57、ClaudeIcon-Square.png）と同じマークになる点は運営者判断。問い合わせ：press@anthropic.com |
| alt | 「Claude Code ロゴ」 |

## HERO：READY（初期状態：NEED_GENERATION）

- 生成していない（この環境には安全に使える画像生成機能がない。外部サービス・API key は使っていない）
- spec／prompt／negative／QA チェックリスト：`prompts/claude-code-hero.md`
- 計画ファイル名：`omochix-ai-tool-claude-code-hero.webp`
- alt：「ターミナルの中でコードが段階的に組み上がっていく、Claude Codeによるエージェント型開発のイメージ」
- 生成後、`prompts/claude-code-hero.md` §4 の全項目を実施し、本ファイルに結果を追記する

## Rollback

- logo：現状 0（未設定）。Updater では 0 を指定できないため、wp-admin で tool_logo を解除する
- hero：現状 0（未設定）。Updater では 0 を指定できないため、wp-admin でアイキャッチ画像を削除する
- いずれも Apply 前の Updater before-snapshot JSON を保存する。旧 attachment は削除しない

---

## HERO QA 結果（2026-10-03）：**PASS**

- 受領ファイル：会話添付 `3.webp`
  - sha256 `8a637f6bad7be68783fefaac5760013066cdb5135f15720f05fd43fd460080e1`
- ready 配置：`ready/claude-code/omochix-ai-tool-claude-code-hero.webp`（無加工コピー、sha256 一致）
- 確認方法：100% の上下半分を目視、疑わしい領域を 200% で確認、340px contact sheet、数値解析（色相・外縁輝度・ブロックノイズ）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 144,396 bytes（目標 ≤250KB を満たす） |
| metadata | EXIF／XMP／ICC なし（webpinfo で VP8 チャンクのみ） |
| faces / people / hands | なし |
| fake UI | ウィンドウボタン・タブ・アイコン列なし |
| artifact / clipping | 目立つ破綻なし。主役はフレーム端で切れていない |
| mobile 340px | 主役のシルエットを判別できる（contact sheet で確認） |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 18.8、下 46.6、左 32.8、右 37.5（基準 ≥14 を満たす） |
| compression | block-edge 比 1.066（Cursor は 1.161）。目立つバンディングなし |
| color | 高彩度の色相：シアン〜青 9.1%、紫〜マゼンタ 56.4%、赤系 33.2%、緑 0.0%、黄 0.0% |
| central safe area | 高輝度画素の中央 60% 内の比率 38.3%、重心 (712, 409) |

### 内容確認（prompt §3 の negative と制作ルールとの照合）

- 対応：横長のガラススラブに、行頭マーカー付きの抽象行が積み上がり、最下行に光るブロックカーソル。右へテラコッタのノード列が伸び、ガラスキューブ群（ファイル）につながる。prompt と一致
- 文字・読めるコード・コマンド文字列・$ や > の記号：なし（200% で確認）
- ウィンドウボタン・タブ・タイトルバー：なし。Claude スパーク・アスタリスク・Anthropic ロゴ：なし
- 注記（構図）：スラブの左端が x≈130 で、中央 60%（x 256–1024）の外にはみ出している。行の本体（x≈240–680）は概ね内側にある。高輝度画素の中央 60% 内の比率は 38%（他は 62–86%）
- 詳細ページ（16:9 全体表示）、og（1.91:1）、X（2:1）はいずれも左右を切らないため、実際の表示への影響はない。spec の予防基準（中央 1:1）からは外れる
