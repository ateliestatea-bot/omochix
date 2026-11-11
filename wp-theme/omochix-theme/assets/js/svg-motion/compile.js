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
    // translate, rotate and scale are the individual transform properties (S4): several motions can share one element
    // without fighting over `transform`, which the engines apply after them in a fixed order.
    var PROPERTIES = ['opacity', 'transform', 'translate', 'rotate', 'scale', 'filter', 'clip-path', 'fill', 'fill-opacity', 'stroke', 'stroke-opacity', 'stroke-width', 'stroke-dasharray', 'stroke-dashoffset'];

    /** Clamp, snap and type-check values against a parameter list. */
    function sanitizeParams(preset, input) {
        return sanitizeList(preset.params, input);
    }

    function sanitizeCommon(list, input) {
        return sanitizeList(list, input);
    }

    function sanitizeList(list, input) {
        var out = {};
        input = input || {};
        list.forEach(function (param) {
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
     * @param {object} [commonValues] Sanitized common adjustments (sanitizeCommon).
     * @param {{parts:number, geometry:number, width:number, height:number, ownStroke?:boolean[], fillOpacity?:string[]}} layout
     * @return {{preset:string, rest:string, setup:Array, tracks:Array, length:number}}
     */
    function buildPlan(preset, params, layout, commonValues) {
        var builder = BUILDERS[preset.id];
        if (!builder) throw new Error('Unknown preset: ' + preset.id);
        if (SHAPE_PRESETS.indexOf(preset.id) !== -1) {
            if (layout.tooManyShapes) throw new Error('TOO_MANY_SHAPES');
            if (!layout.geometry) throw new Error('NO_SHAPES');
        }
        var clean = sanitizeParams(preset, params);
        var built = builder(clean, layout);
        var plan = { preset: preset.id, rest: built.rest, setup: built.setup || [], tracks: built.tracks };
        if (commonValues) applyCommon(plan, preset, commonValues, layout);
        finishTiming(plan);
        return plan;
    }

    /** Whether the common stagger does anything for this preset on this logo. */
    function staggerApplies(preset, layout) {
        var own = preset.params.some(function (param) { return param.key === 'gap' || param.key === 'phase'; });
        return !own && layout.geometry > 1 && SHAPE_PRESETS.indexOf(preset.id) !== -1 && preset.id !== 'logo-shine';
    }

    function applyCommon(plan, preset, values, layout) {
        plan.tracks.forEach(function (t, index) {
            if (values.easing !== 'preset' && EASINGS[values.easing]) t.easing = EASINGS[values.easing];
            if (values.loop !== 'preset') t.iterations = values.loop === 'infinite' ? Infinity : Number(values.loop);
            t.delay += values.delay;
            if (values.stagger && staggerApplies(preset, layout)) t.delay += index * values.stagger;
        });
        // The pose goes on its own wrapper, so a preset's transform and a user's position never overwrite each other.
        var pose = [];
        if (values.x || values.y) pose.push('translate(' + round(layout.width * values.x / 100) + 'px,' + round(layout.height * values.y / 100) + 'px)');
        if (values.rotate) pose.push('rotate(' + values.rotate + 'deg)');
        if (values.scale !== 1) pose.push('scale(' + values.scale + ')');
        var styles = {};
        if (pose.length) styles.transform = pose.join(' ');
        if (values.opacity !== 1) styles.opacity = String(values.opacity);
        if (Object.keys(styles).length) plan.setup.push({ target: { kind: 'base', index: 0 }, styles: styles });
    }

    /** length: when the last track ends (Infinity for a loop). restTime: the time of the resting pose. */
    function finishTiming(plan) {
        var length = 0;
        var once = 0;
        var maxDelay = 0;
        plan.tracks.forEach(function (t) {
            length = Math.max(length, t.delay + t.duration * t.iterations);
            once = Math.max(once, t.delay + t.duration);
            maxDelay = Math.max(maxDelay, t.delay);
        });
        plan.length = length;
        plan.loops = length === Infinity;
        plan.cycle = once;
        plan.maxDelay = maxDelay;
        plan.restTime = plan.rest === 'end' ? (plan.loops ? once : length) : 0;
    }

    /* Attribute selector for a target. Numbers only, so a plan cannot smuggle a selector. */
    function selector(target) {
        var index = Number(target.index);
        if (!isFinite(index) || index < 0) throw new Error('Bad target');
        if (target.kind === 'stage') return '[data-omx-stage]';
        if (target.kind === 'base') return '[data-omx-base]';
        if (target.kind === 'overlay') return '[data-omx-overlay]';
        if (target.kind === 'root') return '[data-omx-root]';
        if (target.kind === 'part') return '[data-omx-part="' + index + '"]';
        if (target.kind === 'geometry') return '[data-omx-geometry="' + index + '"]';
        throw new Error('Bad target');
    }

    var SCOPE = /^omx-[a-z0-9]{4,16}$/;

    /** Selector inside an exported logo: every rule is limited to that logo's class, so logos on one page never meet. */
    function scopedSelector(target, scope) {
        if (!scope) return selector(target);
        if (!SCOPE.test(scope)) throw new Error('Bad scope');
        return target.kind === 'stage' ? 'svg.' + scope : '.' + scope + ' ' + selector(target);
    }

    /** Validates a plan received from outside the compiler (the frame calls this too). */
    function isValidPlan(plan) {
        if (!plan || !Array.isArray(plan.tracks) || !Array.isArray(plan.setup)) return false;
        var valueOk = function (value) { return /^[a-zA-Z0-9 .,()%#+-]{1,80}$/.test(String(value)) && !/url/i.test(String(value)); };
        var targetOk = function (t) { return t && ['stage', 'base', 'overlay', 'root', 'part', 'geometry'].indexOf(t.kind) !== -1 && Number.isInteger(t.index) && t.index >= 0; };
        var propsOk = function (props) {
            return Object.keys(props || {}).every(function (key) { return PROPERTIES.indexOf(key) !== -1 && valueOk(props[key]); });
        };
        if (plan.origin !== undefined && !originOk(plan.origin)) return false;
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
     * @param {{animate?: boolean, time?: number, frozen?: boolean, scope?: string, reducedRest?: boolean}} [options] animate:false writes only the
     *     resting state. time: start at this point of the timeline, through a negative animation-delay.
     *     frozen: hold there with animation-play-state, which is how an image can pause in place.
     */
    function planToCss(plan, options) {
        options = options || {};
        var animate = options.animate !== false;
        var time = Number(options.time) || 0;
        var scope = options.scope || '';
        var sel = function (target) { return scopedSelector(target, scope); };
        var prefix = scope ? '.' + scope + ' ' : '';
        var out = [prefix + '[data-omx-root],' + prefix + '[data-omx-part],' + prefix + '[data-omx-geometry]{transform-box:fill-box;transform-origin:center}' +
            (scope ? 'svg.' + scope : '[data-omx-stage]') + '{transform-origin:center}' + prefix + '[data-omx-base]{transform-box:view-box;transform-origin:center}'];
        // With edited parts moving under it, the root's fill-box would follow them (and Firefox reads it differently): pin its origin.
        if (plan.origin) out.push(prefix + '[data-omx-root]{transform-box:view-box;transform-origin:' + originValue(plan.origin) + '}');
        plan.setup.forEach(function (s) { out.push(sel(s.target) + '{' + declarations(s.styles) + '}'); });
        if (!animate) {
            restingStyles(plan).forEach(function (r) { out.push(sel(r.target) + '{' + declarations(r.styles) + '}'); });
            return out.join('');
        }
        // One `animation` rule per element, listing every track on it: a second rule for the same element would
        // replace the first (S4 puts several tracks on one part). The rule is written where the element's last track is.
        var byTarget = {}, lastIndex = {};
        plan.tracks.forEach(function (t, i) { var key = sel(t.target); (byTarget[key] = byTarget[key] || []).push(i); lastIndex[key] = i; });
        plan.tracks.forEach(function (t, i) {
            var name = (scope || 'omx') + '-k' + i;
            out.push('@keyframes ' + name + '{' + t.frames.map(function (f) {
                return round(f.offset * 100) + '%{' + declarations(f.props) + '}';
            }).join('') + '}');
            var key = sel(t.target);
            if (lastIndex[key] !== i) return;
            out.push(key + '{animation:' + byTarget[key].map(function (j) {
                var x = plan.tracks[j];
                return (scope || 'omx') + '-k' + j + ' ' + x.duration + 'ms ' + x.easing + ' ' + round(x.delay - time) + 'ms ' +
                    (x.iterations === Infinity ? 'infinite' : x.iterations) + ' ' + x.direction + ' both' + (options.frozen ? ' paused' : '');
            }).join(',') + '}');
        });
        // For exported files: viewers who ask for less motion get the resting pose, with no animation at all.
        if (options.reducedRest) {
            out.push('@media (prefers-reduced-motion:reduce){' +
                plan.tracks.map(function (t) { return sel(t.target) + '{animation:none}'; }).join('') +
                restingStyles(plan).map(function (r) { return sel(r.target) + '{' + declarations(r.styles) + '}'; }).join('') + '}');
        }
        return out.join('');
    }

    /* ------------------------------------------------------------------ S4: free edits */

    /** Hard limits for an edit. Anything over is refused, never trimmed. */
    var EDIT_LIMITS = Object.freeze({ layers: 40, tracksPerLayer: 8, tracks: 120, keys: 600, keysPerTrack: 12, minKeys: 2, duration: [100, 20000], minSpan: 50, css: 262144 });
    // css: the edit itself adds at most about 46KB; the rest of the budget is for per-part presets on a logo at the part budget.

    /* Each property: how many numbers a key holds, their range, and how they become a CSS value. Colors are hex strings. */
    var EDIT_PROPS = {
        translate: { n: 2, min: -4000, max: 4000, css: function (v) { return v[0] + 'px ' + v[1] + 'px'; } },
        rotate: { n: 1, min: -3600, max: 3600, css: function (v) { return v[0] + 'deg'; } },
        scale: { n: [1, 2], min: 0.05, max: 10, css: function (v) { return v.join(' '); } },
        opacity: { n: 1, min: 0, max: 1, css: function (v) { return String(v[0]); } },
        'stroke-width': { n: 1, min: 0, max: 100, css: function (v) { return String(v[0]); } },
        'stroke-dashoffset': { n: 1, min: -10000, max: 10000, css: function (v) { return String(v[0]); } },
        fill: { color: true, css: function (v) { return v[0]; } },
        stroke: { color: true, css: function (v) { return v[0]; } }
    };
    var EDIT_TARGETS = ['part', 'root'];
    var LAYER_KEYS = ['id', 'target', 'start', 'end', 'tracks'], TRACK_KEYS = ['prop', 'easing', 'keys'], EDIT_KEYS = ['duration', 'origin', 'nextId', 'layers'];
    var LAYER_ID = /^L[1-9][0-9]{0,5}$/, COLOR = /^#[0-9a-f]{6}$/;

    function editError(code, detail) {
        var error = new Error(code);
        error.code = code;
        error.detail = detail || '';
        return error;
    }
    function isInt(v, lo, hi) { return typeof v === 'number' && Number.isInteger(v) && v >= lo && v <= hi; }
    function isNum(v, lo, hi) { return typeof v === 'number' && isFinite(v) && v >= lo && v <= hi; }
    function onlyKeys(object, allowed, required) {
        if (!object || typeof object !== 'object' || Array.isArray(object)) return false;
        var keys = Object.keys(object);
        return keys.every(function (k) { return allowed.indexOf(k) !== -1; }) && required.every(function (k) { return keys.indexOf(k) !== -1; });
    }
    function originOk(origin) {
        return onlyKeys(origin, ['x', 'y'], ['x', 'y']) && isNum(origin.x, -1e5, 1e5) && isNum(origin.y, -1e5, 1e5);
    }
    function originValue(origin) {
        return Number(origin.x.toFixed(2)) + 'px ' + Number(origin.y.toFixed(2)) + 'px';
    }
    function fixed(v) { return Number(v.toFixed(3)); }

    /**
     * Checks an edit (schema 2 `edit`) and returns a clean copy. Values are never rounded into range: a value
     * outside it, an unknown key or a bad shape refuses the whole edit, because an edit is the viewer's own work.
     * @param {object} edit
     * @param {{parts?: number}} [layout] When given, part numbers must exist in this logo.
     * @throws Error with code EDIT_INVALID, EDIT_LIMIT or EDIT_CONFLICT
     */
    function validateEdit(edit, layout) {
        if (!onlyKeys(edit, EDIT_KEYS, ['duration', 'nextId', 'layers'])) throw editError('EDIT_INVALID', 'shape');
        if (!isInt(edit.duration, EDIT_LIMITS.duration[0], EDIT_LIMITS.duration[1])) throw editError('EDIT_INVALID', 'duration');
        if (edit.origin !== undefined && !originOk(edit.origin)) throw editError('EDIT_INVALID', 'origin');
        if (!isInt(edit.nextId, 1, 999999)) throw editError('EDIT_INVALID', 'nextId');
        if (!Array.isArray(edit.layers)) throw editError('EDIT_INVALID', 'layers');
        if (edit.layers.length > EDIT_LIMITS.layers) throw editError('EDIT_LIMIT', 'layers');
        var ids = {}, targets = {}, tracks = 0, keys = 0;
        var clean = { duration: edit.duration, nextId: edit.nextId, layers: [] };
        if (edit.origin) clean.origin = { x: fixed(edit.origin.x), y: fixed(edit.origin.y) };
        edit.layers.forEach(function (layer) {
            if (!onlyKeys(layer, LAYER_KEYS, LAYER_KEYS)) throw editError('EDIT_INVALID', 'layer');
            if (typeof layer.id !== 'string' || !LAYER_ID.test(layer.id) || Number(layer.id.slice(1)) >= edit.nextId) throw editError('EDIT_INVALID', 'id');
            if (ids[layer.id]) throw editError('EDIT_INVALID', 'id:duplicate');
            ids[layer.id] = true;
            var t = layer.target;
            if (!onlyKeys(t, ['kind', 'index'], ['kind', 'index']) || EDIT_TARGETS.indexOf(t.kind) === -1 || !isInt(t.index, 0, 9999)) throw editError('EDIT_INVALID', 'target');
            if (t.kind === 'root' && t.index !== 0) throw editError('EDIT_INVALID', 'target');
            if (layout && t.kind === 'part' && t.index >= layout.parts) throw editError('EDIT_INVALID', 'target:missing');
            var key = t.kind + ':' + t.index;
            if (targets[key]) throw editError('EDIT_CONFLICT', 'target:' + key);
            targets[key] = true;
            if (!isInt(layer.start, 0, edit.duration) || !isInt(layer.end, 0, edit.duration) || layer.end - layer.start < EDIT_LIMITS.minSpan) throw editError('EDIT_INVALID', 'time');
            if (!Array.isArray(layer.tracks) || !layer.tracks.length) throw editError('EDIT_INVALID', 'tracks');
            if (layer.tracks.length > EDIT_LIMITS.tracksPerLayer) throw editError('EDIT_LIMIT', 'tracks');
            var props = {};
            var cleanLayer = { id: layer.id, target: { kind: t.kind, index: t.index }, start: layer.start, end: layer.end, tracks: [] };
            layer.tracks.forEach(function (track) {
                if (!onlyKeys(track, TRACK_KEYS, TRACK_KEYS)) throw editError('EDIT_INVALID', 'track');
                var spec = Object.prototype.hasOwnProperty.call(EDIT_PROPS, track.prop) ? EDIT_PROPS[track.prop] : null;
                if (!spec) throw editError('EDIT_INVALID', 'prop');
                if (props[track.prop]) throw editError('EDIT_CONFLICT', 'prop:' + track.prop);
                props[track.prop] = true;
                if (!Object.prototype.hasOwnProperty.call(EASINGS, track.easing)) throw editError('EDIT_INVALID', 'easing');
                if (!Array.isArray(track.keys) || track.keys.length < EDIT_LIMITS.minKeys) throw editError('EDIT_INVALID', 'keys');
                if (track.keys.length > EDIT_LIMITS.keysPerTrack) throw editError('EDIT_LIMIT', 'keys');
                var last = -1;
                var cleanKeys = track.keys.map(function (k, i) {
                    if (!onlyKeys(k, ['at', 'v'], ['at', 'v']) || !isNum(k.at, 0, 1) || k.at <= last) throw editError('EDIT_INVALID', 'at');
                    if ((i === 0 && k.at !== 0) || (i === track.keys.length - 1 && k.at !== 1)) throw editError('EDIT_INVALID', 'at:ends');
                    last = k.at;
                    if (!Array.isArray(k.v)) throw editError('EDIT_INVALID', 'v');
                    var v;
                    if (spec.color) {
                        if (k.v.length !== 1 || typeof k.v[0] !== 'string' || !COLOR.test(k.v[0])) throw editError('EDIT_INVALID', 'color');
                        v = [k.v[0]];
                    } else {
                        var n = Array.isArray(spec.n) ? spec.n : [spec.n, spec.n];
                        if (k.v.length < n[0] || k.v.length > n[1] || !k.v.every(function (x) { return isNum(x, spec.min, spec.max); })) throw editError('EDIT_INVALID', 'range');
                        v = k.v.map(fixed);
                    }
                    return { at: fixed(k.at), v: v };
                });
                keys += cleanKeys.length;
                cleanLayer.tracks.push({ prop: track.prop, easing: track.easing, keys: cleanKeys });
            });
            tracks += cleanLayer.tracks.length;
            clean.layers.push(cleanLayer);
        });
        if (tracks > EDIT_LIMITS.tracks) throw editError('EDIT_LIMIT', 'tracks:total');
        if (keys > EDIT_LIMITS.keys) throw editError('EDIT_LIMIT', 'keys:total');
        return clean;
    }

    /**
     * The preset's plan plus the edit's tracks: one plan for the preview and every export.
     * An edit track on an element and property the preset already animates is a conflict (the second animation
     * would silently win), so it is refused rather than compiled.
     * @param {object} basePlan From buildPlan(), left untouched.
     * @param {object|null} edit Output of validateEdit().
     */
    function compileEdit(basePlan, edit) {
        // A deep copy; JSON turns Infinity into null, so endless iterations are put back.
        var plan = JSON.parse(JSON.stringify(basePlan), function (key, value) { return value === null && key === 'iterations' ? Infinity : value; });
        finishTiming(plan); // Infinity in length and loops is restored here too
        if (!edit) return plan;
        var taken = {};
        plan.tracks.forEach(function (t) {
            Object.keys(t.frames[0].props).forEach(function (prop) { taken[t.target.kind + ':' + t.target.index + ':' + prop] = true; });
        });
        edit.layers.forEach(function (layer) {
            layer.tracks.forEach(function (track) {
                var key = layer.target.kind + ':' + layer.target.index + ':' + track.prop;
                if (taken[key]) throw editError('EDIT_CONFLICT', key);
                taken[key] = true;
                plan.tracks.push({
                    target: { kind: layer.target.kind, index: layer.target.index },
                    frames: track.keys.map(function (k) { var props = {}; props[track.prop] = EDIT_PROPS[track.prop].css(k.v); return { offset: k.at, props: props }; }),
                    delay: layer.start, duration: layer.end - layer.start, iterations: 1, direction: 'normal', easing: EASINGS[track.easing]
                });
            });
        });
        if (edit.origin) plan.origin = { x: edit.origin.x, y: edit.origin.y };
        plan.edited = true;
        finishTiming(plan);
        if (!isValidPlan(plan)) throw editError('EDIT_INVALID', 'plan');
        return plan;
    }

    /** An empty edit, ready for layers. */
    function emptyEdit(duration) {
        return { duration: isInt(duration, EDIT_LIMITS.duration[0], EDIT_LIMITS.duration[1]) ? duration : 3000, nextId: 1, layers: [] };
    }

    return { buildPlan: buildPlan, sanitizeParams: sanitizeParams, sanitizeCommon: sanitizeCommon, staggerApplies: staggerApplies, SHAPE_PRESETS: SHAPE_PRESETS, isValidPlan: isValidPlan, restingStyles: restingStyles, planToCss: planToCss, selector: selector, scopedSelector: scopedSelector, BUILDERS: BUILDERS, EASINGS: EASINGS, PROPERTIES: PROPERTIES, validateEdit: validateEdit, compileEdit: compileEdit, emptyEdit: emptyEdit, EDIT_LIMITS: EDIT_LIMITS, EDIT_PROPS: EDIT_PROPS };
}));
