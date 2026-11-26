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
| `includes/web2/glossary-post-type.php` | post type `omx_glossary`、タクソノミー `omx_glossary_category`。スイッチが OFF のときは非公開で登録する（URL・アーカイブ・クエリ変数・検索・rewrite なし）。flush は、スイッチが変わったときに1回だけ。`custom-fields` に対応する（REST でメタを保存・取得するために WordPress が求める）。汎用の「カスタムフィールド」欄は出さない |
| `includes/web2/glossary-caps.php` | 権限。DB のロールには書かず、実行時に `edit_others_posts` を持つ人（編集者・管理者）にだけ与える。それ以外の人には、ロールに付いていても外す |
| `includes/web2/glossary-meta.php` | 14個のメタ。型・消毒・`auth_callback`（`edit_post`）付き。拒否される URL は保存しない |
| `includes/web2/glossary-visibility.php` | G-B（用語ごとの表示・index の判定）と、漏えい対策（下記） |
| `includes/web2/glossary-guard.php` | 公開ガード。規則を満たさない用語は、クラシックエディター・Quick Edit・Bulk Edit・XML-RPC・REST・cron の予約公開のどれからも公開・予約できない。公開中（publish）の用語は降格しない（G-B で隠す）。予約済み（future）の用語は公開中として扱わず、保存や「今すぐ公開」のたびに検査する。REST では、同じリクエストで送られたメタとカテゴリを含めて判定する（下の「REST での公開」） |

## 公開範囲と漏えい対策（スイッチ OFF のとき）
- **URL**：`/glossary/…` は rewrite が無いので、通常の 404 になります。`?omx_glossary=` と `?post_type=omx_glossary` も、`publicly_queryable=false` のため効きません。
  - これは Phase 0 設計の T-SEC-1・T-SEC-6 で、staging での実測が必要です。
- **REST**：`/wp/v2/glossary*`、`/wp/v2/glossary-categories*`、`/wp/v2/types/omx_glossary`、`/wp/v2/taxonomies/omx_glossary_category` は、編集者以外には 401 を返します（大文字小文字・`//`・URL エンコードの違いにも対応）。
  - スイッチを ON にした後も、Phase 1 の間は閉じたままです。
  - 一覧の `/wp/v2/types`・`/wp/v2/taxonomies` と、`/wp/v2/search` からも外します。
- **oEmbed**：用語は埋め込めません。
- **用語の URL**：訪問者に見えない用語（下書き・パスワード付き・規則に合わない・閉じている間の全用語）は、訪問者に対して URL を持ちません（`post_type_link` で空にします）。
  - WordPress は、テーマのフックが届かない場所で投稿の URL を使います。たとえば `/wp-trackback.php/{ID}` は、その投稿の URL へリダイレクトします。URL が空なら、存在しない ID と同じ応答になります。
  - 対象は用語集の投稿だけです。ほかの投稿の trackback やリダイレクトは変えていません。編集できる人には、URL をそのまま返します。
- **そのほかの入口**（どれも用語集だけが対象で、ほかの投稿の動作は変えません）：
  - trackback・pingback・コメントは、用語では常に受け付けません（保存されている設定に関係なく）。
  - XML-RPC の `pingback.extensions.getPingbacks` は、見えない用語の URL に対して、何も無い URL と同じ答えを返します。
  - REST の `/wp/v2/search?type=term` には、編集者以外には用語カテゴリを出しません。
  - REST の `/wp/v2/users` は、用語だけを書いた人を「公開の著者」として扱いません。
  - リビジョンと自動保存の投稿も、親の用語が見えないときは URL を持ちません。
  - 管理画面の検索（review N2）。編集者と管理者には、これまでどおりすべての用語が出ます。
    - リンク挿入の検索（`wp-link-ajax`。`wp_link_query_args` と `wp_link_query`）：見えない公開用語を検索から外します。見える用語は、これまでどおりリンクできます。
    - 添付の「投稿を検索」（`find_posts`）：その処理の中の検索から、用語集を外します。ファイルを添付するには、添付先の投稿の `edit_post` が要り、用語の場合は編集者しか持たないためです。
    - `read_post`：見えない用語を読めるのは、編集者だけです。メディア一覧の「アップロード先」とメディアの選択画面（`uploadedToTitle`）に、見えない公開用語のタイトルが出ていたためです。
    - コメントの管理画面（`edit-comments.php?p={ID}`）：見えない用語の画面は、非編集者には何も出さずに 403 で断ります（`comments_list_table_query_args`）。この画面は寄稿者以上が開け、どの状態の投稿でも見出しにタイトルを出すためです。
    - 管理画面のタイトル：非編集者には、管理画面（admin-ajax を含む）で見えない用語のタイトルを空にします（`the_title`）。ほかの画面に対する二重の防御です。フロント・フィード・REST・SEO・メールには効きません。
  - クエリーループのブロック（`query_loop_block_query_vars`）：用語集を対象にすると、状態を `publish` に固定し、見えない公開用語を `post__not_in` と `post__in` の両方から外します。管理画面（ブロックエディタ）でも、下書き・予約・承認待ち・非公開の用語は出ません。結果の側でも、`the_posts` で、見えない用語を落とします（二重の防御）。ページ送りと件数も、表示と一致します。編集者を含む全員に効きます。1人のために作ったページが、次の人にキャッシュで出ることがあるためです。用語集の専用テンプレートは、このフィルターを使わないので変わりません。
- **直していないこと（P3。WordPress 本体の動作で、どの投稿タイプでも同じ）**：次の3つは、ID が「何かの非公開の投稿」であることが分かります。用語集かどうかと slug は分かりません。
  - コメント投稿（`wp-comments-post.php` に `comment_post_ID={ID}`）：存在する投稿は 403、存在しない ID は空の 200。
  - REST の `/wp/v2/comments?post={ID}`：非公開の投稿は 401、存在しない ID は空の一覧。
  - REST の一覧（`/wp-json/`）と `OPTIONS /wp/v2/glossary` に、用語集のルートとメタの定義が見えます（用語のデータは出ません）。
- **表示の判定の使い回し**：1つのページで、同じ用語の判定（G-B）を何度も求めます。判定の結果は、リクエストの間だけ覚えます。用語の内容・状態・パスワード・日付・公開スイッチのどれかが変われば、別の結果として計算し直します。
- **sitemap**：WordPress 本体と Slim SEO の両方で、post type・タクソノミーごと外します。
  - ON にした後は、index されるページだけを載せます。noindex や 404 のページは載せません。
    - 用語：index できる用語だけ。
    - 一覧（`/glossary/`）：index できる用語が10語以上のときだけ。
    - カテゴリ：index できる用語が3語以上のカテゴリだけ。
  - 10 と 3 の値は Core の定数（`OMOCHIX_CORE_GLOSSARY_INDEX_MIN`・`OMOCHIX_CORE_GLOSSARY_CATEGORY_INDEX_MIN`）が持ち、テーマはそれに従います。robots・canonical・構造化データ・sitemap が同じ答えになります。
  - Slim SEO は、カテゴリの sitemap ファイルを一覧（sitemap index）に載せるかどうかを決めるとき、タクソノミー名を渡しません。そのため、載せるカテゴリが無いときも、空のファイルが一覧に残ることがあります（noindex の URL は入りません）。
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
  - **ブロックエディター**：次のものだけを、検査と表示の前に取り除きます（`omochix_core_glossary_normalize_content()`）。残ったものは、手書きの HTML と同じ規則で検査します。
    - 使えるブロックのコメント：段落・見出し（レベル2〜4）・リスト・リスト項目・引用・コード・整形済みテキスト・表。設定は、見出しのレベル、番号付きリスト、表の固定幅だけ。
    - エディターがそのブロックのタグに付ける class（`wp-block-heading` など、タグごとの固定の一覧）。
    - 表を包む `<figure class="wp-block-table">`。
  - それ以外は拒否します：ほかのブロック（カスタム HTML・画像・埋め込み・ショートコードなど）、色・配置・追加 CSS クラス・アンカー・ブロックバインディングなどの設定、一覧に無い class、表のキャプション、「新しいタブで開く」リンク（`target`・`rel`）。
  - ページは、ブロックとして描画しません。同じ処理で取り除いた HTML を、同じ許可リストの `wp_kses` に通して出します。
  - 本文のデータが 60,000 バイトを超えるものは（保存済みの用語がこれを超えている場合は、見えない用語として扱います）、中身を読まずに拒否します。ブロックエディターの形式で、上限の 6,000文字の長文（見出し24個）は約18KB、リスト200項目・表3つ（各20行×4列）・リンク80個・それらの混在は12〜26KB、10文字の項目540個のリストでも約42KB で、どれも受け付けます（テスト `size limit: ordinary content …`）。超えるのは、5文字の項目1,000個のリスト（約65KB）や、125文字の URL のリンク350個（約63KB）のような場合です。正規表現の処理が途中で止まったときも、「問題なし」ではなく拒否にします。
- **出典**：1〜8件。一次情報（official・paper・standard・docs）を最低1件含める必要があります。
  - URL は https だけです。IP アドレス・内部のホスト名・ユーザー名とパスワード・443 以外のポートは拒否します。
  - 認証情報らしいものを含む URL も拒否します。URL 全体を、そのままと URL デコードした形（3回まで）で調べます。
    - 項目名：クエリ（`a[token]=` のような入れ子も）、パス（`;jsessionid=`）、フラグメント（`#access_token=`）。`access-token`・`Access_Token`・`accessToken` は同じ名前として扱います。
      - 一覧にある名前そのもの（`token`・`key`・`code` など）、決まった語で終わる名前（`…token`・`…secret`・`…signature` など）、決まった語を含む名前（`apikey` など）が対象です。
      - `secretary=` や `credentials=include` は通します。
    - 値：API キー・JWT・Webhook の URL などの形の文字列（パス・クエリ・フラグメントのどこでも）。
    - エラーの文には、一覧にある語か、それと同じ名前だけを出します。値が入りうる文字列は出しません。
    - 既知の限界：`key=`・`code=`・`session=` は、認証情報でなくても拒否します。パスの一部として書かれた秘密（`/token/…`）や、`download_key=` のような一覧に無い名前は検出しません。
    - `#api-key` のような、見出しへのアンカーだけのフラグメントは通します。
  - `127.0.0.1.nip.io` のように、内部アドレスに解決される公開のホスト名は、今は通ります（P3。サーバーから取得はせず、DNS の問い合わせもしません）。
- **日付**：未来の日付は使えません。最終確認日は、出典の確認日より前にはできません。
  - 「今日」は、WordPress のタイムゾーン設定の日付です（`omochix_core_glossary_today()`）。WordPress が無いとき（CLI・CI）は日本時間です。UTC の日付は使いません。
- **ai_assisted**：必ず入れます。
- **秘密情報**：API キー・トークン・秘密鍵らしい文字列は、どの項目にあっても拒否します。報告には種類だけを出し、値は出しません。
  - ほかのエラーの文に値が入るとき（重複した別名など）も、その文を「内容は表示しません」に置き換えます。
  - 本文のリンクのエラーには、URL を出さず、ホスト名だけを出します（URL にはユーザー名・パスワード・鍵が入りうるため）。
  - 検査の正規表現が実行できなかったときは、「見つからなかった」ではなく、検出として扱います。

## REST での公開（ブロックエディター）
- WordPress の REST は、投稿を先に保存し、同じリクエストのメタとカテゴリをその後に保存します。
- REST 側のガードは、リクエスト全体（本文・メタ・カテゴリ）で判定します。通ったときは、これから保存されるメタとカテゴリを、投稿保存側のガードに渡します。
- 投稿保存側のガードは、保存される本文・タイトル・slug と、渡された値を合わせて、全部の規則をもう一度確かめます。規則は省きません。
- 渡した値は、その用語の、その REST 呼び出しの間だけ有効で、1回使うと消えます。拒否したリクエストの値は渡しません。
- 公開・予約のリクエストでは、メタの値が「保存する型そのまま」でないと、保存の前に 400 で拒否します（文字列・配列・整数・true/false。出典は5つの項目だけのオブジェクト）。
  - WordPress は、メタの型の変換と検査を、投稿を保存した後に行います（`"a,b"` を配列にする、未知の項目があるとエラーにする、など）。そのままだと、判定した値と保存される値が食い違います。
- 保存が全部終わった後に、保存された用語を読み直します。このリクエストで公開した用語が規則を満たしていなければ、下書きに戻します。以前から公開中の用語は戻しません。
  - WordPress が、投稿を保存した後のエラー（カテゴリやメタの保存の失敗）で処理を止めたときも、REST 呼び出しの終わりに同じ読み直しをします。
  - 下書きに戻したときは、REST の応答の状態も draft にします。下書きに戻せなかったとき（別のプラグインが状態を戻した、など）は、応答を書き換えません。
  - PHP の処理が途中で終わったとき（`wp_die`・`exit`・捕まえられなかったエラー）も、リクエストの終わり（`shutdown`）に同じ読み直しをします。
- ガードのメモ（渡す値と、公開を認めた用語）は、用語ごと・REST の呼び出しごとに持ちます。保存の途中で別の REST 呼び出しが動いても（`rest_do_request`）、外側の呼び出しのメモは消えず、途中の状態で判定されることもありません。同じ用語への呼び出しが入れ子になったときも同じです。
- 新しい用語は、行が作られた時点（`transition_post_status`）で ID をメモに付けます。`wp_insert_post` の途中で処理が止まっても、リクエストの終わりの読み直しがその用語を見つけられます。
- 状態を送らない REST の保存も、保存済みの状態が「予約」なら判定します。WordPress は、予約済みで日付が過ぎた用語を、保存のときに自動で公開にするためです。
- 自動保存の経路（`/wp/v2/glossary/{id}/autosaves`）では、公開も予約もしません。リクエストに状態が入っていても、保存から外します（下書きは下書きのまま保存されます）。
  - WordPress の自動保存は、メタとカテゴリを保存せず、保存後のフックも呼びません。また、このフィルターがエラーを返しても保存を止めません。そのため、エラーにせず状態を外しています。
- slug が未設定の新しい用語は、タイトルから WordPress が作る slug で判定します。日本語だけのタイトルは slug を作れないので、slug を入力する必要があります。
- メタの入力画面はまだありません（別の工程）。REST 以外の経路では、保存済みのメタで判定します。

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
  - `/page/2/`・feed・embed・trackback は 404、一覧での `?s=` は 400。
  - `?post_type[]=omx_glossary` のように、3つの画面（一覧・カテゴリ・詳細）以外で用語集の post type を指定した要求は 404。ほかの post type と混ぜた場合、feed・検索の場合も同じ。
  - こうした 404 では、メインクエリを空にする（feed のフラグと、見つかった投稿を消す）。WordPress の `set_404()` だけでは、404 の状態コードのまま feed に用語が出てしまうため。
  - 見えない用語へは誘導しない。`/?p=ID` は、WordPress が ID を調べる前にその ID を忘れさせ、存在しない ID と同じ扱いにする。古い slug からのリダイレクトもしない。
  - リビジョンや自動保存の ID（`/?p={リビジョンの ID}`）も、親の用語が見えないときは同じように忘れさせる。
  - 前提：post type の `exclude_from_search` は true のままにする。WordPress が slug の先頭から用語を推測してリダイレクトしないのも、`?s=` に用語が出ないのも、この設定による。サイト内検索に用語を出すときは、先に別の対策が必要。
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

- `run.php` は Core 側の488件です。
- `theme.php` は127件です：判定、SEO、構造化データ、テンプレートの漏えい、XSS、関連、Core 無しのとき、静的検査。
- どちらにも、PR #79 のセキュリティレビューで見つかった7件（P1-1〜P1-5、P2-1、P2-2）と、PR #83 の最終レビューで残った4件（F1〜F4）の再現テストが入っています（テスト名が `P1-1`・`F1` などで始まります）。
- テストは「今」を 2026-11-24 に固定しています（`omochix_core_glossary_clock()`）。固定しないと、テストの用語の日付が古くなったとき（2027年6月ごろ）に、期限切れとして失敗し始めます。本番のコードは、この関数に値を入れません。
- WordPress なしのテストでは、REST の保存順・`WP_Query`・sitemap は「モデル」で確かめています。実物での確認は `wp-test-plan.md` の W-22〜W-53 です。
- 実際の WordPress での確認は `wp-test-plan.md` にあります。
- リリースの手順は、このリポジトリにはまだありません。公開を決めるときに、main のリリース設定と Release gate に合わせて用意します。

## まだのこと（Phase 1 の残り）
- 管理画面：メタボックス、一覧の列（要再確認・出典数）、JSON インポーター（常に下書き）。
- hooks の許可リストへの登録（別の PR）と、ci の `tests.yml` への登録（別の PR）。
- staging での HTTP マトリクス（T-SEC-1〜8）と、本番の前提確認（T-PRE-1〜3）。
