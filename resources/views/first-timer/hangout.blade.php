@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>First Timer Hangout</h6>
    </div>
    <div class="ms-panel-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Invited</th>
                        <th>Confirmed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hangouts as $i => $h)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $h->first_name }} {{ $h->last_name }}</td>
                        <td>{{ $h->phone_number }}</td>
                        <td>{{ $h->invited ? \Carbon\Carbon::parse($h->invited)->format('M j, Y') : '—' }}</td>
                        <td>{{ $h->confirmed ? \Carbon\Carbon::parse($h->confirmed)->format('M j, Y') : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-4 text-muted">No hangout records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
