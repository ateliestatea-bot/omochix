/**
 * OmochiX Labs: page helpers shared by every tool UI (assets/js/labs/ui/*).
 *
 * - status / error regions (aria-live), busy state
 * - copy, download (Blob URL, nothing leaves the browser), size limits
 * - run(op, payload): the heavy work in a Web Worker with a time limit, or on
 *   the main thread for small inputs; both use core/ops.js
 * - analytics: the six labs_* events through Site Kit's gtag, sanitized by
 *   core/events.js (never any input text)
 *
 * Text from the visitor is only ever written with textContent / value.
 */
(function (window, document) {
    'use strict';

    var config = window.omochixLabs || {};
    var strings = config.strings || {};
    var Text = window.OmochixLabsText;
    var Events = window.OmochixLabsEvents;
    var tools = {};

    function $(selector, root) { return (root || document).querySelector(selector); }
    function $all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

    function format(template, value) { return String(template || '').replace('%s', value); }

    /* ------------------------------------------------------------ analytics */

    var lastRun = {};

    function track(name, params) {
        var p = params || {};
        if (!p.tool_slug) p.tool_slug = config.tool || 'index';
        if (name === 'labs_tool_view' && config.category) p.tool_category = config.category;
        return Events.send(name, p, window.gtag);
    }

    /** labs_tool_run; `throttle` limits automatic (as-you-type) runs to one per 30 seconds. */
    function trackRun(action, bytes, throttle) {
        var now = Date.now();
        if (throttle && lastRun[action] && now - lastRun[action] < 30000) return;
        lastRun[action] = now;
        track('labs_tool_run', { action: action, size_bucket: Text.sizeBucket(bytes || 0) });
    }

    /* ---------------------------------------------------- status and errors */

    function ui(root) {
        return {
            root: root,
            status: $('[data-labs-status]', root),
            error: $('[data-labs-error]', root)
        };
    }

    function announce(root, message) {
        var el = $('[data-labs-status]', root);
        if (!el) return;
        el.textContent = '';
        // A fresh text node after a tick so screen readers announce repeated messages.
        window.setTimeout(function () { el.textContent = message; }, 30);
    }

    function showError(root, message, detail) {
        var el = $('[data-labs-error]', root);
        if (!el) return;
        el.textContent = '';
        var p = document.createElement('p');
        p.className = 'labs-error__message';
        p.textContent = message;
        el.appendChild(p);
        if (detail) {
            var pre = document.createElement('pre');
            pre.className = 'labs-error__detail';
            pre.textContent = detail;
            el.appendChild(pre);
        }
        el.hidden = false;
    }

    function clearError(root) {
        var el = $('[data-labs-error]', root);
        if (el) { el.hidden = true; el.textContent = ''; }
    }

    function setBusy(root, busy) {
        root.setAttribute('aria-busy', busy ? 'true' : 'false');
        $all('[data-labs-run]', root).forEach(function (button) { button.disabled = !!busy; });
        if (busy) announce(root, strings.working || '');
    }

    /* ------------------------------------------------------------- limits */

    /** @return {?string} An error message when `text` is over `limit` bytes. */
    function overLimit(text, limit) {
        if (!limit) return null;
        var bytes = Text.byteLength(text);
        return bytes > limit ? format(strings.tooLarge, Text.formatBytes(limit)) : null;
    }

    function limits(slug) { return (config.limits || {})[slug || config.tool] || {}; }

    /* ------------------------------------------------------ copy / download */

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return fallbackCopy(text); });
        }
        return Promise.resolve(fallbackCopy(text));
    }

    function fallbackCopy(text) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        document.body.appendChild(area);
        area.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        document.body.removeChild(area);
        return ok;
    }

    /** Copy and announce; reports labs_tool_copy only when the copy succeeded. */
    function copy(root, text, target) {
        if (!text) return Promise.resolve(false);
        return copyText(text).then(function (ok) {
            announce(root, ok ? strings.copied : strings.copyFailed);
            if (ok) track('labs_tool_copy', { target: target || 'output' });
            return ok;
        });
    }

    function download(text, filename, type) {
        var blob = new Blob([text], { type: (type || 'text/plain') + ';charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    /* ------------------------------------------------------------ worker */

    var worker = null;
    var workerReady = null;
    var pending = {};
    var seq = 0;

    function startWorker() {
        if (workerReady) return workerReady;
        var w = config.worker;
        if (!w || !w.url || typeof window.Worker !== 'function') return (workerReady = Promise.resolve(false));
        workerReady = new Promise(function (resolve) {
            var settled = false;
            // A worker that cannot start (or never answers) is dropped; run() then uses the main thread.
            function ready(ok) {
                if (settled) return;
                settled = true;
                window.clearTimeout(initTimer);
                if (!ok && worker) { worker.terminate(); worker = null; }
                resolve(ok);
            }
            var initTimer = window.setTimeout(function () { ready(false); }, 5000);
            try {
                worker = new window.Worker(w.url);
            } catch (e) {
                worker = null;
                ready(false);
                return;
            }
            worker.onmessage = function (event) {
                var data = event.data || {};
                if (data.init) { ready(!!data.ok); return; }
                var job = pending[data.id];
                if (!job) return;
                delete pending[data.id];
                window.clearTimeout(job.timer);
                if (data.ok) job.resolve(data.result);
                else job.reject(codeError(data.error === 'not_ready' ? 'unsupported' : 'internal'));
            };
            worker.onerror = function () { ready(false); };
            worker.postMessage({ init: w.scripts || [] });
        });
        return workerReady;
    }

    function stopWorker() {
        if (worker) worker.terminate();
        worker = null;
        workerReady = null;
        Object.keys(pending).forEach(function (id) {
            window.clearTimeout(pending[id].timer);
            pending[id].reject(codeError('timeout'));
        });
        pending = {};
    }

    function codeError(code) {
        var e = new Error(code);
        e.labsCode = code;
        return e;
    }

    /**
     * Run a core/ops.js operation. Uses the Worker when `useWorker`, falling
     * back to the main thread when no Worker can start. Rejects with
     * labsCode 'timeout' after the configured limit (the Worker is killed).
     */
    function run(op, payload, useWorker) {
        var timeout = (config.limits && config.limits.timeout_ms) || 10000;
        if (!useWorker) {
            return new Promise(function (resolve, reject) {
                try { resolve(window.OmochixLabsOps.run(op, payload)); } catch (e) { reject(codeError('internal')); }
            });
        }
        return startWorker().then(function (ok) {
            if (!ok || !worker) return run(op, payload, false);
            return new Promise(function (resolve, reject) {
                var id = ++seq;
                pending[id] = {
                    resolve: resolve,
                    reject: reject,
                    timer: window.setTimeout(function () {
                        // Kill the stuck worker; the next run starts a fresh one.
                        stopWorker();
                    }, timeout)
                };
                worker.postMessage({ id: id, op: op, payload: payload });
            });
        });
    }

    /** Message for a run() rejection. */
    function errorMessage(error) {
        var code = error && error.labsCode;
        if (code === 'timeout') return strings.timeout;
        return strings.internal;
    }

    function errorCode(error) {
        var code = error && error.labsCode;
        return code === 'timeout' || code === 'unsupported' ? code : 'internal';
    }

    /* ----------------------------------------------------------- shortcuts */

    /** Ctrl/⌘ + Enter in a field runs the tool's main action. */
    function bindRunShortcut(root, handler) {
        root.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey) && event.target && /^(TEXTAREA|INPUT)$/.test(event.target.tagName)) {
                event.preventDefault();
                handler();
            }
        });
    }

    /* ------------------------------------------------------- related links */

    function bindRelatedLinks() {
        document.addEventListener('click', function (event) {
            var link = event.target && event.target.closest ? event.target.closest('a[data-labs-link-type]') : null;
            if (!link) return;
            track('labs_related_click', { link_type: link.getAttribute('data-labs-link-type'), link_slug: link.getAttribute('data-labs-link-slug') });
        });
    }

    /* ------------------------------------------------------------ registry */

    /** Register a tool UI; `init(root, api)` runs once the DOM is ready. */
    function tool(slug, init) { tools[slug] = init; }

    function start() {
        document.documentElement.classList.add('labs-js');
        bindRelatedLinks();
        if (config.tool) {
            var root = document.querySelector('[data-labs-tool="' + config.tool + '"]');
            if (root && tools[config.tool]) {
                tools[config.tool](root, api);
                root.removeAttribute('data-labs-pending');
            }
            track('labs_tool_view', {});
        }
    }

    var api = {
        config: config,
        strings: strings,
        $: $,
        $all: $all,
        format: format,
        track: track,
        trackRun: trackRun,
        ui: ui,
        announce: announce,
        showError: showError,
        clearError: clearError,
        setBusy: setBusy,
        overLimit: overLimit,
        limits: limits,
        copy: copy,
        download: download,
        run: run,
        errorMessage: errorMessage,
        errorCode: errorCode,
        bindRunShortcut: bindRunShortcut,
        tool: tool
    };
    window.OmochixLabs = api;

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else window.setTimeout(start, 0);
}(window, document));
