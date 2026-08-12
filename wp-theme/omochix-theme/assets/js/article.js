/**
 * Minimal article interactions.
 */
(function () {
    'use strict';

    var copyButton = document.querySelector('[data-copy-url]');
    var copyStatus = document.querySelector('[data-copy-status]');

    if (!copyButton) return;

    function copyUrl(url) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(url);
        }

        return new Promise(function (resolve, reject) {
            var temporaryInput = document.createElement('textarea');
            temporaryInput.value = url;
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

    copyButton.addEventListener('click', function () {
        copyUrl(copyButton.dataset.copyUrl).then(function () {
            copyButton.textContent = 'コピーしました';
            if (copyStatus) copyStatus.textContent = '記事のURLをコピーしました。';
            window.setTimeout(function () {
                copyButton.textContent = 'URLをコピー';
            }, 2000);
        }).catch(function () {
            if (copyStatus) copyStatus.textContent = 'URLをコピーできませんでした。';
        });
    });
}());
