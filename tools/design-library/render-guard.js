/**
 * Design Library media rendering: shared guards (R-7).
 *
 *  - outDir():      where rendered media go. Required (DL_OUT, absolute). Refused if it is inside this
 *                   repository (so the theme's committed media can never be overwritten) or not empty.
 *  - checkFonts():  the OFL fonts in tools/design-library/fonts/fonts.lock.json must be installed
 *                   (~/Library/Fonts) with the recorded SHA-256, or nothing is rendered.
 *  - FONT_SWAP:     init script for demo pages: every element's font-family becomes "Noto Sans JP" or
 *                   "Noto Serif JP" (inline, !important), keeping the sans / serif choice of the demo's
 *                   own stack; elements added later are swapped too. The demo files are not changed.
 *  - auditFonts():  asks Chromium (DevTools CSS.getPlatformFontsForNode) which fonts actually drew the
 *                   text of every element and pseudo-element. Anything other than the two Noto families
 *                   is a failure.
 */
'use strict';
var path = require('path');
var fs = require('fs');
var os = require('os');
var crypto = require('crypto');

var REPO = path.resolve(__dirname, '../..');
var ALLOWED = ['Noto Sans JP', 'Noto Serif JP'];

function within(child, parent) { var r = path.relative(parent, child); return r === '' || (!r.startsWith('..') && !path.isAbsolute(r)); }

function outDir() {
    var o = process.env.DL_OUT || '';
    if (!o || !path.isAbsolute(o)) throw new Error('DL_OUT must be an absolute path outside the repository');
    var real = fs.existsSync(o) ? fs.realpathSync(o) : path.join(fs.realpathSync(path.dirname(o)), path.basename(o));
    if (within(real, fs.realpathSync(REPO))) throw new Error('DL_OUT is inside the repository (' + real + '): refused, nothing written');
    if (fs.existsSync(real) && fs.readdirSync(real).filter(function (n) { return n !== 'audit'; }).some(function (n) {
        return ['posters', 'loops', 'videos'].indexOf(n) < 0 || fs.readdirSync(path.join(real, n)).length > 0 && !process.env.DL_APPEND;
    })) throw new Error('DL_OUT is not empty (' + real + '): use a new folder');
    ['posters', 'loops', 'videos', 'audit'].forEach(function (d) { fs.mkdirSync(path.join(real, d), { recursive: true }); });
    return real;
}

function sha256(file) { return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex'); }

function checkFonts() {
    var lock = JSON.parse(fs.readFileSync(path.join(__dirname, 'fonts', 'fonts.lock.json'), 'utf8'));
    var dir = path.join(os.homedir(), 'Library', 'Fonts');
    lock.fonts.forEach(function (f) {
        var p = path.join(dir, path.basename(f.path));
        if (!fs.existsSync(p)) throw new Error('font not installed: ' + p);
        if (sha256(p) !== f.sha256) throw new Error('installed font does not match fonts.lock.json: ' + p);
    });
    return lock.fonts.map(function (f) { return f.family + ' ' + f.name_version.split(';')[0]; });
}

/* Runs in the page before its own scripts. Plain ES5; no network. */
var FONT_SWAP = '(' + function () {
    var SERIF = /^(hiragino mincho pron|hiragino mincho|yu mincho|yumincho|noto serif jp|serif|times|times new roman|georgia)$/i;
    var SANS = /^(hiragino sans|hiragino kaku gothic pron|yu gothic|yugothic|noto sans jp|system-ui|-apple-system|blinkmacsystemfont|helvetica neue|helvetica|arial|sans-serif|ui-sans-serif)$/i;
    function pick(stack) {
        var parts = String(stack || '').split(',');
        for (var i = 0; i < parts.length; i++) {
            var t = parts[i].trim().replace(/^["']|["']$/g, '');
            if (SERIF.test(t)) return '"Noto Serif JP"';
            if (SANS.test(t)) return '"Noto Sans JP"';
        }
        return '"Noto Sans JP"';
    }
    function swap(els) {
        var want = [];
        for (var i = 0; i < els.length; i++) if (els[i].nodeType === 1) want.push([els[i], pick(getComputedStyle(els[i]).fontFamily)]);
        for (var j = 0; j < want.length; j++) want[j][0].style.setProperty('font-family', want[j][1], 'important');
    }
    function all(root) { var list = [root]; var q = root.querySelectorAll ? root.querySelectorAll('*') : []; for (var i = 0; i < q.length; i++) list.push(q[i]); return list; }
    /* Pseudo-elements (::first-letter drop caps, ::before / ::after labels) cannot take an inline style, so
       rules that target one and set their own font are rewritten in memory (CSSOM only; files untouched).
       var(--x) is resolved on :root first, so a var(--serif) drop cap stays serif. */
    function swapRules() {
        var root = getComputedStyle(document.documentElement);
        function resolve(v) { return String(v).replace(/var\((--[\w-]+)[^)]*\)/g, function (_, n) { return root.getPropertyValue(n) || ''; }); }
        function walk(rules) {
            for (var i = 0; i < rules.length; i++) {
                var r = rules[i];
                if (r.cssRules && !r.selectorText) { walk(r.cssRules); continue; }
                if (!r.style || !/::?(first-letter|first-line|before|after|marker|placeholder|selection)/.test(r.selectorText || '')) continue;
                var ff = r.style.getPropertyValue('font-family');
                if (ff) r.style.setProperty('font-family', pick(resolve(ff)), 'important');
            }
        }
        for (var s = 0; s < document.styleSheets.length; s++) { try { walk(document.styleSheets[s].cssRules); } catch (e) { /* cross-origin sheet: none in the demos */ } }
    }
    function start() {
        swapRules();
        swap(all(document.documentElement));
        new MutationObserver(function (ms) {
            var add = [];
            ms.forEach(function (m) { for (var i = 0; i < m.addedNodes.length; i++) if (m.addedNodes[i].nodeType === 1) add = add.concat(all(m.addedNodes[i])); });
            if (add.length) swap(add);
        }).observe(document.documentElement, { childList: true, subtree: true });
        window.__omxFontSwap = true;
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
} + ')();';

async function auditFonts(page, label) {
    var cdp = await page.context().newCDPSession(page);
    await cdp.send('DOM.enable'); await cdp.send('CSS.enable');
    var doc = await cdp.send('DOM.getDocument', { depth: -1, pierce: true });
    var nodes = [];
    (function walk(n) {
        if (n.nodeType === 1 && (n.children || []).some(function (c) { return c.nodeType === 3 && /\S/.test(c.nodeValue); })) nodes.push(n.nodeId);
        (n.pseudoElements || []).forEach(function (p) { nodes.push(p.nodeId); });
        (n.children || []).forEach(walk);
        if (n.contentDocument) walk(n.contentDocument);
        (n.shadowRoots || []).forEach(walk);
    })(doc.root);
    var used = {}, bad = [];
    for (var i = 0; i < nodes.length; i++) {
        var r;
        try { r = await cdp.send('CSS.getPlatformFontsForNode', { nodeId: nodes[i] }); } catch (e) { continue; }
        (r.fonts || []).forEach(function (f) {
            used[f.familyName] = (used[f.familyName] || 0) + f.glyphCount;
            if (ALLOWED.indexOf(f.familyName) < 0) bad.push(f.familyName);
        });
    }
    await cdp.detach();
    return { label: label, nodes: nodes.length, fonts: used, notAllowed: Array.from(new Set(bad)), pass: bad.length === 0 && Object.keys(used).length > 0 };
}

/* Freshly installed fonts can take seconds before macOS lets Chromium use them; until then text silently
   falls back to Hiragino / Times. Wait (up to ~60 s) until a probe page is drawn with both Noto families. */
async function waitForFonts(browser) {
    for (var i = 0; i < 12; i++) {
        var p = await browser.newPage();
        await p.setContent('<p style="font-family:&quot;Noto Sans JP&quot;">日本語 ABC</p><p style="font-family:&quot;Noto Serif JP&quot;">明朝 ABC</p>');
        await p.evaluate(function () { return document.fonts.ready; });
        var a = await auditFonts(p, 'font readiness probe');
        await p.close();
        if (a.pass && a.fonts['Noto Sans JP'] && a.fonts['Noto Serif JP']) return a;
        await new Promise(function (r) { setTimeout(r, 5000); });
    }
    throw new Error('the Noto fonts are installed but Chromium does not use them yet (font registration); nothing rendered');
}

module.exports = { outDir: outDir, checkFonts: checkFonts, FONT_SWAP: FONT_SWAP, auditFonts: auditFonts, waitForFonts: waitForFonts, ALLOWED: ALLOWED, REPO: REPO };
