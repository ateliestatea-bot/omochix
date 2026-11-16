/* Aurora Gradient: a Canvas 2D aurora of soft vertical curtains drawn as thin gradient strips.
 * Load control: the canvas renders at a fraction of its CSS size (blurred by scaling, which suits an aurora),
 * at most 30 frames per second, with 5 bands (3 on narrow or low-power devices). If frames get slow it
 * drops to the light setting once. Motion is slow and continuous (sums of sines, no randomness per frame),
 * so nothing flickers.
 * It draws only while: motion is allowed, Save-Data is off, the tab is visible, the parent has not paused it,
 * and the sky is on screen. Reduced motion and Save-Data get one still frame; no JS / no Canvas keep the
 * CSS aurora behind it. The pointer gently bends the nearest curtains and brightens their lower rim. */
(function () {
    'use strict';
    var demo = window.omxDemo;
    var root = document.documentElement;
    var hero = document.querySelector('.au-hero');
    var canvas = document.querySelector('[data-au-canvas]');
    var ctx = null;
    try { ctx = canvas.getContext('2d', { alpha: true }); } catch (e) { ctx = null; }

    var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    var saveData = !!(conn && conn.saveData);
    var lowPower = (navigator.hardwareConcurrency || 8) <= 4 || (navigator.deviceMemory || 8) <= 4;

    /* y: base line (0..1 of the canvas), amp: wave height, len: curtain height, k*: wave counts, s*: speeds. */
    var BANDS = [
        { y: 0.15, amp: 0.07, len: 0.42, k1: 0.9, k2: 2.3, k3: 3.1, s1: 0.050, s2: 0.031, s3: 0.11, ph: 0.0, a: 0.55, c: [94, 240, 180], rim: [200, 255, 230] },
        { y: 0.22, amp: 0.06, len: 0.36, k1: 1.2, k2: 2.9, k3: 4.0, s1: 0.041, s2: 0.027, s3: 0.09, ph: 1.7, a: 0.48, c: [86, 196, 245], rim: [205, 240, 255] },
        { y: 0.36, amp: 0.05, len: 0.30, k1: 0.7, k2: 1.9, k3: 2.6, s1: 0.036, s2: 0.022, s3: 0.08, ph: 3.1, a: 0.40, c: [160, 130, 250], rim: [230, 215, 255] },
        { y: 0.30, amp: 0.05, len: 0.26, k1: 1.5, k2: 3.4, k3: 5.0, s1: 0.058, s2: 0.035, s3: 0.13, ph: 4.4, a: 0.30, c: [120, 236, 205], rim: [215, 255, 240] },
        { y: 0.40, amp: 0.04, len: 0.24, k1: 1.1, k2: 2.6, k3: 3.6, s1: 0.030, s2: 0.019, s3: 0.07, ph: 5.6, a: 0.28, c: [240, 160, 205], rim: [255, 225, 240] }
    ];
    var TAU = Math.PI * 2, FRAME_MS = 1000 / 30;
    var light = lowPower || window.innerWidth < 760;
    var W = 0, H = 0, step = 4, grads = [];
    var t = 18, last = 0, raf = 0, onScreen = true, slow = 0;
    var bend = 0, bendTarget = 0, px = 0.5;

    function bands() { return light ? BANDS.slice(0, 3) : BANDS; }
    function still() { return demo.isReduced() || saveData; }
    function running() { return !!ctx && !still() && !demo.isHidden() && onScreen; }

    function size() {
        if (!ctx) return;
        var r = canvas.getBoundingClientRect();
        var q = light ? 0.33 : 0.5;
        W = Math.max(64, Math.min(light ? 520 : 820, Math.round(r.width * q)));
        H = Math.max(48, Math.round(W * r.height / Math.max(1, r.width)));
        canvas.width = W; canvas.height = H;
        step = Math.max(1, Math.round(W / (light ? 160 : 300)));
        grads = bands().map(function (b) {
            var L = b.len * H, g = ctx.createLinearGradient(0, -L, 0, L * 0.16);
            var c = b.c.join(','), m = b.rim.join(',');
            g.addColorStop(0, 'rgba(' + c + ',0)');
            g.addColorStop(0.55, 'rgba(' + c + ',0.28)');
            g.addColorStop(0.82, 'rgba(' + c + ',0.85)');
            g.addColorStop(0.9, 'rgba(' + m + ',1)');
            g.addColorStop(1, 'rgba(' + c + ',0)');
            return { g: g, L: L };
        });
    }

    function draw() {
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = 1;
        ctx.clearRect(0, 0, W, H);
        ctx.globalCompositeOperation = 'lighter';
        bands().forEach(function (b, n) {
            var gr = grads[n];
            ctx.fillStyle = gr.g;
            for (var x = 0; x < W; x += step) {
                var nx = x / W;
                var near = Math.exp(-Math.pow((nx - px) / 0.18, 2));
                var y = b.y
                    + b.amp * (0.7 * Math.sin(nx * b.k1 * TAU + t * b.s1 * TAU + b.ph) + 0.3 * Math.sin(nx * b.k2 * TAU - t * b.s2 * TAU + b.ph * 1.3))
                    + bend * near * (1 - n * 0.12);
                var glow = 0.55 + 0.45 * Math.sin(nx * b.k3 * TAU + t * b.s3 * TAU + b.ph * 0.7);
                var rays = 0.8 + 0.2 * Math.sin(nx * 41 + t * 0.35 + b.ph);
                var edge = Math.min(1, nx * 6, (1 - nx) * 6);
                var stretch = 0.85 + 0.25 * Math.sin(nx * b.k2 * TAU + t * b.s1 * TAU * 0.6 + b.ph);
                ctx.globalAlpha = Math.min(1, b.a * glow * rays * edge * (1 + 0.45 * near * Math.abs(bend) * 14));
                ctx.setTransform(1, 0, 0, stretch, x, y * H);
                ctx.fillRect(0, -gr.L, step, gr.L * 1.16);  // exact width: overlapping strips would add up into lines
            }
        });
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.globalAlpha = 1;
        if (!root.classList.contains('au-live')) root.classList.add('au-live');
    }

    function tick(now) {
        raf = 0;
        if (!running()) return;
        raf = window.requestAnimationFrame(tick);
        if (last && now - last < FRAME_MS - 2) return;
        var dt = last ? Math.min(0.05, (now - last) / 1000) : 0;
        last = now;
        t += dt;
        bend += (bendTarget - bend) * 0.06;
        var s = performance.now();
        draw();
        // One-way fallback: if drawing keeps taking more than 14 ms, switch to the light setting.
        if (!light) { slow = performance.now() - s > 14 ? slow + 1 : 0; if (slow > 20) { light = true; size(); } }
    }
    function update() {
        if (running()) { if (!raf) { last = 0; raf = window.requestAnimationFrame(tick); } }
        else if (raf) { window.cancelAnimationFrame(raf); raf = 0; }
    }

    if (ctx) {
        size();
        if (still()) draw();
        hero.addEventListener('pointermove', function (e) {
            if (still()) return;
            var r = hero.getBoundingClientRect();
            px = (e.clientX - r.left) / Math.max(1, r.width);
            bendTarget = ((e.clientY - r.top) / Math.max(1, r.height) - 0.4) * 0.05;
        });
        hero.addEventListener('pointerleave', function () { bendTarget = 0; });
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (es) { onScreen = es[es.length - 1].isIntersecting; update(); }, { threshold: 0 }).observe(canvas);
        }
        var rt = 0;
        window.addEventListener('resize', function () {
            window.clearTimeout(rt);
            rt = window.setTimeout(function () { light = lowPower || window.innerWidth < 760 || slow > 20; size(); if (!running()) draw(); }, 150);
        });
        demo.onVisibility(update);
        demo.onMotion(function (reduced) { if (reduced) { update(); bendTarget = bend = 0; draw(); } else { update(); } });
    }

    // The aurora fades as the page scrolls past it (no extra frames: only a CSS variable).
    window.addEventListener('scroll', function () {
        if (demo.isReduced()) { root.style.removeProperty('--fade'); return; }
        var f = 1 - Math.min(1, window.scrollY / Math.max(1, hero.offsetHeight * 0.9)) * 0.8;
        root.style.setProperty('--fade', f.toFixed(3));
    }, { passive: true });

    var reveals = Array.prototype.slice.call(document.querySelectorAll('[data-au-reveal]'));
    function revealAll() { reveals.forEach(function (el) { el.classList.add('is-in'); }); }
    if (demo.isReduced() || !('IntersectionObserver' in window)) revealAll();
    else {
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
        }, { threshold: 0.15 });
        reveals.forEach(function (el) { io.observe(el); });
    }
    demo.onMotion(function (reduced) { if (reduced) revealAll(); });

    update();
    window.requestAnimationFrame(function () { hero.classList.add('is-in'); });
})();
