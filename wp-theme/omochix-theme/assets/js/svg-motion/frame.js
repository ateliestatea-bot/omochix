/**
 * SVG Motion Studio: stage preparation and the sandboxed preview frame.
 *
 * prepare() wraps a normalized SVG so it can be animated: parts get a <g>
 * wrapper (CSS transforms replace the transform attribute, so the original
 * transform must stay on an inner element), shapes get pathLength=1.
 *
 * animatedSvg() gives the stand-alone SVG the studio shows as an image.
 *
 * buildDocument() returns the srcdoc for <iframe sandbox="allow-scripts">, with
 * the CSP first in <head> and a fresh nonce per frame. The studio page does not
 * use it (WebKit limits such frames to about 22fps until they are clicked). It
 * stays for the tests that compare the CSS output with WAAPI, and for later
 * features an image cannot do, such as picking a shape on screen.
 */
(function (root) {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var NON_DRAWING = ['defs', 'style', 'title', 'desc', 'symbol'];
    var GEOMETRY = 'path,rect,circle,ellipse,line,polyline,polygon';
    var EXTRA_ATTRIBUTE = /^data-omx-(?:stage|base|root|part|geometry|overlay)$/;
    var OVERLAY_MASK = 'omxoverlay-mask';
    var OVERLAY_GRADIENT = 'omxoverlay-gradient';

    function viewBoxOf(svg) {
        var parts = (svg.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(Number);
        if (parts.length === 4 && parts.every(isFinite) && parts[2] > 0 && parts[3] > 0) return parts;
        var w = parseFloat(svg.getAttribute('width')) || 100;
        var h = parseFloat(svg.getAttribute('height')) || 100;
        return [0, 0, w, h];
    }

    /**
     * @param {string} normalizedSvg Output of OmxSvgSafety.normalize().
     * @param {{maxParts?: number, maxShapes?: number, overlay?: boolean}} [options] Above maxParts the logo animates as one
     *     part; above maxShapes the presets that animate each shape are unavailable (layout.tooManyShapes).
     *     overlay:true adds a light band clipped to the logo's own shape, for logo-shine.
     * @return {{svg: string, layout: object}}
     */
    function prepare(normalizedSvg, options) {
        var doc = new DOMParser().parseFromString(normalizedSvg, 'image/svg+xml');
        var svg = doc.documentElement;
        var viewBox = viewBoxOf(svg);
        if (!svg.hasAttribute('viewBox')) svg.setAttribute('viewBox', viewBox.join(' '));
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        svg.setAttribute('data-omx-stage', '');

        var drawing = Array.prototype.filter.call(svg.children, function (child) {
            return NON_DRAWING.indexOf(child.localName) === -1;
        });
        // A single top-level group is a wrapper: its children are the real parts.
        var partSources = drawing;
        if (drawing.length === 1 && drawing[0].localName === 'g' && drawing[0].children.length > 1) {
            partSources = Array.prototype.slice.call(drawing[0].children);
        }
        var maxParts = options && options.maxParts;
        var tooMany = maxParts && partSources.length > maxParts;

        if (!tooMany) {
            partSources.forEach(function (element, index) {
                var wrapper = doc.createElementNS(SVG_NS, 'g');
                wrapper.setAttribute('data-omx-part', String(index));
                element.replaceWith(wrapper);
                wrapper.appendChild(element);
            });
        }
        var rootGroup = doc.createElementNS(SVG_NS, 'g');
        rootGroup.setAttribute('data-omx-root', '');
        Array.prototype.slice.call(svg.children).forEach(function (child) {
            if (NON_DRAWING.indexOf(child.localName) === -1) rootGroup.appendChild(child);
        });
        // The user's position, size and rotation go here, apart from anything a preset animates.
        var baseGroup = doc.createElementNS(SVG_NS, 'g');
        baseGroup.setAttribute('data-omx-base', '');
        baseGroup.appendChild(rootGroup);
        svg.appendChild(baseGroup);

        var geometry = Array.prototype.filter.call(rootGroup.querySelectorAll(GEOMETRY), function (element) {
            var ancestor = element.closest('clipPath,mask,pattern,marker,defs');
            return !ancestor;
        });
        var ownStroke = [];
        var fillOpacity = [];
        geometry.forEach(function (element, index) {
            element.setAttribute('data-omx-geometry', String(index));
            // pathLength rescales dashes, so a logo's own dash pattern keeps its real length.
            if (!element.hasAttribute('stroke-dasharray')) element.setAttribute('pathLength', '1');
            var stroke = element.getAttribute('stroke');
            ownStroke.push(!!stroke && stroke !== 'none');
            fillOpacity.push(element.getAttribute('fill-opacity') || '1');
        });

        if (options && options.overlay && geometry.length <= (options.maxShapes || Infinity)) addOverlay(doc, svg, baseGroup, rootGroup, viewBox);

        return {
            svg: new XMLSerializer().serializeToString(svg),
            layout: {
                parts: tooMany ? 1 : partSources.length,
                geometry: geometry.length,
                width: viewBox[2],
                height: viewBox[3],
                tooManyShapes: !!(options && options.maxShapes && geometry.length > options.maxShapes),
                ownStroke: ownStroke,
                fillOpacity: fillOpacity
            }
        };
    }

    /* A copy of the logo is the mask (its alpha, so any fill colour works); the band moves behind that mask. */
    function addOverlay(doc, svg, baseGroup, rootGroup, viewBox) {
        function make(name, attributes) {
            var element = doc.createElementNS(SVG_NS, name);
            Object.keys(attributes).forEach(function (key) { element.setAttribute(key, attributes[key]); });
            return element;
        }
        var defs = make('defs', {});
        var gradient = make('linearGradient', { id: OVERLAY_GRADIENT, x1: '0', y1: '0', x2: '1', y2: '0.35' });
        [['0', '0'], ['0.5', '1'], ['1', '0']].forEach(function (stop) {
            gradient.appendChild(make('stop', { offset: stop[0], 'stop-color': '#ffffff', 'stop-opacity': stop[1] }));
        });
        var mask = make('mask', {
            id: OVERLAY_MASK, style: 'mask-type:alpha', maskUnits: 'userSpaceOnUse',
            x: String(viewBox[0]), y: String(viewBox[1]), width: String(viewBox[2]), height: String(viewBox[3])
        });
        var copy = rootGroup.cloneNode(true);
        [copy].concat(Array.prototype.slice.call(copy.querySelectorAll('*'))).forEach(function (element) {
            Array.prototype.slice.call(element.attributes).forEach(function (attribute) {
                if (attribute.name === 'id' || attribute.name.indexOf('data-omx-') === 0) element.removeAttribute(attribute.name);
            });
        });
        mask.appendChild(copy);
        defs.appendChild(gradient);
        defs.appendChild(mask);
        var holder = make('g', { mask: 'url(#' + OVERLAY_MASK + ')' });
        // Starts fully left of the logo, so at rest the logo looks untouched.
        var band = make('rect', {
            'data-omx-overlay': '', x: String(viewBox[0] - viewBox[2] * 0.6), y: String(viewBox[1] - viewBox[3] * 0.2),
            width: String(viewBox[2] * 0.6), height: String(viewBox[3] * 1.4), fill: 'url(#' + OVERLAY_GRADIENT + ')'
        });
        holder.appendChild(band);
        svg.insertBefore(defs, baseGroup);
        baseGroup.appendChild(holder);
    }

    function nonce() {
        var bytes = new Uint8Array(16);
        root.crypto.getRandomValues(bytes);
        return Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    }

    function contentSecurityPolicy(value) {
        return "default-src 'none'; script-src 'nonce-" + value + "'; style-src 'unsafe-inline'; img-src data:; base-uri 'none'; form-action 'none'";
    }

    /* Runs inside the frame. Serialized with toString(), so it must not refer to anything outside itself. */
    /* eslint-disable */
    function frameRuntime() {
        var parentWindow = window.parent;
        var PROPERTIES = /*PROPERTIES*/[];
        var running = [];
        var plan = null;
        var reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        var svg = document.querySelector('svg');

        // A link or a meta refresh must never move this frame, even if one slipped through.
        // The click also tells the parent to toggle playback: in WebKit a sandboxed frame stays at about 22fps until it is clicked directly.
        document.addEventListener('click', function (event) { event.preventDefault(); report('omx:stage-click'); }, true);

        function select(target) {
            if (target.kind === 'stage') return svg;
            if (target.kind === 'base') return svg.querySelector('[data-omx-base]');
            if (target.kind === 'overlay') return svg.querySelector('[data-omx-overlay]');
            if (target.kind === 'root') return svg.querySelector('[data-omx-root]');
            var attribute = target.kind === 'part' ? 'data-omx-part' : 'data-omx-geometry';
            return svg.querySelector('[' + attribute + '="' + Number(target.index) + '"]');
        }
        function camel(name) { return name.replace(/-([a-z])/g, function (m, c) { return c.toUpperCase(); }); }
        function valueOk(value) { return /^[a-zA-Z0-9 .,()%#+-]{1,80}$/.test(String(value)) && !/url/i.test(String(value)); }
        function propsOk(props) {
            return Object.keys(props || {}).every(function (key) { return PROPERTIES.indexOf(key) !== -1 && valueOk(props[key]); });
        }
        function planOk(p) {
            return p && Array.isArray(p.tracks) && Array.isArray(p.setup) &&
                p.setup.every(function (s) { return propsOk(s.styles); }) &&
                p.tracks.every(function (t) { return Array.isArray(t.frames) && t.frames.every(function (f) { return propsOk(f.props); }) && valueOk(t.easing); });
        }
        function clear() {
            running.forEach(function (a) { a.cancel(); });
            running = [];
            Array.prototype.forEach.call(svg.querySelectorAll('[data-omx-base],[data-omx-overlay],[data-omx-root],[data-omx-part],[data-omx-geometry]'), function (el) {
                el.removeAttribute('style');
            });
            svg.style.removeProperty('opacity');
        }
        function report(type, extra) {
            var data = { type: type, playing: running.some(function (a) { return a.playState === 'running'; }), time: running.length ? Number(running[0].currentTime) || 0 : 0 };
            if (extra) Object.keys(extra).forEach(function (k) { data[k] = extra[k]; });
            parentWindow.postMessage(data, '*');
        }
        function applyStyles(list) {
            list.forEach(function (item) {
                var el = select(item.target);
                if (!el) return;
                Object.keys(item.styles).forEach(function (key) { el.style.setProperty(key, item.styles[key]); });
            });
        }
        function build(next, autoplay) {
            clear();
            plan = next;
            applyStyles(plan.setup);
            plan.tracks.forEach(function (t) {
                var el = select(t.target);
                if (!el) return;
                var frames = t.frames.map(function (f) {
                    // Per keyframe, as CSS applies it; a whole-iteration easing would make the preview and the CSS output differ.
                    var out = { offset: f.offset, easing: t.easing };
                    Object.keys(f.props).forEach(function (key) { out[camel(key)] = f.props[key]; });
                    return out;
                });
                var animation = el.animate(frames, {
                    duration: t.duration, delay: t.delay, iterations: t.iterations === null ? Infinity : t.iterations,
                    direction: t.direction, fill: 'both'
                });
                animation.pause();
                animation.onfinish = function () { report('omx:finished'); };
                running.push(animation);
            });
            if (autoplay && !reduced) running.forEach(function (a) { a.play(); });
            else if (plan.rest === 'end') seek(plan.length);
            else seek(0);
            report('omx:state');
        }
        function seek(ms) {
            running.forEach(function (a) { a.pause(); a.currentTime = ms; });
        }

        window.addEventListener('message', function (event) {
            if (event.source !== parentWindow || !event.data || typeof event.data !== 'object') return;
            var data = event.data;
            if (data.type === 'omx:plan') {
                if (planOk(data.plan)) build(data.plan, !!data.autoplay);
                else report('omx:rejected');
            } else if (data.type === 'omx:play') {
                if (!plan) return;
                var finished = running.every(function (a) { return a.playState === 'finished' || (plan.rest === 'end' && Number(a.currentTime) >= plan.length); });
                if (finished) seek(0);
                running.forEach(function (a) { a.play(); });
                report('omx:state');
            } else if (data.type === 'omx:pause') {
                running.forEach(function (a) { a.pause(); });
                report('omx:state');
            } else if (data.type === 'omx:reset') {
                seek(plan && plan.rest === 'end' && reduced ? plan.length : 0);
                report('omx:state');
            } else if (data.type === 'omx:seek') {
                seek(Number(data.time) || 0);
                report('omx:state');
            } else if (data.type === 'omx:theme') {
                document.documentElement.setAttribute('data-theme', data.theme === 'dark' ? 'dark' : 'light');
            } else if (data.type === 'omx:probe') {
                // Automated preview verification: computed values for every animated target.
                var values = {};
                plan.tracks.forEach(function (t, i) {
                    var el = select(t.target);
                    if (!el) return;
                    var style = getComputedStyle(el);
                    values[i] = {};
                    Object.keys(t.frames[0].props).forEach(function (key) { values[i][key] = style.getPropertyValue(key); });
                });
                report('omx:probe', { id: data.id, values: values, reduced: reduced });
            }
        });
        report('omx:ready');
    }
    /* eslint-enable */

    /**
     * @param {string} preparedSvg Output of prepare().svg, verified.
     * @return {string} srcdoc
     */
    function buildDocument(preparedSvg, options) {
        var problems = root.OmxSvgSafety.verify(preparedSvg, { extraAttribute: EXTRA_ATTRIBUTE });
        if (problems.length) throw new root.OmxSvgSafety.SafetyError('VERIFY_FAILED', problems.join('|'));
        var value = nonce();
        var theme = options && options.theme === 'dark' ? 'dark' : 'light';
        var runtime = '(' + frameRuntime.toString().replace('/*PROPERTIES*/[]', JSON.stringify(root.OmxSvgCompile.PROPERTIES)) + '())';
        return '<!doctype html><html data-theme="' + theme + '"><head><meta charset="utf-8">' +
            '<meta http-equiv="Content-Security-Policy" content="' + contentSecurityPolicy(value) + '">' +
            '<style>html,body{height:100%;margin:0;background:transparent}body{display:grid;place-items:center;overflow:hidden}' +
            'svg{display:block;width:100%;height:100%;overflow:hidden}' +
            '[data-omx-root],[data-omx-part],[data-omx-geometry]{transform-box:fill-box;transform-origin:center}[data-omx-stage]{transform-origin:center}[data-omx-base]{transform-box:view-box;transform-origin:center}</style>' +
            '</head><body>' + preparedSvg +
            '<script nonce="' + value + '">' + runtime.replace(/<\/script/gi, '<\\/script') + '</' + 'script></body></html>';
    }

    /**
     * Stand-alone SVG text with CSS animation: for <img>, no script and no network by construction.
     * @param {string} preparedSvg Verified output of prepare().
     * @param {object} plan
     * @param {boolean|{animate?: boolean, time?: number, frozen?: boolean}} [options]
     */
    function animatedSvg(preparedSvg, plan, options) {
        var css = root.OmxSvgCompile.planToCss(plan, typeof options === 'object' ? options : { animate: options });
        var doc = new DOMParser().parseFromString(preparedSvg, 'image/svg+xml');
        var style = doc.createElementNS(SVG_NS, 'style');
        style.textContent = css;
        doc.documentElement.insertBefore(style, doc.documentElement.firstChild);
        return new XMLSerializer().serializeToString(doc);
    }

    /** Problems in a prepared stage; empty when clean. */
    function verifyPrepared(preparedSvg) {
        return root.OmxSvgSafety.verify(preparedSvg, { extraAttribute: EXTRA_ATTRIBUTE });
    }

    root.OmxSvgFrame = { prepare: prepare, buildDocument: buildDocument, animatedSvg: animatedSvg, verifyPrepared: verifyPrepared, EXTRA_ATTRIBUTE: EXTRA_ATTRIBUTE };
}(window));
