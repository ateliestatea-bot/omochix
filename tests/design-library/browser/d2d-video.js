#!/usr/bin/env node
/**
 * Design Library D2-D: the 1:1 video work "brand-typography" (local THROWAWAY site), Chromium, WebKit and Firefox.
 *
 *   DL_BASE=... DL_SITE=... NODE_PATH=<playwright>:<axe-core> node tests/design-library/browser/d2d-video.js
 *
 *  - files: ffprobe — 1080×1080, 30 fps, 10 s, H.264 yuv420p / VP9, no audio track, no location or creator tags
 *  - text colours of the video (ink on paper / green / yellow) are ≥ 4.5:1 (palette values)
 *  - list card: "10 秒 · 1:1 正方形", the whole frame is shown (contain), play / pause / resume of the loop
 *  - reduced motion and Save-Data: the loop does not start by itself
 *  - detail page: square stage, <video controls preload="none">, plays, pauses, resumes from the same point
 *  - no request outside the site, no CSP violation, no page error, no side scroll at 1440 / 834 / 390
 *  - axe (WCAG 2.2 A/AA) on the detail page, light and dark: no serious / critical violation
 *  - JavaScript off: card label, poster and the detail page's <video controls> are there
 */
'use strict';
var fs = require('fs');
var cp = require('child_process');
var path = require('path');
var { chromium, webkit, firefox } = require('playwright');
var L = require('./lib.js');
var check = L.check, BASE = L.BASE;
var AXE = L.axePath();
var AXE_SRC = AXE ? fs.readFileSync(AXE, 'utf8') : '';
var SLUG = 'brand-typography';
var ASSETS = path.join(L.THEME, 'assets/design');

function probe(file) {
    var r = cp.spawnSync('ffprobe', ['-v', 'error', '-show_entries', 'stream=codec_type,codec_name,width,height,r_frame_rate,pix_fmt:format=duration:format_tags:stream_tags', '-of', 'json', file], { encoding: 'utf8' });
    return JSON.parse(r.stdout || '{}');
}
function lum(hex) { var c = [1, 3, 5].map(function (i) { var v = parseInt(hex.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; }
function ratio(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }

function files() {
    ['videos/' + SLUG + '.mp4', 'videos/' + SLUG + '.webm', 'loops/' + SLUG + '.mp4', 'loops/' + SLUG + '.webm'].forEach(function (f) {
        var j = probe(path.join(ASSETS, f));
        var v = (j.streams || []).filter(function (s) { return s.codec_type === 'video'; })[0] || {};
        var audio = (j.streams || []).some(function (s) { return s.codec_type === 'audio'; });
        var size = f.indexOf('videos/') === 0 ? 1080 : 960;
        var tags = Object.keys((j.format && j.format.tags) || {}).concat(Object.keys(v.tags || {})).join(' ');
        check('ffprobe ' + f + ': ' + size + '×' + size + ', 30 fps, 10 s, no audio, no location / creator tags', v.width === size && v.height === size && v.r_frame_rate === '30/1' && Math.abs(Number(j.format.duration) - 10) < 0.1 && !audio && !/location|artist|author|creation_time|comment/i.test(tags) && (f.slice(-4) !== '.mp4' || (v.codec_name === 'h264' && v.pix_fmt === 'yuv420p')), { v: v, d: j.format && j.format.duration, tags: tags });
    });
    var kb = Math.round(fs.statSync(path.join(ASSETS, 'videos/' + SLUG + '.mp4')).size / 1024);
    check('file size: full MP4 ≤ 1.5 MB (' + kb + ' KB)', kb <= 1536);
    var ink = '#12352b';
    var r = ['#f2ecdf', '#3cc47c', '#ffd23f'].map(function (bg) { return Math.round(ratio(ink, bg) * 100) / 100; });
    check('text colours: ink on paper / green / yellow ≥ 4.5:1 (' + r.join(', ') + ')', r.every(function (x) { return x >= 4.5; }), r);
}

async function run(type, launcher) {
    var browser = await launcher.launch();
    var T = type + ' ' + SLUG;
    for (var w of [[1440, 900], [834, 1112], [390, 844]]) {
        var c = await browser.newContext({ viewport: { width: w[0], height: w[1] } });
        await c.addInitScript(L.INIT);
        var outside = [], errs = [];
        c.on('request', function (r) { var u = r.url(); if (u.indexOf(BASE + '/') !== 0 && !/^(data|blob|about):/.test(u)) outside.push(u); });
        var p = await c.newPage();
        p.on('pageerror', function (e) { errs.push(e.message); });
        await p.goto(BASE + '/design/video/');
        var card = p.locator('[data-dl-card]:has(#dl-card-' + SLUG + ')');
        await card.scrollIntoViewIfNeeded(); await L.wait(600);
        var cs = await p.evaluate(function (slug) {
            var c = document.getElementById('dl-card-' + slug).closest('[data-dl-card]');
            var img = c.querySelector('.dl-card__media picture:not(.dl-card__backdrop) img');
            return { facts: Array.prototype.map.call(c.querySelectorAll('.dl-card__fact'), function (f) { return f.textContent.trim(); }).join('|'), fit: getComputedStyle(img).objectFit, label: !!c.querySelector('.dl-card__body .dl-card__medium'), sw: document.documentElement.scrollWidth, iw: innerWidth };
        }, SLUG);
        check(T + ' ' + w[0] + 'px card: "10 秒 · 1:1 正方形", whole frame (contain), label in the body, no side scroll', cs.facts === '10 秒|1:1 正方形' && cs.fit === 'contain' && cs.label && cs.sw <= cs.iw + 1, cs);
        if (w[0] === 1440) {
            var btn = card.locator('[data-dl-play]');
            var st = async function () { return p.evaluate(function (slug) { var v = document.getElementById('dl-card-' + slug).closest('[data-dl-card]').querySelector('video'); return v ? { paused: v.paused, t: v.currentTime } : null; }, SLUG); };
            if ((await btn.getAttribute('aria-pressed')) === 'true') { await btn.click(); await L.wait(300); }
            await btn.click(); await L.wait(1500);
            var a = await st();
            await btn.click(); await L.wait(400);
            var b = await st(); await L.wait(600); var b2 = await st();
            await btn.click(); await L.wait(1200);
            var d = await st();
            check(T + ' card loop: play, pause (time holds), resume (time continues)', a && !a.paused && a.t > 0.3 && b.paused && Math.abs(b2.t - b.t) < 0.05 && !d.paused && d.t > b.t, [a, b, b2, d]);
        }
        /* detail page */
        await p.goto(BASE + '/design/video/' + SLUG + '/');
        var ds = await p.evaluate(function () {
            var s = document.querySelector('[data-dl-stage]'), v = s.querySelector('video'), r = s.querySelector('.dl-stage__view').getBoundingClientRect();
            return { square: s.classList.contains('dl-stage--square'), ratio: Math.round(r.width / r.height * 100) / 100, h: r.height, vh: innerHeight, controls: v.controls, preload: v.preload, sw: document.documentElement.scrollWidth, iw: innerWidth };
        });
        check(T + ' ' + w[0] + 'px detail: square stage (1:1, ≤ 80vh), <video controls preload=none>, no side scroll', ds.square && Math.abs(ds.ratio - 1) < 0.02 && ds.h <= ds.vh * 0.8 + 1 && ds.controls && ds.preload === 'none' && ds.sw <= ds.iw + 1, ds);
        if (w[0] === 1440) {
            var vs = async function () { return p.evaluate(function () { var v = document.querySelector('.dl-stage__video'); return { paused: v.paused, t: Math.round(v.currentTime * 100) / 100, ready: v.readyState }; }); };
            await p.evaluate(function () { var v = document.querySelector('.dl-stage__video'); v.muted = true; return v.play(); });
            await L.wait(1500); var v1 = await vs();
            await p.evaluate(function () { document.querySelector('.dl-stage__video').pause(); }); await L.wait(600); var v2 = await vs();
            await L.wait(600); var v3 = await vs();
            await p.evaluate(function () { return document.querySelector('.dl-stage__video').play(); }); await L.wait(1000); var v4 = await vs();
            check(T + ' detail video: plays, pauses (time holds), resumes from the same point', !v1.paused && v1.t > 0.5 && v2.paused && v3.t === v2.t && !v4.paused && v4.t > v2.t, [v1, v2, v3, v4]);
        }
        var csp = await p.evaluate(function () { return window.__csp; });
        check(T + ' ' + w[0] + 'px: no request outside the site, no CSP violation, no page error', outside.length === 0 && csp.length === 0 && errs.length === 0, { outside: outside, csp: csp, errs: errs });
        await c.close();
    }
    /* reduced motion and Save-Data: no autoplay; the play button starts one loop */
    for (var mode of ['reduce', 'save-data']) {
        var cr = await browser.newContext({ viewport: { width: 1440, height: 1600 }, reducedMotion: mode === 'reduce' ? 'reduce' : 'no-preference' });
        if (mode === 'save-data') await cr.addInitScript(function () { Object.defineProperty(navigator, 'connection', { value: { saveData: true }, configurable: true }); });
        var pr = await cr.newPage();
        await pr.goto(BASE + '/design/video/');
        await pr.locator('[data-dl-card]:has(#dl-card-' + SLUG + ')').scrollIntoViewIfNeeded(); await L.wait(1500);
        var playing = await pr.evaluate(function () { return Array.prototype.filter.call(document.querySelectorAll('video'), function (v) { return !v.paused; }).length; });
        await pr.locator('[data-dl-card]:has(#dl-card-' + SLUG + ') [data-dl-play]').click(); await L.wait(1200);
        var mine = await pr.evaluate(function (slug) { var v = document.getElementById('dl-card-' + slug).closest('[data-dl-card]').querySelector('video'); return !!v && !v.paused; }, SLUG);
        check(T + ' ' + mode + ': no loop starts by itself; the play button starts this one', playing === 0 && mine, [playing, mine]);
        await cr.close();
    }
    /* axe on the detail page, light and dark (Chromium and WebKit; Firefox shares the markup) */
    if (AXE_SRC && type !== 'firefox') {
        for (var scheme of ['light', 'dark']) {
            for (var aw of [[1440, 900], [390, 844]]) {
                var ca = await browser.newContext({ viewport: { width: aw[0], height: aw[1] }, colorScheme: scheme, bypassCSP: true });
                var pa = await ca.newPage();
                await pa.goto(BASE + '/design/video/' + SLUG + '/'); await L.wait(500);
                await pa.evaluate(AXE_SRC);
                var v = await pa.evaluate(function () { return window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } }).then(function (x) { return x.violations.map(function (v) { return v.impact + ' ' + v.id + ' x' + v.nodes.length; }); }); });
                check(T + ' axe ' + scheme + ' ' + aw[0] + 'px detail: no serious or critical violation', v.filter(function (x) { return /^(serious|critical)/.test(x); }).length === 0, v);
                await ca.close();
            }
        }
    }
    /* no JavaScript */
    var cn = await browser.newContext({ viewport: { width: 390, height: 844 }, javaScriptEnabled: false });
    var pn = await cn.newPage();
    await pn.goto(BASE + '/design/video/');
    var nl = await pn.evaluate(function (slug) { var h = document.getElementById('dl-card-' + slug), c = h && h.closest('[data-dl-card]'); return !!c && !!c.querySelector('.dl-card__medium') && !!c.querySelector('picture:not(.dl-card__backdrop) img') && document.documentElement.scrollWidth <= innerWidth + 1; }, SLUG);
    await pn.goto(BASE + '/design/video/' + SLUG + '/');
    var nd = await pn.evaluate(function () { var v = document.querySelector('video[controls]'); return !!v && !!v.getAttribute('poster') && document.documentElement.scrollWidth <= innerWidth + 1; });
    check(T + ' no JS: card with label and poster, detail with <video controls> and poster', nl && nd, [nl, nd]);
    await cn.close();
    await browser.close();
}

(async function () {
    L.flag(false);
    files();
    await run('chromium', chromium);
    await run('webkit', webkit);
    await run('firefox', firefox);
    process.exit(L.summary('D2-D video ' + SLUG + ' (chromium, webkit, firefox)'));
})().catch(function (e) { console.error(e); process.exit(1); });
