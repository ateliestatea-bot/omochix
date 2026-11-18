#!/usr/bin/env node
/**
 * Design Library D2-B: checks for new Web works BEFORE they are marked verified (local THROWAWAY site).
 * The test flag of the local mu-plugin serves unverified demos to these checks only.
 *
 *   DL_BASE=... DL_SITE=... NODE_PATH=<playwright>:<axe-core> node tests/design-library/browser/new-works.js dark-luxury [aurora-gradient]
 *
 * Per work, in Chromium and WebKit (Firefox: firefox.js covers every demo in L.DEMOS):
 *  - direct and embedded: no CSP violation, no page error, no request outside the demo and its assets,
 *    opaque origin, cookies blocked
 *  - widths 1440 / 834 / 390: no horizontal scroll
 *  - small accent text: measured contrast ≥ 4.5:1 against the real background (WCAG AA)
 *  - axe (WCAG 2.2 A/AA) on the demo document: no serious / critical violation (axe is injected with a
 *    CSP-bypassing test context; the demo's real CSP is checked separately above)
 *  - reduced motion: no frame loop when idle, all content visible; JavaScript off: all content visible
 *  - embedded: pause stops the frame loop, play resumes it, restart reloads; hidden tab stops frames
 *  - keyboard: links / buttons get a visible focus ring (D2-D: spec.focus names the first visible header control)
 * Work-specific checks come from SPEC below.
 */
'use strict';
var path = require('path');
var fs = require('fs');
var { chromium, webkit } = require('playwright');
var L = require('./lib.js');
var check = L.check, BASE = L.BASE;
var AXE = L.axePath();
var AXE_SRC = AXE ? fs.readFileSync(AXE, 'utf8') : '';
var SLUGS = process.argv.slice(2);
if (!SLUGS.length) { console.error('slugs required'); process.exit(2); }

/* Selectors of small accent text to measure, and what every work must show without JS / with reduced motion. */
var SPEC = {
    'dark-luxury': {
        accent: ['.dk-kicker', '.dk-label', '.dk-num', '.dk-card__n', '.dk-spec th', '.dk-btn--gold'],
        content: ['.dk-title', '.dk-tenets li', '.dk-card', '.dk-spec', '.dk-contact__actions'],
        loop: false
    },
    'aurora-gradient': {
        accent: ['.au-eyebrow', '.au-kicker', '.au-badge', '.au-plan__tag'],
        content: ['.au-title', '.au-row', '.au-cta'],
        loop: true
    },
    'ai-workspace': {
        accent: ['.aw-cite', '.aw-up', '.aw-src__n', '.aw-hint', '.aw-src__err', '.aw-send', '.aw-side__h'],
        content: ['.aw-thread__head h1', '.aw-msg--ai > p', '.aw-table', '.aw-src'],
        focus: '[data-aw-open-palette]',
        loop: false
    },
    'micro-interactions': {
        accent: ['.mi-step', '.mi-msg', '.mi-btn--primary', '.mi-row__d', '.mi-item__d', '.mi-states small', '.mi-top__note'],
        content: ['.mi-card h2', '.mi-row', '.mi-item', '.mi-states li'],
        focus: '.mi-nav a',
        loop: false
    },
    'interactive-story': {
        accent: ['.sk-kicker', '.sk-meta', '.sk-toc a span', '.sk-ch__h span', '.sk-voice label small', '.sk-bar__ch', '.sk-end__note', '.sk-brand__en'],
        content: ['.sk-intro h1', '.sk-ch__h', '.sk-p--craft', '.sk-year li'],
        focus: '.sk-bar__toc',
        loop: false
    }
};

function luminance(rgb) {
    var c = rgb.map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
}
function ratio(a, b) { var x = luminance(a), y = luminance(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
/* In the page: text colour and the first opaque background colour behind the element. */
function colours(sel) {
    return Array.prototype.slice.call(document.querySelectorAll(sel)).slice(0, 4).map(function (el) {
        function rgb(s) { var m = s.match(/rgba?\(([^)]+)\)/); if (!m) return null; var p = m[1].split(',').map(Number); return { c: p.slice(0, 3), a: p.length > 3 ? p[3] : 1 }; }
        var fg = rgb(getComputedStyle(el).color), bg = null, n = el;
        while (n && n.nodeType === 1) { var b = rgb(getComputedStyle(n).backgroundColor); if (b && b.a > 0.95) { bg = b.c; break; } n = n.parentElement; }
        var r = el.getBoundingClientRect();
        return { sel: (el.className || el.tagName) + '', fg: fg && fg.c, alpha: fg && fg.a, bg: bg || [255, 255, 255], size: parseFloat(getComputedStyle(el).fontSize), visible: r.width > 0 };
    });
}
async function rafDelta(target, ms) { var a = await target.evaluate(function () { return window.__raf; }); await L.wait(ms); return (await target.evaluate(function () { return window.__raf; })) - a; }
async function demoFrame(page) {
    for (var i = 0; i < 80; i++) {
        var f = page.frames().filter(function (fr) { return fr.url().indexOf('/design/demo/web/') !== -1; })[0];
        if (f) { try { if (await f.evaluate(function () { return !!window.omxDemo; })) return f; } catch (e) { /* loading */ } }
        await L.wait(100);
    }
    throw new Error('no demo frame');
}

async function run(type, launcher) {
    var browser = await launcher.launch(type === 'chromium' ? { args: L.GL_ARGS } : {});
    for (var slug of SLUGS) {
        var spec = SPEC[slug];
        var T = type + ' ' + slug;
        var demoUrl = BASE + '/design/demo/web/' + slug + '/';

        /* direct at three widths */
        for (var w of [[1440, 900], [834, 1112], [390, 844]]) {
            var c = await browser.newContext({ viewport: { width: w[0], height: w[1] } });
            await c.addInitScript(L.INIT);
            var outside = [], errs = [];
            c.on('requestfinished', function (r) { var u = r.url(); if (u.indexOf(demoUrl) !== 0 && u.indexOf(BASE + '/wp-content/themes/omochix-theme/assets/design/') !== 0 && !/^(data|blob|about):/.test(u)) outside.push(u); });
            var p = await c.newPage();
            p.on('pageerror', function (e) { errs.push(e.message); });
            p.on('console', function (m) { if (/Content.Security.Policy|Refused to/i.test(m.text())) errs.push(m.text().slice(0, 140)); });
            await p.goto(demoUrl);
            await L.wait(1200);
            var H = await p.evaluate(function () { return document.documentElement.scrollHeight; });
            for (var k = 1; k <= 6; k++) { await p.evaluate(function (y) { window.scrollTo(0, y); }, Math.round(H * k / 6)); await L.wait(250); }
            await p.mouse.move(w[0] / 2, w[1] / 2); await p.mouse.move(w[0] / 3, w[1] / 3, { steps: 8 });
            await L.wait(400);
            var st = await p.evaluate(function () { var c; try { void document.cookie; c = 'readable'; } catch (e) { c = e.name; } return { sw: document.documentElement.scrollWidth, iw: innerWidth, csp: window.__csp, origin: String(self.origin), cookie: c }; });
            check(T + ' ' + w[0] + 'px direct: no side scroll, no CSP violation, no page error, no outside request', st.sw <= st.iw + 1 && st.csp.length === 0 && errs.length === 0 && outside.length === 0, { st: st, errs: errs, outside: outside });
            if (w[0] === 1440) check(T + ' direct: opaque origin, cookie blocked', st.origin === 'null' && st.cookie === 'SecurityError', st);
            if (w[0] === 1440 || w[0] === 390) await p.evaluate(function () { window.scrollTo(0, 0); });
            /* contrast of small accent text, measured */
            var bad = [];
            for (var sel of spec.accent) {
                var cols = await p.evaluate(colours, sel);
                cols.filter(function (x) { return x.visible && x.fg; }).forEach(function (x) {
                    var fg = x.alpha < 1 ? x.fg.map(function (v, i) { return Math.round(v * x.alpha + x.bg[i] * (1 - x.alpha)); }) : x.fg;
                    var r = ratio(fg, x.bg);
                    if (r < 4.5) bad.push({ sel: sel, ratio: Math.round(r * 100) / 100, size: x.size });
                    if (w[0] === 1440 && type === 'chromium') L.info(slug + ' contrast ' + sel + ' ' + x.size + 'px: ' + (Math.round(r * 100) / 100) + ':1');
                });
            }
            check(T + ' ' + w[0] + 'px: small accent text measures ≥ 4.5:1 against its background', bad.length === 0, bad);
            await c.close();
        }

        /* axe on the demo document (test context bypasses CSP only to inject axe) */
        if (AXE_SRC) {
            for (var aw of [[1440, 900], [390, 844]]) {
                var ca = await browser.newContext({ viewport: { width: aw[0], height: aw[1] }, bypassCSP: true });
                var pa = await ca.newPage();
                await pa.goto(demoUrl); await L.wait(1200);
                await pa.evaluate(function () { document.querySelectorAll('[data-dk-reveal],[data-au-reveal]').forEach(function (e) { e.classList.add('is-in'); }); });
                await L.wait(1300);
                await pa.evaluate(AXE_SRC);
                var v = await pa.evaluate(function () { return window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } }).then(function (x) { return x.violations.map(function (v) { return v.impact + ' ' + v.id + ' x' + v.nodes.length; }); }); });
                check(T + ' axe ' + aw[0] + 'px: no serious or critical violation in the demo', v.filter(function (x) { return /^(serious|critical)/.test(x); }).length === 0, v);
                if (v.length) L.info(T + ' axe ' + aw[0] + 'px minor/moderate: ' + v.join(', '));
                await ca.close();
            }
        }

        /* reduced motion and no JavaScript */
        var cr = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
        await cr.addInitScript(L.INIT);
        var pr = await cr.newPage();
        await pr.goto(demoUrl); await L.wait(1000);
        var idle = await rafDelta(pr, 1000);
        var shown = await pr.evaluate(function (sels) { return sels.every(function (s) { return Array.prototype.every.call(document.querySelectorAll(s), function (e) { var cs = getComputedStyle(e); return cs.display !== 'none' && cs.visibility !== 'hidden' && Number(cs.opacity) > 0.95; }); }); }, spec.content);
        check(T + ' reduced motion: everything visible at once, no frame loop when idle', shown && idle === 0, [shown, idle]);
        await cr.close();
        if (spec.loop) {
            /* Canvas works: a still frame for reduced motion; frames stop off screen; Save-Data and low-power settings */
            var stillDrawn = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' })).newPage().then(async function (q) {
                await q.goto(demoUrl); await L.wait(800);
                var r = await q.evaluate(function () { return document.documentElement.classList.contains('au-live'); });
                await q.context().close(); return r;
            });
            check(T + ' reduced motion: one still aurora frame is drawn on the canvas', stillDrawn);
            var co = await browser.newContext({ viewport: { width: 1440, height: 900 } });
            await co.addInitScript(L.INIT);
            var po = await co.newPage();
            await po.goto(demoUrl); await L.wait(1000);
            var on = await rafDelta(po, 800);
            await po.evaluate(function () { window.scrollTo(0, document.querySelector('#au-pricing').offsetTop); }); await L.wait(500);
            var off = await rafDelta(po, 800);
            await po.evaluate(function () { window.scrollTo(0, 0); }); await L.wait(500);
            var back = await rafDelta(po, 800);
            check(T + ' frames stop while the sky is off screen and resume when it returns', on > 5 && off === 0 && back > 5, [on, off, back]);
            await co.close();
            var cs = await browser.newContext({ viewport: { width: 1440, height: 900 } });
            await cs.addInitScript(L.INIT);
            await cs.addInitScript(function () { Object.defineProperty(navigator, 'connection', { value: { saveData: true }, configurable: true }); });
            var ps = await cs.newPage();
            await ps.goto(demoUrl); await L.wait(1000);
            var sd = await rafDelta(ps, 1000);
            var sdLive = await ps.evaluate(function () { return document.documentElement.classList.contains('au-live'); });
            check(T + ' Save-Data: one still frame, no frame loop', sd === 0 && sdLive, [sd, sdLive]);
            await cs.close();
            var cl = await browser.newContext({ viewport: { width: 1440, height: 900 } });
            await cl.addInitScript(function () { Object.defineProperty(navigator, 'hardwareConcurrency', { value: 2, configurable: true }); });
            var pl = await cl.newPage();
            await pl.goto(demoUrl); await L.wait(1000);
            var lw = await pl.evaluate(function () { return document.querySelector('[data-au-canvas]').width; });
            check(T + ' low-power device: light setting (canvas ≤ 520 px wide)', lw > 0 && lw <= 520, lw);
            await cl.close();
        }
        var cn = await browser.newContext({ viewport: { width: 390, height: 844 }, javaScriptEnabled: false });
        var pn = await cn.newPage();
        await pn.goto(demoUrl);
        var nojs = await pn.evaluate(function (sels) { return sels.map(function (s) { var l = document.querySelectorAll(s); return l.length > 0 && Array.prototype.every.call(l, function (e) { var cs = getComputedStyle(e); return cs.display !== 'none' && Number(cs.opacity) > 0.95; }); }); }, spec.content);
        var nsw = await pn.evaluate(function () { return document.documentElement.scrollWidth <= innerWidth + 1; });
        check(T + ' no JS (phone): all content visible, no side scroll', nojs.every(Boolean) && nsw, nojs);
        await cn.close();

        /* embedded: controls, hidden tab, keyboard */
        var ce = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        await ce.addInitScript(L.INIT);
        var pe = await ce.newPage();
        var eerr = [];
        pe.on('pageerror', function (e) { eerr.push(e.message); });
        await pe.goto(BASE + '/design/web/' + slug + '/');
        await pe.locator('[data-dl-stage]').scrollIntoViewIfNeeded();
        var f = await demoFrame(pe);
        for (var i = 0; i < 40 && (await pe.getAttribute('[data-dl-stage]', 'data-state')) !== 'ready'; i++) await L.wait(100);
        if (!spec.loop) await f.evaluate(function () { window.scrollTo(0, 600); });
        await L.wait(600);
        var a1 = spec.loop ? await rafDelta(f, 800) : null;
        await pe.click('[data-dl-toggle]'); await L.wait(300);
        var paused = await f.evaluate(function () { return window.omxDemo.isPaused(); });
        var a2 = await rafDelta(f, 800);
        await pe.click('[data-dl-toggle]'); await L.wait(300);
        var a3 = spec.loop ? await rafDelta(f, 800) : null;
        check(T + ' embedded: pause stops all frames, play resumes' + (spec.loop ? ' the loop' : ''), paused && a2 === 0 && (!spec.loop || (a1 > 5 && a3 > 5)), [a1, paused, a2, a3]);
        await f.evaluate(function () { Object.defineProperty(document, 'hidden', { value: true, configurable: true }); document.dispatchEvent(new Event('visibilitychange')); });
        await L.wait(300);
        check(T + ' embedded: no frames while the tab is hidden', (await rafDelta(f, 800)) === 0);
        await f.evaluate(function () { window.__mark = 1; });
        await pe.click('[data-dl-restart]');
        var f2 = null;
        for (var j = 0; j < 60; j++) { await L.wait(100); f2 = pe.frames().filter(function (fr) { return fr.url().indexOf('/design/demo/web/') !== -1; })[0]; try { if (f2 && await f2.evaluate(function () { return !!window.omxDemo && window.__mark === undefined; })) break; } catch (e) { /* reloading */ } }
        check(T + ' embedded: restart reloads the demo', !!f2 && (await f2.evaluate(function () { return window.__mark === undefined && !window.omxDemo.isPaused(); })));
        var vcsp = await f2.evaluate(function () { return window.__csp; });
        check(T + ' embedded: no CSP violation inside the frame, no page error', vcsp.length === 0 && eerr.length === 0, [vcsp, eerr]);
        await ce.close();

        var ck = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        var pk = await ck.newPage();
        await pk.goto(demoUrl); await L.wait(800);
        await pk.focus(spec.focus || 'nav a, header button');
        var ring = await pk.evaluate(function () { var e = document.activeElement; var cs = getComputedStyle(e); return e !== document.body && (parseFloat(cs.outlineWidth) > 0 || cs.boxShadow !== 'none'); });
        check(T + ' keyboard: the first header control takes focus with a visible ring', ring);
        if (slug === 'dark-luxury') {
            await pk.focus('.dk-card:last-child'); await L.wait(900);
            var seen = await pk.evaluate(function () { var r = document.querySelector('.dk-card:last-child').getBoundingClientRect(); return r.left >= -1 && r.right <= innerWidth + 1 && r.top < innerHeight && r.bottom > 0; });
            check(T + ' keyboard: focusing the last work in the pinned track brings it on screen', seen);
        }
        await ck.close();
    }
    await browser.close();
}

(async function () {
    L.flag(true);
    try {
        await run('chromium', chromium);
        await run('webkit', webkit);
    } finally { L.flag(false); }
    process.exit(L.summary('new works ' + SLUGS.join(', ') + ' (chromium, webkit)'));
})().catch(function (e) { console.error(e); L.flag(false); process.exit(1); });
