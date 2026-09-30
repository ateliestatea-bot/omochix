# Sora — READ ONLY監査（提供終了ツール）

確認日：2026-10-01
方法：公開REST APIへのGETのみ（書き込みなし）。SoraはBatch 01のJSONに含めていない。

## 1. 現在のproduction投稿

| 項目 | 値 |
|---|---|
| post ID | 679 |
| slug | sora |
| status | publish |
| 最終更新 | 2026-09-27T16:29:17 |
| title | Sora |
| tool_status | **active**（「提供中」と表示される） |
| company_name | OpenAI |
| official_url | https://sora.com/ |
| pricing_type | paid |
| has_free_plan | false |
| japanese_support | full |
| rating_overall | 4.5 |
| display_order | 28 / is_featured false |
| api_available / commercial_use | unknown / unknown |
| post_content | 空 |
| tool_logo / featured_media | 未設定 |
| SEO title | 「Sora - OmochiX」 |

current description（short_description、meta descriptionも同文）：

> 文章や画像をもとに映像を生成し、絵コンテ、リミックス、延長などの機能でアイデアを動画へ展開できるAI。生成した動画をアプリ内で共有・編集する体験も備える。

v2の構造化フィールド（key_features、pros、cons、pricing_details、notes、info_checked_date など）はすべて空。

## 2. 公式の状況（一次情報）

- https://sora.com/ → `sora.chatgpt.com/sunset` に転送され、「Sora is no longer available. You can export your data at any time.」と表示される（2026-10-01確認）。
- https://help.openai.com/en/articles/20001152-what-to-know-about-the-sora-discontinuation （openai.com/sora から転送）
  - 「The Sora web and app experiences were discontinued on April 26, 2026.」
  - 「The Sora API will be discontinued on September 24, 2026.」
  - 作成済みコンテンツはエクスポート可能。最終エクスポート期間の終了後、Soraの利用に関するデータは完全に削除される。

→ Web・アプリ・APIのいずれも提供終了済み。現在のページは「提供中」の有料動画生成AIとして現在形で紹介しており、事実と異なる。

## 3. related content

| 種別 | 対象 | 内容 |
|---|---|---|
| 投稿の関連フィールド | related_learn_ids / related_news_ids / related_lab_ids / related_compare_ids | すべて空 |
| taxonomy | カテゴリ1件、機能2件、タグ3件、プラットフォーム3件 | 動画生成カテゴリのアーカイブ・一覧に「提供中」のツールとして並ぶ |
| サイト内検索「Sora」 | プロンプト（ID 796、slug `video-text-to-video-shot-prompts`） | 「使うツール」の入力例に「Sora、Veo、Runway」と記載 |
| repo | `wp-plugin/omochix-core/sample/seed-ai-tools.csv` 29行目 | Soraのseed行（tool_status等は現行本番と同じ内容） |
| repo | `wp-plugin/omochix-core/sample/prompts-library-100.csv` 2984行目 | 上記プロンプトの原稿 |
| repo | `docs/content-drafts/Prompts-Library-100-Review.md` 237行目 | 同プロンプトの関連ツールに `sora` |
| 今回のBatch 01 | 本文中 | Soraへの言及なし（Veoの `omochix_view` からも削除済み） |

## 4. 提供終了の反映方法（提案のみ・未実施）

推奨：**ページは残し、提供終了の記録ページに切り替える**。削除や非公開にすると、既存URLへの流入と「Soraは今どうなったのか」という検索意図の受け皿を失う。

1. `tool_status` を `discontinued` に変更する。テーマ側に「提供終了」ラベルは実装済み（`single-ai_tool.php`）。
   - **この項目はContent Updaterでは書き込めない**（schema fieldsに含まれない）。wp-adminの投稿編集画面、またはCSVインポーター経由での変更になる。
2. 文面を過去形の記録に改める（Content Updaterで対応可能な範囲）。
   - `short_description`：「OpenAIが提供していた動画生成AI。Web・アプリ版は2026年4月26日、APIは2026年9月24日に提供を終了した。」のような事実ベースの文。
   - `post_content`：概要／提供終了の経緯と日付／データのエクスポートについて／代替として検討できるツール（Veo、Runway、Kling AIへの内部リンク）。
   - `notes`：公式ヘルプの出典と確認日。`info_checked_date`：確認日。
   - `pricing_details`：「提供終了のため新規契約不可」。
   - `seo_title` / `meta_description`：「Soraとは？提供終了の経緯と代替ツール」の趣旨に変更。
3. `rating_overall`（4.5）と `display_order` の扱いを編集部で決める。提供終了ツールに評価スコアと通常の並び順を残すかどうかは編集判断（Updater対象外）。
4. 構造化データ：`SoftwareApplication` スキーマを提供終了ツールに出力し続けるかを確認する。出力を止める、または内容を見直す場合はコード変更が必要なため、別タスクとして扱う。
5. 一覧・カテゴリページ：提供終了ツールを通常の一覧に混在させるか、末尾へ回す／バッジを付けるかを決める（テーマ変更が必要な場合は別タスク）。
6. 関連コンテンツ：プロンプト（ID 796）の入力例から「Sora」を外す、または提供中のツールに置き換える。repoのseed CSVとプロンプトCSVも次回更新時に合わせる。

未決事項：上記2の本文は別バッチ（提供終了ツール用）として作成するのがよい。今回は提案のみで、productionもrepoのseedデータも変更していない。
