/* Isolation probe (TEST ONLY). Every attempt here must fail inside the sandbox (B3-B6, B8). */
(function () {
    'use strict';
    var SITE = location.protocol + '//' + location.host; // the URL's host; the document's origin is opaque
    var r = { done: false, inFrame: window.parent !== window };
    window.__probe = r;
    window.__probeMsgs = [];
    function attempt(name, fn) { try { r[name] = fn(); } catch (e) { r[name] = 'threw:' + (e && e.name || 'Error'); } }

    attempt('origin', function () { return String(self.origin); });
    attempt('cookieRead', function () { return 'read:' + document.cookie; });
    attempt('cookieWrite', function () { document.cookie = 'probe=1; path=/'; return 'wrote'; });
    attempt('localStorage', function () { return 'len:' + window.localStorage.length; });
    attempt('sessionStorage', function () { return 'len:' + window.sessionStorage.length; });
    attempt('indexedDB', function () { var q = window.indexedDB.open('probe'); return q ? 'opened' : 'none'; });
    attempt('parentDocument', function () { return r.inFrame ? 'title:' + window.parent.document.title : 'no-parent'; });
    attempt('topDocument', function () { return r.inFrame ? 'title:' + window.top.document.title : 'no-parent'; });
    attempt('eval', function () { return 'ran:' + eval('1+1'); });
    attempt('newFunction', function () { return 'ran:' + (new Function('return 2'))(); });
    attempt('inlineScript', function () {
        window.__probeInjected = false;
        var s = document.createElement('script'); s.textContent = 'window.__probeInjected = true;'; document.body.appendChild(s);
        return 'injected:' + window.__probeInjected;
    });
    attempt('windowOpen', function () { var w = window.open(SITE + '/'); return w ? 'opened' : 'null'; });
    attempt('alert', function () { var t = Date.now(); window.alert('probe'); return 'returned:' + (Date.now() - t < 1000); });
    attempt('formSubmit', function () { document.getElementById('probe-form').submit(); return 'called'; });
    attempt('imgBeacon', function () { document.getElementById('probe-img').src = SITE + '/wp-json/?probe=img'; return 'set'; });
    attempt('sendBeacon', function () { return 'sent:' + navigator.sendBeacon(SITE + '/wp-json/?probe=beacon', 'x'); });
    attempt('webSocket', function () { var ws = new WebSocket('ws://' + location.host + '/?probe=ws'); ws.onerror = function () { r.webSocketError = true; }; return 'created'; });

    function req(name, url, opts) {
        return fetch(url, opts).then(function (res) { r[name] = 'status:' + res.status; }, function (e) { r[name] = 'rejected:' + (e && e.name); });
    }
    function xhr() {
        return new Promise(function (resolve) {
            try {
                var x = new XMLHttpRequest();
                x.open('GET', SITE + '/wp-json/wp/v2/users/me?probe=xhr');
                x.onload = function () { r.xhr = 'status:' + x.status; resolve(); };
                x.onerror = function () { r.xhr = 'error'; resolve(); };
                x.send();
            } catch (e) { r.xhr = 'threw:' + e.name; resolve(); }
        });
    }
    Promise.all([
        req('fetchRoot', SITE + '/wp-json/?probe=fetch'),
        req('fetchMe', SITE + '/wp-json/wp/v2/users/me?probe=fetch', { credentials: 'include' }),
        req('fetchPost', SITE + '/wp-json/wp/v2/posts?probe=fetch', { method: 'POST', credentials: 'include', body: '{}' }),
        xhr()
    ]).then(function () {
        window.setTimeout(function () {
            r.done = true;
            document.getElementById('probe-out').textContent = JSON.stringify(r, null, 1);
        }, 600);
    });

    // B8: record what reaches the demo; the runtime decides what is applied.
    window.addEventListener('message', function (e) {
        window.__probeMsgs.push({ fromParent: e.source === window.parent, origin: e.origin, type: e.data && e.data.type, theme: document.documentElement.getAttribute('data-theme') });
    });
    // B7: the tests call this to send messages the parent must ignore.
    window.__probeSendBad = function () {
        if (!r.inFrame) return 0;
        var bad = [
            { type: 'omx-design:navigate', url: SITE + '/wp-admin/' },
            { type: '<img src=x onerror=alert(1)>' },
            { type: 'omx-design:ready', html: '<b>x</b>', status: '<scr' + 'ipt>alert(1)</scr' + 'ipt>' },
            '<scr' + 'ipt>alert(1)</scr' + 'ipt>',
            ['omx-design:error'],
            null
        ];
        bad.forEach(function (m) { window.parent.postMessage(m, '*'); });
        for (var i = 0; i < 500; i++) window.parent.postMessage({ type: 'omx-design:flood', i: i }, '*');
        return bad.length + 500;
    };
    window.__probeSendError = function () { window.parent.postMessage({ type: 'omx-design:error', code: 'probe' }, '*'); };
})();
