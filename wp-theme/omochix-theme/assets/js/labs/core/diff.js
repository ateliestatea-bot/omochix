/**
 * OmochiX Labs: text diff for the text-diff tool.
 *
 * Myers' O(ND) algorithm on lines (after trimming the common prefix and
 * suffix), then a second, word- or character-level pass inside changed
 * lines so a one-character edit is visible. Japanese is compared character
 * by character; runs of Latin letters and digits as words. Pure functions,
 * shared by the page, the Web Worker and tests/labs/core.js.
 */
(function (root, factory) {
    'use strict';
    var api = factory(root.OmochixLabsText || (typeof require === 'function' ? require('./text.js') : null));
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    else root.OmochixLabsDiff = api;
}(typeof self !== 'undefined' ? self : this, function (Text) {
    'use strict';

    var DEFAULT_MAX_D = 3000;
    var INLINE_MAX_CHARS = 2000;
    var INLINE_MAX_D = 400;

    /**
     * Shortest edit script between two arrays of comparable keys.
     * @return {{ops: Array<{type: string, a: number, b: number}>, approximate: boolean}}
     *   ops in order; a / b are indexes into the inputs (-1 when not applicable).
     */
    function sequences(a, b, maxD) {
        var limit = typeof maxD === 'number' ? maxD : DEFAULT_MAX_D;
        var start = 0;
        var endA = a.length;
        var endB = b.length;
        while (start < endA && start < endB && a[start] === b[start]) start++;
        while (endA > start && endB > start && a[endA - 1] === b[endB - 1]) { endA--; endB--; }

        var ops = [];
        var k;
        for (k = 0; k < start; k++) ops.push({ type: 'equal', a: k, b: k });
        var middle = myers(a, b, start, endA, start, endB, limit);
        var approximate = false;
        if (!middle) {
            approximate = true;
            middle = [];
            for (k = start; k < endA; k++) middle.push({ type: 'delete', a: k, b: -1 });
            for (k = start; k < endB; k++) middle.push({ type: 'insert', a: -1, b: k });
        }
        ops = ops.concat(middle);
        for (k = 0; k < a.length - endA; k++) ops.push({ type: 'equal', a: endA + k, b: endB + k });
        return { ops: ops, approximate: approximate };
    }

    function myers(a, b, a0, a1, b0, b1, maxD) {
        var n = a1 - a0;
        var m = b1 - b0;
        if (n === 0 && m === 0) return [];
        var max = n + m;
        var off = max + 1;
        var v = new Int32Array(2 * max + 3);
        var trace = [];
        var d, k, x, y, found = -1;
        for (d = 0; d <= max; d++) {
            if (d > maxD) return null;
            // Snapshot of v[-d-1 .. d+1] before this step (enough to backtrack step d).
            trace.push(v.slice(off - d - 1, off + d + 2));
            for (k = -d; k <= d; k += 2) {
                if (k === -d || (k !== d && v[off + k - 1] < v[off + k + 1])) x = v[off + k + 1];
                else x = v[off + k - 1] + 1;
                y = x - k;
                while (x < n && y < m && a[a0 + x] === b[b0 + y]) { x++; y++; }
                v[off + k] = x;
                if (x >= n && y >= m) { found = d; break; }
            }
            if (found !== -1) break;
        }
        var ops = [];
        x = n;
        y = m;
        for (d = found; d >= 0; d--) {
            var snap = trace[d];
            var at = function (kk) { return snap[kk + d + 1]; };
            k = x - y;
            var prevK = (k === -d || (k !== d && at(k - 1) < at(k + 1))) ? k + 1 : k - 1;
            var prevX = d === 0 ? 0 : at(prevK);
            var prevY = prevX - prevK;
            if (d === 0) { prevX = 0; prevY = 0; }
            while (x > prevX && y > prevY) {
                x--; y--;
                ops.push({ type: 'equal', a: a0 + x, b: b0 + y });
            }
            if (d > 0) {
                if (x === prevX) ops.push({ type: 'insert', a: -1, b: b0 + prevY });
                else ops.push({ type: 'delete', a: a0 + prevX, b: -1 });
            }
            x = prevX;
            y = prevY;
        }
        ops.reverse();
        // Within a change block put deletions before insertions (readable, and what the pairing expects).
        var out = [];
        var dels = [];
        var ins = [];
        function flush() { out = out.concat(dels, ins); dels = []; ins = []; }
        for (var i = 0; i < ops.length; i++) {
            if (ops[i].type === 'equal') { flush(); out.push(ops[i]); }
            else if (ops[i].type === 'delete') dels.push(ops[i]);
            else ins.push(ops[i]);
        }
        flush();
        return out;
    }

    function keyFn(opts) {
        var o = opts || {};
        return function (line) {
            var s = line;
            if (o.ignoreWhitespace) s = s.replace(/\s+/g, ' ').trim();
            if (o.ignoreCase) s = s.toLowerCase();
            return s;
        };
    }

    /** Map strings to small integers so the inner loop compares numbers. */
    function intern(listA, listB, key) {
        var ids = new Map();
        function map(list) {
            var out = new Array(list.length);
            for (var i = 0; i < list.length; i++) {
                var kk = key(list[i]);
                var id = ids.get(kk);
                if (id === undefined) { id = ids.size; ids.set(kk, id); }
                out[i] = id;
            }
            return out;
        }
        return [map(listA), map(listB)];
    }

    var TOKEN = /[A-Za-z0-9_]+|\s+|[\s\S]/gu;

    function tokens(text) {
        return String(text).match(TOKEN) || [];
    }

    function mergeSegments(list) {
        var out = [];
        for (var i = 0; i < list.length; i++) {
            var last = out[out.length - 1];
            if (last && last.t === list[i].t) last.s += list[i].s;
            else out.push({ t: list[i].t, s: list[i].s });
        }
        return out;
    }

    /**
     * Word / character changes between two lines.
     * @return {{old: Array<{t: string, s: string}>, new: Array<{t: string, s: string}>}|null} null when too long to compare.
     */
    function inline(oldLine, newLine, opts) {
        if (oldLine.length > INLINE_MAX_CHARS || newLine.length > INLINE_MAX_CHARS) return null;
        var ta = tokens(oldLine);
        var tb = tokens(newLine);
        var o = opts || {};
        var ids = intern(ta, tb, function (t) {
            var s = t;
            if (o.ignoreWhitespace && /^\s+$/.test(s)) s = ' ';
            if (o.ignoreCase) s = s.toLowerCase();
            return s;
        });
        var result = sequences(ids[0], ids[1], INLINE_MAX_D);
        if (result.approximate) return null;
        var oldSeg = [];
        var newSeg = [];
        result.ops.forEach(function (op) {
            if (op.type === 'equal') { oldSeg.push({ t: 'equal', s: ta[op.a] }); newSeg.push({ t: 'equal', s: tb[op.b] }); }
            else if (op.type === 'delete') oldSeg.push({ t: 'delete', s: ta[op.a] });
            else newSeg.push({ t: 'insert', s: tb[op.b] });
        });
        return { old: mergeSegments(oldSeg), new: mergeSegments(newSeg) };
    }

    /**
     * Line diff.
     * @param {string} oldText
     * @param {string} newText
     * @param {{ignoreWhitespace?: boolean, ignoreCase?: boolean, maxD?: number}} opts
     * @return {{rows: object[], stats: object, lineEndings: object, approximate: boolean}}
     */
    function lines(oldText, newText, opts) {
        var o = opts || {};
        var oldLines = Text.splitLines(oldText);
        var newLines = Text.splitLines(newText);
        var ids = intern(oldLines, newLines, keyFn(o));
        var result = sequences(ids[0], ids[1], o.maxD);
        var rows = [];
        var stats = { added: 0, removed: 0, unchanged: 0, oldLines: oldLines.length, newLines: newLines.length };
        result.ops.forEach(function (op) {
            if (op.type === 'equal') {
                stats.unchanged++;
                rows.push({ type: 'equal', oldNo: op.a + 1, newNo: op.b + 1, text: oldLines[op.a], newText: newLines[op.b] });
            } else if (op.type === 'delete') {
                stats.removed++;
                rows.push({ type: 'delete', oldNo: op.a + 1, newNo: 0, text: oldLines[op.a] });
            } else {
                stats.added++;
                rows.push({ type: 'insert', oldNo: 0, newNo: op.b + 1, text: newLines[op.b] });
            }
        });
        // Pair the n-th deleted line of a change block with its n-th inserted line.
        var i = 0;
        var pairs = 0;
        while (i < rows.length) {
            if (rows[i].type === 'equal') { i++; continue; }
            var dels = [];
            var ins = [];
            while (i < rows.length && rows[i].type !== 'equal') {
                (rows[i].type === 'delete' ? dels : ins).push(i);
                i++;
            }
            var count = Math.min(dels.length, ins.length);
            for (var p = 0; p < count && pairs < 2000; p++, pairs++) {
                var seg = inline(rows[dels[p]].text, rows[ins[p]].text, o);
                rows[dels[p]].pair = ins[p];
                rows[ins[p]].pair = dels[p];
                if (seg) { rows[dels[p]].segments = seg.old; rows[ins[p]].segments = seg.new; }
            }
        }
        stats.same = stats.added === 0 && stats.removed === 0;
        return {
            rows: rows,
            stats: stats,
            lineEndings: { old: Text.lineEnding(oldText), new: Text.lineEnding(newText) },
            approximate: result.approximate
        };
    }

    /** unified diff text (like `diff -u`) from lines().rows. */
    function unified(rows, opts) {
        var o = opts || {};
        var context = typeof o.context === 'number' ? o.context : 3;
        var out = ['--- ' + (o.oldName || 'before'), '+++ ' + (o.newName || 'after')];
        var changes = [];
        rows.forEach(function (r, idx) { if (r.type !== 'equal') changes.push(idx); });
        if (!changes.length) return out.join('\n') + '\n';
        var c = 0;
        while (c < changes.length) {
            var from = Math.max(0, changes[c] - context);
            var to = Math.min(rows.length - 1, changes[c] + context);
            while (c + 1 < changes.length && changes[c + 1] - context <= to + 1) {
                c++;
                to = Math.min(rows.length - 1, changes[c] + context);
            }
            c++;
            var body = [];
            var oldStart = 0, newStart = 0, oldLen = 0, newLen = 0;
            for (var r = from; r <= to; r++) {
                var row = rows[r];
                if (row.type === 'equal') {
                    if (!oldStart) oldStart = row.oldNo;
                    if (!newStart) newStart = row.newNo;
                    oldLen++; newLen++;
                    body.push(' ' + row.text);
                } else if (row.type === 'delete') {
                    if (!oldStart) oldStart = row.oldNo;
                    oldLen++;
                    body.push('-' + row.text);
                } else {
                    if (!newStart) newStart = row.newNo;
                    newLen++;
                    body.push('+' + row.text);
                }
            }
            // An empty side starts at the line before (diff -u convention).
            if (!oldLen) oldStart = startBefore(rows, from, 'oldNo');
            if (!newLen) newStart = startBefore(rows, from, 'newNo');
            out.push('@@ -' + oldStart + (oldLen === 1 ? '' : ',' + oldLen) + ' +' + newStart + (newLen === 1 ? '' : ',' + newLen) + ' @@');
            out = out.concat(body);
        }
        return out.join('\n') + '\n';
    }

    function startBefore(rows, from, key) {
        for (var r = from - 1; r >= 0; r--) if (rows[r][key]) return rows[r][key];
        return 0;
    }

    return { sequences: sequences, inline: inline, lines: lines, unified: unified, tokens: tokens };
}));
