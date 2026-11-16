#!/usr/bin/env node
/**
 * Design Library performance measurements (Chromium, local THROWAWAY site).
 * Compares against D0 §15.3 and prints JSON for catalog audit.perf.
 *
 * Each demo is measured three times; the median of each metric is compared.
 * Caveats (recorded with the results): headless Chromium with SwiftShader
 * (software WebGL), PHP's built-in server without compression or HTTP/2,
 * so transfer sizes are computed as gzip of each response body here, and
 * LCP uses CDP throttling (4x CPU, 150 ms RTT, 1.6 Mbps) instead of Lighthouse.
 *
 *   DL_BASE=... DL_SITE=... NODE_PATH=<playwright> node tests/design-library/browser/perf.js [--write]
 *   --write stores the numbers in catalog audit.perf (then re-run tools/design-library/demo-hash.js is NOT needed: the catalog is not hashed)
 */
'use strict';
var zlib = require('zlib');
var fs = require('fs');
var path = require('path');
var { chromium } = require('playwright');
var L = require('./lib.js');
var check = L.check, BASE = L.BASE;

var BUDGET = {
    '2d': { transferKb: 300, jsKb: 40, lcpMs: 2500, cls: 0.05, longTasks: 0, heapMb: 50, fpsP5: 55 },
    '3d': { transferKb: 900, jsKb: 250, lcpMs: 2500, cls: 0.05, longTasks: 2, heapMb: 150, fpsP5: 55 }
};
function gz(buf) { return zlib.gzipSync(buf, { level: 6 }).length; }
function kb(n) { return Math.round(n / 102.4) / 10; }

async function demoMetrics(browser, slug) {
    var ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    await ctx.addInitScript(L.INIT);
    var page = await ctx.newPage();
    var bytes = 0, bodies = [];
    page.on('response', function (r) { bodies.push(r.body().then(function (b) { var ct = r.headers()['content-type'] || ''; bytes += /image|video|font/.test(ct) ? b.length : Math.min(b.length, gz(b)); }).catch(function () {})); });
    var cdp = await ctx.newCDPSession(page);
    await cdp.send('Performance.enable');
    await page.goto(BASE + '/design/demo/web/' + slug + '/', { waitUntil: 'load' });
    var html = await page.content();
    await L.wait(5000);
    await Promise.all(bodies);
    var scripts = (await page.evaluate(function () { return Array.prototype.map.call(document.querySelectorAll('script'), function (s) { return s.textContent; }).join('\n'); }));
    var long = await page.evaluate(function () { return window.__longtasks.slice(); });
    var heap = (await cdp.send('Performance.getMetrics')).metrics.filter(function (m) { return m.name === 'JSHeapUsedSize'; })[0].value;
    // motion while measuring frames
    var drive = slug === 'liquid-glass' ? page.mouse.move(900, 500, { steps: 60 }) : slug === 'minimal-editorial' ? page.mouse.wheel(0, 900) : slug === 'bento-saas' ? page.mouse.wheel(0, 500) : Promise.resolve();
    var frames = page.evaluate(function () { return new Promise(function (res) { var t = [], last = performance.now(), end = last + 3000; (function f(now) { t.push(now - last); last = now; if (now < end) requestAnimationFrame(f); else res(t); })(last); }); });
    await drive;
    var iv = (await frames).slice(2).sort(function (a, b) { return a - b; });
    var p95 = iv[Math.floor(iv.length * 0.95)] || 16.7;
    var out = { transferKb: kb(bytes), jsKb: kb(gz(Buffer.from(scripts))), longTasks: long.filter(function (d) { return d > 50; }).length, heapMb: Math.round(heap / 1048576 * 10) / 10, fpsP5: Math.round(1000 / p95) };
    void html;
    await ctx.close();
    return out;
}
async function detailMetrics(browser, slug) {
    var ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    await ctx.addInitScript(L.INIT);
    var page = await ctx.newPage();
    var cdp = await ctx.newCDPSession(page);
    await cdp.send('Network.enable');
    await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1.6 * 1024 * 1024 / 8, uploadThroughput: 750 * 1024 / 8 });
    await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
    await page.goto(BASE + '/design/web/' + slug + '/', { waitUntil: 'load' });
    await L.wait(3500);
    var r = await page.evaluate(function () { return { lcp: Math.round(window.__lcp), cls: Math.round(window.__cls * 1000) / 1000 }; });
    await ctx.close();
    return r;
}
async function listMetrics(browser, reduce) {
    var ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: reduce ? 'reduce' : 'no-preference' });
    await ctx.addInitScript(L.INIT);
    var page = await ctx.newPage();
    var bytes = 0, bodies = [], media = [];
    page.on('response', function (r) { bodies.push(r.body().then(function (b) { var ct = r.headers()['content-type'] || ''; if (/video/.test(ct)) media.push(r.url().split('/').pop()); bytes += /image|video|font/.test(ct) ? b.length : Math.min(b.length, gz(b)); }).catch(function () {})); });
    var cdp = await ctx.newCDPSession(page);
    await cdp.send('Performance.enable');
    await page.goto(BASE + '/design/web/', { waitUntil: 'load' });
    await L.wait(1500);
    var m0 = (await cdp.send('Performance.getMetrics')).metrics;
    await L.wait(5000);
    var m1 = (await cdp.send('Performance.getMetrics')).metrics;
    await Promise.all(bodies);
    function g(ms, n) { return ms.filter(function (m) { return m.name === n; })[0].value; }
    var out = { transferKb: kb(bytes), videos: media, cpuPct: Math.round((g(m1, 'TaskDuration') - g(m0, 'TaskDuration')) / 5 * 100), heapMb: Math.round(g(m1, 'JSHeapUsedSize') / 1048576 * 10) / 10, cls: Math.round((await page.evaluate(function () { return window.__cls; })) * 1000) / 1000 };
    await ctx.close();
    return out;
}

(async function () {
    L.flag(false);
    var browser = await chromium.launch({ args: ['--enable-precise-memory-info'] }); // default headless (WebGL still runs on SwiftShader); the forced-ANGLE flags of the other suites skew frame timing
    var results = {};
    var only = process.argv.slice(2).filter(function (x) { return x.indexOf('--') !== 0; });
    for (var s of L.DEMOS.filter(function (x) { return !only.length || only.indexOf(x) !== -1; })) {
        // three runs, median per metric: single headless runs on a busy machine swing a lot (frame timing, long tasks)
        var runs = [];
        for (var k = 0; k < 3; k++) runs.push(await demoMetrics(browser, s));
        var d = {};
        Object.keys(runs[0]).forEach(function (key) { var v = runs.map(function (x) { return x[key]; }).sort(function (a, b2) { return a - b2; }); d[key] = v[1]; });
        L.info(s + ' runs fpsP5=' + runs.map(function (x) { return x.fpsP5; }).join('/') + ' longTasks=' + runs.map(function (x) { return x.longTasks; }).join('/'));
        var t = await detailMetrics(browser, s);
        var r = Object.assign({}, d, { lcpMs: t.lcp, cls: t.cls });
        results[s] = r;
        var b = BUDGET[s === '3d-interactive' ? '3d' : '2d'];
        L.info(s + ' ' + JSON.stringify(r));
        Object.keys(b).forEach(function (k) {
            var ok = k === 'fpsP5' ? r[k] >= b[k] : r[k] <= b[k];
            check('perf ' + s + ' ' + k + ' ' + r[k] + (k === 'fpsP5' ? ' >= ' : ' <= ') + b[k], ok);
        });
    }
    var list = await listMetrics(browser, false);
    var listReduced = await listMetrics(browser, true);
    L.info('list (loops) ' + JSON.stringify(list));
    L.info('list (reduced motion, posters only) ' + JSON.stringify(listReduced));
    check('perf list: initial transfer ≤ 1.5 MB with loops playing', list.transferKb <= 1536, list.transferKb);
    check('perf list: at most 2 loop files downloaded at first view', list.videos.length <= 2, list.videos);
    check('perf list: reduced motion downloads no video', listReduced.videos.length === 0, listReduced.videos);
    check('perf list: CLS ≤ 0.05', list.cls <= 0.05, list.cls);
    await browser.close();
    var out = path.resolve(__dirname, '../../../docs/design-library/qa/perf-results.json');
    fs.mkdirSync(path.dirname(out), { recursive: true });
    fs.writeFileSync(out, JSON.stringify({ measured: new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10), environment: 'local throwaway WordPress 7.1.4, PHP built-in server, headless Chromium (Playwright 1.62.1, SwiftShader WebGL), CDP throttling 4x CPU / 150 ms / 1.6 Mbps for LCP', demos: results, list: list, listReducedMotion: listReduced }, null, 2) + '\n');
    if (process.argv.indexOf('--write') !== -1) {
        var cat = L.catalog();
        cat.items.forEach(function (it) { if (results[it.slug]) it.audit.perf = results[it.slug]; });
        fs.writeFileSync(L.THEME + '/assets/data/design/catalog.json', JSON.stringify(cat, null, 2) + '\n');
        L.info('catalog audit.perf updated');
    }
    process.exit(L.summary('perf (chromium)'));
})().catch(function (e) { console.error(e); process.exit(1); });
