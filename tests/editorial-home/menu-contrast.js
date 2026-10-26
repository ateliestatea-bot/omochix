/**
 * Static regression guard for the mobile menu's open-state contrast.
 * Run: node tests/editorial-home/menu-contrast.js
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const theme = path.resolve(__dirname, '../../wp-theme/omochix-theme');
const read = (file) => fs.readFileSync(path.join(theme, file), 'utf8');
const base = read('style.css');
const home = read('assets/css/editorial-home.css');
const shell = read('assets/css/shell.css');
const header = read('header.php');

assert.match(base, /body\.has-open-menu\s+\.site-header__menu-button\s*\{\s*color:\s*#fff;\s*background:\s*#151515;/);
assert.match(home, /@media\s*\(max-width:\s*1080px\)\s*\{\s*body\.omx-editorial\.has-open-menu\s+\.site-header\s+\.site-header__menu-button\s*\{\s*color:\s*#fff;/);
assert.match(shell, /body\.omx-shell\.has-open-menu\s+\.site-header\s+\.site-header__menu-button\s*\{\s*color:\s*#fff;/);
assert.match(header, /class="icon-button site-header__menu-button"[^>]+data-menu-toggle/);

for (const [name, css] of [['style.css', base], ['editorial-home.css', home], ['shell.css', shell]]) {
  const opens = (css.match(/\{/g) || []).length;
  const closes = (css.match(/\}/g) || []).length;
  assert.equal(opens, closes, `${name} has unbalanced braces`);
}

console.log('PASS: open-state menu contrast rules for shared, homepage and shell headers');
console.log('PASS: CSS braces and shared menu button hook');
