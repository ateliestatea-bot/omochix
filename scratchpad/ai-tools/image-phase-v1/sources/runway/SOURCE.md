# Runway 公式ロゴ source asset

- 取得日：2026-10-03
- 公式一次情報：https://runway.com/brand-guidelines（Runway Brand Assets and Guidelines）
- ダウンロード元：同ページの LOGO PACK「Download」
  - URL：https://runway.com/assets/Runway-Brand-Guidelines.zip（公式ドメイン）
  - ページには「ダウンロードすると、該当するガイドラインと利用条件を読み、同意したものとみなす」との記載がある。ユーザーの取得指示に基づき取得した
- zip：`Runway-Brand-Guidelines.zip`
  - 41,202 bytes、Last-Modified 2026-10-03
  - sha256 `276a857249424a63c1de0d3867028e11037870ff629dfdcf7776bfafc0442a2c`
- 展開先：`Runway-Brand-Guidelines/`（無加工）
- 同梱物：Black／White の Logo（PNG 1077×328、SVG）、Black／White の Symbol（PNG 816×816、SVG）
- 採用ファイル：`Runway Brand Guidelines/Runway_White_Symbol.png`
  - 816×816、黒背景に白シンボル
  - sha256 `4b32dc1892c5d7cc33d20fe99c518ddcb585a7c4a7aea1b30937ca25b5955f06`

## ガイドライン要旨（2026-10-03 確認）

- 表記は「Runway」（Runway AI、RunwayML などは不可）
- 最小サイズ：デジタルでロゴの高さ 24px 以上
- 黒いワードマークは明るい背景、白いワードマークは暗い背景に使う
- clear space はワードマークのレッグの高さ x に、ディセンダー分として 0.5x を加えた分
- 禁止事項：
  - シンボルをワードマークの横に置く
  - シンボルとワードマークを縦に積む
  - ワードマークの「r」をシンボルに置き換える
  - ワードマークを任意のフォントで打ち直す
  - ワードマークを歪める・回転する
- 狭い場所ではシンボル単体を使ってよい

## 採用理由と加工

- AI Tools のロゴタイル（正方形、1枚の画像を light / dark 両方で使用）にはシンボル単体が適する
- White Symbol（黒地）を選んだ理由：
  - light ページでも輪郭がはっきりする
  - dark ページではタイルの枠線で輪郭が保たれる
  - 白いマークを暗い背景に置くので、ガイドラインにも合う
- 加工内容：
  1. 1px の半透明の縁を公式の背景色 #000000 でフラット化
  2. 背景だけの余白をトリミング（816 → 662。シンボルの占有率 40.6% → 50%）
  3. 512×512 に縮小（Lanczos）
  4. WebP lossless で書き出し
- 余白を詰めた理由：58px のアーカイブタイルでシンボルの高さを 24px 以上にするため（加工前 23.5px → 加工後 28.9px）
- シンボルの形状と色は無変更（トリミングで除いた領域はすべて #000000）
