// S4-0: how several motions share one element, and what that costs. The product's own compiler writes the CSS;
// the browser draws it inside <img>, as the preview and the image export do; the pixels are compared with a single
// reference transform. Chromium, Firefox and WebKit must all match the reference, or the composition method is wrong.
const fs = require('fs'), path = require('path');
const R = path.join(__dirname, 'results');
const C = require(path.join(__dirname, '../../wp-theme/omochix-theme/assets/js/svg-motion/compile.js'));
const DATA = JSON.parse(fs.readFileSync(path.join(__dirname, '../../wp-theme/omochix-theme/assets/data/svg-motion/presets.json'), 'utf8'));
const lerp = (a, b, t) => a + (b - a) * t;
const SVG = (style, body) => 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200"><style>' + style + '</style>' + body + '</svg>');
// Two parts, so the root's box is larger than either of them: the pinned origin must be the root's own centre (100,100).
const PARTS = '<g data-omx-part="0"><rect x="40" y="40" width="40" height="20" fill="#e30613"/></g><g data-omx-part="1"><rect x="120" y="120" width="40" height="40" fill="#e30613"/></g>';
const STAGE = body => '<g data-omx-base=""><g data-omx-root="">' + body + '</g></g>';
const LAYOUT = { parts: 2, geometry: 2, width: 200, height: 200, ownStroke: [false, false], fillOpacity: ['1', '1'] };
const HELPERS = () => {
  const load = u => new Promise((ok, no) => { const i = new Image(); i.onload = () => { const c = document.createElement('canvas'); c.width = i.width; c.height = i.height; const x = c.getContext('2d'); x.drawImage(i, 0, 0); ok(x.getImageData(0, 0, c.width, c.height).data); }; i.onerror = no; i.src = 'data:image/png;base64,' + u; });
  window.__px = async (a, b) => { const A = await load(a), B = await load(b); let n = 0, ink = 0; for (let j = 0; j < A.length; j += 4) { if (A[j + 1] < 200) ink++; if (Math.abs(A[j] - B[j]) + Math.abs(A[j + 1] - B[j + 1]) + Math.abs(A[j + 2] - B[j + 2]) > 60) n++; } return { diff: n, ink }; };
};

async function composeTests(br, e, fail) {
  const out = { cases: {}, perf: {} };
  const ctx = await br.newContext({ viewport: { width: 320, height: 320 } }); const p = await ctx.newPage(); await p.setContent('<body style="margin:0;background:#fff"></body>'); await p.evaluate(HELPERS);
  const shot = async src => { await p.setContent('<body style="margin:0;background:#fff"><img id="i" src="' + src + '"></body>'); await p.waitForFunction(() => document.getElementById('i').complete && document.getElementById('i').naturalWidth > 0); await p.waitForTimeout(120); return (await p.locator('#i').screenshot()).toString('base64'); };
  const px = (a, b) => p.evaluate(([x, y]) => window.__px(x, y), [a, b]);
  // The product's CSS for an edit, frozen at time t the way the preview freezes it.
  const productCss = (edit, t, base) => C.planToCss(C.compileEdit(base || { preset: 'none', rest: 'end', setup: [], tracks: [] }, C.validateEdit(edit, LAYOUT)), { time: t, frozen: true });
  const edit = (layers, origin) => Object.assign({ duration: 1000, nextId: 9, layers }, origin ? { origin } : {});
  const track = (prop, a, b) => ({ prop, easing: 'linear', keys: [{ at: 0, v: a }, { at: 1, v: b }] });

  // 1. Five motions on one part at three times: translate, rotate, scale and opacity as individual properties, plus a
  //    preset-style `transform` on the same element. Reference: one transform holding all of it.
  for (const t of [0.25, 0.5, 0.75]) {
    const ref = SVG('g{transform-box:fill-box;transform-origin:center}', STAGE('<g data-omx-part="0" style="opacity:' + lerp(1, 0.4, t) + ';transform:translate(' + lerp(0, 40, t) + 'px,0) rotate(' + lerp(0, 90, t) + 'deg) scale(' + lerp(1, 0.5, t) + ') skewX(' + lerp(0, 20, t) + 'deg)"><rect x="40" y="40" width="40" height="20" fill="#e30613"/></g>' + '<g data-omx-part="1"><rect x="120" y="120" width="40" height="40" fill="#e30613"/></g>'));
    const base = { preset: 'x', rest: 'end', setup: [], tracks: [{ target: { kind: 'part', index: 0 }, frames: [{ offset: 0, props: { transform: 'skewX(0deg)' } }, { offset: 1, props: { transform: 'skewX(20deg)' } }], delay: 0, duration: 1000, iterations: 1, direction: 'normal', easing: 'linear' }] };
    const css = productCss(edit([{ id: 'L1', target: { kind: 'part', index: 0 }, start: 0, end: 1000, tracks: [track('translate', [0, 0], [40, 0]), track('rotate', [0], [90]), track('scale', [1], [0.5]), track('opacity', [1], [0.4])] }]), t * 1000, base);
    const r = await px(await shot(ref), await shot(SVG(css, STAGE(PARTS)))); out.cases['five-on-one@' + t] = r; if (r.diff > 0 || r.ink < 50) fail(e, 'composition differs from the reference at ' + t + ': ' + JSON.stringify(r));
  }
  // 2. The root rotates (as logo-wiggle or logo-spin-in would) while a part scales: with the pinned origin the root turns
  //    about its own centre in every engine. Without it, the engines disagree (measured in the S4 review).
  {
    const ref = SVG('', STAGE('<g data-omx-part="0" style="transform-box:fill-box;transform-origin:center;transform:scale(.5)"><rect x="40" y="40" width="40" height="20" fill="#e30613"/></g><g data-omx-part="1"><rect x="120" y="120" width="40" height="40" fill="#e30613"/></g>').replace('<g data-omx-root="">', '<g data-omx-root="" style="transform-box:view-box;transform-origin:100px 100px;transform:rotate(45deg)">'));
    const css = productCss(edit([{ id: 'L1', target: { kind: 'part', index: 0 }, start: 0, end: 1000, tracks: [track('scale', [1], [0.5])] }, { id: 'L2', target: { kind: 'root', index: 0 }, start: 0, end: 1000, tracks: [track('rotate', [0], [45])] }], { x: 100, y: 100 }), 1000);
    const r = await px(await shot(ref), await shot(SVG(css, STAGE(PARTS)))); out.cases.pinnedRoot = r; if (r.diff > 0 || r.ink < 50) fail(e, 'pinned root origin differs from the reference: ' + JSON.stringify(r));
    // Control: the comparison sees a wrong origin.
    const wrong = productCss(edit([{ id: 'L1', target: { kind: 'part', index: 0 }, start: 0, end: 1000, tracks: [track('scale', [1], [0.5])] }, { id: 'L2', target: { kind: 'root', index: 0 }, start: 0, end: 1000, tracks: [track('rotate', [0], [45])] }], { x: 60, y: 60 }), 1000);
    const c = await px(await shot(ref), await shot(SVG(wrong, STAGE(PARTS)))); out.cases.pinnedRootControl = c; if (c.diff < 100) fail(e, 'control: a wrong origin went unnoticed ' + JSON.stringify(c));
  }
  // 3. A real preset with the edit laid over it, frozen by the product at two times, equals the same CSS drawn inline (the
  //    export's inline form) — the two ways a site shows the file.
  {
    const pop = DATA.presets.find(x => x.id === 'logo-pop'); const base = C.buildPlan(pop, {}, LAYOUT);
    for (const t of [300, 700]) {
      const css = productCss(edit([{ id: 'L1', target: { kind: 'part', index: 1 }, start: 0, end: 1000, tracks: [track('translate', [0, 0], [-20, 10]), track('rotate', [0], [30])] }], { x: 100, y: 100 }), t, base);
      await p.setContent('<body style="margin:0;background:#fff"><div id="w" style="width:200px;height:200px"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200"><style>' + css + '</style>' + STAGE(PARTS) + '</svg></div></body>'); await p.waitForTimeout(120); const inline = (await p.locator('#w').screenshot()).toString('base64');
      const r = await px(inline, await shot(SVG(css, STAGE(PARTS)))); out.cases['preset+edit@' + t] = r; if (r.diff > 0) fail(e, 'inline and image differ with an edit at ' + t + ': ' + JSON.stringify(r));
    }
  }

  // 4. Cost. A logo at the part budget, the largest edit allowed: how long one preview picture takes (the thing a slider
  //    drag does over and over), and how the engine copes with 120 animations running at once.
  const parts = Array.from({ length: 200 }, (_, i) => '<g data-omx-part="' + i + '"><rect x="' + (i % 20) * 10 + '" y="' + Math.floor(i / 20) * 20 + '" width="8" height="16" fill="#' + ((i * 2654435) & 0xffffff).toString(16).padStart(6, '0') + '"/></g>').join('');
  const bigLayout = { parts: 200, geometry: 200, width: 200, height: 200, ownStroke: [], fillOpacity: [] };
  const bigEdit = C.validateEdit({ duration: 4000, nextId: 41, layers: Array.from({ length: 40 }, (_, i) => ({ id: 'L' + (i + 1), target: { kind: 'part', index: i * 5 }, start: i * 50, end: 4000, tracks: [track('translate', [0, 0], [10, -10]), track('rotate', [0], [180]), track('scale', [1], [0.5])] })) }, bigLayout);
  const pop = DATA.presets.find(x => x.id === 'logo-pop'); const bigPlan = C.compileEdit(C.buildPlan(pop, {}, bigLayout), bigEdit); const bigSvgBody = STAGE(parts);
  out.perf.tracks = bigPlan.tracks.length; out.perf.cssBytes = C.planToCss(bigPlan, { scope: 'omx-abcd1234', reducedRest: true }).length;
  if (bigPlan.tracks.length < 120) fail(e, 'perf: expected at least 120 tracks, got ' + bigPlan.tracks.length);
  // (a) one picture: CSS from the plan, blob URL, decode. Median of 15, as the preview does it.
  const frames = []; for (let i = 0; i < 15; i++) frames.push(C.planToCss(bigPlan, { time: i * 200, frozen: true }));
  const regen = await p.evaluate(async ([cssList, body]) => { const times = []; for (const css of cssList) { const t0 = performance.now(); const text = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200"><style>' + css + '</style>' + body + '</svg>'; const url = URL.createObjectURL(new Blob([text], { type: 'image/svg+xml' })); const img = new Image(); img.src = url; await img.decode(); times.push(performance.now() - t0); URL.revokeObjectURL(url); } times.sort((a, b) => a - b); return { median: +times[7].toFixed(1), max: +times[14].toFixed(1) }; }, [frames, bigSvgBody]);
  out.perf.picture = regen; if (regen.median > 100) fail(e, 'perf: a preview picture takes ' + regen.median + 'ms (limit 100)');
  // (b) 120 animations running inline for one second: frames the page still manages to paint (a proxy for the load an engine
  //     carries; the <img> form cannot be observed from the page). Recorded, not judged.
  const live = C.planToCss(bigPlan); const fps = await p.evaluate(async ([css, body]) => { document.body.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="300" height="300"><style>' + css + '</style>' + body + '</svg>'; let n = 0; const t0 = performance.now(); await new Promise(ok => { (function f() { n++; if (performance.now() - t0 < 1000) requestAnimationFrame(f); else ok(); })(); }); return { fps: n, animations: document.getAnimations().length }; }, [live, bigSvgBody]);
  out.perf.live = fps; if (fps.animations < 120) fail(e, 'perf: expected 120 running animations, saw ' + fps.animations);
  await ctx.close();
  fs.writeFileSync(path.join(R, 'compose-' + e + '.json'), JSON.stringify(out, null, 1));
  return { cases: Object.keys(out.cases).length, picture: regen, live: fps, tracks: out.perf.tracks, cssBytes: out.perf.cssBytes };
}
module.exports = { composeTests };
