"""The release gate (tests/release/release-gate.php) must pass the real releases and fail on purpose-made faults.

Each fault runs on a throwaway copy: a git export of the release commit (wp-plugin/, wp-theme/) and a copy of
tools/release/ and tests/release/expected/. Nothing in the repository is changed.
Run: python3 -m unittest discover -s tests/release -v   (needs php and git)
"""
import json
import os
import shutil
import subprocess
import tempfile
import unittest

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
GATE = os.path.join(ROOT, 'tests', 'release', 'release-gate.php')
HUB = 'industry-hub-core-1.10.0-theme-1.29.0.json'
THEME = 'wp-theme/omochix-theme'
CORE = 'wp-plugin/omochix-core'


def expected(name):
    with open(os.path.join(ROOT, 'tests', 'release', 'expected', name), encoding='utf-8') as fh:
        return json.load(fh)


@unittest.skipUnless(shutil.which('php') and shutil.which('git'), 'needs php and git')
class ReleaseGate(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        self.repo = os.path.join(self.tmp, 'repo')
        shutil.copytree(os.path.join(ROOT, 'tools', 'release'), os.path.join(self.repo, 'tools', 'release'),
                        ignore=shutil.ignore_patterns('__pycache__'))
        shutil.copytree(os.path.join(ROOT, 'tests', 'release', 'expected'), os.path.join(self.repo, 'tests', 'release', 'expected'))

    def tearDown(self):
        shutil.rmtree(self.tmp)

    def tree(self, commit):
        out = os.path.join(self.tmp, 'tree-' + commit[:12])
        if not os.path.isdir(out):
            os.makedirs(out)
            data = subprocess.run(['git', '-C', ROOT, 'archive', commit, 'wp-plugin', 'wp-theme'], check=True, capture_output=True).stdout
            subprocess.run(['tar', '-x', '-C', out], input=data, check=True)
        return out

    def gate(self, tree, config=None):
        env = {k: v for k, v in os.environ.items() if not k.startswith('OMOCHIX_RELEASE_')}
        env.update(OMOCHIX_RELEASE_REPO=self.repo, OMOCHIX_RELEASE_TREE=tree)
        if config:
            env['OMOCHIX_RELEASE_CONFIG'] = config
        r = subprocess.run(['php', GATE], env=env, capture_output=True, text=True)
        return r.returncode, [ln for ln in r.stdout.splitlines() if ln.startswith('FAIL ')]

    def config_path(self, name=None):
        return os.path.join(self.repo, 'tools', 'release', *(('configs', name) if name else ('release.json',)))

    def edit_config(self, change, name=None):
        path = self.config_path(name)
        with open(path, encoding='utf-8') as fh:
            cfg = json.load(fh)
        change(cfg)
        with open(path, 'w', encoding='utf-8') as fh:
            json.dump(cfg, fh)

    def assertFails(self, result, *needles):
        code, fails = result
        self.assertNotEqual(0, code, 'the gate passed a broken release')
        for needle in needles:
            self.assertTrue(any(needle in f for f in fails), f'{needle!r} not in {fails}')

    # ------------------------------------------------------------------ the real releases pass
    def test_current_release_passes_on_its_commit(self):
        code, fails = self.gate(self.tree(expected('release.json')['commit']))
        self.assertEqual((0, []), (code, fails))

    def test_industry_hub_release_still_passes_on_its_commit(self):
        code, fails = self.gate(self.tree(expected(HUB)['commit']), HUB)
        self.assertEqual((0, []), (code, fails))

    def test_a_release_config_does_not_pass_on_another_release_tree(self):
        self.assertFails(self.gate(self.tree(expected('release.json')['commit']), HUB), 'theme: the expected version')

    # ------------------------------------------------------------------ faults in the tree
    def test_a_core_change_in_a_theme_only_release_fails(self):
        tree = self.tree('HEAD')
        with open(os.path.join(tree, CORE, 'includes', 'provider.php'), 'a', encoding='utf-8') as fh:
            fh.write('\n// changed\n')
        self.assertFails(self.gate(tree), 'core: byte-identical to production')

    def test_a_missing_release_file_fails(self):
        tree = self.tree('HEAD')
        os.remove(os.path.join(tree, THEME, 'inc', 'nav.php'))
        self.assertFails(self.gate(tree), 'planned but unchanged: theme:inc/nav.php', 'omochix-theme: 194 files', 'exactly the expected set')

    def test_an_extra_file_fails(self):
        tree = self.tree('HEAD')
        with open(os.path.join(tree, THEME, 'extra.php'), 'w', encoding='utf-8') as fh:
            fh.write('<?php\n')
        self.assertFails(self.gate(tree), 'not approved: theme:extra.php', 'exactly the expected set')

    def test_a_production_file_changed_outside_the_release_fails(self):
        tree = self.tree('HEAD')
        with open(os.path.join(tree, THEME, '404.php'), 'a', encoding='utf-8') as fh:
            fh.write('\n')
        self.assertFails(self.gate(tree), 'not approved: theme:404.php')

    def test_a_deleted_production_file_fails(self):
        tree = self.tree('HEAD')
        os.remove(os.path.join(tree, THEME, '404.php'))
        self.assertFails(self.gate(tree), 'nothing that production has is deleted')

    def test_a_labs_file_that_is_not_named_fails_even_when_the_config_approves_it(self):
        tree = self.tree('HEAD')
        with open(os.path.join(tree, THEME, 'page-labs.php'), 'a', encoding='utf-8') as fh:
            fh.write('\n')
        self.edit_config(lambda c: c['order'].insert(0, ['theme', 'page-labs.php']))
        self.assertFails(self.gate(tree), 'no Labs or SVG Motion file except', 'exactly the expected set', '43 steps')

    def test_a_symlink_in_the_package_fails(self):
        tree = self.tree('HEAD')
        os.symlink('/etc/hosts', os.path.join(tree, THEME, 'link.txt'))
        self.assertFails(self.gate(tree), 'omochix-theme: no symlinks')

    def test_an_extra_file_approved_in_the_config_alone_still_fails(self):
        # Changing the config together with the tree is not enough: the expectations are recorded separately.
        tree = self.tree('HEAD')
        with open(os.path.join(tree, THEME, 'extra.php'), 'w', encoding='utf-8') as fh:
            fh.write('<?php\n')
        self.edit_config(lambda c: c['order'].insert(0, ['theme', 'extra.php']))
        self.assertFails(self.gate(tree), '22 added and 21 replaced', '43 steps', 'exactly the expected set')

    # ------------------------------------------------------------------ faults in the config or the baseline
    def test_functions_php_after_a_template_that_needs_it_fails(self):
        def late(c):
            c['order'].remove(['theme', 'functions.php'])
            c['order'].insert(len(c['order']) - 1, ['theme', 'functions.php'])  # just before style.css
        self.edit_config(late)
        self.assertFails(self.gate(self.tree('HEAD')), 'theme:functions.php after every add and before every replace')

    def test_functions_php_before_a_new_file_fails(self):
        def early(c):
            c['order'].remove(['theme', 'functions.php'])
            c['order'].insert(0, ['theme', 'functions.php'])
        self.edit_config(early)
        self.assertFails(self.gate(self.tree('HEAD')), 'theme:functions.php after every add and before every replace')

    def test_a_switch_file_that_is_not_last_fails(self):
        def moved(c):
            c['order'].remove(['theme', 'style.css'])
            c['order'].insert(0, ['theme', 'style.css'])
        self.edit_config(moved)
        self.assertFails(self.gate(self.tree('HEAD')), 'switch file theme:style.css comes after every new file')

    def test_an_incomplete_rollback_fails(self):
        self.edit_config(lambda c: c['rollback_order'].pop())
        self.assertFails(self.gate(self.tree('HEAD')), 'rollback: restores exactly the replaced files', 'rollback: the expected file first and last')

    def test_a_core_package_added_to_a_theme_only_release_fails(self):
        hub = expected(HUB)
        self.edit_config(lambda c: c['packages'].update(core={'name': 'omochix-core', 'dir': CORE, 'target': 'plugins/omochix-core',
                                                              'baseline': 'tools/release/baseline/omochix-core-1.9.3.sha256', 'baseline_version': '1.9.3'}))
        self.assertTrue(hub['packages']['core'])
        self.assertFails(self.gate(self.tree('HEAD')), 'scope: the release ships exactly the expected packages', 'core: not part of this release')

    def test_a_tampered_baseline_hash_fails(self):
        path = os.path.join(self.repo, 'tools', 'release', 'baseline', 'omochix-theme-1.29.0.sha256')
        with open(path, encoding='utf-8') as fh:
            lines = fh.readlines()
        lines[0] = ('0' * 64) + lines[0][64:]
        with open(path, 'w', encoding='utf-8') as fh:
            fh.writelines(lines)
        self.assertFails(self.gate(self.tree('HEAD')), 'omochix-theme: baseline file is the expected production manifest')

    def test_a_tampered_core_baseline_fails(self):
        path = os.path.join(self.repo, 'tools', 'release', 'baseline', 'omochix-core-1.10.0.sha256')
        with open(path, 'a', encoding='utf-8') as fh:
            fh.write(('1' * 64) + '  extra.php\n')
        self.assertFails(self.gate(self.tree('HEAD')), 'core: baseline file is the expected production manifest', 'core: byte-identical to production')

    def test_a_config_name_with_a_path_is_refused(self):
        self.assertFails(self.gate(self.tree('HEAD'), '../release.json'), 'OMOCHIX_RELEASE_CONFIG must be a file name')

    def test_a_config_without_expectations_is_refused(self):
        shutil.copy(self.config_path(), self.config_path('unrecorded.json'))
        self.assertFails(self.gate(self.tree('HEAD'), 'unrecorded.json'), 'cannot read unrecorded.json')


if __name__ == '__main__':
    unittest.main()
