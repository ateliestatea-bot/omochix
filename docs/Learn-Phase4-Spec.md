# Learn Phase 4 — 実践教材（Learn Practice）仕様

更新日：2026年10月21日
設計の根拠：Phase 4A 設計監査（2026年10月21日、共有ノート `Learn-Phase4A-Design-Audit.md`）。本書は実装した内容の正本で、`docs/Learn-v2-Spec.md`（Motion Gallery / Composer）と並ぶ。

## 1. 目的と範囲

「見る → 学ぶ → 試す → 組み合わせる → AIで実装する → 検証する」を 1 ページで完結させる教材の型を追加する。既存の Learn 固定ページ（32）、Motion Gallery / Composer、Prompts、AI Tools の URL と挙動は変えない。

Phase 4B（本書の範囲）：データモデル（Core Plugin 1.7.0）、一覧・単体・分野ハブ・ハブ連携・逆引きブロック（Theme）、テスト。
Phase 4C 以降：JSON インポータ、代表 3 教材、Motion 新規見本、50 件の制作。

## 2. 情報設計

| URL | 実体 | 備考 |
|---|---|---|
| `/learn/practice/` | CPT アーカイブ | GET 絞り込み `field / format / level / ai / tech / q`。絞り込み時は `noindex,follow`、canonical は一覧 |
| `/learn/practice/{slug}/` | CPT 単体 | 分野を URL に含めない（付け替えで URL が変わらない） |
| `/learn/{field}/` | 固定ページ（運営者作成） | 分野ハブ。slug = `learn_field` のターム slug。`motion` は既存 Gallery が兼ねる |
| `/learn/field/{slug}/` 他 | タームアーカイブ | 一覧テンプレートで描画。`learn_field / format / ai / tech` は noindex・sitemap 除外、`learn_usecase` は 2 件以上で index |

分野（`learn_field`、seed 8）：web / apps / motion / video / automation / ai / projects / devtools。既存セクションの所属：basics・work → ai、development・tools → devtools、tutorials → projects、motion → motion（ターム付与は運営者がページ編集画面で行う）。

形式（`learn_format`、seed 5）：project（作る）/ lesson（学ぶ）/ demo（試す）/ reference（調べる）/ prompt（使う）。

## 3. データモデル（Core Plugin）

- CPT `learn_practice`：`includes/practice-post-type.php`。rewrite `learn/practice`、`has_archive`、`show_in_rest`（`learn-practice`）、supports title / editor / excerpt / thumbnail / revisions / custom-fields / author。
- タクソノミー：`includes/practice-taxonomies.php`。`learn_field`（階層、page にも）、`learn_format`、`learn_usecase`（page にも）、`learn_ai`（slug は `ai_tool` の post_name と揃える）、`learn_tech`。seed は `omochix_core_get_practice_initial_terms()`。プラグイン更新時の flush と seed は `omochix_core_practice_maybe_flush_rewrite_rules()`（option `omochix_core_practice_rewrite_version`）。
- メタ：`includes/practice-meta-schema.php`。

| キー | 型 | 必須 |
|---|---|---|
| `practice_summary` | 120 文字まで | ◯ |
| `practice_difficulty` | beginner / intermediate / advanced | ◯ |
| `practice_minutes` | 0 以上の整数 | ◯ |
| `practice_outcome` | 文 | ◯ |
| `practice_preview_type` / `practice_preview_src` / `practice_preview_poster` | image / srcdoc / youtube、本文、添付 ID | – |
| `practice_prompts` | rows `{title, for, body}` ≤20 | ◯ |
| `practice_code` | rows `{label, lang, file, body}` ≤20 | – |
| `practice_prereq` / `practice_safety` / `practice_publish` / `practice_env` | 1 行 1 項目 | safety ◯ |
| `practice_steps` | rows `{title, body(html), prompt, code, check}` ≤40 | ◯ |
| `practice_verify` | rows `{title, how, expect}` ≤20 | ◯ |
| `practice_troubleshooting` | rows `{symptom, cause, fix}` ≤20（よくあるエラー、4C で追加） | – |
| `related_practice_ids` / `related_prompt_ids` / `related_tool_ids` / `related_learn_ids` | ID 配列 | – |
| `related_motion_slugs` | catalog slug ≤6 | – |
| `practice_verified_at` | Y-m-d | ◯ |
| `practice_sources` | rows `{label, url}` ≤20 | ◯ |
| `practice_changelog` | rows `{date, note}` ≤50 | – |

rows は JSON 配列で入力（管理画面のテキストエリア、4C のインポータも同じ形）。許可キー以外は捨て、`body` は `code_text`（`<` `>` を保持。表示時に必ず `esc_html`）、`steps.body` は `wp_kses_post`。JSON として読めない入力は前回値を保持して通知。`practice_preview_src` の `</script` `</style` は無害化。

- 関連：`includes/practice-relations.php`。順方向は `omochix_core_get_related_content()`、逆引きは `omochix_core_get_practice_for_prompt / _for_tool / _for_page / _for_motion`（serialized LIKE → 厳密確認）。
- 管理画面：`admin/practice-meta-boxes.php`（8 パネル、必須未入力の通知、関連ピッカーは `data-relation-*` を再利用）、`admin/save-practice-meta.php`。
- 鮮度：`practice_verified_at` から 90 日（`omochix_core_practice_stale_days` フィルター）で「要再検証」。

## 4. テーマ

- `inc/learn-practice.php`：絞り込みの正規化と URL、`pre_get_posts`（tax_query AND、難易度は meta、q は s、12 件）、タームアーカイブ → 一覧テンプレート、enqueue（`assets/css/learn-practice.css`、`assets/js/learn-practice.js`）、`omochix_learn_practice_item()`、Motion 連携（存在する slug だけ、先頭 3 件で Composer `#c=`）、AI 実装指示書 `omochix_learn_practice_brief()`（PHP のみ。Claude Code / Codex は冒頭行だけ違う）、SEO（noindex / canonical / description / パンくずに分野 / sitemap 除外）。
- テンプレート：`archive-learn_practice.php`、`single-learn_practice.php`、`template-parts/learn-practice/{card,filter-bar,related}.php`。
- 既存への接続（本文は変えない）：`page-learn.php`（「分野から探す」はハブページが 2 つ以上公開されたとき、「作る」は Composer / 実践教材 / Video の 3 枚に更新）、`page-learn-article.php`（「この内容を実践する」：このページを参照する教材、分野ハブは同分野の教材）、`single-prompt.php`・`single-ai_tool.php`・`learn-gallery/detail.php`（逆引きブロック）、`inc/shell.php`（allow-list）、`functions.php` / `search.php`（サイト検索に `practice`）。
- 単体ページの構成：ヘッダー（分野・形式・難易度・所要・検証日・対応AI）→ プレビュー（image / サンドボックス srcdoc / クリックで埋め込む YouTube）→ 作るもの → 前提 → 手順（STEP カード、プロンプトとコードは `<details>` + コピー）→ 検証（表、チェックはこのブラウザに保存）→ 安全性 → 公開 → AI 実装指示書（コピー・.md 保存・プロンプトまとめてコピー）→ あわせて使う → 参照仕様 → 更新履歴 → 本文。PC（≥1024）は右レール（目次・操作・使用技術）、SP は目次を先頭に、画面下に固定バー。
- 秘密情報：公開コンテンツにはプレースホルダー `＜ここに貼る：…＞` のみ。指示書にもその旨を 1 行入れる。

## 5. テスト

- `tests/learn-practice/run.php`（37 件）：登録内容（CPT / タクソノミー / メタ / REST スキーマ / seed / flush 1 回）、sanitizer（要約・enum・整数・日付・preview・配列・slug・rows・妥当性・鮮度）、必須チェック、item 正規化、Motion → Composer リンク、分野ハブ URL、絞り込みと URL、アーカイブクエリ、指示書（見出し・STEP とプロンプト／コード・フェンスのエスケープ・壊れた参照・Codex 差分）、逆引きの LIKE パターンと厳密確認、noindex。
- 既存：`tests/learn-composer/run.php` 46、`core-parity.js` 78、`tests/learn-gallery/run.php` 134、`params-parity.js` 1760 は無変更で通過。
- スタブサーバーは CPT を描画できないため、画面確認は Local（本物の WordPress）で行う。

## 6. 運用

- 分野ハブ 8 ページの作成は運営者（Runbook 8d）。作成前はハブの「分野から探す」が出ず、既存のカテゴリカードのみ。
- 教材が 0 件の間、`/learn/practice/` は「準備中」、ハブの「作る」は「準備中」カード。
- 教材の作成は管理画面 → Learn 実践教材。必須項目が揃うまで公開しない（通知が出る）。
- 既存 Learn 固定ページには「分野」「用途」を付与できる（ページ編集画面）。

## 7. Phase 4C — JSON インポータ・代表 3 教材・カメラワーク見本（2026年10月22日）

### 7.1 JSON インポータ
- `includes/practice-importer.php`（ロジック）と `admin/practice-json-importer.php`（管理画面：Learn 実践教材 → JSON インポート、`manage_options`）。
- 形式：`{"version": 1, "items": [ {"slug", "title", "status", "excerpt", "content", "menu_order", "meta": {…}, "terms": {"learn_field": […], …}, "relations": {"prompts": [slug], "tools": [slug], "learn": ["learn/…"], "practice": [slug], "motion": [catalog slug]}} ]}`。1 ファイル 5MB・200 件まで。
- 流れ：dry-run（DB 無変更）→ 同じファイルを再アップロード（SHA-256 一致・確認チェック）→ 反映 → 更新前スナップショット JSON のダウンロード。ログは option `omochix_core_practice_import_log`（最新 20 件：日時・実行者・SHA・件数・slug）。
- 検証：不明キー・不明タクソノミー・存在しないターム・配列でない rows・不正な日付・画像 ID はエラー。`publish` は必須項目（説明・所要・完成イメージ・プロンプト・手順・検証・安全性・検証日・出典）が揃わないとエラー。関連の不明 slug は警告（無視）。
- 秘密情報リント：`sk-`、`AIza`、GitHub token、AWS key、Slack token、Slack / Discord webhook URL、秘密鍵ブロック、JWT、LINE トークン様の長い base64、`api_key = …` などの代入。1 件でもあれば反映不可。
- 非破壊：slug で既存と照合し、JSON に無いキー・`null` は変更しない。`""` / `[]` は空にする。同じファイルの再実行は「変更なし」。
- テスト：`tests/learn-practice/run.php` 50 件（decode・lint・plan・create・再実行・部分更新・クリア・ログ・サンプル 3 件の検証）。

### 7.2 代表 3 教材（`sample/practice/*.json`、status は draft。公開は運営者が管理画面で）
| slug | 分野 / 形式 | 内容 |
|---|---|---|
| `corporate-site-contact-line` | web + automation / project | 5 ページの企業サイト + 問い合わせフォーム + メールと LINE Messaging API への通知。トークンは環境変数のみ |
| `nextjs-login-dashboard` | apps / project | Next.js + Supabase（Auth + RLS）のログインとメモのダッシュボード、E2E 3 本 |
| `video-camerawork-prompts` | video + motion / lesson + prompt | 7 要素テンプレートとサービス別（Sora / Veo / Runway / Kling）の書き分け、比較して直す手順 |

各教材は完成イメージ・難易度・所要・対応AI・技術・プロンプト・コード・手順・検証・安全性・公開手順・よくあるエラー（`practice_troubleshooting`、4C で追加）・出典・更新履歴・関連（教材 / Motion / プロンプト / ツール / Learn）を持つ。

### 7.3 Motion 見本の追加
- カテゴリ `camera`（カメラワーク、order 6）と見本 021 ドリーイン `dolly-in`、022 パン `pan`、023 オービット `orbit`（CSS のみ、各 4〜5 パラメータ）。既存 20 件は無変更。
- Web 上の CSS 再現と、動画生成 AI へ渡すカメラワークの語彙を区別する（各見本の `prompt.use` に明記。生成結果を保証する表現は使わない）。
- テスト：`tests/learn-gallery/run.php` の件数を 23 / 6 カテゴリに更新、`params-parity.js` 2024 件。

### 7.4 未実装（4D 以降）
ニュース記事末尾の「Learn で実践」、既存 Learn 記事の目次・コピー、難易度ラベルの一元化、`ai_tool.related_learn_ids` ピッカーの教材対応、50 件の制作。

## 8. Phase 6A — 登録・編集・公開管理（Core Plugin 1.9.0、2026年10月25日）

Theme は変更しない。契約（メタキー・型・JSON 形式）は 4C のまま。運用手順は `docs/Learn-Practice-Editorial-Guide.md`。

### 8.1 JSON エクスポート（S1）
- `includes/practice-exporter.php` と `admin/practice-json-exporter.php`（Learn 実践教材 → JSON エクスポート、`manage_options`）。すべて / 公開中 / 下書き、または一覧の一括操作「JSON エクスポート」で選んだ投稿。
- 出力はインポータと同じ形（`version: 1`、`source: "export"`）。関連は slug（Learn ページはパス）で書き出し、空のメタ・空のタクソノミー・0 の表示順は省略。保存済みの空は既定値に置き換えない（難易度が勝手に `beginner` にならない）。書き出した直後のファイルを dry-run すると全件「変更なし」。
- インポータは「バックアップ JSON」（反映画面でダウンロードした更新前スナップショットの配列、または `{"backups": […]}`）も受け付け、復元用の items に変換する（空だった項目は `""` / `[]` で空に戻す）。dry-run 画面に「バックアップ JSON を読み込みました」と表示。

### 8.2 管理一覧（S2）
- `admin/practice-list-table.php`。列：分野・形式（絞り込みリンク）・難易度・所要・検証日（未検証 / 要再検証バッジ）・必須（欠落数、ツールチップにキー）・slug。並び替え：難易度（初級→上級）・所要・検証日（メタが無い投稿も残す）。
- 絞り込み：分野・形式・難易度（初級はメタ未設定も含む）・「要再検証・未検証のみ」・「必須欠落のみ」（`post__in`）。管理画面のメイン一覧だけに効く。
- クイック編集：難易度・所要時間・最終検証日（不正な日付は保存済みの値を消さない）。一括編集：難易度。

### 8.3 公開ガードと保存時 lint（S3）
- `includes/practice-publish-guard.php`。未公開 → publish / future の遷移で、必須 9 項目の欠落または秘密情報らしき文字列（タイトル・抜粋・本文・メタ、インポータと同じパターン）があれば止める。
- クラシック経路（post.php・クイック編集・一括編集）：`wp_insert_post_data` で status を draft に戻し、理由を通知。同じリクエストで送られた meta box / クイック編集の値は加味する。
- ブロックエディタ（REST）：`rest_pre_insert_learn_practice` が WP_Error（400）を返し、エディタにそのまま表示される。必須欠落のときは「下書き保存してから公開」を案内（meta box はエディタの保存成功後に保存されるため）。
- 公開中の教材は降格しない（警告通知と一覧の「必須」バッジのみ）。
- 保存時 lint：`admin/save-practice-meta.php` で秘密情報らしき値を含むキーは保存せず前回値を保持し、通知する。

### 8.4 メタのリビジョン（S4、第一案）
- `register_post_meta` に `revisions_enabled`（25 キー）。エディタ・クイック編集・インポートの保存でメタがリビジョンに入り、「この版に戻す」でメタも戻る。
- インポートの更新は反映前と反映後をリビジョンに記録する（変化が無ければ WordPress が作らない）。
- Local 検証手順：`docs/Learn-Phase6A-S4-Revisions-QA.md`。NG なら第二案（hidden meta の履歴）。

### 8.5 JSON フィールドの整形・検証（S5）
- 各 JSON パネルに「整形」「検証」ボタンと状態表示（行数 / 空 / 読めない理由）。保存前に読めないフィールドへスクロール。サーバ側の「読めない JSON は前回値を保持」は従来どおり。

### 8.6 テスト
`tests/learn-practice/run.php` 91 件（4C の 60 に、エクスポート往復・バックアップ復元 8、一覧 10、ガード 11、リビジョン 2 を追加）。
