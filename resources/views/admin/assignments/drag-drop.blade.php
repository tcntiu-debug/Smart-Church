@extends('layouts.app')

@section('content')
@php
    $guideCount = count($guidesArray);
    $assignedCount = 0;
    foreach ($guidesArray as $guideData) {
        $assignedCount += count($guideData['first_timers']);
    }
    // "Clear" in one box must not throw away what the other box is showing.
    $keepExcept = function ($prefix) use ($filters) {
        return array_filter($filters, function ($value, $key) use ($prefix) {
            return $value !== '' && strpos($key, $prefix) !== 0;
        }, ARRAY_FILTER_USE_BOTH);
    };

    $clearGuideUrl = route('admin.assignments.drag-drop', $keepExcept('guide_'));
    $clearTimerUrl = route('admin.assignments.drag-drop', $keepExcept('ft_'));
@endphp

<style>
    .assign-page { padding-bottom: 30px; }

    .assign-heading {
        display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
        gap: 12px; margin-bottom: 14px;
    }
    .assign-title { font-size: 20px; font-weight: 700; color: #2e3a59; margin: 0; }
    .assign-subtitle { font-size: 13px; color: #6b7280; margin: 4px 0 0; }
    .assign-totals { display: flex; flex-wrap: wrap; gap: 8px; }
    .assign-chip {
        background: #eef1fb; border: 1px solid #dde2f5; color: #3d4a68;
        border-radius: 999px; padding: 4px 12px; font-size: 12px; white-space: nowrap;
    }
    .assign-chip strong { font-size: 13px; }

    .assign-container { display: flex; flex-wrap: wrap; gap: 18px; align-items: flex-start; }
    .assign-container .column { min-width: 0; }
    .assign-container .column-left { flex: 1 1 560px; }
    .assign-container .column-right { flex: 1 1 360px; }

    .panel {
        background: #fff; border: 1px solid #e3e6f0; border-radius: 8px;
        margin-bottom: 16px; overflow: hidden;
    }
    .panel-head {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
        gap: 10px; padding: 10px 14px; background: #f8f9fc; border-bottom: 1px solid #e3e6f0;
    }
    .panel-head h2 { font-size: 14px; font-weight: 700; color: #2e3a59; margin: 0; }
    .panel-head .panel-count { font-size: 12px; color: #6b7280; font-weight: 400; }
    .panel-body { padding: 12px 14px; }
    .panel-body.is-scroll { max-height: 62vh; overflow-y: auto; }

    .filters-box .form-row > [class*="col-"] { margin-bottom: 8px; }
    .filters-box label {
        font-size: 11px; font-weight: 600; color: #6b7280; margin-bottom: 2px;
        text-transform: uppercase; letter-spacing: .03em;
    }
    .filters-box .form-control { font-size: 13px; }
    .filter-actions { display: flex; gap: 6px; }

    .guide-card { border: 1px solid #d1d3e2; border-radius: 6px; margin-bottom: 10px; }
    .guide-card:last-child { margin-bottom: 0; }
    .guide-card .guide-head {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 8px 12px; background: #f8f9fc; cursor: pointer; user-select: none;
    }
    .guide-card .guide-name { font-size: 14px; font-weight: 600; color: #333; }
    .guide-card .guide-meta { font-size: 11px; color: #6b7280; }
    .guide-card .guide-toggle { color: #4e73df; font-size: 12px; white-space: nowrap; }
    .guide-card .guide-toggle i { transition: transform .2s; }
    .guide-card .guide-body { padding: 8px; }
    .guide-card.is-collapsed .guide-body { display: none; }
    .guide-card.is-collapsed .guide-toggle i { transform: rotate(-90deg); }

    .guide-drop-zone {
        min-height: 62px; padding: 6px; border: 2px dashed #d1d3e2; border-radius: 6px;
        transition: border-color .2s, background .2s;
    }
    .drop-hint { margin: 0; padding: 10px 4px; font-size: 12px; color: #8a8fa3; text-align: center; }
    .drop-hint.d-none { display: none; }

    .ui-droppable-hover { background: #eaf4ff !important; border-color: #4e73df !important; }

    .first-timer-card {
        background: #fff; border: 1px solid #d1d3e2; border-radius: 6px;
        padding: 7px 10px; margin-bottom: 6px; cursor: grab; font-size: 13px;
        transition: box-shadow .2s;
    }
    .first-timer-card:last-child { margin-bottom: 0; }
    .first-timer-card:hover { box-shadow: 0 2px 8px rgba(0, 0, 0, .1); }
    .first-timer-card:active { cursor: grabbing; }
    .first-timer-card strong { color: #2e3a59; font-size: 13px; }
    .first-timer-card p { margin: 2px 0 0; font-size: 11px; color: #6b7280; }
    .first-timer-card p span { font-weight: 600; color: #3d4a68; }

    .ui-draggable-dragging { opacity: .85; z-index: 1000 !important; }

    #unassigned-pool { border: 2px dashed #d1d3e2; border-radius: 6px; padding: 6px; min-height: 160px; }

    #status-message {
        display: none; padding: 8px 12px; border-radius: 4px;
        margin-bottom: 10px; font-size: 13px;
    }
    #status-message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    #status-message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    .assign-more { display: block; margin-top: 10px; font-size: 12px; text-align: center; }
    .panel-head .panel-clear { font-size: 12px; font-weight: 600; margin-left: 8px; white-space: nowrap; }

    /* Dark theme twins (docs/DISPLAY-MODE.md) */
    .ms-dark-theme .assign-title,
    .ms-dark-theme .assign-subtitle,
    .ms-dark-theme .panel-head h2 { color: #fff; }
    .ms-dark-theme .assign-chip { background: #323a67; border-color: #3d4a80; color: #dfe3ff; }
    .ms-dark-theme .panel,
    .ms-dark-theme .first-timer-card { background: #252851; border-color: #242750; }
    .ms-dark-theme .panel-head,
    .ms-dark-theme .guide-card .guide-head { background: #2d3160; border-color: #242750; }
    .ms-dark-theme .guide-card { border-color: #3d4a80; }
    .ms-dark-theme .guide-card .guide-name,
    .ms-dark-theme .first-timer-card strong { color: #fff; }
    .ms-dark-theme .guide-card .guide-meta,
    .ms-dark-theme .first-timer-card p,
    .ms-dark-theme .panel-head .panel-count { color: #b9bcd8; }
    .ms-dark-theme .panel-head .panel-count { color: #b9bcd8 !important; }
    .ms-dark-theme .first-timer-card p span { color: #dfe3ff; }
    .ms-dark-theme .guide-drop-zone,
    .ms-dark-theme #unassigned-pool { border-color: #3d4a80; }
    .ms-dark-theme .drop-hint { color: #b9bcd8; }
    .ms-dark-theme .filters-box label { color: #b9bcd8; }
    .ms-dark-theme .panel-head .panel-clear { color: #9fc3ff; }
    .ms-dark-theme .text-muted { color: #b9bcd8 !important; }
</style>

<div class="assign-page">
    <div class="assign-heading">
        <div>
            <h1 class="assign-title"><i class="fas fa-arrows-alt mr-2"></i>Assign First Timers</h1>
            <p class="assign-subtitle">
                {{ $departmentName }} &middot; drag a first timer onto a guide, or drag an
                assigned one back to the unassigned box.
            </p>
        </div>
        <div class="assign-totals">
            <span class="assign-chip"><strong id="pool-count">{{ number_format($poolTotal) }}</strong> unassigned</span>
            <span class="assign-chip"><strong id="assigned-count">{{ number_format($assignedCount) }}</strong> with guides</span>
            <span class="assign-chip"><strong id="guide-count">{{ number_format($guideCount) }}</strong> guides</span>
        </div>
    </div>

    <div id="status-message" role="status" aria-live="polite"></div>

    <div class="assign-container">
        {{-- LEFT: the guides. Every guide is a drop zone holding its first timers. --}}
        <div class="column column-left">
            <div class="panel">
                <div class="panel-head">
                    <h2>
                        <i class="fas fa-user-friends mr-1"></i>Guides
                        <span class="panel-count">
                            ({{ number_format($guideCount) }}@if($guideFiltersActive) of {{ number_format($guideTotal) }}@endif shown)
                        </span>
                        @if($guideFiltersActive)
                            <a href="{{ $clearGuideUrl }}" class="panel-clear">Show all guides</a>
                        @endif
                    </h2>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggle-all-guides">
                        Collapse all
                    </button>
                </div>

                <div class="panel-body border-bottom">
                    <form action="{{ route('admin.assignments.drag-drop') }}" method="GET" class="filters-box">
                        @foreach($filters as $filterKey => $filterValue)
                            @if($filterValue !== '' && strpos($filterKey, 'guide_') !== 0)
                                <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                            @endif
                        @endforeach

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="guide-search">Name or phone</label>
                                <input type="search" id="guide-search" name="guide_search"
                                       class="form-control form-control-sm"
                                       value="{{ $filters['guide_search'] }}"
                                       placeholder="Search guides">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="guide-gender">Gender</label>
                                <select id="guide-gender" name="guide_gender" class="form-control form-control-sm">
                                    <option value="">All genders</option>
                                    <option value="Male" {{ $filters['guide_gender'] === 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ $filters['guide_gender'] === 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="guide-church-type">Church type</label>
                                <select id="guide-church-type" name="guide_church_type" class="form-control form-control-sm">
                                    <option value="">All church types</option>
                                    @foreach($churchTypes as $churchType)
                                        <option value="{{ $churchType->id }}"
                                            {{ (string) $filters['guide_church_type'] === (string) $churchType->id ? 'selected' : '' }}>
                                            {{ $churchType->church_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="guide-occupation">Occupation</label>
                                <select id="guide-occupation" name="guide_occupation" class="form-control form-control-sm">
                                    <option value="">All occupations</option>
                                    @foreach($occupations as $occupation)
                                        <option value="{{ $occupation }}"
                                            {{ $filters['guide_occupation'] === $occupation ? 'selected' : '' }}>
                                            {{ $occupation }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter mr-1"></i>Filter guides
                            </button>
                            <a href="{{ $clearGuideUrl }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        </div>
                    </form>
                </div>

                <div class="panel-body is-scroll" id="guide-list">
                    @forelse($guidesArray as $guideId => $guideData)
                        @php
                            $guide = $guideData['details'];
                            $guideTimers = $guideData['first_timers'];
                            $guideName = trim(($guide->first_name ?? '').' '.($guide->last_name ?? ''));
                        @endphp
                        <div class="guide-card" data-guide-id="{{ $guideId }}">
                            <div class="guide-head" role="button" tabindex="0">
                                <div>
                                    <div class="guide-name">{{ $guideName !== '' ? $guideName : 'Unnamed guide' }}</div>
                                    <div class="guide-meta">
                                        {{ !empty($guide->gender) ? $guide->gender : 'Gender N/A' }}
                                        @if(!empty($guide->age)) &middot; {{ $guide->age }} yrs @endif
                                        @if(!empty($guide->occupation)) &middot; {{ $guide->occupation }} @endif
                                        @if(!empty($guide->subgroup)) &middot; {{ $guide->subgroup }} @endif
                                    </div>
                                </div>
                                <div class="guide-toggle">
                                    <span class="guide-badge">
                                        {{ count($guideTimers) }} {{ count($guideTimers) === 1 ? 'first timer' : 'first timers' }}
                                    </span>
                                    <i class="fas fa-chevron-down ml-2"></i>
                                </div>
                            </div>
                            <div class="guide-body">
                                <div class="guide-drop-zone droppable-guide-content" data-guide-id="{{ $guideId }}">
                                    <div class="assigned-timers-list">
                                        @foreach($guideTimers as $timer)
                                            @include('admin.assignments.partials.first-timer-card', [
                                                'timer' => $timer,
                                                'origin' => 'assigned',
                                            ])
                                        @endforeach
                                    </div>
                                    <p class="drop-hint {{ count($guideTimers) ? 'd-none' : '' }}">
                                        Drop a first timer here
                                    </p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-muted py-4">
                            @if($guideFiltersActive)
                                No guides match these filters. <a href="{{ $clearGuideUrl }}">Show all guides</a>.
                            @else
                                No guide in {{ $departmentName }} yet.
                            @endif
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- RIGHT: the first timers nobody follows up yet. Drag one onto a guide;
             drag an assigned one back into this box to unassign it. --}}
        <div class="column column-right">
            <div class="panel">
                <div class="panel-head">
                    <h2>
                        <i class="fas fa-user-plus mr-1"></i>First timers to assign
                        <span class="panel-count">
                            ({{ number_format($poolShowing) }} of {{ number_format($poolTotal) }})
                        </span>
                        @if($timerFiltersActive)
                            <a href="{{ $clearTimerUrl }}" class="panel-clear">Show all first timers</a>
                        @endif
                    </h2>
                </div>

                <div class="panel-body border-bottom">
                    <form action="{{ route('admin.assignments.drag-drop') }}" method="GET" class="filters-box">
                        @foreach($filters as $filterKey => $filterValue)
                            @if($filterValue !== '' && strpos($filterKey, 'ft_') !== 0)
                                <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                            @endif
                        @endforeach

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="ft-search">Name</label>
                                <input type="search" id="ft-search" name="ft_search"
                                       class="form-control form-control-sm"
                                       value="{{ $filters['ft_search'] }}"
                                       placeholder="Search first timers">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="ft-gender">Gender</label>
                                <select id="ft-gender" name="ft_gender" class="form-control form-control-sm">
                                    <option value="">All genders</option>
                                    <option value="Male" {{ $filters['ft_gender'] === 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ $filters['ft_gender'] === 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="ft-start-date">Registered from</label>
                                <input type="date" id="ft-start-date" name="ft_start_date"
                                       class="form-control form-control-sm" value="{{ $filters['ft_start_date'] }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="ft-end-date">Registered up to</label>
                                <input type="date" id="ft-end-date" name="ft_end_date"
                                       class="form-control form-control-sm" value="{{ $filters['ft_end_date'] }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="ft-church-type">Church type</label>
                                <select id="ft-church-type" name="ft_church_type" class="form-control form-control-sm">
                                    <option value="">All church types</option>
                                    @foreach($churchTypes as $churchType)
                                        <option value="{{ $churchType->id }}"
                                            {{ (string) $filters['ft_church_type'] === (string) $churchType->id ? 'selected' : '' }}>
                                            {{ $churchType->church_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="ft-guest-type">Guest type</label>
                                <select id="ft-guest-type" name="ft_guest_type" class="form-control form-control-sm">
                                    <option value="">All guest types</option>
                                    @foreach($guestTypeOptions as $guestType)
                                        <option value="{{ $guestType }}"
                                            {{ $filters['ft_guest_type'] === $guestType ? 'selected' : '' }}>
                                            {{ $guestType }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter mr-1"></i>Filter first timers
                            </button>
                            <a href="{{ $clearTimerUrl }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        </div>
                    </form>
                </div>

                <div class="panel-body is-scroll">
                    <div id="unassigned-pool">
                        <div class="assigned-timers-list" id="unassigned-list">
                            @foreach($unassignedMembers as $timer)
                                @include('admin.assignments.partials.first-timer-card', [
                                    'timer' => $timer,
                                    'origin' => 'pool',
                                ])
                            @endforeach
                        </div>
                        <p class="drop-hint {{ $poolShowing ? 'd-none' : '' }}">
                            @if($timerFiltersActive)
                                No unassigned first timer matches these filters.
                            @else
                                Nobody is waiting for a guide. Drag an assigned first timer here to unassign.
                            @endif
                        </p>
                    </div>

                    @if($morePoolUrl)
                        <a class="assign-more" href="{{ $morePoolUrl }}" id="show-more-pool">
                            Show more first timers
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/jquery-ui.min.js') }}"></script>
<script>
$(function () {
    var assignUrl = '{{ route("admin.assignments.ajax-assign") }}';
    var unassignUrl = '{{ route("admin.assignments.ajax-unassign") }}';
    var csrfToken = '{{ csrf_token() }}';
    var $status = $('#status-message');

    function showMessage(message, type) {
        $status.text(message)
            .removeClass('success error')
            .addClass(type)
            .stop(true, true)
            .fadeIn();

        window.clearTimeout(showMessage.timer);
        showMessage.timer = window.setTimeout(function () {
            $status.fadeOut();
        }, type === 'success' ? 3500 : 6000);
    }

    // The chips are the page's totals, so they follow every move.
    function readCount(selector) {
        var digits = $(selector).text().replace(/[^0-9]/g, '');
        return digits === '' ? 0 : parseInt(digits, 10);
    }

    function writeCount(selector, value) {
        $(selector).text(Math.max(0, value).toLocaleString());
    }

    function moveCounts(poolDelta, assignedDelta) {
        writeCount('#pool-count', readCount('#pool-count') + poolDelta);
        writeCount('#assigned-count', readCount('#assigned-count') + assignedDelta);
    }

    // "Drop a first timer here" belongs to empty boxes only - including the
    // unassigned box, which starts empty once every first timer has a guide.
    function refreshHints() {
        $('.guide-drop-zone, #unassigned-pool').each(function () {
            var $box = $(this);
            var hasCards = $box.find('.assigned-timers-list')
                .children('.first-timer-card').length > 0;

            $box.find('.drop-hint').first().toggleClass('d-none', hasCards);
        });
    }

    function makeDraggable() {
        $('.first-timer-card:not(.ui-draggable)').draggable({
            revert: 'invalid',
            helper: 'clone',
            appendTo: 'body',
            cursor: 'grabbing',
            zIndex: 1000,
            start: function (event, ui) {
                $(ui.helper).addClass('ui-draggable-dragging');
            }
        });
    }

    function markAssigned($card) {
        $card.removeClass('draggable-unassigned')
            .addClass('draggable-assigned')
            .attr('title', 'Drag back to the first timers box to unassign');
        $card.find('strong i').removeClass('fa-hand-rock').addClass('fa-arrows-alt-v');
    }

    function markUnassigned($card) {
        $card.removeClass('draggable-assigned')
            .addClass('draggable-unassigned')
            .attr('title', 'Drag onto a guide to assign');
        $card.find('strong i').removeClass('fa-arrows-alt-v').addClass('fa-hand-rock');
    }

    function assignTimer($card, $zone, guideId, fromGuideId) {
        var timerId = $card.data('timer-id');
        if (!timerId) {
            return;
        }

        $.ajax({
            url: assignUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                _token: csrfToken,
                Guild_name: guideId,
                first_timer_id: timerId,
                full_name: $card.data('timer-name'),
                status: $card.data('timer-status')
            }
        }).done(function (response) {
            if (!response || response.status !== 'success') {
                showMessage((response && response.message) || 'The assignment was not saved.', 'error');
                return;
            }

            var $moved = $card.detach();
            markAssigned($moved);
            $zone.closest('.guide-card').removeClass('is-collapsed');
            $zone.find('.assigned-timers-list').first().append($moved);

            refreshHints();
            makeDraggable();
            // Moving between two guides leaves the assigned total alone.
            moveCounts(fromGuideId ? 0 : -1, 1);
            showMessage(response.message, 'success');
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'The server rejected the assignment.';
            showMessage('Could not assign: ' + message, 'error');
        });
    }

    function unassignTimer($card) {
        var timerId = $card.data('timer-id');
        if (!timerId) {
            return;
        }

        $.ajax({
            url: unassignUrl,
            type: 'POST',
            dataType: 'json',
            data: { _token: csrfToken, first_timer_id: timerId }
        }).done(function (response) {
            if (!response || response.status !== 'success') {
                showMessage((response && response.message) || 'The first timer was not unassigned.', 'error');
                return;
            }

            var $moved = $card.detach();
            markUnassigned($moved);
            $moved.attr('data-timer-status', 'Unassigned');
            $('#unassigned-list').prepend($moved);

            refreshHints();
            makeDraggable();
            moveCounts(1, -1);
            showMessage(response.message, 'success');
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'The server rejected the request.';
            showMessage('Could not unassign: ' + message, 'error');
        });
    }

    $('.droppable-guide-content').droppable({
        accept: '.draggable-unassigned, .draggable-assigned',
        hoverClass: 'ui-droppable-hover',
        tolerance: 'pointer',
        drop: function (event, ui) {
            var $zone = $(this);
            var $card = ui.draggable;
            var guideId = $zone.data('guide-id');
            var fromGuideId = $card.closest('.guide-drop-zone').data('guide-id');

            // Dropping a card back on the guide that already holds it is a no-op.
            if (fromGuideId !== undefined && String(fromGuideId) === String(guideId)) {
                return;
            }

            assignTimer($card, $zone, guideId, fromGuideId || null);
        }
    });

    $('#unassigned-pool').droppable({
        accept: '.draggable-assigned',
        hoverClass: 'ui-droppable-hover',
        tolerance: 'pointer',
        drop: function (event, ui) {
            unassignTimer(ui.draggable);
        }
    });

    $('#guide-list')
        .on('click', '.guide-head', function () {
            $(this).closest('.guide-card').toggleClass('is-collapsed');
        })
        .on('keydown', '.guide-head', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                $(this).closest('.guide-card').toggleClass('is-collapsed');
            }
        });

    $('#toggle-all-guides').on('click', function () {
        var $button = $(this);
        var collapse = $button.data('collapsed') !== true;

        $('#guide-list .guide-card').toggleClass('is-collapsed', collapse);
        $button.data('collapsed', collapse).text(collapse ? 'Expand all' : 'Collapse all');
    });

    refreshHints();
    makeDraggable();
});
</script>
@endpush
