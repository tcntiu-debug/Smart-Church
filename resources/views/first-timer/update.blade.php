@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>First Timer Update Log</h6>
    </div>
    <div class="ms-panel-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Update</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($updates as $i => $u)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $u->first_name }} {{ $u->last_name }}</td>
                        <td>{{ $u->phone_number }}</td>
                        <td>{{ $u->update_text }}</td>
                        <td>{{ \Carbon\Carbon::parse($u->created_at)->format('M j, Y g:i A') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-4 text-muted">No updates logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
