@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h6>{{ $page_title }}</h6>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('weeklist.index') }}" class="mb-4">
                <div class="row align-items-end">
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="start_date">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $start_date_filter }}">
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="end_date">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $end_date_filter }}">
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="guest_type">Guest Type</label>
                        <select class="form-control" name="guest_type" id="guest_type">
                            <option value="">All Guest Types</option>
                            @foreach($guestTypes as $type)
                                <option value="{{ $type }}" {{ $guest_type_filter == $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="church_type_id">Church Type</label>
                        <select class="form-control" name="church_type_id" id="church_type_id">
                            <option value="All">All Church Types</option>
                            @foreach($churchTypes as $ct)
                                <option value="{{ $ct->id }}" {{ $church_type_id_filter == $ct->id ? 'selected' : '' }}>{{ $ct->church_type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <button type="submit" class="btn btn-primary w-100 mb-2">Apply</button>
                        <a href="{{ route('weeklist.index') }}" class="btn btn-secondary w-100">Reset</a>
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

            <!-- View Call List button -->
            <div class="text-center mt-4">
                <button type="button" class="btn btn-info btn-lg" data-toggle="modal" data-target="#callerModal">
                    <i class="fas fa-phone-alt"></i> View Call List
                </button>
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
                        No callers were found in the "{{ $callerSubgroup }}" subgroup. Add members to that subgroup to populate this list.
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
<script>
    var groupedPrintData = @json($groupedForPrint);
    var printTitle = @json($page_title);

    $(document).ready(function () {
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
                    caller_member_id: callerId
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