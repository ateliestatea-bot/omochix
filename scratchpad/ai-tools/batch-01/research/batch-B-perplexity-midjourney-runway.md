# Gap research — Batch B（Perplexity / Midjourney / Runway）

確認日：2026-10-01（JST）。復旧セッションでブラウザから公式ページを直接表示して確認。前回agentの取得結果（`recovered/agent-transcripts/*.raw-tool-results.txt`）のうち公式ドメイン由来のものは併用。

## Perplexity

- 料金（個人）：https://www.perplexity.ai/hub/pricing （日本語表示、USD）
  - 無料 $0/月：出典付き検索、基本的なAIモデル、ウェブ回答・ファイルアップロード・アセット生成は「制限あり」
  - Pro $20/月：Computerへの拡張アクセス、4,000ボーナスクレジット、深い研究（Deep Research）、トップAIモデルの選択、400以上のアプリコネクタ、上限10倍
  - Max $200/月：Computerの最大利用量、月間10,000クレジット＋35,000ボーナスクレジット、Model Council、ブレイン、動画生成5倍、新機能への優先アクセス
  - FAQ：「ChatGPT、Google Gemini、Anthropic Claude、NVIDIA Nemotron など」を統合。Pro/Maxで優先モデルを選択可。Pro/MaxでPitchBook・Statista・S&P Capital IQ等のプレミアムデータソース。
- 料金（法人）：https://www.perplexity.ai/hub/pricing?p=enterprise
  - Enterprise Pro：$34/月・シート（年額請求の場合）。前回agentが二次情報で得た「月額払い$40」は公式ページ上では今回未表示のため、**年額請求時の$34のみ採用**。
  - Enterprise Max：$271/月・シート（年額請求の場合）。
  - SOC 2 Type II、HIPAA、GDPR、PCI DSS準拠、SSO、データを学習に使用しない。SCIM・監査ログ・データ保持設定・RBACは「契約が必要」（カスタム価格）。
  - ※production現行値（Enterprise Pro 月額40ドル/年額400ドル、Enterprise Max 月額325ドル/年額3,250ドル、Education Pro 月額10ドル）は今回の公式ページでは確認できず → 確認できた年額請求時の単価に置き換える。
- 利用規約：https://www.perplexity.ai/ja/hub/legal/terms-of-service （日本語版あり、最終更新日2026年1月23日）
  - 5.1「お客様個人の非営利目的での利用に限り、本サービスの利用を許可します」
  - 5.2(e)「営利目的で本サービスを悪用すること（商業広告や勧誘の伝達や促進を含む…）」を禁止
  - Enterprise・開発者向けは別規約（「Enterprise および開発者向け規約」）。→ commercial_use = **partial**
- API：https://docs.perplexity.ai/guides/pricing （前回agent取得）
  - Sonar $1/$1、Sonar Pro $3/$15、Sonar Reasoning Pro $2/$8、Sonar Deep Research $2/$8（100万トークンあたり入力/出力）＋検索コンテキストに応じたリクエスト料金。Search API $5/1,000リクエスト。Embeddings $0.004〜/100万トークン。
- Comet：https://www.perplexity.ai/comet 「Available for Mac, Windows, iOS, and Android」
- 日本語：料金ページ・利用規約・はじめにページが日本語で表示されることを確認（サイト/規約のローカライズあり）。→ full を維持。
- 未確認：現時点で選択できる個別モデル名の一覧（production現行値は2026-09-24編集分。今回は再確認できず、JSONではキーを省略し現行値を維持）。

## Midjourney

- プラン：https://docs.midjourney.com/hc/en-us/articles/27870484040333-Comparing-Midjourney-Plans
  - Basic $10/月（年$96）・Fast 3.3時間/月、Standard $30（年$288）・15時間＋Relax画像無制限、Pro $60（年$576）・30時間＋Relax画像/SD動画無制限＋Stealth、Mega $120（年$1,152）・60時間。年払い20%割引。追加GPU時間 $4/時間。動画：BasicはSD、Standard以上はSD & HD。
  - Usage Rights：全プラン「General Commercial Terms」。年間総収入100万ドル超の企業はProまたはMegaが必要。
- 無料トライアル：https://docs.midjourney.com/hc/en-us/articles/27870399340173-Free-Trials
  - 「No free trial is currently available in Discord or the midjourney.com website」。niji・journeyアプリ（iOS/Android）に限定的なトライアルあり。→ has_free_plan = false
- 商用利用：https://docs.midjourney.com/hc/en-us/articles/27870375276557-Using-Images-Videos-Commercially
  - 作成した画像・動画は解約後もユーザーが所有。例外：他ユーザーの画像のアップスケール、年商100万ドル超の企業はPro/Megaが必要。→ commercial_use = **partial**（条件付き）
- ToS：https://docs.midjourney.com/hc/en-us/articles/32083055291277-Terms-of-Service （Effective May 27, 2026、前回agent取得）自動化ツールによるアクセス禁止、公式APIの記載なし。→ api_available = **no**
- モデル：https://docs.midjourney.com/hc/en-us/articles/32199405667853-Version
  - 既定はV8.2（2026-07-24リリース。新Edit ModelがOmni Reference・Character Reference・Retextureを置き換え）。V8.1は2026-04-14リリース（6/10〜7/23の既定、標準ジョブが従来の約4〜5倍高速、2K HD画像）。V8.0はアルファ版で7/24に終了。V7・V6も選択可。Niji 7（2026-01-09、前回agent取得）。
  - ※前回の検索要約にあった「V8.1は4月30日」「既定はV7」は誤り。公式Versionページの記載を採用。
- 動画：https://docs.midjourney.com/docs/video （前回agent取得）image-to-video、5秒開始・最大4回延長で21秒、既定480p、Standard以上でHD(720p)、Web・Discordで利用可。
- 利用環境：midjourney.com（Web、モバイルブラウザ対応）とDiscord。
- 日本語：**partial**（2026-10-01、編集部判断を受けて none から修正）
  - https://docs.midjourney.com/hc/en-us/articles/35577175650957-Draft-Conversational-Modes （2026-10-01確認）：Conversational modeは「describe your ideas and images in normal, conversational language to an AI that will write prompts for you」、テキストと音声の2通り、V7・V8.1対応、「You can even use Conversational mode in other languages!」。対応言語の一覧や日本語の明示はなし。
  - midjourney.com は日本語ロケールのブラウザでも英語表示（`lang=en`）、ヘルプセンターも英語のみで言語切替なし。
  - Imagineバーへ直接入力する通常プロンプトを日本語で書いた場合の扱いは、公式ドキュメントに明記なし。
  - → 非英語で使える機能はあるが、UI・通常プロンプトを含む全体の日本語対応は公式に保証されていないため partial。

## Runway

- 料金：https://runway.com/ja/pricing （日本語表示、USD）
  - Free $0：125クレジット（1回限り）、5GB
  - Standard $15/月（年払い$12/月）：625クレジット/月、ウォーターマークなし、4Kアップスケール、並列5、3プロジェクト、20GB
  - Pro $35/月（年払い$28/月）：2,250クレジット/月、並列15、Brand Kit 1、カスタムボイス1、Runway MCP、100GB
  - Max $95/月（年払い$76/月）：9,500クレジット/月、1か月繰り越し、並列20、HDR/ProRes、500GB
  - クレジット：Gen-4.5は1秒12クレジット、Gen-4 Image 8クレジット/枚(1080p)、TTS 1クレジット/50文字。追加購入は1,000クレジットから、購入分は無期限。Standard/Proは繰り越しなし。
  - 掲載モデル：Gen-4.5、Aleph 2.0、Seedance 2.5/2.0、Kling 3.0、Nano Banana Pro、Seed Audio 1.0、Lyria 3 など。
- 日本語：
  - https://help.runwayml.com/hc/en-us/articles/25563791920147 「Runway is only available in American English at this time」（Chromeの翻訳機能を案内）
  - https://help.runwayml.com/hc/en-us/articles/24342920074131 モデルは他言語プロンプトを受け付けるが「we do not yet have a localized interface」
  - 一方 runway.com/ja（マーケティングサイト・料金ページ）は日本語化済み。2026-05-14に東京オフィス開設を発表（https://runway.com/news/runway-is-coming-to-japan）。→ japanese_support = **partial**
- API：https://docs.dev.runwayml.com/ （前回agent取得）Base URL api.dev.runwayml.com、SDK `@runwayml/sdk`（Node）/`runwayml`（Python）、1クレジット=$0.01、gen4.5は12クレジット/秒。→ api_available = yes
- 商用利用：https://runway.com/terms-of-use （Last updated May 11, 2026、前回agent取得）「Company does not claim ownership of any of your Inputs or Outputs」「does not restrict your commercial use of your Outputs」。Input/Outputをモデル学習等に利用するライセンスをRunwayに付与。プランによる区別の記載なし。→ commercial_use = yes（学習利用ライセンスは注意点として明記）
- セキュリティ：https://runway.com/data-security SOC 2 Type II、ISO/IEC 27001、TLS 1.2+、AES-256。
- アプリ：フッターに「Android app」「iOS app」リンク、https://runway.com/about にも Web / Android / iOS / ChatGPT連携の記載。
- official_url：runwayml.com は runway.com へ308リダイレクト（本Updaterの対象外フィールド。編集部判断）。
