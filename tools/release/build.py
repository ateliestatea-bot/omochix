#!/usr/bin/env python3
"""Build the release packages from a git commit.

  python3 tools/release/build.py --commit <release commit> --out <dir>

Writes, per package (omochix-core, omochix-theme):
  <name>-<version>.zip               reproducible: same commit -> same bytes
  <name>-<version>.zip.sha256        sha256sum format
  <name>-<version>.manifest.sha256   every file, paths relative to the package root
                                     (on a server: cd <package dir> && sha256sum -c <manifest>)
Refuses a "-dev" version unless --allow-dev (dry runs only).
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import releaselib as rl  # noqa: E402


def build(commit, out, allow_dev=False):
    cfg = rl.load_config()
    sha, ts = rl.commit_info(commit)
    os.makedirs(out, exist_ok=True)
    result = {'commit': sha, 'time': ts, 'packages': {}}
    for key, pkg in cfg['packages'].items():
        files = rl.tree_files(sha, pkg['dir'])
        version = rl.package_version(key, files)
        if not version or ('-dev' in version and not allow_dev):
            raise SystemExit(f'{pkg["name"]}: version "{version}" is not a release version')
        base = f'{pkg["name"]}-{version}'
        data = rl.build_zip(files, pkg['name'], ts)
        with open(os.path.join(out, base + '.zip'), 'wb') as fh:
            fh.write(data)
        with open(os.path.join(out, base + '.zip.sha256'), 'w', encoding='utf-8') as fh:
            fh.write(f'{rl.sha256_bytes(data)}  {base}.zip\n')
        with open(os.path.join(out, base + '.manifest.sha256'), 'w', encoding='utf-8') as fh:
            fh.write(rl.manifest_lines(files))
        result['packages'][key] = {'zip': base + '.zip', 'sha256': rl.sha256_bytes(data), 'files': len(files), 'version': version}
    return result


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--commit', default='HEAD')
    ap.add_argument('--out', required=True)
    ap.add_argument('--allow-dev', action='store_true')
    a = ap.parse_args()
    r = build(a.commit, a.out, a.allow_dev)
    print(f'commit {r["commit"]}')
    for p in r['packages'].values():
        print(f'{p["sha256"]}  {p["zip"]}  ({p["files"]} files, {p["version"]})')


if __name__ == '__main__':
    main()
