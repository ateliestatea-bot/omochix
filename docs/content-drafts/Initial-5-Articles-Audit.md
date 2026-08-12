# OmochiX初期AIニュース5記事 公開前横断監査

- 監査日：2026-08-12
- 対象Branch：`feature/home-mvp`
- 対象：公開待ちMarkdown 5稿
- 更新日：2026-08-12（公開前修正を反映）
- 方針：公式一次情報による再確認、投入情報・内部リンク・公開判定の整理。Theme・Core Plugin・データは変更しない

## 1. 総合評価

5稿は、Model選択、Coding Agent、Research、Mobile AI Development、Creative Agentという異なるSearch intentを持ち、初期コンテンツとしての棲み分けは成立している。Slug、Title、Meta Descriptionの直接重複はない。すべて公式一次情報を中心に構成され、編集部見解も概ね分離されている。

公開前修正により、旧価格、旧Docs URL、分類、Excerpt、内部リンク候補、SEO titleの定型感は整理した。一方、全稿が`Human review status: 公開前確認待ち`かつ`Eyecatch status: 未制作`である。価格、Plan、提供範囲など時点依存情報も含むため、現時点では公開せず、公開直前に公式情報とWordPress Previewを人が確認する。

判定記号：

- **A**：公式一次情報で確認済み
- **B**：公式情報で確認済みだが、公開直前に再確認が必要
- **C**：未確認、要確認、または現行URLから再検証できない

## 2. 事実確認状態

| 記事 | 正式名称 | 発表日 / 提供開始日 | 説明 | Plan / 料金 | 日本提供 | API | 制限 | 一次情報 | Fact-check |
|---|---|---|---|---|---|---|---|---|---|
| GPT-5.6 | A：GPT-5.6 Sol / Terra / Luna | A：2026-07-09 / 同日から段階展開 | A：Model選択、Coding、Knowledge work、Agent | A/B：7月30日以降のAPI価格、ChatGPT Plan表を反映。公開直前に再確認 | B：対応国ルールとAccount rollout | A/B：3 Modelと価格を公式Model比較で確認 | B：Plan・Workspace・Safeguard依存 | A：OpenAI公式4件 | 2026-08-12 |
| Claude Sonnet 5 | A：Claude Sonnet 5 | A：2026-06-30 / 同日 | A：Coding、Tool use、Agent、Effort | A/B：入力2ドル・出力10ドルの恒久化を確認。公開直前に再確認 | A/B：公式対応地域に日本、機能差は再確認 | A：`claude-sonnet-5` | B：Tier別Rate limit | A：Anthropic公式3件 | 2026-08-12 |
| Gemini Notebook | A：Gemini Notebook | A：2026-07-16 / 機能ごとに段階展開 | A：旧NotebookLMの名称変更、Source-based research、Gemini連携 | A/B：基本機能の無料利用とUltra / Workspace / Proへの段階展開を確認 | A/B：Web・iOS・Androidの日本対応を確認。個別機能差は再確認 | C：記事対象機能の公開APIは記載なし | B：Mobile機能制限・Plan別上限 | A/B：Google公式6件。Help 2件は公開日不明 | 2026-08-12 |
| Cursor iPad | A：Cursor for iPad / Cursor Mobile App for iOS | A：iOS 2026-06-29、iPad 2026-07-29 | A：Cloud Agent起動、成果物・Diff・PR確認 | A/B：Pro 20ドル/月、Teams 40ドル/人/月等を確認。利用枠は再確認 | C：日本語対応は確認、日本App Storeでの実配信は要確認 | C：記事対象Mobile機能の外部APIは対象外 / 未確認 | A/B：iOS / iPadOS 26以上、Beta、Model別消費 | A：Cursor公式6件、現行Docs URLへ修正 | 2026-08-12 |
| Runway Agent 2.0 | A：Agent 2.0 | A：2026-06-25 / 発表時点で全ユーザー | A：広告分析、企画、Creative生成、Variation | B/C：現行Plan価格は確認、Agent固有Creditは不明 | C：日本固有条件は要確認 | C：個別Model APIはあるがAgent 2.0 API未確認 | C：Agent固有上限・Credit | A：Runway公式9件 | 2026-08-12 |

### 時点依存表現

公開日の直前に、以下の語を含む文を公式ページと再照合する。

| 記事 | 抽出した表現 | 再確認内容 |
|---|---|---|
| GPT-5.6 | 「最新」「一般提供」「2026年8月12日時点」「対象となる有料プラン」「FreeとGo」「利用できます」 | Rollout、Plan表、ChatGPT / Work / Codex / API価格。公式発表には2026-07-30の価格更新注記あり |
| Claude | 「新モデル」「同日から提供」「デフォルト」「2026年8月12日時点」「恒久化」 | 現行価格、Default model、対応Plan、Model ID、Tokenizer注意事項 |
| Gemini | 「現在の正式製品名」「順次展開」「基本機能は無料」「Ultra / Workspace / Pro」「Mobile制限」 | 名称、段階展開の完了状況、Plan名、地域・Mobile差 |
| Cursor | 「公開ベータ」「すべての有料プラン」「月額20ドルから」「月額40ドル」「利用可能」 | Beta表記、App Store、Plan名・価格、Cloud AgentのUsage pool、Docs URL |
| Runway | 「全ユーザー」「月額」「今後の構想」「2026年8月12日時点」 | Agent固有Credits、Plan価格、API、Platform連携、日本提供 |

## 3. 一次情報URL監査

### 横断結果

- 第三者Mediaを一次情報として扱っている稿：**なし**。
- 5稿間で同一Source URLの重複：**なし**。同一記事内の重複もなし。
- UTM、`trk`など不要なTracking parameter：**なし**。
- `https`で統一されている。
- Runway Pricingの`?tool=runway`は表示対象を指定する機能Parameterであり、Tracking parameterではない。
- Source title、公開日、最終確認日の記載形式は稿ごとに異なる。公開上の誤りではないが、将来は共通書式へ統一可能。

### 記事別

| 記事 | 公式性 | URL / 表記 | 公開日・確認日 | 問題・公開前対応 |
|---|---|---|---|---|
| GPT-5.6 | OpenAI公式のみ | 3件とも現行Pageを確認 | Releaseは日付あり、Help / Docsは初回公開日なし | Helpは監査時点で「15時間前更新」。公開直前にPlan表を再取得。Releaseの7月30日価格更新も本文との整合を確認 |
| Claude | Anthropic公式のみ | 3件とも現行Pageを確認 | Newsは日付あり、Countries / Docsは明示日なし | 発表Pageの更新で2ドル / 10ドルの恒久化を確認。公開直前に変更有無を再確認 |
| Gemini | Google公式のみ | Blog 2件と現行`gemininotebook` Help URL 4件を使用 | Helpは公開日・更新日不明 | 正式名称、日本のWeb / Mobile対応を反映。段階展開とPlan別上限を公開直前に再確認 |
| Cursor | Cursor公式のみ | Changelog / Blog / Pricingに加え、`/docs/cloud-agent/mobile`と`/docs/models-and-pricing`へ修正 | Changelog / Blogは日付あり、Docsは不明 | 旧Docs Redirectを解消。日本App Storeでの配信状態と利用枠は人が再確認 |
| Runway | Runway公式のみ | `runway.com`と公式Help / Dev subdomainを使用 | News / Terms / Policyは日付あり、Pricing / Helpはなし | Agent固有Creditと日本提供はSource不足のまま。価格は公開直前に再確認 |

## 4. SEO横断監査

| 記事 | Article Title（H1想定） | SEO Title | Slug | Main keyword | Search intent |
|---|---|---|---|---|---|
| GPT-5.6 | GPT-5.6とは？OpenAI最新モデルの特徴とSol・Terra・Lunaの違いを解説 | GPT-5.6とは？Sol・Terra・Lunaの違いと特徴をわかりやすく解説 | `gpt-5-6-guide` | GPT-5.6 / Sol Terra Luna | 最新Modelの概要と選び方 |
| Claude | Claude Sonnet 5とは？コーディングとAIエージェントの進化を解説 | Claude Sonnet 5とは？新機能・料金・AI開発での変化を解説 | `claude-sonnet-5-guide` | Claude Sonnet 5 | Coding / Agent / 料金 |
| Gemini | NotebookLMはGemini Notebookへ。名称変更と新しいリサーチ機能を解説 | NotebookLMからGemini Notebookへ｜名称変更と新機能を解説 | `gemini-notebook-update-guide` | Gemini Notebook / NotebookLM | 名称変更とResearch活用 |
| Cursor | CursorがiPadに対応。外出先からAI開発とPRレビューはどこまでできる？ | CursorがiPad対応｜外出先からCloud Agentで開発する方法 | `cursor-ipad-mobile-agent-guide` | Cursor iPad / Mobile AI開発 | 外出先で可能な開発工程 |
| Runway | Runway Agent 2.0とは？AIが広告・動画制作をどこまで進めるのか | Runway Agent 2.0で広告制作はどう変わる？AIワークフローを解説 | `runway-agent-2-creative-workflow` | Runway Agent 2.0 | Creative / 広告制作Workflow |

### 結果

- Title、SEO Title、Slug、Meta Descriptionの直接重複：なし。
- Slug競合：なし。
- Keyword cannibalization：低い。GPT / Claude / CursorはAI開発で接近するが、Model選択、Coding Model、Mobile WorkflowにIntentが分かれる。
- Article Titleは39〜45文字程度、SEO Titleは34〜40文字程度。日本語SERPでは切れる可能性があるため、45文字のGPT / GeminiはPreview確認を推奨。
- Meta Descriptionは約97〜104文字で、重複なく自然な範囲。
- SEO Titleの「とは？」型はGPT-5.6とClaudeの2本に限定し、Gemini、Cursor、Runwayは製品固有のSearch intentに合わせた表現へ変更済み。
- 「公式情報に基づいて解説」「何が変わる」「どんな人に向く」「まとめ」の反復があり、情報設計上は妥当だが、5本を並べるとAI生成記事らしい均一感がある。

### 修正反映

- Gemini、Cursor、RunwayのSEO Titleを、それぞれResearch、Mobile / Cloud Development、Creative / Advertising Agentの意図が明確になる表現へ変更した。
- Article Titleは検索内容との整合を優先して維持した。大幅な導入文リライトは行っていない。

## 5. 内容重複と記事の役割

| 記事 | 主役 | 固有価値 | 重複リスク | 評価 |
|---|---|---|---|---|
| GPT-5.6 | Model tier選択 | Sol / Terra / Lunaを品質・速度・費用で選ぶ | ClaudeとのCoding / Agent説明 | 棲み分け成立 |
| Claude | Coding / Agent Model | Tool use、Effort、Claude Code、API料金 | GPTとのModel能力、Cursorとの開発工程 | 棲み分け成立。Model能力に集中させる |
| Gemini | Source-grounded Research | Source収集、Notebook、Gemini同期、成果物 | 比較節のみGPT / Claudeと接近 | 明確に独立 |
| Cursor | Mobile / Cloud Development | iPad / iPhone / Web、Cloud Agent、PR review | GPT / ClaudeのCoding説明 | 棲み分け成立。Model比較よりWorkflowを主役にする |
| Runway | Creative / Advertising Agent | 広告分析、企画、動画・Campaign素材 | Agent一般論と人間確認の記述 | 明確に独立 |

重複が目立つのは「複数工程を進めるAgent」「結果を人が確認する」「最適なAIは用途で選ぶ」という説明である。安全上必要だが、内部リンクを使い、各稿では固有Workflowへ早く入ると定型感を減らせる。想定された5つの役割分担は成立している。

## 6. 内部リンク設計

| Source | Target slug | Anchor候補 | 設置するH2 | 理由 |
|---|---|---|---|---|
| GPT-5.6 | `claude-sonnet-5-guide` | Claude Sonnet 5のコーディング・Agent機能 | GPT-5.6の注目ポイント | Frontier modelをAI開発の観点で比較検討できる |
| GPT-5.6 | `cursor-ipad-mobile-agent-guide` | Cursorで外出先からAI開発する流れ | CodexやAPIでも利用可能 | Modelから実際の開発Workflowへつなぐ |
| Claude | `cursor-ipad-mobile-agent-guide` | Cloud AgentとMobile開発でできること | コーディング・AI開発では何が変わる？ | Model能力と実行環境の違いを補完 |
| Claude | `gpt-5-6-guide` | GPT-5.6のSol・Terra・Lunaの選び方 | ChatGPTなど他のAIとはどう使い分ける？ | 断定比較を避け、別記事でModel設計を説明 |
| Gemini | `runway-agent-2-creative-workflow` | 調査結果を広告・動画の企画へつなげるAI Workflow | 仕事ではどう使える？ | ResearchからCreative briefへの自然な接続 |
| Gemini | `gpt-5-6-guide` | GPT-5.6を使ったリサーチと資料作成 | ChatGPTやClaudeとはどう使い分ける？ | Notebook型と汎用Model型の役割差を補足 |
| Cursor | `claude-sonnet-5-guide` | Claude Sonnet 5のCoding / Agent機能 | ChatGPT・Codex・Claudeとはどう使い分ける？ | Cursorで選ぶModelの背景へ接続 |
| Cursor | `gpt-5-6-guide` | GPT-5.6の3モデルと用途の違い | ChatGPT・Codex・Claudeとはどう使い分ける？ | CursorのModel選択を補足 |
| Runway | `gemini-notebook-update-guide` | AIリサーチでCreative briefを整理する方法 | 仕事ではどう使えそう？ | Researchから広告企画へつなぐ |
| Runway | `cursor-ipad-mobile-agent-guide` | 人が確認しながらAgentへ仕事を任せる流れ | AI Agentは制作をどこまで進められる？ | Development / Creativeに共通するHuman-in-the-loopを補完 |

公開URLは未確定のため、WordPress投入後にSlugから実URLを取得して設定する。

## 7. Category / Tag設計

### 現在の構造

- 通常投稿はWordPress標準`category`と`post_tag`を使用する。
- Themeは通常投稿カテゴリー`ai-news`（表示名「AIニュース」）をNews一覧のDefault条件として参照する。
- `AIツール`は主にCPT `ai_tool`と`ai_tool_category`の概念であり、通常投稿用カテゴリーとして存在するとは限らない。
- ThemeのHome定義には通常投稿カテゴリー候補として`ai-news`、`tutorial`、`prompts`、`business`、`development`、`design`、`lifestyle`がある。ただしローカルDBの実在TermはこのRepositoryだけでは確定できない。

### 推奨

初期5稿は全てPrimary Categoryを**AIニュース（slug: `ai-news`）**に統一する。製品名や用途はTagで表現し、初期記事だけのために`ChatGPT`、`Claude`、`Gemini`、`Coding`などをCategoryとして増やさない。

| 記事 | Category | Tags |
|---|---|---|
| GPT-5.6 | AIニュース | OpenAI、ChatGPT、GPT-5.6、AIモデル、AIエージェント |
| Claude | AIニュース | Anthropic、Claude、Claude Sonnet 5、AI開発、AIエージェント |
| Gemini | AIニュース | Google、Gemini、Gemini Notebook、NotebookLM、リサーチ |
| Cursor | AIニュース | Cursor、AI開発、Cloud Agent、iPad、GitHub |
| Runway | AIニュース | Runway、動画生成、広告制作、AIエージェント、マーケティング |

`AIツール`、`AI活用`は記事数が増え、一覧として独立価値が出てからCategory化を検討する。

## 8. 推奨公開順

### 順位

1. **GPT-5.6**：最も広い読者にOmochiXの「選び方を整理する」価値を示せる。
2. **Gemini Notebook**：初心者が理解しやすく、Research用途を加えて初期一覧の幅を出せる。
3. **Claude Sonnet 5**：Coding / Agentの技術的な深さを加え、GPT記事と相互参照できる。
4. **Runway Agent 2.0**：Creative / Advertisingを加え、Homeの見た目とTopicの多様性を強める。
5. **Cursor iPad**：Model記事を読んだ後に、外出先での具体的な開発Workflowへつなげる。

### 同日公開案

公開時刻を1〜2時間ずつ分け、古い時刻からCursor → Runway → Claude → Gemini → GPTの順で登録する。これによりHomeの新着順ではGPT、Gemini、Claude、Runwayが上位4件となり、CursorはNews一覧と内部リンクから到達させる。意図した順序を保証したい場合は公開後にStickyを使うが、必要以上に固定しない。

### 分散公開案

- Day 1：GPT-5.6
- Day 2：Gemini Notebook
- Day 3：Claude Sonnet 5
- Day 4：Runway Agent 2.0
- Day 5：Cursor iPad

分散案は各記事のHome露出時間を確保できる。日付の新しさを偽装せず、OmochiXの実公開日時を設定する。

## 9. Home表示監査

### 最新AIニュース

- `post`の公開済み記事を**最大4件**表示する。
- Sticky post IDsを先に`post__in`順で取得し、残枠をSticky以外の公開日降順で補完する。
- 5稿を全て公開しても、同時に表示されるのは最大4件。Stickyなしなら最も新しい4件。
- Imageは`get_the_post_thumbnail_url(..., 'large')`。未設定時はOmochiXのPlaceholderを表示する。
- Excerptは`get_the_excerpt()`をHTML除去後、`wp_trim_words(..., 46, '…')`。手動Excerpt未入力ならWordPressの自動Excerptに依存する。
- Categoryは`get_the_category()`の先頭1件。順序の運用が曖昧になるためPrimary CategoryはAIニュース1件を推奨。
- 投稿日を`Y.m.d`で表示する。公開後3日以内はNEW Badgeを表示する。
- 読了時間は本文のHTML除去後に`strlen()`したByte数を1,200で割って算出する。日本語UTF-8では文字数より大きくなりやすく、実際より長い表示になる可能性がある。今回はコード変更せず既知課題とする。

### おすすめ記事

- 最大3件。
- 順番は、Tag `ai-beginner`から1件、`post_views_count`最大から1件、Meta `is_recommended`が`1 / true / yes`から1件。
- 同一投稿を除外し、不足枠を新着順で補完する。
- 初期5稿にSignalを付けない場合、新着3件がFallback表示される可能性が高い。
- ImageなしはPlaceholder。Excerptは`get_the_excerpt()`を52語相当へTrim。先頭Categoryと投稿日を表示する。
- 読了時間はおすすめ記事Cardには表示しない。

### Category

AIニュースCategoryの件数は公開投稿数へ反映される。TagはHome Cardには表示されない。Eyecatch未制作のまま公開するとLatest / Recommendedの両方がPlaceholderとなり、5記事の視覚的多様性は出ない。

## 10. WordPress投入仕様

### 記事別

| 記事 | Title / Slug | Category / Tags | Excerpt | Meta Description | Eyecatch | Author / Type | Primary sources | Internal links | Review / Status |
|---|---|---|---|---|---|---|---|---|---|
| GPT-5.6 | 原稿H1 / `gpt-5-6-guide` | AIニュース / OpenAI、ChatGPT、GPT-5.6、AIモデル、AIエージェント | 原稿「抜粋」を手動入力 | 原稿記載文 | 未制作 | OmochiX編集部 / ニュース＋解説 | OpenAI Release、Help、Developer Docs | Claude、Cursor | Human review待ち / Draft |
| Claude | 原稿H1 / `claude-sonnet-5-guide` | AIニュース / Anthropic、Claude、Claude Sonnet 5、AI開発、AIエージェント | 原稿「抜粋」を手動入力 | 原稿記載文 | 未制作 | OmochiX編集部 / ニュース＋解説 | Anthropic News、Countries、Platform Docs | Cursor、GPT | Human review待ち / Draft |
| Gemini | 原稿H1 / `gemini-notebook-update-guide` | AIニュース / Google、Gemini、Gemini Notebook、NotebookLM、リサーチ | 原稿「抜粋」を手動入力 | 原稿記載文 | 未制作 | OmochiX編集部 / ニュース＋解説 | Google Blog、Help | Runway、GPT | Human review待ち / Draft |
| Cursor | 原稿H1 / `cursor-ipad-mobile-agent-guide` | AIニュース / Cursor、AI開発、Cloud Agent、iPad、GitHub | 原稿「抜粋」を手動入力 | 原稿記載文 | 未制作 | OmochiX編集部 / ニュース＋解説 | Cursor Changelog、Blog、Docs、Pricing | Claude、GPT | Human review待ち / Draft |
| Runway | 原稿H1 / `runway-agent-2-creative-workflow` | AIニュース / Runway、動画生成、広告制作、AIエージェント、マーケティング | 原稿「抜粋」を手動入力 | 原稿記載文 | 未制作 | OmochiX編集部 / ニュース＋解説 | Runway News、Engineering、Pricing、Help、Dev、Terms | Gemini、Cursor | Human review待ち / Draft |

### 入力順

1. `AIニュース`カテゴリー（slug `ai-news`）の存在を確認する。
2. 推奨Tagを既存Termと照合し、表記・slugの重複を避けて作成する。
3. 通常投稿を**下書き**で新規作成する。
4. Titleを入力し、Slugを明示設定する。
5. Markdownの公開管理情報を除き、本文をBlock editorへ見出し階層を保って移す。
6. 原稿の「抜粋」をWordPressのExcerptへ手動入力する。
7. CategoryをAIニュースに設定し、必要なTagsを付ける。
8. AuthorをOmochiX編集部の適切なWordPress Userへ設定する。
9. 1200×630pxのEyecatchを設定し、意味のあるAltを入力する。
10. Slim SEOでSEO TitleとMeta Descriptionを設定する。Theme側へMetaを追加しない。
11. 公開済みTargetの実URLを使い、内部リンクを設置する。
12. 出典URL、外部リンク属性、Published / Updated / Fact-check dateを確認する。
13. Desktop / Mobile PreviewでH1 1件、H2、Table、Link、Eyecatch、Excerptを確認する。
14. 人間の編集責任者が一次情報と全文を照合し、Revision historyを更新する。
15. 公開日時を決め、必要な記事だけSticky / Recommend Signalを設定して公開する。

## 11. Eyecatch設計

共通仕様：1200×630px（約1.91:1）、Safe areaを確保し、OmochiXロゴは左上または右下の同一位置へ小さく配置。大見出し2行以内、製品Logoは公式Brand guidelineと利用許諾を確認する。5枚すべてを紫一色にしない。

| 記事 | Main / Sub text | Brand | Background | Omochi | Logo位置 | Alt候補 |
|---|---|---|---|---|---|---|
| GPT-5.6 | `GPT-5.6 登場` / `Sol・Terra・Lunaの違い` | OpenAI（権利確認） | 白〜淡いBlue、3層の抽象図形 | 使わない | 右下 | GPT-5.6のSol・Terra・Lunaを3層で表した記事アイキャッチ |
| Claude | `Claude Sonnet 5` / `AI開発はどう変わる？` | Anthropic（権利確認） | Warm beige〜Orangeの細いCode motif | 小さく案内役として可 | 右下 | Claude Sonnet 5のコーディングとAIエージェントを解説するアイキャッチ |
| Gemini | `Gemini Notebook` / `調査と資料整理が進化` | Google / Gemini（権利確認） | White〜Blue、NotebookとSource card | 使うなら小さく | 右下 | Gemini Notebookのソース調査と情報整理を表したアイキャッチ |
| Cursor | `CursorがiPad対応` / `外出先でもAI開発？` | Cursor（権利確認） | Charcoal、Tablet frameとPR diff | 使わない | 右下 | iPadからCursor Cloud AgentとPRを確認する流れのアイキャッチ |
| Runway | `Runway Agent 2.0` / `広告・動画制作はどう変わる？` | Runway（権利確認） | Neutral dark、Timelineと3比率Frame | 使わない | 右下 | Runway Agent 2.0による広告と動画制作フローのアイキャッチ |

Omochiを使う稿でも主役にせず、情報と製品Topicを優先する。製品Screen captureを使う場合は利用条件と表示内容を確認する。

## 12. 公開Blocking判定

| 記事 | 判定 | 理由 |
|---|---|---|
| GPT-5.6 | REVIEW | 7月30日以降の価格とPlan情報は反映済み。Eyecatch、Human review、公開直前のPlan再確認が残る |
| Claude | REVIEW | 2ドル / 10ドルの恒久化を反映済み。Eyecatch、Human review、公開直前の価格・Plan再確認が残る |
| Gemini | REVIEW | 正式名称、現行Help URL、日本のWeb / Mobile対応を反映済み。Eyecatch、Human review、段階展開確認が残る |
| Cursor | REVIEW | 現行Docs URL、対応OS、Plan価格を反映済み。Eyecatch、Human review、日本App Store確認が残る |
| Runway | REVIEW | Agent固有Credit、Agent API、日本条件は未確認と明示。Eyecatch、Human review、公開直前確認が残る |

5稿とも原稿と投入情報は公開前レビューへ進められる状態だが、`Human review`と`Eyecatch`が完了していないため公開実行はしない。READYは0件、REVIEWは5件、公開を妨げる未記載の重大事実があるBLOCKEDは0件。

## 13. 公開前に人間が確認する項目

1. 公式Sourceを公開当日に再訪し、名称、提供範囲、Plan、価格、API、地域を照合する。
2. GPT-5.6のPlan / API価格とClaude Sonnet 5の恒久価格が公開日時点でも有効か確認する。
3. Geminiの段階展開・Plan別上限、Cursorの日本App Store提供、RunwayのAgent固有Credit / API / 日本条件を確認する。
4. 各記事の編集部見解が事実と混ざっていないか全文校閲する。
5. EyecatchのBrand asset、人物・素材、Logoの利用権を確認する。
6. AIニュースCategory、Tags、Author User、ExcerptをWordPress上で確定する。
7. Slim SEOのTitle / Description / OGPを設定し、Themeとの重複がないことをPreviewで確認する。
8. 内部リンクはTarget公開後の実URLを使用し、404がないことを確認する。
9. Homeの最新4件、おすすめ3件、Placeholder、読了時間を実Browserで確認する。
10. Published date、Updated date、Last fact-check date、Revision historyを公開時点へ更新する。

## 14. 公開前修正の反映状況

### 修正済み

- GPT-5.6：2026年7月30日以降のAPI価格、ChatGPT Plan別提供、API Model IDを時点付きで整理。
- Claude Sonnet 5：終了予定だった導入価格ではなく、公式発表で恒久化された2ドル / 10ドルへ統一。
- Gemini Notebook：正式名称、日本のWeb / Mobile対応、現行`gemininotebook` Help URLへ統一。
- Cursor：旧Redirect URLを現行Docs URLへ変更し、iOS / iPadOS条件、Plan価格、Cloud Agentとの関係を整理。
- Runway：Agent 2.0固有Credit、Agent API、日本条件を推測せず要確認として維持。
- 5稿共通：Category / Tags / Excerpt / Primary sources / Internal link candidates / 公開前チェックを整備。
- SEO Title：Gemini、Cursor、Runwayの「とは？」型を解消。

### 残る要確認

- 全稿：公開当日の価格、Plan、地域、API、利用制限、Source linkの最終確認。
- 全稿：Human review、Eyecatch、WordPress Preview、Slim SEO、実URLによる内部リンク、公開日。
- Gemini：段階展開の完了状況とPlan別上限。
- Cursor：日本App StoreでのiPhone / iPadアプリ提供状態。
- Runway：Agent 2.0固有Credit、Agent 2.0 API、日本固有の提供条件。

## 15. 監査範囲と非変更確認

- 指定された5稿と本監査レポートのみ、公開前修正として更新した。
- Theme、Core Plugin、CSS、JavaScript、PHP、CSVは変更していない。
- 本監査で追加したファイルは`docs/content-drafts/Initial-5-Articles-Audit.md`のみ。
- Commit、push、mergeは行わない。
