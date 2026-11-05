/**
 * OmochiX Labs: the heavy operations, by name, so the page can run them in
 * the Web Worker (assets/js/labs/worker.js) or, for small inputs or when a
 * Worker is unavailable, on the main thread with identical results.
 */
(function (root, factory) {
    'use strict';
    var api = factory(root);
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    else root.OmochixLabsOps = api;
}(typeof self !== 'undefined' ? self : this, function (root) {
    'use strict';

    function lib(name, file) {
        if (root[name]) return root[name];
        if (typeof require === 'function') return require(file);
        throw new Error(name + ' not loaded');
    }

    var OPS = {
        'json.format': function (p) {
            return lib('OmochixLabsJson', './json.js').format(p.text, { indent: p.indent, sortKeys: p.sortKeys });
        },
        'diff.lines': function (p) {
            var Diff = lib('OmochixLabsDiff', './diff.js');
            var result = Diff.lines(p.oldText, p.newText, { ignoreWhitespace: p.ignoreWhitespace, ignoreCase: p.ignoreCase });
            result.unified = Diff.unified(result.rows, { oldName: p.oldName, newName: p.newName });
            return result;
        },
        'tokens.estimate': function (p) {
            return lib('OmochixLabsTokens', './tokens.js').estimate(p.text);
        }
    };

    function run(op, payload) {
        if (!Object.prototype.hasOwnProperty.call(OPS, op)) throw new Error('unknown op');
        return OPS[op](payload || {});
    }

    return { run: run, names: Object.keys(OPS) };
}));
