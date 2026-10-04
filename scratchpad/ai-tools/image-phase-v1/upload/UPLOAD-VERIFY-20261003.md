# Media upload 検証（2026-10-03、READ ONLY）

結果：**NG（Updater JSON・dry-run には進まない）**

## アップロード済み attachment（11 件）

| ID | filename | 寸法 | bytes | 内容（sha256 照合） | 現在の alt | 計画 alt との一致 |
|---|---|---|---|---|---|---|
| 1079 | omochix-ai-tool-chatgpt-hero.webp | 1280×720 | 192,396 | ready と一致 | Omochix ai tool chatgpt hero | ✗ |
| 1080 | omochix-ai-tool-claude-code-hero.webp | 1280×720 | 144,396 | ready と一致 | Omochix ai tool claude code hero | ✗ |
| 1081 | **omochix-ai-tool-gemini-hero-v3.webp** | 1280×720 | 217,930 | **Gemini v3（QA PASS）**。ただしファイル名に -v3 が付いている | Omochix ai tool gemini hero v3 | ✗ |
| 1082 | omochix-ai-tool-gemini-hero.webp | 1280×720 | 196,730 | **Gemini v1（QA FAIL、疑似文字）**。ready の Gemini とは不一致 | Omochix ai tool gemini hero | ✗ |
| 1083 | omochix-ai-tool-kling-ai-hero.webp | 1280×720 | 150,234 | ready と一致 | Omochix ai tool kling ai hero | ✗ |
| 1084 | omochix-ai-tool-midjourney-hero.webp | 1280×720 | 204,416 | ready と一致 | Omochix ai tool midjourney hero | ✗ |
| 1085 | omochix-ai-tool-notebooklm-hero.webp | 1280×720 | 187,160 | ready と一致 | Omochix ai tool notebooklm hero | ✗ |
| 1086 | omochix-ai-tool-runway-hero.webp | 1280×720 | 167,692 | ready と一致 | Omochix ai tool runway hero | ✗ |
| 1087 | omochix-ai-tool-veo-hero.webp | 1280×720 | 140,332 | ready と一致 | Omochix ai tool veo hero | ✗ |
| 1088 | omochix-ai-tool-runway-logo.webp | 512×512 | 5,112 | ready と一致 | Omochix ai tool runway logo | ✗ |
| 1089 | omochix-ai-tool-claude-code-logo.webp | 512×512 | 61,340 | ready と一致 | Omochix ai tool claude code logo | ✗ |

- MIME はすべて image/webp
- 親投稿なし
- AI Tool 本体は未変更（1079〜1089 を参照している tool はない）
- 旧 566／636 は存在し、変更されていない

## 問題

1. **alt 未設定**：11 件すべてがファイル名から自動生成された alt のまま
2. **Gemini の取り違え**：
   - 正式ファイル名の 1082 には、QA で FAIL した v1（sha256 7c4f8b1e…）が入っている
   - QA PASS の v3（sha256 d48755fa…）は 1081 に `-v3` 付きのファイル名で入っている
3. **件数**：計画 10 件に対して 11 件（Gemini が 2 件）
