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

                @if ($unread > 0)
                    <form action="{{ route('notifications.read-all') }}" method="POST" class="mb-0">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">Mark all as read</button>
                    </form>
                @endif
            </div>

            <div class="ms-panel-body">
                @forelse ($notifications as $notification)
                    <div class="d-flex flex-wrap align-items-start border-bottom py-3 {{ $notification->read_at ? '' : 'bg-light' }}"
                         style="gap:12px;">
                        <div class="mr-2" style="font-size:22px;line-height:1;">
                            {{ $notification->type === 'birthday_reminder' ? '🎂' : '🔔' }}
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
