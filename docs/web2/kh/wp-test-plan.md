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

## 5. PR #79 のレビューで見つかった7件の修正（スイッチ ON で確かめる）
WordPress なしのテストは、REST の保存順・`WP_Query`・sitemap をモデルで確かめています。ここは実物での確認です。

| ID | 確かめること | 合格条件 |
|---|---|---|
| W-22 | `/?post_type[]=omx_glossary`、`/?post_type[]=post&post_type[]=omx_glossary`、`/feed/?post_type[]=omx_glossary`、`/?s=a&post_type[]=omx_glossary`（未ログイン） | どれも 404。本文・feed に用語（とくに、不足のある公開中の用語とパスワード付きの用語）が出ない |
| W-23 | `/?p={下書きの ID}`、`/?p={不足のある公開中の用語の ID}`、`/glossary/{slug}/trackback/`、`/{slug の先頭}` | 存在しない ID・slug のときと、状態コード・Location・本文が同じ |
| W-24 | 通常の投稿・`/ai-tools/`・`/learn/*`・`/learn/industries/*`・検索・`/?p={投稿の ID}` | 導入前と同じ（W-13 のスナップショット） |
| W-25 | REST（編集者）：`POST /wp/v2/glossary/{id}` で `meta` を送り、`GET` で読み返す | 保存され、同じ値が返る。寄稿者・未ログインでは 401／403。未登録のキーは保存されない |
| W-26 | 編集画面 | 汎用の「カスタムフィールド」欄が出ない（設定で有効にしても出ない）。リビジョンにメタが残る |
| W-27 | ブロックエディターで、段落・見出し（H2〜H4）・リスト・引用・コード・表だけの用語を書き、公開する | 公開できる。ページに `<!-- wp:` と `wp-block-` が出ない |
| W-28 | ブロックエディターで、カスタム HTML・画像・埋め込み、色や配置の設定、追加 CSS クラス、「新しいタブで開く」リンクを入れて公開する | どれも公開できず、理由が表示される |
| W-29 | ブロックエディター：下書きに、メタ・カテゴリ・本文を入れて「公開」を1回押す（REST の1リクエスト） | 公開される（下書きに戻らない）。不足があるときは、エラーが表示されて下書きのまま |
| W-30 | REST のバッチ（`/batch/v1`）：1件目は不足のある公開、2件目は別の用語の公開 | 1件目の値が2件目に使われない |
| W-31 | クラシックエディター・Quick Edit・Bulk Edit・予約公開（W-9 の再確認） | 不足のある用語は下書きのまま。完全な用語は公開できる |
| W-32 | sitemap（Slim SEO。wp-sitemap は Slim SEO が止めている）：用語9語、10語、カテゴリ2語、3語、期限切れの用語 | sitemap にある URL が、すべて 200 で index 可（robots に noindex が無く、canonical が自分自身）。noindex・404 の URL が無い。9語のときは `/glossary/` が無い |
| W-33 | サイトのタイムゾーンを東京にして、日本時間の 0:00〜9:00 に、最終確認日が当日の用語を公開する | 公開できる（「未来の日付」にならない）。翌日の日付は拒否される |
| W-34 | feed の中身：`/feed/?post_type=omx_glossary`、`/?feed=rss2&post_type[]=omx_glossary`、`/?feed=rss2&post_type=omx_glossary&p={隠れた用語の ID}`、`/glossary/category/{slug}/feed/` | 404 で、本文に用語のタイトル・リンク・内容が1つも無い（状態コードだけでなく、本文を確かめる） |
| W-35 | `/?p={ID}` と `/index.php?p={ID}`：存在しない ID、下書き、不足のある公開中の用語 | 3つとも、状態コードと Location が同じ |
| W-36 | slug を変えた後に隠れた用語の、古い slug（`/glossary/{古い slug}/`） | 404。新しい slug へリダイレクトしない |
| W-37 | REST（編集者）で公開するときに、型の違うメタを送る：出典に未知の項目、別名をカンマ区切りの文字列、review_days を文字列 | 400。投稿は下書きのまま（公開されてから失敗、にならない） |
| W-38 | ブロックエディターで新しい用語を作り、slug を入力せずに公開する（英数字のタイトル／日本語だけのタイトル） | 英数字：公開できる。日本語だけ：slug の理由が表示され、下書きのまま |
| W-39 | REST で公開する途中で、投稿の保存後にエラーを起こす（存在しないカテゴリ ID を送る、など） | 応答はエラー。用語は公開のまま残らない（不足があれば下書きに戻る）。応答と DB の状態が一致する |
| W-40 | `/?p={隠れた用語のリビジョンの ID}&feed=rss2`、コメント投稿（`wp-comments-post.php` に `comment_post_ID={ID}`） | 存在しない ID のときとの差を記録する（WordPress 本体の動作。差があれば別の修正で対応） |

確かめるだけで、今回は直していないこと（P3）：`/wp-trackback.php/{ID}` を直接開いたときの Location（存在しない ID・下書き・不足のある公開中の用語で比べる）。差があれば、サーバー側でこのファイルへのアクセスを止めるか、別の修正で対応する。
