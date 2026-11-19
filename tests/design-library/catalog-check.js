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
check('catalog: no work claims verified-production (production checks are not done)', catalog.items.every(function (i) { return i.verification.state !== 'verified-production'; }));
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
    ['negative perf value', function (d) { d.items[W].audit.perf = { lcpMs: -1 }; }]
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
var dup = clone(catalog); dup.items[1].slug = dup.items[0].slug;
var pd = phpResult(dup);
check('php: duplicate slug keeps the first item, drops the second', pd.items.indexOf(catalog.items[0].slug) !== -1 && pd.items.indexOf(catalog.items[1].slug) === -1);

/* ---------- 4. static files ---------- */
function walk(dir) { return fs.readdirSync(dir, { withFileTypes: true }).reduce(function (a, e) { var f = path.join(dir, e.name); return a.concat(e.isDirectory() ? walk(f) : [f]); }, []); }
var files = walk(ASSETS).map(function (f) { return path.relative(ASSETS, f).split(path.sep).join('/'); });
var allowed = /^(posters\/[a-z0-9-]+\.(avif|webp)|loops\/[a-z0-9-]+\.(webm|mp4)|videos\/[a-z0-9-]+\.(webm|mp4)|demos\/[a-z0-9-]+\/demo\.(php|css|js)|runtime\/demo-runtime\.js)$/;
check('static: only posters, loops, videos, demo sources and the runtime under assets/design', files.every(function (f) { return allowed.test(f); }), files.filter(function (f) { return !allowed.test(f); }));
check('static: no .html / .htm / .svg / .xml file (would run in the site origin)', files.every(function (f) { return !/\.(html?|svg|xml|xhtml)$/i.test(f); }));
var published = catalog.items.filter(function (i) { return i.status === 'published' && ['web', 'video'].indexOf(i.medium) !== -1; });
var shipped = { 'runtime/demo-runtime.js': 1 };
published.forEach(function (i) {
    ['poster', 'posterFallback', 'loop', 'loopFallback'].forEach(function (k) { if (i[k]) shipped[i[k]] = 1; });
    if (i.video) { shipped[i.video.mp4] = 1; if (i.video.webm) shipped[i.video.webm] = 1; }
    if (i.preview.entry) files.filter(function (f) { return f.indexOf(i.preview.entry) === 0; }).forEach(function (f) { shipped[f] = 1; });
});
var orphan = files.filter(function (f) { return !shipped[f]; });
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
