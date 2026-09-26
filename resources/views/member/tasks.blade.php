@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>My Assigned First Timers</h6>
    </div>
    <div class="ms-panel-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Register Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments ?? [] as $assignment)
                    <tr>
                        <td>{{ $assignment->first_name ?? '' }} {{ $assignment->last_name ?? '' }}</td>
                        <td>{{ $assignment->phone_number ?? 'N/A' }}</td>
                        <td>{{ $assignment->status ?? 'Pending' }}</td>
                        <td>{{ $assignment->timeStamp_registered ?? '' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center">No assigned first timers</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection