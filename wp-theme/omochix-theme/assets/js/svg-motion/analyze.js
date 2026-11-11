/**
 * SVG Motion Studio: what a normalized logo contains, which presets it can use,
 * and what to tell the viewer about what the safety pipeline removed.
 */
(function (root) {
    'use strict';

    var GEOMETRY = 'path,rect,circle,ellipse,line,polyline,polygon';
    var HIDDEN = 'defs,clipPath,mask,pattern,marker,symbol';

    function visible(list) {
        return Array.prototype.filter.call(list, function (element) { return !element.closest(HIDDEN); });
    }

    /** @param {string} normalizedSvg Output of OmxSvgSafety.normalize(). */
    function analyze(normalizedSvg) {
        var svg = new DOMParser().parseFromString(normalizedSvg, 'image/svg+xml').documentElement;
        return {
            shapes: visible(svg.querySelectorAll(GEOMETRY)).length,
            text: visible(svg.querySelectorAll('text')).length,
            images: visible(svg.querySelectorAll('image')).length,
            gradients: svg.querySelectorAll('linearGradient,radialGradient').length,
            hasViewBox: svg.hasAttribute('viewBox')
        };
    }

    function isDrawable(analysis) {
        return analysis.shapes + analysis.text + analysis.images > 0;
    }

    /** Warning keys for a logo that was accepted. Messages live in the page data. */
    function warnings(analysis, report) {
        var out = [];
        var removed = report.removed.join(' ');
        if (analysis.text) out.push('text');
        if (/element:(?:script)|href:a|handler/.test(removed)) out.push('script');
        if (/element:(?:foreignObject|iframe|embed|object|feImage)|namespace:|href:image|use:external|url:/.test(removed)) out.push('external');
        if (/element:(?:animate|animateTransform|animateMotion|set)|css:at-rule/.test(removed)) out.push('motion');
        if (/css:/.test(removed)) out.push('style');
        if (!analysis.shapes && analysis.images) out.push('imageOnly');
        return out.filter(function (key, index) { return out.indexOf(key) === index; });
    }

    /**
     * @param {object} preset
     * @param {object} layout Layout from OmxSvgFrame.prepare().
     * @param {string[]} shapePresets Presets that animate each shape.
     * @return {{available: boolean, reason: string}} reason: '', 'noShapes', 'tooManyShapes' or 'onePart' (still available).
     */
    function availability(preset, layout, shapePresets) {
        if (shapePresets.indexOf(preset.id) !== -1) {
            if (!layout.geometry) return { available: false, reason: 'noShapes' };
            if (layout.tooManyShapes) return { available: false, reason: 'tooManyShapes' };
        }
        if (preset.structure === 'parts' && layout.parts < 2) return { available: true, reason: 'onePart' };
        return { available: true, reason: '' };
    }

    root.OmxSvgAnalyze = { analyze: analyze, isDrawable: isDrawable, warnings: warnings, availability: availability };
}(window));
