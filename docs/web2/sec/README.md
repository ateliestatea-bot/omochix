# Web 2.0 セキュリティ層（sec stream）

`wp-plugin/omochix-core/includes/web2/security.php` は、WordPress 本体が描画するものを狭めるだけの層です。何かを公開することはありません。

## 読み込み
- Web 2.0 のモジュールではありません。Web 2.0 のローダーは読み込みません。モジュールの目印（`omochix-web2-module:`）もありません。
- `omochix-core.php` が、Web 2.0 のブロックより前に、直接読み込みます（hooks stream の変更）。ファイルが無いとき、または読み込みで例外が出たときは、何もしないで先に進みます。
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

## 守れない範囲
- Core プラグインを無効にしたとき：この層は動きません。用語の行が DB に残っていれば、WordPress 本体の動作で N-1 が起きます。必要なら、別の常時ロードの場所（mu-plugin など）を検討します。
- Core プラグインのファイルを、この層を含まない古い版に戻したとき（ロールバック）も、同じです。

## テスト
- `php tests/security/run.php`：WordPress なしで、形式、参照先、ブロック、callback、フック、ファイルの規則（T-SAFE、独立性、PHP 8.1）を確かめます。
- このファイルは、まだ CI のテストの一覧に入っていません。一覧に入れるのは ci stream の変更です（`.github/workflows/tests.yml` と `tools/ci/required-checks.json`）。
- hooks stream の `tests/web2/hooks/regression.php` は、読み込みの側（`OMOCHIX_WEB2_DISABLED` のときも読み込むこと、ファイルが無い・壊れているときに影響しないこと）を確かめます。
