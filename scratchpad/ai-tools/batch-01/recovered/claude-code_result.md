## 1. Sources list

**Official product**
- https://claude.com/product/claude-code — Research date: 2026-09-30

**Pricing**
- https://claude.com/pricing — Research date: 2026-09-30

**Docs**
- https://code.claude.com/docs/en/overview — Research date: 2026-09-30
- https://code.claude.com/docs/ja/overview (Japanese-localized docs) — Research date: 2026-09-30
- https://code.claude.com/docs/en/model-config — Research date: 2026-09-30
- https://code.claude.com/docs/en/data-usage — Research date: 2026-09-30
- https://code.claude.com/docs/en/changelog — Research date: 2026-09-30

**API**
- https://code.claude.com/docs/en/agent-sdk/overview — Research date: 2026-09-30
- https://github.com/anthropics/claude-agent-sdk-typescript/blob/main/CHANGELOG.md — Research date: 2026-09-30
- https://github.com/anthropics/claude-agent-sdk-python/blob/main/CHANGELOG.md — Research date: 2026-09-30

**Help / Legal**
- https://www.anthropic.com/legal/commercial-terms — Research date: 2026-09-30
- https://trust.anthropic.com — Research date: 2026-09-30
- https://privacy.anthropic.com/ — Research date: 2026-09-30

## 2. JSON object

```json
{
  "slug": "claude-code",
  "short_description": "Anthropic製のエージェント型AIコーディングツール。ターミナルやIDEから自然言語で指示するだけで、コード実装・テスト・PR作成までを自律的に行う。",
  "post_content": "<h2>Claude Codeとは？</h2><p>Claude CodeはAnthropicが開発するエージェント型のAIコーディングツールです。ターミナル、VS CodeやJetBrainsなどのIDE拡張、デスクトップアプリ、Webブラウザ、モバイルアプリといった複数のインターフェースから利用でき、自然言語での指示をもとにコードベースの理解、実装、テスト実行、コミット・プルリクエスト作成までを一連の作業として自律的に行います。2025年2月に提供が開始されて以降、対応プラットフォームやモデル、周辺機能が継続的に拡張されています。</p><h2>Claude Codeでできること</h2><p>Claude Codeは、リポジトリ全体をagentic searchで横断的に把握したうえで、複数ファイルにまたがる機能追加・バグ修正・リファクタリングを計画から実装、検証まで自律的に進めます。Gitとも直接連携しており、変更のステージング、コミットメッセージの作成、ブランチ作成、プルリクエストの作成までを担わせることができます。</p><h3>主要機能</h3><ul><li>自然言語での指示からコードを計画・実装・テストまで一貫して実行</li><li>agentic searchによるコードベース全体の横断的な理解</li><li>Git/GitHub・GitLabとの連携によるコミット作成・PR作成・CI連携</li><li>Model Context Protocol（MCP）による外部ツール・データソース連携</li><li>CLAUDE.md・スキル・フックによるプロジェクト固有のカスタマイズ</li><li>サブエージェントや並列セッションによる大規模タスクの分担処理</li><li>Agent SDK（Python/TypeScript）によるカスタムエージェント構築</li></ul><h2>日本語で使える？</h2><p>Claude Codeの公式ドキュメントは日本語版（code.claude.com/docs/ja/）が用意されており、インストール手順や主要機能の説明を日本語で確認できます。対話面では、Claude自体が多言語対応のモデルであるため、日本語でプロンプトを入力すれば日本語で応答が返ってきます。一方で、CLIのメニュー表示やエラーメッセージなど、ツールの表示面は英語が基本であり、ツール全体が完全に日本語化されているわけではありません。そのためOmochiXでは日本語対応を「一部対応」として扱っています。</p><h2>Claude Codeの料金</h2><p>Claude CodeはAnthropicの有料プランに含まれる形で提供されており、無料プランでは利用できません（2026年9月時点、claude.com/pricing確認）。個人向けにはPro・Maxプラン、チーム向けにはTeamプラン、大規模組織向けにはEnterpriseプランが用意されています。</p><h3>各プラン</h3><ul><li>Pro：月額20ドル（年払いの場合は月額17ドル相当）。Claude Codeを含む基本的な利用枠</li><li>Max 5x：月額100ドル。Proの5倍の利用量</li><li>Max 20x：月額200ドル。長時間・大量利用向けの上位プラン</li><li>Team Standard：1シートあたり月額25ドル（年払いは月額20ドル）</li><li>Team Premium：1シートあたり月額125ドル（年払いは月額100ドル）</li><li>Enterprise：SSO・SCIM・監査ログなどを備えたカスタム契約。料金は個別見積もりで、利用量に応じた課金も選択可能</li></ul><p>Anthropic ConsoleのAPIキーを使って従量課金（トークン単価制）で利用することも可能です。単価はモデルにより異なります。正確な最新料金はclaude.com/pricingで随時確認することを推奨します。</p><h2>Claude Codeの使い方</h2><ol><li>ターミナルで<code>curl -fsSL https://claude.ai/install.sh | bash</code>（macOS/Linux/WSL）、またはWindowsの場合は対応するPowerShell/CMDコマンドを実行してインストールする</li><li>新しいターミナルを開き、<code>claude --version</code>でインストールを確認する</li><li>作業したいプロジェクトのディレクトリに移動し、<code>claude</code>コマンドで起動する</li><li>初回起動時にClaudeアカウント（Pro/Max/Teamなどのサブスクリプション）またはAnthropic APIキーでログインする</li><li>ターミナル上で自然言語で指示を入力する（例：「認証モジュールのテストを書いて実行し、失敗があれば修正して」）</li><li>必要に応じてVS Code拡張、JetBrainsプラグイン、デスクトップアプリ、Webブラウザ（claude.ai/code）など、用途に合わせたインターフェースに切り替える</li></ol><h2>Claude Codeの活用例</h2><ul><li>未テストのコードへのテスト追加やlintエラーの一括修正</li><li>大規模リポジトリの調査・仕様把握・ドキュメント化</li><li>バグ報告からの原因特定と修正パッチ作成</li><li>GitHub/GitLab上でのプルリクエスト作成・コードレビュー自動化</li><li>依存関係のアップデートやマイグレーション作業</li><li>CI/CDパイプラインでの定期的なコードチェックやリリースノート作成</li></ul><h2>Claude Codeのメリット</h2><ul><li>ターミナルを離れずにコード実装からPR作成まで一気通貫で任せられる</li><li>コードベース全体を横断的に理解するため、複数ファイルにまたがる変更に強い</li><li>ターミナル、IDE、デスクトップアプリ、Web、モバイルなど利用シーンに応じたインターフェースを選べる</li><li>MCPやAgent SDKによる拡張性が高く、外部ツール連携や独自エージェント構築が可能</li></ul><h2>Claude Codeの注意点</h2><ul><li>無料プランでは利用できず、Pro以上の有料プランかAPI課金が前提となる</li><li>Claude.aiでのチャット利用とClaude Codeの利用量は共通の上限を消費するため、ヘビーユーザーはプラン選定に注意が必要</li><li>CLIベースのツールであり、ターミナル操作に不慣れな場合は習熟に時間がかかる</li><li>ツール自体のUIやCLI表示は英語が基本であり、完全な日本語UIではない</li></ul><h2>どんな人におすすめ？</h2><p>日常的にコーディングを行うソフトウェアエンジニアや、大規模リポジトリの保守・リファクタリングに取り組む開発チーム、GitHub/GitLabのワークフローにAIレビューを組み込みたい組織、独自のAIコーディングエージェントをAgent SDKで構築したい開発者に向いています。一方、プログラミング未経験でノーコード的にアプリを作りたい人や、無料で使えるツールを探している人には不向きです。</p><h2>FAQ</h2><p><strong>Q. Claude Codeは無料で使えますか？</strong></p><p>A. 無料プランには含まれておらず、利用にはPro以上の有料プランまたはAPI課金が必要です（2026年9月時点）。</p><p><strong>Q. 日本語で指示を出せますか？</strong></p><p>A. 日本語での指示・応答は可能です。ただし公式ドキュメント以外のCLI表示は英語が基本です。</p><p><strong>Q. どのIDEに対応していますか？</strong></p><p>A. VS Code（Cursorを含む）、JetBrains系IDE（IntelliJ IDEA、PyCharm、WebStormなど）の拡張機能・プラグインに対応しているほか、ターミナル、デスクトップアプリ、Webブラウザからも利用できます。</p><p><strong>Q. Claude Code自体にAPIはありますか？</strong></p><p>A. Claude Code自体を組み込める「Agent SDK」（Python/TypeScript）が提供されており、Claude Codeと同じツール・エージェントループを自社アプリケーションに組み込むことができます。</p>",
  "key_features": [
    "エージェント型のコード実装：自然言語の指示からコードの計画・実装・テスト実行までを自律的に行う",
    "コードベース全体の理解：agentic searchでリポジトリ全体を横断的に把握し、複数ファイルにまたがる変更に対応",
    "Git/GitHub・GitLab連携：変更のステージング、コミットメッセージ作成、ブランチ作成、PR作成を自動化",
    "マルチプラットフォーム対応：ターミナル、VS Code/JetBrains拡張、デスクトップアプリ、Web、モバイルから利用可能",
    "MCP対応：Model Context Protocolにより外部ツール・データソースと連携",
    "CLAUDE.md・スキル・フックによるカスタマイズ：プロジェクト固有のルールや反復ワークフローを設定・共有可能",
    "サブエージェント・並列実行：複数のエージェントを同時に走らせて大規模タスクを分担処理",
    "Agent SDK提供：Claude Codeと同じツール・エージェントループをPython/TypeScriptのライブラリとして自社アプリに組み込み可能"
  ],
  "pros": [
    "ターミナルから離れずにコード実装からPR作成まで一気通貫で行える",
    "リポジトリ全体を横断的に理解し、大規模なリファクタリングや複数ファイルにまたがる修正に強い",
    "VS Code、JetBrains、Web、モバイルなど利用シーンに応じて複数のインターフェースを選べる",
    "MCPやAgent SDKにより外部ツール連携や独自エージェント構築など拡張性が高い",
    "GitHub Actions・GitLab CI/CDと連携し、PRレビューや課題対応を自動化できる"
  ],
  "cons": [
    "無料プランでは利用できず、有料のPro以上のプランかAPI課金が必須",
    "CLIベースのツールのため、ターミナル操作に不慣れなユーザーには学習コストがある",
    "Claude.aiでのチャット利用と共通の利用上限を消費するため、ヘビーユーザーはMaxプランへの移行が必要になりやすい",
    "CLI/ツール自体の表示は英語が中心で、完全な日本語UIではない"
  ],
  "strengths": [
    "長時間・自律的なマルチファイル編集とGit/GitHub連携の深さ",
    "ターミナル・IDE・デスクトップ・Web・モバイルを横断した一貫した利用体験",
    "Agent SDKによる独自エージェント開発への拡張性"
  ],
  "weaknesses": [
    "無料プランがなく個人の試用ハードルがやや高い",
    "CLI操作に不慣れな非エンジニアには扱いにくい",
    "料金体系がプラン・API課金・チーム利用量で分岐しており分かりにくい"
  ],
  "recommended_for": [
    "日常的にコーディングを行うソフトウェアエンジニア・開発チーム",
    "大規模リポジトリのリファクタリングや技術的負債解消に取り組みたいチーム",
    "CI/CDやGitHub/GitLabワークフローにAIレビューを組み込みたい開発組織",
    "独自のAIコーディングエージェントをAgent SDKで構築したい開発者"
  ],
  "recommended_use_cases": [
    "バグ修正",
    "大規模リファクタリング",
    "テストコード作成",
    "コードレビュー",
    "プルリクエスト作成",
    "依存関係・マイグレーション対応",
    "CI/CD自動化"
  ],
  "not_recommended_for": [
    "プログラミング未経験でノーコード的にアプリを作りたい人",
    "無料で使えるAIコーディングツールを探している個人",
    "ターミナル/CLI操作に抵抗があるユーザー"
  ],
  "pricing_details": "Claude Codeは無料プランに含まれず、有料プラン経由でのみ利用可能（2026年9月30日時点、claude.com/pricing確認）。個人向け：Pro月額20ドル（年払いだと月額17ドル相当）、Max 5x月額100ドル、Max 20x月額200ドル。チーム向け：Team Standardは1シートあたり月額25ドル（年払い月額20ドル）、Team Premiumは1シートあたり月額125ドル（年払い月額100ドル）。Enterpriseはカスタム契約・個別見積もりで、利用量に応じた従量課金も選択可能。これとは別に、Anthropic Consoleを通じてAPIキーで従量課金（トークン単価制）で利用する方法もあり、単価はモデルごとに異なる。正確な最新料金はclaude.com/pricingで随時確認すること。Claude.aiでのチャット利用とClaude Codeの利用量は同一の上限を共有する。",
  "api_sdk_info": "Claude Code自体はCLIバイナリだが、その内部で使われているエージェントループ・ツール（ファイル読み書き、コマンド実行、権限管理、セッション管理、サブエージェント、MCP、Skills/Hooks等）をライブラリとして自社アプリに組み込める「Agent SDK」（Python / TypeScript）が公式に提供されている（code.claude.com/docs/en/agent-sdk/overview）。認証はAPIキー方式を使用し、claude.aiログインやレート制限を第三者製品に組み込むことは原則許可されていない。利用はAnthropicのCommercial Terms of Serviceに準拠する。ブランドガイドラインにより、Agent SDKで構築した製品を「Claude Code」と名乗ることは不可。TypeScript/Python各SDKの変更履歴はGitHubで公開されている。",
  "security_info": "Claude Codeはローカルで動作し、LLMとの通信はTLS 1.2以上で暗号化される。データ保持方針はアカウント種別で異なり、個人向け（Free/Pro/Max）はモデル改善へのデータ利用可否に応じて5年間または30日間の保持、商用向け（Team/Enterprise/API）は標準30日間保持で、適格な組織向けにゼロデータ保持（Zero Data Retention）オプションも用意されている。商用契約下ではコードやプロンプトはデフォルトでモデル学習に使用されないが、個人向けプランではモデル改善へのデータ利用設定がオンの場合に学習利用される。詳細なAPIセキュリティ管理・コンプライアンス資料はAnthropic Trust Center（trust.anthropic.com）で公開されている。",
  "notes": "本ページの内容は2026年9月30日時点の公式情報（claude.com/product/claude-code、claude.com/pricing、code.claude.com/docs）に基づく。料金・モデル・機能は変更頻度が高いため、掲載後も定期的な再確認を推奨する。",
  "supported_devices": [
    "macOS",
    "Linux",
    "Windows（ネイティブ／WSL）",
    "VS Code拡張機能（Cursor含む）",
    "JetBrains IDE（IntelliJ IDEA、PyCharm、WebStormほか）",
    "デスクトップアプリ（macOS、Windows x64/ARM64、Linuxはベータ）",
    "Webブラウザ（claude.ai/code）",
    "iOS/Androidアプリ"
  ],
  "supported_models": [
    "Claude Opus系（既定モデル、複雑な推論向け）",
    "Claude Sonnet系（日常的なコーディング向け）",
    "Claude Haiku系（高速・軽量タスク向け）",
    "拡張コンテキスト（100万トークン）対応バリアント"
  ],
  "integrations": [
    "VS Code / Cursor",
    "JetBrains IDE",
    "ターミナル/CLI",
    "GitHub / GitHub Actions",
    "GitLab CI/CD",
    "Slack",
    "Model Context Protocol（MCP）経由の外部ツール連携",
    "Chrome（Web上のライブデバッグ）"
  ],
  "has_free_plan": false,
  "api_available": "yes",
  "commercial_use": "partial",
  "japanese_support": "partial",
  "info_checked_date": "2026-09-30",
  "omochix_view": "Claude CodeはAnthropicが自社モデルに最適化して開発するエージェント型コーディングツールで、ターミナルを起点にリポジトリ全体を横断した自律的な実装・修正・PR作成までを一貫して任せられる点が特徴です。エディタ統合型やコード補完型の他のAIコーディングツールと比べると、長時間の自律タスク遂行やGit/GitHub連携の深さ、Agent SDKによる拡張性に強みがあるとされ、大規模な保守・リファクタリングを任せたい開発チームと相性が良いと考えられます。一方で無料プランがなく、個人の試用ハードルはやや高めです。",
  "seo_title": "Claude Codeとは？料金・機能・使い方・日本語対応を解説｜OmochiX",
  "meta_description": "Claude Codeの料金プラン、主な機能、日本語対応状況、導入手順、活用例を公式情報にもとづきOmochiXが解説します。"
}
```

## 3. Gaps (unknown/omitted)

- Exact current API per-token pricing figures — omitted, told to consult claude.com/pricing directly instead.
- commercial_use set to "partial" not "yes" — Team/Enterprise/API commercial terms confirmed, but individual Pro/Max consumer-terms output-rights characterization not independently verified from primary text alone.
- Whether CLI/product UI itself (not just docs) is Japanese-localized — unconfirmed either way; kept japanese_support="partial" matching existing production value.
- Precise "Fast mode" pricing/availability — omitted, single-source only.
- SOC2/ISO/HIPAA certification specifics — not independently confirmed; security_info limited to data retention/encryption/training policy.
