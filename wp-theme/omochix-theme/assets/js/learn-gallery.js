/**
 * Learn v2 / Motion Gallery interactions (progressive enhancement).
 *
 * Everything on the page works without this file: the list is rendered
 * and filtered server-side, cards link to real detail URLs, and the
 * detail page prints a <noscript> preview. This script adds:
 *
 *  - Previews: mounts one sandboxed iframe per preview when it scrolls
 *    near the viewport, pauses it when it leaves, forwards the site theme,
 *    and drives per-preview and "pause all" controls. Viewers who prefer
 *    reduced motion only see motion after pressing play themselves.
 *  - Filtering in place (category chips, keyword, level, tech) with the
 *    state mirrored into the URL, so a filtered view can be shared.
 *  - Detail dialog: a card opens its detail fragment (?omx_partial=1) in a
 *    <dialog>, with the URL pushed so Back closes it and the link can be
 *    shared. Any failure falls back to normal navigation.
 *  - Expanded preview dialog and copy buttons (prompt / code).
 */
(function () {
    'use strict';

    var config = window.omochixLearnGallery || {};
    var strings = config.strings || {};
    var partialParam = config.partialParam || 'omx_partial';
    var docEl = document.documentElement;
    var catalog = { stage: { width: 320, height: 180 }, items: {} };
    var catalogEl = document.getElementById('omx-motion-catalog');
    if (catalogEl) {
        try { catalog = JSON.parse(catalogEl.textContent || '{}') || catalog; } catch (error) { /* keep the empty catalog: previews stay as posters */ }
        catalog.items = catalog.items || {};
        catalog.stage = catalog.stage || { width: 320, height: 180 };
    }
    var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    var supportsDialog = typeof window.HTMLDialogElement === 'function' && 'showModal' in window.HTMLDialogElement.prototype;
    var dialog = document.querySelector('[data-motion-dialog]');
    var dialogBody = dialog ? dialog.querySelector('[data-motion-dialog-body]') : null;
    var expand = document.querySelector('[data-motion-expand]');
    var expandStage = expand ? expand.querySelector('[data-motion-expand-stage]') : null;
    var expandCaption = expand ? expand.querySelector('[data-motion-expand-caption]') : null;

    docEl.classList.add('omx-motion-js');

    /* ------------------------------------------------------------------ */
    /* Previews                                                            */
    /* ------------------------------------------------------------------ */

    var previews = [];
    var allPaused = false;
    try { allPaused = window.sessionStorage.getItem('omx-motion-pause-all') === '1'; } catch (error) { /* storage unavailable: default to playing */ }

    function currentTheme() {
        return docEl.dataset.theme === 'dark' ? 'dark' : 'light';
    }

    function send(preview, message) {
        if (preview.iframe && preview.iframe.contentWindow) {
            preview.iframe.contentWindow.postMessage(message, '*');
        }
    }

    /** The topmost open modal, if any: previews outside it stay paused. */
    function topModal() {
        if (expand && expand.open) return expand;
        if (dialog && dialog.open) return dialog;
        return null;
    }

    function desired(preview) {
        if (!preview.inView) return false;
        var modal = topModal();
        if (modal && !modal.contains(preview.el)) return false;
        if (preview.user === 'play') return true;
        if (preview.user === 'pause') return false;
        return !allPaused && !reduceMotion;
    }

    function updateButton(preview) {
        preview.el.classList.toggle('is-playing', preview.playing);
        if (preview.toggle) {
            preview.toggle.setAttribute('aria-label', (preview.playing ? strings.pause : strings.play) + ': ' + preview.label);
        }
    }

    function apply(preview) {
        var want = desired(preview);
        if (preview.ready && want !== preview.playing) {
            send(preview, { type: want ? 'omx:play' : 'omx:pause' });
        }
        preview.playing = preview.ready ? want : false;
        updateButton(preview);
    }

    function refreshAll() {
        previews.forEach(apply);
    }

    /**
     * Assemble a sample's preview document from the shared template and
     * runtime (both sent once in the catalog JSON) plus the sample's own
     * fragment. Mirrors omochix_learn_gallery_preview_document() in PHP.
     */
    /* ------------------------------------------------------------------ */
    /* Playground parameters (pure helpers, mirrored in inc/learn-gallery.php) */
    /* ------------------------------------------------------------------ */

    var COLOR_RE = /^#[0-9a-f]{6}$/i;

    /** Number formatting shared with PHP: up to 4 decimals, no trailing zeros. */
    function formatNumber(n) {
        var s = (Math.round(Number(n) * 10000) / 10000).toFixed(4).replace(/\.?0+$/, '');
        return s === '' || s === '-0' ? '0' : s;
    }

    function paramDefaults(defs) {
        var values = {};
        (defs || []).forEach(function (def) { values[def.key] = def['default']; });
        return values;
    }

    /** Coerce raw values into valid ones: clamp/snap ranges, hex colours, allowed select values. */
    function sanitizeParams(defs, raw) {
        var values = {};
        raw = raw || {};
        (defs || []).forEach(function (def) {
            var v = Object.prototype.hasOwnProperty.call(raw, def.key) ? raw[def.key] : null;
            if (def.type === 'range') {
                var n = typeof v === 'string' && v.trim() !== '' ? Number(v) : v;
                if (typeof n !== 'number' || !isFinite(n)) n = def['default'];
                n = Math.max(def.min, Math.min(def.max, n));
                n = def.min + Math.round((n - def.min) / def.step) * def.step;
                n = Math.max(def.min, Math.min(def.max, n));
                values[def.key] = Number(formatNumber(n));
            } else if (def.type === 'color') {
                var c = typeof v === 'string' ? v.trim().toLowerCase() : '';
                if (/^[0-9a-f]{6}$/.test(c)) c = '#' + c;
                values[def.key] = COLOR_RE.test(c) ? c : def['default'];
            } else {
                var allowed = (def.options || []).map(function (o) { return o.value; });
                values[def.key] = typeof v === 'string' && allowed.indexOf(v) > -1 ? v : def['default'];
            }
        });
        return values;
    }

    function paramDisplay(def, v) {
        if (def.type === 'range') return formatNumber(v) + (def.unit || '');
        if (def.type === 'select') {
            for (var i = 0; i < (def.options || []).length; i++) if (def.options[i].value === v) return def.options[i].label;
        }
        return String(v);
    }

    /** {cssVars:{'--p-x':'400ms'}, js:{target:12800}} plus the serialized blocks the templates take. */
    function paramBlocks(defs, values) {
        var cssVars = {};
        var cssList = [];
        var js = {};
        var hasJs = false;
        (defs || []).forEach(function (def) {
            var v = Object.prototype.hasOwnProperty.call(values, def.key) ? values[def.key] : def['default'];
            var cssValue = '';
            var jsValue = v;
            if (def.type === 'select') {
                (def.options || []).forEach(function (o) { if (o.value === v) cssValue = o.css || ''; });
            } else if (def.type === 'color') {
                cssValue = v;
            } else {
                cssValue = formatNumber(v) + (def.unit || '');
                jsValue = Number(formatNumber(v));
            }
            if (def.target === 'js') { js[def.key] = jsValue; hasJs = true; }
            else if (cssValue !== '') { cssVars[def.css_var] = cssValue; cssList.push(def.css_var + ':' + cssValue); }
        });
        return {
            cssVars: cssVars,
            js: js,
            css: cssList.length ? ':root{' + cssList.join(';') + '}' : '',
            jsBlock: hasJs ? 'window.omxParams=' + JSON.stringify(js) + ';' : ''
        };
    }

    function paramsText(defs, values) {
        if (!defs || !defs.length) return '';
        var lines = ['調整した値:'];
        defs.forEach(function (def) {
            var v = Object.prototype.hasOwnProperty.call(values, def.key) ? values[def.key] : def['default'];
            var display = paramDisplay(def, v);
            var line = def.prompt ? def.prompt.replace('{value}', display) : def.label + ': ' + display;
            var same = def.type === 'range' ? Number(v) === Number(def['default']) : v === def['default'];
            lines.push('- ' + line + (same ? '（既定）' : ''));
        });
        return lines.join('\n') + '\n';
    }

    function isDefault(defs, values) {
        return (defs || []).every(function (def) {
            var v = values[def.key];
            return def.type === 'range' ? Number(v) === Number(def['default']) : v === def['default'];
        });
    }

    function fillTemplate(template, map) {
        return String(template).replace(/\{\{[a-z_]+\}\}/g, function (token) {
            return Object.prototype.hasOwnProperty.call(map, token) ? map[token] : token;
        });
    }

    /**
     * Assemble a sample's preview document from the shared template and
     * runtime (both sent once in the catalog JSON) plus the sample's own
     * fragment and parameter values. Mirrors
     * omochix_learn_gallery_preview_document() in PHP.
     */
    function buildDoc(data, params) {
        if (data.doc) return data.doc;
        if (!catalog.template || !catalog.runtime) return '';
        var blocks = paramBlocks(data.params, params || paramDefaults(data.params));
        return fillTemplate(catalog.template, {
            '{{stage_w}}': String(catalog.stage.width),
            '{{stage_h}}': String(catalog.stage.height),
            '{{title}}': data.title || '',
            '{{runtime_css}}': catalog.runtime.css || '',
            '{{params_css}}': blocks.css,
            '{{css}}': data.css || '',
            '{{html}}': data.html || '',
            '{{runtime_js}}': catalog.runtime.js || '',
            '{{params_js}}': blocks.jsBlock ? '<script>' + blocks.jsBlock + '</script>' : '',
            '{{js}}': data.js ? '<script>' + data.js + '</script>' : ''
        });
    }

    function sendParams(preview) {
        var data = catalog.items[preview.slug];
        if (!data || !data.params || !data.params.length || !preview.ready) return;
        var blocks = paramBlocks(data.params, preview.params || paramDefaults(data.params));
        send(preview, { type: 'omx:params', cssVars: blocks.cssVars, js: blocks.js });
    }

    function mount(preview) {
        if (preview.mounted || !preview.frame) return;
        var data = catalog.items[preview.slug];
        var doc = data ? buildDoc(data, preview.params) : '';
        if (!doc) return;
        var iframe = document.createElement('iframe');
        iframe.className = 'motion-preview__iframe';
        iframe.setAttribute('sandbox', 'allow-scripts');
        iframe.setAttribute('title', data.label || preview.label);
        iframe.setAttribute('loading', 'lazy');
        iframe.tabIndex = -1;
        iframe.srcdoc = doc;
        preview.frame.appendChild(iframe);
        preview.iframe = iframe;
        preview.mounted = true;
        preview.el.classList.add('is-mounted');
    }

    var observer = 'IntersectionObserver' in window ? new window.IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var preview = entry.target.omxPreview;
            if (!preview) return;
            preview.inView = entry.isIntersecting;
            if (preview.inView) mount(preview);
            apply(preview);
        });
    }, { rootMargin: '200px 0px' }) : null;

    function register(el) {
        var preview = {
            el: el,
            slug: el.dataset.slug || '',
            frame: el.querySelector('[data-motion-frame]'),
            toggle: el.querySelector('[data-motion-toggle]'),
            controls: el.querySelector('[data-motion-controls]'),
            label: '',
            user: 'auto',
            inView: false,
            mounted: false,
            ready: false,
            playing: false,
            iframe: null
        };
        preview.label = (preview.frame && preview.frame.dataset.label) || preview.slug;
        el.omxPreview = preview;
        if (preview.controls && catalog.items[preview.slug]) preview.controls.hidden = false;
        if (preview.toggle) {
            preview.toggle.addEventListener('click', function () {
                preview.user = preview.playing ? 'pause' : 'play';
                if (!preview.mounted) { preview.inView = true; mount(preview); }
                apply(preview);
            });
        }
        var expandButton = el.querySelector('[data-motion-expand-btn]');
        if (expandButton) {
            expandButton.addEventListener('click', function () { openExpand(preview, expandButton); });
        }
        previews.push(preview);
        if (observer) {
            observer.observe(el);
        } else {
            preview.inView = true;
            mount(preview);
            apply(preview);
        }
        updateButton(preview);
        return preview;
    }

    function registerWithin(container) {
        Array.prototype.forEach.call(container.querySelectorAll('[data-motion-preview]'), function (el) {
            if (el.omxPreview) return;
            register(el);
        });
    }

    function unregisterWithin(container) {
        previews = previews.filter(function (preview) {
            if (!container.contains(preview.el)) return true;
            if (observer) observer.unobserve(preview.el);
            preview.el.omxPreview = null;
            return false;
        });
    }

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data !== 'object' || data.type !== 'omx:ready') return;
        for (var i = 0; i < previews.length; i++) {
            var preview = previews[i];
            if (preview.iframe && preview.iframe.contentWindow === event.source) {
                preview.ready = true;
                preview.playing = !!data.playing;
                send(preview, { type: 'omx:theme', theme: currentTheme() });
                if (preview.params) sendParams(preview);
                apply(preview);
                return;
            }
        }
    });

    if ('MutationObserver' in window) {
        new window.MutationObserver(function () {
            var theme = currentTheme();
            previews.forEach(function (preview) {
                if (preview.ready) send(preview, { type: 'omx:theme', theme: theme });
            });
        }).observe(docEl, { attributes: true, attributeFilter: ['data-theme'] });
    }

    document.addEventListener('visibilitychange', function () {
        var hidden = document.visibilityState === 'hidden';
        previews.forEach(function (preview) {
            if (preview.ready && preview.playing) send(preview, { type: hidden ? 'omx:pause' : 'omx:play' });
        });
    });

    var pauseAllButton = document.querySelector('[data-motion-pause-all]');
    function renderPauseAll() {
        if (!pauseAllButton) return;
        pauseAllButton.setAttribute('aria-pressed', String(allPaused));
        var label = pauseAllButton.querySelector('span');
        if (label) label.textContent = allPaused ? strings.playAll : strings.pauseAll;
    }
    if (pauseAllButton) {
        pauseAllButton.hidden = false;
        renderPauseAll();
        pauseAllButton.addEventListener('click', function () {
            allPaused = !allPaused;
            try { window.sessionStorage.setItem('omx-motion-pause-all', allPaused ? '1' : '0'); } catch (error) { /* ignore */ }
            previews.forEach(function (preview) { preview.user = 'auto'; });
            renderPauseAll();
            refreshAll();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Filtering                                                           */
    /* ------------------------------------------------------------------ */

    var form = document.querySelector('[data-motion-filter]');
    if (form) {
        var catInput = form.querySelector('[data-motion-cat-input]');
        var qInput = form.querySelector('[data-motion-q]');
        var levelSelect = form.querySelector('[data-motion-level]');
        var techSelect = form.querySelector('[data-motion-tech]');
        var countEl = form.querySelector('[data-motion-count]');
        var chips = Array.prototype.slice.call(form.querySelectorAll('[data-motion-cat]'));
        var cards = Array.prototype.slice.call(document.querySelectorAll('[data-motion-card]'));
        var sections = Array.prototype.slice.call(document.querySelectorAll('[data-motion-section]'));
        var emptyEl = document.querySelector('[data-motion-empty]');
        var submit = form.querySelector('[data-motion-nojs]');
        var debounce = 0;
        if (submit) submit.hidden = true;

        var readFilters = function () {
            return {
                cat: catInput ? catInput.value : '',
                level: levelSelect ? levelSelect.value : '',
                tech: techSelect ? techSelect.value : '',
                q: qInput ? qInput.value.trim() : ''
            };
        };

        var matches = function (card, filters) {
            if (filters.cat && card.dataset.cat !== filters.cat) return false;
            if (filters.level && card.dataset.level !== filters.level) return false;
            if (filters.tech && (' ' + card.dataset.tech + ' ').indexOf(' ' + filters.tech + ' ') < 0) return false;
            if (filters.q) {
                var haystack = card.dataset.search || '';
                var terms = filters.q.toLowerCase().split(/\s+/);
                for (var i = 0; i < terms.length; i++) {
                    if (terms[i] && haystack.indexOf(terms[i]) < 0) return false;
                }
            }
            return true;
        };

        var applyFilters = function () {
            var filters = readFilters();
            var count = 0;
            cards.forEach(function (card) {
                var visible = matches(card, filters);
                card.hidden = !visible;
                if (visible) count++;
            });
            sections.forEach(function (section) {
                section.hidden = !section.querySelector('[data-motion-card]:not([hidden])');
            });
            if (emptyEl) emptyEl.hidden = count > 0;
            if (countEl) countEl.textContent = (strings.count || '%s').replace('%s', count.toLocaleString('ja-JP'));
            chips.forEach(function (chip) {
                var active = chip.dataset.motionCat === filters.cat;
                chip.classList.toggle('is-active', active);
                if (active) chip.setAttribute('aria-current', 'true'); else chip.removeAttribute('aria-current');
            });
            var params = new window.URLSearchParams();
            if (filters.cat) params.set('cat', filters.cat);
            if (filters.level) params.set('level', filters.level);
            if (filters.tech) params.set('tech', filters.tech);
            if (filters.q) params.set('q', filters.q);
            var query = params.toString();
            var base = config.listUrl || (window.location.origin + window.location.pathname);
            if (!(dialog && dialog.open)) {
                window.history.replaceState(window.history.state, '', base + (query ? '?' + query : ''));
            }
        };

        chips.forEach(function (chip) {
            chip.addEventListener('click', function (event) {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                event.preventDefault();
                if (catInput) catInput.value = chip.dataset.motionCat || '';
                applyFilters();
            });
        });
        if (qInput) {
            qInput.addEventListener('input', function () {
                window.clearTimeout(debounce);
                debounce = window.setTimeout(applyFilters, 160);
            });
        }
        [levelSelect, techSelect].forEach(function (select) {
            if (select) select.addEventListener('change', applyFilters);
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            applyFilters();
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-motion-reset]'), function (reset) {
            reset.addEventListener('click', function (event) {
                event.preventDefault();
                if (catInput) catInput.value = '';
                if (qInput) qInput.value = '';
                if (levelSelect) levelSelect.value = '';
                if (techSelect) techSelect.value = '';
                applyFilters();
                if (qInput) qInput.focus();
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Detail dialog                                                       */
    /* ------------------------------------------------------------------ */

    var dialogTrigger = null;
    var dialogPushed = false;
    var closingFromHistory = false;

    function openDetail(url, trigger, push) {
        if (!dialog || !dialogBody) return;
        var separator = url.indexOf('?') > -1 ? '&' : '?';
        dialogBody.setAttribute('aria-busy', 'true');
        window.fetch(url + separator + partialParam + '=1', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) throw new Error(String(response.status));
                return response.text();
            })
            .then(function (html) {
                unregisterWithin(dialogBody);
                dialogBody.innerHTML = html;
                dialogBody.removeAttribute('aria-busy');
                var wasOpen = dialog.open;
                if (!wasOpen) {
                    dialogTrigger = trigger || document.activeElement;
                    dialog.showModal();
                }
                dialog.scrollTop = 0;
                registerWithin(dialogBody);
                bindPlaygroundWithin(dialogBody);
                refreshAll();
                if (push) {
                    // One history entry per dialog session: moving between
                    // samples inside the open dialog replaces it, so Back
                    // (or Escape) always returns to the list in one step.
                    var state = { omxDialog: true, url: url };
                    if (wasOpen && dialogPushed) window.history.replaceState(state, '', url);
                    else window.history.pushState(state, '', url);
                    dialogPushed = true;
                }
                var heading = dialogBody.querySelector('.motion-detail__title');
                if (heading) { heading.tabIndex = -1; heading.focus(); }
            })
            .catch(function () {
                dialogBody.removeAttribute('aria-busy');
                window.location.href = url;
            });
    }

    if (dialog && dialogBody && supportsDialog) {
        document.addEventListener('click', function (event) {
            var link = event.target.closest('[data-motion-open]');
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            openDetail(link.href, link, true);
        });
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog || event.target.closest('[data-motion-dialog-close]')) dialog.close();
        });
        dialog.addEventListener('close', function () {
            unregisterWithin(dialogBody);
            dialogBody.innerHTML = '';
            if (dialogPushed && !closingFromHistory) {
                dialogPushed = false;
                window.history.back();
            }
            closingFromHistory = false;
            dialogPushed = false;
            refreshAll();
            if (dialogTrigger && typeof dialogTrigger.focus === 'function') dialogTrigger.focus();
            dialogTrigger = null;
        });
        window.addEventListener('popstate', function (event) {
            var state = event.state;
            if (dialog.open && !(state && state.omxDialog)) {
                closingFromHistory = true;
                dialog.close();
            } else if (state && state.omxDialog && state.url) {
                openDetail(state.url, null, false);
                dialogPushed = true;
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Expanded preview                                                    */
    /* ------------------------------------------------------------------ */

    var expandTrigger = null;

    function openExpand(preview, trigger) {
        if (!expand || !expandStage || !supportsDialog) return;
        var data = catalog.items[preview.slug];
        if (!data) return;
        unregisterWithin(expandStage);
        expandStage.innerHTML = '';
        var clone = preview.el.cloneNode(true);
        clone.omxPreview = null;
        clone.className = 'motion-preview motion-preview--expand';
        var frame = clone.querySelector('[data-motion-frame]');
        if (frame) frame.innerHTML = '';
        var expandButton = clone.querySelector('[data-motion-expand-btn]');
        if (expandButton) expandButton.remove();
        expandStage.style.setProperty('--stage-w', String(catalog.stage.width));
        expandStage.style.setProperty('--stage-h', String(catalog.stage.height));
        expandStage.appendChild(clone);
        if (expandCaption) expandCaption.textContent = '#' + data.id + ' ' + (data.name && data.name.ja ? data.name.ja : preview.slug);
        expandTrigger = trigger;
        expand.showModal();
        var expanded = register(clone);
        expanded.user = preview.user;
        expanded.params = preview.params; // keep the viewer's adjustments in the big view
        refreshAll();
        var toggle = clone.querySelector('[data-motion-toggle]');
        if (toggle) toggle.focus();
    }

    if (expand && supportsDialog) {
        expand.addEventListener('click', function (event) {
            if (event.target === expand || event.target.closest('[data-motion-expand-close]')) expand.close();
        });
        expand.addEventListener('close', function () {
            unregisterWithin(expandStage);
            expandStage.innerHTML = '';
            refreshAll();
            if (expandTrigger && typeof expandTrigger.focus === 'function') expandTrigger.focus();
            expandTrigger = null;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Copy (prompt / code)                                                */
    /* ------------------------------------------------------------------ */

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            // The async API can be refused (permissions policy, embedded
            // browsers); the selection-based copy still works on a click.
            return navigator.clipboard.writeText(text).catch(function () { return legacyCopy(text); });
        }
        return legacyCopy(text);
    }

    function legacyCopy(text) {
        return new Promise(function (resolve, reject) {
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
            copied ? resolve() : reject();
        });
    }

    function flash(button, label, ok) {
        if (!button.dataset.omxLabel) button.dataset.omxLabel = button.textContent;
        button.textContent = label;
        button.classList.toggle('is-done', ok);
        var scope = button.closest('.motion-panel') || button.closest('[data-motion-detail]');
        var live = scope ? scope.querySelector('[data-omx-copy-status]') : null;
        if (live) live.textContent = label;
        window.clearTimeout(button.omxTimer);
        button.omxTimer = window.setTimeout(function () {
            button.textContent = button.dataset.omxLabel;
            button.classList.remove('is-done');
        }, 2000);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-omx-copy]');
        if (!button) return;
        var target = document.getElementById(button.dataset.omxCopyTarget || '');
        if (!target) return;
        copyText(target.textContent).then(function () {
            flash(button, strings.copied || 'Copied', true);
        }).catch(function () {
            flash(button, strings.copyFailed || 'Copy failed', false);
        });
    });

    /* ------------------------------------------------------------------ */
    /* Playground panel                                                    */
    /* ------------------------------------------------------------------ */

    var HASH_KEY = 'p';

    function readHashParams() {
        var hash = window.location.hash.replace(/^#/, '');
        if (!hash) return null;
        var raw = null;
        hash.split('&').forEach(function (part) {
            var eq = part.indexOf('=');
            if (eq < 0 || part.slice(0, eq) !== HASH_KEY) return;
            raw = {};
            decodeURIComponent(part.slice(eq + 1)).split(',').slice(0, 20).forEach(function (pair) {
                var colon = pair.indexOf(':');
                if (colon < 1) return;
                var key = pair.slice(0, colon);
                var value = pair.slice(colon + 1, colon + 65);
                if (/^[a-z][a-z0-9_]{0,23}$/.test(key)) raw[key] = value;
            });
        });
        return raw;
    }

    function writeHashParams(defs, values) {
        var base = window.location.pathname + window.location.search;
        if (isDefault(defs, values)) {
            if (window.location.hash) window.history.replaceState(window.history.state, '', base);
            return;
        }
        var pairs = defs.map(function (def) {
            var v = values[def.key];
            return def.key + ':' + (def.type === 'color' ? String(v).replace('#', '') : def.type === 'range' ? formatNumber(v) : v);
        });
        window.history.replaceState(window.history.state, '', base + '#' + HASH_KEY + '=' + pairs.join(','));
    }

    function bindPlayground(panel) {
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
        var promptPre = document.getElementById('motion-' + slug + '-prompt-text');
        var codePre = document.getElementById('motion-' + slug + '-code-text');
        var frameId = 0;
        var values = paramDefaults(defs);

        function readInputs() {
            var raw = {};
            inputs.forEach(function (input) { raw[input.dataset.omxParam] = input.value; });
            return sanitizeParams(defs, raw);
        }

        function writeInputs() {
            inputs.forEach(function (input) {
                var def = null;
                defs.forEach(function (d) { if (d.key === input.dataset.omxParam) def = d; });
                if (!def) return;
                var v = values[def.key];
                input.value = def.type === 'range' ? formatNumber(v) : String(v);
            });
        }

        function previewsOfSample() {
            return previews.filter(function (p) {
                return p.slug === slug && (detail.contains(p.el) || (expand && expand.open && expand.contains(p.el)));
            });
        }

        function render() {
            var modified = !isDefault(defs, values);
            defs.forEach(function (def) {
                if (outputs[def.key]) outputs[def.key].textContent = paramDisplay(def, values[def.key]);
            });
            if (resetButton) resetButton.disabled = !modified;
            if (shareButton) shareButton.hidden = !modified;
            detail.classList.toggle('is-modified', modified);
            previewsOfSample().forEach(function (preview) {
                preview.params = values;
                if (preview.ready) sendParams(preview);
                else if (!preview.mounted && preview.inView) mount(preview);
            });
            var blocks = paramBlocks(defs, values);
            if (codePre && data.codeTemplate) {
                codePre.textContent = fillTemplate(data.codeTemplate, {
                    '{{params_css}}': blocks.css ? blocks.css + '\n' : '',
                    '{{params_js}}': blocks.jsBlock ? blocks.jsBlock + '\n' : ''
                });
            }
            if (promptPre && data.promptTemplate) {
                promptPre.textContent = fillTemplate(data.promptTemplate, { '{{params_text}}': paramsText(defs, values) });
            }
            writeHashParams(defs, values);
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
                values = paramDefaults(defs);
                writeInputs();
                render();
                if (inputs[0]) inputs[0].focus();
            });
        }
        if (shareButton) {
            shareButton.addEventListener('click', function () {
                copyText(window.location.href).then(function () {
                    flash(shareButton, strings.copied || 'Copied', true);
                }).catch(function () {
                    flash(shareButton, strings.copyFailed || 'Copy failed', false);
                });
            });
        }

        function applyHash() {
            var fromHash = readHashParams();
            if (!fromHash) return false;
            values = sanitizeParams(defs, fromHash);
            writeInputs();
            return true;
        }

        // In-page navigation to a shared link (#p=…) while this panel is open.
        window.addEventListener('hashchange', function () {
            if (!document.body.contains(panel)) return;
            if (applyHash()) render();
        });

        applyHash();
        panel.omxBound = true;
        render();
    }

    function bindPlaygroundWithin(container) {
        Array.prototype.forEach.call(container.querySelectorAll('[data-motion-playground]'), function (panel) {
            if (!panel.omxBound) bindPlayground(panel);
        });
    }

    /* ------------------------------------------------------------------ */

    registerWithin(document);
    bindPlaygroundWithin(document);
}());
