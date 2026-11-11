#!/usr/bin/env python3
"""Industry Hub production deploy helper (OmochiX Core 1.10.0 / theme 1.29.0).

Self-contained (Python 3.8+ standard library only), so it can be copied to the
server on its own and checked with its SHA-256. It does exactly one thing: put
the files of a release plan made by plan.py into the OmochiX Core plugin and
the OmochiX theme, one file at a time, and put them back.

  deploy.py status    --plan P --plan-sha256 X [--qa ROOT]
  deploy.py preflight --plan P --plan-sha256 X --dist D --expect-wp V [--qa ROOT]
  deploy.py lock      --plan P --plan-sha256 X --confirm IH-LOCK [--qa ROOT]
  deploy.py backup    ... --dist D --expect-wp V --token T --backup B --log L --confirm IH-BACKUP
  deploy.py apply     ... --dist D --expect-wp V --token T --backup B --log L --confirm IH-APPLY [--until A|B|N]
  deploy.py verify    --plan P --plan-sha256 X --want release|production [--qa ROOT]
  deploy.py rollback  ... --expect-wp V --token T --backup B --log L --confirm IH-ROLLBACK [--remove-added]
  deploy.py unlock    --plan P --plan-sha256 X --token T --confirm IH-UNLOCK [--qa ROOT]

Write targets are fixed: <web>/wp-content/plugins/omochix-core and
<web>/wp-content/themes/omochix-theme, where <web> is ~/web. Nothing else can be
named. --qa ROOT replaces <web> with a throwaway copy, which must contain the
marker file wp-content/.omochix-rehearsal; production must not contain it.

Trust: the plan is accepted only when its SHA-256 equals --plan-sha256 (the
value recorded at approval, never read from a file next to the plan). The plan
names the SHA-256 of each zip and manifest; every file written comes out of the
zip (never an unpacked folder), must be in the manifest, and must have the
plan's SHA-256. Paths are relative with no "..", and every folder is opened with
O_NOFOLLOW relative to its parent's descriptor, so a symlink anywhere is refused
rather than followed, even if it appears after the checks. Each file goes to a
new temporary name in its own folder (O_EXCL), is fsynced and re-hashed, the
target is re-checked against its expected SHA-256, then renamed over it.

Writes need the lock (lock creates it and prints a token), the confirmation
phrase, and the WordPress version read from wp-includes/version.php. apply
checks everything first and writes nothing if a check fails; it stops at gate A
(after the Core switch file), at gate B (end), or after step N with --until, and
running it again resumes. rollback first checks the backup and every target,
then restores the replaced files in the plan's rollback order. The audit log
(0600, outside the web root) holds only target-relative paths, SHA-256 values
and results. The tool never touches the database, caches or rewrite rules: the
plan's after_apply / after_rollback steps are printed for the operator.
"""
import argparse
import datetime
import errno
import hashlib
import io
import json
import os
import re
import secrets
import stat
import subprocess
import sys
import zipfile

TOOL = 'omochix-ih-deploy 1'
PACKAGES = {'core': ('omochix-core', 'plugins/omochix-core'), 'theme': ('omochix-theme', 'themes/omochix-theme')}
MARKER = '.omochix-rehearsal'
LOCK_NAME = '.omochix-ih-release.lock'
CONFIRM = {'lock': 'IH-LOCK', 'backup': 'IH-BACKUP', 'apply': 'IH-APPLY', 'rollback': 'IH-ROLLBACK', 'unlock': 'IH-UNLOCK'}
HEX64 = re.compile(r'^[0-9a-f]{64}$')
O_DIR = os.O_RDONLY | getattr(os, 'O_DIRECTORY', 0) | os.O_NOFOLLOW


class Stop(Exception):
    pass


# ------------------------------------------------------------------ inputs

def safe_rel(path):
    if not isinstance(path, str) or not path or '\0' in path or '\\' in path or path.startswith('/') \
            or re.match(r'^[A-Za-z]:', path) or any(p in ('', '.', '..') for p in path.split('/')):
        raise Stop(f'unsafe path {path!r}')
    return path


def sha(data):
    return hashlib.sha256(data).hexdigest()


def read_regular(path, label):
    """Bytes of a regular file opened without following a symlink."""
    try:
        fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    except OSError:
        raise Stop(f'{label}: missing, a symlink or unreadable')
    try:
        if not stat.S_ISREG(os.fstat(fd).st_mode):
            raise Stop(f'{label}: not a regular file')
        return b''.join(iter(lambda: os.read(fd, 1 << 16), b''))
    finally:
        os.close(fd)


def parse_manifest(text):
    out = {}
    for n, line in enumerate(text.split('\n'), 1):
        if not line:
            continue
        digest, sep, rel = line.partition('  ')
        if not sep or not HEX64.match(digest):
            raise Stop(f'manifest line {n} is malformed')
        safe_rel(rel)
        if rel in out:
            raise Stop(f'manifest line {n}: duplicate path')
        out[rel] = digest
    return out


def zip_files(data, top):
    out = {}
    with zipfile.ZipFile(io.BytesIO(data)) as z:
        names = [i.filename for i in z.infolist()]
        if len(names) != len(set(names)):
            raise Stop(f'{top}.zip has duplicate entries')
        for info in z.infolist():
            name = info.filename
            if not name.startswith(top + '/'):
                raise Stop(f'{top}.zip has an entry outside {top}/')
            rel = name[len(top) + 1:]
            mode = info.external_attr >> 16
            if name.endswith('/'):
                if rel:
                    safe_rel(rel.rstrip('/'))
                if info.file_size:
                    raise Stop(f'{top}.zip has a folder entry with content')
                continue
            safe_rel(rel)
            if mode and not stat.S_ISREG(mode):
                raise Stop(f'{top}.zip has a non-regular entry')
            out[rel] = z.read(info)
    return out


def load_plan(path, want_sha):
    if not want_sha or not HEX64.match(want_sha):
        raise Stop('--plan-sha256 must be the 64-hex SHA-256 recorded at approval')
    data = read_regular(path, 'plan')
    if sha(data) != want_sha:
        raise Stop('the plan does not match --plan-sha256')
    plan = json.loads(data.decode('utf-8'))
    if set(plan.get('packages', {})) != set(PACKAGES):
        raise Stop('the plan must name exactly the core and theme packages')
    for k, (name, target) in PACKAGES.items():
        p = plan['packages'][k]
        if p.get('name') != name or p.get('target') != target:
            raise Stop(f'package {k} is not {name} -> {target}')
        for f in ('manifest', 'zip'):
            if '/' in p.get(f, '/') or not p[f].startswith(name + '-'):
                raise Stop(f'package {k}: bad {f} name')
        for f in ('manifest_sha256', 'zip_sha256'):
            if not HEX64.match(p.get(f) or ''):
                raise Stop(f'package {k}: bad {f}')
    seen = set()
    for i, s in enumerate(plan.get('steps', []), 1):
        if s.get('n') != i or s.get('package') not in PACKAGES or s.get('action') not in ('add', 'replace'):
            raise Stop(f'step {i} is malformed')
        safe_rel(s['path'])
        if s['target'] != f'{PACKAGES[s["package"]][1]}/{s["path"]}' or s['target'] in seen:
            raise Stop(f'step {i}: target is not its package target + path, or repeated')
        seen.add(s['target'])
        if not HEX64.match(s.get('new_sha256') or '') or (s['action'] == 'replace') != bool(HEX64.match(s.get('old_sha256') or '')):
            raise Stop(f'step {i}: bad SHA-256 values')
    if not seen:
        raise Stop('the plan has no steps')
    by = {(s['package'], s['path']): s for s in plan['steps']}
    order = plan.get('rollback_order', [])
    if sorted(map(tuple, order)) != sorted(k for k, s in by.items() if s['action'] == 'replace'):
        raise Stop('rollback_order must list exactly the replaced files')
    return plan


def load_dist(plan, dist):
    """{target: bytes} for every step, from zips and manifests that match the plan."""
    out = {}
    for k, p in plan['packages'].items():
        mdata = read_regular(os.path.join(dist, p['manifest']), p['manifest'])
        if sha(mdata) != p['manifest_sha256']:
            raise Stop(f'{p["manifest"]} does not match the plan')
        zdata = read_regular(os.path.join(dist, p['zip']), p['zip'])
        if sha(zdata) != p['zip_sha256']:
            raise Stop(f'{p["zip"]} does not match the plan')
        manifest = parse_manifest(mdata.decode('utf-8'))
        files = zip_files(zdata, p['name'])
        if set(files) != set(manifest) or any(sha(b) != manifest[r] for r, b in files.items()):
            raise Stop(f'{p["zip"]} and {p["manifest"]} differ')
        for s in plan['steps']:
            if s['package'] == k:
                if manifest.get(s['path']) != s['new_sha256']:
                    raise Stop(f'step {s["n"]}: not the file the manifest lists')
                out[s['target']] = files[s['path']]
    return out


# ------------------------------------------------------------------ the web root

class Site:
    def __init__(self, qa=None):
        web = os.path.realpath(qa) if qa else os.path.realpath(os.path.expanduser('~/web'))
        self.web, self.qa = web, bool(qa)
        self.web_fd = self._open_dir_path(web, 'web root')
        try:
            self.wpc = os.open('wp-content', O_DIR, dir_fd=self.web_fd)
        except OSError:
            raise Stop('wp-content is missing or a symlink')
        has_marker = self.exists(MARKER)
        if qa and not has_marker:
            raise Stop(f'--qa root has no wp-content/{MARKER}')
        if not qa and has_marker:
            raise Stop(f'the production root contains {MARKER}; use --qa for a copy')
        self.lock_dir = os.path.dirname(web)

    @staticmethod
    def _open_dir_path(path, label):
        try:
            fd = os.open(path, O_DIR)
        except OSError:
            raise Stop(f'{label} cannot be opened (missing or a symlink)')
        return fd

    def close(self):
        for fd in (self.wpc, self.web_fd):
            os.close(fd)

    def wp_version(self):
        try:
            fd = os.open('wp-includes', O_DIR, dir_fd=self.web_fd)
            try:
                f = os.open('version.php', os.O_RDONLY | os.O_NOFOLLOW, dir_fd=fd)
                text = os.read(f, 1 << 16).decode('utf-8', 'replace')
                os.close(f)
            finally:
                os.close(fd)
        except OSError:
            raise Stop('wp-includes/version.php cannot be read')
        m = re.search(r"\$wp_version\s*=\s*'([^']+)'", text)
        return m.group(1) if m else ''

    def inside_web(self, path):
        real = os.path.realpath(path)
        return real == self.web or real.startswith(self.web + os.sep)

    # --- descriptor walk under wp-content
    def parent(self, rel, create=False, log=None, step=None):
        parts = safe_rel(rel).split('/')
        fd = os.dup(self.wpc)
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
                    nfd = os.open(part, O_DIR, dir_fd=fd)
                    os.fchmod(nfd, 0o755)
                    os.fsync(fd)
                    if log:
                        log.write(action='mkdir', step=step, path='/'.join(walked))
                except OSError:
                    raise Stop(f'{"/".join(walked)}: a symlink or not a folder')
                os.close(fd)
                fd = nfd
            return fd, parts[-1]
        except BaseException:
            os.close(fd)
            raise

    def exists(self, rel):
        fd, name = self.parent(rel)
        if fd is None:
            return False
        try:
            os.stat(name, dir_fd=fd, follow_symlinks=False)
            return True
        except FileNotFoundError:
            return False
        finally:
            os.close(fd)

    def read(self, rel):
        fd, name = self.parent(rel)
        if fd is None:
            return None, None
        try:
            try:
                f = os.open(name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=fd)
            except FileNotFoundError:
                return None, None
            except OSError:
                raise Stop(f'{rel}: a symlink or not a regular file')
            try:
                st = os.fstat(f)
                if not stat.S_ISREG(st.st_mode):
                    raise Stop(f'{rel}: not a regular file')
                return b''.join(iter(lambda: os.read(f, 1 << 16), b'')), stat.S_IMODE(st.st_mode)
            finally:
                os.close(f)
        finally:
            os.close(fd)

    def hash(self, rel):
        data, _ = self.read(rel)
        return None if data is None else sha(data)

    def swap(self, rel, data, expect_new, expect_before, log, step):
        """Temporary name -> fsync -> re-hash -> target re-checked -> rename -> verify."""
        if sha(data) != expect_new:
            raise Stop(f'step {step}: the data for {rel} is not the planned file')
        fd, name = self.parent(rel, create=True, log=log, step=step)
        tmp = f'.{name}.ih-new'
        try:
            try:
                os.stat(tmp, dir_fd=fd, follow_symlinks=False)
                raise Stop(f'{rel}: a temporary file is left over; nothing changed for this file')
            except FileNotFoundError:
                pass
            mode = 0o644
            try:
                st = os.stat(name, dir_fd=fd, follow_symlinks=False)
                if not stat.S_ISREG(st.st_mode):
                    raise Stop(f'{rel}: exists and is not a regular file')
                mode = stat.S_IMODE(st.st_mode) & 0o755
            except FileNotFoundError:
                pass
            f = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600, dir_fd=fd)
            try:
                view = memoryview(data)
                while view:
                    view = view[os.write(f, view):]
                os.fchmod(f, mode)
                os.fsync(f)
            finally:
                os.close(f)
            f = os.open(tmp, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=fd)
            try:
                ok = sha(b''.join(iter(lambda: os.read(f, 1 << 16), b''))) == expect_new
            finally:
                os.close(f)
            if not ok:
                os.unlink(tmp, dir_fd=fd)
                raise Stop(f'step {step}: the temporary copy of {rel} does not verify; target untouched')
            now = self._hash_at(fd, name)
            if now != expect_before:
                os.unlink(tmp, dir_fd=fd)
                raise Stop(f'step {step}: {rel} changed since the checks; target untouched')
            os.rename(tmp, name, src_dir_fd=fd, dst_dir_fd=fd)
            os.fsync(fd)
            if self._hash_at(fd, name) != expect_new:
                raise Stop(f'step {step}: {rel} does not verify after the rename; investigate, then roll back')
        finally:
            os.close(fd)

    def _hash_at(self, fd, name):
        try:
            f = os.open(name, os.O_RDONLY | os.O_NOFOLLOW, dir_fd=fd)
        except FileNotFoundError:
            return None
        except OSError:
            raise Stop(f'{name}: a symlink or not a regular file')
        try:
            if not stat.S_ISREG(os.fstat(f).st_mode):
                raise Stop(f'{name}: not a regular file')
            return sha(b''.join(iter(lambda: os.read(f, 1 << 16), b'')))
        finally:
            os.close(f)

    def remove(self, rel, expect):
        fd, name = self.parent(rel)
        if fd is None:
            return
        try:
            if self._hash_at(fd, name) == expect:
                os.unlink(name, dir_fd=fd)
                os.fsync(fd)
        finally:
            os.close(fd)


# ------------------------------------------------------------------ backup folder

class Backup:
    def __init__(self, site, path, create=False):
        if not path:
            raise Stop('--backup is required')
        path = os.path.abspath(os.path.expanduser(path))
        if site.inside_web(path) or site.inside_web(os.path.dirname(path)):
            raise Stop('the backup must be outside the web root')
        if create:
            try:
                os.mkdir(path, 0o700)
            except FileExistsError:
                raise Stop('the backup folder already exists; use a new name')
        try:
            self.fd = os.open(path, O_DIR)
        except OSError:
            raise Stop('the backup folder is missing or a symlink')

    def close(self):
        os.close(self.fd)

    def _key(self, rel):
        return safe_rel(rel).replace('/', '__')

    def put(self, rel, data, mode):
        name = self._key(rel)
        f = os.open(name, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600, dir_fd=self.fd)
        try:
            view = memoryview(data)
            while view:
                view = view[os.write(f, view):]
            os.fsync(f)
        finally:
            os.close(f)
        return f'{sha(data)}  {oct(mode)}  {rel}\n'

    def get(self, rel):
        try:
            f = os.open(self._key(rel), os.O_RDONLY | os.O_NOFOLLOW, dir_fd=self.fd)
        except OSError:
            return None
        try:
            if not stat.S_ISREG(os.fstat(f).st_mode):
                return None
            return b''.join(iter(lambda: os.read(f, 1 << 16), b''))
        finally:
            os.close(f)

    def check(self, plan):
        bad = []
        for s in plan['steps']:
            if s['action'] == 'replace':
                data = self.get(s['target'])
                if data is None or sha(data) != s['old_sha256']:
                    bad.append(s['target'])
        return bad


# ------------------------------------------------------------------ lock and log

def lock_path(site):
    return os.path.join(site.lock_dir, LOCK_NAME)


def read_lock(site):
    try:
        return read_regular(lock_path(site), 'lock').decode('ascii').strip()
    except Stop:
        return None if not os.path.lexists(lock_path(site)) else 'damaged'


def need_lock(site, token):
    have = read_lock(site)
    if have is None:
        raise Stop('no release lock: run "lock" first')
    if not token or have != token:
        raise Stop('the lock belongs to another run (token differs); nothing written')


class Log:
    def __init__(self, site, path, quiet=False):
        self.quiet = quiet
        self.fd = None
        if path:
            path = os.path.abspath(os.path.expanduser(path))
            if site.inside_web(path) or site.inside_web(os.path.dirname(path)):
                raise Stop('the log must be outside the web root')
            self.fd = os.open(path, os.O_WRONLY | os.O_APPEND | os.O_CREAT | os.O_NOFOLLOW, 0o600)

    def write(self, **entry):
        line = json.dumps({'at': datetime.datetime.now(datetime.timezone.utc).isoformat(timespec='seconds'), 'tool': TOOL, **entry},
                          ensure_ascii=False)
        if self.fd is not None:
            os.write(self.fd, (line + '\n').encode('utf-8'))
        if not self.quiet:
            print(line)

    def close(self):
        if self.fd is not None:
            os.close(self.fd)


# ------------------------------------------------------------------ commands

def states(site, plan):
    out = {}
    for s in plan['steps']:
        h = site.hash(s['target'])
        out[s['n']] = 'release' if h == s['new_sha256'] else 'production' if h == s['old_sha256'] else 'other'
    return out


def preflight(site, plan, files, expect_wp):
    problems = []
    if site.wp_version() != expect_wp:
        problems.append('WordPress version differs from --expect-wp')
    for s in plan['steps']:
        try:
            h = site.hash(s['target'])
        except Stop as e:
            problems.append(f'step {s["n"]}: {e}')
            continue
        if sha(files[s['target']]) != s['new_sha256']:
            problems.append(f'step {s["n"]}: release file differs')
        if h not in (s['old_sha256'], s['new_sha256']):
            problems.append(f'step {s["n"]} {s["target"]}: on the server {(h or "missing")[:12]}, expected '
                            f'{(s["old_sha256"] or "absent")[:12]}')
    return problems


def gate_index(plan, until):
    if until in (None, 'B'):
        return len(plan['steps'])
    if until == 'A':
        pkg, path = plan['gates']['A']['after']
        return next(s['n'] for s in plan['steps'] if s['package'] == pkg and s['path'] == path)
    if until.isdigit() and 1 <= int(until) <= len(plan['steps']):
        return int(until)
    raise Stop('--until must be A, B or a step number')


def run_probe(cmd, log, label):
    r = subprocess.run(cmd, shell=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    log.write(action='probe', after=label, result='ok' if r.returncode == 0 else 'failed', rc=r.returncode)
    if r.returncode:
        raise Stop(f'probe failed after {label}')


def cmd_backup(site, plan, files, a, log):
    problems = preflight(site, plan, files, a.expect_wp)
    if problems:
        raise Stop('preflight failed: ' + '; '.join(problems))
    b = Backup(site, a.backup, create=True)
    try:
        lines = []
        for s in plan['steps']:
            if s['action'] != 'replace':
                continue
            data, mode = site.read(s['target'])
            if data is None or sha(data) != s['old_sha256']:
                raise Stop(f'{s["target"]} is not the production file; take the backup before any change')
            lines.append(b.put(s['target'], data, mode))
            log.write(action='backup', step=s['n'], path=s['target'], sha256=s['old_sha256'])
        f = os.open('MANIFEST.sha256', os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600, dir_fd=b.fd)
        os.write(f, ''.join(lines).encode('utf-8'))
        os.fsync(f)
        os.close(f)
        bad = b.check(plan)
        if bad:
            raise Stop('backup does not verify: ' + ', '.join(bad))
        log.write(action='backup', result='verified', files=len(lines))
    finally:
        b.close()


def cmd_apply(site, plan, files, a, log):
    problems = preflight(site, plan, files, a.expect_wp)
    if problems:
        for p in problems:
            log.write(action='preflight', result='fail', detail=p)
        raise Stop('preflight failed: nothing was written')
    b = Backup(site, a.backup)
    try:
        bad = b.check(plan)
        if bad:
            raise Stop('backup missing or wrong for: ' + ', '.join(bad) + '; nothing was written')
    finally:
        b.close()
    log.write(action='preflight', result='pass', steps=len(plan['steps']))
    last = gate_index(plan, a.until)
    for s in plan['steps']:
        if s['n'] > last:
            break
        before = site.hash(s['target'])
        if before == s['new_sha256']:
            log.write(action='skip', step=s['n'], path=s['target'], reason='already the release file')
            continue
        site.swap(s['target'], files[s['target']], s['new_sha256'], s['old_sha256'], log, s['n'])
        log.write(action=s['action'], step=s['n'], path=s['target'], sha256=s['new_sha256'])
        if a.probe:
            run_probe(a.probe, log, f'step {s["n"]}')
    for g, spec in plan.get('gates', {}).items():
        n = gate_index(plan, g)
        if n <= last and all(v == 'release' for k, v in states(site, plan).items() if k <= n):
            log.write(action='gate', gate=g, detail=spec['check'])
    if last < len(plan['steps']):
        log.write(action='paused', after_step=last, detail='check the gate, then run apply again to continue')
    else:
        for note in plan.get('after_apply', []):
            log.write(action='todo', detail=note)


def cmd_rollback(site, plan, a, log):
    if site.wp_version() != a.expect_wp:
        raise Stop('WordPress version differs from --expect-wp')
    by = {(s['package'], s['path']): s for s in plan['steps']}
    order = [by[tuple(x)] for x in plan['rollback_order']]
    b = Backup(site, a.backup)
    try:
        bad = b.check(plan)
        if bad:
            raise Stop('backup missing or wrong for: ' + ', '.join(bad) + '; nothing was restored')
        for s in plan['steps']:
            h = site.hash(s['target'])
            if h not in (s['old_sha256'], s['new_sha256']):
                raise Stop(f'{s["target"]} is neither the release nor the production file; nothing was restored')
        for s in order:
            if site.hash(s['target']) == s['old_sha256']:
                log.write(action='skip', path=s['target'], reason='already the production file')
                continue
            site.swap(s['target'], b.get(s['target']), s['old_sha256'], s['new_sha256'], log, s['n'])
            log.write(action='restore', step=s['n'], path=s['target'], sha256=s['old_sha256'])
            if a.probe:
                run_probe(a.probe, log, f'restore {s["path"]}')
    finally:
        b.close()
    if a.remove_added:
        for s in reversed(plan['steps']):
            if s['action'] == 'add' and site.hash(s['target']) == s['new_sha256']:
                site.remove(s['target'], s['new_sha256'])
                log.write(action='remove', step=s['n'], path=s['target'])
    for note in plan.get('after_rollback', []):
        log.write(action='todo', detail=note)


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('command', choices=['status', 'preflight', 'lock', 'backup', 'apply', 'verify', 'rollback', 'unlock'])
    ap.add_argument('--plan', required=True)
    ap.add_argument('--plan-sha256', required=True)
    ap.add_argument('--dist')
    ap.add_argument('--qa')
    ap.add_argument('--expect-wp')
    ap.add_argument('--token')
    ap.add_argument('--confirm')
    ap.add_argument('--backup')
    ap.add_argument('--log')
    ap.add_argument('--until')
    ap.add_argument('--want', choices=['release', 'production'])
    ap.add_argument('--remove-added', action='store_true')
    ap.add_argument('--probe', help='--qa only: command run after every write (only its exit status is logged)')
    a = ap.parse_args(argv)
    site = log = None
    try:
        plan = load_plan(a.plan, a.plan_sha256)
        site = Site(a.qa)
        if a.probe and not a.qa:
            raise Stop('--probe is for --qa copies only')
        if a.command in CONFIRM and a.confirm != CONFIRM[a.command]:
            raise Stop(f'{a.command} needs --confirm {CONFIRM[a.command]}')
        if a.command in ('backup', 'apply', 'rollback'):
            if not a.expect_wp or not a.log:
                raise Stop('--expect-wp and --log are required')
            need_lock(site, a.token)
        log = Log(site, a.log)
        if a.command == 'status':
            st = states(site, plan)
            for s in plan['steps']:
                print(f'{s["n"]:2} {st[s["n"]]:10} {s["target"]}')
            print(f'lock: {"held" if read_lock(site) else "none"}')
            return 0
        if a.command == 'verify':
            if not a.want:
                raise Stop('--want release|production is required')
            st = states(site, plan)
            bad = [s for s in plan['steps'] if st[s['n']] != a.want and not (a.want == 'production' and s['action'] == 'add')]
            for s in bad:
                print(f'FAIL {s["target"]}: {st[s["n"]]}')
            print(f'verify {a.want}: ' + ('FAIL' if bad else 'PASS'))
            return 1 if bad else 0
        if a.command == 'lock':
            token = secrets.token_hex(16)
            try:
                f = os.open(lock_path(site), os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
            except FileExistsError:
                raise Stop('a release lock already exists')
            os.write(f, token.encode('ascii'))
            os.close(f)
            print(f'lock: created; token {token}')
            return 0
        if a.command == 'unlock':
            need_lock(site, a.token)
            st = states(site, plan)
            replaced = {st[s['n']] for s in plan['steps'] if s['action'] == 'replace'}
            if replaced not in ({'release'}, {'production'}) or (replaced == {'release'} and set(st.values()) != {'release'}):
                raise Stop('the files are in a mixed state; finish apply or rollback first')
            os.unlink(lock_path(site))
            print('lock: removed')
            return 0
        if not a.dist and a.command in ('preflight', 'backup', 'apply'):
            raise Stop('--dist is required')
        if a.command == 'preflight':
            if not a.expect_wp:
                raise Stop('--expect-wp is required')
            files = load_dist(plan, a.dist)
            problems = preflight(site, plan, files, a.expect_wp)
            for p in problems:
                print('FAIL ' + p)
            print(f'lock: {"held" if read_lock(site) else "none"}')
            print('preflight: ' + ('FAIL' if problems else 'PASS'))
            return 1 if problems else 0
        if a.command == 'backup':
            cmd_backup(site, plan, load_dist(plan, a.dist), a, log)
        elif a.command == 'apply':
            cmd_apply(site, plan, load_dist(plan, a.dist), a, log)
        else:
            cmd_rollback(site, plan, a, log)
        log.write(action=a.command, result='done')
        return 0
    except Stop as e:
        msg = str(e)
        if site is not None:
            msg = msg.replace(site.web, '<web>')
        msg = msg.replace(os.path.expanduser('~'), '~')
        if log is not None:
            log.write(action=a.command, result='stopped', detail=msg)
        print('STOP: ' + msg)
        return 2
    finally:
        if log is not None:
            log.close()
        if site is not None:
            site.close()


if __name__ == '__main__':
    sys.exit(main())
