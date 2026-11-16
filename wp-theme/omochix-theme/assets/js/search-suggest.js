/*
 * OmochiX search suggestions (v1). Vanilla JS, no dependencies.
 * - One lazy GET of a cached public JSON index on first focus; filtering is 100% local (typing sends nothing).
 * - Text only via textContent / DOM nodes (no innerHTML): query and titles cannot inject markup.
 * - Accessible combobox: role=combobox / listbox / option, aria-activedescendant, Arrow/Enter/Escape, IME-safe.
 * - Without JS or on any failure the normal search form works unchanged.
 */
(function () {
  'use strict';
  var cfg = window.omochixSuggest;
  if (!cfg || !window.fetch) return;
  var items = null, loading = null, retryAt = 0, uid = 0;
  var ORDER = { section: 0, theme: 1, tool: 2, labs: 3, learn: 4, 'tool-category': 5, category: 5, 'prompt-category': 6, prompt: 7, news: 8 };

  function norm(s) {
    s = String(s || '');
    try { s = s.normalize('NFKC'); } catch (e) {}
    s = s.toLowerCase().replace(/[ぁ-ゖ]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) + 0x60); });   // hiragana -> katakana
    return s.replace(/\s+/g, ' ').trim();
  }
  var cssDone = false;
  function ensureCss() {
    if (cssDone || !cfg.css) return; cssDone = true;
    var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = cfg.css; document.head.appendChild(l);
  }
  function load() {
    ensureCss();
    if (items) return Promise.resolve(items);
    if (loading) return loading;
    if (Date.now() < retryAt) return Promise.resolve([]);   // index unavailable a moment ago: no suggestions, normal search still works
    loading = fetch(cfg.index, { credentials: 'omit', headers: { Accept: 'application/json' } })
      .then(function (r) {
        // 503 = the server is rebuilding the index (R2): honour Retry-After instead of refetching on every keystroke.
        if (!r.ok) { retryAt = Date.now() + 1000 * (parseInt(r.headers.get('Retry-After'), 10) || 5); throw new Error('index'); }
        return r.json();
      })
      .then(function (d) {
        var list = (d && d.items) || [];
        items = list.map(function (e) { return { e: e, t: norm(e.t), k: norm(e.k || '') }; });
        return items;
      })
      .catch(function () { if (!retryAt || Date.now() >= retryAt) retryAt = Date.now() + 5000; loading = null; return []; });
    return loading;
  }
  function match(q, limit) {
    var out = [];
    if (!items) return out;
    if (!q) {   // empty: popular themes + sections
      for (var i = 0; i < items.length && out.length < 8; i++) { var y = items[i].e.y; if (y === 'theme' || y === 'section') out.push({ e: items[i].e, s: 0 }); }
      return out;
    }
    var toks = q.split(' ');
    items.forEach(function (it) {
      var hay = it.t + ' ' + it.k, s = 0, ok = true;
      for (var j = 0; j < toks.length; j++) {
        var tk = toks[j], p = hay.indexOf(tk);
        if (p < 0) { ok = false; break; }
        s += p === 0 ? 3 : (hay.charAt(p - 1) === ' ' ? 2 : 1);
      }
      if (ok) out.push({ e: it.e, s: s });
    });
    out.sort(function (a, b) { return (b.s - a.s) || ((ORDER[a.e.y] || 9) - (ORDER[b.e.y] || 9)) || a.e.t.length - b.e.t.length; });
    // a popular-theme row that merely repeats another result's title is noise: keep the real destination
    var seen = {}; out.forEach(function (o) { if (o.e.y !== 'theme') seen[norm(o.e.t)] = 1; });
    out = out.filter(function (o) { return o.e.y !== 'theme' || !seen[norm(o.e.t)]; });
    return out.slice(0, limit);
  }

  function attach(input) {
    if (input.__omxSuggest) return; input.__omxSuggest = true;
    var host = input.closest('dialog') || document.body, list = document.createElement('ul'), id = 'omx-suggest-' + (++uid), active = -1, rows = [], composing = false, open = false;
    list.id = id; list.className = 'omx-suggest'; list.setAttribute('role', 'listbox'); list.setAttribute('aria-label', cfg.i18n.label); list.hidden = true;
    host.appendChild(list);
    input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list'); input.setAttribute('aria-expanded', 'false'); input.setAttribute('aria-controls', id); input.setAttribute('autocomplete', 'off');

    function place() {
      var r = input.closest('form') ? input.closest('form').getBoundingClientRect() : input.getBoundingClientRect();
      list.style.left = Math.max(8, r.left) + 'px'; list.style.top = (r.bottom + 6) + 'px'; list.style.width = Math.min(r.width, window.innerWidth - 16) + 'px';
      var room = window.innerHeight - r.bottom - 16; list.style.maxHeight = Math.max(160, Math.min(380, room)) + 'px';
    }
    function setActive(n) {
      if (active >= 0 && rows[active]) { rows[active].classList.remove('is-active'); rows[active].setAttribute('aria-selected', 'false'); }
      active = n;
      if (n >= 0 && rows[n]) { rows[n].classList.add('is-active'); rows[n].setAttribute('aria-selected', 'true'); input.setAttribute('aria-activedescendant', rows[n].id); rows[n].scrollIntoView({ block: 'nearest' }); }
      else input.removeAttribute('aria-activedescendant');
    }
    function close() { open = false; list.hidden = true; input.setAttribute('aria-expanded', 'false'); setActive(-1); }
    function highlight(parent, text, q) {
      var tn = norm(text), p = q ? tn.indexOf(q.split(' ')[0]) : -1;
      if (p < 0 || tn.length !== text.length) { parent.appendChild(document.createTextNode(text)); return; }   // only highlight when 1:1 normalised
      var len = q.split(' ')[0].length;
      parent.appendChild(document.createTextNode(text.slice(0, p)));
      var m = document.createElement('mark'); m.textContent = text.slice(p, p + len); parent.appendChild(m);
      parent.appendChild(document.createTextNode(text.slice(p + len)));
    }
    function render() {
      var raw = input.value, q = norm(raw), res = match(q, 8);
      list.textContent = ''; rows = []; active = -1;
      res.forEach(function (r, i) {
        var li = document.createElement('li'), a = document.createElement('a'), tag = document.createElement('span'), t = document.createElement('span');
        li.id = id + '-' + i; li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
        a.href = r.e.u; a.tabIndex = -1; tag.className = 'omx-suggest__type'; tag.textContent = cfg.types[r.e.y] || ''; t.className = 'omx-suggest__title'; highlight(t, r.e.t, q);
        a.appendChild(t); a.appendChild(tag); li.appendChild(a); list.appendChild(li); rows.push(li);
      });
      if (raw.trim()) {   // always offer the full search as the last row
        var li2 = document.createElement('li'), a2 = document.createElement('a'), t2 = document.createElement('span');
        li2.id = id + '-all'; li2.setAttribute('role', 'option'); li2.setAttribute('aria-selected', 'false'); li2.className = 'omx-suggest__all';
        a2.href = cfg.search + '?s=' + encodeURIComponent(raw.trim()); a2.tabIndex = -1; t2.className = 'omx-suggest__title'; t2.textContent = cfg.i18n.all.replace('%s', raw.trim().slice(0, 40));
        a2.appendChild(t2); li2.appendChild(a2); list.appendChild(li2); rows.push(li2);
      }
      if (!rows.length) { close(); return; }
      place(); open = true; list.hidden = false; input.setAttribute('aria-expanded', 'true');
    }
    function update() { if (composing) return; load().then(function () { if (document.activeElement === input) render(); }); }

    input.addEventListener('focus', update);
    input.addEventListener('input', update);
    input.addEventListener('compositionstart', function () { composing = true; });
    input.addEventListener('compositionend', function () { composing = false; update(); });
    input.addEventListener('keydown', function (e) {
      // IME: while a composition is active the keys belong to the IME (candidate list, confirm, cancel). Chrome/Edge report
      // isComposing; Safari sends the confirming Enter as keyCode 229 after compositionend in some versions; keep both checks.
      if (composing || e.isComposing || e.keyCode === 229) return;
      if (!open) { if (e.key === 'ArrowDown') { update(); } return; }
      if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1 >= rows.length ? 0 : active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active <= 0 ? rows.length - 1 : active - 1); }
      else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); window.location.href = rows[active].querySelector('a').href; }
      else if (e.key === 'Escape') {   // empty box: let Escape reach the dialog (one press closes everything); typed text: first Escape closes the list only
        if (!input.value.trim()) { close(); return; }
        e.preventDefault(); e.stopPropagation(); close();
      }
    });
    list.addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep focus in the input
    list.addEventListener('click', function (e) { var a = e.target.closest('a'); if (a) { window.location.href = a.href; e.preventDefault(); } });
    document.addEventListener('click', function (e) { if (open && e.target !== input && !list.contains(e.target)) close(); });
    input.addEventListener('blur', function () { setTimeout(function () { if (document.activeElement !== input) close(); }, 150); });
    window.addEventListener('resize', function () { if (open) place(); });
    window.addEventListener('scroll', function () { if (open) place(); }, { passive: true });
    if (document.activeElement === input) update();   // the box was focused before this (late-loaded) script arrived
  }

  function init() {
    var inputs = document.querySelectorAll('form[role="search"] input[name="s"]');
    for (var i = 0; i < inputs.length; i++) attach(inputs[i]);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
