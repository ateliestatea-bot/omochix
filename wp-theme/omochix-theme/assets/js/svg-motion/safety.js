/**
 * SVG Motion Studio: SVG safety pipeline.
 *
 * An SVG is untrusted input. The pipeline parses it as XML (never as HTML in
 * this document), removes everything active or external, keeps internal
 * references working, and finally checks its own output with a verifier that
 * shares no state with the sanitizer. Nothing here inserts the SVG into the
 * page; display happens only in the sandboxed frame built by frame.js.
 *
 * Throws SafetyError (with a stable .code) when an input must be rejected.
 */
(function (root) {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var XLINK_NS = 'http://www.w3.org/1999/xlink';

    // Provisional until measured on real phones: see docs/motion/SVG-MOTION-S1B-RESULT.md.
    var LIMITS = Object.freeze({
        bytes: 1048576, elements: 5000, depth: 32, pathChars: 524288,
        useExpansions: 500, usePasses: 4, dataUriChars: 262144, cssChars: 32768
    });

    var ELEMENTS = Object.freeze([
        'svg', 'g', 'defs', 'symbol', 'title', 'desc',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan',
        'linearGradient', 'radialGradient', 'stop', 'pattern', 'clipPath', 'mask', 'marker',
        'image', 'style',
        'filter', 'feGaussianBlur', 'feOffset', 'feBlend', 'feColorMatrix', 'feComposite', 'feFlood',
        'feMerge', 'feMergeNode', 'feDropShadow', 'feComponentTransfer', 'feFuncR', 'feFuncG', 'feFuncB',
        'feFuncA', 'feMorphology', 'feTurbulence', 'feDisplacementMap'
    ]);

    // Removed with their content before DOMPurify runs.
    var REMOVE = 'script,foreignObject,iframe,embed,object,animate,animateTransform,animateMotion,set,discard,mpath,tref,altGlyph,font,font-face,feImage,view,cursor,handler,listener,base,link,meta';
    // Replaced by their children: the drawing stays, the behaviour goes.
    var UNWRAP = 'a,switch';

    var ATTRIBUTES = Object.freeze([
        'id', 'class', 'viewBox', 'preserveAspectRatio', 'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2',
        'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'fr', 'd', 'points', 'transform', 'overflow',
        'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity', 'color',
        'clip-path', 'clip-rule', 'clipPathUnits', 'mask', 'maskUnits', 'maskContentUnits',
        'filter', 'filterUnits', 'primitiveUnits', 'marker-start', 'marker-mid', 'marker-end',
        'markerWidth', 'markerHeight', 'markerUnits', 'refX', 'refY', 'orient',
        'patternUnits', 'patternContentUnits', 'patternTransform', 'gradientUnits', 'gradientTransform',
        'spreadMethod', 'offset', 'stop-color', 'stop-opacity',
        'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline',
        'letter-spacing', 'dx', 'dy', 'rotate', 'textLength', 'lengthAdjust',
        'in', 'in2', 'result', 'stdDeviation', 'mode', 'type', 'values', 'operator', 'k1', 'k2', 'k3', 'k4',
        'flood-color', 'flood-opacity', 'tableValues', 'slope', 'intercept', 'amplitude', 'exponent',
        'baseFrequency', 'numOctaves', 'seed', 'stitchTiles', 'scale', 'xChannelSelector', 'yChannelSelector', 'radius',
        'href', 'xlink:href', 'style', 'visibility', 'display', 'mix-blend-mode', 'paint-order', 'vector-effect',
        'shape-rendering', 'pathLength', 'xmlns', 'xmlns:xlink', 'version', 'role', 'aria-label', 'aria-hidden', 'xml:space'
    ]);

    var CSS_PROPERTIES = Object.freeze([
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'opacity', 'color',
        'clip-rule', 'visibility', 'display', 'font-family', 'font-size', 'font-weight', 'font-style',
        'letter-spacing', 'text-anchor', 'dominant-baseline', 'stop-color', 'stop-opacity', 'flood-color',
        'flood-opacity', 'paint-order', 'mix-blend-mode', 'vector-effect', 'shape-rendering', 'clip-path',
        'mask', 'filter', 'marker-start', 'marker-mid', 'marker-end', 'transform', 'transform-origin', 'transform-box'
    ]);

    var URL_ATTRIBUTES = ['fill', 'stroke', 'clip-path', 'mask', 'filter', 'marker-start', 'marker-mid', 'marker-end'];
    var REF_HREF_ELEMENTS = ['linearGradient', 'radialGradient', 'pattern', 'filter'];
    var IMAGE_URI = /^data:image\/(?:png|jpeg|webp);base64,[a-z0-9+\/=\s]+$/i;
    var LOCAL_REF = /^#[A-Za-z_][\w.:-]*$/;
    var SAFE_SELECTOR = /^[a-zA-Z#.\s,>_0-9*-]+$/;
    // var(): custom properties are removed, so a reference would resolve to nothing and break the colour it set.
    var BANNED_VALUE = /(?:image-set|cross-fade|element\(|paint\(|src\(|var\(|expression|@import|javascript:|vbscript:|data:)/i;
    var ANY_URL = /url\s*\(/ig;
    var LOCAL_URL = /url\(\s*(["']?)#[A-Za-z_][\w.:-]*\1\s*\)/ig;

    function SafetyError(code, detail) {
        this.name = 'SafetyError';
        this.code = code;
        this.message = code + (detail ? ': ' + detail : '');
    }
    SafetyError.prototype = Object.create(Error.prototype);

    /** True when every url() in the value is url(#id) and nothing else can fetch or escape. */
    function isSafeValue(value) {
        value = String(value);
        if (/[\\<>]|\/\*/.test(value) || BANNED_VALUE.test(value)) return false;
        return (value.match(ANY_URL) || []).length === (value.match(LOCAL_URL) || []).length;
    }

    function rebuildDeclarations(style, report) {
        var out = [];
        for (var i = 0; i < style.length; i++) {
            var name = style[i];
            var value = style.getPropertyValue(name);
            if (CSS_PROPERTIES.indexOf(name) !== -1 && isSafeValue(value)) out.push(name + ':' + value);
            else report.removed.push('css:' + name);
        }
        return out.join(';');
    }

    /* A constructed sheet is parsed by the browser (so CSS escapes are already
       resolved), ignores @import and is never adopted, so nothing can load. */
    function parseCss(text) {
        var sheet = new CSSStyleSheet();
        sheet.replaceSync(text);
        return sheet;
    }

    function rebuildStylesheet(text, report) {
        if (text.length > LIMITS.cssChars) { report.removed.push('css:too-large'); return ''; }
        var sheet;
        try { sheet = parseCss(text); } catch (error) { report.removed.push('css:unparseable'); return ''; }
        var out = [];
        for (var i = 0; i < sheet.cssRules.length; i++) {
            var rule = sheet.cssRules[i];
            if (rule.type !== 1) { report.removed.push('css:at-rule'); continue; }
            if (!SAFE_SELECTOR.test(rule.selectorText)) { report.removed.push('css:selector'); continue; }
            var declarations = rebuildDeclarations(rule.style, report);
            if (declarations) out.push(rule.selectorText + '{' + declarations + '}');
        }
        return out.join('');
    }

    function rebuildInlineStyle(text, report) {
        try {
            var rule = parseCss('x{' + text + '}').cssRules[0];
            return rule && rule.style ? rebuildDeclarations(rule.style, report) : '';
        } catch (error) {
            return '';
        }
    }

    function hrefOf(element) {
        return element.getAttribute('href') || element.getAttributeNS(XLINK_NS, 'href') || '';
    }

    function maxDepth(element) {
        var max = 0;
        (function walk(node, depth) {
            if (depth > max) max = depth;
            if (depth > LIMITS.depth + 1) return;
            for (var i = 0; i < node.children.length; i++) walk(node.children[i], depth + 1);
        }(element, 1));
        return max;
    }

    function countElements(doc) {
        return doc.getElementsByTagName('*').length;
    }

    /* <use> becomes a copy of its target: animatable like any other part, and
       the expansion count stops nested-use bombs. Cycles end at the pass limit. */
    function expandUse(doc, report) {
        var expansions = 0;
        for (var pass = 0; pass <= LIMITS.usePasses; pass++) {
            var uses = Array.prototype.slice.call(doc.getElementsByTagNameNS(SVG_NS, 'use'));
            if (!uses.length) return;
            if (pass === LIMITS.usePasses) {
                uses.forEach(function (use) { use.remove(); });
                report.removed.push('use:too-deep');
                return;
            }
            uses.forEach(function (use) {
                var href = hrefOf(use);
                var target = LOCAL_REF.test(href) ? doc.getElementById(href.slice(1)) : null;
                if (!target || target.contains(use)) {
                    use.remove();
                    report.removed.push(LOCAL_REF.test(href) ? 'use:missing-or-cycle' : 'use:external');
                    return;
                }
                if (++expansions > LIMITS.useExpansions) throw new SafetyError('USE_EXPANSION_LIMIT');
                use.replaceWith(useReplacement(doc, use, target));
                if (countElements(doc) > LIMITS.elements) throw new SafetyError('ELEMENT_LIMIT');
            });
        }
    }

    function useReplacement(doc, use, target) {
        var group = doc.createElementNS(SVG_NS, 'g');
        var x = parseFloat(use.getAttribute('x')) || 0;
        var y = parseFloat(use.getAttribute('y')) || 0;
        var transform = [use.getAttribute('transform'), x || y ? 'translate(' + x + ' ' + y + ')' : ''].filter(Boolean).join(' ');
        if (transform) group.setAttribute('transform', transform);
        Array.prototype.forEach.call(use.attributes, function (attribute) {
            if (['href', 'xlink:href', 'x', 'y', 'transform', 'width', 'height'].indexOf(attribute.name) === -1) {
                group.setAttribute(attribute.name, attribute.value);
            }
        });
        var copy;
        if (target.localName === 'symbol') {
            copy = doc.createElementNS(SVG_NS, 'svg');
            ['viewBox', 'preserveAspectRatio'].forEach(function (name) {
                if (target.hasAttribute(name)) copy.setAttribute(name, target.getAttribute(name));
            });
            copy.setAttribute('width', use.getAttribute('width') || target.getAttribute('width') || '100%');
            copy.setAttribute('height', use.getAttribute('height') || target.getAttribute('height') || '100%');
            copy.setAttribute('overflow', 'visible');
            Array.prototype.forEach.call(target.childNodes, function (child) { copy.appendChild(child.cloneNode(true)); });
        } else {
            copy = target.cloneNode(true);
            copy.removeAttribute('id');
        }
        Array.prototype.forEach.call(copy.querySelectorAll('[id]'), function (element) { element.removeAttribute('id'); });
        group.appendChild(copy);
        return group;
    }

    function sanitizeTree(rootElement, report) {
        var purifier = root.DOMPurify();
        purifier.addHook('afterSanitizeAttributes', function (element) {
            if (!element.getAttribute) return;
            Array.prototype.slice.call(element.attributes || []).forEach(function (attribute) {
                var name = attribute.name.toLowerCase();
                var value = attribute.value;
                if (name.indexOf('on') === 0) { element.removeAttributeNode(attribute); return; }
                if (name === 'href' || name === 'xlink:href') {
                    var tag = element.localName;
                    var allowed = tag === 'image'
                        ? IMAGE_URI.test(value) && value.length <= LIMITS.dataUriChars
                        : LOCAL_REF.test(value) && REF_HREF_ELEMENTS.indexOf(tag) !== -1;
                    if (!allowed) { report.removed.push('href:' + tag); element.removeAttributeNode(attribute); }
                    return;
                }
                if (URL_ATTRIBUTES.indexOf(name) !== -1 && !isSafeValue(value)) {
                    report.removed.push('url:' + name);
                    element.removeAttributeNode(attribute);
                }
            });
        });
        // Arrays are copied: DOMPurify lowercases what it is given, in place.
        // ALLOWED_URI_REGEXP stays at its default: narrowing it strips geometry; the hook above decides hrefs.
        purifier.sanitize(rootElement, {
            IN_PLACE: true,
            ALLOWED_TAGS: ELEMENTS.concat(['#text']),
            ALLOWED_ATTR: ATTRIBUTES.slice(),
            ALLOWED_NAMESPACES: [SVG_NS, XLINK_NS, 'http://www.w3.org/1999/xhtml'],
            KEEP_CONTENT: false,
            ALLOW_DATA_ATTR: false,
            ALLOW_ARIA_ATTR: true
        });
        purifier.removeAllHooks();
        // <image> without a usable source is dead weight.
        Array.prototype.slice.call(rootElement.querySelectorAll('image')).forEach(function (image) {
            if (!hrefOf(image)) image.remove();
        });
    }

    function prefixIds(rootElement, prefix) {
        var map = {};
        Array.prototype.forEach.call(rootElement.querySelectorAll('[id]'), function (element) {
            map[element.id] = prefix + element.id;
            element.id = prefix + element.id;
        });
        if (rootElement.id) { map[rootElement.id] = prefix + rootElement.id; rootElement.id = prefix + rootElement.id; }
        function rewriteUrls(text) {
            return text.replace(/url\(\s*(["']?)#([\w.:-]+)\1\s*\)/g, function (match, quote, id) {
                return Object.prototype.hasOwnProperty.call(map, id) ? 'url(#' + map[id] + ')' : match;
            });
        }
        Array.prototype.forEach.call(rootElement.querySelectorAll('*'), function (element) {
            Array.prototype.slice.call(element.attributes).forEach(function (attribute) {
                if (attribute.value.indexOf('url(') !== -1) attribute.value = rewriteUrls(attribute.value);
                if ((attribute.name === 'href' || attribute.name === 'xlink:href') && attribute.value.charAt(0) === '#' &&
                    Object.prototype.hasOwnProperty.call(map, attribute.value.slice(1))) {
                    attribute.value = '#' + map[attribute.value.slice(1)];
                }
            });
            if (element.localName === 'style') {
                element.textContent = rewriteUrls(element.textContent).replace(/#([A-Za-z_][\w-]*)(?=[^{}]*\{)/g, function (match, id) {
                    return Object.prototype.hasOwnProperty.call(map, id) ? '#' + map[id] : match;
                });
            }
        });
    }

    /**
     * @param {string} input Untrusted SVG text.
     * @param {{prefix?: string}} [options] prefix: added to every id and internal reference.
     * @return {{svg: string, report: {removed: string[]}, elements: number}}
     */
    function normalize(input, options) {
        options = options || {};
        var report = { removed: [] };
        if (typeof input !== 'string') throw new SafetyError('NOT_STRING');
        if (new Blob([input]).size > LIMITS.bytes) throw new SafetyError('TOO_LARGE');
        var text = input.replace(/^﻿/, '');
        // Entity tricks are refused before any parser sees them.
        if (/<!DOCTYPE|<!ENTITY/i.test(text)) throw new SafetyError('DOCTYPE_REJECTED');

        var doc = new DOMParser().parseFromString(text, 'image/svg+xml');
        if (doc.querySelector('parsererror')) throw new SafetyError('PARSE_ERROR');
        var svg = doc.documentElement;
        if (svg.namespaceURI !== SVG_NS || svg.localName !== 'svg' || svg.prefix) throw new SafetyError('NOT_SVG');
        if (countElements(doc) > LIMITS.elements) throw new SafetyError('ELEMENT_LIMIT');
        if (maxDepth(svg) > LIMITS.depth) throw new SafetyError('DEPTH_LIMIT');
        var pathChars = 0;
        Array.prototype.forEach.call(doc.getElementsByTagNameNS(SVG_NS, 'path'), function (path) {
            pathChars += (path.getAttribute('d') || '').length;
        });
        if (pathChars > LIMITS.pathChars) throw new SafetyError('PATH_LIMIT');

        // Processing instructions (xml-stylesheet) and comments live outside the root or are removed by DOMPurify.
        Array.prototype.slice.call(doc.childNodes).forEach(function (node) {
            if (node.nodeType !== 1) node.remove();
        });
        Array.prototype.slice.call(doc.querySelectorAll(REMOVE)).forEach(function (element) {
            report.removed.push('element:' + element.localName);
            element.remove();
        });
        Array.prototype.slice.call(doc.querySelectorAll(UNWRAP)).forEach(function (element) {
            report.removed.push('element:' + element.localName);
            element.replaceWith.apply(element, Array.prototype.slice.call(element.childNodes));
        });
        Array.prototype.slice.call(doc.getElementsByTagName('*')).forEach(function (element) {
            if (element.namespaceURI !== SVG_NS) { report.removed.push('namespace:' + element.localName); element.remove(); }
        });

        expandUse(doc, report);

        Array.prototype.slice.call(doc.getElementsByTagNameNS(SVG_NS, 'style')).forEach(function (style) {
            var css = rebuildStylesheet(style.textContent, report);
            while (style.firstChild) style.removeChild(style.firstChild);
            if (css) style.textContent = css; else style.remove();
        });
        Array.prototype.forEach.call(doc.querySelectorAll('[style]'), function (element) {
            var css = rebuildInlineStyle(element.getAttribute('style'), report);
            if (css) element.setAttribute('style', css); else element.removeAttribute('style');
        });

        try {
            sanitizeTree(svg, report);
        } catch (error) {
            throw new SafetyError('SANITIZE_FAILED', error.message);
        }
        if (options.prefix) prefixIds(svg, options.prefix);

        var out = new XMLSerializer().serializeToString(svg);
        var problems = verify(out);
        if (problems.length) throw new SafetyError('VERIFY_FAILED', problems.join('|'));
        return { svg: out, report: report, elements: svg.querySelectorAll('*').length + 1 };
    }

    /* ---------------------------------------------------------------- verify
       Independent of the sanitizer: re-reads the text under both parsers, since
       the output may later be placed in an HTML document. */
    function verifyTree(element, parser, problems, extraAttribute) {
        var all = [element].concat(Array.prototype.slice.call(element.querySelectorAll('*')));
        all.forEach(function (node) {
            var name = node.localName;
            if (ELEMENTS.indexOf(name) === -1) problems.push(parser + ':element:' + name);
            if (node.namespaceURI !== SVG_NS) problems.push(parser + ':namespace:' + name);
            Array.prototype.forEach.call(node.attributes, function (attribute) {
                var key = attribute.name.toLowerCase();
                var value = attribute.value;
                if (key.indexOf('on') === 0) problems.push(parser + ':handler:' + key);
                if (ATTRIBUTES.indexOf(attribute.name) === -1 && !(extraAttribute && extraAttribute.test(attribute.name)) &&
                    !(parser === 'html' && lowerCaseAllowed(attribute.name))) {
                    problems.push(parser + ':attribute:' + attribute.name);
                }
                if (key === 'href' || key === 'xlink:href') {
                    if (!(LOCAL_REF.test(value) || (name === 'image' && IMAGE_URI.test(value)))) problems.push(parser + ':href:' + name);
                } else if (/url\s*\(/i.test(value) || key === 'style') {
                    if (!isSafeValue(value)) problems.push(parser + ':value:' + key);
                }
            });
            if (name === 'style') {
                var css = node.textContent;
                if (css.indexOf('@') !== -1 || !isSafeValue(css)) problems.push(parser + ':style');
            }
        });
    }

    function lowerCaseAllowed(name) {
        return ATTRIBUTES.some(function (allowed) { return allowed.toLowerCase() === name.toLowerCase(); });
    }

    /**
     * @param {string} text Serialized SVG.
     * @param {{extraAttribute?: RegExp}} [options] Attributes added after sanitizing (data-omx-*).
     * @return {string[]} Problems; empty when clean.
     */
    function verify(text, options) {
        var extra = options && options.extraAttribute;
        var problems = [];
        var xml = new DOMParser().parseFromString(text, 'image/svg+xml');
        if (xml.querySelector('parsererror')) return ['xml:reparse'];
        verifyTree(xml.documentElement, 'xml', problems, extra);
        var html = new DOMParser().parseFromString('<!doctype html><body>' + text, 'text/html');
        var children = html.body.children;
        if (children.length !== 1 || children[0].localName !== 'svg') problems.push('html:root-changed:' + children.length);
        Array.prototype.forEach.call(children, function (child) { verifyTree(child, 'html', problems, extra); });
        return problems;
    }

    root.OmxSvgSafety = { normalize: normalize, verify: verify, isSafeValue: isSafeValue, LIMITS: LIMITS, SafetyError: SafetyError };
}(window));
