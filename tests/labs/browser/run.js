#!/usr/bin/env node
/**
 * OmochiX Labs browser checks (local only; not part of CI).
 *
 * Starts tests/labs/preview-server.php twice and, in Chromium and WebKit:
 *  - on a published copy (every tool published, so a draft tool's code is
 *    still tested; the shipped catalog is unchanged):
 *  - 375 / 768 / 1440 px: no horizontal scroll, axe (WCAG 2.2 A/AA) clean
 *  - every tool: sample → run → copy → reset, error states, keyboard-only use
 *  - Web Worker for large input and the time limit
 *  - no request leaves 127.0.0.1, no CSP (Report-Only) violation
 *  - console: CSP violations and unknown CSP notices fail; only the listed
 *    configuration notices pass (console-policy.js). A deliberate violation
 *    proves the check catches one.
 *  - keyboard focus on a Labs select shows the 2px ring (light and dark)
 *  - on the shipped theme (the release state): a draft tool is a 404 and absent
 *    from the list, search, categories, related links, sitemap and pages, at
 *    375 / 768 / 1440 px, with axe and the console policy
 *  - analytics events: only the six names, only vocabulary values
 *  - unknown slug is a 404
 *
 * Needs Playwright 1.64.1 and axe-core (see package.json). To use copies
 * that are already installed elsewhere instead of `npm ci` here:
 *   LABS_PLAYWRIGHT=/path/to/node_modules/playwright LABS_AXE=/path/to/axe-core/axe.min.js node tests/labs/browser/run.js
 * and LABS_CHROMIUM_PATH / LABS_WEBKIT_PATH for browser builds already on disk.
 */
'use strict';

var path = require('path');
var spawn = require('child_process').spawn;
var fs = require('fs');
var consolePolicy = require('./console-policy');

var root = path.resolve(__dirname, '..', '..', '..');
var playwright = require(process.env.LABS_PLAYWRIGHT || 'playwright');
var axePath = process.env.LABS_AXE || require.resolve('axe-core/axe.min.js');
var shots = process.env.LABS_SHOTS || '';
var PORT = 8791;
var ORIGIN = 'http://127.0.0.1:' + PORT; // published copy
var RELEASE_PORT = 8794;
var RELEASE_ORIGIN = 'http://127.0.0.1:' + RELEASE_PORT; // shipped catalog
var COST = 'token-cost-calculator';
var TOOLS = ['json-formatter', 'text-diff', 'robots-txt-generator', 'token-cost-calculator'];
var EVENTS = ['labs_tool_view', 'labs_tool_run', 'labs_tool_success', 'labs_tool_error', 'labs_tool_copy', 'labs_related_click'];
var SECRET = 'SECRET-INPUT-7f3a';

var pass = 0;
var fail = 0;
function check(name, cond, detail) {
    if (cond) { pass++; console.log('PASS ' + name); }
    else { fail++; console.log('FAIL ' + name + (detail ? '\n  ' + JSON.stringify(detail).slice(0, 1500) : '')); }
}

function startServer(port, publishedCopy) {
    var env = Object.assign({}, process.env, { LABS_PREVIEW_PUBLISHED_COPY: publishedCopy ? '1' : '' });
    var proc = spawn('php', ['-S', '127.0.0.1:' + port, '-t', root, path.join(root, 'tests/labs/preview-server.php')], { stdio: 'ignore', env: env });
    return new Promise(function (resolve) { setTimeout(function () { resolve(proc); }, 700); });
}

async function openPage(browser, width, url, opts) {
    var origin = (opts && opts.origin) || ORIGIN;
    var context = await browser.newContext({ viewport: { width: width, height: 900 }, colorScheme: (opts && opts.dark) ? 'dark' : 'light' });
    if (browser.browserType().name() === 'chromium') await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: origin });
    var page = await context.newPage();
    var external = [];
    var status = 0;
    page.on('request', function (req) { if (req.url().indexOf(origin) !== 0 && !/^(data|blob):/.test(req.url())) external.push(req.url()); });
    page.on('response', function (res) { if (res.url().split('?')[0] === (origin + url).split('?')[0]) status = res.status(); });
    var errors = [];
    page.on('pageerror', function (e) { errors.push(String(e)); });
    var messages = [];
    var policy = { value: '' };
    page.on('console', function (m) { messages.push({ type: m.type(), text: m.text(), url: (m.location() || {}).url || '' }); });
    var failed = [];
    page.on('response', function (res) { if (res.status() >= 400) failed.push(res.status() + ' ' + res.url()); });
    page.on('response', function (res) { if (res.request().resourceType() === 'document' && res.frame() === page.mainFrame()) policy.value = res.headers()['content-security-policy-report-only'] || ''; });
    await page.addInitScript(function () {
        window.__csp = [];
        document.addEventListener('securitypolicyviolation', function (e) { window.__csp.push(e.violatedDirective + ' ' + e.blockedURI); });
    });
    await page.goto(origin + url);
    return { page: page, context: context, external: external, errors: errors, messages: messages, failed: failed, policy: policy, status: function () { return status; } };
}

// Console messages and violation events against console-policy.js. Returns the review.
async function consoleCheck(s, name, label) {
    var events = await s.page.evaluate(function () { return window.__csp || []; });
    var r = consolePolicy.review(name, s.messages, s.policy.value);
    check(name + ' ' + label + ': console has no CSP violation, unknown CSP notice or error' + (Object.keys(r.known).length ? ' (known: ' + JSON.stringify(r.known) + ')' : ''), r.ok && events.length === 0, { bad: r.bad, events: events, failed: s.failed });
    return r;
}

async function axe(page) {
    await page.addScriptTag({ path: axePath });
    return page.evaluate(async function () {
        var r = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
        return r.violations.map(function (v) { return v.id + ': ' + v.nodes.length + ' (' + v.nodes.slice(0, 2).map(function (n) { return n.target.join(' '); }).join(' | ') + ')'; });
    });
}

async function noHorizontalScroll(page) {
    return page.evaluate(function () { return document.documentElement.scrollWidth <= window.innerWidth + 1; });
}

function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

async function checkEvents(page, label) {
    var events = await page.evaluate(function () { return window.__labsEvents || []; });
    var bad = events.filter(function (e) {
        return e[0] !== 'event' || EVENTS.indexOf(e[1]) === -1 || JSON.stringify(e[2]).indexOf(SECRET) !== -1 || Object.keys(e[2]).some(function (k) { return typeof e[2][k] !== 'string' || e[2][k].length > 60 || /\s/.test(e[2][k]); });
    });
    check(label + ': analytics events are the six names with vocabulary values only', events.length > 0 && bad.length === 0, bad);
    return events;
}

async function layoutChecks(browser, name) {
    var urls = ['/labs/'].concat(TOOLS.map(function (t) { return '/labs/' + t + '/'; }));
    for (var w of [375, 768, 1440]) {
        for (var url of urls) {
            var s = await openPage(browser, w, url + '?gtag=1');
            await wait(300);
            check(name + ' ' + w + 'px ' + url + ': no horizontal scroll', await noHorizontalScroll(s.page));
            if (name === 'chromium') {
                var v = await axe(s.page);
                check(name + ' ' + w + 'px ' + url + ': axe WCAG 2.2 AA clean', v.length === 0, v);
            }
            check(name + ' ' + w + 'px ' + url + ': no script errors', s.errors.length === 0, s.errors);
            await consoleCheck(s, name, w + 'px ' + url);
            if (shots && w !== 768) await s.page.screenshot({ path: path.join(shots, name + '-' + w + url.replace(/\//g, '_') + '.png'), fullPage: true });
            await s.context.close();
        }
    }
    var dark = await openPage(browser, 1440, '/labs/text-diff/?theme=dark', { dark: true });
    await dark.page.click('[data-labs-sample]');
    await wait(500);
    if (name === 'chromium') check(name + ' dark theme: axe clean with a diff on screen', (await axe(dark.page)).length === 0, await axe(dark.page));
    if (shots) await dark.page.screenshot({ path: path.join(shots, name + '-dark-text-diff.png'), fullPage: true });
    await consoleCheck(dark, name, 'dark theme');
    await dark.context.close();
}

// The console check must catch a real violation: create a data: Worker (not allowed by
// worker-src, no network request) and expect a violation, never a pass.
async function consoleSelfTest(browser, name) {
    var s = await openPage(browser, 1440, '/labs/json-formatter/');
    await s.page.evaluate(function () { try { new Worker('data:application/javascript,0'); } catch (e) { /* enforced elsewhere */ } });
    await wait(600);
    var r = consolePolicy.review(name, s.messages, s.policy.value);
    var events = await s.page.evaluate(function () { return window.__csp || []; });
    check(name + ' console self-test: a deliberate worker-src violation is reported as a failure', !r.ok && r.bad.some(function (b) { return /^violation: /.test(b); }) && events.some(function (e) { return /^worker-src/.test(e); }), { bad: r.bad, events: events });
    await s.context.close();
}

// F-4: keyboard focus on a Labs select draws the 2px accent ring (WebKit draws no box-shadow on a native select).
async function selectFocusChecks(browser, name) {
    for (var dark of [false, true]) {
        var s = await openPage(browser, 768, '/labs/json-formatter/' + (dark ? '?theme=dark' : ''), { dark: dark });
        var p = s.page;
        await p.focus('#labs-json-sort');
        await p.keyboard.press(name === 'webkit' ? 'Shift+Alt+Tab' : 'Shift+Tab');
        var r = await p.evaluate(function () {
            function rgb(c) {
                var m = /rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/.exec(c) || /color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?: \/ ([\d.]+))?\)/.exec(c);
                if (!m) return null;
                var k = /^color/.test(c) ? 255 : 1;
                return { r: m[1] * k, g: m[2] * k, b: m[3] * k, a: m[4] === undefined ? 1 : Number(m[4]) };
            }
            function lum(c) { return [c.r, c.g, c.b].map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }).reduce(function (s, v, i) { return s + v * [0.2126, 0.7152, 0.0722][i]; }, 0); }
            var e = document.activeElement;
            var st = getComputedStyle(e);
            var bg = null;
            for (var n = e.parentElement; n && !bg; n = n.parentElement) { var c = rgb(getComputedStyle(n).backgroundColor); if (c && c.a > 0.9) bg = c; }
            bg = bg || { r: 255, g: 255, b: 255, a: 1 };
            var ring = rgb(st.outlineColor);
            var l1 = lum(ring), l2 = lum(bg);
            return { id: e.id, fv: e.matches(':focus-visible'), style: st.outlineStyle, width: parseFloat(st.outlineWidth), contrast: (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05), theme: document.documentElement.getAttribute('data-theme') || '' };
        });
        check(name + ' F-4 ' + (dark ? 'dark' : 'light') + ': keyboard focus on a Labs select shows a 2px ring with 3:1 contrast', r.id === 'labs-json-indent' && r.fv && r.style === 'solid' && r.width >= 2 && r.contrast >= 3 && (!dark || r.theme === 'dark'), r);
        if (shots) await (await p.$('#labs-json-indent')).screenshot({ path: path.join(shots, name + '-select-focus-' + (dark ? 'dark' : 'light') + '.png') });
        // Other form fields keep their own focus style (border + glow, no outline).
        await p.focus('#labs-json-input');
        var ta = await p.evaluate(function () { return getComputedStyle(document.activeElement).outlineStyle; });
        check(name + ' F-4 ' + (dark ? 'dark' : 'light') + ': textarea focus style unchanged (no outline)', ta === 'none', ta);
        await s.context.close();
    }
}

async function toolChecks(browser, name) {
    var chromium = name === 'chromium';

    // --- JSON ---------------------------------------------------------
    var s = await openPage(browser, 1440, '/labs/json-formatter/?gtag=1');
    var p = s.page;
    await p.fill('#labs-json-input', '{"' + SECRET + '":1,"b":[1,2]}');
    await p.click('[data-action="format"]');
    await p.waitForFunction(function () { return document.querySelector('#labs-json-output').value.length > 0; });
    check(name + ' json: format', (await p.inputValue('#labs-json-output')).indexOf('\n  "b": [') !== -1);
    await p.click('[data-action="minify"]');
    await wait(200);
    check(name + ' json: minify', (await p.inputValue('#labs-json-output')) === '{"' + SECRET + '":1,"b":[1,2]}');
    if (chromium) {
        await p.click('[data-labs-copy]');
        await wait(200);
        check(name + ' json: copy puts the result on the clipboard', (await p.evaluate(function () { return navigator.clipboard.readText(); })) === '{"' + SECRET + '":1,"b":[1,2]}');
    }
    await p.fill('#labs-json-input', '[1,2,]');
    await p.click('[data-action="format"]');
    await wait(200);
    var errText = await p.textContent('[data-labs-error]');
    check(name + ' json: error with line, column and caret; output cleared', /1行目 5文字目/.test(errText) && errText.indexOf('^') !== -1 && (await p.inputValue('#labs-json-output')) === '' && await p.isDisabled('[data-labs-copy]'));
    await p.fill('#labs-json-input', '<img src=x onerror="window.__xss=1">');
    await p.click('[data-action="format"]');
    await wait(200);
    check(name + ' json: input is never rendered as HTML', !(await p.evaluate(function () { return window.__xss; })) && (await p.locator('[data-labs-error] img').count()) === 0);
    var bigJson = '[' + new Array(60000).fill('{"k":"' + 'x'.repeat(20) + '"}').join(',') + ']';
    await p.evaluate(function (v) { document.querySelector('#labs-json-input').value = v; }, bigJson);
    await p.click('[data-action="format"]');
    await p.waitForFunction(function () { return document.querySelector('#labs-json-output').value.length > 1000000; }, null, { timeout: 15000 });
    check(name + ' json: 1.7MB input is formatted (Web Worker)', true);
    await p.click('[data-labs-reset]');
    check(name + ' json: reset clears input and output', (await p.inputValue('#labs-json-input')) === '' && (await p.inputValue('#labs-json-output')) === '');
    // keyboard only: focus the input, type, Ctrl+Enter
    await p.focus('#labs-json-input');
    await p.keyboard.type('{"k":true}');
    await p.keyboard.press(process.platform === 'darwin' && !chromium ? 'Meta+Enter' : 'Control+Enter');
    await wait(300);
    check(name + ' json: Ctrl/⌘+Enter runs from the keyboard', (await p.inputValue('#labs-json-output')) === '{\n  "k": true\n}');
    await p.evaluate(function () { window.omochixLabs.limits['json-formatter'].input = 10; });
    await p.fill('#labs-json-input', '{"too":"large input"}');
    await p.click('[data-action="format"]');
    await wait(200);
    check(name + ' json: size limit is enforced', /入力が大きすぎます/.test(await p.textContent('[data-labs-error]')));
    await checkEvents(p, name + ' json');
    await consoleCheck(s, name, 'json');
    check(name + ' json: no external requests, no CSP violations', s.external.length === 0 && (await p.evaluate(function () { return window.__csp; })).length === 0, { external: s.external, csp: await p.evaluate(function () { return window.__csp; }) });
    await s.context.close();

    // --- diff ---------------------------------------------------------
    s = await openPage(browser, 1440, '/labs/text-diff/?gtag=1');
    p = s.page;
    await p.click('[data-labs-sample]');
    await p.waitForSelector('.labs-diff__table');
    var summary = await p.textContent('[data-labs-summary]');
    check(name + ' diff: sample shows added / removed counts', /追加 \d+行・削除 \d+行/.test(summary));
    check(name + ' diff: inline <ins>/<del> and +/- markers with screen-reader text', (await p.locator('.labs-diff ins').count()) > 0 && (await p.locator('.labs-diff del').count()) > 0 && (await p.locator('.labs-diff__mark .labs-sr-only').first().textContent()) !== '');
    await p.check('#labs-diff-view-split');
    check(name + ' diff: split view', (await p.locator('.labs-diff__table--split').count()) === 1);
    if (chromium) {
        await p.click('[data-labs-copy]');
        await wait(200);
        check(name + ' diff: unified diff copied', /^--- 変更前\n\+\+\+ 変更後\n@@ /.test(await p.evaluate(function () { return navigator.clipboard.readText(); })));
    }
    await p.fill('#labs-diff-old', 'a\r\nb\r\n');
    await p.fill('#labs-diff-new', 'a\nb\n');
    await p.click('[data-labs-run]');
    await wait(300);
    // Browsers normalise a textarea's line breaks to LF, so CRLF vs LF never shows up as a difference.
    check(name + ' diff: line-ending only change is no difference', /違いはありません/.test(await p.textContent('[data-labs-summary]')));
    var lines = []; for (var i = 0; i < 20000; i++) lines.push('line ' + i + ' ' + SECRET);
    var changed = lines.slice(); for (var j = 0; j < 20000; j += 997) changed[j] = 'changed ' + j;
    await p.evaluate(function (v) { document.querySelector('#labs-diff-old').value = v[0]; document.querySelector('#labs-diff-new').value = v[1]; }, [lines.join('\n'), changed.join('\n')]);
    var t0 = Date.now();
    await p.click('[data-labs-run]');
    await p.waitForFunction(function () { return /追加 21行・削除 21行/.test(document.querySelector('[data-labs-summary]').textContent); }, null, { timeout: 15000 });
    check(name + ' diff: 20,000-line inputs in the Worker (' + (Date.now() - t0) + ' ms)', Date.now() - t0 < 10000);
    await p.evaluate(function () { window.omochixLabs.limits.timeout_ms = 1; });
    changed[1] = 'changed again';
    await p.evaluate(function (v) { document.querySelector('#labs-diff-new').value = v; }, changed.join('\n'));
    await p.click('[data-labs-run]');
    await p.waitForFunction(function () { return !document.querySelector('[data-labs-error]').hidden; }, null, { timeout: 5000 });
    check(name + ' diff: the time limit stops the Worker with a message', /10秒を超えたため中断/.test(await p.textContent('[data-labs-error]')) && !(await p.isDisabled('[data-labs-run]')));
    await p.evaluate(function () { window.omochixLabs.limits.timeout_ms = 10000; });
    await p.fill('#labs-diff-old', 'x');
    await p.fill('#labs-diff-new', 'y');
    await p.click('[data-labs-run]');
    await wait(500);
    check(name + ' diff: a new Worker starts after a timeout', /追加 1行・削除 1行/.test(await p.textContent('[data-labs-summary]')));
    await p.click('[data-labs-reset]');
    check(name + ' diff: reset', (await p.inputValue('#labs-diff-old')) === '' && await p.isHidden('[data-labs-result]'));
    await checkEvents(p, name + ' diff');
    await consoleCheck(s, name, 'diff');
    check(name + ' diff: no external requests, no CSP violations', s.external.length === 0 && (await p.evaluate(function () { return window.__csp; })).length === 0, { external: s.external, csp: await p.evaluate(function () { return window.__csp; }) });
    await s.context.close();

    // --- robots -------------------------------------------------------
    s = await openPage(browser, 1440, '/labs/robots-txt-generator/?gtag=1');
    p = s.page;
    check(name + ' robots: default output allows all', (await p.inputValue('#labs-robots-output')) === 'User-agent: *\nAllow: /\n');
    await p.click('[data-labs-sample]');
    await wait(300);
    var robots = await p.inputValue('#labs-robots-output');
    check(name + ' robots: sample with WordPress rules, AI training crawlers and sitemap', robots.indexOf('Disallow: /wp-admin/') !== -1 && robots.indexOf('User-agent: GPTBot') !== -1 && robots.indexOf('User-agent: OAI-SearchBot') === -1 && robots.indexOf('Sitemap: https://example.com/sitemap.xml') !== -1);
    check(name + ' robots: tester says GPTBot is blocked', /✕ 拒否/.test(await p.textContent('[data-labs-verdict]')));
    await p.fill('#labs-tester-agent', 'Googlebot');
    await p.fill('#labs-tester-path', '/wp-admin/admin-ajax.php');
    await p.click('[data-labs-run]');
    check(name + ' robots: tester longest-match allow', /✓ 許可/.test(await p.textContent('[data-labs-verdict]')) && /Allow: \/wp-admin\/admin-ajax\.php/.test(await p.textContent('[data-labs-verdict]')));
    // keyboard: add a rule with the keyboard and type a path
    await p.focus('[data-labs-add-rule]');
    await p.keyboard.press('Enter');
    await p.keyboard.type('/' + SECRET + '/');
    await wait(300);
    check(name + ' robots: rule added and typed with the keyboard', (await p.inputValue('#labs-robots-output')).indexOf('Disallow: /' + SECRET + '/') !== -1);
    await p.check('#labs-robots-preset-block');
    await wait(300);
    check(name + ' robots: block-all warning', /サイト全体のクロールを拒否/.test(await p.textContent('[data-labs-warnings]')));
    await p.fill('#labs-robots-sitemaps', 'javascript:alert(1)');
    await wait(300);
    check(name + ' robots: invalid sitemap is not written', (await p.inputValue('#labs-robots-output')).indexOf('javascript:') === -1);
    await p.click('[data-labs-reset]');
    await wait(200);
    check(name + ' robots: reset', (await p.inputValue('#labs-robots-output')) === 'User-agent: *\nAllow: /\n');
    await checkEvents(p, name + ' robots');
    await consoleCheck(s, name, 'robots');
    check(name + ' robots: no external requests, no CSP violations', s.external.length === 0 && (await p.evaluate(function () { return window.__csp; })).length === 0, { external: s.external, csp: await p.evaluate(function () { return window.__csp; }) });
    await s.context.close();

    // --- cost ---------------------------------------------------------
    s = await openPage(browser, 1440, '/labs/token-cost-calculator/?gtag=1');
    p = s.page;
    check(name + ' cost: defaults computed for the three preselected models', (await p.locator('[data-labs-cost-rows] tr').count()) === 3);
    // The preview server runs as a non-production environment: unapproved rows are listed with their state.
    check(name + ' cost: outside production, held and pending rows are marked', (await p.locator('[data-labs-models] .labs-tag--state', { hasText: '保留' }).count()) === 5 && (await p.locator('[data-labs-models] .labs-tag--state', { hasText: '承認待ち' }).count()) === 6 && /本番のページには表示されません/.test(await p.textContent('[data-labs-cost-notes]')));
    check(name + ' cost: each result row shows its caveats', (await p.locator('[data-labs-cost-rows] .labs-table__caveat').count()) >= 3);
    check(name + ' cost: review mode shows the administrator-only banner', await p.isVisible('.labs-notice--review'));
    var anon = await openPage(browser, 1440, '/labs/token-cost-calculator/?anon=1&gtag=1');
    var anonHtml = await anon.page.content();
    check(name + ' cost: an anonymous visitor gets no unapproved price, id or review banner', (await anon.page.locator('[data-labs-model]').count()) === 0 && await anon.page.isVisible('[data-labs-no-models]') && anonHtml.indexOf('gpt-6-astra') === -1 && anonHtml.indexOf('claude-opus-5-5') === -1 && (await anon.page.locator('.labs-notice--review').count()) === 0);
    await anon.context.close();
    await p.click('[data-labs-sample]');
    await wait(500);
    check(name + ' cost: estimate shown as an estimate', /^約\d/.test(await p.textContent('[data-labs-tokens]')) && /〜/.test(await p.textContent('[data-labs-range]')));
    var firstRow = await p.locator('[data-labs-cost-rows] tr').first().textContent();
    check(name + ' cost: USD and yen with source date', /\$/.test(firstRow) && /円/.test(firstRow) && /2026-11-04/.test(firstRow));
    await p.fill('#labs-cost-days', '-3');
    await wait(400);
    check(name + ' cost: invalid number shows an error and marks the field', /0以上の整数/.test(await p.textContent('[data-labs-error]')) && (await p.getAttribute('#labs-cost-days', 'aria-invalid')) === 'true');
    await p.fill('#labs-cost-days', '30');
    await p.fill('#labs-cost-text', SECRET + ' ' + 'テスト'.repeat(100));
    await wait(600);
    await p.click('[data-labs-use="input"]');
    await wait(300);
    check(name + ' cost: estimate copied into input tokens', Number(await p.inputValue('#labs-cost-input')) > 300);
    var sourceLinks = await p.locator('[data-labs-cost-rows] a').evaluateAll(function (as) { return as.map(function (a) { return a.href + ' ' + a.rel; }); });
    check(name + ' cost: source links are official and noopener noreferrer', sourceLinks.length > 0 && sourceLinks.every(function (l) { return /^https:\/\/(platform\.claude\.com|developers\.openai\.com|aistudio\.google\.com)\//.test(l) && /noopener/.test(l) && /noreferrer/.test(l); }), sourceLinks);
    // stale data: a page cached for months must not show prices
    await p.evaluate(function () {
        window.omochixLabs.pricing.models.forEach(function (m) { m.verified_at = '2025-01-01'; });
    });
    await p.reload();
    await wait(300);
    check(name + ' cost: reload with fresh data still shows prices', (await p.locator('[data-labs-model]').count()) === 11);
    await p.route('**/labs/token-cost-calculator/**', async function (route) {
        var res = await route.fetch();
        var body = (await res.text()).replace(/\\"verified_at\\":\\"2026-11-04\\"/g, '\\"verified_at\\":\\"2025-01-01\\"').replace(/"verified_at":"2026-11-04"/g, '"verified_at":"2025-01-01"');
        await route.fulfill({ response: res, body: body });
    });
    await p.reload();
    await wait(300);
    check(name + ' cost: prices older than 90 days on the browser date are not shown', (await p.locator('[data-labs-model]').count()) === 0 && await p.isVisible('[data-labs-no-models]'));
    await p.unroute('**/labs/token-cost-calculator/**');
    await checkEvents(p, name + ' cost');
    await s.context.close();

    // --- list -----------------------------------------------------------
    s = await openPage(browser, 375, '/labs/?gtag=1');
    p = s.page;
    await p.fill('#labs-search-q', 'ＪＳＯＮ ふぉーまっと');
    await wait(400);
    check(name + ' list: search filters cards', (await p.locator('[data-labs-card]:visible').count()) === 1 && /1件のツール/.test(await p.textContent('[data-labs-count]')));
    await p.fill('#labs-search-q', SECRET);
    await p.press('#labs-search-q', 'Enter');
    await wait(400);
    check(name + ' list: typed search words never reach the URL', p.url().indexOf(SECRET) === -1 && p.url().indexOf('q=') === -1, p.url());
    await p.fill('#labs-search-q', '');
    await p.click('[data-labs-cat="dev"]');
    await wait(300);
    check(name + ' list: category filter and aria-current', (await p.locator('[data-labs-card]:visible').count()) === 2 && (await p.getAttribute('[data-labs-cat="dev"]', 'aria-current')) === 'true' && /cat=dev/.test(p.url()));
    await p.fill('#labs-search-q', 'zzzz');
    await wait(400);
    check(name + ' list: empty state', await p.isVisible('[data-labs-empty]'));
    await p.fill('#labs-search-q', '');
    await p.click('[data-labs-cat=""]');
    await wait(300);
    await p.click('a[href$="/labs/robots-txt-generator/"]');
    await p.waitForURL(/robots-txt-generator/);
    check(name + ' list: card link opens the tool', /robots\.txt生成/.test(await p.textContent('h1')));
    await s.context.close();

    // --- sitemap and robots.txt (over HTTP, as a crawler sees them) -----
    var req = (await browser.newContext()).request;
    var sm = await req.get(ORIGIN + '/labs-sitemap.xml');
    var smText = await sm.text();
    check(name + ' sitemap: 200 application/xml, noindex, Last-Modified', sm.status() === 200 && /^application\/xml; charset=UTF-8$/i.test(sm.headers()['content-type']) && /noindex/.test(sm.headers()['x-robots-tag']) && !!sm.headers()['last-modified']);
    check(name + ' sitemap: four tool URLs and not the list page', (smText.match(/<loc>/g) || []).length === 4 && smText.indexOf('<loc>' + ORIGIN + '/labs/</loc>') === -1);
    var sm304 = await req.get(ORIGIN + '/labs-sitemap.xml', { headers: { 'If-Modified-Since': sm.headers()['last-modified'] } });
    check(name + ' sitemap: 304 for an unchanged copy', sm304.status() === 304 && (await sm304.text()) === '');
    var rb = await (await req.get(ORIGIN + '/robots.txt')).text();
    check(name + ' robots.txt: Slim SEO sitemap kept and Labs sitemap added once', rb.indexOf('Sitemap: ' + ORIGIN + '/sitemap.xml') !== -1 && (rb.match(/labs-sitemap\.xml/g) || []).length === 1);

    var nf = await openPage(browser, 1440, '/labs/does-not-exist/');
    check(name + ' 404 for an unknown slug', nf.status() === 404);
    await nf.context.close();
    var upper = await openPage(browser, 1440, '/labs/JSON-Formatter/');
    check(name + ' 404 for an upper-case slug', upper.status() === 404);
    await upper.context.close();
}

// Release state: the shipped catalog, with the cost calculator in draft until its prices are reviewed.
async function releaseStateChecks(browser, name) {
    var o = { origin: RELEASE_ORIGIN };
    var pages = ['/labs/', '/labs/json-formatter/', '/labs/text-diff/', '/labs/robots-txt-generator/'];
    for (var w of [375, 768, 1440]) {
        for (var url of pages) {
            var s = await openPage(browser, w, url + '?anon=1', o);
            await wait(300);
            var html = await s.page.content();
            check(name + ' release ' + w + 'px ' + url + ': 200, no horizontal scroll, no trace of the draft calculator', s.status() === 200 && await noHorizontalScroll(s.page) && html.indexOf(COST) === -1 && html.indexOf('API料金') === -1);
            if (name === 'chromium') {
                var v = await axe(s.page);
                check(name + ' release ' + w + 'px ' + url + ': axe WCAG 2.2 AA clean', v.length === 0, v);
            }
            await consoleCheck(s, name, 'release ' + w + 'px ' + url);
            await s.context.close();
        }
    }
    var nf = await openPage(browser, 1440, '/labs/' + COST + '/?anon=1', o);
    check(name + ' release: the draft calculator URL is a 404', nf.status() === 404);
    await nf.context.close();
    var s2 = await openPage(browser, 375, '/labs/?anon=1', o);
    var p = s2.page;
    var visible = function () { return p.locator('[data-labs-card]:visible').count(); };
    check(name + ' release list: three tools, no AI category', (await visible()) === 3 && /3件のツール/.test(await p.textContent('[data-labs-count]')) && (await p.locator('[data-labs-cat="ai"]').count()) === 0);
    for (var q of ['トークン 料金', 'anthropic', 'API料金']) {
        await p.fill('#labs-search-q', q);
        await wait(400);
        check(name + ' release list: search "' + q + '" finds nothing (empty state)', (await visible()) === 0 && await p.isVisible('[data-labs-empty]'));
    }
    await p.fill('#labs-search-q', '');
    await p.click('[data-labs-cat="dev"]');
    await wait(300);
    check(name + ' release list: 開発 → 2, Web運用 → 1', (await visible()) === 2 && (await (async function () { await p.click('[data-labs-cat="web"]'); await wait(300); return visible(); }())) === 1);
    await s2.context.close();
    var req = (await browser.newContext()).request;
    var sm = await (await req.get(RELEASE_ORIGIN + '/labs-sitemap.xml')).text();
    check(name + ' release sitemap: the three published tools only', (sm.match(/<loc>/g) || []).length === 3 && sm.indexOf(COST) === -1);
}

(async function main() {
    var server = await startServer(PORT, true);
    var releaseServer = await startServer(RELEASE_PORT, false);
    try {
        if (shots) fs.mkdirSync(shots, { recursive: true });
        for (var name of (process.env.LABS_BROWSERS || 'chromium,webkit').split(',')) {
            // LABS_CHROMIUM_PATH / LABS_WEBKIT_PATH: use an already-installed browser build instead of downloading one.
            var exe = process.env['LABS_' + name.toUpperCase() + '_PATH'];
            var browser = await playwright[name].launch(exe ? { executablePath: exe } : {});
            await layoutChecks(browser, name);
            await toolChecks(browser, name);
            await consoleSelfTest(browser, name);
            await selectFocusChecks(browser, name);
            await releaseStateChecks(browser, name);
            await browser.close();
        }
    } catch (e) {
        fail++;
        console.log('FAIL unexpected: ' + (e && e.stack || e));
    } finally {
        server.kill();
        releaseServer.kill();
    }
    console.log('\n' + pass + ' passed, ' + fail + ' failed');
    process.exit(fail ? 1 : 0);
}());
