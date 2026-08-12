# CursorがiPadに対応。外出先からAI開発とPRレビューはどこまでできる？

## 公開管理情報

| 項目 | 内容 |
|---|---|
| SEO title | CursorがiPad対応｜外出先からCloud Agentで開発する方法 |
| Slug | `cursor-ipad-mobile-agent-guide` |
| Article type | ニュース＋解説 |
| Category | AIニュース（slug: `ai-news`） |
| Tags | Cursor / AI開発 / Cloud Agent / iPad / GitHub |
| Author | OmochiX編集部 |
| Published date |  |
| Updated date |  |
| Last fact-check date | 2026-08-12 |
| AI assistance | あり（一次情報の調査補助、構成、下書き作成、校正） |
| Human review status | 公開前確認待ち |
| PR / Sponsored status | No |
| Eyecatch status | 未制作 |
| Eyecatch candidate | CursorがiPadに対応 / 外出先でもAI開発？ |
| Primary sources | Cursor Changelog / Cursor Docs / Pricing（本文末尾） |
| Internal link candidates | `claude-sonnet-5-guide` / `gpt-5-6-guide` |

## Meta Description

CursorのiPad対応を解説。iPhone・iPadからCloud Agentへ開発を依頼し、差分やPRを確認・レビュー・マージする流れ、PCが必要になる場面、対象プランと料金を紹介します。

## 抜粋

Cursorは2026年7月29日、iPad向けLayoutを発表しました。iPhone・iPadからCloud Agentへ依頼し、DiffやPRを確認してDesktopへ引き継ぐ流れを解説します。

## 事実確認サマリー

| 確認項目 | 2026年8月12日時点のCursor公式情報 |
|---|---|
| 正式機能名 | Cursor for iPad / Cursor Mobile App for iOS |
| 発表日 | iOS公開ベータ：2026年6月29日、iPad対応：2026年7月29日 |
| 提供開始日 | 各発表日から利用可能と案内 |
| iPad | 公式iPadレイアウトをすべての有料プランへ提供 |
| iPhone | iOSアプリをすべての有料プランへ公開ベータ提供 |
| Web | `cursor.com/agents`をDesktop・Tablet・Mobileブラウザーから利用可能。PWAにも対応 |
| 外部端末からのAgent操作 | Cloud Agentの開始・管理、追加指示、Remote Controlに対応 |
| GitHubとの関係 | GitHubアカウントを接続してRepositoryへアクセス。Cloud AgentはPRを生成可能 |
| PR | Mobileでデモ、ログ、差分を確認。iPhone / iPadでPRのコメント、Check、Approval、Reviewer変更、作成・レビュー・マージに対応 |
| ローカルPC | Cloud AgentはPC不要。ローカルAgentのRemote ControlではPCを起動・接続可能な状態に保つ必要あり |
| Cloud / Background Agent | 独立VMのCloud Agentが実装・テスト・検証。Web / Mobile AgentはBackground Agentと同じ料金体系 |
| 対象プラン | Pro、Pro+、Ultra、Teams、Enterprise。Startはインド限定PlanとしてDocsに記載 |
| 料金 | Individual Proは月額20ドルから、Teams Standardは1ユーザー月額40ドル。利用量・税・上位プランは別条件 |
| 利用制限 | プラン内のAgent使用量と選択モデルの消費量に依存。具体的な残量はDashboardで確認 |
| 日本 | 公式Docsは日本語表示に対応。日本のApp Storeにおける実配信は公開直前に要確認 |
| 動作環境 | iPhoneはiOS 26.0以上、iPadはiPadOS 26.0以上。アプリはBeta、英語UI。Androidは計画中 |

---

Cursorは2026年7月29日、AIコーディング環境「Cursor」のiPad対応を発表しました。iPhone向けアプリは同年6月29日から公開ベータとして提供されており、今回の更新でiPadの広い画面に合わせた専用レイアウトと、iPhone・iPad共通のPRレビュー機能が追加されています。

注目点は、iPadでPC版のコードエディターをそのまま再現したことではありません。外出先からCloud Agentへ作業を依頼し、進捗や成果物を確認し、Pull Requestをレビューするという、Agent中心の開発フローをMobileへ広げたことです。

では「外出先からAIへ開発指示を出し、帰宅後にPCで最終確認する」という使い方は、公式仕様でどこまで可能なのでしょうか。

## CursorのiPad対応とは？

正式な更新名は「Cursor, now on iPad」です。Cursor for iPadは2026年7月29日から、すべての有料プランで利用できると案内されています。

iPad版は画面の広さを生かし、複数のAgentチャットをサイドバーへ固定できます。Split Viewではチャットとレビューを並べ、ファイル差分を確認できます。スクリーンショットの特定位置へコメントしたり、Apple Pencilで書き込んだりする機能も紹介されています。

iPhoneとiPadでは、進行中の作業、確認が必要な項目、レビュー中のPRをまとめるInboxも追加されました。1つのチャットから複数PRが作られた場合に、すべてのPRを開く機能にも対応しています。

## iPad・iPhone・Webから何ができる？

iPhoneとiPadのCursorアプリでは、Repositoryを選び、Cloud Agentへタスクを依頼できます。音声入力、モデル選択、Slash Commandにも対応します。

Agentの完了や追加入力、レビュー可能な状態は、Live ActivitiesやPush通知で確認できます。

デモ、スクリーンショット、ログ、コード差分をMobileから確認でき、レビュー画面では次の操作が可能です。

- PRのコメント、Check、Approvalを確認する
- Reviewerを追加・変更する
- Agentへコメント対応を依頼する
- PRを作成、レビュー、マージする
- 複数PRを持つSessionを確認する

Webブラウザーからは`cursor.com/agents`へアクセスし、GitHubアカウントを接続してAgentを開始できます。Desktop、Tablet、Mobileに対応し、iOSやAndroidではPWAとしてホーム画面へ追加する方法も公式Docsで案内されています。

ただし、2026年7月29日に発表されたネイティブアプリのiPad対応はiOS向けです。AndroidはWeb / PWAでの利用が公式案内の中心で、同等のネイティブAndroidアプリについては確認できませんでした。

## AIエージェントはどこで動く？

Mobileから新しく開始するCloud Agentは、利用者のiPadやiPhone上でコードをビルドするのではありません。Cursorが用意する独立した仮想マシン上で、開発環境を構築して動作します。

Cloud AgentはRepositoryへ接続し、コード変更、テスト、動作確認を行い、スクリーンショット、動画、ログなどを生成できます。レビュー可能なPRを作る流れも案内されています。

Cloud Agentの実行中はLaptopを起動しておく必要がなく、ローカルSessionをCloudへ移して処理を継続することもできます。

一方、Remote Controlは別の仕組みです。PC上で動いているローカルAgentをiPhoneから継続操作するため、PCが到達可能な状態である必要があります。CursorにはPCを起動状態に保つ設定がありますが、Cloud AgentのようにPCから独立して動くわけではありません。

TeamsとEnterpriseでRemote Controlを利用する場合、管理者がCursor Dashboardから機能を有効にする必要があります。

## 外出先からAI開発する流れ

Cursor公式仕様から、次の流れは外出先でも実行できます。

1. **Repositoryを選び、Agentへ作業を依頼する**：iPhone、iPad、またはWebからRepositoryを選択し、実装や不具合修正を指示します。GitHub RepositoryをWebから使う場合は、CursorとGitHubアカウントの接続が必要です。
2. **Cloud Agentがコードを変更・検証する**：Agentは独立VMで開発環境を使い、コード変更、テスト、動作確認を進めます。Laptopは閉じていても構いません。
3. **成果物と差分を確認する**：Mobileからデモ、スクリーンショット、ログ、ファイル差分を確認し、必要なら追加指示を送ります。
4. **PRをレビューする**：コメント、Check、Approvalを読み、Reviewerを変更したり、Agentへコメント対応を依頼したりできます。
5. **PRをマージする、またはDesktopへ引き継ぐ**：公式仕様ではMobileからマージできます。より詳しい確認や手作業が必要なら「Open in Cursor」などでDesktopへ引き継げます。

つまり、「外出先でAgentへ指示し、帰宅後にPCで確認する」流れは公式仕様で可能です。内容が十分に確認できる小さな変更なら、外出先でPRのレビューやマージまで進める機能も提供されています。

ただし、本番へ影響する変更、複雑なUI、専用Hardwareが必要なテストなどは、Desktopや実機での確認が必要です。

## PC版Cursorとの違い

Mobile版の中心はAgentの起動、進捗管理、成果物確認、PRレビューです。PC版は、コードの直接編集やTerminal、Debugger、ローカル環境での確認に向いています。

公式Docsでも、Web / Mobile AgentはDesktop Workflowと連携する設計とされ、Agentの作業を「Open in Cursor」でIDEへ引き継げます。

次のような工程ではPCが必要、またはPCでの確認が安全です。

- コードを行単位で細かく編集する
- ローカル固有の環境やSecretを使って動作確認する
- Debuggerや開発者ツールで問題を追跡する
- 複数画面・複数端末でUIを目視確認する
- 本番デプロイ前の最終テストを行う
- Cloud環境では再現できないHardware連携を検証する

Remote ControlでローカルAgentを操作する場合も、PC自体は必要です。Laptopを閉じたまま独立実行したい場合はCloud Agentを使います。

## どんな人に向いている？

CursorのiPad・iPhone対応は、移動中や会議の合間にもAgentへ作業を委任したい開発者に向いています。

- 外出前に整理できなかった小さな修正をAgentへ依頼したい人
- 移動中に複数Agentの進捗を確認したい人
- PRのコメント対応をAgentへ任せ、レビューを続けたいチーム
- Laptopを閉じても長い処理を継続したい人
- スクリーンショットへ指示を書き込み、UI修正を伝えたいiPad利用者

一方、Mobileだけで常に開発を完結したい人には注意が必要です。CursorのMobile体験はフル機能のDesktop IDEを置き換えるというより、Cloud Agentの指揮とレビューを持ち歩く設計です。

## ChatGPT・Codex・Claudeとはどう使い分ける？

> **OmochiX編集部の見解**
>
> Cursor Mobileの特徴は、特定のAIモデルそのものより、Repository、Cloud開発環境、成果物、PRレビュー、Desktopへの引き継ぎを一つの流れにまとめている点です。

Cursorでは複数モデルを選べるため、ChatGPT、Codex、Claudeとはモデル性能だけでなく、接続先、実行環境、差分確認、承認方法まで含めて比較する必要があります。

外出先ではCursor MobileでCloud Agentを管理し、設計相談や調査では別のAIを使うなど、役割を分ける方法もあります。重要なのは、モデル名ではなく、作業の種類、必要なTool、費用、Security、最終確認の方法を基準に選ぶことです。

Cursor公式情報だけでは、他社製品との包括的な性能優劣は確認できません。本稿では、Cursorが常にChatGPT、Codex、Claudeより優れているとは評価しません。

## 対象プランと料金

Cursor for iOSとiPadは、すべての有料プランで提供されます。2026年8月12日時点の公式料金ページでは、Individual Proが月額20ドルから、Teams Standardが1ユーザーあたり月額40ドルです。税金や上位プラン、年払い、Enterpriseの条件は別です。

ProにはCloud Agentが含まれ、Pro+とUltraではAgentの利用枠が増えます。消費量はモデルで異なり、上限後のOn-demand Usageでは追加請求の可能性があります。残量とToken内訳はDashboardで確認してください。

無料のHobbyプランには限定的なAgent Requestがありますが、公式ChangelogはiOS / iPadアプリの対象を「すべての有料プラン」としています。無料プランで同じMobileアプリ機能を利用できるとは確認できませんでした。

## まとめ

Cursorは2026年7月29日、iPad対応をすべての有料プランへ提供しました。iPhone・iPadでは、Cloud Agentの起動・管理、成果物や差分の確認、PR全体のレビュー、Agentへの修正依頼、マージまで対応しています。

要点は次のとおりです。

- iPad専用レイアウトで複数AgentとPR差分を確認
- iPhone・iPadからCloud Agentへ作業を依頼可能
- Cloud Agentは独立VMで動き、Laptopを閉じても継続
- GitHub連携によりPRの作成・レビュー・マージへ対応
- ローカルAgentのRemote ControlではPCを起動状態に保つ必要あり
- MobileはAgent管理とレビューが中心で、Desktop IDEの全工程を置き換えるものではない

「外出先からAIへ開発を依頼し、帰宅後にPCで最終確認する」Workflowは、Cursorの公式仕様で実行できます。Mobileだけでマージまで進める機能もありますが、変更の影響や必要なテストに応じて、Desktopでの最終確認を残す判断が重要です。

OmochiXでは今後、実際にiPhone・iPadからCloud Agentへ依頼し、PRを確認してDesktopへ引き継ぐ流れも検証していきます。

## Cursor公式一次情報

公開前にURL、内容、対象プラン、料金、アプリ提供地域を再確認してください。

1. **Cursor, now on iPad**
   - URL: https://cursor.com/changelog/ipad
   - 公開日: 2026-07-29
   - 提供開始日: 2026-07-29
   - 最終確認日: 2026-08-12
2. **Cursor Mobile App for iOS**
   - URL: https://cursor.com/changelog/ios-mobile-app
   - 公開日: 2026-06-29
   - 提供開始日: 2026-06-29（公開ベータ）
   - 最終確認日: 2026-08-12
3. **Cursor for iOS — Cursor Docs**
   - URL: https://cursor.com/docs/cloud-agent/mobile
   - 公開日: 要確認
   - 更新日: 要確認
   - 最終確認日: 2026-08-12
4. **Cursor agents can now control their own computers**
   - URL: https://cursor.com/blog/agent-computer-use
   - 公開日: 2026-02-24
   - 最終確認日: 2026-08-12
5. **Cursor Pricing**
   - URL: https://cursor.com/pricing
   - 公開日: 要確認
   - 更新日: 要確認
   - 最終確認日: 2026-08-12
6. **Models & Pricing — Cursor Docs**
   - URL: https://cursor.com/docs/models-and-pricing
   - 公開日: 要確認
   - 更新日: 要確認
   - 最終確認日: 2026-08-12

最終確認日：2026年8月12日

## 要確認事項

- 日本のApp StoreにおけるiPhone / iPadアプリの最新提供状況
- iOS / iPadアプリの公開ベータ終了時期と正式版表記
- Androidネイティブアプリの提供有無。現時点ではWeb / PWAのみ確認
- 対象となるiOS / iPadOSの最低バージョン
- Pro、Pro+、Ultra、Teams各プランの最新価格と利用枠
- Cloud / Background Agentの具体的なモデル別消費量
- GitHub以外のSCMを含むPR機能差
- MobileからProduction Deployを直接実行できるか。本稿では可能と記載していない
- Help / Docsの公開日・更新日
- WordPress上の公開日・更新日
- アイキャッチの制作、権利確認、alt文言
- 人間の編集責任者による一次情報との全文照合
- 記事内リンク、モバイル表示、見出し階層の公開プレビュー

## 内部リンク候補

- Target: `claude-sonnet-5-guide` / Anchor: 「Claude Sonnet 5のCoding / Agent機能」 / Placement: 「ChatGPT・Codex・Claudeとはどう使い分ける？」末尾
- Target: `gpt-5-6-guide` / Anchor: 「GPT-5.6の3モデルと用途の違い」 / Placement: 「ChatGPT・Codex・Claudeとはどう使い分ける？」末尾
- WordPress URL：いずれも未確定。公開時に実URLへ接続する

## 公開前チェック

- [ ] Human review
- [ ] Eyecatch
- [ ] WordPress preview
- [ ] Internal links
- [ ] Slim SEO
- [ ] Source links
- [ ] App / Price / Plan recheck
- [ ] Publish date

## Revision history

| 日付 | 状態 | 内容 |
|---|---|---|
| 2026-08-12 | Draft | Cursor公式一次情報を基に公開待ち第4稿を作成。人間による公開前確認待ち |
| 2026-08-12 | Pre-publication review | 現行Docs URL、iOS / iPadOS条件、Plan情報を反映し、投入情報を整理 |
