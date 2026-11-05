/**
 * OmochiX Labs: API price rules and cost arithmetic for the calculator.
 *
 * hiddenReason() / approvalState() / view() / active() / gate() mirror
 * omochix_labs_price_hidden_reason(), omochix_labs_row_approval_state(),
 * omochix_labs_pricing_view(), omochix_labs_pricing_active() and
 * omochix_labs_pricing_gate() in inc/labs.php (tests/labs/data.js runs both
 * on the same fixtures). The page receives the server's already-gated view
 * (on production: only approved rows of an approved file) and calls active()
 * again with the browser's own date, so a long-cached page never shows an
 * expired or stale price.
 *
 * All amounts are ESTIMATES from list prices; they do not include tax,
 * discounts, batch / priority tiers, cache writes or tool-use surcharges.
 */
(function (root, factory) {
    'use strict';
    var api = factory(root.OmochixLabsText || (typeof require === 'function' ? require('./text.js') : null));
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    else root.OmochixLabsCost = api;
}(typeof self !== 'undefined' ? self : this, function (Text) {
    'use strict';

    var SOURCE_HOSTS = [
        'platform.claude.com', 'docs.anthropic.com', 'www.anthropic.com', 'anthropic.com', 'claude.com', 'www.claude.com',
        'developers.openai.com', 'platform.openai.com', 'openai.com',
        'ai.google.dev', 'aistudio.google.com', 'cloud.google.com'
    ];
    var MAX_TOKENS = 1e9;
    var MAX_REQUESTS = 1e7;
    var MAX_DAYS = 3660;

    function isPrice(v) { return typeof v === 'number' && isFinite(v) && v >= 0 && v < 100000; }
    function isSlugish(id) { return typeof id === 'string' && id.length <= 60 && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(id.replace(/\./g, '-')); }

    function allowedSource(url) {
        if (typeof url !== 'string' || url.indexOf('https://') !== 0) return false;
        var host = url.slice(8).split(/[/?#:]/)[0].toLowerCase();
        return SOURCE_HOSTS.indexOf(host) !== -1;
    }

    function isCaveatCode(v) { return typeof v === 'string' && /^[a-z][a-z_]{2,60}$/.test(v); }

    /** 'approved' | 'held' | 'pending' (see omochix_labs_row_approval_state()). */
    function approvalState(model) {
        var a = model && model.approval && typeof model.approval === 'object' ? model.approval : {};
        if (a.status === 'held') return 'held';
        if (a.status !== 'approved') return 'pending';
        if (typeof a.reviewer !== 'string' || !a.reviewer.trim() || !Text.isDate(a.reviewed_at) || !Text.isDate(model.verified_at) || a.reviewed_at < model.verified_at) return 'pending';
        var ack = Array.isArray(a.acknowledged) ? a.acknowledged : [];
        var caveats = Array.isArray(model.caveats) ? model.caveats : [];
        for (var i = 0; i < caveats.length; i++) if (ack.indexOf(caveats[i]) === -1) return 'pending';
        return 'approved';
    }

    function hiddenReason(model, today, maxAge, caveatLabels) {
        var labels = caveatLabels || {};
        if (!model || typeof model !== 'object') return 'invalid';
        if (model.status !== 'verified') return 'unverified';
        if (!allowedSource(model.source_url)) return 'source';
        // One day of slack: a visitor west of Japan can still be on the previous date.
        if (!Text.isDate(model.verified_at) || Text.daysBetween(today, model.verified_at) > 1) return 'unverified';
        if (Text.daysBetween(model.verified_at, today) > maxAge) return 'stale';
        if (!isSlugish(model.id) || typeof model.label !== 'string' || !model.label || typeof model.provider !== 'string') return 'invalid';
        if (!isPrice(model.input) || !isPrice(model.output)) return 'invalid';
        if (model.cached_input !== undefined && model.cached_input !== null && !isPrice(model.cached_input)) return 'invalid';
        var long = model.long_context;
        if (long !== undefined && long !== null) {
            if (typeof long !== 'object' || !Number.isInteger(long.threshold_tokens) || long.threshold_tokens < 1 || !isPrice(long.input) || !isPrice(long.output)) return 'invalid';
            if (long.cached_input !== undefined && long.cached_input !== null && !isPrice(long.cached_input)) return 'invalid';
        }
        if (!Array.isArray(model.caveats) || model.caveats.some(function (c) { return !isCaveatCode(c) || !Object.prototype.hasOwnProperty.call(labels, c); })) return 'invalid';
        var ap = model.approval;
        if (!ap || typeof ap !== 'object' || ['pending', 'approved', 'held'].indexOf(ap.status) === -1 || (ap.status === 'held' && (typeof ap.reason !== 'string' || !ap.reason.trim()))) return 'invalid';
        if ((model.valid_from != null && !Text.isDate(model.valid_from)) || (model.valid_until != null && !Text.isDate(model.valid_until))) return 'invalid';
        if (model.valid_from && model.valid_until && model.valid_from > model.valid_until) return 'invalid';
        if (model.valid_until && today > model.valid_until) return 'expired';
        if (model.valid_from && today < model.valid_from) return 'upcoming';
        return '';
    }

    function view(data, today) {
        var out = { ok: false, currency: 'USD', max_age_days: 90, providers: {}, caveat_labels: {}, models: [], hidden: {} };
        if (!data || data.schema_version !== 2 || data.currency !== 'USD' || data.unit !== 'per_1m_tokens') return out;
        var maxAge = parseInt(data.max_age_days, 10) || 0;
        if (maxAge < 1 || maxAge > 90) maxAge = 90;
        out.ok = true;
        out.max_age_days = maxAge;
        (data.providers || []).forEach(function (p) {
            if (p && typeof p.slug === 'string' && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(p.slug) && typeof p.name === 'string') out.providers[p.slug] = p.name;
        });
        Object.keys(data.caveat_labels || {}).forEach(function (code) {
            var label = data.caveat_labels[code];
            if (isCaveatCode(code) && typeof label === 'string' && label.trim()) out.caveat_labels[code] = label;
        });
        (data.models || []).forEach(function (m) {
            var reason = hiddenReason(m, today, maxAge, out.caveat_labels);
            if ((reason === '' || reason === 'upcoming') && !Object.prototype.hasOwnProperty.call(out.providers, m.provider)) reason = 'invalid';
            if (reason === 'expired') return;
            if (reason !== '' && reason !== 'upcoming') { out.hidden[reason] = (out.hidden[reason] || 0) + 1; return; }
            var long = m.long_context;
            out.models.push({
                id: m.id,
                provider: m.provider,
                label: m.label,
                input: m.input,
                output: m.output,
                cached_input: m.cached_input === undefined || m.cached_input === null ? null : m.cached_input,
                long_context: long ? { threshold_tokens: long.threshold_tokens, input: long.input, output: long.output, cached_input: long.cached_input === undefined || long.cached_input === null ? null : long.cached_input } : null,
                valid_from: m.valid_from || null,
                valid_until: m.valid_until || null,
                source_url: m.source_url,
                verified_at: m.verified_at,
                caveats: m.caveats.slice(),
                approval: approvalState(m)
            });
        });
        return out;
    }

    /** What a page may show (see omochix_labs_pricing_gate()); publicMode = approved rows only. */
    function gate(v, approved, publicMode) {
        var out = JSON.parse(JSON.stringify(v));
        out.approved = !!approved;
        out.public_mode = !!publicMode;
        if (!publicMode) { out.withheld = false; return out; }
        var kept = [];
        out.models.forEach(function (m) {
            if (approved && m.approval === 'approved') kept.push(m);
            else {
                var key = 'row_' + (approved ? m.approval : 'unapproved_file');
                out.hidden[key] = (out.hidden[key] || 0) + 1;
            }
        });
        out.models = kept;
        out.withheld = !approved || !kept.length;
        return out;
    }

    /** Global gate on the raw file (see omochix_labs_pricing_approved()). */
    function fileApproved(data) {
        var r = data && data.review && typeof data.review === 'object' ? data.review : {};
        return r.status === 'approved' && typeof r.reviewer === 'string' && !!r.reviewer.trim() && Text.isDate(r.reviewed_at);
    }

    /** Rows that apply on `today` (see omochix_labs_pricing_active()). */
    function active(models, today, maxAge) {
        var rows = (models || []).filter(function (m) {
            if (!m || !Text.isDate(m.verified_at) || !allowedSource(m.source_url)) return false;
            if (m.valid_from && today < m.valid_from) return false;
            if (m.valid_until && today > m.valid_until) return false;
            return Text.daysBetween(today, m.verified_at) <= 1 && Text.daysBetween(m.verified_at, today) <= maxAge;
        });
        var counts = {};
        rows.forEach(function (m) { counts[m.id] = (counts[m.id] || 0) + 1; });
        return rows.filter(function (m) { return counts[m.id] === 1; });
    }

    function clampInt(value, max) {
        var n = Math.floor(Number(value));
        if (!isFinite(n) || n < 0) return 0;
        return Math.min(n, max);
    }

    /**
     * Cost of a usage pattern on one model.
     * @param {object} model active() row.
     * @param {{input: number, output: number, cached: number, requestsPerDay: number, days: number}} usage Tokens per request.
     */
    function compute(model, usage) {
        var u = {
            input: clampInt(usage.input, MAX_TOKENS),
            output: clampInt(usage.output, MAX_TOKENS),
            cached: clampInt(usage.cached, MAX_TOKENS),
            requestsPerDay: clampInt(usage.requestsPerDay, MAX_REQUESTS),
            days: clampInt(usage.days, MAX_DAYS)
        };
        var notes = [];
        var tier = 'base';
        var prices = { input: model.input, output: model.output, cached: model.cached_input };
        var prompt = u.input + u.cached;
        if (model.long_context && prompt > model.long_context.threshold_tokens) {
            tier = 'long';
            prices = { input: model.long_context.input, output: model.long_context.output, cached: model.long_context.cached_input };
            notes.push('long_context');
        }
        var cachedPrice = prices.cached;
        if (u.cached > 0 && (cachedPrice === null || cachedPrice === undefined)) {
            cachedPrice = prices.input;
            notes.push('cached_not_listed');
        }
        var perRequest = (u.input * prices.input + u.cached * (cachedPrice || 0) + u.output * prices.output) / 1e6;
        var perDay = perRequest * u.requestsPerDay;
        return { usage: u, tier: tier, prices: prices, perRequest: perRequest, perDay: perDay, total: perDay * u.days, notes: notes };
    }

    /** "$0.0012" / "$12.34" / "$1,234" — enough digits for small per-request amounts. */
    function formatUSD(value) {
        var v = Number(value) || 0;
        if (v === 0) return '$0';
        if (v < 0.01) return '$' + v.toPrecision(2).replace(/\.?0+$/, '').replace(/(e-\d+)$/, '$1');
        if (v < 100) return '$' + v.toFixed(v < 1 ? 4 : 2).replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.0+$/, '');
        return '$' + Math.round(v).toLocaleString('en-US');
    }

    function formatJPY(value, rate) {
        var r = Number(rate);
        if (!isFinite(r) || r <= 0 || r > 10000) return '';
        var yen = (Number(value) || 0) * r;
        if (yen === 0) return '0円';
        if (yen < 1) return (Math.round(yen * 100) / 100).toLocaleString('ja-JP') + '円';
        return Math.round(yen).toLocaleString('ja-JP') + '円';
    }

    function formatPrice(value) {
        if (value === null || value === undefined) return '—';
        return '$' + String(Math.round(value * 10000) / 10000);
    }

    return {
        SOURCE_HOSTS: SOURCE_HOSTS,
        hiddenReason: hiddenReason,
        approvalState: approvalState,
        view: view,
        gate: gate,
        fileApproved: fileApproved,
        active: active,
        compute: compute,
        formatUSD: formatUSD,
        formatJPY: formatJPY,
        formatPrice: formatPrice,
        allowedSource: allowedSource
    };
}));
