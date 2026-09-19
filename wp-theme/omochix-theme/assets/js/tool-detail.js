/**
 * AI Tools v2.1: in-page anchor nav active-state tracking.
 *
 * The nav links in .tool-detail__tabs are plain <a href="#id"> anchors and
 * already work without JavaScript. This file only adds the "which section
 * am I looking at" highlight as progressive enhancement — removing it
 * changes nothing about navigation itself, only the visual indicator.
 */
(function () {
    'use strict';

    var nav = document.querySelector('.tool-detail__tabs');
    if (!nav) return;

    var links = Array.prototype.slice.call(nav.querySelectorAll('[data-tool-nav-link]'));
    if (!links.length || !window.IntersectionObserver) return;

    var sections = links
        .map(function (link) {
            var id = link.getAttribute('href');
            var section = id ? document.querySelector(id) : null;
            return section ? { link: link, section: section } : null;
        })
        .filter(Boolean);

    if (!sections.length) return;

    function setActive(link) {
        links.forEach(function (item) {
            var isActive = item === link;
            item.classList.toggle('is-active', isActive);
            if (isActive) {
                item.setAttribute('aria-current', 'true');
            } else {
                item.removeAttribute('aria-current');
            }
        });
    }

    var observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var match = sections.filter(function (item) {
                    return item.section === entry.target;
                })[0];
                if (match) setActive(match.link);
            });
        },
        { rootMargin: '-45% 0px -50% 0px', threshold: 0 }
    );

    sections.forEach(function (item) {
        observer.observe(item.section);
    });

    links.forEach(function (link) {
        link.addEventListener('click', function () {
            setActive(link);
        });
    });
}());
