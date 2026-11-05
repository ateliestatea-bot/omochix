/**
 * OmochiX Labs: JSON parser and printer for the JSON formatter.
 *
 * A strict RFC 8259 parser that keeps every number and string exactly as
 * written (so integers beyond 2^53 survive formatting, unlike JSON.parse +
 * JSON.stringify) and reports errors in Japanese with a line and column.
 * Pure functions, shared by the page, the Web Worker and tests/labs/core.js.
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    else root.OmochixLabsJson = api;
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var MAX_DEPTH = 512;
    var MAX_WARNINGS = 20;
    var MAX_SAFE = '9007199254740991';

    function display(ch) {
        if (ch === undefined) return '';
        var code = ch.charCodeAt(0);
        if (code < 32) return 'U+' + ('000' + code.toString(16).toUpperCase()).slice(-4);
        return ch;
    }

    /** Line and column (both 1-based, column counted in characters) for an offset. */
    function position(text, offset) {
        var s = String(text);
        var line = 1;
        var lineStart = 0;
        for (var i = 0; i < offset && i < s.length; i++) {
            if (s.charCodeAt(i) === 10) { line++; lineStart = i + 1; }
        }
        var column = Array.from(s.slice(lineStart, Math.min(offset, s.length))).length + 1;
        return { line: line, column: column };
    }

    function isBigInteger(raw) {
        if (/[.eE]/.test(raw)) return false;
        var digits = raw.charAt(0) === '-' ? raw.slice(1) : raw;
        if (digits.length !== MAX_SAFE.length) return digits.length > MAX_SAFE.length;
        return digits > MAX_SAFE;
    }

    /** More than 17 significant digits in a number with a fraction or exponent (a double keeps about 17). */
    function isLongDecimal(raw) {
        if (!/[.eE]/.test(raw)) return false;
        var mantissa = raw.replace(/^-/, '').replace(/[eE].*$/, '').replace('.', '').replace(/^0+/, '');
        return mantissa.replace(/0+$/, '').length > 17;
    }

    /**
     * @return {{ok: boolean, ast?: object, error?: object, warnings: object[], stats?: object}}
     */
    function parse(text) {
        var s = String(text);
        var n = s.length;
        var i = 0;
        var depth = 0;
        var warnings = [];
        var stats = { objects: 0, arrays: 0, values: 0, maxDepth: 0 };

        function fail(code, message, at) {
            var err = new Error(message);
            err.jsonCode = code;
            err.offset = at === undefined ? i : at;
            throw err;
        }
        function warn(code, message, at) {
            if (warnings.length < MAX_WARNINGS) warnings.push({ code: code, message: message, offset: at });
        }
        function ws() {
            while (i < n) {
                var c = s.charCodeAt(i);
                if (c === 32 || c === 9 || c === 10 || c === 13) i++;
                else break;
            }
        }
        function unexpected() {
            var c = s.charAt(i);
            if (c === "'") fail('single_quote', '文字列は二重引用符（"）で囲む必要があります。単一引用符（\'）は使えません。');
            if (c === '/' && (s.charAt(i + 1) === '/' || s.charAt(i + 1) === '*')) fail('comment', 'JSONではコメント（// や /* */）を使えません。');
            if (s.startsWith('NaN', i) || s.startsWith('Infinity', i) || s.startsWith('undefined', i)) fail('literal', 'NaN・Infinity・undefined はJSONの値として使えません。');
            if (s.startsWith('True', i) || s.startsWith('False', i) || s.startsWith('None', i) || s.startsWith('Null', i)) fail('literal_case', 'true・false・null は小文字で書く必要があります。');
            fail('unexpected', '予期しない文字「' + display(c) + '」があります。');
        }
        function enter() {
            depth++;
            if (depth > MAX_DEPTH) fail('too_deep', '入れ子が深すぎます（' + MAX_DEPTH + '段まで）。');
            if (depth > stats.maxDepth) stats.maxDepth = depth;
        }
        function string() {
            var start = i;
            i++;
            while (true) {
                if (i >= n) fail('unclosed_string', '文字列が閉じていません（" が足りません）。', start);
                var c = s.charCodeAt(i);
                if (c === 34) { i++; return s.slice(start, i); }
                if (c === 92) {
                    var e = s.charAt(i + 1);
                    if ('"\\/bfnrt'.indexOf(e) !== -1 && e !== '') { i += 2; continue; }
                    if (e === 'u') {
                        if (!/^[0-9a-fA-F]{4}$/.test(s.substr(i + 2, 4))) fail('bad_escape', '\\u の後には16進数4桁が必要です。');
                        i += 6;
                        continue;
                    }
                    fail('bad_escape', '不正なエスケープ「\\' + display(e) + '」があります。');
                }
                if (c < 32) fail('control_char', '文字列の中に改行やタブをそのまま入れることはできません。\\n や \\t のように書いてください。');
                i++;
            }
        }
        function digits() {
            var start = i;
            while (i < n && s.charCodeAt(i) >= 48 && s.charCodeAt(i) <= 57) i++;
            return i - start;
        }
        function number() {
            var start = i;
            if (s.charAt(i) === '-') i++;
            if (s.charAt(i) === '0') {
                i++;
                if (s.charCodeAt(i) >= 48 && s.charCodeAt(i) <= 57) fail('leading_zero', '数値の先頭に 0 を続けることはできません（例: 007）。', start);
            } else if (!digits()) {
                fail('bad_number', '数値の形式が正しくありません。', start);
            }
            if (s.charAt(i) === '.') {
                i++;
                if (!digits()) fail('bad_number', '小数点の後に数字が必要です。', start);
            }
            if (s.charAt(i) === 'e' || s.charAt(i) === 'E') {
                i++;
                if (s.charAt(i) === '+' || s.charAt(i) === '-') i++;
                if (!digits()) fail('bad_number', '指数（e）の後に数字が必要です。', start);
            }
            var raw = s.slice(start, i);
            if (isLongDecimal(raw)) warn('long_decimal', '有効数字が17桁を超える数値があります。JavaScript の JSON.parse では値が丸められます（このツールでは書かれたまま残します）。', start);
            if (isBigInteger(raw)) warn('big_integer', '9007199254740991 を超える整数があります。JavaScript の JSON.parse では値が丸められます（このツールでは書かれたまま残します）。', start);
            return raw;
        }
        function literal(word) {
            if (s.startsWith(word, i)) { i += word.length; return { t: 'l', raw: word }; }
            return null;
        }
        function value() {
            ws();
            if (i >= n) fail('eof', '値が必要ですが、入力が終わっています。');
            stats.values++;
            var c = s.charAt(i);
            if (c === '{') return object();
            if (c === '[') return array();
            if (c === '"') return { t: 's', raw: string() };
            if (c === '-' || (c >= '0' && c <= '9')) return { t: 'n', raw: number() };
            var lit = literal('true') || literal('false') || literal('null');
            if (lit) return lit;
            return unexpected();
        }
        function object() {
            var open = i;
            enter();
            stats.objects++;
            i++;
            var members = [];
            var keys = Object.create(null);
            ws();
            if (s.charAt(i) === '}') { i++; depth--; return { t: 'o', m: members }; }
            while (true) {
                ws();
                if (s.charAt(i) !== '"') {
                    if (i >= n) fail('unclosed_object', 'オブジェクトの「{」が閉じていません（} が足りません）。', open);
                    if (/[A-Za-z_$]/.test(s.charAt(i))) fail('unquoted_key', 'キーは二重引用符（"）で囲む必要があります。');
                    if (s.charAt(i) === "'" || s.charAt(i) === '/') unexpected();
                    fail('expected_key', 'キー（"name" のような文字列）が必要です。');
                }
                var keyAt = i;
                var raw = string();
                var key = JSON.parse(raw);
                if (keys[key]) warn('duplicate_key', 'キー「' + (key.length > 40 ? key.slice(0, 40) + '…' : key) + '」が同じオブジェクトの中で重複しています。多くの処理系では後の値だけが使われます。', keyAt);
                keys[key] = true;
                ws();
                if (s.charAt(i) !== ':') fail('expected_colon', 'キーの後にコロン（:）が必要です。');
                i++;
                members.push([raw, value()]);
                ws();
                var c = s.charAt(i);
                if (c === ',') {
                    var comma = i;
                    i++;
                    ws();
                    if (s.charAt(i) === '}') fail('trailing_comma', '最後の要素の後のカンマ（,）はJSONでは使えません。', comma);
                    continue;
                }
                if (c === '}') { i++; depth--; return { t: 'o', m: members }; }
                if (i >= n) fail('unclosed_object', 'オブジェクトの「{」が閉じていません（} が足りません）。', open);
                if (c === '/' || c === "'") unexpected();
                fail('expected_comma', '要素の区切りのカンマ（,）か、閉じる「}」が必要です。');
            }
        }
        function array() {
            var open = i;
            enter();
            stats.arrays++;
            i++;
            var items = [];
            ws();
            if (s.charAt(i) === ']') { i++; depth--; return { t: 'a', v: items }; }
            while (true) {
                items.push(value());
                ws();
                var c = s.charAt(i);
                if (c === ',') {
                    var comma = i;
                    i++;
                    ws();
                    if (s.charAt(i) === ']') fail('trailing_comma', '最後の要素の後のカンマ（,）はJSONでは使えません。', comma);
                    continue;
                }
                if (c === ']') { i++; depth--; return { t: 'a', v: items }; }
                if (i >= n) fail('unclosed_array', '配列の「[」が閉じていません（] が足りません）。', open);
                if (c === '/' || c === "'") unexpected();
                fail('expected_comma', '要素の区切りのカンマ（,）か、閉じる「]」が必要です。');
            }
        }

        try {
            if (s.charCodeAt(0) === 0xfeff) {
                warn('bom', '先頭にBOM（目に見えない文字 U+FEFF）があります。出力からは取り除きました。', 0);
                i = 1;
            }
            ws();
            if (i >= n) fail('empty', 'JSONを入力してください。');
            var ast = value();
            ws();
            if (i < n && s.charAt(i) === '/' && (s.charAt(i + 1) === '/' || s.charAt(i + 1) === '*')) unexpected();
            if (i < n) fail('trailing_data', 'JSONの値の後に余分なデータがあります。値は1つだけにしてください（複数なら配列 [ ] でまとめます）。');
            stats.root = ast.t === 'o' ? 'object' : ast.t === 'a' ? 'array' : ast.t === 's' ? 'string' : ast.t === 'n' ? 'number' : 'literal';
            return { ok: true, ast: ast, warnings: warnings, stats: stats };
        } catch (e) {
            if (!e || !e.jsonCode) throw e;
            return { ok: false, error: { code: e.jsonCode, message: e.message, offset: e.offset }, warnings: warnings };
        }
    }

    function keyOf(member) {
        if (member.length < 3) member.push(JSON.parse(member[0]));
        return member[2];
    }

    /**
     * @param {object} ast Parsed tree.
     * @param {{indent?: number|string, sortKeys?: boolean}} opts indent: 0 (minify), 2, 4 or '\t'.
     */
    function stringify(ast, opts) {
        var o = opts || {};
        var unit = o.indent === '\t' ? '\t' : new Array((+o.indent || 0) + 1).join(' ');
        var sortKeys = !!o.sortKeys;
        var out = [];

        function members(node) {
            if (!sortKeys) return node.m;
            return node.m.map(function (m, idx) { return { m: m, idx: idx }; })
                .sort(function (a, b) {
                    var ka = keyOf(a.m);
                    var kb = keyOf(b.m);
                    return ka < kb ? -1 : ka > kb ? 1 : a.idx - b.idx;
                })
                .map(function (x) { return x.m; });
        }
        function write(node, pad) {
            if (node.t === 'o' || node.t === 'a') {
                var isObj = node.t === 'o';
                var list = isObj ? members(node) : node.v;
                var open = isObj ? '{' : '[';
                var close = isObj ? '}' : ']';
                if (!list.length) { out.push(open + close); return; }
                var inner = pad + unit;
                out.push(open);
                for (var k = 0; k < list.length; k++) {
                    if (k) out.push(',');
                    if (unit) out.push('\n' + inner);
                    if (isObj) {
                        out.push(list[k][0] + (unit ? ': ' : ':'));
                        write(list[k][1], inner);
                    } else {
                        write(list[k], inner);
                    }
                }
                if (unit) out.push('\n' + pad);
                out.push(close);
                return;
            }
            out.push(node.raw);
        }
        write(ast, '');
        return out.join('');
    }

    function locate(text, item) {
        var pos = position(text, item.offset || 0);
        var copy = {};
        for (var k in item) if (Object.prototype.hasOwnProperty.call(item, k)) copy[k] = item[k];
        copy.line = pos.line;
        copy.column = pos.column;
        return copy;
    }

    /** Parse and print in one go; positions resolved to line / column. */
    function format(text, opts) {
        var result = parse(text);
        var warnings = result.warnings.map(function (w) { return locate(text, w); });
        if (!result.ok) return { ok: false, error: locate(text, result.error), warnings: warnings };
        return { ok: true, output: stringify(result.ast, opts), warnings: warnings, stats: result.stats };
    }

    /** The error line (trimmed to about 120 characters around the column) and a caret under the column. */
    function excerpt(text, line, column) {
        var lines = String(text).split('\n');
        var chars = Array.from((lines[line - 1] || '').replace(/\r$/, ''));
        var start = Math.max(0, column - 61);
        var slice = chars.slice(start, start + 120);
        var prefix = start > 0 ? '…' : '';
        var caretAt = column - 1 - start + prefix.length;
        var shown = (prefix + slice.join('')).replace(/\t/g, ' ');
        return { text: shown, caret: new Array(Math.max(0, caretAt) + 1).join(' ') + '^' };
    }

    return { MAX_DEPTH: MAX_DEPTH, parse: parse, stringify: stringify, format: format, position: position, excerpt: excerpt };
}));
