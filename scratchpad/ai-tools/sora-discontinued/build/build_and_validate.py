#!/usr/bin/env python3
"""Build sora-discontinued-update.json from sora-tool.json and validate it.

Mirrors the Content Updater contract (admin/ai-tool-content-updater.php,
includes/meta-schema.php) plus archive-page checks specific to a
discontinued tool.
"""
import datetime, hashlib, json, os, re, sys
BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(BASE, "sora-discontinued-update.json")
TEXT = ["short_description", "pricing_details", "api_sdk_info", "security_info", "notes", "omochix_view"]
ARR = ["key_features", "pros", "cons", "strengths", "weaknesses", "recommended_for", "recommended_use_cases", "not_recommended_for", "supported_devices", "supported_models", "integrations"]
ENUMS = {"japanese_support": ["full", "partial", "none", "unknown"], "api_available": ["yes", "partial", "no", "unknown"], "commercial_use": ["yes", "partial", "no", "unknown"]}
SEO = ["seo_title", "meta_description"]
ALLOWED = set(["slug", "post_content", "has_free_plan", "info_checked_date"] + TEXT + ARR + list(ENUMS) + SEO)
IMAGE = ["tool_logo_attachment_id", "featured_image_attachment_id"]
FORBIDDEN = ["title", "new_slug"]
# Wording that would present Sora as currently available.
PRESENT = ["提供中", "今すぐ", "始めましょう", "登録して", "無料で使えます", "Soraを利用できます", "Soraは利用できます", "Soraで動画を生成できます", "おすすめです", "Soraでできること", "使い方"]
errors = []
t = json.load(open(os.path.join(BASE, "sora-tool.json"), encoding="utf-8"))
prod = {x["slug"]: x["id"] for x in json.load(open(os.path.join(BASE, "prod-snapshot/all_tools_page1.json"), encoding="utf-8"))}
def has_null(v):
    return v is None or (isinstance(v, dict) and any(has_null(x) for x in v.values())) or (isinstance(v, list) and any(has_null(x) for x in v))
if has_null(t): errors.append("null present")
for k in t:
    if k in IMAGE: errors.append("image field " + k)
    elif k in FORBIDDEN: errors.append("title/slug change field " + k)
    elif k not in ALLOWED: errors.append("unknown field " + k)
if t.get("slug") not in prod: errors.append("unknown slug")
for k, opts in ENUMS.items():
    if k in t and t[k] not in opts: errors.append("enum " + k)
if not isinstance(t.get("has_free_plan"), bool): errors.append("has_free_plan not bool")
try: datetime.date.fromisoformat(t["info_checked_date"])
except Exception: errors.append("date")
for k in ARR:
    if k in t and (not isinstance(t[k], list) or not all(isinstance(x, str) and x.strip() for x in t[k])): errors.append("array " + k)
for k in TEXT + SEO:
    if k in t and (not isinstance(t[k], str) or not t[k].strip()): errors.append("text " + k)
    if k in t and (re.search(r"%[0-9a-fA-F]{2}", t[k]) or "<" in t[k] or ">" in t[k]): errors.append("sanitizer-sensitive text in " + k)
pc = t["post_content"]
stack = []
for close, name, attrs in re.findall(r"<(/?)([a-zA-Z0-9]+)([^>]*)>", pc):
    if name not in {"h2", "h3", "p", "ul", "ol", "li", "strong", "code", "a"}: errors.append("tag " + name)
    if name == "a" and not close and not re.fullmatch(r'\s+href="/ai-tools/[a-z0-9\-]+/"', attrs): errors.append("link " + attrs)
    if close:
        if not stack or stack[-1] != name: errors.append("unbalanced " + name); break
        stack.pop()
    else: stack.append(name)
if stack: errors.append("unclosed " + str(stack))
for a in re.findall(r'href="/ai-tools/([a-z0-9\-]+)/"', pc):
    if a not in prod: errors.append("link to unknown tool " + a)
    if a == "sora": errors.append("self link")
plain = re.sub(r"<code>.*?</code>", "", pc)
if "--" in plain: errors.append("'--' outside <code> (wptexturize would alter it)")
for h in ["<h2>Soraとは？</h2>", "提供終了について</h2>", "<h3>Web・アプリの提供終了</h3>", "<h3>APIの提供終了</h3>", "提供されていた主な機能</h2>", "対応していたモデル</h2>", "料金・クレジットの扱い</h2>", "提供終了後にできること</h2>", "<h3>データのエクスポート</h3>", "代わりに検討できる動画生成AI</h2>", "<h2>FAQ</h2>"]:
    if h not in pc: errors.append("missing section " + h)
blob = "\n".join(v if isinstance(v, str) else "\n".join(v) for k, v in t.items() if k != "slug" and isinstance(v, (str, list)))
for p in PRESENT:
    if p in blob: errors.append("present-tense availability wording: " + p)
for must in ["2026年4月26日", "2026年9月24日", "提供終了", "エクスポート", "Codex"]:
    if must not in pc: errors.append("post_content lacks " + must)
if "提供終了" not in t["seo_title"]: errors.append("seo_title lacks 提供終了")
if "終了" not in t["meta_description"]: errors.append("meta_description lacks 終了")
if len(t["seo_title"]) > 60: errors.append("seo_title > 60")
if not 50 <= len(t["meta_description"]) <= 160: errors.append("meta_description length")
if "!" in blob or "！" in blob: errors.append("exclamation")
payload = json.dumps({"tools": [t]}, ensure_ascii=False, indent=2) + "\n"
json.loads(payload)
print("TOOL_COUNT 1 | slug", t["slug"], "ID", prod.get(t["slug"]))
print("FIELDS", sorted(k for k in t if k != "slug"))
print("LENGTHS sd=%d content=%d view=%d seo=%d meta=%d" % (len(t["short_description"]), len(pc), len(t["omochix_view"]), len(t["seo_title"]), len(t["meta_description"])))
print("ERRORS", len(errors)); [print("  -", e) for e in errors]
if errors: sys.exit(1)
open(OUT, "w", encoding="utf-8").write(payload)
raw = open(OUT, "rb").read()
print("JSON_FILE", os.path.relpath(OUT, "/Users/omochi/Documents/omochix"))
print("JSON_SIZE", len(raw)); print("JSON_SHA256", hashlib.sha256(raw).hexdigest())
