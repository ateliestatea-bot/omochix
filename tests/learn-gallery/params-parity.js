#!/usr/bin/env node
/**
 * PHP ⇄ JS parity for playground parameters.
 *
 * inc/learn-gallery.php and assets/js/learn-params.js both sanitize values,
 * build the :root / window.omxParams blocks and fill the prompt and code
 * templates. The preview, the copied code and the prompt must not depend on
 * which side assembled them, so this feeds the same raw inputs to both and
 * compares every output byte for byte.
 *
 * Run: node tests/learn-gallery/params-parity.js   (needs php on PATH)
 */
'use strict';

var path = require('path');
var spawnSync = require('child_process').spawnSync;

var root = path.resolve(__dirname, '..', '..');
var P = require(path.join(root, 'wp-theme/omochix-theme/assets/js/learn-params.js'));

var php = spawnSync('php', [path.join(__dirname, 'params-fixture.php')], { encoding: 'utf8' });
if (php.status !== 0) {
    process.stderr.write('params-fixture.php failed:\n' + (php.stderr || php.stdout));
    process.exit(2);
}
var fixture = JSON.parse(php.stdout);

var pass = 0;
var fail = 0;
function check(name, actual, expected) {
    var ok = JSON.stringify(actual) === JSON.stringify(expected);
    if (ok) { pass++; return; }
    fail++;
    process.stdout.write('FAIL ' + name + '\n  js : ' + JSON.stringify(actual) + '\n  php: ' + JSON.stringify(expected) + '\n');
}

var slugs = Object.keys(fixture);
if (!slugs.length) { process.stdout.write('FAIL no adjustable samples in the fixture\n'); process.exit(1); }

slugs.forEach(function (slug) {
    var entry = fixture[slug];
    var defs = entry.defs;
    entry.cases.forEach(function (c, i) {
        var label = slug + ' case ' + i;
        var values = P.sanitize(defs, c.raw);
        check(label + ': sanitize', values, c.values);
        var blocks = P.blocks(defs, values);
        check(label + ': css block', blocks.css, c.css);
        check(label + ': js block', blocks.jsBlock, c.js);
        check(label + ': prompt lines', P.text(defs, values), c.text);
        check(label + ': code', P.fillTemplate(entry.codeTemplate, {
            '{{params_css}}': blocks.css ? blocks.css + '\n' : '',
            '{{params_js}}': blocks.jsBlock ? blocks.jsBlock + '\n' : ''
        }), c.code);
        check(label + ': prompt', P.fillTemplate(entry.promptTemplate, { '{{params_text}}': P.text(defs, values) }), c.prompt);
        // The hash round-trips: parse(serialize(values)) sanitizes back to the same values.
        var hash = P.toHash(defs, values);
        check(label + ': hash round trip', P.sanitize(defs, P.fromHash(hash)), values);
        check(label + ': hash empty only at the defaults', hash === '', P.isDefault(defs, values));
    });
});

process.stdout.write(pass + ' passed, ' + fail + ' failed (' + slugs.length + ' samples)\n');
process.exit(fail ? 1 : 0);
