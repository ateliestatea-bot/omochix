# ナビゲーション設計(将来拡張対応)

実装: `wp-theme/omochix-theme/inc/nav.php`(単一のレジストリ)。テスト: `tests/nav/run.php`(16件)。

## 公開状態
| 状態 | 意味 | 表示 |
|---|---|---|
| `published` | 公開済み | リンク。**URLが解決できない(ページ未公開・未作成)場合は自動的に `coming-soon` 扱い**。デッドリンクを出さない |
| `coming-soon` | 準備中 | 文字のみ+「準備中」。リンクにしない。トップのショートカットには出さない |
| `hidden` | 非公開 | どこにも出さない |
WordPress上にページがあっても `coming-soon` は公開扱いにならない(意図的な切替が必要)。

既定: 主要6カテゴリー=published / 動画・コミュニティ=coming-soon / AIチャット=hidden。

## 面(surface)
`nav`(PCサイドバー+スマホメニュー)/ `home`(検索下ショートカット)/ `footer` / `soon`(フッターの「準備中」行)。各項目が参加する面をレジストリで宣言。

## 将来、動画・コミュニティ・AIチャットを公開するとき(テンプレ変更なし)
1. ページを公開する(`/videos/` `/community/` `/chat/`)。
2. 状態を切り替える。どちらか:
   - 管理画面なしで: `update_option('omochix_nav_status', ['videos' => 'published']);`
   - コードで: `add_filter('omochix_nav_status', fn($s, $id) => 'videos' === $id ? 'published' : $s, 10, 2);`
3. 全く新しいセクションは `add_filter('omochix_nav_registry', …)` で1項目追加(例は `inc/nav.php` 冒頭)。
サイドバー・モバイルメニュー・フッター・トップに同時に反映されます。

## 8項目以上のレイアウト(検証済み: `docs/nav-ux-update/screens/after/future9-*.png`)
- PCサイドバー: グループ(主要 / 近日 / ユーティリティ)を罫線で区切り、はみ出す場合は縦スクロール(既存 `overflow-y:auto`)。
- スマホメニュー: 既存パネルが縦スクロール。区切り線あり。
- トップのショートカット: 6個まで=PC1行 / 7〜8個=4列×2段 / 9〜10個=5列 / スマホは常に3列×N段。
- 管理画面のメニュー(外観 > メニュー、`primary`)を割り当てている場合はこれまで通りヘッダーはそちらが優先(他の面はレジストリ)。**本番で割り当て済みか未確認 → Go条件に記載**。

## 補足: 変更前の定義の重複
header.php(既定リスト)/ footer.php / footer-editorial.php / editorial-home.php の4か所 + labs.php・shell.php のフッターフィルターに分散 → `inc/nav.php` に一本化。
