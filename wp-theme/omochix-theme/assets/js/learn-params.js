/**
 * Learn v2 / Motion Gallery: playground parameter helpers.
 *
 * Pure functions, no DOM. They mirror the PHP side in inc/learn-gallery.php
 * (omochix_learn_gallery_sanitize_params, _params_blocks, _params_text,
 * _param_display, _format_number) so that the preview document, the copied
 * code and the prompt text are assembled identically on the server and in
 * the browser. tests/learn-gallery/params-parity.js checks that.
 *
 * Loaded before learn-gallery.js (which needs the blocks to build preview
 * documents) and learn-playground.js (the panel). Exposed as
 * window.omochixMotionParams; also usable from Node for the tests.
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    root.omochixMotionParams = api;
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    var COLOR_RE = /^#[0-9a-f]{6}$/i;

    function has(obj, key) {
        return !!obj && Object.prototype.hasOwnProperty.call(obj, key);
    }

    /** Number formatting shared with PHP: up to 4 decimals, no trailing zeros. */
    function formatNumber(n) {
        var s = (Math.round(Number(n) * 10000) / 10000).toFixed(4).replace(/\.?0+$/, '');
        return s === '' || s === '-0' ? '0' : s;
    }

    function defaults(defs) {
        var values = {};
        (defs || []).forEach(function (def) { values[def.key] = def['default']; });
        return values;
    }

    /** Coerce raw values into valid ones: clamp/snap ranges, hex colours, allowed select values. */
    function sanitize(defs, raw) {
        var values = {};
        raw = raw || {};
        (defs || []).forEach(function (def) {
            var v = has(raw, def.key) ? raw[def.key] : null;
            if (def.type === 'range') {
                var n = typeof v === 'string' && v.trim() !== '' ? Number(v) : v;
                if (typeof n !== 'number' || !isFinite(n)) n = def['default'];
                n = Math.max(def.min, Math.min(def.max, n));
                n = def.min + Math.round((n - def.min) / def.step) * def.step;
                n = Math.max(def.min, Math.min(def.max, n));
                values[def.key] = Number(formatNumber(n));
            } else if (def.type === 'color') {
                var c = typeof v === 'string' ? v.trim().toLowerCase() : '';
                if (/^[0-9a-f]{6}$/.test(c)) c = '#' + c;
                values[def.key] = COLOR_RE.test(c) ? c : def['default'];
            } else {
                var allowed = (def.options || []).map(function (o) { return o.value; });
                values[def.key] = typeof v === 'string' && allowed.indexOf(v) > -1 ? v : def['default'];
            }
        });
        return values;
    }

    /** Human-readable value: "2.4s", "#ff0066", the option label. */
    function display(def, v) {
        if (def.type === 'range') return formatNumber(v) + (def.unit || '');
        if (def.type === 'select') {
            for (var i = 0; i < (def.options || []).length; i++) if (def.options[i].value === v) return def.options[i].label;
        }
        return String(v);
    }

    function sameAsDefault(def, v) {
        return def.type === 'range' ? Number(v) === Number(def['default']) : v === def['default'];
    }

    /**
     * {cssVars:{'--p-x':'400ms'}, js:{target:12800}} plus the serialized
     * blocks the templates take: css ":root{…}" and jsBlock
     * "window.omxParams={…};" (empty strings when nothing applies).
     */
    function blocks(defs, values) {
        var cssVars = {};
        var cssList = [];
        var js = {};
        var hasJs = false;
        values = values || {};
        (defs || []).forEach(function (def) {
            var v = has(values, def.key) ? values[def.key] : def['default'];
            var cssValue = '';
            var jsValue = v;
            if (def.type === 'select') {
                (def.options || []).forEach(function (o) { if (o.value === v) cssValue = o.css || ''; });
            } else if (def.type === 'color') {
                cssValue = v;
            } else {
                cssValue = formatNumber(v) + (def.unit || '');
                jsValue = Number(formatNumber(v));
            }
            if (def.target === 'js') { js[def.key] = jsValue; hasJs = true; }
            else if (cssValue !== '') { cssVars[def.css_var] = cssValue; cssList.push(def.css_var + ':' + cssValue); }
        });
        return {
            cssVars: cssVars,
            js: js,
            css: cssList.length ? ':root{' + cssList.join(';') + '}' : '',
            jsBlock: hasJs ? 'window.omxParams=' + JSON.stringify(js) + ';' : ''
        };
    }

    /** The "調整した値:" lines inserted into the prompt ("" without params). */
    function text(defs, values) {
        if (!defs || !defs.length) return '';
        values = values || {};
        var lines = ['調整した値:'];
        defs.forEach(function (def) {
            var v = has(values, def.key) ? values[def.key] : def['default'];
            var shown = display(def, v);
            var line = def.prompt ? def.prompt.replace('{value}', shown) : def.label + ': ' + shown;
            lines.push('- ' + line + (sameAsDefault(def, v) ? '（既定）' : ''));
        });
        return lines.join('\n') + '\n';
    }

    function isDefault(defs, values) {
        values = values || {};
        return (defs || []).every(function (def) { return sameAsDefault(def, values[def.key]); });
    }

    /** Serialize for the URL hash: "cycle:1.5,color:ff0066" ("" at the defaults). */
    function toHash(defs, values) {
        if (isDefault(defs, values)) return '';
        return (defs || []).map(function (def) {
            var v = values[def.key];
            return def.key + ':' + (def.type === 'color' ? String(v).replace('#', '') : def.type === 'range' ? formatNumber(v) : v);
        }).join(',');
    }

    /** Parse the hash payload back into raw values (unknown keys kept for sanitize to drop). */
    function fromHash(payload) {
        var raw = {};
        String(payload || '').split(',').slice(0, 20).forEach(function (pair) {
            var colon = pair.indexOf(':');
            if (colon < 1) return;
            var key = pair.slice(0, colon);
            if (/^[a-z][a-z0-9_]{0,23}$/.test(key)) raw[key] = pair.slice(colon + 1, colon + 65);
        });
        return raw;
    }

    function fillTemplate(template, map) {
        return String(template).replace(/\{\{[a-z_]+\}\}/g, function (token) {
            return has(map, token) ? map[token] : token;
        });
    }

    return {
        formatNumber: formatNumber,
        defaults: defaults,
        sanitize: sanitize,
        display: display,
        blocks: blocks,
        text: text,
        isDefault: isDefault,
        toHash: toHash,
        fromHash: fromHash,
        fillTemplate: fillTemplate
    };
}));
