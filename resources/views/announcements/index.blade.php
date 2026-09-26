@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>Announcements Management</h6>
    </div>
    <div class="ms-panel-body">
        <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#announcementModal">
            <i class="fas fa-plus"></i> New Announcement
        </button>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @forelse($announcements as $a)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">{{ $a->title }}</h6>
                <small class="text-muted">{{ \Carbon\Carbon::parse($a->created_at)->format('M j, Y g:i A') }}</small>
            </div>
            <div class="card-body">
                <p>{{ $a->content }}</p>
                <div class="text-right">
                    <button class="btn btn-sm btn-info edit-announcement" 
                            data-id="{{ $a->id }}" data-title="{{ $a->title }}" data-content="{{ $a->content }}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <a href="{{ route('announcements.delete', $a->id) }}" class="btn btn-sm btn-danger" 
                       onclick="return confirm('Delete this announcement?')"><i class="fas fa-trash"></i></a>
                </div>
            </div>
        </div>
        @empty
        <p class="text-muted text-center py-4">No announcements yet.</p>
        @endforelse
    </div>
</div>

<!-- Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('announcements.store') }}">
                @csrf
                <input type="hidden" name="announcement_id" id="announcement_id">
                <div class="modal-header">
                    <h5 class="modal-title">Announcement</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" class="form-control" name="title" id="ann_title" required>
                    </div>
                    <div class="form-group">
                        <label>Content</label>
                        <textarea class="form-control" name="content" id="ann_content" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    $('.edit-announcement').on('click', function() {
        $('#announcement_id').val($(this).data('id'));
        $('#ann_title').val($(this).data('title'));
        $('#ann_content').val($(this).data('content'));
        $('#announcementModal form').attr('action', '{{ route("announcements.update") }}');
        $('#announcementModal').modal('show');
    });
    $('#announcementModal').on('hidden.bs.modal', function() {
        $('#announcement_id').val('');
        $('#ann_title').val('');
        $('#ann_content').val('');
        $('#announcementModal form').attr('action', '{{ route("announcements.store") }}');
    });
});
</script>
@endpush
