# Gemini — Image QA（Image Phase v1 Batch）

監査日：2026-10-03
production：READ ONLY（REST の GET のみ）

## production 現状

| 項目 | 値 |
|---|---|
| post_id | 61 |
| tool_logo_attachment_id | 634 |
| featured_image_attachment_id | 636 |
| current logo | {"url": "/uploads/2026/09/logo-gemini-e1790242274520.webp", "dimensions": "800x800", "mime": "image/webp", "bytes": 8966, "alt": "Logo gemini"} |
| current HERO | {"url": "/uploads/2026/09/3e41a5eaa32d59fd3d578ad3ae959e5a.png", "dimensions": "1672x941", "mime": "image/png", "bytes": 1427380, "alt": "gemini"} |
| Logo 判定 | KEEP → KEEP |
| HERO 判定 | REPLACE → NEED_GENERATION |

## Logo

### Logo：KEEP

- 現行 ID 634（/uploads/2026/09/logo-gemini-e1790242274520.webp、800x800、image/webp、alt「Logo gemini」）
- ID 634（800×800 WebP、現行スパーク）を KEEP。ただし入手元の記録がなく、Google の Product icons「Ask first」区分に該当する可能性があるため、継続使用の可否は運営者が確認する。alt「Logo gemini」はファイル名由来のため「Gemini ロゴ」への修正を推奨（wp-admin のメタデータ変更。今回は実施しない）

## HERO：FAIL（要再生成）（初期状態：NEED_GENERATION）

- 生成していない（この環境には安全に使える画像生成機能がない。外部サービス・API key は使っていない）
- spec／prompt／negative／QA チェックリスト：`prompts/gemini-hero.md`
- 計画ファイル名：`omochix-ai-tool-gemini-hero.webp`
- alt：「テキスト・画像・音声・動画の流れが1点に合流する、GeminiのマルチモーダルAIのイメージ」
- 生成後、`prompts/gemini-hero.md` §4 の全項目を実施し、本ファイルに結果を追記する

## Rollback

- hero：Updater で featured_image_attachment_id を旧 ID 636 に戻す
- いずれも Apply 前の Updater before-snapshot JSON を保存する。旧 attachment は削除しない

---

## HERO QA 結果（2026-10-03）：**FAIL**

- 受領ファイル：会話添付 `4.webp`
  - sha256 `7c4f8b1eb06436d92f940576c48d9b82f8bf01aac404d6c6da1b211c831c6b20`
- ready 配置：**配置しない（FAIL）**
- 確認方法：100% の上下半分を目視、疑わしい領域を 200% で確認、340px contact sheet、数値解析（色相・外縁輝度・ブロックノイズ）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 196,730 bytes（目標 ≤250KB を満たす） |
| metadata | EXIF／XMP／ICC なし（webpinfo で VP8 チャンクのみ） |
| faces / people / hands | なし |
| fake UI | ウィンドウボタン・タブ・アイコン列なし |
| artifact / clipping | 目立つ破綻なし。主役はフレーム端で切れていない |
| mobile 340px | 主役のシルエットを判別できる（contact sheet で確認） |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 24.7、下 29.1、左 67.2、右 40.5（基準 ≥14 を満たす） |
| compression | block-edge 比 1.08（Cursor は 1.161）。目立つバンディングなし |
| color | 高彩度の色相：シアン〜青 64.9%、紫〜マゼンタ 34.4%、赤系 0.7%、緑 0.0%、黄 0.0% |
| central safe area | 高輝度画素の中央 60% 内の比率 68.7%、重心 (613, 314) |

### 内容確認（prompt §3 の negative と制作ルールとの照合）

- 対応：紙片（テキスト）、ピクセル化した画像群、波形（音声）、フィルムストリップ（動画）が中央の球で合流する。prompt と一致
- Google 4 色：なし（緑・黄の高彩度 0%、赤 0.7%）。Gemini スパーク／4 点星：なし（中央は斜めの X 字状に交差する光のリボンで、尖った 4 点星ではない）
- 【不合格理由】疑似文字：左上の紙片群（おおよそ x 0–560、y 0–300。特に x 100–380、y 60–260）に、印刷された本文のような細かい行パターンがある。100% 表示で「文章の行」として明確に認識でき、200% では単語区切りのような塊が並ぶ。判読できる文字はないが、検査項目 8（文字に見える模様／疑似文字）と prompt §3「letters on fragments」禁止に該当
- 影響：詳細ページ（約 400px 幅）や mobile では知覚しにくい。og:image は原寸のため、SNS で拡大されると「偽の文章」に見える
- 再生成：推奨。prompt の紙片の記述を「blank paper fragments with only soft glowing edges, no printed lines, no text texture」に変え、negative に「printed text lines, paragraph texture, newspaper, document text」を追加
- 軽微な画像処理：技術的には可能（紙片領域の行パターンだけを弱いぼかしで均す）。ただし画像内容の変更にあたるため、ユーザーの承認なしには行わない。ぼかしは周囲の質感と不整合になりやすく、推奨しない

---

## HERO 再QA v2（2026-10-03）：**FAIL（軽度。ready には配置しない）**

- 受領ファイル：`omochix-ai-tool-gemini-hero-v2.webp`（会話添付）
  - sha256 `80de7101e3c1a63d63fed764d9c83e3cf4790a8d903c8aec7b1ea389f9b105ed`
- 保管先：`candidates/gemini/omochix-ai-tool-gemini-hero-v2.webp`（ready ではない。production 計画には使わない）
- 旧 v1（FAIL）は ready に存在しない（一度も配置していない）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 197,554 bytes（≤250KB） |
| metadata | EXIF／XMP／ICC／C2PA なし |
| Gemini スパーク／4 点星 | なし。球の中は S 字（陰陽状）の渦で、中心は点状の光。尖った 4 方向の星形はない（200% で確認） |
| Google／Gemini ロゴ・ロゴ状記号 | なし |
| Google 4 色 | なし。高彩度の色相：シアン〜青 56.0%、紫〜マゼンタ 41.8%、赤 2.2%、緑 0%、黄 0% |
| 人物・顔・手 | なし |
| fake UI、透かし、署名、数字、記号 | なし |
| 画像フレーム、フィルムストリップ | 山並みの抽象風景のみ。文字なし（200% で確認） |
| artifact / clipping | 目立つ破綻なし。主役（球、x≈490–805）は切れていない |
| central safe area | 高輝度画素の中央 60% 内の比率 60.1%、重心 (613, 322)。球は中央 60% 内 |
| mobile 340px | 4 本の流れと球を判別できる |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 30.3、下 80.7、左 45.9、右 48.5（基準 ≥14） |
| compression | block-edge 比 1.078 |

### 不合格理由：紙片の疑似テキスト（残存、v1 より大幅に軽減）

- 100% 表示：紙片はほぼ無地に見え、文章としては認識できない（v1 の不合格要因は解消）
- **200% 拡大：以下 3 枚の紙片に、印刷された段落のような薄い横線の行パターンが確認できる**
  - シート A：おおよそ x 20–190、y 75–215（左側の最大の紙片。中央部に複数行）
  - シート B：おおよそ x 155–260、y 15–165（左寄りに短い行が縦に並び、リストのように見える）
  - シート C：おおよそ x 165–285、y 185–290（左上部に薄い行）
- 3 倍＋コントラスト強調では、明確に「文章の段落」に見える
- 判断：QA 基準「pseudo-text／printed paragraph pattern なし（100%／200% で確認）」を満たさないため FAIL とする
  - 実表示（詳細ページ約 400px 幅、og は縮小表示）で知覚される可能性は低い
  - 運営者がこの残存を許容する場合は、指示があれば `candidates/` から正式ファイル名で ready に配置できる

### 再生成の指針

- 原因：「紙片（paper）」という語から、生成モデルが本文の質感を自動的に付けている
  - v2 では文字の濃さは下がったが、行パターン自体は残った
- prompt の修正案（テキストのストリーム）：
  - 「a ribbon of floating paper fragments (text)」を、次のように紙以外の素材に置き換える：
    「a ribbon of floating translucent frosted-glass shards with smooth, completely blank glowing surfaces (representing text)」
  - または「a stream of soft glowing light ribbons shaped like rounded blank cards」
  - 紙を使う場合は「pure blank unprinted paper, perfectly smooth, no lines」と明記する
- negative に追加する語：
  - faint text, ghost text, printed lines, ruled lines, lined paper, handwriting, paragraph texture, document, letter, page with writing
- その他の要素（球、波形、フィルム、画像群、配色、構図）は v2 のままでよい（すべて合格）

---

## HERO 再QA v3（2026-10-03）：**PASS**（ready 配置済み）

- 受領ファイル：Gemini v3（会話添付）
  - sha256 `d48755fa49d4f7d2631e622b78801ac6b9a8a760edc5dfbc649c4c51e9ee4ca9`
- ready 配置：`ready/gemini/omochix-ai-tool-gemini-hero.webp`（正式ファイル名。無加工コピーで sha256 一致）
- v1・v2 は ready に置いていない（v2 は `candidates/gemini/` に参考として保管）

| 項目 | 結果 |
|---|---|
| dimensions / aspect | 1280×720、1.7778 |
| format / alpha | WebP lossy（VP8 チャンクのみ）、alpha なし |
| filesize | 217,930 bytes（≤250KB） |
| metadata | EXIF／XMP／ICC／C2PA なし |
| **疑似文字（左上のガラス片）** | **なし**。テキストのストリームが紙片から無地のすりガラス片に変わり、100%、200%、3 倍＋コントラスト強調のいずれでも行パターン・文字状の模様はない |
| その他の文字・数字・記号 | なし（波形、フィルムストリップ、画像フレームを確認） |
| Gemini スパーク／4 点星 | なし。球の中心は 2 本の光のリボンが ∞ 字状に交差する点光で、尖った 4 方向の星形ではない |
| Google／Gemini ロゴ・ロゴ状記号 | なし |
| Google 4 色 | なし。高彩度の色相：シアン〜青 69.2%、紫〜マゼンタ 30.2%、赤 0.6%、緑 0%、黄 0% |
| 人物・顔・手、fake UI、透かし、署名 | なし |
| artifact / clipping | 目立つ破綻なし。主役（球、x≈500–790）は切れていない |
| central safe area | 高輝度画素の中央 60% 内の比率 61.2%、重心 (632, 359) |
| mobile 340px | 4 本の流れと球を判別できる |
| social crop | 1.91:1（上下各 25px）と 2:1（上下各 40px）で主役は残る |
| light / dark | 外縁の平均輝度：上 21.0、下 50.2、左 48.8、右 38.3（基準 ≥14） |
| compression | block-edge 比 1.046。バンディングなし |

prompt §1–§3 との照合：4 つのストリームが中央の球で合流する構図、indigo〜violet 中心の配色、禁止形状なし。テキストのストリームの素材は v2 の QA 指針どおりガラス片に変更されている。
