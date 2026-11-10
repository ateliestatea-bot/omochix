# Industry Hub Phase 2 リリース計画：OmochiX Core 1.10.0・テーマ 1.29.0（暫定）

本番（テーマ 1.27.1・Core 1.9.3）に Industry Hub Phase 2 を反映するための計画。**まだ何もリリースしていない**。本番への反映・DB の変更・固定ページの作成は、それぞれ別に承認を受けてから行う。具体的な接続先・保存先・コマンドは、前回と同じく運営者の非公開の手順書にだけ置く。

## 1. 版

| 対象 | 本番 | 今回 | 備考 |
| --- | --- | --- | --- |
| OmochiX Core | 1.9.3 | **1.10.0** | Industry Hub Phase 1・2 を含む |
| テーマ | 1.27.1 | **1.29.0（暫定）** | 1.28.0 は Labs 用に残す。Labs との本番リリースの順序が変わったら番号を見直す。番号が確定するまで配布しない |

## 2. 範囲

本番との差は Core 14 ファイル（新規 12・変更 2）、テーマ 15 ファイル（新規 11・変更 4）だけ。削除するファイルはない。Labs・SVG Motion のファイルは含まない。`tests/release/release-gate.php`（CI）が、本番の基準（`tools/release/baseline/*.sha256`、2026-11-06 の P0 リリース記録と一致）と比べて、この 29 ファイル以外が変わっていないことを確かめる。

## 3. 配布物

`tools/release/`（Python 3 標準ライブラリのみ。使い方は `tools/release/README.md`）。

1. `build.py`：リリース commit から ZIP を作る。同じ commit からは同じバイト列になる（2 回ビルドして一致を CI で確認）。`.zip.sha256` と `.manifest.sha256`（`sha256sum -c` で照合できる形式）も作る。
2. `verify.py`：ZIP のハッシュ、ZIP の全エントリーとマニフェスト、マニフェストと git のツリーを照合する。
3. `plan.py`：本番の基準と比べた 29 ステップの反映計画（`release-plan.json`）、変更一覧、戻し用マニフェスト、戻し用ファイルを作る。範囲が承認と違えば止まる。

計画は commit と各 ZIP・マニフェストの SHA-256 を記録し、計画自体の SHA-256（`release-plan.json.sha256`）をリリース記録に残す。パスは相対パスだけを受け付け、`..`・絶対パス・シンボリックリンク・ZIP やマニフェストの重複は拒否する。監査ログには対象からの相対パス・SHA-256・結果だけを残し、確認コマンドの出力は残さない（`tools/release/README.md`）。

ZIP の中の時刻は commit の時刻なので、正式な配布物は main に統合したリリース commit から作る。

## 4. 反映の順序（本番は運営者の置き換えの道具 v2 で実施）

各ファイルを同じフォルダー内の一時名に置き、SHA-256 を照合してから rename で入れ替える。

1. 事前確認：版、置き換える 6 ファイルの SHA-256（戻し用マニフェスト）、新規 23 ファイルが無いこと。違えば止める。
2. バックアップ：DB と、置き換える 6 ファイル。
3. Core：モジュール 11 → `includes/industry-hub.php` → `readme.txt` → `omochix-core.php`（ここで 1.10.0 に切り替わる）。
4. 関門 A：既存ページ・管理画面・AI Tools・Motion が正常、Industry Hub の停止通知なし、ハブは非公開。
5. テーマ：CSS・部品 7・テンプレート 2・`inc/learn-industries.php` → `inc/learn.php` → `inc/shell.php` → `functions.php`（ここでハブが有効になる）→ `style.css`。
6. opcache のやり直し、LiteSpeed の全削除。
7. 関門 B：既存 31 URL、サイトマップ、どの業種のハブも 404（内容がまだ無いため）、29 ファイルの SHA-256。

使い捨ての WordPress で、本番と同じ状態からこの順に 1 ファイルずつ入れ替え、毎回 19 URL を確認した結果、どの段階でも critical error は 0 件だった（`tools/release/rehearse.py`、使い捨て環境専用）。

## 5. DB への変更（Core 1.10.0 の初回の読み込みで 1 回だけ）

新規インストールした本番相当の WordPress で、反映前と反映後の DB を比べた結果：

| 種類 | 内容 |
| --- | --- |
| ターム 17 件 | 業種 `omx_industry` 6（real-estate・restaurant・beauty・ecommerce・construction・web-production）、業務目的 `omx_purpose` 7（marketing・customer-support・document・creative・analysis・knowledge・sales）、提供元 `omx_provider_type` 4（official・external-service・creator・partner） |
| option 4 件 | `omochix_core_industry_rewrite_version`、`omochix_core_industry_gate_version`（autoload）、`omochix_core_industry_gate_cache`、`omochix_core_blocked_workflow_cache`（どちらも autoload しない） |
| option 2 件（WordPress が作る） | `omx_industry_children`、`omx_provider_type_children` |
| 変更 1 件 | `rewrite_rules`（パーマリンク規則の再生成） |

既存の投稿・ページ・メタ・設定・ユーザーは変わらない。テーブルの追加・変更はない。

## 6. ロールバック

1. テーマ `functions.php` → `style.css` → `inc/shell.php` → `inc/learn.php`、Core `omochix-core.php` → `readme.txt` を本番の版に戻す（切り替えファイルが先）。
2. **パーマリンク設定を 1 回保存する（必須）**。Core 1.10.0 が書いた rewrite 規則は DB に残るため、保存しないと `/learn/industries/` 配下の任意の URL が、検索対象のトップページの内容を 200 で返す（使い捨て環境で再現。保存すると 404 に戻る）。
3. opcache のやり直し、LiteSpeed の全削除。
4. 確認：`/learn/industries/real-estate/` と存在しない業種の URL が 404、既存 31 URL が反映前と同じ、戻した 6 ファイルの SHA-256。

残るもの（読み込まれず、表示にも影響しないことを確認済み）：新規 23 ファイル、5 章のターム・option。DB の復元は不要。消す場合は別に承認を受ける。

## 7. ステージング

今回は本番サーバー上にステージングを作らない。構築するときは次を満たす。

| 項目 | 要件 |
| --- | --- |
| 隔離 | 本番と別のサブドメイン、別の DB・DB ユーザー。本番 DB の接続情報を使わず、本番 DB に書き込まない |
| 構成 | PHP 8.4 系・WordPress 7.1.4・Slim SEO 4.9.11・LiteSpeed Cache（本番と同じ設定）。Core 1.9.3・テーマ 1.27.1 から始める |
| 認証・検索 | Basic 認証、`blog_public=0`、robots で全拒否 |
| 外部送信 | メール送信を止める（SMTP を無効化）、GA4 を外す、サイトマップの通知を止める |
| データ | 本番の DB を複製する場合は、ステージング側で、管理者を 1 名作り直して他のユーザーを削除、コメントの個人情報を削除、SMTP・API キー・外部連携の認証情報を削除、transient を削除、URL を置換してから使う。代わりに、エクスポート（投稿・ページ・タクソノミー）と AI Tools・実践教材の JSON だけを移す方法もある。複製に使うファイルは iCloud の外に置き、作業後に消す |
| 権限 | 運営者：ホスティングの管理画面（サブドメイン・DB 作成）、ステージングの SSH。作業者：ステージングの WordPress 管理者アカウントのみ |

手順：構築 → 本番と同じ状態か確認 → 4 章の順で反映（関門 A・B）→ 8 章の確認 → 固定ページの手順 → ロールバック（6 章）→ もう一度反映。結果は記録に残す。

確認項目：既存の記事・固定ページ・タクソノミー、AI Tools 52 件、Motion 53 件、初期 6 業種が 404、公開ゲート G1〜G7（不動産を順に満たし G5 で公開・G7 で index）、SEO（canonical・noindex・構造化データ・サイトマップ）、REST・フィード・埋め込み・検索で非公開業種が出ないこと、業種の内容を編集したとき LiteSpeed がハブのページを削除するか、キャッシュあり・なしの応答時間、反映の途中で止めた状態、ロールバック、opcache（手元の PHP の組み込みサーバーでは opcache がリクエスト間で保持されず、本番の挙動を再現できなかったため、ステージングで確かめる）。

## 8. 固定ページ /learn/industries/（ステージングのみ）

タイトル「業種別AI活用」、スラッグ `industries`、親ページ「Learn」、テンプレートは「デフォルト」、本文は導入文 1〜2 文。公開 → パーマリンク保存 → LiteSpeed 全削除 → 確認（一覧に公開中の業種だけ、非公開は件数だけ、Learn のカード、index 対象の業種が無い間は一覧がサイトマップに出ず noindex）。本番での作成は運営者の承認と業種の内容の準備が揃ってから。

## 9. リリースを止めている条件

1. テーマの版番号の確定（1.29.0 は暫定）
2. ステージングでの予行（7 章）
3. LiteSpeed のハブのキャッシュ削除の確認
4. 本番サーバー上の事前確認（版・6 ファイルの SHA-256）
5. 本番反映・DB 変更の承認
