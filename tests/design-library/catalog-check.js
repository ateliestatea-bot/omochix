#!/usr/bin/env node
/**
 * Design Library catalog (v2) and static-asset checks (no dependencies, CI-ready).
 *
 *   node tests/design-library/catalog-check.js
 *
 *  1. The shipped catalog validates against catalog.schema.json (small
 *     draft 2020-12 subset validator below) and against the PHP validator.
 *  2. Rules beyond the schema: unique id and slug, id prefix = medium, only
 *     web / video published, asset paths bound to the slug and present,
 *     video fields only (and always) for video works, demo deliverables only
 *     for Web works, a verified state carries a date and environments,
 *     sha256 = demo-hash.js.
 *  3. Bad catalogs (one mutation each) are rejected by BOTH the schema and
 *     the PHP validator (parity), including HTML / control characters in
 *     prompts and steps, unknown URLs, trailing newlines and {} / [] swaps.
 *  4. Static files under assets/design (D0 §4.3.3, B10): only allowed file
 *     types, no HTML / SVG files, ABSPATH guard in every demo.php, demo
 *     sources cannot break out of <style>/<script>, no sandbox widening, no
 *     network / eval / HTML injection / navigation / external URL in demo
 *     code, and every file belongs to a published item.
 */
'use strict';
var fs = require('fs');
var path = require('path');
var os = require('os');
var cp = require('child_process');
var hash = require('../../tools/design-library/demo-hash.js');
var d5 = require('../../tools/design-library/d5-pins.js');

var THEME = hash.THEME;
var ASSETS = path.join(THEME, 'assets/design');
var schema = JSON.parse(fs.readFileSync(path.join(THEME, 'assets/data/design/catalog.schema.json'), 'utf8'));
var catalog = JSON.parse(fs.readFileSync(hash.CATALOG, 'utf8'));
var pass = 0, fail = 0;
function check(name, cond, detail) {
    if (cond) { pass++; console.log('PASS ' + name); }
    else { fail++; console.log('FAIL ' + name + (detail ? '\n  ' + JSON.stringify(detail).slice(0, 800) : '')); }
}

/* ---------- JSON Schema subset ---------- */
function resolve(ref) { return ref.replace(/^#\//, '').split('/').reduce(function (o, k) { return o[k]; }, schema); }
function typeOf(v) { return v === null ? 'null' : Array.isArray(v) ? 'array' : (typeof v === 'number' && Number.isInteger(v)) ? 'integer' : typeof v; }
function validate(s, v, at, errs) {
    if (s.$ref) return validate(resolve(s.$ref), v, at, errs);
    var t = typeOf(v);
    if (s.type) { var ok = s.type === t || (s.type === 'number' && t === 'integer'); if (!ok) { errs.push(at + ': type ' + t + ' != ' + s.type); return errs; } }
    if ('const' in s && JSON.stringify(s.const) !== JSON.stringify(v)) errs.push(at + ': const');
    if (s.enum && s.enum.indexOf(v) === -1) errs.push(at + ': enum ' + JSON.stringify(v));
    if (typeof v === 'string') {
        var len = Array.from(v).length;
        if (s.minLength !== undefined && len < s.minLength) errs.push(at + ': minLength');
        if (s.maxLength !== undefined && len > s.maxLength) errs.push(at + ': maxLength');
        if (s.pattern && !new RegExp(s.pattern, 'u').test(v)) errs.push(at + ': pattern');
    }
    if (typeof v === 'number') {
        if (s.minimum !== undefined && v < s.minimum) errs.push(at + ': minimum');
        if (s.maximum !== undefined && v > s.maximum) errs.push(at + ': maximum');
    }
    if (t === 'array') {
        if (s.minItems !== undefined && v.length < s.minItems) errs.push(at + ': minItems');
        if (s.maxItems !== undefined && v.length > s.maxItems) errs.push(at + ': maxItems');
        if (s.uniqueItems && new Set(v.map(function (x) { return JSON.stringify(x); })).size !== v.length) errs.push(at + ': uniqueItems');
        if (s.items) v.forEach(function (x, i) { validate(s.items, x, at + '[' + i + ']', errs); });
    }
    if (t === 'object') {
        var keys = Object.keys(v);
        if (s.maxProperties !== undefined && keys.length > s.maxProperties) errs.push(at + ': maxProperties');
        (s.required || []).forEach(function (k) { if (!(k in v)) errs.push(at + ': missing ' + k); });
        keys.forEach(function (k) {
            if (s.propertyNames) validate(s.propertyNames, k, at + '{' + k + '}', errs);
            if (s.properties && s.properties[k]) validate(s.properties[k], v[k], at + '.' + k, errs);
            else if (s.additionalProperties === false) errs.push(at + ': unknown key ' + k);
            else if (s.additionalProperties && typeof s.additionalProperties === 'object') validate(s.additionalProperties, v[k], at + '.' + k, errs);
        });
    }
    if (s.allOf) s.allOf.forEach(function (x) { validate(x, v, at, errs); });
    if (s.anyOf && !s.anyOf.some(function (x) { return validate(x, v, at, []).length === 0; })) errs.push(at + ': anyOf');
    if (s.oneOf) { var n = s.oneOf.filter(function (x) { return validate(x, v, at, []).length === 0; }).length; if (n !== 1) errs.push(at + ': oneOf matched ' + n); }
    if (s.not && validate(s.not, v, at, []).length === 0) errs.push(at + ': not');
    return errs;
}
function schemaErrors(data) { return validate(schema, data, '$', []); }

/* ---------- rules beyond the schema (same as the PHP validator) ---------- */
var PREVIEW = { web: 'browser', video: 'video', app: 'device', component: 'stage' };
function ruleErrors(data) {
    var errs = [], ids = {}, slugs = {};
    (data.items || []).forEach(function (it, i) {
        var at = 'items[' + i + ']';
        if (ids[it.id]) errs.push(at + ': duplicate id'); ids[it.id] = 1;
        if (slugs[it.slug]) errs.push(at + ': duplicate slug'); slugs[it.slug] = 1;
        if (typeof it.id === 'string' && it.id.indexOf('dl-' + it.medium + '-') !== 0) errs.push(at + ': id prefix');
        if (it.status === 'published' && ['web', 'video'].indexOf(it.medium) === -1) errs.push(at + ': only web and video may be published');
        var exp = { poster: 'posters/' + it.slug + '.avif', posterFallback: 'posters/' + it.slug + '.webp', loop: 'loops/' + it.slug + '.webm', loopFallback: 'loops/' + it.slug + '.mp4' };
        Object.keys(exp).forEach(function (k) {
            if (!(k in it)) return;
            if (it[k] !== exp[k]) errs.push(at + ': ' + k + ' must be ' + exp[k]);
            else if (!fs.existsSync(path.join(ASSETS, it[k]))) errs.push(at + ': missing file ' + it[k]);
        });
        if ('loopFallback' in it && !('loop' in it)) errs.push(at + ': loopFallback without loop');
        if (it.preview && it.preview.type !== PREVIEW[it.medium]) errs.push(at + ': preview.type for medium');
        if (it.medium === 'video') {
            var v = it.video;
            if (!v) errs.push(at + ': video required');
            else {
                if (v.mp4 !== 'videos/' + it.slug + '.mp4' || !fs.existsSync(path.join(ASSETS, v.mp4))) errs.push(at + ': video.mp4');
                if ('webm' in v && (v.webm !== 'videos/' + it.slug + '.webm' || !fs.existsSync(path.join(ASSETS, v.webm)))) errs.push(at + ': video.webm');
                var o = v.width > v.height ? 'landscape' : v.width < v.height ? 'portrait' : 'square';
                if (!it.preview || it.preview.orientation !== o) errs.push(at + ': orientation');
            }
            if ('deliverables' in it) errs.push(at + ': deliverables only for web');
        } else {
            if ('video' in it) errs.push(at + ': video only for medium video');
            var entry = it.preview && it.preview.entry;
            if (entry !== 'demos/' + it.slug + '/' || !fs.existsSync(path.join(ASSETS, entry || '', 'demo.php'))) errs.push(at + ': preview.entry');
            if (it.medium === 'web' && (!it.deliverables || !it.deliverables.html || it.deliverables.html.file !== entry)) errs.push(at + ': deliverables.html.file');
        }
        if ('access' in it && it.access && typeof it.access === 'object') {
            var MODES = { preview: ['disabled'], full: ['disabled', 'free', 'paid'] };
            if (!Object.prototype.hasOwnProperty.call(MODES, it.access.scope) || MODES[it.access.scope].indexOf(it.access.distribution) === -1) errs.push(at + ': access pair (preview allows only disabled)');
        }
        var ver = it.verification;
        if (ver && ver.state !== 'unverified' && (ver.checked === null || !ver.environments || ver.environments.length === 0)) errs.push(at + ': verified state needs a date and environments');
    });
    return errs;
}
function phpResult(data, rawText) {
    var f = path.join(os.tmpdir(), 'omx-dl-cat-' + process.pid + '.json');
    fs.writeFileSync(f, rawText !== undefined ? rawText : JSON.stringify(data));
    var r = cp.spawnSync('php', [path.join(__dirname, 'validate-cli.php'), f], { encoding: 'utf8' });
    fs.unlinkSync(f);
    return JSON.parse(r.stdout);
}

/* ---------- 1-2. shipped catalog ---------- */
var se = schemaErrors(catalog);
check('schema: shipped catalog (v2) is valid', se.length === 0, se);
var re = ruleErrors(catalog);
check('rules: ids, slugs, mediums, assets, videos, entries, verification', re.length === 0, re);
var php = phpResult(catalog);
check('php: all ' + catalog.items.length + ' items accepted, no errors', php.items.length === catalog.items.length && php.errors.length === 0, php);
catalog.items.filter(function (i) { return i.medium === 'web'; }).forEach(function (it) {
    check('sha256: ' + it.slug + ' matches its demo sources', hash.demoHash(it.slug) === it.deliverables.html.sha256);
});
var pub = function (m) { return catalog.items.filter(function (i) { return i.medium === m && i.status === 'published'; }).length; };
check('catalog: 9 published Web works (4 from D1, 2 from D2-B, 2 from D2-D, 1 from D2-E) and 3 published video works (1 from D2-D)', pub('web') === 9 && pub('video') === 3);
check('catalog: the D2-D works keep their ids and slugs', ['dl-video-0003:brand-typography', 'dl-web-0007:ai-workspace', 'dl-web-0008:micro-interactions', 'dl-web-0009:interactive-story'].every(function (p) { var a = p.split(':'); return catalog.items.some(function (i) { return i.id === a[0] && i.slug === a[1]; }); }));
check('catalog: the D2-B works keep their ids and slugs', ['dl-web-0005:dark-luxury', 'dl-web-0006:aurora-gradient'].every(function (p) { var a = p.split(':'); return catalog.items.some(function (i) { return i.id === a[0] && i.slug === a[1]; }); }));
var evidence = d5.loadEvidence();
var prodErrs = d5.productionErrors(catalog, evidence);
check('G12 rule (' + d5.RULE + '): no work claims verified-production, the evidence file takes no entries, no staging fixture ships', prodErrs.length === 0, prodErrs);
check('G12: all 12 works stay verified-local', catalog.items.length === 12 && catalog.items.every(function (i) { return i.verification.state === 'verified-local'; }));
check('G12: the evidence file is exactly {version 1, rule g12-no-promotion, items []} (no staging verdicts)', evidence.version === 1 && evidence.rule === 'g12-no-promotion' && Array.isArray(evidence.items) && evidence.items.length === 0 && !/"p1"|"p2"|staging"\s*:/.test(JSON.stringify(evidence)));
var hookErrs = d5.hookErrors();
check('shipped code: only inc/design-library.php applies the catalog / asset filters, nothing registers them', hookErrs.length === 0, hookErrs);
check('pins: functions.php (it loads the Design Library) is pinned for every work', d5.COMMON.indexOf('functions.php') === 0 && catalog.items.every(function (i) { return d5.itemFiles(i).indexOf('functions.php') !== -1; }));
check('catalog: the 4 D1 works keep their ids and slugs', ['dl-web-0001:liquid-glass', 'dl-web-0002:minimal-editorial', 'dl-web-0003:3d-interactive', 'dl-web-0004:bento-saas'].every(function (p) { var a = p.split(':'); return catalog.items.some(function (i) { return i.id === a[0] && i.slug === a[1]; }); }));
check('catalog: no work maps to a Motion Studio template (no verified template exists)', catalog.items.every(function (i) { return !i.motionStudio; }));
check('catalog: every work is original (credits.source / license = original)', catalog.items.every(function (i) { return i.credits.source === 'original' && i.credits.license === 'original'; }));

/* ---------- 3. bad catalogs: schema+rules and PHP both reject ---------- */
function clone(x) { return JSON.parse(JSON.stringify(x)); }
var W = 0, V = catalog.items.findIndex(function (i) { return i.medium === 'video'; }); // index of a Web and a video work
var MUT = [
    ['unknown item key', function (d) { d.items[W].extra = 1; }],
    ['unknown root key', function (d) { d.extra = 1; }, 'root'],
    ['old catalog version 1', function (d) { d.version = 1; }, 'root'],
    ['bad slug', function (d) { d.items[W].slug = 'Bad_Slug'; }],
    ['slug with trailing newline', function (d) { d.items[W].slug = 'liquid-glass\n'; }],
    ['id with trailing newline (duplicate-id bypass)', function (d) { d.items[1].id = d.items[W].id + '\n'; }],
    ['duplicate slug', function (d) { d.items[1].slug = d.items[W].slug; }, 'rules'],
    ['duplicate id', function (d) { d.items[1].id = d.items[W].id; }, 'rules'],
    ['id prefix not medium', function (d) { d.items[V].id = 'dl-web-0099'; }, 'rules'],
    ['unknown medium', function (d) { d.items[W].medium = 'game'; }],
    ['published app', function (d) { var a = d.items[W]; a.medium = 'app'; a.id = 'dl-app-0001'; delete a.deliverables; a.preview = { type: 'device', entry: a.preview.entry, devices: ['iphone'], screens: [], transitions: [], theme: 'light' }; }, 'rules'],
    ['unknown use case', function (d) { d.items[W].use_cases = ['casino']; }],
    ['unknown motion type', function (d) { d.items[W].motion_types = ['teleport']; }],
    ['unknown technology', function (d) { d.items[W].technologies = ['flash']; }],
    ['duplicate technology', function (d) { d.items[W].technologies = ['css', 'css']; }],
    ['html in title', function (d) { d.items[W].title = '<b>x</b>'; }],
    ['html in prompt', function (d) { d.items[W].prompt.text = 'ok <script>alert(1)</script>'; }],
    ['control character in prompt', function (d) { d.items[W].prompt.text = 'ok \u0007 bell'; }],
    ['html in a production step', function (d) { d.items[W].steps[0].body = '<img src=x onerror=alert(1)>'; }],
    ['too many steps', function (d) { var s = d.items[W].steps[0]; d.items[W].steps = Array(11).fill(s); }],
    ['bad palette color', function (d) { d.items[W].palette[0].value = 'red'; }],
    ['poster of another item', function (d) { d.items[W].poster = 'posters/bento-saas.avif'; }, 'rules'],
    ['poster traversal', function (d) { d.items[W].poster = 'posters/../../x.avif'; }],
    ['loopFallback without loop', function (d) { delete d.items[W].loop; }, 'rules'],
    ['entry of another item', function (d) { d.items[W].preview.entry = d.items[W].deliverables.html.file = 'demos/bento-saas/'; }, 'rules'],
    ['entry with traversal', function (d) { d.items[W].preview.entry = d.items[W].deliverables.html.file = 'demos/../runtime/'; }],
    ['preview type for medium', function (d) { d.items[W].preview.type = 'video'; }],
    ['unknown preview key (sandbox widening)', function (d) { d.items[W].preview.allowSameOrigin = true; }],
    ['bad sha256', function (d) { d.items[W].deliverables.html.sha256 = 'abc'; }],
    ['showCode not boolean', function (d) { d.items[W].deliverables.showCode = 'yes'; }],
    ['video work without video', function (d) { delete d.items[V].video; }, 'rules'],
    ['video file of another work', function (d) { d.items[V].video.mp4 = 'videos/data-chart-explainer.mp4'; }, 'rules'],
    ['video path with a URL', function (d) { d.items[V].video.mp4 = 'https://example.com/x.mp4'; }],
    ['video orientation mismatch', function (d) { d.items[V].preview.orientation = 'landscape'; }, 'rules'],
    ['video fields on a Web work', function (d) { d.items[W].video = clone(d.items[V].video); }, 'rules'],
    ['demo code on a video work', function (d) { d.items[V].deliverables = clone(d.items[W].deliverables); }, 'rules'],
    ['third-party license', function (d) { d.items[W].credits.license = 'cc-by'; }],
    ['third-party source', function (d) { d.items[W].credits.source = 'stock'; }],
    ['unknown material license', function (d) { d.items[W].materials[0].license = 'unknown'; }],
    ['verified without a date', function (d) { d.items[W].verification.checked = null; }, 'rules'],
    ['verified without environments', function (d) { d.items[W].verification.environments = []; }, 'rules'],
    ['unknown verification state', function (d) { d.items[W].verification.state = 'trusted'; }],
    ['unknown environment', function (d) { d.items[W].verification.environments = ['ie6']; }],
    ['learn link outside /learn/', function (d) { d.items[W].related.learn = ['wp-admin/options'], 0; }],
    ['learn link as full URL', function (d) { d.items[W].related.learn = ['https://example.com/learn/x']; }],
    ['motionStudio with unknown key', function (d) { d.items[W].motionStudio = { templateId: 'rag-intro', status: 'unverified', editorUrl: 'https://example.com' }; }],
    ['motionStudio template id with a path', function (d) { d.items[W].motionStudio = { templateId: '../x', status: 'unverified' }; }],
    ['motionStudio settings with HTML', function (d) { d.items[W].motionStudio = { templateId: 'rag-intro', status: 'unverified', settings: { title: '<script>' } }; }],
    ['motionStudio settings with an object value', function (d) { d.items[W].motionStudio = { templateId: 'rag-intro', status: 'unverified', settings: { code: { run: 1 } } }; }],
    ['unknown perf key', function (d) { d.items[W].audit.perf = { bogus: 1 }; }],
    ['negative perf value', function (d) { d.items[W].audit.perf = { lcpMs: -1 }; }],
    ['html in fictionalBrand', function (d) { d.items[W].fictionalBrand = '<b>x</b>'; }],
    ['empty fictionalBrand', function (d) { d.items[W].fictionalBrand = ''; }],
    ['fictionalBrand over 40 characters', function (d) { d.items[W].fictionalBrand = 'x'.repeat(41); }],
    ['fictionalBrand not a string', function (d) { d.items[W].fictionalBrand = 5; }],
    ['control character in fictionalBrand', function (d) { d.items[W].fictionalBrand = 'IKIMA\n'; }],
    ['access with an unknown scope', function (d) { d.items[W].access = { scope: 'public', distribution: 'disabled' }; }],
    ['access with scope private (PRIVATE is status draft)', function (d) { d.items[W].access = { scope: 'private', distribution: 'disabled' }; }],
    ['access with an unknown distribution', function (d) { d.items[W].access = { scope: 'full', distribution: 'open' }; }],
    ['access with upper-case values', function (d) { d.items[W].access = { scope: 'FULL', distribution: 'FREE' }; }],
    ['access preview + free (preview hands out nothing)', function (d) { d.items[W].access = { scope: 'preview', distribution: 'free' }; }],
    ['access preview + paid', function (d) { d.items[V].access = { scope: 'preview', distribution: 'paid' }; }],
    ['access without distribution', function (d) { d.items[W].access = { scope: 'full' }; }],
    ['access with an unknown key', function (d) { d.items[W].access = { scope: 'full', distribution: 'free', price: 100 }; }],
    ['access scope not a string', function (d) { d.items[W].access = { scope: true, distribution: 'free' }; }],
    ['access as a string', function (d) { d.items[W].access = 'full'; }],
    ['access as a list', function (d) { d.items[W].access = ['full', 'free']; }],
    ['access with a prototype-key scope', function (d) { d.items[W].access = { scope: 'constructor', distribution: 'free' }; }]
];
MUT.forEach(function (m) {
    var d = clone(catalog); m[1](d);
    var bySchema = schemaErrors(d).length + ruleErrors(d).length > 0;
    var p = phpResult(d);
    var phpRejects = m[2] === 'root' ? p.items.length === 0 && p.errors.length > 0 : p.errors.length > 0 && p.items.length < catalog.items.length;
    check('reject (schema+rules ' + bySchema + ', php ' + phpRejects + '): ' + m[0], bySchema && phpRejects, { php: p.errors.slice(0, 2) });
});
// {} vs [] cannot be built with JS objects alone: edit the JSON text.
[['params-like list where an object belongs (audit.perf: [])', '"perf": {}', '"perf": []'], ['object where a list belongs (materials: {})', '"materials": [', '"materials": {"x": 1}, "unused": [']].forEach(function (m) {
    var txt = JSON.stringify(catalog, null, 1);
    var i = txt.indexOf(m[1]);
    if (m[0].indexOf('materials') !== -1) { txt = txt.replace(/"materials": \[[^\]]*\]/, '"materials": {}'); } else { txt = txt.slice(0, i) + m[2] + txt.slice(i + m[1].length); }
    var p = phpResult(null, txt);
    check('reject {} / [] swap in PHP: ' + m[0], p.errors.length > 0 && p.items.length < catalog.items.length, p.errors.slice(0, 1));
});
// G14: the valid access pairs are accepted by the schema, the rules and PHP, on a Web and a video work.
[['preview', 'disabled'], ['full', 'disabled'], ['full', 'free'], ['full', 'paid']].forEach(function (pair) {
    var d = clone(catalog); d.items[W].access = { scope: pair[0], distribution: pair[1] }; d.items[V].access = { scope: pair[0], distribution: pair[1] };
    var errs = schemaErrors(d).concat(ruleErrors(d)); var p = phpResult(d);
    check('accept access ' + pair.join(' + ') + ' (schema, rules and PHP)', errs.length === 0 && p.errors.length === 0 && p.items.length === catalog.items.length, [errs.slice(0, 2), p.errors.slice(0, 2)]);
});
check('G14: the shipped catalog sets no access (all 12 works are preview + disabled by default)', catalog.items.every(function (i) { return !('access' in i); }));
var dup = clone(catalog); dup.items[1].slug = dup.items[0].slug;
var pd = phpResult(dup);
check('php: duplicate slug keeps the first item, drops the second', pd.items.indexOf(catalog.items[0].slug) !== -1 && pd.items.indexOf(catalog.items[1].slug) === -1);

/* ---------- 3b. fictionalBrand: only names the works' own content shows ---------- */
var BRANDS = { 'liquid-glass': 'Quillmere', 'minimal-editorial': 'IKIMA', '3d-interactive': 'WAKUREI-01', 'bento-saas': 'Grivio', 'dark-luxury': 'KAGEMURO',
    'aurora-gradient': 'NAGIWA', 'ai-workspace': 'TOIMORI', 'micro-interactions': 'TEMOCHO', 'interactive-story': '楮雪舎（KOZOYUKI）',
    'logo-reveal-short': 'TSUKIAMI', 'brand-typography': 'HONYOI' }; // data-chart-explainer shows no brand name: none is invented
check('fictionalBrand: exactly the 11 works whose content shows a brand name carry it, with that name', catalog.items.every(function (i) { return (BRANDS[i.slug] || null) === (i.fictionalBrand || null); }), catalog.items.map(function (i) { return i.slug + '=' + (i.fictionalBrand || '-'); }));
catalog.items.filter(function (i) { return i.medium === 'web' && i.fictionalBrand; }).forEach(function (i) {
    var src = fs.readFileSync(path.join(ASSETS, 'demos', i.slug, 'demo.php'), 'utf8');
    var parts = i.fictionalBrand.split(/[（）]/).filter(Boolean);
    check('fictionalBrand: ' + i.slug + ' demo shows "' + i.fictionalBrand + '"', parts.every(function (p) { return src.indexOf(p) !== -1; }));
});
check('fictionalBrand: HONYOI is the brand in brand-typography\'s own text', JSON.stringify(catalog.items.filter(function (i) { return i.slug === 'brand-typography'; })[0].customization).indexOf('HONYOI') !== -1);

/* ---------- 3c. G12 allows no promotion: faults made on purpose, each with its expected cause ---------- */
(function () {
    var DATE = '2099-01-02', G = 'not allowed in G12';
    var real = {}; var shaOf = function (f) { if (!(f in real)) real[f] = d5.fileSha(f); return real[f]; };
    var clone2 = function (x) { return JSON.parse(JSON.stringify(x)); };
    function promote(cat, slugs) {
        var c = clone2(cat);
        c.items.forEach(function (it) {
            if (slugs && slugs.indexOf(it.slug) === -1) return;
            it.verification.state = 'verified-production'; it.verification.checked = DATE;
            it.verification.limitations = [d5.PENDING_LINE].concat(it.verification.limitations).slice(0, 8);
        });
        return c;
    }
    /* The cancelled staging-era evidence shape, complete and correctly pinned: it must never promote anything. */
    function oldEntry(c, slug, over) {
        var it = c.items.filter(function (i) { return i.slug === slug; })[0];
        return Object.assign({ id: it.id, slug: it.slug, checked: DATE, staging: { p1: 'PASS', p2: 'PASS' }, promotion: { p3: 'G12-P3-TEST' },
            production: { p4: 'PASS' }, item_sha: d5.itemSha(it), files: d5.pinsFor(it, shaOf).files }, over || {});
    }
    function expect(name, cat, ev, causes, sh) {
        var e = d5.productionErrors(cat, ev, sh || shaOf);
        var missing = causes.filter(function (c) { return !e.some(function (x) { return x.indexOf(c) !== -1; }); });
        check('d5 G12 rule rejects (' + causes.join(' + ') + '): ' + name, e.length > 0 && missing.length === 0, { missing: missing, got: e.slice(0, 4) });
    }
    var web = 'liquid-glass', vid = 'logo-reveal-short';
    var cand = promote(catalog, [web, vid]);
    var R = { version: 1, rule: d5.RULE };
    var good = Object.assign({}, R, { items: [oldEntry(cand, web), oldEntry(cand, vid)] });
    var sub = function (f, bad) { return function (x) { return x === f ? bad : shaOf(x); }; };

    /* the old rule can no longer promote, whatever the evidence says */
    expect('complete staging-era evidence (P1/P2/P3/P4 PASS, pins correct) for a Web and a video work', cand, { version: 1, items: good.items }, [G, 'must be {version: 1, rule']);
    expect('the same evidence under the G12 rule', cand, good, [G, 'accepts no evidence entries']);
    var all = promote(catalog);
    expect('all 12 works promoted with complete staging-era evidence', all, Object.assign({}, R, { items: all.items.map(function (i) { return oldEntry(all, i.slug); }) }), [G, 'accepts no evidence entries']);
    var p4 = clone2(cand); p4.items.forEach(function (i) { i.verification.limitations = i.verification.limitations.filter(function (l) { return l !== d5.PENDING_LINE; }); });
    expect('P4 marked PASS and the pending line removed', p4, good, [G, 'still pending']);
    expect('promotion with an empty evidence file', cand, Object.assign({}, R, { items: [] }), [G, 'no evidence entry']);
    expect('evidence only for another work', cand, Object.assign({}, R, { items: [oldEntry(cand, web)] }), [G, 'no evidence entry', 'accepts no evidence entries']);
    expect('an evidence file without version and rule', cand, { items: [] }, ['must be {version: 1, rule']);
    expect('an evidence file with unknown keys', catalog, Object.assign({}, R, { items: [], staging: { p1: 'PASS' } }), ['unknown keys']);
    expect('an evidence file with an entry while nothing is promoted', catalog, Object.assign({}, R, { items: [oldEntry(catalog, web)] }), ['accepts no evidence entries']);
    var ev;
    ev = clone2(good); ev.items[0].slug = 'minimal-editorial'; expect('evidence slug of another work', cand, ev, [G, 'evidence slug differs']);
    var c2 = clone2(cand); c2.items[0].summary += ' 変更'; expect('catalog entry changed after pinning', c2, good, [G, 'item_sha']);
    c2 = clone2(cand); c2.items.filter(function (i) { return i.slug === web; })[0].deliverables.html.sha256 = '0'.repeat(64); expect('demo hash in the catalog changed', c2, good, [G, 'item_sha']);
    expect('demo endpoint (CSP) changed', cand, good, [G, 'inc/design-library-demo.php changed after it was pinned'], sub('inc/design-library-demo.php', 'f'.repeat(64)));
    expect('publish gate changed', cand, good, [G, 'inc/design-library.php changed after it was pinned'], sub('inc/design-library.php', 'f'.repeat(64)));
    expect('functions.php (loads the Design Library) changed', cand, good, [G, 'functions.php changed after it was pinned'], sub('functions.php', 'f'.repeat(64)));
    expect('catalog lock (.htaccess) changed', cand, good, [G, 'assets/data/design/.htaccess changed after it was pinned'], sub('assets/data/design/.htaccess', 'f'.repeat(64)));
    expect('a demo source changed', cand, good, [G, 'assets/design/demos/' + web + '/', 'changed after it was pinned'], function (f) { return f.indexOf('assets/design/demos/' + web + '/') === 0 ? 'f'.repeat(64) : shaOf(f); });
    expect('a video changed', cand, good, [G, 'videos/logo-reveal-short.mp4 changed after it was pinned'], sub('assets/design/videos/logo-reveal-short.mp4', 'f'.repeat(64)));
    ev = clone2(good); delete ev.items[0].files['inc/design-library-demo.php']; expect('a pinned file left out', cand, ev, [G, 'pins are not exactly']);
    ev = clone2(good); ev.items[0].files['404.php'] = shaOf('404.php'); expect('an extra file in the pins', cand, ev, [G, 'pins are not exactly']);
    /* L2: missing files and malformed pins */
    ev = clone2(good); ev.items[0].files['assets/data/design/.htaccess'] = null;
    expect('pin null while the file is missing from the tree', cand, ev, [G, 'assets/data/design/.htaccess is not a 64-character hex'], sub('assets/data/design/.htaccess', null));
    ev = clone2(good); ev.items[0].files['functions.php'] = ''; expect('empty pin', cand, ev, [G, 'functions.php is not a 64-character hex']);
    ev = clone2(good); ev.items[0].files['functions.php'] = String(shaOf('functions.php')).toUpperCase(); expect('upper-case pin', cand, ev, [G, 'functions.php is not a 64-character hex']);
    ev = clone2(good); ev.items[0].files['functions.php'] = String(shaOf('functions.php')).slice(1); expect('63-character pin', cand, ev, [G, 'functions.php is not a 64-character hex']);
    ev = clone2(good); ev.items[0].files['functions.php'] = shaOf('functions.php') + '\n'; expect('pin with a trailing newline', cand, ev, [G, 'functions.php is not a 64-character hex']);
    ev = clone2(good); ev.items[0].files['functions.php'] = 1; expect('numeric pin', cand, ev, [G, 'functions.php is not a 64-character hex']);
    expect('well-formed pin, but the file is missing from the tree', cand, good, [G, 'functions.php is missing from the tree'], sub('functions.php', null));
    c2 = clone2(cand); c2.items.forEach(function (i) { i.verification.limitations = i.verification.limitations.filter(function (l) { return l !== d5.PENDING_LINE; }); });
    expect('pending line removed', c2, good, [G, 'still pending']);
    /* L4: staging-fixture marks in any spelling, on a catalog that promotes nothing */
    [d5.FIXTURE_MARK, d5.FIXTURE_MARK + ' ', 'staging fixture: local only', 'ＳＴＡＧＩＮＧ　ＦＩＸＴＵＲＥ', 'Staging-Fixture', 'STAGING\u200BFIXTURE', 'staging_fixture', 'STAGING   FIXTURE (test)', 'StagingFixture', 'staging test fixture', 'Staging–Fixture', 'STAGING/FIXTURE', 'stag­ing fixture', 'stagíng fixture', 'STAGING‑FIXTURE', '(staging)(fixture)'].forEach(function (mark, n) {
        var c = clone2(catalog); c.items[3].verification.limitations = c.items[3].verification.limitations.concat([mark]).slice(-8);
        expect('staging-fixture mark, spelling ' + (n + 1), c, Object.assign({}, R, { items: [] }), ['staging fixture line']);
    });
    /* positive controls: the checks are not always failing */
    var pinOk = catalog.items.map(function (i) { return d5.pinErrors(i, d5.pinsFor(i, shaOf).files, shaOf); }).filter(function (e) { return e.length; });
    check('d5 control: fresh pins of all 12 works pass pinErrors (so its failures above are real)', pinOk.length === 0, pinOk);
    check('d5 control: no current limitation reads as a staging-fixture mark', catalog.items.every(function (i) { return !i.verification.limitations.some(d5.isFixtureLine); }));
    check('d5 control: the shipped catalog with the G12 evidence file has no error', d5.productionErrors(catalog, Object.assign({}, R, { items: [] }), shaOf).length === 0);
    check('d5 promoted catalogs stay schema- and PHP-valid (the D5 rule, not the schema, is the gate)', schemaErrors(all).length === 0 && phpResult(all).errors.length === 0);
    /* L1: anything in shipped code that could change a catalog entry at runtime */
    var HOME = 'wp-theme/omochix-theme/inc/design-library.php';
    var legit = d5.HOOKS.map(function (h) { return "    $x = apply_filters('" + h + "', $x);"; }).join('\n');
    var home = {}; home[HOME] = legit;
    check('d5 control: the 4 apply_filters() calls in inc/design-library.php are allowed', d5.hookErrors(home).length === 0, d5.hookErrors(home));
    var read = {}; read['wp-plugin/omochix-core/includes/industry-hub.php'] = "foreach ((array) ($GLOBALS['wp_filter'] ?? array()) as $hook => $f) {}\nif (isset($wp_filter[$h]) && $wp_filter[$h] !== null && $n <= 2 && $m >= 1 && $k != 3) {}\n$x = $wp_filter[$h];"; read[HOME] = legit;
    check('d5 control: reading $GLOBALS[\'wp_filter\'] (Core Industry Hub does) is allowed', d5.hookErrors(read).length === 0, d5.hookErrors(read));
    [['wp-theme/omochix-theme/functions.php', "add_filter('omochix_design_catalog_data', function ($d) { return $d; });", 'filter name'],
     ['wp-theme/omochix-theme/inc/other.php', 'add_filter( "omochix_design_catalog_raw" , "f" );', 'filter name'],
     ['wp-plugin/omochix-core/includes/x.php', "apply_filters('omochix_design_asset_file', $f);", 'filter name'],
     ['wp-plugin/omochix-core/omochix-core.php', "remove_filter('omochix_design_catalog_data', 'x');", 'filter name'],
     ['wp-theme/omochix-theme/functions.php', "ADD_FILTER('omochix_design_catalog_data', 'x');", 'filter name'],
     ['wp-theme/omochix-theme/functions.php', "Add_Filter('OMOCHIX_DESIGN_CATALOG_DATA', 'x');", 'filter name'],
     ['wp-theme/omochix-theme/functions.php', "add_filter(/* x */ 'omochix_design_catalog_data', 'x');", 'filter name'],
     ['wp-theme/omochix-theme/functions.php', "add_filter(hook_name: 'omochix_design_catalog_data', callback: 'x');", 'filter name'],
     ['wp-theme/omochix-theme/functions.php', "call_user_func('add_filter', 'omochix_design_catalog_raw', 'x');", 'filter name'],
     ['wp-theme/omochix-theme/functions.php', "$GLOBALS['wp_filter']['omochix_design_x'] = null;", 'writes the WordPress filter table'],
     ['wp-plugin/omochix-core/includes/x.php', "global $wp_filter; $wp_filter['x'][10] = array();", 'writes the WordPress filter table'],
     ['wp-theme/omochix-theme/functions.php', "unset($GLOBALS['wp_filter']['x']);", 'writes the WordPress filter table'],
     ['wp-theme/omochix-theme/functions.php', "$GLOBALS['omochix_design_catalog'] = array();", 'catalog cache global'],
     [HOME, "add_filter('omochix_design_catalog_file', 'x');", 'filter name']].forEach(function (c) {
        var o = {}; o[HOME] = legit; o[c[0]] = (c[0] === HOME ? legit + '\n' : '') + c[1];
        var e = d5.hookErrors(o);
        check('d5 hook scan rejects (' + c[2] + '): ' + c[1].slice(0, 48) + ' in ' + c[0].split('/').pop(), e.length > 0 && e.every(function (x) { return x.indexOf(c[2]) !== -1 || x.indexOf('expected exactly') !== -1; }) && e.some(function (x) { return x.indexOf(c[2]) !== -1; }), e);
    });
    var missing = {}; missing[HOME] = legit.split('\n').slice(1).join('\n');
    check('d5 hook scan rejects: inc/design-library.php without one of its 4 apply_filters() calls', d5.hookErrors(missing).some(function (x) { return x.indexOf('expected exactly one apply_filters() call for omochix_design_catalog_data, found 0') !== -1; }));
    /* R2: the home file must hold exactly one apply_filters() per name, on lines that register nothing */
    var mix = {}; mix[HOME] = legit.split('\n').slice(1).join('\n') + "\n    $x = apply_filters('omochix_design_catalog_raw', $x); add_filter('omochix_design_catalog_data', 'x');";
    check('d5 hook scan rejects: a home-file line that applies one filter and registers another (count kept at 4)', d5.hookErrors(mix).some(function (x) { return x.indexOf('found 0') !== -1; }) && d5.hookErrors(mix).some(function (x) { return x.indexOf('filter name omochix_design_catalog_data outside') !== -1; }));
    var wrap = {}; wrap[HOME] = legit.split('\n').slice(1).join('\n') + "\n    add_filter('init', function () { return apply_filters('omochix_design_catalog_data', array()); });";
    check('d5 hook scan rejects: a home-file apply_filters() inside a callback that registers on another hook', d5.hookErrors(wrap).some(function (x) { return x.indexOf('filter name omochix_design_catalog_data outside') !== -1; }), d5.hookErrors(wrap));
    var dup = {}; dup[HOME] = legit + "\n    $y = apply_filters('omochix_design_catalog_data', $y);";
    check('d5 hook scan rejects: a second apply_filters() for the same name in the home file', d5.hookErrors(dup).some(function (x) { return x.indexOf('for omochix_design_catalog_data, found 2') !== -1; }));
    /* R1: indirect writes to the filter table and the bare cache variable */
    [["$wp_filter[$a['b']][10][] = 1;", 'writes the WordPress filter table'],
     ["$wp_filter[$h]->callbacks[10]['x'] = $cb;", 'writes the WordPress filter table'],
     ["$GLOBALS['wp_filter'][$h]->add_filter($h, $cb, 10, 1);", 'writes the WordPress filter table'],
     ["$wp_filter[$h] ??= new WP_Hook();", 'writes the WordPress filter table'],
     ["$t = &$GLOBALS['wp_filter']; $t[$h] = 1;", 'writes the WordPress filter table'],
     ["global $omochix_design_catalog; $omochix_design_catalog = array();", 'catalog cache global']].forEach(function (c) {
        var o = {}; o[HOME] = legit; o['wp-theme/omochix-theme/functions.php'] = c[0];
        check('d5 hook scan rejects (' + c[1] + '): ' + c[0].slice(0, 44), d5.hookErrors(o).some(function (x) { return x.indexOf(c[1]) !== -1; }), d5.hookErrors(o));
    });
    /* N4: the real missing-file path */
    check('d5 fileSha: a file that does not exist has no hash (null)', d5.fileSha('no/such/dir/file.php') === null);
    check('d5 fileSha: a folder has no hash (null)', d5.fileSha('inc') === null);
    /* N3: the evidence note must be text */
    expect('an evidence note that is not text', catalog, { version: 1, rule: d5.RULE, note: { staging: { p1: 'PASS' } }, items: [] }, ['note must be a string']);
    c2 = clone2(catalog); c2.items[2].verification.limitations = 'staging fixture';
    expect('limitations that are not a list', c2, Object.assign({}, R, { items: [] }), ['limitations must be a list', 'staging fixture line']);
    c2 = clone2(catalog); c2.items[2].credits.notes = 'STAGING FIXTURE copy';
    expect('a staging-fixture mark outside limitations', c2, Object.assign({}, R, { items: [] }), ['staging fixture line']);
})();

/* ---------- 4. static files ---------- */
function walk(dir) { return fs.readdirSync(dir, { withFileTypes: true }).reduce(function (a, e) { var f = path.join(dir, e.name); return a.concat(e.isDirectory() ? walk(f) : [f]); }, []); }
var files = walk(ASSETS).map(function (f) { return path.relative(ASSETS, f).split(path.sep).join('/'); });
var allowed = /^(posters\/[a-z0-9-]+\.(avif|webp)|loops\/[a-z0-9-]+\.(webm|mp4)|videos\/[a-z0-9-]+\.(webm|mp4)|demos\/[a-z0-9-]+\/demo\.(php|css|js)|runtime\/demo-runtime\.js|demos\/\.htaccess)$/;
check('static: only posters, loops, videos, demo sources, the runtime and the demo-sources .htaccess (G14) under assets/design', files.every(function (f) { return allowed.test(f); }), files.filter(function (f) { return !allowed.test(f); }));
check('static: no .html / .htm / .svg / .xml file (would run in the site origin)', files.every(function (f) { return !/\.(html?|svg|xml|xhtml)$/i.test(f); }));
var published = catalog.items.filter(function (i) { return i.status === 'published' && ['web', 'video'].indexOf(i.medium) !== -1; });
var shipped = { 'runtime/demo-runtime.js': 1, 'demos/.htaccess': 1 };
published.forEach(function (i) {
    ['poster', 'posterFallback', 'loop', 'loopFallback'].forEach(function (k) { if (i[k]) shipped[i[k]] = 1; });
    if (i.video) { shipped[i.video.mp4] = 1; if (i.video.webm) shipped[i.video.webm] = 1; }
    if (i.preview.entry) files.filter(function (f) { return f.indexOf(i.preview.entry) === 0; }).forEach(function (f) { shipped[f] = 1; });
});
var orphan = files.filter(function (f) { return !shipped[f]; });
var dh = fs.existsSync(path.join(ASSETS, 'demos/.htaccess')) ? fs.readFileSync(path.join(ASSETS, 'demos/.htaccess'), 'utf8') : '';
var ch = fs.readFileSync(path.join(ASSETS, '../data/design/.htaccess'), 'utf8');
var rule = function (t) { return t.slice(t.indexOf('<IfModule mod_authz_core.c>')); };
check('G14: assets/design/demos/.htaccess denies every request with the same rule as the catalog guard (Require all denied / Deny from all)', '' !== dh && /Require all denied/.test(dh) && /Deny from all/.test(dh) && rule(dh) === rule(ch) && !/Allow from|granted|<Files/i.test(rule(dh)));
check('package: every design asset belongs to a published work (nothing draft or orphaned ships)', orphan.length === 0, orphan);
files.filter(function (f) { return /demo\.php$/.test(f); }).forEach(function (f) {
    var src = fs.readFileSync(path.join(ASSETS, f), 'utf8');
    check('guard: ' + f + ' exits without ABSPATH', /^<\?php[\s\S]*?\n\s*defined\('ABSPATH'\) \|\| exit;/.test(src) && src.indexOf("defined('ABSPATH') || exit;") < src.indexOf('?>'));
});
files.filter(function (f) { return /^(demos\/.*\.(css|js)|runtime\/.*\.js)$/.test(f); }).forEach(function (f) {
    var src = fs.readFileSync(path.join(ASSETS, f), 'utf8');
    check('inline-safe: ' + f + ' has no </script, </style or <!--', !/<\/script|<\/style|<!--/i.test(src));
});
files.filter(function (f) { return /^demos\/.*\.(js|php)$/.test(f); }).forEach(function (f) {
    var src = fs.readFileSync(path.join(ASSETS, f), 'utf8');
    var bad = ['fetch(', 'XMLHttpRequest', 'WebSocket', 'sendBeacon', 'eval(', 'new Function', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'location.href', 'location.assign', 'location.replace', 'window.open', 'target=', 'http://', 'https://', 'import(', 'localStorage', 'document.cookie'].filter(function (t) { return src.indexOf(t) !== -1; });
    check('audit: ' + f + ' uses no network, eval, HTML injection, navigation or external URL', bad.length === 0, bad);
});
var dlCode = ['inc/design-library.php', 'inc/design-library-demo.php', 'page-design.php', 'assets/js/design-library.js', 'assets/js/design-library-preview.js']
    .concat(walk(path.join(THEME, 'template-parts/design-library')).map(function (f) { return path.relative(THEME, f); }))
    .concat(files.filter(function (f) { return /\.(php|js|css)$/.test(f); }).map(function (f) { return 'assets/design/' + f; }));
var widening = dlCode.filter(function (f) { return /allow-same-origin|allow-top-navigation|allow-popups|allow-forms|allow-modals/.test(fs.readFileSync(path.join(THEME, f), 'utf8').replace(/^\s*(\*|\/\/|#).*$/gm, '')); });
check('sandbox: no Design Library code grants allow-same-origin / top-navigation / popups / forms / modals', widening.length === 0, widening);
var demoSrc = fs.readFileSync(path.join(THEME, 'inc/design-library-demo.php'), 'utf8');
check('sandbox: demo CSP sandbox token is the constant "sandbox allow-scripts"', /const OMOCHIX_DESIGN_DEMO_SANDBOX = 'sandbox allow-scripts';/.test(demoSrc));
check('sandbox: the detail iframe is sandbox="allow-scripts" only', /sandbox="allow-scripts"/.test(fs.readFileSync(path.join(THEME, 'template-parts/design-library/detail.php'), 'utf8')));
var libSrc = fs.readFileSync(path.join(THEME, 'inc/design-library.php'), 'utf8');
check('no create button: omochix_design_motion_studio_url() returns an empty string', /function omochix_design_motion_studio_url\(array \$item\) \{\s*unset\(\$item\);\s*return '';\s*\}/.test(libSrc));
check('no unanchored "$" in the validator patterns (trailing newline)', !/preg_match\('\/\^[^']*\$\/[a-z]*'/.test(libSrc), (libSrc.match(/preg_match\('\/\^[^']*\$\/[a-z]*'/g) || []).slice(0, 3));

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
