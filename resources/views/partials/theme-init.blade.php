{{--
    Resolve the display mode before the page paints, so the user never sees a
    white flash while a dark page loads.

    Include this immediately after the opening <body> tag. The cookie case is
    already handled by the `<body class="...">` rendered in Blade, so this script
    only has to honour the operating-system preference. The full behaviour lives
    in public/assets/js/theme.js.
--}}
<script>
    (function () {
        try {
            if (/(?:^|;\s*)tiu_theme=(dark|light)/.test(document.cookie)) {
                return; // The choice is already rendered into the body class.
            }

            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.body.classList.add('ms-dark-theme');
            }
        } catch (error) {
            // No cookies / no matchMedia support: stay in light mode.
        }
    })();
</script>
