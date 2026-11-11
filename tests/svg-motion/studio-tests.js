// Drives the real studio page (real template, CSS and scripts on a stub WordPress) the way a person would.
const fs = require('fs'), path = require('path'), vm = require('vm');
const U = 'http://127.0.0.1:8788/learn/svg-motion/', R = path.join(__dirname, 'results');
const sandbox = { window: {} }; vm.runInNewContext(fs.readFileSync(path.join(__dirname, 'fixtures/corpus.js'), 'utf8'), sandbox);
const { ATTACKS, BOMBS, LOGOS } = sandbox.window;

// ---- CSP reports in the console (harness policy; csp-tests.js checks it with synthetic and real negative controls) ----
// Under an enforced page CSP, Chromium reports (never runs) on* attributes, and refuses a <base href>, that DOMParser meets
// in an untrusted SVG inside safety.js. Execution stays covered by the hit, marker and canary detectors. Such a report is
// set aside ONLY when all of these hold; anything else in the console is an error (P1, P2, P6):
//   1. the page CSP is enforced;  2. the message is one of the two known kinds;  3. it comes from safety.js;
//   4. it arrives while an attack is being sent (ctl.window), and that attack is one of the six whose SVG has a LIVE on*
//      attribute or <base> element after XML parsing (ALLOWED_ATTACKS, an explicit list: V-4, 2026-11-10). Normal operation
//      has no window, so it is never set aside. The nine corpus cases whose text only mentions on* or <base> (escaped, in
//      CDATA or a comment, inside an attribute value, or in upper case) are mXSS / break-out probes: a report from one of
//      them would mean the sanitizer let something live through, so it is an error (csp-tests.js checks both lists).
const ALLOWED_ATTACKS = new Set(['onload', 'onerrorImg', 'onbegin', 'onclickAuto', 'foreignImg', 'base']);
const MENTION_ONLY_ATTACKS = new Set(['onUpper', 'cssStyleBreak', 'mxssTitle', 'mxssStyle', 'mxssDesc', 'mxssMath', 'mxssText', 'mxssComment', 'idBreak']);
const GENERIC_CONSOLE = /404|Failed to load resource|Not allowed to load|XML Parsing Error/;
const KNOWN_CSP_REPORT = /inline event handler|Setting the document's base URI/;
const KNOWN_SOURCE = /\/svg-motion\/safety\.js$/;
const KNOWN_CAP_PER_KEY = 2; // Chromium reports each allowed attack once (csp-full-run7); Firefox and WebKit not at all
/** @returns {null|'known'|string} null: not an error here; 'known': set aside (counted in ctl.known); string: the error text. */
function consoleVerdict(type, text, sourceUrl, ctl, enforce) {
  if (type !== 'error' || GENERIC_CONSOLE.test(text)) return null;
  if (enforce && /\/control\/blocked/.test(text)) return null; // the detector control: a request the page CSP is meant to stop
  if (enforce && KNOWN_CSP_REPORT.test(text) && KNOWN_SOURCE.test(sourceUrl || '')) {
    const w = ctl && ctl.window;
    if (w && w.allowed) { ctl.known[w.key] = (ctl.known[w.key] || 0) + 1; return 'known'; }
    return 'unexpected CSP report outside an allowed attack window (' + (w ? w.key + ' is not an allowed attack' : 'no attack is being sent') + '): ' + text.slice(0, 160);
  }
  return text;
}
/** Runs fn with the attack window open (reports in it may be set aside if the attack is allowed); always closes it, after the console has caught up. */
async function inWindow(p, ctl, key, allowed, fn) {
  ctl.window = { key, allowed: !!allowed };
  try { return await fn(); } finally { await p.evaluate(() => 0).catch(() => {}); ctl.window = null; }
}

async function open(ctx, errors, url, ctl) {
  const p = await ctx.newPage();
  p.on('pageerror', x => errors.push('pageerror: ' + x.message));
  const enforce = ctl && ctl.enforce !== undefined ? ctl.enforce : process.env.SVGM_CSP === 'enforce';
  p.on('console', m => {
    const loc = m.location() || {}; const v = consoleVerdict(m.type(), m.text(), loc.url, ctl, enforce);
    if (v && v !== 'known') errors.push(v + (loc.url ? ' @ ' + loc.url.slice(0, 80) + ':' + loc.lineNumber : ''));
  });
  // Test instrumentation: keep the Blob behind every object URL the page makes, so a test reads it without fetch()
  // (under the studio's CSP, connect-src 'self' blocks fetch(blob:), as it should). Same URLs, nothing else changes.
  await p.addInitScript(() => { if (window.__blobOf) return; const m = new Map(); window.__blobOf = m; const make = URL.createObjectURL, drop = URL.revokeObjectURL;
    URL.createObjectURL = function (b) { const u = make.apply(this, arguments); m.set(u, b); return u; }; URL.revokeObjectURL = function (u) { m.delete(u); return drop.apply(this, arguments); };
    window.__readBlob = u => { const b = m.get(u); return b ? b.text() : fetch(u).then(r => r.text()); }; });
  await p.goto(url || U); await p.waitForSelector('[data-svgm-ready="true"]'); await p.waitForSelector('[data-svgm-preview]');
  await p.evaluate(() => {
    window.__ink = b64 => new Promise((res, rej) => { const i = new Image(); i.onload = () => { const c = document.createElement('canvas'); c.width = i.width; c.height = i.height; const x = c.getContext('2d'); x.drawImage(i, 0, 0); const d = x.getImageData(0, 0, c.width, c.height).data; let bg = [d[0], d[1], d[2]], n = 0, m = 0; for (let j = 0; j < d.length; j += 4) { if (Math.abs(d[j] - bg[0]) + Math.abs(d[j + 1] - bg[1]) + Math.abs(d[j + 2] - bg[2]) > 30) n++; if (Math.abs(d[j] - 0x12) < 28 && Math.abs(d[j + 1] - 0x34) < 28 && Math.abs(d[j + 2] - 0x56) < 28) m++; } res({ ink: +(n / (d.length / 4) * 100).toFixed(2), marker: m }); }; i.onerror = rej; i.src = 'data:image/png;base64,' + b64; });
    window.__diff = (a, b) => Promise.all([a, b].map(u => new Promise((res, rej) => { const i = new Image(); i.onload = () => { const c = document.createElement('canvas'); c.width = i.width; c.height = i.height; const x = c.getContext('2d'); x.drawImage(i, 0, 0); res(x.getImageData(0, 0, c.width, c.height).data); }; i.onerror = rej; i.src = 'data:image/png;base64,' + u; }))).then(([p, q]) => { if (p.length !== q.length) return 100; let n = 0; for (let i = 0; i < p.length; i += 4) if (Math.abs(p[i] - q[i]) + Math.abs(p[i + 1] - q[i + 1]) + Math.abs(p[i + 2] - q[i + 2]) > 30) n++; return +(n / (p.length / 4) * 100).toFixed(2); });
    window.__previewText = () => window.__readBlob(document.querySelector('[data-svgm-preview]').src);
    // Reference raster and displayed raster at one size, from their SVG text.
    window.__rasterDiff = async (a, b) => { const r = s => new Promise((ok, no) => { const d = new DOMParser().parseFromString(s, 'image/svg+xml'), e = d.documentElement; e.setAttribute('width', 200); e.setAttribute('height', 200); const i = new Image(); i.onload = () => { const c = document.createElement('canvas'); c.width = c.height = 200; const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, 200, 200); x.drawImage(i, 0, 0, 200, 200); ok(x.getImageData(0, 0, 200, 200).data); }; i.onerror = () => no(new Error('img')); i.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(d)); }); const [p, q] = await Promise.all([r(a), r(b)]); let n = 0, ink = 0; for (let i = 0; i < p.length; i += 4) { if (Math.abs(p[i] - q[i]) + Math.abs(p[i + 1] - q[i + 1]) + Math.abs(p[i + 2] - q[i + 2]) > 48) n++; if (p[i] + p[i + 1] + p[i + 2] < 740) ink++; } return { diff: +(n / 40000 * 100).toFixed(2), ink }; };
  });
  return p;
}
const shot = async p => (await p.locator('[data-svgm-stage]').screenshot()).toString('base64');
const ink = (p, b) => p.evaluate(x => __ink(x), b);
const diff = (p, a, b) => p.evaluate(([x, y]) => __diff(x, y), [a, b]);
const attr = (p, n) => p.getAttribute('[data-svg-motion]', n);
async function upload(p, text, opt) { opt = opt || {}; await p.evaluate(() => document.querySelector('[data-svg-motion]').removeAttribute('data-svgm-last')); await p.setInputFiles('[data-svgm-file]', { name: opt.name || 'logo.svg', mimeType: opt.type === undefined ? 'image/svg+xml' : opt.type, buffer: Buffer.isBuffer(text) ? text : Buffer.from(text) }); await p.waitForSelector('[data-svg-motion][data-svgm-last]'); await p.waitForTimeout(opt.settle === undefined ? 90 : opt.settle); return attr(p, 'data-svgm-last'); }
async function paste(p, text) { await p.evaluate(t => { const m = document.querySelector('[data-svg-motion]'); m.removeAttribute('data-svgm-last'); document.querySelector('[data-svgm-paste]').value = t; document.querySelector('[data-svgm-paste-button]').click(); }, text); await p.waitForSelector('[data-svg-motion][data-svgm-last]'); await p.waitForTimeout(90); return attr(p, 'data-svgm-last'); }
const choose = async (p, id) => { await p.evaluate(v => { const i = document.querySelector('input[name="svgm-preset"][value="' + v + '"]'); i.checked = true; i.dispatchEvent(new Event('change', { bubbles: true })); }, id); await p.waitForTimeout(110); };
const setControl = async (p, attribute, key, value) => { await p.evaluate(([a, k, v]) => { const i = document.querySelector('[' + a + '="' + k + '"]'); i.value = v; i.dispatchEvent(new Event('input', { bubbles: true })); }, [attribute, key, value]); await p.waitForTimeout(90); };

const EXPECT_REJECT = { scriptNs: 'NOT_SVG', xxe: 'DOCTYPE_REJECTED', dtdExt: 'DOCTYPE_REJECTED', onload: 'EMPTY_RESULT', onUpper: 'EMPTY_RESULT', xmlStylesheet: 'EMPTY_RESULT' };
const EXPECT_BOMB = { useBomb: /USE_EXPANSION_LIMIT|ELEMENT_LIMIT/, useCycle: /EMPTY_RESULT|ok/, laughs: /DOCTYPE/, deep: /DEPTH/, manyEls: /ELEMENT/, hugeFile: /TOO_LARGE/, hugePath: /PATH/, malformed: /PARSE/, notSvg: /NOT_SVG/, empty: /EMPTY_INPUT/, plain: /PARSE/ };

async function uploadSecurity(br, e, fail) {
  const C = 'http://127.0.0.1:8771', run = 'up' + e + Date.now(), out = { routes: {} }; const errors = [];
  const ctx = await br.newContext({ reducedMotion: 'reduce', viewport: { width: 1280, height: 900 } });
  await ctx.addInitScript(() => { window.__hits = []; addEventListener('message', ev => { if (ev.data && ev.data.hit) window.__hits.push(ev.data.hit); }); });
  const ctl = { window: null, known: {} };
  const p = await open(ctx, errors, undefined, ctl);
  const outbound = []; p.on('request', q => { const u = q.url(); if (!/^(data:|blob:)/.test(u) && !u.startsWith('http://127.0.0.1:8788/')) outbound.push(u); if (q.method() !== 'GET' && !u.startsWith('data:')) outbound.push(q.method() + ' ' + u); });
  await upload(p, LOGOS.simple); const known = await shot(p);
  // Controls: each detector must fire on a real event, or a zero below means nothing.
  const ctlHit = await p.evaluate(async () => { window.postMessage({ hit: 'control' }, '*'); await new Promise(r => setTimeout(r, 50)); const seen = window.__hits.includes('control'); window.__hits = []; return seen; }); let cspBlockedControl = null;
  if (process.env.SVGM_CSP === 'enforce') {
    // The studio page's CSP blocks this request, as it should (checked below); the detectors are proven from a page
    // without that CSP, with the same listener logic.
    cspBlockedControl = await p.evaluate(u => new Promise(res => { let v = false; const on = ev => { if (String(ev.blockedURI).includes('/control/')) v = true; }; document.addEventListener('securitypolicyviolation', on); fetch(u, { mode: 'no-cors' }).catch(() => {}).finally(() => setTimeout(() => { document.removeEventListener('securitypolicyviolation', on); res(v); }, 100)); }), C + '/c/' + run + '/control/blocked');
    const pc = await p.context().newPage(); pc.on('request', q => { const u = q.url(); if (!/^(data:|blob:)/.test(u) && !u.startsWith('http://127.0.0.1:8788/')) outbound.push(u); });
    await pc.evaluate(u => fetch(u, { mode: 'no-cors' }).catch(() => {}), C + '/c/' + run + '/control/x'); await pc.close();
    if (!cspBlockedControl) fail(e, 'control: the studio page CSP did not block a request to the canary');
  } else { await p.evaluate(u => fetch(u, { mode: 'no-cors' }).catch(() => {}), C + '/c/' + run + '/control/x'); }
  await p.waitForTimeout(100); const ctlNet = (await (await fetch(C + '/__dump?run=' + run)).json()).some(u => u.includes('/control/')); const ctlOut = outbound.some(u => u.includes('/control/')); outbound.length = 0; const ctlMarker = await upload(p, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect id="omxkeep" x="80" y="80" width="12" height="16" fill="#123456"/></svg>').then(() => shot(p)).then(x => ink(p, x)); const noMarker = await upload(p, LOGOS.simple).then(() => shot(p)).then(x => ink(p, x)); out.controls = { cspBlockedControl, hit: ctlHit, canary: ctlNet, outbound: ctlOut, marker: ctlMarker.marker, noMarker: noMarker.marker }; if (!ctlHit) fail(e, 'control: hit detector dead'); if (!ctlNet) fail(e, 'control: canary dead'); if (!ctlOut) fail(e, 'control: outbound detector dead'); if (ctlMarker.marker <= 0) fail(e, 'control: marker detector dead'); if (noMarker.marker > 0) fail(e, 'control: marker detector fires on anything');
  for (const [route, send] of [['file', t => upload(p, t, { settle: 120 })], ['paste', t => paste(p, t)]]) {
    const r = out.routes[route] = { accepted: 0, rejected: {}, markerPainted: 0, hadMarker: 0, lost: [], exec: [], blankAccepted: [] };
    for (const k in ATTACKS) {
      const text = ATTACKS[k].split('{C}').join(C + '/c/' + run + '/' + route + '/' + k + '/');
      await upload(p, LOGOS.simple); // a known logo before each, to prove rejection keeps it
      const res = await inWindow(p, ctl, route + ':' + k, ALLOWED_ATTACKS.has(k), () => send(text));
      const had = ATTACKS[k].includes('omxkeep'); const s = await shot(p); const v = await ink(p, s);
      if (res === 'ok') { r.accepted++; if (v.ink <= 0) r.blankAccepted.push(k); if (had) { r.hadMarker++; if (v.marker > 0) r.markerPainted++; else r.lost.push(k); } if (!had && k in EXPECT_REJECT) fail(e, route + ' should reject ' + k); }
      else { r.rejected[k] = res; if (EXPECT_REJECT[k] !== res) fail(e, route + ' unexpected rejection ' + k + ' ' + res); if ((await diff(p, s, known)) > 0.5) fail(e, route + ' lost the current logo after rejecting ' + k); const msg = await p.evaluate(() => { const a = document.querySelector('[data-svgm-error]'); return a.hidden ? '' : a.textContent; }); if (!/[ぁ-ん一-龠]/.test(msg)) fail(e, route + ' no Japanese reason for ' + k); }
    }
    r.exec = await p.evaluate(() => [...new Set(window.__hits)]);
    if (r.exec.length) fail(e, route + ' executed ' + r.exec); if (r.lost.length) fail(e, route + ' marker not painted ' + r.lost); if (r.blankAccepted.length) fail(e, route + ' blank accepted ' + r.blankAccepted);
    if (r.hadMarker !== 63) fail(e, route + ' expected 63 markers, got ' + r.hadMarker);
    const inline = await p.evaluate(() => ({ svgWithOurIds: document.querySelectorAll('[data-svg-motion] svg, [id^="omx-"]:not(#omx-svg-motion-data)').length, stageChildren: [...document.querySelector('[data-svgm-stage]').children].map(c => c.tagName), iframes: document.querySelectorAll('[data-svg-motion] iframe').length, url: location.href })); r.parent = inline; if (inline.svgWithOurIds) fail(e, route + ' logo content in the parent DOM'); if (inline.iframes) fail(e, route + ' iframe present'); if (inline.stageChildren.join() !== 'IMG') fail(e, route + ' stage is not an image: ' + inline.stageChildren); if (/<svg|%3Csvg/i.test(inline.url)) fail(e, route + ' logo in URL');
  }
  await p.waitForTimeout(500); out.canary = (await (await fetch(C + '/__dump?run=' + run)).json()).filter(u => !u.includes('/control/')); if (out.canary.length) fail(e, 'upload route reached the canary: ' + out.canary.slice(0, 5)); out.outbound = [...new Set(outbound)]; if (out.outbound.length) fail(e, 'outbound ' + out.outbound.slice(0, 3));
  const b = out.bombs = {};
  for (const k in BOMBS) { await upload(p, LOGOS.simple); const t0 = Date.now(); const res = await inWindow(p, ctl, 'bomb:' + k, false, async () => BOMBS[k] === '' ? await paste(p, '') : (k === 'hugeFile' ? await upload(p, BOMBS[k]) : await upload(p, BOMBS[k]))); b[k] = { result: res, ms: Date.now() - t0 }; if (!EXPECT_BOMB[k].test(res)) fail(e, 'bomb ' + k + ' gave ' + res); const s = await shot(p); if (res !== 'ok' && (await diff(p, s, known)) > 0.5) fail(e, 'bomb lost the logo: ' + k); }
  // Normal logos: what is displayed must match the source, through the upload route.
  const knownBeforeLogos = JSON.stringify(ctl.known); // P6: the normal logos below run with no attack window
  const l = out.logos = {};
  for (const k in LOGOS) { const src = LOGOS[k].replace(/^<\?xml[^>]*\?>/, ''); const res = await upload(p, LOGOS[k]); if (res !== 'ok') { fail(e, 'logo refused ' + k + ' ' + res); continue; } const d = await p.evaluate(s => __previewText().then(t => __rasterDiff(s, t)), src); l[k] = d; if (d.ink <= 0) fail(e, 'logo reference blank ' + k); if (d.diff !== 0) fail(e, 'logo differs ' + k + ' ' + d.diff); }
  if (JSON.stringify(ctl.known) !== knownBeforeLogos) fail(e, 'P6 normal logos produced CSP reports that were set aside: ' + JSON.stringify(ctl.known));
  // Hostile data placed directly in storage goes through the pipeline again on load.
  const hostile = ATTACKS.foreignImg.split('{C}').join(C + '/c/' + run + '/idb/');
  const idbPut = (svg, schema) => p.evaluate(([s, sc]) => new Promise((ok, no) => { const r = indexedDB.open('omx-svg-motion', 1); r.onsuccess = () => { const t = r.result.transaction('state', 'readwrite'); t.objectStore('state').put({ schema: sc, svg: s, settings: { preset: 'logo-pop' } }, 'current'); t.oncomplete = () => { r.result.close(); ok(); }; t.onerror = no; }; }), [svg, schema]);
  await p.waitForTimeout(300); await idbPut(hostile + '<script>parent.postMessage({hit:"idb"},"*")</script>', 1); await inWindow(p, ctl, 'idb:foreignImg', ALLOWED_ATTACKS.has('foreignImg'), async () => { await p.goto(U); await p.waitForSelector('[data-svgm-ready="true"]'); await p.waitForTimeout(300); }); out.idbHostile = { hits: await p.evaluate(() => window.__hits), logo: await attr(p, 'data-svgm-logo'), inlineIds: await p.evaluate(() => document.querySelectorAll('[data-svg-motion] svg, [id^="omx-"]:not(#omx-svg-motion-data)').length) }; if (out.idbHostile.hits.length || out.idbHostile.inlineIds) fail(e, 'stored hostile data ' + JSON.stringify(out.idbHostile));
  // P2: what was set aside is recorded and bounded; only allowed attacks (windows) can appear here.
  const knownKeys = Object.keys(ctl.known); const enforced = process.env.SVGM_CSP === 'enforce';
  out.cspKnownReports = { enforced, allowedAttacks: ALLOWED_ATTACKS.size, total: knownKeys.reduce((n, k) => n + ctl.known[k], 0), byKey: ctl.known };
  for (const k of knownKeys) { const attack = k.split(':')[1]; if (!ALLOWED_ATTACKS.has(attack)) fail(e, 'P2 a CSP report was set aside for an attack that is not allowed: ' + k); if (ctl.known[k] > KNOWN_CAP_PER_KEY) fail(e, 'P2 too many CSP reports set aside for ' + k + ': ' + ctl.known[k]); }
  if (!enforced && knownKeys.length) fail(e, 'P2 CSP reports were set aside without an enforced page CSP');
  await ctx.close(); out.errors = errors; if (errors.length) fail(e, 'upload console: ' + errors.slice(0, 3).join(' | '));
  fs.writeFileSync(path.join(R, 'upload-security-' + e + '.json'), JSON.stringify(out, null, 1)); return out;
}

async function pageTests(br, e, fail) {
  const out = {}; const errors = [];
  const shots = { 375: null };
  for (const [w, h] of [[375, 812], [768, 1024], [1440, 1000]]) {
    const ctx = await br.newContext({ viewport: { width: w, height: h } }); const p = await open(ctx, errors); await p.waitForTimeout(500);
    const m = await p.evaluate(() => { const cards = [...document.querySelectorAll('[data-svgm-card]')]; const xs = new Set(cards.slice(0, 6).map(c => Math.round(c.getBoundingClientRect().left))); const small = [...document.querySelectorAll('.svg-motion button,.svg-motion__sample,.svg-motion select,.svg-motion__file,.svg-motion summary')].filter(el => el.offsetParent && el.getBoundingClientRect().height < 44).map(el => el.className || el.tagName); return { scrollW: document.documentElement.scrollWidth, innerW: innerWidth, cards: cards.length, columns: xs.size, small, stage: [...document.querySelector('[data-svgm-stage]').children].map(c => c.tagName).join(), iframes: document.querySelectorAll('[data-svg-motion] iframe').length }; });
    // Everything must be really pressable, not just present: nothing may cover it.
    const covered = await p.evaluate(() => [...document.querySelectorAll('.svg-motion button,.svg-motion input,.svg-motion select,.svg-motion summary,.svg-motion__file')].filter(el => { if (!el.offsetParent || el.classList.contains('sr-only') || el.closest('details:not([open])') && el.tagName !== 'SUMMARY') return false; el.scrollIntoView({ block: 'center', behavior: 'instant' }); const r = el.getBoundingClientRect(); const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); return !(hit && (hit === el || el.contains(hit) || hit.contains(el) || (el.closest('label') && el.closest('label').contains(hit)))); }).map(el => el.getAttribute('data-svgm-toggle') !== null ? 'toggle' : (el.name || el.className || el.tagName))); await p.evaluate(() => window.scrollTo(0, 0)); m.covered = covered;
    out['w' + w] = m; if (covered.length) fail(e, w + ' covered controls: ' + covered.slice(0, 6)); if (m.scrollW > m.innerW) fail(e, w + ' horizontal scroll'); if (m.cards !== 19) fail(e, w + ' cards ' + m.cards); if (m.small.length) fail(e, w + ' small targets ' + m.small); if (m.iframes) fail(e, w + ' iframe in the page'); if (m.stage !== 'IMG') fail(e, w + ' stage ' + m.stage);
    if (e === 'chromium') await p.screenshot({ path: path.join(R, 'layout-' + w + '.png') });
    if (w < 1081) { const seen = await p.evaluate(async () => { document.documentElement.style.scrollBehavior = 'auto'; const card = [...document.querySelectorAll('[data-svgm-card]')].pop(); card.scrollIntoView({ block: 'center' }); await new Promise(r => setTimeout(r, 250)); const a = document.querySelector('[data-svgm-stage]').getBoundingClientRect(); return { stageTop: Math.round(a.top), stageBottom: Math.round(a.bottom), inner: innerHeight }; }); out['w' + w].sticky = seen; if (seen.stageTop < 0 || seen.stageBottom > seen.inner) fail(e, w + ' preview left the screen'); if (e === 'chromium') await p.screenshot({ path: path.join(R, 'layout-' + w + '-choosing.png') }); }
    // A phone can do the whole flow.
    if (w === 375) { const r = await upload(p, LOGOS.gradient); if (r !== 'ok') fail(e, '375 upload ' + r); await choose(p, 'logo-wipe'); await setControl(p, 'data-svgm-param', 'duration', 1200); const after = await ink(p, await shot(p)); out.w375.flowInk = after.ink; }
    await ctx.close();
  }
  const ctx = await br.newContext({ viewport: { width: 1440, height: 1000 }, acceptDownloads: false }); const p = await open(ctx, errors);
  const net = []; p.on('request', q => { const u = q.url(); if (!/^(data:|blob:)/.test(u) && !u.startsWith('http://127.0.0.1:8788/')) net.push(u); if (q.method() !== 'GET' && !/^(data:|blob:)/.test(u)) net.push(q.method() + ' ' + u); });

  // Keyboard only.
  await p.locator('input[name="svgm-preset"]:checked').focus(); const first = await attr(p, 'data-svgm-preset'); await p.keyboard.press('ArrowRight'); await p.waitForTimeout(150); const second = await attr(p, 'data-svgm-preset'); const stay = await p.evaluate(() => document.activeElement.name === 'svgm-preset'); out.keyboard = { first, second, focusStays: stay }; if (first === second) fail(e, 'arrow key did not change preset'); if (!stay) fail(e, 'focus lost');
  await p.locator('[data-svgm-toggle]').focus(); const k0 = await attr(p, 'data-svgm-playing'); await p.keyboard.press('Enter'); await p.waitForTimeout(150); const k1 = await attr(p, 'data-svgm-playing'); if (k0 === k1) fail(e, 'Enter on the toggle did nothing'); await p.locator('[data-svgm-stage]').focus(); await p.keyboard.press('Enter'); await p.waitForTimeout(150); if ((await attr(p, 'data-svgm-playing')) !== k0) fail(e, 'Enter on the preview did nothing'); out.keyboard.fileFocusable = await p.evaluate(() => { const i = document.querySelector('[data-svgm-file]'); i.focus(); return document.activeElement === i; }); if (!out.keyboard.fileFocusable) fail(e, 'file input not focusable');

  // Input routes and refusals.
  const rejects = out.rejects = {};
  await upload(p, LOGOS.gradient, { name: 'mine.svg' }); if ((await attr(p, 'data-svgm-logo')) !== 'user') fail(e, 'upload did not take'); const nameShown = await p.textContent('[data-svgm-logo-name]'); if (nameShown !== 'mine.svg') fail(e, 'name ' + nameShown); const good = await shot(p);
  const refuse = async (label, res, code) => { rejects[label] = res; if (res !== code) fail(e, label + ' gave ' + res + ' not ' + code); if ((await attr(p, 'data-svgm-logo')) !== 'user') fail(e, label + ' lost the logo'); const msg = await p.textContent('[data-svgm-error]'); if (!/[ぁ-ん一-龠]/.test(msg)) fail(e, label + ' no reason shown'); const t = await p.evaluate(() => __previewText()); if (!t.includes('stop-color') ) fail(e, label + ' current logo not kept'); };
  await refuse('png file', await upload(p, 'x', { name: 'a.png', type: 'image/png' }), 'NOT_SVG_FILE');
  await refuse('html renamed', await upload(p, '<html><script>alert(1)</script></html>', { name: 'a.svg' }), 'NOT_SVG');
  await refuse('over 1MB', await upload(p, 'x'.repeat(1100000), { name: 'a.svg' }), 'TOO_LARGE');
  await refuse('not utf-8', await upload(p, Buffer.from([0x3c, 0x73, 0x76, 0x67, 0xff, 0xfe, 0x3e]), { name: 'a.svg' }), 'NOT_UTF8');
  await refuse('empty paste', await paste(p, '   '), 'EMPTY_INPUT');
  const after = await upload(p, LOGOS.useBasic); if (after !== 'ok' || !(await p.evaluate(() => document.querySelector('[data-svgm-error]').hidden))) fail(e, 'recovery after an error');
  const pasted = await paste(p, LOGOS.clipMask); if (pasted !== 'ok' || (await p.textContent('[data-svgm-logo-name]')) === 'mine.svg') fail(e, 'paste did not take');
  // Real drop event with a File.
  await p.evaluate(t => { const m = document.querySelector('[data-svg-motion]'); m.removeAttribute('data-svgm-last'); const dt = new DataTransfer(); dt.items.add(new File([t], 'dropped.svg', { type: 'image/svg+xml' })); document.querySelector('[data-svgm-drop]').dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: dt })); }, LOGOS.symbol); await p.waitForSelector('[data-svg-motion][data-svgm-last]'); await p.waitForTimeout(100); out.drop = { result: await attr(p, 'data-svgm-last'), name: await p.textContent('[data-svgm-logo-name]') }; if (out.drop.result !== 'ok' || out.drop.name !== 'dropped.svg') fail(e, 'drop ' + JSON.stringify(out.drop));
  await p.evaluate(t => { const m = document.querySelector('[data-svg-motion]'); m.removeAttribute('data-svgm-last'); const dt = new DataTransfer(); dt.items.add(new File([t], 'a.svg', { type: 'image/svg+xml' })); dt.items.add(new File([t], 'b.svg', { type: 'image/svg+xml' })); document.querySelector('[data-svgm-drop]').dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: dt })); }, LOGOS.simple); await p.waitForSelector('[data-svg-motion][data-svgm-last]'); rejects['two files'] = await attr(p, 'data-svgm-last'); if (rejects['two files'] !== 'TOO_MANY_FILES') fail(e, 'two files ' + rejects['two files']);
  // A hostile file name is text, nothing more.
  await upload(p, LOGOS.simple, { name: '<img src=x onerror=alert(1)>.svg' }); const htmlName = await p.evaluate(() => document.querySelector('[data-svgm-logo-name]').children.length); if (htmlName) fail(e, 'file name became markup');
  // Warnings.
  await upload(p, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><script>x</script><text x="10" y="50">Acme</text><rect width="9" height="9"/><foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject></svg>'); out.warnings = await p.evaluate(() => [...document.querySelectorAll('[data-svgm-warnings] li')].map(l => l.textContent)); if (out.warnings.length < 3) fail(e, 'warnings ' + out.warnings);
  await ctx.close();

  // Structure: presets a logo cannot use say why; the rest work.
  const c2 = await br.newContext({ reducedMotion: 'reduce', viewport: { width: 1440, height: 1000 } }); const q = await open(c2, errors); const st = out.structure = {};
  const state = () => q.evaluate(() => Object.fromEntries([...document.querySelectorAll('[data-svgm-card]')].map(c => [c.querySelector('input').value, { disabled: c.querySelector('input').disabled, reason: c.querySelector('[data-svgm-reason]').textContent }])));
  await upload(q, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 40"><text x="5" y="30" font-size="24" fill="#333">Acme</text></svg>'); st.textOnly = Object.entries(await state()).filter(([, v]) => v.disabled).map(([k]) => k).sort(); if (JSON.stringify(st.textOnly) !== JSON.stringify(['logo-color-shift', 'logo-dash-flow', 'logo-draw', 'logo-draw-fill', 'logo-shine'])) fail(e, 'text-only ' + st.textOnly); const reason = (await state())['logo-draw'].reason; if (!/[ぁ-ん一-龠]/.test(reason)) fail(e, 'no reason on a disabled card');
  await choose(q, 'logo-assemble'); st.onePartNote = await q.evaluate(() => { const n = document.querySelector('[data-svgm-note]'); return n.hidden ? '' : n.textContent; }); if (!st.onePartNote) fail(e, 'one-part note');
  let big = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 180">'; for (let i = 0; i < 205; i++) big += '<rect x="' + (i * 7 % 310) + '" y="' + (i * 11 % 170) + '" width="6" height="6" fill="#6e4bff"/>'; big += '</svg>'; await upload(q, big); await choose(q, 'logo-draw'); const s205 = await state(); st.over200 = Object.entries(s205).filter(([, v]) => v.disabled).map(([k]) => k).sort(); if (st.over200.length !== 5) fail(e, 'over 200 ' + st.over200); if ((await attr(q, 'data-svgm-preset')) === 'logo-draw') fail(e, 'an unavailable preset stayed selected'); await choose(q, 'logo-parts-wave'); const big205 = await ink(q, await shot(q)); if (big205.ink <= 0) fail(e, 'over 200 parts lost the logo');
  // A logo that cannot use the selected preset: selection moves, logo stays.
  await upload(q, LOGOS.simple); await choose(q, 'logo-draw'); await upload(q, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 40"><text x="5" y="30">A</text></svg>'); const moved = await attr(q, 'data-svgm-preset'); st.movedTo = moved; if (moved === 'logo-draw') fail(e, 'unavailable preset kept after a new logo');

  // Every preset on user logos: never blank at rest, and has motion when played.
  const ids = await q.evaluate(() => [...document.querySelectorAll('input[name="svgm-preset"]')].map(i => i.value));
  const apply = out.apply = { combos: 0, blank: [], rows: {} };
  for (const k of ['gradient', 'clipMask', 'useBasic', 'styleClass', 'figmaLike', 'groupsIds', 'illustratorLike', 'filter']) { const res = await upload(q, LOGOS[k]); if (res !== 'ok') { fail(e, 'apply upload ' + k); continue; } const base = (await ink(q, await shot(q))).ink; for (const id of ids) { await choose(q, id); apply.combos++; const a = (await ink(q, await shot(q))).ink; const hasKeyframes = await q.evaluate(() => __previewText().then(t => /@keyframes/.test(t))); if (a <= 0) apply.blank.push(k + '/' + id); if (!hasKeyframes) fail(e, 'no motion ' + k + '/' + id); } }
  if (apply.blank.length) fail(e, 'logo lost on ' + apply.blank.slice(0, 6)); if (apply.combos !== 152) fail(e, 'combos ' + apply.combos);

  // Every adjustment changes what is shown and never loses the logo.
  await upload(q, LOGOS.gradient); await choose(q, 'logo-pop'); await q.waitForTimeout(100); const baseShot = await shot(q); const adj = out.adjust = {};
  for (const [key, value] of [['opacity', 0.3], ['scale', 0.5], ['scale', 2], ['x', 30], ['x', -50], ['y', 50], ['rotate', 90]]) { await setControl(q, 'data-svgm-common', key, value); const s = await shot(q); const d = await diff(q, s, baseShot); const v = await ink(q, s); adj[key + '=' + value] = { changed: d, ink: v.ink }; if (d <= 0) fail(e, 'adjusting ' + key + ' changed nothing'); if (v.ink <= 0) fail(e, key + '=' + value + ' lost the logo'); await q.click('[data-svgm-defaults]'); await q.waitForTimeout(100); }
  const back = await diff(q, await shot(q), baseShot); adj.defaultsRestore = back; if (back > 0.05) fail(e, 'defaults did not restore ' + back);
  for (const [key, value, test] of [['delay', 500, /-700ms/], ['loop', '3', /\b3 normal both/], ['easing', 'linear', /linear/]]) { await setControl(q, 'data-svgm-common', key, value); const t = await q.evaluate(() => __previewText()); if (!test.test(t)) fail(e, 'adjusting ' + key + ' not in the output'); await q.click('[data-svgm-defaults]'); await q.waitForTimeout(80); }
  await upload(q, LOGOS.groupsIds); await choose(q, 'logo-color-shift'); const hasStagger = await q.locator('[data-svgm-common="stagger"]').count(); await setControl(q, 'data-svgm-common', 'stagger', 200); const staggerText = await q.evaluate(() => __previewText()); if (!hasStagger || !/ 200ms infinite/.test(staggerText)) fail(e, 'stagger'); await q.click('[data-svgm-defaults]'); await choose(q, 'logo-pop'); if (await q.locator('[data-svgm-common="stagger"]').count()) fail(e, 'stagger shown where it does nothing');
  // Preset-specific values, every preset.
  for (const id of ids) { await upload(q, LOGOS.gradient); await choose(q, id); const before = await q.evaluate(() => __previewText()); const keys = await q.evaluate(() => [...document.querySelectorAll('[data-svgm-param]')].map(i => ({ k: i.getAttribute('data-svgm-param'), type: i.type, max: i.max, min: i.min, v: i.value, opts: i.options ? [...i.options].map(o => o.value) : [] }))); for (const c of keys) { const value = c.type === 'range' ? (c.v === c.max ? c.min : c.max) : c.type === 'color' ? '#123456' : c.opts.find(o => o !== c.v); await setControl(q, 'data-svgm-param', c.k, value); const t = await q.evaluate(() => __previewText()); if (t === before) fail(e, id + ' param ' + c.k + ' changed nothing'); const v = await ink(q, await shot(q)); if (v.ink <= 0 && id !== 'logo-wipe') fail(e, id + ' param ' + c.k + ' lost the logo'); } await q.click('[data-svgm-defaults]'); await q.waitForTimeout(40); }
  await c2.close();

  // Playback on the real page.
  const c3 = await br.newContext({ viewport: { width: 1440, height: 1000 } }); const r = await open(c3, errors); await upload(r, LOGOS.gradient); await choose(r, 'logo-float'); await setControl(r, 'data-svgm-param', 'amount', 0.2); await setControl(r, 'data-svgm-param', 'duration', 1000);
  const pb = out.playback = {}; if ((await attr(r, 'data-svgm-playing')) !== 'true') fail(e, 'loop not playing'); await r.waitForTimeout(350); await r.click('[data-svgm-toggle]'); await r.waitForTimeout(120); if ((await attr(r, 'data-svgm-playing')) !== 'false') fail(e, 'stop'); const h1 = await shot(r); await r.waitForTimeout(300); const h2 = await shot(r); pb.heldDiff = await diff(r, h1, h2); if (pb.heldDiff > 0) fail(e, 'moved while stopped ' + pb.heldDiff); await r.click('[data-svgm-toggle]'); await r.waitForTimeout(250); if ((await attr(r, 'data-svgm-playing')) !== 'true') fail(e, 'resume'); await r.click('[data-svgm-reset]'); await r.waitForTimeout(120); if ((await attr(r, 'data-svgm-playing')) !== 'false') fail(e, 'reset should stop'); const z = await r.evaluate(() => __previewText()); if (!/-?0ms infinite alternate both paused/.test(z)) fail(e, 'reset is not at time 0'); await r.locator('[data-svgm-stage]').click(); await r.waitForTimeout(120); if ((await attr(r, 'data-svgm-playing')) !== 'true') fail(e, 'clicking the preview');
  // An entrance ends by itself and plays again on request.
  await choose(r, 'logo-pop'); await r.waitForTimeout(900); pb.entranceEnds = await attr(r, 'data-svgm-playing'); if (pb.entranceEnds !== 'false') fail(e, 'entrance did not end'); const endShot = await shot(r); const endInk = await ink(r, endShot); if (endInk.ink <= 0) fail(e, 'entrance end blank'); await r.click('[data-svgm-toggle]'); await r.waitForTimeout(100); if ((await attr(r, 'data-svgm-playing')) !== 'true') fail(e, 'replay');
  // Fast slider: no blank moment, old URLs released, last value wins.
  await choose(r, 'logo-breathe'); const blob = await r.evaluate(async () => { const i = document.querySelector('[data-svgm-param="amount"]'); let min = 1e9; for (let n = 0; n < 40; n++) { i.value = 0.01 + (n % 19) * 0.01; i.dispatchEvent(new Event('input', { bubbles: true })); await new Promise(requestAnimationFrame); const im = document.querySelector('[data-svgm-preview]'); if (!im || !im.complete || !im.naturalWidth) min = 0; } await new Promise(s => setTimeout(s, 120)); return { missing: min === 0, images: document.querySelectorAll('[data-svgm-stage] img').length, finalValue: i.value, text: await __previewText() }; }); pb.fastSlider = { missing: blob.missing, images: blob.images }; if (blob.missing) fail(e, 'preview missing during a fast slider'); if (blob.images !== 1) fail(e, 'images piled up ' + blob.images); const lastScale = 1 + Number(blob.finalValue); if (!blob.text.includes('scale(' + +lastScale.toFixed(4) + ')')) fail(e, 'last slider value not shown');
  await c3.close();

  // Saving, restoring, deleting.
  const c4 = await br.newContext({ viewport: { width: 1280, height: 900 } }); const s = await open(c4, errors); const sv = out.storage = {};
  await upload(s, LOGOS.gradient, { name: 'keep.svg' }); await choose(s, 'logo-wipe'); await s.selectOption('select[data-svgm-param="direction"]', 'up'); await setControl(s, 'data-svgm-common', 'scale', 0.6); await s.waitForTimeout(500);
  const url = await s.evaluate(() => location.href); sv.url = url; if (/svg|%3C|stop-color|base64/i.test(url.split('#')[1] || '') && !/svg-motion/.test(url.split('#')[1] || '')) fail(e, 'logo data in the URL'); if (/%3C|<|linearGradient/i.test(url)) fail(e, 'logo data in the URL');
  const dbRead = () => s.evaluate(() => new Promise(ok => { const r = indexedDB.open('omx-svg-motion', 1); r.onsuccess = () => { const q = r.result.transaction('state').objectStore('state').get('current'); q.onsuccess = () => { r.result.close(); ok(q.result || null); }; }; })); const rec = await dbRead(); sv.saved = rec && { schema: rec.schema, hasSvg: !!rec.svg, preset: rec.settings.preset, hasPlayback: 'playing' in (rec.settings || {}) || 'time' in (rec.settings || {}) }; if (!rec || !rec.svg || rec.svg.includes('stop-color') === false) fail(e, 'logo not saved'); if (rec && (rec.settings.playing !== undefined || rec.settings.time !== undefined)) fail(e, 'playback state in saved settings');
  await s.goto('http://127.0.0.1:8788/learn/svg-motion/'); await s.waitForSelector('[data-svgm-ready="true"]'); await s.waitForTimeout(200); const rest = await s.evaluate(() => ({ preset: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-preset'), logo: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-logo'), dir: document.querySelector('[data-svgm-param="direction"]').value, scale: document.querySelector('[data-svgm-common="scale"]').value, remove: !document.querySelector('[data-svgm-remove]').hidden })); sv.restored = rest; if (rest.logo !== 'user' || rest.preset !== 'logo-wipe' || rest.dir !== 'up' || rest.scale !== '0.6' || !rest.remove) fail(e, 'restore ' + JSON.stringify(rest)); await s.evaluate(() => 0); const t1 = await s.evaluate(() => window.__readBlob(document.querySelector('[data-svgm-preview]').src)); if (!t1.includes('stop-color')) fail(e, 'restored logo missing'); if (/omx-omx-/.test(t1)) fail(e, 'id prefix grew on reload');
  await s.reload(); await s.waitForSelector('[data-svgm-ready="true"]'); await s.waitForTimeout(150); const t2 = await s.evaluate(() => window.__readBlob(document.querySelector('[data-svgm-preview]').src)); if (/omx-omx-/.test(t2)) fail(e, 'id prefix grew on the second reload');
  await s.click('[data-svgm-remove]'); await s.waitForTimeout(400); const afterDel = await s.evaluate(() => ({ logo: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-logo'), remove: !document.querySelector('[data-svgm-remove]').hidden })); await s.waitForTimeout(300); const rec2 = await dbRead(); sv.deleted = { ...afterDel, svgLeft: !!(rec2 && rec2.svg) }; if (afterDel.logo !== 'sample' || afterDel.remove || (rec2 && rec2.svg)) fail(e, 'delete ' + JSON.stringify(sv.deleted));
  await s.goto('http://127.0.0.1:8788/learn/svg-motion/'); await s.waitForSelector('[data-svgm-ready="true"]'); if ((await s.getAttribute('[data-svg-motion]', 'data-svgm-logo')) !== 'sample') fail(e, 'deleted logo came back');
  // Old and unknown formats.
  await s.evaluate(() => new Promise((ok, no) => { const r = indexedDB.open('omx-svg-motion', 1); r.onsuccess = () => { const t = r.result.transaction('state', 'readwrite'); t.objectStore('state').put({ schema: 99, svg: '<svg/>', settings: {} }, 'current'); t.oncomplete = () => { r.result.close(); ok(); }; }; })); await s.reload(); await s.waitForSelector('[data-svgm-ready="true"]'); await s.waitForTimeout(300); const rec3 = await s.evaluate(() => new Promise(ok => { const r = indexedDB.open('omx-svg-motion', 1); r.onsuccess = () => { const q = r.result.transaction('state').objectStore('state').get('current'); q.onsuccess = () => { r.result.close(); ok(q.result || null); }; }; })); sv.unknownSchema = rec3 && rec3.schema; if (rec3 && rec3.schema === 99) fail(e, 'unknown schema kept');
  await s.evaluate(() => new Promise(ok => { const r = indexedDB.open('omx-svg-motion', 1); r.onsuccess = () => { const t = r.result.transaction('state', 'readwrite'); t.objectStore('state').delete('current'); t.oncomplete = () => { r.result.close(); ok(); }; }; })); await s.evaluate(() => localStorage.setItem('omx-svg-motion-state', JSON.stringify({ preset: 'logo-drop', sample: 'stack', params: { 'logo-drop': { bounces: 3 } } }))); await s.goto('http://127.0.0.1:8788/learn/svg-motion/'); await s.waitForSelector('[data-svgm-ready="true"]'); const legacy = await s.evaluate(() => ({ preset: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-preset'), bounces: document.querySelector('[data-svgm-param="bounces"]').value, sample: document.querySelector('input[name="svgm-sample"]:checked').value })); sv.legacy = legacy; if (legacy.preset !== 'logo-drop' || legacy.bounces !== '3' || legacy.sample !== 'stack') fail(e, 'legacy ' + JSON.stringify(legacy));
  await s.evaluate(() => localStorage.setItem('omx-svg-motion-state', JSON.stringify({ preset: 'x"><img src=x>', sample: 'nope', params: { 'logo-pop': { duration: 'url(https://evil.example)', from: 99 } } }))); await s.goto('about:blank'); await s.goto('http://127.0.0.1:8788/learn/svg-motion/#p=__proto__&s=<img>&duration=url(x)'); await s.waitForSelector('[data-svgm-ready="true"]'); const safe = await s.evaluate(() => ({ preset: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-preset'), evil: document.documentElement.innerHTML.includes('evil.example') })); sv.hostileHash = safe; if (safe.evil || safe.preset === '__proto__') fail(e, 'hostile state');
  await c4.close();

  // Filter and reduced motion.
  const c5 = await br.newContext({ reducedMotion: 'reduce', viewport: { width: 1000, height: 800 } }); const t = await open(c5, errors); await t.evaluate(() => { document.documentElement.style.scrollBehavior = 'auto'; document.querySelector('[data-svgm-card]').scrollIntoView(); }); await t.waitForTimeout(400); const rm = await t.evaluate(async () => { const thumbs = [...document.querySelectorAll('[data-svgm-thumb]')].filter(i => i.getAttribute('src')); const texts = await Promise.all(thumbs.map(i => window.__readBlob(i.src))); return { playing: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-playing'), thumbs: thumbs.length, allPaused: texts.every(x => /paused/.test(x)), main: await __previewText().then(x => /paused/.test(x)) }; }); out.reduced = rm; if (rm.playing !== 'false' || !rm.main) fail(e, 'autoplay under reduced motion'); if (!rm.thumbs || !rm.allPaused) fail(e, 'thumbs move under reduced motion'); await t.click('[data-svgm-toggle]'); await t.waitForTimeout(150); if ((await attr(t, 'data-svgm-playing')) !== 'true') fail(e, 'explicit play refused'); await t.click('[data-svgm-filter="loop"]'); const loops = await t.evaluate(() => [...document.querySelectorAll('[data-svgm-card]')].filter(c => !c.hidden).length); await t.click('[data-svgm-filter="entrance"]'); const ent = await t.evaluate(() => [...document.querySelectorAll('[data-svgm-card]')].filter(c => !c.hidden).length); out.filter = { loops, entrance: ent }; if (loops + ent !== 19) fail(e, 'filter'); await c5.close();
  out.network = [...new Set(net)]; if (net.length) fail(e, 'network ' + out.network.slice(0, 3));
  out.consoleErrors = errors; if (errors.length) fail(e, 'console: ' + errors.slice(0, 3).join(' | ')); fs.writeFileSync(path.join(R, 'page-' + e + '.json'), JSON.stringify(out, null, 1)); return { combos: apply.combos };
}
module.exports = { pageTests, uploadSecurity, open, inWindow, consoleVerdict, ALLOWED_ATTACKS, MENTION_ONLY_ATTACKS, KNOWN_CAP_PER_KEY, ATTACKS, LOGOS };
