/* Micro Interaction Collection (fictional "HAKARI"). Feedback for settings, input checks, submit states and
   undo. Nothing is sent or stored; user text is only ever set with textContent. Timers wait while the demo is
   paused or hidden; reduced motion keeps every state and drops the movement. */
(function () {
    'use strict';
    var demo = window.omxDemo;
    var app = document.querySelector('[data-mi]');
    if (!app) return;
    var $ = function (s, el) { return (el || app).querySelector(s); };
    var $$ = function (s, el) { return Array.prototype.slice.call((el || app).querySelectorAll(s)); };
    var calm = function () { return demo.isReduced(); };
    var held = [];
    function later(fn, ms) { setTimeout(function () { if (demo.isHidden()) held.push(fn); else fn(); }, ms); }
    demo.onVisibility(function (hidden) { if (!hidden) { var q = held; held = []; q.forEach(function (fn) { later(fn, 60); }); } });
    function yen(n) { return '¥' + n.toLocaleString('ja-JP'); }
    function restart(el, cls) { el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls); }

    /* 01 settings: every change shows a short "saved" mark */
    var saved = $('[data-mi-saved]'), savedTimer = 0;
    function label(input) {
        if (input.type === 'radio') return '月の始まり：' + $('label[for="' + input.id + '"]').textContent;
        if (input.type === 'range') return '知らせる割合：' + input.value + '%';
        return $('label[for="' + input.id + '"] .mi-row__t').textContent + '：' + (input.checked ? 'オン' : 'オフ');
    }
    $$('[data-mi-setting]').forEach(function (input) {
        input.addEventListener('change', function () {
            saved.textContent = '保存しました（' + label(input) + '）';
            saved.classList.add('is-on');
            var mine = ++savedTimer;
            later(function () { if (mine === savedTimer) saved.classList.remove('is-on'); }, 2200);
        });
    });
    var range = $('[data-mi-range]'), out = $('[data-mi-range-out]');
    range.addEventListener('input', function () { out.textContent = range.value + '%'; if (!calm()) restart(out, 'is-bump'); });
    out.addEventListener('animationend', function () { out.classList.remove('is-bump'); });
    range.addEventListener('change', function () { out.classList.remove('is-bump'); });

    /* 02 entry: amount check, memo counter, submit states */
    var amountField = $('[data-mi-field="amount"]'), amount = $('[data-mi-amount]'), amountMsg = $('#mi-amount-msg');
    var HELP = amountMsg.textContent;
    function parse() { var d = amount.value.replace(/[,，\s円¥]/g, ''); return /^[0-9０-９]+$/.test(d) ? Number(d.replace(/[０-９]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 0xFEE0); })) : NaN; }
    function check(loud) {
        var n = parse(), ok = n >= 1 && n <= 1000000;
        amountField.classList.toggle('is-valid', ok);
        amountField.classList.toggle('is-invalid', !ok);
        amount.setAttribute('aria-invalid', String(!ok));
        if (ok) { amount.value = n.toLocaleString('ja-JP'); amountMsg.textContent = HELP; }
        else {
            amountMsg.textContent = amount.value.trim() === '' ? '金額を入力してください' : '1 円から 1,000,000 円までの数字で入力してください';
            if (loud && !calm()) restart(amountField, 'is-shake');
        }
        return ok ? n : 0;
    }
    amount.addEventListener('blur', function () { check(true); });
    amount.addEventListener('input', function () { amountField.classList.remove('is-valid', 'is-shake'); if (amountField.classList.contains('is-invalid') && parse() >= 1 && parse() <= 1000000) check(false); });
    amount.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); check(true); } });
    check(false);
    var memo = $('[data-mi-memo]'), count = $('[data-mi-count]');
    function counter() { var n = Array.from(memo.value).length; count.textContent = n + ' / 40 文字' + (n >= 34 ? '（残り ' + (40 - n) + ' 文字）' : ''); count.classList.toggle('is-near', n >= 34); }
    memo.addEventListener('input', counter); counter();

    var btn = $('[data-mi-submit]'), btnLabel = $('[data-mi-submit-label]'), result = $('[data-mi-result]'), fail = $('[data-mi-fail]');
    function state(s, text) { btn.setAttribute('data-state', s); btnLabel.textContent = text; if (s === 'loading') btn.setAttribute('aria-busy', 'true'); else btn.removeAttribute('aria-busy'); }
    function tell(text, kind) { result.textContent = text; result.className = 'mi-result' + (kind ? ' is-' + kind : ''); }
    btn.addEventListener('click', function () {
        var st = btn.getAttribute('data-state');
        if (st === 'loading' || st === 'success') return;
        var n = check(true);
        if (!n) { tell('金額を確認してください', 'err'); amount.focus(); return; }
        state('loading', '記録中…'); tell('記録しています…', '');
        later(function () {
            if (fail.checked) { state('error', 'もう一度試す'); tell('通信できませんでした（見本のエラー）。もう一度お試しください。', 'err'); return; }
            state('success', '記録しました');
            tell(n.toLocaleString('ja-JP') + ' 円を記録しました（見本）', 'ok');
            add(n, memo.value.trim() || '（メモなし）', $('#mi-cat').value);
            later(function () { state('idle', '記録する'); }, 2200);
        }, calm() ? 250 : 1100);
    });

    /* 03 list: paid / unpaid changes the total; delete can be undone for 5 seconds */
    var list = $('[data-mi-items]'), total = $('[data-mi-total]'), shown = unpaid(), seq = 3;
    function unpaid() { return $$('.mi-item', list).reduce(function (s, li) { return s + (li.querySelector('[data-mi-paid]').checked ? 0 : Number(li.getAttribute('data-amount'))); }, 0); }
    function update() {
        var to = unpaid(), from = shown;
        shown = to;
        if (calm() || demo.isHidden() || from === to) { total.textContent = yen(to); return; }
        restart(total, 'is-tick');
        var t0 = 0;
        (function step(now) {
            if (demo.isHidden()) { total.textContent = yen(to); return; }
            if (!t0) t0 = now;
            var p = Math.min(1, (now - t0) / 420), e = 1 - Math.pow(1 - p, 3);
            total.textContent = yen(Math.round(from + (to - from) * e));
            if (p < 1 && shown === to) requestAnimationFrame(step);
        })(performance.now());
    }
    list.addEventListener('change', function (e) { if (e.target.matches('[data-mi-paid]')) update(); });
    function add(n, text, cat) {
        var id = 'mi-it-' + (++seq);
        var li = document.createElement('li'); li.className = 'mi-item'; li.setAttribute('data-amount', String(n));
        var cb = document.createElement('input'); cb.className = 'mi-check'; cb.type = 'checkbox'; cb.id = id; cb.setAttribute('data-mi-paid', '');
        var lb = document.createElement('label'); lb.htmlFor = id;
        var t = document.createElement('span'); t.className = 'mi-item__t'; t.textContent = text;
        var d = document.createElement('span'); d.className = 'mi-item__d'; d.textContent = cat + ' · 今日';
        lb.appendChild(t); lb.appendChild(d);
        var amt = document.createElement('span'); amt.className = 'mi-item__amt'; amt.textContent = yen(n);
        var del = list.querySelector('[data-mi-del]').cloneNode(true); del.setAttribute('aria-label', text + 'を削除');
        [cb, lb, amt, del].forEach(function (x) { li.appendChild(x); });
        list.insertBefore(li, list.firstChild);
        update();
    }
    var toast = $('[data-mi-toast]'), toastText = $('[data-mi-toast-text]'), undo = $('[data-mi-undo]'), bar = $('[data-mi-toast-bar]');
    var removed = null, toastId = 0;
    function closeToast() { toast.hidden = true; removed = null; }
    list.addEventListener('click', function (e) {
        var del = e.target.closest('[data-mi-del]');
        if (!del) return;
        var li = del.closest('.mi-item');
        var focusNext = (li.nextElementSibling || li.previousElementSibling);
        var name = li.querySelector('.mi-item__t').textContent;
        li.classList.add('is-leaving');
        later(function () {
            removed = { li: li, next: li.nextElementSibling };
            li.remove(); update();
            if (focusNext && focusNext.isConnected) focusNext.querySelector('[data-mi-paid]').focus(); else $('#mi-list-h').setAttribute('tabindex', '-1'), $('#mi-list-h').focus();
            toastText.textContent = '「' + name + '」を削除しました';
            toast.hidden = false;
            if (!calm()) { restart(toast, 'is-in'); bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = ''; }
            var mine = ++toastId;
            later(function () { if (mine === toastId && !toast.hidden) closeToast(); }, 5000);
        }, calm() ? 0 : 200);
    });
    undo.addEventListener('click', function () {
        if (!removed) return;
        var r = removed;
        r.li.classList.remove('is-leaving');
        list.insertBefore(r.li, r.next && r.next.isConnected ? r.next : null);
        closeToast(); toastId++;
        update();
        r.li.querySelector('[data-mi-paid]').focus();
    });
    toast.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeToast(); toastId++; } });
})();
