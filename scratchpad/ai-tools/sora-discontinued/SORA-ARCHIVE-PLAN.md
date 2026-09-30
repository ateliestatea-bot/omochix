# Phase 3.3 — Sora 提供終了アーカイブ化（準備）

作成日：2026-10-01（JST）
対象：production post ID 679 / slug `sora` / `/ai-tools/sora/`（URL・slugは維持、削除しない、noindexにしない）
このフェーズでの本番書き込み：なし（公開REST・公開ページへのGETのみ）

## 1. OpenAI公式情報（2026-10-01確認）

| 項目 | 内容 | 出典 |
|---|---|---|
| Web・アプリの終了 | 2026年4月26日 | help.openai.com「What to know about the Sora discontinuation」／openai.com/index/sora-2/ の告知（「As of April 26, 2026, the Sora product is no longer available.」） |
| APIの終了 | 2026年9月24日 | 同ヘルプ、developers.openai.com/api/docs/deprecations |
| 終了したモデル | Videos API、`sora-2`、`sora-2-pro`、`sora-2-2025-10-06`、`sora-2-2025-12-08`、`sora-2-pro-2025-10-06`（2026-03-24告知、推奨代替は「—」） | Deprecations |
| sora.comの現在 | `sora.chatgpt.com/sunset` へ転送され、「Sora is no longer available. You can export your data at any time.」と「Export」ボタンを表示 | sora.com（ブラウザで確認） |
| データのエクスポート | sora.chatgpt.com/sunset の「Export」から実行でき、準備ができるとメールが届く。最終エクスポート期間を設ける場合は事前にメールで通知。期間の経過後、Soraの利用に関するデータは完全に削除される | ヘルプ |
| 購入済みクレジット | 購入済みのChatGPT／SoraクレジットはCodexで利用可能。返金はChatGPTサブスクリプションの返金手順を案内 | ヘルプ |
| 後継サービス | 後継の動画生成サービスの案内なし（APIの推奨代替は「—」。Sora 2記事の終了告知には「Create images in ChatGPT」への導線のみ） | Deprecations、openai.com/index/sora-2/ |
| 提供当時の情報（本文用） | 2024年2月にモデル発表。2024-12-09にSora Turboを搭載してsora.comでChatGPT Plus／Pro向けに提供（最大1080p・20秒、拡張・リミックス・ブレンド、ストーリーボード、フィード、C2PAメタデータと既定の透かし。Team・Enterprise・Eduは対象外）。2025-09-30にSora 2（会話の同期・効果音）とiOS向けソーシャルアプリ（招待制、米国・カナダから。キャラクター機能・リミックス・フィード） | openai.com/index/sora-is-here/、openai.com/index/sora-2/ |

## 2. production read-only snapshot（2026-10-01 GET）

保存先：`prod-snapshot/sora_full.json`（REST全体）、`prod-snapshot/sora_readonly_snapshot_20261001.json`（要約と関連）。いずれも本番ホスト名は伏せ字。

| 項目 | 現在値 |
|---|---|
| title / slug / status | Sora / sora / publish（最終更新 2026-09-27） |
| tool_status | active（ページに「提供中」と表示） |
| rating_overall | 4.5（Reviewの構造化データとして出力中） |
| pricing_type / has_free_plan | paid / false |
| api_available / commercial_use / japanese_support | unknown / unknown / full |
| official_url | https://sora.com/（現在は終了告知ページへ転送） |
| company_name | OpenAI |
| 対応環境（platform） | Android, iOS, Web |
| category / feature / tag | 動画生成 / 動画生成・画像生成 / スマホ対応・日本語対応・高精度 |
| supported_devices / supported_models | 空 / 空 |
| short_description | 現在形の機能説明（「…動画へ展開できるAI。…体験も備える。」） |
| post_content | 空 |
| SEO title / meta description | 「Sora - OmochiX」（表示上は「Sora – OmochiX」）／ short_descriptionと同文 |
| robots / canonical | index（noindexなし）／ /ai-tools/sora/（自己参照） |
| 構造化データ | SoftwareApplication（Review 4.5付き）ほか |
| is_featured / display_order | false / 28 |
| ロゴ / アイキャッチ | 0 / 0 |
| 関連Learn / News / Lab / Compare | すべて空 |
| 関連Prompt | ID 796 `video-text-to-video-shot-prompts`（`related_tool_ids = [679, 678, 675]`） |
| サイト内検索「Sora」 | Soraページとプロンプト796のみ |

### Soraを推薦している場所

| ページ | Soraを推薦 |
|---|---|
| トップページ | なし |
| /ai-tools/heygen/、veo、pika、descript、synthesia、runway、luma-dream-machine、kling-ai | **あり（関連AIツール欄）**＝同じカテゴリ「動画生成」の8ページ |
| /prompts/video-text-to-video-shot-prompts/ | 関連ツールとして表示、本文の入力例にも「Sora」 |

## 3. アーカイブ本文とContent Updater JSON

- 本文ソース：`build/author_sora.py` → `sora-tool.json`
- Updater入力：**`sora-discontinued-update.json`**（Soraの1件のみ、`{"tools":[…]}`）
- 検証：`build/build_and_validate.py`（build側）、`build/php_validate_sora.php <WPコアのパス>`（実バリデータをオフラインで実行）

本文の構成：Soraとは？（冒頭で提供終了を太字で明示）／どんなサービスだったか（年表）／Soraの提供終了について（Web・アプリ、API）／提供されていた主な機能／対応していたモデル／料金・クレジットの扱い／提供終了後にできること（データのエクスポート手順、返金・クレジット）／代わりに検討できる動画生成AI（Veo・Runway・Kling AIへの内部リンク）／FAQ（5問）。本文はすべて過去形・提供終了状態で記述。OMOCHIX VIEWは `omochix_view` フィールドで出力される。

### 含めたフィールド（Content Updater）

| フィールド | 新しい値の要旨 |
|---|---|
| short_description | 「OpenAIが提供していた動画生成AI。Web版・アプリ版は2026年4月26日、APIは2026年9月24日に提供を終了し、現在は新たに利用できない。」 |
| post_content | 上記のアーカイブ本文（3,802字、HTML） |
| pricing_details | 新規契約不可、提供当時のプラン扱い、クレジットはCodexで利用可、返金手順 |
| api_sdk_info | Videos API・sora-2系モデルの提供と、2026-03-24告知・2026-09-24削除、推奨代替なし |
| security_info | 提供当時のC2PAメタデータと透かし、キャラクター機能の管理、終了後のデータ削除 |
| notes | 記録ページである旨、終了日と出典、確認日 |
| has_free_plan | false（現行と同じため変更なし扱い） |
| api_available | unknown → **no** |
| japanese_support | full → **unknown**（提供当時の日本語対応を一次情報で確認できず、サービスも終了済みのため） |
| info_checked_date | 空 → **2026-10-01** |
| omochix_view | 新規（提供継続性とデータ保全を選定基準に加えるべきという見解。終了理由は推測しない） |
| seo_title | 「Sora（提供終了）とは？終了日・データのエクスポート・代わりの動画生成AI｜OmochiX」（46字） |
| meta_description | 「OpenAIの動画生成AI『Sora』は2026年4月26日にWeb版・アプリ版、9月24日にAPIの提供を終了しました。…」（119字） |

### あえて含めなかったフィールド

| フィールド | 理由 |
|---|---|
| key_features | 入れると「Soraでできること」カードが表示され、現役ツールに見える |
| pros / cons / strengths / weaknesses | 「選ぶ前に知っておきたいこと」パネルが表示される |
| recommended_for / recommended_use_cases / not_recommended_for | 「おすすめの使い方」が表示される |
| supported_devices / supported_models / integrations | クイックサマリーに「対応デバイス」「対応モデル」として現在形で出る（過去のモデルは本文に記載） |
| commercial_use | unknownのまま。サービスは使えないが、作成済みコンテンツの権利は別の問題のため「非対応」と断定しない |
| 画像フィールド、title、slug | 対象外（禁止） |

いずれも本番では空（commercial_useはunknown）のため、キーを省略すれば現状のまま（PATCH動作）。

## 4. wp-adminでの手動変更（Content Updaterでは変更できない）

| # | 対象 | 現在 → 変更後 | 画面 |
|---|---|---|---|
| 1 | Sora `tool_status` | active → **discontinued** | AIツール一覧のクイック編集「提供状況」 |
| 2 | Sora `rating_overall` | 4.5 → **削除**（空欄で保存すると削除される） | クイック編集 |
| 3 | Sora 対応環境（platform） | Android, iOS, Web → **すべて解除** | クイック編集または投稿編集画面 |
| 4 | Sora `pricing_type` | **paid のまま維持**（過去の提供形態。enumに「終了」はない） | 変更なし |
| 5 | Sora `official_url` | 任意：https://sora.com/ → 公式ヘルプ記事（help.openai.com/en/articles/20001152-…）。現状でも終了告知ページへ転送されるので必須ではない | 投稿編集画面 |
| 6 | Sora `is_featured` / `display_order` | false / 28 のまま（Phase 3.2のコードで推薦導線から自動除外されるため変更不要） | — |
| 7 | プロンプト796 `related_tool_ids` | [679, 678, 675] → **[678, 675]**（Soraを解除。代わりにKling AI 677を加えるかは任意） | プロンプト編集画面の関連ツール |
| 8 | プロンプト796 本文 | 入力例「使うツール：{例：Sora、Veo、Runway}」→「{例：Veo、Runway、Kling AI}」 | プロンプト編集画面 |

プロンプト796の対応：**解除が必要**。Soraが「このプロンプトで使えるツール」として出続け、Soraページの関連Promptにも表示されるため。repo側の `wp-plugin/omochix-core/sample/prompts-library-100.csv` と `docs/content-drafts/Prompts-Library-100-Review.md` も次回更新時に合わせる（今回は変更しない）。

## 5. SEO方針

- **index, followを維持**、canonicalは既存の `/ai-tools/sora/`。
- SEO titleの先頭に「Sora（提供終了）」を入れ、検索結果で終了済みと分かるようにした。
- meta descriptionは終了日から始め、現在利用できると読めない内容にした。
- 反映は**本文・SEOとtool_statusを同じ日に**行う（片方だけだと「提供終了」なのに本文が空、または本文は記録なのに「提供中」となる）。

### 構造化データ

`software-application-schema.php` を確認した結果：
- 出力されるのは name、url、description（=short_description）、image、applicationCategory、operatingSystem（=対応環境）、sameAs（=official_url）、provider、Review（=rating_overall）。**offersや価格、tool_statusは出力していない**。
- 手動変更の2（評価の削除）と3（対応環境の解除）を行えば、Reviewとoperating­Systemは出力されなくなり、descriptionは提供終了を明記した文になる。現役の商品として誤認させる要素はほぼ残らないため、**今回コード変更は不要**と判断。
- 任意の将来改善（別PR）：`tool_status=discontinued` の場合にSoftwareApplicationを出力しない、またはWebPageのみにする。あわせて、評価欄に「未評価」ではなく何も出さない、料金欄に「提供終了」と出す、などのテーマ側の表示調整。

## 6. Phase 3.2 のデプロイ確認

- GETだけでは**断定できない**。PHPのソースは公開されず、現在 `tool_status=discontinued` のツールが1件もないため、除外の挙動がまだ表に出ないため。
- 観測できた範囲：トップページと動画生成カテゴリの8ページはすべてHTTP 200で、関連AIツール欄も正常に出力されている。`single-ai_tool.php` だけが更新されてhelperのファイルが欠けていれば致命的エラーになるはずなので、少なくとも部分的な反映による破損は起きていない。
- 確定方法：Soraを `discontinued` にした直後に、上記8ページの関連AIツール欄からSoraが消えることを確認する。

## 7. 反映当日の手順（推奨順）

1. Content Updaterで `sora-discontinued-update.json` をdry-run → 変更12項目（post_content、short_description、pricing_details、api_sdk_info、security_info、notes、api_available、japanese_support、info_checked_date、omochix_view、SEO title、meta description）を確認 → 同じファイルでApply。
2. 同日中にwp-adminで §4 の1〜3（必要なら5）を変更。
3. プロンプト796の関連ツールと本文例を変更（§4の7・8）。
4. 確認（GET）：`/ai-tools/sora/` が200・index・canonical自己参照、「提供終了」表示、SEO titleの反映、Reviewの構造化データが消えていること。8ページの関連AIツール欄とトップページにSoraが出ないこと。プロンプト796からSoraへのリンクが消えていること。
