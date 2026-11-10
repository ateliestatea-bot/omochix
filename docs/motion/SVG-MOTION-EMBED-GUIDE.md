# SVG モーションスタジオ 書き出しと組み込みの手順

SVG モーションスタジオで作ったロゴの動きを、自分のサイトで使うための手順です。

## 1. どの使い方を選ぶか

| 使い方 | こんなとき | 置くもの | 動きを減らす設定の人 | CSP の厳しいサイト |
|---|---|---|---|---|
| **画像として使う（おすすめ）** | まず迷ったらこれ | SVG 2 つ | 静止画を表示 | 動く |
| HTML に直接書く | ファイルを置けない | なし | 止まる | 静止したロゴになる |
| HTML と CSS ファイル | CSP でインラインのスタイルを禁止している | CSS 1 つ | 止まる | 動く |
| JavaScript で操作する | 画面に入ってから動かしたい。再生や停止を操作したい | JS 1 つ | 止まる | 動く |

「CSP」は、サイトが読み込みを制限する設定です。分からなければ「画像として使う」を選んでください。

## 2. 画像として使う

1. スタジオの「書き出す」で「画像として使う」を選び、「ロゴの説明」に何のロゴかを書きます（例：OmochiX のロゴ）。
2. 「SVG をダウンロード」と「静止画 SVG」で 2 つのファイルを保存します。
3. 2 つのファイルを同じ場所に置きます。
4. 「コードをコピー」したコードを、ロゴを出したい場所に貼ります。

```html
<picture>
  <source media="(prefers-reduced-motion: reduce)" srcset="omochix-logo-motion-pop-static.svg">
  <img src="omochix-logo-motion-pop.svg" alt="OmochiX のロゴ" width="200" height="200">
</picture>
```

- ファイルを別の場所に置いた場合は、`src` と `srcset` をその URL に書き換えます。
- 大きさは `width` と `height` で変えます。
- 画像として使う場合、ページ側の CSS は SVG の中に効きません。

## 3. WordPress で使う

WordPress は標準では SVG のアップロードを受け付けません。また、編集権限によっては投稿に貼った `<picture>` のコードが消されます。OmochiX テーマでは、どの権限でも同じように表示できる **ショートコード** をおすすめします。

### おすすめ：ショートコード `[omochix_logo_motion]`（OmochiX テーマ）

**1. ファイルを置く（管理者）**

スタジオで「画像として使う」を選び、「SVG をダウンロード」と「静止画 SVG」の 2 つを保存します。ファイル名は変えずに、サーバーの次のフォルダーに置きます（FTP、SFTP、サーバーのファイル管理画面など。フォルダーがなければ作ります）。

```
wp-content/uploads/omochix-logo-motion/omochix-logo-motion-pop.svg
wp-content/uploads/omochix-logo-motion/omochix-logo-motion-pop-static.svg
```

- 2 つは同じ書き出しのものを置きます。別々の書き出しを組み合わせると表示されません。
- メディアライブラリからはアップロードしません（SVG を許可する設定やプラグインは使いません）。

**2. 投稿やページに書く（どの権限でも）**

「ショートコード」ブロック、または本文に次のように書きます。

```
[omochix_logo_motion name="omochix-logo-motion-pop" alt="OmochiX のロゴ" size="240"]
```

| 属性 | 内容 |
|---|---|
| `name` | ファイル名から `.svg` を除いたもの。小文字の英数字とハイフンだけ（64 文字まで） |
| `alt` | ロゴの説明（代替テキスト）。`"` を含めたいときは全体を `'` で囲みます（例：`alt='Acme "logo"'`）。省略すると装飾として扱います |
| `size` | 表示の幅（px）。16〜1024。省略すると 200。高さはロゴの縦横比で決まります |

これ以外の属性は無視されます。

**3. 確認する**

プレビューで動きを確認します。端末の「動きを減らす」設定がオンのときは、静止画が表示されます。

**表示されないとき**

ログインして投稿を編集できる人には、ページのソースに `<!-- omochix_logo_motion: 理由 -->` が出ます（訪問者には何も出ません）。

| 理由 | 意味 |
|---|---|
| `name` | `name` の形が正しくない |
| `folder` | `omochix-logo-motion` フォルダーがない（またはリンクになっている） |
| `missing` | 2 つのファイルのどちらかがない |
| `link` | ファイルがシンボリックリンクかハードリンク |
| `outside` | ファイルの実体がフォルダーの外にある |
| `changed` | 読み込みの途中でファイルが置き換わった（もう一度表示すると直る） |
| `invalid` | ファイルの中身がスタジオの書き出しとして正しくない（手で編集した、別のツールで保存し直した、UTF-8 以外で保存した、ロゴのタイトルなどの文字に `<` が含まれているなど） |
| `pair` | 2 つが同じ書き出しのものではない |
| `size` | ファイルが大きすぎる（1MB まで） |

**サーバーの設定（公開前に必須）**

ショートコードのファイルは `<img>` として表示するので、中のスクリプトや外部参照は動きません。ただし、フォルダーのファイルの URL を直接開くと、ブラウザーはそのファイルをサイトの一部として表示します。スタジオの書き出し以外のファイル（大文字の `.SVG`、`logo.svg.bak`、`.html`、`.xml` など）が置かれた場合も同じです。そこで、このフォルダーでは次のようにします（2026-11-09、ローカルの Apache 2.4 と 3 エンジンで確認）。

- 配信するのは、小文字の `{name}.svg` と `{name}-static.svg` だけ。それ以外のファイルは 403 で拒否します。
- すべての応答（403・404 を含む）に、CSP・`nosniff`・`Content-Disposition: attachment`・`noindex` を付けます。`attachment` があると、URL を直接開いたときは表示されずにダウンロードになります。`<img>` と `<picture>` の表示には影響しません。
- PHP などのハンドラーは、このフォルダーでは動かしません。
- 設定は `<IfModule>` で囲みません。必要なモジュールがないサーバーでは、このフォルダーが 500 エラーになり、何も配信されません（守りのない状態で黙って配信されるのを防ぐため）。

Apache / LiteSpeed（`wp-content/uploads/omochix-logo-motion/.htaccess`）：

```
Options -Indexes
<FilesMatch ".">
  Require all denied
</FilesMatch>
<FilesMatch "^[a-z0-9][a-z0-9-]{0,70}\.svg$">
  Require all granted
</FilesMatch>
SetHandler none
ForceType image/svg+xml
Header always set Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox"
Header always set X-Content-Type-Options "nosniff"
Header always set Content-Disposition "attachment"
Header always set X-Robots-Tag "noindex"
```

`{0,70}` は、64 文字の `name` に `-static` を足した長さまで許すためです。LiteSpeed は、対応していない指定を黙って無視することがあります [推定]。そのため、配置したあとに必ず下の確認を行います。

nginx（ローカルでは未確認）：

```
location ^~ /wp-content/uploads/omochix-logo-motion/ {
    autoindex off;
    add_header Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Content-Disposition "attachment" always;
    add_header X-Robots-Tag "noindex" always;
    location ~ "^/wp-content/uploads/omochix-logo-motion/[a-z0-9][a-z0-9-]{0,70}\.svg$" {
        types { }
        default_type image/svg+xml;
    }
    return 403;
}
```

nginx では、`location` の中の `add_header` が、サーバー全体の `add_header`（HSTS など）を打ち消します。必要なヘッダーは、この中にも書き足してください。

**配置したあとの確認（必須）**

本物の書き出しと、わざと置いた確認用のファイルの両方で確かめます。確認用のファイルは、終わったら削除します。

| 確認 | 期待 |
|---|---|
| `curl -I <フォルダー>/<name>.svg` | 200、`Content-Type: image/svg+xml`、CSP・`X-Content-Type-Options`・`Content-Disposition`・`X-Robots-Tag` の 4 つ |
| 確認用の `probe.SVG`（大文字）、`probe.svg.bak`、`probe.html` を置いて `curl -I` | どれも 403 で、同じ 4 つのヘッダー |
| ページの表示 | ショートコードのロゴが表示される |

確認用のファイルが 200 になる、またはヘッダーが欠けるときは、設定が効いていません。確認用のファイルを消し、フォルダーには書き出し以外のファイルを置かない状態で、原因を調べます。キャッシュ（CDN やサーバーのアクセラレータ）を挟む場合は、ヘッダーがそのまま返ることも確かめます。

**差し替え・削除**

- ファイルを新しい書き出しに置き換えると、次の表示から新しいロゴになります（URL の `?v=` はファイルの中身から作るので、内容が変われば必ず変わり、ブラウザーの古いキャッシュは使われません）。ページキャッシュのプラグインを使っている場合は、キャッシュを削除してください。
- どちらかのファイルを消すと、ショートコードは何も表示しなくなります。

### カスタム HTML で表示する（管理者と編集者のみ）

ショートコードが使えない場合だけの方法です。2 章のコードを「カスタム HTML」ブロックに貼り、`src` と `srcset` を置いた場所の URL にします。

**注意**

- 管理者と編集者以外（投稿者、寄稿者）が保存すると `<picture>` と `<source>` が消され、静止画への切り替えが効かなくなります（WordPress 7.1.4 で確認）。この場合はショートコードを使ってください。
- 投稿の本文に「HTML に直接書く」のコードを貼ることはおすすめしません。編集権限によっては SVG が削除され、CSS の文字だけが本文に残ります。

### テーマに組み込む（開発者向け）

テーマのテンプレートに 2 章のコードを書きます。ファイルはテーマの中に置き、URL は `get_theme_file_uri()` で作ります。

## 4. HTML に直接書く

コピーしたコード（`<svg>` から始まります）を HTML に貼ります。

- 同じページに複数のロゴを置いても、互いに干渉しません。
- サイトがインラインのスタイルを禁止していると動かず、静止したロゴになります。その場合は 5 章の方法を使ってください。

## 5. HTML と CSS ファイル

1. 「CSS をダウンロード」でファイルを保存し、サーバーに置きます。
2. コピーしたコードの `<link>` の `href` を、置いた場所の URL に書き換えて貼ります。

## 6. JavaScript で操作する

1. 「JS をダウンロード」でファイルを保存し、サーバーに置きます。
2. コピーしたコードの `<script>` の `src` を、置いた場所の URL に書き換えて貼ります。

ロゴが画面に半分以上入ったときに動き出します。ページの JavaScript から次のように操作できます（`omx-…` はコード内のクラス名）。

```js
window.omxLogoMotion['omx-…'].pause();
window.omxLogoMotion['omx-…'].play();
window.omxLogoMotion['omx-…'].restart();
```

## 7. 設定の保存と読み込み

「設定を保存（JSON）」で、ロゴと設定を 1 つのファイルに保存できます。「設定を読み込む」で同じ状態に戻せます。

- 読み込んだファイルの内容は、安全のために最初から検査し直します。
- このスタジオ以外で作ったファイルや、形式の違うファイルは読み込めません。

## 8. 安全について

- 書き出したファイルにはスクリプトが入っていません（JavaScript で操作する場合の JS ファイルを除く）。外部への通信もしません。
- ロゴに含まれていたスクリプトや外部の画像は、読み込み時に取り除かれています。
- ロゴのデータがスタジオから外部に送られることはありません。

## 9. 動かない場合

| 症状 | 確認すること |
|---|---|
| 何も表示されない | ファイルの URL が正しいか。2 つのファイルが同じ場所にあるか |
| 表示されるが動かない | 端末で「動きを減らす」設定がオンになっていないか。CSP の厳しいサイトで「HTML に直接書く」を使っていないか |
| WordPress で文字が出る | 投稿本文に直接 SVG を貼っていないか。「カスタム HTML」ブロックで画像として表示してください |
| 文字のロゴが崩れる | フォントは閲覧環境に依存します。ロゴの文字をアウトライン化してから読み込んでください |

メール、SNS の投稿画像、プレゼンテーションソフトなど、Web ページ以外では動かないことがあります。
