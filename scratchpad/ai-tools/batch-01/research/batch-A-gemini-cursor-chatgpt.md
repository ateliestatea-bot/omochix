# Gap research — Batch A（Gemini / Cursor / ChatGPT）

確認日：2026-09-30〜10-01（JST）。復旧セッションで公式ページを直接確認した結果のみ記載。

## Gemini

Source: https://gemini.google/subscriptions/ （日本向け表示、JPY）

| プラン | 価格 | 内容（ページ記載） |
|---|---|---|
| 無料 | ¥0/月 | 3.6 Flash へのアクセス、3.1 Pro への限定アクセス、画像生成・編集、Deep Research、Gemini Live、Canvas、ストレージ15GB |
| Google AI Plus | ¥725/月 | 無料版の2倍の利用上限、動画生成、Flowクレジット200、Notebook上限5倍、Gemini in Gmail/ドキュメント、400GB |
| Google AI Pro | ¥2,900/月 | 無料版の4倍の利用上限、Gemini 3.1 Pro・Deep Research・エージェント機能、Flowクレジット1,000、Gemini Omni Flash、YouTube Premium Lite、5TB |
| Google AI Ultra | ¥14,500/月（Proの5倍）または ¥32,000/月（Proの20倍） | Deep Think、Gemini Spark、Flowクレジット10,000〜25,000、Project Genie、YouTube Premium、20TB〜 |

- 前回agentの取得値（Plus ¥900 / ¥1,500 / ¥1,200）はWebFetch要約の誤読または旧価格。¥1,200は2026-01-28提供開始時の価格（blog.google/intl/ja-jp）、2026年6月に¥725へ値下げ。**採用値は上表**。
- 対応デバイス：https://support.google.com/gemini/answer/13594961 → Web（gemini.google.com）、Android、iOS、macOSアプリ、ほかAndroid Auto / Chrome / Android XR等。
- Gemini API：https://ai.google.dev/gemini-api/docs / https://ai.google.dev/gemini-api/docs/pricing → 無料枠＋従量課金。Gemini 3.8 Flash 入力$0.75/出力$3.75（100万トークン、2026-12-31まで。2027-01-01以降$1.50/$7.50）、Gemini 3.5 Flash-Lite $0.30/$2.50。SDK：Python/JavaScript/Java/Go/REST。
- データ：個人アカウントはGemini Apps Privacy Notice適用（人によるレビュー・改善利用あり）。Workspace対象エディションはレビュー・学習利用なし（support.google.com/a/answer/14130944 系）。
- 未確認：アプリ内で選べるモデルの完全な一覧（無料=3.6 Flash、3.1 Pro は確認済み）。

## Cursor

Source: https://cursor.com/pricing （ブラウザで月額/年額・各タブを実際に切替えて確認。サイトは日本語表示あり）

| プラン | 月額払い | 年額払い（月あたり） | 備考 |
|---|---|---|---|
| Hobby | Free | — | クレジットカード不要、制限付きAgent、Composerアクセス |
| Pro | $20/mo | （未取得、20%引き相当と推定されるが未記載とする） | Agent上限拡張、フロンティアモデル、MCP/スキル/フック、クラウドエージェント、従量課金のBugbot |
| Pro+ | $60/mo（公式フォーラム＋年額$48との整合で確認） | $48/mo | Proの3倍のAgent利用上限 |
| Ultra | $200/mo（同上） | $160/mo | Proの20倍のAgent利用上限、新機能への優先アクセス |
| Teams Standard | $40/user/mo | — | 一元請求・管理、チームマーケットプレイス、Bugbot、使用状況分析、チーム全体のプライバシーモード、SAML/OIDC SSO |
| Teams Premium | $120/user/mo | $96/user/mo | Standardの5倍のAgent利用上限 |
| Enterprise | カスタム | — | 使用量プール、請求書/PO、SCIM、アクセス制御、監査ログ、AIコード追跡API |

- 「含まれる利用量を超えた分はオンデマンド利用として後払い課金」（pricing FAQ）。
- 料金ページ・フッターに「SOC 2 | ISO27001 | ISO42001 | AIUC-1認証取得済み」。
- モデル：https://cursor.com/docs/models → Anthropic（Claude Opus 5.5 / Sonnet 5.5 / Fable 5.1 ほか）、OpenAI（GPT-5.6 Sol/Terra/Luna ほか）、Google（Gemini 3.8 Flash / 3.1 Pro ほか）、Cursor（Composer 2.5、Grok 4.7）、Moonshot（Kimi K3）、Z.ai（GLM 5.3）、Meta（Muse Spark 1.3）。ラインナップは頻繁に変わるため本文ではプロバイダー単位＋代表例に留める。
- 日本語：cursor.com のマーケティングサイトは日本語表示に対応（今回確認）。エディタ本体UIはCursor独自部分が英語（前回agentが公式フォーラムで確認）。→ japanese_support=partial を維持。

## ChatGPT

Source: https://chatgpt.com/ja-JP/pricing/ （ブラウザで直接表示を確認。個人向け／ビジネス・エンタープライズ向け両タブ）

| プラン | 価格（日本向け表示） | 内容（ページ記載） |
|---|---|---|
| 無料版 | ¥0/月 | GPT-5.6 Luna でのテキストチャット無制限、アップロード・画像作成・音声・Deep Research・メモリ・Codexに上限、デスクトップでのChatGPTワークへ制限付きアクセス |
| Go | ¥1,400/月 | ツール利用メッセージ・アップロード・画像生成・音声の拡大、より長いメモリ。広告が表示される場合あり |
| Plus | ¥3,000/月 | GPT-6による高度なリーズニングモデル、上限拡大、Deep Research拡張、プロジェクト・スケジュール済みタスク・カスタムGPT、Codex上限拡大 |
| Pro | 月額¥16,800から（3段階の利用枠） | GPT-6 AstraによるPro推論、Codex/ChatGPT Workの長時間セッション、常時稼働エージェントDot、Deep Research最大活用 |
| Business 標準シート | ¥3,050/月（年額課金）／¥3,850/月（月額課金） | ChatGPT・ChatGPT Work・Codex全機能、Google Workspace/Slack/GitHub/Microsoft 365連携、SAML SSO/MFA、既定でビジネスデータを学習に使用しない。2〜200名向け |
| Business プレミアムシート | ¥15,250/月（年額課金）／¥19,250/月（月額課金） | 標準シートの5倍の利用枠 |
| Enterprise | カスタム価格 | SCIM、EKM、RBAC、カスタムデータ保持、10地域のデータレジデンシー、優先サポート、SLA |

- 比較表のモデル欄：GPT-6.1 Sol、GPT-6 Astra、GPT-6 Sol、GPT-6 Luna、GPT-5.6 Sol、GPT-5.6 Sol Pro、GPT-5.6 Terra、GPT-5.6 Luna、GPT-5 Thinking Mini、レガシーモデル。
- 対応：「ウェブ、iOS、Android でアクセス」＋デスクトップアプリ。
- セキュリティ（同ページFAQ）：転送時TLS 1.2、保存時AES-256、SOC 2 Type 2、ISO 27001/27017/27018/27701、データレジデンシー（米国・EU・英国・日本・カナダ・韓国・シンガポール・インド・オーストラリア・UAE）。無料〜Proは「コンテンツをモデルの学習に使用：無効化可能」。
- FAQ：「Go、Plus、Businessには月額プラン、BusinessとEnterpriseには年間プラン」。
- 未確認：Proの3段階それぞれの価格（「月額¥16,800から」のみ確認）。help.openai.comは403のため未読。
