// S3: exported files, used the way a site would use them, away from the studio.
const fs = require('fs'), path = require('path'), vm = require('vm');
const U = 'http://127.0.0.1:8788/learn/svg-motion/', X = 'http://127.0.0.1:' + require('./server.js').PORT + '/exported/', R = path.join(__dirname, 'results'), OUT = path.join(R, 'exported');
const sb = { window: {} }; vm.runInNewContext(fs.readFileSync(path.join(__dirname, 'fixtures/corpus.js'), 'utf8'), sb); const { ATTACKS, LOGOS } = sb.window;
const PAGE = (head, body) => '<!doctype html><html><head><meta charset="utf-8">' + head + '<style>html,body{margin:0;background:#fff}#t{width:300px;height:300px;display:block}#t img,#t svg{width:300px;height:300px;display:block}</style></head><body><div id="t">' + body + '</div></body></html>';
const CSP = '<meta http-equiv="Content-Security-Policy" content="default-src \'self\'; img-src \'self\'; style-src \'self\'; script-src \'self\'">';
const CSP_PAGE = body => '<!doctype html><html><head><meta charset="utf-8">' + CSP + '<link rel="stylesheet" href="/exported/base.css"></head><body><div id="t">' + body + '</div></body></html>';
// SVGM_PROFILE=fast (pull requests): the image-type forms that need real time run on the representative logos only; everything
// else, every security check included, runs in both profiles. SVGM_PARALLEL: pages open at once for the standalone checks.
const PROFILE = process.env.SVGM_PROFILE === 'fast' ? 'fast' : 'full', PAR = Math.max(1, Number(process.env.SVGM_PARALLEL) || 6);
const REAL_TIME_FORMS = ['image', 'object', 'background'], REPRESENTATIVE = ['sample', 'gradient'];
// An edit (S4) laid over three presets of the sample logo: individual transforms on a part and a pinned-origin rotation of the root.
const EDIT = { duration: 2500, nextId: 3, layers: [
  { id: 'L1', target: { kind: 'part', index: 0 }, start: 100, end: 1500, tracks: [
    { prop: 'translate', easing: 'soft', keys: [{ at: 0, v: [0, -30] }, { at: 1, v: [0, 0] }] },
    { prop: 'scale', easing: 'ease-in-out', keys: [{ at: 0, v: [1] }, { at: 0.5, v: [0.6] }, { at: 1, v: [1] }] }] },
  { id: 'L2', target: { kind: 'root', index: 0 }, start: 0, end: 2500, tracks: [{ prop: 'rotate', easing: 'linear', keys: [{ at: 0, v: [0] }, { at: 1, v: [45] }] }] }] };
const EDITED = ['logo-pop', 'logo-breathe', 'logo-wipe'];
const write = (rel, text) => { const f = path.join(OUT, rel); fs.mkdirSync(path.dirname(f), { recursive: true }); fs.writeFileSync(f, text); };
const HELPERS = () => {
  const load = u => new Promise((ok, no) => { const i = new Image(); i.onload = () => { const c = document.createElement('canvas'); c.width = i.width; c.height = i.height; const x = c.getContext('2d'); x.drawImage(i, 0, 0); ok(x.getImageData(0, 0, c.width, c.height).data); }; i.onerror = no; i.src = 'data:image/png;base64,' + u; });
  // Anti-aliasing tolerant comparison (3x3 range), as in the preset tests.
  window.__diff = (a, b) => Promise.all([load(a), load(b)]).then(([p, q]) => { if (p.length !== q.length) return 100; const W = Math.sqrt(p.length / 4) | 0, H = p.length / 4 / W, T = 24; let n = 0; for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) { const i = (y * W + x) * 4; let ok = true; for (let c = 0; c < 3 && ok; c++) { let lo = 255, hi = 0; for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) { const yy = y + dy, xx = x + dx; if (yy < 0 || yy >= H || xx < 0 || xx >= W) continue; const v = q[(yy * W + xx) * 4 + c]; if (v < lo) lo = v; if (v > hi) hi = v; } if (p[i + c] < lo - T || p[i + c] > hi + T) ok = false; } if (!ok) n++; } return +(n / (W * H) * 100).toFixed(2); });
  window.__raw = (a, b) => Promise.all([load(a), load(b)]).then(([p, q]) => { let n = 0; for (let i = 0; i < p.length; i += 4) if (Math.abs(p[i] - q[i]) + Math.abs(p[i + 1] - q[i + 1]) + Math.abs(p[i + 2] - q[i + 2]) > 18) n++; return +(n / (p.length / 4) * 100).toFixed(2); });
  window.__ink = a => load(a).then(d => { let n = 0, m = 0; for (let j = 0; j < d.length; j += 4) { if (d[j] + d[j + 1] + d[j + 2] < 740) n++; if (Math.abs(d[j] - 0x12) < 28 && Math.abs(d[j + 1] - 0x34) < 28 && Math.abs(d[j + 2] - 0x56) < 28) m++; } return { ink: n, marker: m }; });
  window.__center = a => load(a).then(d => { const W = Math.sqrt(d.length / 4) | 0, k = ((W >> 1) * W + (W >> 1)) * 4; return [d[k], d[k + 1], d[k + 2]]; });
};

async function exportTests(br, e, fail) {
  const out = { matrix: {}, ui: {} }; const errors = [];
  const failCount = { n: 0 }; const origFail = fail; fail = (x, m) => { failCount.n++; origFail(x, m); }; const failures = () => failCount.n;
  // 1. Generate with the product's own export, from the real studio page.
  const gctx = await br.newContext({ viewport: { width: 1280, height: 900 }, acceptDownloads: true }); const g = await gctx.newPage(); g.on('pageerror', x => errors.push(x.message));
  await g.goto(U); await g.waitForSelector('[data-svgm-ready="true"]');
  const logos = { sample: null, gradient: LOGOS.gradient, useBasic: LOGOS.useBasic, groupsIds: LOGOS.groupsIds, clipMask: LOGOS.clipMask, figmaLike: LOGOS.figmaLike, illustratorLike: LOGOS.illustratorLike };
  const built = await g.evaluate(async ([logos, EDIT, EDITED]) => {
    const data = JSON.parse(document.getElementById('omx-svg-motion-data').textContent); const out = {};
    for (const [name, src] of Object.entries(logos)) {
      const svg = OmxSvgSafety.normalize(src || data.samples[0].svg, { prefix: 'omx-' }).svg; out[name] = {};
      const variants = []; for (const preset of data.presets) { variants.push([preset.id, preset, null]); if (name === 'sample' && EDITED.includes(preset.id)) variants.push([preset.id + '+edit', preset, EDIT]); }
      for (const [key, preset, rawEdit] of variants) {
        let b; try {
          let edit = null;
          if (rawEdit) { const layout = OmxSvgFrame.prepare(svg, Object.assign({ overlay: false }, data.budget)); const box = await OmxSvgFrame.measure(layout.svg); edit = OmxSvgCompile.validateEdit(Object.assign({}, rawEdit, box ? { origin: { x: box.x + box.width / 2, y: box.y + box.height / 2 } } : {}), layout.layout); if (!box) out[name][key + ':noOrigin'] = { error: 'MEASURE_FAILED' }; }
          b = OmxSvgExport.build({ svg, preset, params: {}, common: {}, commonList: data.common, budget: data.budget, label: 'テスト "ロゴ" <a>', edit });
        } catch (err) { out[name][key] = { error: err.code || err.message }; continue; }
        const stage = OmxSvgFrame.prepare(OmxSvgSafety.normalize(svg, { prefix: b.scope + '-' }).svg, Object.assign({ overlay: preset.structure === 'overlay' }, data.budget));
        const t = Math.round((b.plan.loops ? b.plan.tracks[0].duration : b.plan.length) * 0.4);
        // The preview's own picture at time t, from the same functions the preview uses.
        const preview = OmxSvgFrame.animatedSvg(stage.svg, b.plan, { time: t, frozen: true });
        out[name][key] = { scope: b.scope, names: b.names, files: b.files, snippets: b.snippets, loops: b.plan.loops, length: b.plan.length, restTime: b.plan.restTime, t, preview, edited: !!rawEdit, origin: b.plan.origin || null };
      }
    }
    return out;
  }, [logos, EDIT, EDITED]);
  write('base.css', 'html,body{margin:0;background:#fff}#t{width:300px;height:300px;display:block}#t img,#t svg{width:300px;height:300px;display:block}');
  const list = [];
  for (const [logo, presets] of Object.entries(built)) for (const [id, b] of Object.entries(presets)) {
    if (b.error) { fail(e, 'export failed ' + logo + '/' + id + ' ' + b.error); continue; }
    const d = logo + '/' + id + '/';
    for (const k of ['animated', 'still', 'css', 'js', 'json']) write(d + b.names[k], b.files[k]);
    write(d + 'image.html', PAGE('', b.snippets.image)); write(d + 'inline.html', PAGE('', b.snippets.inline)); write(d + 'external.html', PAGE('', b.snippets.external)); write(d + 'script.html', PAGE('', b.snippets.script));
    write(d + 'object.html', PAGE('', '<object type="image/svg+xml" data="' + b.names.animated + '"></object>')); write(d + 'background.html', PAGE('<style>#t{background:url(' + b.names.animated + ') center/contain no-repeat}</style>', ''));
    write(d + 'still.html', PAGE('', '<img src="' + b.names.still + '" alt="">')); write(d + 'still-inline.html', PAGE('', b.files.still));
    // The export frozen at time t (same rewrite the preview uses) next to the preview's own picture at t.
    // Every animation in a list (commas inside cubic-bezier() are not separators) moved back by t and paused.
    const frozen = b.files.animated.replace(/animation:([^;}]+)/g, (m, v) => 'animation:' + v.split(/,(?![^(]*\))/).map(x => x.trim().replace(/^([\w-]+) (\d+(?:\.\d+)?)ms (.+?) (-?\d+(?:\.\d+)?)ms (infinite|\d+) (\w+) both$/, (mm, n, du, ea, de, it, di) => n + ' ' + du + 'ms ' + ea + ' ' + (Number(de) - b.t) + 'ms ' + it + ' ' + di + ' both paused')).join(','));
    write(d + 'frozen.svg', frozen); write(d + 'preview.svg', b.preview); write(d + 'frozen.html', PAGE('', '<img src="frozen.svg" alt="">')); write(d + 'preview.html', PAGE('', '<img src="preview.svg" alt="">'));
    write(d + 'csp-image.html', CSP_PAGE(b.snippets.image)); write(d + 'csp-inline.html', CSP_PAGE(b.snippets.inline)); write(d + 'csp-external.html', CSP_PAGE(b.snippets.external)); write(d + 'csp-script.html', CSP_PAGE(b.snippets.script));
    list.push({ logo, id, d, b });
    // Names fixed, alt escaped, no viewer text as markup.
    if (!/^omochix-logo-motion-[a-z-]+(-static)?\.svg$/.test(b.names.animated) || !/alt="テスト &quot;ロゴ&quot; &lt;a&gt;"/.test(b.snippets.image)) fail(e, 'names or alt ' + logo + '/' + id);
  }
  out.generated = list.length; if (list.length !== 133 + EDITED.length) fail(e, 'generated ' + list.length);
  // The edited exports carry the edit's CSS and the pinned root origin.
  for (const it of list.filter(x => x.b.edited)) { const css = it.b.files.css; if (!/translate:0px -30px/.test(css) || !/rotate:45deg/.test(css) || !it.b.origin || !new RegExp('\\.' + it.b.scope + ' \\[data-omx-root\\]\\{transform-box:view-box;transform-origin:[-\\d.]+px [-\\d.]+px\\}').test(css)) fail(e, 'edited export lacks the edit or the origin ' + it.id); if (JSON.parse(it.b.files.json).schema !== 2) fail(e, 'edited settings file is not schema 2 ' + it.id); }
  for (const it of list.filter(x => !x.b.edited)) if (JSON.parse(it.b.files.json).schema !== 1) fail(e, 'preset-only settings file is not schema 1 ' + it.id);
  out.matrix.profile = PROFILE;

  // 2. Each form on its own page: it moves, it ends (or rests) exactly as the still file, nothing external, no errors.
  const ctx = await br.newContext({ viewport: { width: 700, height: 500 } }); const hp = await ctx.newPage(); await hp.goto(X + 'base.css'); await hp.evaluate(HELPERS);
  // A page screenshot of #t's box: unlike an element screenshot it does not wait for the element to "stabilise", which a
  // background WebKit page may never report while its logo moves. One retry, counted. A tab is brought to the front first and
  // two frames are waited for. A page in a window of its own (own) is front already, and in WebKit six windows fighting for
  // the front stall each other's frames (export 290s against 400s); Firefox, however, paints an unfocused window about once
  // a second, so there the front and the frame wait stay (export 330-520s against 800s without them; measured, S4-0 B-4).
  const shot = async (p, own, quick) => { for (let i = 0; ; i++) { try { if (!quick && (!own || e === 'firefox')) { await p.bringToFront(); await p.evaluate(() => new Promise(ok => requestAnimationFrame(() => requestAnimationFrame(ok)))); } const box = await p.evaluate(() => { const r = document.getElementById('t').getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; }); return (await p.screenshot({ clip: box, timeout: 10000 })).toString('base64'); } catch (x) { out.shotRetries = (out.shotRetries || 0) + 1; if (i) throw x; } } };
  const keep = (name, shots) => { const d = path.join(R, 'failures', e); fs.mkdirSync(d, { recursive: true }); shots.forEach((b64, i) => { if (b64) fs.writeFileSync(path.join(d, name.replace(/[^\w+-]/g, '_') + '-' + i + '.png'), Buffer.from(b64, 'base64')); }); };
  const diff = (a, b) => hp.evaluate(([x, y]) => __diff(x, y), [a, b]); const raw = (a, b) => hp.evaluate(([x, y]) => __raw(x, y), [a, b]);
  const forms = ['image', 'inline', 'external', 'script', 'object', 'background'];
  // Each form is compared with the still file drawn the same way (as an image, or inline), so the comparison is not about rendering paths.
  const stills = {};
  const stillOf = async (it, inline) => { const key = it.d + (inline ? 'inline' : 'image'); if (stills[key]) return stills[key]; const p = await ctx.newPage(); await p.goto(X + it.d + (inline ? 'still-inline.html' : 'still.html')); await p.waitForTimeout(80); const s = await shot(p); await p.close(); return (stills[key] = s); };
  const runForm = async (it, form, prefix, options = {}) => {
    // Real-time forms get a window of their own (options.ctx): a background tab in Firefox may not paint its animation.
    const own = !!options.ctx, p = await (options.ctx || ctx).newPage(); const ext = [], errs = [];
    p.on('request', q => { const u = q.url(); if (!/^(data:|blob:)/.test(u) && !u.startsWith(X)) ext.push(u); });
    p.on('console', m => { if (m.type() === 'error' && !/Refused to apply a stylesheet|inline style|style-src/.test(m.text())) errs.push(m.text().slice(0, 100)); });
    await p.goto(X + it.d + (prefix || '') + form + '.html'); await p.waitForLoadState('load');
    // Inline forms hold their animations in the page's own DOM, so the clock can be set instead of waited for; the image
    // forms (<img>, <object>, background) cannot be reached and run in real time.
    const driven = !REAL_TIME_FORMS.includes(form) && !options.realTime;
    const setTime = t => p.evaluate(t => { document.getAnimations().forEach(a => { a.pause(); a.currentTime = t; }); }, t);
    // An image form starts its clock when the file loads, and with six windows taking pictures at once a short animation
    // can be over before the first picture (every frame then equals the rest pose). So the file is loaded again, under a
    // fresh query, right before the first picture: the same file through the same form, with its clock started here.
    const restart = () => p.evaluate(() => new Promise(ok => {
      const q = '?omx=' + Date.now(), bare = u => u.split('?')[0] + q, img = document.querySelector('img'), o = document.querySelector('object');
      if (img) { img.src = bare(img.getAttribute('src')); (img.decode ? img.decode() : Promise.resolve()).then(ok, ok); }
      else if (o) { o.addEventListener('load', () => ok(), { once: true }); o.data = bare(o.getAttribute('data')); }
      else { const t = document.getElementById('t'), u = bare(getComputedStyle(t).backgroundImage.match(/url\("?([^")]+)"?\)/)[1]); const w = new Image(); w.onload = w.onerror = () => { t.style.backgroundImage = 'url("' + u + '")'; requestAnimationFrame(() => ok()); }; w.src = u; }
    }));
    const span = it.b.loops ? 3000 : Math.min(it.b.length, 1500); if (driven) await setTime(0); else if (REAL_TIME_FORMS.includes(form)) { await p.bringToFront(); await p.evaluate(() => new Promise(ok => requestAnimationFrame(() => requestAnimationFrame(ok)))); await restart(); }
    // The window is brought to the front and painting before the clock restarts, so the first picture follows at once
    // (waiting for frames after the restart cost Firefox over a second in a new window: first run, PR #55).
    const t0 = Date.now(); const shots = [await shot(p, own, !driven && REAL_TIME_FORMS.includes(form))]; const gap = Date.now() - t0;
    // A forced style pass before each picture: WebKit's capture otherwise misses composited filter animation (see S3 design, note 1).
    const force = () => p.evaluate(() => { const docs = [document]; const o = document.querySelector('object'); if (o && o.contentDocument) docs.push(o.contentDocument); docs.forEach(d => { const s = d.querySelector('svg'); if (s) { s.style.visibility = 'hidden'; void s.getBoundingClientRect(); s.style.visibility = ''; } }); });
    for (let i = 1; i <= 6; i++) { if (driven) await setTime(span * i / 6); else await p.waitForTimeout(span / 6); await force(); shots.push(await shot(p, own)); }
    // Movement between any two consecutive pictures counts, not only against the first one.
    let moves = 0; for (let i = 1; i < shots.length; i++) moves = Math.max(moves, await raw(shots[0], shots[i]), await raw(shots[i - 1], shots[i]));
    let rest = null, restShot = null; if (!it.b.loops) { if (driven) await setTime(it.b.length + 1); else await p.waitForTimeout(Math.max(0, it.b.length - span) + 300); await force(); restShot = await shot(p, own); rest = await diff(restShot, await stillOf(it, ['inline', 'external', 'script'].includes(form))); }
    await p.close(); return { moves, rest, ext: ext.length, errs, driven, gap, shots: [shots[0], shots[shots.length - 1], restShot] };
  };
  // Environment probe: which motion preference an SVG document sees when it is shown as an image. Every exported file
  // holds its own @media (prefers-reduced-motion: reduce) rule, and a browser may answer it from the operating system
  // rather than from the page's emulated preference (WebKit on the GitHub runners drew every image form still: PR #55).
  // Blue means no preference, red means reduce. The inline copy shows what the page itself sees.
  { const probe = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><style>rect{fill:#0000ff}@media (prefers-reduced-motion:reduce){rect{fill:#ff0000}}</style><rect width="10" height="10"/></svg>';
    write('probe-rm.svg', probe); write('probe-rm-image.html', PAGE('', '<img src="probe-rm.svg" alt="">')); write('probe-rm-inline.html', PAGE('', probe));
    const seen = {}; for (const f of ['image', 'inline']) { const p = await ctx.newPage(); await p.goto(X + 'probe-rm-' + f + '.html'); await p.waitForLoadState('load'); await p.waitForTimeout(80); const [r, , b] = await hp.evaluate(a => __center(a), await shot(p)); seen[f] = r > 200 && b < 80 ? 'reduce' : b > 200 && r < 80 ? 'no-preference' : 'unknown'; await p.close(); }
    out.motionPreference = seen;
    if (seen.image !== 'no-preference') fail(e, 'environment: SVG shown as an image sees prefers-reduced-motion ' + seen.image + ' (page: ' + seen.inline + '), so image forms are drawn still; turn the system setting off');
    if (seen.inline !== 'no-preference') fail(e, 'environment: the page itself sees prefers-reduced-motion ' + seen.inline); }
  // Controls for the driven clock: it must see a real animation move and a still page stay.
  const probe = list.find(x => x.logo === 'sample' && x.id === 'logo-pop'); const ctl = out.driveControl = {};
  { const p = await ctx.newPage(); await p.goto(X + probe.d + 'still-inline.html'); await p.waitForLoadState('load'); const a = await shot(p); await p.evaluate(() => { document.getAnimations().forEach(x => { x.pause(); x.currentTime = 500; }); }); ctl.stillMoves = await raw(a, await shot(p)); await p.close(); }
  for (const f of ['inline', 'script']) { const d = await runForm(probe, f), rt = await runForm(probe, f, '', { realTime: true }); ctl[f] = { driven: d.moves, real: rt.moves, drivenRest: d.rest, realRest: rt.rest }; if (d.moves <= 0 || rt.moves <= 0) fail(e, 'drive control: ' + f + ' ' + JSON.stringify(ctl[f])); }
  if (ctl.stillMoves > 0) fail(e, 'drive control: a still page moved ' + ctl.stillMoves);
  const queue = []; for (const it of list) for (const form of forms) { if (!(form === 'image' || form === 'inline' || form === 'script' || REPRESENTATIVE.includes(it.logo))) continue; if (PROFILE === 'fast' && REAL_TIME_FORMS.includes(form) && !REPRESENTATIVE.includes(it.logo)) continue; queue.push([it, form]); }
  // Each worker owns a window, so the real-time pages are all front tabs at once.
  const drain = (q, run) => Promise.all(Array.from({ length: PAR }, async () => { const own = await br.newContext({ viewport: { width: 700, height: 500 } }); while (q.length) { const item = q.shift(); await run(item, own); } await own.close(); }));
  const results = {}; await drain(queue, async ([it, form], own) => { results[it.logo + '/' + it.id + '/' + form] = await runForm(it, form, '', { ctx: own }); });
  // A real-time page that showed no movement is run once more on its own, with nothing else open: a short animation can still
  // be over before the first picture when the machine is busy. The count and the longest wait before a first picture are
  // reported; a page that does not move alone is a failure.
  out.realTime = { pages: 0, retried: [], gapMax: 0 };
  for (const [k, r] of Object.entries(results)) { if (r.driven) continue; out.realTime.pages++; out.realTime.gapMax = Math.max(out.realTime.gapMax, r.gap || 0); if (r.moves > 0) continue; const [logo, id, form] = k.split('/'); const it = list.find(x => x.logo === logo && x.id === id); const own = await br.newContext({ viewport: { width: 700, height: 500 } }); results[k] = await runForm(it, form, '', { ctx: own }); await own.close(); out.realTime.retried.push(k + ' gap ' + r.gap + '->' + results[k].gap + ' moves ' + results[k].moves); }
  for (const [k, r] of Object.entries(results)) { const before = failures(); if (r.moves <= 0) fail(e, 'does not move ' + k); // Playback ends on a composited layer and the still file is drawn statically: WebKit can place a mask edge 1px apart
    // (P08, measured 267px on one row). Real differences in pose are far larger. The preview comparison below stays at 0.
    if (r.rest !== null && r.rest > 0.5) fail(e, 'end differs from the still file ' + k + ' ' + r.rest); if (r.ext) fail(e, 'external request ' + k); if (r.errs.length) fail(e, 'console ' + k + ' ' + r.errs[0]);
    if (failures() > before) keep(k, r.shots); delete r.shots; }
  out.matrix.standalone = Object.keys(results).length; out.matrix.worstRest = Math.max(0, ...Object.values(results).map(r => r.rest || 0));

  // 3. Same picture as the preview at the same moment.
  let worst = 0; for (const it of list.filter(x => x.logo === 'sample' || x.logo === 'groupsIds')) { const a = await ctx.newPage(); await a.goto(X + it.d + 'frozen.html'); await a.waitForTimeout(80); const b = await ctx.newPage(); await b.goto(X + it.d + 'preview.html'); await b.waitForTimeout(80); const d = await diff(await shot(a), await shot(b)); worst = Math.max(worst, d); if (d > 0) fail(e, 'export differs from preview ' + it.logo + '/' + it.id + ' ' + d); await a.close(); await b.close(); }
  out.matrix.previewWorst = worst;

  // 4. CSP: image, external and script move; inline stays a still, unbroken logo.
  const cspQ = []; for (const it of list.filter(x => x.logo === 'sample')) for (const f of ['image', 'external', 'script', 'inline']) cspQ.push([it, f]); const csp = {};
  await drain(cspQ, async ([it, f], own) => { csp[it.id + '/' + f] = await runForm(it, f, 'csp-', { ctx: own }); });
  for (const [k, r] of Object.entries(csp)) { if (r.driven || r.moves > 0) continue; const [id, f] = k.split('/'); const it = list.find(x => x.logo === 'sample' && x.id === id); const own = await br.newContext({ viewport: { width: 700, height: 500 } }); csp[k] = await runForm(it, f, 'csp-', { ctx: own }); await own.close(); out.realTime.retried.push('csp-' + k + ' gap ' + r.gap + '->' + csp[k].gap + ' moves ' + csp[k].moves); }
  for (const [k, r] of Object.entries(csp)) { const inline = k.endsWith('/inline'); if (!inline && r.moves <= 0) fail(e, 'CSP: does not move ' + k); if (inline && r.moves > 0) fail(e, 'CSP: inline should be still ' + k); if (r.ext) fail(e, 'CSP external ' + k); }
  out.matrix.csp = Object.keys(csp).length;
  await ctx.close();

  // 5. Reduced motion: inline, external and script rest exactly as the still file; the image form switches to the still file.
  const rc = await br.newContext({ reducedMotion: 'reduce', viewport: { width: 700, height: 500 } }); const rp0 = await rc.newPage(); await rp0.goto(X + 'base.css'); await rp0.evaluate(HELPERS); const red = out.reduced = {};
  for (const it of list.filter(x => x.logo === 'sample')) for (const f of ['inline', 'external', 'script', 'image']) { const p = await rc.newPage(); await p.goto(X + it.d + f + '.html'); await p.waitForLoadState('load'); await p.waitForTimeout(150); const a = await shot(p); await p.waitForTimeout(350); const b = await shot(p); const src = f === 'image' ? await p.evaluate(() => document.querySelector('img').currentSrc) : ''; await p.close(); const sp = await rc.newPage(); await sp.goto(X + it.d + 'still.html'); await sp.waitForTimeout(80); const s = await shot(sp); await sp.close(); const moved = await rp0.evaluate(([x, y]) => __raw(x, y), [a, b]); const vs = await rp0.evaluate(([x, y]) => __diff(x, y), [b, s]); const before = failures(); red[it.id + '/' + f] = { moved, vs, src: src.split('/').pop() }; if (moved > 0) fail(e, 'reduced: moves ' + it.id + '/' + f); if (vs > 0.1) fail(e, 'reduced: not the still pose ' + it.id + '/' + f + ' ' + vs); if (f === 'image' && !/-static\.svg$/.test(src)) fail(e, 'reduced: image did not switch to the still file ' + it.id); if (failures() > before) keep('reduced-' + it.id + '-' + f, [a, b, s]); }
  await rc.close();

  // 6. Two logos with the same ids on one page.
  const two = list.filter(x => x.logo === 'gradient' && (x.id === 'logo-breathe' || x.id === 'logo-spin-in')); write('two.html', PAGE('<style>#t{display:flex;width:600px}</style>', two[0].b.snippets.inline + two[1].b.snippets.inline.replace(/#f06|#06f/g, '#00aa00').replace(/stop-color="#ff0066"|stop-color="#0066ff"/g, 'stop-color="#00aa00"'))); const tc = await br.newContext({ viewport: { width: 700, height: 400 } }); const tp = await tc.newPage(); await tp.goto(X + 'two.html'); await tp.waitForTimeout(200); out.two = await tp.evaluate(() => { const ids = [...document.querySelectorAll('[id]')].map(x => x.id); return { ids: ids.length, unique: new Set(ids).size, animations: document.getAnimations().length, names: [...new Set(document.getAnimations().map(a => a.animationName))].length }; }); if (out.two.ids !== out.two.unique || out.two.names < 2) fail(e, 'two logos ' + JSON.stringify(out.two)); await tc.close();

  // 6b. A logo's own stylesheet must not reach the page it is pasted into.
  const il = list.find(x => x.logo === 'illustratorLike' && x.id === 'logo-pop'); write('host.html', '<!doctype html><html><head><meta charset="utf-8"></head><body><p class="st0" id="host" style="color:rgb(0, 0, 0)">host</p><svg width="10" height="10"><rect class="st0" id="hostrect" width="10" height="10" fill="rgb(1, 2, 3)"/></svg>' + il.b.snippets.inline + '</body></html>'); const hc = await br.newContext(); const hp2 = await hc.newPage(); await hp2.goto(X + 'host.html'); await hp2.waitForTimeout(100); out.hostPage = await hp2.evaluate(() => ({ text: getComputedStyle(document.getElementById('host')).fill, rect: getComputedStyle(document.getElementById('hostrect')).fill, logoRect: getComputedStyle(document.querySelector('svg[class^="omx-"] .st0')).fill })); if (out.hostPage.rect !== 'rgb(1, 2, 3)' || out.hostPage.logoRect !== 'rgb(227, 6, 19)') fail(e, 'logo CSS leaked or lost ' + JSON.stringify(out.hostPage)); await hc.close();

  // 7. Hostile logos through the real upload, exported, and placed inline together: the worst a customer site could do.
  const C = 'http://127.0.0.1:8771', run = 'ex' + e + Date.now();
  const hostile = await g.evaluate(async ([A, C, run]) => { const data = JSON.parse(document.getElementById('omx-svg-motion-data').textContent); const out = { inline: [], still: [], keys: [], rejected: 0 }; for (const k in A) { let svg; try { svg = OmxSvgSafety.normalize(A[k].split('{C}').join(C + '/c/' + run + '/' + k + '/'), { prefix: 'omx-' }).svg; } catch (err) { out.rejected++; continue; } let b; try { b = OmxSvgExport.build({ svg, preset: data.presets[0], params: {}, common: {}, commonList: data.common, budget: data.budget, label: '' }); } catch (err) { out.exportFailed = (out.exportFailed || []).concat(k + ':' + err.message.slice(0, 60)); continue; } out.inline.push(b.snippets.inline); out.still.push(b.files.still); out.keys.push([k, A[k].includes('omxkeep')]); } return out; }, [ATTACKS, C, run]);
  write('hostile/inline.html', '<!doctype html><html><head><meta charset="utf-8"></head><body>' + hostile.inline.map(s => '<div style="width:100px;height:100px;display:inline-block">' + s + '</div>').join('') + '<script>window.__h=[];addEventListener("message",e=>{if(e.data&&e.data.hit)__h.push(e.data.hit)});</script></body></html>');
  write('hostile/control.html', '<!doctype html><html><head><meta charset="utf-8"></head><body><script>window.__h=[];addEventListener("message",e=>{if(e.data&&e.data.hit)__h.push(e.data.hit)});</script>' + ATTACKS.onerrorImg.split('{C}').join(C + '/c/' + run + '/control/') + ATTACKS.extImage.split('{C}').join(C + '/c/' + run + '/control/') + '</body></html>');
  hostile.still.forEach((s, i) => write('hostile/' + i + '.svg', s));
  const xc = await br.newContext({ viewport: { width: 1200, height: 1200 } }); const xp = await xc.newPage(); await xp.goto(X + 'hostile/inline.html'); await xp.waitForTimeout(1200); await xp.evaluate(() => document.querySelectorAll('svg *').forEach(x => { try { x.dispatchEvent(new MouseEvent('mouseover', { bubbles: true })); x.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true })); } catch (_) {} })); await xp.waitForTimeout(600); const hits = await xp.evaluate(() => window.__h); const stillUrl = xp.url();
  const cp = await xc.newPage(); await cp.goto(X + 'hostile/control.html'); await cp.waitForTimeout(800); const controlHits = await cp.evaluate(() => window.__h);
  // Markers drawn in each exported still file, by pixel.
  await xp.goto(X + 'base.css'); await xp.evaluate(HELPERS); let painted = 0, had = 0; for (let i = 0; i < hostile.keys.length; i++) { if (!hostile.keys[i][1]) continue; had++; const ip = await xc.newPage(); await ip.goto(X + 'hostile/' + i + '.svg'); await ip.setViewportSize({ width: 400, height: 400 }); await ip.waitForTimeout(40); const s = (await ip.screenshot()).toString('base64'); const v = await xp.evaluate(x => __ink(x), s); if (v.marker > 0) painted++; else fail(e, 'export marker not painted ' + hostile.keys[i][0]); await ip.close(); }
  await new Promise(r => setTimeout(r, 400)); const dump = await (await fetch(C + '/__dump?run=' + run)).json(); await xc.close();
  if (hostile.exportFailed) fail(e, 'export refused sanitized logos: ' + hostile.exportFailed.join(' '));
  out.hostile = { exported: hostile.inline.length, rejected: hostile.rejected, hits: hits.length, net: dump.filter(u => !u.includes('/control/')).length, markers: painted + '/' + had, controlHits: controlHits.length, controlNet: dump.filter(u => u.includes('/control/')).length, stayed: stillUrl.endsWith('inline.html') };
  if (out.hostile.hits || out.hostile.net) fail(e, 'exported hostile logos ran or reached out ' + JSON.stringify(out.hostile)); if (!out.hostile.controlHits || !out.hostile.controlNet) fail(e, 'control: detectors dead'); if (had !== 63) fail(e, 'markers expected 63, got ' + had); if (!out.hostile.stayed) fail(e, 'page navigated');

  // 8. The studio UI: usages, copy, download, settings JSON round trip and hostile JSON.
  await g.evaluate(() => { document.documentElement.style.scrollBehavior = 'auto'; });
  const up = async (t, n) => { await g.evaluate(() => document.querySelector('[data-svg-motion]').removeAttribute('data-svgm-last')); await g.setInputFiles('[data-svgm-file]', { name: n || 'logo.svg', mimeType: 'image/svg+xml', buffer: Buffer.from(t) }); await g.waitForSelector('[data-svg-motion][data-svgm-last]'); await g.waitForTimeout(120); return g.getAttribute('[data-svg-motion]', 'data-svgm-last'); };
  const waitExport = async () => { await g.waitForTimeout(350); return g.getAttribute('[data-svg-motion]', 'data-svgm-export'); };
  await up(LOGOS.gradient); await g.evaluate(() => { const i = document.querySelector('input[name="svgm-preset"][value="logo-wipe"]'); i.checked = true; i.dispatchEvent(new Event('change', { bubbles: true })); }); await g.selectOption('select[data-svgm-param="direction"]', 'up'); await g.fill('[data-svgm-alt]', 'Acme "logo" <b>');
  const scope1 = await waitExport(); const ui = out.ui; ui.scope = scope1; if (!/^omx-[0-9a-f]{8}$/.test(scope1 || '')) fail(e, 'export not ready ' + scope1);
  const codes = {}; for (const u of ['image', 'inline', 'external', 'script']) { await g.check('input[name="svgm-usage"][value="' + u + '"]', { force: true }); await g.waitForTimeout(80); codes[u] = await g.textContent('[data-svgm-code]'); const btns = await g.evaluate(() => [...document.querySelectorAll('[data-svgm-download]')].map(b => b.getAttribute('data-svgm-download'))); ui[u] = btns; }
  if (!codes.image.includes('<picture>') || !codes.inline.startsWith('<svg') || !codes.external.includes('<link rel="stylesheet"') || !codes.script.includes('<script src=')) fail(e, 'usage snippets');
  if (!codes.image.includes('alt="Acme &quot;logo&quot; &lt;b&gt;"')) fail(e, 'alt not escaped: ' + codes.image.slice(0, 200)); if (await g.evaluate(() => document.querySelector('[data-svgm-code]').children.length)) fail(e, 'code became markup');
  // Copy (http: the fallback path) and downloads.
  await g.check('input[name="svgm-usage"][value="image"]', { force: true }); await g.waitForTimeout(80); await g.click('[data-svgm-copy]'); await g.waitForTimeout(150); ui.copied = await g.getAttribute('[data-svg-motion]', 'data-svgm-copied');
  const files = {}; for (const key of ['animated', 'still']) { const [dl] = await Promise.all([g.waitForEvent('download'), g.click('[data-svgm-download="' + key + '"]')]); files[key] = { name: dl.suggestedFilename(), text: fs.readFileSync(await dl.path(), 'utf8') }; }
  ui.files = Object.fromEntries(Object.entries(files).map(([k, v]) => [k, v.name])); if (files.animated.name !== 'omochix-logo-motion-wipe.svg' || files.still.name !== 'omochix-logo-motion-wipe-static.svg') fail(e, 'file names ' + JSON.stringify(ui.files)); if (!files.animated.text.includes('@keyframes ' + scope1 + '-k0') || !codes.image.includes(files.animated.name)) fail(e, 'downloaded file does not match the code');
  const [jdl] = await Promise.all([g.waitForEvent('download'), g.click('[data-svgm-json-download]')]); const jsonText = fs.readFileSync(await jdl.path(), 'utf8'); ui.jsonName = jdl.suggestedFilename(); const j = JSON.parse(jsonText); if (j.format !== 'omochix-svg-motion' || j.schema !== 1 || j.settings.preset !== 'logo-wipe' || j.settings.params.direction !== 'up') fail(e, 'json content');
  // Round trip in a fresh browser: same output, byte for byte.
  const rctx = await br.newContext({ viewport: { width: 1280, height: 900 } }); const r = await rctx.newPage(); await r.goto(U); await r.waitForSelector('[data-svgm-ready="true"]'); fs.writeFileSync(path.join(R, 'roundtrip.json'), jsonText); await r.evaluate(() => document.querySelector('[data-svg-motion]').removeAttribute('data-svgm-last')); await r.setInputFiles('[data-svgm-json-file]', path.join(R, 'roundtrip.json')); await r.waitForSelector('[data-svg-motion][data-svgm-last]'); await r.fill('[data-svgm-alt]', 'Acme "logo" <b>'); await r.waitForTimeout(400); const after = await r.evaluate(() => ({ preset: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-preset'), scope: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-export'), code: document.querySelector('[data-svgm-code]').textContent, last: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-last') })); const cls = t => (t.match(/omochix-logo-motion|class="(omx-[0-9a-f]{8})"/) || [])[1]; ui.roundTrip = { last: after.last, preset: after.preset, sameScope: (codes.inline.match(/class="(omx-[0-9a-f]{8})"/) || [])[1] === (after.scope || ''), sameCode: after.code === codes.image }; if (after.last !== 'ok' || after.preset !== 'logo-wipe' || after.code !== codes.image) fail(e, 'json round trip ' + JSON.stringify(ui.roundTrip));
  // S4-0: a schema 2 file (an edit) through the studio: kept, measured, exported, saved, and dropped with a new logo.
  const withEdit = (edit, extra) => JSON.stringify(Object.assign({}, j, { schema: 2, edit }, extra || {}));
  const studio = async () => { await r.check('input[name="svgm-usage"][value="inline"]', { force: true }); await r.waitForTimeout(80); return r.evaluate(() => { const n = document.querySelector('[data-svg-motion]'); return { last: n.getAttribute('data-svgm-last'), edit: n.getAttribute('data-svgm-edit'), origin: n.getAttribute('data-svgm-origin'), scope: n.getAttribute('data-svgm-export'), code: document.querySelector('[data-svgm-code]').textContent, status: document.querySelector('[data-svgm-status]').textContent, logo: n.getAttribute('data-svgm-logo') }; }); };
  const feed = async (name, text) => { fs.writeFileSync(path.join(R, name), text); await r.evaluate(() => document.querySelector('[data-svg-motion]').removeAttribute('data-svgm-last')); await r.setInputFiles('[data-svgm-json-file]', path.join(R, name)); await r.waitForSelector('[data-svg-motion][data-svgm-last]'); await r.waitForTimeout(500); return studio(); };
  const ed = ui.edit = {};
  const good = await feed('e1.json', withEdit(EDIT)); ed.good = { last: good.last, edit: good.edit, origin: good.origin, hasEditCss: /translate:0px -30px/.test(good.code) && /rotate:45deg/.test(good.code), pinned: /\[data-omx-root\]\{transform-box:view-box;transform-origin:[-\d.]+px [-\d.]+px\}/.test(good.code) };
  if (good.last !== 'ok' || good.edit !== '2' || !good.origin || !ed.good.hasEditCss || !ed.good.pinned) fail(e, 'schema 2 import ' + JSON.stringify(ed.good));
  // Saved as schema 2 and back after a reload, with the same code.
  await r.waitForTimeout(600); await r.reload(); await r.waitForSelector('[data-svgm-ready="true"]'); await r.waitForTimeout(600); await r.fill('[data-svgm-alt]', 'Acme "logo" <b>'); await r.waitForTimeout(400); const back = await studio(); const rec = await r.evaluate(() => new Promise(ok => { const q = indexedDB.open('omx-svg-motion', 1); q.onsuccess = () => { const g = q.result.transaction('state').objectStore('state').get('current'); g.onsuccess = () => { q.result.close(); ok(g.result ? { schema: g.result.schema, layers: g.result.edit && g.result.edit.layers.length, origin: !!(g.result.edit && g.result.edit.origin) } : null); }; }; }));
  ed.reload = { edit: back.edit, sameCode: back.code === good.code, saved: rec }; if (back.edit !== '2' || back.code !== good.code || !rec || rec.schema !== 2 || rec.layers !== 2 || !rec.origin) fail(e, 'edit did not survive a reload ' + JSON.stringify(ed.reload));
  // Refused edits: the logo and the kept edit stay as they were.
  const bads = { limit: withEdit(Object.assign({}, EDIT, { nextId: 50, layers: Array.from({ length: 41 }, (_, i) => ({ id: 'L' + (i + 1), target: { kind: 'part', index: i }, start: 0, end: 100, tracks: [{ prop: 'opacity', easing: 'linear', keys: [{ at: 0, v: [0] }, { at: 1, v: [1] }] }] })) })), invalid: withEdit(Object.assign({}, EDIT, { layers: [Object.assign({}, EDIT.layers[0], { tracks: [{ prop: 'translate', easing: 'soft', keys: [{ at: 0, v: [0, 99999] }, { at: 1, v: [0, 0] }] }] })] })), conflict: withEdit(Object.assign({}, EDIT, { layers: [Object.assign({}, EDIT.layers[0], { tracks: [{ prop: 'rotate', easing: 'soft', keys: [{ at: 0, v: [0] }, { at: 1, v: [1] }] }, { prop: 'rotate', easing: 'soft', keys: [{ at: 0, v: [0] }, { at: 1, v: [2] }] }] })] })), hostile: withEdit(Object.assign({}, EDIT, { layers: [Object.assign({}, EDIT.layers[0], { tracks: [{ prop: 'fill', easing: 'soft', keys: [{ at: 0, v: ['url(https://evil.example)'] }, { at: 1, v: ['#000000'] }] }] })] })), schemaOneWithEdit: JSON.stringify(Object.assign({}, j, { edit: EDIT })) };
  const want = { limit: 'EDIT_LIMIT', invalid: 'EDIT_INVALID', conflict: 'EDIT_CONFLICT', hostile: 'EDIT_INVALID', schemaOneWithEdit: 'JSON_INVALID' };
  for (const [k, text] of Object.entries(bads)) { const got = await feed('e-' + k + '.json', text); ed[k] = { last: got.last, edit: got.edit, logo: got.logo, sameCode: got.code === good.code }; if (got.last !== want[k] || got.edit !== '2' || got.logo !== 'user' || got.code !== good.code) fail(e, 'bad edit ' + k + ' ' + JSON.stringify(ed[k])); }
  // A part that this logo does not have: refused after the logo loads, the import still succeeds without the edit.
  const missing = await feed('e-missing.json', withEdit(Object.assign({}, EDIT, { layers: [Object.assign({}, EDIT.layers[0], { target: { kind: 'part', index: 9000 } })] }))); ed.missing = { last: missing.last, edit: missing.edit, said: /取り消し/.test(missing.status) }; if (missing.last !== 'ok' || missing.edit !== 'none' || !ed.missing.said) fail(e, 'missing part ' + JSON.stringify(ed.missing));
  // A new logo drops the edit, and says so.
  await feed('e2.json', withEdit(EDIT)); await r.evaluate(() => document.querySelector('[data-svg-motion]').removeAttribute('data-svgm-last')); await r.setInputFiles('[data-svgm-file]', { name: 'other.svg', mimeType: 'image/svg+xml', buffer: Buffer.from(LOGOS.useBasic) }); await r.waitForSelector('[data-svg-motion][data-svgm-last]'); await r.waitForTimeout(300); const dropped = await studio(); ed.dropped = { edit: dropped.edit, said: /取り消し/.test(dropped.status) }; if (dropped.edit !== 'none' || !ed.dropped.said) fail(e, 'edit not dropped with a new logo ' + JSON.stringify(ed.dropped));
  // Hostile and broken settings files: refused with a reason, or accepted only after the full pipeline. The current logo stays.
  const bad = ui.badJson = {}; const tryJson = async (name, text) => { fs.writeFileSync(path.join(R, name), text); await r.evaluate(() => { window.__h = []; addEventListener('message', ev => { if (ev.data && ev.data.hit) window.__h.push(ev.data.hit); }); document.querySelector('[data-svg-motion]').removeAttribute('data-svgm-last'); }); await r.setInputFiles('[data-svgm-json-file]', path.join(R, name)); await r.waitForSelector('[data-svg-motion][data-svgm-last]'); await r.waitForTimeout(200); return r.evaluate(() => ({ last: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-last'), logo: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-logo'), err: document.querySelector('[data-svgm-error]').hidden ? '' : document.querySelector('[data-svgm-error]').textContent, hits: window.__h.length, inline: document.querySelectorAll('[data-svg-motion] svg, [id^="omx-"]:not(#omx-svg-motion-data)').length })); };
  bad.schema = await tryJson('b1.json', JSON.stringify({ format: 'omochix-svg-motion', schema: 9, svg: LOGOS.simple, settings: {} }));
  bad.format = await tryJson('b2.json', JSON.stringify({ format: 'other', schema: 1, svg: LOGOS.simple, settings: {} }));
  bad.invalid = await tryJson('b3.json', '{"format": "omochix-svg-motion", ');
  bad.large = await tryJson('b4.json', JSON.stringify({ format: 'omochix-svg-motion', schema: 1, svg: '<svg xmlns="http://www.w3.org/2000/svg"><desc>' + 'a'.repeat(2200000) + '</desc></svg>', settings: {} }));
  bad.brokenSvg = await tryJson('b5.json', JSON.stringify({ format: 'omochix-svg-motion', schema: 1, svg: '<svg><g></svg>', settings: { preset: 'logo-pop' } }));
  bad.hostileSvg = await tryJson('b6.json', JSON.stringify({ format: 'omochix-svg-motion', schema: 1, svg: ATTACKS.foreignImg.split('{C}').join(C + '/c/' + run + '/json/') + '', settings: { preset: '__proto__', params: { 'logo-pop': { from: 'url(https://evil.example)' } }, common: { opacity: 0 } } }));
  const expect = { schema: 'JSON_SCHEMA', format: 'JSON_FORMAT', invalid: 'JSON_INVALID', large: 'JSON_TOO_LARGE', brokenSvg: 'PARSE_ERROR' };
  for (const [k, code] of Object.entries(expect)) { if (bad[k].last !== code) fail(e, 'json ' + k + ' gave ' + bad[k].last); if (bad[k].logo !== 'user' || !/[ぁ-ん一-龠]/.test(bad[k].err)) fail(e, 'json ' + k + ' lost the logo or reason'); }
  if (bad.hostileSvg.last !== 'ok' || bad.hostileSvg.hits || bad.hostileSvg.inline) fail(e, 'hostile json ' + JSON.stringify(bad.hostileSvg));
  const finalCode = await r.evaluate(() => document.querySelector('[data-svgm-code]').textContent); if (/evil|url\(https/.test(finalCode)) fail(e, 'hostile json values reached the export');
  // Negative control: when the export's own check fails, no file and no copy is offered, and the reason shows.
  await r.check('input[name="svgm-usage"][value="image"]', { force: true }); await r.evaluate(() => { window.__verify = OmxSvgSafety.verify; OmxSvgSafety.verify = () => ['forced']; }); await r.fill('[data-svgm-alt]', 'x'); await r.waitForTimeout(400); const failed = await r.evaluate(() => ({ state: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-export'), buttons: document.querySelectorAll('[data-svgm-download]').length, copyDisabled: document.querySelector('[data-svgm-copy]').disabled, code: document.querySelector('[data-svgm-code]').textContent })); await r.evaluate(() => { OmxSvgSafety.verify = window.__verify; }); await r.fill('[data-svgm-alt]', 'y'); await r.waitForTimeout(400); const recovered = await r.evaluate(() => ({ state: document.querySelector('[data-svg-motion]').getAttribute('data-svgm-export'), buttons: document.querySelectorAll('[data-svgm-download]').length, copyDisabled: document.querySelector('[data-svgm-copy]').disabled }));
  ui.exportFailed = { failed, recovered: recovered }; if (failed.state !== 'failed' || failed.buttons || !failed.copyDisabled || !/[ぁ-ん一-龠]/.test(failed.code) || !/^omx-/.test(recovered.state || '') || !recovered.buttons || recovered.copyDisabled) fail(e, 'export failure handling ' + JSON.stringify(ui.exportFailed));
  await rctx.close(); await gctx.close();
  out.errors = errors; if (errors.length) fail(e, 'export page errors ' + errors.slice(0, 2));
  fs.writeFileSync(path.join(R, 'export-' + e + '.json'), JSON.stringify(out, null, 1));
  return { generated: out.generated, standalone: out.matrix.standalone, csp: out.matrix.csp, previewWorst: out.matrix.previewWorst, worstRest: out.matrix.worstRest, hostile: out.hostile, two: out.two, shotRetries: out.shotRetries || 0, realTime: out.realTime, motionPreference: out.motionPreference };
}
module.exports = { exportTests };
