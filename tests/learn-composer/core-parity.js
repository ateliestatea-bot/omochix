#!/usr/bin/env node
/**
 * PHP ⇄ JS parity for the composer model: inc/learn-composer.php and
 * assets/js/learn-composer-core.js must sanitize, serialize and parse a
 * composition identically. Run: node tests/learn-composer/core-parity.js
 */
'use strict';

var path = require('path');
var spawnSync = require('child_process').spawnSync;

var root = path.resolve(__dirname, '..', '..');
var P = require(path.join(root, 'wp-theme/omochix-theme/assets/js/learn-params.js'));
var core = require(path.join(root, 'wp-theme/omochix-theme/assets/js/learn-composer-core.js'));

var php = spawnSync('php', [path.join(__dirname, 'fixture.php')], { encoding: 'utf8' });
if (php.status !== 0) {
    process.stderr.write('fixture.php failed:\n' + (php.stderr || php.stdout));
    process.exit(2);
}
var fx = JSON.parse(php.stdout);
var items = fx.items;

var pass = 0;
var fail = 0;
function check(name, actual, expected) {
    var a = JSON.stringify(actual);
    var e = JSON.stringify(expected);
    if (a === e) { pass++; return; }
    fail++;
    process.stdout.write('FAIL ' + name + '\n  js : ' + a + '\n  php: ' + e + '\n');
}

// Normalize slot param objects' key order for comparison (PHP keeps catalog order; so does JS).
fx.raw.forEach(function (c, i) {
    var comp = core.sanitize(items, P, c.in);
    check('raw ' + i + ': sanitize', comp, c.sanitized);
    check('raw ' + i + ': hash', core.toHash(items, P, comp), c.hash);
});
fx.hash.forEach(function (c, i) {
    var comp = core.sanitize(items, P, core.parseHash(c.in));
    check('hash ' + i + ': parse+sanitize', comp, c.sanitized);
    check('hash ' + i + ': rehash', core.toHash(items, P, comp), c.rehash);
});

process.stdout.write(pass + ' passed, ' + fail + ' failed\n');
process.exit(fail ? 1 : 0);
