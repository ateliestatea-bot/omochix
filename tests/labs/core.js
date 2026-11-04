#!/usr/bin/env node
/**
 * OmochiX Labs: unit tests for the pure browser modules in
 * wp-theme/omochix-theme/assets/js/labs/core/ (JSON, diff, robots.txt,
 * token estimate, cost, analytics events, text helpers) plus a source scan
 * of every Labs script for HTML injection and network APIs.
 * Run: node tests/labs/core.js   (Node built-ins only, no network)
 */
'use strict';

var fs = require('fs');
var path = require('path');

var root = path.resolve(__dirname, '..', '..');
var dir = path.join(root, 'wp-theme/omochix-theme/assets/js/labs');
var Text = require(path.join(dir, 'core/text.js'));
var Json = require(path.join(dir, 'core/json.js'));
var Diff = require(path.join(dir, 'core/diff.js'));
var Robots = require(path.join(dir, 'core/robots.js'));
var Tokens = require(path.join(dir, 'core/tokens.js'));
var Cost = require(path.join(dir, 'core/cost.js'));
var Events = require(path.join(dir, 'core/events.js'));
var Ops = require(path.join(dir, 'core/ops.js'));

var pass = 0;
var fail = 0;
function check(name, cond) {
    if (cond) { pass++; console.log('PASS ' + name); }
    else { fail++; console.log('FAIL ' + name); }
}
function eq(name, actual, expected) {
    var ok = JSON.stringify(actual) === JSON.stringify(expected);
    if (!ok) console.log('  expected ' + JSON.stringify(expected) + '\n  actual   ' + JSON.stringify(actual));
    check(name, ok);
}

// ------------------------------------------------------------------ text
eq('text: byteLength ascii / japanese / emoji', [Text.byteLength('abc'), Text.byteLength('日本'), Text.byteLength('😀')], [3, 6, 4]);
eq('text: sizeBucket', [Text.sizeBucket(10), Text.sizeBucket(2048), Text.sizeBucket(200 * 1024), Text.sizeBucket(2 * 1024 * 1024)], ['xs', 's', 'm', 'l']);
eq('text: normalizeSearch folds width, case and katakana', Text.normalizeSearch(' ＪＳＯＮ　ツール '), 'json つーる');
eq('text: splitLines handles CRLF, CR and a trailing newline', Text.splitLines('a\r\nb\rc\n'), ['a', 'b', 'c']);
eq('text: splitLines of empty text', Text.splitLines(''), []);
eq('text: lineEnding', [Text.lineEnding('a\nb'), Text.lineEnding('a\r\nb'), Text.lineEnding('a\r\nb\nc'), Text.lineEnding('a')], ['LF', 'CRLF', 'mixed', 'none']);
eq('text: daysBetween across a month', Text.daysBetween('2026-11-04', '2026-12-04'), 30);
eq('text: isDate rejects impossible dates', [Text.isDate('2026-02-29'), Text.isDate('2028-02-29'), Text.isDate('2026-1-1')], [false, true, false]);

// ------------------------------------------------------------------ json
var f = Json.format('{"b":1,"a":[true,null,{"c":"x"}]}', { indent: 2 });
eq('json: format with 2 spaces', f.output, '{\n  "b": 1,\n  "a": [\n    true,\n    null,\n    {\n      "c": "x"\n    }\n  ]\n}');
eq('json: minify', Json.format('{ "a" : [ 1 , 2 ] ,\n "b":{} }', { indent: 0 }).output, '{"a":[1,2],"b":{}}');
eq('json: tab indent', Json.format('[1]', { indent: '\t' }).output, '[\n\t1\n]');
eq('json: sort keys recursively, stable for equal keys', Json.format('{"b":{"z":1,"y":2},"a":0}', { indent: 0, sortKeys: true }).output, '{"a":0,"b":{"y":2,"z":1}}');
eq('json: empty containers stay compact', Json.format('{"a":[],"b":{}}', { indent: 2 }).output, '{\n  "a": [],\n  "b": {}\n}');
eq('json: scalar root', Json.format(' "x" ', { indent: 2 }).output, '"x"');
var big = Json.format('{"id":12345678901234567890,"n":-9007199254740993,"ok":9007199254740991,"f":1.5e300}', { indent: 0 });
check('json: big integers kept as written', big.ok && big.output === '{"id":12345678901234567890,"n":-9007199254740993,"ok":9007199254740991,"f":1.5e300}');
eq('json: big integer warnings (two, not the safe max or a float)', big.warnings.map(function (w) { return w.code; }), ['big_integer', 'big_integer']);
var dec = Json.format('[0.1, 3.14159265358979323846, 1.000000000000000000, 12345678901234567.5e3]', { indent: 0 });
eq('json: decimals over 17 significant digits are kept and warned (trailing zeros do not count)', [dec.output, dec.warnings.map(function (w) { return w.code; })], ['[0.1,3.14159265358979323846,1.000000000000000000,12345678901234567.5e3]', ['long_decimal', 'long_decimal']]);
var dup = Json.format('{"a":1,\n"a":2}', { indent: 0 });
check('json: duplicate key warning with position', dup.ok && dup.warnings[0].code === 'duplicate_key' && dup.warnings[0].line === 2 && dup.warnings[0].column === 1);
var bom = Json.format('﻿{"a":1}', { indent: 0 });
check('json: BOM removed with a warning', bom.ok && bom.output === '{"a":1}' && bom.warnings[0].code === 'bom');
eq('json: string escapes and unicode preserved', Json.format('["\\u00e9\\n\\"", "日本😀"]', { indent: 0 }).output, '["\\u00e9\\n\\"","日本😀"]');
function err(text) { var r = Json.format(text, { indent: 2 }); return r.ok ? null : [r.error.code, r.error.line, r.error.column]; }
eq('json error: trailing comma points at the comma', err('{\n  "a": 1,\n}'), ['trailing_comma', 2, 9]);
eq('json error: trailing comma in array', err('[1,2,]'), ['trailing_comma', 1, 5]);
eq('json error: unquoted key', err('{name: 1}'), ['unquoted_key', 1, 2]);
eq('json error: single quotes', err("{'a': 1}"), ['single_quote', 1, 2]);
eq('json error: comment', err('{"a": 1 // x\n}'), ['comment', 1, 9]);
eq('json error: unclosed string points at its start', err('{"a": "abc'), ['unclosed_string', 1, 7]);
eq('json error: raw newline in string', err('["a\nb"]'), ['control_char', 1, 4]);
eq('json error: empty input', err('   '), ['empty', 1, 4]);
eq('json error: trailing data', err('{} {}'), ['trailing_data', 1, 4]);
eq('json error: leading zero', err('[01]'), ['leading_zero', 1, 2]);
eq('json error: bad escape', err('["\\x"]'), ['bad_escape', 1, 3]);
eq('json error: NaN', err('[NaN]'), ['literal', 1, 2]);
eq('json error: Python True', err('[True]'), ['literal_case', 1, 2]);
eq('json error: missing colon', err('{"a" 1}'), ['expected_colon', 1, 6]);
eq('json error: missing comma', err('[1 2]'), ['expected_comma', 1, 4]);
eq('json error: unclosed object', err('{"a":1'), ['unclosed_object', 1, 1]);
eq('json error: unclosed array', err('[1,2'), ['unclosed_array', 1, 1]);
eq('json error: number without digits after dot', err('[1.]'), ['bad_number', 1, 2]);
eq('json error: column counts characters, not UTF-16 units', err('["😀", x]'), ['unexpected', 1, 7]);
var deep = new Array(Json.MAX_DEPTH + 1).join('[') + new Array(Json.MAX_DEPTH + 1).join(']');
check('json: 512 levels are accepted', Json.format(deep, { indent: 0 }).ok);
eq('json error: 513 levels are rejected', err('[' + deep + ']'), ['too_deep', 1, 513]);
var ex = Json.excerpt('{\n  "a": 1,\n}', 2, 9);
check('json: excerpt caret under the column', ex.text === '  "a": 1,' && ex.caret === '        ^');
check('json: every error message is Japanese text', ['{', '[1,]', "{'a'}", '{a}', '"', '01', '{} x'].every(function (t) { var r = Json.format(t, {}); return !r.ok && /[぀-ヿ一-鿿]/.test(r.error.message); }));
var fuzzOk = true;
for (var fz = 0; fz < 300; fz++) {
    var value = randomJson(0);
    var text = JSON.stringify(value);
    var r1 = Json.format(text, { indent: 2 });
    if (!r1.ok || JSON.stringify(JSON.parse(r1.output)) !== text) { fuzzOk = false; break; }
}
check('json: 300 random documents round-trip through format', fuzzOk);
function randomJson(depth) {
    var k = Math.floor(Math.random() * (depth > 3 ? 4 : 6));
    if (k === 0) return Math.random() < 0.5 ? Math.floor(Math.random() * 1e6) : Math.random() * 100;
    if (k === 1) return Math.random().toString(36).slice(2) + (Math.random() < 0.3 ? '日本"\\\n' : '');
    if (k === 2) return Math.random() < 0.5;
    if (k === 3) return null;
    if (k === 4) { var a = []; for (var i = 0; i < 4; i++) a.push(randomJson(depth + 1)); return a; }
    var o = {}; for (var j = 0; j < 4; j++) o['k' + j + Math.random().toString(36).slice(2, 4)] = randomJson(depth + 1); return o;
}

// ------------------------------------------------------------------ diff
function apply(a, b, ops) {
    var out = [];
    ops.forEach(function (op) { if (op.type === 'equal') out.push(a[op.a]); else if (op.type === 'insert') out.push(b[op.b]); });
    return out;
}
var seqOk = true;
var minimalOk = true;
for (var t = 0; t < 400; t++) {
    var a = randomSeq();
    var b = randomSeq();
    var res = Diff.sequences(a, b);
    if (JSON.stringify(apply(a, b, res.ops)) !== JSON.stringify(b)) { seqOk = false; break; }
    var dels = res.ops.filter(function (o) { return o.type === 'delete'; }).length;
    var lcs = lcsLength(a, b);
    if (dels !== a.length - lcs) { minimalOk = false; break; }
}
check('diff: 400 random edit scripts turn A into B', seqOk);
check('diff: edit scripts are minimal (deletions = |A| - LCS)', minimalOk);
function randomSeq() { var n = Math.floor(Math.random() * 30); var s = []; for (var i = 0; i < n; i++) s.push(Math.floor(Math.random() * 5)); return s; }
function lcsLength(a, b) {
    var prev = new Array(b.length + 1).fill(0);
    for (var i = 1; i <= a.length; i++) {
        var cur = [0];
        for (var j = 1; j <= b.length; j++) cur[j] = a[i - 1] === b[j - 1] ? prev[j - 1] + 1 : Math.max(prev[j], cur[j - 1]);
        prev = cur;
    }
    return prev[b.length];
}
var d1 = Diff.lines('a\nb\nc\n', 'a\nB\nc\nd\n');
eq('diff: lines stats', [d1.stats.added, d1.stats.removed, d1.stats.unchanged, d1.stats.same], [2, 1, 2, false]);
eq('diff: rows in order with line numbers', d1.rows.map(function (r) { return r.type[0] + r.oldNo + ':' + r.newNo; }), ['e1:1', 'd2:0', 'i0:2', 'e3:3', 'i0:4']);
check('diff: paired lines get inline segments', d1.rows[1].pair === 2 && d1.rows[1].segments[0].t === 'delete' && d1.rows[2].segments[0].t === 'insert');
eq('diff: unified output', Diff.unified(d1.rows, { oldName: 'old', newName: 'new' }), '--- old\n+++ new\n@@ -1,3 +1,4 @@\n a\n-b\n+B\n c\n+d\n');
eq('diff: unified for identical input has no hunks', Diff.unified(Diff.lines('x', 'x').rows, {}), '--- before\n+++ after\n');
var d2 = Diff.lines('a\r\nb\r\n', 'a\nb\n');
check('diff: CRLF vs LF is not a difference but is reported', d2.stats.same && d2.lineEndings.old === 'CRLF' && d2.lineEndings.new === 'LF');
check('diff: ignoreWhitespace', Diff.lines('if (a)  {\n  x\n', 'if (a) {\nx\n', { ignoreWhitespace: true }).stats.same && !Diff.lines('if (a)  {', 'if (a) {').stats.same);
check('diff: ignoreCase', Diff.lines('Labs', 'labs', { ignoreCase: true }).stats.same && !Diff.lines('Labs', 'labs').stats.same);
var ja = Diff.inline('今日は晴れです', '今日は雨です');
eq('diff: Japanese inline diff is per character', [ja.old, ja.new], [[{ t: 'equal', s: '今日は' }, { t: 'delete', s: '晴れ' }, { t: 'equal', s: 'です' }], [{ t: 'equal', s: '今日は' }, { t: 'insert', s: '雨' }, { t: 'equal', s: 'です' }]]);
var en = Diff.inline('return "Hello, " + name;', 'return greeting + ", " + name;');
check('diff: English inline diff is per word', en.old.some(function (s) { return s.t === 'delete' && s.s.indexOf('Hello') !== -1; }) && en.new.some(function (s) { return s.t === 'insert' && s.s.indexOf('greeting') !== -1; }));
var empty = Diff.lines('', 'a\nb');
eq('diff: empty old side', [empty.stats.added, empty.stats.removed], [2, 0]);
eq('diff: unified when old is empty', Diff.unified(empty.rows, {}), '--- before\n+++ after\n@@ -0,0 +1,2 @@\n+a\n+b\n');
var approx = Diff.lines('1\n2\n3\n4\n5\n6', '6\n5\n4\n3\n2\n1', { maxD: 2 });
check('diff: falls back to approximate block when D exceeds the limit', approx.approximate && approx.stats.removed === 6 && approx.stats.added === 6);
var bigA = []; var bigB = [];
for (var bl = 0; bl < 20000; bl++) { bigA.push('line ' + bl); bigB.push(bl % 1000 === 0 ? 'changed ' + bl : 'line ' + bl); }
var t0 = Date.now();
var bigRes = Diff.lines(bigA.join('\n'), bigB.join('\n'));
check('diff: 20,000 lines with 20 changes in under 2 s', bigRes.stats.removed === 20 && bigRes.stats.added === 20 && Date.now() - t0 < 2000);

function timed(fn) { var t = Date.now(); var r = fn(); return { r: r, ms: Date.now() - t }; }
var distinctA = []; var distinctB = [];
for (var dl = 0; dl < 20000; dl++) { distinctA.push('a' + dl); distinctB.push('b' + dl); }
var worst = timed(function () { return Diff.lines(distinctA.join('\n'), distinctB.join('\n')); });
check('diff extreme: 20,000 vs 20,000 completely different lines fall back quickly (' + worst.ms + ' ms)', worst.r.approximate && worst.r.stats.removed === 20000 && worst.r.stats.added === 20000 && worst.ms < 3000);
var altA = []; var altB = [];
for (var al = 0; al < 20000; al++) { altA.push(al % 2 ? 'x' : 'y'); altB.push(al % 2 ? 'y' : 'x'); }
var alt = timed(function () { return Diff.lines(altA.join('\n'), altB.join('\n')); });
check('diff extreme: 20,000 alternating lines finish (' + alt.ms + ' ms)', alt.r.stats.unchanged >= 19998 && alt.ms < 3000);
var longLine = timed(function () { return Diff.lines(new Array(1024 * 1024).join('a'), new Array(1024 * 1024).join('a') + 'b'); });
check('diff extreme: one 1MB line skips the inline pass (' + longLine.ms + ' ms)', longLine.r.stats.removed === 1 && !longLine.r.rows[0].segments && longLine.ms < 2000);
var surrogate = Diff.inline('😀a', '😀b');
eq('diff extreme: emoji are not split into surrogate halves', surrogate.old.map(function (s) { return s.s; }), ['😀', 'a']);

// ---------------------------------------------------------------- robots
var built = Robots.build({ groups: [{ agents: ['*'], rules: [{ type: 'disallow', path: '/wp-admin/' }, { type: 'allow', path: '/wp-admin/admin-ajax.php' }, { type: 'disallow', path: '/wp-admin/' }] }, { agents: ['GPTBot', 'CCBot'], rules: [{ type: 'disallow', path: '/' }], comment: 'AI' }], sitemaps: ['https://example.com/sitemap.xml', '', 'https://example.com/sitemap.xml'] });
eq('robots: build groups, dedupe rules and sitemaps', built.text, 'User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\n# AI\nUser-agent: GPTBot\nUser-agent: CCBot\nDisallow: /\n\nSitemap: https://example.com/sitemap.xml\n');
eq('robots: no warnings for a normal file', built.warnings, []);
eq('robots: a group without rules allows everything', Robots.build({ groups: [{ agents: ['*'], rules: [] }] }).text, 'User-agent: *\nAllow: /\n');
var warn = Robots.build({ groups: [{ agents: ['*'], rules: [{ type: 'disallow', path: '/' }, { type: 'disallow', path: 'no-slash' }, { type: 'allow', path: '/a b' }] }], sitemaps: ['/sitemap.xml', 'javascript:alert(1)'] });
eq('robots: warnings for block-all, bad paths and bad sitemaps', warn.warnings.map(function (w) { return w.code; }).sort(), ['bad_path', 'bad_path', 'bad_sitemap', 'bad_sitemap', 'block_all']);
check('robots: rejected lines are not written', warn.text === 'User-agent: *\nDisallow: /\n');
check('robots: comment newlines cannot inject directives', Robots.build({ groups: [{ agents: ['*'], rules: [], comment: 'x\nDisallow: /' }] }).text.indexOf('\nDisallow: /') === -1);
check('robots: agent tokens are validated', Robots.build({ groups: [{ agents: ['Bad Agent\nDisallow: /'], rules: [] }] }).text === '');
var parsed = Robots.parse('# comment\nUser-agent: *\nDisallow: /private/\nAllow: /private/public\nDisallow: /*.pdf$\nDisallow:\n\nUser-agent: GPTBot\nuser-agent: CCBot\nDisallow: /\nCrawl-delay: 10\nSitemap: https://example.com/s.xml\n');
eq('robots: parse groups (consecutive user-agents share a group)', parsed.groups.map(function (g) { return g.agents; }), [['*'], ['gptbot', 'ccbot']]);
eq('robots: parse sitemaps and ignored lines', [parsed.sitemaps, parsed.ignored], [['https://example.com/s.xml'], [11]]);
function verdict(agent, p) { var r = Robots.test(parsed, agent, p); return [r.allowed, r.rule ? r.rule.line : 0]; }
eq('robots test: disallowed prefix', verdict('Googlebot', '/private/x'), [false, 3]);
eq('robots test: longer allow wins', verdict('Googlebot', '/private/public/page'), [true, 4]);
eq('robots test: $ anchors the end', [verdict('Googlebot', '/a/file.pdf'), verdict('Googlebot', '/a/file.pdf?x=1')], [[false, 5], [true, 0]]);
eq('robots test: specific group replaces *', verdict('gptbot', '/anything'), [false, 10]);
eq('robots test: case-insensitive agent match', verdict('CCBOT', '/'), [false, 10]);
eq('robots test: unknown agent uses *', verdict('Bingbot', '/public'), [true, 0]);
eq('robots test: /robots.txt is always allowed', verdict('GPTBot', '/robots.txt'), [true, 0]);
eq('robots test: full URL is reduced to its path', Robots.test(parsed, 'Googlebot', 'https://example.com/private/x?y#z').path, '/private/x?y');
var tie = Robots.parse('User-agent: *\nDisallow: /page\nAllow: /page\n');
eq('robots test: equal length, Allow wins', Robots.test(tie, 'x', '/page').allowed, true);
var wild = Robots.parse('User-agent: *\nDisallow: /*?\nAllow: /*?lang=\n');
eq('robots test: wildcard and longest match', [Robots.test(wild, 'x', '/a?b=1').allowed, Robots.test(wild, 'x', '/a?lang=ja').allowed, Robots.test(wild, 'x', '/a').allowed], [false, true, true]);
check('robots test: regex characters in paths are literal', Robots.test(Robots.parse('User-agent: *\nDisallow: /a.b(c)+\n'), 'x', '/aXb(c)+').allowed && !Robots.test(Robots.parse('User-agent: *\nDisallow: /a.b(c)+\n'), 'x', '/a.b(c)+').allowed);
check('robots test: no robots.txt allows everything', Robots.test(Robots.parse(''), 'x', '/').allowed);

eq('robots: canonical form (RFC 9309 percent-encoding)', [Robots.canonical('/café'), Robots.canonical('/caf%c3%a9'), Robots.canonical('/%41%2f%7e')], ['/caf%C3%A9', '/caf%C3%A9', '/A%2F~']);
var enc = Robots.parse('User-agent: *\nDisallow: /café/\nDisallow: /%7Euser/\n');
eq('robots test: encoded and raw non-ASCII paths match each other', [Robots.test(enc, 'x', '/caf%C3%A9/menu').allowed, Robots.test(enc, 'x', '/café/menu').allowed, Robots.test(enc, 'x', '/~user/a').allowed, Robots.test(enc, 'x', '/%2Fuser/').allowed], [false, false, false, true]);
eq('robots: octet length counts encoded bytes (longest match)', Robots.test(Robots.parse('User-agent: *\nAllow: /é\nDisallow: /%C3%A9x\n'), 'x', '/éx').allowed, false);
check('robots: a leading BOM does not hide the first group', !Robots.test(Robots.parse('﻿User-agent: *\nDisallow: /\n'), 'x', '/a').allowed);

// ---------------------------------------------------------------- tokens
eq('tokens: empty', Tokens.estimate(''), { chars: 0, tokens: 0, low: 0, high: 0, counts: { cjk: 0, word: 0, digits: 0, symbol: 0, newline: 0, other: 0, space: 0 } });
eq('tokens: Japanese is about one token per character', Tokens.estimate('今日は良い天気です').tokens, 9);
check('tokens: English words', Tokens.estimate('the quick brown fox jumps').tokens === 7);
var mix = Tokens.estimate('こんにちは、world! 123\n😀');
check('tokens: low <= typical <= high', mix.low <= mix.tokens && mix.tokens <= mix.high && mix.chars === 18);

// ------------------------------------------------------------------ cost
var model = { id: 'm', provider: 'p', label: 'M', input: 2, output: 10, cached_input: 0.2, long_context: { threshold_tokens: 1000, input: 4, output: 20, cached_input: 0.4 }, source_url: 'https://platform.claude.com/x', verified_at: '2026-11-04' };
var c1 = Cost.compute(model, { input: 500, output: 100, cached: 200, requestsPerDay: 10, days: 30 });
check('cost: base tier arithmetic', c1.tier === 'base' && Math.abs(c1.perRequest - (500 * 2 + 200 * 0.2 + 100 * 10) / 1e6) < 1e-12 && Math.abs(c1.total - c1.perRequest * 300) < 1e-12);
var c2 = Cost.compute(model, { input: 900, output: 100, cached: 200, requestsPerDay: 1, days: 1 });
check('cost: long-context tier when input + cached exceeds the threshold', c2.tier === 'long' && Math.abs(c2.perRequest - (900 * 4 + 200 * 0.4 + 100 * 20) / 1e6) < 1e-12);
var c3 = Cost.compute({ id: 'n', input: 1, output: 2, cached_input: null, long_context: null }, { input: 0, output: 0, cached: 1000000, requestsPerDay: 1, days: 1 });
check('cost: unlisted cache price falls back to the input price with a note', c3.perRequest === 1 && c3.notes.indexOf('cached_not_listed') !== -1);
var c4 = Cost.compute(model, { input: -5, output: 'abc', cached: 1e12, requestsPerDay: 2.7, days: 1e9 });
eq('cost: inputs are clamped to non-negative integers', c4.usage, { input: 0, output: 0, cached: 1e9, requestsPerDay: 2, days: 3660 });
eq('cost: formatUSD', [Cost.formatUSD(0), Cost.formatUSD(0.000123), Cost.formatUSD(0.5), Cost.formatUSD(12.3456), Cost.formatUSD(1234.5)], ['$0', '$0.00012', '$0.5', '$12.35', '$1,235']);
eq('cost: formatJPY needs a valid rate', [Cost.formatJPY(10, 150), Cost.formatJPY(10, ''), Cost.formatJPY(10, -1), Cost.formatJPY(0.001, 150)], ['1,500円', '', '', '0.15円']);
var base = { id: 'x', provider: 'p', label: 'X', input: 1, output: 2, cached_input: null, long_context: null, source_url: 'https://developers.openai.com/api/docs/pricing', verified_at: '2026-11-01', status: 'verified' };
function variant(over) { var m = JSON.parse(JSON.stringify(base)); Object.keys(over).forEach(function (k) { m[k] = over[k]; }); return m; }
eq('cost: hiddenReason', [
    Cost.hiddenReason(base, '2026-11-04', 90),
    Cost.hiddenReason(variant({ status: 'draft' }), '2026-11-04', 90),
    Cost.hiddenReason(variant({ source_url: 'https://example.com/prices' }), '2026-11-04', 90),
    Cost.hiddenReason(variant({ source_url: 'http://developers.openai.com/' }), '2026-11-04', 90),
    Cost.hiddenReason(variant({ source_url: 'https://developers.openai.com.evil.test/' }), '2026-11-04', 90),
    Cost.hiddenReason(variant({ verified_at: '2026-11-06' }), '2026-11-04', 90),
    Cost.hiddenReason(variant({ verified_at: '2026-11-05' }), '2026-11-04', 90),
    Cost.hiddenReason(base, '2027-01-31', 90),
    Cost.hiddenReason(base, '2027-01-30', 90),
    Cost.hiddenReason(variant({ input: -1 }), '2026-11-04', 90),
    Cost.hiddenReason(variant({ input: '1' }), '2026-11-04', 90),
    Cost.hiddenReason(variant({ valid_until: '2026-11-03' }), '2026-11-04', 90),
    Cost.hiddenReason(variant({ valid_from: '2026-11-05' }), '2026-11-04', 90),
    Cost.hiddenReason(variant({ valid_from: '2026-12-01', valid_until: '2026-11-30' }), '2026-11-04', 90)
], ['', 'unverified', 'source', 'source', 'source', 'unverified', '', 'stale', '', 'invalid', 'invalid', 'expired', 'upcoming', 'invalid']);
var rows = [variant({ id: 'a', valid_until: '2026-11-30' }), variant({ id: 'a', valid_from: '2026-12-01', input: 9 }), variant({ id: 'b' }), variant({ id: 'c' }), variant({ id: 'c' })];
eq('cost: active picks the row for the day and drops ambiguous duplicates', [Cost.active(rows, '2026-11-20', 90).map(function (m) { return m.id + m.input; }), Cost.active(rows, '2026-12-02', 90).map(function (m) { return m.id + m.input; })], [['a1', 'b1'], ['a9', 'b1']]);
eq('cost: active drops rows older than max age on the browser date', Cost.active([base], '2027-02-15', 90), []);

// ---------------------------------------------------------------- events
eq('events: allowed payload passes', Events.sanitize('labs_tool_run', { tool_slug: 'json-formatter', action: 'format', size_bucket: 's' }), { tool_slug: 'json-formatter', action: 'format', size_bucket: 's' });
eq('events: unknown keys are dropped', Events.sanitize('labs_tool_copy', { tool_slug: 'text-diff', target: 'diff', text: 'secret', value: 'x' }), { tool_slug: 'text-diff', target: 'diff' });
eq('events: unknown events are not sent', Events.sanitize('labs_input', { tool_slug: 'json-formatter' }), null);
eq('events: required tool_slug', Events.sanitize('labs_tool_view', { tool_category: 'dev' }), null);
eq('events: free text in vocab fields is dropped', Events.sanitize('labs_tool_error', { tool_slug: 'json-formatter', action: 'format', error_code: 'SyntaxError: Unexpected token { at "password"' }), { tool_slug: 'json-formatter', action: 'format' });
eq('events: URLs and emails are not slugs', [Events.sanitize('labs_related_click', { tool_slug: 'index', link_type: 'labs', link_slug: 'https://example.com/x' }), Events.sanitize('labs_tool_view', { tool_slug: 'me@example.com' })], [{ tool_slug: 'index', link_type: 'labs' }, null]);
var sent = [];
var payload = Events.send('labs_tool_success', { tool_slug: 'robots-txt-generator', action: 'generate', duration_bucket: 'fast', input: 'User-agent: *' }, function () { sent.push(Array.prototype.slice.call(arguments)); });
eq('events: send calls gtag with the clean payload', sent, [['event', 'labs_tool_success', { tool_slug: 'robots-txt-generator', action: 'generate', duration_bucket: 'fast' }]]);
check('events: nothing is sent without gtag', Events.send('labs_tool_view', { tool_slug: 'json-formatter' }, undefined) === null && payload !== null);
var leak = false;
var samples = ['{"password":"x"}', 'User-agent: *', '今日は', 'https://example.com', 'a b', 'A', '1 2'];
Object.keys(Events.EVENTS).forEach(function (name) {
    Events.EVENTS[name].forEach(function (key) {
        samples.forEach(function (s) {
            var p = { tool_slug: 'json-formatter' };
            p[key] = s;
            var clean = Events.sanitize(name, p);
            if (clean && JSON.stringify(clean).indexOf(s) !== -1) leak = true;
        });
    });
});
check('events: no typed-looking value survives in any field of any event', !leak);
eq('events: exactly the six specified events', Object.keys(Events.EVENTS).sort(), ['labs_related_click', 'labs_tool_copy', 'labs_tool_error', 'labs_tool_run', 'labs_tool_success', 'labs_tool_view']);

// ------------------------------------------------------------------- ops
check('ops: json.format through the registry', Ops.run('json.format', { text: '[1]', indent: 0 }).output === '[1]');
check('ops: diff.lines adds unified text', Ops.run('diff.lines', { oldText: 'a', newText: 'b', oldName: 'x', newName: 'y' }).unified.indexOf('--- x') === 0);
check('ops: unknown op throws', (function () { try { Ops.run('eval', {}); return false; } catch (e) { return true; } }()));

// ----------------------------------------------------------- source scan
function walk(d) {
    return fs.readdirSync(d).reduce(function (acc, name) {
        var p = path.join(d, name);
        return acc.concat(fs.statSync(p).isDirectory() ? walk(p) : (/\.js$/.test(name) ? [p] : []));
    }, []);
}
var files = walk(dir);
var banned = [/\.innerHTML\b/, /\.outerHTML\b/, /insertAdjacentHTML/, /document\.write/, /\beval\s*\(/, /new Function\s*\(/, /\bfetch\s*\(/, /XMLHttpRequest/, /sendBeacon/, /WebSocket/, /EventSource/, /\bimport\s*\(/, /localStorage/, /sessionStorage/, /indexedDB/];
var offenders = [];
files.forEach(function (file) {
    var src = fs.readFileSync(file, 'utf8').replace(/^\s*(\/\/|\*|\/\*).*$/gm, '');
    banned.forEach(function (re) { if (re.test(src)) offenders.push(path.relative(root, file) + ' ' + re); });
});
eq('source: no HTML injection, eval, storage or network APIs in Labs scripts', offenders, []);
check('source: scanned all Labs scripts', files.length >= 15);

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
