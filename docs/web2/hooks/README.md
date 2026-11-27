# Web 2.0 モジュールローダー（hooks）

Web 2.0 の各機能（用語集など）を、既存の機能を壊さずに1つずつ読み込むための共通ローダーです。

| | Core | テーマ |
|---|---|---|
| ローダー | `wp-plugin/omochix-core/includes/web2-loader.php` | `wp-theme/omochix-theme/inc/web2-loader.php` |
| 読み込む場所 | `omochix-core.php` の末尾 | `functions.php` の末尾 |
| モジュールの置き場所 | `includes/web2/<名前>.php` | `inc/web2/<名前>.php` |
| 関数の接頭辞 | `omochix_core_web2_` | `omochix_web2_` |
| API の定数 | `OMOCHIX_CORE_WEB2_LOADER_API`（= 1） | `OMOCHIX_WEB2_LOADER_API`（= 1） |
| 通知を見られる人 | `activate_plugins` | `switch_themes` |

- 2つのローダーは同じ設計で、コードの違いは4行だけです（モジュールの置き場所・フィルターの第3引数・通知の権限・通知の文面）。違いが増えると `tests/web2/hooks/run.php` が失敗します。
- 読み込み行は `is_readable` と `try/catch` で囲んでいます。ローダーのファイルが無い・壊れている・アップロードの途中でも、プラグインとテーマは今までどおり動きます。
- Core は自分のモジュールだけを、テーマはテーマのモジュールだけを読みます。テーマは Core に依存しません。

## いまの状態

**許可リストは空です。** 読み込むモジュールは1つもありません。

- 許可リストが空のうちは、ローダーは WordPress の関数を1つも呼ばず、フックも1つも追加しません。
- 既存の登録内容（post type・タクソノミー・メタ・rewrite・フック）、関数、出力は、ローダーが無い場合と同じです。`tests/web2/hooks/regression.php` で確認しています。

## モジュールが読み込まれる条件

次のすべてを満たしたモジュールだけを読み込みます。1つでも満たさなければ、そのモジュールだけを読まずに、理由を記録します。

1. `OMOCHIX_WEB2_DISABLED` が true ではない。
2. 許可リストに `'enabled' => true` で載っている（既定はオフ。`1` や `'1'` はオフ扱い）。
3. フィルター `omochix_web2_module_enabled` が、ちょうど `true` を返す。
4. id が `[a-z][a-z0-9-]*`（40文字まで）で、ファイルが `includes/web2/<名前>.php`（テーマは `inc/web2/`）である。
5. 通常のファイルで、512 KB 以下である。
6. 2行目と最終行に、id と API が一致するマーカーがある。
7. マーカーの API がローダーの API と同じである。
8. 構文解析が通る。
9. 名前空間を使っていない。
10. PHP の外に HTML を書いていない。
11. 宣言する関数・クラスがすべて `omochix_` で始まり、ファイルの中で重複せず、まだ存在しない。

補足：
- 4：ほかのフォルダー、サブフォルダー、`..`、絶対パスは使えません。シンボリックリンク（ファイルでもフォルダーでも）は拒否します。
- 6：アップロードの途中で切れたファイルや、別のモジュールのファイルを検出するためです。
- 8：`token_get_all(..., TOKEN_PARSE)` で調べるので、構文エラーはファイルを実行する前に見つかります。

条件を満たしたファイルは、`try/catch` と出力バッファーの中で `require_once` します。

- 例外（`Error` を含む）が起きたとき、または何かを出力したときは、そのファイルで定義されたフックのコールバックをすべて外し、出力は捨てます。
- 関数の定義は残ります（PHP では取り消せません）。フックが外れるので、呼ばれることはありません。

## 状態の確認と停止

- 状態の確認：`omochix_core_web2_module_status( $id )`／`omochix_web2_module_status( $id )`
  - 返る値：`loaded`、`off`、`disabled`、`unknown`、または読まなかった理由（`missing`・`bad-id`・`bad-path`・`too-large`・`bad-marker`・`api-mismatch`・`syntax`・`namespace`・`output`・`prefix`・`redeclare`・`error`）
- 読み込まれたかどうかだけを知るとき：`omochix_core_web2_module_loaded( $id )`／`omochix_web2_module_loaded( $id )`
- テーマのモジュールが Core 側のモジュールを必要とするときは、テーマのモジュール自身が `function_exists( 'omochix_core_web2_module_loaded' ) && omochix_core_web2_module_loaded( '<id>' )` を確認します。ローダーは依存関係を解決しません。
- **全体を止める**：`wp-config.php` に `define( 'OMOCHIX_WEB2_DISABLED', true );` を書きます。Core とテーマの両方で、モジュールを1つも読まなくなります（運営者が行う作業です）。
- **1つだけ止める**：`add_filter( 'omochix_web2_module_enabled', fn( $on, $id, $side ) => 'glossary' === $id ? false : $on, 10, 3 );`（mu-plugin などで）。`$side` は `core` か `theme` です。
  - このフィルターでできるのは止めることだけです。モジュールを追加したり、オフのモジュールを有効にしたりはできません。
- 読めなかったモジュールがあるときだけ、上の表の権限を持つ人に、管理画面で通知を出します。
  - 通知には id と理由だけを出し、パスは出しません。
  - 通知の文字列は `esc_html` を通します。

## セキュリティ層（sec stream、常に読み込む）
- `omochix-core.php` は、Web 2.0 のブロックの直前に `includes/web2/security.php` を直接読み込みます（`is_readable` と try/catch で守ります）。
- これはモジュールではありません。ローダーのリストにも載せません。WordPress 本体が描画するものを狭めるだけなので、`OMOCHIX_WEB2_DISABLED` が true のときも、モジュールのリストが空のときも読み込みます。
- ファイルが無いとき、例外を出すとき、途中で切れているとき、空のときは、何もしないで先に進みます（`tests/web2/hooks/regression.php`）。
- 中身と規則は `docs/web2/sec/README.md` を見てください。

## ローダー自身がしないこと

次のことはしません。`tests/web2/hooks/run.php` が、記録用の stub での実行と、トークン単位の静的検査で確認しています。

- ルート、rewrite、REST、管理画面のページ・メニュー、権限、ロール、post type、タクソノミーの登録
- オプション・メタ・投稿の書き込み、`$wpdb` の利用
- リクエストのデータ（`$_GET`・`$_POST`・`$_REQUEST`・`$_COOKIE`・`$_SERVER` など）の読み取り
- HTTP 通信、ヘッダー、cookie、シェル、ファイルの書き込み、ディレクトリの一覧（`glob` などの自動探索）
- 機能を公開するかどうかの判断。各モジュールの公開スイッチ（既定は false）が決めます。

ローダーがフィルターを読むのは `omochix_web2_module_enabled` だけで、追加するフックは、失敗があったときの `admin_notices` だけです。

## モジュールの書き方

```php
<?php
// omochix-web2-module: glossary api=1
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function omochix_core_glossary_init() { /* ... */ }
add_action( 'init', 'omochix_core_glossary_init', 11 );

// omochix-web2-module: glossary api=1 (end)
```

- 2行目と最終行のマーカーは必須です。
  - Industry Hub の `// omochix-industry-hub-version:` は使いません（Release gate がその数を数えています）。
  - Core の版番号にも連動させません。
- 読み込みのときにしてよいのは、関数・クラスの宣言と `add_action`／`add_filter` だけです。
  - 出力、`exit`、DB への書き込み、定数・グローバル変数の変更はしないでください。
- 関数・クラスの名前は `omochix_` で始めます。stream ごとに接頭辞を分けてください（例：`omochix_core_glossary_`）。
- 公開スイッチは、各モジュールが自分で持ちます（既定は false）。

## モジュールを登録する手順（hooks の PR）

1. その stream のモジュールのファイルが、先に統合ブランチへ入っている。
2. hooks が、許可リストに1行を足す PR を出す。

```php
function omochix_core_web2_modules() {
	return array(
		'glossary' => array( 'file' => 'includes/web2/glossary.php', 'enabled' => true ),
	);
}
```

3. テーマ側も必要なら、`omochix_web2_modules()` に `inc/web2/...` を足す。

## 防げないこと（PHP がリクエストを止める）

- **構文は正しいがコンパイルで失敗するコード**（クラスの中で同じメソッドを2回宣言する、など）
  - `try/catch` でも捕まえられません。
  - リポジトリにあるものは、CI の Lint PHP（`php -l`、PHP 8.1／8.3）が検出します。
- **`exit`／`die`、メモリや実行時間の上限**（モジュールのファイルを実行している間）
- **検査を通ってから読み込むまでの間に、ファイルが置き換えられた場合**（アップロード中の競合）
  - リリースでは、SHA-256 の一覧と照合します。

`tests/web2/hooks/run.php` の「limit」の項目は、ここに書いた限界が実際にそのとおりであることを確認するテストです。そのため、この文書と実際の動作の食い違いを防げます。

## テスト

```bash
php tests/web2/hooks/run.php
```

```bash
php tests/web2/hooks/regression.php
```

- `run.php`：ローダー単体のテストです。
  - 各ケースを別の PHP プロセスで実行するので、致命的エラーは終了コードで判定できます。
  - Core とテーマの両方で、同じケースを実行します。
- `regression.php`：既存の機能に影響が無いことを確認します。比べる対象は、ローダーが無い・途中まで・例外を投げる・停止定数がある、の各場合です。
  - モジュールのフォルダ（Core の `includes/web2/`、テーマの `inc/web2/`）には、許可リストに載る前のモジュールが置かれることがあります。読み込むかどうかを決めるのは許可リストで、フォルダの中身ではありません。
  - そのため、次のことも確認します。
    - 許可リストが、ソースでも実行時でも空であること
    - フォルダのファイルが1つも実行されていないこと（副作用のあるモジュールを置いても動かないこと）
    - フォルダのすべてのファイルがローダーの規則（構文、namespace と出力の禁止、`omochix_` の接頭辞）を守り、入口のファイルがローダーの検査（marker、API の版、重複定義）を通ること
    - 規則に反するモジュールは、許可リストに載せても読み込まれないこと
    - フォルダのモジュールを許可リストに載せて有効にしても、公開スイッチが閉じたままで、公開される post type・タクソノミー・rewrite が増えないこと
- どちらも WordPress なしで動きます。
- 構文検査は、既存の Lint PHP（全 `*.php`）で自動的に行われます。
- 上の2つのテストを CI で実行するには、ci が `tests.yml` に追加する必要があります（ci が所有するパスなので、別の PR で行います）。

## ロールバック

| どこで | 方法 |
|---|---|
| 統合ブランチ | revert の PR |
| 本番（リリース後） | ① `wp-config.php` に `OMOCHIX_WEB2_DISABLED`（運営者）<br>② `includes/web2-loader.php`／`inc/web2-loader.php` を削除する（`is_readable` で読み飛ばされる）<br>（`OMOCHIX_WEB2_DISABLED` はセキュリティ層を止めません。セキュリティ層だけを外すときは `includes/web2/security.php` を削除します）<br>③ 通常のリリースのロールバック（`docs/Beta-Deployment-Runbook.md`） |
