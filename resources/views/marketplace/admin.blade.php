@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>Marketplace Administration</h6>
    </div>
    <div class="ms-panel-body">
        <ul class="nav nav-tabs" id="marketplaceTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#pending">Pending Approval ({{ $pendingApproval->count() }})</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#deactivation">Deactivation Requests ({{ $pendingDeactivation->count() }})</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#active">Active ({{ $activeBusinesses->count() }})</a>
            </li>
        </ul>

        <div class="tab-content mt-3">
            <div class="tab-pane active" id="pending">
                @forelse($pendingApproval as $b)
                <div class="card mb-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">{{ $b->business_name }}</h6>
                            <small>{{ $b->first_name }} {{ $b->last_name }} — {{ $b->category_name }}</small>
                        </div>
                        <form method="POST" action="{{ route('marketplace.approve') }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="business_id" value="{{ $b->business_id }}">
                            <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                        </form>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center py-3">No pending approvals.</p>
                @endforelse
            </div>
            <div class="tab-pane" id="deactivation">
                @forelse($pendingDeactivation as $b)
                <div class="card mb-2">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">{{ $b->business_name }}</h6>
                            <small>{{ $b->first_name }} {{ $b->last_name }} — {{ $b->category_name }}</small>
                        </div>
                        <form method="POST" action="{{ route('marketplace.deactivate') }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="business_id" value="{{ $b->business_id }}">
                            <button class="btn btn-warning btn-sm"><i class="fas fa-ban"></i> Deactivate</button>
                        </form>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center py-3">No deactivation requests.</p>
                @endforelse
            </div>
            <div class="tab-pane" id="active">
                @forelse($activeBusinesses as $b)
                <div class="card mb-2">
                    <div class="card-body">
                        <h6 class="mb-0">{{ $b->business_name }}</h6>
                        <small>{{ $b->first_name }} {{ $b->last_name }} — {{ $b->category_name }}</small>
                        <form method="POST" action="{{ route('marketplace.deactivate') }}" style="display:inline;float:right">
                            @csrf
                            <input type="hidden" name="business_id" value="{{ $b->business_id }}">
                            <button class="btn btn-danger btn-sm"><i class="fas fa-ban"></i></button>
                        </form>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center py-3">No active businesses.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
