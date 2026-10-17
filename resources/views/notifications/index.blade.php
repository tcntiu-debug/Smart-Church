@extends('layouts.app')

@section('content')
{{--
    The notification centre, as a page. The bell opens the same list in a modal
    (partials/notification-modal), so this page is both the no-JavaScript fallback
    and the place where browser alerts are switched on.

    Marking an alert read clears it (NotificationController::markRead), therefore
    the list only ever holds what still needs attention - which is also why the
    forms below are POSTs. See docs/NOTIFICATIONS.md.
--}}
<div class="row">
    <div class="col-12">
        <div class="ms-panel">
            <div class="ms-panel-header d-flex flex-wrap align-items-center justify-content-between">
                <h6 class="mb-0">
                    <i class="fas fa-bell"></i> Notifications
                    <span class="notif-panel-count" @if ($unread <= 0) hidden @endif>{{ $unread }} unread</span>
                </h6>

                <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                    {{-- The same caller / guide alert can reach the phone as a
                         system notification. The browser asks once, so the button
                         hides itself after the member has answered
                         (public/assets/js/notifications.js). --}}
                    <span id="browserAlertsState" class="text-muted" style="font-size:12px;"></span>

                    <button type="button" id="enableBrowserAlerts" class="btn btn-sm btn-outline-primary" hidden>
                        <i class="fas fa-mobile-alt"></i> Enable browser alerts
                    </button>

                    @if ($unread > 0)
                        <form action="{{ route('notifications.read-all') }}" method="POST" class="mb-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-check-double"></i> Mark all as read
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="ms-panel-body">
                @if ($notifications->isEmpty())
                    <div class="notif-empty">
                        <i class="fas fa-check-circle"></i>
                        <p class="notif-empty-title">You are all caught up.</p>
                        <p class="notif-empty-note">New caller and guide assignments appear here.</p>
                    </div>
                @else
                    <ul class="notif-list">
                        @foreach ($notifications as $notification)
                            <li class="notif-item">
                                <span class="notif-icon notif-icon-{{ $notification['tone'] }}">
                                    <i class="fas {{ $notification['icon'] }}"></i>
                                </span>

                                <div class="notif-main">
                                    <div class="notif-title">{{ $notification['title'] }}</div>

                                    @if ($notification['body'])
                                        <p class="notif-body">{{ $notification['body'] }}</p>
                                    @endif

                                    <div class="notif-meta">
                                        <span class="notif-ago">{{ $notification['ago'] }}</span>
                                        @if ($notification['created_at_exact'])
                                            <span class="notif-exact" title="{{ $notification['created_at'] }}"> &middot; {{ $notification['created_at_exact'] }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="notif-actions">
                                    @if ($notification['url'])
                                        <a href="{{ $notification['url'] }}" class="btn btn-sm btn-outline-primary">
                                            {{ $notification['action_label'] }}
                                        </a>
                                    @endif

                                    <form action="{{ route('notifications.read', $notification['id']) }}" method="POST" class="mb-0">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                title="Mark read and remove this alert"
                                                aria-label="Mark read and remove this alert">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Asks the browser for the one-time permission behind the alerts raised by
    // public/assets/js/notifications.js, then reports the current state.
    (function () {
        'use strict';

        var button = document.getElementById('enableBrowserAlerts');
        var state = document.getElementById('browserAlertsState');
        var config = window.tiuNotifications;

        if (!button || !state || !config || typeof config.requestPermission !== 'function') {
            return;
        }

        function render() {
            if (typeof window.Notification !== 'function') {
                state.textContent = 'This browser cannot show notifications.';
                return;
            }

            if (window.Notification.permission === 'granted') {
                button.hidden = true;
                state.textContent = 'Browser alerts are on.';
                return;
            }

            if (window.Notification.permission === 'denied') {
                button.hidden = true;
                state.textContent = 'Browser alerts are blocked in this browser\u2019s site settings.';
                return;
            }

            button.hidden = false;
            state.textContent = 'Turn on browser alerts to hear about new assignments.';
        }

        function done() {
            button.disabled = false;
            render();
        }

        button.addEventListener('click', function () {
            button.disabled = true;

            config.requestPermission().then(done).catch(done);
        });

        render();
    })();
</script>
@endpush
