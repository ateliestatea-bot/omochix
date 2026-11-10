#!/usr/bin/env python3
"""Rehearse a staged deploy and its rollback on a THROWAWAY copy of wp-content.

Production is deployed by the operator's own replace tool (v2) with the same
release-plan.json; this script reproduces that procedure so it can be tested.
It refuses any target that does not contain the marker file `.omochix-rehearsal`.

  python3 tools/release/rehearse.py preflight --plan P --manifests M --source S --target T
  python3 tools/release/rehearse.py apply     --plan P --manifests M --source S --target T --backup B --log L [--probe CMD] [--stop-after N]
  python3 tools/release/rehearse.py verify    --plan P --target T --state release|production
  python3 tools/release/rehearse.py rollback  --plan P --target T --backup B --log L [--probe CMD] [--remove-added]

P = release-plan.json; its SHA-256 must match release-plan.json.sha256 next to it
(or --plan-sha256). M = the folder with the packages' .manifest.sha256 files
(and, when present, the zips): their SHA-256 must be the ones the plan records,
and every step must be the manifest's file. S = the unpacked release
(S/omochix-core/..., S/omochix-theme/...); T = wp-content (T/plugins/..., T/themes/...).

Paths: every path is relative, has no "..", and is opened one folder at a time
without following symlinks (O_NOFOLLOW, relative to the folder's descriptor), so
nothing outside T can be written and a folder swapped for a symlink after the
checks is refused, not followed. Files are written to a new temporary name in
the same folder, checked, then renamed into place. apply checks everything
first and writes nothing if a check fails; a step already at the release SHA-256
is skipped, so a run that stopped can be run again. The audit log holds only
paths relative to T, SHA-256 values and results: no command output.
"""
import argparse
import datetime
import errno
import json
import os
import stat
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import releaselib as rl  # noqa: E402

MARKER = '.omochix-rehearsal'
O_DIR = os.O_RDONLY | getattr(os, 'O_DIRECTORY', 0) | os.O_NOFOLLOW


class Stop(Exception):
    pass


def now():
    return datetime.datetime.now(datetime.timezone.utc).isoformat(timespec='seconds')


class Log:
    """JSON-lines audit log. Callers pass only relative paths, hashes and results."""

    def __init__(self, path, quiet=False):
        self.path = path
        self.quiet = quiet

    def write(self, **entry):
        entry = {'at': now(), **entry}
        line = json.dumps(entry, ensure_ascii=False)
        if self.path:
            with open(self.path, 'a', encoding='utf-8') as fh:
                fh.write(line + '\n')
        if not self.quiet:
            print(line)


# ---------------------------------------------------------------- safe file access

def open_root(path, label):
    """Descriptor of a root folder; the root itself must not be a symlink."""
    try:
        return os.open(path, O_DIR)
    except OSError as e:
        raise Stop(f'{label} folder cannot be opened safely ({errno.errorcode.get(e.errno, e.errno)})')


def open_parent(root_fd, rel, create=False, log=None, step=None):
    """(descriptor of rel's folder, file name). None when a folder is missing and create is False."""
    parts = rl.safe_rel(rel).split('/')
    fd = os.dup(root_fd)
    walked = []
    try:
        for part in parts[:-1]:
            walked.append(part)
            try:
                nfd = os.open(part, O_DIR, dir_fd=fd)
            except FileNotFoundError:
                if not create:
                    os.close(fd)
                    return None, parts[-1]
                os.mkdir(part, 0o755, dir_fd=fd)
                if log:
                    log.write(action='mkdir', step=step, path='/'.join(walked))
                nfd = os.open(part, O_DIR, dir_fd=fd)
            except OSError:
                raise Stop(f'{"/".join(walked)}: a symlink or not a folder (refused)')
            os.close(fd)
            fd = nfd
        return fd, parts[-1]
    except BaseException:
        os.close(fd)
        raise


def read_rel(root_fd, rel):
    """Bytes of rel under root, None when it does not exist; a symlink or special file is refused."""
    fd, name = open_parent(root_fd, rel)
    if fd is None:
        return None
    try:
        try:
            f = os.open(name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=fd)
        except FileNotFoundError:
            return None
        except OSError:
            raise Stop(f'{rel}: a symlink or not a regular file (refused)')
        try:
            if not stat.S_ISREG(os.fstat(f).st_mode):
                raise Stop(f'{rel}: not a regular file (refused)')
            chunks = []
            while True:
                b = os.read(f, 1 << 16)
                if not b:
                    break
                chunks.append(b)
            return b''.join(chunks)
        finally:
            os.close(f)
    finally:
        os.close(fd)


def sha_rel(root_fd, rel):
    data = read_rel(root_fd, rel)
    return None if data is None else rl.sha256_bytes(data)


def write_rel(root_fd, rel, data, sha, log, step):
    """Write rel under root through a new temporary file in the same folder, check it, rename it."""
    if rl.sha256_bytes(data) != sha:
        raise Stop(f'step {step}: the data to write for {rel} is not the planned file')
    fd, name = open_parent(root_fd, rel, create=True, log=log, step=step)
    try:
        try:
            st = os.stat(name, dir_fd=fd, follow_symlinks=False)
            if not stat.S_ISREG(st.st_mode):
                raise Stop(f'{rel}: exists and is a symlink or not a regular file (refused)')
        except FileNotFoundError:
            pass
        tmp = f'.{name}.tmp-release'
        try:
            st = os.stat(tmp, dir_fd=fd, follow_symlinks=False)
            if not stat.S_ISREG(st.st_mode):
                raise Stop(f'{rel}: a leftover temporary name is not a regular file (refused)')
            os.unlink(tmp, dir_fd=fd)
        except FileNotFoundError:
            pass
        f = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o644, dir_fd=fd)
        try:
            os.write(f, data)
            os.fsync(f)
        finally:
            os.close(f)
        check = os.open(tmp, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=fd)
        try:
            got = b''.join(iter(lambda: os.read(check, 1 << 16), b''))
        finally:
            os.close(check)
        if rl.sha256_bytes(got) != sha:
            os.unlink(tmp, dir_fd=fd)
            raise Stop(f'step {step}: the temporary copy of {rel} does not match')
        os.rename(tmp, name, src_dir_fd=fd, dst_dir_fd=fd)
    finally:
        os.close(fd)
    if sha_rel(root_fd, rel) != sha:
        raise Stop(f'step {step}: {rel} does not match after the rename')


def remove_rel(root_fd, rel):
    fd, name = open_parent(root_fd, rel)
    if fd is None:
        return
    try:
        os.unlink(name, dir_fd=fd)
    finally:
        os.close(fd)


# ---------------------------------------------------------------- plan checks

def load_plan(path, expected_sha=None):
    """The plan, after checking its own SHA-256 (release-plan.json.sha256 next to it, or expected_sha)."""
    with open(path, 'rb') as fh:
        data = fh.read()
    if expected_sha is None:
        side = path + '.sha256'
        if not os.path.isfile(side):
            raise Stop('no release-plan.json.sha256 next to the plan and no --plan-sha256')
        with open(side, encoding='utf-8') as fh:
            expected_sha = fh.read().split()[0]
    if rl.sha256_bytes(data) != expected_sha:
        raise Stop('release-plan.json does not match its recorded SHA-256')
    return json.loads(data.decode('utf-8'))


def check_plan(plan):
    """Structure: every step is a safe path inside its package's target, numbered 1..N once."""
    problems = []
    pkgs = plan.get('packages', {})
    for k, p in pkgs.items():
        try:
            rl.safe_rel(p['target'])
            rl.safe_rel(p['name'])
            if '/' in p['name'] or '/' in p.get('manifest', 'x'):
                raise rl.UnsafeInput('names must be plain file names')
        except (rl.UnsafeInput, KeyError) as e:
            problems.append(f'package {k}: {e}')
    for i, s in enumerate(plan.get('steps', []), 1):
        try:
            if s.get('n') != i:
                raise rl.UnsafeInput(f'step numbers must run 1..N (got {s.get("n")} at {i})')
            if s.get('package') not in pkgs:
                raise rl.UnsafeInput(f'unknown package {s.get("package")!r}')
            rl.safe_rel(s['path'])
            if s['target'] != f'{pkgs[s["package"]]["target"]}/{s["path"]}':
                raise rl.UnsafeInput(f'target {s["target"]!r} is not {pkgs[s["package"]]["target"]}/{s["path"]}')
            if s['action'] not in ('add', 'replace') or not rl.HEX64.match(s.get('new_sha256') or ''):
                raise rl.UnsafeInput('bad action or SHA-256')
            if s['action'] == 'replace' and not rl.HEX64.match(s.get('old_sha256') or ''):
                raise rl.UnsafeInput('a replace step needs the production SHA-256')
        except (rl.UnsafeInput, KeyError, TypeError) as e:
            problems.append(f'step {i}: {e}')
    return problems


def check_manifests(plan, manifests):
    """Each package's manifest (and zip, when present) is the one the plan records; every step is in it."""
    problems = []
    for k, p in plan['packages'].items():
        mpath = os.path.join(manifests, p['manifest'])
        if not os.path.isfile(mpath) or os.path.islink(mpath):
            problems.append(f'{p["manifest"]}: missing')
            continue
        with open(mpath, 'rb') as fh:
            mdata = fh.read()
        if rl.sha256_bytes(mdata) != p['manifest_sha256']:
            problems.append(f'{p["manifest"]}: SHA-256 differs from the plan')
            continue
        manifest = rl.parse_manifest(mdata.decode('utf-8'))
        zpath = os.path.join(manifests, p.get('zip', ''))
        if p.get('zip') and os.path.isfile(zpath) and rl.sha256_file(zpath) != p['zip_sha256']:
            problems.append(f'{p["zip"]}: SHA-256 differs from the plan')
        for s in plan['steps']:
            if s['package'] == k and manifest.get(s['path']) != s['new_sha256']:
                problems.append(f'step {s["n"]} {s["target"]}: not the file the manifest lists')
    return problems


def guard_target(target_fd):
    if read_rel(target_fd, MARKER) is None:
        raise Stop(f'the target has no {MARKER}: this tool only runs on a throwaway copy')


def preflight(plan, source, target, manifests):
    """Every problem that would make apply unsafe, without writing anything."""
    problems = check_plan(plan)
    if problems:
        return problems
    problems += check_manifests(plan, manifests)
    tfd = open_root(target, 'target')
    sfd = open_root(source, 'source')
    try:
        guard_target(tfd)
        for s in plan['steps']:
            try:
                if sha_rel(sfd, f'{plan["packages"][s["package"]]["name"]}/{s["path"]}') != s['new_sha256']:
                    problems.append(f'step {s["n"]} {s["target"]}: release file missing or not the planned SHA-256')
                have = sha_rel(tfd, s['target'])
            except Stop as e:
                problems.append(f'step {s["n"]}: {e}')
                continue
            if s['action'] == 'replace' and have not in (s['old_sha256'], s['new_sha256']):
                problems.append(f'step {s["n"]} {s["target"]}: production file is not the expected one ({(have or "missing")[:12]})')
            if s['action'] == 'add' and have not in (None, s['new_sha256']):
                problems.append(f'step {s["n"]} {s["target"]}: a different file already exists')
    finally:
        os.close(tfd)
        os.close(sfd)
    return problems


def run_probe(cmd, log, label):
    """Run the check command; only its exit status is logged (never its output)."""
    if not cmd:
        return
    r = subprocess.run(cmd, shell=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    log.write(action='probe', after=label, result='ok' if r.returncode == 0 else 'failed', rc=r.returncode)
    if r.returncode != 0:
        raise Stop(f'probe failed after {label}')


# ---------------------------------------------------------------- commands

def apply(plan, source, target, backup, log, manifests, probe=None, stop_after=None):
    problems = preflight(plan, source, target, manifests)
    if problems:
        for p in problems:
            log.write(action='preflight', result='fail', detail=p)
        raise Stop('preflight failed: nothing was written')
    log.write(action='preflight', result='pass', steps=len(plan['steps']))
    os.makedirs(backup, exist_ok=True)
    tfd, sfd, bfd = open_root(target, 'target'), open_root(source, 'source'), open_root(backup, 'backup')
    try:
        for s in plan['steps']:
            if s['action'] != 'replace' or sha_rel(bfd, s['target']) == s['old_sha256']:
                continue
            data = read_rel(tfd, s['target'])
            if data is None or rl.sha256_bytes(data) != s['old_sha256']:
                raise Stop(f'no backup of {s["target"]} and the target is no longer the production file')
            write_rel(bfd, s['target'], data, s['old_sha256'], None, s['n'])
            log.write(action='backup', step=s['n'], path=s['target'], sha256=s['old_sha256'])
        for s in plan['steps']:
            if sha_rel(tfd, s['target']) == s['new_sha256']:
                log.write(action='skip', step=s['n'], path=s['target'], reason='already the release file')
            else:
                data = read_rel(sfd, f'{plan["packages"][s["package"]]["name"]}/{s["path"]}')
                write_rel(tfd, s['target'], data, s['new_sha256'], log, s['n'])
                log.write(action=s['action'], step=s['n'], path=s['target'], sha256=s['new_sha256'])
                run_probe(probe, log, f'step {s["n"]}')
            if stop_after and s['n'] >= stop_after:
                raise Stop(f'stopped after step {s["n"]} (--stop-after)')
    finally:
        for fd in (tfd, sfd, bfd):
            os.close(fd)
    for note in plan.get('after_apply', []):
        log.write(action='todo', detail=note)


def verify(plan, target, state):
    problems = check_plan(plan)
    if problems:
        return problems
    tfd = open_root(target, 'target')
    try:
        guard_target(tfd)
        key = 'new_sha256' if state == 'release' else 'old_sha256'
        bad = []
        for s in plan['steps']:
            if state == 'production' and s['action'] == 'add':
                continue
            have = sha_rel(tfd, s['target'])
            if have != s[key]:
                bad.append(f'{s["target"]}: {(have or "missing")[:12]} != {(s[key] or "absent")[:12]}')
        return bad
    finally:
        os.close(tfd)


def rollback(plan, target, backup, log, probe=None, remove_added=False):
    problems = check_plan(plan)
    if problems:
        raise Stop('the plan is not valid: ' + '; '.join(problems))
    steps = {(s['package'], s['path']): s for s in plan['steps']}
    order = [steps[tuple(x)] for x in plan['rollback_order']]
    tfd, bfd = open_root(target, 'target'), open_root(backup, 'backup')
    try:
        guard_target(tfd)
        for s in order:
            if sha_rel(bfd, s['target']) != s['old_sha256']:
                raise Stop(f'backup of {s["target"]} is missing or wrong: nothing was restored')
            if sha_rel(tfd, s['target']) not in (s['old_sha256'], s['new_sha256']):
                raise Stop(f'{s["target"]} is neither the release nor the production file: nothing was restored')
        for s in order:
            if sha_rel(tfd, s['target']) == s['old_sha256']:
                log.write(action='skip', path=s['target'], reason='already the production file')
                continue
            write_rel(tfd, s['target'], read_rel(bfd, s['target']), s['old_sha256'], log, s['n'])
            log.write(action='restore', step=s['n'], path=s['target'], sha256=s['old_sha256'])
            run_probe(probe, log, f'restore {s["path"]}')
        if remove_added:
            for s in reversed(plan['steps']):
                if s['action'] == 'add' and sha_rel(tfd, s['target']) == s['new_sha256']:
                    remove_rel(tfd, s['target'])
                    log.write(action='remove', step=s['n'], path=s['target'])
    finally:
        os.close(tfd)
        os.close(bfd)
    for note in plan.get('after_rollback', []):
        log.write(action='todo', detail=note)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('command', choices=['preflight', 'apply', 'verify', 'rollback'])
    ap.add_argument('--plan', required=True)
    ap.add_argument('--plan-sha256')
    ap.add_argument('--manifests')
    ap.add_argument('--source')
    ap.add_argument('--target', required=True)
    ap.add_argument('--backup')
    ap.add_argument('--log')
    ap.add_argument('--probe')
    ap.add_argument('--stop-after', type=int)
    ap.add_argument('--state', choices=['release', 'production'], default='release')
    ap.add_argument('--remove-added', action='store_true')
    a = ap.parse_args()
    log = Log(a.log)
    try:
        plan = load_plan(a.plan, a.plan_sha256)
        if a.command in ('preflight', 'apply') and not (a.manifests and a.source):
            raise Stop('--manifests and --source are required')
        if a.command == 'preflight':
            problems = preflight(plan, a.source, a.target, a.manifests)
            for p in problems:
                print('FAIL ' + p)
            sys.exit(1 if problems else 0)
        if a.command == 'verify':
            bad = verify(plan, a.target, a.state)
            for b in bad:
                print('FAIL ' + b)
            print(f'verify {a.state}: ' + ('FAIL' if bad else 'PASS'))
            sys.exit(1 if bad else 0)
        if not a.backup:
            raise Stop('--backup is required')
        if a.command == 'apply':
            apply(plan, a.source, a.target, a.backup, log, a.manifests, a.probe, a.stop_after)
        else:
            rollback(plan, a.target, a.backup, log, a.probe, a.remove_added)
        log.write(action=a.command, result='done')
    except (Stop, rl.UnsafeInput) as e:
        log.write(action=a.command, result='stopped', detail=str(e))
        sys.exit(2)


if __name__ == '__main__':
    main()
