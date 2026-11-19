/* Interactive Storytelling (fictional "紙季廊 / SHIKIRO"). The fixed illustration follows the paragraph in the
   middle of the screen (IntersectionObserver; no frame loop). While the demo is paused or hidden nothing changes
   (CSS transitions are not held by the runtime's pause, so state changes wait) and the latest paragraph is applied
   once on resume. Reading progress uses one rAF per scroll burst. No network, no storage. */
(function () {
    'use strict';
    var demo = window.omxDemo;
    var root = document.documentElement;
    var app = document.querySelector('[data-sk]');
    if (!app) return;
    var $ = function (s, el) { return (el || app).querySelector(s); };
    var $$ = function (s, el) { return Array.prototype.slice.call((el || app).querySelectorAll(s)); };
    var saveData = !!(navigator.connection && navigator.connection.saveData);
    if (saveData) root.classList.add('sk-instant');
    var instant = function () { return demo.isReduced() || saveData; };
    var status = $('[data-sk-status]');
    function say(t) { status.textContent = ''; setTimeout(function () { status.textContent = t; }, 40); }

    var stage = $('[data-sk-stage]'), uses = $$('.sk-u'), steps = $$('.sk-step');
    var chapterLabel = $('[data-sk-chapter]'), cap = $('[data-sk-cap]');
    var TITLE = chapterLabel.textContent;
    var titles = {};
    $$('.sk-ch').forEach(function (c) { titles[c.getAttribute('data-ch')] = c.querySelector('.sk-ch__h').textContent.replace(/^(第\d章)/, '$1 '); });
    var current = 0, pending = 0;

    /* one paragraph becomes the current one */
    function apply(n) {
        if (demo.isHidden()) { pending = n; return; }
        pending = 0;
        if (n === current) return;
        current = n;
        uses.forEach(function (u) { u.classList.toggle('is-on', Number(u.getAttribute('data-n')) === n); });
        steps.forEach(function (s) { s.classList.toggle('is-active', Number(s.getAttribute('data-step')) === n); });
        stage.style.setProperty('--s', String(n));
        stage.style.setProperty('--p', String(n / 13));
        var step = steps[n - 1], ch = step ? step.getAttribute('data-ch') : '0';
        app.setAttribute('data-ch', ch);
        chapterLabel.textContent = titles[ch] || TITLE;
        cap.textContent = titles[ch] || titles['1'];
    }
    uses[0].classList.add('is-on');
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { if (e.isIntersecting) apply(Number(e.target.getAttribute('data-step'))); });
    }, { rootMargin: '-50% 0px -40% 0px' });
    steps.forEach(function (s) { io.observe(s); });

    /* reading progress: one frame per scroll burst, none while paused or hidden */
    var bar = $('[data-sk-progress]'), ticking = false;
    function progress() {
        ticking = false;
        var max = document.documentElement.scrollHeight - window.innerHeight;
        bar.style.transform = 'scaleX(' + (max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0).toFixed(4) + ')';
    }
    window.addEventListener('scroll', function () {
        if (ticking || demo.isHidden()) return;
        ticking = true;
        window.requestAnimationFrame(progress);
    }, { passive: true });
    demo.onVisibility(function (hidden) {
        if (hidden) return;
        if (pending) apply(pending);
        progress();
    });
    progress();

    /* voice: the CSS switches the text (also without JS); JS keeps the reader's place and says what changed */
    $$('[data-sk-voice]').forEach(function (r) {
        r.addEventListener('change', function () {
            var anchor = steps[current - 1] || null, before = anchor ? anchor.getBoundingClientRect().top : 0;
            app.classList.toggle('is-paper', r.value === 'paper' && r.checked);
            if (anchor && anchor.getBoundingClientRect().bottom > 0) window.scrollBy(0, anchor.getBoundingClientRect().top - before);
            say(r.value === 'paper' ? '紙の目で読んでいます' : '職人の目で読んでいます');
        });
    });

    /* jumping to a chapter moves focus to its heading */
    function jump(id) {
        var target = document.getElementById(id);
        if (!target) return;
        var h = target.querySelector('h2') || target;
        target.scrollIntoView({ block: 'start', behavior: instant() ? 'auto' : 'smooth' });
        h.focus({ preventScroll: true });
    }
    $$('[data-sk-next]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); jump(a.getAttribute('href').slice(1)); }); });

    /* contents sheet: a modal dialog; Tab stays inside, Esc / close / outside click return focus to the opener */
    var tocBtn = $('[data-sk-toc]'), sheet = $('[data-sk-sheet]'), back = null;
    tocBtn.hidden = false;
    function focusables() { return $$('button', sheet); }
    function openSheet() {
        back = tocBtn;   // the only opener; WebKit does not focus a clicked button, so do not rely on activeElement
        var ch = app.getAttribute('data-ch');
        $$('[data-sk-go]', sheet).forEach(function (b) { b.classList.toggle('is-here', b.getAttribute('data-sk-go') === 'sk-ch' + ch); });
        sheet.hidden = false;
        ($('[data-sk-go].is-here', sheet) || focusables()[0]).focus();
    }
    function closeSheet(restore) { if (sheet.hidden) return; sheet.hidden = true; if (restore !== false && back && back.focus) back.focus({ preventScroll: true }); }
    tocBtn.addEventListener('click', openSheet);
    $('[data-sk-close]', sheet).addEventListener('click', function () { closeSheet(); });
    sheet.addEventListener('click', function (e) {
        if (e.target === sheet) { closeSheet(); return; }
        var go = e.target.closest('[data-sk-go]');
        if (go) { closeSheet(false); jump(go.getAttribute('data-sk-go')); }
    });
    sheet.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { e.preventDefault(); closeSheet(); return; }
        if (e.key !== 'Tab') return;
        var f = focusables(), i = f.indexOf(document.activeElement);
        e.preventDefault();
        f[(i + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus();
    });

    $('[data-sk-visit]').addEventListener('click', function () {
        $('[data-sk-visit-msg]').textContent = '架空の工房のため、見学の申し込みはできません（見本の表示です）。';
    });
})();
