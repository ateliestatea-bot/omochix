#!/usr/bin/env node
/**
 * Design Library D2-A detail preview in Chromium and WebKit (local THROWAWAY site):
 *  - viewport switch PC 1280 / tablet 834 / phone 390: the demo's own innerWidth changes, the demo is not
 *    reloaded, the aspect ratio is kept, the page never scrolls sideways (page widths 1440 / 834 / 390),
 *    arrow keys / Home / End move the selection
 *  - play / pause (frame loop stops), restart (demo reloads and plays), pause while off screen
 *  - full screen through the Fullscreen API and through the in-page overlay (API removed): sandbox and CSP
 *    unchanged, the demo still has an opaque origin, cannot use cookies or go full screen itself, Escape
 *    closes, focus and scroll position come back, Tab stays inside the overlay
 *  - Save-Data starts paused, reduced motion keeps the controls, no JavaScript shows the plain iframe
 *  - axe on the detail page in light and dark, one demo instance per page
 *   DL_BASE=... DL_SITE=... NODE_PATH=<playwright>:<axe-core> node tests/design-library/browser/preview.js
 */
'use strict';
var path = require('path');
var fs = require('fs');
var { chromium, webkit } = require('playwright');
var L = require('./lib.js');
var check = L.check, BASE = L.BASE;
var SHOTS = path.resolve(__dirname, '../../../docs/design-library/qa/d2a/after');
fs.mkdirSync(SHOTS, { recursive: true });
var AXE = L.axePath();
var VPS = { desktop: [1280, 800], tablet: [834, 1112], mobile: [390, 844] };

async function ctxFor(browser, o) {
    o = o || {};
    var c = await browser.newContext({ viewport: o.vp || { width: 1440, height: 900 }, colorScheme: o.scheme || 'light', reducedMotion: o.reduce ? 'reduce' : 'no-preference', javaScriptEnabled: o.js !== false });
    await c.addInitScript(L.INIT);
    await c.addInitScript(function (s) { try { localStorage.setItem('omochix-theme', s); } catch (e) { /* sandboxed demo */ } }, o.scheme || 'light');
    if (o.saveData) await c.addInitScript(function () { Object.defineProperty(navigator, 'connection', { value: { saveData: true, effectiveType: '4g', addEventListener: function () {} }, configurable: true }); });
    if (o.noFullscreenApi) await c.addInitScript(function () { try { delete Element.prototype.requestFullscreen; delete Element.prototype.webkitRequestFullscreen; Element.prototype.requestFullscreen = undefined; Element.prototype.webkitRequestFullscreen = undefined; } catch (e) { /* ignore */ } });
    return c;
}
async function demoFrame(page) {
    for (var i = 0; i < 80; i++) {
        var f = page.frames().filter(function (fr) { return fr.url().indexOf('/design/demo/web/') !== -1; })[0];
        if (f) { try { if (await f.evaluate(function () { return !!window.omxDemo; })) return f; } catch (e) { /* loading */ } }
        await L.wait(100);
    }
    throw new Error('no demo frame');
}
async function ready(page) {
    for (var i = 0; i < 80; i++) { if ((await page.getAttribute('[data-dl-stage]', 'data-state')) === 'ready') return true; await L.wait(100); }
    return false;
}
async function rafDelta(frame, ms) { var a = await frame.evaluate(function () { return window.__raf; }); await L.wait(ms); return (await frame.evaluate(function () { return window.__raf; })) - a; }
function stageInfo(page) {
    return page.evaluate(function () {
        var s = document.querySelector('[data-dl-stage]'), v = s.querySelector('[data-dl-view]'), c = s.querySelector('[data-dl-canvas]'), f = s.querySelector('[data-dl-demo]');
        var cr = c.getBoundingClientRect(), vr = v.getBoundingClientRect();
        return { vp: s.dataset.viewport, scale: Number(s.dataset.scale), cw: cr.width, ch: cr.height, vw: vr.width, sw: document.documentElement.scrollWidth, iw: innerWidth, sandbox: f.getAttribute('sandbox'), allow: f.getAttribute('allow'), afs: f.hasAttribute('allowfullscreen'), src: f.getAttribute('src'), frames: document.querySelectorAll('iframe[data-dl-demo]').length };
    });
}

async function run(type, launcher) {
    var browser = await launcher.launch(type === 'chromium' ? { args: L.GL_ARGS } : {});

    /* ---------- viewport switch at three page widths ---------- */
    for (var pw of [[1440, 900, 'pc'], [834, 1112, 'tablet'], [390, 844, 'phone']]) {
        var c = await ctxFor(browser, { vp: { width: pw[0], height: pw[1] } });
        var p = await c.newPage();
        var errs = [];
        p.on('pageerror', function (e) { errs.push(e.message); });
        for (var slug of L.DEMOS) {
            await p.goto(BASE + '/design/web/' + slug + '/');
            await p.locator('[data-dl-stage]').scrollIntoViewIfNeeded();
            var f = await demoFrame(p);
            await ready(p);
            await f.evaluate(function () { window.__mark = 'kept'; });
            var ok = true, det = [];
            for (var name of Object.keys(VPS)) {
                await p.click('[data-vp="' + name + '"]');
                await L.wait(350);
                var iw = await f.evaluate(function () { return innerWidth; });
                var st = await stageInfo(p);
                var ratio = (st.cw / st.ch) / (VPS[name][0] / VPS[name][1]);
                var good = iw === VPS[name][0] && st.cw <= st.vw + 1 && Math.abs(ratio - 1) < 0.02 && st.sw <= st.iw + 1 && st.scale <= 1;
                if (!good) { ok = false; det.push({ name: name, iw: iw, st: st, ratio: ratio }); }
            }
            var kept = await f.evaluate(function () { return window.__mark; });
            check(type + ' ' + pw[2] + ' ' + slug + ': PC/tablet/phone give the demo 1280/834/390 px, ratio kept, no side scroll, not reloaded', ok && kept === 'kept', det.concat([{ kept: kept }]));
        }
        // keyboard on the last page
        await p.focus('[data-vp][aria-checked="true"]');
        await p.keyboard.press('Home');
        var k0 = await p.evaluate(function () { return document.activeElement.getAttribute('data-vp'); });
        await p.keyboard.press('ArrowRight');
        var k1 = await p.evaluate(function () { return [document.activeElement.getAttribute('data-vp'), document.activeElement.getAttribute('aria-checked')]; });
        await p.keyboard.press('End');
        var k2 = await p.evaluate(function () { return document.activeElement.getAttribute('data-vp'); });
        await p.keyboard.press('ArrowRight');
        var k3 = await p.evaluate(function () { return document.activeElement.getAttribute('data-vp'); });
        check(type + ' ' + pw[2] + ' keyboard: Home / ArrowRight / End / wrap-around move and select the size', k0 === 'desktop' && k1[0] === 'tablet' && k1[1] === 'true' && k2 === 'mobile' && k3 === 'desktop', [k0, k1, k2, k3]);
        check(type + ' ' + pw[2] + ': no page errors', errs.length === 0, errs);
        await c.close();
    }

    /* ---------- controls, off screen, full screen (API) ---------- */
    var c2 = await ctxFor(browser, {});
    var p2 = await c2.newPage();
    var dialogs = [];
    p2.on('dialog', function (d) { dialogs.push(d.message()); d.dismiss().catch(function () {}); });
    await p2.goto(BASE + '/design/web/3d-interactive/');
    await p2.locator('[data-dl-stage]').scrollIntoViewIfNeeded();
    var f2 = await demoFrame(p2);
    await ready(p2);
    await L.wait(600);
    var playing = await rafDelta(f2, 800);
    await p2.click('[data-dl-toggle]'); await L.wait(300);
    var paused = await rafDelta(f2, 800);
    var pressed = await p2.getAttribute('[data-dl-toggle]', 'aria-pressed');
    check(type + ' pause: the 3D frame loop stops (rAF ' + playing + ' -> ' + paused + '), button pressed', playing > 10 && paused === 0 && pressed === 'true', [playing, paused, pressed]);
    await p2.click('[data-dl-toggle]'); await L.wait(300);
    var again = await rafDelta(f2, 800);
    check(type + ' play: the loop runs again', again > 10, again);
    await f2.evaluate(function () { window.__mark = 'old'; });
    await p2.click('[data-dl-restart]');
    var f3 = null;
    for (var i = 0; i < 60; i++) { await L.wait(100); f3 = p2.frames().filter(function (fr) { return fr.url().indexOf('/design/demo/web/') !== -1; })[0]; try { if (f3 && await f3.evaluate(function () { return !!window.omxDemo && window.__mark !== 'old'; })) break; } catch (e) { /* reloading */ } }
    await ready(p2); await L.wait(500);
    check(type + ' restart: the demo reloads from the start and plays', (await f3.evaluate(function () { return window.__mark === undefined && !window.omxDemo.isPaused(); })) && (await rafDelta(f3, 600)) > 5);
    await p2.evaluate(function () { window.scrollTo(0, document.body.scrollHeight); }); await L.wait(700);
    var off = await rafDelta(f3, 800);
    await p2.locator('[data-dl-stage]').scrollIntoViewIfNeeded(); await L.wait(700);
    var on = await rafDelta(f3, 800);
    check(type + ' off screen: the demo pauses while the stage is out of view and resumes after', off === 0 && on > 5, [off, on]);
    // full screen
    await p2.evaluate(function () { window.scrollTo(0, 260); }); await L.wait(200);
    var y0 = await p2.evaluate(function () { return window.scrollY; });
    var before = await stageInfo(p2);
    await p2.click('[data-dl-fullscreen]'); await L.wait(700);
    var fsState = await p2.evaluate(function () { var s = document.querySelector('[data-dl-stage]'); return { full: s.dataset.full, api: document.fullscreenElement === s || (document.webkitFullscreenElement === s), pressed: s.querySelector('[data-dl-fullscreen]').getAttribute('aria-pressed'), focus: document.activeElement.hasAttribute('data-dl-fullscreen') }; });
    var inFull = await stageInfo(p2);
    var fx = await demoFrame(p2);
    var iso = await fx.evaluate(function () { var c = 'n/a'; try { void document.cookie; c = 'readable'; } catch (e) { c = e.name; } return { origin: String(self.origin), cookie: c, canFs: document.fullscreenEnabled === true || document.webkitFullscreenEnabled === true }; });
    check(type + ' full screen: opens (' + fsState.full + '), toggle pressed, focus on the close control, still one demo', !!fsState.full && fsState.pressed === 'true' && fsState.focus && inFull.frames === 1, fsState);
    check(type + ' full screen: sandbox / allow / src unchanged, demo origin null, cookie blocked, demo cannot request full screen itself', inFull.sandbox === 'allow-scripts' && inFull.allow === null && !inFull.afs && inFull.src === before.src && iso.origin === 'null' && iso.cookie === 'SecurityError' && iso.canFs === false, [inFull, iso]);
    check(type + ' full screen: the demo keeps its real width and fits the screen', inFull.cw <= inFull.vw + 1 && inFull.sw <= inFull.iw + 1);
    await p2.screenshot({ path: path.join(SHOTS, type + '-fullscreen-3d.png') });
    await p2.keyboard.press('Escape'); await L.wait(700);
    var closed = await p2.evaluate(function () { var s = document.querySelector('[data-dl-stage]'); return { full: s.classList.contains('is-full'), fe: !!(document.fullscreenElement || document.webkitFullscreenElement), focus: document.activeElement.hasAttribute('data-dl-fullscreen'), y: window.scrollY }; });
    check(type + ' full screen: Escape closes, focus returns, scroll position restored', !closed.full && !closed.fe && closed.focus && Math.abs(closed.y - y0) <= 2, [closed, y0]);
    check(type + ' no dialogs / popups during the preview', dialogs.length === 0);
    await c2.close();

    /* ---------- full screen fallback (no Fullscreen API), phone ---------- */
    var c3 = await ctxFor(browser, { vp: { width: 390, height: 844 }, noFullscreenApi: true });
    var p3 = await c3.newPage();
    await p3.goto(BASE + '/design/web/liquid-glass/');
    await p3.locator('[data-dl-stage]').scrollIntoViewIfNeeded();
    await demoFrame(p3); await ready(p3);
    var y3 = await p3.evaluate(function () { return window.scrollY; });
    await p3.click('[data-dl-fullscreen]'); await L.wait(400);
    var ov = await p3.evaluate(function () { var s = document.querySelector('[data-dl-stage]'); var r = s.getBoundingClientRect(); return { full: s.dataset.full, role: s.getAttribute('role'), modal: s.getAttribute('aria-modal'), lock: document.documentElement.classList.contains('dl-noscroll'), cover: r.top <= 0 && r.left <= 0 && r.width >= innerWidth - 1 && r.height >= innerHeight - 1, sandbox: s.querySelector('iframe').getAttribute('sandbox') }; });
    check(type + ' fallback overlay: opens without the Fullscreen API as a modal dialog over the page, scroll locked, sandbox unchanged', ov.full === 'overlay' && ov.role === 'dialog' && ov.modal === 'true' && ov.lock && ov.cover && ov.sandbox === 'allow-scripts', ov);
    var inside = true;
    for (var t = 0; t < 8; t++) { await p3.keyboard.press('Tab'); inside = inside && await p3.evaluate(function () { return document.querySelector('[data-dl-stage]').contains(document.activeElement); }); }
    check(type + ' fallback overlay: Tab stays inside the overlay', inside);
    await p3.screenshot({ path: path.join(SHOTS, type + '-overlay-phone.png') });
    // Put the focus inside the demo document, where the page cannot hear keys: Escape must still close.
    var fr3 = await demoFrame(p3);
    await fr3.evaluate(function () { var b = document.querySelector('button'); if (b) b.focus(); else document.body.focus(); });
    var inDemo = await p3.evaluate(function () { return document.activeElement && document.activeElement.tagName === 'IFRAME'; });
    await p3.keyboard.press('Escape'); await L.wait(400);
    check(type + ' fallback overlay: focus was inside the demo when Escape was pressed', inDemo);
    var cl = await p3.evaluate(function () { var s = document.querySelector('[data-dl-stage]'); return { full: s.classList.contains('is-full'), lock: document.documentElement.classList.contains('dl-noscroll'), y: window.scrollY, sw: document.documentElement.scrollWidth, iw: innerWidth }; });
    check(type + ' fallback overlay: Escape closes, scroll unlocked and restored, no side scroll', !cl.full && !cl.lock && Math.abs(cl.y - y3) <= 2 && cl.sw <= cl.iw + 1, [cl, y3]);
    await c3.close();

    /* ---------- Save-Data, reduced motion, no JS ---------- */
    var c4 = await ctxFor(browser, { saveData: true });
    var p4 = await c4.newPage();
    await p4.goto(BASE + '/design/web/3d-interactive/');
    await p4.locator('[data-dl-stage]').scrollIntoViewIfNeeded();
    var f4 = await demoFrame(p4); await ready(p4); await L.wait(500);
    check(type + ' Save-Data: the demo starts paused (no frame loop) and says so', (await p4.getAttribute('[data-dl-toggle]', 'aria-pressed')) === 'true' && (await rafDelta(f4, 800)) === 0 && (await p4.textContent('[data-dl-status]')).indexOf('データセーバー') !== -1);
    await c4.close();
    var c5 = await ctxFor(browser, { reduce: true });
    var p5 = await c5.newPage();
    await p5.goto(BASE + '/design/web/minimal-editorial/');
    var f5 = await demoFrame(p5); await ready(p5);
    check(type + ' reduced motion: controls and sizes still offered, the demo itself is in reduced mode', (await p5.isVisible('[data-dl-toggle]')) && (await p5.isVisible('[data-vp="mobile"]')) && (await f5.evaluate(function () { return document.documentElement.classList.contains('reduced'); })));
    await c5.close();
    var c6 = await ctxFor(browser, { js: false });
    var p6 = await c6.newPage();
    await p6.goto(BASE + '/design/web/bento-saas/');
    var nojs = await p6.evaluate(function () { var s = document.querySelector('[data-dl-stage]'); var f = s.querySelector('iframe'); var v = s.querySelector('[data-dl-view]'); return { radios: s.querySelector('[data-dl-viewports]').offsetParent !== null, controls: s.querySelector('[data-dl-controls]').offsetParent !== null, fluid: Math.abs(f.getBoundingClientRect().width - v.getBoundingClientRect().width) < 2, link: !!s.querySelector('a[target="_blank"][href*="/design/demo/web/bento-saas/"]') }; });
    check(type + ' no JS: no size buttons or controls, fluid iframe, new-tab link available', !nojs.radios && !nojs.controls && nojs.fluid && nojs.link, nojs);
    await c6.close();

    /* ---------- axe, light and dark, PC and phone ---------- */
    for (var m of [['light', { width: 1440, height: 900 }], ['dark', { width: 390, height: 844 }]]) {
        var c7 = await ctxFor(browser, { scheme: m[0], vp: m[1] });
        var p7 = await c7.newPage();
        await p7.goto(BASE + '/design/web/liquid-glass/');
        await demoFrame(p7); await ready(p7);
        if (AXE) {
            await p7.addScriptTag({ path: AXE });
            var v = await p7.evaluate(function () { return window.axe.run({ include: [['main']], exclude: [['iframe']] }, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } }).then(function (x) { return x.violations.map(function (v) { return v.impact + ' ' + v.id; }); }); });
            check(type + ' axe ' + m[0] + ' ' + m[1].width + 'px: no serious or critical violation on the detail page with the new controls', v.filter(function (x) { return /^(serious|critical)/.test(x); }).length === 0, v);
        }
        await p7.screenshot({ path: path.join(SHOTS, type + '-detail-' + m[0] + '-' + m[1].width + '.png'), fullPage: false });
        await c7.close();
    }
    await browser.close();
}

(async function () {
    L.flag(false);
    await run('chromium', chromium);
    await run('webkit', webkit);
    process.exit(L.summary('preview D2-A (chromium, webkit)'));
})().catch(function (e) { console.error(e); process.exit(1); });
