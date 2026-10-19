/**
 * Learn v2 / Motion Composer: the composition model, pure and DOM-free.
 *
 * Mirrors inc/learn-composer.php (omochix_learn_composer_sanitize / _hash /
 * _parse_hash) so a composition round-trips identically on both sides;
 * tests/learn-composer/core-parity.js checks that. Needs learn-params.js
 * (window.omochixMotionParams) for the per-sample parameter rules.
 *
 * Composition: { v: '1', slots: [{ slug, params: {key: value (non-default only)}, target: '', intent: '' }] }
 * Hash payload: "1;pop-in:cycle=1.5,color=ff0000;toast;gradient-shift:angle=45,t=hero"
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module && module.exports) module.exports = api;
    root.omochixMotionComposerCore = api;
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    var VERSION = '1';
    var MAX_SLOTS = 3;
    var NOTE_MAX = 40;
    var NOTE_RE = /^[\p{L}\p{N} 　・、。，．ー\-_()（）!?！？:：]{0,40}$/u;
    var KEY_RE = /^[a-z][a-z0-9_]{0,23}$/;

    function sameAsDefault(def, v) {
        return def.type === 'range' ? Number(v) === Number(def['default']) : v === def['default'];
    }

    function sanitizeNote(note) {
        if (typeof note !== 'string') return '';
        note = note.replace(/\s+/g, ' ').trim();
        if (note.length > NOTE_MAX || !NOTE_RE.test(note)) return '';
        return note;
    }

    /**
     * Coerce a raw composition into a valid one against the catalog items
     * ({slug: {params: [...]}}) using the params helpers P (learn-params.js).
     */
    function sanitize(items, P, raw) {
        var out = { v: VERSION, slots: [] };
        var slots = raw && Array.isArray(raw.slots) ? raw.slots : [];
        for (var i = 0; i < slots.length && out.slots.length < MAX_SLOTS; i++) {
            var slot = slots[i] || {};
            var slug = typeof slot.slug === 'string' ? slot.slug.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '') : '';
            var item = slug && items[slug];
            if (!item) continue;
            var defs = item.params || [];
            var values = P.sanitize(defs, slot.params && typeof slot.params === 'object' ? slot.params : {});
            var params = {};
            defs.forEach(function (def) {
                if (!sameAsDefault(def, values[def.key])) params[def.key] = values[def.key];
            });
            out.slots.push({ slug: slug, params: params, target: sanitizeNote(slot.target), intent: sanitizeNote(slot.intent) });
        }
        return out;
    }

    /** Hash payload ('' for an empty composition). Expects a sanitized composition. */
    function toHash(items, P, composition) {
        var comp = sanitize(items, P, composition);
        if (!comp.slots.length) return '';
        var segments = [VERSION];
        comp.slots.forEach(function (slot) {
            var defs = items[slot.slug].params || [];
            var pairs = [];
            defs.forEach(function (def) {
                if (!Object.prototype.hasOwnProperty.call(slot.params, def.key)) return;
                var v = slot.params[def.key];
                pairs.push(def.key + '=' + (def.type === 'color' ? String(v).replace(/^#/, '') : def.type === 'range' ? P.formatNumber(v) : v));
            });
            if (slot.target) pairs.push('t=' + encodeURIComponent(slot.target));
            if (slot.intent) pairs.push('i=' + encodeURIComponent(slot.intent));
            segments.push(slot.slug + (pairs.length ? ':' + pairs.join(',') : ''));
        });
        return segments.join(';');
    }

    /** Raw composition from a hash payload; run it through sanitize(). */
    function parseHash(payload) {
        var raw = { slots: [] };
        var segments = String(payload || '').split(';');
        if (segments.shift() !== VERSION) return raw;
        segments.slice(0, MAX_SLOTS).forEach(function (segment) {
            var colon = segment.indexOf(':');
            var slug = colon < 0 ? segment : segment.slice(0, colon);
            var slot = { slug: slug, params: {}, target: '', intent: '' };
            if (colon >= 0) {
                segment.slice(colon + 1).split(',').slice(0, 20).forEach(function (pair) {
                    var eq = pair.indexOf('=');
                    if (eq < 1) return;
                    var key = pair.slice(0, eq);
                    var value = pair.slice(eq + 1, eq + 65);
                    if (key === 't') slot.target = safeDecode(value);
                    else if (key === 'i') slot.intent = safeDecode(value);
                    else if (KEY_RE.test(key)) slot.params[key] = value;
                });
            }
            raw.slots.push(slot);
        });
        return raw;
    }

    function safeDecode(s) {
        try { return decodeURIComponent(s); } catch (error) { return ''; }
    }

    function isEmpty(composition) {
        return !composition || !composition.slots || !composition.slots.length;
    }

    /* ---- combined preview (mirrors omochix_learn_composer_preview_document) ---- */

    /** ".omx-slot[data-slot=\"n\"]{--p-a:v;…}" for the slot's non-default CSS params, '' when none. */
    function scopedBlock(P, defs, values, slot) {
        var blocks = P.blocks(defs, values);
        if (!blocks.css) return '';
        return '.omx-slot[data-slot="' + slot + '"]{' + blocks.css.slice(':root{'.length);
    }

    /** Non-default JS params of a slot as JSON text ("{}" when none). */
    function jsParams(P, defs, values) {
        var blocks = P.blocks(defs, values);
        return blocks.jsBlock ? blocks.jsBlock.slice('window.omxParams='.length, -1) : '{}';
    }

    var SLOT_SHIM = 'function omxSlot(n,p){var l=[];return{animate:function(f){window.omx.animate(f)},param:function(k,d){return Object.prototype.hasOwnProperty.call(p,k)?p[k]:d},onParams:function(f){l.push(f)}}}';
    var FIT_SCRIPT = '(function(){var r=document.documentElement,w=parseInt(r.getAttribute("data-stage-w"),10)||320,h=parseInt(r.getAttribute("data-stage-h"),10)||180;function fit(){var s=document.querySelectorAll(".omx-slot");for(var i=0;i<s.length;i++){var k=s[i].clientWidth/w;s[i].style.height=Math.round(h*k)+"px";var g=s[i].querySelector(".omx-stage");if(g){g.style.setProperty("--omx-w",w+"px");g.style.setProperty("--omx-h",h+"px");g.style.setProperty("--omx-scale",String(k));}}}window.addEventListener("resize",fit);fit();}());';
    var COMBINED_CSS = 'html,body{height:auto;overflow:hidden}.omx-slot{position:relative;width:100%;max-width:640px;margin:0 auto;overflow:hidden}.omx-slot+.omx-slot{border-top:1px solid var(--line)}.omx-slot .omx-stage{transform-origin:top left;left:0;top:0;transform:scale(var(--omx-scale,1))}';

    function escapeHtml(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    /**
     * One sandboxed document with the slots stacked in order.
     * catalog: {stage, runtime:{css,js}, items}; slots: [{slug, params}] (sanitized composition slots);
     * title: document title.
     */
    function buildCombinedDoc(catalog, P, slots, title) {
        var scoped = [];
        var css = [];
        var html = [];
        var js = [];
        var n = 0;
        slots.forEach(function (slot) {
            var item = catalog.items[slot.slug];
            if (!item) return;
            n++;
            var defs = item.params || [];
            var values = P.sanitize(defs, slot.params || {});
            var block = scopedBlock(P, defs, values, n);
            if (block) scoped.push(block);
            if (item.css) css.push('<style>' + item.css + '</style>');
            html.push('<section class="omx-slot" data-slot="' + n + '"><div class="omx-stage">' + (item.html || '') + '</div></section>');
            if (item.js) js.push('<script>(function(omx){' + item.js + '}(omxSlot(' + n + ',' + jsParams(P, defs, values) + ')));</script>');
        });
        return '<!doctype html><html lang="ja" data-theme="light" data-stage-w="' + catalog.stage.width + '" data-stage-h="' + catalog.stage.height + '">'
            + '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            + '<title>' + escapeHtml(title || '') + '</title>'
            + '<style>' + (catalog.runtime.css || '') + '</style>'
            + '<style>' + COMBINED_CSS + '</style>'
            + '<style id="omx-params">' + scoped.join('') + '</style>'
            + css.join('')
            + '</head><body>' + html.join('')
            + '<script>' + (catalog.runtime.js || '') + '</script>'
            + '<script>' + SLOT_SHIM + '</script>'
            + js.join('')
            + '<script>' + FIT_SCRIPT + '</script>'
            + '</body></html>';
    }

    return {
        VERSION: VERSION,
        MAX_SLOTS: MAX_SLOTS,
        NOTE_MAX: NOTE_MAX,
        sanitize: sanitize,
        sanitizeNote: sanitizeNote,
        toHash: toHash,
        parseHash: parseHash,
        isEmpty: isEmpty,
        scopedBlock: scopedBlock,
        jsParams: jsParams,
        buildCombinedDoc: buildCombinedDoc
    };
}));
