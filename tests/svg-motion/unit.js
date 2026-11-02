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
t('selector() only ever produces numbered attributes', () => { assert.throws(() => C.selector({ kind: 'part', index: 'x' })); assert.strictEqual(C.selector({ kind: 'geometry', index: 2 }), '[data-omx-geometry="2"]'); });
console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
