# R-7：54 media の反映と、全試験の結果（2026-11-20〜21、ローカルの QA だけ）

- commit、push、PR、本番への反映、本番の DB、本番の公開ゲートには触れていない。
- ほかの worktree、共有のリリースの設定、CI の設定は変えていない。

## 1. 照合と反映

| 項目 | 結果 |
| --- | --- |
| 候補 3（`final-r7-c3-20261120-234934`） | `SHA256SUMS` は `41d12abc…`。54/54 が一致。フォントの監査は 33/33 が PASS |
| 今の 54 ファイルの写し（`archive-1.31.1-68d81af`） | `SHA256SUMS` は `ac3942ee…`。54/54 が一致 |
| パスの対応 | 54 のパスが 1 対 1 で同じ |
| R-7 の worktree への反映 | 54 ファイルを写した。テーマの media は、54/54 が候補 3 と一致。`git status` の media の変更は 54 件だけ。ほかのファイルは、写す前と同じ |

## 2. 試験の期待の値の変更（題名「Liquid Glass」を「Frosted Glass UI」にしたことによるもの）

| ファイル | 変えたこと | 理由 |
| --- | --- | --- |
| `tests/design-library/browser/ui.js` 147 行目 | 絞り込み（`motion_type=ambient`）の結果の期待の値を、`["Liquid Glass","Aurora Gradient"]` から `["Frosted Glass UI","Aurora Gradient"]` にした | カタログの題名を変えたため |
| `tests/design-library/browser/ui.js` 272 行目 | 確認の名前（説明の文字）だけを変えた。確かめる中身は同じ | 説明を、題名に合わせた |
| `tests/design-library/run.php` 132 行目 | 関連の作品の題名の期待の値を、`'Liquid Glass'` から `'Frosted Glass UI'` にした | カタログの題名を変えたため |

- 前の段階で、`tests/design-library/http-check.php` の `Aerolume` も `Quillmere` に変えた（ブランド名の変更による）。
- 期待の値を緩める変更はしていない。

## 3. Design Library の全試験（`run-all.sh`、ローカルの使い捨てのサイト 127.0.0.1:8961）

| 組 | 結果 |
| --- | --- |
| catalog | 127 PASS / 0 FAIL |
| unit | 91 / 0 |
| http | 171 / 0 |
| isolation（chromium、webkit） | 106 / 0 |
| firefox | 32 / 0 |
| ui（chromium、webkit） | 222 / 0 |
| layout（chromium、webkit） | 116 / 0 |
| d2d-video（chromium、webkit、firefox） | 56 / 0 |
| d2d-new（chromium、webkit） | 96 / 0 |
| d2d-web（chromium、webkit、firefox） | 96 / 0 |
| d2e-story（chromium、webkit、firefox） | 81 / 0 |
| preview（chromium、webkit） | 102 / 0 |
| perf（chromium） | 67 / 0 |
| heal | 14 / 0 |
| **計** | **1,377 PASS、0 FAIL**（rc 0） |

- **SKIP**：heal の中の任意の 2 つ。どちらも INFO で、前の回と同じ。
  - 「DL_MYSQL not set: statement counts skipped」：DB の文の数を数える確認。
  - 「DL_ROLLBACK_THEME not set: rollback / redeploy skipped」：旧テーマに戻して、もう一度入れる確認。
- 試験の間だけ、ローカルの公開ゲートを開けた。テーマの向き先も R-7 の worktree にした。終わった後、ゲートを閉じ、向き先を戻した（`/design/` は 404）。

## 4. そのほかの確認（セキュリティを含む）

| 項目 | 結果 | 補足 |
| --- | --- | --- |
| isolation（CSP、sandbox、hash） | PASS（106） | 3 の表に含まれる。名前を変えた demo も、inline script の hash が CSP に合う |
| demo の中の外の URL | 0 件 | — |
| PHP lint（テーマと試験の 108 件） | 失敗 0 | — |
| JS の構文（変えたもの） | OK | — |
| workflow policy | PASS（違反 0） | — |
| release の unittest | 100/100 | — |
| **release-gate** | **25 PASS、4 FAIL（想定どおり）** | `release.json` は 1.31.1 のまま（2 件）。R-7 が変えた 72 件（media 54、demo 17、カタログ）を「承認されていない差」として正しく検出した。リリースの設定は、今回は変えてはいけないので、統合の時に R-7 の release.json を作れば PASS する |
| **tests/ci** | **80/81（1 件は想定どおり）** | `test_gate_passes_on_this_tree` が、上の release-gate を、作業中の tree で実行するため。原因は同じ |

## 5. ほかの作業への影響の確認

| 対象 | 結果 |
| --- | --- |
| main（origin） | `68d81af` のまま |
| ほかの worktree 39 件 | 38 件は、HEAD と、commit していない変更の状態が、試験の前後で同じ |
| Code Studio の別の作業用の worktree | HEAD は同じ。commit していない変更の状態は、試験の間に変わった。読むだけで確かめると、変わったのは Labs と Code Studio のファイルで、Code Studio の側の作業による（非公開の記録）。私の操作はこの場所に触れていない |
| SVG Motion の作業用の worktree | 変わらない |
| Mac の利用者のフォントのフォルダー | Noto は残っていない |

## 6. R-7 の worktree の状態

- ブランチは R-7 の作業ブランチ。HEAD は `68d81af`。upstream は無い。
- 変更：80 件（media 54、demo 17、カタログ、道具 2、動画の素材 3、試験 3）。
- 未追跡：5 件（`render-guard.js`、`fonts/`、文書）と、試験のログ（`docs/design-library/qa/logs/`、追跡していない）。

## 7. Code Studio 1.32.0 との統合の順番（案）

| # | 段階 | 内容 | 承認 |
| --- | --- | --- | --- |
| 1 | Code Studio 1.32.0 を先に出す | 準備が進んでいて（RC の準備の文書がある）、R-7 は名前の権利の確認を待つ。Code Studio が、1.31.1 を基準にしたリリースの設定、PR（CI のレビューの手続き）、merge、本番への反映を行う | Code Studio の側で |
| 2 | R-7 を main に合わせる | 1.32.0 が merge された後、R-7 のブランチを、その main に rebase か merge する。触るファイルは重ならない（R-7 は Design Library だけ、Code Studio は Labs）。`style.css` の版とリリースの設定は、R-7 では、まだ触らない | commit（ローカル）の承認 |
| 3 | R-7 のリリースの設定を作る | 版は 1.32.0 の次（例：**1.32.1**。main に合わせて決める）。基準は 1.32.0 の manifest。置き換えは 72 件（media 54、demo 17、カタログ）＋ `style.css`。`tools/release/*`、release の試験、`release.yml`、`required-checks.json`（CI の変更なので、オーナーのレビューの手続きが要る） | 承認 |
| 4 | 試験をやり直す | release-gate と tests/ci が PASS すること。全試験 1,377 件、build を 2 回、verify、plan、予行（ロールバックを含む） | — |
| 5 | PR と merge | ラベル `ci-change-reviewed` と digest の行。必須の検査 10 件 | PR、merge の承認 |
| 6 | 本番への反映 | ゲートは閉じたまま。1.31.1 と同じく、pre-check、反映、post-check の順 | 承認と、運営者の端末での実行 |
| 7 | 一般公開 | 名前の権利の確認、R-2、R-5（ステージング）、R-7 の利用の条件、ゲートを開けること | 承認 |

- Code Studio が遅れる場合は、R-7 を先に 1.32.0 として出すこともできる。そのときは Code Studio の基準が変わるので、両方の担当がその場で合意する。
