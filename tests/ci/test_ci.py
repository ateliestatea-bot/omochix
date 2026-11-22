"""CI self-tests: Release scope, Integration gate, CI change guard, workflow policy.

Each case builds a throwaway git repository with the real tools/ci/, workflows and
ownership file, makes a branch, and merges it with --no-ff the way GitHub builds
refs/pull/N/merge (first parent = base tip, second parent = the pull request head).
Normal and failure cases for every gate. Standard library only; Python 3.9+.

Run: python3 -m unittest discover -s tests/ci -v
"""
import io
import json
import os
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile
import unittest

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.join(REPO, "tools", "ci"))
import cilib  # noqa: E402
import ci_guard  # noqa: E402
import integration_gate  # noqa: E402
import release_scope  # noqa: E402
import workflow_policy  # noqa: E402

ENV = {
    "GIT_CONFIG_GLOBAL": os.devnull, "GIT_CONFIG_NOSYSTEM": "1",
    "GIT_AUTHOR_NAME": "ci-test", "GIT_AUTHOR_EMAIL": "ci-test@example.invalid",
    "GIT_COMMITTER_NAME": "ci-test", "GIT_COMMITTER_EMAIL": "ci-test@example.invalid",
}
_saved = {}


def slurp(path, mode="r"):
    with open(path, mode, **({} if "b" in mode else {"encoding": "utf-8"})) as fh:
        return fh.read()


def setUpModule():
    for k, v in ENV.items():
        _saved[k] = os.environ.get(k)
        os.environ[k] = v
    for k in ("GITHUB_OUTPUT", "GITHUB_STEP_SUMMARY"):
        _saved[k] = os.environ.pop(k, None)


def tearDownModule():
    for k, v in _saved.items():
        if v is None:
            os.environ.pop(k, None)
        else:
            os.environ[k] = v


CORE = """<?php
/**
 * Plugin Name: OmochiX Core
 * Version: 1.10.0
 */
define( 'OMOCHIX_CORE_VERSION', '1.10.0' );
"""
README = "=== OmochiX Core ===\nStable tag: 1.10.0\n\n== Changelog ==\n= 1.10.0 =\n"
STYLE = "/*\nTheme Name: OmochiX\nVersion: 1.30.0\n*/\n"
MARKED = "<?php\n// omochix-industry-hub-version: 1.10.0\nfunction x() {}\n// omochix-industry-hub-version: 1.10.0 (end)\n"


class Repo:
    """A throwaway repository: main with release-like files and the real CI files."""

    def __init__(self, with_ci=True):
        self.dir = tempfile.mkdtemp(prefix="omx-ci-test-")
        self.git("init", "-q", "-b", "main")
        files = {
            "wp-plugin/omochix-core/omochix-core.php": CORE,
            "wp-plugin/omochix-core/readme.txt": README,
            "wp-plugin/omochix-core/includes/industry-hub.php": MARKED,
            "wp-theme/omochix-theme/style.css": STYLE,
            "wp-theme/omochix-theme/functions.php": "<?php\n",
            "wp-theme/omochix-theme/front-page.php": "<?php\n",
            "tools/release/release.json": "{}\n",
            "docs/readme.md": "docs\n",
        }
        for p, c in files.items():
            self.write(p, c)
        if with_ci:
            for sub in ("tools/ci", ".github/workflows"):
                shutil.copytree(os.path.join(REPO, sub), os.path.join(self.dir, sub),
                                ignore=shutil.ignore_patterns("__pycache__"))
            self.write("docs/web2/ownership.json", slurp(os.path.join(REPO, "docs/web2/ownership.json")))
        self.commit("main")
        self.git("update-ref", "refs/remotes/origin/main", "main")

    def git(self, *args):
        return subprocess.run(["git", *args], cwd=self.dir, check=True, stdout=subprocess.PIPE,
                              stderr=subprocess.PIPE).stdout.decode().strip()

    def write(self, path, content):
        full = os.path.join(self.dir, path)
        os.makedirs(os.path.dirname(full), exist_ok=True)
        with open(full, "w", encoding="utf-8") as fh:
            fh.write(content)

    def read(self, path):
        with open(os.path.join(self.dir, path), encoding="utf-8") as fh:
            return fh.read()

    def commit(self, msg):
        self.git("add", "-A")
        self.git("commit", "-q", "--allow-empty", "-m", msg)
        return self.git("rev-parse", "HEAD")

    def apply(self, changes):
        for p, c in changes.items():
            if isinstance(c, tuple) and c[0] == "rename":
                self.git("mv", p, c[1])
            elif c is None:
                self.git("rm", "-q", p)
            elif callable(c):
                self.write(p, c(self.read(p) if os.path.exists(os.path.join(self.dir, p)) else ""))
            else:
                self.write(p, c)

    def pr(self, branch, changes, base="main"):
        """Branch from base, apply changes, merge --no-ff like refs/pull/N/merge. Returns the merge SHA."""
        self.git("checkout", "-q", "-B", branch, base)
        self.apply(changes)
        self.commit("change on " + branch)
        self.git("checkout", "-q", "--detach", base)
        self.git("merge", "-q", "--no-ff", "--no-edit", branch)
        return self.git("rev-parse", "HEAD")

    def cleanup(self):
        shutil.rmtree(self.dir, ignore_errors=True)


class RepoCase(unittest.TestCase):
    with_ci = True

    def setUp(self):
        self.r = Repo(with_ci=self.with_ci)
        self.addCleanup(self.r.cleanup)


# --------------------------------------------------------------------------- Release scope

class ReleaseScope(RepoCase):
    def decide(self, event="pull_request", **kw):
        return release_scope.decide(event, cwd=self.r.dir, **kw)

    def test_docs_only_pr_is_not_a_target_and_says_why(self):
        self.r.pr("docs", {"docs/readme.md": "new\n", "docs/web2/notes.md": "x\n"})
        target, reason, paths = self.decide()
        self.assertFalse(target)
        self.assertIn("none of the 2 changed files is a release path", reason)
        self.assertEqual(paths, ["docs/readme.md", "docs/web2/notes.md"])

    def test_not_a_target_writes_summary_and_output(self):
        self.r.pr("docs", {"docs/readme.md": "new\n"})
        out, summ = os.path.join(self.r.dir, "..out"), os.path.join(self.r.dir, "..sum")
        res = subprocess.run([sys.executable, os.path.join(self.r.dir, "tools/ci/release_scope.py"), "--event", "pull_request"],
                             cwd=self.r.dir, env=dict(os.environ, GITHUB_OUTPUT=out, GITHUB_STEP_SUMMARY=summ),
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        self.assertEqual(res.returncode, 0, res.stderr)
        self.assertIn("target=false", slurp(out))
        self.assertIn("not a release target", slurp(summ))
        self.assertIn("docs/readme.md", slurp(summ))

    def test_release_paths_are_targets(self):
        cases = {
            "theme": {"wp-theme/omochix-theme/functions.php": "<?php // x\n"},
            "plugin": {"wp-plugin/omochix-core/includes/new.php": "<?php\n"},
            "release-tools": {"tools/release/release.json": "{\"x\":1}\n"},
            "release-tests": {"tests/release/new.py": "x\n"},
            "workflow": {".github/workflows/tests.yml": lambda s: s + "\n# x\n"},
            "ci-scripts": {"tools/ci/cilib.py": lambda s: s + "\n# x\n"},
            "mixed": {"docs/readme.md": "x\n", "wp-theme/omochix-theme/style.css": STYLE + "/* x */\n"},
        }
        for name, changes in cases.items():
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                self.r.pr("b-" + name, changes)
                target, reason, paths = self.decide()
                self.assertTrue(target, reason)
                self.assertTrue(paths)

    def test_moving_a_release_file_out_is_a_target(self):
        # With rename detection only docs/moved.php would be listed.
        self.r.pr("mv", {"wp-theme/omochix-theme/functions.php": ("rename", "docs/moved.php")})
        target, _, paths = self.decide()
        self.assertTrue(target)
        self.assertIn("wp-theme/omochix-theme/functions.php", paths)

    def test_deleting_a_release_file_is_a_target(self):
        self.r.pr("rm", {"wp-plugin/omochix-core/includes/industry-hub.php": None})
        self.assertTrue(self.decide()[0])

    def test_push_and_manual_runs_always_check_everything(self):
        self.r.pr("docs", {"docs/readme.md": "x\n"})
        for event in ("push", "workflow_dispatch", "schedule", "something-new"):
            with self.subTest(event):
                target, reason, _ = self.decide(event)
                self.assertTrue(target)
                self.assertIn("always runs", reason)

    def test_fails_closed_without_a_merge_commit(self):
        self.r.write("docs/readme.md", "x\n")
        self.r.commit("plain commit")
        target, reason, _ = self.decide()
        self.assertTrue(target)
        self.assertIn("not a two-parent merge commit", reason)

    def test_fails_closed_on_git_errors(self):
        self.r.pr("docs", {"docs/readme.md": "x\n"})
        target, reason, _ = self.decide(base="0123456789abcdef0123456789abcdef01234567")
        self.assertTrue(target)
        self.assertIn("git could not list", reason)

    def test_empty_pull_request_is_not_a_target(self):
        self.r.git("checkout", "-q", "-B", "empty", "main")
        self.r.commit("empty")
        self.r.git("checkout", "-q", "--detach", "main")
        self.r.git("merge", "-q", "--no-ff", "--no-edit", "empty")
        target, reason, _ = self.decide()
        self.assertFalse(target)
        self.assertIn("changes no file", reason)


# --------------------------------------------------------------------------- Integration gate

class IntegrationGate(RepoCase):
    def setUp(self):
        super().setUp()
        self.r.git("branch", "integrate/web2", "main")

    def gate(self, head_ref, same_repo=True, mode="pr"):
        head = self.r.git("rev-parse", "HEAD")
        base = self.r.git("rev-parse", "HEAD^1")
        return integration_gate.check(base, head, mode, head_ref, same_repo, cwd=self.r.dir)

    def pr(self, branch, changes):
        return self.r.pr(branch, changes, base="integrate/web2")

    def assertFailsWith(self, result, text):
        ok, lines = result
        self.assertFalse(ok, "\n".join(lines))
        self.assertTrue(any(text in l for l in lines if l.startswith("FAIL")), "\n".join(lines))

    def test_owned_change_passes(self):
        self.pr("web2/kh/glossary-cpt", {
            "wp-theme/omochix-theme/single-omx_glossary.php": "<?php\n",
            "wp-plugin/omochix-core/includes/web2/glossary-cpt.php": "<?php\n",
            "docs/web2/kh/plan.md": "x\n",
        })
        ok, lines = self.gate("web2/kh/glossary-cpt")
        self.assertTrue(ok, "\n".join(lines))
        self.assertTrue(any("belong to stream 'kh'" in l for l in lines))

    def test_shared_file_outside_the_stream_fails_and_names_the_owner(self):
        self.pr("web2/kh/x", {"wp-theme/omochix-theme/functions.php": "<?php // loader\n"})
        self.assertFailsWith(self.gate("web2/kh/x"), "outside stream 'kh' (owned by: hooks)")

    def test_the_owner_may_change_the_shared_file(self):
        self.pr("web2/hooks/loader", {"wp-theme/omochix-theme/functions.php": "<?php // loader\n"})
        self.assertTrue(self.gate("web2/hooks/loader")[0])

    def test_unowned_path_fails(self):
        self.pr("web2/kh/x", {"random/file.txt": "x\n"})
        self.assertFailsWith(self.gate("web2/kh/x"), "no stream owns it")

    def test_theme_version_change_fails(self):
        self.pr("web2/hooks/v", {"wp-theme/omochix-theme/style.css": STYLE.replace("1.30.0", "1.31.0")})
        res = self.gate("web2/hooks/v")
        self.assertFailsWith(res, "theme style.css Version changed (1.30.0 -> 1.31.0)")
        self.assertFailsWith(res, "style.css is release-only")

    def test_core_version_change_fails(self):
        self.pr("web2/hooks/v", {"wp-plugin/omochix-core/omochix-core.php": CORE.replace("'1.10.0'", "'1.11.0'")})
        self.assertFailsWith(self.gate("web2/hooks/v"), "OMOCHIX_CORE_VERSION changed")

    def test_industry_hub_marker_change_fails(self):
        self.pr("web2/ih/m", {"wp-plugin/omochix-core/includes/industry-hub.php": MARKED.replace("1.10.0", "1.11.0")})
        self.assertFailsWith(self.gate("web2/ih/m"), "Industry Hub version markers changed")

    def test_release_settings_fail(self):
        self.pr("web2/hooks/r", {"tools/release/release.json": "{\"release\": \"x\"}\n"})
        self.assertFailsWith(self.gate("web2/hooks/r"), "tools/release/release.json is release-only")

    def test_held_paths_fail_for_every_stream(self):
        for stream, path in (("ux", "wp-theme/omochix-theme/front-page.php"),
                             ("hooks", "wp-theme/omochix-theme/inc/design-library.php"),
                             ("ux", "docs/design-library/x.md")):
            with self.subTest(path):
                self.r.git("checkout", "-q", "integrate/web2")
                self.pr("web2/%s/held-%d" % (stream, abs(hash(path)) % 1000), {path: "x\n"})
                self.assertFailsWith(self.gate("web2/%s/held" % stream), "is held")

    def test_symlinks_and_submodules_fail(self):
        self.r.git("checkout", "-q", "-B", "web2/kh/link", "integrate/web2")
        os.symlink("../front-page.php", os.path.join(self.r.dir, "wp-theme/omochix-theme/single-omx_glossary.php"))
        self.r.commit("symlink into a held file")
        self.r.git("checkout", "-q", "--detach", "integrate/web2")
        self.r.git("merge", "-q", "--no-ff", "--no-edit", "web2/kh/link")
        self.assertFailsWith(self.gate("web2/kh/link"), "is a symlink")

    def test_case_variants_of_held_and_release_files_fail(self):
        for path, text in (("wp-theme/omochix-theme/Front-Page.php", "is held"),
                           ("wp-theme/omochix-theme/STYLE.css", "release-only")):
            with self.subTest(path):
                self.r.git("checkout", "-q", "integrate/web2")
                self.pr("web2/ux/case-" + str(len(path)), {path: "x\n"})
                self.assertFailsWith(self.gate("web2/ux/case-" + str(len(path))), text)

    def test_branch_name_and_stream_must_be_known(self):
        self.pr("feature/x", {"docs/web2/kh/a.md": "x\n"})
        self.assertFailsWith(self.gate("feature/x"), "is not web2/<stream>/<topic>")
        self.assertFailsWith(self.gate("web2/zzz/x"), "is not web2/<stream>/<topic>")
        self.assertFailsWith(self.gate(""), "is not web2/<stream>/<topic>")

    def test_fork_pull_requests_fail(self):
        self.pr("web2/kh/x", {"docs/web2/kh/a.md": "x\n"})
        self.assertFailsWith(self.gate("web2/kh/x", same_repo=False), "fork")

    def test_ownership_comes_from_the_base_not_the_head(self):
        def widen(s):
            d = json.loads(s)
            d["streams"]["kh"]["owns"].append("**")
            return json.dumps(d)
        self.pr("web2/kh/widen", {"docs/web2/ownership.json": widen, "wp-theme/omochix-theme/functions.php": "<?php // x\n"})
        res = self.gate("web2/kh/widen")
        self.assertFailsWith(res, "wp-theme/omochix-theme/functions.php is outside stream 'kh'")
        self.assertFailsWith(res, "docs/web2/ownership.json is outside stream 'kh' (owned by: ci)")

    def test_missing_ownership_on_the_base_fails(self):
        self.r.git("checkout", "-q", "integrate/web2")
        self.r.git("rm", "-q", "docs/web2/ownership.json")
        self.r.commit("drop ownership")
        self.pr("web2/kh/x", {"docs/web2/kh/a.md": "x\n"})
        self.assertFailsWith(self.gate("web2/kh/x"), "ownership.json is missing or unreadable on the base commit")

    def test_sync_from_main_passes_even_with_a_release(self):
        self.r.git("checkout", "-q", "main")
        self.r.write("wp-theme/omochix-theme/style.css", STYLE.replace("1.30.0", "1.31.0"))
        self.r.write("tools/release/release.json", "{\"release\": \"1.31.0\"}\n")
        self.r.commit("release 1.31.0 on main")
        self.r.git("update-ref", "refs/remotes/origin/main", "main")
        self.r.git("checkout", "-q", "--detach", "integrate/web2")
        self.r.git("merge", "-q", "--no-ff", "--no-edit", "main")
        ok, lines = self.gate("main")
        self.assertTrue(ok, "\n".join(lines))
        self.assertTrue(any("sync from main" in l for l in lines))

    def test_a_branch_not_contained_in_main_is_not_a_sync(self):
        # Same content as a main release, but the commit is not main's: checked normally.
        self.pr("main-lookalike", {"wp-theme/omochix-theme/style.css": STYLE.replace("1.30.0", "1.31.0")})
        self.assertFailsWith(self.gate("main"), "theme style.css Version changed")

    def test_push_checks_versions_and_release_settings(self):
        self.r.git("checkout", "-q", "integrate/web2")
        before = self.r.git("rev-parse", "HEAD")
        self.r.write("docs/web2/kh/a.md", "x\n")
        after = self.r.commit("docs push")
        ok, lines = integration_gate.check(before, after, "push", cwd=self.r.dir)
        self.assertTrue(ok, "\n".join(lines))
        self.assertTrue(any("push: ownership is checked on pull requests" in l for l in lines))
        self.r.write("wp-theme/omochix-theme/style.css", STYLE.replace("1.30.0", "1.31.0"))
        after2 = self.r.commit("version push")
        ok, lines = integration_gate.check(after, after2, "push", cwd=self.r.dir)
        self.assertFalse(ok)

    def test_command_line_pr_mode_and_new_branch_push(self):
        self.pr("web2/kh/x", {"docs/web2/kh/a.md": "x\n"})
        script = os.path.join(self.r.dir, "tools/ci/integration_gate.py")
        res = subprocess.run([sys.executable, script, "--mode", "pr", "--head-ref", "web2/kh/x", "--same-repo", "true"],
                             cwd=self.r.dir, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        self.assertEqual(res.returncode, 0, res.stdout.decode() + res.stderr.decode())
        res = subprocess.run([sys.executable, script, "--mode", "pr", "--head-ref", "feature/x"],
                             cwd=self.r.dir, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        self.assertEqual(res.returncode, 1)
        res = subprocess.run([sys.executable, script, "--mode", "push", "--base", cilib.ZERO_SHA, "--head", "HEAD"],
                             cwd=self.r.dir, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        self.assertEqual(res.returncode, 0, res.stdout.decode())
        self.assertIn("new branch", res.stdout.decode())

    def test_command_line_pr_mode_needs_a_merge_commit(self):
        self.r.git("checkout", "-q", "integrate/web2")
        self.r.write("docs/web2/kh/a.md", "x\n")
        self.r.commit("plain")
        res = subprocess.run([sys.executable, os.path.join(self.r.dir, "tools/ci/integration_gate.py"), "--mode", "pr", "--head-ref", "web2/kh/x"],
                             cwd=self.r.dir, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        self.assertEqual(res.returncode, 1)
        self.assertIn("not the pull request's merge commit", res.stdout.decode())


# --------------------------------------------------------------------------- CI change guard

PREV_2F8 = "Release gate for the previous release on its own commit (Industry Hub, 2f800b2)"
# A fictional future release check, so these tests do not depend on how many real ones exist.
PREV_NEXT = "Release gate for the previous release on its own commit (theme 9.9.9, 0123456)"
STEP_NEXT = (
    "      - name: " + PREV_NEXT + "\n"
    "        if: steps.scope.outputs.target == 'true'\n"
    "        run: |\n"
    "          set -e\n"
    "          tree=\"$RUNNER_TEMP/theme-9.9.9-tree\"; mkdir -p \"$tree\"\n"
    "          git archive 0123456789abcdef0123456789abcdef01234567 wp-plugin wp-theme | tar -x -C \"$tree\"\n"
    "          OMOCHIX_RELEASE_CONFIG=theme-9.9.9.json OMOCHIX_RELEASE_TREE=\"$tree\" php tests/release/release-gate.php\n\n")


def step_block(text, name):
    """The YAML text of one step (from its '- name:' line to the next step)."""
    start = text.index("      - name: " + name + "\n")
    nxt = text.find("\n      - ", start + 1)
    return text[start:(nxt + 1) if nxt != -1 else len(text)]


def drop_step(text, name):
    return text.replace(step_block(text, name), "", 1)


def add_release_step(text, block=None):
    """Insert a release check the way PR #69 does: after the previous-release gate."""
    block = STEP_NEXT if block is None else block
    anchor = "      - name: Release tools ("
    assert anchor in text
    return text.replace(anchor, block + anchor, 1)


def pin_steps(repo_dir, inventory_text):
    """The inventory with 'pinned_steps' refreshed from the repository's release.yml (what --print-steps prints)."""
    d = json.loads(inventory_text)
    doc = workflow_policy.load_yaml(slurp(os.path.join(repo_dir, ".github/workflows/release.yml")))
    d["workflows"][".github/workflows/release.yml"]["pinned_steps"]["gate"] = workflow_policy._walk_steps(doc["jobs"]["gate"])
    return json.dumps(d, indent=2)


def drop_line(text, line):
    assert line in text, line
    return text.replace(line, "", 1)


class Guard(RepoCase):
    def base_inventory(self):
        return json.loads(self.r.git("show", "HEAD^1:tools/ci/required-checks.json"))

    def guard(self, labels=(), event="pull_request", body="", merged_pulls=None):
        return ci_guard.guard("HEAD^1", "HEAD", event, list(labels), inventory=self.base_inventory(), cwd=self.r.dir, body=body,
                              merged_pulls=merged_pulls)

    def merged(self, labels=(), body="", number=99, sha=None, merged=True):
        """The 'commits/{sha}/pulls' API answer for the merge at HEAD."""
        return [{"number": number, "merged_at": "2026-11-22T00:00:00Z" if merged else None,
                 "merge_commit_sha": sha or self.r.git("rev-parse", "HEAD"),
                 "labels": [{"name": n} for n in labels], "body": body}]

    def approval(self, label):
        """The description line a failing run asks for (what an owner pastes after reading the diff)."""
        ok, lines = self.guard()
        found = [l.split("put this line in the description: ", 1)[1] for l in lines if "put this line in the description: %s:" % label in l]
        self.assertTrue(found, "\n".join(lines))
        return found[0]

    def assertFailsWith(self, result, text):
        ok, lines = result
        self.assertFalse(ok, "\n".join(lines))
        self.assertTrue(any(text in l for l in lines if l.startswith("FAIL")), "\n".join(lines))

    def test_no_ci_change_passes(self):
        self.r.pr("docs", {"docs/readme.md": "x\n", "wp-theme/omochix-theme/functions.php": "<?php // x\n"})
        ok, lines = self.guard()
        self.assertTrue(ok, "\n".join(lines))

    def test_ci_changes_need_the_review_label(self):
        for name, changes in {
            "workflow": {".github/workflows/tests.yml": lambda s: s + "\n# comment\n"},
            "ci-script": {"tools/ci/release_scope.py": lambda s: s + "\n# comment\n"},
            "ownership": {"docs/web2/ownership.json": lambda s: s.replace('"version": 1', '"version": 1 ')},
            "gate-code": {"tests/release/release-gate.php": "<?php // weakened\n"},
        }.items():
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                self.r.pr("ci-" + name, changes)
                self.assertFailsWith(self.guard(), "CI or gate files changed")
                line = self.approval("ci-change-reviewed")
                # The label alone is not an approval: it must name this content's digest.
                self.assertFailsWith(self.guard(labels=["ci-change-reviewed"]), "CI or gate files changed")
                # The digest alone (no label) is not an approval either.
                self.assertFalse(self.guard(body=line)[0])
                ok, lines = self.guard(labels=["ci-change-reviewed"], body="Reviewed.\n\n" + line + "\n")
                self.assertTrue(ok, "\n".join(lines))
                self.assertTrue(any(l.startswith("WARN") for l in lines))

    def test_a_commit_after_the_review_needs_a_new_review(self):
        self.r.pr("ci", {".github/workflows/tests.yml": lambda s: s + "\n# reviewed\n"})
        line = self.approval("ci-change-reviewed")
        self.assertTrue(self.guard(labels=["ci-change-reviewed"], body=line)[0])
        # Same label, same description, one more commit on the branch: GitHub keeps the label.
        self.r.git("checkout", "-q", "ci")
        self.r.write(".github/workflows/tests.yml", self.r.read(".github/workflows/tests.yml") + "# not reviewed\n")
        self.r.commit("after the review")
        self.r.git("checkout", "-q", "--detach", "main")
        self.r.git("merge", "-q", "--no-ff", "--no-edit", "ci")
        ok, lines = self.guard(labels=["ci-change-reviewed"], body=line)
        self.assertFalse(ok, "\n".join(lines))
        self.assertTrue(any("does not carry this change's digest" in l for l in lines), "\n".join(lines))
        # A re-run for an unrelated reason (title edit, another label) on the reviewed content still passes.
        self.assertTrue(self.guard(labels=["ci-change-reviewed", "docs"], body=self.approval("ci-change-reviewed"))[0])

    def test_a_non_ci_commit_after_the_review_keeps_the_approval(self):
        self.r.pr("ci", {".github/workflows/tests.yml": lambda s: s + "\n# reviewed\n"})
        line = self.approval("ci-change-reviewed")
        self.r.git("checkout", "-q", "ci")
        self.r.write("docs/readme.md", "more docs\n")
        self.r.commit("docs after the review")
        self.r.git("checkout", "-q", "--detach", "main")
        self.r.git("merge", "-q", "--no-ff", "--no-edit", "ci")
        self.assertTrue(self.guard(labels=["ci-change-reviewed"], body=line)[0])

    def test_a_digest_for_other_content_or_a_short_prefix_is_rejected(self):
        self.r.pr("ci", {".github/workflows/tests.yml": lambda s: s + "\n# x\n"})
        line = self.approval("ci-change-reviewed")
        digest = line.split(": ", 1)[1]
        for body in ("ci-change-reviewed: " + "0" * 16, "ci-change-reviewed: " + digest[:8],
                     "text ci-change-reviewed: " + digest, "ci-weakening-approved: " + digest):
            with self.subTest(body):
                self.assertFalse(self.guard(labels=["ci-change-reviewed"], body=body)[0])

    def test_push_with_ci_changes_passes_only_as_an_approved_merge(self):
        self.r.pr("ci", {".github/workflows/tests.yml": lambda s: s + "\n# comment\n"})
        line = self.approval("ci-change-reviewed")
        ok, lines = self.guard(event="push", merged_pulls=self.merged(["ci-change-reviewed"], "LGTM\r\n" + line))
        self.assertTrue(ok, "\n".join(lines))
        self.assertTrue(any(l.startswith("NOTE") and "approved there" in l for l in lines))
        for name, pulls, text in (
            ("no API answer", None, "could not be read from the GitHub API"),
            ("no pull request", [], "no merged pull request"),
            ("merged without approval (like #69)", self.merged(), "does not carry the approval"),
            ("label only", self.merged(["ci-change-reviewed"]), "does not carry the approval"),
            ("digest only", self.merged([], line), "does not carry the approval"),
            ("digest of other content", self.merged(["ci-change-reviewed"], "ci-change-reviewed: " + "0" * 16), "does not carry the approval"),
            ("closed, not merged", self.merged(["ci-change-reviewed"], line, merged=False), "no merged pull request"),
            ("another merge commit", self.merged(["ci-change-reviewed"], line, sha="1" * 40), "no merged pull request"),
            ("garbage answer", {"message": "Not Found"}, "no merged pull request"),
        ):
            with self.subTest(name):
                self.assertFailsWith(self.guard(event="push", merged_pulls=pulls), text)

    def test_push_without_ci_changes_needs_no_api(self):
        self.r.pr("docs", {"docs/readme.md": "x\n"})
        self.assertTrue(self.guard(event="push", merged_pulls=None)[0])

    # With the ruleset disabled or changed, these pushes reach main; the guard reports them.
    def test_direct_push_fails_even_with_an_approved_pull_request_elsewhere(self):
        self.r.git("checkout", "-q", "main")
        self.r.write(".github/workflows/tests.yml", self.r.read(".github/workflows/tests.yml") + "# direct\n")
        self.r.commit("direct push, no pull request")
        sha = self.r.git("rev-parse", "HEAD")
        ok, lines = self.guard(event="push", merged_pulls=self.merged(["ci-change-reviewed"], "ci-change-reviewed: " + "a" * 16, sha=sha))
        self.assertFailsWith((ok, lines), "not a single merge commit")

    def test_forged_merge_commit_without_a_pull_request_fails(self):
        self.r.pr("forged", {".github/workflows/tests.yml": lambda s: s + "# forged\n"})   # a merge, but nothing on GitHub
        self.assertFailsWith(self.guard(event="push", merged_pulls=[]), "no merged pull request")

    def test_several_commits_in_one_push_fail(self):
        self.r.pr("one", {"docs/readme.md": "x\n"})
        first = self.r.git("rev-parse", "HEAD")
        self.r.git("checkout", "-q", "-B", "two", "main")
        self.r.write(".github/workflows/tests.yml", self.r.read(".github/workflows/tests.yml") + "# two\n")
        self.r.commit("two")
        self.r.git("checkout", "-q", "--detach", first)
        self.r.git("merge", "-q", "--no-ff", "--no-edit", "two")
        head = self.r.git("rev-parse", "HEAD")
        res = ci_guard.guard("main", "HEAD", "push", [], inventory=self.base_inventory(), cwd=self.r.dir,
                             merged_pulls=self.merged(["ci-change-reviewed"], "x", sha=head))
        self.assertFailsWith(res, "not a single merge commit")

    def test_push_of_a_new_release_check_needs_the_approval_and_the_pin(self):
        def change(pin):
            self.r.git("checkout", "-q", "main")
            self.r.git("checkout", "-q", "-B", "rel-%s" % pin, "main")
            self.r.write(".github/workflows/release.yml", add_release_step(self.r.read(".github/workflows/release.yml")))
            if pin:
                self.r.write("tools/ci/required-checks.json", pin_steps(self.r.dir, self.r.read("tools/ci/required-checks.json")))
            self.r.commit("release check")
            self.r.git("checkout", "-q", "--detach", "main")
            self.r.git("merge", "-q", "--no-ff", "--no-edit", "rel-%s" % pin)
        change(True)
        line = self.approval("ci-change-reviewed")
        self.assertTrue(self.guard(labels=["ci-change-reviewed"], body=line)[0])            # the pull request
        ok, lines = self.guard(event="push", merged_pulls=self.merged(["ci-change-reviewed"], line))
        self.assertTrue(ok, "\n".join(lines))                                               # the push after the merge (run 44665403771)
        self.assertTrue(any("approved on pull request #99" in l and "pinned by the pushed commit" in l for l in lines), "\n".join(lines))
        self.assertFailsWith(self.guard(event="push", merged_pulls=self.merged()), "does not carry the approval")
        self.assertFailsWith(self.guard(event="push", merged_pulls=None), "could not be read")
        change(False)                                                                      # approved, but not pinned by its own inventory
        line = self.approval("ci-change-reviewed")
        self.assertFailsWith(self.guard(event="push", merged_pulls=self.merged(["ci-change-reviewed"], line)),
                             "the pushed commit's own inventory does not pin them")

    def test_push_weakening_and_security_fail_even_when_approved(self):
        for name in ("release-no-push-main", "write-permission"):
            path, fn, text = self.WEAKENINGS[name]
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                self.r.pr("pw-" + name, {path: fn})
                ok, lines = self.guard()
                body = "\n".join(l.split("description: ", 1)[1] for l in lines if "description: " in l)
                pulls = self.merged(["ci-change-reviewed", "ci-weakening-approved"], body)
                self.assertFailsWith(self.guard(event="push", merged_pulls=pulls), text)

    WEAKENINGS = {
        "release-no-push-main": (".github/workflows/release.yml", lambda s: drop_line(s, "  push:\n    branches: [main]\n"), "push.branches lacks main"),
        "release-paths-filter": (".github/workflows/release.yml", lambda s: s.replace("    branches: [main]\n    types:", "    branches: [main]\n    paths: ['wp-plugin/**']\n    types:", 1), "pull_request.paths"),
        "release-any-base": (".github/workflows/release.yml", lambda s: s.replace("  pull_request:\n    branches: [main]\n", "  pull_request:\n", 1), "pull_request.branches is []"),
        "release-no-edited": (".github/workflows/release.yml", lambda s: s.replace("types: [opened, synchronize, reopened, edited, ready_for_review]", "types: [opened, synchronize, reopened]", 1), "lacks edited"),
        "release-gate-step-off": (".github/workflows/release.yml", lambda s: s.replace("        if: steps.scope.outputs.target == 'true'\n        run: php tests/release/release-gate.php", "        if: false\n        run: php tests/release/release-gate.php", 1), "under 'if: false'"),
        "release-gate-swallowed": (".github/workflows/release.yml", lambda s: s.replace("run: php tests/release/release-gate.php", "run: php tests/release/release-gate.php || true", 1), "no longer runs 'php tests/release/release-gate.php'"),
        "tests-swallowed": (".github/workflows/tests.yml", lambda s: s.replace('php "$test" || status=1', 'php "$test" || true', 1), "swallow a failure"),
        "release-gate-removed": (".github/workflows/release.yml", lambda s: s.replace("run: php tests/release/release-gate.php", "run: echo skipped", 1), "no longer runs 'php tests/release/release-gate.php'"),
        "release-gate-diluted": (".github/workflows/release.yml", lambda s: s.replace("run: php tests/release/release-gate.php", "run: php tests/release/release-gate.php --help", 1), "no longer runs 'php tests/release/release-gate.php'"),
        "previous-release-gate-removed": (".github/workflows/release.yml", lambda s: s.replace(step_block(s, PREV_2F8), step_block(s, PREV_2F8).replace('OMOCHIX_RELEASE_TREE="$tree" php tests/release/release-gate.php', "true", 1), 1), "step '%s' differs from its pinned copy (run)" % PREV_2F8),
        "release-tools-tests-removed": (".github/workflows/release.yml", lambda s: s.replace("run: python3 -W error::ResourceWarning -m unittest discover -s tests/release -v", "run: echo no", 1), "no longer runs 'python3 -W error"),
        "build-verify-removed": (".github/workflows/release.yml", lambda s: s.replace("python3 tools/release/verify.py --commit HEAD", "echo verify", 1), "no longer runs 'python3 tools/release/verify.py"),
        "release-continue-on-error": (".github/workflows/release.yml", lambda s: s.replace("      - name: Release tools (", "      - continue-on-error: true\n        name: Release tools (", 1), "continue-on-error"),
        "release-job-if": (".github/workflows/release.yml", lambda s: s.replace("    timeout-minutes: 10\n", "    timeout-minutes: 10\n    if: false\n", 1), "job-level 'if'"),
        "old-duplicate-name": (".github/workflows/industry-hub.yml", lambda s: s.replace("name: Industry Hub (PHP ${{ matrix.php }})", "name: PHP ${{ matrix.php }}", 1), "produces checks ['PHP 8.1', 'PHP 8.3']"),
        "matrix-shrunk": (".github/workflows/tests.yml", lambda s: s.replace("php: ['8.1', '8.3']", "php: ['8.3']", 1), "produces checks ['Tests (PHP 8.3)']"),
        "tests-paths-filter": (".github/workflows/tests.yml", lambda s: s.replace("  pull_request:\n", "  pull_request:\n    paths: ['wp-theme/**']\n", 1), "pull_request.paths"),
        "integration-removed": (".github/workflows/integration.yml", None, "integration.yml: missing"),
        "integration-no-push": (".github/workflows/integration.yml", lambda s: drop_line(s, "  push:\n    branches: ['integrate/web2']\n"), "push.branches lacks integrate/web2"),
        "policy-removed": (".github/workflows/ci-policy.yml", None, "ci-policy.yml: missing"),
        "write-permission": (".github/workflows/labs.yml", lambda s: s.replace("    permissions:\n      contents: read\n", "    permissions:\n      contents: write\n", 1), "permission contents: write"),
        "no-top-permissions": (".github/workflows/labs.yml", lambda s: drop_line(s, "permissions: {}\n"), "no top-level 'permissions'"),
        "pull-request-target": (".github/workflows/svg-motion.yml", lambda s: s.replace("on:\n", "on:\n  pull_request_target:\n", 1), "'pull_request_target'"),
        "unpinned-action": (".github/workflows/labs.yml", lambda s: s.replace("actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1", "actions/checkout@v7", 1), "not pinned to a full commit SHA"),
        "persisted-token": (".github/workflows/tests.yml", lambda s: s.replace("persist-credentials: false", "persist-credentials: true", 1), "persist-credentials"),
        "script-injection": (".github/workflows/tests.yml", lambda s: s.replace("        run: |\n          status=0\n", "        run: |\n          echo \"${{ github.event.pull_request.title }}\"\n          status=0\n", 1), "untrusted pull request text"),
        "shadow-release-gate": (".github/workflows/shadow.yml", lambda _s: "name: Shadow\non:\n  pull_request:\npermissions: {}\njobs:\n  x:\n    name: Release gate (PHP 8.1)\n    runs-on: ubuntu-24.04\n    steps:\n      - run: echo ok\n", "a required check that only .github/workflows/release.yml may report"),
        "shadow-computed-name": (".github/workflows/shadow.yml", lambda _s: "name: Shadow\non:\n  pull_request:\npermissions: {}\njobs:\n  x:\n    name: ${{ github.event.pull_request.number && 'CI change guard' }}\n    runs-on: ubuntu-24.04\n    steps:\n      - run: echo ok\n", "computed name"),
        "shadow-job-id": (".github/workflows/shadow.yml", lambda _s: "name: Shadow\non:\n  pull_request:\npermissions: {}\njobs:\n  'Integration gate':\n    runs-on: ubuntu-24.04\n    steps:\n      - run: echo ok\n", "job id 'Integration gate'"),
        "guard-back-to-pull-request": (".github/workflows/ci-guard.yml", lambda s: s.replace("  pull_request_target:\n", "  pull_request:\n", 1), "no pull_request_target trigger"),
        "guard-secret": (".github/workflows/ci-guard.yml", lambda s: s.replace("          PR_BODY: ${{ github.event.pull_request.body }}\n", "          PR_BODY: ${{ github.event.pull_request.body }}\n          T: ${{ secrets.DEPLOY_TOKEN }}\n", 1), "must not use secrets"),
        "guard-other-action": (".github/workflows/ci-guard.yml", lambda s: s.replace("      - name: CI change guard (base rules", "      - uses: actions/setup-python@0b93645e9fea7318ecaed2b359559ac225c90a2b\n      - name: CI change guard (base rules", 1), "may only use actions/checkout"),
        "guard-runs-head-script": (".github/workflows/ci-guard.yml", lambda s: s.replace('python3 -I "$dir/tools/ci/ci_guard.py"', "python3 tools/ci/ci_guard.py", 1), "job 'guard' no longer runs 'set -euo pipefail"),
        "release-scope-runs-head-script": (".github/workflows/release.yml", lambda s: s.replace('python3 -I "$dir/tools/ci/release_scope.py" --event pull_request', "python3 tools/ci/release_scope.py --event pull_request", 1), "job 'gate' no longer runs 'set -euo pipefail"),
        "release-scope-copies-from-head": (".github/workflows/release.yml", lambda s: s.replace('git archive "$base" tools/ci', "git archive HEAD tools/ci", 1), "job 'gate' no longer runs 'set -euo pipefail"),
        "release-scope-helper-again": (".github/workflows/release.yml", lambda s: s.replace('          dir="$RUNNER_TEMP/ci-trusted"\n          rm -rf "$dir"; mkdir -p "$dir"\n          git archive "$base" tools/ci | tar -x -C "$dir"\n', '          dir=$(bash tools/ci/use-base-copy.sh "$base")/../..\n', 1), "job 'gate' no longer runs 'set -euo pipefail"),
        "integration-runs-head-script": (".github/workflows/integration.yml", lambda s: s.replace('python3 -I "$dir/tools/ci/integration_gate.py" --mode pr', "python3 tools/ci/integration_gate.py --mode pr", 1), "job 'gate' no longer runs 'set -euo pipefail"),
        "integration-bootstrap-from-head": (".github/workflows/integration.yml", lambda s: s.replace('git archive "$trusted" tools/ci', "git archive HEAD tools/ci", 1), "job 'gate' no longer runs 'set -euo pipefail"),
        "workflow-run": (".github/workflows/svg-motion.yml", lambda s: s.replace("on:\n", "on:\n  workflow_run:\n    workflows: [Tests]\n", 1), "'workflow_run'"),
        "integration-other-branch": (".github/workflows/integration.yml", lambda s: s.replace("    branches: ['integrate/web2']\n    # 'edited'", "    branches: ['integrate/other']\n    # 'edited'", 1), "pull_request.branches is ['integrate/other']"),
        "previous-release-step-removed": (".github/workflows/release.yml", lambda s: drop_step(s, PREV_2F8), "pinned step '%s' is gone" % PREV_2F8),
        "release-step-if-dropped": (".github/workflows/release.yml", lambda s: s.replace("        if: steps.scope.outputs.target == 'true'\n        run: python3 -W error", "        run: python3 -W error", 1), "differs from its pinned copy (if)"),
        "release-gate-if-dropped": (".github/workflows/release.yml", lambda s: s.replace("        if: steps.scope.outputs.target == 'true'\n        run: php tests/release/release-gate.php", "        run: php tests/release/release-gate.php", 1), "must run under 'if: steps.scope.outputs.target == 'true'' (got no 'if')"),
        "release-steps-reordered": (".github/workflows/release.yml", lambda s: drop_step(s, PREV_2F8).replace("      - name: Release gate (versions,", step_block(s, PREV_2F8) + "      - name: Release gate (versions,", 1), "not in the pinned order"),
        "release-step-env-added": (".github/workflows/release.yml", lambda s: s.replace("        run: php tests/release/release-gate.php\n", "        env:\n          BASH_ENV: ./x.sh\n        run: php tests/release/release-gate.php\n", 1), "differs from its pinned copy (env)"),
        "release-job-env": (".github/workflows/release.yml", lambda s: s.replace("    timeout-minutes: 10\n", "    timeout-minutes: 10\n    env:\n      BASH_ENV: ./x.sh\n", 1), "job-level 'env'"),
        "release-top-defaults": (".github/workflows/release.yml", lambda s: s.replace("\njobs:\n", "\ndefaults:\n  run:\n    shell: bash {0}\n\njobs:\n", 1), "top-level 'defaults'"),
        "release-step-renamed": (".github/workflows/release.yml", lambda s: s.replace("      - name: " + PREV_2F8, "      - name: Previous release (off)", 1), "pinned step '%s' is gone" % PREV_2F8),
        "guard-removed": (".github/workflows/ci-guard.yml", lambda s: s.replace("    name: CI change guard\n", "    name: CI change guard (off)\n", 1), "job 'guard' produces checks"),
    }

    SECURITY = {"write-permission", "no-top-permissions", "pull-request-target", "unpinned-action", "persisted-token",
                "script-injection", "shadow-release-gate", "shadow-computed-name", "shadow-job-id", "guard-secret",
                "guard-other-action", "workflow-run"}

    def test_weakening_fails_even_with_the_review_label(self):
        for name, (path, fn, text) in self.WEAKENINGS.items():
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                self.r.pr("weak-" + name, {path: fn})
                ok, lines = self.guard()
                review = [l.split("description: ", 1)[1] for l in lines if "description: ci-change-reviewed:" in l]
                self.assertFailsWith(self.guard(labels=["ci-change-reviewed"], body="\n".join(review)), text)

    def test_security_findings_fail_even_with_the_weakening_approval(self):
        for name in sorted(self.SECURITY):
            path, fn, text = self.WEAKENINGS[name]
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                self.r.pr("sec-" + name, {path: fn})
                ok, lines = self.guard()
                self.assertTrue(any(l.startswith("FAIL") and "security findings" in l for l in lines), "\n".join(lines))
                # Other findings may be approvable; the security ones never are (asserted below).
                body = "\n".join(l.split("description: ", 1)[1] for l in lines if "description: " in l)
                inv = self.base_inventory()
                hits = [x for x in cilib.changed_paths("HEAD^1", "HEAD", cwd=self.r.dir) if cilib.matches(x, inv["protected_paths"])]
                weak = [x for x in workflow_policy.check(rev="HEAD", inventory=inv, cwd=self.r.dir) if not x.startswith(workflow_policy.SECURITY)]
                body += "\nci-weakening-approved: " + ci_guard.digest(hits, weak, "HEAD^1", "HEAD", cwd=self.r.dir)
                self.assertFailsWith(self.guard(labels=["ci-weakening-approved", "ci-change-reviewed"], body=body), text)

    def test_every_non_security_weakening_is_not_tagged_security(self):
        for name in sorted(set(self.WEAKENINGS) - self.SECURITY):
            path, fn, text = self.WEAKENINGS[name]
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                self.r.pr("w-" + name, {path: fn})
                ok, lines = self.guard()
                self.assertFalse(any("security findings" in l for l in lines), "\n".join(lines))

    def test_weakening_passes_only_with_the_weakening_approval(self):
        path, fn, _ = self.WEAKENINGS["release-no-push-main"]
        self.r.pr("weak", {path: fn})
        self.assertFalse(self.guard(labels=["ci-weakening-approved"])[0])          # label without digest
        line = self.approval("ci-weakening-approved")
        self.assertFalse(self.guard(labels=["ci-change-reviewed"], body=line)[0])  # wrong label
        ok, lines = self.guard(labels=["ci-weakening-approved"], body=line)
        self.assertTrue(ok, "\n".join(lines))
        self.assertTrue(any("accepted with the 'ci-weakening-approved' label" in l for l in lines))

    def test_a_step_that_could_change_the_tree_needs_a_review_and_a_pin(self):
        tamper = "      - name: Prepare\n        run: git checkout a04511722accd560216b019ca47a4bfcaad630ab -- wp-theme\n\n"
        self.r.pr("tamper", {".github/workflows/release.yml": lambda s: s.replace("      - name: Release scope (", tamper + "      - name: Release scope (", 1)})
        ok, lines = self.guard()
        self.assertFailsWith((ok, lines), "step 'Prepare' is not pinned")
        # The CI policy job (the head's own inventory) fails too until the inventory pins it.
        self.assertTrue(any("step 'Prepare' is not pinned" in x for x in workflow_policy.check(rev="HEAD", cwd=self.r.dir)))

    def with_two_previous_releases_on_main(self):
        """main as it would be after PR #69: two previous-release checks, both pinned."""
        self.r.git("checkout", "-q", "main")
        self.r.write(".github/workflows/release.yml", add_release_step(self.r.read(".github/workflows/release.yml")))
        self.r.write("tools/ci/required-checks.json", pin_steps(self.r.dir, self.r.read("tools/ci/required-checks.json")))
        self.r.commit("second previous-release check, pinned")
        self.r.git("update-ref", "refs/remotes/origin/main", "main")
        self.assertEqual(workflow_policy.check(rev="HEAD", cwd=self.r.dir), [])

    def test_removing_either_of_two_similar_release_checks_fails(self):
        # The substring check could not tell these two apart: both run
        # OMOCHIX_RELEASE_TREE="$tree" php tests/release/release-gate.php.
        self.with_two_previous_releases_on_main()
        for name in (PREV_2F8, PREV_NEXT):
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                self.r.pr("rm-" + name[-8:-1], {".github/workflows/release.yml": lambda s, n=name: drop_step(s, n)})
                review = self.approval("ci-change-reviewed")
                self.assertFailsWith(self.guard(labels=["ci-change-reviewed"], body=review), "pinned step '%s' is gone" % name)

    def test_dropping_the_condition_of_a_new_release_check_fails(self):
        self.with_two_previous_releases_on_main()
        self.r.pr("no-if", {".github/workflows/release.yml": lambda s: s.replace(
            "      - name: " + PREV_NEXT + "\n        if: steps.scope.outputs.target == 'true'\n", "      - name: " + PREV_NEXT + "\n", 1)})
        self.assertFailsWith(self.guard(labels=["ci-change-reviewed"], body=self.approval("ci-change-reviewed")), "differs from its pinned copy (if)")

    def test_adding_a_release_check_the_way_pr_69_does(self):
        # Without the scope condition and without a pin: both are findings.
        unconditional = STEP_NEXT.replace("        if: steps.scope.outputs.target == 'true'\n", "", 1)
        self.r.pr("pr69-as-is", {".github/workflows/release.yml": lambda s: add_release_step(s, unconditional)})
        ok, lines = self.guard()
        self.assertFailsWith((ok, lines), "step '%s' must run under" % PREV_NEXT)
        self.assertFailsWith((ok, lines), "step '%s' is not pinned" % PREV_NEXT)
        # With the condition and the inventory refreshed in the same pull request: a review is enough.
        self.r.git("checkout", "-q", "main")
        self.r.git("checkout", "-q", "-B", "pr69-fixed", "main")
        self.r.write(".github/workflows/release.yml", add_release_step(self.r.read(".github/workflows/release.yml")))
        self.r.write("tools/ci/required-checks.json", pin_steps(self.r.dir, self.r.read("tools/ci/required-checks.json")))
        self.r.commit("release check + pin")
        self.r.git("checkout", "-q", "--detach", "main")
        self.r.git("merge", "-q", "--no-ff", "--no-edit", "pr69-fixed")
        ok, lines = self.guard()
        self.assertFalse(ok)
        self.assertFalse(any("must run under" in l or "security" in l for l in lines), "\n".join(lines))
        self.assertTrue(any("is not pinned" in l for l in lines))          # base inventory does not know it yet
        ok, lines = self.guard(labels=["ci-change-reviewed"], body=self.approval("ci-change-reviewed"))
        self.assertTrue(ok, "\n".join(lines))
        self.assertEqual(workflow_policy.check(rev="HEAD", cwd=self.r.dir), [])   # CI policy (head inventory)

    def test_adding_a_line_to_a_pinned_step_needs_a_review_removing_one_is_a_weakening(self):
        plan2f8 = "OMOCHIX_RELEASE_CONFIG=industry-hub-core-1.10.0-theme-1.29.0.json python3 tools/release/plan.py --commit 2f800b2c2a7ed34f0e7d33616dbfafc9cb9f1008 --check"
        added = "          OMOCHIX_RELEASE_CONFIG=theme-9.9.9.json python3 tools/release/plan.py --commit 0123456789abcdef0123456789abcdef01234567 --check\n"
        self.r.pr("add-line", {".github/workflows/release.yml": lambda s: s.replace("          " + plan2f8 + "\n", "          " + plan2f8 + "\n" + added, 1)})
        ok, lines = self.guard()
        self.assertFailsWith((ok, lines), "adds lines to its pinned script")
        self.assertFalse(any("can weaken" in l for l in lines), "\n".join(lines))
        self.assertTrue(self.guard(labels=["ci-change-reviewed"], body=self.approval("ci-change-reviewed"))[0])
        # Dropping the previous release's plan check is a weakening, also next to an added line.
        for name, fn in (("drop", lambda s: s.replace("          " + plan2f8 + "\n", "", 1)),
                         ("swap", lambda s: s.replace("          " + plan2f8 + "\n", added, 1)),
                         ("dilute", lambda s: s.replace(plan2f8, plan2f8 + " || true", 1))):
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                self.r.pr("plan-" + name, {".github/workflows/release.yml": fn})
                self.assertFailsWith(self.guard(labels=["ci-change-reviewed"], body=self.approval("ci-change-reviewed")),
                                     "step 'Build twice and verify (packages stay in the runner)' differs from its pinned copy (run)")

    def test_print_steps_matches_the_pinned_inventory(self):
        res = subprocess.run([sys.executable, os.path.join(REPO, "tools/ci/workflow_policy.py"), "--print-steps", ".github/workflows/release.yml", "gate"],
                             cwd=REPO, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        self.assertEqual(res.returncode, 0, res.stderr)
        inv = json.loads(slurp(os.path.join(REPO, "tools/ci/required-checks.json")))
        self.assertEqual(json.loads(res.stdout), inv["workflows"][".github/workflows/release.yml"]["pinned_steps"]["gate"])

    def test_unexpected_event_fails(self):
        self.r.pr("docs", {"docs/readme.md": "x\n"})
        run = subprocess.run([sys.executable, os.path.join(REPO, "tools/ci/ci_guard.py"), "--base", "HEAD^1", "--head", "HEAD", "--event", "workflow_dispatch"],
                             cwd=self.r.dir, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        self.assertEqual(run.returncode, 1)
        self.assertIn("unexpected event", run.stdout.decode())

    def test_loosening_the_inventory_in_the_same_pr_does_not_help(self):
        def loosen(s):
            d = json.loads(s)
            d["workflows"][".github/workflows/release.yml"]["push_branches"] = []
            d["protected_paths"] = []
            return json.dumps(d)
        self.r.pr("loosen", {
            "tools/ci/required-checks.json": loosen,
            ".github/workflows/release.yml": lambda s: drop_line(s, "  push:\n    branches: [main]\n"),
        })
        line = self.approval("ci-change-reviewed")
        res = self.guard(labels=["ci-change-reviewed"], body=line)
        self.assertFailsWith(res, "push.branches lacks main")

class Bootstrap(RepoCase):
    """The base has no tools/ci/ (the pull request that introduces it): every gate fails closed."""
    with_ci = False

    def introduce_ci(self, extra=None):
        self.r.git("checkout", "-q", "-B", "ci", "main")
        for sub in ("tools/ci", ".github/workflows"):
            shutil.copytree(os.path.join(REPO, sub), os.path.join(self.r.dir, sub), ignore=shutil.ignore_patterns("__pycache__"))
        self.r.write("docs/web2/ownership.json", slurp(os.path.join(REPO, "docs/web2/ownership.json")))
        self.r.apply(extra or {})
        self.r.commit("introduce CI")
        self.r.git("checkout", "-q", "--detach", "main")
        self.r.git("merge", "-q", "--no-ff", "--no-edit", "ci")

    def test_release_scope_runs_the_full_gate_and_never_the_heads_script(self):
        # The introducing pull request even says "not a target" in its own scope script.
        self.introduce_ci({"tools/ci/release_scope.py": EVIL_SCOPE})
        code, log, outputs = WorkflowSteps.run_step(self, *WorkflowSteps.SCOPE, GITHUB_EVENT_NAME="pull_request")
        self.assertEqual(code, 0, log)
        self.assertEqual(outputs.strip(), "target=true")
        self.assertIn("without a trusted scope script", log)
        self.assertNotIn("evil", log)

    def test_integration_gate_fails(self):
        self.introduce_ci({"tools/ci/integration_gate.py": "print('evil pass')\n"})
        code, log, _ = WorkflowSteps.run_step(self, *WorkflowSteps.INTEG, EVENT="pull_request", HEAD_REF="web2/ci/x", SAME_REPO="true", BEFORE="")
        self.assertEqual(code, 1, log)
        self.assertIn("nothing trusted can judge", log)
        self.assertNotIn("evil", log)

    def test_guard_push_fails(self):
        before = self.r.git("rev-parse", "main")
        self.introduce_ci({"tools/ci/ci_guard.py": "print('evil pass')\n"})
        code, log, _ = WorkflowSteps.run_step(self, *WorkflowSteps.GUARD, EVENT="push", BEFORE=before, PR_LABELS="", PR_BODY="", PR_HEAD_SHA="", GITHUB_SHA="")
        self.assertEqual(code, 1, log)
        self.assertIn("nothing trusted can judge", log)
        self.assertNotIn("evil", log)


EVIL_COPY = """#!/usr/bin/env bash
# A pull request's helper that points the caller at the pull request's own scripts.
d="${RUNNER_TEMP}/evil"; mkdir -p "$d/tools"; cp -R tools/ci "$d/tools/ci"; echo "$d/tools/ci"
"""
EVIL_SCOPE = "import os\nopen(os.environ['GITHUB_OUTPUT'], 'a').write('target=false\\n')\nprint('NOT A RELEASE TARGET (evil)')\n"
EVIL_GATE = "print('Integration gate: PASS (evil)')\n"


# --------------------------------------------------------------------------- Workflow policy on this repository

class Policy(unittest.TestCase):
    def setUp(self):
        self.inv = json.loads(slurp(os.path.join(REPO, workflow_policy.INVENTORY)))
        self.docs = {}
        for p in workflow_policy.all_workflows(None, cwd=REPO):
            self.docs[p] = workflow_policy.load_yaml(slurp(os.path.join(REPO, p)))

    def test_this_repository_passes_its_own_policy(self):
        self.assertEqual(workflow_policy.check(None, None, cwd=REPO), [])

    def all_names(self):
        names = []
        for p, d in self.docs.items():
            for job in (d.get("jobs") or {}).values():
                names += workflow_policy.expand_names(job)
        return names

    def test_check_names_are_unique_across_workflows(self):
        names = self.all_names()
        dup = sorted({n for n in names if names.count(n) > 1})
        self.assertEqual(dup, [], "two jobs report the same check name")
        self.assertNotIn("PHP 8.1", names)
        self.assertNotIn("PHP 8.3", names)

    def runs_for(self, spec, base):
        b = spec["pull_request_branches"]
        return b == "all" or any(cilib.glob_re(x).match(base) for x in b)

    def test_every_required_check_is_produced_for_its_base(self):
        for base_pattern, required in self.inv["required_checks"].items():
            base = base_pattern
            produced = []
            for path, spec in self.inv["workflows"].items():
                if self.runs_for(spec, base):
                    for names in spec["jobs"].values():
                        produced += names
            with self.subTest(base_pattern):
                self.assertEqual(sorted(set(required) - set(produced)), [], "required but never reported for %s" % base)

    def test_release_gate_runs_only_for_main_and_integration_gate_only_for_integrate(self):
        rel = self.docs[".github/workflows/release.yml"]["on"]
        self.assertEqual(rel["pull_request"]["branches"], ["main"])
        self.assertEqual(rel["push"]["branches"], ["main"])
        self.assertNotIn("paths", rel["pull_request"])
        integ = self.docs[".github/workflows/integration.yml"]["on"]
        self.assertEqual(integ["pull_request"]["branches"], ["integrate/web2"])
        self.assertEqual(integ["push"]["branches"], ["integrate/web2"])
        # A required name never comes from two bases: neither gate's name is reported for the other.
        self.assertNotIn("Integration gate", self.inv["required_checks"]["main"])
        self.assertFalse(any(n.startswith("Release gate") for n in self.inv["required_checks"]["integrate/web2"]))

    def test_release_strict_steps_are_only_skipped_by_the_scope_decision(self):
        job = self.docs[".github/workflows/release.yml"]["jobs"]["gate"]
        steps = job["steps"]
        names = [s.get("name") for s in steps]
        scope = names.index("Release scope (what this event must check)")
        self.assertNotIn("if", steps[scope])
        for s in steps[scope + 1:]:
            self.assertEqual(s.get("if"), "steps.scope.outputs.target == 'true'", s.get("name"))
        self.assertNotIn("if", job)

    def test_every_workflow_is_least_privilege(self):
        for p, d in self.docs.items():
            with self.subTest(p):
                self.assertEqual(workflow_policy.least_privilege(p, d, self.inv["pull_request_target_allowed"], slurp(os.path.join(REPO, p))), [])
                self.assertIn("permissions", d)

    def test_only_the_guard_uses_pull_request_target_and_it_runs_nothing_from_the_pull_request(self):
        users = sorted(p for p, d in self.docs.items() if "pull_request_target" in (d.get("on") or {}))
        self.assertEqual(users, [".github/workflows/ci-guard.yml"])
        self.assertEqual(self.inv["pull_request_target_allowed"], users)
        guard = self.docs[".github/workflows/ci-guard.yml"]
        self.assertNotIn("pull_request", guard["on"])
        self.assertIn(guard["permissions"], ({}, "{}"))
        for jid, job in guard["jobs"].items():
            self.assertEqual(job["permissions"], {"contents": "read", "pull-requests": "read"})
            for st in job["steps"]:
                if "uses" in st:
                    self.assertTrue(st["uses"].startswith("actions/checkout@"))
                    self.assertEqual(st["with"]["persist-credentials"], "false")
                run = st.get("run", "")
                # Only base-copy code runs: no script path relative to the (pull request) working tree.
                for bad in ("bash tools/", "python3 tools/", "./", "source ", "make ", "npm ", "php "):
                    self.assertNotIn(bad, run, st.get("name"))
                if "ci_guard.py" in run:
                    self.assertIn('python3 -I "$dir/tools/ci/ci_guard.py"', run)
        self.assertNotIn("pull_request_target", self.docs[".github/workflows/ci-policy.yml"]["on"])

    def test_trusted_copy_steps_run_nothing_from_the_working_tree(self):
        steps = {(".github/workflows/release.yml", "gate", "Release scope (what this event must check)"): "release_scope.py",
                 (".github/workflows/integration.yml", "gate", "Integration gate (versions, release settings, ownership)"): "integration_gate.py",
                 (".github/workflows/ci-guard.yml", "guard", "CI change guard (base rules against this change)"): "ci_guard.py"}
        self.assertFalse(os.path.exists(os.path.join(REPO, "tools/ci/use-base-copy.sh")))
        for (wf, job, name), script in steps.items():
            st = [x for x in self.docs[wf]["jobs"][job]["steps"] if x.get("name") == name][0]
            run = st["run"]
            with self.subTest(wf):
                for bad in ("bash tools/", "python3 tools/", "use-base-copy", "./", "source ", ". tools"):
                    self.assertNotIn(bad, run)
                self.assertIn('git archive "$', run)
                for line in run.splitlines():
                    if "python3" in line:
                        self.assertIn('python3 -I "$dir/tools/ci/%s"' % script, line)
                # The whole script is pinned: any edit is a policy finding for the CI change guard.
                self.assertIn("=" + run.strip(), self.inv["workflows"][wf]["must_run"][job])

    def test_reader_matches_ruby_yaml(self):
        ruby = shutil.which("ruby")
        if not ruby:
            self.skipTest("ruby not installed")
        def norm(x):
            if isinstance(x, dict):
                return {("on" if k is True or k == "true" else str(k)): norm(v) for k, v in x.items()}
            if isinstance(x, list):
                return [norm(v) for v in x]
            if x is None:
                return None
            if isinstance(x, bool):
                return "true" if x else "false"
            return str(x)
        for p, d in self.docs.items():
            with self.subTest(p):
                res = subprocess.run([ruby, "-ryaml", "-rjson", "-e", "puts JSON.generate(YAML.load(STDIN.read))"],
                                     input=slurp(os.path.join(REPO, p), "rb"), stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                self.assertEqual(res.returncode, 0, res.stderr.decode())
                self.assertEqual(norm(d), norm(json.loads(res.stdout)))

    def test_every_run_script_is_valid_bash(self):
        import re as _re
        for p, d in self.docs.items():
            for jid, job in (d.get("jobs") or {}).items():
                for st in job.get("steps") or []:
                    run = st.get("run") if isinstance(st, dict) else None
                    if not isinstance(run, str):
                        continue
                    script = _re.sub(r"\$\{\{[^}]*\}\}", "PLACEHOLDER", run)
                    with self.subTest(p + ":" + jid + ":" + str(st.get("name"))):
                        res = subprocess.run(["bash", "-n"], input=script.encode(), stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                        self.assertEqual(res.returncode, 0, res.stderr.decode())

    def test_reader_rejects_what_it_cannot_read(self):
        for bad in ("a: [1, 2\n", "a:\n  b: 1\n    c: 2\n", "a: 1\na: 2\n"):
            with self.subTest(bad):
                with self.assertRaises(workflow_policy.YamlError):
                    workflow_policy.load_yaml(bad)


class WorkflowSteps(RepoCase):
    """Runs the shell of the real workflow steps (from the YAML) in a throwaway repository."""

    def run_step(self, workflow, job, step, pre=None, **env):
        doc = workflow_policy.load_yaml(slurp(os.path.join(REPO, ".github/workflows", workflow)))
        st = [x for x in doc["jobs"][job]["steps"] if x.get("name") == step][0]
        self.assertNotIn("${{", st["run"], "event data must reach the script through env")
        rt = tempfile.mkdtemp(prefix="omx-ci-rt-")
        self.addCleanup(shutil.rmtree, rt, True)
        for name, content in (pre or {}).items():
            with open(os.path.join(rt, name), "w", encoding="utf-8") as fh:
                fh.write(content)
        out = os.path.join(rt, "output")
        e = dict(os.environ, RUNNER_TEMP=rt, GITHUB_OUTPUT=out, GITHUB_STEP_SUMMARY=os.path.join(rt, "summary"))
        e.update(env)
        res = subprocess.run(["bash", "-e", "-c", st["run"]], cwd=self.r.dir, env=e, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        outputs = slurp(out) if os.path.exists(out) else ""
        return res.returncode, res.stdout.decode() + res.stderr.decode(), outputs

    SCOPE = ("release.yml", "gate", "Release scope (what this event must check)")

    def test_release_scope_step_for_a_docs_pr(self):
        self.r.pr("docs", {"docs/readme.md": "x\n"})
        code, log, outputs = self.run_step(*self.SCOPE, GITHUB_EVENT_NAME="pull_request", BEFORE="")
        self.assertEqual(code, 0, log)
        self.assertIn("target=false", outputs)
        self.assertIn("NOT A RELEASE TARGET", log)

    def test_release_scope_step_for_a_release_pr(self):
        self.r.pr("theme", {"wp-theme/omochix-theme/functions.php": "<?php // x\n"})
        code, log, outputs = self.run_step(*self.SCOPE, GITHUB_EVENT_NAME="pull_request", BEFORE="")
        self.assertEqual(code, 0, log)
        self.assertIn("target=true", outputs)

    def test_release_scope_step_ignores_a_pr_that_rewrites_the_scope_script(self):
        evil = "import sys\nprint('output: target=false')\nopen(__import__('os').environ['GITHUB_OUTPUT'],'a').write('target=false\\n')\n"
        self.r.pr("evil", {"tools/ci/release_scope.py": evil, "wp-theme/omochix-theme/functions.php": "<?php // x\n"})
        code, log, outputs = self.run_step(*self.SCOPE, GITHUB_EVENT_NAME="pull_request", BEFORE="")
        self.assertEqual(code, 0, log)
        self.assertEqual(outputs.strip(), "target=true")

    def test_release_scope_step_after_a_push_to_main(self):
        before = self.r.git("rev-parse", "main")
        self.r.git("checkout", "-q", "main")
        self.r.write("docs/readme.md", "x\n")
        self.r.commit("direct push")
        code, log, outputs = self.run_step(*self.SCOPE, GITHUB_EVENT_NAME="push", BEFORE=before)
        self.assertEqual(code, 0, log)
        self.assertIn("target=true", outputs)

    INTEG = ("integration.yml", "gate", "Integration gate (versions, release settings, ownership)")

    def test_integration_step_pass_and_fail(self):
        self.r.git("branch", "integrate/web2", "main")
        self.r.pr("web2/kh/a", {"docs/web2/kh/a.md": "x\n"}, base="integrate/web2")
        code, log, _ = self.run_step(*self.INTEG, EVENT="pull_request", HEAD_REF="web2/kh/a", SAME_REPO="true", BEFORE="")
        self.assertEqual(code, 0, log)
        code, log, _ = self.run_step(*self.INTEG, EVENT="pull_request", HEAD_REF="web2/seo/a", SAME_REPO="true", BEFORE="")
        self.assertEqual(code, 1, log)
        self.assertIn("outside stream 'seo'", log)

    def test_release_scope_step_ignores_a_rewritten_copy_helper(self):
        # The attack on the old design: the pull request brings its own copy helper and scope script.
        self.r.pr("evil", {"tools/ci/use-base-copy.sh": EVIL_COPY, "tools/ci/release_scope.py": EVIL_SCOPE,
                           "wp-theme/omochix-theme/style.css": STYLE.replace("1.30.0", "9.9.9")})
        code, log, outputs = self.run_step(*self.SCOPE, GITHUB_EVENT_NAME="pull_request")
        self.assertEqual(code, 0, log)
        self.assertEqual(outputs.strip(), "target=true")
        self.assertNotIn("evil", log)

    def test_release_scope_docs_pr_with_an_evil_helper_is_still_judged_by_the_base(self):
        # Only docs and tools/ci change: the base script judges, and tools/ci is a release path.
        self.r.pr("evil-docs", {"tools/ci/use-base-copy.sh": EVIL_COPY, "tools/ci/release_scope.py": EVIL_SCOPE, "docs/readme.md": "x\n"})
        code, log, outputs = self.run_step(*self.SCOPE, GITHUB_EVENT_NAME="pull_request")
        self.assertEqual(outputs.strip(), "target=true", log)

    def test_integration_step_ignores_a_rewritten_copy_helper(self):
        self.r.git("branch", "integrate/web2", "main")
        self.r.pr("web2/kh/x", {"tools/ci/use-base-copy.sh": EVIL_COPY, "tools/ci/integration_gate.py": EVIL_GATE,
                                "wp-theme/omochix-theme/style.css": STYLE.replace("1.30.0", "9.9.9"),
                                "wp-theme/omochix-theme/front-page.php": "<?php // held\n"}, base="integrate/web2")
        code, log, _ = self.run_step(*self.INTEG, EVENT="pull_request", HEAD_REF="web2/kh/x", SAME_REPO="true", BEFORE="")
        self.assertEqual(code, 1, log)
        self.assertNotIn("evil", log)
        for text in ("theme style.css Version changed", "front-page.php is held", "tools/ci/integration_gate.py is outside stream"):
            self.assertIn(text, log)

    def test_integration_step_needs_a_merge_commit_and_a_known_event(self):
        self.r.git("checkout", "-q", "-B", "integrate/web2", "main")
        self.r.write("docs/web2/kh/a.md", "x\n")
        self.r.commit("plain")
        code, log, _ = self.run_step(*self.INTEG, EVENT="pull_request", HEAD_REF="web2/kh/a", SAME_REPO="true", BEFORE="")
        self.assertEqual(code, 1, log)
        self.assertIn("not the pull request's merge commit", log)
        code, log, _ = self.run_step(*self.INTEG, EVENT="workflow_dispatch", HEAD_REF="", SAME_REPO="true", BEFORE="")
        self.assertEqual(code, 1, log)
        self.assertIn("unexpected event", log)

    def test_integration_step_on_a_new_branch_push_uses_main_rules(self):
        self.r.git("checkout", "-q", "-B", "integrate/web2", "main")
        self.r.write("tools/ci/integration_gate.py", EVIL_GATE)
        self.r.write("wp-theme/omochix-theme/style.css", STYLE.replace("1.30.0", "1.31.0"))
        self.r.commit("new branch with its own gate")
        code, log, _ = self.run_step(*self.INTEG, EVENT="push", HEAD_REF="", SAME_REPO="false", BEFORE="0" * 40)
        self.assertEqual(code, 1, log)
        self.assertNotIn("evil", log)
        self.assertIn("theme style.css Version changed", log)

    def test_integration_step_on_push(self):
        self.r.git("checkout", "-q", "-B", "integrate/web2", "main")
        before = self.r.git("rev-parse", "HEAD")
        self.r.write("wp-theme/omochix-theme/style.css", STYLE.replace("1.30.0", "1.31.0"))
        self.r.commit("version on integrate")
        code, log, _ = self.run_step(*self.INTEG, EVENT="push", HEAD_REF="", SAME_REPO="false", BEFORE=before)
        self.assertEqual(code, 1, log)
        self.assertIn("theme style.css Version changed", log)

    GUARD = ("ci-guard.yml", "guard", "CI change guard (base rules against this change)")

    def target_env(self, branch, labels="", body=""):
        """pull_request_target: HEAD is the pull request head (checked out as data), GITHUB_SHA the base tip."""
        base = self.r.git("rev-parse", "main")
        head = self.r.git("rev-parse", branch)
        self.r.git("checkout", "-q", "--detach", head)
        return dict(EVENT="pull_request_target", GITHUB_SHA=base, PR_HEAD_SHA=head, BEFORE="", PR_LABELS=labels, PR_BODY=body)

    def test_guard_step_with_and_without_the_approval(self):
        self.r.pr("ci", {".github/workflows/labs.yml": lambda s: s + "\n# x\n"})
        code, log, _ = self.run_step(*self.GUARD, **self.target_env("ci"))
        self.assertEqual(code, 1, log)
        line = [l.split("description: ", 1)[1] for l in log.splitlines() if "description: ci-change-reviewed:" in l][0]
        code, log, _ = self.run_step(*self.GUARD, **self.target_env("ci", labels="docs,ci-change-reviewed"))
        self.assertEqual(code, 1, log)                       # label without the digest
        code, log, _ = self.run_step(*self.GUARD, **self.target_env("ci", labels="docs,ci-change-reviewed", body="LGTM\r\n" + line + "\r\n"))
        self.assertEqual(code, 0, log)

    def test_guard_step_judges_with_the_base_even_if_the_pr_rewrites_the_guard(self):
        # The pull request replaces both the guard workflow and the guard script with "pass".
        evil_yml = "name: CI guard\non:\n  pull_request_target:\npermissions: {}\njobs:\n  guard:\n    name: CI change guard\n    runs-on: ubuntu-24.04\n    steps:\n      - run: echo PASS\n"
        self.r.pr("evil", {".github/workflows/ci-guard.yml": evil_yml, "tools/ci/ci_guard.py": "import sys\nsys.exit(0)\n",
                           ".github/workflows/release.yml": lambda s: s.replace("run: php tests/release/release-gate.php", "run: echo skipped", 1)})
        code, log, _ = self.run_step(*self.GUARD, **self.target_env("evil", labels="ci-change-reviewed,ci-weakening-approved"))
        self.assertEqual(code, 1, log)
        self.assertIn("no longer runs 'php tests/release/release-gate.php'", log)
        self.assertIn("tools/ci/ci_guard.py", log)

    def test_guard_step_fails_on_a_wrong_head_or_a_conflict(self):
        self.r.pr("ci", {".github/workflows/labs.yml": lambda s: s + "\n# x\n"})
        env = self.target_env("ci")
        env["PR_HEAD_SHA"] = "0" * 40
        code, log, _ = self.run_step(*self.GUARD, **env)
        self.assertEqual(code, 1, log)
        self.assertIn("not the pull request head", log)
        self.r.git("checkout", "-q", "main")
        self.r.write("docs/readme.md", "main side\n")
        self.r.commit("main moves")
        self.r.git("checkout", "-q", "-B", "conflict", "main~1")
        self.r.write("docs/readme.md", "branch side\n")
        self.r.commit("conflicting")
        code, log, _ = self.run_step(*self.GUARD, **self.target_env("conflict"))
        self.assertEqual(code, 1, log)
        self.assertIn("does not merge cleanly", log)

    def test_guard_step_unexpected_event_fails(self):
        code, log, _ = self.run_step(*self.GUARD, EVENT="pull_request", BEFORE="", PR_LABELS="", PR_BODY="", PR_HEAD_SHA="", GITHUB_SHA="")
        self.assertEqual(code, 1, log)
        self.assertIn("unexpected event", log)

    def test_guard_step_after_a_direct_push_fails(self):
        before = self.r.git("rev-parse", "main")
        self.r.git("checkout", "-q", "main")
        self.r.write(".github/workflows/labs.yml", self.r.read(".github/workflows/labs.yml") + "\n# x\n")
        self.r.commit("push")
        code, log, _ = self.run_step(*self.GUARD, EVENT="push", BEFORE=before, PR_LABELS="", PR_BODY="", PR_HEAD_SHA="", GITHUB_SHA="")
        self.assertEqual(code, 1, log)
        self.assertIn("not a single merge commit", log)

    def test_guard_step_after_an_approved_merge(self):
        before = self.r.git("rev-parse", "main")
        self.r.pr("ci", {".github/workflows/labs.yml": lambda s: s + "\n# x\n"})
        head = self.r.git("rev-parse", "HEAD")
        self.r.git("update-ref", "refs/heads/main", head)                   # main after the merge
        inv = json.loads(self.r.git("show", before + ":tools/ci/required-checks.json"))
        ok, lines = ci_guard.guard(before, head, "pull_request", [], inventory=inv, cwd=self.r.dir)
        line = [l.split("description: ", 1)[1] for l in lines if "description: ci-change-reviewed:" in l][0]
        pulls = json.dumps([{"number": 7, "merged_at": "x", "merge_commit_sha": head, "labels": [{"name": "ci-change-reviewed"}], "body": line}])
        env = dict(EVENT="push", BEFORE=before, PR_LABELS="", PR_BODY="", PR_HEAD_SHA="", GITHUB_SHA="")
        code, log, _ = self.run_step(*self.GUARD, pre={"ci-merged-pulls.json": pulls}, **env)
        self.assertEqual(code, 0, log)
        self.assertIn("approved there", log)
        code, log, _ = self.run_step(*self.GUARD, **env)                    # no API answer
        self.assertEqual(code, 1, log)
        code, log, _ = self.run_step(*self.GUARD, pre={"ci-merged-pulls.json": "not json"}, **env)
        self.assertEqual(code, 1, log)

    FETCH = ("ci-guard.yml", "guard", "Merged pull request of this push (read-only API)")

    def fake_gh(self, script):
        d = tempfile.mkdtemp(prefix="omx-ci-gh-")
        self.addCleanup(shutil.rmtree, d, True)
        with open(os.path.join(d, "gh"), "w", encoding="utf-8") as fh:
            fh.write("#!/usr/bin/env bash\n" + script)
        os.chmod(os.path.join(d, "gh"), 0o755)
        return d + os.pathsep + os.environ["PATH"]

    def test_fetch_step_writes_the_api_answer_or_nothing(self):
        doc = workflow_policy.load_yaml(slurp(os.path.join(REPO, ".github/workflows/ci-guard.yml")))
        st = [x for x in doc["jobs"]["guard"]["steps"] if x.get("name") == self.FETCH[2]][0]
        self.assertEqual(st["if"], "github.event_name == 'push'")
        self.assertEqual(set(st["env"]), {"GH_TOKEN", "REPO", "SHA"})
        rt = tempfile.mkdtemp(prefix="omx-ci-rt-")
        self.addCleanup(shutil.rmtree, rt, True)
        for name, gh, want in (("ok", 'echo "[{\\"number\\": 1}]"\n', True), ("api error", "echo boom >&2; exit 1\n", False)):
            with self.subTest(name):
                f = os.path.join(rt, "ci-merged-pulls.json")
                if os.path.exists(f):
                    os.remove(f)
                e = dict(os.environ, RUNNER_TEMP=rt, PATH=self.fake_gh(gh), REPO="o/r", SHA="a" * 40, GH_TOKEN="x")
                res = subprocess.run(["bash", "-e", "-c", st["run"]], cwd=self.r.dir, env=e, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                self.assertEqual(res.returncode, 0, res.stderr)
                self.assertEqual(os.path.exists(f), want)

    def test_guard_step_after_a_push_that_weakens_fails(self):
        before = self.r.git("rev-parse", "main")
        self.r.git("checkout", "-q", "main")
        self.r.write(".github/workflows/labs.yml", self.r.read(".github/workflows/labs.yml").replace("contents: read", "contents: write", 1))
        self.r.commit("push")
        code, log, _ = self.run_step(*self.GUARD, EVENT="push", BEFORE=before, PR_LABELS="", PR_BODY="", PR_HEAD_SHA="", GITHUB_SHA="")
        self.assertEqual(code, 1, log)

    # Release gate scenarios, one per case of the audit (the real scope step of release.yml).
    def scope(self, event, before=""):
        return self.run_step(*self.SCOPE, GITHUB_EVENT_NAME=event, BEFORE=before)

    def test_release_scenarios(self):
        cases = [
            ("normal PR to main", "pull_request", {"docs/readme.md": "x\n"}, "target=false"),
            ("release PR to main", "pull_request", {"wp-theme/omochix-theme/style.css": STYLE.replace("1.30.0", "1.31.0")}, "target=true"),
            ("PR with a CI change", "pull_request", {".github/workflows/labs.yml": lambda s: s + "\n# x\n"}, "target=true"),
            ("PR with a CI script change", "pull_request", {"tools/ci/release_scope.py": lambda s: s + "\n# x\n"}, "target=true"),
        ]
        for name, event, changes, want in cases:
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                self.r.pr("s-%d" % len(name), changes)
                code, log, outputs = self.scope(event)
                self.assertEqual(code, 0, log)
                self.assertEqual(outputs.strip(), want)
        for name, changes in (("normal push to main", {"docs/readme.md": "y\n"}),
                              ("release push to main", {"wp-plugin/omochix-core/includes/new.php": "<?php\n"})):
            with self.subTest(name):
                self.r.git("checkout", "-q", "main")
                before = self.r.git("rev-parse", "HEAD")
                self.r.apply(changes)
                self.r.commit(name)
                code, log, outputs = self.scope("push", before)
                self.assertEqual(code, 0, log)
                self.assertEqual(outputs.strip(), "target=true")
        for event in ("workflow_dispatch", "merge_group", "something-new"):
            with self.subTest(event):
                code, log, outputs = self.scope(event)
                self.assertEqual(code, 0, log)
                self.assertEqual(outputs.strip(), "target=true")


# ----------------------------------------------- Release gate positive control (integrate/*)

# The integration branches cannot change the release settings (release_only in
# docs/web2/ownership.json), so on a pull request into integrate/* the tree under test is
# not a release candidate and the Release gate would reject any package change. The
# positive control then runs on the tree the settings were written for: the fork point
# with main. Everything else (main, pushes to main, manual or local runs, unknown targets)
# keeps the strict check on this tree. The Release gate itself is unchanged, and on main
# it still runs on every pull request and push as a required check (release.yml).
# Git branch name segments only: no "..", no segment starting with "." (git refuses both).
INTEGRATE_TARGET_RE = re.compile(r"(?!.*\.\.)integrate(?:/(?!\.)[A-Za-z0-9._-]+)+")
RELEASE_SETTING_PATHS = ("tools/release", "tests/release")
PACKAGE_DIRS = ("wp-plugin", "wp-theme")


def release_selftest_target(env=None, cwd=REPO, main_ref="origin/main"):
    """Which tree the Release gate positive control runs on.

    Returns a dict: mode ("this-tree", "fork-point" or "error"), target, head, base, reason.
    "error" (integrate target without a usable fork point) must fail the test: fail closed.
    """
    env = os.environ if env is None else env
    event = env.get("GITHUB_EVENT_NAME", "")
    if event in ("pull_request", "pull_request_target"):
        target = env.get("GITHUB_BASE_REF", "")
    elif event == "push":
        target = env.get("GITHUB_REF_NAME", "")
    else:
        target = ""
    out = {"mode": "this-tree", "target": target, "event": event, "head": "", "base": ""}

    def git(*args):
        return subprocess.run(["git", *args], cwd=cwd, check=True, stdout=subprocess.PIPE,
                              stderr=subprocess.PIPE).stdout.decode().strip()

    try:
        out["head"] = git("rev-parse", "--verify", "HEAD^{commit}")
    except (subprocess.CalledProcessError, OSError):
        out["head"] = "?"
    if not INTEGRATE_TARGET_RE.fullmatch(target):
        out["reason"] = "target %r (event %r) is not integrate/*: strict check on this tree" % (target, event)
        return out
    try:
        base = git("merge-base", "HEAD", main_ref)
        git("cat-file", "-e", base + "^{commit}")
    except (subprocess.CalledProcessError, OSError):
        out.update(mode="error", reason="target %r is integrate/* but the fork point with %s cannot be found: fail closed" % (target, main_ref))
        return out
    out["base"] = base
    changed = subprocess.run(["git", "diff", "--quiet", base, "HEAD", "--", *RELEASE_SETTING_PATHS], cwd=cwd,
                             stdout=subprocess.PIPE, stderr=subprocess.PIPE).returncode
    if changed != 0:
        out["reason"] = "target %r is integrate/* but %s differ from the fork point %s (or git failed): strict check on this tree" % (
            target, " or ".join(RELEASE_SETTING_PATHS), base[:12])
        return out
    out.update(mode="fork-point", reason="target %r is integrate/*: the release settings are the fork point's (%s), so the gate's positive control runs on that tree" % (target, base[:12]))
    return out


def export_packages(cwd, rev, dest):
    """Write the package folders of rev (wp-plugin, wp-theme) into dest (git archive, nothing runs)."""
    data = subprocess.run(["git", "archive", "--format=tar", rev, *PACKAGE_DIRS], cwd=cwd, check=True,
                          stdout=subprocess.PIPE, stderr=subprocess.PIPE).stdout
    with tarfile.open(fileobj=io.BytesIO(data)) as tar:
        if hasattr(tarfile, "data_filter"):
            tar.extractall(dest, filter="data")
        else:  # Python < 3.12: our own archive, members checked by hand
            for m in tar.getmembers():
                if m.name.startswith("/") or ".." in m.name.split("/") or not (m.isfile() or m.isdir()):
                    raise ValueError("unexpected archive member %r" % m.name)
            tar.extractall(dest)


@unittest.skipUnless(shutil.which("php"), "php not installed (the Release workflow runs this gate itself)")
def log_release_selftest(target, stream=None):
    """One line in the job log: which tree the positive control judges, and why."""
    line = "\nrelease self-test: mode=%s event=%r target=%r head=%s fork-point=%s\n  %s\n" % (
        target["mode"], target["event"], target["target"], target["head"][:12], (target["base"] or "-")[:12], target["reason"])
    (stream or sys.stderr).write(line)
    return line


def release_control_tree(testcase, target, cwd=REPO):
    """The tree the positive control runs on: None (this tree) or a temporary export of the fork point."""
    if target["mode"] != "fork-point":
        return None
    tree = tempfile.mkdtemp(prefix="omx-ci-release-tree-")
    testcase.addCleanup(shutil.rmtree, tree, True)
    export_packages(cwd, target["base"], tree)
    return tree


class ReleaseGateStillStrict(unittest.TestCase):
    """The Release gate script is unchanged and still fails on an unapproved package change."""

    def run_gate(self, tree=None):
        env = dict(os.environ)
        env.pop("OMOCHIX_RELEASE_TREE", None)
        if tree:
            env["OMOCHIX_RELEASE_TREE"] = tree
        return subprocess.run(["php", os.path.join(REPO, "tests/release/release-gate.php")], cwd=REPO, env=env,
                              stdout=subprocess.PIPE, stderr=subprocess.PIPE)

    def test_gate_passes_on_the_release_tree(self):
        t = release_selftest_target()
        log_release_selftest(t)  # the run shows which tree was judged and why
        self.assertNotEqual(t["mode"], "error", t["reason"])
        res = self.run_gate(release_control_tree(self, t))
        self.assertEqual(res.returncode, 0, t["reason"] + "\n" + res.stdout.decode()[-2000:])

    def test_gate_fails_on_an_unapproved_theme_change(self):
        tree = tempfile.mkdtemp(prefix="omx-ci-tree-")
        self.addCleanup(shutil.rmtree, tree, True)
        for pkg in ("wp-plugin", "wp-theme"):
            shutil.copytree(os.path.join(REPO, pkg), os.path.join(tree, pkg))
        # A file that is not one of the release's approved steps.
        with open(os.path.join(tree, "wp-theme/omochix-theme/unapproved.php"), "w", encoding="utf-8") as fh:
            fh.write("<?php // unapproved change\n")
        res = self.run_gate(tree)
        self.assertEqual(res.returncode, 1)
        self.assertIn("FAIL scope: the files that differ from production are exactly the approved steps", res.stdout.decode())

    def test_integrate_mode_still_rejects_unapproved_package_changes(self):
        # The fork-point tree passes, and the same tree with an unapproved Core and theme
        # change still fails: the switch changes which tree is the control, not the gate.
        t = release_selftest_target({"GITHUB_EVENT_NAME": "pull_request", "GITHUB_BASE_REF": "integrate/web2"})
        self.assertEqual(t["mode"], "fork-point", t["reason"])
        tree = release_control_tree(self, t)
        self.assertEqual(self.run_gate(tree).returncode, 0)
        with open(os.path.join(tree, "wp-plugin/omochix-core/includes/unapproved-module.php"), "w", encoding="utf-8") as fh:
            fh.write("<?php // unapproved Core file\n")
        with open(os.path.join(tree, "wp-theme/omochix-theme/functions.php"), "a", encoding="utf-8") as fh:
            fh.write("\n// unapproved theme change\n")
        res = self.run_gate(tree)
        out = res.stdout.decode()
        self.assertEqual(res.returncode, 1, out[-2000:])
        self.assertIn("FAIL core: byte-identical to production", out)
        self.assertIn("includes/unapproved-module.php", out)
        self.assertIn("FAIL scope: the files that differ from production are exactly the approved steps", out)
        self.assertIn("theme:functions.php", out)


class ReleaseSelftestTarget(RepoCase):
    """Counterexamples for the tree choice (throwaway repositories, no PHP)."""

    PR = {"GITHUB_EVENT_NAME": "pull_request"}

    def integrate_pr(self, changes):
        """integrate/web2 forked from main, then a stream pull request merged like refs/pull/N/merge."""
        self.fork = self.r.git("rev-parse", "main")
        self.r.git("branch", "integrate/web2", "main")
        self.r.write("docs/readme.md", "main moved on\n")
        self.r.commit("main after the fork")
        self.r.git("update-ref", "refs/remotes/origin/main", "main")
        return self.r.pr("web2/hooks/x", changes, base="integrate/web2")

    def target(self, env, main_ref="origin/main"):
        return release_selftest_target(env, cwd=self.r.dir, main_ref=main_ref)

    def test_pull_request_into_integrate_uses_the_fork_point(self):
        self.integrate_pr({"wp-plugin/omochix-core/includes/web2-loader.php": "<?php\n"})
        t = self.target(dict(self.PR, GITHUB_BASE_REF="integrate/web2"))
        self.assertEqual((t["mode"], t["base"]), ("fork-point", self.fork), t["reason"])
        self.assertIn(self.fork[:12], t["reason"])

    def test_push_to_integrate_uses_the_fork_point(self):
        self.integrate_pr({"wp-theme/omochix-theme/inc/web2-loader.php": "<?php\n"})
        t = self.target({"GITHUB_EVENT_NAME": "push", "GITHUB_REF_NAME": "integrate/web2"})
        self.assertEqual((t["mode"], t["base"]), ("fork-point", self.fork))

    def test_the_exported_tree_is_the_fork_point_not_the_pull_request(self):
        self.integrate_pr({"wp-theme/omochix-theme/functions.php": "<?php\n// changed by the stream\n",
                           "wp-plugin/omochix-core/includes/new.php": "<?php\n"})
        t = self.target(dict(self.PR, GITHUB_BASE_REF="integrate/web2"))
        self.assertNotEqual(self.r.git("rev-parse", "HEAD"), t["base"])
        dest = release_control_tree(self, t, cwd=self.r.dir)
        self.assertEqual(slurp(os.path.join(dest, "wp-theme/omochix-theme/functions.php")), "<?php\n")
        self.assertFalse(os.path.exists(os.path.join(dest, "wp-plugin/omochix-core/includes/new.php")))
        self.assertFalse(os.path.exists(os.path.join(dest, "tools")))
        # Strict modes get no export: the gate then runs on this tree.
        self.assertIsNone(release_control_tree(self, dict(t, mode="this-tree"), cwd=self.r.dir))

    def test_main_and_other_targets_keep_the_strict_check(self):
        self.integrate_pr({"wp-plugin/omochix-core/includes/web2-loader.php": "<?php\n"})
        for env in (dict(self.PR, GITHUB_BASE_REF="main"),
                    {"GITHUB_EVENT_NAME": "push", "GITHUB_REF_NAME": "main"},
                    {"GITHUB_EVENT_NAME": "workflow_dispatch", "GITHUB_REF_NAME": "integrate/web2"},
                    {"GITHUB_EVENT_NAME": "schedule", "GITHUB_BASE_REF": "integrate/web2"}):
            self.assertEqual(self.target(env)["mode"], "this-tree", env)

    def test_bad_branch_names_keep_the_strict_check(self):
        self.integrate_pr({"wp-plugin/omochix-core/includes/web2-loader.php": "<?php\n"})
        for name in ("integrate", "integrate/", "integrate-web2", "xintegrate/web2", "refs/heads/integrate/web2",
                     "integrate//web2", "integrate/web2/", " integrate/web2", "integrate/web2\n", "Integrate/web2",
                     "integrate/web 2", "main/integrate/web2", "integrate/../main", "integrate/.hidden", "integrate/web2/../x", "integrate/a..b", "integrate/web2.."):
            self.assertEqual(self.target(dict(self.PR, GITHUB_BASE_REF=name))["mode"], "this-tree", repr(name))
        # push: the base ref of a pull request is not used.
        self.assertEqual(self.target({"GITHUB_EVENT_NAME": "push", "GITHUB_BASE_REF": "integrate/web2", "GITHUB_REF_NAME": "main"})["mode"], "this-tree")
        # pull request: the pushed ref name is not used.
        self.assertEqual(self.target(dict(self.PR, GITHUB_BASE_REF="main", GITHUB_REF_NAME="integrate/web2"))["mode"], "this-tree")

    def test_missing_environment_keeps_the_strict_check(self):
        self.integrate_pr({"wp-plugin/omochix-core/includes/web2-loader.php": "<?php\n"})
        for env in ({}, {"GITHUB_BASE_REF": "integrate/web2"}, {"GITHUB_EVENT_NAME": "pull_request"},
                    {"GITHUB_EVENT_NAME": "", "GITHUB_BASE_REF": "integrate/web2"}, {"GITHUB_REF_NAME": "integrate/web2"},
                    {"GITHUB_EVENT_NAME": "pull_request", "GITHUB_BASE_REF": "", "GITHUB_REF_NAME": "integrate/web2"},
                    {"GITHUB_EVENT_NAME": "push", "GITHUB_REF_NAME": ""}):
            self.assertEqual(self.target(env)["mode"], "this-tree", env)

    def test_no_fork_point_fails_closed(self):
        self.integrate_pr({"wp-plugin/omochix-core/includes/web2-loader.php": "<?php\n"})
        env = dict(self.PR, GITHUB_BASE_REF="integrate/web2")
        self.assertEqual(self.target(env, main_ref="origin/no-such-branch")["mode"], "error")
        self.r.git("update-ref", "-d", "refs/remotes/origin/main")
        t = self.target(env)
        self.assertEqual(t["mode"], "error")
        self.assertIn("fail closed", t["reason"])
        # Unrelated history: no common ancestor with main.
        self.r.git("checkout", "-q", "--orphan", "lonely")
        self.r.commit("unrelated")
        self.r.git("update-ref", "refs/remotes/origin/main", "main")
        self.assertEqual(self.target(env)["mode"], "error")

    def test_changed_release_settings_keep_the_strict_check(self):
        for path in ("tools/release/release.json", "tests/release/expected/release.json", "tests/release/release-gate.php"):
            with self.subTest(path=path):
                self.r.cleanup()
                self.r = Repo()
                self.integrate_pr({path: "{\"changed\": true}\n"})
                t = self.target(dict(self.PR, GITHUB_BASE_REF="integrate/web2"))
                self.assertEqual(t["mode"], "this-tree", path)
                self.assertIn("differ from the fork point", t["reason"])

    def test_the_choice_is_logged_with_both_shas(self):
        self.integrate_pr({"wp-plugin/omochix-core/includes/web2-loader.php": "<?php\n"})
        t = self.target(dict(self.PR, GITHUB_BASE_REF="integrate/web2"))
        buf = io.StringIO()
        line = log_release_selftest(t, buf)
        self.assertEqual(buf.getvalue(), line)
        for part in ("mode=fork-point", "target='integrate/web2'", "head=" + t["head"][:12], "fork-point=" + self.fork[:12], t["reason"]):
            self.assertIn(part, line)
        src = slurp(os.path.join(REPO, "tests/ci/test_ci.py"))
        body = src[src.index("    def test_gate_passes_on_the_release_tree(self):"):src.index("    def test_gate_fails_on_an_unapproved_theme_change(self):")]
        self.assertIn("log_release_selftest(t)", body)

    def test_the_test_itself_fails_on_error_mode(self):
        # The positive control asserts mode != error before running anything.
        src = slurp(os.path.join(REPO, "tests/ci/test_ci.py"))
        body = src[src.index("    def test_gate_passes_on_the_release_tree(self):"):src.index("    def test_gate_fails_on_an_unapproved_theme_change(self):")]
        self.assertIn('self.assertNotEqual(t["mode"], "error", t["reason"])', body)
        self.assertLess(body.index('assertNotEqual(t["mode"], "error"'), body.index("self.run_gate("))


if __name__ == "__main__":
    unittest.main()
