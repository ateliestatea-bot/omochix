"""Shared helpers for the CI gate scripts in tools/ci/ (Python 3.9+, standard library only).

Every gate fails closed: when git cannot answer (missing commit, shallow clone, bad
revision) the caller treats the question as "this needs the strict check".
Nothing here reads secrets, calls the network or writes outside the files named by
GITHUB_OUTPUT / GITHUB_STEP_SUMMARY.
"""
import os
import re
import subprocess

ZERO_SHA = "0" * 40


class GitError(Exception):
    pass


def git(*args, cwd=None):
    """Run git and return stdout as bytes; raise GitError on failure."""
    try:
        res = subprocess.run(["git", *args], cwd=cwd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
    except OSError as exc:  # git missing
        raise GitError(str(exc))
    if res.returncode != 0:
        raise GitError("git %s: %s" % (" ".join(args), res.stderr.decode("utf-8", "replace").strip()))
    return res.stdout


def rev(name, cwd=None):
    return git("rev-parse", "--verify", "--quiet", name + "^{commit}", cwd=cwd).decode().strip()


def parents(name, cwd=None):
    out = git("rev-list", "--parents", "-n", "1", name, cwd=cwd).decode().split()
    return out[1:]


def changed_paths(base, head, cwd=None):
    """Paths that differ between two commits (two-dot), renames split into delete + add.

    --no-renames matters: with rename detection a move out of wp-theme/ would list only the
    new path and hide that a release file was touched.
    """
    out = git("diff", "--no-renames", "--name-only", "-z", base, head, cwd=cwd)
    return sorted(p for p in out.decode("utf-8", "surrogateescape").split("\0") if p)


def new_modes(base, head, cwd=None):
    """{path: new file mode} for every path changed between two commits ('000000' = deleted)."""
    out = git("diff", "--no-renames", "--raw", "-z", base, head, cwd=cwd).decode("utf-8", "surrogateescape").split("\0")
    modes, i = {}, 0
    while i + 1 < len(out):
        meta, path = out[i], out[i + 1]
        if meta.startswith(":"):
            modes[path] = meta[1:].split(" ")[1]
        i += 2
    return modes


def blob(revision, path, cwd=None):
    """Object id of a path at a revision, or None if it does not exist there."""
    try:
        return git("rev-parse", "--verify", "--quiet", "%s:%s" % (revision, path), cwd=cwd).decode().strip() or None
    except GitError:
        return None


def show(revision, path, cwd=None):
    """File contents at a revision, or None if the path does not exist there."""
    try:
        return git("show", "%s:%s" % (revision, path), cwd=cwd).decode("utf-8", "replace")
    except GitError:
        return None


def is_ancestor(a, b, cwd=None):
    try:
        res = subprocess.run(["git", "merge-base", "--is-ancestor", a, b], cwd=cwd,
                             stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=False)
    except OSError:
        return False
    return res.returncode == 0


_glob_cache = {}


def glob_re(pattern):
    """'**' spans folders, '*' and '?' stay inside one path segment."""
    if pattern not in _glob_cache:
        out, i = [], 0
        while i < len(pattern):
            c = pattern[i]
            if pattern.startswith("**/", i):
                out.append("(?:.*/)?"); i += 3
            elif pattern.startswith("**", i):
                out.append(".*"); i += 2
            elif c == "*":
                out.append("[^/]*"); i += 1
            elif c == "?":
                out.append("[^/]"); i += 1
            else:
                out.append(re.escape(c)); i += 1
        _glob_cache[pattern] = re.compile("^" + "".join(out) + "$")
    return _glob_cache[pattern]


def matches_nocase(path, patterns):
    """Like matches, ignoring case: 'Front-Page.php' is the same file on a case-insensitive disk."""
    return matches(path.lower(), [p.lower() for p in patterns])


def matches(path, patterns):
    return [p for p in patterns if glob_re(p).match(path)]


def write_output(**values):
    """Step outputs for later steps (GITHUB_OUTPUT); printed as well for local runs."""
    target = os.environ.get("GITHUB_OUTPUT")
    lines = ["%s=%s" % (k, v) for k, v in values.items()]
    for line in lines:
        if "\n" in line:
            raise ValueError("output values must be one line")
    if target:
        with open(target, "a", encoding="utf-8") as fh:
            fh.write("".join(l + "\n" for l in lines))
    for line in lines:
        print("output: " + line)


def write_summary(markdown):
    target = os.environ.get("GITHUB_STEP_SUMMARY")
    if target:
        with open(target, "a", encoding="utf-8") as fh:
            fh.write(markdown.rstrip() + "\n\n")


def md_list(paths, limit=50):
    shown = ["- `%s`" % p.replace("`", "'") for p in paths[:limit]]
    if len(paths) > limit:
        shown.append("- ... and %d more" % (len(paths) - limit))
    return "\n".join(shown)
