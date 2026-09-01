(function () {
    'use strict';

    var form = document.querySelector('.contact-form__form');
    if (!form) {
        return;
    }

    var submitButton = form.querySelector('.contact-form__submit');

    form.addEventListener('submit', function () {
        if (!submitButton || submitButton.disabled) {
            return;
        }
        submitButton.disabled = true;
    });
})();
