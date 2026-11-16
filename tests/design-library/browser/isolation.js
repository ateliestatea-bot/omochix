#!/usr/bin/env node
/**
 * Design Library isolation acceptance B2-B9 (D0 §10.1) in Chromium and WebKit,
 * against the THROWAWAY local site, with the test-only probe demo
 * (tests/design-library/fixtures/isolation-probe). Also: the 4 real demos have
 * no CSP violation and send no request outside the site's own assets.
 *
 *   DL_BASE=http://127.0.0.1:8961 DL_SITE=<site dir> NODE_PATH=<playwright> node tests/design-library/browser/isolation.js
 * Firefox: tests/design-library/browser/firefox.js.
 */
'use strict';
var { chromium, webkit } = require('playwright');
var L = require('./lib.js');
var check = L.check, BASE = L.BASE;
var PROBE = '/design/demo/web/isolation-probe/';

async function probeFrame(page) {
    for (var i = 0; i < 60; i++) {
        var f = page.frames().filter(function (fr) { return fr.url().indexOf(PROBE) !== -1; })[0];
        if (f) { try { if (await f.evaluate(function () { return window.__probe && window.__probe.done; })) return f; } catch (e) { /* loading */ } }
        await L.wait(150);
    }
    throw new Error('probe frame did not finish');
}
function threw(v, name) { return typeof v === 'string' && v.indexOf('threw:' + (name || '')) === 0; }

async function run(type, launcher) {
    var browser = await launcher.launch(type === 'chromium' ? { args: L.GL_ARGS } : {});
    var ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    await ctx.addInitScript(L.INIT);
    await ctx.addCookies([{ name: 'omx_b4', value: 'visible-to-the-site', domain: '127.0.0.1', path: '/', httpOnly: false }]);
    // Only requests that really completed count (Chromium also reports CSP-blocked ones, as failed "csp").
    var requests = [], dialogs = [], popups = [];
    ctx.on('requestfinished', function (r) { requests.push(r.method() + ' ' + r.url()); });
    var page = await ctx.newPage();
    ctx.on('page', function (p) { popups.push(p.url()); });
    page.on('dialog', function (d) { dialogs.push(d.message()); d.dismiss().catch(function () {}); });

    /* ---------------- embedded ---------------- */
    var detail = BASE + '/design/web/isolation-probe/';
    await page.goto(detail);
    await page.locator('[data-dl-demo]').scrollIntoViewIfNeeded();
    var f = await probeFrame(page);
    await L.wait(800);
    var r = await f.evaluate(function () { return window.__probe; });
    var T = type + ' embedded';
    check(T + ' B2: iframe sandbox attribute is exactly "allow-scripts"', (await page.getAttribute('[data-dl-demo]', 'sandbox')) === 'allow-scripts');
    check(T + ' B3: demo origin is "null"', r.origin === 'null', r.origin);
    check(T + ' B4 positive control: the site page itself can read the test cookie', (await page.evaluate(function () { return document.cookie; })).indexOf('omx_b4=') !== -1);
    check(T + ' B4: document.cookie read and write throw SecurityError', threw(r.cookieRead, 'SecurityError') && threw(r.cookieWrite, 'SecurityError'), [r.cookieRead, r.cookieWrite]);
    check(T + ' B4: localStorage / sessionStorage are not available', threw(r.localStorage) && threw(r.sessionStorage), [r.localStorage, r.sessionStorage]);
    check(T + ' B4: the site cookie jar did not get "probe=1"', (await ctx.cookies()).every(function (c) { return c.name !== 'probe'; }));
    check(T + ' B5: fetch to /wp-json/ (GET, users/me, POST posts) is rejected', [r.fetchRoot, r.fetchMe, r.fetchPost].every(function (v) { return String(v).indexOf('rejected') === 0; }), [r.fetchRoot, r.fetchMe, r.fetchPost]);
    check(T + ' B5: XMLHttpRequest fails', r.xhr === 'error' || threw(r.xhr), r.xhr);
    check(T + ' B5: sendBeacon never reaches the network (it may report "queued")', requests.every(function (u) { return u.indexOf('probe=beacon') === -1; }), r.sendBeacon);
    check(T + ' B5: WebSocket cannot connect', threw(r.webSocket) || r.webSocketError === true, [r.webSocket, r.webSocketError]);
    var leaked = requests.filter(function (u) { return u.indexOf('probe=') !== -1 || /admin-post\.php|wp-admin\/$/.test(u); });
    check(T + ' B5/B6: no request left the demo (fetch, XHR, beacon, image, WebSocket, form, link)', leaked.length === 0, leaked);
    check(T + ' B6: parent and top document throw SecurityError', threw(r.parentDocument, 'SecurityError') && threw(r.topDocument, 'SecurityError'), [r.parentDocument, r.topDocument]);
    check(T + ' B6: eval / new Function / injected inline script do not run', threw(r.eval) && threw(r.newFunction) && r.inlineScript === 'injected:false', [r.eval, r.newFunction, r.inlineScript]);
    check(T + ' B6: window.open gives no window, alert shows no dialog', (r.windowOpen === 'null' || threw(r.windowOpen)) && popups.length === 0 && dialogs.length === 0, [r.windowOpen, popups, dialogs]);
    await f.click('#probe-top').catch(function () {});
    await L.wait(800);
    check(T + ' B6: a target=_top link and form.submit() do not move the parent page', page.url() === detail && page.mainFrame().url() === detail, page.url());

    /* B7 demo -> parent */
    var before = await page.evaluate(function () { var s = document.querySelector('[data-dl-stage]'); return { state: s.getAttribute('data-state'), html: document.querySelector('main').innerHTML.length, status: s.querySelector('[data-dl-status]').textContent }; });
    var sent = await f.evaluate(function () { return window.__probeSendBad(); });
    await page.evaluate(function () {
        var x = document.createElement('iframe'); x.id = 'b7-sibling';
        x.srcdoc = '<script>parent.postMessage({type:"omx-design:error",code:"forged"},"*");parent.postMessage({type:"omx-design:ready"},"*");<\/script>';
        document.body.appendChild(x);
    });
    await L.wait(800);
    var after = await page.evaluate(function () { var s = document.querySelector('[data-dl-stage]'); return { state: s.getAttribute('data-state'), html: document.querySelector('main').innerHTML.length, status: s.querySelector('[data-dl-status]').textContent }; });
    check(T + ' B7: ' + sent + ' bad / flood messages and a forged sibling frame change nothing in the parent', before.state === 'ready' && JSON.stringify(before) === JSON.stringify(after) && page.url() === detail && dialogs.length === 0, [before, after]);
    /* B8 parent -> demo */
    async function themeAfter(fn) { await page.evaluate(fn); await L.wait(250); return f.evaluate(function () { return document.documentElement.getAttribute('data-theme'); }); }
    await f.evaluate(function () { window.__scriptsBefore = document.querySelectorAll('script').length; });
    var t1 = await themeAfter(function () { document.querySelector('[data-dl-demo]').contentWindow.postMessage({ type: 'omx-design:theme', theme: 'dark' }, '*'); });
    var t2 = await themeAfter(function () { document.getElementById('b7-sibling').contentWindow.eval('parent.frames[0] && 0'); var w = document.querySelector('[data-dl-demo]').contentWindow; var s = document.getElementById('b7-sibling').contentWindow; s.postMessage(null, '*'); s.eval ? 0 : 0; var sc = s.document.createElement('script'); sc.textContent = 'parent.document.querySelector("[data-dl-demo]").contentWindow.postMessage({type:"omx-design:theme",theme:"light"},"*")'; s.document.body.appendChild(sc); void w; });
    var t3 = await themeAfter(function () { var w = document.querySelector('[data-dl-demo]').contentWindow; w.postMessage({ type: 'omx-design:theme', theme: '<img src=x onerror=alert(1)>' }, '*'); w.postMessage({ type: 'omx-design:params', values: { title: '<script>alert(1)<\/script>' } }, '*'); w.postMessage('<script>alert(1)<\/script>', '*'); w.postMessage({ type: 'omx-design:theme', theme: 'light', extra: '<b>' }, '*'); });
    var injected = await f.evaluate(function () { return document.querySelectorAll('img[src="x"], b').length + document.querySelectorAll('script').length - window.__scriptsBefore; });
    check(T + ' B8: valid theme from the parent applies, a sibling frame is ignored', t1 === 'dark' && t2 === 'dark', [t1, t2]);
    check(T + ' B8: invalid values are not applied and nothing is injected', (t3 === 'light' || t3 === 'dark') && dialogs.length === 0 && injected === 0, [t3, injected]);
    /* D2-A: control messages (parent -> demo) are validated like the theme message */
    async function pausedAfter(fn) { await page.evaluate(fn); await L.wait(250); return f.evaluate(function () { return window.omxDemo.isPaused(); }); }
    var c1 = await pausedAfter(function () { var w = document.querySelector('[data-dl-demo]').contentWindow; w.postMessage({ type: 'omx-design:control', action: '<script>' }, '*'); w.postMessage({ type: 'omx-design:control', action: 'stop' }, '*'); w.postMessage({ type: 'omx-design:control' }, '*'); });
    var c2 = await pausedAfter(function () { var s = document.getElementById('b7-sibling').contentWindow; var sc = s.document.createElement('script'); sc.textContent = 'parent.document.querySelector("[data-dl-demo]").contentWindow.postMessage({type:"omx-design:control",action:"pause"},"*")'; s.document.body.appendChild(sc); });
    var c3 = await pausedAfter(function () { document.querySelector('[data-dl-demo]').contentWindow.postMessage({ type: 'omx-design:control', action: 'pause' }, '*'); });
    var c4 = await pausedAfter(function () { document.querySelector('[data-dl-demo]').contentWindow.postMessage({ type: 'omx-design:control', action: 'play' }, '*'); });
    check(T + ' D2-A control: invalid actions and a sibling frame are ignored; pause / play from the parent apply', c1 === false && c2 === false && c3 === true && c4 === false, [c1, c2, c3, c4]);
    var stBefore = await page.evaluate(function () { return document.querySelector('[data-dl-stage]').className; });
    await f.evaluate(function () { window.parent.postMessage({ type: 'omx-design:key', key: 'Escape' }, '*'); window.parent.postMessage({ type: 'omx-design:key', key: 'F11' }, '*'); window.parent.postMessage({ type: 'omx-design:fullscreen' }, '*'); });
    await L.wait(300);
    check(T + ' D2-A key message: changes nothing when no overlay is open; no message can open full screen', stBefore === (await page.evaluate(function () { return document.querySelector('[data-dl-stage]').className; })) && !(await page.evaluate(function () { return !!document.fullscreenElement || document.querySelector('[data-dl-stage]').classList.contains('is-full'); })));
    /* positive control for B7: a valid error message from the demo itself is accepted */
    await f.evaluate(function () { window.__probeSendError(); });
    await L.wait(300);
    check(T + ' B7 positive control: a valid message from the demo is accepted (the listener works)', (await page.getAttribute('[data-dl-stage]', 'data-state')) === 'error');

    /* ---------------- B9 direct (top-level) ---------------- */
    requests.length = 0;
    await page.goto(BASE + PROBE);
    for (var i = 0; i < 40 && !(await page.evaluate(function () { return window.__probe && window.__probe.done; })); i++) await L.wait(150);
    var d = await page.evaluate(function () { return window.__probe; });
    T = type + ' direct';
    check(T + ' B9/B3: origin is "null" when opened directly', d.origin === 'null', d.origin);
    check(T + ' B9/B4: cookies and storage throw', threw(d.cookieRead, 'SecurityError') && threw(d.cookieWrite, 'SecurityError') && threw(d.localStorage), [d.cookieRead, d.localStorage]);
    check(T + ' B9/B5: network APIs are blocked, no request leaves', [d.fetchRoot, d.fetchMe, d.fetchPost].every(function (v) { return String(v).indexOf('rejected') === 0; }) && requests.filter(function (u) { return u.indexOf('probe=') !== -1 || /admin-post\.php/.test(u); }).length === 0, [d.fetchRoot, requests.filter(function (u) { return u.indexOf('probe=') !== -1; })]);
    check(T + ' B9/B6: no popup, no dialog, eval blocked', (d.windowOpen === 'null' || threw(d.windowOpen)) && popups.length === 0 && dialogs.length === 0 && threw(d.eval), [d.windowOpen, popups, dialogs]);
    L.info(T + ': a link clicked in a demo opened directly navigates that tab like any page link (it is the top document); the demo audit forbids such links (catalog-check.js)');

    /* ---------------- real demos: no CSP violation, no outside request ---------------- */
    for (var s of L.DEMOS) {
        requests.length = 0;
        var pg = await ctx.newPage();
        var consoleCsp = [];
        pg.on('console', function (m) { if (/Content.Security.Policy|Refused to/i.test(m.text())) consoleCsp.push(m.text().slice(0, 160)); });
        await pg.goto(BASE + '/design/demo/web/' + s + '/');
        await pg.mouse.move(600, 400); await pg.mouse.wheel(0, 1200); await L.wait(1800);
        var vio = await pg.evaluate(function () { return window.__csp; });
        var outside = requests.filter(function (u) { var url = u.split(' ')[1]; return url.indexOf(BASE + '/design/demo/web/' + s + '/') !== 0 && url.indexOf(BASE + '/wp-content/themes/omochix-theme/assets/design/') !== 0 && !/^(data|blob|about):/.test(url); });
        check(type + ' demo ' + s + ': no CSP violation', vio.length === 0 && consoleCsp.length === 0, vio.concat(consoleCsp));
        check(type + ' demo ' + s + ': no request outside the demo and its own assets', outside.length === 0, outside);
        check(type + ' demo ' + s + ': document.cookie throws inside the real demo', await pg.evaluate(function () { try { void document.cookie; return false; } catch (e) { return e.name === 'SecurityError'; } }));
        await pg.close();
    }
    await browser.close();
    var log = L.SITE + '/logs/requests.log';
    if (require('fs').existsSync(log)) {
        var hits = require('fs').readFileSync(log, 'utf8').split('\n').filter(function (l) { return l.indexOf('probe=') !== -1; });
        check(type + ' server log: no probe request reached the server', hits.length === 0, hits.slice(0, 5));
    } else { L.info('no server request log at ' + log + '; relying on the browser network log'); }
}

(async function () {
    L.flag(true);
    try {
        await run('chromium', chromium);
        await run('webkit', webkit);
    } finally { L.flag(false); }
    process.exit(L.summary('isolation (chromium, webkit)'));
})().catch(function (e) { console.error(e); L.flag(false); process.exit(1); });
