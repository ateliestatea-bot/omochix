'use strict';
/**
 * Console message policy for the Labs browser checks (Chromium, WebKit, Firefox).
 *
 * Every console message on a Labs page is classified:
 *  - "violation": a CSP (Report-Only) violation. Always a failure.
 *  - "known":     a CSP *configuration* notice listed in KNOWN below. Accepted only
 *                 for the listed browser, only when the text matches exactly, and only
 *                 when its condition holds for the policy the page was really served
 *                 with (for example, the notice must quote that exact policy).
 *  - "csp-unknown": anything else that mentions CSP. A failure: a new notice has to be
 *                 reviewed and added here on purpose, never ignored by default.
 *  - "error":     any other console error. A failure.
 *  - "other":     logs, info and warnings unrelated to CSP.
 *
 * Firefox localises its console text, so Firefox patterns key on the fixed
 * "Content-Security-Policy:" prefix and the Report-Only marker only.
 */

// Accepted CSP configuration notices. Keep this list short and specific.
var KNOWN = [
    {
        id: 'webkit-report-only-without-report-to',
        browsers: ['webkit'],
        // WebKit/Safari: a Report-Only policy with no report-to. Expected: there is no
        // report endpoint by design (docs/labs/LABS-CSP-OBSERVATION.md).
        pattern: /^The Content Security Policy '([^]*)' was delivered in report-only mode, but does not specify a 'report-to'; the policy will have no effect\. Please either add a 'report-to' directive, or deliver the policy via the 'Content-Security-Policy' header\.$/,
        when: function (match, policy) {
            return typeof policy === 'string' && policy !== '' && match[1] === policy && !/\breport-(to|uri)\b/.test(policy);
        },
    },
];

var VIOLATION = {
    chromium: [/violates the following Content Security Policy directive: ".+"\. The policy is report-only/],
    webkit: [/^\[Report Only\] Refused to /],
    firefox: [/^Content-Security-Policy: \(Report-Only\b/],
};

var CSP_RELATED = /Content[- ]Security[- ]Policy|\bCSP\b|report-only|report-to|frame-ancestors/i;

/**
 * @param {string} browser chromium | webkit | firefox
 * @param {{type: string, text: string}} msg console type (error, warning, info, log...) and text
 * @param {string} policy the Content-Security-Policy-Report-Only header of the page
 * @return {{kind: string, id?: string, text: string}}
 */
function classify(browser, msg, policy) {
    var text = String(msg.text || '');
    var v = VIOLATION[browser] || [];
    for (var i = 0; i < v.length; i++) {
        if (v[i].test(text)) return { kind: 'violation', text: text };
    }
    for (var k = 0; k < KNOWN.length; k++) {
        var entry = KNOWN[k];
        if (entry.browsers.indexOf(browser) === -1) continue;
        var m = entry.pattern.exec(text);
        if (m && entry.when(m, policy)) return { kind: 'known', id: entry.id, text: text };
    }
    if (CSP_RELATED.test(text)) return { kind: 'csp-unknown', text: text };
    if (msg.type === 'error') return { kind: 'error', text: text };
    return { kind: 'other', text: text };
}

/**
 * @return {{ok: boolean, known: Object<string, number>, bad: Array}}
 */
function review(browser, messages, policy) {
    var known = {};
    var bad = [];
    messages.forEach(function (msg) {
        var c = classify(browser, msg, policy);
        if (c.kind === 'known') known[c.id] = (known[c.id] || 0) + 1;
        else if (c.kind !== 'other') bad.push(c.kind + ': ' + c.text.slice(0, 300) + (msg.url ? ' @ ' + msg.url : ''));
    });
    return { ok: bad.length === 0, known: known, bad: bad };
}

module.exports = { classify: classify, review: review, KNOWN: KNOWN };
