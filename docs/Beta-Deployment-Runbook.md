# β公開 Deployment Runbook

更新日：2026年8月12日

対象：OmochiX β公開

前提：本書に本番URL、DB認証情報、Secret、Token、SMTP情報は記載しない。値は承認済みの安全な管理手段から投入する。

## 事前条件

- 本番責任者、公開日時、正式ドメイン、会社・法務情報が確定している。
- `feature/home-mvp`の公開候補コミットがレビュー済みで、正式な統合手順が承認されている。
- Theme、Core Plugin、WordPress、PHP、Slim SEOの互換バージョンを記録する。
- メンテナンス時間とRollback判断者を決める。

## 公開手順

### 1. バックアップ

1. 公開先のDBと`wp-content`を別保管先へ完全バックアップする。
2. 復元テスト済みのバックアップ日時・担当者・保存先だけを作業記録へ残す。
3. 認証情報は作業記録へ貼り付けない。

### 2. Git状態確認

1. 作業branch、HEAD、tracking branchを確認する。
2. Working treeがcleanであることを確認する。
3. 公開対象のdiffと承認済みcommitを照合する。
4. 本Runbook実行中にforce pushや無承認mergeを行わない。

### 3. Theme配置

1. `wp-theme/omochix-theme/`を公開先の`wp-content/themes/omochix-theme/`へ配置する。
2. 所有者・権限を公開環境の標準へ合わせる。
3. 管理画面でOmochiX Themeを有効化する。

### 4. Core Plugin配置

1. `wp-plugin/omochix-core/`を`wp-content/plugins/omochix-core/`へ配置する。
2. Themeとは独立して配置・バージョン管理する。

### 5. Core Plugin有効化

1. OmochiX Core Pluginを有効化する。
2. `ai_tool`と4 taxonomyの管理画面・REST公開設定を確認する。
3. PHP Fatalがないことをログと管理画面で確認する。

### 6. Slim SEO導入

1. 検証済みバージョンを公式配布元から導入・有効化する。
2. title、description、canonical、OGP、X Card、Organization、WebSite、Article系の所有をSlim SEOへ任せる。
3. SoftwareApplicationはOmochiX Core Pluginだけが出力することを確認する。

### 7. WordPress基本設定

1. サイトタイトルを`OmochiX`へ設定する。
2. キャッチフレーズを承認済み文言へ設定する。
3. サイト言語を日本語、タイムゾーンを`Asia/Tokyo`へ設定する。
4. パーマリンクを投稿名へ設定する。
5. 公開直前に「検索エンジンがサイトをインデックスしないようにする」がオフであることを責任者と確認する。

### 8. 固定ページ作成

1. Home、AIニュース、About、Contact、Privacy、Terms、運営者情報を承認済み内容で用意する。
2. 未確定ページは公開リンクへ出さない。
3. 本番会社情報や法務文面を推測して補完しない。

### 9. Home / 投稿ページ設定

1. 表示設定を固定フロントページへ切り替える。
2. ホームページにHome、投稿ページにAIニュースを指定する。
3. `/`と`/ai-news/`が200で正しいテンプレートを使うことを確認する。

### 10. ai-newsカテゴリー

1. 通常投稿カテゴリー`AIニュース`、slug `ai-news`が存在することを確認する。
2. 重複タームを作らない。

### 11. AIツール50件Import

1. Import前にCSVヘッダー、UTF-8、enum、slug重複を再検証する。
2. 最初は下書きとしてImportし、件数・新規・更新・エラーをプレビューで照合する。
3. 既存投稿更新時に公開状態が維持されることを確認する。
4. 内容・URL・taxonomyを確認後、承認済みツールだけを公開する。

### 12. パーマリンクflush

1. 管理画面のパーマリンク設定を開き、内容を変えずに保存する。
2. `/ai-tools/`、AI Tool詳細、taxonomy URLを確認する。

### 13. Header / Footer menu

1. 公開済みページだけがリンク表示されることを確認する。
2. 準備中項目と未確定SNSが外部トップページへ誤リンクしていないことを確認する。
3. Mobile menuの開閉、Esc、Focus、背景スクロール防止を確認する。

### 14. Site Icon

1. 512×512px以上の承認済み正方形画像を管理画面から設定する。
2. favicon、Apple Touch Icon、小サイズ表示を確認する。

### 15. OGP

1. Default、AI News、AI Tool用の承認済み1200×630px画像を登録する。
2. 優先順位が個別設定 → アイキャッチ → Defaultとなることを確認する。
3. AI Toolの正方形ロゴを共有画像として自動利用しない。

### 16. 法務・運営ページ

1. Privacy、Terms、運営者情報、Contact、所在地、対応時間を責任者が承認する。
2. Contact送信先、個人情報の扱い、保存期間、spam対策を確認する。
3. 仮文言や未確認会社情報が残っていないことを確認する。

### 17. Sitemap

1. 公開中のSitemap URLが1系統だけであることを確認する。
2. Home、post、ai_tool、必要なpage、category、ai_tool_categoryを確認する。
3. 検索、404、下書き、filter URLが含まれないことを確認する。

### 18. robots

1. 本番`robots.txt`のSitemap URLが本番HTTPSを指すことを確認する。
2. noindex対象をDisallowしていないことを確認する。
3. AI学習crawler方針は運営判断を記録してから変更する。

### 19. HTTP確認

Home、AIニュース一覧・詳細、AI Tool一覧・詳細、About、Search、404、taxonomy、Sitemap、robotsのstatusを確認する。意図しないredirect、404、500、mixed contentがないことを確認する。

### 20. SEO / Schema確認

1. title、description、canonical、robots、OGP、X Cardの重複がないことを確認する。
2. NewsArticleは実ニュース詳細だけに1件、SoftwareApplicationは公開AI Tool詳細だけに1件とする。
3. SoftwareApplicationにoffers、aggregateRating、Reviewが推測出力されていないことを確認する。
4. Search / 404 / filter URLのnoindexを確認する。

### 21. 公開判定

P0チェックがすべて完了し、Fatal/500、誤情報、法務未確定、意図しないindex、主要導線404が0件の場合のみ公開する。未解消P0が1件でもあれば延期する。

## Rollback

1. 重大障害を確認したら新規投稿・Import・設定変更を止める。
2. 影響範囲、発生時刻、直前操作、HTTP/PHPログを記録する。Secretは記録しない。
3. 直前の承認済みTheme / Plugin版へ戻す。
4. データ変更を伴う場合だけ、事前バックアップからDBを復元する。復元前に現状DBも退避する。
5. パーマリンクを再保存し、cacheを安全にpurgeする。
6. 主要URL、管理画面、Sitemap、robots、Schemaを再確認する。
7. 原因と再発防止が確認できるまで再公開しない。
