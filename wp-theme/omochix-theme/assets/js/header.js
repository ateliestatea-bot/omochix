/**
 * Header interactions: scroll state, theme, mobile navigation and search.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var header = document.querySelector('[data-site-header]');
    var menuButton = document.querySelector('[data-menu-toggle]');
    var navigation = document.querySelector('[data-primary-nav]');
    var themeButton = document.querySelector('[data-theme-toggle]');
    var searchDialog = document.querySelector('[data-search-dialog]');
    var searchOpen = document.querySelector('[data-search-open]');
    var searchClose = document.querySelector('[data-search-close]');

    function getMenuFocusables() {
        if (!navigation) return [];
        var focusables = Array.prototype.slice.call(navigation.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'));
        if (menuButton) focusables.unshift(menuButton);
        return focusables;
    }

    function updateHeader() {
        if (header) header.classList.toggle('is-scrolled', window.scrollY > 8);
    }

    function setMenu(open) {
        if (!menuButton || !navigation) return;
        menuButton.setAttribute('aria-expanded', String(open));
        menuButton.querySelector('.sr-only').textContent = open ? 'メニューを閉じる' : 'メニューを開く';
        navigation.classList.toggle('is-open', open);
        document.body.classList.toggle('has-open-menu', open);

        if (open) {
            window.requestAnimationFrame(function () {
                var focusables = getMenuFocusables();
                if (focusables.length > 1) focusables[1].focus();
                else if (focusables.length) focusables[0].focus();
            });
        }
    }

    function updateThemeLabel() {
        if (!themeButton) return;
        var isDark = root.dataset.theme === 'dark';
        themeButton.setAttribute('aria-label', isDark ? 'ライトモードに切り替える' : 'ダークモードに切り替える');
    }

    updateHeader();
    updateThemeLabel();
    window.addEventListener('scroll', updateHeader, { passive: true });

    if (menuButton) {
        menuButton.addEventListener('click', function () {
            setMenu(menuButton.getAttribute('aria-expanded') !== 'true');
        });
    }

    if (navigation) {
        navigation.addEventListener('click', function (event) {
            if (event.target.closest('a')) setMenu(false);
        });
    }

    if (themeButton) {
        themeButton.addEventListener('click', function () {
            var nextTheme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.dataset.theme = nextTheme;
            try { localStorage.setItem('omochix-theme', nextTheme); } catch (error) {}
            updateThemeLabel();
        });
    }

    if (searchDialog && searchOpen && searchClose) {
        searchOpen.addEventListener('click', function () {
            searchDialog.showModal();
            searchDialog.querySelector('input').focus();
        });
        searchClose.addEventListener('click', function () { searchDialog.close(); });
        searchDialog.addEventListener('click', function (event) {
            if (event.target === searchDialog) searchDialog.close();
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && menuButton && menuButton.getAttribute('aria-expanded') === 'true') {
            setMenu(false);
            menuButton.focus();
        }

        if (event.key === 'Tab' && menuButton && menuButton.getAttribute('aria-expanded') === 'true') {
            var focusables = getMenuFocusables();
            if (!focusables.length) return;
            var first = focusables[0];
            var last = focusables[focusables.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1080) setMenu(false);
    });
}());
