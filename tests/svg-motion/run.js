// SVG Motion Studio browser tests. Run from tests/svg-motion: node run.js [security,fidelity,presets,page,regression]
// Writes results/ per engine. Exit code 1 on any failure. Starts and stops its own local servers.
const { chromium, firefox, webkit } = require('playwright'); const fs = require('fs'), path = require('path'), { spawn } = require('child_process');
const { start, PORT } = require('./server.js'); const B = 'http://127.0.0.1:' + PORT + '/pages'; const REPO = path.join(__dirname, '../..'); const R = path.join(__dirname, 'results'); fs.mkdirSync(R, { recursive: true });
const { pageTests, uploadSecurity } = require('./studio-tests.js');
const { exportTests } = require('./export-tests.js');
const only = (process.argv[2] || 'security,upload,fidelity,presets,page,export,compose,shortcode,regression').split(',');
// SVGM_ENGINE=chromium|firefox|webkit runs one engine (CI runs the three as parallel jobs). SVGM_PROFILE=fast|full (see export-tests.js).
const ENGINES = [['chromium', chromium], ['firefox', firefox], ['webkit', webkit]].filter(([n]) => !process.env.SVGM_ENGINE || process.env.SVGM_ENGINE === n);
if (!ENGINES.length) { console.log('unknown SVGM_ENGINE ' + process.env.SVGM_ENGINE); process.exit(2); }
const PROFILE = process.env.SVGM_PROFILE === 'fast' ? 'fast' : 'full';
const { composeTests } = require('./compose-tests.js');
const { shortcodeTests } = require('./shortcode-tests.js');
const failures = [], summary = {}; const fail = (e, m) => { failures.push(e + ': ' + m); console.log('  FAIL ' + m); };
const read = n => JSON.parse(fs.readFileSync(path.join(R, n + '.json'), 'utf8'));
const num = v => { const m = String(v).match(/-?\d+(\.\d+)?/g); return m ? m.map(Number) : null; };
function php(entry, port) { const p = spawn('php', ['-S', '127.0.0.1:' + port, '-t', '.', entry], { cwd: REPO, stdio: 'ignore' }); return p; }
// Servers are ready when their ports answer, not after a fixed pause: a slow runner must not start the tests early.
const net = require('net'); const portReady = (port, ms = 15000) => new Promise((ok, no) => { const t0 = Date.now(); (function tryOnce() { const s = net.connect(port, '127.0.0.1'); s.once('connect', () => { s.destroy(); ok(); }); s.once('error', () => { s.destroy(); if (Date.now() - t0 > ms) no(new Error('port ' + port + ' not ready')); else setTimeout(tryOnce, 100); }); })(); });
// Traces are recorded for every context and kept only for those in which something failed.
function traced(br, e) { let n = 0; const orig = br.newContext.bind(br); br.newContext = async opts => { const c = await orig(opts); const at = failures.length; await c.tracing.start({ screenshots: true, snapshots: true }); const close = c.close.bind(c); c.close = async () => { const f = path.join(R, 'trace-' + e + '-' + (++n) + '.zip'); try { await c.tracing.stop({ path: f }); if (failures.length === at) fs.unlinkSync(f); } catch (x) { /* a closed context has no trace to keep */ } return close(); }; return c; }; }
const EXPECT_REJECT = { scriptNs: 'NOT_SVG', xxe: 'DOCTYPE_REJECTED', dtdExt: 'DOCTYPE_REJECTED' };
const EXPECT_BOMB = { useBomb: /USE_EXPANSION_LIMIT|ELEMENT_LIMIT/, useCycle: 'sanitized', laughs: /DOCTYPE/, deep: /DEPTH/, manyEls: /ELEMENT/, hugeFile: /TOO_LARGE/, hugePath: /PATH/, malformed: /PARSE/, notSvg: /NOT_SVG/, empty: /PARSE/, plain: /PARSE/ };

async function pageRun(ctx, e, name) { const p = await ctx.newPage(); p.on('pageerror', x => { if (!/navigation of the top-level window is sandboxed/.test(x.message)) fail(e, name + ' page error: ' + x.message); }); await p.goto(B + '/' + name + '.html?engine=' + e + '&run=' + e + Date.now()); await p.waitForFunction(() => document.title === 'DONE' || document.title === 'ERR', null, { timeout: 180000 }); const t = await p.title(); await p.close(); if (t !== 'DONE') fail(e, name + ' ended ' + t); return read(name + '-' + e); }

function checkSecurity(e, d) {
  const s = {}; for (const c in d.configs) { const v = d.configs[c]; s[c] = { vectors: Object.keys(v).length, ready: Object.values(v).filter(x => x.ready).length, exec: Object.entries(v).filter(([, x]) => x.hits.length).map(([k]) => k), net: [...new Set((d.canary[c] || []).map(x => x.split('/')[0]))] }; }
  // Positive controls: if these are zero, the detectors are broken and every other zero means nothing.
  if (!s.A_raw_sandbox.exec.length) fail(e, 'control: raw SVG executed nothing'); if (!s.A_raw_sandbox.net.length) fail(e, 'control: raw SVG reached no canary');
  if (Object.keys(d.norm).length !== 69) fail(e, 'corpus is not 69'); if (Object.keys(d.bombs).length !== 11) fail(e, 'bombs are not 11');
  for (const c of ['C_product_no_csp', 'D_product']) { if (s[c].exec.length) fail(e, c + ' executed ' + s[c].exec); if (s[c].net.length) fail(e, c + ' reached canary ' + s[c].net); }
  // Non-vacuous: output must keep the drawing, in the text and painted on screen.
  const sanitized = Object.entries(d.norm).filter(([, v]) => v.status === 'sanitized');
  for (const [k, v] of sanitized) { if (v.hadMarker && !v.markerInOutput) fail(e, 'marker lost in output: ' + k); const r = d.configs.D_product[k]; if (!r || !r.ready) fail(e, 'runtime did not start for ' + k); else if (v.hadMarker && !r.marker) fail(e, 'marker not painted: ' + k); }
  for (const [k, v] of Object.entries(d.norm)) if (v.status === 'rejected' && EXPECT_REJECT[k] !== v.code) fail(e, 'unexpected reject ' + k + ' ' + v.code);
  for (const k in EXPECT_REJECT) if (d.norm[k].status !== 'rejected') fail(e, 'should reject ' + k);
  for (const k in EXPECT_BOMB) { const x = EXPECT_BOMB[k], b = d.bombs[k]; if (x === 'sanitized' ? b.status !== 'sanitized' : !(b.status === 'rejected' && x.test(b.code))) fail(e, 'bomb ' + k + ' ' + JSON.stringify(b)); if (b.ms > 2000) fail(e, 'bomb slow ' + k + ' ' + b.ms); }
  const w = d.isolation.withCSP; if (!w || w.parentDoc !== 'blocked' || w.localStorage !== 'blocked') fail(e, 'isolation'); if ((d.canary['iso-csp'] || []).length) fail(e, 'CSP frame reached canary ' + d.canary['iso-csp']); if (!(d.canary['iso-nocsp'] || []).length) fail(e, 'control: no-CSP frame reached no canary'); if (d.canary.topnav) fail(e, 'top navigation happened');
  return { matrix: s, rejected: Object.keys(EXPECT_REJECT), sanitized: sanitized.length, markersPainted: sanitized.filter(([k, v]) => v.hadMarker && d.configs.D_product[k].marker).length, isolationCsp: d.canary['iso-csp'] || [], isolationNoCsp: d.canary['iso-nocsp'] };
}
function checkFidelity(e, d) {
  let inked = 0; for (const [k, r] of Object.entries(d.logos)) { if (r.error) { fail(e, 'logo ' + k + ' ' + r.error); continue; } if (r.ink > 0) inked++; else fail(e, 'logo ' + k + ' reference is blank'); if (!r.overlayClean) fail(e, 'logo ' + k + ' overlay fails verify'); for (const f of ['diff', 'prefixDiff', 'preparedDiff', 'overlayDiff']) if (r[f] !== 0) fail(e, 'logo ' + k + ' ' + f + '=' + r[f]); if (!r.idempotent) fail(e, 'logo ' + k + ' not idempotent'); }
  if (Object.keys(d.logos).length !== 17) fail(e, 'logos are not 17'); if (!d.idCollision.uniqueIds || !d.idCollision.refsOwn) fail(e, 'id collision');
  return { logos: Object.keys(d.logos).length, inked, worstDiff: Math.max(...Object.values(d.logos).map(r => Math.max(r.diff, r.prefixDiff, r.preparedDiff))) };
}

// Entrance presets must end exactly as the logo; these loops rest exactly as the logo.
const END_EQUALS_LOGO = ['logo-pop', 'logo-draw-fill', 'logo-assemble', 'logo-stagger-pop', 'logo-rise-mask', 'logo-wipe', 'logo-drop', 'logo-spin-in', 'logo-focus', 'logo-flip'];
const START_EQUALS_LOGO = ['logo-breathe', 'logo-float', 'logo-wiggle', 'logo-parts-wave', 'logo-shine', 'logo-glow'];
const START_HIDDEN = ['logo-pop', 'logo-assemble', 'logo-stagger-pop', 'logo-wipe', 'logo-drop', 'logo-spin-in', 'logo-focus', 'logo-flip'];
async function presets(br, e) {
  const ctx = await br.newContext({ viewport: { width: 700, height: 700 } }); const p = await ctx.newPage(); p.on('pageerror', x => fail(e, 'stage page error: ' + x.message));
  await p.goto(B + '/stage.html?engine=' + e); await p.waitForFunction(() => document.title === 'READY'); const ids = await p.evaluate(() => H.init()); const out = {};
  const shot = async () => (await p.locator('#s iframe').screenshot()).toString('base64');
  for (const sample of ['prism', 'orbit', 'stack']) {
    await p.evaluate(s => H.load(s), sample); await p.waitForTimeout(80); const base = await shot();
    for (const id of ids) {
      // The shine band lives in its own stage document, as in the product.
      if (id === 'logo-shine') { await p.evaluate(s => H.load(s, null, true), sample); await p.waitForTimeout(80); } else if (ids[ids.indexOf(id) - 1] === 'logo-shine') { await p.evaluate(s => H.load(s), sample); await p.waitForTimeout(80); }
      const row = out[id] = out[id] || { samples: {} }; const r = row.samples[sample] = {}; const tag = id + '/' + sample;
      const plan = await p.evaluate(i => H.plan(i), id); const len = plan.length;
      await p.evaluate(i => H.setPlan(i, {}, false), id);
      // 1. the computed values really move between start and end, on every track
      await p.evaluate(() => H.seek(0)); const a = await p.evaluate(() => H.probe()); const startShot = await shot();
      const T = plan.tracks.every(t => t.iterations === null || t.iterations === 1) ? len : plan.tracks[0].duration; await p.evaluate(t => H.seek(t), plan.loops ? Math.max(...plan.tracks.map(t => t.delay)) + Math.round(plan.tracks[0].duration * (plan.tracks[0].direction === 'alternate' ? 0.97 : 0.3)) : len); const b = await p.evaluate(() => H.probe()); const endShot = await shot();
      const moved = Object.keys(a.values).length && Object.keys(a.values).every(i => JSON.stringify(a.values[i]) !== JSON.stringify(b.values[i])); r.moved = moved; if (!moved) fail(e, tag + ' values do not move');
      // 2. pixels
      r.startInk = await p.evaluate(x => H.ink(x), startShot); r.endInk = await p.evaluate(x => H.ink(x), endShot); r.startEndDiff = await p.evaluate(([x, y]) => H.inkDiff(x, y), [startShot, endShot]); r.endVsLogo = await p.evaluate(([x, y]) => H.inkDiff(x, y), [endShot, base]); r.startVsLogo = await p.evaluate(([x, y]) => H.inkDiff(x, y), [startShot, base]);
      if (r.startEndDiff <= 0) fail(e, tag + ' no visible change between start and end'); if (r.endInk <= 0) fail(e, tag + ' blank at end');
      if (END_EQUALS_LOGO.includes(id) && r.endVsLogo > 0.1) fail(e, tag + ' end differs from logo ' + r.endVsLogo); if (START_EQUALS_LOGO.includes(id) && r.startVsLogo > 0.1) fail(e, tag + ' start differs from logo ' + r.startVsLogo);
      if (START_HIDDEN.includes(id) && r.startInk > 0.5) fail(e, tag + ' start should be hidden, ink ' + r.startInk);
      // 3. CSS output matches WAAPI at the same time
      const t0 = Math.round((plan.loops ? plan.tracks[0].duration : len) * 0.37); await p.evaluate(t => H.seek(t), t0); const wa = await p.evaluate(() => H.probe()); const cs = await p.evaluate(([i, t]) => H.cssValues(i, {}, t), [id, t0]); let worst = 0, mismatch = 0;
      if (cs.count !== plan.tracks.length) fail(e, tag + ' css animations ' + cs.count + ' vs tracks ' + plan.tracks.length);
      for (const i of Object.keys(wa.values)) for (const k of Object.keys(wa.values[i])) { const x = num(wa.values[i][k]), y = num(cs.values[i] && cs.values[i][k]); if (!x || !y || x.length !== y.length) { if (wa.values[i][k] !== (cs.values[i] || {})[k]) mismatch++; continue; } x.forEach((v, n) => worst = Math.max(worst, Math.abs(v - y[n]))); }
      r.cssVsWaapi = +worst.toFixed(3);
      // The image at a frozen time must look exactly like the WAAPI reference at that time.
      await p.evaluate(t => H.seek(t), t0); await p.waitForTimeout(40); const refShot = await shot(); await p.evaluate(([i, t]) => H.showImage(i, t), [id, t0]); await p.waitForTimeout(70); r.imageVsWaapi = await p.evaluate(([x, y]) => H.inkDiff(x, y), [(await p.locator('#img').screenshot()).toString('base64'), refShot]); if (r.imageVsWaapi > 0.1) fail(e, tag + ' frozen image differs from WAAPI ' + r.imageVsWaapi); if (mismatch) fail(e, tag + ' css/waapi mismatch count ' + mismatch); if (worst > 0.5) fail(e, tag + ' css/waapi differ by ' + worst);
    }
    // Control: the comparison must notice a real difference, or "equals the logo" above proves nothing.
    await p.evaluate(() => H.setPlan('logo-float', { amount: 0.2 }, false)); await p.evaluate(t => H.seek(t), 1940); const shifted = await shot(); const control = await p.evaluate(([x, y]) => H.inkDiff(x, y), [shifted, base]); out.__control = out.__control || {}; out.__control[sample] = control; if (control < 0.5) fail(e, 'control: pixel comparison missed a real shift on ' + sample + ' (' + control + ')');
    // 4. controls on one preset per kind in this sample
    for (const id of ['logo-pop', 'logo-breathe']) {
      await p.evaluate(i => H.setPlan(i, {}, false), id); await p.evaluate(() => { H.send({ type: 'omx:reset' }); }); await p.waitForTimeout(60); await p.evaluate(() => H.send({ type: 'omx:play' })); await p.waitForTimeout(250); const s1 = await p.evaluate(() => H.state()); if (!s1.playing && id === 'logo-breathe') fail(e, id + ' play did not run');
      await p.evaluate(() => H.send({ type: 'omx:pause' })); await p.waitForTimeout(120); await p.evaluate(() => H.send({ type: 'omx:pause' })); await p.waitForTimeout(60); const s2 = await p.evaluate(() => H.state()); await p.waitForTimeout(150); await p.evaluate(() => H.send({ type: 'omx:pause' })); await p.waitForTimeout(60); const s3 = await p.evaluate(() => H.state()); if (s2.playing) fail(e, id + ' pause'); if (s2.time !== s3.time) fail(e, id + ' time moved while paused');
      await p.evaluate(() => H.send({ type: 'omx:reset' })); await p.waitForTimeout(60); const s4 = await p.evaluate(() => H.state()); if (s4.time !== 0) fail(e, id + ' reset time ' + s4.time);
    }
    if (sample === 'prism') { await p.evaluate(() => H.send({ type: 'omx:plan', plan: { tracks: [{ target: { kind: 'root', index: 0 }, frames: [{ offset: 0, props: { fill: 'url(https://evil.example/x)' } }, { offset: 1, props: {} }], duration: 1, delay: 0, easing: 'linear' }], setup: [] } })); await p.waitForTimeout(60); const s = await p.evaluate(() => H.state()); if (s.type !== 'omx:rejected') fail(e, 'frame accepted a plan with url()'); }
  }
  await ctx.close();
  // 5. reduced motion in the frame: no autoplay, resting state, explicit play still works
  const c2 = await br.newContext({ reducedMotion: 'reduce', viewport: { width: 700, height: 700 } }); const q = await c2.newPage(); await q.goto(B + '/stage.html?engine=' + e); await q.waitForFunction(() => document.title === 'READY'); await q.evaluate(() => H.init()); await q.evaluate(() => H.load('prism')); const reduced = {};
  for (const id of ids) { if (id === 'logo-shine') await q.evaluate(() => H.load('prism', null, true)); else if (ids[ids.indexOf(id) - 1] === 'logo-shine') await q.evaluate(() => H.load('prism')); const s = await q.evaluate(i => H.setPlan(i, {}, true), id); const plan = await q.evaluate(i => H.plan(i), id); const pr = await q.evaluate(() => H.probe()); if (!pr.reduced) fail(e, 'reduced not seen'); if (s.playing) fail(e, id + ' autoplayed under reduced motion'); const want = plan.rest === 'end' ? plan.length : 0; if (Math.abs((s.time || 0) - want) > 1) fail(e, id + ' reduced rest time ' + s.time + ' want ' + want); await q.evaluate(() => H.send({ type: 'omx:play' })); await q.waitForTimeout(120); const s2 = await q.evaluate(() => H.state()); reduced[id] = { rest: plan.rest, autoplayed: s.playing, playOnRequest: s2.playing }; if (!s2.playing && plan.loops) fail(e, id + ' explicit play refused'); }
  await c2.close(); out.__reduced = reduced; fs.writeFileSync(path.join(R, 'presets-' + e + '.json'), JSON.stringify(out, null, 1)); return { presets: ids.length, samples: 3 };
}

async function regression(br, e) {
  const L = 'http://127.0.0.1:8787'; const cat = JSON.parse(fs.readFileSync(path.join(REPO, 'wp-theme/omochix-theme/assets/data/motion/catalog.json'), 'utf8')); const out = { details: 0, previews: 0 }; const errors = [];
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const p = await ctx.newPage(); p.on('pageerror', x => errors.push(x.message));
  await p.goto(L + '/learn/motion/'); await p.waitForTimeout(800); const list = await p.evaluate(() => ({ cards: document.querySelectorAll('.motion-card').length, chips: document.querySelectorAll('.motion-filter__chip').length, svgm: !!document.querySelector('[data-svg-motion]'), frames: document.querySelectorAll('iframe[sandbox="allow-scripts"]').length, scripts: [...document.scripts].some(s => /svg-motion|purify/.test(s.src)), count: (document.querySelector('.motion-filter__count') || {}).textContent })); out.list = list; if (list.cards !== 53) fail(e, 'regression cards ' + list.cards); if (list.chips !== 9) fail(e, 'regression chips ' + list.chips); if (list.scripts || list.svgm) fail(e, 'studio assets leaked to the gallery'); if (!list.frames) fail(e, 'regression: no previews mounted');
  for (const it of cat.items) { const r = await p.goto(L + '/learn/motion/' + it.slug + '/'); if (r.status() !== 200) fail(e, 'regression ' + it.slug + ' ' + r.status()); out.details++; const ok = await p.locator('.motion-preview').first().count(); if (ok) out.previews++; else fail(e, 'regression no preview ' + it.slug); }
  const r2 = await p.goto(L + '/learn/motion/nope/'); if (r2.status() !== 404) fail(e, 'regression 404 ' + r2.status());
  await p.goto(L + '/learn/motion/compose/'); await p.waitForTimeout(400); out.composerSlots = await p.locator('[data-composer-slots] > *').count(); await p.goto(L + '/learn/'); await p.waitForTimeout(400); out.featured = await p.evaluate(() => [...document.querySelectorAll('.motion-preview')].map(x => x.dataset.slug)); if (JSON.stringify(out.featured) !== JSON.stringify(['pop-in', 'typewriter', 'scroll-fade-in'])) fail(e, 'featured ' + out.featured);
  await ctx.close(); out.errors = errors; if (errors.length) fail(e, 'regression errors ' + errors.slice(0, 3)); fs.writeFileSync(path.join(R, 'regression-' + e + '.json'), JSON.stringify(out, null, 1)); return out;
}

(async () => {
  const stop = start(); const p1 = php('tests/svg-motion/preview-server.php', 8788), p2 = php('tests/learn-gallery/preview-server.php', 8787); await Promise.all([PORT, 8771, 8788, 8787].map(p => portReady(p)));
  summary.profile = PROFILE; summary.startedAt = new Date().toISOString(); const t0 = Date.now();
  try {
    for (const [e, t] of ENGINES) {
      const br = await t.launch(); traced(br, e); const s = summary[e] = { version: br.version(), steps: {} }; console.log('== ' + e + ' ' + s.version); const tick = () => { const now = Date.now(); const d = now - (s._t || t0); s._t = now; return Math.round(d / 1000); };
      const ctx = await br.newContext();
      if (only.includes('security')) { s.security = checkSecurity(e, await pageRun(ctx, e, 'security')); s.steps.security = tick(); console.log(' security done ' + s.steps.security + 's'); }
      if (only.includes('fidelity')) { s.fidelity = checkFidelity(e, await pageRun(ctx, e, 'fidelity')); s.steps.fidelity = tick(); console.log(' fidelity done ' + s.steps.fidelity + 's'); }
      await ctx.close();
      if (only.includes('presets')) { s.presets = await presets(br, e); s.steps.presets = tick(); console.log(' presets done ' + s.steps.presets + 's'); }
      if (only.includes('upload')) { const u = await uploadSecurity(br, e, fail); s.upload = { routes: Object.fromEntries(Object.entries(u.routes).map(([k, v]) => [k, { accepted: v.accepted, rejected: Object.keys(v.rejected).length, markerPainted: v.markerPainted, hadMarker: v.hadMarker, exec: v.exec.length }])), canary: u.canary.length, outbound: u.outbound.length, controls: u.controls }; s.steps.upload = tick(); console.log(' upload done ' + s.steps.upload + 's'); }
      if (only.includes('page')) { s.page = await pageTests(br, e, fail); s.steps.page = tick(); console.log(' page done ' + s.steps.page + 's'); }
      if (only.includes('export')) { s.export = await exportTests(br, e, fail); s.steps.export = tick(); console.log(' export done ' + s.steps.export + 's'); }
      if (only.includes('compose')) { s.compose = await composeTests(br, e, fail); s.steps.compose = tick(); console.log(' compose done ' + s.steps.compose + 's'); }
      if (only.includes('shortcode')) { s.shortcode = await shortcodeTests(br, e, fail); s.steps.shortcode = tick(); console.log(' shortcode done ' + s.steps.shortcode + 's'); }
      if (only.includes('regression')) { s.regression = await regression(br, e); s.steps.regression = tick(); console.log(' regression done ' + s.steps.regression + 's'); }
      await br.close();
    }
  } catch (x) { failures.push('runner: ' + (x.stack || x)); console.log(x); } finally { stop(); p1.kill(); p2.kill(); }
  summary.failures = failures; summary.seconds = Math.round((Date.now() - t0) / 1000); fs.writeFileSync(path.join(R, 'summary.json'), JSON.stringify(summary, null, 1));
  console.log('\n' + (failures.length ? failures.length + ' FAILED\n' + failures.slice(0, 60).join('\n') : 'ALL PASSED')); process.exit(failures.length ? 1 : 0);
})();
