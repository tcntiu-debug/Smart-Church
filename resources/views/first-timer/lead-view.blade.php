@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>First Timer Lead View</h6>
    </div>
    <div class="ms-panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Service</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($firstTimers as $i => $ft)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $ft->first_name }} {{ $ft->last_name }}</td>
                        <td>{{ $ft->phone_number }}</td>
                        <td>{{ $ft->service_type ?? '—' }}</td>
                        <td><span class="badge badge-{{ $ft->status == 'active' ? 'success' : 'warning' }}">{{ $ft->status }}</span></td>
                        <td>{{ $ft->reg_date ? \Carbon\Carbon::parse($ft->reg_date)->format('M j, Y') : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">No first timers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
