/**
 * Learn v2 / Phase 4: practice list and single page interactions.
 *
 * - Copy buttons ([data-practice-copy="<id>"]) copy an element's text and
 *   announce the result in the nearest [data-practice-copy-status].
 * - The AI brief switches between assistants ([data-practice-assistant])
 *   and can be saved as a .md file ([data-practice-download]).
 * - Verification checkboxes are remembered per item in localStorage.
 * - The outline highlights the section in view.
 * - List selects submit the form on change (the submit button is hidden).
 * - A YouTube preview is embedded only after the visitor clicks it.
 * Everything works without this file: forms submit, links navigate.
 */
(function () {
    'use strict';

    var config = window.omochixLearnPractice || {};
    var strings = config.strings || {};

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            var copied = false;
            try { copied = document.execCommand('copy'); } catch (error) { copied = false; }
            area.remove();
            copied ? resolve() : reject();
        });
    }

    function statusFor(button) {
        var scope = button.closest('[data-practice-copy-scope]') || button.parentElement;
        for (var el = scope; el; el = el.parentElement) {
            var live = el.querySelector('[data-practice-copy-status]');
            if (live) return live;
        }
        return null;
    }

    function flash(button, label, ok) {
        if (!button.dataset.practiceLabel) button.dataset.practiceLabel = button.textContent;
        button.textContent = label;
        button.classList.toggle('is-done', ok);
        var live = statusFor(button);
        if (live) live.textContent = label;
        window.clearTimeout(button.practiceTimer);
        button.practiceTimer = window.setTimeout(function () {
            button.textContent = button.dataset.practiceLabel;
            button.classList.remove('is-done');
        }, 2000);
    }

    document.addEventListener('click', function (event) {
        var copy = event.target.closest('[data-practice-copy]');
        if (copy) {
            var target = document.getElementById(copy.dataset.practiceCopy || '');
            if (!target) return;
            copyText(target.textContent).then(function () { flash(copy, strings.copied || 'Copied', true); })
                .catch(function () { flash(copy, strings.copyFailed || 'Copy failed', false); });
            return;
        }
        var download = event.target.closest('[data-practice-download]');
        if (download) {
            var source = document.getElementById(download.dataset.practiceDownload || '');
            if (!source) return;
            var blob = new Blob([source.textContent], { type: 'text/markdown;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = download.dataset.practiceFilename || 'brief.md';
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        }
    });

    /* Brief: assistant switch (the PHP-rendered texts are carried as data attributes). */
    var briefText = document.querySelector('[data-practice-brief-text]');
    if (briefText) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-practice-assistant]'), function (radio) {
            radio.addEventListener('change', function () {
                var text = briefText.getAttribute('data-brief-' + radio.value);
                if (radio.checked && text) briefText.textContent = text;
            });
        });
    }

    /* Verification checklist, remembered per item in this browser. */
    var root = document.querySelector('[data-practice-id]');
    var checks = Array.prototype.slice.call(document.querySelectorAll('[data-practice-check]'));
    if (root && checks.length) {
        var key = 'omx-practice-verify-' + root.dataset.practiceId;
        var state = {};
        try { state = JSON.parse(window.localStorage.getItem(key) || '{}') || {}; } catch (error) { state = {}; }
        checks.forEach(function (box) {
            box.checked = !!state[box.dataset.practiceCheck];
            box.closest('tr').classList.toggle('is-done', box.checked);
            box.addEventListener('change', function () {
                state[box.dataset.practiceCheck] = box.checked;
                box.closest('tr').classList.toggle('is-done', box.checked);
                try { window.localStorage.setItem(key, JSON.stringify(state)); } catch (error) { /* storage unavailable */ }
            });
        });
    }

    /* Outline: highlight the section in view. */
    var outline = document.querySelector('[data-practice-outline]');
    if (outline && 'IntersectionObserver' in window) {
        var links = {};
        Array.prototype.forEach.call(outline.querySelectorAll('a[href^="#"]'), function (a) { links[a.getAttribute('href').slice(1)] = a; });
        var sections = Object.keys(links).map(function (id) { return document.getElementById(id); }).filter(Boolean);
        var observer = new window.IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                Object.keys(links).forEach(function (id) { links[id].classList.toggle('is-current', id === entry.target.id); });
            });
        }, { rootMargin: '-20% 0px -70% 0px' });
        sections.forEach(function (section) { observer.observe(section); });
    }

    /* YouTube: embed on demand. */
    var yt = document.querySelector('[data-practice-youtube]');
    if (yt) {
        var link = yt.querySelector('a');
        if (link) link.addEventListener('click', function (event) {
            event.preventDefault();
            var iframe = document.createElement('iframe');
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(yt.dataset.practiceYoutube) + '?autoplay=1';
            iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
            iframe.setAttribute('allowfullscreen', '');
            iframe.setAttribute('title', link.textContent.trim());
            yt.replaceChild(iframe, link);
        });
    }

    /* List: selects submit on change; the submit button is only for no-JS. */
    var filter = document.querySelector('[data-practice-filter]');
    if (filter) {
        filter.classList.add('practice-filter--js');
        // Empty fields are left out of the URL (same shape as the PHP url builder).
        filter.addEventListener('submit', function () {
            Array.prototype.forEach.call(filter.querySelectorAll('input[name], select[name]'), function (field) {
                if ('' === String(field.value).trim()) field.disabled = true;
            });
        });
        Array.prototype.forEach.call(filter.querySelectorAll('[data-practice-auto]'), function (select) {
            select.addEventListener('change', function () { filter.requestSubmit ? filter.requestSubmit() : filter.submit(); });
        });
    }
}());
