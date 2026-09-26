@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Assign Subgroups</li>
            </ol>
        </nav>

        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6>Create New Subgroup</h6>
            </div>
            <div class="ms-panel-body">
                <form id="create-subgroup-form">
                    @csrf
                    <div id="creation-status" class="mb-3"></div>
                    <div class="form-row">
                        <div class="col-md-5 mb-3">
                            <label for="new-subgroup-department">Department Name</label>
                                <select class="form-control" id="new-subgroup-department" name="department_id" required>
                                    <option value="" selected disabled>-- Choose a Department --</option>
                                    @foreach($departments as $dept)
                                    <option value="{{ $dept->dept_id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="new-subgroup-name">Subgroup Name</label>
                            <input type="text" class="form-control" id="new-subgroup-name" name="sub_group_name" placeholder="e.g., Touch Point 3" required>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Create</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6>Filter Members by Department</h6>
            </div>
            <div class="ms-panel-body">
                <form id="filter-form" class="form-inline">
                                <select class="form-control mr-sm-2" id="department-filter" name="department" required>
                                    <option value="" selected disabled>-- Choose a Department --</option>
                                    @foreach($departments as $dept)
                                    <option value="{{ $dept->dept_id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>

        <div id="assignment-area">
            <div class="ms-panel">
                <div class="ms-panel-body">
                    <p class="text-center">Please select a department and click "Filter" to view members and subgroups.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Promoting a Member to Lead -->
<div class="modal fade" id="promote-lead-modal" tabindex="-1" role="dialog" aria-labelledby="promoteLeadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="promoteLeadModalLabel">Promote to Lead</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to make <strong id="modal-member-name"></strong> the new Lead for this subgroup?</p>
                <p class="text-warning"><small>If a lead already exists for this subgroup, they will be demoted to 'Member'. This action cannot be undone.</small></p>
                <div id="modal-status-message" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="modal-member-id">
                <input type="hidden" id="modal-subgroup-id">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-promotion-btn">Confirm Promotion</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link href="{{ asset('assets/css/jquery-ui.min.css') }}" rel="stylesheet">
<script src="{{ asset('assets/js/jquery-ui.min.js') }}"></script>
<script>
$(document).ready(function() {

    //==========================================================
    // 1. FILTER & DATA LOADING LOGIC
    //==========================================================

    var currentCampusId = {{ $campusId ?? 1 }};

    function loadFilteredData(department) {
        if (!department) return;

        localStorage.setItem('lastSelectedDepartment', department);

        $('#assignment-area').html('<div class="ms-panel"><div class="ms-panel-body text-center">Loading...</div></div>');
        $.ajax({
            url: '{{ url("/group/fetch") }}',
            type: 'GET',
            data: { department: department, campus_id: currentCampusId },
            success: function(response) {
                $('#assignment-area').html(response);
                initializeSortable();
            },
            error: function() {
                $('#assignment-area').html('<div class="ms-panel"><div class="ms-panel-body text-center text-danger">Failed to load data. Please try again.</div></div>');
            }
        });
    }

    $('#filter-form').on('submit', function(e) {
        e.preventDefault();
        loadFilteredData($('#department-filter').val());
    });

    const savedDepartment = localStorage.getItem('lastSelectedDepartment');
    if (savedDepartment) {
        $('#department-filter').val(savedDepartment);
        loadFilteredData(savedDepartment);
    }

    //==========================================================
    // 2. DRAG-AND-DROP INITIALIZATION
    //==========================================================
    function initializeSortable() {
        var currentDepartment = $('#department-filter').val();
        $(".ms-list").sortable({
            connectWith: ".ms-list",
            receive: function(event, ui) {
                var memberId = ui.item.data("member-id");
                var newSubgroupListId = $(this).attr("id");
                var updateColumn = $(this).data("update-column");
                var newSubgroup = $(this).data("subgroup-name") || '';

                var oldSubgroup = '';
                if (ui.sender) {
                    oldSubgroup = ui.sender.data("subgroup-name") || '';
                }


                $.ajax({
                    type: "POST",
                    url: '{{ url("/group/assign") }}',
                    data: {
                        _token: '{{ csrf_token() }}',
                        member_id: memberId,
                        new_subgroup: newSubgroup,
                        old_subgroup: oldSubgroup,
                        update_column: updateColumn,
                        department_id: currentDepartment
                    },
                    success: function(response) {
                        console.log('Assignment saved to DB: ' + response);
                        // Auto-refresh the view so the member appears in the correct list
                        loadFilteredData(currentDepartment);
                    },
                    error: function(xhr) {
                        alert('Server error during assignment. Please refresh and try again.');
                        $(ui.sender).sortable('cancel');
                    }
                });
            }
        }).disableSelection();
    }

    //==========================================================
    // 3. SUBGROUP CREATION LOGIC
    //==========================================================
    $('#create-subgroup-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var statusDiv = $('#creation-status');
        var createButton = form.find('button[type="submit"]');

        createButton.prop('disabled', true).text('Creating...');
        statusDiv.html('');

        $.ajax({
            type: 'POST',
            url: '{{ url("/group/create") }}',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    statusDiv.html('<div class="alert alert-success">' + response.message + '</div>');
                    form.trigger('reset');
                    var selectedDepartment = $('#department-filter').val();
                    if (selectedDepartment && selectedDepartment === $('#new-subgroup-department').val()) {
                        $('#filter-form').submit();
                    }
                } else {
                    statusDiv.html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function() {
                statusDiv.html('<div class="alert alert-danger">An unexpected error occurred. Please try again.</div>');
            },
            complete: function() {
                createButton.prop('disabled', false).text('Create');
            }
        });
    });

    //==========================================================
    // 4. SUBGROUP DELETION LOGIC
    //==========================================================
    $('#assignment-area').on('click', '.delete-subgroup', function(e) {
        e.preventDefault();
        let deleteIcon = $(this);
        let subId = deleteIcon.data('subid');
        let panel = deleteIcon.closest('.subgroup-panel');
        let subGroupName = panel.find('h4').text();
        let memberList = panel.find('.ms-list');

        if (memberList.children('li').length > 0) {
            alert('Error: You cannot delete "' + subGroupName + '" because it contains members. Please move all members out first.');
            return;
        }

        if (confirm('Are you sure you want to permanently delete the subgroup "' + subGroupName + '"? This cannot be undone.')) {
            $.ajax({
                type: 'POST',
                url: '{{ url("/group/delete") }}',
                data: {
                    _token: '{{ csrf_token() }}',
                    subid: subId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        panel.fadeOut('slow', function() { $(this).remove(); });
                    } else {
                        alert('Deletion failed: ' + response.message);
                    }
                },
                error: function() {
                    alert('An unexpected server error occurred during deletion.');
                }
            });
        }
    });

    //==========================================================
    // 5. PROMOTE TO LEAD MODAL LOGIC
    //==========================================================
    $('#assignment-area').on('click', '.promote-to-lead', function(e) {
        e.preventDefault();
        var memberId = $(this).data('member-id');
        var memberName = $(this).data('member-name');
        var subgroupId = $(this).data('subgroup-id');

        $('#modal-member-id').val(memberId);
        $('#modal-subgroup-id').val(subgroupId);
        $('#modal-member-name').text(memberName);
        $('#modal-status-message').html('');
        $('#promote-lead-modal').modal('show');
    });

    $('#confirm-promotion-btn').on('click', function() {
        var memberId = $('#modal-member-id').val();
        var subgroupId = $('#modal-subgroup-id').val();
        var statusDiv = $('#modal-status-message');
        var confirmButton = $(this);

        confirmButton.prop('disabled', true).text('Promoting...');
        statusDiv.html('');

        $.ajax({
            type: 'POST',
            url: '{{ url("/group/promote") }}',
            data: {
                _token: '{{ csrf_token() }}',
                tiu_member_id: memberId,
                sub_group_id: subgroupId
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    statusDiv.html('<div class="alert alert-success">' + response.message + '</div>');
                    setTimeout(function() {
                        $('#promote-lead-modal').modal('hide');
                        $('#filter-form').submit();
                    }, 1500);
                } else {
                    statusDiv.html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function() {
                statusDiv.html('<div class="alert alert-danger">An unexpected server error occurred.</div>');
            },
            complete: function() {
                confirmButton.prop('disabled', false).text('Confirm Promotion');
            }
        });
    });

});
</script>
@endpush
