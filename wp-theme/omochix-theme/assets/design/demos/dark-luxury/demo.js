/* Dark Luxury: hero layers move at different depths with scroll, headline lines rise out of a mask,
 * sections reveal once, and on wide screens the works pin and slide sideways with vertical scroll.
 * No frame loop: work happens only on scroll / resize, and nothing moves while hidden or paused.
 * Reduced motion, narrow screens and no JS: no pinning, no parallax — the stacked layout. */
(function () {
    'use strict';
    var demo = window.omxDemo;
    var root = document.documentElement;
    var hero = document.querySelector('.dk-hero');
    var nav = document.querySelector('.dk-nav');
    var works = document.querySelector('[data-dk-works]');
    var track = document.querySelector('[data-dk-track]');
    var index = document.querySelector('[data-dk-index]');
    var interior = document.querySelector('[data-dk-depth]');
    var cards = Array.prototype.slice.call(document.querySelectorAll('.dk-card'));
    var pending = false, pin = { on: false, overflow: 0, top: 0 };

    function clamp(v) { return Math.max(0, Math.min(1, v)); }
    function wantPin() { return window.innerWidth >= 900 && !demo.isReduced(); }
    function layout() {
        pin.on = wantPin();
        root.classList.toggle('dk-pin', pin.on);
        works.style.height = '';
        track.style.setProperty('--x', '0px');
        if (!pin.on) return;
        pin.overflow = Math.max(0, track.scrollWidth - window.innerWidth);
        works.style.height = (window.innerHeight + pin.overflow) + 'px';
        pin.top = works.getBoundingClientRect().top + window.scrollY;
    }
    function frame() {
        pending = false;
        if (demo.isHidden()) return;
        var y = window.scrollY;
        nav.classList.toggle('is-solid', y > 40);
        if (demo.isReduced()) return;
        hero.style.setProperty('--p', clamp(y / Math.max(1, hero.offsetHeight)).toFixed(3));
        if (pin.on) {
            var p = clamp((y - pin.top) / Math.max(1, pin.overflow));
            track.style.setProperty('--x', (-p * pin.overflow).toFixed(1) + 'px');
            index.textContent = '0' + Math.min(cards.length, 1 + Math.round(p * (cards.length - 1)));
        }
        var r = interior.getBoundingClientRect();
        var t = clamp((window.innerHeight - r.top) / (window.innerHeight + r.height));
        interior.style.setProperty('--d', ((t - 0.5) * -36).toFixed(1) + 'px');
    }
    function schedule() { if (!pending) { pending = true; window.requestAnimationFrame(frame); } }

    // Keyboard: a focused card in the pinned track is scrolled into view (the track moves with page scroll).
    cards.forEach(function (card, i) {
        card.addEventListener('focusin', function () {
            if (!pin.on) return;
            var p = cards.length > 1 ? i / (cards.length - 1) : 0;
            window.scrollTo(0, pin.top + p * pin.overflow);
        });
    });

    var reveals = Array.prototype.slice.call(document.querySelectorAll('[data-dk-reveal]'));
    if (demo.isReduced() || !('IntersectionObserver' in window)) {
        reveals.forEach(function (el) { el.classList.add('is-in'); });
    } else {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
        }, { threshold: 0.2 });
        reveals.forEach(function (el) { io.observe(el); });
    }

    var t = 0;
    window.addEventListener('resize', function () { window.clearTimeout(t); t = window.setTimeout(function () { layout(); schedule(); }, 120); });
    window.addEventListener('scroll', schedule, { passive: true });
    demo.onVisibility(function (hidden) { if (!hidden) schedule(); });
    demo.onMotion(function (reduced) { if (reduced) reveals.forEach(function (el) { el.classList.add('is-in'); }); layout(); schedule(); });
    layout();
    schedule();
    window.requestAnimationFrame(function () { hero.classList.add('is-in'); });
})();
