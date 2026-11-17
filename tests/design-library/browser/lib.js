/* Shared helpers for the Design Library browser checks (local only). */
'use strict';
var fs = require('fs');
var path = require('path');

var BASE = String(process.env.DL_BASE || '').replace(/\/$/, '');
var SITE = String(process.env.DL_SITE || '').replace(/\/$/, '');
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(BASE) || !fs.existsSync(SITE + '/wp')) {
    console.error('DL_BASE=http://127.0.0.1:PORT and DL_SITE=<throwaway site dir> are required');
    process.exit(2);
}
var FLAG = SITE + '/design-isolation-test.on';
var THEME = path.resolve(__dirname, '../../../wp-theme/omochix-theme');
/* Published works (status published), from the catalog: the expected counts follow the shipped works. */
function published(medium) { return catalog().items.filter(function (i) { return i.status === 'published' && (!medium || i.medium === medium); }); }
/* QA output (screenshots, perf record): DL_QA_OUT=<dir> writes there instead of docs/design-library/qa (keeps the committed QA files as they are). */
function qaOut(rel) { var base = process.env.DL_QA_OUT ? path.resolve(process.env.DL_QA_OUT) : path.resolve(__dirname, '../../../docs/design-library/qa'); return path.join(base, rel); }
var DEMOS = ['liquid-glass', 'minimal-editorial', '3d-interactive', 'bento-saas', 'dark-luxury', 'aurora-gradient', 'ai-workspace', 'micro-interactions'];
var GL_ARGS = ['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--enable-precise-memory-info'];

var results = { pass: 0, fail: 0, lines: [] };
function check(name, cond, detail) {
    var line = (cond ? 'PASS ' : 'FAIL ') + name + (!cond && detail !== undefined ? '  [' + JSON.stringify(detail).slice(0, 600) + ']' : '');
    results[cond ? 'pass' : 'fail']++;
    results.lines.push(line);
    console.log(line);
}
function info(line) { results.lines.push('INFO ' + line); console.log('INFO ' + line); }
function flag(on) { if (on) fs.writeFileSync(FLAG, ''); else if (fs.existsSync(FLAG)) fs.unlinkSync(FLAG); }
function catalog() { return JSON.parse(fs.readFileSync(THEME + '/assets/data/design/catalog.json', 'utf8')); }
function axePath() {
    var tries = (process.env.NODE_PATH || '').split(':').filter(Boolean).map(function (d) { return path.join(d, 'axe-core/axe.min.js'); });
    return tries.filter(function (f) { return fs.existsSync(f); })[0] || null;
}
/* Records CSP violations (also inside sandboxed frames: init scripts run in every frame) and rAF calls. */
var INIT = function () {
    window.__csp = [];
    document.addEventListener('securitypolicyviolation', function (e) { window.__csp.push(e.violatedDirective + ' ' + (e.blockedURI || '')); });
    window.__raf = 0;
    var raf = window.requestAnimationFrame.bind(window);
    window.requestAnimationFrame = function (cb) { window.__raf++; return raf(cb); };
    window.__longtasks = [];
    try { new PerformanceObserver(function (l) { l.getEntries().forEach(function (e) { window.__longtasks.push(Math.round(e.duration)); }); }).observe({ type: 'longtask', buffered: true }); } catch (e) { /* not supported */ }
    window.__cls = 0;
    try { new PerformanceObserver(function (l) { l.getEntries().forEach(function (e) { if (!e.hadRecentInput) window.__cls += e.value; }); }).observe({ type: 'layout-shift', buffered: true }); } catch (e) { window.__cls = -1; }
    window.__lcp = 0;
    try { new PerformanceObserver(function (l) { var es = l.getEntries(); window.__lcp = es[es.length - 1].startTime; }).observe({ type: 'largest-contentful-paint', buffered: true }); } catch (e) { window.__lcp = -1; }
};
function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
function summary(label) {
    console.log('\n' + label + ': ' + results.pass + ' passed, ' + results.fail + ' failed');
    return results.fail ? 1 : 0;
}
module.exports = { BASE: BASE, SITE: SITE, THEME: THEME, DEMOS: DEMOS, GL_ARGS: GL_ARGS, check: check, info: info, flag: flag, catalog: catalog, axePath: axePath, INIT: INIT, wait: wait, summary: summary, results: results, published: published, qaOut: qaOut };
