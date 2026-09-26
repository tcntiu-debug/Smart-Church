@extends('layouts.app')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Manage Shared Resources</li>
            </ol>
        </nav>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <!-- Upload Form Panel -->
        <div class="row">
            <div class="col-xl-4 col-md-12">
                <div class="ms-panel">
                    <div class="ms-panel-header">
                        <h6>Upload New Resource</h6>
                    </div>
                    <div class="ms-panel-body">
                        <form id="upload-resource-form" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="resource_title">Resource Title</label>
                                <input type="text" class="form-control" id="resource_title" name="resource_title" required>
                            </div>
                            <div class="form-group">
                                <label for="resource_description">Description (Optional)</label>
                                <textarea class="form-control" id="resource_description" name="resource_description" rows="3"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="resource_file">File</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="resource_file" name="resource_file" required>
                                    <label class="custom-file-label" for="resource_file">Choose file...</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">
                                <span id="upload-spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display: none;"></span>
                                Upload Resource
                            </button>
                            <div id="upload-status" class="mt-3"></div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Resources List Panel -->
            <div class="col-xl-8 col-md-12">
                <div class="ms-panel">
                    <div class="ms-panel-header">
                        <h6>Existing Resources</h6>
                    </div>
                    <div class="ms-panel-body">
                        <div class="table-responsive">
                            <table class="table table-hover thead-primary">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>File Name</th>
                                        <th>Uploaded By</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="resources-table-body">
                                    @forelse($resources as $resource)
                                    <tr id="resource-row-{{ $resource->id }}">
                                        <td>{{ $resource->title }}</td>
                                        <td><a href="{{ asset($resource->file_path) }}" target="_blank">{{ $resource->file_name }}</a></td>
                                        <td>{{ $resource->first_name }} {{ $resource->last_name }}</td>
                                        <td>{{ \Carbon\Carbon::parse($resource->upload_date)->format('M d, Y') }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="#" onclick="openShareModal({{ $resource->id }})">Share</a>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteResource({{ $resource->id }})">Delete</a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No resources uploaded yet.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" role="dialog" aria-labelledby="shareModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareModalLabel">Share Resource</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="share-form">
                    <input type="hidden" id="share_resource_id" name="resource_id">
                    
                    <div class="form-group">
                        <label>Sharing Type</label>
                        <div class="d-flex">
                            <div class="custom-control custom-radio mr-4">
                                <input type="radio" id="share_type_public" name="share_type" value="public" class="custom-control-input" checked>
                                <label class="custom-control-label" for="share_type_public">Public</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="share_type_private" name="share_type" value="private" class="custom-control-input">
                                <label class="custom-control-label" for="share_type_private">Private</label>
                            </div>
                        </div>
                        <small class="form-text text-muted">
                            <b>Public:</b> Visible to all current and future members.<br>
                            <b>Private:</b> Visible only to the specific members you select below.
                        </small>
                    </div>

                    <div id="private-share-options" style="display: none;">
                        <hr>
                        <div class="form-group">
                             <label for="share_members">Share with specific members:</label>
                             <select class="form-control" id="share_members" name="member_ids[]" multiple="multiple" style="width: 100%;">
                                 @foreach($members as $member)
                                     <option value="{{ $member->tiu_member_id }}">
                                         {{ $member->first_name }} {{ $member->last_name }}
                                     </option>
                                 @endforeach
                             </select>
                        </div>
                    </div>
                    
                    <div id="share-status" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveShareSettings()">Save Sharing</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#share_members').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#shareModal')
    });
    
    $('.custom-file-input').on('change', function() {
       let fileName = $(this).val().split('\\').pop();
       $(this).next('.custom-file-label').addClass('selected').html(fileName);
    });

    $('input[name="share_type"]').on('change', function() {
        if (this.value === 'private') {
            $('#private-share-options').slideDown();
        } else {
            $('#private-share-options').slideUp();
        }
    });

    $('#upload-resource-form').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        formData.append('_token', '{{ csrf_token() }}');
        $('#upload-spinner').show();
        $('#upload-status').html('');
        $.ajax({
            url: '{{ route("resources.upload-ajax") }}',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.status === 'success') {
                    $('#upload-status').html('<div class="alert alert-success">' + response.message + '</div>');
                    $('#upload-resource-form')[0].reset();
                    $('.custom-file-label').html('Choose file...');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    $('#upload-status').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function() {
                 $('#upload-status').html('<div class="alert alert-danger">An unexpected error occurred.</div>');
            },
            complete: function() {
                $('#upload-spinner').hide();
            }
        });
    });
});

function openShareModal(resourceId) {
    $('#share_resource_id').val(resourceId);
    $('#share_type_public').prop('checked', true);
    $('#private-share-options').hide();
    $('#share_members').val(null).trigger('change');
    $('#share-status').html('');
    $('#shareModal').modal('show');
}

function saveShareSettings() {
    let formData = $('#share-form').serialize() + '&_token={{ csrf_token() }}';
    $('#share-status').html('');
    $.ajax({
        url: '{{ route("resources.share") }}',
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.status === 'success') {
                $('#share-status').html('<div class="alert alert-success">' + response.message + '</div>');
                setTimeout(() => $('#shareModal').modal('hide'), 1500);
            } else {
                $('#share-status').html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function() {
            $('#share-status').html('<div class="alert alert-danger">An error occurred.</div>');
        }
    });
}

function deleteResource(resourceId) {
    if (confirm('Are you sure you want to delete this resource? This cannot be undone.')) {
        $.ajax({
            url: '{{ route("resources.delete-ajax") }}',
            type: 'POST',
            data: { 
                resource_id: resourceId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.status === 'success') {
                    $('#resource-row-' + resourceId).fadeOut(500, function() { $(this).remove(); });
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An unexpected error occurred.');
            }
        });
    }
}
</script>
@endpush
