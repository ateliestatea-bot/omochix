/**
 * Prompt detail interactions: copy prompt body to clipboard.
 */
(function () {
    'use strict';

    var copyButton = document.querySelector('[data-copy-prompt]');
    var promptText = document.querySelector('[data-prompt-text]');
    var copyStatus = document.querySelector('[data-copy-status]');

    if (!copyButton || !promptText) return;

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

    var originalLabel = copyButton.textContent;

    copyButton.addEventListener('click', function () {
        copyText(promptText.textContent).then(function () {
            copyButton.textContent = 'コピーしました';
            if (copyStatus) copyStatus.textContent = 'プロンプトをコピーしました。';
            window.setTimeout(function () {
                copyButton.textContent = originalLabel;
            }, 2000);
        }).catch(function () {
            if (copyStatus) copyStatus.textContent = 'プロンプトをコピーできませんでした。';
        });
    });
}());
