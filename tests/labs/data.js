#!/usr/bin/env node
/**
 * OmochiX Labs data checks:
 *  - pricing rules give the same result in PHP (inc/labs.php) and in the
 *    browser (core/cost.js) for the shipped file and mutated rows, on
 *    several dates (the page re-filters with the browser's own date);
 *  - pricing.json / crawlers.json / catalog.json shape and sources.
 * Run: node tests/labs/data.js              (development)
 *      node tests/labs/data.js --release    (also requires the price review)
 */
'use strict';

var fs = require('fs');
var path = require('path');
var spawnSync = require('child_process').spawnSync;

var root = path.resolve(__dirname, '..', '..');
var theme = path.join(root, 'wp-theme/omochix-theme');
var Cost = require(path.join(theme, 'assets/js/labs/core/cost.js'));
var release = process.argv.indexOf('--release') !== -1;

var pass = 0;
var fail = 0;
function check(name, cond) {
    if (cond) { pass++; console.log('PASS ' + name); }
    else { fail++; console.log('FAIL ' + name); }
}
function read(name) { return JSON.parse(fs.readFileSync(path.join(theme, 'assets/data/labs', name + '.json'), 'utf8')); }

var pricing = read('pricing');
var crawlers = read('crawlers');
var catalog = read('catalog');

// ------------------------------------------------------------ PHP ⇄ JS
var cases = [
    { date: '2026-11-04', mutations: [] },
    { date: '2026-11-03', mutations: [] },
    { date: '2026-11-30', mutations: [] },
    { date: '2026-12-01', mutations: [] },
    { date: '2027-01-01', mutations: [] },
    { date: '2027-02-02', mutations: [] },
    { date: '2027-02-03', mutations: [] },
    { date: '2026-11-04', mutations: [[0, 'status', 'draft'], [1, 'source_url', 'https://example.com/'], [2, 'verified_at', '2026-11-09'], [3, 'input', '10'], [4, 'provider', 'nobody'], [5, 'long_context', { threshold_tokens: 0, input: 1, output: 1 }], [6, 'cached_input', -1], [7, 'valid_until', '2026-11-01']] },
    { date: '2026-11-04', mutations: [[8, 'valid_until', null], [9, 'valid_from', null]] },
    { date: '2026-11-04', mutations: [[10, 'valid_from', '2027-01-01'], [10, 'valid_until', '2026-12-01']] },
    { date: '2026-11-04', mutations: [[0, 'source_url', 'https://platform.claude.com.evil.test/x'], [1, 'source_url', 'http://platform.claude.com/x'], [2, 'id', 'Bad ID']] }
];
var php = spawnSync('php', [path.join(__dirname, 'fixture.php')], { input: JSON.stringify(cases), encoding: 'utf8' });
if (php.status !== 0) {
    console.log('FAIL fixture.php: ' + (php.stderr || php.stdout));
    process.exit(2);
}
var phpOut = JSON.parse(php.stdout);
check('hosts: PHP and JS allow the same source hosts', JSON.stringify(phpOut.hosts) === JSON.stringify(Cost.SOURCE_HOSTS));
cases.forEach(function (c, i) {
    var data = JSON.parse(JSON.stringify(pricing));
    c.mutations.forEach(function (m) {
        if (m[2] === null) delete data.models[m[0]][m[1]];
        else data.models[m[0]][m[1]] = m[2];
    });
    var view = Cost.view(data, c.date);
    var js = {
        view: view.models.map(function (m) { return m.id + '@' + m.input; }),
        hidden: view.hidden,
        active: Cost.active(view.models, c.date, view.max_age_days).map(function (m) { return m.id + '@' + m.input; })
    };
    var p = phpOut.results[i];
    var same = JSON.stringify(js.view) === JSON.stringify(p.view) && JSON.stringify(js.active) === JSON.stringify(p.active) && JSON.stringify(Object.keys(js.hidden).sort().map(function (k) { return k + js.hidden[k]; })) === JSON.stringify(Object.keys(p.hidden).sort().map(function (k) { return k + p.hidden[k]; }));
    if (!same) console.log('  php ' + JSON.stringify(p) + '\n  js  ' + JSON.stringify(js));
    check('parity case ' + (i + 1) + ' (' + c.date + ', ' + c.mutations.length + ' mutations)', same);
});
check('parity: the mutated case hides the right rows', JSON.stringify(phpOut.results[7].hidden) === JSON.stringify({ unverified: 2, source: 1, invalid: 4 }));

// ------------------------------------------------------------- pricing
check('pricing: schema, currency and unit', pricing.schema_version === 1 && pricing.currency === 'USD' && pricing.unit === 'per_1m_tokens' && pricing.max_age_days === 90);
check('pricing: every row is verified, dated and officially sourced', pricing.models.every(function (m) { return m.status === 'verified' && /^\d{4}-\d{2}-\d{2}$/.test(m.verified_at) && Cost.allowedSource(m.source_url); }));
check('pricing: input and output prices are separate non-negative numbers', pricing.models.every(function (m) { return typeof m.input === 'number' && typeof m.output === 'number' && m.input >= 0 && m.output >= 0 && m.output >= m.input; }));
check('pricing: every provider is declared', pricing.models.every(function (m) { return pricing.providers.some(function (p) { return p.slug === m.provider; }); }));
check('pricing: scheduled price changes do not overlap', (function () {
    var byId = {};
    pricing.models.forEach(function (m) { (byId[m.id] = byId[m.id] || []).push(m); });
    return Object.keys(byId).every(function (id) {
        var rows = byId[id];
        if (rows.length === 1) return true;
        return rows.length === 2 && rows[0].valid_until && rows[1].valid_from && rows[0].valid_until < rows[1].valid_from;
    });
}()));
check('pricing: scope of the listed prices is written down', pricing.scope && ['providers', 'tier', 'prices', 'schedule'].every(function (k) { return typeof pricing.scope[k] === 'string' && pricing.scope[k].length > 20; }));
check('pricing: review record present', pricing.review && ['pending', 'approved'].indexOf(pricing.review.status) !== -1);
if (release) {
    check('release: prices reviewed by a person', pricing.review.status === 'approved' && typeof pricing.review.reviewer === 'string' && pricing.review.reviewer !== '' && /^\d{4}-\d{2}-\d{2}$/.test(pricing.review.reviewed_at || ''));
} else if (pricing.review.status !== 'approved') {
    console.log('NOTE pricing.json review is "' + pricing.review.status + '": run with --release before 1.28.0 to enforce it.');
}

// ------------------------------------------------------------ crawlers
check('crawlers: tokens are valid and unique', (function () {
    var seen = {};
    return crawlers.crawlers.every(function (c) {
        var k = c.token.toLowerCase();
        var ok = /^[A-Za-z][A-Za-z0-9_-]{1,40}$/.test(c.token) && !seen[k];
        seen[k] = true;
        return ok;
    });
}()));
check('crawlers: each has an https source, a date and a known purpose', crawlers.crawlers.every(function (c) { return /^https:\/\//.test(c.source_url) && /^\d{4}-\d{2}-\d{2}$/.test(c.verified_at) && Object.prototype.hasOwnProperty.call(crawlers.purposes, c.purpose); }));
check('crawlers: user-initiated agents carry the robots.txt caveat where the operator states one', crawlers.crawlers.filter(function (c) { return c.token === 'ChatGPT-User' || c.token === 'Perplexity-User'; }).every(function (c) { return c.robots_note.length > 0; }));

// ------------------------------------------------------------- catalog
check('catalog: four published tools with unique slugs and titles', catalog.tools.length === 4 && new Set(catalog.tools.map(function (t) { return t.slug; })).size === 4 && new Set(catalog.tools.map(function (t) { return t.title; })).size === 4);
check('catalog: every tool has a UI script and a content template', catalog.tools.every(function (t) {
    return fs.existsSync(path.join(theme, 'assets/js/labs/ui', t.slug + '.js')) && fs.existsSync(path.join(theme, 'template-parts/labs/content', t.slug + '.php'));
}));

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
