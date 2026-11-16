/* OmochiX Design Library demo runtime (inlined into every demo document, allowed by its SHA-256).
 * Runs inside the opaque-origin sandbox. Messages (D0 §3.4 T6/T7):
 *  - to the parent: only {type: "omx-design:ready" | "omx-design:error"} with fixed fields, and
 *    {type: "omx-design:key", key: "Escape"} (so a full-screen overlay can close while focus is in the demo),
 *    sent to the site origin;
 *  - from the parent (window.parent at the site origin only):
 *      {type: "omx-design:theme", theme: "light" | "dark"}
 *      {type: "omx-design:control", action: "pause" | "play"}   (D2-A: pause / resume the demo's motion)
 * Everything else is ignored. Values are never written as HTML.
 * A paused demo behaves like a hidden one: isHidden() is true, "visibility" listeners stop their frame loops,
 * and CSS animations are held with animation-play-state. */
(function () {
    'use strict';
    var SITE = "__OMX_SITE_ORIGIN__";
    var root = document.documentElement;
    var mq = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var listeners = { motion: [], visibility: [], theme: [] };
    root.classList.add('js');

    function emit(kind, value) {
        listeners[kind].forEach(function (cb) { try { cb(value); } catch (e) { report('listener'); } });
    }
    function report(code) {
        if (window.parent === window) return;
        try { window.parent.postMessage({ type: 'omx-design:error', code: String(code).slice(0, 24) }, SITE); } catch (e) { /* parent gone */ }
    }
    var state = { reduced: !!(mq && mq.matches), hidden: document.hidden === true, paused: false };
    function stopped() { return state.hidden || state.paused; }
    var hold = null;
    function applyPause() {
        root.classList.toggle('omx-paused', state.paused);
        if (state.paused && !hold) {
            hold = document.createElement('style');
            hold.textContent = 'html.omx-paused *,html.omx-paused *::before,html.omx-paused *::after{animation-play-state:paused!important}';
            document.head.appendChild(hold);
        }
    }
    var api = {
        isReduced: function () { return state.reduced; },
        isHidden: stopped,
        isPaused: function () { return state.paused; },
        onMotion: function (cb) { listeners.motion.push(cb); },
        onVisibility: function (cb) { listeners.visibility.push(cb); },
        onTheme: function (cb) { listeners.theme.push(cb); },
        report: report
    };
    if (mq) {
        var onChange = function () { state.reduced = mq.matches; root.classList.toggle('reduced', state.reduced); emit('motion', state.reduced); };
        if (mq.addEventListener) mq.addEventListener('change', onChange); else if (mq.addListener) mq.addListener(onChange);
    }
    root.classList.toggle('reduced', state.reduced);
    document.addEventListener('visibilitychange', function () {
        var before = stopped();
        state.hidden = document.hidden === true;
        if (stopped() !== before) emit('visibility', stopped());
    });

    window.addEventListener('message', function (event) {
        if (event.source !== window.parent || window.parent === window || event.origin !== SITE) return;
        var d = event.data;
        if (!d || typeof d !== 'object' || Array.isArray(d)) return;
        if (d.type === 'omx-design:theme') {
            if (d.theme !== 'light' && d.theme !== 'dark') return;
            root.setAttribute('data-theme', d.theme);
            emit('theme', d.theme);
        } else if (d.type === 'omx-design:control') {
            if (d.action !== 'pause' && d.action !== 'play') return;
            var before = stopped();
            state.paused = d.action === 'pause';
            applyPause();
            if (stopped() !== before) emit('visibility', stopped());
        }
    });
    window.addEventListener('error', function () { report('runtime'); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || window.parent === window) return;
        try { window.parent.postMessage({ type: 'omx-design:key', key: 'Escape' }, SITE); } catch (err) { /* parent gone */ }
    });

    window.omxDemo = Object.freeze(api);
    function ready() {
        if (window.parent === window) return;
        try { window.parent.postMessage({ type: 'omx-design:ready' }, SITE); } catch (e) { /* parent gone */ }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
})();
