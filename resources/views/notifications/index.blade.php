@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="ms-panel">
            <div class="ms-panel-header d-flex flex-wrap align-items-center justify-content-between">
                <h6 class="mb-0">
                    <i class="fas fa-bell"></i> Notifications
                    @if ($unread > 0)
                        <span class="badge badge-danger ml-2">{{ $unread }} new</span>
                    @endif
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
                            <button type="submit" class="btn btn-sm btn-primary">Mark all as read</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="ms-panel-body">
                @forelse ($notifications as $notification)
                    @php
                        // Where this notification leads: what the writer stored, or
                        // the type's default (config/notifications.php).
                        $payload = is_string($notification->data ?? null)
                            ? (json_decode($notification->data, true) ?: [])
                            : (array) ($notification->data ?? []);

                        $icons = [
                            'birthday_reminder' => '🎂',
                            'caller_assignment' => '📞',
                            'guide_assignment'  => '🤝',
                        ];

                        $target = $payload['url'] ?? config('notifications.urls.' . $notification->type);
                        $targetLabel = [
                            'caller_assignment' => 'Open call list',
                            'guide_assignment'  => 'Open my tasks',
                        ][$notification->type] ?? 'Open';
                    @endphp

                    <div class="d-flex flex-wrap align-items-start border-bottom py-3 {{ $notification->read_at ? '' : 'bg-light' }}"
                         style="gap:12px;">
                        <div class="mr-2" style="font-size:22px;line-height:1;">
                            {{ $icons[$notification->type] ?? '🔔' }}
                        </div>

                        <div class="flex-grow-1" style="min-width:220px;">
                            <div class="d-flex flex-wrap align-items-center">
                                <strong>{{ $notification->title }}</strong>
                                @if (empty($notification->read_at))
                                    <span class="badge badge-primary ml-2">New</span>
                                @endif
                            </div>

                            @if ($notification->body)
                                <div class="text-muted mt-1" style="white-space:pre-line;font-size:13px;">{{ $notification->body }}</div>
                            @endif

                            <div class="text-muted mt-1" style="font-size:12px;">
                                {{ \Carbon\Carbon::parse($notification->created_at)->format('D, d M Y g:i A') }}
                            </div>
                        </div>

                        <div class="text-right">
                            @if ($notification->type === 'birthday_reminder')
                                <a href="{{ url('/birthdays') }}" class="btn btn-sm btn-outline-primary">View birthdays</a>
                            @elseif ($target)
                                <a href="{{ url($target) }}" class="btn btn-sm btn-outline-primary">{{ $targetLabel }}</a>
                            @endif

                            @if (empty($notification->read_at))
                                <a href="{{ route('notifications.read', $notification->id) }}"
                                   class="btn btn-sm btn-outline-secondary">Mark read</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0 py-3">You have no notifications yet.</p>
                @endforelse
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
