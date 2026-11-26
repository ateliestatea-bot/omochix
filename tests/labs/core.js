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
var Studio = require(path.join(dir, 'core/codestudio.js'));

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
var base = { id: 'x', provider: 'p', label: 'X', input: 1, output: 2, cached_input: null, long_context: null, source_url: 'https://developers.openai.com/api/docs/pricing', verified_at: '2026-11-01', status: 'verified', caveats: ['known_caveat'], approval: { status: 'pending' } };
var LABELS = { known_caveat: 'A caveat' };
function variant(over) { var m = JSON.parse(JSON.stringify(base)); Object.keys(over).forEach(function (k) { m[k] = over[k]; }); return m; }
eq('cost: hiddenReason', [
    Cost.hiddenReason(base, '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ status: 'draft' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ source_url: 'https://example.com/prices' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ source_url: 'http://developers.openai.com/' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ source_url: 'https://developers.openai.com.evil.test/' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ verified_at: '2026-11-06' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ verified_at: '2026-11-05' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(base, '2027-01-31', 90, LABELS),
    Cost.hiddenReason(base, '2027-01-30', 90, LABELS),
    Cost.hiddenReason(variant({ input: -1 }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ input: '1' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ valid_until: '2026-11-03' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ valid_from: '2026-11-05' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ valid_from: '2026-12-01', valid_until: '2026-11-30' }), '2026-11-04', 90, LABELS)
], ['', 'unverified', 'source', 'source', 'source', 'unverified', '', 'stale', '', 'invalid', 'invalid', 'expired', 'upcoming', 'invalid']);
eq('cost: hiddenReason for caveats and approval records', [
    Cost.hiddenReason(variant({ caveats: ['unknown_code'] }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ caveats: 'known_caveat' }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ approval: null }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ approval: { status: 'ok' } }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ approval: { status: 'held' } }), '2026-11-04', 90, LABELS),
    Cost.hiddenReason(variant({ approval: { status: 'held', reason: 'unconfirmed' } }), '2026-11-04', 90, LABELS)
], ['invalid', 'invalid', 'invalid', 'invalid', 'invalid', '']);
var good = { status: 'approved', reviewer: 'R', reviewed_at: '2026-11-05', acknowledged: ['known_caveat'] };
function withApproval(a) { return variant({ approval: a }); }
eq('cost: approvalState', [
    Cost.approvalState(withApproval(good)),
    Cost.approvalState(withApproval({ status: 'pending' })),
    Cost.approvalState(withApproval({ status: 'held', reason: 'x' })),
    Cost.approvalState(withApproval(Object.assign({}, good, { reviewer: ' ' }))),
    Cost.approvalState(withApproval(Object.assign({}, good, { reviewed_at: 'soon' }))),
    Cost.approvalState(withApproval(Object.assign({}, good, { reviewed_at: '2026-10-31' }))),
    Cost.approvalState(withApproval(Object.assign({}, good, { acknowledged: [] }))),
    Cost.approvalState(variant({ caveats: [], approval: Object.assign({}, good, { acknowledged: [] }) }))
], ['approved', 'pending', 'held', 'pending', 'pending', 'pending', 'pending', 'approved']);
var gview = { models: [{ id: 'a', approval: 'approved' }, { id: 'b', approval: 'pending' }, { id: 'c', approval: 'held' }], hidden: {} };
function ids(v) { return v.models.map(function (m) { return m.id; }); }
eq('cost: gate', [
    [ids(Cost.gate(gview, false, true)), Cost.gate(gview, false, true).withheld],
    [ids(Cost.gate(gview, true, true)), Cost.gate(gview, true, true).withheld, Cost.gate(gview, true, true).hidden],
    [ids(Cost.gate(gview, false, false)), Cost.gate(gview, false, false).withheld],
    [ids(Cost.gate({ models: [{ id: 'b', approval: 'pending' }], hidden: {} }, true, true)), Cost.gate({ models: [{ id: 'b', approval: 'pending' }], hidden: {} }, true, true).withheld]
], [[[], true], [['a'], false, { row_pending: 1, row_held: 1 }], [['a', 'b', 'c'], false], [[], true]]);
eq('cost: fileApproved', [Cost.fileApproved({ review: { status: 'approved', reviewer: 'R', reviewed_at: '2026-11-05' } }), Cost.fileApproved({ review: { status: 'pending' } }), Cost.fileApproved({ review: { status: 'approved', reviewer: '', reviewed_at: '2026-11-05' } }), Cost.fileApproved(null)], [true, false, false, false]);
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

// ----------------------------------------------------------- Code Studio
var pc = Studio.purifyConfig();
check('studio: script, style, frames, forms, svg and loaders are forbidden tags', ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'base', 'form', 'svg', 'math', 'template', 'audio', 'video'].every(function (t) { return pc.FORBID_TAGS.indexOf(t) !== -1 && pc.ALLOWED_TAGS.indexOf(t) === -1; }));
check('studio: no event attribute, srcset, srcdoc, action or target is allowed', pc.ALLOWED_ATTR.every(function (a) { return !/^on/.test(a); }) && ['srcset', 'srcdoc', 'action', 'formaction', 'target', 'ping'].every(function (a) { return pc.FORBID_ATTR.indexOf(a) !== -1 && pc.ALLOWED_ATTR.indexOf(a) === -1; }) && pc.ALLOW_DATA_ATTR === false && pc.ALLOW_UNKNOWN_PROTOCOLS === false);
check('studio: no href; URLs are small inline images only', pc.ALLOWED_ATTR.indexOf('href') === -1 && pc.FORBID_ATTR.indexOf('href') !== -1 && ['data:image/png;base64,AAAA', 'data:image/webp;base64,AA+/=='].every(function (u) { return pc.ALLOWED_URI_REGEXP.test(u); }) && ['#top', '#', 'https://example.com/', '//example.com/x', 'http://x', 'javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'data:text/html;base64,AAAA', 'data:image/svg+xml;base64,AAAA', '/wp-admin/', 'mailto:a@b.c', 'x', '#a b', '#"><script>'].every(function (u) { return !pc.ALLOWED_URI_REGEXP.test(u); }));
check('studio: purifyConfig returns fresh lists (a caller cannot widen the allowlist)', (function () { var a = Studio.purifyConfig(); a.ALLOWED_TAGS.push('script'); a.FORBID_TAGS.length = 0; var b = Studio.purifyConfig(); return b.ALLOWED_TAGS.indexOf('script') === -1 && b.FORBID_TAGS.indexOf('script') !== -1; }()));
function cssOut(css) { return Studio.sanitizeCss(css).css; }
var cssAttacks = [
    '</style><script>alert(1)</script>', '<!-- x -->', '@import url(https://evil.example/a.css);', '@import "https://evil.example/a.css";', '@IMPORT url(//evil.example);',
    'a{background:url(https://evil.example/x.png)}', 'a{background:URL( "//evil.example/x" )}', 'a{background:u\\72l(https://evil.example)}', 'a{background:ur\\l(https://evil.example)}',
    'a{background:\\75\\72\\6c(https://evil.example)}', 'a{background:image-set("https://evil.example/x.png" 1x)}', 'a{background:-webkit-image-set(url(https://evil.example/x) 1x)}',
    'a{background:image("https://evil.example/x.png")}', 'a{background:cross-fade(url(https://evil.example/a), url(b))}', '@font-face{font-family:x;src:url(https://evil.example/f.woff2)}',
    'a{width:expression(alert(1))}', 'a{-moz-binding:url(https://evil.example/x.xml#x)}', 'a{behavior:url(x.htc)}', 'a{background:url(javascript:alert(1))}',
    'a{background:url(data:text/html;base64,AAAA)}', 'a{background:url(data:image/svg+xml;base64,AAAA)}', 'a{background:url(https://evil.example/x', '@namespace url(http://www.w3.org/1999/xhtml);',
    'a{cursor:url(https://evil.example/c.cur),auto}', 'a{list-style:url("https://evil.example/l.png")}', 'a{content:url(https://evil.example/c)}', 'a{background:url(/wp-admin/admin-ajax.php?x)}',
    '@media screen{a{background:url(https://evil.example/m)}}', 'a{background:url(\nhttps://evil.example/n)}', '@im\\port url(https://evil.example);', '@charset "x";@import url(//evil.example);'
];
check('studio css: ' + cssAttacks.length + ' attack strings leave no markup, escape, @import, loader or remote url()', cssAttacks.every(function (c) {
    var o = cssOut(c);
    return !/[<\\]|-->/.test(o) && !/@import|@font-face|@namespace|@charset/i.test(o) && !/(?:image-set|image|cross-fade|element|paint|expression)\s*\(/i.test(o) && !/["']/.test(o) && !/https?:|ftp:|wss?:|file:/i.test(o) && !/-moz-binding|behavior\s*:|javascript\s*:/i.test(o)
        && (o.match(/url\([^)]*\)?/gi) || []).every(function (u) { return /^url\(\s*["']?data:image\/(?:png|gif|jpeg|webp);base64,/i.test(u); });   // what is left of an escape ("u72l(https://…)") is an unknown function: not a request
}));
check('studio css: sanitising is idempotent', cssAttacks.every(function (c) { var once = cssOut(c); return cssOut(once) === once; }));
var cssJoins = [
    '@im@import;port "//evil.example/a.css";', '@im@im@im@import;port;port;port "//evil.example/a.css";', '@im@charset;port "//evil.example/a.css";', '@imjavascript:port "//evil.example/a.css";',
    '@im<port "//evil.example/a.css";', '@import "//evil.example/a{.css";', '@import "//evil.example/a}.css";', 'a{background:uexpression(rl(//evil.example/x)}', 'a{background:ubehavior:rl(//evil.example/x)}',
    'a{background:u@import;rl(//evil.example/x)}', 'a{background:ur@namespace;l(//evil.example/x)}', 'a{background:im@import;age-set("//evil.example/x" 1x)}', '@font-@import;face{src:local(x)}'
];
check('studio css: text joined by a removal is sanitised again (' + cssJoins.length + ' strings)', cssJoins.every(function (c) {
    var o = cssOut(c);
    return !/@import|@font-face|url\s*\(|image-set\s*\(/i.test(o) && cssOut(o) === o;
}), cssJoins.map(cssOut));
check('studio css: text that keeps changing is dropped whole', (function () { var c = new Array(40).join('@im') + '@import;' + new Array(40).join('port;') + ' "//evil.example/a.css";a{color:red}'; var r = Studio.sanitizeCss(c); return r.css === '' && r.removed.indexOf('unstable') !== -1; }()));
check('studio purify: only src is checked as a URL; the plain attributes are not', (function () { var safe = pc.ADD_URI_SAFE_ATTR; return safe.indexOf('src') === -1 && pc.ALLOWED_ATTR.indexOf('cite') === -1 && ['type', 'colspan', 'rowspan', 'width', 'height', 'lang', 'start', 'min', 'max', 'rows', 'tabindex'].every(function (a) { return safe.indexOf(a) !== -1; }) && safe.length === pc.ALLOWED_ATTR.length - 1; }()));
check('studio purify: isImageUri accepts a small inline bitmap only', Studio.isImageUri('data:image/png;base64,AAAA') && Studio.isImageUri(' data:image/webp;base64,AA== ') && !Studio.isImageUri('data:image/svg+xml;base64,AAAA') && !Studio.isImageUri('data:image/svg+xml,%3Csvg%3E') && !Studio.isImageUri('data:text/html;base64,AAAA') && !Studio.isImageUri('//evil.example/x.png') && !Studio.isImageUri('x.png') && !Studio.isImageUri('data:image/png;base64,AA AA') && !Studio.isImageUri(null));
check('studio check dom: a pattern in a check is ignored (no RegExp from data)', Studio.checkDom({ querySelectorAll: function () { return [{ getAttribute: function () { return 'x'; } }]; } }, { type: 'attribute', selector: 'img', name: 'alt', pattern: '^y$' }) === true);
// What the sanitiser must never leave: IE "behavior" as a property name, "javascript:" where a URL can start (not inside
// a class / id / element name such as ".javascript:hover", which is a pseudo-class and loads nothing).
var DANGER_WORD = /(?:^|[^A-Za-z0-9_\-\u0080-\uFFFF])(?:-ms-)?behavior\s*:|(?:^|[^A-Za-z0-9_\-\u0080-\uFFFF.#])javascript\s*:/i;
// Fuzz (seeded, so a failure is reproducible): pieces that a removal can join back together.
check('studio css: 20,000 random joins of removable pieces leave nothing that loads or escapes', (function () {
    var seed = 20261120;
    function rnd(n) { seed = (seed * 1103515245 + 12345) & 0x7fffffff; return seed % n; }
    var pieces = ['@im', 'port', '@import', ';', 'u', 'rl', 'url(', ')', '(', '<', '-->', '\\', '/*', '*/', '"', "'", 'http:', '//evil.example/x', 'expression(', 'behavior:', 'javascript:',
        '@font-face', '@namespace', '@charset', 'image', '-set', 'image-set(', 'cross-fade(', 'el', 'ement(', '{', '}', ' ', 'a{background:', 'data:image/png;base64,AAAA', 'data:text/html,', '@media x{',
        // Review 2026-11-21 (M3): the boundaries the sanitiser now looks at.
        '.file', '.http', '#blob', 'a.', ':hover', '::before', 'scroll-', 'overscroll-', '-ms-', 'x', 'src(', 'é', 'file:', 'https:'];
    var bad = [];
    for (var i = 0; i < 20000 && bad.length < 5; i++) {
        var c = '', n = 2 + rnd(14);
        for (var k = 0; k < n; k++) c += pieces[rnd(pieces.length)];
        var o = cssOut(c);
        var urls = (o.match(/url\s*\([^)]*\)?/gi) || []).filter(function (u) { return !/^url\(\s*["']?data:image\/(?:png|gif|jpeg|webp);base64,/i.test(u); });
        // A bare "http:" left as text loads nothing; what matters is a function or rule that loads, an escape or markup.
        if (/[<\\]|-->|@import|@font-face|@namespace|@charset|expression\s*\(|(?:image-set|image|cross-fade|element|paint|src)\s*\(/i.test(o) || DANGER_WORD.test(o) || urls.length || cssOut(o) !== o) bad.push([c, o]);
    }
    return bad.length === 0 || (console.log(JSON.stringify(bad)), false);
}()));
// Review 2026-11-21 (M3): ordinary CSS that only looks like a scheme or "behavior" stays whole.
var cssKeep = ['html{scroll-behavior:smooth}', '.x{overscroll-behavior: contain}', '.list{overscroll-behavior-y:none}', '.file:hover{color:red}', '.blob::before{content:"x"}', 'a.http:focus{color:red}',
    '#ftp:focus{outline:1px solid}', '.https:focus-visible{color:red}', '.javascript:hover{color:red}', '.ファイル:hover{color:red}', '.x-file:hover{color:red}', 'a.wss:active{color:red}', '.filesystem:checked{color:red}', '.u-behavior:hover{color:red}'];
eq('studio css (M3): ' + cssKeep.length + ' ordinary rules whose words look like a scheme or "behavior" are kept as they are', cssKeep.map(function (c) { var r = Studio.sanitizeCss(c); return r.removed.length ? r.css + ' ' + r.removed : c; }), cssKeep);
eq('studio css (M3): IE behavior (as a property), javascript: and schemes where a URL can start are still removed', [
    'a{behavior:url(x.htc)}', 'a{-ms-behavior:url(x.htc)}', 'a{BEHAVIOR :x}', 'a{ behavior:x}', '/**/behavior:x', 'a{content:"https://x"}', "a{content:'file:///etc'}", 'a{b:javascript:alert(1)}', 'a{b: ftp://x}', 'a{b:(blob:x)}', 'a{background:src("//x")}', 'a{background:SRC (x)}'
].map(cssOut), ['a{none}', 'a{none}', 'a{x}', 'a{ x}', '/**/x', 'a{content:"//x"}', "a{content:'///etc'}", 'a{b:alert(1)}', 'a{b: //x}', 'a{b:(x)}', 'a{background:none}', 'a{background:none}']);
check('studio css: ordinary CSS is unchanged and nothing is reported', (function () { var c = '.card {\n  padding: 16px;\n  color: #6735c7;\n  background: linear-gradient(90deg, #fff, #f5f0ff);\n}\n@media (max-width: 600px) { .card > h2:hover { font-size: 1.2rem; } }\n'; var r = Studio.sanitizeCss(c); return r.css === c && r.removed.length === 0; }()));
check('studio css: an inline png stays', cssOut('a{background:url(data:image/png;base64,AAAA)}') === 'a{background:url(data:image/png;base64,AAAA)}');
check('studio css: removed lists what was taken out', Studio.sanitizeCss('@import "x";a{background:url(//x)}').removed.join(',') === 'import,url');
var docOut = Studio.buildDocument({ html: '<h1>x</h1>', css: 'h1{color:red}</style><script>alert(1)</script>' });
check('studio document: the enforced policy is the first element of head', docOut.indexOf('<!doctype html><html lang="ja"><head><meta http-equiv="Content-Security-Policy" content="default-src &#039;none&#039;') === 0 || docOut.indexOf('<!doctype html><html lang="ja"><head><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:; form-action \'none\'"><meta charset="utf-8">') === 0 && docOut.indexOf('<base') === -1);
check('studio document: the policy allows no script, connection, font, frame or form', Studio.PREVIEW_CSP === "default-src 'none'; style-src 'unsafe-inline'; img-src data:; form-action 'none'" && !/script-src|connect-src|unsafe-eval|https?:|\*/.test(Studio.PREVIEW_CSP));
check('studio document: CSS cannot close the style element', docOut.indexOf('<script') === -1 && (docOut.match(/<\/style>/g) || []).length === 3);
check('studio document: reduced-motion rule is last so it wins', docOut.lastIndexOf('prefers-reduced-motion') > docOut.indexOf('h1{color:red}'));
check('studio export: a standalone file without the preview policy, CSS sanitised', (function () { var e = Studio.buildExport({ html: '<p>a</p>', css: '@import "x";p{color:red}' }, 'T<x>'); return e.indexOf('<title>T&lt;x&gt;</title>') !== -1 && e.indexOf('@import') === -1 && e.indexOf('p{color:red}') !== -1 && e.indexOf('Content-Security-Policy') === -1; }()));
check('studio limits: 50KB each, counted in UTF-8 bytes', Studio.overLimit({ html: new Array(51201).join('a'), css: '' }) === null && Studio.overLimit({ html: new Array(51202).join('a'), css: '' }) === 'html' && Studio.overLimit({ html: '', css: new Array(17068).join('あ') }) === 'css' && Studio.overLimit({ html: '', css: new Array(17067).join('あ') }) === null && Studio.byteLength('あ😀a') === 8);
var parsed = Studio.parseCss('/* c */ .card , .box { padding : 16px ; COLOR: Red }\n@media (max-width: 600px) { .columns { flex-direction: column } }\n@keyframes k { from { opacity: 0 } }\n.card h2{margin:0}');
eq('studio parse: selectors, declarations and media', parsed.rules.map(function (r) { return [r.selectors.join('|'), Object.keys(r.declarations).join('|'), r.media]; }), [['.card|.box', 'padding|color', null], ['.columns', 'flex-direction', '(max-width: 600px)'], ['.card h2', 'margin', null]]);
eq('studio parse: properties in order of first use', Studio.cssProperties(parsed), ['padding', 'color', 'flex-direction', 'margin']);
eq('studio parse: tags in the source, comments ignored', Studio.htmlTags('<H1>a</H1><!-- <table> --><p class="x">b<br/></p><ul><li>1</li><li>2</li></ul>'), ['h1', 'p', 'br', 'ul', 'li']);
check('studio parse: unbalanced and huge input ends (no hang)', (function () { var t = Date.now(); Studio.parseCss(new Array(20001).join('a{')); Studio.parseCss(new Array(5001).join('@media x{a{b:c}')); Studio.parseCss('}}}}{{{{'); return Date.now() - t < 5000; }()));
check('studio check css: selector and property, outside media only', Studio.checkCss(parsed, { type: 'css', selector: '.card', property: 'padding' }) && Studio.checkCss(parsed, { type: 'css', selector: '.card  H2', property: 'margin' }) && !Studio.checkCss(parsed, { type: 'css', selector: '.columns', property: 'flex-direction' }) && !Studio.checkCss(parsed, { type: 'css', selector: '.card', property: 'margin' }));
// Review 2026-11-21 (M1): class names, ids and attribute values are case-sensitive in HTML / CSS; element names,
// pseudo-classes and attribute names are not. The line above used to expect ".CARD H2" to match ".card h2", which a
// browser does not do (".CARD" never selects class="card"), so a learner could pass with CSS that has no effect.
check('studio check css (M1): class / id / attribute value keep their case; element, pseudo-class and attribute names do not', (function () {
    // Review N4: the value of "type" (and the other attributes HTML lists) matches without case; "title" does not.
    var p = Studio.parseCss('.Card{padding:1px}\n#Main H2:HOVER{color:red}\nINPUT[TYPE="Text"]{margin:0}\nP[TITLE="Hi"]{width:2px}\nli:NTH-CHILD(ODD){width:1px}');
    function has(sel, prop) { return Studio.checkCss(p, { type: 'css', selector: sel, property: prop }); }
    return !has('.card', 'padding') && has('.Card', 'padding') && has('#Main h2:hover', 'color') && !has('#main h2:hover', 'color') && has('input[type="Text"]', 'margin') && has('input[type="text"]', 'margin')
        && has('p[title="Hi"]', 'width') && !has('p[title="hi"]', 'width') && has('li:nth-child(odd)', 'width');
}()));
eq('studio normSelector (M1, N4): what is lower-cased', ['DIV.Card > H2', 'A:HOVER::BEFORE', 'p:NOT(.Lead)', 'INPUT[TYPE="Text" I]', 'P[TITLE="Hi"]', '.a  .b', '.カード', 'a[title="x]y"].B'].map(function (x) { return Studio.normSelector(x); }),
    ['div.Card > h2', 'a:hover::before', 'p:not(.Lead)', 'input[type="text" i]', 'p[title="Hi"]', '.a .b', '.カード', 'a[title="x]y"].B']);
// Review N3 / N4 (2026-11-21): what reads the same is the same, and nothing else is.
var sameSel = [['.card>h2', '.card > h2'], ['li+li', 'li  +  li'], ['.a ~.b', '.a~ .b'], ['.a,  .b', '.a,.b'], ['DIV.a  >  P:HOVER', 'div.a > p:hover'], ['li:nth-child(2n+1)', 'li:nth-child( 2n + 1 )'],
    ['input[type="TEXT"]', 'input[type=text]'], ['input[TYPE = "Text"]', 'input[type="text"]'], ['p[title="A" i]', 'p[title="a" I]'], ['em[lang="EN"]', 'em[lang="en"]'], ["a[title='x']", 'a[title="x"]'], ['p:not(.A, .b)', 'p:not(.A,.b)']];
var diffSel = [['.Card', '.card'], ['#Main', '#main'], ['a[title="a  b"]', 'a[title="a b"]'], ['a[title="x>y"]', 'a[title="x > y"]'], ['a[title="x,y"]', 'a[title="x, y"]'], ['p[title="A"]', 'p[title="a"]'],
    ['p[title="A" i]', 'p[title="a"]'], ['input[type="TEXT" s]', 'input[type="text"]'], ['p:not(.A)', 'p:not(.a)'], ['.カード', '.ｶｰﾄﾞ'], ['a[x', 'a[y'],
    // Review F2 (2026-11-21): case is ignored for A-Z only. A browser tells these apart (class, element, "i"-flag value, HTML list value).
    ['.Café', '.cafÉ'], ['.É', '.é'], ['x-É', 'x-é'], ['ÉLÉMENT', 'élément'], ['[title="É" i]', '[title="é" i]'], ['input[type="É"]', 'input[type="é"]'], ['p[lang="ÆN"]', 'p[lang="æn"]'], ['p:not(.Ä)', 'p:not(.ä)']];
// Review F2 / F4: ASCII case and spacing inside parentheses still do not matter next to non-ASCII text.
sameSel.push(['DIV.Café', 'div.Café'], ['INPUT[TYPE="TEXTÉ"]', 'input[type="textÉ"]'], ['p[title="aÉ" I]', 'p[title="AÉ" i]'], ['X-É', 'x-É'],
    [':not(.a , .b)', ':not(.a,.b)'], ['p:not( .a ,.b )', 'p:not(.a, .b)'], ['li:is(.a  ,  .b) > p', 'li:is(.a,.b)>p']);
check('studio normSelector (N3/N4): ' + sameSel.length + ' pairs that read the same are equal', sameSel.every(function (p) { return Studio.normSelector(p[0]) === Studio.normSelector(p[1]); }), sameSel.filter(function (p) { return Studio.normSelector(p[0]) !== Studio.normSelector(p[1]); }).map(function (p) { return p.map(Studio.normSelector); }));
check('studio normSelector (N3/N4): ' + diffSel.length + ' pairs that differ (quoted text, class / id case, value case outside the HTML list, flags) stay different', diffSel.every(function (p) { return Studio.normSelector(p[0]) !== Studio.normSelector(p[1]); }), diffSel.filter(function (p) { return Studio.normSelector(p[0]) === Studio.normSelector(p[1]); }));
check('studio check css (N4): ".card>h2" in the CSS solves a check written ".card > h2", and the other way round', Studio.checkCss(Studio.parseCss('.card>h2{color:red}'), { type: 'css', selector: '.card > h2', property: 'color' }) && Studio.checkCss(Studio.parseCss('.card > h2{color:red}'), { type: 'css', selector: '.card>h2', property: 'color' }));
// Review N2 (2026-11-21): deep nesting is refused; ordinary nesting is kept.
check('studio css (N2): nesting deeper than ' + Studio.LIMITS.depth + ' levels is dropped whole ("too_deep"); the deepest ordinary CSS is kept', (function () {
    function nots(k) { return 'a' + new Array(k + 1).join(':not(') + '.b' + new Array(k + 1).join(')') + '{color:red}'; }
    var deep = Studio.sanitizeCss(nots(Studio.LIMITS.depth + 1));
    var ok = Studio.sanitizeCss(nots(Studio.LIMITS.depth));
    var calc = '.a{width:calc(100% - min(10px, max(1px, calc(2px + var(--x, calc(1px * 2))))))}';
    return deep.css === '' && deep.removed.join() === 'too_deep' && ok.css === nots(Studio.LIMITS.depth) && Studio.sanitizeCss(calc).css === calc;
}()));
// Review F1 (2026-11-21): the depth is the larger of "every bracket" and "the brackets the browser's tokenizer sees", so
// brackets in strings, comments and escapes now count too (too deep only refuses CSS). The line before F1 expected
// 'a{b:"(((("}' to be 1 deep: a stray quote ended by a newline hid 6,000 levels that way and crashed the tab.
eq('studio css (N2, F1): depth counts ( [ { together, inside strings, comments and escapes too', [Studio.nestingDepth('a:not(:not(.b)){c:d}'), Studio.nestingDepth('a{b:"(((("}'), Studio.nestingDepth('/*((((*/a{}'), Studio.nestingDepth('a\\(({}'), Studio.nestingDepth('a[b]{c:d(e)}'), Studio.nestingDepth('a{b{c{d:e}}}')], [2, 5, 5, 3, 2, 3]);
eq('studio css (F1): what the browser tokenizer sees counts even where every-bracket counting is low', [
    Studio.nestingDepth('a' + new Array(41).join(':not(")"') + '{}'),           // ")" in strings: every-bracket 1, browser 41
    Studio.nestingDepth('a' + new Array(41).join(':not(]') + '{}'),             // "]" does not close "(": browser 41
    Studio.nestingDepth('a{b:"x\n}' + new Array(41).join(':not(')),            // a newline ends a string
    Studio.nestingDepth('a{b:"x\\\n((((("}'),                                 // "\" + newline continues the string
    Studio.nestingDepth('a{b:url(x"y)}' + new Array(41).join('('))             // an unquoted url( ends at ")", a quote in it starts no string
], [41, 41, 40, 6, 40]);
// Each of these depends on one rule of the tokenizer alone (the ")" hidden in strings keep the every-bracket count at 1).
eq('studio css (F1): tokenizer rules: a newline ends a string, "\\" + newline continues it, an unquoted url( ends at ")"', (function () {
    var hidden = new Array(41).join(':not(")"');
    return [Studio.nestingDepth('a{b:"x\n}a' + hidden + '{}'), Studio.nestingDepth('a{b:"x\\\n}a' + hidden + '{}'), Studio.nestingDepth('a{b:url(x"y)}a' + hidden + '{}')];
}()), [41, 1, 41]);
// Review G1 (2026-11-22): "#url(" is a hash token and "@url(" an at-keyword to the browser, not a url token, so their "("
// count. Before G1 they were read as url tokens up to the ")" hidden in a comment, and the closes in the comment kept the
// every-bracket count low: 16,000 levels read 32 and crashed Firefox. A real url( after a space stays one token.
eq('studio css (G1): "#url(" and "@url(" are not url tokens (their brackets count); " url(" still is', ['#', '@', ' '].map(function (p) {
    return Studio.nestingDepth('a{--x:' + new Array(41).join(p + 'url(x(/*))*/') + '}');
}), [81, 81, 3]);
// Review F4 (2026-11-21): the limit itself, and CSS that becomes deeper when the sanitiser takes text out.
check('studio css (F4): the depth limit is 32 levels: 32 are kept, 33 are refused', Studio.LIMITS.depth === 32 && (function () {
    function nest(k) { return 'a' + new Array(k + 1).join(':not(') + '.b' + new Array(k + 1).join(')') + '{color:red}'; }   // k levels
    return Studio.nestingDepth(nest(32)) === 32 && Studio.sanitizeCss(nest(32)).css === nest(32) && Studio.sanitizeCss(nest(33)).removed.join() === 'too_deep';
}()));
var f1Shapes = {
    'stray quote (the tab crash)': 'a{content:"x\n}a' + new Array(6001).join(':not(') + '.b' + new Array(6001).join(')') + '{color:red}',
    'escaped brackets': 'a' + new Array(6001).join(':not\\(') + '{}',
    'comment opener removed with @import': '@import /*;\na' + new Array(6001).join(':not(') + '{}*/',
    'quote removed with url()': 'a{background:url(")}a' + new Array(6001).join(':not(') + '{}"}',
    'shallow before, deep after (every-bracket and tokenizer both low before)': '@import /*;\na' + new Array(41).join(':not(")"') + '{}',
    '#url( hiding the closes in a comment (review G1, Firefox tab crash)': 'a{--x:' + new Array(501).join('#url(data:image/png;base64,' + new Array(31).join('(') + '/*' + new Array(32).join(')') + '*/') + '}',
    '@url( hiding the closes in a comment (review G1)': 'a{color:var(--y,' + new Array(501).join('@url(data:image/png;base64,' + new Array(30).join('(') + '/*' + new Array(31).join(')') + '*/') + ')}'
};
check('studio css (F1): CSS that is (or becomes) deeper than the limit is dropped whole: <style>, style="" and the saved file', Object.keys(f1Shapes).every(function (k) {
    var css = f1Shapes[k], r = Studio.sanitizeCss(css);
    var doc = Studio.buildDocument({ html: '<p>x</p>', css: css }), file = Studio.buildExport({ html: '<p>x</p>', css: css }, 't');
    return r.css === '' && r.removed.join() === 'too_deep' && Studio.nestingDepth(doc) < 8 && Studio.nestingDepth(file) < 8;
}), Object.keys(f1Shapes).filter(function (k) { return Studio.sanitizeCss(f1Shapes[k]).css !== ''; }));
check('studio css (F1): the second check is on the sanitised text (before it, the depth reads 1)', Studio.nestingDepth(f1Shapes['shallow before, deep after (every-bracket and tokenizer both low before)']) <= Studio.LIMITS.depth);
check('studio css (F1): brackets in strings over many rules are not carried along: 40 rule pairs with content:"(" / ")" and 20 rules with a lone "(" are kept', (function () {
    var pairs = '', lone = '';
    for (var k = 0; k < 40; k++) pairs += '.x' + k + '::before{content:"("}.x' + k + '::after{content:")"}\n';
    for (var j = 0; j < 20; j++) lone += '.y' + j + '::before{content:"("}\n';
    return Studio.sanitizeCss(pairs).css === pairs && Studio.sanitizeCss(lone).css === lone;
}()));
check('studio css (F1): the site\'s own stylesheets are not refused (deepest 7)', (function () {
    var max = 0, bad = [];
    (function walk(d) { fs.readdirSync(d).forEach(function (f) { var p = path.join(d, f); if (fs.statSync(p).isDirectory()) { if (f !== 'node_modules') walk(p); } else if (/\.css$/.test(f)) { var t = fs.readFileSync(p, 'utf8'); max = Math.max(max, Studio.nestingDepth(t)); if (Studio.sanitizeCss(t).removed.indexOf('too_deep') !== -1) bad.push(f); } }); }(path.join(dir, '../../..')));
    return max > 0 && max <= 8 && bad.length === 0;
}()));
check('studio css (N2): 8,000 levels (inside the 50KB limit) end quickly and throw nothing (sanitizeCss, parseCss, normSelector)', (function () {
    var huge = 'a' + new Array(8001).join(':not(') + '.b' + new Array(8001).join(')') + '{c:d}', t = Date.now();
    try { Studio.sanitizeCss(huge); Studio.parseCss(huge); Studio.normSelector(huge); Studio.nestingDepth(huge); } catch (e) { return false; }
    return Date.now() - t < 2000;
}()));
check('studio css (N2): the shipped lessons are far below the limit', JSON.parse(fs.readFileSync(path.join(dir, '../../data/labs/code-studio.json'), 'utf8')).lessons.every(function (l) { return Studio.nestingDepth(l.files.css) < 8; }));
check('studio parse (N3): every selector of a long list is normalised (the 40th too)', (function () { var list = []; for (var k = 0; k < 40; k++) list.push('.a' + k + '>B'); var r = Studio.parseCss(list.join(',') + '{color:red}').rules[0]; return r.selectors.length === 40 && r.selectors[39] === '.a39 > b'; }()));
check('studio check css: value / not', Studio.checkCss(parsed, { type: 'css', selector: '.card', property: 'color', value: 'RED' }) && !Studio.checkCss(parsed, { type: 'css', selector: '.card', property: 'color', value: 'blue' }) && Studio.checkCss(parsed, { type: 'css', selector: '.card', property: 'color', not: 'blue' }) && !Studio.checkCss(parsed, { type: 'css', selector: '.card', property: 'color', not: 'red' }));
check('studio check css-media: needs the feature in the media condition', Studio.checkCss(parsed, { type: 'css-media', feature: 'max-width', selector: '.columns', property: 'flex-direction' }) && !Studio.checkCss(parsed, { type: 'css-media', feature: 'min-width', selector: '.columns', property: 'flex-direction' }) && !Studio.checkCss(parsed, { type: 'css-media', feature: 'max-width', selector: '.card', property: 'padding' }));
function fakeDoc(map) { return { querySelectorAll: function (sel) { if (sel === '!!') throw new Error('bad'); return map[sel] || []; } }; }
function node(text, attrs) { return { textContent: text, getAttribute: function (n) { return attrs && Object.prototype.hasOwnProperty.call(attrs, n) ? attrs[n] : null; } }; }
var fd = fakeDoc({ 'h2': [node(' 好きなもの ')], 'ul li': [node('a'), node('b'), node('c')], 'img': [node('', { alt: '  ' }), node('', { alt: '猫' })], 'p': [node('   ')] });
check('studio check dom: element count', Studio.checkDom(fd, { type: 'element', selector: 'ul li', min: 3 }) && !Studio.checkDom(fd, { type: 'element', selector: 'ul li', min: 4 }) && !Studio.checkDom(fd, { type: 'element', selector: 'h3' }));
check('studio check dom: text contains / min_length, whitespace ignored', Studio.checkDom(fd, { type: 'text', selector: 'h2', contains: '好きな' }) && Studio.checkDom(fd, { type: 'text', selector: 'h2', min_length: 5 }) && !Studio.checkDom(fd, { type: 'text', selector: 'p', min_length: 1 }));
check('studio check dom: attribute must be non-empty', Studio.checkDom(fd, { type: 'attribute', selector: 'img', name: 'alt' }) && !Studio.checkDom(fd, { type: 'attribute', selector: 'img', name: 'src' }));
check('studio check dom: an invalid selector is false, not an exception', Studio.checkDom(fd, { type: 'element', selector: '!!' }) === false && Studio.checkDom(fd, { type: 'unknown', selector: 'h2' }) === false);
var studioData = JSON.parse(fs.readFileSync(path.join(dir, '../../data/labs/code-studio.json'), 'utf8'));
check('studio data: no starter lesson is already solved, and none is changed by the CSS sanitiser', studioData.lessons.every(function (l) {
    var p = Studio.parseCss(l.files.css);
    var cssChecks = l.exercises.reduce(function (acc, e) { return acc.concat(e.checks.filter(function (c) { return c.type === 'css' || c.type === 'css-media'; })); }, []);
    return Studio.sanitizeCss(l.files.css).removed.length === 0 && cssChecks.every(function (c) { return !Studio.checkCss(p, c); });
}));
check('studio data: every hint that is CSS solves its own check', (function () {
    var l = studioData.lessons[2];
    var p = Studio.parseCss(l.files.css + '\n@media (max-width: 600px) { .columns { flex-direction: column; } .box { width: auto; } }');
    return l.exercises.every(function (e) { return Studio.runChecks(null, p, e.checks).every(Boolean); });
}()));
check('studio data: every tag and property the starter code uses has a glossary entry', studioData.lessons.every(function (l) {
    return Studio.htmlTags(l.files.html).every(function (t) { return !!studioData.glossary.tags[t]; }) && Studio.cssProperties(Studio.parseCss(l.files.css)).every(function (p) { return !!studioData.glossary.properties[p]; });
}));

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
check('source: scanned all Labs scripts', files.length >= 17);

console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
