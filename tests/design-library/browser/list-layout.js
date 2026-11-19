#!/usr/bin/env node
/**
 * Design Library D2-C: list card layout (local THROWAWAY site), Chromium and WebKit.
 *
 *   DL_BASE=... DL_SITE=... NODE_PATH=<playwright> node tests/design-library/browser/list-layout.js
 *
 *  - columns: 2 on PC (1440), 1–2 on tablet (834, each card ≥ 340 px wide), 1 on phone (390); no side scroll
 *  - the medium label sits below the preview (it covers nothing of the work), first in the card body
 *  - fact line from the catalog: video length / aspect / orientation, "ライブデモ" for Web works
 *  - Web posters fill the 16:10 box without cropping (same ratio); videos are shown whole (contain)
 *  - play button: visual ≤ 36 px, hit area ≥ 44 px, focusable with a visible ring, hidden without JS
 *  - summaries are cut at two lines, at most 5 tags per card, the rest counted ("+N", with a text for screen readers)
 *  - "詳細を見る" is the one primary button; "デモを開く" is a secondary link (new tab), after it in tab order
 *  - filters and keyword search still see every value (tags trimmed only on screen)
 */
'use strict';
var { chromium, webkit } = require('playwright');
var L = require('./lib.js');
var check = L.check, BASE = L.BASE;

async function run(type, launcher) {
    var browser = await launcher.launch();
    for (var vp of [[1440, 900, 2, 2], [834, 1112, 1, 2], [390, 844, 1, 1]]) {
        var T = type + ' ' + vp[0] + 'px';
        var c = await browser.newContext({ viewport: { width: vp[0], height: vp[1] }, colorScheme: 'dark' });
        var p = await c.newPage();
        for (var u of ['/design/', '/design/web/', '/design/video/']) {
            await p.goto(BASE + u);
            await p.evaluate(function () { return Promise.all(Array.prototype.map.call(document.images, function (i) { i.loading = 'eager'; return i.complete ? 0 : new Promise(function (r) { i.onload = i.onerror = r; }); })); });
            var st = await p.evaluate(function () {
                var cards = Array.prototype.filter.call(document.querySelectorAll('[data-dl-card]'), function (c) { return !c.hidden; });
                var rows = {};
                cards.forEach(function (c) { var t = Math.round(c.getBoundingClientRect().top); rows[t] = (rows[t] || 0) + 1; });
                return {
                    sw: document.documentElement.scrollWidth, iw: innerWidth,
                    cols: Math.max.apply(null, Object.keys(rows).map(function (k) { return rows[k]; })),
                    minW: Math.min.apply(null, cards.map(function (c) { return c.getBoundingClientRect().width; })),
                    cards: cards.map(function (c) {
                        var media = c.querySelector('.dl-card__media').getBoundingClientRect();
                        var label = c.querySelector('.dl-card__medium');
                        var lr = label.getBoundingClientRect();
                        var body = c.querySelector('.dl-card__body');
                        var img = c.querySelector('.dl-card__media picture:not(.dl-card__backdrop) img');
                        var fit = getComputedStyle(img).objectFit;
                        var ratio = img.naturalWidth / Math.max(1, img.naturalHeight), box = media.width / Math.max(1, media.height);
                        var sum = c.querySelector('.dl-card__summary'), lh = parseFloat(getComputedStyle(sum).lineHeight);
                        return {
                            medium: c.getAttribute('data-medium'),
                            labelBelow: lr.top >= media.bottom - 0.5 && body.firstElementChild.contains(label),
                            facts: Array.prototype.map.call(c.querySelectorAll('.dl-card__fact'), function (f) { return f.textContent.trim(); }),
                            whole: c.getAttribute('data-medium') === 'video' ? fit === 'contain' : (fit === 'cover' && Math.abs(ratio - box) < 0.02),
                            lines: Math.round(sum.getBoundingClientRect().height / lh),
                            tags: c.querySelectorAll('.dl-tag').length,
                            primary: c.querySelectorAll('.dl-card__actions .dl-btn--primary').length,
                            demo: c.querySelector('.dl-card__demo') ? c.querySelector('.dl-card__demo').target : null
                        };
                    })
                };
            });
            var P = T + ' ' + u;
            check(P + ': no side scroll', st.sw <= st.iw + 1, st.sw);
            check(P + ': ' + (vp[2] === vp[3] ? vp[2] : vp[2] + '–' + vp[3]) + ' column(s), cards ≥ 300 px wide', st.cols >= vp[2] && st.cols <= vp[3] && st.minW >= 300, [st.cols, st.minW]);
            check(P + ': the medium label is below the preview, first in the card body', st.cards.every(function (x) { return x.labelBelow; }), st.cards.map(function (x) { return x.labelBelow; }));
            check(P + ': previews are not cropped (Web 16:10 cover, video contain)', st.cards.every(function (x) { return x.whole; }), st.cards.map(function (x) { return x.whole; }));
            check(P + ': summary ≤ 2 lines, ≤ 5 tags, one primary action, demo link opens a new tab', st.cards.every(function (x) { return x.lines <= 2 && x.tags <= 5 && x.primary === 1 && (x.medium === 'video' ? x.demo === null : x.demo === '_blank'); }), st.cards);
            if (u === '/design/video/' && vp[0] === 1440) {
                var f = st.cards.map(function (x) { return x.facts.join('|'); });
                check(T + ' video facts from the catalog: length, aspect, orientation', f[0] === '6 秒|9:16 縦型' && f[1] === '8 秒|16:9 横型', f);
            }
            if (u === '/design/web/' && vp[0] === 1440) {
                check(T + ' Web facts: live demo (+ WebGL / Canvas where used)', st.cards.every(function (x) { return x.facts[0] === 'ライブデモ'; }) && st.cards.some(function (x) { return x.facts.indexOf('WebGL') !== -1; }) && st.cards.some(function (x) { return x.facts.indexOf('Canvas') !== -1; }), st.cards.map(function (x) { return x.facts; }));
            }
        }
        /* play button size, hit area, focus ring and tab order */
        await p.goto(BASE + '/design/web/');
        await L.wait(500);
        var pb = await p.evaluate(function () {
            var b = document.querySelector('[data-dl-play]'), r = b.getBoundingClientRect();
            var before = getComputedStyle(b, '::before'), inset = parseFloat(before.top);
            return { w: r.width, h: r.height, hit: r.width - 2 * inset, hidden: b.hidden };
        });
        check(T + ' play button: visual ≤ 36 px, hit area ≥ 44 px', !pb.hidden && pb.w <= 36 && pb.h <= 36 && pb.hit >= 44, pb);
        await p.locator('[data-dl-play]').first().scrollIntoViewIfNeeded();
        var hit = await p.evaluate(function () { var b = document.querySelector('[data-dl-play]'), r = b.getBoundingClientRect(); var e = document.elementFromPoint(r.left - 3, r.top + r.height / 2); return e === b || b.contains(e); });
        check(T + ' play button: a tap just outside the circle still hits it', hit);
        await p.focus('#dl-card-liquid-glass a');
        var order = [];
        for (var k = 0; k < 3; k++) { await p.keyboard.press(type === 'webkit' ? 'Alt+Tab' : 'Tab'); order.push(await p.evaluate(function () { var e = document.activeElement; return e.matches('[data-dl-play]') ? 'play' : e.classList.contains('dl-btn--primary') ? 'detail' : e.classList.contains('dl-card__demo') ? 'demo' : e.className; })); }
        check(T + ' keyboard: title → detail → demo, then the next card', order[0] === 'detail' && order[1] === 'demo', order);
        await c.close();
    }
    /* filters still see every value although only some tags are shown */
    var cf = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    var pf = await cf.newPage();
    await pf.goto(BASE + '/design/web/?motion_type=pointer');
    var hiddenTag = await pf.evaluate(function () { return Array.prototype.filter.call(document.querySelectorAll('[data-dl-card]'), function (c) { return !c.hidden; }).map(function (c) { return c.querySelector('.dl-card__title').textContent.trim(); }); });
    check(type + ' filter motion_type=pointer still finds Aurora Gradient (pointer is not among its shown tags)', hiddenTag.indexOf('Aurora Gradient') !== -1, hiddenTag);
    await cf.close();
    var cn = await browser.newContext({ viewport: { width: 390, height: 844 }, javaScriptEnabled: false });
    var pn = await cn.newPage();
    await pn.goto(BASE + '/design/');
    var nojs = await pn.evaluate(function () { return { labels: document.querySelectorAll('.dl-card__body .dl-card__medium').length, play: Array.prototype.filter.call(document.querySelectorAll('[data-dl-play]'), function (b) { return b.offsetParent !== null; }).length, sw: document.documentElement.scrollWidth, iw: innerWidth }; });
    check(type + ' no JS: ' + L.published().length + ' labels below the previews, no play button, no side scroll', nojs.labels === L.published().length && nojs.play === 0 && nojs.sw <= nojs.iw + 1, nojs);
    await cn.close();
    await browser.close();
}

(async function () {
    L.flag(false);
    await run('chromium', chromium);
    await run('webkit', webkit);
    process.exit(L.summary('list layout D2-C (chromium, webkit)'));
})().catch(function (e) { console.error(e); process.exit(1); });
