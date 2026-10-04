# ChatGPT HERO — Production Specification & Generation Prompt

作成日：2026-10-03
対象：`chatgpt`（post 53）の featured image
manifest：`docs/ai-tools/AI-Tools-Image-Manifest-v1.md` の H-08 / L-08
出力ファイル名：`omochix-ai-tool-chatgpt-hero.webp`
状態：**READY_FOR_EXTERNAL_GENERATION**（この環境には画像生成機能がないため、spec と prompt までを作成）
基準：Cursor HERO（`prompts/cursor-hero.md`、production 反映済み・監査 PASS）と同じ品質基準

---

## 1. Production Specification

| 項目 | 仕様 |
|---|---|
| サイズ | **1280×720 px**（16:9）。1920×1080 以上で生成し、縮小してよい |
| 形式 | **WebP**、sRGB、**metadata 除去**（EXIF／XMP／ICC なし） |
| 容量 | **250KB 以下を目標、上限 400KB**（参考：Cursor は 67.9KB） |
| コンセプト | 汎用 AI アシスタントとの対話（文章・画像・データ・コードなど多様なタスク） |
| トーン | premium / editorial / technology。静かで精密 |
| 背景 | OmochiX のダーク基調（深い藍〜黒紫。目安 #0E0C1A〜#161229）。外縁は純黒にしない |
| 主アクセント | OmochiX 紫 #6E4BFF |
| サブアクセント | ティール #19C3A6（manifest §8 の割当） |
| 人物 | なし（顔・手・人影なし） |
| 文字 | なし |
| ロゴ | なし（当該ツール・OmochiX・他社のいずれも） |

### 構図とモチーフ

1. 主役：画面中央に、柔らかく呼吸するような光の球（対話の中心）
2. 球を囲むように、文字のない角丸の吹き出し形が数個、緩やかな円軌道で広がる（対話）
3. その外側に、タスクを表す小さな浮遊オブジェクト：紙のシート（文章）、抽象的な風景の入ったフレーム（画像）、高さの違うバー（データ。数字なし）、ガラスのブロックの積み重ね（コード。括弧・記号は描かない）
4. 親しみやすく明るめのトーン（ただし背景はダーク基調）
5. OpenAI Blossom（花形・六角の結び目形状）に似た形は作らない

### セーフエリア（Cursor の実測に基づく）

- 詳細ページの HERO は `aspect-ratio: 16/9` と `object-fit: cover`。desktop 約 400px 幅、mobile 337×189px で、1280×720 は**トリミングされずに全体が表示される**
- og:image は原寸。SNS の 1.91:1 表示で上下各約 25px、X の 2:1 表示で上下各約 40px が切れる
- **主役は中央 60%（x 256–1024、y 144–576）に置く**。中央 1:1 領域（x 280–1000）の外に重要要素を出さない
- mobile 約 340px 幅で主役のシルエットが判別できるコントラストにする

### light / dark ページとの相性

画像はダーク基調で固定する。light ページでは枠線と角丸のカード内に置かれる。dark ページ（#0D0D0D／#191919）では、外縁の平均輝度を 14 以上にして境界が消えないようにする（Cursor の実測は 14–22）。

---

## 2. Final Production Prompt（そのまま画像生成 AI に渡す）

```
A premium editorial technology illustration representing a versatile AI assistant in conversation, 16:9 widescreen composition.

At the exact center of a deep indigo space floats a soft, gently glowing orb of light, like a calm, breathing presence. This orb is the hero element.

Around it, a few rounded speech-bubble shapes with no text drift in a slow orbital arrangement, suggesting an ongoing conversation.

Further out, small floating objects represent the many tasks the assistant helps with: a sheet of paper (writing), a small frame containing an abstract landscape (images), a cluster of bars of different heights with no numbers (data), and a neat stack of glass blocks (code, without any brackets or symbols).

Lighting: warm and approachable, soft volumetric glow from the central orb, crisp rim light on the floating objects. Palette: deep indigo and black-violet background, violet #6E4BFF primary accent, teal #19C3A6 secondary accent, soft white highlights. Mood: friendly, helpful, clear, premium, editorial.

Composition: the main subject (the orb and nearest bubbles) is fully inside the central 60% of the frame; dark negative space around the edges, not pure black. Clean high-end 3D render aesthetic, no clutter.
```

### 補助パラメータ

- Aspect ratio：16:9
- Style：photorealistic 3D render / editorial illustration
- 生成解像度：1920×1080 以上。最終的に 1280×720 へ縮小する
- 4 案以上生成し、§4 の QA 基準で選ぶ

---

## 3. Negative Requirements

### negative prompt

```
text, letters, words, numbers, readable code, characters, symbols, typography, captions, watermark, signature, logo, brand mark, app icon, UI screenshot, realistic software interface, window title bar, window control dots, traffic light buttons, menu bar, tabs with labels, file names, folder icons, file tree icons, toolbar icons, browser chrome, person, human, face, hands, silhouette of a person, robot, android, mascot, character, eyes, neon cyberpunk city, matrix rain, binary digits, cluttered, busy, noisy, grain, jpeg artifacts, blurry subject, distorted perspective, oversaturated, rainbow colors, OpenAI logo, ChatGPT logo, blossom shape, hexagonal knot, interlocking flower symbol, chat interface, message bubbles with text, model name, GPT text, robot, android, face in the orb, eyes, smile, curly braces, angle brackets, numbers on charts
```

### 制作ルール（生成後に人が確認する）

1. 実在サービス（当該ツールを含む）の UI の再現・トレース、実在スクリーンショットの使用をしない
2. 公式ロゴ・ロゴに見える記号を入れない。公式ブランドアセットを HERO 内にコピーしない（ブランド色は連想用のサブアクセントに留める）
3. 読める文字・数字・疑似文字を入れない
4. 人物・顔・手・ロボット・マスコットを入れない
5. ウィンドウ操作ボタン（3 点ドット）・タブ・フォルダ／ファイルアイコンなど、fake UI に見える要素を描かない（Cursor HERO の QA 注記を反映）
6. OmochiX のダーク基調・紫系から逸脱しない（ネオン原色・虹色を使わない）
7. OpenAI Blossom（花形・六角の結び目）に似た形を作らない
8. 光の球に顔・目・表情を持たせない（擬人化しない）
9. モデル名（GPT-x など）・チャット UI を描かない（旧 HERO ID 566 の不合格理由）

---

## 4. QA チェックリスト（生成後に実施）

| 項目 | 合格基準 |
|---|---|
| dimensions | 1280×720 |
| format | WebP、alpha なし、sRGB |
| filesize | ≤250KB 目標、≤400KB 上限 |
| aspect ratio | 1.7778 |
| metadata | EXIF／XMP／ICC／C2PA なし（webpinfo で VP8／VP8L チャンクのみ） |
| readable text | 100% と 200% 拡大の両方で、文字・数字・疑似文字がない |
| fake logo | ロゴ・ロゴ状の記号がない。§3 の禁止形状がない |
| faces / people | 顔・人影・手がない（反射や模様に見えるものも含む） |
| fake UI | ウィンドウボタン・タブ・アイコン列がなく、アプリ画面に見えない |
| artifact | 形状の破綻、溶けた輪郭、途切れた線がない |
| clipping | 主役がフレーム端で切れていない |
| mobile readability | 340px 幅に縮小しても主役が判別できる |
| social crop | 1.91:1（上下各 25px カット）と 2:1（上下各 40px カット）で主役が残る |
| light / dark | 両ページのカード内で外縁が背景に溶けない（外縁平均輝度 ≥14） |
| compression | 暗部グラデーションのバンディング・ブロックノイズが目立たない（コントラスト強調で確認） |
| color | 高彩度ピクセルが紫〜サブアクセントの色相に収まる。禁止配色がない |

---

## 5. WordPress 反映情報

- ready 配置先：`scratchpad/ai-tools/image-phase-v1/ready/chatgpt/omochix-ai-tool-chatgpt-hero.webp`
- Media Library alt（upload 時に必ず設定）：「対話を中心に多様なタスクが広がる、ChatGPTのAIアシスタントのイメージ」
- 詳細ページの `<img>` alt はテーマが自動生成する（「ChatGPTのイメージ」）。Media Library の alt は og:image:alt などに使われる
- 現在の featured image：ID 566（REPLACE）
- 計画：`ready/chatgpt/chatgpt-wordpress-plan.json`
