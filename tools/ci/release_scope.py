#!/usr/bin/env python3
"""Decide whether the Release gate's strict steps run for this event.

Release workflow (.github/workflows/release.yml) runs on every pull request into main, on
every push to main and by hand. This script only decides what the job does once it runs:

  - push / workflow_dispatch / any other event: always the full gate (target=true).
  - pull_request: the full gate when the merge commit changes any release path (below);
    otherwise the job ends successfully and the step summary records why.
  - Any doubt (no merge commit, git error, unknown event): the full gate. Fail closed.

Release paths are conservative on purpose: the packages, the release tools and their
tests, the CI scripts and every workflow file. A change to CI is always a release-relevant
change because it can change what the gate checks.

Usage (in the workflow, after actions/checkout of the pull request's merge commit):
  python3 tools/ci/release_scope.py --event "$GITHUB_EVENT_NAME"
Local / tests:
  python3 tools/ci/release_scope.py --event pull_request --base <rev> --head <rev>
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import cilib  # noqa: E402

RELEASE_PATHS = [
    "wp-plugin/**",
    "wp-theme/**",
    "tools/release/**",
    "tests/release/**",
    "tools/ci/**",
    "tests/ci/**",
    ".github/**",
]


def decide(event, base=None, head="HEAD", cwd=None):
    """Return (target: bool, reason: str, paths: list)."""
    if event != "pull_request":
        return True, "event '%s': the full Release gate always runs (only pull requests can be out of scope)" % event, []
    try:
        if base is None:
            # actions/checkout checks out refs/pull/N/merge: first parent = the base tip it was merged into.
            ps = cilib.parents(head, cwd=cwd)
            if len(ps) != 2:
                return True, "HEAD is not a two-parent merge commit (%d parents): cannot tell what the pull request changes, so the full gate runs" % len(ps), []
            base = ps[0]
        paths = cilib.changed_paths(cilib.rev(base, cwd=cwd), cilib.rev(head, cwd=cwd), cwd=cwd)
    except cilib.GitError as exc:
        return True, "git could not list the changed files (%s): the full gate runs" % exc, []
    hits = [p for p in paths if cilib.matches(p, RELEASE_PATHS)]
    if hits:
        return True, "%d of %d changed files are release paths: the full gate runs" % (len(hits), len(paths)), hits
    if not paths:
        return False, "the pull request changes no file: nothing can reach production", []
    return False, "none of the %d changed files is a release path (%s): no package, release tool, CI script or workflow changes, so nothing here can reach production" % (len(paths), ", ".join(RELEASE_PATHS)), paths


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--event", required=True)
    ap.add_argument("--base", help="base revision (default: first parent of --head, which must be a merge commit)")
    ap.add_argument("--head", default="HEAD")
    a = ap.parse_args(argv)
    target, reason, paths = decide(a.event, a.base, a.head)
    print(("RELEASE TARGET: " if target else "NOT A RELEASE TARGET: ") + reason)
    for p in paths[:200]:
        print("  " + p)
    title = "Release gate: full check" if target else "Release gate: not a release target (strict steps skipped)"
    body = "**%s**\n\n%s" % (title, reason)
    if paths:
        body += "\n\n" + ("Release paths changed:" if target else "Changed files:") + "\n" + cilib.md_list(paths)
    cilib.write_summary(body)
    cilib.write_output(target="true" if target else "false")
    return 0


if __name__ == "__main__":
    sys.exit(main())
