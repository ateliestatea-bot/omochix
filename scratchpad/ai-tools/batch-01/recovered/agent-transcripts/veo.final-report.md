Good, 81 chars — within range. Now compiling the final report.

## 1. Sources list

**Official product**
- https://deepmind.google/models/veo/ — Veo overview, model versions, capabilities, access surfaces, SynthID (Research date: 2026-09-30)

**Pricing**
- https://ai.google.dev/gemini-api/docs/pricing — Veo 3.1 Standard/Fast/Lite per-second pricing (Research date: 2026-09-30)
- https://gemini.google/subscriptions/ — Gemini app plan names, JPY prices, Flow credit allotments per plan, Veo access by plan (Research date: 2026-09-30)

**Docs**
- https://ai.google.dev/gemini-api/docs/veo — Model IDs, SDKs, resolution/duration/aspect-ratio limits, input types, prompt-language note (Research date: 2026-09-30)
- https://support.google.com/labs/answer/16353544 — Google Flow supported languages/countries, Japan credit-purchase restriction (Research date: 2026-09-30)
- https://cloud.google.com/terms/genai-preview-products (and its archived revisions, e.g. genai-preview-products-20240409) — Generative AI Preview Products commercial/production-use restriction language (fetch was truncated; content corroborated via search index of the same official domain) (Research date: 2026-09-30)

**API**
- https://ai.google.dev/gemini-api/docs/veo (see above)
- https://ai.google.dev/gemini-api/docs/pricing (see above)

**Release notes / announcement**
- https://blog.google/intl/es-419/actualizaciones-de-producto/informacion/gemini-omni/ — Official Google blog announcement of "Gemini Omni" (I/O 2026), relevant context for how Veo's consumer-facing role is shifting (Research date: 2026-09-30)

Note: I could not get an un-truncated fetch of `cloud.google.com/vertex-ai/generative-ai/pricing`, so exact Vertex AI (as opposed to Gemini API) per-second figures are not independently confirmed from that specific page — I relied on the Gemini API official pricing page instead and flagged this gap below.

## 2. JSON object

```json
{
  "slug": "veo",
  "short_description": "Google DeepMindの動画生成AI「Veo」。テキストや画像から音声付き動画を生成でき、Gemini API・Vertex AIなど複数の経路で使える。",
  "post_content": "<h2>Veoとは？</h2><p>Veoは、Google DeepMindが開発する動画生成AIモデルです。テキストや画像から音声付きの動画を生成でき、開発者向けのGemini API・Vertex AI、クリエイター向けのGoogle Flow、一般ユーザー向けのGemini アプリなど、複数の経路で提供されています。現行の中心モデルはVeo 3.1で、旧世代のVeo 3（veo-3.0-generate-001など）は非推奨（deprecated）として扱われています。</p><h2>Veoでできること</h2><p>Veoはテキストプロンプトからの動画生成に加え、画像を起点とした動画化、最初と最後のフレームを指定した動画生成、参照画像を使ったキャラクターやスタイルの一貫性維持、動画の延長生成など、映像制作を意識した機能を備えています。生成される動画には効果音・環境音・セリフなどの音声もあわせて含めることができます。</p><h3>主要モデル・主要機能</h3><ul><li>Veo 3.1（Standard）：最も高品質な出力。720p/1080p/4Kに対応</li><li>Veo 3.1 Fast：Standardよりも高速・低コストな生成。720p/1080p/4Kに対応</li><li>Veo 3.1 Lite：軽量版。720p/1080pに対応（4Kは非対応）</li><li>Veo 3・Veo 3 Fast：旧世代モデルで、現在は非推奨（deprecated）表記</li></ul><p>動画の長さは4秒・6秒・8秒から選択でき、1080pや4K、参照画像を使う場合は8秒固定となります。アスペクト比は16:9（横）または9:16（縦）に対応しています。</p><h2>日本語で使える？</h2><p>Gemini アプリおよびGoogle Flowの画面（UI）は日本語に対応しており、Googleの公式ヘルプセンターにも日本語版のサポート記事が用意されています。一方、Gemini APIの公式ドキュメントでは「英語（EN）のプロンプトは十分にサポートされているが、他言語での評価は行われていない」と明記されており、日本語プロンプトを使った場合の出力品質は英語と同等であるとは保証されていません。開発者向けのAPIドキュメントやコンソールは基本的に英語中心です。</p><h2>Veoの料金</h2><p>Veoの料金体系は利用経路によって異なります。Gemini API経由では、生成した動画の秒数に応じた従量課金制です（すべて米ドル建て、音声込みの価格）。</p><h3>各プラン</h3><ul><li>Veo 3.1 Standard：720p/1080pが1秒あたり0.40ドル、4Kが1秒あたり0.60ドル</li><li>Veo 3.1 Fast：720pが1秒あたり0.10ドル、1080pが0.12ドル、4Kが0.30ドル</li><li>Veo 3.1 Lite：720pが1秒あたり0.05ドル、1080pが0.08ドル（4K非対応）</li></ul><p>Gemini APIには動画生成専用の無料枠は用意されていません。Vertex AI（Google Cloud）経由の企業向け価格は別体系となる場合がありますが、公式サイトの詳細な価格表は今回の調査では確認できませんでした。</p><p>消費者向けのGemini アプリでは、無料プランにVeoは含まれません。Google AI Pro（月額2,900円）ではVeo 3.1 Liteの限定的な試用が可能で、Google AI Ultra（月額14,500円〜32,000円、利用量プランにより異なる）ではVeo 3.1のフル機能が利用できます。これらの有料プランにはGoogle Flow用のクレジットも付与されますが、Flow単体でのAIクレジット追加購入は日本では現時点で提供されていません（2026年9月30日確認）。</p><h2>Veoの使い方</h2><ol><li>Gemini API・Vertex AI・Google AI Studio・Gemini アプリ・Flowのいずれかの利用経路を選ぶ</li><li>開発者の場合はGoogle AI StudioまたはGoogle Cloudコンソールでプロジェクトを作成し、APIキーを取得する</li><li>テキストプロンプト、または起点となる画像・参照画像を用意する</li><li>動画の長さ（4/6/8秒）、解像度（720p/1080p/4K）、アスペクト比（16:9または9:16）を指定する</li><li>generateContent APIを呼び出す、またはGemini アプリ/Flowの画面から生成を実行する</li><li>生成された動画（SynthID透かし入り）を確認し、必要に応じて延長やフレーム指定で調整する</li></ol><h2>Veoの活用例</h2><ul><li>SNS向けの短尺動画やプロモーション映像の制作</li><li>広告・マーケティング映像のプロトタイプ作成</li><li>アプリやサービスへの動画生成機能の組み込み（Gemini API/Vertex AI経由）</li><li>映像制作におけるプリビジュアライゼーション（絵コンテ代わりの試作映像）</li><li>音声付き動画コンテンツの制作</li></ul><h2>Veoのメリット</h2><ul><li>映像と音声（効果音・環境音・セリフ）を同時に生成できる</li><li>Standard・Fast・Liteと用途に応じてモデルを選択でき、品質とコストのバランスを調整しやすい</li><li>Python・JavaScript・Go・Java・REST APIなど主要な開発言語のSDKが揃っている</li><li>Google Cloud（Vertex AI）や他のGoogle製品と組み合わせた開発がしやすい</li><li>SynthIDによる電子透かしなど、AI生成物の識別に向けた仕組みが標準で組み込まれている</li></ul><h2>Veoの注意点</h2><ul><li>API・Vertex AI経由の料金は秒単位の従量課金で、4KやStandardモードを多用すると1本あたりのコストが高くなりやすい</li><li>Gemini アプリの無料プランではVeoを利用できず、Pro以上の有料プランが前提となる</li><li>Veo 3.1の各モデルIDは現時点で「preview」表記であり、Googleの生成AIプレビュー製品向け利用規約の対象となる可能性がある。商用利用の可否や条件は自社の利用形態（Preview/GA、消費者向け/開発者向け）によって異なるため、利用前に最新の利用規約を必ず確認することが推奨される</li><li>短尺のセリフなど自然な発話同期は、Google自身が「発展途上の領域」としている</li><li>プロンプト評価は英語が中心とされ、日本語プロンプトでの出力品質は保証されていない</li></ul><h2>どんな人におすすめ？</h2><p>動画生成APIを自社サービスやプロダクトに組み込みたい開発者・エンジニア、Google Cloud（Vertex AI）を既に利用している企業の映像制作チーム、音声付きの短尺動画をGemini アプリやFlowで手軽に作りたいクリエイターに向いています。一方、無料で動画生成を継続的に使いたいユーザーや、予算を月額固定費として厳密に管理したいチームには、従量課金・クレジット制が中心のVeoは合いにくい可能性があります。</p><h2>FAQ</h2><p><strong>Q. Veoは無料で使えますか？</strong></p><p>A. Gemini アプリの無料プランにはVeoは含まれません。Google AI Pro以上の有料プラン、またはGemini API/Vertex AIの従量課金での利用が必要です（一時的な無料お試しキャンペーンが実施される場合はあります）。</p><p><strong>Q. Veoで生成した動画は商用利用できますか？</strong></p><p>A. Vertex AI（GA提供）経由の生成物については、Googleの利用規約上、生成物（Generated Output）は顧客データとして扱われ、Googleは所有権を主張しないとされています。ただし、Veo 3.1の各モデルは現時点で「preview」表記であり、Googleの生成AIプレビュー製品向け利用規約が適用される可能性があるため、商用・本番利用の可否は最新の利用規約を確認する必要があります。</p><p><strong>Q. 日本語のプロンプトでも動画は作れますか？</strong></p><p>A. 日本語プロンプトの入力自体は可能ですが、公式ドキュメントでは英語プロンプトを推奨しており、日本語プロンプトでの出力品質は英語と同等であるとは保証されていません。</p><p><strong>Q. Veo 3とVeo 3.1の違いは何ですか？</strong></p><p>A. Veo 3（veo-3.0-generate-001など）は旧世代モデルで、現在は非推奨（deprecated）とされています。Veo 3.1は後継モデルで、参照画像によるスタイル一貫性や動画延長など、より細かい演出コントロール機能が追加されています。</p>",
  "key_features": [
    "音声付き動画生成：効果音・環境音・セリフを含む動画をテキストや画像から生成できる",
    "複数の画質モード：Standard・Fast・Liteの3モデルがあり、720p/1080p/4Kの解像度に対応する（4KはStandardとFastのみ）",
    "動画の延長・フレーム制御：最初と最後のフレーム指定や動画の延長生成など、細かい演出コントロールが可能",
    "参照画像によるスタイル一貫性：最大3枚の参照画像でキャラクターやスタイルの一貫性を保った生成ができる",
    "複数の利用経路：Gemini API・Vertex AI・Google AI Studio・Flow・Gemini アプリ・Google Vidsなど用途に応じて選べる",
    "SynthID電子透かし：生成された動画すべてに目に見えないAI生成コンテンツ識別用の透かしが埋め込まれる",
    "開発者向けSDK対応：Python・JavaScript・Go・Java・REST APIでの呼び出しに対応する"
  ],
  "pros": [
    "音声・映像を同時生成でき、効果音やセリフ付きの動画を1回の生成で作成できる",
    "Standard/Fast/Liteと用途に応じたモデルを選べ、コストと品質のバランスを調整しやすい",
    "Python/JS/Go/Java/RESTなど主要言語のSDKが揃い、既存の開発パイプラインに組み込みやすい",
    "SynthIDによる透かしなど、AI生成物の識別に向けた安全対策が標準で組み込まれている"
  ],
  "cons": [
    "API/Vertex AI利用は秒単位の従量課金で、4K・Standardモードは1本あたりのコストが高くなりやすい",
    "Gemini アプリの無料プランではVeoを利用できず、Pro以上の有料プラン加入が前提となる",
    "Veo 3.1の各モデルIDは現時点で「preview」表記であり、商用利用の可否はGoogleの利用規約上の位置付けに左右される",
    "プロンプトの評価は英語が中心とされ、日本語プロンプトでの出力品質は英語と同等とは限らない"
  ],
  "strengths": [
    "GoogleのGemini/Vertex AIエコシステムに統合されており、他のGoogle製品と組み合わせた開発がしやすい",
    "音声込みで動画を一括生成できる数少ない主要モデルの一つで、対話・環境音を含む表現に強い",
    "エンタープライズ向け（Vertex AI）と消費者向け（Gemini アプリ/Flow）の両方に対応する幅広い提供形態を持つ"
  ],
  "weaknesses": [
    "短尺セリフなど自然な発話同期は発展途上の領域とGoogle自身が明言している",
    "料金体系が従量課金・クレジット制など複数の方式に分かれ、月額固定費として見積もりにくい",
    "消費者向けFlowでのAIクレジット追加購入は日本など一部地域で制限がある"
  ],
  "recommended_for": [
    "動画生成APIを自社サービスに組み込みたい開発者・エンジニア",
    "音声付きショート動画をGemini アプリやFlowで手軽に作りたいクリエイター",
    "Google Cloud/Vertex AIを既に利用している企業の映像制作チーム"
  ],
  "recommended_use_cases": [
    "SNS向けショート動画制作",
    "広告・プロモーション映像の試作",
    "アプリ・サービスへの動画生成機能組み込み",
    "映像制作のプリビジュアライゼーション",
    "音声付き動画コンテンツ制作"
  ],
  "not_recommended_for": [
    "無料で動画生成を継続的に使いたいユーザー",
    "厳密な日本語の自然な発話・リップシンクが必須の制作",
    "予算を月額固定費として厳密に管理したいチーム（従量課金のため変動しやすい）"
  ],
  "pricing_details": "VeoはGemini API経由の場合、生成した動画の秒数に応じた従量課金制で、価格はモデルにより異なる(すべて米ドル建て、音声込みの価格、ai.google.dev公式価格表より2026年9月30日確認)。Veo 3.1 Standardは720p/1080pが1秒あたり0.40ドル、4Kが1秒あたり0.60ドル。Veo 3.1 Fastは720pが0.10ドル、1080pが0.12ドル、4Kが0.30ドル。Veo 3.1 Liteは720pが0.05ドル、1080pが0.08ドル(4K非対応)。Gemini APIに動画生成専用の無料枠は用意されていない。Vertex AI経由の企業向け価格は別体系となる場合があるが、公式ページの詳細な価格表(cloud.google.com)は今回truncateされ確認できなかった。消費者向けのGemini アプリでは、Veoの直接利用は無料プランには含まれず、Google AI Pro(月額2,900円)でVeo 3.1 Liteの限定的な試用、Google AI Ultra(月額14,500円〜32,000円、利用量プランにより異なる)でVeo 3.1のフル機能が利用できる(gemini.google/subscriptions確認)。これらのプランにはGoogle Flow用クレジットも付与されるが、Flow単体でのAIクレジット追加購入は日本では現時点で提供されていない。",
  "api_sdk_info": "Veoは Gemini API(ai.google.dev)およびVertex AI(Google Cloud)を通じて開発者向けAPIが提供されている。モデルIDはveo-3.1-generate-preview(Standard)、veo-3.1-fast-generate-preview(Fast)、veo-3.1-lite-generate-preview(Lite)で、いずれも現時点では「preview」表記。旧世代のveo-3.0-generate-001・veo-3.0-fast-generate-001は非推奨(deprecated)とされている。公式SDKはPython(google-genai)、JavaScript(@google/genai)、Go(google.golang.org/genai)、Java(com.google.genai)に加えREST APIでの利用も可能。generateContent APIを通じてテキストから動画、画像を起点とした動画化、最初と最後のフレーム指定、参照画像(最大3枚)によるスタイル一貫性、動画延長などの機能を呼び出せる。プロンプトは最大1,024トークン、動画の長さは4/6/8秒、アスペクト比は16:9または9:16に対応する(1080pや4K、参照画像利用時は8秒固定)。ドキュメント上「英語(EN)のプロンプトは十分にサポートされているが、他言語での評価は行われていない」との記載があり、日本語プロンプトでの出力品質は保証されていない。詳細はai.google.dev/gemini-api/docs/veo参照。",
  "security_info": "すべての生成動画にはGoogle DeepMindのSynthID技術による、人の目には見えないAI生成コンテンツ識別用の電子透かしが埋め込まれる(deepmind.google公式ページより)。同ページによると、有害なリクエストは生成前にブロックされるほか、安全性評価や既存コンテンツの記憶(メモライゼーション)チェックが行われているとされる。EU・英国・スイス・MENA地域では人物生成設定が「allow_adult」のみに制限されるなど、地域ごとに生成内容の制限が異なる(ai.google.dev公式ドキュメントより)。",
  "notes": "2026年5月のGoogle I/O 2026で、Google DeepMindは動画生成・編集の新モデル「Gemini Omni」を発表した(blog.google公式発表)。Gemini Omni(第一弾はGemini Omni Flash)はGemini アプリ・Flow・YouTube Shortsなど消費者向け面での動画生成・編集を担う新モデルとして展開が始まっており、今後Veoとの役割分担・統合が進む可能性がある。一方、Gemini API・Vertex AI・Google AI Studioの開発者向け経路では、2026年9月30日時点でVeo 3.1(Standard/Fast/Lite)が引き続き提供されている。本ページはVeo(開発者向けAPI・Vertex AI・Gemini アプリの上位プランでの提供)を対象としており、Gemini Omniは別モデルとして扱う。今後Gemini Omniへの統合状況を継続的に確認する必要がある。",
  "supported_devices": ["Web", "iOS", "Android"],
  "supported_models": ["Veo 3.1 (Standard)", "Veo 3.1 Fast", "Veo 3.1 Lite", "Veo 3 (レガシー・非推奨)", "Veo 3 Fast (レガシー・非推奨)"],
  "integrations": ["Gemini API", "Vertex AI", "Google AI Studio", "Google Flow", "Google Vids", "Gemini Enterprise Agent Platform"],
  "has_free_plan": false,
  "api_available": "yes",
  "commercial_use": "partial",
  "japanese_support": "full",
  "info_checked_date": "2026-09-30",
  "omochix_view": "VeoはGoogleのGemini/Vertex AIエコシステムに深く組み込まれた動画生成AIで、開発者向けAPIとしての完成度と、音声込みで動画を一括生成できる表現力の高さが強みと言える。Runway・Sora・Klingなど競合の動画生成AIと比べると、Google Cloudの他サービスと組み合わせた本格的なプロダクト開発に向いている一方、個人が気軽に無料で試せるツールではない点は差別化ポイントであると同時に参入障壁にもなりうる。2026年5月に発表された「Gemini Omni」が消費者向け動画生成・編集の主軸になりつつあり、今後Veoは開発者・エンタープライズ向けの位置付けがより明確になっていく可能性がある。既にGoogle Cloudを利用している開発チームや、音声付き動画をAPI経由で量産したい事業者には特に相性が良いツールと言えそうだ。",
  "seo_title": "Veoとは？料金・機能・使い方・日本語対応を解説｜OmochiX",
  "meta_description": "Google DeepMindの動画生成AI「Veo」を解説。Gemini API・Vertex AI・Gemini アプリでの使い方、料金体系、日本語対応状況、商用利用の注意点をOmochiXがまとめました。"
}
```

## 3. Research gaps (could not confirm / marked unknown or omitted)

- **Vertex AI exact per-second pricing**: `cloud.google.com/vertex-ai/generative-ai/pricing` repeatedly returned truncated content; I relied on the Gemini API pricing page (ai.google.dev) instead. It's plausible but not independently confirmed that Vertex AI enterprise rates match the Gemini API figures exactly — flagged in `pricing_details`.
- **Commercial use status is genuinely unresolved**: Veo 3.1's model IDs are all suffixed `-preview`, which per Google Cloud's "Generative AI Preview Products" terms may restrict commercial/production use "unless permitted by Google in writing" — but I could not fetch the full current terms text (truncated) to confirm whether Veo 3.1 specifically is carved out or covered. Set `commercial_use: "partial"` rather than "yes" for this reason.
- **"Gemini Omni" overlap with Veo**: Google announced Gemini Omni in May 2026 as a new model rolling out to Gemini app/Flow/YouTube Shorts for video generation/editing. It's unclear from official sources how much this has already displaced Veo in consumer surfaces vs. coexisting with it as of 2026-09-30 — flagged in `notes` rather than guessed at.
- **Exact Veo generation counts per Gemini app plan** (e.g., how many Veo 3.1 videos Pro/Ultra actually allow per month) were not confirmed with precise numbers — omitted from the JSON rather than estimated.
- **Vertex AI regional availability list and rate limits** for Veo were referenced in docs but specific numbers/regions were not returned in full — omitted.
- **Customer-support responsiveness in Japanese** (e.g., live support quality, not just UI localization) was not verifiable from official sources — `japanese_support: "full"` is based on confirmed UI localization (Gemini app, Flow) and existence of official Japanese help articles, not on direct verification of support-ticket handling quality.