@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>First Timer Gallery</h6>
    </div>
    <div class="ms-panel-body">
        <div class="row">
            @forelse($photos as $p)
            <div class="col-md-3 mb-4">
                <div class="card">
                    <img src="{{ asset($p->photo_url) }}" class="card-img-top" style="height:200px;object-fit:cover;">
                    <div class="card-body">
                        <p class="mb-0 small text-muted">{{ $p->caption ?? '' }}</p>
                        <small>{{ \Carbon\Carbon::parse($p->created_at)->format('M j, Y') }}</small>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center py-5">
                <i class="fas fa-images fa-4x text-muted mb-3"></i>
                <p class="text-muted">No photos in the gallery yet.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
