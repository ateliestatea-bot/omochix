# OmochiX Industry Hub — Phase 2 詳細設計 v0.2（確定案）

作成日：2026-11-07 / Claude Code
基準：指示された基準は `origin/main` = `4a5d382`。ただし 2026-11-07 の確認で、main は **`a3973a6`（PR #58、AI Tools P0）に進んでいた**（§0.1）。基準の変更には承認が必要。
状態：v0.2 承認済み（2026-11-07）。基準は `a3973a6` に変更して承認された。実装中（ブランチ `feature/industry-hub-p2-cc`）。

---

## 0. v0.1 からの変更点

| 項目 | v0.1 | v0.2 |
|---|---|---|
| D-1 固定ページ | 推奨 | **承認**。本番 DB へのページ作成はリリースの承認まで禁止 |
| D-2 G1 未達は 404 | 推奨 | **承認**。編集者はプレビューできる |
| D-3 サブ業種 | 301 で親へ | **302**（一時転送）で、親の `?sub=` 付き URL へ。検証結果と理由は §3 |
| D-4 未知の値 | 無視して全件表示 | **400 / 404 で返す**。正しい条件で0件のときは 200 と0件表示（§4） |
| D-5 公開スイッチ | テーマから有効化 | **承認**。副作用を防ぐ仕組みを追加（§5） |
| 業種ハブの並び | 課題が先頭 | **概要とメリット → 課題の選択 → 事例 → ワークフロー → ツール・プロンプト・Motion → その他**（§6） |
| スマホ | 対応 | **スマホ優先**。少ない操作で教材に届く導線（§6.3） |

### 0.1 2026-11-07 の再確認

- `origin/main` は **`a3973a6`** に進んだ（PR #58「AI Tools P0」、2026-11-06 マージ）。
  - 内容：`search.php`、`archive-ai_tool.php`、`single-ai_tool.php`、`inc/ai-tool-detail.php`、`style.css`（版 1.27.1）、Core の `tool-display-facts.php`（新規）、`software-application-schema.php`、`omochix-core.php`（版 `1.10.0-dev`）、`tests.yml` ほか。
  - 本番はテーマ 1.27.1・Core 1.9.3。リリース記録で、Industry Hub は**本番に含まれていない**と確認した。
  - Phase 2 の設計に影響する点は2つ。
    - Core の新しいファイルは `is_readable()` で守って require している（FTP 配布で途中までしか上がらなくても壊れないため）。Phase 1 の `includes/industry-hub.php` の require は守られていない。**リリースまでに同じ方式へそろえる**（§10 の P2-02）。
    - `search.php` が変わったが、Phase 2 は `search.php` を触らない。
  - **推奨：Phase 2 の基準を `a3973a6` にする**（要承認）。
- Labs #56 は 2dcb3ac に、SVG Motion #55 は e218142 に進んだ。新しいコミットはどちらも自分のファイル（CSS・`labs.php`）だけ。
  - **Labs #56 は新しい main と `style.css` の版番号の行で衝突する**（1.27.1 と 1.28.0）。Industry Hub とは関係なく、Labs 側の版の調整が必要。
  - SVG Motion #55 は新しい main と衝突しない。
  - 既存の共有ファイル（`shell.php`・`functions.php`・`page-learn.php`・`style.css`）への両ブランチの変更は v0.1 の調査どおり残っている（§9）。
- 既存の作業ツリーと Phase 1 の worktree は変更していない。

---

## 1. 確定事項（D-1〜D-5）

| ID | 内容 |
|---|---|
| D-1 | `/learn/industries/` は WordPress の固定ページ（`learn` の子、slug `industries`）＋ `page-learn-industries.php`。**本番 DB へのページ作成はリリースの承認まで禁止**。ページが無い間、入口の URL は 404 のままで、業種ハブ自体（ターム）は独立して動く |
| D-2 | **表示の条件は G1 ＋ G2〜G5**（編集者のチェックと、概要・課題・事例・公式ワークフローの中身）。満たさない業種は閲覧者に **404**。`edit_term` 権限のある編集者にはプレビューを表示する（noindex,nofollow、「未公開プレビュー」バナー、`no-store`）。G6（鮮度）と G7（出典）は index の条件だけに使う。レビュー #8 を反映：中身の無いハブはチェックだけでは公開されない |
| D-3 | サブ業種の URL は **302** で親業種の `?sub=` 付き URL へ転送する。将来、サブ業種を独立したページにできるようにしておく（§3） |
| D-4 | 未知の業種・ページ番号は **404**、未知のフィルター値は **400**。正しい条件で0件なら **200** と0件表示（§4） |
| D-5 | テーマが `omochix_core_industry_public` を有効にする。既存ページ・URL への副作用は §5 の仕組みで防ぐ |

---

## 2. ページ構成と URL

| URL | 実体 | 状態コード |
|---|---|---|
| `/learn/industries/` | 固定ページ（D-1） | 200（ページが無ければ 404） |
| `/learn/industries/page/N/` | 入口の横断検索のページ送り | 200 / 範囲外は 404 |
| `/learn/industries/{industry}/` | `omx_industry` のトップレベルのターム | 200（表示条件 G1＋G2〜G5 を満たす）/ 404（満たさない、訪問者）/ 404（存在しない） |
| `/learn/industries/{industry}/page/N/` | 「その他の関連コンテンツ」のページ送り | 200 / 範囲外は 404 |
| `/learn/industries/{sub}/` | サブ業種 | 302 → 親（§3）/ 親が表示条件を満たさなければ 404 |
| 既存の URL | 変更なし | 既存のまま |

---

## 3. D-3 サブ業種の URL：最終仕様と検証

### 3.1 検証した事実（2026-11-07、使い捨て WordPress 7.1.2 ＋ Slim SEO 4.9.11）

- 公開スイッチを入れた状態では、`/learn/industries/rental/`（サブ業種）は core のままだと **200** で、親と同じテンプレートで表示される。
- 試作の転送で、302・`Location`・`X-Robots-Tag: noindex, follow`・キャッシュ無効のヘッダーが期待どおりに出る。転送先（絞り込みあり）は noindex で、Slim SEO は canonical を出さない。
- 試作では元のクエリ（`?purpose=`）が転送先に引き継がれなかった。本仕様で、検証済みの値を引き継ぐ形に直す。

### 3.2 方式の比較

| 方式 | 検索エンジン | 将来の独立ページ化 | 判断 |
|---|---|---|---|
| A. 301 → 親 `?sub=`（noindex） | 転送元・転送先とも index されない。永続の転送先が noindex という矛盾したシグナル | 301 はブラウザや検索エンジンに長く記憶されるため、取り消しが難しい | 不採用（ご指摘の問題） |
| B. 301 → 親（index される素の URL） | 親に評価が集まる | 同上で取り消しが難しい。サブの文脈も失う | 不採用 |
| **C. 302 → 親 `?sub=`（noindex）** | どちらも index されない。サブ業種はまだ SEO 対象外という意図と一致する | 302 は記憶されないので、独立ページ（200）へいつでも切り替えられる | **採用** |
| D. サブ URL を 200 で表示（noindex） | 転送無し。重複はあるがどちらも noindex | 切り替えは最も簡単 | 代案（初期は転送したいという方針に合わないため不採用） |

### 3.3 最終仕様（テスト可能）

| # | リクエスト | 期待する応答 |
|---|---|---|
| S-1 | `/learn/industries/{sub}/`（親が表示条件を満たす） | **302**。`Location: /learn/industries/{parent}/?sub={sub}`。`Cache-Control: no-store` を含む。`X-Robots-Tag: noindex, follow` |
| S-2 | `/learn/industries/{sub}/?purpose=marketing`（正しい値） | 302。`Location` に `sub` と `purpose=marketing` の両方を含む |
| S-3 | `/learn/industries/{sub}/?purpose=nope` | 転送せず **400**（D-4 を先に判定する） |
| S-4 | `/learn/industries/{sub}/page/2/` | 302 で `?sub={sub}`（ページ番号は引き継がない） |
| S-5 | `/learn/industries/{sub}/`（親が表示条件を満たさない） | **404**（転送で親の存在を漏らさない）。編集者には 302 |
| S-6 | `/learn/industries/{parent}/?sub={sub}` | 200。noindex,follow。canonical なし。サブ業種で絞った一覧 |
| S-7 | `/learn/industries/{parent}/?sub={他業種のサブ}` | 400 |
| S-8 | sitemap | サブ業種の URL は含めない |
| S-9 | サイト内のリンク | サブ業種のチップは `?sub=` の URL を直接リンクする（転送を経由しない） |

### 3.4 将来、サブ業種を独立ページにするとき

- 新しい term meta `industry_sub_standalone`（真偽値）と、サブ業種用のゲートを追加する。
- 有効になったサブ業種は 302 を止めて **200** で表示し、ゲートを満たせば index して sitemap に載せる。302 なので、切り替えても過去の転送が記憶されて残る問題は起きない。
- Phase 2 では、転送の判定を関数 `omochix_industry_hub_sub_mode( $term )` にまとめておく（返り値は `'redirect'` のみ）。将来は `'standalone'` を足すだけで切り替えられる。

---

## 4. D-4 不正な値の扱い：最終仕様

### 4.1 判定の順序と、判定する場所（レビュー #1 を反映）

すべての判定を1つの関数 `omochix_industry_hub_decide()` にまとめる。
- 呼び出すのは `pre_handle_404`（core の `handle_404` の中、範囲外ページの 404 より前）と、`template_redirect` の優先度 0（`redirect_canonical` より前、HEAD リクエストのテンプレート読み込みの終了より前）。
- 結果をリクエスト内にキャッシュする。
- 状態コードとヘッダーは `template_redirect` の段階で確定させ、出力が始まってから変えない。
- `/learn/industries/` 配下では `do_redirect_guess_404_permalink` を false にする（似た slug の投稿への 301 を防ぐ）。

判定の順序：
1. パス：業種が存在しない → **404**。表示条件（D-2）を満たさない → **404**（訪問者）。`redirect_canonical` より前に判定するので、末尾のスラッシュ無しや `?omx_industry=` でも、先に 301 を出さない（業種の存在を漏らさない）。
2. 大文字などの正規でない slug（`/Rental/`）→ **301** で小文字の正規 URL へ（正規化なので恒久転送でよい）。
3. サブ業種 → §3（クエリを先に検証する）。
4. クエリ（Industry Hub が使うキーだけ。§4.2）→ 不正なら **400**（`pre_handle_404` で core の 404 を止める）。
5. ページ番号：範囲外 → **404**。入口ページ（`/learn/industries/page/N/`）は core が 404 にしないので、自前で範囲を確認する。
6. 結果が0件：1ページ目なら **200** と0件表示、2ページ目以降は 404。
7. フィードと埋め込み（`/{industry}/feed/`、`/{industry}/embed/`、`/feed/?omx_industry=`）→ **404**（`pre_get_posts` で判定する。URL では判定しない）。

### 4.2 対象のキーと、正しい値

| キー | 正しい値 | 空文字 | 不正 |
|---|---|---|---|
| `purpose` | 存在する `omx_purpose` の slug | 指定なし扱い（200） | 400 |
| `type` | `workflow`, `practice`, `tool`, `prompt`, `learn`, `news` | 指定なし | 400 |
| `level` | `beginner`, `intermediate`, `advanced` | 指定なし | 400 |
| `provider` | `official`, `external-service`, `creator`, `partner` | 指定なし | 400（`unclassified` も 400） |
| `sub` | **この業種の**サブ業種の slug | 指定なし | 400 |
| `q` | `sanitize_text_field` 後に1〜60文字 | 指定なし | 61文字以上は 400 |
| `industry`（入口の検索だけ） | 表示条件を満たすトップレベルの業種 | 指定なし | 400 |

共通のルール：
- slug は `sanitize_title()` で照合する（大文字は小文字に、パーセントエンコードは復元してから）。`q` は `mb_strlen()` で数え、空白だけなら空として扱う。
- `type` の `tool` と `prompt` は、§6 の「その他の関連コンテンツ」の一覧の対象（main query の `post_type`）。
- 値が配列（`?purpose[]=x`）→ 400。
- 同じキーが2回（`?purpose=a&purpose=b`）→ 400。`$_SERVER['QUERY_STRING']` を直接解析して判定する。
- 上の表に無いキー（`utm_*`, `gclid`, `fbclid`, `ref` など）→ **無視して 200**。広告やシェアからの流入を壊さないため。S-1・S-2 の 302 でも、これらはそのまま転送先へ引き継ぐ。
- `/{sub}/?sub=x`（パスとクエリでサブ業種が二重）→ 400。`/{sub}/feed/` → 404。
- サイト内検索の `?s=…&omx_industry={表示条件を満たさない業種}` → その条件を無視する（検索結果を絞らない）。
- WordPress core のクエリ変数（`?p=` など）は core の挙動のまま。

### 4.3 400 の応答

- `status_header(400)`、`nocache_headers()`（`no-store`）、`X-Robots-Tag: noindex, follow`。robots は `slim_seo_robots_index` と `wp_robots` の両方で noindex にし、canonical は `slim_seo_canonical_url` で空にする（Slim SEO が無効な場合も `rel_canonical` を外す）。
- 本文は業種ハブのレイアウトのまま、「指定された条件が正しくありません」と、条件を外す・業種の入口へ戻るリンクを出す。一覧は出さない。
- Slim SEO は noindex のとき canonical を出さない（検証済み）。

### 4.4 0件のとき

- 正しい条件で0件：**200**、noindex,follow、「条件に合うコンテンツがありません」と条件を外すリンク、同じ業種の公式ワークフローを最大3件おすすめとして出す。

### 4.5 既存の Learn 検索への影響

| 既存の機能 | 現在の不正値の扱い | Phase 2 の影響 |
|---|---|---|
| 実践教材の一覧 `/learn/practice/` | 不正値は無視して全件 | **変更しない** |
| Motion Gallery `/learn/motion/` | 不正値は空として扱う | 変更しない |
| サイト内検索（`content_type`） | 不正値は `all` | 変更しない |
| Industry Hub | （新規） | 厳格な判定は `/learn/industries/` 配下だけに適用する |

- 挙動に差が出る（Industry Hub だけ 400 になる）。その代わりに、Industry Hub のフォームは選択肢以外の値を送れない UI にし、通常の操作では 400 にならないようにする。
- 既存の機能をそろえるかどうかは Phase 2 の範囲外とし、必要なら別のタスクにする。

---

## 5. D-5 公開スイッチの副作用対策

| リスク | 対策 | 確認方法 |
|---|---|---|
| rewrite ルールの追加 | 増えるのは `learn/industries/([^/]+)/…` の5件だけ（Phase 1 の版番号の仕組みで flush は1回） | 有効化の前後で既存のルール一覧（順序まで）を比較する |
| 既存 URL との衝突 | `/learn/industries/` 配下だけ。`page` は予約スラッグ | 既存 URL の回帰表 |
| REST で準備中の業種が見える | 公開後は `/wp/v2/industries` が公開されるため、閲覧者には**表示条件（G1＋G2〜G5）を満たす業種だけ**を返すよう `rest_omx_industry_query` で絞る | REST の結合テスト |
| サイト内検索の `?omx_industry=` | タクソノミーのクエリ変数でサイト内検索を絞れる（検証済み）。表示条件を満たさない業種の条件は無視する（§4.2） | 結合テスト |
| `?omx_industry=slug` の正規化 | core が 301 で `/learn/industries/slug/` に転送する（検証済み）。転送先の判定（G1・404）がそのまま効く | 結合テスト |
| テーマの切り替え・巻き戻し | スイッチはテーマのフィルタなので、テーマを外せば Phase 1 の非公開状態に戻り、flush も1回 | Phase 1 の手順で確認済み。Phase 2 の回帰でも再確認 |
| 投稿の REST フィールド | Phase 1 で、閲覧者には業種のターム ID を出していない。公開後は `industries` だけ出す（Phase 1 の実装どおり） | 既存のテスト |

---

## 6. 業種ハブの画面

### 6.1 セクションの順序（必須）

| # | セクション | データ | 中身が無いとき |
|---|---|---|---|
| 1 | **業種の概要と AI 活用のメリット** | `industry_lead` ＋ **`industry_benefits`**（新しい term meta、最大6行 `{title, body}`）、最終確認日 | 概要は G2 で必須。メリットは任意 |
| 2 | **解決したい業務課題を選ぶ** | `industry_challenges`（行に `purpose` を任意で追加）。選ぶと `?purpose=` に絞り込み、`#workflows` へ移動 | G3 で必須 |
| 3 | **AI 活用事例** | `industry_usecases`（課題で選んだ業務目的の事例を先頭に、それ以外も表示） | G4 で必須 |
| 4 | **実践ワークフロー** | Workflow（公式を先頭）＋ `industry_featured_ids`。選んだ業務目的で絞る | G5 で公式が1件以上 |
| 5 | **推奨 AI ツール・プロンプト・Motion 教材** | キュレーションしたツール＋業種タグのツール、プロンプト、Motion（catalog に存在するもの） | 空の区画は見出しごと出さない |
| 6 | **その他の関連コンテンツ** | 外部コンテンツ（台帳カード）、実践教材・Learn 記事・ニュースの一覧（絞り込み・ページ送り、main query） | 0件の表示（§4.4） |

### 6.2 部品

v0.1 §3 の部品に `benefit-list.php` を追加する。`challenge-card.php` は、選択中の状態を `aria-pressed` ではなくリンクとして表す（GET の状態を URL で持つため）。

### 6.3 スマホ優先の導線（タップ数の目標）

| 経路 | タップ数 |
|---|---|
| 入口 → 業種カード → 業種ハブ（課題が画面1枚目に見える） | 1 |
| 業種ハブ → 課題 → その課題のワークフロー（`?purpose=…#workflows`） | 2 |
| 入口の「業種 × よくある課題」ショートカット → ワークフロー | 1 |
| ワークフロー → 実践教材の詳細 | +1（合計 3 以内） |

UI の要件：
- 375px 幅で、1画面目に業種名・概要の先頭・課題のチップが入る。
- 課題のチップは横スクロール。現在の選択を文字でも示す。
- セクション内のジャンプリンク（概要・課題・事例・ワークフロー・ツール・その他）を、スマホでは画面下部に固定する。`position: sticky`、高さ 56px、safe-area に対応。
- 絞り込みバーはスマホでは `<details>` で折りたたむ。
- 1カラムを基本とし、768px 以上で2列、1200px 以上で3列。

---

## 7. データ取得と N+1 対策

v0.1 §4・§5 のとおり。確定した追加事項（レビュー #2・#3 を反映）：

- **ゲート結果の保存**：トランジェントは使わない。キーが毎回変わり、永続オブジェクトキャッシュが無いと閲覧のたびに DB へ書き込むため。
  - 業種タームの非公開 meta `_industry_gate_cache` に `{result, computed_at}` を保存する。
  - 次のフックで無効にする：業種の term meta の更新、`learn_practice` の `transition_post_status`、`omx_industry`・`learn_format`・`omx_provider_type` の `set_object_terms`、workflow メタの更新。
  - G6（鮮度）は時間で変わるので、`computed_at` から24時間で再計算する。
  - 再計算は無効化の後の最初の読み込みで1回だけ。
- **ゲートはまとめて計算する**：入口ページでは、6業種の公式ワークフローの候補を1回の tax_query（IN）で取り、業種ごとに振り分ける。候補の投稿・メタ・タームと、`related_tool_ids` が指す投稿を、まとめて事前に読み込む。
- **term meta の読み出し**：`omochix_core_get_industry_meta()` はサニタイズのたびに `get_post_type()` と `term_exists()` を呼ぶ。読む前に、参照されている投稿を `_prime_post_caches()` で、業務目的のタームを `get_terms(include)` で、まとめて読み込む。

- **プラグイン（Phase 1 のデータモデルは維持し、追加だけ）**
  - `industry_benefits` の term meta（新しいキー）
  - `industry_challenges` の行に `purpose` 列（任意。既存の行はそのまま読める）
  - `omochix_core_count_official_workflows()` と `omochix_core_get_industry_posts()` に事前取得を入れる（`update_meta_cache` / `update_object_term_cache`）
  - ゲートの結果と業種ごとの件数を、`last_changed` を含むキーのトランジェントに保存する
  - 表示条件（G1＋G2〜G5）を満たす業種だけを返す関数 `omochix_core_get_visible_industries()`
- **テーマ**
  - 区画の投稿をまとめて `update_post_caches()` を1回呼ぶ
  - main query は `update_post_meta_cache` / `update_post_term_cache` を既定のまま（true）にする
- **クエリ予算**
  - Slim SEO 有効の使い捨て WordPress で `SAVEQUERIES` を使い、**キャッシュが冷えた状態**で計測する
  - 「追加クエリ」の定義：同じテンプレートで中身が空の業種ハブとの差
  - 上限は実測してから受け入れ基準に固定する（目安：業種ハブ 20 以内、入口 15 以内）
  - 区画の件数を 3→12 に増やしても、追加クエリが投稿数に比例して増えないこと（増加 ≤ 2）をテストする

---

## 8. SEO 仕様（v0.1 §6 を確定）

| URL | robots | canonical（Slim SEO） | sitemap |
|---|---|---|---|
| 入口（G1〜G7 を満たす業種が1件以上） | index | 自己 | 固定ページとして含める |
| 入口（その業種が0件）・検索・ページ送り | noindex,follow | 出さない | — |
| 業種ハブ（G1〜G7 すべて達成、絞り込み・ページ送りなし） | index | 自己 | 含める |
| 業種ハブ（G2〜G7 に未達）・絞り込み・ページ送り | noindex,follow | 出さない | 除外 |
| 編集者のプレビュー（表示条件を満たさない） | noindex,nofollow | 出さない | 除外 |
| 400 / 404 / 302 | noindex（ヘッダー） | — | — |

- 「noindex のときは canonical を出さない」は既存の方針で、使い捨て環境の Slim SEO 4.9.11 で確認した。ただしこれは、noindex を `slim_seo_robots_index` で指定した場合に限る。Industry Hub はすべての noindex を、`slim_seo_robots_index`・`wp_robots`・`slim_seo_canonical_url` の3か所で指定する（レビュー #6）。
- 編集者プレビューの `nofollow`：Slim SEO が `follow` を付けるので、Slim SEO より後の優先度で上書きする。
- **Slim SEO の「中身が無いページは noindex」**（`$wp_query->post_count` が0）：ゲートを満たした業種ハブで、「その他の関連コンテンツ」が0件でも index されるよう、`slim_seo_robots_index` でゲートの結果を優先する（レビュー #4）。
- **sitemap**（レビュー #5）：
  - Phase 1 の優先度 11 の一括除外は、公開スイッチが off のときだけ効くようにプラグイン側で変える。
  - テーマは `get_terms` で `ss_sitemap` のときに、ゲートを満たすトップレベルの業種だけを返す。条件は `parent=0`、`hide_empty=false`（サブ業種にだけ投稿が付いている親も残すため）。`include` が空のときの対策も入れる。
  - ゲートを満たす業種が0件なら、入口の固定ページを `slim_seo_sitemap_post_ignore` で外す。
- **JSON-LD**（レビュー #7）：Slim SEO はタームアーカイブに CollectionPage と BreadcrumbList を既に出している。重複させず、`slim_seo_schema_webpage` と `slim_seo_schema_breadcrumblist` を拡張し、ItemList だけを追加して @id でつなぐ。
- 実際のコンテンツが無い業種は公開も index もしない（表示は G1＋G2〜G5、index は G1〜G7）。
- **情報の漏れ**（レビュー #9）：表示条件を満たさない業種は、閲覧者に対して次のすべてから外す。`/wp/v2/industries`（一覧と単体）、投稿の REST の `industries` フィールドと `?industries=` 絞り込み、`post_class`（`post_class_taxonomies`）、フィード・埋め込み（404）。

---

## 9. 競合回避（v0.1 §7 を確定）

- `page-learn.php`・`style.css`・`tests.yml` は触らない。
- 導線は `inc/learn.php` の `omochix_get_learn_categories()` に1件追加する。
- `inc/shell.php` は**最後の1コミット**だけにする。内容は `omochix_shell_enabled` と `omochix_shell_page_templates` のフィルタを追加し、Industry Hub はそのフィルタで自分を登録する。
  - 注意（レビュー #11）：Labs は `shell.php` の30行目（`omochix_shell_enabled()` の戻り値）、SVG Motion は20行目（テンプレート配列）を変更している。フィルタ化はこの2か所に触れるので、**どちらかがマージされた後に PR-B を rebase する**か、衝突の解消手順を PR に書く。
  - Phase 2 は Labs・SVG Motion のマージを待たない。PR-A は共有ファイルに触れないので、いつでもマージできる。
- `functions.php` は**ファイルの本当の末尾**に require を1行（Labs は `article-v2` の require の直後、SVG Motion は中ほど）。
- Industry Hub の `template_include` は**優先度 9 以下**。シェルが最終テンプレートを記録するのは優先度 99 なので、それより前に決める。
- `page-learn-industries.php` には「Template Name」ヘッダーを付けない（付けると `get_page_template_slug()` が値を返し、シェルが無効になる）。
- PR は2本に分ける：PR-A（プラグイン＋テーマの新規ファイル）、PR-B（`inc/learn.php`・`shell.php`・`functions.php` の最小変更）。
- PR を出す前に毎回、`git merge-tree` と3ブランチを合わせたツリーでの全テストを行う。

---

## 10. 実装タスク（推奨順序）

| 順 | ID | タスク | PR |
|---|---|---|---|
| 1 | P2-01 | テスト基盤：テーマ用 stub、使い捨て WordPress ＋ Slim SEO のコピー手順、HTTP の結合テスト（PHP の組み込みサーバー、HEAD を含む） | A |
| 2 | P2-02 | プラグインへの追加：`industry_benefits`、課題の `purpose`（サニタイズで業務目的の存在を確認）、事前取得とゲートの一括計算、`_industry_gate_cache` と無効化のフック、`get_visible_industries`（G1＋G2〜G5）、REST と `post_class` からの除外、sitemap の一括除外の条件変更、`industry-hub.php` の require を `is_readable()` で守る | A |
| 3 | P2-03 | ルーティング：公開スイッチ、`template_include`、G1 の 404 とプレビュー、サブ業種の 302（§3.3） | A |
| 4 | P2-04 | 判定：クエリの検証と 400、0件、範囲外の 404（§4） | A |
| 5 | P2-05 | 業種ハブのテンプレートと部品（§6 の順序） | A |
| 6 | P2-06 | 入口ページ：業種カード、「業種 × 課題」のショートカット、横断検索とページ送り | A |
| 7 | P2-07 | CSS（スマホ優先）とアクセシビリティ | A |
| 8 | P2-08 | SEO：robots、sitemap、title、description、JSON-LD、breadcrumb | A |
| 9 | P2-09 | 性能の計測と調整（クエリ予算） | A |
| 10 | P2-10 | 導線と組み込み：`inc/learn.php`、`functions.php`、`shell.php`（フィルタ化） | B |
| 11 | P2-11 | 回帰：既存 URL の回帰表、3ブランチを合わせたテスト、Runbook に「固定ページ作成（承認後）」を追記 | B |

---

## 11. テストと受け入れ基準

### 11.1 自動テスト

| ファイル | 内容 |
|---|---|
| `tests/industry-hub/theme-routing.php`（stub） | §2 の状態コード、G1 の 404 とプレビュー、§3.3 の S-1〜S-9 |
| `tests/industry-hub/theme-validate.php`（stub） | §4.2 の表を網羅：キーごとに正しい値・空・不正・配列・重複・無関係のキー |
| `tests/industry-hub/theme-seo.php`（stub） | §8 の表を網羅。ゲートの境界 |
| `tests/industry-hub/theme-render.php`（stub） | §6.1 の順序、空の区画、提供元のバッジ（未分類は無し、PR は文字でも）、`rel`、エスケープ、Tab で止まる位置 |
| `tests/industry-hub/theme-perf.php`（stub） | 事前取得のあと、カードごとのメタ・タームのクエリが0であること |
| `tests/industry-hub/http-integration.php`（使い捨て WordPress ＋ Slim SEO、CI 対象外） | 実際の HTTP で、状態コード・ヘッダー・robots・canonical・sitemap・クエリ数・既存 URL の回帰表 |
| 既存のテスト | Tests と Industry Hub の全件（PHP 8.1 / 8.3） |

### 11.2 受け入れ基準

- [ ] §3.3（S-1〜S-9）と §4.2 の表のすべての行が、自動テストで成功する
- [ ] 正しい条件で0件なら 200 と0件表示。不正な値は 400。存在しない業種と範囲外のページは 404
- [ ] 実践教材・Motion・サイト内検索の不正値の扱いが変わっていない（回帰テスト）
- [ ] 表示条件（G1＋G2〜G5）を満たさない業種は訪問者に 404、編集者はプレビュー（noindex,nofollow）。G1〜G7 すべて達成した業種だけ index され sitemap に載る
- [ ] 業種ハブの区画が §6.1 の順序で表示される。中身の無い区画は出ない
- [ ] **375×667** の画面で、1画面目に業種名・概要（3行まで）・課題のチップが入る。メリットは2件まで表示し、残りは折りたたむ。業種を選んでから実践教材まで3タップ以内
- [ ] 選択中の課題チップに `aria-current="true"`。下部固定ナビは DOM 上の順序と見た目の順序を一致させる。`scroll-padding-bottom` と `scroll-margin` で、フォーカスがナビに隠れない（WCAG 2.4.11）
- [ ] 課題に業務目的が無いチップは、絞り込まずに `#workflows` へ移動する。業務目的で絞って公式ワークフローが0件なら、区画4に「この課題の公式ワークフローは準備中」と、絞り込まない一覧へのリンクを出す
- [ ] HEAD リクエスト、末尾スラッシュ無し、`?paged=`、`?omx_industry=`、大文字の slug、似た slug の投稿があるとき、のそれぞれで §2〜§4 の状態コードになる（301 で業種の存在を漏らさない）
- [ ] G1 の 404・302・400・編集者プレビューの応答に `Cache-Control: no-store`
- [ ] ゲートを満たした業種ハブで「その他の関連コンテンツ」が0件でも index（Slim SEO の中身無し判定を上書き）
- [ ] axe で critical・serious の違反 0、キーボードだけで操作できる、JS が無くても動く、light / dark で崩れない
- [ ] 業種ハブの追加クエリ数が予算内。区画の件数を増やしてもクエリ数が比例して増えない
- [ ] 公開スイッチの前後で、既存の rewrite ルール（順序まで）と既存 URL の状態コード・robots・canonical が同一
- [ ] REST・`post_class`・フィード・埋め込み：閲覧者に表示条件を満たさない業種が見えない。業務目的と提供元は引き続き 401
- [ ] 既存 CI の全件と新しいテストが PHP 8.1 / 8.3 で成功。Labs・SVG Motion と合わせたツリーでも全テストが成功
- [ ] 本番 WordPress・DB への変更なし。固定ページの作成はリリースの承認後

---

## 12. 実装前レビュー（独立レビュー、2026-11-07）

High 4件・Medium 9件・Low 数件の指摘をすべて設計に反映した。

| # | 重要度 | 指摘 | 反映先 |
|---|---|---|---|
| 1 | High | 判定のフックが決まっていない：core の 404 が 400 より先に出る、HEAD で状態コードが届かない、`redirect_canonical` の 301 で業種の存在が漏れる、似た slug の推測転送 | §4.1（判定関数を `pre_handle_404`＋`template_redirect` 優先度 0 に置く、推測転送を止める）、受け入れ基準 |
| 2 | High | `last_changed` をキーにしたトランジェントは、永続キャッシュが無いと閲覧のたびに DB へ書き込む。G6 は時間で変わる | §7（term meta へ保存、フックで無効化、24時間で再計算） |
| 3 | High | ゲート・ワークフローの再確認・term meta のサニタイズが投稿ごとにクエリを出す。目安 25 の根拠が無い | §7（一括計算と事前読み込み、追加クエリの定義、実測で上限を決める） |
| 4 | High | Slim SEO は投稿0件のページを noindex にする | §8（ゲートの結果を優先）、受け入れ基準 |
| 5 | Medium | Phase 1 の sitemap 一括除外とターム単位の判定が両立しない。`hide_empty` で親が消える。入口ページの扱いが無い | §8（sitemap） |
| 6 | Medium | `wp_robots` だけの noindex では canonical が残る。プレビューの nofollow | §4.3、§8 |
| 7 | Medium | Slim SEO の CollectionPage・BreadcrumbList と重複する | §8（Slim SEO の entity を拡張し、ItemList だけ追加） |
| 8 | Medium | G1 だけでは中身の無いハブが公開できてしまう | D-2（表示は G1＋G2〜G5） |
| 9 | Medium | REST 単体・投稿の REST・`post_class`・フィード・埋め込みからの漏れ | §4.1、§8 |
| 10 | Medium | 課題の業務目的のサニタイズ、業務目的の無いチップ、区画4の0件 | §10 の P2-02、受け入れ基準 |
| 11 | Medium | Labs・SVG Motion の共有ファイル変更の扱い、`template_include` の優先度、Template Name | §0.1、§9 |
| 12 | Medium | 375px の1画面目にチップが入らない可能性、画面の高さが未定義 | 受け入れ基準（375×667、メリットは折りたたみ） |
| 13 | Medium | 404・302・400 のキャッシュ | §4.3、受け入れ基準 |
| — | Low | slug の照合方法、`q` の数え方、302 での utm の引き継ぎ、`/{sub}/?sub=`、フィード、検索の `omx_industry`、`type=tool/prompt`、入口のページ範囲、アクセシビリティの細部、main の更新 | §4.1・§4.2・受け入れ基準・§0.1 |

レビューで「問題なし」とされた点：302 の採用と、将来の独立ページ化の仕組み。厳格な判定を `/learn/industries/` 配下に限定し、無関係なキーは無視すること。正しい条件で0件なら 200。テーマのフィルタによる公開スイッチとロールバック。予約スラッグ。`industry_benefits` の追加と、既存の課題行の互換性。PR の2分割。本番 DB・ページを変更しないこと。

## 13. リスク・未決定

0. **基準の変更**：main が `a3973a6` に進んだ。Phase 2 は `a3973a6` を基準にすることを推奨する（要承認）。
1. 本番相当のデータでの性能は検証できない（ステージングが無い）。
2. 400 応答は、利用しているキャッシュや CDN が 4xx をキャッシュしないことが前提（`no-store` を付ける）。本番のキャッシュ構成は未確認。
3. Industry Hub だけ不正値の扱いが厳格になる（§4.5）。UI で不正値を送れないようにして緩和する。
4. `industry_benefits` の追加は、Phase 1 のデータモデルへの**追加**（既存のキーは変えない）。
5. 初期6業種の本文（概要・メリット・課題・事例・公式ワークフロー）が揃うまで、どの業種も公開・index されない。コンテンツ作業は別に進める必要がある。
6. 本番の配布は FTP（P0 のリリース記録）。Phase 1・2 のファイルを FTP で上げる順序によっては、途中の状態で読み込みが失敗しうる。`is_readable()` で守る（P2-02）ことと、リリース PR で配布順序を決める。
7. Labs #56 は新しい main と `style.css` の版番号で衝突している（Labs 側の対応）。
