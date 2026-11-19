/* 3D Interactive: procedurally generated module in plain WebGL.
 * Drag / arrow keys rotate, page scroll moves the camera, hovering a part (or its card) highlights it,
 * parts assemble on load. Reduced motion: no auto rotation, camera move or assembly — drag only.
 * The render loop runs only while the canvas is on screen, the page is visible and something moves. */
(function () {
    'use strict';
    var demo = window.omxDemo;
    var canvas = document.querySelector('.kd-canvas');
    var label = document.querySelector('.kd-label');
    var gl = null;
    try { gl = canvas.getContext('webgl', { antialias: true, alpha: true, powerPreference: 'low-power' }); } catch (e) { gl = null; }
    if (!gl) return; // poster fallback stays visible

    /* ---------------- math (column-major 4x4) ---------------- */
    function m4() { var m = new Float32Array(16); m[0] = m[5] = m[10] = m[15] = 1; return m; }
    function mul(a, b) {
        var o = new Float32Array(16);
        for (var c = 0; c < 4; c++) for (var r = 0; r < 4; r++) {
            o[c * 4 + r] = a[r] * b[c * 4] + a[4 + r] * b[c * 4 + 1] + a[8 + r] * b[c * 4 + 2] + a[12 + r] * b[c * 4 + 3];
        }
        return o;
    }
    function tr(x, y, z) { var m = m4(); m[12] = x; m[13] = y; m[14] = z; return m; }
    function sc(s) { var m = m4(); m[0] = m[5] = m[10] = s; return m; }
    function rx(a) { var m = m4(), c = Math.cos(a), s = Math.sin(a); m[5] = c; m[6] = s; m[9] = -s; m[10] = c; return m; }
    function ry(a) { var m = m4(), c = Math.cos(a), s = Math.sin(a); m[0] = c; m[2] = -s; m[8] = s; m[10] = c; return m; }
    function rz(a) { var m = m4(), c = Math.cos(a), s = Math.sin(a); m[0] = c; m[1] = s; m[4] = -s; m[5] = c; return m; }
    function persp(fy, asp, n, f) { var m = new Float32Array(16), t = 1 / Math.tan(fy / 2); m[0] = t / asp; m[5] = t; m[10] = (f + n) / (n - f); m[11] = -1; m[14] = 2 * f * n / (n - f); return m; }
    function lookAt(e, c, u) {
        var z = norm([e[0] - c[0], e[1] - c[1], e[2] - c[2]]), x = norm(cross(u, z)), y = cross(z, x), m = m4();
        m[0] = x[0]; m[4] = x[1]; m[8] = x[2]; m[1] = y[0]; m[5] = y[1]; m[9] = y[2]; m[2] = z[0]; m[6] = z[1]; m[10] = z[2];
        m[12] = -dot(x, e); m[13] = -dot(y, e); m[14] = -dot(z, e); return m;
    }
    function cross(a, b) { return [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]]; }
    function dot(a, b) { return a[0] * b[0] + a[1] * b[1] + a[2] * b[2]; }
    function norm(a) { var l = Math.hypot(a[0], a[1], a[2]) || 1; return [a[0] / l, a[1] / l, a[2] / l]; }
    function xf(m, p) { var x = p[0], y = p[1], z = p[2]; return [m[0] * x + m[4] * y + m[8] * z + m[12], m[1] * x + m[5] * y + m[9] * z + m[13], m[2] * x + m[6] * y + m[10] * z + m[14], m[3] * x + m[7] * y + m[11] * z + m[15]]; }
    function ease(t) { t = Math.max(0, Math.min(1, t)); return 1 - Math.pow(1 - t, 3); }

    /* ---------------- geometry ---------------- */
    function icoBase() {
        var t = (1 + Math.sqrt(5)) / 2;
        var v = [[-1, t, 0], [1, t, 0], [-1, -t, 0], [1, -t, 0], [0, -1, t], [0, 1, t], [0, -1, -t], [0, 1, -t], [t, 0, -1], [t, 0, 1], [-t, 0, -1], [-t, 0, 1]].map(norm);
        var f = [[0, 11, 5], [0, 5, 1], [0, 1, 7], [0, 7, 10], [0, 10, 11], [1, 5, 9], [5, 11, 4], [11, 10, 2], [10, 7, 6], [7, 1, 8], [3, 9, 4], [3, 4, 2], [3, 2, 6], [3, 6, 8], [3, 8, 9], [4, 9, 5], [2, 4, 11], [6, 2, 10], [8, 6, 7], [9, 8, 1]];
        return { v: v, f: f };
    }
    function flat(tris) { // tris: [[a,b,c],...] of points -> positions + face normals
        var pos = [], nrm = [];
        tris.forEach(function (t) {
            var n = norm(cross([t[1][0] - t[0][0], t[1][1] - t[0][1], t[1][2] - t[0][2]], [t[2][0] - t[0][0], t[2][1] - t[0][1], t[2][2] - t[0][2]]));
            t.forEach(function (p) { pos.push(p[0], p[1], p[2]); nrm.push(n[0], n[1], n[2]); });
        });
        return { pos: new Float32Array(pos), nrm: new Float32Array(nrm), count: pos.length / 3 };
    }
    function ico(r) {
        var b = icoBase(), tris = [];
        b.f.forEach(function (f) {
            var a = b.v[f[0]], c = b.v[f[1]], d = b.v[f[2]];
            var ab = norm([(a[0] + c[0]) / 2, (a[1] + c[1]) / 2, (a[2] + c[2]) / 2]), cd = norm([(c[0] + d[0]) / 2, (c[1] + d[1]) / 2, (c[2] + d[2]) / 2]), da = norm([(d[0] + a[0]) / 2, (d[1] + a[1]) / 2, (d[2] + a[2]) / 2]);
            [[a, ab, da], [ab, c, cd], [da, cd, d], [ab, cd, da]].forEach(function (t) { tris.push(t.map(function (p) { return [p[0] * r, p[1] * r, p[2] * r]; })); });
        });
        return flat(tris);
    }
    function torus(R, r, seg, side) {
        var pos = [], nrm = [];
        function pt(i, j) {
            var u = i / seg * Math.PI * 2, v = j / side * Math.PI * 2, cx = Math.cos(u) * R, cz = Math.sin(u) * R;
            var n = [Math.cos(u) * Math.cos(v), Math.sin(v), Math.sin(u) * Math.cos(v)];
            return [[cx + n[0] * r, n[1] * r, cz + n[2] * r], n];
        }
        for (var i = 0; i < seg; i++) for (var j = 0; j < side; j++) {
            var a = pt(i, j), b = pt(i + 1, j), c = pt(i + 1, j + 1), d = pt(i, j + 1);
            [a, b, c, a, c, d].forEach(function (p) { pos.push.apply(pos, p[0]); nrm.push.apply(nrm, p[1]); });
        }
        return { pos: new Float32Array(pos), nrm: new Float32Array(nrm), count: pos.length / 3 };
    }
    function cube(s) {
        var p = [[-s, -s, -s], [s, -s, -s], [s, s, -s], [-s, s, -s], [-s, -s, s], [s, -s, s], [s, s, s], [-s, s, s]];
        var q = [[4, 5, 6, 7], [1, 0, 3, 2], [5, 1, 2, 6], [0, 4, 7, 3], [7, 6, 2, 3], [0, 1, 5, 4]], tris = [];
        q.forEach(function (f) { tris.push([p[f[0]], p[f[1]], p[f[2]]], [p[f[0]], p[f[2]], p[f[3]]]); });
        return flat(tris);
    }
    function icoEdges(r) {
        var b = icoBase(), seen = {}, pos = [];
        b.f.forEach(function (f) {
            [[f[0], f[1]], [f[1], f[2]], [f[2], f[0]]].forEach(function (e) {
                var k = Math.min(e[0], e[1]) + '-' + Math.max(e[0], e[1]);
                if (seen[k]) return; seen[k] = 1;
                pos.push(b.v[e[0]][0] * r, b.v[e[0]][1] * r, b.v[e[0]][2] * r, b.v[e[1]][0] * r, b.v[e[1]][1] * r, b.v[e[1]][2] * r);
            });
        });
        return { pos: new Float32Array(pos), nrm: null, count: pos.length / 3, lines: true, verts: b.v.map(function (v) { return [v[0] * r, v[1] * r, v[2] * r]; }) };
    }

    /* ---------------- GL setup ---------------- */
    function shader(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
    var prog = gl.createProgram();
    gl.attachShader(prog, shader(gl.VERTEX_SHADER, 'attribute vec3 aPos;attribute vec3 aNrm;uniform mat4 uM,uVP;varying vec3 vN,vW;void main(){vec4 w=uM*vec4(aPos,1.0);vW=w.xyz;vN=mat3(uM)*aNrm;gl_Position=uVP*w;}'));
    gl.attachShader(prog, shader(gl.FRAGMENT_SHADER, 'precision mediump float;uniform vec3 uColor,uEye,uLight;uniform float uGlow,uLine,uAlpha;varying vec3 vN,vW;void main(){if(uLine>0.5){gl_FragColor=vec4(uColor*(0.55+uGlow*0.9),uAlpha*(1.0+uGlow));return;}vec3 n=normalize(vN),v=normalize(uEye-vW),l=normalize(uLight),h=normalize(l+v);float d=max(dot(n,l),0.0),s=pow(max(dot(n,h),0.0),48.0),hm=0.5+0.5*n.y,rim=pow(1.0-max(dot(n,v),0.0),3.0);vec3 amb=mix(vec3(0.05,0.06,0.14),vec3(0.24,0.26,0.46),hm);vec3 c=uColor*(amb+d*0.9)+s*0.55+rim*vec3(0.45,0.5,1.0)*0.55;c+=uColor*uGlow*0.5+rim*uGlow*0.9;gl_FragColor=vec4(c,uAlpha);}'));
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) { demo.report('webgl-link'); return; }
    gl.useProgram(prog);
    var A = { pos: gl.getAttribLocation(prog, 'aPos'), nrm: gl.getAttribLocation(prog, 'aNrm') };
    var U = {};
    ['uM', 'uVP', 'uColor', 'uEye', 'uLight', 'uGlow', 'uLine', 'uAlpha'].forEach(function (n) { U[n] = gl.getUniformLocation(prog, n); });
    function upload(g) {
        g.pb = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, g.pb); gl.bufferData(gl.ARRAY_BUFFER, g.pos, gl.STATIC_DRAW);
        if (g.nrm) { g.nb = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, g.nb); gl.bufferData(gl.ARRAY_BUFFER, g.nrm, gl.STATIC_DRAW); }
        return g;
    }

    var ringPts = function (R) { var out = []; for (var i = 0; i < 24; i++) { var a = i / 24 * Math.PI * 2; out.push([Math.cos(a) * R, 0, Math.sin(a) * R]); } return out; };
    var parts = [
        { name: 'コア', geo: upload(ico(0.95)), color: [0.62, 0.55, 1.0], explode: [0, 3.2, 0], tilt: m4(), spin: 0.12, pick: [[0, 0, 0]], radius: 0.95 },
        { name: '内側のリング', geo: upload(torus(1.55, 0.07, 72, 10)), color: [0.37, 0.92, 0.83], explode: [-3.6, 0.4, 0], tilt: mul(rx(1.12), rz(0.25)), spin: 0.5, pick: ringPts(1.55) },
        { name: '外側のリング', geo: upload(torus(1.98, 0.045, 96, 8)), color: [0.98, 0.66, 0.83], explode: [3.6, -0.4, 0], tilt: mul(rx(0.38), rz(-0.9)), spin: -0.32, pick: ringPts(1.98) },
        { name: 'サテライト', geo: upload(cube(0.17)), color: [0.98, 0.75, 0.14], explode: [0, -3.4, 0.5], tilt: rx(0.2), spin: 0.6, sats: 6, orbit: 2.4 },
        { name: 'フレーム', geo: upload(icoEdges(2.95)), color: [0.58, 0.77, 0.99], explode: [0, 0, -4.5], tilt: m4(), spin: 0.05, alpha: 0.38 }
    ];
    parts[3].pick = []; for (var si = 0; si < 6; si++) { var sa = si / 6 * Math.PI * 2; parts[3].pick.push([Math.cos(sa) * 2.4, 0, Math.sin(sa) * 2.4]); }
    parts[4].pick = parts[4].geo.verts;

    /* ---------------- state ---------------- */
    var reduced = demo.isReduced();
    var yaw = 0.6, pitch = -0.18, vyaw = 0, vpitch = 0, dragging = false, lastX = 0, lastY = 0;
    var start = performance.now(), clock = 0, last = start, hover = -1, cardHover = -1, glow = [0, 0, 0, 0, 0];
    var visible = true, raf = 0, dirty = true, scrollP = 0;
    var assembled = reduced;
    var W = 1, H = 1, VP = m4(), eye = [0, 0, 8];
    document.documentElement.classList.add('gl');

    function resize() {
        var dpr = Math.min(window.devicePixelRatio || 1, 2), w = Math.round(canvas.clientWidth * dpr), h = Math.round(canvas.clientHeight * dpr);
        if (w && h && (w !== canvas.width || h !== canvas.height)) { canvas.width = w; canvas.height = h; }
        W = canvas.width; H = canvas.height; dirty = true;
    }
    function partModels(p, i, t, world) {
        var a = assembled ? 1 : ease((t - i * 0.16) / 1.3);
        var off = 1 - a, s = 0.55 + 0.45 * a;
        var base = mul(world, mul(tr(p.explode[0] * off, p.explode[1] * off, p.explode[2] * off), mul(sc(s), mul(p.tilt, ry((reduced ? 0 : t) * p.spin + off * 2.5)))));
        if (!p.sats) return [base];
        var out = [];
        for (var k = 0; k < p.sats; k++) { var ang = k / p.sats * Math.PI * 2; out.push(mul(base, mul(ry(ang), mul(tr(p.orbit, 0, 0), ry((reduced ? 0 : t) * 1.4 + k))))); }
        return out;
    }
    function camera() {
        var sp = reduced ? 0 : scrollP;
        var dist = 8.4 - 2.6 * sp, elev = 0.16 + 0.5 * sp;
        eye = [0, Math.sin(elev) * dist, Math.cos(elev) * dist];
        VP = mul(persp(0.72, W / H, 0.1, 50), lookAt(eye, [0, 0, 0], [0, 1, 0]));
    }
    function draw(t) {
        resize(); camera();
        gl.viewport(0, 0, W, H);
        gl.clearColor(0, 0, 0, 0); gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
        gl.enable(gl.DEPTH_TEST); gl.enable(gl.BLEND); gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);
        gl.uniformMatrix4fv(U.uVP, false, VP); gl.uniform3fv(U.uEye, eye); gl.uniform3fv(U.uLight, [0.6, 0.9, 0.7]);
        var world = mul(rx(pitch), ry(yaw));
        parts.forEach(function (p, i) {
            var target = (i === hover || i === cardHover) ? 1 : 0;
            glow[i] += (target - glow[i]) * 0.2;
            gl.uniform3fv(U.uColor, p.color); gl.uniform1f(U.uGlow, glow[i]); gl.uniform1f(U.uAlpha, p.alpha || 1); gl.uniform1f(U.uLine, p.geo.lines ? 1 : 0);
            gl.bindBuffer(gl.ARRAY_BUFFER, p.geo.pb); gl.enableVertexAttribArray(A.pos); gl.vertexAttribPointer(A.pos, 3, gl.FLOAT, false, 0, 0);
            if (p.geo.nb) { gl.bindBuffer(gl.ARRAY_BUFFER, p.geo.nb); gl.enableVertexAttribArray(A.nrm); gl.vertexAttribPointer(A.nrm, 3, gl.FLOAT, false, 0, 0); }
            else { gl.disableVertexAttribArray(A.nrm); gl.vertexAttrib3f(A.nrm, 0, 1, 0); }
            gl.depthMask(!p.geo.lines);
            p.models = partModels(p, i, t, world);
            p.models.forEach(function (m) { gl.uniformMatrix4fv(U.uM, false, m); gl.drawArrays(p.geo.lines ? gl.LINES : gl.TRIANGLES, 0, p.geo.count); });
        });
        gl.depthMask(true);
    }
    function moving(t) {
        if (dragging || Math.abs(vyaw) > 1e-4 || Math.abs(vpitch) > 1e-4) return true;
        if (!assembled && t < 2.2) return true;
        for (var i = 0; i < glow.length; i++) { var tg = (i === hover || i === cardHover) ? 1 : 0; if (Math.abs(glow[i] - tg) > 0.01) return true; }
        return !reduced;
    }
    function tick(now) {
        raf = 0;
        var dt = Math.min(0.05, (now - last) / 1000); last = now;
        if (!reduced) clock += dt;
        if (!dragging) {
            if (!reduced) yaw += dt * 0.25;
            yaw += vyaw; pitch = Math.max(-0.9, Math.min(0.9, pitch + vpitch));
            vyaw *= 0.92; vpitch *= 0.92;
        }
        if (!assembled && (now - start) / 1000 > 2.2) assembled = true;
        draw(assembled ? clock : (now - start) / 1000);
        dirty = false;
        if (moving((now - start) / 1000)) loop();
    }
    function loop() { if (!raf && visible && !demo.isHidden()) { last = performance.now(); raf = window.requestAnimationFrame(tick); } }
    function stop() { if (raf) { window.cancelAnimationFrame(raf); raf = 0; } }
    function poke() { dirty = true; loop(); }

    /* ---------------- picking ---------------- */
    function pick(cx, cy) {
        var r = canvas.getBoundingClientRect(), best = -1, bestD = Infinity, sx = W / r.width;
        var px = (cx - r.left) * sx, py = (cy - r.top) * sx, focal = H / 2 / Math.tan(0.36);
        parts.forEach(function (p, i) {
            if (!p.models) return;
            p.models.forEach(function (m, k) {
                var pts = p.sats ? [[0, 0, 0]] : p.pick;
                pts.forEach(function (pt) {
                    var c = xf(VP, xf(m, pt));
                    if (c[3] <= 0) return;
                    var x = (c[0] / c[3] * 0.5 + 0.5) * W, y = (0.5 - c[1] / c[3] * 0.5) * H;
                    var tol = p.radius ? p.radius * focal / c[3] : 22 * sx;
                    var d = Math.hypot(x - px, y - py) - tol + c[3] * 0.5; // nearer parts win ties
                    if (Math.hypot(x - px, y - py) < tol && d < bestD) { bestD = d; best = i; }
                });
                void k;
            });
        });
        return best;
    }
    function setHover(i) {
        if (i === hover) return;
        hover = i;
        label.textContent = i >= 0 ? parts[i].name : '';
        label.classList.toggle('is-on', i >= 0);
        poke();
    }

    /* ---------------- input ---------------- */
    canvas.addEventListener('pointerdown', function (e) { dragging = true; lastX = e.clientX; lastY = e.clientY; vyaw = vpitch = 0; canvas.setPointerCapture(e.pointerId); poke(); });
    canvas.addEventListener('pointermove', function (e) {
        if (dragging) {
            var dx = e.clientX - lastX, dy = e.clientY - lastY; lastX = e.clientX; lastY = e.clientY;
            yaw += dx * 0.008; pitch = Math.max(-0.9, Math.min(0.9, pitch + dy * 0.006)); vyaw = dx * 0.002; vpitch = dy * 0.0015; poke();
        } else if (e.pointerType === 'mouse') { setHover(pick(e.clientX, e.clientY)); }
    });
    function release() { dragging = false; poke(); }
    canvas.addEventListener('pointerup', release); canvas.addEventListener('pointercancel', release);
    canvas.addEventListener('pointerleave', function () { if (!dragging) setHover(-1); });
    canvas.addEventListener('keydown', function (e) {
        var k = { ArrowLeft: [-0.15, 0], ArrowRight: [0.15, 0], ArrowUp: [0, -0.12], ArrowDown: [0, 0.12] }[e.key];
        if (!k) return;
        e.preventDefault(); yaw += k[0]; pitch = Math.max(-0.9, Math.min(0.9, pitch + k[1])); poke();
    });
    Array.prototype.forEach.call(document.querySelectorAll('.kd-card'), function (card) {
        var i = Number(card.getAttribute('data-part'));
        function on() { cardHover = i; card.classList.add('is-on'); poke(); }
        function off() { if (cardHover === i) cardHover = -1; card.classList.remove('is-on'); poke(); }
        card.addEventListener('pointerenter', on); card.addEventListener('pointerleave', off);
        card.addEventListener('focus', on); card.addEventListener('blur', off);
    });
    var hero = document.querySelector('.kd-hero');
    window.addEventListener('scroll', function () {
        var p = Math.max(0, Math.min(1, window.scrollY / Math.max(1, hero.offsetHeight * 0.8)));
        if (Math.abs(p - scrollP) > 0.001) { scrollP = p; if (!reduced) poke(); }
    }, { passive: true });
    window.addEventListener('resize', poke);
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) { visible = entries[entries.length - 1].isIntersecting; if (visible) poke(); else stop(); }).observe(canvas);
    }
    demo.onVisibility(function (hidden) { if (hidden) stop(); else poke(); });
    demo.onMotion(function (r) { reduced = r; if (r) assembled = true; poke(); });
    canvas.addEventListener('webglcontextlost', function (e) { e.preventDefault(); stop(); document.documentElement.classList.remove('gl'); });
    poke();
})();
