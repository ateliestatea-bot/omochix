/**
 * OmochiX Labs: text helpers shared by every tool, the Web Worker and the
 * Node tests (tests/labs/core.js). Pure functions, no DOM.
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    else root.OmochixLabsText = api;
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /** UTF-8 byte length without allocating a buffer. */
    function byteLength(text) {
        var s = String(text);
        var bytes = 0;
        for (var i = 0; i < s.length; i++) {
            var c = s.charCodeAt(i);
            if (c < 0x80) bytes += 1;
            else if (c < 0x800) bytes += 2;
            else if (c >= 0xd800 && c <= 0xdbff && i + 1 < s.length) {
                var d = s.charCodeAt(i + 1);
                if (d >= 0xdc00 && d <= 0xdfff) { bytes += 4; i++; } else bytes += 3;
            } else bytes += 3;
        }
        return bytes;
    }

    /** Coarse size class sent with analytics events instead of the real size. */
    function sizeBucket(bytes) {
        if (bytes < 1024) return 'xs';
        if (bytes < 100 * 1024) return 's';
        if (bytes < 1024 * 1024) return 'm';
        return 'l';
    }

    function durationBucket(ms) {
        if (ms < 100) return 'fast';
        if (ms < 1000) return 'normal';
        return 'slow';
    }

    function formatBytes(bytes) {
        if (bytes >= 1024 * 1024) return (Math.round(bytes / 1024 / 1024 * 10) / 10) + 'MB';
        if (bytes >= 1024) return Math.round(bytes / 1024) + 'KB';
        return bytes + 'B';
    }

    /** NFKC, lower case, katakana folded to hiragana, whitespace collapsed. */
    function normalizeSearch(text) {
        var s = String(text || '');
        if (typeof s.normalize === 'function') s = s.normalize('NFKC');
        s = s.toLowerCase();
        s = s.replace(/[ァ-ヶ]/g, function (ch) { return String.fromCharCode(ch.charCodeAt(0) - 0x60); });
        return s.replace(/\s+/g, ' ').trim();
    }

    /** Split on LF, CRLF or CR. An empty text has no lines; a trailing newline adds no empty line. */
    function splitLines(text) {
        var s = String(text);
        if (s === '') return [];
        var lines = s.split(/\r\n|\r|\n/);
        if (lines.length > 1 && lines[lines.length - 1] === '') lines.pop();
        return lines;
    }

    function lineEnding(text) {
        var s = String(text);
        var crlf = (s.match(/\r\n/g) || []).length;
        var lf = (s.match(/\n/g) || []).length - crlf;
        var cr = (s.match(/\r/g) || []).length - crlf;
        var kinds = (crlf ? 1 : 0) + (lf ? 1 : 0) + (cr ? 1 : 0);
        if (kinds === 0) return 'none';
        if (kinds > 1) return 'mixed';
        return crlf ? 'CRLF' : (lf ? 'LF' : 'CR');
    }

    /** Local calendar date as YYYY-MM-DD. */
    function today(date) {
        var d = date || new Date();
        var m = d.getMonth() + 1;
        var day = d.getDate();
        return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
    }

    function daysBetween(from, to) {
        var a = Date.UTC(+from.slice(0, 4), +from.slice(5, 7) - 1, +from.slice(8, 10));
        var b = Date.UTC(+to.slice(0, 4), +to.slice(5, 7) - 1, +to.slice(8, 10));
        return Math.floor((b - a) / 86400000);
    }

    function isDate(value) {
        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
        var y = +value.slice(0, 4);
        var m = +value.slice(5, 7);
        var d = +value.slice(8, 10);
        var dt = new Date(Date.UTC(y, m - 1, d));
        return dt.getUTCFullYear() === y && dt.getUTCMonth() === m - 1 && dt.getUTCDate() === d;
    }

    return {
        byteLength: byteLength,
        sizeBucket: sizeBucket,
        durationBucket: durationBucket,
        formatBytes: formatBytes,
        normalizeSearch: normalizeSearch,
        splitLines: splitLines,
        lineEnding: lineEnding,
        today: today,
        daysBetween: daysBetween,
        isDate: isDate
    };
}));
