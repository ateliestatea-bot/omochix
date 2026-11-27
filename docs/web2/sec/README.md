# Web 2.0 セキュリティ層（sec stream）

`wp-plugin/omochix-core/includes/web2/security.php` は、WordPress 本体が描画するものを狭めるだけの層です。何かを公開することはありません。

## 読み込み
- Web 2.0 のモジュールではありません。Web 2.0 のローダーは読み込みません。モジュールの目印（`omochix-web2-module:`）もありません。
- `omochix-core.php` が、Web 2.0 のブロックより前に、直接読み込みます（hooks stream の変更）。ファイルが無いとき、読み込めないとき、防御がそろわないときは、fail-closed になります（下の「異常時」を見てください）。
- そのため、次のどの状態でも動きます。
  - モジュールのリストが空のとき（今の本番）
  - `OMOCHIX_WEB2_DISABLED` が true のとき
  - 用語集のコードが読み込まれていないとき
- 用語集の関数、公開スイッチ、許可リストには依存しません。
- Core プラグインを無効にすると、このファイルも読み込まれません。そのときは、WordPress 本体の動作のままです（下の「守れない範囲」を見てください）。

## N-1：ナビゲーションのブロックの参照
WordPress 本体の `core/navigation` は、`ref`（旧形式は `navigationMenuId`）が指す投稿が公開されていれば、そのタイトル（aria-label）とブロックを描画します。投稿タイプとパスワードは確かめません。そのため、次の投稿が漏れていました。
- 公開された用語集の用語（見えない状態のものも含みます。DB に残った行は、モジュールを読み込まなくても漏れます）
- パスワード付きの公開投稿

漏れる経路は、投稿者が公開した投稿と、寄稿者が呼べる REST の block-renderer です。

規則：`ref` があるブロックは、次のすべてを満たすときだけ、WordPress 本体のまま描画します。
- 正しい整数の形式です（int、整数値の float、符号・空白・先頭の 0 が無い数字の文字列）。REST の block-renderer は、数値を float にして渡します。
- 投稿が存在し、その ID が指定と一致します。
- 投稿タイプが `wp_navigation` です。
- 状態が `publish` です（下書き・予約・承認待ち・非公開・ゴミ箱は断ります）。
- パスワードが付いていません。

それ以外の場合は、空の文字列を返します。代わりのメニュー（fallback）も、タイトルも出しません。
- 値は、保存された属性と描画に渡る属性の両方を確かめます。形式が違う値は WordPress が描画の前に捨て、そのままだと fallback が出るためです。
- `ref` の無いブロックは、WordPress 本体のままです（中のブロックか fallback）。
- 役割、経路、公開スイッチに関係なく、同じ答えです。

仕組み：`register_block_type_args`（優先度 9999、引数2つ）で、`core/navigation` の `render_callback` を包みます。
- WordPress 7.1.4 で実測すると、ページ、入れ子、同期パターン、Query Loop、REST の投稿、block-renderer の GET と POST、フィードのどの経路も、この callback を通りました。
- 用語集のモジュール（`glossary-visibility.php`）にも同じ目的の防御があります。こちらのフィルターが登録されているときは、用語集の側は包みません。そのため、callback を包むのは1回だけです。

WordPress 本体との違い（意図した変更）：下書き・非公開・パスワード付きのメニューへの参照は、これまで fallback を出していましたが、今後は何も出しません。削除したメニューへの参照も同じです。テーマにはナビゲーションのブロックがありません。

## N-1（fallback）：参照の無いナビゲーションのブロック
`ref` も中のブロックも無いナビゲーションのブロックには、WordPress 本体が代わりのメニュー（fallback）を出します。
- 選び方は「公開された `wp_navigation` のうち、日時が最新のもの」で、パスワードは確かめません。そのため、最新のメニューにパスワードが付いていると、その中身が出ていました（2026-11-27 に実機で確認）。
  - 出ていた場所：未ログインの公開ページ、入れ子・同期パターン、REST の投稿、寄稿者の block-renderer。公開スイッチ ON・OFF、用語集の未ロードのどれでも同じでした。
  - 前提は、管理者（`edit_theme_options`）がメニューにパスワードを付けることです。それでも、守ったはずの内容が未ログインの人に出るので、P2 として扱いました。
- 規則：`block_core_navigation_render_fallback`（優先度 9999）で、本体と同じ条件の検索をもう一度行います。
  - 最新のメニュー、または同じ日時に公開されたメニューが、上の規則で許可されないものなら、fallback を空にします（何も出しません）。
  - 許可されるメニューなら、本体が作った fallback のままです。メニューが1つも無いときの、固定ページの一覧（page list）もそのままです。
- 注意：公開メニューが1つも無いと、本体は表示のついでにメニューを新しく作ることがあります（`wp_navigation_should_create_fallback`）。これは本体の動作で、この層は変えていません。

## N-1（REST）：パスワード付きメニューの本文
- WordPress 本体は、`wp_navigation` の `content.raw` に `embed` の文脈を足します。`raw` にはパスワードの確認が無いので、`/wp/v2/navigation/<id>?context=embed` と一覧の経路で、パスワード付きのメニューの中身が未ログインの人に返っていました（Fable の監査6、P2）。
- 規則：`rest_prepare_wp_navigation`（優先度 9999）で、パスワード付きのメニューは、そのメニューを編集できない人には、`content.raw` と `content.block_version` を外し、`content.rendered` を空、`content.protected` を true にします。
- タイトルは、WordPress ではパスワード付きの投稿でも公開されるので、そのままです。
- 下書き・ゴミ箱のメニューは、本体が 401 を返します。

## N-1（REST の検索）：パスワード付きメニューの中身の推測
- `/wp/v2/navigation?search=<文字列>&include=<ID>` の答え（そのメニューか、空か）で、ログインした人なら購読者でも、パスワード付きメニューにその文字列が含まれるかを1回ずつ確かめられました（Fable の監査7。271回の要求で URL を復元）。未ログインは本体が閉じています。WordPress 本体の動作で、パスワード付きの投稿全般に同じ性質があります。
- 規則：`rest_wp_navigation_query`（優先度 9999）で、メニューを編集できない人（`wp_navigation` の `edit_posts`、つまり `edit_theme_options` を持たない人）の検索からは、パスワード付きのメニューを外します（`has_password` を false）。
- fallback の同日時の確認で、最新のメニュー自身が見つからないとき（夏時間で存在しない時刻など）も、fail closed にしました。

## 異常時（fail-closed、hooks stream）
- `omochix-core.php` は、このファイルが無い・例外や構文エラーで読み込めない・4つの防御（参照、fallback、REST の本文、REST の検索）の関数かフィルターがそろわない、のどれかのときに、ナビゲーションのブロックをすべて空で描画し、REST では編集できない人にメニューの中身を返しません。
- 理由は `error_log()`（壊れている間は1要求につき1行。量が多くなるので、リリース前の検査で見つけます）と、管理者向けの通知に出します。詳しくは `docs/web2/hooks/README.md` を見てください。
- PHP の致命的エラー（関数の二重宣言など）は捕まえられないので、サイト全体が止まります。

## リリース前の完全性検査（案。`tools/release/**` は release_only なので、リリースの PR で入れます）
1. ZIP の中身：`includes/web2/security.php` が入っていて、sha256 がリリースのコミットの blob と一致すること。`omochix-core.php` に `omochix_core_sec_layer_load();` があること。
2. 事前の検査：リリースの木で `php tests/security/run.php` と `php tests/web2/hooks/regression.php` が PASS すること。
3. 反映後の確認（読み取りだけ）：管理画面に、セキュリティ層の通知が出ていないこと。PHP のエラーログに `OmochiX Core: the security layer` の行が無いこと。
4. ロールバック：前の版の Core の ZIP に戻します。この層は状態を持たないので、DB の変更はありません。戻したあとは、ページキャッシュを消します（承認が要る操作です）。

## 守れない範囲
- Core プラグインを無効にしたとき：この層も fail-closed も動きません。用語の行が DB に残っていれば、WordPress 本体の動作で N-1 が起きます。必要なら、別の常時ロードの場所（mu-plugin など）を検討します。
- Core プラグインのファイルを、この層を含まない古い版に戻したとき（ロールバック）も、同じです。

## テスト
- `php tests/security/run.php`：WordPress なしで、形式、参照先、ブロック、callback、フック、ファイルの規則（T-SAFE、独立性、PHP 8.1）を確かめます。
- CI では、ci stream の変更で、必須の「Tests (PHP 8.1)」と「Tests (PHP 8.3)」に、専用の手順「Security layer tests」として入ります（`.github/workflows/tests.yml` と `tools/ci/required-checks.json`）。固定されている「Web 2.0 tests」の手順は変えません（変えると、CI change guard が必須チェックを弱める変更と判定するためです）。PR の順は sec → ci → hooks → kh です。
- hooks stream の `tests/web2/hooks/regression.php` は、読み込みの側（`OMOCHIX_WEB2_DISABLED` のときも読み込むこと、ファイルが無い・壊れているときに影響しないこと）を確かめます。
