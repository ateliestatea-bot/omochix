/* OmochiX Design Library demo runtime (inlined into every demo document, allowed by its SHA-256).
 * Runs inside the opaque-origin sandbox. Messages (D0 §3.4 T6/T7):
 *  - to the parent: only {type: "omx-design:ready" | "omx-design:error"} with fixed fields, sent to the site origin;
 *  - from the parent: only {type: "omx-design:theme", theme: "light" | "dark"} from window.parent at the site origin.
 * Everything else is ignored. Values are never written as HTML. */
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
    var state = { reduced: !!(mq && mq.matches), hidden: document.hidden === true };
    var api = {
        isReduced: function () { return state.reduced; },
        isHidden: function () { return state.hidden; },
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
    document.addEventListener('visibilitychange', function () { state.hidden = document.hidden === true; emit('visibility', state.hidden); });

    window.addEventListener('message', function (event) {
        if (event.source !== window.parent || window.parent === window || event.origin !== SITE) return;
        var d = event.data;
        if (!d || typeof d !== 'object' || Array.isArray(d) || d.type !== 'omx-design:theme') return;
        if (d.theme !== 'light' && d.theme !== 'dark') return;
        root.setAttribute('data-theme', d.theme);
        emit('theme', d.theme);
    });
    window.addEventListener('error', function () { report('runtime'); });

    window.omxDemo = Object.freeze(api);
    function ready() {
        if (window.parent === window) return;
        try { window.parent.postMessage({ type: 'omx-design:ready' }, SITE); } catch (e) { /* parent gone */ }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
})();
