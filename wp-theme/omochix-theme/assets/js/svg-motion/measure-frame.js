/*!
 * SVG Motion Studio: the measuring frame's script (loaded by assets/svg-motion/measure-frame.html).
 *
 * Runs only inside <iframe sandbox="allow-scripts" src="measure-frame.html">: an opaque origin with no access to
 * the studio page, its cookies or its storage, under the frame's own CSP (default-src 'none'; script-src 'self').
 * It is not srcdoc, so the page's CSP does not apply here and no inline script is needed (CSP design, 2026-11-09).
 *
 * Protocol (v 1), one measurement per frame:
 *   frame  -> parent  {type: 'omx:ready', v: 1}
 *   parent -> frame   {type: 'omx:measure', v: 1, id: <32 hex>, svg: <verified prepare() output>}
 *   frame  -> parent  {type: 'omx:measure', v: 1, id, box: {x, y, width, height} | null, attempts: <0..10>}
 * Messages are accepted from the parent window only, once, and only in this shape. Replies carry numbers only.
 * The target origin is '*' because a sandboxed frame's origin is opaque and the parent's cannot be named from here;
 * the parent checks event.source, the id, the shape and the numbers.
 *
 * Firefox can answer getBBox() with 0,0,0,0 when it runs before the SVG is laid out. A box whose width and height
 * are both 0 is measured again with setTimeout (requestAnimationFrame does not run in a hidden frame in Chromium and
 * WebKit): every 16 ms, at most 10 more times and within 500 ms. Still empty, the answer is null and the studio uses
 * the centre of the viewBox. Forcing a reflow is not relied on (it did not help in Firefox).
 */
(function () {
    'use strict';

    var parentWindow = window.parent;
    var SVG_NS = 'http://www.w3.org/2000/svg';
    var MAX_CHARS = 2097152;
    var RETRY_DELAY = 16;
    var RETRY_LIMIT = /(?:^|[?&])retry=0(?:&|$)/.test(window.location.search) ? 0 : 10; // retry=0: tests only (the Firefox control)
    var RETRY_BUDGET = 500;
    var ID = /^[0-9a-f]{32}$/;
    var used = false;

    function reply(id, box, attempts) {
        parentWindow.postMessage({ type: 'omx:measure', v: 1, id: id, box: box, attempts: attempts }, '*');
    }

    function read(element) {
        var b = element.getBBox();
        return { x: b.x, y: b.y, width: b.width, height: b.height };
    }

    function measure(id, text) {
        var doc = new DOMParser().parseFromString(text, 'image/svg+xml');
        var source = doc.documentElement;
        if (doc.getElementsByTagName('parsererror').length || !source || source.namespaceURI !== SVG_NS || source.localName !== 'svg') {
            reply(id, null, 0);
            return;
        }
        var svg = document.importNode(source, true);
        document.body.appendChild(svg);
        var element = svg.querySelector('[data-omx-root]') || svg;
        var started = Date.now();
        var attempts = 0;
        (function attempt() {
            var box;
            try { box = read(element); } catch (error) { reply(id, null, attempts); return; }
            if (box.width === 0 && box.height === 0) {
                if (attempts < RETRY_LIMIT && Date.now() - started + RETRY_DELAY <= RETRY_BUDGET) {
                    attempts++;
                    window.setTimeout(attempt, RETRY_DELAY);
                    return;
                }
                reply(id, null, attempts);
                return;
            }
            reply(id, box, attempts);
        }());
    }

    window.addEventListener('message', function (event) {
        if (used || event.source !== parentWindow) return;
        var data = event.data;
        if (!data || typeof data !== 'object' || data.type !== 'omx:measure' || data.v !== 1) return;
        if (typeof data.id !== 'string' || !ID.test(data.id) || typeof data.svg !== 'string' || !data.svg || data.svg.length > MAX_CHARS) return;
        used = true;
        try { measure(data.id, data.svg); } catch (error) { reply(data.id, null, 0); }
    });

    parentWindow.postMessage({ type: 'omx:ready', v: 1 }, '*');
}());
