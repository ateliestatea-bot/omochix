#!/usr/bin/env python3
"""Rehearse a staged deploy and its rollback on a THROWAWAY copy of wp-content.

Production is deployed by the operator's own replace tool (v2) with the same
release-plan.json; this script reproduces that procedure so it can be tested.
It refuses any target that does not contain the marker file `.omochix-rehearsal`.

  python3 tools/release/rehearse.py preflight --plan P --source S --target T
  python3 tools/release/rehearse.py apply     --plan P --source S --target T --backup B --log L [--probe CMD] [--stop-after N]
  python3 tools/release/rehearse.py verify    --plan P --target T --state release|production
  python3 tools/release/rehearse.py rollback  --plan P --target T --backup B --log L [--probe CMD] [--remove-added]

S = the unpacked release (S/omochix-core/..., S/omochix-theme/...); T = wp-content (T/plugins/..., T/themes/...).
Every step writes a temporary file in the target folder, checks its SHA-256 and
renames it into place, so a reader never sees a half-written file. apply checks
everything first and writes nothing if any check fails; a step already at the
release SHA-256 is skipped, so a run that stopped can simply be run again.
Every action goes to the JSON-lines audit log.
"""
import argparse
import datetime
import json
import os
import shutil
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import releaselib as rl  # noqa: E402

MARKER = '.omochix-rehearsal'


class Stop(Exception):
    pass


def now():
    return datetime.datetime.now(datetime.timezone.utc).isoformat(timespec='seconds')


class Log:
    def __init__(self, path, quiet=False):
        self.path = path
        self.quiet = quiet

    def write(self, **entry):
        entry = {'at': now(), **entry}
        if self.path:
            with open(self.path, 'a', encoding='utf-8') as fh:
                fh.write(json.dumps(entry, ensure_ascii=False) + '\n')
        if not self.quiet:
            print(json.dumps(entry, ensure_ascii=False))


def file_sha(path):
    return rl.sha256_file(path) if os.path.isfile(path) else None


def guard_target(target):
    if not os.path.isfile(os.path.join(target, MARKER)):
        raise Stop(f'{target} has no {MARKER}: this tool only runs on a throwaway copy')


def source_path(plan, source, step):
    return os.path.join(source, plan['packages'][step['package']]['name'], step['path'])


def preflight(plan, source, target):
    """Every problem that would make apply unsafe, without writing anything."""
    guard_target(target)
    problems = []
    for s in plan['steps']:
        if source is not None:
            src = source_path(plan, source, s)
            if file_sha(src) != s['new_sha256']:
                problems.append(f'step {s["n"]} {s["target"]}: release file missing or not the planned SHA-256')
        have = file_sha(os.path.join(target, s['target']))
        if s['action'] == 'replace' and have not in (s['old_sha256'], s['new_sha256']):
            problems.append(f'step {s["n"]} {s["target"]}: production file is not the expected one ({(have or "missing")[:12]})')
        if s['action'] == 'add' and have not in (None, s['new_sha256']):
            problems.append(f'step {s["n"]} {s["target"]}: a different file already exists')
    return problems


def put(src_bytes, dst, sha, log, n):
    folder = os.path.dirname(dst)
    if not os.path.isdir(folder):
        os.makedirs(folder, exist_ok=True)
        log.write(action='mkdir', step=n, path=folder)
    tmp = os.path.join(folder, f'.{os.path.basename(dst)}.tmp-release')
    with open(tmp, 'wb') as fh:
        fh.write(src_bytes)
    if rl.sha256_file(tmp) != sha:
        os.unlink(tmp)
        raise Stop(f'step {n}: temporary copy of {dst} does not match')
    os.replace(tmp, dst)
    if rl.sha256_file(dst) != sha:
        raise Stop(f'step {n}: {dst} does not match after the rename')


def run_probe(cmd, log, label):
    if not cmd:
        return
    r = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    log.write(action='probe', after=label, rc=r.returncode, out=r.stdout.strip()[-300:])
    if r.returncode != 0:
        raise Stop(f'probe failed after {label}')


def apply(plan, source, target, backup, log, probe=None, stop_after=None):
    problems = preflight(plan, source, target)
    if problems:
        for p in problems:
            log.write(action='preflight', result='fail', detail=p)
        raise Stop('preflight failed: nothing was written')
    log.write(action='preflight', result='pass', steps=len(plan['steps']))
    os.makedirs(backup, exist_ok=True)
    for s in plan['steps']:
        if s['action'] != 'replace':
            continue
        dst, bak = os.path.join(target, s['target']), os.path.join(backup, s['target'])
        if file_sha(bak) == s['old_sha256']:
            continue
        if file_sha(dst) != s['old_sha256']:
            raise Stop(f'no backup of {s["target"]} and the target is no longer the production file')
        os.makedirs(os.path.dirname(bak), exist_ok=True)
        shutil.copyfile(dst, bak)
        log.write(action='backup', step=s['n'], path=s['target'], sha256=s['old_sha256'])
    for s in plan['steps']:
        dst = os.path.join(target, s['target'])
        if file_sha(dst) == s['new_sha256']:
            log.write(action='skip', step=s['n'], path=s['target'], reason='already the release file')
        else:
            with open(source_path(plan, source, s), 'rb') as fh:
                put(fh.read(), dst, s['new_sha256'], log, s['n'])
            log.write(action=s['action'], step=s['n'], path=s['target'], sha256=s['new_sha256'])
            run_probe(probe, log, f'step {s["n"]}')
        if stop_after and s['n'] >= stop_after:
            raise Stop(f'stopped after step {s["n"]} (--stop-after)')
    for note in plan['after_apply']:
        log.write(action='todo', detail=note)


def verify(plan, target, state):
    guard_target(target)
    key = 'new_sha256' if state == 'release' else 'old_sha256'
    bad = []
    for s in plan['steps']:
        have = file_sha(os.path.join(target, s['target']))
        want = s[key]
        if state == 'production' and s['action'] == 'add':
            continue
        if have != want:
            bad.append(f'{s["target"]}: {(have or "missing")[:12]} != {(want or "absent")[:12]}')
    return bad


def rollback(plan, target, backup, log, probe=None, remove_added=False):
    guard_target(target)
    steps = {(s['package'], s['path']): s for s in plan['steps']}
    order = [steps[tuple(x)] for x in plan['rollback_order']]
    for s in order:
        if file_sha(os.path.join(backup, s['target'])) != s['old_sha256']:
            raise Stop(f'backup of {s["target"]} is missing or wrong: nothing was restored')
        if file_sha(os.path.join(target, s['target'])) not in (s['old_sha256'], s['new_sha256']):
            raise Stop(f'{s["target"]} is neither the release nor the production file: nothing was restored')
    for s in order:
        dst = os.path.join(target, s['target'])
        if file_sha(dst) == s['old_sha256']:
            log.write(action='skip', path=s['target'], reason='already the production file')
            continue
        with open(os.path.join(backup, s['target']), 'rb') as fh:
            put(fh.read(), dst, s['old_sha256'], log, s['n'])
        log.write(action='restore', step=s['n'], path=s['target'], sha256=s['old_sha256'])
        run_probe(probe, log, f'restore {s["path"]}')
    if remove_added:
        for s in reversed(plan['steps']):
            dst = os.path.join(target, s['target'])
            if s['action'] == 'add' and file_sha(dst) == s['new_sha256']:
                os.unlink(dst)
                log.write(action='remove', step=s['n'], path=s['target'])
    for note in plan.get('after_rollback', []):
        log.write(action='todo', detail=note)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('command', choices=['preflight', 'apply', 'verify', 'rollback'])
    ap.add_argument('--plan', required=True)
    ap.add_argument('--source')
    ap.add_argument('--target', required=True)
    ap.add_argument('--backup')
    ap.add_argument('--log')
    ap.add_argument('--probe')
    ap.add_argument('--stop-after', type=int)
    ap.add_argument('--state', choices=['release', 'production'], default='release')
    ap.add_argument('--remove-added', action='store_true')
    a = ap.parse_args()
    with open(a.plan, encoding='utf-8') as fh:
        plan = json.load(fh)
    log = Log(a.log)
    try:
        if a.command == 'preflight':
            problems = preflight(plan, a.source, a.target)
            for p in problems:
                print('FAIL ' + p)
            sys.exit(1 if problems else 0)
        if a.command == 'verify':
            bad = verify(plan, a.target, a.state)
            for b in bad:
                print('FAIL ' + b)
            print(f'verify {a.state}: ' + ('FAIL' if bad else 'PASS'))
            sys.exit(1 if bad else 0)
        if a.command == 'apply':
            apply(plan, a.source, a.target, a.backup, log, a.probe, a.stop_after)
        else:
            rollback(plan, a.target, a.backup, log, a.probe, a.remove_added)
        log.write(action=a.command, result='done')
    except Stop as e:
        log.write(action=a.command, result='stopped', detail=str(e))
        sys.exit(2)


if __name__ == '__main__':
    main()
