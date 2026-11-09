"""Tests for tools/release (build, verify, plan, rehearse). Run: python3 -m unittest discover -s tests/release -v"""
import json
import os
import shutil
import sys
import tempfile
import unittest
import zipfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
sys.path.insert(0, os.path.join(ROOT, 'tools', 'release'))
import build  # noqa: E402
import plan as planmod  # noqa: E402
import rehearse  # noqa: E402
import releaselib as rl  # noqa: E402
import verify  # noqa: E402


class BuildAndVerify(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()

    def tearDown(self):
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

    def test_plan_scope_matches_the_approved_order(self):
        plan, _, problems = planmod.make_plan('HEAD')
        self.assertEqual([], problems)
        self.assertEqual(29, len(plan['steps']))
        self.assertEqual(6, sum(1 for s in plan['steps'] if s['action'] == 'replace'))
        self.assertEqual(['plugins/omochix-core/omochix-core.php'], [s['target'] for s in plan['steps'] if s['n'] == 14])


class Rehearse(unittest.TestCase):
    """The staged replace procedure on a synthetic package (no WordPress needed)."""

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
        self.plan = {'packages': {'p': {'name': 'pkg', 'target': 'plugins/pkg'}}, 'steps': steps,
                     'rollback_order': [['p', 'switch.php'], ['p', 'b.php']], 'after_apply': ['reset opcache']}
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

    def test_refuses_a_target_without_the_rehearsal_marker(self):
        os.unlink(os.path.join(self.tgt, rehearse.MARKER))
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger)
        self.assertEqual(self.old['b.php'], self._read('b.php'))

    def test_a_missing_release_file_stops_before_any_write(self):
        os.unlink(os.path.join(self.src, 'pkg', 'switch.php'))
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger)
        self.assertIsNone(self._read('new/dir/a.php'))
        self.assertEqual(self.old['b.php'], self._read('b.php'))
        self.assertFalse(os.path.exists(self.bak))

    def test_an_unexpected_production_file_stops_before_any_write(self):
        self._write(os.path.join(self.tgt, 'plugins', 'pkg', 'b.php'), b'<?php // edited on the server')
        self.assertTrue(rehearse.preflight(self.plan, self.src, self.tgt))
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger)
        self.assertIsNone(self._read('new/dir/a.php'))

    def test_apply_creates_folders_backs_up_and_logs(self):
        rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger)
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
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, stop_after=1)
        self.assertEqual(self.files['new/dir/a.php'], self._read('new/dir/a.php'))
        self.assertEqual(self.old['switch.php'], self._read('switch.php'))
        rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger)
        self.assertEqual([], rehearse.verify(self.plan, self.tgt, 'release'))
        self.assertIn('skip', [e['action'] for e in self._entries()])

    def test_a_failing_probe_stops_the_run(self):
        with self.assertRaises(rehearse.Stop):
            rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger, probe='exit 1')
        self.assertEqual(self.old['b.php'], self._read('b.php'))

    def test_rollback_restores_switch_files_first_and_keeps_added_files(self):
        rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger)
        rehearse.rollback(self.plan, self.tgt, self.bak, self.logger)
        self.assertEqual(self.old['b.php'], self._read('b.php'))
        self.assertEqual(self.old['switch.php'], self._read('switch.php'))
        self.assertEqual(self.files['new/dir/a.php'], self._read('new/dir/a.php'))
        restored = [e['path'] for e in self._entries() if e['action'] == 'restore']
        self.assertEqual(['plugins/pkg/switch.php', 'plugins/pkg/b.php'], restored)
        self.assertEqual([], rehearse.verify(self.plan, self.tgt, 'production'))
        rehearse.rollback(self.plan, self.tgt, self.bak, self.logger, remove_added=True)
        self.assertIsNone(self._read('new/dir/a.php'))

    def test_rollback_refuses_a_broken_backup(self):
        rehearse.apply(self.plan, self.src, self.tgt, self.bak, self.logger)
        self._write(os.path.join(self.bak, 'plugins', 'pkg', 'b.php'), b'corrupt')
        with self.assertRaises(rehearse.Stop):
            rehearse.rollback(self.plan, self.tgt, self.bak, self.logger)
        self.assertEqual(self.files['switch.php'], self._read('switch.php'))


if __name__ == '__main__':
    unittest.main()
