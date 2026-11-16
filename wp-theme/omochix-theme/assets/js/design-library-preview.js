/* OmochiX Design Library detail page.
 *
 * Talks to the sandboxed demo iframe (D0 §3.4 T6/T7):
 *  - accepted from the demo: {type: "omx-design:ready"}, {type: "omx-design:error"} and {type: "omx-design:key",
 *    key: "Escape"} (closes the full-screen overlay) only, and only when
 *    event.source is the demo iframe's window (its origin is the opaque "null", so the origin alone proves nothing);
 *  - sent to the demo: {type: "omx-design:theme", theme} and {type: "omx-design:control", action: "pause" | "play"}
 *    — public values only, never a nonce or user data. Nothing received is written to the page.
 *
 * D2-A, all without touching the iframe's sandbox or CSP:
 *  - viewport switch: the iframe gets the real width (1280 / 834 / 390 CSS px, so the demo's own media queries
 *    apply) and the device height, then is scaled down with a transform to fit the stage — never stretched,
 *    never wider than the page. Switching does not reload the demo.
 *  - play / pause and restart (restart reloads the same demo URL; play / pause is a message).
 *  - full screen of the stage (Fullscreen API), or an in-page overlay when the API is missing or refused;
 *    Escape closes, focus and scroll position come back.
 *  - the demo is paused while the stage is off screen; with Save-Data it starts paused.
 * Without JavaScript the stage is the plain responsive iframe and the "open in a new tab" link. */
(function () {
    'use strict';
    /* Copy buttons: copy the text of an element already on the page (prompt, code). Hidden without JS / clipboard. */
    Array.prototype.forEach.call(document.querySelectorAll('[data-dl-copy]'), function (btn) {
        var target = document.getElementById(btn.getAttribute('data-dl-copy'));
        if (!target || !navigator.clipboard || !window.isSecureContext) return; // 127.0.0.1 and HTTPS are secure contexts
        var label = btn.textContent;
        btn.hidden = false;
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(target.textContent).then(function () { btn.textContent = 'コピーしました'; }, function () { btn.textContent = 'コピーできませんでした'; })
                .then(function () { window.setTimeout(function () { btn.textContent = label; }, 1800); });
        });
    });
})();
(function () {
    'use strict';
    var stage = document.querySelector('[data-dl-stage]');
    var frame = stage && stage.querySelector('[data-dl-demo]');
    if (!frame) return;
    var status = stage.querySelector('[data-dl-status]');
    var view = stage.querySelector('[data-dl-view]');
    var canvas = stage.querySelector('[data-dl-canvas]');
    var controls = stage.querySelector('[data-dl-controls]');
    var live = stage.querySelector('[data-dl-live]');
    var group = stage.querySelector('[data-dl-viewports]');
    var radios = group ? Array.prototype.slice.call(group.querySelectorAll('[role="radio"]')) : [];
    var toggle = stage.querySelector('[data-dl-toggle]');
    var toggleLabel = stage.querySelector('[data-dl-toggle-label]');
    var restart = stage.querySelector('[data-dl-restart]');
    var fsBtn = stage.querySelector('[data-dl-fullscreen]');
    var fsLabel = stage.querySelector('[data-dl-fullscreen-label]');
    var TEXT = {
        ready: 'ライブデモはサイト本体から切り離した環境で動いています。',
        error: 'ライブデモでエラーが起きました。「新しいタブで開く」でも表示できます。'
    };
    var conn = navigator.connection;
    var saveData = !!(conn && conn.saveData);
    var userPaused = saveData;   // the visitor's choice (Save-Data starts paused)
    var offscreen = false;       // the stage is out of view
    var ready = false;
    var lastSent = null;

    function say(text) { if (live) { live.textContent = ''; window.setTimeout(function () { live.textContent = text; }, 30); } }
    function post(msg) { if (frame.contentWindow) frame.contentWindow.postMessage(msg, '*'); }
    function theme() { return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }
    function sync(force) {
        if (!ready) return;
        var action = (userPaused || offscreen) ? 'pause' : 'play';
        if (force || action !== lastSent) { lastSent = action; post({ type: 'omx-design:control', action: action }); }
        stage.setAttribute('data-motion', action === 'pause' ? 'paused' : 'playing');
    }
    function paint() {
        if (!toggle) return;
        toggle.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
        toggleLabel.textContent = userPaused ? '再生' : '一時停止';
    }

    /* ---------------- messages from the demo ---------------- */
    window.addEventListener('message', function (event) {
        if (event.source !== frame.contentWindow || event.origin !== 'null') return;
        var d = event.data;
        if (!d || typeof d !== 'object' || Array.isArray(d)) return;
        if (d.type === 'omx-design:ready') {
            if (stage.getAttribute('data-state') === 'error') return;
            stage.setAttribute('data-state', 'ready');
            if (status) status.textContent = TEXT.ready + (saveData && userPaused ? '（データセーバーが有効なため、一時停止しています）' : '');
            ready = true;
            post({ type: 'omx-design:theme', theme: theme() });
            sync(true);
        } else if (d.type === 'omx-design:error') {
            stage.setAttribute('data-state', 'error');
            if (status) status.textContent = TEXT.error;
        } else if (d.type === 'omx-design:key' && d.key === 'Escape' && stage.getAttribute('data-full') === 'overlay') {
            leave(false); // focus was inside the demo, where the page's own keydown listener cannot hear Escape
        }
    });
    if ('MutationObserver' in window) {
        new MutationObserver(function () { if (ready) post({ type: 'omx-design:theme', theme: theme() }); }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    }

    /* ---------------- viewport switch ---------------- */
    var vp = null;
    function fit() {
        if (!vp || !canvas) return;
        var full = stage.classList.contains('is-full');
        var cs = window.getComputedStyle(view);
        var availW = view.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
        // Inline the whole device stays visible (82% of the window height); full screen uses the stage's height.
        var availH = full ? view.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom) : Math.max(240, window.innerHeight * 0.82);
        var s = Math.min(1, availW / vp.w, availH / vp.h);
        s = Math.max(0.1, Math.floor(s * 1000) / 1000);
        frame.style.width = vp.w + 'px';
        frame.style.height = vp.h + 'px';
        frame.style.transform = 'scale(' + s + ')';
        canvas.style.width = Math.round(vp.w * s) + 'px';
        canvas.style.height = Math.round(vp.h * s) + 'px';
        view.style.height = full ? '' : Math.round(vp.h * s) + 'px';
        stage.setAttribute('data-scale', String(s));
    }
    function choose(btn, focus) {
        radios.forEach(function (r) { var on = r === btn; r.setAttribute('aria-checked', on ? 'true' : 'false'); r.tabIndex = on ? 0 : -1; });
        vp = { name: btn.getAttribute('data-vp'), w: Number(btn.getAttribute('data-w')), h: Number(btn.getAttribute('data-h')) };
        stage.setAttribute('data-viewport', vp.name);
        fit();
        if (focus) btn.focus();
    }
    if (radios.length && canvas) {
        stage.classList.add('is-sized');
        group.hidden = false;
        radios.forEach(function (r, i) {
            r.addEventListener('click', function () { choose(r, false); say(r.textContent.replace(/\s+/g, ' ').trim() + 'で表示'); });
            r.addEventListener('keydown', function (e) {
                var n = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
                var j = e.key === 'Home' ? 0 : e.key === 'End' ? radios.length - 1 : n ? (i + n + radios.length) % radios.length : -1;
                if (j < 0) return;
                e.preventDefault();
                choose(radios[j], true);
                say(radios[j].textContent.replace(/\s+/g, ' ').trim() + 'で表示');
            });
        });
        choose(radios.filter(function (r) { return r.getAttribute('aria-checked') === 'true'; })[0] || radios[0], false);
        var t = 0;
        window.addEventListener('resize', function () { window.clearTimeout(t); t = window.setTimeout(fit, 60); });
    }

    /* ---------------- play / pause / restart ---------------- */
    if (controls) controls.hidden = false;
    paint();
    if (toggle) toggle.addEventListener('click', function () {
        userPaused = !userPaused;
        paint(); sync(false);
        say(userPaused ? '一時停止しました' : '再生しています');
    });
    if (restart) restart.addEventListener('click', function () {
        userPaused = false; ready = false; lastSent = null;
        paint();
        stage.setAttribute('data-state', 'loading');
        frame.src = frame.src; // reload the same sandboxed demo: same URL, same sandbox, same CSP
        say('最初から再生します');
    });

    /* ---------------- off screen: pause ---------------- */
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
            var e = entries[entries.length - 1];
            offscreen = !stage.classList.contains('is-full') && !(e.isIntersecting && e.intersectionRatio > 0.05);
            sync(false);
        }, { threshold: [0, 0.05, 0.25] }).observe(stage);
    }

    /* ---------------- full screen ---------------- */
    var restore = null;
    function fsElement() { return document.fullscreenElement || document.webkitFullscreenElement || null; }
    function focusables() {
        return Array.prototype.filter.call(stage.querySelectorAll('button, a[href], iframe, [tabindex]:not([tabindex="-1"])'), function (el) { return !el.hidden && el.offsetParent !== null; });
    }
    function enter() {
        if (stage.classList.contains('is-full')) return;
        restore = { x: window.scrollX, y: window.scrollY, focus: document.activeElement };
        var req = stage.requestFullscreen || stage.webkitRequestFullscreen;
        var done = function (mode) {
            if (stage.classList.contains('is-full')) return;
            stage.classList.add('is-full');
            stage.setAttribute('data-full', mode);
            if (mode === 'overlay') { document.documentElement.classList.add('dl-noscroll'); stage.setAttribute('role', 'dialog'); stage.setAttribute('aria-modal', 'true'); }
            fsBtn.setAttribute('aria-pressed', 'true');
            fsLabel.textContent = '全画面を閉じる';
            offscreen = false; sync(false);
            fit();
            fsBtn.focus({ preventScroll: true });
            say(mode === 'overlay' ? '全画面で表示しています。Escape キーで閉じます' : '全画面で表示しています');
        };
        if (!req) { done('overlay'); return; }
        try {
            var p = req.call(stage);
            if (p && typeof p.then === 'function') p.then(function () { done('api'); }, function () { done('overlay'); });
            else window.setTimeout(function () { done(fsElement() === stage ? 'api' : 'overlay'); }, 50);
        } catch (e) { done('overlay'); }
    }
    function leave(fromApi) {
        if (!stage.classList.contains('is-full')) return;
        if (stage.getAttribute('data-full') === 'api' && !fromApi && fsElement()) {
            (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            return; // fullscreenchange finishes the job
        }
        stage.classList.remove('is-full');
        stage.removeAttribute('data-full');
        stage.removeAttribute('role'); stage.removeAttribute('aria-modal');
        document.documentElement.classList.remove('dl-noscroll');
        fsBtn.setAttribute('aria-pressed', 'false');
        fsLabel.textContent = '全画面';
        fit();
        if (restore) {
            var r = restore; restore = null;
            window.scrollTo(r.x, r.y);
            (r.focus && document.contains(r.focus) && r.focus !== document.body ? r.focus : fsBtn).focus({ preventScroll: true });
            window.requestAnimationFrame(function () { window.scrollTo(r.x, r.y); });
        }
        say('全画面を閉じました');
    }
    if (fsBtn) {
        fsBtn.addEventListener('click', function () { if (stage.classList.contains('is-full')) leave(false); else enter(); });
        ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (ev) {
            document.addEventListener(ev, function () { if (!fsElement() && stage.getAttribute('data-full') === 'api') leave(true); else fit(); });
        });
        document.addEventListener('keydown', function (e) {
            if (!stage.classList.contains('is-full')) return;
            if (e.key === 'Escape') { e.preventDefault(); leave(false); return; }
            if (e.key === 'Tab' && stage.getAttribute('data-full') === 'overlay') {
                // Move focus ourselves: browsers differ on which controls Tab visits (WebKit skips buttons).
                var list = focusables();
                if (!list.length) return;
                e.preventDefault();
                var i = list.indexOf(document.activeElement);
                list[(i + (e.shiftKey ? -1 : 1) + list.length) % list.length].focus();
            }
        });
        // Focus never leaves the overlay (e.g. tabbing out of the demo document).
        document.addEventListener('focusin', function (e) {
            if (stage.getAttribute('data-full') === 'overlay' && !stage.contains(e.target)) fsBtn.focus({ preventScroll: true });
        });
    }
})();
