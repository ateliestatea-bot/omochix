#!/usr/bin/env python3
"""Author final content for notebooklm, chatgpt, gemini.

Facts come only from research/batch-A-*.md, research/batch-C-*.md and the
recovered agent reports. Output: tools/<slug>.json
"""
import json
import os

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def save(tool):
    with open(os.path.join(BASE, "tools", tool["slug"] + ".json"), "w", encoding="utf-8") as f:
        json.dump(tool, f, ensure_ascii=False, indent=2)
    print("wrote", tool["slug"])


def html(*blocks):
    return "\n".join(blocks)


# =============================================================== NotebookLM
with open(os.path.join(BASE, "recovered/agent-transcripts/notebooklm.agent-draft.json"), encoding="utf-8") as f:
    nb = json.load(f)

nb["info_checked_date"] = "2026-10-01"
nb["supported_devices"] = ["Web", "iOS", "Android"]
nb["integrations"] = ["Google ドライブ", "Google ドキュメント", "Google スライド", "YouTube", "Google Workspace"]
nb["post_content"] = html(
    "<h2>NotebookLMとは？</h2>",
    "<p>NotebookLM（ノートブックエルエム）は、Googleが提供するリサーチ・情報整理に特化したAIツールです。PDFやGoogleドキュメント、Webページ、YouTube動画のURLなど、自分で用意した資料（ソース）を読み込ませると、その内容の範囲内で要約・質問応答・音声解説などを生成できます。汎用的な対話AIと異なり、回答の根拠を自分のソースに限定できる点が特徴です。</p>",
    "<p>2026年7月16日、GoogleはNotebookLMを「Gemini Notebook」へ改称すると公式ブログで発表しました。既存のノートブックや共有リンクは自動リダイレクトで維持され、基本的な使い方は変わりません。公式ヘルプも現在は「Gemini Notebook」の名称で案内されています。本ページでは、広く知られている旧称の「NotebookLM」を主に用いて解説します。</p>",
    "<h2>NotebookLMでできること</h2>",
    "<p>NotebookLMの中核は、アップロードした資料に基づいて質問に答え、根拠となる箇所を引用付きで示す機能です。加えて、資料の内容を音声・動画・図解・学習教材といった別の形式に変換する機能が充実しています。</p>",
    "<h3>主要機能</h3>",
    "<ul>",
    "<li><strong>ソースに基づく質問応答</strong> — 資料の範囲内で回答を生成し、根拠箇所を引用として示す</li>",
    "<li><strong>Audio Overview（音声解説）</strong> — 資料の内容を2人のAIホストが対話形式で解説する音声を生成する。日本語音声にも対応</li>",
    "<li><strong>Video Overview（動画概要）</strong> — 資料の内容をスライド形式の動画にまとめる</li>",
    "<li><strong>マインドマップ</strong> — 資料の構造を図として可視化する</li>",
    "<li><strong>フラッシュカード・クイズ</strong> — 学習用の一問一答やクイズを自動作成する</li>",
    "<li><strong>多様なソース形式</strong> — PDF、Googleドキュメント／スライド、テキスト、Webページ、YouTube動画のURLを取り込める</li>",
    "<li><strong>コード実行</strong> — 2026年7月の改称以降、ノートブック内でデータ分析用のコードを実行する機能が順次追加されている</li>",
    "</ul>",
    "<h2>日本語で使える？</h2>",
    "<p>画面表示、資料のアップロード、チャットでの質問応答、Audio Overviewの生成はいずれも日本語で利用できます。Audio Overviewの日本語音声は2025年4月に追加されており、日本語の資料から日本語のポッドキャスト風音声を作成できます。公式ヘルプセンターにも日本語版があります。</p>",
    "<p>ただし、Audio Overviewの「Interactive（対話）モード」（音声ホストとリアルタイムで会話する機能）は、公式ヘルプの記載では英語のみの提供です。日本語ユーザーが使える範囲には一部制約が残っています。</p>",
    "<h2>NotebookLMの料金</h2>",
    "<p>NotebookLM単体の有料プランはありません。無料の「Standard」と、Googleの統合AIサブスクリプション（Google AI Plus／Pro／Ultra）または対象のGoogle Workspaceエディションに含まれる上位枠、という構成です（2026年10月1日、Google公式ヘルプおよびプランページで確認）。</p>",
    "<h3>各プラン</h3>",
    "<ul>",
    "<li><strong>Standard（無料）</strong> — ノートブック100件、1ノートブックあたりソース50件、チャット1日50回</li>",
    "<li><strong>Plus（Google AI Plus、月額725円）</strong> — ノートブック200件、ソース100件、チャット1日200回</li>",
    "<li><strong>Pro（Google AI Pro、月額2,900円）</strong> — ノートブック500件、ソース300件、チャット1日500回</li>",
    "<li><strong>Ultra（Google AI Ultra、月額14,500円または32,000円）</strong> — ノートブック500件、ソース500〜600件、チャット1日2,500〜5,000回</li>",
    "</ul>",
    "<p>上限の数値は変更される場合があると公式ヘルプに明記されています。Google AIの各プランにはGeminiアプリの利用枠やクラウドストレージなども含まれるため、NotebookLMだけの価格ではない点に注意してください。</p>",
    "<h2>NotebookLMの使い方</h2>",
    "<ol>",
    "<li><strong>ノートブックを作成</strong> — GoogleアカウントでNotebookLMにアクセスし、新しいノートブックを作成します。</li>",
    "<li><strong>ソースを追加</strong> — PDF、Googleドキュメント／スライド、テキスト、WebページやYouTube動画のURLを追加します。</li>",
    "<li><strong>質問する</strong> — チャット欄に質問を入力し、資料に基づく回答と引用元を確認します。</li>",
    "<li><strong>別の形式に変換</strong> — Audio Overview、Video Overview、マインドマップ、フラッシュカードなどを生成します。</li>",
    "<li><strong>保存・共有</strong> — 生成した音声や要約をダウンロード、またはノートブックを共有します。</li>",
    "</ol>",
    "<h2>NotebookLMの活用例</h2>",
    "<ul>",
    "<li><strong>研究・学習</strong> — 論文や専門資料を読み込み、要点を把握する</li>",
    "<li><strong>社内ナレッジ</strong> — マニュアルや議事録をもとに、質問形式で情報を引き出す</li>",
    "<li><strong>音声インプット</strong> — 移動中や作業中にAudio Overviewで資料の内容を聞く</li>",
    "<li><strong>試験対策・研修</strong> — フラッシュカードやクイズを作成する</li>",
    "<li><strong>情報整理</strong> — 複数の資料を横断してマインドマップで構造を整理する</li>",
    "</ul>",
    "<h2>NotebookLMのメリット</h2>",
    "<ul>",
    "<li>無料のStandardでも、要約・質問応答・Audio Overviewなど主要機能を試せる</li>",
    "<li>回答に出典箇所が示されるため、内容の裏付けを確認しやすい</li>",
    "<li>Audio Overviewを日本語で生成でき、音声でのインプットに使える</li>",
    "<li>PDF、Webページ、YouTube動画など多様な情報源を1つのノートブックにまとめられる</li>",
    "<li>対象のGoogle Workspaceエディションでは、追加契約なしで上位枠を使える場合がある</li>",
    "</ul>",
    "<h2>NotebookLMの注意点</h2>",
    "<ul>",
    "<li>資料を用意しない自由な雑談や汎用的な相談には向いていない</li>",
    "<li>無料のStandardはチャットが1日50回までなど、利用回数に上限がある</li>",
    "<li>Audio OverviewのInteractiveモードは英語のみで、日本語には対応していない</li>",
    "<li>個人アカウントでフィードバックを送信すると、そのやり取りが人によるレビューの対象となる場合がある</li>",
    "<li>個人向けには公開APIがなく、プログラムからの操作にはNotebookLM Enterpriseの契約が必要</li>",
    "</ul>",
    "<h2>どんな人におすすめ？</h2>",
    "<ul>",
    "<li>大量の論文・資料・議事録を読み込んで要点を把握したい研究者・学生</li>",
    "<li>社内資料をもとにQ&amp;Aや音声要約を作りたいビジネスパーソン</li>",
    "<li>移動中や作業中に資料の内容を音声で聞きたい人</li>",
    "<li>Google Workspaceを業務で利用している企業・チーム</li>",
    "</ul>",
    "<p>逆に、資料を用意せずに自由な会話をしたい人や、公開APIで自社サービスに深く組み込みたい個人開発者には不向きです。</p>",
    "<h2>FAQ</h2>",
    "<p><strong>Q. NotebookLMは無料で使えますか？</strong></p>",
    "<p>A. はい。無料の「Standard」でソースの取り込み、質問応答、Audio Overviewの生成などを利用できます。ノートブック100件、1ノートブックあたりソース50件、チャット1日50回といった上限があり、より多く使う場合はGoogle AI Plus／Pro／Ultra、または対象のGoogle Workspaceエディションが必要です。</p>",
    "<p><strong>Q. Audio Overviewは日本語で聞けますか？</strong></p>",
    "<p>A. 日本語のAudio Overviewを生成できます。ただし、音声ホストとリアルタイムで会話するInteractiveモードは英語のみの提供です。</p>",
    "<p><strong>Q. 「Gemini Notebook」とは別のサービスですか？</strong></p>",
    "<p>A. 同じサービスです。2026年7月16日にNotebookLMからGemini Notebookへ改称されました。既存のノートブックや共有リンクは自動リダイレクトで引き続き利用できます。</p>",
    "<p><strong>Q. 仕事や商用目的で使えますか？</strong></p>",
    "<p>A. Googleの利用規約では、サービスで生成したオリジナルコンテンツについてGoogleは所有権を主張しないとされています。組織で利用する場合は、アップロード内容が人によるレビューやモデル学習に使われないGoogle Workspaceの対象エディション、またはNotebookLM Enterpriseが用意されています。</p>",
    "<p><strong>Q. APIはありますか？</strong></p>",
    "<p>A. 個人向けのNotebookLMには公開APIがありません。Google Cloud上で別契約となるNotebookLM Enterpriseでは、ノートブックの作成やソースの追加をREST APIで操作できます。</p>",
)
nb["pricing_details"] = (
    "NotebookLM（Gemini Notebook）単体の有料プランはなく、無料の「Standard」と、Google AI Plus／Pro／Ultraまたは対象のGoogle Workspaceエディションに含まれる上位枠で構成される。"
    "Standard（無料）はノートブック100件、1ノートブックあたりソース50件、チャット1日50回。"
    "Plus（Google AI Plus、月額725円）は200件・100件・1日200回。"
    "Pro（Google AI Pro、月額2,900円）は500件・300件・1日500回。"
    "Ultra（Google AI Ultra、月額14,500円または32,000円）は500件・500〜600件・1日2,500〜5,000回。"
    "上限は変更される場合があると公式ヘルプに明記されている。"
    "Google AIの各プランはGeminiアプリの利用枠やクラウドストレージなどを含む統合サブスクリプションで、NotebookLM単独の価格ではない。"
    "（2026年10月1日、support.google.comおよびgemini.google/subscriptionsで確認）"
)
nb["api_sdk_info"] = (
    "個人向け（Standard／Plus／Pro／Ultra）のNotebookLMには、一般開発者向けの公開APIは提供されていない。"
    "プログラムからの操作が可能なのは、Google Cloud上で別契約となる「NotebookLM Enterprise」のみで、ノートブックの作成・管理やソースの追加をREST API経由で行える。"
    "利用には組織向けのGoogle Cloudプロジェクトとライセンス契約が必要で、個人開発者がAPIキーを発行してすぐ使える形態ではない。"
)
nb["notes"] = (
    "2026年7月16日、GoogleはNotebookLMを「Gemini Notebook」へ改称すると公式ブログで発表した。既存の共有リンクやノートブックは自動リダイレクトで維持される。"
    "本ページでは広く知られた旧称の「NotebookLM」を主に用いている。"
    "利用上限やGoogle AIプランの内容は変更されることがあるため、契約前に公式ページで最新情報を確認することを推奨する。"
    "Audio OverviewのInteractiveモードは英語のみの提供（公式ヘルプの記載）。"
)
nb["not_recommended_for"] = [
    "資料を用意せず自由な雑談や汎用的な相談をしたい人",
    "公開APIで自社サービスに深く組み込みたい個人・小規模の開発者",
    "利用回数の上限なく無料で使い続けたい人",
]
nb["cons"] = [
    "資料を持たない自由な雑談や汎用的な相談には向かない",
    "無料のStandardはチャット1日50回までなど利用回数に上限がある",
    "Audio OverviewのInteractiveモードは英語のみで日本語未対応",
    "個人アカウントではフィードバック送信時にやり取りが人によるレビュー対象となる場合がある",
    "個人向けには公開APIがなく、自社サービスへの組み込みは限定的",
]
nb["meta_description"] = "NotebookLM（2026年7月にGemini Notebookへ改称）の料金と無料枠の上限、Audio Overviewの日本語対応、使い方、APIや商用利用の考え方をOmochiXが公式情報をもとに解説します。"
save(nb)

# ================================================================= ChatGPT
chatgpt = {
    "slug": "chatgpt",
    "short_description": "文章作成・調査・画像生成・コーディング・データ分析を1つの対話画面で行えるOpenAIのAIアシスタント。無料版から法人向けまでプランが用意されている。",
    "post_content": html(
        "<h2>ChatGPTとは？</h2>",
        "<p>ChatGPTは、OpenAIが開発・提供するAIアシスタントです。Webブラウザ、iOS・Androidアプリ、デスクトップアプリから利用でき、文章作成、調査、画像生成、プログラミング、データ分析などを対話形式で進められます。無料版でも基本的なチャットを利用でき、個人向けのGo・Plus・Pro、法人向けのBusiness・Enterpriseと、用途に応じたプランが用意されています。</p>",
        "<h2>ChatGPTでできること</h2>",
        "<p>テキストでの対話に加え、Web検索、複数の情報源を横断して調べるDeep Research、画像生成、ファイルのアップロードと分析、データ分析、音声での会話に対応します。プロジェクト単位で会話やファイルを整理する機能や、過去のやり取りを踏まえて回答を調整するメモリ機能もあります。</p>",
        "<h3>主要モデル</h3>",
        "<p>公式の料金ページ（2026年10月1日確認）では、無料版は「GPT-5.6 Luna」でのテキストチャットが無制限とされ、Plus以上では「GPT-6」による高度なリーズニングモデル、Proでは「GPT-6 Astra」によるPro推論が案内されています。プラン比較表にはGPT-6.1 Sol、GPT-6 Sol、GPT-6 Luna、GPT-5.6 Sol、GPT-5.6 Terraなども掲載されています。利用できるモデルはプランによって異なり、入れ替わりも早いため、最新の構成は公式ページで確認してください。</p>",
        "<h3>Codex・ChatGPT Work・エージェント機能</h3>",
        "<p>コーディングエージェントの「Codex」は無料版でも上限付きで利用でき、有料プランで上限が拡大します。「ChatGPT Work（ChatGPT ワーク）」は無料版ではデスクトップアプリで制限付き、Plus以上ではデスクトップ・Web・モバイルで利用できます。Proには常時稼働のエージェント「Dot」が含まれます。</p>",
        "<h3>外部サービスとの連携</h3>",
        "<p>Businessプランでは、Google Workspace、Slack、GitHub、Microsoft 365などとの連携が案内されています。社内の情報を踏まえた回答や作業支援に利用できます。</p>",
        "<h2>日本語で使える？</h2>",
        "<p>ChatGPTは公式サイト、料金ページ、利用規約が日本語で提供されており、画面表示・入力・回答のいずれも日本語で利用できます。料金も日本向けには円建てで表示されます。回答には誤りが含まれる場合があるため、重要な内容は一次情報で確認する必要があります。</p>",
        "<h2>ChatGPTの料金</h2>",
        "<p>（2026年10月1日、ChatGPT公式の日本向け料金ページで確認した表示価格）</p>",
        "<h3>無料版</h3>",
        "<p>0円。GPT-5.6 Lunaでのテキストチャットは無制限です（不正利用防止のための安全対策が適用されます）。アップロードを含むメッセージ、画像作成、音声チャット、Deep Research、メモリ、Codexには上限があります。</p>",
        "<h3>Go</h3>",
        "<p>月額1,400円。無料版の内容に加え、ツールを使ったメッセージ、アップロード、画像生成、音声チャットをより多く利用でき、メモリも長くなります。このプランには広告が表示される場合があります。</p>",
        "<h3>Plus</h3>",
        "<p>月額3,000円。GPT-6による高度なリーズニングモデル、メッセージ送信とアップロードの上限拡大、Deep Researchの拡張、プロジェクト、スケジュール済みタスク、カスタムGPT、Codexの上限拡大、新機能への先行アクセスが含まれます。</p>",
        "<h3>Pro</h3>",
        "<p>月額16,800円から。3段階の利用枠から選べ、GPT-6 AstraによるPro推論、CodexとChatGPT Workの長時間セッション、常時稼働のエージェントDot、Deep Researchの最大活用などが含まれます。</p>",
        "<h3>Business</h3>",
        "<p>標準シートは年額課金で1ユーザーあたり月額3,050円（月額課金は3,850円）、プレミアムシートは年額課金で月額15,250円（月額課金は19,250円）で、標準シートの5倍の利用枠です。従業員数2〜200名のチーム向けで、SAML SSO・多要素認証、請求と管理の一元化、各種サービス連携を利用でき、デフォルトではビジネスデータが学習に使用されません。</p>",
        "<h3>Enterprise</h3>",
        "<p>カスタム価格（要問い合わせ）。SCIM、エンタープライズキー管理、ロールベースのアクセス制御、カスタムのデータ保持ポリシー、10の地域でのデータレジデンシー、優先サポートなどが含まれます。</p>",
        "<h2>ChatGPTの使い方</h2>",
        "<ol>",
        "<li><strong>アクセス</strong> — 公式サイト（chatgpt.com）またはアプリを開きます。</li>",
        "<li><strong>質問・依頼を入力</strong> — やりたいことを日本語で入力します。ファイルや画像を添付することもできます。</li>",
        "<li><strong>機能を選ぶ</strong> — 必要に応じてWeb検索、Deep Research、画像生成、データ分析などを使います。</li>",
        "<li><strong>対話で仕上げる</strong> — 回答に追加の指示を出し、内容を調整します。</li>",
        "<li><strong>整理・再利用</strong> — プロジェクトに会話やファイルをまとめ、継続的な作業に使います。</li>",
        "</ol>",
        "<h2>ChatGPTの活用例</h2>",
        "<ul>",
        "<li><strong>文章作成</strong> — メール、企画書、記事の下書き、要約、翻訳</li>",
        "<li><strong>調査</strong> — Web検索やDeep Researchによる情報収集と整理</li>",
        "<li><strong>開発</strong> — コードの作成・修正・デバッグ、Codexによるタスク実行</li>",
        "<li><strong>データ分析</strong> — 表データの集計、グラフ作成、資料の読み取り</li>",
        "<li><strong>クリエイティブ</strong> — 画像の生成と編集</li>",
        "<li><strong>学習</strong> — 分からない点の質問、学習モードを使った理解の確認</li>",
        "</ul>",
        "<h2>ChatGPTのメリット</h2>",
        "<ul>",
        "<li>文章・調査・画像・コード・データ分析まで、幅広い用途を1つのサービスでカバーできる</li>",
        "<li>公式サイト・料金・規約まで日本語化されており、日本語で自然に利用できる</li>",
        "<li>無料版でもテキストチャットを無制限に利用できる</li>",
        "<li>個人向けから法人向けまでプランが細かく分かれており、利用量に合わせて選べる</li>",
        "</ul>",
        "<h2>ChatGPTの注意点</h2>",
        "<ul>",
        "<li>回答内容が常に正しいとは限らず、重要な情報は一次情報での確認が必要</li>",
        "<li>モデルやプラン構成の変更頻度が高く、名称・価格・上限が変わりやすい</li>",
        "<li>無料版・Goは多くの機能に上限があり、Goでは広告が表示される場合がある</li>",
        "<li>個人向けプランでは、会話内容をモデルの学習に使用する設定を自分で無効化する必要がある</li>",
        "<li>ChatGPTとOpenAI APIは別サービスで、料金も別体系</li>",
        "</ul>",
        "<h2>どんな人におすすめ？</h2>",
        "<ul>",
        "<li>初めて生成AIを使う人</li>",
        "<li>文章作成・調査・資料分析を1つのAIで効率化したい人</li>",
        "<li>コーディングやデータ分析にもAIを使いたい人</li>",
        "<li>チームでの利用や外部サービス連携、管理機能を重視する企業</li>",
        "</ul>",
        "<p>逆に、AIの回答だけで正確性を保証したい人や、常に同じモデル・上限で使い続けたい人には不向きです。</p>",
        "<h2>FAQ</h2>",
        "<p><strong>Q. ChatGPTは無料で使えますか？</strong></p>",
        "<p>A. はい。無料版ではGPT-5.6 Lunaでのテキストチャットを無制限に利用できます。画像作成、音声、Deep Research、ファイルのアップロードなどには上限があります。</p>",
        "<p><strong>Q. PlusとProは何が違いますか？</strong></p>",
        "<p>A. Plus（月額3,000円）はGPT-6による高度なリーズニングモデルや各種上限の拡大が中心です。Pro（月額16,800円から）は3段階の利用枠から選べ、GPT-6 AstraによるPro推論や常時稼働のエージェントDotなどが加わります。</p>",
        "<p><strong>Q. 商用利用はできますか？</strong></p>",
        "<p>A. OpenAIの利用規約（2026年1月1日発効）では、適用法令で認められる範囲でユーザーがアウトプットを所有すると定められています。ChatGPT EnterpriseやAPIなど企業・開発者向けサービスには、別途、事業者用の取引規約が適用されます。</p>",
        "<p><strong>Q. 入力した内容は学習に使われますか？</strong></p>",
        "<p>A. 個人向けプランでは、コンテンツをモデルの学習に使用する設定を無効化できます。BusinessとEnterpriseでは、デフォルトでビジネスデータが学習に使用されません。</p>",
        "<p><strong>Q. APIとの違いは何ですか？</strong></p>",
        "<p>A. ChatGPTは対話アプリで、月額プランで利用します。開発者が自社のアプリに組み込むためのOpenAI APIは別サービスで、利用量に応じた従量課金です。</p>",
    ),
    "key_features": [
        "対話型アシスタント：文章作成・要約・翻訳・アイデア出しを対話しながら進められる",
        "Web検索・Deep Research：最新情報の収集から複数ソースを横断した詳細調査まで対応",
        "画像生成・編集：テキストの指示から画像を作成・修正できる",
        "ファイル・データ分析：PDFや表データを読み込み、集計やグラフ作成を行える",
        "Codex：コードの作成・修正・タスク実行を任せられるコーディングエージェント",
        "音声対話：音声チャットや動画対応の音声モードで会話できる",
        "プロジェクト・メモリ：会話やファイルを整理し、過去のやり取りを踏まえて回答する",
        "外部サービス連携：Google WorkspaceやSlack、GitHub、Microsoft 365などと接続できる",
    ],
    "pros": [
        "幅広い用途を1つのAIでカバーできる",
        "公式サイトや規約まで日本語化されており、日本語で自然に利用できる",
        "無料版でもテキストチャットを無制限に利用できる",
        "文章・画像・音声・ファイルなど複数の形式を扱える",
        "個人向けから法人向けまでプランを選べる",
        "Web検索や外部サービス連携にも対応している",
    ],
    "cons": [
        "回答内容が常に正しいとは限らない",
        "モデルやプラン構成の変更頻度が高い",
        "無料版とGoは多くの機能に上限があり、Goでは広告が表示される場合がある",
        "上位モデルや大きな利用枠にはPlus以上の契約が必要",
    ],
    "strengths": [
        "テキスト・画像・音声・ファイルを横断できる汎用性",
        "検索・リサーチ・コーディング・データ分析まで1つの画面に集約されている",
        "外部サービス連携やエージェント機能による作業支援の広がり",
        "無料版から法人向けまで幅広いプラン設計",
    ],
    "weaknesses": [
        "プランとモデルの名称が多く、選びにくい",
        "料金や機能の変更が頻繁で、最新情報を追う必要がある",
        "回答には誤りや古い情報が含まれる場合があり、重要な情報は一次情報での確認が必要",
    ],
    "recommended_for": [
        "初めて生成AIを使う人",
        "文章作成や調査を効率化したい人",
        "プログラミングやデータ分析にAIを使いたい人",
        "画像生成などクリエイティブ用途にもAIを使いたい人",
        "チーム利用や外部ツール連携を重視する企業",
    ],
    "recommended_use_cases": [
        "文章作成・要約・翻訳",
        "Web検索・情報収集",
        "Deep Researchによる調査",
        "企画・アイデア出し",
        "プログラミング",
        "画像生成・画像編集",
        "PDF・資料・ファイル分析",
        "データ分析",
    ],
    "not_recommended_for": [
        "AIの回答だけで100％の正確性を保証したい人",
        "専門家による確認なしで重要な判断を完結させたい人",
        "機密情報を組織のルール確認なしで入力したい人",
        "常に同じモデル・機能・利用上限を必要とする人",
    ],
    "pricing_details": (
        "無料版（0円）はGPT-5.6 Lunaでのテキストチャットが無制限で、アップロード・画像作成・音声・Deep Research・メモリ・Codexに上限がある。"
        "Go（月額1,400円）は各種上限が拡大し、広告が表示される場合がある。"
        "Plus（月額3,000円）はGPT-6による高度なリーズニングモデル、Deep Researchの拡張、プロジェクト、スケジュール済みタスク、カスタムGPT、Codexの上限拡大を含む。"
        "Pro（月額16,800円から）は3段階の利用枠から選べ、GPT-6 AstraによるPro推論や常時稼働のエージェントDotを含む。"
        "Businessは標準シートが年額課金で1ユーザーあたり月額3,050円（月額課金は3,850円）、プレミアムシートが年額課金で月額15,250円（月額課金は19,250円）で、従業員数2〜200名のチーム向け。"
        "Enterpriseはカスタム価格。"
        "（2026年10月1日、ChatGPT公式の日本向け料金ページの表示価格を確認。Proの各段階の価格は公式ページで確認が必要）"
    ),
    "api_sdk_info": (
        "ChatGPTとは別に、開発者向けのOpenAI APIが提供されており、OpenAIのモデルをWebサービスやアプリ、社内システムへ組み込める。"
        "API利用料金はChatGPTのサブスクリプションとは別体系で、モデルとトークン数に応じた従量課金。"
        "公式ドキュメントでは、ツール呼び出しに対応するResponses APIや、エージェント構築向けのAgents SDKが案内されている。"
        "ChatGPT Enterprise向けには、監査用途のCompliance APIも用意されている。"
    ),
    "security_info": (
        "データは転送時にTLS 1.2、保存時にAES-256で暗号化される（公式料金ページのFAQ）。"
        "個人向けプランでは、コンテンツをモデルの学習に使用する設定を無効化できる。BusinessとEnterpriseでは、デフォルトでビジネスデータが学習に使用されない。"
        "法人向けにはSOC 2 Type 2、ISO 27001・27017・27018・27701の認証、SAML SSO、SCIM、エンタープライズキー管理、ロールベースのアクセス制御が案内されており、Enterpriseでは日本を含む10の地域でのデータレジデンシーに対応する。"
    ),
    "notes": (
        "生成された回答には誤りが含まれる可能性がある。重要な事実・数値・引用・法律・医療・金融などの情報は、一次情報や専門家による確認が必要。"
        "利用できるモデル・機能・利用上限はプラン、地域、時期によって変更される場合がある。"
        "料金は日本向け料金ページの表示価格で、Proの3段階それぞれの価格は公式ページで確認が必要。"
        "ChatGPTとOpenAI APIは料金・利用枠が別。"
    ),
    "supported_devices": ["Web", "iOS", "Android", "macOS", "Windows"],
    "supported_models": ["GPT-6.1 Sol", "GPT-6 Astra", "GPT-6 Sol", "GPT-6 Luna", "GPT-5.6 Sol", "GPT-5.6 Terra", "GPT-5.6 Luna"],
    "integrations": ["Google Drive", "Google Calendar", "Gmail", "Microsoft Outlook", "Microsoft Teams", "SharePoint", "OneDrive", "GitHub", "Slack"],
    "has_free_plan": True,
    "api_available": "yes",
    "commercial_use": "yes",
    "japanese_support": "full",
    "info_checked_date": "2026-10-01",
    "omochix_view": (
        "ChatGPTの強みは、単体のモデル性能よりも、検索・リサーチ・画像・音声・ファイル・データ分析・コーディングまでを1つの画面に集約している点にあると言える。"
        "Codexや常時稼働のエージェントDotの追加によって、「質問に答えるAI」から「作業を任せるAI」へと重心が移りつつあり、Google連携に強いGeminiや出典付き検索を軸にするPerplexityとは異なる方向で総合力を伸ばしている。"
        "一方でプランとモデルの名称が増え、どれを選ぶべきか分かりにくくなっている面もある。"
        "まず無料版で試し、利用量や必要なモデルに応じてGo・Plusへ移る進め方が現実的だろう。"
        "法人利用では、学習に使用されないBusiness以上を前提に検討したい。"
    ),
    "seo_title": "ChatGPTとは？料金・機能・使い方・日本語対応を解説｜OmochiX",
    "meta_description": "ChatGPTの料金プラン（無料版・Go・Plus・Pro・Business・Enterprise）、対応モデル、主な機能、使い方、日本語対応、商用利用の考え方をOmochiXが公式情報をもとに解説します。",
}
save(chatgpt)

# ================================================================== Gemini
gemini = {
    "slug": "gemini",
    "short_description": "Googleが提供するAIアシスタント。文章作成や調査、画像・動画の生成、Deep Researchに対応し、GmailやGoogleドキュメントなどGoogleサービスと連携できる。",
    "post_content": html(
        "<h2>Geminiとは？</h2>",
        "<p>Geminiは、Googleが提供するAIアシスタントです。Web（gemini.google.com）やAndroid・iOSアプリから利用でき、文章作成、調査、画像の生成・編集、音声での会話などに対応します。GmailやGoogleドキュメントといったGoogleサービスの中でも使えるため、普段からGoogleのサービスを利用している人ほど導入しやすいのが特徴です。無料でも利用でき、より多く使いたい場合はGoogle AI Plus・Pro・Ultraの有料プランが用意されています。</p>",
        "<h2>Geminiでできること</h2>",
        "<p>テキストでの対話に加え、画像やファイルを使った分析、画像の生成・編集、複数の情報源を調べてレポートにまとめるDeep Research、音声で自然に会話できるGemini Live、文書やコードを対話しながら編集するCanvasを利用できます。有料プランでは動画生成やエージェント機能も使えます。</p>",
        "<h3>主要モデル</h3>",
        "<p>公式のプランページ（2026年10月1日確認）では、無料プランで「3.6 Flash」へのアクセスと「3.1 Pro」への限定的なアクセスが案内されています。Google AI Proでは「Gemini 3.1 Pro」やDeep Research、エージェント機能に幅広くアクセスでき、Google AI Ultraでは高度な推論モードの「Deep Think」や「Gemini Spark」が加わります。動画生成・編集向けには「Gemini Omni Flash」も提供されています。</p>",
        "<h3>Googleサービスとの連携</h3>",
        "<p>有料プランでは、GmailやGoogleドキュメントの中でGeminiを利用できます（Gemini in Gmail、Gemini in Google ドキュメント）。メールの下書きや文書の要約など、日常業務の中でAIを使える点が他のAIアシスタントとの違いです。</p>",
        "<h2>日本語で使える？</h2>",
        "<p>Geminiは画面表示・入力・回答のいずれも日本語で利用できます。公式のプランページやヘルプセンターも日本語で提供されており、料金は円建てで表示されます。回答には誤りが含まれる場合があるため、重要な内容は一次情報で確認する必要があります。</p>",
        "<h2>Geminiの料金</h2>",
        "<p>（2026年10月1日、Google公式の日本向けプランページで確認した表示価格）</p>",
        "<h3>無料</h3>",
        "<p>0円。3.6 Flashへのアクセス、3.1 Proへの限定的なアクセス、画像の生成・編集、Deep Research、Gemini Live、Canvasを利用でき、15GBのストレージが含まれます。</p>",
        "<h3>Google AI Plus</h3>",
        "<p>月額725円。無料プランの2倍の利用上限に加え、動画生成、Google Flowのクレジット200、Gemini in Gmail・Gemini in Google ドキュメント、400GBのストレージが含まれます。</p>",
        "<h3>Google AI Pro</h3>",
        "<p>月額2,900円。無料プランの4倍の利用上限で、Gemini 3.1 Pro、Deep Research、エージェント機能に幅広くアクセスできます。Google Flowのクレジット1,000、Gemini Omni Flash、YouTube Premium Lite、5TBのストレージが含まれます。</p>",
        "<h3>Google AI Ultra</h3>",
        "<p>月額14,500円（Proの5倍の利用上限）または月額32,000円（Proの20倍の利用上限）。Deep ThinkやGemini Sparkなどの高度な機能、Google Flowのクレジット10,000〜25,000、Project Genie、YouTube Premium、20TB以上のストレージが含まれます。</p>",
        "<p>開発者向けのGemini APIは、これらのサブスクリプションとは別の料金体系（無料枠と従量課金）です。</p>",
        "<h2>Geminiの使い方</h2>",
        "<ol>",
        "<li><strong>アクセス</strong> — Googleアカウントでgemini.google.comまたはGeminiアプリを開きます。</li>",
        "<li><strong>質問・依頼を入力</strong> — やりたいことを日本語で入力します。画像やファイルを添付することもできます。</li>",
        "<li><strong>機能を選ぶ</strong> — Deep Research、画像生成、Canvas、Gemini Liveなどを必要に応じて使います。</li>",
        "<li><strong>対話で仕上げる</strong> — 回答に追加の指示を出し、内容を調整します。</li>",
        "<li><strong>Googleサービスで活用</strong> — 有料プランでは、GmailやGoogleドキュメントの中でもGeminiを使います。</li>",
        "</ol>",
        "<h2>Geminiの活用例</h2>",
        "<ul>",
        "<li><strong>調査</strong> — Deep Researchで複数の情報源を調べ、レポートにまとめる</li>",
        "<li><strong>文章作成</strong> — メールや文書の下書き、要約、翻訳</li>",
        "<li><strong>資料分析</strong> — PDFや画像を読み込ませて内容を整理する</li>",
        "<li><strong>クリエイティブ</strong> — 画像の生成・編集、有料プランでの動画生成</li>",
        "<li><strong>音声での相談</strong> — Gemini Liveで話しながらアイデアを整理する</li>",
        "<li><strong>開発</strong> — Gemini APIを使って自社のアプリやサービスにAIを組み込む</li>",
        "</ul>",
        "<h2>Geminiのメリット</h2>",
        "<ul>",
        "<li>GmailやGoogleドキュメントなど、Googleサービスとの連携に強い</li>",
        "<li>無料プランでもDeep Researchや画像生成、Gemini Liveを試せる</li>",
        "<li>日本語で利用でき、料金も円建てで分かりやすい</li>",
        "<li>有料プランにクラウドストレージなどが含まれる</li>",
        "<li>開発者向けのGemini APIに無料枠がある</li>",
        "</ul>",
        "<h2>Geminiの注意点</h2>",
        "<ul>",
        "<li>回答内容が常に正しいとは限らず、重要な情報は一次情報での確認が必要</li>",
        "<li>モデル・機能・利用上限はプランや時期によって変わりやすい</li>",
        "<li>個人のGoogleアカウントで利用する場合、チャットが人によるレビューやサービス改善に使われることがある</li>",
        "<li>動画生成やGmail・ドキュメント内でのGeminiは有料プランが前提</li>",
        "<li>GeminiアプリとGemini APIは料金体系と利用条件が異なる</li>",
        "</ul>",
        "<h2>どんな人におすすめ？</h2>",
        "<ul>",
        "<li>GmailやGoogleドライブなど、Googleサービスを日常的に使っている人</li>",
        "<li>調査や資料の整理を効率化したい人</li>",
        "<li>文章・画像・音声を1つのAIで扱いたい人</li>",
        "<li>Gemini APIで自社サービスにAIを組み込みたい開発者</li>",
        "</ul>",
        "<p>逆に、Googleアカウントを利用したくない人や、機密情報を組織のルール確認なしで入力したい人には不向きです。</p>",
        "<h2>FAQ</h2>",
        "<p><strong>Q. Geminiは無料で使えますか？</strong></p>",
        "<p>A. はい。無料プランで3.6 Flashへのアクセス、3.1 Proへの限定的なアクセス、画像の生成・編集、Deep Research、Gemini Live、Canvasを利用できます。利用上限を増やしたい場合や動画生成を使いたい場合は、Google AI Plus（月額725円）以上が必要です。</p>",
        "<p><strong>Q. Google AI PlusとProはどう違いますか？</strong></p>",
        "<p>A. Plus（月額725円）は無料プランの2倍、Pro（月額2,900円）は4倍の利用上限です。ProではGemini 3.1 Proやエージェント機能に幅広くアクセスでき、Google Flowのクレジットやストレージも増えます。</p>",
        "<p><strong>Q. 商用利用はできますか？</strong></p>",
        "<p>A. Googleの利用規約（2026年7月30日発効）では、サービスで生成したオリジナルコンテンツについてGoogleは所有権を主張しないとされています。一方、生成コンテンツを機械学習モデルの開発に使うことなどは禁止されています。業務で使う場合は、データの取り扱いが異なるGoogle Workspace向けの提供も検討してください。</p>",
        "<p><strong>Q. 入力した内容は学習に使われますか？</strong></p>",
        "<p>A. 個人のGoogleアカウントでは、チャットが人によるレビューの対象となり、サービスの改善に使われることがあります。対象のGoogle Workspaceエディションで利用する場合は、許可なく人によるレビューや学習に使用されないとされています。</p>",
        "<p><strong>Q. Gemini APIとの違いは何ですか？</strong></p>",
        "<p>A. Geminiアプリは個人やチームが対話で使うアシスタントで、月額プランで利用します。Gemini APIは開発者が自社のアプリに組み込むためのもので、無料枠と従量課金の料金体系です。</p>",
    ),
    "key_features": [
        "対話型アシスタント：文章作成・要約・翻訳・アイデア出しを対話で進められる",
        "Deep Research：複数の情報源を調べてレポートにまとめる",
        "画像生成・編集：テキストの指示から画像を作成・修正できる",
        "Gemini Live：音声で自然に会話しながら相談できる",
        "Canvas：文書やコードを対話しながら編集できる",
        "Googleサービス連携：GmailやGoogleドキュメントの中でGeminiを利用できる（有料プラン）",
        "動画生成：有料プランで動画生成とGoogle Flowのクレジットを利用できる",
        "Gemini API：開発者が自社アプリにGeminiのモデルを組み込める",
    ],
    "pros": [
        "Googleサービスとの連携に強い",
        "無料プランでもDeep Researchや画像生成を試せる",
        "文章・画像・音声など幅広く扱える",
        "日本語で利用でき、料金も円建てで表示される",
        "有料プランにクラウドストレージなどが含まれる",
    ],
    "cons": [
        "回答内容が常に正しいとは限らない",
        "モデル・機能・利用上限が頻繁に変更される",
        "動画生成やGmail・ドキュメント内での利用は有料プランが必要",
        "個人アカウントではチャットが人によるレビューや改善に使われることがある",
    ],
    "strengths": [
        "Gmail・ドキュメントなど日常的に使うGoogleサービスとAIがつながっている",
        "Deep Researchによる複雑な調査",
        "画像・動画・音声を含むマルチモーダルな生成と理解",
        "月額725円から始められる段階的なプラン設計",
    ],
    "weaknesses": [
        "回答には誤りや古い情報が含まれる場合がある",
        "利用できるモデルや機能がプランによって異なり、把握しにくい",
        "Googleアカウントの種類によってデータの取り扱いが異なる",
    ],
    "recommended_for": [
        "Googleサービスを日常的に利用する人",
        "GmailやGoogleドライブを仕事で使う人",
        "調査や資料の整理を効率化したい人",
        "画像・動画・音声もAIで扱いたい人",
        "Gemini APIでAIを組み込みたい開発者",
    ],
    "recommended_use_cases": [
        "Web検索・情報収集",
        "Deep Research",
        "文章作成・要約・翻訳",
        "PDF・資料分析",
        "画像生成・画像編集",
        "Google Workspaceを使った業務",
        "アイデア出し・企画",
        "音声AIアシスタント",
    ],
    "not_recommended_for": [
        "AIの回答だけで100％の正確性を保証したい人",
        "一次情報を確認せず重要な判断を完結させたい人",
        "Googleアカウントを利用したくない人",
        "機密情報を組織のルール確認なしで入力したい人",
    ],
    "pricing_details": (
        "無料プラン（0円）は3.6 Flashへのアクセス、3.1 Proへの限定的なアクセス、画像の生成・編集、Deep Research、Gemini Live、Canvas、15GBのストレージを含む。"
        "Google AI Plus（月額725円）は無料プランの2倍の利用上限、動画生成、Google Flowのクレジット200、Gemini in Gmail・Gemini in Google ドキュメント、400GBのストレージ。"
        "Google AI Pro（月額2,900円）は無料プランの4倍の利用上限で、Gemini 3.1 Pro・Deep Research・エージェント機能への幅広いアクセス、Flowのクレジット1,000、Gemini Omni Flash、YouTube Premium Lite、5TBのストレージ。"
        "Google AI Ultraは月額14,500円（Proの5倍の利用上限）または月額32,000円（Proの20倍）で、Deep ThinkやGemini Spark、Flowのクレジット10,000〜25,000、Project Genie、YouTube Premium、20TB以上のストレージを含む。"
        "開発者向けのGemini APIは別料金で、無料枠と従量課金がある。"
        "（2026年10月1日、gemini.google/subscriptionsの日本向け表示を確認）"
    ),
    "api_sdk_info": (
        "Gemini APIを通じて、GoogleのAIモデルをWebサービス、アプリ、社内システムへ組み込める。Google AI Studioでプロンプトやモデルを試しながら開発でき、APIキーもここで取得する。"
        "SDKはPython、JavaScript、Java、Goに対応し、RESTでも呼び出せる。"
        "公式のモデル一覧では、Gemini 3.8 Flash、Gemini 3.8 Live、Gemini 3.6 Flash、Gemini 3.5 Flash-Liteなどが安定版、Gemini 3.1 ProやGemini Omni Flashがプレビュー版として掲載されている。"
        "料金は無料枠と従量課金で、Gemini 3.8 Flashは100万トークンあたり入力0.75ドル・出力3.75ドル（2026年12月31日まで。2027年1月1日以降は1.50ドル・7.50ドル）、Gemini 3.5 Flash-Liteは入力0.30ドル・出力2.50ドル。Batch APIでは50%割引になる。"
        "（2026年9月30日〜10月1日、ai.google.devで確認）"
    ),
    "security_info": (
        "データの取り扱いは、Geminiアプリ（個人アカウント）、Google Workspace、Gemini APIのどれを使うかによって異なる。"
        "個人のGoogleアカウントでは、Googleの利用規約とGemini アプリのプライバシー ノーティスが適用され、チャットが人によるレビューの対象となり、サービスの改善に使われることがある。"
        "対象のGoogle Workspaceエディションでは、Geminiアプリがエンタープライズ向けのデータ保護付きで提供され、コンテンツは許可なく人によるレビューや生成AIモデルの学習に使用されないとされている。"
        "機密情報や個人情報を扱う場合は、利用するサービスのデータ利用条件と所属組織のポリシーを確認すること。"
    ),
    "notes": (
        "Geminiが生成する回答には誤りが含まれる可能性がある。重要な事実、数値、引用、法律、医療、金融などの情報は、一次情報や専門家による確認が必要。"
        "利用できるモデル、機能、利用上限はプラン・地域・アカウント・時期によって異なり、変更されることがある。"
        "GeminiアプリとGemini APIは料金体系や利用条件が異なる。"
        "Google AIプランの価格や内容は改定されることがあるため（Google AI Plusは2026年1月の提供開始時は月額1,200円）、契約前に公式ページで最新の表示を確認することを推奨する。"
    ),
    "supported_devices": ["Web", "iOS", "Android", "macOS"],
    "supported_models": ["Gemini 3.6 Flash", "Gemini 3.1 Pro", "Gemini Omni Flash", "Gemini 3.8 Flash（API）", "Gemini 3.8 Live（API）"],
    "has_free_plan": True,
    "api_available": "yes",
    "commercial_use": "yes",
    "japanese_support": "full",
    "info_checked_date": "2026-10-01",
    "omochix_view": (
        "Geminiの最大の強みは、単独のAIチャットとしての性能よりも、Gmailやドキュメント、検索といったGoogleのサービス群とAIがつながっている点にあると言える。"
        "ChatGPTが1つの画面に機能を集約する方向で総合力を高めているのに対し、Geminiは普段使っているGoogleサービスの中にAIを溶け込ませる方向で広がっている。"
        "月額725円のGoogle AI Plusから段階的に選べる料金設計も、まず試してみたい個人には入りやすい。"
        "一方で、モデル名やプラン内容の入れ替わりが速く、個人アカウントと組織向けでデータの取り扱いが異なる点は分かりにくい。"
        "業務で使う場合は、Workspace側の提供条件を確認したうえで導入を検討したい。"
    ),
    "seo_title": "Geminiとは？料金・機能・使い方・日本語対応を解説｜OmochiX",
    "meta_description": "GoogleのAIアシスタント「Gemini」の料金プラン（無料・Google AI Plus／Pro／Ultra）、対応モデル、Deep Researchなどの機能、使い方、日本語対応、商用利用、Gemini APIをOmochiXが解説します。",
}
save(gemini)
