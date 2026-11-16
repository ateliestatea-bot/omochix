#!/usr/bin/env node
/**
 * Design Library: SHA-256 of each demo's sources (deliverables.html.sha256).
 *
 * The hash covers every file under assets/design/demos/{slug}/ as sorted
 * lines "relative/path<TAB>sha256(file)\n", so adding, removing or changing
 * any file changes it.
 *
 *   node tools/design-library/demo-hash.js           # print slug and hash, exit 1 if the catalog differs
 *   node tools/design-library/demo-hash.js --write   # update the catalog in place
 *
 * No dependencies. Used by tests/design-library/catalog-check.js.
 */
'use strict';
var fs = require('fs');
var path = require('path');
var crypto = require('crypto');

var THEME = path.resolve(__dirname, '../../wp-theme/omochix-theme');
var CATALOG = path.join(THEME, 'assets/data/design/catalog.json');

function sha(buf) { return crypto.createHash('sha256').update(buf).digest('hex'); }
function walk(dir, base, out) {
    fs.readdirSync(dir, { withFileTypes: true }).forEach(function (e) {
        var full = path.join(dir, e.name), rel = path.posix.join(base, e.name);
        if (e.isDirectory()) walk(full, rel, out); else if (e.isFile()) out.push(rel);
    });
    return out;
}
function demoHash(slug) {
    var dir = path.join(THEME, 'assets/design/demos', slug);
    if (!fs.existsSync(dir)) return null;
    var lines = walk(dir, '', []).sort().map(function (rel) { return rel + '\t' + sha(fs.readFileSync(path.join(dir, rel))) + '\n'; });
    return sha(lines.join(''));
}
module.exports = { demoHash: demoHash, THEME: THEME, CATALOG: CATALOG };

if (require.main === module) {
    var catalog = JSON.parse(fs.readFileSync(CATALOG, 'utf8'));
    var differ = 0;
    catalog.items.filter(function (item) { return item.deliverables && item.deliverables.html; }).forEach(function (item) { // Web works only
        var h = demoHash(item.slug);
        var cur = item.deliverables && item.deliverables.html ? item.deliverables.html.sha256 : '';
        if (h !== cur) differ++;
        console.log((h === cur ? 'OK   ' : 'DIFF ') + item.slug + ' ' + h);
        if (process.argv.indexOf('--write') !== -1 && h) item.deliverables.html.sha256 = h;
    });
    if (process.argv.indexOf('--write') !== -1) {
        fs.writeFileSync(CATALOG, JSON.stringify(catalog, null, 2) + '\n');
        console.log('catalog updated');
    } else if (differ) {
        process.exit(1);
    }
}
