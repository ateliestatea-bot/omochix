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

### CI policy

`ci-policy.yml`（`pull_request`）で、この commit 自身の一覧に対してポリシーを確認し、自己テスト `python3 -m unittest discover -s tests/ci` を実行します。PR のコードを実行するので、`pull_request_target` にはしません。PR 側で書き換えられるので、整合性の確認であって、変更を判定するのは CI change guard です。

### 最小権限

全 workflow で次のようにしています。

- 最上位は `permissions: {}`（`svg-motion.yml` は既存のまま `contents: read`）、各ジョブは `contents: read`
- `pull_request_target` は `ci-guard.yml` だけ。そこでは actions/checkout 以外の action と Secret を使わない
- checkout は `persist-credentials: false`
- action はすべて commit SHA で固定
- イベントの値は `env:` を通してスクリプトに渡す

`workflow_policy.py` がこれらを全 workflow について検査します。

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
