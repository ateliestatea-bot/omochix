<?php exit; // Server-only data, never served: inc/labs.php reads the JSON after this line (omochix_labs_read_guarded_json). Edit the JSON only. ?>
{
  "schema_version": 2,
  "currency": "USD",
  "unit": "per_1m_tokens",
  "max_age_days": 90,
  "scope": {
    "providers": "Anthropic, OpenAI and Google models listed on the provider's official API pricing page with a published API model ID: each provider's current flagship, mid-size and small models.",
    "tier": "Standard tier, general availability list prices only. No Batch / Flex / Priority tiers, regional prices or contract discounts.",
    "prices": "Text input, output and cache-read prices per 1M tokens in USD. Cache writes, image / audio input and tool fees are not modelled.",
    "schedule": "Announced price changes are separate rows with valid_until / valid_from and switch on the effective date."
  },
  "caveat_labels": {
    "cache_write_excluded": "キャッシュ書き込みの料金は計算に含めない",
    "thinking_as_output_unstated": "thinking(推論)トークンは出力トークンに含めて入力する前提(料金ページに明記なし)",
    "thinking_included_in_output": "出力の単価は thinking トークンを含む(公式に明記)",
    "prompt_length_includes_cache": "10万トークンの境目はキャッシュの読み書きを含めて数える(このツールはキャッシュ書き込みを扱わない)",
    "cache_storage_excluded": "明示的キャッシュのストレージ料金(時間単位)は計算に含めない",
    "free_tier_excluded": "無料枠は考慮しない(有料ティアの価格で計算する)",
    "modality_unlabeled_as_text": "モダリティの表記がない価格を、テキストの単価として扱う",
    "implicit_cache_only": "キャッシュは暗黙キャッシュのみ(明示的キャッシュには非対応)",
    "api_id_unconfirmed": "料金ページの名前が API のモデルIDであることを、公式に確認できていない",
    "long_context_whole_request_assumed": "272K トークンを超えたリクエストは、全体に長いコンテキストの単価をかける前提(公式に明記なし)"
  },
  "review": {
    "status": "pending",
    "reviewer": null,
    "reviewed_at": null,
    "note": "Standard tier list prices, read from each provider's official pricing page on 2026-11-04 and re-checked on 2026-11-04 (prices, effective dates, billing conditions). Global gate: production shows nothing until status is approved. Row gate: production shows only rows whose approval is approved (reviewer, reviewed_at on or after verified_at, every caveat acknowledged). tests/labs/data.js --release enforces both."
  },
  "providers": [
    {
      "slug": "anthropic",
      "name": "Anthropic"
    },
    {
      "slug": "openai",
      "name": "OpenAI"
    },
    {
      "slug": "google",
      "name": "Google"
    }
  ],
  "models": [
    {
      "id": "claude-opus-5-5",
      "provider": "anthropic",
      "label": "Claude Opus 5.5",
      "input": 4,
      "output": 20,
      "cached_input": 0.2,
      "long_context": null,
      "source_url": "https://platform.claude.com/docs/en/about-claude/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "cache_write_excluded",
        "thinking_as_output_unstated"
      ],
      "approval": {
        "status": "pending",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": []
      }
    },
    {
      "id": "claude-sonnet-5-5",
      "provider": "anthropic",
      "label": "Claude Sonnet 5.5",
      "input": 2,
      "output": 10,
      "cached_input": 0.1,
      "long_context": null,
      "source_url": "https://platform.claude.com/docs/en/about-claude/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "cache_write_excluded",
        "thinking_as_output_unstated"
      ],
      "approval": {
        "status": "pending",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": []
      }
    },
    {
      "id": "claude-haiku-5-5",
      "provider": "anthropic",
      "label": "Claude Haiku 5.5",
      "input": 0.1,
      "output": 0.5,
      "cached_input": 0.01,
      "long_context": {
        "threshold_tokens": 100000,
        "input": 0.5,
        "output": 2.5,
        "cached_input": 0.05
      },
      "source_url": "https://platform.claude.com/docs/en/about-claude/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "cache_write_excluded",
        "thinking_as_output_unstated",
        "prompt_length_includes_cache"
      ],
      "approval": {
        "status": "pending",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": []
      }
    },
    {
      "id": "gpt-6-astra",
      "provider": "openai",
      "label": "gpt-6-astra",
      "input": 10,
      "output": 50,
      "cached_input": 1,
      "long_context": {
        "threshold_tokens": 272000,
        "input": 20,
        "output": 75,
        "cached_input": 2
      },
      "source_url": "https://developers.openai.com/api/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "api_id_unconfirmed",
        "long_context_whole_request_assumed",
        "thinking_as_output_unstated"
      ],
      "approval": {
        "status": "held",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": [],
        "reason": "料金ページの名前が API のモデルIDであると明記されておらず、272K トークン超の課金方法(リクエスト全体か超過分か)も公式に確認できないため保留。",
        "decided_at": "2026-11-05"
      }
    },
    {
      "id": "gpt-6.1-sol",
      "provider": "openai",
      "label": "gpt-6.1-sol",
      "input": 2,
      "output": 10,
      "cached_input": 0.1,
      "long_context": {
        "threshold_tokens": 272000,
        "input": 4,
        "output": 15,
        "cached_input": 0.2
      },
      "source_url": "https://developers.openai.com/api/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "api_id_unconfirmed",
        "long_context_whole_request_assumed",
        "thinking_as_output_unstated"
      ],
      "approval": {
        "status": "held",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": [],
        "reason": "料金ページの名前が API のモデルIDであると明記されておらず、272K トークン超の課金方法(リクエスト全体か超過分か)も公式に確認できないため保留。",
        "decided_at": "2026-11-05"
      }
    },
    {
      "id": "gpt-6.1-terra",
      "provider": "openai",
      "label": "gpt-6.1-terra",
      "input": 1,
      "output": 5,
      "cached_input": 0.05,
      "long_context": {
        "threshold_tokens": 272000,
        "input": 2,
        "output": 7.5,
        "cached_input": 0.1
      },
      "source_url": "https://developers.openai.com/api/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "api_id_unconfirmed",
        "long_context_whole_request_assumed",
        "thinking_as_output_unstated"
      ],
      "approval": {
        "status": "held",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": [],
        "reason": "料金ページの名前が API のモデルIDであると明記されておらず、272K トークン超の課金方法(リクエスト全体か超過分か)も公式に確認できないため保留。",
        "decided_at": "2026-11-05"
      }
    },
    {
      "id": "gpt-6-luna",
      "provider": "openai",
      "label": "gpt-6-luna",
      "input": 0.1,
      "output": 0.5,
      "cached_input": 0.01,
      "long_context": {
        "threshold_tokens": 272000,
        "input": 0.2,
        "output": 0.75,
        "cached_input": 0.02
      },
      "source_url": "https://developers.openai.com/api/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "api_id_unconfirmed",
        "long_context_whole_request_assumed",
        "thinking_as_output_unstated"
      ],
      "approval": {
        "status": "held",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": [],
        "reason": "料金ページの名前が API のモデルIDであると明記されておらず、272K トークン超の課金方法(リクエスト全体か超過分か)も公式に確認できないため保留。",
        "decided_at": "2026-11-05"
      }
    },
    {
      "id": "gpt-5.4-mini",
      "provider": "openai",
      "label": "gpt-5.4-mini",
      "input": 0.75,
      "output": 4.5,
      "cached_input": 0.075,
      "long_context": null,
      "source_url": "https://developers.openai.com/api/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "api_id_unconfirmed",
        "thinking_as_output_unstated"
      ],
      "approval": {
        "status": "held",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": [],
        "reason": "料金ページの名前が API のモデルIDであると公式に確認できないため保留(長いコンテキストの区分はない)。",
        "decided_at": "2026-11-05"
      }
    },
    {
      "id": "gemini-4-argon",
      "provider": "google",
      "label": "Gemini 4 Argon",
      "input": 2,
      "output": 10,
      "cached_input": 0.1,
      "long_context": null,
      "valid_until": "2026-11-30",
      "source_url": "https://aistudio.google.com/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "modality_unlabeled_as_text",
        "thinking_included_in_output",
        "implicit_cache_only"
      ],
      "approval": {
        "status": "pending",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": []
      }
    },
    {
      "id": "gemini-4-argon",
      "provider": "google",
      "label": "Gemini 4 Argon",
      "input": 4,
      "output": 20,
      "cached_input": 0.2,
      "long_context": null,
      "valid_from": "2026-12-01",
      "source_url": "https://aistudio.google.com/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "modality_unlabeled_as_text",
        "thinking_included_in_output",
        "implicit_cache_only"
      ],
      "approval": {
        "status": "pending",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": []
      }
    },
    {
      "id": "gemini-3.8-flash",
      "provider": "google",
      "label": "Gemini 3.8 Flash",
      "input": 0.75,
      "output": 3.75,
      "cached_input": 0.075,
      "long_context": null,
      "valid_until": "2026-12-31",
      "source_url": "https://aistudio.google.com/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "modality_unlabeled_as_text",
        "thinking_included_in_output",
        "cache_storage_excluded",
        "free_tier_excluded"
      ],
      "approval": {
        "status": "pending",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": []
      }
    },
    {
      "id": "gemini-3.8-flash",
      "provider": "google",
      "label": "Gemini 3.8 Flash",
      "input": 1.5,
      "output": 7.5,
      "cached_input": 0.15,
      "long_context": null,
      "valid_from": "2027-01-01",
      "source_url": "https://aistudio.google.com/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "modality_unlabeled_as_text",
        "thinking_included_in_output",
        "cache_storage_excluded",
        "free_tier_excluded"
      ],
      "approval": {
        "status": "pending",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": []
      }
    },
    {
      "id": "gemini-3.5-flash-lite",
      "provider": "google",
      "label": "Gemini 3.5 Flash-Lite",
      "input": 0.3,
      "output": 2.5,
      "cached_input": 0.03,
      "long_context": null,
      "source_url": "https://aistudio.google.com/docs/pricing",
      "verified_at": "2026-11-04",
      "status": "verified",
      "caveats": [
        "cache_storage_excluded",
        "free_tier_excluded"
      ],
      "approval": {
        "status": "pending",
        "reviewer": null,
        "reviewed_at": null,
        "acknowledged": []
      }
    }
  ]
}
