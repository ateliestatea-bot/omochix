# Gemini HERO — Production Specification & Generation Prompt

作成日：2026-10-03
対象：`gemini`（post 61）の featured image
manifest：`docs/ai-tools/AI-Tools-Image-Manifest-v1.md` の H-09（Logo は KEEP）
出力ファイル名：`omochix-ai-tool-gemini-hero.webp`
状態：**READY_FOR_EXTERNAL_GENERATION**（この環境には画像生成機能がないため、spec と prompt までを作成）
基準：Cursor HERO（`prompts/cursor-hero.md`、production 反映済み・監査 PASS）と同じ品質基準

---

## 1. Production Specification

| 項目 | 仕様 |
|---|---|
| サイズ | **1280×720 px**（16:9）。1920×1080 以上で生成し、縮小してよい |
| 形式 | **WebP**、sRGB、**metadata 除去**（EXIF／XMP／ICC なし） |
| 容量 | **250KB 以下を目標、上限 400KB**（参考：Cursor は 67.9KB） |
| コンセプト | マルチモーダル AI（テキスト・画像・音声・動画を横断して理解・生成する） |
| トーン | premium / editorial / technology。静かで精密 |
| 背景 | OmochiX のダーク基調（深い藍〜黒紫。目安 #0E0C1A〜#161229）。外縁は純黒にしない |
| 主アクセント | OmochiX 紫 #6E4BFF |
| サブアクセント | インディゴ〜バイオレットのグラデーション #4F5BFF→#9B6BFF（manifest §8 の割当） |
| 人物 | なし（顔・手・人影なし） |
| 文字 | なし |
| ロゴ | なし（当該ツール・OmochiX・他社のいずれも） |

### 構図とモチーフ

1. 四方から 4 本の異なる素材感のストリームが中央へ流れ込む：紙片のリボン（テキスト）、光るピクセルのモザイク（画像）、滑らかな波形（音声）、フィルムストリップ状の帯（動画）
2. 主役：画面中央で 4 つの流れが合流し、透明なプリズム状の球体の中で 1 つの調和した光に溶け合う
3. 合流点から柔らかな光が放射されるが、4 方向の尖った星形（Gemini スパーク）にはしない。光は円形のグロー
4. 色はインディゴ〜バイオレットのグラデーションで統一する。Google の 4 色は使わない

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
A premium editorial technology illustration representing multimodal AI that understands text, images, audio, and video together, 16:9 widescreen composition.

In a deep, dark indigo space, four distinct streams flow in from different directions toward the center, each with its own material: a ribbon of floating paper fragments (text), a mosaic of glowing pixels (images), a smooth flowing waveform (audio), and a translucent film-strip band (video). The film strip shows only soft abstract color fields.

The hero element sits at the exact center: the four streams meeting inside a clear, glass-like prism sphere, where they dissolve and blend into one harmonious, softly glowing light.

The light from the meeting point radiates as a round, diffuse glow, not as a pointed star or sparkle.

Lighting: soft volumetric haze, crisp caustic highlights inside the glass sphere, rim light on each stream. Palette: deep indigo and black-violet background, a unified gradient from indigo #4F5BFF to violet #9B6BFF, violet #6E4BFF as the primary accent, touches of cool white. Mood: intelligent, harmonious, expansive, premium, editorial.

Composition: the main subject is fully inside the central 60% of the frame; generous dark negative space, outer edges not pure black. Clean high-end 3D render aesthetic, no clutter.
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
text, letters, words, numbers, readable code, characters, symbols, typography, captions, watermark, signature, logo, brand mark, app icon, UI screenshot, realistic software interface, window title bar, window control dots, traffic light buttons, menu bar, tabs with labels, file names, folder icons, file tree icons, toolbar icons, browser chrome, person, human, face, hands, silhouette of a person, robot, android, mascot, character, eyes, neon cyberpunk city, matrix rain, binary digits, cluttered, busy, noisy, grain, jpeg artifacts, blurry subject, distorted perspective, oversaturated, rainbow colors, four-pointed star, sparkle shape, twinkle star, Gemini logo, Google logo, Google colors, red green yellow blue combination, rainbow, readable text on paper, letters on fragments, twin figures, zodiac Gemini symbol
```

### 制作ルール（生成後に人が確認する）

1. 実在サービス（当該ツールを含む）の UI の再現・トレース、実在スクリーンショットの使用をしない
2. 公式ロゴ・ロゴに見える記号を入れない。公式ブランドアセットを HERO 内にコピーしない（ブランド色は連想用のサブアクセントに留める）
3. 読める文字・数字・疑似文字を入れない
4. 人物・顔・手・ロボット・マスコットを入れない
5. ウィンドウ操作ボタン（3 点ドット）・タブ・フォルダ／ファイルアイコンなど、fake UI に見える要素を描かない（Cursor HERO の QA 注記を反映）
6. OmochiX のダーク基調・紫系から逸脱しない（ネオン原色・虹色を使わない）
7. 4 方向に尖った星形・きらめき（Gemini スパーク）に似た形を作らない
8. Google の 4 色を使わない（色はインディゴ〜バイオレットで統一）
9. 紙片上の文字は判読不能にする

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

- ready 配置先：`scratchpad/ai-tools/image-phase-v1/ready/gemini/omochix-ai-tool-gemini-hero.webp`
- Media Library alt（upload 時に必ず設定）：「テキスト・画像・音声・動画の流れが1点に合流する、GeminiのマルチモーダルAIのイメージ」
- 詳細ページの `<img>` alt はテーマが自動生成する（「Geminiのイメージ」）。Media Library の alt は og:image:alt などに使われる
- 現在の featured image：ID 636（REPLACE）
- 計画：`ready/gemini/gemini-wordpress-plan.json`
