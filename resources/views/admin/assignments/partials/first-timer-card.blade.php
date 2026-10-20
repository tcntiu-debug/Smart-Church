{{--
    One first timer. Drag it onto a guide to assign it, or back into the
    unassigned box to unassign it.

    $timer  - a first_timer row
    $origin - 'pool' for an unassigned card, 'assigned' for one a guide holds
--}}
@php
    $isPoolCard = ($origin ?? 'pool') === 'pool';
    $name = trim(($timer->first_name ?? '').' '.($timer->last_name ?? ''));

    $registeredOn = '';
    if (!empty($timer->register_date)) {
        try {
            $registeredOn = \Illuminate\Support\Carbon::parse($timer->register_date)->format('M j, Y');
        } catch (\Throwable $e) {
            $registeredOn = (string) $timer->register_date;
        }
    }
@endphp

<div class="first-timer-card {{ $isPoolCard ? 'draggable-unassigned' : 'draggable-assigned' }}"
     data-timer-id="{{ $timer->first_timer_id }}"
     data-timer-name="{{ $name }}"
     data-timer-status="{{ $timer->status ?? 'Assigned' }}"
     title="{{ $isPoolCard ? 'Drag onto a guide to assign' : 'Drag back to the first timers box to unassign' }}">
    <strong>
        <i class="fas {{ $isPoolCard ? 'fa-hand-rock' : 'fa-arrows-alt-v' }} mr-2"></i>{{ $name !== '' ? $name : 'Unnamed first timer' }}
    </strong>

    <p>
        <span>Phone:</span>
        {{ !empty($timer->phone_number) ? $timer->phone_number : 'N/A' }}
    </p>

    @if(!empty($timer->gender) || $registeredOn !== '')
        <p>
            @if(!empty($timer->gender))
                <span>Gender:</span> {{ $timer->gender }}
            @endif
            @if(!empty($timer->gender) && $registeredOn !== '')
                &middot;
            @endif
            @if($registeredOn !== '')
                <span>Registered:</span> {{ $registeredOn }}
            @endif
        </p>
    @endif

    @if(!empty($timer->attendant_type))
        <p><span>Guest type:</span> {{ $timer->attendant_type }}</p>
    @endif
</div>
