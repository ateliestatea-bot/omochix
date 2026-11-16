"""Tests for tools/release (build, verify, plan, rehearse). Run: python3 -m unittest discover -s tests/release -v"""
import contextlib
import json
import os
import shutil
import sys
import tempfile
import unittest
import warnings
import zipfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
sys.path.insert(0, os.path.join(ROOT, 'tools', 'release'))
import build  # noqa: E402
import plan as planmod  # noqa: E402
import rehearse  # noqa: E402
import releaselib as rl  # noqa: E402
import verify  # noqa: E402


EXPECTED = os.path.join(ROOT, 'tests', 'release', 'expected')
# The Industry Hub release (Core and theme) is the config with both packages; the generic build/verify tests use it.
BOTH_PACKAGES = 'industry-hub-core-1.10.0-theme-1.29.0.json'


@contextlib.contextmanager
def release_config(name):
    """Use tools/release/release.json (name 'release.json') or a named config in tools/release/configs/."""
    old = os.environ.get('OMOCHIX_RELEASE_CONFIG')
    if name == 'release.json':
        os.environ.pop('OMOCHIX_RELEASE_CONFIG', None)
    else:
        os.environ['OMOCHIX_RELEASE_CONFIG'] = name
    try:
        yield
    finally:
        if old is None:
            os.environ.pop('OMOCHIX_RELEASE_CONFIG', None)
        else:
            os.environ['OMOCHIX_RELEASE_CONFIG'] = old


class BuildAndVerify(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        self._cfg = release_config(BOTH_PACKAGES)
        self._cfg.__enter__()

    def tearDown(self):
        self._cfg.__exit__(None, None, None)
        shutil.rmtree(self.tmp)

    def test_same_commit_builds_byte_identical_zips(self):
        a, b = os.path.join(self.tmp, 'a'), os.path.join(self.tmp, 'b')
        ra, rb = build.build('HEAD', a, allow_dev=True), build.build('HEAD', b, allow_dev=True)
        for key in ra['packages']:
            self.assertEqual(ra['packages'][key]['sha256'], rb['packages'][key]['sha256'])
            name = ra['packages'][key]['zip']
            with open(os.path.join(a, name), 'rb') as x, open(os.path.join(b, name), 'rb') as y:
                self.assertEqual(x.read(), y.read())
        self.assertEqual([], verify.verify('HEAD', a))

    def test_zip_entries_are_normalised(self):
        r = build.build('HEAD', self.tmp, allow_dev=True)
        with zipfile.ZipFile(os.path.join(self.tmp, r['packages']['core']['zip'])) as z:
            infos = z.infolist()
        names = [i.filename for i in infos]
        self.assertEqual(sorted(names, key=lambda s: s.encode()), names, 'entries are sorted')
        self.assertTrue(all(i.filename.startswith('omochix-core/') for i in infos))
        self.assertEqual({i.date_time for i in infos}, {infos[0].date_time}, 'one fixed time')
        self.assertTrue(all((i.external_attr >> 16) in (0o100644, 0o40755) for i in infos))
        self.assertFalse(any(n.endswith('.DS_Store') or n.endswith(' 2') for n in names))

    def test_verify_detects_a_changed_file_and_a_missing_manifest_line(self):
        r = build.build('HEAD', self.tmp, allow_dev=True)
        core = r['packages']['core']['zip']
        path = os.path.join(self.tmp, core)
        files = rl.zip_files(path, 'omochix-core')
        files['readme.txt'] = files['readme.txt'] + b'tampered'
        with open(path, 'wb') as fh:
            fh.write(rl.build_zip(files, 'omochix-core', rl.commit_info('HEAD')[1]))
        problems = verify.verify('HEAD', self.tmp)
        self.assertTrue(any('SHA-256 differs from' in p for p in problems))
        self.assertTrue(any('readme.txt differs from the manifest' in p for p in problems))
        mpath = path[:-4] + '.manifest.sha256'
        with open(mpath, encoding='utf-8') as fh:
            lines = [ln for ln in fh if not ln.rstrip().endswith('  omochix-core.php')]
        with open(mpath, 'w', encoding='utf-8') as fh:
            fh.writelines(lines)
        self.assertTrue(any('omochix-core.php is in the zip but not in the manifest' in p for p in verify.verify('HEAD', self.tmp)))



class ReleaseExpectations(unittest.TestCase):
    """Every release config (the current one and the named ones that have expectations) against its own commit
    and against the hand-recorded expectations in tests/release/expected/, never against itself."""

    def releases(self):
        names = sorted(f for f in os.listdir(EXPECTED) if f.endswith('.json'))
        self.assertIn('release.json', names)
        self.assertIn(BOTH_PACKAGES, names)
        for name in names:
            with open(os.path.join(EXPECTED, name), encoding='utf-8') as fh:
                yield name, json.load(fh)

    def test_each_release_plan_matches_its_expectations(self):
        for name, exp in self.releases():
            with self.subTest(release=name), release_config(name):
                cfg = rl.load_config()
                self.assertEqual(exp['release'], cfg['release'])
                plan, packages, problems = planmod.make_plan(exp['commit'])
                self.assertEqual([], problems)
                self.assertEqual(exp['steps'], len(plan['steps']))
                self.assertEqual(sorted(exp['packages']), sorted(plan['packages']))
                changed = sorted(f"{s['package']}:{s['path']}" for s in plan['steps'])
                self.assertEqual(exp['changed_paths_sha256'], rl.sha256_bytes(''.join(c + '\n' for c in changed).encode()))
                for key, want in exp['packages'].items():
                    mine = [s for s in plan['steps'] if s['package'] == key]
                    self.assertEqual(want['add'], sum(1 for s in mine if s['action'] == 'add'), key)
                    self.assertEqual(want['replace'], sum(1 for s in mine if s['action'] == 'replace'), key)
                    self.assertEqual(want['files'], len(packages[key]['files']), key)
                    self.assertEqual(exp['versions'][key], plan['packages'][key]['version'], key)
                    self.assertEqual(want['baseline_sha256'], rl.sha256_file(os.path.join(ROOT, cfg['packages'][key]['baseline'])), key)
                self.assertEqual(exp['gates'], list(plan['gates']))
                self.assertEqual(exp['rollback']['count'], len(plan['rollback_order']))
                if BOTH_PACKAGES == name:
                    self.assertEqual(['plugins/omochix-core/omochix-core.php'], [s['target'] for s in plan['steps'] if s['n'] == 14])
                    self.assertTrue(any('Permalinks' in n for n in plan['after_rollback']), 'rollback requires regenerating the rewrite rules')

    def test_packages_the_release_must_not_touch_are_identical_to_production(self):
        for name, exp in self.releases():
            for key, u in exp['unchanged'].items():
                with self.subTest(release=name, package=key):
                    self.assertEqual(u['baseline_sha256'], rl.sha256_file(os.path.join(ROOT, u['baseline'])))
                    base = rl.read_manifest(os.path.join(ROOT, u['baseline']))
                    now = {p: rl.sha256_bytes(d) for p, d in rl.tree_files(rl.commit_info(exp['commit'])[0], u['dir']).items()}
                    self.assertEqual(base, now)

    def test_current_release_builds_reproducibly_and_matches_git(self):
        with open(os.path.join(EXPECTED, 'release.json'), encoding='utf-8') as fh:
            exp = json.load(fh)
        a, b = tempfile.mkdtemp(), tempfile.mkdtemp()
        try:
            with release_config('release.json'):
                ra, rb = build.build(exp['commit'], a), build.build(exp['commit'], b)
                self.assertEqual(sorted(exp['packages']), sorted(ra['packages']))
                for key, p in ra['packages'].items():
                    with open(os.path.join(a, p['zip']), 'rb') as x, open(os.path.join(b, rb['packages'][key]['zip']), 'rb') as y:
                        self.assertEqual(x.read(), y.read(), 'two builds of the same commit differ')
                    with zipfile.ZipFile(os.path.join(a, p['zip'])) as zf:
                        self.assertEqual(exp['packages'][key]['files'], sum(1 for i in zf.infolist() if not i.is_dir()))
                self.assertEqual([], verify.verify(exp['commit'], a))
        finally:
            shutil.rmtree(a)
            shutil.rmtree(b)

    # The scope check refuses configs that do not match the tree (independent of the expectations above).
    def test_plan_refuses_a_step_missing_from_the_order_and_a_step_that_does_not_change(self):
        with release_config('release.json'):
            cfg = rl.load_config()
        dropped = dict(cfg, order=[s for s in cfg['order'] if s != ['theme', 'inc/nav.php']])
        extra = dict(cfg, order=[['theme', '404.php']] + cfg['order'])
        for bad, needle in ((dropped, 'inc/nav.php differs from production but is not in the approved order'),
                            (extra, '404.php is in the order but does not change')):
            with self.subTest(needle=needle):
                orig = rl.load_config
                rl.load_config = lambda bad=bad: bad
                try:
                    _, _, problems = planmod.make_plan('HEAD')
                finally:
                    rl.load_config = orig
                self.assertTrue(any(needle in p for p in problems), problems)


class Fixture(unittest.TestCase):
    """A synthetic package and a throwaway target (no WordPress needed)."""

    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        self.src = os.path.join(self.tmp, 'release')
        self.tgt = os.path.join(self.tmp, 'wp-content')
        self.bak = os.path.join(self.tmp, 'backup')
        self.log = os.path.join(self.tmp, 'audit.jsonl')
        self.files = {'new/dir/a.php': b'<?php // a', 'b.php': b'<?php // b release', 'switch.php': b'<?php // switch release'}
        self.old = {'b.php': b'<?php // b production', 'switch.php': b'<?php // switch production'}
        for rel, data in self.files.items():
            self._write(os.path.join(self.src, 'pkg', rel), data)
        for rel, data in self.old.items():
            self._write(os.path.join(self.tgt, 'plugins', 'pkg', rel), data)
        self._write(os.path.join(self.tgt, rehearse.MARKER), b'')
        steps = []
        for n, rel in enumerate(['new/dir/a.php', 'b.php', 'switch.php'], 1):
            steps.append({'n': n, 'package': 'p', 'path': rel, 'target': f'plugins/pkg/{rel}', 'action': 'replace' if rel in self.old else 'add',
                          'old_sha256': rl.sha256_bytes(self.old[rel]) if rel in self.old else None, 'new_sha256': rl.sha256_bytes(self.files[rel])})
        self.man = os.path.join(self.tmp, 'dist')
        mtext = rl.manifest_lines(self.files).encode('utf-8')
        self._write(os.path.join(self.man, 'pkg-1.manifest.sha256'), mtext)
        self.plan = {'packages': {'p': {'name': 'pkg', 'target': 'plugins/pkg', 'manifest': 'pkg-1.manifest.sha256',
                                        'manifest_sha256': rl.sha256_bytes(mtext), 'zip': 'pkg-1.zip', 'zip_sha256': '0' * 64}},
                     'steps': steps,
                     'rollback_order': [['p', 'switch.php'], ['p', 'b.php']], 'after_apply': ['reset opcache'],
                     'after_rollback': ['save permalinks']}
        self.logger = rehearse.Log(self.log, quiet=True)

    def tearDown(self):
        shutil.rmtree(self.tmp)

    @staticmethod
    def _write(path, data):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, 'wb') as fh:
            fh.write(data)

    def _read(self, rel):
        p = os.path.join(self.tgt, 'plugins', 'pkg', rel)
        if not os.path.exists(p):
            return None
        with open(p, 'rb') as fh:
            return fh.read()

    def _entries(self):
        with open(self.log, encoding='utf-8') as fh:
            return [json.loads(line) for line in fh]

class Rehearse(Fixture):
    """The staged replace procedure."""

    def test_refuses_a_target_without_the_rehearsal_marker(self):
        os.unlink(os.path.join(self.tgt, rehearse.MARKER))
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man)
        self.assertEqual(self.old['b.php'], self._read('b.php'))

    def test_a_missing_release_file_stops_before_any_write(self):
        os.unlink(os.path.join(self.src, 'pkg', 'switch.php'))
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man)
        self.assertIsNone(self._read('new/dir/a.php'))
        self.assertEqual(self.old['b.php'], self._read('b.php'))
        self.assertFalse(os.path.exists(self.bak))

    def test_an_unexpected_production_file_stops_before_any_write(self):
        self._write(os.path.join(self.tgt, 'plugins', 'pkg', 'b.php'), b'<?php // edited on the server')
        self.assertTrue(rehearse.preflight(self.plan, self.src, self.tgt, self.man))
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man)
        self.assertIsNone(self._read('new/dir/a.php'))

    def test_apply_creates_folders_backs_up_and_logs(self):
        rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man)
        for rel, data in self.files.items():
            self.assertEqual(data, self._read(rel))
        self.assertEqual([], rehearse.verify(self.plan, self.tgt, 'release'))
        with open(os.path.join(self.bak, 'plugins', 'pkg', 'b.php'), 'rb') as fh:
            self.assertEqual(self.old['b.php'], fh.read())
        actions = [e['action'] for e in self._entries()]
        for a in ('preflight', 'backup', 'mkdir', 'add', 'replace', 'todo'):
            self.assertIn(a, actions)
        leftovers = [f for _, _, fs in os.walk(self.tgt) for f in fs if f.endswith('.tmp-release')]
        self.assertEqual([], leftovers)

    def test_an_interrupted_run_resumes_where_it_stopped(self):
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man, stop_after=1)
        self.assertEqual(self.files['new/dir/a.php'], self._read('new/dir/a.php'))
        self.assertEqual(self.old['switch.php'], self._read('switch.php'))
        rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man)
        self.assertEqual([], rehearse.verify(self.plan, self.tgt, 'release'))
        self.assertIn('skip', [e['action'] for e in self._entries()])

    def test_a_failing_probe_stops_the_run(self):
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man, probe='exit 1')
        self.assertEqual(self.old['b.php'], self._read('b.php'))

    def test_rollback_restores_switch_files_first_and_keeps_added_files(self):
        rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man)
        rehearse.rollback(self.plan, self.tgt, self.bak, self.logger)
        self.assertEqual(self.old['b.php'], self._read('b.php'))
        self.assertEqual(self.old['switch.php'], self._read('switch.php'))
        self.assertEqual(self.files['new/dir/a.php'], self._read('new/dir/a.php'))
        restored = [e['path'] for e in self._entries() if e['action'] == 'restore']
        self.assertEqual(['plugins/pkg/switch.php', 'plugins/pkg/b.php'], restored)
        self.assertEqual([], rehearse.verify(self.plan, self.tgt, 'production'))
        self.assertIn('save permalinks', [e.get('detail') for e in self._entries() if e['action'] == 'todo'])
        rehearse.rollback(self.plan, self.tgt, self.bak, self.logger, remove_added=True)
        self.assertIsNone(self._read('new/dir/a.php'))

    def test_rollback_refuses_a_broken_backup(self):
        rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man)
        self._write(os.path.join(self.bak, 'plugins', 'pkg', 'b.php'), b'corrupt')
        with self.assertRaises(rehearse.Stop):
            rehearse.rollback(self.plan, self.tgt, self.bak, self.logger)
        self.assertEqual(self.files['switch.php'], self._read('switch.php'))


class Attacks(Fixture):
    """Attacks found in the 2026-11-11 review. Each one wrote outside the target, followed a
    symlink, accepted a tampered input or leaked data before the fix (35c90e5)."""

    def _outside(self):
        return os.path.join(self.tmp, 'outside')

    def _assert_nothing_outside(self):
        out = self._outside()
        written = [f for _, _, fs in os.walk(out) for f in fs] if os.path.isdir(out) else []
        self.assertEqual([], written, 'nothing may be written outside the target')

    def _retarget(self, n, path, target=None):
        s = self.plan['steps'][n - 1]
        s['path'] = path
        s['target'] = target if target is not None else f'plugins/pkg/{path}'

    def _refused(self):
        with self.assertRaises((rehearse.Stop, rl.UnsafeInput)):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man)
        self._assert_nothing_outside()
        self.assertIsNone(self._read('new/dir/a.php'))

    # --- paths in the plan
    def test_dotdot_step_path_is_refused(self):
        self._write(os.path.join(self.src, 'outside', 'x.php'), self.files['new/dir/a.php'])
        self._retarget(1, '../../../outside/x.php')
        self._refused()

    def test_absolute_step_target_is_refused(self):
        self._retarget(1, 'new/dir/a.php', os.path.join(self._outside(), 'x.php'))
        self._refused()

    def test_target_that_is_not_package_target_plus_path_is_refused(self):
        self._retarget(1, 'new/dir/a.php', 'themes/other/new/dir/a.php')
        self._refused()

    def test_dotdot_package_target_is_refused(self):
        self.plan['packages']['p']['target'] = '../outside'
        for s in self.plan['steps']:
            s['target'] = '../outside/' + s['path']
        self._refused()

    # --- symlinks
    def test_symlinked_folder_in_the_target_is_refused(self):
        os.makedirs(self._outside())
        os.makedirs(os.path.join(self.tgt, 'plugins', 'pkg', 'new'))
        os.symlink(self._outside(), os.path.join(self.tgt, 'plugins', 'pkg', 'new', 'dir'))
        self._refused()

    def test_symlinked_destination_file_is_refused(self):
        os.makedirs(self._outside())
        victim = os.path.join(self._outside(), 'victim.php')
        os.makedirs(os.path.join(self.tgt, 'plugins', 'pkg', 'new', 'dir'))
        os.symlink(victim, os.path.join(self.tgt, 'plugins', 'pkg', 'new', 'dir', 'a.php'))
        self._refused()
        self.assertFalse(os.path.exists(victim))

    def test_symlinked_source_file_is_refused(self):
        os.makedirs(self._outside())
        real = os.path.join(self._outside(), 'secret.php')
        with open(real, 'wb') as fh:
            fh.write(self.files['new/dir/a.php'])
        os.unlink(os.path.join(self.src, 'pkg', 'new', 'dir', 'a.php'))
        os.symlink(real, os.path.join(self.src, 'pkg', 'new', 'dir', 'a.php'))
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man)
        self.assertIsNone(self._read('new/dir/a.php'))

    def test_folder_swapped_for_a_symlink_after_preflight_is_not_followed(self):
        os.makedirs(self._outside())
        real = rehearse.preflight

        def swap(*args):
            problems = real(*args)
            d = os.path.join(self.tgt, 'plugins', 'pkg', 'new')
            if not os.path.lexists(d):
                os.symlink(self._outside(), d)
            return problems
        rehearse.preflight = swap
        try:
            self._refused()
        finally:
            rehearse.preflight = real

    # --- tampered inputs
    def test_plan_and_source_tampered_together_are_refused(self):
        evil = b'<?php system($_GET["c"]);'
        self._write(os.path.join(self.src, 'pkg', 'b.php'), evil)
        self.plan['steps'][1]['new_sha256'] = rl.sha256_bytes(evil)
        self._refused()
        self.assertEqual(self.old['b.php'], self._read('b.php'))

    def test_tampered_manifest_is_refused(self):
        evil = b'<?php system($_GET["c"]);'
        self._write(os.path.join(self.src, 'pkg', 'b.php'), evil)
        self.plan['steps'][1]['new_sha256'] = rl.sha256_bytes(evil)
        files = dict(self.files, **{'b.php': evil})
        self._write(os.path.join(self.man, 'pkg-1.manifest.sha256'), rl.manifest_lines(files).encode())
        self._refused()

    def test_tampered_plan_file_fails_its_recorded_sha256(self):
        path = os.path.join(self.tmp, 'release-plan.json')
        data = json.dumps(self.plan).encode()
        self._write(path, data)
        self._write(path + '.sha256', f'{rl.sha256_bytes(data)}  release-plan.json\n'.encode())
        self.assertEqual(self.plan['steps'], rehearse.load_plan(path)['steps'])
        self._write(path, data.replace(b'plugins/pkg/b.php', b'plugins/pkg/c.php'))
        with self.assertRaises(rehearse.Stop):
            rehearse.load_plan(path)
        with self.assertRaises(rehearse.Stop):
            rehearse.load_plan(path, expected_sha='0' * 64)

    def test_missing_manifest_is_refused(self):
        os.unlink(os.path.join(self.man, 'pkg-1.manifest.sha256'))
        self._refused()

    # --- audit log
    def test_log_has_no_command_output_and_no_absolute_paths(self):
        rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, self.man,
                       probe='echo DB_PASSWORD=hunter2; echo token-abc >&2')
        with open(self.log, encoding='utf-8') as fh:
            text = fh.read()
        for leak in ('hunter2', 'token-abc', self.tmp, os.path.realpath(self.tmp)):
            self.assertNotIn(leak, text)
        for e in self._entries():
            if 'path' in e:
                rl.safe_rel(e['path'])

    def test_stop_message_has_no_absolute_paths(self):
        os.makedirs(self._outside())
        os.makedirs(os.path.join(self.tgt, 'plugins', 'pkg', 'new'))
        os.symlink(self._outside(), os.path.join(self.tgt, 'plugins', 'pkg', 'new', 'dir'))
        problems = rehearse.preflight(self.plan, self.src, self.tgt, self.man)
        self.assertTrue(problems)
        self.assertFalse(any(self.tmp in p or os.path.realpath(self.tmp) in p for p in problems))


class ArchiveAttacks(unittest.TestCase):
    """Zips and manifests that verify must refuse."""

    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        self._cfg = release_config(BOTH_PACKAGES)
        self._cfg.__enter__()

    def tearDown(self):
        self._cfg.__exit__(None, None, None)
        shutil.rmtree(self.tmp)

    def _zip(self, entries):
        path = os.path.join(self.tmp, 'p.zip')
        with zipfile.ZipFile(path, 'w') as z:
            for name, data, mode in entries:
                zi = zipfile.ZipInfo(name, (2026, 1, 1, 0, 0, 0))
                zi.create_system = 3
                zi.external_attr = mode << 16
                with warnings.catch_warnings():
                    warnings.simplefilter('ignore')
                    z.writestr(zi, data)
        return path

    def test_duplicate_zip_entries_are_refused(self):
        p = self._zip([('pkg/a.php', b'evil', 0o100644), ('pkg/a.php', b'good', 0o100644)])
        with self.assertRaises(rl.UnsafeInput):
            rl.zip_files(p, 'pkg')

    def test_dotdot_absolute_and_outside_zip_entries_are_refused(self):
        for name in ('pkg/../evil.php', '/pkg/a.php', 'other/a.php', 'pkg/./a.php', 'pkg//a.php', 'pkg/a\\..\\b.php'):
            with self.subTest(name=name), self.assertRaises(rl.UnsafeInput):
                rl.zip_files(self._zip([(name, b'x', 0o100644)]), 'pkg')

    def test_symlink_zip_entry_is_refused(self):
        p = self._zip([('pkg/a.php', b'/etc/passwd', 0o120777)])
        with self.assertRaises(rl.UnsafeInput):
            rl.zip_files(p, 'pkg')

    def test_verify_reports_a_refused_zip_instead_of_passing(self):
        r = build.build('HEAD', self.tmp, allow_dev=True)
        core = os.path.join(self.tmp, r['packages']['core']['zip'])
        with zipfile.ZipFile(core, 'a') as z, warnings.catch_warnings():
            warnings.simplefilter('ignore')
            z.writestr('omochix-core/readme.txt', b'second copy')
        self.assertTrue(any('refused' in p for p in verify.verify('HEAD', self.tmp)))

    def test_bad_manifest_lines_are_refused(self):
        good = 'a' * 64
        for text in (f'{good}  a.php\n{good}  a.php\n', f'{good}  ../a.php\n', f'{good}  /etc/a\n',
                     'nothex  a.php\n', f'{good} a.php\n', f'{good}  \n'):
            with self.subTest(text=text), self.assertRaises(rl.UnsafeInput):
                rl.parse_manifest(text)

    def test_git_symlinks_and_submodules_are_not_shipped(self):
        for mode, kind in (('120000', 'blob'), ('160000', 'commit')):
            with self.subTest(mode=mode), self.assertRaises(rl.UnsafeInput):
                rl.parse_ls_tree(f'{mode} {kind} {"b" * 40}\tdir/link.php\0', 'dir')
        self.assertEqual([('a.php', 'c' * 40)], rl.parse_ls_tree(f'100644 blob {"c" * 40}\tdir/a.php\0', 'dir'))

    def test_plan_records_zip_and_manifest_sha256_of_the_commit(self):
        plan, packages, _ = planmod.make_plan('HEAD')
        r = build.build('HEAD', self.tmp, allow_dev=True)
        for key, p in plan['packages'].items():
            self.assertEqual(r['packages'][key]['sha256'], p['zip_sha256'])
            self.assertEqual(r['packages'][key]['zip'], p['zip'])
            self.assertEqual(rl.sha256_file(os.path.join(self.tmp, p['manifest'])), p['manifest_sha256'])
        self.assertEqual([], rehearse.check_plan(plan))


if __name__ == '__main__':
    unittest.main()
