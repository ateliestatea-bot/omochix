#!/usr/bin/env node
/**
 * Design Library list / detail / demo behaviour in Chromium and WebKit
 * (local THROWAWAY site, shipped catalog — the probe flag is off).
 *
 *  - PC 1440 and phone 390: no horizontal scroll, light and dark, axe (WCAG 2.2 A/AA) on <main>
 *  - loops: ≤ 2 playing at any time, off-screen cards pause, hidden tab pauses, a failed video keeps
 *    the poster, no autoplay with reduced motion or Save-Data (the play button still works), no layout shift
 *  - filters: keyword, style, industry, motion, empty state, reset, URL sync, keyboard only, server side without JS
 *  - demos: phone width without horizontal scroll, reduced-motion behaviour (D0 §15.2), no animation frames
 *    while hidden, JavaScript off fallbacks, 3D falls back to the poster without WebGL
 *  - screenshots of the 4 demos and the pages into docs/design-library/qa/screenshots/
 *
 *   DL_BASE=http://127.0.0.1:8961 DL_SITE=<site dir> NODE_PATH=<playwright>:<axe-core> node tests/design-library/browser/ui.js
 */
'use strict';
var fs = require('fs');
var path = require('path');
var { chromium, webkit } = require('playwright');
var L = require('./lib.js');
var check = L.check, BASE = L.BASE;
var SHOTS = path.resolve(__dirname, '../../../docs/design-library/qa/screenshots');
fs.mkdirSync(SHOTS, { recursive: true });
var AXE = L.axePath();

function playing(page) { return page.evaluate(function () { return Array.prototype.filter.call(document.querySelectorAll('[data-dl-video]'), function (v) { return !v.paused; }).length; }); }
async function newCtx(browser, o) {
    o = o || {};
    var ctx = await browser.newContext({ viewport: o.vp || { width: 1440, height: 900 }, reducedMotion: o.reduce ? 'reduce' : 'no-preference', colorScheme: o.scheme || 'light', javaScriptEnabled: o.js !== false, isMobile: false });
    await ctx.addInitScript(L.INIT);
    await ctx.addInitScript(function (s) { try { localStorage.setItem('omochix-theme', s); } catch (e) { /* sandboxed demo */ } }, o.scheme || 'light');
    if (o.saveData) await ctx.addInitScript(function () { Object.defineProperty(navigator, 'connection', { value: { saveData: true, effectiveType: '4g', addEventListener: function () {} }, configurable: true }); });
    if (o.noWebGL) await ctx.addInitScript(function () { var g = HTMLCanvasElement.prototype.getContext; HTMLCanvasElement.prototype.getContext = function (t) { return /webgl/.test(t) ? null : g.apply(this, arguments); }; });
    return ctx;
}
async function axe(page, label) {
    if (!AXE) { L.info('axe-core not found on NODE_PATH; skipped ' + label); return; }
    await page.addScriptTag({ path: AXE });
    var r = await page.evaluate(function () { return window.axe.run({ include: [['main']] }, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] }, resultTypes: ['violations'] }).then(function (x) { return x.violations.map(function (v) { return v.impact + ' ' + v.id + ' x' + v.nodes.length; }); }); });
    var serious = r.filter(function (v) { return /^(serious|critical)/.test(v); });
    check(label + ': axe has no serious or critical violation in <main>', serious.length === 0, r);
    if (r.length) L.info(label + ' axe (minor/moderate): ' + r.join(', '));
}

async function listChecks(type, browser) {
    for (var mode of [{ n: 'pc-light', vp: { width: 1440, height: 900 }, scheme: 'light' }, { n: 'pc-dark', vp: { width: 1440, height: 900 }, scheme: 'dark' }, { n: 'phone-light', vp: { width: 390, height: 844 }, scheme: 'light' }, { n: 'phone-dark', vp: { width: 390, height: 844 }, scheme: 'dark' }]) {
        var ctx = await newCtx(browser, mode);
        var page = await ctx.newPage();
        var errors = [];
        page.on('pageerror', function (e) { errors.push(e.message); });
        for (var url of ['/design/', '/design/web/', '/design/web/bento-saas/', '/design/video/', '/design/video/logo-reveal-short/']) {
            await page.goto(BASE + url);
            await L.wait(2200);
            var st = await page.evaluate(function () { return { sw: document.documentElement.scrollWidth, iw: innerWidth, theme: document.documentElement.getAttribute('data-theme') }; });
            var T = type + ' ' + mode.n + ' ' + url;
            check(T + ': no horizontal scroll, theme ' + mode.scheme, st.sw <= st.iw + 1 && st.theme === mode.scheme, st);
            await axe(page, T);
            if (type === 'chromium' || mode.n === 'pc-light' || mode.n === 'phone-dark') {
                await page.screenshot({ path: path.join(SHOTS, type + '-' + mode.n + url.replace(/\//g, '_').replace(/_$/, '') + '.png'), fullPage: url !== '/design/web/bento-saas/' });
            }
        }
        check(type + ' ' + mode.n + ': no page errors', errors.length === 0, errors);
        await ctx.close();
    }

    /* playback */
    var ctx2 = await newCtx(browser, { vp: { width: 1440, height: 900 } });
    var p = await ctx2.newPage();
    await p.goto(BASE + '/design/web/');
    await L.wait(2500);
    var max = 0, samples = [];
    for (var i = 0; i < 25; i++) { var n = await playing(p); samples.push(n); max = Math.max(max, n); if (i % 3 === 0) await p.mouse.wheel(0, i < 12 ? 140 : -140); await L.wait(200); }
    check(type + ' playback: some loop plays and never more than 2 at once (25 samples while scrolling)', max <= 2 && samples.some(function (x) { return x > 0; }), samples);
    await p.evaluate(function () { window.scrollTo(0, document.body.scrollHeight); });
    await L.wait(800);
    var off = await p.evaluate(function () { return Array.prototype.filter.call(document.querySelectorAll('[data-dl-card]'), function (c) { var r = c.getBoundingClientRect(); var v = c.querySelector('video'); return (r.bottom < 0 || r.top > innerHeight) && v && !v.paused; }).length; });
    check(type + ' playback: cards off screen are paused', off === 0, off);
    await p.evaluate(function () { window.scrollTo(0, 0); });
    await L.wait(800);
    await p.evaluate(function () { Object.defineProperty(document, 'hidden', { value: true, configurable: true }); document.dispatchEvent(new Event('visibilitychange')); });
    await L.wait(400);
    check(type + ' playback: a hidden tab pauses every loop', (await playing(p)) === 0);
    await p.evaluate(function () { Object.defineProperty(document, 'hidden', { value: false, configurable: true }); document.dispatchEvent(new Event('visibilitychange')); });
    await L.wait(800);
    check(type + ' playback: visible again, loops resume (≤ 2)', (await playing(p)) >= 1 && (await playing(p)) <= 2);
    var cls = await p.evaluate(function () { return window.__cls; });
    if (cls >= 0) check(type + ' CLS of the list while loops start: ≤ 0.05', cls <= 0.05, cls); else L.info(type + ': layout-shift API not available, CLS not measured');
    // keyboard: the play buttons are reachable and toggle
    await p.focus('[data-dl-play]');
    var focusRing = await p.evaluate(function () { var b = document.activeElement; var cs = getComputedStyle(b); return b.matches('[data-dl-play]') && (parseFloat(cs.outlineWidth) > 0 || cs.boxShadow !== 'none'); });
    var wasPressed = await p.getAttribute('[data-dl-play]', 'aria-pressed');
    await p.keyboard.press('Enter'); await L.wait(500);
    var nowPressed = await p.getAttribute('[data-dl-play]', 'aria-pressed');
    check(type + ' keyboard: play button focusable with a visible ring and toggles aria-pressed', focusRing && wasPressed !== nowPressed, [focusRing, wasPressed, nowPressed]);
    await ctx2.close();

    /* lazy posters load when scrolled to (phone width, all 6 works) */
    var cz = await newCtx(browser, { vp: { width: 390, height: 844 } });
    var pz = await cz.newPage();
    await pz.goto(BASE + '/design/');
    for (var z = 0; z < 30; z++) { await pz.mouse.wheel(0, 400); await L.wait(100); }
    await L.wait(800);
    var posters = await pz.evaluate(function () { return Array.prototype.map.call(document.querySelectorAll('[data-dl-card] img'), function (i) { return i.complete && i.naturalWidth > 0; }); });
    check(type + ' phone: all 8 lazy posters load when scrolled to', posters.length === 8 && posters.every(Boolean), posters);
    await cz.close();

    /* reduced motion and Save-Data: no autoplay, the button still plays */
    for (var o of [{ n: 'reduced motion', reduce: true }, { n: 'Save-Data', saveData: true }]) {
        var c = await newCtx(browser, o);
        var pg = await c.newPage();
        await pg.goto(BASE + '/design/web/');
        await L.wait(2500);
        var auto = await playing(pg);
        await pg.click('[data-dl-play]'); await L.wait(900);
        check(type + ' ' + o.n + ': no autoplay (poster), the play button starts one loop', auto === 0 && (await playing(pg)) === 1, [auto, await playing(pg)]);
        await c.close();
    }

    /* a play the visitor asks for works even when the card is only partly on screen (reduced motion: no autoplay at all) */
    var cp2 = await newCtx(browser, { reduce: true, vp: { width: 1440, height: 640 } });
    var pp = await cp2.newPage();
    await pp.goto(BASE + '/design/web/');
    await pp.evaluate(function () { var r = document.querySelector('[data-dl-card]').getBoundingClientRect(); window.scrollTo(0, Math.max(0, window.scrollY + r.top - innerHeight + r.height * 0.3)); });
    await L.wait(600);
    var part = await pp.evaluate(function () { var r = document.querySelector('[data-dl-card]').getBoundingClientRect(); return Math.round(Math.max(0, Math.min(innerHeight, r.bottom) - Math.max(0, r.top)) / r.height * 100); });
    await pp.click('[data-dl-play]'); await L.wait(900);
    check(type + ' play button works on a card only ' + part + '% on screen', part > 0 && part < 50 && (await playing(pp)) === 1, [part, await playing(pp)]);
    await cp2.close();

    /* video failure keeps the poster */
    var cf = await newCtx(browser, {});
    await cf.route(/\/assets\/design\/loops\//, function (route) { route.abort(); });
    var pf = await cf.newPage();
    await pf.goto(BASE + '/design/web/');
    await L.wait(3000);
    var failState = await pf.evaluate(function () { var cards = document.querySelectorAll('[data-dl-card]'); return Array.prototype.map.call(cards, function (c) { return { playing: c.classList.contains('is-playing'), btnHidden: c.querySelector('[data-dl-play]').hidden, posterShown: c.querySelector('img').getBoundingClientRect().height > 0 }; }); });
    var visibleTried = failState.slice(0, 2);
    check(type + ' video failure: the card stays on its poster and hides the play button', visibleTried.every(function (s) { return !s.playing && s.btnHidden && s.posterShown; }), failState);
    await cf.close();

    /* filters (in place) */
    var cx = await newCtx(browser, {});
    var px = await cx.newPage();
    await px.goto(BASE + '/design/web/');
    function vis() { return px.evaluate(function () { return Array.prototype.filter.call(document.querySelectorAll('[data-dl-card]'), function (c) { return !c.hidden; }).map(function (c) { return c.querySelector('.dl-card__title').textContent.trim(); }); }); }
    await px.selectOption('#dl-motion_type', 'ambient'); await L.wait(200);
    check(type + ' filter motion_type=ambient -> Liquid Glass and Aurora Gradient, URL synced', JSON.stringify(await vis()) === '["Liquid Glass","Aurora Gradient"]' && px.url().indexOf('motion_type=ambient') !== -1);
    await px.selectOption('#dl-motion_type', ''); await px.selectOption('#dl-technology', 'webgl'); await L.wait(200);
    check(type + ' filter technology=webgl -> 3D Interactive', JSON.stringify(await vis()) === '["3D Interactive"]');
    await px.selectOption('#dl-technology', ''); await px.selectOption('#dl-use_case', 'saas'); await L.wait(200);
    check(type + ' filter use_case=saas -> Bento SaaS and Aurora Gradient', JSON.stringify(await vis()) === '["Bento SaaS","Aurora Gradient"]');
    await px.selectOption('#dl-use_case', ''); await px.selectOption('#dl-industry', 'beauty'); await L.wait(200);
    check(type + ' filter industry=beauty -> Minimal Editorial', JSON.stringify(await vis()) === '["Minimal Editorial"]');
    await px.selectOption('#dl-industry', '');
    await px.fill('#dl-q', 'ガラス 3D'); await L.wait(400);
    check(type + ' keyword AND with no match shows the empty state and "0件"', (await vis()).length === 0 && !(await px.isHidden('[data-dl-empty]')) && (await px.textContent('[data-dl-count]')).indexOf('0件') === 0);
    await px.click('[data-dl-reset]'); await L.wait(200);
    check(type + ' reset shows all 6 and clears the URL', (await vis()).length === 6 && px.url() === BASE + '/design/web/');
    // keyboard only
    await px.focus('#dl-q');
    await px.keyboard.type('カウント'); await L.wait(400);
    var kb1 = await vis();
    await px.keyboard.press('Tab');
    var focused = await px.evaluate(function () { return document.activeElement.id; });
    check(type + ' keyboard: typing filters in place, Tab moves on to the use-case select', JSON.stringify(kb1) === '["Bento SaaS"]' && focused === 'dl-use_case', [kb1, focused]);
    // top page: Web and video together, sections follow the filters
    await px.goto(BASE + '/design/');
    function secs() { return px.evaluate(function () { return Array.prototype.map.call(document.querySelectorAll('[data-dl-section]'), function (s) { return s.hidden ? 0 : 1; }).join(''); }); }
    check(type + ' top: 8 works in two sections', (await vis()).length === 8 && (await secs()) === '11');
    await px.selectOption('#dl-medium', 'video'); await L.wait(200);
    check(type + ' top medium=video -> the 2 video works, Web section hidden', JSON.stringify(await vis()) === '["Logo Reveal Short","Data Chart Explainer"]' && (await secs()) === '01' && px.url().indexOf('medium=video') !== -1);
    await px.selectOption('#dl-medium', ''); await px.selectOption('#dl-motion_type', 'chart'); await L.wait(200);
    check(type + ' top motion_type=chart -> Bento SaaS and Data Chart Explainer, both sections shown', JSON.stringify(await vis()) === '["Bento SaaS","Data Chart Explainer"]' && (await secs()) === '11');
    await px.selectOption('#dl-motion_type', ''); await px.fill('#dl-q', 'ロゴ'); await L.wait(400);
    check(type + ' top keyword ロゴ -> Logo Reveal Short only', JSON.stringify(await vis()) === '["Logo Reveal Short"]' && (await secs()) === '01');
    await cx.close();

    /* video detail: nothing loads or plays until asked; controls work; copy buttons */
    var cv = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    if (type === 'chromium') await cv.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: BASE });
    var vreq = [];
    cv.on('request', function (r) { if (/\/assets\/design\/videos\//.test(r.url())) vreq.push(r.url()); });
    var pv = await cv.newPage();
    await pv.goto(BASE + '/design/video/data-chart-explainer/');
    await L.wait(1500);
    var before = await pv.evaluate(function () { var v = document.querySelector('.dl-stage__video'); return { paused: v.paused, t: v.currentTime, controls: v.controls, autoplay: v.autoplay }; });
    check(type + ' video detail: no autoplay, controls shown, the full video is not downloaded before play', before.paused && before.t === 0 && before.controls && !before.autoplay && vreq.length === 0, [before, vreq]);
    await pv.evaluate(function () { return document.querySelector('.dl-stage__video').play(); });
    await L.wait(1500);
    var after = await pv.evaluate(function () { var v = document.querySelector('.dl-stage__video'); return { paused: v.paused, t: v.currentTime, w: v.videoWidth, h: v.videoHeight }; });
    check(type + ' video detail: plays when asked (1920x1080)', !after.paused && after.t > 0.3 && after.w === 1920 && after.h === 1080, after);
    var copyBtn = pv.locator('[data-dl-copy="dl-prompt-text"]');
    var copyVisible = await copyBtn.isVisible();
    await copyBtn.click(); await L.wait(300);
    var copyText = await copyBtn.textContent();
    var clip = type === 'chromium' ? await pv.evaluate(function () { return navigator.clipboard.readText(); }) : null;
    var prompt = await pv.textContent('#dl-prompt-text');
    check(type + ' copy button: visible with JS, reports the result' + (clip !== null ? ', clipboard holds the prompt' : ''), copyVisible && /コピーしました|コピーできませんでした/.test(copyText) && (clip === null || clip === prompt), [copyVisible, copyText]);
    check(type + ' video detail: no create / generate / Motion Studio control', (await pv.locator('text=/Motion Studio で作る|この作品を作る|生成する/').count()) === 0 && (await pv.locator('[data-dl-motion-studio]').count()) === 0);
    await cv.close();

    /* no JavaScript */
    var cn = await newCtx(browser, { js: false });
    var pn = await cn.newPage();
    await pn.goto(BASE + '/design/web/');
    check(type + ' no JS: 6 posters, no video playing', (await pn.locator('[data-dl-card] img').count()) === 6 && (await pn.locator('[data-dl-card]:not([hidden])').count()) === 6);
    check(type + ' no JS: no play button is shown (it could not do anything)', (await pn.locator('[data-dl-play]:visible').count()) === 0);
    await pn.selectOption('#dl-use_case', 'saas');
    await Promise.all([pn.waitForNavigation(), pn.click('[data-dl-submit]')]);
    check(type + ' no JS: the filter form submits and the server filters (Bento SaaS and Aurora Gradient)', pn.url().indexOf('use_case=saas') !== -1 && (await pn.locator('[data-dl-card]:not([hidden])').count()) === 2);
    await pn.goto(BASE + '/design/video/logo-reveal-short/');
    check(type + ' no JS: the video detail keeps a playable <video controls> and hides the copy buttons', (await pn.locator('video.dl-stage__video[controls]').count()) === 1 && (await pn.locator('[data-dl-copy]:visible').count()) === 0);
    await pn.goto(BASE + '/design/web/3d-interactive/');
    await L.wait(1500);
    var fr = pn.frames().filter(function (f) { return f.url().indexOf('/design/demo/web/3d-interactive/') !== -1; })[0];
    var fb = fr ? await fr.evaluate(function () { var e = document.querySelector('.kd-fallback'); return e && getComputedStyle(e).display !== 'none' && document.querySelector('.kd-fallback img').complete; }) : false;
    check(type + ' no JS: the 3D demo frame shows its poster fallback and description', fb === true);
    for (var s of L.DEMOS) {
        await pn.goto(BASE + '/design/demo/web/' + s + '/');
        var nojs = await pn.evaluate(function (slug) {
            var t = document.body.innerText;
            if (slug === 'minimal-editorial') return document.querySelectorAll('.ed-page').length === 3 && Array.prototype.every.call(document.querySelectorAll('.ed-page, .ed-line'), function (e) { return getComputedStyle(e).display !== 'none' && getComputedStyle(e).opacity === '1'; });
            if (slug === 'bento-saas') return t.indexOf('1,240') !== -1 && t.indexOf('99.9') !== -1 && Array.prototype.every.call(document.querySelectorAll('.bn-tile'), function (e) { return getComputedStyle(e).opacity === '1'; });
            if (slug === 'dark-luxury') return t.indexOf('静けさを') !== -1 && document.querySelectorAll('.dk-card').length === 4 && Array.prototype.every.call(document.querySelectorAll('[data-dk-reveal], .dk-line'), function (e) { return getComputedStyle(e).opacity === '1'; });
            if (slug === 'aurora-gradient') return getComputedStyle(document.querySelector('.au-static')).opacity === '1' && getComputedStyle(document.querySelector('.au-canvas')).opacity === '0' && Array.prototype.every.call(document.querySelectorAll('[data-au-reveal], .au-line'), function (e) { return getComputedStyle(e).opacity === '1'; });
            if (slug === 'liquid-glass') return getComputedStyle(document.querySelector('.lg-blob--1')).animationName === 'none' && t.indexOf('考えごとを') !== -1;
            return getComputedStyle(document.querySelector('.kd-fallback')).display !== 'none';
        }, s);
        check(type + ' no JS: demo ' + s + ' is complete and static', nojs === true);
    }
    await cn.close();
}

async function demoChecks(type, browser) {
    /* phone width */
    var c = await newCtx(browser, { vp: { width: 390, height: 844 } });
    var p = await c.newPage();
    for (var s of L.DEMOS) {
        await p.goto(BASE + '/design/demo/web/' + s + '/');
        await L.wait(1800);
        var st = await p.evaluate(function () { return { sw: document.documentElement.scrollWidth, iw: innerWidth }; });
        check(type + ' demo ' + s + ' at 390 px: no horizontal scroll', st.sw <= st.iw + 1, st);
        if (type === 'chromium') await p.screenshot({ path: path.join(SHOTS, 'demo-' + s + '-phone.png') });
    }
    await c.close();
    /* desktop screenshots + hidden tab stops frames */
    var cd = await newCtx(browser, { vp: { width: 1280, height: 800 } });
    var pd = await cd.newPage();
    for (var s2 of L.DEMOS) {
        await pd.goto(BASE + '/design/demo/web/' + s2 + '/');
        await L.wait(s2 === '3d-interactive' ? 3200 : 2400);
        await pd.screenshot({ path: path.join(SHOTS, type + '-demo-' + s2 + '.png') });
        await pd.evaluate(function () { Object.defineProperty(document, 'hidden', { value: true, configurable: true }); document.dispatchEvent(new Event('visibilitychange')); });
        await L.wait(500);
        var r0 = await pd.evaluate(function () { return window.__raf; });
        await L.wait(1200);
        var r1 = await pd.evaluate(function () { return window.__raf; });
        check(type + ' demo ' + s2 + ': no animation frames while hidden', r1 - r0 === 0, r1 - r0);
        var paused = await pd.evaluate(function () { var a = document.getAnimations ? document.getAnimations().filter(function (x) { return x.playState === 'running'; }).length : 0; return { running: a, pausedClass: document.documentElement.classList.contains('paused') }; });
        if (s2 === 'liquid-glass') check(type + ' demo liquid-glass: CSS animations pause while hidden', paused.running === 0 && paused.pausedClass, paused);
    }
    await cd.close();
    /* reduced motion inside the demos (D0 §15.2) */
    var cr = await newCtx(browser, { reduce: true, vp: { width: 1280, height: 800 } });
    var pr = await cr.newPage();
    await pr.goto(BASE + '/design/demo/web/liquid-glass/'); await L.wait(800);
    check(type + ' reduced: Liquid Glass background and highlight are still', await pr.evaluate(function () { return getComputedStyle(document.querySelector('.lg-blob--1')).animationName === 'none' && getComputedStyle(document.querySelector('.lg-spot')).opacity === '0' && getComputedStyle(document.querySelector('.lg-float')).animationName === 'none'; }));
    await pr.goto(BASE + '/design/demo/web/minimal-editorial/'); await L.wait(300);
    check(type + ' reduced: Editorial headline shown at once, lines fully drawn', await pr.evaluate(function () { return Array.prototype.every.call(document.querySelectorAll('.ed-line'), function (e) { return getComputedStyle(e).opacity === '1'; }) && document.querySelector('.ed-rule').style.getPropertyValue('--p') === '1'; }));
    await pr.click('[data-ed-next]');
    check(type + ' reduced: Editorial page turn is immediate (no animation)', await pr.evaluate(function () { var p = document.querySelector('.ed-page.is-current'); return !p.classList.contains('is-entering') && document.querySelector('[data-ed-index]').textContent === '2'; }));
    await pr.goto(BASE + '/design/demo/web/bento-saas/'); await L.wait(300);
    check(type + ' reduced: Bento numbers in their final value, tiles visible', await pr.evaluate(function () { return document.body.innerText.indexOf('1,240') !== -1 && Array.prototype.every.call(document.querySelectorAll('.bn-tile'), function (e) { return getComputedStyle(e).opacity === '1'; }); }));
    await pr.goto(BASE + '/design/demo/web/3d-interactive/'); await L.wait(1500);
    var a0 = await pr.evaluate(function () { return window.__raf; }); await L.wait(1000); var a1 = await pr.evaluate(function () { return window.__raf; });
    check(type + ' reduced: 3D does not rotate or animate on its own (no frames when idle)', a1 - a0 === 0, a1 - a0);
    var box = await pr.locator('.kd-canvas').boundingBox();
    if (box) {
        await pr.mouse.move(box.x + box.width / 2, box.y + box.height / 2); await pr.mouse.down(); await pr.mouse.move(box.x + box.width / 2 + 120, box.y + box.height / 2, { steps: 8 }); await pr.mouse.up();
        var a2 = await pr.evaluate(function () { return window.__raf; });
        check(type + ' reduced: 3D still rotates by drag', a2 > a1, a2 - a1);
    }
    await pr.focus('.kd-canvas'); var k0 = await pr.evaluate(function () { return window.__raf; }); await pr.keyboard.press('ArrowRight'); await L.wait(200);
    check(type + ' keyboard: 3D model rotates with the arrow keys', (await pr.evaluate(function () { return window.__raf; })) > k0);
    await cr.close();
    /* no WebGL */
    var cg = await newCtx(browser, { noWebGL: true, vp: { width: 1280, height: 800 } });
    var pg = await cg.newPage();
    await pg.goto(BASE + '/design/demo/web/3d-interactive/'); await L.wait(800);
    check(type + ' no WebGL: 3D Interactive shows the poster and the explanation', await pg.evaluate(function () { return !document.documentElement.classList.contains('gl') && getComputedStyle(document.querySelector('.kd-fallback')).display !== 'none' && document.querySelector('.kd-fallback img').naturalWidth > 0; }));
    await cg.close();
}

(async function () {
    L.flag(false);
    for (var [type, launcher] of [['chromium', chromium], ['webkit', webkit]]) {
        var browser = await launcher.launch(type === 'chromium' ? { args: L.GL_ARGS } : {});
        await listChecks(type, browser);
        await demoChecks(type, browser);
        await browser.close();
    }
    process.exit(L.summary('ui (chromium, webkit)'));
})().catch(function (e) { console.error(e); process.exit(1); });
