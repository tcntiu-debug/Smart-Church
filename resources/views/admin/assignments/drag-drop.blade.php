@extends('layouts.app')

@section('content')
@php
    $member_role = session('member_role', 'Guest');
@endphp

<style>
    /* --- Drag & Drop Layout --- */
    .assign-container {
        display: flex;
        gap: 20px;
        min-height: 70vh;
    }
    .assign-container .column {
        flex: 1;
        min-width: 0;
    }
    .assign-container .column-left { flex: 0 0 45%; }
    .assign-container .column-right { flex: 0 0 55%; }

    .filters-box {
        background: #f8f9fc;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    .filters-box h4 { font-size: 14px; font-weight: 700; margin-bottom: 10px; }
    .filters-box select,
    .filters-box input { font-size: 13px; }

    .content-box {
        background: #fff;
        border: 1px solid #e3e6f0;
        border-radius: 8px;
        padding: 15px;
        max-height: 70vh;
        overflow-y: auto;
    }
    .content-box h2 { font-size: 16px; font-weight: 700; }

    /* Guide card */
    .guide-card { margin-bottom: 8px; border: 1px solid #d1d3e2; border-radius: 6px; }
    .guide-card .card-header {
        background: #f8f9fc;
        padding: 8px 12px;
        cursor: pointer;
    }
    .guide-card .card-header .btn-link {
        padding: 0;
        color: #4e73df;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        width: 100%;
        text-align: left;
    }
    .guide-card .card-header .btn-link:hover { text-decoration: none; }
    .guide-card .card-body { padding: 10px; background: #fff; min-height: 60px; }

    .guide-details { font-size: 12px; color: #666; margin-bottom: 8px; }
    .guide-details p { margin: 2px 0; }
    .guide-details span { font-weight: 600; color: #333; }

    /* First Timer Card */
    .first-timer-card {
        background: #fff;
        border: 1px solid #d1d3e2;
        border-radius: 6px;
        padding: 8px 10px;
        margin-bottom: 6px;
        cursor: grab;
        transition: box-shadow 0.2s;
        font-size: 13px;
    }
    .first-timer-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .first-timer-card:active { cursor: grabbing; }
    .first-timer-card p { margin: 2px 0; font-size: 12px; color: #555; }
    .first-timer-card p span { font-weight: 600; color: #333; }
    .first-timer-card hr { margin: 4px 0; }

    .ui-draggable-dragging {
        opacity: 0.85;
        transform: rotate(2deg);
        z-index: 1000 !important;
    }
    .ui-droppable-hover {
        background: #eaf4ff !important;
        border-color: #4e73df !important;
    }

    /* Unassigned container */
    .droppable-unassign {
        min-height: 200px;
        padding: 5px;
        border: 2px dashed #d1d3e2;
        border-radius: 6px;
        transition: border-color 0.2s;
    }

    #status-message {
        display: none;
        padding: 8px 12px;
        border-radius: 4px;
        margin-bottom: 10px;
        font-size: 13px;
    }
    #status-message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    #status-message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    /* Suggestion box */
    #suggestion-box {
        display: none;
        background: #fff3cd;
        border: 1px solid #ffeeba;
        border-radius: 6px;
        padding: 10px;
        margin-bottom: 10px;
    }
    #suggestion-box h4 { font-size: 14px; font-weight: 700; margin-bottom: 8px; }

    /* ---- Dark mode ------------------------------------------------------
       style.css scopes every dark rule to `ms-dark-theme`, forces every
       heading/paragraph/span to #fff and repaints `.ms-dark-theme .card`
       #252851 - but it does not know about the light surfaces painted below
       (.filters-box / .content-box / .first-timer-card / .guide-card body). If
       those keep their white background the page text stays #fff on top of
       them, i.e. invisible. Each light rule therefore gets a dark twin here.
       Palette: surface #252851, deeper #1f2247, hover #2a2e5b, border #242750,
       accent #ff8306 (see docs/DISPLAY-MODE.md). */
    .ms-dark-theme .filters-box,
    .ms-dark-theme .content-box,
    .ms-dark-theme .first-timer-card,
    .ms-dark-theme .guide-card .card-body {
        background: #252851;
        border-color: #242750;
        color: #e7e8f5;
    }
    .ms-dark-theme .guide-card {
        border-color: #242750;
    }
    .ms-dark-theme .guide-card .card-header {
        background: #1f2247;
        border-color: #242750;
    }
    /* The guide name is a `.btn-link`, which layouts/app.blade.php paints
       #0062cc with `!important`, so this twin needs `!important` too or the name
       stays blue on the dark header. */
    .ms-dark-theme .guide-card .card-header .btn-link {
        color: #ff8306 !important;
    }
    .ms-dark-theme .guide-details {
        color: #b9bcd8;
    }
    .ms-dark-theme .guide-details span {
        color: #e7e8f5;
    }
    .ms-dark-theme .first-timer-card p {
        color: #b9bcd8;
    }
    .ms-dark-theme .first-timer-card p span {
        color: #e7e8f5;
    }
    /* Bootstrap utility, `!important`, so the twin needs it too. */
    .ms-dark-theme .text-muted {
        color: #b9bcd8 !important;
    }
    .ms-dark-theme .droppable-unassign {
        border-color: #242750;
    }
    .ms-dark-theme .ui-droppable-hover {
        background: #2a2e5b !important;
        border-color: #ff8306 !important;
    }
    .ms-dark-theme #suggestion-box {
        background: #3a2f10;
        border-color: #5a4a1a;
    }
    .ms-dark-theme #status-message.success {
        background: #1f3b2a;
        color: #b7f0c5;
        border-color: #2c5b40;
    }
    .ms-dark-theme #status-message.error {
        background: #3d2226;
        color: #ffc9cf;
        border-color: #5b2c33;
    }
</style>

<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-arrows-alt"></i> Assign Members (Drag & Drop)</h5>
            <div>
                <form method="GET" action="{{ route('admin.assignments.drag-drop') }}" class="form-inline">
                    <label class="mr-2 font-weight-bold">Department:</label>
                    <select name="dept_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        @foreach($deptOptions as $dept)
                            <option value="{{ $dept->dept_id }}" {{ $selectedDeptId == $dept->dept_id ? 'selected' : '' }}>{{ $dept->dept_name }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('admin.assignments.index') }}" class="btn btn-sm btn-secondary">Standard View</a>
                </form>
            </div>
        </div>
        <div class="card-body">
            <input type="hidden" id="selected-dept-id" value="{{ $selectedDeptId }}">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div id="status-message"></div>

            <div class="assign-container">
                <!-- LEFT COLUMN: GUIDES -->
                <div class="column column-left">
                    <div class="filters-box">
                        <h4><i class="fas fa-filter"></i> Filter Guides</h4>
                        <form action="{{ route('admin.assignments.drag-drop') }}" method="GET" class="form-row">
                            <input type="hidden" name="dept_id" value="{{ $selectedDeptId }}">
                            <input type="hidden" name="ft_start_date" value="{{ $ftStartDate }}">
                            <input type="hidden" name="ft_end_date" value="{{ $ftEndDate }}">
                            <input type="hidden" name="ft_gender" value="{{ $ftGender }}">
                            <input type="hidden" name="ft_church_type" value="{{ $ftChurchType }}">
                            <input type="hidden" name="ft_guest_type" value="{{ $ftGuestType }}">

                            <div class="form-group col-md-6">
                                <select name="guide_gender" class="form-control form-control-sm">
                                    <option value="">All Genders</option>
                                    <option value="Male" {{ $guideGender == 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ $guideGender == 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <select name="guide_church_type" class="form-control form-control-sm">
                                    <option value="">All Church Types</option>
                                    @foreach($churchTypes as $ct)
                                        <option value="{{ $ct->id }}" {{ $guideChurchType == $ct->id ? 'selected' : '' }}>{{ $ct->church_type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-12">
                                <select name="guide_occupation" class="form-control form-control-sm">
                                    <option value="">All Occupations</option>
                                    @foreach($occupations as $occ)
                                        <option value="{{ $occ }}" {{ $guideOccupation == $occ ? 'selected' : '' }}>{{ $occ }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6"><button type="submit" class="btn btn-primary btn-sm btn-block">Apply</button></div>
                            <div class="col-6"><a href="{{ route('admin.assignments.drag-drop') }}" class="btn btn-secondary btn-sm btn-block">Reset</a></div>
                        </form>
                    </div>

                    <div class="content-box">
                        <h2>Guides ({{ count($guidesArray) }})</h2>
                        <div id="guides-accordion">
                            @forelse($guidesArray as $guideId => $guideData)
                                <div class="guide-card">
                                    <div class="card-header" id="heading-{{ $guideId }}">
                                        <h5 class="mb-0">
                                            <button class="btn btn-link" data-toggle="collapse" data-target="#collapse-{{ $guideId }}" aria-expanded="false" aria-controls="collapse-{{ $guideId }}">
                                                {{ $guideData['details']->first_name }} {{ $guideData['details']->last_name }}
                                                <span class="badge badge-info float-right">{{ count($guideData['first_timers']) }}</span>
                                            </button>
                                        </h5>
                                    </div>
                                    <div id="collapse-{{ $guideId }}" class="collapse" aria-labelledby="heading-{{ $guideId }}" data-parent="#guides-accordion">
                                        <div class="card-body droppable-guide-content" data-guide-id="{{ $guideId }}">
                                                    <div class="guide-details">
                                                        <p><span>Age:</span> {{ $guideData['details']->age ?? 'N/A' }}</p>
                                                        <p><span>Gender:</span> {{ $guideData['details']->gender ?? 'N/A' }}</p>
                                                        <p><span>Occupation:</span> {{ $guideData['details']->occupation ?? 'N/A' }}</p>
                                                    </div>
                                                    <div class="assigned-timers-list">
                                                        @foreach($guideData['first_timers'] as $timer)
                                                            <div class="first-timer-card draggable-assigned"
                                                                 data-timer-id="{{ $timer->first_timer_id }}"
                                                                 data-timer-name="{{ $timer->first_name }} {{ $timer->last_name }}"
                                                                 data-timer-status="{{ $timer->status ?? 'Assigned' }}">
                                                                <strong><i class="fas fa-arrows-alt-v mr-2"></i>{{ $timer->first_name }} {{ $timer->last_name }}</strong>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-center text-muted">No guides match the criteria.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: UNASSIGNED FIRST TIMERS -->
                <div class="column column-right">
                    <div class="filters-box">
                        <h4><i class="fas fa-filter"></i> Filter First Timers</h4>
                        <form action="{{ route('admin.assignments.drag-drop') }}" method="GET" class="form-row">
                            <input type="hidden" name="dept_id" value="{{ $selectedDeptId }}">
                            <input type="hidden" name="guide_gender" value="{{ $guideGender }}">
                            <input type="hidden" name="guide_church_type" value="{{ $guideChurchType }}">
                            <input type="hidden" name="guide_occupation" value="{{ $guideOccupation }}">

                            <div class="form-group col-md-6"><input type="date" name="ft_start_date" class="form-control form-control-sm" value="{{ $ftStartDate }}"></div>
                            <div class="form-group col-md-6"><input type="date" name="ft_end_date" class="form-control form-control-sm" value="{{ $ftEndDate }}"></div>
                            <div class="form-group col-md-6">
                                <select name="ft_gender" class="form-control form-control-sm">
                                    <option value="">All Genders</option>
                                    <option value="Male" {{ $ftGender == 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ $ftGender == 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <select name="ft_church_type" class="form-control form-control-sm">
                                    <option value="">All Church Types</option>
                                    @foreach($churchTypes as $ct)
                                        <option value="{{ $ct->id }}" {{ $ftChurchType == $ct->id ? 'selected' : '' }}>{{ $ct->church_type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-12">
                                <select name="ft_guest_type" class="form-control form-control-sm">
                                    <option value="">All Guest Types</option>
                                    @foreach($guestTypeOptions as $option)
                                        <option value="{{ $option }}" {{ $ftGuestType == $option ? 'selected' : '' }}>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6"><button type="submit" class="btn btn-primary btn-sm btn-block">Apply</button></div>
                            <div class="col-6"><a href="{{ route('admin.assignments.drag-drop') }}" class="btn btn-secondary btn-sm btn-block">Reset</a></div>
                        </form>
                    </div>

                    <div class="content-box">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2>Unassigned ({{ $unassignedMembers->count() }})</h2>
                            <button id="suggest-assignments-btn" class="btn btn-success btn-sm mb-2"><i class="fas fa-magic"></i> Suggest</button>
                        </div>

                        <div id="suggestion-box">
                            <h4>Assignment Suggestions</h4>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>First-Timer</th>
                                            <th>Suggested Guide</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="suggestion-table-body"></tbody>
                                </table>
                            </div>
                            <hr>
                        </div>

                        <div id="unassigned-container" class="droppable-unassign">
                            @forelse($unassignedMembers as $ft)
                                <div class="first-timer-card draggable-unassigned"
                                     data-timer-id="{{ $ft->first_timer_id }}"
                                     data-timer-name="{{ $ft->first_name }} {{ $ft->last_name }}"
                                     data-timer-status="{{ $ft->status ?? 'Unassigned' }}">
                                    <strong><i class="fas fa-hand-rock mr-2"></i>{{ $ft->first_name }} {{ $ft->last_name }}</strong>
                                    @if($selectedDeptId == 23)
                                        <p><i class="fas fa-phone-alt fa-sm text-muted mr-1"></i> <span>Phone:</span> {{ $ft->phone_number ?? 'N/A' }}</p>
                                        <p><i class="fas fa-user-tag fa-sm text-muted mr-1"></i> <span>Guest Type:</span> {{ $ft->attendant_type ?? 'N/A' }}</p>
                                        <p><i class="fas fa-map-marker-alt fa-sm text-muted mr-1"></i> <span>Address:</span> {{ $ft->address ?? 'N/A' }}</p>
                                        <hr class="my-1">
                                        <p><span>Age:</span> {{ $ft->age ?? 'N/A' }} | <span>Occ:</span> {{ $ft->occupation ?? 'N/A' }}</p>
                                    @else
                                        <p><i class="fas fa-phone-alt fa-sm text-muted mr-1"></i> <span>Phone:</span> {{ $ft->phone_number ?? 'N/A' }}</p>
                                        <p><i class="fas fa-envelope fa-sm text-muted mr-1"></i> <span>Email:</span> {{ $ft->email ?? 'N/A' }}</p>
                                        <p><i class="fas fa-calendar fa-sm text-muted mr-1"></i> <span>Cohort:</span> {{ $ft->cohort_id ?? 'N/A' }} | <span>Gender:</span> {{ $ft->gender ?? 'N/A' }}</p>
                                        <p><i class="fas fa-check-circle fa-sm text-muted mr-1"></i> <span>Commitment:</span> {{ $ft->commitment ?? 'N/A' }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-center text-muted py-4">No unassigned members match the criteria.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    var selectedDeptId = parseInt($('#selected-dept-id').val());

    // --- DRAGGABLE SETUP ---
    function makeDraggable() {
        $(".draggable-unassigned, .draggable-assigned").draggable({
            revert: "invalid",
            helper: "clone",
            cursor: "grabbing",
            start: function(event, ui) {
                $(ui.helper).addClass("ui-draggable-dragging");
            }
        });
    }
    makeDraggable();

    function showMessage(msg, type) {
        var el = $("#status-message");
        el.text(msg).removeClass('success error').addClass(type).fadeIn();
        setTimeout(function() { el.fadeOut(); }, type === 'success' ? 3000 : 5000);
    }

    function getAssignUrl() {
        return '{{ route("admin.assignments.ajax-assign") }}';
    }

    function getUnassignUrl() {
        return '{{ route("admin.assignments.ajax-unassign") }}';
    }

    // --- DROPPABLE: Guide boxes (ASSIGN) ---
    $(".droppable-guide-content").droppable({
        accept: ".draggable-unassigned, .draggable-assigned",
        hoverClass: "ui-droppable-hover",
        drop: function(event, ui) {
            var guideId = $(this).data("guide-id");
            if (ui.draggable.closest('.droppable-guide-content').data("guide-id") == guideId) return;

            var draggableElement = ui.draggable;
            var firstTimerId = draggableElement.data("timer-id");
            var firstTimerName = draggableElement.data("timer-name");
            var firstTimerStatus = draggableElement.data("timer-status");

            $.ajax({
                url: getAssignUrl(),
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    Guild_name: guideId,
                    first_timer_id: firstTimerId,
                    full_name: firstTimerName,
                    status: firstTimerStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        var droppedCard = draggableElement.detach();
                        droppedCard.removeClass("draggable-unassigned").addClass("draggable-assigned");
                        droppedCard.find('strong i').removeClass('fa-hand-rock').addClass('fa-arrows-alt-v');
                        $(event.target).find(".assigned-timers-list").append(droppedCard);
                        makeDraggable();
                        showMessage(response.message, 'success');
                    } else {
                        showMessage('Error: ' + response.message, 'error');
                    }
                },
                error: function() {
                    showMessage('Error: Could not connect to the server.', 'error');
                }
            });
        }
    });

    // --- DROPPABLE: Unassigned container (UN-ASSIGN) ---
    $(".droppable-unassign").droppable({
        accept: ".draggable-assigned",
        hoverClass: "ui-droppable-hover",
        drop: function(event, ui) {
            var firstTimerId = ui.draggable.data("timer-id");

            $.ajax({
                url: getUnassignUrl(),
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    first_timer_id: firstTimerId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        ui.draggable.remove();
                        var td = response.timer_data;
                        var newCardHTML =
                            '<div class="first-timer-card draggable-unassigned" data-timer-id="' + td.first_timer_id + '" data-timer-name="' + td.first_name + ' ' + td.last_name + '" data-timer-status="' + td.status + '">' +
                                '<strong><i class="fas fa-hand-rock mr-2"></i>' + td.first_name + ' ' + td.last_name + '</strong>' +
                                '<p><span>Age:</span> ' + (td.age || 'N/A') + '</p>' +
                                '<p><span>Occupation:</span> ' + (td.occupation || 'N/A') + '</p>' +
                            '</div>';
                        $(event.target).append(newCardHTML);
                        makeDraggable();
                        showMessage(response.message, 'success');
                    } else {
                        showMessage('Error: ' + response.message, 'error');
                    }
                },
                error: function() {
                    showMessage('Error: Server connection failed.', 'error');
                }
            });
        }
    });

    // --- SUGGESTION FEATURE ---
    $('#suggest-assignments-btn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating...');

        var params = window.location.search.substring(1);

        $.ajax({
            url: '{{ route("admin.assignments.ajax-suggest") }}?' + params,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#suggestion-table-body').empty();
                if (response.success && response.suggestions.length > 0) {
                    response.suggestions.forEach(function(s) {
                        var row = '<tr data-ft-id="' + s.first_timer_id + '">' +
                            '<td>' + s.first_timer_name + '</td>' +
                            '<td>' + s.suggested_guide_name + '</td>' +
                            '<td>' +
                                '<button class="btn btn-sm btn-primary apply-suggestion" ' +
                                'data-ft-id="' + s.first_timer_id + '" ' +
                                'data-ft-name="' + s.first_timer_name + '" ' +
                                'data-ft-status="' + s.first_timer_status + '" ' +
                                'data-guide-id="' + s.suggested_guide_id + '">Apply</button>' +
                            '</td></tr>';
                        $('#suggestion-table-body').append(row);
                    });
                    $('#suggestion-box').slideDown();
                } else {
                    showMessage(response.message || 'No suggestions could be generated.', 'error');
                }
            },
            error: function() {
                showMessage('Error generating suggestions.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-magic"></i> Suggest');
            }
        });
    });

    // Apply suggestion
    $('#suggestion-table-body').on('click', '.apply-suggestion', function() {
        var applyBtn = $(this);
        var guideId = applyBtn.data('guide-id');
        var firstTimerId = applyBtn.data('ft-id');
        var firstTimerName = applyBtn.data('ft-name');
        var firstTimerStatus = applyBtn.data('ft-status');

        applyBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '{{ route("admin.assignments.ajax-assign") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                Guild_name: guideId,
                first_timer_id: firstTimerId,
                full_name: firstTimerName,
                status: firstTimerStatus
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    var cardToMove = $('.draggable-unassigned[data-timer-id="' + firstTimerId + '"]').detach();
                    var guideBox = $('.droppable-guide-content[data-guide-id="' + guideId + '"]');

                    guideBox.closest('.collapse').collapse('show');
                    cardToMove.removeClass("draggable-unassigned").addClass("draggable-assigned");
                    cardToMove.find('strong i').removeClass('fa-hand-rock').addClass('fa-arrows-alt-v');
                    guideBox.find(".assigned-timers-list").append(cardToMove);
                    makeDraggable();

                    applyBtn.closest('tr').remove();
                    if ($('#suggestion-table-body tr').length === 0) {
                        $('#suggestion-box').slideUp();
                    }
                    showMessage(response.message, 'success');
                } else {
                    applyBtn.prop('disabled', false).html('Apply');
                    showMessage('Error: ' + response.message, 'error');
                }
            },
            error: function() {
                applyBtn.prop('disabled', false).html('Apply');
                showMessage('Error assigning timer.', 'error');
            }
        });
    });
});
</script>
@endpush
