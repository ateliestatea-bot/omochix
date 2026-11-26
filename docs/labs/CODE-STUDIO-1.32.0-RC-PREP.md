# Theme 1.32.0(Code Studio, draft)RC 準備(2026-11-20)

**状態(2026-11-22 更新):基準を 1.31.1(`main` `f02c2fb`)に確定し、1.32.0 のリリース設定を作った。リリースゲート 30/30。詳細は §13。** 以下 §1〜§12 は当時の記録。 機能追加は凍結。Code Studio は `draft` のまま。本番・`main`・PR #72・ほかの worktree は変えていない。コミット・push もしていない。

## 1. PR #72 と基準

| 項目 | 状態(2026-11-20 読み取り) |
| --- | --- |
| PR #72 | **MERGED**(2026-11-20 03:06Z)。`main` = `68d81af`(Theme 1.31.1) |
| 本番 | Theme **1.31.0**(1.31.1 は未反映) |
| `main` の CI | Labs・Industry Hub・CI policy は成功。**CI change guard が失敗**:`.github/workflows/release.yml` の 2 つの手順(「Build twice and verify」に追加した行、「Release gate for the previous release (1.31.0, c9ae6e2)」)が base で固定(pin)されていない。push で入った CI・ゲートのファイル 4 件の確認も求めている |
| Code Studio の変更と `main` の差分 | 重なりなし。未コミットの変更は `origin/main` にそのまま当たる(`git apply --check` で確認、作業ツリーは変えていない) |

### 1.32.0 の基準を固定してよい条件

1. **1.31.1 が本番に反映され**、読み取りだけで本番の `style.css` が 1.31.1、Labs と Design Library のファイルの SHA-256 が `main` と一致すること。
2. **`main` の CI change guard の件がオーナーのレビューで解決**していること(PR #72 とは別の扱い。Code Studio 側からは触らない)。1.32.0 でも同じ 2 か所(前のリリースのゲート登録と pin)を変えるので、解決方法が決まらないと 1.32.0 の CI 変更が同じ理由で止まる。
3. その時点の `main` の commit を基準とし、1.31.1 のリリース manifest(パッケージのファイル数と SHA-256)を記録する。

この 3 つがそろうまで、基準・版番号・期待値は固定しない。

## 2. RC の中身(基準に依存しない部分)

テーマの変更 7 ファイル(Core は 1.10.0 のまま変更なし)。SHA-256 は今の作業ツリー:

| 区分 | ファイル(`wp-theme/omochix-theme/`) | SHA-256 |
| --- | --- | --- |
| 追加 | `assets/data/labs/code-studio.json` | `f982569215e3ffcd` |
| 追加 | `assets/js/labs/core/codestudio.js` | `2ff8497639618d3b`(2026-11-22 の G1 修正後。その前は `60b64006e9593198`、`676ba0aeab47b4d8`、`2f84710c1f3edb0b`、`3a9c506f437c0af5`) |
| 追加 | `assets/js/labs/ui/code-studio.js` | `1cc9921d10144788`(2026-11-21 の最終レビュー修正(F1)後。前は `39a40630627d74a2`、`42a6573c8af592e0`) |
| 追加 | `template-parts/labs/content/code-studio.php` | `18f72ce0c8e8e450` |
| 置換 | `inc/labs.php` | `e022fa0a4bf4c509`(2026-11-21 のレビュー修正後。前は `fb6bdec471f23691`) |
| 置換 | `assets/data/labs/catalog.json`(`code-studio` は `draft`) | `ca2241ae4693c896` |
| 置換 | `assets/css/labs.css` | `e09b36dd6c4565b5` |
| 置換(切り替え) | `style.css`(版番号) | 版番号の確定後 |

(完全な値はコミット後に `build.py` の manifest で記録する。上は確認用の先頭 16 桁。)

`inc/labs.php` は 1.31.0 と 1.31.1 で同じ(SHA-256 先頭 `50a9c613da0b65b2`)なので、置換の元は基準が変わっても同じ。

## 3. リリース設定の設計(基準の確定後に作る)

1.31.1(PR #72)と同じ形。版番号は推奨 **1.32.0**(確定は運用者)。

| ファイル | 内容 |
| --- | --- |
| `tools/release/baseline/omochix-theme-1.31.1.sha256` | 本番 1.31.1 の全ファイルの SHA-256(1.31.1 のリリース manifest から) |
| `tools/release/configs/theme-1.31.1.json`・`tests/release/expected/theme-1.31.1.json` | 今の `release.json` と期待値を保存 |
| `tools/release/release.json` | Theme 1.32.0、Core 1.10.0 は変更なし(baseline 一致を要求)。手順 8 |
| `tests/release/expected/release.json` | 手で記録:追加 4、置換 4、パッケージのファイル数(1.31.1 の数 + 4)、手順 8、変更パスの SHA-256、ロールバック 4 件、`forbidden` の例外に Labs の 7 ファイルを名指し |
| `tests/release/test_release_gate.py`・`test_release_tools.py` | 1.31.1 を「前のリリース」として試験に登録 |
| `tools/ci/required-checks.json`・`.github/workflows/release.yml` | 1.31.1 のゲートを前のリリースとして登録、pin の更新。**CI の変更なのでオーナーのレビューが必須** |
| `tools/release/README.md` | 現在のリリースの説明 |
| `wp-theme/omochix-theme/style.css` | `Version: 1.32.0` |

### 適用の順番

1. 追加 4 ファイル(順不同)。この時点では誰も読まない。
2. `inc/labs.php`(新しいデータを読む。catalog がまだ古くても、Code Studio が無いだけで既存 3 ツールは同じ)。
3. `assets/data/labs/catalog.json`(`code-studio` は `draft`:ページは 404 のまま)。
4. `assets/css/labs.css`。
5. `style.css`(切り替え。キャッシュ用の版番号が変わる)。

DB の変更なし。新しい rewrite ルールなし(パーマリンクの再保存は不要)。キャッシュは運用者の通常の手順に従う。

### ロールバック基準

- 対象:置換した 4 ファイルを 1.31.1 の内容に戻す。順番は `style.css` → `labs.css` → `catalog.json` → `inc/labs.php`(適用の逆)。
- 追加した 4 ファイルは残してよい(catalog に無い、または draft なら読み込まれない)。消す場合は置換を戻した後。
- 戻した後の確認:`style.css` が 1.31.1、`/labs/` と公開 3 ツールが 200、`/labs/code-studio/` が 404、`/labs-sitemap.xml` が 3 件、Design Library・業種別ハブ・SVG Motion が 1.31.1 と同じ応答。
- ロールバックの判断基準:公開 3 ツールのどれかが 200 以外、Labs のページにスクリプトエラー、`/labs/code-studio/` が 404 以外、サイトマップに `code-studio`、Design Library の公開ゲートの変化。

## 4. 追加の安全性確認(今回実施)

| 確認 | 結果 |
| --- | --- |
| CSS 無害化:ファズ(試験に入れた 20,000 件 + 単発で 100 万件、60 種類の部品を最大 24 個つなげる) | 読み込み・エスケープ・マークアップの残りが **0**、2 回目の無害化で変化 **0**。途中で、`@importx` のような「単語が続く」形が残る点を見つけ、`@import` と `@font-face` などは単語の区切りに関係なく取り除くようにした(実害はない形だったが、判定を単純にするため) |
| 再結合・エスケープ・`@import`・`url()`(既存の試験 + 追加) | 試験 156 件(Node)すべて合格 |
| **保存した HTML を独立したブラウザーで開く**:攻撃入力 59 件を実際の「ファイルに保存」で保存し、サイトと無関係の新しいコンテキストで `file://` として開く(Chromium・WebKit・Firefox) | スクリプト実行 **0**、ダイアログ **0**、ネットワーク要求 **0**(外部の受け口への到達 0)。ファイルにスクリプト・イベント属性・`@import`・URL・エスケープ・リンク先なし。対照として、無害化していないファイルでは 3 ブラウザーとも検出できることを確認 |
| Code Studio ブラウザー試験(上を含む) | **234 / 234** |
| draft の露出(出荷状態のスタブと、実 WordPress の QA を一時的に draft に戻して確認) | `/labs/code-studio/`(末尾の `/` なし・大文字・`?lesson=`・`/index.php/` 付きを含む)はすべて最終的に 404。一覧・Labs 内の検索(code・studio・html・css)・カテゴリ・公開 3 ツールの関連リンク・`/labs-sitemap.xml`・`robots.txt`・Learn・ホームに記載なし。サイト内検索(`?s=`)と Labs 検索で出る「Code Studio」は入力した検索語をそのまま表示しているだけ(無関係の語でも同じ)。`?pagename=labs&omx_labs_tool=code-studio` は Labs の一覧を表示(既存の draft の token-cost-calculator と同じ動き) |
| 既存の公開ゲートが弱まっていないか | token-cost-calculator の照合語 11 個はすべて部分一致のまま(単語照合の対象外)。公開スクリプト 12 ファイルで旧・新の照合を比べた差は 2 件だけで、どちらもクラス名(`labs-code`、`labs-diff__code`)。seeded の漏れ試験 5 件は従来どおり検出 |
| Labs PHP / data | 329 / 329、41 / 41 |
| リリースゲート | **24 / 30**(想定どおり。原因は §3 のリリース設定が無いことだけ) |

## 5. QA 環境

### 隔離の確認

- QA は Local の `omochix-labs-qa`。DB は `localhost`(Local の MySQL、`127.0.0.1` と `::1` だけで待ち受け)。`siteurl` は `http://omochix-labs-qa.local`。DB の設定値と投稿に `omochix.com` を含むものは 0。利用者は QA 用の管理者 1 人。プラグインは Core と Slim SEO だけ(Site Kit・計測なし)。
- **注意**:Local のルーター(nginx の 80・443)とメール確認用の mailpit は、すべてのネットワークインターフェースで待ち受けている(Local の既定)。同じ LAN の機器は、ホスト名を指定すれば QA に届き得る。本番とはつながっていない。システム設定は変えていないので、気になる場合は Local の設定かファイアウォールで運営者が対応する。
- WordPress 本体の更新確認(wordpress.org)が外へ出ようとして失敗している(QA の既定の動作。本番とは無関係)。
- Live Link・トンネルは動いていない。

### QA で変えたこと(すべて QA の中だけ)

1. テーマを 1.28.0 → この worktree(1.31.0 + Code Studio)、Core を 1.9.3 → 1.10.0 に置き換えた。
2. 固定ページ 3 件を追加:`learn/svg-motion`(ID 31)、`learn/industries`(32)、`design`(33)。パーマリンクを 1 回再生成。
3. QA の `catalog.json` のコピーだけ `code-studio` を `published`(今もこの状態)。今回の露出確認の間だけ draft に戻し、終わってから published に戻した。

### 検証が終わった後の戻し方(方針)

- **公開ゲート**:まず QA の `wp-content/themes/omochix-theme/assets/data/labs/catalog.json` を worktree の同じファイル(draft)で上書きし、`/labs/code-studio/` が 404 になることを確認する。iPhone の検証が終わるまでは published のままにする。
- **QA を元に戻す場合**(1.28.0 の状態へ):バックアップ `~/Documents/omochix-code-studio-backups/labs-qa-before-cs-20261120/` から、`theme-1.28.0.tgz` と `core-1.9.3.tgz` を展開して置き換え、`db.sql` を取り込む(追加した固定ページ 3 件とパーマリンクも元に戻る)。
- **QA を次の検証に使い続ける場合**(推奨):draft に戻したうえで、1.32.0 の RC の commit からテーマを入れ直し、ページ 3 件は残す。1.31.1 の反映後なら、QA も 1.31.1 + Code Studio にそろえる。
- バックアップは 1.32.0 の本番反映が終わるまで消さない。worktree の未コミット変更のバックアップ(`p1-*.patch`・`p1-*.tgz`)も同じ。

### 外部到達性の評価(2026-11-20、読み取りと自分の機器内の確認だけ)

| 経路 | 結果 |
| --- | --- |
| QA の nginx(10019)・MySQL(10018) | `127.0.0.1` と `::1` だけ。外から直接は届かない |
| Local のルーター(nginx の 80・443) | すべてのインターフェース。LAN の IP(en0)と tailnet の IP に `Host: omochix-labs-qa.local` を付けると、`/` と `/labs/code-studio/` が **200** |
| QA の mailpit(10015・10016) | すべてのインターフェース。LAN と tailnet から **200**(QA で送ったメールが見える) |
| macOS のファイアウォール | 無効(システム設定なので変えていない) |
| Tailscale | Serve は 8443→`127.0.0.1:3000`、8444→`127.0.0.1:8908`(どちらも「tailnet only」、QA とは無関係で、今は待ち受けなし)。**Funnel(インターネット公開)は無し** |
| Live Link・ngrok・cloudflared など | 動いていない |

評価:**インターネットからは届かない。同じ LAN と自分の tailnet の機器からは届く**(Local の既定の動作)。QA の Code Studio はブラウザーの中だけで動き、サーバーへの書き込みは無いので、影響は小さい。mailpit には QA の通知メールが残り得る。

不要な公開を避ける方法(運営者の判断。Claude は変えていない):

1. QA を使わない間は、Local で `omochix-labs-qa` を止める(ルーターから QA に届かなくなる)。
2. 公衆の Wi-Fi などでは、macOS のファイアウォールを有効にするか、Local を終了する。
3. iPhone の検証では新しい公開(Live Link・Funnel)を作らない。経路は承認を得てから、tailnet の中だけに限る。終わったら閉じる。

## 6. 実機

iPhone・VoiceOver は **未検証**。手順は `CODE-STUDIO-IPHONE-QA.md`。iPhone から QA に届く経路(Live Link など)は外部公開の操作なので、運営者の承認が要る。

## 7. 判定

**NO-GO(RC はまだ作れない)**。

- 済:実装とセキュリティの再確認、保存したファイルの確認、draft の露出確認、既存のゲートが弱まっていないことの確認、QA の隔離確認、実機の手順。
- 残り:(1) 1.31.1 の本番反映と読み取り確認、(2) `main` の CI change guard のオーナー判断、(3) その後に §3 のリリース設定を作ってゲート 30/30、(4) コミットと `build.py` での manifest と SHA の記録、(5) iPhone・VoiceOver の実機確認、(6) 教材の文章の校正。

## 8. 一時停止(2026-11-20)

機能追加は停止中。成果物は次のとおり保護した。

- 未コミットの変更 19 件(変更 9・新規 10)は、バックアップと 1 ファイルずつ一致することを確認した。最新のバックアップは `~/Documents/omochix-code-studio-backups/p1-tracked-20261120-pause.patch` と `p1-worktree-20261120-pause.tgz`(この節を書き足した後に取り直したもの)。
- worktree の `catalog.json` の `code-studio` は `draft`。QA のコピーだけ `published`(iPhone の検証用。§5 の方針で戻す)。

### 再開の条件(すべて満たしてから)

1. Theme 1.31.1 が本番に反映されている。
2. `main` の CI の問題が解決している。
3. `main` と本番の基準を、読み取りで改めて確認した。
4. 1.31.1 の manifest を取得した。

### 再開後の順番

1. 最新の基準に Code Studio を統合する(worktree を新しい `main` の上に移す。移す前にもう一度バックアップを取る)。
2. `release.json` と manifest を作る(§3)。
3. リリースゲート 30/30。
4. WordPress の QA と回帰の確認(QA を 1.31.1 + Code Studio にそろえる)。
5. Fable による最終のセキュリティレビュー。
6. リリース候補の GO / NO-GO を報告する。

セキュリティの試験と公開ゲートの基準は、再開後も緩めない。

## 9. Fable レビューの指摘の修正(2026-11-21)

Phase 2 の独立レビューで、Phase 1 のコードにも当てはまる指摘(M3・M1・L6 の一部)が見つかったため、Phase 1 にも入れた。

| 指摘 | 修正 |
| --- | --- |
| M3:CSS の無害化が正常な CSS を壊す(`scroll-behavior` → `scroll-`、`.file:hover` → `.hover` など) | IE の `behavior` はプロパティ名のときだけ、`javascript:` と URL のスキームは URL が始まりうる位置(識別子・`.`・`#` の直後でない)だけで取り除く。`url()`・`@import`・読み込みの関数(`src()` を追加)・エスケープ・`<` は今までどおり、どこにあっても取り除く |
| M1:答え合わせが大文字・小文字を区別しない(`.Card` の CSS で「`.card` に padding」が達成になる) | `normSelector()`:小文字にするのは要素名・疑似クラス名・属性名だけ。class・id・属性値は区別する |
| L6(一部):正規表現の `$` が末尾の改行を通す | 教材データの検証と `omochix_labs_is_slug()` に `D` 修飾子 |

- 変更したファイル:`assets/js/labs/core/codestudio.js`、`inc/labs.php`、`tests/labs/core.js`、`tests/labs/run.php`。
- 修正前のバックアップ:`~/Documents/omochix-code-studio-backups/p1-*-20261121-before-fix.*`。
- 差分:`p1-review-fix-m1-m3-20261121.patch`(`codestudio.js`・`core.js`。Phase 2 にも同じパッチを当てた)と `p1-review-fix-anchors-20261121.patch`(`labs.php`・`run.php`)。
- 試験の期待値の訂正:`core.js` の「`.CARD  H2` が `.card h2` に一致する」は、ブラウザーの動きと違うため「`.card  H2`(要素名だけ大文字)」に直した。ファズの判定(`behavior:`・`javascript:`)は、無害化と同じ「その位置で働くか」に合わせた(緩めたのではなく、意味を正しくした)。
- 結果:Labs PHP 330、Node core 160 / data 41、ブラウザー 234/234(3 ブラウザー、保存した HTML を単独で開く試験を含む)、ファズ 100 万件で不正 0。テーマの CSS 4,357 規則で、修正前は 21 規則を変えていた(13 規則が正常な CSS の破壊)のが、修正後は 8 規則(すべて意図どおりの `url()` の除去)。
- 1.32.0 のリリース設定を作るときは、この SHA を使う。

## 10. Fable 再レビューの指摘の修正(2026-11-21、N2〜N4)

| 指摘 | Phase 1 での修正 |
| --- | --- |
| N2:数千段の `:not(`(50KB 以内)で、ブラウザーの CSS パーサーが止まる(Firefox はタブごと停止、Chromium はプレビューが黙って止まる) | `nestingDepth()` を追加。`(` `[` `{` の入れ子が 32 段を超える CSS は、プレビューの前にエラーとして断る(理由を表示し、直前のプレビューを残す)。`sanitizeCss()` も丸ごと捨てるので、`style` 属性・保存ファイルにも届かない。`normSelector()` の再帰にも上限を置いた。サイトの CSS で最も深いのは 7 段、教材は 1 段 |
| N3:セレクタの正規化で、引用符の中の空白・記号まで変えていた | `normSelector()` を書き直し、空白の正規化は引用符の外だけで行う |
| N4:答え合わせで、空白だけ違う結合子(`.card>h2` と `.card > h2`)を別物とみなす。属性値の大文字・小文字 | 結合子を一つの書き方にそろえる。属性値は `i` フラグか、HTML の仕様が大文字・小文字を区別しないとする属性(`type`・`lang` など)のときだけ小文字にする |

- 変更したファイル:`assets/js/labs/core/codestudio.js`、`assets/js/labs/ui/code-studio.js`、`tests/labs/core.js`、`tests/labs/browser/code-studio.js`(Phase 2 と共通の 3 ファイルは同一)。
- 差分:`~/Documents/omochix-code-studio-backups/p1-review-fix-n-20261121.patch`。修正前のバックアップは `p1-*-20261121-before-n.*`、修正後は `p1-*-20261121-after-n.*`。
- 試験:Node core 168、PHP 330、data 41、ブラウザー(3 ブラウザー)は Phase 1 の試験の結果を参照。Phase 2 の `docs/labs/CODE-STUDIO-REVIEW-FIX-N-REPORT.md` にまとめた。

## 11. Fable 最終レビューの指摘の修正(2026-11-21、F1・F2・F4)

| 指摘 | Phase 1 での修正 |
| --- | --- |
| F1:引用符・コメント・エスケープを使うと N2 の深さの確認をすり抜ける(改行で終わる引用符 1 つで、Chromium と Firefox のタブが落ちた) | `nestingDepth()` を「すべてのかっこ」と「ブラウザーの字句解析(CSS Syntax 3)が見るかっこ」の大きいほうにした。`sanitizeCss()` は無害化した後の最終の CSS も確かめる。画面は最終の CSS が深すぎる場合も理由を表示して断り、`style` 属性は空にして理由を表示する。保存ファイルにも届かない |
| F2:ASCII 以外の大文字・小文字を同じとみなす | `normSelector()` の小文字化を A〜Z だけにした |
| F4:テストの穴 | 上限 32 段、無害化の前後で深さが変わる入力、`:not(.a , .b)`、タブが落ちた入力(3 ブラウザー)の試験を足した。「判定不可」の後の復帰は Phase 2 の試験 |

- 変更したファイル:`assets/js/labs/core/codestudio.js`、`assets/js/labs/ui/code-studio.js`、`tests/labs/core.js`、`tests/labs/browser/code-studio.js`(Phase 2 と共通の 3 ファイルは同一)。
- 修正前のバックアップは `p1-*-20261121-before-f.*`、修正後は `p1-*-20261121-after-f.*`。
- 試験・残留リスク:Phase 2 の `docs/labs/CODE-STUDIO-REVIEW-FIX-F-REPORT.md` にまとめた。

## 12. Fable 最終差分レビューの指摘の修正(2026-11-22、G1)

| 指摘 | Phase 1 での修正 |
| --- | --- |
| G1(Medium):`#url(` と `@url(` を引用符の無い `url(` として読んでいたため、コメントに閉じかっこを隠すと深さの確認を通った(約 16,000 段を 32 段と数えた。カスタムプロパティや `var()` の代替値に入れると Firefox のタブが落ちた。`<style>`・`style` 属性・保存 HTML の 3 経路とも) | `nestingDepth()` の字句解析で、`#` と `@` の直後の `url(` を url トークンとして読まないようにした(ブラウザーはハッシュとアットキーワードとして読むので、続く `(` はかっこ)。空白などの後の本物の `url(` は今までどおり 1 トークン |

- 変更したファイル:`assets/js/labs/core/codestudio.js`(`2ff8497639618d3b`)、`tests/labs/core.js`、`tests/labs/browser/code-studio.js`(Phase 2 と同一)。画面のコードは変えていない。
- 修正前のバックアップは `p1-*-20261122-before-g.*`、修正後は `p1-*-20261122-after-g.*`。
- 試験・残留リスク:Phase 2 の `docs/labs/CODE-STUDIO-REVIEW-FIX-F-REPORT.md` §8 にまとめた。

## 13. RC 準備(2026-11-22、Opus 5.5):基準の確定とリリース設定

本番・`main`・QA・ほかの worktree・DB・固定ページ・パーマリンク・キャッシュは変えていない。コミット・push・PR・merge・デプロイはしていない。

### 13.1 基準(読み取りだけで確認)

| 項目 | 結果 |
| --- | --- |
| `origin/main` | `f02c2fb`(PR #73 の merge、CI の変更だけ)。その前が `68d81af`(PR #72、Theme 1.31.1)。テーマと Core のファイルは `68d81af` と `f02c2fb` で同一 |
| 本番 | Theme **1.31.1**、Core **1.10.0**。公開の `style.css` と `catalog.json` の SHA-256 が `main` と一致 |
| 1.31.1 のリリース manifest | `releases/theme-1.31.1-68d81af/`(Design Library の worktree にある記録)の manifest `29d3d926…`(287 件)。`68d81af` から `build.py` で作り直すと、zip(`55ba8b8c…`)と manifest がバイト単位で一致 |
| 本番の公開ファイル | manifest のうち PHP・md・txt を除く 184 件を GET:**183 件が一致、不一致 0**、1 件(`inc/labs-data/.htaccess`)は 403(公開しない設定どおり) |
| 並行作業 | 開いている PR は #55(SVG Motion、2026-11-10 から更新なし)だけ。Design Library R-7(`~/omochix-design-r7-wt`、未コミット 89 件)は「Code Studio 1.32.0 を先に出し、R-7 は 1.32.0 の merge 後の `main` で 1.32.1 として作る」計画で、ファイルは重ならない。1.32.0 を名乗るほかのリリース作業は無い |
| Phase 1・2 の共通ファイル | 11 件が同一(`codestudio.js` `2ff8497639618d3b`、`catalog.json`、試験 6 件、文書 3 件)。差がある 8 件は Phase 2 の機能を含むファイル(画面の JS、教材、テンプレート、`labs.css`、`inc/labs.php`、`run.php`)と、Phase ごとの文書 2 件で、想定どおり |

Phase 1 の worktree は、19 ファイルが `p1-*-20261122-after-g.*` と一致することを確かめてから、ローカルのブランチ `feature/labs-code-studio-p1` を `c9ae6e2` → `f02c2fb` に早送りした(`git merge --ff-only`。未コミットの 19 ファイルと `main` の変更は重ならない)。push はしていない。

### 13.2 `main` の CI change guard

- **原因**:`f02c2fb` の push で失敗したのは、PR #73 が直した仕組みそのものの初回。push の検査は **push の前の commit(`68d81af`)の規則**で行うので、#73 が足した「承認済みの merge の push を通す」処理と新しい手順の pin を、古い規則は知らない(`'Merged pull request of this push (read-only API)' is not pinned`)。手元で `68d81af` の規則を当てると同じ FAIL が出る。
- #73 の PR の検査は PASS(ラベル `ci-change-reviewed`、digest `5f31d1ea…`)。オーナーが承認して merge している。
- **Code Studio 側の問題ではない**。1.32.0 の変更は CI の判定を変えない。
- **次の merge から通る見込み**:1.32.0 の PR の push は `f02c2fb` の規則で判定される。scratch のクローンで再現した結果:
  - PR として:REVIEW 2 件(新しい手順 1 件と、Build の手順に 1 行)と、CI・ゲートのファイル 4 件。弱める変更・SECURITY は **0**。ラベルと digest の行があれば **PASS**。
  - merge の push として(API の答えを模した):承認済みなら **PASS**。承認なし・別の digest なら **FAIL**(対照)。
  - digest は commit の内容で決まるので、本物の PR では値が変わる(PR の CI の出力を使う)。
- **`main` の赤を消す方法(オーナーの判断。Claude は変えない)**:(1) 何もしない(次の承認済みの merge の push で緑になる)、(2) 空ではない次の PR を merge する。CI 設定を緩める必要は無い。

### 13.3 リリース設定(Phase 1 の worktree、未コミット)

新しいファイル 3 件・変更 8 件(リリースの道具と試験)。Code Studio の 19 ファイルに加えて合計 30 件。

| 区分 | ファイル | 内容 |
| --- | --- | --- |
| 新規 | `tools/release/baseline/omochix-theme-1.31.1.sha256` | 1.31.1 の manifest(`29d3d926…`、287 件) |
| 新規 | `tools/release/configs/theme-1.31.1.json` | 1.31.1 の `release.json` をそのまま保存 |
| 新規 | `tests/release/expected/theme-1.31.1.json` | 1.31.1 の期待値。commit を `68d81af` に固定 |
| 変更 | `tools/release/release.json` | Theme 1.32.0、8 手順、ゲート B、ロールバック 4 件 |
| 変更 | `tests/release/expected/release.json` | 手で記録した期待値(下の表) |
| 変更 | `tests/release/test_release_gate.py` | 前のリリースを 1.31.1、その前を 1.31.0 にし、1.30.0 の試験も残す。Code Studio のファイルを外す・Labs の例外を 1 つ消す・`inc/labs.php` の順番を崩すと失敗する試験を追加 |
| 変更 | `tests/release/test_release_tools.py` | 1.31.1 の確認を保存した設定へ移し、1.32.0 はゲート B とロールバックに `/labs/code-studio/` の確認があること、最後の手順が `style.css` であることを確かめる |
| 変更(CI) | `.github/workflows/release.yml` | 「前のリリースを、その commit の上でゲートにかける(1.31.1、`68d81af`)」手順と、Build の手順に `plan --check` を 1 行 |
| 変更(CI) | `tools/ci/required-checks.json` | 上の手順の pin(`workflow_policy.py --print-steps` の出力)と `must_run` に 1 行。PR #72 と同じ形 |
| 変更 | `tools/release/README.md` | 基準と現在のリリースの説明 |
| 変更 | `wp-theme/omochix-theme/style.css` | `Version: 1.32.0` |

**期待値(`tests/release/expected/release.json`)**:版 Theme 1.32.0 / Core 1.10.0、基準の manifest `29d3d926…`、パッケージ 291 件(追加 4、置き換え 4)、8 手順、変更パスの SHA-256 `2c04320d…`、Labs の例外 7 件を名指し、順番の規則「`inc/labs.php` は追加の後、ほかの置き換えの前」、切り替えは `style.css`、ゲート B、ロールバック 4 件(最初 `style.css`、最後 `inc/labs.php`)、Core はバイト単位で 1.10.0 と同じ。

### 13.4 適用の順番とロールバック

| # | 操作 | ファイル(`wp-theme/omochix-theme/`) | SHA-256(先頭) |
| --- | --- | --- | --- |
| 1 | 追加 | `assets/data/labs/code-studio.json` | `f982569215e3ffcd` |
| 2 | 追加 | `assets/js/labs/core/codestudio.js` | `2ff8497639618d3b` |
| 3 | 追加 | `assets/js/labs/ui/code-studio.js` | `1cc9921d10144788` |
| 4 | 追加 | `template-parts/labs/content/code-studio.php` | `18f72ce0c8e8e450` |
| 5 | 置き換え | `inc/labs.php` | `e022fa0a4bf4c509`(1.31.1 は `50a9c613…`) |
| 6 | 置き換え | `assets/data/labs/catalog.json`(`code-studio` は `draft`) | `ca2241ae4693c896`(1.31.1 は `5e638970…`) |
| 7 | 置き換え | `assets/css/labs.css` | `e09b36dd6c4565b5`(1.31.1 は `ed771ad6…`) |
| 8 | 置き換え(切り替え) | `style.css` | `049c1a2a8dfed2b6`(1.31.1 は `fad8c986…`) |

- ゲート B(`style.css` の後):1.31.1 の 101 URL が変わらない。`/labs/` と公開 3 ツールが 200 でスクリプトエラーなし。`/labs/code-studio/`(末尾の `/` なし、大文字、`?lesson=`、`/index.php/`)が 404 で、一覧・Labs 検索・`/labs-sitemap.xml`・`robots.txt`・関連リンクに出ない。Design Library の URL は閉じたゲートの 404 のまま。`style.css?ver=1.32.0`。8 件が manifest と一致。
- ロールバック:`style.css` → `labs.css` → `catalog.json` → `inc/labs.php`(元の SHA-256 は `rollback.manifest` にある 1.31.1 の値。本番の公開ファイルと一致を確認済み)。追加の 4 件は `--remove-added` を付けなければ残る(読み込まれず、`/labs/code-studio/` は 404 のまま)。DB と rewrite の規則の変更は無いので、戻す操作も無い。OPcache とキャッシュは別の承認がある時だけ。

### 13.5 RC 候補の値(scratch のクローンで作った commit。本物のコミットの前の参考値)

- 作業ツリー(30 件)を `f02c2fb` の上に scratch でコミットした tree:`7e5a8ffee934dad9176969a1d69bf9db8cfee53f`(内容で決まる。本物のコミットでも同じ値になるはず)。
- `build.py` を 2 回:`omochix-theme-1.32.0.zip` が 2 回とも同じ(`a15b0468…`、291 件)。manifest `b282a689…`。`verify.py`:PASS。`plan.py --check`:8 手順(追加 4、置き換え 4)。
- 本物のコミットの後に、`build.py` の manifest と zip の SHA-256 を記録し直す(commit の SHA は変わる)。

### 13.6 試験の結果

| 試験 | 結果 |
| --- | --- |
| リリースゲート(作業ツリー) | **30 / 30** |
| 前のリリースのゲート(それぞれの commit の上):1.31.1 / 1.31.0 / 1.30.0 / Industry Hub | 29/29、30/30、30/30、33/33。`plan --check` もすべて PASS |
| `tests/release`(unittest) | 104 件 OK |
| `tests/ci`(unittest)・CI policy | 89 件 OK・違反 0 |
| CI change guard(模擬) | §13.2 のとおり(承認ありで PASS、承認なし・別の digest で FAIL) |
| Labs:PHP / core / data / Labs のリリースゲート | 330 / 176 / 41 / PASS(コスト計算機は draft) |
| Code Studio のブラウザー試験(3 ブラウザー) | 258 / 258 |
| Labs の既存ブラウザー試験(`run.js` / `firefox.js`) | 323 / 106 |
| Design Library(`tests/design-library/run.php`) | 91 / 91 |
| ほかの回帰 27 件(SVG Motion、Industry Hub、Learn、AI ツールなど) | 全件合格 |
| `php -l`(追跡している PHP 全部)・`node --check`(Labs の JS) | PASS |
| CSS ファズ 100 万件 / 字句解析モデルとの差分ファズ 40 万件 | 不正 0 / 食い違い 0 |
| 外部通信(3 ブラウザー) | 無害化した CSS・`style` 属性:0 件(対照 729 件) |

### 13.7 判定

**RC 候補:GO(コミットと PR の準備まで)。本番への反映:NO-GO(下の条件がそろうまで)。**

- 残る条件:
  1. コミット・push・PR の作成(運営者の承認)。PR では CI change guard が REVIEW で止まるので、オーナーが差分を読み、ラベル `ci-change-reviewed` と digest の行を付ける。
  2. 必須の検査 10 件が PR で緑になること。merge は運営者の判断。
  3. merge の後、本物の commit から `build.py`・`verify.py`・`plan.py` を実行して manifest と SHA を記録し、`releases/theme-1.32.0-<commit>/` を作る。
  4. 本番反映の前の pre-check(本番が 1.31.1 のままであること)と、反映・ゲート B の計画(1.31.1 と同じ形)。反映は運営者の承認ごとに 1 手順ずつ。
  5. Code Studio の公開(`draft` → `published`)は別のリリース。iPhone・VoiceOver の実機確認が条件。
- バックアップ:修正前 `p1-*-20261122-before-rc.*`、修正後 `p1-*-20261122-after-rc.*`。
