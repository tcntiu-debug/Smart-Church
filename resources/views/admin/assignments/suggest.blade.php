@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Suggest Assignments - Generate Pairings</h5>
            <a href="{{ route('admin.assignments.index') }}" class="btn btn-sm btn-secondary">Back to Assignments</a>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @if($unassigned->isEmpty())
                <div class="alert alert-info text-center py-5">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h5>All First Timers Are Assigned!</h5>
                    <p class="text-muted">There are no unassigned first timers at this time.</p>
                </div>
            @else
                <form method="POST" action="{{ route('admin.assignments.generate') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Unassigned First Timers ({{ $unassigned->count() }})</h6>
                                </div>
                                <div class="card-body" style="max-height: 450px; overflow-y: auto;">
                                    <div class="form-check mb-3">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                        <label class="form-check-label fw-bold" for="selectAll">Select All</label>
                                    </div>
                                    @foreach($unassigned as $ft)
                                        <div class="form-check">
                                            <input type="checkbox" name="unassigned_ids[]" value="{{ $ft->first_timer_id }}" class="form-check-input ft-checkbox">
                                            <label class="form-check-label">
                                                {{ $ft->first_name }} {{ $ft->last_name }}
                                                @if($ft->phone_number) - {{ $ft->phone_number }} @endif
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Guides & Current Load</h6>
                                </div>
                                <div class="card-body" style="max-height: 350px; overflow-y: auto;">
                                    @foreach($guides as $guide)
                                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-bottom">
                                            <strong>{{ $guide->first_name }} {{ $guide->last_name }}</strong>
                                            <span class="badge bg-{{ $guide->assignment_count > 5 ? 'danger' : ($guide->assignment_count > 3 ? 'warning' : 'success') }}" style="float:right;">
                                                {{ $guide->assignment_count }} assigned
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mt-3">
                                <p class="text-muted small">
                                    <i class="fa fa-info-circle"></i> First timers will be evenly distributed among guides. Each guide gets one first timer in round-robin order.
                                </p>
                                <button type="submit" class="btn btn-primary btn-block w-100">
                                    <i class="fa fa-random"></i> Generate Pairings
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.ft-checkbox').forEach(function(cb) {
        cb.checked = this.checked;
    });
});
</script>
@endpush
@endsection
