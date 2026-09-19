/**
 * AI Tools v2.1: in-page anchor nav active-state tracking.
 *
 * The nav links in .tool-detail__tabs are plain <a href="#id"> anchors and
 * already work without JavaScript. This file only adds the "which section
 * am I looking at" highlight as progressive enhancement — removing it
 * changes nothing about navigation itself, only the visual indicator.
 *
 * Scrollspy method: a virtual line is placed just below the sticky site
 * header (and, at desktop/tablet widths, the sticky in-page tabs bar too —
 * on mobile that bar is not sticky, see the max-width:767px override in
 * style.css, so it must not be counted there). The active section is
 * whichever tracked section's top edge is the last one to have scrolled up
 * past that line. This is recomputed from live geometry on every scroll
 * frame, so it is correct continuously — including mid smooth-scroll
 * animation and immediately on arrival — rather than relying on
 * IntersectionObserver's sparse, threshold-crossing entries. An earlier
 * IntersectionObserver-based version could have a later-processed entry in
 * the same callback batch (order not tied to document/scroll position)
 * overwrite the correct section while multiple sections crossed the
 * observed band during a fast animated scroll; this rewrite has no such
 * batch-order dependency because it always re-evaluates every tracked
 * section's current position, not just the ones that just changed state.
 */
(function () {
    'use strict';

    var nav = document.querySelector('.tool-detail__tabs');
    if (!nav) return;

    var links = Array.prototype.slice.call(nav.querySelectorAll('[data-tool-nav-link]'));
    if (!links.length) return;

    // Document order === nav order === array order here, since each link is
    // only rendered when its target section renders, in the same order.
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

    var header = document.querySelector('.site-header');

    function getLineOffset() {
        var offset = 8; // small breathing room, same spirit as the CSS scroll-margin buffer
        if (header) {
            offset += header.getBoundingClientRect().height;
        }
        var tabsPosition = window.getComputedStyle(nav).position;
        if (tabsPosition === 'sticky' || tabsPosition === 'fixed') {
            offset += nav.getBoundingClientRect().height;
        }
        return offset;
    }

    var lineOffset = getLineOffset();

    function recomputeActive() {
        var activeItem = null;
        // Walk in document order and keep the last section whose top has
        // already scrolled up past the line — i.e. the section the line is
        // currently inside of, or the last one passed if between sections.
        for (var i = 0; i < sections.length; i++) {
            var top = sections[i].section.getBoundingClientRect().top;
            if (top <= lineOffset) {
                activeItem = sections[i];
            }
        }
        if (activeItem) {
            setActive(activeItem.link);
        }
    }

    var ticking = false;
    function onScroll() {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(function () {
            recomputeActive();
            ticking = false;
        });
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', function () {
        lineOffset = getLineOffset();
        recomputeActive();
    });

    links.forEach(function (link) {
        link.addEventListener('click', function () {
            // Immediate optimistic feedback; the scroll listener above keeps
            // this correct for the rest of the (possibly animated) scroll
            // and after it settles, so nothing here is a timed override.
            setActive(link);
        });
    });

    recomputeActive();
}());
