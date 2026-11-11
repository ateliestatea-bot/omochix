/**
 * OmochiX Labs: robots.txt builder, parser and matcher (RFC 9309).
 *
 * Matching follows the standard: the group whose user-agent equals the
 * crawler's product token (case-insensitive) wins, otherwise "*"; inside the
 * group the longest matching path pattern wins and Allow wins a tie; "*"
 * matches any sequence and a trailing "$" anchors the end. Non-standard
 * extensions (crawl-delay, host, …) are kept in the text but never used.
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    else root.OmochixLabsRobots = api;
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var AGENT = /^(\*|[A-Za-z][A-Za-z0-9_-]{0,60})$/;
    var MAX_BYTES = 500 * 1024; // what Google reads; the rest of a larger file is ignored

    function validPath(path) {
        return typeof path === 'string' && path.length <= 2048 && /^[/*]/.test(path) && !/\s/.test(path);
    }

    function validSitemap(url) {
        return typeof url === 'string' && url.length <= 2048 && /^https?:\/\/[^\s/?#]+[^\s]*$/i.test(url);
    }

    /**
     * @param {{groups: Array<{agents: string[], rules: Array<{type: string, path: string}>, comment?: string}>, sitemaps?: string[]}} config
     * @return {{text: string, warnings: Array<{code: string, message: string}>}}
     */
    function build(config) {
        var warnings = [];
        var lines = [];
        var c = config || {};
        (c.groups || []).forEach(function (group) {
            var agents = (group.agents || []).filter(function (a) { return AGENT.test(a); });
            if (!agents.length) return;
            var seen = {};
            var rules = [];
            (group.rules || []).forEach(function (rule) {
                var type = rule.type === 'allow' ? 'Allow' : 'Disallow';
                if (!validPath(rule.path)) {
                    warnings.push({ code: 'bad_path', message: 'パス「' + String(rule.path).slice(0, 60) + '」は「/」か「*」で始め、空白を含めないでください。この行は出力していません。' });
                    return;
                }
                var key = type + ' ' + rule.path;
                if (seen[key]) return;
                seen[key] = true;
                rules.push(type + ': ' + rule.path);
                if (type === 'Disallow' && rule.path === '/' && agents.indexOf('*') !== -1) {
                    warnings.push({ code: 'block_all', message: 'すべてのクローラーにサイト全体のクロールを拒否する設定です。検索エンジンにも巡回されなくなります。意図した設定か確認してください。' });
                }
            });
            if (!rules.length) rules.push('Allow: /');
            if (lines.length) lines.push('');
            if (group.comment) lines.push('# ' + String(group.comment).replace(/[\r\n]+/g, ' '));
            agents.forEach(function (a) { lines.push('User-agent: ' + a); });
            lines = lines.concat(rules);
        });
        var sitemaps = [];
        (c.sitemaps || []).forEach(function (url) {
            var u = String(url).trim();
            if (!u) return;
            if (!validSitemap(u)) {
                warnings.push({ code: 'bad_sitemap', message: 'サイトマップ「' + u.slice(0, 60) + '」は https:// から始まる完全なURLで書いてください。この行は出力していません。' });
                return;
            }
            if (sitemaps.indexOf(u) === -1) sitemaps.push(u);
        });
        if (sitemaps.length) {
            if (lines.length) lines.push('');
            sitemaps.forEach(function (u) { lines.push('Sitemap: ' + u); });
        }
        var text = lines.join('\n') + (lines.length ? '\n' : '');
        if (text.length > MAX_BYTES) warnings.push({ code: 'too_large', message: 'robots.txt が500KiBを超えています。Google は超えた部分を読みません。' });
        return { text: text, warnings: warnings };
    }

    /**
     * @return {{groups: Array<{agents: string[], rules: Array<{type: string, path: string, line: number}>}>, sitemaps: string[], ignored: number[]}}
     */
    function parse(text) {
        var groups = [];
        var sitemaps = [];
        var ignored = [];
        var current = null;
        var lastWasAgent = false;
        String(text).replace(/^\ufeff/, '').split(/\r\n|\r|\n/).forEach(function (raw, idx) {
            var line = raw.replace(/#.*$/, '').trim();
            if (!line) return;
            var m = /^([A-Za-z-]+)\s*:\s*(.*)$/.exec(line);
            if (!m) { ignored.push(idx + 1); return; }
            var key = m[1].toLowerCase();
            var value = m[2].trim();
            if (key === 'user-agent') {
                if (!current || !lastWasAgent) { current = { agents: [], rules: [] }; groups.push(current); }
                current.agents.push(value.toLowerCase());
                lastWasAgent = true;
                return;
            }
            if (key === 'sitemap') { sitemaps.push(value); return; }
            lastWasAgent = false;
            if ((key === 'allow' || key === 'disallow') && current) {
                current.rules.push({ type: key, path: value, line: idx + 1 });
                return;
            }
            if (key !== 'allow' && key !== 'disallow') ignored.push(idx + 1);
        });
        return { groups: groups, sitemaps: sitemaps, ignored: ignored };
    }

    /**
     * RFC 9309 §2.2.2: compare with non-ASCII octets percent-encoded and
     * percent-encoded unreserved ASCII decoded (hex upper-cased), so "/café",
     * "/caf%c3%a9" and "/caf%C3%A9" are the same path and "%41" equals "A".
     */
    function canonical(path) {
        return String(path)
            .replace(/%([0-9a-fA-F]{2})/g, function (m, hex) {
                var ch = String.fromCharCode(parseInt(hex, 16));
                return /[A-Za-z0-9\-._~]/.test(ch) ? ch : '%' + hex.toUpperCase();
            })
            .replace(/[^\x00-\x7F]/gu, function (ch) { return encodeURIComponent(ch); });
    }

    /** Octet length, as the RFC compares; "*" and a trailing "$" count as written. */
    function patternLength(path) {
        return canonical(path).length;
    }

    function matches(pattern, path) {
        var anchored = pattern.charAt(pattern.length - 1) === '$';
        var body = anchored ? pattern.slice(0, -1) : pattern;
        var re = '^' + body.split('*').map(function (part) {
            return part.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
        }).join('.*') + (anchored ? '$' : '');
        return new RegExp(re).test(path);
    }

    /** Path part of a URL or path, percent-encoding preserved, always starting with "/". */
    function pathOf(input) {
        var s = String(input || '').trim();
        var m = /^[a-z][a-z0-9+.-]*:\/\/[^/?#]*([^#]*)/i.exec(s);
        var path = m ? m[1] : s.replace(/#.*$/, '');
        if (!path || path.charAt(0) !== '/') path = '/' + path;
        return path;
    }

    /**
     * @param {object} parsed parse() result.
     * @param {string} agent  Crawler product token, e.g. "GPTBot".
     * @param {string} target URL or path.
     * @return {{allowed: boolean, rule: ?{type: string, path: string, line: number}, agents: ?string[], path: string}}
     */
    function test(parsed, agent, target) {
        var path = pathOf(target);
        var token = String(agent || '').trim().toLowerCase();
        if (path === '/robots.txt') return { allowed: true, rule: null, agents: null, path: path };
        var chosen = parsed.groups.filter(function (g) { return token && token !== '*' && g.agents.indexOf(token) !== -1; });
        if (!chosen.length) chosen = parsed.groups.filter(function (g) { return g.agents.indexOf('*') !== -1; });
        if (!chosen.length) return { allowed: true, rule: null, agents: null, path: path };
        var best = null;
        var bestLen = -1;
        chosen.forEach(function (g) {
            g.rules.forEach(function (rule) {
                if (rule.path === '') return; // "Disallow:" with no path allows everything
                if (!matches(canonical(rule.path), canonical(path))) return;
                var len = patternLength(rule.path);
                if (len > bestLen || (len === bestLen && rule.type === 'allow' && best.type !== 'allow')) {
                    best = rule;
                    bestLen = len;
                }
            });
        });
        var agents = [];
        chosen.forEach(function (g) { agents = agents.concat(g.agents); });
        return { allowed: !best || best.type === 'allow', rule: best, agents: agents, path: path };
    }

    return { build: build, parse: parse, test: test, pathOf: pathOf, canonical: canonical, validPath: validPath, validSitemap: validSitemap };
}));
