@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active">Daily Bus Attendance</li>
            </ol>
        </nav>

        <div class="ms-panel">
            <div class="ms-panel-header d-flex justify-content-between">
                <h6>Daily Bus Attendance - {{ date('l, d M Y') }}</h6>
            </div>
            <div class="ms-panel-body">
                <form method="GET" class="row mb-4">
                    <div class="col-md-4 mb-2">
                        <label>Filter by Route</label>
                        <select name="route_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- All Routes --</option>
                            @foreach($routes as $route)
                                <option value="{{ $route->route_id }}" {{ $routeFilter == $route->route_id ? 'selected' : '' }}>
                                    {{ $route->route_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label>Search Member</label>
                        <input type="text" name="search" class="form-control" placeholder="Name or Phone..." value="{{ $searchQuery }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">Apply</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>Member Name</th>
                                <th>Phone</th>
                                <th>Route & Stop</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($members as $m)
                                <tr id="member-row-{{ $m->tiu_member_id }}"
                                    data-member-name="{{ $m->first_name }} {{ $m->last_name }}"
                                    data-member-phone="{{ $m->phone_number }}"
                                    data-member-route="{{ $m->route_name }}"
                                    data-member-stop="{{ $m->stop_name }}">
                                    <td>
                                        <strong>{{ $m->first_name }} {{ $m->last_name }}</strong>
                                    </td>
                                    <td>{{ $m->phone_number }}</td>
                                    <td>
                                        <span class="badge badge-info">{{ $m->route_name }}</span><br>
                                        <small>{{ $m->stop_name }}</small>
                                    </td>
                                    <td class="action-buttons">
                                        @if($m->today_record)
                                            <button class="btn btn-warning btn-sm revert-btn"
                                                data-id="{{ $m->tiu_member_id }}"
                                                data-att-id="{{ $m->today_record }}">
                                                <i class="fa fa-undo"></i> Revert
                                            </button>
                                        @else
                                            <button class="btn btn-success btn-sm mark-btn" data-id="{{ $m->tiu_member_id }}">
                                                Mark Present
                                            </button>
                                        @endif

                                        @if($isAdmin)
                                            <button class="btn btn-danger btn-sm delete-member-btn"
                                                data-id="{{ $m->tiu_member_id }}">
                                                <i class="fa fa-trash"></i> Delete
                                            </button>
                                        @else
                                            <button class="btn btn-info btn-sm request-delete-btn"
                                                data-id="{{ $m->tiu_member_id }}"
                                                data-name="{{ $m->first_name }} {{ $m->last_name }}"
                                                data-phone="{{ $m->phone_number }}"
                                                data-route="{{ $m->route_name }}"
                                                data-stop="{{ $m->stop_name }}">
                                                <i class="fab fa-whatsapp"></i> Request Delete
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        No records found for the selected criteria. (Only members with a bus route assigned are shown)
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .action-buttons .btn {
        margin-right: 5px;
        margin-bottom: 5px;
    }
    .action-buttons {
        white-space: nowrap;
    }
    @media (max-width: 768px) {
        .action-buttons {
            white-space: normal;
        }
    }
</style>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    function handleAjaxError(xhr, defaultMsg) {
        let msg = defaultMsg;
        if (xhr.responseText) {
            try {
                const json = JSON.parse(xhr.responseText);
                if (json.message) msg = json.message;
            } catch (e) {
                msg = "Server error. Please try again.";
            }
        }
        alert(msg);
    }

    function sendWhatsAppRequest(memberId, memberName, memberPhone, route, stop) {
        let text = `Please delete duplicate bus registration:%0A`
                 + `Name: ${encodeURIComponent(memberName)}%0A`
                 + `Phone: ${encodeURIComponent(memberPhone)}%0A`
                 + `Route: ${encodeURIComponent(route)}%0A`
                 + `Stop: ${encodeURIComponent(stop)}%0A`
                 + `Member ID: ${memberId}`;
        let url = `https://wa.me/2348061355032?text=${text}`;
        window.open(url, '_blank');
    }

    // Request Delete button click (non-admin)
    $('body').on('click', '.request-delete-btn', function() {
        let btn = $(this);
        sendWhatsAppRequest(
            btn.data('id'),
            btn.data('name'),
            btn.data('phone'),
            btn.data('route'),
            btn.data('stop')
        );
    });

    // Mark Present
    $('body').on('click', '.mark-btn', function() {
        let btn = $(this);
        let memberId = btn.data('id');
        let actionCell = btn.closest('.action-buttons');
        let row = btn.closest('tr');

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: '{{ route("transport.mark-attendance-ajax") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                tiu_member_id: memberId
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    let memberName = row.data('member-name');
                    let memberPhone = row.data('member-phone');
                    let memberRoute = row.data('member-route');
                    let memberStop = row.data('member-stop');

                    @if($isAdmin)
                        let newButtons = `
                            <button class="btn btn-warning btn-sm revert-btn" data-id="${memberId}" data-att-id="${res.attendance_id}">
                                <i class="fa fa-undo"></i> Revert
                            </button>
                            <button class="btn btn-danger btn-sm delete-member-btn" data-id="${memberId}">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        `;
                    @else
                        let newButtons = `
                            <button class="btn btn-warning btn-sm revert-btn" data-id="${memberId}" data-att-id="${res.attendance_id}">
                                <i class="fa fa-undo"></i> Revert
                            </button>
                            <button class="btn btn-info btn-sm request-delete-btn" 
                                data-id="${memberId}"
                                data-name="${memberName}"
                                data-phone="${memberPhone}"
                                data-route="${memberRoute}"
                                data-stop="${memberStop}">
                                <i class="fab fa-whatsapp"></i> Request Delete
                            </button>
                        `;
                    @endif
                    actionCell.html(newButtons);
                } else {
                    alert(res.message);
                    btn.prop('disabled', false).html('Mark Present');
                }
            },
            error: function(xhr) {
                handleAjaxError(xhr, "System Error: Could not log attendance.");
                btn.prop('disabled', false).html('Mark Present');
            }
        });
    });

    // Revert attendance
    $('body').on('click', '.revert-btn', function() {
        if (!confirm('Unmark this member as present? Today\'s attendance will be removed.')) return;
        let btn = $(this);
        let attId = btn.data('att-id');
        let memberId = btn.data('id');
        let actionCell = btn.closest('.action-buttons');
        let row = btn.closest('tr');

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: '{{ route("transport.revert-attendance") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                attendance_id: attId
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    let memberName = row.data('member-name');
                    let memberPhone = row.data('member-phone');
                    let memberRoute = row.data('member-route');
                    let memberStop = row.data('member-stop');

                    @if($isAdmin)
                        let newButtons = `
                            <button class="btn btn-success btn-sm mark-btn" data-id="${memberId}">
                                Mark Present
                            </button>
                            <button class="btn btn-danger btn-sm delete-member-btn" data-id="${memberId}">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        `;
                    @else
                        let newButtons = `
                            <button class="btn btn-success btn-sm mark-btn" data-id="${memberId}">
                                Mark Present
                            </button>
                            <button class="btn btn-info btn-sm request-delete-btn" 
                                data-id="${memberId}"
                                data-name="${memberName}"
                                data-phone="${memberPhone}"
                                data-route="${memberRoute}"
                                data-stop="${memberStop}">
                                <i class="fab fa-whatsapp"></i> Request Delete
                            </button>
                        `;
                    @endif
                    actionCell.html(newButtons);
                } else {
                    alert(res.message);
                    btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Revert');
                }
            },
            error: function(xhr) {
                handleAjaxError(xhr, "Error reverting attendance.");
                btn.prop('disabled', false).html('<i class="fa fa-undo"></i> Revert');
            }
        });
    });

    // Delete member (admin only)
    $('body').on('click', '.delete-member-btn', function() {
        if (!confirm('WARNING: This will permanently delete the member\'s bus registration and ALL attendance records.\nThis action cannot be undone. Proceed?')) return;
        let btn = $(this);
        let memberId = btn.data('id');
        let row = $('#member-row-' + memberId);

        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            url: '{{ route("transport.delete-member") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                tiu_member_id: memberId
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    row.fadeOut(400, function() { $(this).remove(); });
                    $('.ms-panel-body').prepend(
                        '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                        'Member and all attendance records deleted.' +
                        '<button type="button" class="close" data-dismiss="alert">&times;</button></div>'
                    );
                    setTimeout(function() { $('.alert').alert('close'); }, 3000);
                } else {
                    alert(res.message);
                    btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Delete');
                }
            },
            error: function() {
                alert("Error deleting member. Please try again.");
                btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Delete');
            }
        });
    });
});
</script>
@endpush
