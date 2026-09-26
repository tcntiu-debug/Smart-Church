@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6>Public Resources</h6>
            @if(in_array(Auth::user()->member_role, ['Super User', 'Admin']))
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#uploadModal">
                <i class="fas fa-upload"></i> Upload Resource
            </button>
            @endif
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(isset($resources) && $resources->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>File Name</th>
                            <th style="width: 100px;">Action</th>
                            <th>Uploaded On</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($resources as $resource)
                        <tr>
                            <td class="align-middle">{{ $resource->title }}</td>
                            <td class="align-middle">{{ Str::limit($resource->description ?? 'No description', 50) }}</td>
                            <td class="align-middle">{{ $resource->file_name }}</td>
                            <td class="align-middle">
                                <a href="{{ asset($resource->file_path) }}" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </td>
                            <td class="align-middle">{{ \Carbon\Carbon::parse($resource->upload_date)->format('F j, Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <p class="text-center text-muted">No public resources available at this time.</p>
            @endif
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('resources.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Upload Resource</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="title">Title *</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="description">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group mb-3">
                        <label for="file">File *</label>
                        <input type="file" class="form-control" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
                        <small class="text-muted">Max 10MB. Allowed: PDF, DOC, XLS, PPT, JPG, PNG</small>
                    </div>
                    <div class="form-group mb-3">
                        <label class="fw-bold">Share Type</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="share_type" id="share_public" value="public" checked>
                            <label class="form-check-label" for="share_public">Public (Visible to everyone)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="share_type" id="share_private" value="private">
                            <label class="form-check-label" for="share_private">Private (Share with specific members)</label>
                        </div>
                    </div>
                    <div class="form-group mb-3" id="share_with_container" style="display: none;">
                        <label for="share_with">Share with Members</label>
                        <select class="form-control" name="share_with[]" multiple size="5">
                            @foreach(DB::table('tiu_member')->orderBy('first_name')->get() as $member)
                                <option value="{{ $member->tiu_member_id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple members</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('input[name="share_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var container = document.getElementById('share_with_container');
            if (document.getElementById('share_private').checked) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        });
    });
</script>
@endpush
@endsection