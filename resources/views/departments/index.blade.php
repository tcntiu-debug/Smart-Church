@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Manage Departments</li>
            </ol>
        </nav>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <!-- Add Department Panel -->
        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6>Add New Department</h6>
            </div>
            <div class="ms-panel-body">
                <form action="{{ route('departments.store') }}" method="POST">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Department Name</label>
                            <input type="text" class="form-control" name="dept_name" placeholder="e.g., Ushering Department" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Department Type</label>
                            <select name="dept_type" class="form-control" required>
                                <option selected disabled value="">Choose...</option>
                                <option value="House Fellowship">House Fellowship</option>
                                <option value="Department">Department</option>
                                <option value="Cluster">Cluster</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Address</label>
                            <textarea class="form-control" name="dept_address" rows="3" placeholder="Enter address (if applicable)"></textarea>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Community (Optional)</label>
                            <select class="form-control" name="community_id">
                                <option value="">-- No Community --</option>
                                @foreach($communities as $community)
                                    <option value="{{ $community->id }}">{{ $community->community_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </form>
            </div>
        </div>

        <!-- Department List Panel -->
        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6>Department List</h6>
            </div>
            <div class="ms-panel-body">
                <div class="table-responsive">
                    <table class="table table-hover thead-primary" id="order-listing">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Address</th>
                                <th>Lead</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($departments as $dept)
                                <tr>
                                    <td>{{ $dept->dept_id }}</td>
                                    <td>{{ $dept->dept_name }}</td>
                                    <td>{{ $dept->dept_type }}</td>
                                    <td>{{ $dept->dept_address }}</td>
                                    <td>{{ $dept->dept_lead }}</td>
                                    <td class="text-center">
                                        <i class="fas fa-edit action-icon"
                                           data-toggle="modal" data-target="#editDepartmentModal"
                                           data-id="{{ $dept->dept_id }}"
                                           data-name="{{ $dept->dept_name }}"
                                           data-type="{{ $dept->dept_type }}"
                                           data-address="{{ $dept->dept_address }}"
                                           data-community-id="{{ $dept->community_id }}"
                                           data-lead="{{ $dept->dept_lead }}"
                                           data-lead-id="{{ $dept->dept_lead_id }}"
                                           style="cursor: pointer; color: #007bff; font-size: 1.2rem;"></i>
                                        <a href="{{ route('departments.delete', $dept->dept_id) }}"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete {{ $dept->dept_name }}?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No departments found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Department</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('departments.update') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="dept_id" id="editDeptId">
                    <input type="hidden" name="dept_lead_id" id="editDeptLeadId">

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Department Name</label>
                            <input type="text" class="form-control" id="editDeptName" name="dept_name">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Department Type (Read-only)</label>
                            <input type="text" class="form-control" id="editDeptType" name="dept_type" disabled>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="editDeptAddress">Address (Editable)</label>
                            <textarea class="form-control" id="editDeptAddress" name="dept_address" rows="3"></textarea>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="editCommunityId">Community (Editable)</label>
                            <select class="form-control" id="editCommunityId" name="community_id">
                                <option value="">-- No Community --</option>
                                @foreach($communities as $community)
                                    <option value="{{ $community->id }}">{{ $community->community_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editDeptLead">Department Lead (Editable)</label>
                        <select class="form-control" id="editDeptLead" name="dept_lead">
                            <option>Loading...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#editDepartmentModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);

        var deptId = button.data('id');
        var deptName = button.data('name');
        var deptType = button.data('type');
        var deptAddress = button.data('address');
        var communityId = button.data('community-id');
        var currentLead = button.data('lead');
        var currentLeadId = button.data('lead-id');

        var modal = $(this);
        var leadSelect = modal.find('#editDeptLead');

        // Clean currentLead if it contains JSON
        try {
            if (currentLead && currentLead.startsWith('{')) {
                var leadData = JSON.parse(currentLead);
                currentLead = leadData.name || '';
                currentLeadId = leadData.id || currentLeadId;
            }
        } catch (e) {
            console.log('Not JSON, using as-is');
        }

        modal.find('.modal-title').text('Edit: ' + deptName);
        modal.find('#editDeptId').val(deptId);
        modal.find('#editDeptName').val(deptName);
        modal.find('#editDeptType').val(deptType);
        modal.find('#editDeptAddress').val(deptAddress);
        modal.find('#editCommunityId').val(communityId);
        modal.find('#editDeptLeadId').val(currentLeadId || '');

        // AJAX request to fetch members
        leadSelect.html('<option value="">Loading members...</option>');
        $.ajax({
            url: '{{ url("/departments/members") }}',
            type: 'GET',
            data: {
                dept_id: deptId,
                dept_type: deptType
            },
            dataType: 'json',
            success: function(members) {
                leadSelect.empty();
                leadSelect.append('<option value="">-- No Lead --</option>');

                if (members.length > 0) {
                    $.each(members, function(index, member) {
                        var option = $('<option></option>')
                            .val(member.full_name)
                            .text(member.full_name)
                            .data('member-id', member.member_id);

                        if (member.member_id == currentLeadId) {
                            option.prop('selected', true);
                        } else if (!currentLeadId && member.full_name === currentLead) {
                            option.prop('selected', true);
                            $('#editDeptLeadId').val(member.member_id);
                        }

                        leadSelect.append(option);
                    });
                } else {
                    leadSelect.append('<option value="">No members found</option>');
                }

                if (currentLead && !leadSelect.val()) {
                    leadSelect.prepend($('<option></option>')
                        .val(currentLead)
                        .text(currentLead + ' (Current)')
                        .prop('selected', true)
                        .data('member-id', currentLeadId || ''));
                }
            },
            error: function() {
                leadSelect.empty();
                leadSelect.append('<option value="">Error loading members</option>');
            }
        });

        leadSelect.off('change').on('change', function() {
            var selectedOption = $(this).find('option:selected');
            $('#editDeptLeadId').val(selectedOption.data('member-id') || '');
        });
    });
});
</script>
<style>
    .action-icon {
        cursor: pointer;
        color: #007bff;
        font-size: 1.2rem;
    }
    .action-icon:hover {
        color: #0056b3;
    }
    .ms-content-wrapper {
        padding: 20px;
    }
    .ms-panel {
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }
    .ms-panel-header {
        background: #f8f9fa;
        padding: 15px 20px;
    }
</style>
@endpush
