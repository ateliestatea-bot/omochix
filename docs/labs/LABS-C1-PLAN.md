# OmochiX Labs — C1 実装計画(MVP)

作成日: 2026-11-04
ブランチ: `feature/labs-c1-mvp`(worktree `~/Documents/omochix-labs-c1`、`origin/main` 94152a7 から作成。upstream は外してある)
前提: C0 設計(`omochix-editorial-engine/docs/audits/2026-11-04-omochix-labs/LABS_C0_DESIGN.md`)を承認済み
範囲: 実装計画のみ。本番反映・push・merge・deploy・DB 変更・Secrets 変更はしない

---

## 1. 実装前の必須確認(2026-11-04 実施)

| # | 確認項目 | 結果 | 判定 |
| --- | --- | --- | --- |
| 1 | 本番 `/labs/` の URL 占有 | `/labs/`、`/labs`、`/labs/json-formatter/`、`/labs/foo-bar/` はすべて 404(リダイレクトなし) | 空いている |
| 2 | 固定ページ・投稿・タームの slug 競合 | REST API の `pages`・`posts`・`categories`・`tags` で `slug=labs` はいずれも `[]`。テーマ・プラグイン・テスト・CI に `labs` という識別子はない(seed CSV の会社名 "… Labs" だけ) | 競合なし |
| 3 | rewrite ルールの競合 | 既存ルールの接頭辞は `ai-tools/*`、`prompts/*`、`learn/*`、`learn/motion(/compose)` だけで、`^([^/]+)/…` のような汎用ルールはない。投稿のパーマリンクは `/%postname%/` なので、将来 slug が `labs` の投稿を作ると衝突するが、固定ページが優先される(§9 R-3) | 競合なし |
| 4 | テーマとの互換性 | **本番は theme 1.26.0**(`style.css`、Motion カタログ 23 件)。`origin/main` は 1.27.0(53 件)で、**まだ本番に出ていない**。Labs は 1.27.0 の上に作る | 要判断(§10 D-1) |
| 5 | AI Tools・Learn・Motion の回帰の基準値 | 既存の PHP テスト 7 本と Node テスト 4 本がすべて PASS(worktree、PHP 8.5 / Node 24)。本番のスモーク: `/`、`/ai-tools/`、`/ai-tools/chatgpt/`、`/prompts/`、`/learn/`、`/learn/motion/`、`/learn/motion/compose/`、`/learn/practice/`、`/sitemap.xml`、`/robots.txt` がすべて 200 | 基準値を取得済み |
| 6 | 未コミット変更とブランチ | `~/Documents/omochix`(本体)は古いブランチ `feature/ai-tools-platform-taxonomy-cleanup`(main より 117 コミット遅れ)で、未コミット 15 件がある。**この checkout には触らない**。Labs は新しい worktree で作業する | 分離済み |
| 7 | 他セッションとの編集ファイルの重複 | 実行中のセッションはない。編集中なのは `feature/svg-motion-s1b` だけで、main より 10 コミット進み、未コミット 10 件。このブランチが触っている共有ファイルは `functions.php`(require 2 行)、`inc/shell.php`(テンプレート配列の行)、`.github/workflows/tests.yml`(テスト一覧の末尾) | §4 の配置で衝突を避ける |

確認で分かったその他の事実:

- 計測は **Google Site Kit** が gtag(`GT-5R69CZFH`)を出している。テーマ側に計測コードはない。
- 本番のレスポンスには CSP・X-Frame-Options などのセキュリティヘッダーがない(サーバーは LiteSpeed)。Labs で追加するヘッダーは Labs のページだけに付け、他のページのヘッダーは変えない。
- サイトマップは Slim SEO(`/sitemap.xml` → `sitemap-post-type-page.xml` など)。固定ページ `labs` は自動で載るが、`/labs/{slug}/` は載らない(Gallery の詳細ページも同じ状態)。
- ヘッダーのメインメニューは `wp_nav_menu`(管理画面で管理するデータ)。テーマのコードからは追加しない。

## 2. C1 の範囲

| 含める | 含めない(C2 以降) |
| --- | --- |
| `/labs/` 一覧(検索・カテゴリ・カード) | 画像圧縮、Markdown、OGP、プロンプト改善 |
| `json-formatter`、`text-diff`、`robots-txt-generator`、`token-cost-calculator` | AI Tools 詳細・Prompts 詳細からのリンク(AI Tools 側の回帰範囲を広げないため) |
| 入力例・コピー・リセット、使い方・FAQ・解説・関連リンク | トップページの導線カード(D-3) |
| SEO メタ、canonical、JSON-LD 4 種、未知 slug は 404 | ヘッダーのメインメニュー(管理画面で操作するため、リリース時に判断) |
| 計測イベント 6 種 | CSP の強制適用(C2) |
| Labs ページだけの CSP Report-Only とセキュリティヘッダー | サーバー API、DB、CPT |
| フッターと Learn トップからの導線 | AI Orchestrator との連携 |

## 3. 変更対象ファイル

### 3.1 新規(すべて `wp-theme/omochix-theme/` 配下。テストと CI は除く)

| ファイル | 役割 |
| --- | --- |
| `inc/labs.php` | 定数、query var、rewrite(`^labs/([a-z0-9-]+)/?$` → `pagename=labs&omx_lab=$1`、`top`)、バージョン付きの flush、カタログの読込と検証、未知 slug の 404、Slim SEO フィルタ、JSON-LD、ヘッダー、アセット読込、フッターリンクのフィルタ |
| `page-labs.php` | 一覧と詳細の出し分け |
| `template-parts/labs/index.php`、`card.php`、`tool.php`、`related.php`、`faq.php` | 共通の部品 |
| `template-parts/labs/content/{slug}.php` × 4 | ツールごとの作業領域のマークアップと解説(使い方・用語・注意点・FAQ)。検索意図を満たす本文として 1,500〜3,000 字を目安にする |
| `assets/data/labs/catalog.json` | ツール定義(slug・カテゴリ・名前・説明・keywords・related・links・updated) |
| `assets/data/labs/pricing.json` | モデル価格(§6) |
| `assets/data/labs/crawlers.json` | robots.txt 生成用の AI クローラー一覧(公式出典と確認日つき) |
| `assets/css/labs.css` | 既存のデザイントークンだけを使う |
| `assets/js/labs/core/{search,json,diff,robots,tokens,cost}.js` | DOM に触れない純粋関数。既存の `learn-composer-core.js` と同じ UMD 形式(Node のテストからも使える) |
| `assets/js/labs/shared.js` | コピー・リセット・入力例・`aria-live`・入力サイズ上限・Worker の起動とタイムアウト・計測(§5) |
| `assets/js/labs/worker.js` | JSON の解析と差分計算を Worker で実行する |
| `assets/js/labs/ui/{index,json-formatter,text-diff,robots-txt-generator,token-cost-calculator}.js` | 各画面の UI |
| `assets/img/labs/og/{index,slug}.png` | 静的な OG 画像(1200×630) |
| `tests/labs/run.php` | ルーティング・404・テンプレートの描画・SEO フィルタ・JSON-LD・ヘッダーの範囲・カタログと価格表の検証(既存の `tests/learn-gallery/run.php` と同じスタブ方式) |
| `tests/labs/core.js` | core の単体テスト(Node 組み込み機能だけを使う) |
| `tests/labs/data.js` | カタログ・価格表・クローラー一覧の整合性(§6 の除外ルールを含む) |
| `tests/labs/browser/`(ローカル専用。`package.json` で Playwright を固定版にする。svg-motion と同じ方式) | 通信 0 件、CSP 違反 0 件、axe、375px で横スクロールなし、キーボード操作 |
| `.github/workflows/labs.yml` | `tests/labs/run.php`、`core.js`、`data.js` を実行する。`tests.yml` は触らない |
| `docs/labs/LABS-C1-PLAN.md`(本書)、`docs/labs/LABS-C1-RESULT.md`(実装後) | 計画と結果 |

### 3.2 既存ファイルの変更(2 ファイルだけ)

| ファイル | 変更内容 | svg-motion ブランチとの関係 |
| --- | --- | --- |
| `functions.php` | `require_once …/inc/labs.php` を **`inc/article-v2.php` の行の後ろ**(require ブロックの末尾)に 1 行追加 | svg-motion は 17〜18 行目付近に追加している。間に変更のない行があるので自動でマージできる |
| `inc/shell.php` | `omochix_shell_enabled()` の `return` 式に `\|\| (function_exists('omochix_labs_is_request') && omochix_labs_is_request())` を追加 | svg-motion が変えているのは `omochix_shell_page_enabled()` の配列の行(約 10 行離れている)なので衝突しない |

フッターのリンクは既存の `omochix_editorial_footer_links` フィルタで `inc/labs.php` から追加します(`footer-editorial.php` は変えない)。Learn トップのカードも可能ならフィルタやカタログ経由で足し、テンプレートを変えずに済む方法を優先します。済まない場合は `page-learn.php` の該当箇所だけを変え、結果報告に書きます。

`style.css` の Version は C1 のブランチでは変えません(リリースブランチで上げる)。

実装の最後に `git merge-tree origin/main feature/svg-motion-s1b feature/labs-c1-mvp` 相当の試しマージを行い、衝突が 0 件であることを結果報告に載せます。

## 4. 実装の順序(コミット単位)

1. 基盤: `inc/labs.php`(ルーティング・404・カタログ)、`page-labs.php`、一覧、shell・functions の 2 か所。`tests/labs/run.php` の 404 と描画のテスト
2. SEO: タイトル・説明・canonical・パンくず・JSON-LD・OG 画像。Slim SEO の出力と重ならないことを確かめるテスト
3. 共通 JS: `shared.js`、`worker.js`、検索、計測。`tests/labs/core.js`
4. `json-formatter`
5. `text-diff`
6. `robots-txt-generator` と `crawlers.json`
7. `token-cost-calculator` と `pricing.json`(公式ページで確認した価格だけを載せる)
8. セキュリティヘッダー(Report-Only)、フッターと Learn の導線、`labs.yml`、ブラウザテスト、結果報告

## 5. 計測イベント

- 送り先は、Site Kit が読み込んだ `window.gtag` です。`typeof window.gtag === 'function'` のときだけ送り、gtag を自分で読み込んだり `dataLayer` を作ったりはしません。Site Kit の同意設定(Consent Mode)にもそのまま従います。
- パラメータは**次の表の固定語彙だけ**にします。入力本文、ファイル名、URL、ドメイン、モデルに入れた数値は送りません。`shared.js` の送信関数が許可リストにないキーと値を捨て、`tests/labs/core.js` でそれを確かめます。

| イベント | タイミング | パラメータ |
| --- | --- | --- |
| `labs_tool_view` | 詳細ページの表示(1 回だけ) | `tool_slug`、`tool_category` |
| `labs_tool_run` | 実行ボタン、または自動更新するツールで最初の変更から 30 秒に 1 回まで | `tool_slug`、`action`(例: `format`、`minify`、`compare`、`generate`、`estimate`)、`size_bucket`(`xs` 1KB 未満、`s` 100KB 未満、`m` 1MB 未満、`l` それ以上) |
| `labs_tool_success` | 処理が成功したとき | `tool_slug`、`action`、`duration_bucket`(`fast` 100ms 未満、`normal` 1 秒未満、`slow` それ以上) |
| `labs_tool_error` | 失敗したとき | `tool_slug`、`action`、`error_code`(`invalid_input`、`too_large`、`timeout`、`unsupported`、`internal`) |
| `labs_tool_copy` | コピーが成功したとき | `tool_slug`、`target`(`output`、`diff`、`robots`、`summary`) |
| `labs_related_click` | 関連リンクを押したとき | `tool_slug`(一覧では `index`)、`link_type`(`labs`、`ai_tool`、`prompt`、`learn`)、`link_slug`(カタログに書いてある slug だけ) |

GA4 の管理画面でのカスタムディメンション登録は任意で、利用者が行う作業です(新しいサービス契約は不要)。

## 6. AI 料金計算のデータルール(`pricing.json`)

```json
{
  "schema_version": 1,
  "currency": "USD",
  "unit": "per_1m_tokens",
  "max_age_days": 90,
  "models": [{
    "id": "provider-model-id",
    "provider": "openai | anthropic | google",
    "label": "表示名",
    "input": 0.0,
    "output": 0.0,
    "cached_input": null,
    "source_url": "https://<公式ドメイン>/…",
    "verified_at": "YYYY-MM-DD",
    "status": "verified"
  }]
}
```

- 入力単価と出力単価は別の項目にし、キャッシュ単価は任意(`null` の場合は画面に出さない)にします。
- **表示しないモデル**: `status` が `verified` でない、`source_url` が公式ドメインの許可リスト(`openai.com`、`anthropic.com`、`claude.com`、`ai.google.dev`、`cloud.google.com`)にない、`verified_at` がない・未来の日付・`max_age_days` より古い、単価が数値でない、のいずれか。PHP と JS の両方で同じルールで除外し、`tests/labs/data.js` で固定します。
- 価格は、実装時に私が公式の価格ページを読んで確認した値だけを載せます。**リリース前に利用者のレビューが必要です**。Editorial Engine の `pricing.ts` は参照しません。
- トークン数は「推定値」と画面に明記します。係数(日本語・英語・コード)と算出方法は解説に書きます。正確なトークナイザは使いません(C3 で検討)。
- 為替レートは利用者が入力します。既定値は置かず、入力がなければ USD だけで表示します。為替 API は使いません。

## 7. 入力上限・HTML 非実行・負荷分離

| ツール | 上限 | 処理場所 |
| --- | --- | --- |
| JSON | 5MB | 256KB 以上は Worker で処理。10 秒でタイムアウト |
| 差分 | 片側 1MB または 2 万行 | 常に Worker で処理。10 秒でタイムアウトし、Worker を `terminate` する |
| robots.txt | 生成するルール 200 件、テスターに入れるパス 2KB | メインスレッド |
| トークン計算 | 1MB | 256KB 以上は Worker で処理 |

- 上限はペーストや入力の時点で判定し、超えた場合は処理せず `error_code: too_large` にします。
- 出力はすべて `textContent`、または `document.createTextNode` で組み立てます。`innerHTML`、`insertAdjacentHTML`、`eval`、`new Function` は使いません。`tests/labs/core.js` で `assets/js/labs/**` のソースにこれらの文字列がないことを検査します(既存テストと同じソース検査方式)。
- 既定では入力を保存しません(`localStorage` は使わない)。

## 8. CSP Report-Only の検証方法

Labs のページ(`/labs/` と `/labs/{slug}/`)だけに、`template_redirect` で次のヘッダーを付けます。他のページのヘッダーは変えません。

```
Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com; connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com; img-src 'self' data: blob: https://*.google-analytics.com https://www.googletagmanager.com; style-src 'self' 'unsafe-inline'; worker-src 'self'; font-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
```

(`X-Frame-Options` は Report-Only がないため、C1 では付けません。`frame-ancestors` は C2 で強制に切り替えるときに有効になります。)

違反の報告を受け取るエンドポイントは作りません(サーバー API を作らない方針のため)。代わりに、次の 3 段階で確認します。

1. **自動(ローカル)**: `tests/labs/browser/` が PHP の組み込みサーバー(既存の `preview-server.php` と同じ方式)で、同じヘッダー付きの Labs ページを Playwright(Chromium・WebKit)で開き、`securitypolicyviolation` イベントを集めます。4 ツールで入力例 → 実行 → コピー → リセットを行い、**違反 0 件、`self` 以外への通信 0 件**であれば合格です。
2. **手動(リリース後の本番)**: Chrome DevTools の Console で `[Report Only]` の警告を確認します(`/labs/` と 4 ツール、PC とスマホ表示)。Site Kit の gtag と WordPress のインラインスクリプトはポリシーに含めてあるので、違反が出た場合は種類ごとに記録します。
3. **強制への切り替え判断(C2)**: 2 週間、上記の手動確認で Labs 由来の違反が 0 件で、他のプラグイン由来の違反を許可リストに反映できたら、`Content-Security-Policy` に切り替えます。

LiteSpeed Cache はヘッダーも一緒にキャッシュするため、ヘッダーを変えたらキャッシュを削除します。

## 9. テスト計画

| 区分 | 内容 | 実行場所 |
| --- | --- | --- |
| 既存の回帰 | PHP 7 本 + Node 4 本(基準値はすべて PASS) | ローカルと CI(`tests.yml`) |
| `tests/labs/run.php` | `/labs/` と 4 ツールの描画。未知 slug・`status: draft`・大文字 slug は 404 の status。canonical は末尾スラッシュ付きでクエリなし。title と description が全ページで固有。JSON-LD は一覧が `CollectionPage` + `ItemList` + `BreadcrumbList`、詳細が `WebApplication` + `BreadcrumbList`。Slim SEO の `BreadcrumbList` と重複しない。ヘッダーが Labs 以外のページに付かない。カタログが壊れていても致命的エラーにならない | ローカルと CI(`labs.yml`) |
| `tests/labs/core.js` | JSON(整形・圧縮・キー並べ替え・エラー位置・BOM・大きな整数)、差分(Myers の既知ケース・空入力・CRLF・空白無視・日本語の文字単位)、robots(生成・テスター・`Disallow: /` の警告)、トークン推定と料金計算(0・小数・上限)、検索(NFKC・かなの同一視)、計測パラメータの許可リスト、危険な API がソースにないこと | ローカルと CI |
| `tests/labs/data.js` | カタログの参照の整合性、価格表の除外ルール、クローラー一覧の出典と確認日 | ローカルと CI |
| `tests/labs/browser/` | 通信 0 件、CSP 違反 0 件、axe のエラー 0 件、375px で横スクロールなし、キーボードだけで全操作、Worker のタイムアウト | ローカル |
| 手動 | Lighthouse(SEO と Accessibility が 95 以上)、VoiceOver(iOS と macOS)、Safari・Firefox・Edge | ローカル |
| 試しマージ | svg-motion ブランチとの衝突 0 件 | ローカル |

## 10. 判断が必要な事項

| ID | 内容 | 推奨 |
| --- | --- | --- |
| D-1 | 本番は 1.26.0 で、main の 1.27.0(Motion 53 件)は未リリース。Labs を含むリリースに 1.27.0 の変更も含まれる | **1.27.0 を先に単独でリリース**し、Labs は 1.28.0 で出す(障害が起きたときに原因を切り分けやすい) |
| D-2 | 価格の有効期限 `max_age_days` | 90 日(期限を過ぎたモデルは表示しない) |
| D-3 | トップページ(`omochix_editorial_destinations`)に Labs を足すか | C1 では足さない(トップのレイアウトと `tests/editorial-home` に影響するため)。フッターと Learn から始める |
| D-4 | ヘッダーのメインメニュー(管理画面のデータ) | リリース後、利用者が管理画面で追加する |

## 11. ロールバック手順

C1 は作業ブランチだけで完結しており、本番・main・DB には何も反映しません。C1 の時点で戻す場合は、worktree とブランチを削除するだけです。

リリースした後(C1 の範囲外。既存の `docs/Beta-Deployment-Runbook.md` の Rollback に従う):

1. 直前の承認済みテーマ(1.27.0 か 1.26.0)の zip を再アップロードする。Labs のコードが消え、rewrite も登録されなくなる
2. 固定ページ `labs` を下書きにする(フッターのリンクは、公開中のページがあるときだけ出るので自動で消える)
3. 管理画面でパーマリンク設定を再保存し、rewrite を作り直す
4. LiteSpeed Cache を全削除する(ヘッダーと HTML のキャッシュを消す)
5. オプション `omochix_labs_rewrite_version` は残っても害はない(必要なら削除)
6. 主要 URL(§1 #5 の 10 件)、サイトマップ、robots.txt、Schema を確認し直す

DB への影響は、固定ページ 1 件とオプション 1 件(とメニュー項目を足した場合はその 1 件)だけです。スキーマは変えないので、DB の復元は不要です。

## 12. リスク

| ID | リスク | 対策 |
| --- | --- | --- |
| R-1 | Slim SEO の出力(タイトル・パンくず・WebPage)と重複する | Gallery と同じ `slim_seo_*` フィルタで上書きする。graph への追加は 1 本のフィルタにまとめ、テストで型の数を固定する |
| R-2 | Slim SEO のサイトマップに `/labs/{slug}/` を足すフックが使えない可能性(プラグインのソースはリポジトリにない) | 実装時にプラグインのバージョンとフックを確認する。使えなければ C1 では一覧ページだけをサイトマップに載せ、詳細ページは一覧からの内部リンクで発見させる(テーマ独自のサイトマップは作らない) |
| R-3 | 将来 slug が `labs` の投稿が作られる | 固定ページが優先される。Editorial Engine の slug 生成は変えない。結果報告に記録しておく |
| R-4 | 公開後に価格が古くなる | `max_age_days` を過ぎたら自動で表示しない。月 1 回確認する |
| R-5 | WebKit で Worker の挙動が違う | ブラウザテストに WebKit を含める |
