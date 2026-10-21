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

### 8b. Learn v2（動く見本）ページ

テーマ1.18.0以降。詳細は`docs/Learn-v2-Spec.md`。

1. 固定ページ`Learn`（slug `learn`）が公開済みであることを確認する。
2. その子ページとしてタイトル「動く見本」、slug `motion`（パス `learn/motion`）を作成し、抜粋に一覧のリード文を入れて公開する。本文は空でよい。
3. 公開後に`/learn/motion/`と`/learn/motion/pop-in/`が200、`/learn/motion/nope/`が404になることを確認する。詳細URLはテーマが初回に自動で書き換えルールを反映するが、404になる場合は12のパーマリンクflushを行う。
4. `/learn/`のヒーローに「動く見本を見る」が出ること、未公開の間は出ないことを確認する。
5. 見本とPromptの関連（`assets/data/motion/catalog.json`の`related.prompts`）は公開済みPromptのslugにだけ効く。本番のslugと照合し、一致しないものは外すか差し替える。

### 8c. Motion Composer（動きを組み合わせる）ページ

テーマ1.23.0以降。詳細は`docs/Learn-v2-Spec.md` 8.4。

1. 固定ページ「動く見本」（`learn/motion`）が公開済みであることを確認する。
2. その子ページとしてタイトル「動きを組み合わせる」、slug `compose`（パス `learn/motion/compose`）を作成して公開する。本文は空でよい。抜粋を入れるとリード文と meta description に使われる。
3. 公開後に`/learn/motion/compose/`を一度開き、Composer のページ（3つの空きスロット）が出ることを確認する。テーマが初回に書き換えルールを反映するが、「動く見本」の404になる場合は12のパーマリンクflushを行う。
4. ロリポップ！アクセラレータの「キャッシュ削除」を実行する（テーマ更新後は毎回）。
5. `/learn/motion/`のリード文の下に「見本を3つまで組み合わせて…」のリンクが出ること、`/learn/motion/compose/`が`noindex, follow`であることを確認する。Composer の状態はURLハッシュ（`#c=`）とブラウザ内保存だけで、DBには何も書かない。

### 8d. Learn 実践教材（Learn Practice）と分野ハブ

テーマ1.25.0 / Core Plugin 1.8.0以降（実践教材の post type・分野は 1.7.0 から、JSON インポートは 1.8.0 から）。詳細は`docs/Learn-Phase4-Spec.md`。

1. Core Plugin を更新すると、初回アクセスで実践教材の post type・分野などのタクソノミー・初期ターム（分野 8・形式 5・対応AI 10・技術 14）が登録され、書き換えルールが一度 flush される。`/learn/practice/`が404なら12のパーマリンクflushを行う。
2. 分野ハブは 8 つ。固定ページ「Learn」（slug `learn`）の子ページとして次の 7 ページを作成して公開する（親＝Learn、slug は分野タームの slug と一致させる。タイトルは分野名。本文は空でよい。抜粋はカードの説明になる）。`motion` は既存の「動く見本」ページ（`/learn/motion/`）がハブを兼ねるので作らない。

   | 分野ターム slug | ページ slug（親：Learn） | URL | タイトル |
   |---|---|---|---|
   | `web` | `web` | `/learn/web/` | Webサイト・LP制作 |
   | `apps` | `apps` | `/learn/apps/` | アプリ・システム開発 |
   | `motion` | （作らない：既存 `motion`） | `/learn/motion/` | モーション・インタラクション |
   | `video` | `video` | `/learn/video/` | 動画・映像制作 |
   | `automation` | `automation` | `/learn/automation/` | 外部連携・自動化 |
   | `ai` | `ai` | `/learn/ai/` | AI入門・AI活用 |
   | `projects` | `projects` | `/learn/projects/` | 実践プロジェクト |
   | `devtools` | `devtools` | `/learn/devtools/` | 開発ツール・制作環境 |

   ページが無い間、`/learn/web/`・`/learn/video/`・`/learn/ai/` は WordPress の 404 推測リダイレクトで無関係な既存記事へ 301 する（2026年10月22日時点の本番で確認）。ページを作成すると完全一致が優先され、その URL は作成したページになる。作成前にこれらの URL をメニューや記事からリンクしない。
3. 既存の Learn セクションページに「分野」を付ける（ページ編集画面の分野パネル）：basics・work → AI入門・AI活用、development・tools → 開発ツール・制作環境、tutorials → 実践プロジェクト。
4. `learn/practice` という固定ページは作らない（実践教材の URL と競合する）。
5. ロリポップ！アクセラレータの「キャッシュ削除」を実行する。
6. `/learn/`に「分野から探す」と「作る」、`/learn/practice/`が200（教材 0 件なら「準備中」）であることを確認する。
7. 教材の一括登録は「Learn 実践教材 → JSON インポート」（管理者のみ）。dry-run の結果表で「公開されます」「公開が取り下げられます」の注意が出た行は、反映前に意図どおりか確認する。見つからない関連先（prompts / learn / tools / motion）は注意として表示され無視される（エラーにはならない）。`wp-plugin/omochix-core/sample/practice/*.json` を dry-run → 内容確認 → 同じファイルで反映。登録される教材は下書きなので、必須項目と本文を確認してから公開する。反映後に表示される「ロールバック用バックアップ」は保存しておく。

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
3. `/learn/motion/{slug}/`（例：`/learn/motion/pop-in/`）が200で見本の詳細を返すことを確認する。

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

## 更新リリースの適用順序（Theme 1.25.0 / Core Plugin 1.8.0）

配布物と手順書は `omochix-learn-v2/release/omochix-theme-1.25.0.*`・`omochix-core-1.8.0.*`・`release-1.25.0-core-1.8.0.deploy.md`。

1. 順序は **Core Plugin 1.8.0 → Theme 1.25.0**。Core だけ先に更新しても旧テーマ 1.24.2 は致命的エラーなく動く（実践教材 post type が登録されるが、テーマ側の一覧・単体テンプレートが無いので標準テンプレートで表示される。教材が 0 件なら影響なし）。Theme だけ先に更新しても旧 Core 1.6.0 で致命的エラーは起きない（実践教材・分野ハブの表示は `post_type_exists` / `function_exists` で自動的に非表示になる）。どちらの順でも DB は変更されないが、`/learn/practice/` が確実に表示されるのは両方が揃ってから。
2. Core 1.6.0 → 1.8.0 はファイルの追加と `omochix-core.php`・`readme.txt` の差し替えのみで、削除されるファイルはない。Theme 1.24.2 → 1.25.0 も同様（追加 8・変更 10、削除 0）。したがって上書きアップロードで旧ファイルが残っても動作に影響しない。
3. Core 更新後の初回アクセスで実践教材の post type・5 タクソノミー・初期ターム（分野 8・形式 5・対応AI 10・技術 14）が登録され、書き換えルールが一度 flush される（オプション `omochix_core_practice_rewrite_version`）。既存の `ai_tool`・`prompt`・投稿・固定ページ・オプションは変更されない。
4. Theme 更新前後で既存 URL（`/`、`/learn/`、`/learn/motion/`、`/learn/motion/compose/`、`/learn/motion/<slug>/`、`/ai-tools/`、`/prompts/`、Learn 各ページ）の title・canonical・robots は変わらない（Local で新旧を比較済み）。

## 復旧（Core 1.6.0 / Theme 1.24.2 へ戻す）

- 戻す条件：PHP Fatal、管理画面の実践教材メニューや JSON インポートでのエラー、既存の `/ai-tools/`・`/prompts/`・Learn ページの表示崩れ・404、Schema / Sitemap の欠落のいずれかが本番で確認されたとき。
- Core を 1.6.0 に戻す場合：`omochix-core/` を 1.6.0 の配布物（`release/` の 1.6.0 相当は `git` の `e4f9918` から `git archive` で再作成できる）で上書きする。1.8.0 で追加されたファイルは 1.6.0 の `omochix-core.php` から読み込まれないので残っていても無害だが、`includes/practice-*.php`・`admin/practice-*.php`・`sample/practice/` を削除すれば完全に 1.6.0 と同一になる。実践教材を作成済みなら、その投稿・ターム・メタは DB に残る（post type 未登録のため表示されないだけ。再度 1.8.0 にすると復活する）。DB の復元は不要。
- Theme を 1.24.2 に戻す場合：`release/omochix-theme-1.24.2.zip` の内容で上書きする（追加された 8 ファイルは残っても読み込まれない）。
- 戻した後：ロリポップ！アクセラレータのキャッシュ削除、パーマリンクの再保存、主要 URL と管理画面の確認。

## Rollback

1. 重大障害を確認したら新規投稿・Import・設定変更を止める。
2. 影響範囲、発生時刻、直前操作、HTTP/PHPログを記録する。Secretは記録しない。
3. 直前の承認済みTheme / Plugin版へ戻す。
4. データ変更を伴う場合だけ、事前バックアップからDBを復元する。復元前に現状DBも退避する。
5. パーマリンクを再保存し、cacheを安全にpurgeする。
6. 主要URL、管理画面、Sitemap、robots、Schemaを再確認する。
7. 原因と再発防止が確認できるまで再公開しない。
