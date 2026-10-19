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

fx.combined.forEach(function (c, i) {
    c.slots.forEach(function (slot, k) {
        var defs = fx.catalog.items[slot.slug].params;
        var values = P.sanitize(defs, slot.params);
        check('combined ' + i + ' slot ' + (k + 1) + ': scoped block', core.scopedBlock(P, defs, values, k + 1), c.blocks[k].scoped);
        check('combined ' + i + ' slot ' + (k + 1) + ': js params', core.jsParams(P, defs, values), c.blocks[k].js);
    });
    check('combined ' + i + ': document', core.buildCombinedDoc(fx.catalog, P, c.slots, '組み合わせプレビュー'), c.doc);
});

check('brief tokens: token block', core.tokenBlock(2), fx.tokenBlock);
check('brief tokens: token list', core.TOKENS, fx.tokens);
check('brief tokens: dark list', core.DARK_TOKENS, fx.darkTokens);
check('brief tokens: comment', core.TOKEN_COMMENT, fx.tokenComment);
fx.briefJsParams.forEach(function (c, i) {
    var item = fx.catalog.items[c.slug];
    check('brief js params ' + i + ' (' + c.slug + ')', core.briefJsParams(P, item, item.params, P.sanitize(item.params, c.params)), c.out);
});
fx.briefHtml.forEach(function (c, i) {
    var item = fx.catalog.items[c.slug];
    check('brief html ' + i + ' (' + c.slug + ')', core.briefHtml(P, item, item.params, P.sanitize(item.params, c.params)), c.out);
});

var ctx = fx.briefCtx;
ctx.items = fx.catalog.items;
fx.brief.forEach(function (c, i) {
    check('brief ' + i + ' (' + c.assistant + ')', core.buildBrief(ctx, P, c.in, c.assistant), c.out);
});

process.stdout.write(pass + ' passed, ' + fail + ' failed\n');
process.exit(fail ? 1 : 0);
