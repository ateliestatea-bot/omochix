// Shared in-page IME check (string evaluated by Playwright and by the Firefox Marionette runner). Returns a result object.
module.exports = `(async () => {
  const i = document.getElementById('ed-search-input'); i.focus();
  i.value = 'cl'; i.dispatchEvent(new Event('input', { bubbles: true })); await new Promise(r => setTimeout(r, 400));
  const open = () => !!document.querySelector('.omx-suggest:not([hidden])');
  const key = (k, o) => { const e = new KeyboardEvent('keydown', Object.assign({ key: k, bubbles: true, cancelable: true }, o || {})); i.dispatchEvent(e); return e.defaultPrevented; };
  const r = { listOpenBefore: open() };
  // 1) event.isComposing
  r.isComposing = { down: key('ArrowDown', { isComposing: true }), up: key('ArrowUp', { isComposing: true }), enter: key('Enter', { isComposing: true }), esc: key('Escape', { isComposing: true }) };
  // 2) keyCode 229 (Safari's confirming Enter / some Android keyboards)
  r.keyCode229 = { enter: key('Enter', { keyCode: 229 }), down: key('ArrowDown', { keyCode: 229 }) };
  // 3) compositionstart..compositionend window (flag set by the listeners), plain key events inside it
  i.dispatchEvent(new CompositionEvent('compositionstart', { data: '' }));
  r.insideComposition = { down: key('ArrowDown'), enter: key('Enter'), esc: key('Escape') };
  i.value = 'claude'; i.dispatchEvent(new CompositionEvent('compositionend', { data: 'ude' })); await new Promise(r => setTimeout(r, 400));
  r.listOpenAfterComposition = open();
  // 4) after the composition: normal keyboard behaviour is back
  r.afterComposition = { down: key('ArrowDown'), activeSet: !!i.getAttribute('aria-activedescendant'), escWithText: key('Escape'), closed: !open() };
  r.urlUnchanged = location.pathname === '/';
  r.pass = r.listOpenBefore && !Object.values(r.isComposing).some(Boolean) && !Object.values(r.keyCode229).some(Boolean) && !Object.values(r.insideComposition).some(Boolean)
    && r.listOpenAfterComposition && r.afterComposition.down && r.afterComposition.activeSet && r.afterComposition.escWithText && r.afterComposition.closed && r.urlUnchanged;
  return r;
})()`;
