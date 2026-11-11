"""Tests for tools/release/deploy.py, the production deploy helper. Run: python3 -m unittest discover -s tests/release -v

Every test builds a fake home with web/wp-content (Core and theme), real zips and
manifests, and a plan whose SHA-256 is passed as the trusted value. Tests marked
"production path" run without --qa, with HOME pointing at the fake home, so the
code that runs on the server (~/web) is the code under test.
"""
import contextlib
import io
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
import deploy  # noqa: E402
import releaselib as rl  # noqa: E402

CORE_OLD = {'omochix-core.php': b'<?php // core 1.9.3', 'readme.txt': b'Stable tag: 1.9.3'}
CORE_NEW = {'omochix-core.php': b'<?php // core 1.10.0', 'readme.txt': b'Stable tag: 1.10.0', 'includes/industry-hub.php': b'<?php // hub'}
THEME_OLD = {'style.css': b'/* Version: 1.28.0 */', 'functions.php': b'<?php // 1.28.0', 'inc/labs.php': b'<?php // labs'}
THEME_NEW = {'style.css': b'/* Version: 1.29.0 */', 'functions.php': b'<?php // 1.29.0', 'inc/labs.php': b'<?php // labs',
             'template-parts/learn-industries/card.php': b'<?php // card'}


class Base(unittest.TestCase):
    production = False

    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        self.home = os.path.join(self.tmp, 'home')
        self.web = os.path.join(self.home, 'web')
        self.wpc = os.path.join(self.web, 'wp-content')
        self.dist = os.path.join(self.home, 'dist')
        self.outside = os.path.join(self.tmp, 'outside')
        os.makedirs(self.outside)
        for rel, data in CORE_OLD.items():
            self._w(os.path.join(self.wpc, 'plugins', 'omochix-core', rel), data)
        for rel, data in THEME_OLD.items():
            self._w(os.path.join(self.wpc, 'themes', 'omochix-theme', rel), data)
        self._w(os.path.join(self.web, 'wp-includes', 'version.php'), b"<?php\n$wp_version = '7.1.4';\n")
        if not self.production:
            self._w(os.path.join(self.wpc, deploy.MARKER), b'')
        self.plan = self._make_plan()
        self._save_plan()
        self.backup = os.path.join(self.home, 'bk-1')
        self.log = os.path.join(self.home, 'ih.log')
        self.old_home = os.environ.get('HOME')
        os.environ['HOME'] = self.home

    def tearDown(self):
        os.environ['HOME'] = self.old_home
        shutil.rmtree(self.tmp)

    @staticmethod
    def _w(path, data):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, 'wb') as fh:
            fh.write(data)

    def _make_plan(self):
        pk, steps = {}, []
        for key, name, target, old, new in (('core', 'omochix-core', 'plugins/omochix-core', CORE_OLD, CORE_NEW),
                                           ('theme', 'omochix-theme', 'themes/omochix-theme', THEME_OLD, THEME_NEW)):
            m = rl.manifest_lines(new).encode()
            z = rl.build_zip(new, name, 1767225600)
            self._w(os.path.join(self.dist, f'{name}-x.manifest.sha256'), m)
            self._w(os.path.join(self.dist, f'{name}-x.zip'), z)
            pk[key] = {'name': name, 'target': target, 'manifest': f'{name}-x.manifest.sha256', 'manifest_sha256': rl.sha256_bytes(m),
                       'zip': f'{name}-x.zip', 'zip_sha256': rl.sha256_bytes(z)}
            switch = 'omochix-core.php' if key == 'core' else 'style.css'
            for rel in sorted(p for p in new if new[p] != old.get(p)) + []:
                if rel != switch:
                    steps.append((key, target, rel, old, new))
            steps.append((key, target, switch, old, new))
        plan_steps = [{'n': i, 'package': k, 'path': rel, 'target': f'{t}/{rel}', 'action': 'replace' if rel in old else 'add',
                       'old_sha256': rl.sha256_bytes(old[rel]) if rel in old else None, 'new_sha256': rl.sha256_bytes(new[rel])}
                      for i, (k, t, rel, old, new) in enumerate(steps, 1)]
        return {'packages': pk, 'steps': plan_steps,
                'gates': {'A': {'after': ['core', 'omochix-core.php'], 'check': 'gate A'}, 'B': {'after': ['theme', 'style.css'], 'check': 'gate B'}},
                'rollback_order': [['theme', 'style.css'], ['theme', 'functions.php'], ['core', 'omochix-core.php'], ['core', 'readme.txt']],
                'after_apply': ['purge caches'], 'after_rollback': ['save permalinks']}

    def _save_plan(self):
        self.plan_path = os.path.join(self.home, 'release-plan.json')
        data = json.dumps(self.plan).encode()
        self._w(self.plan_path, data)
        self.plan_sha = rl.sha256_bytes(data)

    def run_tool(self, *args, plan_sha=None):
        argv = [args[0], '--plan', self.plan_path, '--plan-sha256', plan_sha or self.plan_sha] + list(args[1:])
        if not self.production:
            argv += ['--qa', self.web]
        out = io.StringIO()
        with contextlib.redirect_stdout(out):
            rc = deploy.main(argv)
        self.out = out.getvalue()
        return rc

    def lock(self):
        self.assertEqual(0, self.run_tool('lock', '--confirm', 'IH-LOCK'))
        self.token = self.out.split('token ')[1].strip()
        return self.token

    def w(self, *extra):
        return ['--dist', self.dist, '--expect-wp', '7.1.4', '--token', self.token, '--backup', self.backup, '--log', self.log] + list(extra)

    def file(self, rel):
        p = os.path.join(self.wpc, rel)
        if not os.path.lexists(p):
            return None
        with open(p, 'rb') as fh:
            return fh.read()

    def untouched(self):
        self.assertEqual(CORE_OLD['omochix-core.php'], self.file('plugins/omochix-core/omochix-core.php'))
        self.assertEqual(THEME_OLD['style.css'], self.file('themes/omochix-theme/style.css'))
        self.assertIsNone(self.file('plugins/omochix-core/includes/industry-hub.php'))
        self.assertEqual([], [f for _, _, fs in os.walk(self.outside) for f in fs])

    def ready(self):
        self.lock()
        self.assertEqual(0, self.run_tool('backup', *self.w('--confirm', 'IH-BACKUP')), self.out)


class Flow(Base):
    def test_lock_backup_apply_to_gate_a_resume_verify_rollback_unlock(self):
        self.assertEqual(0, self.run_tool('preflight', '--dist', self.dist, '--expect-wp', '7.1.4'), self.out)
        self.ready()
        self.assertEqual(0, self.run_tool('apply', *self.w('--confirm', 'IH-APPLY', '--until', 'A')), self.out)
        self.assertIn('"paused"', self.out)
        self.assertEqual(CORE_NEW['omochix-core.php'], self.file('plugins/omochix-core/omochix-core.php'))
        self.assertEqual(THEME_OLD['style.css'], self.file('themes/omochix-theme/style.css'), 'theme untouched until gate A is passed')
        self.assertNotEqual(0, self.run_tool('unlock', '--token', self.token, '--confirm', 'IH-UNLOCK'), 'mixed state keeps the lock')
        self.assertEqual(0, self.run_tool('apply', *self.w('--confirm', 'IH-APPLY')), self.out)
        self.assertIn('"skip"', self.out)
        self.assertEqual(0, self.run_tool('verify', '--want', 'release'), self.out)
        for rel, data in THEME_NEW.items():
            self.assertEqual(data, self.file(f'themes/omochix-theme/{rel}'))
        self.assertEqual(0, self.run_tool('rollback', '--expect-wp', '7.1.4', '--token', self.token, '--backup', self.backup,
                                          '--log', self.log, '--confirm', 'IH-ROLLBACK'), self.out)
        self.assertIn('save permalinks', self.out)
        self.assertEqual(0, self.run_tool('verify', '--want', 'production'), self.out)
        self.assertEqual(0, self.run_tool('unlock', '--token', self.token, '--confirm', 'IH-UNLOCK'), self.out)
        self.assertFalse(os.path.exists(os.path.join(self.home, deploy.LOCK_NAME)))
        mode = os.stat(os.path.join(self.wpc, 'themes/omochix-theme/style.css')).st_mode & 0o777
        self.assertEqual(0o644, mode)
        self.assertEqual([], [f for _, _, fs in os.walk(self.wpc) for f in fs if f.endswith('.ih-new')])

    def test_rollback_restores_in_rollback_order_and_can_remove_added_files(self):
        self.ready()
        self.run_tool('apply', *self.w('--confirm', 'IH-APPLY'))
        self.run_tool('rollback', '--expect-wp', '7.1.4', '--token', self.token, '--backup', self.backup, '--log', self.log,
                      '--confirm', 'IH-ROLLBACK', '--remove-added')
        with open(self.log, encoding='utf-8') as fh:
            entries = [json.loads(x) for x in fh]
        self.assertEqual(['themes/omochix-theme/style.css', 'themes/omochix-theme/functions.php', 'plugins/omochix-core/omochix-core.php',
                          'plugins/omochix-core/readme.txt'], [e['path'] for e in entries if e['action'] == 'restore'])
        self.assertIsNone(self.file('plugins/omochix-core/includes/industry-hub.php'))
        self.untouched()


class ProductionPath(Flow):
    """The same flow without --qa: the tool resolves ~/web itself."""
    production = True

    def test_production_root_with_a_rehearsal_marker_is_refused(self):
        self._w(os.path.join(self.wpc, deploy.MARKER), b'')
        self.assertEqual(2, self.run_tool('status'))
        self.assertIn('--qa', self.out)

    def test_probe_is_refused_on_production(self):
        self.lock()
        self.assertEqual(2, self.run_tool('apply', *self.w('--confirm', 'IH-APPLY', '--probe', 'true')))
        self.untouched()


class Trust(Base):
    def test_wrong_plan_sha256_is_refused(self):
        self.assertEqual(2, self.run_tool('status', plan_sha='0' * 64))
        self.assertEqual(2, self.run_tool('status', plan_sha='not-hex'))

    def test_a_tampered_plan_is_refused_even_with_a_new_side_file(self):
        good = self.plan_sha
        self.plan['steps'][0]['new_sha256'] = '1' * 64
        self._save_plan()
        self._w(self.plan_path + '.sha256', f'{self.plan_sha}  release-plan.json\n'.encode())
        self.assertEqual(2, self.run_tool('status', plan_sha=good))

    def test_zip_or_manifest_that_differs_from_the_plan_is_refused(self):
        self.lock()
        zpath = os.path.join(self.dist, 'omochix-theme-x.zip')
        with zipfile.ZipFile(zpath, 'a') as z, warnings.catch_warnings():
            warnings.simplefilter('ignore')
            z.writestr('omochix-theme/functions.php', b'<?php system($_GET["c"]);')
        self.assertEqual(2, self.run_tool('backup', *self.w('--confirm', 'IH-BACKUP')))
        self.assertIn('does not match the plan', self.out)
        self.untouched()

    def test_package_target_outside_the_allowlist_is_refused(self):
        self.plan['packages']['theme']['target'] = 'themes/other'
        for s in self.plan['steps']:
            if s['package'] == 'theme':
                s['target'] = 'themes/other/' + s['path']
        self._save_plan()
        self.assertEqual(2, self.run_tool('status'))
        self.assertIn('package theme', self.out)

    def test_traversal_in_a_trusted_plan_is_still_refused(self):
        self.plan['steps'][0]['path'] = '../../../outside/x.php'
        self.plan['steps'][0]['target'] = 'plugins/omochix-core/../../../outside/x.php'
        self._save_plan()
        self.assertEqual(2, self.run_tool('status'))
        self.untouched()

    def test_zip_entries_and_manifest_lines_are_strict(self):
        def mk(entries):
            buf = io.BytesIO()
            with zipfile.ZipFile(buf, 'w') as z, warnings.catch_warnings():
                warnings.simplefilter('ignore')
                for name, mode in entries:
                    zi = zipfile.ZipInfo(name, (2026, 1, 1, 0, 0, 0))
                    zi.create_system, zi.external_attr = 3, mode << 16
                    z.writestr(zi, b'x')
            return buf.getvalue()
        for entries in ([('p/a', 0o100644), ('p/a', 0o100644)], [('p/../a', 0o100644)], [('/p/a', 0o100644)], [('q/a', 0o100644)],
                        [('p/a', 0o120777)]):
            with self.subTest(entries=entries), self.assertRaises(deploy.Stop):
                deploy.zip_files(mk(entries), 'p')
        good = 'a' * 64
        for text in (f'{good}  a\n{good}  a\n', f'{good}  ../a\n', 'zz  a\n', f'{good} a\n'):
            with self.subTest(text=text), self.assertRaises(deploy.Stop):
                deploy.parse_manifest(text)


class Guards(Base):
    def test_writes_need_the_lock_token_confirmation_and_wordpress_version(self):
        self.token = 'none'
        self.assertEqual(2, self.run_tool('backup', *self.w('--confirm', 'IH-BACKUP')))
        self.assertIn('no release lock', self.out)
        self.lock()
        self.token = 'f' * 32
        self.assertEqual(2, self.run_tool('backup', *self.w('--confirm', 'IH-BACKUP')))
        self.lock_token_ok = self.out
        self.token = open(os.path.join(self.home, deploy.LOCK_NAME)).read()
        self.assertEqual(2, self.run_tool('backup', *self.w('--confirm', 'yes')))
        args = self.w('--confirm', 'IH-BACKUP')
        args[args.index('7.1.4')] = '7.0.0'
        self.assertEqual(2, self.run_tool('backup', *args))
        self.assertEqual(2, self.run_tool('lock', '--confirm', 'IH-LOCK'), 'a second lock is refused')
        self.untouched()
        self.assertFalse(os.path.exists(self.backup))

    def test_apply_without_a_verified_backup_writes_nothing(self):
        self.lock()
        os.mkdir(self.backup)
        self.assertEqual(2, self.run_tool('apply', *self.w('--confirm', 'IH-APPLY')))
        self.untouched()

    def test_backup_and_log_inside_the_web_root_are_refused(self):
        self.lock()
        self.backup = os.path.join(self.web, 'bk')
        self.assertEqual(2, self.run_tool('backup', *self.w('--confirm', 'IH-BACKUP')))
        self.backup = os.path.join(self.home, 'bk-2')
        self.log = os.path.join(self.wpc, 'ih.log')
        self.assertEqual(2, self.run_tool('backup', *self.w('--confirm', 'IH-BACKUP')))
        self.assertFalse(os.path.exists(self.log))

    def test_rollback_refuses_a_corrupt_backup(self):
        self.ready()
        self.run_tool('apply', *self.w('--confirm', 'IH-APPLY'))
        with open(os.path.join(self.backup, 'themes__omochix-theme__style.css'), 'ab') as fh:
            fh.write(b'x')
        self.assertEqual(2, self.run_tool('rollback', '--expect-wp', '7.1.4', '--token', self.token, '--backup', self.backup,
                                          '--log', self.log, '--confirm', 'IH-ROLLBACK'))
        self.assertEqual(THEME_NEW['style.css'], self.file('themes/omochix-theme/style.css'), 'nothing restored')

    def test_an_unexpected_production_file_stops_before_any_write(self):
        self._w(os.path.join(self.wpc, 'themes/omochix-theme/functions.php'), b'<?php // hotfix')
        self.lock()
        self.assertEqual(2, self.run_tool('backup', *self.w('--confirm', 'IH-BACKUP')))
        self.assertNotIn('IH-', self.out.split('STOP')[-1])

    def test_qa_root_without_marker_is_refused(self):
        os.unlink(os.path.join(self.wpc, deploy.MARKER))
        self.assertEqual(2, self.run_tool('status'))


class Symlinks(Base):
    def test_symlinked_folder_is_refused(self):
        shutil.rmtree(os.path.join(self.wpc, 'themes/omochix-theme'))
        os.symlink(self.outside, os.path.join(self.wpc, 'themes/omochix-theme'))
        self.lock()
        self.assertEqual(2, self.run_tool('backup', *self.w('--confirm', 'IH-BACKUP')))
        self.assertEqual([], os.listdir(self.outside))

    def test_symlinked_destination_is_refused(self):
        victim = os.path.join(self.outside, 'victim')
        os.makedirs(os.path.join(self.wpc, 'plugins/omochix-core/includes'))
        os.symlink(victim, os.path.join(self.wpc, 'plugins/omochix-core/includes/industry-hub.php'))
        self.ready_or_fail()
        self.assertFalse(os.path.exists(victim))

    def ready_or_fail(self):
        self.lock()
        self.assertEqual(2, self.run_tool('backup', *self.w('--confirm', 'IH-BACKUP')))

    def test_folder_swapped_for_a_symlink_after_the_checks_is_not_followed(self):
        self.ready()
        real = deploy.preflight

        def swap(*a):
            problems = real(*a)
            d = os.path.join(self.wpc, 'themes/omochix-theme/template-parts')
            if not os.path.lexists(d):
                os.symlink(self.outside, d)
            return problems
        deploy.preflight = swap
        try:
            self.assertEqual(2, self.run_tool('apply', *self.w('--confirm', 'IH-APPLY')))
        finally:
            deploy.preflight = real
        self.assertEqual([], os.listdir(self.outside))

    def test_target_changed_after_the_checks_is_not_overwritten(self):
        self.ready()
        real = deploy.preflight

        def change(*a):
            problems = real(*a)
            self._w(os.path.join(self.wpc, 'plugins/omochix-core/readme.txt'), b'edited meanwhile')
            return problems
        deploy.preflight = change
        try:
            self.assertEqual(2, self.run_tool('apply', *self.w('--confirm', 'IH-APPLY')))
        finally:
            deploy.preflight = real
        self.assertEqual(b'edited meanwhile', self.file('plugins/omochix-core/readme.txt'))
        self.assertIn('changed since the checks', self.out)

    def test_leftover_temporary_name_stops_that_file(self):
        self.ready()
        os.symlink(os.path.join(self.outside, 'x'), os.path.join(self.wpc, 'plugins/omochix-core/.readme.txt.ih-new'))
        self.assertEqual(2, self.run_tool('apply', *self.w('--confirm', 'IH-APPLY')))
        self.assertEqual([], os.listdir(self.outside))
        self.assertEqual(CORE_OLD['readme.txt'], self.file('plugins/omochix-core/readme.txt'))


class AuditLog(Base):
    def test_log_has_relative_paths_only_no_probe_output_and_mode_0600(self):
        self.ready()
        self.run_tool('apply', *self.w('--confirm', 'IH-APPLY', '--probe', 'echo DB_PASSWORD=hunter2; echo tok >&2'))
        with open(self.log, encoding='utf-8') as fh:
            text = fh.read()
        for leak in ('hunter2', 'tok\n', self.tmp, os.path.realpath(self.tmp)):
            self.assertNotIn(leak, text)
        for e in map(json.loads, text.splitlines()):
            if 'path' in e:
                rl.safe_rel(e['path'])
        self.assertEqual(0o600, os.stat(self.log).st_mode & 0o777)
        self.assertEqual(0o700, os.stat(self.backup).st_mode & 0o777)

    def test_stop_messages_hide_the_web_root(self):
        shutil.rmtree(os.path.join(self.wpc, 'themes/omochix-theme'))
        os.symlink(self.outside, os.path.join(self.wpc, 'themes/omochix-theme'))
        self.run_tool('preflight', '--dist', self.dist, '--expect-wp', '7.1.4')
        self.assertNotIn(self.tmp, self.out)
        self.assertNotIn(os.path.realpath(self.tmp), self.out)


class RealPlan(unittest.TestCase):
    def test_the_real_release_plan_is_accepted_by_the_helper(self):
        sys.path.insert(0, os.path.join(ROOT, 'tools', 'release'))
        import plan as planmod
        plan, _, problems = planmod.make_plan('HEAD')
        self.assertEqual([], problems)
        tmp = tempfile.mkdtemp()
        try:
            path = os.path.join(tmp, 'release-plan.json')
            data = json.dumps(plan, ensure_ascii=False, indent=1).encode()
            with open(path, 'wb') as fh:
                fh.write(data)
            got = deploy.load_plan(path, rl.sha256_bytes(data))
            self.assertEqual(len(plan['steps']), len(got['steps']))
            self.assertEqual('omochix-core.php', plan['steps'][deploy.gate_index(got, 'A') - 1]['path'])
        finally:
            shutil.rmtree(tmp)


if __name__ == '__main__':
    unittest.main()
