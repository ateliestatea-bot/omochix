# OmochiX Labs — L1(サイトマップ運用ルール)・L2(料金表の行単位承認)実装結果

作成日: 2026-11-05
ブランチ: `feature/labs-c1-mvp`(基準 `682cf44`)。**未コミット**。
変更していないもの: 本番、DB、Secrets、SVG Motion、他の worktree、全体の `review`(`pending` のまま)

## 1. L1:サイトマップ運用ルール

- **手順書の改訂**(`docs/Beta-Deployment-Runbook.md`):
  - §17:サイトマップの正規の生成元は Slim SEO とする。WordPress の管理外にある仮想 URL に限り、補助サイトマップを許可する(現在は `/labs-sitemap.xml` だけ)。掲載・除外の条件、応答の形式、入口の固定ページが非公開なら 404 であることを明記した。
  - §18:robots.txt に Slim SEO と補助サイトマップの `Sitemap:` 行が各 1 行あることを、確認項目に加えた。
- **チェックリスト**(`docs/Beta-Release-Checklist.md`):「Sitemap送信」と「robots本番確認」に補助サイトマップを追記した。
- **Search Console の手順**:`docs/labs/LABS-SEARCH-CONSOLE.md` を新規作成した(公開当日・1 週間後・4 週間後・やめる場合)。
- **コード**:同じパスに実在する固定ページ(`labs/{slug}`)があるツールは、補助サイトマップから外す。そのページは Slim SEO の固定ページ用サイトマップに載るので、重複を防ぐため。テストも追加した。

## 2. L2:料金表の行単位承認

### 2.1 設計

| 項目 | 内容 |
| --- | --- |
| データの置き場所 | `assets/data/labs/pricing.json`(**公開 URL から誰でもダウンロードできた**)を、`inc/labs-data/pricing.json.php` に移した。1 行目が `<?php exit; … ?>` で、Web から要求されても何も出力しない(テストで出力 0 バイトを確認)。JSON はその下で、そのまま編集できる。さらに `inc/labs-data/.htaccess` で、このフォルダへの直接のアクセスを拒否する(PHP が実行されない設定でも配信されないようにするため。FTP で配置するときは、隠しファイルも転送する設定にする) |
| スキーマ | `schema_version: 2`。各行に `caveats`(計算の前提のコード)と `approval`(`status`: pending / approved / held、`reviewer`、`reviewed_at`、`acknowledged`、held なら `reason`)を持たせる。前提のコードと表示文は `caveat_labels` に置く |
| 行が承認済みになる条件 | `status = approved`、`reviewer` がある、`reviewed_at` が日付で、かつ**価格の確認日(`verified_at`)以降**、行のすべての `caveats` を `acknowledged` に含む。1 つでも欠ければ pending として扱う |
| 本番での表示(全体のゲート + 行のゲート) | 全体の `review` が approved でなければ 1 行も出さない(従来どおり)。approved なら、承認済みの行だけを出す。pending と held の行は、HTML にも埋め込みの設定 JSON にも出さない(件数だけを `hidden` に入れる) |
| 本番以外 | 有効な行をすべて表示し、「承認待ち」「保留」の印を付ける(レビュー用) |
| 90 日ルール | 従来どおり。承認済みの行でも、確認日から 90 日を過ぎると非表示になる(テストで確認) |
| PHP と JS の一致 | `approvalState` / `gate` / `fileApproved` を `cost.js` に実装し、PHP と JS の照合ケースを 13 にした(行の承認・ゲートを含む) |
| リリースゲート(`node tests/labs/data.js --release`) | 全体が approved、pending の行が 0、approved の行が 1 行以上、approved の行はすべて条件を満たす、held の行は理由を持つ |

### 2.2 変更ファイル

| ファイル | 変更 |
| --- | --- |
| `wp-theme/omochix-theme/inc/labs.php` | 料金データの読み込み(`omochix_labs_pricing_file`、`omochix_labs_read_guarded_json`)、`omochix_labs_row_approval_state`、`caveats` と `approval` の検証、`omochix_labs_pricing_gate`。サイトマップの重複防止 |
| `wp-theme/omochix-theme/inc/labs-data/pricing.json.php` | 旧 `assets/data/labs/pricing.json` から移動(`git mv`)し、スキーマ 2 にした(値・確認日・出典・適用日は変更なし) |
| `wp-theme/omochix-theme/assets/js/labs/core/cost.js` | `approvalState`、`gate`、`fileApproved`。スキーマ 2 の検証 |
| `wp-theme/omochix-theme/assets/js/labs/ui/token-cost-calculator.js` | 「承認待ち」「保留」の印(本番以外)、結果の行に計算の前提を表示、注記 |
| `wp-theme/omochix-theme/template-parts/labs/content/token-cost-calculator.php` | モデルの一覧に状態の印、価格表に「計算の前提」の列、掲載条件に承認とモデルごとの前提を追記 |
| `wp-theme/omochix-theme/assets/css/labs.css` | 状態の印と前提の表示 |
| `docs/Beta-Deployment-Runbook.md`、`docs/Beta-Release-Checklist.md`、`docs/labs/LABS-SEARCH-CONSOLE.md` | L1 |
| `.github/workflows/labs.yml` | 構文チェックに `inc/labs-data/*.php` を追加 |
| `tests/labs/run.php`、`core.js`、`data.js`、`fixture.php`、`browser/run.js` | テストの追加と更新 |

## 3. モデルごとの承認状態(初期値。承認者だけが変更する)

| 行 | モデル | `approval.status` | 計算の前提(承認時に `acknowledged` に入れるもの) |
| --- | --- | --- | --- |
| 1 | Claude Opus 5.5 | pending(承認候補) | `cache_write_excluded`、`thinking_as_output_unstated` |
| 2 | Claude Sonnet 5.5 | pending(承認候補) | 同上 |
| 3 | Claude Haiku 5.5 | pending(承認候補) | 同上 + `prompt_length_includes_cache` |
| 4〜7 | gpt-6-astra / 6.1-sol / 6.1-terra / 6-luna | **held**(モデル ID と、272K 超の課金方法を公式に確認できないため) | `api_id_unconfirmed`、`long_context_whole_request_assumed`、`thinking_as_output_unstated` |
| 8 | gpt-5.4-mini | **held**(モデル ID を確認できないため) | `api_id_unconfirmed`、`thinking_as_output_unstated` |
| 9〜10 | Gemini 4 Argon(現行 / 2026-12-01 から) | pending(条件付き承認候補) | `modality_unlabeled_as_text`、`thinking_included_in_output`、`implicit_cache_only` |
| 11〜12 | Gemini 3.8 Flash(現行 / 2027-01-01 から) | pending(条件付き承認候補) | `modality_unlabeled_as_text`、`thinking_included_in_output`、`cache_storage_excluded`、`free_tier_excluded` |
| 13 | Gemini 3.5 Flash-Lite | pending(条件付き承認候補) | `cache_storage_excluded`、`free_tier_excluded` |

全体の `review.status` は **pending**(変更していない)。このため本番では、料金計算の価格は 1 行も出ない。

### 承認者の記入例(行 1 を承認する場合)

```json
"approval": {
  "status": "approved",
  "reviewer": "<氏名>",
  "reviewed_at": "2026-11-XX",
  "acknowledged": ["cache_write_excluded", "thinking_as_output_unstated"]
}
```

あわせて、全体の `review` を `"status": "approved"` にし、`reviewer` と `reviewed_at` を記入する。そのうえで `node tests/labs/data.js --release` が PASS することを確認する。held の行は held のままで良い(リリースゲートは held を「判断済み」として扱う)。

## 4. テスト結果

| テスト | 結果 |
| --- | --- |
| `php tests/labs/run.php` | 247 件 PASS(L1 で 2 件、L2 で 23 件を追加。不正なデータは非公開側に倒す検査 6 件を含む) |
| `node tests/labs/core.js` | 121 件 PASS(approvalState、gate、fileApproved、caveats と approval の検証) |
| `node tests/labs/data.js` | 37 件 PASS(PHP と JS の照合 13 ケース、データの形式、初期状態、公開ファイルがないこと) |
| `node tests/labs/data.js --release` | **想定どおり FAIL**:全体の review が未承認、pending の行が 8、承認済みの行が 0 |
| `tests/labs/browser/run.js`(Chromium) | 100 件 PASS(状態の印、計算の前提、axe、CSP、外部通信、GA4) |
| 既存テーマのテスト(11 本) | §5 を参照 |

**漏えいの検査**(`run.php`):

- 本番を想定し、全体と Anthropic 3 行だけを承認した状態を一時的なテーマで再現した。OpenAI と Google のモデル ID・表示名・出典の URL が、HTML にも埋め込みの設定 JSON にも 1 つもないことを確認した。
- 同じ値が、ステージングでは検出されることも確かめた(検査が正しく効いている証拠)。
- テーマの `assets/` 配下のどのファイルにも、価格のあるモデルの ID が含まれないことを確認した。
- 料金データのファイルを実行したときの出力が 0 バイトであることを確認した。

## 5. 既存機能の回帰

既存テスト 11 本はすべて PASS:ai-tools-csv-update、ai-tools-inline、learn-practice 96、learn-gallery 134(Motion 53 件)、learn-composer 46、article-v2 197、editorial-home 26、core-parity 78、params-parity 4,664(53 見本)、menu-contrast、ai-tools-quick-edit 33。リポジトリ内のすべての PHP の構文チェックと、Labs の JS の構文チェックも問題なし。テーマのバージョンは 1.27.0 のまま。
