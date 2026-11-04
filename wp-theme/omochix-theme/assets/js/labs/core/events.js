/**
 * OmochiX Labs: analytics event schema. Only the six events below are ever
 * sent, and each only with its own keys and values from fixed vocabularies:
 * no input text, file name, URL, domain or typed number can pass through
 * (tests/labs/core.js). Events go to the gtag that Site Kit already loads;
 * nothing is sent when it is absent.
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    else root.OmochixLabsEvents = api;
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
    function slug(max) { return function (v) { return typeof v === 'string' && v.length <= max && SLUG.test(v); }; }
    function oneOf(list) { return function (v) { return list.indexOf(v) !== -1; }; }

    var VALUES = {
        tool_slug: slug(40),
        tool_category: oneOf(['ai', 'dev', 'web']),
        action: oneOf(['format', 'minify', 'validate', 'compare', 'generate', 'test', 'estimate', 'calculate', 'sample', 'reset', 'download']),
        size_bucket: oneOf(['xs', 's', 'm', 'l']),
        duration_bucket: oneOf(['fast', 'normal', 'slow']),
        error_code: oneOf(['invalid_input', 'too_large', 'timeout', 'unsupported', 'internal']),
        target: oneOf(['output', 'diff', 'robots', 'summary']),
        link_type: oneOf(['labs', 'ai_tool', 'prompt', 'learn', 'section']),
        link_slug: slug(60)
    };

    /** Allowed keys per event; the first key is required. */
    var EVENTS = {
        labs_tool_view: ['tool_slug', 'tool_category'],
        labs_tool_run: ['tool_slug', 'action', 'size_bucket'],
        labs_tool_success: ['tool_slug', 'action', 'duration_bucket'],
        labs_tool_error: ['tool_slug', 'action', 'error_code'],
        labs_tool_copy: ['tool_slug', 'target'],
        labs_related_click: ['tool_slug', 'link_type', 'link_slug']
    };

    /** Clean parameters for an event, or null when the event must not be sent. */
    function sanitize(name, params) {
        if (!Object.prototype.hasOwnProperty.call(EVENTS, name)) return null;
        var keys = EVENTS[name];
        var p = params && typeof params === 'object' ? params : {};
        var out = {};
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (Object.prototype.hasOwnProperty.call(p, key) && VALUES[key](p[key])) out[key] = p[key];
        }
        if (!Object.prototype.hasOwnProperty.call(out, keys[0])) return null;
        return out;
    }

    /** Send through gtag when present. Returns the payload that was sent (or null). */
    function send(name, params, gtag) {
        var clean = sanitize(name, params);
        if (!clean || typeof gtag !== 'function') return null;
        try { gtag('event', name, clean); } catch (e) { return null; }
        return clean;
    }

    return { EVENTS: EVENTS, sanitize: sanitize, send: send };
}));
