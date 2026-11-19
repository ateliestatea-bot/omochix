#!/usr/bin/env python3
"""CI change guard: a pull request that changes CI cannot pass silently.

The workflow runs on pull_request_target, so GitHub takes the workflow file from the BASE
branch, never from the pull request, and the workflow runs this script from a copy of
tools/ci/ taken from the base commit. The rules, the protected paths and the required-check
inventory are the base's. The pull request's files are only read as data (git plumbing and
a YAML reader); nothing from the pull request runs.

  1. SECURITY findings of the workflow policy (write permission, pull_request_target or
     workflow_run outside the allowlist, unpinned action, persisted token, script
     injection, a second job reporting a required check's name ...):
       always FAIL. No label accepts them; the policy has to change first, in its own
       reviewed pull request.
  2. Other workflow policy findings (a required check would weaken: removed or renamed
     job, paths filter, continue-on-error, missing trigger, a gate command removed ...):
       FAIL unless APPROVED with 'ci-weakening-approved'.
  2b. REVIEW findings (a step that the base inventory does not pin yet, e.g. a new release
     check): FAIL unless APPROVED with 'ci-change-reviewed' (or the weakening label).
  3. Protected paths changed (workflows, CI scripts and tests, the ownership file, the
     Release gate and release tool code):
       pull request -> FAIL unless APPROVED with 'ci-change-reviewed' (or the weakening label).
       push         -> NOTE only (a push cannot be stopped afterwards; the ruleset does that).

APPROVED means both: the label is set, AND the pull request description has the line
'<label>: <digest>', where <digest> is printed by a failing run. The digest covers the
base and head blobs of every changed protected file and the accepted findings, so a
commit pushed after the review (or a moved base that changes those files) needs a new
review: the label alone never carries over to content nobody has read.

Usage: python3 -I <base copy>/ci_guard.py --base <rev> --head <rev> [--event pull_request|push]
Labels and the description come from the event payload through environment variables
(PR_LABELS, PR_BODY), never from the shell.
"""
import argparse
import json
import os
import re
import hashlib
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import cilib  # noqa: E402
import workflow_policy  # noqa: E402

INVENTORY = os.path.join(os.path.dirname(os.path.abspath(__file__)), "required-checks.json")
FALLBACK_PROTECTED = [".github/**", "tools/ci/**", "tests/ci/**", "docs/web2/ownership.json",
                      "tests/release/release-gate.php", "tests/release/*.py", "tools/release/*.py"]


def digest(paths, accepted, base, head, cwd=None):
    """Fingerprint of what a reviewer approves: the protected files on both sides and the accepted findings."""
    h = hashlib.sha256(b"omochix-ci-guard v1\n")
    for p in sorted(paths):
        h.update(("%s\0%s\0%s\n" % (p, cilib.blob(base, p, cwd=cwd) or "-", cilib.blob(head, p, cwd=cwd) or "-")).encode())
    for x in sorted(accepted):
        h.update(("finding\0%s\n" % x).encode())
    return h.hexdigest()


def approved_in(body, label, full):
    """The description names this label with a prefix (16+ hex) of this exact digest."""
    text = (body or "").replace("\r\n", "\n").replace("\r", "\n")  # GitHub stores descriptions with CRLF
    for m in re.finditer(r"(?im)^[ \t>*-]*%s:[ \t]*`?([0-9a-f]{16,64})`?[ \t]*$" % re.escape(label), text):
        if full.startswith(m.group(1).lower()):
            return True
    return False


def guard(base, head, event, labels, inventory=None, cwd=None, body=""):
    """Return (ok, lines)."""
    lines, ok = [], True
    if event == "pull_request_target":
        event = "pull_request"
    inv = inventory
    if inv is None:
        try:
            with open(INVENTORY, encoding="utf-8") as fh:
                inv = json.load(fh)
        except (OSError, ValueError) as exc:
            return False, ["FAIL cannot read the guard's inventory (%s)" % exc]
    reviewed = inv.get("labels", {}).get("reviewed", "ci-change-reviewed")
    weakening = inv.get("labels", {}).get("weakening", "ci-weakening-approved")
    protected = inv.get("protected_paths") or FALLBACK_PROTECTED
    try:
        paths = cilib.changed_paths(cilib.rev(base, cwd=cwd), cilib.rev(head, cwd=cwd), cwd=cwd)
    except cilib.GitError as exc:
        return False, ["FAIL git could not list the changed files: %s" % exc]
    hits = [p for p in paths if cilib.matches(p, protected)]

    try:
        violations = workflow_policy.check(rev=head, inventory=inv, cwd=cwd)
    except (cilib.GitError, ValueError, KeyError) as exc:
        violations = [workflow_policy.SECURITY + "the workflow policy could not run: %s" % exc]
    security = [x for x in violations if x.startswith(workflow_policy.SECURITY)]
    review = [x for x in violations if x.startswith(workflow_policy.REVIEW)]
    weak = [x for x in violations if not x.startswith((workflow_policy.SECURITY, workflow_policy.REVIEW))]

    try:
        full = digest(hits, weak + review, base, head, cwd=cwd)
    except cilib.GitError as exc:
        return False, ["FAIL git could not read the changed files: %s" % exc]
    short = full[:16]
    pr = event == "pull_request"

    def approved(label):
        return pr and label in labels and approved_in(body, label, full)

    def how(label):
        if label in labels:
            return ("NOTE the '%s' label is set, but the description does not carry this change's digest "
                    "(new commits or a moved base since the review?). After reading the current diff, put this line in the description: %s: %s"
                    % (label, label, short))
        return "NOTE after reading the diff, set the '%s' label and put this line in the description: %s: %s" % (label, label, short)

    if security:
        ok = False
        lines.append("FAIL workflow policy: %d security findings (no label accepts these; change the policy first in its own reviewed pull request):" % len(security))
        lines += ["FAIL   " + x for x in security]
    if weak:
        if approved(weakening):
            lines.append("WARN workflow policy: %d findings, accepted with the '%s' label for digest %s:" % (len(weak), weakening, short))
            lines += ["WARN   " + x for x in weak]
        else:
            ok = False
            lines.append("FAIL workflow policy: %d findings (these can weaken a required check):" % len(weak))
            lines += ["FAIL   " + x for x in weak]
            if pr:
                lines.append("NOTE if this is intended, change the ruleset in the same step.")
                lines.append(how(weakening))
    if review:
        if approved(reviewed) or approved(weakening):
            lines.append("WARN workflow policy: %d new steps not pinned on the base, reviewed for digest %s:" % (len(review), short))
            lines += ["WARN   " + x for x in review]
        else:
            ok = False
            lines.append("FAIL workflow policy: %d new steps are not pinned on the base (a new release check, or a step that could change the tree before a check runs); read them:" % len(review))
            lines += ["FAIL   " + x for x in review]
            if pr and not weak:
                lines.append(how(reviewed))
    if not violations:
        lines.append("PASS workflow policy (base inventory against head workflows)")

    if not hits:
        lines.append("PASS no CI or gate file changes (%d changed files)" % len(paths))
    elif not pr:
        lines.append("NOTE %d CI or gate files changed by a push (cannot be stopped afterwards; the ruleset must require pull requests):" % len(hits))
        lines += ["NOTE   " + p for p in hits]
    elif approved(reviewed) or approved(weakening):
        lines.append("WARN %d CI or gate files changed; reviewed for digest %s:" % (len(hits), short))
        lines += ["WARN   " + p for p in hits]
    else:
        ok = False
        lines.append("FAIL %d CI or gate files changed. An owner must read this diff and approve this exact content:" % len(hits))
        lines += ["FAIL   " + p for p in hits]
        lines.append(how(reviewed))
    return ok, lines


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--base", required=True)
    ap.add_argument("--head", default="HEAD")
    ap.add_argument("--event", default="pull_request")
    ap.add_argument("--labels", default=os.environ.get("PR_LABELS", ""))
    ap.add_argument("--body", default=os.environ.get("PR_BODY", ""))
    a = ap.parse_args(argv)
    labels = [x.strip() for x in a.labels.split(",") if x.strip()]
    base = a.base
    if a.event not in ("pull_request", "pull_request_target", "push"):
        print("CI change guard: FAIL (unexpected event '%s')" % a.event)
        return 1
    if a.event == "push" and base == cilib.ZERO_SHA:
        try:
            base = cilib.git("merge-base", "origin/main", a.head).decode().strip()
        except cilib.GitError:
            base = a.head + "^"
    ok, lines = guard(base, a.head, a.event, labels, body=a.body)
    print("\n".join(lines))
    print("\nCI change guard: %s" % ("PASS" if ok else "FAIL"))
    cilib.write_summary("**CI change guard: %s**\n\n%s" % ("PASS" if ok else "FAIL", "\n".join("- " + l.replace("`", "'") for l in lines)))
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
