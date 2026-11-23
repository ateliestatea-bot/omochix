/* Frosted Glass UI: pointer-following highlight, scroll-linked blur. Stops when hidden; nothing moves with reduced motion. */
(function () {
    'use strict';
    var demo = window.omxDemo;
    var root = document.documentElement;
    var spot = document.querySelector('.lg-spot');
    var lg = document.querySelector('.lg');
    var lastBlur = '';
    var cards = Array.prototype.slice.call(document.querySelectorAll('.glass'));
    var pending = false, px = 0, py = 0, target = null;

    function frame() {
        pending = false;
        if (demo.isReduced() || demo.isHidden()) return;
        spot.style.setProperty('--mx', px + 'px'); // only the highlight layer moves (transform), no page-wide style change
        spot.style.setProperty('--my', py + 'px');
        if (target) {
            var r = target.getBoundingClientRect();
            target.style.setProperty('--px', (px - r.left) + 'px');
            target.style.setProperty('--py', (py - r.top) + 'px');
        }
        var max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
        var s = Math.min(1, window.scrollY / max);
        var blur = Math.round(10 + s * 8) + 'px'; // whole pixels: the glass re-renders only when the value changes
        if (blur !== lastBlur) { lastBlur = blur; lg.style.setProperty('--blur', blur); }
    }
    function schedule() { if (!pending) { pending = true; window.requestAnimationFrame(frame); } }

    window.addEventListener('pointermove', function (e) {
        px = e.clientX; py = e.clientY;
        target = null;
        for (var i = 0; i < cards.length; i++) { if (cards[i].contains(e.target)) { target = cards[i]; break; } }
        schedule();
    }, { passive: true });
    window.addEventListener('scroll', schedule, { passive: true });
    demo.onVisibility(function (hidden) { root.classList.toggle('paused', hidden); if (!hidden) schedule(); });
    demo.onMotion(function (reduced) { if (reduced) { lg.style.setProperty('--blur', '12px'); lastBlur = ''; } else { schedule(); } });
    px = window.innerWidth * 0.6; py = window.innerHeight * 0.35;
    schedule();
})();
