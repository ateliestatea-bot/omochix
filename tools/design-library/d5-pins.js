#!/usr/bin/env node
/**
 * Design Library D5: what a "verified-production" work must be able to show, and the pins
 * (SHA-256 of the product files) that tie the D5 staging evidence to the exact implementation.
 *
 * Production may serve a work's live demo and index it only when the catalog says
 * "verified-production" (inc/design-library.php, omochix_design_verified_here). That state is
 * allowed only with D5 evidence for that work (tests/design-library/d5-evidence.json):
 *   - one entry with the same id and slug;
 *   - P1 staging isolation PASS and P2 staging cache PASS, and the P3 promotion approval id;
 *   - the same verification date as the catalog;
 *   - item_sha = SHA-256 of the work's catalog entry without "verification" (so a changed
 *     entry, demo hash, asset path or video field invalidates the evidence);
 *   - files = exactly the pinned product files of the work, each with the SHA-256 it has now
 *     (so any change after D5 to the demo endpoint, the gate, the CSP, the templates, the
 *     demo sources or the media invalidates the evidence: re-run the affected D5 tests);
 *   - until P4 (production B1/B9 right after G14) is PASS, the catalog keeps PENDING_LINE in
 *     the work's limitations, so "verified-production" never reads as "production verified".
 * A staging fixture (FIXTURE_MARK in limitations) must never be shipped.
 *
 *   node tools/design-library/d5-pins.js pins [slug ...]                      # pins of the current tree (JSON)
 *   node tools/design-library/d5-pins.js fixture --promote a,b --out F        # staging-only fixture catalog
 *   node tools/design-library/d5-pins.js release-candidate --checked D --out F # 12-work release candidate (local only)
 *
 * No dependencies. Used by tests/design-library/catalog-check.js.
 */
'use strict';
var fs = require('fs');
var path = require('path');
var crypto = require('crypto');
var hash = require('./demo-hash.js');

var THEME = hash.THEME;
var EVIDENCE = path.resolve(__dirname, '../../tests/design-library/d5-evidence.json');
var PENDING_LINE = '本番のドメインでの B1/B9 は、公開の直後に確かめる（未完了）';
var FIXTURE_MARK = 'STAGING FIXTURE: D5 staging only, not production evidence';
/* Files every work depends on: the gate and publish decision, the demo endpoint and its CSP,
   the catalog lock, the shared runtime, the schema and the templates, scripts and styles. */
var COMMON = [
    'inc/design-library.php',
    'inc/design-library-demo.php',
    'assets/data/design/.htaccess',
    'assets/data/design/catalog.schema.json',
    'assets/design/runtime/demo-runtime.js',
    'page-design.php',
    'template-parts/design-library/card.php',
    'template-parts/design-library/detail.php',
    'template-parts/design-library/filter-bar.php',
    'assets/js/design-library.js',
    'assets/js/design-library-preview.js',
    'assets/css/design-library.css'
];

function sha(buf) { return crypto.createHash('sha256').update(buf).digest('hex'); }
function fileSha(rel) { var f = path.join(THEME, rel); return fs.existsSync(f) ? sha(fs.readFileSync(f)) : null; }
function canonical(v) {
    if (Array.isArray(v)) return '[' + v.map(canonical).join(',') + ']';
    if (v && typeof v === 'object') return '{' + Object.keys(v).sort().map(function (k) { return JSON.stringify(k) + ':' + canonical(v[k]); }).join(',') + '}';
    return JSON.stringify(v);
}
/** SHA-256 of the catalog entry without "verification" (key order does not matter). */
function itemSha(item) {
    var c = JSON.parse(JSON.stringify(item));
    delete c.verification;
    return sha(Buffer.from(canonical(c), 'utf8'));
}
function walk(dir, base, out) {
    if (!fs.existsSync(dir)) return out;
    fs.readdirSync(dir, { withFileTypes: true }).forEach(function (e) {
        var full = path.join(dir, e.name), rel = path.posix.join(base, e.name);
        if (e.isDirectory()) walk(full, rel, out); else if (e.isFile()) out.push(rel);
    });
    return out;
}
/** The product files a work's D5 evidence pins (theme-relative, sorted). */
function itemFiles(item) {
    var files = COMMON.slice();
    ['poster', 'posterFallback', 'loop', 'loopFallback'].forEach(function (k) { if (item[k]) files.push('assets/design/' + item[k]); });
    if (item.video) { files.push('assets/design/' + item.video.mp4); if (item.video.webm) files.push('assets/design/' + item.video.webm); }
    if (item.medium === 'web') walk(path.join(THEME, 'assets/design/demos', item.slug), 'assets/design/demos/' + item.slug, files);
    return files.filter(function (f, i) { return files.indexOf(f) === i; }).sort();
}
function pinsFor(item, shaOf) {
    shaOf = shaOf || fileSha;
    var files = {};
    itemFiles(item).forEach(function (f) { files[f] = shaOf(f); });
    return { id: item.id, slug: item.slug, item_sha: itemSha(item), files: files };
}
function loadEvidence(file) { return JSON.parse(fs.readFileSync(file || EVIDENCE, 'utf8')); }

/**
 * Errors for "verified-production" claims and fixture leaks. Empty = the catalog is allowed.
 * shaOf(rel) gives the current SHA-256 of a theme file (tests pass a fake one).
 */
function productionErrors(catalog, evidence, shaOf) {
    shaOf = shaOf || fileSha;
    var errs = [];
    var ev = evidence && Array.isArray(evidence.items) ? evidence.items : null;
    if (!evidence || evidence.version !== 1 || !ev) return ['d5-evidence: must be {version: 1, items: [...]}'];
    var ids = {};
    ev.forEach(function (e, i) {
        if (!e || typeof e.id !== 'string') { errs.push('d5-evidence.items[' + i + ']: no id'); return; }
        if (ids[e.id]) errs.push('d5-evidence: duplicate entry for ' + e.id);
        ids[e.id] = (ids[e.id] || 0) + 1;
        if (!catalog.items.some(function (it) { return it.id === e.id; })) errs.push('d5-evidence: ' + e.id + ' is not in the catalog');
    });
    catalog.items.forEach(function (it) {
        var ver = it.verification || {}, lim = Array.isArray(ver.limitations) ? ver.limitations : [];
        var at = it.id + ' (' + it.slug + ')';
        if (lim.indexOf(FIXTURE_MARK) !== -1) errs.push(at + ': staging fixture catalog (must never ship)');
        if (ver.state !== 'verified-production') return;
        var mine = ev.filter(function (e) { return e && e.id === it.id; });
        if (mine.length !== 1) { errs.push(at + ': verified-production without exactly one D5 evidence entry'); return; }
        var e = mine[0];
        if (e.slug !== it.slug) errs.push(at + ': evidence slug ' + JSON.stringify(e.slug) + ' differs');
        if (!e.staging || e.staging.p1 !== 'PASS') errs.push(at + ': P1 staging isolation is not PASS');
        if (!e.staging || e.staging.p2 !== 'PASS') errs.push(at + ': P2 staging cache is not PASS');
        if (!e.promotion || typeof e.promotion.p3 !== 'string' || !/^[A-Za-z0-9][A-Za-z0-9._:#-]{2,63}$/.test(e.promotion.p3)) errs.push(at + ': no P3 promotion approval id');
        if (typeof e.checked !== 'string' || e.checked !== ver.checked) errs.push(at + ': evidence date differs from verification.checked');
        if (e.item_sha !== itemSha(it)) errs.push(at + ': catalog entry changed after D5 (item_sha)');
        var want = itemFiles(it), got = e.files && typeof e.files === 'object' ? Object.keys(e.files).sort() : [];
        if (JSON.stringify(want) !== JSON.stringify(got)) errs.push(at + ': evidence does not pin exactly the work\'s product files');
        want.forEach(function (f) {
            if (!e.files || e.files[f] !== shaOf(f)) errs.push(at + ': ' + f + ' changed after D5 (re-run the affected D5 tests)');
        });
        var p4 = e.production && e.production.p4;
        if (p4 !== 'PENDING' && p4 !== 'PASS') errs.push(at + ': production.p4 must be PENDING or PASS');
        if (p4 !== 'PASS' && lim.indexOf(PENDING_LINE) === -1) errs.push(at + ': limitations must say production B1/B9 is still pending');
    });
    return errs;
}

/** Staging-only fixture: the listed works become verified-production with FIXTURE_MARK. */
function stagingFixture(catalog, slugs, checked) {
    var c = JSON.parse(JSON.stringify(catalog));
    slugs.forEach(function (s) { if (!c.items.some(function (it) { return it.slug === s; })) throw new Error('unknown slug ' + s); });
    c.items.forEach(function (it) {
        if (slugs.indexOf(it.slug) === -1) return;
        it.verification.state = 'verified-production';
        if (checked) it.verification.checked = checked;
        it.verification.limitations = [FIXTURE_MARK].concat(it.verification.limitations.filter(function (l) { return l !== FIXTURE_MARK; })).slice(0, 8);
    });
    return c;
}
/** Release candidate (kept local until D5 PASS): every work verified-production, PENDING_LINE first. */
function releaseCandidate(catalog, checked, slugs) {
    var c = JSON.parse(JSON.stringify(catalog));
    c.items.forEach(function (it) {
        if (slugs && slugs.indexOf(it.slug) === -1) return;
        it.verification.state = 'verified-production';
        it.verification.checked = checked;
        it.verification.limitations = [PENDING_LINE].concat(it.verification.limitations.filter(function (l) { return l !== PENDING_LINE && l !== FIXTURE_MARK; })).slice(0, 8);
    });
    return c;
}

module.exports = {
    PENDING_LINE: PENDING_LINE, FIXTURE_MARK: FIXTURE_MARK, COMMON: COMMON, EVIDENCE: EVIDENCE,
    itemSha: itemSha, itemFiles: itemFiles, pinsFor: pinsFor, fileSha: fileSha, loadEvidence: loadEvidence,
    productionErrors: productionErrors, stagingFixture: stagingFixture, releaseCandidate: releaseCandidate
};

if (require.main === module) {
    var argv = process.argv.slice(2), cmd = argv[0];
    var opt = function (k) { var i = argv.indexOf(k); return i === -1 ? null : argv[i + 1]; };
    var catalog = JSON.parse(fs.readFileSync(hash.CATALOG, 'utf8'));
    var writeNew = function (f, data) {
        if (!f) { console.error('--out is required'); process.exit(2); }
        var fd = fs.openSync(f, 'wx', 0o600); // never overwrite
        fs.writeSync(fd, JSON.stringify(data, null, 2) + '\n'); fs.closeSync(fd);
        console.log(f + ' ' + sha(fs.readFileSync(f)));
    };
    if (cmd === 'pins') {
        var want = argv.slice(1);
        var out = catalog.items.filter(function (it) { return !want.length || want.indexOf(it.slug) !== -1; }).map(function (it) { return pinsFor(it); });
        console.log(JSON.stringify({ common: COMMON, items: out }, null, 2));
    } else if (cmd === 'fixture') {
        writeNew(opt('--out'), stagingFixture(catalog, (opt('--promote') || '').split(',').filter(Boolean), opt('--checked')));
    } else if (cmd === 'release-candidate') {
        var d = opt('--checked');
        if (!/^\d{4}-\d{2}-\d{2}$/.test(d || '')) { console.error('--checked YYYY-MM-DD is required'); process.exit(2); }
        writeNew(opt('--out'), releaseCandidate(catalog, d));
    } else {
        console.error('usage: d5-pins.js pins [slug ...] | fixture --promote a,b [--checked D] --out F | release-candidate --checked D --out F');
        process.exit(2);
    }
}
