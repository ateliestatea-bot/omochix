#!/usr/bin/env python3
"""Integration gate: what may land on an integration branch (integrate/**).

An integration branch collects Web 2.0 work before a release. It must not carry a release
of its own: releases are cut from main and checked there by the Release gate. So for every
pull request into integrate/** (and every push to it) this gate checks:

  1. versions   - Core Version header, OMOCHIX_CORE_VERSION, readme.txt Stable tag, theme
                  style.css Version and every "// omochix-industry-hub-version:" marker are
                  unchanged.
  2. release    - nothing under the release-only paths (tools/release/, tests/release/,
                  style.css, readme.txt) changes.
  3. ownership  - pull requests only: the head branch is web2/<stream>/<topic> from this
                  repository, and every changed path is owned by that stream in
                  docs/web2/ownership.json, read from the BASE commit. Held paths (Design
                  Library, front-page/header/footer) fail for every stream.

Sync exception: when the incoming commit is already contained in main (merging main into
the integration branch), the changes are main's own released state and all checks pass with
that reason recorded. Main is protected by its own Release gate and ruleset.

Usage (workflow, after checkout of the merge commit with full history):
  python3 tools/ci/integration_gate.py --mode pr --head-ref "$HEAD_REF" --same-repo "$SAME_REPO"
  python3 tools/ci/integration_gate.py --mode push --base "$BEFORE" --head "$AFTER"
Exit 0 when every check passes, 1 otherwise.
"""
import argparse
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import cilib  # noqa: E402

CORE_MAIN = "wp-plugin/omochix-core/omochix-core.php"
CORE_README = "wp-plugin/omochix-core/readme.txt"
THEME_STYLE = "wp-theme/omochix-theme/style.css"
MARKER_RE = re.compile(r"^// omochix-industry-hub-version: .*$", re.M)
BRANCH_RE = re.compile(r"^web2/([a-z0-9-]+)/[A-Za-z0-9._/-]+$")
OWNERSHIP = "docs/web2/ownership.json"


def versions(revision, cwd=None):
    """The release version fields at a revision (None when a file is missing)."""
    out = {}
    core = cilib.show(revision, CORE_MAIN, cwd=cwd)
    readme = cilib.show(revision, CORE_README, cwd=cwd)
    style = cilib.show(revision, THEME_STYLE, cwd=cwd)

    def grab(pattern, text):
        if text is None:
            return None
        m = re.search(pattern, text, re.M)
        return m.group(1) if m else ""
    out["core Version header"] = grab(r"^ \* Version: (\S+)", core)
    out["OMOCHIX_CORE_VERSION"] = grab(r"define\( 'OMOCHIX_CORE_VERSION', '([^']+)' \);", core)
    out["readme.txt Stable tag"] = grab(r"^Stable tag: (\S+)", readme)
    out["theme style.css Version"] = grab(r"^Version:\s*(\S+)", style)
    return out


def check(base, head, mode, head_ref="", same_repo=True, main_ref="origin/main", cwd=None):
    """Return (ok, lines) where lines are human-readable PASS/FAIL/NOTE entries."""
    lines, ok = [], True

    def fail(msg):
        nonlocal ok
        ok = False
        lines.append("FAIL " + msg)

    try:
        base_sha, head_sha = cilib.rev(base, cwd=cwd), cilib.rev(head, cwd=cwd)
        paths = cilib.changed_paths(base_sha, head_sha, cwd=cwd)
    except cilib.GitError as exc:
        return False, ["FAIL git could not list the changed files: %s" % exc]
    lines.append("NOTE %d changed files between %s and %s" % (len(paths), base_sha[:12], head_sha[:12]))

    incoming = head_sha
    if mode == "pr":
        # The merge commit's second parent is the pull request head; fall back to head itself.
        try:
            ps = cilib.parents(head_sha, cwd=cwd)
            if len(ps) == 2:
                incoming = ps[1]
        except cilib.GitError:
            pass
    try:
        main_sha = cilib.rev(main_ref, cwd=cwd)
    except cilib.GitError:
        main_sha = None
        lines.append("NOTE %s is not available: the sync-from-main exception cannot apply" % main_ref)
    if main_sha and cilib.is_ancestor(incoming, main_sha, cwd=cwd):
        lines.append("PASS sync from main: %s is already contained in %s, so these changes are main's released state" % (incoming[:12], main_ref))
        return True, lines

    # 1. Versions.
    vb, vh = versions(base_sha, cwd=cwd), versions(head_sha, cwd=cwd)
    for key in vb:
        if vb[key] != vh[key]:
            fail("version: %s changed (%s -> %s); versions change only in a release from main" % (key, vb[key], vh[key]))
    for p in paths:
        if not p.endswith(".php"):
            continue
        mb = MARKER_RE.findall(cilib.show(base_sha, p, cwd=cwd) or "")
        mh = MARKER_RE.findall(cilib.show(head_sha, p, cwd=cwd) or "")
        if mb != mh:
            fail("version: Industry Hub version markers changed in %s" % p)
    if ok:
        lines.append("PASS versions unchanged (Core header, OMOCHIX_CORE_VERSION, Stable tag, theme Version, markers)")

    # Ownership rules come from the base commit: the head cannot widen its own scope.
    raw = cilib.show(base_sha, OWNERSHIP, cwd=cwd)
    try:
        own = json.loads(raw) if raw is not None else None
    except ValueError:
        own = None
    if not isinstance(own, dict) or not isinstance(own.get("streams"), dict):
        fail("ownership: %s is missing or unreadable on the base commit" % OWNERSHIP)
        return ok, lines

    # Symlinks and submodules could point an owned path at a held or release file.
    try:
        modes = cilib.new_modes(base_sha, head_sha, cwd=cwd)
    except cilib.GitError as exc:
        fail("git could not read file modes: %s" % exc)
        modes = {}
    for p, fmode in sorted(modes.items()):
        if fmode in ("120000", "160000"):
            fail("file type: %s is a %s; integration branches take regular files only" % (p, "symlink" if fmode == "120000" else "submodule"))

    # 2. Release settings (case-insensitive: a case-only variant is the same file on macOS).
    rel = [p for p in paths if cilib.matches_nocase(p, own.get("release_only", []))]
    for p in rel:
        fail("release: %s is release-only (changed only by a release from main)" % p)
    if not rel:
        lines.append("PASS release settings unchanged")

    # 3. Ownership.
    if mode != "pr":
        lines.append("NOTE push: ownership is checked on pull requests (a push has no stream branch); the ruleset should require pull requests")
        return ok, lines
    if not same_repo:
        fail("ownership: the head branch is in a fork; integration branches only take branches of this repository")
        return ok, lines
    m = BRANCH_RE.match(head_ref or "")
    stream = m.group(1) if m else None
    if not stream or stream not in own["streams"]:
        fail("ownership: head branch '%s' is not web2/<stream>/<topic> with a stream from %s (%s)" % (head_ref, OWNERSHIP, ", ".join(sorted(own["streams"]))))
        return ok, lines
    owns = own["streams"][stream].get("owns", [])
    bad = 0
    for p in paths:
        if cilib.matches_nocase(p, own.get("release_only", [])):
            continue  # already reported
        if cilib.matches_nocase(p, own.get("held", [])):
            fail("ownership: %s is held (Design Library or the front/header/footer templates); no stream changes it" % p)
            bad += 1
            continue
        if not cilib.matches(p, owns):
            others = sorted(s for s, d in own["streams"].items() if cilib.matches(p, d.get("owns", [])))
            fail("ownership: %s is outside stream '%s'%s" % (p, stream, (" (owned by: %s)" % ", ".join(others)) if others else " (no stream owns it)"))
            bad += 1
    if not bad:
        lines.append("PASS ownership: all %d changed files belong to stream '%s'" % (len(paths), stream))
    return ok, lines


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--mode", choices=["pr", "push"], required=True)
    ap.add_argument("--base", help="pr: default first parent of --head; push: the commit before the push")
    ap.add_argument("--head", default="HEAD")
    ap.add_argument("--head-ref", default="")
    ap.add_argument("--same-repo", default="true")
    ap.add_argument("--main-ref", default="origin/main")
    a = ap.parse_args(argv)
    base = a.base
    if a.mode == "pr" and not base:
        try:
            ps = cilib.parents(a.head)
        except cilib.GitError as exc:
            ps = []
            print("FAIL %s" % exc)
        if len(ps) != 2:
            print("FAIL HEAD is not the pull request's merge commit; cannot tell what it changes")
            cilib.write_summary("**Integration gate: FAIL**\n\nHEAD is not a merge commit.")
            return 1
        base = ps[0]
    if a.mode == "push" and (not base or base == cilib.ZERO_SHA):
        # A new branch: compare with where it left main.
        try:
            base = cilib.git("merge-base", a.main_ref, a.head).decode().strip()
            print("NOTE new branch: compared with its merge base on %s" % a.main_ref)
        except cilib.GitError as exc:
            print("FAIL new branch and no merge base with %s: %s" % (a.main_ref, exc))
            return 1
    ok, lines = check(base, a.head, a.mode, a.head_ref, a.same_repo.lower() == "true", a.main_ref)
    print("\n".join(lines))
    print("\nIntegration gate: %s" % ("PASS" if ok else "FAIL"))
    cilib.write_summary("**Integration gate: %s**\n\n%s" % ("PASS" if ok else "FAIL", "\n".join("- " + l.replace("`", "'") for l in lines)))
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
