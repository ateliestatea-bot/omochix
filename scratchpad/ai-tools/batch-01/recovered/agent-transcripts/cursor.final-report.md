## 1. Sources list

**Official product / Pricing**
- https://cursor.com/pricing — Research date: 2026-09-30
- https://cursor.com/features — Research date: 2026-09-30 (fetch blocked by tool permission; not directly verified)

**Docs**
- https://docs.cursor.com/advanced/models — Research date: 2026-09-30
- https://cursor.com/docs/models — Research date: 2026-09-30
- https://cursor.com/docs/sdk/python (referenced via search cache; direct fetch 404'd) — Research date: 2026-09-30
- https://cursor.com/help/getting-started/install — Research date: 2026-09-30
- https://cursor.com/download — Research date: 2026-09-30

**API**
- https://cursor.com/changelog/sdk-release — Research date: 2026-09-30
- https://forum.cursor.com/t/introducing-the-cursor-python-sdk/161367 (official Cursor company forum) — Research date: 2026-09-30
- https://forum.cursor.com/t/cursor-sdk-cloud-agents-api-updates/159284 — Research date: 2026-09-30

**Help**
- https://cursor.com/help/ai-features/background-agents.md (fetch 404'd; content inferred from search snippet only, lower confidence) — Research date: 2026-09-30
- https://docs.cursor.com/bugbot/faq — Research date: 2026-09-30
- https://cursor.com/security — Research date: 2026-09-30
- https://cursor.com/terms-of-service — Research date: 2026-09-30

**Release notes**
- https://cursor.com/changelog (general changelog index, version cadence confirmed via search of official changelog pages) — Research date: 2026-09-30

**Forum (official Cursor company forum, used only to cross-verify Pro+/Ultra exact pricing which the pricing page's dynamic toggle could not be scraped directly)**
- https://forum.cursor.com/t/ultra-plan-v-s-200-pay-as-you-go/144918 — Research date: 2026-09-30
- https://forum.cursor.com/t/feature-request-native-japanese-ui-localization/170398 — Research date: 2026-09-30
- https://forum.cursor.com/t/bug-japanese-language-pack-only-translates-the-run-menu-in-cursor-3-11-13/165485 — Research date: 2026-09-30

---

## 2. JSON

```json
{
  "slug": "cursor",
  "short_description": "VS Codeをベースに、コードベース全体を理解するAIチャット・自動補完・複数ファイル編集（Agentモード）を一つのエディターで行える開発者向けAIコーディングツール。",
  "post_content": "<h2>Cursorとは？</h2>\n<p>Cursorは、Anysphere, Inc.が開発するAIコードエディターです。Microsoftの「Visual Studio Code」をベースに構築されており、既存の拡張機能や設定、キーバインドをそのまま引き継ぎながら、コードベース全体を理解するAIチャットや自律的なコード編集機能を利用できます。エディター単体の補完ツールではなく、複数ファイルにまたがる実装やクラウド上でのタスク実行まで対応する、開発ワークフロー全体を支援するツールとして位置づけられています。</p>\n\n<h2>Cursorでできること</h2>\n<p>Cursorの中心機能は、コードベース全体の文脈を踏まえて質問に答えるAIチャット、次に書くコードを予測して複数行をまとめて提案するTab（コード補完）、選択範囲を指示だけで書き換えるインライン編集（Cmd/Ctrl+K）、そして複数ファイルにまたがる実装・修正を自律的に進めるAgentモードです。さらに、長時間かかるタスクをクラウドの仮想マシン上で実行するBackground Agent、GitHubのプルリクエストを自動レビューするBugbot、外部イベントをトリガーにエージェントを起動するAutomationsなど、エディターの外側で動く自動化機能も用意されています。モデルはAnthropic（Claude系）、OpenAI（GPT系）、Google（Gemini系）、xAI（Grok系）、Anysphere自社製のComposerなど複数プロバイダーから選択でき、用途に応じて切り替えて使う設計です。</p>\n\n<h2>日本語で使える？</h2>\n<p>Cursor自体はAIチャットやAgentモードへ日本語でプロンプトを入力すること自体は可能ですが、エディターのUI（設定画面、アカウント画面、Agent/Chat/Composerの操作パネルなど）はCursor独自部分については英語表記のままです。VS Code本体部分についてはマーケットプレイスの日本語言語パック拡張機能を導入し、コマンドパレットから表示言語を切り替えることで一部翻訳できますが、Cursorが追加した機能のUIは対象外です。公式フォーラムでも日本語を含む多言語UI対応は要望として挙がっているものの、2026年9月時点で提供時期は明言されていません。そのため、日本語対応は「部分的」（入力・出力の日本語は可能、UIは英語中心）と理解しておくのが実態に近いといえます。</p>\n\n<h2>Cursorの料金</h2>\n<p>個人向けにはHobby（無料）、Pro、Pro+、Ultraの4段階、チーム向けにはTeams（Standard/Premium）、組織向けにはEnterpriseが用意されています。年払いを選ぶと月払いに比べて割引が適用されます。価格は税別で表示されています。</p>\n<h3>各プラン</h3>\n<ul>\n<li>Hobby：無料。クレジットカード登録不要。Agentの利用回数やTab補完に制限があり、お試し・学習用途向け。</li>\n<li>Pro：月額20ドル。Agent利用の上限が拡張され、フロンティアモデルへのアクセスやMCP・スキル・フック、クラウドエージェントなどが利用可能。</li>\n<li>Pro+：月額60ドル。Proよりも大きい利用枠が付与され、Agentを日常的に使う開発者向け。</li>\n<li>Ultra：月額200ドル。Pro+よりもさらに大きい利用枠と、新機能・新モデルへの優先アクセスが付与される。</li>\n<li>Teams：1ユーザーあたり月額40ドル（Standard/Premium）。一元請求、チーム共有のクラウドエージェント、使用状況分析、チーム単位のプライバシー設定などを含む。</li>\n<li>Enterprise：要問い合わせのカスタム価格。プール利用枠、請求書/PO対応、SCIMによる座席管理、リポジトリ・モデルのアクセス制御、監査ログなどを含む。</li>\n</ul>\n<p>Pro以上の各プランには一定量のモデル利用枠（クレジット）が含まれ、それを超えた利用分は従量課金となります。Pro+・Ultraの利用枠は、公式コミュニティフォーラムなどの情報ではそれぞれ70ドル相当・400ドル相当のAPIエージェント利用枠とされていますが、正式な金額は変更される可能性があるため、契約前に公式サイトの最新表示を確認することを推奨します。</p>\n\n<h2>Cursorの使い方</h2>\n<ol>\n<li>cursor.com/downloadから、利用しているOS（macOS/Windows/Linux）に対応したインストーラーをダウンロードする。</li>\n<li>インストール後にCursorを起動し、アカウントを作成またはログインする。</li>\n<li>VS Codeからの移行者は、設定・拡張機能・キーバインドのインポートを選択できる（案内が表示される場合がある）。</li>\n<li>プロジェクトフォルダーを開き、右側または下部のAIチャットパネルからコードベースに関する質問を入力する。</li>\n<li>Cmd/Ctrl+Kで選択範囲を指示だけで書き換える、またはAgentモードで複数ファイルにまたがる実装を依頼する。</li>\n<li>利用するAIモデル（Claude、GPT、Gemini、Grok、Composerなど）を設定から選択・切り替える。</li>\n<li>必要に応じてBackground AgentやBugbot、MCP連携などの自動化機能を有効化する。</li>\n</ol>\n\n<h2>Cursorの活用例</h2>\n<p>Cursorは、既存コードベースの仕様把握、複数ファイルにまたがる機能追加やリファクタリング、バグの原因調査と修正、プルリクエストのレビュー支援、定型的な保守タスクのクラウド自動化など、幅広い開発工程で利用されています。特にAgentモードやBackground Agentは、指示内容を明確に与えることで、実装からテスト実行までの一連の作業をある程度自律的に進められる点が特徴です。</p>\n\n<h2>Cursorのメリット</h2>\n<ul>\n<li>既存コードベースを踏まえた提案の精度が高く、大規模なコードでも文脈を保ったやり取りがしやすい。</li>\n<li>VS Codeの拡張機能・設定資産をそのまま利用できるため、移行コストが低い。</li>\n<li>無料のHobbyプランで基本機能を試用できる。</li>\n<li>Claude・GPT・Geminiなど複数の最先端モデルを1つのエディター内で使い分けられる。</li>\n<li>Background AgentやAutomationsにより、定型タスクをクラウド上で自動化できる。</li>\n</ul>\n\n<h2>Cursorの注意点</h2>\n<ul>\n<li>本格的に使い込むほど有料プランが前提になりやすく、上位プラン（Ultra）は月額200ドルに達する。</li>\n<li>UIは日本語に完全対応しておらず、Cursor独自部分は英語表記が中心となる。</li>\n<li>Pro以上のプランは利用量に応じた従量課金（クレジット消費）を伴うため、利用量が多いとコストの見通しが立てにくい場合がある。</li>\n</ul>\n\n<h2>どんな人におすすめ？</h2>\n<p>既にVS Codeで開発しており、設定や拡張機能を引き継いだままAI機能を追加したいエンジニアや、複数のAIモデルを比較・使い分けたい開発者、コードレビューや定型作業の自動化を試したいチームに向いています。一方で、日本語UIを必須とする人や、無料の範囲で高頻度のAgent利用を続けたい人には制約が大きく感じられる可能性があります。</p>\n\n<h2>FAQ</h2>\n<p><strong>Q. Cursorは無料で使えますか。</strong></p>\n<p>A. Hobbyプランであればクレジットカード登録なしで利用を開始できますが、Agentの利用回数やTab補完には制限があります。継続的に本格利用する場合はPro以降の有料プランが前提になりやすいです。</p>\n<p><strong>Q. VS Codeの拡張機能はそのまま使えますか。</strong></p>\n<p>A. CursorはVS Codeをベースにしているため、多くの拡張機能や設定、キーバインドを引き継いで利用できます。</p>\n<p><strong>Q. 商用利用はできますか。</strong></p>\n<p>A. 公式の利用規約上、ユーザーはCursorで生成したコードの権利を保持し、プラン種別による商用利用の制限は明記されていません。ただし、Cursor自体を第三者に再販する行為や、出力を競合モデルの学習に利用する行為などは禁止されています。詳細は利用前に公式のTerms of Serviceを確認してください。</p>\n<p><strong>Q. APIやSDKは提供されていますか。</strong></p>\n<p>A. Cursor SDK（TypeScript／Python）とCloud Agents APIが公式に提供されており、Cursorのエージェント機能を外部のコードから呼び出すことができます。エンドユーザー向けの汎用チャットAPIというより、開発者がCursorのエージェント基盤を自分のシステムに組み込むための機能です。</p>",
  "key_features": [
    "Agentモード：複数ファイルにまたがる実装・修正をAIが自律的に実行",
    "Tab（コード補完）：次の編集内容を予測し複数行をまとめて提案",
    "AIチャット：コードベース全体の文脈を踏まえた質問応答・コード生成",
    "インライン編集（Cmd/Ctrl+K）：選択範囲を指示だけで直接書き換え",
    "複数AIモデル選択：Claude・GPT・Gemini・Grok・Cursor製Composerなどを切り替え可能",
    "Background Agent：長時間タスクをクラウドVM上で自律実行",
    "Bugbot：GitHub上のプルリクエストを自動レビューし品質課題を指摘",
    "MCP対応：Model Context Protocolで外部ツール・データソースと連携"
  ],
  "pros": [
    "既存コードベースを踏まえた提案精度が高い",
    "VS Codeの拡張機能・設定資産をそのまま利用できる",
    "無料のHobbyプランで試用できる",
    "Claude・GPT・Geminiなど複数の最先端モデルを1つのエディターで使い分けられる",
    "Background AgentやAutomationsにより定型タスクをクラウド上で自動化できる"
  ],
  "cons": [
    "本格利用は有料プラン（Pro以降）が前提になりやすく、上位プランは月額200ドルに達する",
    "UIは日本語に完全対応していない（VS Code部分のみ言語パックで一部翻訳可、Cursor独自UIは英語のまま）",
    "利用量に応じた従量課金（クレジット消費）があり、コストの見通しが立てにくい場合がある"
  ],
  "strengths": [
    "VS Codeとの高い互換性により既存の開発環境からの移行コストが低い",
    "Agent／Background Agent／Automationsなど自律型AI機能の幅が広い",
    "複数のフロンティアモデルを比較しながら使える柔軟性"
  ],
  "weaknesses": [
    "日本語UIが未整備で英語表記に慣れる必要がある",
    "料金体系がクレジット消費ベースで分かりにくい",
    "SSO/SCIMなどチーム管理機能はTeams/Enterpriseプラン以降"
  ],
  "recommended_for": [
    "既にVS Codeで開発しているエンジニア・チーム",
    "複数のAIモデルを使い分けたい開発者",
    "コードレビューや定型的な修正作業を自動化したいチーム"
  ],
  "recommended_use_cases": [
    "コードベース全体を踏まえた質問応答",
    "複数ファイルにまたがる実装・リファクタリング",
    "コード補完・入力支援",
    "プルリクエストの自動レビュー（Bugbot）",
    "定型タスクのクラウド自動化（Background Agent/Automations）",
    "バグ修正・デバッグ支援"
  ],
  "not_recommended_for": [
    "完全な日本語UIを必須とするユーザー",
    "IDE操作に不慣れで学習コストをかけたくない人",
    "無料の範囲で高頻度のAgent利用をしたい人（Hobbyプランは利用制限あり）"
  ],
  "pricing_details": "Individual向けにHobby（無料）、Pro（月20ドル）、Pro+（月60ドル）、Ultra（月200ドル）の4段階、Teams（1ユーザーあたり月40ドル、Standard/Premium）、Enterprise（要問い合わせ）を提供。年払いで割引あり、価格は税別。Pro以上には一定のモデル利用枠が含まれ、超過分は従量課金。Pro+/Ultraの利用枠（70ドル相当・400ドル相当とされる）は公式コミュニティフォーラム等での言及に基づく参考値であり、公式価格ページの動的表示から直接確認できていないため、契約前に最新情報の確認を推奨する。",
  "api_sdk_info": "Cursor SDK（TypeScript: npm install @cursor/sdk、Python: pip install cursor-sdk）を公式提供。Cursor IDE/CLI/Web版と同じランタイム・モデルをコードから呼び出し、エージェントをローカルまたはcrsrのクラウドVM上で実行できる。Cloud Agents API（v1）はSSEストリーミング・再接続・ライフサイクル管理（archive/unarchive/delete）に対応。課金はトークン消費ベースの従量制で、紐づくアカウントのプラン・利用枠の範囲で消費される。エンドユーザー向けの汎用チャットAPIではなく、Cursor自身のエージェント機能を外部システムに組み込むための開発者向けSDKという位置づけ。",
  "security_info": "AIUC-1、ISO/IEC 27001:2022、ISO/IEC 42001:2023の認証を取得し、SOC 2 Type IIのアテステーションも保有（証明書・レポートはtrust.cursor.comで申請により入手可能）。Privacy Mode（無料・Proを含む全プランで設定可能）を有効にすると、コードやチャット内容をモデル学習に利用しない。中国にインフラを置かず、中国拠点のサブプロセッサーも利用しない方針を明示。Enterprise向けにSSO/SCIM、監査ログ、CMEKによる暗号化、MDM配布などを提供し、年1回以上の第三者ペネトレーションテストを実施している。",
  "notes": "Cursorは価格・モデルラインナップの変動が激しい製品のため、掲載前にcursor.com/pricingとdocs.cursor.com/advanced/modelsの最新表示を再確認することを推奨する。Pro+/Ultraの具体的金額は公式ページの動的トグル表示を直接取得できず、公式フォーラム等の間接情報で相互確認した値である。具体的な選択可能モデルのバージョン名（Claude/GPT/Geminiの細かいモデル番号等）はWeb検索結果の要約に不自然な記載（実在しないモデル名を含む可能性）が見られたため、本レポートではプロバイダー単位の記載に留め、個別モデル名の掲載は見送った。",
  "supported_devices": [
    "macOS",
    "Windows",
    "Linux",
    "VS Codeベースのエディター（拡張機能・設定・キーバインドの引き継ぎに対応）"
  ],
  "integrations": [
    "GitHub（Bugbotによる自動PRレビュー、Automationsのトリガー）",
    "Slack（Automationsのトリガー：メッセージ・リアクション等）",
    "MCP（Model Context Protocol）による外部ツール・データソース連携",
    "VS Code拡張機能マーケットプレイスとの互換性"
  ],
  "has_free_plan": true,
  "api_available": "yes",
  "commercial_use": "yes",
  "japanese_support": "partial",
  "info_checked_date": "2026-09-30",
  "omochix_view": "CursorはVS Codeをベースにしたエディター型AIコーディングツールで、GitHub Copilotのような補完特化でもClaude Codeのようなエージェント特化でもなく、両者の中間に位置する。最大の強みは、既存のVS Code資産をほぼそのまま引き継げる移行のしやすさと、Claude・GPT・Geminiなど複数モデルを1つのエディター内で使い分けられる柔軟性にある。Background AgentやAutomationsによる自律実行機能も充実してきており、日常的な開発タスクの自動化を試したい層と相性が良い。一方で日本語UIは未整備で、本格運用には月額20〜200ドル台の有料プランが前提になりやすい点は考慮が必要である。",
  "seo_title": "Cursorとは？料金・機能・使い方・日本語対応を解説｜OmochiX",
  "meta_description": "Cursorの料金プラン（Hobby/Pro/Pro+/Ultra）、Agentモードなどの主要機能、日本語対応状況、導入手順をOmochiXが解説。VS Codeベースで移行しやすいAIコードエディターです。"
}
```

---

## 3. Could not confirm / omitted

- **Exact Pro+ ($60/mo → 70ドル相当) と Ultra ($200/mo → 400ドル相当) の利用枠金額**：公式pricingページはプラン切替がJS描画のため直接スクレイプできず、公式コミュニティフォーラムの投稿と複数の第三者情報の一致で間接確認したのみ。真の一次ソース（価格ページの静的テキスト）では未確認。
- **選択可能な個別モデルのバージョン名**（Claude/GPT/Geminiの具体的な型番リスト）：検索結果の要約に不自然・不整合な記載（存在が疑わしいモデル名を含む）が見られたため、プロバイダー単位（Anthropic/OpenAI/Google/xAI/Composer）にとどめ、`supported_models`キー自体を省略した。
- **cursor.com/features の直接取得**：WebFetchがツール権限で拒否されたため、機能詳細は他の公式ページ（changelog、docs、help）からの間接確認に依拠。
- **Background agents公式ヘルプページ（cursor.com/help/ai-features/background-agents.md）**：404で直接取得できず、検索結果のスニペットのみで補完。
- **Enterpriseプランの具体的価格**：公式に「custom pricing」とのみ記載で数値なし（意図通り"要問い合わせ"として記載）。
- **api_available の粒度**：SDK/Cloud Agents APIの存在自体は複数の公式ソース（changelog、公式フォーラム）で確認できたが、「一般開発者が自由に汎用LLM APIとして使える」ものではなく、Cursor自身のエージェント基盤を呼び出す形態である点に注意（JSON内`api_sdk_info`に明記済み）。