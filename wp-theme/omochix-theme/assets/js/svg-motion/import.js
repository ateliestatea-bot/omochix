/**
 * SVG Motion Studio: taking a logo in. A file or pasted text ends up here and
 * stays in the browser. Nothing is sent anywhere. Rejections carry a stable
 * code the page turns into a message.
 */
(function (root) {
    'use strict';

    var Safety = root.OmxSvgSafety;
    var ACCEPTED_TYPES = ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', ''];

    function reject(code, detail) {
        throw new Safety.SafetyError(code, detail);
    }

    /* The picture must really contain something: a sanitizer that removed everything is a failure, not a success. */
    function hasInk(preparedSvg) {
        return new Promise(function (resolve) {
            var doc = new DOMParser().parseFromString(preparedSvg, 'image/svg+xml');
            doc.documentElement.setAttribute('width', '64');
            doc.documentElement.setAttribute('height', '64');
            var image = new Image();
            image.onload = function () {
                try {
                    var canvas = document.createElement('canvas');
                    canvas.width = 64;
                    canvas.height = 64;
                    var context = canvas.getContext('2d', { willReadFrequently: true });
                    context.drawImage(image, 0, 0, 64, 64);
                    var data = context.getImageData(0, 0, 64, 64).data;
                    for (var i = 3; i < data.length; i += 4) {
                        if (data[i] > 8) { resolve(true); return; }
                    }
                    resolve(false);
                } catch (error) {
                    // Some browsers refuse to read an SVG image back: not a reason to refuse the logo.
                    resolve(true);
                }
            };
            image.onerror = function () { resolve(false); };
            image.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(doc));
        });
    }

    /**
     * @param {string} text Untrusted SVG text.
     * @param {{prepare: function(string): object, prefix?: string}} options
     * @return {Promise<{svg: string, analysis: object, warnings: string[]}>}
     */
    function fromText(text, options) {
        return Promise.resolve().then(function () {
            if (typeof text !== 'string' || !text.trim()) reject('EMPTY_INPUT');
            // A saved logo already carries its prefix.
            var normalized = Safety.normalize(text, { prefix: options.prefix === undefined ? 'omx-' : options.prefix });
            var analysis = root.OmxSvgAnalyze.analyze(normalized.svg);
            if (!root.OmxSvgAnalyze.isDrawable(analysis)) reject('EMPTY_RESULT');
            var stage = options.prepare(normalized.svg);
            var problems = root.OmxSvgFrame.verifyPrepared(stage.svg);
            if (problems.length) reject('VERIFY_FAILED', problems.join('|'));
            return hasInk(stage.svg).then(function (ink) {
                if (!ink) reject('EMPTY_RESULT');
                return { svg: normalized.svg, analysis: analysis, warnings: root.OmxSvgAnalyze.warnings(analysis, normalized.report) };
            });
        });
    }

    /** @param {File} file @return {Promise<string>} The file's text, after the checks that need no parsing. */
    function readFile(file) {
        return new Promise(function (resolve, rejectPromise) {
            var name = String(file.name || '').toLowerCase();
            var extensionOk = /\.svg$/.test(name);
            if (file.size > Safety.LIMITS.bytes) { rejectPromise(new Safety.SafetyError('TOO_LARGE')); return; }
            if (ACCEPTED_TYPES.indexOf(file.type) === -1 || !extensionOk && file.type !== 'image/svg+xml') {
                rejectPromise(new Safety.SafetyError('NOT_SVG_FILE'));
                return;
            }
            var reader = new FileReader();
            reader.onerror = function () { rejectPromise(new Safety.SafetyError('READ_FAILED')); };
            reader.onload = function () {
                try {
                    // Strict: a file that is not UTF-8 is refused rather than silently misread.
                    resolve(new TextDecoder('utf-8', { fatal: true }).decode(reader.result));
                } catch (error) {
                    rejectPromise(new Safety.SafetyError('NOT_UTF8'));
                }
            };
            reader.readAsArrayBuffer(file);
        });
    }

    root.OmxSvgImport = { fromText: fromText, readFile: readFile };
}(window));
