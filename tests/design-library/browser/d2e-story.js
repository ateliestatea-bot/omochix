#!/usr/bin/env node
/**
 * Design Library D2-E: Interactive Storytelling (local THROWAWAY site), Chromium, WebKit and Firefox.
 * Generic checks (CSP in the frame, contrast, axe, restart) are in new-works.js; this file checks the story.
 *
 *   DL_BASE=... DL_SITE=... NODE_PATH=<playwright> node tests/design-library/browser/d2e-story.js
 *
 *  - direct, 1440 / 834 / 390: the illustration follows the paragraph in the middle of the screen (one scene shown,
 *    the paragraph marked, chapter colour and header label), the illustration stays fixed while scrolling,
 *    reading progress grows to the end, no side scroll, no CSP violation, no page error, no outside request
 *  - voice switch: the other voice is shown and the current paragraph stays in place; the change is announced
 *  - contents dialog: focus inside, Tab stays inside, Esc returns focus; choosing a chapter moves focus to its heading;
 *    "次の章へ" moves focus to the next chapter
 *  - hidden: scrolling while hidden changes nothing and draws no frame; the latest paragraph applies on return
 *  - embedded (detail page) at 1440 / 834 / 390: mouse-wheel scrolling inside the frame moves the story;
 *    pause holds the scene while the frame scrolls, play applies it
 *  - reduced motion, JavaScript off, short landscape: no fixed illustration, 13 illustrations in the text;
 *    Save-Data: changes are immediate; without JavaScript the voice switch still works (CSS :has)
 */
'use strict';
var { chromium, webkit, firefox } = require('playwright');
var L = require('./lib.js');
var check = L.check, BASE = L.BASE;
var URL_DEMO = BASE + '/design/demo/web/interactive-story/';

function to(target, n) { return target.evaluate(function (k) { var e = document.querySelector('.sk-step[data-step="' + k + '"]'); window.scrollTo(0, e.getBoundingClientRect().top + window.scrollY - window.innerHeight * 0.45); }, n); }
function scene(target) {
    return target.evaluate(function () {
        var on = Array.prototype.filter.call(document.querySelectorAll('.sk-u'), function (u) { return u.classList.contains('is-on'); }).map(function (u) { return Number(u.getAttribute('data-n')); });
        var act = Array.prototype.filter.call(document.querySelectorAll('.sk-step'), function (s) { return s.classList.contains('is-active'); }).map(function (s) { return Number(s.getAttribute('data-step')); });
        return { on: on, act: act, ch: document.querySelector('[data-sk]').getAttribute('data-ch'), label: document.querySelector('[data-sk-chapter]').textContent, raf: window.__raf };
    });
}
var CH = { 1: '1', 5: '2', 8: '3', 13: '4' };

async function direct(type, browser) {
    for (var vp of [[1440, 900], [834, 1112], [390, 844]]) {
        var T = type + ' ' + vp[0] + 'px';
        var c = await browser.newContext({ viewport: { width: vp[0], height: vp[1] } });
        await c.addInitScript(L.INIT);
        var outside = [], errs = [];
        c.on('request', function (r) { var u = r.url(); if (u.indexOf(URL_DEMO) !== 0 && !/^(data|blob|about):/.test(u)) outside.push(u); });
        var p = await c.newPage();
        p.on('pageerror', function (e) { errs.push(e.message); });
        await p.goto(URL_DEMO); await L.wait(500);
        var seen = [], tops = [];
        for (var n of [1, 5, 8, 13]) {
            await to(p, n); await L.wait(1100);
            var s = await scene(p);
            seen.push(s.on.length === 1 && s.on[0] === n && s.act.length === 1 && s.act[0] === n && s.ch === CH[n] && s.label.indexOf('第' + CH[n] + '章') === 0);
            tops.push(await p.evaluate(function () { return Math.round(document.querySelector('[data-sk-stage]').getBoundingClientRect().top); }));
        }
        check(T + ': the illustration follows the paragraph (one scene, paragraph marked, chapter colour and label) for scenes 1 / 5 / 8 / 13', seen.every(Boolean), seen);
        check(T + ': the illustration stays fixed while the text scrolls', tops.slice(1).every(function (t) { return Math.abs(t - tops[1]) <= 1; }) && tops[1] >= 50 && tops[1] <= 80, tops);
        await p.evaluate(function () { window.scrollTo(0, document.documentElement.scrollHeight); }); await L.wait(400);
        var pr = await p.evaluate(function () { return new DOMMatrix(getComputedStyle(document.querySelector('[data-sk-progress]')).transform).a; });
        var st = await p.evaluate(function () { return { sw: document.documentElement.scrollWidth, iw: innerWidth, csp: window.__csp }; });
        check(T + ': reading progress reaches the end; no side scroll, no CSP violation, no page error, no outside request', pr > 0.98 && st.sw <= st.iw + 1 && st.csp.length === 0 && errs.length === 0 && outside.length === 0, { pr: pr, st: st, errs: errs, outside: outside });
        if (vp[0] === 1440 || vp[0] === 390) {
            /* voice switch keeps the paragraph in place */
            await to(p, 5); await L.wait(900);
            var before = await p.evaluate(function () { return document.querySelector('.sk-step[data-step="5"]').getBoundingClientRect().top; });
            await p.evaluate(function () { var r = document.getElementById('sk-v-paper'); r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); });
            await L.wait(300);
            var v = await p.evaluate(function (b) {
                var s = document.querySelector('.sk-step[data-step="5"]');
                return { moved: Math.round(Math.abs(s.getBoundingClientRect().top - b)), paper: getComputedStyle(s.querySelector('.sk-p--paper')).display, craft: getComputedStyle(s.querySelector('.sk-p--craft')).display, said: document.querySelector('[data-sk-status]').textContent };
            }, before);
            check(T + ': switching to the paper voice shows it in place of the craftsman, keeps the paragraph within 4 px and says so', v.paper === 'block' && v.craft === 'none' && v.moved <= 4 && v.said === '紙の目で読んでいます', v);
            await p.evaluate(function () { var r = document.getElementById('sk-v-craft'); r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); });
        }
        if (vp[0] === 1440) {
            /* contents dialog and next-chapter links */
            await to(p, 5); await L.wait(900);
            var y0 = await p.evaluate(function () { return window.scrollY; });
            await p.evaluate(function () { document.querySelector('[data-sk-toc]').click(); }); await L.wait(200);
            var d1 = await p.evaluate(function () { var d = document.querySelector('[data-sk-sheet]'); return { open: !d.hidden, modal: d.getAttribute('aria-modal'), here: document.activeElement.getAttribute('data-sk-go'), inside: d.contains(document.activeElement) }; });
            for (var k = 0; k < 7; k++) await p.keyboard.press('Tab');
            var trapped = await p.evaluate(function () { return document.querySelector('[data-sk-sheet]').contains(document.activeElement); });
            await p.keyboard.press('Escape'); await L.wait(100);
            var d2 = await p.evaluate(function () { return { closed: document.querySelector('[data-sk-sheet]').hidden, back: document.activeElement.hasAttribute('data-sk-toc'), y: window.scrollY }; });
            check(T + ': contents opens as a modal on the current chapter, Tab stays inside, Esc closes and returns focus without moving the page', d1.open && d1.modal === 'true' && d1.here === 'sk-ch2' && trapped && d2.closed && d2.back && Math.abs(d2.y - y0) <= 1, [d1, trapped, d2, y0]);
            await p.click('[data-sk-toc]'); await L.wait(150);
            await p.click('[data-sk-go="sk-ch3"]'); await L.wait(1500);
            var g = await p.evaluate(function () { var h = document.getElementById('sk-ch3-h'), r = h.getBoundingClientRect(); return { focus: document.activeElement === h, inView: r.top >= 0 && r.top < innerHeight / 2, closed: document.querySelector('[data-sk-sheet]').hidden }; });
            check(T + ': choosing chapter 3 closes the contents and moves focus to its heading, in view', g.focus && g.inView && g.closed, g);
            await p.focus('#sk-ch1 [data-sk-next]'); await p.keyboard.press('Enter'); await L.wait(1500);
            check(T + ': "次の章へ" (Enter) moves focus to the next chapter heading', await p.evaluate(function () { return document.activeElement.id === 'sk-ch2-h'; }));
            await p.click('[data-sk-visit]');
            check(T + ': the visit button answers in a status line (nothing is sent)', /架空の工房/.test(await p.textContent('[data-sk-visit-msg]')));
            /* hidden: nothing changes and no frame is drawn; the latest paragraph applies on return */
            await to(p, 3); await L.wait(1000);
            await p.evaluate(function () { Object.defineProperty(document, 'hidden', { value: true, configurable: true }); document.dispatchEvent(new Event('visibilitychange')); });
            var r0 = (await scene(p)).raf;
            await to(p, 9); await L.wait(900);
            var h1 = await scene(p);
            await p.evaluate(function () { Object.defineProperty(document, 'hidden', { value: false, configurable: true }); document.dispatchEvent(new Event('visibilitychange')); });
            await L.wait(300);
            var h2 = await scene(p);
            check(T + ': while hidden the scene stays (3) and no frame is drawn; on return scene 9 applies', h1.on[0] === 3 && h1.raf - r0 === 0 && h2.on[0] === 9 && h2.act[0] === 9, [h1.on, h1.raf - r0, h2.on]);
        }
        await c.close();
    }
}

async function embedded(type, browser) {
    for (var vp of [[1440, 900], [834, 1112], [390, 844]]) {
        var T = type + ' embedded ' + vp[0] + 'px';
        var c = await browser.newContext({ viewport: { width: vp[0], height: vp[1] } });
        await c.addInitScript(L.INIT);
        var p = await c.newPage();
        await p.goto(BASE + '/design/web/interactive-story/');
        await p.locator('[data-dl-stage]').scrollIntoViewIfNeeded();
        var f = null;
        for (var i = 0; i < 80 && !f; i++) { f = p.frames().filter(function (fr) { return fr.url().indexOf('/design/demo/web/interactive-story/') !== -1; })[0]; if (!f) await L.wait(100); }
        for (var j = 0; j < 50; j++) { try { if (await f.evaluate(function () { return !!window.omxDemo; })) break; } catch (e) { /* loading */ } await L.wait(100); }
        var box = await p.locator('[data-dl-stage] iframe').boundingBox();
        await p.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
        var y0 = await f.evaluate(function () { return window.scrollY; });
        for (var k = 0; k < 12; k++) { await p.mouse.wheel(0, 400); await L.wait(120); }
        await L.wait(1200);
        var s = await f.evaluate(function () { return { y: window.scrollY, on: Array.prototype.filter.call(document.querySelectorAll('.sk-u'), function (u) { return u.classList.contains('is-on'); }).map(function (u) { return Number(u.getAttribute('data-n')); }) }; });
        check(T + ': mouse-wheel scrolling inside the frame moves the story and its scene', s.y > y0 + 1000 && s.on.length === 1 && s.on[0] > 1, [y0, s]);
        if (vp[0] === 1440) {
            await p.click('[data-dl-toggle]'); await L.wait(300);
            var before = (await scene(f)).on[0];
            await to(f, 12); await L.wait(1000);
            var held = (await scene(f)).on[0];
            await p.click('[data-dl-toggle]'); await L.wait(500);
            var after = (await scene(f)).on[0];
            check(T + ': pause holds the scene while the frame scrolls; play applies the current paragraph', held === before && after === 12, [before, held, after]);
            check(T + ': no CSP violation inside the frame', (await f.evaluate(function () { return window.__csp.length; })) === 0);
        }
        await c.close();
    }
}

async function fallbacks(type, browser) {
    var T = type;
    async function layout(opts, label) {
        var c = await browser.newContext(Object.assign({ viewport: { width: 1440, height: 900 } }, opts));
        var p = await c.newPage();
        await p.goto(URL_DEMO); await L.wait(600);
        var r = await p.evaluate(function () {
            var figs = Array.prototype.filter.call(document.querySelectorAll('.sk-fig'), function (f) { return getComputedStyle(f).display !== 'none' && f.getBoundingClientRect().height > 40; });
            return { stage: getComputedStyle(document.querySelector('[data-sk-stage]')).display, figs: figs.length, uses: figs.every(function (f) { return /^#sk-s\d+$/.test(f.querySelector('use').getAttribute('href')); }), craft: Array.prototype.every.call(document.querySelectorAll('.sk-p--craft'), function (e) { return getComputedStyle(e).display === 'block'; }), anims: document.getAnimations ? document.getAnimations().length : 0, sw: document.documentElement.scrollWidth <= innerWidth + 1, toc: document.querySelectorAll('.sk-toc a').length };
        });
        check(T + ' ' + label + ': no fixed illustration; 13 illustrations in the text; every paragraph readable; contents links; no side scroll', r.stage === 'none' && r.figs === 13 && r.uses && r.craft && r.toc === 4 && r.sw, r);
        return { c: c, p: p, r: r };
    }
    var red = await layout({ reducedMotion: 'reduce' }, 'reduced motion');
    check(T + ' reduced motion: no animation runs', red.r.anims === 0, red.r.anims);
    await red.c.close();
    var nj = await layout({ javaScriptEnabled: false }, 'no JavaScript');
    await nj.p.click('label[for="sk-v-paper"]'); await L.wait(100);
    var css = await nj.p.evaluate(function () { var s = document.querySelector('.sk-step[data-step="2"]'); return getComputedStyle(s.querySelector('.sk-p--paper')).display + '/' + getComputedStyle(s.querySelector('.sk-p--craft')).display + '/' + document.querySelector('[data-sk-toc]').hidden; });
    check(T + ' no JavaScript: the voice switch still works (CSS :has); the contents button is not offered', css === 'block/none/true', css);
    await nj.c.close();
    var ls = await layout({ viewport: { width: 844, height: 390 } }, 'short landscape (844×390)');
    await ls.c.close();
    var cs = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    await cs.addInitScript(L.INIT);
    await cs.addInitScript(function () { Object.defineProperty(navigator, 'connection', { value: { saveData: true }, configurable: true }); });
    var ps = await cs.newPage();
    await ps.goto(URL_DEMO); await L.wait(500);
    await to(ps, 7); await L.wait(500);
    var sd = await ps.evaluate(function () { var u = document.querySelector('.sk-u[data-n="7"]'); return { instant: document.documentElement.classList.contains('sk-instant'), dur: getComputedStyle(u).transitionDuration, on: u.classList.contains('is-on') }; });
    check(T + ' Save-Data: the scene changes immediately (no transition)', sd.instant && /^0s(, 0s)*$/.test(sd.dur) && sd.on, sd);
    await cs.close();
}

(async function () {
    L.flag(true);
    try {
        for (var pair of [['chromium', chromium], ['webkit', webkit], ['firefox', firefox]]) {
            var b = await pair[1].launch();
            await direct(pair[0], b);
            await embedded(pair[0], b);
            await fallbacks(pair[0], b);
            await b.close();
        }
    } finally { L.flag(false); }
    process.exit(L.summary('D2-E story (chromium, webkit, firefox)'));
})().catch(function (e) { console.error(e); L.flag(false); process.exit(1); });
