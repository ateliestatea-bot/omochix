#!/usr/bin/env node
/**
 * Design Library D5: the G12 promotion rule ("g12-no-promotion") and the product-file pins.
 *
 * Production may serve a work's live demo and index it only when the catalog says
 * "verified-production" (inc/design-library.php, omochix_design_verified_here). The D5 plan in force
 * is D5 v3 (docs/design-library/D5-Production-Isolation.md): local L1 tests (B1-B10), a pre-tested
 * kill switch, G14a with 0 promoted works, then one canary work verified in production (P4).
 * There is no staging server: the earlier staging plan (P1 staging isolation, P2 staging cache) was
 * cancelled, and its verdicts must never be filled in.
 *
 * Release 1.33.0 (G12) allows NO promotion. productionErrors() is fail-closed:
 *   - any work in state "verified-production" is an error, whatever evidence is present;
 *   - the evidence file must be exactly {version: 1, rule: "g12-no-promotion", items: []};
 *   - a staging-fixture line in any work's limitations is an error (normalised match).
 * When something does claim verified-production, the pins and the pending line are still checked,
 * so the errors also say what else is wrong (the tests assert each expected cause).
 * The canary rule (evidence rule v2) comes in its own pull request. It must bind each entry to the
 * commit, the product-file pins, the test environment and the L1 / P4 records; a string such as
 * "PASS" is never taken as proof on its own.
 *
 * Pins: the SHA-256 of the product files a work's delivery depends on (functions.php loads the
 * Design Library; the gate, demo endpoint, CSP, catalog lock, runtime, schema, templates, scripts,
 * styles; the work's own demo sources and media). Each pin must be 64 lowercase hex characters.
 * hookErrors() refuses shipped code that registers the catalog / asset filters, touches the catalog
 * cache global or writes the filter table, any of which could change a verification state at runtime.
 *
 *   node tools/design-library/d5-pins.js pins [slug ...]   # pins of the current tree (JSON)
 *
 * No dependencies. Used by tests/design-library/catalog-check.js.
 */
'use strict';
var fs = require('fs');
var path = require('path');
var crypto = require('crypto');
var hash = require('./demo-hash.js');

var THEME = hash.THEME;
var REPO = path.resolve(THEME, '../..');
var EVIDENCE = path.resolve(__dirname, '../../tests/design-library/d5-evidence.json');
var RULE = 'g12-no-promotion';
var PENDING_LINE = '本番のドメインでの B1/B9 は、公開の直後に確かめる（未完了）';
var FIXTURE_MARK = 'STAGING FIXTURE: D5 staging only, not production evidence';
var SHA_RE = /^[0-9a-f]{64}$/;
var HOOKS = ['omochix_design_catalog_data', 'omochix_design_catalog_raw', 'omochix_design_catalog_file', 'omochix_design_asset_file'];
var COMMON = [
    'functions.php',
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
function fileSha(rel) { var f = path.join(THEME, rel); return fs.existsSync(f) && fs.statSync(f).isFile() ? sha(fs.readFileSync(f)) : null; }
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
/** The product files a work's evidence pins (theme-relative, sorted). */
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
/**
 * Errors of a set of pins for one work: exactly the work's product files, each a 64-character
 * lowercase hex SHA-256 equal to the file's current hash (a missing file never matches).
 */
function pinErrors(item, files, shaOf) {
    shaOf = shaOf || fileSha;
    var errs = [], at = item.id + ' (' + item.slug + ')';
    var want = itemFiles(item), got = files && typeof files === 'object' && !Array.isArray(files) ? Object.keys(files).sort() : [];
    if (JSON.stringify(want) !== JSON.stringify(got)) errs.push(at + ': pins are not exactly the work\'s product files');
    want.forEach(function (f) {
        var v = files && typeof files === 'object' ? files[f] : undefined, now = shaOf(f);
        if (typeof v !== 'string' || !SHA_RE.test(v)) errs.push(at + ': pin for ' + f + ' is not a 64-character hex SHA-256');
        else if (now === null || now === undefined) errs.push(at + ': ' + f + ' is missing from the tree');
        else if (v !== now) errs.push(at + ': ' + f + ' changed after it was pinned');
    });
    return errs;
}
/**
 * Staging-fixture marks: after compatibility decomposition, accents and every character that is not a
 * letter are removed (case, width, separators, dashes, zero-width and invisible characters), and a line
 * naming both "staging" and "fixture" is a mark. Look-alike letters from other scripts cannot be caught
 * in general; the fixture-writing tool is gone, so a mark can only come from a deliberate edit.
 */
function isFixtureLine(line) {
    if (typeof line !== 'string') return false;
    var n = line.normalize('NFKD').replace(/\p{M}/gu, '').toLowerCase().replace(/[^\p{L}]/gu, '');
    return n.indexOf('staging') !== -1 && n.indexOf('fixture') !== -1;
}
/** Every string in a value (keys included). */
function strings(v, out) {
    if (typeof v === 'string') out.push(v);
    else if (Array.isArray(v)) v.forEach(function (x) { strings(x, out); });
    else if (v && typeof v === 'object') Object.keys(v).forEach(function (k) { strings(k, out); strings(v[k], out); });
    return out;
}
function loadEvidence(file) { return JSON.parse(fs.readFileSync(file || EVIDENCE, 'utf8')); }

/** Errors for the G12 rule (fail-closed). Empty = the catalog is allowed. */
function productionErrors(catalog, evidence, shaOf) {
    shaOf = shaOf || fileSha;
    var errs = [];
    if (!evidence || evidence.version !== 1 || evidence.rule !== RULE || !Array.isArray(evidence.items)) {
        errs.push('d5-evidence: must be {version: 1, rule: "' + RULE + '", items: []}');
    } else if (evidence.items.length) {
        errs.push('d5-evidence: rule ' + RULE + ' accepts no evidence entries (the canary rule v2 comes in its own pull request)');
    }
    var keys = evidence && typeof evidence === 'object' ? Object.keys(evidence).filter(function (k) { return ['version', 'rule', 'note', 'items'].indexOf(k) === -1; }) : [];
    if (keys.length) errs.push('d5-evidence: unknown keys ' + keys.join(', '));
    if (evidence && typeof evidence === 'object' && 'note' in evidence && typeof evidence.note !== 'string') errs.push('d5-evidence: note must be a string');
    catalog.items.forEach(function (it) {
        var ver = it.verification || {}, lim = Array.isArray(ver.limitations) ? ver.limitations : [];
        var at = it.id + ' (' + it.slug + ')';
        if (!Array.isArray(ver.limitations)) errs.push(at + ': verification.limitations must be a list');
        if (strings(it, []).some(isFixtureLine)) errs.push(at + ': staging fixture line in the entry (must never ship)');
        if (ver.state !== 'verified-production') return;
        errs.push(at + ': verified-production is not allowed in G12 (rule ' + RULE + '; promotion needs evidence rule v2)');
        if (lim.indexOf(PENDING_LINE) === -1) errs.push(at + ': limitations must say production B1/B9 is still pending');
        var entry = (evidence && Array.isArray(evidence.items) ? evidence.items : []).filter(function (e) { return e && e.id === it.id; })[0];
        if (!entry) { errs.push(at + ': no evidence entry'); return; }
        if (entry.slug !== it.slug) errs.push(at + ': evidence slug differs');
        if (entry.item_sha !== itemSha(it)) errs.push(at + ': catalog entry changed after it was pinned (item_sha)');
        pinErrors(it, entry.files, shaOf).forEach(function (e) { errs.push(e); });
    });
    return errs;
}

/**
 * Shipped code (theme and Core plugin) that could change a work's catalog entry at runtime:
 *   - a quoted catalog / asset filter name, in any case, anywhere except the 4 lower-case
 *     apply_filters() calls in inc/design-library.php (so add_filter in any spelling, named
 *     arguments, call_user_func, comments between the parenthesis and the name);
 *   - a reference to the catalog cache global ("omochix_design_catalog") outside that file;
 *   - a write to the WordPress filter table ($wp_filter or $GLOBALS['wp_filter']); reading it is allowed.
 * Hook names built at runtime (concatenation, variables) cannot be found statically: code review and
 * the release diff stay the control for those.
 * files: optional {relativePath: source} to scan instead of the tree (tests).
 */
var HOME_FILE = 'wp-theme/omochix-theme/inc/design-library.php';
function hookErrors(files) {
    if (!files) {
        files = {};
        ['wp-theme', 'wp-plugin'].forEach(function (top) {
            walk(path.join(REPO, top), top, []).filter(function (f) { return /\.(php|inc|phtml)$/i.test(f); }).forEach(function (f) { files[f] = fs.readFileSync(path.join(REPO, f), 'utf8'); });
        });
    }
    var errs = [], allowed = {};
    var nameRe = new RegExp('([\'"])(' + HOOKS.join('|') + ')\\1', 'gi');
    var cacheRe = /(['"])omochix_design_catalog\1|\$omochix_design_catalog\b/i;
    var registerRe = /\b(add_filter|add_action|remove_filter|remove_all_filters|has_filter|call_user_func(_array)?)\s*\(/i;
    var writeRe = /(\$wp_filter\b|\[\s*(['"])wp_filter\2\s*\])[^;=]*?((?<![!<>=])=(?![=>])|\?\?=)|\bunset\s*\(\s*\$(GLOBALS\s*\[\s*(['"])wp_filter\5|wp_filter)|(?<!&)&(?!&)\s*\$(GLOBALS\s*\[\s*(['"])wp_filter\7|wp_filter\b)|wp_filter\b.*(->\s*callbacks|->\s*add_filter\s*\()/i;
    Object.keys(files).sort().forEach(function (f) {
        String(files[f]).split('\n').forEach(function (line, i) {
            var m, at = f + ':' + (i + 1), names = [];
            nameRe.lastIndex = 0;
            while ((m = nameRe.exec(line))) names.push(m[2]);
            names.forEach(function (n) {
                var ok = f === HOME_FILE && names.length === 1 && n === n.toLowerCase() && !registerRe.test(line)
                    && new RegExp('\\bapply_filters\\s*\\(\\s*([\'"])' + n + '\\1').test(line);
                if (ok) allowed[n] = (allowed[n] || 0) + 1;
                else errs.push(at + ': filter name ' + n + ' outside the apply_filters() calls of inc/design-library.php');
            });
            if (f !== HOME_FILE && cacheRe.test(line)) errs.push(at + ': touches the catalog cache global omochix_design_catalog');
            if (writeRe.test(line)) errs.push(at + ': writes the WordPress filter table');
        });
    });
    if (files[HOME_FILE] !== undefined) {
        HOOKS.forEach(function (h) {
            if (allowed[h] !== 1) errs.push(HOME_FILE + ': expected exactly one apply_filters() call for ' + h + ', found ' + (allowed[h] || 0));
        });
    }
    return errs;
}

module.exports = {
    RULE: RULE, PENDING_LINE: PENDING_LINE, FIXTURE_MARK: FIXTURE_MARK, COMMON: COMMON, EVIDENCE: EVIDENCE, HOOKS: HOOKS,
    itemSha: itemSha, itemFiles: itemFiles, pinsFor: pinsFor, pinErrors: pinErrors, fileSha: fileSha, loadEvidence: loadEvidence,
    isFixtureLine: isFixtureLine, productionErrors: productionErrors, hookErrors: hookErrors
};

if (require.main === module) {
    var argv = process.argv.slice(2);
    if (argv[0] !== 'pins') {
        console.error('usage: d5-pins.js pins [slug ...]   (G12 writes no promoted or fixture catalog)');
        process.exit(2);
    }
    var catalog = JSON.parse(fs.readFileSync(hash.CATALOG, 'utf8'));
    var want = argv.slice(1);
    var out = catalog.items.filter(function (it) { return !want.length || want.indexOf(it.slug) !== -1; }).map(function (it) { return pinsFor(it); });
    console.log(JSON.stringify({ rule: RULE, common: COMMON, items: out }, null, 2));
}
