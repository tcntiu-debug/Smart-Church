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
    // The one-click binder lives in public/assets/js/notifications.js, so this
    // page, the bell panel and the dashboard share one state machine instead of
    // three drifting copies of it. It also reports whether the PHONE - not just
    // the browser - holds a push subscription (docs/PUSH.md).
    //
    // This renders in the scripts stack at the end of layouts/app.blade.php, which
    // sits BELOW window.tiuNotifications and notifications.js. It once did not, and
    // the guard below read `undefined` and returned, so the button stayed hidden for
    // every member, on every browser, on the one page that offered it.
    // (Name that stack WITHOUT an @ in here - Blade compiles directives inside JS
    // comments too, and would paste the whole stack into this script block.)
    (function () {
        'use strict';

        var config = window.tiuNotifications;

        if (!config || typeof config.bindEnableButton !== 'function') {
            return;
        }

        config.bindEnableButton(
            document.getElementById('enableBrowserAlerts'),
            document.getElementById('browserAlertsState')
        );
    })();
</script>
@endpush
