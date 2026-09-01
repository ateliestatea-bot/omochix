# CLAUDE.md

Claude Code向けの開発ルールです。
更新日：2026年9月1日

OmochiXはCodexとClaude Codeの両方で開発します。両エージェント共通のルール（開発方針、Git運用、WordPress/Production制約、公開優先順位、AI間引継ぎフォーマット）は `@AGENTS.md` に定義されています。Claude Codeで作業する場合も **`@AGENTS.md`の内容をすべて遵守すること**。このファイルはClaude Code固有の補足事項のみを扱います。

@AGENTS.md

## このリポジトリについて

OmochiXはWordPress製のAIツール比較・AIニュースメディアです。`wp-theme/omochix-theme/`（テーマ）と`wp-plugin/omochix-core/`（プラグイン）を中心に開発します。ビルドツールはなく、素のPHP/CSS/JSです。詳細は `@AGENTS.md` および `docs/` 配下を参照してください。

## 作業開始時に必ず行うこと

1. `git branch --show-current` と `git status` を確認する。
2. `git log --oneline -5` で直近commitを確認する。
3. 未追跡・未コミットの変更があれば、それが自分（今回のセッション）の変更か、他エージェント/ユーザーによる進行中の作業かを確認してから扱う。

## Claude Code固有の運用

- 破壊的操作（`git reset --hard`、`git clean -fd`、force push、本番DB変更など）は `@AGENTS.md` により禁止・要許可。権限モードに関わらず、実行前に必ずユーザーに確認する。
- commit・pushはユーザーが明示的に指示した場合のみ行う。指示がない限り、変更提案・レビューのみに留める。
- `main`ブランチへのmergeはユーザーの明示的な許可がある場合のみ行う。
- Secret・Password・API Key・本番URL等を出力やファイルに書き出さない（`docs/Beta-Deployment-Runbook.md`の方針と同一）。
- `wp-theme/omochix-theme/style.css` など大規模ファイルを編集する際は、既存のデザイントークン・命名規則・構造を踏襲し、`docs/CSS-Debt-Audit.md`が公開前は非対象とした分類C（削除候補）の削除を単独判断で行わない。
- タスクが複数ステップにわたる場合はTodoリストで進捗を可視化し、完了ごとに更新する。

## セッション終了時の報告フォーマット

`@AGENTS.md`の「AI間引継ぎ」に従い、以下を報告する。

1. 現在のbranch
2. `git status`
3. 直近commit
4. 今回変更したファイル一覧
5. 完了した内容
6. 未完了の内容
7. 次にやること
8. 注意事項（他エージェント・人間が引き継ぐ上でのリスク・前提）
