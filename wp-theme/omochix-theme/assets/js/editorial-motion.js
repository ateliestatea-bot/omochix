/**
 * Editorial motion frames (assets/css/editorial-motion.css): each frame plays once, about 2.8 seconds, the
 * first time it is in view, then rests on its finished frame. No replay, no play button. The light/dark switch
 * only changes colours, so it never restarts a play. With reduced motion (or without this script) nothing is
 * armed and the finished frame shows from the start.
 */
(function () {
    'use strict';

    var frames = Array.prototype.slice.call(document.querySelectorAll('[data-omx-motion]'));
    if (!frames.length) return;

    var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    if (reduce && reduce.matches) return;

    function finish(frame) {
        frame.classList.remove('is-armed', 'is-playing');
        frame.classList.add('is-done');
    }

    function play(frame) {
        if (!frame.classList.contains('is-armed')) return;
        var timer;
        function done(event) {
            if (event && (event.target !== frame || event.animationName !== 'omx-mo-clock')) return;
            frame.removeEventListener('animationend', done);
            window.clearTimeout(timer);
            finish(frame);
        }
        frame.addEventListener('animationend', done);
        timer = window.setTimeout(done, 4500); // In case animationend never comes (hidden tab, old browser).
        frame.classList.remove('is-armed');
        frame.classList.add('is-playing');
    }

    frames.forEach(function (frame) { frame.classList.add('is-armed'); });

    if (!('IntersectionObserver' in window)) {
        frames.forEach(play);
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            play(entry.target);
        });
    }, { threshold: 0.4 });
    frames.forEach(function (frame) { observer.observe(frame); });

    function finishAll() {
        observer.disconnect();
        frames.forEach(finish);
    }
    window.addEventListener('beforeprint', finishAll);
    if (reduce) {
        if (reduce.addEventListener) reduce.addEventListener('change', function (event) { if (event.matches) finishAll(); });
        else if (reduce.addListener) reduce.addListener(function (event) { if (event.matches) finishAll(); });
    }
}());
