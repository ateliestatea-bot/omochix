#!/usr/bin/env python3
"""Merge tools/<slug>.json into the final Content Updater input and validate it.

The validation mirrors wp-plugin/omochix-core/admin/ai-tool-content-updater.php
(omochix_core_tool_update_validate_row) and includes/meta-schema.php, which are
the source of truth. Existing slugs come from the recovered production REST
snapshot (recovered/prod-snapshot/all_tools_page1.json).
"""
import datetime
import hashlib
import json
import os
import re
import sys

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(BASE, "ai-tools-content-batch-01.json")

ORDER = ["veo", "kling-ai", "runway", "midjourney", "claude-code", "cursor", "perplexity", "gemini", "chatgpt", "notebooklm"]

TEXT_FIELDS = ["short_description", "pricing_details", "api_sdk_info", "security_info", "notes", "omochix_view"]
ARRAY_FIELDS = ["key_features", "pros", "cons", "strengths", "weaknesses", "recommended_for", "recommended_use_cases", "not_recommended_for", "supported_devices", "supported_models", "integrations"]
ENUMS = {
    "japanese_support": ["full", "partial", "none", "unknown"],
    "api_available": ["yes", "partial", "no", "unknown"],
    "commercial_use": ["yes", "partial", "no", "unknown"],
}
SEO_FIELDS = ["seo_title", "meta_description"]
IMAGE_FIELDS = ["tool_logo_attachment_id", "featured_image_attachment_id"]
FORBIDDEN = ["title", "new_slug"]
# Fields the updater accepts but this batch must not touch.
OUT_OF_SCOPE = ["pricing_type", "tool_status", "official_url", "company_name", "rating_overall", "is_featured", "display_order"]
ALLOWED = set(["slug", "post_content", "has_free_plan", "info_checked_date"] + TEXT_FIELDS + ARRAY_FIELDS + list(ENUMS) + SEO_FIELDS)

KEY_ORDER = ["slug", "short_description", "post_content", "key_features", "pros", "cons", "strengths", "weaknesses", "recommended_for", "recommended_use_cases", "not_recommended_for", "pricing_details", "api_sdk_info", "security_info", "notes", "supported_devices", "supported_models", "integrations", "has_free_plan", "api_available", "commercial_use", "japanese_support", "info_checked_date", "omochix_view", "seo_title", "meta_description"]

# Internal/editorial phrasing that must never reach a public page.
LEAK_PATTERNS = ["OmochiX側", "既存メタ", "truncate", "official_url", "pricing_type", "has_free_plan", "japanese_support", "編集者側", "運営者の判断", "本レポート", "今回の調査", "WebFetch", "slug", "掲載前に", "実機確認"]

errors, warnings = [], []


def err(slug, msg):
    errors.append("%s: %s" % (slug, msg))


def warn(slug, msg):
    warnings.append("%s: %s" % (slug, msg))


def has_null(v):
    if v is None:
        return True
    if isinstance(v, dict):
        return any(has_null(x) for x in v.values())
    if isinstance(v, list):
        return any(has_null(x) for x in v)
    return False


def check_html(slug, s):
    if re.search(r"^#{1,6} |\*\*|```", s, re.M):
        err(slug, "post_content contains markdown")
    tags = re.findall(r"<(/?)([a-zA-Z0-9]+)[^>]*>", s)
    allowed = {"h2", "h3", "p", "ul", "ol", "li", "strong", "code"}
    stack = []
    for close, name in tags:
        if name not in allowed:
            err(slug, "post_content uses unexpected tag <%s>" % name)
        if close:
            if not stack or stack[-1] != name:
                err(slug, "post_content unbalanced </%s>" % name)
                return
            stack.pop()
        else:
            stack.append(name)
    if stack:
        err(slug, "post_content unclosed tags %s" % stack)
    for h in ["とは？</h2>", "料金</h2>", "使い方</h2>", "<h2>日本語で使える？</h2>", "<h2>FAQ</h2>", "<ol>"]:
        if h not in s:
            err(slug, "post_content missing section %s" % h)


with open(os.path.join(BASE, "recovered/prod-snapshot/all_tools_page1.json"), encoding="utf-8") as f:
    prod = {t["slug"]: t["id"] for t in json.load(f)}

tools = []
for slug in ORDER:
    with open(os.path.join(BASE, "tools", slug + ".json"), encoding="utf-8") as f:
        t = json.load(f)
    tools.append({k: t[k] for k in KEY_ORDER if k in t} | {k: v for k, v in t.items() if k not in KEY_ORDER})

slugs = [t.get("slug") for t in tools]
unknown = [s for s in slugs if s not in prod]
dupes = sorted({s for s in slugs if slugs.count(s) > 1})
null_fields = image_fields = title_fields = 0

for t in tools:
    slug = t.get("slug", "?")
    if t != {k: t[k] for k in t}:
        pass
    if has_null(t):
        null_fields += 1
        err(slug, "contains null")
    for k in t:
        if k in IMAGE_FIELDS:
            image_fields += 1
            err(slug, "image field %s present" % k)
        elif k in FORBIDDEN:
            title_fields += 1
            err(slug, "forbidden field %s present" % k)
        elif k in OUT_OF_SCOPE:
            err(slug, "out-of-scope field %s present" % k)
        elif k not in ALLOWED:
            err(slug, "unknown field %s" % k)
    if not isinstance(t.get("post_content"), str) or not t["post_content"].strip():
        err(slug, "post_content must be a non-empty string")
    else:
        check_html(slug, t["post_content"])
    for k in TEXT_FIELDS + SEO_FIELDS:
        if k in t and (not isinstance(t[k], str) or not t[k].strip()):
            err(slug, "%s must be a non-empty string" % k)
    for k in ARRAY_FIELDS:
        if k in t:
            if not isinstance(t[k], list) or not t[k]:
                err(slug, "%s must be a non-empty array" % k)
            elif not all(isinstance(x, str) and x.strip() for x in t[k]):
                err(slug, "%s items must be non-empty strings" % k)
            elif len(set(t[k])) != len(t[k]):
                err(slug, "%s has duplicate items" % k)
    if not isinstance(t.get("has_free_plan"), bool):
        err(slug, "has_free_plan must be boolean")
    for k, opts in ENUMS.items():
        if t.get(k) not in opts:
            err(slug, "%s=%r not in %s" % (k, t.get(k), opts))
    d = t.get("info_checked_date", "")
    if not isinstance(d, str) or not re.match(r"^\d{4}-\d{2}-\d{2}$", d):
        err(slug, "info_checked_date format")
    else:
        try:
            datetime.date.fromisoformat(d)
        except ValueError:
            err(slug, "info_checked_date not a real date")
    # sanitize_text_field / sanitize_textarea_field strip tags and %XX octets.
    for k in TEXT_FIELDS + SEO_FIELDS + ARRAY_FIELDS:
        vals = t.get(k, [])
        vals = vals if isinstance(vals, list) else [vals]
        for v in vals:
            if re.search(r"%[0-9a-fA-F]{2}", v):
                err(slug, "%s contains a %%XX sequence the sanitizer would strip" % k)
            if "<" in v or ">" in v:
                err(slug, "%s contains angle brackets" % k)
            if k not in ("pricing_details", "notes") and "\n" in v:
                err(slug, "%s contains a newline" % k)
    # Editorial quality gates (HeyGen reference).
    blob = "\n".join(v if isinstance(v, str) else "\n".join(v) for k, v in t.items() if k != "slug" and isinstance(v, (str, list)))
    for p in LEAK_PATTERNS:
        if p in blob:
            err(slug, "internal phrasing leaked: %s" % p)
    ja_forbidden = {
        "partial": ["日本語非対応", "日本語は使えない", "日本語には対応していません", "日本語対応なし", "日本語完全対応", "公式ドキュメントに記載はありません", "公式ドキュメントに記載がない"],
        "none": ["日本語完全対応", "一部日本語対応", "一部対応"],
    }.get(t.get("japanese_support"), [])
    for p in ja_forbidden:
        if p in blob:
            err(slug, "wording contradicts japanese_support=%s: %s" % (t.get("japanese_support"), p))
    if "！" in blob or "!" in blob:
        err(slug, "exclamation mark")
    if len(t.get("seo_title", "")) > 60:
        err(slug, "seo_title > 60 chars")
    if not 50 <= len(t.get("meta_description", "")) <= 160:
        err(slug, "meta_description length %d" % len(t.get("meta_description", "")))
    if not 60 <= len(t.get("short_description", "")) <= 95:
        warn(slug, "short_description length %d" % len(t.get("short_description", "")))
    if not 200 <= len(t.get("omochix_view", "")) <= 420:
        warn(slug, "omochix_view length %d" % len(t.get("omochix_view", "")))
    if len(t.get("post_content", "")) < 3000:
        warn(slug, "post_content only %d chars" % len(t.get("post_content", "")))
    for kf in t.get("key_features", []):
        if "：" not in kf:
            err(slug, "key_features item not in 機能名：説明 format: %s" % kf)
    for k in KEY_ORDER:
        if k not in t:
            warn(slug, "field omitted (left unchanged in production): %s" % k)

if unknown:
    errors.append("unknown slugs: %s" % unknown)
if dupes:
    errors.append("duplicate slugs: %s" % dupes)
if len(tools) != 10:
    errors.append("tool count %d != 10" % len(tools))

payload = json.dumps({"tools": tools}, ensure_ascii=False, indent=2) + "\n"
json.loads(payload)  # round-trip

print("TOOL_COUNT", len(tools))
print("SLUGS", ", ".join("%s(ID %s)" % (s, prod.get(s, "?")) for s in slugs))
print("UNKNOWN_SLUGS", len(unknown), "DUPLICATE_SLUGS", len(dupes), "NULL_FIELDS", null_fields, "IMAGE_FIELDS", image_fields, "TITLE_SLUG_FIELDS", title_fields)
for t in tools:
    print("  %-12s content=%5d sd=%3d view=%3d seo=%2d meta=%3d kf=%d free=%-5s api=%-7s com=%-7s ja=%-7s date=%s" % (
        t["slug"], len(t["post_content"]), len(t["short_description"]), len(t["omochix_view"]), len(t["seo_title"]), len(t["meta_description"]),
        len(t["key_features"]), t["has_free_plan"], t["api_available"], t["commercial_use"], t["japanese_support"], t["info_checked_date"]))
print("WARNINGS", len(warnings))
for w in warnings:
    print("  -", w)
print("ERRORS", len(errors))
for e in errors:
    print("  -", e)
if errors:
    sys.exit(1)

with open(OUT, "w", encoding="utf-8") as f:
    f.write(payload)
raw = open(OUT, "rb").read()
print("JSON_FILE", os.path.relpath(OUT, os.path.dirname(os.path.dirname(os.path.dirname(BASE)))))
print("JSON_SIZE", len(raw), "bytes (limit 5 MB)")
print("JSON_SHA256", hashlib.sha256(raw).hexdigest())
