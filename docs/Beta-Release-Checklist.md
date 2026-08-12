# β公開チェックリスト

更新日：2026年8月12日

状態は `完了`、`要確認`、`未着手`、`公開後` で管理します。P0は公開判定に必須、P1はβ品質に重要、P2は継続改善です。

## 完了済み

| 項目 | 状態 | 優先度 | 作業場所 | 備考 |
|---|---|---:|---|---|
| WordPress Theme | 完了 | P0 | Git / WordPress | 公開ページ用テンプレート実装済み |
| OmochiX Core Plugin | 完了 | P0 | Git / WordPress | AI Toolデータ契約、管理、Import、Schema基盤を実装済み |
| Home | 完了 | P0 | Theme | Responsive UI基準を確定 |
| Site Search | 完了 | P0 | Theme | post / ai_tool、検索結果noindex |
| AI Tool Archive | 完了 | P0 | Theme | Filter、Pagination、Empty対応 |
| AI Tool Detail | 完了 | P0 | Theme / Core Plugin | 正式enumとSoftwareApplicationを利用 |
| AI News Archive / Detail基盤 | 完了 | P0 | Theme | 0件Fallbackを含む。実記事での最終確認は別項目 |
| SEO基盤 | 完了 | P0 | Slim SEO / Theme | SEO所有範囲を分離 |
| Schema基盤 | 完了 | P0 | Slim SEO / Core Plugin | SoftwareApplicationはCore Pluginのみ |
| Sitemap / robots | 完了 | P0 | Slim SEO / WordPress | ローカルHTTP 200を確認 |
| Responsive構造 | 完了 | P0 | Theme | 320 / 390 / 768 / 1024 / 1440で構造確認 |
| Dark Mode | 完了 | P1 | Theme | 主要ページが既存Tokenへ追従 |
| Feature branch Git backup | 完了 | P0 | GitHub | `origin/feature/home-mvp`を追跡 |

## 帰宅後必要

| 項目 | 状態 | 優先度 | 作業場所 | 備考 |
|---|---|---:|---|---|
| 主要ページ最終実ブラウザ目視 | 要確認 | P0 | Local browser | 実機・Chrome/Safari、Light/Darkで確認 |
| 正式おもち素材 | 未着手 | P1 | Design / Theme assets | 仮素材との差し替え、CLS維持 |
| 正式ロゴ確認 | 要確認 | P0 | Brand / Theme | 小サイズ、Dark背景で判別確認 |
| Site Icon | 未着手 | P0 | WordPress管理画面 | 512×512px以上の正方形PNG推奨 |
| Default / News / Tool OGP | 未着手 | P0 | Design / Slim SEO | 1200×630px。正方形Toolロゴは流用しない |
| 実ニュースカード | 未着手 | P0 | WordPress | 一次情報確認済みの記事を3〜10件投入 |
| 実AI Toolロゴ | 未着手 | P1 | WordPress Media | 正方形表示とFallback混在を確認 |
| 法務ページ | 未着手 | P0 | WordPress / Legal | Privacy、Terms、運営者情報を確定 |
| Contact Form | 未着手 | P0 | WordPress | 送信先、個人情報、spam対策を確認 |
| 正式会社・所在地・対応時間 | 要確認 | P0 | Operations / Legal | 推測せず運営者が確定 |
| AIニュース一覧index確認 | 要確認 | P0 | Slim SEO / HTTP | 現在0件のためnoindex。実記事投入後にself canonicalとindexを確認 |

## 本番公開後

| 項目 | 状態 | 優先度 | 作業場所 | 備考 |
|---|---|---:|---|---|
| Google Search Console | 公開後 | P0 | Google | 所有権確認、インデックス状況確認 |
| Sitemap送信 | 公開後 | P0 | Search Console | 本番HTTPS URLを使用 |
| Rich Results Test | 公開後 | P0 | Google | 実NewsArticle、SoftwareApplicationを検証 |
| Schema Validator | 公開後 | P1 | Schema.org | JSON-LDの型・関係を確認 |
| OGP / SNS preview | 公開後 | P1 | X / Facebook等 | 正式画像とキャッシュを確認 |
| Canonical HTTPS確認 | 公開後 | P0 | Browser / crawler | HTTP、www有無、末尾slashを統一 |
| robots本番確認 | 公開後 | P0 | Browser | crawl許可とSitemap URLを確認 |
| Lighthouse実測 | 公開後 | P1 | Browser | CWV、Accessibility、SEOを実測 |
| Index確認 | 公開後 | P0 | Search Console | noindex対象が混入していないか確認 |
| 404 / redirect監視 | 公開後 | P1 | Logs / Search Console | 内部・外部リンク切れを継続確認 |
