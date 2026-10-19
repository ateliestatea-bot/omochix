/**
 * Learn v2 / Motion Gallery: the "調整する" (playground) panel.
 *
 * Binds the parameter controls printed by template-parts/learn-gallery/
 * playground.php, on the detail page and inside the detail dialog (the
 * gallery script fires "omx:motion:detail" after rendering a fragment).
 * Each change is sanitized with learn-params.js, pushed into the sample's
 * live previews (detail, expanded view) as CSS custom properties /
 * window.omxParams, written into the copy targets (prompt and code) through
 * the templates printed next to the panel, and mirrored into the URL hash (#p=…) so
 * the adjusted state can be shared. Without this file the controls are
 * inert and the defaults still describe the sample.
 */
(function () {
    'use strict';

    var gallery = window.omochixMotionGallery;
    var P = window.omochixMotionParams;
    if (!gallery || !P) return;

    var catalog = gallery.catalog;
    var strings = gallery.strings || {};
    var HASH_KEY = 'p';

    /* ------------------------------------------------------------------ */
    /* URL hash (#p=key:value,…)                                           */
    /* ------------------------------------------------------------------ */

    /** Raw values from the current hash, or null when it carries none. */
    function readHash() {
        var hash = window.location.hash.replace(/^#/, '');
        if (!hash) return null;
        var raw = null;
        hash.split('&').forEach(function (part) {
            var eq = part.indexOf('=');
            if (eq < 0 || part.slice(0, eq) !== HASH_KEY) return;
            var payload = part.slice(eq + 1);
            try { payload = decodeURIComponent(payload); } catch (error) { /* keep as is */ }
            raw = P.fromHash(payload);
        });
        return raw;
    }

    /** "#p=…" for the values, "" at the defaults. */
    function hashFor(defs, values) {
        var payload = P.toHash(defs, values);
        return payload ? '#' + HASH_KEY + '=' + payload : '';
    }

    /** True when the current hash is ours (or empty) and may be rewritten; an unrelated anchor is left alone. */
    function hashIsOurs() {
        var current = window.location.hash;
        return !current || current.indexOf('#' + HASH_KEY + '=') === 0;
    }

    function writeHash(defs, values) {
        var base = window.location.pathname + window.location.search;
        var hash = hashFor(defs, values);
        if (!hashIsOurs()) {
            // Keep an in-page anchor such as #motion-pop-in-code; only our
            // own #p=… is replaced or removed.
            if (!hash) return;
        } else if (!hash && !window.location.hash) {
            return;
        }
        window.history.replaceState(window.history.state, '', base + hash);
    }

    /* ------------------------------------------------------------------ */
    /* Panel                                                               */
    /* ------------------------------------------------------------------ */

    /** The sample's copy templates, printed next to its panel; null when absent or invalid. */
    function readTemplates(detail) {
        var el = detail.querySelector('[data-omx-item-templates]');
        if (!el) return null;
        try {
            var data = JSON.parse(el.textContent || '');
            if (data && typeof data.codeTemplate === 'string' && typeof data.promptTemplate === 'string') return data;
        } catch (error) { /* fall through */ }
        return null;
    }

    function announce(panel, message) {
        var live = panel.querySelector('[data-omx-copy-status]');
        if (live) live.textContent = message;
    }

    function bind(panel) {
        var slug = panel.dataset.slug || '';
        var data = catalog.items[slug];
        var defs = data && data.params;
        if (!defs || !defs.length) return;
        var detail = panel.closest('[data-motion-detail]') || panel.parentNode;
        var inputs = Array.prototype.slice.call(panel.querySelectorAll('[data-omx-param]'));
        var outputs = {};
        Array.prototype.forEach.call(panel.querySelectorAll('[data-omx-output]'), function (o) { outputs[o.dataset.omxOutput] = o; });
        var resetButton = panel.querySelector('[data-omx-reset]');
        var shareButton = panel.querySelector('[data-omx-share]');
        var modifiedLabels = detail.querySelectorAll('[data-omx-modified]');
        var promptPre = document.getElementById('motion-' + slug + '-prompt-text');
        var codePre = document.getElementById('motion-' + slug + '-code-text');
        var templates = readTemplates(detail);
        var frameId = 0;
        // Without the templates (missing or unparsable JSON) the panel still
        // drives the preview and the URL, but the copy targets keep their
        // server-rendered defaults and are not flagged as adjusted.
        if (!templates) modifiedLabels = [];
        var values = P.defaults(defs);

        function defOf(key) {
            for (var i = 0; i < defs.length; i++) if (defs[i].key === key) return defs[i];
            return null;
        }

        function readInputs() {
            var raw = {};
            inputs.forEach(function (input) { raw[input.dataset.omxParam] = input.value; });
            return P.sanitize(defs, raw);
        }

        function writeInputs() {
            inputs.forEach(function (input) {
                var def = defOf(input.dataset.omxParam);
                if (!def) return;
                var v = values[def.key];
                input.value = def.type === 'range' ? P.formatNumber(v) : String(v);
            });
        }

        /** The previews that show this sample from this panel: its detail and the expanded view. */
        function livePreviews() {
            var expand = gallery.expand;
            return gallery.previewsOf(slug).filter(function (preview) {
                return detail.contains(preview.el) || (expand && expand.open && expand.contains(preview.el));
            });
        }

        function render() {
            var modified = !P.isDefault(defs, values);
            defs.forEach(function (def) {
                var shown = P.display(def, values[def.key]);
                if (outputs[def.key]) outputs[def.key].textContent = shown;
            });
            inputs.forEach(function (input) {
                var def = defOf(input.dataset.omxParam);
                if (def && def.type === 'range') input.setAttribute('aria-valuetext', P.display(def, values[def.key]));
            });
            if (resetButton) resetButton.disabled = !modified;
            if (shareButton) shareButton.hidden = !modified;
            Array.prototype.forEach.call(modifiedLabels, function (el) { el.hidden = !modified; });
            detail.classList.toggle('is-modified', modified);
            livePreviews().forEach(function (preview) {
                preview.params = values;
                if (preview.ready) gallery.sendParams(preview);
                else if (!preview.mounted && preview.inView) gallery.mount(preview);
            });
            var blocks = P.blocks(defs, values);
            if (codePre && templates && templates.codeTemplate) {
                codePre.textContent = P.fillTemplate(templates.codeTemplate, {
                    '{{params_css}}': blocks.css ? blocks.css + '\n' : '',
                    '{{params_js}}': blocks.jsBlock ? blocks.jsBlock + '\n' : ''
                });
            }
            if (promptPre && templates && templates.promptTemplate) {
                promptPre.textContent = P.fillTemplate(templates.promptTemplate, { '{{params_text}}': P.text(defs, values) });
            }
            writeHash(defs, values);
        }

        function scheduleRender() {
            if (frameId) return;
            frameId = window.requestAnimationFrame(function () { frameId = 0; render(); });
        }

        inputs.forEach(function (input) {
            var eventName = input.tagName === 'SELECT' ? 'change' : 'input';
            input.addEventListener(eventName, function () {
                values = readInputs();
                scheduleRender();
            });
            input.addEventListener('change', function () {
                values = readInputs();
                writeInputs(); // snap the control to the sanitized value
                scheduleRender();
            });
        });

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                values = P.defaults(defs);
                writeInputs();
                render();
                announce(panel, strings.resetDone || 'Reset');
                if (inputs[0]) inputs[0].focus();
            });
        }

        /* Share: the sample's canonical URL plus the current values. On devices
         * with a share sheet offer it; otherwise (or when it fails) copy. */
        function shareUrl() {
            var base = (detail.dataset && detail.dataset.url) || (window.location.origin + window.location.pathname);
            return base.replace(/#.*$/, '') + hashFor(defs, values);
        }

        function copyShareUrl() {
            gallery.copyText(shareUrl()).then(function () {
                gallery.flash(shareButton, strings.linkCopied || strings.copied || 'Copied', true);
            }).catch(function () {
                gallery.flash(shareButton, strings.copyFailed || 'Copy failed', false);
            });
        }

        if (shareButton) {
            shareButton.addEventListener('click', function () {
                var url = shareUrl();
                if (navigator.share && (!navigator.canShare || navigator.canShare({ url: url }))) {
                    navigator.share({ url: url, title: document.title }).catch(function (error) {
                        if (!error || error.name !== 'AbortError') copyShareUrl(); // cancelled: nothing to do
                    });
                    return;
                }
                copyShareUrl();
            });
        }

        function applyHash() {
            var fromHash = readHash();
            if (!fromHash) return false;
            values = P.sanitize(defs, fromHash);
            writeInputs();
            return true;
        }

        // In-page navigation to a shared link (#p=…) while this panel is open.
        // Dialog panels are re-rendered on every open, so the listener
        // unregisters itself once its panel has left the document.
        function onHashChange() {
            if (!document.body.contains(panel)) {
                window.removeEventListener('hashchange', onHashChange);
                return;
            }
            if (applyHash()) render();
        }
        window.addEventListener('hashchange', onHashChange);

        applyHash();
        panel.omxBound = true;
        render();
    }

    function bindWithin(container) {
        Array.prototype.forEach.call(container.querySelectorAll('[data-motion-playground]'), function (panel) {
            if (!panel.omxBound) bind(panel);
        });
    }

    document.addEventListener('omx:motion:detail', function (event) {
        var container = event.detail && event.detail.container;
        if (container) bindWithin(container);
    });

    bindWithin(document);
}());
