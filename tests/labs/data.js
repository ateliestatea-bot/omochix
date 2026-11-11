#!/usr/bin/env node
/**
 * OmochiX Labs data checks:
 *  - pricing rules give the same result in PHP (inc/labs.php) and in the
 *    browser (core/cost.js) for the shipped file and mutated rows, on
 *    several dates (the page re-filters with the browser's own date);
 *  - pricing.json / crawlers.json / catalog.json shape and sources.
 * Run: node tests/labs/data.js              (development)
 *      node tests/labs/data.js --release    (release gate, see below)
 *
 * Release gate, set by the cost calculator's catalog status (never skipped):
 *  - published: the price review must be complete (global and every row);
 *  - draft: the calculator must be unreachable and no unapproved price public
 *    (tests/labs/release-gate.php: 404, list, search, categories, related
 *    links, sitemap, structured data, page config, copy).
 * tests/labs/release-gate.php runs in both cases.
 *      node tests/labs/data.js --release --published-copy
 *        the same gate as if every tool were published (the shipped catalog is
 *        not changed): fails while the prices are unreviewed.
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
function check(name, cond, detail) {
    if (cond) { pass++; console.log('PASS ' + name); }
    else { fail++; console.log('FAIL ' + name + (detail ? '\n  ' + JSON.stringify(detail) : '')); }
}
function read(name) { return JSON.parse(fs.readFileSync(path.join(theme, 'assets/data/labs', name + '.json'), 'utf8')); }
/** inc/labs-data/*.json.php: JSON after a "<?php exit; … ?>" guard line (see omochix_labs_read_guarded_json()). */
function readGuarded(name) {
    var raw = fs.readFileSync(path.join(theme, 'inc/labs-data', name + '.json.php'), 'utf8');
    if (raw.indexOf('<?php exit;') !== 0 || raw.indexOf('?>\n') === -1) throw new Error(name + ': missing guard line');
    return JSON.parse(raw.slice(raw.indexOf('?>\n') + 3));
}

var pricing = readGuarded('pricing');
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
    { date: '2026-11-04', mutations: [[0, 'source_url', 'https://platform.claude.com.evil.test/x'], [1, 'source_url', 'http://platform.claude.com/x'], [2, 'id', 'Bad ID']] },
    // Row approval: valid, missing acknowledgement, review before the price check, held without reason, unknown caveat.
    { date: '2026-11-06', review: { status: 'approved', reviewer: 'R', reviewed_at: '2026-11-06' }, mutations: [
        [0, 'approval', { status: 'approved', reviewer: 'R', reviewed_at: '2026-11-06', acknowledged: ['cache_write_excluded', 'thinking_as_output_unstated'] }],
        [1, 'approval', { status: 'approved', reviewer: 'R', reviewed_at: '2026-11-06', acknowledged: ['cache_write_excluded'] }],
        [2, 'approval', { status: 'approved', reviewer: 'R', reviewed_at: '2026-11-01', acknowledged: ['cache_write_excluded', 'thinking_as_output_unstated', 'prompt_length_includes_cache'] }],
        [3, 'approval', { status: 'held' }],
        [4, 'caveats', ['not_a_known_code']],
        [12, 'approval', { status: 'approved', reviewer: 'R', reviewed_at: '2026-11-06', acknowledged: ['cache_storage_excluded', 'free_tier_excluded'] }]
    ] },
    { date: '2026-11-06', review: { status: 'approved', reviewer: '', reviewed_at: '2026-11-06' }, mutations: [[0, 'approval', { status: 'approved', reviewer: 'R', reviewed_at: '2026-11-06', acknowledged: ['cache_write_excluded', 'thinking_as_output_unstated'] }]] }
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
    Object.keys(c.review || {}).forEach(function (k) { data.review[k] = c.review[k]; });
    c.mutations.forEach(function (m) {
        if (m[2] === null) delete data.models[m[0]][m[1]];
        else data.models[m[0]][m[1]] = m[2];
    });
    var view = Cost.view(data, c.date);
    var js = {
        view: view.models.map(function (m) { return m.id + '@' + m.input; }),
        hidden: view.hidden,
        active: Cost.active(view.models, c.date, view.max_age_days).map(function (m) { return m.id + '@' + m.input; }),
        states: view.models.map(function (m) { return m.id + '@' + m.input + '=' + m.approval; }),
        file_approved: Cost.fileApproved(data),
        gate: [true, false].map(function (production) {
            var g = Cost.gate(view, Cost.fileApproved(data), production);
            return { models: g.models.map(function (m) { return m.id; }), withheld: g.withheld, hidden: g.hidden };
        })
    };
    var p = phpOut.results[i];
    var sortObj = function (o) { var r = {}; Object.keys(o).sort().forEach(function (k) { r[k] = o[k]; }); return r; };
    var gateSame = JSON.stringify(js.gate.map(function (g) { return [g.models, g.withheld, sortObj(g.hidden)]; })) === JSON.stringify(p.gate.map(function (g) { return [g.models, g.withheld, sortObj(g.hidden)]; }));
    var same = gateSame && JSON.stringify(js.states) === JSON.stringify(p.states) && js.file_approved === p.file_approved && JSON.stringify(js.view) === JSON.stringify(p.view) && JSON.stringify(js.active) === JSON.stringify(p.active) && JSON.stringify(Object.keys(js.hidden).sort().map(function (k) { return k + js.hidden[k]; })) === JSON.stringify(Object.keys(p.hidden).sort().map(function (k) { return k + p.hidden[k]; }));
    if (!same) console.log('  php ' + JSON.stringify(p) + '\n  js  ' + JSON.stringify(js));
    check('parity case ' + (i + 1) + ' (' + c.date + ', ' + c.mutations.length + ' mutations)', same);
});
check('parity: the mutated case hides the right rows', JSON.stringify(phpOut.results[7].hidden) === JSON.stringify({ unverified: 2, source: 1, invalid: 4 }));
var rowCase = phpOut.results[11];
check('parity: row approval needs every caveat acknowledged and a review on or after the price check', JSON.stringify(rowCase.states.slice(0, 3)) === JSON.stringify(['claude-opus-5-5@4=approved', 'claude-sonnet-5-5@2=pending', 'claude-haiku-5-5@0.1=pending']) && rowCase.states.indexOf('gpt-6.1-terra@1=held') !== -1, rowCase.states);
check('parity: rows made invalid (held without reason, unknown caveat) are dropped', rowCase.states.every(function (s) { return s.indexOf('gpt-6-astra@') !== 0 && s.indexOf('gpt-6.1-sol@') !== 0; }));
check('parity: production shows only approved rows of an approved file', JSON.stringify(rowCase.gate[0].models) === JSON.stringify(['claude-opus-5-5', 'gemini-3.5-flash-lite']) && rowCase.gate[0].withheld === false);
check('parity: a held row without a reason and an unknown caveat are invalid', rowCase.hidden.invalid === 2);
check('parity: production with an unapproved file shows nothing even with approved rows', JSON.stringify(phpOut.results[12].gate[0].models) === '[]' && phpOut.results[12].gate[0].withheld === true && phpOut.results[12].file_approved === false);
check('parity: staging shows every valid row with its state', phpOut.results[11].gate[1].models.length === phpOut.results[11].view.length);

// ------------------------------------------------------------- pricing
check('pricing: schema, currency and unit', pricing.schema_version === 2 && pricing.currency === 'USD' && pricing.unit === 'per_1m_tokens' && pricing.max_age_days === 90);
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
check('pricing: every caveat code has a label, every row lists known caveats and an approval record', Object.keys(pricing.caveat_labels).length > 0 && pricing.models.every(function (m) {
    return Array.isArray(m.caveats) && m.caveats.every(function (c) { return Object.prototype.hasOwnProperty.call(pricing.caveat_labels, c); }) && m.approval && ['pending', 'approved', 'held'].indexOf(m.approval.status) !== -1;
}));
check('pricing: held rows give a reason', pricing.models.filter(function (m) { return m.approval.status === 'held'; }).every(function (m) { return typeof m.approval.reason === 'string' && m.approval.reason.trim().length > 10; }));
check('pricing: initial state — global review pending, OpenAI held, Anthropic and Google pending, nothing approved', pricing.review.status === 'pending' && pricing.models.every(function (m) {
    return m.provider === 'openai' ? m.approval.status === 'held' : m.approval.status === 'pending';
}));
check('pricing: data lives outside assets/ (no public pricing file)', !fs.existsSync(path.join(theme, 'assets/data/labs/pricing.json')));
// Release gate: global approval, every row decided, at least one approved, approvals complete.
var releaseProblems = [];
if (!Cost.fileApproved(pricing)) releaseProblems.push('review is not approved (status, reviewer, reviewed_at)');
pricing.models.forEach(function (m) {
    var st = Cost.approvalState(m);
    if (m.approval.status === 'pending') releaseProblems.push(m.id + (m.valid_from ? '@' + m.valid_from : '') + ': row still pending');
    else if (m.approval.status === 'approved' && st !== 'approved') releaseProblems.push(m.id + ': approval incomplete (reviewer, reviewed_at on/after verified_at, acknowledged caveats)');
});
if (!pricing.models.some(function (m) { return Cost.approvalState(m) === 'approved'; })) releaseProblems.push('no approved row');

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
check('catalog: tools are published or draft', catalog.tools.every(function (t) { return t.status === 'published' || t.status === 'draft'; }));
check('catalog: four tools with unique slugs and titles', catalog.tools.length === 4 && new Set(catalog.tools.map(function (t) { return t.slug; })).size === 4 && new Set(catalog.tools.map(function (t) { return t.title; })).size === 4);
check('catalog: every tool has a UI script and a content template', catalog.tools.every(function (t) {
    return fs.existsSync(path.join(theme, 'assets/js/labs/ui', t.slug + '.js')) && fs.existsSync(path.join(theme, 'template-parts/labs/content', t.slug + '.php'));
}));

// --------------------------------------------------------- release gate
function costStatus(cat) {
    var t = cat.tools.filter(function (x) { return x.slug === 'token-cost-calculator'; })[0];
    return t ? t.status : 'missing';
}
/** Pricing problems that block a release for a given calculator status. */
function pricingGate(status, problems) {
    if (status === 'published') return problems.slice();
    if (status === 'draft') return [];
    return ['cost calculator status is "' + status + '"'];
}
var publishedCopy = process.argv.indexOf('--published-copy') !== -1;
var status = publishedCopy ? 'published' : costStatus(catalog);
check('release gate: a published calculator with the current (unreviewed) prices would fail', pricingGate('published', releaseProblems).length > 0 || releaseProblems.length === 0);
check('release gate: an unknown calculator status fails', pricingGate('live', []).length === 1 && pricingGate('missing', []).length === 1);
check('release gate: a draft calculator needs no price review (the PHP gate checks it is unreachable)', pricingGate('draft', releaseProblems).length === 0);
if (release) {
    console.log('INFO release: cost calculator is ' + status + (publishedCopy ? ' (published copy; shipped catalog unchanged)' : ''));
    var gate = pricingGate(status, releaseProblems);
    check('release: price review complete' + (status === 'published' ? ' (the calculator is published)' : ' — not required: the calculator is ' + status), gate.length === 0, gate);
    var php = spawnSync('php', [path.join(root, 'tests/labs/release-gate.php')].concat(publishedCopy ? ['--published-copy'] : []), { encoding: 'utf8' });
    check('release: PHP gate (calculator ' + (status === 'published' ? 'reviewed' : 'unreachable') + ', no unapproved price public)', php.status === 0, (php.stdout + php.stderr).trim().split('\n'));
} else if (pricingGate(status, releaseProblems).length) {
    console.log('NOTE release gate would fail (' + releaseProblems.length + ' pricing items, calculator ' + status + '): run with --release before a release.');
}

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
