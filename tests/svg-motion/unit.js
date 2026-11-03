// Plan builder checks that need no browser. Run: node tests/svg-motion/unit.js
const assert = require('assert'), path = require('path');
const C = require(path.join(__dirname, '../../wp-theme/omochix-theme/assets/js/svg-motion/compile.js'));
const data = require(path.join(__dirname, '../../wp-theme/omochix-theme/assets/data/svg-motion/presets.json'));
let pass = 0, fail = 0; const t = (n, f) => { try { f(); pass++; console.log('PASS ' + n); } catch (e) { fail++; console.log('FAIL ' + n + ': ' + e.message); } };
const L = { parts: 3, geometry: 3, width: 120, height: 120, ownStroke: [false, false, true], fillOpacity: ['1', '.85', '1'] };
const one = { parts: 1, geometry: 1, width: 100, height: 100, ownStroke: [false], fillOpacity: ['1'] };
t('every preset has a builder and no builder is extra', () => assert.deepStrictEqual(Object.keys(C.BUILDERS).sort(), data.presets.map(p => p.id).sort()));
for (const p of data.presets) {
  t(p.id + ': valid plan at defaults', () => assert(C.isValidPlan(C.buildPlan(p, {}, L))));
  t(p.id + ': valid with one part', () => { const pl = C.buildPlan(p, {}, one); assert(C.isValidPlan(pl) && pl.tracks.length >= 1); });
  t(p.id + ': valid at both ends of every range', () => { for (const end of ['min', 'max']) { const v = {}; p.params.forEach(q => { if (q.type === 'range') v[q.key] = q[end]; }); assert(C.isValidPlan(C.buildPlan(p, v, L)), end); } });
  t(p.id + ': every parameter changes the plan', () => { const base = JSON.stringify(C.buildPlan(p, {}, L)); p.params.forEach(q => { const v = {}; v[q.key] = q.type === 'range' ? (q.default === q.max ? q.min : q.max) : q.type === 'color' ? '#123456' : q.options.find(o => o.value !== q.default).value; assert.notStrictEqual(JSON.stringify(C.buildPlan(p, v, L)), base, q.key); }); });
  t(p.id + ': every range default fits its step', () => p.params.filter(q => q.type === 'range').forEach(q => assert.strictEqual(C.sanitizeParams(p, {})[q.key], q.default, q.key)));
  t(p.id + ': hostile values are cleaned', () => { const v = {}; p.params.forEach(q => v[q.key] = 'url(https://evil.example/)};@import'); const pl = C.buildPlan(p, v, L); assert(C.isValidPlan(pl)); assert(!/evil|url|import/.test(JSON.stringify(pl) + C.planToCss(pl))); });
  t(p.id + ': css output has keyframes and no external reference', () => { const css = C.planToCss(C.buildPlan(p, {}, L)); assert(/@keyframes/.test(css) && !/url\(|@import|https?:/.test(css)); });
}
t('isValidPlan refuses url(), unknown properties and selector targets', () => {
  const good = () => JSON.parse(JSON.stringify(C.buildPlan(data.presets[0], {}, L)));
  let p = good(); p.tracks[0].frames[0].props.opacity = 'url(x)'; assert(!C.isValidPlan(p));
  p = good(); p.tracks[0].frames[0].props.background = 'red'; assert(!C.isValidPlan(p));
  p = good(); p.tracks[0].target = { kind: 'selector', index: 0 }; assert(!C.isValidPlan(p));
  p = good(); p.tracks[0].target = { kind: 'part', index: '0"]' }; assert(!C.isValidPlan(p));
});
t('over the shape budget: per-shape presets refuse, the others still plan', () => { const big = Object.assign({}, one, { tooManyShapes: true }); const refused = data.presets.filter(p => { try { C.buildPlan(p, {}, big); return false; } catch (e) { assert.strictEqual(e.message, 'TOO_MANY_SHAPES'); return true; } }).map(p => p.id).sort(); assert.deepStrictEqual(refused, ['logo-color-shift', 'logo-dash-flow', 'logo-draw', 'logo-draw-fill', 'logo-shine']); });
t('filter and 3D target the svg element, never an inner element', () => { for (const id of ['logo-focus', 'logo-flip', 'logo-glow']) { const pl = C.buildPlan(data.presets.find(p => p.id === id), {}, L); assert(pl.tracks.every(t => t.target.kind === 'stage'), id); } const inner = data.presets.filter(p => !['logo-focus', 'logo-flip', 'logo-glow'].includes(p.id)); inner.forEach(p => C.buildPlan(p, {}, L).tracks.forEach(t => assert(!t.frames.some(f => f.props.filter), p.id))); });
const allCommon = { delay: 100, easing: 'soft', loop: '2', stagger: 50, opacity: 0.5, scale: 1.5, x: 10, y: -5, rotate: 45 };
t('common: every preset stays valid with every adjustment at once', () => data.presets.forEach(p => assert(C.isValidPlan(C.buildPlan(p, {}, L, C.sanitizeCommon(data.common, allCommon))), p.id)));
t('common: the pose goes on its own wrapper, never on an animated target', () => data.presets.forEach(p => { const pl = C.buildPlan(p, {}, L, C.sanitizeCommon(data.common, allCommon)); const base = pl.setup.filter(s => s.target.kind === 'base'); assert.strictEqual(base.length, 1, p.id); assert(/translate\(12px,-6px\) rotate\(45deg\) scale\(1\.5\)/.test(base[0].styles.transform), p.id); assert(!pl.tracks.some(t => t.target.kind === 'base'), p.id); }));
t('common: defaults add nothing', () => data.presets.forEach(p => assert.deepStrictEqual(C.buildPlan(p, {}, L, C.sanitizeCommon(data.common, {})), C.buildPlan(p, {}, L), p.id)));
t('common: delay, loop and easing reach every track', () => data.presets.forEach(p => { const a = C.buildPlan(p, {}, L), b = C.buildPlan(p, {}, L, C.sanitizeCommon(data.common, { delay: 200, loop: '3', easing: 'linear' })); b.tracks.forEach((x, i) => { assert.strictEqual(x.delay, a.tracks[i].delay + 200); assert.strictEqual(x.iterations, 3); assert.strictEqual(x.easing, 'linear'); }); assert(!b.loops && b.length > a.tracks[0].delay, p.id); }));
t('common: an endless entrance still has a resting pose', () => { const p = data.presets.find(x => x.id === 'logo-pop'); const b = C.buildPlan(p, {}, L, C.sanitizeCommon(data.common, { loop: 'infinite' })); assert(b.loops && isFinite(b.restTime) && b.restTime === 700); });
t('common: hostile values are cleaned', () => { const v = {}; data.common.forEach(q => v[q.key] = 'url(https://evil.example/)'); const pl = C.buildPlan(data.presets[0], {}, L, C.sanitizeCommon(data.common, v)); assert(C.isValidPlan(pl) && !/evil|url/.test(JSON.stringify(pl))); });
t('common: opacity cannot reach zero', () => assert(C.sanitizeCommon(data.common, { opacity: 0 }).opacity >= 0.1));
t('common: stagger only where it does something', () => { assert(C.staggerApplies(data.presets.find(p => p.id === 'logo-color-shift'), L)); assert(!C.staggerApplies(data.presets.find(p => p.id === 'logo-pop'), L)); assert(!C.staggerApplies(data.presets.find(p => p.id === 'logo-assemble'), L)); });
t('phase: time is a negative delay and frozen pauses', () => { const pl = C.buildPlan(data.presets.find(p => p.id === 'logo-breathe'), {}, L); const css = C.planToCss(pl, { time: 300, frozen: true }); assert(/-300ms infinite alternate both paused/.test(css), css); assert(!/paused/.test(C.planToCss(pl, { time: 300 }))); });
t('no shapes: per-shape presets refuse', () => { const none = Object.assign({}, one, { geometry: 0 }); assert.throws(() => C.buildPlan(data.presets.find(p => p.id === 'logo-draw'), {}, none), /NO_SHAPES/); });
t('selector() only ever produces numbered attributes', () => { assert.throws(() => C.selector({ kind: 'part', index: 'x' })); assert.strictEqual(C.selector({ kind: 'geometry', index: 2 }), '[data-omx-geometry="2"]'); });
t('scope: header, setup and keyframes stay inside one class', () => { const pl = C.buildPlan(data.presets.find(p => p.id === 'logo-pop'), {}, L, C.sanitizeCommon(data.common, { x: 10 })); const css = C.planToCss(pl, { scope: 'omx-abcd1234' }); assert(/@keyframes omx-abcd1234-k0/.test(css), css); css.replace(/@keyframes[^{]+\{([^{}]*\{[^{}]*\})*\}/g, '').replace(/@media[^{]+\{([^{}]*\{[^{}]*\})*\}/g, '').split('}').filter(r => r.includes('{')).forEach(r => assert(/^\s*(svg\.omx-abcd1234|\.omx-abcd1234 )/.test(r), r)); });
t('scope: only omx- plus 4 to 16 lower-case letters or digits', () => { assert.strictEqual(C.scopedSelector({ kind: 'stage' }, 'omx-abcd'), 'svg.omx-abcd'); ['omx-ab', 'omx-ABCD', 'x-abcd', 'omx-abcd} *{', 'omx-' + 'a'.repeat(17)].forEach(s => assert.throws(() => C.scopedSelector({ kind: 'stage' }, s), Error, s)); });
t('scope: reduced motion stops and rests', () => { const pl = C.buildPlan(data.presets.find(p => p.id === 'logo-pop'), {}, L); const css = C.planToCss(pl, { scope: 'omx-abcd1234', reducedRest: true }); assert(/@media \(prefers-reduced-motion:reduce\)\{[^@]*animation:none/.test(css), css); });
const vm = require('vm'), fs = require('fs');
const win = { OmxSvgSafety: { SafetyError: function (code) { this.code = code; } }, OmxSvgCompile: C };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../wp-theme/omochix-theme/assets/js/svg-motion/export.js'), 'utf8'), { window: win });
const X = win.OmxSvgExport, code = s => { try { X.parseSettings(s); return 'ok'; } catch (e) { return e.code; } };
const good = { format: 'omochix-svg-motion', schema: 1, svg: '<svg/>', settings: { preset: 'logo-pop', params: {}, common: {} } };
t('settings file: a good file reads back', () => assert.strictEqual(JSON.stringify(X.parseSettings(JSON.stringify(good))), JSON.stringify({ svg: good.svg, settings: good.settings })));
t('settings file: every bad file has its own reason', () => { const w = o => JSON.stringify(Object.assign({}, good, o)); assert.strictEqual(code('x'.repeat(2 * 1024 * 1024 + 1)), 'JSON_TOO_LARGE'); assert.strictEqual(code('{'), 'JSON_INVALID'); assert.strictEqual(code('null'), 'JSON_FORMAT'); assert.strictEqual(code(w({ format: 'x' })), 'JSON_FORMAT'); assert.strictEqual(code(w({ schema: 2 })), 'JSON_SCHEMA'); assert.strictEqual(code(w({ svg: 1 })), 'JSON_INVALID'); assert.strictEqual(code(w({ settings: null })), 'JSON_INVALID'); });
console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
