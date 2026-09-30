# AI Tools Content Batch 01 — Research Record / Sources

作成日：2026-10-01
対象：Veo / Kling AI / Runway / Midjourney / Claude Code / Cursor / Perplexity / Gemini / ChatGPT / NotebookLM（10件）
成果物：`scratchpad/ai-tools/batch-01/ai-tools-content-batch-01.json`（AI Tool Content Updater入力）
対象外：Sora（提供終了のためREAD ONLY監査のみ。`scratchpad/ai-tools/batch-01/SORA-READONLY-AUDIT.md`）

## この文書の読み方

- **確認者**：`A` = 2026-09-30の前回セッションのresearch agent（セッション制限で中断。取得結果は `scratchpad/ai-tools/batch-01/recovered/agent-transcripts/` に回収済み）／ `R` = 2026-10-01の復旧セッションで公式ページを直接確認。
- 一次情報（公式サイト・公式ドキュメント・公式ヘルプ・公式規約）のみを事実の根拠とした。第三者サイトの数値は採用していない。
- openai.com / perplexity.ai / midjourney.com / help.runwayml.com など通常のfetchが403になるページは、復旧セッションでブラウザから直接表示して確認した（`R`）。
- 料金は確認時点の公式表示。通貨・税の扱いは各ツールの欄に明記。
- 詳細な取得ログ：`scratchpad/ai-tools/batch-01/research/batch-A-*.md` / `batch-B-*.md` / `batch-C-*.md`。

## 判定サマリー

| ツール | slug（本番ID） | has_free_plan | api_available | commercial_use | japanese_support | 本番現行値からの変更点 |
|---|---|---|---|---|---|---|
| Veo | veo (678) | false | yes | partial | partial | japanese_support full→partial、api/commercial unknown→確定 |
| Kling AI | kling-ai (677) | true | yes | partial | partial | has_free_plan false→true、api/commercial unknown→確定 |
| Runway | runway (675) | true | yes | yes | partial | has_free_plan false→true、api/commercial unknown→確定 |
| Midjourney | midjourney (59) | false | no | partial | partial | japanese_support full→partial、api/commercial unknown→確定 |
| Claude Code | claude-code (665) | false | yes | yes | partial | api/commercial unknown→確定 |
| Cursor | cursor (65) | true | yes | yes | partial | api/commercial unknown→確定 |
| Perplexity | perplexity (63) | true | yes | partial | full | commercial unknown→partial |
| Gemini | gemini (61) | true | yes | yes | full | enum変更なし |
| ChatGPT | chatgpt (53) | true | yes | yes | full | enum変更なし |
| NotebookLM | notebooklm (659) | true | partial | yes | full | has_free_plan false→true、api/commercial unknown→確定 |

---

## 1. Veo

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| 公式 | https://deepmind.google/models/veo/ | 概要、提供経路、SynthID透かし、安全対策 | A / 2026-09-30 |
| 料金（API） | https://ai.google.dev/gemini-api/docs/pricing | Veo 3.1 $0.40/秒（720p/1080p）・$0.60（4K）、Fast $0.10/$0.12/$0.30、Lite $0.05/$0.08。動画生成の無料枠なし | A / 2026-09-30 |
| 料金（アプリ） | https://gemini.google/subscriptions/ | 無料¥0（動画生成の記載なし）、Google AI Plus ¥725（動画生成・Flowクレジット200）、Pro ¥2,900（同1,000）、Ultra ¥14,500/¥32,000（同10,000〜25,000） | R / 2026-10-01 |
| Docs | https://ai.google.dev/gemini-api/docs/veo | モデルID（veo-3.1-*-preview）、SDK（Python/JS/Go/Java/REST）、4/6/8秒、720p/1080p/4K、16:9・9:16、参照画像3枚、「英語以外のプロンプトは未評価」、Veo 3.0系はdeprecated、地域別の人物生成制限 | A / 2026-09-30 |
| Help | https://support.google.com/labs/answer/16353544 | Flowの対応言語・国、日本ではAIクレジット追加購入不可 | A / 2026-09-30 |
| 規約 | https://cloud.google.com/terms/genai-preview-products | Preview製品の本番・商用利用制限（全文は取得できず） | A / 2026-09-30 |
| リリース | blog.google（Gemini Omni発表、I/O 2026） | Gemini Omni Flashが消費者向け動画生成・編集に展開 | A / 2026-09-30 |

- 日本語対応：Geminiアプリ・FlowのUIは日本語、公式APIドキュメントは英語プロンプト以外未評価と明記 → **partial**。
- 商用利用：全モデルIDがpreview表記で、Preview製品規約の対象となる可能性 → **partial**。
- 未確認：Vertex AIの単価表、プラン別に使えるVeoモデルと生成回数。

## 2. Kling AI

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| 公式 | https://kling.ai/ ・ https://kling.ai/ja | klingai.comはkling.aiへ301転送。日本語のナビゲーションあり | A / 2026-09-30 |
| 公式 | https://kling.ai/llms.txt | モデル構成（VIDEO 3.0 / 3.0 Omni / O1 / 2.6、IMAGE 3.0 / 3.0 Omni / O1）、音声対応言語（英・中・日・韓・西）。Kling 4.0は存在しない | A / 2026-09-30、R / 2026-10-01 |
| 料金 | https://kling.ai/app/membership/membership-plan | Basic無料、Standard $6.99→$8.80、Pro $25.99→$32.56、Premier $64.99→$80.96、Ultra $127.99→$159.99（初月→2か月目以降）、年払い34%割引、クレジット数 | A / 2026-09-30 |
| 料金（API） | https://kling.ai/dev/pricing | 動画 $0.084〜0.42/秒、画像 $0.028〜0.056/枚 | A / 2026-09-30 |
| 規約 | https://kling.ai/docs/user-policy | 4.6：無料ユーザーの生成物は書面の許可なく商用利用不可 | A / 2026-09-30 |
| ポリシー | https://kling.ai/docs/point-policy ・ https://kling.ai/docs/privacy-policy | クレジット有効期限、暗号化・シンガポール保管 | A / 2026-09-30 |
| API | https://kling.ai/document-api/quickStart/productIntroduction/overview | JWT認証のREST API、非同期タスク方式 | A / 2026-09-30 |
| アプリ | App Store / Google Play 公式リスティング | iOS・Androidアプリ | A / 2026-09-30 |

- 料金ページはJS描画のため、前回agentは同じ公式URLをリーダー経由で取得。復旧セッションでは料金の再取得は行っていない（`info_checked_date` は 2026-09-30）。
- 未確認：無料プランの固定クレジット量、アプリ本体とサポートの日本語対応、公式SDKの有無、第三者セキュリティ認証、円建て価格。

## 3. Runway

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| 料金 | https://runway.com/ja/pricing | Free（125クレジット1回限り）、Standard $15（年払い$12）、Pro $35（$28）、Max $95（$76）、クレジット数、繰り越し条件、モデル別クレジット消費 | A / 2026-09-30、R / 2026-10-01 |
| Help | https://help.runwayml.com/hc/en-us/articles/25563791920147 | 「Runway is only available in American English at this time」 | R / 2026-10-01 |
| Help | https://help.runwayml.com/hc/en-us/articles/24342920074131 | 他言語プロンプトは受け付けるが、ローカライズされたUIはまだない | R / 2026-10-01 |
| 公式 | https://runway.com/ja ・ https://runway.com/about | 日本語マーケティングサイト、Web/iOS/Android/ChatGPT連携、東京を含む拠点 | A / 2026-09-30 |
| ニュース | https://runway.com/news/runway-is-coming-to-japan | 2026-05-14、東京オフィス開設を発表 | A / 2026-09-30 |
| API | https://docs.dev.runwayml.com/ （ai-context.md / guides/models.md / guides/pricing.md） | REST API、SDK（@runwayml/sdk・runwayml）、1クレジット=$0.01、gen4.5 12クレジット/秒、モデル一覧 | A / 2026-09-30 |
| 規約 | https://runway.com/terms-of-use | 2026-05-11更新。Input/Outputの所有権を主張しない、Outputの商用利用を制限しない、学習等への利用ライセンス | A / 2026-09-30 |
| セキュリティ | https://runway.com/data-security | SOC 2 Type II、ISO/IEC 27001、TLS 1.2+、AES-256 | A / 2026-09-30 |
| アプリ | App Store公式リスティング | Runway Agentの説明 | A / 2026-09-30 |

- 日本語対応：マーケティングサイト・料金ページは日本語、アプリ本体は英語のみ → **partial**。
- 商用利用：公式規約はプランによる区別なく商用利用を制限しないと明記 → **yes**（第三者サイトの「無料プランは非商用」という記述は公式規約で確認できず不採用）。
- メモ：runwayml.comはrunway.comへ308転送。本番の `official_url` は旧ドメインのまま（Updater対象外）。

## 4. Midjourney

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| 料金 | https://docs.midjourney.com/hc/en-us/articles/27870484040333-Comparing-Midjourney-Plans | Basic $10 / Standard $30 / Pro $60 / Mega $120、年払い20%割引、Fast GPU時間、Relax、Stealth、追加GPU $4/時間、年商100万ドル超はPro/Mega | R / 2026-10-01 |
| Help | https://docs.midjourney.com/hc/en-us/articles/27870399340173-Free-Trials | 公式サイト・Discordに無料トライアルなし。niji・journeyアプリに限定的トライアル | R / 2026-10-01 |
| Help | https://docs.midjourney.com/hc/en-us/articles/27870375276557-Using-Images-Videos-Commercially | 作成物はユーザー所有（解約後も）。例外2点 | R / 2026-10-01 |
| Docs | https://docs.midjourney.com/hc/en-us/articles/32199405667853-Version | 既定V8.2（2026-07-24）、V8.1（2026-04-14、約4〜5倍高速、2K HD）、V8.0アルファ終了 | R / 2026-10-01 |
| Docs | https://docs.midjourney.com/hc/en-us/articles/33329261836941-Getting-Started-Guide | 加入→Createページ→Imagineバー→4枚生成 | R / 2026-10-01 |
| Docs | docs.midjourney.com（video / models / personalization） | 画像から動画、5秒〜最大21秒、480p既定・Standard以上でHD、Niji 7（2026-01-09）、Personalization | A / 2026-09-30 |
| 規約 | https://docs.midjourney.com/hc/en-us/articles/32083055291277-Terms-of-Service | 2026-05-27発効。自動化ツールによるアクセス禁止、公式APIの記載なし | A / 2026-09-30 |
| Docs | https://docs.midjourney.com/hc/en-us/articles/35577175650957-Draft-Conversational-Modes | Conversational mode：普段の言葉で伝えるとAIがプロンプトを書く。テキスト・音声対応、V7・V8.1対応。「You can even use Conversational mode in other languages!」（対応言語の一覧・日本語の明示はなし） | R / 2026-10-01 |
| 公式 | https://www.midjourney.com/explore | 日本語ロケールのブラウザでも英語表示（`lang=en`） | R / 2026-10-01 |

- 日本語対応：公式ドキュメントがConversational modeを英語以外の言語でも利用できると案内している一方、公式サイト・ヘルプは英語のみで、通常プロンプトを日本語で入力した場合の扱いは明記されていない。非英語で使える機能はあるが、サービス全体の日本語対応は公式に保証されていない → **partial**（本番現行値fullから変更。当初noneと判定したが、2026-10-01に編集部判断でpartialへ修正）。
- API：公式APIなし → **no**。`api_sdk_info` はJSONに含めていない。
- 商用利用：条件付き（年商100万ドル超はPro/Mega必須）→ **partial**。
- 不採用：検索要約にあった「既定はV7」「V8.1は4月30日」は公式Versionページと矛盾するため不採用。

## 5. Claude Code

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| 公式 | https://claude.com/product/claude-code | 製品概要 | A / 2026-09-30 |
| 料金 | https://claude.com/pricing | Freeは対象外。Pro 月払い$20・年払い月額$17相当、Max $100から（5倍/20倍）、Team $25/$20・$125/$100、Enterprise $20/シート＋API利用量。日本向け表示は消費税10%込み（Pro $22/$18、Max $110から） | R / 2026-10-01 |
| Docs | https://code.claude.com/docs/en/overview ・ /docs/ja/overview | 対応環境、インストール、日本語ドキュメント | A / 2026-09-30 |
| Docs | https://code.claude.com/docs/en/model-config | モデル（Opus既定、Sonnet、Haiku、Fableは明示選択） | A / 2026-09-30 |
| Docs | https://code.claude.com/docs/en/data-usage | データ保持、学習利用、ZDR、TLS | A / 2026-09-30 |
| API | https://code.claude.com/docs/en/agent-sdk/overview | Agent SDK（Python/TypeScript） | A / 2026-09-30 |
| 規約 | https://www.anthropic.com/legal/consumer-terms | 2025-10-08発効。Outputの権利をユーザーに譲渡。非商用限定は評価利用のみ | R / 2026-10-01 |
| 規約 | https://www.anthropic.com/legal/commercial-terms | Team/Enterprise/API | A / 2026-09-30 |

- 前回agentの「Max 20x 月額$200」「Enterpriseは個別見積もり」は復旧時の公式表示と一致せず不採用。
- 商用利用：Consumer Termsを直接確認できたため **yes**（前回agentはpartial）。
- 未確認：APIトークン単価、CLIのUIローカライズの有無（日本語対応はpartialのまま）。

## 6. Cursor

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| 料金 | https://cursor.com/pricing | Hobby無料、Pro $20、Pro+ 年払い$48（Proの3倍）、Ultra 年払い$160（Proの20倍）、Teams Standard $40、Teams Premium $120（年払い$96）、Enterpriseカスタム、超過分は後払い従量課金。日本語表示あり | R / 2026-10-01 |
| フォーラム | https://forum.cursor.com/t/ultra-plan-v-s-200-pay-as-you-go/144918 | Pro+ $60・Ultra $200（月払い） | A / 2026-09-30 |
| Docs | https://cursor.com/docs/models | 対応モデル一覧 | R / 2026-10-01 |
| API | https://cursor.com/changelog/sdk-release ・ 公式フォーラム | Cursor SDK（TypeScript/Python）、Cloud Agents API | A / 2026-09-30 |
| セキュリティ | https://cursor.com/security | AIUC-1、ISO/IEC 27001、ISO/IEC 42001、SOC 2 Type II、Privacy Mode | A / 2026-09-30 |
| 規約 | https://cursor.com/terms-of-service | 生成コードの権利はユーザーが保持、再販・競合モデル学習は禁止 | A / 2026-09-30 |
| フォーラム | forum.cursor.com（日本語UIの要望・不具合スレッド） | Cursor独自UIは未ローカライズ | A / 2026-09-30 |

- Pro+・Ultraの月払い価格は、復旧セッションでは年払い表示（$48・$160）のみ直接確認。月払い$60・$200は公式フォーラムの記載と年払い20%引きとの整合から採用。
- `supported_models` は代表例のみ（頻繁に入れ替わるため）。

## 7. Perplexity

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| 料金 | https://www.perplexity.ai/hub/pricing | 無料$0、Pro $20、Max $200、各プランの内容 | R / 2026-10-01 |
| 料金（法人） | https://www.perplexity.ai/hub/pricing?p=enterprise | Enterprise Pro $34/シート・月（年額請求）、Enterprise Max $271、SOC 2 Type II・HIPAA・GDPR・PCI DSS、学習不使用 | R / 2026-10-01 |
| 規約 | https://www.perplexity.ai/ja/hub/legal/terms-of-service | 最終更新2026-01-23。5.1 個人の非営利目的に限り利用を許可 | R / 2026-10-01 |
| API | https://docs.perplexity.ai/guides/pricing | Sonar各モデルの単価、Search API、Embeddings | A / 2026-09-30 |
| 公式 | https://www.perplexity.ai/comet | Comet：Mac / Windows / iOS / Android | R / 2026-10-01 |
| 公式 | https://www.perplexity.ai/hub/getting-started | 15以上のフロンティアモデル、Computerの概要 | R / 2026-10-01 |

- 本番現行の「Enterprise Pro 月額$40/年額$400」「Enterprise Max $325」「Education Pro $10」「Pro年額$200」は公式ページで再確認できず、確認できた金額に置き換えた。
- `supported_models` と `integrations` は個別名を再確認できなかったため **JSONに含めず、本番の現行値を維持**。
- 商用利用：個人向け規約が非営利目的に限定 → **partial**。

## 8. Gemini

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| 料金 | https://gemini.google/subscriptions/ | 無料¥0（3.6 Flash、3.1 Pro限定、Deep Research、Live、Canvas）、Plus ¥725、Pro ¥2,900、Ultra ¥14,500/¥32,000 と各内容 | R / 2026-10-01 |
| Help | https://support.google.com/gemini/answer/13594961 | 対応環境（Web・Android・iOS・macOS）、プライバシー | A / 2026-09-30 |
| API | https://ai.google.dev/gemini-api/docs ・ /docs/models ・ /docs/pricing | SDK、モデル一覧（3.8 Flash等）、単価、無料枠、Batch 50%割引 | A / 2026-09-30、R / 2026-10-01 |
| 規約 | https://policies.google.com/terms | 2026-07-30発効。生成したオリジナルコンテンツの所有権をGoogleは主張しない。AIモデル開発への利用等は禁止 | R / 2026-10-01 |
| ブログ | https://blog.google/intl/ja-jp/company-news/technology/google-ai-plus/ | Google AI Plus提供開始時（2026-01-28）は月額1,200円 | A / 2026-09-30 |

- 前回agentの取得結果に料金の矛盾（Plus ¥900/¥1,500など）があったが、公式プランページの直接確認値（¥725）を採用。
- `integrations` は本番の現行値（Connected Apps）を再確認できなかったため **JSONに含めず維持**。

## 9. ChatGPT

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| 料金 | https://chatgpt.com/ja-JP/pricing/ | 無料¥0、Go ¥1,400、Plus ¥3,000、Pro ¥16,800から、Business 標準¥3,050/¥3,850・プレミアム¥15,250/¥19,250、Enterpriseカスタム。モデル一覧、機能比較、セキュリティFAQ | R / 2026-10-01 |
| 規約 | https://openai.com/ja-JP/policies/row-terms-of-use/ | 2026-01-01発効。ユーザーがアウトプットを所有 | R / 2026-10-01 |
| API | developers.openai.com / platform.openai.com（検索経由） | Responses API、Agents SDK | A / 2026-09-30（公式ドメイン限定の検索要約。直接取得は403） |

- 前回agentはopenai.comが403で料金を「程度」表記にとどめていた。復旧セッションで日本向け料金ページを直接確認して置き換えた。
- 未確認：Proの3段階それぞれの価格、表示価格の税の扱い。

## 10. NotebookLM（Gemini Notebook）

| 区分 | URL | 確認した事実 | 確認者 / 日付 |
|---|---|---|---|
| Help | https://support.google.com/notebooklm/answer/16213268?hl=ja | Standard/Plus/Pro/Ultraの上限（ノートブック・ソース・チャット） | A / 2026-09-30、R / 2026-10-01 |
| 料金 | https://gemini.google/subscriptions/ | Google AI Plus ¥725、Pro ¥2,900、Ultra ¥14,500/¥32,000 | R / 2026-10-01 |
| Help | https://support.google.com/notebooklm/answer/16212820?hl=ja | Audio Overviewの対応言語、Interactiveモードは英語のみ | A / 2026-09-30 |
| Help | https://support.google.com/notebooklm/answer/16337734 | フィードバック送信時の人によるレビュー、Workspaceでの保護 | A / 2026-09-30 |
| ブログ | https://blog.google/innovation-and-ai/products/gemini-notebook/notebooklm-gemini-notebook/ | 2026-07-16、Gemini Notebookへ改称、コード実行の追加 | A / 2026-09-30 |
| ブログ | https://workspaceupdates.googleblog.com/2026/07/notebooklm-now-gemini-notebook.html | 改称、自動リダイレクト | A / 2026-09-30 |
| API | Google Cloud NotebookLM Enterprise docs（overview / api-notebooks） | Enterprise版のみREST API | A / 2026-09-30 |
| 規約 | https://policies.google.com/terms | 生成コンテンツの所有権をGoogleは主張しない | R / 2026-10-01 |

- API：個人向けに公開APIなし、Enterpriseのみ → **partial**。
- `supported_models` は一次情報で確認できなかったためJSONに含めていない。
- メモ：本番の `pricing_type=free` は実態（無料枠＋有料上位枠）と合わないが、`pricing_type` はこのUpdaterの書き込み対象外。wp-adminでの個別判断が必要。

---

## Updater対象外で、編集部の判断が必要な項目

| ツール | 項目 | 現行値 | 調査結果 |
|---|---|---|---|
| NotebookLM | pricing_type | free | 無料枠＋Google AIプランの上位枠（freemium相当） |
| NotebookLM | official_url / 表示名 | notebooklm.google.com / NotebookLM | 2026-07-16にGemini Notebookへ改称、URLは自動転送 |
| Veo | pricing_type | freemium | アプリの無料プランに動画生成なし、APIも無料枠なし（paid相当） |
| Kling AI | official_url | klingai.com | kling.aiへ301転送 |
| Runway | official_url | runwayml.com | runway.comへ308転送 |
| Midjourney | japanese_support（今回JSONでpartialに変更） | full | Conversational modeは英語以外の言語でも利用可、公式UI・ヘルプは英語のみ。partialで確定（2026-10-01） |
| Veo | japanese_support（今回JSONでpartialに変更） | full | UIは日本語、APIは英語プロンプト以外未評価 |
