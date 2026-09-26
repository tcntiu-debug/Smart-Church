@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>Airtime Topup Requests</h6>
    </div>
    <div class="ms-panel-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>User Name</th>
                        <th>User Phone</th>
                        <th>Year / Week</th>
                        <th>Requested At</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>{{ $r->user_name ?? '—' }}</td>
                        <td>{{ $r->user_phone ?? '—' }}</td>
                        <td>{{ $r->year }} / Week {{ $r->week }}</td>
                        <td>{{ $r->requested_at }}</td>
                        <td>
                            @if($r->credited)
                                <span class="badge badge-success">Credited</span><br>
                                <small>{{ $r->credited_at ?? '' }}</small>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </td>
                        <td>
                            @if(!$r->credited)
                                <form method="POST" action="{{ route('admin.airtime') }}">
                                    @csrf
                                    <input type="hidden" name="credit_id" value="{{ $r->id }}">
                                    <button class="btn btn-success btn-sm" onclick="return confirm('Mark as credited?')">Mark Credited</button>
                                </form>
                            @else
                                <button class="btn btn-secondary btn-sm" disabled>Done</button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
