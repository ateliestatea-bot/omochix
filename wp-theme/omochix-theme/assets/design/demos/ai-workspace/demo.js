/* AI Workspace UI (fictional "NOKURA"). Nothing typed here is sent or stored: replies are prepared text,
   inserted with textContent. Timers wait while the demo is paused or hidden; reduced motion and Save-Data
   show every reply at once. No frame loop, no network, no storage. */
(function () {
    'use strict';
    var demo = window.omxDemo;
    var root = document.documentElement;
    var app = document.querySelector('[data-aw]');
    if (!app) return;
    var $ = function (s, el) { return (el || app).querySelector(s); };
    var $$ = function (s, el) { return Array.prototype.slice.call((el || app).querySelectorAll(s)); };
    var saveData = !!(navigator.connection && navigator.connection.saveData);
    var instant = function () { return demo.isReduced() || saveData; };
    var statusEl = $('[data-aw-status]');
    function say(t) { statusEl.textContent = ''; setTimeout(function () { statusEl.textContent = t; }, 30); }

    /* timers that hold while paused or hidden, and continue afterwards */
    var held = [];
    function later(fn, ms) { setTimeout(function () { if (demo.isHidden()) held.push(fn); else fn(); }, ms); }
    demo.onVisibility(function (hidden) { if (!hidden) { var q = held; held = []; q.forEach(function (fn) { later(fn, 60); }); } });

    if (!/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) $$('kbd').forEach(function (k) { if (k.textContent === '⌘K') k.textContent = 'Ctrl K'; });

    /* theme */
    var themeBtn = $('[data-aw-theme]');
    function themeLabel() { themeBtn.setAttribute('aria-label', root.getAttribute('data-theme') === 'dark' ? 'ライトテーマに切り替える' : 'ダークテーマに切り替える'); }
    if (window.parent === window && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) root.setAttribute('data-theme', 'dark');
    function toggleTheme() { root.setAttribute('data-theme', root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); themeLabel(); say(root.getAttribute('data-theme') === 'dark' ? 'ダークテーマにしました' : 'ライトテーマにしました'); }
    themeBtn.addEventListener('click', toggleTheme);
    demo.onTheme(themeLabel);
    themeLabel();

    /* sources panel (wide screens), sidebar drawer (tablets), one view at a time (phones) */
    var panelBtn = $('[data-aw-panel]');
    function togglePanel(force) {
        var off = typeof force === 'boolean' ? !force : !app.classList.contains('aw--nopanel');
        app.classList.toggle('aw--nopanel', off);
        panelBtn.setAttribute('aria-expanded', String(!off));
        panelBtn.setAttribute('aria-label', off ? '出典パネルを開く' : '出典パネルを閉じる');
    }
    panelBtn.addEventListener('click', function () { togglePanel(); });
    var navBtn = $('[data-aw-nav]');
    function nav(open) { app.classList.toggle('is-nav', open); navBtn.setAttribute('aria-expanded', String(open)); if (open) $('.aw-side a.is-current').focus(); }
    navBtn.addEventListener('click', function () { nav(!app.classList.contains('is-nav')); });
    $$('.aw-side a').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); if (app.classList.contains('is-nav')) { nav(false); navBtn.focus(); } show('chat'); }); });
    app.setAttribute('data-view', 'chat');
    var viewTabs = $$('[data-aw-show]');
    function show(v) {
        app.setAttribute('data-view', v);
        viewTabs.forEach(function (t) { var on = t.getAttribute('data-aw-show') === v; t.setAttribute('aria-selected', String(on)); t.tabIndex = on ? 0 : -1; });
    }
    function roving(tabs, pick) {
        tabs.forEach(function (t, i) {
            t.addEventListener('click', function () { pick(t); });
            t.addEventListener('keydown', function (e) {
                var n = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
                if (!n) return;
                e.preventDefault();
                var next = tabs[(i + n + tabs.length) % tabs.length];
                pick(next); next.focus();
            });
        });
    }
    roving(viewTabs, function (t) { show(t.getAttribute('data-aw-show')); });
    var panelTabs = $$('.aw-tabs [role="tab"]');
    function panelTab(id) {
        panelTabs.forEach(function (t) { var on = t.id === id; t.setAttribute('aria-selected', String(on)); t.tabIndex = on ? 0 : -1; document.getElementById(t.getAttribute('aria-controls')).hidden = !on; });
    }
    roving(panelTabs, function (t) { panelTab(t.id); });

    /* citations open the source they point to */
    function openSource(n) {
        togglePanel(true); panelTab('aw-tab-src');
        if (window.matchMedia('(max-width: 767px)').matches) show('sources');
        var src = document.getElementById('aw-src-' + n);
        $$('.aw-src.is-focus').forEach(function (s) { s.classList.remove('is-focus'); });
        src.classList.add('is-focus');
        src.focus({ preventScroll: true });
        src.scrollIntoView({ block: 'nearest', behavior: instant() ? 'auto' : 'smooth' });
        later(function () { src.classList.remove('is-focus'); }, 1800);
    }
    app.addEventListener('click', function (e) {
        var c = e.target.closest('[data-aw-cite]');
        if (c) { $$('[data-aw-cite]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === c)); }); openSource(c.getAttribute('data-aw-cite')); }
    });
    $$('[data-aw-cite]').forEach(function (b) { b.setAttribute('aria-pressed', 'false'); });

    /* source 3: error, then a retry that loads */
    var bad = $('[data-aw-src-error]'), retry = $('[data-aw-retry]');
    function reload() {
        if (!bad.classList.contains('aw-src--error') || retry.getAttribute('aria-busy') === 'true') return;
        retry.setAttribute('aria-busy', 'true'); retry.textContent = '読み込み中…';
        later(function () { bad.classList.remove('aw-src--error'); retry.removeAttribute('aria-busy'); say('出典 3 を読み込みました'); }, instant() ? 200 : 900);
    }
    retry.addEventListener('click', reload);

    /* replies: blocks appear one after another (prepared text only) */
    var log = $('[data-aw-log]'), empty = $('[data-aw-empty]'), input = $('[data-aw-input]'), send = $('[data-aw-send]'), sendLabel = $('[data-aw-send-label]');
    var streaming = null;
    function reveal(blocks, done) {
        if (instant()) { blocks.forEach(function (b) { b.classList.remove('is-pending'); }); if (done) done(); return; }
        var run = { stop: false };
        streaming = run;
        send.setAttribute('aria-busy', 'true'); sendLabel.textContent = '止める';
        var i = 0;
        (function next() {
            if (run.stop) return;
            if (i >= blocks.length) { finish(); if (done) done(); return; }
            blocks[i++].classList.remove('is-pending');
            later(next, 380);
        })();
    }
    function finish(stopped) {
        if (!streaming) return;
        streaming.stop = true; streaming = null;
        send.removeAttribute('aria-busy'); sendLabel.textContent = '送る';
        if (stopped) {
            $$('.is-pending', log).forEach(function (b) { b.remove(); });
            var note = el('p', 'aw-who', '回答を途中で止めました');
            log.lastElementChild.appendChild(note);
            say('回答を途中で止めました');
        }
    }
    function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text) n.textContent = text; return n; }
    var REPLY = ['これは見本の返答です。入力された内容は送信も保存もされていません。', '実際のアプリでは、選んだ資料を読み、出典の番号を付けて答えます。たとえば来館者の数は「来館者統計 2025」から引きます。', '続けて聞くときは、候補を押すか、もう一度入力してください。'];
    function ask(text) {
        text = String(text).replace(/\s+/g, ' ').trim().slice(0, 400);
        if (!text || streaming) return;
        empty.hidden = true; log.hidden = false;
        var u = el('div', 'aw-msg aw-msg--user is-new');
        u.appendChild(el('p', 'aw-who', 'あなた')); u.appendChild(el('p', '', text));
        log.appendChild(u);
        var a = el('article', 'aw-msg aw-msg--ai is-new'); a.setAttribute('aria-label', 'NOKURA の回答');
        var who = el('p', 'aw-who'); var mark = el('span', 'aw-brand__mark'); mark.setAttribute('aria-hidden', 'true'); who.appendChild(mark); who.appendChild(document.createTextNode('NOKURA'));
        a.appendChild(who);
        var skel = el('div', 'aw-skel'); skel.setAttribute('aria-hidden', 'true'); for (var k = 0; k < 3; k++) skel.appendChild(el('i'));
        a.appendChild(skel);
        log.appendChild(a);
        a.scrollIntoView({ block: 'end', behavior: instant() ? 'auto' : 'smooth' });
        a.setAttribute('aria-busy', 'true');
        later(function () {
            skel.remove(); a.removeAttribute('aria-busy');
            var blocks = [el('p', 'aw-s is-pending', '「' + text + '」について：')].concat(REPLY.map(function (t) { return el('p', 'aw-s is-pending', t); }));
            blocks.forEach(function (b) { a.appendChild(b); });
            reveal(blocks, function () { a.scrollIntoView({ block: 'end' }); });
        }, instant() ? 0 : 700);
    }
    function submit() { if (streaming) { finish(true); return; } var t = input.value; input.value = ''; grow(); ask(t); }
    send.addEventListener('click', submit);
    function grow() { input.style.height = 'auto'; input.style.height = Math.min(160, input.scrollHeight) + 'px'; }
    input.addEventListener('input', grow);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && e.keyCode !== 229) { e.preventDefault(); submit(); } });
    app.addEventListener('click', function (e) { var s = e.target.closest('[data-aw-suggest]'); if (s) { show('chat'); ask(s.getAttribute('data-aw-suggest')); } });
    function startNew() {
        finish(true);
        log.hidden = true; empty.hidden = false;
        $('.aw-thread__head h1').textContent = '新しい調査';
        show('chat'); input.focus(); say('新しい調査を始めました');
    }
    $('[data-aw-new]').addEventListener('click', function () { if (app.classList.contains('is-nav')) nav(false); startNew(); });

    /* the first answer appears block by block when the demo opens */
    var first = $$('.aw-msg--ai > :not(.aw-who)', log);
    first.forEach(function (b) { b.classList.add('aw-s', 'is-pending'); });
    later(function () { reveal(first); }, 300);

    /* command palette: ⌘K / Ctrl+K, arrows, Enter, Esc; focus stays in the dialog and returns afterwards */
    var pal = $('[data-aw-palette]'), q = $('[data-aw-palette-q]'), opts = $$('[data-aw-palette-list] [role="option"]'), none = $('[data-aw-palette-none]');
    var active = 0, back = null;
    function visible() { return opts.filter(function (o) { return !o.hidden; }); }
    function mark(i) {
        var v = visible(); active = v.length ? (i + v.length) % v.length : 0;
        opts.forEach(function (o) { o.setAttribute('aria-selected', 'false'); });
        if (v[active]) { v[active].setAttribute('aria-selected', 'true'); q.setAttribute('aria-activedescendant', v[active].id); v[active].scrollIntoView({ block: 'nearest' }); } else q.removeAttribute('aria-activedescendant');
        none.hidden = v.length > 0;
    }
    function openPalette() { if (!pal.hidden) return; back = document.activeElement; pal.hidden = false; q.value = ''; opts.forEach(function (o) { o.hidden = false; }); mark(0); q.focus(); }
    function closePalette() { if (pal.hidden) return; pal.hidden = true; if (back && back.focus) back.focus(); }
    var CMDS = {
        'new': startNew,
        sources: function () { openSource(1); },
        memo: function () { togglePanel(true); panelTab('aw-tab-memo'); if (window.matchMedia('(max-width: 767px)').matches) show('sources'); document.getElementById('aw-memo-text').focus(); },
        theme: toggleTheme,
        panel: function () { togglePanel(); },
        retry: function () { openSource(3); reload(); }
    };
    function run(o) { var c = o.getAttribute('data-cmd'); pal.hidden = true; back = null; CMDS[c](); if (c === 'theme' || c === 'panel') themeBtn.focus(); }
    $('[data-aw-open-palette]').addEventListener('click', openPalette);
    q.addEventListener('input', function () { var t = q.value.trim().toLowerCase(); opts.forEach(function (o) { o.hidden = t !== '' && o.textContent.toLowerCase().indexOf(t) === -1; }); mark(0); });
    q.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); mark(active + (e.key === 'ArrowDown' ? 1 : -1)); }
        else if (e.key === 'Enter') { e.preventDefault(); var v = visible()[active]; if (v) run(v); }
        else if (e.key === 'Tab') { e.preventDefault(); }
    });
    opts.forEach(function (o) { o.addEventListener('click', function () { run(o); }); });
    pal.addEventListener('click', function (e) { if (e.target === pal) closePalette(); });
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); if (pal.hidden) openPalette(); else closePalette(); }
        else if (e.key === 'Escape') { if (!pal.hidden) closePalette(); else if (app.classList.contains('is-nav')) { nav(false); navBtn.focus(); } }
    });
})();
