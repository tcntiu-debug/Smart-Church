@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>Notice Board</h6>
    </div>
    <div class="ms-panel-body">
        @forelse($announcements as $a)
        <div class="card mb-3 border-left border-primary border-3">
            <div class="card-body">
                <h5>{{ $a->title }}</h5>
                <p>{{ $a->content }}</p>
                <small class="text-muted">Posted {{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}</small>
            </div>
        </div>
        @empty
        <div class="text-center py-5">
            <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
            <p class="text-muted">No announcements posted yet.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
