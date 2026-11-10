# OmochiX Labs — CSP の観察の手順(Report-Only)

対象:Labs のページだけに付ける `Content-Security-Policy-Report-Only`(`inc/labs.php` の `omochix_labs_security_headers()`)
目的:公開から 2 週間、Labs のページで想定外の読み込み(違反)が起きないことを確かめ、C2 で強制(`Content-Security-Policy`)に切り替える判断の材料にする。

## 1. 前提(できること・できないこと)

| 項目 | 内容 |
| --- | --- |
| 違反の送り先 | **ない**(`report-uri` / `report-to` を付けていない。受け口の用意と個人情報の扱いの検討が必要なため、C2 で検討する) |
| 一般の訪問者の違反 | **収集できない**。訪問者のブラウザで起きた違反は、運営者に届かない |
| 観察できる範囲 | 運営者が自分のブラウザの開発者ツールで見た範囲だけ |
| Chrome | 違反を評価し、コンソールに `… violates the following Content Security Policy directive … The policy is report-only …` を出す。**観察に使う** |
| Firefox | 違反を評価し、コンソールに `Content-Security-Policy: (Report-Only …)` で始まる行を出す(表示は日本語になることがある)。**観察に使う** |
| Safari | 送り先のない Report-Only のポリシーについて「the policy will have no effect」と警告する。版によっては違反を出すこともあるが、ブラウザ自身が「効果がない」と述べているので、**観察の手段として扱わない** |

Safari のコンソールに次の 1 行(ページごと)が出るのは**想定どおり**で、対応は不要:

> The Content Security Policy '…' was delivered in report-only mode, but does not specify a 'report-to'; the policy will have no effect. …

1.28.0 からは、Report-Only のポリシーに `frame-ancestors` を含めない。そのため、Safari の「frame-ancestors is ignored when delivered in a report-only policy」の警告は出なくなる。この警告が出た場合は、想定外の変更なので確かめる。

## 2. 公開後 2 週間の観察の計画

| 時期 | 担当 | 内容 |
| --- | --- | --- |
| 公開当日 | 運営者 | Chrome と Firefox で 5 ページ(`/labs/` と 4 ツール)を PC の幅とスマホの幅(開発者ツールのレスポンシブ表示、375px)で開く。各ツールで「入力例」→ 実行 → コピー → リセットを 1 回ずつ行い、コンソールを確かめる(§3) |
| 1 週間後 | 運営者 | 同じことを Chrome と Firefox で行う。プラグインやテーマの更新があった週は必ず行う |
| 2 週間後 | 運営者 + Claude Code | 同じ確認をし、記録(§4)をまとめて、C2 で強制に切り替えるかを判断する |
| 随時 | Claude Code(ローカル) | テーマ・プラグイン・WordPress を更新したら、QA サイトで `tests/labs/browser/run.js`(Chromium・WebKit)と `tests/labs/browser/firefox.js`(Firefox)を実行する。どちらもコンソールの方針(`console-policy.js`)で違反と未知の CSP の警告を失敗にする |

**「2 週間で違反 0 件」は「運営者が見た範囲で 0 件」という意味**であり、すべての訪問者の環境で違反がなかったことの証明にはならない。強制に切り替えた直後に問題が出た場合は、すぐに Report-Only に戻せるようにする(§5)。

## 3. コンソールの見方

| 表示 | 判断 |
| --- | --- |
| Chrome:`… violates the following Content Security Policy directive: "xxx-src …". The policy is report-only …` | **違反**。読み込まれた URL と指示(`script-src` など)を記録する |
| Firefox:`Content-Security-Policy: (Report-Only …)` で始まる行 | **違反**。同上 |
| 送り先(report-to)がない、という Safari の警告 | 想定どおり(§1) |
| それ以外の CSP の警告 | 想定外。記録して Claude Code に共有する |

違反を見つけたら、次を記録する:日時、ブラウザ、ページ、指示、読み込まれた URL、直前の操作、プラグインかどうか。

## 4. 記録の様式

| 日付 | ブラウザ | ページ | 結果 | 違反の内容 | 対応 |
| --- | --- | --- | --- | --- | --- |
| 2026-xx-xx | Chrome 1xx | /labs/json-formatter/ | 違反 0 | — | — |

## 5. C2(強制への切り替え)の設計メモ

- ヘッダーの名前を `Content-Security-Policy` に変える。ただし、既存の強制の CSP がある場合は付けない(現在の動作を維持する)。
- **クリックジャッキングの対策として、`frame-ancestors 'self'` を強制のポリシーに戻す**。Report-Only では効かないので、1.28.0 では外している。
  - 古いブラウザのために、`X-Frame-Options: SAMEORIGIN` の併用も検討する。
  - WordPress のカスタマイザーなど、同じオリジンの中での埋め込みは `'self'` で許可される。
- 観察で見つかった違反のうち、正当なもの(プラグイン・Site Kit など)だけを許可リストに足す。許可リストは `tests/labs/run.php` で全文を固定しているので、変えるときはテストも直す。
- 違反の送り先(`report-to`)を付けるかを決める。付ける場合は、受け口(WordPress の REST API など)、回数の制限、保存する情報(URL のクエリや入力を保存しない)を設計する。外部の報告サービスは、訪問者の閲覧の情報を外に送るので使わない。
- 切り戻し:問題があれば、ヘッダーの名前を `Content-Security-Policy-Report-Only` に戻すだけで、元の観察の状態に戻せる。
