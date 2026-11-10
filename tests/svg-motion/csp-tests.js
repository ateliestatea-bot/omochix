// CSP design tests (l5/final/15-CSP-FINAL-DESIGN.md, 7-1). A6 (versions) and A10 (policy, hooks) are in contract.php.
// A1 accuracy, A2 Firefox 0x0 (control and limit), A3 frame protocol and attacks, A5 exports with and without the
// page CSP, A7 inline scripts, A8 blob: in a new tab, A9 fallback when the frame cannot answer. Nothing here retries a
// failure: every check runs once per repetition and every miss is reported.
const fs = require('fs'), path = require('path'), crypto = require('crypto');
const { PORT } = require('./server.js');
const { open, inWindow, consoleVerdict, ALLOWED_ATTACKS, MENTION_ONLY_ATTACKS, KNOWN_CAP_PER_KEY, ATTACKS, LOGOS } = require('./studio-tests.js');
const HARNESS = 'http://127.0.0.1:' + PORT + '/pages/measure.html', STUDIO = 'http://127.0.0.1:8788/learn/svg-motion/', CANARY = 'http://127.0.0.1:8771';
const FIX = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/measure-logos.json'), 'utf8')).logos;
const REPS = Number(process.env.SVGM_MEASURE_REPS) || 20;
const sha = t => crypto.createHash('sha256').update(t).digest('hex');
const same = (a, b) => !!a && !!b && ['x', 'y', 'width', 'height'].every((k, i) => Math.abs(a[k] - b[i]) < 1e-3);
const EVIL_TAB = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><script>document.title="RAN"</script><rect width="10" height="10"/></svg>';
const hostile = run => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" onload="fetch(\'' + CANARY + '/c/' + run + '/onload\')"><script>fetch(\'' + CANARY + '/c/' + run + '/script\')</script>' +
  '<image href="' + CANARY + '/c/' + run + '/img" width="1" height="1"/><foreignObject width="10" height="10"><iframe xmlns="http://www.w3.org/1999/xhtml" src="' + CANARY + '/c/' + run + '/iframe"></iframe></foreignObject>' +
  '<style>@import url(' + CANARY + '/c/' + run + '/css);</style><g data-omx-root=""><rect x="10" y="10" width="20" height="20"/></g></svg>';


// P5 (minimal): the console filter in studio-tests.js is checked on its own, so a weakened filter fails CI.
//  1. synthetic: consoleVerdict() with fixed inputs (engine-independent; every row is a pass/fail condition);
//  2. real browser, page CSP enforced, the real open(): inputs that violate the CSP and are NOT known attack reports
//     must be errors in every engine, even while an allowed attack window is open; normal use must give no error;
//  3. a real allowed attack inside its window: no error (and Chromium may record set-aside reports; not required).
async function harnessSelfCheck(br, e, fail) {
  const out = { synthetic: 0, real: {} }; const STUDIO_ENFORCE = 'http://127.0.0.1:8788/learn/svg-motion/?csp=enforce';
  const SAFETY = 'http://x/wp-theme/omochix-theme/assets/js/svg-motion/safety.js', OTHER = 'http://x/wp-theme/omochix-theme/assets/js/svg-motion/studio.js';
  const HANDLER = "Executing inline event handler violates the following Content Security Policy directive 'script-src 'self''.", BASE = "Setting the document's base URI to 'http://x/' violates the following Content Security Policy directive: \"base-uri 'self'\".";
  const W = (key, allowed) => ({ window: { key, allowed }, known: {} });
  const rows = [
    ['generic 404 is not an error', consoleVerdict('error', 'Failed to load resource: 404', '', W('file:onload', true), true) === null],
    ['non-error types are ignored', consoleVerdict('warning', HANDLER, SAFETY, null, true) === null],
    ['the detector control request is set aside only when enforced', consoleVerdict('error', 'Connecting to http://x/c/r/control/blocked violates', '', null, true) === null && typeof consoleVerdict('error', 'Connecting to http://x/c/r/control/blocked violates', '', null, false) === 'string'],
    ['known handler report, safety.js, allowed window: set aside and counted', (() => { const c = W('file:onload', true); return consoleVerdict('error', HANDLER, SAFETY, c, true) === 'known' && c.known['file:onload'] === 1; })()],
    ['known base report, safety.js, allowed window: set aside', consoleVerdict('error', BASE, SAFETY, W('paste:base', true), true) === 'known'],
    ['known report with no window is an error', /outside an allowed attack window \(no attack is being sent\)/.test(consoleVerdict('error', HANDLER, SAFETY, { window: null, known: {} }, true))],
    ['known report in a window of a not-allowed attack is an error', /is not an allowed attack/.test(consoleVerdict('error', HANDLER, SAFETY, W('file:plain', false), true))],
    ['known report from another file is an error even in an allowed window', consoleVerdict('error', HANDLER, OTHER, W('file:onload', true), true) !== 'known' && typeof consoleVerdict('error', HANDLER, OTHER, W('file:onload', true), true) === 'string'],
    ['known report is an error when the CSP is not enforced', typeof consoleVerdict('error', HANDLER, SAFETY, W('file:onload', true), false) === 'string'],
    ['an inline-script violation is an error even from safety.js in an allowed window', (() => { const v = consoleVerdict('error', "Executing inline script violates the following Content Security Policy directive 'script-src 'self''.", SAFETY, W('file:onload', true), true); return typeof v === 'string' && v !== 'known'; })()],
    ['a connect-src violation is an error in an allowed window', (() => { const v = consoleVerdict('error', "Connecting to 'http://x/' violates the following Content Security Policy directive: \"connect-src 'self'\".", SAFETY, W('file:onload', true), true); return typeof v === 'string' && v !== 'known'; })()],
    // V-4: two explicit lists. Every corpus case whose text mentions on* or <base> must be in exactly one of them, so a
    // new or renamed corpus case fails here until someone decides which list it belongs to.
    ['V-4 the allowed list is the six named attacks, all present in the corpus', ALLOWED_ATTACKS.size === 6 && ['onload', 'onerrorImg', 'onbegin', 'onclickAuto', 'foreignImg', 'base'].every(k => ALLOWED_ATTACKS.has(k) && k in ATTACKS)],
    ['V-4 allowed and mention-only together are exactly the cases that mention on* or <base>, with no overlap', (() => { const raw = Object.keys(ATTACKS).filter(k => /\son[a-z]+\s*=/i.test(ATTACKS[k]) || /<base[\s>]/i.test(ATTACKS[k])); const both = [...ALLOWED_ATTACKS, ...MENTION_ONLY_ATTACKS]; return MENTION_ONLY_ATTACKS.size === 9 && both.length === new Set(both).size && raw.length === both.length && raw.every(k => ALLOWED_ATTACKS.has(k) || MENTION_ONLY_ATTACKS.has(k)); })()],
    ['V-4 a known report in the window of a mention-only attack is an error', [...MENTION_ONLY_ATTACKS].every(k => /is not an allowed attack/.test(consoleVerdict('error', HANDLER, SAFETY, W('file:' + k, ALLOWED_ATTACKS.has(k)), true)))],
    ['V-4 the per-attack cap is tight', KNOWN_CAP_PER_KEY === 2],
    ['no normal logo is an allowed attack', Object.keys(LOGOS).every(k => !ALLOWED_ATTACKS.has(k)) && KNOWN_CAP_PER_KEY > 0],
  ];
  for (const [name, ok] of rows) { out.synthetic++; if (!ok) fail(e, 'harness self-check (synthetic): ' + name); }

  // V-4 in the engine itself: after XML parsing, every allowed attack has a live on* attribute or <base>; no mention-only case does.
  { const ctx = await br.newContext(); const errors = []; const p = await open(ctx, errors, STUDIO_ENFORCE, { window: null, known: {}, enforce: true });
    const texts = Object.fromEntries([...ALLOWED_ATTACKS, ...MENTION_ONLY_ATTACKS].map(k => [k, ATTACKS[k].split('{C}').join('http://127.0.0.1:8771/c/live/' + k + '/')]));
    const live = await p.evaluate(t => Object.fromEntries(Object.entries(t).map(([k, x]) => { const d = new DOMParser().parseFromString(x, 'image/svg+xml'); const els = [...d.getElementsByTagName('*')];
      return [k, els.some(el => el.localName === 'base' || [...el.attributes].some(a => /^on[a-z]+$/.test(a.localName)))]; })), texts);
    out.liveness = live; await ctx.close();
    for (const k of ALLOWED_ATTACKS) if (live[k] !== true) fail(e, 'V-4 allowed attack ' + k + ' has no live on*/<base> in this engine');
    for (const k of MENTION_ONLY_ATTACKS) if (live[k] !== false) fail(e, 'V-4 mention-only case ' + k + ' has a live on*/<base> in this engine'); }

  // 2 and 3: real browser, real open() with the page CSP enforced for this check whatever the run's mode is.
  const probe = async (label, fn, { window: win } = {}) => {
    const ctx = await br.newContext(); const errors = []; const ctl = { window: null, known: {}, enforce: true };
    const p = await open(ctx, errors, STUDIO_ENFORCE, ctl);
    if (win) await inWindow(p, ctl, win.key, win.allowed, () => fn(p)); else await fn(p);
    await p.waitForTimeout(600); await p.evaluate(() => 0); out.real[label] = { errors: errors.length, known: { ...ctl.known } }; await ctx.close(); return { errors, ctl };
  };
  const mustError = async (label, fn, win) => { const r = await probe(label, fn, win); if (!r.errors.length) fail(e, 'harness self-check: "' + label + '" must be an error and was not seen'); if (Object.keys(r.ctl.known).length) fail(e, 'harness self-check: "' + label + '" was set aside'); };
  const inline = p => p.evaluate(() => { const s = document.createElement('script'); s.textContent = 'window.__x = 1'; document.body.appendChild(s); });
  const handler = p => p.evaluate(() => { const d = document.createElement('div'); d.setAttribute('onclick', 'window.__y = 1'); document.body.appendChild(d); d.click(); });
  const outbound = p => p.evaluate(() => fetch('http://127.0.0.1:8771/c/selfcheck/net').catch(() => {}));
  const frame = p => p.evaluate(() => { const f = document.createElement('iframe'); f.src = 'http://127.0.0.1:8771/c/selfcheck/frame'; document.body.appendChild(f); });
  const ALLOWED_WINDOW = { window: { key: 'file:onload', allowed: true } }, DENIED_WINDOW = { window: { key: 'file:plain', allowed: false } };
  for (const [label, fn] of [['inline script', inline], ['inline handler from page code', handler], ['request to another origin', outbound], ['frame of another origin', frame]]) {
    await mustError(label + ' (no window)', fn);
    await mustError(label + ' (allowed window)', fn, ALLOWED_WINDOW);
    await mustError(label + ' (not-allowed window)', fn, DENIED_WINDOW);
  }
  // Normal operation: no error, nothing set aside (P6 positive control).
  const normal = await probe('normal logo upload and preset change', async p => { await p.setInputFiles('[data-svgm-file]', { name: 'n.svg', mimeType: 'image/svg+xml', buffer: Buffer.from(LOGOS.simple) }); await p.waitForTimeout(400);
    await p.evaluate(() => { const i = document.querySelector('input[name="svgm-preset"][value="logo-float"]'); i.checked = true; i.dispatchEvent(new Event('change', { bubbles: true })); }); await p.waitForTimeout(400); });
  if (normal.errors.length || Object.keys(normal.ctl.known).length) fail(e, 'harness self-check: normal use produced CSP reports ' + JSON.stringify({ errors: normal.errors.slice(0, 2), known: normal.ctl.known }));
  // A real allowed attack inside its window: no error; the set-aside count is recorded (Chromium reports these, Firefox and WebKit do not).
  const attackKey = Object.keys(ATTACKS).find(k => ALLOWED_ATTACKS.has(k) && !/\{C\}/.test(ATTACKS[k])) || 'onload';
  const text = ATTACKS[attackKey].split('{C}').join('http://127.0.0.1:8771/c/selfcheck/' + attackKey + '/');
  const real = await probe('real allowed attack (' + attackKey + ') inside its window', async p => { await p.setInputFiles('[data-svgm-file]', { name: 'a.svg', mimeType: 'image/svg+xml', buffer: Buffer.from(text) }); await p.waitForTimeout(500); }, { window: { key: 'file:' + attackKey, allowed: true } });
  if (real.errors.length) fail(e, 'harness self-check: a real allowed attack gave errors ' + JSON.stringify(real.errors.slice(0, 2)));
  // The same attack outside any window: never set aside. Chromium reports it (an error); the others report nothing (0 errors is fine).
  const outside = await probe('real allowed attack (' + attackKey + ') outside any window', async p => { await p.setInputFiles('[data-svgm-file]', { name: 'a.svg', mimeType: 'image/svg+xml', buffer: Buffer.from(text) }); await p.waitForTimeout(500); });
  if (Object.keys(outside.ctl.known).length) fail(e, 'harness self-check: a report was set aside outside any window');
  if (outside.errors.some(x => !/outside an allowed attack window/.test(x))) fail(e, 'harness self-check: unexpected error kind outside a window ' + JSON.stringify(outside.errors.slice(0, 2)));
  return out;
}

async function cspTests(br, e, fail) {
  const out = {}; const t0 = Date.now(); const step = n => console.log('   csp ' + n + ' ' + Math.round((Date.now() - t0) / 1000) + 's');
  const ctx = await br.newContext(); const p = await ctx.newPage(); p.on('pageerror', x => fail(e, 'csp harness page error: ' + x.message));
  await p.goto(HARNESS + '?engine=' + e); await p.waitForFunction(() => document.title === 'READY');
  const dump = run => p.evaluate(u => fetch(u).then(r => r.json()), CANARY + '/__dump?run=' + run);
  const prepared = {};
  for (const [k, v] of Object.entries(FIX)) prepared[k] = await p.evaluate(async ([s, t]) => H.prepared(s ? await H.sample(s) : t), [v.sample || null, v.svg || null]);

  step('prepared');
  // A1: the external-script frame gives the same box as the old srcdoc frame, every time, in every engine.
  const a1 = out.accuracy = {};
  for (const [k, v] of Object.entries(FIX)) {
    const r = a1[k] = { same: 0, empty: 0, timeout: 0, other: 0, differs: [], retried: 0, maxAttempts: 0, avgMs: 0 };
    for (let i = 0; i < REPS; i++) {
      const m = await p.evaluate(svg => H.measure(svg), prepared[k]); r.avgMs += m.ms / REPS; r.maxAttempts = Math.max(r.maxAttempts, m.attempts); if (m.attempts) r.retried++;
      if (m.outcome === 'measured' && same(m.box, v.box)) r.same++; else if (m.outcome === 'measured') r.differs.push(m.box); else if (m.outcome === 'empty') r.empty++; else if (m.outcome === 'timeout') r.timeout++; else r.other++;
    }
    r.avgMs = Math.round(r.avgMs);
    // Detects a fallback that became the rule: every repetition must be measured, not the viewBox centre.
    if (r.same !== REPS) fail(e, 'A1 measure ' + k + ': ' + r.same + '/' + REPS + ' as before ' + JSON.stringify({ empty: r.empty, timeout: r.timeout, differs: r.differs.slice(0, 2) }));
  }

  step('A1');
  // A2: the Firefox control (no retry) and the limit (a box that stays 0 x 0).
  const ctl = out.zeroControl = {};
  for (const k of ['gradient', 'plain-rect', 'simple']) { let empty = 0; for (let i = 0; i < REPS; i++) { const m = await p.evaluate(([svg, u]) => H.measure(svg, { frameUrl: u }), [prepared[k], HARNESS.replace('/pages/measure.html', '/theme/assets/svg-motion/measure-frame.html?retry=0')]); if (m.outcome === 'empty') empty++; } ctl[k] = empty; }
  ctl.total = ctl.gradient + ctl['plain-rect'] + ctl.simple;
  // Not a pass condition (the 0 x 0 answer is timing-dependent); recorded so a run shows whether the retry was exercised.
  ctl.note = e === 'firefox' ? (ctl.total ? 'reproduced without retry' : 'not reproduced in this run') : 'not expected in this engine';
  const zeroSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><g data-omx-root=""><rect width="0" height="0"/></g></svg>';
  const lim = out.zeroLimit = await p.evaluate(svg => H.measure(svg), zeroSvg);
  if (lim.box !== null || lim.outcome !== 'empty' || lim.attempts !== 10 || lim.ms > 2000) fail(e, 'A2 a box that stays 0x0 must end as null after 10 retries within the budget ' + JSON.stringify(lim));

  step('A2');
  // A9: a frame that cannot answer (missing page) ends in the fallback at the timeout.
  const miss = out.missingFrame = await p.evaluate(([svg, u]) => H.measure(svg, { frameUrl: u }), [prepared.simple, '/theme/assets/svg-motion/missing.html']);
  if (miss.box !== null || miss.outcome !== 'timeout' || miss.ms < 2900 || miss.ms > 4500) fail(e, 'A9 missing frame ' + JSON.stringify(miss));

  step('A9');
  // A3a: an unsanitized hostile SVG sent straight to the frame reaches nothing; the same without the frame's CSP does (control).
  const run1 = 'm1' + e + Date.now(); await p.evaluate(() => H.rawFrame()); await p.evaluate(svg => H.send({ type: 'omx:measure', v: 1, id: 'a'.repeat(32), svg }), hostile(run1)); await p.waitForTimeout(1500);
  out.hostile = { withFrameCsp: await dump(run1) }; if (out.hostile.withFrameCsp.length) fail(e, 'A3 hostile SVG in the frame reached ' + out.hostile.withFrameCsp);
  const run2 = 'm2' + e + Date.now(); await p.evaluate(u => H.rawFrame(u), HARNESS.replace('/pages/measure.html', '/control/measure-frame-nocsp.html')); await p.evaluate(svg => H.send({ type: 'omx:measure', v: 1, id: 'b'.repeat(32), svg }), hostile(run2)); await p.waitForTimeout(1500);
  out.hostile.controlWithoutFrameCsp = await dump(run2); if (!out.hostile.controlWithoutFrameCsp.length) fail(e, 'A3 control: without the frame CSP the hostile SVG reached nothing (detector broken)');

  out.harness = await harnessSelfCheck(br, e, fail); step('harness self-check');
  step('A3a');
  // A3b: the frame answers only the parent, once, and only well-formed requests.
  await p.evaluate(() => H.rawFrame()); const ok = prepared.simple;
  await p.evaluate(svg => { H.send({ type: 'omx:measure', v: 2, id: 'c'.repeat(32), svg }); H.send({ type: 'omx:measure', v: 1, id: 'short', svg }); H.send({ type: 'omx:measure', v: 1, id: 'c'.repeat(32), svg: 123 }); H.send({ type: 'omx:measure', v: 1, id: 'c'.repeat(32), svg: 'a'.repeat(2097153) }); H.send({ type: 'other', v: 1 }); H.send('omx:measure'); }, ok);
  await p.waitForTimeout(400); const afterBad = await p.evaluate(() => H.replies());
  await p.evaluate(svg => { H.send({ type: 'omx:measure', v: 1, id: 'd'.repeat(32), svg }); H.send({ type: 'omx:measure', v: 1, id: 'e'.repeat(32), svg }); }, ok); await p.waitForTimeout(600); const afterGood = await p.evaluate(() => H.replies());
  out.protocol = { repliesToMalformed: afterBad.length, repliesToTwoValid: afterGood.map(r => r.id && r.id[0]) };
  if (afterBad.length) fail(e, 'A3 frame answered a malformed request ' + JSON.stringify(afterBad));
  if (afterGood.length !== 1 || afterGood[0].id !== 'd'.repeat(32) || !same(afterGood[0].box, FIX.simple.box)) fail(e, 'A3 frame must answer the first valid request only ' + JSON.stringify(afterGood));
  // A3c: a request from another frame (not the parent) is ignored.
  await p.evaluate(() => H.rawFrame());
  await p.evaluate(svg => new Promise(res => { const idx = [...document.querySelectorAll('iframe')].indexOf(window.__raw.f); const s = document.createElement('iframe'); s.setAttribute('sandbox', 'allow-scripts');
    s.srcdoc = '<script>parent.frames[' + idx + '].postMessage({type:"omx:measure",v:1,id:"' + 'f'.repeat(32) + '",svg:' + JSON.stringify(svg).replace(/</g, '\\u003c') + '},"*");<\/script>'; document.body.appendChild(s); setTimeout(res, 500); }), ok);
  const fromSibling = await p.evaluate(() => H.replies()); await p.evaluate(svg => H.send({ type: 'omx:measure', v: 1, id: '1'.repeat(32), svg }), ok); await p.waitForTimeout(500); const thenParent = await p.evaluate(() => H.replies());
  out.protocol.siblingIgnored = fromSibling.length === 0 && thenParent.length === 1 && thenParent[0].id === '1'.repeat(32);
  if (!out.protocol.siblingIgnored) fail(e, 'A3 frame accepted a request from a frame other than its parent ' + JSON.stringify({ fromSibling, thenParent }));
  // A3d: forged replies and ready messages from another frame do not change the parent's result.
  const forged = await p.evaluate(svg => { const s = document.createElement('iframe'); s.setAttribute('sandbox', 'allow-scripts');
    s.srcdoc = '<script>var n=0,t=setInterval(function(){parent.postMessage({type:"omx:ready",v:1},"*");parent.postMessage({type:"omx:measure",v:1,id:"' + '0'.repeat(32) + '",box:{x:1,y:2,width:3,height:4},attempts:0},"*");if(++n>100)clearInterval(t)},5);<\/script>';
    document.body.appendChild(s); return H.measure(svg); }, ok);
  out.protocol.forgedReplies = forged; if (!same(forged.box, FIX.simple.box)) fail(e, 'A3 a forged reply changed the measurement ' + JSON.stringify(forged));
  await ctx.close();

  step('A3');
  // A5, A7, A8 and the measured-not-fallback check on the real studio page, with the page CSP off and enforced.
  const files = {}; out.studio = {};
  for (const mode of ['off', 'enforce']) {
    const c = await br.newContext({ viewport: { width: 1280, height: 900 } }); const q = await c.newPage(); const errors = [];
    q.on('pageerror', x => errors.push(x.message));
    await c.addInitScript(() => { window.__violations = []; document.addEventListener('securitypolicyviolation', ev => window.__violations.push(ev.effectiveDirective + ' ' + String(ev.blockedURI).slice(0, 60)), true); });
    const resp = await q.goto(STUDIO + '?csp=' + mode); await q.waitForSelector('[data-svgm-ready="true"]');
    const s = out.studio[mode] = { header: (resp.headers()['content-security-policy'] || '') !== '' };
    if ((mode === 'enforce') !== s.header) fail(e, 'A10 page CSP header ' + mode + ' ' + s.header);
    // A7: no inline script on the page other than data.
    s.inlineScripts = await q.evaluate(() => [...document.scripts].filter(x => !x.src && x.type !== 'application/json').length); if (s.inlineScripts) fail(e, 'A7 inline scripts on the studio page: ' + s.inlineScripts);
    // The studio's own measurement on its own page: measured, not the fallback (the frame is the configured, versioned one).
    s.measure = await q.evaluate(async () => { const data = JSON.parse(document.getElementById('omx-svg-motion-data').textContent); const prep = x => OmxSvgFrame.prepare(x, Object.assign({ overlay: false }, data.budget));
      const out = []; for (const smp of data.samples) { const r = await OmxSvgImport.fromText(smp.svg, { prepare: prep }); out.push((await OmxSvgFrame.measure(prep(r.svg).svg, { detail: true })).outcome); } return out; });
    if (s.measure.some(o => o !== 'measured')) fail(e, 'studio page measure under CSP ' + mode + ': ' + s.measure);
    // L-3: X-Frame-Options in every mode.
    s.xfo = resp.headers()['x-frame-options'] || ''; if (s.xfo !== 'SAMEORIGIN') fail(e, 'L-3 X-Frame-Options ' + mode + ': ' + s.xfo);
    if (mode === 'enforce') {
      // T-3: every script the page loads is named exactly in script-src, and script-src has nothing else but the hash.
      const policy = resp.headers()['content-security-policy'] || ''; const sources = ((policy.match(/script-src ([^;]*)/) || [])[1] || '').trim().split(/\s+/);
      const loaded = await q.evaluate(() => [...document.scripts].filter(x => x.src).map(x => x.src.split('?')[0]));
      s.scriptSources = sources.length; s.scriptsLoaded = loaded.length;
      if (loaded.some(u => !sources.includes(u))) fail(e, 'T-3 a loaded script is not named in script-src: ' + loaded.filter(u => !sources.includes(u)));
      if (sources.some(x => x === "'self'" || /\/$/.test(x) || /unsafe/.test(x))) fail(e, 'T-3 script-src has a broad source: ' + sources.join(' '));
      // T-4: JSONP and the bypass routes, injected as an attacker who can add markup would; none may run.
      const ROUTES = {
        'wp-json': '/wp-json/?_jsonp=postMessage', 'root + rest_route': '/?rest_route=/&_jsonp=postMessage', 'studio page + rest_route': '/learn/svg-motion/?rest_route=/&_jsonp=postMessage',
        'missing file in the studio JS folder': '/wp-theme/omochix-theme/assets/js/svg-motion/nope.js?rest_route=/&_jsonp=postMessage',
        'listed file + rest_route': '/wp-theme/omochix-theme/assets/js/svg-motion/store.js?rest_route=/&_jsonp=postMessage',
        'path after a listed file': '/wp-theme/omochix-theme/assets/js/svg-motion/store.js/x?rest_route=/&_jsonp=postMessage',
        'percent-encoded listed file': '/wp-theme/omochix-theme/assets/js/svg-motion/%73tore.js?rest_route=/&_jsonp=postMessage',
        'upper-case listed file': '/wp-theme/omochix-theme/assets/js/svg-motion/STORE.JS?rest_route=/&_jsonp=postMessage',
        'scheme-relative wp-json': '//127.0.0.1:8788/wp-json/?_jsonp=postMessage',
      };
      const inject = async (page, route) => page.evaluate(r => new Promise(res => { let n = 0; const on = ev => { if (ev.data && typeof ev.data === 'object' && 'name' in ev.data && 'url' in ev.data) n++; }; addEventListener('message', on);
        const sc = document.createElement('script'); sc.src = r; document.body.appendChild(sc); setTimeout(() => { removeEventListener('message', on); res(n); }, 500); }), route);
      // On a page of its own: these injections cause CSP violations on purpose, and the main page must stay at 0.
      const jq = await c.newPage(); await jq.goto(STUDIO + '?csp=enforce'); await jq.waitForSelector('[data-svgm-ready="true"]');
      s.jsonp = {}; for (const [label, route] of Object.entries(ROUTES)) { s.jsonp[label] = await inject(jq, route); if (s.jsonp[label]) fail(e, 'T-4 JSONP ran through ' + label + ' under the studio CSP'); }
      await jq.close();
      // Positive control: the same page with script-src 'self' (the policy V-2 replaced) lets the wp-json route run.
      const cq = await c.newPage(); await cq.goto(STUDIO + '?csp=enforce-self'); await cq.waitForSelector('[data-svgm-ready="true"]');
      s.jsonpControl = { wpJson: await inject(cq, ROUTES['wp-json']), missingFile: await inject(cq, ROUTES['missing file in the studio JS folder']), listedFile: await inject(cq, ROUTES['listed file + rest_route']) }; await cq.close();
      if (!s.jsonpControl.wpJson || !s.jsonpControl.missingFile) fail(e, 'T-4 control: with script-src \'self\' the JSONP routes did not run (the test cannot see JSONP) ' + JSON.stringify(s.jsonpControl));
      if (s.jsonpControl.listedFile) fail(e, 'T-4 control: a listed (existing) file + rest_route answered JSONP; the stub no longer models a real server');
    }
    // A5: 3 samples x 19 presets, the three downloads captured in the page (the real download path; saving is not in scope here).
    await q.evaluate(() => { window.__blobs = new Map(); window.__dl = []; const o = URL.createObjectURL; URL.createObjectURL = function (b) { const u = o.apply(this, arguments); window.__blobs.set(u, b); return u; };
      HTMLAnchorElement.prototype.click = function () { const b = window.__blobs.get(this.href); const r = { name: this.download }; window.__dl.push(r); if (b) b.text().then(t => { r.text = t; }); }; });
    const ids = await q.evaluate(() => [...document.querySelectorAll('input[name="svgm-preset"]')].map(i => i.value)); let blank = 0;
    for (const sample of ['prism', 'orbit', 'stack']) {
      await q.evaluate(v => { const r = document.querySelector('input[name="svgm-sample"][value="' + v + '"]'); r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }, sample); await q.waitForTimeout(300);
      for (const id of ids) {
        const iter = Date.now();
        await q.evaluate(v => { const i = document.querySelector('input[name="svgm-preset"][value="' + v + '"]'); i.checked = true; i.dispatchEvent(new Event('change', { bubbles: true })); }, id);
        // Ready = the chosen preset, an export, and download buttons that are enabled. The export attribute alone is not
        // enough: it keeps the logo's scope while the studio rebuilds (refreshExport disables the buttons for ~120 ms).
        await q.waitForFunction(v => { const n = document.querySelector('[data-svg-motion]'); const b = [...document.querySelectorAll('[data-svgm-download],[data-svgm-json-download]')];
          return n.getAttribute('data-svgm-preset') === v && /^omx-[0-9a-f]{8}$/.test(n.getAttribute('data-svgm-export') || '') && b.length === 3 && b.every(x => !x.disabled); }, id, { timeout: 10000 }).catch(() => fail(e, 'A5 export not ready ' + mode + ' ' + sample + ' ' + id));
        if (!(await q.evaluate(() => { const i = document.querySelector('[data-svgm-preview]'); return !!i && i.complete && i.naturalWidth > 0; }))) blank++;
        // Wait for the three files themselves (with a limit), not for a fixed pause; the export is built asynchronously.
        const got = await q.evaluate(async () => { window.__dl.length = 0; document.querySelector('[data-svgm-download="animated"]').click(); document.querySelector('[data-svgm-download="still"]').click(); document.querySelector('[data-svgm-json-download]').click();
          const t = Date.now(); while (Date.now() - t < 5000 && !(window.__dl.length === 3 && window.__dl.every(d => d.text))) await new Promise(r => setTimeout(r, 20)); return window.__dl.map(d => ({ name: d.name, text: d.text || '' })); });
        const took = Date.now() - iter; (s.slowest = s.slowest || { ms: 0 }); if (took > s.slowest.ms) s.slowest = { ms: took, sample, id }; s.totalMs = (s.totalMs || 0) + took;
        if (took > 10000) fail(e, 'A5 one export took ' + took + ' ms (' + mode + ' ' + sample + ' ' + id + ')');
        for (const d of got) { const key = sample + '/' + id + '/' + d.name; (files[key] = files[key] || {})[mode] = d.text ? sha(d.text) : 'EMPTY'; }
        if (got.length !== 3 || got.some(d => !d.text)) fail(e, 'A5 downloads ' + mode + ' ' + sample + ' ' + id + ' ' + got.map(d => d.name + ':' + (d.text || '').length));
      }
    }
  step('A5 ' + mode + '');
    s.previewsBlank = blank; if (blank) fail(e, 'A5 blank previews under CSP ' + mode + ': ' + blank);
    // Thumbnails (lazy) and an upload.
    await q.evaluate(async () => { for (const i of document.querySelectorAll('[data-svgm-thumb]')) { i.scrollIntoView({ block: 'center' }); await new Promise(r => setTimeout(r, 60)); } scrollTo(0, 0); }); await q.waitForTimeout(800);
    s.thumbs = await q.evaluate(() => { const t = [...document.querySelectorAll('[data-svgm-thumb]')]; return t.filter(i => i.complete && i.naturalWidth > 0).length + '/' + t.length; }); if (!/^(\d+)\/\1$/.test(s.thumbs)) fail(e, 'thumbnails under CSP ' + mode + ' ' + s.thumbs);
    await q.evaluate(() => document.querySelector('[data-svg-motion]').removeAttribute('data-svgm-last')); await q.setInputFiles('[data-svgm-file]', { name: 'gradient.svg', mimeType: 'image/svg+xml', buffer: Buffer.from(FIX.gradient.svg) });
    await q.waitForFunction(() => document.querySelector('[data-svg-motion]').getAttribute('data-svgm-last'), null, { timeout: 10000 }).catch(() => {}); s.upload = await q.getAttribute('[data-svg-motion]', 'data-svgm-last'); if (s.upload !== 'ok') fail(e, 'upload under CSP ' + mode + ' ' + s.upload);
    // A8: a blob: SVG with a script, opened in a new tab (stands for a sanitizer miss): runs without the page CSP (control), not with it.
    const pop = c.waitForEvent('page', { timeout: 5000 }).catch(() => null); await q.evaluate(t => window.open(URL.createObjectURL(new Blob([t], { type: 'image/svg+xml' })), '_blank'), EVIL_TAB); const tab = await pop;
    if (tab) { await tab.waitForTimeout(1000); s.blobTab = tab.isClosed() ? 'closed' : ((await tab.title().catch(() => '')) === 'RAN' ? 'script ran' : 'no script'); await tab.close().catch(() => {}); } else s.blobTab = 'no window';
    if (mode === 'enforce' && s.blobTab === 'script ran') fail(e, 'A8 a blob: tab ran script under the page CSP');
    if (mode === 'off' && s.blobTab !== 'script ran') fail(e, 'A8 control: the blob: tab ran nothing without the page CSP (' + s.blobTab + ')');
    s.violations = await q.evaluate(() => window.__violations); if (mode === 'enforce' && s.violations.length) fail(e, 'CSP violations on the studio page: ' + s.violations.slice(0, 5));
    s.errors = errors; if (errors.length) fail(e, 'studio page errors under CSP ' + mode + ': ' + errors.slice(0, 3));
    await c.close();
  }
  const keys = Object.keys(files); const differ = keys.filter(k => files[k].off !== files[k].enforce || files[k].off === 'EMPTY');
  out.exports = { files: keys.length, identical: keys.length - differ.length, differ: differ.slice(0, 5) };
  if (keys.length !== 3 * 19 * 3) fail(e, 'A5 expected 171 exported files, got ' + keys.length);
  if (differ.length) fail(e, 'A5 exports differ with and without the page CSP: ' + differ.slice(0, 5));
  fs.writeFileSync(path.join(__dirname, 'results', 'csp-' + e + '.json'), JSON.stringify(out, null, 1));
  return { harness: { synthetic: out.harness.synthetic, real: out.harness.real }, accuracy: Object.fromEntries(Object.entries(a1).map(([k, v]) => [k, v.same + '/' + REPS + (v.retried ? ' (retried ' + v.retried + ')' : '')])), zeroControl: ctl, zeroLimit: { attempts: lim.attempts, ms: lim.ms }, missingFrameMs: miss.ms, hostile: { withFrameCsp: out.hostile.withFrameCsp.length, control: out.hostile.controlWithoutFrameCsp.length }, protocol: out.protocol.siblingIgnored && !out.protocol.repliesToMalformed, exports: out.exports, studio: Object.fromEntries(Object.entries(out.studio).map(([m, s]) => [m, { exportLoopMs: s.totalMs, slowest: s.slowest, header: s.header, violations: s.violations.length, thumbs: s.thumbs, upload: s.upload, blobTab: s.blobTab, measure: s.measure.join(','), xfo: s.xfo, scriptSources: s.scriptSources, scriptsLoaded: s.scriptsLoaded, jsonp: s.jsonp, jsonpControl: s.jsonpControl }])) };
}
module.exports = { cspTests };
