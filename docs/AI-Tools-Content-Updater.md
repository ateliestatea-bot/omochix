# AI Tools Content Updater

管理画面：**AIツール → コンテンツ更新**（`wp-admin/edit.php?post_type=ai_tool&page=omochix-ai-tool-content-updater`）
実装ファイル：`wp-plugin/omochix-core/admin/ai-tool-content-updater.php`
登録：`wp-plugin/omochix-core/omochix-core.php`（`require_once` 1行のみ追加）

このドキュメントは、別セッション・別担当者が同じ運用を引き継げることを目的としています。

---

## 1. 目的（purpose）

既存のAI Tools CSVインポーター（`admin/csv-importer.php`）は、新規ツールの一括登録用に設計されており、書き込むフィールドは以下の**9個のみ**です：

```
company_name, official_url, short_description, pricing_type,
japanese_support, tool_status, rating_overall, is_featured, display_order
```

`post_content`（本文）や、`key_features`/`pros`/`cons`/`has_free_plan`/`api_available`/`commercial_use`などの「v2」構造化フィールド、SEO（Slim SEO title/description）、画像（`tool_logo`/featured image）は一切書き込みません。これはバグではなく、CSVインポーターの`$meta_keys`配列がこの9個にハードコードされているためです（`admin/csv-importer.php` 372行目）。

52ツール分のコンテンツを1件ずつwp-adminで手入力するのは非現実的なため、**既存ツールをJSONファイルから安全にUPDATEできる別ツール**として、このContent Updaterを実装しました。

- **既存CSVインポーター（新規登録用）とは完全に別ファイル**として実装し、互いに一切呼び出し合いません。
- **UPDATE ONLY**：slugが既存の`ai_tool`投稿と一致しない場合は必ずエラーとし、新規作成は一切行いません。

---

## 2. 入力形式（input format）：JSONを採用した理由

検討した2案：

| | A. CSV Updater | B. JSON Updater（採用） |
|---|---|---|
| 長文post_content（HTML含む） | セル内改行・引用符のエスケープが必要で、Excel/Numbersで開くと壊れやすい | ネイティブに複数行文字列を扱える |
| 配列フィールド（key_features等） | 独自の区切り文字（`\|\|`等）が必要で、本文中に同じ文字が出ると壊れる | ネイティブな配列型 |
| boolean（has_free_plan） | `"true"`/`"1"`等、文字列としての表現がCSVでは曖昧になりやすい | ネイティブなtrue/false |
| enum（japanese_support等） | 文字列比較のみ、表記ゆれに弱い | 同じく文字列だが、周囲の型安全性でミスが減る |
| CSV injection対策 | `=`や`+`で始まるセルの対策が別途必要 | 該当なし（JSONにCSV injectionの概念がない） |
| Excel/Numbersでの破損耐性 | 長文・改行・配列を含めると高リスク | JSONエディタ/テキストエディタで直接編集するため無関係 |

**結論：JSON形式を採用。** 既存インポーターがCSVだからという理由だけでCSVに固定することはしていません。長文本文・配列・真偽値・enum・SEOという、今回のユースケースが要求する型の組み合わせに対して、JSONの方が構造的に安全です。

---

## 3. 入力ファイルの構造

```json
{
  "tools": [
    {
      "slug": "既存AIツールのslug（必須、識別専用）",
      "short_description": "...",
      "post_content": "<h2>見出し</h2><p>本文（HTML可）</p>",
      "key_features": ["...", "..."],
      "pros": ["..."],
      "cons": ["..."],
      "strengths": ["..."],
      "weaknesses": ["..."],
      "recommended_for": ["..."],
      "recommended_use_cases": ["..."],
      "not_recommended_for": ["..."],
      "pricing_details": "...",
      "api_sdk_info": "...",
      "security_info": "...",
      "notes": "...",
      "supported_devices": ["Web"],
      "supported_models": ["..."],
      "integrations": ["..."],
      "has_free_plan": true,
      "api_available": "yes",
      "commercial_use": "partial",
      "japanese_support": "partial",
      "info_checked_date": "2026-09-28",
      "omochix_view": "...",
      "seo_title": "...",
      "meta_description": "...",
      "tool_logo_attachment_id": 1234,
      "featured_image_attachment_id": 5678
    }
  ]
}
```

1ファイルに複数ツールを含められます（`"tools"`配列に複数オブジェクト）。1回の上限は200件（`OMOCHIX_CORE_TOOL_UPDATE_MAX_ROWS`）。

**対応フィールド一覧**：上記JSON例に列挙した全項目。`title`と`slug`自体は更新不可（識別専用、変更しようとするとエラー）。`rating_overall`・`is_featured`・`display_order`・taxonomy（category/feature/tag/platform）はこのUpdaterの対象外（編集部の意思決定に関わる項目のため、wp-adminでの個別判断に残しています）。

---

## 4. PATCH semantics（重要）

- **JSONオブジェクトにキーが存在しないフィールド** → 変更しない
- **値が`null`のフィールド** → キーが存在しないのと同じ扱いで、変更しない
- **文字列フィールドを明示的に空にしたい場合** → `""`を指定する
- **配列フィールドを明示的に空にしたい場合** → `[]`を指定する
- **画像フィールド（`tool_logo_attachment_id`/`featured_image_attachment_id`）は例外**：`0`は現在のバージョンでは**バリデーションエラー**（「画像を削除する」という意味には解釈しません）。画像を変更しない場合は、そのキー自体を省略してください。

### null semantics（安全性の設計判断）

`null`を「変更しない」として扱うのが最も安全と判断しました。理由：
- 多くのJSON実装で「キー省略」と「値がnull」は事実上区別がつきにくく、両者に異なる意味を持たせると事故の元になります。
- 「フィールドを明示的に空にする」という意図は、文字列なら`""`、配列なら`[]`という、その型で最も自然な「空」の表現で既に表現できます。
- `null`に「削除」のような特別な意味を持たせると、他の設定ファイルからの機械的な変換時に事故で`null`が混入した場合、意図せずコンテンツが消える危険があります。

---

## 5. Dry Run（必須ワークフロー）

1. 管理画面でJSONファイルを選択し「dry-run（検証のみ）」を実行
2. **データベースへの書き込みは一切行われません**（実測でも確認済み：dry-run前後で`wp_postmeta`の行数・`post_content`の長さが完全一致）
3. 結果画面に、対象ツールごとに **フィールド / Before / After** の表が表示される
4. エラーがあれば反映は不可（「反映を実行」フォーム自体が表示されない）

---

## 6. SHA-256ロック

Prompt Library CSVインポーター（`admin/prompt-csv-importer.php`）と同じ設計思想：

1. dry-run時にアップロードされたファイルの内容から`hash('sha256', $contents)`を計算し、画面の隠しフィールドに埋め込む
2. 「反映を実行」フォームで、**同じファイルをもう一度選択**してもらう
3. サーバー側で再度ハッシュを計算し、`hash_equals()`で比較
4. 一致しなければ反映を拒否し、「dry-runで確認したファイルと内容が異なります」と表示

**サーバー側にファイルやプレビュー結果を一切保持しません**（トランジェントもオプションも使わない、ステートレス設計）。これにより、古いプレビューが残り続けて事故を起こす可能性がありません。

動作確認済み：dry-run後にファイル内容を変更して反映を試みると、正しく拒否され、DBは変更されない（負のテスト12番で確認）。

---

## 7. Apply（反映）の安全装置

反映実行前に必ず確認される項目：

1. `manage_options`権限（既存CSVインポーターより厳格 — 全AIツールの本文を一括書き換えられる強力なツールのため）
2. nonce（`check_admin_referer`）
3. SHA-256一致
4. 確認チェックボックス（「既存AIツールX件を更新します。新規投稿は作成されません。」に同意）
5. バリデーションの再実行（アップロードされたファイルを毎回ゼロから再検証。dry-run時の結果を信用しない）

反映後の表示：**updated / unchanged / failed** の件数、失敗した場合はslugと理由。

---

## 8. Rollback（初期バージョン）

今回のバージョンでは、**snapshot export + WordPress revisions** の組み合わせを採用しました（Updater自体に「ロールバック実行」ボタンは実装していません — 安全性を優先した判断）。

- **反映直前**に、更新対象となる各投稿の完全なbeforeスナップショット（post_id, slug, post_content, 全schema meta, slim_seo, tool_logo, featured_media, timestamp）を自動取得
- 反映完了後の画面から、このスナップショットを**JSONファイルとしてダウンロード可能**（`data:` URIによるブラウザダウンロード、サーバー側にファイルを書き出さない設計）
- `ai_tool`投稿タイプは標準のWordPress `revisions`サポートが有効（`includes/post-types.php`で確認済み）。反映によって新しいリビジョンが自動的に作成されるため、**wp-adminの「リビジョン」からいつでも直前の状態に戻せます**。

将来、Updater自体にワンクリックrollback機能を追加する余地はありますが、「誤操作で別の誤ったロールバックを実行してしまう」リスクとのトレードオフのため、初期バージョンでは見送りました。

---

## 9. Images（画像）

- `tool_logo_attachment_id` / `featured_image_attachment_id` は、**既存のWordPressメディアライブラリの添付ファイルID**のみを受け付けます。
- 外部URLからの自動ダウンロード機能は**意図的に実装していません**（要求仕様どおり）。画像自体のアップロードは、引き続きwp-admin メディアライブラリから手動で行います。
- バリデーション：整数であること、`get_post_type($id) === 'attachment'`であること、`wp_attachment_is_image($id)`であること。
- `0`は現在のバージョンではバリデーションエラーです（§4参照）。

---

## 10. SEO

- Slim SEOは`slim_seo`という1つのpost meta（配列：`title`/`description`/`facebook_image`/`twitter_image`/`canonical`/`noindex`）にデータを保存しています（`Settings/Base.php`で確認）。
- このUpdaterは**既存の`slim_seo`配列を読み込み、`title`と`description`キーだけを上書きし、他のキー（canonical指定やnoindexなど、editorが個別に設定している可能性がある値）はそのまま保持**します（ブラインドな全体上書きはしません）。
- 保存時、Slim SEO自身の`Base::sanitize()`と同じ処理（`sanitize_text_field()` + 空値の`array_filter()`除去）を適用し、Slim SEO本体が保存する形式と完全に互換性を保っています。
- **canonical・robots・schema（構造化データ）は、このUpdaterからは一切変更しません。** これらは既存の`inc/seo.php`・`software-application-schema.php`のロジックにすべて委ねています（featured imageを設定すれば、`SoftwareApplication.image`スキーマは既存の仕組みで自動的に反映されます）。

---

## 11. Error handling（エラー処理）

- **行（ツール）単位でエラーを収集**し、1つのエラーで全体が止まることはありません（他の正常な行は引き続き処理対象として表示されます）。
- **エラーが1件でもあれば、反映は全体としてブロック**されます（「反映を実行」フォーム自体が表示されない）。部分的な反映は行いません。
- 反映実行中に個別の投稿でエラーが起きた場合（`wp_update_post`がWP_Errorを返す等）は、その投稿だけ`failed`として記録し、他の投稿の処理は継続します。

---

## 12. 52ツール運用フロー

```
Tool選定
  ↓
公式情報research（一次情報、日付を記録）
  ↓
production-ready content生成（本文・構造化フィールド・SEO）
  ↓
Updater JSON生成（{"tools": [ {slug, ...} ]}）
  ↓
画像をMedia Libraryへupload（wp-admin、手動）
  ↓
attachment ID追記（JSONに tool_logo_attachment_id / featured_image_attachment_id を追加）
  ↓
Dry Run
  ↓
差分確認（Before/After表）
  ↓
Apply（同じファイルを再アップロード、SHA一致確認）
  ↓
production read-only audit（public HTTP/REST）
  ↓
完了
```

このフローは、HeyGenを題材にローカルのthrowaway WordPress環境で実際に実行し、動作を確認済みです（画像アップロードの工程のみ、production write権限がないため未実施）。

---

## 13. 既知の制約・今後の検討事項

- 反映結果画面のバックアップJSONは`data:` URIによるブラウザダウンロードのため、非常に大きなファイル（多数のツールを一度に更新する場合）ではブラウザ側の制限に注意が必要です。件数が多い場合は複数回に分けて実行することを推奨します。
- ワンクリックrollback機能は未実装（§8参照）。
- 画像の外部URLからの自動取得は意図的に非対応（著作権確認を必ず人間が行うため）。
