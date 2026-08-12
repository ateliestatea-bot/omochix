# Runway Agent 2.0とは？AIが広告・動画制作をどこまで進めるのか

## 公開管理情報

| 項目 | 内容 |
|---|---|
| SEO title | Runway Agent 2.0で広告制作はどう変わる？AIワークフローを解説 |
| Slug | `runway-agent-2-creative-workflow` |
| Article type | ニュース＋解説 |
| Category | AIニュース（slug: `ai-news`） |
| Tags | Runway / 動画生成 / 広告制作 / AIエージェント / マーケティング |
| Author | OmochiX編集部 |
| Published date |  |
| Updated date |  |
| Last fact-check date | 2026-08-12 |
| AI assistance | あり（一次情報の調査補助、構成、下書き作成、校正） |
| Human review status | 公開前確認待ち |
| PR / Sponsored status | No |
| Eyecatch status | 未制作 |
| Eyecatch candidate | Runway Agent 2.0 / 広告・動画制作はどう変わる？ |
| Primary sources | Runway News / Engineering / Pricing / Help / Dev / Terms（本文末尾） |
| Internal link candidates | `gemini-notebook-update-guide` / `cursor-ipad-mobile-agent-guide` |

## Meta Description

Runway Agent 2.0を公式情報から解説。広告データの分析、企画、動画・SNS素材の生成、バリエーション制作、人による修正、料金・CreditsやAPI状況を紹介します。

## 抜粋

Runwayが2026年6月25日に発表したAgent 2.0を解説します。広告データの分析から企画、動画・広告素材の生成、人による修正までの流れを整理します。

## 事実確認サマリー

| 確認項目 | 2026年8月12日時点のRunway公式情報 |
|---|---|
| 正式名称 | Agent 2.0（Runway Agentのアップグレード版） |
| 発表日 | 2026年6月25日 |
| 提供開始日 | 発表時点で全ユーザーが利用可能と案内 |
| 主な機能 | 広告・商品・対象顧客・実績データを分析し、質問、企画、メッセージ設計、動画・広告・キャンペーン素材の生成とバリエーション作成を会話内で支援 |
| 動画との関係 | 複数シーン、音声、対話、音楽を含む完成動画を生成し、タイムラインで最終調整できるRunway Agentを基盤とする |
| 画像との関係 | 画像を入力・参照し、画像を含む制作物や視覚案を扱える。Agent内では画像候補を視覚的に比較・選別できる |
| 複数工程 | データ分析、戦略・Briefの提案、素材生成、形式別のバリエーション作成を同じ体験内で進められる |
| 広告 / Campaign | Meta、YouTube、TikTok、Googleの広告指標を利用者が渡すと、分析して次のテスト広告を作成。Campaign案、商品訴求、地域向けCopy・Visualの変更にも対応 |
| 人による確認 | 会話で質問・修正し、Visual候補を採用・破棄できる。動画は生成後にTimeline editorで最終調整可能 |
| 対象プラン | Agent 2.0は「全ユーザー向け」。個別機能・生成量はPlanとCreditsの条件に依存 |
| 料金 | Freeは月額0ドル・初回125 Credits。Standardは月額15ドル、Proは35ドル、Maxは95ドル。公式Pricingの月払い表示を基準 |
| Credits | Standard 625 / 月、Pro 2,250 / 月、Max 9,500 / 月。Agent 2.0固有の消費量は要確認 |
| API | Runway APIには動画・画像・音声ModelやAd Localization Recipeがあるが、Agent 2.0自体のAPI提供は確認できず |
| 商用利用 | Runwayは生成物の商用利用を制限せず、利用者のInput / Outputの所有権を主張しない。ただし第三者の権利や利用規約への適合は利用者の責任 |
| 日本 | 公式に日本を対象外とする記載は確認できないが、日本固有の提供地域・支払い条件は要確認 |

---

Runwayは2026年6月25日、クリエイティブ制作を会話で進める「Agent 2.0」を発表しました。今回の更新は、動画を1本生成して終わる機能ではなく、広告の実績や商品の情報を読み取り、次に作るべき案を考え、複数の素材まで制作する流れをマーケティング業務へ広げたものです。

ただし、「制作が完全に自動化された」という意味ではありません。利用者が目的や素材、実績データを渡し、Agentの質問や提案を確認しながら方向を決め、生成後も会話や編集画面で調整する設計です。どこまで任せられ、どこで人の判断が必要なのかを公式情報から整理します。

## Runway Agent 2.0とは？

Agent 2.0は、2026年5月13日に発表されたRunway Agentをマーケティング用途へ強化した体験です。最初のRunway Agentは、作りたい内容を文章で伝えると、Concept、Story beats、Visual directionを提案し、複数Scene、Voiceover、Dialogue、Musicを組み合わせた動画を制作する機能として登場しました。

Agent 2.0では、Paid ads campaign、近日発売する商品、狙いたいAudienceなどを渡すと、内容を分析し、必要な質問をしながら制作を進めます。Runwayは対象として、Brand、Performance、Social、Product marketingを挙げています。全ユーザーが利用できますが、使える生成Modelや生成量は契約Planの条件に左右されます。

## 何ができるようになった？

Agent 2.0の特徴は、入力できる材料と出力までの工程が広がったことです。

- 商品、対象顧客、制作途中のCampaignを基に企画案を考える
- Creative angleやPositioningを整理する
- 広告素材とMeta、YouTube、TikTok、Googleの実績指標を分析する
- 分析を基に次のテスト広告を作成する
- 1週間分のSocial contentとPlatform別Variationを作成する
- 9:16、16:9、1:1など、配信先に合わせた形式へ展開する
- Copyを地域向けに調整し、Visualを差し替える

開発記事では、広告結果のPDFを分析し、新しい戦略やBriefを提案してCreativeを作る例も示されています。

広告Platformへ自動接続し、実績を常時学習してCampaignを自動運用する機能は、現時点では「今後」の構想です。現状は、利用者が素材や数値を持ち込み、会話で分析と制作を進めます。

## AI Agentは制作をどこまで進められる？

Runway Agentは、単一のClipを生成するだけではありません。公式情報では、Video clip、Audio、Imageを1つのTimelineへまとめ、完成形に近い複数Sceneの動画を作れると説明されています。

大まかな流れは次のとおりです。

1. 作りたい動画やCampaignの目的を文章で伝える
2. 必要に応じてReference image、Creative、実績データ、Briefを渡す
3. Agentが質問し、ConceptやStory structure、訴求案を提案する
4. 利用者が会話とVisual候補を見ながら方向を修正する
5. Agentが動画やCampaign素材、形式別のVariationを生成する
6. 利用者がTimeline editorや会話で最終調整する

Runwayは、Agentが目的に応じて異なるModelの特性を使い分けると説明しています。一方、内部で選ばれたModelや各工程を固定できる範囲は、公開情報だけでは確認できません。

人の役割は残ります。提示された画像を採用・破棄し、Chatで別方向を試し、動画生成後はTimeline editorで最終調整できます。人の目的と判断を反映しながら実行を支援する構造です。

## 動画・広告制作ではどう使える？

公式に挙げられている用途には、Seasonal content、Brand video、Campaign asset、Paid ad、Social post、Product positioningがあります。

たとえばPerformance marketingでは、現在の広告素材と指標を入力し、反応の良い要素を分析して次のテスト案を作れます。Social marketingでは、前週のEngagement dataを参考に次週の投稿案を作り、Reels、Stories、YouTube、Feed向けの比率へ展開できます。

商品紹介では、商品情報やReference imageを基に訴求角度を相談しながらCampaign素材を作れます。ただし、商品情報、価格、効能、Brand guideline、字幕、人物表現は、人が事実と権利を確認する必要があります。広告Platformへの直接接続と自動配信は将来構想であり、現在の機能ではありません。

## 従来の動画生成AIと何が違う？

従来型の生成画面では、PromptやReference imageを渡し、1つのClipや画像を出力することが中心です。Agent 2.0は、その前後にある「何を作るか」「なぜその案にするか」「どの形式へ展開するか」まで会話の対象にします。

違いは性能の優劣ではなくWorkflowの範囲です。単体Modelは特定の生成を細かく制御する用途、Agentは複数の素材と工程を組み合わせる用途を担います。

なお、Runway APIではGen-4.5、Aleph 2.0、Seedance 2.0などのModelや、広告画像を別言語向けに調整するAd Localization Recipeが提供されています。一方、Agent 2.0そのものをAPIから呼び出す仕様は、2026年8月12日時点の公式API Documentationでは確認できませんでした。

## 仕事ではどう使えそう？

**ここからは、公式機能を踏まえたOmochiX編集部の見解です。**

Agent 2.0は、最終成果物を無確認で公開する仕組みより、企画と試作の往復を短くする「制作パートナー」として使うのが現実的です。

SNS担当者なら、過去投稿の数値とBrand素材を渡し、次週分の叩き台を複数作る。広告担当者なら、反応の違う訴求案を展開し、入稿前に事実・Brand・権利を確認する。商品担当者なら、1つの説明から縦型・横型・正方形の案を比較するといった使い方が考えられます。

価値が出やすいのは、複数案を作り、比較し、改善する場面でしょう。厳密な法務審査、実在人物の表現、細かな演出、商品の正確な再現が必要な仕事では、人の確認と専門的な編集を省けません。何を評価し公開するかを決める役割は人に残ります。

## 料金・Credits・API

2026年8月12日時点のRunway公式Pricingでは、Freeは月額0ドルで初回125 Credits、Standardは月額15ドルで625 Credits、Proは月額35ドルで2,250 Credits、Maxは月額95ドルで9,500 Creditsと案内されています。年払いには割引価格があります。価格とCreditsは変更される可能性があるため、公開直前にも公式Pricingを再確認してください。

Creditsは画像、動画、音声の生成に使われ、消費量はModel、長さ、解像度で変わります。たとえばGen-4.5は動画1秒につき12 Creditsです。

Agent 2.0は全ユーザー向けですが、Agent内の各操作が何Creditsを消費するかをまとめた固有の料金表は確認できませんでした。公開前に実アカウントで消費表示を確認する必要があります。

Runway CreativeのCreditsとRunway APIのCreditsは別管理です。APIでは個別の生成ModelやRecipeが提供されていますが、Agent 2.0のAPI提供は要確認です。

## 商用利用と権利で注意すること

Runway公式Helpは、Runwayで作成したContentを商用利用でき、RunwayへのCredit表記も必須ではないと説明しています。利用規約では、Runwayは利用者のInputやOutputの所有権を主張せず、規約を守る限りOutputの商用利用を制限しないとしています。

一方、第三者の著作権、商標、肖像権などが自動的に処理されるわけではありません。利用者はBrand素材、人物画像、音声、音楽などを使う権利や許可を持つ必要があります。Usage Policyも、他人の画像・動画・音声の無断使用、知的財産権を侵害するContent、存命ArtistのStyleを狙う試みなどを禁止しています。

また、利用規約ではInputsとOutputsがModelなどの改善に使われ得る旨が記載されています。未公開Campaignや機密情報を入力する前に、所属組織のPolicy、Account設定、最新の利用規約を確認してください。

本記事は一般的な公式情報の整理であり、法律判断ではありません。広告へ使う場合は、地域ごとの法令、媒体審査、表示義務、素材の許諾を個別に確認してください。

## どんな人に向いている？

Agent 2.0は、次のような人に検討しやすい機能です。

- 少人数で広告やSocial contentを継続制作する担当者
- 既存広告の実績から次のテスト案を作りたい人
- 1つの企画を複数の画面比率や地域向けに展開したい人
- 動画制作の専門用語に詳しくなく、会話で方向を固めたい人
- 完成前の案を短時間で可視化し、Teamで検討したい人

生成工程を細かく固定したい場合や、厳密な商品・人物表現が必要な場合は、Agentだけで完結させず、個別Model、編集Tool、人によるProductionを組み合わせる判断が必要です。

## まとめ

Runway Agent 2.0は、広告や動画を単発で生成するだけでなく、実績データやBriefを読み、企画を提案し、複数のCreativeを作り、人が会話と編集画面で修正する流れを1つにつなげた機能です。

2026年8月12日時点で、広告Platformへの直接接続やCampaignの完全自動運用は今後の構想です。Agent 2.0固有のCredit消費、Agent API、日本固有の利用条件も確認が必要です。

OmochiXでは今後、実際の広告データを使わない安全な検証素材で、企画から複数形式の動画制作までの操作、Credit消費、人が直すべき工程を確認していきます。

## 一次情報

- [Introducing Agent 2.0](https://runway.com/news/introducing-agent-2) — Runway、2026年6月25日（最終確認：2026年8月12日）
- [Introducing Runway Agent](https://runway.com/news/introducing-runway-agent) — Runway、2026年5月13日（最終確認：2026年8月12日）
- [Inside Our Approach to Building Runway Agent](https://runway.com/news/engineering/inside-building-runway-agent) — Runway、2026年7月17日（最終確認：2026年8月12日）
- [Runway Pricing](https://runway.com/pricing?tool=runway) — Runway、公開日記載なし（最終確認：2026年8月12日）
- [How do credits work?](https://help.runwayml.com/hc/en-us/articles/15124877443219-How-do-credits-work) — Runway Help Center、公開日記載なし（最終確認：2026年8月12日）
- [API Changelog & Updates](https://docs.dev.runwayml.com/api-details/api_changelog/) — Runway Dev、継続更新（最終確認：2026年8月12日）
- [Can I use the content I made in Runway for commercial purposes?](https://help.runwayml.com/hc/en-us/articles/21668707517587-Can-I-use-the-content-I-made-in-Runway-for-commercial-purposes) — Runway Help Center、公開日記載なし（最終確認：2026年8月12日）
- [Terms of Use](https://runway.com/terms-of-use) — Runway、2026年5月11日更新（最終確認：2026年8月12日）
- [Runway's Usage Policy](https://runway.com/safety/usage-policy) — Runway、2026年3月6日更新（最終確認：2026年8月12日）

## 要確認事項

- Agent 2.0の各操作・完成動画・Variation生成に対する正確なCredit消費
- Agent 2.0そのものを外部Applicationから利用できるAPIの提供有無
- Agent 2.0で選択される内部Modelと、利用者がModelを固定できる範囲
- 広告Platformとの直接接続、自動取得、自動配信の提供時期（現時点では将来構想）
- 日本からの契約・支払い・機能提供に関する地域別の正式条件
- PlanごとのAgent固有の生成上限、同時実行数、Queue制限
- 個別の広告・Brand素材・人物・音楽を利用する際の権利と各媒体の審査条件
- 実アカウントでの日本語Prompt、字幕、Copy localizationの品質

## OmochiX編集部の見解

- Agent 2.0は「完成品を無確認で公開する仕組み」ではなく、分析・企画・試作・展開の往復を短くする制作パートナーとして使うのが現実的と判断しました。
- 複数案を比較し改善する業務で価値が出やすく、厳密な法務・Brand・人物・商品表現では人の確認を省けないと整理しました。
- 生成AIが単体素材の生成からWorkflow全体の支援へ広がっている一方、成果の評価と公開責任は人に残ると位置づけました。

## 内部リンク候補

- Target: `gemini-notebook-update-guide` / Anchor: 「AIリサーチでCreative briefを整理する方法」 / Placement: 「仕事ではどう使えそう？」末尾
- Target: `cursor-ipad-mobile-agent-guide` / Anchor: 「人が確認しながらAgentへ仕事を任せる流れ」 / Placement: 「AI Agentは制作をどこまで進められる？」末尾
- WordPress URL：未確定。公開時に実URLへ接続する

## 公開前チェック

- [ ] Human review
- [ ] Eyecatch
- [ ] WordPress preview
- [ ] Internal links
- [ ] Slim SEO
- [ ] Source links
- [ ] Credit / API / Region recheck
- [ ] Publish date

## 修正履歴

- 2026-08-12：一次情報を基に公開前初稿を作成。未確認事項を分離。
- 2026-08-12：SEO title・投入情報・内部リンク候補を整理し、未確認のCredit / API / 日本条件を維持。
