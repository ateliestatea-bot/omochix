=== OmochiX Core ===
Contributors: omochix
Requires at least: 6.8
Requires PHP: 8.1
Stable tag: 1.10.0
License: GPLv2 or later

OmochiXのAIツール投稿タイプ、タクソノミー、投稿メタ、管理画面を提供します。

== Installation ==

1. `omochix-core`ディレクトリを`wp-content/plugins/`へ配置します。
2. WordPress管理画面の「プラグイン」から「OmochiX Core」を有効化します。
3. 有効化時に初期タームとパーマリンク規則が登録されます。

== Data ==

Archive: `/ai-tools/`
Single: `/ai-tools/{slug}/`
REST: `/wp-json/wp/v2/ai-tools`

AIツールのHero画像にはWordPress標準アイキャッチを使用します。

== Changelog ==

= 1.10.0 =
* Industry Hub（業種別AI活用）を追加：業種・業務目的・提供元のタクソノミー、業種ハブの項目と公開判定（G1〜G7）、外部コンテンツ台帳、ワークフローの項目と公開ガード、非公開業種の保護（REST・フィード・埋め込み・検索・サイトマップ）。
* 業種ハブは対応テーマ（1.29.0 以降）と組み合わせたときだけ公開され、内容が公開判定を満たすまでは表示されません。
* 部分アップロードへの備え：Industry Hub のファイルが揃わない・版が違う間は Industry Hub だけを停止し、その間ワークフローの新規公開・予約公開を保留します。
* 予約公開の直前にワークフローの公開条件を再確認し、満たさないものは下書きに戻して理由を記録します。
* 初回の読み込み時に、業種6件・業務目的7件・提供元4種の初期タームを作成し、パーマリンク規則を一度だけ更新します。既存の投稿・ページ・設定は変更しません。
