/**
 * SVG Motion Studio: presets to a motion plan, and a plan to CSS.
 *
 * One plan drives both the WAAPI preview (frame.js) and the CSS used by the
 * gallery thumbnails, so the two cannot drift apart. Targets are numbers, never
 * selectors, and values only come from these builders and sanitized parameters.
 *
 * Works in the browser and in Node (tests/svg-motion/unit.js).
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.OmxSvgCompile = factory();
}(this, function () {
    'use strict';

    var EASINGS = {
        linear: 'linear', ease: 'ease', 'ease-in': 'ease-in', 'ease-out': 'ease-out', 'ease-in-out': 'ease-in-out',
        soft: 'cubic-bezier(.2,.8,.2,1)', back: 'cubic-bezier(.34,1.56,.64,1)'
    };
    var HEX = /^#[0-9a-fA-F]{6}$/;
    var PROPERTIES = ['opacity', 'transform', 'filter', 'clip-path', 'fill', 'fill-opacity', 'stroke', 'stroke-opacity', 'stroke-width', 'stroke-dasharray', 'stroke-dashoffset'];

    /** Clamp, snap and type-check values against the preset's parameter list. */
    function sanitizeParams(preset, input) {
        var out = {};
        input = input || {};
        preset.params.forEach(function (param) {
            var value = Object.prototype.hasOwnProperty.call(input, param.key) ? input[param.key] : param.default;
            if (param.type === 'range') {
                var number = Number(value);
                if (!isFinite(number)) number = param.default;
                number = Math.min(param.max, Math.max(param.min, number));
                out[param.key] = Number((Math.round((number - param.min) / param.step) * param.step + param.min).toFixed(4));
            } else if (param.type === 'color') {
                out[param.key] = HEX.test(String(value)) ? String(value).toLowerCase() : param.default;
            } else if (param.type === 'select') {
                var known = param.options.some(function (option) { return option.value === value; });
                out[param.key] = known ? value : param.default;
            }
        });
        return out;
    }

    function track(kind, index, frames, options) {
        return Object.assign({ target: { kind: kind, index: index }, frames: frames, delay: 0, duration: 1000, iterations: 1, direction: 'normal', easing: 'linear' }, options);
    }

    function frame(offset, props) {
        return { offset: offset, props: props };
    }

    /** Falls back to the whole logo when there are too few parts to stagger. */
    function partIndexes(layout) {
        return layout.parts > 1 ? range(layout.parts) : [];
    }

    function range(n) {
        var out = [];
        for (var i = 0; i < n; i++) out.push(i);
        return out;
    }

    function round(n) {
        return Number(n.toFixed(4));
    }

    function geometrySetup(layout, styles) {
        return range(layout.geometry).map(function (i) { return { target: { kind: 'geometry', index: i }, styles: styles(i) }; });
    }

    /* A shape with its own stroke keeps its colour and width: the end state must still be the logo. */
    function outlineStroke(layout, i, color, width) {
        var own = layout.ownStroke && layout.ownStroke[i];
        return own ? {} : { stroke: color, 'stroke-width': String(width) };
    }

    // These animate each shape, so their cost grows with the logo's shape count.
    var SHAPE_PRESETS = ['logo-draw', 'logo-draw-fill', 'logo-color-shift', 'logo-dash-flow', 'logo-shine'];

    var ENTRANCE_END = 'end';
    var LOOP_START = 'start';

    var BUILDERS = {
        'logo-pop': function (p) {
            return { rest: ENTRANCE_END, tracks: [track('root', 0, [
                frame(0, { opacity: '0', transform: 'scale(' + p.from + ')' }),
                frame(0.6, { opacity: '1', transform: 'scale(' + round(1 + p.overshoot) + ')' }),
                frame(1, { opacity: '1', transform: 'scale(1)' })
            ], { duration: p.duration, easing: EASINGS['ease-out'] })] };
        },
        'logo-breathe': function (p) {
            return { rest: LOOP_START, tracks: [track('root', 0, [
                frame(0, { transform: 'scale(1)' }),
                frame(1, { transform: 'scale(' + round(1 + p.amount) + ')' })
            ], { duration: p.duration, iterations: Infinity, direction: 'alternate', easing: EASINGS['ease-in-out'] })] };
        },
        'logo-float': function (p, layout) {
            return { rest: LOOP_START, tracks: [track('root', 0, [
                frame(0, { transform: 'translateY(0px) rotate(0deg)' }),
                frame(1, { transform: 'translateY(' + round(-layout.height * p.amount) + 'px) rotate(' + p.tilt + 'deg)' })
            ], { duration: p.duration, iterations: Infinity, direction: 'alternate', easing: EASINGS['ease-in-out'] })] };
        },
        'logo-wiggle': function (p) {
            var a = p.angle;
            var active = round(1 - p.rest);
            return { rest: LOOP_START, tracks: [track('root', 0, [
                frame(0, { transform: 'rotate(0deg)' }),
                frame(round(active * 0.25), { transform: 'rotate(' + a + 'deg)' }),
                frame(round(active * 0.5), { transform: 'rotate(' + (-a) + 'deg)' }),
                frame(round(active * 0.75), { transform: 'rotate(' + round(a / 2) + 'deg)' }),
                frame(active, { transform: 'rotate(0deg)' }),
                frame(1, { transform: 'rotate(0deg)' })
            ], { duration: p.duration, iterations: Infinity, easing: EASINGS['ease-in-out'] })] };
        },
        'logo-draw': function (p, layout) {
            return {
                rest: ENTRANCE_END,
                setup: geometrySetup(layout, function (i) {
                    return Object.assign({ 'fill-opacity': '0', 'stroke-dasharray': '1' }, outlineStroke(layout, i, p.color, p.width));
                }),
                tracks: range(layout.geometry).map(function (i) {
                    return track('geometry', i, [frame(0, { 'stroke-dashoffset': '1' }), frame(1, { 'stroke-dashoffset': '0' })], {
                        duration: p.duration, delay: Math.round(i * p.gap), easing: EASINGS['ease-in-out']
                    });
                })
            };
        },
        'logo-draw-fill': function (p, layout) {
            var total = p.duration;
            var fillAt = p.fillAt;
            return {
                rest: ENTRANCE_END,
                setup: geometrySetup(layout, function (i) {
                    return Object.assign({ 'stroke-dasharray': '1' }, outlineStroke(layout, i, p.color, p.width));
                }),
                tracks: range(layout.geometry).map(function (i) {
                    var own = layout.ownStroke && layout.ownStroke[i];
                    var fillEnd = layout.fillOpacity ? layout.fillOpacity[i] : '1';
                    return track('geometry', i, [
                        frame(0, { 'stroke-dashoffset': '1', 'fill-opacity': '0', 'stroke-opacity': '1' }),
                        frame(fillAt, { 'stroke-dashoffset': '0', 'fill-opacity': '0', 'stroke-opacity': '1' }),
                        frame(1, { 'stroke-dashoffset': '0', 'fill-opacity': fillEnd, 'stroke-opacity': own ? '1' : '0' })
                    ], { duration: total, easing: EASINGS['ease-in-out'] });
                })
            };
        },
        'logo-assemble': function (p, layout) {
            var parts = partIndexes(layout);
            if (!parts.length) {
                return { rest: ENTRANCE_END, tracks: [track('root', 0, [
                    frame(0, { opacity: '0', transform: 'translateY(' + round(layout.height * p.distance) + 'px)' }),
                    frame(1, { opacity: '1', transform: 'translateY(0px)' })
                ], { duration: p.duration, easing: EASINGS.soft })] };
            }
            return { rest: ENTRANCE_END, tracks: parts.map(function (i) {
                var angle = (i / parts.length) * Math.PI * 2 - Math.PI / 2;
                var dx = round(Math.cos(angle) * layout.width * p.distance);
                var dy = round(Math.sin(angle) * layout.height * p.distance);
                return track('part', i, [
                    frame(0, { opacity: '0', transform: 'translate(' + dx + 'px,' + dy + 'px)' }),
                    frame(1, { opacity: '1', transform: 'translate(0px,0px)' })
                ], { duration: p.duration, delay: Math.round(i * p.gap), easing: EASINGS.soft });
            }) };
        },
        'logo-stagger-pop': function (p, layout) {
            var parts = partIndexes(layout);
            function pop(kind, i, delay) {
                return track(kind, i, [
                    frame(0, { opacity: '0', transform: 'scale(' + p.from + ')' }),
                    frame(1, { opacity: '1', transform: 'scale(1)' })
                ], { duration: p.duration, delay: delay, easing: EASINGS.back });
            }
            return { rest: ENTRANCE_END, tracks: parts.length
                ? parts.map(function (i) { return pop('part', i, Math.round(i * p.gap)); })
                : [pop('root', 0, 0)] };
        },
        'logo-rise-mask': function (p, layout) {
            return { rest: ENTRANCE_END, tracks: [track('root', 0, [
                frame(0, { transform: 'translateY(' + round(layout.height * p.distance) + 'px)' }),
                frame(1, { transform: 'translateY(0px)' })
            ], { duration: p.duration, easing: EASINGS.soft })] };
        },
        'logo-wipe': function (p) {
            var from = { right: 'inset(-10% 110% -10% -10%)', left: 'inset(-10% -10% -10% 110%)', down: 'inset(-10% -10% 110% -10%)', up: 'inset(110% -10% -10% -10%)' }[p.direction];
            return { rest: ENTRANCE_END, tracks: [track('root', 0, [
                frame(0, { 'clip-path': from }),
                frame(1, { 'clip-path': 'inset(-10% -10% -10% -10%)' })
            ], { duration: p.duration, easing: EASINGS[p.easing] })] };
        },
        'logo-drop': function (p, layout) {
            var height = layout.height * p.height;
            var frames = [frame(0, { opacity: '0', transform: 'translateY(' + round(-height) + 'px)' })];
            // Each bounce is lower and shorter; offsets are spread over 0.45..1.
            var n = p.bounces;
            frames.push(frame(0.45, { opacity: '1', transform: 'translateY(0px)' }));
            for (var i = 1; i <= n; i++) {
                var start = 0.45 + (i - 1) * (0.55 / n);
                var size = 0.55 / n;
                frames.push(frame(round(start + size / 2), { opacity: '1', transform: 'translateY(' + round(-height * Math.pow(0.35, i)) + 'px)' }));
                frames.push(frame(round(start + size), { opacity: '1', transform: 'translateY(0px)' }));
            }
            return { rest: ENTRANCE_END, tracks: [track('root', 0, frames, { duration: p.duration, easing: EASINGS['ease-in-out'] })] };
        },
        'logo-spin-in': function (p) {
            var turn = p.direction === 'ccw' ? -p.turns : p.turns;
            return { rest: ENTRANCE_END, tracks: [track('root', 0, [
                frame(0, { opacity: '0', transform: 'rotate(' + round(turn * 360) + 'deg) scale(' + p.from + ')' }),
                frame(1, { opacity: '1', transform: 'rotate(0deg) scale(1)' })
            ], { duration: p.duration, easing: EASINGS.soft })] };
        },
        'logo-parts-wave': function (p, layout) {
            var parts = partIndexes(layout);
            function wave(kind, i, delay) {
                return track(kind, i, [
                    frame(0, { transform: 'translateY(0px)' }),
                    frame(1, { transform: 'translateY(' + round(-layout.height * p.height) + 'px)' })
                ], { duration: p.duration, delay: delay, iterations: Infinity, direction: 'alternate', easing: EASINGS['ease-in-out'] });
            }
            return { rest: LOOP_START, tracks: parts.length
                ? parts.map(function (i) { return wave('part', i, Math.round(i * p.phase)); })
                : [wave('root', 0, 0)] };
        },
        'logo-color-shift': function (p, layout) {
            return { rest: LOOP_START, tracks: range(layout.geometry).map(function (i) {
                return track('geometry', i, [frame(0, { fill: p.color1 }), frame(1, { fill: p.color2 })], {
                    duration: p.duration, iterations: Infinity, direction: 'alternate', easing: EASINGS['ease-in-out']
                });
            }) };
        },
        /* filter and 3D go on the <svg> element ('stage'): WebKit does not paint CSS filter on inner SVG
           elements, and inner elements flatten 3D. It is also far cheaper (docs/motion/SVG-MOTION-S1C-RESULT.md). */
        'logo-focus': function (p) {
            return { rest: ENTRANCE_END, tracks: [track('stage', 0, [
                frame(0, { opacity: '0', filter: 'blur(' + p.blur + 'px)' }),
                frame(1, { opacity: '1', filter: 'blur(0px)' })
            ], { duration: p.duration, easing: EASINGS['ease-out'] })] };
        },
        'logo-flip': function (p) {
            var axis = p.axis === 'X' ? 'rotateX' : 'rotateY';
            return { rest: ENTRANCE_END, tracks: [track('stage', 0, [
                frame(0, { opacity: '0', transform: 'perspective(' + p.depth + 'px) ' + axis + '(' + p.angle + 'deg)' }),
                frame(0.3, { opacity: '1', transform: 'perspective(' + p.depth + 'px) ' + axis + '(' + round(p.angle * 0.7) + 'deg)' }),
                frame(1, { opacity: '1', transform: 'perspective(' + p.depth + 'px) ' + axis + '(0deg)' })
            ], { duration: p.duration, easing: EASINGS['ease-out'] })] };
        },
        'logo-shine': function (p, layout) {
            var active = round(1 - p.rest);
            var travel = round(layout.width * 1.8);
            return {
                rest: LOOP_START,
                setup: [{ target: { kind: 'overlay', index: 0 }, styles: { opacity: String(p.strength) } }],
                tracks: [track('overlay', 0, [
                    frame(0, { transform: 'translateX(0px)' }),
                    frame(active, { transform: 'translateX(' + travel + 'px)' }),
                    frame(1, { transform: 'translateX(' + travel + 'px)' })
                ], { duration: p.duration, iterations: Infinity, easing: EASINGS['ease-in-out'] })]
            };
        },
        'logo-glow': function (p) {
            return { rest: LOOP_START, tracks: [track('stage', 0, [
                frame(0, { filter: 'drop-shadow(0px 0px 0px ' + p.color + ')' }),
                frame(1, { filter: 'drop-shadow(0px 0px ' + p.size + 'px ' + p.color + ')' })
            ], { duration: p.duration, iterations: Infinity, direction: 'alternate', easing: EASINGS['ease-in-out'] })] };
        },
        'logo-dash-flow': function (p, layout) {
            var dash = p.dash;
            return {
                rest: LOOP_START,
                setup: geometrySetup(layout, function (i) {
                    return Object.assign({ 'stroke-dasharray': dash + ' ' + round(dash) }, outlineStroke(layout, i, p.color, p.width));
                }),
                tracks: range(layout.geometry).map(function (i) {
                    return track('geometry', i, [frame(0, { 'stroke-dashoffset': '0' }), frame(1, { 'stroke-dashoffset': String(round(-dash * 2)) })], {
                        duration: p.duration, iterations: Infinity
                    });
                })
            };
        }
    };

    /**
     * @param {object} preset Entry of presets.json.
     * @param {object} params Raw values; sanitized here.
     * @param {{parts:number, geometry:number, width:number, height:number, ownStroke?:boolean[], fillOpacity?:string[]}} layout
     * @return {{preset:string, rest:string, setup:Array, tracks:Array, length:number}}
     */
    function buildPlan(preset, params, layout) {
        var builder = BUILDERS[preset.id];
        if (!builder) throw new Error('Unknown preset: ' + preset.id);
        if (layout.tooManyShapes && SHAPE_PRESETS.indexOf(preset.id) !== -1) throw new Error('TOO_MANY_SHAPES');
        var clean = sanitizeParams(preset, params);
        var plan = builder(clean, layout);
        var length = 0;
        plan.tracks.forEach(function (t) {
            length = Math.max(length, t.delay + t.duration);
        });
        return { preset: preset.id, rest: plan.rest, setup: plan.setup || [], tracks: plan.tracks, length: length, loops: plan.rest === LOOP_START };
    }

    /* Attribute selector for a target. Numbers only, so a plan cannot smuggle a selector. */
    function selector(target) {
        var index = Number(target.index);
        if (!isFinite(index) || index < 0) throw new Error('Bad target');
        if (target.kind === 'stage') return '[data-omx-stage]';
        if (target.kind === 'overlay') return '[data-omx-overlay]';
        if (target.kind === 'root') return '[data-omx-root]';
        if (target.kind === 'part') return '[data-omx-part="' + index + '"]';
        if (target.kind === 'geometry') return '[data-omx-geometry="' + index + '"]';
        throw new Error('Bad target');
    }

    /** Validates a plan received from outside the compiler (the frame calls this too). */
    function isValidPlan(plan) {
        if (!plan || !Array.isArray(plan.tracks) || !Array.isArray(plan.setup)) return false;
        var valueOk = function (value) { return /^[a-zA-Z0-9 .,()%#+-]{1,80}$/.test(String(value)) && !/url/i.test(String(value)); };
        var targetOk = function (t) { return t && ['stage', 'overlay', 'root', 'part', 'geometry'].indexOf(t.kind) !== -1 && Number.isInteger(t.index) && t.index >= 0; };
        var propsOk = function (props) {
            return Object.keys(props || {}).every(function (key) { return PROPERTIES.indexOf(key) !== -1 && valueOk(props[key]); });
        };
        return plan.setup.every(function (s) { return targetOk(s.target) && propsOk(s.styles); }) &&
            plan.tracks.every(function (t) {
                return targetOk(t.target) && Array.isArray(t.frames) && t.frames.length >= 2 &&
                    t.frames.every(function (f) { return f.offset >= 0 && f.offset <= 1 && propsOk(f.props); }) &&
                    typeof t.duration === 'number' && typeof t.delay === 'number' && valueOk(t.easing);
            });
    }

    /** The final (or first) resting style for a plan: used for reduced motion and thumbnails at rest. */
    function restingStyles(plan) {
        return plan.tracks.map(function (t) {
            var f = plan.rest === 'end' ? t.frames[t.frames.length - 1] : t.frames[0];
            return { target: t.target, styles: f.props };
        });
    }

    function declarations(props) {
        return Object.keys(props).map(function (key) { return key + ':' + props[key]; }).join(';');
    }

    /**
     * CSS for a plan. Same timing and values as the WAAPI preview.
     * @param {object} plan
     * @param {{animate?: boolean}} [options] animate:false writes only the resting state.
     */
    function planToCss(plan, options) {
        var animate = !options || options.animate !== false;
        var out = ['[data-omx-root],[data-omx-part],[data-omx-geometry]{transform-box:fill-box;transform-origin:center}[data-omx-stage]{transform-origin:center}'];
        plan.setup.forEach(function (s) { out.push(selector(s.target) + '{' + declarations(s.styles) + '}'); });
        if (!animate) {
            restingStyles(plan).forEach(function (r) { out.push(selector(r.target) + '{' + declarations(r.styles) + '}'); });
            return out.join('');
        }
        plan.tracks.forEach(function (t, i) {
            var name = 'omx-k' + i;
            out.push('@keyframes ' + name + '{' + t.frames.map(function (f) {
                return round(f.offset * 100) + '%{' + declarations(f.props) + '}';
            }).join('') + '}');
            out.push(selector(t.target) + '{animation:' + name + ' ' + t.duration + 'ms ' + t.easing + ' ' + t.delay + 'ms ' +
                (t.iterations === Infinity ? 'infinite' : t.iterations) + ' ' + t.direction + ' both}');
        });
        return out.join('');
    }

    return { buildPlan: buildPlan, sanitizeParams: sanitizeParams, isValidPlan: isValidPlan, restingStyles: restingStyles, planToCss: planToCss, selector: selector, BUILDERS: BUILDERS, EASINGS: EASINGS, PROPERTIES: PROPERTIES };
}));
