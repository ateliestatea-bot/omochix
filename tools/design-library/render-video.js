#!/usr/bin/env node
/**
 * Design Library: render a video work from its HTML/CSS composition
 * (tools/design-library/video-sources/{slug}/index.html), frame by frame.
 *
 * Every CSS / Web Animation is paused and seeked to t = frame / fps, then the
 * frame is captured, so the output does not depend on machine speed. Frames
 * are piped to ffmpeg. Outputs (theme assets, all original work):
 *   videos/{slug}.mp4  (H.264, yuv420p, faststart, no audio)  — full work, detail page
 *   videos/{slug}.webm (VP9, no audio)                         — full work, detail page
 *   loops/{slug}.webm|.mp4 (card preview, long side 960)       — list cards
 *   posters/{slug}.avif|.webp (poster frame)
 *
 *   NODE_PATH=<playwright> node tools/design-library/render-video.js <slug> [--poster-at=4.8]
 * Needs ffmpeg, avifenc and cwebp on PATH. Only reads the local source file; no network.
 */
'use strict';
var path = require('path');
var fs = require('fs');
var os = require('os');
var cp = require('child_process');
var { chromium } = require('playwright');

var SPECS = {
    'logo-reveal-short': { width: 1080, height: 1920, fps: 30, seconds: 6, posterAt: 4.8 },
    'data-chart-explainer': { width: 1920, height: 1080, fps: 30, seconds: 8, posterAt: 6.4 }
};
var slug = process.argv[2];
var spec = SPECS[slug];
if (!spec) { console.error('usage: render-video.js <' + Object.keys(SPECS).join('|') + '>'); process.exit(2); }
var SRC = path.join(__dirname, 'video-sources', slug, 'index.html');
var OUT = path.resolve(__dirname, '../../wp-theme/omochix-theme/assets/design');
var TMP = fs.mkdtempSync(path.join(os.tmpdir(), 'omx-dl-video-'));
['videos', 'loops', 'posters'].forEach(function (d) { fs.mkdirSync(path.join(OUT, d), { recursive: true }); });
function sh(cmd, args) { var r = cp.spawnSync(cmd, args, { encoding: 'utf8' }); if (r.status !== 0) throw new Error(cmd + ': ' + (r.stderr || '').slice(-400)); }

(async function () {
    var browser = await chromium.launch();
    var page = await browser.newPage({ viewport: { width: spec.width, height: spec.height }, deviceScaleFactor: 1 });
    await page.route('**/*', function (r) { return r.request().url().indexOf('file://') === 0 ? r.continue() : r.abort(); }); // no network
    await page.goto('file://' + SRC);
    await page.evaluate(function () { return document.fonts.ready; });
    var frames = Math.round(spec.fps * spec.seconds);
    var raw = path.join(TMP, 'frames.mp4');
    var ff = cp.spawn('ffmpeg', ['-y', '-loglevel', 'error', '-f', 'image2pipe', '-framerate', String(spec.fps), '-c:v', 'png', '-i', '-', '-c:v', 'libx264', '-preset', 'slow', '-crf', '12', '-pix_fmt', 'yuv420p', raw], { stdio: ['pipe', 'inherit', 'inherit'] });
    var posterPng = path.join(TMP, 'poster.png');
    for (var i = 0; i < frames; i++) {
        var t = i / spec.fps * 1000;
        await page.evaluate(function (ms) { document.getAnimations().forEach(function (a) { a.pause(); a.currentTime = ms; }); }, t);
        var png = await page.screenshot({ type: 'png' });
        if (!ff.stdin.write(png)) await new Promise(function (r) { ff.stdin.once('drain', r); });
        if (Math.abs(t / 1000 - spec.posterAt) < 0.5 / spec.fps) fs.writeFileSync(posterPng, png);
    }
    ff.stdin.end();
    await new Promise(function (res, rej) { ff.on('close', function (c) { c === 0 ? res() : rej(new Error('ffmpeg ' + c)); }); });
    await browser.close();

    var full = path.join(OUT, 'videos', slug);
    sh('ffmpeg', ['-y', '-loglevel', 'error', '-i', raw, '-an', '-c:v', 'libx264', '-preset', 'slow', '-crf', '24', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', full + '.mp4']);
    sh('ffmpeg', ['-y', '-loglevel', 'error', '-i', raw, '-an', '-c:v', 'libvpx-vp9', '-b:v', '0', '-crf', '36', '-row-mt', '1', '-deadline', 'good', '-cpu-used', '2', full + '.webm']);
    var scale = spec.width >= spec.height ? 'scale=960:-2' : 'scale=-2:960';
    var loop = path.join(OUT, 'loops', slug);
    sh('ffmpeg', ['-y', '-loglevel', 'error', '-i', raw, '-an', '-vf', scale, '-c:v', 'libvpx-vp9', '-b:v', '0', '-crf', '42', '-row-mt', '1', '-deadline', 'good', '-cpu-used', '2', loop + '.webm']);
    sh('ffmpeg', ['-y', '-loglevel', 'error', '-i', raw, '-an', '-vf', scale, '-c:v', 'libx264', '-preset', 'slow', '-crf', '30', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', loop + '.mp4']);
    var poster = path.join(OUT, 'posters', slug);
    var small = path.join(TMP, 'poster-small.png');
    sh('ffmpeg', ['-y', '-loglevel', 'error', '-i', posterPng, '-vf', spec.width >= spec.height ? 'scale=1280:-2' : 'scale=-2:1280', small]);
    sh('avifenc', ['-q', '50', '-s', '4', '-y', '420', small, poster + '.avif']);
    sh('cwebp', ['-quiet', '-q', '72', '-m', '6', small, '-o', poster + '.webp']);
    fs.rmSync(TMP, { recursive: true, force: true });
    ['videos/' + slug + '.mp4', 'videos/' + slug + '.webm', 'loops/' + slug + '.webm', 'loops/' + slug + '.mp4', 'posters/' + slug + '.avif', 'posters/' + slug + '.webp'].forEach(function (f) {
        console.log(f + ' ' + Math.round(fs.statSync(path.join(OUT, f)).size / 1024) + ' KiB');
    });
})().catch(function (e) { console.error(e); process.exit(1); });
