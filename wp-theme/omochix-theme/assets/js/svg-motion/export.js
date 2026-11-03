/**
 * SVG Motion Studio: files to take away.
 *
 * Every output is built from the same plan and the same CSS generator the
 * preview uses (OmxSvgCompile.planToCss), with all rules limited to one class
 * so several logos can share a page. The logo goes through the safety pipeline
 * again before export, and every output is checked before it is offered,
 * because it will run on other people's sites.
 */
(function (root) {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';
    var FORMAT = 'omochix-svg-motion';
    var SCHEMA = 1;
    var JSON_LIMIT = 2097152;

    function fnv(text) {
        var hash = 0x811c9dc5;
        for (var i = 0; i < text.length; i++) {
            hash ^= text.charCodeAt(i);
            hash = Math.imul(hash, 0x01000193) >>> 0;
        }
        return ('0000000' + hash.toString(16)).slice(-8);
    }

    function escapeHtml(text) {
        return String(text).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* A logo's own stylesheet would apply to the whole page once the SVG is pasted inline: every selector is
       limited to this logo. The rules were already rebuilt by the safety pipeline (simple selectors only). */
    function scopeOwnStyles(svg, scope) {
        Array.prototype.forEach.call(svg.querySelectorAll('style'), function (style) {
            var sheet = new CSSStyleSheet();
            sheet.replaceSync(style.textContent);
            var rules = [];
            Array.prototype.forEach.call(sheet.cssRules, function (rule) {
                if (rule.type !== 1) return;
                var selectors = rule.selectorText.split(',').map(function (part) {
                    part = part.trim();
                    return /^svg\b/.test(part) ? 'svg.' + scope + part.slice(3) : '.' + scope + ' ' + part;
                });
                rules.push(selectors.join(',') + '{' + rule.style.cssText + '}');
            });
            if (rules.length) style.textContent = rules.join(''); else style.remove();
        });
    }

    /* The exported SVG: the logo with a class, an accessible name, and optionally the generated style. */
    function svgText(preparedSvg, scope, css, label) {
        var doc = new DOMParser().parseFromString(preparedSvg, 'image/svg+xml');
        var svg = doc.documentElement;
        scopeOwnStyles(svg, scope);
        svg.setAttribute('class', scope);
        svg.setAttribute('role', 'img');
        if (label) svg.setAttribute('aria-label', label); else svg.setAttribute('aria-hidden', 'true');
        if (css) {
            var style = doc.createElementNS(SVG_NS, 'style');
            style.textContent = css;
            svg.insertBefore(style, svg.firstChild);
        }
        return new XMLSerializer().serializeToString(svg);
    }

    /* Generated CSS only ever contains these characters and no fetching function. */
    function cssIsClean(css) {
        return /^[\w\s.#%(),:;{}\[\]="'*>+\-@]*$/.test(css) &&
            !/url\s*\(|@import|@font-face|expression|javascript:|<|\\/i.test(css);
    }

    /**
     * Problems in an exported SVG; empty when clean. The generated style is checked apart from the logo,
     * which goes through the same verifier as everything else.
     */
    function verifyExport(text) {
        var doc = new DOMParser().parseFromString(text, 'image/svg+xml');
        if (doc.querySelector('parsererror')) return ['parse'];
        var svg = doc.documentElement;
        var problems = [];
        var cls = svg.getAttribute('class');
        if (!/^omx-[a-z0-9]{8}$/.test(cls || '')) problems.push('class');
        // The generated style, when present, is the first child; the logo's own styles stay and are checked below.
        var first = svg.firstElementChild;
        if (first && first.localName === 'style' && /@keyframes|^\.omx-|^svg\.omx-/.test(first.textContent)) {
            if (!cssIsClean(first.textContent)) problems.push('style');
            first.remove();
        }
        // Every remaining rule must be limited to this logo.
        Array.prototype.forEach.call(svg.querySelectorAll('style'), function (style) {
            var sheet = new CSSStyleSheet();
            sheet.replaceSync(style.textContent);
            Array.prototype.forEach.call(sheet.cssRules, function (rule) {
                var ok = rule.type === 1 && rule.selectorText.split(',').every(function (part) {
                    part = part.trim();
                    return part.indexOf('.' + cls + ' ') === 0 || part.indexOf('svg.' + cls) === 0;
                });
                if (!ok) problems.push('style:scope');
            });
        });
        return problems.concat(root.OmxSvgSafety.verify(new XMLSerializer().serializeToString(svg), { extraAttribute: root.OmxSvgFrame.EXTRA_ATTRIBUTE }));
    }

    /* JavaScript output: plays the same plan with Web Animations, starts when the logo comes into view,
       respects reduced motion, and exposes play, pause and restart. Generated, never evaluated here. */
    function runnerText(plan, scope) {
        var sel = function (target) { return root.OmxSvgCompile.scopedSelector(target, scope); };
        var camel = function (name) { return name.replace(/-([a-z])/g, function (m, c) { return c.toUpperCase(); }); };
        var data = {
            scope: scope,
            rest: plan.restTime,
            setup: plan.setup.map(function (s) { return { s: sel(s.target), styles: s.styles }; }),
            boxes: plan.tracks.map(function (t) { return { s: sel(t.target), box: t.target.kind === 'stage' ? '' : (t.target.kind === 'base' ? 'view-box' : 'fill-box') }; }),
            tracks: plan.tracks.map(function (t) {
                return {
                    s: sel(t.target),
                    frames: t.frames.map(function (f) {
                        var out = { offset: f.offset, easing: t.easing };
                        Object.keys(f.props).forEach(function (key) { out[camel(key)] = f.props[key]; });
                        return out;
                    }),
                    options: { duration: t.duration, delay: t.delay, iterations: t.iterations === Infinity ? -1 : t.iterations, direction: t.direction, fill: 'both' }
                };
            })
        };
        return '/* OmochiX SVG Motion Studio — ' + scope + ' */\n' +
            '(function () {\n' +
            "  'use strict';\n" +
            '  var D = ' + JSON.stringify(data).replace(/</g, '\\u003c') + ';\n' +
            '  var svg = document.querySelector("svg." + D.scope);\n' +
            '  if (!svg || !svg.animate) return;\n' +
            '  var reduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;\n' +
            '  D.setup.forEach(function (x) { var e = document.querySelector(x.s); if (e) Object.keys(x.styles).forEach(function (k) { e.style.setProperty(k, x.styles[k]); }); });\n' +
            '  D.boxes.forEach(function (x) { var e = document.querySelector(x.s); if (!e) return; if (x.box) e.style.transformBox = x.box; e.style.transformOrigin = "center"; });\n' +
            '  var list = D.tracks.map(function (t) { var e = document.querySelector(t.s); if (!e) return null; var o = Object.assign({}, t.options); if (o.iterations < 0) o.iterations = Infinity; var a = e.animate(t.frames, o); a.pause(); a.currentTime = reduced ? D.rest : 0; return a; }).filter(Boolean);\n' +
            '  function play() { list.forEach(function (a) { if (a.playState === "finished") a.currentTime = 0; a.play(); }); }\n' +
            '  function pause() { list.forEach(function (a) { a.pause(); }); }\n' +
            '  function restart() { list.forEach(function (a) { a.currentTime = 0; a.play(); }); }\n' +
            '  window.omxLogoMotion = window.omxLogoMotion || {};\n' +
            '  window.omxLogoMotion[D.scope] = { play: play, pause: pause, restart: restart };\n' +
            '  if (reduced) return;\n' +
            '  if (!("IntersectionObserver" in window)) { play(); return; }\n' +
            '  var seen = new IntersectionObserver(function (entries) { if (entries[0].isIntersecting) { seen.disconnect(); play(); } }, { threshold: 0.5 });\n' +
            '  seen.observe(svg);\n' +
            '})();\n';
    }

    /**
     * @param {{svg: string, preset: object, params: object, common: object, commonList: object[], budget: object, label: string}} input
     *     svg: the normalized logo as kept by the studio.
     * @return {object} Text of every output, file names and the scope. Throws when anything fails a check.
     */
    function build(input) {
        var settings = { preset: input.preset.id, params: root.OmxSvgCompile.sanitizeParams(input.preset, input.params), common: root.OmxSvgCompile.sanitizeCommon(input.commonList, input.common) };
        var scope = 'omx-' + fnv(input.svg + JSON.stringify(settings));
        // Again through the whole pipeline: the export carries its own id prefix, so pasted logos never share ids.
        var normalized = root.OmxSvgSafety.normalize(input.svg, { prefix: scope + '-' });
        var stage = root.OmxSvgFrame.prepare(normalized.svg, Object.assign({ overlay: input.preset.structure === 'overlay' }, input.budget));
        var plan = root.OmxSvgCompile.buildPlan(input.preset, settings.params, stage.layout, settings.common);
        var label = String(input.label || '').slice(0, 120);
        var css = root.OmxSvgCompile.planToCss(plan, { scope: scope, reducedRest: true });
        var restCss = root.OmxSvgCompile.planToCss(plan, { scope: scope, animate: false });
        if (!cssIsClean(css) || !cssIsClean(restCss)) throw new root.OmxSvgSafety.SafetyError('EXPORT_FAILED', 'css');
        var animated = svgText(stage.svg, scope, css, label);
        var still = svgText(stage.svg, scope, restCss, label);
        var bare = svgText(stage.svg, scope, '', label);
        [animated, still, bare].forEach(function (text) {
            var problems = verifyExport(text);
            if (problems.length) throw new root.OmxSvgSafety.SafetyError('EXPORT_FAILED', problems.join('|'));
        });
        var base = 'omochix-logo-motion-' + input.preset.id.replace(/^logo-/, '');
        var names = { animated: base + '.svg', still: base + '-static.svg', css: base + '.css', js: base + '.js', json: 'omochix-logo-motion-settings.json' };
        var alt = escapeHtml(label);
        return {
            scope: scope,
            plan: plan,
            names: names,
            files: {
                animated: animated,
                still: still,
                css: css + '\n',
                js: runnerText(plan, scope),
                json: JSON.stringify({ format: FORMAT, schema: SCHEMA, svg: input.svg, settings: settings }, null, 2) + '\n'
            },
            snippets: {
                image: '<picture>\n  <source media="(prefers-reduced-motion: reduce)" srcset="' + names.still + '">\n  <img src="' + names.animated + '" alt="' + alt + '" width="200" height="200">\n</picture>',
                inline: animated,
                external: '<link rel="stylesheet" href="' + names.css + '">\n' + bare,
                script: bare + '\n<script src="' + names.js + '" defer></script>'
            }
        };
    }

    /**
     * Reads settings JSON. Untrusted: the logo goes through the whole import pipeline afterwards.
     * @return {{svg: string, settings: object}}
     */
    function parseSettings(text) {
        if (typeof text !== 'string' || text.length > JSON_LIMIT) throw new root.OmxSvgSafety.SafetyError('JSON_TOO_LARGE');
        var data;
        try { data = JSON.parse(text); } catch (error) { throw new root.OmxSvgSafety.SafetyError('JSON_INVALID'); }
        if (!data || typeof data !== 'object' || data.format !== FORMAT) throw new root.OmxSvgSafety.SafetyError('JSON_FORMAT');
        if (data.schema !== SCHEMA) throw new root.OmxSvgSafety.SafetyError('JSON_SCHEMA');
        if (typeof data.svg !== 'string' || !data.settings || typeof data.settings !== 'object') throw new root.OmxSvgSafety.SafetyError('JSON_INVALID');
        return { svg: data.svg, settings: data.settings };
    }

    root.OmxSvgExport = { build: build, parseSettings: parseSettings, verifyExport: verifyExport, FORMAT: FORMAT, SCHEMA: SCHEMA };
}(window));
