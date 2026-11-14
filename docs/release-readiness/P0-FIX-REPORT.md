# P0 Fix & Release Stabilization — 結果 (2026-11-12)

作業場所: `~/omochix-nav-ux-wt`(`feature/nav-ux-update-v1`、HEAD a617fd3、**未コミット**)。commit / push / PR / merge / rebase / reset / stash・本番操作・別 worktree の変更なし。Industry Hub RC には未接触。テーマ Version は未確定のまま(統合順序確定後)。
開発環境の DB には検証用の公開/非公開フィクスチャ(`LEAKTEST`・`d6-*`・`claude-code` 等、開発 DB のみ)を追加しました。

## 1. D1 日本語IME(**修正・検証済み**)
- `assets/js/search-suggest.js` keydown の先頭: `if (composing || e.isComposing || e.keyCode === 229) return;`(`composing` は既存の compositionstart/end で管理)。
- 検証(`tests/nav-ux/ime.cjs`・`ime-check.js`、Firefox は `firefox-suggest.cjs`): **Chromium / WebKit / Firefox 159 すべて PASS** — 変換中(`isComposing`)・keyCode 229・compositionstart〜end の間の ↓/↑/Enter/Esc は `preventDefault` されず、ページ遷移なし。確定後は ↓ で候補選択(`aria-activedescendant`)、Esc で閉じる通常動作に戻る。
- **未検証**: 実機の日本語 IME(iOS Safari・Android Chrome・macOS/Windows)。合成イベントでの検証のため、実機確認がステージング必須項目。

## 2. D2 固定ページの `/page/N/`(**修正・検証済み**、Slim SEO 環境は未検証)
- `inc/seo.php`: トップ限定の処理を `omochix_singular_pagination_target()` / `omochix_singular_no_pagination()`(`template_redirect` 優先度1)に置換。**単一ページで `<!--nextpage-->` を持たないものの `page`/`paged` ≥ 2 → 正規URLへ 301**(`wp_safe_redirect`、同一ホストのみ)。
- 除外: 投稿ページ(`/ai-news/page/N/` は WordPress のアーカイブページ送り)、アーカイブ全般、`<!--nextpage-->` を持つ本当の複数ページ、**Industry Hub `/learn/industries/`**(`omochix_paged_singular_paths` フィルターで拡張可)、Labs ツールのルート(`omx_lab`)、プレビュー・カスタマイザー・フィード・robots。
- クエリ: `page`/`paged` を除き、スカラー値のみ RFC3986 で再エンコードして維持(配列パラメーターは捨てる)。
- 開発環境の実測(変更前 → 変更後):
| URL | 変更前 | 変更後 |
|---|---|---|
| `/page/2/`・`/page/999/` | 200(重複) | **301 → `/`** |
| `/?paged=3` | 301 → `/page/3/?paged=3`(→200) | **301 → `/`** |
| `/learn/page/2/`・`/about/page/7/` | 200(重複) | **301 → 各ページ** |
| `/labs/page/3/` | 200(canonical `/labs/`) | **301 → `/labs/`** |
| `/about/page/7/?utm_source=x&a[b]=1` | 200 | 301 → `/about/?utm_source=x` |
| `/` `/news/` `/news/page/2/`(正当) `/labs/json-formatter/` | 200 | 200(不変) |
| `/ai-tools/page/2/` `/prompts/page/2/` `/category/…/page/2/`(範囲外) | 404 | 404(不変) |
| `/news-1/2/` `/contact/2/` `/claude/page/2/` | 301(WordPress 標準) | 301(不変) |
| `/learn/industries/page/2/`(除外の確認、ハブ無効の開発環境) | — | 301 しない(テーマの既存処理のまま) |
- ループなし: 全リダイレクトは1回で 200 に到達(`curl -L`)。Firefox でも `/page/2/`→`/`、`/learn/page/2/`→`/learn/`、`/about/page/7/`→`/about/` を確認。
- canonical: 301 になるため重複URLの canonical は出力されない。正規URL側の canonical は不変(SEO 前後比較: 10URL で追加した h2 以外の差分なし)。
- **Slim SEO 有効環境(本番相当)での挙動・二重リダイレクトの有無は UNVERIFIED** → ステージング A。

## 3. D3 検索索引の情報公開リスク(**漏えいを確認 → P0 として修正・検証済み**)
- **再現(修正前)**: パスワード保護された Learn 配下ページ「LEAKTEST 合言葉Learn」が、**パスワードの Cookie を持つ閲覧者(管理者・匿名どちらでも)が索引を再構築したとき、公開キャッシュ(transient)に入った**(`tests/nav-ux/suggest-leak.php`: 4シナリオ中2シナリオで漏えい)。原因は `post_password_required()` が閲覧者の Cookie に依存すること。
- **修正(最小差分、`inc/search-suggest.php`)**: ①パスワード判定を投稿自身の `post_password` 欄で行う ②索引の構築を**匿名ユーザー(ID 0)の文脈で実行**し、終了後に元のユーザーへ戻す ③公開設定でないタクソノミーを除外(`is_taxonomy_viewable`)④キャッシュキーを `v2` に変更(修正前に作られた可能性のある索引を破棄)。
- **検証(修正後)**: 匿名 / 管理者 / 管理者+パスワードCookie / 匿名+パスワードCookie の4シナリオで、非公開・下書き・予約・パスワード付き(投稿/固定ページ/プロンプト)・非公開ツール・非公開記事のみのカテゴリーが**索引に 0 件**。HTTP で匿名・Cookie 付きの両方から取得しても 0 件。該当URLへの直接アクセスは 404。
- 会員限定コンテンツ: OmochiX には会員機能・会員制プラグインがない(コード確認)。将来導入する場合、会員限定の公開ステータス(publish だが閲覧制限)は索引から除外する必要がある(`05-FUTURE-ARCHITECTURE.md` の会員基盤分離方針)。
- キャッシュされた索引: transient(15分)は v2 キーで刷新。ブラウザ/CDN の `max-age=300` 分は旧応答が残り得るが、旧応答に含まれ得たのは「パスワード付き Learn ページのタイトルとURL」のみで、本番には該当ページがあるか未確認(UNVERIFIED。管理画面で読み取り確認を推奨)。

## 4. F1・D5・D6(**修正・検証済み**)
| ID | 修正 | 検証 |
|---|---|---|
| F1 | `inc/icons.php`: `site_icon_meta_tags` を空にするのは公開フロントのみ(`is_admin()`・`login_init` では WordPress 標準のタグを返す) | 開発環境でサイトアイコンを一時設定: フロントはテーマのタグ5本のみ(uploads のアイコン 0)、ログイン画面・管理画面は WordPress のサイトアイコンタグ 4本 → 設定を削除して元に戻した |
| D5 | `omochix_suggest_flush()`: `save_post` がリビジョン/自動保存のときは索引を破棄しない | `tests/nav-ux/suggest-flush.php`: 実更新で破棄・リビジョン保存/自動保存では保持 → PASS |
| D6 | 記事末の AIツール判定を「長い名称優先・一致した名称を本文から除去してから短い名称を判定」 | `tests/nav-ux/article-tools.php`: 「Claude Code × Sentry…」→ Claude Code のみ、「GPT・Claudeとの競争」+タグ Claude Code → Claude Code と Claude、該当なし → 空 など5件 PASS。境界例「Microsoft Copilot Studio」→ Microsoft Copilot は仕様どおり(製品ファミリー名) |

## 5. 変更ファイル(今回分)
- `wp-theme/omochix-theme/assets/js/search-suggest.js`(D1)
- `wp-theme/omochix-theme/inc/seo.php`(D2)
- `wp-theme/omochix-theme/inc/search-suggest.php`(D3・D4・D5・D6)
- `wp-theme/omochix-theme/inc/icons.php`(F1)
- テスト(新規、開発環境用): `tests/nav-ux/ime.cjs`・`ime-check.js`・`suggest-leak.php`・`suggest-flush.php`・`article-tools.php`。変更: `tests/nav-ux/firefox-suggest.cjs`・`perf2.cjs`
- 文書: 本書・`seo-p0fix-diff.json`・`firefox-p0-fix.json`・`perf-p0fix.json`
(ブランチ全体: 既存の変更 22 ファイル + 未追跡。前回までの成果物はそのまま)

## 6. テスト結果
| 区分 | 結果 |
|---|---|
| 既存 PHP/JS テスト | labs 285・editorial-home 26・industry-hub(theme-without-core)18・learn-practice 96・learn-gallery 134・learn-composer 46・article-v2 197・svg-motion shortcode 34・nav 16・ai-tools 3種・CSS 構文 — **すべて PASS**。`php -l` 全 OK |
| ナビ/UX QA(Chromium・WebKit) | 16/16 PASS(横スクロール・リンク・aria・キーボード・ファビコン・ローダー既定OFF・コンソールエラー 0) |
| 検索サジェスト(Chromium・WebKit・Firefox) | PASS(フォーカス前の索引通信 0・入力中の通信 0・XSS 注入なし・モバイル表示・ダイアログ内表示) |
| IME(D1) | Chromium・WebKit・Firefox PASS(合成イベント)。**実機は未検証** |
| 情報漏えい(D3) | 4シナリオ・HTTP で 0 件 PASS |
| ページネーション(D2) | 開発環境 PASS(表のとおり)。**Slim SEO 環境は未検証** |
| サイトマップ(S2) | フィルターのメモリ上シミュレーション PASS。**Slim SEO の実出力は未検証** |
| SEO 前後(10URL) | 追加 h2「次にできること」以外は同一(`seo-p0fix-diff.json`) |
| Industry Hub | `theme-without-core` 18 PASS、`/learn/industries/page/N/` は 301 対象外。ハブ有効時の動作は RC 環境で要確認 |
| ファビコン | F1 検証(上記)+ QA の重複なし・ファイル読込 PASS |
| 記事末リンク | D6 テスト PASS |
| 性能 | Chromium・390px・冷間・N=10(変更前 origin/main → 本ブランチ): 高速 / 228→160ms, /learn/ 64→64ms, /prompts/ 72→72ms / 低速4G / 3804→3824ms, /learn/ 2232→2264ms, /prompts/ 1748→1780ms(高速回線の差は測定ばらつきの範囲。低速4Gは +20〜32ms ≒ +1〜2%、前回測定と同水準で今回の修正による悪化なし)。CLS 全 0。Lighthouse は未実行(同等の指標を Playwright + CDP で計測) |
| セキュリティ回帰 | 追加出力はエスケープ済み/固定、リダイレクトは `wp_safe_redirect`(同一ホスト)・クエリは再エンコード、索引は匿名文脈で構築・公開データのみ |
| 管理画面メニュー割当時の Labs(F7) | 変化なし(割当時はヘッダーから消える。本番は未割当=前回実測) |

## 7. 残る No-Go 条件
1. **Industry Hub RC(テーマ 1.29.0 / Core 1.10.0)の本番反映と main 統合が未完了**(本変更はその後に統合)
2. テーマ Version の確定(1.29.0 より上。統合順序確定後)
3. QA 画像(約49MB)をコミットから除外する運用
4. ステージング(Slim SEO・LiteSpeed・本番相当データ)での A(ページネーション)・B(サイトマップ)・C(索引の負荷・キャッシュ・実データサイズ)・D(既存31URL ほか)
5. 実機: 日本語 IME(iOS/Android/macOS/Windows)・Learn 実践ページの下部固定バーとフッター・Safe Area・スクリーンリーダー
6. 本番の読み取り確認: `primary` メニュー未割当・サイトアイコン設定・パスワード保護ページの有無
7. デプロイ後のキャッシュ削除(承認済み手順)

## 8. Industry Hub RC 完了後の統合手順
1. RC(`rc/industry-hub-on-theme-1.28.1`)を承認済みの手順で本番反映 → main に統合(main = 本番 1.29.0 相当)。
2. 本作業を未コミットのまま退避せず、担当者がコミット方針を決定(QA 画像を除外、`docs/release-readiness` 等は必要分のみ)。
3. 更新後の main に本ブランチを載せる(rebase/merge は担当者。事前の 3-way 確認では RC との競合 0、重なりは `style.css`・`page-learn.php`)。
4. Version を決定(例 1.30.0)し、RC と同じリリースツールで「本番 1.29.0 基準」の差分範囲・SHA-256 マニフェストを作成。
5. 統合後に全テスト・`tests/nav-ux/*`(QA・IME・漏えい・D5・D6・SEO 前後)を再実行 → ステージング → 承認 → 本番 → キャッシュ削除。

## 9. ステージング検証項目
`STAGING-PLAN.md` の A〜D に加えて:
- A: `/?paged=N`・`/labs/page/N/`・`/learn/industries/page/2/`(ハブ有効時に 200 のまま)・UTM 付き URL の 301 先
- C: `v2` キーでの索引構築、パスワード保護ページを1件用意し Cookie 有無で索引が変わらないこと、リビジョン保存で再構築されないこと
- D: サイトアイコンを一時設定してフロント/管理画面/ログイン画面のアイコン(F1)

## 10. 最終 Go / No-Go
**No-Go(継続)**。今回の P0(D1・D2・D3)と F1・D5・D6 はローカルで修正・検証済みで、**コード品質上の既知の P0 はなし**。残る理由はリリース手順(RC 先行・Version・画像除外)とステージング/実機での未検証項目のみ。本番反映には別途承認が必要です。
