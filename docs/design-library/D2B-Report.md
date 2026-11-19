# OmochiX Design Library — D2-B 実装報告（Dark Luxury・Aurora Gradient）

- ブランチ：`feature/design-library-d1`（未コミット。commit・push・PR・main への merge はしていない）
- 実施日：2026-11-17
- 環境：使い捨てのローカル WordPress（`http://127.0.0.1:8961`、`blog_public=0`）。本番・DB・キャッシュには触れていない
- 検証状態：2 作品とも、ローカル検証に合格した後に `unverified` → `verified-local`。`verified-production` はどの作品にも使っていない（catalog-check で検査）

## 1. 実装した作品

### 1.1 Dark Luxury（`dl-web-0005` / `dark-luxury`）

架空の建築事務所「SUMIKAGE ARCHITECTS」のブランドサイトです。

- **構成**：ヒーロー → 思想（3 項目）→ 作品（4 件、横送り）→ 住まい（内観図と仕様表）→ 見学予約 → フッター
- **書体と色**：
  - 見出しは明朝体（システムフォントのみ、Web フォントは使わない）。
  - 黒は 3 段階（#0b0b0c・#111113・#18181b）。
  - 金は文字用の #c9ab6e と、装飾線専用の #8a7448 に分けた。
- **図版**：アーチ・列柱・階段の 3 層と、作品 4 件・内観を、すべてインライン SVG で自作した。写真や外部素材は使っていない。
- **動き**（常時のループは回さず、スクロールとリサイズのときだけ rAF で 1 回計算する）：
  - ヒーローの 3 層を、奥ほど遅く動かす視差。
  - 見出しの行がマスクの中から現れる。
  - 光の隙間がゆっくり明滅する（CSS）。
  - 各セクションは一度だけ現れる。
  - 広い画面（900px 以上）では作品セクションを固定し、縦スクロールで横に送る。
  - ホバーでは、金の下線・画像の 3.5% 拡大・光の通過・字間の変化。
- **操作性**：
  - 横送りしている作品カードにキーボードでフォーカスすると、そのカードが見える位置までスクロールする。
  - スクロール量が増えるのは横送りの分だけで、普通のスクロール操作の邪魔はしない。
- **スマートフォン・reduced-motion・JS 無効**：固定と視差をやめて縦に並べる。ヒーローは、文字と階段の線が重ならない配置に調整した。

### 1.2 Aurora Gradient（`dl-web-0006` / `aurora-gradient`）

架空の SaaS「MIRYU」（チームの集中時間を整えるワークスペース）の紹介サイトです。

- **構成**：
  - ヒーロー（オーロラ＋製品画面の例）→ 導入チームの声 → 機能 3 件（左右交互）→ 導入の 3 ステップ → 料金 3 プラン → CTA の帯 → フッター。
  - ヒーローの下は明るい背景にした。
- **オーロラ（Canvas 2D が主役）**：
  - 帯ごとに縦のグラデーションを 1 つ作り、横に並べた細い短冊として描く。合成は加算（lighter）。
  - 位置と明るさは、ゆっくりした正弦波の和で決める。毎フレームの乱数は使わないので、ちらつかない。
  - ポインターの近くでは帯がわずかにたわみ、下端の縁が明るくなる。
  - スクロールすると、CSS 変数でゆっくり薄くなる（追加のフレームは使わない）。
- **負荷の制御**：

  | 項目 | 通常 | 軽い設定（幅 760px 未満・CPU 4 コア以下・メモリ 4GB 以下） |
  | --- | --- | --- |
  | 描画解像度 | CSS サイズの 0.5 倍（最大 820px） | 0.33 倍（最大 520px） |
  | 帯の数 | 5 本 | 3 本 |
  | フレーム | 最大 30fps に間引き | 同じ |

  描画に 14ms を超える状態が 20 フレーム続いたら、一度だけ軽い設定に切り替える。
- **描画を止める条件**：
  - タブが非表示のとき。
  - 空が画面外のとき（IntersectionObserver）。
  - 親ページから一時停止を受けたとき。
  - reduced-motion と Save-Data のとき（静止した 1 枚だけ描く）。
- **フォールバック**：Canvas や JS が使えないときは、CSS の放射グラデーションで描いた静止のオーロラを表示する。
- **文字の読みやすさ**：コピーの下に夜空色の淡い影を置いた。オーロラがどこへ流れても小さな文字が読めることを、実際の画素で確認した（3.2）。

### 1.3 共通

- 隔離の設計と CSP は変更していない。
  - `sandbox allow-scripts`、スクリプトはハッシュで許可、`connect-src 'none'`、`frame-ancestors 'self'`。
  - デモのソースには `</script`・`</style`・`<!--` を含めていない。
- 両作品とも、カタログには次を登録した。
  - 用途、技術、スタイル、モーション、業種、配色、素材、カスタマイズ、プロンプト、手順 5 段階。
  - 関連作品・Learn・モーション、SHA-256、性能の測定値。
- 関連作品は双方向にした。
  - Minimal Editorial ⇄ Dark Luxury。
  - Liquid Glass・Bento SaaS ⇄ Aurora Gradient。
- ポスター（AVIF・WebP）と 8 秒のループ（WebM・MP4）は、`tools/design-library/capture-media.js` にそれぞれのスクリプトを追加して、実際のデモから作った。

  | 作品 | ポスター（AVIF） | ループ（WebM） | ループ（MP4） |
  | --- | --- | --- | --- |
  | Dark Luxury | 13 KB | 113 KB | 172 KB |
  | Aurora Gradient | 18 KB | 81 KB | 89 KB |

## 2. 変更ファイル

**新規**
- `wp-theme/omochix-theme/assets/design/demos/dark-luxury/demo.php`・`demo.css`・`demo.js`
- `wp-theme/omochix-theme/assets/design/demos/aurora-gradient/demo.php`・`demo.css`・`demo.js`
- `wp-theme/omochix-theme/assets/design/posters/{dark-luxury,aurora-gradient}.{avif,webp}`
- `wp-theme/omochix-theme/assets/design/loops/{dark-luxury,aurora-gradient}.{webm,mp4}`
- `tests/design-library/browser/new-works.js`：新作の単体・ブラウザ・セキュリティ試験
- `docs/design-library/D2B-Report.md`

**変更**
- `wp-theme/omochix-theme/assets/data/design/catalog.json`：2 作品の追加と、関連作品の双方向化
- `tools/design-library/capture-media.js`：2 作品の撮影スクリプト
- 既存試験の件数を「Web 6・動画 2」に更新した。
  - 対象：`catalog-check.js`、`run.php`、`http-check.php`、`browser/{lib,ui,firefox}.js`。
  - 試験自体は弱めていない。新作向けに、`verified-production` が無いことの検査と、JS 無効時の検査を追加した。
- `docs/design-library/qa/perf-results.json`：試験の再実行による性能の測定値（新作 2 件を含む）

**commit に含めないもの**：QA の画像（`qa/d2b/`・`qa/screenshots/`・`qa/d2a/after/`。試験の再実行で作り直されるもの）、ログ（`qa/logs/`。`.gitignore` で除外）、D0 の未追跡文書 2 件。QA の画像は作業ツリーにだけ残している。

## 3. 試験結果

### 3.1 全体（`run-all.sh` ＋ 86 URL の回帰）

| 試験 | 結果 |
| --- | --- |
| catalog | 108 / 108 |
| unit | 85 / 85 |
| http | 169 / 169 |
| isolation（Chromium・WebKit） | 88 / 88 |
| firefox | 27 / 27 |
| ui（Chromium・WebKit） | 204 / 204 |
| preview（D2-A） | 84 / 84 |
| perf | 46 / 46 |
| heal | 25 / 25 |
| 回帰（86 URL） | 差分 0 |
| 新作 `new-works.js`（Chromium・WebKit） | Dark Luxury 34 / 34、Aurora Gradient 40 / 40 |

D2-A の 766 項目は、件数の更新を除いて同じ内容で通っている。

### 3.2 新作の試験の内容

**隔離（`new-works.js`・firefox・isolation）**
- 直接開いたときと埋め込んだときの両方で、次を確認した。
  - CSP 違反 0、ページエラー 0。
  - デモ以外への通信 0（外部リクエスト 0）。
  - origin が `null`。
  - cookie の読み取りが SecurityError になる。
- 親ページ・cookie・Secrets に届かないことと、不正な postMessage を無視することは、共通ランタイムの試験（isolation の B 系）で確認した。新作は同じランタイムを使っている。

**レイアウト**
- 1440・834・390px で横スクロール無し（3 ブラウザ）。

**小さな文字のコントラスト（計算値）**

| 作品 | 対象 | コントラスト比 |
| --- | --- | --- |
| Dark Luxury | 金の小さな文字（11〜15px） | 8.56〜8.93:1 |
| Aurora Gradient | 紫のラベル | 7.56:1 |
| Aurora Gradient | 緑のバッジ | 5.77:1 |
| Aurora Gradient | 料金のタグ | 11.06:1 |

**実際の背景の画素で測ったコントラスト**（文字を透明にして撮影。下位 1% の画素の値）
- 測定のきっかけ：
  - Aurora の eyebrow（12px）が、修正前は 2.98:1 でした。オーロラの帯が文字の後ろを通っていたためです。
  - Dark Luxury のリード文も、SVG の線の上では 4.20:1 でした。
- 修正：
  - Aurora：帯の位置を上へ移し、コピーの下に影を置いた。
  - Dark Luxury：リード文を明るくし、文字の影を付けた。
- 修正後の値：

  | 作品 | 対象 | PC | スマートフォン |
  | --- | --- | --- | --- |
  | Aurora Gradient | eyebrow | 9.9:1 | 5.0:1 |
  | Aurora Gradient | リード文 | 12.1:1 | 14.5:1 |
  | Dark Luxury | リード文 | 6.2:1 | 7.7:1 |

**axe（WCAG 2.2 A/AA）**
- 1440・390px で、serious・critical は 0。

**動きの制御**
- reduced-motion：全文がすぐに表示され、待機中のフレームは 0。Aurora は静止した 1 枚を描く。
- JS 無効：スマートフォン幅で全文が表示される。Aurora は CSS のオーロラを表示する。
- Save-Data：Aurora はフレーム 0 で、静止した 1 枚を描く。
- Aurora は空が画面外にあるとフレーム 0 になり、戻ると再開する。
- 低性能端末（CPU 2 コア）では、キャンバス幅 520px 以下の軽い設定になる。

**デモの操作**（詳細ページに埋め込んだ状態）
- 一時停止すると全フレームが止まり、再生で再開する。Aurora はループが戻ることも確認した。
- リスタートでデモが読み直される。
- タブが非表示の間はフレーム 0。

**キーボード**
- 先頭の操作部品にフォーカスの輪が見える。
- Dark Luxury で横送りの最後の作品にフォーカスすると、そのカードが画面内に来る。

**性能**（Chromium・ローカル）

| 作品 | 転送量 | JS（gzip） | Long Task | ヒープ | fps（下位 5%） | LCP | CLS |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Dark Luxury | 9.6 KB | 2.7 KB | 0 | 0.9 MB | 97 | 2,156 ms | 0 |
| Aurora Gradient | 10.8 KB | 4.3 KB | 0 | 1.2 MB | 98 | 2,132 ms | 0 |

- いずれも 2D の予算内に収まった（LCP は CPU 4 倍・150ms・1.6Mbps に絞った条件で測定）。

### 3.3 ブラウザ

| 項目 | Chromium | WebKit | Firefox |
| --- | --- | --- | --- |
| CSP・cookie・外部通信 | ✅ | ✅ | ✅（CSP・cookie） |
| 3 幅のレイアウト・コントラスト・axe | ✅ | ✅ | 一覧・トップのみ |
| reduced-motion・JS 無効・操作・非表示時の停止 | ✅ | ✅ | 未実施 |

## 4. スクリーンショット（`docs/design-library/qa/d2b/`。commit には含めず、作業ツリーにだけ置いている）

- **最終画面**
  - Dark Luxury：`dark-luxury-pc-0`（ヒーロー）、`-pc-2`・`-pc-3`（作品の横送り・住まい）、`-phone-0`・`-phone-2`
  - Aurora Gradient：`aurora-gradient-pc-0`（ヒーロー）、`-pc-2`・`-pc-4`（機能・料金）、`-phone-0`・`-phone-2`
- **動きの代表フレーム**：`*-motion-frames.webp`（8 秒ループから 2 秒ごとに 4 枚）
  - Dark Luxury：ヒーロー → 視差 → 作品の固定 → 横送り
  - Aurora Gradient：帯の流れとポインターによるたわみ
- **既存作品との比較**：
  - `compare-pc.webp`（1280×800、2 列×3 段）：左上から Dark Luxury、Aurora Gradient、Liquid Glass、Minimal Editorial、3D Interactive、Bento SaaS の順。
  - `compare-phone.webp`（390px）：左から同じ順。

## 5. 評価（既存 4 作品との比較）

| 観点 | Dark Luxury | Aurora Gradient |
| --- | --- | --- |
| 第一印象 | 6 作品の中で最も静かで格がある。明朝の大見出しと 1 本の光の線で、一目で建築・高級の文脈が伝わる。Minimal Editorial と同じく文字が主役だが、こちらは暗い・重い・奥行きの方向。 | 色と動きで最も目を引く。Liquid Glass と同じく藍・紫系だが、ガラスではなく空と光の帯で、より情緒的。製品画面のカードで SaaS だと分かる。 |
| 余白・文字組み | 字間を広く取ったラベルと、大きな明朝の行間で余白を多く取った。スマートフォンでも見出しの 2 行は崩れない。 | 太いゴシックの見出しを 2 行に固定し（改行位置を制御）、本文は 34em に収めた。本文側の余白と行間は Bento SaaS と同じくらい。 |
| 配色・コントラスト | 金は 1 色で控えめにし、小さな金の文字は実測で 8.5:1 以上。装飾線は文字に使わない暗い金に分けた。 | 夜空・オーロラ・夜明け・明るい本文の 4 段で、ヒーローから本文へ自然につながる。小さな文字は、実際の画素で測って 5:1 以上。 |
| 動きの完成度 | 視差・マスクからの出現・横送りは、どれもスクロールに直接結び付いていて、勝手に動き続けない。最も「重さ」を感じる動き。 | 動きが常に続く唯一の 2D 作品。最大 30fps・正弦波の和で、ちらつきも急な動きも無い。止める条件は 5 種類ある。3D Interactive に次ぐ「見ていられる」動き。 |
| スマートフォンの美しさ | 視差と固定をやめても、縦長の列柱と光の線で絵として成り立つ。PC との差が最も小さい作品の一つ。 | オーロラは上部に収まり、製品カードが下に来る。PC より帯が少ない分、上品さは保ったが迫力は落ちる。 |
| 商用の参考としての実用性 | 建築・不動産・ホテル・高級ブランドにそのまま応用できる。図版を SVG で描いた構成は、写真に差し替えやすい。 | SaaS・アプリの LP に応用しやすい。Canvas の負荷制御と止める条件は、ほかの常時アニメーションにも流用できる型になっている。 |

**総評**：既存 4 作品（ガラス・誌面・3D・ダッシュボード）にない「暗い高級」と「光の情緒」を補い、作品の幅が広がった。品質（隔離・性能・アクセシビリティ）は既存作品と同じ基準で通っている。

## 6. 未検証・残る課題

- 本番環境（LiteSpeed・キャッシュ経由）での隔離・配信は未確認。このため `verified-production` にはしていない。
- 実機（macOS・iOS の Safari、Android の Chrome）は未確認。低性能端末の軽い設定は、`hardwareConcurrency` を偽装して確認しただけ。
- Firefox で確認したのは、デモの CSP・cookie と、一覧・トップの件数・フィルタまで。新作の reduced-motion・JS 無効・操作・コントラスト・axe は、Chromium と WebKit だけで確認した。
- Aurora が実際に描いているフレーム数（30fps の上限）は、コードと手順で確認しただけで、計測はしていない。
- Save-Data は `navigator.connection` を偽装して確認した。実際のブラウザ設定では確認していない。
- 「小さな文字のコントラスト」の実画素測定は、ヒーローの主要な文字だけを対象にした。
- 永続オブジェクトキャッシュ、実際の Slim SEO との連携、本番の Learn ページは、D2-A から引き続き未確認。
