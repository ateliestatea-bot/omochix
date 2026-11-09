#!/usr/bin/env python3
"""Check built packages: zip hash, zip vs manifest, manifest vs the git tree of the commit.

  python3 tools/release/verify.py --commit <release commit> --dir <build dir>

Exit 0 only when, for every package: the .zip.sha256 matches the zip; every zip
entry is in the manifest with the same SHA-256 (nothing missing, nothing extra);
and the manifest lists exactly the files of the package directory at that commit,
byte for byte.
"""
import argparse
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import releaselib as rl  # noqa: E402


def verify(commit, directory):
    cfg = rl.load_config()
    sha, _ = rl.commit_info(commit)
    problems = []
    for key, pkg in cfg['packages'].items():
        tree = rl.tree_files(sha, pkg['dir'])
        base = f'{pkg["name"]}-{rl.package_version(key, tree)}'
        zpath = os.path.join(directory, base + '.zip')
        if not os.path.isfile(zpath):
            problems.append(f'{base}.zip: missing')
            continue
        with open(zpath + '.sha256', encoding='utf-8') as fh:
            want_zip = fh.read().split()[0]
        if rl.sha256_file(zpath) != want_zip:
            problems.append(f'{base}.zip: SHA-256 differs from {base}.zip.sha256')
        manifest = rl.read_manifest(os.path.join(directory, base + '.manifest.sha256'))
        inzip = rl.zip_files(zpath, pkg['name'])
        for rel, data in inzip.items():
            if rel not in manifest:
                problems.append(f'{base}: {rel} is in the zip but not in the manifest')
            elif rl.sha256_bytes(data) != manifest[rel]:
                problems.append(f'{base}: {rel} differs from the manifest')
        problems += [f'{base}: {rel} is in the manifest but not in the zip' for rel in manifest if rel not in inzip]
        for rel, data in tree.items():
            if manifest.get(rel) != rl.sha256_bytes(data):
                problems.append(f'{base}: {rel} differs from the commit (or is missing from the manifest)')
        problems += [f'{base}: {rel} is not in the commit' for rel in manifest if rel not in tree]
        print(f'{base}: {len(inzip)} zip entries, {len(manifest)} manifest lines, {len(tree)} files at {sha[:7]}')
    return problems


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--commit', default='HEAD')
    ap.add_argument('--dir', required=True)
    a = ap.parse_args()
    problems = verify(a.commit, a.dir)
    for p in problems:
        print('FAIL ' + p)
    print('verify: ' + ('FAIL' if problems else 'PASS'))
    sys.exit(1 if problems else 0)


if __name__ == '__main__':
    main()
