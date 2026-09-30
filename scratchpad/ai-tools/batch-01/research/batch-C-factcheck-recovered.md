# Fact check — Batch C（RECOVERED分の再確認＋商用利用の根拠）

確認日：2026-10-01（JST）。RECOVEREDのツールは再調査せず、料金・日本語対応・API・商用利用の4点のみ公式ページで再確認した。

## Claude Code

- https://claude.com/pricing （日本からは https://claude.com/ja/pricing に転送、日本向け表示は「Prices include 10% JCT」）
  - Free $0：Claude Code「いいえ」
  - Pro：税別 月払い$20／年払い月額$17相当（$200一括）。日本向け税込表示は月払い$22／年払い月額$18相当（$220一括）。Claude Code「はい」
  - Max：税別 月額$100から（日本向け税込表示 $110から）。Proの5倍または20倍の使用量を選択。Claude Code「はい」
  - Team：スタンダードシート 年払い$20/月・月払い$25/月、プレミアムシート 年払い$100/月・月払い$125/月（税別、WebFetchで確認）
  - Enterprise：「シートあたり月額$20にAPIレートでの使用量を加算」「年払い」。SCIM、監査ログ、カスタムデータ保持、RBAC。
  - FAQ：ウェブ・デスクトップ・モバイル・Claude Codeの利用は同じ使用量プールから消費。Consoleアカウントで従量課金のAPIクレジットに切替可能。
  - ※前回agentの「Max 20x 月額200ドル」「Enterpriseは個別見積もり」は今回の公式ページ表示と一致しないため不採用。
- 商用利用：https://www.anthropic.com/legal/consumer-terms （Effective October 8, 2025）「we assign to you all of our right, title, and interest—if any—in Outputs」。非商用限定は評価目的の利用のみ。Team/Enterprise/APIは Commercial Terms。→ commercial_use = **yes**（前回agentのpartialから変更。根拠を一次情報で確認できたため）
- 日本語：公式ドキュメント日本語版あり（code.claude.com/docs/ja）、claude.comも日本語表示。CLI表示は英語中心 → partial 維持。

## Kling AI

- https://kling.ai/llms.txt 再取得：最新は Kling 3.0 シリーズ（2026年2月）。VIDEO 3.0 / 3.0 Omni / O1 / 2.6、IMAGE 3.0 / 3.0 Omni / O1。「Kling 4.0」は存在しない。音声は英語・中国語・日本語・韓国語・スペイン語。→ 前回レポートの内容どおり。

## NotebookLM（Gemini Notebook）

- https://support.google.com/notebooklm/answer/16213268?hl=ja ：製品名は「Gemini Notebook」。上限（変更される場合あり）
  - Standard（無料）：ノートブック100、ソース50/ノートブック、チャット50/日
  - Plus：200 / 100 / 200
  - Pro：500 / 300 / 500
  - Ultra（20TB）：500 / 500 / 2,500、Ultra（30TB）：500 / 600 / 5,000
- 料金：https://gemini.google/subscriptions/ → Google AI Plus ¥725/月、Pro ¥2,900/月、Ultra ¥14,500/月または¥32,000/月（Batch A参照）。
- 商用利用：https://policies.google.com/terms （Effective July 30, 2026）「Some of our services allow you to generate original content. Google won't claim ownership over that content.」禁止事項はAIモデル開発への利用、人が作成したと誤認させること等。→ commercial_use = yes
- API：個人向けに公開APIなし、NotebookLM Enterprise（Google Cloud）のみAPIあり → partial（前回レポートどおり）。

## Veo

- 料金：Gemini API単価は前回agentが https://ai.google.dev/gemini-api/docs/pricing で確認済み。Geminiアプリ側の価格は https://gemini.google/subscriptions/ で今回再確認（Plus ¥725で動画生成・Flowクレジット200、Pro ¥2,900でFlowクレジット1,000・Gemini Omni Flash、Ultra ¥14,500/¥32,000）。
  - ※前回レポートの「Google AI ProでVeo 3.1 Liteの限定的な試用」「UltraでVeo 3.1フル機能」というプラン別のVeoモデル割当は今回のページ表示では確認できず → 本文では「有料プランに動画生成とFlowクレジットが含まれる」に留める。
- 日本語：Geminiアプリ・FlowのUIは日本語対応だが、公式APIドキュメントは「英語プロンプト以外は評価していない」と明記。→ japanese_support = **partial**（production現行値 full から変更。要編集部確認）
- 商用利用：モデルIDがpreview表記のため partial（前回レポートどおり）。

## Gemini（追加）

- https://ai.google.dev/gemini-api/docs/models ：Stable = Gemini 3.8 Flash / 3.8 Live / 3.8 Live Extended Thinking / 3.7 Flash / 3.6 Flash / 3.5 Flash / 3.5 Flash-Lite / 3.1 Flash-Lite、Nano Banana 2 / Pro。Preview = Gemini 3.1 Pro、Gemini 3 Flash、Gemini Omni Flash。Deprecated = Gemini 2.0 Flash系、Imagen 4。
- 商用利用：上記 Google ToS（2026-07-30発効）→ yes 維持。

## ChatGPT（追加）

- https://openai.com/ja-JP/policies/row-terms-of-use/ （2026年1月1日発効）「お客様は、（a）インプットに対する所有権を保持し、（b）アウトプットを所有します」。ChatGPT Enterprise・API等は事業者用取引規約。→ commercial_use = yes
