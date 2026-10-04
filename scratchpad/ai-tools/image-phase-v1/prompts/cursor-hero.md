# Cursor HERO — Production Specification & Generation Prompt

作成日：2026-10-01
対象：`cursor`（post 65）featured image
関連：`docs/ai-tools/AI-Tools-Image-Manifest-v1.md` の H-06
出力ファイル名：`omochix-ai-tool-cursor-hero.webp`
状態：**未生成**（この環境には安全に使える画像生成機能がないため、spec と prompt までを作成）

---

## 1. Production Specification

| 項目 | 仕様 |
|---|---|
| サイズ | **1280×720 px**（16:9）。生成は 16:9 のより大きいサイズ（例：1920×1080、2560×1440）で行い、1280×720 に縮小してよい |
| 形式 | **WebP**、quality 82–85、sRGB、メタデータ除去 |
| 容量 | 250KB 以下を目標（上限 400KB） |
| コンセプト | **AI-native development environment**：AI と一緒にコードベースを操作している |
| トーン | premium / editorial / technology。静かで精密、高級感。ゲーム的・SF 的な派手さは避ける |
| 背景 | OmochiX のダーク基調（深い藍〜黒紫。目安 #0E0C1A〜#161229） |
| 主アクセント | OmochiX 紫（#6E4BFF 系） |
| サブアクセント | エレクトリックブルー（#3D8BFF〜#5AB0FF 系）。manifest §8 の割当 |
| 光 | 画面中央付近からの柔らかい発光とリムライト。薄いボリューメトリック光 |
| 人物 | なし（手・顔・シルエットを含めない） |
| 文字 | なし。コードは「読めない抽象的な行」（長さの異なる角丸の線）だけ |
| ロゴ | なし（Cursor の立方体ロゴや類似形状、OmochiX ロゴ、他社ロゴを入れない） |

### 構図とモチーフ

画面中央の奥行きのある空間に、**プロジェクトの構造**が浮かんでいる。

1. **project architecture / connected files**
   - 半透明のガラス板（ファイルを表す角丸パネル）が 3 層ほどの奥行きで配置されている
   - 細い光のライン（依存関係）がパネル同士をつなぎ、ツリーまたはグラフ構造を作る
2. **code structure**
   - 各パネルの中は、インデントの違う**抽象的なコード行**（文字のない角丸バー）だけ
   - 行はパネルごとに 3〜8 本で、密度を低く保つ
3. **cursor / selection**
   - 中央パネルに、縦長で光るテキストキャレットを置く（この画像の主役）
   - キャレットの周囲の数行が紫〜ブルーの選択ハイライトで包まれている
4. **AI agent**
   - 選択範囲から小さな光の粒子の流れが伸び、ほかのパネルへ移る
   - 移った先のパネルで、新しい行がフェードインしている（AI が複数ファイルを横断して編集する様子）
   - エージェントは「形」ではなく「光の流れ」で表す（ロボットや顔は描かない）
5. **developer workflow**
   - パネル群の下から奥へ、薄い光のタイムライン（ステップの点列）を一本通す
   - 構図は左→右：計画 → 編集 → 検証の流れを暗示する

### セーフエリア（テーマ実装から確認）

- 詳細ページの HERO は `aspect-ratio: 16/9` と `object-fit: cover`
  - desktop は約 400px 幅、mobile は約 340px 幅
  - 1280×720 はトリミングされずに全体が表示される
- og:image は featured image の原寸（Slim SEO）が使われる。SNS の 1.91:1 表示では上下が約 25px ずつ切れる
- **主役（キャレット＋選択範囲）は中央 60%**（x 256–1024、y 144–576）に置く
- 重要な要素を中央 1:1 領域（x 280–1000）の外に出さない
- 小さく表示されるため、細部よりシルエットとコントラストで読める構図にする（mobile 幅約 340px で主役が判別できること）

### light / dark ページとの相性

- 画像自体はダーク基調で固定する
- light ページでは枠線と角丸 16–24px のカード内に置かれ、「ダークな写真」として成立する
- dark ページでは背景 #0D0D0D / #191919 に対して、画像の外縁がわずかに明るい藍（#12102A 以上）になるようにし、境界が消えないようにする

---

## 2. Final Production Prompt（そのまま画像生成 AI に渡す）

```
A premium editorial technology illustration representing an AI-native software development environment, 16:9 widescreen composition.

In a deep, dark indigo-to-black-violet space, several translucent frosted-glass panels float at three levels of depth, each panel representing a source file of a software project. Thin glowing lines connect the panels like a dependency graph, forming a clear, elegant project architecture.

Inside each panel there is only abstract code structure: a few horizontal rounded bars of varying lengths and indentation, like minimalist code lines with no characters, no letters, and no symbols. Keep the density low and calm.

The hero element sits at the exact center: a tall, luminous vertical text caret (an editor cursor) glowing in soft violet (#6E4BFF), with the surrounding lines wrapped in a gentle violet-to-electric-blue selection highlight.

From this selection, a stream of fine luminous particles flows across to two other panels, where new abstract code lines are softly fading in, suggesting an AI agent editing multiple connected files at once. The agent is expressed only as flowing light, not as a robot, character, or face.

Beneath the panels, a faint thin horizontal light timeline with a few small glowing nodes runs from left to right, implying a developer workflow of plan, edit, and verify.

Lighting: soft central glow, subtle volumetric haze, crisp rim light on the glass edges, gentle depth of field on the farthest panels. Palette: deep indigo and black-violet background, violet #6E4BFF as the primary accent, electric blue #4A9BFF as the secondary accent, small touches of cool white. Mood: calm, precise, premium, sophisticated, editorial.

Composition: the main subject (caret and selection) is fully inside the central 60% of the frame; generous negative space around the edges; the outer edges remain dark but not pure black. Clean, high-end 3D render aesthetic with fine detail, no noise, no clutter.
```

### 補助パラメータ（生成ツール側で指定できる場合）

- Aspect ratio：16:9
- Style：photorealistic 3D render / editorial illustration（写実寄りのガラス質感）
- 生成解像度：1920×1080 以上。最終的に 1280×720 へ縮小する
- シード固定で 4 案以上生成し、§4 の QA 基準で選ぶ

---

## 3. Negative Requirements

### 生成プロンプト用（negative prompt 欄）

```
text, letters, words, numbers, readable code, characters, symbols, typography, captions, watermark, signature, logo, brand mark, cube logo, hexagon logo, Cursor logo, OpenAI logo, GitHub logo, VS Code logo, app icon, UI screenshot, realistic IDE interface, window title bar, menu bar, tabs with labels, file names, toolbar icons, browser chrome, person, human, face, hands, silhouette, robot, android, mascot, character, eyes, neon cyberpunk city, matrix rain, binary digits, hacker hoodie, cluttered, busy, noisy, grain, jpeg artifacts, blurry subject, distorted perspective, oversaturated, rainbow colors, Google colors, red green yellow blue combination
```

### 制作ルール（生成後に人が確認する）

1. Cursor 実 UI の再現、実在スクリーンショットの使用・トレースはしない
2. Cursor ロゴ（六角形の立方体と矢印の形状）に似た形を主役・中心に置かない
3. 読めるコード、文字、数字、ファイル名がないこと。AI が生成しがちな疑似文字も不可
4. 人物、顔、手、ロボット、マスコットがないこと
5. 他社ロゴや、ロゴに見える記号がないこと
6. fake UI に見えすぎないこと。パネルは「ガラスの板」であって「アプリ画面」ではない（タイトルバー、タブ、ボタンを描かない）
7. OmochiX のカラートーンから逸脱しないこと（ネオン原色、虹色、Google 4 色を使わない）

---

## 4. 品質チェック基準（生成後）

| 項目 | 合格基準 |
|---|---|
| 寸法 | 1280×720、WebP、sRGB |
| stray text | 100% 表示と 200% 拡大の両方で、文字・疑似文字がない |
| fake logo | ロゴに見える記号がない。中央の形状が Cursor ロゴに似ていない |
| human face | 顔・人影がない（パネルの反射に見える形も含む） |
| artifact | ガラス縁の破綻、線の途切れ、溶けた形状がない |
| mobile crop | 340px 幅に縮小しても、キャレットと選択範囲が識別できる |
| safe area | 主役が中央 60% 内にある。og の 1.91:1 クロップ（上下各約 25px）で主役が切れない |
| light / dark | light / dark 両ページのカード内に置いて、外縁が背景に溶けない |
| 圧縮 | WebP q82–85 で、グラデーションにバンディングやブロックノイズがない（出る場合は q90 か軽いディザ） |
| 容量 | 250KB 目標（上限 400KB） |

---

## 5. 反映

- 完成品は `scratchpad/ai-tools/image-phase-v1/ready/cursor/omochix-ai-tool-cursor-hero.webp` に置く
- 反映手順は `ready/cursor/cursor-wordpress-plan.json` と manifest §7 に従う
- HERO の alt 案（Media Library 用）：「光るカーソルを中心にコードブロックが補完されていく、Cursor の AI コードエディタをイメージした OmochiX オリジナルビジュアル」
- 注意：テーマは HERO の `<img>` に「Cursorのイメージ」という alt を自動で付けるため、Media Library の alt は og:image:alt などで使われる
