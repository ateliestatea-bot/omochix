/**
 * Learn v2 / Motion Composer page (progressive enhancement).
 *
 * Up to three samples are picked from the picker dialog, ordered, removed
 * and adjusted. Each slot shows the sample's live preview (cloned from the
 * picker and registered with learn-gallery.js) and, on demand, the sample's
 * adjustment panel (fetched as a fragment and bound by learn-playground.js
 * in host mode, which reports changes instead of touching the URL). A
 * combined preview stacks the slots in one sandboxed document and is
 * rebuilt when the order or a value changes; the implementation brief
 * (Markdown, see learn-composer-core.js buildBrief) follows the same
 * changes and can be copied, saved or exchanged as JSON. The composition is kept in
 * the URL hash (#c=…, see learn-composer-core.js) so it can be shared, and
 * the last state is also saved as a localStorage draft for the next visit.
 */
(function () {
    'use strict';

    var gallery = window.omochixMotionGallery;
    var P = window.omochixMotionParams;
    var core = window.omochixMotionComposerCore;
    var config = window.omochixLearnComposer || {};
    var strings = config.strings || {};
    var root = document.querySelector('[data-composer]');
    if (!gallery || !P || !core || !root) return;

    var items = gallery.catalog.items;
    var HASH_KEY = config.hashKey || 'c';
    var DRAFT_KEY = 'omx-composer-draft';
    var PARTIAL = config.partialParam || 'omx_partial';
    var PANEL = config.panelParam || 'omx_panel';
    var slotEls = Array.prototype.slice.call(root.querySelectorAll('[data-composer-slot]'));
    var MAX = Math.min(core.MAX_SLOTS, slotEls.length);
    var picker = root.querySelector('[data-composer-picker]');
    var pickerItems = picker ? Array.prototype.slice.call(picker.querySelectorAll('[data-composer-item]')) : [];
    var search = picker ? picker.querySelector('[data-composer-search]') : null;
    var pickerNone = picker ? picker.querySelector('[data-composer-picker-none]') : null;
    var countEl = root.querySelector('[data-composer-count]');
    var status = root.querySelector('[data-composer-status]');
    var shareButton = root.querySelector('[data-composer-share]');
    var clearButton = root.querySelector('[data-composer-clear]');
    var combined = root.querySelector('[data-composer-combined]');
    var combinedPreview = combined ? combined.querySelector('[data-motion-preview]') : null;
    var brief = root.querySelector('[data-composer-brief]');
    var briefText = brief ? brief.querySelector('[data-composer-brief-text]') : null;
    var jsonDialog = brief ? brief.querySelector('[data-composer-json-dialog]') : null;
    var briefData = null;
    try { briefData = JSON.parse((document.getElementById('omx-composer-data') || {}).textContent || 'null'); } catch (error) { briefData = null; }
    var briefTimer = 0;
    var openNotes = {};       // slug -> true while the notes form is expanded
    var supportsDialog = !!(picker && typeof window.HTMLDialogElement === 'function' && 'showModal' in window.HTMLDialogElement.prototype);
    var composition = { v: core.VERSION, slots: [] };
    var pickerTrigger = null;
    var panelCache = {};      // slug -> fragment HTML (fetched once)
    var openPanels = {};      // slug -> true while the adjustment panel is expanded
    var combinedTimer = 0;

    root.classList.add('is-ready');

    /* ------------------------------------------------------------------ */
    /* State: hash and draft                                               */
    /* ------------------------------------------------------------------ */

    function readHash() {
        var hash = window.location.hash.replace(/^#/, '');
        if (!hash) return null;
        var payload = null;
        hash.split('&').forEach(function (part) {
            var eq = part.indexOf('=');
            if (eq > 0 && part.slice(0, eq) === HASH_KEY) payload = part.slice(eq + 1);
        });
        if (payload === null) return null;
        try { payload = decodeURIComponent(payload); } catch (error) { /* keep */ }
        return core.sanitize(items, P, core.parseHash(payload));
    }

    function writeHash() {
        var base = window.location.pathname + window.location.search;
        var payload = core.toHash(items, P, composition);
        var current = window.location.hash;
        var ours = !current || current.indexOf('#' + HASH_KEY + '=') === 0;
        if (!payload) {
            if (ours && current) window.history.replaceState(window.history.state, '', base);
            return;
        }
        window.history.replaceState(window.history.state, '', base + '#' + HASH_KEY + '=' + payload);
    }

    function saveDraft() {
        try {
            if (core.isEmpty(composition)) window.localStorage.removeItem(DRAFT_KEY);
            else window.localStorage.setItem(DRAFT_KEY, JSON.stringify(composition));
        } catch (error) { /* storage unavailable: the URL still carries the state */ }
    }

    function readDraft() {
        try {
            var raw = window.localStorage.getItem(DRAFT_KEY);
            if (!raw) return null;
            var comp = core.sanitize(items, P, JSON.parse(raw));
            return core.isEmpty(comp) ? null : comp;
        } catch (error) { return null; }
    }

    function announce(message) {
        if (status) status.textContent = message;
    }

    function format(template) {
        var args = Array.prototype.slice.call(arguments, 1);
        var i = 0;
        return String(template || '').replace(/%[sd]/g, function () { return args[i++]; });
    }

    function galleryUrl() {
        return ((window.omochixLearnGallery && window.omochixLearnGallery.listUrl) || '/learn/motion/').replace(/\/?$/, '/');
    }

    /* ------------------------------------------------------------------ */
    /* Slots                                                               */
    /* ------------------------------------------------------------------ */

    function previewSource(slug) {
        return picker ? picker.querySelector('[data-composer-item][data-slug="' + slug + '"] [data-motion-preview]') : null;
    }

    function slotPreview(el, slug) {
        var host = el.querySelector('[data-composer-preview]');
        return gallery.previewsOf(slug).filter(function (p) { return host.contains(p.el); })[0] || null;
    }

    function fillSlot(el, slot, index) {
        var item = items[slot.slug];
        var body = el.querySelector('[data-composer-slot-body]');
        var empty = el.querySelector('.motion-composer__slot-empty');
        var previewHost = el.querySelector('[data-composer-preview]');
        gallery.unregisterWithin(previewHost);
        previewHost.innerHTML = '';
        var source = previewSource(slot.slug);
        if (source) {
            var clone = source.cloneNode(true);
            clone.omxPreview = null;
            clone.className = 'motion-preview motion-preview--detail';
            var frame = clone.querySelector('[data-motion-frame]');
            if (frame) frame.innerHTML = '';
            var controls = clone.querySelector('[data-motion-controls]');
            if (controls) controls.hidden = true;
            previewHost.appendChild(clone);
            gallery.registerWithin(previewHost);
            var preview = slotPreview(el, slot.slug);
            if (preview && Object.keys(slot.params).length) {
                preview.params = P.sanitize(item.params, slot.params);
                if (preview.ready) gallery.sendParams(preview);
            }
        }
        el.querySelector('[data-composer-name]').textContent = item.name && item.name.ja ? item.name.ja : slot.slug;
        el.querySelector('[data-composer-en]').textContent = item.name && item.name.en ? item.name.en : '';
        el.querySelector('[data-composer-summary]').textContent = item.summary || '';
        var link = el.querySelector('[data-composer-link]');
        if (link) link.href = galleryUrl() + slot.slug + '/';
        el.dataset.url = galleryUrl() + slot.slug + '/';
        el.dataset.slug = slot.slug;
        el.classList.remove('is-empty');
        el.classList.add('is-filled');
        empty.hidden = true;
        body.hidden = false;
        var adjust = el.querySelector('[data-composer-adjust]');
        var panelHost = el.querySelector('[data-composer-panel]');
        panelHost.innerHTML = '';
        panelHost.hidden = true;
        adjust.hidden = !(item.params && item.params.length);
        adjust.setAttribute('aria-expanded', 'false');
        adjust.textContent = strings.adjust || '調整する';
        if (openPanels[slot.slug]) openPanel(el, slot);
        syncNotes(el, slot);
        updateSlotChrome(el, slot, index);
    }

    /* ---- notes (target / intent): preset select + free text ---- */

    function syncNotes(el, slot) {
        var toggle = el.querySelector('[data-composer-notes-toggle]');
        var form = el.querySelector('[data-composer-notes]');
        if (!form) return;
        var open = !!openNotes[slot.slug];
        form.hidden = !open;
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.textContent = open ? (strings.notesClose || '用途を閉じる') : (strings.notes || '用途');
        }
        Array.prototype.forEach.call(form.querySelectorAll('[data-composer-note]'), function (field) {
            var kind = field.dataset.composerNote;
            var select = field.querySelector('[data-composer-note-select]');
            var input = field.querySelector('[data-composer-note-input]');
            var value = slot[kind] || '';
            var presets = (config.presets && config.presets[kind]) || {};
            var isPreset = value === '' || Object.prototype.hasOwnProperty.call(presets, value);
            select.value = isPreset ? value : '__custom';
            if (select.value !== (isPreset ? value : '__custom')) select.value = '';
            input.hidden = isPreset;
            input.value = isPreset ? '' : value;
        });
    }

    function readNote(field) {
        var select = field.querySelector('[data-composer-note-select]');
        var input = field.querySelector('[data-composer-note-input]');
        if (select.value === '__custom') { input.hidden = false; return core.sanitizeNote(input.value); }
        input.hidden = true;
        return core.sanitizeNote(select.value);
    }

    root.addEventListener('change', function (event) {
        var field = event.target.closest && event.target.closest('[data-composer-note]');
        if (field) {
            var el = field.closest('[data-composer-slot]');
            var slot = composition.slots[slotEls.indexOf(el)];
            if (!slot) return;
            slot[field.dataset.composerNote] = readNote(field);
            if (field.querySelector('[data-composer-note-select]').value === '__custom' && event.target.matches('select')) field.querySelector('[data-composer-note-input]').focus();
            writeHash();
            saveDraft();
            scheduleBrief();
            return;
        }
        if (event.target.matches && event.target.matches('[data-composer-assistant]')) renderBrief();
    });
    root.addEventListener('input', function (event) {
        var input = event.target.closest && event.target.closest('[data-composer-note-input]');
        if (!input) return;
        var field = input.closest('[data-composer-note]');
        var el = field.closest('[data-composer-slot]');
        var slot = composition.slots[slotEls.indexOf(el)];
        if (!slot) return;
        slot[field.dataset.composerNote] = core.sanitizeNote(input.value);
        writeHash();
        saveDraft();
        scheduleBrief();
    });

    function updateSlotChrome(el, slot, index) {
        el.querySelector('[data-composer-order]').textContent = String(index + 1);
        el.querySelector('[data-composer-up]').disabled = index === 0;
        el.querySelector('[data-composer-down]').disabled = index === composition.slots.length - 1;
        var badge = el.querySelector('[data-composer-adjusted]');
        var n = Object.keys(slot.params || {}).length;
        if (badge) {
            badge.hidden = n === 0;
            badge.textContent = format(strings.adjusted || '%d', n);
        }
    }

    function emptySlot(el, index) {
        var body = el.querySelector('[data-composer-slot-body]');
        var empty = el.querySelector('.motion-composer__slot-empty');
        var previewHost = el.querySelector('[data-composer-preview]');
        gallery.unregisterWithin(previewHost);
        previewHost.innerHTML = '';
        var panelHost = el.querySelector('[data-composer-panel]');
        panelHost.innerHTML = '';
        panelHost.hidden = true;
        delete el.dataset.slug;
        delete el.dataset.url;
        el.classList.add('is-empty');
        el.classList.remove('is-filled');
        body.hidden = true;
        empty.hidden = false;
        // Only the next free slot offers the picker, so the order stays dense.
        empty.querySelector('[data-composer-pick]').hidden = index !== composition.slots.length;
    }

    function resetSlotEls() {
        slotEls.forEach(function (el) { delete el.dataset.slug; });
    }

    function render() {
        composition = core.sanitize(items, P, composition);
        slotEls.forEach(function (el, index) {
            var slot = composition.slots[index];
            if (slot) {
                if (el.dataset.slug !== slot.slug) fillSlot(el, slot, index);
                else updateSlotChrome(el, slot, index);
            } else {
                emptySlot(el, index);
            }
        });
        var n = composition.slots.length;
        if (countEl) countEl.textContent = n + ' / ' + MAX;
        if (shareButton) {
            shareButton.hidden = n === 0;
            // Browsers and chat apps start truncating around 2,000 characters; the JSON export still works then.
            shareButton.disabled = core.toHash(items, P, composition).length > 1900;
            shareButton.title = shareButton.disabled ? (strings.linkTooLong || '') : '';
        }
        if (clearButton) clearButton.hidden = n === 0;
        pickerItems.forEach(function (li) {
            var chosen = composition.slots.some(function (s) { return s.slug === li.dataset.slug; });
            li.classList.toggle('is-chosen', chosen);
            var add = li.querySelector('[data-composer-add]');
            if (add) add.disabled = chosen || n >= MAX;
        });
        writeHash();
        saveDraft();
        scheduleCombined();
        scheduleBrief();
    }

    function addSample(slug) {
        if (!items[slug]) return false;
        if (composition.slots.length >= MAX) { announce(strings.full || ''); return false; }
        if (composition.slots.some(function (s) { return s.slug === slug; })) return false;
        composition.slots.push({ slug: slug, params: {}, target: '', intent: '' });
        render();
        announce(format(strings.added || '%s', items[slug].name.ja));
        return true;
    }

    function removeSample(index) {
        var slot = composition.slots[index];
        if (!slot) return;
        composition.slots.splice(index, 1);
        delete openPanels[slot.slug];
        resetSlotEls(); // re-fill from scratch so previews follow their samples, not their positions
        render();
        announce(format(strings.removed || '%s', items[slot.slug].name.ja));
        var next = slotEls[Math.min(index, composition.slots.length)];
        var focus = next && (next.querySelector('[data-composer-remove]:not([hidden])') || next.querySelector('[data-composer-pick]'));
        if (focus && !focus.hidden) focus.focus();
    }

    function moveSample(index, delta) {
        var to = index + delta;
        if (to < 0 || to >= composition.slots.length) return;
        var slot = composition.slots.splice(index, 1)[0];
        composition.slots.splice(to, 0, slot);
        resetSlotEls();
        render();
        announce(format(strings.moved || '%s %d', items[slot.slug].name.ja, to + 1));
        var btn = slotEls[to].querySelector(delta < 0 ? '[data-composer-up]' : '[data-composer-down]');
        if (btn && !btn.disabled) btn.focus();
        else slotEls[to].querySelector('[data-composer-remove]').focus();
    }

    /* ------------------------------------------------------------------ */
    /* Adjustment panel (fragment + learn-playground.js in host mode)      */
    /* ------------------------------------------------------------------ */

    function panelUrl(slug) {
        var url = galleryUrl() + slug + '/';
        return url + (url.indexOf('?') > -1 ? '&' : '?') + PARTIAL + '=1&' + PANEL + '=1';
    }

    function openPanel(el, slot) {
        var adjust = el.querySelector('[data-composer-adjust]');
        var host = el.querySelector('[data-composer-panel]');
        openPanels[slot.slug] = true;
        adjust.setAttribute('aria-expanded', 'true');
        adjust.textContent = strings.adjustClose || '調整を閉じる';
        host.hidden = false;
        if (panelCache[slot.slug]) { mountPanel(el, slot, panelCache[slot.slug]); return; }
        host.setAttribute('aria-busy', 'true');
        host.innerHTML = '';
        window.fetch(panelUrl(slot.slug), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) throw new Error(String(response.status));
                return response.text();
            })
            .then(function (html) {
                panelCache[slot.slug] = html;
                host.setAttribute('aria-busy', 'false');
                if (el.dataset.slug === slot.slug && openPanels[slot.slug]) mountPanel(el, slot, html);
            })
            .catch(function () {
                host.setAttribute('aria-busy', 'false');
                host.innerHTML = '';
                var p = document.createElement('p');
                p.className = 'motion-composer__panel-error';
                p.textContent = strings.panelFailed || 'Could not load the panel.';
                var retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'motion-copy motion-copy--ghost';
                retry.textContent = strings.retry || 'Retry';
                retry.addEventListener('click', function () { openPanel(el, slot); });
                p.appendChild(retry);
                host.appendChild(p);
            });
    }

    function mountPanel(el, slot, html) {
        var host = el.querySelector('[data-composer-panel]');
        host.innerHTML = html;
        var panel = host.querySelector('[data-motion-playground]');
        if (!panel) return;
        var current = composition.slots.filter(function (s) { return s.slug === slot.slug; })[0] || slot;
        panel.dataset.omxValues = JSON.stringify(P.sanitize(items[slot.slug].params, current.params || {}));
        // Same path as the gallery dialog: learn-playground.js binds panels in the container.
        var event;
        try { event = new window.CustomEvent('omx:motion:detail', { detail: { container: host } }); }
        catch (error) { event = document.createEvent('CustomEvent'); event.initCustomEvent('omx:motion:detail', false, false, { container: host }); }
        document.dispatchEvent(event);
        var first = panel.querySelector('[data-omx-param]');
        if (first && document.activeElement === el.querySelector('[data-composer-adjust]')) first.focus();
    }

    function closePanel(el, slug) {
        var adjust = el.querySelector('[data-composer-adjust]');
        var host = el.querySelector('[data-composer-panel]');
        delete openPanels[slug];
        adjust.setAttribute('aria-expanded', 'false');
        adjust.textContent = strings.adjust || '調整する';
        host.hidden = true;
        host.innerHTML = '';
    }

    // learn-playground.js (host mode) reports every change of a slot's panel.
    root.addEventListener('omx:params:change', function (event) {
        var detail = event.detail || {};
        var el = event.target.closest ? event.target.closest('[data-composer-slot]') : null;
        var index = el ? slotEls.indexOf(el) : -1;
        var slot = composition.slots[index];
        if (!slot || slot.slug !== detail.slug) return;
        var defs = items[slot.slug].params || [];
        var params = {};
        defs.forEach(function (def) {
            var v = detail.values ? detail.values[def.key] : undefined;
            if (v === undefined) return;
            var isDefault = def.type === 'range' ? Number(v) === Number(def['default']) : v === def['default'];
            if (!isDefault) params[def.key] = v;
        });
        slot.params = params;
        updateSlotChrome(el, slot, index);
        writeHash();
        saveDraft();
        scheduleCombined();
        scheduleBrief();
    });

    /* ------------------------------------------------------------------ */
    /* Brief (Markdown), copy / download, JSON in and out                  */
    /* ------------------------------------------------------------------ */

    function assistant() {
        var checked = brief ? brief.querySelector('[data-composer-assistant]:checked') : null;
        return checked ? checked.value : 'claude-code';
    }

    function briefContext() {
        return {
            items: items,
            data: briefData.items,
            shim: briefData.shim,
            slotShim: briefData.slotShim,
            templates: config.brief,
            assistants: config.assistants,
            presets: config.presets,
            noParams: strings.noParams || '調整した値: なし'
        };
    }

    function scheduleBrief() {
        if (!brief || !briefText) return;
        window.clearTimeout(briefTimer);
        briefTimer = window.setTimeout(renderBrief, 300);
    }

    function renderBrief() {
        if (!brief || !briefText) return;
        var n = composition.slots.length;
        brief.hidden = n === 0 || !briefData || !config.brief;
        if (brief.hidden) return;
        briefText.textContent = core.buildBrief(briefContext(), P, composition, assistant());
    }

    function download(name, text) {
        var blob = new Blob([text], { type: 'text/markdown;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    if (brief) {
        brief.addEventListener('click', function (event) {
            var button = event.target.closest('button');
            if (!button) return;
            if (button.hasAttribute('data-composer-brief-copy')) {
                gallery.copyText(briefText.textContent).then(function () {
                    gallery.flash(button, strings.briefCopied || strings.linkCopied || 'Copied', true);
                }).catch(function () {
                    gallery.flash(button, strings.copyFailed || 'Copy failed', false);
                });
            } else if (button.hasAttribute('data-composer-brief-download')) {
                download('omochix-motion-brief.md', briefText.textContent);
            } else if (button.hasAttribute('data-composer-json-copy')) {
                gallery.copyText(JSON.stringify(composition, null, 2)).then(function () {
                    gallery.flash(button, strings.jsonCopied || 'Copied', true);
                }).catch(function () {
                    gallery.flash(button, strings.copyFailed || 'Copy failed', false);
                });
            } else if (button.hasAttribute('data-composer-json-open') && jsonDialog) {
                var text = jsonDialog.querySelector('[data-composer-json-text]');
                var error = jsonDialog.querySelector('[data-composer-json-error]');
                text.value = '';
                error.hidden = true;
                if (typeof jsonDialog.showModal === 'function') jsonDialog.showModal();
                else jsonDialog.setAttribute('open', '');
                text.focus();
            }
        });
        if (jsonDialog) {
            var form = jsonDialog.querySelector('[data-composer-json-form]');
            form.addEventListener('submit', function (event) {
                var submitter = event.submitter || form.querySelector('[data-composer-json-load]');
                if (!submitter || submitter.value !== 'load') return; // "cancel" closes the dialog natively
                var text = jsonDialog.querySelector('[data-composer-json-text]');
                var error = jsonDialog.querySelector('[data-composer-json-error]');
                var parsed = null;
                try { parsed = JSON.parse(text.value); } catch (e) { parsed = null; }
                var comp = parsed ? core.sanitize(items, P, parsed) : null;
                if (!comp || core.isEmpty(comp)) {
                    event.preventDefault();
                    error.textContent = strings.jsonInvalid || 'Invalid JSON';
                    error.hidden = false;
                    text.focus();
                    return;
                }
                composition = comp;
                openPanels = {};
                resetSlotEls();
                render();
                announce(strings.jsonLoaded || '');
            });
        }
    }

    /* ------------------------------------------------------------------ */
    /* Combined preview: one document, rebuilt (debounced) on any change   */
    /* ------------------------------------------------------------------ */

    function combinedDoc() {
        return core.buildCombinedDoc(gallery.catalog, P, composition.slots, combined ? combined.querySelector('.motion-composer__title').textContent : '');
    }

    function scheduleCombined() {
        if (!combined || !combinedPreview) return;
        window.clearTimeout(combinedTimer);
        combinedTimer = window.setTimeout(rebuildCombined, 300);
    }

    function rebuildCombined() {
        var n = composition.slots.length;
        combined.hidden = n === 0;
        gallery.unregisterWithin(combined);
        var frame = combinedPreview.querySelector('[data-motion-frame]');
        if (frame) frame.innerHTML = '';
        combinedPreview.classList.remove('is-mounted');
        if (n === 0) return;
        // Each slot renders at min(frame width, 640px) wide, 320:180, plus a 1px divider between slots.
        var slotWidth = Math.min(combinedPreview.clientWidth || 640, 640);
        combinedPreview.style.setProperty('--combined-h', (n * Math.round(slotWidth * 180 / 320) + (n - 1)) + 'px');
        combinedPreview.omxDocBuilder = combinedDoc;
        gallery.registerWithin(combined);
    }

    /* ------------------------------------------------------------------ */
    /* Picker                                                              */
    /* ------------------------------------------------------------------ */

    function openPicker(trigger) {
        if (!picker) return;
        pickerTrigger = trigger;
        if (supportsDialog) picker.showModal();
        else { picker.setAttribute('open', ''); picker.hidden = false; }
        if (search) { search.value = ''; filterPicker(''); search.focus(); }
    }

    function closePicker() {
        if (!picker) return;
        if (supportsDialog && picker.open) picker.close();
        else picker.removeAttribute('open');
    }

    function filterPicker(query) {
        var q = String(query || '').trim().toLowerCase();
        var shown = 0;
        pickerItems.forEach(function (li) {
            var hit = !q || (li.dataset.search || '').toLowerCase().indexOf(q) > -1;
            li.hidden = !hit;
            if (hit) shown++;
        });
        if (picker) {
            Array.prototype.forEach.call(picker.querySelectorAll('[data-composer-group]'), function (group) {
                group.hidden = !group.querySelector('[data-composer-item]:not([hidden])');
            });
        }
        if (pickerNone) pickerNone.hidden = shown > 0;
    }

    root.addEventListener('click', function (event) {
        var target = event.target;
        var pick = target.closest('[data-composer-pick]');
        if (pick) { openPicker(pick); return; }
        var add = target.closest('[data-composer-add]');
        if (add) {
            var slug = add.closest('[data-composer-item]').dataset.slug;
            if (addSample(slug)) {
                closePicker();
                var el = slotEls[composition.slots.length - 1];
                var remove = el && el.querySelector('[data-composer-remove]');
                if (remove) remove.focus();
            }
            return;
        }
        if (target.closest('[data-composer-picker-close]')) { closePicker(); return; }
        var slotEl = target.closest('[data-composer-slot]');
        if (slotEl) {
            var index = slotEls.indexOf(slotEl);
            var slot = composition.slots[index];
            if (target.closest('[data-composer-remove]')) { removeSample(index); return; }
            if (target.closest('[data-composer-up]')) { moveSample(index, -1); return; }
            if (target.closest('[data-composer-down]')) { moveSample(index, 1); return; }
            if (target.closest('[data-composer-adjust]') && slot) {
                if (openPanels[slot.slug]) closePanel(slotEl, slot.slug);
                else openPanel(slotEl, slot);
                return;
            }
            if (target.closest('[data-composer-notes-toggle]') && slot) {
                openNotes[slot.slug] = !openNotes[slot.slug];
                syncNotes(slotEl, slot);
                if (openNotes[slot.slug]) slotEl.querySelector('[data-composer-note-select]').focus();
                return;
            }
        }
        if (target.closest('[data-composer-clear]')) {
            composition.slots = [];
            openPanels = {};
            openNotes = {};
            resetSlotEls();
            render();
            announce(strings.cleared || '');
            var first = slotEls[0].querySelector('[data-composer-pick]');
            if (first) first.focus();
            return;
        }
        if (target.closest('[data-composer-share]')) {
            var url = window.location.origin + window.location.pathname + window.location.search + '#' + HASH_KEY + '=' + core.toHash(items, P, composition);
            var button = target.closest('[data-composer-share]');
            if (navigator.share && (!navigator.canShare || navigator.canShare({ url: url }))) {
                navigator.share({ url: url, title: document.title }).catch(function (error) {
                    if (!error || error.name !== 'AbortError') copyShare(button, url);
                });
                return;
            }
            copyShare(button, url);
        }
    });

    function copyShare(button, url) {
        gallery.copyText(url).then(function () {
            gallery.flash(button, strings.linkCopied || 'Copied', true);
        }).catch(function () {
            gallery.flash(button, strings.copyFailed || 'Copy failed', false);
        });
    }

    if (picker) {
        picker.addEventListener('click', function (event) {
            if (event.target === picker) closePicker();
        });
        picker.addEventListener('close', function () {
            if (pickerTrigger && typeof pickerTrigger.focus === 'function' && document.body.contains(pickerTrigger) && !pickerTrigger.hidden) pickerTrigger.focus();
            pickerTrigger = null;
        });
        if (search) search.addEventListener('input', function () { filterPicker(search.value); });
    }

    window.addEventListener('hashchange', function () {
        var fromHash = readHash();
        if (!fromHash) return;
        composition = fromHash;
        resetSlotEls();
        render();
    });

    /* ------------------------------------------------------------------ */
    /* Init: the URL wins, then the draft                                  */
    /* ------------------------------------------------------------------ */

    var initial = readHash();
    if (!initial) {
        initial = readDraft();
        if (initial) announce(strings.restored || '');
    }
    if (initial) composition = initial;
    render();
}());
