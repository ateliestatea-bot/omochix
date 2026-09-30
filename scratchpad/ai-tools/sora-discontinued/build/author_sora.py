#!/usr/bin/env python3
"""Author the Sora discontinued-archive update (Content Updater input).

Every fact comes from OpenAI's own pages, checked 2026-10-01:
- https://help.openai.com/en/articles/20001152-what-to-know-about-the-sora-discontinuation
- https://developers.openai.com/api/docs/deprecations  (2026-03-24 entry)
- https://sora.com/  -> sora.chatgpt.com/sunset
- https://openai.com/index/sora-is-here/  (2024-12-09)
- https://openai.com/index/sora-2/        (2025-09-30)
Alternative-tool one-liners reuse the Batch 01 verified content.

Deliberately NOT included (they would render "current tool" UI on the page):
key_features ("Soraでできること" cards), pros/cons/strengths/weaknesses
("選ぶ前に知っておきたいこと"), recommended_*/not_recommended_for
("おすすめの使い方"), supported_devices/supported_models/integrations
(Quick Summary "対応デバイス/対応モデル"). They are empty in production and stay
empty (absent key = unchanged). commercial_use stays "unknown" (absent).
"""
import json
import os

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def html(*blocks):
    return "\n".join(blocks)


sora = {
    "slug": "sora",
    "short_description": "OpenAIが提供していた動画生成AI。Web版・アプリ版は2026年4月26日、APIは2026年9月24日に提供を終了し、現在は新たに利用できない。",
    "post_content": html(
        "<h2>Soraとは？</h2>",
        "<p>Soraは、OpenAIが提供していた動画生成AIです。<strong>2026年4月26日にWeb版とアプリ版、2026年9月24日にAPIの提供が終了しており、現在は新たに動画を生成することはできません。</strong>このページは、Soraがどのようなサービスだったかと、提供終了後に利用者ができることをまとめた記録です。</p>",
        "<h2>どんなサービスだったか</h2>",
        "<p>SoraはテキストなどからAIが動画を生成するサービスで、OpenAIの公式発表によると、次のような経緯で提供されていました。</p>",
        "<ul>",
        "<li><strong>2024年2月</strong> — 動画生成モデル「Sora」が発表される</li>",
        "<li><strong>2024年12月9日</strong> — 高速化した「Sora Turbo」を搭載し、sora.comでChatGPT PlusとProのユーザー向けに単独の製品として提供開始</li>",
        "<li><strong>2025年9月30日</strong> — 音声も生成できる「Sora 2」と、iOS向けのソーシャルアプリ「Sora」を公開（招待制、米国とカナダから提供開始）</li>",
        "<li><strong>2026年3月24日</strong> — APIで提供していたSora 2のモデルとVideos APIの廃止を開発者に告知</li>",
        "<li><strong>2026年4月26日</strong> — Web版とアプリ版の提供が終了</li>",
        "<li><strong>2026年9月24日</strong> — APIの提供が終了</li>",
        "</ul>",
        "<h2>Soraの提供終了について</h2>",
        "<h3>Web・アプリの提供終了</h3>",
        "<p>OpenAIの公式ヘルプによると、SoraのWeb版とアプリ版は2026年4月26日に提供を終了しました。現在sora.comにアクセスすると、Soraはもう利用できないことと、データのエクスポートは引き続き可能であることが案内されます。</p>",
        "<h3>APIの提供終了</h3>",
        "<p>開発者向けには、Videos APIとSora 2の動画生成モデルが2026年9月24日にAPIから削除されました。OpenAIの廃止モデル一覧では、これらの推奨される代替モデルは示されていません。</p>",
        "<h2>提供されていた主な機能</h2>",
        "<p>OpenAIの発表によると、Soraでは次のような機能が提供されていました。</p>",
        "<ul>",
        "<li><strong>動画の生成</strong> — テキスト、画像、動画を入力に動画を生成できた（2024年12月の提供開始時は最大1080p・最大20秒、横長・縦長・正方形に対応）</li>",
        "<li><strong>拡張・リミックス・ブレンド</strong> — 手持ちの素材を延長したり、作り替えたり、組み合わせたりできた</li>",
        "<li><strong>ストーリーボード</strong> — フレームごとに入力を指定して動画を組み立てられた</li>",
        "<li><strong>音声の生成</strong> — Sora 2では、会話の同期や効果音を含む動画を生成できた</li>",
        "<li><strong>キャラクター</strong> — アプリで短い動画と音声を一度記録し、自分の姿をSoraの動画に登場させられた（利用できる相手は本人が管理）</li>",
        "<li><strong>フィード</strong> — ほかのユーザーの作品を見たり、リミックスしたりできた</li>",
        "</ul>",
        "<h2>対応していたモデル</h2>",
        "<ul>",
        "<li><strong>Sora</strong> — 2024年2月に発表された最初の動画生成モデル</li>",
        "<li><strong>Sora Turbo</strong> — 2024年12月の製品提供開始時に搭載された高速版</li>",
        "<li><strong>Sora 2</strong> — 2025年9月に公開された、音声も生成できる動画・音声生成モデル</li>",
        "<li><strong>API</strong> — <code>sora-2</code>、<code>sora-2-pro</code>と各スナップショット（いずれも2026年9月24日に削除）</li>",
        "</ul>",
        "<h2>料金・クレジットの扱い</h2>",
        "<p>2024年12月の提供開始時、SoraはChatGPT Plusでは追加料金なしで利用でき、Proではより多くの利用量や高い解像度、長い動画が利用できました。ChatGPTのTeam・Enterprise・Eduには含まれていませんでした。</p>",
        "<p>提供終了後、新たに契約や購入をすることはできません。OpenAIの公式ヘルプによると、購入済みのChatGPT／Soraのクレジットは、希望すればCodexで利用できます。返金については、ChatGPTのサブスクリプションの返金手順を案内しています。</p>",
        "<h2>提供終了後にできること</h2>",
        "<h3>データのエクスポート</h3>",
        "<p>Soraで作成したコンテンツは、提供終了後もエクスポートできると案内されています。</p>",
        "<ol>",
        "<li><strong>終了ページを開く</strong> — sora.chatgpt.com/sunset にアクセスします。</li>",
        "<li><strong>エクスポートを実行</strong> — 「Export」をクリックします。</li>",
        "<li><strong>メールを確認</strong> — 準備ができるとメールで通知されます。</li>",
        "</ol>",
        "<p>最終的なエクスポート期間が設けられる場合は、事前にメールで通知されます。その期間が過ぎると、Soraの利用に関するデータは完全に削除されるため、OpenAIはできるだけ早いエクスポートを勧めています。</p>",
        "<h3>返金・クレジット</h3>",
        "<p>未使用のChatGPT／SoraクレジットはCodexで使えます。返金を希望する場合は、OpenAIヘルプのChatGPTサブスクリプションの返金手順を確認してください。</p>",
        "<h2>代わりに検討できる動画生成AI</h2>",
        "<p>OpenAIからSoraの後継となる動画生成サービスは案内されていません。現在提供されている動画生成AIとしては、次のようなツールがあります。料金や機能は各ページで確認してください。</p>",
        "<ul>",
        "<li><a href=\"/ai-tools/veo/\">Veo</a> — Google DeepMindの動画生成AI。音声付きの動画を生成でき、Gemini API・Vertex AIやGeminiアプリの有料プランで利用できる</li>",
        "<li><a href=\"/ai-tools/runway/\">Runway</a> — 動画・画像・音声の生成と編集を1つの環境で行える制作プラットフォーム。Freeプランで試せる</li>",
        "<li><a href=\"/ai-tools/kling-ai/\">Kling AI</a> — Kuaishou Technologyの動画・画像生成AI。モーションコントロールなどの演出機能があり、無料のBasicプランもある</li>",
        "</ul>",
        "<h2>FAQ</h2>",
        "<p><strong>Q. Soraは今も使えますか？</strong></p>",
        "<p>A. 使えません。Web版とアプリ版は2026年4月26日、APIは2026年9月24日に提供を終了しました。</p>",
        "<p><strong>Q. Soraで作った動画は取り出せますか？</strong></p>",
        "<p>A. sora.chatgpt.com/sunset の「Export」からエクスポートできると案内されています。最終的なエクスポート期間の後はデータが削除されるため、早めの対応が勧められています。</p>",
        "<p><strong>Q. Sora APIは使えますか？</strong></p>",
        "<p>A. 使えません。Videos APIと <code>sora-2</code>・<code>sora-2-pro</code> などのモデルは、2026年9月24日にAPIから削除されました。</p>",
        "<p><strong>Q. 購入したクレジットはどうなりますか？</strong></p>",
        "<p>A. 購入済みのChatGPT／Soraクレジットは、希望すればCodexで利用できます。返金はChatGPTのサブスクリプションの返金手順が案内されています。</p>",
        "<p><strong>Q. Soraの後継サービスはありますか？</strong></p>",
        "<p>A. 2026年10月1日時点で、OpenAIから後継となる動画生成サービスは案内されていません。</p>",
    ),
    "pricing_details": (
        "提供終了のため、新たな契約や購入はできない。"
        "2024年12月の提供開始時、SoraはChatGPT Plusでは追加料金なしで利用でき、Proではより多くの利用量・高い解像度・長い動画が利用できた（ChatGPT Team・Enterprise・Eduには含まれていなかった）。"
        "購入済みのChatGPT／Soraクレジットは、希望すればCodexで利用できる。返金はChatGPTのサブスクリプションの返金手順が案内されている。"
        "（2026年10月1日、OpenAI公式ヘルプおよび公式発表で確認）"
    ),
    "api_sdk_info": (
        "OpenAIのAPIでは、Videos APIとSora 2の動画生成モデル（sora-2、sora-2-pro、およびsora-2-2025-10-06、sora-2-2025-12-08、sora-2-pro-2025-10-06の各スナップショット）が提供されていた。"
        "2026年3月24日に廃止が告知され、2026年9月24日にAPIから削除された。OpenAIの廃止モデル一覧では、推奨される代替モデルは示されていない。"
    ),
    "security_info": (
        "提供当時、Soraで生成した動画にはC2PAメタデータが付与され、既定で透かしが表示されていた（2024年12月の公式発表）。"
        "Sora 2のキャラクター機能では、自分の姿を使える相手を本人が決め、いつでもアクセス権を取り消せるとされていた。"
        "提供終了後、最終的なエクスポート期間が過ぎると、Soraの利用に関するデータは完全に削除されると案内されている。"
    ),
    "notes": (
        "このページは、提供を終了したAIツールの記録として残している。"
        "提供終了日：Web版・アプリ版は2026年4月26日、APIは2026年9月24日（OpenAI公式ヘルプ「What to know about the Sora discontinuation」、OpenAI API Deprecations）。"
        "sora.comは現在、提供終了とデータのエクスポートを案内するページへ転送される。"
        "機能・料金の記述は提供当時のOpenAI公式発表に基づく。"
        "（2026年10月1日確認）"
    ),
    "has_free_plan": False,
    "api_available": "no",
    "japanese_support": "unknown",
    "info_checked_date": "2026-10-01",
    "omochix_view": (
        "Soraは、2024年12月の製品提供開始から、音声まで生成できるSora 2とソーシャルアプリの公開へと短期間で進化し、動画生成AIの注目度を大きく押し上げたサービスだったと言える。"
        "一方で、Sora 2のアプリ公開からおよそ7か月でアプリの提供を終え、APIも同じ年の9月に終了した。"
        "生成AIのサービスは、評価の高いものでも提供条件が大きく変わりうることを示した例と言えるだろう。"
        "動画生成AIを業務に組み込む場合は、機能や品質だけでなく、提供の継続性や、作成したデータを手元に残せるかどうかも選ぶ基準に加えておきたい。"
        "Soraを使っていた人は、まず作成済みのコンテンツをエクスポートしたうえで、VeoやRunway、Kling AIなど現在提供されているツールを比較するのが現実的だろう。"
    ),
    "seo_title": "Sora（提供終了）とは？終了日・データのエクスポート・代わりの動画生成AI｜OmochiX",
    "meta_description": "OpenAIの動画生成AI「Sora」は2026年4月26日にWeb版・アプリ版、9月24日にAPIの提供を終了しました。終了の経緯、データのエクスポート方法、クレジットの扱い、代わりに検討できる動画生成AIをOmochiXがまとめます。",
}

out = os.path.join(BASE, "sora-tool.json")
with open(out, "w", encoding="utf-8") as f:
    json.dump(sora, f, ensure_ascii=False, indent=2)
print("wrote", out)
