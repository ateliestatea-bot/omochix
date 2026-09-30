# AI Tools Image Manifest v1

作成日：2026-10-01
フェーズ：Image Phase v1（調査・設計のみ。production / WordPress / DB / FTP への変更なし）
対象：Batch 01 の9ツール。Veo, Kling AI, Runway, Midjourney, Claude Code, Cursor, NotebookLM, ChatGPT, Gemini
資産数：17点（Logo 8点・HERO 9点）。Gemini Logo は KEEP のため資産数に含めない

関連文書：

- `docs/AI-Tools-Content-Updater.md`：画像 attachment ID の反映経路
- `docs/Pre-Launch-Backlog.md`
- `docs/OmochiX-Design-System-v1.1.pdf`

---

## 0. サマリー

| Tool | Slug | Post ID | Logo 現状 | Logo 判定 | HERO 現状 | HERO 判定 |
|---|---|---|---|---|---|---|
| Veo | `veo` | 678 | なし（0） | MISSING | なし（0） | MISSING |
| Kling AI | `kling-ai` | 677 | なし（0） | MISSING | なし（0） | MISSING |
| Runway | `runway` | 675 | なし（0） | MISSING | なし（0） | MISSING |
| Midjourney | `midjourney` | 59 | なし（0） | MISSING | なし（0） | MISSING |
| Claude Code | `claude-code` | 665 | なし（0） | MISSING | なし（0） | MISSING |
| Cursor | `cursor` | 65 | なし（0） | MISSING | なし（0） | MISSING |
| NotebookLM | `notebooklm` | 659 | なし（0） | MISSING | なし（0） | MISSING |
| ChatGPT | `chatgpt` | 53 | ID 54：716×716 PNG（黒Blossom・透過） | **REPLACE** | ID 566：1672×941 PNG | **REPLACE** |
| Gemini | `gemini` | 61 | ID 634：800×800 WebP | **KEEP** | ID 636：1672×941 PNG | **REPLACE** |

- Logo：KEEP 1 / REPLACE 1 / MISSING 7。作成・取得対象は8点
- HERO：KEEP 0 / REPLACE 2 / MISSING 7。作成対象は9点
- 52ツール全体のカバレッジ（2026-10-01 production REST 読み取り）
  - Logo：8/52
  - HERO：8/52
  - 設定済みのツール：heygen, grok, muse, jev, perplexity, gemini, claude, chatgpt
- HeyGen / Perplexity は本フェーズでは変更しない（既存決定）。HERO のスタイル基準（PASS 例）として参照する

---

## 1. 現状監査（STEP 1、READ ONLY）

調査方法：public REST（`/wp-json/wp/v2/ai_tool`、`/wp-json/wp/v2/media`）の GET、公開ページの目視確認。
スナップショットはローカル scratchpad に保存しており、production の URL・ホスト名は本文書に記載しない。

### 表示仕様（テーマ実装から確認）

- **Logo**
  - 取得：`wp_get_attachment_image($logo_id, 'thumbnail')`。150×150 のハードクロップ
  - 表示サイズ：詳細ページは 80px、カードは 64px
  - タイル背景：light は薄紫、dark は暗紫
  - **1枚の画像を light / dark 両方で使う**（テーマ側に dark 用の差し替え機構はない）
- **HERO**
  - 取得：`get_the_post_thumbnail_url($id, 'large')`。1024w、16:9 ボックス
  - featured image は Slim SEO の og:image と `SoftwareApplication.image` にも使われる
- **未設定時**
  - Logo：頭文字タイル（fallback）
  - HERO：OmochiX 共通プレースホルダー

### ChatGPT Logo（ID 54）→ REPLACE

- 公式の黒 Blossom（`OAI_OpenAI-Blossom_Black.png`）で、透過 PNG
- dark モードの暗紫タイル上で視認性が大きく落ちることをブラウザで確認済み
- 画像自体は公式素材なので「素材の出所」ではなく「テーマ上の見え方」の問題

### ChatGPT HERO（ID 566）→ REPLACE

- UI モック風で、文字情報が多い
- モデル名表記（「GPT-5.6」）が陳腐化する
- 方針「文字最小・UI コピー禁止」に反する

### Gemini Logo（ID 634）→ KEEP

- 800×800 の不透明 WebP
- 現行スパークマークで、light / dark 両方で視認性に問題なし
- **注意**：入手元の記録がない。Google 素材の利用条件（§2）の確認対象に含める

### Gemini HERO（ID 636）→ REPLACE

- 文字が多い
- Google のプロダクトマーク類を含む
- 方針「ロゴ・文字を含まないオリジナル editorial visual」に反する

### Media Library の衝突確認（STEP 6）

- 総数：176件
- `omochix-ai-tool` 検索：0件
- 命名規約（§5）との衝突なし

---

## 2. 公式ブランド調査（STEP 2、一次情報のみ・2026-10-01 確認）

Wikipedia・Pinterest・ロゴ収集サイトは参照していない。ロゴファイルのダウンロードは本フェーズでは行っていない。

| 事業者 / 製品 | 公式情報源 | 公式素材 | 主な利用条件 | 判定 |
|---|---|---|---|---|
| OpenAI / ChatGPT | https://openai.com/brand/ | Blossom ロゴ（ページ内ダウンロードボタン）。詳細ガイドライン brand.openai.com はログイン必要 | Blossom を着色・改変しない、提供されたまま使う、自社の主要ブランドとして使わない、提携・推奨を示唆しない。問い合わせは partnercomms@openai.com | 公式素材あり |
| Cursor（Anysphere） | https://cursor.com/brand | 公式 brand zip（App icon light/dark、正方形 avatar を含む） | 製品名は「Cursor」（「Cursor AI」と書かない） | 公式素材あり（最良） |
| Runway | https://runway.com/brand-guidelines | `Runway-Brand-Guidelines.zip`（公式ドメイン配布） | light 背景は黒ワードマーク、dark 背景は白ワードマーク。狭い場所ではシンボルのみ可。最小 24px。製品名は「Runway」 | 公式素材あり |
| Anthropic / Claude Code | https://www.anthropic.com/news →「Download press kit」（`anthropic.com/press-kit`、問い合わせ press@anthropic.com） | press kit（中身は未確認：in-app browser のナビゲーションが拒否された） | press kit 内のガイドラインを要確認。Claude Code 固有ロゴの有無も要確認 | 公式配布経路あり・内容未検証 |
| Google / Gemini・Veo・NotebookLM | https://about.google/brand-resource-center/guidance/ ・ `/products-and-services/` | Gemini / Veo / NotebookLM 専用の公開キットなし（products-and-services に掲載なし） | 製品名のプレーンテキスト参照は可。**Product icons は「Ask first」**（許可申請が必要な区分）。ロゴ・ビジュアルアイデンティティの模倣は禁止、推奨・提携の示唆も禁止 | **許可確認が必要** |
| Midjourney | Trademark Policy（docs.midjourney.com、Last Updated 2023-08-07）。問い合わせ press@midjourney.com | 公開ロゴキットなし。公式サイトには apple-touch-icon（小サイズ）のみ | 表記は「Midjourney」のまま（複数形・所有格・改変・組み合わせは禁止）。推奨・提携の示唆は禁止。商標注記「Midjourney is a trademark of Midjourney, Inc. We are not endorsed by or affiliated with Midjourney, Inc.」を求めている | **公式高解像度素材なし** |
| Kling AI（Kuaishou） | https://kling.ai/ | press / brand ページなし。公式 CDN に `logo-180x180.png`（apple-touch-icon）のみ | 公開された利用条件なし | **公式高解像度素材なし** |

---

## 3. Logo ポリシー（STEP 3）

1. **公式素材のみ使う**
   - 入手元は §2 の公式ページ、公式ドメイン配布物、公式 press 窓口の順に優先する
   - 第三者サイトからは取得しない
2. **AI 生成・描き直し・類似ロゴ作成は禁止**
   - SVG の手描きトレースも禁止
   - 色変更も禁止（OpenAI・Midjourney・Google が明示的に禁止している）
3. **許容する加工は「配置」だけ**
   - 公式マークを改変せず、正方形のニュートラル背景プレート（白 `#FFFFFF`）の中央に余白 20% 以上で配置する
   - 書き出しは WebP / PNG。縮小のみ行い、拡大はしない
   - 公式が不透明な正方形 App icon を提供している場合は、プレートを使わずそのまま使う（第一候補）
4. **仕様**
   - 正方形、最低 512×512（推奨 1024×1024）
   - WebP（透過が必要なら lossless）
   - light / dark 両タイルで視認できること（黒一色・白一色の透過マークは単体で使わない）
5. **公式素材が得られない場合は画像を登録しない**
   - 頭文字タイル（現行 fallback）のままにする
   - fallback は「欠損」ではなく正しい状態として扱う
6. **許可制ブランド（Google）**
   - Product icons の「Ask first」区分に該当する可能性があるため、運営者が判断・申請するまでアップロードしない
   - 既存の Gemini Logo（ID 634）も同じ確認の対象（撤去は運営者判断。本フェーズでは変更しない）
7. **表記**
   - alt と本文では公式表記を使う（Cursor、Runway、Midjourney、NotebookLM など）
   - ロゴを製品名より目立たせる使い方はしない（Midjourney 条件）

---

## 4. HERO デザインシステム（STEP 4）

OmochiX オリジナルの editorial visual。各ツールの「何をするツールか」を抽象的に表現する。

### 共通仕様

| 項目 | 仕様 |
|---|---|
| サイズ | 1280×720（16:9）。WordPress が `large` 1024×576 を自動生成する |
| 形式 | WebP（quality 80–85、sRGB）。目標 250KB 以下 |
| 構図 | 主題を中央 60% に集める（カード・og:image の 1.91:1 クロップでも主題が残るように） |
| 背景 | OmochiX のダーク基調（深い藍〜黒紫）。アクセントは OmochiX 紫系 |
| ツール差別化 | ツールごとに**モチーフとサブアクセント色**を変える |
| 文字 | **原則なし**（UI の文字、モデル名、価格、キャッチコピーを入れない） |
| ロゴ | **入れない**（ツール・OmochiX どちらのロゴも入れない。ロゴは Logo 資産の役割） |
| 禁止 | 公式 UI スクリーンショットの複写やトレース、公式サイトの単純なスクリーンショット、実在人物の顔、他社ブランド色の再現（特に Google 4 色の組み合わせ）、公式キービジュアルの模倣 |
| 制作 | 画像生成 AI またはデザイナー制作（下記ブリーフ使用）。生成物に紛れた文字・ロゴ風の形は除去する |
| スタイル基準 | 既存 PASS 例：HeyGen / Perplexity の HERO（文字なし、ダーク基調） |

### 共通ネガティブ指示（生成時）

text, letters, words, logo, watermark, UI screenshot, brand colors of Google, real person face, trademark symbol

---

## 5. 命名規約（STEP 6）

- Logo：`omochix-ai-tool-{slug}-logo.webp`
- HERO：`omochix-ai-tool-{slug}-hero.webp`
- `{slug}` は `ai_tool` の post slug と完全一致させる（例：`kling-ai`、`claude-code`）
- **差し替え時も同じファイル名を使い、既存 attachment は上書きしない**
  - 新しい attachment として追加し、ID を差し替える
  - 同名ファイルが存在する場合、WordPress は `-1` などの接尾辞を付けるため、アップロード後に実ファイル名を記録する
- 版を上げるときは `-v2` を付ける（例：`omochix-ai-tool-chatgpt-hero-v2.webp`）
- 衝突確認：2026-10-01 時点で Media Library の `omochix-ai-tool` 該当は 0 件

---

## 6. 資産マニフェスト（STEP 5、17点）

共通値：

- Target Format：WebP
- WordPress Usage（Logo）：`tool_logo` メタ。`thumbnail` 150×150 で、詳細 80px・カード 64px 表示
- WordPress Usage（HERO）：featured image。詳細 HERO（`large`）、og:image、`SoftwareApplication.image`
- Replacement Required は MISSING と REPLACE の場合 `yes`

### L-01 Veo — Logo

- **Tool / Slug**：Veo / `veo`（post 678）
- **Asset Type**：Logo
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 公式素材取得（Google 許可確認後）
- **Official Source**：Google Brand Resource Center（guidance、products-and-services）。Veo 専用キットなし
- **Brand Notes**：Product icons は「Ask first」。Google のビジュアルアイデンティティ模倣は禁止。表記は「Veo」
- **Design Concept**：公式アイコンを無改変で配置（許可が得られた場合のみ）
- **Target Dimensions**：1024×1024（最低 512）
- **Filename**：`omochix-ai-tool-veo-logo.webp`
- **Alt Text**：Veo のロゴ
- **WordPress Usage**：共通値
- **Replacement Required**：yes（ただし許可確認が前提）
- **Risk / Notes**：許可が得られない場合は頭文字タイルのまま運用する

### L-02 Kling AI — Logo

- **Tool / Slug**：Kling AI / `kling-ai`（post 677）
- **Asset Type**：Logo
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 公式高解像度素材を Kling AI 公式窓口に依頼
- **Official Source**：kling.ai（brand / press ページなし。公式 CDN の apple-touch-icon は 180×180 のみ）
- **Brand Notes**：公開された利用条件なし。表記は「Kling AI」
- **Design Concept**：公式アイコンを無改変で使う
- **Target Dimensions**：1024×1024（最低 512）
- **Filename**：`omochix-ai-tool-kling-ai-logo.webp`
- **Alt Text**：Kling AI のロゴ
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：
  - 暫定案：公式ドメインの 180×180 apple-touch-icon を使う（`thumbnail` の 150px 表示なら足りる）
  - 暫定案は仕様の最低解像度を下回るため、運営者の承認が必要

### L-03 Runway — Logo

- **Tool / Slug**：Runway / `runway`（post 675）
- **Asset Type**：Logo
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 公式 zip のシンボルを使う
- **Official Source**：https://runway.com/brand-guidelines（`Runway-Brand-Guidelines.zip`）
- **Brand Notes**：
  - light 背景は黒、dark 背景は白
  - 狭い場所ではシンボル単体可、最小 24px
  - 表記は「Runway」
- **Design Concept**：黒シンボルを白プレート中央に無改変で配置（1枚で light / dark 両対応）。ワードマークは正方形に収まらないため使わない
- **Target Dimensions**：1024×1024
- **Filename**：`omochix-ai-tool-runway-logo.webp`
- **Alt Text**：Runway のロゴ
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：zip 内ガイドラインの clear space・背景色規定を制作前に確認する

### L-04 Midjourney — Logo

- **Tool / Slug**：Midjourney / `midjourney`（post 59）
- **Asset Type**：Logo
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → press@midjourney.com に公式素材を依頼。入手まで fallback
- **Official Source**：Midjourney Trademark Policy（公開ロゴキットなし）
- **Brand Notes**：
  - 改変・組み合わせは禁止
  - 自社名より目立たせない
  - 推奨・提携の示唆は禁止
  - 商標注記を推奨している
- **Design Concept**：公式素材を無改変で配置
- **Target Dimensions**：1024×1024
- **Filename**：`omochix-ai-tool-midjourney-logo.webp`
- **Alt Text**：Midjourney のロゴ
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：
  - 公式サイトの apple-touch-icon は低解像度で、利用許諾が不明確
  - 商標注記をどこに置くかは運営者が判断する（例：本文末尾）

### L-05 Claude Code — Logo

- **Tool / Slug**：Claude Code / `claude-code`（post 665）
- **Asset Type**：Logo
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → Anthropic 公式 press kit から Claude マークを使う
- **Official Source**：https://www.anthropic.com/news →「Download press kit」（press@anthropic.com）
- **Brand Notes**：press kit の内容は未検証。Claude Code 固有ロゴがなければ Claude マーク（Anthropic 公式）を使う
- **Design Concept**：公式マークを無改変で配置（不透明の正方形版があればそのまま使う）
- **Target Dimensions**：1024×1024
- **Filename**：`omochix-ai-tool-claude-code-logo.webp`
- **Alt Text**：Claude Code のロゴ
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：
  - 既存 `claude` ツールの Logo 添付を流用する案もある。ただしファイル名規約と製品識別のため別 attachment を推奨する
  - コミュニティ製マスコット画像は使わない

### L-06 Cursor — Logo

- **Tool / Slug**：Cursor / `cursor`（post 65）
- **Asset Type**：Logo
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 公式 brand zip の App icon（正方形・不透明）をそのまま使う
- **Official Source**：https://cursor.com/brand
- **Brand Notes**：
  - App icon は light / dark 版がある
  - 表記は「Cursor」（「Cursor AI」ではない）
- **Design Concept**：App icon をプレートなしで使う。OmochiX の light / dark 両タイルで見えることを確認して版を選ぶ
- **Target Dimensions**：1024×1024
- **Filename**：`omochix-ai-tool-cursor-logo.webp`
- **Alt Text**：Cursor ロゴ（Canary 指示により確定）
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：
  - 最もリスクが低い。先行 canary に最適
  - 2026-10-01 Canary：公式 `APP_ICON_25D_DARK.png` を 512×512 lossless WebP に縮小して準備済み
  - 格納先：`scratchpad/ai-tools/image-phase-v1/ready/cursor/`

### L-07 NotebookLM — Logo

- **Tool / Slug**：NotebookLM / `notebooklm`（post 659）
- **Asset Type**：Logo
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 公式素材取得（Google 許可確認後）
- **Official Source**：Google Brand Resource Center（NotebookLM 専用キットなし）
- **Brand Notes**：
  - 既存決定により NotebookLM ブランドのロゴを使う（Gemini Notebook への完全リブランドは後続フェーズ）
  - Product icons は「Ask first」
- **Design Concept**：公式アイコンを無改変で配置（許可が得られた場合のみ）
- **Target Dimensions**：1024×1024
- **Filename**：`omochix-ai-tool-notebooklm-logo.webp`
- **Alt Text**：NotebookLM のロゴ
- **WordPress Usage**：共通値
- **Replacement Required**：yes（許可確認が前提）
- **Risk / Notes**：ブランド名が移行中。移行後は `-v2` として差し替える

### L-08 ChatGPT — Logo

- **Tool / Slug**：ChatGPT / `chatgpt`（post 53）
- **Asset Type**：Logo
- **Current Status**：REPLACE
- **Current Attachment ID**：54
- **Current URL**：`/wp-content/uploads/2026/09/OAI_OpenAI-Blossom_Black.png`
- **Current Dimensions**：716×716 PNG（透過）
- **Decision**：REPLACE（dark タイルで不可視）
- **Official Source**：https://openai.com/brand/
- **Brand Notes**：
  - Blossom の着色・改変は禁止、提供されたまま使う
  - 推奨・提携の示唆は禁止
- **Design Concept**：
  - 公式の**黒 Blossom を白い正方形プレート中央に無改変で配置**（余白 20% 以上）
  - 公式の不透明 App icon 版が提供されていれば、そちらを優先する
- **Target Dimensions**：1024×1024
- **Filename**：`omochix-ai-tool-chatgpt-logo.webp`
- **Alt Text**：ChatGPT のロゴ
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：
  - 白プレートへの配置が「改変」に当たらないかは運営者が最終確認する（OpenAI の詳細ガイドラインはログインが必要）
  - 旧 ID 54 は削除しない

### H-01 Veo — HERO

- **Tool / Slug**：Veo / `veo`
- **Asset Type**：HERO
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし（プレースホルダー表示） / なし
- **Decision**：MISSING → 新規作成
- **Official Source**：（HERO は公式素材を使わない）
- **Brand Notes**：Google の 4 色配色・スパーク形状を模倣しない
- **Design Concept**：
  - 「映画的な動画生成」がテーマ
  - 暗いスタジオで光の粒子が集まり、連続するフィルムフレーム（中身は抽象的な風景）へ結晶化していく
  - 奥行きのあるシネマティックなライティング
  - サブアクセント：琥珀色
- **Target Dimensions / Format**：1280×720 / WebP
- **Filename**：`omochix-ai-tool-veo-hero.webp`
- **Alt Text**：光の粒子が映画のフレームへと変わっていく、Veo による動画生成をイメージした OmochiX オリジナルビジュアル
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：フレーム内に実在の映像作品に似たカットを入れない

### H-02 Kling AI — HERO

- **Tool / Slug**：Kling AI / `kling-ai`
- **Asset Type**：HERO
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 新規作成
- **Official Source**：（なし）
- **Brand Notes**：公式キービジュアルを模倣しない
- **Design Concept**：
  - 「動きの表現力（モーション）」がテーマ
  - 静止画の抽象的なシルエットから、流れるモーショントレイル（残像のリボン）が伸びていく
  - スピード感のある斜め構図
  - サブアクセント：シアン
- **Target Dimensions / Format**：1280×720 / WebP
- **Filename**：`omochix-ai-tool-kling-ai-hero.webp`
- **Alt Text**：静止画から滑らかな動きが生まれる様子を表現した、Kling AI の動画生成をイメージした OmochiX オリジナルビジュアル
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：人物シルエットは顔が判別できないものに限る

### H-03 Runway — HERO

- **Tool / Slug**：Runway / `runway`
- **Asset Type**：HERO
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 新規作成
- **Official Source**：（なし）
- **Brand Notes**：Runway の UI・タイムライン画面を複写しない
- **Design Concept**：
  - 「クリエイター向け映像制作スイート」がテーマ
  - 重なり合う半透明の映像レイヤー（抽象的なクリップのパネル）と、それを編み上げる光の軌跡
  - 編集スタジオの作業台を俯瞰したような editorial 構図
  - サブアクセント：マゼンタ
- **Target Dimensions / Format**：1280×720 / WebP
- **Filename**：`omochix-ai-tool-runway-hero.webp`
- **Alt Text**：重なり合う映像レイヤーを光がつなぐ、Runway の映像制作をイメージした OmochiX オリジナルビジュアル
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：パネル内に UI 文字を入れない

### H-04 Midjourney — HERO

- **Tool / Slug**：Midjourney / `midjourney`
- **Asset Type**：HERO
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 新規作成
- **Official Source**：（なし）
- **Brand Notes**：帆船モチーフ（公式ロゴ連想）を避ける
- **Design Concept**：
  - 「アート志向の画像生成」がテーマ
  - 霧の中からいくつもの画風（油彩・水彩・版画調）のキャンバスが浮かび上がるギャラリー空間
  - 絵画的な質感
  - サブアクセント：ローズゴールド
- **Target Dimensions / Format**：1280×720 / WebP
- **Filename**：`omochix-ai-tool-midjourney-hero.webp`
- **Alt Text**：霧の中に多様な画風のキャンバスが浮かぶ、Midjourney の画像生成をイメージした OmochiX オリジナルビジュアル
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：
  - 実在の画家・作品の模倣をしない
  - Midjourney で生成する場合は Midjourney の利用規約（有料プランの商用利用条件）を確認する

### H-05 Claude Code — HERO

- **Tool / Slug**：Claude Code / `claude-code`
- **Asset Type**：HERO
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 新規作成
- **Official Source**：（なし）
- **Brand Notes**：Anthropic / Claude のマーク・マスコットを使わない
- **Design Concept**：
  - 「ターミナルで動くエージェント型コーディング」がテーマ
  - 暗い作業空間に、読めない抽象的なコード行が段階的に組み上がる
  - ターミナル窓を思わせる枠と、タスクが連鎖するノード線
  - 落ち着いたクラフト感
  - サブアクセント：テラコッタ
- **Target Dimensions / Format**：1280×720 / WebP
- **Filename**：`omochix-ai-tool-claude-code-hero.webp`
- **Alt Text**：ターミナルの中でコードが段階的に組み上がる、Claude Code のエージェント型開発をイメージした OmochiX オリジナルビジュアル
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：読めるコード・コマンド文字列は入れない（ぼかし・抽象化）。Cursor HERO と差別化する（こちらはターミナルとタスク連鎖）

### H-06 Cursor — HERO

- **Tool / Slug**：Cursor / `cursor`
- **Asset Type**：HERO
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 新規作成
- **Official Source**：（なし）
- **Brand Notes**：Cursor エディタの UI を複写しない
- **Design Concept**：
  - 「AI ネイティブなコードエディタ」がテーマ
  - 大きく光るテキストキャレット（点滅カーソル）を中心に、抽象的なコードブロックが補完されるように並ぶ
  - 分割ペインを思わせる幾何学レイアウト
  - サブアクセント：エレクトリックブルー
- **Target Dimensions / Format**：1280×720 / WebP
- **Filename**：`omochix-ai-tool-cursor-hero.webp`
- **Alt Text**：光るカーソルを中心にコードブロックが補完されていく、Cursor の AI コードエディタをイメージした OmochiX オリジナルビジュアル
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：公式ロゴ（立方体）に似た形状を中心に置かない

### H-07 NotebookLM — HERO

- **Tool / Slug**：NotebookLM / `notebooklm`
- **Asset Type**：HERO
- **Current Status**：MISSING
- **Current Attachment ID / URL / Dimensions**：0 / なし / なし
- **Decision**：MISSING → 新規作成
- **Official Source**：（なし）
- **Brand Notes**：Google 配色・アイコン形状を模倣しない
- **Design Concept**：
  - 「資料に根ざしたリサーチノート」がテーマ
  - 複数の文書・PDF・ノートのシルエットが光の線でつながり、1冊のノートと音声波形（音声解説）に集約される
  - 知的で静かなトーン
  - サブアクセント：ミントグリーン
- **Target Dimensions / Format**：1280×720 / WebP
- **Filename**：`omochix-ai-tool-notebooklm-hero.webp`
- **Alt Text**：複数の資料がつながり 1冊のノートと音声にまとまる、NotebookLM のリサーチ支援をイメージした OmochiX オリジナルビジュアル
- **WordPress Usage**：共通値
- **Replacement Required**：yes
- **Risk / Notes**：文書シルエット上の文字は判読不能にする

### H-08 ChatGPT — HERO

- **Tool / Slug**：ChatGPT / `chatgpt`
- **Asset Type**：HERO
- **Current Status**：REPLACE
- **Current Attachment ID**：566
- **Current URL**：`/wp-content/uploads/2026/09/chatgpt-hero.png`
- **Current Dimensions**：1672×941 PNG
- **Decision**：REPLACE（UI モック・文字過多・モデル名が陳腐化）
- **Official Source**：（なし）
- **Brand Notes**：Blossom 形状を模倣しない。モデル名を入れない
- **Design Concept**：
  - 「汎用 AI アシスタントとの対話」がテーマ
  - 中央の柔らかな光源を囲むように、抽象的な会話バブルが広がる
  - 周囲に文章・画像・データ・コードを示す幾何学アイコン状のオブジェクトが浮かぶ
  - 親しみやすく明るいトーン
  - サブアクセント：ティール
- **Target Dimensions / Format**：1280×720 / WebP
- **Filename**：`omochix-ai-tool-chatgpt-hero.webp`
- **Alt Text**：中央の光を囲むように会話と多様なタスクが広がる、ChatGPT の対話型 AI をイメージした OmochiX オリジナルビジュアル
- **WordPress Usage**：共通値。og:image も新画像に切り替わる
- **Replacement Required**：yes
- **Risk / Notes**：旧 ID 566 は削除しない（rollback 用）

### H-09 Gemini — HERO

- **Tool / Slug**：Gemini / `gemini`
- **Asset Type**：HERO
- **Current Status**：REPLACE
- **Current Attachment ID**：636
- **Current URL**：`/wp-content/uploads/2026/09/3e41a5eaa32d59fd3d578ad3ae959e5a.png`
- **Current Dimensions**：1672×941 PNG
- **Decision**：REPLACE（文字過多、Google マーク含む）
- **Official Source**：（なし）
- **Brand Notes**：Google 4 色・Gemini スパーク形状を模倣しない
- **Design Concept**：
  - 「マルチモーダル AI」がテーマ
  - テキスト・画像・音声・動画を表す異なる素材感のストリーム（紙片、光のピクセル、波形、フィルム）が、1点に合流して調和する
  - サブアクセント：インディゴからバイオレットのグラデーション（OmochiX 紫系に寄せる）
- **Target Dimensions / Format**：1280×720 / WebP
- **Filename**：`omochix-ai-tool-gemini-hero.webp`
- **Alt Text**：テキスト・画像・音声・動画の流れが 1点に合流する、Gemini のマルチモーダル AI をイメージした OmochiX オリジナルビジュアル
- **WordPress Usage**：共通値。og:image も新画像に切り替わる
- **Replacement Required**：yes
- **Risk / Notes**：旧 ID 636 は削除しない

---

## 7. WordPress 反映計画（STEP 7、実行は別フェーズ・運営者承認後）

1. **制作・検品（ローカル）**
   - 対象：Logo 8 / HERO 9
   - 確認項目：
     - 寸法・形式・容量
     - light / dark 両タイルでの視認性（ローカル環境で確認）
     - 文字・ロゴ混入がないこと
   - Google 系 Logo（Veo・NotebookLM）は許可確認が完了したものだけを対象にする
2. **Media Library へアップロード（wp-admin、手動）**
   - 命名規約どおりのファイル名でアップロードし、alt に §6 の Alt Text を入れる
   - 既存 attachment（54 / 566 / 634 / 636）は**変更・削除しない**
3. **attachment ID 記録**
   - 実ファイル名（接尾辞の有無）と ID を本マニフェストの追補表に記録する
4. **Content Updater JSON 作成**
   - 画像専用の JSON を作る。1件の書式：`{"slug": "...", "tool_logo_attachment_id": N, "featured_image_attachment_id": M}`
   - `title` キーを含めない（含めるとエラー）
   - 変更しない画像のキーは省略する（`0` は不可）
5. **Dry Run**
   - 差分が `tool_logo_attachment_id` / `featured_image_attachment_id` だけであることを確認する
6. **Canary Apply**
   - 対象：`cursor` 1件（公式素材が最も明確）
   - public 監査で確認する項目：
     - 詳細ページ Logo（light / dark）と HERO
     - カード
     - og:image
     - `SoftwareApplication.image`
7. **Batch Apply**
   - 残りのツールを適用する
   - 公式素材が揃っていない Logo は JSON から**キーごと省略**し、HERO だけ反映する
8. **Production read-only audit**
   - REST と HTML で ID、表示、og:image を確認する
   - Updater の before snapshot JSON を保存する

### Rollback

- 画像の変更（`update_post_meta` / `set_post_thumbnail`）は投稿リビジョンに残らない
- そのため、Apply 前の snapshot（`tool_logo` と `featured_media` を含む）の旧 ID を使って Updater で戻す
  - 旧 ID がある ChatGPT / Gemini：旧 ID を指定して戻す
  - MISSING だったもの：旧 ID が `0` で、`0` は Updater では指定不可。wp-admin で個別に「アイキャッチを削除」または Logo を解除する

### Content Updater 互換性（コード確認済み）

`admin/ai-tool-content-updater.php` で確認した内容：

- PATCH semantics：キーを省略すると変更なし
- 画像 ID の検証：
  - 1以上の int であること
  - `get_post_type === 'attachment'`
  - `wp_attachment_is_image`
- 差分表示：現 ID から新 ID
- 反映処理：
  - Logo は `update_post_meta('tool_logo')`
  - HERO は `set_post_thumbnail`
- **画像だけを更新する JSON は本文・SEO に影響しない**
- WebP：production に WebP 添付（ID 634）がすでにあるため、アップロードと `wp_attachment_is_image` はどちらも問題なし

---

## 8. 52ツール・将来ツールへの拡張（STEP 8）

- 本マニフェストの項目構成・命名規約・Logo ポリシー・HERO 共通仕様は slug だけに依存する（テーマ・プラグインにツール固有の分岐は不要）
- 残りの 43 ツール（Logo・HERO がどちらもないもの）は、同じテンプレートで Batch 単位のマニフェスト（`AI-Tools-Image-Manifest-v{n}.md`）を作る
- 機械可読版：`scratchpad/ai-tools/image-phase-v1/image-manifest-v1.json`
  - 17点の主要項目を格納
  - Updater JSON 生成の入力に使える
- ツールごとの手順：
  1. 公式ブランドページ調査
  2. 素材区分を判定：A 公式キット／B 許可制／C 公式素材なし
  3. HERO モチーフとサブアクセント色を決める（既存と重複させない）
  4. 命名規約に従って作成
  5. §7 の手順で反映する
- サブアクセント色の割当表（重複回避用）：

| アクセント | ツール |
|---|---|
| 琥珀 | veo |
| シアン | kling-ai |
| マゼンタ | runway |
| ローズゴールド | midjourney |
| テラコッタ | claude-code |
| エレクトリックブルー | cursor |
| ミント | notebooklm |
| ティール | chatgpt |
| インディゴ〜バイオレット | gemini |

- 提供終了ツール（例：sora）は HERO を新規作成しない。優先度を下げる

---

## 9. リスク

1. **Google 系ロゴの利用許可**
   - Veo / NotebookLM は Product icons「Ask first」区分の可能性がある
   - 既存の Gemini Logo（ID 634）も同じ確認が必要
2. **公式高解像度素材がない**
   - Kling AI / Midjourney は、入手まで fallback タイルで運用する
3. **Anthropic press kit の内容が未検証**
   - ブラウザのナビゲーションが拒否されたため、中身を確認できていない
4. **OpenAI の詳細ガイドラインはログインが必要**
   - 白プレート配置の可否は運営者が確認する
5. **HERO 生成物の混入リスク**
   - 文字・ロゴ風の形、実在人物、既存作品の模倣が紛れ込む可能性があるため、検品必須
6. **og:image の変化**
   - HERO 反映により SNS シェア画像が変わる（SNS 側キャッシュは即時更新されない）
7. **Rollback の制約**
   - 画像 rollback はリビジョンに頼れない。snapshot の保存が必須
8. **テーマの Logo 表示仕様**
   - 1枚の画像で light / dark 両対応するため、透過単色マークは使えない
