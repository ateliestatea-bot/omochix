// Page-speed before/after (Chromium, cold loads, cache disabled). BEFORE = origin/main theme, AFTER = this branch. Usage: N=15 node tests/nav-ux/perf.cjs
const pw = require(process.env.PW_CORE || '/Users/omochi/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright-core');
const SITES = { before: process.env.BEFORE || 'http://127.0.0.1:8941', after: process.env.AFTER || 'http://127.0.0.1:8940' }, N = +process.env.N || 15, PAGES = ['/', '/learn/', '/prompts/'];
const med = (a) => { a = a.filter((x) => x != null).sort((x, y) => x - y); return a.length ? +a[a.length >> 1].toFixed(1) : null; };
(async () => { const b = await pw.chromium.launch(); const res = {};
  for (const scen of (process.env.SCEN || 'fast,slow-4g').split(',')) for (const pg of PAGES) for (const v of ['before', 'after']) { const rows = [];
    for (let i = 0; i < N + 2; i++) { const ctx = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await ctx.newPage(); const cdp = await ctx.newCDPSession(p); await cdp.send('Network.enable'); await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
      if (scen === 'slow-4g') await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1.6 * 1024 * 1024 / 8, uploadThroughput: 750 * 1024 / 8 });
      let bytes = 0, reqs = 0; cdp.on('Network.loadingFinished', (e) => { bytes += e.encodedDataLength; reqs++; });
      await p.addInitScript(() => { window.__m = { lcp: 0, cls: 0 }; new PerformanceObserver((l) => l.getEntries().forEach((e) => (window.__m.lcp = e.startTime))).observe({ type: 'largest-contentful-paint', buffered: true }); new PerformanceObserver((l) => l.getEntries().forEach((e) => { if (!e.hadRecentInput) window.__m.cls += e.value; })).observe({ type: 'layout-shift', buffered: true }); });
      await p.goto(SITES[v] + pg, { waitUntil: 'load' }); await p.waitForTimeout(scen === 'slow-4g' ? 800 : 400);
      const m = await p.evaluate(() => { const n = performance.getEntriesByType('navigation')[0]; const f = performance.getEntriesByType('paint').find((x) => x.name === 'first-contentful-paint'); return { ttfb: n.responseStart, fcp: f && f.startTime, lcp: window.__m.lcp, cls: +window.__m.cls.toFixed(4), load: n.loadEventEnd, htmlKB: n.encodedBodySize / 1024 }; });
      if (i >= 2) rows.push({ ...m, transferKB: bytes / 1024, reqs }); await ctx.close(); }
    res[`${scen} ${pg} ${v}`] = Object.fromEntries(Object.keys(rows[0]).map((k) => [k, med(rows.map((r) => r[k]))])); }
  await b.close(); require('fs').writeFileSync(__dirname + '/../../' + (process.env.OUT_DIR || 'docs/ux-expansion') + '/' + (process.env.OUT || 'perf-result.json'), JSON.stringify(res, null, 1)); console.log('done'); })();
