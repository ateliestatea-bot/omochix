# Motion 50（実態 23→53）本番移行計画（M4）

作成日：2026年11月3日
対象：Theme 1.26.0（本番稼働中）→ 1.27.0。Core Plugin は変更しない。
原則：本番 WordPress・DB・FTP・公開記事には本書の作成段階で一切触れていない。配置は運営者の承認後、`omochix-learn-v2/release/omochix-theme-1.27.0.deploy.md` の手順で行う（本番 URL・認証情報は書かない）。

## 1. リリース内容

| 項目 | 内容 |
|---|---|
| 版 | Theme 1.27.0（`release/theme-1.27.0`、base main `56fa6c9`） |
| 含まれる PR | #46（TOP メニュー「×」の色）、#52（Motion 23→53、M1 文書）、#53（チップ横スクロール、M3 QA 結果） |
| 変更ファイル | 4 件、すべて置き換え：`assets/data/motion/catalog.json`、`assets/css/learn-v2.css`、`assets/css/editorial-home.css`、`style.css` |
| 追加・削除 | なし（112 ファイル、構成は 1.26.0 と同一） |
| DB / 固定ページ / 設定 | 変更なし。`learn/motion`・`learn/motion/compose` の固定ページは既存のまま |
| 配布物 | `omochix-theme-1.27.0.zip`（SHA-256 `574e19a5…905605a5…`、`.zip.sha256` 参照）、`.manifest.sha256`（112 行）、`.changes.txt`、`.rollback/`（4 ファイルの 1.26.0 版）、`.rollback.manifest.sha256`、`.deploy.md` |

## 2. 配置前の確認

1. ローカル：ZIP の SHA-256、展開物の manifest 照合、`style.css` 6 行目 `Version: 1.27.0`、`catalog.json` が 53 件（`python3 -c` などで `items` の長さ）。
2. 本番（読み取りのみ）：4 ファイルをダウンロードし、1.26.0 manifest と照合する。`editorial-home.css` は PR #46 が FTP で先行している可能性があるため、1.26.0 版と 1.27.0 版のどちらと一致するかを確定する。どちらとも一致しないファイルがあれば停止して報告する。
3. 本番の公開 slug：新規見本の `related` が参照する prompt `development-web-performance-investigation`・`development-prototype-brief-for-ai-builders`、tool `cursor`・`claude-code`・`claude`、Learn `learn/development` は、未公開なら関連リンクが出ないだけでエラーにならない（`omochix_get_published_page_url` と同じ扱い）。
4. バックアップ：4 ファイルを `release/backup-1.26.0-YYYYMMDD/` に保存し、rollback manifest と照合する。

## 3. 配置順（FileZilla、1 ファイルずつ、バイナリ）

1. `assets/data/motion/catalog.json`（213KB。転送後にサイズ一致を確認。途中状態でも旧 JSON なら 23 件で動く）
2. `assets/css/learn-v2.css`
3. `assets/css/editorial-home.css`（本番が既に 1.27.0 と同一なら省略可）
4. `style.css`（最後。`?ver=1.27.0` でキャッシュが切れる）

PHP・JS は変更しないため、配置途中でも致命的エラーは起きない。

## 4. 配置後の確認（公開判定）

| # | 確認 | 期待 |
|---|---|---|
| 1 | 外観 → テーマ | OmochiX Theme 1.27.0 |
| 2 | アクセラレータのキャッシュ削除 | 実施 |
| 3 | `/learn/motion/` | 「53件の見本」、チップ 9 個（カテゴリ 8＋すべて）、カードが 8 セクション。スマホ幅でチップ 1 行横スクロール、横スクロールなし |
| 4 | `/learn/motion/magnetic-button/`・`/learn/motion/liquid-fill/`・`/learn/motion/pop-in/` | 200、プレビュー再生、調整パネル、前後リンク。`/learn/motion/nope/` は 404 |
| 5 | `/learn/motion/?cat=cta`・`?cat=loading`・`?tech=js` | 6 件・4 件・7 件 |
| 6 | `/learn/motion/compose/` | ピッカー 53 件、3 スロットの結合プレビューと指示書 |
| 7 | `/learn/` | 注目の見本 3 件は従来どおり（pop-in / typewriter / scroll-fade-in） |
| 8 | `/`・`/ai-tools/`・`/prompts/` | 200、コンソールエラー 0。TOP のモバイルメニューの「×」が白い |
| 9 | Search Console | 変更不要（新規 URL は 30 件の `/learn/motion/<slug>/`。sitemap は Slim SEO が固定ページ単位で出すため個別登録は不要。インデックス状況は 1 週間後に確認） |

## 5. ロールバック

- `omochix-theme-1.27.0.rollback/omochix-theme/` の 4 ファイル（または配置前バックアップ）を逆順（`style.css` → CSS → `catalog.json`）で上書き。`catalog.json` を戻すだけで一覧は 23 件に戻る。DB 復元は不要。
- 判断基準：一覧が 0 件／空（JSON 破損）、詳細が 404 以外のエラー、コンソールエラー、既存 23 件の挙動の後退。

## 6. リスクと対策

| リスク | 対策 |
|---|---|
| `catalog.json` の転送が途中で切れる | サイズ一致の確認。不正 JSON は読み込み時に検証され一覧が空になるので再アップロード |
| 本番 `editorial-home.css` が未知の状態 | 配置前の照合で 1.26.0 版／1.27.0 版のどちらかに一致することを確認。一致しなければ停止 |
| キャッシュで旧 CSS/JSON が残る | `style.css` の `?ver=`、CSS は更新時刻でキャッシュバスター。アクセラレータのキャッシュ削除、ハードリロード |
| 一覧 HTML が 319KB に増える | iframe は可視分のみで初回描画 150ms 未満（Local 計測）。将来 80 件超なら「もっと見る」を検討 |
| 関連リンク先が本番に未公開 | リンクが出ないだけ。公開後に自動で出る |

## 7. 承認ポイント

1. `release/theme-1.27.0` の version 更新（`style.css` 1 行）と本書の commit。
2. push → PR → main へ merge（通常の merge commit）。
3. 本番配置（FileZilla、上記手順）。
4. 配置後の確認結果の記録（`MOTION-QA-RESULT.md` に追記、または Runbook 8b 手順 6）。
