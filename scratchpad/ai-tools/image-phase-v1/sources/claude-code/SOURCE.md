# Claude Code 公式ロゴ source asset

- 取得日：2026-10-03
- 公式一次情報：https://www.anthropic.com/news の「Download press kit」
- ダウンロード元：
  - https://www.anthropic.com/press-kit
  - 上記から Anthropic 公式 CDN の https://www-cdn.anthropic.com/ae59ca4ca194dac9c9dc3bc78c5829468cb0e8af.zip へ 307 リダイレクト
- zip：`Anthropic-media-resources.zip`
  - 26,465,941 bytes
  - sha256 `c68ac92df86c825f95177e24016fcc9a8863a3fd4ca344fe6f0700b2c1e07151`
  - 配布名：「Anthropic media resources.zip」
- 展開先：`Anthropic-media-resources/`（無加工）
- 同梱物：
  - Anthropic logo / symbol
  - Claude logo（2289×500）
  - Claude Code logo（3638×500、横長ロックアップ。Ivory、One-color、Slate）
  - Claude Spark（937×937）
  - Claude icon（Rounded / Square、1280×1280）
  - 経営陣の写真と略歴（未使用）
- ロゴの利用ガイドライン文書は同梱されていない。問い合わせ先：press@anthropic.com
- 採用ファイル：`Anthropic media resources/Anthropic logos/Claude logos/4 Claude icon/PNG/ClaudeIcon-Square.png`
  - 1280×1280、ほぼ不透明（alpha 254）
  - sha256 `f252cddcf91362ce4e01655044c7d8308c32b4972b1c16b459839ad8c61a0a68`

## 採用理由と加工

- Claude Code logo は横長のロックアップ（7.3:1）。正方形のタイル（58〜96px）に収めると文字の高さが数 px になり、判読できないため採用しなかった
- 正方形の公式アイコンは Claude icon だけなので、これを採用した
- 既存の `claude` ツールの Logo（attachment 57）も同じ `ClaudeIcon-Square.png` を使っている。Claude と Claude Code は同じマークになる（運営者判断事項）
- 加工内容：
  1. 公式の背景色 #D97757 でフラット化
  2. 512×512 に縮小（Lanczos）
  3. WebP lossless で書き出し
- 形状と色は無変更（単純な縮小結果とのピクセル差 0）
