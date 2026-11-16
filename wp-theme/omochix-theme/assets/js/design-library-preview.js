/* OmochiX Design Library detail: talks to the sandboxed demo iframe (D0 §3.4 T6/T7).
 * Accepted from the demo: {type: "omx-design:ready"} and {type: "omx-design:error"} only, and only when
 * event.source is the demo iframe's window (its origin is the opaque "null", so the origin alone proves nothing).
 * Sent to the demo: {type: "omx-design:theme", theme: "light" | "dark"} — public values only, never a nonce or user data.
 * Nothing received is written to the page: the status line shows fixed strings. */
(function () {
    'use strict';
    /* Copy buttons: copy the text of an element already on the page (prompt, code). Hidden without JS / clipboard. */
    Array.prototype.forEach.call(document.querySelectorAll('[data-dl-copy]'), function (btn) {
        var target = document.getElementById(btn.getAttribute('data-dl-copy'));
        if (!target || !navigator.clipboard || !window.isSecureContext) return; // 127.0.0.1 and HTTPS are secure contexts
        var label = btn.textContent;
        btn.hidden = false;
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(target.textContent).then(function () { btn.textContent = 'コピーしました'; }, function () { btn.textContent = 'コピーできませんでした'; })
                .then(function () { window.setTimeout(function () { btn.textContent = label; }, 1800); });
        });
    });
})();
(function () {
    'use strict';
    var stage = document.querySelector('[data-dl-stage]');
    var frame = stage && stage.querySelector('[data-dl-demo]');
    if (!frame) return;
    var status = stage.querySelector('[data-dl-status]');
    var TEXT = {
        ready: 'ライブデモはサイト本体から切り離した環境で動いています。',
        error: 'ライブデモでエラーが起きました。「全画面で開く」でも表示できます。'
    };
    function theme() { return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }
    function sendTheme() {
        if (frame.contentWindow) frame.contentWindow.postMessage({ type: 'omx-design:theme', theme: theme() }, '*');
    }
    window.addEventListener('message', function (event) {
        if (event.source !== frame.contentWindow || event.origin !== 'null') return;
        var d = event.data;
        if (!d || typeof d !== 'object' || Array.isArray(d)) return;
        if (d.type === 'omx-design:ready') {
            if (stage.getAttribute('data-state') === 'error') return;
            stage.setAttribute('data-state', 'ready');
            if (status) status.textContent = TEXT.ready;
            sendTheme();
        } else if (d.type === 'omx-design:error') {
            stage.setAttribute('data-state', 'error');
            if (status) status.textContent = TEXT.error;
        }
    });
    if ('MutationObserver' in window) {
        new MutationObserver(sendTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    }
})();
