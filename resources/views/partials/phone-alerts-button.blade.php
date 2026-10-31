{{--
    "Turn on phone alerts" on the dashboard - one click, without the walk through
    the bell, the notification page and a second button there.

    It renders NOTHING unless this server can push at all (a VAPID pair in .env and
    the /push/* route names), which is the same two-sided guard the layout uses -
    see layouts/app.blade.php. Everything else is decided in the browser by
    public/assets/js/notifications.js (config.bindEnableButton): the button appears
    only while a click would achieve something, because a page cannot ask for the
    Notification permission by itself - browsers require a user gesture - and it
    gets out of the way once alerts are on.

    Include it anywhere with:  @include('partials.phone-alerts-button')
--}}
@php
    $phoneAlertsKey = app(\App\Services\WebPushService::class)->publicKey();

    // Route::has() takes one name; a stale route cache is what made the layout ask.
    $phoneAlertsReady = $phoneAlertsKey !== null
        && \Illuminate\Support\Facades\Route::has('push.status')
        && \Illuminate\Support\Facades\Route::has('push.subscribe');
@endphp

@if ($phoneAlertsReady)
    <div class="phone-alerts mb-3" data-phone-alerts hidden>
        <button type="button" class="btn btn-outline-primary" data-phone-alerts-button>
            <i class="fas fa-mobile-alt" aria-hidden="true"></i> Turn on phone alerts
        </button>

        <p class="text-muted small mb-0 mt-2" data-phone-alerts-state hidden></p>
    </div>

    @push('scripts')
    <script>
        (function () {
            'use strict';

            var root = document.querySelector('[data-phone-alerts]');
            var config = window.tiuNotifications;

            if (!root || !config || typeof config.bindEnableButton !== 'function') {
                return;
            }

            var button = root.querySelector('[data-phone-alerts-button]');
            var state = root.querySelector('[data-phone-alerts-state]');

            // The dashboard only ever shows the action, never a status line: when
            // there is nothing to click (alerts already on, blocked, or a browser
            // that cannot show them) the whole block disappears. /notifications is
            // where the member reads the state.
            config.bindEnableButton(button, state, function (element) {
                root.hidden = element.hidden;
            });
        })();
    </script>
    @endpush
@endif
