#!/usr/bin/env python3
"""Make the deploy plan: the release commit against the production baseline.

  python3 tools/release/plan.py --commit <release commit> --out <dir> [--baseline-dir <dir>]

Refuses unless the files that differ from the production baseline
(tools/release/baseline/*.sha256) are exactly the approved order in release.json:
no deletion, no file outside it, the switch files last in their package.
Writes:
  release-plan.json                 ordered steps (add / replace, old and new SHA-256), gates, rollback order
  <name>-<version>.changes.txt      the files each package adds or changes
  <name>-<version>.rollback.manifest.sha256   production SHA-256 of every file a rollback restores
With --baseline-dir (production files: <dir>/wp-plugin/omochix-core, <dir>/wp-theme/omochix-theme),
also copies those rollback files to <out>/rollback/<target>/..., after checking their SHA-256.
"""
import argparse
import json
import os
import shutil
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import releaselib as rl  # noqa: E402


def make_plan(commit):
    cfg = rl.load_config()
    sha, _ = rl.commit_info(commit)
    problems, steps, packages = [], [], {}
    order = [tuple(x) for x in cfg['order']]
    for key, pkg in cfg['packages'].items():
        files = rl.tree_files(sha, pkg['dir'])
        base = rl.read_manifest(os.path.join(rl.ROOT, pkg['baseline']))
        new = {p: rl.sha256_bytes(d) for p, d in files.items()}
        changed = sorted(p for p in new if base.get(p) != new[p])
        deleted = sorted(p for p in base if p not in new)
        planned = [p for k, p in order if k == key]
        problems += [f'{pkg["name"]}: {p} would be deleted (the release deletes nothing)' for p in deleted]
        problems += [f'{pkg["name"]}: {p} differs from production but is not in the approved order' for p in changed if p not in planned]
        problems += [f'{pkg["name"]}: {p} is in the order but does not change' for p in planned if p not in changed]
        packages[key] = {'name': pkg['name'], 'version': rl.package_version(key, files), 'baseline_version': pkg['baseline_version'],
                         'target': pkg['target'], 'files': files, 'base': base, 'new': new}
    for k, p in cfg['switches']:
        last = [q for kk, q in order if kk == k][-2:]
        if p not in last:
            problems.append(f'switch file {k}:{p} must come at the end of its package')
    for i, (key, rel) in enumerate(order, 1):
        pk = packages[key]
        steps.append({'n': i, 'package': key, 'path': rel, 'target': f'{pk["target"]}/{rel}',
                      'action': 'replace' if rel in pk['base'] else 'add',
                      'old_sha256': pk['base'].get(rel), 'new_sha256': pk['new'].get(rel), 'size': len(pk['files'].get(rel, b''))})
    plan = {'release': cfg['release'], 'commit': sha, 'note': cfg['note'],
            'packages': {k: {'name': v['name'], 'version': v['version'], 'from': v['baseline_version'], 'target': v['target']} for k, v in packages.items()},
            'steps': steps, 'switches': cfg['switches'], 'gates': cfg['gates'],
            'rollback_order': cfg['rollback_order'], 'after_apply': cfg['after_apply']}
    return plan, packages, problems


def write(plan, packages, out, baseline_dir=None):
    cfg = rl.load_config()
    os.makedirs(out, exist_ok=True)
    with open(os.path.join(out, 'release-plan.json'), 'w', encoding='utf-8') as fh:
        json.dump(plan, fh, ensure_ascii=False, indent=1)
    for key, pk in packages.items():
        base = f'{pk["name"]}-{pk["version"]}'
        mine = [s for s in plan['steps'] if s['package'] == key]
        with open(os.path.join(out, base + '.changes.txt'), 'w', encoding='utf-8') as fh:
            fh.write(f'{pk["name"]} {pk["baseline_version"]} -> {pk["version"]} ({plan["commit"]})\n')
            fh.writelines(f'{s["action"]:7} {s["path"]}\n' for s in mine)
        with open(os.path.join(out, base + '.rollback.manifest.sha256'), 'w', encoding='utf-8') as fh:
            fh.writelines(f'{s["old_sha256"]}  {s["path"]}\n' for s in mine if s['action'] == 'replace')
        if baseline_dir:
            src_root = os.path.join(baseline_dir, cfg['packages'][key]['dir'])
            for s in mine:
                if s['action'] != 'replace':
                    continue
                src = os.path.join(src_root, s['path'])
                if rl.sha256_file(src) != s['old_sha256']:
                    raise SystemExit(f'rollback source {src} does not match the production SHA-256')
                dst = os.path.join(out, 'rollback', pk['target'], s['path'])
                os.makedirs(os.path.dirname(dst), exist_ok=True)
                shutil.copyfile(src, dst)


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--commit', default='HEAD')
    ap.add_argument('--out')
    ap.add_argument('--baseline-dir')
    ap.add_argument('--check', action='store_true', help='only check the scope, write nothing')
    a = ap.parse_args()
    plan, packages, problems = make_plan(a.commit)
    for p in problems:
        print('FAIL ' + p)
    if problems:
        sys.exit(1)
    adds = sum(1 for s in plan['steps'] if s['action'] == 'add')
    print(f'plan: {len(plan["steps"])} steps ({adds} add, {len(plan["steps"]) - adds} replace) at {plan["commit"][:7]}')
    if not a.check:
        if not a.out:
            raise SystemExit('--out is required unless --check')
        write(plan, packages, a.out, a.baseline_dir)


if __name__ == '__main__':
    main()
