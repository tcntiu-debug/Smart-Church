@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <div class="d-flex justify-content-between">
            <div class="ms-header-text">
                <h6>TRACKING OVERVIEW</h6>
            </div>
        </div>
    </div>
    <div class="ms-panel-body pb-0">
        <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="ms-card card-twitter ms-widget ms-infographics-widget">
                    <div class="ms-card-body media">
                        <div class="media-body">
                            <p class="fs-12">First timers not assigned</p>
                            <p class="ms-card-change">{{ $statusCounts['Not Assigned'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="ms-card card-linkedin ms-widget ms-infographics-widget">
                    <div class="ms-card-body media">
                        <div class="media-body">
                            <p class="fs-12">First timers assigned</p>
                            <p class="ms-card-change">{{ $statusCounts['Assigned'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="ms-card card-facebook ms-widget ms-infographics-widget">
                    <div class="ms-card-body media">
                        <div class="media-body">
                            <p class="fs-12">First Timers Reassigned</p>
                            <p class="ms-card-change">{{ $statusCounts['Reassigned'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="ms-card card-instagram ms-widget ms-infographics-widget">
                    <div class="ms-card-body media">
                        <div class="media-body">
                            <p class="fs-12">First Timers Unassigned</p>
                            <p class="ms-card-change">{{ $statusCounts['Unassigned'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>FIRST TIMERS RECORD...</h6>
    </div>
    <div class="ms-panel-body">
        <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
            <button id="download-csv" class="btn btn-primary">Download CSV</button>
            <form class="d-flex flex-wrap align-items-center gap-2" method="GET" action="{{ route('admin.first-timers') }}">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label for="gender_filter" class="form-label">Gender</label>
                    <select name="gender_filter" id="gender_filter" class="form-control">
                        <option value="">All Genders</option>
                        <option value="Male" {{ ($genderFilter ?? '') === 'Male' ? 'selected' : '' }}>Male</option>
                        <option value="Female" {{ ($genderFilter ?? '') === 'Female' ? 'selected' : '' }}>Female</option>
                    </select>
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
            <a href="{{ route('admin.first-timers') }}" class="btn btn-secondary">Reset</a>
        </div>
        <div class="table-responsive">
            <table id="order-listing" class="table table-hover thead-primary w-100">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Hangout</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Occupation</th>
                        <th>Guest&nbsp;Type</th>
                        <th>Church&nbsp;Type</th>
                        <th>Campus</th>
                        <th>Status</th>
                        <th>Register&nbsp;Date</th>
                        <th>View</th>
                        <th>Guide</th>
                        <th>Action</th>
                        <th>Last Comment</th>
                        <th>Marital&nbsp;Status</th>
                        <th>How&nbsp;Did&nbsp;You&nbsp;Hear</th>
                        <th>Born&nbsp;Again</th>
                        <th>Water&nbsp;Baptism</th>
                        <th>Holy&nbsp;Ghost&nbsp;Baptism</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($firstTimers as $i => $ft)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            @if(($ft->attendance_count ?? 0) >= 1)
                                <i class="fas fa-check-circle text-success" title="Attended"></i>
                            @else
                                <span><a href="#" data-toggle="modal" data-target="#modal-13{{ $ft->first_timer_id }}">Register</a></span>
                            @endif
                        </td>
                        <td>{{ $ft->first_name }} {{ $ft->last_name }}</td>
                        <td>{{ $ft->phone_number }}</td>
                        <td>{{ $ft->email ?? '&mdash;' }}</td>
                        <td>{{ $ft->address ?? '&mdash;' }}</td>
                        <td>{{ $ft->gender }}</td>
                        <td>{{ $ft->age }}</td>
                        <td>{{ $ft->occupation }}</td>
                        <td>{{ $ft->attendant_type }}</td>
                        <td>{{ $ft->church_type_name ?? '&mdash;' }}</td>
                        <td>{{ $ft->campus_name ?? '&mdash;' }}</td>
                        <td><span class="badge badge-{{ $ft->status === 'Assigned' ? 'success' : ($ft->status === 'Not Assigned' ? 'warning' : 'secondary') }}">{{ $ft->status }}</span></td>
                        <td>{{ $ft->register_date ? \Carbon\Carbon::parse($ft->register_date)->format('M d, Y h:i A') : '&mdash;' }}</td>
                        <td><a class="media fs-14 p-2" href="{{ route('member.show', $ft->first_timer_id) }}">View</a></td>
                        <td>
                            @if($ft->guide_name)
                                <span><a class="media fs-14 p-2" href="#">{{ $ft->guide_name }}</a></span>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info open-manage-modal"
                                data-ft-id="{{ $ft->first_timer_id }}"
                                data-fname="{{ $ft->first_name }}"
                                data-sname="{{ $ft->last_name }}"
                                data-gender="{{ $ft->gender }}"
                                data-occupation="{{ $ft->occupation }}"
                                data-status="{{ $ft->status }}"
                                data-email="{{ $ft->email ?? '' }}"
                                data-phone="{{ $ft->phone_number }}">
                                <i class='fas fa-edit'></i> Manage
                            </button>
                        </td>
                        <td>
                            @if($ft->unassign_reason)
                                <a href="#" class="open-reason-modal"
                                    data-first-timer-id="{{ $ft->first_timer_id }}"
                                    data-first-timer-name="{{ $ft->first_name }} {{ $ft->last_name }}"
                                    data-unassign-reason="{{ $ft->unassign_reason }}">
                                    {{ \Illuminate\Support\Str::limit($ft->unassign_reason, 30) }}
                                </a>
                            @else
                                <span class="text-muted">&mdash;</span>
                            @endif
                        </td>
                        <td>{{ $ft->marital_status ?? '&mdash;' }}</td>
                        <td>{{ $ft->how_did_you_hear ?? '&mdash;' }}</td>
                        <td>{{ $ft->born_again ?? '&mdash;' }}</td>
                        <td>{{ $ft->water_baptism ?? '&mdash;' }}</td>
                        <td>{{ $ft->holy_ghost_baptism ?? '&mdash;' }}</td>
                    </tr>
                    <!-- Hangout Modal (inside loop for each first timer) -->
                    <div class="modal fade" id="modal-13{{ $ft->first_timer_id }}" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-max">
                            <div class="modal-content">
                                <div class="modal-body">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h5>Register {{ $ft->first_name }} {{ $ft->last_name }} for Hangout</h5>
                                    <form class="needs-validation clearfix" method="POST" action="{{ route('admin.ft.hangout-register') }}">
                                        @csrf
                                        <div class="form-row">
                                            <div class="col-xl-12 col-md-12">
                                                <div class="ms-panel ms-panel-fh">
                                                    <div class="ms-panel-body">
                                                        <div class="form-row">
                                                            <div class="col-md-12 mb-3">
                                                                <label>More of what you do</label>
                                                                <div class="input-group">
                                                                    <input name="occupation2" type="text" required class="form-control" placeholder="e.g. Student, Engineer">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-12 mb-3">
                                                                <label>Membership Status</label>
                                                                <div class="input-group">
                                                                    <select name="member_status" class="form-control" required>
                                                                        <option value="">Select</option>
                                                                        <option value="Yes">Yes</option>
                                                                        <option value="No">No</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-12 mb-3">
                                                                <label>Department</label>
                                                                <div class="input-group">
                                                                    <select name="department" class="form-control">
                                                                        <option value="">Select Department</option>
                                                                        @foreach($departments as $dept)
                                                                            <option value="{{ $dept }}">{{ $dept }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <input name="first_timer_id" type="hidden" value="{{ $ft->first_timer_id }}">
                                                        <button class="btn btn-primary d-block float-right" type="submit">Proceed</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr><td colspan="23" class="text-center py-4 text-muted">No first timers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="d-flex justify-content-center mt-3">
                {{ $firstTimers->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- == FULLY FEATURED "MANAGE" MODAL (ASSIGN & UPDATE)   == -->
<!-- ======================================================= -->
<div class="modal fade" id="manageFtModal" tabindex="-1" role="dialog" aria-labelledby="manageFtModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageFtModalLabel">Manage: <span id="modal-ft-fullname-title"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <!-- Info Bar -->
                <ol class="breadcrumb pl-0">
                    <li class="breadcrumb-item"><span class="font-weight-bold mr-1">Full Name:</span> <span id="modal-ft-fullname"></span></li>
                    <li class="breadcrumb-item"><span class="font-weight-bold mr-1">Occupation:</span> <span id="modal-ft-occupation"></span></li>
                    <li class="breadcrumb-item"><span class="font-weight-bold mr-1">Gender:</span> <span id="modal-ft-gender"></span></li>
                </ol>

                <div class="row">
                   
                    <!-- Column 2: MORE ACTION Form -->
                    <div class="col-12 mb-4">
                        <div class="ms-panel ms-panel-fh">
                            <div class="ms-panel-header"><h6>MORE ACTION</h6></div>
                            <div class="ms-panel-body">
                                <form id="more-action-form" method="POST" action="{{ route('admin.ft.perform-action') }}">
                                    @csrf
                                    <label for="more_action">Status Update</label>
                                    <select name="more_action" id="more_action" class="form-control" required>
                                        <option value="">Select</option>
                                        <option value="Disable">Disable</option>
                                        <option value="Unassigned">Unassigned</option>
                                        <option value="Integrated">Integrated</option>
                                        <option value="Delete">Delete</option>
                                    </select>
                                    <div id="unassign-reason-wrapper" class="mt-2" style="display: none;">
                                        <label for="unassign_reason">Reason for Unassignment <span class="text-danger">*</span></label>
                                        <textarea name="unassign_reason" id="unassign_reason" class="form-control" rows="3" placeholder="Please enter the reason for unassigning this first timer..." maxlength="1000"></textarea>
                                        <small class="text-muted">Max 1000 characters</small>
                                    </div>
                                    <input name="first_timer_id" type="hidden" value="">
                                    <button name="other_action" class="btn btn-primary float-right mt-3" type="submit">Submit</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: NAMES UPDATE Form -->
                    <div class="col-12">
                        <div class="ms-panel ms-panel-fh">
                            <div class="ms-panel-header"><h6>NAMES UPDATE</h6></div>
                            <div class="ms-panel-body">
                                <form id="names-update-form" method="POST" action="{{ route('admin.ft.update-details') }}">
                                    @csrf
                                    <div class="form-row">
                                        <div class="col-md-6 mb-3">
                                            <label>First name</label>
                                            <input type="text" class="form-control" name="first_name" id="modal_update_fname" placeholder="First name" required value="">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label>Last name</label>
                                            <input type="text" class="form-control" name="last_name" id="modal_update_sname" placeholder="Last name" required value="">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="col-md-6 mb-3">
                                            <label for="modal_update_email">Email Address</label>
                                            <input type="email" name="email" id="modal_update_email" class="form-control" placeholder="Email">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="modal_update_phone">Phone Number</label>
                                            <input type="tel" name="phone_number" id="modal_update_phone" class="form-control" placeholder="Phone">
                                        </div>
                                    </div>
                                    <input name="first_timer_id" type="hidden" value="">
                                    <button name="update_ft" class="btn btn-success float-right" type="submit">Update Details</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- == REASON MODAL (displays full unassign reason)     == -->
<!-- ======================================================= -->
<div class="modal fade" id="reasonModal" tabindex="-1" role="dialog" aria-labelledby="reasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reasonModalLabel">Last Comment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="reasonModalBody">
                <p id="reasonModalText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- == CHAT MODAL                                        == -->
<div class="modal fade" id="chatModal" tabindex="-1" role="dialog" aria-labelledby="chatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chatModalLabel">Chat</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body ms-scrollable" id="chatModalBody">
                <!-- Unassign reason will be displayed here if exists -->
                <div id="unassign-reason-display" class="alert alert-warning mb-3" style="display: none;">
                    <strong>Unassign Reason:</strong> <span id="unassign-reason-text"></span>
                </div>
            </div>
            <div class="chat-input-area p-3 border-top">
                <form id="chatModalForm" class="w-100">
                    <input type="hidden" name="first_timer_id" id="chat-first-timer-id">
                    <div class="input-group">
                        <input type="text" name="message" class="form-control" placeholder="Type your feedback..." required autocomplete="off">
                        <div class="input-group-append"><button class="btn btn-primary" type="submit">Send</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .ms-chat-bubble {
        max-width: 80%;
        width: fit-content;
        margin-bottom: 1rem;
    }
    .ms-chat-bubble .media-body {
        padding: .75rem 1rem;
        border-radius: 1rem;
    }
    .ms-chat-incoming .media-body {
        background-color: #f0f0f7;
        border-top-left-radius: 0;
    }
    .ms-chat-outgoing {
        margin-left: auto;
    }
    .ms-chat-outgoing .media-body {
        background-color: #d7f8b5;
        border-top-right-radius: 0;
    }
    .ms-chat-user {
        font-weight: 700;
        font-size: .8rem;
        margin-bottom: .25rem;
    }
    .ms-chat-time {
        font-size: .75rem;
        color: #6c757d;
        margin-top: .25rem;
    }
    #chatModal .chat-input-area {
        position: relative;
        z-index: 10;
    }
    #chatModalBody {
        max-height: 60vh;
    }
</style>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // ========== DOWNLOAD CSV ==========
        $('#download-csv').on('click', function() {
            const escapeCsvCell = function(cellData) {
                if (cellData === null || cellData === undefined) return '';
                let cellText = cellData.toString().trim();
                if (cellText.search(/("|,|\n)/g) >= 0) {
                    cellText = '"' + cellText.replace(/"/g, '""') + '"';
                }
                return cellText;
            };

            let csvRows = [];
            const headersToExclude = ['Action', 'Last Comment', 'View', 'Hangout'];
            const headers = [];
            $('#order-listing thead tr th').each(function() {
                const headerText = $(this).text().trim();
                if (!headersToExclude.includes(headerText)) {
                    headers.push(escapeCsvCell(headerText));
                }
            });
            csvRows.push(headers.join(','));

            $('#order-listing tbody tr').each(function() {
                const rowData = [];
                $(this).find('td, th').each(function(index) {
                    const headerText = $('#order-listing thead tr th').eq(index).text().trim();
                    if (!headersToExclude.includes(headerText)) {
                        rowData.push(escapeCsvCell($(this).text().trim()));
                    }
                });
                csvRows.push(rowData.join(','));
            });

            const csvString = csvRows.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                const today = new Date().toISOString().slice(0, 10);
                const filename = `first_timers_export_${today}.csv`;
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        });

        // ========== MANAGE MODAL ==========
        $(document).on('click', '.open-manage-modal', function() {
            var button = $(this);
            var modal = $('#manageFtModal');
            var ftId = button.data('ft-id');
            var fName = button.data('fname');
            var sName = button.data('sname');
            var gender = button.data('gender');
            var occupation = button.data('occupation');
            var status = button.data('status');
            var email = button.data('email') || '';
            var phone = button.data('phone') || '';
            var fullName = fName + ' ' + sName;

            // Set title
            modal.find('#modal-ft-fullname-title').text(fullName);
            // Set info bar
            modal.find('#modal-ft-fullname').text(fullName);
            modal.find('#modal-ft-occupation').text(occupation);
            modal.find('#modal-ft-gender').text(gender);

            modal.find('input[name="first_timer_id"]').val(ftId);
            modal.find('input[name="status"]').val(status);
            modal.find('input[name="full_name"]').val(fullName);
            modal.find('#modal_gender').val(gender);
            modal.find('#modal_update_fname').val(fName);
            modal.find('#modal_update_sname').val(sName);
            modal.find('#modal_update_email').val(email);
            modal.find('#modal_update_phone').val(phone);

            if (status === "Integrated" || status === "Disable") {
                modal.find('button[name="assignment"]').hide();
            } else {
                modal.find('button[name="assignment"]').show();
            }

            // Only show "Unassigned" option in more_action if status is "Assigned"
            var $moreAction = modal.find('#more_action');
            var $unassignedOption = $moreAction.find('option[value="Unassigned"]');
            if (status === "Assigned") {
                $unassignedOption.show();
            } else {
                $unassignedOption.hide();
                // If "Unassigned" is currently selected, reset it
                if ($moreAction.val() === 'Unassigned') {
                    $moreAction.val('');
                }
            }

            modal.modal('show');
        });

        // Load occupations when modal opens
        $('#manageFtModal').on('shown.bs.modal', function(e) {
            // Load occupation list
            $.ajax({
                url: '{{ route("admin.ft.occupations") }}',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var $occupationSelect = $('#modal_occupation');
                        $occupationSelect.empty().append($('<option>', { value: 'All', text: 'All' }));
                        $.each(response.occupations, function(index, value) {
                            $occupationSelect.append($('<option>', { value: value, text: value }));
                        });
                        // Set the occupation from the button data
                        var ftOccupation = $('.open-manage-modal').data('occupation') || 'All';
                        $occupationSelect.val(ftOccupation).trigger('change');
                    }
                },
                error: function() {
                    $('#modal_occupation').html('<option>Could not load occupations</option>');
                }
            });
        });

        // Load guides when occupation or gender changes
        $("#modal_occupation, #modal_gender").on('change', function() {
            var occupation = $("#modal_occupation").val();
            var gender = $("#modal_gender").val();
            $('#modal_guild_name').html('<option>Loading guides...</option>');
            $.ajax({
                type: 'POST',
                url: '{{ route("admin.ft.guides") }}',
                data: {
                    '_token': '{{ csrf_token() }}',
                    'occupation': occupation,
                    'gender': gender
                },
                success: function(result) {
                    $('#modal_guild_name').html(result);
                },
                error: function() {
                    $('#modal_guild_name').html('<option>Error loading guides</option>');
                }
            });
        });

        // ========== CHAT MODAL ==========
        function loadChatHistory(firstTimerId) {
            const chatBody = $('#chatModalBody');
            const existingReason = $('#unassign-reason-display');
            // Keep the unassign reason display, replace everything else
            chatBody.find(':not(#unassign-reason-display)').remove();
            chatBody.append('<div class="text-center p-3" id="chat-loading"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
            $.ajax({
                url: '{{ route("admin.ft.chat") }}',
                type: 'GET',
                dataType: 'json',
                data: { first_timer_id: firstTimerId },
                success: function(response) {
                    $('#chat-loading').remove();
                    if (response.success && response.messages.length > 0) {
                        response.messages.forEach(msg => {
                            const alignClass = msg.user_id == response.current_user_id ? 'ms-chat-outgoing' : 'ms-chat-incoming';
                            const roleText = msg.member_role ? `(${escapeHtml(msg.member_role)})` : '';
                            const bubbleHtml = `<div class="ms-chat-bubble media ${alignClass} clearfix"><div class="media-body"><div class="ms-chat-user">${escapeHtml(msg.user_name)} ${roleText}</div><p class="mb-0">${escapeHtml(msg.message)}</p><p class="ms-chat-time mb-0">${msg.created_at}</p></div></div>`;
                            chatBody.append(bubbleHtml);
                        });
                    } else {
                        chatBody.append('<p class="text-center text-muted">No feedback yet. Start the conversation!</p>');
                    }
                    chatBody.scrollTop(chatBody[0].scrollHeight);
                },
                error: function() {
                    $('#chat-loading').remove();
                    chatBody.append('<p class="text-center text-danger">Failed to load chat history.</p>');
                }
            });
        }

        $(document).on('click', '.open-chat-modal', function(e) {
            e.preventDefault();
            const button = $(this);
            const firstTimerId = button.data('first-timer-id');
            const firstTimerName = button.data('first-timer-name');
            const unassignReason = button.data('unassign-reason') || '';

            $('#chatModalLabel').text(`Feedback On ${firstTimerName}`);

            // Show/hide unassign reason
            if (unassignReason) {
                $('#unassign-reason-text').text(unassignReason);
                $('#unassign-reason-display').show();
            } else {
                $('#unassign-reason-display').hide();
                $('#unassign-reason-text').text('');
            }

            $('#chat-first-timer-id').val(firstTimerId);
            loadChatHistory(firstTimerId);
            $('#chatModal').modal('show');
        });

        $('#chatModalForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const firstTimerId = $('#chat-first-timer-id').val();
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                url: '{{ route("admin.ft.chat-send") }}',
                type: 'POST',
                dataType: 'json',
                data: {
                    '_token': '{{ csrf_token() }}',
                    'first_timer_id': firstTimerId,
                    'message': form.find('input[name="message"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        form.find('input[name="message"]').val('');
                        loadChatHistory(firstTimerId);
                    } else {
                        alert('Error: ' + (response.message || 'Could not send message.'));
                    }
                },
                error: function() {
                    alert('An unexpected error occurred. Please try again.');
                },
                complete: function() {
                    submitBtn.prop('disabled', false).html('Send');
                }
            });
        });

        // ========== REASON MODAL (view full unassign reason) ==========
        $(document).on('click', '.open-reason-modal', function(e) {
            e.preventDefault();
            const button = $(this);
            const firstTimerName = button.data('first-timer-name');
            const unassignReason = button.data('unassign-reason') || '';

            $('#reasonModalLabel').text(`Last Comment - ${firstTimerName}`);
            $('#reasonModalText').text(unassignReason);
            $('#reasonModal').modal('show');
        });

        function escapeHtml(text) {
            if (text === null || text === undefined) return "";
            var map = {
                '&': 'amp;',
                '<': 'lt;',
                '>': 'gt;',
                '"': 'quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // ========== UNASSIGN REASON TOGGLE ==========
        $('#more_action').on('change', function() {
            if ($(this).val() === 'Unassigned') {
                $('#unassign-reason-wrapper').slideDown();
                $('#unassign_reason').prop('required', true);
            } else {
                $('#unassign-reason-wrapper').slideUp();
                $('#unassign_reason').prop('required', false);
            }
        });

        // Also reset when modal closes
        $('#manageFtModal').on('hidden.bs.modal', function() {
            $('#unassign-reason-wrapper').hide();
            $('#unassign_reason').val('').prop('required', false);
            $('#more_action').val('');
        });

        // ========== DATATABLE ==========
        $('#order-listing').DataTable({
            "aLengthMenu": [[5, 10, 15, -1], [5, 10, 15, "All"]],
            "iDisplayLength": 10,
            "language": { search: "" },
            "order": []
        });
    });
</script>
@endpush
