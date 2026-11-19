/* Minimal Editorial: headline lines fade in, hairlines draw with scroll, page-turn between articles. */
(function () {
    'use strict';
    var demo = window.omxDemo;
    var hero = document.querySelector('.ed-hero');
    var drawn = Array.prototype.slice.call(document.querySelectorAll('.ed-rule, .ed-figure'));
    var pending = false;

    function progress(el) {
        var r = el.getBoundingClientRect(), vh = window.innerHeight;
        return Math.max(0, Math.min(1, (vh - r.top) / (vh * 0.55 + r.height * 0.5)));
    }
    function frame() {
        pending = false;
        drawn.forEach(function (el) { el.style.setProperty('--p', demo.isReduced() ? '1' : progress(el).toFixed(3)); });
    }
    function schedule() { if (!pending && !demo.isHidden()) { pending = true; window.requestAnimationFrame(frame); } }
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    demo.onMotion(schedule);
    demo.onVisibility(function (hidden) { if (!hidden) schedule(); });
    schedule();
    window.requestAnimationFrame(function () { hero.classList.add('is-in'); });

    var pages = Array.prototype.slice.call(document.querySelectorAll('.ed-page'));
    var index = 0, label = document.querySelector('[data-ed-index]');
    function show(next, back) {
        next = (next + pages.length) % pages.length;
        if (next === index) return;
        pages[index].classList.remove('is-current', 'is-entering', 'is-back');
        index = next;
        var p = pages[index];
        p.classList.add('is-current');
        if (!demo.isReduced()) {
            p.classList.remove('is-entering', 'is-back');
            void p.offsetWidth;
            p.classList.add('is-entering');
            if (back) p.classList.add('is-back');
        }
        label.textContent = String(index + 1);
    }
    document.querySelector('[data-ed-next]').addEventListener('click', function () { show(index + 1, false); });
    document.querySelector('[data-ed-prev]').addEventListener('click', function () { show(index - 1, true); });
})();
