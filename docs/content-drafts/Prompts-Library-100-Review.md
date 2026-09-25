# Prompts Library 100 レビュー資料

作成日：2026年9月25日
対象ファイル：`wp-plugin/omochix-core/sample/prompts-library-100.csv`
状態：レビュー待ち（本番未投入）

OmochiXで公開する実用プロンプト100件のCSVと、その検証結果・内部リンク案をまとめた資料です。CSVはPhase 1のPrompt CSV Importer（`admin/prompt-csv-importer.php`）と完全互換で、列構成は `sample/sample-prompts.csv` と同一です。

## 1. 構成

| カテゴリ | 件数 | 初級 | 中級 | 上級 |
|---|---|---|---|---|
| sales（営業） | 15 | 4 | 7 | 4 |
| marketing（マーケティング） | 15 | 4 | 8 | 3 |
| development（開発） | 15 | 5 | 6 | 4 |
| writing（文章） | 15 | 8 | 4 | 3 |
| productivity（業務効率化） | 15 | 7 | 6 | 2 |
| documents（資料） | 10 | 5 | 4 | 1 |
| image（画像） | 8 | 2 | 5 | 1 |
| video（動画） | 7 | 3 | 3 | 1 |
| 合計 | 100 | 38 | 43 | 19 |

対応AI（prompt_model）の付与数：ChatGPT 89／Claude 79／Gemini 18／Image 7／Video 3

- 文章作成・分析中心のプロンプトは ChatGPT・Claude を基本とし、長文資料の読解・Google Workspace連携・画像の読み取り・リサーチが要となるものに Gemini を割り当てた。
- 3つのチャットAIすべてを付けたプロンプトはない（「何でも全モデル対応」にしない方針）。
- Image / Video は、画像・動画を生成するAIに入力するプロンプトにのみ付与。台本や構成を作るプロンプト（YouTube台本など）には付けていない。
- 関連AIツールは `sample/seed-ai-tools.csv` に実在する50件のslugのみを使用（使用37種）。

## 2. 作成方針

- 各プロンプトは「誰が・どんな場面で・何を完成させるか」が異なるように設計した。
- 本文は用途に合わせて ROLE / GOAL / CONTEXT / INPUT / PROCESS / RULES / OUTPUT / CHECK などを使い分け、同一テンプレートへの機械的な当てはめは避けた。
- 書き換える箇所は `{ }` の入力変数で示した。
- 出力例は架空の会社名・人物・数値のみを使用し、実在の顧客情報・個人情報は含まない。
- 法務・医療・広告表現・著作権・商標・個人情報に関わるものは、専門家や公式情報での確認を促す注意書きを本文または使い方に入れた。
- サンプル5件（`sample-prompts.csv`）と同じ検索意図のものは作っていない。

## 3. 検証結果

### 3.1 CSV検査（8バッチごと＋全体）

| 項目 | 結果 |
|---|---|
| slug重複（サンプル5件を含む） | 0 |
| title重複（サンプル5件を含む） | 0 |
| 空セル | 0 |
| slug形式違反 | 0 |
| 存在しないカテゴリ・対応AI | 0 |
| 存在しない関連AIツールslug | 0 |
| difficultyの不正値 | 0 |
| 入力変数 `{ }` のないprompt_body | 0 |
| prompt_bodyの文字数 | 最小364／中央値485／最大643 |
| prompt_bodyの類似度（文字3-gram Jaccard 0.30超） | 0組 |
| タイトルの類似度（文字2-gram Jaccard 0.45超） | 0組 |

意味的な重複は、タイトル＋抜粋のTF-IDF類似度の上位ペアを人が確認した。最大値は0.26で、上位ペアはいずれも成果物・利用場面が異なることを確認した（例：導入事例インタビュー〔質問設計を含むBtoB事例制作〕と、インタビュー文字起こしの記事化〔汎用〕）。

### 3.2 使い捨てWordPressでのインポート検証

本番ではなく、ローカルに一時的に構築したWordPress（WordPress 7.1 / Slim SEO 4.9.10 / PHP 8.5 / MySQL 8.4、AIツール50件を登録）で実施。

| 手順 | 結果 |
|---|---|
| dry-run | 対象100／新規100／エラー0／警告0、DBチェックサム変化なし |
| インポート | 新規作成100（すべて下書き）／エラー0 |
| DBの内容とCSVの突合 | 100件すべて一致（本文・使い方・出力例・難易度・カテゴリ・対応AI・関連AIツール） |
| 再dry-run | 変更なし100、DBチェックサム変化なし |

### 3.3 表示確認（テストDBで15件のみ公開して確認）

- 一覧・ページ送り・詳細・パンくず・難易度/対応AIバッジ・関連AIツール・description：正常
- 下書きはログアウト状態で非公開（404）
- カテゴリページ：公開2件のカテゴリはindex＋canonical、1件のカテゴリはnoindex（既存方針どおり）
- 対応AI（prompt_model）ページ：すべてnoindex（既存方針を維持）
- 一覧内検索・サイト内検索：本文中の語でもヒット
- AIツール詳細の「このツールで使えるプロンプト」：正常
- コピー：Desktop / Tablet / Mobile × Light / Dark で48回すべてクリップボードと本文が一致、横スクロール・JSエラーなし

## 4. SEOとの整合

- 各プロンプトは単独ページとして、タイトル・抜粋（description）・本文・使い方・出力例を持つ。
- カテゴリページのindex条件（公開2件以上）は、100件をすべて公開すれば全8カテゴリで満たす。段階的に公開する場合は、各カテゴリで2件目を公開するまでnoindexになる。
- prompt_model のnoindex方針、taxonomy slug、URL構造は変更していない。

## 5. 内部リンク設計案（コード変更なし・提案のみ）

1. **Prompts → AI Tools（今回構築済み）**：各プロンプトの関連AIツール（37種）を詳細ページのサイドバーに表示。AIツール詳細からは逆引きで最大6件を表示。
2. **AI Tools → Prompts の拡張**：ChatGPT・Claudeは60件以上のプロンプトと関連するため、6件表示では足りない。「このツールのプロンプトをすべて見る」導線が必要。ChatGPT / Claude / Gemini は既存の `/prompts/model/{slug}/` へ、その他のツールは将来の絞り込み機能へつなぐ案。（要コード変更）
3. **Prompts ⇄ AIツールカテゴリ**：プロンプトのカテゴリページから、対応するAIツールカテゴリ（例：画像 → 画像生成AIの一覧）へ誘導する。（要コード変更）
4. **News → Prompts**：ChatGPT・Claude・Geminiなどのアップデート記事の末尾に、そのツールで使える代表プロンプトを2〜3件、手動でリンクする。将来は記事側に関連プロンプトを選べる項目を追加する案。
5. **Learn → Prompts**：Learnハブには既に「プロンプト」カードがある。Learnの入門記事（AI入門・AI仕事術）から、初級のプロンプトへカテゴリ別に手動リンクする。
6. **Prompts → Learn / News**：プロンプト詳細の下部CTAに、関連するLearn記事や使い方記事への導線を追加する案。（要コード変更）
7. **公開順**：各カテゴリで2件以上を同時に公開し、カテゴリページをindex対象にしてから残りを段階的に公開すると、カテゴリページとプロンプト詳細の両方が評価されやすい。

## 6. 既知の注意点

- 本番環境のタームとAIツールslugは未確認。本番で実行するのはdry-run（DB変更なし）から始め、エラー0・警告0を確認すること。
- AIツールの機能・料金・提供状況は変わるため、本文で特定ツール名に触れているプロンプト（動画・画像・自動化系）は定期的に見直す。
- 対応AIの付与はChatGPT・Claudeに寄っている（文章中心のタスクが多いため）。Video付与は3件で、`/prompts/model/video/` の掲載数は少ない（noindexのため検索上の影響はない）。

## 7. プロンプト一覧

### 営業（sales）

| slug | タイトル | 難易度 | 対応AI | 関連AIツール |
|---|---|---|---|---|
| `sales-dormant-customer-reactivation-email` | 休眠顧客を掘り起こす再アプローチメール作成プロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `sales-cold-email-3-step-sequence` | 新規開拓のコールドメール3通シーケンス作成プロンプト | 中級 | chatgpt, claude | chatgpt, claude |
| `sales-objection-handling-talk-sheet` | よくある断り文句への切り返しトーク集作成プロンプト | 中級 | chatgpt, claude | chatgpt, claude |
| `sales-problem-solving-proposal-storyline` | 顧客課題から逆算する提案書ストーリー設計プロンプト | 中級 | claude, chatgpt | claude, chatgpt, gamma |
| `sales-competitor-battle-card` | 競合比較バトルカード作成プロンプト | 上級 | gemini, chatgpt | gemini, perplexity, chatgpt |
| `sales-account-research-from-ir-news` | IR資料とニュースから訪問先の経営課題を読み解くプロンプト | 中級 | gemini, claude | gemini, notebooklm, perplexity |
| `sales-post-meeting-thank-you-next-step` | 商談後のお礼と次回アクション確認メール作成プロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `sales-price-negotiation-preparation` | 値引き要求に備える価格交渉の準備シート作成プロンプト | 上級 | claude, chatgpt | claude, chatgpt |
| `sales-lost-deal-retrospective` | 失注案件の振り返りと改善策を整理するプロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `sales-pipeline-priority-review` | 案件パイプラインの確度見直しと優先順位付けプロンプト | 上級 | claude, chatgpt | claude, chatgpt, microsoft-copilot |
| `sales-roleplay-difficult-customer` | 商談ロールプレイの顧客役をAIに演じさせるプロンプト | 中級 | chatgpt, claude | chatgpt, claude |
| `sales-inside-sales-call-script` | インサイドセールスの架電トークスクリプト作成プロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `sales-upsell-opportunity-finder` | 既存顧客へのアップセル・クロスセル提案ポイント抽出プロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `sales-web-inquiry-first-reply` | Web問い合わせへの初回返信メールを温度感別に作るプロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `sales-rfp-requirements-compliance-matrix` | RFP（提案依頼書）の要件を読み解き対応可否表を作るプロンプト | 上級 | claude, gemini | claude, gemini, notebooklm |

### マーケティング（marketing）

| slug | タイトル | 難易度 | 対応AI | 関連AIツール |
|---|---|---|---|---|
| `marketing-competitor-differentiation-points` | 競合3社から自社の差別化ポイントを抽出するプロンプト | 中級 | gemini, chatgpt | gemini, perplexity, chatgpt |
| `marketing-persona-insight-from-reviews` | 顧客レビューからペルソナと購買インサイトを抽出するプロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `marketing-x-monthly-post-calendar` | X（旧Twitter）1か月分の投稿カレンダー作成プロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `marketing-instagram-carousel-post` | Instagram保存されるカルーセル投稿の構成作成プロンプト | 初級 | chatgpt, claude | chatgpt, canva-ai |
| `marketing-newsletter-with-subject-lines` | 開封される件名付きメールマガジン作成プロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `marketing-search-ads-headlines-descriptions` | リスティング広告の見出しと説明文を作成するプロンプト | 中級 | chatgpt, gemini | chatgpt, gemini |
| `marketing-product-launch-campaign-plan` | 新商品ローンチのキャンペーン企画を設計するプロンプト | 上級 | claude, chatgpt | claude, chatgpt |
| `marketing-kpi-tree-design` | マーケティングKGIから逆算するKPIツリー設計プロンプト | 上級 | claude, chatgpt | claude, chatgpt |
| `marketing-ab-test-hypothesis-plan` | A/Bテストの仮説と検証計画を立てるプロンプト | 中級 | chatgpt, claude | chatgpt, claude |
| `marketing-customer-case-study-interview` | 導入事例インタビューの質問設計と記事構成プロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `marketing-press-release-draft` | プレスリリースの草稿を作成するプロンプト | 中級 | chatgpt, claude | chatgpt, claude |
| `marketing-customer-journey-map` | カスタマージャーニーマップを作成するプロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `marketing-seo-keyword-intent-clustering` | SEOキーワードを検索意図でグルーピングしコンテンツ計画に落とすプロンプト | 上級 | chatgpt, claude | chatgpt, claude |
| `marketing-customer-survey-design` | 顧客満足度アンケートの設問を設計するプロンプト | 中級 | chatgpt, gemini | chatgpt, gemini |
| `marketing-brand-tagline-concept` | ブランドのタグラインとコンセプト案を作るプロンプト | 初級 | chatgpt, claude | chatgpt, claude |

### 開発（development）

| slug | タイトル | 難易度 | 対応AI | 関連AIツール |
|---|---|---|---|---|
| `development-error-log-root-cause` | エラーログとスタックトレースから原因を特定するデバッグプロンプト | 中級 | claude, chatgpt | claude-code, cursor, claude |
| `development-unit-test-case-generation` | 既存関数の単体テストケースを設計・生成するプロンプト | 中級 | claude, chatgpt | cursor, claude-code, github-copilot |
| `development-legacy-refactoring-plan` | レガシーコードを段階的にリファクタリングする計画プロンプト | 上級 | claude, chatgpt | claude-code, cursor, claude |
| `development-sql-query-from-requirements` | 業務要件からSQLクエリを作成し解説するプロンプト | 中級 | chatgpt, claude | chatgpt, claude, github-copilot |
| `development-regex-builder-with-tests` | 正規表現をテストケース付きで作成するプロンプト | 初級 | chatgpt, claude | chatgpt, github-copilot |
| `development-openapi-spec-draft` | API仕様書（OpenAPI形式）のドラフトを作成するプロンプト | 上級 | claude, chatgpt | claude, chatgpt, cursor |
| `development-pull-request-description` | 差分からプルリクエストの説明文を作成するプロンプト | 初級 | chatgpt, claude | github-copilot, chatgpt, claude |
| `development-feature-task-breakdown-estimate` | 機能要件を開発タスクに分解して見積もるプロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `development-codebase-onboarding-explainer` | 初めて触るコードベースを新メンバー向けに解説させるプロンプト | 初級 | claude, chatgpt | claude-code, cursor, claude |
| `development-database-schema-design` | 業務要件からデータベースのテーブル設計を行うプロンプト | 上級 | claude, chatgpt | claude, chatgpt |
| `development-web-performance-investigation` | 表示が遅いWebページのパフォーマンス調査計画プロンプト | 上級 | claude, chatgpt | claude, chatgpt |
| `development-shell-script-automation` | 手作業の定型作業をシェルスクリプトで自動化するプロンプト | 中級 | chatgpt, claude | chatgpt, claude, github-copilot |
| `development-readme-generator` | リポジトリのREADMEを作成するプロンプト | 初級 | chatgpt, claude | github-copilot, chatgpt, claude |
| `development-incident-postmortem` | システム障害のポストモーテム（振り返り報告書）作成プロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `development-prototype-brief-for-ai-builders` | v0・Lovable・Boltに渡すプロトタイプ指示書を作るプロンプト | 初級 | chatgpt, claude | v0, lovable, bolt |

### 文章（writing）

| slug | タイトル | 難易度 | 対応AI | 関連AIツール |
|---|---|---|---|---|
| `writing-article-introduction-rewrite` | 最後まで読まれる記事の導入文リライトプロンプト | 初級 | claude, chatgpt | claude, chatgpt |
| `writing-japanese-proofreading` | 日本語の誤字脱字・表記ゆれ・冗長表現を校正するプロンプト | 初級 | claude, chatgpt | claude, grammarly |
| `writing-tone-and-style-conversion` | 同じ内容を読み手に合わせた文体に書き換えるプロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `writing-apology-email-after-trouble` | トラブル発生時の謝罪メール作成プロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `writing-long-article-three-level-summary` | 長文記事を3段階の長さで要約するプロンプト | 初級 | gemini, claude | gemini, claude, notebooklm |
| `writing-article-title-ideas-with-evaluation` | 記事タイトル案を複数の型で作り評価するプロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `writing-interview-transcript-to-article` | インタビューの文字起こしを読みやすい記事にするプロンプト | 中級 | claude, chatgpt | claude, otter-ai |
| `writing-ec-product-description` | ECサイトの商品説明文を作成するプロンプト | 初級 | chatgpt, claude | chatgpt, copy-ai, jasper |
| `writing-fact-check-checklist` | 記事公開前のファクトチェックリストを作成するプロンプト | 上級 | claude, gemini | perplexity, gemini, claude |
| `writing-plain-language-rewrite` | 専門的な文章を中学生にも分かる言葉に言い換えるプロンプト | 初級 | claude, chatgpt | claude, chatgpt |
| `writing-speech-draft-for-occasion` | 朝礼・式典・送別会のスピーチ原稿を作るプロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `writing-resume-self-pr` | 職務経歴書の自己PRと職務要約を作成するプロンプト | 中級 | chatgpt, claude | chatgpt, claude |
| `writing-comparison-review-article-structure` | 商品比較レビュー記事の評価軸と構成を作るプロンプト | 中級 | chatgpt, claude | chatgpt, claude, perplexity |
| `writing-english-article-localization` | 英語の記事を自然な日本語に翻訳・ローカライズするプロンプト | 上級 | claude, chatgpt | claude, chatgpt |
| `writing-reader-persona-draft-review` | 想定読者になりきって原稿の分かりにくい箇所を指摘させるプロンプト | 上級 | claude, chatgpt | claude, chatgpt |

### 業務効率化（productivity）

| slug | タイトル | 難易度 | 対応AI | 関連AIツール |
|---|---|---|---|---|
| `productivity-daily-priority-time-blocking` | ToDoリストから今日の優先順位と時間割を作るプロンプト | 初級 | chatgpt, claude | chatgpt, claude, notion-ai |
| `productivity-meeting-agenda-with-goals` | 会議のゴールと時間配分を決めるアジェンダ作成プロンプト | 初級 | chatgpt, claude | chatgpt, claude, microsoft-copilot |
| `productivity-weekly-review-kpt` | 1週間の振り返りをKPTで整理するプロンプト | 初級 | claude, chatgpt | claude, chatgpt, notion-ai |
| `productivity-spreadsheet-formula-helper` | Excel・スプレッドシートの関数を作成して解説するプロンプト | 初級 | gemini, chatgpt | gemini, microsoft-copilot, chatgpt |
| `productivity-google-apps-script-automation` | Googleスプレッドシートの手作業をGASで自動化するプロンプト | 中級 | gemini, chatgpt | gemini, chatgpt |
| `productivity-no-code-automation-flow-design` | 定型業務の自動化フローをZapier・Make・n8n向けに設計するプロンプト | 中級 | chatgpt, claude | zapier-ai, make, n8n |
| `productivity-decision-matrix` | 複数の選択肢を評価軸で比較する意思決定マトリクス作成プロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `productivity-inbox-triage-reply-priority` | 溜まったメールを仕分けて返信の優先度を決めるプロンプト | 初級 | gemini, chatgpt | gemini, microsoft-copilot |
| `productivity-project-plan-wbs-milestones` | プロジェクト計画をWBSとマイルストーンに分解するプロンプト | 中級 | claude, chatgpt | claude, chatgpt, notion-ai |
| `productivity-one-on-one-meeting-prep` | 部下との1on1ミーティングの準備と質問を作るプロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `productivity-skill-learning-plan` | 新しいスキルを身につけるための学習計画を作るプロンプト | 初級 | chatgpt, gemini | chatgpt, gemini, notebooklm |
| `productivity-brainstorm-diverge-converge` | アイデアを発散させてから絞り込むブレインストーミングプロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `productivity-project-premortem-risks` | プレモーテムでプロジェクトの失敗シナリオとリスクを洗い出すプロンプト | 上級 | claude, chatgpt | claude, chatgpt |
| `productivity-csv-data-quick-analysis` | CSVデータの集計と傾向分析を行うプロンプト | 中級 | chatgpt, gemini | chatgpt, gemini |
| `productivity-ai-agent-task-instructions` | AIエージェントに業務を任せるための指示書（SOP）作成プロンプト | 上級 | claude, chatgpt | lindy, relevance-ai, n8n |

### 資料（documents）

| slug | タイトル | 難易度 | 対応AI | 関連AIツール |
|---|---|---|---|---|
| `documents-new-business-one-page-proposal` | 新規事業アイデアを企画書1枚にまとめるプロンプト | 中級 | claude, chatgpt | claude, gamma, canva-ai |
| `documents-work-manual-from-notes` | 作業メモから業務マニュアルを作成するプロンプト | 初級 | claude, chatgpt | notion-ai, claude, chatgpt |
| `documents-handover-document` | 異動・休暇前の引き継ぎ書を作成するプロンプト | 初級 | claude, chatgpt | claude, chatgpt, notion-ai |
| `documents-presentation-slide-outline` | 伝わるプレゼン資料のスライド構成を作るプロンプト | 初級 | chatgpt, claude | gamma, canva-ai, chatgpt |
| `documents-contract-review-checkpoints` | 契約書の確認ポイントと質問事項を洗い出すプロンプト | 上級 | claude, gemini | claude, gemini |
| `documents-generative-ai-usage-guideline` | 社内向け生成AI利用ガイドラインの草案を作成するプロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `documents-report-executive-summary` | 長い報告書のエグゼクティブサマリーを作成するプロンプト | 中級 | claude, gemini | claude, gemini, notebooklm |
| `documents-faq-from-inquiry-history` | 問い合わせ履歴からFAQ（よくある質問）ページを作成するプロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `documents-job-posting-description` | 応募したくなる求人票（募集要項）を作成するプロンプト | 初級 | chatgpt, claude | chatgpt, claude |
| `documents-multi-document-comparison-table` | 複数の資料を読み比べて比較表を作るプロンプト | 中級 | gemini, claude | notebooklm, gemini, claude |

### 画像（image）

| slug | タイトル | 難易度 | 対応AI | 関連AIツール |
|---|---|---|---|---|
| `image-ec-product-photo-generation` | EC用の商品イメージ写真を生成するための画像プロンプト作成 | 中級 | image, chatgpt | adobe-firefly, midjourney, flux |
| `image-blog-eyecatch-concept` | ブログ記事のアイキャッチ画像のコンセプトと生成プロンプトを作る | 初級 | image, chatgpt | ideogram, canva-ai, chatgpt |
| `image-text-banner-with-typography` | 文字入りのキャンペーンバナーを画像生成AIで作るプロンプト | 中級 | image | ideogram, canva-ai |
| `image-consistent-character-multi-pose` | 同じキャラクターを複数のポーズ・場面で一貫して生成するプロンプト | 上級 | image | midjourney, leonardo-ai |
| `image-logo-concept-sketches` | ロゴのコンセプト案とラフ画像を作るプロンプト | 中級 | image, chatgpt | ideogram, adobe-firefly |
| `image-reference-to-generation-prompt` | 参考画像の雰囲気を言葉にして生成プロンプトに変換するプロンプト | 中級 | chatgpt, gemini | midjourney, flux, adobe-firefly |
| `image-brand-moodboard` | ブランドのムードボード用画像をまとめて生成するプロンプト | 中級 | image | midjourney, flux |
| `image-infographic-layout-plan` | 図解・インフォグラフィックの構成と作画指示を作るプロンプト | 初級 | chatgpt, image | canva-ai, chatgpt |

### 動画（video）

| slug | タイトル | 難易度 | 対応AI | 関連AIツール |
|---|---|---|---|---|
| `video-short-vertical-30s-script` | 縦型ショート動画（30秒）の台本と構成を作るプロンプト | 初級 | chatgpt, claude | chatgpt, descript |
| `video-text-to-video-shot-prompts` | 動画生成AI用にシーンをショット単位のプロンプトへ分解する | 中級 | video, chatgpt | sora, veo, runway |
| `video-youtube-explainer-script` | YouTube解説動画の構成と台本を作るプロンプト | 中級 | claude, chatgpt | claude, chatgpt |
| `video-storyboard-shot-list` | 企業PR・商品紹介動画の絵コンテ（カット表）を作るプロンプト | 中級 | chatgpt, claude | chatgpt, runway |
| `video-ai-avatar-training-video-script` | AIアバターで作る社内研修動画の原稿を作るプロンプト | 初級 | chatgpt, video | heygen, synthesia |
| `video-youtube-title-thumbnail-description` | YouTubeのタイトル・サムネイル文言・概要欄をまとめて作るプロンプト | 初級 | chatgpt, gemini | chatgpt, gemini |
| `video-image-to-video-motion-direction` | 静止画を動画に変える（image-to-video）ための動きの指示を作るプロンプト | 上級 | video | kling-ai, luma-dream-machine, runway |
