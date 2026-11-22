/**
 * OmochiX Labs: Code Studio core (HTML/CSS adapter, Phase 1).
 *
 * Pure functions, no DOM access, so the same code runs in the page and in
 * tests/labs/core.js. docs/labs/CODE-STUDIO-DESIGN.md §5 is the security
 * design. What this file guarantees:
 *  - PURIFY: the allowlist handed to DOMPurify by the page. No script, no
 *    event attribute, no element that loads or navigates, no href, URLs
 *    limited to small inline images.
 *  - sanitizeCss(): no "<" (so "</style>" cannot close the style element),
 *    no @import, no url() other than data:image, no legacy script hooks.
 *  - buildDocument(): the preview document. Its FIRST element in <head> is an
 *    enforced Content-Security-Policy with default-src 'none', so even markup
 *    that slipped through could not send a request. The page shows it in
 *    <iframe sandbox=""> (no scripts, opaque origin).
 * The learner's code is never evaluated here or on the server.
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    else root.OmochixLabsStudio = api;
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /** depth: how deeply "(", "[" and "{" may nest in CSS (review N2: thousands of ":not(" stop a browser's CSS parser). */
    var LIMITS = { html: 50 * 1024, css: 50 * 1024, nodes: 1500, depth: 32 };

    /** The policy of the preview document. No script-src, connect-src, font-src, frame-src: all fall back to 'none'. form-action has no fallback, so it is named. */
    var PREVIEW_CSP = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; form-action 'none'";

    var ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'br', 'hr', 'div', 'span', 'a', 'strong', 'em', 'b', 'i', 'u', 's', 'small', 'mark',
        'sub', 'sup', 'code', 'pre', 'kbd', 'samp', 'blockquote', 'q', 'cite', 'abbr', 'time', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'colgroup', 'col', 'figure', 'figcaption', 'img',
        'header', 'footer', 'main', 'nav', 'section', 'article', 'aside', 'address', 'details', 'summary',
        'label', 'input', 'button', 'select', 'option', 'optgroup', 'textarea', 'fieldset', 'legend', 'progress', 'meter'
    ];
    var ALLOWED_ATTR = [
        'class', 'id', 'title', 'lang', 'dir', 'hidden', 'style', 'src', 'alt', 'width', 'height', 'colspan', 'rowspan',
        'scope', 'headers', 'span', 'start', 'reversed', 'value', 'type', 'name', 'for', 'placeholder', 'checked', 'disabled',
        'readonly', 'selected', 'multiple', 'rows', 'cols', 'min', 'max', 'step', 'open', 'datetime', 'role', 'tabindex'
    ];
    /** The only attribute that holds a URL. Every other allowed attribute is plain text (see purifyConfig). */
    var URL_ATTR = 'src';
    /** The only URL a preview may hold: a small inline bitmap. */
    var IMAGE_URI = /^data:image\/(?:png|gif|jpeg|webp);base64,[A-Za-z0-9+\/=]+$/;
    /** Elements that run code, load a resource, navigate, or change how the document is parsed. */
    var FORBID_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'link', 'meta', 'base', 'form', 'svg', 'math',
        'template', 'slot', 'noscript', 'audio', 'video', 'source', 'track', 'picture', 'canvas', 'map', 'area', 'dialog', 'portal'
    ];
    /** DOMPurify options. A function, so no caller can mutate the shared lists. */
    function purifyConfig() {
        return {
            ALLOWED_TAGS: ALLOWED_TAGS.slice(),
            ALLOWED_ATTR: ALLOWED_ATTR.slice(),
            FORBID_TAGS: FORBID_TAGS.slice(),
            FORBID_ATTR: ['href', 'srcset', 'srcdoc', 'action', 'formaction', 'ping', 'background', 'poster', 'xlink:href', 'target', 'download', 'autofocus'],
            ALLOW_DATA_ATTR: false,
            ALLOW_ARIA_ATTR: true,
            ALLOW_UNKNOWN_PROTOCOLS: false,
            // Small inline images only. No href at all: in a srcdoc frame even "#top" resolves against the
            // page's URL and would load the site inside the frame, so links are shown but do not navigate.
            ALLOWED_URI_REGEXP: IMAGE_URI,
            // DOMPurify tests EVERY attribute value against that pattern unless the attribute is listed here,
            // so the plain-text attributes (type, colspan, width, lang, ...) are named. src is not: the page
            // also checks it with isImageUri() in a hook, because DOMPurify lets any data: URL through on <img>.
            ADD_URI_SAFE_ATTR: ALLOWED_ATTR.filter(function (a) { return a !== URL_ATTR; }),
            KEEP_CONTENT: true,
            WHOLE_DOCUMENT: false,
            RETURN_DOM: false,
            SANITIZE_DOM: true
        };
    }

    function byteLength(text) {
        var s = String(text == null ? '' : text), n = 0;
        for (var i = 0; i < s.length; i++) {
            var c = s.charCodeAt(i);
            if (c < 0x80) n += 1;
            else if (c < 0x800) n += 2;
            else if (c >= 0xd800 && c <= 0xdbff) { n += 4; i++; }
            else n += 3;
        }
        return n;
    }

    /** @return {?string} 'html' or 'css' when that input is over its limit. */
    function overLimit(files, limits) {
        var l = limits || LIMITS;
        if (byteLength(files.html) > (l.html || LIMITS.html)) return 'html';
        if (byteLength(files.css) > (l.css || LIMITS.css)) return 'css';
        return null;
    }

    /**
     * Deepest nesting of "(", "[" and "{" (review N2, F1). The larger of two counts, so a CSS text cannot look shallow to
     * one of them and be deep to the browser:
     *  - every bracket, wherever it is (inside strings, comments and escapes too): never less than the browser's nesting
     *    unless closing brackets are hidden in strings or comments, and
     *  - the brackets the browser's own tokenizer sees (CSS Syntax 3): a string ends at its quote OR at a newline, a
     *    comment runs to its end (or to the end of the text), "\" escapes one character (a newline continues a string),
     *    and an unquoted url( runs to its ")" (only where the browser reads a url token: not in "#url(" or "@url(").
     * In the tokenizer count, brackets nest like the browser's blocks: a closing bracket that does not match the open one
     * is not a close ("(]" stays open). In the every-bracket count any closing bracket closes one level, so a lone "(" in
     * a string (content:"(") is not carried from rule to rule.
     * Counting too deep only refuses CSS (the safe side); the deepest in this site's own stylesheets is 7.
     */
    function nestingDepth(css) {
        var t = String(css == null ? '' : css).replace(/\r\n?|\f/g, '\n');
        return Math.max(bracketDepth(t, false), bracketDepth(t, true));
    }

    var OPEN_OF = { ')': '(', ']': '[', '}': '{' };

    function bracketDepth(t, tokens) {
        var stack = [], max = 0, n = t.length, i = 0;
        while (i < n) {
            var c = t.charAt(i);
            if (tokens) {
                if (c === '/' && t.charAt(i + 1) === '*') { var e = t.indexOf('*/', i + 2); if (e === -1) break; i = e + 2; continue; }
                if (c === '\\') { i += t.charAt(i + 1) === '\n' ? 1 : 2; continue; }     // "\" + newline outside a string is not an escape
                if (c === '"' || c === "'") {
                    for (i++; i < n; i++) {
                        var d = t.charAt(i);
                        if (d === c) { i++; break; }
                        if (d === '\n') break;                                          // a bad string: the newline ends it
                        if (d === '\\') i++;                                            // escape, or a newline that continues the string
                    }
                    continue;
                }
                // Not after an identifier character or "\" (part of a longer name), nor after "#" or "@" (review G1): the
                // browser reads "#url" as a hash token and "@url" as an at-keyword, so the "(" after them is a bracket.
                if ((c === 'u' || c === 'U') && /^url\(/i.test(t.slice(i, i + 4)) && !/[A-Za-z0-9_\-\u0080-￿\\#@]/.test(t.charAt(i - 1) || '')) {
                    var k = i + 4;
                    while (k < n && /[ \t\n]/.test(t.charAt(k))) k++;
                    if (t.charAt(k) !== '"' && t.charAt(k) !== "'") {                   // url(...) unquoted: one token up to ")"
                        for (i = k; i < n; i++) {
                            if (t.charAt(i) === ')') { i++; break; }
                            if (t.charAt(i) === '\\' && t.charAt(i + 1) !== '\n') i++;
                        }
                        continue;
                    }
                }
            }
            if (c === '(' || c === '[' || c === '{') { stack.push(c); if (stack.length > max) max = stack.length; }
            else if (OPEN_OF[c] && stack.length && (!tokens || stack[stack.length - 1] === OPEN_OF[c])) stack.pop();
            i++;
        }
        return max;
    }

    function stripComments(css) { return String(css).replace(/\/\*[\s\S]*?\*\//g, ''); }

    /**
     * CSS that cannot leave the <style> element or load anything.
     * @return {{css: string, removed: string[]}} removed lists what was taken out (codes), for a note to the learner.
     */
    function sanitizeCss(css) {
        var removed = [];
        var out = String(css == null ? '' : css).replace(/\u0000/g, '');
        // Nesting deeper than LIMITS.depth is not ordinary CSS (the deepest in this site's own stylesheets is 7) and can
        // stop the browser's CSS parser: drop it whole, before anything reaches a style element or a saved file.
        if (nestingDepth(out) > LIMITS.depth) return { css: '', removed: ['too_deep'] };
        // Taking text out can join what is left into a new "@import" or "url(" ("@im@import;port"), so the
        // rules run again until nothing changes. Text that is still changing after MAX_PASSES is dropped whole.
        for (var pass = 0; pass < MAX_PASSES; pass++) {
            var before = out;
            out = sanitizeCssOnce(out, removed);
            if (out === before) {
                // Review F1: removing text can also make CSS deeper (a quote or a comment opener taken out with a url() or
                // an @import), so the limit is checked again on what would reach the browser.
                if (nestingDepth(out) > LIMITS.depth) return { css: '', removed: ['too_deep'] };
                return { css: out, removed: unique(removed) };
            }
        }
        removed.push('unstable');
        return { css: '', removed: unique(removed) };
    }

    var MAX_PASSES = 12;
    /** Characters of a CSS identifier (for a RegExp character class): ASCII letters, digits, "_", "-" and anything non-ASCII. */
    var IDENT = 'A-Za-z0-9_\\-\\u0080-\\uFFFF';

    function sanitizeCssOnce(out, removed) {
        function drop(re, code, replacement) {
            var next = out.replace(re, replacement || '');
            if (next !== out) { removed.push(code); out = next; }
        }
        drop(/<|-->/g, 'markup');                                             // "</style>", "<!--", "-->"
        drop(/\\/g, 'escape');                                                // no escapes: "\75rl(" or "ur\l(" cannot spell url(
        // No word boundary: "@importx" is not an import, but there is no reason to keep it either.
        drop(/@import[^;]*;?/gi, 'import');                                    // to the next ";" (or the end), whatever is in between
        drop(/@(?:font-face|namespace|charset|document)[^{};]*(?:\{[^{}]*\}|;)?/gi, 'at-rule');
        drop(/url\(\s*(?!["']?data:image\/(?:png|gif|jpeg|webp);base64,)[^)]*\)?/gi, 'url', 'none');
        drop(/(?:image-set|image|cross-fade|element|paint|src)\s*\([^)]*\)?/gi, 'function', 'none');   // with its arguments (a quoted URL); src() is the CSS Values 4 url()
        drop(/expression\s*\(|-moz-binding/gi, 'legacy');
        // The two rules below look at what comes before the word (IDENT: letters, digits, "_", "-", non-ASCII), because
        // the same letters are ordinary CSS elsewhere. Removing them is defence in depth: in this CSS nothing can load
        // from a bare word (url(), @import and the loading functions above are removed wherever they are).
        //  - IE's "behavior" / "-ms-behavior" property, as a property name only: "scroll-behavior" and
        //    "overscroll-behavior" are other properties and stay.
        drop(new RegExp('(^|[^' + IDENT + '])(?:-ms-)?behavior\\s*:', 'gi'), 'legacy', '$1');
        //  - "javascript:" and URL schemes where a URL could start: not right after a class / id / element name, where
        //    ":" starts a pseudo-class (".file:hover", "a.http:focus", ".blob::before", "#ftp:focus").
        drop(new RegExp('(^|[^' + IDENT + '.#])javascript\\s*:', 'gi'), 'legacy', '$1');
        drop(new RegExp('(^|[^' + IDENT + '.#])(?:https?|ftp|wss?|file|blob|filesystem)\\s*:', 'gi'), 'url', '$1');
        return out;
    }

    /** @return {boolean} true for the one kind of URL an attribute may hold. */
    function isImageUri(value) { return IMAGE_URI.test(String(value == null ? '' : value).trim()); }

    /**
     * A small CSS reader: rules with their selectors and declarations, and the
     * media condition they sit in (one level). Enough for the exercise checks
     * and the property glossary; it is not a validator.
     * @return {{rules: Array<{selectors: string[], declarations: Object<string,string>, media: ?string}>}}
     */
    function parseCss(css) {
        var text = stripComments(css);
        var rules = [];
        var i = 0, n = text.length;
        function norm(s) { return s.replace(/\s+/g, ' ').trim(); }
        function block(start) {                    // index after the matching "}" for the "{" at start
            var depth = 0;
            for (var k = start; k < n; k++) {
                if (text[k] === '{') depth++;
                else if (text[k] === '}') { depth--; if (depth === 0) return k + 1; }
            }
            return n;
        }
        function declarations(body) {
            var out = {};
            body.split(';').forEach(function (part) {
                var c = part.indexOf(':');
                if (c < 1) return;
                var prop = norm(part.slice(0, c)).toLowerCase();
                var value = norm(part.slice(c + 1));
                if (/^-{0,2}[a-z][a-z0-9-]*$/.test(prop) && value) out[prop] = value;
            });
            return out;
        }
        function read(from, to, media, depth) {
            var p = from;
            while (p < to) {
                var open = text.indexOf('{', p);
                if (open === -1 || open >= to) break;
                var end = block(open);
                var prelude = norm(text.slice(p, open).replace(/^[;\s}]+/, ''));
                var body = text.slice(open + 1, end - 1);
                if (prelude.charAt(0) === '@') {
                    var m = /^@media\b(.*)$/i.exec(prelude);
                    if (m && depth === 0) read(open + 1, end - 1, norm(m[1]).toLowerCase(), 1);
                    // any other at-rule (@keyframes, @supports, ...) is skipped
                } else if (prelude) {
                    rules.push({
                        selectors: prelude.split(',').map(function (sel) { return normSelector(sel); }).filter(Boolean),   // not map(normSelector): the index would be read as a nesting level
                        declarations: declarations(body),
                        media: media
                    });
                }
                p = end;
            }
        }
        read(i, n, null, 0);
        return { rules: rules };
    }

    function unique(list) {
        var seen = {}, out = [];
        list.forEach(function (v) { if (!Object.prototype.hasOwnProperty.call(seen, v)) { seen[v] = true; out.push(v); } });
        return out;
    }

    /** Property names used, in order of first use (custom properties left out). */
    function cssProperties(parsed) {
        var names = [];
        parsed.rules.forEach(function (r) { Object.keys(r.declarations).forEach(function (p) { if (p.indexOf('--') !== 0) names.push(p); }); });
        return unique(names);
    }

    /** Tag names written in the HTML source, in order of first use. Text matching only: nothing is parsed or run. */
    function htmlTags(html) {
        var names = [], m, re = /<([A-Za-z][A-Za-z0-9]*)(?=[\s>\/])/g;
        var text = String(html == null ? '' : html).replace(/<!--[\s\S]*?-->/g, '');
        while ((m = re.exec(text))) names.push(m[1].toLowerCase());
        return unique(names);
    }

    function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

    var BASE_CSS = 'html{color-scheme:light}body{margin:16px;font-family:system-ui,-apple-system,"Hiragino Sans","Noto Sans JP",sans-serif;line-height:1.7;color:#1a1a1a;background:#fff}img{max-width:100%;height:auto}a{color:#0b57d0;text-decoration:underline}';
    var CALM_CSS = '@media (prefers-reduced-motion: reduce){*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}';

    /**
     * The preview document (iframe srcdoc).
     * @param {{html: string, css: string}} safe html MUST be DOMPurify output made with purifyConfig();
     *        css is passed through sanitizeCss() again here.
     */
    function buildDocument(safe) {
        var css = sanitizeCss(safe.css).css;
        return '<!doctype html><html lang="ja"><head>'
            + '<meta http-equiv="Content-Security-Policy" content="' + esc(PREVIEW_CSP) + '">'
            + '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            + '<title>preview</title><style>' + BASE_CSS + '</style><style>' + css + '</style><style>' + CALM_CSS + '</style>'
            + '</head><body>' + String(safe.html == null ? '' : safe.html) + '</body></html>';
    }

    /** A standalone .html file of the learner's work (same sanitised content as the preview, no preview policy). */
    function buildExport(safe, title) {
        return '<!doctype html>\n<html lang="ja">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>'
            + esc(title || 'OmochiX Code Studio') + '</title>\n<style>\n' + sanitizeCss(safe.css).css + '\n</style>\n</head>\n<body>\n'
            + String(safe.html == null ? '' : safe.html) + '\n</body>\n</html>\n';
    }

    /**
     * Attributes whose values HTML matches without regard to case in a selector (HTML Standard, "Selectors"), unless the
     * selector says "s". Any other value keeps its case unless the selector says "i".
     */
    var CASELESS_ATTR = ['accept', 'accept-charset', 'align', 'alink', 'axis', 'bgcolor', 'charset', 'checked', 'clear', 'codetype', 'color', 'compact', 'declare', 'defer', 'dir',
        'direction', 'disabled', 'enctype', 'face', 'frame', 'hreflang', 'http-equiv', 'lang', 'language', 'link', 'media', 'method', 'multiple', 'nohref', 'noresize', 'noshade',
        'nowrap', 'readonly', 'rel', 'rev', 'rules', 'scope', 'scrolling', 'selected', 'shape', 'target', 'text', 'type', 'valign', 'valuetype', 'vlink'];

    /**
     * A selector in a form two selectors can be compared in: two selectors that read the same give the same string.
     *  - Spaces outside strings are collapsed; combinators (">", "+", "~") and "," are written one way (" > ", ",").
     *  - Lower-cased: element names, pseudo-class / pseudo-element names and their keyword arguments, attribute names,
     *    and the value of an attribute selector that matches without case ("i" flag, or an attribute in CASELESS_ATTR).
     *  - Kept as written: class names, ids, other attribute values, and everything inside quotes (spaces and symbols too).
     *  - [name=value] and [name="value"] are the same selector: the value is always written in double quotes.
     * Text it cannot read (an unclosed bracket, nesting deeper than LIMITS.depth) is kept as it is, so it only equals
     * the very same text.
     */
    /**
     * Review F2: HTML and CSS ignore case in selectors for ASCII letters only ("ASCII case-insensitive"): "É" and "é" are
     * different class names, element names and "i"-flag values to a browser, so only A-Z are lower-cased here.
     */
    function asciiLower(s) { return String(s).replace(/[A-Z]+/g, function (m) { return m.toLowerCase(); }); }

    function normSelector(selector, level) {
        var t = String(selector == null ? '' : selector);
        if ((level || 0) > LIMITS.depth) return t;
        var out = '', i = 0, n = t.length, space = false;
        var identChar = /[A-Za-z0-9_\-\u0080-\uFFFF]/, ws = /[ \t\n\r\f]/;
        function emit(text) { if (space && out !== '' && !/[ ,(]$/.test(out)) out += ' '; space = false; out += text; }
        function ident(lower) {                       // an identifier (escapes kept) from i
            var start = i;
            while (i < n) {
                if (t.charAt(i) === '\\') { i = Math.min(n, i + 2); continue; }
                if (!identChar.test(t.charAt(i))) break;
                i++;
            }
            var word = t.slice(start, i);
            return lower ? asciiLower(word) : word;
        }
        function quoted() {                           // a string from i, exactly as written (with its quotes)
            var q = t.charAt(i), start = i;
            i++;
            while (i < n && t.charAt(i) !== q) i += t.charAt(i) === '\\' ? 2 : 1;
            i = Math.min(n, i + 1);
            return t.slice(start, i);
        }
        function skipSpace() { while (i < n && ws.test(t.charAt(i))) i++; }
        function attribute() {                        // from "[" to "]"; null when it cannot be read with certainty
            var start = i;
            i++;
            skipSpace();
            var name = '';
            while (i < n && (identChar.test(t.charAt(i)) || t.charAt(i) === '|' || t.charAt(i) === '\\')) name += t.charAt(i) === '\\' ? t.slice(i, (i += 2)) : t.charAt(i++);
            if (!name) { i = start; return null; }
            skipSpace();
            var op = '';
            if (t.charAt(i) === '=') op = '=';
            else if (/[~|^$*]/.test(t.charAt(i)) && t.charAt(i + 1) === '=') op = t.charAt(i) + '=';
            if (!op) {
                if (t.charAt(i) !== ']') { i = start; return null; }
                i++;
                return '[' + asciiLower(name) + ']';
            }
            i += op.length;
            skipSpace();
            var value;
            if (t.charAt(i) === '"' || t.charAt(i) === "'") {
                var q = quoted();
                if (q.length < 2 || q.charAt(q.length - 1) !== q.charAt(0)) { i = start; return null; }
                value = q.slice(1, -1);
                if (q.charAt(0) === "'") value = value.replace(/\\'/g, "'").replace(/"/g, '\\"');
            } else {
                value = ident(false);
                if (!value) { i = start; return null; }
            }
            skipSpace();
            var flag = '';
            if (/[iIsS]/.test(t.charAt(i)) && !identChar.test(t.charAt(i + 1) || '')) { flag = t.charAt(i).toLowerCase(); i++; skipSpace(); }
            if (t.charAt(i) !== ']') { i = start; return null; }
            i++;
            var lowerName = asciiLower(name);
            if (flag === 'i' || (flag !== 's' && CASELESS_ATTR.indexOf(lowerName) !== -1)) value = asciiLower(value);
            return '[' + lowerName + op + '"' + value + '"' + (flag === 'i' ? ' i' : '') + ']';
        }
        while (i < n) {
            var c = t.charAt(i);
            if (ws.test(c)) { skipSpace(); space = true; continue; }
            if (c === '>' || c === '+' || c === '~') {
                out = out.replace(/ $/, '');
                out += (out === '' ? '' : ' ') + c + ' ';
                space = false; i++; skipSpace();
                continue;
            }
            if (c === ',') { out = out.replace(/ $/, '') + ','; space = false; i++; skipSpace(); continue; }
            if (c === '"' || c === "'") { emit(quoted()); continue; }
            if (c === '.' || c === '#') { i++; emit(c + ident(false)); continue; }
            if (c === '[') {
                var attr = attribute();
                if (attr === null) { emit(t.slice(i)); i = n; continue; }    // unreadable: the rest stays as written
                emit(attr);
                continue;
            }
            if (c === ':') {                          // :name or ::name, then (arguments) read as a selector again
                i++;
                var colon = ':';
                if (t.charAt(i) === ':') { colon = '::'; i++; }
                var pseudo = colon + ident(true);
                if (t.charAt(i) === '(') {
                    var depth = 0, start = i;
                    for (; i < n; i++) {
                        if (t.charAt(i) === '"' || t.charAt(i) === "'") { var qq = t.charAt(i); i++; while (i < n && t.charAt(i) !== qq) i += t.charAt(i) === '\\' ? 2 : 1; continue; }
                        if (t.charAt(i) === '(') depth++;
                        else if (t.charAt(i) === ')') { depth--; if (depth === 0) break; }
                    }
                    pseudo += '(' + normSelector(t.slice(start + 1, i), (level || 0) + 1).trim() + ')';
                    i = Math.min(n, i + 1);
                }
                emit(pseudo);
                continue;
            }
            if (identChar.test(c) || c === '\\') { emit(ident(true)); continue; }   // element name (or a keyword such as "odd")
            emit(c); i++;
        }
        return out.replace(/ $/, '');
    }

    function normValue(v) { return String(v).toLowerCase().replace(/\s+/g, ' ').replace(/\s*,\s*/g, ',').trim(); }

    /** css / css-media checks against parseCss() output. */
    function checkCss(parsed, check) {
        var selector = normSelector(check.selector || '');
        var prop = String(check.property || '').toLowerCase();
        return parsed.rules.some(function (r) {
            if (check.type === 'css-media') {
                if (!r.media || r.media.indexOf(String(check.feature || 'max-width')) === -1) return false;
            } else if (r.media) {
                return false;
            }
            if (r.selectors.indexOf(selector) === -1) return false;
            if (!Object.prototype.hasOwnProperty.call(r.declarations, prop)) return false;
            var value = normValue(r.declarations[prop]);
            if (typeof check.value === 'string') return value === normValue(check.value);
            if (typeof check.not === 'string') return value !== normValue(check.not);
            return true;
        });
    }

    /**
     * element / text / attribute checks against an inert document (DOMParser
     * output in the page; any object with querySelectorAll in tests).
     */
    function checkDom(doc, check) {
        var nodes;
        try { nodes = Array.prototype.slice.call(doc.querySelectorAll(String(check.selector || '*'))); } catch (e) { return false; }
        if (check.type === 'element') return nodes.length >= (check.min || 1);
        if (check.type === 'text') {
            return nodes.some(function (el) {
                var t = String(el.textContent || '').replace(/\s+/g, ' ').trim();
                if (typeof check.contains === 'string') return t.indexOf(check.contains) !== -1;
                return t.length >= (check.min_length || 1);
            });
        }
        if (check.type === 'attribute') {
            return nodes.some(function (el) {
                var v = el.getAttribute(String(check.name));
                return v !== null && v !== undefined && String(v).trim() !== '';   // no pattern from data: no RegExp is built from a file
            });
        }
        return false;
    }

    /** @return {boolean[]} one result per check. */
    function runChecks(doc, parsedCss, checks) {
        return (checks || []).map(function (c) {
            return (c.type === 'css' || c.type === 'css-media') ? checkCss(parsedCss, c) : checkDom(doc, c);
        });
    }

    return {
        LIMITS: LIMITS, PREVIEW_CSP: PREVIEW_CSP, CHECK_TYPES: ['element', 'text', 'attribute', 'css', 'css-media'],
        purifyConfig: purifyConfig, isImageUri: isImageUri, byteLength: byteLength, overLimit: overLimit, sanitizeCss: sanitizeCss, parseCss: parseCss,
        cssProperties: cssProperties, htmlTags: htmlTags, normSelector: normSelector, nestingDepth: nestingDepth, buildDocument: buildDocument, buildExport: buildExport,
        checkCss: checkCss, checkDom: checkDom, runChecks: runChecks
    };
}));
