/* OmochiX Design Library: card loop playback and in-place filtering (list / top pages).
 *
 * Playback (D0 §2.1):
 *  - at most MAX cards play at once (hard cap 2), only while at least half on screen and the tab is visible;
 *  - no autoplay with prefers-reduced-motion, Save-Data / 2G, or deviceMemory < 4: the poster stays,
 *    and a card plays only after its play button is pressed;
 *  - the video sources are attached on first play (preload="none" before), a failed load keeps the poster;
 *  - the media box has a fixed aspect ratio, so starting a video never shifts the layout.
 * Without this script the cards are posters with links, and the filter form submits normally. */
(function () {
    'use strict';
    var cfg = window.omochixDesignLibrary || {};
    var strings = cfg.strings || {};
    var MAX = Math.max(0, Math.min(2, parseInt(cfg.maxPlaying, 10) || 2));
    var root = document.querySelector('[data-dl-root]');
    if (!root) return;
    var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };

    function lowPower() {
        var c = navigator.connection;
        if (c && (c.saveData || /(^|-)2g$/.test(String(c.effectiveType || '')))) return true;
        return typeof navigator.deviceMemory === 'number' && navigator.deviceMemory < 4;
    }
    function autoAllowed() { return !reduce.matches && !lowPower(); }

    var cards = Array.prototype.slice.call(root.querySelectorAll('[data-dl-card]')).map(function (el, idx) {
        return { el: el, idx: idx, video: el.querySelector('[data-dl-video]'), btn: el.querySelector('[data-dl-play]'), visible: false, onScreen: false, ratio: 0, user: null, failed: false, loaded: false };
    }).filter(function (c) { return c.video; });

    function setPressed(c, on) {
        c.el.classList.toggle('is-playing', on);
        if (!c.btn) return;
        c.btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        var label = c.btn.querySelector('.sr-only');
        var title = c.el.querySelector('.dl-card__title');
        if (label && title) label.textContent = (on ? (strings.pause || 'Pause') : (strings.play || 'Play')) + '：' + title.textContent.trim();
    }
    function fail(c) {
        c.failed = true;
        if (!c.video.paused) c.video.pause();
        setPressed(c, false);
        if (c.btn) c.btn.hidden = true;
        update();
    }
    function load(c) {
        if (c.loaded) return;
        c.loaded = true;
        var srcs = [['data-webm', 'video/webm'], ['data-mp4', 'video/mp4']].filter(function (s) { return c.video.getAttribute(s[0]); });
        srcs.forEach(function (s, i) {
            var el = document.createElement('source');
            el.src = c.video.getAttribute(s[0]);
            el.type = s[1];
            if (i === srcs.length - 1) el.addEventListener('error', function () { fail(c); });
            c.video.appendChild(el);
        });
        c.video.addEventListener('error', function () { fail(c); });
        c.video.load();
    }
    // Autoplay needs half the card on screen; a play the visitor asked for only needs the card on screen at all.
    function wants(c) {
        if (c.failed || document.hidden || c.el.hidden || c.user === false) return false;
        return c.user === true ? c.onScreen : (c.visible && autoAllowed());
    }
    function start(c) {
        load(c);
        if (!c.video.paused) return;
        var p = c.video.play();
        if (p && typeof p.catch === 'function') p.catch(function () { setPressed(c, false); });
    }
    function update() {
        var chosen = cards.filter(wants).sort(function (a, b) {
            return ((b.user === true) - (a.user === true)) || (b.ratio - a.ratio) || (a.idx - b.idx);
        }).slice(0, MAX);
        cards.forEach(function (c) {
            if (chosen.indexOf(c) === -1) { if (!c.video.paused) c.video.pause(); }
        });
        chosen.forEach(start);
    }

    cards.forEach(function (c) {
        c.video.addEventListener('playing', function () { setPressed(c, true); });
        c.video.addEventListener('pause', function () { setPressed(c, false); });
        if (c.btn) {
            c.btn.hidden = false;
            c.btn.addEventListener('click', function () {
                var playing = !c.video.paused;
                c.user = !playing;
                if (c.user) cards.forEach(function (o) { if (o !== c && o.user === true) o.user = null; });
                update();
            });
        }
    });

    if (!('IntersectionObserver' in window)) {
        // No visibility information: never autoplay, but let the play button work.
        cards.forEach(function (c) { c.visible = c.onScreen = true; c.ratio = 1; c.user = false; }); // treated as paused until pressed
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                var c = cards.filter(function (x) { return x.el === en.target; })[0];
                if (!c) return;
                c.ratio = en.intersectionRatio;
                c.onScreen = en.isIntersecting && en.intersectionRatio > 0;
                c.visible = c.onScreen && en.intersectionRatio >= 0.5;
            });
            update();
        }, { threshold: [0, 0.25, 0.5, 0.75, 1] });
        cards.forEach(function (c) { io.observe(c.el); });
    }
    document.addEventListener('visibilitychange', update);
    if (reduce.addEventListener) reduce.addEventListener('change', update);
    if (navigator.connection && navigator.connection.addEventListener) navigator.connection.addEventListener('change', update);

    /* ---------------- filters ---------------- */
    var form = root.querySelector('[data-dl-filters]');
    if (!form) return;
    root.classList.add('is-enhanced');
    var all = Array.prototype.slice.call(root.querySelectorAll('[data-dl-card]'));
    var sections = Array.prototype.slice.call(root.querySelectorAll('[data-dl-section]'));
    var KEYS = [['medium', 'data-medium'], ['use_case', 'data-use-case'], ['motion_type', 'data-motion-type'], ['technology', 'data-technology'], ['industry', 'data-industry']];
    var count = root.querySelector('[data-dl-count]');
    var empty = root.querySelector('[data-dl-empty]');
    var timer = 0;

    function values() {
        var v = {};
        ['q'].concat(KEYS.map(function (k) { return k[0]; })).forEach(function (k) {
            var f = form.elements[k];
            v[k] = f ? String(f.value || '').trim() : '';
        });
        v.q = v.q.replace(/\s+/g, ' ').slice(0, 60);
        return v;
    }
    function matches(el, v) {
        function has(attr, val) { return !val || (' ' + (el.getAttribute(attr) || '') + ' ').indexOf(' ' + val + ' ') !== -1; }
        for (var i = 0; i < KEYS.length; i++) { if (!has(KEYS[i][1], v[KEYS[i][0]])) return false; }
        if (!v.q) return true;
        var hay = el.getAttribute('data-search') || '';
        return v.q.toLowerCase().split(' ').every(function (w) { return !w || hay.indexOf(w) !== -1; });
    }
    function apply() {
        var v = values(), n = 0;
        all.forEach(function (el) { var ok = matches(el, v); el.hidden = !ok; if (ok) n++; });
        sections.forEach(function (sec) { sec.hidden = !sec.querySelector('[data-dl-card]:not([hidden])'); });
        if (count) count.textContent = (strings.count || '%s').replace('%s', n.toLocaleString('ja-JP'));
        if (empty) empty.hidden = n !== 0;
        var params = new URLSearchParams();
        Object.keys(v).forEach(function (k) { if (v[k]) params.set(k, v[k]); });
        var qs = params.toString();
        if (window.history && history.replaceState) history.replaceState(null, '', form.getAttribute('action') + (qs ? '?' + qs : ''));
        update();
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); apply(); });
    form.addEventListener('change', apply);
    form.addEventListener('input', function (e) {
        if (e.target && e.target.name === 'q') { window.clearTimeout(timer); timer = window.setTimeout(apply, 150); }
    });
    var reset = form.querySelector('[data-dl-reset]');
    if (reset) reset.addEventListener('click', function (e) {
        e.preventDefault();
        Array.prototype.forEach.call(form.elements, function (f) { if (f.name) f.value = ''; });
        apply();
        var q = form.elements.q; if (q) q.focus();
    });
})();
