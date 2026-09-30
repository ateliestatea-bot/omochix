# AI Tools Batch 01 — Recovery Audit

作成日：2026-09-30（復旧セッション）
branch：`feature/ai-tools-content-batch-01`（HEAD `1850457`、working tree clean、stash は `On main: WIP before Learn MVP` の1件のみで本件と無関係）

## 1. 何が残っていたか

repo内（`scratchpad/ai-tools/batch-01/`、`docs/content-research/`）は **空ディレクトリのみ**（18:34作成）。前回セッションは成果物をrepoへ書き出す前に終了していた。

repo外に以下が残存していたため、`scratchpad/ai-tools/batch-01/recovered/` へ **コピー**（元ファイルは未変更・未削除）：

| 回収元 | 内容 | 回収先 |
|---|---|---|
| 前回セッションscratchpad `ai-tools-batch01/` | production REST snapshot（全52ツール一覧、対象10件+Sora+HeyGenの完全レコード、18:17〜18:20取得） | `recovered/prod-snapshot/` |
| 同上 | `claude-code_result.md`（Claude Code agentの最終レポート） | `recovered/claude-code_result.md` |
| 前回セッションscratchpad `heygen/` | HeyGenのUpdater入力JSON（品質基準） | `recovered/heygen-reference/` |
| 前回セッションのsubagent transcript 10本 | 各agentへの指示、取得した全WebSearch/WebFetch結果、最終レポート（存在する場合） | `recovered/agent-transcripts/` |

cookieファイル等の認証情報を含む可能性のあるファイルはコピーしていない。

## 2. 分類

| ツール | slug | agent終了状態 | 分類 | 理由 |
|---|---|---|---|---|
| Veo | veo | 正常終了・最終レポートあり | RECOVERED | 公式ソース付き。Vertex AI単価・preview規約は未確認と明記済み |
| Kling AI | kling-ai | 最終レポートあり | RECOVERED | 公式料金・API・規約を確認済み |
| Claude Code | claude-code | 正常終了・最終レポートあり | RECOVERED | 公式ソース付き |
| NotebookLM | notebooklm | 正常終了・最終レポートあり | RECOVERED | 公式ソース付き。料金の具体額のみ未記載（要補完） |
| Cursor | cursor | 正常終了・最終レポートあり | PARTIAL | Pro+/Ultra価格が公式フォーラム経由の間接確認のみ。モデル名未掲載 |
| ChatGPT | chatgpt | 最終レポートあり | PARTIAL | openai.comが全て403で、検索要約のみに依拠。料金が「程度」表記、モデル名が曖昧 |
| Midjourney | midjourney | **session limitで中断・レポートなし** | PARTIAL | 公式docs（プラン・モデル・動画・ToS）の取得結果は残存。統合・執筆が未実施 |
| Runway | runway | **session limitで中断・レポートなし** | PARTIAL | 公式料金・API・ToS・セキュリティの取得結果は残存。統合・執筆が未実施 |
| Perplexity | perplexity | **session limitで中断・レポートなし** | PARTIAL | 公式ページが403。API料金のみ公式確認。消費者向け料金・規約は二次情報のみ |
| Gemini | gemini | **session limitで中断・レポートなし** | PARTIAL | 料金が取得ごとに矛盾（Plus ¥725/¥900/¥1,200/¥1,500）。モデル世代も不確定 |

MISSING：なし。

## 3. 再開方針

- RECOVERED 4件は再調査しない（最終fact checkで料金・日本語・API・商用利用のみ再確認）。
- PARTIAL 6件は不足部分のみ調査。2〜3件ずつ処理し、バッチごとに `docs/content-research/` へ保存する。
