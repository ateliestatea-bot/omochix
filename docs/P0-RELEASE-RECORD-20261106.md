# P0 リリース記録：テーマ 1.27.1・OmochiX Core 1.9.3（2026-11-06）

AI Tools の P0 品質改善（料金の表示・記事更新日・編集部評価の一時非表示）を本番に反映した記録。DB は変えていない（9 ファイルだけ）。

## 1. 結果
| 項目 | 内容 | 根拠 |
|---|---|---|
| リリース日 | 2026-11-06 JST（本番のファイルの更新時刻 00:43:39 JST） | 運営者の報告、公開の `style.css`・Core の `readme.txt` の Last-Modified |
| テーマ | 1.27.0 → **1.27.1** | 運営者の報告。公開の `style.css` がリリースのファイルと一致（SHA-256、2026-11-06 に確認） |
| OmochiX Core | 1.9.2 → **1.9.3** | 運営者の報告。公開の `readme.txt` がリリースのファイルと一致（同上） |
| WordPress / PHP CLI / Slim SEO | 7.1.4 / 8.4 系 / 4.9.11 | 運営者の報告 |
| 置き換え | 9/9 PASS、置き換えの後の SHA-256 9/9 PASS（置き換えの道具 v2、承認済み SSH 接続・一時名→照合→同じフォルダーでの `rename()`） | 運営者の報告 |
| LiteSpeed Cache | 全削除済み | 運営者の報告 |
| AI Tools 52 ページ・料金の絞り込み 5 種 | PASS | 運営者の報告。**2026-11-06 に `p0_verify.py` を公開 GET で再実行：52 ページ・絞り込み 5 種、失敗 0、テーマ 1.27.1** |
| Motion Gallery 53 件＋Compose | PASS | 運営者の報告。**`p0_verify_motion.py` を公開 GET で再実行：一覧（53件の見本）・Compose・53 件、失敗 0** |
| P5b の先行 22 件 | 分類・タグは反映後の状態のまま | **REST で確認：22/22。AI ツール 52 件の分類・タグ・更新日時・meta は 2026-11-05 21:51 の基準と同じ**（P0 は DB を変えない） |
| PC・スマホの目視 | PASS | 運営者の報告 |
| Google リッチリザルト | HeyGen・Leonardo AI とも有効。`aggregateRating`／`offers` の任意の警告は P0 の仕様（評価の非表示・料金の非掲載）で許容 | 運営者の報告 |
| 未公開の機能の混入 | なし：Industry Hub（`main` の PR #57）・テーマ 1.28.0（PR #56、下書き）は含まない | リリースの 9 ファイルに Industry Hub のファイル・参照が無いことを確認 |

## 2. 変更の内容（本番 1.27.0・1.9.2 との差分、9 ファイル）
| ファイル | 変更 |
|---|---|
| `wp-plugin/omochix-core/includes/tool-display-facts.php`（新規） | 料金の分類（`pricing_type` と `has_free_plan` が食い違えば「要確認」）、評価の公開の切り替え（既定は非表示）、記事更新日（内容の変わった最新のリビジョン） |
| `wp-plugin/omochix-core/includes/software-application-schema.php` | Review は評価の公開のときだけ、`dateModified`（SoftwareApplication・Slim SEO の WebPage）と OGP の `article:modified_time`・`og:updated_time` を記事更新日に |
| `wp-plugin/omochix-core/omochix-core.php`・`readme.txt` | 新しいファイルの読み込み（あるときだけ）、1.9.3 |
| `wp-theme/omochix-theme/inc/ai-tool-detail.php`・`single-ai_tool.php`・`archive-ai_tool.php`・`search.php` | 料金・無料プラン・評価（「準備中」、評価の欄・星・評価順を出さない）・「記事更新日」の表示。新しい関数の呼び出しはすべて存在の確認つき（ファイルを 1 つずつ置いても壊れない） |
| `wp-theme/omochix-theme/style.css` | Version 1.27.1（この 1 行だけ） |
- 本番に置いたファイルは、テーマ 1.27.0・Core 1.9.2（GitHub の `94152a7`）に上の 9 ファイルの変更を加えたもの。本番のリリースの正確な記録（コミット）は運営者の手元に非公開で保持し、GitHub の `main` には本番に置いたファイルの内容を新しいコミットとして取り込んだ（`main` の Industry Hub と合わせるため、Core の版は `1.10.0-dev`）。
- ほかに `tests/ai-tools-display-facts.php`（新規、57 件）・`.github/workflows/tests.yml`（試験の追加）。

本番に置いた 9 ファイルの SHA-256（反映の後に本番で照合した値）と、`main` に取り込んだファイルとの関係：

| ファイル（`wp-content/` から） | 本番の SHA-256 | `main` のファイル |
|---|---|---|
| `plugins/omochix-core/includes/tool-display-facts.php` | `e1c4ca17d4217a2f…` | main のファイルは本番とバイト単位で同一 |
| `plugins/omochix-core/includes/software-application-schema.php` | `b5dc90c056ac0151…` | main のファイルは本番とバイト単位で同一 |
| `plugins/omochix-core/omochix-core.php` | `65bd0bfa4c70e801…` | main のファイルは本番と異なる：Industry Hub の読み込み 1 行と版 `1.10.0-dev`（本番は 1.9.3） |
| `plugins/omochix-core/readme.txt` | `73ba8b51c09d258a…` | main のファイルは本番とバイト単位で同一 |
| `themes/omochix-theme/inc/ai-tool-detail.php` | `fae81af70f77fefc…` | main のファイルは本番とバイト単位で同一 |
| `themes/omochix-theme/single-ai_tool.php` | `b5075b7b3c1100c3…` | main のファイルは本番とバイト単位で同一 |
| `themes/omochix-theme/archive-ai_tool.php` | `155c586cf9253263…` | main のファイルは本番とバイト単位で同一 |
| `themes/omochix-theme/search.php` | `f5b4ee4ab4cc7a73…` | main のファイルは本番とバイト単位で同一 |
| `themes/omochix-theme/style.css` | `324851f6199a32dc…` | main のファイルは本番とバイト単位で同一 |

## 3. 実施した手順（要約。具体的な接続先・保存先・コマンドは非公開の手順書に保持）
1. 事前：パッケージの SHA-256、本番の公開の状態の確認（公開 GET の事前の確認）、サーバー上の読み取りだけの事前の確認（版・8 ファイルのハッシュ・新規ファイルが無い・シンボリックリンク・権限・所有者・ファイルシステム・空き容量）。
2. 本番サーバーの非公開ステージング領域（web の外）に 9 ファイルを送り、サーバー上で SHA-256 を照合。
3. バックアップ：本番サーバーの非公開バックアップ領域（置き換える前の 8 ファイル、600、元の mode の記録、操作の記録）→ 運営者のローカルバックアップ領域に写して照合。
4. 反映（確認の文字列と WordPress の版が必須）：Core → テーマの順に、各ファイルを同じフォルダーの一時名に置き、照合し、元の mode を付け、名前の変更で置き換え。`verify --want release`。
5. LiteSpeed Cache の全削除、公開 GET の確認、目視、リッチリザルト。

## 4. バックアップ・保存先
| 場所 | 内容 | 扱い |
|---|---|---|
| 本番サーバーの非公開バックアップ領域 | 置き換える前の 8 ファイル（600）、元の mode の記録、操作の記録 | **消さない**（残す期間は運営者が決める） |
| 本番サーバーの非公開ステージング領域 | 置いた 9 ファイル（web の外） | **消さない**（同上） |
| 運営者のローカルバックアップ領域 | サーバーのバックアップの写し | 保管（クラウド同期の外） |
| 運営者のローカルのリリースパッケージ | 反映した 9 ファイル・戻し用の 8 ファイル・SHA-256 の一覧・置き換えの道具 v2（SHA-256 `6b103969…`）・非公開の手順書 | 保管 |

## 5. 復旧の方法（必要になった場合。承認の後）
- 非公開の手順書のロールバックの章のとおり、承認済み SSH 接続で置き換えの道具 v2 の `rollback`（確認の文字列と WordPress の版が必須）→ `verify`（反映前の状態）→ LiteSpeed Cache の全削除 → 公開 GET の確認。
- `rollback` は、バックアップの 8 ファイルと 9 ファイルの状態を、何かを変える前にすべて確かめ、違えば何も書かずに止まる。WordPress の版が変わっていれば、その版を指定する。DB の復元は不要。
- 具体的な接続先・保存先・コマンドは非公開の手順書に保持（公開のリポジトリには載せない）。

## 6. 記録しておく事実
- **`p0_verify.py` の修正**：テーマの版の判定が古い 1.26.1 のままだったため、ローカルの検証用に 1.27.1 へ修正した（2026-11-06 00:55、2 行：`style.css?ver=1.27.1` の判定とその表示の文）。元の版と比べて、ほかの検証の条件は同一であることを確認した。説明の文（docstring）の版も 1.27.1 に直した（判定には影響なし）。修正後、QA の反映後の取得で PASS・反映前の取得で FAIL（見分けられる）、本番の公開 GET で PASS。
- 評価は全 52 件で非表示（`omochix_core_tool_ratings_published()` が false）。`rating_overall` のデータは残っている。
- 27 件が料金「要確認」（`pricing_type` と `has_free_plan` の食い違い）。DB の値は変えていない。

## 7. 残っていること
1. **Git**：P0 の内容を `main` に取り込む（`main` の Core は Industry Hub を含むので `1.10.0-dev`、次の Core のリリースは **1.10.0**）。テーマ 1.28.0（PR #56）は 1.27.1 の上で作り直し、版は 1.28.0 を残す。
2. サーバーの置き場・バックアップを残す期間の決定（消すのは運営者の判断）。
3. 本番の opcache の再読み込みの間隔の設定は未確認（今回は反映の後の確認で問題なし）。
4. iCloud 上の機密データ（本番の DB のバックアップなど）の移行（`docs/` 外の計画 `ICLOUD-SENSITIVE-DATA-PLAN.md`、承認制）。
5. P5b-3・P5b-4・P5b-6b（HOLD/BLOCKED）、P5c（35 件）は未実施のまま。
6. 次の段階：`docs/AI-Tools-Quality-Next-Phase.md`。
