#!/usr/bin/env python3
"""Apply verified corrections to the recovered agent drafts (veo, kling-ai, claude-code, cursor).

Input : recovered/agent-transcripts/<slug>.agent-draft.json (never modified)
Output: tools/<slug>.json
Every replacement asserts that its target text exists, so a silent no-op is impossible.
"""
import json
import os

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEXT_FIELDS = ("post_content", "pricing_details", "api_sdk_info", "security_info", "notes", "omochix_view", "short_description", "meta_description")
ARRAY_FIELDS = ("key_features", "pros", "cons", "strengths", "weaknesses", "recommended_for", "recommended_use_cases", "not_recommended_for")


def load(slug):
    with open(os.path.join(BASE, "recovered/agent-transcripts", slug + ".agent-draft.json"), encoding="utf-8") as f:
        return json.load(f)


def save(tool):
    with open(os.path.join(BASE, "tools", tool["slug"] + ".json"), "w", encoding="utf-8") as f:
        json.dump(tool, f, ensure_ascii=False, indent=2)
    print("wrote", tool["slug"])


def rep(tool, field, old, new):
    assert old in tool[field], "%s.%s: target not found: %s" % (tool["slug"], field, old[:40])
    tool[field] = tool[field].replace(old, new)


def rep_item(tool, field, old_sub, new):
    hits = [i for i, v in enumerate(tool[field]) if old_sub in v]
    assert len(hits) == 1, "%s.%s: expected 1 item containing %s, got %d" % (tool["slug"], field, old_sub, len(hits))
    tool[field][hits[0]] = new


def fullwidth_parens(tool):
    for k in TEXT_FIELDS:
        if k in tool:
            tool[k] = tool[k].replace("(", "（").replace(")", "）")
    for k in ARRAY_FIELDS:
        if k in tool:
            tool[k] = [v.replace("(", "（").replace(")", "）") for v in tool[k]]


# --------------------------------------------------------------------- Veo
t = load("veo")
fullwidth_parens(t)
t["japanese_support"] = "partial"
t["supported_models"] = ["Veo 3.1", "Veo 3.1 Fast", "Veo 3.1 Lite"]
t["integrations"] = ["Gemini API", "Vertex AI", "Google AI Studio", "Google Flow", "Google Vids", "Gemini アプリ"]
rep(t, "post_content",
    "<p>消費者向けのGemini アプリでは、無料プランにVeoは含まれません。Google AI Pro（月額2,900円）ではVeo 3.1 Liteの限定的な試用が可能で、Google AI Ultra（月額14,500円〜32,000円、利用量プランにより異なる）ではVeo 3.1のフル機能が利用できます。これらの有料プランにはGoogle Flow用のクレジットも付与されますが、Flow単体でのAIクレジット追加購入は日本では現時点で提供されていません（2026年9月30日確認）。</p>",
    "<p>消費者向けのGemini アプリでは、無料プラン（0円）の機能一覧に動画生成は含まれていません。Google AI Plus（月額725円）以上の有料プランに動画生成とGoogle Flow用クレジット（Plus 200、Pro 1,000、Ultra 10,000〜25,000）が含まれます。Google AI Proは月額2,900円、Google AI Ultraは月額14,500円または32,000円です（2026年10月1日、Google公式のプランページで確認）。プランごとに利用できるVeoのモデルや生成回数は変更されることがあるため、契約前に公式ページで確認してください。Flow単体でのAIクレジット追加購入は、日本では現時点で提供されていません（2026年9月30日確認）。</p>")
rep(t, "post_content", "<li>generateContent APIを呼び出す、またはGemini アプリ/Flowの画面から生成を実行する</li>", "<li>APIを呼び出す、またはGemini アプリ/Flowの画面から生成を実行する</li>")
rep(t, "post_content", "<li>Gemini アプリの無料プランではVeoを利用できず、Pro以上の有料プランが前提となる</li>", "<li>Gemini アプリの無料プランには動画生成が含まれず、有料プラン（Google AI Plus以上）が前提となる</li>")
rep(t, "post_content", "A. Gemini アプリの無料プランにはVeoは含まれません。Google AI Pro以上の有料プラン、またはGemini API/Vertex AIの従量課金での利用が必要です（一時的な無料お試しキャンペーンが実施される場合はあります）。", "A. Gemini アプリの無料プランの機能一覧に動画生成は含まれていません。Google AI Plus以上の有料プラン、またはGemini API/Vertex AIの従量課金での利用が必要です。")
rep(t, "post_content", "Gemini アプリおよびGoogle Flowの画面（UI）は日本語に対応しており", "Gemini アプリおよびGoogle Flowの画面（UI）は日本語で利用でき")
rep_item(t, "cons", "無料プランではVeoを利用できず", "Gemini アプリの無料プランには動画生成が含まれず、有料プラン（Google AI Plus以上）への加入が前提となる")
t["pricing_details"] = (
    "Gemini API経由の場合は、生成した動画の秒数に応じた従量課金（米ドル建て、音声込み。2026年9月30日、ai.google.devの公式価格表で確認）。"
    "Veo 3.1は720p/1080pが1秒あたり0.40ドル、4Kが0.60ドル。Veo 3.1 Fastは720pが0.10ドル、1080pが0.12ドル、4Kが0.30ドル。Veo 3.1 Liteは720pが0.05ドル、1080pが0.08ドル（4K非対応）。"
    "Gemini APIに動画生成用の無料枠はない。Vertex AI経由の企業向け価格は別体系となる場合があるため、Google Cloudの公式価格ページで確認が必要。"
    "消費者向けのGemini アプリでは、無料プラン（0円）の機能一覧に動画生成は含まれず、Google AI Plus（月額725円）、Google AI Pro（月額2,900円）、Google AI Ultra（月額14,500円または32,000円）に動画生成とGoogle Flow用クレジット（Plus 200、Pro 1,000、Ultra 10,000〜25,000）が含まれる（2026年10月1日、gemini.google/subscriptionsで確認）。"
    "Flow単体でのAIクレジット追加購入は、日本では現時点で提供されていない。"
)
rep(t, "api_sdk_info", "generateContent APIを通じて", "APIを通じて")
rep(t, "post_content", "Vertex AI（Google Cloud）経由の企業向け価格は別体系となる場合がありますが、公式サイトの詳細な価格表は今回の調査では確認できませんでした。", "Vertex AI（Google Cloud）経由の企業向け価格は別体系となる場合があるため、Google Cloudの公式価格ページで確認してください。")
rep(t, "api_sdk_info", "Veoは Gemini API", "VeoはGemini API")
rep(t, "notes", "今後Gemini Omniへの統合状況を継続的に確認する必要がある。", "両モデルの役割分担は今後変わる可能性がある。")
t["omochix_view"] = (
    "VeoはGoogleのGemini／Vertex AIのエコシステムに深く組み込まれた動画生成AIで、開発者向けAPIとしての完成度と、音声込みで動画を一括生成できる表現力の高さが強みと言える。"
    "RunwayやKling AIなどの動画生成サービスと比べると、Google Cloudの他サービスと組み合わせた本格的なプロダクト開発に向いている一方、無料で気軽に試せるツールではない点は参入障壁にもなりうる。"
    "2026年5月に発表された「Gemini Omni」が消費者向けの動画生成・編集の主軸になりつつあり、今後Veoは開発者・エンタープライズ向けの位置付けがより明確になっていく可能性がある。"
    "すでにGoogle Cloudを利用している開発チームや、音声付き動画をAPI経由で量産したい事業者には相性が良い。"
)
t["info_checked_date"] = "2026-10-01"
save(t)

# ---------------------------------------------------------------- Kling AI
t = load("kling-ai")
fullwidth_parens(t)
t["notes"] = (
    "旧ドメインのklingai.comは現在kling.aiへ転送される（2026年9月30日確認）。"
    "料金は米ドル建てで、プラン内容・価格は改定されることがあるため、契約前に公式の会員プランページで最新情報を確認することを推奨する。"
    "無料プランでの生成物は、公式利用規約上、書面の許可なく商用目的で使用できない。"
    "生成アプリ本体とサポート窓口の日本語対応レベルは公式情報から確認できていない。"
)
t["integrations"] = []
del t["integrations"]
save(t)

# ------------------------------------------------------------- Claude Code
t = load("claude-code")
t["commercial_use"] = "yes"
t["info_checked_date"] = "2026-10-01"
t["supported_devices"] = ["macOS", "Linux", "Windows", "Web", "iOS", "Android"]
t["supported_models"] = ["Claude Opus", "Claude Sonnet", "Claude Haiku", "Claude Fable"]
t["integrations"] = ["VS Code", "Cursor", "JetBrains IDE", "GitHub", "GitLab", "Slack", "Chrome", "MCP"]
rep(t, "post_content",
    "<p>Claude CodeはAnthropicの有料プランに含まれる形で提供されており、無料プランでは利用できません（2026年9月時点、claude.com/pricing確認）。個人向けにはPro・Maxプラン、チーム向けにはTeamプラン、大規模組織向けにはEnterpriseプランが用意されています。</p>",
    "<p>Claude CodeはAnthropicの有料プランに含まれる形で提供されており、無料プランでは利用できません（2026年10月1日、claude.com/pricingで確認）。個人向けにはPro・Maxプラン、チーム向けにはTeamプラン、大規模組織向けにはEnterpriseプランが用意されています。以下は税別の米ドル価格で、日本から表示される料金ページでは消費税10%込みの金額（Proは月払い22ドル、Maxは110ドルから）が表示されます。</p>")
rep(t, "post_content",
    "<ul><li>Pro：月額20ドル（年払いの場合は月額17ドル相当）。Claude Codeを含む基本的な利用枠</li><li>Max 5x：月額100ドル。Proの5倍の利用量</li><li>Max 20x：月額200ドル。長時間・大量利用向けの上位プラン</li><li>Team Standard：1シートあたり月額25ドル（年払いは月額20ドル）</li><li>Team Premium：1シートあたり月額125ドル（年払いは月額100ドル）</li><li>Enterprise：SSO・SCIM・監査ログなどを備えたカスタム契約。料金は個別見積もりで、利用量に応じた課金も選択可能</li></ul>",
    "<ul><li>Pro：月払い20ドル（年払いの場合は月額17ドル相当）。Claude Codeを含む基本的な利用枠</li><li>Max：月額100ドルから。Proの5倍または20倍の使用量を選択できる</li><li>Team スタンダードシート：1シートあたり月払い25ドル（年払いは月額20ドル）</li><li>Team プレミアムシート：1シートあたり月払い125ドル（年払いは月額100ドル）。スタンダードシートの5倍の使用量</li><li>Enterprise：1シートあたり月額20ドルに、APIレートでの使用量を加算（年払い）。SCIM、監査ログ、カスタムデータ保持、ロールベースのアクセス制御を含む</li></ul>")
rep(t, "post_content",
    "<p><strong>Q. Claude Code自体にAPIはありますか？</strong></p>",
    "<p><strong>Q. 商用利用はできますか？</strong></p><p>A. 個人向けプランの利用規約（Consumer Terms）では、規約の遵守を条件に出力に関する権利がユーザーに譲渡されると定められています。Team・Enterprise・APIは商用利用規約（Commercial Terms）の対象です。業務で利用する場合は、データの取り扱いを含めて自社に適用される規約を確認してください。</p><p><strong>Q. Claude Code自体にAPIはありますか？</strong></p>")
rep(t, "post_content", "そのためOmochiXでは日本語対応を「一部対応」として扱っています。", "そのため、日本語対応は「一部対応」と捉えるのが実態に近いでしょう。")
rep_item(t, "weaknesses", "料金体系がプラン・API課金", "サブスクリプション・API従量課金・シート課金が併存し、料金体系が分かりにくい")
t["pricing_details"] = (
    "Claude Codeは無料プランに含まれず、有料プラン経由でのみ利用できる（2026年10月1日、claude.com/pricingで確認）。以下は税別の米ドル価格。"
    "Proは月払い20ドル（年払いは月額17ドル相当、200ドル一括）。Maxは月額100ドルからで、Proの5倍または20倍の使用量を選択できる。"
    "Teamはスタンダードシートが1シートあたり月払い25ドル（年払い月額20ドル）、プレミアムシートが月払い125ドル（年払い月額100ドル）。"
    "Enterpriseは1シートあたり月額20ドルにAPIレートでの使用量を加算する年払いの契約で、営業経由のカスタム見積もりにも対応する。"
    "日本から表示される料金ページは消費税10%込みの表示で、Proは月払い22ドル（年払い月額18ドル相当）、Maxは110ドルからと表示される。"
    "ウェブ・デスクトップ・モバイル・Claude Codeの利用は同じ使用量プールから消費される。Anthropic ConsoleのAPIキーを使った従量課金（トークン単価制）でも利用でき、単価はモデルごとに異なる。"
)
t["notes"] = (
    "Claude Codeは更新頻度の高い製品で、料金・モデル・機能は変更される可能性がある。"
    "日本から表示される料金ページは消費税10%込みの金額で、本ページに記載した税別の米ドル価格とは表示が異なる。"
    "Agent SDKで構築した製品を「Claude Code」と名乗ることは、ブランドガイドライン上認められていない。"
    "契約前に公式の料金ページ（claude.com/pricing）で最新情報を確認することを推奨する。"
)
rep(t, "security_info", "個人向け（Free/Pro/Max）はモデル改善へのデータ利用可否に応じて", "個人向けプランはモデル改善へのデータ利用可否に応じて")
rep(t, "security_info", "個人向けプラン（Free/Pro/Max）ではモデル改善へのデータ利用設定がオンの場合に学習利用される", "個人向けプランではモデル改善へのデータ利用設定がオンの場合に学習利用される")
t["omochix_view"] = (
    "Claude Codeは、Anthropicが自社モデルに最適化して開発するエージェント型コーディングツールで、ターミナルを起点にリポジトリ全体を横断した実装・修正・プルリクエスト作成までを一貫して任せられる点が特徴と言える。"
    "エディター統合型のCursorや補完中心のツールと比べると、長時間の自律タスクやGit／GitHub連携の深さ、Agent SDKによる拡張性に強みがあり、大規模な保守・リファクタリングを任せたい開発チームと相性が良い。"
    "一方で無料プランでは使えず、チャットと使用量を共有するため、日常的に使い込むほどMaxやAPI従量課金の検討が必要になりやすい。"
    "料金とモデルの更新が速い製品だけに、導入後も公式情報を定期的に確認しておきたい。"
)
t["meta_description"] = "Claude CodeはAnthropicのエージェント型AIコーディングツール。料金プラン（Pro／Max／Team／Enterprise）、主な機能、日本語対応、導入手順、商用利用の考え方をOmochiXが公式情報をもとに解説します。"
save(t)

# ------------------------------------------------------------------ Cursor
t = load("cursor")
t["info_checked_date"] = "2026-10-01"
t["supported_devices"] = ["macOS", "Windows", "Linux"]
t["supported_models"] = ["Composer 2.5", "Claude Opus 5.5", "Claude Sonnet 5.5", "GPT-5.6 Sol", "Gemini 3.8 Flash", "Gemini 3.1 Pro", "Grok 4.7", "Kimi K3"]
t["integrations"] = ["GitHub", "Slack", "MCP", "VS Code拡張機能"]
rep(t, "post_content", "モデルはAnthropic（Claude系）、OpenAI（GPT系）、Google（Gemini系）、xAI（Grok系）、Anysphere自社製のComposerなど複数プロバイダーから選択でき、用途に応じて切り替えて使う設計です。",
    "モデルはAnthropic（Claude系）、OpenAI（GPT系）、Google（Gemini系）のほか、Grok系、Cursor製のComposerなど複数の選択肢があり、用途に応じて切り替えて使う設計です。")
rep(t, "post_content", "<p>Cursor自体はAIチャットやAgentモードへ日本語でプロンプトを入力すること自体は可能ですが、エディターのUI",
    "<p>公式サイト（cursor.com）や料金ページは日本語表示に対応しています。エディター本体も、AIチャットやAgentモードへ日本語でプロンプトを入力できますが、エディターのUI")
rep(t, "post_content",
    "<p>個人向けにはHobby（無料）、Pro、Pro+、Ultraの4段階、チーム向けにはTeams（Standard/Premium）、組織向けにはEnterpriseが用意されています。年払いを選ぶと月払いに比べて割引が適用されます。価格は税別で表示されています。</p>",
    "<p>個人向けにはHobby（無料）、Pro、Pro+、Ultraの4段階、チーム向けにはTeams（Standard／Premium）、組織向けにはEnterpriseが用意されています（2026年10月1日、cursor.com/pricingで確認。米ドル建て）。年払いを選ぶと月あたりの金額が下がります。</p>")
rep(t, "post_content",
    "<li>Pro+：月額60ドル。Proよりも大きい利用枠が付与され、Agentを日常的に使う開発者向け。</li>\n<li>Ultra：月額200ドル。Pro+よりもさらに大きい利用枠と、新機能・新モデルへの優先アクセスが付与される。</li>\n<li>Teams：1ユーザーあたり月額40ドル（Standard/Premium）。一元請求、チーム共有のクラウドエージェント、使用状況分析、チーム単位のプライバシー設定などを含む。</li>",
    "<li>Pro+：月額60ドル（年払いは月額48ドル）。Proの3倍のAgent利用上限が付与され、Agentを日常的に使う開発者向け。</li>\n<li>Ultra：月額200ドル（年払いは月額160ドル）。Proの20倍のAgent利用上限と、新機能への優先アクセスが付与される。</li>\n<li>Teams Standard：1ユーザーあたり月額40ドル。一元請求と管理、チーム共有のクラウドエージェント、Bugbotによるコードレビュー、使用状況分析、チーム全体のプライバシーモード、SAML/OIDC SSOを含む。</li>\n<li>Teams Premium：1ユーザーあたり月額120ドル（年払いは月額96ドル）。Standardの5倍のAgent利用上限。</li>")
rep(t, "post_content",
    "<p>Pro以上の各プランには一定量のモデル利用枠（クレジット）が含まれ、それを超えた利用分は従量課金となります。Pro+・Ultraの利用枠は、公式コミュニティフォーラムなどの情報ではそれぞれ70ドル相当・400ドル相当のAPIエージェント利用枠とされていますが、正式な金額は変更される可能性があるため、契約前に公式サイトの最新表示を確認することを推奨します。</p>",
    "<p>有料プランには一定量の利用枠が含まれ、それを使い切った後もオンデマンド利用として使い続けられます（超過分は後払いの従量課金）。プラン内容や価格は変更されることがあるため、契約前に公式サイトの最新表示を確認することを推奨します。</p>")
rep(t, "post_content", "利用するAIモデル（Claude、GPT、Gemini、Grok、Composerなど）を設定から選択・切り替える。", "利用するAIモデル（Claude、GPT、Gemini、Composerなど）を設定から選択・切り替える。")
t["pricing_details"] = (
    "個人向けはHobby（無料、クレジットカード不要、Agentリクエストは制限付き）、Pro（月額20ドル）、Pro+（月額60ドル、年払いは月額48ドル。Proの3倍のAgent利用上限）、Ultra（月額200ドル、年払いは月額160ドル。Proの20倍のAgent利用上限）。"
    "チーム向けはTeams Standard（1ユーザーあたり月額40ドル）とTeams Premium（1ユーザーあたり月額120ドル、年払いは月額96ドル。Standardの5倍のAgent利用上限）、Enterpriseはカスタム価格。"
    "有料プランに含まれる利用枠を超えた分は、オンデマンド利用として後払いの従量課金になる。Bugbotは個人向けプランでは従量課金で利用する。"
    "（2026年10月1日、cursor.com/pricingで確認。米ドル建て。Pro+とUltraの月払い価格は公式フォーラムの記載と年払い価格から確認）"
)
rep(t, "api_sdk_info", "エージェントをローカルまたはcrsrのクラウドVM上で実行できる", "エージェントをローカルまたはCursorのクラウドVM上で実行できる")
rep(t, "security_info", "Privacy Mode（無料・Proを含む全プランで設定可能）を有効にすると", "Privacy Modeを有効にすると")
t["notes"] = (
    "Cursorは価格・モデルのラインナップが頻繁に変わる製品で、対応モデルは本ページ記載のもの以外にも多数ある（公式ドキュメントのモデル一覧を参照）。"
    "公式サイトと料金ページは日本語で表示できるが、エディター本体のうちCursor独自のUIは英語表記が中心。"
    "契約前にcursor.com/pricingで最新の料金と利用枠を確認することを推奨する。"
)
rep_item(t, "cons", "UIは日本語に完全対応していない", "エディターのUIは日本語に完全対応していない（VS Code部分は言語パックで一部翻訳可、Cursor独自UIは英語）")
rep_item(t, "strengths", "Agent／Background Agent／Automations", "Agent・Background Agent・Automationsなど自律型AI機能の幅が広い")
rep(t, "omochix_view", "GitHub Copilotのような補完特化でもClaude Codeのようなエージェント特化でもなく、両者の中間に位置する。", "補完中心のツールとClaude Codeのようなエージェント中心のツールの中間に位置すると言える。")
rep(t, "omochix_view", "本格運用には月額20〜200ドル台の有料プランが前提になりやすい点は考慮が必要である。", "本格運用には月額20〜200ドルの有料プランが前提になりやすい点は考慮しておきたい。")
save(t)
