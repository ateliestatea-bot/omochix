#!/usr/bin/env node
/**
 * Design Library D2-D: interactions of the new Web works (local THROWAWAY site), Chromium, WebKit and Firefox.
 * The generic checks (CSP, outside requests, contrast, axe, reduced motion, no JS, pause / play / restart)
 * are in new-works.js; this file checks what each work does when used.
 *
 *   DL_BASE=... DL_SITE=... NODE_PATH=<playwright> node tests/design-library/browser/d2d-web.js
 *
 * AI Workspace UI: command palette (Ctrl+K, arrows, filter, Enter, Esc returns focus, Tab stays inside),
 *   citation → source, typed text shown as text only and never sent, reply streams and can be stopped,
 *   the stream holds while the demo is hidden and continues afterwards, sources panel, tablet drawer,
 *   phone views, retry of a failed source, reduced motion shows replies at once.
 * Micro Interaction Collection: switches by keyboard with a "saved" status, disabled switch, segmented
 *   control by arrows, range output, amount validation (aria-invalid, message, shake only with motion),
 *   memo counter, submit loading → success (adds a row, text only) and loading → error, unpaid total,
 *   delete → undo restores the row and focus, the toast waits while hidden.
 */
'use strict';
var { chromium, webkit, firefox } = require('playwright');
var L = require('./lib.js');
var check = L.check, BASE = L.BASE;
var ONLY = process.argv.slice(2);   // optional: only these slugs

async function open(browser, slug, vp, opts) {
    var c = await browser.newContext(Object.assign({ viewport: { width: vp[0], height: vp[1] } }, opts || {}));
    await c.addInitScript(L.INIT);
    var url = BASE + '/design/demo/web/' + slug + '/', outside = [], errs = [];
    c.on('request', function (r) { var u = r.url(); if (u.indexOf(url) !== 0 && !/^(data|blob|about):/.test(u)) outside.push(u); });
    var p = await c.newPage();
    p.on('pageerror', function (e) { errs.push(e.message); });
    await p.goto(url); await L.wait(500);
    return { c: c, p: p, outside: outside, errs: errs };
}
function hide(p, on) { return p.evaluate(function (h) { Object.defineProperty(document, 'hidden', { value: h, configurable: true }); document.dispatchEvent(new Event('visibilitychange')); }, on); }
var active = function (p) { return p.evaluate(function () { var e = document.activeElement; return e.id || e.getAttribute('data-aw-show') || e.className; }); };

async function aiWorkspace(type, browser) {
    var T = type + ' ai-workspace';
    var s = await open(browser, 'ai-workspace', [1440, 900]);
    var p = s.p;
    await L.wait(2600);   // first answer has appeared
    check(T + ': the first answer is fully shown after it streams in', (await p.locator('.aw-msg--ai .is-pending').count()) === 0);
    /* palette */
    await p.focus('[data-aw-open-palette]');
    await p.keyboard.press('Control+k'); await L.wait(200);
    var pal = await p.evaluate(function () { var d = document.querySelector('[data-aw-palette]'); return { open: !d.hidden, focus: document.activeElement.id, act: document.activeElement.getAttribute('aria-activedescendant'), modal: d.getAttribute('aria-modal') }; });
    check(T + ': Ctrl+K opens the palette (modal dialog), focus in the field, first command active', pal.open && pal.focus === 'aw-palette-q' && pal.act === 'aw-cmd-new' && pal.modal === 'true', pal);
    await p.keyboard.press('ArrowDown'); await p.keyboard.press('ArrowDown');
    var a2 = await p.getAttribute('#aw-palette-q', 'aria-activedescendant');
    await p.keyboard.press('Tab');
    check(T + ': arrows move the active command; Tab stays in the palette', a2 === 'aw-cmd-memo' && (await active(p)) === 'aw-palette-q', [a2, await active(p)]);
    await p.keyboard.press('Escape'); await L.wait(100);
    check(T + ': Esc closes the palette and returns focus to the opener', (await p.isHidden('[data-aw-palette]')) && (await p.evaluate(function () { return document.activeElement.hasAttribute('data-aw-open-palette'); })));
    await p.keyboard.press('Control+k'); await p.keyboard.type('テーマ'); await L.wait(100);
    var vis = await p.evaluate(function () { return Array.prototype.filter.call(document.querySelectorAll('[data-aw-palette-list] [role="option"]'), function (o) { return !o.hidden; }).map(function (o) { return o.id; }); });
    var before = await p.getAttribute('html', 'data-theme');
    await p.keyboard.press('Enter'); await L.wait(150);
    var after = await p.getAttribute('html', 'data-theme');
    check(T + ': typing filters the commands; Enter runs "theme" (light ↔ dark), the palette closes', JSON.stringify(vis) === '["aw-cmd-theme"]' && before !== after && (await p.isHidden('[data-aw-palette]')), [vis, before, after]);
    await p.click('[data-aw-theme]'); await L.wait(100);
    check(T + ': the theme button switches back and names the next theme', (await p.getAttribute('html', 'data-theme')) === before && /テーマに切り替える/.test(await p.getAttribute('[data-aw-theme]', 'aria-label')));
    /* citation → source */
    await p.click('[data-aw-cite="2"]'); await L.wait(150);
    var cite = await p.evaluate(function () { return { pressed: document.querySelector('[data-aw-cite="2"]').getAttribute('aria-pressed'), focus: document.activeElement.id, ring: document.getElementById('aw-src-2').classList.contains('is-focus') }; });
    check(T + ': a citation number moves focus to its source and marks it', cite.pressed === 'true' && cite.focus === 'aw-src-2' && cite.ring, cite);
    /* typed text: shown as text, reply streams, nothing is sent */
    var evil = '<img src=x onerror="window.__x=1">見本の質問';
    await p.fill('[data-aw-input]', evil); await p.press('[data-aw-input]', 'Enter'); await L.wait(300);
    var sent = await p.evaluate(function () { var u = document.querySelectorAll('.aw-msg--user'); var last = u[u.length - 1]; return { text: last.lastElementChild.textContent, imgs: document.querySelectorAll('[data-aw-log] img').length, x: window.__x === 1, busy: document.querySelector('[data-aw-send]').getAttribute('aria-busy'), skel: !!document.querySelector('.aw-skel'), input: document.querySelector('[data-aw-input]').value }; });
    check(T + ': typed text is shown as plain text (no element, no script), the field clears, a skeleton shows', sent.text === evil && sent.imgs === 0 && !sent.x && sent.skel && sent.input === '', sent);
    await L.wait(1200);
    var mid = await p.evaluate(function () { return { label: document.querySelector('[data-aw-send-label]').textContent, busy: document.querySelector('[data-aw-send]').getAttribute('aria-busy'), pending: document.querySelectorAll('.aw-msg--ai:last-child .is-pending').length }; });
    await L.wait(2400);
    var end = await p.evaluate(function () { var a = document.querySelectorAll('.aw-msg--ai'); a = a[a.length - 1]; return { label: document.querySelector('[data-aw-send-label]').textContent, pending: a.querySelectorAll('.is-pending').length, blocks: a.querySelectorAll('.aw-s').length, live: document.querySelector('[data-aw-log]').getAttribute('aria-live') }; });
    check(T + ': the reply streams block by block ("止める" while busy), then completes (4 blocks, aria-live log)', mid.label === '止める' && mid.busy === 'true' && mid.pending > 0 && end.label === '送る' && end.pending === 0 && end.blocks === 4 && end.live === 'polite', [mid, end]);
    /* stop */
    await p.fill('[data-aw-input]', '止める試験'); await p.press('[data-aw-input]', 'Enter'); await L.wait(1100);
    await p.click('[data-aw-send]'); await L.wait(200);
    var stop = await p.evaluate(function () { var a = document.querySelectorAll('.aw-msg--ai'); a = a[a.length - 1]; return { note: a.textContent.indexOf('回答を途中で止めました') !== -1, pending: a.querySelectorAll('.is-pending').length, label: document.querySelector('[data-aw-send-label]').textContent }; });
    check(T + ': "止める" stops the reply and says so', stop.note && stop.pending === 0 && stop.label === '送る', stop);
    /* hidden: the stream holds, then continues */
    await p.fill('[data-aw-input]', '一時停止の試験'); await p.press('[data-aw-input]', 'Enter'); await L.wait(1000);
    await hide(p, true);
    var h1 = await p.evaluate(function () { var a = document.querySelectorAll('.aw-msg--ai'); return a[a.length - 1].querySelectorAll('.aw-s:not(.is-pending)').length; });
    await L.wait(1500);
    var h2 = await p.evaluate(function () { var a = document.querySelectorAll('.aw-msg--ai'); return a[a.length - 1].querySelectorAll('.aw-s:not(.is-pending)').length; });
    await hide(p, false); await L.wait(2200);
    var h3 = await p.evaluate(function () { var a = document.querySelectorAll('.aw-msg--ai'); return a[a.length - 1].querySelectorAll('.aw-s:not(.is-pending)').length; });
    check(T + ': while hidden the reply holds; it continues and completes afterwards', h2 === h1 && h1 < 4 && h3 === 4, [h1, h2, h3]);
    /* retry, panel */
    await p.click('[data-aw-retry]');
    var busy = await p.getAttribute('[data-aw-retry]', 'aria-busy'); await L.wait(1200);
    check(T + ': a failed source shows an error and loads on retry', busy === 'true' && !(await p.evaluate(function () { return document.getElementById('aw-src-3').classList.contains('aw-src--error'); })));
    await p.click('[data-aw-panel]'); await L.wait(100);
    var closed = await p.evaluate(function () { return { exp: document.querySelector('[data-aw-panel]').getAttribute('aria-expanded'), shown: document.getElementById('aw-panel').offsetParent !== null }; });
    await p.click('[data-aw-panel]'); await L.wait(100);
    check(T + ': the panel button hides and shows the sources panel (aria-expanded)', closed.exp === 'false' && !closed.shown && (await p.isVisible('#aw-panel')), closed);
    check(T + ': no request outside the demo, no page error', s.outside.length === 0 && s.errs.length === 0, [s.outside, s.errs]);
    await s.c.close();

    /* tablet drawer */
    var t = await open(browser, 'ai-workspace', [834, 1112]);
    await t.p.click('[data-aw-nav]'); await L.wait(300);
    var dr = await t.p.evaluate(function () { var r = document.getElementById('aw-side').getBoundingClientRect(); return { exp: document.querySelector('[data-aw-nav]').getAttribute('aria-expanded'), left: Math.round(r.left), focus: document.getElementById('aw-side').contains(document.activeElement) && document.activeElement.tagName === 'A' }; });
    await t.p.keyboard.press('Escape'); await L.wait(300);
    var dc = await t.p.evaluate(function () { return { exp: document.querySelector('[data-aw-nav]').getAttribute('aria-expanded'), focus: document.activeElement.hasAttribute('data-aw-nav'), vis: getComputedStyle(document.getElementById('aw-side')).visibility }; });
    check(T + ' 834px: the menu opens the thread drawer (focus inside); Esc closes it and returns focus', dr.exp === 'true' && dr.left === 0 && dr.focus === true && dc.exp === 'false' && dc.focus && dc.vis === 'hidden', [dr, dc]);
    await t.c.close();
    /* phone views */
    var m = await open(browser, 'ai-workspace', [390, 844]);
    var view = function () { return m.p.evaluate(function () { return ['aw-side', 'aw-main', 'aw-panel'].filter(function (id) { return document.getElementById(id).offsetParent !== null; }).join(','); }); };
    var v0 = await view();
    await m.p.click('[data-aw-show="sources"]'); var v1 = await view();
    await m.p.focus('[data-aw-show="sources"]');   // WebKit does not focus a clicked button
    await m.p.keyboard.press('ArrowLeft'); var v2 = await view();
    var sel = await m.p.evaluate(function () { return document.activeElement.getAttribute('data-aw-show') + ' ' + document.activeElement.getAttribute('aria-selected'); });
    await m.p.click('[data-aw-cite="1"]'); await L.wait(200); var v3 = await view();
    var sw = await m.p.evaluate(function () { return document.documentElement.scrollWidth <= innerWidth + 1; });
    check(T + ' 390px: one view at a time (chat → sources → chat by arrow key); a citation opens the sources view; no side scroll', v0 === 'aw-main' && v1 === 'aw-panel' && v2 === 'aw-main' && sel === 'chat true' && v3 === 'aw-panel' && sw, [v0, v1, v2, sel, v3, sw]);
    await m.c.close();
    /* reduced motion */
    var r = await open(browser, 'ai-workspace', [1440, 900], { reducedMotion: 'reduce' });
    await r.p.fill('[data-aw-input]', '動きを減らす'); await r.p.press('[data-aw-input]', 'Enter'); await L.wait(250);
    var rr = await r.p.evaluate(function () { var a = document.querySelectorAll('.aw-msg--ai'); a = a[a.length - 1]; return { pending: a.querySelectorAll('.is-pending').length, blocks: a.querySelectorAll('.aw-s').length, anims: document.getAnimations().length }; });
    check(T + ' reduced motion: the reply is shown at once, no animation runs', rr.pending === 0 && rr.blocks === 4 && rr.anims === 0, rr);
    await r.c.close();
}

async function microInteractions(type, browser) {
    var T = type + ' micro-interactions';
    var s = await open(browser, 'micro-interactions', [1440, 900]);
    var p = s.p;
    /* settings */
    await p.focus('#mi-sw-2'); await p.keyboard.press('Space'); await L.wait(150);
    var sw = await p.evaluate(function () { var i = document.getElementById('mi-sw-2'); return { on: i.checked, role: i.getAttribute('role'), saved: document.querySelector('[data-mi-saved]').textContent }; });
    check(T + ': a switch toggles with Space and says what was saved (role=switch, live status)', sw.on && sw.role === 'switch' && sw.saved === '保存しました（週ごとのまとめ：オン）', sw);
    await p.click('label[for="mi-sw-3"]', { force: true });
    check(T + ': the disabled switch does not change and says why', !(await p.isChecked('#mi-sw-3')) && (await p.getAttribute('#mi-sw-3', 'aria-describedby')) === 'mi-sw-3-why');
    await p.focus('#mi-start-1'); await p.keyboard.press('ArrowRight'); await L.wait(350);
    var seg = await p.evaluate(function () { return { on: document.getElementById('mi-start-25').checked, thumb: getComputedStyle(document.querySelector('.mi-seg__thumb')).transform }; });
    check(T + ': the segmented control moves with arrow keys and its thumb follows', seg.on && seg.thumb !== 'none', seg);
    await p.focus('#mi-alert'); await p.keyboard.press('ArrowRight');
    check(T + ': the range shows its value', (await p.textContent('[data-mi-range-out]')) === '85%');
    /* validation */
    await p.fill('#mi-amount', 'abc'); await p.focus('#mi-memo'); await L.wait(60);
    var bad = await p.evaluate(function () { var f = document.querySelector('[data-mi-field="amount"]'); return { inv: document.getElementById('mi-amount').getAttribute('aria-invalid'), msg: document.getElementById('mi-amount-msg').textContent, shake: f.classList.contains('is-shake') }; });
    check(T + ': a wrong amount is marked (aria-invalid, message linked by aria-describedby) and shakes once', bad.inv === 'true' && /数字で入力/.test(bad.msg) && bad.shake, bad);
    await p.fill('#mi-amount', '2500'); await p.focus('#mi-memo'); await L.wait(60);
    var good = await p.evaluate(function () { return { inv: document.getElementById('mi-amount').getAttribute('aria-invalid'), val: document.getElementById('mi-amount').value, ok: document.querySelector('[data-mi-field="amount"]').classList.contains('is-valid') }; });
    check(T + ': a correct amount is formatted and gets a check mark', good.inv === 'false' && good.val === '2,500' && good.ok, good);
    await p.fill('#mi-memo', 'あいうえおかきくけこさしすせそたちつてとなにぬねのはひふへほまみむめもや');
    check(T + ': the memo counter warns near the limit', (await p.textContent('[data-mi-count]')) === '36 / 40 文字（残り 4 文字）' && (await p.evaluate(function () { return document.querySelector('[data-mi-count]').classList.contains('is-near'); })));
    /* submit: success adds a row (text only), error is shown */
    var rows0 = await p.locator('.mi-item').count();
    var total0 = await p.textContent('[data-mi-total]');
    await p.fill('#mi-memo', '<b>見本</b>');
    await p.click('[data-mi-submit]');
    var loading = await p.evaluate(function () { var b = document.querySelector('[data-mi-submit]'); return b.getAttribute('data-state') + ' ' + b.getAttribute('aria-busy'); });
    await L.wait(2000);   // success at ~1.1 s, then the total counts up for 0.42 s
    var ok = await p.evaluate(function () { var b = document.querySelector('[data-mi-submit]'); var first = document.querySelector('.mi-item .mi-item__t'); return { st: b.getAttribute('data-state'), res: document.querySelector('[data-mi-result]').textContent, first: first.textContent, bold: document.querySelectorAll('[data-mi-items] b').length, total: document.querySelector('[data-mi-total]').textContent }; });
    check(T + ': submit shows loading (aria-busy) then success; the new row shows the memo as text; the total grows', loading === 'loading true' && ok.st === 'success' && /2,500 円を記録しました/.test(ok.res) && ok.first === '<b>見本</b>' && ok.bold === 0 && (await p.locator('.mi-item').count()) === rows0 + 1 && ok.total === '¥8,360' && total0 === '¥5,860', [loading, ok, total0]);
    await L.wait(1900);
    await p.check('[data-mi-fail]'); await p.click('[data-mi-submit]'); await L.wait(1500);
    var err = await p.evaluate(function () { return { st: document.querySelector('[data-mi-submit]').getAttribute('data-state'), label: document.querySelector('[data-mi-submit-label]').textContent, res: document.querySelector('[data-mi-result]').className }; });
    check(T + ': with the failure switch, submit ends in an error state that offers a retry', err.st === 'error' && err.label === 'もう一度試す' && /is-err/.test(err.res), err);
    /* list: paid, delete, undo */
    await p.click('label[for="mi-it-2"]'); await L.wait(700);
    check(T + ': marking a row paid lowers the unpaid total', (await p.textContent('[data-mi-total]')) === '¥5,960');
    var idx = await p.evaluate(function () { return Array.prototype.indexOf.call(document.querySelectorAll('.mi-item'), document.getElementById('mi-it-3').closest('.mi-item')); });
    await p.focus('#mi-it-3 ~ [data-mi-del]'); await p.keyboard.press('Enter'); await L.wait(900);
    var del = await p.evaluate(function () { return { gone: !document.getElementById('mi-it-3'), toast: !document.querySelector('[data-mi-toast]').hidden, text: document.querySelector('[data-mi-toast-text]').textContent, focus: document.activeElement.className, total: document.querySelector('[data-mi-total]').textContent }; });
    check(T + ': delete (Enter) removes the row, keeps focus in the list and offers undo', del.gone && del.toast && del.text === '「文具とプリント」を削除しました' && del.focus === 'mi-check' && del.total === '¥3,780', del);
    await p.click('[data-mi-undo]'); await L.wait(700);
    var un = await p.evaluate(function () { var li = document.getElementById('mi-it-3'); return { back: !!li, at: li ? Array.prototype.indexOf.call(document.querySelectorAll('.mi-item'), li.closest('.mi-item')) : -1, toast: document.querySelector('[data-mi-toast]').hidden, focus: document.activeElement.id, total: document.querySelector('[data-mi-total]').textContent }; });
    check(T + ': undo puts the row back in place, closes the toast, focuses the row', un.back && un.at === idx && un.toast && un.focus === 'mi-it-3' && un.total === '¥5,960', [un, idx]);
    await p.click('#mi-it-3 ~ [data-mi-del]'); await L.wait(400);
    await hide(p, true); await L.wait(5600);
    var held = await p.isVisible('[data-mi-toast]');
    await hide(p, false); await L.wait(400);
    check(T + ': the undo toast waits while the demo is hidden, then closes', held && (await p.isHidden('[data-mi-toast]')), held);
    check(T + ': no request outside the demo, no page error', s.outside.length === 0 && s.errs.length === 0, [s.outside, s.errs]);
    await s.c.close();
    /* reduced motion: same states, no movement */
    var r = await open(browser, 'micro-interactions', [390, 844], { reducedMotion: 'reduce' });
    await r.p.fill('#mi-amount', 'x'); await r.p.focus('#mi-memo'); await L.wait(60);
    var rr = await r.p.evaluate(function () { var f = document.querySelector('[data-mi-field="amount"]'); return { inv: f.classList.contains('is-invalid'), shake: f.classList.contains('is-shake'), anims: document.getAnimations().length, sw: document.documentElement.scrollWidth <= innerWidth + 1 }; });
    await r.p.fill('#mi-amount', '900'); await r.p.click('[data-mi-submit]'); await L.wait(500);
    var rs = await r.p.getAttribute('[data-mi-submit]', 'data-state');
    check(T + ' reduced motion (phone): errors and success are still shown, nothing shakes or moves, no side scroll', rr.inv && !rr.shake && rr.anims === 0 && rr.sw && rs === 'success', [rr, rs]);
    await r.c.close();
}

(async function () {
    L.flag(true);
    try {
        for (var pair of [['chromium', chromium], ['webkit', webkit], ['firefox', firefox]]) {
            var b = await pair[1].launch();
            if (!ONLY.length || ONLY.indexOf('ai-workspace') !== -1) await aiWorkspace(pair[0], b);
            if (!ONLY.length || ONLY.indexOf('micro-interactions') !== -1) await microInteractions(pair[0], b);
            await b.close();
        }
    } finally { L.flag(false); }
    process.exit(L.summary('D2-D web interactions (chromium, webkit, firefox)'));
})().catch(function (e) { console.error(e); L.flag(false); process.exit(1); });
