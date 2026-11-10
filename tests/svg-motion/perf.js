// Frame-rate separation and performance budget measurements. Run from tests/svg-motion: node perf.js
// Writes results/perf.json and results/fps-*.json. Measurements, not pass/fail tests.
const { chromium, firefox, webkit } = require('playwright'); const fs = require('fs'), path = require('path'); const { start } = require('./server.js');
const B = 'http://127.0.0.1:8770/pages', R = path.join(__dirname, 'results');
(async () => {
  const stop = start(); const out = { fps: {}, budget: {} };
  for (const [e, t] of (process.argv[2] === 'budget' ? [] : [['chromium', chromium], ['firefox', firefox], ['webkit', webkit]])) {
    const br = await t.launch(); const ctx = await br.newContext({ viewport: { width: 800, height: 600 } }); const p = await ctx.newPage();
    await p.goto(B + '/fps.html?engine=' + e); await p.waitForFunction(() => document.title === 'READY'); const steps = [];
    steps.push(await p.evaluate(() => sample('initial')));
    await p.mouse.click(780, 580); steps.push(await p.evaluate(() => sample('click in the parent page')));
    await p.evaluate(() => document.getElementById('sandbox').focus()); steps.push(await p.evaluate(() => sample('iframe.focus() from the parent')));
    await p.hover('#sandbox'); steps.push(await p.evaluate(() => sample('pointer over the frame')));
    await p.locator('#sandbox').click(); steps.push(await p.evaluate(() => sample('click inside the frame')));
    await p.mouse.click(780, 580); steps.push(await p.evaluate(() => sample('click back in the parent')));
    await p.evaluate(() => document.getElementById('sandbox').blur()); steps.push(await p.evaluate(() => sample('after blur')));
    out.fps[e] = steps; console.log(e, JSON.stringify(steps));
    await ctx.close(); await br.close();
  }
  // Sandboxed frames get their own process, which CPU throttling on the page does not reach; keep them in-process so the rate applies.
  const runs = [['chromium', chromium, [1, 4, 6, 20]]];
  for (const [e, t, rates] of runs) {
    const br = await t.launch({ args: ['--disable-site-isolation-trials', '--disable-features=IsolateOrigins,site-per-process,IsolateSandboxedIframes'] }); out.budget[e] = {};
    for (const rate of rates) {
      const ctx = await br.newContext({ viewport: { width: 800, height: 600 } }); const p = await ctx.newPage(); await p.goto(B + '/budget.html?engine=' + e); await p.waitForFunction(() => document.title === 'READY');
      if (e === 'chromium' && rate > 1) { const c = await ctx.newCDPSession(p); await c.send('Emulation.setCPUThrottlingRate', { rate }); }
      const rows = out.budget[e]['x' + rate] = [];
      for (const [preset, ns] of [['logo-parts-wave', [50, 100, 200, 500, 1000, 2000]], ['logo-breathe', [200, 1000, 2500, 4900]], ['logo-draw', [50, 200, 500, 1000]], ['logo-color-shift', [200, 500, 1000]]]) for (const n of ns) { const r = await p.evaluate(([n, preset]) => measure(n, preset, 100000), [n, preset]); rows.push({ preset, ...r }); console.log(e, 'x' + rate, preset, n, r.fps, 'p95', r.p95, 'worst', r.worst, 'norm', r.normalizeMs); }
      await ctx.close();
    }
    await br.close();
  }
  fs.writeFileSync(path.join(R, process.argv[2] === 'budget' ? 'perf-budget.json' : 'perf.json'), JSON.stringify(out, null, 1)); stop();
})().catch(e => { console.error(e); process.exit(1); });
