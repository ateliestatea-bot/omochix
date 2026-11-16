# OmochiX Design Library — D1 実装報告（Web Design MVP）

- 作成日：2026-11-15
- ブランチ：`feature/design-library-d1`（`2f800b2` から分岐。専用の worktree で作業）
- 正本の設計：`Design-Library-D0-Architecture.md` v1.3（変更していない）。D0 から変えた点・足した点は §3 にまとめた（**D0 への反映は要承認**）。
- 状態：**ローカル実装と試験まで完了し、D1.5 とあわせてローカル commit**（`feat(design-library): add web and video works library`）。push・PR・main へのマージ・本番反映は未実施（別承認）。D0 の 2 文書（設計書・P-Checks 手順書）は、P1〜P8 の結果を待つ条件があるため、この commit に含めていない。

---

## 1. 開発基準の確定（Step 1）

| 確認項目 | 結果 |
|---|---|
| 作業開始時の `origin/main` | `a617fd3`（theme **1.28.0**、Core **1.10.0-dev**）。報告にあった本番の版（theme 1.29.0 / Core 1.10.0）は main に**入っていなかった** |
| Industry Hub RC `2f800b2` | `origin/rc/industry-hub-on-theme-1.28.1` の先頭。theme 1.29.0 / Core 1.10.0 |
| 本番相当かの照合 | `releases/industry-hub-2f800b2/` の配布マニフェストと `2f800b2` のソースを照合：**theme 172/172、Core 59/59 一致、余分なファイル 0** |
| PR #61 / #63 / #64 | いずれも OPEN のまま（head `c9fab17` / `88e3b08` / `65f1089`）。**変更していない** |
| 作業中の変化 | 作業中に PR #65（RC → main）が別の操作でマージされ、`origin/main` は `3ef4a14` になった。**`3ef4a14` のツリーは `2f800b2` と完全に一致**（`git diff --quiet`）なので、D1 はそのまま現在の main の上に載る |
| D0 成果物 | `feature/design-library-d0` の worktree に残したまま（未追跡、SHA-256 不変）。D1 worktree にコピーし、リポジトリ外のバックアップにも退避 |
| 元のチェックアウト（未コミット 22 件） | 触れていない（22 件のまま、ブランチ `feature/ai-tools-platform-taxonomy-cleanup` のまま） |

→ **D1 の実装基準は `2f800b2`（= 本番配布物と一致、= 現在の main のツリー）**。新しいブランチ `feature/design-library-d1` と worktree を、iCloud 同期外に作成した。

D0 は「P1〜P8 の結果を受け取るまで D1 を始めない」としていたが、今回の指示書で **D1 のローカル実装と試験が承認**されたため着手した。P1〜P8 が未確認であることは、本番反映の条件として残る（§7）。

---

## 2. 実装したもの

### 2.1 ファイル一覧

テーマ（既存ファイルの変更は `functions.php` の `require` 2 行だけ）：

| ファイル | 内容 |
|---|---|
| `functions.php` | `inc/design-library.php` と `inc/design-library-demo.php` の読み込み（+2 行） |
| `inc/design-library.php` | 語彙、カタログの読み込みと検証、公開条件 C1〜C6、絞り込み、URL、rewrite、404、ページの Report-Only CSP、SEO、shell への登録 |
| `inc/design-library-demo.php` | 隔離デモ（`/design/demo/web/{slug}/`）：**強制 CSP（`sandbox allow-scripts`）、送れなければ 503、ヘッダーの実在確認**、スクリプトは SHA-256 で許可 |
| `page-design.php` | トップ `/design/`、一覧 `/design/web/`、詳細 `/design/web/{slug}/` |
| `template-parts/design-library/{card,filter-bar,detail}.php` | カード、絞り込み、詳細 |
| `assets/css/design-library.css` | 一覧・詳細（`style.css` は変更していない） |
| `assets/js/design-library.js` | ループ再生の制御（同時 2 件まで等）と絞り込み |
| `assets/js/design-library-preview.js` | 詳細ページとデモの postMessage（受信は `ready` / `error` だけ、送信はテーマの明暗だけ） |
| `assets/data/design/catalog.json`・`catalog.schema.json` | カタログ（4 件）と JSON Schema（draft 2020-12） |
| `assets/design/runtime/demo-runtime.js` | 全デモに埋め込む共通ランタイム（メッセージ検証、reduced-motion、非表示時の通知） |
| `assets/design/demos/{slug}/demo.{php,css,js}` | 4 デザイン。`demo.php` は ABSPATH ガード付き |
| `assets/design/posters/{slug}.{avif,webp}`・`loops/{slug}.{webm,mp4}` | 静止画サムネイル（1280×800）と 8 秒のループ（960×600、無音） |

試験・道具・記録（テーマ外。配布物には入らない）：

| 場所 | 内容 |
|---|---|
| `tests/design-library/run.php`・`stub.php`・`validate-cli.php` | PHP 単体試験（WordPress 不要） |
| `tests/design-library/catalog-check.js` | JSON Schema・ハッシュ・静的ファイル・デモのコード監査（依存なし、CI 向け） |
| `tests/design-library/http-check.php` | 使い捨てローカル WordPress への HTTP 試験 |
| `tests/design-library/browser/{isolation,ui,perf,firefox,lib}.js`・`package.json` | ブラウザ試験 |
| `tests/design-library/regression-snapshot.php` | 既存 URL の回帰比較 |
| `tests/design-library/run-all.sh` | 全試験の一括実行（ログを `qa/logs/` に保存） |
| `tests/design-library/fixtures/` | **テスト専用**：隔離プローブのデモと、ローカル専用の mu-plugin |
| `tools/design-library/demo-hash.js`・`capture-media.js` | デモのハッシュ計算、ポスターとループの生成（デモ自身を録画） |
| `docs/design-library/qa/` | 試験ログ、性能の結果、回帰の基準データ、スクリーンショット |

### 2.2 一覧（A）

- URL：`/design/`（トップ。新着カードと Web デザインへの導線）と `/design/web/`（絞り込み付きの一覧）。D0 §4.1 のとおり、固定ページ `design` ＋ rewrite ＋ query var。ローカルに `/design` 系のルートの競合はなかった（D0 §0.2 と同じ結果。既存 86 URL も不変）。
- カード：ポスター（AVIF、WebP フォールバック）、ループ動画、スタイル・モーションのタグ、「詳細を見る」「デモを開く（新しいタブ）」。
- 絞り込み：キーワード（日本語のラベルも検索）、スタイル、業種（Core の `omx_industry` に実在する term だけ）、モーション。GET フォームなので JS なしでもサーバー側で絞り込める。JS があればその場で絞り込み、URL も同期する。絞り込み後の URL は `noindex` で、canonical は素の一覧。
- テーマの shell（左ナビ）、ライト／ダーク（テーマのトークンに追従）、PC・スマホに対応。

### 2.3 4 デザイン（B）

| | Liquid Glass | Minimal Editorial | 3D Interactive | Bento SaaS |
|---|---|---|---|---|
| 架空の題材 | AI ワークスペース「Aerolume」 | 季刊誌「YOHAKU」 | 卓上モジュール「KORE-01」 | タスク分析 SaaS「Tilely」 |
| レイアウト | 全画面 hero ＋ ガラスカード 3 段 | 12 カラム、縦書きの引用、段組み | 3D ステージの hero ＋ 横スクロールの部品カード | 大きさの違う Bento タイル ＋ 料金表 |
| モーション | 光のドリフト、ポインター追従、カードの浮遊、スクロールでぼかし変化 | 見出しの行ごとの出現、スクロールで罫線と図版を描画、ページ送り | 組み立て演出、ドラッグ／矢印キーで回転、スクロールでカメラ、部品のハイライト | タイルの順次出現、ホバー拡大、カウントアップ、グラフ描画 |
| reduced-motion | 背景とハイライトを止める | 即時表示、罫線は描画済み | 自動回転・カメラ・組み立てを止め、ドラッグだけ残す | 最終状態で表示 |
| JS 無効 | 静止したガラスレイアウト | 全文と全記事が読める | ポスター＋説明 | 最終値のタイル |
| その他 | — | 親ページの明暗に追従 | WebGL 非対応ではポスター＋説明 | 親ページの明暗に追従 |

- すべて自作（CSS・SVG・素の WebGL）。写真・イラスト・ロゴ・外部フォント・外部ライブラリ・参考サイトのコードや文章は使っていない。**参考サイトは参照していない**ため、D0 A5 の観察メモ（`research/`）は作っていない。
- 3D は D0 では three.js（MIT）の同梱を想定していたが、**素の WebGL で実装**した（gzip 後 6.8 KB）。第三者コードの同梱・ライセンス記録が不要になった。
- ポスターとループは `tools/design-library/capture-media.js` がデモを Chromium で開き、デザインごとに決めた操作（ポインター移動・スクロール・ドラッグ）を録画して作る。再現できる。

### 2.4 カタログ（C）

- D0 §1.2 の共通モデルを使い、**CPT・DB テーブルは追加していない**。
- 検証（PHP 実行時とテスト）：JSON Schema、id と slug の重複（先に出たものだけ残す）、id の接頭辞と kind の一致、`published` は `web` だけ（App・Components は公開を拒否）、ポスター・ループ・デモのパスは slug に縛り、実在を確認（他のアイテムのファイルや `..` を参照できない）、未知のキーと語彙の拒否、`deliverables.html.sha256` をデモのソースと照合（CI 側）。不正なアイテムはそのアイテムだけ除外し、ページは落とさない。
- 不正なカタログ 34 種類（未知のキー、重複、パスの細工、公開できない kind など）を、**JSON Schema と PHP の両方が拒否する**ことを試験で確認した。

### 2.5 動画プレビュー（D）

- 同時再生は最大 2 件（PHP の定数と JS の上限 2 の二重）。画面に半分以上入ったカードだけ再生し、画面外とタブ非表示では停止する。
- `prefers-reduced-motion`、Save-Data・2G、`deviceMemory < 4` では自動再生せず、ポスターのまま。再生ボタンを押したときだけ動く。
- 動画は `preload="none"` で、最初の再生時にソースを付ける（見えていないカードの動画は読み込まない）。読み込みに失敗したらポスターのまま、再生ボタンを隠す。
- メディア枠は縦横比が固定なので、再生の開始で CLS は起きない（実測 0）。

---

## 3. D0 から変えた点・足した点（要承認：D0 文書へ反映するか）

| # | 内容 | 理由 |
|---|---|---|
| 1 | カタログに **`id`**（`dl-{kind}-NNNN`、一意）を追加 | 指示書の「ID と slug の重複防止」。slug を変えても同じアイテムだと分かる（将来の CPT 化にも使える） |
| 2 | **`posterFallback`（WebP）・`loopFallback`（MP4）** を追加（任意） | AVIF・WebM に対応しない環境のため |
| 3 | **`audit.isolation`**（`unverified` / `verified-local` / `verified-production`）を追加 | 「B1〜B10 に合格するまでデモを利用可能にしない」を、データで強制するため。local・development 環境では `verified-local` 以上、**それ以外（production・staging・未設定）では `verified-production` のときだけ**デモを返す（既定で閉じる） |
| 4 | デモの CSP は D0 §3.3.3 より**狭くした**：`script-src` はハッシュだけ（ホストのパスは許可しない）、`style-src` は `'unsafe-inline'` だけ、`worker-src`・`frame-src`・`object-src`・`manifest-src` も `'none'`。ほかに `X-Frame-Options: SAMEORIGIN`・`Cross-Origin-Opener-Policy`・空の favicon を宣言 | 外部ファイルのスクリプトを使わないので不要。Firefox が `/favicon.ico` を取りに行って違反になるため |
| 5 | 3D は three.js ではなく素の WebGL | §2.3 |
| 6 | 詳細ページは D1 では最小限（ブラウザ枠・全画面リンク・説明・プロンプト例・配色）。幅の切り替えは D2 | 一覧から詳細への導線と、B2 の試験に必要な範囲 |
| 7 | **`/design/` 系の URL が 404 になったとき、WordPress の「似た URL への 301（推測リダイレクト）」を止める** | 止めないと、公開条件を満たさない `/design/app/` などが `/design/` へ、ページ `design` がない状態の `/design/` が「design」で始まる別の投稿へ 301 になる（C1〜C5 は 404 の決まり）。**テーマを本番に入れると、本番の `/design/` の現在の振る舞い（推測リダイレクトの可能性）が 404 に変わる**。P3・P8 と合わせて判断が必要 |
| 8 | shell への登録は `inc/shell.php` を編集せず、既存のフィルター `omochix_shell_enabled_for_request` を使った | 既存ファイルの変更を減らすため |
| 9 | Liquid Glass の背面ぼかし（`backdrop-filter`）はナビとヒーローのパネルだけ。下段のカードは、ぼかしを使わない半透明のグラデーション | 11 枚すべてにかけると、負荷が高いときに fps が基準を割った（§5） |

---

## 4. セキュリティ試験の結果（ローカルのみ）

環境：使い捨てのローカル WordPress 7.1.4（`http://127.0.0.1:8961`、MySQL 8.4 を専用データディレクトリで起動、`WP_ENVIRONMENT_TYPE=local`）。ブラウザは Chromium（Playwright 1.62.1）、WebKit（同）、Firefox 159（インストール済み、WebDriver BiDi）。

### 4.1 隔離の受け入れ条件 B1〜B10（D0 §10.1）

| # | 結果 | 確認した内容 |
|---|---|---|
| B1 | **合格** | 4 デモ × 2 回の応答で、強制 `Content-Security-Policy` の先頭が `sandbox allow-scripts`。`allow-same-origin` などなし。`frame-ancestors 'self'`、`connect-src 'none'`。Report-Only なし。404 の応答にも同じ CSP。`script-src` のハッシュはインラインスクリプトと完全一致 |
| B2 | **合格**（3 ブラウザ） | 詳細ページの iframe は `sandbox="allow-scripts"` だけ |
| B3 | **合格**（3 ブラウザ × 埋め込み・直接） | デモ内の `self.origin` は `"null"` |
| B4 | **合格** | サイトのページは試験用 Cookie を読める（陽性対照）。デモ内では `document.cookie` の読み書きが `SecurityError`、localStorage も使えない |
| B5 | **合格** | デモ内の fetch・XHR・WebSocket・sendBeacon・画像ビーコン・フォーム送信は、**サーバーのアクセスログに 1 件も届いていない**（Chromium はブラウザ側で「CSP でブロック」と記録） |
| B6 | **合格** | 親・top の document は `SecurityError`。eval・`new Function`・注入したスクリプトは動かない。`window.open` は `null` でウィンドウは開かない。alert は出ない。`target=_top` のリンクとフォーム送信で親ページは変わらない |
| B7 | **合格** | 型外・HTML 入りのメッセージ、500 件の連続送信、別の iframe からの偽の `ready` / `error` で、親の DOM と状態は変わらない。デモ自身からの正しい `error` は受け付ける（陽性対照） |
| B8 | **合格** | 親からの正しいテーマ指定は反映、別の iframe からは無視、不正な値は反映されず、何も挿入されない |
| B9 | **合格** | 直接開いても B3〜B6 と同じ結果。ヘッダーを送れない状態（試験用フック）では、**正しい URL でも 503・本文 0 バイト** |
| B10 | **合格** | `demo.php` を直接開くと空。テーマの `assets/design` には HTML・SVG のファイルがない（CI で検査）。試験用プローブのファイルはテーマに存在しない |

注：直接開いたデモの中のリンクをクリックすると、そのタブ自体が遷移する（最上位の文書のため。通常のリンクと同じ）。デモのコードに `target` 付きリンクや遷移を入れないことは、コード監査（`catalog-check.js`）で禁止している。

### 4.2 V1：層ごとの検証（D0 §17）

| 層 | 結果 |
|---|---|
| L1 オリジン隔離 | B1〜B10 合格（上記） |
| L2 認証 Cookie | ログイン時の WordPress の認証 Cookie はすべて `HttpOnly`。デモの応答本文は、管理者ログイン時と未ログイン時で**ハッシュが一致**。nonce・REST 設定・管理バー・ユーザー名を含まない。**`Secure`・`SameSite` はローカルが http のため未確認**（HTTPS のステージングか本番で記録が必要） |
| L3 CSRF | 管理者の Cookie だけ（nonce なし）で、投稿のゴミ箱移動・admin-ajax の削除・admin-post・テーマの切り替え・Core の停止・設定の変更・ログアウトを送り、**すべて拒否**、状態は不変。Design Library に状態を変えるエンドポイントはない |
| L4 REST | 管理者の Cookie だけで `users/me` は 401 `rest_not_logged_in`、投稿の作成は 401/403。OmochiX Core の書き込み系ルートもすべて拒否。REST に Design Library の名前空間やルートはない |

L3・L4 は D0 では「テスト専用ビルドで CSP を外したブラウザ」から送る想定だったが、今回は**サーバー側の検証として curl で同じ要求を送った**（CSP が破れた場合にデモが送れる最大のもの = Cookie 付きで nonce なし）。CSP を外したブラウザでの実施は D5 の残作業とする。

### 4.3 本番での安全性は未実証

- 上記は**すべてローカル（PHP の内蔵サーバー）での結果**。本番の LiteSpeed・LiteSpeed Cache・CDN の有無・セキュリティプラグインが、CSP ヘッダーを落とす・書き換える・ヘッダーなしの本文をキャッシュするかは**確認していない**（D0 §3.8 R2、P7、D5）。
- そのため、出荷するカタログの状態は `verified-local`。**本番環境ではデモを返さない**（`verified-production` が必要）。本番でデモを有効にするには、承認された公開作業の中で B1・B9 を本番の実応答で確認し、`verified-production` に変える必要がある。
- 手動の Safari（macOS・iOS）と Chrome（Android）での確認（D0 §10.1）は未実施。

---

## 5. 試験結果（最終実行、2026-11-15）

`tests/design-library/run-all.sh` のログは `docs/design-library/qa/logs/` にある。

| 試験 | 結果 |
|---|---|
| カタログ・静的ファイル（`catalog-check.js`） | 70 / 70 |
| PHP 単体（`run.php`：C1〜C5、環境ごとのデモ許可、CSP、ハッシュ、はみ出し拒否、絞り込み） | 60 / 60 |
| HTTP（`http-check.php`：ルーティングと 404、B1・B9・B10、デモの許可、C1、SEO、V1 L2〜L4） | 96 / 96 |
| 隔離（`isolation.js`：Chromium・WebKit、B2〜B9、4 デモの CSP 違反 0・外部通信 0） | 72 / 72 |
| Firefox（`firefox.js`：B2〜B9、4 デモ、一覧） | 20 / 20 |
| 表示・操作（`ui.js`：Chromium・WebKit） | 134 / 134 |
| 性能（`perf.js`：Chromium、各デモ 3 回の中央値） | 32 / 32 |
| 既存 URL の回帰（D1 適用前の 86 URL と比較） | **差分 0** |
| 既存のテスト | learn-gallery 134、labs 285、industry-hub 83（＋ theme-validate 38、theme-without-core 18）、article-v2 197、editorial-home 26、learn-composer 46、learn-practice 96、svg-motion contract 66、AI Tools 3 種：**すべて合格** |
| `tests/release/release-gate.php` | 13 / 14。失敗の 1 件は「Industry Hub リリースとの差分が承認済みの 29 項目と一致すること」で、**D1 の新規ファイルを未承認の差分として正しく検出したもの**（基準の `2f800b2` では 14 / 14）。D1 の出荷には、別のリリース計画が必要 |
| PHP 構文（`php -l`） | 変更・追加した PHP すべて問題なし |

`ui.js` の内訳：PC 1440 とスマホ 390 × ライト・ダークでトップ・一覧・詳細に横スクロールなし、axe（WCAG 2.2 A/AA、`<main>`）の serious・critical 違反 0（minor・moderate も 0）、ページエラー 0。同時再生は 25 回の計測で最大 2、画面外は停止、タブ非表示で全停止と再開。reduced-motion・Save-Data で自動再生なし（ボタンでは再生）。動画を読めないときはポスターのまま。絞り込み 4 種、空の状態、リセット、URL 同期、キーボードだけの操作、JS 無効でのサーバー側絞り込み。デモ 4 種：スマホ幅で横スクロールなし、タブ非表示で rAF 0 回、reduced-motion の挙動（D0 §15.2 どおり）、矢印キーでの 3D 回転、JS 無効と WebGL 非対応のフォールバック。

---

## 6. 性能（D0 §15.3 との比較）

ヘッドレス Chromium・SwiftShader（ソフトウェア描画）、PHP の内蔵サーバー（圧縮なし。転送量は各応答の gzip 後の大きさで算出）。LCP は CDP の制限（CPU 4 倍・150 ms・1.6 Mbps、390×844）で測った。**Lighthouse ではない**。詳細は `qa/perf-results.json`。

| 指標 | Liquid Glass | Minimal Editorial | 3D Interactive | Bento SaaS | 基準（2D / 3D） |
|---|---|---|---|---|---|
| デモの転送量 | 6.4 KB | 5.6 KB | 38.7 KB | 6.5 KB | ≤ 300 / 900 KB |
| デモの JS（gzip） | 1.8 KB | 1.7 KB | 6.8 KB | 1.8 KB | ≤ 40 / 250 KB |
| LCP（詳細、モバイル相当） | 1.88 s | 1.88 s | 1.88 s | 1.87 s | ≤ 2.5 s |
| CLS | 0 | 0 | 0 | 0 | ≤ 0.05 |
| 50 ms 超のロングタスク（5 秒） | 0 | 0 | 1 | 0 | 0 / ≤ 2 |
| fps（下位 5%） | 60 | 110 | 108 | 111 | ≥ 55 |
| JS ヒープ | 0.9 MB | 0.9 MB | 1.5 MB | 1.0 MB | ≤ 50 / 150 MB |
| ポスター AVIF | 20 KiB | 10 KiB | 20 KiB | 14 KiB | ≤ 60 KB |
| ループ WebM / MP4 | 97 / 136 KiB | 186 / 281 KiB | 398 / 335 KiB | 119 / 163 KiB | ≤ 600 KB |

一覧ページ（PC 1440、初回）：転送 421 KB（ループ 2 本を含む）、動画は表示中の 2 本だけ読み込む、CLS 0、ヒープ 1 MB。reduced-motion では動画 0 本、138 KB。CPU（メインスレッド）は計測上ほぼ 0%（動画のデコードはメインスレッドの外）。12 カードでの計測（D0 §15.3）はカードが 4 枚のため未実施。

**測定の注意**：作業中、同じマシンで別の重い処理が動き、負荷平均が 10〜22 になった時間帯があった。その間は 3D の fps が 4〜12、Liquid Glass が 24〜41 まで落ちた。3D は負荷が下がると安定して 108。Liquid Glass は背面ぼかしを 2 層に絞ってから、負荷 6〜8 で 60 / 60 / 60 になった。**実機の GPU での測定（特に低価格のスマホ）は未実施**。

---

## 7. 既存機能への影響

- 既存ファイルの変更は `functions.php` の 2 行だけ。既存の 86 URL（トップ、Learn、Motion Gallery 全 53 件、Composer、SVG Motion Studio、Industry Hub、Labs 全ツール、AI Tools、Prompts、カテゴリー `design`、サイトマップ、robots.txt、REST、フィード、404）の応答は、D1 適用の前後で**ステータス・主要ヘッダー・本文（nonce と版番号を除く）が一致**した。
- 新しい rewrite ルールは `^design/...` の 3 本。版の定数で 1 回だけ flush する（Labs・Gallery と同じ方式）。**本番に入れると、最初のリクエストで `flush_rewrite_rules` と `update_option` が 1 回だけ走る**。
- `/design/` 系が 404 になったときの推測リダイレクトを止める（§3 の 7）。対象は `pagename=design` の要求だけ。
- Core 1.10.0 / theme 1.29.0（= `2f800b2`）の上で動作を確認した。Core の Industry Hub がない、または非公開のときは、業種の絞り込みを出さずに動く（単体試験で確認）。theme の版番号（`style.css` の Version）は上げていない（出荷時に決める）。

---

## 8. ローカルプレビュー

起動中のサイト：<http://127.0.0.1:8961/design/>（一覧 `/design/web/`、詳細 `/design/web/3d-interactive/` など、デモ `/design/demo/web/bento-saas/`）

- 使い捨て環境：リポジトリ外の作業フォルダーに置いた WordPress 7.1.4（theme と Core は D1 worktree へのシンボリックリンク。MySQL 8.4 は専用のデータディレクトリとポート）。管理者のパスワードはそのフォルダー内のファイル（権限 600。ローカルの試験用の値で、リポジトリには含めない）。試験は `DL_SITE` でそのフォルダーを指定して動かす。
- 試験専用の mu-plugin（`fixtures/mu-plugin/omx-design-test.php`）がシンボリックリンクで入っている。フラグファイルがないときは何もしない。
- この環境は本番と無関係で、外部への HTTP は `WP_HTTP_BLOCK_EXTERNAL` で止めている。

---

## 9. D2 以降に引き継ぐ課題

1. **本番反映の前提**：P1〜P8（運用者の読み取り確認）の結果、§3 の 7（`/design/` の 404 化）の判断、D1 用のリリース計画とマニフェスト（`release-gate` に D1 の項目を追加）、テーマの版番号。
2. **D5（本番相当）の隔離確認**：LiteSpeed・キャッシュ・CDN を通った応答で B1・B9 を確認し、`verified-production` に変える手順。L2 の `Secure`・`SameSite` の記録。CSP を外した試験用ビルドのブラウザから L3・L4。手動の Safari（macOS・iOS）と Chrome（Android）。
3. **V2（静的アセットが残らないこと）**：配布パッケージに published のアセットだけを入れる検査はある（`catalog-check.js` の package 検査）。リリース道具（`deploy.py`）での照合と、ロールバック時の削除手順は未着手（道具の変更は別承認）。アセットのファイル名に内容のハッシュを入れる案（D0 §17 V2）も未着手。
4. **D2**：詳細ページのブラウザ枠の幅切り替え（1280 / 834 / 390）、全画面表示、前後のデザインへの導線。サイトマップ（`/design-sitemap.xml`、published が 0 件ならインデックスに載せない）、JSON-LD。
5. **D3**：カスタマイズ（`params` は現在、空配列のみ許可）とコード・プロンプトのコピー。
6. **性能**：実機（GPU・低価格のスマホ）と Lighthouse での測定。12 カードでの一覧の計測。
7. **CI**：`catalog-check.js` と `run.php` は依存なしなので、GitHub Actions に追加できる（ワークフローの追加は別承認）。
8. **D0 文書への反映**：§3 の 1〜9。
9. 既存の PR #61・#63・#64 は OPEN のまま。#65 で内容が main に入ったため、閉じるかどうかは運用者の判断。

---

## 10. D1.5 追記（2026-11-16）：D1 レビューと作品ライブラリへの拡張

設計の詳細は `D1.5-Works-Model.md`。ここではレビュー結果、修正、試験結果を記録する。

### 10.1 D1 独立レビュー（コードを別の担当が読み、結果を自分で再現して確認）

Critical・High はなかったため、拡張は止めずに進めた。

| # | 重要度 | 指摘 | 対応 |
|---|---|---|---|
| 1 | Medium | `/design/web/{slug}/?omx_design_demo=1` で詳細の URL がデモ本体（`public, max-age=300`）を返す。クエリを無視するキャッシュに入ると、詳細ページがデモに置き換わる | **修正**：内部の query var は rewrite からだけ受け付け、クエリ文字列にあれば 404 |
| 2 | Low/Medium | canonical が Slim SEO 経由でしか設定されず、Slim SEO がないと一覧・詳細の canonical が `/design/` になる | **修正**：WordPress 本体の `get_canonical_url` にも同じ値を返す |
| 3 | Low | `/design/web/?omx_design_slug=…` で一覧の URL に詳細が出る | **修正**（#1 と同じ） |
| 4 | Low | 正規表現の `$` が末尾の改行を通す（重複 ID の検査をすり抜ける）。PHP で `{}` と `[]` を区別できない | **修正**：`\z` で終端を固定し、オブジェクトとしても読み直して型を確認 |
| 5 | Low | デモの `header_remove()` が他の HSTS なども消す | **修正**：消すヘッダーを決めた一覧に限定（HSTS が残ることを試験で確認） |
| 6 | Low | アセット名に内容のハッシュがなく、キャッシュ 300 秒固定（D0 V2 との差） | 未対応。D5 の配布作業に残す |
| 7 | Low | サイトが非公開設定のとき robots が `nofollow, follow` と矛盾 | **修正** |
| 8 | Low | IntersectionObserver がないと、ボタンを押しても再生されない | **修正** |

D1.5 の作業中に見つけて直したもの：

| 重要度 | 不具合 | 対応 |
|---|---|---|
| Medium | **本番での再反映で `/design/` 系が 404 になる**。D1 を入れたあと旧テーマにロールバックし、パーマリンクを再生成し（Runbook の手順）、もう一度入れると、版のオプションが一致するため rewrite ルールが作り直されない。ローカルで実際に起きた | rewrite ルールに自分のルールが入っているかも確かめ、なければ 1 回だけ作り直す。最初のリクエストから 200 になることを試験で確認。さらに同時アクセス時の 404（`update_option()` が古いキャッシュを残す競合）を、修復したリクエストだけ flush の直後にオプションを読み直すことで解消（`qa/rewrite-self-heal-audit.md`、試験 24 項目） |
| Low | `.dl-btn` などの `display` 指定が `hidden` 属性を上書きし、**JavaScript が無効なとき、押しても動かない再生ボタンが見えていた**（D1 から） | `.dl [hidden]` を必ず非表示にした。JS 無効で再生ボタンとコピーボタンが見えないことを試験で確認 |
| Low | 利用者が押した再生も「カードが画面に半分以上入っている」ことを条件にしていて、半分未満だと押しても何も起きなかった | 押した再生は画面に少しでも入っていれば始める（自動再生は従来どおり半分以上）。30% だけ見えるカードで試験 |

### 10.2 変更前と変更後の試験結果

| 試験 | D1.5 の変更前 | 変更後 |
|---|---|---|
| カタログ・静的ファイル | 70 / 70 | 94 / 94（不正なカタログ 52 種類を、スキーマと PHP の両方が拒否） |
| PHP 単体 | 60 / 60 | 70 / 70 |
| HTTP | 96 / 96 | 131 / 131 |
| 隔離 B2〜B9（Chromium・WebKit） | 72 / 72 | 72 / 72 |
| Firefox | 20 / 20 | 22 / 22 |
| 表示・操作（Chromium・WebKit） | 134 / 134 | 192 / 192 |
| 性能 | 32 / 32 | 32 / 32 |
| 既存 86 URL（**変更前のテーマ `2f800b2` から取り直した基準**と比較） | — | **差分 0** |
| 既存の試験（Learn・Labs・Industry Hub・SVG Motion・AI Tools ほか） | すべて合格 | すべて合格（`release-gate` の 1 件は D1 と同じく、新規ファイルを承認外の差分として検出する想定どおりの失敗） |

- 変更前の記録：`qa/before-d15/`（このとき回帰比較で `/contact/` の 1 件が差分になった。日付をまたいだことでお問い合わせフォームの nonce が変わったためで、比較スクリプトの正規化を直した）。
- 変更後のログ：`qa/logs/`、集計は `qa/summary-d15.txt`。スクリーンショットは `qa/screenshots/`（6 作品の一覧は `d15-six-works.webp`）。

### 10.3 試験で確かめた主な D1.5 の挙動

- `/design/` に Web と動画の 2 セクション、`/design/video/` に動画 2 件。種類・用途・表現・技術・業種・キーワードでの絞り込み（サーバー側とその場の両方）、該当のないセクションは隠れる。
- 動画の詳細：自動再生しない。再生を押すまで本編を読み込まない（ネットワークで確認）。押せば再生される（Chromium・WebKit・Firefox）。iframe・デモへのリンク・コードの欄は出ない。
- 検証の表示：`verified-local` は「ローカル環境で検証済み（本番環境では未検証）」。全作品を未検証扱いにすると「未検証」だけになり、「検証済み」という文字は出ない。
- 制作ボタン：Motion Studio・生成・作成のボタンは出ない（`omochix_design_motion_studio_url()` は常に空。試験で固定）。
- コード：Web 作品の HTML・CSS・JS は、デモの実ファイルから作ったエスケープ済みのテキスト。コピーボタンの結果をクリップボードで確認（Chromium）。
- スマホ：6 枚のポスターはスクロールで読み込まれる。横スクロールなし。ダークモードで axe の serious・critical 違反 0。
