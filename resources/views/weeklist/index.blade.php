@extends('layouts.app')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* The touch point and guest type filters are rendered as chip-style
       dropdowns (Select2), the same Material chips as
       Profile -> My Department(s). The native multi-selects are hidden by the
       plugin; if the CDN is unreachable they stay usable list boxes. */
    .chip-picker .select2-container {
        width: 100% !important;
    }
    .chip-picker .select2-container--default .select2-selection--multiple {
        min-height: 38px;
        padding: 3px 6px;
        background-color: #fff;
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem !important;
        cursor: text;
    }
    .chip-picker .select2-container--default.select2-container--focus .select2-selection--multiple,
    .chip-picker .select2-container--default.select2-container--open .select2-selection--multiple {
        border-color: #80bdff !important;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
    .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        padding: 2px 0 !important;
    }
    .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__choice {
        display: inline-flex !important;
        align-items: center !important;
        background-color: #e0e0e0 !important;
        border: none !important;
        border-radius: 16px !important;
        padding: 2px 8px 2px 12px !important;
        margin: 0 !important;
        font-size: 0.8125rem !important;
        line-height: 1.5 !important;
        color: rgba(0, 0, 0, 0.87) !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
        transition: box-shadow 0.2s ease;
    }
    .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__choice:hover {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
    }
    .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        margin-left: 4px !important;
        font-size: 16px !important;
        line-height: 1 !important;
        color: rgba(0, 0, 0, 0.54) !important;
        cursor: pointer !important;
        order: 1 !important;
    }
    .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: rgba(0, 0, 0, 0.87) !important;
    }
    .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
        color: #6c757d;
    }
    .chip-picker .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
        height: 26px;
        margin: 2px 0 !important;
        font-size: 0.875rem !important;
    }
    /* Highlight colour for the open list (only this page loads Select2). */
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #ff8306;
    }
    /* Dark twins (docs/DISPLAY-MODE.md): the plugin ships a white surface and
       light chips, which would glow on the dark card. */
    .ms-dark-theme .chip-picker .select2-container--default .select2-selection--multiple {
        background-color: #2a2e5b !important;
        border-color: #3b4270 !important;
    }
    .ms-dark-theme .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #323a67 !important;
        color: #dfe3ff !important;
    }
    .ms-dark-theme .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #9db2ff !important;
    }
    .ms-dark-theme .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ffffff !important;
    }
    .ms-dark-theme .chip-picker .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
        color: #5c6dc0;
    }
    .ms-dark-theme .chip-picker .select2-search--inline .select2-search__field {
        color: #dfe3ff !important;
    }
    .ms-dark-theme .chip-picker .select2-search--inline .select2-search__field::placeholder {
        color: #5c6dc0;
    }
    .ms-dark-theme .select2-dropdown {
        background-color: #252851;
        border-color: #323a67;
    }
    .ms-dark-theme .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #323a67;
        color: #dfe3ff;
    }
    .ms-dark-theme .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #ff8306;
        color: #ffffff;
    }
    .ms-dark-theme .select2-container--default .select2-search--dropdown .select2-search__field {
        background-color: #323a67;
        border-color: #3b4270;
        color: #dfe3ff;
    }
</style>
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h6>{{ $page_title }}</h6>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('weeklist.index') }}" class="mb-4">
                <div class="row align-items-end">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="start_date">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $start_date_filter }}">
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="end_date">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $end_date_filter }}">
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="guest_type">Guest Type</label>
                        <div class="chip-picker">
                            <select class="form-control" name="guest_type[]" id="guest_type" multiple
                                    data-placeholder="All Guest Types">
                                @foreach($guestTypes as $type)
                                    <option value="{{ $type }}" {{ in_array($type, $guest_type_filter, true) ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label for="church_type_id">Church Type</label>
                        <select class="form-control" name="church_type_id" id="church_type_id">
                            <option value="All">All Church Types</option>
                            @foreach($churchTypes as $ct)
                                <option value="{{ $ct->id }}" {{ $church_type_id_filter == $ct->id ? 'selected' : '' }}>{{ $ct->church_type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row align-items-end">
                    <div class="col-lg-8 col-md-12 mb-3">
                        <label for="touchpoints">Call List Touch Point(s)</label>
                        <div class="chip-picker">
                            <select class="form-control" name="touchpoints[]" id="touchpoints" multiple
                                    data-placeholder="Select touch point(s)">
                                @foreach($touchpointOptions as $touchpoint)
                                    <option value="{{ $touchpoint }}" {{ in_array($touchpoint, $selectedTouchpoints, true) ? 'selected' : '' }}>{{ $touchpoint }}</option>
                                @endforeach
                            </select>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            These members staff the Weekly Call List. Pick one or more teams - every selected
                            team is combined into one caller pool.
                        </small>
                    </div>
                    <div class="col-lg-4 col-md-12 mb-3">
                        <button type="submit" class="btn btn-primary mb-2">Apply</button>
                        <a href="{{ route('weeklist.index') }}" class="btn btn-secondary mb-2">Reset</a>
                    </div>
                </div>
            </form>

            <!-- Google Meet Link -->
            <p class="mb-3">
                <a href="https://meet.google.com/mus-whut-qus" target="_blank" class="btn btn-link">
                    <i class="fas fa-video"></i> Click to Join Prayers Meetings
                </a>
            </p>

            <div class="table-responsive">
                <table id="first-timers-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Full Name</th>
                            <th>Guest Type</th>
                            <th>Church Type</th>
                            <th>Gender</th>
                            <th>Guide</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $index => $row)
                            @php
                                $whatsappPhone = $row->whatsapp_phone;
                                $encodedMessage = urlencode($row->whatsapp_message);
                                $guideWhatsAppMessage = "";
                                if ($row->isGuideAssigned) {
                                    $salutation = ($row->guide_gender == "Male") ? "Hello Sir. " : "Hello Ma. ";
                                    $guideWhatsAppMessage = $salutation . "I trust this meets you well?\n\nA first-timer has been assigned to you - " . $row->first_name . " " . $row->last_name . ".\n\n*Login to the TIU APP for details.*";
                                    $guideWhatsAppMessage = urlencode($guideWhatsAppMessage);
                                }
                            @endphp
                            <tr class="{{ $row->isDuplicate ? 'table-warning' : '' }}">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    @if(in_array($member_role, ['Super User', 'Admin']) && $row->status != "Disable")
                                        <a href="https://wa.me/{{ $whatsappPhone }}?text={{ $encodedMessage }}" target="_blank" title="Send WhatsApp message to {{ $row->first_name }} {{ $row->last_name }}">
                                            <i class="fab fa-whatsapp" style="color: green; margin-right: 5px;"></i>
                                        </a>
                                    @endif
                                    {{ $row->first_name }} {{ $row->last_name }}
                                    @if($row->isDuplicate)
                                        <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Possible Duplicate</span>
                                    @endif
                                </td>
                                <td>{{ $row->attendant_type }}</td>
                                <td>{{ $row->church_type_name ?? 'N/A' }}</td>
                                <td>{{ $row->gender ?? 'N/A' }}</td>
                                <td>
                                    @if($row->isGuideAssigned && $row->status != "Disable")
                                        <a href="https://wa.me/{{ $row->guide_phone }}?text={{ $guideWhatsAppMessage }}" target="_blank" title="Contact guide {{ $row->guide_name }}">
                                            <i class="fab fa-whatsapp" style="color: green; margin-right: 5px;"></i>
                                            {{ $row->guide_name }}
                                        </a>
                                    @else
                                        Unassigned
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No records found for the selected period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- View Call List button, plus the admin-only bulk actions -->
            <div class="text-center mt-4">
                <button type="button" class="btn btn-info btn-lg mb-2" data-toggle="modal" data-target="#callerModal">
                    <i class="fas fa-phone-alt"></i> View Call List
                </button>

                @if($isAdmin)
                    <button type="button" id="unassign-all-guides" class="btn btn-warning btn-lg mb-2 ml-2">
                        <i class="fas fa-user-slash"></i> Unassign all guides
                    </button>
                    <a href="{{ route('weeklist.guide-history') }}" class="btn btn-secondary btn-lg mb-2 ml-2">
                        <i class="fas fa-history"></i> Guide history
                    </a>
                    <div class="text-muted small mt-2">
                        <i class="fas fa-info-circle"></i>
                        These two work on every first timer in the list above (Admin / Super User only).
                        "Assign all callers" is inside the call list - open <strong>View Call List</strong> above.
                    </div>
                @endif

                <div id="bulk-action-status" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Call List Modal -->
<div class="modal fade" id="callerModal" tabindex="-1" role="dialog" aria-labelledby="callerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="callerModalLabel">
                    <i class="fas fa-phone-volume"></i> Weekly Call List ({{ $page_title }})
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border py-2 mb-3">
                    <i class="fas fa-users"></i>
                    <strong>Caller team:</strong>
                    {{ $selectedTouchpoints ? implode(', ', $selectedTouchpoints) : 'No touch point available' }}
                    <span class="text-muted">(change the "Call List Touch Point(s)" filter above and press Apply)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Guest Type</th>
                                <th>Caller (select to change)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $index => $row)
                                @php
                                    $currentCallerId = $assignments[$row->first_timer_id] ?? null;
                                    $phoneLink = \Illuminate\Support\Str::startsWith($row->phone_number, '0')
                                        ? '234' . substr($row->phone_number, 1)
                                        : $row->phone_number;
                                @endphp
                                <tr data-ftid="{{ $row->first_timer_id }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->first_name }} {{ $row->last_name }}</td>
                                    <td><a href="tel:{{ $phoneLink }}">{{ $row->phone_number }}</a></td>
                                    <td>{{ $row->gender }}</td>
                                    <td>{{ ($row->age !== null && $row->age !== '') ? $row->age : 'Not specified' }}</td>
                                    <td>{{ $row->attendant_type }}</td>
                                    <td>
                                        <select class="form-control caller-select" data-ftid="{{ $row->first_timer_id }}">
                                            <option value="">-- Auto (Round-robin) --</option>
                                            @foreach($callers as $caller)
                                                <option value="{{ $caller->tiu_member_id }}" {{ (string) $currentCallerId === (string) $caller->tiu_member_id ? 'selected' : '' }}>
                                                    {{ $caller->full_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="save-status" id="status-{{ $row->first_timer_id }}"></span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">No records to display.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($callers->isEmpty())
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        No callers were found in the selected touch point(s):
                        {{ $selectedTouchpoints ? implode(', ', $selectedTouchpoints) : 'none available' }}.
                        Add members to those touch points to populate this list.
                    </div>
                @else
                    <hr>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Select a caller from the dropdown. Changes are saved automatically. Choose "-- Auto (Round-robin) --" to reset.
                    </small>
                @endif
            </div>
            <div class="modal-footer">
                @if($isAdmin)
                    <div class="mr-auto text-left">
                        <button type="button" id="assign-all-callers" class="btn btn-success">
                            <i class="fas fa-users"></i> Assign all callers
                        </button>
                        <div id="assign-guides-status" class="small mt-1"></div>
                        <div class="text-muted small mt-1">
                            <i class="fas fa-info-circle"></i>
                            Hands every first timer above to the caller in its row - the same follow-up a drag and drop makes on the assignment board.
                        </div>
                    </div>
                @endif
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printCallList();">Print List</button>
            </div>
        </div>
    </div>
</div>

<style>
    @media (max-width: 767px) {
        #first-timers-table thead { display: none; }
        #first-timers-table, #first-timers-table tbody, #first-timers-table tr, #first-timers-table td { display: block; width: 100%; }
        #first-timers-table tr { margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 5px; }
        #first-timers-table td { text-align: right; padding-left: 50%; position: relative; border-bottom: 1px solid #eee; padding-top: 12px; padding-bottom: 12px; }
        #first-timers-table td::before { content: attr(data-label); position: absolute; left: 15px; width: 45%; padding-right: 10px; white-space: nowrap; text-align: left; font-weight: bold; }
    }
</style>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    var groupedPrintData = @json($groupedForPrint);
    var printTitle = @json($page_title . ($selectedTouchpoints ? ' | Caller team: ' . implode(', ', $selectedTouchpoints) : ''));
    var selectedTouchpoints = @json($selectedTouchpoints);

    $(document).ready(function () {
        // Turn the touch point and guest type filters into chip-style
        // dropdowns: the picked values show as removable chips.
        // closeOnSelect: false keeps the list open so several values can be
        // picked in one go. Without the plugin (offline CDN) the native
        // multi-selects stay usable.
        if ($.fn.select2) {
            $('#touchpoints').select2({
                width: '100%',
                placeholder: 'Select touch point(s)',
                closeOnSelect: false
            });

            $('#guest_type').select2({
                width: '100%',
                placeholder: 'All Guest Types',
                closeOnSelect: false
            });
        }

        // Persist the caller change as soon as the dropdown changes.
        $(document).on('change', '.caller-select', function () {
            var selectEl = $(this);
            var ftId = selectEl.data('ftid');
            var callerId = selectEl.val();
            var statusSpan = $('#status-' + ftId);

            statusSpan.html('<i class="fas fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: '{{ route('weeklist.assign-caller') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    first_timer_id: ftId,
                    caller_member_id: callerId,
                    touchpoints: selectedTouchpoints
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        statusSpan.html('<i class="fas fa-check-circle text-success"></i> Saved');
                        setTimeout(function () { statusSpan.html(''); }, 2000);
                    } else {
                        statusSpan.html('<i class="fas fa-exclamation-circle text-danger"></i> ' + (response.message || 'Error'));
                    }
                },
                error: function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Save failed';
                    statusSpan.html('<i class="fas fa-exclamation-circle text-danger"></i> ' + msg);
                }
            });
        });
    });

    @if($isAdmin)
    // ------------------------------------------------------------------
    // Admin-only bulk actions: one click for the whole filtered window -
    // hand the call list to its callers (inside the modal), release every
    // guide, or open the guide history.
    // ------------------------------------------------------------------
    function runBulkAction(url, confirmText, statusSelector, button) {
        var statusEl = $(statusSelector || '#bulk-action-status');
        var buttonEl = button ? $(button) : $();

        if (!window.confirm(confirmText)) {
            return;
        }

        buttonEl.prop('disabled', true);
        statusEl.html('<i class="fas fa-spinner fa-spin"></i> Working...');

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                start_date: @json($start_date_filter),
                end_date: @json($end_date_filter),
                guest_type: @json($guest_type_filter),
                church_type_id: @json($church_type_id_filter),
                touchpoints: selectedTouchpoints
            },
            dataType: 'json',
            success: function (response) {
                if (response && response.success) {
                    statusEl.html('<i class="fas fa-check-circle text-success"></i> ' + (response.message || 'Done'));
                    // Reload so the caller list and the guide column show what
                    // is now stored.
                    window.setTimeout(function () { window.location.reload(); }, 1200);
                } else {
                    buttonEl.prop('disabled', false);
                    statusEl.html('<i class="fas fa-exclamation-circle text-danger"></i> ' + ((response && response.message) || 'Action failed'));
                }
            },
            error: function (xhr) {
                buttonEl.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Action failed';
                statusEl.html('<i class="fas fa-exclamation-circle text-danger"></i> ' + msg);
            }
        });
    }

    // The button inside the Weekly Call List modal: one click hands every first
    // timer the modal is showing to the caller in its row, as the real follow-up
    // assignment - the same write a drag and drop makes on /admin-assign-dragdrop,
    // but for the whole list at once.
    $('#assign-all-callers').on('click', function () {
        runBulkAction(
            '{{ route('weeklist.assign-guides') }}',
            'Hand every first timer in this list to the caller shown in its row? Each one becomes that caller\'s follow-up, exactly as a drag and drop on the assignment board.',
            '#assign-guides-status',
            this
        );
    });

    // The toolbar under the list: release the guides of the whole window.
    $('#unassign-all-guides').on('click', function () {
        runBulkAction(
            '{{ route('weeklist.unassign-guides') }}',
            'Unassign the guide of every first timer shown? Their follow-up notes and comments are kept.',
            '#bulk-action-status',
            this
        );
    });
    @endif

    function buildCallerBlock(caller, persons) {
        var html = '<div class="caller-group"><strong>' + escapeHtml(caller) + ':</strong><br>';
        for (var i = 0; i < persons.length; i++) {
            var p = persons[i];
            var line = escapeHtml(p.name) + ' (' + escapeHtml(p.phone) + ') - ' + escapeHtml(p.gender) + ' - ' + escapeHtml(p.guest_type) + ' - ' + escapeHtml(p.age);
            html += '&nbsp;&nbsp;&nbsp;' + line + '<br>';
        }
        return html + '</div><br>';
    }

    function printCallList() {
        if (!groupedPrintData || Object.keys(groupedPrintData).length === 0) {
            alert('No data to print.');
            return;
        }

        var contentHtml = '<div class="header"><h2>Weekly Call List</h2><p>' + printTitle + '</p></div>';
        var sortedCallers = Object.keys(groupedPrintData).sort();

        for (var i = 0; i < sortedCallers.length; i++) {
            var caller = sortedCallers[i];
            if (caller === 'Unassigned') continue;
            contentHtml += buildCallerBlock(caller, groupedPrintData[caller]);
        }
        if (groupedPrintData['Unassigned'] && groupedPrintData['Unassigned'].length > 0) {
            contentHtml += buildCallerBlock('Unassigned', groupedPrintData['Unassigned']);
        }

        var fullHtml = '<!DOCTYPE html><html><head><title>Weekly Call List - Grouped by Caller</title>'
            + '<style>'
            + 'body{font-family:Arial,sans-serif;margin:20px;line-height:1.5;}'
            + '.header{text-align:center;margin-bottom:30px;}'
            + '.header h2{margin:0;}'
            + '.caller-group{margin-bottom:15px;}'
            + '.caller-group strong{font-size:1.1em;display:inline-block;margin-bottom:5px;}'
            + '.footer{margin-top:30px;font-size:12px;text-align:center;color:#777;}'
            + '@media print{body{margin:0;}}'
            + '</style></head><body>'
            + contentHtml
            + '<div class="footer">Printed on ' + new Date().toLocaleString() + '</div>'
            + '</body></html>';

        var printWindow = window.open('', '_blank');
        printWindow.document.write(fullHtml);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    }

    function escapeHtml(str) {
        if (str === null || str === undefined || str === '') return '';
        return String(str).replace(/[&<>]/g, function (m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
</script>
@endpush