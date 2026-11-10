/**
 * OmochiX Labs Web Worker: runs JSON parsing, diffs and token estimates off
 * the main thread so a large input cannot freeze the page. The page sends
 * {init: [script URLs]} once (same-origin theme files only), then
 * {id, op, payload}; the worker answers {id, ok, result} or {id, ok: false}.
 * The page terminates the worker if an answer takes longer than the limit.
 */
/* eslint-env worker */
'use strict';

var ready = false;

self.onmessage = function (event) {
    var data = event.data || {};
    if (data.init) {
        if (ready) return;
        var origin = self.location.origin;
        var scripts = (Array.isArray(data.init) ? data.init : []).filter(function (url) {
            return typeof url === 'string' && url.indexOf(origin + '/') === 0 && /\/assets\/js\/labs\/core\/[a-z]+\.js(\?ver=[\w.-]+)?$/.test(url);
        });
        try {
            importScripts.apply(self, scripts);
            ready = !!self.OmochixLabsOps;
        } catch (e) {
            ready = false;
        }
        self.postMessage({ init: true, ok: ready });
        return;
    }
    if (!ready) {
        self.postMessage({ id: data.id, ok: false, error: 'not_ready' });
        return;
    }
    try {
        self.postMessage({ id: data.id, ok: true, result: self.OmochixLabsOps.run(data.op, data.payload) });
    } catch (e) {
        self.postMessage({ id: data.id, ok: false, error: 'internal' });
    }
};
