#!/usr/bin/env python3
"""Static policy for .github/workflows/*.yml (Python 3.9+, standard library only).

Two kinds of checks, both read the workflow files as data (nothing is executed):

  - required checks (tools/ci/required-checks.json): every workflow that produces a
    required status check still exists, still runs on the events and branches the ruleset
    relies on, has no paths filter that could leave the check pending, re-runs when the
    base branch of a pull request changes ('edited'), still has the expected job names
    (after matrix expansion), and nothing in it can turn a failure into a pass
    (continue-on-error, a job-level 'if').
  - least privilege, for every workflow: a top-level permissions block, no write
    permission, no workflow_run, no pull_request_target except in the workflows the
    inventory lists under 'pull_request_target_allowed' (those may only check out and
    read, with no secrets), actions pinned to a full commit SHA, checkout without
    persisted credentials, no untrusted pull request text interpolated into a shell
    script, and no second job reporting a required check's name. These findings start
    with SECURITY; the CI change guard never accepts them with a label (the policy itself
    has to change first, in its own reviewed pull request).

The CI change guard runs this with the inventory from the BASE commit against the
workflows of the HEAD commit, so a pull request cannot relax the rules it is checked by.

Usage: python3 tools/ci/workflow_policy.py [--rev REV] [--inventory-rev REV]
  (default: the working tree for both)
"""
import argparse
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import cilib  # noqa: E402

INVENTORY = "tools/ci/required-checks.json"
WORKFLOW_DIR = ".github/workflows"
DEFAULT_PR_TYPES = ["opened", "synchronize", "reopened"]
SECURITY = "SECURITY "
# A step the base inventory does not pin yet (a new release check, or a step that could
# change the tree before a check runs). The CI change guard accepts it only with a review
# of this exact content; CI policy (head inventory) fails until the inventory pins it.
REVIEW = "REVIEW "


# ---------------------------------------------------------------------------
# A small YAML reader for the subset GitHub workflows here use: block mappings and
# sequences, flow [..] and {..} of scalars, block scalars (| >), quotes, comments.
# Scalars stay strings. Anything it cannot read raises YamlError (the policy then fails).
# tests/ci compares it with Ruby's YAML parser when Ruby is installed.

class YamlError(Exception):
    pass


def _strip_comment(s):
    q = None
    for i, c in enumerate(s):
        if q:
            if c == q:
                q = None
        elif c in "'\"":
            q = c
        elif c == "#" and (i == 0 or s[i - 1] in " \t"):
            return s[:i].rstrip()
    return s.rstrip()


def _split_top(s, sep):
    out, depth, q, cur = [], 0, None, ""
    for c in s:
        if q:
            cur += c
            if c == q:
                q = None
            continue
        if c in "'\"":
            q = c
        elif c in "[{":
            depth += 1
        elif c in "]}":
            depth -= 1
        if c == sep and depth == 0:
            out.append(cur)
            cur = ""
        else:
            cur += c
    out.append(cur)
    return out


def _split_key(s):
    """'key: rest' -> (key, rest); None when s is not a mapping entry."""
    q, depth = None, 0
    for i, c in enumerate(s):
        if q:
            if c == q:
                q = None
            continue
        if c in "'\"" and i == 0:
            q = c
        elif c in "[{":
            depth += 1
        elif c in "]}":
            depth -= 1
        elif c == ":" and depth == 0 and (i + 1 == len(s) or s[i + 1] == " "):
            key = s[:i].strip()
            if len(key) >= 2 and key[0] == key[-1] and key[0] in "'\"":
                key = key[1:-1]
            if key.startswith(("[", "{", "${{")):
                return None
            return key, s[i + 1:]
    return None


def _scalar(s):
    s = _strip_comment(s).strip()
    if s.startswith("["):
        if not s.endswith("]"):
            raise YamlError("unterminated flow sequence: " + s)
        inner = s[1:-1].strip()
        return [] if not inner else [_scalar(x) for x in _split_top(inner, ",")]
    if s.startswith("{"):
        if not s.endswith("}"):
            raise YamlError("unterminated flow mapping: " + s)
        out = {}
        inner = s[1:-1].strip()
        for part in (_split_top(inner, ",") if inner else []):
            kv = _split_key(part.strip())
            if not kv:
                raise YamlError("bad flow mapping entry: " + part)
            out[kv[0]] = _scalar(kv[1])
        return out
    if len(s) >= 2 and s[0] == s[-1] and s[0] in "'\"":
        return s[1:-1].replace("''", "'") if s[0] == "'" else s[1:-1]
    return s


class _Reader:
    def __init__(self, text):
        self.lines = text.replace("\t", "    ").split("\n")
        self.i = 0

    def _skip(self):
        while self.i < len(self.lines):
            st = self.lines[self.i].strip()
            if st and not st.startswith("#"):
                return True
            self.i += 1
        return False

    def _indent(self):
        line = self.lines[self.i]
        return len(line) - len(line.lstrip(" "))

    def node(self, indent):
        if not self._skip() or self._indent() < indent:
            return None
        ind = self._indent()
        st = self.lines[self.i].strip()
        return self.seq(ind) if st == "-" or st.startswith("- ") else self.mapping(ind)

    def mapping(self, ind):
        d = {}
        while self._skip():
            cur = self._indent()
            st = self.lines[self.i].strip()
            if cur < ind or st == "-" or st.startswith("- "):
                break
            if cur > ind:
                raise YamlError("line %d: unexpected indent" % (self.i + 1))
            kv = _split_key(st)
            if not kv:
                raise YamlError("line %d: expected 'key: value'" % (self.i + 1))
            self.i += 1
            if kv[0] in d:
                raise YamlError("line %d: duplicate key %s" % (self.i, kv[0]))
            d[kv[0]] = self.value(kv[1], ind)
        return d

    def value(self, rest, ind):
        rest = _strip_comment(rest).strip()
        if rest[:1] in ("|", ">"):
            return self.block_scalar(ind)
        if rest == "":
            if not self._skip():
                return None
            ni, st = self._indent(), self.lines[self.i].strip()
            if ni > ind:
                return self.node(ni)
            if ni == ind and (st == "-" or st.startswith("- ")):
                return self.seq(ind)
            return None
        return _scalar(rest)

    def block_scalar(self, ind):
        out = []
        while self.i < len(self.lines):
            line = self.lines[self.i]
            if line.strip() and len(line) - len(line.lstrip(" ")) <= ind:
                break
            out.append(line)
            self.i += 1
        while out and not out[-1].strip():
            out.pop()
        pad = min((len(l) - len(l.lstrip(" ")) for l in out if l.strip()), default=0)
        return "\n".join(l[pad:] for l in out) + "\n"

    def seq(self, ind):
        out = []
        while self._skip():
            cur, st = self._indent(), self.lines[self.i].strip()
            if cur != ind or not (st == "-" or st.startswith("- ")):
                if cur > ind:
                    raise YamlError("line %d: unexpected indent in sequence" % (self.i + 1))
                break
            body = st[1:]
            if not body.strip():
                self.i += 1
                out.append(self.node(ind + 1))
                continue
            col = ind + 1 + (len(body) - len(body.lstrip(" ")))
            content = body.strip()
            if _split_key(content) and not content.startswith(("'", '"', "[", "{")):
                self.lines[self.i] = " " * col + content
                out.append(self.mapping(col))
            else:
                self.i += 1
                out.append(_scalar(content))
        return out


def load_yaml(text):
    r = _Reader(text)
    doc = r.node(0)
    if r._skip():
        raise YamlError("line %d: could not read past here" % (r.i + 1))
    return doc


# ---------------------------------------------------------------------------

def _as_list(v):
    if v is None:
        return []
    return v if isinstance(v, list) else [v]


def _walk_steps(job):
    return [s for s in _as_list(job.get("steps")) if isinstance(s, dict)]


def _read(path):
    with open(path, encoding="utf-8") as fh:
        return fh.read()


def _runs(step, must):
    """'=cmd': the step's whole script is exactly cmd; otherwise a substring of the script."""
    run = str(step.get("run", ""))
    return run.strip() == must[1:] if must.startswith("=") else must in run


def expand_names(job):
    """Check names a job produces: its name with ${{ matrix.<key> }} expanded."""
    name = job.get("name")
    if not isinstance(name, str):
        return []
    keys = re.findall(r"\$\{\{\s*matrix\.([A-Za-z0-9_-]+)\s*\}\}", name)
    if not keys:
        return [name]
    matrix = ((job.get("strategy") or {}).get("matrix") or {}) if isinstance(job.get("strategy"), dict) else {}
    if isinstance(matrix, dict) and set(matrix) == {"include"} and all(isinstance(e, dict) for e in _as_list(matrix["include"])):
        out = []
        for entry in _as_list(matrix["include"]):
            n = name
            for k in keys:
                if k not in entry:
                    return [name]
                n = re.sub(r"\$\{\{\s*matrix\.%s\s*\}\}" % re.escape(k), str(entry[k]), n)
            out.append(n)
        return out
    names = [name]
    for k in dict.fromkeys(keys):
        vals = _as_list(matrix.get(k)) if isinstance(matrix, dict) else []
        if not vals or "include" in matrix or "exclude" in matrix:
            return [name]  # not expandable here; the inventory comparison will fail loudly
        names = [re.sub(r"\$\{\{\s*matrix\.%s\s*\}\}" % re.escape(k), str(v), n) for n in names for v in vals]
    return names


UNTRUSTED_RE = re.compile(r"\$\{\{[^}]*(github\.head_ref|pull_request\.(title|body|head\.ref|head\.label)|\.labels|issue\.(title|body)|comment\.body|review\.body|commits|head_commit\.message)[^}]*\}\}")
SHA_PIN_RE = re.compile(r"^[A-Za-z0-9_.-]+/[A-Za-z0-9_./-]+@[0-9a-f]{40}$")


def least_privilege(path, doc, target_allowed=(), text=""):
    """Findings for one workflow; every finding starts with SECURITY."""
    v = []
    on = doc.get("on", doc.get(True))
    events = list(on.keys()) if isinstance(on, dict) else _as_list(on)
    if "workflow_run" in events:
        v.append("%s: 'workflow_run' runs with the base repository's token on untrusted input; not allowed" % path)
    target = "pull_request_target" in events
    if target and path not in target_allowed:
        v.append("%s: 'pull_request_target' runs with the base repository's token on untrusted input; not allowed" % path)
    if target and path in target_allowed and re.search(r"\$\{\{[^}]*secrets\.", text or ""):
        v.append("%s: a pull_request_target workflow must not use secrets" % path)
    perms = doc.get("permissions")
    if perms is None:
        v.append("%s: no top-level 'permissions' (the repository default would apply)" % path)

    def check_perms(where, p):
        if p is None:
            return
        if isinstance(p, str):
            if p.strip() not in ("{}", "read-all"):
                v.append("%s: %s permissions '%s' is not allowed (use explicit read scopes)" % (path, where, p))
            elif p.strip() == "read-all":
                v.append("%s: %s permissions 'read-all' is broader than needed" % (path, where))
            return
        if isinstance(p, dict):
            for scope, level in p.items():
                if str(level) != "read" and str(level) != "none":
                    v.append("%s: %s permission %s: %s (only read is allowed)" % (path, where, scope, level))
    check_perms("top-level", perms)
    for jid, job in (doc.get("jobs") or {}).items():
        if not isinstance(job, dict):
            continue
        check_perms("job '%s'" % jid, job.get("permissions"))
        if "uses" in job:
            v.append("%s: job '%s' calls a reusable workflow; not allowed without review" % (path, jid))
        for st in _walk_steps(job):
            uses = st.get("uses")
            if isinstance(uses, str):
                if uses.startswith("docker://"):
                    v.append("%s: job '%s' uses a docker image: %s" % (path, jid, uses))
                elif not uses.startswith("./") and not SHA_PIN_RE.match(uses):
                    v.append("%s: job '%s' action not pinned to a full commit SHA: %s" % (path, jid, uses))
                if target and not uses.startswith("actions/checkout@"):
                    v.append("%s: job '%s' a pull_request_target workflow may only use actions/checkout: %s" % (path, jid, uses))
                if isinstance(uses, str) and uses.startswith("actions/checkout@"):
                    with_ = st.get("with") or {}
                    if str(with_.get("persist-credentials", "")).lower() != "false":
                        v.append("%s: job '%s' checkout keeps the token in .git/config (set persist-credentials: false)" % (path, jid))
            run = st.get("run")
            if isinstance(run, str) and UNTRUSTED_RE.search(run):
                v.append("%s: job '%s' step '%s' puts untrusted pull request text into a shell script (pass it through env:)" % (path, jid, st.get("name", "?")))
    return [SECURITY + x for x in v]


def _step_key(st):
    return st.get("name") if isinstance(st.get("name"), str) else None


def _diff_step(got, want):
    """Keys whose values differ between a workflow step and its pinned copy."""
    out = []
    for k in sorted(set(got) | set(want), key=str):
        if got.get(k) != want.get(k):
            out.append(str(k))
    return out


def _only_adds_lines(old, new):
    """Every line of old appears in new, in the same order (new only adds lines)."""
    it = iter(new.splitlines())
    return all(any(line == got for got in it) for line in old.splitlines())


def pinned_steps(path, jid, job, want):
    """A closed, ordered list: every step of the job is pinned in full (name, if, env, run, uses, with ...).

    Each release check is one named step, compared as a whole, never by a substring of the
    job's scripts: removing one of two similar checks, dropping its 'if', adding a step that
    could change the tree before a check runs, or reordering the steps are all findings.
    """
    v = []
    steps = _walk_steps(job)
    names = [_step_key(st) for st in steps]
    for i, st in enumerate(steps):
        if names[i] is None:
            v.append("%s: job '%s' step %d has no name (every step of this job is pinned by name)" % (path, jid, i + 1))
    for n in sorted({n for n in names if n and names.count(n) > 1}):
        v.append("%s: job '%s' has more than one step named '%s'" % (path, jid, n))
    want_names = [w.get("name") for w in want]
    by_name = {n: st for n, st in zip(names, steps) if n}
    for w in want:
        n = w.get("name")
        st = by_name.get(n)
        if st is None:
            v.append("%s: job '%s' pinned step '%s' is gone" % (path, jid, n))
            continue
        diff = _diff_step(st, w)
        if diff == ["run"] and _only_adds_lines(str(w.get("run", "")), str(st.get("run", ""))):
            # Every pinned line is still there, in order; lines were only added (a new
            # release check in an existing step). Not a weakening, but it must be read.
            v.append(REVIEW + "%s: job '%s' step '%s' adds lines to its pinned script (refresh 'pinned_steps' in the inventory)" % (path, jid, n))
        elif diff:
            v.append("%s: job '%s' step '%s' differs from its pinned copy (%s)" % (path, jid, n, ", ".join(diff)))
    for n in names:
        if n and n not in want_names:
            v.append(REVIEW + "%s: job '%s' step '%s' is not pinned (add it to 'pinned_steps' in the inventory, in a reviewed pull request)" % (path, jid, n))
    order = [n for n in names if n in want_names]
    if order != [n for n in want_names if n in by_name]:
        v.append("%s: job '%s' steps are not in the pinned order" % (path, jid))
    return v


def required(inv, rev, cwd=None):
    """Policy for the workflows that produce required checks. Returns (violations, names)."""
    v, names = [], {}
    for path, spec in inv["workflows"].items():
        text = cilib.show(rev, path, cwd=cwd) if rev else (_read(os.path.join(cwd or ".", path)) if os.path.isfile(os.path.join(cwd or ".", path)) else None)
        if text is None:
            v.append("%s: missing, but it produces required checks" % path)
            continue
        try:
            doc = load_yaml(text)
        except YamlError as exc:
            v.append("%s: cannot be read (%s)" % (path, exc))
            continue
        on = doc.get("on", doc.get(True)) or {}
        if not isinstance(on, dict):
            on = {e: None for e in _as_list(on)}
        pr_event = spec.get("pull_request_event", "pull_request")
        pr = on.get(pr_event, "absent")
        if pr == "absent":
            v.append("%s: no %s trigger" % (path, pr_event))
            pr = {}
        pr = pr or {}
        for key in ("paths", "paths-ignore", "branches-ignore"):
            if key in pr:
                v.append("%s: pull_request.%s can leave a required check pending or unset; not allowed" % (path, key))
        want_b = spec.get("pull_request_branches")
        have_b = _as_list(pr.get("branches"))
        if want_b == "all":
            if have_b:
                v.append("%s: pull_request.branches must not be filtered (runs on every pull request)" % path)
        elif sorted(have_b) != sorted(want_b):
            v.append("%s: pull_request.branches is %s, expected %s" % (path, have_b, want_b))
        types = _as_list(pr.get("types")) or DEFAULT_PR_TYPES
        missing = [t for t in spec.get("pull_request_types", []) if t not in types]
        if missing:
            v.append("%s: pull_request.types lacks %s (a changed base branch would keep the old result)" % (path, ", ".join(missing)))
        push = on.get("push") or {}
        if spec.get("push_branches"):
            for key in ("paths", "paths-ignore", "branches-ignore", "tags"):
                if isinstance(push, dict) and key in push:
                    v.append("%s: push.%s narrows the post-merge check; not allowed" % (path, key))
            have = _as_list(push.get("branches")) if isinstance(push, dict) else []
            for b in spec["push_branches"]:
                if b not in have:
                    v.append("%s: push.branches lacks %s (no check after a push there)" % (path, b))
        # Workflow- or job-level env/defaults/container would change how every pinned step
        # runs (BASH_ENV, a custom shell, another image) without touching the steps.
        for key in ("env", "defaults"):
            if key in doc:
                v.append("%s: top-level '%s' is not allowed in a workflow that produces required checks" % (path, key))
        jobs = doc.get("jobs") or {}
        for jid, want_names in spec["jobs"].items():
            job = jobs.get(jid)
            if not isinstance(job, dict):
                v.append("%s: job '%s' is gone" % (path, jid))
                continue
            got = expand_names(job)
            if sorted(got) != sorted(want_names):
                v.append("%s: job '%s' produces checks %s, expected %s" % (path, jid, got, want_names))
            names.setdefault(path, []).extend(got)
            if "if" in job:
                v.append("%s: job '%s' has a job-level 'if' (a skipped job reports success to required checks)" % (path, jid))
            if "continue-on-error" in job:
                v.append("%s: job '%s' has continue-on-error" % (path, jid))
            for key in ("env", "defaults", "container", "services"):
                if key in job:
                    v.append("%s: job '%s' has a job-level '%s' (it changes how every step runs)" % (path, jid, key))
            if jid in spec.get("pinned_steps", {}):
                v += pinned_steps(path, jid, job, spec["pinned_steps"][jid])
            gated = spec.get("gated_after", {}).get(jid)
            if gated:
                # Every step after the scope decision runs under exactly that condition:
                # a check added later cannot run unconditionally or under another 'if'.
                steps = _walk_steps(job)
                idx = [i for i, st in enumerate(steps) if st.get("name") == gated["step"]]
                if not idx:
                    v.append("%s: job '%s' has no step '%s'" % (path, jid, gated["step"]))
                else:
                    for st in steps[idx[0] + 1:]:
                        if str(st.get("if", "")).strip() != gated["if"]:
                            v.append("%s: job '%s' step '%s' must run under 'if: %s' (got %s)" % (path, jid, st.get("name", "?"), gated["if"], ("'if: %s'" % st["if"]) if "if" in st else "no 'if'"))
            for st in _walk_steps(job):
                if "continue-on-error" in st:
                    v.append("%s: job '%s' step '%s' has continue-on-error" % (path, jid, st.get("name", "?")))
            musts = spec.get("must_run", {}).get(jid, [])
            steps = _walk_steps(job)
            for must in musts:
                if not any(_runs(st, must) for st in steps):
                    v.append("%s: job '%s' no longer runs '%s'" % (path, jid, must.lstrip("=")))
            allowed_if = spec.get("allowed_step_if", [])
            for st in steps:
                run = str(st.get("run", ""))
                required_step = any(_runs(st, m) for m in musts)
                if "if" in st and required_step and str(st["if"]).strip() not in allowed_if:
                    v.append("%s: job '%s' step '%s' runs a required command under 'if: %s' (allowed: %s)" % (path, jid, st.get("name", "?"), st["if"], allowed_if or "none"))
                if required_step and re.search(r"\|\|\s*(true|:|exit 0)\b", run):
                    v.append("%s: job '%s' step '%s' can swallow a failure ('|| true')" % (path, jid, st.get("name", "?")))
    return v, names


def all_workflows(rev, cwd=None):
    if rev:
        out = cilib.git("ls-tree", "--name-only", rev, WORKFLOW_DIR + "/", cwd=cwd).decode().split("\n")
        return [p for p in out if p.endswith((".yml", ".yaml"))]
    d = os.path.join(cwd or ".", WORKFLOW_DIR)
    return sorted(os.path.join(WORKFLOW_DIR, f) for f in os.listdir(d) if f.endswith((".yml", ".yaml")))


def check(rev=None, inventory=None, cwd=None):
    """All violations for the workflows at rev (None = working tree)."""
    v = []
    inv = inventory
    if inv is None:
        text = cilib.show(rev, INVENTORY, cwd=cwd) if rev else _read(os.path.join(cwd or ".", INVENTORY))
        inv = json.loads(text)
    rv, _ = required(inv, rev, cwd=cwd)
    v += rv
    owner = {}
    for path, spec in inv["workflows"].items():
        for names in spec["jobs"].values():
            for n in names:
                owner[n] = path
    for path in all_workflows(rev, cwd=cwd):
        text = cilib.show(rev, path, cwd=cwd) if rev else _read(os.path.join(cwd or ".", path))
        try:
            doc = load_yaml(text)
        except YamlError as exc:
            v.append("%s: cannot be read (%s)" % (path, exc))
            continue
        v += least_privilege(path, doc or {}, inv.get("pull_request_target_allowed", []), text)
        # A second job with a required check's name could report success in its place.
        for jid, job in ((doc or {}).get("jobs") or {}).items():
            if not isinstance(job, dict):
                continue
            names = expand_names(job)
            raw = job.get("name") if isinstance(job.get("name"), str) else jid
            if "${{" in raw and names == [raw]:
                names = [raw]  # not expandable: compared as written below
            for n in names:
                if n in owner and (owner[n] != path or jid not in inv["workflows"][owner[n]]["jobs"]):
                    v.append(SECURITY + "%s: job '%s' reports '%s', a required check that only %s may report" % (path, jid, n, owner[n]))
                if "${{" in n and any(_could_be(n, req) for req in owner) and path not in inv["workflows"]:
                    v.append(SECURITY + "%s: job '%s' has a computed name '%s' that could equal a required check" % (path, jid, n))
            if not isinstance(job.get("name"), str) and jid in owner:
                v.append(SECURITY + "%s: job id '%s' (no name) equals a required check" % (path, jid))
    return v


def _could_be(pattern, name):
    """Could a name with ${{ ... }} expressions render as this required check name?"""
    parts = re.split(r"\$\{\{[^}]*\}\}", pattern)
    rx = ".*".join(re.escape(p) for p in parts)
    return re.fullmatch(rx, name) is not None


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--rev", help="read workflows from this commit (default: working tree)")
    ap.add_argument("--print-steps", nargs=2, metavar=("WORKFLOW", "JOB"),
                    help="print the job's steps as JSON for 'pinned_steps' in the inventory (after adding a release check)")
    ap.add_argument("--inventory-rev", help="read the inventory from this commit (default: same as --rev)")
    a = ap.parse_args(argv)
    if a.print_steps:
        wf, jid = a.print_steps
        text = cilib.show(a.rev, wf) if a.rev else _read(wf)
        job = ((load_yaml(text or "") or {}).get("jobs") or {}).get(jid)
        if not isinstance(job, dict):
            print("no job '%s' in %s" % (jid, wf))
            return 1
        print(json.dumps(_walk_steps(job), indent=2, ensure_ascii=False))
        return 0
    inv = None
    if a.inventory_rev:
        inv = json.loads(cilib.show(a.inventory_rev, INVENTORY) or "null")
        if not inv:
            print("FAIL no %s at %s" % (INVENTORY, a.inventory_rev))
            return 1
    v = check(a.rev, inv)
    for line in v:
        print("FAIL " + line)
    print("workflow policy: %s (%d violations)" % ("PASS" if not v else "FAIL", len(v)))
    return 1 if v else 0


if __name__ == "__main__":
    sys.exit(main())
