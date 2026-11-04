/**
 * OmochiX Labs list: filters the server-rendered cards in place as the
 * visitor types or picks a category (the same result the server gives for
 * ?q= / ?cat=, which still works without JavaScript).
 */
(function (window, document) {
    'use strict';

    var L = window.OmochixLabs;
    var Text = window.OmochixLabsText;
    var root = document.querySelector('[data-labs-index]');
    if (!L || !root) return;

    var form = L.$('[data-labs-search]', root);
    var input = L.$('#labs-search-q', root);
    var chips = L.$all('[data-labs-cat]', root);
    var cards = L.$all('[data-labs-card]', root);
    var count = L.$('[data-labs-count]', root);
    var empty = L.$('[data-labs-empty]', root);
    var hiddenCat = L.$('[data-labs-cat-input]', root);
    var current = '';
    chips.forEach(function (chip) { if (chip.getAttribute('aria-current') === 'true') current = chip.getAttribute('data-labs-cat'); });
    var index = cards.map(function (card) { return Text.normalizeSearch(card.getAttribute('data-search')); });
    var timer = null;

    function apply(announce) {
        var terms = Text.normalizeSearch(input ? input.value : '').split(' ').filter(Boolean);
        var shown = 0;
        cards.forEach(function (card, i) {
            var ok = (!current || card.getAttribute('data-category') === current) && terms.every(function (t) { return index[i].indexOf(t) !== -1; });
            card.hidden = !ok;
            if (ok) shown++;
        });
        if (empty) empty.hidden = shown > 0;
        if (count && announce) count.textContent = L.format(L.strings.count, shown);
        updateUrl();
    }

    /** Only the category goes into the URL: typed search words never do (GA4 records page URLs). */
    function updateUrl() {
        if (!window.history || !window.history.replaceState || !form) return;
        var url = new URL(form.getAttribute('action'), window.location.href);
        if (current) url.searchParams.set('cat', current);
        window.history.replaceState(null, '', url.pathname + url.search);
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function (event) {
            event.preventDefault();
            current = chip.getAttribute('data-labs-cat') || '';
            chips.forEach(function (c) {
                if (c === chip) c.setAttribute('aria-current', 'true');
                else c.removeAttribute('aria-current');
            });
            if (hiddenCat) hiddenCat.value = current;
            apply(true);
        });
    });

    if (form) {
        form.hidden = false; // the search box is JavaScript-only, so a search is never a GET request
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            apply(true);
        });
    }
    if (input) {
        input.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () { apply(true); }, 200);
        });
    }
}(window, document));
