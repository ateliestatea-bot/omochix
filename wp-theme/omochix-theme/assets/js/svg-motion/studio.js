/**
 * SVG Motion Studio: taking a logo, applying a preset, adjusting it.
 *
 * Every logo, bundled or the viewer's own, goes through the same pipeline and
 * reaches the screen only as an image. Text from a file is never written into
 * this page. Settings and the logo are saved in this browser; the URL carries
 * only the preset and its values; playback state is never saved.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-svg-motion]');
    var dataElement = document.getElementById('omx-svg-motion-data');
    if (!root || !dataElement || !window.OmxSvgPreview || !window.OmxSvgImport || !window.OmxSvgExport) return;

    var data = JSON.parse(dataElement.textContent);
    var Compile = window.OmxSvgCompile;
    var Frame = window.OmxSvgFrame;
    Frame.configure({ measureFrame: data.measureFrame }); // the versioned measuring frame from the page data
    var Analyze = window.OmxSvgAnalyze;
    var Import = window.OmxSvgImport;
    var Store = window.OmxSvgStore;
    var Safety = window.OmxSvgSafety;
    var messages = data.messages;

    var reduced = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
    // No prototype: a hash or saved state naming __proto__ must not look like a preset.
    var presets = Object.create(null);
    data.presets.forEach(function (preset) { presets[preset.id] = preset; });

    var settings = { preset: data.presets[0].id, sample: data.samples[0].id, params: Object.create(null), common: {}, edit: null };
    // edit: a validated S4 edit (schema 2) or null. S4-0 carries it through preview, save and export; the editor comes with S4-1.
    var logo = null; // { kind: 'sample'|'user', svg, analysis, stages }
    var thumbsMoving = !reduced.matches;
    var thumbUrls = Object.create(null);
    var saveTimer = 0;
    var frameRequest = 0;

    function el(name) { return root.querySelector('[data-svgm-' + name + ']'); }
    var els = {
        stage: el('stage'), title: el('title'), description: el('description'), toggle: el('toggle'), reset: el('reset'),
        defaults: el('defaults'), params: el('params'), common: el('common'), status: el('status'), error: el('error'),
        thumbs: el('thumbs'), count: el('count'), file: el('file'), drop: el('drop'), paste: el('paste'), pasteButton: el('paste-button'),
        logoName: el('logo-name'), warnings: el('warnings'), remove: el('remove'), note: el('note'), storage: el('storage'),
        code: el('code'), copy: el('copy'), downloads: el('downloads'), alt: el('alt'), usageNote: el('usage-note'), originNote: el('origin-note'),
        jsonDownload: el('json-download'), jsonFile: el('json-file')
    };
    var Export = window.OmxSvgExport;
    var exported = null;
    var exportTimer = 0;

    var preview = window.OmxSvgPreview.create(els.stage, function (state) { setPlaying(state.playing); });

    function announce(message) { els.status.textContent = message; }

    function showError(message) {
        els.error.textContent = message || '';
        els.error.hidden = !message;
    }

    function messageFor(code) {
        return messages.errors[code] || messages.errors.DEFAULT;
    }

    function setPlaying(next) {
        root.setAttribute('data-svgm-playing', next ? 'true' : 'false');
        els.toggle.textContent = next ? els.toggle.getAttribute('data-pause') : els.toggle.getAttribute('data-play');
        els.toggle.setAttribute('aria-pressed', next ? 'true' : 'false');
    }

    /* ---------------------------------------------------------------- logo */
    function prepare(svg, overlay) {
        return Frame.prepare(svg, Object.assign({ overlay: !!overlay }, data.budget));
    }

    function stageFor(overlay) {
        var key = overlay ? 'overlay' : 'plain';
        if (!logo.stages[key]) {
            logo.stages[key] = prepare(logo.svg, overlay);
            var problems = Frame.verifyPrepared(logo.stages[key].svg);
            if (problems.length) throw new Safety.SafetyError('VERIFY_FAILED', problems.join('|'));
        }
        return logo.stages[key];
    }

    function needsOverlay(id) {
        return presets[id].structure === 'overlay';
    }

    /** A new logo replaces the current one only after everything about it succeeded. */
    function accept(result, kind, name) {
        logo = { kind: kind, svg: result.svg, analysis: result.analysis, stages: {} };
        els.logoName.textContent = name;
        els.remove.hidden = kind !== 'user';
        var keys = result.warnings;
        els.warnings.textContent = '';
        keys.forEach(function (key) {
            var item = document.createElement('li');
            item.textContent = messages.warnings[key];
            els.warnings.appendChild(item);
        });
        els.warnings.hidden = !keys.length;
        root.setAttribute('data-svgm-logo', kind);
        refreshAvailability();
        renderThumbs();
    }

    function loadText(text, kind, name, prefix) {
        return Import.fromText(text, { prefix: prefix, prepare: function (svg) { return prepare(svg, false); } }).then(function (result) {
            accept(result, kind, name);
            showError('');
            selectPreset(settings.preset, { announce: false });
            return true;
        });
    }

    function takeUserLogo(promiseOfText, name) {
        return promiseOfText.then(function (text) {
            return loadText(text, 'user', name);
        }).then(function () {
            var dropped = dropEdit(false); // the part numbers it referred to belong to the previous logo
            announce(messages.loaded + (dropped ? ' ' + messages.editDiscarded : ''));
            root.setAttribute('data-svgm-last', 'ok');
            scheduleSave();
        }, function (error) {
            // The current logo stays as it was.
            showError(messageFor(error && error.code));
            root.setAttribute('data-svgm-last', (error && error.code) || 'DEFAULT');
        });
    }

    function loadSample(id) {
        var sample = data.samples.filter(function (s) { return s.id === id; })[0] || data.samples[0];
        settings.sample = sample.id;
        return loadText(sample.svg, 'sample', sample.name);
    }

    /* ------------------------------------------------------------ settings */
    function commonValues() {
        return Compile.sanitizeCommon(data.common, settings.common);
    }

    function paramsFor(id) {
        return Compile.sanitizeParams(presets[id], settings.params[id]);
    }

    function currentPlan() {
        var base = Compile.buildPlan(presets[settings.preset], paramsFor(settings.preset), stageFor(needsOverlay(settings.preset)).layout, commonValues());
        return Compile.compileEdit(base, settings.edit);
    }

    /* ---------------------------------------------------------------- edits (S4) */
    function markEdit() {
        root.setAttribute('data-svgm-edit', settings.edit ? String(settings.edit.layers.length) : 'none');
    }

    /** Drops the edit when the logo it was made for goes away. The confirmation step belongs to the S4-1 editor. */
    function dropEdit(reason) {
        if (!settings.edit) return false;
        settings.edit = null;
        markEdit();
        markOrigin('none');
        if (reason) announce(messages.editDiscarded);
        return true;
    }

    /**
     * Takes an untrusted edit for the current logo: checked against the logo's parts and compiled once before it is kept.
     * @return {boolean} false when it was refused (the current state is unchanged).
     */
    function takeEdit(edit, options) {
        options = options || {};
        if (!edit) { dropEdit(false); return true; }
        var clean;
        try {
            clean = Compile.validateEdit(edit, stageFor(false).layout);
            Compile.compileEdit(Compile.buildPlan(presets[settings.preset], paramsFor(settings.preset), stageFor(needsOverlay(settings.preset)).layout, commonValues()), clean);
        } catch (error) {
            if (options.throwOnError) throw error;
            dropEdit(true);
            return false;
        }
        settings.edit = clean;
        markEdit();
        if (clean.origin) markOrigin('provided');
        else { markOrigin('none'); measureOrigin(); }
        return true;
    }

    /*
     * The root's origin is pinned to the logo's own centre (see compile.js) by measuring it once in the sandbox frame.
     * When that cannot be done (no frame, a timeout, an unreadable box) the centre of the viewBox stands in: the same in
     * every browser, said on screen, and not saved, so the next visit measures again. While measuring, nothing is offered
     * for copy or download (the code would change underneath).
     */
    var measuring = null, originSource = 'none';
    function viewBoxCentre() {
        var box = (new DOMParser().parseFromString(stageFor(false).svg, 'image/svg+xml').documentElement.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(Number);
        return box.length === 4 && box.every(isFinite) ? { x: box[0] + box[2] / 2, y: box[1] + box[3] / 2 } : null;
    }

    /** source: 'none' | 'measured' | 'fallback' | 'provided' (from a settings file or the saved state). */
    function markOrigin(source) {
        originSource = source;
        root.setAttribute('data-svgm-origin-source', source);
        if (settings.edit && settings.edit.origin) root.setAttribute('data-svgm-origin', settings.edit.origin.x + ',' + settings.edit.origin.y);
        else root.removeAttribute('data-svgm-origin');
        els.originNote.textContent = source === 'fallback' ? messages.originFallback : '';
        els.originNote.hidden = source !== 'fallback';
    }

    function measureOrigin() {
        if (measuring || !settings.edit) return;
        var mine = settings.edit;
        var settle = function (box) {
            measuring = null;
            // The edit changed meanwhile: measure for the new one if it needs it, otherwise just rebuild the export.
            if (settings.edit !== mine) { if (settings.edit && !settings.edit.origin) measureOrigin(); else refreshExport(); return; }
            var centre = box ? { x: box.x + box.width / 2, y: box.y + box.height / 2 } : viewBoxCentre();
            if (centre) {
                try { settings.edit = Compile.validateEdit(Object.assign({}, settings.edit, { origin: centre }), stageFor(false).layout); } catch (error) { centre = null; }
            }
            markOrigin(centre ? (box ? 'measured' : 'fallback') : 'none');
            if (!box && centre) announce(messages.originFallback);
            preview.update(currentPlan()).catch(function () { showError(messages.errors.DEFAULT); });
            scheduleSave();
        };
        measuring = Frame.measure(stageFor(false).svg).then(settle, function () { settle(null); });
        refreshExport();
    }

    /** The edit as it is saved: a stand-in origin is left out, so it is measured again next time. */
    function editForSave() {
        if (!settings.edit || originSource !== 'fallback') return settings.edit;
        var copy = Object.assign({}, settings.edit);
        delete copy.origin;
        return copy;
    }

    function plainSettings() {
        var params = {};
        Object.keys(settings.params).forEach(function (key) { params[key] = settings.params[key]; });
        return { preset: settings.preset, sample: settings.sample, params: params, common: settings.common };
    }

    function adopt(saved) {
        if (!saved || typeof saved !== 'object') return;
        if (typeof saved.preset === 'string' && presets[saved.preset]) settings.preset = saved.preset;
        if (data.samples.some(function (s) { return s.id === saved.sample; })) settings.sample = saved.sample;
        if (saved.params && typeof saved.params === 'object') {
            Object.keys(saved.params).forEach(function (id) {
                if (presets[id]) settings.params[id] = Compile.sanitizeParams(presets[id], saved.params[id]);
            });
        }
        settings.common = Compile.sanitizeCommon(data.common, saved.common);
    }

    function readHash() {
        var out = Object.create(null);
        location.hash.replace(/^#/, '').split('&').forEach(function (pair) {
            var at = pair.indexOf('=');
            if (at > 0) {
                try { out[decodeURIComponent(pair.slice(0, at))] = decodeURIComponent(pair.slice(at + 1)); } catch (error) { /* ignore */ }
            }
        });
        return out;
    }

    function adoptHash() {
        var hash = readHash();
        if (hash.p && presets[hash.p]) {
            settings.preset = hash.p;
            var values = {};
            presets[hash.p].params.forEach(function (param) {
                if (param.key in hash) values[param.key] = hash[param.key];
            });
            if (Object.keys(values).length) settings.params[hash.p] = Compile.sanitizeParams(presets[hash.p], values);
        }
        var common = {};
        data.common.forEach(function (param) {
            if ('c.' + param.key in hash) common[param.key] = hash['c.' + param.key];
        });
        if (Object.keys(common).length) settings.common = Compile.sanitizeCommon(data.common, Object.assign({}, settings.common, common));
        return hash;
    }

    /** The URL gets the preset and values, and the sample's name. A viewer's logo is never in it. */
    function writeHash() {
        var values = paramsFor(settings.preset);
        var pairs = ['p=' + encodeURIComponent(settings.preset)];
        if (logo && logo.kind === 'sample') pairs.push('s=' + encodeURIComponent(settings.sample));
        presets[settings.preset].params.forEach(function (param) {
            if (values[param.key] !== param.default) pairs.push(encodeURIComponent(param.key) + '=' + encodeURIComponent(values[param.key]));
        });
        var common = commonValues();
        data.common.forEach(function (param) {
            if (common[param.key] !== param.default) pairs.push('c.' + param.key + '=' + encodeURIComponent(common[param.key]));
        });
        // WebKit allows 100 replaceState calls per 10 seconds and throws beyond that. A slider must not be able to reach it.
        try {
            if (window.history && window.history.replaceState) window.history.replaceState(window.history.state, '', '#' + pairs.join('&'));
        } catch (error) { /* the next save tries again */ }
    }

    function usage() {
        var checked = root.querySelector('input[name="svgm-usage"]:checked');
        return checked ? checked.value : 'image';
    }

    /** Copy and every download (files and settings) are offered only for an export that matches the screen. */
    function offer(open) {
        els.copy.disabled = !open;
        els.jsonDownload.disabled = !open;
        Array.prototype.forEach.call(els.downloads.querySelectorAll('button'), function (button) { button.disabled = !open; });
    }

    /* The export always matches what is on screen: rebuilt shortly after any change, and withdrawn until then. */
    function refreshExport() {
        window.clearTimeout(exportTimer);
        offer(false);
        if (measuring) {
            exported = null;
            els.code.textContent = messages.measuring;
            els.downloads.textContent = '';
            root.setAttribute('data-svgm-export', 'measuring');
            return;
        }
        exportTimer = window.setTimeout(function () {
            if (!logo) return;
            try {
                exported = Export.build({
                    svg: logo.svg, preset: presets[settings.preset], params: paramsFor(settings.preset), common: commonValues(),
                    commonList: data.common, budget: data.budget, label: els.alt.value, edit: settings.edit
                });
            } catch (error) {
                // Nothing stale stays offered: no files, no copy, and the reason in place of the code.
                exported = null;
                els.code.textContent = messages.errors.EXPORT_FAILED;
                els.downloads.textContent = '';
                offer(false);
                root.setAttribute('data-svgm-export', 'failed');
                return;
            }
            renderExport();
            offer(true);
        }, 120);
    }

    var FILES = { image: ['animated', 'still'], inline: [], external: ['css'], script: ['js'] };
    var TYPES = { animated: 'image/svg+xml', still: 'image/svg+xml', css: 'text/css', js: 'text/javascript', json: 'application/json' };

    function renderExport() {
        var kind = usage();
        els.code.textContent = exported.snippets[kind];
        els.usageNote.textContent = messages.usages[kind];
        els.downloads.textContent = '';
        FILES[kind].forEach(function (key) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'svg-motion__button';
            button.textContent = messages.download[key];
            button.setAttribute('data-svgm-download', key);
            button.addEventListener('click', function () { download(key); });
            els.downloads.appendChild(button);
        });
        root.setAttribute('data-svgm-export', exported.scope);
    }

    /* File names are fixed by the studio, never taken from the viewer's input. */
    function download(key) {
        if (!exported) return;
        var url = URL.createObjectURL(new Blob([exported.files[key]], { type: TYPES[key] }));
        var link = document.createElement('a');
        link.href = url;
        link.download = exported.names[key];
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return fallbackCopy(text); });
        }
        return Promise.resolve(fallbackCopy(text));
    }

    function fallbackCopy(text) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        var copied = false;
        try { copied = document.execCommand('copy'); } catch (error) { copied = false; }
        area.remove();
        return copied;
    }

    function scheduleSave() {
        refreshExport();
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(function () {
            writeHash();
            Store.save(logo && logo.kind === 'user' ? logo.svg : null, plainSettings(), editForSave()).then(function (saved) {
                root.setAttribute('data-svgm-saved', saved ? String(Date.now()) : 'failed');
                els.storage.hidden = saved;
            });
        }, 200);
    }

    /* ------------------------------------------------------------ controls */
    function formatValue(param, value) {
        return param.type === 'range' ? value + (param.unit ? ' ' + param.unit : '') : value;
    }

    function control(param, value, id, attribute, onChange) {
        var wrap = document.createElement('div');
        wrap.className = 'svg-motion__param';
        var label = document.createElement('label');
        label.setAttribute('for', id);
        label.textContent = param.label;
        var head = document.createElement('div');
        head.className = 'svg-motion__param-head';
        head.appendChild(label);
        var output = null;
        if (param.type !== 'select') {
            output = document.createElement('output');
            output.setAttribute('for', id);
            output.textContent = formatValue(param, value);
            head.appendChild(output);
        }
        var input;
        if (param.type === 'select') {
            input = document.createElement('select');
            param.options.forEach(function (option) {
                var element = document.createElement('option');
                element.value = option.value;
                element.textContent = option.label;
                input.appendChild(element);
            });
        } else {
            input = document.createElement('input');
            input.type = param.type;
            if (param.type === 'range') { input.min = param.min; input.max = param.max; input.step = param.step; }
        }
        input.id = id;
        input.value = value;
        input.setAttribute(attribute, param.key);
        input.addEventListener('input', function () {
            var clean = onChange(input.value);
            if (output) output.textContent = formatValue(param, clean);
            queueUpdate();
        });
        wrap.appendChild(head);
        wrap.appendChild(input);
        return wrap;
    }

    function renderControls() {
        var preset = presets[settings.preset];
        var values = paramsFor(settings.preset);
        els.params.textContent = '';
        preset.params.forEach(function (param) {
            els.params.appendChild(control(param, values[param.key], 'svgm-' + param.key, 'data-svgm-param', function (raw) {
                var next = paramsFor(settings.preset);
                next[param.key] = raw;
                settings.params[settings.preset] = Compile.sanitizeParams(preset, next);
                return settings.params[settings.preset][param.key];
            }));
        });
        var common = commonValues();
        var layout = stageFor(needsOverlay(settings.preset)).layout;
        els.common.textContent = '';
        data.common.forEach(function (param) {
            if (param.key === 'stagger' && !Compile.staggerApplies(preset, layout)) return;
            els.common.appendChild(control(param, common[param.key], 'svgm-c-' + param.key, 'data-svgm-common', function (raw) {
                var next = commonValues();
                next[param.key] = raw;
                settings.common = Compile.sanitizeCommon(data.common, next);
                return settings.common[param.key];
            }));
        });
    }

    /* A slider fires faster than a picture can be built: one update per frame, with the latest values. */
    function queueUpdate() {
        if (frameRequest) return;
        frameRequest = window.requestAnimationFrame(function () {
            frameRequest = 0;
            preview.update(currentPlan()).catch(function () { showError(messages.errors.DEFAULT); });
            scheduleSave();
        });
    }

    /* ---------------------------------------------------------- selection */
    function refreshAvailability() {
        var plain = stageFor(false).layout;
        Array.prototype.forEach.call(root.querySelectorAll('[data-svgm-card]'), function (card) {
            var input = card.querySelector('input');
            var state = Analyze.availability(presets[input.value], plain, Compile.SHAPE_PRESETS);
            input.disabled = !state.available;
            card.classList.toggle('is-disabled', !state.available);
            var reason = card.querySelector('[data-svgm-reason]');
            reason.textContent = state.available ? '' : messages.unavailable[state.reason];
            reason.hidden = state.available;
        });
    }

    function selectPreset(id, options) {
        options = options || {};
        var state = Analyze.availability(presets[id], stageFor(false).layout, Compile.SHAPE_PRESETS);
        if (!state.available) {
            announce(messages.unavailable[state.reason]);
            id = data.presets.filter(function (p) { return Analyze.availability(p, stageFor(false).layout, Compile.SHAPE_PRESETS).available; })[0].id;
            state = Analyze.availability(presets[id], stageFor(false).layout, Compile.SHAPE_PRESETS);
        }
        settings.preset = id;
        var preset = presets[id];
        var radio = root.querySelector('input[name="svgm-preset"][value="' + id + '"]');
        if (radio) radio.checked = true;
        els.title.textContent = preset.name;
        els.description.textContent = preset.description;
        els.note.textContent = state.reason === 'onePart' ? messages.onePart : '';
        els.note.hidden = !els.note.textContent;
        root.setAttribute('data-svgm-preset', id);
        renderControls();
        var stage = stageFor(needsOverlay(id));
        preview.show(stage.svg, currentPlan(), { play: !reduced.matches, alt: preset.name }).catch(function () { showError(messages.errors.DEFAULT); });
        if (options.announce !== false) announce(preset.name);
        scheduleSave();
    }

    /* ---------------------------------------------------------- thumbnails */
    function renderThumb(img) {
        var id = img.getAttribute('data-svgm-thumb');
        if (thumbUrls[id]) { URL.revokeObjectURL(thumbUrls[id]); delete thumbUrls[id]; }
        var preset = presets[id];
        var stage = stageFor(needsOverlay(id));
        var plan;
        try {
            plan = Compile.buildPlan(preset, {}, stage.layout);
        } catch (error) {
            img.removeAttribute('src');
            return;
        }
        var text = Frame.animatedSvg(stage.svg, plan, thumbsMoving ? { animate: true } : { animate: true, time: plan.restTime, frozen: true });
        thumbUrls[id] = URL.createObjectURL(new Blob([text], { type: 'image/svg+xml' }));
        img.src = thumbUrls[id];
    }

    var thumbObserver = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) { thumbObserver.unobserve(entry.target); renderThumb(entry.target); }
        });
    }, { rootMargin: '200px 0px' }) : null;

    function renderThumbs() {
        Array.prototype.forEach.call(root.querySelectorAll('[data-svgm-thumb]'), function (img) {
            if (thumbObserver) { thumbObserver.unobserve(img); thumbObserver.observe(img); } else renderThumb(img);
        });
        els.thumbs.textContent = thumbsMoving ? els.thumbs.getAttribute('data-stop') : els.thumbs.getAttribute('data-start');
        els.thumbs.setAttribute('aria-pressed', thumbsMoving ? 'false' : 'true');
    }

    /* -------------------------------------------------------------- events */
    root.addEventListener('change', function (event) {
        var target = event.target;
        if (target.name === 'svgm-preset' && presets[target.value]) {
            selectPreset(target.value);
        } else if (target.name === 'svgm-sample') {
            loadSample(target.value).then(function () { if (dropEdit(false)) announce(messages.editDiscarded); scheduleSave(); }, function (error) { showError(messageFor(error && error.code)); });
        } else if (target === els.file && target.files && target.files[0]) {
            var file = target.files[0];
            takeUserLogo(Import.readFile(file), file.name);
            target.value = '';
        }
    });

    els.pasteButton.addEventListener('click', function () {
        takeUserLogo(Promise.resolve(els.paste.value), messages.pastedName);
    });

    ['dragenter', 'dragover'].forEach(function (name) {
        els.drop.addEventListener(name, function (event) { event.preventDefault(); els.drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (name) {
        els.drop.addEventListener(name, function () { els.drop.classList.remove('is-over'); });
    });
    els.drop.addEventListener('drop', function (event) {
        event.preventDefault();
        var files = event.dataTransfer && event.dataTransfer.files;
        if (!files || !files.length) return;
        if (files.length > 1) { showError(messages.errors.TOO_MANY_FILES); root.setAttribute('data-svgm-last', 'TOO_MANY_FILES'); return; }
        takeUserLogo(Import.readFile(files[0]), files[0].name);
    });
    // A file dropped beside the zone must not navigate away and lose the work.
    ['dragover', 'drop'].forEach(function (name) {
        window.addEventListener(name, function (event) {
            if (event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types || [], 'Files') !== -1) event.preventDefault();
        });
    });

    els.remove.addEventListener('click', function () {
        Store.remove().then(function () {
            return loadSample(settings.sample);
        }).then(function () {
            var sampleRadio = root.querySelector('input[name="svgm-sample"][value="' + settings.sample + '"]');
            if (sampleRadio) sampleRadio.checked = true;
            announce(messages.removed);
            scheduleSave();
        });
    });

    els.toggle.addEventListener('click', togglePlayback);
    els.stage.addEventListener('click', togglePlayback);
    function togglePlayback() {
        if (preview.state().playing) preview.pause(); else preview.play();
    }
    els.reset.addEventListener('click', function () { preview.reset(); announce(els.reset.textContent); });
    els.defaults.addEventListener('click', function () {
        delete settings.params[settings.preset];
        settings.common = {};
        renderControls();
        preview.update(currentPlan());
        announce(els.defaults.textContent);
        scheduleSave();
    });
    els.thumbs.addEventListener('click', function () { thumbsMoving = !thumbsMoving; renderThumbs(); });

    root.addEventListener('change', function (event) {
        if (event.target.name === 'svgm-usage' && exported) renderExport();
    });
    els.alt.addEventListener('input', refreshExport);
    els.copy.addEventListener('click', function () {
        if (!exported) return;
        copyText(exported.snippets[usage()]).then(function (copied) {
            announce(copied ? messages.copied : messages.copyFailed);
            root.setAttribute('data-svgm-copied', copied ? 'true' : 'false');
        });
    });
    els.jsonDownload.addEventListener('click', function () { download('json'); });
    els.jsonFile.addEventListener('change', function () {
        var file = els.jsonFile.files && els.jsonFile.files[0];
        els.jsonFile.value = '';
        if (!file) return;
        root.removeAttribute('data-svgm-last');
        // The logo inside is untrusted: it goes through the whole import pipeline, and settings are re-validated.
        var reading = file.size > 2097152 ? Promise.reject(new Safety.SafetyError('JSON_TOO_LARGE')) : file.text();
        reading.then(function (text) {
            var parsed = Export.parseSettings(text);
            if (parsed.edit) Compile.validateEdit(parsed.edit); // shape and limits first, so a bad file never replaces the logo
            return loadText(parsed.svg, 'user', file.name, '').then(function () { return parsed; });
        }).then(function (parsed) {
            adopt(parsed.settings);
            selectPreset(settings.preset, { announce: false });
            // Against this logo's parts and the chosen preset; a refusal drops the edit and says so, the logo stays.
            var kept = takeEdit(parsed.edit);
            preview.update(currentPlan()).catch(function () { showError(messages.errors.DEFAULT); });
            announce(kept ? messages.imported : messages.imported + ' ' + messages.editDiscarded);
            root.setAttribute('data-svgm-last', 'ok');
            scheduleSave();
        }, function (error) {
            showError(messageFor(error && error.code));
            root.setAttribute('data-svgm-last', (error && error.code) || 'DEFAULT');
        });
    });

    function replayThumb(card) {
        var img = card.querySelector('[data-svgm-thumb]');
        if (img && thumbsMoving && img.getAttribute('src')) { var src = img.src; img.src = ''; img.src = src; }
    }
    Array.prototype.forEach.call(root.querySelectorAll('[data-svgm-card]'), function (card) {
        card.addEventListener('pointerenter', function () { replayThumb(card); });
        card.addEventListener('focusin', function () { replayThumb(card); });
    });

    Array.prototype.forEach.call(root.querySelectorAll('[data-svgm-filter]'), function (chip) {
        chip.addEventListener('click', function () {
            var category = chip.getAttribute('data-svgm-filter');
            Array.prototype.forEach.call(root.querySelectorAll('[data-svgm-filter]'), function (other) {
                other.setAttribute('aria-pressed', other === chip ? 'true' : 'false');
            });
            var shown = 0;
            Array.prototype.forEach.call(root.querySelectorAll('[data-svgm-card]'), function (card) {
                var match = category === 'all' || card.getAttribute('data-category') === category;
                card.hidden = !match;
                if (match) shown++;
            });
            els.count.textContent = els.count.getAttribute('data-template').replace('%d', shown);
        });
    });

    /* ---------------------------------------------------------------- boot */
    function ready() {
        var sampleRadio = root.querySelector('input[name="svgm-sample"][value="' + settings.sample + '"]');
        if (sampleRadio && logo.kind === 'sample') sampleRadio.checked = true;
        setPlaying(false);
        root.setAttribute('data-svgm-ready', 'true');
    }

    setPlaying(false);
    Store.load().then(function (loaded) {
        if (loaded.status === 'discarded') announce(messages.discarded);
        if (loaded.status === 'unavailable') els.storage.hidden = false;
        var record = loaded.record;
        if (record) adopt(record.settings);
        var hash = adoptHash();
        var wantsSample = hash.s && data.samples.some(function (s) { return s.id === hash.s; });
        if (wantsSample) settings.sample = hash.s;
        // Whatever was saved is untrusted: it goes through the whole pipeline again.
        var restoreEdit = function (loadedLogo) {
            // Saved edits are untrusted too: refused ones are dropped, and the preview is rebuilt with what was kept.
            if (record && record.edit && loadedLogo === true) takeEdit(record.edit);
            markEdit();
            if (settings.edit) preview.update(currentPlan()).catch(function () { showError(messages.errors.DEFAULT); });
        };
        if (record && record.svg && !wantsSample) {
            return loadText(record.svg, 'user', messages.savedName, '').then(function () { return true; }, function () {
                announce(messages.discarded);
                return Store.remove().then(function () { return loadSample(settings.sample); });
            }).then(restoreEdit);
        }
        return loadSample(settings.sample).then(restoreEdit);
    }).then(ready, function () {
        showError(messages.errors.DEFAULT);
        root.setAttribute('data-svgm-ready', 'failed');
    });
}());
