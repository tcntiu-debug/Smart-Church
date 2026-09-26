@extends('layouts.app')

@section('content')
<style>
    /* Responsive table for mobile (same as mview) */
    @media (max-width: 767px) {
        #children-listing thead { display: none; }
        #children-listing, #children-listing tbody, #children-listing tr, #children-listing td {
            display: block; width: 100%;
        }
        #children-listing tr {
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        #children-listing td {
            text-align: right; padding-left: 50%;
            position: relative; border-bottom: 1px solid #eee;
            padding-top: 12px; padding-bottom: 12px;
        }
        #children-listing td:last-child { border-bottom: 0; }
        #children-listing td::before {
            content: attr(data-label); position: absolute; left: 15px;
            width: 45%; padding-right: 10px; white-space: nowrap;
            text-align: left; font-weight: bold; color: #333;
        }
    }

    .btn-edit-sm {
        background: #3e52a3; color: white; border: none;
        padding: 4px 12px; border-radius: 4px; font-weight: 600; font-size: 0.75rem;
        cursor: pointer; transition: all 0.2s;
    }
    .btn-edit-sm:hover { background: #2d3f8a; }

    .modal-header-custom {
        background: #eab308; color: white; border-radius: 0; padding: 14px 20px;
    }
    .modal-header-custom .close { color: white; opacity: 0.8; }
    .dataTables_filter input { border-radius: 4px; border: 1px solid #ccc; padding: 6px 12px; }
</style>

<div class="ms-panel">
    <div class="ms-panel-header d-flex justify-content-between align-items-center flex-wrap">
        <h6>CHILDREN CHURCH RECORDS</h6>
        <div>
            <span class="badge badge-primary p-2 mr-2">{{ $children->count() }} child{{ $children->count() != 1 ? 'ren' : '' }} registered</span>
            <a href="{{ route('children-church.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Check-In
            </a>
        </div>
    </div>
    <div class="ms-panel-body">
        <div class="table-responsive">
            <table id="children-listing" class="table table-hover thead-primary w-100">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Child's Name</th>
                        <th>Parent/Guardian</th>
                        <th>Date of Birth</th>
                        <th>Phone</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($children as $child)
                    <tr>
                        <td data-label="No.">{{ $loop->iteration }}</td>
                        <td data-label="Child's Name"><strong>{{ htmlspecialchars($child->child_name) }}</strong></td>
                        <td data-label="Parent/Guardian">{{ $child->parent_name ? htmlspecialchars($child->parent_name) : '—' }}</td>
                        <td data-label="Date of Birth">{{ $child->dob ?: '—' }}</td>
                        <td data-label="Phone">{{ $child->parent_phone ?: '—' }}</td>
                        <td data-label="Action">
                            <button class="btn-edit-sm" 
                                    data-id="{{ $child->child_id }}"
                                    data-child_name="{{ $child->child_name }}"
                                    data-parent_name="{{ $child->parent_name }}"
                                    data-dob="{{ $child->dob }}"
                                    data-parent_phone="{{ $child->parent_phone }}"
                                    onclick="openEditModal(this)">
                                <i class="fas fa-pen"></i> Edit
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No children records found for your campus.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title fw-bold"><i class="fas fa-pen"></i> Edit Child Record</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editForm">
                @csrf
                <input type="hidden" name="child_id" id="edit_child_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Child's Name <span class="text-danger">*</span></label>
                        <input type="text" name="child_name" id="edit_child_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Parent/Guardian Name</label>
                        <input type="text" name="parent_name" id="edit_parent_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" id="edit_dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Parent/Guardian Phone</label>
                        <input type="text" name="parent_phone" id="edit_parent_phone" class="form-control">
                    </div>
                    <div class="alert alert-danger mt-3" id="editFormError" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editSubmitBtn">
                        <i class="fas fa-save"></i> Update Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/datatables.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('#children-listing').DataTable({
        "aLengthMenu": [[5, 10, 15, -1], [5, 10, 15, "All"]],
        "iDisplayLength": 10,
        "language": { search: "" },
        order: [[0, 'asc']]
    });
});

// Open edit modal
function openEditModal(btn) {
    const data = btn.dataset;
    document.getElementById('edit_child_id').value = data.id;
    document.getElementById('edit_child_name').value = data.child_name;
    document.getElementById('edit_parent_name').value = data.parent_name;
    document.getElementById('edit_parent_phone').value = data.parent_phone;
    document.getElementById('edit_dob').value = data.dob;
    document.getElementById('editFormError').style.display = 'none';
    $('#editModal').modal('show');
}

// Submit edit
$('#editForm').on('submit', function(e) {
    e.preventDefault();
    const childId = document.getElementById('edit_child_id').value;
    const btn = $('#editSubmitBtn');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');
    $('#editFormError').hide();

    $.ajax({
        url: '{{ route("children-church.update", "") }}/' + childId,
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                $('#editModal').modal('hide');
                alert(resp.message);
                location.reload();
            } else {
                $('#editFormError').text(resp.message).show();
            }
        },
        error: function(xhr) {
            const msg = xhr.responseJSON?.message || 'Error updating record.';
            $('#editFormError').text(msg).show();
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Record');
        }
    });
});
</script>
@endpush
