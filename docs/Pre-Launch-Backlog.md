# 公開前Backlog

更新日：2026年9月1日

状態：`未着手`、`要確認`、`進行中`、`完了`。P0は公開前必須、P1はβ品質、P2は公開後改善です。

## Brand Assets

| 項目 | Priority | Status | Blocker | 時期 | 備考 |
|---|---:|---|---|---|---|
| 正式おもち素材 | P1 | 未着手 | 正式素材承認 | 帰宅後 | 仮素材を差し替え、CLSを維持 |
| Hero背景 | P2 | 要確認 | 正式素材方針 | 帰宅後 | 現行CSS背景で公開可能か判断 |
| Site Icon | P0 | 未着手 | 正式ブランド画像 | 帰宅後 | 512×512px以上 |
| Apple Touch Icon | P1 | 未着手 | Site Icon | 帰宅後 | 実端末で確認 |
| favicon | P1 | 要確認 | Site Icon | 帰宅後 | Fallbackは存在、正式設定が必要 |
| Default OGP | P0 | 未着手 | 正式デザイン | 帰宅後 | 1200×630px |
| AI News OGP | P0 | 未着手 | 正式デザイン | 帰宅後 | 1200×630px |
| AI Tool OGP | P0 | 未着手 | 正式デザイン | 帰宅後 | 正方形ロゴを直接流用しない |

## AI Tool Data

| 項目 | Priority | Status | Blocker | 時期 | 備考 |
|---|---:|---|---|---|---|
| 実ロゴ登録 | P1 | 未着手 | 利用権確認 | 帰宅後 | 頭文字Fallbackとの混在確認 |
| 実本文 | P1 | 未着手 | 編集・確認 | 帰宅後 | ブロックエディターで管理 |
| key_features | P1 | 未着手 | 編集・確認 | 帰宅後 | 空なら非表示を維持 |
| pros | P1 | 未着手 | 編集・確認 | 帰宅後 | 誇張しない |
| cons | P1 | 未着手 | 編集・確認 | 帰宅後 | 制約・注意を具体化 |
| recommended_for | P1 | 未着手 | 編集・確認 | 帰宅後 | 判断支援に使用 |
| pricing_type = contact表示 | P1 | 要確認 | 該当実データ | 帰宅後 | 正式enum表示を確認 |
| japanese_support = none / unknown表示 | P1 | 要確認 | 該当実データ | 帰宅後 | 色だけに依存しない |
| 関連記事 | P1 | 未着手 | 実ニュース | 帰宅後 | 自動取得の関連性を確認 |

## News

| 項目 | Priority | Status | Blocker | 時期 | 備考 |
|---|---:|---|---|---|---|
| 初期ニュース3〜10件 | P0 | 未着手 | 一次情報確認 | 帰宅後 | 架空ニュース禁止 |
| Newsアイキャッチ | P0 | 未着手 | 権利・素材 | 帰宅後 | 1200×630px推奨 |
| 出典URL・最終確認日 | P0 | 未着手 | 編集確認 | 帰宅後 | 各記事必須 |
| NewsArticle実出力 | P0 | 要確認 | 公開ニュース | 帰宅後 | 詳細だけに1件 |
| 一覧Pagination実記事確認 | P1 | 要確認 | 十分な記事数 | 帰宅後 | Mobile wrapも確認 |
| 実記事Dark Mode | P1 | 要確認 | 公開ニュース | 帰宅後 | table/code/imageを確認 |
| AIニュース一覧index/canonical | P0 | 要確認 | 公開ニュース | 帰宅後 | 現在0件でnoindex。投入後に自己canonicalを確認 |

## Legal / Company

| 項目 | Priority | Status | Blocker | 時期 | 備考 |
|---|---:|---|---|---|---|
| Contact機能・文面 | P0 | 完了 | ー | ー | フォーム実装済み。対応時間文言も確定 |
| Privacy Policy | P0 | 完了 | ー | ー | 個人運営前提で実装済み |
| Terms | P0 | 未着手 | 法務確認 | 帰宅後 | サービス範囲に合わせる |
| 運営者情報 | P0 | 完了 | ー | ー | Companyページに反映済み（個人運営／OmochiX編集部） |
| 正式会社情報 | P0 | 完了（対象外） | ー | ー | 個人運営のため法人情報掲載は現時点で対象外。法令上必要な開示事項はPrivacy Policy記載の問い合わせ窓口経由で対応 |
| 所在地（渋谷本店） | P0 | 完了（対象外） | ー | ー | 個人運営のため法人情報掲載は現時点で対象外。法令上必要な開示事項はPrivacy Policy記載の問い合わせ窓口経由で対応 |
| 問い合わせ先 | P0 | 完了 | ー | ー | Contactフォームのみで確定。メールアドレスは非公開 |
| 対応時間 | P1 | 完了 | ー | ー | 「お問い合わせには順次対応いたします。内容により返信までお時間をいただく場合があります。」で確定 |

## SEO / Production

| 項目 | Priority | Status | Blocker | 時期 | 備考 |
|---|---:|---|---|---|---|
| Search Console | P0 | 未着手 | 本番ドメイン | 本番後 | 所有権確認 |
| Sitemap送信 | P0 | 未着手 | 本番公開 | 本番後 | 本番HTTPS URL |
| OGP preview | P1 | 未着手 | 本番公開・正式画像 | 本番後 | SNS cacheを確認 |
| Rich Results Test | P0 | 未着手 | 本番公開・実記事 | 本番後 | NewsArticle / SoftwareApplication |
| Schema Validator | P1 | 未着手 | 本番公開 | 本番後 | 誤情報と重複を確認 |
| Lighthouse実測 | P1 | 未着手 | 本番公開 | 本番後 | Mobile中心 |
| Production canonical | P0 | 未着手 | 本番ドメイン | 本番後 | HTTPS、host、slash統一 |
| Production robots | P0 | 未着手 | 本番公開 | 本番後 | Sitemapとcrawl方針 |
| Index確認 | P0 | 未着手 | crawler反映 | 本番後 | index/noindex方針を照合 |

## 集計

- 全項目：41件
- P0：22件
- P1：18件
- P2：1件
