"""Shared helpers for the OmochiX release tools (Python 3 standard library only).

Packages are always built from a git commit, never from a working tree, so a
package is byte-for-byte the files of that commit. Every path that comes from a
zip, a manifest, a plan or git goes through safe_rel(): relative, POSIX, no
"..", "." or empty segment, no backslash, no NUL.
"""
import hashlib
import io
import json
import os
import re
import stat
import subprocess
import time
import zipfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
HEX64 = re.compile(r'^[0-9a-f]{64}$')


class UnsafeInput(ValueError):
    """A path, archive or manifest that the release tools refuse."""


def safe_rel(path):
    """The path itself when it is a safe relative POSIX path, else UnsafeInput."""
    if not isinstance(path, str) or not path:
        raise UnsafeInput(f'empty or non-text path: {path!r}')
    if '\0' in path or '\\' in path or path.startswith('/') or re.match(r'^[A-Za-z]:', path):
        raise UnsafeInput(f'unsafe path: {path!r}')
    parts = path.split('/')
    if any(p in ('', '.', '..') for p in parts):
        raise UnsafeInput(f'unsafe path segment in {path!r}')
    return path


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


def parse_ls_tree(text, directory):
    """[(relative path, blob sha)] from `git ls-tree -r -z` output; only regular files are allowed."""
    out = []
    for rec in filter(None, text.split('\0')):
        meta, name = rec.split('\t', 1)
        mode, kind, obj = meta.split(' ')
        if kind != 'blob' or mode not in ('100644', '100755'):
            raise UnsafeInput(f'{name}: git mode {mode} {kind} (symlinks and submodules are not shipped)')
        rel = name[len(directory) + 1:]
        out.append((safe_rel(rel), obj))
    return out


def tree_files(commit, directory):
    """{relative path: bytes} for every file under directory at commit."""
    files = {}
    for rel, obj in parse_ls_tree(git('ls-tree', '-r', '-z', commit, '--', directory), directory):
        if rel in files:
            raise UnsafeInput(f'duplicate path in git tree: {rel}')
        files[rel] = git('cat-file', 'blob', obj, binary=True)
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
    return ''.join(f'{sha256_bytes(files[p])}  {safe_rel(p)}\n' for p in sorted(files, key=lambda s: s.encode('utf-8')))


def parse_manifest(text):
    """{path: sha256}; refuses malformed lines, unsafe paths and duplicate paths."""
    entries = {}
    for n, line in enumerate(text.split('\n'), 1):
        if not line:
            continue
        if '  ' not in line:
            raise UnsafeInput(f'manifest line {n}: not "<sha256>  <path>"')
        digest, rel = line.split('  ', 1)
        if not HEX64.match(digest):
            raise UnsafeInput(f'manifest line {n}: not a SHA-256')
        safe_rel(rel)
        if rel in entries:
            raise UnsafeInput(f'manifest line {n}: duplicate path {rel}')
        entries[rel] = digest
    return entries


def read_manifest(path):
    with open(path, encoding='utf-8') as fh:
        return parse_manifest(fh.read())


def build_zip(files, top, ts):
    """Deterministic zip: sorted entries, fixed time, 0644/0755, deflate 9, no extra fields."""
    dt = time.gmtime(ts)[:6]
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, 'w') as z:
        seen = set()
        for rel in sorted(files, key=lambda s: s.encode('utf-8')):
            parts = safe_rel(rel).split('/')
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
    """{relative path: bytes} of the files in a package zip (top folder stripped).

    Refuses: an entry outside <top>/, an unsafe path, a duplicate name, a
    symlink or other non-regular entry, and a directory entry with content.
    """
    out = {}
    with zipfile.ZipFile(path) as z:
        infos = z.infolist()
        names = [i.filename for i in infos]
        dupes = sorted({n for n in names if names.count(n) > 1})
        if dupes:
            raise UnsafeInput(f'duplicate zip entries: {dupes}')
        for info in infos:
            name = info.filename
            if not name.startswith(top + '/'):
                raise UnsafeInput(f'entry outside {top}/: {name!r}')
            mode = info.external_attr >> 16
            is_dir = name.endswith('/')
            rel = name[len(top) + 1:].rstrip('/') if is_dir else name[len(top) + 1:]
            if rel:
                safe_rel(rel)
            if is_dir:
                if info.file_size:
                    raise UnsafeInput(f'directory entry with content: {name!r}')
                continue
            if mode and not stat.S_ISREG(mode):
                raise UnsafeInput(f'not a regular file (mode {oct(mode)}): {name!r}')
            out[rel] = z.read(info)
    return out
