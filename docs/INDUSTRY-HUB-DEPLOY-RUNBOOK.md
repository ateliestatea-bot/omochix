# Industry Hub（Core 1.10.0・テーマ 1.29.0）本番反映の確認手順と道具

**まだ何もリリースしていない。** この文書は、運営者が本番で行う「読み取りだけの確認」と、承認後に使う道具 `tools/release/deploy.py` の使い方をまとめたもの。接続先・アカウント・パスワードはここに書かない（運営者の非公開の手順書だけに置く）。

## 0. 前提と順序

- リリースの順序は運営者の決定どおり：テーマ 1.28.0（済み）→ **1.28.1（Learn カード）** → 1.29.0（Industry Hub）。本番への反映は 1 回に 1 つ。
- 1.28.1 が本番に入るまでは、下の「変更前の SHA-256」を**確定しない**。1.28.1 が入ったら、その本番マニフェストを基準にして計画を作り直す（6 章）。
- この文書のどの確認も、本番のファイル・DB・キャッシュ・設定に書き込まない。

## 1. 本番での読み取りだけの確認（運営者が自分で開いた SSH の画面で実行）

認証情報は運営者が入力する。コマンドの出力にも認証情報は出ない。結果は「一致・不一致」と値の先頭 12 文字だけを記録する。

### 1-1. 置き換える 6 ファイルの SHA-256

```bash
cd ~/web/wp-content && sha256sum plugins/omochix-core/readme.txt plugins/omochix-core/omochix-core.php themes/omochix-theme/inc/learn.php themes/omochix-theme/inc/shell.php themes/omochix-theme/functions.php themes/omochix-theme/style.css
```

| ファイル | 期待する値（本番 1.28.0 / Core 1.9.3） | これまでの照合 |
| --- | --- | --- |
| `plugins/omochix-core/readme.txt` | `73ba8b51c09d258a8e9b04d096fa7b2e66016169c45dcee0d0fe744b3ec7fd7e` | 公開 GET で一致 |
| `plugins/omochix-core/omochix-core.php` | `65bd0bfa4c70e80152c476d370f011ec8635f1862321a673f054a8d359df030e` | **未照合** |
| `themes/omochix-theme/inc/learn.php` | `6f42d2129af68ccedf0bcfbe0d43a7ece9bbcdfa28e2aede175b445788b121a2` | **未照合** |
| `themes/omochix-theme/inc/shell.php` | `1421a8f2e676ccc38b3cd669b889fb8f06ad93af9f240a68b174c8c712d6926c` | 1.28.0 のリリース時にサーバーで一致 |
| `themes/omochix-theme/functions.php` | `2382649ee378aac2ae583b09b4a6fade8989b5e0575acdc4dac2f7d0790c3d59` | 同上 |
| `themes/omochix-theme/style.css` | `725a4a58c79678f3a7af8b23a529c8f616729d3d2f19ff6fa9b9a5fcd430fbd8` | 同上、公開 GET でも一致 |

1 つでも違えば中止する（反映しない）。1.28.1 の後は、`style.css` などの値が変わる。

### 1-2. 配置場所・版・有効なプラグインとテーマ

```bash
ls -ld ~/web/wp-content/plugins/omochix-core ~/web/wp-content/themes/omochix-theme
```

```bash
grep -m1 "OMOCHIX_CORE_VERSION'" ~/web/wp-content/plugins/omochix-core/omochix-core.php; grep -m1 '^Version:' ~/web/wp-content/themes/omochix-theme/style.css; grep "wp_version =" ~/web/wp-includes/version.php
```

```bash
cd ~/web && wp plugin list --fields=name,status,version && wp theme list --fields=name,status,version
```

期待：2 つとも実在のフォルダー（`l` で始まらない）、Core 1.9.3、テーマ 1.28.0、`omochix-core` と `omochix-theme` が有効。`wp` が無い場合は管理画面の一覧で確認する。

### 1-3. 新規 23 ファイルが無いこと

```bash
cd ~/web/wp-content && ls plugins/omochix-core/includes/industry-hub.php themes/omochix-theme/inc/learn-industries.php 2>&1
```

期待：2 つとも「No such file」。

### 1-4. PHP と opcache の設定

```bash
php -v | head -1; python3 --version
```

```bash
php -i | grep -E '^opcache\.(enable|enable_cli|validate_timestamps|revalidate_freq|file_cache|restrict_api) '
```

- `python3` は 3.8 以上が必要（`deploy.py`）。無い場合は道具を使えないので中止する。
- `php -i` は **CLI** の値。Web で使われる PHP（LiteSpeed の lsphp）の値と違うことがある。Web 側の値は、ホスティングの管理画面の PHP 設定（読むだけ）で確かめる。確認できなければ「未確認」と記録する。
- 判断：`validate_timestamps=1` なら、最長 `revalidate_freq` 秒で新しいファイルが使われる。`0` なら、opcache のリセットをしない限り古いファイルが使われ続ける（反映直後の確認の前にリセットが必須）。

### 1-5. LiteSpeed Cache の状態（読むだけ）

```bash
cd ~/web && wp plugin get litespeed-cache --fields=name,status,version
```

管理画面の LiteSpeed Cache → Toolbox を開き、「Purge All」と「Purge All - Opcode Cache」が表示されることだけを確かめる（**押さない**）。

## 2. LiteSpeed と opcache の確認表

手元の環境では LiteSpeed と opcache の挙動を再現できない（PHP の組み込みサーバーでは opcache がリクエスト間で保持されない）。次の各項目は、本番と同じ構成のステージングで確認するまで**未確認**として扱う。

| # | 確認 | 方法 | 合格 |
| --- | --- | --- | --- |
| L1 | キャッシュ更新の手順 | 反映の後に Toolbox → Purge All - Opcode Cache → Purge All | 既存 31 URL が反映後の内容、業種のハブは 404 |
| L2 | 新旧の PHP が混ざった状態 | 関門 A（Core 1.10.0・テーマ 1.28.0）で止め、opcache をリセットせずに 31 URL を確認 | critical error 0、ハブは 404 |
| L3 | opcache のリセットと再検証 | 1-4 の値を記録し、リセット前後で `omochix-core.php` の版の表示（管理画面のプラグイン一覧）が切り替わるかを見る | リセットの後に 1.10.0 |
| L4 | ロールバック後のキャッシュ | ロールバック → パーマリンクの保存 → opcache リセット → Purge All | `/learn/industries/real-estate/` と存在しない業種が 404、31 URL が反映前と同じ |
| L5 | Labs・SVG Motion | 各段の後に `/labs/`、`/labs-sitemap.xml`、ツール詳細 1 件、`/learn/svg-motion/` | すべて 200、SVG のページは noindex のまま、Labs の CSP が SVG のページに出ない |
| L6 | ハブのページのキャッシュ削除 | ステージングで業種の内容を編集 | LiteSpeed がそのハブのページを削除する |

## 3. 道具 `tools/release/deploy.py`

- 書き込み先は `~/web/wp-content/plugins/omochix-core` と `~/web/wp-content/themes/omochix-theme` に固定。書き込み先を指定する引数は無い（`--qa` は使い捨ての複製専用で、`wp-content/.omochix-rehearsal` が無いと動かない。本番にその印があれば止まる）。
- 計画は、承認時に記録した SHA-256（`--plan-sha256`）と一致しないと読まない。計画の横の `.sha256` ファイルは信用しない。
- 書き込むファイルは ZIP の中から直接取り出す（展開したフォルダーは使わない）。ZIP とマニフェストは計画の SHA-256 と一致し、重複・`..`・絶対パス・シンボリックリンクのエントリーは拒否。
- フォルダーは 1 段ずつ、シンボリックリンクをたどらずに開く。各ファイルは同じフォルダーの新しい一時名に書き、fsync・再照合し、置き換える直前に元のファイルの SHA-256 をもう一度確かめてから rename する。
- 書き込みには、ロック（`lock` が出すトークン）、確認の文字列、WordPress の版（`wp-includes/version.php`）が必要。書き込みの間はロックのファイルを排他的に握る（flock）ので、同じトークンでも 2 つ同時には動かない。
- 書き込みの途中で失敗したら、自分が作った一時名（`.<名前>.ih-new`）を消してから止まる。強制終了などで一時名が残った場合は、その段で止まるので、`clean-temps`（確認の文字列 `IH-CLEANTEMPS`）で一時名だけを消してから、もう一度実行する。
- パッケージのフォルダー（`plugins/omochix-core`・`themes/omochix-theme`）は作らない。無ければ止まる。
- 対象として使うのは計画に書かれたものだけで、計画は Core とテーマの両方でも、テーマだけ（1.28.1）でもよい。
- バックアップは web の外の新しいフォルダー（0700・ファイル 0600・各ファイルの SHA-256 と権限を書いた MANIFEST 付き）。反映もロールバックも、先にバックアップを照合する。ロールバックは MANIFEST の権限で戻す。
- 監査ログは web の外（0600）。対象からの相対パス・SHA-256・結果だけを残す。
- DB・キャッシュ・rewrite 規則には触れない。計画の `after_apply`・`after_rollback` を表示するので、運営者が行う。

### 3-1. 使う順（すべて承認の後。`P` は計画、`X` は記録した計画の SHA-256、`V` は WordPress の版）

| 段 | コマンド | 確認の文字列 |
| --- | --- | --- |
| 道具の照合 | `sha256sum deploy.py`（記録した値と一致） | — |
| 事前確認 | `python3 deploy.py preflight --plan P --plan-sha256 X --dist . --expect-wp V` | —（PASS 以外は中止） |
| ロック | `python3 deploy.py lock --plan P --plan-sha256 X --confirm IH-LOCK` | `IH-LOCK`（トークンが出る） |
| バックアップ | `python3 deploy.py backup … --dist . --expect-wp V --token T --backup ~/ih-backup-<日付> --log ~/ih-<日付>.jsonl --confirm IH-BACKUP` | `IH-BACKUP` |
| Core（関門 A まで） | `python3 deploy.py apply … --until A --confirm IH-APPLY` | `IH-APPLY` |
| 関門 A | ブラウザで確認（既存ページ・管理画面・AI Tools・Motion・Labs・SVG、Industry Hub の停止通知なし、ハブは 404） | — |
| テーマ（関門 B まで） | `python3 deploy.py apply … --confirm IH-APPLY`（続きから） | `IH-APPLY` |
| 確認 | `python3 deploy.py verify --plan P --plan-sha256 X --want release` → opcache リセット → Purge All → 関門 B | — |
| ロック解除 | `python3 deploy.py unlock --plan P --plan-sha256 X --token T --confirm IH-UNLOCK` | `IH-UNLOCK` |

### 3-2. ロールバック

`python3 deploy.py rollback … --expect-wp V --token T --backup ~/ih-backup-<日付> --log ~/ih-<日付>.jsonl --confirm IH-ROLLBACK` → `verify --want production` → **パーマリンク設定を 1 回保存（必須）**（`wp rewrite flush`、`--hard` は付けない）→ opcache リセット → Purge All → `/learn/industries/real-estate/` と存在しない業種が 404、31 URL が反映前と同じ。新規 23 ファイルは残しても読み込まれない（`--remove-added` で消せる。別に承認を受ける）。

## 4. 計画の SHA-256 の保管

承認した計画の SHA-256 は、ZIP や計画と別の場所（PR のコメントと運営者のリリース記録の両方）に残す。`deploy.py` には毎回この値を手で渡す。計画と `.sha256` を同時に差し替えられても、記録した値と比べれば見抜ける。

## 5. 予行の結果（使い捨ての WordPress。本番の測定ではない）

道具のレビュー（2026-11-11）の指摘（一時名の後始末、例外時のパスの表示、ロックの排他、一時名の差し替え、ほか 11 件）を直した版で、もう一度実行した。


本番と同じ構成の手順（`~/web/wp-content` の形、Core 1.9.3・テーマ 1.28.0）で、`lock → backup → apply --until A → 関門 A → apply → verify → rollback → unlock` を実行。各書き込みの後の確認 29/29・戻しの後 6/6、HTTP 95/95、アクセス制御 25/25、Labs・SVG Motion 維持、壊れたバックアップでの戻しは何もせずに停止、戻した後の 31 URL は反映前と同じ、監査ログに絶対パス 0。

## 6. 1.28.1 との関係

1.28.1（Learn カード）は、今わかっている範囲では `page-learn.php`・`assets/css/learn-v2.css`・`style.css` を変える。Industry Hub はこのうち `style.css` を置き換えるので、1.28.1 が本番に入ったら次をやり直す：基準（`tools/release/baseline/`）を 1.28.1 の本番マニフェストに替える → PR #61 に 1.28.1 を取り込み、テーマの版は 1.29.0 のまま → `plan.py` で範囲を再計算（数は固定しない）→ 変更前の SHA-256・ロールバックの値・1-1 の表を更新 → テストと予行。
