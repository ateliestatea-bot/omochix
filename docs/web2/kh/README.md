# Knowledge Hub（AI用語集）Phase 1

設計の全体（URL・テンプレート・SEO・テスト計画・未決事項）は Phase 0 設計書にあります。この文書は、このブランチ（`web2/kh/glossary-phase1`）で実装した部分の仕様です。

## 状態
- **読み込み**：Core の `omochix-core.php` は、このモジュールを直接読みません。hooks の Web 2.0 ローダー（`includes/web2-loader.php`、PR #75）が、許可リストに `glossary` を載せたときだけ読みます。
  - 許可リストへの登録は、hooks の別の PR で行います。
  - それまでは、このブランチのファイルは何もしません。
- **公開**：公開スイッチ G-A（フィルター `omochix_core_glossary_public`）は既定で false です。ちょうど `true` を返したときだけ開きます。
  - `1` や `'1'` では開きません。
  - Phase 1 では開きません。
- **テーマ**：`inc/web2/glossary.php`（テーマ側の Web 2.0 ローダーが読む）とテンプレートを実装済みです（下の「テーマ側」）。

## ファイル（Core、`wp-plugin/omochix-core/`）
| ファイル | 内容 |
|---|---|
| `includes/web2/glossary-loader.php` | 入口。Web 2.0 ローダー用の版マーカー（`glossary api=1`）を持つ。下の6ファイルを、版マーカー（`omochix-glossary-version: 1`）を確かめてから全部か無しで読む。失敗したら、そのファイルのフックを外し、管理者に通知する |
| `includes/web2/glossary-validate.php` | データの規則（WordPress なしで動く）。インポーター・公開ガード・CI のコンテンツ検査が、同じ規則を使う |
| `includes/web2/glossary-post-type.php` | post type `omx_glossary`、タクソノミー `omx_glossary_category`。スイッチが OFF のときは非公開で登録する（URL・アーカイブ・クエリ変数・検索・rewrite なし）。flush は、スイッチが変わったときに1回だけ |
| `includes/web2/glossary-caps.php` | 権限。DB のロールには書かず、実行時に `edit_others_posts` を持つ人（編集者・管理者）にだけ与える。それ以外の人には、ロールに付いていても外す |
| `includes/web2/glossary-meta.php` | 14個のメタ。型・消毒・`auth_callback`（`edit_post`）付き。拒否される URL は保存しない |
| `includes/web2/glossary-visibility.php` | G-B（用語ごとの表示・index の判定）と、漏えい対策（下記） |
| `includes/web2/glossary-guard.php` | 公開ガード。規則を満たさない用語は、クラシックエディター・Quick Edit・Bulk Edit・XML-RPC・REST・cron の予約公開のどれからも公開・予約できない。公開中の用語は降格しない（G-B で隠す） |

## 公開範囲と漏えい対策（スイッチ OFF のとき）
- **URL**：`/glossary/…` は rewrite が無いので、通常の 404 になります。`?omx_glossary=` と `?post_type=omx_glossary` も、`publicly_queryable=false` のため効きません。
  - これは Phase 0 設計の T-SEC-1・T-SEC-6 で、staging での実測が必要です。
- **REST**：`/wp/v2/glossary*`、`/wp/v2/glossary-categories*`、`/wp/v2/types/omx_glossary`、`/wp/v2/taxonomies/omx_glossary_category` は、編集者以外には 401 を返します（大文字小文字・`//`・URL エンコードの違いにも対応）。
  - スイッチを ON にした後も、Phase 1 の間は閉じたままです。
  - 一覧の `/wp/v2/types`・`/wp/v2/taxonomies` と、`/wp/v2/search` からも外します。
- **oEmbed**：用語は埋め込めません。
- **sitemap**：WordPress 本体と Slim SEO の両方で、post type・タクソノミーごと外します。
  - ON にした後は、index できる用語だけを載せます。
- **index の期限**：最終確認日に `review_days`（既定180日）を足した日を過ぎると「要再確認」になります。
  - さらに30日（`OMOCHIX_CORE_GLOSSARY_NOINDEX_GRACE_DAYS`）を過ぎると noindex になり、sitemap からも外れます。
  - これは Phase 0 の D-3 の推奨値です。運営者の決定が必要です。

## データの規則（要点）
詳細は `glossary-schema.json` と `glossary-validate.php` にあります。食い違うときは PHP の規則が優先です。

- **slug**：ASCII の小文字・数字・ハイフンで、2〜60文字。予約語は使えません。
- **よみ（reading）**：ひらがなと長音（ー）だけ。
- **summary**：40〜120文字のプレーンテキスト。
- **本文**：使えるタグは許可リストのものだけで、リンクは https の `<a href>` だけです。script・style・on〜 属性・`javascript:` は拒否します。
  - 文字数は 400〜2,000 が目安で、外れると警告です。6,000 を超えるとエラーです。
- **出典**：1〜8件。一次情報（official・paper・standard・docs）を最低1件含める必要があります。
  - URL は https だけです。IP アドレス・内部のホスト名・ユーザー名とパスワード・443 以外のポート・認証情報らしいクエリは拒否します。
- **日付**：未来の日付は使えません。最終確認日は、出典の確認日より前にはできません。
- **ai_assisted**：必ず入れます。
- **秘密情報**：API キー・トークン・秘密鍵らしい文字列は、どの項目にあっても拒否します。報告には種類だけを出し、値は出しません。

## コンテンツの検査（seo stream の原稿）
```bash
php tests/glossary/validate-content.php /path/to/content/glossary
```

```bash
php tests/glossary/validate-content.php /path/to/content/glossary --release
```

- 2本目の `--release` を付けると、50語そろっていること、各カテゴリに3語以上あることもエラーとして扱います。
- このツールは形式だけを確かめます。出典が本当にその内容を述べているかは、人が確認します（内容 QA、Phase 0 §8.4）。

## テスト
```bash
php tests/glossary/run.php
```

- WordPress なしで動きます。既存の `tests/industry-hub/stub.php` を変えずに読み込みます。
- 途中までのアップロードの場面は、別のプロセスで確かめます。
- CI で実行するには、ci の PR で `tests.yml` に追加する必要があります（保護パスなので `ci-change-reviewed` が必要）。

## テーマ側（`wp-theme/omochix-theme/`）
| ファイル | 内容 |
|---|---|
| `inc/web2/glossary.php` | 入口（`glossary api=1`）。下の項目を担う |
| `archive-omx_glossary.php`・`taxonomy-omx_glossary_category.php`・`single-omx_glossary.php` | 一覧・カテゴリ・詳細のテンプレート |
| `template-parts/glossary/*.php` | カード、絞り込み、一覧、関連、404 |
| `assets/css/glossary.css` | 既存のトークンだけを使い、ライトとダークに対応 |

`inc/web2/glossary.php` が担うこと：
- **公開スイッチ**：`omochix_glossary_public_switch()` が常に false を返すので、Phase 1 は閉じたまま。公開は、このリリースの1行を変えて行う。
- **判定**：
  - 見えない用語（下書き・パスワード付き・規則に合わない）は、存在しないページと同じ 404 になり、noindex と no-cache を付ける。
  - 編集者には、noindex・nofollow のプレビューを出す。
  - `/page/2/`・feed・embed は 404、一覧での `?s=` は 400。
  - 閉じている間の `/glossary/…` にも noindex を付ける。
- **index**：一覧は index できる用語が10語以上、カテゴリは3語以上のときだけ index する（G-C）。
- **SEO**：title、description、canonical（index できるページだけ）、robots、パンくず。
- **構造化データ**：
  - index できる用語のページに DefinedTerm、一覧とカテゴリに DefinedTermSet（index できる用語だけ）を出す。
  - noindex のページには何も足さない。
  - 用語のページからは Article・SoftwareApplication・FAQPage を外す。
- **関連**：見える用語だけを出す。AI ツールは公開中で提供終了でないもの、Learn は `/learn/` の下の公開ページ、Practice は公開中のもの、業種は公開中のもの。
- **絞り込み**：ページの中で絞り込み、何も送信しない。
  - JavaScript が無くても、全件とカテゴリのリンクは使える。
  - ひらがな・カタカナ、全角・半角、大文字・小文字の違いを吸収する。
  - Escape で消せ、件数を `aria-live` で読み上げる。
  - スクリプトは固定の文字列で、DB の値を書き込まない。
- **本文の HTML**：用語集専用の許可リストで `wp_kses` を通し、https のリンクにだけ `rel="noopener noreferrer external"` を付ける。

## テスト（Phase 1B）
```bash
php tests/glossary/run.php
```

```bash
php tests/glossary/theme.php
```

- `run.php` は Core 側の198件です。
- `theme.php` は64件です：判定、SEO、構造化データ、テンプレートの漏えい、XSS、関連、Core 無しのとき、静的検査。
- 実際の WordPress での確認は `wp-test-plan.md` にあります。
- リリースの手順は、このリポジトリにはまだありません。公開を決めるときに、main のリリース設定と Release gate に合わせて用意します。

## まだのこと（Phase 1 の残り）
- 管理画面：メタボックス、一覧の列（要再確認・出典数）、JSON インポーター（常に下書き）。
- hooks の許可リストへの登録（別の PR）と、ci の `tests.yml` への登録（別の PR）。
- staging での HTTP マトリクス（T-SEC-1〜8）と、本番の前提確認（T-PRE-1〜3）。
