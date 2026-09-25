/**
 * Prompt copy interactions.
 *
 * Supports any number of copy buttons per page. Each [data-copy-prompt]
 * button copies the [data-prompt-text] element it points at, resolved in
 * this order:
 *   1. data-copy-target="<id>" on the button
 *   2. the nearest [data-prompt-copy-scope] ancestor's [data-prompt-text]
 *   3. the page's only [data-prompt-text] (single-prompt.php before scopes)
 * Status messages go to the matching [data-copy-status] the same way.
 */
(function () {
    'use strict';

    var copyButtons = document.querySelectorAll('[data-copy-prompt]');
    if (!copyButtons.length) return;

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var temporaryInput = document.createElement('textarea');
            temporaryInput.value = text;
            temporaryInput.setAttribute('readonly', '');
            temporaryInput.style.position = 'fixed';
            temporaryInput.style.opacity = '0';
            document.body.appendChild(temporaryInput);
            temporaryInput.select();
            var copied = document.execCommand('copy');
            temporaryInput.remove();
            copied ? resolve() : reject();
        });
    }

    function resolveWithin(button, selector) {
        var scope = button.closest('[data-prompt-copy-scope]');
        if (scope) return scope.querySelector(selector);

        var matches = document.querySelectorAll(selector);
        return matches.length === 1 ? matches[0] : null;
    }

    function resolveText(button) {
        var targetId = button.getAttribute('data-copy-target');
        if (targetId) return document.getElementById(targetId);
        return resolveWithin(button, '[data-prompt-text]');
    }

    Array.prototype.forEach.call(copyButtons, function (copyButton) {
        var promptText = resolveText(copyButton);
        if (!promptText) return;

        var copyStatus = resolveWithin(copyButton, '[data-copy-status]');
        var originalLabel = copyButton.textContent;
        var resetTimer = null;

        copyButton.addEventListener('click', function () {
            copyText(promptText.textContent).then(function () {
                copyButton.textContent = 'コピーしました';
                if (copyStatus) copyStatus.textContent = 'プロンプトをコピーしました。';
                window.clearTimeout(resetTimer);
                resetTimer = window.setTimeout(function () {
                    copyButton.textContent = originalLabel;
                }, 2000);
            }).catch(function () {
                if (copyStatus) copyStatus.textContent = 'プロンプトをコピーできませんでした。';
            });
        });
    });
}());
