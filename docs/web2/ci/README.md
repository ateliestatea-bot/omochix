# Web 2.0 CI（Phase 1）

更新日：2026-11-19（最終監査で CI change guard の信頼の起点と承認方法を変更）

main（＝本番と同じ状態）を守る Release gate はそのままにして、統合ブランチ `integrate/web2` で並行開発を進められるようにする CI の構成です。
GitHub の Ruleset は運営者が別に承認してから設定します。このファイルは推奨値だけを示し、設定そのものは変えません。

## workflow と必須チェック

| workflow | チェック名 | 実行するとき | main で必須 | integrate/web2 で必須 |
|---|---|---|---|---|
| `release.yml` | `Release gate (PHP 8.1)`・`(PHP 8.3)` | main 向けのすべての PR／main への push／手動実行 | ○ | — |
| `tests.yml` | `Tests (PHP 8.1)`・`(PHP 8.3)` | すべての PR／main・integrate/** への push | ○ | ○ |
| `industry-hub.yml` | `Industry Hub (PHP 8.1)`・`(PHP 8.3)` | 同上（対象ファイルの絞り込みは廃止） | ○ | ○ |
| `labs.yml` | `Labs (PHP 8.1)`・`(PHP 8.3)` | 同上（対象ファイルの絞り込みは廃止） | ○ | ○ |
| `integration.yml` | `Integration gate` | integrate/web2 向けのすべての PR／integrate/web2 への push | — | ○ |
| `ci-guard.yml` | `CI change guard` | すべての PR（**pull_request_target**。ラベルの付け外し・説明文の編集も含む）／main・integrate/** への push | ○ | ○ |
| `ci-policy.yml` | `CI policy` | すべての PR／main・integrate/** への push | ○ | ○ |
| `svg-motion.yml` | （変更なし） | 変更なし | — | — |

必須チェックの一覧は `tools/ci/required-checks.json` にあり、Ruleset の設定はこれに合わせます。

- **対象ファイルの絞り込み（`paths`）:** 必須チェックを出す workflow には付けません。付けると、対象外の PR でチェックが出ないまま「待機中」で止まります。
- **`edited`:** PR の送り先ブランチを変えたときに再実行させるため、`types` に入れています。古い送り先で出た結果は使われません。
- **ジョブ名:** workflow ごとに区別できる名前にしました。以前は `PHP 8.1` が2つの workflow で重複していました。

## Release gate（main）

ジョブは毎回、同じ名前で実行されます。何を検査するかは `tools/ci/release_scope.py` が決めます。

- **PR がリリース対象のパスを変えるとき:** 従来どおりすべてを検査します。
  - 対象のパス: `wp-plugin/`、`wp-theme/`、`tools/release/`、`tests/release/`、`tools/ci/`、`tests/ci/`、`.github/`
  - 実行するもの: ゲート、前回リリースのゲート、リリースツールのテスト、2回ビルドと verify・plan
- **PR が対象のパスを変えないとき:** 正常に終了し、「対象外である理由」と変更されたファイルの一覧をステップのサマリーに記録します。
- **main への push・手動実行:** 必ずすべてを検査します。ただし事後の検査なので、push そのものは止められません。止めるには Ruleset が必要です。
- **判定に迷う場合**（merge commit が無い、git がエラーを返すなど）: すべてを検査します（安全側に倒します）。
- **移動・削除:** `--no-renames` で比較するので、リリース対象のファイルを外へ移動・削除しても対象と判定されます。
- **判定スクリプト自体:** workflow のステップの中で、PR の送り先（base）のコミットから `git archive` で `tools/ci/` を取り出し、`python3 -I` で実行します。
  - 取り出す処理も含めて、PR 側のファイルは一切実行しません。以前の `use-base-copy.sh` は PR 側のファイルで、書き換えると検査を回避できたため廃止しました。
  - ステップのスクリプト全体を `required-checks.json` に固定しているので、ステップを書き換えると CI change guard がポリシー違反として止めます。
- **検査ステップの固定:** `release.yml` の gate ジョブ（Integration gate・CI change guard も同じ）は、全ステップを順番どおり、ステップ単位で丸ごと `required-checks.json` の `pinned_steps` に固定しています。名前・`if`・`env`・`run`・`uses`・`with` のどれが違っても検出します。コマンドの部分一致では判定しません。
  - 似たリリース検査が2つある場合（前回リリースのゲートが2本など）、どちらを消しても失敗になります。
  - 固定されていないステップが足された場合（新しいリリース検査、または検査の前に tree を書き換えうるステップ）は、CI change guard が `ci-change-reviewed` の承認を求めます。CI policy は、PR 側の一覧がそのステップを固定するまで失敗します。
  - Release scope より後のステップは、すべて `if: steps.scope.outputs.target == 'true'` で動く必要があります（`gated_after`）。条件が無い、または別の条件だと失敗します。
  - ワークフロー全体またはジョブに `env`・`defaults`（ジョブでは `container`・`services` も）を書くと失敗します（`BASH_ENV` やシェルの差し替えで、固定したステップの動きを変えられるため）。
  - **リリース検査を足す手順:** ステップを `if: steps.scope.outputs.target == 'true'` 付きで足し、`python3 tools/ci/workflow_policy.py --print-steps .github/workflows/release.yml gate` の出力で `pinned_steps` を更新し、同じ PR に含めます。
- **base に `tools/ci/` が無いとき**（`tools/ci/` を持ち込む最初の PR）: 判定スクリプトを実行せず、すべてを検査します（fail-closed）。

`tests/release/release-gate.php` と `tools/release/` は一切変えていません。

## Integration gate（integrate/web2）

`tools/ci/integration_gate.py` を、Release gate と同じくステップ内で base から取り出したコピーで実行します。base に `tools/ci/` が無いとき、および新しいブランチの push で origin/main との merge base が無いときは失敗します（fail-closed）。新しいブランチの push は、main から分かれた地点の規則で判定します。
対象は `integrate/web2` だけです。ほかの `integrate/*`（既存のローカルブランチ `integrate/p0-theme-1.27.1-into-main` など）は Web 2.0 の工程ではないので、Tests などの共通チェックだけが走り、所有範囲で止まることはありません。

1. **版番号:** 次の値が変わっていないことを確認します。
   - Core の Version ヘッダー、`OMOCHIX_CORE_VERSION`、readme.txt の Stable tag
   - テーマの style.css の Version
   - Industry Hub の版マーカー
2. **リリース設定:** `release_only` のパスが変わっていないことを確認します（`tools/release/**`、`tests/release/**`、style.css、readme.txt）。
3. **ファイル所有範囲（PR のみ）:** 次をすべて満たす必要があります。
   - head ブランチがこのリポジトリの `web2/<stream>/<topic>` である
   - 変更したファイルがすべてその工程の所有範囲にある
   - 所有範囲は `docs/web2/ownership.json` を **base から**読むので、PR の中で自分の範囲を広げても効きません
   - `held` のパス（Design Library、front-page・header・footer）は、どの工程でも失敗にします
   - `held` と `release_only` は大文字小文字を区別せずに照合します（`Front-Page.php` は macOS では同じファイル）
4. **ファイルの種類:** シンボリックリンクとサブモジュールは失敗にします（所有範囲のパスから held のファイルを指せるため）
5. **main の取り込み:** 取り込む commit がすでに main に含まれていれば、理由を記録して合格にします（main の release 状態の取り込み）。

## CI change guard と CI policy

### CI change guard

**信頼の起点:** PR では `pull_request_target` で動きます。GitHub は workflow ファイルを **base ブランチから**読むので、PR が `ci-guard.yml` を書き換えても消しても、その PR には base のガードが走ります。
ジョブは PR を「データとしてだけ」読みます。

- PR の head を checkout し、GitHub と同じ merge を `git merge-tree` で作ります。
- `tools/ci/` は base のコミットから `git archive` で取り出し、`python3 -I` で実行します。PR のスクリプトは一切実行しません。
- トークンは読み取りのみで、Secret は使いません。
- `pull_request_target` を使ってよいのはこのファイルだけです（`required-checks.json` の `pull_request_target_allowed`）。ほかの workflow に書くとセキュリティ違反になります。

判定は次のとおりです。

1. **セキュリティ違反**（書き込み権限、`pull_request_target`・`workflow_run`、SHA 固定でない action、トークンを残す checkout、スクリプトへの埋め込み、必須チェックと同じ名前のジョブ＝成りすまし）
   - **どのラベルでも通りません。** 必要なら、先にポリシー自体を変える PR を別に出してレビュー・merge します。
2. **必須チェックを弱める変更**（ジョブの削除・改名、matrix の縮小、`paths` の追加、push トリガーの削除、`continue-on-error`、ジョブ全体の `if`、必須コマンドの削除・`if: false`・`|| true` など）
   - `ci-weakening-approved` で承認したときだけ通ります。Ruleset の変更と同時に使います。
3. **CI・ゲートのファイルの変更**（保護パスは `required-checks.json` の `protected_paths`）
   - `ci-change-reviewed`（または `ci-weakening-approved`）で承認したときだけ通ります。
   - push のときは記録だけです（push 自体は止められないので、Ruleset で PR を必須にします）。

**承認のしかた:** ラベルだけでは承認になりません。

1. 失敗したログに `ci-change-reviewed: <ダイジェスト16桁>` の行が出ます。
2. 運営者が差分を読み、その行を PR の説明文に貼り、ラベルを付けます。説明文の編集とラベルの付け外しで、ガードは再実行されます。
3. ダイジェストは「変更された保護ファイルの base 側と head 側の内容」と「承認する違反」から計算します。
   - **レビュー後に CI のファイルを変える commit が積まれると、ダイジェストが変わって再び失敗します。** ラベルは GitHub に残りますが、承認は引き継がれません。
   - CI 以外のファイルだけの commit や、タイトルの編集による再実行では、承認はそのままです。

### push のとき（merge の後）

- **何を確認するか:** CI のファイルの変更と「要レビュー」の違反（新しいリリース検査など）は、次の3つをすべて満たすときだけ通ります。
  1. その push が、承認された PR の merge commit である
  2. その PR に、**この内容と同じダイジェスト**の承認（ラベルとダイジェストの行）が付いている
  3. 新しいステップが、push された commit 自身の `pinned_steps` で固定されている（要レビューの違反の場合）
- **PR の読み取り:** PR の情報は、直前のステップ「Merged pull request of this push」が GitHub API（`commits/{sha}/pulls`、読み取りのみ。ジョブの権限は `pull-requests: read`）から `$RUNNER_TEMP/ci-merged-pulls.json` に保存したものを使います。
- **FAIL になる場合:**
  - 直接 push
  - squash・rebase での merge
  - 複数の commit をまとめた push
  - API が読めない
  - 承認の無い PR の merge
  - ダイジェストが違う
- **弱める変更とセキュリティ違反:** push では承認があっても FAIL です。
- **以前の誤判定:** 以前は push で要レビューの違反を承認する手段がなく、承認済みの PR を merge しても必ず FAIL していました（run 44665403771）。
- **Ruleset との関係:** この確認は Ruleset に依存しません。Ruleset が無効になっても、承認の無い CI の変更が main に入れば、push のときに FAIL として出ます。
  - ただし push では、push された commit の workflow が実行されます。悪意のある直接 push（ガードの workflow ごと書き換えるもの）を止めたり検知したりできるのは、Ruleset（PR 必須・必須チェック・バイパスなし）だけです。

### CI policy

`ci-policy.yml`（`pull_request`）で、この commit 自身の一覧に対してポリシーを確認し、自己テスト `python3 -m unittest discover -s tests/ci` を実行します。PR のコードを実行するので、`pull_request_target` にはしません。PR 側で書き換えられるので、整合性の確認であって、変更を判定するのは CI change guard です。

#### Release gate の自己テスト（`ReleaseGateStillStrict`）

自己テストは、`tests/release/release-gate.php`（本体は変えない）が厳格なままであることを、2つの面から確かめます。

- **合格側**：リリースとして正しいファイル一式なら合格する。
- **拒否側**：承認されていない変更があれば失敗する。

**合格側を確かめるファイル一式は、送り先によって変わります。**

| 送り先 | 合格側を確かめるファイル一式 |
|---|---|
| main 向けの PR、main への push、手動・ローカルでの実行、送り先が分からない場合 | **この commit**（以前と同じ） |
| `integrate/<名前>` 向けの PR、または `integrate/<名前>` への push | **main との分岐点**（`git merge-base HEAD origin/main`）の `wp-plugin`・`wp-theme` |

統合ブランチを分岐点で確かめる理由：

- 統合ブランチでは、リリースの設定（`tools/release/**`・`tests/release/**`）を変えられません（ownership の `release_only`）。
- そのため、統合ブランチ上のファイル一式はリリース候補ではなく、製品ファイルを1つでも変えた PR は、内容に関係なく Release gate で失敗していました（PR #75）。

送り先の判定：

- PR は `GITHUB_EVENT_NAME` と `GITHUB_BASE_REF`、push は `GITHUB_REF_NAME` で判定します。どちらも GitHub Actions が設定する値です。
- 名前は `integrate/` で始まるブランチ名と完全に一致する必要があります。`..` を含むもの、`.` で始まる区切りがあるもの、空の区切りがあるものは対象外です。

分岐点を使うのは、次の条件をすべて満たすときだけです。

1. 送り先が上の判定に合う。
2. 分岐点が取れる。取れないとき（`origin/main` が無い、共通の祖先が無い）は、**テストが失敗します**（fail-closed）。
3. `tools/release`・`tests/release` が分岐点と同じである。違うときは、この commit で厳格に確かめます。

判定した結果は、実行ログに1行で出ます。

```text
release self-test: mode=fork-point event='pull_request' target='integrate/web2' head=… fork-point=…
```

拒否側のテストは、今までどおりです。統合ブランチ向けのときは、さらに「分岐点のファイル一式に Core と theme の承認されていない変更を加えると失敗する」ことも確かめます（`test_integrate_mode_still_rejects_unapproved_package_changes`）。

**本番の保護は変わりません。**

- main へのすべての PR と push では、`release.yml` の Release gate が、そのファイル一式に対して実行されます。これは必須チェックです。
- 統合ブランチの変更がリリースとして正しいかは、統合ブランチを main に入れる PR の Release gate が、新しいリリース設定とあわせて確かめます。

判定の反例テストは `ReleaseSelftestTarget` にあり、使い捨てのリポジトリで動きます。

- main 向けの PR、ほかのイベント
- 不正な名前、環境変数の不足
- 分岐点が無い場合
- リリース設定が変わった場合
- 取り出すファイルが PR 側ではなく分岐点のものであること
- 判定が `error` のときに、Release gate を実行する前に失敗すること

### 最小権限

全 workflow で次のようにしています。

- 最上位は `permissions: {}`（`svg-motion.yml` は既存のまま `contents: read`）、各ジョブは `contents: read`
- `pull_request_target` は `ci-guard.yml` だけ。そこでは actions/checkout 以外の action と Secret を使わない
- checkout は `persist-credentials: false`
- action はすべて commit SHA で固定
- イベントの値は `env:` を通してスクリプトに渡す

`workflow_policy.py` がこれらを全 workflow について検査します。

## Web 2.0 のテスト（ci-2）

`tests.yml` の job `test`（必須チェック `Tests (PHP 8.1)`・`Tests (PHP 8.3)`）の最後のステップ「Web 2.0 tests」で、次の4つを実行します。チェック名も Ruleset も変わりません。

| テスト | 内容 |
|---|---|
| `tests/web2/hooks/run.php` | Web 2.0 ローダー単体（Core・テーマ） |
| `tests/web2/hooks/regression.php` | ローダーがあっても既存の機能が変わらないこと、モジュールのフォルダの安全条件 |
| `tests/glossary/run.php` | Knowledge Hub の Core 側 |
| `tests/glossary/theme.php` | Knowledge Hub のテーマ側 |

- **追加した理由:** hooks ローダー（#75・#81）と Knowledge Hub（#79）は integrate/web2 に入っていますが、これまで CI は Lint PHP しか実行していませんでした。公開スイッチと許可リストの安全条件を、PR ごとに PHP 8.1 と 8.3 で確かめるためです。
- **動き:**
  - 最初に tokenizer があることを確かめます。無ければ `::error::` を出して失敗します。
    - `run.php` などは `token_get_all` を直接呼ぶので、tokenizer が無ければそれ自体が失敗します。
    - ただし、ローダーは tokenizer の失敗を「読み込まない」理由として扱うので、無いまま進むと、テストによっては「全部拒否されて合格」に見えるおそれがあります（`regression.php` など）。原因をはっきりさせるため、最初に確かめます。
  - 4つを順に実行します。1つが失敗しても残りを実行し、最後にステップを失敗させます。
  - Node tests と同じく `if: ${{ !cancelled() }}` で、前のステップが失敗しても結果を出します（ジョブは失敗のまま）。
- **場所:** 既存の「PHP tests」の一覧には足していません。main が同じ箇所に `tests/seo/sitemap-ignore.php` を足していて、同期のときに衝突するためです。job の末尾に足すと、main と衝突しません。
- **登録しないもの:** `tests/glossary/validate-content.php` は、原稿のフォルダ（`content/glossary`）がまだ無いので登録しません（今は終了コード 2 になります）。

### 固定のしかた

`tools/ci/required-checks.json` の `workflows[".github/workflows/tests.yml"].must_run.test` に、次を足しました。

- 4つのテストのパス（部分一致。どれかのステップの `run` に含まれていること）
- `=` で始まる、このステップの **`run` のスクリプト全体**（前後の空白を除いて完全一致）

部分一致だけでは、`theme.php` を `theme.php.off` に変えても通るため、完全一致も足しました。

**完全一致が固定するのは `run` のスクリプトだけで、ステップ全体ではありません。** `workflow_policy.py` の `_runs()` は、ステップの `run` の文字列だけを比べます。ステップのほかのキーについては、次のとおりです。

| ステップのキー | 検査 |
|---|---|
| `run` | 完全一致（`=`）と部分一致で固定 |
| `if` | `allowed_step_if`（`${{ !cancelled() }}` だけ）以外は失敗 |
| `continue-on-error` | どのステップでも失敗 |
| `run` の中の `\|\| true`・`\|\| exit 0` | 必須のコマンドがあるステップでは失敗 |
| `run` の中の `\|\| :` | 後ろに英数字が続くときだけ失敗。行末・空白・`;` が続く `\|\| :` は **検出しない**（正規表現の `\b` のため。新しいステップは `=` で検出される） |
| job・workflow の `env`・`defaults`・`container`・`services` | 失敗 |
| job の `strategy.matrix`（チェック名が変わる場合） | 失敗 |
| ステップの `env`・`shell`・`working-directory`・`timeout-minutes` | **検査しない** |
| 前のステップが `$GITHUB_ENV`・`$GITHUB_PATH` に書くこと、テストのファイルや `php` を書き換えること | **検査しない** |
| 必須のコマンドが無いステップ（Check out・Set up PHP など）の `if`・`with`（`ini-values`、`php-version` の固定など）・`uses` の差し替え | **検査しない** |
| job の `runs-on`・`timeout-minutes` | **検査しない** |
| 同じスクリプトのステップを残したまま、別のステップを足すこと | **検査しない** |

ステップ全体（名前・`if`・`env`・`run`・`uses`・`with` …）を固定するには、Release gate と同じ `pinned_steps` を使います。ただし `pinned_steps` は job の全ステップを固定する仕組みで、1つのステップだけを固定することはできません。tests.yml に入れるかどうかは、下の「残るリスク」に書きました。

### 承認の手順

`.github/` と `tools/ci/` は保護パスなので、この変更の PR は CI change guard の承認が必要です。

1. CI change guard が、変更したファイルと digest を出して失敗します（`ci-change-reviewed: <digest>`）。
2. 運営者が差分を読みます。特に、tests.yml の新しいステップの全文と、`required-checks.json` の `=` の行が同じであることを確かめます。
3. 運営者が `ci-change-reviewed` のラベルを付け、説明文にその行を書きます。エージェントはラベルも行も付けません。
4. CI change guard が PASS し、必須の9件がそろったら、運営者が merge を判断します。

コミットを足すと digest が変わるので、手順2からやり直しです。

### この変更の残るリスク

- **ステップの `env`・`shell` と前のステップ:** 上の表の「検査しない」項目は、tests.yml の既存のステップすべてに共通する穴です。
  - 例：ステップに `env: BASH_ENV: <リポジトリのファイル>` を足すと、bash が最初にそのファイルを読むので、`php` を差し替えられます。前のステップが `$GITHUB_ENV` に `BASH_ENV` を書いても同じです。
  - tests.yml と `tools/ci/` は保護パスなので、こうした変更にも CI change guard の承認（人が差分を読むこと）が必要です。自動では「弱める変更」と判定されないので、レビューで見落とさないことが前提です。
  - 自動で検出するには、tests.yml の job `test` を `pinned_steps` で固定するか、`workflow_policy.py` を直して、必須のコマンドがあるジョブでステップの `env`・`shell` と `$GITHUB_ENV`・`$GITHUB_PATH` への書き込みを禁止します。どちらも別の PR（ci-3）で検討します。
  - `\|\| :` の検出漏れ（上の表）と、`run: >`（折りたたみ）を `\|` と同じに読むこと（実行すると構文エラーで失敗側に倒れる）も、ci-3 で直します。
  - `workflow_policy.py` の修正は、main に入るまで PR の CI change guard には効きません（guard は main の `tools/ci/` で判定するため）。
- **テストのファイル自体は保護パスではない:** `tests/web2/**`・`tests/glossary/**`・`tests/industry-hub/stub.php` は保護パスではありません。
  - たとえば `tests/glossary/run.php` を `<?php exit(0);` に変える PR は、承認なしで必須チェックを通ります。`must_run` が保証するのは「そのパスを実行すること」だけです。
  - 既存の `tests/nav` などのテストも同じです。テストの差分は、通常のコードレビューで確かめます。保護パスに入れるかどうかは後の PR で検討します（main の `required-checks.json` に入ってから有効になります）。
- **main と同期するまで:** PR の CI change guard は main の `required-checks.json` で判定します。main にはまだこの `must_run` が無いので、同期するまでは integrate/web2 でこのステップを外しても、「弱める変更」ではなく「CI ファイルの変更（要承認）」として扱われます。
  - CI policy は PR 側の `required-checks.json` で判定するので、ステップだけを外すと失敗します。
- **PHP 8.1・8.3:** 4つのテストを実際の 8.1・8.3 で実行するのは、この PR の CI が最初です。ローカルは PHP 8.5 だけで、静的には 8.2 以降の構文・関数を使っていないことを確かめました。

## 残るリスク

- **承認する人:** 共同作業者は1名で、AI エージェントも同じアカウントで操作します。ラベルと説明文の行は「運営者が読んだ」記録で、GitHub 上ではエージェントが付けたものと区別できません。エージェントは承認用のラベル・行を付けない、という運用ルールが前提です。
- **成りすましの同名チェック:** PR が新しい workflow で `CI change guard` と同じ名前のジョブを作ると、ガードは失敗しますが、同名の成功チェックも並びます。GitHub がどちらを採るかは未確認です。Ruleset で必須チェックの送信元を GitHub Actions に限っても、同じ Actions 同士は区別できません。
- **Ruleset が未設定の間:** 必須チェックは何も止めません。main への直接 push・force push もできます。
- **push 時の workflow:** push で走る workflow はその commit の版です。Ruleset で PR を必須にすれば、push は merge した PR だけになります。
- **既存の Release gate:** 承認済みのファイルの「中身」をさらに変えても通ります（変わったファイルの一覧だけを照合するため）。今回の範囲外で、変えていません。
- **GitHub 上で確かめること**（PR を作った後）
  - `pull_request_target` のチェック結果が PR の head に付き、必須チェックとして使えること。付かない場合は PR が「待機中」で止まる側に倒れます（素通りにはなりません）。
  - `edited`（送り先・説明文の変更）とラベルで再実行されること、古い結果が使われないこと
  - チェック名の表示
- **最初の PR:** main にまだ `ci-guard.yml` が無いので、この PR では CI change guard が走りません（`pull_request_target` は base の workflow を使うため）。差分は人が読んで確認します。
  - Release gate は、base に判定スクリプトが無いのですべてを検査します。
  - この PR を merge した直後の main への push では、CI change guard が「base に信頼できる規則が無い」として **失敗** します（fail-closed）。その commit だけの想定どおりの結果で、次の commit からは通常どおり判定します。

## PR #69（Design Library）との統合（2026-11-19 時点）

#69（head f61944c）は `release.yml` に「theme 1.30.0 の前回リリースのゲート」のステップを足し、Build ステップに plan の行を1行足しています。
隔離した一時クローンで、#68 → #69 の順に統合した状態を試しました。

- **merge:** 競合はありません。
- **#69 そのままの場合:** CI policy が失敗します（3件）。
  - 新しいステップに `if` が無い
  - 新しいステップが `pinned_steps` に無い
  - Build ステップに行が足されている
- **#69 側で必要な修正**（#69 の作業として行う。CI 側で検査を緩めて合わせることはしない）
  1. 新しいステップに `if: steps.scope.outputs.target == 'true'` を足す
  2. `python3 tools/ci/workflow_policy.py --print-steps .github/workflows/release.yml gate` の出力で、`tools/ci/required-checks.json` の `pinned_steps` の `.github/workflows/release.yml` → `gate` を置き換える
- **修正した後の確認結果:**
  - CI policy は PASS、CI テストは81件 OK
  - CI change guard の結果は、弱める変更0件と要レビュー2件です。`ci-change-reviewed` とダイジェストの承認で通ります。
  - Release gate は 30 passed、前回リリースのゲートは2本とも PASS
- **merge の順番:** #68 → #69 の順にします。#69 は #68 が入った main を取り込んでから、上の2点を足します。

## Ruleset（推奨。設定は運営者の別承認）

- **main:**
  - 削除と force push を禁止
  - PR を必須にする（承認は0件）
  - 上の表の必須チェック10件を GitHub Actions に限定
  - 最新の main を取り込んでいることを必須にする
  - merge commit のみ
  - バイパスなし
- **integrate/web2:**
  - 削除と force push を禁止
  - PR を必須にする
  - 上の表の必須チェック9件
- **ラベル:** `ci-change-reviewed` と `ci-weakening-approved` を作り、運営者だけが付けます。
- **既存のブランチ:** `integrate/p0-theme-1.27.1-into-main` はローカルだけのブランチで、Integration gate と integrate/web2 の Ruleset の対象外です（push すると Tests などの共通チェックと CI change guard だけが走ります）。
