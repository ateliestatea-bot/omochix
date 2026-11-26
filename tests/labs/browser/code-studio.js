#!/usr/bin/env node
/**
 * OmochiX Code Studio browser checks (local only; not part of CI).
 *
 * Security (docs/labs/CODE-STUDIO-DESIGN.md §5), in Chromium, WebKit and Firefox:
 *  - no script from the learner's HTML / CSS runs (parent flag, dialogs, popups)
 *  - no request reaches another origin (a local "sink" server counts hits)
 *  - the frame cannot navigate the page, even when the sanitiser is bypassed
 *    (sandbox layer) and cannot load anything when DOMPurify is bypassed (CSP layer)
 *  - size / element limits, large input stays responsive
 *  - nothing is stored (cookies, localStorage, sessionStorage) or posted
 * Function: lessons, checks, widths, compare, reset, copy, download, ?lesson=.
 * Also keyboard use, dark mode, reduced motion, 375 px, axe (Chromium).
 *
 * Run (see run.js for the LABS_* variables):
 *   LABS_PLAYWRIGHT=… LABS_AXE=… node tests/labs/browser/code-studio.js
 * Against a WordPress QA site where the tool is published: add LABS_QA_ORIGIN=http://qa-site.local
 * The sink is 127.0.0.1 on another port: nothing leaves this machine.
 */
'use strict';

var path = require('path');
var http = require('http');
var spawn = require('child_process').spawn;
var fs = require('fs');

var root = path.resolve(__dirname, '..', '..', '..');
/** The page's own core (run in Node here only to measure what reached the preview / the saved file). */
var Studio = require(path.join(root, 'wp-theme/omochix-theme/assets/js/labs/core/codestudio.js'));
var playwright = require(process.env.LABS_PLAYWRIGHT || 'playwright');
var axePath = process.env.LABS_AXE || require.resolve('axe-core/axe.min.js');
var PORT = 8796;
// LABS_QA_ORIGIN: run against a real WordPress QA site that has Code Studio published (no stub servers, no release checks).
var QA = (process.env.LABS_QA_ORIGIN || '').replace(/\/$/, '');
var ORIGIN = QA || 'http://127.0.0.1:' + PORT;          // every tool published (Code Studio is a draft in the shipped catalog)
var RELEASE_PORT = 8797;
var RELEASE_ORIGIN = 'http://127.0.0.1:' + RELEASE_PORT;
var SINK_PORT = 8798;
var SINK = 'http://127.0.0.1:' + SINK_PORT;
var URL_ = '/labs/code-studio/';
var EVENTS = ['labs_tool_view', 'labs_tool_run', 'labs_tool_success', 'labs_tool_error', 'labs_tool_copy', 'labs_related_click'];
var SECRET = 'SECRET-CODE-9c1e';

var pass = 0;
var fail = 0;
function check(name, cond, detail) {
    if (cond) { pass++; console.log('PASS ' + name); }
    else { fail++; console.log('FAIL ' + name + (detail !== undefined ? '\n  ' + JSON.stringify(detail).slice(0, 1500) : '')); }
}
function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

var sinkHits = [];
function startSink() {
    var server = http.createServer(function (req, res) { sinkHits.push(req.method + ' ' + req.url); res.writeHead(200, { 'Content-Type': 'text/plain', 'Access-Control-Allow-Origin': '*' }); res.end('sink'); });
    return new Promise(function (resolve) { server.listen(SINK_PORT, '127.0.0.1', function () { resolve(server); }); });
}
function startServer(port, publishedCopy) {
    var env = Object.assign({}, process.env, { LABS_PREVIEW_PUBLISHED_COPY: publishedCopy ? '1' : '' });
    var proc = spawn(process.env.LABS_PHP || 'php', ['-S', '127.0.0.1:' + port, '-t', root, path.join(root, 'tests/labs/preview-server.php')], { stdio: 'ignore', env: env });
    return new Promise(function (resolve) { setTimeout(function () { resolve(proc); }, 900); });
}

async function open(browser, width, url, opts) {
    var o = opts || {};
    var context = await browser.newContext({ viewport: { width: width, height: 900 }, colorScheme: o.dark ? 'dark' : 'light', reducedMotion: o.calm ? 'reduce' : 'no-preference', acceptDownloads: true });
    if (browser.browserType().name() === 'chromium') await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: ORIGIN });
    var page = await context.newPage();
    // On the QA site there is no ?gtag=1 stub: record the events the same way, before the page's scripts run.
    if (QA) await page.addInitScript(function () { window.__labsEvents = []; window.gtag = function () { window.__labsEvents.push(Array.prototype.slice.call(arguments)); }; });
    var s = { page: page, context: context, requests: [], errors: [], dialogs: [], popups: [], status: 0, posts: [], console: [] };
    page.on('console', function (m) { if (m.type() === 'error' || m.type() === 'warning' || /Content Security Policy|CSP/i.test(m.text())) s.console.push(m.type() + ': ' + m.text()); });
    page.on('request', function (r) { s.requests.push(r.url()); if (r.method() !== 'GET') s.posts.push(r.method() + ' ' + r.url()); });
    page.on('response', function (r) { if (r.url().split('?')[0] === ((o.origin || ORIGIN) + url).split('?')[0]) s.status = r.status(); });
    page.on('pageerror', function (e) { s.errors.push(String(e)); });
    page.on('dialog', function (d) { s.dialogs.push(d.type() + ':' + d.message()); d.accept(); });
    context.on('page', function (p) { if (p !== page) s.popups.push(p.url()); });
    await page.goto((o.origin || ORIGIN) + url);
    await wait(500);
    return s;
}

/** Put code in the editors the way a learner does (input event → debounce → render). */
async function write(page, html, css) {
    if (html !== null) await page.fill('#labs-studio-html', html);
    if (css !== null && css !== undefined) await page.fill('#labs-studio-css', css);
    await wait(650);
}
function srcdoc(page) { return page.evaluate(function () { return document.querySelector('[data-studio-stage] iframe').srcdoc; }); }
function body(doc) { return doc.slice(doc.indexOf('<body>')); }

var HTML_ATTACKS = [
    '<script>parent.__pwned = 1; top.__pwned = 1; alert(1)</script>',
    '<img src="' + SINK + '/img.png">',
    '<img src=x onerror="parent.__pwned=1;alert(1)">',
    '<img src="data:image/png;base64,AAAA" onload="parent.__pwned=1">',
    '<svg onload="parent.__pwned=1"><script>parent.__pwned=1</script></svg>',
    '<svg><image href="' + SINK + '/svg.png"/></svg>',
    '<iframe src="' + SINK + '/frame"></iframe>',
    '<iframe srcdoc="<script>parent.parent.__pwned=1</script>"></iframe>',
    '<object data="' + SINK + '/object"></object><embed src="' + SINK + '/embed">',
    '<link rel="stylesheet" href="' + SINK + '/link.css">',
    '<link rel="prefetch" href="' + SINK + '/prefetch"><link rel="preconnect" href="' + SINK + '">',
    '<meta http-equiv="refresh" content="0;url=' + SINK + '/refresh">',
    '<base href="' + SINK + '/"><img src="base.png"><a href="#x" id="basefrag">x</a>',
    '<style>@import "' + SINK + '/import.css"; body{background:url(' + SINK + '/style.png)}</style>',
    '<div style="background:url(' + SINK + '/inline.png)">x</div>',
    '<div style="background:u\\72l(' + SINK + '/escape.png)">x</div>',
    '<form action="' + SINK + '/form" method="post"><input name="a" value="b"><button>go</button></form>',
    '<button formaction="' + SINK + '/formaction">x</button>',
    '<input type="image" src="' + SINK + '/input.png">',
    '<video src="' + SINK + '/v.mp4" poster="' + SINK + '/poster.png" autoplay></video><audio src="' + SINK + '/a.mp3" autoplay></audio>',
    '<img srcset="' + SINK + '/srcset.png 1x" src="data:image/png;base64,AAAA">',
    '<picture><source srcset="' + SINK + '/picture.png"><img alt=""></picture>',
    '<a href="javascript:parent.__pwned=1" id="j">x</a>',
    '<a href="' + SINK + '/a" ping="' + SINK + '/ping" target="_top">x</a>',
    '<a href="//127.0.0.1:' + SINK_PORT + '/schemeless" target="_blank">x</a>',
    '<details open ontoggle="parent.__pwned=1"><summary>x</summary></details>',
    '<body onload="parent.__pwned=1"><div onclick="parent.__pwned=1" onmouseover="parent.__pwned=1">x</div>',
    '<math><mtext><table><mglyph><style><img src=x onerror="parent.__pwned=1">',
    '<noscript><p title="</noscript><img src=x onerror=parent.__pwned=1>">',
    '<template><script>parent.__pwned=1</script></template>',
    '<p id="location">x</p><form id="document"></form><img name="cookie">',
    '<table background="' + SINK + '/tablebg.png"><tr><td>x</td></tr></table>',
    '</body></html><script>parent.__pwned=1</script><html><body>',
    '<!--><script>parent.__pwned=1</script>-->',
    '<textarea></textarea><script>parent.__pwned=1</script>',
    '<img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIG9ubG9hZD0icGFyZW50Ll9fcHduZWQ9MSIvPg==">',
    '<marquee onstart="parent.__pwned=1">x</marquee><isindex action="' + SINK + '/isindex">',
    '<div data-x="1" contenteditable autofocus onfocus="parent.__pwned=1">x</div>'
];
var CSS_ATTACKS = [
    '@import url(' + SINK + '/css-import.css);',
    '@import "' + SINK + '/css-import2.css";',
    'body{background:url(' + SINK + '/css-bg.png)}',
    'body{background:URL( "' + SINK + '/css-bg2.png" )}',
    'body{background:u\\72l(' + SINK + '/css-esc.png)}',
    'body{background:image-set("' + SINK + '/css-set.png" 1x)}',
    'body{background:-webkit-image-set(url(' + SINK + '/css-wset.png) 1x)}',
    '@font-face{font-family:x;src:url(' + SINK + '/css-font.woff2)} body{font-family:x}',
    'body{cursor:url(' + SINK + '/css-cursor.cur),auto} li{list-style:url(' + SINK + '/css-list.png)}',
    'p::before{content:url(' + SINK + '/css-content.png)}',
    '</style><script>parent.__pwned=1</script><img src="' + SINK + '/css-break.png"><style>',
    'body{width:expression(parent.__pwned=1)} a{-moz-binding:url(' + SINK + '/css-xbl.xml#x)}',
    '@media (min-width:1px){body{background:url(' + SINK + '/css-media.png)}}',
    '@supports (display:grid){body{border-image:url(' + SINK + '/css-supports.png) 1}}'
];

async function securityChecks(browser, name) {
    var s = await open(browser, 1200, URL_ + '?gtag=1');
    var p = s.page;
    check(name + ' page: 200, no script error', s.status === 200 && s.errors.length === 0, { status: s.status, errors: s.errors });
    var frames = await p.evaluate(function () { return Array.prototype.map.call(document.querySelectorAll('iframe'), function (f) { return { sandbox: f.getAttribute('sandbox'), src: f.getAttribute('src'), has: f.hasAttribute('sandbox'), ref: f.getAttribute('referrerpolicy') }; }); });
    check(name + ' frame: one preview frame, sandbox="" (no allow-*), no src, no-referrer', frames.length === 1 && frames[0].has && frames[0].sandbox === '' && frames[0].src === null && frames[0].ref === 'no-referrer', frames);
    var first = await srcdoc(p);
    check(name + ' frame: document starts with the enforced policy', first.indexOf('<!doctype html><html lang="ja"><head><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:; form-action \'none\'"><meta charset="utf-8">') === 0 && first.indexOf('<base') === -1);
    var startUrl = p.url();
    var before = s.requests.length;
    sinkHits.length = 0;

    var leaks = [];
    for (var i = 0; i < HTML_ATTACKS.length; i++) {
        await write(p, '<p>' + SECRET + '</p>' + HTML_ATTACKS[i], 'p{color:red}');
        var b = body(await srcdoc(p));
        if (/<script|<iframe|<object|<embed|<link|<meta|<base|<form|<svg|<math|<style|<video|<audio|<template|<source|\son[a-z]+\s*=|javascript:|srcset|formaction|ping=|target=|background=|href|https?:|url\(/i.test(b)) leaks.push(i + ': ' + b.slice(0, 300));
    }
    check(name + ' html: ' + HTML_ATTACKS.length + ' attack inputs leave no script, handler, loader, form or URL in the preview document', leaks.length === 0, leaks);
    var cssLeaks = [];
    for (var k = 0; k < CSS_ATTACKS.length; k++) {
        await write(p, '<p>x</p><ul><li>y</li></ul>', CSS_ATTACKS[k]);
        var d = await srcdoc(p);
        if (/<|@import|@font-face|https?:|url\(|\\/.test(d.slice(d.indexOf('</style><style>') + 15, d.lastIndexOf('</style><style>'))) || (d.match(/<\/style>/g) || []).length !== 3) cssLeaks.push(k + ': ' + d.slice(-400));
    }
    check(name + ' css: ' + CSS_ATTACKS.length + ' attack inputs leave no import, font, remote URL, escape or markup in the preview document', cssLeaks.length === 0, cssLeaks);
    // Click what is clickable inside the frame (links / buttons survive as inert elements).
    await write(p, '<a href="' + SINK + '/click" id="a1">link</a> <a href="#top" id="a2">frag</a> <button id="b1">b</button><form action="' + SINK + '/submit"><button id="b2">s</button></form>', '');
    var fl = p.frameLocator('[data-studio-stage] iframe');
    for (var sel of ['#a1', '#a2', '#b1', '#b2']) { try { await fl.locator(sel).click({ timeout: 1500 }); } catch (e) { /* removed or not clickable: fine */ } }
    await wait(500);
    check(name + ' isolation: no learner script ran in the page (flag), no dialog, no popup', (await p.evaluate(function () { return window.__pwned === undefined; })) && s.dialogs.length === 0 && s.popups.length === 0, { dialogs: s.dialogs, popups: s.popups });
    check(name + ' isolation: the page did not navigate', p.url() === startUrl, p.url());
    check(name + ' network: no request to another origin (sink hits 0)', sinkHits.length === 0, sinkHits);
    check(name + ' network: no request at all while editing (nothing is sent to the server)', s.requests.length === before, s.requests.slice(before));


    // Review 2026-11-20: plain attributes survive, src is a small bitmap only, joined CSS is sanitised again.
    await write(p, '<input type="checkbox" checked><input type="range" min="1" max="5"><table><tr><td colspan="2" rowspan="3">x</td></tr></table><ol start="3"><li>a</li></ol>'
        + '<img src="data:image/png;base64,AAAA" width="10" height="20" alt="x"><p lang="en" dir="rtl" tabindex="0">t</p><textarea rows="3" cols="9"></textarea><blockquote cite="' + SINK + '/cite">q</blockquote>', '');
    var kept = body(await srcdoc(p));
    check(name + ' html: plain attributes are kept (type, min, max, colspan, rowspan, start, width, height, lang, dir, tabindex, rows, cols); cite is not',
        ['type="checkbox"', 'type="range"', 'min="1"', 'max="5"', 'colspan="2"', 'rowspan="3"', 'start="3"', 'width="10"', 'height="20"', 'lang="en"', 'dir="rtl"', 'tabindex="0"', 'rows="3"', 'cols="9"', 'src="data:image/png;base64,AAAA"'].every(function (t) { return kept.indexOf(t) !== -1; }) && kept.indexOf('cite') === -1, kept);
    await write(p, '<img id="i1" src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22%3E%3C/svg%3E"><img id="i2" src="data:image/svg+xml;base64,AAAA"><img id="i3" src="data:text/html,x"><img id="i4" src="x.png"><img id="i5" src="//127.0.0.1:' + SINK_PORT + '/pr.png"><input type="image" src="' + SINK + '/input.png">', '');
    var imgs = body(await srcdoc(p));
    check(name + ' html: src other than a small inline bitmap is removed (svg, text, relative, protocol-relative, input type=image)', imgs.indexOf('src') === -1 && imgs.indexOf('id="i1"') !== -1, imgs);
    var joined = ['@im@im@im@import;port;port;port "' + SINK + '/join1.css";', '@import "//127.0.0.1:' + SINK_PORT + '/join2{.css";', 'p{background:uexpression(rl(//127.0.0.1:' + SINK_PORT + '/join3.png)}', '@im@charset;port "//127.0.0.1:' + SINK_PORT + '/join4.css";'];
    var joinLeaks = [];
    for (var j = 0; j < joined.length; j++) {
        await write(p, '<p style="background:u@import;rl(//127.0.0.1:' + SINK_PORT + '/join-style.png)">x</p>', joined[j]);
        var jd = await srcdoc(p);
        if (/@import|url\(/i.test(jd)) joinLeaks.push(j + ': ' + jd.slice(-300));
    }
    check(name + ' css: text joined by a removal (4 inputs, and a style attribute) leaves no @import or url(', joinLeaks.length === 0, joinLeaks);
    check(name + ' security run: no script error in the page', s.errors.length === 0, s.errors);

    // Layer test 1: sandbox alone. Put a hostile document straight into the frame (no sanitiser, no policy).
    sinkHits.length = 0;
    await p.evaluate(function (sink) {
        document.querySelector('[data-studio-stage] iframe').srcdoc = '<script>try{parent.__pwned=1}catch(e){} try{top.location="' + sink + '/top"}catch(e){} try{window.open("' + sink + '/open")}catch(e){} try{alert(1)}catch(e){} try{fetch("' + sink + '/fetch")}catch(e){}</script><a id="t" href="' + sink + '/toplink" target="_top">t</a><form id="f" action="' + sink + '/form2" target="_top"><button id="g">g</button></form>';
    }, SINK);
    await wait(600);
    for (var sel2 of ['#t', '#g']) { try { await p.frameLocator('[data-studio-stage] iframe').locator(sel2).click({ timeout: 1500 }); } catch (e) { /* blocked */ } }
    await wait(600);
    check(name + ' sandbox layer: with the sanitiser bypassed, no script runs and the page is not navigated, no popup, no form post', (await p.evaluate(function () { return window.__pwned === undefined; })) && p.url() === startUrl && s.popups.length === 0 && s.dialogs.length === 0 && !sinkHits.some(function (h) { return /\/(top|open|fetch|form2)/.test(h); }), { url: p.url(), hits: sinkHits, popups: s.popups });

    // Layer test 2: the policy alone. Build the document with hostile markup that skipped DOMPurify.
    sinkHits.length = 0;
    await p.evaluate(function (sink) {
        var doc = window.OmochixLabsStudio.buildDocument({ html: '<img src="' + sink + '/csp-img.png"><link rel="stylesheet" href="' + sink + '/csp-link.css"><iframe src="' + sink + '/csp-frame"></iframe><object data="' + sink + '/csp-object"></object><video src="' + sink + '/csp-video.mp4" autoplay></video><div style="background:url(' + sink + '/csp-inline.png)">x</div><meta http-equiv="refresh" content="0;url=' + sink + '/csp-refresh">', css: '' });
        document.querySelector('[data-studio-stage] iframe').srcdoc = doc;
    }, SINK);
    await wait(1200);
    var cspHits = sinkHits.filter(function (h) { return !/csp-refresh/.test(h); });
    check(name + ' policy layer: with DOMPurify bypassed, the enforced policy blocks every load', cspHits.length === 0 && p.url() === startUrl, sinkHits);
    if (sinkHits.length !== cspHits.length) console.log('  note: ' + name + ' followed a meta refresh inside the sandboxed frame (frame only; the tag is removed by DOMPurify in normal use)');

    // Storage and posts.
    // WordPress core itself keeps one emoji-support entry in sessionStorage on every page (real site only); nothing else may be there.
    var stored = await p.evaluate(function () { var keys = Object.keys(window.sessionStorage).filter(function (k) { return k !== 'wpEmojiSettingsSupports'; }); var all = JSON.stringify(window.sessionStorage) + JSON.stringify(window.localStorage); return { ls: window.localStorage.length, ss: keys.length, cookie: document.cookie, all: all }; });
    check(name + ' storage: nothing in localStorage, sessionStorage or cookies; no POST', stored.ls === 0 && stored.ss === 0 && stored.cookie.indexOf(SECRET) === -1 && stored.all.indexOf(SECRET) === -1 && s.posts.length === 0, { stored: stored, posts: s.posts });
    var events = await p.evaluate(function () { return window.__labsEvents || []; });
    var bad = events.filter(function (e) { return e[0] !== 'event' || EVENTS.indexOf(e[1]) === -1 || JSON.stringify(e[2]).indexOf(SECRET) !== -1 || Object.keys(e[2]).some(function (key) { return typeof e[2][key] !== 'string' || e[2][key].length > 60 || /[\s<>]/.test(e[2][key]); }); });
    check(name + ' analytics: only the six events with vocabulary values, never the code', events.length > 0 && bad.length === 0, bad);
    await s.context.close();
}

/**
 * The saved .html has no preview policy and no sandbox: only the sanitiser protects it. Save the learner's
 * hostile code with the real button, then open each file on its own (file://, new context, nothing of the site)
 * and count what it runs or loads.
 */
async function exportChecks(browser, name) {
    var fs = require('fs');
    var os = require('os');
    var dir = fs.mkdtempSync(path.join(process.env.LABS_EXPORT_DIR || os.tmpdir(), 'cs-export-'));
    var joins = ['@im@im@im@import;port;port;port "' + SINK + '/x-join1.css";', '@import "' + SINK + '/x-join2{.css";', 'body{background:uexpression(rl(' + SINK + '/x-join3.png)}',
        'body{background:u@import;rl(' + SINK + '/x-join4.png)}', 'body{background:im@import;age-set("' + SINK + '/x-join5.png" 1x)}', '@im<port "' + SINK + '/x-join6.css";'];
    var inputs = HTML_ATTACKS.map(function (h) { return [h, 'p{color:red}']; })
        .concat(CSS_ATTACKS.concat(joins).map(function (c) { return ['<p>x</p><ul><li>y</li></ul>', c]; }))
        .concat([['<p style="background:u@import;rl(' + SINK + '/x-style.png)">x</p>', '']]);
    var s = await open(browser, 1200, URL_);
    var files = [];
    for (var i = 0; i < inputs.length; i++) {
        await write(s.page, inputs[i][0], inputs[i][1]);
        var dl = await Promise.all([s.page.waitForEvent('download', { timeout: 5000 }).catch(function () { return null; }), s.page.click('[data-labs-download]')]);
        if (!dl[0]) continue;
        var file = path.join(dir, i + '.html');
        await dl[0].saveAs(file);
        files.push(file);
    }
    await s.context.close();
    check(name + ' export: every input was saved as a file (' + inputs.length + ')', files.length === inputs.length, files.length);
    sinkHits.length = 0;
    var ran = [], requests = [], text = [];
    var ctx = await browser.newContext();
    for (var f of files) {
        var page = await ctx.newPage();
        var dialogs = 0;
        page.on('dialog', function (d) { dialogs++; d.dismiss(); });
        page.on('request', function (r) { if (!/^file:|^data:|^about:/.test(r.url())) requests.push(path.basename(f) + ' ' + r.url()); });
        await page.goto('file://' + f);
        await wait(250);
        var flag = await page.evaluate(function () { return window.__pwned !== undefined; });
        if (flag || dialogs) ran.push(path.basename(f));
        var src = fs.readFileSync(f, 'utf8');
        if (/<script|\son[a-z]+\s*=|javascript:|@import|https?:|url\((?!data:image)|\\|srcset|href=|action=/i.test(src.replace(/<meta name="viewport"[^>]*>/, ''))) text.push(path.basename(f) + ': ' + src.slice(src.indexOf('<style>'), src.indexOf('<style>') + 200));
        await page.close();
    }
    await ctx.close();
    check(name + ' export: opened on its own, no saved file runs a script or opens a dialog', ran.length === 0, ran);
    check(name + ' export: opened on its own, no saved file makes a network request (sink hits 0)', requests.length === 0 && sinkHits.length === 0, { requests: requests, hits: sinkHits });
    check(name + ' export: no saved file contains a script, handler, @import, URL, escape or link target', text.length === 0, text);
    fs.rmSync(dir, { recursive: true, force: true });
}

async function limitChecks(browser, name) {
    var s = await open(browser, 1200, URL_);
    var p = s.page;
    await write(p, '<h1>ok</h1>', 'h1{color:green}');
    var good = await srcdoc(p);
    await write(p, '<p>' + new Array(51200).join('a') + '</p>', null);
    check(name + ' limit: HTML over 50KB is refused, the last good preview stays', (await p.isVisible('[data-labs-error]')) && /HTML が大きすぎます/.test(await p.textContent('[data-labs-error]')) && (await srcdoc(p)) === good);
    await write(p, '<h1>ok</h1>', 'a{' + new Array(51200).join('b') + '}');
    check(name + ' limit: CSS over 50KB is refused', (await p.isVisible('[data-labs-error]')) && /CSS が大きすぎます/.test(await p.textContent('[data-labs-error]')) && (await srcdoc(p)) === good);
    await write(p, new Array(1502).join('<i></i>'), 'h1{color:green}');
    check(name + ' limit: more than 1,500 elements is refused', (await p.isVisible('[data-labs-error]')) && /要素が多すぎます/.test(await p.textContent('[data-labs-error]')) && (await srcdoc(p)) === good);
    await write(p, new Array(1501).join('<i></i>'), null);
    check(name + ' limit: exactly 1,500 elements is accepted and the error clears', !(await p.isVisible('[data-labs-error]')) && (await srcdoc(p)) !== good);
    // Heavy but legal input: deep nesting and a large stylesheet. The page must stay responsive.
    var deep = new Array(1401).join('<div>') + 'x';
    var bigCss = new Array(900).join('.c' + ' > div:nth-child(2n+1):not(.x) { box-shadow: 0 0 9px #000; filter: blur(1px); animation: spin 0.01s infinite; }\n').slice(0, 50000);
    var t0 = Date.now();
    await p.fill('#labs-studio-html', deep);
    await p.fill('#labs-studio-css', bigCss + '\n@keyframes spin { to { transform: rotate(360deg) } }');
    await wait(700);
    var alive = await p.evaluate(function () { return document.querySelector('[data-labs-run]').disabled === false; });
    check(name + ' DoS: 1,400 nested elements + 50KB of heavy CSS render and the page answers within 5 s', alive && Date.now() - t0 < 5000, Date.now() - t0);
    // Rapid typing: many inputs, one render.
    await p.fill('#labs-studio-html', '');
    var c0 = await p.evaluate(function () { var f = document.querySelector('[data-studio-stage] iframe'); window.__loads = 0; f.addEventListener('load', function () { window.__loads++; }); return true; });
    await p.type('#labs-studio-html', '<p>abcdefghijklmnopqrstuvwxyz</p>', { delay: 10 });
    await wait(900);
    var loads = await p.evaluate(function () { return window.__loads; });
    check(name + ' DoS: 32 keystrokes are debounced into a few renders', c0 && loads >= 1 && loads <= 4, loads);
    // Review N2 (2026-11-21): thousands of nested ":not(" (inside 50KB) stopped the browser's CSS parser (tab crash in
    // Firefox, a stuck preview in Chromium). Now: refused before drawing, with a reason; the tab stays alive.
    await write(p, '<h1>ok</h1>', 'h1{color:green}');
    var good2 = await srcdoc(p);
    var crashed = false;
    p.on('crash', function () { crashed = true; });
    var nots = function (k) { return 'a' + new Array(k + 1).join(':not(') + '.b' + new Array(k + 1).join(')') + '{color:red}\nh1{color:green}'; };
    await write(p, '<h1>deep</h1>', nots(6000));
    var deepState = crashed ? null : { error: await p.isVisible('[data-labs-error]'), text: await p.textContent('[data-labs-error]'), same: (await srcdoc(p)) === good2, alive: await p.evaluate(function () { return 1 + 1; }) };
    check(name + ' N2: 6,000 nested ":not(" (inside 50KB) is refused with a reason; the tab stays alive and the last good preview stays', !crashed && deepState && deepState.error && /入れ子が深すぎます/.test(deepState.text) && deepState.same && deepState.alive === 2, deepState);
    var dlDeep = crashed ? [null] : await Promise.all([p.waitForEvent('download', { timeout: 1500 }).catch(function () { return null; }), p.click('[data-labs-download]')]);
    check(name + ' N2: nothing is saved while the CSS is refused', !crashed && dlDeep[0] === null);
    if (!crashed) {
        await write(p, '<p style="background:' + new Array(60).join('x(') + '1' + new Array(60).join(')') + ';color:red">deep-style</p>', 'h1{color:green}');
        var styleBody = body(await srcdoc(p));
        check(name + ' N2: a style attribute nested deeper than the limit is emptied (the element stays)', /deep-style/.test(styleBody) && !/x\(x\(x\(/.test(styleBody) && !(await p.isVisible('[data-labs-error]')), styleBody.slice(0, 200));
        await write(p, '<h1>after</h1>', 'h1{color:green}');
        check(name + ' N2: removing the nesting brings the preview back', /<h1>after<\/h1>/.test(await srcdoc(p)) && !(await p.isVisible('[data-labs-error]')));
        // Review F1 (2026-11-21): shapes that passed the N2 check. The stray quote crashed the tab in Chromium and Firefox;
        // the others reached the preview with no reason given (the CSS vanished). Each must be refused with the reason,
        // the last good preview staying and nothing saved.
        var N = function (k) { return 'a' + new Array(k + 1).join(':not(') + '.b' + new Array(k + 1).join(')') + '{color:red}'; };
        var f1 = [
            ['stray quote (tab crash)', 'a{content:"x\n}' + N(6000) + '\nh1{color:green}'],
            ['escaped brackets', 'a' + new Array(6001).join(':not\\(') + '{}\nh1{color:green}'],
            ['comment opener removed with @import', '@import /*;\n' + N(6000) + '*/\nh1{color:green}'],
            ['quote removed with url()', 'a{background:url(")}' + N(6000) + '"}\nh1{color:green}'],
            ['deep only after sanitising', '@import /*;\na' + new Array(41).join(':not(")"') + '{}\nh1{color:green}'],
            // Review G1 (2026-11-22): "#url(" / "@url(" read as url tokens hid the closes in a comment; Firefox's tab crashed.
            ['#url( in a custom property (G1, Firefox tab crash)', 'a{--x:' + new Array(501).join('#url(data:image/png;base64,' + new Array(31).join('(') + '/*' + new Array(32).join(')') + '*/') + '}\nh1{color:green}'],
            ['@url( in a var() fallback (G1, Firefox tab crash)', 'a{color:var(--y,' + new Array(501).join('@url(data:image/png;base64,' + new Array(30).join('(') + '/*' + new Array(31).join(')') + '*/') + ')}\nh1{color:green}']
        ];
        await write(p, '<h1>f1-good</h1>', 'h1{color:green}');
        var goodF1 = await srcdoc(p), f1Bad = [];
        for (var fi = 0; fi < f1.length && !crashed; fi++) {
            await write(p, '<h1>f1</h1>', f1[fi][1]);
            if (crashed) { f1Bad.push(f1[fi][0] + ': crash'); break; }
            var st = { error: await p.isVisible('[data-labs-error]'), text: await p.textContent('[data-labs-error]'), same: (await srcdoc(p)) === goodF1 };
            var dl = await Promise.all([p.waitForEvent('download', { timeout: 800 }).catch(function () { return null; }), p.click('[data-labs-download]')]);
            if (!(st.error && /入れ子が深すぎます/.test(st.text) && st.same && dl[0] === null)) f1Bad.push(f1[fi][0] + ': ' + JSON.stringify(st) + (dl[0] ? ' saved' : ''));
        }
        check(name + ' F1: ' + f1.length + ' shapes that hid deep nesting (the tab-crash input too) are refused with a reason; no crash, the last preview stays, nothing is saved', !crashed && f1Bad.length === 0, f1Bad);
        if (!crashed) {
            await write(p, '<p style="background:' + new Array(3001).join('x\\(') + ';color:red">f1-style</p><p style=\'content:"x\n}' + new Array(3001).join(':not(') + '\'>f1-quote</p>'
                + '<p style="--x:' + new Array(201).join('#url(data:image/png;base64,' + new Array(31).join('(') + '/*' + new Array(32).join(')') + '*/') + '">f1-hash</p>', 'h1{color:green}');
            var f1Doc = await srcdoc(p), f1Notes = await p.isVisible('[data-studio-notes]') ? await p.textContent('[data-studio-notes]') : '';
            check(name + ' F1: style attributes that hid deep nesting are emptied, with the reason in the notice', !crashed && /f1-style/.test(f1Doc) && /f1-quote/.test(f1Doc) && /f1-hash/.test(f1Doc) && !/#url\(/.test(body(f1Doc)) && Studio.nestingDepth(body(f1Doc)) <= 8 && /入れ子が深すぎる CSS（style 属性）/.test(f1Notes), { depth: Studio.nestingDepth(body(f1Doc)), notes: f1Notes });
            var dlF1 = await Promise.all([p.waitForEvent('download', { timeout: 3000 }), p.click('[data-labs-download]')]);
            var savedF1 = fs.readFileSync(await dlF1[0].path(), 'utf8');
            check(name + ' F1: the saved file holds no deep nesting either', Studio.nestingDepth(savedF1) <= 8 && /f1-style/.test(savedF1) && /f1-hash/.test(savedF1) && !/#url\(/.test(savedF1), Studio.nestingDepth(savedF1));
        }
        check(name + ' F1: the tab never crashed', !crashed);
    }
    check(name + ' limits run: no script error', s.errors.length === 0, s.errors);
    await s.context.close();
}

async function functionChecks(browser, name) {
    var s = await open(browser, 1280, URL_ + '?gtag=1');
    var p = s.page;
    await write(p, (await p.inputValue('#labs-studio-html')) + '<p><a href="#x">リンク</a></p>', null);
    var quiet = s.console.filter(function (t) { return !/report-only|Report Only|report-to|report-uri/i.test(t) || /violat|Refused/i.test(t); });
    check(name + ' console: loading and editing a lesson logs no error and no CSP violation (page or frame)', quiet.length === 0, s.console);
    await p.click('[data-labs-reset]');
    await wait(300);
    s.dialogs.length = 0;
    check(name + ' notice: nothing is reported as removed for the starter code', !(await p.isVisible('[data-studio-notes]')));
    check(name + ' lessons: three in the select, the first is loaded with its starter code', (await p.locator('#labs-studio-lesson option').count()) === 3 && (await p.inputValue('#labs-studio-lesson')) === 'first-page' && /<h1>はじめてのページ<\/h1>/.test(await p.inputValue('#labs-studio-html')) && (await p.locator('[data-studio-steps] li').count()) === 3 && (await p.locator('[data-studio-tasks] > li').count()) === 2);
    check(name + ' glossary: explains the tags and properties in the code', /<h1>\s*大見出し/.test(await p.textContent('[data-studio-glossary]')) && /font-family\s*書体/.test(await p.textContent('[data-studio-glossary]')));
    await p.click('[data-labs-run]');
    await wait(300);
    check(name + ' check: nothing solved at the start', (await p.locator('[data-task][data-state="done"]').count()) === 0 && /2件のうち0件/.test(await p.textContent('[data-studio-progress]')));
    await write(p, '<h1>やまだ</h1>\n<p>こんにちは</p>\n<h2>好きなもの</h2>\n<ul>\n<li>ねこ</li>\n<li>いぬ</li>\n</ul>', null);
    await p.click('[data-labs-run]');
    await wait(300);
    check(name + ' check: one of two solved (h2), list still short', (await p.getAttribute('[data-task="cs-html-001-a"]', 'data-state')) === 'done' && (await p.getAttribute('[data-task="cs-html-001-b"]', 'data-state')) === 'todo');
    check(name + ' glossary: follows the code (h2, ul, li appear)', /<h2>\s*中見出し/.test(await p.textContent('[data-studio-glossary]')) && /<li>\s*リストの項目/.test(await p.textContent('[data-studio-glossary]')));
    await write(p, '<h1>やまだ</h1>\n<h2>好きなもの</h2>\n<ul>\n<li>ねこ</li>\n<li>いぬ</li>\n<li>とり</li>\n</ul>', null);
    await p.focus('#labs-studio-html');
    await p.keyboard.press(process.platform === 'darwin' && name === 'webkit' ? 'Meta+Enter' : 'Control+Enter');
    await wait(300);
    check(name + ' check: all solved with Ctrl/⌘+Enter, announced in the status region', (await p.locator('[data-task][data-state="done"]').count()) === 2 && /すべて達成/.test(await p.textContent('[data-studio-progress]')) && /すべて達成/.test(await p.textContent('[data-labs-status]')));

    // Compare.
    await p.click('[data-studio-compare] > summary');
    await wait(400);
    var cmp = await p.evaluate(function () { return Array.prototype.map.call(document.querySelectorAll('[data-studio-compare] iframe'), function (f) { return [f.getAttribute('sandbox'), f.srcdoc.indexOf('はじめてのページ') !== -1, f.srcdoc.indexOf('やまだ') !== -1]; }); });
    check(name + ' compare: first and current previews side by side, both sandboxed', cmp.length === 2 && cmp[0][0] === '' && cmp[1][0] === '' && cmp[0][1] && !cmp[0][2] && cmp[1][2] && !cmp[1][1], cmp);
    check(name + ' compare: changed lines listed with +/− and screen-reader labels; CSS unchanged', (await p.locator('[data-studio-diff] .labs-diff__row.is-insert').count()) >= 5 && (await p.locator('[data-studio-diff] .labs-diff__row.is-delete').count()) >= 1 && /変更はありません/.test(await p.textContent('[data-studio-diff]')) && (await p.locator('[data-studio-diff] .labs-sr-only').count()) > 2);

    // Widths.
    var w = async function () { return p.evaluate(function () { return Math.round(document.querySelector('[data-studio-stage] iframe').getBoundingClientRect().width); }); };
    var pc = await w();
    await p.check('#labs-studio-width-tablet'); var tablet = await w();
    await p.check('#labs-studio-width-phone'); var phone = await w();
    check(name + ' width: PC fills the pane, tablet 768 px, phone 375 px', pc > 400 && tablet === 768 && phone === 375, [pc, tablet, phone]);
    check(name + ' width: a frame wider than the pane scrolls inside its stage, not the page', await p.evaluate(function () { return document.documentElement.scrollWidth <= window.innerWidth + 1; }));
    await p.check('#labs-studio-width-pc');

    // Copy / download.
    if (name === 'chromium') {
        await p.click('[data-labs-copy]');
        await wait(300);
        var secure = await p.evaluate(function () { return window.isSecureContext && !!navigator.clipboard; });
        // On a plain-http QA site the page uses its execCommand fallback and the clipboard cannot be read back: check that the copy was reported.
        if (!secure) { console.log('  note: not a secure context (http QA site): clipboard contents not read back'); check(name + ' copy: reported as done (fallback path)', await p.evaluate(function () { return (window.__labsEvents || []).some(function (e) { return e[1] === 'labs_tool_copy'; }); })); }
        var clip = secure ? await p.evaluate(function () { return navigator.clipboard.readText(); }) : '';
        if (secure) check(name + ' copy: a complete HTML file of the sanitised code', /^<!doctype html>/.test(clip) && clip.indexOf('<li>とり</li>') !== -1 && clip.indexOf('font-family: sans-serif') !== -1 && clip.indexOf('<script') === -1);
    }
    var dl = await Promise.all([p.waitForEvent('download', { timeout: 5000 }).catch(function () { return null; }), p.click('[data-labs-download]')]);
    check(name + ' download: code-studio-first-page.html, made in the browser (blob)', !!dl[0] && dl[0].suggestedFilename() === 'code-studio-first-page.html' && /^blob:/.test(dl[0].url()), dl[0] && [dl[0].suggestedFilename(), dl[0].url()]);

    // Reset (asks first), lesson switch (asks first).
    s.dialogs.length = 0;
    await p.click('[data-labs-reset]');
    await wait(300);
    check(name + ' reset: asks, then restores the starter code and clears the checklist', s.dialogs.length === 1 && /^confirm:/.test(s.dialogs[0]) && /はじめてのページ/.test(await p.inputValue('#labs-studio-html')) && (await p.locator('[data-task][data-state="done"]').count()) === 0);
    s.dialogs.length = 0;
    await p.selectOption('#labs-studio-lesson', 'style-a-card');
    await wait(300);
    await write(p, '<p onclick="x()">a</p><script>1</script>', 'p{background:url(//x.example/a.png)}');
    check(name + ' notice: removed tags, attributes and URLs are named to the learner', (await p.isVisible('[data-studio-notes]')) && /使えないタグ・属性・URL、外部の URL/.test(await p.textContent('[data-studio-notes]')));
    await p.click('[data-labs-reset]');
    await wait(300);
    s.dialogs.length = 0;
    check(name + ' lesson switch: no question when nothing was changed; second lesson loads', s.dialogs.length === 0 && /class="card"/.test(await p.inputValue('#labs-studio-html')) && (await p.locator('[data-studio-tasks] > li').count()) === 3);
    await write(p, null, '.card {\n  border: 1px solid #cccccc;\n  padding: 16px;\n  border-radius: 12px;\n  background-color: #f5f0ff;\n}\n.card h2 { margin: 0; color: #6735c7; }');
    await p.click('[data-labs-run]');
    await wait(300);
    check(name + ' lesson 2: all three CSS exercises solved', (await p.locator('[data-task][data-state="done"]').count()) === 3);
    check(name + ' function run: no script error, no request to another origin', s.errors.length === 0 && s.requests.every(function (u) { return u.indexOf(ORIGIN) === 0 || /^(data|blob):/.test(u); }), s.errors);
    var events = await p.evaluate(function () { return (window.__labsEvents || []).map(function (e) { return e[1] + ':' + (e[2].action || e[2].target || ''); }); });
    check(name + ' analytics: view, validate run / success, reset, download' + (name === 'chromium' ? ', copy' : ''), ['labs_tool_view:', 'labs_tool_run:validate', 'labs_tool_success:validate', 'labs_tool_run:reset', 'labs_tool_run:download'].every(function (e) { return events.indexOf(e) !== -1; }), events);
    await s.context.close();

    // ?lesson=
    var third = await open(browser, 1280, URL_ + '?lesson=responsive-columns');
    check(name + ' ?lesson=responsive-columns opens the third lesson', (await third.page.inputValue('#labs-studio-lesson')) === 'responsive-columns' && /class="columns"/.test(await third.page.inputValue('#labs-studio-html')));
    await write(third.page, null, (await third.page.inputValue('#labs-studio-css')) + '\n@media (max-width: 600px) {\n  .columns { flex-direction: column; }\n  .box { width: auto; }\n}');
    await third.page.click('[data-labs-run]');
    await wait(300);
    check(name + ' lesson 3: the media-query exercises are solved by the hint', (await third.page.locator('[data-task][data-state="done"]').count()) === 2);
    await third.context.close();
    var odd = await open(browser, 1280, URL_ + '?lesson=' + encodeURIComponent('"><img src=x onerror=window.__pwned=1>'));
    check(name + ' ?lesson=<markup> is ignored: first lesson, value not written to the page', (await odd.page.inputValue('#labs-studio-lesson')) === 'first-page' && (await odd.page.evaluate(function () { return window.__pwned === undefined && document.body.textContent.indexOf('onerror=window') === -1; })) && odd.errors.length === 0);
    await odd.context.close();
}

async function accessChecks(browser, name) {
    // Keyboard: Tab leaves the editors (no trap), every control is reachable, focus is visible.
    var s = await open(browser, 1280, URL_);
    var p = s.page;
    await p.focus('#labs-studio-html');
    await p.keyboard.press('Tab');
    var after = await p.evaluate(function () { return document.activeElement.id; });
    await p.keyboard.press('Shift+Tab');
    var back = await p.evaluate(function () { return document.activeElement.id; });
    check(name + ' keyboard: Tab moves from the HTML editor to the CSS editor and back (no focus trap)', after === 'labs-studio-css' && back === 'labs-studio-html', [after, back]);
    if (name !== 'webkit') { // WebKit's Tab order skips buttons unless the macOS "keyboard navigation" setting is on
        await p.focus('#labs-studio-lesson');
        var seen = [];
        for (var i = 0; i < 16; i++) {
            await p.keyboard.press('Tab');
            seen.push(await p.evaluate(function () { var a = document.activeElement; return a.id || (a.hasAttribute('data-labs-reset') ? 'reset' : a.hasAttribute('data-labs-copy') ? 'copy' : a.hasAttribute('data-labs-download') ? 'download' : a.hasAttribute('data-labs-run') ? 'run' : a.tagName.toLowerCase()); }));
        }
        check(name + ' keyboard: reset, copy, save, check, both editors, the width switch and the comparison are in the Tab order', ['reset', 'copy', 'download', 'run', 'labs-studio-html', 'labs-studio-css', 'summary'].every(function (id) { return seen.indexOf(id) !== -1; }) && seen.some(function (id) { return /^labs-studio-width-/.test(id); }), seen);
        await p.focus('[data-labs-run]');
        await p.keyboard.press('Tab');
        var ring = await p.evaluate(function () { var c = window.getComputedStyle(document.activeElement); return c.outlineStyle + ' ' + c.outlineWidth; });
        check(name + ' keyboard: focus ring is visible', /solid 2px|auto/.test(ring) || parseFloat(ring.split(' ')[1]) >= 2, ring);
    }
    await p.focus('#labs-studio-width-pc');
    await p.keyboard.press('ArrowRight');
    check(name + ' keyboard: arrow keys change the preview width', (await p.getAttribute('[data-studio-stage]', 'data-width')) === 'tablet');
    await s.context.close();

    for (var mode of [{ w: 375, dark: false }, { w: 375, dark: true }, { w: 1440, dark: true }, { w: 1440, dark: false }]) {
        var v = await open(browser, mode.w, URL_ + (mode.dark ? '?theme=dark' : ''), { dark: mode.dark });
        var label = name + ' ' + mode.w + 'px ' + (mode.dark ? 'dark' : 'light');
        await v.page.click('[data-labs-run]');
        await v.page.click('[data-studio-compare] > summary');
        await wait(400);
        check(label + ': no horizontal scroll of the page', await v.page.evaluate(function () { return document.documentElement.scrollWidth <= window.innerWidth + 1; }));
        var small = await v.page.evaluate(function () { return Array.prototype.filter.call(document.querySelectorAll('.labs-studio button, .labs-studio select, .labs-studio summary, .labs-studio label.labs-check'), function (el) { var r = el.getBoundingClientRect(); return r.width > 0 && r.height < 43.5; }).map(function (el) { return el.tagName + ':' + el.textContent.trim().slice(0, 12) + ':' + Math.round(el.getBoundingClientRect().height); }); });
        check(label + ': touch targets are at least 44 px high', small.length === 0, small);
        if (name === 'chromium') {
            await v.page.addScriptTag({ path: axePath });
            var viol = await v.page.evaluate(async function () {
                var r = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
                return r.violations.map(function (x) { return x.id + ': ' + x.nodes.length + ' (' + x.nodes.slice(0, 3).map(function (n) { return n.target.join(' '); }).join(' | ') + ')'; });
            });
            check(label + ': axe WCAG 2.2 AA clean (page, with results and comparison open)', viol.length === 0, viol);
        }
        if (mode.dark) {
            var colors = await v.page.evaluate(function () { var c = window.getComputedStyle(document.querySelector('.labs-studio__lesson')); var f = window.getComputedStyle(document.querySelector('.labs-studio__frame')); return [c.backgroundColor, c.color, f.backgroundColor]; });
            check(label + ': the tool follows the dark theme; the preview stays a light page', colors[0] !== 'rgb(255, 255, 255)' && colors[2] === 'rgb(255, 255, 255)', colors);
        }
        check(label + ': no script error', v.errors.length === 0, v.errors);
        if (process.env.LABS_SHOTS) await v.page.screenshot({ path: path.join(process.env.LABS_SHOTS, 'code-studio-' + name + '-' + mode.w + (mode.dark ? '-dark' : '') + '.png'), fullPage: true });
        await v.context.close();
    }

    var calm = await open(browser, 1280, URL_, { calm: true });
    await write(calm.page, '<div class="spin">x</div>', '.spin { animation: spin 1s infinite; transition: all 2s; } @keyframes spin { to { transform: rotate(360deg); } }');
    var doc = await srcdoc(calm.page);
    check(name + ' reduced motion: the preview document ends with a rule that stops animation and transition', doc.lastIndexOf('prefers-reduced-motion: reduce') > doc.indexOf('.spin') && /animation:none!important;transition:none!important/.test(doc));
    check(name + ' reduced motion: the tool itself runs no animation', await calm.page.evaluate(function () { return Array.prototype.every.call(document.querySelectorAll('.labs-studio *'), function (el) { var c = window.getComputedStyle(el); return c.animationName === 'none' && parseFloat(c.transitionDuration) < 0.01; }); }));
    await calm.context.close();
}

async function releaseChecks(browser, name) {
    var o = { origin: RELEASE_ORIGIN };
    var nf = await open(browser, 1280, URL_ + '?anon=1', o);
    check(name + ' release: /labs/code-studio/ is a 404 while it is a draft', nf.status === 404 && (await nf.page.locator('[data-studio]').count()) === 0);
    await nf.context.close();
    var admin = await open(browser, 1280, URL_, o);
    check(name + ' release: also a 404 for a logged-in administrator', admin.status === 404);
    await admin.context.close();
    var list = await open(browser, 1280, '/labs/?anon=1', o);
    check(name + ' release: the list does not mention Code Studio', !/Code Studio|code-studio/.test(await list.page.content()));
    await list.context.close();
    var req = (await browser.newContext()).request;
    var sm = await (await req.get(RELEASE_ORIGIN + '/labs-sitemap.xml')).text();
    check(name + ' release: the sitemap does not list it', sm.indexOf('code-studio') === -1 && (sm.match(/<loc>/g) || []).length === 3);
    for (var t of ['json-formatter', 'text-diff', 'robots-txt-generator']) {
        var page = await open(browser, 1280, '/labs/' + t + '/?anon=1', o);
        check(name + ' release: /labs/' + t + '/ still 200, does not load or mention Code Studio', page.status === 200 && page.errors.length === 0 && !/Code Studio|code-studio|codestudio|purify/.test(await page.page.content()) && !page.requests.some(function (u) { return /codestudio|code-studio|purify/.test(u); }));
        await page.context.close();
    }
}

(async function main() {
    var sink = await startSink();
    var server = QA ? null : await startServer(PORT, true);
    var releaseServer = QA ? null : await startServer(RELEASE_PORT, false);
    try {
        for (var name of (process.env.LABS_BROWSERS || 'chromium,webkit,firefox').split(',')) {
            var exe = process.env['LABS_' + name.toUpperCase() + '_PATH'];
            var launch = exe ? { executablePath: exe } : {};
            // A QA site on plain http is not a secure context, so Chromium would have no clipboard API (production is https).
            if (QA && name === 'chromium' && /^http:/.test(QA)) launch.args = ['--unsafely-treat-insecure-origin-as-secure=' + QA];
            // Firefox does not resolve a .local name from /etc/hosts by itself: name the QA host as a loopback domain.
            if (QA && name === 'firefox') launch.firefoxUserPrefs = { 'network.dns.localDomains': QA.replace(/^https?:\/\//, '').replace(/[:\/].*$/, '') };
            var browser = await playwright[name].launch(launch);
            await securityChecks(browser, name);
            await exportChecks(browser, name);
            await limitChecks(browser, name);
            await functionChecks(browser, name);
            await accessChecks(browser, name);
            if (!QA) await releaseChecks(browser, name);
            await browser.close();
        }
    } catch (e) {
        fail++;
        console.log('FAIL unexpected: ' + (e && e.stack || e));
    } finally {
        if (server) server.kill();
        if (releaseServer) releaseServer.kill();
        sink.close();
    }
    console.log('\n' + pass + ' passed, ' + fail + ' failed');
    process.exit(fail ? 1 : 0);
}());
