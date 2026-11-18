#!/usr/bin/env node
/**
 * Design Library: make each item's card media from its own demo.
 *  - poster: 1280x800 screenshot → posters/{slug}.avif (≤ 60 KB) and .webp
 *  - loop:   8 s scripted recording → loops/{slug}.webm (VP9) and .mp4 (H.264), 960x600, no audio (≤ 600 KB each)
 * Needs a LOCAL site serving the demos (DL_BASE=http://127.0.0.1:PORT, isolation test flag on),
 * Playwright (NODE_PATH) with its Chromium, and ffmpeg / avifenc / cwebp on PATH.
 *
 *   DL_BASE=http://127.0.0.1:8961 node tools/design-library/capture-media.js [slug ...]
 */
'use strict';
var path = require('path');
var fs = require('fs');
var os = require('os');
var cp = require('child_process');
var { chromium } = require('playwright');

var BASE = String(process.env.DL_BASE || '').replace(/\/$/, '');
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(BASE)) { console.error('DL_BASE must be http://127.0.0.1:PORT'); process.exit(2); }
var OUT = path.resolve(__dirname, '../../wp-theme/omochix-theme/assets/design');
var TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'omx-dl-media-'));
var W = 1280, H = 800, POSTER_MAX = 60 * 1024, LOOP_MAX = 600 * 1024, SKIP = 1.2, LEN = 8;
var GL_ARGS = ['--use-angle=swiftshader', '--enable-unsafe-swiftshader'];

function sh(cmd, args) { var r = cp.spawnSync(cmd, args, { encoding: 'utf8' }); if (r.status !== 0) throw new Error(cmd + ' failed: ' + (r.stderr || '').slice(-400)); }
async function smoothScroll(page, to, ms) {
    await page.evaluate(function (a) {
        return new Promise(function (res) {
            var from = window.scrollY, t0 = performance.now();
            function step(now) { var p = Math.min(1, (now - t0) / a.ms), e = p < .5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2; window.scrollTo(0, from + (a.to - from) * e); if (p < 1) requestAnimationFrame(step); else res(); }
            requestAnimationFrame(step);
        });
    }, { to: to, ms: ms });
}
async function glide(page, pts, ms) {
    var steps = Math.max(2, Math.round(ms / 16));
    for (var i = 0; i < pts.length - 1; i++) await page.mouse.move(pts[i + 1][0], pts[i + 1][1], { steps: Math.round(steps / (pts.length - 1)) });
}

/* Per demo: where the poster is taken and what the 8 s loop shows. Each starts at t=0 on a fresh load. */
var SCRIPTS = {
    'liquid-glass': {
        poster: async function (p) { await p.mouse.move(900, 300); await p.waitForTimeout(2200); },
        loop: async function (p) {
            await p.mouse.move(820, 260); await p.waitForTimeout(1300);
            await glide(p, [[820, 260], [1060, 420], [700, 560], [420, 300], [900, 300]], 4200);
            await smoothScroll(p, 760, 2600); await p.mouse.move(640, 420, { steps: 30 }); await p.waitForTimeout(1200);
        }
    },
    'minimal-editorial': {
        poster: async function (p) { await p.waitForTimeout(2400); },
        loop: async function (p) {
            await p.waitForTimeout(3000);
            await smoothScroll(p, 900, 2600); await p.waitForTimeout(600);
            var y = await p.evaluate(function () { return document.querySelector('.ed-pager').getBoundingClientRect().top + window.scrollY - 120; });
            await smoothScroll(p, y, 1200); await p.click('[data-ed-next]'); await p.waitForTimeout(1100); await p.click('[data-ed-next]'); await p.waitForTimeout(1200);
        }
    },
    '3d-interactive': {
        poster: async function (p) { await p.waitForTimeout(3200); },
        loop: async function (p) {
            await p.waitForTimeout(3300);
            await p.mouse.move(940, 400); await p.mouse.down();
            await p.mouse.move(1140, 360, { steps: 40 }); await p.mouse.move(900, 440, { steps: 40 }); await p.mouse.up();
            await p.waitForTimeout(400); await p.mouse.move(940, 410, { steps: 10 }); await p.waitForTimeout(1200);
            await smoothScroll(p, 520, 2200); await p.waitForTimeout(800);
        }
    },
    'bento-saas': {
        poster: async function (p) { await smoothScroll(p, 330, 10); await p.waitForTimeout(2600); },
        loop: async function (p) {
            await p.waitForTimeout(1600);
            await smoothScroll(p, 330, 1400); await p.waitForTimeout(2600);
            await p.mouse.move(980, 420, { steps: 20 }); await p.waitForTimeout(900);
            await smoothScroll(p, 760, 1800); await p.waitForTimeout(800);
        }
    },
    'dark-luxury': {
        poster: async function (p) { await p.waitForTimeout(2800); },
        loop: async function (p) {
            await p.evaluate(function () { document.documentElement.style.scrollBehavior = 'auto'; });   // the demo scrolls smoothly for anchors
            await p.waitForTimeout(2600);
            await smoothScroll(p, 640, 1800); await p.waitForTimeout(300);
            var y = await p.evaluate(function () { var w = document.querySelector('[data-dk-works]'); var top = w.getBoundingClientRect().top + window.scrollY; return [top, top + w.offsetHeight - window.innerHeight]; });
            await smoothScroll(p, y[0], 1200);
            await smoothScroll(p, y[1], 2600); await p.waitForTimeout(500);   // the pinned works slide sideways to the last one
        }
    },
    'aurora-gradient': {
        poster: async function (p) { await p.mouse.move(760, 220); await p.waitForTimeout(3200); },
        loop: async function (p) {
            await p.evaluate(function () { document.documentElement.style.scrollBehavior = 'auto'; });
            await p.mouse.move(640, 200); await p.waitForTimeout(2400);
            await glide(p, [[640, 200], [960, 160], [1120, 260], [760, 240]], 3600);
            await smoothScroll(p, 520, 2000); await p.waitForTimeout(600);
        }
    },
    'ai-workspace': {
        poster: async function (p) { await p.waitForTimeout(3000); },
        loop: async function (p) {
            await p.waitForTimeout(2600);   // the first answer appears block by block
            await p.click('[data-aw-cite="2"]'); await p.waitForTimeout(900);
            await p.keyboard.press('Control+k'); await p.waitForTimeout(400);
            await p.keyboard.type('テーマ', { delay: 90 }); await p.waitForTimeout(300); await p.keyboard.press('Enter'); await p.waitForTimeout(700);
            await p.click('[data-aw-input]'); await p.keyboard.type('予約制の不満点は？', { delay: 70 }); await p.keyboard.press('Enter');
            await p.waitForTimeout(2600);
        }
    },
    'micro-interactions': {
        poster: async function (p) { await p.click('label[for="mi-sw-2"]'); await p.waitForTimeout(700); },
        loop: async function (p) {
            await p.waitForTimeout(900);
            await p.click('label[for="mi-sw-2"]'); await p.waitForTimeout(700);
            await p.click('label[for="mi-start-25"]'); await p.waitForTimeout(700);
            await p.fill('#mi-amount', '12a'); await p.press('#mi-amount', 'Enter'); await p.waitForTimeout(700);
            await p.fill('#mi-amount', '1280'); await p.press('#mi-amount', 'Enter'); await p.waitForTimeout(400);
            await p.click('[data-mi-submit]'); await p.waitForTimeout(1600);
            await p.evaluate(function () { window.scrollTo(0, document.querySelector('#mi-list').offsetTop - 80); }); await p.waitForTimeout(500);
            await p.click('label[for="mi-it-2"]'); await p.waitForTimeout(800);
            await p.click('#mi-list .mi-item:nth-child(3) [data-mi-del]'); await p.waitForTimeout(1400);
        }
    },
    'interactive-story': {
        poster: async function (p) {
            await p.evaluate(function () { var e = document.querySelector('.sk-step[data-step="8"]'); window.scrollTo(0, e.getBoundingClientRect().top + window.scrollY - window.innerHeight * 0.4); });
            await p.waitForTimeout(1500);
        },
        loop: async function (p) {
            await p.evaluate(function () { document.documentElement.style.scrollBehavior = 'auto'; });
            var y = function (n) { return p.evaluate(function (k) { var e = document.querySelector('.sk-step[data-step="' + k + '"]'); return e.getBoundingClientRect().top + window.scrollY - window.innerHeight * 0.4; }, n); };
            await p.waitForTimeout(800);
            await smoothScroll(p, await y(1), 1400); await p.waitForTimeout(700);
            await smoothScroll(p, await y(3), 1300); await p.waitForTimeout(600);
            await smoothScroll(p, await y(6), 1500); await p.waitForTimeout(600);
            await smoothScroll(p, await y(8), 1300); await p.waitForTimeout(900);
        }
    }
};

async function poster(browser, slug) {
    var page = await browser.newPage({ viewport: { width: W, height: H } });
    await page.goto(BASE + '/design/demo/web/' + slug + '/');
    await SCRIPTS[slug].poster(page);
    var png = path.join(TMP, slug + '.png');
    await page.screenshot({ path: png });
    await page.close();
    var avif = path.join(OUT, 'posters', slug + '.avif'), q = 52;
    do { sh('avifenc', ['-q', String(q), '-s', '4', '-y', '420', png, avif]); q -= 6; } while (fs.statSync(avif).size > POSTER_MAX && q > 10);
    sh('cwebp', ['-q', '72', '-m', '6', png, '-o', path.join(OUT, 'posters', slug + '.webp')]);
    return fs.statSync(avif).size;
}
async function loop(browser, slug) {
    var dir = path.join(TMP, slug + '-rec');
    var ctx = await browser.newContext({ viewport: { width: W, height: H }, recordVideo: { dir: dir, size: { width: W, height: H } } });
    var page = await ctx.newPage();
    var t0 = Date.now();
    await page.goto(BASE + '/design/demo/web/' + slug + '/');
    await SCRIPTS[slug].loop(page);
    var elapsed = (Date.now() - t0) / 1000;
    var raw = await page.video().path();
    await ctx.close();
    var start = Math.max(0, Math.min(SKIP, elapsed - LEN));
    var vf = 'scale=960:600:flags=lanczos,fps=30';
    var webm = path.join(OUT, 'loops', slug + '.webm'), mp4 = path.join(OUT, 'loops', slug + '.mp4');
    var crf = 40;
    do { sh('ffmpeg', ['-y', '-loglevel', 'error', '-ss', String(start), '-t', String(LEN), '-i', raw, '-an', '-vf', vf, '-c:v', 'libvpx-vp9', '-b:v', '0', '-crf', String(crf), '-row-mt', '1', '-deadline', 'good', '-cpu-used', '2', webm]); crf += 4; } while (fs.statSync(webm).size > LOOP_MAX && crf < 60);
    crf = 30;
    do { sh('ffmpeg', ['-y', '-loglevel', 'error', '-ss', String(start), '-t', String(LEN), '-i', raw, '-an', '-vf', vf, '-c:v', 'libx264', '-preset', 'slow', '-crf', String(crf), '-pix_fmt', 'yuv420p', '-movflags', '+faststart', mp4]); crf += 3; } while (fs.statSync(mp4).size > LOOP_MAX && crf < 45);
    return [fs.statSync(webm).size, fs.statSync(mp4).size];
}

(async function () {
    var slugs = process.argv.slice(2).length ? process.argv.slice(2) : Object.keys(SCRIPTS);
    var browser = await chromium.launch({ args: GL_ARGS });
    for (var s of slugs) {
        var p = await poster(browser, s);
        var l = await loop(browser, s);
        console.log(s + ': poster.avif ' + Math.round(p / 1024) + ' KB, loop.webm ' + Math.round(l[0] / 1024) + ' KB, loop.mp4 ' + Math.round(l[1] / 1024) + ' KB');
    }
    await browser.close();
    fs.rmSync(TMP, { recursive: true, force: true });
})().catch(function (e) { console.error(e); process.exit(1); });
