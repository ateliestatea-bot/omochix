# Phase 3.1 — Data Consistency + Sora Discontinuation Preparation

作成日：2026-10-01（JST）
種別：調査・修正案のみ。production・WordPress・DBへの書き込みなし、commitなし。
production確認はすべて公開REST／公開ページへのGET（2026-10-01取得）。

---

## 1. Source of truth（実装上の定義）

### 1-1. フィールドの正式な定義

| フィールド | 保存先 | 正式な値 | 画面での使われ方 | 定義元 |
|---|---|---|---|---|
| `pricing_type` | post meta（enum） | `free` / `freemium` / `paid` / `trial` / `contact`（既定 `contact`） | クイックサマリーとHERO下の「料金」。ラベルは 無料 / 無料プランあり / 有料 / 無料体験あり / 要問い合わせ | `includes/meta-schema.php`（schema・`omochix_core_get_pricing_type_label()`） |
| `has_free_plan` | post meta（boolean） | true / false | クイックサマリー「無料プラン：あり/なし」 | 同上 |
| `tool_status` | post meta（enum） | `active` / `beta` / `waitlist` / `discontinued`（既定 `active`） | ツール詳細ページの「提供状況」のみ。一覧・関連ツール・トップ・構造化データでは**参照されていない** | 同上、`single-ai_tool.php` |
| 対応環境（platform） | taxonomy `ai_tool_platform`（非公開・term archiveなし） | 既定の語彙：Web / Windows / macOS / Linux / iOS / Android / API / Chrome拡張 / Slack / Discord | ①クイックサマリー「対応環境」 ②HEROのチップ（一覧の絞り込み `?platform=` へのリンク） ③一覧ページの絞り込み ④SoftwareApplicationの `operatingSystem` | `includes/taxonomies.php`、`archive-ai_tool.php`、`software-application-schema.php` |
| `supported_devices` | post meta（文字列配列） | 自由記述、1行1項目（管理画面の説明文は「例：スマホアプリ、デスクトップアプリ」） | クイックサマリーと詳細情報の「対応デバイス」表示のみ（絞り込み・構造化データには不使用） | `meta-schema.php`、`admin/meta-boxes.php` |

→ **platformは「絞り込みと構造化データに使う、統制語彙の環境分類」**、**supported_devicesは「表示専用の自由記述」**。役割が違うので同じ値に揃える必要はない。ただしplatformが実態より少ないと、一覧の絞り込みから漏れ、`operatingSystem` も不正確になる。

### 1-2. 更新経路と書き込めるフィールド

| 経路 | 書き込めるもの | 注意 |
|---|---|---|
| Content Updater（JSON） | short_description、post_content、v2の配列・テキスト項目、has_free_plan、api_available、commercial_use、japanese_support、info_checked_date、omochix_view、SEO title/description、画像ID | `pricing_type`・`tool_status`・`rating_overall`・`official_url`・taxonomyは**書き込めない**（対象外） |
| wp-admin 一覧のクイック編集 | pricing_type、japanese_support、tool_status、rating_overall、is_featured、display_order、非階層taxonomy（対応環境など） | 1件ずつ確認しながら変更でき、最も安全 |
| wp-admin 投稿編集画面 | すべてのmetaとtaxonomy | — |
| CSVインポーター | 既存slugに対しても title・9項目のmeta・**4種のtaxonomyを丸ごと置換**する | 既存ツールの部分修正には**使わない**（short_descriptionやratingを上書きする危険） |

### 1-3. productionの現在値（2026-10-01 GET）

| slug | ID | pricing_type | has_free_plan | 対応環境（platform） | supported_devices | tool_status |
|---|---|---|---|---|---|---|
| veo | 678 | freemium | false | Web | Web, iOS, Android | active |
| notebooklm | 659 | free | true | Android, iOS, Web | Web, iOS, Android | active |
| runway | 675 | freemium | true | iOS, Web | Web, iOS, Android | active |
| midjourney | 59 | paid | false | （なし） | Web, Discord | active |
| perplexity | 63 | freemium | true | Android, iOS, macOS, Web | Webブラウザ, iPhone / iPad, Android, macOS, Windows | active |
| gemini | 61 | freemium | true | Android, iOS, Web | Web, iOS, Android, macOS | active |
| claude-code | 665 | paid | false | Linux, macOS, Windows | macOS, Linux, Windows, Web, iOS, Android | active |
| sora | 679 | paid | false | Android, iOS, Web | （空） | active |

参考：platformの語彙に、使われていない不要なterm「Web / iOS / Android / Mac / WhatsApp」（件数0）がある。今回の範囲外（掃除は別途）。

---

## 2. pricing_type（Veo / NotebookLM）

| slug | pricing_type | has_free_plan | pricing_details | 判定 | 理由 |
|---|---|---|---|---|---|
| veo | freemium → **paid** | false（KEEP） | KEEP | 修正要 | Geminiアプリの無料プランの機能一覧に動画生成はなく（gemini.google/subscriptions）、Gemini APIにも動画生成の無料枠がない（ai.google.dev pricing）。無料で使える経路が公式に確認できないため「無料プランあり」は誤り。今の「料金：無料プランあり／無料プラン：なし」という矛盾も解消される |
| notebooklm | free → **freemium** | true（KEEP） | KEEP | 修正要 | 無料のStandardに加えて、Google AI Plus／Pro／Ultraの有料上位枠がある（support.google.com/notebooklm/answer/16213268）。「完全無料」ではなく「無料で一部利用可能＋有料上位枠」 |

- 経路：**WP_ADMIN_MANUAL**（一覧のクイック編集で `pricing_type` を変更）。Updaterでは変更不可。
- `trial` は、Veoで公式の無料体験が確認できないため不採用。

---

## 3. Platform consistency（6ツール）

方針：platformは統制語彙の範囲で「公式に利用できると確認できた環境」を網羅する。supported_devicesは表示用なので原則KEEP。

| slug | CURRENT_PLATFORM | CURRENT_SUPPORTED_DEVICES | RECOMMENDED_PLATFORM | RECOMMENDED_SUPPORTED_DEVICES | 根拠 |
|---|---|---|---|---|---|
| veo | Web | Web, iOS, Android | **Web, iOS, Android, API** | KEEP | Geminiアプリ（Web/iOS/Android）とFlow（Web）で利用でき、Gemini API・Vertex AIで開発者が利用可能（ai.google.dev/gemini-api/docs/veo） |
| runway | iOS, Web | Web, iOS, Android | **Web, iOS, Android, API** | KEEP | 公式サイトにAndroid app / iOS appへのリンク、runway.com/aboutにWeb・Android・iOSを明記。開発者向けAPIあり（docs.dev.runwayml.com） |
| midjourney | （なし） | Web, Discord | **Web, Discord** | KEEP | 公式ヘルプ：midjourney.comのWebアプリとDiscordで利用。現状は対応環境が「未確認」表示で、一覧の絞り込みにも出ない |
| perplexity | Android, iOS, macOS, Web | Webブラウザ, iPhone / iPad, Android, macOS, Windows | **Web, iOS, Android, macOS, Windows, API** | 任意：`Web, iOS, Android, macOS, Windows` へ表記を統一（下記） | Windows：公式のCometページに「Available for Mac, Windows, iOS, and Android」（Perplexity本体のWindowsアプリは今回未確認のため、Comet経由の対応として扱う）。API：docs.perplexity.ai |
| gemini | Android, iOS, Web | Web, iOS, Android, macOS | **Web, iOS, Android, macOS, API** | KEEP | support.google.com/gemini/answer/13594961 にmacOSアプリの記載。Gemini APIあり |
| claude-code | Linux, macOS, Windows | macOS, Linux, Windows, Web, iOS, Android | **macOS, Windows, Linux, Web, iOS, Android** | KEEP | 公式ドキュメント：ターミナル（macOS/Linux/Windows）、Web（claude.ai/code）、iOS/Androidアプリ。Agent SDKはライブラリで、Claude Code自体をAPIとして提供するものではないため `API` は付けない |

- 経路：platformは **WP_ADMIN_MANUAL**（クイック編集の「対応環境」欄、または投稿編集画面）。perplexityのsupported_devices表記統一を行う場合は **CONTENT_UPDATER**（任意・低優先）。
- 影響：SoftwareApplicationの `operatingSystem` と一覧の絞り込みが実態に近づく。本文・SEOへの影響なし。

---

## 4. Sora（提供終了対応の設計）

### 4-1. 公式情報（2026-10-01 再確認）

| 項目 | 内容 | 出典 |
|---|---|---|
| Web・アプリの終了日 | 2026年4月26日 | https://help.openai.com/en/articles/20001152-what-to-know-about-the-sora-discontinuation |
| APIの終了日 | 2026年9月24日（Videos APIとSora 2系モデルを削除） | 同上、https://developers.openai.com/api/docs/deprecations |
| 対象モデル | `sora-2`、`sora-2-pro`、`sora-2-2025-10-06`、`sora-2-2025-12-08`、`sora-2-pro-2025-10-06`、Videos API | Deprecationsページ（2026-03-24告知、Recommended replacementは「—」＝後継の案内なし） |
| 現在の案内 | sora.com は `sora.chatgpt.com/sunset` へ転送され「Sora is no longer available. You can export your data at any time.」と表示。エクスポートは sora.chatgpt.com/sunset から可能 | sora.com（ブラウザで確認） |
| データ | 最終エクスポート期間の終了後、Sora利用に関するデータは完全に削除される | ヘルプ記事 |
| 返金・クレジット | 返金はChatGPTサブスクリプションの返金手順を参照。購入済みのChatGPT/SoraクレジットはCodexで利用可能 | ヘルプ記事 |

### 4-2. フィールドごとの変更案（ID 679 / slug sora、ページ削除・slug変更なし）

| フィールド | 現在 | 推奨 | 経路 |
|---|---|---|---|
| tool_status | active | **discontinued**（「提供終了」表示） | WP_ADMIN_MANUAL |
| short_description | 現在形の機能説明 | 「OpenAIが提供していた動画生成AI。Web・アプリ版は2026年4月26日、APIは2026年9月24日に提供を終了し、現在は新たに利用できない。」 | CONTENT_UPDATER |
| post_content | 空 | 記録ページとして新規作成（4-3の構成） | CONTENT_UPDATER |
| pricing_type | paid | **KEEP（paid）**。enumに「終了」がないため、過去の提供形態（有料）として残す。「料金：有料」表示が誤解を招く点はコード対応（4-5）で解消する | —（任意でCODE_CHANGE） |
| has_free_plan | false | KEEP（false） | — |
| api_available | unknown | **no**（API終了済み） | CONTENT_UPDATER |
| commercial_use | unknown | **KEEP（unknown）**。サービス自体は使えないが、作成済みコンテンツの権利は別問題のため「非対応」と断定しない。本文FAQで説明する | — |
| info_checked_date | 空 | **2026-10-01**（反映日に再確認して更新） | CONTENT_UPDATER |
| omochix_view | 空 | 新規作成（4-3） | CONTENT_UPDATER |
| SEO title | 「Sora – OmochiX」（Slim SEO未設定） | 「Soraとは？提供終了の経緯と代わりの動画生成AI｜OmochiX」（35字） | CONTENT_UPDATER |
| meta description | short_descriptionと同文 | 「OpenAIの動画生成AI『Sora』は2026年4月にアプリ、9月にAPIの提供を終了。終了日、データのエクスポート方法、クレジットの扱い、代わりに使える動画生成AIをOmochiXがまとめます。」 | CONTENT_UPDATER |
| pricing_details / notes | 空 | 新規契約不可・クレジットはCodexで利用可、公式出典と確認日 | CONTENT_UPDATER |
| rating_overall | 4.5 | **削除を推奨**（提供終了ツールに評価スコアとReview構造化データを残さない）。編集部判断 | WP_ADMIN_MANUAL（クイック編集で空にすると削除される） |
| official_url | https://sora.com/ | 任意：公式ヘルプ記事へ変更。現状でも終了告知ページへ転送されるので実害はない | WP_ADMIN_MANUAL |
| 対応環境（platform） | Android, iOS, Web | **全解除を推奨**（一覧の「Web/iOS/Android」絞り込みに提供終了ツールが出ないように） | WP_ADMIN_MANUAL |
| supported_devices / supported_models | 空 | KEEP（空）。過去のモデル名を「対応モデル」欄に出すと現役に見えるため | — |

### 4-3. 記録ページの構成案（post_content）

事実は4-1の公式情報のみ。

- `<h2>Soraとは？</h2>`：OpenAIが提供していた動画生成AIで、現在は提供終了している旨を冒頭で明示。
- `<h2>Soraの提供終了について</h2>`：Web・アプリは2026-04-26、APIは2026-09-24に終了。API側で `sora-2` / `sora-2-pro` とVideos APIが削除され、後継モデルの案内はない。
- `<h2>作成したコンテンツのエクスポート</h2>`：sora.chatgpt.com/sunset からエクスポートでき、最終エクスポート期間の後にデータは削除される。
- `<h2>返金・クレジットの扱い</h2>`：返金はChatGPTの返金手順を参照、購入済みクレジットはCodexで利用可能。
- `<h2>代わりに検討できる動画生成AI</h2>`：Veo、Runway、Kling AIのOmochiX内ページへのリンクと一行説明（Batch 01の確認済み内容から）。
- `<h2>FAQ</h2>`：「Soraはもう使えない？」「作った動画は？」「APIは？」「代わりは？」。

`omochix_view` は、提供終了の事実と、動画生成AIを選ぶ際に提供継続性も判断材料になるという編集部の見解を200〜300字で書く（推測で終了理由を書かない）。

**build側の注意**：`build/merge_and_validate.py` は通常ツール向けに「料金」「使い方」「`<ol>`」セクションを必須としている。Soraは記録ページのため、別ファイル（`sora-discontinued.json`）と提供終了モードの検証ルールを追加して作る。production側のコード変更ではない。

### 4-4. インデックス方針

**index, follow を維持（noindexにしない）**。

- 「Sora 終了」「Sora 使えない」「Sora 代わり」といった検索意図が現にあり、公式情報をまとめた記録ページはその受け皿になる。
- noindexにすると既存URLの評価と流入を捨てることになる。削除・リダイレクトより、終了情報と代替ツールへの導線を持つページとして残す方が利用者にとって有益。
- ただし**「提供中」表示のまま本文が空の現状は、インデックスさせる価値が低く誤解を招く**。tool_status・本文・SEOを同じタイミングで反映すること。
- SoftwareApplicationスキーマは出力され続ける。rating_overallを削除すればReviewは出なくなる。スキーマ自体を止めるかは任意のコード対応（4-5）。

### 4-5. 「提供中」として出ないようにする方法（実装確認結果）

`tool_status` を参照しているのはツール詳細ページの「提供状況」表示だけで、次のクエリは提供終了を**考慮していない**。したがって `tool_status=discontinued` にしても、関連ツール欄やトップページからは消えない。

| 場所 | 現状 | 必要な対応 |
|---|---|---|
| ツール詳細の「関連AIツール」（`single-ai_tool.php` 173〜220行、taxonomy一致＋最新順フォールバックの2クエリ） | Veo / Kling AI / Runway / HeyGen のページでSoraが表示されている | **CODE_CHANGE**：両クエリに `meta_query`（`tool_status` が存在しない、または `discontinued` 以外）を追加 |
| トップページのツール枠（`front-page.php` 323行 注目・354行 最新順の補充） | 最新順の補充でSoraが入る可能性がある | **CODE_CHANGE**：同じ条件を追加 |
| AIツール一覧（`archive-ai_tool.php`） | 通常のカードとして表示 | 推奨：記録として一覧には残し、カードに「提供終了」バッジを出す（**CODE_CHANGE**・任意）。除外するとサイト内から辿れなくなる |
| クイックサマリーの「料金：有料」 | pricing_typeのまま表示 | 任意 **CODE_CHANGE**：discontinuedの場合は料金欄を「提供終了」と表示 |
| SoftwareApplicationスキーマ | active扱いで出力 | 任意 **CODE_CHANGE**：discontinuedではスキーマを出さない、またはrating削除で十分とする |
| プロンプト（ID 796 `video-text-to-video-shot-prompts`） | `related_tool_ids = [679, 678, 675]`（Sora, Veo, Runway）。本文の入力例も「Sora、Veo、Runway」 | **WP_ADMIN_MANUAL**：関連ツールからSora（679）を外し、必要ならKling AI（677）を追加。本文の例を「Veo、Runway、Kling AI」へ変更。repoの `sample/prompts-library-100.csv` と `Prompts-Library-100-Review.md` も次回更新時に合わせる |

最小のコード変更はテーマ2ファイル（`single-ai_tool.php`、`front-page.php`）への `meta_query` 追加。既存のクエリ構造は変えない。PR→ステージング確認→デプロイの通常フローで行う。

---

## 5. 修正の分類

| # | 修正 | 対象 | 経路 |
|---|---|---|---|
| 1 | pricing_type freemium→paid | veo | WP_ADMIN_MANUAL |
| 2 | pricing_type free→freemium | notebooklm | WP_ADMIN_MANUAL |
| 3 | 対応環境の追加・設定 | veo, runway, midjourney, perplexity, gemini, claude-code | WP_ADMIN_MANUAL |
| 4 | `--v` を `<code>--v</code>` に | midjourney（post_content） | CONTENT_UPDATER |
| 5 | How-toのstep短文化 | claude-code（post_content） | CONTENT_UPDATER |
| 6 | supported_devicesの表記統一（任意） | perplexity | CONTENT_UPDATER |
| 7 | tool_status→discontinued、rating削除、対応環境の解除、official_url（任意） | sora | WP_ADMIN_MANUAL |
| 8 | 記録ページの本文・SEO・api_available・info_checked_date・notes等 | sora | CONTENT_UPDATER（別JSON） |
| 9 | 関連ツール・トップページから提供終了ツールを除外 | テーマ | CODE_CHANGE |
| 10 | 一覧の「提供終了」バッジ、料金欄の表示、スキーマ扱い（任意） | テーマ／プラグイン | CODE_CHANGE（任意） |
| 11 | プロンプト796の関連ツールと本文例 | prompt 796 | WP_ADMIN_MANUAL（repoのCSVは次回更新時） |

推奨する反映順：①WP_ADMIN_MANUALの1〜3 → ②Batch 01のdelta JSON（4・5、任意で6）→ ③Soraは7・8・9・11をまとめて同じ日に反映（先にtool_statusだけ変えると本文が空のまま「提供終了」になるため）。

**delta JSONの方針**：Batch 01の全体JSONを再Applyしても、未変更のフィールドは「unchanged」になるので安全ではある。ただし差分を明確にするため、`slug` と `post_content` だけを持つ2件（midjourney、claude-code）の小さなJSONを別に作る。

---

## 6. Midjourney `--v`

- 原因：WordPressの `wptexturize()` が、本文中の `--` をenダッシュ（`&#8211;` ＝「–」）に変換する。`<code>` `<pre>` `<kbd>` などの中は変換対象外。
- 検証：ローカルのWordPressコアの `wptexturize()` で確認。
  - 現状：`「--v」` → `「&#8211;v」`（変換される）
  - `「<code>--v</code>」` → 変換されない
  - Claude Codeの `<code>claude --version</code>` → 変換されない（本番でも正常表示を確認済み）
- Batch 01の全10件の本文を同じ関数にかけ、影響があるのはMidjourneyのこの1か所だけと確認。
- 修正案（本文ソース `build/author_b.py` の1か所のみ）：
  - 現在：`<p>使用するモデルは、設定パネルまたはプロンプト末尾の「--v」パラメータで切り替えられます。</p>`
  - 修正：`<p>使用するモデルは、設定パネルまたはプロンプト末尾の<code>--v</code>パラメータで切り替えられます。</p>`
  - `<code>` は `wp_kses_post` で許可されており、検証スクリプトの許可タグにも含まれている。

---

## 7. How-to step length

計測（本番のレンダリング後本文、各 `<li>` のテキスト文字数）：

| slug | step数 | 最大 | 平均 | 各step |
|---|---|---|---|---|
| claude-code | 6 | **119** | 67 | 119, 40, 38, 68, 54, 88 |
| veo | 6 | 66 | 50 | 66, 65, 30, 57, 39, 46 |
| kling-ai | 6 | 47 | 35 | 34, 47, 31, 36, 39, 24 |
| cursor | 7 | 73 | 54 | 73, 37, 57, 52, 58, 53, 50 |
| （参考）heygen | 6 | 92 | 72 | 54, 85, 92, 75, 73, 56 |
| （参考）midjourney | 6 | 47 | 40 | — |

判定：品質基準のHeyGen（最大92字）を超えるのは **Claude Codeのstep 1（119字）だけ**。step 1には改行されない長いコマンド（`<code>curl … | bash</code>`）が入っており、カードが縦に伸びる主因になっている。step 6（88字）も長め。**Veo・Kling AI・Cursorは修正不要**。

Claude Codeの修正案（意味を変えず、HeyGenと同じ「見出し — 説明」の形にする。デザイン変更なし）：

```html
<ol>
<li><strong>インストール</strong> — ターミナルで公式のインストールコマンドを実行します（コマンドは下記）。</li>
<li><strong>インストールを確認</strong> — 新しいターミナルで<code>claude --version</code>を実行します。</li>
<li><strong>起動</strong> — 作業するプロジェクトのディレクトリで<code>claude</code>と入力します。</li>
<li><strong>ログイン</strong> — 初回はClaudeのサブスクリプション（Pro・Max・Teamなど）またはAnthropic APIキーでログインします。</li>
<li><strong>指示を入力</strong> — 「認証モジュールのテストを書いて実行し、失敗があれば修正して」のように自然言語で依頼します。</li>
<li><strong>使う場所を選ぶ</strong> — 必要に応じてVS Code・JetBrainsの拡張機能、デスクトップアプリ、Web（claude.ai/code）に切り替えます。</li>
</ol>
<p>macOS・Linux・WSLでのインストールコマンドは<code>curl -fsSL https://claude.ai/install.sh | bash</code>です。Windowsでは公式ドキュメントのPowerShell／CMD用コマンドを使います。</p>
```

各stepは本文換算で約30〜60字になり、長いコマンドはリスト外の段落へ移る（情報は削除しない）。

---

## 8. 画像Phase manifest（確定）

HeyGenとPerplexityは対象外（変更しない）。

| # | Tool | slug | ID | Logo | Hero | 理由（Production Audit） |
|---|---|---|---|---|---|---|
| 1 | Veo | veo | 678 | CREATE | CREATE | ロゴ・HEROとも未設定（FALLBACK） |
| 2 | Kling AI | kling-ai | 677 | CREATE | CREATE | 同上 |
| 3 | Runway | runway | 675 | CREATE | CREATE | 同上 |
| 4 | Midjourney | midjourney | 59 | CREATE | CREATE | 同上 |
| 5 | Claude Code | claude-code | 665 | CREATE | CREATE | 同上 |
| 6 | Cursor | cursor | 65 | CREATE | CREATE | 同上 |
| 7 | NotebookLM | notebooklm | 659 | CREATE | CREATE | 同上 |
| 8 | ChatGPT | chatgpt | 53 | REPLACE（ID 54） | REPLACE（ID 566） | ロゴ：黒一色の透過PNGでダークモードでほぼ見えない。HERO：UI模倣で文字が多く「GPT-5.6」表記が古い |
| 9 | Gemini | gemini | 61 | KEEP（ID 634） | REPLACE（ID 636） | HEROの画像内文字が多く、Google系プロダクトのマークを含む |

- 合計：ロゴ8（新規7＋差し替え1）、HERO 9（新規7＋差し替え2）＝**17点**。
- 規格（現行のPASS素材に合わせる）：HEROは1672×940前後の16:9、画像内の文字なし（HeyGen・Perplexityと同じ方向性）。ロゴは正方形でライト・ダーク両方の背景で視認できること（透過の単色マークは避けるか、背景付きにする）。
- 画像ファイルはwp-adminのメディアライブラリへ手動でアップロードし、attachment IDをJSONに追記してContent Updaterで反映する（Updaterは外部URLからの取得に対応していない）。
- 判断事項：NotebookLMは2026年7月にGemini Notebookへ改称済み。ロゴは現行の公式マークを使うか、編集部で決める。
- 公式ロゴは各社のブランドガイドライン・プレスキットの素材を使い、権利確認は人が行う（`docs/AI-Tools-Content-Updater.md` §9の方針）。
- Soraは画像Phaseの対象外（記録ページ化の後に必要かを判断）。

---

## 9. 残る判断事項

1. Soraの `rating_overall` 削除と、一覧に残すか（バッジ付き）の最終判断。
2. Perplexityの対応環境に、Comet経由の対応としてWindowsを含めるか。
3. NotebookLMのロゴをNotebookLMとGemini Notebookのどちらの表記にするか。
4. テーマのコード変更（§4-5）を、Sora記録ページの反映前にデプロイできるか。
