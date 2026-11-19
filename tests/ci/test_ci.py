"""CI self-tests: Release scope, Integration gate, CI change guard, workflow policy.

Each case builds a throwaway git repository with the real tools/ci/, workflows and
ownership file, makes a branch, and merges it with --no-ff the way GitHub builds
refs/pull/N/merge (first parent = base tip, second parent = the pull request head).
Normal and failure cases for every gate. Standard library only; Python 3.9+.

Run: python3 -m unittest discover -s tests/ci -v
"""
import json
import os
import shutil
import subprocess
import sys
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

def drop_line(text, line):
    assert line in text, line
    return text.replace(line, "", 1)


class Guard(RepoCase):
    def base_inventory(self):
        return json.loads(self.r.git("show", "HEAD^1:tools/ci/required-checks.json"))

    def guard(self, labels=(), event="pull_request", body=""):
        return ci_guard.guard("HEAD^1", "HEAD", event, list(labels), inventory=self.base_inventory(), cwd=self.r.dir, body=body)

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

    def test_push_with_ci_changes_is_reported_not_failed(self):
        self.r.pr("ci", {".github/workflows/tests.yml": lambda s: s + "\n# comment\n"})
        ok, lines = self.guard(event="push")
        self.assertTrue(ok, "\n".join(lines))
        self.assertTrue(any(l.startswith("NOTE") and "push" in l for l in lines))

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
        "previous-release-gate-removed": (".github/workflows/release.yml", lambda s: s.replace('OMOCHIX_RELEASE_TREE="$tree" php tests/release/release-gate.php', "true", 1), "no longer runs 'OMOCHIX_RELEASE_TREE"),
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
                self.assertFalse(any("description: ci-weakening-approved:" in l for l in lines))
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
            self.assertEqual(job["permissions"], {"contents": "read"})
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

    def run_step(self, workflow, job, step, **env):
        doc = workflow_policy.load_yaml(slurp(os.path.join(REPO, ".github/workflows", workflow)))
        st = [x for x in doc["jobs"][job]["steps"] if x.get("name") == step][0]
        self.assertNotIn("${{", st["run"], "event data must reach the script through env")
        rt = tempfile.mkdtemp(prefix="omx-ci-rt-")
        self.addCleanup(shutil.rmtree, rt, True)
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

    def test_guard_step_after_a_push(self):
        before = self.r.git("rev-parse", "main")
        self.r.git("checkout", "-q", "main")
        self.r.write(".github/workflows/labs.yml", self.r.read(".github/workflows/labs.yml") + "\n# x\n")
        self.r.commit("push")
        code, log, _ = self.run_step(*self.GUARD, EVENT="push", BEFORE=before, PR_LABELS="", PR_BODY="", PR_HEAD_SHA="", GITHUB_SHA="")
        self.assertEqual(code, 0, log)
        self.assertIn("cannot be stopped afterwards", log)

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


@unittest.skipUnless(shutil.which("php"), "php not installed (the Release workflow runs this gate itself)")
class ReleaseGateStillStrict(unittest.TestCase):
    """The Release gate script is unchanged and still fails on an unapproved package change."""

    def run_gate(self, tree=None):
        env = dict(os.environ)
        if tree:
            env["OMOCHIX_RELEASE_TREE"] = tree
        return subprocess.run(["php", os.path.join(REPO, "tests/release/release-gate.php")], cwd=REPO, env=env,
                              stdout=subprocess.PIPE, stderr=subprocess.PIPE)

    def test_gate_passes_on_this_tree(self):
        res = self.run_gate()
        self.assertEqual(res.returncode, 0, res.stdout.decode()[-2000:])

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


if __name__ == "__main__":
    unittest.main()
