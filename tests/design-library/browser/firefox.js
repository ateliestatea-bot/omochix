#!/usr/bin/env node
/**
 * Design Library checks in Firefox (local only): isolation B2-B9 with the
 * test probe, the 4 demos (no CSP violation, cookie blocked), and the list
 * page (≤ 2 loops playing, no horizontal scroll, filters).
 *
 * Drives the installed Firefox over its built-in WebDriver BiDi (no driver
 * download), headless, with a throwaway profile (no telemetry, updates or
 * remote settings). Whether a request reached the server is read from the
 * throwaway site's request log (<site>/logs/requests.log) when present.
 *
 *   DL_BASE=http://127.0.0.1:8961 DL_SITE=<site dir> node tests/design-library/browser/firefox.js
 *   DL_FIREFOX_PATH=/Applications/Firefox.app/Contents/MacOS/firefox (default)
 */
'use strict';
var path = require('path');
var fs = require('fs');
var os = require('os');
var spawn = require('child_process').spawn;
var L = require('./lib.js');

var FIREFOX = process.env.DL_FIREFOX_PATH || '/Applications/Firefox.app/Contents/MacOS/firefox';
var BIDI_PORT = 9341;
var BASE = L.BASE;
var check = function (n, c, d) { L.check('firefox ' + n, c, d); };
function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

var profile = fs.mkdtempSync(path.join(os.tmpdir(), 'omochix-dl-firefox-'));
fs.writeFileSync(path.join(profile, 'user.js'), [
    ['browser.shell.checkDefaultBrowser', false], ['datareporting.policy.dataSubmissionEnabled', false], ['datareporting.healthreport.uploadEnabled', false],
    ['toolkit.telemetry.enabled', false], ['app.update.auto', false], ['app.normandy.enabled', false], ['browser.safebrowsing.malware.enabled', false],
    ['browser.safebrowsing.phishing.enabled', false], ['network.captive-portal-service.enabled', false], ['network.connectivity-service.enabled', false],
    ['services.settings.server', 'http://127.0.0.1:1/'], ['browser.aboutwelcome.enabled', false], ['media.autoplay.default', 0], ['dom.disable_open_during_load', false]
].map(function (p) { return 'user_pref(' + JSON.stringify(p[0]) + ', ' + JSON.stringify(p[1]) + ');'; }).join('\n'));

var ws, nextId = 1, pending = new Map(), events = [], ctx;
function send(method, params) {
    var id = nextId++;
    ws.send(JSON.stringify({ id: id, method: method, params: params || {} }));
    return new Promise(function (res, rej) { pending.set(id, { res: res, rej: rej, method: method }); });
}
async function js(expr, context) {
    var r = await send('script.evaluate', { expression: '(async () => JSON.stringify(await (' + expr + ')))()', target: { context: context || ctx }, awaitPromise: true });
    if (r.type === 'exception') throw new Error('script: ' + expr.slice(0, 80) + ' :: ' + JSON.stringify(r.exceptionDetails).slice(0, 300));
    return r.result.value === undefined ? undefined : JSON.parse(r.result.value);
}
async function open(url, width, height) {
    await send('browsingContext.setViewport', { context: ctx, viewport: { width: width || 1280, height: height || 900 } });
    await send('browsingContext.navigate', { context: ctx, url: BASE + url, wait: 'complete' });
    await wait(400);
}
async function child(urlPart) {
    for (var i = 0; i < 60; i++) {
        var tree = await send('browsingContext.getTree', { root: ctx });
        var kids = (tree.contexts[0].children || []).filter(function (c) { return c.url.indexOf(urlPart) !== -1; });
        if (kids.length) {
            try { if (await js('window.__probe && window.__probe.done', kids[0].context)) return kids[0].context; } catch (e) { /* loading */ }
        }
        await wait(150);
    }
    throw new Error('no child frame ' + urlPart);
}
function threw(v, n) { return typeof v === 'string' && v.indexOf('threw:' + (n || '')) === 0; }
function serverHits() {
    var log = L.SITE + '/logs/requests.log';
    return fs.existsSync(log) ? fs.readFileSync(log, 'utf8').split('\n').filter(function (l) { return l.indexOf('probe=') !== -1; }) : null;
}
var userContexts = 0;

async function run() {
    var PROBE = '/design/demo/web/isolation-probe/';
    var log = L.SITE + '/logs/requests.log';
    if (fs.existsSync(log)) fs.writeFileSync(log, '');
    await send('storage.setCookie', { cookie: { name: 'omx_b4', value: { type: 'string', value: 'visible-to-the-site' }, domain: '127.0.0.1', path: '/', httpOnly: false, secure: false, sameSite: 'lax' } });

    /* embedded */
    await open('/design/web/isolation-probe/');
    await js('(document.querySelector("[data-dl-demo]").scrollIntoView(), true)');
    var f = await child(PROBE);
    await wait(800);
    var r = await js('window.__probe', f);
    var T = 'embedded';
    check(T + ' B2: iframe sandbox is exactly "allow-scripts"', (await js('document.querySelector("[data-dl-demo]").getAttribute("sandbox")')) === 'allow-scripts');
    check(T + ' B3: origin "null"', r.origin === 'null', r.origin);
    check(T + ' B4: positive control, the site page reads the test cookie', (await js('document.cookie')).indexOf('omx_b4=') !== -1);
    check(T + ' B4: cookie read/write throw SecurityError, storage unavailable', threw(r.cookieRead, 'SecurityError') && threw(r.cookieWrite, 'SecurityError') && threw(r.localStorage), [r.cookieRead, r.cookieWrite, r.localStorage]);
    check(T + ' B5: fetch rejected, XHR fails, WebSocket fails', [r.fetchRoot, r.fetchMe, r.fetchPost].every(function (v) { return String(v).indexOf('rejected') === 0; }) && (r.xhr === 'error' || threw(r.xhr)) && (threw(r.webSocket) || r.webSocketError === true), [r.fetchRoot, r.xhr, r.webSocket]);
    check(T + ' B6: parent/top document throw, eval and injected script blocked, no popup', threw(r.parentDocument, 'SecurityError') && threw(r.topDocument, 'SecurityError') && threw(r.eval) && r.inlineScript === 'injected:false' && (r.windowOpen === 'null' || threw(r.windowOpen)), r);
    var detailUrl = await js('location.href');
    await js('(document.querySelector("[data-dl-demo]").contentWindow && true)');
    var before = await js('(() => { const s = document.querySelector("[data-dl-stage]"); return { state: s.getAttribute("data-state"), text: s.querySelector("[data-dl-status]").textContent, len: document.querySelector("main").innerHTML.length }; })()');
    await js('window.__probeSendBad()', f);
    await js('(() => { const x = document.createElement("iframe"); x.srcdoc = "<script>parent.postMessage({type:\\"omx-design:error\\"},\\"*\\")<\\/script>"; document.body.appendChild(x); return true; })()');
    await wait(800);
    var after = await js('(() => { const s = document.querySelector("[data-dl-stage]"); return { state: s.getAttribute("data-state"), text: s.querySelector("[data-dl-status]").textContent, len: document.querySelector("main").innerHTML.length }; })()');
    check(T + ' B7: bad, flooded and forged messages change nothing', before.state === 'ready' && JSON.stringify(before) === JSON.stringify(after) && (await js('location.href')) === detailUrl, [before, after]);
    await js('(document.querySelector("[data-dl-demo]").contentWindow.postMessage({type:"omx-design:theme",theme:"dark"},"*"), true)');
    await wait(250);
    var t1 = await js('document.documentElement.getAttribute("data-theme")', f);
    await js('(document.querySelector("[data-dl-demo]").contentWindow.postMessage({type:"omx-design:theme",theme:"<img src=x onerror=alert(1)>"},"*"), true)');
    await wait(250);
    var t2 = await js('document.documentElement.getAttribute("data-theme")', f);
    check(T + ' B8: valid theme applies, invalid value ignored', t1 === 'dark' && t2 === 'dark' && (await js('document.querySelectorAll("img[src=x]").length', f)) === 0, [t1, t2]);
    await js('window.__probeSendError()', f);
    await wait(300);
    check(T + ' B7 positive control: a valid message from the demo is accepted', (await js('document.querySelector("[data-dl-stage]").getAttribute("data-state")')) === 'error');

    /* direct */
    await open(PROBE);
    for (var i = 0; i < 40 && !(await js('!!(window.__probe && window.__probe.done)')); i++) await wait(150);
    var d = await js('window.__probe');
    check('direct B9: origin null, cookie throws, fetch rejected, no popup, eval blocked', d.origin === 'null' && threw(d.cookieRead, 'SecurityError') && String(d.fetchRoot).indexOf('rejected') === 0 && (d.windowOpen === 'null' || threw(d.windowOpen)) && threw(d.eval), d);
    var tree = await send('browsingContext.getTree', {});
    check('direct B6: no extra window was opened', tree.contexts.length === 1, tree.contexts.map(function (c) { return c.url; }));
    var hits = serverHits();
    if (hits) check('B5/B9: no probe request reached the server', hits.length === 0, hits.slice(0, 5));

    /* real demos */
    for (var s of L.DEMOS) {
        await open('/design/demo/web/' + s + '/');
        await send('input.performActions', { context: ctx, actions: [{ type: 'wheel', id: 'w', actions: [{ type: 'scroll', x: 400, y: 300, deltaX: 0, deltaY: 1200 }] }] }).catch(function () {});
        await wait(1500);
        check('demo ' + s + ': no CSP violation, cookie blocked', (await js('window.__csp.length')) === 0 && (await js('(() => { try { void document.cookie; return false; } catch (e) { return e.name === "SecurityError"; } })()')), await js('window.__csp'));
    }
}

async function list() {
    L.flag(false);
    for (var w of [390, 1440]) {
        await open('/design/web/', w, 900);
        await wait(2500);
        var st = await js('({ sw: document.documentElement.scrollWidth, iw: innerWidth, cards: document.querySelectorAll("[data-dl-card]:not([hidden])").length, playing: [...document.querySelectorAll("video")].filter(v => !v.paused).length })');
        check('list ' + w + 'px: 4 cards, no horizontal scroll, at most 2 loops playing', st.cards === 4 && st.sw <= st.iw + 1 && st.playing <= 2, st);
    }
    var max = 0;
    for (var k = 0; k < 12; k++) { await js('(scrollBy(0, 120), true)'); await wait(250); max = Math.max(max, await js('[...document.querySelectorAll("video")].filter(v => !v.paused).length')); }
    check('list: while scrolling, never more than 2 loops play', max <= 2, max);
    await js('(() => { const s = document.querySelector("#dl-motion_type"); s.value = "ambient"; s.dispatchEvent(new Event("change", { bubbles: true })); return true; })()');
    await wait(300);
    check('list: motion-type filter in place (1 card, URL updated)', (await js('document.querySelectorAll("[data-dl-card]:not([hidden])").length')) === 1 && (await js('location.search')) === '?motion_type=ambient');
    await open('/design/', 1440, 900);
    await wait(2500);
    var top = await js('({ cards: document.querySelectorAll("[data-dl-card]:not([hidden])").length, playing: [...document.querySelectorAll("video[data-dl-video]")].filter(v => !v.paused).length, sw: document.documentElement.scrollWidth, iw: innerWidth })');
    check('top: 6 works (Web + video), at most 2 loops playing, no horizontal scroll', top.cards === 6 && top.playing <= 2 && top.sw <= top.iw + 1, top);
    await open('/design/video/data-chart-explainer/', 1280, 900);
    var v0 = await js('(() => { const v = document.querySelector(".dl-stage__video"); return { paused: v.paused, t: v.currentTime }; })()');
    await js('document.querySelector(".dl-stage__video").play().then(() => true)');
    await wait(1500);
    var v1 = await js('(() => { const v = document.querySelector(".dl-stage__video"); return { paused: v.paused, t: v.currentTime, w: v.videoWidth }; })()');
    check('video detail: no autoplay, plays when asked', v0.paused && v0.t === 0 && !v1.paused && v1.t > 0.3 && v1.w === 1920, [v0, v1]);
}

(async function main() {
    L.flag(true);
    var ff = spawn(FIREFOX, ['-headless', '-no-remote', '-profile', profile, '--remote-debugging-port', String(BIDI_PORT), 'about:blank'], { stdio: ['ignore', 'pipe', 'pipe'] });
    try {
        await Promise.race([new Promise(function (res) { function on(d) { if (/WebDriver BiDi listening/.test(String(d))) res(); } ff.stderr.on('data', on); ff.stdout.on('data', on); }), wait(30000)]);
        ws = new WebSocket('ws://127.0.0.1:' + BIDI_PORT + '/session');
        await new Promise(function (res, rej) { ws.onopen = res; ws.onerror = rej; });
        ws.onmessage = function (m) {
            var d = JSON.parse(m.data);
            if (d.id && pending.has(d.id)) { var p = pending.get(d.id); pending.delete(d.id); if (d.type === 'error') p.rej(new Error(p.method + ': ' + d.error + ' ' + d.message)); else p.res(d.result); }
            else if (d.type === 'event') events.push(d);
        };
        var sess = await send('session.new', { capabilities: {} });
        L.info('Firefox ' + sess.capabilities.browserVersion + ' (headless, WebDriver BiDi)');
        await send('script.addPreloadScript', { functionDeclaration: '() => { window.__csp = []; document.addEventListener("securitypolicyviolation", e => window.__csp.push(e.violatedDirective + " " + e.blockedURI)); }' });
        ctx = (await send('browsingContext.getTree', {})).contexts[0].context;
        await run();
        await list();
        await send('browser.close', {}).catch(function () {});
    } catch (e) {
        L.check('firefox unexpected error', false, String(e && e.stack || e));
    } finally {
        L.flag(false);
        try { ff.kill(); } catch (e) { /* closed */ }
        await wait(500);
        fs.rmSync(profile, { recursive: true, force: true });
    }
    void userContexts;
    process.exit(L.summary('firefox'));
}());
