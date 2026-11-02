/**
 * SVG Motion Studio: preset gallery and preview controls.
 *
 * Every sample goes through the same safety pipeline a user's file will. The
 * preview runs in a sandboxed frame; thumbnails are <img> elements with CSS
 * animation (no script, no network). State lives in the URL hash and in
 * localStorage, and is validated on the way back in.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-svg-motion]');
    var dataElement = document.getElementById('omx-svg-motion-data');
    if (!root || !dataElement || !window.OmxSvgSafety || !window.OmxSvgCompile || !window.OmxSvgFrame) return;

    var data = JSON.parse(dataElement.textContent);
    var Safety = window.OmxSvgSafety;
    var Compile = window.OmxSvgCompile;
    var Frame = window.OmxSvgFrame;
    var STORAGE_KEY = 'omx-svg-motion-state';
    var THUMB_PREFIX = 'omxt-';

    var reducedQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
    // No prototype: a hash or saved state naming __proto__ must not look like a preset.
    var presets = Object.create(null);
    data.presets.forEach(function (preset) { presets[preset.id] = preset; });

    var stages = Object.create(null);
    var state = { preset: data.presets[0].id, sample: data.samples[0].id, params: Object.create(null) };

    var els = {
        stage: root.querySelector('[data-svgm-stage]'),
        title: root.querySelector('[data-svgm-title]'),
        description: root.querySelector('[data-svgm-description]'),
        toggle: root.querySelector('[data-svgm-toggle]'),
        reset: root.querySelector('[data-svgm-reset]'),
        defaults: root.querySelector('[data-svgm-defaults]'),
        params: root.querySelector('[data-svgm-params]'),
        status: root.querySelector('[data-svgm-status]'),
        error: root.querySelector('[data-svgm-error]'),
        thumbsToggle: root.querySelector('[data-svgm-thumbs]'),
        count: root.querySelector('[data-svgm-count]')
    };

    var frameEl = null;
    var frameReady = false;
    var playing = false;
    var thumbsMoving = !reducedQuery.matches;

    function stageFor(sampleId) {
        if (stages[sampleId]) return stages[sampleId];
        var sample = data.samples.filter(function (s) { return s.id === sampleId; })[0];
        var normalized = Safety.normalize(sample.svg, { prefix: 'omx-' + sampleId + '-' });
        stages[sampleId] = Frame.prepare(normalized.svg, data.budget);
        return stages[sampleId];
    }

    function paramsFor(presetId) {
        return Compile.sanitizeParams(presets[presetId], state.params[presetId]);
    }

    function currentPlan() {
        return Compile.buildPlan(presets[state.preset], paramsFor(state.preset), stageFor(state.sample).layout);
    }

    function announce(message) {
        if (els.status) els.status.textContent = message;
    }

    function showError(message) {
        if (!els.error) return;
        els.error.textContent = message || '';
        els.error.hidden = !message;
    }

    /* ---------------------------------------------------------------- state */
    function readHash() {
        var out = {};
        location.hash.replace(/^#/, '').split('&').forEach(function (pair) {
            var at = pair.indexOf('=');
            if (at > 0) {
                try { out[decodeURIComponent(pair.slice(0, at))] = decodeURIComponent(pair.slice(at + 1)); } catch (error) { /* ignore */ }
            }
        });
        return out;
    }

    function loadState() {
        var saved = null;
        try { saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || 'null'); } catch (error) { saved = null; }
        if (saved && typeof saved === 'object') {
            if (presets[saved.preset]) state.preset = saved.preset;
            if (data.samples.some(function (s) { return s.id === saved.sample; })) state.sample = saved.sample;
            if (saved.params && typeof saved.params === 'object') {
                Object.keys(saved.params).forEach(function (id) {
                    if (presets[id]) state.params[id] = Compile.sanitizeParams(presets[id], saved.params[id]);
                });
            }
        }
        var hash = readHash();
        if (presets[hash.p]) {
            state.preset = hash.p;
            var values = {};
            presets[hash.p].params.forEach(function (param) {
                if (Object.prototype.hasOwnProperty.call(hash, param.key)) values[param.key] = hash[param.key];
            });
            if (Object.keys(values).length) state.params[hash.p] = Compile.sanitizeParams(presets[hash.p], values);
        }
        if (data.samples.some(function (s) { return s.id === hash.s; })) state.sample = hash.s;
    }

    function saveState() {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (error) { /* private mode: the hash still carries the state */ }
        var preset = presets[state.preset];
        var values = paramsFor(state.preset);
        var pairs = ['p=' + encodeURIComponent(state.preset), 's=' + encodeURIComponent(state.sample)];
        preset.params.forEach(function (param) {
            if (values[param.key] !== param.default) pairs.push(encodeURIComponent(param.key) + '=' + encodeURIComponent(values[param.key]));
        });
        if (window.history && window.history.replaceState) {
            window.history.replaceState(window.history.state, '', '#' + pairs.join('&'));
        }
    }

    /* -------------------------------------------------------------- preview */
    function post(message) {
        if (frameEl && frameEl.contentWindow) frameEl.contentWindow.postMessage(message, '*');
    }

    function mountFrame() {
        frameReady = false;
        if (frameEl) frameEl.remove();
        var stage;
        try {
            stage = stageFor(state.sample);
            var srcdoc = Frame.buildDocument(stage.svg, { theme: document.documentElement.dataset.theme });
            frameEl = document.createElement('iframe');
            frameEl.setAttribute('sandbox', 'allow-scripts');
            frameEl.setAttribute('title', els.stage.getAttribute('data-label') || '');
            frameEl.className = 'svg-motion__frame';
            frameEl.srcdoc = srcdoc;
            els.stage.appendChild(frameEl);
            showError('');
        } catch (error) {
            showError(els.error.getAttribute('data-message'));
        }
    }

    function sendPlan(autoplay) {
        if (!frameReady) return;
        var plan;
        try {
            plan = currentPlan();
        } catch (error) {
            // A logo over the shape budget cannot use this preset.
            showError(els.error.getAttribute('data-too-complex'));
            return;
        }
        showError('');
        post({ type: 'omx:plan', plan: plan, autoplay: !!autoplay });
    }

    function setPlaying(next) {
        playing = !!next;
        root.setAttribute('data-svgm-playing', playing ? 'true' : 'false');
        if (els.toggle) {
            els.toggle.textContent = playing ? els.toggle.getAttribute('data-pause') : els.toggle.getAttribute('data-play');
            els.toggle.setAttribute('aria-pressed', playing ? 'true' : 'false');
        }
    }

    window.addEventListener('message', function (event) {
        if (!frameEl || event.source !== frameEl.contentWindow || !event.data || typeof event.data !== 'object') return;
        var type = event.data.type;
        if (type === 'omx:ready') {
            frameReady = true;
            sendPlan(!reducedQuery.matches);
        } else if (type === 'omx:state') {
            setPlaying(!!event.data.playing);
        } else if (type === 'omx:finished') {
            setPlaying(false);
        } else if (type === 'omx:stage-click') {
            post({ type: playing ? 'omx:pause' : 'omx:play' });
        } else if (type === 'omx:rejected') {
            showError(els.error.getAttribute('data-message'));
        }
    });

    /* ----------------------------------------------------------- parameters */
    function formatValue(param, value) {
        return param.type === 'range' ? value + (param.unit ? ' ' + param.unit : '') : value;
    }

    function renderParams() {
        var preset = presets[state.preset];
        var values = paramsFor(state.preset);
        els.params.textContent = '';
        preset.params.forEach(function (param) {
            var id = 'svgm-' + param.key;
            var wrap = document.createElement('div');
            wrap.className = 'svg-motion__param';
            var label = document.createElement('label');
            label.setAttribute('for', id);
            label.textContent = param.label;
            var output = document.createElement('output');
            output.setAttribute('for', id);
            output.textContent = formatValue(param, values[param.key]);
            var head = document.createElement('div');
            head.className = 'svg-motion__param-head';
            head.appendChild(label);
            head.appendChild(output);
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
            input.value = values[param.key];
            input.setAttribute('data-svgm-param', param.key);
            input.addEventListener('input', function () {
                var next = paramsFor(state.preset);
                next[param.key] = input.value;
                state.params[state.preset] = Compile.sanitizeParams(preset, next);
                output.textContent = formatValue(param, state.params[state.preset][param.key]);
                saveState();
                sendPlan(playing || !reducedQuery.matches);
            });
            wrap.appendChild(head);
            wrap.appendChild(input);
            els.params.appendChild(wrap);
        });
    }

    /* ----------------------------------------------------------- thumbnails */
    function thumbUri(presetId, moving) {
        var sample = data.samples[0];
        var normalized = Safety.normalize(sample.svg, { prefix: THUMB_PREFIX });
        var stage = Frame.prepare(normalized.svg, data.budget);
        var plan = Compile.buildPlan(presets[presetId], {}, stage.layout);
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(Frame.animatedSvg(stage.svg, plan, moving));
    }

    function renderThumbs() {
        Array.prototype.forEach.call(root.querySelectorAll('[data-svgm-thumb]'), function (img) {
            img.src = thumbUri(img.getAttribute('data-svgm-thumb'), thumbsMoving);
        });
        if (els.thumbsToggle) {
            els.thumbsToggle.textContent = thumbsMoving ? els.thumbsToggle.getAttribute('data-stop') : els.thumbsToggle.getAttribute('data-start');
            els.thumbsToggle.setAttribute('aria-pressed', thumbsMoving ? 'false' : 'true');
        }
    }

    /* ------------------------------------------------------------- selection */
    function selectPreset(id, options) {
        state.preset = id;
        var preset = presets[id];
        var radio = root.querySelector('input[name="svgm-preset"][value="' + id + '"]');
        if (radio) radio.checked = true;
        els.title.textContent = preset.name;
        els.description.textContent = preset.description;
        root.setAttribute('data-svgm-preset', id);
        renderParams();
        saveState();
        if (!options || !options.silent) {
            sendPlan(!reducedQuery.matches);
            announce(preset.name);
        }
    }

    root.addEventListener('change', function (event) {
        var target = event.target;
        if (target.name === 'svgm-preset' && presets[target.value]) {
            selectPreset(target.value);
        } else if (target.name === 'svgm-sample') {
            state.sample = target.value;
            saveState();
            mountFrame();
        }
    });

    if (els.toggle) els.toggle.addEventListener('click', function () { post({ type: playing ? 'omx:pause' : 'omx:play' }); });
    if (els.reset) els.reset.addEventListener('click', function () { post({ type: 'omx:reset' }); announce(els.reset.textContent); });
    if (els.defaults) {
        els.defaults.addEventListener('click', function () {
            delete state.params[state.preset];
            renderParams();
            saveState();
            sendPlan(!reducedQuery.matches);
            announce(els.defaults.textContent);
        });
    }
    if (els.thumbsToggle) {
        els.thumbsToggle.addEventListener('click', function () {
            thumbsMoving = !thumbsMoving;
            renderThumbs();
        });
    }

    /* Replays a thumbnail when a viewer points at or focuses its card. */
    function replayThumb(card) {
        var img = card.querySelector('[data-svgm-thumb]');
        if (!img || !thumbsMoving) return;
        var src = img.src;
        img.src = '';
        img.src = src;
    }
    Array.prototype.forEach.call(root.querySelectorAll('[data-svgm-card]'), function (card) {
        card.addEventListener('pointerenter', function () { replayThumb(card); });
        card.addEventListener('focusin', function () { replayThumb(card); });
    });

    /* Category filter. */
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
            if (els.count) els.count.textContent = els.count.getAttribute('data-template').replace('%d', shown);
        });
    });

    /* ------------------------------------------------------------------ boot */
    loadState();
    var sampleRadio = root.querySelector('input[name="svgm-sample"][value="' + state.sample + '"]');
    if (sampleRadio) sampleRadio.checked = true;
    selectPreset(state.preset, { silent: true });
    renderThumbs();
    setPlaying(false);
    mountFrame();
    root.setAttribute('data-svgm-ready', 'true');
}());
