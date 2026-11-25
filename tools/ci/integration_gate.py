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

Sync exception: merging main into the integration branch passes with that reason recorded,
because the changes are main's own released state (main is protected by its own Release
gate and ruleset). Main is always refs/remotes/origin/main, read by its exact name
(git show-ref --verify): a short name such as "origin/main" would resolve to a tag of that
name first, and even the full name falls back to a tag or branch of that name when the real
ref is missing (git rev-parse), so anybody who can push a tag could pose as main. No other
main ref is accepted; without that exact ref nothing is a sync.
A sync source must be a state main had and the integration
branch does not have yet: a commit on main's first-parent line, or the merged head of such a
commit with the same tree (the pull request head GitHub merged), not reachable from the base.
Two shapes pass, in both modes, and nothing else:
  - the incoming commit is such a main state (pull request head = main's commit; the GitHub
    merge of it for a push), or
  - the incoming commit is a sync merge: exactly two parents, the first is the base, the
    second is a main state as above, and its tree is exactly the clean merge of the two
    (git merge-tree): nothing added or resolved by hand. This is the head a sync pull
    request needs when the ruleset requires branches to be up to date with the base.
A push additionally has to be one merge commit on top of the commit before the push whose
tree is the incoming commit's (GitHub's merge of the pull request). Any git error, a
missing or ambiguous main, a direct push, a squash or rebase merge, several commits or an
edited merge is not a sync and is checked normally (fail-closed).

Usage (workflow, after checkout of the merge commit with full history):
  python3 tools/ci/integration_gate.py --mode pr --head-ref "$HEAD_REF" --same-repo "$SAME_REPO"
  python3 tools/ci/integration_gate.py --mode push --base "$BEFORE" --head "$AFTER"
Exit 0 when every check passes, 1 otherwise.
"""
import argparse
import json
import os
import re
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import cilib  # noqa: E402

CORE_MAIN = "wp-plugin/omochix-core/omochix-core.php"
CORE_README = "wp-plugin/omochix-core/readme.txt"
THEME_STYLE = "wp-theme/omochix-theme/style.css"
MARKER_RE = re.compile(r"^// omochix-industry-hub-version: .*$", re.M)
SHA_RE = re.compile(r"^[0-9a-f]{40}$")
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


MAIN_REF = "refs/remotes/origin/main"


class Unknown(Exception):
    """git could not answer: never read as a "no" that lets a sync pass."""


def _git(*args, cwd=None):
    try:
        return cilib.git(*args, cwd=cwd).decode()
    except cilib.GitError as exc:
        raise Unknown(str(exc))


def _commit(name, cwd=None):
    sha = _git("rev-parse", "--verify", "--quiet", "--end-of-options", name + "^{commit}", cwd=cwd).strip()
    if not SHA_RE.fullmatch(sha):
        raise Unknown("%r is not a commit" % name)
    return sha


def exact_ref(ref, cwd=None):
    """The commit a ref points at, by its exact full name only; Unknown when that ref does not exist.

    git rev-parse is not exact: when refs/remotes/origin/main is missing it falls back to
    refs/tags/refs/remotes/origin/main, then refs/heads/refs/remotes/origin/main. git show-ref
    --verify takes the full name as it is and fails otherwise. The ref must point straight at a
    commit (no tag object to peel).
    """
    out = _git("show-ref", "--verify", "--hash", "--", ref, cwd=cwd).split()
    if len(out) != 1 or not SHA_RE.fullmatch(out[0]):
        raise Unknown("no exact ref %s" % ref)
    if _git("cat-file", "-t", out[0], cwd=cwd).strip() != "commit":
        raise Unknown("%s does not point at a commit" % ref)
    return out[0]


def _tree(commit, cwd=None):
    sha = _git("rev-parse", "--verify", "--quiet", "--end-of-options", commit + "^{tree}", cwd=cwd).strip()
    if not SHA_RE.fullmatch(sha):
        raise Unknown("no tree for %s" % commit[:12])
    return sha


def _parents(commit, cwd=None):
    out = _git("rev-list", "--parents", "-n", "1", "--end-of-options", commit, cwd=cwd).split()
    if not out or out[0] != commit or not all(SHA_RE.fullmatch(x) for x in out):
        raise Unknown("unexpected parent list for %s" % commit[:12])
    return out[1:]


def _ancestor(a, b, cwd=None):
    """True or False from git merge-base --is-ancestor; anything else raises Unknown."""
    try:
        res = subprocess.run(["git", "merge-base", "--is-ancestor", a, b], cwd=cwd,
                             stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, check=False)
    except OSError as exc:
        raise Unknown(str(exc))
    if res.returncode == 0:
        return True
    if res.returncode == 1:
        return False
    raise Unknown("git merge-base --is-ancestor %s %s exited %d" % (a[:12], b[:12], res.returncode))


def _clean_merge(a, b, cwd=None):
    """The tree of the clean merge of a and b, or None when they conflict; Unknown on errors."""
    try:
        res = subprocess.run(["git", "merge-tree", "--write-tree", a, b], cwd=cwd,
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
    except OSError as exc:
        raise Unknown(str(exc))
    out = res.stdout.decode("utf-8", "replace").split("\n")
    if res.returncode == 1:
        return None
    if res.returncode != 0 or not SHA_RE.fullmatch(out[0].strip()) or any(x.strip() for x in out[1:]):
        raise Unknown("git merge-tree exited %d with unexpected output" % res.returncode)
    return out[0].strip()


def main_state(commit, main_sha, base_sha, cwd=None):
    """None when commit is a state main had that base does not have, else the reason.

    A state main had: a commit on main's first-parent line, or a parent of one with the same
    tree (the pull request head that commit merged). Only main's first-parent commits not
    reachable from base count, so an old state cannot be brought back as a sync.
    """
    tree = _tree(commit, cwd=cwd)
    for line in _git("log", "--first-parent", "--format=%H %T %P", main_sha, "--not", base_sha, "--", cwd=cwd).splitlines():
        f = line.split()
        if len(f) < 2 or not all(SHA_RE.fullmatch(x) for x in f):
            raise Unknown("unexpected git log output")
        if commit == f[0] or (commit in f[2:] and tree == f[1]):
            return None
    return "%s is not a state of main that the base lacks (an intermediate or side commit, or an old state)" % commit[:12]


def sync_source(base_sha, incoming, main_sha, cwd=None):
    """None when incoming is a main state (shape 1) or a sync merge (shape 2) for base, else the reason."""
    if _ancestor(incoming, main_sha, cwd=cwd):
        return main_state(incoming, main_sha, base_sha, cwd=cwd)
    ps = _parents(incoming, cwd=cwd)
    if len(ps) != 2:
        return "%s is not contained in main and is not a two-parent sync merge" % incoming[:12]
    if ps[0] != base_sha:
        return "the sync merge's first parent %s is not the base %s (an old base?)" % (ps[0][:12], base_sha[:12])
    if not _ancestor(ps[1], main_sha, cwd=cwd):
        return "the sync merge's second parent %s is not contained in main" % ps[1][:12]
    why = main_state(ps[1], main_sha, base_sha, cwd=cwd)
    if why:
        return why
    clean = _clean_merge(ps[0], ps[1], cwd=cwd)
    if clean is None:
        return "the base and %s do not merge cleanly, so the sync merge carries a resolution of its own" % ps[1][:12]
    if clean != _tree(incoming, cwd=cwd):
        return "the sync merge's content differs from the clean merge of its parents (changes added in the merge)"
    return None


def sync_check(mode, base_sha, head_sha, main_sha, cwd=None):
    """None when this pull request or push is a sync from main, else the reason. Never raises."""
    try:
        if mode == "pr":
            ps = _parents(head_sha, cwd=cwd)
            if len(ps) != 2 or ps[0] != base_sha:
                return "the judged commit is not the merge of the base and the pull request head"
            incoming = ps[1]
        else:
            if _ancestor(head_sha, main_sha, cwd=cwd):
                return main_state(head_sha, main_sha, base_sha, cwd=cwd)   # a fast-forward to main
            ps = _parents(head_sha, cwd=cwd)
            if len(ps) != 2:
                return "the push is not a merge commit with two parents (a direct push, a squash or rebase merge)"
            if ps[0] != base_sha:
                return "the merge's first parent %s is not the commit before the push %s (several commits or an old base)" % (ps[0][:12], base_sha[:12])
            incoming = ps[1]
        if _ancestor(incoming, base_sha, cwd=cwd):
            return "%s is already in the integration branch (an old sync source)" % incoming[:12]
        why = sync_source(base_sha, incoming, main_sha, cwd=cwd)
        if why:
            return why
        if _ancestor(incoming, main_sha, cwd=cwd):
            # Shape 1: GitHub's merge of a main state must be exactly the clean merge.
            clean = _clean_merge(base_sha, incoming, cwd=cwd)
            if clean is None:
                return "the base and %s do not merge cleanly, so the merge commit carries a resolution of its own" % incoming[:12]
            if clean != _tree(head_sha, cwd=cwd):
                return "the merge commit's content differs from the clean merge of its parents (changes added in the merge)"
        elif _tree(head_sha, cwd=cwd) != _tree(incoming, cwd=cwd):
            # Shape 2: the sync merge already contains the base, so merging it changes nothing.
            return "the merge commit's content differs from the sync merge it merged"
        return None
    except Unknown as exc:
        return "git could not answer, so this is not taken as a sync: %s" % exc
    except Exception as exc:  # noqa: BLE001 - anything unexpected is not a sync either
        return "unexpected error, so this is not taken as a sync: %s" % type(exc).__name__


def check(base, head, mode, head_ref="", same_repo=True, main_ref=MAIN_REF, cwd=None):
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

    main_sha = None
    if main_ref != MAIN_REF:
        lines.append("NOTE main ref %r is not %s: the sync-from-main exception cannot apply" % (main_ref, MAIN_REF))
    else:
        try:
            main_sha = exact_ref(main_ref, cwd=cwd)
        except Unknown:
            lines.append("NOTE %s is not available: the sync-from-main exception cannot apply" % main_ref)
    if main_sha:
        why = sync_check(mode, base_sha, head_sha, main_sha, cwd=cwd)
        if why is None:
            lines.append("PASS sync from main (%s %s): these changes are main's released state" % (main_ref, main_sha[:12]))
            return True, lines
        lines.append("NOTE not a sync from main: %s" % why)

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
    ap.add_argument("--main-ref", default=MAIN_REF)
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
    if a.main_ref != MAIN_REF:
        print("FAIL --main-ref must be %s\n\nIntegration gate: FAIL" % MAIN_REF)
        return 1
    if a.mode == "push" and (not base or base == cilib.ZERO_SHA):
        # A new branch: compare with where it left main (the exact ref, never a tag of that name).
        try:
            try:
                main_sha = exact_ref(a.main_ref)
            except Unknown as exc:
                raise cilib.GitError(str(exc))
            base = cilib.git("merge-base", main_sha, a.head).decode().strip()
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
