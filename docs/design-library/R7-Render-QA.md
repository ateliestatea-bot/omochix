# R-7：media の再生成と QA（2026-11-20）

運営者が承認した範囲で行ったこと：

1. 描く道具を直した。
2. Noto フォントに置き換えた。
3. 54 ファイルのバックアップを取った。
4. 別のフォルダーに描き直した。
5. QA をした。

守ったこと：

- テーマの media は変えていない。
- commit、push、PR はしていない。本番は変えていない。
- 公開ゲートは閉じたまま。ローカルの使い捨てのサイトでだけ、描く間は開けて、終わった後に閉じた。
- リリースの設定、CI、`style.css` は変えていない。

## 1. 名前

| 作品 | 前 | 後 |
| --- | --- | --- |
| ai-workspace | NOKURA | TOIMORI（問いの森） |
| aurora-gradient | MIRYU | NAGIWA |
| brand-typography（動画） | YOMIBA | HONYOI（本宵。線のワードマークを描き直した） |
| 3d-interactive | KORE-01 | WAKUREI-01 |
| dark-luxury | SUMIKAGE ARCHITECTS | KAGEMURO ARCHITECTS |
| interactive-story | 紙季廊／SHIKIRO | 楮雪舎／KOZOYUKI |
| micro-interactions | HAKARI | TEMOCHO |
| liquid-glass | Aerolume、題名 Liquid Glass | Quillmere、題名 Frosted Glass UI（slug は `liquid-glass` のまま） |
| minimal-editorial | YOHAKU（季刊 余白） | IKIMA（季刊 イキマ）。本文の「余白」はそのまま |
| bento-saas | Tilely | Grivio |
| logo-reveal-short（動画） | HOSHIORI（星を織る） | TSUKIAMI（月を編む） |

- 名前はすべて架空。名前の選び方と、名前についての確認の記録は、非公開の記録に分けた。
- 3d-interactive、minimal-editorial、ai-workspace、brand-typography は、後で名前を替え、その作品の media を、新しいフォルダーに描き直して入れ替えた（QA の結果は非公開の記録）。
- 今の名前（11 件）：TOIMORI、NAGIWA、HONYOI、WAKUREI-01、KAGEMURO、楮雪舎（KOZOYUKI）、TEMOCHO、Quillmere、IKIMA、Grivio、TSUKIAMI。題名は Frosted Glass UI。

## 2. 変えたもの（R-7 の worktree、commit していない）

| 区分 | ファイル |
| --- | --- |
| 道具 | `tools/design-library/render-guard.js`（新規）、`capture-media.js`、`render-video.js` |
| フォントの記録 | `tools/design-library/fonts/OFL.txt`、`fonts.lock.json`（新規） |
| 動画の素材 | `video-sources/*/index.html`（3 件：フォントを Noto に。名前） |
| demo（名前だけ） | 9 作品の `demo.php`、`demo.js`、`demo.css`（合わせて 16 ファイル）。`liquid-glass` の ✦ は、Noto に字が無いので、同じ大きさのインラインの SVG にした |
| カタログ | `catalog.json`：名前、題名、demo の SHA-256（`demo-hash.js --write` で更新） |
| 試験 | `tests/design-library/http-check.php`（Aerolume を Quillmere に） |

## 3. 道具の守り

- 出力の先（`DL_OUT`）がリポジトリの中なら、止まる。空でないフォルダーなら、止まる。
  - 試験：テーマの中、リポジトリの中、指定なし、空でないフォルダーの 4 件とも、何も書かずに止まった。
- 入っているフォントが、`fonts.lock.json` の SHA-256 と一致しなければ、描かない。
- 撮影の時だけ、フォントを置き換える。
  - 要素ごとに、インラインで指定する。
  - 疑似要素（`::first-letter` など）は、CSSOM の規則をメモリーの上だけで書き換える。
  - demo のファイルは変えない。
- フォントの監査：Chromium の DevTools（`CSS.getPlatformFontsForNode`）で、実際に使われたフォントを取る。Noto 以外があれば止まる。
  - 陰性の対照：置き換えずに監査すると、`.SF NS`、Hiragino Sans、Hiragino Mincho ProN を検出して FAIL になった。
  - 実際に検出して直したもの 2 件：
    - liquid-glass の ✦（Zapf Dingbats で描かれていた）。
    - minimal-editorial のドロップキャップ（`::first-letter` がヒラギノ明朝のまま）。

## 4. 成果物（すべてリポジトリの外）

| 内容 | 場所 |
| --- | --- |
| 今の 54 ファイルの写し（読み取り専用） | `<media 保管フォルダー>/archive-1.31.1-68d81af/`（`SHA256SUMS` は `ac3942ee3b57c648fb2ada1681e1989f77ecf7422e2dea2b38db69334dfdc1a9`、54/54 が一致） |
| **新しい 54 ファイル（最終）** | `<media 保管フォルダー>/final-r7-20261120-230451/`（`SHA256SUMS` は `d152a12840c18b909ff41e823166cd3860bf50d253deec74a5740365bc3bdd38`。監査は `audit/`、新旧の比較の画像は `qa/`） |
| フォント | `<media 保管フォルダー>/fonts/` |
| 途中の出力（使わない） | `render-r7-*`、`trial-*`、`neg.*`（監査で止まった回、試しの回、守りの試験の回） |

- 最終のフォルダーの中身：
  - 9 作品のポスターとループは、`render-r7-20261120-230022` から。
  - brand-typography の 6 ファイルは、ワードマークの字間を直した `render-r7-bt-230440` から。

## 5. QA の結果

| 項目 | 結果 |
| --- | --- |
| ファイルの組 | **PASS**：今の 54 ファイルと、名前がすべて同じ（1 対 1 で置き換えられる） |
| フォントの監査 | **PASS**：12 作品、33 回。Noto Sans JP が 11,659 字、Noto Serif JP が 2,342 字、ほかのフォントは 0 |
| 形式 | **PASS**（54/54）：コーデック、幅と高さ、画素の形式が、今のものと同じ。長さはループが 8 秒（動画のループは 6、8、10 秒）、本編が 6、8、10 秒。音声は無い。mp4 は faststart。AVIF のポスターは 60 KB 以下、ループは 600 KB 以下（最大は 414 KB） |
| 見た目（新旧を並べて見た） | 配置、色、図は同じ。違いは名前と書体（Noto の方が欧文の線が少し太い）。運営者に見てほしい点：① micro-interactions のポスターは、2 つ目のスイッチが ON で、「保存しました」の表示が出ている（ポスターの台本の操作が反映された状態。今のポスターは、反映の前に撮られていた）② data-chart-explainer の「×2.4」の × が、Noto では大きく見える ③ brand-typography のワードマーク |
| catalog-check | **PASS**（127/127） |
| PHP lint、JS の構文 | **PASS** |

## 6. 戻したもの

- Mac の利用者のフォントのフォルダーから、Noto を外した（残りは 0）。
- ローカルのサイトのテーマの向き先を、元に戻した（別の作業用の worktree）。
- ローカルの公開ゲートを閉じた（`/design/` は 404）。

## 7. 確かめていないこと

- Design Library の通しの試験（1,377 件）。
  - media をテーマに入れる段階で、流す。
  - demo の名前は変えたので、その時に、画面の写真の比べと、CSP の hash も確かめる。
