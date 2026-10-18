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

    window.omx = {
        animate: function (fn) {
            if (typeof fn !== 'function') return;
            callbacks.push(fn);
            try { fn(0); } catch (error) { /* draw the first frame even if the sample throws later */ }
            if (playing && !frame) frame = window.requestAnimationFrame(tick);
        }
    };

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data !== 'object') return;
        if (data.type === 'omx:play') setPlaying(true);
        else if (data.type === 'omx:pause') setPlaying(false);
        else if (data.type === 'omx:theme') root.setAttribute('data-theme', data.theme === 'dark' ? 'dark' : 'light');
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
