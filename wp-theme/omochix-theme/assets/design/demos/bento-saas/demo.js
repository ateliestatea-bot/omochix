/* Bento SaaS: tiles appear one after another, numbers count up, charts draw. Reduced motion shows the final state. */
(function () {
    'use strict';
    var demo = window.omxDemo;
    var tiles = Array.prototype.slice.call(document.querySelectorAll('.bn-tile'));
    var nums = Array.prototype.slice.call(document.querySelectorAll('[data-count]'));
    var fmt = function (v, d) { return v.toLocaleString('ja-JP', { minimumFractionDigits: d, maximumFractionDigits: d }); };

    function finalText(el) { var d = Number(el.getAttribute('data-decimals') || 0); return (el.getAttribute('data-prefix') || '') + fmt(Number(el.getAttribute('data-count')), d); }
    function count(el) {
        if (el.getAttribute('data-done')) return;
        el.setAttribute('data-done', '1');
        if (demo.isReduced()) { el.textContent = finalText(el); return; }
        var to = Number(el.getAttribute('data-count')), d = Number(el.getAttribute('data-decimals') || 0), pre = el.getAttribute('data-prefix') || '', t0 = 0;
        function step(now) {
            if (demo.isHidden()) { el.textContent = finalText(el); return; }
            if (!t0) t0 = now;
            var p = Math.min(1, (now - t0) / 1400), e = 1 - Math.pow(1 - p, 3);
            el.textContent = pre + fmt(to * e, d);
            if (p < 1) window.requestAnimationFrame(step);
        }
        window.requestAnimationFrame(step);
    }
    if (demo.isReduced() || !('IntersectionObserver' in window)) {
        tiles.forEach(function (t) { t.classList.add('is-in'); });
        return; // numbers are already in their final value in the markup
    }
    nums.forEach(function (el) { el.textContent = (el.getAttribute('data-prefix') || '') + fmt(0, Number(el.getAttribute('data-decimals') || 0)); });
    var order = 0;
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
            if (!en.isIntersecting) return;
            var t = en.target;
            t.style.setProperty('--delay', (order++ % 8) * 0.08 + 's');
            t.classList.add('is-in');
            io.unobserve(t);
            Array.prototype.forEach.call(t.querySelectorAll('[data-count]'), function (el) { window.setTimeout(function () { count(el); }, 250 + (order % 8) * 80); });
        });
    }, { threshold: 0.2 });
    tiles.forEach(function (t) { io.observe(t); });
    demo.onMotion(function (r) { if (r) { tiles.forEach(function (t) { t.classList.add('is-in'); }); nums.forEach(function (el) { el.setAttribute('data-done', '1'); el.textContent = finalText(el); }); } });
})();
