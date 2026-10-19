/**
 * Learn v2 / Motion Composer page (progressive enhancement).
 *
 * Up to three samples are picked from the picker dialog, ordered and
 * removed. Each slot shows the sample's live preview (cloned from the
 * picker and registered with learn-gallery.js). The composition is kept in
 * the URL hash (#c=…, see learn-composer-core.js) so it can be shared, and
 * the last state is also saved as a localStorage draft for the next visit.
 * Per-slot parameter panels and the implementation brief come in later
 * phases; the model already carries params and notes for them.
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
    var supportsDialog = !!(picker && typeof window.HTMLDialogElement === 'function' && 'showModal' in window.HTMLDialogElement.prototype);
    var composition = { v: core.VERSION, slots: [] };
    var pickerTrigger = null;
    var pickerTargetIndex = null;

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

    /* ------------------------------------------------------------------ */
    /* Slots                                                               */
    /* ------------------------------------------------------------------ */

    function previewSource(slug) {
        return picker ? picker.querySelector('[data-composer-item][data-slug="' + slug + '"] [data-motion-preview]') : null;
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
            var preview = gallery.previewsOf(slot.slug).filter(function (p) { return previewHost.contains(p.el); })[0];
            if (preview && Object.keys(slot.params).length) {
                preview.params = P.sanitize(item.params, slot.params);
                if (preview.ready) gallery.sendParams(preview);
            }
        }
        el.querySelector('[data-composer-name]').textContent = item.name && item.name.ja ? item.name.ja : slot.slug;
        el.querySelector('[data-composer-en]').textContent = item.name && item.name.en ? item.name.en : '';
        el.querySelector('[data-composer-summary]').textContent = item.summary || '';
        el.querySelector('[data-composer-order]').textContent = String(index + 1);
        var link = el.querySelector('[data-composer-link]');
        if (link) link.href = (config.galleryUrl || (window.omochixLearnGallery && window.omochixLearnGallery.listUrl) || '/learn/motion/').replace(/\/?$/, '/') + slot.slug + '/';
        el.querySelector('[data-composer-up]').disabled = index === 0;
        el.querySelector('[data-composer-down]').disabled = index === composition.slots.length - 1;
        el.dataset.slug = slot.slug;
        el.classList.remove('is-empty');
        el.classList.add('is-filled');
        empty.hidden = true;
        body.hidden = false;
    }

    function emptySlot(el, index) {
        var body = el.querySelector('[data-composer-slot-body]');
        var empty = el.querySelector('.motion-composer__slot-empty');
        var previewHost = el.querySelector('[data-composer-preview]');
        gallery.unregisterWithin(previewHost);
        previewHost.innerHTML = '';
        delete el.dataset.slug;
        el.classList.add('is-empty');
        el.classList.remove('is-filled');
        body.hidden = true;
        empty.hidden = false;
        // Only the next free slot offers the picker, so the order stays dense.
        empty.querySelector('[data-composer-pick]').hidden = index !== composition.slots.length;
    }

    function render() {
        composition = core.sanitize(items, P, composition);
        slotEls.forEach(function (el, index) {
            var slot = composition.slots[index];
            if (slot) {
                if (el.dataset.slug !== slot.slug) fillSlot(el, slot, index);
                else {
                    el.querySelector('[data-composer-order]').textContent = String(index + 1);
                    el.querySelector('[data-composer-up]').disabled = index === 0;
                    el.querySelector('[data-composer-down]').disabled = index === composition.slots.length - 1;
                }
            } else {
                emptySlot(el, index);
            }
        });
        var n = composition.slots.length;
        if (countEl) countEl.textContent = n + ' / ' + MAX;
        if (shareButton) shareButton.hidden = n === 0;
        if (clearButton) clearButton.hidden = n === 0;
        pickerItems.forEach(function (li) {
            var chosen = composition.slots.some(function (s) { return s.slug === li.dataset.slug; });
            li.classList.toggle('is-chosen', chosen);
            var add = li.querySelector('[data-composer-add]');
            if (add) add.disabled = chosen || n >= MAX;
        });
        writeHash();
        saveDraft();
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
        // Re-fill from scratch so previews follow their samples, not their positions.
        slotEls.forEach(function (el) { delete el.dataset.slug; el.classList.add('is-empty'); });
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
        slotEls.forEach(function (el) { delete el.dataset.slug; el.classList.add('is-empty'); });
        render();
        announce(format(strings.moved || '%s %d', items[slot.slug].name.ja, to + 1));
        var btn = slotEls[to].querySelector(delta < 0 ? '[data-composer-up]' : '[data-composer-down]');
        if (btn && !btn.disabled) btn.focus();
        else slotEls[to].querySelector('[data-composer-remove]').focus();
    }

    /* ------------------------------------------------------------------ */
    /* Picker                                                              */
    /* ------------------------------------------------------------------ */

    function openPicker(trigger, index) {
        if (!picker) return;
        pickerTrigger = trigger;
        pickerTargetIndex = index;
        if (supportsDialog) picker.showModal();
        else { picker.setAttribute('open', ''); picker.hidden = false; }
        if (search) { search.value = ''; filterPicker(''); search.focus(); }
        gallery.refreshAll && gallery.refreshAll();
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
        if (pick) { openPicker(pick, parseInt(pick.closest('[data-composer-slot]').dataset.composerSlot, 10)); return; }
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
            if (target.closest('[data-composer-remove]')) { removeSample(index); return; }
            if (target.closest('[data-composer-up]')) { moveSample(index, -1); return; }
            if (target.closest('[data-composer-down]')) { moveSample(index, 1); return; }
        }
        if (target.closest('[data-composer-clear]')) {
            composition.slots = [];
            slotEls.forEach(function (el) { delete el.dataset.slug; });
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
        slotEls.forEach(function (el) { delete el.dataset.slug; });
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
