# Knowledge Hub：実際の WordPress を使った隔離環境でのテスト計画（案）

- **使う環境**：使い捨ての WordPress だけ。本番・staging・他のセッションの Local サイトは使わない。
- **前提**：hooks の #75（ローダー）と、許可リストへの `glossary` の登録が入ったコードを使う。
- **このリポジトリのテストとの関係**：WordPress なしで動く `tests/glossary/run.php`・`theme.php` では、確かめられないことを確かめる。

## 0. 環境
- WordPress（最新版）、PHP 8.1 と 8.3 の両方、SQLite か使い捨ての DB。
- Slim SEO（本番と同じ版。版は T-PRE-3 で確認）。
- ユーザー：管理者・編集者・投稿者・寄稿者・購読者。未ログインでも確かめる。
- テスト用の用語：`content/glossary/terms/*.json` の5語（下書き）、それに不足・不正な用語。

## 1. スイッチ OFF（Phase 1 の出荷状態）
| ID | 確かめること | 合格条件 |
|---|---|---|
| W-1 | `/glossary/`、`/glossary/rag/`、`/glossary/category/llm/`、`/glossary/feed/`、`/glossary/rag/embed/` | 404。`X-Robots-Tag: noindex`。存在しない slug と応答が同じ |
| W-2 | `?post_type=omx_glossary`、`?omx_glossary=rag`、`?p={ID}` | 404（T-SEC-6） |
| W-3 | REST の `/wp/v2/glossary`・`/wp/v2/glossary-categories`（未ログイン・購読者・投稿者） | 401 |
| W-3b | REST の `/wp/v2/types`・`/wp/v2/taxonomies` の一覧、`/wp/v2/search?subtype=omx_glossary` | 用語集が含まれない |
| W-4 | Slim SEO の sitemap と wp-sitemap | 用語集が無い |
| W-5 | oEmbed（`/wp-json/oembed/1.0/embed?url=`） | 埋め込めない |
| W-6 | rewrite の flush・オプションの書き込み | 起きない（`omochix_core_glossary_rewrite_version` が無い） |

## 2. 権限と公開ガード
| ID | 確かめること | 合格条件 |
|---|---|---|
| W-7 | 編集者・管理者 | 用語集のメニューで、作成・編集・削除ができる |
| W-7b | 投稿者・寄稿者・購読者 | 用語集のメニューが見えず、URL を直接開いても権限エラー |
| W-8 | DB のロール（`wp_user_roles`） | 用語集の権限が書き込まれていない |
| W-9 | 不足のある用語を、クラシックエディター・ブロックエディター・Quick Edit・Bulk Edit・REST・予約公開から公開する | どれも下書きのまま。理由が表示される |
| W-10 | 5語を、メタ情報（REST）とカテゴリ付きで公開する | 公開できる |
| W-11 | 本物の `wp_kses` | 本文の script・iframe・on 属性・`javascript:`・http のリンクが除かれる（テストでは代用の kses を使っている） |

## 3. 互換性と回帰
| ID | 確かめること | 合格条件 |
|---|---|---|
| W-12 | PHP 8.1 と 8.3 | 上の全項目で PHP の notice・warning・error が0件（`WP_DEBUG_LOG`） |
| W-13 | 既存の URL：`/`、`/ai-tools/`、ツール詳細、`/prompts/`、`/learn/*`、`/learn/industries/*`、ニュース、検索、sitemap | 状態コード・canonical・robots・JSON-LD が、導入前と同じ（スナップショットで比較） |
| W-14 | `OMOCHIX_WEB2_DISABLED` | 用語集のメニューが消え、ほかは変わらない |
| W-15 | 途中までのアップロード（ファイル1つを欠く・途中で切る） | 用語集だけが止まり、管理者に通知が出る。サイトは動く |

## 4. スイッチ ON（公開の前。テストのときだけ、テーマの1行を true にする）
| ID | 確かめること | 合格条件 |
|---|---|---|
| W-16 | 一覧・カテゴリ・詳細 | 200。title・description・canonical・パンくず・DefinedTerm／DefinedTermSet が出る。Article が重ならない |
| W-17 | 下書き・パスワード付き・不足のある用語 | 未ログインでは 404。編集者にはプレビュー（noindex, nofollow） |
| W-18 | `/glossary/page/2/` と `?s=` | 404 と 400 |
| W-19 | 用語が10語未満のとき | 一覧が noindex になる。カテゴリは3語未満で noindex |
| W-20 | 見た目と操作 | 375px・768px・1280px、ライト・ダーク、キーボードだけでの操作（フォーカスが見える、絞り込みは Escape で消せる）、スクリーンリーダー（件数の読み上げ） |
| W-21 | Lighthouse のアクセシビリティ・SEO | 既存のページと同等以上 |
