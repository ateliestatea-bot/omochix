#!/usr/bin/env node
/**
 * OmochiX Labs browser checks (local only; not part of CI).
 *
 * Starts tests/labs/preview-server.php and, in Chromium and WebKit:
 *  - 375 / 768 / 1440 px: no horizontal scroll, axe (WCAG 2.2 A/AA) clean
 *  - every tool: sample → run → copy → reset, error states, keyboard-only use
 *  - Web Worker for large input and the time limit
 *  - no request leaves 127.0.0.1, no CSP (Report-Only) violation
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

var root = path.resolve(__dirname, '..', '..', '..');
var playwright = require(process.env.LABS_PLAYWRIGHT || 'playwright');
var axePath = process.env.LABS_AXE || require.resolve('axe-core/axe.min.js');
var shots = process.env.LABS_SHOTS || '';
var PORT = 8791;
var ORIGIN = 'http://127.0.0.1:' + PORT;
var TOOLS = ['json-formatter', 'text-diff', 'robots-txt-generator', 'token-cost-calculator'];
var EVENTS = ['labs_tool_view', 'labs_tool_run', 'labs_tool_success', 'labs_tool_error', 'labs_tool_copy', 'labs_related_click'];
var SECRET = 'SECRET-INPUT-7f3a';

var pass = 0;
var fail = 0;
function check(name, cond, detail) {
    if (cond) { pass++; console.log('PASS ' + name); }
    else { fail++; console.log('FAIL ' + name + (detail ? '\n  ' + JSON.stringify(detail).slice(0, 1500) : '')); }
}

function startServer() {
    var proc = spawn('php', ['-S', '127.0.0.1:' + PORT, '-t', root, path.join(root, 'tests/labs/preview-server.php')], { stdio: 'ignore' });
    return new Promise(function (resolve) { setTimeout(function () { resolve(proc); }, 700); });
}

async function openPage(browser, width, url, opts) {
    var context = await browser.newContext({ viewport: { width: width, height: 900 }, colorScheme: (opts && opts.dark) ? 'dark' : 'light' });
    if (browser.browserType().name() === 'chromium') await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: ORIGIN });
    var page = await context.newPage();
    var external = [];
    var status = 0;
    page.on('request', function (req) { if (req.url().indexOf(ORIGIN) !== 0 && !/^(data|blob):/.test(req.url())) external.push(req.url()); });
    page.on('response', function (res) { if (res.url().split('?')[0] === (ORIGIN + url).split('?')[0]) status = res.status(); });
    var errors = [];
    page.on('pageerror', function (e) { errors.push(String(e)); });
    await page.addInitScript(function () {
        window.__csp = [];
        document.addEventListener('securitypolicyviolation', function (e) { window.__csp.push(e.violatedDirective + ' ' + e.blockedURI); });
    });
    await page.goto(ORIGIN + url);
    return { page: page, context: context, external: external, errors: errors, status: function () { return status; } };
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
            if (shots && w !== 768) await s.page.screenshot({ path: path.join(shots, name + '-' + w + url.replace(/\//g, '_') + '.png'), fullPage: true });
            await s.context.close();
        }
    }
    var dark = await openPage(browser, 1440, '/labs/text-diff/?theme=dark', { dark: true });
    await dark.page.click('[data-labs-sample]');
    await wait(500);
    if (name === 'chromium') check(name + ' dark theme: axe clean with a diff on screen', (await axe(dark.page)).length === 0, await axe(dark.page));
    if (shots) await dark.page.screenshot({ path: path.join(shots, name + '-dark-text-diff.png'), fullPage: true });
    await dark.context.close();
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
    check(name + ' robots: no external requests, no CSP violations', s.external.length === 0 && (await p.evaluate(function () { return window.__csp; })).length === 0, { external: s.external, csp: await p.evaluate(function () { return window.__csp; }) });
    await s.context.close();

    // --- cost ---------------------------------------------------------
    s = await openPage(browser, 1440, '/labs/token-cost-calculator/?gtag=1');
    p = s.page;
    check(name + ' cost: defaults computed for the three preselected models', (await p.locator('[data-labs-cost-rows] tr').count()) === 3);
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

(async function main() {
    var server = await startServer();
    try {
        if (shots) fs.mkdirSync(shots, { recursive: true });
        for (var name of (process.env.LABS_BROWSERS || 'chromium,webkit').split(',')) {
            // LABS_CHROMIUM_PATH / LABS_WEBKIT_PATH: use an already-installed browser build instead of downloading one.
            var exe = process.env['LABS_' + name.toUpperCase() + '_PATH'];
            var browser = await playwright[name].launch(exe ? { executablePath: exe } : {});
            await layoutChecks(browser, name);
            await toolChecks(browser, name);
            await browser.close();
        }
    } catch (e) {
        fail++;
        console.log('FAIL unexpected: ' + (e && e.stack || e));
    } finally {
        server.kill();
    }
    console.log('\n' + pass + ' passed, ' + fail + ' failed');
    process.exit(fail ? 1 : 0);
}());
