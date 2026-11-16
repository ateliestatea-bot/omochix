# D1.5 rewrite ルール自己修復の監査（2026-11-16）

- 対象：`wp-theme/omochix-theme/inc/design-library.php` の `omochix_design_rewrite_rules_present()`（950 行目付近）と、`init` の自己修復（964〜970 行目）。
- 方法：コードの読み取りと WordPress 7.1.4 本体の該当箇所の確認。そのうえで使い捨てのローカル WordPress（`127.0.0.1:8961`、MySQL 8.4）で、MySQL の general log を有効にして実際の SQL を数えた。本番・ステージングには接続していない。テーマのコードは変更していない。
- 機械可読の結果：`rewrite-self-heal-audit.json`（`rw_audit.py` の出力）。**ただし、その中の `S5_unfixable_20_GETs` は試験条件の誤りで無効**（保存済みのルールに自分のルールが残っていて、自己修復の条件に入っていなかった）。正しくやり直した結果は下の表の「直せない状態」。

## 1. コード上の事実

| 項目 | 内容 |
|---|---|
| 実装箇所 | `add_action('init', 匿名関数, 20)`。ルールの登録は `init`（優先度 10）の `omochix_design_register_rewrite_rules()` |
| 発火条件 | `omochix_design_rewrite_version` が `'2'` でない、または、パーマリンクが有効（`permalink_structure` が空でない）で、保存済みの `rewrite_rules` に `^design/(web\|video\|app\|components)/?$` がないとき |
| 実行内容 | `flush_rewrite_rules(false)`（soft。`.htaccess` は書かない）と `update_option('omochix_design_rewrite_version', '2')` |
| 実際に書き込むタイミング | WordPress 7.1.4 の `WP_Rewrite::flush_rules()` は `init` から呼ばれると自分を `wp_loaded` に延期する。書き込みは同じリクエストの `wp_loaded` で、`update_option('rewrite_rules', …)` 1 回（値が同じなら書き込まない）。`delete_option` はしない |
| 条件を確かめる費用 | `rewrite_rules` は autoload（約 22 KB）なので、通常時は追加のクエリなし |

## 2. ローカル試験の結果

| 状況 | 結果 |
|---|---|
| 通常時（ルールあり・版一致）、28 リクエスト | rewrite 関係の DB 書き込み **0 件** |
| ルール欠落、匿名の `GET /about/` 1 件 | `UPDATE rewrite_rules` **1 件**、そのリクエストで修復。応答 200、18 ms（通常 17 ms）。続く 30 リクエストは書き込み 0 件 |
| 版のオプションが `'1'`／削除済み | 最初の GET で版のオプションの `UPDATE`／`INSERT` 1 件。ルールは同じ内容なので `rewrite_rules` の書き込みなし。2 回目以降は 0 件 |
| 発火する入口（ルール欠落時） | 匿名の `GET /about/`・`HEAD /`・`GET /wp-json/`・`GET /wp-cron.php`・`POST /wp-admin/admin-ajax.php`（未知の action、400）・`GET /feed/`・存在しない URL（404）・`GET /wp-login.php`：**すべて発火し修復**。静的ファイル（画像）では PHP が動かないので発火しない |
| 直せない状態（別のプラグインが毎回 `/design/` のルールを消す）、匿名の GET 20 件 | **20 件すべてで作り直しが走る**。ただし生成結果が保存済みの値と同じため、DB 書き込みは **0 件**。費用は rewrite ルールの生成分（ローカルで中央値 +約 1 ms） |
| 同時アクセス（ルール欠落、40 件同時、6 ワーカー）× 5 回 | `UPDATE rewrite_rules` が各回 **7 件**（重複書き込み。DB エラー・デッドロックなし）。最終的にルールは正しく保存される。**ただし各回 `/design/` 系の 1〜3 件が 404**（5 回で計 10 件） |
| `.htaccess` | 変更なし（SHA-256 一致） |

## 3. 同時アクセスで 404 になる原因（再現・計測済み）

リクエストごとに計測した結果、作り直しを予約した 7 件のうち、ルーティングの時点でルールを持っていたのは 1 件だけだった。

1. リクエスト A と B は、どちらも「ルールが欠けた」オプションを読み込んで起動する（autoload のキャッシュ）。
2. B が先に `UPDATE` して正しいルールを保存する。
3. A も同じ値で `UPDATE` するが、DB 上は変更 0 行になる。WordPress の `update_option()` はこれを失敗として扱い、**A のメモリ上のオプションのキャッシュを更新しない**。
4. A のルーティング（`WP_Rewrite::wp_rewrite_rules()`）は、作り直した結果ではなく古いキャッシュを読み直すため、`/design/web/` などが 404 になる。

- これは WordPress 本体の flush に共通する性質で、D1.5 の自己修復に限らない。D1 の「版が変わったときに 1 回 flush する」処理でも、**初回の反映直後に同時アクセスがあれば同じ窓が開く**。
- 窓は修復が完了するまでの短い間だけ（ローカルでは同時に来た 40 件のうち数件）。修復後のリクエストは 200。
- **本番での追加の懸念（未確認）**：LiteSpeed Cache が 404 応答をキャッシュする設定だと、その間に返った 404 が、`/design/web/` などの URL にしばらく残り得る。永続オブジェクトキャッシュ（Redis など）を使っている場合は、オプションのキャッシュの共有のされ方が変わる。どちらもローカルでは試せない。

## 4. 直し方の候補（コードは未変更）

一時的な mu-plugin で次の処理を足して、同じ同時アクセス（5 回 × 40 件）を再現した：**404 は 0 件**（今のコードでは 10 件）。

- 作り直しを予約したリクエストに限り、`wp_loaded`（優先度 11。延期された flush の直後）で、オプションのキャッシュ（`alloptions` と `rewrite_rules`）を捨て、`$wp_rewrite->rules` を空にする。ルーティングのときに、DB の最新の値を読み直させる。
- 費用は、修復が走ったリクエストでのオプションの再読み込みだけ（通常時は何もしない）。

テーマへの反映は、承認を得てから行う。反映する場合は、HTTP 試験にこの同時アクセスの試験を加える。

---

## 5. 実装と検証（2026-11-16、承認を受けて実施）

### 5.1 差分（`inc/design-library.php`）

修復の分岐（版の不一致、またはルールの欠落）に入ったリクエストだけで、`wp_loaded`（優先度 11。WordPress が延期した flush の直後）に `omochix_design_reload_rewrite_rules()` を登録する。この関数は `alloptions` と `rewrite_rules` のキャッシュを捨て、`$wp_rewrite->rules` を空にする。そのため、ルーティングは DB に保存された最新のルールを読む。通常時はこの分岐に入らないので、何も変わらない。

```diff
-    flush_rewrite_rules(false);
+    flush_rewrite_rules(false); // deferred by WordPress to wp_loaded (priority 10)
     update_option('omochix_design_rewrite_version', OMOCHIX_DESIGN_REWRITE_VERSION);
+    // (理由のコメント 3 行)
+    add_action('wp_loaded', 'omochix_design_reload_rewrite_rules', 11);
 }, 20);
+
+function omochix_design_reload_rewrite_rules() {
+    global $wp_rewrite;
+    wp_cache_delete('alloptions', 'options');
+    wp_cache_delete('rewrite_rules', 'options');
+    $wp_rewrite->rules = '';
+}
```

### 5.2 試験（`tests/design-library/rewrite-heal-check.php`、24 項目）

使い捨てのローカル WordPress で、MySQL の general log を数えて確認した。

| 場面 | 修正あり | 修正を実行時に外した場合（A/B） |
|---|---|---|
| 通常時、30 リクエスト | 全件 200、rewrite・版の書き込み 0、オプションの読み込みは 1 リクエスト 1 回（再読み込みなし） | 同じ |
| ルール欠落、単独リクエスト | 最初の `/design/web/` が 200 で修復。書き込み 1 回、オプションの読み込み 2 回（再読み込み 1 回）。続く 10 件は書き込み 0、読み込み 1 回ずつ | 200 で修復（読み込み 1 回） |
| ルール欠落、40 件同時 × 5 回 | **失敗 0 件**（各回、同じ値の重複書き込みが 7 回。エラーなし。修復後の 40 件は書き込み 0） | **各回 404 が 3 件以上**（5 回すべて） |
| 初回の反映（版のオプションもルールもない）、40 件同時 | 失敗 0 | 404 あり |
| D1 からの更新（版 1、video を含まない旧ルール）、40 件同時 | 失敗 0 | 404 あり |
| ロールバック（旧テーマ `2f800b2` に切り替えてパーマリンクを再生成）→ 再反映 → 40 件同時、を計 5 回 | 失敗 0。ロールバック中の `/design/web/` は想定どおり 404 | 404 あり |

- 修正ありでは 2 回とも 24 / 24。修正を外すと 15 / 24（9 件失敗）。試験は修正の有無を見分けられる。
- ログ：`logs/heal.log`（修正あり）、`logs/heal-without-fix-ab.log`（A/B）。
- ロールバックの再現では、使い捨てサイトのテーマのシンボリックリンクを、`git archive 2f800b2` で書き出した旧テーマに付け替え、試験の終わりに必ず戻す。

### 5.3 永続オブジェクトキャッシュ：**未検証**

この端末には Redis・Memcached・PHP の拡張（redis / memcached / apcu）・`object-cache.php` の drop-in がなく、検証環境を用意できなかった（新たなインストールはしていない）。

コードから読める範囲の見込み（未確認）：永続キャッシュがある場合、`wp_cache_delete('alloptions', 'options')` は共有キャッシュのキーも消す。そのため、修復の直後に限り、ほかのリクエストもオプションを DB から 1 回読み直す。修復は条件に入ったときだけ走るので、通常時の負荷は変わらない。ただし、drop-in の実装によっては、DB の値とキャッシュの値の食い違い方が今回の再現と異なる可能性がある。本番（LiteSpeed のオブジェクトキャッシュを使う場合）では、ステージングで同じ同時アクセスの試験を行うまで未検証とする。

### 5.4 再実行した試験

| 試験 | 結果 |
|---|---|
| D1.5 の全試験（catalog 94・unit 70・http 131・isolation 72・firefox 22・ui 192・perf 32・heal 24） | すべて合格（`summary-d15-healfix.txt`） |
| 既存 86 URL（変更前のテーマ `2f800b2` の基準と比較） | 差分 0 |
| 既存の試験（Learn・Labs・Industry Hub・SVG Motion・AI Tools ほか） | すべて合格（`release-gate` の 1 件は従来どおり、D1 の新規ファイルを承認外の差分として検出する想定どおりの失敗） |
