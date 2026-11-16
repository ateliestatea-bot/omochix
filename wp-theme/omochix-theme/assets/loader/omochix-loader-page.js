/* OmochiX page overlay: ends on the REAL document state - DOMContentLoaded + web fonts ready - never on a fixed timer.
 * It deliberately does NOT wait for window "load" (images/embeds): the page is already usable and a loader must never
 * cover readable content longer than necessary.
 * - If the page finishes before the overlay became visible (<250ms), it is removed without ever showing.
 * - Otherwise: fast-forward to the completed logo (6x), fade, remove. */
(function () {
  'use strict';
  var overlay = document.querySelector('[data-omx-overlay]');
  var root = document.documentElement;
  function remove() { if (overlay) overlay.classList.add('is-gone'); root.classList.remove('omx-js', 'omx-show'); }
  if (!overlay || !window.OmochiXLoader) { remove(); return; }
  var node = overlay.querySelector('[data-omx-loader]');
  var ctl = window.OmochiXLoader.enhance(node);
  var finished = false;

  function leave() {
    if (finished) return; finished = true;
    try { sessionStorage.setItem('omxSeen', '1'); } catch (e) {}
    if (!root.classList.contains('omx-show')) { remove(); return; }   // 250ms not reached: never shown
    ctl.finish({ rate: 6 }).then(function () {
      overlay.classList.add('is-leaving');
      setTimeout(remove, 280);
    });
  }
  function whenReady() {
    var fonts = document.fonts && document.fonts.ready;
    if (!fonts) return leave();
    // 1.5s cap is a safety net for a stalled font request, not a wait time.
    Promise.race([fonts, new Promise(function (r) { setTimeout(r, 1500); })]).then(leave, leave);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', whenReady, { once: true });
  else whenReady();
})();
