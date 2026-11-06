// Pre-implementation measurement for P07, P08, P12 and P18. Run from tests/svg-motion: node effects.js. Writes results/effects.json
const { chromium, firefox, webkit } = require('playwright'); const fs = require('fs'), path = require('path'); const { start } = require('./server.js');
const B = 'http://127.0.0.1:8770/pages/effects.html?engine=x', R = path.join(__dirname, 'results');
const ISO = { args: ['--disable-site-isolation-trials', '--disable-features=IsolateOrigins,site-per-process,IsolateSandboxedIframes'] };
(async () => { const stop = start(); const out = { paint: {}, mask: {}, cost: {} };
  for (const [e, t] of [['chromium', chromium], ['firefox', firefox], ['webkit', webkit]]) {
    const br = await t.launch(e === 'chromium' ? ISO : {}); const ctx = await br.newContext({ viewport: { width: 1300, height: 800 } }); const p = await ctx.newPage(); await p.goto(B); await p.waitForFunction(() => document.title === 'READY');
    const shot = async () => (await p.locator('#f').screenshot()).toString('base64');
    await p.evaluate(() => show('none', 'start')); const base = await shot(); out.paint[e] = {};
    for (const fx of ['blur-g', 'blur-svg', 'shadow-g', 'shadow-svg', 'rotY-g', 'rotY-svg', 'translate-g']) { await p.evaluate(x => show(x, 'mid'), fx); await p.waitForTimeout(60); const mid = await shot(); out.paint[e][fx] = await p.evaluate(([a, b]) => diff(a, b), [mid, base]); }
    await p.evaluate(() => maskDoc('alpha')); const al = await shot(); await p.evaluate(() => maskDoc('filter')); const fi = await shot(); await p.evaluate(() => show('none', 'start')); const b2 = await shot(); out.mask[e] = { alphaVsBase: await p.evaluate(([a, b]) => diff(a, b), [al, b2]), filterVsBase: await p.evaluate(([a, b]) => diff(a, b), [fi, b2]), alphaVsFilter: await p.evaluate(([a, b]) => diff(a, b), [al, fi]) };
    console.log(e, 'paint', JSON.stringify(out.paint[e]), 'mask', JSON.stringify(out.mask[e]));
    const rates = e === 'chromium' ? [1, 6, 20] : [1]; out.cost[e] = {};
    for (const rate of rates) { const rows = out.cost[e]['x' + rate] = {}; const c2 = await br.newContext({ viewport: { width: 1300, height: 800 } }); const q = await c2.newPage(); await q.goto(B); await q.waitForFunction(() => document.title === 'READY'); if (rate > 1) { const c = await c2.newCDPSession(q); await c.send('Emulation.setCPUThrottlingRate', { rate }); }
      for (const [w, hh] of [[640, 360], [1200, 675]]) for (const n of [0, 200, 1000]) for (const fx of ['translate-g', 'blur-svg', 'blur-g', 'shadow-svg', 'shadow-g', 'rotY-svg']) { const r = await q.evaluate(([n, fx, w, hh]) => cost(n, fx, w, hh), [n, fx, w, hh]); rows[fx + '/' + n + '/' + w] = r; }
      console.log(e, 'x' + rate, Object.entries(rows).filter(([k]) => k.endsWith('/1200')).map(([k, v]) => k.replace('/1200', '') + '=' + v.fps).join(' ')); await c2.close(); }
    await br.close(); }
  fs.writeFileSync(path.join(R, 'effects.json'), JSON.stringify(out, null, 1)); stop(); process.exit(0);
})().catch(e => { console.error(e); process.exit(1); });
