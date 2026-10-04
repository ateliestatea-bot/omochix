# Cursor Image Canary — 最終品質監査

監査日：2026-10-01
対象：`cursor`（post 65）、Logo 1点・HERO 1点
production：READ ONLY（GET のみ）。表示確認は production HTML のローカルコピーに新画像を差し込んで実施した

---

## ファイル

| File | 寸法 | 形式 | bytes | sha256 |
|---|---|---|---|---|
| `omochix-ai-tool-cursor-logo.webp` | 512×512 | WebP lossless、不透明 | 8,032 | `745ff7f0…0f08ed` |
| `omochix-ai-tool-cursor-hero.webp` | 1280×720 | WebP lossy（VP8）、alpha なし、EXIF/XMP/ICC なし | 67,858 | `fcf59c3b…bd1f1b` |

HERO はユーザーが生成したファイル（会話添付）を無加工で `ready/cursor/` に配置した。

---

## Logo：PASS

| 項目 | 結果 |
|---|---|
| official asset integrity | 公式 `APP_ICON_25D_DARK.png` を Lanczos で縮小しただけ。ピクセル差 0。形状・色・エフェクトの変更なし |
| dimensions / aspect | 512×512、1:1 |
| transparency / background | 公式 icon の不透明背景をそのまま使用。プレート不要 |
| light mode | detail（80px）、archive card（58–64px）とも輪郭が明瞭 |
| dark mode | タイル枠線で輪郭が保たれ、キューブ面がはっきり見える |
| blur | 150px thumbnail でもシャープ（Laplacian 分散 548） |
| clipping | なし（公式 icon 内の余白を保持。タイルの角丸は 4 隅の背景だけを切る） |

---

## HERO：PASS（注記あり）

| 項目 | 結果 |
|---|---|
| 1280×720 | ○（1.7778） |
| stray text | ○ 判読できる文字・数字・ファイル名はない |
| fake logo | ○ ロゴ・ロゴ状の記号はない。Cursor キューブ形状もない |
| human face | ○ 人物・顔・手・ロボットはない |
| artifact | ○ ガラス縁・光ラインに破綻や溶けた形はない。手前床の大きなボケブロックは意図的な被写界深度として自然 |
| mobile crop | ○ 375px 幅で 337×189 表示。キャレットと選択範囲、AI の光の流れが判別できる |
| safe area | ○ 主役（キャレット x≈700、y≈343）は中央 60% 内。中央 1:1 クロップでもメインパネル全体が残る |
| detail 表示 | ○ `.tool-detail__media` は 16:9 + `object-fit: cover` なので、トリミングなしで全体が表示される（desktop 約 400px 幅、mobile 337px 幅） |
| light / dark ページ | ○ ダーク基調の画像で、両モードとも枠線・角丸カード内で外縁が背景に溶けない（外縁の平均輝度 14–22） |
| compression | ○ 暗部グラデーションをコントラスト強調して確認し、バンディング・ブロックノイズは目立たない（block-edge 比 1.16） |
| 色 | ○ 高彩度ピクセルの 100% が青〜紫の色相（180–300°）。Google 4 色・原色ネオンはない |
| OGP | ○ 1.91:1 クロップ（1280×670、上下各 25px カット）、X の 2:1（上下各 40px）とも主役は残る。1200×630 推奨以上の解像度 |

### 注記（spec との差分。ブロッカーではない）

1. **fake UI 傾向**
   - 中央パネル左上にウィンドウの 3 点ドットがあり、各パネルにフォルダ／ファイルアイコンのツリーがある
   - spec の「タイトルバー・ボタンを描かない」からは少し外れ、IDE 風の見た目が強め
   - ただし Cursor 固有の UI（チャットパネル、タブ名、ロゴ等）の再現ではなく、汎用的な抽象表現にとどまる
   - 読める文字もないため、許容範囲と判断した
2. **疑似グリフ**
   - サイドパネルのツリー罫線部に、括弧や「D」に似た極小の記号がある
   - 原寸表示でもほぼ判読できず、表示サイズ（最大約 400px 幅）では視認できない
3. 上記を厳密に除きたい場合は、negative に `window control dots, file tree icons, folder icons` を追加して再生成する（任意）

---

## production 現状（READ ONLY、2026-10-01）

- REST の `ai-tools?slug=cursor`：`featured_media: 0`、`meta.tool_logo: 0`、`tool_status: active`
- detail ページ
  - Logo は頭文字タイル「C」、HERO はプレースホルダー
  - og:image はサイト既定画像（1732×908、alt「ChatGPT Image …」）
  - `SoftwareApplication` に `image` なし
- 比較用の perplexity では、featured image を設定すると og:image が featured の原寸に、`SoftwareApplication.image` が `#thumbnail` 参照に自動で切り替わることを確認した
- archive：Cursor は `/ai-tools/page/4/` に掲載。カードの Logo タイルは `object-fit: contain`

## Theme / Plugin 変更

不要。
