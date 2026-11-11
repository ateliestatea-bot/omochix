# SVG Motion Studio 設定ファイル schema 2（確定仕様）

対象：`export.js` の `parseSettings`、`compile.js` の `validateEdit` と `compileEdit`、`store.js`。
更新日：2026年11月3日（S4-0）

## 1. 形

```json
{
  "format": "omochix-svg-motion",
  "schema": 2,
  "svg": "<svg …正規化済み…>",
  "settings": { "preset": "logo-pop", "params": {}, "common": {} },
  "edit": {
    "duration": 2500,
    "nextId": 3,
    "origin": { "x": 100, "y": 100 },
    "layers": [
      {
        "id": "L1",
        "target": { "kind": "part", "index": 0 },
        "start": 100,
        "end": 1500,
        "tracks": [
          { "prop": "translate", "easing": "soft", "keys": [ { "at": 0, "v": [0, -30] }, { "at": 1, "v": [0, 0] } ] },
          { "prop": "scale", "easing": "ease-in-out", "keys": [ { "at": 0, "v": [1] }, { "at": 0.5, "v": [0.6] }, { "at": 1, "v": [1] } ] }
        ]
      },
      { "id": "L2", "target": { "kind": "root", "index": 0 }, "start": 0, "end": 2500,
        "tracks": [ { "prop": "rotate", "easing": "linear", "keys": [ { "at": 0, "v": [0] }, { "at": 1, "v": [45] } ] } ] }
    ]
  }
}
```

## 2. schema 番号

| schema | `edit` | 書く場面 | 読める実装 |
|---|---|---|---|
| 1 | なし（あれば `JSON_INVALID`） | 自由編集がないとき | S3 以降 |
| 2 | 必須（オブジェクト） | 自由編集があるとき | S4-0 以降。S3 のスタジオでは `JSON_SCHEMA` で拒否される |
| その他 | — | — | `JSON_SCHEMA` |

書き出し側は、編集がなければ schema 1 を書く。S4 で保存したファイルの多くは、そのまま S3 でも開ける。

IndexedDB の記録も同じ番号を使う。保存は常に 2（`edit` は `null` か編集）。1 の記録は読み込んで `edit: null` として扱い、次の保存で 2 になる。不明な番号は削除する（S2 からの方針）。

## 3. `edit` の規則

| 項目 | 規則 | 違反時 |
|---|---|---|
| キー | `duration`、`nextId`、`layers` は必須。`origin` は任意。それ以外は不可 | `EDIT_INVALID` |
| `duration` | 整数 ms、100〜20000 | `EDIT_INVALID` |
| `nextId` | 整数 1〜999999。すべてのレイヤー ID の番号より大きい | `EDIT_INVALID` |
| `origin` | `{x, y}` の有限数（±100000）。root の回転・拡大の中心（viewBox 座標）。スタジオが sandbox 枠で計測して書く。省略時は root の `fill-box` のまま | `EDIT_INVALID` |
| `layers` | 配列、最大 40 | `EDIT_LIMIT` |
| `layers[].id` | `L` + 1 以上の整数（先頭 0 なし）。重複不可。削除後も再利用しない | `EDIT_INVALID` |
| `layers[].target` | `{kind: "part" \| "root", index}`。`part` の `index` はロゴのパーツ番号の範囲内、`root` は 0。同じ対象に 2 レイヤーは不可 | `EDIT_INVALID` / `EDIT_CONFLICT` |
| `start`、`end` | 整数 ms。`0 ≤ start`、`end ≤ duration`、`end − start ≥ 50` | `EDIT_INVALID` |
| `tracks` | 1〜8 個。同じ `prop` は 1 つまで | `EDIT_INVALID` / `EDIT_LIMIT` / `EDIT_CONFLICT` |
| `tracks[].prop` | `translate`、`rotate`、`scale`、`opacity`、`fill`、`stroke`、`stroke-width`、`stroke-dashoffset` | `EDIT_INVALID` |
| `tracks[].easing` | `linear`、`ease`、`ease-in`、`ease-out`、`ease-in-out`、`soft`、`back` | `EDIT_INVALID` |
| `keys` | 2〜12 個。`at` は 0〜1 で昇順、先頭 0、末尾 1 | `EDIT_INVALID` / `EDIT_LIMIT` |
| `keys[].v` | `prop` ごとの数値配列（下表）。色は `#rrggbb`（小文字） | `EDIT_INVALID` |
| 合計 | トラック 120、キーフレーム 600 | `EDIT_LIMIT` |

| `prop` | `v` | 範囲 | CSS |
|---|---|---|---|
| `translate` | `[x, y]` | ±4000 | `translate: Xpx Ypx` |
| `rotate` | `[deg]` | ±3600 | `rotate: Ddeg` |
| `scale` | `[s]` または `[sx, sy]` | 0.05〜10 | `scale: S` |
| `opacity` | `[o]` | 0〜1 | `opacity: O` |
| `stroke-width` | `[w]` | 0〜100 | `stroke-width: W` |
| `stroke-dashoffset` | `[d]` | ±10000 | `stroke-dashoffset: D` |
| `fill`、`stroke` | `["#rrggbb"]` | — | そのまま |

値は小数 3 桁に丸めて保持する（表示精度の正規化）。範囲外の値、型違い、未知のキーは **丸めずに拒否** し、ファイル全体を受け付けない。

## 4. プリセットとの関係

- `settings` は常に残り、「プリセットに戻す」の元になる。
- 編集トラックは、プリセットのトラックの後ろに足される。同じ要素の同じ性質（例：`logo-stagger-pop` の `opacity` とレイヤーの `opacity`）はプリセットと競合するので `EDIT_CONFLICT`。`transform`（プリセット）と `translate`/`rotate`/`scale`（編集）は別の性質で、同じ要素に共存する。
- 1 つの要素のすべてのトラックは、1 つの `animation` 規則に列挙される（S4-0 で修正。以前は後の規則が前の規則を置き換えていた）。

## 5. 互換性

| 場合 | 扱い |
|---|---|
| S4 が schema 1 を読む | 受け付ける（`edit: null`） |
| S4 が schema 2 を読む | 受け付け、第 3 章の検査。パーツ番号の検査はロゴの読み込み後 |
| S3 が schema 2 を読む | `JSON_SCHEMA`。`settings` だけ使いたいときは `schema` を 1 にして `edit` を消す |
| 別のロゴを読み込む | `edit` は破棄し、状態文に表示する（確認ダイアログは S4-1） |
| schema 3 | 意味が変わる変更のときだけ番号を上げ、2 → 3 の変換を `parseSettings` に置く。未知のキーは拒否のままにする |
