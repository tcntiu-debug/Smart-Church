/**
 * Smart-Church display mode (dark / light).
 *
 * The dark palette is supplied by the "Modern Admin" stylesheet: every dark rule
 * in public/assets/css/style.css is scoped to `.ms-dark-theme` (for example
 * `body.ms-dark-theme, .ms-dark-theme a, ...`), so the class must live on <body>.
 * This file is the single owner of that class.
 *
 * Storage - the `tiu_theme` cookie:
 *   - The cookie is read server side by the layouts, which render the class with
 *     the HTML, so there is no white flash while the page loads.
 *   - The cookie is written here only after the user clicks a [data-theme-toggle].
 *   - While no cookie exists the operating system preference
 *     (`prefers-color-scheme`) is followed, and it keeps being followed if the
 *     user changes it mid-session.
 *
 * Markup contract: any element carrying `data-theme-toggle` becomes a switch.
 * Icons and labels swap through CSS (see public/assets/css/theme-toggle.css).
 */
(function (window, document) {
    'use strict';

    var COOKIE_NAME = 'tiu_theme';
    var BODY_CLASS = 'ms-dark-theme';
    var COOKIE_MAX_AGE = 31536000; // one year, in seconds
    var COOKIE_PATTERN = /(?:^|;\s*)tiu_theme=(dark|light)/;

    /** @returns {string|null} the stored choice, or null when the user never chose. */
    function read() {
        var match = document.cookie.match(COOKIE_PATTERN);

        return match ? match[1] : null;
    }

    /** @param {string} theme 'dark' or 'light'. */
    function write(theme) {
        var attributes = ';path=/;max-age=' + COOKIE_MAX_AGE + ';samesite=lax';

        if (window.location.protocol === 'https:') {
            attributes += ';secure';
        }

        document.cookie = COOKIE_NAME + '=' + theme + attributes;
    }

    /** @returns {string} the operating system preference. */
    function systemTheme() {
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

        return prefersDark ? 'dark' : 'light';
    }

    /**
     * Resolve what should be displayed right now.
     *
     * @returns {string} stored choice first, then the OS preference.
     */
    function get() {
        return read() || systemTheme();
    }

    /** Walk up from the click target to the nearest switch element. */
    function findToggle(node) {
        while (node && node !== document) {
            if (node.hasAttribute && node.hasAttribute('data-theme-toggle')) {
                return node;
            }

            node = node.parentNode;
        }

        return null;
    }

    /**
     * Paint a theme and keep every toggle's state in sync.
     *
     * @param {string} theme 'dark' or 'light'.
     */
    function apply(theme) {
        var isDark = theme === 'dark';

        if (document.body) {
            if (isDark) {
                document.body.classList.add(BODY_CLASS);
            } else {
                document.body.classList.remove(BODY_CLASS);
            }
        }

        var toggles = document.querySelectorAll('[data-theme-toggle]');

        for (var i = 0; i < toggles.length; i++) {
            toggles[i].setAttribute('aria-pressed', isDark ? 'true' : 'false');
            toggles[i].setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        }
    }

    /**
     * @param {string}  theme   'dark' or 'light'.
     * @param {boolean} persist store the choice in the cookie.
     */
    function set(theme, persist) {
        if (persist) {
            write(theme);
        }

        apply(theme);
    }

    /** Flip to the opposite mode and remember it. */
    function toggle() {
        set(get() === 'dark' ? 'light' : 'dark', true);
    }

    // Delegated so toggles rendered later (or several on one page) all work.
    document.addEventListener('click', function (event) {
        if (!findToggle(event.target)) {
            return;
        }

        event.preventDefault();
        toggle();
    });

    // Follow the OS preference live while the user has not made a choice.
    if (window.matchMedia) {
        var query = window.matchMedia('(prefers-color-scheme: dark)');
        var syncWithSystem = function () {
            if (!read()) {
                apply(systemTheme());
            }
        };

        if (query.addEventListener) {
            query.addEventListener('change', syncWithSystem);
        } else if (query.addListener) {
            query.addListener(syncWithSystem); // Safari < 14
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            apply(get());
        });
    } else {
        apply(get());
    }

    // Small public API, handy for other scripts (and for manual testing).
    window.TiuTheme = {
        get: get,
        set: set,
        toggle: toggle,
        apply: apply,
        cookieName: COOKIE_NAME,
        bodyClass: BODY_CLASS
    };
})(window, document);
