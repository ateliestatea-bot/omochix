# Claude Code HERO — Production Specification & Generation Prompt

作成日：2026-10-03
対象：`claude-code`（post 665）の featured image
manifest：`docs/ai-tools/AI-Tools-Image-Manifest-v1.md` の H-05 / L-05
出力ファイル名：`omochix-ai-tool-claude-code-hero.webp`
状態：**READY_FOR_EXTERNAL_GENERATION**（この環境には画像生成機能がないため、spec と prompt までを作成）
基準：Cursor HERO（`prompts/cursor-hero.md`、production 反映済み・監査 PASS）と同じ品質基準

---

## 1. Production Specification

| 項目 | 仕様 |
|---|---|
| サイズ | **1280×720 px**（16:9）。1920×1080 以上で生成し、縮小してよい |
| 形式 | **WebP**、sRGB、**metadata 除去**（EXIF／XMP／ICC なし） |
| 容量 | **250KB 以下を目標、上限 400KB**（参考：Cursor は 67.9KB） |
| コンセプト | ターミナルで動くエージェント型コーディング（計画→編集→検証を自律的に進める） |
| トーン | premium / editorial / technology。静かで精密 |
| 背景 | OmochiX のダーク基調（深い藍〜黒紫。目安 #0E0C1A〜#161229）。外縁は純黒にしない |
| 主アクセント | OmochiX 紫 #6E4BFF |
| サブアクセント | テラコッタ #D9775A（manifest §8 の割当） |
| 人物 | なし（顔・手・人影なし） |
| 文字 | なし |
| ロゴ | なし（当該ツール・OmochiX・他社のいずれも） |

### 構図とモチーフ

1. 暗い作業空間に、横長で厚みのある 1 枚のガラススラブ（ターミナルを表す）。上部のウィンドウ操作ボタン（3 点ドット）、タブ、フォルダ／ファイルアイコンは描かない
2. スラブの中には、等幅のリズムを持つ抽象的な行（文字のない角丸バー）が下から上へ段階的に積み上がる。最下段の行頭に、光る小さなブロック状のカーソル
3. 主役：スラブ中央。行が 1 段ずつ点灯しながら組み上がっていく様子
4. スラブの右側から、テラコッタ色の光のノード列（タスクの連鎖：計画→編集→検証）が伸び、奥に浮かぶ小さなガラスキューブ群（ファイル）へ接続していく
5. Cursor HERO との差別化：caret とファイルパネル群ではなく、「ターミナルの行の積み上げ」と「タスクの連鎖」が主題

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
A premium editorial technology illustration representing an autonomous AI coding agent working in a terminal, 16:9 widescreen composition.

In a dark, calm workspace, a single wide, thick slab of frosted glass floats at the center, representing a command-line terminal. It has no window buttons, no tabs, no title bar, and no file or folder icons.

Inside the slab, abstract lines with a steady monospaced rhythm (rounded bars with no characters) build up step by step from the bottom, each new line lighting up softly as it is added. A small glowing block cursor sits at the start of the newest line.

The hero element is the center of the slab, where the lines are visibly assembling one by one.

From the right side of the slab, a chain of glowing terracotta #D9775A nodes extends outward in a gentle arc, like a sequence of tasks being completed (plan, edit, verify), connecting to a small cluster of floating glass cubes in the background that represent project files.

Lighting: warm, crafted, low-key lighting with soft volumetric haze and crisp rim light on the glass. Palette: deep indigo and black-violet background, violet #6E4BFF primary accent, terracotta #D9775A secondary accent, warm ivory highlights. Mood: focused, capable, calm, premium, editorial.

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
text, letters, words, numbers, readable code, characters, symbols, typography, captions, watermark, signature, logo, brand mark, app icon, UI screenshot, realistic software interface, window title bar, window control dots, traffic light buttons, menu bar, tabs with labels, file names, folder icons, file tree icons, toolbar icons, browser chrome, person, human, face, hands, silhouette of a person, robot, android, mascot, character, eyes, neon cyberpunk city, matrix rain, binary digits, cluttered, busy, noisy, grain, jpeg artifacts, blurry subject, distorted perspective, oversaturated, rainbow colors, window control dots, traffic light buttons, title bar, tabs, folder icons, file tree icons, asterisk, starburst, spark shape, sunburst, Claude logo, Anthropic logo, mascot, pixel character, crab, readable shell commands, dollar sign prompt, command text, check mark symbols
```

### 制作ルール（生成後に人が確認する）

1. 実在サービス（当該ツールを含む）の UI の再現・トレース、実在スクリーンショットの使用をしない
2. 公式ロゴ・ロゴに見える記号を入れない。公式ブランドアセットを HERO 内にコピーしない（ブランド色は連想用のサブアクセントに留める）
3. 読める文字・数字・疑似文字を入れない
4. 人物・顔・手・ロボット・マスコットを入れない
5. ウィンドウ操作ボタン（3 点ドット）・タブ・フォルダ／ファイルアイコンなど、fake UI に見える要素を描かない（Cursor HERO の QA 注記を反映）
6. OmochiX のダーク基調・紫系から逸脱しない（ネオン原色・虹色を使わない）
7. Claude のスパーク（放射状の星形・アスタリスク）に似た形を作らない
8. ウィンドウ操作ボタン、タブ、フォルダ／ファイルアイコンを描かない（Cursor HERO の QA 注記を反映）
9. コマンド文字列・プロンプト記号（$ や >）を描かない

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

- ready 配置先：`scratchpad/ai-tools/image-phase-v1/ready/claude-code/omochix-ai-tool-claude-code-hero.webp`
- Media Library alt（upload 時に必ず設定）：「ターミナルの中でコードが段階的に組み上がっていく、Claude Codeによるエージェント型開発のイメージ」
- 詳細ページの `<img>` alt はテーマが自動生成する（「Claude Codeのイメージ」）。Media Library の alt は og:image:alt などに使われる
- 現在の featured image：未設定（MISSING）
- 計画：`ready/claude-code/claude-code-wordpress-plan.json`
