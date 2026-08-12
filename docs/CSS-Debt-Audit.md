# CSS負債監査

監査日：2026年8月12日

対象：`wp-theme/omochix-theme/style.css`

方針：本監査では削除・統合・表示変更を行わない。分類Cも、DOM確認と全幅の視覚回帰確認を終えるまでは削除しない。

## 概要

- セレクター出現数（概算）：1,517
- ユニークセレクター数（概算）：835
- 複数回定義されるセレクター：316
- 追加定義の出現数：682
- `prefers-reduced-motion`：既存定義あり
- CSS構文：波括弧の対応に問題なし

上記はメディアクエリ内の再定義も重複として数えた静的集計です。レスポンシブ定義を含むため、重複数そのものは不具合数ではありません。

## 重複が多い領域

| 領域 | 主なセレクター例 | 状況 | 分類 |
|---|---|---|---|
| Home Hero | `.home-hero__inner`（21回）、`.home-hero__visual`（19回）、`.home-hero__image-frame`（17回） | Sprintごとの調整とブレークポイント定義が積層。現行Heroの基準表示を成立させている | A / B |
| Home Hero文字・検索 | `.home-hero__description`、`.home-hero__search`、`.home-hero__popular`（各11回） | PC・Tablet・Mobile専用調整を含む | A / B |
| Homeセクション | News、Tool、Category、Recommendedの各header/card | Home UI統一の最終ルールと初期ルールが併存 | A / B |
| Newsletter / Footer | `.newsletter__inner`（11回）、`.newsletter__note`（9回）ほか | 黒単一カード化とレスポンシブ最終調整が後段にある | A / B |
| News一覧 | News archive専用セレクター | 初期一覧CSSを最終UIブロックが上書き | A / B |
| AI Tool一覧 | `.tool-results__grid`（7回）ほか | archive専用の最終UI・ページネーション調整が後段にある | A / B |
| AI Tool詳細 | Hero、Summary、Tab、Related、CTA | 詳細ページ専用の最終UIブロックが後段にある | A / B |
| About / 404 | About/404専用セレクター | 最終調整ブロックが後段にある | A / B |

## 分類A：現在必要

- Design Systemのルート変数、色、Typography、Container、Focus、Dark Mode。
- Header、確定済みHome Hero、Newsletter、Footerの最終ブレークポイント定義。
- Homeカード・セクションヘッダー・Typographyの最終統一ルール。
- News一覧、AI Tool一覧、AI Tool詳細、About、404のページ専用最終ルール。
- `prefers-reduced-motion`、横スクロール防止、画像比率、table/code内部スクロール。
- WordPress標準出力とEmpty/Fallback状態を支えるルール。

これらは現在の公開候補UIを成立させるため、優先順位の整理なしに移動・削除しません。

## 分類B：将来統合可能

- 同一コンポーネントの基本値と最終上書きを、コンポーネント単位へ集約する。
- `24px`、`16px`、`12px`、`48px`、`1px solid var(--color-line)`など反復値を既存Tokenへ寄せる。
- Home各カードの共通Border、Radius、Motion、Title clampを共通ユーティリティまたは複合セレクターへ統合する。
- Newsと関連記事カード、AI Tool一覧と関連ツールカードの共通部分を整理する。
- HeroのPC、Tablet、Mobileルールをブレークポイントごとの一箇所へまとめる。
- Newsletter / Footer、About / 404のDark Mode上書きを各コンポーネント末尾へ集約する。

実施時は、320 / 390 / 768 / 1024 / 1440px、Dark Mode、Reduced Motion、Empty/Fallbackの視覚回帰確認を必須とします。

## 分類C：削除候補

- 後段の同一詳細度セレクターに全プロパティを上書きされ、どのブレークポイントでも最終値にならない旧Sprintルール。
- 現在のPHPテンプレート、JavaScript、WordPress生成クラスのいずれからも参照されない単純クラス。
- 廃止済みのNewsletter紫外枠や旧Heroカード表現にだけ使われていた装飾ルール。

ただし、WordPress本文・プラグイン・管理画面から動的に付与されるクラスを静的検索だけで「死んだCSS」と断定できません。本監査時点では具体的な削除を行わず、次回のCSS整理Sprintで次の順に確定します。

1. テンプレートとWordPress生成HTMLのクラス一覧を採取
2. 主要URL・全幅・Dark ModeでCoverageを採取
3. 削除候補を小単位で除去
4. 視覚回帰とキーボード操作を再確認

## 推奨対応順

1. Heroは基準デザインとして凍結し、当面リファクタリング対象外にする。
2. Token化できる反復値だけを先に一覧化する。
3. ページ単位（News → AI Tool一覧 → AI Tool詳細 → About/404）で統合する。
4. Home共通カードは最後にまとめ、取得ロジックやDOMを変更しない。
