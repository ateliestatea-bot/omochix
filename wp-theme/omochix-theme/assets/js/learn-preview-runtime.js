/**
 * Preview runtime, inlined into every sandboxed preview document by
 * inc/learn-gallery.php (sandbox="allow-scripts", no same-origin access).
 *
 * - Fits the fixed-size stage into the frame with scale().
 * - Exposes `omx.animate(fn)` for JavaScript samples: fn(t) receives the
 *   elapsed playing time in seconds; the loop stops while paused.
 * - Listens for parent messages: omx:play, omx:pause, omx:theme.
 * - Starts paused when the viewer prefers reduced motion; the parent then
 *   shows a play button, so motion only runs on the viewer's own request.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var stage = document.querySelector('.omx-stage');
    var stageW = parseInt(root.getAttribute('data-stage-w'), 10) || 320;
    var stageH = parseInt(root.getAttribute('data-stage-h'), 10) || 180;
    var callbacks = [];
    var playing = true;
    var startedAt = performance.now();
    var pausedAt = 0;
    var pausedTotal = 0;
    var frame = 0;

    function fit() {
        if (!stage) return;
        var scale = Math.min(window.innerWidth / stageW, window.innerHeight / stageH) || 1;
        stage.style.setProperty('--omx-w', stageW + 'px');
        stage.style.setProperty('--omx-h', stageH + 'px');
        stage.style.setProperty('--omx-scale', String(scale));
    }

    function tick(now) {
        frame = 0;
        if (!playing) return;
        var t = (now - startedAt - pausedTotal) / 1000;
        for (var i = 0; i < callbacks.length; i++) {
            try { callbacks[i](t); } catch (error) { /* a broken sample must not stop the others */ }
        }
        frame = window.requestAnimationFrame(tick);
    }

    function setPlaying(next) {
        next = !!next;
        if (next === playing) return;
        playing = next;
        if (playing) {
            pausedTotal += performance.now() - pausedAt;
            root.removeAttribute('data-paused');
            if (!frame && callbacks.length) frame = window.requestAnimationFrame(tick);
        } else {
            pausedAt = performance.now();
            root.setAttribute('data-paused', '');
            if (frame) { window.cancelAnimationFrame(frame); frame = 0; }
        }
    }

    /*
     * Playground parameters. The parent sends already-sanitized values, but
     * the frame re-checks every name and value against the same fixed
     * grammar before writing them: CSS custom properties go into the
     * #omx-params style element (the same block the copied code carries),
     * JS values into window.omxParams for samples that read omx.param().
     */
    var paramListeners = [];
    var CSS_VAR_RE = /^--p-[a-z0-9-]{1,30}$/;
    var CSS_VAL_RE = /^[a-z0-9 .,()%#-]{1,80}$/i;
    var JS_KEY_RE = /^[a-z][a-z0-9_]{0,23}$/;
    var JS_STR_RE = /^[#a-z0-9_-]{1,24}$/i;

    function applyParams(data) {
        var cssVars = data.cssVars && typeof data.cssVars === 'object' ? data.cssVars : {};
        var jsValues = data.js && typeof data.js === 'object' ? data.js : {};
        var css = [];
        Object.keys(cssVars).forEach(function (name) {
            var value = String(cssVars[name]);
            if (CSS_VAR_RE.test(name) && CSS_VAL_RE.test(value) && !/url\s*\(/i.test(value)) css.push(name + ':' + value);
        });
        var style = document.getElementById('omx-params');
        if (style) style.textContent = css.length ? ':root{' + css.join(';') + '}' : '';
        var clean = {};
        var any = false;
        Object.keys(jsValues).forEach(function (key) {
            var value = jsValues[key];
            if (!JS_KEY_RE.test(key)) return;
            if (typeof value === 'number' && isFinite(value)) { clean[key] = value; any = true; }
            else if (typeof value === 'string' && JS_STR_RE.test(value)) { clean[key] = value; any = true; }
        });
        if (any || !window.omxParams) window.omxParams = clean;
        for (var i = 0; i < paramListeners.length; i++) {
            try { paramListeners[i](window.omxParams); } catch (error) { /* keep other listeners alive */ }
        }
    }

    window.omx = {
        animate: function (fn) {
            if (typeof fn !== 'function') return;
            callbacks.push(fn);
            try { fn(0); } catch (error) { /* draw the first frame even if the sample throws later */ }
            if (playing && !frame) frame = window.requestAnimationFrame(tick);
        },
        param: function (key, fallback) {
            var params = window.omxParams || {};
            return Object.prototype.hasOwnProperty.call(params, key) ? params[key] : fallback;
        },
        onParams: function (fn) {
            if (typeof fn === 'function') paramListeners.push(fn);
        }
    };

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data !== 'object') return;
        if (data.type === 'omx:play') setPlaying(true);
        else if (data.type === 'omx:pause') setPlaying(false);
        else if (data.type === 'omx:theme') root.setAttribute('data-theme', data.theme === 'dark' ? 'dark' : 'light');
        else if (data.type === 'omx:params') applyParams(data);
    });
    window.addEventListener('resize', fit);

    fit();
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        // Stay on the first frame until the viewer asks for motion.
        playing = true;
        setPlaying(false);
    }
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'omx:ready', playing: playing }, '*');
    }
}());
