// MVP-2: the [omochix_logo_motion] shortcode (inc/logo-motion.php) against the studio's real exports, the attack corpus
// and three browsers. The PHP side runs through tests/svg-motion/shortcode.php (a WordPress stub); the pages it renders
// are served by the harness server and opened as a visitor would, with and without reduced motion.
const fs = require('fs'), path = require('path'), vm = require('vm'), { execFileSync } = require('child_process');
const { PORT } = require('./server.js');
const U = 'http://127.0.0.1:8788/learn/svg-motion/', X = 'http://127.0.0.1:' + PORT + '/exported/', R = path.join(__dirname, 'results');
const D = path.join(R, 'exported', 'sc'), FIX = path.join(__dirname, 'fixtures', 'logo-motion'), PHP = path.join(__dirname, 'shortcode.php');
const sb = { window: {} }; vm.runInNewContext(fs.readFileSync(path.join(__dirname, 'fixtures/corpus.js'), 'utf8'), sb); const { ATTACKS, BOMBS, LOGOS } = sb.window;
const php = (...args) => execFileSync('php', [PHP, ...args], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
const put = (rel, text) => { const f = path.join(D, rel); fs.mkdirSync(path.dirname(f), { recursive: true }); fs.writeFileSync(f, text); return f; };
const PAGE = body => '<!doctype html><html><head><meta charset="utf-8"><style>html,body{margin:0;background:#fff}</style></head><body><div id="t" style="width:200px">' + body + '</div></body></html>';
const attr = (html, name) => (new RegExp('\\s' + name + '="([^"]*)"').exec(html) || [])[1];

async function shortcodeTests(br, e, fail) {
  const out = {}; fs.rmSync(D, { recursive: true, force: true });
  const ctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const g = await ctx.newPage();
  await g.goto(U); await g.waitForSelector('[data-svgm-ready="true"]');

  // 1. Every export the studio makes is accepted by the shortcode's own check: 7 logos x 19 presets, and each attack
  //    of the corpus that the studio accepts (sanitized) and exports.
  const logos = { sample: null, gradient: LOGOS.gradient, useBasic: LOGOS.useBasic, groupsIds: LOGOS.groupsIds, clipMask: LOGOS.clipMask, figmaLike: LOGOS.figmaLike, illustratorLike: LOGOS.illustratorLike };
  const built = await g.evaluate(([logos, attacks]) => {
    const data = JSON.parse(document.getElementById('omx-svg-motion-data').textContent); const out = { exports: [], hostile: [], refused: 0 };
    const make = (svg, preset) => OmxSvgExport.build({ svg, preset, params: {}, common: {}, commonList: data.common, budget: data.budget, label: 'Acme "logo"' });
    for (const [name, src] of Object.entries(logos)) {
      const svg = OmxSvgSafety.normalize(src || data.samples[0].svg, { prefix: 'omx-' }).svg;
      for (const preset of data.presets) { const b = make(svg, preset); out.exports.push({ key: name + '--' + preset.id, base: b.names.animated.replace(/\.svg$/, ''), animated: b.files.animated, still: b.files.still, snippet: b.snippets.image }); }
    }
    const pop = data.presets.find(p => p.id === 'logo-pop');
    // MVP-4 (S3): the studio's own export check refuses generated CSS outside what the compiler writes.
    const sample = make(OmxSvgSafety.normalize(data.samples[0].svg, { prefix: 'omx-' }).svg, pop).files.animated;
    out.studioCss = Object.fromEntries(Object.entries({ imageSet: ';background-image:image-set("x.png" 1x)', cssVar: ';fill:var(--a)', namespace: '@namespace x "y";' }).map(([k, add]) => {
      const text = k === 'namespace' ? sample.replace('<style>', '<style>' + add) : sample.replace('transform-origin:center}', 'transform-origin:center' + add + '}');
      return [k, OmxSvgExport.verifyExport(text).includes('style')];
    }));
    for (const [k, src] of Object.entries(attacks)) { try { const b = make(OmxSvgSafety.normalize(src, { prefix: 'omx-' }).svg, pop); out.hostile.push({ key: k, animated: b.files.animated, still: b.files.still }); } catch (x) { out.refused++; } }
    return out;
  }, [logos, Object.fromEntries(Object.entries(ATTACKS).map(([k, v]) => [k, v.split('{C}').join('http://127.0.0.1:8771/c/sc/')]))]);
  built.exports.forEach((x, i) => { put('exports/e' + i + '.svg', x.animated); put('exports/e' + i + '-static.svg', x.still); });
  built.hostile.forEach((x, i) => { put('hostile/h' + i + '.svg', x.animated); put('hostile/h' + i + '-static.svg', x.still); });
  const exp = JSON.parse(php('--check-dir', path.join(D, 'exports'))), hos = JSON.parse(php('--check-dir', path.join(D, 'hostile')));
  const refusedExports = Object.entries(exp).filter(([, p]) => p.length), refusedHostile = Object.entries(hos).filter(([, p]) => p.length);
  out.studioExports = { files: Object.keys(exp).length, refused: refusedExports.length, hostileExported: built.hostile.length, hostileRefusedByStudio: built.refused, hostileFiles: Object.keys(hos).length, hostileRefusedHere: refusedHostile.length };
  if (Object.keys(exp).length !== 2 * 7 * 19 || refusedExports.length) fail(e, 'shortcode refuses studio exports ' + JSON.stringify(refusedExports.slice(0, 3)));
  // Fail-closed (MVP-4): a sanitized export whose text still holds markup-like characters (the escaped-markup attacks of
  // the corpus) is refused for that reason alone; every other sanitized hostile export is accepted.
  out.studioExports.hostileRefusedFor = [...new Set(refusedHostile.flatMap(([, p]) => p))];
  if (!built.hostile.length || refusedHostile.some(([, p]) => p.join() !== 'text')) fail(e, 'shortcode refuses sanitized hostile exports ' + JSON.stringify(refusedHostile.slice(0, 3)));
  out.studioCss = built.studioCss; if (!Object.values(built.studioCss).every(Boolean)) fail(e, 'studio export check accepts CSS outside the compiler\'s output ' + JSON.stringify(built.studioCss));

  // 2. The raw corpus put inside a real export (as if someone edited the file on the server): every one is refused.
  const pop = fs.readFileSync(path.join(FIX, 'omochix-logo-motion-pop.svg'), 'utf8');
  const raw = Object.assign({}, ...Object.entries(ATTACKS).map(([k, v]) => ({ ['a-' + k]: v.split('{C}').join('http://127.0.0.1:8771/c/sc/') })), ...Object.entries(BOMBS).map(([k, v]) => ({ ['b-' + k]: v })));
  const names = Object.keys(raw);
  // The corpus's marker <rect id="omxkeep"> is left out, so each file is refused for its payload, not for the marker's id.
  names.forEach((k, i) => { const m = /^\s*<svg\b[^>]*>([\s\S]*)<\/svg>\s*$/.exec(raw[k]); put('raw/r' + i + '.svg', m ? pop.replace('<title>', m[1].replace(/<rect id="omxkeep"[^>]*\/>/, '') + '<title>') : raw[k]); });
  const rawCheck = JSON.parse(php('--check-dir', path.join(D, 'raw')));
  const accepted = names.filter((k, i) => !(rawCheck['r' + i + '.svg'] || []).length);
  // Every one is refused (MVP-4: the escaped-markup text cases too, which MVP-2 accepted as inert in an image).
  out.rawCorpus = { cases: names.length, refused: names.length - accepted.length, accepted };
  if (accepted.length) fail(e, 'shortcode accepts raw attacks ' + accepted.join(','));
  // Control: the check is alive (the untouched export passes, one obvious attack does not).
  const ctl = JSON.parse(php('--check-dir', FIX)); if (Object.values(ctl).some(p => p.length)) fail(e, 'control: fixtures refused');

  // 3. As a visitor sees it: the shortcode's <picture> in this browser, moving, and the still file under reduced motion.
  const breathe = built.exports.find(x => x.key === 'sample--logo-breathe'); const up = path.join(D, 'uploads', 'omochix-logo-motion'); fs.mkdirSync(up, { recursive: true });
  fs.writeFileSync(path.join(up, breathe.base + '.svg'), breathe.animated); fs.writeFileSync(path.join(up, breathe.base + '-static.svg'), breathe.still);
  const render = atts => php('--render', JSON.stringify(atts), path.join(D, 'uploads'), X + 'sc/uploads');
  const html = render({ name: breathe.base, alt: 'Acme "logo"', size: '200' }); put('page.html', PAGE(html));
  // The same picture as the studio's own image snippet: same media query, same two files, same alt.
  const snip = breathe.snippet, base = s => (s || '').split('?')[0].split('/').pop();
  out.matchesStudio = { media: attr(html, 'media') === attr(snip, 'media'), still: base(attr(html, 'srcset')) === base(attr(snip, 'srcset')), animated: base(attr(html, 'src')) === base(attr(snip, 'src')), alt: attr(html, 'alt') === attr(snip, 'alt') };
  if (!Object.values(out.matchesStudio).every(Boolean)) fail(e, 'shortcode output differs from the studio snippet ' + JSON.stringify(out.matchesStudio));

  const look = async (reducedMotion, file) => {
    const c = await br.newContext({ reducedMotion, viewport: { width: 400, height: 300 } }); const p = await c.newPage(); const ext = [], errs = [];
    p.on('request', q => { const u = q.url(); if (!u.startsWith(X)) ext.push(u); }); p.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 100)); });
    await p.goto(X + 'sc/' + file); await p.waitForLoadState('load'); await p.bringToFront();
    // The shortcode's image is loading="lazy", which does not hold up the load event: wait for the image itself.
    await p.waitForFunction(() => { const i = document.querySelector('img'); return !i || (i.complete && i.naturalWidth > 0); }, null, { timeout: 5000 }).catch(() => {});
    const info = await p.evaluate(() => { const i = document.querySelector('img'); return i ? { src: i.currentSrc, w: i.naturalWidth } : null; });
    const shots = []; for (let k = 0; k < 4; k++) { const box = await p.evaluate(() => { const r = document.querySelector('#t').getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: Math.max(1, r.height) }; }); shots.push(await p.screenshot({ clip: box })); await p.waitForTimeout(350); }
    await c.close();
    return { src: info && info.src.split('/').pop(), loaded: !!(info && info.w), moved: shots.some(s => !s.equals(shots[0])), ext: ext.length, errs: errs.length };
  };
  out.visitor = { motion: await look('no-preference', 'page.html'), reduced: await look('reduce', 'page.html') };
  const m = out.visitor.motion, rd = out.visitor.reduced;
  if (!m.loaded || !/^omochix-logo-motion-breathe\.svg\?v=/.test(m.src || '') || !m.moved || m.ext || m.errs) fail(e, 'shortcode picture: ' + JSON.stringify(m));
  if (!rd.loaded || !/-static\.svg\?v=/.test(rd.src || '') || rd.moved || rd.ext || rd.errs) fail(e, 'shortcode picture, reduced motion: ' + JSON.stringify(rd));

  // 4. The files are replaced by another export: a new ?v= and the new image; one removed: nothing shown.
  const popStill = fs.readFileSync(path.join(FIX, 'omochix-logo-motion-pop-static.svg'), 'utf8'), later = new Date(Date.now() + 5000);
  fs.writeFileSync(path.join(up, breathe.base + '.svg'), pop); fs.writeFileSync(path.join(up, breathe.base + '-static.svg'), popStill);
  fs.utimesSync(path.join(up, breathe.base + '.svg'), later, later); fs.utimesSync(path.join(up, breathe.base + '-static.svg'), later, later);
  const html2 = render({ name: breathe.base, alt: 'Acme "logo"', size: '200' }); put('page2.html', PAGE(html2));
  const swapped = await look('no-preference', 'page2.html'); fs.unlinkSync(path.join(up, breathe.base + '-static.svg'));
  const html3 = render({ name: breathe.base });
  out.swap = { newVersion: attr(html, 'src') !== attr(html2, 'src'), loaded: swapped.loaded, removedShowsNothing: html3 === '' };
  if (!out.swap.newVersion || !out.swap.loaded || !out.swap.removedShowsNothing) fail(e, 'replacing or removing files ' + JSON.stringify(out.swap));

  // 5. Opened on its own (not through <img>): with the production headers an SVG in the shortcode's folder runs nothing;
  //    the same file served without them does run (the detector is alive). The pictures above came through the same
  //    headers, so display and reduced motion are unaffected by them.
  const run = 'sc' + e + Date.now(), C = 'http://127.0.0.1:8771';
  const evil = n => '<svg xmlns="http://www.w3.org/2000/svg" onload="fetch(\'' + C + '/c/' + run + '/' + n + '-onload\')"><script>fetch("' + C + '/c/' + run + '/' + n + '-script")</script><rect width="10" height="10"/></svg>';
  fs.writeFileSync(path.join(up, 'direct.svg'), evil('headers')); put('direct-control.svg', evil('control'));
  out.direct = {};
  for (const [k, url] of [['headers', X + 'sc/uploads/omochix-logo-motion/direct.svg'], ['control', X + 'sc/direct-control.svg']]) {
    const c = await br.newContext(); const p = await c.newPage(); const resp = await p.goto(url); await p.waitForTimeout(700);
    const h = resp.headers(); out.direct[k] = { type: h['content-type'], csp: h['content-security-policy'] || '', nosniff: h['x-content-type-options'] || '' }; await c.close();
  }
  const hits = await (await fetch(C + '/__dump?run=' + run)).json();
  out.direct.hits = { headers: hits.filter(h => h.includes('/headers-')).length, control: hits.filter(h => h.includes('/control-')).length };
  const dh = out.direct.headers;
  if (dh.type !== 'image/svg+xml' || !/\bsandbox\b/.test(dh.csp) || !/default-src 'none'/.test(dh.csp) || dh.nosniff !== 'nosniff') fail(e, 'direct access headers ' + JSON.stringify(dh));
  if (out.direct.hits.headers) fail(e, 'an SVG opened directly ran script despite the headers ' + JSON.stringify(out.direct.hits));
  if (!out.direct.hits.control) fail(e, 'control: an SVG without the headers ran nothing (detector dead) ' + JSON.stringify(out.direct.hits));

  await ctx.close();
  fs.writeFileSync(path.join(R, 'shortcode-' + e + '.json'), JSON.stringify(out, null, 1));
  return out;
}
module.exports = { shortcodeTests };
