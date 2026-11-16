#!/usr/bin/env node
/**
 * Design Library: PC (1440x900) and phone (390x844) screenshots of the six
 * detail pages and the top page, for before/after comparisons.
 *   DL_BASE=http://127.0.0.1:8961 NODE_PATH=<playwright> node tools/design-library/screens.js <out dir>
 */
'use strict';
var path = require('path');
var { chromium } = require('playwright');
var BASE = String(process.env.DL_BASE || '').replace(/\/$/, '');
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(BASE) || !process.argv[2]) { console.error('DL_BASE=http://127.0.0.1:PORT and an output dir are required'); process.exit(2); }
var OUT = path.resolve(process.argv[2]);
var PAGES = ['/design/', '/design/web/liquid-glass/', '/design/web/minimal-editorial/', '/design/web/3d-interactive/', '/design/web/bento-saas/', '/design/video/logo-reveal-short/', '/design/video/data-chart-explainer/'];
(async function () {
    var b = await chromium.launch();
    for (var [name, vp] of [['pc', { width: 1440, height: 900 }], ['phone', { width: 390, height: 844 }]]) {
        var c = await b.newContext({ viewport: vp, colorScheme: 'light' });
        var p = await c.newPage();
        for (var u of PAGES) {
            await p.goto(BASE + u);
            await p.waitForTimeout(2500);
            var file = path.join(OUT, name + (u.replace(/\//g, '_').replace(/_$/, '') || '_top') + '.png');
            await p.screenshot({ path: file });
            console.log(file);
        }
        await c.close();
    }
    await b.close();
})().catch(function (e) { console.error(e); process.exit(1); });
