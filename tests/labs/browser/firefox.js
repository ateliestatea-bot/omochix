#!/usr/bin/env node
/**
 * OmochiX Labs browser checks in Firefox (local only; not part of CI).
 *
 * Drives an installed Firefox over its built-in WebDriver BiDi (no driver
 * download), with a throwaway profile: no telemetry, no updates, no sync.
 * Starts tests/labs/preview-server.php, or uses LABS_ORIGIN (for example a
 * local WordPress QA site) instead. Visits as an anonymous visitor.
 *
 *  - 375 / 768 / 1440 px: no horizontal scroll; console policy (console-policy.js)
 *  - list search and category; JSON, diff and robots.txt: run, copy (pasted back), reset
 *  - token estimate; no unapproved price, id or review banner for a visitor
 *  - keyboard only: Tab order with a visible focus indicator, ⌘+Enter, add a robots rule
 *  - F-4: keyboard focus on a Labs select shows the 2px ring (light and dark)
 *  - a deliberate CSP violation is caught (the console check cannot pass blindly)
 *  - no external request
 *
 *   LABS_FIREFOX_PATH=/Applications/Firefox.app/Contents/MacOS/firefox node tests/labs/browser/firefox.js
 */
'use strict';

var path = require('path');
var fs = require('fs');
var os = require('os');
var spawn = require('child_process').spawn;
var consolePolicy = require('./console-policy');

var root = path.resolve(__dirname, '..', '..', '..');
var FIREFOX = process.env.LABS_FIREFOX_PATH || '/Applications/Firefox.app/Contents/MacOS/firefox';
var PORT = 8793;
var BIDI_PORT = 9335;
var ORIGIN = process.env.LABS_ORIGIN || 'http://127.0.0.1:' + PORT;
var PAGES = ['/labs/', '/labs/json-formatter/', '/labs/text-diff/', '/labs/robots-txt-generator/', '/labs/token-cost-calculator/'];
var K = { Tab: '', Enter: '', Meta: '', Shift: '' };

var pass = 0;
var fail = 0;
function check(name, cond, detail) {
    if (cond) { pass++; console.log('PASS firefox ' + name); }
    else { fail++; console.log('FAIL firefox ' + name + (detail !== undefined ? '\n  ' + JSON.stringify(detail).slice(0, 1500) : '')); }
}
function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

var profile = fs.mkdtempSync(path.join(os.tmpdir(), 'omochix-labs-firefox-'));
fs.writeFileSync(path.join(profile, 'user.js'), [
    ['browser.shell.checkDefaultBrowser', false],
    ['datareporting.policy.dataSubmissionEnabled', false],
    ['datareporting.healthreport.uploadEnabled', false],
    ['toolkit.telemetry.enabled', false],
    ['app.update.auto', false],
    ['app.normandy.enabled', false],
    ['browser.safebrowsing.malware.enabled', false],
    ['browser.safebrowsing.phishing.enabled', false],
    ['network.captive-portal-service.enabled', false],
    ['network.connectivity-service.enabled', false],
    ['services.settings.server', 'http://127.0.0.1:1/'],
    ['browser.aboutwelcome.enabled', false],
    ['accessibility.tabfocus', 7], // Tab reaches links and buttons, like macOS keyboard navigation turned on
].map(function (p) { return 'user_pref(' + JSON.stringify(p[0]) + ', ' + JSON.stringify(p[1]) + ');'; }).join('\n'));

var ws;
var nextId = 1;
var pending = new Map();
var events = [];
var ctx;
function send(method, params) {
    var id = nextId++;
    ws.send(JSON.stringify({ id: id, method: method, params: params || {} }));
    return new Promise(function (res, rej) { pending.set(id, { res: res, rej: rej, method: method }); });
}
async function js(expr) {
    var r = await send('script.evaluate', { expression: '(async () => JSON.stringify(await (' + expr + ')))()', target: { context: ctx }, awaitPromise: true });
    if (r.type === 'exception') throw new Error('script: ' + expr.slice(0, 80) + ' :: ' + JSON.stringify(r.exceptionDetails).slice(0, 300));
    return r.result.value === undefined ? undefined : JSON.parse(r.result.value);
}
function q(sel) { return 'document.querySelector(' + JSON.stringify(sel) + ')'; }
async function click(sel) {
    var r = await send('script.evaluate', { expression: '(() => { const e = ' + q(sel) + '; e.scrollIntoView({ block: "center", behavior: "instant" }); return e; })()', target: { context: ctx }, awaitPromise: false, resultOwnership: 'root' });
    if (r.type === 'exception') throw new Error('no element ' + sel);
    await send('input.performActions', { context: ctx, actions: [{ type: 'pointer', id: 'mouse', parameters: { pointerType: 'mouse' }, actions: [{ type: 'pointerMove', x: 0, y: 0, origin: { type: 'element', element: { sharedId: r.result.sharedId } } }, { type: 'pointerDown', button: 0 }, { type: 'pointerUp', button: 0 }] }] });
    await wait(250);
}
async function keys(seq) {
    var actions = [];
    seq.forEach(function (k) {
        if (Array.isArray(k)) { k.forEach(function (v) { actions.push({ type: 'keyDown', value: v }); }); k.slice().reverse().forEach(function (v) { actions.push({ type: 'keyUp', value: v }); }); }
        else actions.push({ type: 'keyDown', value: k }, { type: 'keyUp', value: k });
    });
    await send('input.performActions', { context: ctx, actions: [{ type: 'key', id: 'kbd', actions: actions }] });
    await wait(150);
}
async function setValue(sel, v) {
    await js('(() => { const e = ' + q(sel) + '; e.focus(); e.value = ' + JSON.stringify(v) + '; e.dispatchEvent(new Event("input", { bubbles: true })); e.dispatchEvent(new Event("change", { bubbles: true })); return true; })()');
    await wait(400);
}
var policy = '';
async function open(url, width) {
    await send('browsingContext.setViewport', { context: ctx, viewport: { width: width, height: 900 } });
    events.length = 0;
    policy = '';
    await send('browsingContext.navigate', { context: ctx, url: ORIGIN + url, wait: 'complete' });
    await send('browsingContext.activate', { context: ctx });
    await js('(window.focus(), true)');
    await wait(500);
}
// Paste with a real ⌘V into a scratch textarea: reads what the page put on the clipboard.
async function pasted() {
    await js('(() => { const t = document.createElement("textarea"); t.id = "__labs_paste"; t.style.cssText = "position:fixed;top:0;left:0;width:10px;height:10px"; document.body.appendChild(t); t.focus(); return true; })()');
    await keys([[K.Meta, 'v']]);
    await wait(200);
    return js('(() => { const t = document.getElementById("__labs_paste"); const v = t.value; t.remove(); return v; })()');
}
function consoleMessages() {
    return events.filter(function (e) { return e.method === 'log.entryAdded'; }).map(function (e) { return { type: e.params.level === 'warn' ? 'warning' : e.params.level, text: e.params.text, url: (e.params.source && e.params.source.url) || '' }; });
}
function externalRequests() {
    return events.filter(function (e) { return e.method === 'network.beforeRequestSent'; }).map(function (e) { return e.params.request.url; }).filter(function (u) { return u.indexOf(ORIGIN) !== 0 && !/^(data|blob|about):/.test(u); });
}
var external = [];
async function consoleCheck(label) {
    var vio = await js('window.__csp || []');
    var r = consolePolicy.review('firefox', consoleMessages(), policy);
    external = external.concat(externalRequests());
    check(label + ': console has no CSP violation, unknown CSP notice or error' + (Object.keys(r.known).length ? ' (known: ' + JSON.stringify(r.known) + ')' : ''), r.ok && vio.length === 0, { bad: r.bad, events: vio });
    return r;
}
// Setup, not a check: under automation Firefox hands keyboard focus to the page only after it has
// handled real key input on a control. This sequence does that. Every keyboard check below also
// requires document.hasFocus() or :focus-visible, so a page without focus can never pass.
async function takeFocus() {
    await open('/labs/robots-txt-generator/', 1440);
    await click('[data-labs-sample]');
    await click('h1');
    for (var i = 0; i < 40; i++) { await keys([K.Tab]); if (await js('document.activeElement === ' + q('[data-labs-add-rule]'))) break; }
    await keys([K.Enter]); await keys([' ']); await keys([K.Enter]);
    await keys('/x/'.split(''));
}
var visible = 'Array.from(document.querySelectorAll("[data-labs-card]")).filter(e => e.offsetParent !== null).length';

async function run() {
    // --- layout and console ------------------------------------------------
    for (var w of [375, 768, 1440]) {
        for (var u of PAGES) {
            await open(u, w);
            var r = await js('({ sw: document.documentElement.scrollWidth, iw: window.innerWidth, labs: !!document.querySelector("main.labs") })');
            check(w + 'px ' + u + ': no horizontal scroll', r.labs && r.sw <= r.iw + 1, r);
            check(w + 'px ' + u + ': CSP Report-Only header has no frame-ancestors', policy !== '' && policy.indexOf('frame-ancestors') === -1 && policy.indexOf("worker-src 'self' blob:") !== -1, policy);
            await consoleCheck(w + 'px ' + u);
        }
    }
    // --- list --------------------------------------------------------------
    await open('/labs/', 375);
    check('list: 4 tool cards', (await js(visible)) === 4);
    await setValue('#labs-search-q', 'json');
    check('list: search "json" → 1 card and count', (await js(visible)) === 1 && /1件のツール/.test(await js(q('[data-labs-count]') + '.textContent')));
    await setValue('#labs-search-q', '');
    await click('[data-labs-cat="dev"]');
    check('list: category "dev" → 2 cards, aria-current, ?cat=dev', (await js(visible)) === 2 && (await js(q('[data-labs-cat="dev"]') + '.getAttribute("aria-current")')) === 'true' && /cat=dev/.test(await js('location.href')));
    await click('[data-labs-cat=""]');
    check('list: all → 4 cards', (await js(visible)) === 4);
    await consoleCheck('list');
    // --- JSON --------------------------------------------------------------
    await open('/labs/json-formatter/', 1440);
    await setValue('#labs-json-input', '{"a":1,"b":[1,2]}');
    await click('[data-action="format"]');
    var out = await js(q('#labs-json-output') + '.value');
    check('json: format', out.indexOf('\n  "b": [') !== -1);
    await click('[data-labs-copy]');
    check('json: copy → pasted text equals the output', (await pasted()) === out);
    await setValue('#labs-json-input', '{"a":1,}');
    await click('[data-action="format"]');
    var err = await js(q('[data-labs-error]') + '.textContent');
    check('json: trailing comma → line, column and caret; copy disabled', /1行目 \d+文字目/.test(err) && err.indexOf('^') !== -1 && await js(q('[data-labs-copy]') + '.disabled'), err);
    await click('[data-labs-reset]');
    check('json: reset', (await js(q('#labs-json-input') + '.value + ' + q('#labs-json-output') + '.value')) === '');
    await consoleCheck('json');
    // --- diff --------------------------------------------------------------
    await open('/labs/text-diff/', 1440);
    await setValue('#labs-diff-old', 'おはようございます\n今日は晴れです');
    await setValue('#labs-diff-new', 'おはようございます\n今日は雨です');
    await click('[data-labs-run]');
    await wait(400);
    check('diff: Japanese 2 lines → 追加 1行・削除 1行', /追加 1行・削除 1行/.test(await js(q('[data-labs-summary]') + '.textContent')) && (await js('document.querySelectorAll(".labs-diff ins").length')) > 0);
    await click('[data-labs-copy]');
    var diff = await pasted();
    check('diff: copy → unified diff pasted back', /^--- 変更前\n\+\+\+ 変更後\n@@ /.test(diff) && diff.indexOf('+今日は雨です') !== -1, diff);
    await click('[data-labs-reset]');
    check('diff: reset', (await js(q('#labs-diff-old') + '.value')) === '' && await js(q('[data-labs-result]') + '.hidden'));
    await consoleCheck('diff');
    // --- robots ------------------------------------------------------------
    await open('/labs/robots-txt-generator/', 1440);
    await click('[data-labs-sample]');
    var robots = await js(q('#labs-robots-output') + '.value');
    check('robots: sample → WordPress rules, AI crawlers, sitemap', robots.indexOf('Disallow: /wp-admin/') !== -1 && robots.indexOf('User-agent: GPTBot') !== -1 && /Sitemap: https:\/\//.test(robots));
    await click('[data-labs-copy]');
    check('robots: copy → pasted text equals the output', (await pasted()) === robots);
    await click('[data-labs-reset]');
    check('robots: reset', (await js(q('#labs-robots-output') + '.value')) === 'User-agent: *\nAllow: /\n');
    await consoleCheck('robots');
    // --- cost (visitor) ----------------------------------------------------
    await open('/labs/token-cost-calculator/?anon=1', 1440);
    var html = await js('document.documentElement.outerHTML');
    check('cost: visitor → no model rows, notice shown, no review banner', (await js('document.querySelectorAll("[data-labs-model]").length')) === 0 && await js('!!' + q('[data-labs-no-models]') + ' && ' + q('[data-labs-no-models]') + '.offsetParent !== null') && html.indexOf('labs-notice--review') === -1);
    var leaked = ['gpt-6-astra', 'gpt-5.4-mini', 'claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5', 'gemini-4', 'gemini-3.8', 'gemini-3.5'].filter(function (id) { return html.indexOf(id) !== -1; });
    check('cost: no unapproved model id in the page', leaked.length === 0, leaked);
    await setValue('#labs-cost-text', 'OmochiX Labsのトークン推定をFirefoxで確認します。これはテスト用の文章です。');
    var tok = await js(q('[data-labs-tokens]') + '.textContent');
    await click('[data-labs-use="input"]');
    var n = Number(await js(q('#labs-cost-input') + '.value'));
    check('cost: estimate 約N, 「入力トークンに使う」 copies it', /^約\d+/.test(tok) && n > 0 && tok.indexOf(String(n)) !== -1, { tok: tok, n: n });
    await consoleCheck('cost');
    // --- keyboard ----------------------------------------------------------
    await takeFocus();
    await open('/labs/robots-txt-generator/', 1440);
    await click('h1');
    var reached = false;
    for (var i = 0; i < 60 && !reached; i++) { await keys([K.Tab]); reached = await js('document.activeElement === ' + q('[data-labs-add-rule]')); }
    await keys([K.Enter]);
    await wait(300);
    await keys('/kb/'.split(''));
    await wait(300);
    check('robots: keyboard only — Tab to 「ルールを追加」, Enter, type a path', reached && (await js(q('#labs-robots-output') + '.value')).indexOf('Disallow: /kb/') !== -1);
    for (var page of ['/labs/', '/labs/json-formatter/', '/labs/robots-txt-generator/']) {
        await takeFocus();
        await open(page, 1440);
        await click('h1');
        var seen = [];
        var noRing = [];
        for (var t = 0; t < 150; t++) {
            await keys([K.Tab]);
            var f = await js('(() => { const e = document.activeElement; if (!e || !e.closest("main")) return null; const s = getComputedStyle(e), a = getComputedStyle(e, "::after"); return { id: e.tagName + (e.id ? "#" + e.id : "") + (e.getAttribute("href") ? " " + e.getAttribute("href") : ""), ring: (s.outlineStyle !== "none" && parseFloat(s.outlineWidth) > 0) || s.boxShadow !== "none" || (a.outlineStyle !== "none" && parseFloat(a.outlineWidth) > 0), focus: document.hasFocus() }; })()');
            if (f && f.focus) { seen.push(f.id); if (!f.ring) noRing.push(f.id); }
            if (!f && seen.length > 0) break;
        }
        check('keyboard ' + page + ': ' + new Set(seen).size + ' controls reached with Tab, each with a focus indicator', seen.length > 3 && noRing.length === 0, Array.from(new Set(noRing)));
    }
    await open('/labs/json-formatter/', 1440);
    await setValue('#labs-json-input', '{"k":true}');
    await keys([[K.Meta, K.Enter]]);
    await wait(300);
    check('json: ⌘+Enter runs from the keyboard', (await js(q('#labs-json-output') + '.value')) === '{\n  "k": true\n}');
    // --- F-4 -----------------------------------------------------------------
    for (var dark of [false, true]) {
        await takeFocus();
        await open('/labs/json-formatter/' + (dark ? '?theme=dark' : ''), 768);
        await js('(' + q('#labs-json-sort') + '.focus(), true)');
        await keys([[K.Shift, K.Tab]]);
        var sel = await js('(() => { const e = document.activeElement, s = getComputedStyle(e); return { id: e.id, fv: e.matches(":focus-visible"), style: s.outlineStyle, width: parseFloat(s.outlineWidth), theme: document.documentElement.getAttribute("data-theme") || "" }; })()');
        check('F-4 ' + (dark ? 'dark' : 'light') + ': keyboard focus on a Labs select shows the 2px ring', sel.id === 'labs-json-indent' && sel.fv && sel.style === 'solid' && sel.width >= 2 && (!dark || sel.theme === 'dark'), sel);
    }
    // --- the console check catches a real violation ---------------------------
    await open('/labs/json-formatter/', 1440);
    await js('(() => { try { new Worker("data:application/javascript,0"); } catch (e) {} return true; })()');
    await wait(600);
    var self = consolePolicy.review('firefox', consoleMessages(), policy);
    check('console self-test: a deliberate worker-src violation is reported as a failure', !self.ok && self.bad.some(function (b) { return /^violation: /.test(b); }), self.bad);
    check('no external requests', external.length === 0, Array.from(new Set(external)));
}

(async function main() {
    var server = null;
    if (!process.env.LABS_ORIGIN) {
        server = spawn('php', ['-S', '127.0.0.1:' + PORT, '-t', root, path.join(root, 'tests/labs/preview-server.php')], { stdio: 'ignore' });
        await wait(700);
    }
    var ff = spawn(FIREFOX, ['-headless', '-no-remote', '-profile', profile, '--remote-debugging-port', String(BIDI_PORT), 'about:blank'], { stdio: ['ignore', 'pipe', 'pipe'] });
    try {
        await Promise.race([new Promise(function (res) { function on(d) { if (/WebDriver BiDi listening/.test(String(d))) res(); } ff.stderr.on('data', on); ff.stdout.on('data', on); }), wait(30000)]);
        ws = new WebSocket('ws://127.0.0.1:' + BIDI_PORT + '/session');
        await new Promise(function (res, rej) { ws.onopen = res; ws.onerror = rej; });
        ws.onmessage = function (m) {
            var d = JSON.parse(m.data);
            if (d.id && pending.has(d.id)) { var p = pending.get(d.id); pending.delete(d.id); if (d.type === 'error') p.rej(new Error(p.method + ': ' + d.error + ' ' + d.message)); else p.res(d.result); }
            else if (d.type === 'event') {
                events.push(d);
                if (d.method === 'network.responseCompleted' && d.params.navigation && d.params.context === ctx) {
                    (d.params.response.headers || []).forEach(function (h) { if (h.name.toLowerCase() === 'content-security-policy-report-only') policy = h.value.value; });
                }
            }
        };
        var sess = await send('session.new', { capabilities: {} });
        console.log('Firefox ' + sess.capabilities.browserVersion + ' (headless) → ' + ORIGIN);
        await send('session.subscribe', { events: ['log.entryAdded', 'network.beforeRequestSent', 'network.responseCompleted'] });
        await send('script.addPreloadScript', { functionDeclaration: '() => { window.__csp = []; document.addEventListener("securitypolicyviolation", e => window.__csp.push(e.violatedDirective + " " + e.blockedURI)); }' });
        ctx = (await send('browsingContext.getTree', {})).contexts[0].context;
        await run();
        await send('browser.close', {}).catch(function () {});
    } catch (e) {
        fail++;
        console.log('FAIL firefox unexpected: ' + (e && e.stack || e));
    } finally {
        try { ff.kill(); } catch (e) { /* already closed */ }
        if (server) server.kill();
        await wait(500);
        fs.rmSync(profile, { recursive: true, force: true });
    }
    console.log('\n' + pass + ' passed, ' + fail + ' failed');
    process.exit(fail ? 1 : 0);
}());
