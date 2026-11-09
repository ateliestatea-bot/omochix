"""Shared helpers for the OmochiX release tools (Python 3 standard library only).

Packages are always built from a git commit, never from a working tree, so a
package is byte-for-byte the files of that commit.
"""
import hashlib
import io
import json
import os
import re
import subprocess
import time
import zipfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))


def load_config():
    with open(os.path.join(ROOT, 'tools', 'release', 'release.json'), encoding='utf-8') as fh:
        return json.load(fh)


def sha256_bytes(data):
    return hashlib.sha256(data).hexdigest()


def sha256_file(path):
    h = hashlib.sha256()
    with open(path, 'rb') as fh:
        for chunk in iter(lambda: fh.read(1 << 16), b''):
            h.update(chunk)
    return h.hexdigest()


def git(*args, binary=False):
    out = subprocess.run(['git', '-C', ROOT] + list(args), check=True, capture_output=True).stdout
    return out if binary else out.decode('utf-8')


def commit_info(commit):
    sha = git('rev-parse', commit + '^{commit}').strip()
    ts = int(git('show', '-s', '--format=%ct', sha).strip())
    return sha, ts


def tree_files(commit, directory):
    """{relative path: bytes} for every file under directory at commit."""
    names = git('ls-tree', '-r', '-z', '--name-only', commit, '--', directory).split('\0')
    files = {}
    for name in filter(None, names):
        files[name[len(directory) + 1:]] = git('show', f'{commit}:{name}', binary=True)
    return files


def core_version(files):
    m = re.search(r"define\( 'OMOCHIX_CORE_VERSION', '([^']+)' \);", files['omochix-core.php'].decode('utf-8'))
    return m.group(1) if m else ''


def theme_version(files):
    m = re.search(r'^Version:\s*(\S+)', files['style.css'].decode('utf-8'), re.M)
    return m.group(1) if m else ''


def package_version(key, files):
    return core_version(files) if 'core' == key else theme_version(files)


def manifest_lines(files):
    """sha256sum format, paths relative to the package root, sorted bytewise (LC_ALL=C)."""
    return ''.join(f'{sha256_bytes(files[p])}  {p}\n' for p in sorted(files, key=lambda s: s.encode('utf-8')))


def read_manifest(path):
    entries = {}
    with open(path, encoding='utf-8') as fh:
        for line in fh:
            line = line.rstrip('\n')
            if line:
                digest, rel = line.split('  ', 1)
                entries[rel] = digest
    return entries


def build_zip(files, top, ts):
    """Deterministic zip: sorted entries, fixed time, 0644/0755, deflate 9, no extra fields."""
    dt = time.gmtime(ts)[:6]
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, 'w') as z:
        seen = set()
        for rel in sorted(files, key=lambda s: s.encode('utf-8')):
            parts = rel.split('/')
            for i in range(1, len(parts)):
                d = '/'.join([top] + parts[:i]) + '/'
                if d not in seen:
                    zi = zipfile.ZipInfo(d, dt)
                    zi.create_system = 3
                    zi.external_attr = (0o40755 << 16) | 0x10
                    z.writestr(zi, b'')
                    seen.add(d)
            zi = zipfile.ZipInfo(f'{top}/{rel}', dt)
            zi.create_system = 3
            zi.external_attr = 0o100644 << 16
            zi.compress_type = zipfile.ZIP_DEFLATED
            z.writestr(zi, files[rel], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    return buf.getvalue()


def zip_files(path, top):
    """{relative path: bytes} of the files inside a package zip (top folder stripped)."""
    out = {}
    with zipfile.ZipFile(path) as z:
        for info in z.infolist():
            if info.filename.endswith('/'):
                continue
            if not info.filename.startswith(top + '/'):
                raise ValueError(f'entry outside {top}/: {info.filename}')
            out[info.filename[len(top) + 1:]] = z.read(info.filename)
    return out
