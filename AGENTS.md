# AGENTS.md

OmochiX開発に関わるすべてのAIエージェント（Codex、Claude Code、その他）向けの共通ルールです。
更新日：2026年9月1日

このファイルと`docs/`配下の既存文書が矛盾する場合は、`docs/Pre-Launch-Backlog.md`・`docs/Beta-Release-Checklist.md`・`docs/Beta-Deployment-Runbook.md`など詳細文書を正とします。本ファイルはそれらの要約と、エージェント運用に特化した追加ルールです。

## プロジェクト概要

- OmochiXはWordPressで構築するAIツール比較・AIニュースメディアです。
- `wp-theme/omochix-theme/`：公開用カスタムテーマ（PHP / CSS / 最小限のJS、ビルドツールなし）
- `wp-plugin/omochix-core/`：AIツールのデータモデル・管理画面・Schemaを提供するカスタムプラグイン
- `docs/`：設計書・公開前Backlog・チェックリスト・デプロイ手順・コンテンツ運用文書
- SEO/構造化データはSlim SEO（外部プラグイン）とOmochiX Core Pluginで役割分担している（`SoftwareApplication`はCore Pluginのみが出力）。

## 現在地

- 開発ブランチ：`feature/home-mvp`
- フェーズ：β公開前。テーマ・プラグインの実装自体はβ品質でほぼ完了しており、現在は公開判定のためのコンテンツ・法務・ブランド素材の確定待ち。
- 現在地の詳細と最新の未完了項目は必ず `docs/Pre-Launch-Backlog.md` と `docs/Beta-Release-Checklist.md` を参照して確認すること。これらは随時更新されるため、本ファイルには個別タスクの状態を書き写さない。

## 開発方針

- Build → Use → Measure → Fix の短サイクルを優先する。
- 完璧な設計より実運用開始（β公開）を優先する。
- 実運用を妨げるBlocker（`Pre-Launch-Backlog.md`のP0項目）を最優先で解消する。
- 不要な大規模リファクタリングは行わない。
- 公開を妨げない技術的負債（例：CSSの重複定義、`docs/CSS-Debt-Audit.md`参照）は後回しにする。分類C（削除候補）は視覚回帰確認なしに削除しない。

## Git運用ルール

- 作業開始時に必ず `git branch --show-current` と `git status` を確認する。
- 他のAIエージェントや人間による未コミットの変更を勝手に削除・上書き・破棄しない。未追跡ファイルや変更を見つけたら、まず誰の作業かを確認する。
- 次のコマンドは禁止：`git reset --hard`、`git clean -fd`（または `-f`）、`git push --force`（`--force-with-lease`を含む）。
- ユーザーの明示的な許可なく `main` へマージしない。
- ユーザーの明示的な許可なく `push` しない。
- commitする前に、変更内容をユーザーに要約して提示する。
- 自分が行った変更と、既存の変更（他エージェント・ユーザーによるもの）を明確に区別して報告する。

## WordPress / Production ルール

- Production DB（本番データベース）を勝手に変更しない。
- 本番コンテンツ（投稿・固定ページ・設定）を勝手に公開しない。
- Privacy Policy / Terms / 会社情報 / 所在地 / 対応時間など、法務・運営に関わる情報を推測して作成・補完しない。運営者による正式な確認・承認が必要（`Beta-Deployment-Runbook.md`と同方針）。
- Secret、Password、API Key、Token、SMTP情報等をコードやドキュメントに記載しない。
- 外部サービス（Search Console、SNS、ホスティング、Slim SEOなどの設定）を勝手に変更しない。

## OmochiX固有ルール

- `docs/Pre-Launch-Backlog.md` を公開前優先順位の基準とする。優先度は P0 > P1 > P2。
- 現在はβ公開・実運用開始を最優先とする。
- CSS負債整理（`docs/CSS-Debt-Audit.md`）など公開を妨げない作業は後回しにする。
- AIニュース記事は `docs/AI-News-Publishing-Template.md` の基準に従う：架空ニュース禁止、出典URLと最終確認日を必須とする。
- AIツールデータ・AIニュース・SNS運用など、実運用に直結する作業を優先する。

## AI間引継ぎ（Codex ⇄ Claude Code）

異なるAIエージェントに引き継ぐ際は、必ず以下を確認・報告する。

### セッション開始時に確認する

1. 現在のbranch（`git branch --show-current`）
2. `git status`
3. 直近のcommit（`git log --oneline -5`）
4. 前セッションからの引継ぎ内容（会話や直前のcommitメッセージから確認）

### セッション終了時・区切りごとに報告する

1. 現在のbranch
2. `git status`
3. 直近のcommit
4. 今回変更したファイル一覧
5. 完了した内容
6. 未完了の内容
7. 次にやること
8. 注意事項（引き継ぐ上でのリスク・前提・未確定事項）

## 参照ドキュメント

- `docs/Pre-Launch-Backlog.md` — 公開前優先順位（P0/P1/P2）
- `docs/Beta-Release-Checklist.md` — β公開チェックリスト
- `docs/Beta-Deployment-Runbook.md` — 本番デプロイ手順・Rollback
- `docs/CSS-Debt-Audit.md` — CSS技術的負債の分類と整理方針
- `docs/AI-News-Publishing-Template.md` — AIニュース公開基準
- `docs/OmochiX-Design-System-v1.1.pdf` — デザインシステム
- `docs/OmochiX-Development-Handbook-v1.0.pdf` — 開発ハンドブック
