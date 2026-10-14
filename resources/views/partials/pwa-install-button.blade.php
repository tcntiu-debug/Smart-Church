{{--
    "Install app" button (PWA).

    Chrome / Edge fire `beforeinstallprompt` once per page life; the layout
    (resources/views/layouts/app.blade.php) captures it in `window.tiuInstallPrompt`
    and raises `tiu:install-available`. This element turns that into a one click
    install, and stays hidden when

      * the app already runs as an installed app (display-mode: standalone), or
      * the browser never offers the prompt and it is not iOS.

    iOS Safari never fires `beforeinstallprompt`, so there the button is replaced
    by the "Share > Add to Home Screen" instructions.

    Include it anywhere with:  @include('partials.pwa-install-button')
--}}
<div class="pwa-install mb-3" data-pwa-install hidden>
    <button type="button" class="btn btn-primary" data-pwa-install-button hidden>
        <i class="fas fa-download" aria-hidden="true"></i> Install app on this device
    </button>

    <p class="text-muted small mb-0" data-pwa-install-ios hidden>
        On iPhone or iPad: tap <strong>Share</strong> in the browser bar, then
        <strong>Add to Home Screen</strong>.
    </p>

    <p class="text-muted small mb-0" data-pwa-install-status hidden></p>
</div>

@push('scripts')
<script>
    (function () {
        'use strict';

        var root = document.querySelector('[data-pwa-install]');

        if (!root) {
            return;
        }

        var button = root.querySelector('[data-pwa-install-button]');
        var iosHint = root.querySelector('[data-pwa-install-ios]');
        var status = root.querySelector('[data-pwa-install-status]');

        /** The page is already running as an installed app. */
        function isInstalled() {
            var standalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;

            return Boolean(standalone) || window.navigator.standalone === true;
        }

        /** Every iOS browser shares the same engine and has no install prompt. */
        function isIos() {
            return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
        }

        function reveal() {
            if (isInstalled()) {
                root.hidden = true;

                return;
            }

            if (window.tiuInstallPrompt) {
                root.hidden = false;
                button.hidden = false;
                iosHint.hidden = true;

                return;
            }

            if (isIos()) {
                root.hidden = false;
                button.hidden = true;
                iosHint.hidden = false;
            }
        }

        button.addEventListener('click', function () {
            var prompt = window.tiuInstallPrompt;

            if (!prompt) {
                return;
            }

            button.disabled = true;
            prompt.prompt();

            prompt.userChoice.then(function (choice) {
                window.tiuInstallPrompt = null;
                button.disabled = false;

                if (choice && choice.outcome === 'accepted') {
                    root.hidden = true;

                    return;
                }

                status.hidden = false;
                status.textContent = 'Install cancelled - you can install later from the browser menu.';
            });
        });

        window.addEventListener('tiu:install-available', reveal);
        window.addEventListener('tiu:installed', function () {
            root.hidden = true;
        });

        reveal();
    })();
</script>
@endpush
